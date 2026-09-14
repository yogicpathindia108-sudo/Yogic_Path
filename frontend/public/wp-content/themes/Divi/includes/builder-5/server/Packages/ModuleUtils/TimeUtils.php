<?php
/**
 * Time Utils Class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleUtils;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * TimeUtils class.
 *
 * This class provides utility methods for converting time values with unit suffixes
 * to milliseconds. Used by Group Carousel and other modules that handle speed/duration values.
 *
 * @since ??
 */
class TimeUtils {

	/**
	 * Convert speed value with unit suffix to milliseconds.
	 *
	 * Handles both 'ms' (milliseconds) and 's' (seconds) suffixes.
	 * Checks for 'ms' suffix FIRST to avoid incorrectly treating '3ms' as '3s'.
	 *
	 * @since ??
	 *
	 * @param string|int $value Speed value with optional unit suffix (e.g., '2000ms', '5s', 2000).
	 * @return int Speed in milliseconds.
	 *
	 * @example:
	 * ```php
	 * $ms = TimeUtils::value_to_ms('5s');
	 * // Returns: 5000
	 *
	 * $ms = TimeUtils::value_to_ms('3ms');
	 * // Returns: 3
	 *
	 * $ms = TimeUtils::value_to_ms('2000');
	 * // Returns: 2000 (assumed milliseconds)
	 * ```
	 */
	public static function value_to_ms( $value ): int {
		if ( empty( $value ) ) {
			return 2000;
		}

		$value = (string) $value;

		// Check for 'ms' suffix FIRST to avoid incorrectly treating '3ms' as '3s'.
		if ( 'ms' === substr( $value, -2 ) ) {
			return (int) substr( $value, 0, -2 );
		}

		// Check for 's' suffix (seconds).
		if ( 's' === substr( $value, -1 ) ) {
			return (int) substr( $value, 0, -1 ) * 1000;
		}

		// No suffix, assume milliseconds.
		return (int) $value;
	}
}
