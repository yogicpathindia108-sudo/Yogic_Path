<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

use CloudLinux\Imunify\App\Model\PluginConfig;

/**
 * Named rate-limit and escalation profiles.
 *
 * Each preset maps every Category to a requests-per-minute limit (0 means
 * "no rate limit — always allow") and declares its escalation threshold —
 * how many rate-limit violations inside a rolling hour trigger the extended
 * block in PHP storage.
 *
 * Values match the Phase 1 engineering definition's preset table.
 *
 * @since 4.0.0
 */
class Preset {

	const BALANCED = 'balanced';
	const STRICT   = 'strict';
	const MONITOR  = 'monitor';

	/**
	 * Default preset used when caller specifies an unknown value.
	 */
	const DEFAULT_PRESET = self::BALANCED;

	/**
	 * Requests-per-minute limit for a (preset, category) pair.
	 *
	 * 0 means "no rate limit" — the category either has no limit defined
	 * (HUMAN, malicious/monitor rows) or the preset disables rate limiting
	 * entirely (MONITOR). MONITOR intentionally sets all categories to 0
	 * so that the limiter's standard "limit <= 0 → allow" short-circuit
	 * is the same code path whether rate limiting is off for this preset
	 * or off for this specific category.
	 *
	 * @param string $preset   Preset identifier.
	 * @param string $category Category constant.
	 * @return int Requests per minute (0 = no limit).
	 */
	public static function limitFor( $preset, $category ) {
		$limits = self::limits();
		if ( ! isset( $limits[ $preset ] ) ) {
			$preset = self::DEFAULT_PRESET;
		}
		return isset( $limits[ $preset ][ $category ] )
			? (int) $limits[ $preset ][ $category ]
			: 0;
	}

	/**
	 * Human-readable rate-limit label for a preset/category pair — the display
	 * form ("Block", "Monitor only", "No limit", "N / min") shared by the widget
	 * and the Bot Traffic page so the wording lives in one place.
	 *
	 * @param string $preset   Preset identifier.
	 * @param string $category Category constant.
	 * @return string
	 */
	public static function limitLabel( $preset, $category ) {
		if ( Category::isBlocking( $category ) ) {
			return self::isMonitorOnly( $preset )
				? __( 'Monitor only', 'imunify-security' )
				: __( 'Block', 'imunify-security' );
		}
		$limit = self::limitFor( $preset, $category );
		if ( $limit <= 0 ) {
			return __( 'No limit', 'imunify-security' );
		}
		/* translators: %d: requests per minute. */
		return sprintf( __( '%d / min', 'imunify-security' ), $limit );
	}

	/**
	 * Rate-limit violations within the escalation window that trigger an
	 * extended block. 0 means "escalation disabled" for this preset.
	 *
	 * @param string $preset Preset identifier.
	 * @return int
	 */
	public static function escalationThreshold( $preset ) {
		$map = array(
			self::BALANCED => 3,
			self::STRICT   => 1,
			self::MONITOR  => 0,
		);
		return isset( $map[ $preset ] ) ? $map[ $preset ] : $map[ self::DEFAULT_PRESET ];
	}

	/**
	 * Whether this preset suppresses blocking and only records telemetry.
	 *
	 * @param string $preset Preset identifier.
	 * @return bool
	 */
	public static function isMonitorOnly( $preset ) {
		return self::MONITOR === $preset;
	}

	/**
	 * Whether $preset is one of the three canonical values.
	 *
	 * @param mixed $preset Value to test.
	 * @return bool
	 */
	public static function isValid( $preset ) {
		if ( ! is_string( $preset ) ) {
			return false;
		}
		return in_array( $preset, array( self::BALANCED, self::STRICT, self::MONITOR ), true );
	}

	/**
	 * All three canonical preset identifiers.
	 *
	 * @return array
	 */
	public static function all() {
		return array( self::BALANCED, self::STRICT, self::MONITOR );
	}

