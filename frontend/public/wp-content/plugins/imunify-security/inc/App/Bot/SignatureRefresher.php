<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 *
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fopen
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fwrite
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fread
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_fclose
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_flock
 * phpcs:disable WordPress.WP.AlternativeFunctions.rename_rename
 * phpcs:disable WordPress.WP.AlternativeFunctions.unlink_unlink
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
 * phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Wp-cron-driven refresher for bot-protection data.
 *
 * Polls the CloudLinux mirror (`MIRROR_BASE_URL`) on a 6-hour schedule
 * wired via `scheduleHooks()` / `BotLifecycle::activate()`. Fetches
 * `description.json` first (cheap MD5 check); only downloads `all.json`
 * when its MD5 differs from the locally-cached value. Both downloads go
 * through `MirrorDownloader`, which verifies and retries them, so nothing
 * here parses or caches a body the mirror did not fully deliver. Delegates
 * JSON→PHP bundle conversion to `BotDataConverter`, which writes files
 * atomically into the overlay directory
 * (`wp-content/imunify-security/bot-data/`). `BundledData` picks up overlay
 * files on the next classification call, transparently superseding the
 * snapshot shipped under `inc/App/Bot/data/`.
 *
 * Safety invariants:
 *   - Network failures and MD5 mismatches leave existing overlay files
 *     untouched (fail-open: never degrade on transient network error).
 *   - A POSIX advisory lock on `<overlay>/.refresh.lock` prevents two
 *     concurrently-firing wp-cron workers from racing each other.
 *   - Every run books its next attempt before touching the network, so a
 *     failing mirror costs one attempt per schedule tick even if wp-cron
 *     fires the hook on every page load.
 *
 * @since 4.0.0
 */
class SignatureRefresher {

	const CRON_HOOK_REFRESH          = 'imunify_security_bot_refresh';
	const LOCK_FILENAME              = '.refresh.lock';
	const MIRROR_BASE_URL            = 'https://files.imunify360.com/static/crawler-intel/v1';
	const MIRROR_MD5_OPTION          = 'imunify_security_bot_mirror_md5sum';
	const MIRROR_GENERATED_AT_OPTION = 'imunify_security_bot_mirror_generated_at';
	const MIRROR_NEXT_ATTEMPT_OPTION = 'imunify_security_bot_mirror_next_attempt';

	/**
	 * Shortest gap between two refresh attempts: the 6-hour schedule minus a
	 * 15-minute allowance, so a tick that fires slightly early still runs while
	 * a wp-cron that keeps re-firing the hook cannot turn every page load into
	 * a mirror request.
	 */
	const MIN_REFRESH_INTERVAL_SECONDS = 6 * 3600 - 900;

	const MIN_SIGNATURE_LENGTH = 4;
	const SIGNATURE_DENYLIST   = array(
		'mozilla',
		'chrome',
		'safari',
		'firefox',
		'edge',
		'opera',
		'msie',
		'trident',
		'applewebkit',
		'gecko',
		'webkit',
	);

	/**
	 * Absolute path to the overlay root.
	 *
	 * @var string
	 */
	private $overlay_dir;

	/**
	 * Injected HTTP client, handed to a MirrorDownloader on each refresh.
	 *
	 * @var HttpClient
	 */
	private $http;

	/**
	 * Downloader used for both mirror objects; built from $http when not given.
	 *
	 * @var MirrorDownloader|null
	 */
	private $downloader;

	/**
	 * Build a refresher bound to an overlay directory.
	 *
	 * @param string                $overlay_dir Absolute path to the overlay root.
	 * @param HttpClient            $http        HTTP client (WpHttpClient in production, fake in tests).
	 * @param MirrorDownloader|null $downloader  Downloader to use; built from $http when null.
	 */
	public function __construct( $overlay_dir, $http, $downloader = null ) {
		$this->overlay_dir = rtrim( (string) $overlay_dir, '/' );
		$this->http        = $http;
		$this->downloader  = $downloader;
	}

