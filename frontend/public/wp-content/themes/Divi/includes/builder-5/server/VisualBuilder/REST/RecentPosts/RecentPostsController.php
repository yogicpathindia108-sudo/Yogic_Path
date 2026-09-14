<?php
/**
 * REST: RecentPostsController class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\REST\RecentPosts;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Recent Posts REST Controller class.
 *
 * @since ??
 */
class RecentPostsController extends RESTController {

	/**
	 * Retrieves an array of recently updated posts with Divi builder enabled.
	 *
	 * This function retrieves the 10 most recently updated posts that have
	 * the Divi builder enabled (checked via `_et_pb_use_builder` postmeta).
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response The REST response object containing the recent posts.
	 */
	public static function index( WP_REST_Request $request ): WP_REST_Response {
		$limit = 10;

		// Query more posts initially to account for permission filtering.
		$query_limit = $limit * 2;

		$query = [
			'post_type'      => 'any',
			'posts_per_page' => $query_limit,
			'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'meta_query'     => [
				[
					'key'   => '_et_pb_use_builder',
					'value' => 'on',
				],
			],
		];

		$posts = new \WP_Query( $query );

		$results = [];

		foreach ( $posts->posts as $post ) {
			// Check if current user has permission to edit this post.
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}

			$post_url = get_permalink( $post->ID );

			// Validate that we have valid data before adding to results.
			// Ensure ID is a valid integer, title is a non-empty string, and URL is a valid string.
			if ( ! is_numeric( $post->ID ) || ! is_string( $post_url ) || '' === $post_url ) {
				continue;
			}

			$post_title = wp_strip_all_tags( $post->post_title );

			// Ensure title is a non-empty string.
			if ( ! is_string( $post_title ) || '' === $post_title ) {
				continue;
			}

			$results[] = [
				'id'    => (int) $post->ID,
				'title' => $post_title,
				'url'   => $post_url,
			];

			// Stop once we have enough results.
			if ( count( $results ) >= $limit ) {
				break;
			}
		}

		$data = [
			'results' => $results,
		];

		return self::response_success( $data );
	}

	/**
	 * Get the arguments for the index action.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for the index action.
	 */
	public static function index_args(): array {
		return [];
	}

	/**
	 * Get the permission status for the index action.
	 *
	 * This function checks if the current user has the permission to use the Visual Builder.
	 *
	 * @since ??
	 *
	 * @return bool Returns `true` if the current user has the permission to use the Visual Builder, `false` otherwise.
	 */
	public static function index_permission(): bool {
		return UserRole::can_current_user_use_visual_builder();
	}
}
