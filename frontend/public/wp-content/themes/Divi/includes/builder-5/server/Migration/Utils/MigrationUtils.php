<?php
/**
 * Migration Utilities
 *
 * Shared utility functions for migration classes.
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Migration\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\Conversion\Utils\ConversionUtils;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\BlockParser\BlockParserBlock;
use ET\Builder\Framework\Utility\StringUtility;

/**
 * Migration Utilities Class.
 *
 * @since ??
 */
class MigrationUtils {

	/**
	 * Cache of parsed flat objects keyed by serialized content hash.
	 *
	 * @since ??
	 *
	 * @var array<string, array>
	 */
	private static $_flat_objects_cache = [];

	/**
	 * Maximum number of cached flat object entries.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	private const MAX_FLAT_OBJECT_CACHE_ENTRIES = 40;

	/**
	 * Whether shared flat-object migration pipeline is active.
	 *
	 * @since ??
	 *
	 * @var bool
	 */
	private static $_shared_pipeline_active = false;

	/**
	 * Shared pipeline source content.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_shared_pipeline_source_content = '';

	/**
	 * Shared pipeline flat objects.
	 *
	 * @since ??
	 *
	 * @var array|null
	 */
	private static $_shared_pipeline_flat_objects = null;

	/**
	 * Shared pipeline dirty flag.
	 *
	 * @since ??
	 *
	 * @var bool
	 */
	private static $_shared_pipeline_dirty = false;


	/**
	 * Get current post content.
	 *
	 * @since ??
	 *
	 * @return string|null Post content or null if not in post context.
	 */
	public static function get_current_content(): ?string {
		global $post;
		return $post instanceof \WP_Post ? get_the_content( null, false, $post ) : null;
	}

	/**
	 * Ensure content is wrapped with wp:divi/placeholder block.
	 *
	 * This is needed because later `MigrationUtils::serialize_blocks` is used and it uses
	 * `get_comment_delimited_block_content()` that will skip the direct child of `divi/root`, causing $content
	 * without placeholder block to be broken: only the first row gets rendered, the rest is gone.
	 *
	 * @since ??
	 *
	 * @param string $content The content to wrap.
	 *
	 * @return string The wrapped content.
	 */
	public static function ensure_placeholder_wrapper( string $content ): string {
		$is_wrapped = '' !== $content &&
		(bool) preg_match( '/^<!--\s+wp:divi\/placeholder(?:\s+\{[^}]*\})?\s+-->/', $content ) &&
		str_contains( $content, '<!-- /wp:divi/placeholder -->' );

		if ( ! $is_wrapped ) {
			$content = "<!-- wp:divi/placeholder -->\n" . $content . "\n<!-- /wp:divi/placeholder -->";
		}

		return $content;
	}

	/**
	 * Convert flat module objects back to block array structure.
	 *
	 * @since ??
	 *
	 * @param  array $flat_objects The flat module objects.
	 * @return array The block array structure.
	 */
	public static function flat_objects_to_blocks( array $flat_objects ): array {
		// Find the root object.
		$root = null;
		foreach ( $flat_objects as $object ) {
			if ( isset( $object['parent'] ) && ( null === $object['parent'] || 'root' === $object['parent'] ) ) {
				$root = $object;
				break;
			}
		}
		if ( ! $root ) {
			return [];
		}
		return array_map(
			function ( $child_id ) use ( $flat_objects ) {
				return self::build_block_from_flat( $child_id, $flat_objects );
			},
			$root['children']
		);
	}

	/**
	 * Recursively build a block from a flat object.
	 *
	 * @since ??
	 *
	 * @param  string $id           The object ID.
	 * @param  array  $flat_objects The flat module objects.
	 * @return array The block array.
	 */
	public static function build_block_from_flat( string $id, array $flat_objects ): array {
		$object = $flat_objects[ $id ];
		$block  = [
			'blockName'    => $object['name'],
			'attrs'        => $object['props']['attrs'] ?? [],
			'innerBlocks'  => [],
			'innerContent' => [],
		];
		if ( ! empty( $object['children'] ) ) {
			foreach ( $object['children'] as $child_id ) {
				$block['innerBlocks'][]  = self::build_block_from_flat( $child_id, $flat_objects );
				$block['innerContent'][] = null; // Placeholder, will be filled by serializer.
			}
		}
		if ( isset( $object['props']['innerHTML'] ) ) {
			$block['innerContent'][] = $object['props']['innerHTML'];
		}
		return $block;
	}