	/**
	 * Reject signature tokens that are too short or match common browser substrings.
	 *
	 * Public so `bin/update-bot-data.php` can apply the same rule when
	 * generating bundled data files, keeping bundled and cron-refreshed
	 * overlays byte-for-byte comparable.
	 *
	 * @param array $items Raw signature tokens.
	 * @return array Sanitized tokens, re-indexed.
	 */
	public static function sanitizeSignatures( $items ) {
		$out = array();
		foreach ( $items as $token ) {
			if ( strlen( $token ) < self::MIN_SIGNATURE_LENGTH ) {
				continue;
			}
			$lower = strtolower( $token );
			if ( in_array( $lower, self::SIGNATURE_DENYLIST, true ) ) {
				continue;
			}
			$out[] = $token;
		}
		return $out;
	}

	/**
	 * Bucket a sorted list of CIDRs into the shape CidrMatcher::matchesAnyBucketed() expects.
	 *
	 * Public so bin/update-bot-data.php can produce the same bucketed structure,
	 * keeping dev-bundled and cron-refreshed overlays byte-identical.
	 *
	 * @param array $sorted_ranges Sorted CIDR strings.
	 * @return array { 'ranges_by_octet' => int[] => string[], 'ranges_broad' => string[] }
	 */
	public static function bucketRanges( $sorted_ranges ) {
		$by_octet = array();
		$broad    = array();
		foreach ( $sorted_ranges as $cidr ) {
			if ( ! is_string( $cidr ) ) {
				continue;
			}
			$slash = strpos( $cidr, '/' );
			if ( false === $slash ) {
				continue;
			}
			$network    = substr( $cidr, 0, $slash );
			$prefix_str = substr( $cidr, $slash + 1 );
			if ( '' === $prefix_str || ! ctype_digit( $prefix_str ) ) {
				continue;
			}
			$prefix = (int) $prefix_str;
			$bin    = @inet_pton( $network );
			if ( false === $bin ) {
				continue;
			}
			if ( 16 === strlen( $bin ) && "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff" === substr( $bin, 0, 12 ) ) {
				$bin     = substr( $bin, 12 );
				$prefix -= 96;
			}
			if ( $prefix < 8 ) {
				$broad[] = $cidr;
			} else {
				$octet                = ord( $bin[0] );
				$by_octet[ $octet ][] = $cidr;
			}
		}
		ksort( $by_octet );
		return array(
			'ranges_by_octet' => $by_octet,
			'ranges_broad'    => $broad,
		);
	}

	/**
	 * Extract IPv4/IPv6 prefixes from a Google-shape JSON payload.
	 *
	 * Shared between bin/update-bot-data.php fetchers and cron-spec parsers so
	 * both paths apply identical parsing.
	 *
	 * @param array $data Decoded JSON array.
	 * @return array List of CIDR strings.
	 * @throws \RuntimeException When the payload does not match the expected shape.
	 */
	public static function parseGoogleShape( $data ) {
		if ( ! isset( $data['prefixes'] ) || ! is_array( $data['prefixes'] ) ) {
			throw new \RuntimeException( 'unexpected JSON shape: missing prefixes[]' );
		}
		$out = array();
		foreach ( $data['prefixes'] as $p ) {
			if ( isset( $p['ipv4Prefix'] ) ) {
				$out[] = $p['ipv4Prefix'];
			} elseif ( isset( $p['ipv6Prefix'] ) ) {
				$out[] = $p['ipv6Prefix'];
			}
		}
		if ( empty( $out ) ) {
			throw new \RuntimeException( 'no prefixes found in payload' );
		}
		return $out;
	}

	/**
	 * Acquire the refresher's POSIX advisory lock.
	 *
	 * @return resource|null Lock file handle on success, null when another worker holds the lock.
	 */
	private function acquireLock() {
		if ( ! is_dir( $this->overlay_dir ) && ! mkdir( $this->overlay_dir, 0755, true ) && ! is_dir( $this->overlay_dir ) ) {
			return null;
		}
		$path = $this->overlay_dir . '/' . self::LOCK_FILENAME;
		$h    = fopen( $path, 'c+' );
		if ( false === $h ) {
			return null;
		}
		if ( ! flock( $h, LOCK_EX | LOCK_NB ) ) {
			fclose( $h );
			return null;
		}
		return $h;
	}

	/**
	 * Release a previously-acquired lock handle.
	 *
	 * @param resource $handle File handle returned by acquireLock().
	 */
	private function releaseLock( $handle ) {
		flock( $handle, LOCK_UN );
		fclose( $handle );
	}

