<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * InnoDB + MEMORY dual-write block storage backed by WordPress `$wpdb`.
 *
 * Block records are written to both an InnoDB table (durable, survives restarts)
 * and a MEMORY-engine table (fast per-request lookup). Violation counters live
 * exclusively in InnoDB so they survive MariaDB/MySQL restarts.
 *
 * On first request, `isBlocked()` queries only the MEMORY table. If the IP is
 * not found AND the active table has not yet been repopulated this request,
 * the MEMORY table is rebuilt from InnoDB and the query is retried. This
 * handles both cold starts (MariaDB restart wiped MEMORY data) and the
 * first request after a plugin activation.
 *
 * Schema creation is lazy: each exec helper creates the relevant table(s) on
 * the first "doesn't exist" error and retries the original query once. A flag
 * prevents infinite retry loops.
 *
 * All methods are fail-open: errors return safe defaults (false / 0 / no-op)
 * rather than throwing, so a DB hiccup never takes down the plugin.
 *
 * @since 4.0.0
 */
class MemoryBlockStorage implements BlockStorageInterface {

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
	 * Full InnoDB blocks table name (with WP prefix).
	 *
	 * @var string
	 */
	private $blocks_table;

	/**
	 * Full MEMORY active-blocks table name (with WP prefix).
	 *
	 * @var string
	 */
	private $active_table;

	/**
	 * Full InnoDB violations table name (with WP prefix).
	 *
	 * @var string
	 */
	private $violations_table;

	/**
	 * Prevents multiple CREATE TABLE attempts for blocks/active per request.
	 *
	 * @var bool
	 */
	private $blocks_schema_init = false;

	/**
	 * Prevents multiple CREATE TABLE attempts for violations per request.
	 *
	 * @var bool
	 */
	private $violations_schema_init = false;

	/**
	 * Whether the MEMORY active table has been repopulated this request.
	 *
	 * @var bool
	 */
	private $repopulated = false;

	/**
	 * Wrap a WordPress database handle.
	 *
	 * @param object $wpdb Connected WordPress $wpdb instance.
	 */
	public function __construct( $wpdb ) {
		$this->wpdb             = $wpdb;
		$this->blocks_table     = $wpdb->prefix . 'imunify_bot_blocks';
		$this->active_table     = $wpdb->prefix . 'imunify_bot_blocks_active';
		$this->violations_table = $wpdb->prefix . 'imunify_bot_violations';
	}

