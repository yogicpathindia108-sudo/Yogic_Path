<?php
/**
 * Module Utils: Children Utilities
 *
 * Helper utilities for modules that support the Children feature.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleUtils;

use WP_Block;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * ChildrenUtils class.
 *
 * Provides utility methods for modules that support child modules
 * through the Children feature (childrenName: [] in metadata).
 *
 * @since ??
 */
class ChildrenUtils {

	/**
	 * Extract child module IDs from a block's innerBlocks.
	 *
	 * This is a helper method to avoid repeating the same array_map logic
	 * in every module that supports children.
	 *
	 * @since ??
	 *
	 * @param WP_Block $block The block object.
	 *
	 * @return array Array of child module IDs.
	 *
	 * @example
	 * ```php
	 * $children_ids = ChildrenUtils::extract_children_ids( $block );
	 * ```
	 */
	public static function extract_children_ids( WP_Block $block ): array {
		if ( ! isset( $block->parsed_block['innerBlocks'] ) || ! is_array( $block->parsed_block['innerBlocks'] ) ) {
			return [];
		}

		return array_map(
			function ( $inner_block ) {
				return $inner_block['id'];
			},
			$block->parsed_block['innerBlocks']
		);
	}

	/**
	 * Check if a module supports the Children feature.
	 *
	 * A module supports children if it has childrenName defined (usually as an empty array
	 * to indicate it accepts any module type).
	 *
	 * @since ??
	 *
	 * @param string $module_name The module name (e.g., 'divi/blurb').
	 *
	 * @return bool True if the module supports children, false otherwise.
	 *
	 * @example
	 * ```php
	 * if ( ChildrenUtils::supports_children( 'divi/blurb' ) ) {
	 *     // Handle children rendering
	 * }
	 * ```
	 */
	public static function supports_children( string $module_name ): bool {
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( $module_name );

		if ( ! $block_type ) {
			return false;
		}

		// Check if children_name is defined in the block type.
		// For the Children feature, this is typically an empty array.
		return isset( $block_type->children_name );
	}
}
