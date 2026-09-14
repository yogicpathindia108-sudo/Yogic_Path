<?php
/**
 * Module Library: WooCommerce Product Gallery Module REST Controller class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductGallery;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Packages\IconLibrary\IconFont\Utils;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * WooCommerce Product Gallery REST Controller class.
 *
 * @since ??
 */
class WooCommerceProductGalleryController extends RESTController {

	/**
	 * Static count of gallery items.
	 *
	 * @var int
	 */
	public static $gallery_items_count = 0;

	/**
	 * Retrieve the rendered HTML for the WooCommerce Product Gallery module.
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
		// Validate product exists for numeric product IDs.
		$product = WooCommerceUtils::get_product( $product_id );
		if ( ! $product ) {
			return self::response_error(
				'product_not_found',
				__( 'Product not found.', 'divi' ),
				[ 'status' => 404 ],
				404
			);
		}

		// Prepare arguments for the unified gallery method.
		$gallery_layout = $request->get_param( 'galleryLayout' ) ?? 'slider';

		// Explicitly set fullwidth based on gallery_layout.
		// WooCommerce Product Gallery defaults: fullwidth = 'on' (slider), gallery_layout = 'slider'.
		// When fullwidth = 'off', gallery_layout = 'grid'.
		$fullwidth = 'slider' === $gallery_layout ? 'on' : 'off';
		$posts_number = $request->get_param( 'postsNumber' ) ?? 4;

		$args = [
			'product'                => $product_id,
			'gallery_layout'         => $gallery_layout,
			'fullwidth'              => $fullwidth,
			'thumbnail_orientation'  => $request->get_param( 'thumbnailOrientation' ) ?? 'landscape',
			'show_pagination'        => $request->get_param( 'showPagination' ) ?? 'on',
			'show_title_and_caption' => $request->get_param( 'showTitleAndCaption' ) ?? 'on',
			'hover_icon'             => $request->get_param( 'hoverIcon' ) ?? '',
			'hover_icon_tablet'      => $request->get_param( 'hoverIconTablet' ) ?? '',
			'hover_icon_phone'       => $request->get_param( 'hoverIconPhone' ) ?? '',
			'heading_level'          => $request->get_param( 'headingLevel' ) ?? 'h3',
			'posts_number'           => $posts_number,
		];

		// Get the raw attachment objects (not processed data).
		$attachments = WooCommerceProductGalleryModule::get_wc_gallery( $args );

		// Check if no gallery images found.
		if ( empty( $attachments ) ) {
			return self::response_success(
				[
					'html'         => 'No gallery images found',
					'attachments'  => [],
					'posts_number' => 0,
				]
			);
		}

		// Process icon data for enhanced overlay rendering.
		$icon        = ! empty( $args['hover_icon'] ) ? Utils::process_font_icon( $args['hover_icon'] ) : '';
		$icon_tablet = ! empty( $args['hover_icon_tablet'] ) ? Utils::process_font_icon( $args['hover_icon_tablet'] ) : '';
		$icon_phone  = ! empty( $args['hover_icon_phone'] ) ? Utils::process_font_icon( $args['hover_icon_phone'] ) : '';

		$icon_data = [
			'icon'        => $icon,
			'icon_tablet' => $icon_tablet,
			'icon_phone'  => $icon_phone,
		];

		// Prepare rendering arguments for the controller method.
		$render_args = [
			'posts_number'           => WooCommerceProductGalleryModule::normalize_posts_number( $args['posts_number'] ),
			'fullwidth'              => $args['fullwidth'],
			'gallery_layout'         => $args['gallery_layout'],
			'show_title_and_caption' => $args['show_title_and_caption'],
			'show_pagination'        => $args['show_pagination'],
			'orientation'            => $args['thumbnail_orientation'] ?? $args['orientation'],
		];

		// Use the module method to generate JUST gallery items HTML (no wrappers).
		$gallery_items_html = WooCommerceProductGalleryModule::generate_gallery_html( $attachments, $render_args, $icon_data );

		// Sanitize the attachments before returning them.
		$gallery_attachments = array_map(
			function ( $attachment ) {
				// Generate both portrait and landscape thumbnails for frontend choice.
				$thumbnail_landscape = wp_get_attachment_image_url( $attachment->ID, [ 400, 284 ] );
				$thumbnail_portrait  = wp_get_attachment_image_url( $attachment->ID, [ 400, 516 ] );

				return [
					'id'                  => $attachment->ID,
					'url'                 => wp_get_attachment_url( $attachment->ID ),
					'thumbnail'           => $thumbnail_landscape,
					'thumbnail_landscape' => $thumbnail_landscape,
					'thumbnail_portrait'  => $thumbnail_portrait,
					'alt'                 => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
					'title'               => $attachment->post_title,
					'caption'             => $attachment->post_excerpt,
				];
			},
			$attachments
		);

		return self::response_success(
			[
				'html'         => $gallery_items_html,
				'attachments'  => $gallery_attachments,
				'posts_number' => count( $attachments ),
			]
		);
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
			'productId'            => [
				'type'              => 'string',
				'required'          => false,
				'default'           => 'current',
				'sanitize_callback' => function ( $param ) {
					$param = sanitize_text_field( $param );

					// Handle empty strings by defaulting to 'current'.
					if ( empty( $param ) ) {
						return 'current';
					}

					if ( 'dynamic' === $param ) {
						return WooCommerceUtils::get_default_product();
					}

					return ( 'current' !== $param && 'latest' !== $param ) ? absint( $param ) : $param;
				},
				'validate_callback' => function ( $param, $request ) {
					return WooCommerceUtils::validate_product_id( $param, $request );
				},
			],
			'galleryLayout'        => [
				'type'              => 'string',
				'required'          => false,
				'default'           => 'grid',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $param ) {
					return in_array( $param, [ 'grid', 'slider' ], true );
				},
			],
			'thumbnailOrientation' => [
				'type'              => 'string',
				'required'          => false,
				'default'           => 'landscape',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $param ) {
					return in_array( $param, [ 'landscape', 'portrait' ], true );
				},
			],
			'showPagination'       => [
				'type'              => 'string',
				'required'          => false,
				'default'           => 'on',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $param ) {
					return in_array( $param, [ 'on', 'off' ], true );
				},
			],
			'showTitleAndCaption'  => [
				'type'              => 'string',
				'required'          => false,
				'default'           => 'off',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $param ) {
					return in_array( $param, [ 'on', 'off' ], true );
				},
			],
			'hoverIcon'            => [
				'type'     => [ 'object', 'string' ],
				'required' => false,
				'default'  => '',
			],
			'hoverIconTablet'      => [
				'type'     => [ 'object', 'string' ],
				'required' => false,
				'default'  => '',
			],
			'hoverIconPhone'       => [
				'type'     => [ 'object', 'string' ],
				'required' => false,
				'default'  => '',
			],
			'headingLevel'         => [
				'type'              => 'string',
				'required'          => false,
				'default'           => 'h3',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'postsNumber'          => [
				'type'              => [ 'string', 'integer' ],
				'required'          => false,
				'default'           => 4,
				'sanitize_callback' => 'absint',
			],
			'returnInnerOnly'      => [
				'type'     => 'boolean',
				'required' => false,
				'default'  => false,
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
