<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Helpers;

/**
 * Deletes transients whose option names share a key prefix, including their
 * companion _transient_timeout_ rows, in a single error-suppressed query.
 */
class TransientCleaner {

	/**
	 * Delete all transients whose names share a common prefix.
	 *
	 * Only clears DB-backed transients; sites with a persistent object cache self-expire.
	 *
	 * @param string $prefix Transient key prefix, without the _transient_ wrapper.
	 * @return void
	 */
	public static function deleteByPrefix( $prefix ) {
		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			return;
		}
		$suppress = $wpdb->suppress_errors( true );
		$value    = $wpdb->esc_like( '_transient_' . $prefix ) . '%';
		$timeout  = $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $value, $timeout ) );
		$wpdb->suppress_errors( $suppress );
	}
}
