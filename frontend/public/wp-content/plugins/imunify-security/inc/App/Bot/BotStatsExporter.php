<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Daily bot-traffic export: aggregate the previous full calendar day from the
 * durable hourly tables, write it as a single file for the Imunify agent to
 * pick up, cap the on-disk backlog, and prune the local tables.
 *
 * File layout mirrors the incident export (see IncidentRecorder): a
 * `<?php __halt_compiler();` guard line, then one record per line as
 * `#` + base64(wp_json_encode(record)). The first record is `meta`; each
 * remaining line is one `rollup` record per (category, bot) — humans included,
 * sharing the rollup schema so the agent parses every line the same way.
 *
 * Filename is `bot-stats/YYYY-MM-DD.php`, where the date is the exported
 * (previous) day. Unlike the incident export the whole day is written at once,
 * so this uses AtomicFileWriter::write() (temp file + rename) rather than an
 * incremental append.
 *
 * Every path is fail-open: aggregation or write errors are suppressed so the
 * site and dashboard are never affected, and each terminal failure is buffered
 * to Sentry via StorageEventBuffer. A failed hourly read aborts the run rather
 * than exporting the day as zero-traffic, so the rows survive for a retry.
 * The export is best-effort — it only ever
 * targets the immediately previous day and never retries a missed one, so a day
 * whose single run fails, or on which WP-Cron never fires, is not recovered and
 * is pruned by the next day's retention floor.
 *
 * @since 4.1.0
 */
class BotStatsExporter {

	/**
	 * Export directory relative to wp-content.
	 */
	const EXPORT_SUBDIR = 'imunify-security/bot-stats';

	/**
	 * Export record schema version, carried in the meta record.
	 */
	const SCHEMA_VERSION = 1;

	/**
	 * Maximum export files retained on disk. When a new file is written the
	 * oldest beyond this count are dropped — a backstop against an agent that
	 * never consumes.
	 */
	const MAX_FILES = 3;

	/**
	 * Longest version string carried in the meta record. Bounds the record for a
	 * version that did not come from a release build — the plugin version is a
	 * constant in a file the site can edit — so one odd install cannot emit an
	 * outsized export line.
	 */
	const MAX_VERSION_LENGTH = 64;

	/**
	 * Absolute path to wp-content.
	 *
	 * @var string
	 */
	private $wpContentDir;

	/**
	 * Hourly rollup storage, or null when no DB handle is available.
	 *
	 * @var HourlyStatsStorage|null
	 */
	private $hourly;

	/**
	 * Per-IP hourly storage, or null when no DB handle is available.
	 *
	 * @var HourlyIpStatsStorage|null
	 */
	private $ipStats;

	/**
	 * Bind the exporter to wp-content and its storage collaborators.
	 *
	 * @param string                    $wp_content_dir Absolute path to wp-content.
	 * @param HourlyStatsStorage|null   $hourly         Rollup storage (nullable for fail-open / tests).
	 * @param HourlyIpStatsStorage|null $ip_stats       Per-IP storage (nullable for fail-open / tests).
	 */
	public function __construct( $wp_content_dir, $hourly, $ip_stats ) {
		$this->wpContentDir = (string) $wp_content_dir;
		$this->hourly       = $hourly;
		$this->ipStats      = $ip_stats;
	}

	/**
	 * Build the exporter over the global $wpdb. Returns null when no DB handle
	 * is available so the caller can no-op.
	 *
	 * @param string $wp_content_dir Absolute path to wp-content.
	 * @return self|null
	 */
	public static function forGlobalWpdb( $wp_content_dir ) {
		$hourly  = HourlyStatsStorage::forGlobalWpdb();
		$ipStats = HourlyIpStatsStorage::forGlobalWpdb();
		if ( null === $hourly || null === $ipStats ) {
			return null;
		}
		return new self( $wp_content_dir, $hourly, $ipStats );
	}

