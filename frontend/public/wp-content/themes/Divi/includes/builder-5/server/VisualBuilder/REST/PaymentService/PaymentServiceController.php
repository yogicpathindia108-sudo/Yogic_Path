<?php
/**
 * REST: PaymentServiceController class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\REST\PaymentService;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\Services\PaymentAccountService\PaymentAccountService;
use WP_REST_Request;
use WP_REST_Response;

/**
 * PaymentServiceController class.
 *
 * @since ??
 */
class PaymentServiceController extends RESTController {
	/**
	 * Normalize provider resource value while preserving encoded URL octets.
	 *
	 * @since ??
	 *
	 * @param mixed $value Raw request value.
	 *
	 * @return string
	 */
	public static function normalize_resource_value( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return trim( wp_unslash( (string) $value ) );
	}

	/**
	 * Write payment service debug log.
	 *
	 * @since ??
	 *
	 * @param string $message Log message.
	 * @param array  $context Context payload.
	 *
	 * @return void
	 */
	private static function _debug_log( string $message, array $context = [] ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log(
			sprintf(
				'[Divi Builder 5][PaymentServiceController] %1$s | context=%2$s',
				$message,
				(string) wp_json_encode( $context )
			)
		);
	}

	/**
	 * Create resource for payment provider.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function create( WP_REST_Request $request ): WP_REST_Response {
		$services = PaymentAccountService::create_resource(
			$request->get_param( 'provider' ),
			(string) $request->get_param( 'resource_value' ),
			(string) $request->get_param( 'resource_name' )
		);

		if ( is_wp_error( $services ) ) {
			self::_debug_log(
				'Create payment resource request failed.',
				[
					'provider'  => sanitize_text_field( (string) $request->get_param( 'provider' ) ),
					'errorCode' => sanitize_text_field( $services->get_error_code() ),
					'error'     => sanitize_text_field( $services->get_error_message() ),
				]
			);

			return self::response_error(
				sanitize_text_field( $services->get_error_code() ),
				sanitize_text_field( $services->get_error_message() ),
				[],
				422
			);
		}

		return self::response_success(
			[
				'services' => $services,
			]
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
			'provider'       => [
				'description'       => esc_html__( 'Payment provider key.', 'et_builder_5' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'resource_value' => [
				'description'       => esc_html__( 'Provider resource value (email or checkout URL).', 'et_builder_5' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => [ self::class, 'normalize_resource_value' ],
			],
			'resource_name'  => [
				'description'       => esc_html__( 'Payment resource display name.', 'et_builder_5' ),
				'type'              => 'string',
				'required'          => false,
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
	 * Delete resource for payment provider.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function delete( WP_REST_Request $request ): WP_REST_Response {
		return self::response_success(
			[
				'services' => PaymentAccountService::delete_resource(
					$request->get_param( 'provider' ),
					$request->get_param( 'resourceId' )
				),
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
			'provider'   => [
				'description'       => esc_html__( 'Payment provider key.', 'et_builder_5' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'resourceId' => [
				'description'       => esc_html__( 'Payment resource ID.', 'et_builder_5' ),
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
	 * Update resource for payment provider.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function update( WP_REST_Request $request ): WP_REST_Response {
		$services = PaymentAccountService::update_resource(
			$request->get_param( 'provider' ),
			$request->get_param( 'resourceId' ),
			(string) $request->get_param( 'resource_name' ),
			(string) $request->get_param( 'resource_value' )
		);

		if ( is_wp_error( $services ) ) {
			self::_debug_log(
				'Update payment resource request failed.',
				[
					'provider'   => sanitize_text_field( (string) $request->get_param( 'provider' ) ),
					'resourceId' => sanitize_text_field( (string) $request->get_param( 'resourceId' ) ),
					'errorCode'  => sanitize_text_field( $services->get_error_code() ),
					'error'      => sanitize_text_field( $services->get_error_message() ),
				]
			);

			return self::response_error(
				sanitize_text_field( $services->get_error_code() ),
				sanitize_text_field( $services->get_error_message() ),
				[],
				422
			);
		}

		return self::response_success(
			[
				'services' => $services,
			]
		);
	}

	/**
	 * Update action arguments.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function update_args(): array {
		return [
			'provider'       => [
				'description'       => esc_html__( 'Payment provider key.', 'et_builder_5' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'resourceId'     => [
				'description'       => esc_html__( 'Payment resource ID.', 'et_builder_5' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'resource_name'  => [
				'description'       => esc_html__( 'Payment resource display name.', 'et_builder_5' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'resource_value' => [
				'description'       => esc_html__( 'Provider resource value (email or checkout URL).', 'et_builder_5' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => [ self::class, 'normalize_resource_value' ],
			],
		];
	}

	/**
	 * Update action permission callback.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function update_permission(): bool {
		return UserRole::can_current_user_use_visual_builder() && current_user_can( 'manage_options' );
	}

	/**
	 * Read payment resources/services.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function read( WP_REST_Request $request ): WP_REST_Response {
		return self::response_success(
			[
				'services' => PaymentAccountService::definition(),
			]
		);
	}

	/**
	 * Read action arguments.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function read_args(): array {
		return [];
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
