<?php
/**
 * Module Library: WooCommerce Product Tabs Module REST Controller class
 *
 * @since ??
 * @package Divi
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductTabs;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductTabs\WooCommerceProductTabsModule;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use ET\Builder\VisualBuilder\SettingsData\SettingsDataCallbacks;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * WooCommerce Product Tabs REST Controller class.
 *
 * @since ??
 */
class WooCommerceProductTabsController extends RESTController {

	/**
	 * Retrieve the rendered HTML for the WooCommerce Product Tabs module.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error Returns the REST response object containing the product tabs data.
	 */
	public static function index( WP_REST_Request $request ) {
		$common_required_params = WooCommerceUtils::validate_woocommerce_request_params( $request );

		// If the conditional tags are not set, the returned value is an error.
		if ( ! isset( $common_required_params['conditional_tags'] ) ) {
			return self::response_error( ...$common_required_params );
		}

		$product_id = $request->get_param( 'productId' ) ?? WooCommerceUtils::get_default_product();

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

		$include_tabs = $request->get_param( 'includeTabs' );

		if ( empty( $include_tabs ) || ! is_array( $include_tabs ) ) {
			$include_tabs = [];
		}

		$args = [
			'product'      => $product->get_id(),
			'include_tabs' => $include_tabs,
		];

		$product_tabs = WooCommerceProductTabsModule::get_product_tabs( $args );

		$response = [
			'tabs' => $product_tabs,
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
			'productId'   => [
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
			'includeTabs' => [
				'type'              => 'array',
				'required'          => false,
				'sanitize_callback' => function ( $param ) {
					return array_map( 'sanitize_text_field', $param );
				},
				'validate_callback' => function ( $param ) {
					if ( [] === $param ) {
						return true;
					}

					// Allowed WooCommerce productTabsOptions.
					$product_tab_options = [
						'description',
						'additional_information',
						'reviews',
					];

					foreach ( $param as $param_item ) {
						if ( ! in_array( $param_item, $product_tab_options, true ) ) {
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
	public static function index_permission( WP_REST_Request $request ): bool {
		$product_id = $request->get_param( 'productId' ) ?? WooCommerceUtils::get_default_product();

		return WooCommerceUtils::can_current_user_render_product( $product_id );
	}
}
