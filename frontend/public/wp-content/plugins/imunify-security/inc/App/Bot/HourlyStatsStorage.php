<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * IP-free hourly rollup of bot traffic, backed by a durable InnoDB table.
 *
 * One row per (hour_bucket, category, bot, verdict). Every recorded bot
 * request is a single atomic `INSERT ... ON DUPLICATE KEY UPDATE` — no
 * staging table, no flush, nothing lost on a MySQL restart. Row count is
 * bounded by category × bot × verdict cardinality (a few hundred/hour at
 * most), never by request volume.
 *
 * `peak_req_min` is maintained inline: each write bumps a running
 * current-minute counter (resetting it when the minute-of-hour changes) and
 * keeps the largest value seen. This yields "peaked at N/min" without any
 * per-request storage.
 *
 * Every method is fail-open: schema is created lazily on first write, and any
 * DB error (or unexpected throw) degrades to a no-op / empty result rather
 * than surfacing to the request. Retention and opt-out purging are the
 * caller's responsibility (see {@see prune()} / {@see purge()}).
 *
 * @since 4.1.0
 */
class HourlyStatsStorage {

	use DbErrorDetection;

	/**
	 * Column widths, mirrored from the table schema so oversized inputs are
	 * capped before they reach the DB rather than being silently truncated
	 * (or rejected in STRICT mode).
	 */
	const CATEGORY_MAX = 24;
	const BOT_MAX      = 32;
	const VERDICT_MAX  = 12;

	/**
	 * Canonical retention window (hours) for the rollup — the single source of
	 * truth the cron prune and the dashboard read window both derive from.
	 */
	const RETENTION_HOURS = 24;

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
	 * Prevents repeated CREATE TABLE attempts within one request.
	 *
	 * @var bool
	 */
	private $schema_init_attempted = false;

	/**
	 * Bind the storage to a WordPress database handle.
	 *
	 * @param object $wpdb Connected WordPress $wpdb instance.
	 */
	public function __construct( $wpdb ) {
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'imunify_bot_hourly';
	}

	/**
	 * Build the rollup storage over the global $wpdb, or null when no DB
	 * handle is available. The single authoritative constructor — callers
	 * (pipeline wiring, cron prune, dashboard read, opt-out purge) use this
	 * rather than re-deriving the "$wpdb present?" build at each site.
	 *
	 * @return self|null
	 */
	public static function forGlobalWpdb() {
		global $wpdb;
		return isset( $wpdb ) ? new self( $wpdb ) : null;
	}

	/**
	 * UTC epoch hour for a timestamp — the hour_bucket key. Single source of
	 * truth for the bucket definition shared with the reader and the cron prune.
	 *
	 * @param int $ts Unix timestamp.
	 * @return int
	 */
	public static function hourBucket( $ts ) {
		return (int) floor( (int) $ts / 3600 );
	}

