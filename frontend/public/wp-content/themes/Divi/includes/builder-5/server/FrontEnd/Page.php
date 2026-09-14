<?php
/**
 * Page Class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\FrontEnd;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Settings\Overflow;
use ET\Builder\Framework\Settings\Settings;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Packages\StyleLibrary\Utils\Utils;
use ET_Post_Stack;

/**
 * Page Class.
 *
 * This class is responsible for handling functionality related to Page rendering (specifically that uses Divi Builder)
 *
 * @since ??
 */
class Page {
	/**
	 * Return page custom style.
	 *
	 * @internal Equivalent of Divi 4's `et_pb_get_page_custom_css()`.
	 *
	 * @since ??
	 *
	 * @param int $post_id post id.
	 */
	public static function custom_css( $post_id = 0 ) {
		$post_id          = $post_id ? $post_id : get_the_ID();
		$post_type        = get_post_type( $post_id );
		$page_id          = apply_filters( 'et_pb_page_id_custom_css', $post_id );
		$exclude_defaults = true;
		$page_settings    = Settings::get_values( 'page', $page_id, $exclude_defaults );
		$selector_prefix  = '.et-l--post';

		switch ( $post_type ) {
			case ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE:
				$selector_prefix = '.et-l--header';
				break;

			case ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE:
				$selector_prefix = '.et-l--body';
				break;

			case ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE:
				$selector_prefix = '.et-l--footer';
				break;
		}

		$wrap_post_id = $page_id;

		if ( et_theme_builder_is_layout_post_type( $post_type ) ) {
			$main_post_id = ET_Post_Stack::get_main_post_id();

			if ( $main_post_id ) {
				$wrap_post_id = $main_post_id;
			}
		}

		$wrap_selector = et_pb_is_pagebuilder_used( $wrap_post_id ) && ( et_is_builder_plugin_active() || Conditions::is_custom_post_type() );

		if ( $wrap_selector ) {
			$selector_prefix = ' ' . ET_BUILDER_CSS_PREFIX . $selector_prefix;
		}

		$output = get_post_meta( $page_id, '_et_pb_custom_css', true );

		if ( isset( $page_settings['et_pb_light_text_color'] ) ) {
			$output .= sprintf(
				'%2$s .et_pb_bg_layout_dark { color: %1$s !important; }',
				esc_html( Utils::resolve_dynamic_variable( $page_settings['et_pb_light_text_color'] ) ),
				esc_html( $selector_prefix )
			);
		}

		if ( isset( $page_settings['et_pb_dark_text_color'] ) ) {
			$output .= sprintf(
				'%2$s .et_pb_bg_layout_light { color: %1$s !important; }',
				esc_html( Utils::resolve_dynamic_variable( $page_settings['et_pb_dark_text_color'] ) ),
				esc_html( $selector_prefix )
			);
		}

		if ( isset( $page_settings['et_pb_content_area_background_color'] ) ) {
			$content_area_bg_color = Utils::resolve_dynamic_variable( $page_settings['et_pb_content_area_background_color'] );

			// For Divi Builder pages.
			$output .= sprintf(
				' .page.et_pb_pagebuilder_layout #main-content { background-color: %1$s; }',
				esc_html( $content_area_bg_color )
			);
			// For non-Divi Builder pages.
			$output .= sprintf(
				' #main-content { background-color: %1$s; }',
				esc_html( $content_area_bg_color )
			);
		}

		if ( isset( $page_settings['et_pb_section_background_color'] ) ) {
			$output .= sprintf(
				'%2$s > .et_builder_inner_content > .et_pb_section { background-color: %1$s; }',
				esc_html( Utils::resolve_dynamic_variable( $page_settings['et_pb_section_background_color'] ) ),
				esc_html( $selector_prefix )
			);
		}

		$overflow_x = Overflow::get_value_x( $page_settings, '', 'et_pb_' );
		$overflow_y = Overflow::get_value_y( $page_settings, '', 'et_pb_' );

		if ( ! empty( $overflow_x ) ) {
			$output .= sprintf(
				'%2$s .et_builder_inner_content { overflow-x: %1$s; }',
				esc_html( $overflow_x ),
				esc_html( $selector_prefix )
			);
		}

		if ( ! empty( $overflow_y ) ) {
			$output .= sprintf(
				'%2$s .et_builder_inner_content { overflow-y: %1$s; }',
				esc_html( $overflow_y ),
				esc_html( $selector_prefix )
			);
		}

		// Skip "auto": VB treats it as no override (#39564); emitting it breaks theme header stacking.
		if (
			isset( $page_settings['et_pb_page_z_index'] )
			&& '' !== $page_settings['et_pb_page_z_index']
			&& 'auto' !== $page_settings['et_pb_page_z_index']
		) {
			$output .= sprintf(
				'%2$s .et_builder_inner_content { z-index: %1$s; }',
				esc_html( $page_settings['et_pb_page_z_index'] ),
				esc_html( '.et-db #et-boc .et-l' . $selector_prefix )
			);
		}

		return apply_filters( 'et_pb_page_custom_css', $output );
	}

	/**
	 * Return canvas z-index styles.
	 *
	 * Generates CSS for canvas z-index styles collected during canvas rendering.
	 * The styles are collected in $GLOBALS['divi_canvas_z_index_styles'] during
	 * canvas rendering and output via StaticCSS mechanism.
	 *
	 * @since ??
	 *
	 * @return string Canvas z-index CSS.
	 */
	public static function canvas_z_index_css() {
		// Only run on frontend (not in admin or visual builder).
		if ( Conditions::is_admin_request() || Conditions::is_vb_enabled() ) {
			return '';
		}

		// Check if we have any canvas z-index styles to output.
		if ( ! isset( $GLOBALS['divi_canvas_z_index_styles'] ) || empty( $GLOBALS['divi_canvas_z_index_styles'] ) ) {
			return '';
		}

		$css_rules = [];
		// Styles are already deduplicated by selector (key), so iterate directly.
		foreach ( $GLOBALS['divi_canvas_z_index_styles'] as $style_data ) {
			$selector = $style_data['selector'] ?? '';
			$z_index  = $style_data['z_index'] ?? '';

			if ( ! empty( $selector ) && ! empty( $z_index ) ) {
				$css_rules[] = sprintf(
					'%s { position: relative; z-index: %s; }',
					esc_html( $selector ),
					esc_html( $z_index )
				);
			}
		}

		// Clear the global array after generating CSS to prevent accumulation.
		unset( $GLOBALS['divi_canvas_z_index_styles'] );

		if ( ! empty( $css_rules ) ) {
			return implode( "\n", $css_rules );
		}

		return '';
	}
}
