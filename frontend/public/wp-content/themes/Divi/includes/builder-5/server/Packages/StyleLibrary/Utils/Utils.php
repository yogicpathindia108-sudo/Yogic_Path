<?php
/**
 * StyleLibrary\Utils class
 *
 * @package Divi
 * @since   ??
 */

namespace ET\Builder\Packages\StyleLibrary\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentACFUtils;

/**
 * Utils class is a helper class with helper methods to work with the style library.
 *
 * @since ??
 */
class Utils {


	/**
	 * Join array of declarations into `;` separated string, suffixed by `;`.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/style-library/join-declarations joinDeclarations} in:
	 * `@divi/style-library` package.
	 *
	 * @since ??
	 *
	 * @param array $declarations Array of declarations.
	 *
	 * @return string
	 */
	public static function join_declarations( array $declarations ): string {
		$joined = implode( '; ', $declarations );

		if ( 0 < count( $declarations ) ) {
			$joined = $joined . ';';
		}

		return $joined;
	}

	/**
	 * Recursively resolve any `$variable(...)$` strings within an array or string.
	 *
	 * @since ??
	 *
	 * @param mixed $value The raw input, string or array.
	 *
	 * @return mixed The resolved value with all dynamic variables normalized.
	 */
	public static function resolve_dynamic_variables_recursive( $value ) {
		if ( ! is_array( $value ) ) {
			if ( ! is_string( $value ) || ! str_contains( $value, '$variable(' ) ) {
				return $value;
			}
			return self::resolve_dynamic_variable( $value );
		}

		foreach ( $value as $key => $subvalue ) {
			$value[ $key ] = self::resolve_dynamic_variables_recursive( $subvalue );
		}

		return $value;
	}

	/**
	 * Resolves a `$variable(...)$` encoded dynamic content string into a CSS variable.
	 *
	 * Example:
	 * Input:  $variable({"type":"content","value":{"name":"gvid-abc123"}})$
	 * Output: var(--gvid-abc123)
	 *
	 * @since ??
	 *
	 * @param string $value The raw string to be resolved.
	 *
	 * @return string The resolved CSS variable or original value if not matched.
	 */
	public static function resolve_dynamic_variable( $value ) {
		static $cache = [];

		if ( ! is_string( $value ) || ! str_contains( $value, '$variable(' ) ) {
			return $value;
		}

		if ( isset( $cache[ $value ] ) ) {
			return $cache[ $value ];
		}

		$result = preg_replace_callback(
			'/\$variable\((.+?)\)\$/',
			function ( $matches ) {
				$json = $matches[1];

				// Handle escaped quotes in JSON string.
				if ( str_contains( $json, '\"' ) ) {
					$json = stripslashes( $json );
				}

				$decoded = json_decode( $json, true );
				$type    = $decoded['type'] ?? '';
				$name    = $decoded['value']['name'] ?? null;

				if ( $name ) {
					// Check if this is an ACF color field.
					$is_acf_color_field = self::_is_acf_color_field( $name, $type, $decoded['value']['settings'] ?? [] );

					if ( $is_acf_color_field ) {
						// For ACF color fields, return the resolved ACF value directly without HSL processing.
						return self::_resolve_acf_color_field_value( $name, $decoded['value']['settings'] ?? [] );
					}

					// Strip leading `--` to prevent double-prefix (e.g. `var(----name)`).
					// Customizer font variables are defined with `--` prefix in GlobalData.
					$normalized_name = preg_replace( '/^--/', '', $name );
					$css_variable    = "var(--{$normalized_name})";

					switch ( $type ) {
						case 'color':
							return GlobalData::transform_state_into_global_color_value( $css_variable, $decoded['value']['settings'] ?? [] );
						default:
							return $css_variable;
					}
				}

				return $matches[0];
			},
			$value
		);

		$cache[ $value ] = $result;

		return $result;
	}

	/**
	 * Resolve dynamic variable and sanitize scalar value for CSS usage.
	 *
	 * @since ??
	 *
	 * @param mixed $value Raw scalar value.
	 *
	 * @return string
	 */
	public static function resolve_and_sanitize_css_scalar_value( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$sanitized_value = self::resolve_dynamic_variable( (string) $value );
		$sanitized_value = wp_check_invalid_utf8( $sanitized_value );
		$sanitized_value = wp_kses_no_null( $sanitized_value );
		$sanitized_value = wp_strip_all_tags( $sanitized_value );

		return trim( $sanitized_value );
	}

