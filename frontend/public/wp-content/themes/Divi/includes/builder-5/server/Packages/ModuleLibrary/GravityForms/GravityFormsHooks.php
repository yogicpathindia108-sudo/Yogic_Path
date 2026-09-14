<?php
/**
 * Gravity Forms integration hooks.
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\Packages\ModuleLibrary\GravityForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\FrontEnd\Assets\DynamicAssets\IntegrationAssetsProviderRegistry;

/**
 * Loads Gravity Forms integration hooks independently of lazy module registration.
 *
 * @since ??
 */
final class GravityFormsHooks implements DependencyInterface {

	/**
	 * Register Gravity Forms integration hooks.
	 *
	 * The earliest priority preserves Divi's first-registration ownership of its reserved provider ID
	 * while using the same public registration action exposed to third-party integrations.
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
	}

	/**
	 * Register the built-in Gravity Forms integration asset provider.
	 *
	 * @since ??
	 *
	 * @param IntegrationAssetsProviderRegistry $registry Request-scoped provider registry.
	 *
	 * @return void
	 */
	public static function register_dynamic_assets_provider( IntegrationAssetsProviderRegistry $registry ): void {
		$registry->register(
			'divi/gravity-forms',
			new GravityFormsIntegrationAssetsProvider()
		);
	}
}
