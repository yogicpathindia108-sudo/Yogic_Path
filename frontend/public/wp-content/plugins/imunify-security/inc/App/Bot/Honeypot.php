<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Honeypot URI and its supporting fragments.
 *
 * The link is rendered invisibly in every page footer and banned in
 * robots.txt. A well-behaved visitor (human or compliant crawler) will
 * never request it, so any hit is a zero-false-positive malicious-bot
 * signal. The Pipeline passes the result of isTriggered() to the
 * Classifier, which short-circuits to MALICIOUS_BOT / 403.
 *
 * Everything on this class is static and pure — no filesystem, no WP —
 * so the Pipeline's hot path can call isTriggered() without worrying
 * about side effects.
 *
 * @since 4.0.0
 */
class Honeypot {

	/**
	 * URI path that triggers the honeypot. Hard-coded rather than
	 * configurable so bundled robots.txt guidance stays in lockstep with
	 * detection. Changing the value is a breaking change for any
	 * site-level robots.txt an admin may have published.
	 */
	const PATH = '/imunify-bot-check';

	/**
	 * Honeypot URI, optionally prefixed with the site's subdirectory base.
	 *
	 * @since 4.1.0 Added $base_path for subdirectory WordPress installs.
	 *
	 * @param string $base_path Path component of home_url(); '' on root installs.
	 * @return string
	 */
	public static function path( $base_path = '' ) {
		return self::normalizeBase( $base_path ) . self::PATH;
	}

