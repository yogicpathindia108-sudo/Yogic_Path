<?php
/**
 * Module Library: Shared trait for form-field variant preset map utilities.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Shared helper methods for duplicating and filtering form-field variant preset maps.
 *
 * @since ??
 */
trait FormFieldVariantPresetMapTrait {

	/**
	 * Duplicate map entries by replacing key and attrName prefixes.
	 *
	 * @since ??
	 *
	 * @param array  $map           Preset attrs map.
	 * @param string $source_prefix Source key/attrName prefix.
	 * @param string $target_prefix Target key/attrName prefix.
	 *
	 * @return array
	 */
	private static function _duplicate_map_entries_by_prefix( array $map, string $source_prefix, string $target_prefix ): array {
		$duplicated_map = [];

		foreach ( $map as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			$mapped_value = $value;
			$attr_name    = is_array( $mapped_value ) && is_string( $mapped_value['attrName'] ?? '' )
				? $mapped_value['attrName']
				: '';
			$key_matches  = str_starts_with( $key, $source_prefix );
			$attr_matches = str_starts_with( $attr_name, $source_prefix );

			if ( ! $key_matches && ! $attr_matches ) {
				continue;
			}

			$mapped_key = $key_matches
				? $target_prefix . substr( $key, strlen( $source_prefix ) )
				: $key;

			if ( $attr_matches ) {
				$mapped_value['attrName'] = $target_prefix . substr( $attr_name, strlen( $source_prefix ) );
			}

			$duplicated_map[ $mapped_key ] = $mapped_value;
		}

		return $duplicated_map;
	}

	/**
	 * Filter unsupported keys from a form-field variant preset map.
	 *
	 * @since ??
	 *
	 * @param array  $map                   Variant preset attrs map.
	 * @param string $variant_prefix        Variant key prefix.
	 * @param bool   $exclude_heading_level Whether `font__headingLevel` should be filtered out.
	 *
	 * @return array
	 */
	private static function _filter_form_field_variant_map(
		array $map,
		string $variant_prefix,
		bool $exclude_heading_level = false
	): array {
		$filtered_map = [];

		foreach ( $map as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			$is_unsupported_advanced_key         = str_starts_with( $key, "{$variant_prefix}advanced." );
			$is_unsupported_label_font_key       = str_starts_with( $key, "{$variant_prefix}decoration.labelFont." );
			$is_unsupported_placeholder_font_key = str_starts_with( $key, "{$variant_prefix}decoration.placeholderFont." );
			$is_unsupported_background_key       = "{$variant_prefix}decoration.background__backgroundColor" === $key;
			$is_unsupported_heading_level_key    = $exclude_heading_level && "{$variant_prefix}decoration.font.font__headingLevel" === $key;

			if (
				$is_unsupported_advanced_key
				|| $is_unsupported_label_font_key
				|| $is_unsupported_placeholder_font_key
				|| $is_unsupported_background_key
				|| $is_unsupported_heading_level_key
			) {
				continue;
			}

			$filtered_map[ $key ] = $value;
		}

		return $filtered_map;
	}
}
