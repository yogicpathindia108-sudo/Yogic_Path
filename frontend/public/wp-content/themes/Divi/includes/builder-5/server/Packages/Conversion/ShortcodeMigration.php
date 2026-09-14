<?php
/**
 * Migration: Migration Class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Conversion;

// phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames -- Reserved keywords used intentionally for clarity in utility functions.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET_Builder_Module_Settings_Migration;
use ET_Global_Settings;
use ET\Builder\Framework\Utility\ArrayUtility;
use ET\Builder\Framework\Utility\StringUtility;

/**
 * Handles Migration
 *
 * @since ??
 */
class ShortcodeMigration {
	/**
	 * Detect whether inner content contains non-shortcode content.
	 *
	 * @param string|null $content Inner content to inspect.
	 *
	 * @return bool
	 */
	private static function has_non_shortcode_content( ?string $content ): bool {
		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return false;
		}

		// Regex101 link: https://regex101.com/r/JZSiv6/1.
		$without_shortcodes = preg_replace( '/\[\/?[^\]]+\]/', '', $content );
		$without_shortcodes = is_string( $without_shortcodes ) ? trim( $without_shortcodes ) : '';

		return '' !== $without_shortcodes;
	}

	/**
	 * Parse shortcode attributes using WordPress core function.
	 *
	 * WordPress puts boolean attributes (e.g., `enabled` in `[tag enabled]`) as positional
	 * array entries. We convert them to named attributes for consistent handling.
	 *
	 * @since ??
	 *
	 * @param string $shortcode_atts The raw attribute text (without the shortcode name).
	 *
	 * @return array Parsed attributes.
	 */
	private static function _parse_shortcode_atts( string $shortcode_atts ): array {
		$shortcode_atts = trim( $shortcode_atts );

		if ( '' === $shortcode_atts ) {
			return [];
		}

		if ( function_exists( 'shortcode_parse_atts' ) ) {
			$atts = shortcode_parse_atts( $shortcode_atts );

			// Convert boolean attributes from positional to named.
			$positional_keys = [];
			foreach ( $atts as $key => $value ) {
				// This regex is same as the fallback regex except for the value part.
				if ( is_int( $key ) && is_string( $value ) && preg_match( '/^[a-zA-Z0-9_-]+$/', $value ) ) {
					$positional_keys[] = $key;
					$atts[ $value ]    = '';
				}
			}

			foreach ( $positional_keys as $key ) {
				unset( $atts[ $key ] );
			}

			return $atts;
		}

		// Fallback: basic parsing for environments where WordPress functions may not be available.
		$atts = [];
		// Regex101 link: https://regex101.com/r/2O9Tbz/1.
		if ( preg_match_all( '/([a-zA-Z0-9_-]+)="([^"]*)"/s', $shortcode_atts, $matches ) ) {
			$atts = array_combine( $matches[1], $matches[2] );
		}

		return $atts;
	}

	/**
	 * A callback function to determine if the module should be migrated.
	 *
	 * @since ??
	 *
	 * @param bool|null $should_handle The current value of the flag.
	 * @param string    $module_slug   The module slug.
	 *
	 * @return bool|null Should the attribute be removed?
	 */
	public static function should_handle_migration( $should_handle, string $module_slug ) {
		if ( str_starts_with( $module_slug, 'et_pb_' ) ) {
			return true;
		}

		return $should_handle;
	}

	/**
	 * Parses a string of content to extract shortcodes.
	 *
	 * @param string $content The content to parse.
	 * @param string $parent_address The address of the parent shortcode.
	 * @param string $remaining_content The non-shortcode residue that remains after parsing.
	 * @param bool   $preserve_mixed_content_nodes Whether mixed shortcode+raw content should be represented as parsed nodes plus raw segments.
	 *
	 * @return array
	 */
	public static function process_shortcode( string $content, string $parent_address = '', ?string &$remaining_content = null, bool $preserve_mixed_content_nodes = false ): array {
		// Regex101 link: https://regex101.com/r/Nolw0Z/4.
		// Updated to allow hyphens in shortcode names (WordPress standard).
		$pattern = '/^\[([a-zA-Z0-9_-]+)(.*?)(\/)?](?:(.*?)\[\/\1])?/s';
		$result  = [];
		$index   = 0;

		while ( preg_match( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			$shortcode_name  = $matches[1][0];
			$shortcode_atts  = trim( $matches[2][0] );
			$self_closing    = isset( $matches[3][0] ) && '/' === $matches[3][0];
			$has_closing_tag = isset( $matches[4][0] ); // Check if closing tag group exists, not if content is non-empty.
			$inner_content   = $self_closing ? null : ( $matches[4][0] ?? null );

			// Parse attributes into an associative array using WordPress core function.
			$atts = self::_parse_shortcode_atts( $shortcode_atts );

			// Construct the current address.
			$current_address = '' === $parent_address ? (string) $index : "{$parent_address}.{$index}";

			// Remove this match from the content for recursive parsing of siblings.
			$content = substr_replace(
				$content,
				'',
				$matches[0][1],
				strlen( $matches[0][0] )
			);

			// If not self-closing, recursively parse inner content for nested shortcodes.
			$parsed_inner            = null;
			$remaining_inner_content = '';
			if ( ! $self_closing && ! empty( $inner_content ) ) {
				$parsed_inner = self::process_shortcode( $inner_content, $current_address, $remaining_inner_content, $preserve_mixed_content_nodes );
			}

			$content_value = null;
			if ( ! $self_closing ) {
				$parsed_inner_count = is_array( $parsed_inner ) ? count( $parsed_inner ) : 0;
				$has_mixed_content  = 0 < $parsed_inner_count && self::has_non_shortcode_content( $remaining_inner_content );
				$should_use_parsed_inner = ! empty( $parsed_inner ) && ( ! $has_mixed_content || $preserve_mixed_content_nodes );

				if ( $should_use_parsed_inner ) {
					$content_value = $parsed_inner;
				} else {
					$content_value = $inner_content;
				}

				if ( $preserve_mixed_content_nodes && is_array( $content_value ) && '' !== $remaining_inner_content ) {
					$content_value[] = [
						'is_raw'  => true,
						'content' => $remaining_inner_content,
					];
				}
			}

			$result[] = [
				'index'           => $index,
				'address'         => $current_address,
				'name'            => $shortcode_name,
				'attributes'      => $atts,
				'content'         => $content_value,
				'self_closing'    => $self_closing,
				'has_closing_tag' => $has_closing_tag,
			];

			++$index;
		}

		$remaining_content = $content;

		return $result;
	}

	/**
	 * Normalize the value based on the given key.
	 *
	 * @param mixed  $value The value to be normalized.
	 * @param string $key The key used for normalization.
	 * @return mixed The normalized value.
	 */
	private static function _normalize_value( $value, string $key ) {
		$key_info = self::_key_info( $key );

		if ( StringUtility::ends_with( $key_info['baseKey'], '_icon' ) && StringUtility::starts_with( $value ?? '', '&amp;#x' ) ) {
			return str_replace( '&amp;#x', '&#x', $value );
		}

		return $value;
	}

	/**
	 * Retrieves information about a given key.
	 *
	 * This function determines the mode and base key for a given key based on certain suffixes.
	 * It caches the results to improve performance.
	 *
	 * @param string $key The key for which to retrieve information.
	 * @return array An array containing the mode, key, and base key for the given key.
	 */
	private static function _key_info( string $key ): array {
		static $cached = [];

		if ( isset( $cached[ $key ] ) ) {
			return $cached[ $key ];
		}

		if ( StringUtility::ends_with( $key, '_tablet' ) ) {
			$base_key = substr( $key, 0, -7 );

			$cached[ $key ] = [
				'mode'    => 'tablet',
				'key'     => $key,
				'baseKey' => $base_key,
			];

			return $cached[ $key ];
		}

		if ( StringUtility::ends_with( $key, '_phone' ) ) {
			$base_key = substr( $key, 0, -6 );

			$cached[ $key ] = [
				'mode'    => 'phone',
				'key'     => $key,
				'baseKey' => $base_key,
			];

			return $cached[ $key ];
		}

		if ( StringUtility::ends_with( $key, '__hover' ) ) {
			$base_key = substr( $key, 0, -7 );

			$cached[ $key ] = [
				'mode'    => 'hover',
				'key'     => $key,
				'baseKey' => $base_key,
			];

			return $cached[ $key ];
		}

		if ( StringUtility::ends_with( $key, '__focus' ) ) {
			$base_key = substr( $key, 0, -7 );

			$cached[ $key ] = [
				'mode'    => 'focus',
				'key'     => $key,
				'baseKey' => $base_key,
			];

			return $cached[ $key ];
		}

		if ( StringUtility::ends_with( $key, '__checked' ) ) {
			$base_key = substr( $key, 0, -9 );

			$cached[ $key ] = [
				'mode'    => 'checked',
				'key'     => $key,
				'baseKey' => $base_key,
			];

			return $cached[ $key ];
		}

		if ( StringUtility::ends_with( $key, '__active' ) ) {
			$base_key = substr( $key, 0, -8 );

			$cached[ $key ] = [
				'mode'    => 'active',
				'key'     => $key,
				'baseKey' => $base_key,
			];

			return $cached[ $key ];
		}

		if ( StringUtility::ends_with( $key, '__sticky' ) ) {
			$base_key = substr( $key, 0, -8 );

			$cached[ $key ] = [
				'mode'    => 'sticky',
				'key'     => $key,
				'baseKey' => $base_key,
			];

			return $cached[ $key ];
		}

		$cached[ $key ] = [
			'mode'    => 'desktop',
			'key'     => $key,
			'baseKey' => $key,
		];

		return $cached[ $key ];
	}

	/**
	 * Serializes an array of shortcodes into a string.
	 *
	 * @since ??
	 *
	 * @param array $shortcodes The shortcodes to serialize.
	 * @param bool  $maybe_global_presets_migration Whether to migrate global presets.
	 *
	 * @return string The serialized shortcodes.
	 */
	public static function process_to_shortcode( array $shortcodes, ?bool $maybe_global_presets_migration = false ): string {
		$result = '';

		foreach ( $shortcodes as $shortcode ) {
			if ( ! empty( $shortcode['is_raw'] ) ) {
				$result .= (string) ( $shortcode['content'] ?? '' );
				continue;
			}

			$name            = $shortcode['name'];
			$attributes      = $shortcode['attributes'] ?? [];
			$content         = $shortcode['content'] ?? null;
			$address         = $shortcode['address'] ?? '';
			$self_closing    = $shortcode['self_closing'] ?? false;
			$has_closing_tag = $shortcode['has_closing_tag'] ?? false;
			$content_for_filters = $content;

			// Keep migration callbacks compatible with legacy shortcode node arrays.
			// Raw passthrough segments are serializer-only artifacts and should not be
			// interpreted as shortcode nodes by attribute migration logic.
			if ( is_array( $content_for_filters ) ) {
				$content_for_filters = array_values(
					array_filter(
						$content_for_filters,
						function ( $item ) {
							return ! ( is_array( $item ) && ! empty( $item['is_raw'] ) );
						}
					)
				);
			}

			// Apply filters to migrate attributes.
			$migrated_attributes = apply_filters( 'et_pb_module_shortcode_attributes', $attributes, $attributes, $name, $address, $content_for_filters, $maybe_global_presets_migration );

			// Serialize attributes.
			$atts = '';

			foreach ( $migrated_attributes as $key => $value ) {
				$value_normalized = self::_normalize_value( $value, $key );

				// Skip if the value is a WP_Error instance.
				if ( is_wp_error( $value_normalized ) ) {
					continue;
				}

				$value_string = null === $value_normalized ? '' : (string) $value_normalized;

				// Use unquoted format for safe numeric values to preserve readability.
				if ( is_numeric( $value_string ) && ! preg_match( '/[\s\'"]/', $value_string ) ) {
					$atts .= sprintf( ' %s=%s', $key, $value_string );
				} else {
					// Intentionally did not apply esc_attr() to the attribute value as this is a migration.
					// The esc_attr will be applied when the shortcode is rendered.
					$atts .= sprintf( ' %s="%s"', $key, $value_string );
				}
			}

			// Format based on original shortcode structure.
			if ( $self_closing ) {
				// Originally self-closing: [tag attrs /].
				$result .= sprintf( '[%s%s /]', $name, $atts );
			} elseif ( $has_closing_tag ) {
				// Has content with closing tag: [tag attrs]content[/tag].
				if ( is_array( $content ) ) {
					// Recursively serialize nested content.
					$nested_content = self::process_to_shortcode( $content, $maybe_global_presets_migration );
					$result        .= sprintf( '[%s%s]%s[/%s]', $name, $atts, $nested_content, $name );
				} else {
					// Apply filters to migrate content.
					$migrated_content = apply_filters( 'et_pb_module_content', $content, $migrated_attributes, $attributes, $name, $address, '' );
					$result          .= sprintf( '[%s%s]%s[/%s]', $name, $atts, $migrated_content, $name );
				}
			} else {
				// No closing tag (simple format): [tag attrs].
				$result .= sprintf( '[%s%s]', $name, $atts );
			}
		}

		return $result;
	}

	/**
	 * Determines if a legacy shortcode needs to be migrated.
	 *
	 * @param string $shortcode The shortcode to check.
	 * @return bool Returns true if the shortcode needs to be migrated, false otherwise.
	 */
	public static function is_migrate_legacy_shortcode( string $shortcode ): bool {
		// Exit early if the content contains `wp:divi/shortcode-module` (indicating it's already a block)
		// or if no shortcode with the '[et_pb_' prefix is found (indicating there are no relevant shortcodes to process).
		if ( str_contains( $shortcode, 'wp:divi/shortcode-module' ) || ! str_contains( $shortcode, '[et_pb_' ) ) {
			return false;
		}

		// Define the regex pattern to match the '_builder_version' attribute.
		$regex_pattern = '/\_builder_version=\"(\d+\.\d+(\.\d+)?)\"/';

		// Use preg_match_all to find all matches of the regex pattern in the shortcode.
		preg_match_all( $regex_pattern, $shortcode, $matches, PREG_SET_ORDER, 0 );

		// If no matches are found, return false.
		if ( ! $matches ) {
			return false;
		}

		if ( ! class_exists( 'ET_Builder_Module_Settings_Migration' ) ) {
			require_once ET_BUILDER_DIR . 'module/settings/Migration.php';
		}

		// Get the versions array from the migrations array.
		$versions = array_keys( ET_Builder_Module_Settings_Migration::$migrations );

		if ( ! $versions ) {
			return false;
		}

		// Get the last version in the array.
		$versions_last = $versions[ count( $versions ) - 1 ];

		// Use ArrayUtility::find to check if any match requires shortcode migration.
		$found = ArrayUtility::find(
			$matches,
			function ( $match ) use ( $versions_last ) {
				return version_compare( $match[1], $versions_last, '<=' );
			}
		);

		// Return true if a match requiring migration is found, false otherwise.
		return null !== $found;
	}

	/**
	 * Checks if the given shortcode needs to be migrated and performs the migration if necessary.
	 *
	 * @param string $shortcode The shortcode to check and migrate.
	 * @param bool   $maybe_global_presets_migration Whether to migrate global presets.
	 *
	 * @return string The migrated shortcode, if migration is required; otherwise, the original shortcode.
	 */
	public static function maybe_migrate_legacy_shortcode( string $shortcode, ?bool $maybe_global_presets_migration = false ): string {
		// Check if the shortcode needs to be migrated.
		if ( ! self::is_migrate_legacy_shortcode( $shortcode ) ) {
			return $shortcode;
		}

		return self::migrate_legacy_shortcode( $shortcode, $maybe_global_presets_migration );
	}

	/**
	 * Migrates a legacy shortcode to the latest version.
	 *
	 * @param string $shortcode The shortcode to check and migrate.
	 * @param bool   $maybe_global_presets_migration Whether to migrate global presets.
	 *
	 * @return string The migrated shortcode, if migration is required; otherwise, the original shortcode.
	 */
	public static function migrate_legacy_shortcode( string $shortcode, ?bool $maybe_global_presets_migration = false ): string {
		add_filter( 'et_pb_should_handle_migration_pre', [ self::class, 'should_handle_migration' ], 10, 2 );

		// Load the shortcode framework.
		et_load_shortcode_framework();

		ET_Global_Settings::init();
		ET_Builder_Module_Settings_Migration::init();

		$remaining_content     = null;
		$parsed_shortcodes     = self::process_shortcode( $shortcode, '', $remaining_content, true );
		$serialized_shortcodes = self::process_to_shortcode( $parsed_shortcodes, $maybe_global_presets_migration );

		remove_filter( 'et_pb_should_handle_migration_pre', [ self::class, 'should_handle_migration' ], 10 );

		return $serialized_shortcodes;
	}
}
