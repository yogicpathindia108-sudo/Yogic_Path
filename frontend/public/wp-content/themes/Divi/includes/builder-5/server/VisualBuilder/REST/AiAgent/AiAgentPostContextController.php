<?php
/**
 * REST: AiAgentPostContextController class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\REST\AiAgent;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\VisualBuilder\REST\PageManager\PageManagerController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for AI Agent composer `@` post context listing.
 *
 * Catalog policy lives here, not on Page Manager: every post type the current
 * user can edit in wp-admin (`show_ui`), including non-public CPTs such as
 * Divi Library layouts (`et_pb_layout`). Selected posts are used as model
 * context, not displayed as front-end content. Attachments and types without
 * an admin UI are excluded. Rows themselves are loaded through Page Manager
 * search with a concrete slug so Page Manager's own catalog is unchanged.
 *
 * @since ??
 */
class AiAgentPostContextController extends RESTController {

	/**
	 * Lists composer `@` post context candidates, one page per editable post type.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response The grouped `@` post context response.
	 */
	public static function search( WP_REST_Request $request ): WP_REST_Response {
		$post_types = self::_get_editable_post_types();

		if ( empty( $post_types ) ) {
			return self::response_success( [ 'groups' => [] ] );
		}

		$per_page_param = $request->get_param( 'per_page' );
		$page_param     = $request->get_param( 'page' );
		$search         = $request->get_param( 'search' );
		$exclude        = $request->get_param( 'exclude' );
		$per_page       = absint( null !== $per_page_param ? $per_page_param : 20 );
		$page           = absint( null !== $page_param ? $page_param : 1 );

		$per_page = max( 1, min( $per_page, 100 ) );
		$page     = max( 1, $page );

		$query_params = [
			'per_page' => $per_page,
			'page'     => $page,
		];

		if ( is_string( $search ) && '' !== trim( $search ) ) {
			$query_params['search'] = $search;
		}

		$exclude_id = absint( $exclude );
		if ( 0 < $exclude_id ) {
			$query_params['exclude'] = $exclude_id;
		}

		$groups = [];

		foreach ( $post_types as $post_type ) {
			$sub_request = new WP_REST_Request( 'GET', '/divi/v1/page-manager/search' );
			$sub_request->set_query_params(
				array_merge(
					$query_params,
					[
						'post_type' => $post_type,
					]
				)
			);

			$response = PageManagerController::search( $sub_request );

			if ( is_wp_error( $response ) ) {
				continue;
			}

			$data = $response->get_data();

			if ( ! is_array( $data ) || empty( $data['results'] ) ) {
				continue;
			}

			$meta = isset( $data['meta'] ) && is_array( $data['meta'] ) ? $data['meta'] : [];

			$groups[] = [
				'post_type'   => $post_type,
				'results'     => $data['results'],
				'total'       => $meta['total'] ?? count( $data['results'] ),
				'total_pages' => $meta['total_pages'] ?? 1,
			];
		}

		return self::response_success( [ 'groups' => $groups ] );
	}

	/**
	 * Search route args.
	 *
	 * @since ??
	 *
	 * @return array Route arguments for the context-posts endpoint.
	 */
	public static function search_args(): array {
		return [
			'search'   => [
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'per_page' => [
				'required'          => false,
				'sanitize_callback' => 'absint',
			],
			'page'     => [
				'required'          => false,
				'sanitize_callback' => 'absint',
			],
			'exclude'  => [
				'required'          => false,
				'sanitize_callback' => 'absint',
			],
		];
	}

	/**
	 * Search permission callback.
	 *
	 * @since ??
	 *
	 * @return bool|WP_Error True when the current user can list `@` post context.
	 */
	public static function search_permission() {
		if ( ! UserRole::can_current_user_use_visual_builder() || ! current_user_can( 'edit_posts' ) ) {
			return self::response_error_permission();
		}

		return true;
	}

	/**
	 * Post types the current user can attach as AI Agent `@` context.
	 *
	 * `public` on the CPT is not the access gate. `show_ui` is the proxy for
	 * documents the user can already open in wp-admin. Attachments and types
	 * without an admin UI stay out of the catalog.
	 *
	 * @since ??
	 *
	 * @return string[] Post type slugs.
	 */
	private static function _get_editable_post_types(): array {
		$post_types = get_post_types(
			[
				'show_ui' => true,
			],
			'objects'
		);

		$editable = [];

		foreach ( $post_types as $post_type => $post_type_object ) {
			if ( 'attachment' === $post_type ) {
				continue;
			}

			if ( ! isset( $post_type_object->cap->edit_posts ) ) {
				continue;
			}

			if ( ! current_user_can( $post_type_object->cap->edit_posts ) ) {
				continue;
			}

			$editable[] = $post_type;
		}

		return $editable;
	}

}
