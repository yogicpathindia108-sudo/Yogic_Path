<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Defender;

use CloudLinux\Imunify\App\DataStore;
use CloudLinux\Imunify\App\Debug;
use CloudLinux\Imunify\App\Defender\Model\Rule;
use CloudLinux\Imunify\App\Defender\Model\RuleCollection;
use CloudLinux\Imunify\App\Defender\Model\Target;
use CloudLinux\Imunify\App\Defender\Model\TargetInfo;
use CloudLinux\Imunify\Composer\Semver\Semver;

/**
 * Rule provider class.
 *
 * Handles loading and providing rules for the Defender system.
 *
 * @since 2.1.0
 */
class RuleProvider extends CachedFileProvider {

	/**
	 * Rules file name.
	 */
	const RULES_FILE_NAME = 'rules.php';

	/**
	 * Transient name for caching rules.
	 */
	const RULES_TRANSIENT = 'imunify_security_rules';

	/**
	 * Debug instance.
	 *
	 * @var Debug
	 * @phpstan-ignore-next-line
	 */
	private $debug;

	/**
	 * List of plugins (local cache).
	 *
	 * @var array|null
	 */
	private $plugins;

	/**
	 * Cached rules (local cache for the duration of the request).
	 *
	 * @var RuleCollection|null
	 */
	private $rules = null;

	/**
	 * Ruleset version.
	 *
	 * @var string
	 */
	private $rulesetVersion = '';

	/**
	 * Constructor.
	 *
	 * @param Debug     $debug Debug instance.
	 * @param DataStore $dataStore Data store instance.
	 */
	public function __construct( $debug, $dataStore ) {
		$this->debug     = $debug;
		$this->dataStore = $dataStore;

		// Force reload rules when any plugin is activated or deactivated.
		add_action( 'activated_plugin', array( $this, 'onPluginActivation' ), 10, 2 );
		add_action( 'deactivated_plugin', array( $this, 'onPluginDeactivation' ), 10, 2 );

		// Force reload rules when theme is switched.
		add_action( 'switch_theme', array( $this, 'onThemeSwitch' ), 10, 3 );

		// Force reload rules when WordPress, plugins, or themes are updated.
		add_action( 'upgrader_process_complete', array( $this, 'onUpgraderProcessComplete' ), 10, 2 );
	}

	/**
	 * Get the filename for this provider.
	 *
	 * @since 3.0.0
	 *
	 * @return string The filename.
	 */
	protected function getFileName() {
		return self::RULES_FILE_NAME;
	}

	/**
	 * Force reload rules when any plugin is activated.
	 *
	 * @param string $plugin The plugin slug.
	 * @param bool   $network_wide Whether the plugin was activated network-wide.
	 * @return void
	 */
	public function onPluginActivation( $plugin, $network_wide ) {
		$this->forceRulesReload();
	}

	/**
	 * Force reload rules when any plugin is deactivated.
	 *
	 * @param string $plugin The plugin slug.
	 * @param bool   $network_deactivating_wide Whether the plugin was deactivated network-wide.
	 *
	 * @return void
	 */
	public function onPluginDeactivation( $plugin, $network_deactivating_wide ) {
		$this->forceRulesReload();
	}

	/**
	 * Force reload rules when theme is switched.
	 *
	 * @param string    $new_name The new theme name.
	 * @param \WP_Theme $new_theme The new theme object.
	 * @param \WP_Theme $old_theme The old theme object.
	 *
	 * @return void
	 */
	public function onThemeSwitch( $new_name, $new_theme, $old_theme ) {
		$this->forceRulesReload();
	}

	/**
	 * Force reload rules when WordPress, plugins, or themes are updated.
	 *
	 * Other entity types and actions are ignored.
	 *
	 * @param \WP_Upgrader $upgrader   Upgrader instance.
	 * @param array        $hook_extra Extra arguments passed to hooked filters.
	 *
	 * @return void
	 */
	public function onUpgraderProcessComplete( $upgrader, $hook_extra ) {
		if ( ! isset( $hook_extra['type'] ) || ! in_array( $hook_extra['type'], array( 'core', 'plugin', 'theme' ), true ) ) {
			return;
		}

		if ( ! isset( $hook_extra['action'] ) || 'update' !== $hook_extra['action'] ) {
			return;
		}

		$this->forceRulesReload();
	}

