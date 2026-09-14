<?php
/**
 * Compatibility for the Tutor LMS plugin.
 *
 * @package Divi
 * @subpackage Builder
 * @since 5.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility for the Tutor LMS plugin.
 *
 * @since 5.0.0
 *
 * @link https://www.themeum.com/product/tutor-lms/
 */
class ET_Builder_Plugin_Compat_Tutor extends ET_Builder_Plugin_Compat_Base {
	/**
	 * Constructor.
	 *
	 * @since 5.0.0
	 */
	public function __construct() {
		$this->plugin_id = 'tutor/tutor.php';
		$this->init_hooks();
	}

	/**
	 * Hook methods to WordPress.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public function init_hooks() {
		$version = $this->get_plugin_version();

		if ( ! $version ) {
			return;
		}

		add_filter( 'et_theme_builder_template_layouts', [ $this, 'maybe_fix_order_page_templates' ], 20 );
		add_action( 'tutor_order_placement_success', [ $this, 'maybe_add_theme_builder_hooks' ], 1 );
	}

	/**
	 * Check if current page is a Tutor LMS Order page.
	 *
	 * @since 5.0.0
	 *
	 * @return bool
	 */
	protected function _is_tutor_order_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This function does not change any state, and is therefore not susceptible to CSRF.
		return isset( $_GET['tutor_order_placement'] ) && 'success' === $_GET['tutor_order_placement'];
	}

	/**
	 * Get the Tutor LMS checkout page ID.
	 *
	 * @since 5.0.0
	 *
	 * @return int|false
	 */
	protected function _get_checkout_page_id() {
		if ( ! function_exists( 'tutor_utils' ) ) {
			return false;
		}

		$checkout_page_id = tutor_utils()->get_option( 'tutor_checkout_page_id' );

		return $checkout_page_id ? (int) $checkout_page_id : false;
	}

	/**
	 * Fix Theme Builder template layouts for Tutor LMS Order pages.
	 *
	 * @since 5.0.0
	 *
	 * @param array $layouts Current template layouts.
	 *
	 * @return array
	 */
	public function maybe_fix_order_page_templates( $layouts ) {
		if ( ! $this->_is_tutor_order_page() ) {
			return $layouts;
		}

		$checkout_page_id = $this->_get_checkout_page_id();

		if ( ! $checkout_page_id ) {
			return $this->_get_default_template_layouts();
		}

		$request   = new ET_Theme_Builder_Request( ET_Theme_Builder_Request::TYPE_SINGULAR, 'page', $checkout_page_id );
		$templates = et_theme_builder_get_theme_builder_templates( true, false );
		$settings  = et_theme_builder_get_flat_template_settings_options();
		$template  = $request->get_template( $templates, $settings );

		if ( empty( $template ) ) {
			return $this->_get_default_template_layouts();
		}

		$is_default      = $template['default'];
		$override_header = $template['layouts']['header']['override'];
		$override_body   = $template['layouts']['body']['override'];
		$override_footer = $template['layouts']['footer']['override'];

		if ( ! $is_default || $override_header || $override_body || $override_footer ) {
			return [
				ET_THEME_BUILDER_TEMPLATE_POST_TYPE      => false,
				ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE => $template['layouts']['header'],
				ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE   => $template['layouts']['body'],
				ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE => $template['layouts']['footer'],
			];
		}

		return $layouts;
	}

	/**
	 * Get default template layouts.
	 *
	 * @since 5.0.0
	 *
	 * @return array
	 */
	protected function _get_default_template_layouts() {
		$request = new ET_Theme_Builder_Request( ET_Theme_Builder_Request::TYPE_SINGULAR, 'page', 0 );

		$templates = et_theme_builder_get_theme_builder_templates( true, false );
		$settings  = et_theme_builder_get_flat_template_settings_options();
		$template  = $request->get_template( $templates, $settings );

		if ( empty( $template ) ) {
			return [];
		}

		$is_default      = $template['default'];
		$override_header = $template['layouts']['header']['override'];
		$override_body   = $template['layouts']['body']['override'];
		$override_footer = $template['layouts']['footer']['override'];

		if ( ! $is_default || $override_header || $override_body || $override_footer ) {
			return [
				ET_THEME_BUILDER_TEMPLATE_POST_TYPE      => false,
				ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE => $template['layouts']['header'],
				ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE   => $template['layouts']['body'],
				ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE => $template['layouts']['footer'],
			];
		}

		return [];
	}

	/**
	 * Maybe add Theme Builder header/footer hooks for Tutor Order pages.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public function maybe_add_theme_builder_hooks() {
		if ( ! $this->_is_tutor_order_page() ) {
			return;
		}

		$override_header = et_theme_builder_overrides_layout( ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE );
		$override_footer = et_theme_builder_overrides_layout( ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE );

		if ( $override_header || $override_footer ) {
			remove_action( 'wp_body_open', 'wp_admin_bar_render', 0 );

			add_action( 'get_header', 'et_theme_builder_frontend_override_header' );
			add_action( 'get_footer', 'et_theme_builder_frontend_override_footer' );
			et_prioritize_deferred_block_css_for_theme_builder();
		}
	}
}

new ET_Builder_Plugin_Compat_Tutor();