	/**
	 * {@inheritdoc}
	 */
	public function isBlocked( $ip_key, $site_id ) {
		$result = $this->queryActiveTable( $ip_key, $site_id );

		if ( true === $result ) {
			return true;
		}

		// Table missing → DB restart wiped the MEMORY table. Recreate
		// schema, repopulate from InnoDB, and retry. Only attempt once
		// per request to avoid infinite loops.
		if ( 'missing' === $result && ! $this->repopulated ) {
			$this->repopulated = true;
			$suppress          = $this->wpdb->suppress_errors( true );
			if ( ! $this->blocks_schema_init ) {
				$this->blocks_schema_init = true;
				$this->createBlocksTable();
				$this->createActiveTable();
			}
			$this->repopulateActiveTable();
			$this->wpdb->suppress_errors( $suppress );
			return true === $this->queryActiveTable( $ip_key, $site_id );
		}

		// IP not in the MEMORY table but table exists → not blocked.
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function writeBlock( $ip_key, $site_id, $category, $preset, $ttl_seconds ) {
		$now     = time();
		$expires = $now + (int) $ttl_seconds;

		// Dual-write: InnoDB first, then MEMORY.
		$this->execBlocks( $this->prepareBlockUpsert( $this->blocks_table, $ip_key, $site_id, $category, $preset, $now, $expires ) );
		$this->execBlocks( $this->prepareBlockUpsert( $this->active_table, $ip_key, $site_id, $category, $preset, $now, $expires ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function incrementViolation( $key, $ttl_seconds ) {
		$now            = time();
		$expires        = $now + (int) $ttl_seconds;
		$violations_tbl = $this->violations_table;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$insert_sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"INSERT INTO `{$violations_tbl}` (`viol_key`, `counter`, `expires_at`) VALUES (%s, 1, %d)"
			. ' ON DUPLICATE KEY UPDATE `counter` = IF(`expires_at` > %d, `counter` + 1, 1), `expires_at` = IF(`expires_at` > %d, `expires_at`, %d)',
			$key,
			$expires,
			$now,
			$now,
			$expires
		);

		$result = $this->execViolations( $insert_sql );
		if ( false === $result ) {
			return 0;
		}

		return $this->getViolationCountAt( $key, $now );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getViolationCount( $key ) {
		return $this->getViolationCountAt( $key, time() );
	}

	/**
	 * Read the violation counter using a fixed timestamp.
	 *
	 * Shared by getViolationCount() and incrementViolation() so the
	 * INSERT and SELECT use the same $now — avoids a TOCTOU window.
	 *
	 * @param string $key Counter key.
	 * @param int    $now Unix timestamp for the expiry check.
	 * @return int Current value, or 0 when missing/expired.
	 */
	private function getViolationCountAt( $key, $now ) {
		$violations_tbl = $this->violations_table;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT `counter` FROM `{$violations_tbl}` WHERE `viol_key` = %s AND `expires_at` > %d",
			$key,
			$now
		);
		$suppress = $this->wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$value = $this->wpdb->get_var( $sql );
		$this->wpdb->suppress_errors( $suppress );
		return null !== $value ? (int) $value : 0;
	}

	/**
	 * {@inheritdoc}
	 *
	 * Deletes expired rows from InnoDB tables, then rebuilds the MEMORY
	 * active table to reclaim space (DROP + CREATE + repopulate).
	 */
	public function cleanup() {
		$blocks_tbl     = $this->blocks_table;
		$active_tbl     = $this->active_table;
		$violations_tbl = $this->violations_table;
		$now            = time();

		$suppress = $this->wpdb->suppress_errors( true );

		// LIMIT 0 resolves the table reference without touching rows; ob buffering
		// stops mysqli output from reaching error handlers that throw on E_WARNING.
		ob_start();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->query( "SELECT 1 FROM `{$blocks_tbl}` LIMIT 0" );
		ob_end_clean();

		if ( $this->isTableMissing() ) {
			$this->wpdb->suppress_errors( $suppress );
			return;
		}

		// Delete expired InnoDB blocks.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"DELETE FROM `{$blocks_tbl}` WHERE `expires_at` <= %d",
			$now
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$this->wpdb->query( $sql );

		// Reclaim MEMORY space: DROP + CREATE + repopulate.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->wpdb->query( "DROP TABLE IF EXISTS `{$active_tbl}`" );
		$this->createActiveTable();
		$this->repopulateActiveTable();

		// Delete expired violations.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$viol_sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"DELETE FROM `{$violations_tbl}` WHERE `expires_at` <= %d",
			$now
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$this->wpdb->query( $viol_sql );

		$this->wpdb->suppress_errors( $suppress );
	}

	/**
	 * {@inheritdoc}
	 */
	public function name() {
		return 'db';
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a prepared UPSERT statement for a blocks table.
	 *
	 * @param string $table    Full table name (with WP prefix).
	 * @param string $ip_key   Normalised IP key.
	 * @param string $site_id  Site scope identifier.
	 * @param string $category Bot category.
	 * @param string $preset   Active preset name.
	 * @param int    $now      Current Unix timestamp.
	 * @param int    $expires  Expiry Unix timestamp.
	 * @return string Prepared SQL.
	 */
	private function prepareBlockUpsert( $table, $ip_key, $site_id, $category, $preset, $now, $expires ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"INSERT INTO `{$table}` (`ip_key`, `site_id`, `category`, `preset`, `blocked_at`, `expires_at`)"
			. ' VALUES (%s, %s, %s, %s, %d, %d)'
			. ' ON DUPLICATE KEY UPDATE `category` = %s, `preset` = %s, `blocked_at` = %d, `expires_at` = %d',
			$ip_key,
			$site_id,
			$category,
			$preset,
			$now,
			$expires,
			$category,
			$preset,
			$now,
			$expires
		);
	}

	/**
	 * Query the MEMORY active table for a live block record.
	 *
	 * @param string $ip_key  Normalised IP key.
	 * @param string $site_id Site scope identifier.
	 * @return true|false|'missing'  true = blocked, false = not found, 'missing' = table absent.
	 */
	private function queryActiveTable( $ip_key, $site_id ) {
		$active_tbl = $this->active_table;
		$now        = time();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT 1 FROM `{$active_tbl}` WHERE `ip_key` = %s AND `site_id` = %s AND `expires_at` > %d LIMIT 1",
			$ip_key,
			$site_id,
			$now
		);
		$suppress = $this->wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$value = $this->wpdb->get_var( $sql );
		$this->wpdb->suppress_errors( $suppress );

		if ( null !== $value ) {
			return true;
		}

		if ( $this->isTableMissing() ) {
			return 'missing';
		}

		return false;
	}

	/**
	 * Rebuild the MEMORY active table from the durable InnoDB blocks table.
	 *
	 * Only active (non-expired) rows are copied.
	 *
	 * @return void
	 */
	private function repopulateActiveTable() {
		$blocks_tbl = $this->blocks_table;
		$active_tbl = $this->active_table;
		$now        = time();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $this->wpdb->prepare(
			'INSERT IGNORE INTO `' . $active_tbl . '` (`ip_key`, `site_id`, `category`, `preset`, `blocked_at`, `expires_at`)' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			. ' SELECT `ip_key`, `site_id`, `category`, `preset`, `blocked_at`, `expires_at`'
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			. " FROM `{$blocks_tbl}` WHERE `expires_at` > %d",
			$now
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$this->wpdb->query( $sql );
	}

	/**
	 * Execute a write query against the blocks/active tables with lazy schema init.
	 *
	 * If the query fails because either blocks table is missing and we haven't
	 * tried creating them yet, both tables are created and the query is retried.
	 *
	 * @param string $sql Prepared SQL string.
	 * @return int|false Number of affected rows, or false on failure.
	 */
	private function execBlocks( $sql ) {
		$suppress = $this->wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->wpdb->query( $sql );
		if ( false === $result && ! $this->blocks_schema_init ) {
			if ( $this->isTableMissing() ) {
				$this->blocks_schema_init = true;
				$this->createBlocksTable();
				$this->createActiveTable();
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$result = $this->wpdb->query( $sql );
			} elseif ( $this->isTableFull() && false !== strpos( $sql, $this->active_table ) ) {
				// MEMORY engine: DELETE does not reclaim space — only
				// DROP + CREATE frees the memory (see MySQL docs).
				// Only rebuild when the MEMORY active table is full —
				// an InnoDB "table full" (disk) is not recoverable here.
				$this->blocks_schema_init = true;
				StorageEventBuffer::record(
					'MEMORY table full: imunify_bot_blocks_active — rebuilt from InnoDB',
					'bot_table_full_blocks_active',
					array( 'bot_storage_table_full', 'imunify_bot_blocks_active' )
				);
				$active_tbl = $this->active_table;
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->wpdb->query( "DROP TABLE IF EXISTS `{$active_tbl}`" );
				$this->createActiveTable();
				$this->repopulateActiveTable();
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$result = $this->wpdb->query( $sql );
			}
		}
		$this->wpdb->suppress_errors( $suppress );
		return $result;
	}

	/**
	 * Execute a write query against the violations table with lazy schema init.
	 *
	 * @param string $sql Prepared SQL string.
	 * @return int|false Number of affected rows, or false on failure.
	 */
	private function execViolations( $sql ) {
		$suppress = $this->wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->wpdb->query( $sql );
		if ( false === $result && ! $this->violations_schema_init ) {
			if ( $this->isTableMissing() ) {
				$this->violations_schema_init = true;
				$this->createViolationsTable();
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$result = $this->wpdb->query( $sql );
			} elseif ( $this->isTableFull() ) {
				// InnoDB table full means disk/tablespace exhaustion —
				// not recoverable from the plugin. Record for Sentry so
				// ops can act; stop retrying this request.
				$this->violations_schema_init = true;
				StorageEventBuffer::record(
					'Violations table full (InnoDB) — violation tracking disabled until space is freed',
					'bot_violations_table_full',
					array( 'bot_storage_violations_full' ),
					array( 'table' => $this->violations_table )
				);
			}
		}
		$this->wpdb->suppress_errors( $suppress );
		return $result;
	}

	/**
	 * Create the durable InnoDB blocks table if it does not exist.
	 *
	 * All columns are ASCII-only (IPs, hex site-id, internal category/preset
	 * identifiers). ASCII charset avoids the 4-byte-per-char overhead of
	 * utf8mb4, which matters for the MEMORY-engine active table that shares
	 * the same schema.
	 *
	 * @return void
	 */
	private function createBlocksTable() {
		$table = $this->blocks_table;
		$sql   = 'CREATE TABLE IF NOT EXISTS `' . $table . '` ('
			. ' `ip_key`     VARCHAR(191) NOT NULL,'
			. ' `site_id`    CHAR(16) NOT NULL,'
			. ' `category`   VARCHAR(64) NOT NULL,'
			. ' `preset`     VARCHAR(32) NOT NULL,'
			. ' `blocked_at` BIGINT UNSIGNED NOT NULL,'
			. ' `expires_at` BIGINT UNSIGNED NOT NULL,'
			. ' PRIMARY KEY (`site_id`, `ip_key`),'
			. ' KEY `idx_expires` (`expires_at`)'
			. ') ENGINE=InnoDB DEFAULT CHARSET=ascii';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->wpdb->query( $sql );
		if ( false === $result ) {
			StorageEventBuffer::record(
				'Failed to create InnoDB table: ' . $table,
				'bot_create_table_failed_blocks',
				array( 'bot_storage_create_failed', 'imunify_bot_blocks' ),
				array( 'table' => $table )
			);
		}
	}

	/**
	 * Create the MEMORY active-blocks table if it does not exist.
	 *
	 * @return void
	 */
	private function createActiveTable() {
		$table = $this->active_table;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$this->wpdb->query( 'SET max_heap_table_size = ' . self::MAX_HEAP_TABLE_SIZE );
		$sql = 'CREATE TABLE IF NOT EXISTS `' . $table . '` ('
			. ' `ip_key`     VARCHAR(191) NOT NULL,'
			. ' `site_id`    CHAR(16) NOT NULL,'
			. ' `category`   VARCHAR(64) NOT NULL,'
			. ' `preset`     VARCHAR(32) NOT NULL,'
			. ' `blocked_at` BIGINT UNSIGNED NOT NULL,'
			. ' `expires_at` BIGINT UNSIGNED NOT NULL,'
			. ' PRIMARY KEY (`site_id`, `ip_key`)'
			. ') ENGINE=MEMORY DEFAULT CHARSET=ascii';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->wpdb->query( $sql );
		if ( false === $result ) {
			StorageEventBuffer::record(
				'Failed to create MEMORY table: ' . $table,
				'bot_create_table_failed_blocks_active',
				array( 'bot_storage_create_failed', 'imunify_bot_blocks_active' ),
				array( 'table' => $table )
			);
		}
	}

	/**
	 * Create the durable InnoDB violations table if it does not exist.
	 *
	 * @return void
	 */
	private function createViolationsTable() {
		$table = $this->violations_table;
		$sql   = 'CREATE TABLE IF NOT EXISTS `' . $table . '` ('
			. ' `viol_key`   VARCHAR(191) NOT NULL,'
			. ' `counter`    INT UNSIGNED NOT NULL DEFAULT 0,'
			. ' `expires_at` BIGINT UNSIGNED NOT NULL,'
			. ' PRIMARY KEY (`viol_key`),'
			. ' KEY `idx_expires` (`expires_at`)'
			. ') ENGINE=InnoDB DEFAULT CHARSET=ascii';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->wpdb->query( $sql );
		if ( false === $result ) {
			StorageEventBuffer::record(
				'Failed to create InnoDB table: ' . $table,
				'bot_create_table_failed_violations',
				array( 'bot_storage_create_failed', 'imunify_bot_violations' ),
				array( 'table' => $table )
			);
		}
	}
}
