<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Activation / deactivation / uninstall for the bot-protection feature.
 *
 * WordPress-facing glue: builds the real collaborators and delegates the
 * install-then-loopback decision to BotShimManager. Also owns the bot
 * WP-Cron schedules and the teardown of every artefact this feature
 * persists — the mu-plugin shim, the rate-limit / block tables, and the
 * bot-owned options.
 *
 * @since 4.0.0
 */
class BotLifecycle {

	/**
	 * WP-Cron hook name for periodic storage cleanup.
	 */
	const CRON_HOOK_STORAGE_CLEANUP = 'imunify_security_bot_storage_cleanup';

	/**
	 * WP-Cron hook name for the daily bot-traffic export.
	 */
	const CRON_HOOK_DAILY_EXPORT = 'imunify_security_bot_daily_export';

	/**
	 * Custom WP-Cron recurrence: every 6 hours (4x/day).
	 */
	const CLEANUP_INTERVAL_SECONDS = 21600;

	/**
	 * Retention window for the hourly bot-traffic rollup, in seconds. Derived
	 * from the canonical hour count so it cannot drift from the read window.
	 * The daily export tightens the local tables back to this window right
	 * after it writes; between exports the 6-hourly cleanup keeps a wider floor
	 * (see {@see retentionFloorBucket()}).
	 */
	const STATS_RETENTION_SECONDS = HourlyStatsStorage::RETENTION_HOURS * 3600;

	/**
	 * Width of the post-midnight window the daily export's first run is jittered
	 * across (2 hours). Staggers export writes / prunes across sites so they do
	 * not all fire at 00:00 UTC, while keeping every run within a couple of
	 * hours of midnight so a complete previous day is always available.
	 */
	const EXPORT_JITTER_SECONDS = 7200;

	/**
	 * Activation entry point. Installs the mu-plugin shim only when the
	 * server-level ai_bot_protection gate is on.
	 *
	 * @return bool Whether the shim is installed and passed the safety test,
	 *              or true when the feature gate is off and nothing was installed.
	 */
	public static function activate() {
		$mu_dir = self::muPluginDir();
		if ( '' === $mu_dir ) {
			return false;
		}
		self::ensureStorageCleanupScheduled();
		SignatureRefresher::scheduleHooks();
		$home_url       = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
		$wp_content_dir = defined( 'WP_CONTENT_DIR' ) ? (string) WP_CONTENT_DIR : '';
		if ( OptOutFlag::load( $wp_content_dir )->isStatsEnabled() ) {
			self::ensureDailyExportScheduled();
		}
		$cfg = MuLoader::loadPluginConfig( $wp_content_dir );
		return self::shimManager( $mu_dir )->install( $home_url, $cfg );
	}

	/**
	 * Deactivation entry point. Clears cron and removes the shim — temporary
	 * artefacts only, never persisted data.
	 *
	 * @return bool
	 */
	public static function deactivate() {
		self::unscheduleStorageCleanup();
		$mu_dir = self::muPluginDir();
		if ( '' === $mu_dir ) {
			return true;
		}
		return self::shimManager( $mu_dir )->remove();
	}

	/**
	 * Remove every bot-protection artefact that survives deactivation: the
	 * rate-limit / block tables, the bot-owned options, and the shim.
	 *
	 * @return void
	 */
	public static function uninstall() {
		self::unscheduleStorageCleanup();
		self::dropBotTables();
		delete_option( SignatureRefresher::MIRROR_MD5_OPTION );
		delete_option( SignatureRefresher::MIRROR_GENERATED_AT_OPTION );
		delete_option( SignatureRefresher::MIRROR_NEXT_ATTEMPT_OPTION );
		delete_option( MuPluginSelfHealer::OPTION_NAME );
		delete_option( LoopbackStatus::OPTION_NAME );
		$mu_dir = self::muPluginDir();
		if ( '' !== $mu_dir ) {
			self::shimManager( $mu_dir )->remove();
		}
	}

