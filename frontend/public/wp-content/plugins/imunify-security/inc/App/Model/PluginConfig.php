<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Model;

use CloudLinux\Imunify\App\Bot\Preset;

/**
 * Configuration carried in the dedicated plugin_config.php file.
 *
 * Separate from scan_data.php so the mu-plugin can load just what it
 * needs on the hot path — no malware list, no license metadata, just
 * feature flags. The agent writes this file on every admin-config
 * toggle and alongside every scan-cycle rewrite.
 *
 * The plugin is distributed with the agent, so their versions are in
 * lockstep by construction — if this class reads a field, the agent
 * that wrote the file knew how to populate it. Any forward-compatible
 * gating therefore belongs on the writer side (the agent simply
 * omits a field it doesn't support); consumers of this class treat
 * missing fields as "unset, use safe default".
 *
 * @since 4.0.0
 */
class PluginConfig {

	/**
	 * Raw ai_bot_protection flag as written by the agent. Missing /
	 * malformed input defaults to false so a first-time install or a
	 * partially-written file never inadvertently turns the feature on.
	 *
	 * @var bool
	 */
	private $aiBotProtectionEnabled = false;

	/**
	 * Hoster-level default preset written by the agent. Null means
	 * "agent didn't set one" (or the value was malformed) — caller
	 * (Preset::resolve) decides the chain fallback.
	 *
	 * @var string|null One of Preset::BALANCED/STRICT/MONITOR, or null.
	 */
	private $preset = null;

	/**
	 * License edition string as written by the agent (e.g. imunify360,
	 * imunifyAV, imunifyAVPlus), or null when the agent didn't write one
	 * (older agent, missing / malformed value). Kept verbatim (trimmed);
	 * edition matching normalises case at read time.
	 *
	 * @var string|null
	 */
	private $licenseType = null;

	/**
	 * True when this instance stands in for a plugin_config.php that
	 * couldn't be read at all (missing / unreadable / unparseable),
	 * rather than one that was successfully read and says the flag is
	 * off. isAiBotProtectionEnabled() still reads false either way — the
	 * distinction exists for callers like MuPluginSelfHealer that must
	 * not treat "couldn't tell" the same as "hoster said no".
	 *
	 * @var bool
	 */
	private $indeterminate = false;

	/**
	 * Hydrate from the decoded contents of plugin_config.php.
	 *
	 * @param mixed $data Decoded file contents (expected array).
	 * @return self
	 */
	public static function fromArray( $data ) {
		$instance = new self();
		if ( ! is_array( $data ) ) {
			return $instance;
		}
		if ( isset( $data['ai_bot_protection'] ) ) {
			$instance->aiBotProtectionEnabled = (bool) $data['ai_bot_protection'];
		}
		if ( isset( $data['preset'] ) && is_string( $data['preset'] ) ) {
			$normalized = strtolower( trim( $data['preset'] ) );
			if ( Preset::isValid( $normalized ) ) {
				$instance->preset = $normalized;
			}
		}
		if ( isset( $data['license_type'] ) && is_string( $data['license_type'] ) ) {
			$edition = trim( $data['license_type'] );
			if ( '' !== $edition ) {
				$instance->licenseType = $edition;
			}
		}
		return $instance;
	}

	/**
	 * Stand-in for a plugin_config.php that couldn't be determined at
	 * all. Reads the same as a disabled config to callers that don't
	 * check isIndeterminate() — install decisions and the per-request
	 * gate already default safely to "off" when unsure.
	 *
	 * @return self
	 */
	public static function indeterminate() {
		$instance                = new self();
		$instance->indeterminate = true;
		return $instance;
	}

	/**
	 * Whether the server-level AI bot protection feature gate is enabled.
	 *
	 * @return bool
	 */
	public function isAiBotProtectionEnabled() {
		return $this->aiBotProtectionEnabled;
	}

	/**
	 * Whether this config's real state is unknown (the source file was
	 * missing, unreadable, or failed to parse) rather than genuinely
	 * read as disabled.
	 *
	 * @return bool
	 */
	public function isIndeterminate() {
		return $this->indeterminate;
	}

	/**
	 * Hoster-level default preset, or null when unset / malformed.
	 *
	 * @return string|null
	 */
	public function getPreset() {
		return $this->preset;
	}

	/**
	 * License edition string as written by the agent, or null when unset /
	 * malformed. Returned verbatim (trimmed); callers that gate on the
	 * edition should use {@see isImunifyAvEdition()} rather than comparing
	 * this raw value, so case and future variants are handled in one place.
	 *
	 * @since 4.1.0
	 *
	 * @return string|null
	 */
	public function getLicenseType() {
		return $this->licenseType;
	}

	/**
	 * Whether the license edition belongs to the ImunifyAV family
	 * (ImunifyAV or ImunifyAV+), for which AI bot management is forced to
	 * monitor-only. Delegates to {@see Edition::isImunifyAvFamily()} — the
	 * single source of truth for the AV-family rule — so an absent / unknown
	 * edition never locks and an Imunify360 customer is never wrongly
	 * downgraded.
	 *
	 * @since 4.1.0
	 *
	 * @return bool
	 */
	public function isImunifyAvEdition() {
		return Edition::isImunifyAvFamily( $this->licenseType );
	}
}