	/**
	 * Download all.json from the mirror if its md5 has changed, verify integrity,
	 * and convert to PHP bundle files via BotDataConverter.
	 *
	 * Fetches description.json first (small, cheap); only downloads the full
	 * all.json when its md5 entry differs from the locally-cached value.
	 *
	 * @param string $mirror_base_url Base URL of the mirror (no trailing slash).
	 */
	public function refreshFromMirror( $mirror_base_url = self::MIRROR_BASE_URL ) {
		$lock = $this->acquireLock();
		if ( null === $lock ) {
			return; // Another worker holds the refresh lock — skip this run.
		}
		try {
			$this->doMirrorRefresh( $mirror_base_url );
		} finally {
			$this->releaseLock( $lock );
		}
	}

	/**
	 * Mirror-refresh body. Always invoked under the refresh lock by
	 * refreshFromMirror(); that caller's finally guarantees the lock is
	 * released on every exit path, including thrown errors.
	 *
	 * @param string $mirror_base_url Base URL of the mirror (no trailing slash).
	 */
	private function doMirrorRefresh( $mirror_base_url ) {
		$now = time();
		if ( $now < (int) get_option( self::MIRROR_NEXT_ATTEMPT_OPTION, 0 ) ) {
			return;
		}
		// Booked before the first request, so a failed — or fatally interrupted —
		// refresh still costs one attempt per schedule tick instead of one per
		// wp-cron tick.
		update_option( self::MIRROR_NEXT_ATTEMPT_OPTION, $now + self::MIN_REFRESH_INTERVAL_SECONDS, false );

		$downloader  = null === $this->downloader ? new MirrorDownloader( $this->http ) : $this->downloader;
		$description = $downloader->download( rtrim( $mirror_base_url, '/' ) . '/description.json' );
		if ( ! $description->isSuccess() ) {
			$this->reportDownloadFailure( 'description.json', 'description-fetch-failed', $description );
			return;
		}

		$manifest = json_decode( $description->body(), true );
		if ( ! is_array( $manifest ) || ! isset( $manifest['items'] ) || ! is_array( $manifest['items'] ) ) {
			BundledData::reportFailOpenError( 'refreshFromMirror', 'description.json: unexpected shape', array( 'bot-refresh', 'description-shape-invalid' ) );
			return;
		}

		$entry = null;
		foreach ( $manifest['items'] as $item ) {
			if ( isset( $item['name'] ) && 'all.json' === $item['name'] ) {
				$entry = $item;
				break;
			}
		}
		if ( null === $entry || ! isset( $entry['url'] ) ) {
			BundledData::reportFailOpenError( 'refreshFromMirror', 'all.json not found in description.json', array( 'bot-refresh', 'all-json-not-in-manifest' ) );
			return;
		}

		$remote_md5 = isset( $entry['md5sum'] )
			? (string) $entry['md5sum']
			: ( isset( $entry['md5'] ) ? (string) $entry['md5'] : '' );
		if ( '' === $remote_md5 ) {
			BundledData::reportFailOpenError( 'refreshFromMirror', 'all.json entry has no md5sum in description.json', array( 'bot-refresh', 'all-json-no-md5sum' ) );
			return;
		}

		$base           = rtrim( $mirror_base_url, '/' );
		$allowed_scheme = wp_parse_url( $base, PHP_URL_SCHEME );
		$allowed_host   = wp_parse_url( $base, PHP_URL_HOST );
		$entry_url      = (string) $entry['url'];
		$entry_scheme   = wp_parse_url( $entry_url, PHP_URL_SCHEME );
		$entry_host     = wp_parse_url( $entry_url, PHP_URL_HOST );
		if ( null === $allowed_host || $entry_scheme !== $allowed_scheme || $entry_host !== $allowed_host ) {
			BundledData::reportFailOpenError( 'refreshFromMirror', 'all.json URL does not match expected mirror origin — discarding', array( 'bot-refresh', 'all-json-url-origin-mismatch' ) );
			return;
		}

		if ( $remote_md5 === $this->readMirrorMd5() && $this->overlayHasData() ) {
			return;
		}

		$download = $downloader->download( $entry_url, $remote_md5 );
		if ( ! $download->isSuccess() ) {
			$this->reportDownloadFailure( 'all.json', 'all-json-fetch-failed', $download );
			return;
		}

		try {
			$converter = new BotDataConverter( $this->overlay_dir );
			$written   = $converter->convert( $download->body() );
			if ( $written > 0 ) {
				// The md5 is the short-circuit sentinel for the next run, so it is
				// written after the value it vouches for. Losing the second write
				// then costs one redundant download instead of pinning the intel
				// version empty until the mirror content changes.
				$this->saveIntelGeneratedAt( $converter->generatedAt() );
				$this->saveMirrorMd5( $remote_md5 );
			}
		} catch ( \Exception $e ) {
			BundledData::reportFailOpenError( 'refreshFromMirror', 'convert failed: ' . $e->getMessage(), array( 'bot-refresh', 'convert-failed' ) );
		}
	}

