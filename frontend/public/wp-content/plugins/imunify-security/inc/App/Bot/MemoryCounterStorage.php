<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * MEMORY-engine counter storage backed by a WordPress `$wpdb` table.
 *
 * Uses a MySQL/MariaDB table with ENGINE=MEMORY for sub-millisecond
 * rate-limit accounting. Data is lost on DB server restart — acceptable
 * because rate-limit windows are 60 seconds or less.
 *
 * Schema is created lazily on first use: if the first query fails with a
 * "doesn't exist" error, the table is created and the query is retried once.
 * A `$schema_init_attempted` flag prevents infinite retry loops.
 *
 * All methods are fail-open: any error returns a safe default (0 / no-op)
 * rather than throwing, so a DB hiccup never takes down the plugin.
 *
 * @since 4.0.0
 */
class MemoryCounterStorage implements CounterStorageInterface {

	use DbErrorDetection;

	/**
	 * Session max_heap_table_size set before creating the MEMORY table (4 MB).
	 */
	const MAX_HEAP_TABLE_SIZE = 4194304;

	/**
	 * WordPress database handle.
	 *
	 * @var object
	 */
	private $wpdb;

	/**
	 * Full table name (with WP prefix).
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Prevents multiple CREATE TABLE attempts per request.
	 *
	 * @var bool
	 */
	private $schema_init_attempted = false;

	/**
	 * Wrap a WordPress database handle.
	 *
	 * @param object $wpdb Connected WordPress $wpdb instance.
	 */
	public function __construct( $wpdb ) {
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'imunify_bot_rl';
	}

	/**
	 * {@inheritdoc}
	 */
	public function increment( $key, $ttl_seconds ) {
		$now     = time();
		$expires = $now + (int) $ttl_seconds;
		$table   = $this->table;

		// Table name cannot be a placeholder in prepare(), so we interpolate it
		// directly. The key and numeric values are properly escaped via prepare().
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$insert_sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"INSERT INTO `{$table}` (`rl_key`, `counter`, `expires_at`) VALUES (%s, 1, %d)"
			. ' ON DUPLICATE KEY UPDATE `counter` = IF(`expires_at` > %d, `counter` + 1, 1), `expires_at` = IF(`expires_at` > %d, `expires_at`, %d)',
			$key,
			$expires,
			$now,
			$now,
			$expires
		);

		$result = $this->exec( $insert_sql );
		if ( false === $result ) {
			return 0;
		}

		return $this->getAt( $key, $now );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get( $key ) {
		return $this->getAt( $key, time() );
	}

	/**
	 * Read the counter value using a fixed timestamp.
	 *
	 * Shared by get() and increment() so the INSERT and SELECT in
	 * increment() use the same $now — avoids a TOCTOU window when
	 * a second boundary is crossed between the two DB operations.
	 *
	 * @param string $key Counter key.
	 * @param int    $now Unix timestamp for the expiry check.
	 * @return int Current value, or 0 when missing/expired.
	 */
	private function getAt( $key, $now ) {
		$table = $this->table;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT `counter` FROM `{$table}` WHERE `rl_key` = %s AND `expires_at` > %d",
			$key,
			$now
		);
		$suppress = $this->wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$value = $this->wpdb->get_var( $sql );
		if ( null === $value ) {
			if ( $this->isTableMissing() && ! $this->schema_init_attempted ) {
				$this->schema_init_attempted = true;
				$this->createTable();
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$value = $this->wpdb->get_var( $sql );
			}
		}
		$this->wpdb->suppress_errors( $suppress );
		return null !== $value ? (int) $value : 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function set( $key, $value, $ttl_seconds ) {
		$expires   = time() + (int) $ttl_seconds;
		$table     = $this->table;
		$int_value = (int) $value;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"INSERT INTO `{$table}` (`rl_key`, `counter`, `expires_at`) VALUES (%s, %d, %d)"
			. ' ON DUPLICATE KEY UPDATE `counter` = %d, `expires_at` = %d',
			$key,
			$int_value,
			$expires,
			$int_value,
			$expires
		);

