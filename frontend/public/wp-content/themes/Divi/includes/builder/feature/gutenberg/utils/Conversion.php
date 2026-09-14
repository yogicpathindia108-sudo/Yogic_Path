<?php
/**
 * Gutenberg Conversion Utilities.
 *
 * @package Divi
 * @subpackage Builder
 * @since 4.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

	/**
	 * Class ET_GB_Utils_Conversion
	 *
	 * Handling Gutenberg serialized content conversion into builder divi layout
	 */
class ET_GB_Utils_Conversion {
	/**
	 * Populate all layout block which is placed inside other block. Layout block contains
	 * section which has to be the first level element once converted into VB content.
	 *
	 * @var array
	 */
	private $_deep_layout_blocks = array();

	/**
	 * Layout list. Layout block got its own section. Others are concatenated into text module.
	 *
	 * @var array
	 */
	private $_layout_list = array();

	/**
	 * Temporary variable to hold non layout block into one (stored as array of blocks).
	 *
	 * @var array
	 */
	private $_text_module_content = array();

	/**
	 * Serialized layout.
	 *
	 * @var string
	 */
	private $_divi_layout = '';

	/**
	 * Raw post content for extracting layout content by uniqueId.
	 *
	 * @var string
	 */
	private $_raw_content = '';

	/**
	 * Check if given block is layout block
	 *
	 * @since 4.1.0
	 *
	 * @todo being set as static so it is easier to be used outside this class. If being used quite
	 *       frequently, probably consider wrap this into function. Not needed at the moment tho
	 *
	 * @param array $block Parsed block.
	 *
	 * @return bool
	 */
	public static function is_layout_block( $block = array() ) {
		$block_name = et_()->array_get( $block, 'blockName', '' );

		return 'divi/layout' === $block_name;
	}

	/**
	 * Check if given block is reusable block
	 *
	 * @since 4.1.0
	 *
	 * @todo being set as static so it is easier to be used outside this class. If being used quite
	 *       frequently, probably consider wrap this into function. Not needed at the moment tho
	 *
	 * @param array $block Parsed block.
	 *
	 * @return bool
	 */
	public static function is_reusable_block( $block = array() ) {
		$block_name = et_()->array_get( $block, 'blockName', '' );

		return 'core/block' === $block_name && et_()->array_get( $block, 'attrs.ref' ) > 0;
	}

	/**
	 * Get reusable block's parsed content. NOTE: WordPress has built in `render_block_core_block()`
	 * but it renders the block and its content instead of parse its content.
	 *
	 * @since 4.1.0
	 *
	 * @see render_block_core_block()
	 *
	 * @todo being set as static so it is easier to be used outside this class. If being used quite
	 *       frequently, probably consider wrap this into function. Not needed at the moment tho
	 *
	 * @param array $block Parsed block.
	 *
	 * @return array
	 */
	public static function get_reusable_block_content( $block ) {
		$block_id   = et_()->array_get( $block, 'attrs.ref' );
		$block_data = get_post( $block_id );

		if ( ! $block_data || 'wp_block' !== $block_data->post_type || 'publish' !== $block_data->post_status ) {
			return array();
		}

		return parse_blocks( $block_data->post_content );
	}

	/**
	 * Parse reusable block by getting its content and append it as innerBlocks
	 *
	 * @since 4.1.0
	 *
	 * @param array Parsed block.
	 *
	 * @return array Modified parsed block.
	 */
	public static function parse_reusable_block( $block ) {
		$reusable_block_data  = self::get_reusable_block_content( $block );
		$block['innerBlocks'] = array_merge( $block['innerBlocks'], $reusable_block_data );

		// Unset reusable block's ref attribute so reusable block content is no longer fetched
		unset( $block['attrs']['ref'] );

		// Change block into group so its content is being rendered
		$block['blockName'] = 'core/group';

		// Recreate innerContent which is used by block parser to render innerBlock.
		// See: `render_block()`'s `$block['innerContent'] as $chunk` loop
		$block['innerContent'] = array_merge(
			array( '<div class="wp-block-group"><div class="wp-block-group__inner-container">' ),
			array_fill( 0, count( $block['innerBlocks'] ), null ),
			array( '</div></div>' )
		);

		return $block;
	}

