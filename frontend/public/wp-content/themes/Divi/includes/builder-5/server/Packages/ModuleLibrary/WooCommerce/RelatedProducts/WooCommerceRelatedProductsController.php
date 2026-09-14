<?php
/**
 * Module Library: WooCommerce Related Products Module REST Controller class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\RelatedProducts;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\RelatedProducts\WooCommerceRelatedProductsModule;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * WooCommerce Related Products REST Controller class.
 *
 * @since ??
 */
class WooCommerceRelatedProductsController extends RESTController {
	/**
	 * Normalize includeCategories request value into a string array.
	 *
	 * Supports array payloads only to keep REST contract strict and explicit.
	 *
	 * @since ??
	 *
	 * @param mixed $param Raw include categories request value.
	 *
	 * @return array
	 */
	private static function _normalize_include_categories_param( $param ): array {
		if ( is_array( $param ) ) {
			return array_values(
				array_filter(
					array_map( 'sanitize_text_field', $param ),
					static function ( $category ) {
						return is_string( $category ) && '' !== $category;
					}
				)
			);
		}

		return [];
	}

	/**
	 * Retrieve the rendered HTML for the WooCommerce Related Products module.
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

		$product_id = $request->get_param( 'productId' ) ?? 'current';
		$product    = WooCommerceUtils::get_product( $product_id );

		if ( ! $product ) {
			return self::response_error(
				'product_not_found',
				__( 'Product not found.', 'divi' ),
				[ 'status' => 404 ],
				404
			);
		}

		$conditional_tags = $common_required_params['conditional_tags'];

		$include_categories = self::_normalize_include_categories_param( $request->get_param( 'includeCategories' ) );
		$show_price         = $request->get_param( 'showPrice' );
		$offset_number      = $request->get_param( 'offsetNumber' );
		$posts_number       = $request->get_param( 'postsNumber' );
		$columns_number     = $request->get_param( 'columnsNumber' );
		$orderby            = $request->get_param( 'orderby' );

		$args = [ 'product' => $product->get_id() ];

		if ( ! empty( $include_categories ) ) {
			$args['include_categories'] = $include_categories;
		}

		if ( ! empty( $show_price ) ) {
			$args['show_price'] = $show_price;
		}

		if ( ! empty( $offset_number ) ) {
			$args['offset_number'] = $offset_number;
		}

		if ( 0 === $posts_number || ! empty( $posts_number ) ) {
			$args['posts_number'] = $posts_number;
		}

		if ( ! empty( $columns_number ) ) {
			$args['columns_number'] = $columns_number;
		}

		if ( ! empty( $orderby ) ) {
			$args['orderby'] = $orderby;
		}

		// Retrieve the related products using the WooCommerceRelatedProductsModule class.
		$related_products_html = WooCommerceRelatedProductsModule::get_related_products( $args, $conditional_tags );

		$response = [
			'html' => $related_products_html,
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
			'productId'         => [
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
			'includeCategories' => [
				'type'              => 'array',
				'required'          => false,
				'sanitize_callback' => function ( $param ) {
					return self::_normalize_include_categories_param( $param );
				},
				'validate_callback' => function ( $param ) {
					/**
					 * Validate includeCategories parameter as array.
					 *
					 * Accepts either:
					 * - Empty array: No category filtering (show products from all categories)
					 * - Array of strings: Valid category slugs/IDs without empty elements
					 *
					 * This follows the same pattern as Product Tabs includeTabs for consistency
					 * across D5 WooCommerce modules.
					 *
					 * @since ??
					 */
					if ( [] === $param ) {
						return true;
					}

					if ( ! is_array( $param ) ) {
						return false;
					}

					// Ensure no empty strings in the array.
					foreach ( $param as $category ) {
						if ( empty( $category ) || ! is_string( $category ) ) {
							return false;
						}
					}

					return true;
				},
			],
			'showPrice'         => [
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => function ( $param ) {
					return sanitize_text_field( $param );
				},
				'validate_callback' => function ( $param ) {
					return in_array( $param, [ 'on', 'off' ], true );
				},
			],
			'offsetNumber'      => [
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
			'postsNumber'       => [
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
			'columnsNumber'     => [
				'type'              => 'integer',
				'required'          => false,
				'sanitize_callback' => function ( $param ) {
					return absint( $param );
				},
				'validate_callback' => function ( $param ) {
					return in_array( absint( $param ), [ 1, 2, 3, 4, 5, 6 ], true );
				},
			],
			'orderby'           => [
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => function ( $param ) {
					return sanitize_text_field( $param );
				},
				'validate_callback' => function ( $param ) {
					return in_array( $param, [ 'default', 'menu_order', 'popularity', 'date', 'date-desc', 'price', 'price-desc' ], true );
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
	public static function index_permission( WP_REST_Request $request ): bool {
		$product_id = $request->get_param( 'productId' ) ?? 'current';

		return WooCommerceUtils::can_current_user_render_product( $product_id );
	}
}
