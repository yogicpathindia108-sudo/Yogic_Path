<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Probe whether the site can reach itself over HTTP — the same
 * self-reachability test WordPress core's Site Health performs.
 *
 * Self-reachability is what wp-cron and every scheduled event depend on,
 * including this feature's bot-data refresh, storage cleanup, and rDNS
 * cache-warmer. A site that cannot loop back to its own home URL may not
 * run those jobs. The result is therefore informational — the caller
 * records it and surfaces a warning; it never changes whether the
 * mu-plugin shim stays installed.
 *
 * The probe is strictly synchronous with a short timeout so a hung
 * request cannot hold the activation handler indefinitely.
 *
 * @since 4.0.0
 */
class LoopbackSafetyTest {

	/**
	 * Connect + read timeout, in seconds. 5s is the Shield Security
	 * value; shorter timeouts trip on slow shared hosting where the WP
	 * bootstrap itself can take 2-3 seconds.
	 */
	const TIMEOUT_SECONDS = 5;

	/**
	 * Probe $home_url and classify the outcome into a {@see LoopbackResult}.
	 *
	 * Transport errors and an empty URL map to FAILED; any answered but
	 * non-200 status maps to INCONCLUSIVE, except a redirect to the probe's
	 * own origin while credentials are attached — see {@see isSameOriginRedirect()}.
	 * HTTP 200 maps to OK.
	 *
	 * @param string $home_url     Site home URL to probe.
	 * @param bool   $forward_auth Forward the current request's cookies and
	 *                             Basic Auth so an authenticated re-check is
	 *                             not turned away by a login wall or WAF. Off
	 *                             at activation time, where there is no request
	 *                             user.
	 * @return LoopbackResult
	 */
	public function run( $home_url, $forward_auth = false ) {
		if ( ! is_string( $home_url ) || '' === $home_url ) {
			return LoopbackResult::failed( 'empty_url' );
		}

		$args = array(
			'timeout'     => self::TIMEOUT_SECONDS,
			'redirection' => 2,
			'sslverify'   => false,
			// Tag the probe so logs can distinguish it from organic traffic.
			'user-agent'  => 'ImunifySecurity-Loopback/4.0',
		);
		// Only forward credentials to the site's own host over verified TLS.
		// Plain http would expose them in transit, and a foreign host (e.g. a
		// poisoned `home` option) would receive them outright, so the probe
		// runs unauthenticated in either case.
		$credentials_attached = $forward_auth && self::isSafeForwardTarget( $home_url );
		if ( $credentials_attached ) {
			$args = self::withRequestAuth( $args );
		}

		// $home_url is the site's own home_url(); any forwarded credentials
		// target that same first-party origin, mirroring WP core's Site
		// Health loopback. Not an attacker-controlled URL.
		// nosemgrep -- $home_url is first-party home_url(); forwarded cookies/Basic Auth are same-origin.
		$response = wp_remote_get( $home_url, $args );

		if ( is_wp_error( $response ) ) {
			return LoopbackResult::failed( $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 === $code ) {
			return LoopbackResult::ok();
		}
		if ( $credentials_attached && self::isSameOriginRedirect( $home_url, $response ) ) {
			return LoopbackResult::ok();
		}
		return LoopbackResult::inconclusive( 'HTTP ' . $code );
	}

	/**
	 * Whether a response is a redirect back to the probe's own origin.
	 *
	 * Re-check never follows a redirect while credentials are attached (see
	 * {@see withRequestAuth()}), so a same-host canonicalizing redirect (an
	 * enforced trailing slash) would otherwise be a permanent, un-clearable
	 * warning that activation's redirection=2 would have resolved to 200. The
	 * match is deliberately strict — same host (case-insensitive), effective
	 * port, and path (bar a trailing slash), with a host-less or scheme-relative
	 * Location resolved against the probe's own origin. A redirect that changes
	 * the host (apex-to-www included) or the path (a login wall, a maintenance
	 * page) stays a warning: wp-cron is unauthenticated and would meet the same
	 * wall, so such a redirect does not prove reachability. Only the Location
	 * header is inspected — it is never fetched — so credentials already withheld
	 * from an off-origin target stay withheld.
	 *
	 * @param string          $home_url Probed URL.
	 * @param array|\WP_Error $response  wp_remote_get() response.
	 * @return bool
	 */
	private static function isSameOriginRedirect( $home_url, $response ) {
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 300 || $code >= 400 ) {
			return false;
		}
		$location = wp_remote_retrieve_header( $response, 'location' );
		if ( ! is_string( $location ) || '' === $location ) {
			return false;
		}
		$location_host = wp_parse_url( $location, PHP_URL_HOST );
		if ( empty( $location_host ) ) {
			// No authority component: a path-relative or absolute-path
			// reference, resolved against the probe's own origin — same origin
			// by construction, so it turns on whether the path canonicalizes.
			return self::samePath( $home_url, $location );
		}
		$target_host = wp_parse_url( $home_url, PHP_URL_HOST );
		if ( empty( $target_host )
			|| 0 !== strcasecmp( (string) $target_host, (string) $location_host ) ) {
			return false;
		}
		// A protocol-relative Location (`//host/path`) inherits the probe's
		// scheme; resolve it so an https probe's 443 is not compared against a
		// scheme-guessed http:80 and the same-origin redirect wrongly rejected.
		if ( 0 === strpos( $location, '//' ) ) {
			$location = wp_parse_url( $home_url, PHP_URL_SCHEME ) . ':' . $location;
		}
		return self::effectivePort( $home_url ) === self::effectivePort( $location )
			&& self::samePath( $home_url, $location );
	}

