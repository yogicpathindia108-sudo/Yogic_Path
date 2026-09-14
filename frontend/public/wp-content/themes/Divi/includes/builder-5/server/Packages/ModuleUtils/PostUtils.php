<?php
/**
 * Module Utils: Post Utilities
 *
 * Helper utilities for modules that work with post data and context.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleUtils;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * PostUtils class.
 *
 * Provides utility methods for modules that work with post data,
 * with proper context handling for Theme Builder templates.
 *
 * @since ??
 */
class PostUtils {

	/**
	 * Get the correct post ID with proper Theme Builder context handling.
	 *
	 * This function follows the pattern used by other Divi 5 modules to ensure
	 * the correct post context in Theme Builder templates. It uses get_queried_object_id()
	 * first (which works correctly in Theme Builder contexts) before falling back to get_the_ID().
	 *
	 * @since ??
	 *
	 * @return int|false The post ID, or false if no post is available.
	 *
	 * @example
	 * ```php
	 * $post_id = PostUtils::get_current_post_id();
	 * if ( $post_id ) {
	 *     $comments_count = get_comments_number( $post_id );
	 * }
	 * ```
	 */
	public static function get_current_post_id() {
		$post_id = get_queried_object_id();

		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		return $post_id ?: false;
	}

	/**
	 * Get comments popup link with proper Theme Builder context handling.
	 *
	 * Delegates to et_pb_get_comments_popup_link() after switching global post context to
	 * the resolved post (see get_current_post_id()) so markup, hooks, and comments_number
	 * behavior match the legacy helper everywhere Theme Builder would otherwise use the
	 * template post.
	 *
	 * @since ??
	 *
	 * @param string $zero Text to display when 0 comments.
	 * @param string $one  Text to display when 1 comment.
	 * @param string $more Text to display for more than 1 comments.
	 *
	 * @return string The HTML for the comments link, or empty string if not applicable.
	 *
	 * @example
	 * ```php
	 * $comments_link = PostUtils::get_comments_popup_link(
	 *     esc_html__( '0 comments', 'et_builder_5' ),
	 *     esc_html__( '1 comment', 'et_builder_5' ),
	 *     '% ' . esc_html__( 'comments', 'et_builder_5' )
	 * );
	 * ```
	 */
	public static function get_comments_popup_link( string $zero, string $one, string $more ): string {
		$post_id = self::get_current_post_id();

		if ( ! $post_id ) {
			return '';
		}

		$target_post = get_post( $post_id );

		if ( ! $target_post instanceof \WP_Post ) {
			return '';
		}

		if ( ! function_exists( 'et_pb_get_comments_popup_link' ) ) {
			return '';
		}

		global $post;

		$previous_post = $post;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Temporary context for legacy helper.
		$post = $target_post;
		setup_postdata( $post );

		try {
			$link = et_pb_get_comments_popup_link( $zero, $one, $more );
		} finally {
			wp_reset_postdata();

			if ( $previous_post instanceof \WP_Post ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore prior post context.
				$post = $previous_post;

				setup_postdata( $previous_post );
			}
		}

		return is_string( $link ) ? $link : '';
	}
}
