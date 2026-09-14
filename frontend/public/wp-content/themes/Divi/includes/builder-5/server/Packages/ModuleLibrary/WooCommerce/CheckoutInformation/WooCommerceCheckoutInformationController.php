<?php
/**
 * Module Library: WooCommerce Checkout Information Module REST Controller class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\CheckoutInformation;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\CheckoutInformation\WooCommerceCheckoutInformationModule;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_REST_Request;
use WP_REST_Response;

/**
 * WooCommerce Checkout Information REST Controller class.
 *
 * @since ??
 */
class WooCommerceCheckoutInformationController extends RESTController {

	/**
	 * Retrieve the rendered HTML for the WooCommerce Checkout Information module.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error Returns the REST response object containing the rendered HTML.
	 *                                  If the request is invalid, a `WP_Error` object is returned.
	 */
	public static function index( WP_REST_Request $request ) {
		$common_required_params = WooCommerceUtils::validate_woocommerce_request_params( $request );

		// If the conditional tags are not set, the returned value is an error.
		if ( ! isset( $common_required_params['conditional_tags'] ) ) {
			return self::response_error( ...$common_required_params );
		}

		$conditional_tags = $common_required_params['conditional_tags'];

		// Retrieve the product add to cart markup using the WooCommerceCheckoutInformationModule class.
		$add_to_checkout_information_html = WooCommerceCheckoutInformationModule::get_checkout_additional_info( $conditional_tags );

		$response = [
			'html' => $add_to_checkout_information_html,
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