	/**
	 * Register the cron_schedules filter and the cleanup action hooks.
	 *
	 * Called from the mu-plugin bootstrap (MuLoader) on every request so the
	 * custom interval and action handlers are always available to WP-Cron,
	 * even on requests where the main plugin file is not loaded yet.
	 *
	 * @param string|null $wp_content_dir Absolute path to wp-content, or null to use WP_CONTENT_DIR.
	 * @return void
	 */
	public static function registerCleanupHooks( $wp_content_dir = null ) {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}
		add_filter( 'cron_schedules', array( __CLASS__, 'addCleanupSchedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		// activate() runs only on a fresh activation, not on a plugin update, so
		// schedule from the per-request path too. wp_next_scheduled-guarded.
		self::ensureStorageCleanupScheduled();
		SignatureRefresher::scheduleHooks();
		add_action( self::CRON_HOOK_STORAGE_CLEANUP, array( __CLASS__, 'runStorageCleanup' ) );
		add_action( self::CRON_HOOK_DAILY_EXPORT, array( __CLASS__, 'runDailyExport' ) );
		add_action( SignatureRefresher::CRON_HOOK_REFRESH, array( __CLASS__, 'runSignatureRefresh' ) );

		// The daily export is scheduled from activate() and the stats toggle only;
		// self-heal it here so an upgraded site (which never calls activate) still
		// exports. Gate on the stats flag — the master switch activate() and the
		// toggle key off — so a stats-disabled site never gets a schedule.
		if ( null === $wp_content_dir ) {
			$wp_content_dir = defined( 'WP_CONTENT_DIR' ) ? (string) WP_CONTENT_DIR : '';
		}
		if ( '' !== $wp_content_dir && OptOutFlag::load( $wp_content_dir )->isStatsEnabled() ) {
			self::ensureDailyExportScheduled();
		}
	}

	/**
	 * Add the 'imunify_six_hours' recurrence to WP-Cron schedules.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function addCleanupSchedule( $schedules ) {
		if ( ! isset( $schedules['imunify_six_hours'] ) ) {
			$schedules['imunify_six_hours'] = array(
				'interval' => self::CLEANUP_INTERVAL_SECONDS,
				'display'  => 'Every 6 hours',
			);
		}
		return $schedules;
	}

	/**
	 * WP-Cron callback: run storage cleanup in a fail-safe wrapper.
	 *
	 * Accepts an optional $wp_content_dir to allow unit testing without
	 * relying on the WP_CONTENT_DIR constant.
	 *
	 * @param string|null $wp_content_dir Absolute path to wp-content, or null to use WP_CONTENT_DIR.
	 * @return void
	 */
	public static function runStorageCleanup( $wp_content_dir = null ) {
		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			return;
		}

		// Enforce retention on the durable rollup + per-IP tables before the
		// protection-gate short-circuits below. These are independent InnoDB
		// tables — not the MEMORY-engine pair the gates guard from being
		// recreated — so their retention must hold even while protection is off.
		// The per-IP table is additionally trimmed back to its per-group cap.
		// The floor keeps the whole previous calendar day so this 6-hourly sweep
		// leaves that day for the daily export to write (best-effort: a day whose
		// export fails or never fires is pruned the next day, not recovered). The
		// daily export tightens the tables back to 24h + current hour right after
		// it writes. Fail-open: neither blocks nor is blocked by the MEMORY cleanup.
		$oldest_kept = self::retentionFloorBucket( time() );
		$hourly      = HourlyStatsStorage::forGlobalWpdb();
		if ( null !== $hourly ) {
			$hourly->prune( $oldest_kept );
		}
		$hourly_ip = HourlyIpStatsStorage::forGlobalWpdb();
		if ( null !== $hourly_ip ) {
			$hourly_ip->prune( $oldest_kept );
			$hourly_ip->trim();
		}

		if ( null === $wp_content_dir ) {
			if ( ! defined( 'WP_CONTENT_DIR' ) ) {
				return;
			}
			$wp_content_dir = (string) WP_CONTENT_DIR;
		}
		// Skip when protection is off: cleanup() rebuilds the MEMORY counter table
		// via DROP+CREATE, so an ungated run recreates an empty table the instant
		// the gate closes. Skip rather than self-cancel like the refresh cron —
		// this event is scheduled only from activate(), so unscheduling it here
		// would strand it until the next reactivation.
		if ( ! MuLoader::loadPluginConfig( $wp_content_dir )->isAiBotProtectionEnabled() ) {
			return;
		}
		if ( ! OptOutFlag::load( $wp_content_dir )->isEnabled() ) {
			return;
		}
		try {
			$storage = DbStorageFactory::detect( $wpdb );
			$storage['block']->cleanup();
			$storage['counter']->cleanup();
		} catch ( \Exception $e ) {
			$transient_key = 'imunify_security_error_bot_cleanup_failed';
			if ( function_exists( 'get_transient' ) && ! get_transient( $transient_key ) ) {
				if ( function_exists( 'set_transient' ) ) {
					set_transient( $transient_key, true, 3600 );
				}
				do_action(
					'imunify_security_set_error',
					E_WARNING,
					'Bot storage cleanup failed: ' . $e->getMessage(),
					__FILE__,
					__LINE__,
					array(
						'fingerprint' => array( 'bot_storage_cleanup_failed', get_class( $e ) ),
					)
				);
			}
		}
	}