	/**
	 * Load rules from transient cache or rules file.
	 *
	 * @param bool $ignoreCache Whether to ignore cache and force reload from file.
	 *
	 * @return RuleCollection Collection of rules or empty collection if no rules are available.
	 */
	public function loadRules( $ignoreCache = false ) {

		// Return cached rules if already loaded during this request.
		if ( null !== $this->rules && ! $ignoreCache ) {
			return $this->rules;
		}

		if ( $this->dataStore->isDataFileAvailable( self::RULES_FILE_NAME ) ) {

			// Use atomic stat call to avoid race conditions between mtime and size checks.
			$rulesFilePath = $this->getRulesFilePath();
			$fileStats     = stat( $rulesFilePath );

			if ( is_array( $fileStats ) ) {
				// Create unique transient name using both modification time and file size.
				$transientName = $this->buildTransientName( $fileStats );

				// Try to load from transient first (unless cache should be ignored).
				if ( ! $ignoreCache ) {
					$cachedData = get_transient( $transientName );

					if ( is_array( $cachedData ) && isset( $cachedData['rules'] ) ) {
						// Return cached rules if available.
						$this->rulesetVersion = isset( $cachedData['version'] ) ? $cachedData['version'] : '';
						$result               = RuleCollection::fromArray( $cachedData['rules'] );
						$this->rules          = $result;
						return $result;
					}
				}

				// If no cached rules or cache should be ignored, load from file and cache them.
				// WordPress transients provide built-in stampede protection.
				$rulesData = $this->loadRulesFromFile();
				$rules     = isset( $rulesData['rules'] ) ? $rulesData['rules'] : array();
				$result    = $this->getRelevantRules( $rules );

				// Cache the rules and version for 6 hours if we have valid rules.
				$cacheData = array(
					'version' => $this->rulesetVersion,
					'rules'   => $result->toArray(),
				);
				set_transient( $transientName, $cacheData, self::TRANSIENT_LIFETIME );
				$this->rules = $result;
				return $result;
			}
		}

		$result      = RuleCollection::withNoRules();
		$this->rules = $result;
		return $result;
	}

	/**
	 * Load rules from the rules file.
	 *
	 * @return array Array with 'version' and 'rules' keys, or empty array if file doesn't exist or is invalid.
	 */
	public function loadRulesFromFile() {
		$data = $this->loadDataFromFile();

		if ( ! $data || ! is_array( $data ) ) {
			$this->rulesetVersion = '';
			return array(
				'version' => '',
				'rules'   => array(),
			);
		}

		// Extract version and rules from the new structure.
		$this->rulesetVersion = isset( $data['version'] ) ? $data['version'] : '';
		$rules                = isset( $data['rules'] ) && is_array( $data['rules'] ) ? $data['rules'] : array();

		return array(
			'version' => $this->rulesetVersion,
			'rules'   => $rules,
		);
	}

	/**
	 * Get the rules file path.
	 *
	 * @return string The rules file path.
	 */
	public function getRulesFilePath() {
		return $this->getFilePath();
	}

	/**
	 * Build transient name from file statistics.
	 *
	 * @param array $fileStats File statistics from stat() call.
	 *
	 * @return string Transient name.
	 */
	public function buildTransientName( $fileStats ) {
		return self::RULES_TRANSIENT . '_' . $fileStats['mtime'] . '_' . $fileStats['size'];
	}

	/**
	 * Force reload rules from file and update cache.
	 *
	 * @return RuleCollection Collection of rules.
	 */
	public function forceRulesReload() {
		$this->plugins = null;
		$this->rules   = null;
		return $this->loadRules( true );
	}

