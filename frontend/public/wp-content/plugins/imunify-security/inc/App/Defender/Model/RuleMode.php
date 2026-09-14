<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Defender\Model;

/**
 * Rule mode enum-like class.
 *
 * This class provides an enum-like functionality for rule modes
 * that is compatible with PHP 5.6.
 */
class RuleMode {
	/**
	 * Pass mode - allows the action to proceed.
	 *
	 * @var string
	 */
	const PASS = 'pass';

	/**
	 * Block mode - blocks the action.
	 *
	 * @var string
	 */
	const BLOCK = 'block';

	/**
	 * Get all available rule modes.
	 *
	 * @return string[]
	 */
	public static function getAll() {
		return array(
			self::PASS,
			self::BLOCK,
		);
	}

	/**
	 * Check if a rule mode is valid.
	 *
	 * @param string $mode Rule mode to check.
	 *
	 * @return bool True if the rule mode is valid, false otherwise.
	 */
	public static function isValid( $mode ) {
		return in_array( $mode, self::getAll(), true );
	}

	/**
	 * Get the display name for a rule mode.
	 *
	 * @param string $mode Rule mode.
	 *
	 * @return string Display name for the rule mode.
	 */
	public static function getDisplayName( $mode ) {
		switch ( $mode ) {
			case self::PASS:
				return esc_html__( 'Pass', 'imunify-security' );
			case self::BLOCK:
				return esc_html__( 'Block', 'imunify-security' );
			default:
				return '';
		}
	}

	/**
	 * Get the description for a rule mode.
	 *
	 * @param string $mode Rule mode.
	 *
	 * @return string Description for the rule mode.
	 */
	public static function getDescription( $mode ) {
		switch ( $mode ) {
			case self::PASS:
				return esc_html__( 'Allows the action to proceed normally.', 'imunify-security' );
			case self::BLOCK:
				return esc_html__( 'Blocks the action and returns a 403 response.', 'imunify-security' );
			default:
				return '';
		}
	}
}