	/**
	 * Human-readable label for a preset. Single source of truth for the three
	 * preset names shown across the widget and the Bot Traffic page.
	 *
	 * @param string $preset Preset identifier.
	 * @return string
	 */
	public static function label( $preset ) {
		if ( self::STRICT === $preset ) {
			return __( 'Strict', 'imunify-security' );
		}
		if ( self::MONITOR === $preset ) {
			return __( 'Monitor only', 'imunify-security' );
		}
		return __( 'Balanced', 'imunify-security' );
	}

	/**
	 * Resolve the active preset from the configuration chain.
	 *
	 * Edition clamp (authoritative): on an ImunifyAV / ImunifyAV+ license,
	 * active bot blocking is an Imunify360-only capability, so resolution
	 * short-circuits to MONITOR and ignores every other input. An absent /
	 * unknown edition never clamps, so an Imunify360 customer is never
	 * wrongly downgraded when the edition can't be determined.
	 *
	 * Priority (first match wins) once past the clamp:
	 *   1. IMUNIFY_AI_BOT_PROTECTION_PRESET wp-config constant.
	 *   2. bot-settings.php explicit preset (site owner via widget).
	 *   3. plugin_config.php hoster default (agent-written).
	 *   4. Preset::BALANCED.
	 *
	 * @since 4.0.0
	 *
	 * @param OptOutFlag   $opt_out Site-owner preference.
	 * @param PluginConfig $cfg     Agent-written config.
	 * @return string One of self::BALANCED/STRICT/MONITOR.
	 */
	public static function resolve( OptOutFlag $opt_out, PluginConfig $cfg ) {
		if ( $cfg->isImunifyAvEdition() ) {
			return self::MONITOR;
		}
		if ( defined( 'IMUNIFY_AI_BOT_PROTECTION_PRESET' ) ) {
			$candidate = (string) constant( 'IMUNIFY_AI_BOT_PROTECTION_PRESET' );
			if ( self::isValid( $candidate ) ) {
				return $candidate;
			}
		}
		if ( $opt_out->hasExplicitPreset() ) {
			return $opt_out->getPreset();
		}
		$hoster = $cfg->getPreset();
		if ( null !== $hoster ) {
			return $hoster;
		}
		return self::BALANCED;
	}

	/**
	 * Per-preset, per-category req/min map. Single source of truth for
	 * limitFor(). Kept private to discourage inline mutation.
	 *
	 * @return array
	 */
	private static function limits() {
		return array(
			self::BALANCED => array(
				Category::VERIFIED_SEARCH_ENGINE => 300,
				Category::VERIFIED_AI_CRAWLER    => 10,
				Category::VERIFIED_SEO_CRAWLER   => 60,
				Category::UNKNOWN_AUTOMATED      => 5,
				Category::UNVERIFIED_BOT         => 2,
				Category::MALICIOUS_BOT          => 0,
				Category::HUMAN                  => 0,
			),
			self::STRICT   => array(
				Category::VERIFIED_SEARCH_ENGINE => 300,
				Category::VERIFIED_AI_CRAWLER    => 3,
				Category::VERIFIED_SEO_CRAWLER   => 20,
				Category::UNKNOWN_AUTOMATED      => 2,
				Category::UNVERIFIED_BOT         => 1,
				Category::MALICIOUS_BOT          => 0,
				Category::HUMAN                  => 0,
			),
			self::MONITOR  => array(
				Category::VERIFIED_SEARCH_ENGINE => 0,
				Category::VERIFIED_AI_CRAWLER    => 0,
				Category::VERIFIED_SEO_CRAWLER   => 0,
				Category::UNKNOWN_AUTOMATED      => 0,
				Category::UNVERIFIED_BOT         => 0,
				Category::MALICIOUS_BOT          => 0,
				Category::HUMAN                  => 0,
			),
		);
	}
}