	/**
	 * Whether the supplied REQUEST_URI targets the honeypot.
	 *
	 * Matching is case-insensitive on the leading path component so a
	 * bot that normalises the URI to upper-case still trips the trap.
	 * Query strings and a trailing slash are ignored, but the token must
	 * be the entire first path segment — a benign page that happens to
	 * contain "imunify-bot-check" deeper in the path does not match.
	 *
	 * On a subdirectory install the honeypot lives under $base_path (e.g.
	 * "/blog/imunify-bot-check"). That prefix is stripped before the token
	 * comparison, so the trap fires whether the request carries the
	 * subdirectory prefix or hits the bare root token directly.
	 *
	 * @since 4.1.0 Added $base_path for subdirectory WordPress installs.
	 *
	 * @param string $uri       Request URI (from $_SERVER['REQUEST_URI']).
	 * @param string $base_path Subdirectory base to strip; '' on root installs.
	 * @return bool
	 */
	public static function isTriggered( $uri, $base_path = '' ) {
		if ( ! is_string( $uri ) || '' === $uri ) {
			return false;
		}
		// Collapse leading double slashes so parse_url does not treat the
		// path as a host in a protocol-relative URL (e.g. "//imunify-bot-check").
		if ( 0 === strpos( $uri, '//' ) ) {
			$uri = '/' . ltrim( $uri, '/' );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pre-WP hot path; wp_parse_url may not be available.
		$path = (string) parse_url( $uri, PHP_URL_PATH );
		$path = rtrim( $path, '/' );
		$path = self::stripBase( $path, self::normalizeBase( $base_path ) );
		return 0 === strcasecmp( $path, self::PATH );
	}

	/**
	 * Derive the honeypot base path from a site URL (typically home_url()).
	 *
	 * Returns the normalised path component — '' for a root install,
	 * '/blog' for a site whose home is "https://example.com/blog/".
	 *
	 * @since 4.1.0
	 *
	 * @param string $url Absolute site URL or a bare path.
	 * @return string
	 */
	public static function basePathFromUrl( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure helper; may run before WordPress is loaded.
		return self::normalizeBase( (string) parse_url( $url, PHP_URL_PATH ) );
	}

	/**
	 * Derive the honeypot base path from $_SERVER['SCRIPT_NAME'].
	 *
	 * The mu-plugin runs before home_url() is available, so the
	 * subdirectory is recovered from the front controller that handled the
	 * request: "/blog/index.php" yields "/blog", "/index.php" yields ''.
	 *
	 * Note: this reflects the request's front controller, which only matches the
	 * rendered (home_url()-based) path when dirname(SCRIPT_NAME) equals
	 * home_url()'s path. Layouts where they diverge — subdirectory multisite
	 * (network-root index.php), a path-rewriting reverse proxy/alias, or
	 * "WordPress in its own directory" — are not detected and fail open (the
	 * honeypot does not fire; nothing is wrongly blocked).
	 *
	 * @since 4.1.0
	 *
	 * @param string $script_name Value of $_SERVER['SCRIPT_NAME'].
	 * @return string
	 */
	public static function basePathFromScriptName( $script_name ) {
		if ( ! is_string( $script_name ) || '' === $script_name ) {
			return '';
		}
		return self::normalizeBase( dirname( $script_name ) );
	}

	/**
	 * Hidden footer link that feeds the trap.
	 *
	 * Inline CSS keeps the link invisible even if the active theme strips
	 * stylesheets. aria-hidden + tabindex=-1 keep screen readers and
	 * keyboard users away from it, so only a bot crawling the DOM and
	 * following every href will reach it.
	 *
	 * @since 4.1.0 Added $base_path for subdirectory WordPress installs.
	 *
	 * @param string $base_path Path component of home_url(); '' on root installs.
	 * @return string HTML snippet suitable for echoing inside wp_footer.
	 */
	public static function footerLinkHtml( $base_path = '' ) {
		$href = htmlspecialchars( self::path( $base_path ), ENT_QUOTES, 'UTF-8' );
		return '<a href="' . $href . '" '
			. 'rel="nofollow" aria-hidden="true" tabindex="-1" '
			. 'style="display:none!important;position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden">'
			. 'imunify-bot-check</a>';
	}

	/**
	 * Robots.txt fragment that bans compliant crawlers from the honeypot.
	 *
	 * Appended to the site robots.txt via the `robots_txt` filter so any
	 * bot that claims to honour robots.txt but requests this path is
	 * definitively lying.
	 *
	 * @since 4.1.0 Added $base_path for subdirectory WordPress installs.
	 *
	 * @param string $base_path Path component of home_url(); '' on root installs.
	 * @return string
	 */
	public static function robotsTxtFragment( $base_path = '' ) {
		return "User-agent: *\nDisallow: " . self::path( $base_path ) . "\n";
	}

	/**
	 * Normalise a subdirectory base to '' or a leading-slash, no-trailing-slash
	 * path. Anything that does not resolve to an absolute path segment
	 * (empty, '/', '.', a relative fragment) collapses to '' — the root
	 * install behaviour — so a malformed value never breaks matching.
	 *
	 * @param string $path Candidate base path.
	 * @return string
	 */
	private static function normalizeBase( $path ) {
		$path = rtrim( (string) $path, '/' );
		if ( '' === $path || '/' !== $path[0] ) {
			return '';
		}
		return $path;
	}

	/**
	 * Strip a normalised subdirectory base from the front of a request path.
	 *
	 * The base must align on a path-segment boundary (compared
	 * case-insensitively, mirroring the token match) — "/blog" is stripped
	 * from "/blog/imunify-bot-check" but not from "/blogfoo/...". A path
	 * that does not carry the prefix is returned unchanged so the bare root
	 * token still matches.
	 *
	 * @param string $path      Request path, already trimmed of a trailing slash.
	 * @param string $base_path Normalised subdirectory base ('' for root).
	 * @return string
	 */
	private static function stripBase( $path, $base_path ) {
		if ( '' === $base_path ) {
			return $path;
		}
		$prefix = $base_path . '/';
		if ( 0 === strncasecmp( $path, $prefix, strlen( $prefix ) ) ) {
			return substr( $path, strlen( $base_path ) );
		}
		return $path;
	}
}
