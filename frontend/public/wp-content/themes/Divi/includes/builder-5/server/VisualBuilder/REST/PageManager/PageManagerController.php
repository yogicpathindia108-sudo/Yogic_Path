<?php
/**
 * REST: PageManagerController class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\REST\PageManager;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Page Manager REST Controller class.
 *
 * @since ??
 */
class PageManagerController extends RESTController {

	/**
	 * Retrieves an array of all posts with Divi builder enabled that the user can edit.
	 *
	 * This function retrieves all posts that have the Divi builder enabled
	 * (checked via `_et_pb_use_builder` postmeta) and groups them by post type.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response The REST response object containing the posts grouped by post type.
	 */
	public static function index( WP_REST_Request $request ): WP_REST_Response {
		$query = [
			'post_type'      => 'any',
			'posts_per_page' => -1,
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

		$results_by_type = [];

		foreach ( $posts->posts as $post ) {
			// Check if current user has permission to edit this post.
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}

			$post_type = get_post_type( $post->ID );
			$post_url  = get_permalink( $post->ID );

			if ( ! isset( $results_by_type[ $post_type ] ) ) {
				$results_by_type[ $post_type ] = [];
			}

			$results_by_type[ $post_type ][] = [
				'id'    => $post->ID,
				'title' => wp_strip_all_tags( $post->post_title ),
				'url'   => $post_url,
			];
		}

		// Sort posts by saved order if available.
		foreach ( $results_by_type as $post_type => &$posts_array ) {
			// Get saved order for this post type.
			$order_meta_key = '_et_pb_page_manager_order_' . $post_type;
			$saved_order    = get_option( $order_meta_key, [] );

			if ( ! empty( $saved_order ) && is_array( $saved_order ) ) {
				// Create a map of post ID to order.
				$order_map = [];
				foreach ( $saved_order as $index => $post_id ) {
					$order_map[ $post_id ] = $index;
				}

				// Sort posts array by saved order.
				usort(
					$posts_array,
					function ( $a, $b ) use ( $order_map ) {
						$a_order = $order_map[ $a['id'] ] ?? PHP_INT_MAX;
						$b_order = $order_map[ $b['id'] ] ?? PHP_INT_MAX;

						return $a_order <=> $b_order;
					}
				);
			}
		}

		$data = [
			'results' => $results_by_type,
		];

		return self::response_success( $data );
	}

	/**
	 * Duplicates a post.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response|WP_Error The REST response object. WP_Error if there is an error.
	 */
	public static function duplicate( WP_REST_Request $request ) {
		$post_id = $request->get_param( 'id' );

		if ( ! $post_id ) {
			return self::response_error( 'missing_post_id', esc_html__( 'Post ID is required.', 'et_builder_5' ) );
		}

		$original_post = get_post( $post_id );

		if ( ! $original_post ) {
			return self::response_error( 'post_not_found', esc_html__( 'Post not found.', 'et_builder_5' ) );
		}

		// Check if user has permission to edit the specific post.
		// This ensures only posts that can be edited can be duplicated.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return self::response_error( 'insufficient_permissions', esc_html__( 'You do not have permission to duplicate this post.', 'et_builder_5' ) );
		}

		// Prepare post data for duplication.
		$post_data = [
			'post_title'   => sprintf( '%s %s', $original_post->post_title, __( '(Copy)', 'et_builder_5' ) ),
			'post_content' => $original_post->post_content,
			'post_excerpt' => $original_post->post_excerpt,
			'post_status'  => 'draft',
			'post_type'    => $original_post->post_type,
			'post_author'  => get_current_user_id(),
		];

		// Apply wp_slash to post_content for proper handling of Divi 5 content.
		$post_data['post_content'] = wp_slash( $post_data['post_content'] );

