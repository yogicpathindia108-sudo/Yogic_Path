<?php
/**
 * InstagramFeed: InstagramFeedController.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\InstagramFeed;

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- WordPress REST API callbacks require specific signatures.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\Services\InstagramAccountService\InstagramAccountService;
use WP_REST_Request;
use WP_REST_Response;

/**
 * InstagramFeed REST Controller class.
 *
 * @since ??
 */
class InstagramFeedController extends RESTController {

	/**
	 * Return normalized instagram feed payload for VB preview.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function index( WP_REST_Request $request ): WP_REST_Response {
		$config        = $request->get_param( 'config' );
		$limit         = isset( $config['postCount'] ) ? intval( $config['postCount'] ) : 6;
		$force_refresh = $request->get_param( 'forceRefresh' );
		$media_items   = InstagramAccountService::fetch_media( $request->get_param( 'accountId' ), $limit, $force_refresh );

		if ( is_wp_error( $media_items ) ) {
			return self::response_error( $media_items->get_error_code(), $media_items->get_error_message(), [], 502 );
		}

		return self::response_success(
			[
				'items' => $media_items,
			]
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
			'accountId'    => [
				'description'       => esc_html__( 'The account ID.', 'et_builder_5' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'config'       => [
				'description'       => esc_html__( 'The feed config.', 'et_builder_5' ),
				'type'              => 'object',
				'required'          => false,
				'default'           => [
					'postCount' => 6,
				],
				'sanitize_callback' => [ self::class, 'sanitize_config' ],
			],
			'forceRefresh' => [
				'description'       => esc_html__( 'Whether fetch should bypass cache.', 'et_builder_5' ),
				'type'              => 'boolean',
				'required'          => false,
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			],
		];
	}

	/**
	 * Index action permission callback.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function index_permission(): bool {
		return UserRole::can_current_user_use_visual_builder();
	}

	/**
	 * Sanitize feed config for the index action.
	 *
	 * @since ??
	 *
	 * @param mixed                $value   Raw feed config.
	 * @param WP_REST_Request|null $request Request object.
	 * @param string               $param   Request parameter.
	 *
	 * @return array{postCount: int}
	 */
	public static function sanitize_config( $value, ?WP_REST_Request $request = null, string $param = '' ): array {
		$post_count = 6;

		if ( is_array( $value ) && isset( $value['postCount'] ) ) {
			$post_count = intval( $value['postCount'] );
		}

		$post_count = max( 1, min( InstagramAccountService::MAX_LIMIT, $post_count ) );

		return [
			'postCount' => $post_count,
		];
	}
}
