<?php
/**
 * ShortcodeModule: SaveContent.
 *
 * Normalizes post content on save so shortcode-module block innerHTML is not
 * stored with shortcodes wrapped in paragraph tags (e.g. when saving via Classic Editor).
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ShortcodeModule;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Save-time content normalization for shortcode-module blocks.
 *
 * @since ??
 */
class SaveContent {

	/**
	 * Unwrap shortcodes from paragraph tags in divi/shortcode-module block innerHTML on save.
	 *
	 * When saving via Classic Editor (or any editor that submits HTML), WordPress/TinyMCE
	 * can wrap shortcode syntax in `<p>` tags. This runs shortcode_unautop() only on the
	 * innerHTML of each divi/shortcode-module block so the Visual Builder and conversion
	 * logic see raw shortcode syntax. The filter is registered from ClassicEditor (priority 4).
	 *
	 * @since ??
	 *
	 * @param array $data     An array of slashed post data.
	 * @param array $postarr Raw post data (unused).
	 *
	 * @return array Modified post data.
	 */
	public static function unwrap_shortcode_paragraphs( $data, $postarr = [] ) {
		if ( ! isset( $data['post_content'] ) || ! is_string( $data['post_content'] ) || '' === $data['post_content'] ) {
			return $data;
		}

		$content = wp_unslash( $data['post_content'] );

		// Only process when the content contains Divi shortcode-module blocks.
		if ( ! str_contains( $content, '<!-- wp:divi/shortcode-module' ) ) {
			return $data;
		}

		$blocks = parse_blocks( $content );

		self::_unwrap_shortcode_module_blocks( $blocks );

		$data['post_content'] = wp_slash( serialize_blocks( $blocks ) );

		return $data;
	}

	/**
	 * Recursively unwrap shortcodes from paragraph tags inside divi/shortcode-module blocks.
	 *
	 * @since ??
	 *
	 * @param array &$blocks Parsed blocks (modified in place).
	 *
	 * @return void
	 */
	private static function _unwrap_shortcode_module_blocks( &$blocks ) {
		foreach ( $blocks as &$block ) {
			if ( 'divi/shortcode-module' === ( $block['blockName'] ?? null ) ) {
				if ( isset( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ) {
					$block['innerHTML'] = shortcode_unautop( $block['innerHTML'] );

					// Keep innerContent in sync so serialize_blocks() outputs the unwrapped content.
					$block['innerContent'] = [ $block['innerHTML'] ];
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				self::_unwrap_shortcode_module_blocks( $block['innerBlocks'] );
			}
		}
	}
}
