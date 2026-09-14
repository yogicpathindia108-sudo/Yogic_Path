<?php
/**
 * Gravity Forms Dynamic Assets integration asset provider.
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\Packages\ModuleLibrary\GravityForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use ET\Builder\FrontEnd\Assets\DynamicAssets\IntegrationAssetsContext;
use ET\Builder\FrontEnd\Assets\DynamicAssets\IntegrationAssetsProviderInterface;

/**
 * Adapter between the public Dynamic Assets provider contract and Gravity Forms service logic.
 *
 * @since ??
 */
final class GravityFormsIntegrationAssetsProvider implements IntegrationAssetsProviderInterface {

	/**
	 * Whether Gravity Forms' public enqueue API is available.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return class_exists( '\GFForms' ) && function_exists( 'gravity_form_enqueue_scripts' );
	}

	/**
	 * Enqueue Gravity Forms assets required by supplied DA content.
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

		foreach ( GravityFormsService::get_form_asset_requirements( $context->get_content() ) as $form ) {
			GravityFormsService::enqueue_form_assets( $form['formId'], $form['useAjax'] );
		}
	}
}
