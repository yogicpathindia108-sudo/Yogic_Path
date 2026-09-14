<?php
/**
 * ModuleLibrary: Row Module Conversion class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Row;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * RowModuleConversion class.
 *
 * This class handles conversion-specific logic for the Row module,
 * including pattern-based deprecation of legacy column background attributes.
 *
 * @since ??
 */
class RowModuleConversion {

	/**
	 * Checks if an attribute is a legacy column background attribute with numeric suffix.
	 *
	 * Legacy column background attributes (e.g., `background_color_4`, `background_size_4`)
	 * were stored on row modules but belong to columns. These should be deprecated for row
	 * modules to prevent them from appearing in `unknownAttributes` and triggering
	 * shortcode-module fallback.
	 *
	 * @since ??
	 *
	 * @param bool   $should_deprecate The current deprecation status (from previous filters). Default false.
	 * @param string $desktop_name     The desktop name of the attribute (without responsive/hover/sticky suffixes).
	 * @param string $module_name      The module name (e.g., 'divi/row', 'divi/row-inner').
	 *
	 * @return bool True if the attribute is a legacy column background attribute with numeric suffix on a row module, false otherwise.
	 */
	public static function is_legacy_column_background_attribute( $should_deprecate, $desktop_name, $module_name ) {
		// Early return if attribute already deprecated by another filter.
		if ( $should_deprecate ) {
			return $should_deprecate;
		}

		// Only apply to row and row-inner modules.
		if ( 'divi/row' !== $module_name && 'divi/row-inner' !== $module_name ) {
			return $should_deprecate;
		}

		// Pattern matches legacy column background attributes with numeric suffixes.
		// Examples: background_color_4, background_size_4, use_background_color_gradient_4, etc.
		// Pattern breakdown:
		// - ^(use_)? : optionally starts with "use_"
		// - background_ : starts with "background_"
		// - .+ : one or more characters (attribute name part)
		// - _\d+$ : ends with underscore followed by one or more digits
		// Regex Test: https://regex101.com/r/uSgcEd/1.
		$pattern = '/^(use_)?background_.+_\d+$/';

		return (bool) preg_match( $pattern, $desktop_name );
	}
}
