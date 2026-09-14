<?php
/**
 * REST: InstagramServiceController class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\REST\InstagramService;

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
 * InstagramServiceController class.
 *
 * @since ??
 */
class InstagramServiceController extends RESTController {

	/**
	 * Create a new account for an instagram service provider.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function create( WP_REST_Request $request ): WP_REST_Response {
		return self::response_success(
			InstagramAccountService::create_account(
				$request->get_param( 'account_name' ),
				$request->get_param( 'access_token' )
			)
		);
	}

	/**
	 * Create action arguments.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function create_args(): array {
		return [
			'account_name' => [
				'description'       => esc_html__( 'The account name.', 'et_builder_5' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'access_token' => [
				'description'       => esc_html__( 'The Instagram access token.', 'et_builder_5' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Create action permission callback.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function create_permission(): bool {
		return UserRole::can_current_user_use_visual_builder() && current_user_can( 'manage_options' );
	}

	/**
	 * Delete an account for a instagram service provider.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function delete( WP_REST_Request $request ): WP_REST_Response {
		return self::response_success( InstagramAccountService::delete_account( $request->get_param( 'accountId' ) ) );
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
			'accountId' => [
				'description'       => esc_html__( 'The account ID.', 'et_builder_5' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Delete action permission callback.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function delete_permission(): bool {
		return UserRole::can_current_user_use_visual_builder() && current_user_can( 'manage_options' );
	}

	/**
	 * Read account for specific instagram service provider.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function read( WP_REST_Request $request ): WP_REST_Response {
		return self::response_success( InstagramAccountService::fetch_account( $request->get_param( 'accountId' ) ) );
	}

	/**
	 * Read action arguments.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function read_args(): array {
		return [
			'accountId' => [
				'description'       => esc_html__( 'The account ID.', 'et_builder_5' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Read action permission callback.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function read_permission(): bool {
		return UserRole::can_current_user_use_visual_builder() && current_user_can( 'manage_options' );
	}
}
