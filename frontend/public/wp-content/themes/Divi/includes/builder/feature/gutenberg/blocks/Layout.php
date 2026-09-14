<?php
/**
 * Layout Block Utility Class
 *
 * Provides core utility methods for Layout Block functionality.
 * This file is always loaded and used by both legacy code and D5 implementation.
 *
 * @package Divi
 * @since 5.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ET_GB_Block_Layout utility class.
 *
 * Contains shared utility methods used by both legacy code and D5 implementation.
 * This ensures backward compatibility while centralizing logic.
 *
 * @since 5.0.0
 */
class ET_GB_Block_Layout {

	/**
	 * Check if current page is layout block preview page.
	 *
	 * Checks if current request has `et_block_layout_preview` query var and verifies its nonce.
	 *
	 * @since 4.1.0
	 *
	 * @return bool
	 */
	public static function is_layout_block_preview() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Security check is done by et_core_security_check.
		return isset( $_GET['et_block_layout_preview'] ) && et_core_security_check(
			'edit_posts',
			'et_block_layout_preview',
			'et_block_layout_preview_nonce',
			'_GET',
			false
		);
	}

	/**
	 * Check if current builder shortcode rendering is done inside layout block.
	 *
	 * @since 4.1.0
	 *
	 * @return bool
	 */
	public static function is_layout_block() {
		global $et_is_layout_block;

		// Ensure the returned value is bool.
		return $et_is_layout_block ? true : false;
	}

	/**
	 * Get Theme Builder's template settings of current (layout block preview) page.
	 *
	 * @since 4.1.0
	 *
	 * @return array
	 */
	public static function get_preview_tb_template() {
		// Identify current request, and get applicable TB template for current page.
		$request     = ET_Theme_Builder_Request::from_current();
		$templates   = et_theme_builder_get_theme_builder_templates( true );
		$settings    = et_theme_builder_get_flat_template_settings_options();
		$tb_template = $request->get_template( $templates, $settings );

		// Define template properties as variables for readability.
		$template_id     = et_()->array_get( $tb_template, 'id', 0 );
		$layout_id       = et_()->array_get( $tb_template, 'layouts.body.id', 0 );
		$layout_enabled  = et_()->array_get( $tb_template, 'layouts.body.enabled', false );
		$layout_override = et_()->array_get( $tb_template, 'layouts.body.override', false );
		$has_layout      = $layout_id && $layout_enabled && $layout_override;

		return array(
			'layout_id'      => $layout_id,
			'layout_enabled' => $layout_enabled,
			'template_id'    => $template_id,
			'has_layout'     => $has_layout,
		);
	}
}