	/**
	 * Force reload data from file.
	 *
	 * Implementation of abstract method from CachedFileProvider.
	 *
	 * @since 3.0.0
	 *
	 * @return RuleCollection Collection of rules.
	 */
	public function forceReload() {
		return $this->forceRulesReload();
	}

	/**
	 * Check if the rule has all required fields.
	 *
	 * @param Rule $rule Rule object.
	 *
	 * @return bool True if the rule is valid, false otherwise.
	 */
	public function isRuleValid( $rule ) {
		// Check required fields: target, versions.
		if ( empty( $rule->getTarget() ) || empty( $rule->getVersions() ) ) {
			return false;
		}

		// Check if target is valid.
		if ( ! Target::isValid( $rule->getTarget() ) ) {
			return false;
		}

		// Check slug requirement (not required for core target).
		if ( Target::requiresSlug( $rule->getTarget() ) && empty( $rule->getSlug() ) ) {
			return false;
		}

		// Check that rule has either action or ajax_action.
		if ( ! $rule->getAction() && ! $rule->getAjaxAction() ) {
			return false;
		}

		return true;
	}

	/**
	 * Get target information if the rule's target is present on the site.
	 *
	 * @param Rule $rule Rule object.
	 *
	 * @return TargetInfo|null Target information if target is present and meets version requirements, null otherwise.
	 */
	public function getTargetInfo( $rule ) {
		if ( empty( $rule->getVersions() ) ) {
			return null;
		}

		// Load plugins if not already loaded.
		if ( null === $this->plugins ) {
			$this->plugins = $this->loadPlugins();
		}

		if ( Target::PLUGIN === $rule->getTarget() && ! empty( $rule->getSlug() ) ) {
			// Check if plugin exists and is active.
			$pluginData = $this->getPluginData( $rule->getSlug() );
			if ( ! $pluginData ) {
				return null;
			}

			// Check if plugin is active.
			$pluginFile = $this->getPluginFile( $rule->getSlug() );
			if ( ! $pluginFile || ! is_plugin_active( $pluginFile ) ) {
				return null;
			}

			// Check if plugin version meets requirements.
			$pluginVersion = isset( $pluginData['Version'] ) ? $pluginData['Version'] : null;
			if ( ! $pluginVersion ) {
				return null;
			}

			// Check if the plugin version satisfies the constraint.
			if ( $this->versionSatisfiesConstraint( $pluginVersion, $rule->getVersions() ) ) {
				return new TargetInfo( $rule->getTarget(), $rule->getSlug(), $pluginVersion );
			}
		}

		if ( Target::THEME === $rule->getTarget() && ! empty( $rule->getSlug() ) ) {
			// Check if the rule target the current theme.
			$theme = wp_get_theme();
			if ( $theme->get_template() !== $rule->getSlug() ) {
				return null;
			}

			// Check if theme version meets requirements.
			$themeVersion = $theme->get( 'Version' );
			if ( $this->versionSatisfiesConstraint( $themeVersion, $rule->getVersions() ) ) {
				return new TargetInfo( $rule->getTarget(), $rule->getSlug(), $themeVersion );
			}
		}

		if ( Target::CORE === $rule->getTarget() ) {
			// Check if WordPress core version meets requirements.
			global $wp_version;
			if ( $this->versionSatisfiesConstraint( $wp_version, $rule->getVersions() ) ) {
				return new TargetInfo( $rule->getTarget(), '', $wp_version );
			}
		}

		return null;
	}

