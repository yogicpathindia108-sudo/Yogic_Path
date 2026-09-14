<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Forward-confirmed reverse-DNS verifier for search-engine UAs.
 *
 * Scope: providers whose IP ranges are NOT bundled (Yandex, Baidu,
 * Sogou, Seznam, Naver, Mojeek). Each provider documents one or more
 * PTR suffixes (e.g. `.yandex.com`, `.crawl.baidu.com`); a request
 * whose UA matches one of the provider's tokens AND whose IP misses
 * every IpRangeLookup bucket falls through to this class.
 *
 * Verification, in order — any failure yields a negative result:
 *   1. PTR-resolve the IP to a hostname.
 *   2. Verify the hostname (lowercased, trailing dot stripped) ends
 *      with one of the provider's allowed suffixes.
 *   3. Forward-resolve the hostname to a list of A/AAAA records.
 *   4. Verify the original IP appears in the forward-resolved list.
 *
 * Steps 2 and 3 together close the spoof channel: an attacker can set
 * any PTR for an IP they own (claiming `spider.yandex.com`), but
 * forward-resolving that hostname returns the real Yandex IPs, which
 * the attacker doesn't control.
 *
 * Caching: results live in CounterStorageInterface keyed by IP+provider, with
 * separate TTLs for positive (24h) and negative (5min) outcomes — so a
 * cold-cache rDNS round-trip is paid at most once per (provider, IP)
 * per TTL window. Tight negative TTL keeps spoofers from camping a
 * cached "rejected" entry forever; long positive TTL keeps the
 * blocking PHP DNS call off the request path for legitimate bots
 * after the first hit.
 *
 * Phase-2 follow-up: replace the synchronous PHP DNS calls with an
 * async resolver (or a small wp-cron job that warms the cache from a
 * pool of recent client IPs) so the cold-cache hit is removed from
 * the request path entirely. Until then, the system resolver's
 * default timeout governs the worst-case latency.
 *
 * @since 4.0.0
 */
class RdnsVerifier {

	const POSITIVE_TTL = 86400;
	const NEGATIVE_TTL = 300;
	const KEY_PREFIX   = 'rdns:';

	const RESULT_VERIFIED = 1;
	const RESULT_REJECTED = 2;

	/**
	 * Cache backend. Reuses DbStorageFactory's chosen storage so Memory /
	 * Null fallback semantics match the rate limiter.
	 *
	 * @var CounterStorageInterface
	 */
	private $storage;

	/**
	 * Per-instance cache key prefix. Allows multiple RdnsVerifier instances
	 * (search-engine vs AI-crawler) to use non-overlapping key namespaces.
	 *
	 * @var string
	 */
	private $key_prefix;

	/**
	 * Sanitised provider map keyed by provider name.
	 *
	 * Shape: [ provider => [ 'tokens' => string[], 'suffixes' => string[] ] ].
	 *
	 * @var array
	 */
	private $providers = array();

	/**
	 * PTR resolver. Function (string $ip): ?string hostname.
	 *
	 * @var callable
	 */
	private $reverse;

	/**
	 * Forward A/AAAA resolver. Function (string $hostname): string[] IPs.
	 *
	 * @var callable
	 */
	private $forward;

	/**
	 * Wire the verifier over its collaborators.
	 *
	 * @param CounterStorageInterface $storage    Cache backend.
	 * @param array                   $providers  Map of provider name => [tokens => list, suffixes => list].
	 * @param callable|null           $reverse    PTR resolver. Default: dns_get_record-based reverse lookup.
	 * @param callable|null           $forward    Forward A/AAAA resolver. Default: dns_get_record.
	 * @param string|null             $key_prefix Cache key prefix. Default: 'rdns:'.
	 */
	public function __construct( $storage, $providers, $reverse = null, $forward = null, $key_prefix = null ) {
		$this->storage    = $storage;
		$this->providers  = is_array( $providers ) ? self::sanitiseProviders( $providers ) : array();
		$this->reverse    = is_callable( $reverse ) ? $reverse : array( __CLASS__, 'defaultReverse' );
		$this->forward    = is_callable( $forward ) ? $forward : array( __CLASS__, 'defaultForward' );
		$this->key_prefix = is_string( $key_prefix ) && '' !== $key_prefix ? $key_prefix : self::KEY_PREFIX;
	}

