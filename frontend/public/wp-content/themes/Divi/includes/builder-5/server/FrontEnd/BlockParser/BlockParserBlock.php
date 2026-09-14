<?php
/**
 * Class BlockParserBlock
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\FrontEnd\BlockParser;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\Packages\GlobalData\GlobalPreset;

// phpcs:disable WordPress.NamingConventions.ValidVariableName -- Matches WordPress block parser conventions.

/**
 * Class BlockParserBlock
 *
 * Holds the block structure in memory
 *
 * @since ??
 */
class BlockParserBlock extends \WP_Block_Parser_Block {

	/**
	 * It's a static variable that is used to keep track of the index of each block.
	 *
	 * @since ??
	 *
	 * @var integer
	 */
	private static $_index = -1;

	/**
	 * The order index of the block.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidVariableName -- Matches WordPress block parser conventions.
	// phpcs:ignore Squiz.Commenting.VariableComment.Missing -- Doc comment is present above.
	public $orderIndex;

	/**
	 * The index of the block that will be used to sort blocks list.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	public $index;

	/**
	 * The unique ID of the block.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	public $id;

	/**
	 * The parent ID of the block.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidVariableName -- Matches WordPress block parser conventions.
	// phpcs:ignore Squiz.Commenting.VariableComment.Missing -- Doc comment is present above.
	public $parentId;

	/**
	 * The BlockParserStore class instance where this block stored
	 *
	 * @since ??
	 *
	 * @var int
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidVariableName -- Matches WordPress block parser conventions.
	// phpcs:ignore Squiz.Commenting.VariableComment.Missing -- Doc comment is present above.
	public $storeInstance;

	/**
	 * List of inner blocks (of this same class)
	 *
	 * @since ??
	 *
	 * @var BlockParserBlock[]
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidVariableName -- Matches WordPress block parser conventions.
	// phpcs:ignore Squiz.Commenting.VariableComment.Missing -- Doc comment is present above.
	public $innerBlocks;

	/**
	 * Layout type where this block is being rendered.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	public $layout_type;

	/**
	 * Placeholder for merged attributes.
	 *
	 * @var array
	 */
	private $_merged_attrs;

	/**
	 * Create an instance of `BlockParserBlock`.
	 *
	 * Will populate object properties from the provided arguments.
	 *
	 * @since ??
	 *
	 * @param string $name           Name of block.
	 * @param array  $attrs          Optional set of attributes from block comment delimiters.
	 * @param array  $inner_blocks   List of inner blocks (of this same class: `BlockParserBlock`).
	 * @param string $inner_html     Resultant HTML from inside block comment delimiters after removing inner blocks.
	 * @param array  $inner_content  List of string fragments and null markers where inner blocks were found.
	 * @param int    $store_instance The store instance where this block will be stored.
	 * @param string $parent_id      Optional. The parent ID of the block. Default `divi/root`.
	 * @param string $layout_type    Optional. The layout type of the block. Default `default`.
	 */
	public function __construct( $name, $attrs, $inner_blocks, $inner_html, $inner_content, $store_instance, $parent_id = 'divi/root', $layout_type = 'default' ) {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName -- Matches WordPress block parser conventions.
		$this->blockName = $name;
		$this->attrs     = $attrs;
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName -- Matches WordPress block parser conventions.
		$this->innerBlocks = $inner_blocks;
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName -- Matches WordPress block parser conventions.
		$this->innerHTML = $inner_html;
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName -- Matches WordPress block parser conventions.
		$this->innerContent = $inner_content;
		$this->layout_type  = in_array( $layout_type, BlockParserStore::get_layout_types(), true )
			? $layout_type
			: 'default';

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName -- Matches WordPress block parser conventions.
		if ( $this->blockName ) {
			// Use general block index for sorting.
			$this->index = ++self::$_index;

			// For order index (used for CSS classes), use the central module index manager.
			// Convert block name to module slug for compatibility with D4.
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName -- Matches WordPress block parser conventions.
			$module_slug = \ET_Builder_Module_Order::block_name_to_module_slug( $this->blockName );

			// Get order index from central manager.
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName -- Matches WordPress block parser conventions.
			$this->orderIndex = \ET_Builder_Module_Order::increment_index(
				\ET_Builder_Module_Order::INDEX_MODULE_ORDER,
				$module_slug,
				$this->layout_type
			);

			// phpcs:ignore WordPress.NamingConventions.ValidVariableName -- Matches WordPress block parser conventions.
			$this->id = "{$this->blockName}-{$this->orderIndex}";
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName -- Matches WordPress block parser conventions.
			$this->parentId = $parent_id;
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName -- Matches WordPress block parser conventions.
			$this->storeInstance = $store_instance;
		}
	}

	/**
	 * Reset order indexes data
	 *
	 * Resets all module order indexes using the central module order manager.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function reset_order_index() {
		BlockParserStore::reset_order_index();
	}

	/**
	 * Merges module attributes with preset and group preset attributes.
	 *
	 * This method retrieves and merges attributes from a specified module,
	 * its selected preset, and any applicable group presets.
	 *
	 * @since ??
	 *
	 * @return array The merged attributes array.
	 */
	public function get_merged_attrs(): array {
		if ( is_array( $this->_merged_attrs ) ) {
			return $this->_merged_attrs;
		}

		$this->_merged_attrs = GlobalPreset::get_merged_attrs(
			[
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName -- Matches WordPress block parser conventions.
				'moduleName'  => $this->blockName,
				'moduleAttrs' => $this->attrs ?? [],
			]
		);

		return $this->_merged_attrs;
	}
}
