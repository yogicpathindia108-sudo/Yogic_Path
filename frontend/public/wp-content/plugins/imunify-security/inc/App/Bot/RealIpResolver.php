<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Real client-IP extraction with CDN anti-spoofing.
 *
 * Walks a priority-ordered list of proxy headers (CF-Connecting-IP,
 * True-Client-IP, Fastly-Client-IP, X-Sucuri-ClientIP, Incap-Client-IP,
 * CloudFront-Viewer-Address, X-Real-IP, X-Forwarded-For). A header is
 * only trusted when REMOTE_ADDR actually originates from that CDN's
 * published ranges — a direct attacker cannot spoof
 * CF-Connecting-IP from outside the Cloudflare edge network.
 *
 * X-Forwarded-For is walked right-to-left, skipping trusted-proxy hops
 * until the first untrusted IP is found. All trusted hops fall through
 * to REMOTE_ADDR as the safe fallback.
 *
 * Fail-open: any malformed header value is skipped rather than raised;
 * a resolver with no trustworthy headers always returns REMOTE_ADDR.
 *
 * @since 4.0.0
 */
class RealIpResolver {

	/**
	 * CDN origin detector used for anti-spoofing validation.
	 *
	 * @var CdnDetector
	 */
	private $cdn;

	/**
	 * Additional trusted-proxy CIDRs beyond the CDN ranges (e.g. ranges
	 * parsed from /etc/imunify360-webshield/common-proxies.conf or
	 * private-network reverse proxies).
	 *
	 * @var array
	 */
	private $extra_trusted = array();

	/**
	 * Build a resolver over a CDN detector and optional extra trusted proxies.
	 *
	 * @param CdnDetector $cdn            CDN origin detector seeded with provider ranges.
	 * @param array       $extra_trusted  Optional list of extra trusted-proxy CIDRs.
	 */
	public function __construct( $cdn, $extra_trusted = array() ) {
		$this->cdn = $cdn;
		if ( is_array( $extra_trusted ) ) {
			$this->extra_trusted = $extra_trusted;
		}
	}

	/**
	 * Resolve the real client IP for a request.
	 *
	 * @param array  $headers     Request headers (case-insensitive keys).
	 * @param string $remote_addr Socket peer IP (the value of $_SERVER['REMOTE_ADDR']).
	 * @return string Real client IP in canonical form, or '0.0.0.0' on irrecoverable input.
	 */
	public function resolve( $headers, $remote_addr ) {
		if ( ! is_array( $headers ) ) {
			$headers = array();
		}
		return $this->resolveFromNormalised( self::normaliseHeaders( $headers ), $remote_addr );
	}

	/**
	 * Resolve variant that accepts an already-lower-cased header map.
	 *
	 * Lets callers that already hold a normalised map (e.g. Classifier)
	 * skip the redundant strtolower pass. The expected input shape is what
	 * normaliseHeaders() produces.
	 *
	 * @param array  $normalised  Header map with lower-case keys.
	 * @param string $remote_addr Socket peer IP.
	 * @return string Real client IP, or '0.0.0.0' on irrecoverable input.
	 */
	public function resolveFromNormalised( $normalised, $remote_addr ) {
		$remote = self::validIp( $remote_addr );
		if ( null === $remote ) {
			return '0.0.0.0';
		}
		// normaliseHeaders() is the only supported way to produce the input,
		// and it always returns an array — no extra type guard needed here.

		// True-Client-IP is accepted from both Akamai (its primary origin per
		// the Akamai docs) and Cloudflare (the Enterprise tier ships the
		// same header, per cloudflare.com/learning/cdn/glossary/true-client-ip).
		// Every other CDN header is single-origin.
		$cdn_headers = array(
			array( 'cf-connecting-ip', array( 'cloudflare' ), false ),
			array( 'true-client-ip', array( 'akamai', 'cloudflare' ), false ),
			array( 'fastly-client-ip', array( 'fastly' ), false ),
			array( 'x-sucuri-clientip', array( 'sucuri' ), false ),
			array( 'incap-client-ip', array( 'imperva' ), false ),
			array( 'cloudfront-viewer-address', array( 'cloudfront' ), true ),
		);

		foreach ( $cdn_headers as $spec ) {
			$name       = $spec[0];
			$cdns       = $spec[1];
			$strip_port = $spec[2];
			$value      = self::getHeader( $normalised, $name );
			if ( '' === $value ) {
				continue;
			}
			if ( ! $this->isFromAnyCdn( $remote, $cdns ) ) {
				continue;
			}
			$candidate = $strip_port ? self::stripPort( $value ) : trim( $value );
			$validated = self::validIp( $candidate );
			if ( null !== $validated ) {
				return $validated;
			}
		}

		$x_real_ip = self::getHeader( $normalised, 'x-real-ip' );
		if ( '' !== $x_real_ip && $this->isTrustedProxy( $remote ) ) {
			$validated = self::validIp( trim( $x_real_ip ) );
			if ( null !== $validated ) {
				return $validated;
			}
		}

		$xff = self::getHeader( $normalised, 'x-forwarded-for' );
		if ( '' !== $xff && $this->isTrustedProxy( $remote ) ) {
			$resolved = $this->resolveXffChain( $xff );
			if ( null !== $resolved ) {
				return $resolved;
			}
		}

		return $remote;
	}