	/**
	 * Remove WordPress block comments from content.
	 *
	 * @since 4.1.0
	 *
	 * @param string $content Content to clean.
	 *
	 * @return string Cleaned content.
	 */
	private function _remove_wordpress_blocks( $content ) {
		// Use a regular expression to match comments that start with "wp:" or "/wp:".
		// Pattern matches: /<!--\s*\/?wp:(.*?)-->/gs (global + dotall flags).
		$pattern = '/<!--\s*\/?wp:(.*?)-->/s';

		// Replace matched comments with an empty string.
		return preg_replace( $pattern, '', $content );
	}

	/**
	 * Extract layout block content from raw content using uniqueId.
	 * This bypasses parse_blocks() which strips unregistered Divi block comments.
	 *
	 * @since 4.1.0
	 *
	 * @param string $content   Raw post content.
	 * @param string $unique_id Layout block's uniqueId attribute.
	 *
	 * @return string The layout block's inner Divi content.
	 */
	private function _get_layout_content_by_unique_id( $content, $unique_id ) {
		// Match layout block with this uniqueId and capture the div innerHTML.
		$layout_pattern = '/<!--\s*wp:divi\/layout\s+\{[^}]*"uniqueId"\s*:\s*"' . preg_quote( $unique_id, '/' ) . '"[^}]*\}\s*-->\s*<div class="wp-block-divi-layout">([\s\S]*?)<\/div>\s*<!--\s*\/wp:divi\/layout\s*-->/i';

		if ( ! preg_match( $layout_pattern, $content, $matches ) || empty( $matches[1] ) ) {
			return '';
		}

		$inner_content = trim( $matches[1] );

		// Strip the placeholder wrapper if present.
		$inner_content = preg_replace( '/^<!--\s*wp:divi\/placeholder\s*-->/i', '', $inner_content );
		$inner_content = preg_replace( '/<!--\s*\/wp:divi\/placeholder\s*-->$/i', '', $inner_content );
		$inner_content = trim( $inner_content );

		return $inner_content;
	}

	/**
	 * Pull layout block that is located deep inside inner blocks. Layout block contains section;
	 * in builder, section has to be on the first level of document
	 *
	 * @since 4.1.0
	 *
	 * @param array $block Parsed block.
	 *
	 * @return array|false Modified block or false if layout block was pulled.
	 */
	private function pull_layout_block( $block ) {
		// Pull and populate layout block. Layout block contains section(s) so it should be rendered
		// on first level layout, below Gutenberg content inside text module
		if ( self::is_layout_block( $block ) ) {
			// Pull layout block and populate list of layout block located on inner blocks.
			$this->_deep_layout_blocks[] = $block;

			// Return false to remove layout block from parsed content (matching JS behavior).
			return false;
		}

		// Reusable block's content is not saved inside block; Thus Get reusable block's content,
		// append it as innerBlock, and pull layout block if exist.
		if ( self::is_reusable_block( $block ) ) {
			$block = self::parse_reusable_block( $block );
		}

		// Recursively loop over block then pull Layout Block.
		if ( ! empty( $block['innerBlocks'] ) ) {
			$filtered_blocks = array();
			foreach ( $block['innerBlocks'] as $inner_block ) {
				$result = $this->pull_layout_block( $inner_block );
				// Only add non-false results (false means layout block was pulled out).
				if ( false !== $result ) {
					$filtered_blocks[] = $result;
				}
			}
			$block['innerBlocks'] = $filtered_blocks;
		}

		return $block;
	}