	/**
	 * Sanitize non-variable image URL and format as CSS `url(...)` reference.
	 *
	 * @since ??
	 *
	 * @param mixed $url Raw non-variable image URL value.
	 *
	 * @return string
	 */
	public static function sanitize_non_variable_image_url_css_reference( $url ): string {
		$sanitized_url = self::resolve_and_sanitize_css_scalar_value( $url );

		if ( '' === $sanitized_url ) {
			return '';
		}

		// Regex test: https://regex101.com/r/W00es9/2.
		if ( 1 === preg_match( '/^url\((.+)\)$/i', $sanitized_url, $matches ) ) {
			$sanitized_url = trim( $matches[1], " \t\n\r\0\x0B\"'" );
		}

		$is_data_url = 0 === stripos( $sanitized_url, 'data:' );

		// Allow only base64-encoded data:image/* payloads.
		// Regex test: https://regex101.com/r/nVNo8f/1/.
		if (
			$is_data_url &&
			1 !== preg_match(
				'/^data:image\/(?:png|gif|jpe?g|webp|bmp|x-icon|vnd\.microsoft\.icon|avif|svg\+xml);base64,[a-z0-9+\/=\r\n]+$/i',
				$sanitized_url
			)
		) {
			return '';
		}

		$escaped_url = esc_url_raw(
			$sanitized_url,
			$is_data_url ? array_merge( wp_allowed_protocols(), [ 'data' ] ) : wp_allowed_protocols()
		);

		if ( '' === $escaped_url ) {
			return '';
		}

		$css_escaped_url = addcslashes( $escaped_url, "\\'" );

		return "url('{$css_escaped_url}')";
	}

	/**
	 * Check whether a value contains a global image variable token.
	 *
	 * @since ??
	 *
	 * @param mixed $value The value to inspect.
	 *
	 * @return bool Whether the value contains a global image variable token.
	 */
	public static function is_global_image_variable( $value ): bool {
		if ( ! is_string( $value ) || ! str_contains( $value, '$variable(' ) ) {
			return false;
		}

		$global_variables          = GlobalData::get_global_variables();
		$image_global_variables    = (array) ( $global_variables['images'] ?? (object) [] );
		$has_global_image_variable = false;

		preg_replace_callback(
			'/\$variable\((.+?)\)\$/',
			function ( $matches ) use ( &$has_global_image_variable, $image_global_variables ) {
				$json = $matches[1];

				// Handle escaped quotes in JSON string.
				if ( str_contains( $json, '\"' ) ) {
					$json = stripslashes( $json );
				}

				$decoded              = json_decode( $json, true );
				$type                 = $decoded['type'] ?? '';
				$name                 = $decoded['value']['name'] ?? '';
				$is_global_image_type = 'image' === $type && is_string( $name ) && str_starts_with( $name, 'gvid-' );
				$is_registered_image  = is_string( $name ) && isset( $image_global_variables[ $name ] );

				if ( ! $is_registered_image && is_string( $name ) ) {
					foreach ( $image_global_variables as $image_global_variable ) {
						if ( is_array( $image_global_variable ) && ( $image_global_variable['id'] ?? '' ) === $name ) {
							$is_registered_image = true;
							break;
						}
					}
				}

				if ( $is_global_image_type || $is_registered_image ) {
					$has_global_image_variable = true;
				}

				return $matches[0];
			},
			$value
		);

		return $has_global_image_variable;
	}

