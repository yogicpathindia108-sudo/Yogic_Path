<?php
/**
 * Module: DynamicContentFields::get_fields() method.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentFieldsTraits;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentFields;

trait GetFieldsTrait {

	/**
	 * Flag to prevent recursive calls to get_fields().
	 *
	 * @since ??
	 *
	 * @var bool
	 */
	private static $_is_getting_fields = false;

	/**
	 * Get dynamic content fields for the given post ID and context.
	 *
	 * This function runs the value through the `divi_module_dynamic_content_options` filter hook.
	 *
	 * @since ??
	 *
	 * @param int    $post_id Post Id.
	 * @param string $context Context e.g `edit`, `display`.
	 *
	 * @return array[] Dynamic content built-in and custom options.
	 */
	public static function get_fields( int $post_id, string $context ): array {
		// Prevent recursive calls to avoid infinite loops.
		if ( self::$_is_getting_fields ) {
			return [];
		}

		// Dynamic content options.
		$dynamic_content_options = [];

		// Type cast variable for the filter hooks.
		$post_id = (int) $post_id;
		$context = (string) $context;

		// Set flag to prevent recursion.
		self::$_is_getting_fields = true;

		try {
			/**
			 * This filter is documented in /builder-5/server/Packages/Module/Layout/Components/DynamicContent/DynamicContentOptionsTraits/GetOptionsTrait.php
			 *
			 * @ignore
			 */
			$dynamic_content_options = apply_filters( 'divi_module_dynamic_content_options', $dynamic_content_options, $post_id, $context );

			$all_fields     = (array) $dynamic_content_options;
			$all_field_keys = array_flip( array_keys( $all_fields ) );

			// Sort fields by group based on the existence `group` and the order of `id`.
			uasort(
				$all_fields,
				function ( $first_field, $second_field ) use ( $all_field_keys ) {
					return DynamicContentFields::get_sorted_fields_comparison_result( $first_field, $second_field, $all_field_keys );
				}
			);

			return $all_fields;
		} finally {
			// Always reset flag, even if an exception occurs.
			self::$_is_getting_fields = false;
		}
	}

	/**
	 * Check if get_fields() is currently being executed.
	 *
	 * This method is used to prevent recursive calls that could cause infinite loops.
	 *
	 * @since ??
	 *
	 * @return bool True if get_fields() is currently being executed, false otherwise.
	 */
	public static function is_getting_fields(): bool {
		return self::$_is_getting_fields;
	}
}
