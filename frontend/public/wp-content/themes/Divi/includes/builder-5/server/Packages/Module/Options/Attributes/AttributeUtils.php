<?php
/**
 * Attributes Utils Class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Attributes;

use ET\Builder\Framework\Utility\HTMLUtility;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * AttributeUtils class.
 *
 * This class provides utility methods for processing module attributes.
 *
 * @since ??
 */
class AttributeUtils {

	/**
	 * Merge attribute values when there's a collision between existing and custom attributes.
	 * Handles different attribute types with appropriate merging strategies.
	 *
	 * @since ??
	 *
	 * @param string $attribute_name The name of the attribute.
	 * @param string $existing_value The existing attribute value.
	 * @param string $new_value The new custom attribute value.
	 *
	 * @return string The merged attribute value.
	 */
	public static function merge_attribute_values( $attribute_name, $existing_value, $new_value ) {
		switch ( $attribute_name ) {
			case 'class':
				// For class attribute, deduplicate individual class names.
				$existing_classes = array_filter( preg_split( '/\s+/', $existing_value ), 'strlen' );
				$new_classes      = array_filter( preg_split( '/\s+/', $new_value ), 'strlen' );
				$all_classes      = array_merge( $existing_classes, $new_classes );
				$unique_classes   = array_unique( $all_classes );
				return implode( ' ', $unique_classes );

			case 'style':
				// For style attribute, merge with semicolon separation.
				$existing_trimmed = rtrim( $existing_value, '; ' );
				$new_trimmed      = rtrim( $new_value, '; ' );
				return $existing_trimmed . '; ' . $new_trimmed . ';';

			default:
				// For other attributes, new value takes precedence (override).
				return $new_value;
		}
	}

	/**
	 * Separate attributes by their target element for output/rendering.
	 *
	 * This function takes custom attributes data and organizes it by the target element
	 * specified in each attribute. Attributes without a targetElement specified
	 * or with an empty targetElement are considered for the main module container.
	 *
	 * Note: This function returns raw attribute names and values. Escaping is handled
	 * by HTMLUtility::render_attributes() using AttributeUtils escape functions as sanitizers.
	 * Attributes should already be sanitized during save via AttributeSecurity.
	 *
	 * @since ??
	 *
	 * @param array $custom_attributes_data The custom attributes data array.
	 *
	 * @return array Associative array where keys are element names and values are attribute arrays.
	 *               The main module uses 'main' as the key.
	 */
	public static function separate_attributes_by_target_element( array $custom_attributes_data ): array {

		$separated_attributes = [
			'main' => [], // Main module container.
		];

		// Extract attributes from either simple or responsive structure.
		$attributes_list = $custom_attributes_data['desktop']['value']['attributes'] ?? $custom_attributes_data['attributes'] ?? [];

		if ( is_array( $attributes_list ) ) {
			foreach ( $attributes_list as $attribute ) {
				if ( ! is_array( $attribute ) && ! is_object( $attribute ) ) {
					continue;
				}

				// Convert to array if needed.
				if ( is_object( $attribute ) ) {
					$attribute = (array) $attribute;
				}

				// Get attribute details.
				$name           = $attribute['name'] ?? '';
				$value          = $attribute['value'] ?? '';
				$target_element = $attribute['targetElement'] ?? '';
				$is_boolean     = self::_is_boolean_attribute( $name );

				// Skip if no name, or if non-boolean value is empty.
				if ( empty( $name ) || ( '' === $value && ! $is_boolean ) ) {
					continue;
				}

				if ( $is_boolean ) {
					$value = '';
				}

				// Normalize target element - treat empty string as 'main' for backward compatibility.
				if ( empty( $target_element ) ) {
					$target_element = 'main';
				}

				// Ensure target element key exists.
				if ( ! isset( $separated_attributes[ $target_element ] ) ) {
					$separated_attributes[ $target_element ] = [];
				}

				// Add to appropriate target element group.
				// Note: These will be escaped later by HTMLUtility::render_attributes() using our AttributeUtils functions.
				// Same-name entries in one list must merge (class/style) or override (other attrs),
				// not last-wins overwrite — see merge_attribute_values().
				if ( isset( $separated_attributes[ $target_element ][ $name ] ) ) {
					$separated_attributes[ $target_element ][ $name ] = self::merge_attribute_values(
						$name,
						$separated_attributes[ $target_element ][ $name ],
						$value
					);
				} else {
					$separated_attributes[ $target_element ][ $name ] = $value;
				}
			}
		}

		return $separated_attributes;
	}

	/**
	 * Check whether the provided attribute is a boolean HTML attribute.
	 *
	 * @since ??
	 *
	 * @param string $attribute_name Attribute name.
	 *
	 * @return bool
	 */
	private static function _is_boolean_attribute( $attribute_name ): bool {
		static $boolean_cache = [];

		$attribute_name = strtolower( (string) $attribute_name );

		if ( isset( $boolean_cache[ $attribute_name ] ) ) {
			return $boolean_cache[ $attribute_name ];
		}

		$attribute_details                = HTMLUtility::get_attribute_details( $attribute_name );
		$boolean_cache[ $attribute_name ] = true === ( $attribute_details['booleanAttribute'] ?? false );

		return $boolean_cache[ $attribute_name ];
	}
}
