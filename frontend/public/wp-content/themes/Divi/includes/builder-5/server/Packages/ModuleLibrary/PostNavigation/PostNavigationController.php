<?php
/**
 * PostNavigation: PostNavigationController.
 *
 * @package Builder\Framework\Route
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostNavigation;

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- WordPress REST API callbacks require specific signatures.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use WP_REST_Request;
use WP_REST_Response;

/**
 * PostNavigation REST Controller class.
 *
 * @since ??
 */
class PostNavigationController extends RESTController {

	/**
	 * Return post navigation data for the Post Navigation module.
	 *
	 * When a targetLoop is provided (not 'main_query'), this endpoint returns
	 * only the next button for loop-connected pagination.
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response
	 * @since ??
	 */
	public static function index( WP_REST_Request $request ): WP_REST_Response {
		$args = [
			'post_id'       => $request->get_param( 'postId' ),
			'in_same_term'  => $request->get_param( 'inSameTerm' ),
			'taxonomy_name' => $request->get_param( 'taxonomyName' ),
			'prev_text'     => $request->get_param( 'prevText' ),
			'next_text'     => $request->get_param( 'nextText' ),
			'target_loop'   => $request->get_param( 'targetLoop' ),
			'is_vb'         => $request->get_param( 'isVB' ) ?? false,
		];

		$target_loop = $args['target_loop'] ?? 'main_query';

		// Check if this is a loop connection request.
		if ( 'main_query' !== $target_loop ) {
			// Get pagination for connected loop.
			$posts_navigation = PostNavigationModule::get_loop_pagination( $target_loop, $args );
		} else {
			// Use default post navigation behavior.
			$posts_navigation = PostNavigationModule::get_post_navigation( $args );
		}

		$response = [
			'postsNavigation' => $posts_navigation,
		];

		return self::response_success( $response );
	}

	/**
	 * Index action arguments.
	 *
	 * Endpoint arguments as used in `register_rest_route()`.
	 *
	 * @return array
	 */
	public static function index_args(): array {
		return [
			'postId'       => [
				'type'              => 'number',
				'default'           => -1,
				'sanitize_callback' => function ( $value, $request, $param ) {
					return (int) $value;
				},
			],
			'inSameTerm'   => [
				'type'              => 'string',
				'default'           => 'off',
				'validate_callback' => function ( $param, $request, $key ) {
					return 'on' === $param || 'off' === $param;
				},
			],
			'taxonomyName' => [
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => function ( $value, $request, $param ) {
					return is_string( $value ) ? $value : '';
				},
			],
			'prevText'     => [
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => function ( $value, $request, $param ) {
					return is_string( $value ) ? $value : '';
				},
			],
			'nextText'     => [
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => function ( $value, $request, $param ) {
					return is_string( $value ) ? $value : '';
				},
			],
			'targetLoop'   => [
				'type'              => 'string',
				'default'           => 'main_query',
				'sanitize_callback' => function ( $value, $request, $param ) {
					return is_string( $value ) ? $value : 'main_query';
				},
			],
			'isVB'         => [
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => function ( $value, $request, $param ) {
					return (bool) $value;
				},
			],
		];
	}

	/**
	 * Index action permission.
	 *
	 * Endpoint permission callback as used in `register_rest_route()`.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 *
	 * @return bool
	 */
	public static function index_permission( \WP_REST_Request $request ): bool {
		if ( ! UserRole::can_current_user_use_visual_builder() ) {
			return false;
		}

		$target_loop = sanitize_text_field( (string) $request->get_param( 'targetLoop' ) );

		if ( '' !== $target_loop && 'main_query' !== $target_loop ) {
			return true;
		}

		$post_id = (int) $request->get_param( 'postId' );

		return 0 < $post_id
			&& current_user_can( 'read_post', $post_id );
	}
}
