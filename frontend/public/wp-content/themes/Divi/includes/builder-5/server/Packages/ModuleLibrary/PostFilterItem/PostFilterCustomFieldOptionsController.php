<?php
/**
 * Post Filter Item custom field options REST controller.
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
 * REST endpoint for post-filter-item custom field option discovery.
 *
 * @since ??
 */
class PostFilterCustomFieldOptionsController extends RESTController {
	/**
	 * Return grouped custom field option choices for module settings.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function index( WP_REST_Request $request ): WP_REST_Response {
		$post_type         = sanitize_key( $request->get_param( 'post_type' ) ? $request->get_param( 'post_type' ) : 'post' );
		$filter_field_type = (string) ( $request->get_param( 'filter_field_type' ) ? $request->get_param( 'filter_field_type' ) : '' );

		if ( ! PostFilterCustomFieldUtils::is_filter_field_type( $filter_field_type ) ) {
			$filter_field_type = '';
		}

		if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
			$post_type = 'post';
		}

		return self::response_success(
			PostFilterCustomFieldUtils::get_field_option_choices( $post_type, $filter_field_type )
		);
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
			'post_type' => [
				'description' => __( 'Post type slug used to resolve custom field option choices.', 'et_builder_5' ),
				'type'        => 'string',
				'required'    => false,
				'default'     => 'post',
			],
			'filter_field_type' => [
				'description' => __( 'Optional post-filter field type used to filter ACF field choices.', 'et_builder_5' ),
				'type'        => 'string',
				'required'    => false,
				'default'     => '',
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
