<?php
/**
 * GlobalLayout Class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\GlobalLayout;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Contains Global Layout utilities.
 *
 * @since ??
 */
class GlobalLayout {
	/**
	 * Check if a post is a global layout template.
	 *
	 * A global layout template is a Divi Library item (post type: et_pb_layout)
	 * with the 'scope' taxonomy term set to 'global'. This can be any layout type
	 * including sections, rows, columns, modules, or full layouts.
	 *
	 * @since ??
	 *
	 * @param int|null $post_id Post ID to check. If null, returns false.
	 *
	 * @return bool True if the post is a global layout template, false otherwise.
	 */
	public static function is_global_layout_template( ?int $post_id ): bool {
		// Return false if no post ID provided.
		if ( empty( $post_id ) ) {
			return false;
		}

		// Check if post exists and is a Divi Library item.
		$post = get_post( $post_id );
		if ( ! $post || ET_BUILDER_LAYOUT_POST_TYPE !== $post->post_type ) {
			return false;
		}

		// Check if the post has 'global' scope taxonomy term.
		$scope_terms = get_the_terms( $post_id, 'scope' );
		if ( ! empty( $scope_terms ) && ! is_wp_error( $scope_terms ) ) {
			// Check if 'global' is in the scope terms.
			$scope_slugs = wp_list_pluck( $scope_terms, 'slug' );
			if ( in_array( 'global', $scope_slugs, true ) ) {
				return true;
			}
		}

		// Fallback for imported D4 global module templates where taxonomy may not exist yet.
		$module_type      = get_post_meta( $post_id, '_et_pb_module_type', true );
		$excluded_options = get_post_meta( $post_id, '_et_pb_excluded_global_options', true );

		return ! empty( $module_type ) && ! empty( $excluded_options );
	}
}
