<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Per-IP hourly detail for the bot-traffic drill-down, backed by a durable
 * InnoDB table one step below {@see HourlyStatsStorage}.
 *
 * One row per (hour_bucket, category, bot, ip). Non-human traffic only —
 * humans never get a per-IP row (they are a single aggregate in the rollup).
 * The IP is stored in `inet_pton()` binary form (IPv4 or IPv6). `verdict`
 * holds the most severe action seen for that IP in the hour; the upsert raises
 * it (block > rate_limit > allow) and never lowers it.
 *
 * The table is bounded on shared hosting by capping each
 * (hour_bucket, category, bot) group at {@see IP_CAP} distinct IPs: a new IP
 * is not inserted once the group is full (the request is still counted in the
 * rollup), and {@see trim()} sweeps any overflow that slipped in under a race.
 *
 * Every method is fail-open, mirroring {@see HourlyStatsStorage}: schema is
 * created lazily on first write and any DB error degrades to a no-op / empty
 * result rather than surfacing to the request.
 *
 * @since 4.1.0
 */
class HourlyIpStatsStorage {

	use DbErrorDetection;

	const CATEGORY_MAX = 24;
	const BOT_MAX      = 32;
	const VERDICT_MAX  = 12;

	/**
	 * Maximum distinct IPs retained per (hour_bucket, category, bot) group.
	 */
	const IP_CAP = 50;

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
	 * Groups already observed at capacity this request, keyed by
	 * "hour|category|bot" — lets a new IP skip the group-count query once the
	 * group is known full (matters under a scrape hammering one bot).
	 *
	 * @var array<string,bool>
	 */
	private $full_groups = array();

	/**
	 * Bind the storage to a WordPress database handle.
	 *
	 * @param object $wpdb Connected WordPress $wpdb instance.
	 */
	public function __construct( $wpdb ) {
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'imunify_bot_hourly_ip';
	}

	/**
	 * Build the per-IP storage over the global $wpdb, or null when no DB handle
	 * is available. The single authoritative constructor (see the twin on
	 * {@see HourlyStatsStorage}).
	 *
	 * @return self|null
	 */
	public static function forGlobalWpdb() {
		global $wpdb;
		return isset( $wpdb ) ? new self( $wpdb ) : null;
	}

	/**
	 * Record one non-human request against its (hour, category, bot, IP) row.
	 *
	 * Bumps an existing IP's count (raising its verdict) in a single UPDATE;
	 * for a new IP, inserts only while the group is below {@see IP_CAP}.
	 *
	 * @param string   $category One of the {@see Category} constants (never human).
	 * @param string   $bot      Matched signature slug, or '' when unnamed.
	 * @param string   $ip       Client IP in textual form.
	 * @param string   $verdict  One of the {@see RateLimitDecision} ACTION_* values.
	 * @param int|null $now      Unix timestamp; defaults to time(). Injectable for tests.
	 * @return void
	 */
	public function record( $category, $bot, $ip, $verdict, $now = null ) {
		$this->guardVoid(
			function () use ( $category, $bot, $ip, $verdict, $now ) {
				$category = (string) $category;
				if ( '' === $category ) {
					return;
				}
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- inet_pton warns on garbage; we want false and a skip.
				$ip_bin = @inet_pton( (string) $ip );
				if ( false === $ip_bin ) {
					return;
				}
				$category = substr( $category, 0, self::CATEGORY_MAX );
				$bot      = substr( (string) $bot, 0, self::BOT_MAX );
				$verdict  = substr( (string) $verdict, 0, self::VERDICT_MAX );

				$hour_bucket = HourlyStatsStorage::hourBucket( null === $now ? time() : (int) $now );

				if ( $this->bumpExisting( $hour_bucket, $category, $bot, $ip_bin, $verdict ) > 0 ) {
					return;
				}
				$group_key = $hour_bucket . '|' . $category . '|' . $bot;
				if ( isset( $this->full_groups[ $group_key ] ) ) {
					return;
				}
				if ( $this->groupCount( $hour_bucket, $category, $bot ) >= self::IP_CAP ) {
					$this->full_groups[ $group_key ] = true;
					return;
				}
				$this->insertNew( $hour_bucket, $category, $bot, $ip_bin, $verdict );
			}
		);
	}

