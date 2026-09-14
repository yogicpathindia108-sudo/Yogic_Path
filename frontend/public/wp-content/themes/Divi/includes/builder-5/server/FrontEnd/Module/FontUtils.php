<?php
/**
 * Frontend Font Utilities
 *
 * @package Divi
 *
 * @since ??
 */

namespace ET\Builder\FrontEnd\Module;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Font Utilities class.
 *
 * Provides shared utility functions for font processing.
 *
 * @since ??
 */
class FontUtils {

	/**
	 * Format font value with MS version support for websafe fonts.
	 *
	 * Checks if a font requires an MS version (like Trebuchet) and formats
	 * the font stack accordingly. For fonts that need MS version, returns
	 * the format: 'FontName MS', 'FontName'. Otherwise, returns the escaped font name.
	 *
	 * @since ??
	 *
	 * @param string $font_name The font name to format.
	 *
	 * @return string The formatted font value, ready for CSS output.
	 */
	public static function format_font_value_with_ms_version( string $font_name ): string {
		$font_name        = trim( $font_name );
		$needs_ms_version = false;

		$websafe_fonts = et_builder_get_websafe_fonts();

		if ( isset( $websafe_fonts[ $font_name ] ) ) {
			$font_data        = $websafe_fonts[ $font_name ];
			$needs_ms_version = isset( $font_data['add_ms_version'] ) && $font_data['add_ms_version'];
		}

		if ( $needs_ms_version ) {
			$escaped_font_name = esc_html( $font_name );
			return "'" . $escaped_font_name . " MS', '" . $escaped_font_name . "'";
		}

		return esc_html( $font_name );
	}
}