	/**
	 * WP-Cron callback: pull the latest bot-data overlay from the mirror.
	 *
	 * Accepts an optional $wp_content_dir to allow unit testing without
	 * relying on the WP_CONTENT_DIR constant.
	 *
	 * @param string|null $wp_content_dir Absolute path to wp-content, or null to use WP_CONTENT_DIR.
	 * @return void
	 */
	public static function runSignatureRefresh( $wp_content_dir = null ) {
		if ( null === $wp_content_dir ) {
			if ( ! defined( 'WP_CONTENT_DIR' ) ) {
				return;
			}
			$wp_content_dir = (string) WP_CONTENT_DIR;
		}
		if ( ! MuLoader::loadPluginConfig( $wp_content_dir )->isAiBotProtectionEnabled() ) {
			self::unscheduleRefresh();
			return;
		}
		if ( ! OptOutFlag::load( $wp_content_dir )->isEnabled() ) {
			self::unscheduleRefresh();
			return;
		}
		$overlay_dir = rtrim( $wp_content_dir, '/' ) . '/imunify-security/bot-data';
		$refresher   = new SignatureRefresher(
			$overlay_dir,
			new WpHttpClient( MirrorDownloader::REQUEST_TIMEOUT_SECONDS )
		);
		$refresher->refreshFromMirror();
	}

	/**
	 * WP-Cron callback: export the previous full day's bot traffic.
	 *
	 * Self-cancels when the site owner opted out of stats capture — the flag that
	 * governs the schedule — so a schedule that outlives an opt-out made outside
	 * the widget stops firing. With protection or the site opt-out off it skips
	 * the run but leaves the schedule in place (the stats-gated self-heal would
	 * re-add it anyway), knowingly forgoing a previous day that has not been
	 * exported yet. Accepts an optional $wp_content_dir for unit testing without
	 * the WP_CONTENT_DIR constant.
	 *
	 * @param string|null $wp_content_dir Absolute path to wp-content, or null to use WP_CONTENT_DIR.
	 * @return void
	 */
	public static function runDailyExport( $wp_content_dir = null ) {
		if ( null === $wp_content_dir ) {
			if ( ! defined( 'WP_CONTENT_DIR' ) ) {
				return;
			}
			$wp_content_dir = (string) WP_CONTENT_DIR;
		}
		$opt_out = OptOutFlag::load( $wp_content_dir );
		// Stats capture is the sole governor of the export schedule — activate(),
		// the widget toggle, and the per-request self-heal all key off it — so
		// self-cancel only when it is off. Cancelling on protection/opt-out too
		// would fight the stats-gated self-heal, which re-adds the event every
		// request (schedule/cancel thrash). With protection or the site opt-out
		// off the run is skipped and the schedule left in place. That knowingly
		// forgoes a previous day still sitting unexported in the tables: capture
		// has stopped, so exporting anyway would write a meta-only file every day
		// from then on, and the retention floor drops that day like any other
		// unexported one.
		if ( ! $opt_out->isStatsEnabled() ) {
			self::unscheduleDailyExport();
			return;
		}
		if ( ! MuLoader::loadPluginConfig( $wp_content_dir )->isAiBotProtectionEnabled()
			|| ! $opt_out->isEnabled() ) {
			return;
		}
		$exporter = BotStatsExporter::forGlobalWpdb( $wp_content_dir );
		if ( null !== $exporter ) {
			$exporter->export();
		}
	}

	/**
	 * Hour-bucket retention floor for the 6-hourly durable-table sweep: the
	 * start of the previous calendar day (UTC). Keeping the whole previous day
	 * lets the daily export find a complete day to write. This holds only for the
	 * best-effort case where each day's run fires and succeeds: the floor advances
	 * unconditionally every day while the export only ever targets the immediately
	 * previous day, so a day whose run fails, never fires, or is skipped because
	 * protection went off is pruned here the next day and not recovered. Anything
	 * older than the floor is fair game to prune.
	 *
	 * @param int $now Unix timestamp.
	 * @return int
	 */
	public static function retentionFloorBucket( $now ) {
		$now            = (int) $now;
		$today_midnight = $now - ( $now % DAY_IN_SECONDS );
		return HourlyStatsStorage::hourBucket( $today_midnight - DAY_IN_SECONDS );
	}

	/**
	 * Schedule the daily export cron if not already scheduled. The first run is
	 * anchored to the next UTC midnight plus a per-site jitter offset in
	 * [0, EXPORT_JITTER_SECONDS); the built-in `daily` recurrence then preserves
	 * that per-site offset every day.
	 *
	 * @return void
	 */
	public static function ensureDailyExportScheduled() {
		if ( ! function_exists( 'wp_next_scheduled' ) ) {
			return;
		}
		if ( wp_next_scheduled( self::CRON_HOOK_DAILY_EXPORT ) ) {
			return;
		}
		$now           = time();
		$next_midnight = ( $now - ( $now % DAY_IN_SECONDS ) ) + DAY_IN_SECONDS;
		wp_schedule_event( $next_midnight + self::exportJitter(), 'daily', self::CRON_HOOK_DAILY_EXPORT );
	}