	/**
	 * Verify whether $ip belongs to the search-engine provider whose
	 * tokens appear in $ua. Returns false when no provider matches.
	 *
	 * Idempotent and side-effect-free aside from the cache write — safe
	 * to call from the classifier's hot path.
	 *
	 * @param string $ip Resolved client IP.
	 * @param string $ua Raw User-Agent header.
	 * @return bool
	 */
	public function verifyAgainstUa( $ip, $ua ) {
		if ( ! is_string( $ip ) || '' === $ip ) {
			return false;
		}
		// Without a real cache backend, `$storage->get` always returns
		// 0 (cache miss) and every call would re-pay the blocking PTR
		// + A/AAAA round-trip — 1–10s of latency per matching UA, on
		// every request. The rate limiter is also unenforced on
		// NullCounterStorage (any counter increment returns 0, so checks
		// always allow), so verification yields no functional benefit
		// either way. Bail before running matchProvider so the cost
		// is constant-time on null-backend hosts.
		if ( 'null' === $this->storage->name() ) {
			return false;
		}
		$provider = $this->matchProvider( $ua );
		if ( null === $provider ) {
			return false;
		}
		return $this->verify( $ip, $provider );
	}

	/**
	 * Cache-aware verification for a known (ip, provider) pair.
	 *
	 * @param string $ip       Client IP under verification.
	 * @param string $provider Provider key matched from the UA.
	 * @return bool
	 */
	private function verify( $ip, $provider ) {
		$key    = $this->key_prefix . $provider . ':' . $ip;
		$cached = (int) $this->storage->get( $key );
		if ( self::RESULT_VERIFIED === $cached ) {
			return true;
		}
		if ( self::RESULT_REJECTED === $cached ) {
			return false;
		}

		$verified = $this->lookup( $ip, $provider );
		$this->storage->set(
			$key,
			$verified ? self::RESULT_VERIFIED : self::RESULT_REJECTED,
			$verified ? self::POSITIVE_TTL : self::NEGATIVE_TTL
		);
		return $verified;
	}

	/**
	 * Run the PTR + suffix + forward-confirm sequence. Any failure
	 * yields false — callers cache the negative result briefly.
	 *
	 * @param string $ip       Client IP to verify.
	 * @param string $provider Provider key whose suffix list to check against.
	 * @return bool
	 */
	private function lookup( $ip, $provider ) {
		$hostname = $this->callReverse( $ip );
		if ( null === $hostname ) {
			return false;
		}
		$normalised = self::normaliseHostname( $hostname );
		if ( '' === $normalised ) {
			return false;
		}
		if ( ! self::hostnameMatchesSuffixes( $normalised, $this->providers[ $provider ]['suffixes'] ) ) {
			return false;
		}
		$forward = $this->callForward( $normalised );
		return self::ipInList( $ip, $forward );
	}