		// Insert the duplicated post.
		$new_post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $new_post_id ) ) {
			return self::response_error( 'duplication_failed', esc_html__( 'Failed to duplicate post.', 'et_builder_5' ) );
		}

		// Copy post meta.
		$meta_keys = get_post_meta( $post_id );
		foreach ( $meta_keys as $key => $values ) {
			foreach ( $values as $value ) {
				// Unserialize if needed, then re-serialize for storage.
				$meta_value = maybe_unserialize( $value );
				update_post_meta( $new_post_id, $key, $meta_value );
			}
		}

		// Copy taxonomies.
		$taxonomies = get_object_taxonomies( $original_post->post_type );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'slugs' ] );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				wp_set_object_terms( $new_post_id, $terms, $taxonomy );
			}
		}

		$new_post_url = get_permalink( $new_post_id );
		$post_type    = get_post_type( $new_post_id );

		$data = [
			'id'        => $new_post_id,
			'title'     => get_the_title( $new_post_id ),
			'url'       => $new_post_url,
			'post_type' => $post_type,
		];

		return self::response_success( $data );
	}

	/**
	 * Creates a new post/page.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response|WP_Error The REST response object containing the created post information.
	 */
	public static function create( WP_REST_Request $request ) {
		$title       = $request->get_param( 'title' );
		$post_type   = $request->get_param( 'post_type' );
		$post_status = $request->get_param( 'post_status' );
		$post_date   = $request->get_param( 'post_date' );

		if ( ! $title ) {
			return self::response_error( 'missing_title', esc_html__( 'Title is required.', 'et_builder_5' ) );
		}

		if ( ! $post_type ) {
			return self::response_error( 'missing_post_type', esc_html__( 'Post type is required.', 'et_builder_5' ) );
		}

		// Check if user has permission to create posts of this type.
		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object ) {
			return self::response_error( 'invalid_post_type', esc_html__( 'Invalid post type.', 'et_builder_5' ) );
		}

		if ( ! current_user_can( $post_type_object->cap->create_posts ) ) {
			return self::response_error( 'insufficient_permissions', esc_html__( 'You do not have permission to create posts of this type.', 'et_builder_5' ) );
		}

		// Prepare post data for creation.
		$post_data = [
			'post_title'   => sanitize_text_field( $title ),
			'post_content' => '',
			'post_status'  => sanitize_text_field( $post_status ),
			'post_type'    => sanitize_text_field( $post_type ),
			'post_author'  => get_current_user_id(),
		];

		// Add post date if provided (for scheduled posts).
		if ( $post_date ) {
			$post_data['post_date']     = sanitize_text_field( $post_date );
			$post_data['post_date_gmt'] = get_gmt_from_date( $post_date );
		}

		// Insert the new post.
		$new_post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $new_post_id ) ) {
			return self::response_error( 'creation_failed', esc_html__( 'Failed to create post.', 'et_builder_5' ) );
		}

		// Enable Divi Builder for the new post.
		update_post_meta( $new_post_id, '_et_pb_use_builder', 'on' );

		$new_post_url            = get_permalink( $new_post_id );
		$post_type_from_response = get_post_type( $new_post_id );

		$data = [
			'id'        => $new_post_id,
			'title'     => get_the_title( $new_post_id ),
			'url'       => $new_post_url,
			'post_type' => $post_type_from_response,
		];

		return self::response_success( $data );
	}

	/**
	 * Retrieves a single post/page by ID.
	 *
	 * Unlike `search()`, this endpoint distinguishes between a missing post
	 * (`post_not_found`) and a post the current user cannot edit
	 * (`insufficient_permissions`). This avoids the ambiguity where a
	 * permission-denied result would otherwise be indistinguishable from a
	 * "not found" result via the filtered search list.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response|WP_Error The REST response object.
	 */
	public static function show( WP_REST_Request $request ) {
		$post_id = $request->get_param( 'id' );

		if ( ! $post_id ) {
			return self::response_error( 'missing_post_id', esc_html__( 'Post ID is required.', 'et_builder_5' ) );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return self::response_error( 'post_not_found', esc_html__( 'Post not found.', 'et_builder_5' ) );
		}

		$data = [
			'id'     => $post->ID,
			'title'  => wp_strip_all_tags( $post->post_title ),
			'slug'   => $post->post_name,
			'status' => $post->post_status,
			'url'    => get_permalink( $post->ID ),
			'date'   => $post->post_date,
		];

		return self::response_success( $data );
	}

	/**
	 * Updates a post/page.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response|WP_Error The REST response object.
	 */
	public static function update( WP_REST_Request $request ) {
		$post_id      = $request->get_param( 'id' );
		$title        = $request->get_param( 'title' );
		$post_name    = $request->get_param( 'post_name' );
		$post_content = $request->get_param( 'post_content' );

		if ( ! $post_id ) {
			return self::response_error( 'missing_post_id', esc_html__( 'Post ID is required.', 'et_builder_5' ) );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return self::response_error( 'post_not_found', esc_html__( 'Post not found.', 'et_builder_5' ) );
		}

		$has_changes = false;
		$post_data   = [
			'ID' => absint( $post_id ),
		];

		if ( null !== $title && '' !== $title ) {
			$post_data['post_title'] = $title;
			$has_changes             = true;
		}

		if ( null !== $post_name && '' !== $post_name ) {
			$post_data['post_name'] = $post_name;
			$has_changes            = true;
		}

		if ( null !== $post_content && '' !== $post_content ) {
			$post_data['post_content'] = $post_content;
			$has_changes               = true;
		}

		if ( ! $has_changes ) {
			return self::response_error( 'missing_update_data', esc_html__( 'At least one field to update is required.', 'et_builder_5' ) );
		}

		if ( isset( $post_data['post_content'] ) ) {
			$post_data['post_content'] = wp_slash( $post_data['post_content'] );
		}

		$updated_post_id = wp_update_post( $post_data, true );

		if ( is_wp_error( $updated_post_id ) ) {
			return self::response_error( 'update_failed', esc_html__( 'Failed to update post.', 'et_builder_5' ) );
		}

		$data = [
			'id'     => $updated_post_id,
			'title'  => get_the_title( $updated_post_id ),
			'slug'   => get_post_field( 'post_name', $updated_post_id ),
			'status' => get_post_status( $updated_post_id ),
			'url'    => get_permalink( $updated_post_id ),
		];

		return self::response_success( $data );
	}

	/**
	 * Updates post/page status.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response|WP_Error The REST response object.
	 */
	public static function update_status( WP_REST_Request $request ) {
		$post_id     = $request->get_param( 'id' );
		$post_status = $request->get_param( 'post_status' );
		$post_date   = $request->get_param( 'post_date' );

		if ( ! $post_id ) {
			return self::response_error( 'missing_post_id', esc_html__( 'Post ID is required.', 'et_builder_5' ) );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return self::response_error( 'post_not_found', esc_html__( 'Post not found.', 'et_builder_5' ) );
		}

		$post_data = [
			'ID'          => absint( $post_id ),
			'post_status' => $post_status,
		];

		if ( 'future' === $post_status ) {
			$post_data['post_date']     = $post_date;
			$post_data['post_date_gmt'] = get_gmt_from_date( $post_date );
		}

		$updated_post_id = wp_update_post( $post_data, true );

		if ( is_wp_error( $updated_post_id ) ) {
			return self::response_error( 'status_update_failed', esc_html__( 'Failed to update post status.', 'et_builder_5' ) );
		}

		$data = [
			'id'        => $updated_post_id,
			'title'     => get_the_title( $updated_post_id ),
			'slug'      => get_post_field( 'post_name', $updated_post_id ),
			'status'    => get_post_status( $updated_post_id ),
			'url'       => get_permalink( $updated_post_id ),
			'post_date' => get_post_field( 'post_date', $updated_post_id ),
		];

		return self::response_success( $data );
	}

	/**
	 * Searches posts by post type.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response|WP_Error The REST response object.
	 */
	public static function search( WP_REST_Request $request ) {
		$id             = $request->get_param( 'id' );
		$search         = $request->get_param( 'search' );
		$post_status    = $request->get_param( 'post_status' );
		$slug           = $request->get_param( 'slug' );
		$per_page_param = $request->get_param( 'per_page' );
		$page_param     = $request->get_param( 'page' );
		$per_page       = absint( null !== $per_page_param ? $per_page_param : 20 );
		$page           = absint( null !== $page_param ? $page_param : 1 );

		$per_page = max( 1, min( $per_page, 100 ) );
		$page     = max( 1, $page );

		$post_type        = self::get_search_post_type( $request );
		$post_type_object = get_post_type_object( $post_type );
		$query_args = [
			'post_type'      => $post_type,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'post_status'    => $post_status ? $post_status : [ 'publish', 'draft', 'pending', 'private', 'future' ],
		];

		$edit_others_posts_cap = 'edit_others_posts';
		if ( isset( $post_type_object->cap->edit_others_posts ) ) {
			$edit_others_posts_cap = $post_type_object->cap->edit_others_posts;
		}

		if ( ! current_user_can( $edit_others_posts_cap ) ) {
			$query_args['author'] = get_current_user_id();
		}

		if ( $id ) {
			$query_args['p'] = absint( $id );
		}

		if ( $search ) {
			$query_args['s'] = sanitize_text_field( $search );
		}

		if ( $slug ) {
			$query_args['name'] = sanitize_title( $slug );
		}

		$posts = new \WP_Query( $query_args );

		// Prime parent post caches so `get_permalink()` for hierarchical pages
		// doesn't trigger per-row ancestor lookups inside the loop below.
		if ( ! empty( $posts->posts ) && function_exists( 'update_post_parent_caches' ) ) {
			update_post_parent_caches( $posts->posts );
		}

		$results = [];

		foreach ( $posts->posts as $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}

			$results[] = [
				'id'     => $post->ID,
				'title'  => wp_strip_all_tags( $post->post_title ),
				'slug'   => $post->post_name,
				'status' => $post->post_status,
				'url'    => get_permalink( $post->ID ),
				'date'   => $post->post_date,
			];
		}

		$data = [
			'results' => $results,
			'meta'    => [
				'total'       => (int) $posts->found_posts,
				'total_pages' => (int) $posts->max_num_pages,
				'page'        => $page,
				'per_page'    => $per_page,
			],
		];

		return self::response_success( $data );
	}

	/**
	 * Updates the order of posts for a specific post type.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response|WP_Error The REST response object. WP_Error if there is an error.
	 */
	public static function update_order( WP_REST_Request $request ) {
		$post_type = $request->get_param( 'post_type' );
		$post_ids  = $request->get_param( 'post_ids' );

		if ( ! $post_type ) {
			return self::response_error( 'missing_post_type', esc_html__( 'Post type is required.', 'et_builder_5' ) );
		}

		if ( ! is_array( $post_ids ) || empty( $post_ids ) ) {
			return self::response_error( 'missing_post_ids', esc_html__( 'Post IDs array is required.', 'et_builder_5' ) );
		}

		// Validate post type.
		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object ) {
			return self::response_error( 'invalid_post_type', esc_html__( 'Invalid post type.', 'et_builder_5' ) );
		}

		// Check if user has permission to edit posts of this type.
		if ( ! current_user_can( $post_type_object->cap->edit_posts ) ) {
			return self::response_error( 'insufficient_permissions', esc_html__( 'You do not have permission to reorder posts of this type.', 'et_builder_5' ) );
		}

		// Sanitize post IDs.
		$sanitized_post_ids = array_map( 'absint', $post_ids );

		// Verify all posts exist and user has permission to edit them.
		foreach ( $sanitized_post_ids as $post_id ) {
			if ( ! get_post( $post_id ) ) {
				return self::response_error( 'post_not_found', esc_html__( 'One or more posts not found.', 'et_builder_5' ) );
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return self::response_error( 'insufficient_permissions', esc_html__( 'You do not have permission to edit one or more posts.', 'et_builder_5' ) );
			}

			// Verify post type matches.
			if ( get_post_type( $post_id ) !== $post_type ) {
				return self::response_error( 'post_type_mismatch', esc_html__( 'Post type mismatch.', 'et_builder_5' ) );
			}
		}

		// Save order as option (per post type).
		$order_meta_key = '_et_pb_page_manager_order_' . sanitize_key( $post_type );
		update_option( $order_meta_key, $sanitized_post_ids );

		return self::response_success( [] );
	}

	/**
	 * Moves a post to trash.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response|WP_Error The REST response object. WP_Error if there is an error.
	 */
	public static function trash( WP_REST_Request $request ) {
		$post_id = $request->get_param( 'id' );

		if ( ! $post_id ) {
			return self::response_error( 'missing_post_id', esc_html__( 'Post ID is required.', 'et_builder_5' ) );
		}

		// Check if user has permission to edit the post.
		// This ensures only posts that can be edited can be trashed, providing consistency
		// with the duplicate action and better security.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return self::response_error( 'insufficient_permissions', esc_html__( 'You do not have permission to trash this post.', 'et_builder_5' ) );
		}

		// Check if user has permission to delete the post.
		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			return self::response_error( 'insufficient_permissions', esc_html__( 'You do not have permission to trash this post.', 'et_builder_5' ) );
		}

		$trashed = wp_trash_post( $post_id );

		if ( ! $trashed ) {
			return self::response_error( 'trash_failed', esc_html__( 'Failed to move post to trash.', 'et_builder_5' ) );
		}

		return self::response_success( [] );
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
	 * Get the arguments for the duplicate action.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for the duplicate action.
	 */
	public static function duplicate_args(): array {
		return [
			'id' => [
				'required'          => true,
				'sanitize_callback' => 'absint',
			],
		];
	}

	/**
	 * Get the arguments for the create action.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for the create action.
	 */
	public static function create_args(): array {
		return [
			'title'       => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'post_type'   => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'post_status' => [
				'required'          => false,
				'type'              => 'string',
				'default'           => 'publish',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => [ self::class, 'validate_post_status_arg' ],
			],
			'post_date'   => [
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => [ self::class, 'validate_post_date_arg' ],
			],
		];
	}

	/**
	 * Get the arguments for the show action.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for the show action.
	 */
	public static function show_args(): array {
		return [
			'id' => [
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'validate_callback' => [ self::class, 'validate_positive_id_arg' ],
			],
		];
	}

	/**
	 * Get the arguments for the update action.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for the update action.
	 */
	public static function update_args(): array {
		return [
			'id'           => [
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'validate_callback' => [ self::class, 'validate_positive_id_arg' ],
			],
			'title'        => [
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => [ self::class, 'validate_optional_non_empty_string_arg' ],
			],
			'post_name'    => [
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_title',
				'validate_callback' => [ self::class, 'validate_optional_non_empty_string_arg' ],
			],
			'post_content' => [
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
				'validate_callback' => [ self::class, 'validate_optional_non_empty_string_arg' ],
			],
		];
	}

	/**
	 * Get the arguments for the update_status action.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for the update_status action.
	 */
	public static function update_status_args(): array {
		return [
			'id'          => [
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'validate_callback' => [ self::class, 'validate_positive_id_arg' ],
			],
			'post_status' => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => [ self::class, 'validate_post_status_arg' ],
			],
			'post_date'   => [
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => [ self::class, 'validate_post_date_arg' ],
			],
		];
	}

	/**
	 * Get the arguments for the search action.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for the search action.
	 */
	public static function search_args(): array {
		return [
			'id'          => [
				'required'          => false,
				'sanitize_callback' => 'absint',
			],
			'search'      => [
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'post_status' => [
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => [ self::class, 'validate_search_post_status_arg' ],
			],
			'slug'        => [
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'per_page'    => [
				'required'          => false,
				'sanitize_callback' => 'absint',
			],
			'page'        => [
				'required'          => false,
				'sanitize_callback' => 'absint',
			],
			'post_type'   => [
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => [ self::class, 'validate_post_type_arg' ],
			],
		];
	}

	/**
	 * Get the arguments for the update_order action.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for the update_order action.
	 */
	public static function update_order_args(): array {
		return [
			'post_type' => [
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'post_ids'  => [
				'required'          => true,
				'sanitize_callback' => function ( $value ) {
					if ( ! is_array( $value ) ) {
						return [];
					}
					return array_map( 'absint', $value );
				},
			],
		];
	}

	/**
	 * Get the arguments for the trash action.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for the trash action.
	 */
	public static function trash_args(): array {
		return [
			'id' => [
				'required'          => true,
				'sanitize_callback' => 'absint',
			],
		];
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

	/**
	 * Get the permission status for the duplicate action.
	 *
	 * This function checks if the current user has the permission to use the Visual Builder
	 * and can edit posts (required for duplicating posts).
	 *
	 * @since ??
	 *
	 * @return bool Returns `true` if the current user has the permission to use the Visual Builder and can edit posts, `false` otherwise.
	 */
	public static function duplicate_permission(): bool {
		return UserRole::can_current_user_use_visual_builder() && current_user_can( 'edit_posts' );
	}

	/**
	 * Get the permission status for the create action.
	 *
	 * This function checks if the current user has the permission to use the Visual Builder
	 * and can create posts (required for creating posts).
	 *
	 * @since ??
	 *
	 * @return bool Returns `true` if the current user has the permission to use the Visual Builder and can create posts, `false` otherwise.
	 */
	public static function create_permission(): bool {
		return UserRole::can_current_user_use_visual_builder() && current_user_can( 'edit_posts' );
	}

	/**
	 * Get the permission status for the show action.
	 *
	 * This gates the endpoint at the app level. Fine-grained
	 * `edit_post`/existence checks happen in the controller so the API
	 * can differentiate between "not found" and "access denied".
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return bool Returns `true` if the current user has search permissions, `false` otherwise.
	 */
	public static function show_permission( WP_REST_Request $request ): bool {
		$post_id = absint( $request->get_param( 'id' ) );

		if ( ! UserRole::can_current_user_use_visual_builder() || ! current_user_can( 'edit_posts' ) || 0 >= $post_id ) {
			return false;
		}

		// Let the endpoint callback return `post_not_found` when the post doesn't exist.
		if ( ! get_post( $post_id ) ) {
			return true;
		}

		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Get the permission status for the update action.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return bool Returns `true` if the current user has update permissions, `false` otherwise.
	 */
	public static function update_permission( WP_REST_Request $request ): bool {
		$post_id = absint( $request->get_param( 'id' ) );

		return UserRole::can_current_user_use_visual_builder() &&
			current_user_can( 'edit_posts' ) &&
			0 < $post_id &&
			current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Validate positive ID arguments.
	 *
	 * @since ??
	 *
	 * @param mixed           $value   Raw argument value.
	 * @param WP_REST_Request $request The REST request object.
	 * @param string          $param   Argument key.
	 *
	 * @return bool|WP_Error True when valid, `WP_Error` otherwise.
	 */
	public static function validate_positive_id_arg( $value, WP_REST_Request $request, string $param ) {
		if ( 0 < absint( $value ) ) {
			return true;
		}

		return new \WP_Error(
			'rest_invalid_param',
			esc_html__( 'Invalid post ID.', 'et_builder_5' ),
			[
				'status' => 400,
				'param'  => $param,
			]
		);
	}

	/**
	 * Validate optional non-empty string arguments.
	 *
	 * @since ??
	 *
	 * @param mixed           $value   Raw argument value.
	 * @param WP_REST_Request $request The REST request object.
	 * @param string          $param   Argument key.
	 *
	 * @return bool|WP_Error True when valid, `WP_Error` otherwise.
	 */
	public static function validate_optional_non_empty_string_arg( $value, WP_REST_Request $request, string $param ) {
		if ( null === $value ) {
			return true;
		}

		if ( is_string( $value ) && '' !== trim( $value ) ) {
			return true;
		}

		return new \WP_Error(
			'rest_invalid_param',
			esc_html__( 'This field cannot be empty.', 'et_builder_5' ),
			[
				'status' => 400,
				'param'  => $param,
			]
		);
	}

	/**
	 * Get allowed public post statuses for mutations.
	 *
	 * @since ??
	 *
	 * @return array<string> List of allowed statuses.
	 */
	public static function get_allowed_public_post_statuses(): array {
		return array_values( get_post_stati( [ 'internal' => false ] ) );
	}

	/**
	 * Validate post_status argument.
	 *
	 * @since ??
	 *
	 * @param mixed           $value   Raw argument value.
	 * @param WP_REST_Request $request The REST request object.
	 * @param string          $param   Argument key.
	 *
	 * @return bool|WP_Error True when valid, `WP_Error` otherwise.
	 */
	public static function validate_post_status_arg( $value, WP_REST_Request $request, string $param ) {
		if ( null === $value || '' === $value ) {
			return true;
		}

		$allowed_statuses = self::get_allowed_public_post_statuses();
		if ( ! in_array( $value, $allowed_statuses, true ) ) {
			return new \WP_Error(
				'rest_invalid_param',
				esc_html__( 'Invalid post status.', 'et_builder_5' ),
				[
					'status' => 400,
					'param'  => $param,
				]
			);
		}

		$post_date = $request->get_param( 'post_date' );
		if ( 'future' === $value && empty( $post_date ) ) {
			return new \WP_Error(
				'rest_invalid_param',
				esc_html__( 'Post date is required for scheduled posts.', 'et_builder_5' ),
				[
					'status' => 400,
					'param'  => 'post_date',
				]
			);
		}

		return true;
	}

	/**
	 * Validate post_date argument.
	 *
	 * @since ??
	 *
	 * @param mixed           $value   Raw argument value.
	 * @param WP_REST_Request $request The REST request object.
	 * @param string          $param   Argument key.
	 *
	 * @return bool|WP_Error True when valid, `WP_Error` otherwise.
	 */
	public static function validate_post_date_arg( $value, WP_REST_Request $request, string $param ) {
		if ( null === $value || '' === $value ) {
			return true;
		}

		if ( false === strtotime( (string) $value ) ) {
			return new \WP_Error(
				'rest_invalid_param',
				esc_html__( 'Invalid post date format.', 'et_builder_5' ),
				[
					'status' => 400,
					'param'  => $param,
				]
			);
		}

		return true;
	}

	/**
	 * Validate post_status argument for search endpoint.
	 *
	 * @since ??
	 *
	 * @param mixed           $value   Raw argument value.
	 * @param WP_REST_Request $request The REST request object.
	 * @param string          $param   Argument key.
	 *
	 * @return bool|WP_Error True when valid, `WP_Error` otherwise.
	 */
	public static function validate_search_post_status_arg( $value, $request, string $param ) {
		if ( null === $value || '' === $value ) {
			return true;
		}

		$allowed_statuses = array_values( get_post_stati() );
		if ( ! in_array( $value, $allowed_statuses, true ) ) {
			return new \WP_Error(
				'rest_invalid_param',
				esc_html__( 'Invalid post status.', 'et_builder_5' ),
				[
					'status' => 400,
					'param'  => $param,
				]
			);
		}

		return true;
	}

	/**
	 * Validate optional post_type argument.
	 *
	 * @since ??
	 *
	 * @param mixed           $value   Raw argument value.
	 * @param WP_REST_Request $request The REST request object.
	 * @param string          $param   Argument key.
	 *
	 * @return bool|WP_Error True when valid, `WP_Error` otherwise.
	 */
	public static function validate_post_type_arg( $value, WP_REST_Request $request, string $param ) {
		if ( null === $value || '' === $value ) {
			return true;
		}

		$post_type = sanitize_key( (string) $value );
		$post_type_object = get_post_type_object( $post_type );

		if ( ! $post_type_object ) {
			return new \WP_Error(
				'rest_invalid_param',
				esc_html__( 'Invalid post type.', 'et_builder_5' ),
				[
					'status' => 400,
					'param'  => $param,
				]
			);
		}

		return true;
	}

	/**
	 * Get the permission status for the update_status action.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return bool Returns `true` if the current user has update permissions, `false` otherwise.
	 */
	public static function update_status_permission( WP_REST_Request $request ): bool {
		$post_id = absint( $request->get_param( 'id' ) );

		if ( ! UserRole::can_current_user_use_visual_builder() || ! current_user_can( 'edit_posts' ) || 0 >= $post_id ) {
			return false;
		}

		// Let the endpoint callback return `post_not_found` when the post doesn't exist.
		if ( ! get_post( $post_id ) ) {
			return true;
		}

		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Get the permission status for the search action.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return bool Returns `true` if the current user has search permissions, `false` otherwise.
	 */
	public static function search_permission( WP_REST_Request $request ): bool {
		$post_type        = self::get_search_post_type( $request );
		$post_type_object = get_post_type_object( $post_type );

		if ( ! UserRole::can_current_user_use_visual_builder() || ! current_user_can( 'edit_posts' ) ) {
			return false;
		}

		// Keep input validation concerns in validate_post_type_arg().
		if ( ! $post_type_object || ! isset( $post_type_object->cap->edit_posts ) ) {
			return true;
		}

		return current_user_can( $post_type_object->cap->edit_posts );
	}

	/**
	 * Resolve the effective post type for search operations.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return string The effective post type slug.
	 */
	private static function get_search_post_type( WP_REST_Request $request ): string {
		$post_type = $request->get_param( 'post_type' );

		return is_string( $post_type ) && '' !== trim( $post_type ) ? sanitize_key( $post_type ) : 'page';
	}

	/**
	 * Get the permission status for the update_order action.
	 *
	 * This function checks if the current user has the permission to use the Visual Builder
	 * and can edit posts (required for reordering posts).
	 *
	 * @since ??
	 *
	 * @return bool Returns `true` if the current user has the permission to use the Visual Builder and can edit posts, `false` otherwise.
	 */
	public static function update_order_permission(): bool {
		return UserRole::can_current_user_use_visual_builder() && current_user_can( 'edit_posts' );
	}

	/**
	 * Get the permission status for the trash action.
	 *
	 * This function checks if the current user has the permission to use the Visual Builder
	 * and can delete posts (required for trashing posts).
	 *
	 * @since ??
	 *
	 * @return bool Returns `true` if the current user has the permission to use the Visual Builder and can delete posts, `false` otherwise.
	 */
	public static function trash_permission(): bool {
		return UserRole::can_current_user_use_visual_builder() && current_user_can( 'delete_posts' );
	}
}
