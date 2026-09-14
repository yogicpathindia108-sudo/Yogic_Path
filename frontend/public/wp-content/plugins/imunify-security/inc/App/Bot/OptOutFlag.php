<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Site-owner opt-out + preset selection read from a widget-written PHP file.
 *
 * The dashboard widget writes this file; the agent
 * never reads or touches it. Layered below the {@see PluginConfig}
 * server-level gate and the `IMUNIFY_AI_BOT_PROTECTION*` wp-config
 * constants — see the priority chain in {@see MuLoader}.
 *
 * File layout (wp-content/imunify-security/bot-settings.php):
 *
 *     return array(
 *         'enabled'       => true,      // bool, defaults to true
 *         'preset'        => 'balanced' // one of Preset::BALANCED/STRICT/MONITOR
 *         'stats_enabled' => true,      // bool, defaults to true
 *     );
 *
 * `stats_enabled` is independent of `enabled`: turning off detailed
 * bot-traffic stats never turns off protection, and vice versa.
 *
 * Fail-open semantics: a missing, malformed, or syntactically broken
 * file is treated as "feature enabled, Balanced preset" so that a
 * corrupted settings file never turns protection off silently. The
 * inverse fail-closed posture would be a foot-gun — an admin who
 * expected the feature to remain on after a botched edit would get
 * silent degradation instead of an explicit error.
 *
 * @since 4.0.0
 */
class OptOutFlag {

	const SETTINGS_DIR  = 'imunify-security';
	const SETTINGS_FILE = 'bot-settings.php';

	/**
	 * Whether the site owner has kept bot protection on.
	 *
	 * @var bool
	 */
	private $enabled;

	/**
	 * Canonical preset identifier (always a Preset::* value).
	 *
	 * @var string
	 */
	private $preset;

	/**
	 * Whether the site owner keeps detailed bot-traffic stats capture on.
	 *
	 * @var bool
	 */
	private $statsEnabled;

	/**
	 * Whether the settings file was present and parsed as an array.
	 *
	 * Distinguishes "no site-owner preference expressed yet" (defaults)
	 * from "site owner has an explicit preset". MuLoader uses this to
	 * decide whether bot-settings.php::preset wins over the agent's
	 * plugin_config.php::bot_preset default.
	 *
	 * @var bool
	 */
	private $hasExplicitPreset;

	/**
	 * Construct a concrete OptOutFlag view over resolved values.
	 *
	 * @param bool   $enabled              Whether the feature is on.
	 * @param string $preset               Canonical preset identifier.
	 * @param bool   $has_explicit_preset  Whether $preset came from the file.
	 * @param bool   $stats_enabled        Whether detailed stats capture is on.
	 */
	private function __construct( $enabled, $preset, $has_explicit_preset, $stats_enabled = true ) {
		$this->enabled           = (bool) $enabled;
		$this->preset            = Preset::isValid( $preset ) ? $preset : Preset::BALANCED;
		$this->hasExplicitPreset = (bool) $has_explicit_preset;
		$this->statsEnabled      = (bool) $stats_enabled;
	}

	/**
	 * Read the site-owner opt-out file from a WP_CONTENT_DIR-like base path.
	 *
	 * @param string $wp_content_dir Absolute path to WordPress's wp-content directory.
	 * @return self
	 */
	public static function load( $wp_content_dir ) {
		$default = new self( true, Preset::BALANCED, false, true );

		$path = rtrim( (string) $wp_content_dir, '/' )
			. '/' . self::SETTINGS_DIR . '/' . self::SETTINGS_FILE;
		clearstatcache( true );
		if ( ! is_readable( $path ) ) {
			return $default;
		}

		$raw = SafeInclude::load( $path );
		if ( ! is_array( $raw ) ) {
			return $default;
		}

		$enabled = isset( $raw['enabled'] ) ? (bool) $raw['enabled'] : true;

		$has_explicit_preset = false;
		$preset              = Preset::BALANCED;
		if ( isset( $raw['preset'] ) && is_string( $raw['preset'] ) && Preset::isValid( $raw['preset'] ) ) {
			$preset              = $raw['preset'];
			$has_explicit_preset = true;
		}

		$stats_enabled = isset( $raw['stats_enabled'] ) ? (bool) $raw['stats_enabled'] : true;

		return new self( $enabled, $preset, $has_explicit_preset, $stats_enabled );
	}

	/**
	 * Whether bot protection should run at all for this request.
	 *
	 * @return bool
	 */
	public function isEnabled() {
		return $this->enabled;
	}

	/**
	 * Whether detailed bot-traffic stats capture should run for this request.
	 * Independent of {@see isEnabled()} — protection can be on with stats off.
	 *
	 * @return bool
	 */
	public function isStatsEnabled() {
		return $this->statsEnabled;
	}

	/**
	 * Selected Preset identifier (always a canonical Preset::* value).
	 *
	 * @return string
	 */
	public function getPreset() {
		return $this->preset;
	}

	/**
	 * Whether the site owner has explicitly chosen a preset.
	 *
	 * @return bool
	 */
	public function hasExplicitPreset() {
		return $this->hasExplicitPreset;
	}
}
