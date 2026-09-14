<?php
/**
 * Module Library: WooCommerce Checkout Details Module REST Controller class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\CheckoutOrderDetails;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_REST_Request;
use WP_REST_Response;

/**
 * WooCommerce Checkout Details REST Controller class.
 *
 * @since ??
 */
class WooCommerceCheckoutOrderDetailsController extends RESTController {

	/**
	 * Retrieve the rendered HTML for the WooCommerce Checkout Details module.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error Returns the REST response object containing the rendered HTML.
	 */
	public static function index( WP_REST_Request $request ) {
		// Validate request using WooCommerce utilities.
		$common_required_params = WooCommerceUtils::validate_woocommerce_request_params( $request );

		// If the conditional tags are not set, the returned value is an error.
		if ( ! isset( $common_required_params['conditional_tags'] ) ) {
			return self::response_error( ...$common_required_params );
		}

		$conditional_tags = $common_required_params['conditional_tags'] ?? [];

		// Ensure WooCommerce objects are initialized for REST context.
		WooCommerceUtils::ensure_woocommerce_objects_initialized( $conditional_tags );

		// Get the checkout order details HTML.
		$html = WooCommerceCheckoutOrderDetailsModule::get_checkout_order_details( $conditional_tags );

		$response = [
			'html' => $html,
		];

		return self::response_success( $response );
	}

	/**
	 * Get the arguments for the index action.
	 *
	 * This function returns an array that defines the arguments for the index action,
	 * which is used in the `register_rest_route()` function.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for the index action.
	 */
	public static function index_args(): array {
		return [
			'conditionalTags' => [
				'type'                 => 'object',
				'required'             => false,
				'default'              => [],
				'description'          => 'Conditional tags for preview modes.',
				'additionalProperties' => [
					'type' => [ 'string', 'boolean' ],
				],
				'sanitize_callback'    => function ( $param ) {
					if ( ! is_array( $param ) ) {
						return [];
					}
					$sanitized = [];
					foreach ( $param as $key => $value ) {
						$sanitized[ sanitize_text_field( $key ) ] = is_bool( $value ) ? $value : sanitize_text_field( $value );
					}
					return $sanitized;
				},
				'validate_callback'    => function ( $param ) {
					if ( [] === $param ) {
						return true;
					}

					if ( ! is_array( $param ) ) {
						return false;
					}

					// Ensure valid key-value pairs.
					foreach ( $param as $key => $value ) {
						if ( empty( $key ) || ! is_string( $key ) ) {
							return false;
						}
						if ( ! is_string( $value ) && ! is_bool( $value ) ) {
							return false;
						}
					}

					return true;
				},
			],
		];
	}

	/**
	 * Provides the permission status for the index action.
	 *
	 * @since ??
	 *
	 * @return bool Returns `true` if the current user has the permission to use the rest endpoint, otherwise `false`.
	 */
	public static function index_permission(): bool {
		return UserRole::can_current_user_use_visual_builder();
	}
}
