<?php
/**
 * Frontend Font Loader
 *
 * @package Divi
 *
 * @since ??
 */

namespace ET\Builder\FrontEnd\Module;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Utility\Conditions;

/**
 * Font Loader class.
 *
 * Responsible for loading fonts in the frontend.
 *
 * @since ??
 */
class Fonts {

	/**
	 * Theme Customizer default body font size sentinel (unchanged from legacy `functions.php`).
	 *
	 * @var int
	 */
	private const DEFAULT_BODY_FONT_SIZE = 14;

	/**
	 * Keep track of Fonts added.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	public static $_fonts_added = [];

	/**
	 * Reset fonts added.
	 *
	 * @since ??
	 */
	public static function reset(): void {
		self::$_fonts_added = [];
	}

	/**
	 * Add a font family to the store.
	 *
	 * Enqueue a given font family for use in the Builder.
	 *
	 * @since ??
	 *
	 * @param string $font_family The name of the font family.
	 *
	 * @return void
	 *
	 * @example: Enqueue the 'Open Sans' font family.
	 * ```php
	 * add_font_family( 'Open Sans' );
	 * ```
	 */
	public static function add( string $font_family ): void {
		if ( ! empty( $font_family ) && ! in_array( $font_family, self::$_fonts_added, true ) ) {
			self::$_fonts_added[] = $font_family;

			// phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
			// TODO feat(D5, FE Rendering): Need to rewrite et_builder_enqueue_font in D5.
			et_builder_enqueue_font( $font_family );

		}
	}

