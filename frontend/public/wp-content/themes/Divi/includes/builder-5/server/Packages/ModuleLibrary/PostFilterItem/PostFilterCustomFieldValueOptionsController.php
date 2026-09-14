<?php
/**
 * Post Filter Item custom field value options REST controller.
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
 * REST endpoint for post-filter-item custom field value option discovery.
 *
 * @since ??
 */
class PostFilterCustomFieldValueOptionsController extends RESTController {
	/**
	 * Return distinct custom field values for module settings and rendering.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function index( WP_REST_Request $request ): WP_REST_Response {
		$post_type        = sanitize_key( $request->get_param( 'post_type' ) ?: 'post' );
		$field_option     = sanitize_text_field( (string) $request->get_param( 'field_option' ) );
		$custom_field_key = sanitize_text_field( (string) $request->get_param( 'custom_field_key' ) );
		$field_value_type = sanitize_key( (string) $request->get_param( 'field_value_type' ) );
		$label_date_format = sanitize_text_field( (string) $request->get_param( 'label_date_format' ) );
		$bypass_cache     = rest_sanitize_boolean( $request->get_param( 'bypass_cache' ) );

		if ( '' === $field_value_type ) {
			$field_value_type = 'date';
		}

		if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
			$post_type = 'post';
		}

		return self::response_success(
			PostFilterCustomFieldValueOptions::get_value_options_response(
				$post_type,
				$field_option,
				$custom_field_key,
				$bypass_cache,
				$field_value_type,
				$label_date_format
			)
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
			'post_type'        => [
				'description' => __( 'Post type slug used to resolve custom field values.', 'et_builder_5' ),
				'type'        => 'string',
				'required'    => false,
				'default'     => 'post',
			],
			'field_option'     => [
				'description' => __( 'Selected field option key (for example custom_field:price).', 'et_builder_5' ),
				'type'        => 'string',
				'required'    => true,
			],
			'custom_field_key' => [
				'description' => __( 'Manual meta key when field option is custom_field:manual.', 'et_builder_5' ),
				'type'        => 'string',
				'required'    => false,
				'default'     => '',
			],
			'field_value_type' => [
				'description' => __( 'Selected field value type used to format discovered post date values.', 'et_builder_5' ),
				'type'        => 'string',
				'required'    => false,
				'default'     => 'date',
			],
			'label_date_format' => [
				'description' => __( 'PHP date format string used to format discovered option labels.', 'et_builder_5' ),
				'type'        => 'string',
				'required'    => false,
				'default'     => '',
			],
			'bypass_cache'     => [
				'description' => __( 'Whether to bypass the transient cache and refresh values.', 'et_builder_5' ),
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
