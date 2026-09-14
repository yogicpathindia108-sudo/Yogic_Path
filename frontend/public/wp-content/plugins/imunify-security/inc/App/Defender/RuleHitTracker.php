<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Defender;

use CloudLinux\Imunify\App\Defender\Model\Rule;

/**
 * Tracks rule hits using WordPress transients.
 *
 * Stores hit counts per day for the last 7 days for each rule,
 * along with the timestamp of the most recent hit.
 */
class RuleHitTracker {
	/**
	 * Transient name prefix.
	 */
	const TRANSIENT_PREFIX = 'imunify_rule_hits_';

	/**
	 * Number of days to track.
	 */
	const TRACKING_DAYS = 7;

	/**
	 * Transient expiration time in seconds (8 days to keep 7 days + buffer).
	 */
	const TRANSIENT_EXPIRATION = 8 * DAY_IN_SECONDS;

	/**
	 * Record a hit for a rule.
	 *
	 * @param Rule $rule Rule that was triggered.
	 *
	 * @return void
	 */
	public function recordHit( Rule $rule ) {
		$ruleId     = $rule->getId();
		$today      = gmdate( 'Y-m-d' );
		$storedData = $this->getStoredData( $ruleId );
		$hitsData   = isset( $storedData['hits'] ) ? $storedData['hits'] : array();

		// Increment hit count for today.
		if ( ! isset( $hitsData[ $today ] ) ) {
			$hitsData[ $today ] = 0;
		}
		$hitsData[ $today ]++;

		// Remove data older than 7 days.
		$hitsData = $this->cleanupOldData( $hitsData );

		// Save updated data with current timestamp.
		$this->setStoredData(
			$ruleId,
			array(
				'hits'           => $hitsData,
				'last_timestamp' => time(),
			)
		);
	}

	/**
	 * Get hit counts for a rule.
	 *
	 * @param Rule $rule Rule to get hits for.
	 *
	 * @return array Associative array with dates as keys and hit counts as values.
	 */
	public function getHitsForRule( Rule $rule ) {
		$ruleId     = $rule->getId();
		$storedData = $this->getStoredData( $ruleId );
		$hitsData   = isset( $storedData['hits'] ) ? $storedData['hits'] : array();
		return $this->cleanupOldData( $hitsData );
	}

	/**
	 * Get total hit count for the last 7 days for a rule.
	 *
	 * @param Rule $rule Rule to get total hits for.
	 *
	 * @return int Total number of hits in the last 7 days.
	 */
	public function getTotalHitsForRule( Rule $rule ) {
		$hitsData = $this->getHitsForRule( $rule );
		return array_sum( $hitsData );
	}

	/**
	 * Get the timestamp of the most recent hit for a rule.
	 *
	 * @param Rule $rule Rule to get last timestamp for.
	 *
	 * @return int|null Unix timestamp of the last hit, or null if no hits recorded.
	 */
	public function getLastTimestampForRule( Rule $rule ) {
		$ruleId     = $rule->getId();
		$storedData = $this->getStoredData( $ruleId );
		return isset( $storedData['last_timestamp'] ) ? (int) $storedData['last_timestamp'] : null;
	}

	/**
	 * Get stored data from transient.
	 *
	 * @param string $ruleId Rule identifier.
	 *
	 * @return array Array with 'hits' and optionally 'last_timestamp' keys.
	 */
	private function getStoredData( $ruleId ) {
		$transientName = $this->getTransientName( $ruleId );
		$data          = get_transient( $transientName );

		if ( false === $data ) {
			return array( 'hits' => array() );
		}

		$decoded = json_decode( $data, true );
		if ( ! is_array( $decoded ) || ! isset( $decoded['hits'] ) ) {
			return array( 'hits' => array() );
		}

		return $decoded;
	}

	/**
	 * Set stored data in transient.
	 *
	 * @param string $ruleId     Rule identifier.
	 * @param array  $storedData Array with 'hits' and 'last_timestamp' keys.
	 *
	 * @return void
	 */
	private function setStoredData( $ruleId, array $storedData ) {
		$transientName = $this->getTransientName( $ruleId );
		$jsonData      = wp_json_encode( $storedData );
		set_transient( $transientName, $jsonData, self::TRANSIENT_EXPIRATION );
	}

	/**
	 * Get transient name for a rule.
	 *
	 * WordPress transients have a 40-character limit for the name.
	 * We use a hash of the rule ID if it's too long.
	 *
	 * @param string $ruleId Rule identifier.
	 *
	 * @return string Transient name.
	 */
	private function getTransientName( $ruleId ) {
		$prefix    = self::TRANSIENT_PREFIX;
		$maxLength = 40 - strlen( $prefix );

		// If rule ID is too long, use a hash.
		if ( strlen( $ruleId ) > $maxLength ) {
			$hash = md5( $ruleId ); // nosemgrep: weak-crypto -- used for key shortening, not security.
			return $prefix . substr( $hash, 0, $maxLength );
		}

		return $prefix . $ruleId;
	}

	/**
	 * Remove data older than the tracking period.
	 *
	 * @param array $hitsData Associative array with dates as keys.
	 *
	 * @return array Cleaned data with only the last 7 days.
	 */
	private function cleanupOldData( array $hitsData ) {
		$today      = new \DateTime( 'today', new \DateTimeZone( 'UTC' ) );
		$cutoffDate = clone $today;
		$cutoffDate->modify( '-' . self::TRACKING_DAYS . ' days' );

		$cleaned = array();
		foreach ( $hitsData as $date => $count ) {
			$dateObj = \DateTime::createFromFormat( 'Y-m-d', $date, new \DateTimeZone( 'UTC' ) );
			if ( $dateObj && $dateObj >= $cutoffDate ) {
				$cleaned[ $date ] = $count;
			}
		}

		return $cleaned;
	}
}
