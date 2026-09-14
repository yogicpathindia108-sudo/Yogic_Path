<?php
/**
 * Module Library: WooCommerce Product Description Module REST Controller class
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductDescription;

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- WordPress REST API callbacks require specific signatures.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductDescription\WooCommerceProductDescriptionModule;
use ET\Builder\Framework\Controllers\RESTController;

/**
 * WooCommerce Product Description REST Controller class.
 *
 * Handles the REST API endpoint to fetch WooCommerce product descriptions.
 * Validates incoming request parameters, retrieves the product description
 * via the module, and returns sanitized HTML output.
 *
 * @since ??
 */
class WooCommerceProductDescriptionController extends RESTController {

	/**
	 * Retrieve the rendered HTML for the WooCommerce Product Description module.
	 *
	 * Processes the REST API request to generate and return the HTML content
	 * for the WooCommerce Product Description module with the specified product and description type.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object containing parameters.
	 *
	 * @return WP_REST_Response|WP_Error The REST response object containing the rendered HTML,
	 *                                   or a WP_Error object if the request is invalid.
	 */
	public static function index( WP_REST_Request $request ) {
		$common_required_params = WooCommerceUtils::validate_woocommerce_request_params( $request );

		// If the conditional tags are not set, the returned value is an error.
		if ( ! isset( $common_required_params['conditional_tags'] ) ) {
			return self::response_error( ...$common_required_params );
		}

		$params     = $request->get_params();
		$product_id = $params['productId'] ?? 'current';

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

		$product_id       = $product->get_id();
		$description_type = $params['descriptionType'];

		// Retrieve the product description HTML using the module method.
		$html = WooCommerceProductDescriptionModule::get_description( $product_id, $description_type );

		return self::response_success(
			[
				'html' => $html,
			]
		);
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
			'productId'       => [
				'required'          => false,
				'sanitize_callback' => function ( $param ) {
					$param = sanitize_text_field( $param );

					return ( 'current' !== $param && 'latest' !== $param ) ? absint( $param ) : $param;
				},
				'validate_callback' => function ( $param, $request ) {
					return WooCommerceUtils::validate_product_id( $param, $request );
				},
			],
			'descriptionType' => [
				'required'          => false,
				'sanitize_callback' => function ( $param ) {
					return sanitize_text_field( $param );
				},
				'validate_callback' => function ( $param, $request ) {
					$allowed = [ 'short_description', 'description' ];

					return in_array( $param, $allowed, true );
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
