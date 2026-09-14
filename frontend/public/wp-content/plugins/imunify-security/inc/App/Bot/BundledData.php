<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * File path selector that prefers a runtime overlay over the shipped bundle.
 *
 * The plugin ships with a snapshot of bot/CDN/datacenter ranges and UA
 * signatures under inc/App/Bot/data/. SignatureRefresher refreshes the
 * volatile sources on a WP-Cron schedule and writes updated copies to an
 * overlay directory (typically wp-content/imunify-security/bot-data/);
 * this loader prefers an overlay file when present and falls through to
 * the bundled snapshot otherwise.
 *
 * Overlay presence is tested by readability — a missing overlay file
 * transparently falls back to the bundled snapshot. Failed overlay
 * fetches never propagate to the classifier.
 *
 * @since 4.0.0
 */
class BundledData {

	/**
	 * Absolute path to the shipped bundled data directory.
	 *
	 * @var string
	 */
	private $bundled_dir;

	/**
	 * Absolute path to the optional overlay directory, or null.
	 *
	 * @var string|null
	 */
	private $overlay_dir;

	/**
	 * Configure the loader with bundled and (optionally) overlay roots.
	 *
	 * @param string      $bundled_dir Absolute path to inc/App/Bot/data or equivalent.
	 * @param string|null $overlay_dir Optional absolute path to a writable overlay directory.
	 */
	public function __construct( $bundled_dir, $overlay_dir = null ) {
		$this->bundled_dir = self::trimTrailingSlash( (string) $bundled_dir );
		$this->overlay_dir = ( is_string( $overlay_dir ) && '' !== $overlay_dir )
			? self::trimTrailingSlash( $overlay_dir )
			: null;
	}

	/**
	 * Absolute path to the active copy of $relative, or null if neither exists.
	 *
	 * @param string $relative Path under the data root (e.g. "cdn-ip-ranges/cloudflare.php").
	 * @return string|null
	 */
	public function pathFor( $relative ) {
		if ( self::isUnsafePath( $relative ) ) {
			return null;
		}
		$tail = ltrim( $relative, '/' );

		if ( null !== $this->overlay_dir ) {
			$overlay = $this->overlay_dir . '/' . $tail;
			if ( is_file( $overlay ) ) {
				return $overlay;
			}
		}

		$bundled = $this->bundled_dir . '/' . $tail;
		if ( is_file( $bundled ) ) {
			return $bundled;
		}

		return null;
	}

	/**
	 * Enumerate every .php file directly under a sub-directory, merging overlay over bundled.
	 *
	 * Returns a map of file basename (without .php) → absolute path. When the same
	 * provider exists in both locations the overlay wins, matching pathFor() semantics.
	 *
	 * @param string $subdirectory A sub-directory of the data root (e.g. "bot-ip-ranges").
	 * @return array
	 */
	public function providersIn( $subdirectory ) {
		if ( self::isUnsafePath( $subdirectory ) ) {
			return array();
		}
		$subdirectory = trim( $subdirectory, '/' );
		$out          = array();

		foreach ( array( $this->overlay_dir, $this->bundled_dir ) as $base ) {
			if ( null === $base ) {
				continue;
			}
			$dir = $base . '/' . $subdirectory;
			if ( ! is_dir( $dir ) ) {
				continue;
			}
			$matches = glob( $dir . '/*.php' );
			if ( ! is_array( $matches ) ) {
				continue;
			}
			foreach ( $matches as $file ) {
				$name = basename( $file, '.php' );
				if ( ! isset( $out[ $name ] ) ) {
					$out[ $name ] = $file;
				}
			}
		}

		return $out;
	}

	/**
	 * Strip a trailing slash from a directory path.
	 *
	 * @param string $path Directory path, possibly with a trailing slash.
	 * @return string
	 */
	private static function trimTrailingSlash( $path ) {
		return rtrim( $path, '/' );
	}

	/**
	 * Reject path values that could escape the data root.
	 *
	 * The loader is bounded to two fixed roots (bundled, overlay) and is never
	 * expected to receive attacker-influenced input. Defensive validation keeps
	 * it that way: callers that accidentally wire user data into a relative
	 * path argument cannot reach files above the configured root.
	 *
	 * Shared with SignatureRefresher, which applies the same rules when
	 * validating overlay-write targets.
	 *
	 * Rejects: non-strings, empty strings, paths with null bytes, absolute
	 * paths (leading `/`), and paths containing a `.` or `..` as a complete
	 * path segment. Literal filename containing dots (e.g. `foo..bar`) is
	 * allowed — the dots must be a full segment between slashes or at an
	 * endpoint to be rejected.
	 *
	 * @param mixed $path Relative path to vet.
	 * @return bool True when the path is disallowed.
	 */
	public static function isUnsafePath( $path ) {
		if ( ! is_string( $path ) || '' === $path ) {
			return true;
		}
		if ( false !== strpos( $path, "\0" ) ) {
			return true;
		}
		// Reject absolute paths on Unix (`/...`) and Windows (`\...`).
		if ( 0 === strpos( $path, '/' ) || 0 === strpos( $path, '\\' ) ) {
			return true;
		}
		// Reject `.` or `..` as a complete path segment, treating both `/`
		// (POSIX) and `\` (Windows) as directory separators. Literal
		// filenames containing dots (`foo..bar`) remain allowed.
		return 1 === preg_match( '~(^|[/\\\\])\.\.?([/\\\\]|$)~', $path );
	}

