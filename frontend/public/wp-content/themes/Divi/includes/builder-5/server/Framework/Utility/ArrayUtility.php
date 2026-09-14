<?php
/**
 * ArrayUtility class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Framework\Utility;

// phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames -- Reserved keywords used intentionally for clarity in utility functions.
use ET\Builder\Framework\Breakpoint\Breakpoint;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * ArrayUtility class.
 *
 * This class has helper methods to make working with arrays easier.
 *
 * @since ??
 */
class ArrayUtility {

	use ArrayUtilityTraits\GetValueTrait;
	use ArrayUtilityTraits\FindTrait;
	use ArrayUtilityTraits\DiffTrait;
	use ArrayUtilityTraits\IsListTrait;
	use ArrayUtilityTraits\IsAssocTrait;
	use ArrayUtilityTraits\FilterDeepTrait;
	use ArrayUtilityTraits\MapDeepTrait;

	/**
	 * Checks if a given variable is an array of strings.
	 *
	 * This function iterates over each element of the provided variable and verifies
	 * if it's a string. If any element is not a string the function returns `false`.
	 *
	 * @param mixed $var The variable to check.
	 *
	 * @return bool `true` if the variable is an array of strings, `false` otherwise.
	 */
	public static function is_array_of_strings( $var ) {
		if ( ! is_array( $var ) ) {
			return false;
		}

		foreach ( $var as $value ) {
			if ( ! is_string( $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Sets a value at a given path in a nested PHP array without using references.
	 *
	 * @since ??
	 *
	 * @param array $array The array to modify.
	 * @param array $path The path as a dot-separated string or an array of keys.
	 * @param mixed $value The value to set.
	 * @return array The modified array.
	 */
	public static function set_value( array $array, array $path, $value ): array {
		$result  = $array;
		$current = &$result;

		foreach ( $path as $key ) {
			if ( ! isset( $current[ $key ] ) || ! is_array( $current[ $key ] ) ) {
				$current[ $key ] = [];
			}

			$current = &$current[ $key ];
		}

		$current = $value;

		return $result;
	}

	/**
	 * Checks if path is a direct property of an array.
	 *
	 * @since ??
	 *
	 * @param array $data The array to check.
	 * @param array $path The path to check.
	 *
	 * @return bool Returns true if path exists, else false.
	 */
	public static function has( array $data, array $path ) {
		foreach ( $path as $key ) {
			if ( is_array( $data ) && array_key_exists( $key, $data ) ) {
				$data = $data[ $key ];
			} else {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get list of attribute field paths that should be merged instead of replaced.
	 *
	 * @since ??
	 *
	 * @return array List of field paths that need array merging.
	 *               Each entry contains 'path' and 'unique_keys' for deduplication.
	 */
	public static function get_mergeable_array_fields(): array {
		return [
			[
				'path'        => [ 'module', 'decoration', 'attributes' ],
				'unique_keys' => [ 'name', 'targetElement' ],
				'wrapper_key' => 'attributes', // Wrapped structure: { attributes: [...] }.
			],
			// Add more mergeable fields here as needed.
			// Example for wrapped structure:
			// [
			// 'path'        => [ 'module', 'advanced', 'classes' ],
			// 'unique_keys' => [ 'className' ],
			// 'wrapper_key' => 'classes', // Wrapped: { classes: [...] }.
			// ],
			// Example for direct array structure:
			// [
			// 'path'        => [ 'module', 'settings', 'permissions' ],
			// 'unique_keys' => [ 'permission' ],
			// 'wrapper_key' => null, // Direct array: [...].
			// ].
		];
	}

	/**
	 * Merges array fields that should be combined instead of replaced.
	 *
	 * This function combines arrays from multiple sources, preserving items from
	 * presets unless explicitly overridden by module-level values. Duplicates are
	 * identified by the specified unique keys.
	 *
	 * Merging logic:
	 * - Items with matching unique key values are considered duplicates.
	 * - Module-level items override preset items with matching unique keys for most attributes.
	 * - For `class` / `style`, duplicate unique-key rows merge values (space / semicolon) so
	 *   multi-entry Custom Attributes are not collapsed before AttributeUtils separation.
	 * - All unique items from all sources are preserved.
	 *
	 * Supports two structures:
	 * 1. Wrapped: { <wrapper_key>: [...] } - e.g., { attributes: [...] }
	 * 2. Direct: [...] - plain array of items
	 *
	 * @since ??
	 *
	 * @param array  $unique_keys List of keys to use for identifying duplicates.
	 * @param string $wrapper_key Optional key that wraps the array (e.g., 'attributes').
	 *                            If provided, expects structure { wrapper_key: [...] }.
	 *                            If null, expects direct array structure.
	 * @param array  ...$sources  Variable number of arrays to merge.
	 *
	 * @return array The merged array in the same structure as input.
	 */
	public static function merge_array_by_unique_keys( array $unique_keys, ?string $wrapper_key = null, ...$sources ): array {
		$merged_items      = [];
		$seen_combinations = [];

		// Process sources in reverse order (module → group preset → module preset).
		// so that later sources (module-level) override earlier ones (presets).
		$sources = array_reverse( $sources );

		foreach ( $sources as $source ) {
			// Skip empty sources.
			if ( empty( $source ) || ! is_array( $source ) ) {
				continue;
			}

			// Extract items array based on structure (wrapped or direct).
			if ( null !== $wrapper_key ) {
				// Wrapped structure: { wrapper_key: [...] }.
				$items_array = isset( $source[ $wrapper_key ] ) ? $source[ $wrapper_key ] : [];
			} else {
				// Direct array structure: [...].
				$items_array = $source;
			}

			if ( ! is_array( $items_array ) ) {
				continue;
			}

			// Walk each source list in reverse so later rows in one source keep their
			// natural precedence when duplicates collapse before final array_reverse().
			foreach ( array_reverse( $items_array ) as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				// Create a unique key based on the specified unique keys.
				$key_parts = [];
				foreach ( $unique_keys as $key ) {
					$key_parts[] = $item[ $key ] ?? '';
				}
				$combination_key = implode( '|', $key_parts );

				// First occurrence of this unique-key combination.
				if ( ! isset( $seen_combinations[ $combination_key ] ) ) {
					$merged_items[]                        = $item;
					$seen_combinations[ $combination_key ] = count( $merged_items ) - 1;
					continue;
				}

				// Same name/target (or other unique keys): class/style must merge values,
				// matching AttributeUtils::merge_attribute_values — otherwise multi-entry
				// Custom Attributes (e.g. class=blue then class=purple) collapse to one row
				// before separate_attributes_by_target_element can merge them.
				$existing_index = $seen_combinations[ $combination_key ];
				$attribute_name = $item['name'] ?? '';

				if ( 'class' === $attribute_name || 'style' === $attribute_name ) {
					$higher_priority_value = $merged_items[ $existing_index ]['value'] ?? '';
					$lower_priority_value  = $item['value'] ?? '';

					if ( 'class' === $attribute_name ) {
						$existing_classes = array_filter( preg_split( '/\s+/', (string) $lower_priority_value ), 'strlen' );
						$new_classes      = array_filter( preg_split( '/\s+/', (string) $higher_priority_value ), 'strlen' );
						$merged_items[ $existing_index ]['value'] = implode(
							' ',
							array_unique( array_merge( $existing_classes, $new_classes ) )
						);
					} else {
						// Lower-priority styles must come first so higher-priority declarations stay last.
						$existing_trimmed = rtrim( (string) $lower_priority_value, " \t\n\r\0\x0B;" );
						$new_trimmed      = rtrim( (string) $higher_priority_value, " \t\n\r\0\x0B;" );
						$style_values     = array_values(
							array_filter(
								[ $existing_trimmed, $new_trimmed ],
								static function ( string $value ): bool {
									return '' !== $value;
								}
							)
						);
						$merged_items[ $existing_index ]['value'] = empty( $style_values )
							? ''
							: implode( '; ', $style_values ) . ';';
					}
				}
				// Non-class/style: keep the first-seen item (module override over presets).
			}
		}

		// Reverse back to get original priority (module overrides presets).
		$merged_result = array_reverse( $merged_items );

		// Return in the same structure as input.
		if ( null !== $wrapper_key ) {
			return [ $wrapper_key => $merged_result ];
		}

		return $merged_result;
	}

	/**
	 * Apply custom merge logic to all mergeable fields in the attributes array.
	 *
	 * This function loops through all configured mergeable fields and applies
	 * the appropriate merge strategy based on unique keys. This ensures that
	 * array-based fields are properly combined instead of replaced.
	 * All mergeable fields are processed using responsive breakpoint paths.
	 *
	 * @since ??
	 *
	 * @param array $merged_attrs The base merged attributes array (result of array_replace_recursive).
	 * @param array $preset_attrs Attributes from module preset.
	 * @param array $group_attrs  Attributes from group preset.
	 * @param array $module_attrs Attributes from module level.
	 *
	 * @return array The attributes array with mergeable fields properly merged.
	 */
	public static function apply_mergeable_fields_logic(
		array $merged_attrs,
		array $preset_attrs,
		array $group_attrs,
		array $module_attrs
	): array {
		$mergeable_fields = self::get_mergeable_array_fields();

		// Get all breakpoint names for responsive field processing.
		$breakpoint_names = Breakpoint::get_all_breakpoint_names();

		foreach ( $mergeable_fields as $field_config ) {
			$field_path  = $field_config['path'];
			$unique_keys = $field_config['unique_keys'];
			$wrapper_key = $field_config['wrapper_key'] ?? null;

			// Loop through ALL breakpoints to ensure values at each breakpoint are properly merged.
			foreach ( $breakpoint_names as $breakpoint ) {
				$full_path = array_merge( $field_path, [ $breakpoint, 'value' ] );

				$preset_values = self::get_value_by_array_path( $preset_attrs, $full_path, [] );
				$group_values  = self::get_value_by_array_path( $group_attrs, $full_path, [] );
				$module_values = self::get_value_by_array_path( $module_attrs, $full_path, [] );

				if ( ! empty( $preset_values ) || ! empty( $group_values ) || ! empty( $module_values ) ) {
					$merged_values = self::merge_array_by_unique_keys(
						$unique_keys,
						$wrapper_key,
						$preset_values,
						$group_values,
						$module_values
					);
					$merged_attrs  = self::set_value( $merged_attrs, $full_path, $merged_values );
				}
			}
		}

		return $merged_attrs;
	}

	/**
	 * Sorts an array using a user-defined comparison function.
	 *
	 * This method creates a copy of the input array and sorts it using `usort`
	 * with the provided callback function. The original array is not modified.
	 *
	 * @since ??
	 *
	 * @param array    $array    The array to sort.
	 * @param callable $callback  The comparison function to use for sorting.
	 *                           Must return an integer less than, equal to, or greater than zero
	 *                           if the first argument is considered to be respectively less than,
	 *                           equal to, or greater than the second.
	 *
	 * @return array The sorted array.
	 */
	public static function sort( array $array, callable $callback ): array {
		$sorted = $array;
		usort( $sorted, $callback );
		return $sorted;
	}
}