	/**
	 * Check if a dynamic variable represents an ACF color picker field.
	 *
	 * @since ??
	 *
	 * @param string $name     The dynamic variable name.
	 * @param string $type     The dynamic variable type.
	 * @param array  $settings The dynamic variable settings.
	 *
	 * @return bool True if this is an ACF color picker field.
	 */
	private static function _is_acf_color_field( $name, $type, $settings = [] ) {
		// Only check color type variables.
		if ( 'color' !== $type ) {
			return false;
		}

		// Simplified detection: if it's a custom_meta field with color type, treat it as ACF color field.
		// This is more reliable than trying to check ACF field types at runtime since ACF may not be loaded during server-side rendering.
		if ( str_starts_with( $name, 'custom_meta_' ) ) {
			return true;
		}

		// Check for legacy format: name is 'post_meta_key' with ACF field in settings.
		if ( 'post_meta_key' === $name ) {
			$selected_meta_key = $settings['select_meta_key'] ?? '';

			if ( str_starts_with( $selected_meta_key, 'custom_meta_' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve ACF color field value directly without HSL processing.
	 *
	 * ACF color picker fields return hex color values directly and should not be processed
	 * as global color variables with HSL adjustments.
	 *
	 * @since ??
	 *
	 * @param string $field_name The ACF field name (e.g., 'custom_meta_color_field').
	 * @param array  $settings   Dynamic content settings (may include before/after text).
	 *
	 * @return string The resolved ACF color field value.
	 */
	private static function _resolve_acf_color_field_value( $field_name, $settings = [] ) {
		// Extract the actual meta key based on the field name format.
		$meta_key = '';

		if ( str_starts_with( $field_name, 'custom_meta_' ) ) {
			// New format: remove 'custom_meta_' prefix.
			$meta_key = str_replace( 'custom_meta_', '', $field_name );
		} elseif ( 'post_meta_key' === $field_name ) {
			// Legacy format: extract from settings.
			$selected_meta_key = $settings['select_meta_key'] ?? '';

			if ( str_starts_with( $selected_meta_key, 'custom_meta_' ) ) {
				$meta_key = str_replace( 'custom_meta_', '', $selected_meta_key );
			} elseif ( ! empty( $settings['meta_key'] ) ) {
				$meta_key = $settings['meta_key'];
			}
		}

		if ( empty( $meta_key ) ) {
			return '';
		}

		// Get the post ID from global context (set by DynamicData processing) or current post.
		global $et_dynamic_data_post_id;
		$post_id = $et_dynamic_data_post_id ?? get_the_ID();

		if ( false === $post_id || 0 === $post_id || null === $post_id ) {
			// No valid post context in REST API or other contexts.
			// Return empty value since we can't resolve ACF values without a post ID.
			return '';
		}

		// Use ACF utils to get the meta value.
		$value = DynamicContentACFUtils::get_meta_value_by_type( 'post', $post_id, $meta_key );

		// Ensure we have a valid color value.
		if ( ! is_string( $value ) || empty( $value ) ) {
			$value = '';
		}

		// Add before/after text if specified.
		$before = isset( $settings['before'] ) ? $settings['before'] : '';
		$after  = isset( $settings['after'] ) ? $settings['after'] : '';

		return $before . $value . $after;
	}

	/**
	 * Helper function to resolve nested global colors and global variables to actual color values.
	 * This ensures SVG elements get concrete color values instead of CSS variables or variable syntax.
	 *
	 * Handles all global color formats including:
	 * - CSS variables: var(--gcid-xxx)
	 * - Variable syntax: $variable({"type":"color","value":{"name":"gcid-xxx","settings":{...}}})$
	 * - HSL with variables: hsl(from var(--gcid-xxx) calc(h + 0) calc(s + 0) calc(l + 0) / 0.2)
	 * - Nested global colors: Global colors that reference other global colors
	 * - Multiple levels of nesting with recursive resolution
	 *
	 * @param  string $color The input color value (could be global color ID, $variable syntax, CSS variable, or nested reference).
	 * @param  int    $depth Current recursion depth to prevent infinite loops.
	 * @return string The resolved concrete color value or original color if not a global color.
	 */
	public static function resolve_global_color_to_value( $color, $depth = 0 ) {
		// Input validation.
		if ( ! is_string( $color ) || empty( $color ) ) {
			return $color;
		}

		// Maximum recursion depth to prevent infinite loops.
		$max_depth = 10;

		if ( $depth >= $max_depth ) {
			return $color;
		}

		// Check if it's a global color (either CSS variable or $variable syntax).
		$global_color_id    = GlobalData::get_global_color_id_from_value( $color );
		$is_variable_syntax = str_starts_with( $color, '$variable(' ) && '$' === substr( $color, -1 );

		if ( ! $global_color_id && ! $is_variable_syntax ) {
			return $color; // Not a global color, return as-is.
		}

		// Resolve the global color variable (handles nested references).
		$resolved_color = GlobalData::resolve_global_color_variable( $color );

		// Safety check: ensure resolved color is valid.
		if ( ! is_string( $resolved_color ) || empty( $resolved_color ) ) {
			return $color;
		}

		// If still contains CSS variable, get the raw color value.
		if ( str_contains( $resolved_color, 'var(--' ) && $global_color_id ) {
			$color_data = GlobalData::get_global_color_by_id( $global_color_id );
			if ( is_array( $color_data ) && isset( $color_data['color'] ) && ! empty( $color_data['color'] ) ) {
				$resolved_color = $color_data['color'];
			}
		}

		// If still contains nested $variable syntax, resolve recursively.
		if ( str_contains( $resolved_color, '$variable(' ) ) {
			$resolved_color = self::resolve_global_color_to_value( $resolved_color, $depth + 1 );
		}

		// Return original color if resolution failed.
		return ( is_string( $resolved_color ) && ! empty( $resolved_color ) ) ? $resolved_color : $color;
	}
}