	/**
	 * Enqueue user custom fonts
	 *
	 * This function is used to enqueue custom fonts specified by the user. It takes in an array of
	 * font URLs and registers them using the WordPress `wp_enqueue_style` function. This allows the
	 * fonts to be loaded on the front-end of the website.
	 *
	 * @since ??
	 *
	 * @see wp_enqueue_style() To register and enqueue the custom font stylesheets.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		$heading_font         = et_get_option( 'heading_font', 'Open Sans' );
		$body_font            = et_get_option( 'body_font', 'Open Sans' );
		$body_font_weight     = et_get_option( 'body_font_weight', '500' );
		$heading_font_weight  = et_get_option( 'heading_font_weight', '500' );
		$body_font_size_raw   = et_get_option( 'body_font_size', '14' );
		$body_font_height_raw = et_get_option( 'body_font_height', '1.7' );
		$body_font_size         = absint( '' === $body_font_size_raw ? '14' : $body_font_size_raw );
		$body_font_height       = floatval( '' === $body_font_height_raw ? '1.7' : $body_font_height_raw );
		$tablet_body_font_size  = absint( et_get_option( 'tablet_body_font_size', '14' ) );
		$phone_body_font_size   = absint( et_get_option( 'phone_body_font_size', $tablet_body_font_size ) );

		// Map legacy Google Fonts names to their modern equivalents.
		// Google Fonts renamed "Source Sans Pro" to "Source Sans 3" in their API.
		// This ensures D4 sites with "Source Sans Pro" in Theme Customizer options
		// automatically load the correct modern font in D5.
		$font_aliases = [
			'Source Sans Pro'       => 'Source Sans 3',
			'Source Sans Pro Light' => 'Source Sans 3',
		];

		// Apply font aliases to heading and body fonts.
		if ( isset( $font_aliases[ $heading_font ] ) ) {
			$heading_font = $font_aliases[ $heading_font ];
		}

		if ( isset( $font_aliases[ $body_font ] ) ) {
			$body_font = $font_aliases[ $body_font ];
		}

		// Apply fallback to 'Open Sans' if font is empty to match CSS variable defaults.
		$heading_font = $heading_font ? $heading_font : 'Open Sans';
		$body_font    = $body_font ? $body_font : 'Open Sans';

		$google_fonts_url            = function_exists( 'et_divi_fonts_url' ) ? et_divi_fonts_url() : '';
		$body_font_option            = et_get_option( 'body_font', 'none' );
		$theme_uses_default_bodyfont = is_customize_preview() || 'none' === $body_font_option || '' === $body_font_option;
		$theme_enqueues_open_sans    = ! empty( $google_fonts_url ) && $theme_uses_default_bodyfont;

		// Enqueue the fonts so they are actually loaded on the frontend.
		if ( ! ( $theme_enqueues_open_sans && 0 === strcasecmp( trim( (string) $heading_font ), 'Open Sans' ) ) ) {
			self::add( $heading_font );
		}

		if ( ! ( $theme_enqueues_open_sans && 0 === strcasecmp( trim( (string) $body_font ), 'Open Sans' ) ) ) {
			self::add( $body_font );
		}

		$global_font_vars = '';
		$customizer_fonts = [
			'--et_global_heading_font'        => $heading_font,
			'--et_global_body_font'           => $body_font,
			'--et_global_heading_font_weight' => $heading_font_weight ? $heading_font_weight : '500',
			'--et_global_body_font_weight'    => $body_font_weight ? $body_font_weight : '500',
			'--et_global_body_font_size'      => $body_font_size . 'px',
			'--et_global_body_font_height'    => $body_font_height . 'em',
		];

		foreach ( $customizer_fonts as $var_name => $value ) {
			if ( substr( $var_name, -5 ) === '_font' && 'none' === $value ) {
				continue;
			}

			if ( ! empty( $value ) ) {
				$needs_ms_version = false;
				$is_font          = substr( $var_name, -5 ) === '_font' && 'none' !== $value;
				if ( $is_font ) {
					$formatted_font_value = FontUtils::format_font_value_with_ms_version( $value );
					$needs_ms_version     = str_contains( $formatted_font_value, " MS', '" );
					$value                = $formatted_font_value;
				} else {
					$value = esc_html( $value );
				}

				$quote             = $is_font && ! $needs_ms_version ? "'" : '';
				$global_font_vars .= esc_html( $var_name ) . ': ' . $quote . $value . $quote . ';';
			}
		}

		// Only load on FE. VB loads this via the modules root component.
		if ( ! empty( $global_font_vars ) && ! Conditions::is_vb_enabled() ) {
			$responsive_body_font_size_css = self::get_responsive_body_font_size_css_suffix(
				$body_font_size,
				$tablet_body_font_size,
				$phone_body_font_size
			);

			$global_fonts_style = ':root{' . $global_font_vars . '}body{line-height:var(--et_global_body_font_height);font-size:var(--et_global_body_font_size);}' . $responsive_body_font_size_css;

			echo '<style class="et-vb-global-data et-vb-global-fonts">';
			echo et_core_esc_previously( ( $global_fonts_style ) );
			echo '</style>';
		}
	}

	/**
	 * Build responsive `:root` overrides for `--et_global_body_font_size`.
	 *
	 * Guard logic matches legacy `functions.php` tablet/phone body font-size rules.
	 *
	 * @since ??
	 *
	 * @param int $body_font_size   Desktop body font size in px.
	 * @param int $tablet_body_font_size Tablet body font size in px.
	 * @param int $phone_body_font_size Phone body font size in px.
	 *
	 * @return string Minified CSS suffix (empty when no overrides needed).
	 */
	private static function get_responsive_body_font_size_css_suffix(
		int $body_font_size,
		int $tablet_body_font_size,
		int $phone_body_font_size
	): string {
		$suffix = '';

		if ( self::DEFAULT_BODY_FONT_SIZE !== $tablet_body_font_size && $body_font_size !== $tablet_body_font_size ) {
			$suffix .= '@media only screen and ( max-width: 980px ){:root{--et_global_body_font_size:' . esc_html( (string) $tablet_body_font_size ) . 'px;}}';
		}

		if ( self::DEFAULT_BODY_FONT_SIZE !== $phone_body_font_size && $phone_body_font_size !== $tablet_body_font_size ) {
			$suffix .= '@media only screen and ( max-width: 767px ){:root{--et_global_body_font_size:' . esc_html( (string) $phone_body_font_size ) . 'px;}}';
		}

		return $suffix;
	}
}
