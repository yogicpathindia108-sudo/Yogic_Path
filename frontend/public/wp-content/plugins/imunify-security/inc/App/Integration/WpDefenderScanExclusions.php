<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Integration;

use CloudLinux\Imunify\App\Bot\MuPluginInstaller;

/**
 * Registers Imunify Security with WPMU Defender's public scan-exclusion
 * hooks so Defender stops flagging our own files as malware: the data
 * files this plugin and the Imunify agent write under
 * wp-content/imunify-security/, the SQLi/XSS matcher in the plugin
 * directory, and the bot mu-plugin.
 *
 * Two hooks cover two scan types:
 *   - `wd_scan_excluded_plugin_slugs` excludes our plugin directory from
 *     Defender's plugin integrity and abandoned-plugin checks.
 *   - `site_option_defender_scan_ignore_index` is WordPress core's
 *     read-time filter for Defender's stored ignore list. Defender reads
 *     that option with `get_site_option()` while scanning — including the
 *     Pro suspicious-code scan, which skips any file whose absolute path is
 *     in the list before running its YARA rules. Merging at read time keeps
 *     Defender's stored DB option untouched.
 *
 * Both callbacks no-op when Defender is absent: nothing fires the hooks.
 *
 * Defender matches ignore entries with a strict `in_array()` against a file
 * list it builds by concatenating names onto ABSPATH (no `realpath()`), so
 * the absolute paths derived here from the WordPress path constants match
 * byte-for-byte in the standard layout.
 *
 * @since 4.0.2
 */
class WpDefenderScanExclusions {

	const SLUG_FILTER = 'wd_scan_excluded_plugin_slugs';

	const IGNORE_INDEX_FILTER = 'site_option_defender_scan_ignore_index';

	const CONDITION_MATCHER_RELATIVE_PATH = 'inc/App/Defender/ConditionMatcher.php';

	/**
	 * Fixed set of files the plugin and the Imunify agent write under the
	 * data directory. An explicit list rather than a directory scan: the
	 * set is known and stable, and it deliberately omits the hourly
	 * incidents/*.php files, which rotate too fast for an exact-match
	 * ignore list to track reliably.
	 */
	const DATA_FILES = array(
		'rules.php',
		'disabled-rules.php',
		'changelog.php',
		'scan_data.php',
		'auth.php',
		'plugin_config.php',
		'bot-settings.php',
	);

	/**
	 * Imunify data directory, e.g. wp-content/imunify-security.
	 *
	 * @var string
	 */
	private $dataDirectory;

	/**
	 * Installed plugin directory (IMUNIFY_SECURITY_PATH).
	 *
	 * @var string
	 */
	private $pluginDirectory;

	/**
	 * Must-use plugin directory (WPMU_PLUGIN_DIR); may be empty.
	 *
	 * @var string
	 */
	private $muPluginDirectory;

	/**
	 * Memoized exclusion paths.
	 *
	 * @var array|null
	 */
	private $exclusionPaths = null;

	/**
	 * Record the target directories and defer registration to `plugins_loaded`.
	 *
	 * @param string $data_directory      Imunify data directory.
	 * @param string $plugin_directory    Installed plugin directory.
	 * @param string $mu_plugin_directory Must-use plugin directory, may be empty.
	 */
	public function __construct( $data_directory, $plugin_directory, $mu_plugin_directory ) {
		$this->dataDirectory     = rtrim( (string) $data_directory, '/\\' );
		$this->pluginDirectory   = rtrim( (string) $plugin_directory, '/\\' );
		$this->muPluginDirectory = rtrim( (string) $mu_plugin_directory, '/\\' );

		add_action( 'plugins_loaded', array( $this, 'maybeRegister' ) );
	}

	/**
	 * Hook both Defender exclusion filters, but only when Defender is loaded.
	 *
	 * Must run on `plugins_loaded` or later: WordPress loads active plugins in
	 * `active_plugins` order, so Defender (whose Pro directory `wp-defender`
	 * sorts after `imunify-security`) may not have defined its constants yet
	 * during our own bootstrap.
	 *
	 * @return void
	 */
	public function maybeRegister() {
		if ( ! $this->isDefenderActive() ) {
			return;
		}
		$this->register();
	}

	/**
	 * Whether WPMU Defender is loaded for this request.
	 *
	 * @return bool
	 */
	public function isDefenderActive() {
		// WP_DEFENDER_DIR is defined at the top of Defender's main file in both
		// the free and Pro builds, so its presence marks Defender as loaded.
		return defined( 'WP_DEFENDER_DIR' );
	}

	/**
	 * Hook both Defender exclusion filters.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( self::SLUG_FILTER, array( $this, 'filterExcludedPluginSlugs' ) );
		add_filter( self::IGNORE_INDEX_FILTER, array( $this, 'filterScanIgnoreIndex' ), 10, 3 );
	}

	/**
	 * Add our plugin slug to Defender's excluded-plugin list.
	 *
	 * @param mixed $slugs Excluded plugin slugs from Defender.
	 *
	 * @return array
	 */
	public function filterExcludedPluginSlugs( $slugs ) {
		$slugs   = is_array( $slugs ) ? $slugs : array();
		$slugs[] = $this->pluginSlug();

		return array_values( array_unique( $slugs ) );
	}

	/**
	 * Merge our data-file paths into Defender's ignore list at read time.
	 *
	 * @param mixed  $value      Stored ignore list; Defender passes an array.
	 * @param string $option     Option name (unused).
	 * @param int    $network_id Network id (unused).
	 *
	 * @return array
	 */
	public function filterScanIgnoreIndex( $value, $option = '', $network_id = 0 ) {
		$value = is_array( $value ) ? $value : array();

		return array_values( array_unique( array_merge( $value, $this->getExclusionPaths() ) ) );
	}

	/**
	 * Absolute paths Defender must skip. Memoized because the ignore-index
	 * filter fires once per scanned file.
	 *
	 * @return array
	 */
	public function getExclusionPaths() {
		if ( null !== $this->exclusionPaths ) {
			return $this->exclusionPaths;
		}

		$paths = array();
		foreach ( self::DATA_FILES as $filename ) {
			$paths[] = $this->dataDirectory . '/' . $filename;
		}

		if ( '' !== $this->pluginDirectory ) {
			$paths[] = $this->pluginDirectory . '/' . self::CONDITION_MATCHER_RELATIVE_PATH;
		}

		if ( '' !== $this->muPluginDirectory ) {
			$paths[] = $this->muPluginDirectory . '/' . MuPluginInstaller::SHIM_FILENAME;
			$paths[] = $this->muPluginDirectory . '/' . MuPluginInstaller::LEGACY_SHIM_FILENAME;
		}

		$this->exclusionPaths = array_values( array_unique( $paths ) );

		return $this->exclusionPaths;
	}

	/**
	 * Defender keys plugin exclusions by directory name, which is the
	 * basename of the installed plugin directory.
	 *
	 * @return string
	 */
	private function pluginSlug() {
		return basename( $this->pluginDirectory );
	}
}
