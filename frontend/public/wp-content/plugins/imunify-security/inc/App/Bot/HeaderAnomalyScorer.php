<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Stateless HTTP header anomaly scorer.
 *
 * Secondary signal for the classifier's "Unknown Automated" category — a non-
 * browser request profile becomes suspicious when combined with a datacenter
 * IP. Higher scores indicate more anomalies; the classifier decides the
 * threshold. Individual checks are additive so the result is interpretable.
 *
 * Checks (each +1 when true):
 *   - Accept-Language header is absent or empty.
 *   - Accept-Encoding header is absent or empty.
 *   - User-Agent claims Chrome >= 89 but Sec-Ch-Ua is absent.
 *   - User-Agent claims Chrome >= 89 but the server protocol is HTTP/1.1.
 *
 * Chrome 89 was the first release to ship Sec-Ch-Ua (Mar 2021) and to
 * default-negotiate HTTP/2 where available, so these flags catch bots that
 * spoof recent Chrome UAs without matching the surrounding request profile.
 *
 * Fail-open: non-array header inputs score zero rather than throwing.
 *
 * @since 4.0.0
 */
class HeaderAnomalyScorer {

	const MIN_SEC_CH_UA_CHROME_VERSION = 89;

	/**
	 * Score a request's header profile against known browser baselines.
	 *
	 * @param array  $headers         Map of HTTP header name => value (case-insensitive lookup).
	 * @param string $server_protocol The SERVER_PROTOCOL value (e.g. "HTTP/2.0", "HTTP/1.1").
	 * @return int Non-negative anomaly score.
	 */
	public static function score( $headers, $server_protocol = '' ) {
		// Non-array input is treated as "no data" rather than "empty headers"
		// so a garbage caller can never promote a request to Unknown Automated.
		// scoreFromNormalised() applies no such guard — callers that already
		// hold a normalised map are expected to have done their own validation.
		if ( ! is_array( $headers ) ) {
			return 0;
		}
		return self::scoreFromNormalised( RealIpResolver::normaliseHeaders( $headers ), $server_protocol );
	}

	/**
	 * Score variant that accepts an already-lower-cased header map.
	 *
	 * Lets callers that already normalised (e.g. Classifier) avoid the
	 * redundant strtolower pass. Input shape matches what
	 * RealIpResolver::normaliseHeaders() produces.
	 *
	 * @param array  $normalised      Header map with lower-case keys.
	 * @param string $server_protocol The SERVER_PROTOCOL value.
	 * @return int Non-negative anomaly score.
	 */
	public static function scoreFromNormalised( $normalised, $server_protocol = '' ) {
		if ( ! is_array( $normalised ) ) {
			return 0;
		}

		$ua         = self::get( $normalised, 'user-agent' );
		$chrome_ver = self::modernChromeVersion( $ua );

		$score = 0;

		if ( '' === self::get( $normalised, 'accept-language' ) ) {
			++$score;
		}
		if ( '' === self::get( $normalised, 'accept-encoding' ) ) {
			++$score;
		}
		if ( null !== $chrome_ver && '' === self::get( $normalised, 'sec-ch-ua' ) ) {
			++$score;
		}
		if ( null !== $chrome_ver && self::isDownlevelProtocol( $server_protocol ) ) {
			++$score;
		}

		return $score;
	}

	/**
	 * Whether a SERVER_PROTOCOL string indicates a pre-HTTP/2 transport.
	 *
	 * Chrome 89+ defaults to HTTP/2 where available, so any downlevel
	 * protocol (1.0, 1.1, 0.9) is anomalous for a claimed-Chrome UA. An
	 * empty / non-string value is treated as "unknown" and does not score,
	 * to avoid false positives when SAPI doesn't expose SERVER_PROTOCOL.
	 *
	 * @param mixed $server_protocol Possibly-untyped SERVER_PROTOCOL value.
	 * @return bool
	 */
	private static function isDownlevelProtocol( $server_protocol ) {
		if ( ! is_string( $server_protocol ) || '' === $server_protocol ) {
			return false;
		}
		$upper = strtoupper( $server_protocol );
		return 0 !== strpos( $upper, 'HTTP/2' ) && 0 !== strpos( $upper, 'HTTP/3' );
	}

	/**
	 * Read a header value as a string; returns '' when absent or non-string.
	 *
	 * @param array  $headers Normalised header map (lower-case keys).
	 * @param string $key     Lower-cased header name.
	 * @return string
	 */
	private static function get( $headers, $key ) {
		if ( ! isset( $headers[ $key ] ) || ! is_string( $headers[ $key ] ) ) {
			return '';
		}
		return $headers[ $key ];
	}

	/**
	 * Extract the Chrome major version from a UA if it is recent enough to ship Sec-Ch-Ua.
	 *
	 * @param string $ua User-Agent string.
	 * @return int|null Chrome major version when >= MIN_SEC_CH_UA_CHROME_VERSION, otherwise null.
	 */
	private static function modernChromeVersion( $ua ) {
		if ( ! is_string( $ua ) || '' === $ua ) {
			return null;
		}
		if ( 1 === preg_match( '~Chrome/(\d+)~', $ua, $m ) ) {
			$v = (int) $m[1];
			if ( $v >= self::MIN_SEC_CH_UA_CHROME_VERSION ) {
				return $v;
			}
		}
		return null;
	}
}
