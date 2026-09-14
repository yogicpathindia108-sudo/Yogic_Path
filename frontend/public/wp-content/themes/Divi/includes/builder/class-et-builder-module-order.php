<?php
/**
 * Module Order class.
 *
 * @since 5.0.0
 * @subpackage Builder
 * @package    Divi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Module Order Manager Class
 *
 * Manages module order indexes for both Divi 4 and Divi 5, ensuring they stay in sync.
 * This class serves as a central place to manage module order indexes that both
 * the legacy D4 ET_Builder_Element and D5 BlockParserBlock can use.
 *
 * @since 5.0.0
 */
class ET_Builder_Module_Order {

	/**
	 * Constants for index types
	 *
	 * @since 5.0.0
	 */
	const INDEX_SECTION            = 'section';
	const INDEX_ROW                = 'row';
	const INDEX_ROW_INNER          = 'row_inner';
	const INDEX_COLUMN             = 'column';
	const INDEX_COLUMN_INNER       = 'column_inner';
	const INDEX_MODULE             = 'module';
	const INDEX_MODULE_ITEM        = 'module_item';
	const INDEX_MODULE_ORDER       = 'module_order';
	const INDEX_INNER_MODULE_ORDER = 'inner_module_order';

	/**
	 * Static index storage
	 *
	 * Format: [
	 *   'default' => [
	 *     'module_order' => ['et_pb_section' => 1, 'et_pb_row' => 2, ...],
	 *     'inner_module_order' => ['et_pb_column' => 3, ...],
	 *   ],
	 *   'et_header_layout' => [...],
	 *   'et_body_layout' => [...],
	 *   'et_footer_layout' => [...],
	 * ]
	 *
	 * @since 5.0.0
	 *
	 * @var array
	 */
	protected static $_indices = [];

	/**
	 * Reset all indexes across all layout types
	 *
	 * @return void
	 * @since 5.0.0
	 */
	public static function reset_all_indexes() {
		foreach ( array_keys( self::$_indices ) as $layout_type ) {
			self::reset_indexes( $layout_type );
		}
	}

	/**
	 * Get the current layout type
	 *
	 * @return string The current layout type ('default', 'et_header_layout', etc.)
	 * @since 5.0.0
	 */
	public static function get_layout_type() {
		$layout_type = 'default';

		// Check for Theme Builder layout type if the class exists.
		if ( class_exists( 'ET_Theme_Builder_Layout' ) && method_exists( 'ET_Theme_Builder_Layout', 'get_theme_builder_layout_type' ) ) {
			$layout_type = ET_Theme_Builder_Layout::get_theme_builder_layout_type();
		}

		// Also check for WP Editor template if needed.
		if ( 'default' === $layout_type && method_exists( 'ET\Builder\FrontEnd\Assets\StaticCSS', 'get_wp_editor_template_type' ) ) {
			$wp_editor_type = \ET\Builder\FrontEnd\Assets\StaticCSS::get_wp_editor_template_type( true );

			if ( $wp_editor_type ) {
				$layout_type = $wp_editor_type;
			}
		}

		return $layout_type;
	}

	/**
	 * Set a module index
	 *
	 * @param string $index_type  Type of index (module_order, inner_module_order, section, row,
	 *                            etc.).
	 * @param string $module_slug The module slug to set the index for (only for module_order and
	 *                            inner_module_order).
	 * @param int    $index       The index value.
	 * @param string $layout_type Optional layout type. Default to current layout.
	 *
	 * @return void
	 * @since 5.0.0
	 */
	public static function set_index( $index_type, $module_slug, $index, $layout_type = null ) {
		if ( null === $layout_type ) {
			$layout_type = self::get_layout_type();
		}

		$is_module_order_index = in_array( $index_type, [ self::INDEX_MODULE_ORDER, self::INDEX_INNER_MODULE_ORDER ], true )
			&& is_string( $module_slug )
			&& '' !== $module_slug;

		if ( $is_module_order_index ) {
			$index = apply_filters(
				'et_builder_module_order_adjusted_index',
				$index,
				$index_type,
				$module_slug,
				$layout_type
			);
		}

		// Initialize layout type if it doesn't exist.
		if ( ! isset( self::$_indices[ $layout_type ] ) ) {
			self::$_indices[ $layout_type ] = array(
				'section'            => -1,
				'row'                => -1,
				'row_inner'          => -1,
				'column'             => -1,
				'column_inner'       => -1,
				'module'             => -1,
				'module_item'        => -1,
				'module_order'       => [],
				'inner_module_order' => [],
			);
		}

		// For module_order and inner_module_order, we need to initialize the array if it doesn't exist.
		if ( in_array( $index_type, [ self::INDEX_MODULE_ORDER, self::INDEX_INNER_MODULE_ORDER ], true ) ) {
			if ( ! isset( self::$_indices[ $layout_type ][ $index_type ] ) ) {
				self::$_indices[ $layout_type ][ $index_type ] = [];
			}

			// Set the index in the array.
			self::$_indices[ $layout_type ][ $index_type ][ $module_slug ] = $index;
		} else {
			// For other index types, just set the value directly.
			self::$_indices[ $layout_type ][ $index_type ] = $index;
		}
	}

