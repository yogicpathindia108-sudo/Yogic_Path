<?php
/**
 * Module Library: WooCommerce Breadcrumbs Module REST Controller class
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\Breadcrumb;

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- WordPress REST API callbacks require specific signatures.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * WooCommerce Breadcrumbs REST Controller class.
 *
 * @since ??
 */
class WooCommerceBreadcrumbController extends RESTController {

	/**
	 * Determines whether to use placeholders in the breadcrumb output.
	 *
	 * Placeholders are used when any of the breadcrumb parameters are empty,
	 * allowing the visual-builder to replace them with actual values for better
	 * performance and user experience.
	 *
	 * @since ??
	 *
	 * @param array $params The request parameters.
	 *
	 * @return bool True if placeholders should be used, false otherwise.
	 */
	private static function _should_use_placeholders( array $params ): bool {
		return empty( $params['breadcrumbHomeText'] ) || empty( $params['breadcrumbHomeUrl'] ) || empty( $params['breadcrumbSeparator'] );
	}

	/**
	 * Retrieve the rendered HTML for the WooCommerce Breadcrumbs module.
	 *
	 * This method handles the REST API request, validates parameters, and returns
	 * the breadcrumb HTML. It follows the response format defined in the OpenAPI schema.
	 * It uses WooCommerceUtils to handle product ID validation and conversion.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function index( WP_REST_Request $request ) {
		$common_required_params = WooCommerceUtils::validate_woocommerce_request_params( $request );

		// If the conditional tags are not set, the returned value is an error.
		if ( ! isset( $common_required_params['conditional_tags'] ) ) {
			return self::response_error( ...$common_required_params );
		}

		$params = $request->get_params();

		$product_id = $params['productId'] ?? WooCommerceUtils::get_default_product();

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

		// Generate breadcrumb HTML using the module method.
		$breadcrumb_html = WooCommerceBreadcrumbModule::get_breadcrumb(
			[
				'product'              => $product->get_id(),
				'breadcrumb_home_text' => $params['breadcrumbHomeText'] ?? '',
				'breadcrumb_home_url'  => $params['breadcrumbHomeUrl'] ?? '',
				'breadcrumb_separator' => $params['breadcrumbSeparator'] ?? '',
				'use_placeholders'     => self::_should_use_placeholders( $params ),
			]
		);

		return self::response_success(
			[
				'html' => $breadcrumb_html,
			]
		);
	}

	/**
	 * Returns the arguments for the REST endpoint.
	 *
	 * This method defines the parameters accepted by the endpoint and their validation rules.
	 * Note: The OpenAPI schema marks productId as optional, but it's typically required for
	 * proper functionality. Consider making it required in the actual implementation.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function index_args(): array {
		return [
			'productId'           => [
				'required'          => false,
				'description'       => __( 'Product ID.', 'divi' ),
				'sanitize_callback' => function ( $param ) {
					$param = sanitize_text_field( $param );

					return ( 'current' !== $param && 'latest' !== $param ) ? absint( $param ) : $param;
				},
				'validate_callback' => function ( $param, $request ) {
					return WooCommerceUtils::validate_product_id( $param, $request );
				},
			],
			'breadcrumbHomeText'  => [
				'required'          => false,
				'description'       => __( 'Text for the home link in the breadcrumb.', 'divi' ),
				'sanitize_callback' => 'sanitize_text_field',
			],
			'breadcrumbHomeUrl'   => [
				'required'          => false,
				'description'       => __( 'URL for the home link in the breadcrumb.', 'divi' ),
				'sanitize_callback' => 'esc_url_raw',
				'validate_callback' => function ( $param, $request, $key ) {
					return empty( $param ) || filter_var( $param, FILTER_VALIDATE_URL ) !== false;
				},
			],
			'breadcrumbSeparator' => [
				'required'          => false,
				'description'       => __( 'Separator between breadcrumb items.', 'divi' ),
				'sanitize_callback' => 'sanitize_text_field',
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
