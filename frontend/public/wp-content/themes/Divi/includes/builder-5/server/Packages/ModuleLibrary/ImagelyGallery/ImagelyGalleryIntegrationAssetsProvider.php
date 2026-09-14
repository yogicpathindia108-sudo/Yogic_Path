<?php
/**
 * Imagely Gallery Dynamic Assets integration asset provider.
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\Packages\ModuleLibrary\ImagelyGallery;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use ET\Builder\FrontEnd\Assets\DynamicAssets\IntegrationAssetsContext;
use ET\Builder\FrontEnd\Assets\DynamicAssets\IntegrationAssetsProviderInterface;

/**
 * Adapts pass-scoped Dynamic Assets content to Imagely-owned asset selection.
 *
 * @since ??
 */
final class ImagelyGalleryIntegrationAssetsProvider implements IntegrationAssetsProviderInterface {

	/**
	 * Whether NextGEN's public enqueue API is available.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return class_exists( '\Imagely\NGG\Display\DisplayManager' )
			&& is_callable( [ '\Imagely\NGG\Display\DisplayManager', 'enqueue_frontend_resources_for_content' ] );
	}

	/**
	 * Enqueue NextGEN assets required by supplied Dynamic Assets content.
	 *
	 * @since ??
	 *
	 * @param IntegrationAssetsContext $context Dynamic Assets integration context.
	 *
	 * @return void
	 */
	public function enqueue( IntegrationAssetsContext $context ): void {
		if ( ! $this->is_available() ) {
			return;
		}

		$gallery_ids = ImagelyGalleryService::get_gallery_ids_from_content( $context->get_content() );

		ImagelyGalleryService::enqueue_gallery_assets( $gallery_ids );
	}
}
