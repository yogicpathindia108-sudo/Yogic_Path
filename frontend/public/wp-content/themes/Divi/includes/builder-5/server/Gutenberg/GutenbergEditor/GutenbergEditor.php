<?php
/**
 * Gutenberg Editor: GutenbergEditor class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Gutenberg\GutenbergEditor;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Utility\PostUtility;
use ET\Builder\VisualBuilder\AppPreferences\AppPreferences;

/**
 * GutenbergEditor class.
 *
 * This class provides functionality to add speculation prerendering for the Visual Builder
 * when editing posts in the Gutenberg block editor.
 *
 * @since ??
 */
class GutenbergEditor {


	/**
	 * Initialize GutenbergEditor.
	 *
	 * @since ??
	 */
	public function initialize() {
		// Enqueue speculation rules script for Gutenberg editor.
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_gutenberg_speculation_script' ] );
	}

	/**
	 * Enqueue speculation rules script in the Gutenberg editor.
	 *
	 * This function enqueues the speculation rules script and passes the Visual Builder URL
	 * via script data when the Divi Builder is enabled on the current post.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_gutenberg_speculation_script(): void {
		// Check if prerendering is enabled.
		if ( ! AppPreferences::is_prerendering_enabled() ) {
			return;
		}

		$post_id = PostUtility::get_editor_post_id();

		if ( ! $post_id ) {
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
			'et-speculation-rules-gutenberg',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-speculation-rules.js',
			[],
			ET_BUILDER_PRODUCT_VERSION,
			true
		);

		// Pass data to the script.
		wp_localize_script(
			'et-speculation-rules-gutenberg',
			'diviSpeculationRules',
			[
				'urls'                   => [ $vb_url ],
				'eagerness'              => 'immediate',
				'dataAttribute'          => 'data-vb-gutenberg-prerender',
				'clearOnSave'            => true,
				'gutenbergEditorContext' => true,
			]
		);
	}
}

$gutenberg_editor = new GutenbergEditor();
$gutenberg_editor->initialize();
