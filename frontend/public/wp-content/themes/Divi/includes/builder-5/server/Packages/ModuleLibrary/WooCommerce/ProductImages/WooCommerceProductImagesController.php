<?php
/**
 * Module Library: WooCommerce Product Images Module REST Controller class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductImages;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductImages\WooCommerceProductImagesModule;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * WooCommerce Product Images REST Controller class.
 *
 * @since ??
 */
class WooCommerceProductImagesController extends RESTController {

	/**
	 * Retrieve the rendered HTML for the WooCommerce Product Images module.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error Returns the REST response object containing the rendered HTML.
	 */
	public static function index( WP_REST_Request $request ) {
		$common_required_params = WooCommerceUtils::validate_woocommerce_request_params( $request );

		// If the conditional tags are not set, the returned value is an error.
		if ( ! isset( $common_required_params['conditional_tags'] ) ) {
			return self::response_error( ...$common_required_params );
		}

		$product_id = $request->get_param( 'productId' ) ?? 'current';

		$product = WooCommerceUtils::get_product( $product_id );

		if ( ! $product ) {
			return self::response_error(
				'product_not_found',
				__( 'Product not found.', 'divi' ),
				[ 'status' => 404 ],
				404
			);
		}

		$args = [ 'product' => $product->get_id() ];

		if ( $request->get_param( 'showProductImage' ) ) {
			$args['show_product_image'] = $request->get_param( 'showProductImage' );
		}

		if ( $request->get_param( 'showProductGallery' ) ) {
			$args['show_product_gallery'] = $request->get_param( 'showProductGallery' );
		}

		if ( $request->get_param( 'showSaleBadge' ) ) {
			$args['show_sale_badge'] = $request->get_param( 'showSaleBadge' );
		}

		// Retrieve the product images using the WooCommerceProductImagesModule class.
		$images_html = WooCommerceProductImagesModule::get_images( $args );

		$response = [
			'html' => $images_html,
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
			'productId'          => [
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
			'showProductImage'   => [
				'type'     => 'string',
				'required' => false,
				'enum'     => [ 'on', 'off' ],
			],
			'showProductGallery' => [
				'type'     => 'string',
				'required' => false,
				'enum'     => [ 'on', 'off' ],
			],
			'showSaleBadge'      => [
				'type'     => 'string',
				'required' => false,
				'enum'     => [ 'on', 'off' ],
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
