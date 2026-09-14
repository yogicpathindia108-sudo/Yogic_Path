<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Defender\Model;

/**
 * Severity enum-like class.
 *
 * This class provides an enum-like functionality for CVE severity levels
 * using CVSS scores (0-10 scale) that is compatible with PHP 5.6.
 */
class Severity {
	/**
	 * Default severity for CVSS Medium range (4.0-6.9).
	 *
	 * @var float
	 */
	const DEFAULT_SEVERITY = 5.0;

	/**
	 * Minimum valid CVSS score.
	 *
	 * @var float
	 */
	const MIN_SCORE = 0.0;

	/**
	 * Maximum valid CVSS score.
	 *
	 * @var float
	 */
	const MAX_SCORE = 10.0;

	/**
	 * Normalize a severity level to a numeric CVSS score.
	 *
	 * @param float|int|string $severity Severity level to normalize (numeric CVSS score).
	 *
	 * @return float Normalized CVSS score (0-10) or default if invalid.
	 */
	public static function normalize( $severity ) {
		if ( ! self::isValid( $severity ) ) {
			return self::DEFAULT_SEVERITY;
		}

		return floatval( $severity );
	}

	/**
	 * Check if a severity level is valid.
	 *
	 * @param float|int|string $severity Severity level to check (numeric CVSS score).
	 *
	 * @return bool True if the severity level is valid, false otherwise.
	 */
	public static function isValid( $severity ) {
		if ( ! is_numeric( $severity ) ) {
			return false;
		}

		$score = floatval( $severity );
		return $score >= self::MIN_SCORE && $score <= self::MAX_SCORE;
	}

	/**
	 * Convert a numeric CVSS score to a severity string.
	 *
	 * @param float|int $score CVSS score (0-10).
	 *
	 * @return string Severity string: 'critical', 'high', 'medium', 'low', or 'none'.
	 */
	public static function getSeverityString( $score ) {
		$score = floatval( $score );
		if ( $score >= 9.0 ) {
			return 'critical';
		} elseif ( $score >= 7.0 ) {
			return 'high';
		} elseif ( $score >= 4.0 ) {
			return 'medium';
		} elseif ( $score > 0.0 ) {
			return 'low';
		} else {
			return 'none';
		}
	}

	/**
	 * Get the display name for a severity level based on CVSS score.
	 *
	 * @param float|int $score CVSS score (0-10).
	 *
	 * @return string Display name with CVSS score in brackets.
	 */
	public static function getDisplayName( $score ) {
		$score = floatval( $score );
		return '(CVSS ' . number_format( $score, 1 ) . ')';
	}

	/**
	 * Get the numeric value for a severity level (returns the CVSS score itself).
	 *
	 * @param float|int $score CVSS score (0-10).
	 *
	 * @return float Numeric CVSS score (0-10, higher = more severe).
	 */
	public static function getNumericValue( $score ) {
		return self::normalize( $score );
	}
}