	/**
	 * Get parent column type for a module.
	 *
	 * Module hierarchy is typically: Column → Parent Module → Child Module
	 * So we traverse to the grandparent (column) to get the column type.
	 *
	 * @since ??
	 *
	 * @param array $module_data  The child module data.
	 * @param array $flat_objects All flat module objects.
	 *
	 * @return string|null The parent column type or null if not found.
	 */
	public static function get_parent_column_type( array $module_data, array $flat_objects ): ?string {
		// Get parent (parent module).
		$parent_id = $module_data['parent'] ?? null;
		if ( ! $parent_id || ! isset( $flat_objects[ $parent_id ] ) ) {
			return null;
		}

		// Get grandparent (column).
		$parent_module  = $flat_objects[ $parent_id ];
		$grandparent_id = $parent_module['parent'] ?? null;
		if ( ! $grandparent_id || ! isset( $flat_objects[ $grandparent_id ] ) ) {
			return null;
		}

		$grandparent_module = $flat_objects[ $grandparent_id ];
		if ( in_array( $grandparent_module['name'], [ 'divi/column', 'divi/column-inner' ], true ) ) {
			// First check for old type attribute.
			$column_type = $grandparent_module['props']['attrs']['module']['advanced']['type']['desktop']['value'] ?? null;

			// If not found, check for flexType and map it back to old column type.
			if ( ! $column_type ) {
				// Check new location first.
				$flex_type = $grandparent_module['props']['attrs']['module']['decoration']['sizing']['desktop']['value']['flexType'] ?? null;

				// Fallback to old location for backwards compatibility during migration.
				if ( ! $flex_type ) {
					$flex_type = $grandparent_module['props']['attrs']['module']['advanced']['flexType']['desktop']['value'] ?? null;
				}

				$column_type = self::map_flex_type_to_column_type( $flex_type );
			}

			return $column_type;
		}

		return null;
	}

	/**
	 * Map flexType values back to old column type system.
	 *
	 * @since ??
	 *
	 * @param string|null $flex_type The flexType value.
	 *
	 * @return string|null The corresponding old column type.
	 */
	public static function map_flex_type_to_column_type( ?string $flex_type ): ?string {
		if ( ! $flex_type ) {
			return null;
		}

		// Map flexType values to old column types.
		// phpcs:disable Universal.Arrays.DuplicateArrayKey.Found -- String keys for column width fractions misread as integers.
		$flex_to_column_map = [
			'24_24' => '4_4',   // 100% width
			'18_24' => '3_4',   // 75% width
			'16_24' => '2_3',   // 66.67% width
			'14_24' => '7_12',  // 58.33% width
			'12_24' => '1_2',   // 50% width
			'10_24' => '5_12',  // 41.67% width
			'9_24'  => '3_8',   // 37.5% width
			'8_24'  => '1_3',   // 33.33% width
			'6_24'  => '1_4',   // 25% width
			'4_24'  => '1_6',   // 16.67% width
			'3_24'  => '1_8',   // 12.5% width
		// Add more mappings as needed
		];
		// phpcs:enable

		return $flex_to_column_map[ $flex_type ] ?? null;
	}