	/**
	 * Augment the request args with the current request's cookies, Basic
	 * Auth credentials, and a no-cache directive, and require TLS verification
	 * so the forwarded credentials cannot be read off an unverified connection.
	 *
	 * Kept separate from {@see run()} so the superglobal reads do not sit in
	 * the same scope as the outbound request — the forwarded values target
	 * the first-party home URL only. Only ever called for https URLs.
	 *
	 * @param array $args Base wp_remote_get args.
	 * @return array
	 */
	private static function withRequestAuth( $args ) {
		// Credentials are attached below; never let them traverse a TLS
		// connection whose certificate we did not verify, and never follow a
		// redirect — a 30x off-origin would otherwise carry the cookies and
		// Basic Auth header to another host.
		$args['sslverify']   = true;
		$args['redirection'] = 0;

		$cookies = array();
		foreach ( (array) wp_unslash( $_COOKIE ) as $name => $value ) {
			if ( is_scalar( $value ) ) {
				$cookies[ (string) $name ] = (string) $value;
			}
		}
		if ( ! empty( $cookies ) ) {
			$args['cookies'] = $cookies;
		}

		$headers = array( 'Cache-Control' => 'no-cache' );
		if ( isset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- these are credentials to forward verbatim, not output to sanitize; sanitize_text_field() would alter (and thus invalidate) any value containing tabs, angle brackets, repeated spaces, or %XX sequences.
			$user = wp_unslash( $_SERVER['PHP_AUTH_USER'] );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- see above.
			$pass = wp_unslash( $_SERVER['PHP_AUTH_PW'] );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic Auth credentials are base64 per RFC 7617.
			$headers['Authorization'] = 'Basic ' . base64_encode( $user . ':' . $pass );
		}
		$args['headers'] = $headers;

		return $args;
	}

	/**
	 * Whether credentials may be forwarded to this URL: it must use https and
	 * resolve to the site's own host.
	 *
	 * The host is compared against the independently-stored site URL
	 * (get_site_url), NOT re-derived from the same `home` option the probe
	 * target comes from — otherwise a poisoned `home` option would pass by
	 * being compared against itself and the credentials would be shipped to
	 * the attacker's host. The trade-off is that sites where the home and
	 * site-URL hosts legitimately differ fall back to an unauthenticated
	 * probe, which is fail-safe (no credentials leave the origin).
	 *
	 * @param string $home_url URL to test.
	 * @return bool
	 */
	private static function isSafeForwardTarget( $home_url ) {
		if ( ! is_string( $home_url ) || 0 !== stripos( $home_url, 'https://' ) ) {
			return false;
		}
		if ( ! function_exists( 'get_site_url' ) ) {
			return false;
		}
		$site_url = (string) get_site_url();

		$target_host = wp_parse_url( $home_url, PHP_URL_HOST );
		$site_host   = wp_parse_url( $site_url, PHP_URL_HOST );
		return ! empty( $target_host ) && ! empty( $site_host )
			&& 0 === strcasecmp( (string) $target_host, (string) $site_host )
			&& self::effectivePort( $home_url ) === self::effectivePort( $site_url );
	}

	/**
	 * The port a URL resolves to: its explicit port, or the scheme's default
	 * (80/443) when none is given — so `https://x/` and `https://x:443/`
	 * compare equal instead of one being an int and the other null.
	 *
	 * @param string $url URL to inspect.
	 * @return int
	 */
	private static function effectivePort( $url ) {
		$port = wp_parse_url( $url, PHP_URL_PORT );
		if ( ! empty( $port ) ) {
			return (int) $port;
		}
		return 0 === stripos( $url, 'https://' ) ? 443 : 80;
	}

	/**
	 * Whether two URLs address the same path, ignoring a trailing slash so a
	 * canonicalizing redirect (`/x` to `/x/`) still matches. A redirect to a
	 * different path is not a canonicalization and does not prove reachability.
	 *
	 * @param string $home_url Probed URL.
	 * @param string $location Redirect target (may be host-less).
	 * @return bool
	 */
	private static function samePath( $home_url, $location ) {
		return self::normalizedPath( wp_parse_url( $home_url, PHP_URL_PATH ) )
			=== self::normalizedPath( wp_parse_url( $location, PHP_URL_PATH ) );
	}

	/**
	 * A URL path with any trailing slash removed and an empty path treated as
	 * root, so `/x/` and `/x` — or `` and `/` — share one canonical form.
	 *
	 * @param string|null $path Parsed URL path.
	 * @return string
	 */
	private static function normalizedPath( $path ) {
		$path = is_string( $path ) ? rtrim( $path, '/' ) : '';
		return '' === $path ? '/' : $path;
	}
}
