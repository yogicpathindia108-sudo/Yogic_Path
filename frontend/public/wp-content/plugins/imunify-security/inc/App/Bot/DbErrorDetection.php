<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Shared error-detection helpers for wpdb-backed storage classes.
 *
 * Uses numeric MySQL/MariaDB error codes via $wpdb->dbh->errno when
 * available, with a string-matching fallback for custom DB drop-ins
 * (HyperDB, LudicrousDB) that may not expose a mysqli handle.
 *
 * @since 4.0.0
 */
trait DbErrorDetection {

	/**
	 * Return the numeric MySQL error code from the last query, or 0.
	 *
	 * @return int
	 */
	private function getLastErrorCode() {
		if ( isset( $this->wpdb->dbh ) && is_object( $this->wpdb->dbh ) && isset( $this->wpdb->dbh->errno ) ) {
			return (int) $this->wpdb->dbh->errno;
		}
		return 0;
	}

	/**
	 * Check whether the last wpdb error indicates a missing table.
	 *
	 * MySQL error 1146: "Table '%s.%s' doesn't exist".
	 *
	 * @return bool
	 */
	private function isTableMissing() {
		$code = $this->getLastErrorCode();
		if ( 1146 === $code ) {
			return true;
		}
		return false !== strpos( (string) $this->wpdb->last_error, "doesn't exist" );
	}

	/**
	 * Check whether the last wpdb error indicates the table is full.
	 *
	 * MySQL error 1114: "The table '%s' is full".
	 *
	 * @return bool
	 */
	private function isTableFull() {
		$code = $this->getLastErrorCode();
		if ( 1114 === $code ) {
			return true;
		}
		return false !== strpos( (string) $this->wpdb->last_error, 'is full' );
	}
}