	/**
	 * Aggregate the previous full calendar day, write its export file, cap the
	 * backlog, and prune the local tables. Fail-open throughout.
	 *
	 * @param int|null $now Unix timestamp; defaults to time(). Injectable for tests.
	 * @return bool Whether an export file was written.
	 */
	public function export( $now = null ) {
		if ( null === $this->hourly || null === $this->ipStats ) {
			return false;
		}
		// Fail-open on both PHP 5.6 (Exception only) and PHP 7+ (Throwable), so a
		// storage or filesystem failure never surfaces to the request or cron.
		if ( interface_exists( 'Throwable' ) ) {
			try {
				return $this->doExport( $now );
			} catch ( \Throwable $t ) {
				$this->recordFailure( $t );
				return false;
			}
		}
		try {
			return $this->doExport( $now );
		} catch ( \Exception $e ) {
			$this->recordFailure( $e );
			return false;
		}
	}

	/**
	 * Buffer a Sentry event for a terminal export failure. The rest of the
	 * storage layer reports its structural failures the same way, so a
	 * permanently-failing export leaves a trace rather than silently dropping a
	 * day (the export is best-effort and never retries a missed day).
	 *
	 * @param \Throwable|\Exception $error Swallowed failure.
	 * @return void
	 */
	private function recordFailure( $error ) {
		StorageEventBuffer::record(
			'Bot stats export failed: ' . $error->getMessage(),
			'bot_export_exception',
			array( 'bot_stats_export_failed', get_class( $error ) )
		);
	}

	/**
	 * The export body, wrapped by {@see export()}'s fail-open guard.
	 *
	 * @param int|null $now Unix timestamp; defaults to time().
	 * @return bool Whether an export file was written.
	 */
	private function doExport( $now ) {
		$now = null === $now ? time() : (int) $now;

		$today_midnight = $now - ( $now % DAY_IN_SECONDS );
		$prev_day_start = $today_midnight - DAY_IN_SECONDS;
		$day            = gmdate( 'Y-m-d', $prev_day_start );
		$path           = $this->exportDir() . '/' . $day . '.php';

		// The written file is the idempotency marker: a second run the same UTC
		// day would re-read tables pruneLocal() has since trimmed and overwrite
		// the file with a short day, so bail once it exists.
		if ( is_file( $path ) ) {
			return true;
		}

		$first_bucket = HourlyStatsStorage::hourBucket( $prev_day_start );
		$last_bucket  = $first_bucket + 23;

		$hourly_rows = $this->hourly->fetchSince( $first_bucket );
		$ip_rows     = $this->ipStats->fetchSince( $first_bucket );

		// A failed read is null, an empty day is an empty array. Publishing the
		// former would assert zero traffic for a day that had some, and the prune
		// below would then destroy the rows that prove otherwise — so bail and
		// leave the tables for the next run.
		if ( null === $hourly_rows || null === $ip_rows ) {
			StorageEventBuffer::record(
				'Bot stats export: hourly read failed, day not exported',
				'bot_export_read_failed',
				array( 'bot_stats_export_failed', 'read' ),
				array( 'day' => $day )
			);
			return false;
		}

		$aggregator = new DailyBotAggregator( $hourly_rows, $ip_rows, $first_bucket, $last_bucket );
		$body       = $this->buildFileBody( $aggregator->rollups(), $day );

		if ( ! $this->ensureDirectory() ) {
			StorageEventBuffer::record(
				'Bot stats export: could not create or protect the export directory',
				'bot_export_dir_failed',
				array( 'bot_stats_export_failed', 'directory' ),
				array( 'dir' => $this->exportDir() )
			);
			return false;
		}

		$written = AtomicFileWriter::write( $path, $body, 0600 );
		if ( ! $written ) {
			StorageEventBuffer::record(
				'Bot stats export: atomic write failed',
				'bot_export_write_failed',
				array( 'bot_stats_export_failed', 'write' ),
				array( 'path' => $path )
			);
			return false;
		}

		$this->enforceBacklogCap();
		$this->pruneLocal( $now );

		return true;
	}