	/**
	 * Report a download that could not be verified, once per refresh cycle.
	 *
	 * Deliberately not routed through Debug::sendThrottledError(): the 6-hour
	 * schedule already caps this at four events per site per day, and throttling
	 * would drop the `x-req-id` values the Server team needs to find the request
	 * in the mirror logs.
	 *
	 * @param string               $object_name    Mirror object that failed, for the message.
	 * @param string               $fingerprint_id Fingerprint segment identifying the call site.
	 * @param MirrorDownloadResult $result         Failed download.
	 */
	private function reportDownloadFailure( $object_name, $fingerprint_id, $result ) {
		BundledData::reportFailOpenError(
			'refreshFromMirror',
			$object_name . ' download failed (' . $result->reason() . ') after ' . $result->attempts() . ' attempt(s)',
			array( 'bot-refresh', $fingerprint_id, $result->reason() ),
			$result->context()
		);
	}

	/**
	 * Version of the crawler-intel dataset this site is running on: the
	 * `generated_at` of the last successfully applied all.json. The mirror
	 * always publishes it in UTC, so the offset is stripped and the bare
	 * timestamp is what downstream consumers store and compare.
	 *
	 * A site that has never applied a mirror refresh runs on the snapshot
	 * bundled with the plugin, which carries no dataset version of its own —
	 * that case is reported as unknown.
	 *
	 * @return string Offset-free ISO-8601 timestamp, or '' when unknown.
	 */
	public static function intelVersion() {
		$generated_at = (string) get_option( self::MIRROR_GENERATED_AT_OPTION, '' );
		return (string) preg_replace( '/(?:Z|[+-]\d{2}:?\d{2})$/', '', $generated_at );
	}

	/**
	 * Read the md5sum stored from the last successfully applied all.json.
	 *
	 * @return string Stored md5sum, or '' when none has been applied yet.
	 */
	private function readMirrorMd5() {
		return (string) get_option( self::MIRROR_MD5_OPTION, '' );
	}

	/**
	 * Persist the md5sum of the last successfully applied all.json.
	 *
	 * @param string $md5 md5sum the mirror reported for the applied all.json.
	 */
	private function saveMirrorMd5( $md5 ) {
		update_option( self::MIRROR_MD5_OPTION, $md5, false );
	}

	/**
	 * Persist the `generated_at` of the last successfully applied all.json.
	 *
	 * @param string $generated_at Timestamp the mirror stamped on the dataset.
	 */
	private function saveIntelGeneratedAt( $generated_at ) {
		update_option( self::MIRROR_GENERATED_AT_OPTION, $generated_at, false );
	}

	/**
	 * Whether the overlay holds at least one converted bundle file.
	 *
	 * Guards the md5sum short-circuit: a matching stored md5sum must not skip the
	 * download when the overlay data has been wiped, otherwise the site would run
	 * indefinitely on bundled data with no way to repopulate until the mirror changes.
	 *
	 * @return bool
	 */
	private function overlayHasData() {
		$files = glob( $this->overlay_dir . '/*/*.php' );
		return is_array( $files ) && count( $files ) > 0;
	}

	/**
	 * Register the daily wp-cron events driving the mirror refresh.
	 *
	 * Called from BotLifecycle::activate() so events are scheduled
	 * on plugin activation and survive across site requests.
	 */
	public static function scheduleHooks() {
		$current = \wp_get_schedule( self::CRON_HOOK_REFRESH );
		if ( false !== $current && 'imunify_six_hours' !== $current ) {
			\wp_clear_scheduled_hook( self::CRON_HOOK_REFRESH );
			$current = false;
		}
		if ( false === $current ) {
			\wp_schedule_event(
				// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- cron jitter offset, not security
				\time() + \mt_rand( 0, 21599 ),
				'imunify_six_hours',
				self::CRON_HOOK_REFRESH
			);
		}
	}
}