	/**
	 * Walk an X-Forwarded-For chain right-to-left, returning the rightmost untrusted IP.
	 *
	 * @param string $xff The raw X-Forwarded-For header value.
	 * @return string|null Real client IP, or null when every entry is a trusted proxy / garbage.
	 */
	private function resolveXffChain( $xff ) {
		$entries = array();
		foreach ( explode( ',', $xff ) as $entry ) {
			$entry = trim( $entry );
			if ( '' !== $entry ) {
				$entries[] = $entry;
			}
		}
		for ( $i = count( $entries ) - 1; $i >= 0; $i-- ) {
			$ip = self::validIp( self::stripPort( $entries[ $i ] ) );
			if ( null === $ip ) {
				continue;
			}
			if ( ! $this->isTrustedProxy( $ip ) ) {
				return $ip;
			}
		}
		return null;
	}

	/**
	 * Whether $ip is in any of the given CDN provider ranges.
	 *
	 * @param string $ip   Validated IP address.
	 * @param array  $cdns CDN provider names to test against.
	 * @return bool
	 */
	private function isFromAnyCdn( $ip, $cdns ) {
		foreach ( $cdns as $cdn_name ) {
			if ( $this->cdn->isFrom( $ip, $cdn_name ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether $ip is a generically trusted proxy (any known CDN or an extra-trusted range).
	 *
	 * @param string $ip Validated IP address.
	 * @return bool
	 */
	private function isTrustedProxy( $ip ) {
		if ( $this->cdn->isKnownCdn( $ip ) ) {
			return true;
		}
		return CidrMatcher::matchesAny( $ip, $this->extra_trusted );
	}

	/**
	 * Strip an optional port suffix from a CloudFront-Viewer-Address value.
	 *
	 * CloudFront serialises the viewer address as "<ipv4>:<port>" or
	 * "[<ipv6>]:<port>". A bracketless single-colon v4+port is stripped too.
	 * Bare IPv6 (no brackets, multi-colon) is returned unchanged — the
	 * single-colon heuristic is the only thing that distinguishes "IPv4:port"
	 * from an accidentally-passed v6 string, and anything with two or more
	 * colons cannot be v4+port.
	 *
	 * @param string $value Header value to trim.
	 * @return string
	 */
	private static function stripPort( $value ) {
		$value = trim( $value );
		if ( '' === $value ) {
			return $value;
		}
		if ( '[' === $value[0] ) {
			$close = strpos( $value, ']' );
			if ( false !== $close ) {
				return substr( $value, 1, $close - 1 );
			}
		}
		$first = strpos( $value, ':' );
		if ( false !== $first && false === strpos( $value, ':', $first + 1 ) ) {
			// Exactly one colon — treat as IPv4+port.
			return substr( $value, 0, $first );
		}
		return $value;
	}

	/**
	 * Lower-case header keys so subsequent lookups are case-insensitive.
	 *
	 * Exposed as a public static so callers (e.g. Classifier) can normalise
	 * once and pass the result to resolveFromNormalised(), avoiding the
	 * redundant pass that would otherwise happen inside resolve().
	 *
	 * @param array $headers Raw header map.
	 * @return array
	 */
	public static function normaliseHeaders( $headers ) {
		if ( ! is_array( $headers ) ) {
			return array();
		}
		$out = array();
		foreach ( $headers as $k => $v ) {
			if ( is_string( $k ) ) {
				$out[ strtolower( $k ) ] = $v;
			}
		}
		return $out;
	}

	/**
	 * Fetch a normalised header value as a string, returning '' when absent.
	 *
	 * @param array  $normalised Normalised headers.
	 * @param string $key        Lower-cased header name.
	 * @return string
	 */
	private static function getHeader( $normalised, $key ) {
		if ( ! isset( $normalised[ $key ] ) || ! is_string( $normalised[ $key ] ) ) {
			return '';
		}
		return $normalised[ $key ];
	}

	/**
	 * Validate and canonicalise an IP, returning null on any error.
	 *
	 * @param mixed $ip Possibly-untyped IP candidate.
	 * @return string|null
	 */
	private static function validIp( $ip ) {
		if ( ! is_string( $ip ) ) {
			return null;
		}
		$ip = trim( $ip );
		if ( '' === $ip ) {
			return null;
		}
		$validated = filter_var( $ip, FILTER_VALIDATE_IP );
		if ( false === $validated ) {
			return null;
		}
		return $validated;
	}
}
