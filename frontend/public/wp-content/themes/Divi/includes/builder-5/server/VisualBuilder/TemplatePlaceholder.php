<?php
/**
 * Visual Builder's Template Placeholder Class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\ModuleLibrary\Comments\CommentsModule;
use ET\Builder\VisualBuilder\SettingsData\SettingsDataCallbacks;

/**
 * Class for rendering Visual Builder's element that should be rendered on server then delivered to Visual Builder.
 *
 * @since ??
 */
class TemplatePlaceholder {
	/**
	 * Comments template cannot be generated via AJAX so prepare it beforehand.
	 *
	 * @since ??
	 */
	public static function comments() {
		global $post;
		$original_post          = $post;
		$original_withcomments  = $GLOBALS['withcomments'] ?? null;
		$did_setup_current_post = false;

		// On settings-data REST requests, global $post can be missing.
		// Restore post context from SettingsData so comments_template can render markup.
		if ( ! ( $post instanceof \WP_Post ) ) {
			$current_post_id = SettingsDataCallbacks::get_current_post_id();
			if ( $current_post_id > 0 ) {
				$current_post = get_post( $current_post_id );
				if ( $current_post instanceof \WP_Post ) {
					// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required to provide comments context in REST.
					$post = $current_post;
					setup_postdata( $current_post );
					$did_setup_current_post = true;
				}
			}
		}

		$post_type = isset( $post->post_type ) ? $post->post_type : false;

		// Modify the Comments content for the Comment Module preview in TB.
		if ( et_theme_builder_is_layout_post_type( $post_type ) ) {
			add_filter( 'comments_open', '__return_true' );

			if ( function_exists( 'et_builder_set_comment_fields' ) ) {
				add_filter( 'comment_form_field_comment', 'et_builder_set_comment_fields' );
			}

			if ( function_exists( 'et_builder_set_comments_number' ) ) {
				add_filter( 'get_comments_number', 'et_builder_set_comments_number' );
			}

			if ( function_exists( 'et_builder_add_fake_comments' ) ) {
				add_filter( 'comments_array', 'et_builder_add_fake_comments' );
			}
		}

		// Use Builder 5 comments hooks so Visual Builder markup matches frontend output.
		add_action( 'pre_get_comments', [ CommentsModule::class, 'et_pb_modify_comments_request' ], 1 );
		add_filter( 'comments_template', [ CommentsModule::class, 'et_pb_comments_template' ] );
		add_filter( 'comment_form_submit_button', [ CommentsModule::class, 'et_pb_comments_submit_button' ] );

		// Custom action before calling comments_template.
		do_action( 'et_fb_before_comments_template' );

		// In REST requests, WordPress conditionals like `is_single()` are false,
		// so comments_template() can return early unless `$withcomments` is forced.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required so comments_template() does not early-return in REST context.
		$GLOBALS['withcomments'] = true;

		ob_start();
		// TODO fix(D5, Comments): Revert to comments_template after WordPress core resolves Trac #61468. [https://github.com/elegantthemes/Divi/issues/28338].
		et_comments_template_safe( '', true );
		$comments_content = ob_get_contents();
		ob_end_clean();

		// Custom action after calling comments_template.
		do_action( 'et_fb_after_comments_template' );

		// Remove all the actions and filters to not break the default comments section from theme.
		remove_filter( 'comments_template', [ CommentsModule::class, 'et_pb_comments_template' ] );
		remove_action( 'pre_get_comments', [ CommentsModule::class, 'et_pb_modify_comments_request' ], 1 );
		remove_filter( 'comment_form_submit_button', [ CommentsModule::class, 'et_pb_comments_submit_button' ] );
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring original global state after temporary override.
		$GLOBALS['withcomments'] = $original_withcomments;

		if ( $did_setup_current_post ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore original state after temporary context switch.
			$post = $original_post;
			wp_reset_postdata();
		}

		return $comments_content;
	}
}