	/**
	 * Fetch every per-IP row at or after $oldest_hour_bucket for aggregation.
	 * `ip` is returned in binary (inet_pton) form. Fail-open: null on error,
	 * which callers must keep distinct from an empty range (see
	 * {@see HourlyStatsStorage::fetchSince()}).
	 *
	 * @param int      $oldest_hour_bucket Inclusive lower bound on hour_bucket.
	 * @param int|null $newest_hour_bucket Inclusive upper bound; defaults to the
	 *                                     current hour so a clock-skewed future
	 *                                     row cannot skew the drill-down.
	 * @return array|null List of associative row arrays, or null on read failure.
	 */
	public function fetchSince( $oldest_hour_bucket, $newest_hour_bucket = null ) {
		return $this->guard(
			function () use ( $oldest_hour_bucket, $newest_hour_bucket ) {
				$table  = $this->table;
				$newest = null === $newest_hour_bucket ? HourlyStatsStorage::hourBucket( time() ) : (int) $newest_hour_bucket;
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$sql = $this->wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT `hour_bucket`,`category`,`bot`,`ip`,`verdict`,`req_count` FROM `{$table}` WHERE `hour_bucket` >= %d AND `hour_bucket` <= %d",
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
	 * Delete per-IP rows older than the retention cutoff.
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
				$this->runSuppressed( $sql );
			}
		);
	}

	/**
	 * Trim every (hour_bucket, category, bot) group back to the top
	 * {@see IP_CAP} IPs by req_count — a backstop for rows that slipped past
	 * the insert-time guard under a concurrent race.
	 *
	 * Portable to MySQL 5.6 / MariaDB 10.0 (no window functions): a correlated
	 * subquery counts how many rows in the same group outrank each row, and any
	 * row with at least IP_CAP betters is dropped. Groups are tiny (~cap rows),
	 * so the O(n^2) self-join is negligible. The extra SELECT wrapper is the
	 * MySQL idiom for deleting from a table also read in the subquery.
	 *
	 * @return void
	 */
	public function trim() {
		$this->guardVoid(
			function () {
				$table = $this->table;
				$cap   = (int) self::IP_CAP;
				// The only value is the integer cap (a %d placeholder); every
				// other token is a literal or the trusted internal table name,
				// repeated across the correlated self-join. phpcs cannot see
				// through the multi-line build, so disable its SQL sniffs here.
				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
				$sql = $this->wpdb->prepare(
					"DELETE FROM `{$table}` WHERE (`hour_bucket`,`category`,`bot`,`ip`) IN ("
					. ' SELECT `hour_bucket`,`category`,`bot`,`ip` FROM ('
					. " SELECT t1.`hour_bucket`,t1.`category`,t1.`bot`,t1.`ip` FROM `{$table}` t1"
					. " WHERE ( SELECT COUNT(*) FROM `{$table}` t2"
					. ' WHERE t2.`hour_bucket`=t1.`hour_bucket` AND t2.`category`=t1.`category` AND t2.`bot`=t1.`bot`'
					. ' AND ( t2.`req_count` > t1.`req_count`'
					. ' OR ( t2.`req_count` = t1.`req_count` AND t2.`ip` > t1.`ip` ) ) ) >= %d'
					. ' ) d )',
					$cap
				);
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
				$this->runSuppressed( $sql );
			}
		);
	}

	/**
	 * Remove every per-IP row (used when the site owner opts out of stats).
	 *
	 * @return void
	 */
	public function purge() {
		$this->guardVoid(
			function () {
				$table = $this->table;
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$this->runSuppressed( "DELETE FROM `{$table}`" );
			}
		);
	}