	/**
	 * Load a PHP return-array file and extract a named list of strings.
	 *
	 * Shared backing for IpRangeLookup and UserAgentSignatures: both pass
	 * either an in-memory array of strings or a path to a PHP file whose
	 * return value is a provenance-wrapped payload with a named key
	 * (`ranges` for CIDR lists, `signatures` for UA tokens). Missing
	 * files, parse errors, wrong-shape payloads, and non-string items are
	 * all collapsed to an empty list per the fail-open contract; parse
	 * failures surface via the plugin's existing error hook.
	 *
	 * @param mixed  $source Either a list of strings or a path to a PHP file.
	 * @param string $key    Payload key to extract when $source is a file.
	 * @return array List of string values, empty on any error.
	 */
	public static function loadArrayFile( $source, $key ) {
		if ( is_array( $source ) ) {
			return self::filterNonEmptyStrings( $source );
		}
		if ( ! is_string( $source ) || '' === $source || ! is_readable( $source ) ) {
			return array();
		}
		try {
			$data = include $source;
		} catch ( \Throwable $e ) {
			self::reportFailOpenError( 'data file include failed for ' . $source, $e->getMessage() );
			return array();
		}
		if ( ! is_array( $data ) || ! isset( $data[ $key ] ) || ! is_array( $data[ $key ] ) ) {
			return array();
		}
		return self::filterNonEmptyStrings( $data[ $key ] );
	}

	/**
	 * Load a pre-bucketed CIDR data file and return its structure.
	 *
	 * Expects a PHP return-array file with 'ranges_by_octet' and
	 * 'ranges_broad' keys as produced by bin/update-bot-data.php.
	 * Files in the legacy flat-ranges format return empty — callers
	 * must regenerate data files with the current build tooling.
	 *
	 * @param string $source Path to a PHP data file.
	 * @return array Array with 'by_octet' => array(int => array) and 'broad' => array.
	 */
	public static function loadBucketedFile( $source ) {
		$empty = array(
			'by_octet' => array(),
			'broad'    => array(),
		);

		if ( ! is_string( $source ) || '' === $source || ! is_readable( $source ) ) {
			return $empty;
		}

		try {
			$data = include $source;
		} catch ( \Throwable $e ) {
			self::reportFailOpenError( 'bucketed data file include failed for ' . $source, $e->getMessage() );
			return $empty;
		}

		if ( ! is_array( $data ) ) {
			return $empty;
		}

		if ( ! isset( $data['ranges_by_octet'] ) || ! is_array( $data['ranges_by_octet'] ) ) {
			return $empty;
		}

		$by_octet = array();
		foreach ( $data['ranges_by_octet'] as $octet => $cidrs ) {
			if ( ! is_array( $cidrs ) || empty( $cidrs ) ) {
				continue;
			}
			$by_octet[ (int) $octet ] = $cidrs;
		}
		$broad = isset( $data['ranges_broad'] ) && is_array( $data['ranges_broad'] )
			? $data['ranges_broad']
			: array();
		return array(
			'by_octet' => $by_octet,
			'broad'    => $broad,
		);
	}


	/**
	 * Surface a fail-open error through the plugin's established error hook.
	 *
	 * Swallows exceptions thrown by registered hook callbacks so fail-open
	 * call sites (classifier data loading, refresh spec iteration) never
	 * propagate a hook's own failure past the boundary they guard.
	 *
	 * @param string     $context     Short label identifying the call site.
	 * @param string     $message     Detailed message body.
	 * @param array|null $fingerprint Optional Sentry fingerprint for cross-site grouping.
	 * @param array      $details     Optional extra values to attach to the event.
	 */
	public static function reportFailOpenError( $context, $message, $fingerprint = null, $details = array() ) {
		if ( ! function_exists( 'do_action' ) ) {
			return;
		}
		$extra = is_array( $details ) ? $details : array();
		if ( is_array( $fingerprint ) ) {
			$extra['fingerprint'] = $fingerprint;
		}
		try {
			do_action(
				'imunify_security_set_error',
				E_NOTICE,
				'Bot ' . $context . ': ' . $message,
				'',
				0,
				$extra
			);
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Intentionally swallowed — a hook-callback throw must never
			// defeat a fail-open catch block.
			unset( $e );
		}
	}

	/**
	 * Keep only non-empty strings, reindexed sequentially.
	 *
	 * Empty strings are toxic to `UserAgentSignatures::compile()`: an empty
	 * token produces `preg_quote('', ...)` === '' and the resulting
	 * alternation gets an empty branch, which matches every input. For the
	 * malicious category that would 403 every request on the site. Enforcing
	 * non-empty here covers both in-memory and file-backed sources with a
	 * single guard.
	 *
	 * @param array $items Raw list that may contain mixed / empty values.
	 * @return array
	 */
	private static function filterNonEmptyStrings( $items ) {
		$out = array();
		foreach ( $items as $item ) {
			if ( is_string( $item ) && '' !== $item ) {
				$out[] = $item;
			}
		}
		return $out;
	}
}
