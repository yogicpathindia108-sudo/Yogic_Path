<?php
/**
 * Class BlockParserUtils
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

/**
 * Class BlockParserUtils
 *
 * Utility methods for block parsing operations.
 *
 * @since ??
 */
class BlockParserUtils {

	/**
	 * Checks if the content contains wrapper blocks that trigger double parsing.
	 *
	 * WordPress wrapper blocks like wp:post-content, wp:navigation, and wp:block
	 * can cause double parsing issues. This method detects their presence in the content.
	 *
	 * @since ??
	 *
	 * @param string $content The content to check for wrapper blocks.
	 *
	 * @return bool True if wrapper blocks are found, false otherwise.
	 *
	 * @see https://github.com/WordPress/WordPress/commit/c5f7803d64e5a35e84aa3b8abd315c0441aea463
	 */
	public static function is_parsing_wrapper_blocks( $content ) {
		// Bail early if content is empty.
		if ( empty( $content ) ) {
			return false;
		}

		// Check for WordPress wrapper blocks that trigger double parsing.
		$has_post_content_wrapper = strpos( $content, '<!-- wp:post-content ' ) !== false;
		$has_navigation_wrapper   = strpos( $content, '<!-- wp:navigation ' ) !== false;
		$has_block_wrapper        = strpos( $content, '<!-- wp:block ' ) !== false;

		return $has_post_content_wrapper || $has_navigation_wrapper || $has_block_wrapper;
	}

	/**
	 * Parses blocks with specific layout context.
	 *
	 * @since ??
	 *
	 * @param string $content The content to parse.
	 * @param string $layout_type The type of layout to parse.
	 * @param string $layout_id The ID of the layout to parse.
	 *
	 * @return array The parsed blocks.
	 */
	public static function parse_blocks_with_layout_context( string $content, string $layout_type, string $layout_id = '' ): array {
		BlockParserStore::set_layout(
			[
				'id'   => $layout_id,
				'type' => $layout_type,
			]
		);

		$blocks = parse_blocks( $content );

		// Reset the block parser store and order index to avoid conflicts with rendering.
		BlockParserBlock::reset_order_index();

		BlockParserStore::reset_layout();
		return $blocks;
	}
}