	/**
	 * Serialise the export records into the on-disk file body: guard line, then
	 * the meta record, then one line per rollup, each `#` + base64(json).
	 *
	 * @param array  $rollups Rollup records from DailyBotAggregator::rollups().
	 * @param string $day     Exported day, gmdate('Y-m-d').
	 * @return string
	 */
	public function buildFileBody( $rollups, $day ) {
		// An unknown version is an empty string, never a missing key — the
		// agent-side parser relies on a stable record shape.
		$meta = array(
			'type'                  => 'meta',
			'day'                   => $day,
			'schema_version'        => self::SCHEMA_VERSION,
			'plugin_version'        => $this->boundedVersion( defined( 'IMUNIFY_SECURITY_VERSION' ) ? IMUNIFY_SECURITY_VERSION : '' ),
			'crawler_intel_version' => $this->boundedVersion( SignatureRefresher::intelVersion() ),
		);

		$body  = "<?php __halt_compiler();\n";
		$body .= $this->encodeRecord( $meta );
		foreach ( $rollups as $rollup ) {
			$body .= $this->encodeRecord( $rollup );
		}
		return $body;
	}

	/**
	 * A version string for the meta record, or '' when it exceeds
	 * MAX_VERSION_LENGTH. An over-long value is dropped rather than truncated —
	 * '' already means "unknown" to the consumer, while a shortened version would
	 * read as a real one and silently skew a split by version.
	 *
	 * @param string $version Raw version value.
	 * @return string
	 */
	private function boundedVersion( $version ) {
		$version = (string) $version;
		return strlen( $version ) <= self::MAX_VERSION_LENGTH ? $version : '';
	}

	/**
	 * Encode a single record as its on-disk line: `#` + base64(json) + newline.
	 * Returns an empty string when the record fails JSON encoding — wp_json_encode
	 * signals that by returning false rather than throwing, so the caller's
	 * fail-open guard would miss it and emit a bare `#` line otherwise.
	 *
	 * @param array $record Record to encode.
	 * @return string
	 */
	private function encodeRecord( $record ) {
		$json = wp_json_encode( $record );
		if ( false === $json ) {
			return '';
		}
		return '#' . base64_encode( $json ) . "\n";
	}

	/**
	 * Ensure the export directory exists and carries the same listing-protection
	 * trio as the incident export.
	 *
	 * @return bool
	 */
	private function ensureDirectory() {
		$dir = $this->exportDir();
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		AtomicFileWriter::ensureDirectoryProtection( $dir );
		// Owner-only: the export holds per-site top-IP telemetry and the agent
		// reads it as root, so no group/other access is needed. Also repairs the
		// mode on a directory created by an older build.
		// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@chmod( $dir, 0700 );
		return true;
	}

	/**
	 * Drop the oldest export files beyond MAX_FILES. Only `YYYY-MM-DD.php` files
	 * are considered — the protection index.php / index.html are never counted
	 * or deleted.
	 *
	 * @return void
	 */
	private function enforceBacklogCap() {
		$files = glob( $this->exportDir() . '/*.php' );
		if ( ! is_array( $files ) ) {
			return;
		}
		$dated = array();
		foreach ( $files as $file ) {
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}\.php$/', basename( $file ) ) ) {
				$dated[] = $file;
			}
		}
		if ( count( $dated ) <= self::MAX_FILES ) {
			return;
		}
		// Ascending by name — Y-m-d sorts chronologically — so the leading
		// slice is the oldest.
		sort( $dated );
		$excess = array_slice( $dated, 0, count( $dated ) - self::MAX_FILES );
		foreach ( $excess as $file ) {
			@unlink( $file );
		}
	}

	/**
	 * Prune the local hourly tables back to the tight 24h + current-hour window
	 * now that the previous day has been exported.
	 *
	 * @param int $now Unix timestamp.
	 * @return void
	 */
	private function pruneLocal( $now ) {
		$oldest_kept = HourlyStatsStorage::hourBucket( $now - BotLifecycle::STATS_RETENTION_SECONDS );
		$this->hourly->prune( $oldest_kept );
		$this->ipStats->prune( $oldest_kept );
		$this->ipStats->trim();
	}

	/**
	 * Absolute export directory path.
	 *
	 * @return string
	 */
	private function exportDir() {
		return rtrim( $this->wpContentDir, '/' ) . '/' . self::EXPORT_SUBDIR;
	}
}
