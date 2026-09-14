<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Defender\Model;

/**
 * Target enum-like class.
 *
 * Defines valid target types for security rules.
 *
 * @since 2.1.0
 */
class Target {
	/**
	 * Plugin target.
	 *
	 * @var string
	 */
	const PLUGIN = 'plugin';

	/**
	 * Theme target.
	 *
	 * @var string
	 */
	const THEME = 'theme';

	/**
	 * WordPress core target.
	 *
	 * @var string
	 */
	const CORE = 'core';

	/**
	 * Get all valid target types.
	 *
	 * @return array Array of valid target types.
	 */
	public static function getValidTargets() {
		return array(
			self::PLUGIN,
			self::THEME,
			self::CORE,
		);
	}

	/**
	 * Check if a target type is valid.
	 *
	 * @param string $target Target type to validate.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public static function isValid( $target ) {
		return in_array( $target, self::getValidTargets(), true );
	}

	/**
	 * Check if a target requires a slug.
	 *
	 * @param string $target Target type to check.
	 *
	 * @return bool True if slug is required, false otherwise.
	 */
	public static function requiresSlug( $target ) {
		return in_array( $target, array( self::PLUGIN, self::THEME ), true );
	}

	/**
	 * Get target description.
	 *
	 * @param string $target Target type.
	 *
	 * @return string Target description.
	 */
	public static function getDescription( $target ) {
		$descriptions = array(
			self::PLUGIN => 'Applies to a specific plugin',
			self::THEME  => 'Applies to a specific theme',
			self::CORE   => 'Applies to WordPress core (version-based targeting)',
		);

		return isset( $descriptions[ $target ] ) ? $descriptions[ $target ] : 'Unknown target';
	}
}