	/**
	 * Short identifier for telemetry.
	 *
	 * @return string
	 */
	public function name() {
		return 'hourly_ip';
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Bump an existing IP's count and raise its verdict. Returns affected rows
	 * (0 when the IP is new — req_count always changes, so a match is never 0).
	 *
	 * @param int    $hour_bucket Bucket.
	 * @param string $category    Category.
	 * @param string $bot         Bot slug.
	 * @param string $ip_bin      Binary IP.
	 * @param string $verdict     Incoming verdict.
	 * @return int|false
	 */
	private function bumpExisting( $hour_bucket, $category, $bot, $ip_bin, $verdict ) {
		$table = $this->table;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"UPDATE `{$table}` SET `req_count` = `req_count` + 1,"
			. " `verdict` = IF( FIELD(%s,'allow','rate_limit','block') > FIELD(`verdict`,'allow','rate_limit','block'), %s, `verdict` )"
			. ' WHERE `hour_bucket` = %d AND `category` = %s AND `bot` = %s AND `ip` = %s',
			$verdict,
			$verdict,
			$hour_bucket,
			$category,
			$bot,
			$ip_bin
		);
		return $this->exec( $sql );
	}

	/**
	 * Count rows already in a (hour_bucket, category, bot) group.
	 *
	 * @param int    $hour_bucket Bucket.
	 * @param string $category    Category.
	 * @param string $bot         Bot slug.
	 * @return int
	 */
	private function groupCount( $hour_bucket, $category, $bot ) {
		$table = $this->table;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT COUNT(*) FROM `{$table}` WHERE `hour_bucket` = %d AND `category` = %s AND `bot` = %s",
			$hour_bucket,
			$category,
			$bot
		);
		$suppress = $this->wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $this->wpdb->get_var( $sql );
		$this->wpdb->suppress_errors( $suppress );
		return null === $count ? 0 : (int) $count;
	}

	/**
	 * Insert a new IP row. The ON DUPLICATE KEY UPDATE bump is a race guard for
	 * two requests racing the same new IP past the count check.
	 *
	 * @param int    $hour_bucket Bucket.
	 * @param string $category    Category.
	 * @param string $bot         Bot slug.
	 * @param string $ip_bin      Binary IP.
	 * @param string $verdict     Incoming verdict.
	 * @return int|false
	 */
	private function insertNew( $hour_bucket, $category, $bot, $ip_bin, $verdict ) {
		$table = $this->table;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"INSERT INTO `{$table}` (`hour_bucket`,`category`,`bot`,`ip`,`verdict`,`req_count`)"
			. ' VALUES (%d, %s, %s, %s, %s, 1)'
			. ' ON DUPLICATE KEY UPDATE `req_count` = `req_count` + 1,'
			. " `verdict` = IF( FIELD(VALUES(`verdict`),'allow','rate_limit','block') > FIELD(`verdict`,'allow','rate_limit','block'), VALUES(`verdict`), `verdict` )",
			$hour_bucket,
			$category,
			$bot,
			$ip_bin,
			$verdict
		);
		return $this->exec( $sql );
	}

	/**
	 * Execute a write query, lazily creating the table on the first
	 * "doesn't exist" error and retrying once.
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
	 * Run a query with errors suppressed and no table auto-create (prune / trim
	 * / purge tolerate a missing table as a no-op).
	 *
	 * @param string $sql SQL to run.
	 * @return void
	 */
	private function runSuppressed( $sql ) {
		$suppress = $this->wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->query( $sql );
		$this->wpdb->suppress_errors( $suppress );
	}

	/**
	 * Create the durable InnoDB per-IP table if it does not exist.
	 *
	 * @return void
	 */
	private function createTable() {
		$table = $this->table;
		$sql   = 'CREATE TABLE IF NOT EXISTS `' . $table . '` ('
			. ' `hour_bucket` INT UNSIGNED NOT NULL,'
			. ' `category`    VARCHAR(24) NOT NULL,'
			. " `bot`         VARCHAR(32) NOT NULL DEFAULT '',"
			. ' `ip`          VARBINARY(16) NOT NULL,'
			. ' `verdict`     VARCHAR(12) NOT NULL,'
			. ' `req_count`   INT UNSIGNED NOT NULL DEFAULT 0,'
			. ' PRIMARY KEY (`hour_bucket`,`category`,`bot`,`ip`)'
			. ') ENGINE=InnoDB DEFAULT CHARSET=ascii';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$result = $this->wpdb->query( $sql );
		if ( false === $result ) {
			StorageEventBuffer::record(
				'Failed to create InnoDB table: ' . $this->table,
				'bot_create_table_failed_hourly_ip',
				array( 'bot_stats_create_failed', 'imunify_bot_hourly_ip' ),
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
