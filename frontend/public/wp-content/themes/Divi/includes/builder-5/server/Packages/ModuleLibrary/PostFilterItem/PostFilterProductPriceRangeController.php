<?php
/**
 * Post Filter Item product price range REST controller.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostFilterItem;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST endpoint for post-filter-item product price range metadata.
 *
 * @since ??
 */
class PostFilterProductPriceRangeController extends RESTController {
	/**
	 * Return WooCommerce-aligned product price range metadata.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function index( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		return self::response_success( PostFilterProductPriceRange::get_range() );
	}

	/**
	 * Index action arguments.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function index_args(): array {
		return [];
	}

	/**
	 * Index action permission.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function index_permission(): bool {
		return UserRole::can_edit_posts();
	}
}
