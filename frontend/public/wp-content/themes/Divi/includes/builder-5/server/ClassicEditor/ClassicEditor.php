<?php
/**
 * Classic Editor: ClassicEditor class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\ClassicEditor;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Utility\PostUtility;
use ET\Builder\Packages\ShortcodeModule\SaveContent;
use ET\Builder\VisualBuilder\AppPreferences\AppPreferences;

/**
 * ClassicEditor class.
 *
 * This class provides functionality to add speculation prerendering for the Visual Builder
 * when editing posts in the Classic editor.
 *
 * @since ??
 */
class ClassicEditor {

	/**
	 * Initialize ClassicEditor.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function initialize(): void {
		// Unwrap shortcodes from paragraph tags in shortcode-module blocks on save (e.g. Classic Editor) - issue #48732.
		add_filter( 'wp_insert_post_data', [ SaveContent::class, 'unwrap_shortcode_paragraphs' ], 4, 2 );

		// Enqueue speculation rules script for Classic editor.
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_speculation_script' ] );
	}

	/**
	 * Enqueue speculation rules script in the Classic editor.
	 *
	 * @since ??
	 *
	 * @param string $hook_suffix Current admin page hook.
	 *
	 * @return void
	 */
	public static function enqueue_speculation_script( string $hook_suffix ): void {
		// Only enqueue on post edit screens.
		if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		// Check if prerendering is enabled.
		if ( ! AppPreferences::is_prerendering_enabled() ) {
			return;
		}

		$post_id = PostUtility::get_editor_post_id();

		// Classic editor prerendering only applies to existing posts.
		if ( ! $post_id ) {
			return;
		}

		// Skip when block editor is active for this post.
		if ( function_exists( 'use_block_editor_for_post' ) && use_block_editor_for_post( $post_id ) ) {
			return;
		}

		// Check if the Divi Builder is enabled for this post.
		if ( ! et_builder_enabled_for_post( $post_id ) ) {
			return;
		}

		// Get the post URL and construct the Visual Builder URL.
		$post_url = get_permalink( $post_id );
		if ( ! $post_url ) {
			return;
		}

		// Add Visual Builder query parameters.
		$vb_url = add_query_arg(
			[
				'et_fb'     => '1',
				'PageSpeed' => 'off',
			],
			$post_url
		);

		// Enqueue the speculation rules script.
		wp_enqueue_script(
			'et-speculation-rules-classic',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-speculation-rules.js',
			[],
			ET_BUILDER_PRODUCT_VERSION,
			true
		);

		// Pass data to the script.
		wp_localize_script(
			'et-speculation-rules-classic',
			'diviSpeculationRules',
			[
				'urls'          => [ $vb_url ],
				'eagerness'     => 'immediate',
				'dataAttribute' => 'data-vb-classic-prerender',
			]
		);
	}
}

$classic_editor = new ClassicEditor();
$classic_editor->initialize();