	/**
	 * Get a module index
	 *
	 * @param string $index_type  Type of index (module_order, inner_module_order, section, row,
	 *                            etc.).
	 * @param string $module_slug The module slug to get the index for (only for module_order and
	 *                            inner_module_order).
	 * @param string $layout_type Optional layout type. Default to current layout.
	 *
	 * @return int|array The current index or -1 if not found.
	 * @since 5.0.0
	 */
	public static function get_index( $index_type, $module_slug = null, $layout_type = null ) {
		if ( null === $layout_type ) {
			$layout_type = self::get_layout_type();
		}

		// If the layout type doesn't exist, return -1.
		if ( ! isset( self::$_indices[ $layout_type ] ) ) {
			return -1;
		}

		// For module_order and inner_module_order, we need to check the module slug.
		if ( in_array( $index_type, [ self::INDEX_MODULE_ORDER, self::INDEX_INNER_MODULE_ORDER ], true ) ) {
			if ( null === $module_slug ) {
				return -1;
			}

			// If we don't have an index yet for this module slug, return -1.
			if ( ! isset( self::$_indices[ $layout_type ][ $index_type ][ $module_slug ] ) ) {
				return -1;
			}

			return self::$_indices[ $layout_type ][ $index_type ][ $module_slug ];
		} else {
			// For other index types, just return the value directly.
			return self::$_indices[ $layout_type ][ $index_type ] ?? -1;
		}
	}

	/**
	 * Increment a module index
	 *
	 * @param string $index_type  Type of index (module_order, inner_module_order, section, row,
	 *                            etc.).
	 * @param string $module_slug The module slug to increment the index for (only for
	 *                            module_order and inner_module_order).
	 * @param string $layout_type Optional layout type. Default to current layout.
	 *
	 * @return int The new incremented index.
	 * @since 5.0.0
	 */
	public static function increment_index( $index_type, $module_slug = null, $layout_type = null ) {
		if ( null === $layout_type ) {
			$layout_type = self::get_layout_type();
		}

		$current   = self::get_index( $index_type, $module_slug, $layout_type );
		$new_index = $current + 1;

		self::set_index( $index_type, $module_slug, $new_index, $layout_type );

		return $new_index;
	}

	/**
	 * Reset indexes for a specific layout type
	 *
	 * @param string $layout_type Optional layout type. Default to current layout.
	 *
	 * @return void
	 * @since 5.0.0
	 */
	public static function reset_indexes( $layout_type = null ) {
		if ( null === $layout_type ) {
			$layout_type = self::get_layout_type();
		}

		// Reset indexes for this layout type.
		if ( isset( self::$_indices[ $layout_type ] ) ) {
			self::$_indices[ $layout_type ] = array(
				'section'            => -1,
				'row'                => -1,
				'row_inner'          => -1,
				'column'             => -1,
				'column_inner'       => -1,
				'module'             => -1,
				'module_item'        => -1,
				'module_order'       => [],
				'inner_module_order' => [],
			);
		}
	}

	/**
	 * Convert a block name to a module slug
	 *
	 * @param string $block_name Block name (e.g., 'divi/accordion').
	 *
	 * @return string Module slug (e.g., 'et_pb_accordion').
	 * @since 5.0.0
	 */
	public static function block_name_to_module_slug( $block_name ) {
		return str_replace( 'divi/', 'et_pb_', $block_name );
	}

	/**
	 * Convert a module slug to a block name
	 *
	 * @param string $module_slug Module slug (e.g., 'et_pb_accordion').
	 *
	 * @return string Block name (e.g., 'divi/accordion').
	 * @since 5.0.0
	 */
	public static function module_slug_to_block_name( $module_slug ) {
		return str_replace( 'et_pb_', 'divi/', $module_slug );
	}
}
