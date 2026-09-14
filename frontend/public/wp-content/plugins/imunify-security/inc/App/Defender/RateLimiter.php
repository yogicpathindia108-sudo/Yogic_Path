<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Defender;

/**
 * Rate limiter for incident reporting DoS protection.
 *
 * Tracks incidents per IP address per rule and enforces rate limits
 * using WordPress transients for temporary storage.
 *
 * @since 2.1.0
 */
class RateLimiter {

	/**
	 * Maximum incidents allowed per time window.
	 */
	const MAX_INCIDENTS_PER_WINDOW = 100;

	/**
	 * Time window in seconds (15 minutes).
	 */
	const TIME_WINDOW = 900;

	/**
	 * Transient key prefix for rate limiting.
	 */
	const TRANSIENT_PREFIX = 'imunify_incident_rate_';

	/**
	 * Check if an incident can be recorded for the given rule and IP.
	 *
	 * @param string $rule_id    The rule ID that triggered the incident.
	 * @param string $ip_address The client IP address.
	 *
	 * @return bool True if incident can be recorded, false if rate limit exceeded.
	 */
	public function checkRateLimit( $rule_id, $ip_address ) {
		$transient_key = $this->generateTransientKey( $rule_id, $ip_address );

		// Get current count from transient.
		$current_count = get_transient( $transient_key );

		if ( false === $current_count ) {
			// No existing transient, start with count 1.
			$current_count = 0;
		}

		// Check if we've reached the limit.
		if ( $current_count >= self::MAX_INCIDENTS_PER_WINDOW ) {
			return false;
		}

		// Increment count and update transient.
		$new_count = $current_count + 1;
		set_transient( $transient_key, $new_count, self::TIME_WINDOW );

		return true;
	}

	/**
	 * Generate a unique transient key for the rule and IP combination.
	 *
	 * @param string $rule_id    The rule ID.
	 * @param string $ip_address The IP address.
	 *
	 * @return string The transient key.
	 */
	private function generateTransientKey( $rule_id, $ip_address ) {
		// Hash the IP address for brevity and to avoid special characters.
		$ip_hash = md5( $ip_address ); // nosemgrep: weak-crypto -- used for cache key, not security.

		return self::TRANSIENT_PREFIX . $rule_id . '_' . $ip_hash;
	}

	/**
	 * Get current incident count for a rule and IP combination.
	 *
	 * This method is primarily for testing and debugging purposes.
	 *
	 * @param string $rule_id    The rule ID.
	 * @param string $ip_address The IP address.
	 *
	 * @return int Current incident count.
	 */
	public function getCurrentCount( $rule_id, $ip_address ) {
		$transient_key = $this->generateTransientKey( $rule_id, $ip_address );
		$count         = get_transient( $transient_key );

		return false === $count ? 0 : $count;
	}

	/**
	 * Clear rate limit data for a specific rule and IP combination.
	 *
	 * This method is primarily for testing purposes.
	 *
	 * @param string $rule_id    The rule ID.
	 * @param string $ip_address The IP address.
	 *
	 * @return bool True if transient was deleted, false otherwise.
	 */
	public function clearRateLimit( $rule_id, $ip_address ) {
		$transient_key = $this->generateTransientKey( $rule_id, $ip_address );
		return delete_transient( $transient_key );
	}
}
