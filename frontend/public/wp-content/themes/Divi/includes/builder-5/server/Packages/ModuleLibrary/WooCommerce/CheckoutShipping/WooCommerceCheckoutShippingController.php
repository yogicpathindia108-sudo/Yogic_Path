<?php
/**
 * Module Library: WooCommerce Checkout Shipping Module REST Controller class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\CheckoutShipping;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\CheckoutShipping\WooCommerceCheckoutShippingModule;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * WooCommerce Checkout Shipping REST Controller class.
 *
 * @since ??
 */
class WooCommerceCheckoutShippingController extends RESTController {

	/**
	 * Retrieve the rendered HTML for the WooCommerce Checkout Shipping module.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error The REST response object containing the rendered HTML or error details.
	 */
	public static function index( WP_REST_Request $request ) {
		$common_required_params = WooCommerceUtils::validate_woocommerce_request_params( $request );

		// If the conditional tags are not set, the returned value is an error.
		if ( ! isset( $common_required_params['conditional_tags'] ) ) {
			return self::response_error( ...$common_required_params );
		}

		$cart_shipping_html = WooCommerceCheckoutShippingModule::get_checkout_shipping(
			$common_required_params['conditional_tags']
		);

		$response = [
			'html' => $cart_shipping_html,
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
		return [];
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