	/**
	 * Load plugins.
	 *
	 * @return array
	 */
	private function loadPlugins() {
		// Only active plugins can match a rule target, so read just those instead
		// of scanning and header-parsing every installed plugin with get_plugins().
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Per-site active plugins are the option values; network-activated
		// (multisite) plugins are the keys of the active_sitewide_plugins site
		// option. Must-use plugins are intentionally excluded: get_plugins() never
		// returned them and is_plugin_active() rejects them, so including them
		// would change which rules match.
		$active   = (array) get_option( 'active_plugins', array() );
		$sitewide = array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) );

		$plugins = array();
		foreach ( array_unique( array_merge( $active, $sitewide ) ) as $pluginFile ) {
			if ( ! is_string( $pluginFile ) || '' === $pluginFile ) {
				continue;
			}
			$path = WP_PLUGIN_DIR . '/' . $pluginFile;
			if ( is_readable( $path ) ) {
				// $markup = false, $translate = false skips the header markup/i18n
				// pass; only the raw Version string is needed.
				$plugins[ $pluginFile ] = get_plugin_data( $path, false, false );
			}
		}

		return $plugins;
	}

	/**
	 * Get plugin data by slug.
	 *
	 * @param string $pluginSlug Plugin slug.
	 *
	 * @return array|null Plugin data or null if not found.
	 */
	private function getPluginData( $pluginSlug ) {
		$pluginFile = $this->getPluginFile( $pluginSlug );

		return null === $pluginFile ? null : $this->plugins[ $pluginFile ];
	}

	/**
	 * Get plugin file path for is_plugin_active.
	 *
	 * @param string $pluginSlug Plugin slug.
	 *
	 * @return string|null Plugin file path or null if not found.
	 */
	private function getPluginFile( $pluginSlug ) {
		foreach ( $this->plugins as $pluginFile => $pluginData ) {
			if ( 0 === strcasecmp( $this->getPluginSlugFromFile( $pluginFile ), $pluginSlug ) ) {
				return $pluginFile;
			}
		}

		return null;
	}

	/**
	 * Derive the plugin slug from a plugin file path.
	 *
	 * WordPress plugin files are either "slug/main-file.php" for directory-based
	 * plugins or "main-file.php" for single-file plugins. For directory-based
	 * plugins the slug is the directory segment before the first slash. For
	 * single-file plugins the slug is the file name without the ".php" extension.
	 *
	 * Matching on the exact segment (instead of a loose substring test) avoids
	 * matching an unintended plugin, e.g. slug "woo" matching "woocommerce" or a
	 * slug that is a substring of another plugin's folder.
	 *
	 * @param string $pluginFile Plugin file path relative to the plugins directory.
	 *
	 * @return string Plugin slug.
	 */
	private function getPluginSlugFromFile( $pluginFile ) {
		$slashPos = strpos( $pluginFile, '/' );
		if ( false !== $slashPos ) {
			return substr( $pluginFile, 0, $slashPos );
		}

		return preg_replace( '/\.php$/i', '', $pluginFile );
	}

	/**
	 * Sanitize a version string before parsing.
	 *
	 * Handles common real-world quirks found in WordPress plugin/theme headers:
	 * - URL-encoded characters (%0d, %c4%97, etc.)
	 * - Control characters and non-ASCII bytes
	 * - Build metadata (+v3api)
	 * - Spaces in version strings (1.0.0 Alpha)
	 * - Commas used as separators (1,6,8)
	 * - Consecutive dots (3..1.27)
	 * - Underscores as separators (4.2.4_2)
	 * - Hyphen + bare number suffix (1.0.5-1 → 1.0.5.1)
	 * - Hyphen + arbitrary text suffix (1.16.5-issue-598 → 1.16.5)
	 * - Text-only segments (.unstable, .Test)
	 * - Letters mixed into numeric segments (1dev2, 3bk, 1.6c)
	 * - 6+ numeric segments (truncated to 5)
	 *
	 * @param string $version Raw version string.
	 *
	 * @return string Sanitized version string.
	 */
	private static function sanitizeVersion( $version ) {
		// Decode percent-encoded characters. E.g. "2.0.10.8%0d" → "2.0.10.8\r".
		$version = rawurldecode( $version );

		// Strip control characters and non-ASCII bytes. E.g. "2.0.10.8\r" → "2.0.10.8".
		$version = preg_replace( '/[^\x20-\x7E]/', '', $version );
		$version = trim( $version );

		// Strip semver build metadata. E.g. "3.0.13+v3api" → "3.0.13".
		$version = preg_replace( '/\+.*$/', '', $version );

		// Strip everything from the first space onward. E.g. "1.0.0 Alpha" → "1.0.0".
		$version = preg_replace( '/\s.*$/', '', $version );

		// Replace commas with dots. E.g. "1,6,8" → "1.6.8".
		$version = str_replace( ',', '.', $version );

		// Collapse consecutive dots. E.g. "3..1.27" → "3.1.27".
		$version = preg_replace( '/\.{2,}/', '.', $version );

		// Replace underscores with dots. E.g. "4.2.4_2" → "4.2.4.2".
		$version = str_replace( '_', '.', $version );

		// Handle hyphenated suffixes on numeric base versions.
		if ( preg_match( '/^(v?\d+(?:\.\d+)*)-(.+)$/', $version, $m ) ) {
			$base   = $m[1];
			$suffix = $m[2];

			// Keep known stability suffixes for the library. E.g. "1.0.0-beta1", "3.1.0-dev1".
			if ( ! preg_match( '/^(stable|beta|b|rc|alpha|a|patch|pl|p|dev)(\d|[.-]|$)/i', $suffix ) ) {
				if ( preg_match( '/^(\d+)/', $suffix, $numMatch ) ) {
					// Leading digit becomes a dot-segment. E.g. "1.0.5-1" → "1.0.5.1", "9.3.1-2nd" → "9.3.1.2".
					$version = $base . '.' . $numMatch[1];
				} else {
					// Arbitrary text suffix stripped. E.g. "1.16.5-issue-598" → "1.16.5".
					$version = $base;
				}
			}
		}

		// Strip trailing text-only segments. E.g. "1.0.20.2.unstable" → "1.0.20.2".
		$version = preg_replace( '/(\.[a-zA-Z]\w*)+$/', '', $version );

		// Strip letters mixed into numeric segments. E.g. "1.0.8.1dev2" → "1.0.8.1", "1.6c" → "1.6".
		$version = preg_replace( '/(?<=\d)[a-zA-Z]+\w*(?=\.|$)/', '', $version );

		// Truncate 6+ numeric segments to 5. E.g. "2.1.4.6.2.1" → "2.1.4.6.2".
		$version = preg_replace( '/^(v?\d+(?:\.\d+){4})\.\d+/', '$1', $version );

		// Clean up any trailing dots left by prior rules.
		$version = rtrim( $version, '.' );

		return $version;
	}

	/**
	 * Check if version satisfies constraint.
	 *
	 * @param string $currentVersion Current version of the plugin or theme.
	 * @param string $constraint     Version constraint (e.g., ">=1.0.0 <2.0.0").
	 *
	 * @return bool
	 */
	private function versionSatisfiesConstraint( $currentVersion, $constraint ) {
		try {
			$currentVersion = self::sanitizeVersion( $currentVersion );

			return Semver::satisfies( $currentVersion, $constraint );
		} catch ( \UnexpectedValueException $e ) {
			return false;
		}
	}

	/**
	 * Filter and return only relevant rules.
	 *
	 * Rule is relevant if it is valid and its target is present on the site.
	 *
	 * @param array $rules Array of rules.
	 *
	 * @return RuleCollection Collection of relevant rules.
	 */
	private function getRelevantRules( $rules ) {
		// Validate and filter rules.
		$result = new RuleCollection();
		if ( ! empty( $rules ) ) {
			foreach ( $rules as $id => $ruleData ) {
				$rule = Rule::fromArray( $id, $ruleData );
				if ( $this->isRuleValid( $rule ) && $this->getTargetInfo( $rule ) ) {
					$result->addRule( $rule );
				}
			}
		}
		return $result;
	}

	/**
	 * Get the ruleset version.
	 *
	 * @return string Ruleset version.
	 */
	public function getRulesetVersion() {
		return $this->rulesetVersion;
	}
}