	/**
	 * Map parent column type to module column breakdown (grid column count).
	 *
	 * Both block column types (4_4, 3_4, 2_3, etc.) and flex column types (24_24, 18_24, etc.)
	 * are mapped to appropriate grid column counts based on their width.
	 * Narrower columns should display fewer items per row.
	 *
	 * Different modules have different default breakdowns:
	 * - Blog: 3 columns in full width (4_4)
	 * - Gallery/Portfolio: 4 columns in full width (4_4)
	 *
	 * This mapping handles all column types that might exist from both old and new column systems.
	 *
	 * @since ??
	 *
	 * @param string      $column_type The column type (e.g., '4_4', '1_2', '24_24', '12_24').
	 * @param string|null $module_name Optional. The module name to apply module-specific defaults (e.g., 'divi/blog').
	 *
	 * @return int The number of columns for the grid.
	 */
	public static function map_parent_column_to_module_column_breakdown( string $column_type, ?string $module_name = null ): int {
		// Determine if this is a Blog module (which has 3 columns in full width).
		$is_blog_module = 'divi/blog' === $module_name;

		// Map column types to grid column counts.
		// Wider columns can accommodate more items per row.
		// This includes both block column format (4_4, 3_4, etc.) and flex column format (24_24, 18_24, etc.).
		// phpcs:disable Universal.Arrays.DuplicateArrayKey.Found -- String keys for column width fractions misread as integers.
		$column_to_grid_map = [
			// Block column format (commonly used).
			'4_4'   => $is_blog_module ? 3 : 4, // 100% width: 3 columns (Blog) or 4 columns (Gallery/Portfolio).
			'3_4'   => $is_blog_module ? 2 : 3, // 75% width: 3 columns.
			'2_3'   => 2, // 66.67% width: 3 columns.
			'7_12'  => 2, // 58.33% width: 2 columns.
			'1_2'   => 2, // 50% width: 2 columns.
			'5_12'  => 2, // 41.67% width: 2 columns.
			'3_8'   => 2, // 37.5% width: 2 columns.
			'1_3'   => 1, // 33.33% width: 1 column.
			'1_4'   => 1, // 25% width: 1 column.
			'1_5'   => 1, // 20% width: 1 column.
			'1_6'   => 1, // 16.67% width: 1 column.
			'1_7'   => 1, // 14.29% width: 1 column.
			'1_8'   => 1, // 12.5% width: 1 column.
			'1_9'   => 1, // 11.11% width: 1 column.
			'1_10'  => 1, // 10% width: 1 column.
			'1_11'  => 1, // 9.09% width: 1 column.

		// Flex column format (24-column grid system).
			'24_24' => $is_blog_module ? 3 : 4, // 100% width: 3 columns (Blog) or 4 columns (Gallery/Portfolio).
			'23_24' => $is_blog_module ? 3 : 4, // 95.83% width: 3 columns (Blog) or 4 columns (Gallery/Portfolio).
			'22_24' => $is_blog_module ? 3 : 4, // 91.67% width: 3 columns (Blog) or 4 columns (Gallery/Portfolio).
			'21_24' => $is_blog_module ? 3 : 4, // 87.5% width: 3 columns (Blog) or 4 columns (Gallery/Portfolio).
			'20_24' => $is_blog_module ? 3 : 4, // 83.33% width: 3 columns (Blog) or 4 columns (Gallery/Portfolio).
			'19_24' => 3, // 79.17% width: 3 columns.
			'18_24' => 3, // 75% width: 3 columns.
			'17_24' => 3, // 70.83% width: 3 columns.
			'16_24' => 2, // 66.67% width: 3 columns.
			'15_24' => 2, // 62.5% width: 2 columns.
			'14_24' => 2, // 58.33% width: 2 columns.
			'13_24' => 2, // 54.17% width: 2 columns.
			'12_24' => 2, // 50% width: 2 columns.
			'11_24' => 2, // 45.83% width: 2 columns.
			'10_24' => 2, // 41.67% width: 2 columns.
			'9_24'  => 2, // 37.5% width: 2 columns.
			'8_24'  => 1, // 33.33% width: 1 column.
			'7_24'  => 1, // 29.17% width: 1 column.
			'6_24'  => 1, // 25% width: 1 column.
			'5_24'  => 1, // 20.83% width: 1 column.
			'4_24'  => 1, // 16.67% width: 1 column.
			'3_24'  => 1, // 12.5% width: 1 column.
			'2_24'  => 1, // 8.33% width: 1 column.
			'1_24'  => 1, // 4.17% width: 1 column.

		// Additional fractional formats.
			'3_5'   => 2, // 60% width: 2 columns.
			'2_5'   => 2, // 40% width: 2 columns.
		];
		// phpcs:enable

		// Default to 4 columns for Gallery/Portfolio, 3 for Blog.
		$default = $is_blog_module ? 3 : 4;

		return $column_to_grid_map[ $column_type ] ?? $default;
	}

	/**
	 * Map flexType value to grid column count.
	 *
	 * FlexType values represent the fraction of 24 columns.
	 * The column count represents how many items fit per row at that width.
	 * For example: 12_24 means each item is 12/24 (50%) of the row width,
	 * so 2 items fit per row (24 / 12 = 2).
	 *
	 * This mapping is derived from the source of truth in:
	 * visual-builder/packages/field-library/src/components/select-column-class/components.tsx
	 *
	 * @since ??
	 *
	 * @param string|null $flex_type The flexType value.
	 *
	 * @return int The number of columns (defaults to 4 if not found).
	 */
	public static function map_flex_type_to_column_count( ?string $flex_type ): int {
		if ( ! $flex_type ) {
			return 4; // Default to 4 columns when no flexType is assigned.
		}

		// Map flexType values to column counts.
		// The column count is calculated as: 24 / numerator (rounded to nearest integer).
		// This represents how many items of that width fit in a 24-column grid row.
		//
		// Source of truth: select-column-class component in field-library.
		// phpcs:disable Universal.Arrays.DuplicateArrayKey.Found -- String keys for column width fractions misread as integers.
		$flex_to_column_map = [
			// Common column widths (from 'common' group).
			'24_24' => 1,  // 100% width (Fullwidth) = 1 column per row.
			'18_24' => 1,  // 75% width (3/4) = 1.33 columns → 1 column per row.
			'16_24' => 2,  // 66.67% width (2/3) = 1.5 columns → 2 columns per row.
			'3_5'   => 2,  // 60% width (3/5) = 1.67 columns → 2 columns per row.
			'12_24' => 2,  // 50% width (1/2) = 2 columns per row.
			'2_5'   => 2,  // 40% width (2/5) = 2.5 columns → 2 columns per row.
			'8_24'  => 3,  // 33.33% width (1/3) = 3 columns per row.
			'6_24'  => 4,  // 25% width (1/4) = 4 columns per row.
			'1_5'   => 5,  // 20% width (1/5) = 5 columns per row.
			'4_24'  => 6,  // 16.67% width (1/6) = 6 columns per row.
			'1_7'   => 7,  // 14.29% width (1/7) = 7 columns per row.
			'3_24'  => 8,  // 12.5% width (1/8) = 8 columns per row.

		// Other column widths (from 'other' group).
			'23_24' => 1,  // 95.83% width (23/24) = 1.04 columns → 1 column per row.
			'22_24' => 1,  // 91.67% width (22/24) = 1.09 columns → 1 column per row.
			'21_24' => 1,  // 87.5% width (21/24) = 1.14 columns → 1 column per row.
			'20_24' => 1,  // 83.33% width (20/24) = 1.2 columns → 1 column per row.
			'19_24' => 1,  // 79.17% width (19/24) = 1.26 columns → 1 column per row.
			'17_24' => 1,  // 70.83% width (17/24) = 1.41 columns → 1 column per row.
			'15_24' => 2,  // 62.5% width (15/24) = 1.6 columns → 2 columns per row.
			'14_24' => 2,  // 58.33% width (14/24) = 1.71 columns → 2 columns per row.
			'13_24' => 2,  // 54.17% width (13/24) = 1.85 columns → 2 columns per row.
			'11_24' => 2,  // 45.83% width (11/24) = 2.18 columns → 2 columns per row.
			'10_24' => 2,  // 41.67% width (10/24) = 2.4 columns → 2 columns per row.
			'9_24'  => 3,  // 37.5% width (9/24) = 2.67 columns → 3 columns per row.
			'7_24'  => 3,  // 29.17% width (7/24) = 3.43 columns → 3 columns per row.
			'5_24'  => 5,  // 20.83% width (5/24) = 4.8 columns → 5 columns per row.
			'1_9'   => 9,  // 11.11% width (1/9) = 9 columns per row.
			'1_10'  => 10, // 10% width (1/10) = 10 columns per row.
			'1_11'  => 11, // 9.09% width (1/11) = 11 columns per row.
			'2_24'  => 12, // 8.33% width (1/12) = 12 columns per row.
			'1_24'  => 24, // 4.17% width (1/24) = 24 columns per row.
		];
		// phpcs:enable

		if ( isset( $flex_to_column_map[ $flex_type ] ) ) {
			return $flex_to_column_map[ $flex_type ];
		}

		// If not in the map, calculate dynamically.
		// Parse flexType format: "numerator_denominator".
		if ( preg_match( '/^(\d+)_(\d+)$/', $flex_type, $matches ) ) {
			$numerator   = (int) $matches[1];
			$denominator = (int) $matches[2];

			// Calculate column count: denominator / numerator (rounding to nearest integer).
			if ( $numerator > 0 && $denominator > 0 ) {
				return max( 1, (int) round( $denominator / $numerator ) );
			}
		}

		// Default fallback to 4 columns.
		return 4;
	}