	/**
	 * IPv4/IPv6-aware membership test.
	 *
	 * The PHP `dns_get_record()` call can return IPv6 addresses in an expanded form
	 * (`2a00:1450:4001:0c50:0000:0000:0000:006a`) while the resolved
	 * client IP arrives in compressed form (`2a00:1450:4001:c50::6a`)
	 * — the same address, but a strict string compare rejects the
	 * forward confirmation and downgrades a legitimate verified bot
	 * to UNVERIFIED_BOT. Convert both sides to packed binary form via
	 * inet_pton() so the comparison is canonical for IPv4 and IPv6
	 * alike. Garbage entries on either side are silently skipped.
	 *
	 * @param string $needle   The client IP we expect to find.
	 * @param array  $haystack Forward A/AAAA records.
	 * @return bool
	 */
	private static function ipInList( $needle, $haystack ) {
		if ( ! is_string( $needle ) || '' === $needle ) {
			return false;
		}
		// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- inet_pton emits a warning on garbage input; we want false instead.
		$wanted = @inet_pton( $needle );
		if ( false === $wanted ) {
			return false;
		}
		foreach ( $haystack as $candidate ) {
			if ( ! is_string( $candidate ) || '' === $candidate ) {
				continue;
			}
			// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- skip garbage entries silently.
			$packed = @inet_pton( $candidate );
			if ( false !== $packed && $packed === $wanted ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Invoke the reverse resolver, swallowing any throw as null so
	 * a misbehaving system resolver can't escape into the request path.
	 *
	 * @param string $ip Client IP to PTR-resolve.
	 * @return string|null
	 */
	private function callReverse( $ip ) {
		// Dual-path catch so PHP 5.6 (\Exception only) and PHP 7+
		// (\Throwable, covers \Error) both swallow resolver throws.
		// Without the interface_exists() guard, the bare
		// `catch (\Throwable $t)` silently never matches on 5.6 and
		// any \Exception escapes the verifier.
		if ( interface_exists( 'Throwable' ) ) {
			try {
				$r = call_user_func( $this->reverse, $ip );
			} catch ( \Throwable $t ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- fail-open: any resolver throw maps to "no PTR".
				return null;
			}
		} else {
			try {
				$r = call_user_func( $this->reverse, $ip );
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- fail-open: any resolver throw maps to "no PTR".
				return null;
			}
		}
		return is_string( $r ) ? $r : null;
	}

	/**
	 * Invoke the forward resolver, swallowing any throw as an empty list.
	 *
	 * @param string $hostname Hostname to A/AAAA-resolve.
	 * @return array
	 */
	private function callForward( $hostname ) {
		// See callReverse() for the dual-path Throwable/Exception
		// rationale. Same shape applies here.
		if ( interface_exists( 'Throwable' ) ) {
			try {
				$r = call_user_func( $this->forward, $hostname );
			} catch ( \Throwable $t ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- fail-open: any resolver throw maps to no records.
				return array();
			}
		} else {
			try {
				$r = call_user_func( $this->forward, $hostname );
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- fail-open: any resolver throw maps to no records.
				return array();
			}
		}
		if ( ! is_array( $r ) ) {
			return array();
		}
		return array_values( array_filter( $r, 'is_string' ) );
	}

	/**
	 * Find the first provider whose tokens appear in $ua.
	 *
	 * Matching mirrors UserAgentSignatures (case-insensitive substring),
	 * so a UA that the bundled signatures classify as search-engine
	 * also matches here when the token belongs to one of our rDNS
	 * providers.
	 *
	 * @param mixed $ua Raw User-Agent header (may be non-string in malformed input).
	 * @return string|null Provider key, or null when no provider matches.
	 */
	private function matchProvider( $ua ) {
		if ( ! is_string( $ua ) || '' === $ua ) {
			return null;
		}
		foreach ( $this->providers as $name => $info ) {
			foreach ( $info['tokens'] as $token ) {
				if ( '' !== $token && false !== stripos( $ua, $token ) ) {
					return $name;
				}
			}
		}
		return null;
	}

	/**
	 * Lowercase + strip trailing dot. PTR responses canonically end in
	 * `.`; the suffix data we ship doesn't, so normalising once here
	 * keeps the suffix check below uniform.
	 *
	 * @param string $hostname Hostname as returned from PTR.
	 * @return string
	 */
	private static function normaliseHostname( $hostname ) {
		return strtolower( rtrim( (string) $hostname, '.' ) );
	}

	/**
	 * Whether $hostname (already lowercased + trimmed) ends with any
	 * suffix in $suffixes. Suffixes are stored with a leading dot
	 * (`.yandex.com`) so substring suffix matching catches
	 * `spider.yandex.com` but rejects `evilyandex.com`. A bare match on
	 * `yandex.com` is also accepted for completeness.
	 *
	 * @param string $hostname Already-normalised hostname.
	 * @param array  $suffixes Allowed suffix list (each with leading dot).
	 * @return bool
	 */
	private static function hostnameMatchesSuffixes( $hostname, $suffixes ) {
		if ( '' === $hostname ) {
			return false;
		}
		foreach ( $suffixes as $suffix ) {
			$needle = strtolower( (string) $suffix );
			if ( '' === $needle ) {
				continue;
			}
			$len = strlen( $needle );
			if ( strlen( $hostname ) >= $len && substr( $hostname, -$len ) === $needle ) {
				return true;
			}
			$bare = ltrim( $needle, '.' );
			if ( '' !== $bare && $hostname === $bare ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Reject malformed providers entries so the verifier never indexes
	 * a half-loaded provider on a typo'd or partially-overlay'd config.
	 *
	 * @param array $providers Raw map.
	 * @return array Sanitised map (only well-formed entries).
	 */
	private static function sanitiseProviders( $providers ) {
		$out = array();
		foreach ( $providers as $name => $info ) {
			if ( ! is_string( $name ) || '' === $name || ! is_array( $info ) ) {
				continue;
			}
			$tokens   = isset( $info['tokens'] ) && is_array( $info['tokens'] ) ? $info['tokens'] : array();
			$suffixes = isset( $info['suffixes'] ) && is_array( $info['suffixes'] ) ? $info['suffixes'] : array();
			$tokens   = array_values( array_filter( $tokens, 'is_string' ) );
			$suffixes = array_values( array_filter( $suffixes, 'is_string' ) );
			if ( empty( $tokens ) || empty( $suffixes ) ) {
				continue;
			}
			$out[ $name ] = array(
				'tokens'   => $tokens,
				'suffixes' => $suffixes,
			);
		}
		return $out;
	}

	/**
	 * Default PTR resolver via dns_get_record(DNS_PTR).
	 *
	 * Uses dns_get_record instead of gethostbyaddr so the libc
	 * RES_OPTIONS timeout cap applies to the reverse lookup too.
	 *
	 * @param string $ip IP address to PTR-resolve.
	 * @return string|null
	 */
	public static function defaultReverse( $ip ) {
		$ptr_name = self::buildPtrName( (string) $ip );
		if ( null === $ptr_name ) {
			return null;
		}
		$old = self::capResolverTimeout();
		try {
			// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents -- dns_get_record emits E_WARNING on NXDOMAIN; DNS lookup, not file I/O.
			$records = @dns_get_record( $ptr_name, DNS_PTR );
		} finally {
			self::restoreResolverTimeout( $old );
		}

		if ( ! is_array( $records ) || empty( $records ) ) {
			return null;
		}
		foreach ( $records as $r ) {
			if ( isset( $r['target'] ) && is_string( $r['target'] ) && '' !== $r['target'] ) {
				return $r['target'];
			}
		}
		return null;
	}

	/**
	 * Default forward resolver. Returns the union of A and AAAA records.
	 *
	 * @param string $hostname Hostname to A/AAAA-resolve.
	 * @return array
	 */
	public static function defaultForward( $hostname ) {
		$old = self::capResolverTimeout();
		try {
			// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents -- dns_get_record emits E_WARNING on NXDOMAIN; DNS lookup, not file I/O.
			$records = @dns_get_record( (string) $hostname, DNS_A | DNS_AAAA );
		} finally {
			self::restoreResolverTimeout( $old );
		}

		if ( ! is_array( $records ) ) {
			return array();
		}
		$ips = array();
		foreach ( $records as $r ) {
			if ( isset( $r['ip'] ) && is_string( $r['ip'] ) ) {
				$ips[] = $r['ip'];
			} elseif ( isset( $r['ipv6'] ) && is_string( $r['ipv6'] ) ) {
				$ips[] = $r['ipv6'];
			}
		}
		return $ips;
	}

	/**
	 * Build the in-addr.arpa / ip6.arpa PTR name for an IP address.
	 *
	 * @param string $ip IPv4 or IPv6 address.
	 * @return string|null PTR query name, or null on invalid input.
	 */
	private static function buildPtrName( $ip ) {
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return implode( '.', array_reverse( explode( '.', $ip ) ) ) . '.in-addr.arpa';
		}
		// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- inet_pton emits a warning on garbage; we want null.
		$packed = @inet_pton( $ip );
		if ( false === $packed || 16 !== strlen( $packed ) ) {
			return null;
		}
		$hex = bin2hex( $packed );
		return implode( '.', array_reverse( str_split( $hex, 1 ) ) ) . '.ip6.arpa';
	}

	/**
	 * Set a 1-second / 1-attempt DNS resolver timeout and return the
	 * previous value so the caller can restore it.
	 *
	 * @return string|false Previous RES_OPTIONS value, or false if unset.
	 */
	private static function capResolverTimeout() {
		$old = getenv( 'RES_OPTIONS' );
		// @phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- intentional: cap libc resolver timeout to 1s so a slow authoritative NS cannot stall the FPM worker.
		putenv( 'RES_OPTIONS=timeout:1 attempts:1' );
		return $old;
	}

	/**
	 * Restore the RES_OPTIONS environment variable to a saved value.
	 *
	 * @param string|false $old Value from capResolverTimeout (false = was unset).
	 */
	private static function restoreResolverTimeout( $old ) {
		if ( false === $old ) {
			// @phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- restoring env to pre-cap state.
			putenv( 'RES_OPTIONS' );
		} else {
			// @phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- restoring env to pre-cap state.
			putenv( 'RES_OPTIONS=' . $old );
		}
	}

	/**
	 * Load and sanitise the providers map from a bundled rDNS suffix
	 * data file. Symmetric to BundledData::loadArrayFile but for the
	 * nested shape used by ua-rdns-suffixes.php.
	 *
	 * Failures yield an empty map — the verifier then matches no UA
	 * and returns false to all callers, falling Classifier through to
	 * the existing UNVERIFIED_BOT branch.
	 *
	 * @param string|null $path Path to the bundled file.
	 * @return array
	 */
	public static function loadProvidersFromFile( $path ) {
		if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) ) {
			return array();
		}
		// Dual-path so a parse error or syntax error in the bundled file
		// doesn't escape uncaught on PHP 5.6, where \Throwable doesn't
		// exist as an interface and the bare catch silently never fires.
		if ( interface_exists( 'Throwable' ) ) {
			try {
				$data = include $path;
			} catch ( \Throwable $t ) {
				BundledData::reportFailOpenError( 'rdns providers load failed for ' . $path, $t->getMessage() );
				return array();
			}
		} else {
			try {
				$data = include $path;
			} catch ( \Exception $e ) {
				BundledData::reportFailOpenError( 'rdns providers load failed for ' . $path, $e->getMessage() );
				return array();
			}
		}
		if ( ! is_array( $data ) || ! isset( $data['providers'] ) || ! is_array( $data['providers'] ) ) {
			return array();
		}
		return self::sanitiseProviders( $data['providers'] );
	}
}
