<?php
/**
 * Imagely Gallery integration hooks.
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\Packages\ModuleLibrary\ImagelyGallery;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\FrontEnd\Assets\DynamicAssets\IntegrationAssetsProviderRegistry;

/**
 * Loads Imagely integration hooks independently of lazy module registration.
 *
 * @since ??
 */
final class ImagelyGalleryHooks implements DependencyInterface {

	/**
	 * Register Imagely integration hooks.
	 *
	 * The earliest provider priority preserves Divi's first-registration ownership
	 * of its reserved ID. The separate VB callback retains mutable all-gallery
	 * preloading without routing it through the frontend provider context.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		add_action(
			'divi_frontend_assets_dynamic_assets_register_integration_asset_providers',
			[ self::class, 'register_dynamic_assets_provider' ],
			PHP_INT_MIN
		);
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_visual_builder_assets' ] );
	}

	/**
	 * Register the built-in Imagely Gallery integration asset provider.
	 *
	 * @since ??
	 *
	 * @param IntegrationAssetsProviderRegistry $registry Request-scoped provider registry.
	 *
	 * @return void
	 */
	public static function register_dynamic_assets_provider( IntegrationAssetsProviderRegistry $registry ): void {
		$registry->register(
			'divi/imagely-gallery',
			new ImagelyGalleryIntegrationAssetsProvider()
		);
	}

	/**
	 * Preload all mutable Imagely Gallery assets in the Visual Builder app window.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_visual_builder_assets(): void {
		if ( ! Conditions::is_vb_app_window() ) {
			return;
		}

		ImagelyGalleryService::enqueue_all_gallery_assets();
	}
}