	/**
	 * Get parent column type from shortcode context.
	 *
	 * For shortcodes, the hierarchy is typically:
	 * Column -> Parent Module -> Child Module
	 * So we need to find the grandparent (column) via the parent module.
	 *
	 * @since ??
	 *
	 * @param array $shortcode      The shortcode data.
	 * @param array $all_shortcodes All shortcodes array for context.
	 *
	 * @return string|null The parent column type or null if not found.
	 */
	public static function get_parent_column_type_from_shortcode( array $shortcode, array $all_shortcodes ): ?string {
		// Find the parent module in the nested shortcode structure.
		$current_context = $all_shortcodes;

		// Recursively search for this shortcode within the shortcode hierarchy.
		// to determine its parent column.
		$parent_column_type = self::find_parent_column_in_shortcodes( $shortcode, $current_context );

		return $parent_column_type;
	}

	/**
	 * Recursively find parent column type in shortcode hierarchy.
	 *
	 * @since ??
	 *
	 * @param array $target_shortcode The shortcode to find parent for.
	 * @param array $shortcodes       The shortcodes to search in.
	 * @param array $parent_stack     Stack of parent shortcodes.
	 *
	 * @return string|null The parent column type or null if not found.
	 */
	public static function find_parent_column_in_shortcodes( array $target_shortcode, array $shortcodes, array $parent_stack = [] ): ?string {
		foreach ( $shortcodes as $shortcode ) {
			// Check if this is a column.
			if ( in_array( $shortcode['name'], [ 'et_pb_column', 'et_pb_column_inner' ], true ) ) {
				// Check if target shortcode is nested within this column.
				if ( self::is_shortcode_nested_in( $target_shortcode, $shortcode ) ) {
					return $shortcode['attributes']['type'] ?? null;
				}
			}

			// If this shortcode has nested content, search recursively.
			if ( isset( $shortcode['content'] ) && is_array( $shortcode['content'] ) ) {
				$new_parent_stack = array_merge( $parent_stack, [ $shortcode ] );
				$result           = self::find_parent_column_in_shortcodes( $target_shortcode, $shortcode['content'], $new_parent_stack );
				if ( $result ) {
					return $result;
				}
			}
		}

		return null;
	}