	/**
	 * Convert serialized block into divi layout.
	 *
	 * @since 4.1.0
	 *
	 * @param string $serialized_block Serialized block content.
	 * @param string $raw_content      Raw post content for extracting layout content by uniqueId.
	 *
	 * @return string
	 */
	public function block_to_divi_layout( $serialized_block = '', $raw_content = '' ) {
		// Store raw content for extracting layout content by uniqueId.
		$this->_raw_content = $raw_content;

		// Reset instance variables.
		$this->_deep_layout_blocks  = array();
		$this->_layout_list         = array();
		$this->_text_module_content = array();
		$this->_divi_layout         = '';

		// Parsed blocks.
		$blocks = parse_blocks( $serialized_block );

		// Loop blocks.
		foreach ( $blocks as $block ) {
			if ( self::is_layout_block( $block ) ) {
				// Append currently populated non-Layout Block into one before layout block is appended.
				if ( ! empty( $this->_text_module_content ) ) {
					$this->_layout_list[] = $this->_text_module_content;

					// Reset text module content so next non-layout block is placed below current layout block.
					$this->_text_module_content = array();
				}

				$this->_layout_list[] = $block;
			} else {
				// Reusable block's content is not saved inside block; Thus Get reusable block's
				// content, append it as innerBlock, and pull layout block if exist.
				if ( self::is_reusable_block( $block ) ) {
					$block = self::parse_reusable_block( $block );
				}

				// Pull any Layout Block inside nested block if there's any.
				if ( ! empty( $block['innerBlocks'] ) ) {
					$filtered_blocks = array();
					foreach ( $block['innerBlocks'] as $inner_block ) {
						$result = $this->pull_layout_block( $inner_block );
						// Only add non-false results (false means layout block was pulled out).
						if ( false !== $result ) {
							$filtered_blocks[] = $result;
						}
					}
					$block['innerBlocks'] = $filtered_blocks;
				}

				// Populate block into temporary text module content buffer (store as block object, not rendered HTML).
				$this->_text_module_content[] = $block;
			}
		}

		// Populate remaining non-layout block into layout list.
		if ( ! empty( $this->_text_module_content ) ) {
			$this->_layout_list[] = $this->_text_module_content;

			// Reset.
			$this->_text_module_content = array();
		}

		// Loop over populated content and render it into divi layout.
		foreach ( array_merge( $this->_layout_list, $this->_deep_layout_blocks ) as $item ) {
			if ( self::is_layout_block( $item ) ) {
				// Extract layout content using uniqueId from raw content.
				$unique_id   = et_()->array_get( $item, 'attrs.uniqueId', '' );
				$divi_layout = '';

				if ( ! empty( $unique_id ) && ! empty( $this->_raw_content ) ) {
					$divi_layout = $this->_get_layout_content_by_unique_id( $this->_raw_content, $unique_id );
				}

				// Fallback to layoutContent attribute if uniqueId extraction failed.
				if ( empty( $divi_layout ) ) {
					$divi_layout = et_()->array_get( $item, 'attrs.layoutContent', '' );
				}

				$this->_divi_layout .= $divi_layout;
			} else {
				// Serialize the block(s) - item can be an array of blocks.
				$serialized_block_content = '';
				if ( is_array( $item ) && ! isset( $item['blockName'] ) ) {
					// Item is an array of blocks.
					foreach ( $item as $block ) {
						$serialized_block_content .= serialize_block( $block );
					}
				} else {
					// Item is a single block.
					$serialized_block_content = serialize_block( $item );
				}

				// Remove WordPress block comments from the content.
				$cleaned_content = $this->_remove_wordpress_blocks( $serialized_block_content );

				// Build text module block and use WordPress serialize_block for proper attribute escaping.
				$text_block = array(
					'blockName'    => 'divi/text',
					'attrs'        => array(
						'content' => array(
							'innerContent' => array(
								'desktop' => array(
									'value' => $cleaned_content,
								),
							),
						),
					),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				);

				$column_block = array(
					'blockName'    => 'divi/column',
					'attrs'        => array(
						'module' => array(
							'advanced' => array(
								'type' => array(
									'desktop' => array(
										'value' => '4_4',
									),
								),
							),
						),
					),
					'innerBlocks'  => array( $text_block ),
					'innerHTML'    => '',
					'innerContent' => array( null ),
				);

				$row_block = array(
					'blockName'    => 'divi/row',
					'attrs'        => array(
						'module' => array(
							'meta' => array(
								'adminLabel' => array(
									'desktop' => array(
										'value' => 'row',
									),
								),
							),
						),
					),
					'innerBlocks'  => array( $column_block ),
					'innerHTML'    => '',
					'innerContent' => array( null ),
				);

				$section_block = array(
					'blockName'    => 'divi/section',
					'attrs'        => array(
						'module' => array(
							'meta' => array(
								'adminLabel' => array(
									'desktop' => array(
										'value' => 'section',
									),
								),
							),
						),
					),
					'innerBlocks'  => array( $row_block ),
					'innerHTML'    => '',
					'innerContent' => array( null ),
				);

				$this->_divi_layout .= serialize_block( $section_block );
			}
		}

		return $this->_divi_layout;
	}

	/**
	 * Convert gutenberg block layout into divi layout.
	 *
	 * NOTE: There is JS version for activation via Gutenberg. See: `convertBlockToDiviLayout()`.
	 *
	 * @since 4.1.0
	 *
	 * @param string $post_content Post content / serialized block.
	 *
	 * @return string Divi layout.
	 */
	public static function convert_block_to_divi_layout( $post_content ) {
		$conversion = new self();

		// Pass raw post content as second parameter for extracting layout content by uniqueId.
		return $conversion->block_to_divi_layout( $post_content, $post_content );
	}
}