	/**
	 * Per-site post-midnight export offset in [0, EXPORT_JITTER_SECONDS).
	 *
	 * Derived from the site scope rather than an RNG: this runs from the
	 * mu-plugin self-heal, where wp_rand() does not exist yet (pluggable.php is
	 * loaded after plugins), so a random offset would collapse to a fleet-wide
	 * 00:00 UTC stampede on exactly the upgraded sites that only ever schedule
	 * via that path. Deterministic also means a site keeps its slot when it
	 * reschedules.
	 *
	 * @return int
	 */
	public static function exportJitter() {
		return self::jitterForScope( SiteScope::derive() );
	}

	/**
	 * Map a site scope to its export offset. Split out from {@see exportJitter()}
	 * so the spread across sites is testable without touching ABSPATH.
	 *
	 * @param string $scope Site scope identifier from SiteScope::derive().
	 * @return int
	 */
	public static function jitterForScope( $scope ) {
		// 8 hex chars keeps the value inside PHP_INT_MAX on 32-bit builds.
		// nosemgrep: php.lang.security.weak-crypto.weak-crypto -- spreads cron start times, not cryptography.
		return (int) ( hexdec( substr( md5( (string) $scope ), 0, 8 ) ) % self::EXPORT_JITTER_SECONDS );
	}

	/**
	 * Remove the daily export cron event. Called on the stats opt-out toggle,
	 * on teardown, and by the run-time self-cancel guard.
	 *
	 * @return void
	 */
	public static function unscheduleDailyExport() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK_DAILY_EXPORT );
		}
	}

	/**
	 * Schedule the cleanup cron event if not already scheduled.
	 *
	 * @return void
	 */
	private static function ensureStorageCleanupScheduled() {
		if ( ! function_exists( 'wp_next_scheduled' ) ) {
			return;
		}
		// Ensure the custom recurrence is registered before scheduling.
		// activate() runs from the main plugin hooks (not MuLoader),
		// so registerCleanupHooks() hasn't fired yet for this request.
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'cron_schedules', array( __CLASS__, 'addCleanupSchedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK_STORAGE_CLEANUP ) ) {
			wp_schedule_event( time(), 'imunify_six_hours', self::CRON_HOOK_STORAGE_CLEANUP );
		}
	}

	/**
	 * Remove the cleanup cron events.
	 *
	 * @return void
	 */
	private static function unscheduleStorageCleanup() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK_STORAGE_CLEANUP );
			wp_clear_scheduled_hook( self::CRON_HOOK_DAILY_EXPORT );
			wp_clear_scheduled_hook( SignatureRefresher::CRON_HOOK_REFRESH );
		}
	}

	/**
	 * Remove only the signature-refresh cron event.
	 *
	 * Called from runSignatureRefresh() when bot protection is disabled so
	 * the cron self-cancels rather than firing harmlessly every 6 hours.
	 *
	 * @return void
	 */
	private static function unscheduleRefresh() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( SignatureRefresher::CRON_HOOK_REFRESH );
		}
	}

	/**
	 * Drop all bot-protection database tables.
	 *
	 * Suppresses errors so a missing table or DB permission issue never
	 * prevents the rest of the uninstall from completing.
	 *
	 * @return void
	 */
	private static function dropBotTables() {
		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			return;
		}
		$suppress = $wpdb->suppress_errors( true );
		$prefix   = $wpdb->prefix;
		$tables   = array(
			$prefix . 'imunify_bot_rl',
			$prefix . 'imunify_bot_blocks',
			$prefix . 'imunify_bot_blocks_active',
			$prefix . 'imunify_bot_violations',
			$prefix . 'imunify_bot_hourly',
			$prefix . 'imunify_bot_hourly_ip',
		);
		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		}
		$wpdb->suppress_errors( $suppress );
	}

	/**
	 * Build the shim manager with its real collaborators.
	 *
	 * @param string $mu_dir Absolute path to wp-content/mu-plugins.
	 * @return BotShimManager
	 */
	private static function shimManager( $mu_dir ) {
		return new BotShimManager( new MuPluginInstaller( $mu_dir ), new LoopbackSafetyTest(), new LoopbackStatus() );
	}

	/**
	 * Resolve WPMU_PLUGIN_DIR, or '' when unavailable.
	 *
	 * @return string
	 */
	private static function muPluginDir() {
		return defined( 'WPMU_PLUGIN_DIR' ) && '' !== WPMU_PLUGIN_DIR
			? (string) WPMU_PLUGIN_DIR
			: '';
	}
}
