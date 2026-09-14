<?php
/**
 * Module Library: Image Module REST Controller class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Image;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElementsUtils;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Image Module REST Controller class.
 *
 * @since ??
 */
class ImageController extends RESTController {


	/**
	 * Return responsive image attributes for server-side rendering.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function server_rendering_attributes( WP_REST_Request $request ) {
		$src = $request->get_param( 'src' ) ?? '';
		$id  = $request->get_param( 'id' ) ?? '';

		$response = ModuleElementsUtils::get_responsive_image_attrs(
			[
				'src' => $src,
				'id'  => $id,
			]
		);

		return self::response_success( $response );
	}

	/**
	 * Server rendering attributes action arguments.
	 *
	 * Endpoint arguments as used in `register_rest_route()`.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function server_rendering_attributes_args(): array {
		return [
			'src' => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'esc_url_raw',
			],
			'id'  => [
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Server rendering attributes action permission.
	 *
	 * Endpoint permission callback as used in `register_rest_route()`.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function server_rendering_attributes_permission(): bool {
		return UserRole::can_current_user_use_visual_builder();
	}
}
