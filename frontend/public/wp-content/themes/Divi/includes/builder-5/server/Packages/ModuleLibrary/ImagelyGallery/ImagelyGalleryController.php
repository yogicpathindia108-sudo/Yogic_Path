<?php
/**
 * Module Library: Imagely Gallery REST Controller.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\ImagelyGallery;

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- WordPress REST API callbacks require specific signatures.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Imagely Gallery REST Controller class.
 *
 * Exposes one endpoint:
 * - `GET /divi/v1/module-data/imagely-gallery/preview` — returns rendered gallery HTML.
 *
 * The gallery list is not served over REST; it is injected into the Visual
 * Builder settings payload via `SettingsDataCallbacks::imagely_gallery()`
 * under the top-level `imagelyGallery` settings key.
 *
 * @since ??
 */
class ImagelyGalleryController extends RESTController {

	/**
	 * Return rendered HTML for a single Imagely gallery.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request object. Requires `galleryId` (int).
	 *
	 * @return WP_REST_Response REST response containing an `html` string and, for
	 *                          slideshow galleries, the gallery's real `slickOptions`.
	 */
	public static function preview( WP_REST_Request $request ): WP_REST_Response {
		$gallery_id = absint( $request->get_param( 'galleryId' ) );

		return self::response_success(
			[
				'html'         => ImagelyGalleryService::render_gallery( $gallery_id, true ),
				// Null for non-slideshow galleries; the client keeps its defaults then.
				'slickOptions' => ImagelyGalleryService::get_slideshow_slick_options( $gallery_id ),
			]
		);
	}

	/**
	 * Get the arguments for the preview action.
	 *
	 * @since ??
	 *
	 * @return array Arguments definition for gallery preview parameters.
	 */
	public static function preview_args(): array {
		return [
			'galleryId' => [
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && $param > 0;
				},
			],
		];
	}

	/**
	 * Provides the permission status for the preview action.
	 *
	 * @since ??
	 *
	 * @return bool True if the current user can use the Visual Builder.
	 */
	public static function preview_permission(): bool {
		return UserRole::can_current_user_use_visual_builder();
	}
}
