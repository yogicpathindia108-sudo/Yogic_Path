<?php
/**
 * Theme Customizer CSS output filters.
 *
 * @package Divi
 * @since ??
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Add #main-content background CSS for non-Divi Builder pages when Theme Customizer background is set.
 *
 * @since ??
 *
 * @param string $css Theme Customizer CSS output.
 *
 * @return string Modified CSS output.
 */
function et_divi_add_main_content_background_css( $css ) {
	$post_id          = et_core_page_resource_get_the_ID();
	$is_pagebuilder   = et_pb_is_pagebuilder_used( $post_id );
	$background_image = get_theme_mod( 'background_image', '' );
	$background_color = get_theme_mod( 'background_color', '' );

	if ( $is_pagebuilder ) {
		return $css;
	}

	$main_content_css = '';
	if ( $background_image ) {
		$main_content_css = '#main-content { background-color: transparent; }';
	} elseif ( $background_color ) {
		$background_color_with_hash = maybe_hash_hex_color( $background_color );
		$main_content_css           = sprintf(
			'#main-content { background-color: %s; }',
			esc_html( $background_color_with_hash )
		);
	}

	if ( $main_content_css ) {
		$css .= $main_content_css;
	}

	return $css;
}

/**
 * Sync parent background theme mods into child theme on activation.
 *
 * @since ??
 *
 * @return void
 */
function et_divi_sync_background_theme_mods_on_child_activation() {
	if ( ! is_child_theme() ) {
		return;
	}

	$parent_stylesheet = get_template();
	$child_stylesheet  = get_stylesheet();

	if ( $parent_stylesheet === $child_stylesheet ) {
		return;
	}

	$parent_theme_mods = get_option( "theme_mods_{$parent_stylesheet}" );
	if ( ! is_array( $parent_theme_mods ) ) {
		return;
	}

	$child_theme_mods = get_option( "theme_mods_{$child_stylesheet}" );
	if ( ! is_array( $child_theme_mods ) ) {
		$child_theme_mods = array();
	}

	$background_keys = array(
		'background_color',
		'background_image',
	);

	foreach ( $background_keys as $background_key ) {
		$child_has_background_value = array_key_exists( $background_key, $child_theme_mods );
		$child_background_value     = $child_has_background_value ? $child_theme_mods[ $background_key ] : null;
		$child_value_is_empty       = '' === $child_background_value || null === $child_background_value || false === $child_background_value;

		if ( $child_has_background_value && ! $child_value_is_empty ) {
			continue;
		}

		if ( ! array_key_exists( $background_key, $parent_theme_mods ) ) {
			continue;
		}

		$parent_background_value = $parent_theme_mods[ $background_key ];

		if ( '' === $parent_background_value || null === $parent_background_value || false === $parent_background_value ) {
			continue;
		}

		set_theme_mod( $background_key, $parent_background_value );
	}
}

add_filter( 'et_divi_theme_customizer_css_output', 'et_divi_add_main_content_background_css' );
add_action( 'after_switch_theme', 'et_divi_sync_background_theme_mods_on_child_activation' );
