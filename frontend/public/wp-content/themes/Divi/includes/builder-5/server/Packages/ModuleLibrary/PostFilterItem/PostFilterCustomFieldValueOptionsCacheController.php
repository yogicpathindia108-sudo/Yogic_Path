<?php
/**
 * Post Filter Item custom field value options cache REST controller.
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
 * REST endpoint for clearing cached post-filter-item custom field value options.
 *
 * @since ??
 */
class PostFilterCustomFieldValueOptionsCacheController extends RESTController {
	/**
	 * Clear cached custom field value options for one post type and meta key.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function delete( WP_REST_Request $request ): WP_REST_Response {
		$post_type        = sanitize_key( $request->get_param( 'post_type' ) ?: 'post' );
		$field_option     = sanitize_text_field( (string) $request->get_param( 'field_option' ) );
		$custom_field_key = sanitize_text_field( (string) $request->get_param( 'custom_field_key' ) );

		if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
			$post_type = 'post';
		}

		if ( PostFilterCustomFieldUtils::is_post_date_field_value_option( $field_option ) ) {
			PostFilterCustomFieldValueOptions::flush_post_date_column_caches( $post_type );

			return self::response_success(
				[
					'cleared' => true,
				]
			);
		}

		$meta_key = PostFilterCustomFieldUtils::resolve_meta_key( $field_option, $custom_field_key );

		if ( '' === $meta_key ) {
			return self::response_error( __( 'A valid custom field meta key is required.', 'et_builder_5' ), 400 );
		}

		$cleared = PostFilterCustomFieldValueOptions::clear_cache( $post_type, $meta_key );

		return self::response_success(
			[
				'cleared' => $cleared,
			]
		);
	}

	/**
	 * Delete action arguments.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function delete_args(): array {
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
		];
	}

	/**
	 * Delete action permission.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function delete_permission(): bool {
		return UserRole::can_current_user_use_visual_builder();
	}
}
