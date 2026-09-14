<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * IP canonicalisation for rate-limit keys.
 *
 * IPv6 networks routinely assign a whole /64 to a single subscriber, so
 * rate-limiting individual addresses is trivially bypassed. The Phase 1
 * engineering definition therefore collapses IPv6 addresses to their /64
 * prefix before use as a counter key. IPv4 addresses pass through unchanged.
 *
 * IPv6-mapped IPv4 addresses (::ffff:a.b.c.d) are first de-mapped to their
 * IPv4 form so they share a bucket with their direct-IPv4 equivalent.
 *
 * All methods fail open: any malformed input yields an empty string, which
 * the rate limiter treats as "skip rate limiting for this request".
 *
 * @since 4.0.0
 */
class IpNormalizer {

	/**
	 * Canonicalise an IP for use as a rate-limit counter key.
	 *
	 * @param mixed $ip IP address in textual form.
	 * @return string Canonical key (empty string on invalid input).
	 */
	public static function forRateLimit( $ip ) {
		if ( ! is_string( $ip ) || '' === $ip ) {
			return '';
		}
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		$bin = inet_pton( $ip );
		if ( false === $bin ) {
			return '';
		}
		if ( 4 === strlen( $bin ) ) {
			// IPv4 — round-trip through inet_ntop to strip any stray formatting.
			$textual = inet_ntop( $bin );
			return false === $textual ? '' : $textual;
		}
		if ( 16 === strlen( $bin ) ) {
			// Demap IPv6-mapped IPv4 → treat as IPv4.
			$mapped_prefix = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff";
			if ( substr( $bin, 0, 12 ) === $mapped_prefix ) {
				$textual = inet_ntop( substr( $bin, 12 ) );
				return false === $textual ? '' : $textual;
			}
			// Truncate to /64 — keep first 8 bytes, zero the rest.
			$truncated = substr( $bin, 0, 8 ) . str_repeat( "\x00", 8 );
			$textual   = inet_ntop( $truncated );
			return false === $textual ? '' : $textual . '/64';
		}
		return '';
	}
}
