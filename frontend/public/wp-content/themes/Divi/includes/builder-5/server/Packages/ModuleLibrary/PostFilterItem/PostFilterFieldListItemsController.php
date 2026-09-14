<?php
/**
 * Post Filter Item field list items REST controller.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostFilterItem;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST endpoint for post-filter-item list control options.
 *
 * @since ??
 */
class PostFilterFieldListItemsController extends RESTController {
	/**
	 * Return list items for a field type and option key.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function index( WP_REST_Request $request ): WP_REST_Response {
		$post_type                    = sanitize_key( $request->get_param( 'post_type' ) ?: 'post' );
		$field_type                   = sanitize_key( $request->get_param( 'field_type' ) ?: '' );
		$field_option_key             = sanitize_key( $request->get_param( 'field_option' ) ?: '' );
		$include_term_ids             = $request->get_param( 'include_term_ids' );
		$exclude_term_ids             = $request->get_param( 'exclude_term_ids' );
		$has_include_term_restriction = $request->get_param( 'has_include_term_restriction' );

		$loop_term_restrictions = [];

		if ( is_array( $include_term_ids ) || is_array( $exclude_term_ids ) || null !== $has_include_term_restriction ) {
			$loop_term_restrictions = [
				'include'                 => is_array( $include_term_ids ) ? array_map( 'intval', $include_term_ids ) : [],
				'exclude'                 => is_array( $exclude_term_ids ) ? array_map( 'intval', $exclude_term_ids ) : [],
				'has_include_restriction' => filter_var( $has_include_term_restriction, FILTER_VALIDATE_BOOLEAN ),
			];
		}

		$list_items = PostFilterFieldListItems::get_list_items(
			$field_type,
			$field_option_key,
			$post_type,
			[],
			'post_types',
			$loop_term_restrictions
		);

		return self::response_success( $list_items );
	}

	/**
	 * Index action arguments.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function index_args(): array {
		return [
			'post_type'                    => [
				'description' => __( 'Post type slug used to resolve taxonomy and author list items.', 'et_builder_5' ),
				'type'        => 'string',
				'required'    => false,
				'default'     => 'post',
			],
			'field_type'                   => [
				'description' => __( 'Post filter item field type.', 'et_builder_5' ),
				'type'        => 'string',
				'required'    => true,
			],
			'field_option'                 => [
				'description' => __( 'Post filter item field option key.', 'et_builder_5' ),
				'type'        => 'string',
				'required'    => false,
				'default'     => '',
			],
			'include_term_ids'             => [
				'description' => __( 'Optional include term IDs for loop-scoped taxonomy list items.', 'et_builder_5' ),
				'type'        => 'array',
				'required'    => false,
				'default'     => [],
				'items'       => [
					'type' => 'integer',
				],
			],
			'exclude_term_ids'             => [
				'description' => __( 'Optional exclude term IDs for loop-scoped taxonomy list items.', 'et_builder_5' ),
				'type'        => 'array',
				'required'    => false,
				'default'     => [],
				'items'       => [
					'type' => 'integer',
				],
			],
			'has_include_term_restriction' => [
				'description' => __( 'Whether include term restrictions are configured for the requested taxonomy.', 'et_builder_5' ),
				'type'        => 'boolean',
				'required'    => false,
				'default'     => false,
			],
		];
	}

	/**
	 * Index action permission.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function index_permission(): bool {
		return UserRole::can_current_user_use_visual_builder();
	}
}
