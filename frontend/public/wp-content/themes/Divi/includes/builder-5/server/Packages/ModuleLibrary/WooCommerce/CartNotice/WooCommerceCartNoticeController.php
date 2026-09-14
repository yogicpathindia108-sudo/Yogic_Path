<?php
/**
 * Module Library: WooCommerce Notice Module REST Controller class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\CartNotice;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\CartNotice\WooCommerceCartNoticeModule;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * WooCommerce Notice REST Controller class.
 *
 * @since ??
 */
class WooCommerceCartNoticeController extends RESTController {

	/**
	 * Retrieve the rendered HTML for the WooCommerce Cart Notice module.
	 *
	 * Processes the REST API request to generate and return the HTML content
	 * for the WooCommerce Cart Notice module with the specified product and page type.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object containing parameters.
	 *
	 * @return WP_REST_Response|WP_Error The REST response object containing the rendered HTML or error details.
	 */
	public static function index( WP_REST_Request $request ) {
		$common_required_params = WooCommerceUtils::validate_woocommerce_request_params( $request );

		// If the conditional tags are not set, the returned value is an error.
		if ( ! isset( $common_required_params['conditional_tags'] ) ) {
			return self::response_error( ...$common_required_params );
		}

		$product_id = $request->get_param( 'productId' ) ?? 'current';

		// This will convert 'current', 'latest', or numeric IDs to an actual product ID.
		$product = WooCommerceUtils::get_product( $product_id );

		// Return error if not a valid product ID.
		if ( ! $product ) {
			return self::response_error(
				'product_not_found',
				__( 'Product not found.', 'divi' ),
				[ 'status' => 404 ],
				404
			);
		}

		$args             = [ 'product' => $product->get_id() ];
		$conditional_tags = $common_required_params['conditional_tags'];

		if ( $request->get_param( 'pageType' ) ) {
			$args['page_type'] = $request->get_param( 'pageType' );
		}

		// Retrieve the cart notice HTML using the WooCommerceCartNoticeModule class.
		$notice_html = WooCommerceCartNoticeModule::get_cart_notice( $args, $conditional_tags );

		$response = [
			'html' => $notice_html,
		];

		return self::response_success( $response );
	}

	/**
	 * Get the arguments configuration for the index action.
	 *
	 * Returns an array that defines the parameter validation and sanitization
	 * rules for the index action, used in the `register_rest_route()` function.
	 *
	 * @since ??
	 *
	 * @return array[] An array of argument configurations for the index action.
	 */
	public static function index_args(): array {
		return [
			'productId' => [
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => function ( $param ) {
					$param = sanitize_text_field( $param );

					return ( 'current' !== $param && 'latest' !== $param ) ? absint( $param ) : $param;
				},
				'validate_callback' => function ( $param, $request ) {
					return WooCommerceUtils::validate_product_id( $param, $request );
				},
			],
			'pageType'  => [
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => function ( $param ) {
					return sanitize_text_field( $param );
				},
				'validate_callback' => function ( $param ) {
					return in_array( $param, [ 'product', 'cart', 'checkout' ], true );
				},
			],
		];
	}

	/**
	 * Check if the current user has permission to access the index action.
	 *
	 * Determines whether the current user has the necessary capability
	 * to use the Visual Builder and access this REST endpoint.
	 *
	 * @since ??
	 *
	 * @return bool Returns `true` if the current user has permission to use the REST endpoint, otherwise `false`.
	 */
	public static function index_permission( WP_REST_Request $request ): bool {
		$product_id = $request->get_param( 'productId' ) ?? 'current';

		return WooCommerceUtils::can_current_user_render_product( $product_id );
	}
}
