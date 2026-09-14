<?php
/**
 * ModuleLibrary: Section Module Conversion class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Section;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * SectionModuleConversion class.
 *
 * This class handles conversion-specific logic for the Section module,
 * including pattern-based deprecation of column-specific attributes for regular sections.
 *
 * @since ??
 */
class SectionModuleConversion {

	/**
	 * Checks if an attribute is a column-specific attribute for a regular (non-specialty) section.
	 *
	 * In Divi 4, column-specific attributes (ending with _1, _2, or _3) are only used when
	 * specialty="on". For regular sections, these properties are ignored during rendering. This method
	 * deprecates such attributes to prevent them from appearing in `unknownAttributes` and triggering
	 * shortcode-module fallback.
	 *
	 * @since ??
	 *
	 * @param bool   $should_deprecate The current deprecation status (from previous filters). Default false.
	 * @param string $desktop_name     The desktop name of the attribute (without responsive/hover/sticky suffixes).
	 * @param string $module_name      The module name (e.g., 'divi/section').
	 * @param array  $attrs            The full attributes array for the module.
	 *
	 * @return bool True if the attribute should be deprecated, false otherwise.
	 */
	public static function is_column_attribute_for_regular_section( $should_deprecate, $desktop_name, $module_name, $attrs = [] ) {
		// Early return if attribute already deprecated by another filter.
		if ( $should_deprecate ) {
			return $should_deprecate;
		}

		// Only apply to section modules.
		if ( 'divi/section' !== $module_name ) {
			return $should_deprecate;
		}

		// Only apply to regular (non-specialty) sections.
		if ( 'on' === ( $attrs['specialty'] ?? '' ) ) {
			return $should_deprecate;
		}

		// Pattern matches column-specific attributes (ends with _1, _2, or _3).
		// Examples: background_color_1, module_id_2, custom_css_3, etc.
		// Pattern breakdown:
		// - _[123] : ends with underscore followed by 1, 2, or 3
		// - $ : end of string
		// Regex Test: https://regex101.com/r/LHygHP/2.
		// Note: $desktop_name already has responsive/state suffixes stripped, so we don't check for them.
		$pattern = '/_[123]$/';

		return (bool) preg_match( $pattern, $desktop_name );
	}
}
