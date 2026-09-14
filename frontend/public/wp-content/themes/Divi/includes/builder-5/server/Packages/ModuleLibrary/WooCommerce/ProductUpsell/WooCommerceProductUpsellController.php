<?php
/**
 * Module Library: WooCommerce Product Upsell Module REST Controller class.
 *
 * This file contains the REST controller for handling WooCommerce Product Upsell
 * module requests, including rendering HTML and managing module parameters.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductUpsell;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * WooCommerce Product Upsell REST Controller class.
 *
 * Handles REST API endpoints for the WooCommerce Product Upsell module,
 * providing functionality to retrieve and render upsell product data.
 *
 * @since ??
 */
class WooCommerceProductUpsellController extends RESTController {

	/**
	 * Retrieve the rendered HTML for the WooCommerce Product Upsell module.
	 *
	 * This method processes the REST request to generate and return the HTML
	 * for displaying WooCommerce product upsells based on the provided parameters.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object containing module parameters.
	 *
	 * @return WP_REST_Response|WP_Error Returns the REST response object containing the rendered HTML,
	 *                                   or WP_Error if the request fails validation.
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

		// Warn if not a valid product ID.
		if ( ! $product ) {
			return self::response_error(
				'product_not_found',
				__( 'Product not found.', 'divi' ),
				[ 'status' => 404 ],
				404
			);
		}

		$posts_number   = $request->get_param( 'posts_number' );
		$columns_number = $request->get_param( 'columns_number' );
		$orderby        = $request->get_param( 'orderby' );
		$offset_number  = $request->get_param( 'offset_number' );

		$args = [ 'product' => $product->get_id() ];

		if ( 0 === $posts_number || ! empty( $posts_number ) ) {
			$args['posts_number'] = $posts_number;
		}

		if ( ! empty( $columns_number ) ) {
			$args['columns_number'] = $columns_number;
		}

		if ( ! empty( $orderby ) ) {
			$args['orderby'] = $orderby;
		}

		if ( 0 === $offset_number || ! empty( $offset_number ) ) {
			$args['offset_number'] = $offset_number;
		}

		// Retrieve the product upsell HTML using the WooCommerceProductUpsellModule class.
		$upsell_html = WooCommerceProductUpsellModule::get_upsells( $args, $common_required_params['conditional_tags'] );

		$response = [
			'html' => $upsell_html,
		];

		return self::response_success( $response );
	}

	/**
	 * Get the arguments for the index action.
	 *
	 * This method returns an array that defines the REST API arguments for the index action,
	 * including parameter validation, sanitization, and type definitions used in the
	 * `register_rest_route()` function.
	 *
	 * @since ??
	 *
	 * @return array An array of REST API arguments for the index action, including
	 *               parameter definitions with validation and sanitization callbacks.
	 */
	public static function index_args(): array {
		return [
			'productId'      => [
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
			'posts_number'   => [
				'type'              => 'integer',
				'required'          => false,
				'maximum'           => 1000, // Security: Prevent excessive database queries and memory usage.
				'sanitize_callback' => function ( $param ) {
					return absint( $param );
				},
				'validate_callback' => function ( $param ) {
					// Security: Validate range to prevent resource exhaustion.
					return is_numeric( $param ) && $param >= 0 && $param <= 1000;
				},
			],
			'columns_number' => [
				'type'              => 'integer',
				'required'          => false,
				'sanitize_callback' => function ( $param ) {
					return absint( $param );
				},
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && in_array( absint( $param ), [ 1, 2, 3, 4, 5, 6 ], true );
				},
			],
			'orderby'        => [
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => function ( $param ) {
					return sanitize_text_field( $param );
				},
				'validate_callback' => function ( $param ) {
					return is_string( $param ) && in_array(
						sanitize_text_field( $param ),
						[
							'default',
							'menu_order',
							'popularity',
							'date',
							'date-desc',
							'price',
							'price-desc',
						],
						true
					);
				},
			],
			'offset_number'  => [
				'type'              => 'integer',
				'required'          => false,
				'maximum'           => 1000, // Security: Prevent excessive offset values.
				'sanitize_callback' => function ( $param ) {
					return absint( $param );
				},
				'validate_callback' => function ( $param ) {
					// Security: Validate range to prevent resource exhaustion.
					return is_numeric( $param ) && $param >= 0 && $param <= 1000;
				},
			],
		];
	}

	/**
	 * Provides the permission status for the index action.
	 *
	 * This method checks whether the current user has the necessary permissions
	 * to access the WooCommerce Product Upsell REST endpoint.
	 *
	 * @since ??
	 *
	 * @return bool Returns `true` if the current user has permission to use the REST endpoint,
	 *              otherwise `false`.
	 */
	public static function index_permission( WP_REST_Request $request ): bool {
		$product_id = $request->get_param( 'productId' ) ?? 'current';

		return WooCommerceUtils::can_current_user_render_product( $product_id );
	}
}