		$this->exec( $sql );
	}

	/**
	 * {@inheritdoc}
	 */
	public function reset( $key ) {
		$table = $this->table;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"DELETE FROM `{$table}` WHERE `rl_key` = %s",
			$key
		);

		$this->exec( $sql );
	}

	/**
	 * {@inheritdoc}
	 *
	 * DROP + CREATE is the only way to reclaim MEMORY engine space —
	 * DELETE does not free memory (MySQL docs: "Memory is reclaimed
	 * only when the entire table is deleted"). Counter rows have short
	 * TTLs (≤ 60 s) so losing them during a periodic cleanup is
	 * acceptable and fail-open (counters reset to 0, requests pass).
	 */
	public function cleanup() {
		$table    = $this->table;
		$suppress = $this->wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		$this->createTable();
		$this->wpdb->suppress_errors( $suppress );
	}

	/**
	 * {@inheritdoc}
	 */
	public function name() {
		return 'memory';
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Execute a write query with lazy schema initialisation.
	 *
	 * If the query fails because the table doesn't exist and we haven't tried
	 * creating it yet, we run CREATE TABLE and retry once.
	 *
	 * @param string $sql Prepared SQL string.
	 * @return int|false Number of affected rows, or false on failure.
	 */
	private function exec( $sql ) {
		$suppress = $this->wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->wpdb->query( $sql );
		if ( false === $result && ! $this->schema_init_attempted ) {
			if ( $this->isTableMissing() ) {
				$this->schema_init_attempted = true;
				$this->createTable();
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$result = $this->wpdb->query( $sql );
			} elseif ( $this->isTableFull() ) {
				// MEMORY engine: DELETE does not reclaim space — only
				// DROP + CREATE frees the memory (see MySQL docs).
				$this->schema_init_attempted = true;
				StorageEventBuffer::record(
					'MEMORY table full: imunify_bot_rl — dropped and recreated',
					'bot_table_full_rl',
					array( 'bot_storage_table_full', 'imunify_bot_rl' )
				);
				$this->cleanup();
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$result = $this->wpdb->query( $sql );
			}
		}
		$this->wpdb->suppress_errors( $suppress );
		return $result;
	}

	/**
	 * Create the rate-limit MEMORY table if it does not exist.
	 *
	 * ASCII charset is intentional: keys are built from internal ASCII
	 * identifiers (preset, category, IP, hex site-id). MEMORY engine
	 * stores VARCHAR as fixed-width CHAR — ascii uses 1 byte/char vs
	 * utf8mb4's 4 bytes/char, giving ~4x more rows per heap allocation.
	 *
	 * @return void
	 */
	private function createTable() {
		$table = $this->table;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$this->wpdb->query( 'SET max_heap_table_size = ' . self::MAX_HEAP_TABLE_SIZE );
		$sql = 'CREATE TABLE IF NOT EXISTS `' . $table . '` ('
			. ' `rl_key`     VARCHAR(191) NOT NULL,'
			. ' `counter`    INT UNSIGNED NOT NULL DEFAULT 0,'
			. ' `expires_at` BIGINT UNSIGNED NOT NULL,'
			. ' PRIMARY KEY (`rl_key`),'
			. ' KEY `idx_expires_at` (`expires_at`) USING BTREE'
			. ') ENGINE=MEMORY DEFAULT CHARSET=ascii';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->wpdb->query( $sql );
		if ( false === $result ) {
			// CREATE TABLE privilege may be missing on shared hosts.
			// A separate CREATE TABLE probe at detection time was
			// considered but skipped — it would add DDL overhead on
			// every request for the 99%+ of hosts where it works fine.
			StorageEventBuffer::record(
				'Failed to create MEMORY table: ' . $this->table,
				'bot_create_table_failed_rl',
				array( 'bot_storage_create_failed', 'imunify_bot_rl' ),
				array( 'table' => $this->table )
			);
		}
	}
}
