<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Helpers;

/**
 * Date and time formatter helper.
 */
class DateTimeFormatter {
	/**
	 * Format timestamp into localized date and time string.
	 * Example: "25 Dec at 8:00 PM"
	 *
	 * @param int $timestamp Unix timestamp in UTC.
	 * @return string Formatted date and time.
	 */
	public static function formatScanTime( $timestamp ) {
		// Convert UTC timestamp to local timestamp using WordPress settings.
		$local_timestamp = $timestamp + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );

		// Get WordPress date format settings but force specific format for scan times.
		$date_format = _x( 'j M', 'scan date format', 'imunify-security' );
		$time_format = _x( 'g:i A', 'scan time format', 'imunify-security' );

		$date = wp_date( $date_format, $local_timestamp );
		$time = wp_date( $time_format, $local_timestamp );

		return sprintf(
			/* translators: 1: Date (e.g. "25 Dec"), 2: Time (e.g. "8:00 PM") */
			_x( '%1$s at %2$s', 'scan time', 'imunify-security' ),
			$date,
			$time
		);
	}

	/**
	 * Format timestamp into localized detection date string.
	 * Example: "Jan 15"
	 *
	 * @param int $timestamp Unix timestamp in UTC.
	 * @return string Formatted date.
	 */
	public static function formatDetectionDate( $timestamp ) {
		// Convert UTC timestamp to local timestamp using WordPress settings.
		$local_timestamp = $timestamp + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );

		// Force specific format for detection dates.
		$date_format = _x( 'M j', 'detection date format', 'imunify-security' );

		return date_i18n( $date_format, $local_timestamp );
	}

	/**
	 * Format timestamp into a standardized date and time string.
	 * Example: "Jan 14, 02:05:00"
	 *
	 * @param int $timestamp Unix timestamp in UTC.
	 * @return string Formatted date and time.
	 */
	public static function formatTimestamp( $timestamp ) {
		// Convert UTC timestamp to local timestamp using WordPress settings.
		$local_timestamp = $timestamp + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );

		return date_i18n( 'M j, H:i:s', $local_timestamp );
	}

	/**
	 * Format timestamp as relative time if recent, otherwise as date.
	 *
	 * Examples:
	 * - "5 secs ago" (if less than 1 minute ago)
	 * - "3 mins ago" (if less than 1 hour ago)
	 * - "2 hrs ago" (if less than 1 day ago)
	 * - "Jan 25" (if older than 1 day)
	 *
	 * @param int $timestamp Unix timestamp in UTC.
	 * @return string Formatted relative time or date.
	 */
	public static function formatRelativeTime( $timestamp ) {
		$now  = time();
		$diff = $now - $timestamp;

		// Less than 1 minute ago.
		if ( $diff < MINUTE_IN_SECONDS ) {
			$seconds = max( 1, $diff );
			return sprintf(
				/* translators: %d: number of seconds */
				_n( '%d sec ago', '%d secs ago', $seconds, 'imunify-security' ),
				$seconds
			);
		}

		// Less than 1 hour ago.
		if ( $diff < HOUR_IN_SECONDS ) {
			$minutes = (int) floor( $diff / MINUTE_IN_SECONDS );
			return sprintf(
				/* translators: %d: number of minutes */
				_n( '%d min ago', '%d mins ago', $minutes, 'imunify-security' ),
				$minutes
			);
		}

		// Less than 1 day ago.
		if ( $diff < DAY_IN_SECONDS ) {
			$hours = (int) floor( $diff / HOUR_IN_SECONDS );
			return sprintf(
				/* translators: %d: number of hours */
				_n( '%d hr ago', '%d hrs ago', $hours, 'imunify-security' ),
				$hours
			);
		}

		// Older than 1 day - show date.
		return self::formatDetectionDate( $timestamp );
	}
}
