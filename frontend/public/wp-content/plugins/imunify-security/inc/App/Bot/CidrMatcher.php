<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * CIDR range matching for IPv4 and IPv6.
 *
 * All methods fail open: malformed input returns false rather than throwing,
 * in keeping with the bot-protection subsystem's never-break-the-site principle.
 *
 * @since 4.0.0
 */
class CidrMatcher {

	/**
	 * Check whether an IP address falls within a CIDR block.
	 *
	 * @param string $ip   IP address (IPv4 or IPv6 textual form).
	 * @param string $cidr CIDR block in "network/prefix" form.
	 * @return bool True when $ip is inside $cidr; false on no match or invalid input.
	 */
	public static function matches( $ip, $cidr ) {
		if ( ! is_string( $ip ) || ! is_string( $cidr ) ) {
			return false;
		}
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}
		return self::matchesParsed( inet_pton( $ip ), $cidr );
	}

	/**
	 * Match a pre-parsed IP binary against a CIDR block.
	 *
	 * @param string $ip_bin Binary IP from inet_pton().
	 * @param string $cidr   CIDR block in "network/prefix" form.
	 * @return bool
	 */
	private static function matchesParsed( $ip_bin, $cidr ) {
		if ( ! is_string( $cidr ) ) {
			return false;
		}

		$slash_pos = strpos( $cidr, '/' );
		if ( false === $slash_pos ) {
			return false;
		}

		$network    = substr( $cidr, 0, $slash_pos );
		$prefix_str = substr( $cidr, $slash_pos + 1 );

		if ( '' === $prefix_str || ! ctype_digit( $prefix_str ) ) {
			return false;
		}
		$prefix = (int) $prefix_str;

		if ( false === filter_var( $network, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		$net_bin = inet_pton( $network );
		$cmp_bin = $ip_bin;

		if ( 16 === strlen( $cmp_bin ) && 4 === strlen( $net_bin ) ) {
			$mapped_prefix = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff";
			if ( substr( $cmp_bin, 0, 12 ) === $mapped_prefix ) {
				$cmp_bin = substr( $cmp_bin, 12 );
			}
		}

		if ( strlen( $cmp_bin ) !== strlen( $net_bin ) ) {
			return false;
		}

		$max_prefix = strlen( $net_bin ) * 8;
		if ( $prefix < 0 || $prefix > $max_prefix ) {
			return false;
		}

		if ( 0 === $prefix ) {
			return true;
		}

		$whole_bytes = (int) ( $prefix / 8 );
		$remainder   = $prefix % 8;

		if ( $whole_bytes > 0 && substr( $cmp_bin, 0, $whole_bytes ) !== substr( $net_bin, 0, $whole_bytes ) ) {
			return false;
		}

		if ( $remainder > 0 ) {
			$mask     = ( 0xff << ( 8 - $remainder ) ) & 0xff;
			$ip_byte  = ord( $cmp_bin[ $whole_bytes ] );
			$net_byte = ord( $net_bin[ $whole_bytes ] );
			if ( ( $ip_byte & $mask ) !== ( $net_byte & $mask ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check whether an IP address falls within any of the given CIDR blocks.
	 *
	 * Short-circuits on the first match. Invalid CIDRs in the list are skipped
	 * silently rather than aborting the search.
	 *
	 * @param string $ip    IP address (IPv4 or IPv6 textual form).
	 * @param array  $cidrs List of CIDR blocks.
	 * @return bool True when $ip is inside at least one CIDR; false otherwise.
	 */
	public static function matchesAny( $ip, $cidrs ) {
		if ( ! is_array( $cidrs ) ) {
			return false;
		}
		foreach ( $cidrs as $cidr ) {
			if ( self::matches( $ip, $cidr ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check whether an IP falls within any CIDR in a first-octet-bucketed structure.
	 *
	 * @param string $ip       IP address (IPv4 or IPv6 textual form).
	 * @param array  $bucketed Array with 'by_octet' => array(int => array of CIDRs)
	 *                         and 'broad' => array of CIDRs.
	 * @return bool
	 */
	public static function matchesAnyBucketed( $ip, $bucketed ) {
		if ( ! is_string( $ip ) || '' === $ip || ! is_array( $bucketed ) ) {
			return false;
		}
		$by_octet = isset( $bucketed['by_octet'] ) && is_array( $bucketed['by_octet'] )
			? $bucketed['by_octet'] : array();
		$broad    = isset( $bucketed['broad'] ) && is_array( $bucketed['broad'] )
			? $bucketed['broad'] : array();

		if ( empty( $by_octet ) && empty( $broad ) ) {
			return false;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- inet_pton emits a warning for malformed input; suppression is intentional.
		$ip_bin = @inet_pton( $ip );
		if ( false === $ip_bin ) {
			return false;
		}

		// Demap IPv6-mapped IPv4 for bucket selection.
		$lookup_bin = $ip_bin;
		if ( 16 === strlen( $ip_bin ) ) {
			$mapped_prefix = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff";
			if ( substr( $ip_bin, 0, 12 ) === $mapped_prefix ) {
				$lookup_bin = substr( $ip_bin, 12 );
			}
		}

		$octet = ord( $lookup_bin[0] );

		if ( isset( $by_octet[ $octet ] ) ) {
			foreach ( $by_octet[ $octet ] as $cidr ) {
				if ( self::matchesParsed( $ip_bin, $cidr ) ) {
					return true;
				}
			}
		}

		foreach ( $broad as $cidr ) {
			if ( self::matchesParsed( $ip_bin, $cidr ) ) {
				return true;
			}
		}

		return false;
	}
}