	/**
	 * Check if a target shortcode is nested within a parent shortcode.
	 *
	 * @since ??
	 *
	 * @param array $target_shortcode The shortcode to find.
	 * @param array $parent_shortcode The parent shortcode to search in.
	 *
	 * @return bool True if target is nested within parent.
	 */
	public static function is_shortcode_nested_in( array $target_shortcode, array $parent_shortcode ): bool {
		// If parent has no content, target can't be nested.
		if ( ! isset( $parent_shortcode['content'] ) || ! is_array( $parent_shortcode['content'] ) ) {
			return false;
		}

		// Search through all nested content.
		foreach ( $parent_shortcode['content'] as $child_shortcode ) {
			// Direct match.
			if ( $child_shortcode === $target_shortcode ) {
				return true;
			}

			// Check if target is nested deeper.
			if ( isset( $child_shortcode['content'] ) && is_array( $child_shortcode['content'] ) ) {
				if ( self::is_shortcode_nested_in( $target_shortcode, $child_shortcode ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Serialize an array of blocks into a string.
	 *
	 * This function takes an array of blocks and converts them into a concatenated string representation.
	 * Each block in the array is individually serialized using the `serialize_block` method and then
	 * joined together without any separators to form the final output.
	 *
	 * @since ??
	 *
	 * @param array $blocks Array of blocks to be serialized.
	 *
	 * @return string The serialized blocks as a concatenated string.
	 *
	 * @example
	 * ```php
	 * $blocks = [
	 *     [
	 *         'blockName' => 'core/paragraph',
	 *         'attrs' => ['content' => 'Hello World'],
	 *         'innerBlocks' => [],
	 *         'innerContent' => ['Hello World']
	 *     ],
	 *     [
	 *         'blockName' => 'core/heading',
	 *         'attrs' => ['level' => 2, 'content' => 'My Heading'],
	 *         'innerBlocks' => [],
	 *         'innerContent' => ['My Heading']
	 *     ]
	 * ];
	 *
	 * $serialized = MigrationUtils::serialize_blocks($blocks);
	 *
	 * // Output: Concatenated string of all serialized blocks
	 * ```
	 */
	public static function serialize_blocks( array $blocks ): string {
		return implode( '', array_map( [ __CLASS__, 'serialize_block' ], $blocks ) );
	}

	/**
	 * Serialize flat module objects and cache them for downstream migrations.
	 *
	 * @since ??
	 *
	 * @param array $flat_objects Flat module objects.
	 *
	 * @return string Serialized content.
	 */
	public static function serialize_flat_objects( array $flat_objects ): string {
		if ( self::$_shared_pipeline_active ) {
			self::$_shared_pipeline_flat_objects = $flat_objects;
			self::$_shared_pipeline_dirty        = true;
			return self::$_shared_pipeline_source_content;
		}

		$content = self::serialize_blocks( self::flat_objects_to_blocks( $flat_objects ) );

		self::_cache_flat_objects_for_content( $content, $flat_objects );

		return $content;
	}

	/**
	 * Serialize a single block into a string.
	 *
	 * This function takes a single block array and converts it into its serialized string representation.
	 * The function processes the block's inner content, recursively serializing any nested inner blocks,
	 * and handles block attributes by removing empty array attributes. The final output is generated
	 * using WordPress's `get_comment_delimited_block_content` function.
	 *
	 * @since ??
	 *
	 * @param array $block {
	 *                     The block to be serialized.
	 *
	 * @type string $blockName     The name of the block (e.g., 'core/paragraph', 'divi/text').
	 * @type array  $attrs         Optional. The block attributes. Default empty array.
	 * @type array  $innerBlocks   Optional. Array of nested blocks. Default empty array.
	 * @type array  $innerContent  Optional. Array of content chunks, can contain strings or references to inner blocks.
	 * }
	 *
	 * @return string The serialized block as a comment-delimited string.
	 *
	 * @example
	 * ```php
	 * $block = [
	 *     'blockName' => 'core/paragraph',
	 *     'attrs' => [
	 *         'content' => 'Hello World',
	 *         'className' => 'my-paragraph'
	 *     ],
	 *     'innerBlocks' => [],
	 *     'innerContent' => ['Hello World']
	 * ];
	 *
	 * $serialized = MigrationUtils::serialize_block($block);
	 *
	 * // Output: <!-- wp:core/paragraph {"content":"Hello World","className":"my-paragraph"} -->
	 * //         Hello World
	 * //         <!-- /wp:core/paragraph -->
	 * ```
	 */
	public static function serialize_block( array $block ): string {
		$block_content = '';

		$index = 0;

		foreach ( $block['innerContent'] as $chunk ) {
			$block_content .= is_string( $chunk ) ? $chunk : self::serialize_block( $block['innerBlocks'][ $index++ ] );
		}

		if ( ! isset( $block['attrs'] ) || ! is_array( $block['attrs'] ) ) {
			$block['attrs'] = [];
		}

		if ( ! empty( $block['attrs'] ) ) {
			$block['attrs'] = ModuleUtils::remove_empty_array_attributes( $block['attrs'] );
		}

		return get_comment_delimited_block_content(
			$block['blockName'],
			$block['attrs'],
			$block_content
		);
	}

	/**
	 * Parse serialized post content into a flat module object structure for migration purposes.
	 *
	 * This method sets up a temporary layout context for the migration process, parses the serialized
	 * post content into a flat associative array of module objects, and then cleans up the context
	 * to prevent conflicts with subsequent rendering operations.
	 *
	 * The method is primarily used by migration classes (such as GlobalColorMigration and FlexboxMigration)
	 * to convert legacy serialized post data into the new flat module object format required by Divi 5.
	 *
	 * @since ??
	 *
	 * @param string $content        The serialized post content to parse. This should contain the raw
	 *                               serialized data from the database that represents the post's
	 *                               module structure.
	 * @param string $migration_name The name of the migration being performed. This is used to
	 *                               identify the layout context during parsing and should typically
	 *                               match the migration class name (e.g., 'GlobalColorMigration').
	 *
	 * @return array A flat associative array where keys are module IDs and values are module objects.
	 *               Each module object contains:
	 *               - 'id': The unique identifier for the module
	 *               - 'name': The module type/name (e.g., 'divi/row', 'divi/column')
	 *               - 'props': Module properties including attributes and settings
	 *               - 'children': Array of child module IDs (if any)
	 *               - 'parent': Parent module ID (if not root)
	 *               The array also includes a root module with ID 'root' that serves as the
	 *               top-level container for all other modules.
	 *
	 * @example
	 * ```php
	 * $content = get_post_meta($post_id, '_et_pb_old_content', true);
	 * $flat_objects = MigrationUtils::parseSerializedPostIntoFlatModuleObject($content, 'GlobalColorMigration');
	 *
	 * // Access root module
	 * $root = $flat_objects['root'];
	 *
	 * // Access specific module by ID
	 * $module = $flat_objects['some-module-id'];
	 * ```
	 *
	 * @see ConversionUtils::parseSerializedPostIntoFlatModuleObject() The underlying conversion method
	 * @see BlockParserStore::set_layout() For layout context management
	 * @see BlockParserStore::reset_layout() For layout cleanup
	 * @see BlockParserBlock::reset_order_index() For order index cleanup
	 */
	public static function parse_serialized_post_into_flat_module_object( string $content, string $migration_name ): array {
		if ( self::$_shared_pipeline_active && is_array( self::$_shared_pipeline_flat_objects ) ) {
			return self::$_shared_pipeline_flat_objects;
		}

		$cache_key           = self::_get_content_cache_key( $content );
		$cached_flat_objects = self::$_flat_objects_cache[ $cache_key ] ?? null;

		if ( is_array( $cached_flat_objects ) ) {
			return $cached_flat_objects;
		}

		BlockParserStore::set_layout(
			[
				'id'   => $migration_name,
				'type' => 'migration',
			]
		);

		$flat_objects = ConversionUtils::parseSerializedPostIntoFlatModuleObject( $content );

		// Reset the block parser store and order index to avoid conflicts with rendering.
		BlockParserBlock::reset_order_index();

		BlockParserStore::reset_layout();

		self::_cache_flat_objects_for_content( $content, $flat_objects );

		return $flat_objects;
	}

	/**
	 * Begin shared flat-object migration pipeline.
	 *
	 * @since ??
	 *
	 * @param string $content Source content.
	 *
	 * @return void
	 */
	public static function begin_shared_pipeline( string $content ): void {
		self::$_shared_pipeline_active         = true;
		self::$_shared_pipeline_source_content = $content;
		self::$_shared_pipeline_flat_objects   = null;
		self::$_shared_pipeline_dirty          = false;
	}

	/**
	 * Finalize shared pipeline and return final content.
	 *
	 * @since ??
	 *
	 * @param string $fallback_content Fallback content when no changes occurred.
	 *
	 * @return string
	 */
	public static function finalize_shared_pipeline( string $fallback_content ): string {
		if ( ! self::$_shared_pipeline_active ) {
			return $fallback_content;
		}

		$final_content = $fallback_content;

		if ( self::$_shared_pipeline_dirty && is_array( self::$_shared_pipeline_flat_objects ) ) {
			$final_content = self::serialize_blocks( self::flat_objects_to_blocks( self::$_shared_pipeline_flat_objects ) );
			self::_cache_flat_objects_for_content( $final_content, self::$_shared_pipeline_flat_objects );
		}

		self::$_shared_pipeline_active         = false;
		self::$_shared_pipeline_source_content = '';
		self::$_shared_pipeline_flat_objects   = null;
		self::$_shared_pipeline_dirty          = false;

		return $final_content;
	}

	/**
	 * Create a stable cache key for serialized content.
	 *
	 * @since ??
	 *
	 * @param string $content Serialized content.
	 *
	 * @return string Cache key.
	 */
	private static function _get_content_cache_key( string $content ): string {
		return md5( $content );
	}

	/**
	 * Cache flat objects for serialized content.
	 *
	 * @since ??
	 *
	 * @param string $content      Serialized content.
	 * @param array  $flat_objects Flat module objects.
	 *
	 * @return void
	 */
	private static function _cache_flat_objects_for_content( string $content, array $flat_objects ): void {
		// Cache exact content form.
		self::_set_flat_objects_cache_entry( $content, $flat_objects );

		// Cache wrapped form to support migrations that wrap before parsing.
		$wrapped_content = self::ensure_placeholder_wrapper( $content );
		self::_set_flat_objects_cache_entry( $wrapped_content, $flat_objects );

		// Cache unwrapped form to support migrations that parse raw content.
		$unwrapped_content = self::_strip_placeholder_wrapper( $content );
		if ( $unwrapped_content !== $content ) {
			self::_set_flat_objects_cache_entry( $unwrapped_content, $flat_objects );
		}
	}

	/**
	 * Set a single flat-object cache entry.
	 *
	 * @since ??
	 *
	 * @param string $content      Serialized content.
	 * @param array  $flat_objects Flat module objects.
	 *
	 * @return void
	 */
	private static function _set_flat_objects_cache_entry( string $content, array $flat_objects ): void {
		$cache_key = self::_get_content_cache_key( $content );

		if ( isset( self::$_flat_objects_cache[ $cache_key ] ) ) {
			unset( self::$_flat_objects_cache[ $cache_key ] );
		}

		self::$_flat_objects_cache[ $cache_key ] = $flat_objects;

		if ( count( self::$_flat_objects_cache ) <= self::MAX_FLAT_OBJECT_CACHE_ENTRIES ) {
			return;
		}

		reset( self::$_flat_objects_cache );
		$oldest_cache_key = key( self::$_flat_objects_cache );

		if ( is_string( $oldest_cache_key ) ) {
			unset( self::$_flat_objects_cache[ $oldest_cache_key ] );
		}
	}

	/**
	 * Strip placeholder wrapper from content when present.
	 *
	 * @since ??
	 *
	 * @param string $content Serialized content.
	 *
	 * @return string Unwrapped content.
	 */
	private static function _strip_placeholder_wrapper( string $content ): string {
		$prefix = '<!-- wp:divi/placeholder -->';
		$suffix = '<!-- /wp:divi/placeholder -->';

		if (
			! str_starts_with( $content, $prefix )
			|| ! str_ends_with( $content, $suffix )
		) {
			return $content;
		}

		$unwrapped = substr( $content, strlen( $prefix ) );
		if ( false === $unwrapped ) {
			return $content;
		}

		$unwrapped = substr( $unwrapped, 0, -strlen( $suffix ) );
		if ( false === $unwrapped ) {
			return $content;
		}

		return trim( $unwrapped );
	}

	/**
	 * Sort modules by depth (deepest first) for bottom-up processing.
	 *
	 * This utility is useful for migrations that need to process modules in a specific
	 * order based on their nesting level, ensuring that closer parents override values
	 * set by distant parents.
	 *
	 * @since ??
	 *
	 * @param array    $flat_objects All flat module objects.
	 * @param string[] $module_names Array of module names to filter by (e.g., ['divi/row', 'divi/row-inner']).
	 *                               If empty, all modules are included.
	 *
	 * @return array Array of module IDs sorted by depth (deepest first).
	 */
	public static function sort_modules_by_depth( array $flat_objects, array $module_names = [] ): array {
		$modules_with_depth = [];

		// Find matching modules and calculate their depth.
		foreach ( $flat_objects as $module_id => $module_data ) {
			// Skip if module names filter is provided and this module doesn't match.
			if ( ! empty( $module_names ) ) {
				$module_name = $module_data['name'] ?? '';
				if ( ! in_array( $module_name, $module_names, true ) ) {
					continue;
				}
			}

			$depth                = self::calculate_module_depth( $module_id, $flat_objects );
			$modules_with_depth[] = [
				'id'    => $module_id,
				'depth' => $depth,
			];
		}

		// Sort by depth (deepest first = highest depth number first).
		usort(
			$modules_with_depth,
			function ( $a, $b ) {
				return $b['depth'] - $a['depth'];
			}
		);

		// Extract just the IDs.
		return array_map(
			function ( $module ) {
				return $module['id'];
			},
			$modules_with_depth
		);
	}

	/**
	 * Calculate the depth of a module in the tree.
	 *
	 * The depth is determined by counting the number of parent modules
	 * from the given module up to the root.
	 *
	 * @since ??
	 *
	 * @param string $module_id    The module ID.
	 * @param array  $flat_objects All flat module objects.
	 *
	 * @return int The depth (0 for root-level modules).
	 */
	public static function calculate_module_depth( string $module_id, array $flat_objects ): int {
		$depth      = 0;
		$current_id = $module_id;

		// Traverse up the parent chain.
		while ( isset( $flat_objects[ $current_id ]['parent'] ) ) {
			$parent_id = $flat_objects[ $current_id ]['parent'];

			// Stop if we've reached the root or if parent doesn't exist.
			if ( null === $parent_id || 'root' === $parent_id || ! isset( $flat_objects[ $parent_id ] ) ) {
				break;
			}

			++$depth;
			$current_id = $parent_id;
		}

		return $depth;
	}

	/**
	 * Check if content needs migration based on module versions and presence.
	 *
	 * This is a fast pre-check using regex and string searches without expensive full parsing.
	 *
	 * Two-stage bailout:
	 * 1. Version check: If we find ANY module with version >= release_version, skip migration.
	 * 2. Module check: If specific modules are provided and NONE are present, skip migration.
	 *
	 * The logic: If a page has been opened in VB and saved after this migration was released,
	 * at least one module will have the new version, proving the migration already ran.
	 *
	 * @since ??
	 *
	 * @param string   $content            The content to check.
	 * @param string   $release_version    The migration's release version.
	 * @param string[] $block_modules      Optional. Array of block module names to check (e.g., ['divi/section', 'divi/row']).
	 * @param string[] $shortcode_modules  Optional. Array of shortcode module names to check (e.g., ['et_pb_section', 'et_pb_row']).
	 *
	 * @return bool True if content needs migration (all versions old or uncertain),
	 *              false if migration not needed (found >= 1 module with current version OR no target modules present).
	 */
	public static function content_needs_migration(
		string $content,
		string $release_version,
		array $block_modules = [],
		array $shortcode_modules = []
	): bool {
		// Empty content doesn't need migration.
		if ( empty( $content ) || '<!-- wp:divi/placeholder /-->' === $content ) {
			return false;
		}

		// Stage 1: Version-based bailout.
		// If content doesn't contain builderVersion at all, it needs migration.
		if ( str_contains( $content, '"builderVersion":"' ) ) {
			// Extract all builderVersion values from content.
			if ( preg_match_all( '/"builderVersion":"([^"]+)"/', $content, $matches ) ) {
				// Check if ANY version is >= release_version.
				// If we find even ONE up-to-date module, the migration already ran.
				foreach ( $matches[1] as $version ) {
					if ( StringUtility::version_compare( $version, $release_version, '>=' ) ) {
						// Found at least one module with new version, migration already ran.
						return false;
					}
				}
			}
		}

		// Stage 2: Module presence bailout.
		// If specific modules are provided, check if ANY of them exist in content.
		if ( ! empty( $block_modules ) || ! empty( $shortcode_modules ) ) {
			$has_target_module = false;

			// Check for block modules.
			foreach ( $block_modules as $module_name ) {
				if ( str_contains( $content, $module_name ) ) {
					$has_target_module = true;
					break;
				}
			}

			// If no block modules found, check for shortcode modules.
			if ( ! $has_target_module ) {
				foreach ( $shortcode_modules as $module_name ) {
					if ( str_contains( $content, '[' . $module_name ) ) {
						$has_target_module = true;
						break;
					}
				}
			}

			// If no target modules found, skip migration.
			if ( ! $has_target_module ) {
				return false;
			}
		}

		// Content has old versions and (if specified) contains target modules.
		return true;
	}

	/**
	 * Check whether module has legacy Woo field label attrs.
	 *
	 * @since ??
	 *
	 * @param string $module_name Module name.
	 *
	 * @return bool True when module should run Woo field-label migrations.
	 */
	public static function is_woocommerce_field_labels_legacy_module( string $module_name ): bool {
		return in_array(
			$module_name,
			[
				'divi/woocommerce-product-add-to-cart',
				'divi/woocommerce-checkout-shipping',
				'divi/woocommerce-checkout-additional-info',
				'divi/woocommerce-checkout-information',
				'divi/woocommerce-checkout-billing',
				'divi/woocommerce-cart-notice',
			],
			true
		);
	}

	/**
	 * Check whether module has legacy Woo required indicator color attr.
	 *
	 * @since ??
	 *
	 * @param string $module_name Module name.
	 *
	 * @return bool True when module should run required indicator color migration checks.
	 */
	public static function is_woocommerce_required_field_indicator_color_legacy_module( string $module_name ): bool {
		return in_array(
			$module_name,
			[
				'divi/woocommerce-checkout-shipping',
				'divi/woocommerce-checkout-billing',
				'divi/woocommerce-cart-notice',
			],
			true
		);
	}

	/**
	 * Get legacy Woo field-label group preset keys to be remapped.
	 *
	 * @since ??
	 *
	 * @return array<int, string> Legacy group preset keys.
	 */
	public static function get_woocommerce_legacy_field_labels_group_preset_keys(): array {
		return [
			'fieldLabels.decoration.font',
			'designFieldLabel',
			'designFieldLabels',
		];
	}

	/**
	 * Normalize preset stack value into an array.
	 *
	 * @since ??
	 *
	 * @param mixed $preset_value Preset stack value.
	 *
	 * @return array<int, string> Normalized preset IDs.
	 */
	public static function normalize_preset_stack_value( $preset_value ): array {
		if ( is_string( $preset_value ) ) {
			$trimmed_value = trim( $preset_value );
			return '' === $trimmed_value ? [] : [ $trimmed_value ];
		}

		if ( ! is_array( $preset_value ) ) {
			return [];
		}

		return array_values(
			array_filter(
				array_map(
					static function ( $preset_id ) {
						return is_string( $preset_id ) ? trim( $preset_id ) : '';
					},
					$preset_value
				),
				static function ( string $preset_id ): bool {
					return '' !== $preset_id;
				}
			)
		);
	}
}