	/**
	 * Record one bot request into its hourly bucket.
	 *
	 * @param string   $category One of the {@see Category} constants.
	 * @param string   $bot      Matched signature slug, or '' when unnamed.
	 * @param string   $verdict  One of the {@see RateLimitDecision} ACTION_* values.
	 * @param int|null $now      Unix timestamp; defaults to time(). Injectable for tests.
	 * @return void
	 */
	public function record( $category, $bot, $verdict, $now = null ) {
		$this->guardVoid(
			function () use ( $category, $bot, $verdict, $now ) {
				$category = (string) $category;
				if ( '' === $category ) {
					return;
				}
				$category = substr( $category, 0, self::CATEGORY_MAX );
				$bot      = substr( (string) $bot, 0, self::BOT_MAX );
				$verdict  = substr( (string) $verdict, 0, self::VERDICT_MAX );

				$ts          = null === $now ? time() : (int) $now;
				$hour_bucket = self::hourBucket( $ts );
				$cur_min     = (int) floor( $ts / 60 ) % 60;
				$table       = $this->table;

				// A single row-locked upsert keeps the current-minute counter and
				// the all-time peak in step. The assignment order is load-bearing
				// and must not be reshuffled: peak_req_min must be computed BEFORE
				// cur_min_cnt (so it reads the OLD count, not the incremented one),
				// and cur_min must be written LAST (so both the peak and the count
				// compare against the OLD minute). Both read OLD cur_min/cur_min_cnt.
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$sql = $this->wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"INSERT INTO `{$table}`"
					. ' (`hour_bucket`,`category`,`bot`,`verdict`,`req_count`,`cur_min`,`cur_min_cnt`,`peak_req_min`)'
					. ' VALUES (%d, %s, %s, %s, 1, %d, 1, 1)'
					. ' ON DUPLICATE KEY UPDATE'
					. ' `req_count` = `req_count` + 1,'
					. ' `peak_req_min` = GREATEST(`peak_req_min`, IF(`cur_min` = %d, `cur_min_cnt` + 1, 1)),'
					. ' `cur_min_cnt` = IF(`cur_min` = %d, `cur_min_cnt` + 1, 1),'
					. ' `cur_min` = %d',
					$hour_bucket,
					$category,
					$bot,
					$verdict,
					$cur_min,
					$cur_min,
					$cur_min,
					$cur_min
				);

				$this->exec( $sql );
			}
		);
	}

	/**
	 * Fetch every rollup row at or after $oldest_hour_bucket, newest data
	 * included. Rows are returned as associative arrays for the aggregation
	 * layer. Fail-open: any error yields null, which callers must keep distinct
	 * from the empty array a genuinely empty range returns — the daily export
	 * would otherwise publish a failed read as a zero-traffic day and prune the
	 * rows that prove otherwise.
	 *
	 * @param int      $oldest_hour_bucket Inclusive lower bound on hour_bucket.
	 * @param int|null $newest_hour_bucket Inclusive upper bound; defaults to the
	 *                                     current hour so a clock-skewed future
	 *                                     row cannot inflate the summed KPIs.
	 * @return array|null List of associative row arrays, or null on read failure.
	 */
	public function fetchSince( $oldest_hour_bucket, $newest_hour_bucket = null ) {
		return $this->guard(
			function () use ( $oldest_hour_bucket, $newest_hour_bucket ) {
				$table  = $this->table;
				$newest = null === $newest_hour_bucket ? self::hourBucket( time() ) : (int) $newest_hour_bucket;
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$sql = $this->wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT `hour_bucket`,`category`,`bot`,`verdict`,`req_count`,`cur_min`,`cur_min_cnt`,`peak_req_min` FROM `{$table}` WHERE `hour_bucket` >= %d AND `hour_bucket` <= %d",
					(int) $oldest_hour_bucket,
					$newest
				);
				$suppress = $this->wpdb->suppress_errors( true );
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$rows = $this->wpdb->get_results( $sql );
				$this->wpdb->suppress_errors( $suppress );
				if ( ! is_array( $rows ) ) {
					return null;
				}
				$out = array();
				foreach ( $rows as $row ) {
					$out[] = (array) $row;
				}
				return $out;
			},
			null
		);
	}

	/**
	 * The storage engine the rollup table is actually on (e.g. 'InnoDB'), or
	 * null when the table does not exist yet or the engine cannot be read.
	 *
	 * The dashboard reports this rather than assuming InnoDB: on a host whose
	 * sql_mode allows engine substitution, a CREATE that asked for InnoDB can
	 * be silently downgraded, and the label must not then claim InnoDB.
	 *
	 * @return string|null
	 */
	public function tableEngine() {
		return $this->guard(
			function () {
				$sql      = $this->wpdb->prepare(
					'SELECT `ENGINE` FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = %s',
					$this->table
				);
				$suppress = $this->wpdb->suppress_errors( true );
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$engine = $this->wpdb->get_var( $sql );
				$this->wpdb->suppress_errors( $suppress );
				return is_string( $engine ) && '' !== $engine ? $engine : null;
			},
			null
		);
	}

	/**
	 * Delete rollup rows older than the retention cutoff.
	 *
	 * @param int $min_hour_bucket_to_keep Rows with hour_bucket below this are removed.
	 * @return void
	 */
	public function prune( $min_hour_bucket_to_keep ) {
		$this->guardVoid(
			function () use ( $min_hour_bucket_to_keep ) {
				$table = $this->table;
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$sql = $this->wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"DELETE FROM `{$table}` WHERE `hour_bucket` < %d",
					(int) $min_hour_bucket_to_keep
				);
				$suppress = $this->wpdb->suppress_errors( true );
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$this->wpdb->query( $sql );
				$this->wpdb->suppress_errors( $suppress );
			}
		);
	}

	/**
	 * Remove every rollup row (used when the site owner opts out of stats).
	 *
	 * @return void
	 */
	public function purge() {
		$this->guardVoid(
			function () {
				$table    = $this->table;
				$suppress = $this->wpdb->suppress_errors( true );
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$this->wpdb->query( "DELETE FROM `{$table}`" );
				$this->wpdb->suppress_errors( $suppress );
			}
		);
	}

	/**
	 * Short identifier for telemetry.
	 *
	 * @return string
	 */
	public function name() {
		return 'hourly';
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Execute a write query, lazily creating the table on the first
	 * "doesn't exist" error and retrying the query once.
	 *
	 * @param string $sql Prepared SQL.
	 * @return int|false Affected rows, or false on failure.
	 */
	private function exec( $sql ) {
		$suppress = $this->wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->query( $sql );
		if ( false === $result && ! $this->schema_init_attempted && $this->isTableMissing() ) {
			$this->schema_init_attempted = true;
			$this->createTable();
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $this->wpdb->query( $sql );
		}
		$this->wpdb->suppress_errors( $suppress );
		return $result;
	}

	/**
	 * Create the durable InnoDB rollup table if it does not exist.
	 *
	 * ASCII charset: every stored value is an internal ASCII identifier
	 * (category enum, verdict enum, signature slug), so 1 byte/char is both
	 * sufficient and compact.
	 *
	 * @return void
	 */
	private function createTable() {
		$table = $this->table;
		$sql   = 'CREATE TABLE IF NOT EXISTS `' . $table . '` ('
			. ' `hour_bucket`  INT UNSIGNED NOT NULL,'
			. ' `category`     VARCHAR(24) NOT NULL,'
			. " `bot`          VARCHAR(32) NOT NULL DEFAULT '',"
			. ' `verdict`      VARCHAR(12) NOT NULL,'
			. ' `req_count`    INT UNSIGNED NOT NULL DEFAULT 0,'
			. ' `cur_min`      TINYINT UNSIGNED NOT NULL DEFAULT 0,'
			. ' `cur_min_cnt`  INT UNSIGNED NOT NULL DEFAULT 0,'
			. ' `peak_req_min` INT UNSIGNED NOT NULL DEFAULT 0,'
			. ' PRIMARY KEY (`hour_bucket`,`category`,`bot`,`verdict`)'
			. ') ENGINE=InnoDB DEFAULT CHARSET=ascii';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$result = $this->wpdb->query( $sql );
		if ( false === $result ) {
			StorageEventBuffer::record(
				'Failed to create InnoDB table: ' . $this->table,
				'bot_create_table_failed_hourly',
				array( 'bot_stats_create_failed', 'imunify_bot_hourly' ),
				array( 'table' => $this->table )
			);
		}
	}

	/**
	 * Run $fn under a fail-open wrapper, returning $default on any throw.
	 *
	 * @param callable $fn      Work to attempt.
	 * @param mixed    $default Value to return when $fn throws.
	 * @return mixed
	 */
	private function guard( $fn, $default ) {
		if ( interface_exists( 'Throwable' ) ) {
			try {
				return call_user_func( $fn );
			} catch ( \Throwable $t ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- fail-open telemetry.
				unset( $t );
				return $default;
			}
		}
		try {
			return call_user_func( $fn );
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- fail-open telemetry.
			unset( $e );
			return $default;
		}
	}

	/**
	 * Fail-open wrapper for callables with no return value (see {@see guard()}).
	 *
	 * @param callable $fn Work to attempt.
	 * @return void
	 */
	private function guardVoid( $fn ) {
		$this->guard( $fn, null );
	}
}
