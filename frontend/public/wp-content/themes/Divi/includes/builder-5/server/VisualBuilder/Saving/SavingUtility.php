<?php
/**
 * Saving: SavingUtility class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\Saving;

// phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames -- Reserved keywords used intentionally for clarity in utility functions.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\VisualBuilder\Assets\AssetsUtility;
use ET\Builder\Framework\Utility\StringUtility;
use ET\Builder\Framework\Utility\ArrayUtility;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\VisualBuilder\AppPreferences\AppPreferences;

/**
 * SavingUtility class.
 *
 * A utility class for saving and sanitizing data.
 *
 * @since ??
 */
class SavingUtility {

	/**
	 * Function to prime the page cache when a post is saved.
	 *
	 * @param int   $post_id         Post ID.
	 * @param mixed $prime_cache_url Optional URL passed from the client context.
	 */
	public static function prime_page_cache_on_save( $post_id, $prime_cache_url = '' ) {
		// Get the post object, once.
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		// The Visual Builder is used to save these post types, but they are not available on the front end and don't need their cache primed.
		$excluded_post_types = [
			ET_BUILDER_LAYOUT_POST_TYPE,
			ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE,
			ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE,
			ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE,
		];

		// If the post type is in the excluded array, return false.
		if ( in_array( $post->post_type, $excluded_post_types, true ) ) {
			return;
		}

		// Check if the post is published. If not, return false.
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		$resolved_prime_cache_url = self::_resolve_prime_cache_url( $prime_cache_url );
		$cache_url                = $resolved_prime_cache_url ? $resolved_prime_cache_url : get_permalink( $post );

		/**
		 * Filters the URL to prime the cache for.
		 *
		 * @since ??
		 *
		 * @param string  $cache_url       The URL to prime the cache for.
		 * @param int     $post_id         The post ID.
		 * @param WP_Post $post            The post object.
		 * @param string  $prime_cache_url Optional URL passed from the client context.
		 */
		$url = apply_filters( 'et_builder_priming_cache_url', $cache_url, $post_id, $post, $resolved_prime_cache_url );

		if ( ! $url ) {
			return;
		}

		wp_remote_get(
			esc_url_raw( $url ),
			[
				'timeout'  => 1,  // Set a short timeout to avoid long delays.
				'blocking' => false, // Non-blocking request to avoid affecting save performance.
			]
		);
	}

	/**
	 * Resolve cache priming URL from client context.
	 *
	 * @since ??
	 *
	 * @param mixed $prime_cache_url Optional URL passed from the client context.
	 *
	 * @return string
	 */
	private static function _resolve_prime_cache_url( $prime_cache_url ) {
		if ( ! is_string( $prime_cache_url ) || '' === $prime_cache_url ) {
			return '';
		}

		$sanitized_prime_cache_url = esc_url_raw( $prime_cache_url );

		if ( '' === $sanitized_prime_cache_url ) {
			return '';
		}

		$site_host            = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$prime_cache_url_host = wp_parse_url( $sanitized_prime_cache_url, PHP_URL_HOST );

		if ( ! $prime_cache_url_host && '/' === substr( $sanitized_prime_cache_url, 0, 1 ) ) {
			$sanitized_prime_cache_url = home_url( $sanitized_prime_cache_url );
			$prime_cache_url_host      = wp_parse_url( $sanitized_prime_cache_url, PHP_URL_HOST );
		}

		// Ignore URLs that don't match current site host.
		if ( $site_host && strtolower( (string) $prime_cache_url_host ) !== strtolower( (string) $site_host ) ) {
			return '';
		}

		$sanitized_prime_cache_url = remove_query_arg(
			[
				'et_fb',
				'et_bfb',
				'et_fb_iframe',
				'et_builder',
			],
			$sanitized_prime_cache_url
		);

		return esc_url_raw( $sanitized_prime_cache_url );
	}

	/**
	 * Determine if a string is valid JSON.
	 *
	 * This function checks if the given string is a valid JSON by performing the following checks:
	 *  - It verifies if the string is a valid string.
	 *  - It decodes the JSON string and checks if it returns an array.
	 *  - It checks if there are no JSON decoding errors.
	 *
	 * @since ??
	 *
	 * @param string $string The string to check if it is a valid JSON.
	 *
	 * @return bool Returns `true` if the string is a valid JSON, `false` otherwise.
	 *
	 * @example:
	 * ```php
	 * $string = '{"name": "<script>alert(1)</script>", "email": "test@example.com"}';
	 * $isJson = SavingUtility::is_json($string);
	 *
	 * var_dump($isJson);
	 *
	 * // Output: bool(true)
	 * ```
	 */
	public static function is_json( string $string ): bool {
		return is_string( $string ) && is_array( json_decode( $string, true ) ) && ( JSON_ERROR_NONE === json_last_error() );
	}

	/**
	 * Parse the given value based on its type.
	 *
	 * This function takes a value and a type and returns the parsed value based on the type.
	 * If the type is 'boolean', the function checks if the value is a boolean and returns it.
	 * If the value is not a boolean, it checks if the value is 1, '1', or 'true' and returns true,
	 * otherwise it returns false.
	 * If the type is 'int', the function converts the value to an integer using the `intval()` function.
	 * For any other type, the function simply returns the value as is.
	 *
	 * @since ??
	 *
	 * @param mixed  $value The value to be parsed.
	 * @param string $type  Optional. The type of the value. Default `'string'`.
	 *
	 * @return mixed The parsed value.
	 *
	 * @example:
	 * ```php
	 * // Parsing a boolean value
	 * $value = true;
	 * $type = 'bool';
	 * $parsed_value = SavingUtility::parse_value_type($value, $type);
	 * ```
	 *
	 * @example:
	 * ```php
	 * // Parsing an integer value
	 * $value = '10';
	 * $type = 'int';
	 * $parsed_value = SavingUtility::parse_value_type($value, $type);
	 * ```
	 *
	 * @example:
	 * ```php
	 * // Parsing a string value
	 * $value = 'example';
	 * $type = 'string';
	 * $parsed_value = SavingUtility::parse_value_type($value, $type);
	 * ```
	 */
	public static function parse_value_type( $value, string $type = 'string' ) {
		switch ( $type ) {
			case 'bool':
				if ( is_bool( $value ) ) {
					return $value;
				}
				return ( 1 === $value || '1' === $value || 'true' === $value ) ? true : false;
			case 'int':
				return intval( $value );
			default:
				return $value;
		}
	}

	/**
	 * Prepare the post content for database storage by parsing and sanitizing the blocks.
	 *
	 * @since ??
	 *
	 * @param string|null $post_content The content of the post.
	 *                                  It is assumed the content passed here has been cleaned with `stripslashes()`.
	 *
	 * @return string The serialized and sanitized blocks.
	 */
	public static function prepare_content_for_db( ?string $post_content ): string {
		// If the post content is null or an empty string, return an empty string.
		if ( null === $post_content || '' === $post_content ) {
			return '';
		}

		// If the user has the 'unfiltered_html' capability, return the post content as is and let's WordPress handle the sanitization.
		//
		// Discussion & ADR:
		// - https://elegantthemes.slack.com/archives/C01CW343ZJ9/p1640351289407000.
		if ( current_user_can( 'unfiltered_html' ) ) {
			return $post_content;
		}

		// phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
		// @todo feat(D5, Saving Mechanism) Revisit this later to fix the special characters issue for users without 'unfiltered_html' capability.
		//
		// Known issues:
		// - https://github.com/elegantthemes/Divi/issues/36607
		// - https://github.com/elegantthemes/Divi/issues/36645
		//
		// Discussion & ADR:
		// - https://elegantthemes.slack.com/archives/C01CW343ZJ9/p1640351289407000
		return self::serialize_sanitize_blocks( parse_blocks( $post_content ) );
	}

	/**
	 * Whether a nested attribute path points at module Custom CSS free-form (full rules).
	 *
	 * Used when sanitizing strings that must allow CSS child combinators (`>`) and must not
	 * pass through `wp_kses_post()`, which encodes `>` as `&gt;`.
	 *
	 * @since ??
	 *
	 * @param array $path Path segments (keys from root block attrs).
	 *
	 * @return bool
	 */
	public static function is_module_css_free_form_attr_path( array $path ): bool {
		$path_length = count( $path );

		if ( 2 > $path_length ) {
			return false;
		}

		if ( 'freeForm' !== $path[ $path_length - 1 ] ) {
			return false;
		}

		return in_array( 'css', $path, true );
	}

	/**
	 * Sanitizes CSS properties and removes disallowed rules.
	 *
	 * @since ??
	 *
	 * @param string $css            A string of CSS rules.
	 * @param bool   $allow_comments Optional. Flag to allow CSS comments. Default `false`.
	 *
	 * @return string
	 */
	public static function sanitize_css_properties( string $css, bool $allow_comments = false ): string {
		$css = wp_kses_no_null( $css );

		if ( '' === $css ) {
			return '';
		}

		$allowed_protocols = wp_allowed_protocols();

		// Add `data` to allowed protocols for SVG background images.
		if ( ! in_array( 'data', $allowed_protocols, true ) ) {
			$allowed_protocols[] = 'data';
		}

		$allowed_properties_defaults = [
			'accent-color',
			'align-content',
			'align-items',
			'align-self',
			'animation-delay',
			'animation-direction',
			'animation-duration',
			'animation-fill-mode',
			'animation-iteration-count',
			'animation-name',
			'animation-play-state',
			'animation-timing-function',
			'animation',
			'appearance',
			'aspect-ratio',
			'backdrop-filter',
			'backface-visibility',
			'background-attachment',
			'background-blend-mode',
			'background-clip',
			'background-color',
			'background-image',
			'background-origin',
			'background-position',
			'background-position-x',
			'background-position-y',
			'background-repeat',
			'background-size',
			'background',
			'border',
			'border-bottom-color',
			'border-bottom-left-radius',
			'border-bottom-right-radius',
			'border-bottom-style',
			'border-bottom-width',
			'border-bottom',
			'border-collapse',
			'border-color',
			'border-image-outset',
			'border-image-repeat',
			'border-image-slice',
			'border-image-source',
			'border-image-width',
			'border-image',
			'border-left-color',
			'border-left-style',
			'border-left-width',
			'border-left',
			'border-radius',
			'border-right-color',
			'border-right-style',
			'border-right-width',
			'border-right',
			'border-spacing',
			'border-style',
			'border-top-color',
			'border-top-left-radius',
			'border-top-right-radius',
			'border-top-style',
			'border-top-width',
			'border-top',
			'border-width',
			'bottom',
			'box-shadow',
			'box-sizing',
			'break-after',
			'break-before',
			'break-inside',
			'caption-side',
			'caret-color',
			'clear',
			'color-scheme',
			'color',
			'column-count',
			'column-fill',
			'column-gap',
			'column-rule-color',
			'column-rule-style',
			'column-rule-width',
			'column-rule',
			'column-span',
			'column-width',
			'columns',
			'contain',
			'content-visibility',
			'content',
			'counter-increment',
			'counter-reset',
			'counter-set',
			'cursor',
			'direction',
			'display',
			'empty-cells',
			'filter',
			'flex-basis',
			'flex-direction',
			'flex-flow',
			'flex-grow',
			'flex-shrink',
			'flex-wrap',
			'flex',
			'float',
			'font-family',
			'font-feature-settings',
			'font-kerning',
			'font-language-override',
			'font-optical-sizing',
			'font-size-adjust',
			'font-size',
			'font-stretch',
			'font-style',
			'font-synthesis',
			'font-variant-alternates',
			'font-variant-caps',
			'font-variant-east-asian',
			'font-variant-ligatures',
			'font-variant-numeric',
			'font-variant-position',
			'font-variant',
			'font-variation-settings',
			'font-weight',
			'font',
			'forced-color-adjust',
			'gap',
			'grid-area',
			'grid-auto-columns',
			'grid-auto-flow',
			'grid-auto-rows',
			'grid-column-end',
			'grid-column-gap',
			'grid-column-start',
			'grid-column',
			'grid-gap',
			'grid-row-end',
			'grid-row-gap',
			'grid-row-start',
			'grid-row',
			'grid-template-areas',
			'grid-template-columns',
			'grid-template-rows',
			'grid-template',
			'grid',
			'height',
			'hyphens',
			'image-rendering',
			'inline-size',
			'isolation',
			'justify-content',
			'justify-items',
			'justify-self',
			'left',
			'letter-spacing',
			'line-break',
			'line-height-step',
			'line-height',
			'list-style-image',
			'list-style-position',
			'list-style-type',
			'list-style',
			'margin-bottom',
			'margin-left',
			'margin-right',
			'margin-top',
			'margin',
			'mask-border',
			'mask-type',
			'mask',
			'max-height',
			'max-width',
			'min-height',
			'min-width',
			'mix-blend-mode',
			'object-fit',
			'object-position',
			'opacity',
			'order',
			'orphans',
			'outline-color',
			'outline-offset',
			'outline-style',
			'outline-width',
			'outline',
			'overflow-anchor',
			'overflow-block',
			'overflow-clip-margin',
			'overflow-inline',
			'overflow-wrap',
			'overflow-x',
			'overflow-y',
			'overflow',
			'overscroll-behavior-block',
			'overscroll-behavior-inline',
			'overscroll-behavior-x',
			'overscroll-behavior-y',
			'overscroll-behavior',
			'padding-bottom',
			'padding-left',
			'padding-right',
			'padding-top',
			'padding',
			'paint-order',
			'page-break-after',
			'page-break-before',
			'page-break-inside',
			'perspective-origin',
			'perspective',
			'place-content',
			'place-items',
			'place-self',
			'pointer-events',
			'position',
			'quotes',
			'resize',
			'right',
			'rotate',
			'row-gap',
			'ruby-align',
			'ruby-position',
			'scale',
			'scroll-behavior',
			'scroll-margin-bottom',
			'scroll-margin-inline-start',
			'scroll-margin-inline',
			'scroll-margin-left',
			'scroll-margin-right',
			'scroll-margin-top',
			'scroll-padding-block-end',
			'scroll-padding-block-start',
			'scroll-padding-bottom',
			'scroll-padding-inline-end',
			'scroll-padding-inline-start',
			'scroll-padding-inline',
			'scroll-padding-left',
			'scroll-padding',
			'scroll-snap-align',
			'scroll-snap-type',
			'scrollbar-color',
			'scrollbar-width',
			'shape-image-threshold',
			'shape-margin',
			'shape-outside',
			'tab-size',
			'table-layout',
			'text-align',
			'text-decoration-color',
			'text-decoration-line',
			'text-decoration-thickness',
			'text-decoration-style',
			'text-decoration',
			'text-emphasis-color',
			'text-emphasis-position',
			'text-emphasis-style',
			'text-emphasis',
			'text-indent',
			'text-justify',
			'text-orientation',
			'text-overflow',
			'text-rendering',
			'text-shadow',
			'text-stroke',
			'text-stroke-color',
			'text-stroke-width',
			'text-transform',
			'text-underline-offset',
			'text-underline-position',
			'text-wrap',
			'top',
			'touch-action',
			'transform-origin',
			'transform',
			'transition-delay',
			'transition-duration',
			'transition-property',
			'transition-timing-function',
			'transition',
			'translate',
			'unicode-bidi',
			'user-select',
			'vertical-align',
			'visibility',
			'white-space',
			'widows',
			'width',
			'will-change',
			'word-break',
			'word-spacing',
			'writing-mode',
			'z-index',
		];

		/**
		 * Filters list of allowed CSS properties.
		 *
		 * This filter is used to allow or disallow certain CSS properties in
		 * accordance with non-experimental and non-deprecated CSS spec.
		 *
		 * @since ??
		 *
		 * @link: https://developer.mozilla.org/en-US/docs/Web/CSS
		 *
		 * @param string[] $allowed_properties_defaults Array of allowed CSS properties.
		 */
		$allowed_properties = apply_filters(
			'divi_visual_builder_sanitize_css_properties_allowed_css_properties',
			$allowed_properties_defaults
		);

		$css_for_matching = $css;
		if ( $allow_comments ) {
			// Replace comment contents with whitespace to preserve offsets.
			// Test Regex: https://regex101.com/r/0Ef7BY/2.
			$css_for_matching = preg_replace_callback(
				'/\/\*[\s\S]*?\*\//',
				function ( $matches ) {
					return str_repeat( ' ', strlen( $matches[0] ) );
				},
				$css
			);
		}

		// Allow CSS custom properties (variables) that start with --.
		// Extract all CSS custom properties from the original CSS and add them to allowed list.
		// Regex matches: --main-color, --button_size, --nav-height-mobile, --primary123.
		// Regex ignores: -single-dash, --, var.
		// See: https://regex101.com/r/dVyt3S/1.
		preg_match_all( '/--[a-zA-Z0-9\-_]+/', $css_for_matching, $custom_property_matches );
		if ( ! empty( $custom_property_matches[0] ) ) {
			$allowed_properties = array_merge( $allowed_properties, $custom_property_matches[0] );
		}

		if ( empty( $allowed_properties ) ) {
			return '';
		}

		$css_url_data_types_defaults = [
			'background',
			'background-image',
			'border-image',
			'border-image-source',
			'content',
			'cursor',
			'filter',
			'list-style',
			'list-style-image',
			'mask',
		];

		/**
		 * CSS properties that accept URL data types.
		 *
		 * @since ??
		 *
		 * @link: https://developer.mozilla.org/en-US/docs/Web/CSS/url
		 *
		 * @param string[] $css_url_data_types_defaults Allowed CSS properties that accept URL data types.
		 */
		$css_url_data_types = apply_filters(
			'divi_visual_builder_sanitize_css_properties_allowed_css_properties_with_url_support',
			$css_url_data_types_defaults
		);

		$css_transform_defaults = [
			'matrix',
			'matrix3d',
			'perspective',
			'rotate',
			'rotate3d',
			'rotateX',
			'rotateY',
			'rotateZ',
			'scale',
			'scale3d',
			'scaleX',
			'scaleY',
			'scaleZ',
			'skew',
			'skewX',
			'skewY',
			'translate',
			'translate3d',
			'translateX',
			'translateY',
			'translateZ',
		];

		/**
		 * These functions are used for the `transform-function` CSS data type.
		 *
		 * @since ??
		 *
		 * @link: https://developer.mozilla.org/en-US/docs/Web/CSS/transform-function
		 *
		 * @param string[] $css_transform_defaults Allowed `transform-function` CSS data type.
		 */
		$css_transform_functions = apply_filters(
			'divi_visual_builder_sanitize_css_properties_allowed_functions_for_transform_property',
			$css_transform_defaults
		);

		$css_filter_function_defaults = [
			'blur',
			'brightness',
			'contrast',
			'drop-shadow',
			'grayscale',
			'hue-rotate',
			'invert',
			'opacity',
			'saturate',
			'sepia',
		];

		/**
		 * These functions are used for the `filter-function` CSS data type.
		 *
		 * @since ??
		 *
		 * @link: https://developer.mozilla.org/en-US/docs/Web/CSS/filter-function
		 *
		 * @param string[] $css_filter_function_defaults Allowed `filter-function` CSS data type.
		 */
		$css_filter_functions = apply_filters(
			'divi_visual_builder_sanitize_css_properties_allowed_functions_for_filter_property',
			$css_filter_function_defaults
		);

		$css_color_function_defaults = [
			'rgb',
			'rgba',
			'hsl',
			'hsla',
		];

		/**
		 * These functions are used for the `color-function` CSS data type.
		 *
		 * @since ??
		 *
		 * @link: https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_Functions#color_functions
		 *
		 * @param string[] $css_color_function_defaults Allowed `color-function` CSS data type.
		 */
		$css_color_functions = apply_filters(
			'divi_visual_builder_sanitize_css_properties_allowed_functions_for_color_property',
			$css_color_function_defaults
		);

		$css_gradient_functions_defaults = [
			'conic-gradient',
			'linear-gradient',
			'radial-gradient',
			'repeating-conic-gradient',
			'repeating-linear-gradient',
			'repeating-radial-gradient',
		];

		/**
		 * These functions are used for the `gradient-function` CSS data type.
		 *
		 * @since ??
		 *
		 * @link: https://developer.mozilla.org/en-US/docs/Web/CSS/gradient
		 *
		 * @param string[] $css_gradient_functions_defaults Allowed `gradient-function` CSS data type.
		 */
		$css_gradient_functions = apply_filters(
			'divi_visual_builder_sanitize_css_properties_allowed_functions_for_gradient_property',
			$css_gradient_functions_defaults
		);

		$css_shape_functions_defaults = [
			'circle',
			'ellipse',
			'inset',
			'polygon',
		];

		/**
		 * These functions are used for the `basic-shape` CSS data type.
		 *
		 * @since ??
		 *
		 * @link: https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_Functions#shape_functions
		 *
		 * @param string[] $css_shape_functions_defaults Allowed `basic-shape` CSS data type.
		 */
		$css_shape_functions = apply_filters(
			'divi_visual_builder_sanitize_css_properties_allowed_functions_for_shape_property',
			$css_shape_functions_defaults
		);

		$css_timing_function_defaults = [
			'cubic-bezier',
			'steps',
			'linear',
			'ease',
			'ease-in',
			'ease-out',
			'ease-in-out',
		];

		/**
		 * These functions are used for the `timing-function` CSS data type.
		 *
		 * @since ??
		 *
		 * @link https://developer.mozilla.org/en-US/docs/Web/CSS/easing-function
		 *
		 * @param string[] $css_timing_function_defaults Allowed `timing-function` CSS data type.
		 */
		$css_timing_functions = apply_filters(
			'divi_visual_builder_sanitize_css_properties_allowed_functions_for_timing_property',
			$css_timing_function_defaults
		);

		$css_units_defaults = [
			'%',
			'ch',
			'cm',
			'deg',
			'em',
			'ex',
			'in',
			'mm',
			'pc',
			'pt',
			'px',
			'rad',
			'rem',
			'turn',
			'vh',
			'vmax',
			'vmin',
			'vw',
		];

		/**
		 * Various allowed units in CSS.
		 *
		 * @since ??
		 *
		 * @param string[] $css_units_defaults Allowed CSS units.
		 */
		$css_units = apply_filters(
			'divi_visual_builder_sanitize_css_properties_allowed_units',
			$css_units_defaults
		);

		$css_sanitized        = '';
		$extracted_properties = [];

		// Collect properties that are used in the CSS.
		$used_properties = array_filter(
			$allowed_properties,
			function ( $allow ) use ( $css_for_matching ) {
				// Test Regex: https://regex101.com/r/WI7Sdv/1.
				// The ending `/i` is important for case insensitivity.
				return (bool) preg_match( '/' . $allow . '\s*?:(.+)/is', $css_for_matching );
			}
		);

		// Extract CSS variables from the CSS string e.g. `--mainColor`.
		$used_css_variables = self::extract_css_variables( $css_for_matching );

		// Merge the used properties and variables used in the CSS.
		$used_properties = array_merge( $used_properties, $used_css_variables );

		// Detect vendor-prefixed versions of ALL allowed properties (e.g., -webkit-animation, -moz-transform).
		// This must check all allowed properties, not just used ones, because CSS might only contain
		// vendor-prefixed versions without the standard property.
		// Test Regex: https://regex101.com/r/8KvN2X/1.
		$vendor_prefixes            = [ '-webkit-', '-moz-', '-o-', '-ms-' ];
		$vendor_prefixed_properties = [];

		foreach ( $allowed_properties as $property ) {
			foreach ( $vendor_prefixes as $prefix ) {
				$prefixed_property = $prefix . $property;
				// Check if this vendor-prefixed property exists in the CSS.
				if ( preg_match( '/' . preg_quote( $prefixed_property, '/' ) . '\s*?:(.+)/is', $css_for_matching ) ) {
					$vendor_prefixed_properties[] = $prefixed_property;
				}
			}
		}

		// Merge vendor-prefixed properties with used properties.
		$used_properties = array_merge( $used_properties, $vendor_prefixed_properties );

		if ( empty( $used_properties ) ) {
			return '';
		}

		// Reset the array index of used properties.
		$used_properties = array_values( $used_properties );

		// Prefer longer property names to avoid partial matches (e.g. background-image vs background).
		usort(
			$used_properties,
			function ( $left, $right ) {
				return strlen( $right ) <=> strlen( $left );
			}
		);

		$escaped_properties = array_map(
			function ( $property ) {
				return preg_quote( $property, '/' );
			},
			$used_properties
		);

		// Match property declarations in-order using offsets.
		// Match property names at start or after a rule delimiter.
		$property_pattern = '/(^|[;{])\s*(' . implode( '|', $escaped_properties ) . ')\s*:/i';
		preg_match_all( $property_pattern, $css_for_matching, $property_matches, PREG_OFFSET_CAPTURE );

		if ( empty( $property_matches[2] ) ) {
			return '';
		}

		$property_occurrences = $property_matches[2];
		$css_length           = strlen( $css );
		$total_occurrences    = count( $property_occurrences );

		if ( $allow_comments ) {
			$first_property_pos = $property_occurrences[0][1];
			$leading_css        = substr( $css, 0, $first_property_pos );

			if ( '' !== $leading_css ) {
				// Preserve leading comments before the first property.
				// Test Regex: https://regex101.com/r/GJaZHT/1.
				if ( preg_match_all( '/\s*\/\*.*?\*\/\s*/s', $leading_css, $leading_comment_matches ) ) {
					$css_sanitized .= implode( '', $leading_comment_matches[0] );
				}
			}
		}

		for ( $index = 0; $index < $total_occurrences; $index++ ) {
			$property      = $property_occurrences[ $index ][0];
			$property_pos  = $property_occurrences[ $index ][1];
			$next_property = $property_occurrences[ $index + 1 ] ?? null;
			$end_pos       = $next_property ? $next_property[1] : $css_length;
			$declaration   = substr( $css, $property_pos, $end_pos - $property_pos );

			// Match the declaration as: exact property name, optional spaces around colon, then the full value up to end of declaration.
			// Purpose: reliably split each declaration into property/colon/value parts without partial matches from malformed or concatenated fragments.
			// Test Regex: https://regex101.com/r/p9mvLN/1.
			if ( ! preg_match( '/^(' . preg_quote( $property, '/' ) . ')(\s*?:)([\s\S]*?)\z/i', $declaration, $parts ) ) {
				continue;
			}

			$extracted_properties[] = [
				'property' => $parts[1],
				'colon'    => $parts[2],
				'value'    => $parts[3],
			];
		}

		if ( empty( $extracted_properties ) ) {
			return '';
		}

		// Loop through the extracted CSS properties and values to populate the sanitized CSS string.
		foreach ( $extracted_properties as $item ) {
			$property        = $item['property'];
			$colon           = $item['colon'];
			$value           = $item['value'];
			$declaration     = $property . $colon . $value; // Combined string of the CSS property, colon, and value including the semicolon at the end if any. Example: `color : #fff;`.
			$css_test_string = $declaration;

			/**
			 * Allow certain CSS Functional Notation in value.
			 *
			 * This is in accordance to the non experimental CSS spec.
			 *
			 * @link https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_Functions
			 */

			// Allow vendor-prefixed CSS functions (e.g., -webkit-image-set, -moz-linear-gradient).
			// This must happen BEFORE URL validation, because URL validation removes url() functions
			// which can break the vendor-prefixed function pattern matching.
			// Uses the same nested parentheses pattern as clamp() and calc() for consistency.
			// Test Regex: https://regex101.com/r/9KvN2X/3.
			$css_test_string = preg_replace( '/(-webkit-|-moz-|-o-|-ms-)[a-zA-Z-]+\(((?:\([^()]*\)?|[^()])*)\)/', '', $css_test_string );

			// Allow CSS url().
			if ( in_array( $property, $css_url_data_types, true ) ) {
				$url_attr_valid = true;

				// Regex pattern to match everything between `url(` string.
				// Test Regex: https://regex101.com/r/JXnRGn/2.
				// Allow matches across newlines to preserve formatting safely.
				$regex_pattern = '/url\(.+(?=(url\())/Us';

				/**
				 * Loop through the CSS declaration to extract the URL function and then truncate the extracted URL function
				 * from the CSS declaration for the next loop iteration. This is done to prevent the regex pattern from matching the same CSS declaration multiple times.
				 * It also needs to append `url(` to the CSS declaration that passed into the `preg_match` function.
				 * This is done to ensure that the regex pattern matches all CSS url function that are used.
				 *
				 * Example:
				 * - Original CSS declaration ($css_test_string): `url(image1.jpg), url(image2.jpg);`.
				 * - 1st Iteration:
				 *      -- CSS declaration passed into `preg_match`: `url(image1.jpg), url(image2.jpg);url(`.
				 *      -- Matched string ($matches[0]): `url(image1.jpg), `
				 *      -- Extracted url ($url_pieces[1]): `image1.jpg`
				 *      -- CSS declaration truncated to `url(image2.jpg);`.
				 * - 2nd Iteration:
				 *      -- CSS string passed into `preg_match`: `url(image2.jpg);url(`.
				 *      -- Matched string ($matches[0]): `url(image2.jpg);`
				 *      -- Extracted url ($url_pieces[1]): `image2.jpg`
				 *      -- CSS declaration truncated to an empty string.
				 *      -- Break the loop.
				 */
				while ( '' !== $css_test_string && true === (bool) preg_match( $regex_pattern, "{$css_test_string}url(", $matches ) ) {
					if ( empty( $matches[0] ) ) {
						break;
					}

					// Extract the URL from each of the matches above.
					// Test Regex: https://regex101.com/r/JXnRGn/1.
					preg_match( '/url\((.+)\)/', $matches[0], $url_pieces );

					if ( empty( $url_pieces[1] ) ) {
						$url_attr_valid = false;
						break;
					}

					$url = StringUtility::trim_extended( $url_pieces[1], [ ' ', '"', '\'' ] );

					if ( empty( $url ) || wp_kses_bad_protocol( $url, $allowed_protocols ) !== $url ) {
						$url_attr_valid = false;
						break;
					}

					// Remove the whole `url(*)` bit that was matched above from the CSS.
					$css_test_string_truncated = substr_replace( $css_test_string, '', strpos( $css_test_string, $matches[0] ), strlen( $matches[0] ) );

					if ( $css_test_string_truncated === $css_test_string ) {
						break;
					}

					$css_test_string = $css_test_string_truncated;
				}

				if ( ! $url_attr_valid ) {
					continue;
				}
			}

			// Allow CSS <transform-function>.
			// Check for both standard and vendor-prefixed transform properties.
			$transform_properties = [
				'transform',
				'-webkit-transform',
				'-moz-transform',
				'-o-transform',
				'-ms-transform',
			];
			if ( in_array( $property, $transform_properties, true ) && $css_transform_functions ) {
				// Test Regex: https://regex101.com/r/D6jB3s/2.
				$css_test_string = preg_replace( '/(' . implode( '|', $css_transform_functions ) . ')\((\.|-)?[0-9\.]+(' . implode( '|', $css_units ) . ')?+(,\s?(\.|-)?[0-9\.]+(' . implode( '|', $css_units ) . ')?+)*\)/', '', $css_test_string );
			}

			// Allow CSS <filter-function>.
			// Check for both standard and vendor-prefixed filter/backdrop-filter properties.
			$filter_properties = [
				'filter',
				'backdrop-filter',
				'-webkit-filter',
				'-moz-filter',
				'-o-filter',
				'-ms-filter',
				'-webkit-backdrop-filter',
				'-moz-backdrop-filter',
				'-o-backdrop-filter',
				'-ms-backdrop-filter',
			];
			if ( in_array( $property, $filter_properties, true ) && $css_filter_functions ) {
				foreach ( $css_filter_functions as $css_filter_function ) {
					if ( 'drop-shadow' === $css_filter_function ) {
						// Test Regex: https://regex101.com/r/sJR3Zj/2.
						$css_test_string = preg_replace( '/drop-shadow\(((\.|-)?[0-9\.]+(' . implode( '|', $css_units ) . ')?+\s){3}+(#(?:[0-9a-fA-F]{3}){1,2}|[a-zAz0-9]|(hsl|hsla|rgb|rgba)\(([0-9]{1,3})+(((\s|,|,\s)[0-9]{1,3}+(%)?){2})+([\s\/,]+(\.|-)?+[0-9\.]+)?\))*\)/', '', $css_test_string );
					} else {
						// Test Regex: https://regex101.com/r/CuT1Bk/1.
						$css_test_string = preg_replace( '/' . $css_filter_function . '\((\.|-)?[0-9\.]+(' . implode( '|', $css_units ) . ')?\)/', '', $css_test_string );
					}
				}
			}

			// Allow CSS <gradient-function>.
			if ( $css_gradient_functions ) {
				// Test Regex: https://regex101.com/r/igg53c/3.
				// Updated to handle multiple levels of nested parentheses using recursive pattern.
				// Pattern matches gradient function with deeply nested content (e.g., linear-gradient(... hsl(... calc(...)))).
				// Uses recursive regex (?2) to reference second capturing group (content) for balanced parentheses matching.
				// Pattern breakdown:
				// - (conic-gradient|linear-gradient|...) - Function name (capturing group 1)
				// - \( - Opening parenthesis
				// - ((?:[^()]++|\((?:[^()]++|(?2))*\))*) - Balanced content (capturing group 2):
				// - [^()]++ - Non-parentheses characters (possessive for performance)
				// - | - OR
				// - \((?:[^()]++|(?2))*\) - Recursive match of balanced parentheses via (?2)
				// - \) - Closing parenthesis.
				$gradient_pattern = '/(' . implode( '|', $css_gradient_functions ) . ')\(((?:[^()]++|\((?:[^()]++|(?2))*\))*)\)/';
				$css_test_string  = preg_replace( $gradient_pattern, '', $css_test_string );
			}

			// Allow CSS <basic-shape>.
			if ( $css_shape_functions ) {
				// Test Regex: https://regex101.com/r/FV7wWF/1.
				$css_test_string = preg_replace( '/(' . implode( '|', $css_shape_functions ) . ')\(((\.|-)?[0-9\.]+(' . implode( '|', $css_units ) . ')?)+(,?\s(((\.|-)?[0-9\.]+(' . implode( '|', $css_units ) . ')?)|([a-z-]+)))*\)/', '', $css_test_string );
			}

			// Allow CSS <color-function>.
			if ( $css_color_functions ) {
				// Handle HSL relative color syntax (hsl(from ...)) with three calc() expressions for HSL components (hue, saturation, lightness) plus optional alpha.
				// Note: This must run BEFORE calc() is stripped, as it contains calc() functions.
				// Test Regex: https://regex101.com/r/LVTwwI/1.
				$hsl_relative_pattern = '/(hsl|hsla)\(from\s+([#a-zA-Z0-9\-_()\s,]+?)\s+calc\(((?:\([^()]*\)?|[^()])+)\)\s+calc\(((?:\([^()]*\)?|[^()])+)\)\s+calc\(((?:\([^()]*\)?|[^()])+)\)(?:\s*\/\s*[0-9.]+)?\)/';
				$css_test_string      = preg_replace( $hsl_relative_pattern, '', $css_test_string );

				// Then handle traditional color functions (rgb, rgba, hsl, hsla).
				// Test Regex: https://regex101.com/r/H1zTey/2.
				$traditional_color_pattern = '/(' . implode( '|', $css_color_functions ) . ')\(([0-9]{1,3})+(((\s|,|,\s)[0-9]{1,3}+(%)?){2})+([\s\/,]+(\.|-)?+[0-9\.]+)?\)/';
				$css_test_string           = preg_replace( $traditional_color_pattern, '', $css_test_string );
			}

			// Allow CSS <timing-function> (used in animation and transition properties).
			$animation_transition_properties = [
				'animation',
				'animation-timing-function',
				'transition',
				'transition-timing-function',
				'-webkit-animation',
				'-webkit-animation-timing-function',
				'-moz-animation',
				'-moz-animation-timing-function',
				'-o-animation',
				'-o-animation-timing-function',
				'-ms-animation',
				'-ms-animation-timing-function',
				'-webkit-transition',
				'-webkit-transition-timing-function',
				'-moz-transition',
				'-moz-transition-timing-function',
				'-o-transition',
				'-o-transition-timing-function',
				'-ms-transition',
				'-ms-transition-timing-function',
			];
			if ( in_array( $property, $animation_transition_properties, true ) && $css_timing_functions ) {
				// Handle cubic-bezier() with 4 numeric values (e.g., cubic-bezier(.77, 0, .175, 1)).
				// Test Regex: https://regex101.com/r/xKvN2X/2.
				if ( in_array( 'cubic-bezier', $css_timing_functions, true ) ) {
					$css_test_string = preg_replace( '/cubic-bezier\(((\.|-)?[0-9\.]+)(\s*,\s*((\.|-)?[0-9\.]+)){3}\)/', '', $css_test_string );
				}
				// Handle steps() with optional number and direction.
				// Test Regex: https://regex101.com/r/yKvN2X/1.
				if ( in_array( 'steps', $css_timing_functions, true ) ) {
					$css_test_string = preg_replace( '/steps\([0-9]+(\s*,\s*(start|end|jump-start|jump-end|jump-none|jump-both))?\)/', '', $css_test_string );
				}
				// Handle keyword timing functions (linear, ease, etc.) - these don't have parentheses.
				// No regex needed as they're just keywords.
			}

			// Allow CSS clamp().
			// Test Regex: https://regex101.com/r/gCYJmZ/1.
			$css_test_string = preg_replace( '/clamp\(((?:\([^()]*\)?|[^()])*)\)/', '', $css_test_string );

			// Allow CSS calc().
			// Note: This runs after HSL relative color syntax is stripped, so calc() inside hsl(from ...) is already handled above.
			// Test Regex: https://regex101.com/r/TMC0X5/1.
			$css_test_string = preg_replace( '/calc\(((?:\([^()]*\)?|[^()])*)\)/', '', $css_test_string );

			// Allow CSS repeat() (used in grid-template-columns, grid-template-rows, etc.).
			// Test Regex: https://regex101.com/r/aKvN2X/1.
			$css_test_string = preg_replace( '/repeat\(((?:\([^()]*\)?|[^()])*)\)/', '', $css_test_string );

			// Allow CSS minmax() (used in grid-template-columns, grid-template-rows, etc.).
			// Test Regex: https://regex101.com/r/bKvN2X/1.
			$css_test_string = preg_replace( '/minmax\(((?:\([^()]*\)?|[^()])*)\)/', '', $css_test_string );

			// Allow CSS var().
			// Test Regex: https://regex101.com/r/rTGDvT/3.
			$css_test_string = preg_replace( '/\(?var\((--[a-zA-Z0-9\,\-\s](?:.+))\)/', '', $css_test_string );

			// Allow Divi $variable() syntax for dynamic content in CSS custom properties.
			// First, handle url() functions that contain $variable() syntax.
			// Test Regex: https://regex101.com/r/B7mX8f/1.
			$css_test_string = preg_replace( '/url\(\$variable\(.*?\)\$\)/s', '', $css_test_string );

			// Then handle any remaining standalone $variable() syntax.
			// Test Regex: https://regex101.com/r/mKw9Vg/1.
			$css_test_string = preg_replace( '/\$variable\(.*?\)\$/s', '', $css_test_string );

			// Allow CSS counter().
			// Test Regex: https://regex101.com/r/ingNgT/2.
			$css_test_string = preg_replace( '/counter\([a-zA-Z0-9-_]+(,\s?[a-z-]+)?\)/', '', $css_test_string );

			// Allow CSS counters().
			// Test Regex: https://regex101.com/r/C8ovoS/2.
			$css_test_string = preg_replace( '/counters\([a-zA-Z0-9-_]+(,\s?(\'|\")\W+(\'|\"))+(,\s?[a-z-]+)?\)/', '', $css_test_string );

			if ( $allow_comments ) {
				// Remove comments from validation string while preserving output.
				$css_test_string = preg_replace( '/\/\*.*?\*\//s', '', $css_test_string );
			}

			// Check for any CSS containing \ ( & } = or comments.
			$allowed_css = self::_validate_css_with_test_string( $property, $value, $css_test_string );

			/**
			 * Filters the check for unsafe CSS in `safecss_filter_attr`.
			 *
			 * Enables developers to determine whether a section of CSS should be allowed or discarded.
			 * By default, the value will be false if the part contains \ ( & } = or comments.
			 * Return true to allow the CSS part to be included in the output.
			 *
			 * @since WordPress 5.5.0
			 * @since ??
			 *
			 * @param bool   $allowed_css     Whether the CSS in the test string is considered safe.
			 * @param string $declaration     The CSS declaration to test.
			 */
			$allowed_css = apply_filters( 'safecss_filter_attr_allow_css', $allowed_css, $css_test_string );

			// Only add the CSS part if it passes the regex check.
			if ( ! $allowed_css ) {
				continue;
			}

			$css_sanitized .= $declaration;
		}

		if ( '' !== $css_sanitized && preg_match( '/\s+$/', $css, $matches ) ) {
			$css_sanitized  = rtrim( $css_sanitized );
			$css_sanitized .= $matches[0];
		}

		return $css_sanitized;
	}

	/**
	 * Extracts CSS variables from a string of CSS rules.
	 *
	 * @since ??
	 *
	 * @param string $css A string of CSS rules.
	 *
	 * @return array An array of CSS variables e.g. `['--mainColor', '--secondaryColor']`.
	 */
	public static function extract_css_variables( $css ) {
		// Test Regex: https://regex101.com/r/2Kkd4N/1.
		$regex = '/--[\w-]+/';

		if ( preg_match_all( $regex, $css, $matches ) ) {
			// Return the array of matches, which represents the CSS variables.
			return $matches[0];
		}

		// Return an empty array if no matches are found.
		return [];
	}

	/**
	 * Checks if a CSS property and value are allowed based on a given CSS test string.
	 *
	 * @since ??
	 *
	 * @param string $property The CSS property.
	 * @param string $value The CSS value.
	 * @param string $css_test_string The CSS test string.
	 * @return bool Returns true if the CSS property and value are allowed, false otherwise.
	 */
	private static function _validate_css_with_test_string( string $property, string $value, string $css_test_string ): bool {
		// Special handler for `content` property.
		if ( 'content' === $property ) {
			// Strip out semicolon and whitespace.
			$value_clean = str_replace( [ ';', ' ' ], '', $value );

			// Strip out paired quotes.
			$value_clean = StringUtility::trim_pair( $value_clean, [ '"', "'" ] );

			// Check for problematic characters and sequences.
			// Test Regex: https://regex101.com/r/a4ZI0Q/1.
			if ( preg_match( '/[<>{}]/', $value_clean ) ) {
				return false; // Disallow angle brackets, and curly braces.
			}

			// Disallow CSS comments.
			// Test Regex: https://regex101.com/r/P0M8iu/1.
			if ( preg_match( '/\/\*.*?\*\//s', $value_clean ) ) {
				return false;
			}

			// Disallow JavaScript expressions.
			// Test Regex: https://regex101.com/r/umhmDX/1.
			if ( preg_match( '/expression\s*\(.*\)/i', $value_clean ) ) {
				return false;
			}

			// Disallow URLs with dangerous protocols.
			// Test Regex: https://regex101.com/r/2FMMAZ/1.
			if ( preg_match( '/url\s*\(\s*[\'"]?\s*(javascript|data):/i', $value_clean ) ) {
				return false;
			}

			// Allowed special single characters in `content` property:
			// - \ (backslash).

			return true;
		}

		// Characters that are not allowed in CSS:
		// - \ (backslash)
		// - ( (open parenthesis)
		// - & (ampersand)
		// - = (equal sign)
		// - } (close curly brace)
		// - /* (comment start)
		// Test Regex: https://regex101.com/r/3z07PZ/2.
		return ! preg_match( '%[\\\(&=}]|/\*%', $css_test_string );
	}

	/**
	 * Sanitize the app preferences based on the provided Visual Builder preferences.
	 *
	 * This function maps the relevant Visual Builder preferences to the corresponding app preferences,
	 * sanitizes each preference value, and returns the cleaned preferences as an array.
	 *
	 * @since ??
	 *
	 * @param array|null $vb_preferences The Visual Builder preferences to be sanitized.
	 *
	 * @return array The sanitized app preferences.
	 *
	 * @example
	 * ```php
	 * $vb_preferences = [
	 *     'pageSettingsBar' => [
	 *         'modes' => ['click', 'hover'],
	 *         'views' => ['desktop', 'tablet']
	 *     ],
	 *     'location' => 'header',
	 *     'type' => 'text',
	 *     'default' => 'Default Value'
	 * ];
	 *
	 * $clean_preferences = SavingUtility::sanitize_app_preferences( $vb_preferences );
	 * ```
	 */
	public static function sanitize_app_preferences( ?array $vb_preferences ): array {
		$app_preferences   = AppPreferences::mapping();
		$clean_preferences = [];

		$modes = et_()->array_get( $vb_preferences, 'pageSettingsBar.modes', [] );
		$views = et_()->array_get( $vb_preferences, 'pageSettingsBar.views', [] );

		foreach ( $app_preferences as $preference_key => $preference ) {
			$save_value = '';
			switch ( $preference_key ) {
				case 'toolbarClick':
					$save_value = in_array( 'click', $modes, true );
					break;
				case 'toolbarGrid':
					$save_value = in_array( 'grid', $modes, true );
					break;
				case 'toolbarHover':
					$save_value = in_array( 'hover', $modes, true );
					break;
				case 'toolbarDesktop':
					$save_value = in_array( 'desktop', $views, true );
					break;
				case 'toolbarPhone':
					$save_value = in_array( 'phone', $views, true );
					break;
				case 'toolbarTablet':
					$save_value = in_array( 'tablet', $views, true );
					break;
				case 'toolbarWireframe':
					$save_value = in_array( 'wireframe', $views, true );
					break;
				case 'toolbarZoom':
					$save_value = in_array( 'zoom', $views, true );
					break;
				case 'hideDisabledModules':
					$vb_value   = et_()->array_get( $vb_preferences, $preference['location'], 'transparent' );
					$save_value = 'transparent' === $vb_value ? false : true;
					break;
				case 'pageCreationFlow':
					$vb_value   = et_()->array_get( $vb_preferences, $preference['location'], $preference['default'] );
					$save_value = strtolower( preg_replace( '/([A-Z])/', '_$1', $vb_value ) );
					break;
				default:
					$save_value = et_()->array_get( $vb_preferences, $preference['location'], $preference['default'] );
					$save_value = self::parse_value_type( $save_value, $preference['type'], $preference['default'] );
					break;
			}

			$clean_preferences[ $preference['key'] ] = $save_value;
		}

		return $clean_preferences;
	}

	/**
	 * Filter an inline style attribute and remove disallowed rules.
	 *
	 * @since ??
	 *
	 * @param string $css                           A string of CSS rules.
	 * @param bool   $allow_import                  Optional. Flag to allow import statement. Default `false`.
	 * @param bool   $strip_comments                Optional. Flag to strip CSS comments. Default `true`.
	 * @param bool   $allow_comments_in_properties  Optional. Flag to allow comments in CSS properties. Default `false`.
	 *
	 * @return string
	 */
	public static function sanitize_css( string $css, bool $allow_import = false, bool $strip_comments = true, bool $allow_comments_in_properties = false ): string {

		if ( $strip_comments ) {
			/**
			 * Regular expression to strip out the comment.
			 *
			 * Test Regex: https://regex101.com/r/lxsp02/1
			 */
			$regex = '/\/\*+([\S\s]+)\*\//U';

			if ( preg_match( $regex, $css ) ) {
				$css = preg_replace( $regex, '', $css );
			}
		}

		if ( ! $allow_import ) {
			/**
			 * Regular expression to strip out CSS import statement.
			 *
			 * Test Regex: https://regex101.com/r/3t8VsB/1
			 */
			$regex = '/@import([^;]+);/';

			if ( preg_match( $regex, $css ) ) {
				$css = preg_replace( $regex, '', $css );
			}
		}

		// Strip out HTML tags.
		$css = self::strip_html_tags( $css );

		/**
		 * Regular expression to strip out script tag with %-encoded octets.
		 *
		 * Test Regex: https://regex101.com/r/zA4dHq/2
		 */
		$regex = '/%[0-9A-Fa-f]{2}\/?script%[0-9A-Fa-f]{2}/m';

		// Strip out script tag with %-encoded octets.
		$css = preg_replace( $regex, '', $css );

		/**
		 * Regular expression to get CSS property and value within the curly braces.
		 *
		 * Test Regex: https://regex101.com/r/IYRkpK/1
		 */
		$regex = '/({\s*)([^{}]+)(\s*})/';

		if ( preg_match( $regex, $css ) ) {
			$css = preg_replace_callback(
				$regex,
				function ( $matches ) use ( $allow_comments_in_properties ) {
					return $matches[1] . self::sanitize_css_properties( $matches[2], $allow_comments_in_properties ) . $matches[3];
				},
				$css
			);
		}

		return $css;
	}

	/**
	 * Sanitize page settings custom CSS while preserving comments.
	 *
	 * @since ??
	 *
	 * @param string $css A string of CSS rules.
	 *
	 * @return string
	 */
	public static function sanitize_page_settings_css( string $css ): string {
		return self::sanitize_css( $css, false, false, true );
	}

	/**
	 * Sanitize JSON.
	 *
	 * Sanitizes a JSON string by applying custom sanitizers to specific keys and using
	 * `sanitize_text_field` for all other values.
	 *
	 * @since ??
	 *
	 * @param string $string             The JSON string to sanitize.
	 * @param array  $custom_sanitizer   Optional. An array of custom sanitizers to apply to specific keys.
	 *                                   Each key in the array should correspond to a key in the JSON
	 *                                   string, and each value should be a callable function that takes
	 *                                   the current value of the key and returns the sanitized value.
	 *                                   If a custom sanitizer function is not defined for a specific
	 *                                   key, `sanitize_text_field` will be used to sanitize the value.
	 *                Default `[]`.
	 *
	 * @return string                    The sanitized JSON string.
	 *
	 * @example
	 * ```php
	 * $string = '{"name": "<script>alert(1)</script>", "email": "test@example.com"}';
	 * $custom_sanitizer = [
	 *     'name' => function($value) {
	 *         return strip_tags($value);
	 *     },
	 *     'email' => function($value) {
	 *         return filter_var($value, FILTER_SANITIZE_EMAIL);
	 *     },
	 * ];
	 *
	 * $sanitized = SavingUtility::sanitize_json($string, $custom_sanitizer);
	 *
	 * echo $sanitized;
	 *
	 * // Output: `{"name": "alert(1)", "email": "test@example.com"}`
	 * ```
	 */
	public static function sanitize_json( string $string, array $custom_sanitizer = [] ): string {
		if ( ! self::is_json( $string ) ) {
				return '{}';
		}

		$json_decoded = json_decode( $string, true );

		array_walk_recursive(
			$json_decoded,
			function ( &$value, $key, $array_walk_sanitizer ) {
				if ( isset( $array_walk_sanitizer[ $key ] ) && is_callable( $array_walk_sanitizer[ $key ] ) ) {
					$value = call_user_func( $array_walk_sanitizer[ $key ], $value );
				} else {
					$value = sanitize_text_field( $value );
				}
			},
			$custom_sanitizer
		);

		return wp_json_encode( $json_decoded, JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Strips out any %-encoded octets from the given string.
	 *
	 * This function uses a regular expression pattern to find and remove any octets that are
	 * percent-encoded using the URL encoding scheme (`%XX`). It matches any percent sign followed by
	 * two hexadecimal digits ranging from 0 to 9 and from A to F (case-insensitive). For example,
	 * `"%3C"` will be replaced with an empty string, effectively removing it from the string.
	 *
	 * @since ??
	 *
	 * @param string $string The string to process.
	 *
	 * @return string The processed string with all the encoded octets removed.
	 *
	 * @example:
	 * ```php
	 * $input = '%3Cscript%3Ealert(1)%3C/script%3E';
	 * $output = SavingUtility::strip_encoded_octets( $input );
	 *
	 * // Result: "scriptalert(1)/script"
	 * ```
	 */
	public static function strip_encoded_octets( string $string ): string {
		/**
		 * Regular expression to strip out any %-encoded octets.
		 *
		 * Test Regex: https://regex101.com/r/dNjvJr/1
		 */
		$regex = '/%[a-fA-F0-9][a-fA-F0-9]/';

		if ( preg_match( $regex, $string ) ) {
			$string = preg_replace( $regex, '', $string );
		}

		return $string;
	}

	/**
	 * Strip HTML tags from a string.
	 *
	 * This function removes HTML tags from a given string.
	 * By default, it also removes the content between the tags.
	 *
	 * @since ??
	 *
	 * @param string $string        The input string to strip HTML tags from.
	 * @param bool   $strip_content Optional. Whether to also remove the content between the tags. Default `true`.
	 *
	 * @return string The input string with HTML tags removed.
	 *
	 * @example:
	 * ```php
	 * $string = '<p>Hello, <strong>world!</strong></p>';
	 * $stripped_string = MyNamespace\MyClass::strip_html_tags( $string );
	 * // Result: 'Hello, world!'
	 * ```
	 */
	public static function strip_html_tags( string $string, bool $strip_content = true ): string {
		/**
		 * Regular expression to get content between '<' and '>'.
		 *
		 * Test Regex: https://regex101.com/r/zjwXmr/1
		 */
		$regex = '/(<)(.*?)(>)/s';

		// Strip out newline and replace multiple spaces with a single space.
		if ( preg_match( $regex, $string ) ) {
			$string = preg_replace_callback(
				$regex,
				function ( $matches ) {
					// Strip out newline.
					$processed = self::strip_newline( $matches[2] );

					// Replacing multiple spaces with single space.
					$processed = preg_replace( '/\s+/', ' ', $processed );

					return '<' . trim( $processed ) . '>';
				},
				$string
			);
		}

		if ( $strip_content ) {
			/**
			 * Regular expression to strip out balanced HTML tags and it's content.
			 *
			 * Test Regex: https://regex101.com/r/cYzV2I/2
			 */
			$regex = '/<(\w+)\b.*?>.*?<\/\1>/';

			if ( preg_match( $regex, $string ) ) {
				$string = preg_replace( $regex, '', $string );
			}
		}

		/**
		 * Regular expression to strip out the HTML tags it self. Despite balanced or not.
		 *
		 * Test Regex: https://regex101.com/r/kpQ2qB/2
		 */
		$regex = '/<\/?\s*?(\w+)\b.*?>/';

		if ( preg_match( $regex, $string ) ) {
			$string = preg_replace( $regex, '', $string );
		}

		return $string;
	}

	/**
	 * Strip out newline characters from a string.
	 *
	 * This function uses regular expressions to remove newline characters,
	 * such as line breaks and carriage returns, from a given string.
	 * It is primarily used to sanitize input and ensure consistent formatting in text data.
	 *
	 * @since ??
	 *
	 * @param string $string The string to process and remove newline characters from.
	 *
	 * @return string The sanitized string with newline characters removed.
	 *
	 * @example:
	 * ```php
	 * $input = "This is a string with\nnew lines.";
	 * $output = strip_newline($input);
	 * // $output = "This is a string with new lines."
	 * ```
	 */
	public static function strip_newline( string $string ): string {
		/**
		 * Regular expression to strip out newline.
		 *
		 * Test Regex: https://regex101.com/r/YIAcbh/1
		 */
		$regex = '/[\n\r]/';

		if ( preg_match( $regex, $string ) ) {
			$string = preg_replace( $regex, '', $string );
		}

		return $string;
	}

	/**
	 * Serialize and sanitize an array of blocks into a string.
	 *
	 * This function takes an array of blocks and serializes each block individually using the 'serialize_sanitize_block' method.
	 * The individual serialized blocks are then concatenated into a single string.
	 *
	 * @since ??
	 *
	 * @param WP_Block_Parser_Block[] $blocks Array of blocks.
	 *
	 * @return string The serialized and sanitized blocks as an HTML  string.
	 *
	 * @example:
	 * ```php
	 *  $blocks = [
	 *      ['block_type' => 'heading', 'attributes' => ['level' => 1, 'content' => 'Hello']],
	 *      ['block_type' => 'paragraph', 'attributes' => ['content' => 'Lorem ipsum']],
	 *  ];
	 *  $serialized_blocks = self::serialize_sanitize_blocks( $blocks );
	 *
	 *  // Returns '<h1>Hello</h1><p>Lorem ipsum</p>'
	 * ```
	 */
	public static function serialize_sanitize_blocks( array $blocks ): string {
		return implode( '', array_map( [ __CLASS__, 'serialize_sanitize_block' ], $blocks ) );
	}

	/**
	 * Serialize and sanitize a block into a string.
	 *
	 * This function takes a block and serializes each block individually using the 'serialize_sanitize_block' method.
	 * The individual serialized blocks are then concatenated into a single string.
	 *
	 * @since ??
	 *
	 * @param array $block The block to be serialized.
	 *
	 * @return string The serialized and sanitized block as an HTML string.
	 *
	 * @example:
	 * ```php
	 *  $block = ['block_type' => 'paragraph', 'attributes' => ['content' => 'Lorem ipsum']];
	 *  $serialized_block = MapDeepTrait::serialize_sanitize_block( $block );
	 *
	 *  // Returns '<p>Lorem ipsum</p>'
	 * ```
	 */
	public static function serialize_sanitize_block( array $block ): string {
		$block_name    = $block['blockName'];
		$block_content = '';

		$index = 0;

		foreach ( $block['innerContent'] as $chunk ) {
			$block_content .= is_string( $chunk ) ? $chunk : self::serialize_sanitize_block( $block['innerBlocks'][ $index++ ] );
		}

		if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
			// At this point, the unicode characters within the attributes data has been decoded
			// into the actual character.
			// \u003c ==> <
			// \u003e ==> >
			// \u002d\u002d ==> --
			// \u0026 ==> &
			// \u0022 ==> "
			// Hence we can sanitize the attributes normally using the self::sanitize_block_attrs function.
			$block['attrs'] = self::sanitize_block_attrs( $block['attrs'], $block_name );
		}

		if ( ! isset( $block['attrs'] ) || ! is_array( $block['attrs'] ) ) {
			$block['attrs'] = [];
		}

		if ( ! empty( $block['attrs'] ) ) {
			$block['attrs'] = ModuleUtils::remove_empty_array_attributes( $block['attrs'] );
		}

		return get_comment_delimited_block_content(
			$block['blockName'],
			$block['attrs'],
			$block_content
		);
	}

	/**
	 * Sanitize the content by removing disallowed HTML tags and attributes.
	 *
	 * This function takes the provided content and removes any HTML tags and attributes
	 * that are not allowed based on the user's permissions. It retrieves the allowed tags
	 * for the post using the `wp_kses_allowed_html()` function and adds inline styles and
	 * scripts to the allowed tags if the user has the 'unfiltered_html' capability.
	 *
	 * @since ??
	 *
	 * @param string $content The content to be sanitized.
	 *
	 * @return string The sanitized content.
	 *
	 * @example:
	 * ```php
	 *   $content = '<p><script>alert("Hello World");</script></p>';
	 *   $sanitized_content = sanitize_content($content);
	 *
	 *   // The sanitized content will be '<p></p>'.
	 *
	 *   $content = '<p><a href="#" onclick="alert(\'Hello World\');">Click me</a></p>';
	 *   $sanitized_content = sanitize_content($content);
	 *
	 *   // The sanitized content will be: `<p><a href="#">Click me</a></p>`.
	 * ```
	 */
	public static function sanitize_content( string $content ): string {
		// Get allowed tags for post.
		$allowed_tags = wp_kses_allowed_html( 'post' );

		// Add inline styles and scripts to allowed tags.
		if ( current_user_can( 'unfiltered_html' ) ) {
			$allowed_tags['style']  = [
				'media'  => true,
				'scoped' => true,
				'type'   => true,
			];
			$allowed_tags['script'] = [
				'async'          => true,
				'crossorigin'    => true,
				'defer'          => true,
				'integrity'      => true,
				'nomodule'       => true,
				'referrerpolicy' => true,
				'src'            => true,
				'type'           => true,
			];
		}

		return wp_kses( $content, $allowed_tags );
	}

	/**
	 * Replace escaped quotes with regular quotes.
	 *
	 * This function to fix the escaped quotes in the serialized block when there are any attributes ended with a backslash.
	 * During the serialization process, the Gutenberg serializer will escape `\"` to `\\u0022`, this intended to bypass
	 * server stripslashes behavior which would unescape stringify's escaping of quotation mark. But this being a problem
	 * when the backslash is at the end of the string, resulting the serialized block to be invalid JSON.
	 *
	 * Example Case 1: {"foo":"Ba\"r"} will be escaped to {"foo":"Ba\\u0022r"}
	 * Example Case 2: {"foo":"Bar\"} will be escaped to {"foo":"Bar\\u0022}
	 *
	 * As we can see, the second example will result in invalid JSON, so we need to replace `\\u0022` with `\"` to fix it.
	 *
	 * @see https://github.com/WordPress/wordpress-develop/blob/2e60defa5d9bac1a3900f1ed5d92f56ce00f5d47/src/wp-includes/blocks.php#L917
	 *
	 * This function is equivalent of JS function replaceEscapedQuotes located in:
	 * visual-builder/packages/serialized-post/src/store/utils/replace-escaped-quotes/index.ts
	 *
	 * @since ??
	 *
	 * @param string $serialized The serialized block to replace escaped quotes.
	 *
	 * @return string The serialized block with escaped quotes replaced with regular quotes.
	 */
	public static function replace_escaped_quotes( string $serialized ): string {
		$replaced = preg_replace_callback(
			/**
			 * Test Regex: https://regex101.com/r/prLcHO/1.
			 */
			'/\{"[^}]+/',
			function ( $matches ) {
				// Replace `\\\\u0022` with `\\\\"` if it's at the end of the string.
				// Test Regex: https://regex101.com/r/LyJtxi/1.
				$replaced_end = preg_replace( '/\\\\u0022$/', '\\\\"', $matches[0] );

				return $replaced_end;
			},
			$serialized
		);

		return $replaced;
	}

	/**
	 * Decodes angle brackets entities in a sanitized value if necessary.
	 *
	 * This function will try to decode the angle brackets if it was encoded to entities by the wp_kses function. This is intended to be used
	 * for restoring the greater than character (>) that was encoded to &gt; by the wp_kses function. The wp_kses function
	 * encodes the greater than character to &gt; when it did not find a matching less than character (<).
	 *
	 * This method is used to decode angle brackets entities in a sanitized value if necessary.
	 * This is necessary because the wp_kses() function converts non paired angle brackets to entities,
	 * and we need to ensure that the original value is returned after sanitization.
	 *
	 * @see https://github.com/WordPress/WordPress/blob/e88758878ea45ccd663eb3a6c8ba70326b7edb76/wp-includes/kses.php#L747
	 *
	 * @since ??
	 *
	 * @param string $sanitized_value The sanitized value.
	 * @param string $original_value  The original value.
	 *
	 * @return string The decoded value if necessary, otherwise the sanitized value.
	 */
	public static function maybe_decode_angle_brackets_entities( string $sanitized_value, string $original_value ): string {
		$decoded = str_replace( [ '&lt;', '&gt;' ], [ '<', '>' ], $sanitized_value );

		if ( $decoded === $original_value ) {
			return $original_value;
		}

		return $sanitized_value;
	}

	/**
	 * Get the page settings mapping.
	 *
	 * This function returns the mapping of the page settings where the key is the page setting name in D5
	 * and the value is the page setting name in D4.
	 *
	 * @see https://github.com/elegantthemes/submodule-builder-5/blob/1832b0bc363d8a799475aad329ca1fdcf2c23526/visual-builder/packages/page-settings/src/store/index.ts
	 *
	 * @since ??
	 *
	 * @return array The mapping of the page settings.
	 */
	public static function get_page_settings_mapping(): array {
		$mapping = [
			'abBounceRateLimit'                 => [
				'd4_key' => 'et_pb_ab_bounce_rate_limit',
			],
			'abCurrentShortcode'                => [
				'd4_key' => 'et_pb_ab_current_shortcode',
			],
			'abStatsRefreshInterval'            => [
				'd4_key' => 'et_pb_ab_stats_refresh_interval',
			],
			'contentAreaBackgroundColor'        => [
				'd4_key'    => 'et_pb_content_area_background_color',
				'sanitizer' => 'et_sanitize_alpha_color',
			],
			'customCss'                         => [
				'd4_key'    => 'et_pb_custom_css',
				'sanitizer' => [ self::class, 'sanitize_page_settings_css' ],
			],
			'enableAbTesting'                   => [
				'd4_key' => 'et_pb_enable_ab_testing',
			],
			'enableShortcodeTracking'           => [
				'd4_key' => 'et_pb_enable_shortcode_tracking',
			],
			'overflowX'                         => [
				'd4_key' => 'et_pb_overflow-x',
			],
			'overflowY'                         => [
				'd4_key' => 'et_pb_overflow-y',
			],
			'pageGutterWidth'                   => [
				'd4_key' => 'et_pb_page_gutter_width',
			],
			'pageZIndex'                        => [
				'd4_key'    => 'et_pb_page_z_index',
				'sanitizer' => [ self::class, 'sanitize_page_z_index' ],
			],
			'postCategories'                    => [
				'd4_key' => 'et_pb_post_settings_categories',
			],
			'postExcerpt'                       => [
				'd4_key'    => 'et_pb_post_settings_excerpt',
				'sanitizer' => 'wp_kses_post',
			],
			'postImage'                         => [
				'd4_key' => 'et_pb_post_settings_image',
			],
			'postProjectCategories'             => [
				'd4_key' => 'et_pb_post_settings_project_categories',
			],
			'postProjectTags'                   => [
				'd4_key' => 'et_pb_post_settings_project_tags',
			],
			'postTags'                          => [
				'd4_key' => 'et_pb_post_settings_tags',
			],
			'postTitle'                         => [
				'd4_key' => 'et_pb_post_settings_title',
			],
			'sectionBackgroundColor'            => [
				'd4_key'    => 'et_pb_section_background_color',
				'sanitizer' => 'et_sanitize_alpha_color',
			],
			'wooCommerceProductLongDescription' => [
				'd4_key' => '_et_pb_old_content',
			],
		];

		return apply_filters(
			'divi_visual_builder_saving_page_settings_mapping',
			$mapping
		);
	}

	/**
	 * Map the page settings.
	 *
	 * This function maps the page settings from D5 to D4.
	 *
	 * @since ??
	 *
	 * @param array $settings The page settings to map.
	 *
	 * @return array The mapped page settings.
	 */
	public static function map_page_settings( array $settings ): array {
		$mapping = self::get_page_settings_mapping();
		$keys    = array_keys( $mapping );
		$mapped  = [];

		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				continue;
			}

			$d4_key = $mapping[ $key ]['d4_key'] ?? '';

			if ( ! $d4_key ) {
				continue;
			}

			$mapped[ $d4_key ] = $settings[ $key ];
		}

		return $mapped;
	}

	/**
	 * Sanitize the page settings array by preparing each content item for database storage.
	 *
	 * @since ??
	 *
	 * @param array $page_settings The array of page settings to be sanitized.
	 *
	 * @return array The sanitized page settings array
	 */
	public static function sanitize_page_settings( array $page_settings ): array {
		$sanitized = [];

		$mappings = self::get_page_settings_mapping();

		foreach ( $mappings as $key => $data ) {
			if ( ! array_key_exists( $key, $page_settings ) ) {
				continue;
			}

			$sanitizer = $data['sanitizer'] ?? null;

			if ( ! $sanitizer || ! is_callable( $sanitizer ) ) {
				$sanitizer = 'sanitize_text_field';
			}

			$sanitized[ $key ] = call_user_func( $sanitizer, $page_settings[ $key ] );
		}

		// Contract-only key from #47743 — not in D4 mapping; must reach save_page_settings().
		if ( array_key_exists( 'pageGutterWidthIsDefault', $page_settings ) ) {
			$sanitized['pageGutterWidthIsDefault'] = self::sanitize_page_gutter_width_is_default(
				$page_settings['pageGutterWidthIsDefault']
			);
		}

		return $sanitized;
	}

	/**
	 * Sanitize the page gutter width inherited/explicit contract flag.
	 *
	 * Visual Builder sends `'0'` / `'1'` strings; normalize to the same for save_page_settings().
	 *
	 * @since ??
	 *
	 * @param mixed $value Raw contract flag from REST or direct save.
	 *
	 * @return string `'1'` when inherited/default, `'0'` when explicit override.
	 */
	public static function sanitize_page_gutter_width_is_default( $value ): string {
		return true === filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ? '1' : '0';
	}

	/**
	 * Sanitize page z-index. Treat `"auto"` as unset so theme stacking applies (#51130 / #39564).
	 *
	 * @since ??
	 *
	 * @param mixed $value Raw page z-index from REST or direct save.
	 *
	 * @return string Sanitized z-index; empty string when unset or `"auto"`.
	 */
	public static function sanitize_page_z_index( $value ): string {
		$value = sanitize_text_field( $value );

		if ( 'auto' === $value ) {
			return '';
		}

		return $value;
	}

	/**
	 * Save the page settings.
	 *
	 * This function saves the page settings to the database.
	 *
	 * @since ??
	 *
	 * @param array $settings The page settings to save.
	 * @param int   $post_id  The post ID to save the settings for.
	 *
	 * @return void
	 */
	public static function save_page_settings( array $settings, $post_id ) {
		$has_page_gutter_width_is_default = array_key_exists( 'pageGutterWidthIsDefault', $settings );
		$page_gutter_width_is_default     = $has_page_gutter_width_is_default
			&& true === filter_var( $settings['pageGutterWidthIsDefault'], FILTER_VALIDATE_BOOLEAN );

		$mapped_page_settings = self::map_page_settings( $settings );
		if ( $has_page_gutter_width_is_default && $page_gutter_width_is_default ) {
			unset( $mapped_page_settings['et_pb_page_gutter_width'] );
		}

		// `"auto"` means no override (VB default); persist empty so meta is deleted like D4 unset.
		if (
			isset( $mapped_page_settings['et_pb_page_z_index'] )
			&& 'auto' === $mapped_page_settings['et_pb_page_z_index']
		) {
			$mapped_page_settings['et_pb_page_z_index'] = '';
		}

		// Exclude postTitle and postExcerpt from et_builder_update_settings() because they were.
		// already saved in the main wp_update_post() call in SyncToServerController::update().
		// This prevents a second wp_update_post() call which would create duplicate revisions.
		// and trigger expensive filter processing twice.
		$settings_for_et_builder = $mapped_page_settings;
		unset( $settings_for_et_builder['et_pb_post_settings_title'] );
		unset( $settings_for_et_builder['et_pb_post_settings_excerpt'] );

		// Exclude et_pb_custom_css from et_builder_update_settings() because that path runs.
		// sanitize_textarea_field(), which strips percent-encoded sequences (%XX) and breaks.
		// data URLs in CSS (e.g. SVG in url("data:image/svg+xml,...")). Values are already.
		// sanitized via sanitize_page_settings_css() before this method runs.
		$should_persist_custom_css_directly = false;
		$custom_css_value                   = '';

		if ( array_key_exists( 'et_pb_custom_css', $mapped_page_settings ) ) {
			$should_persist_custom_css_directly = true;
			$custom_css_value                   = $mapped_page_settings['et_pb_custom_css'];
			unset( $settings_for_et_builder['et_pb_custom_css'] );
		}

		et_builder_update_settings( $settings_for_et_builder, $post_id );

		if ( true === $should_persist_custom_css_directly ) {
			update_post_meta( $post_id, '_et_pb_custom_css', $custom_css_value );
			delete_post_meta( $post_id, '_et_pb_custom_css_draft' );
		}

		if ( $has_page_gutter_width_is_default ) {
			if ( $page_gutter_width_is_default ) {
				delete_post_meta( $post_id, '_et_pb_gutter_width' );
				update_post_meta( $post_id, '_et_pb_page_gutter_width_is_default', '1' );
			} else {
				update_post_meta( $post_id, '_et_pb_page_gutter_width_is_default', '0' );
			}
		}

		// Keep legacy post settings meta in sync without triggering additional wp_update_post calls.
		// Some integrations still read these explicit meta values directly.
		if ( array_key_exists( 'et_pb_post_settings_title', $mapped_page_settings ) ) {
			update_post_meta( $post_id, '_et_pb_post_settings_title', $mapped_page_settings['et_pb_post_settings_title'] );
		}

		if ( array_key_exists( 'et_pb_post_settings_excerpt', $mapped_page_settings ) ) {
			update_post_meta( $post_id, '_et_pb_post_settings_excerpt', $mapped_page_settings['et_pb_post_settings_excerpt'] );
		}

		// Save WooCommerce Product Long Description.
		if ( 'product' === get_post_type( $post_id ) ) {
			if ( isset( $mapped_page_settings['_et_pb_old_content'] ) ) {
				$our_value   = $mapped_page_settings['_et_pb_old_content'];
				$final_check = get_post_meta( $post_id, '_et_pb_old_content', true );

				if ( $final_check !== $our_value ) {
					update_post_meta( $post_id, '_et_pb_old_content', wp_kses_post( $our_value ) );
				}
			}
		}
	}

	/**
	 * Save non-singular page settings to their respective WordPress objects.
	 *
	 * The `$settings_data` parameter is the `mainLoopSettingsData` object sent
	 * from the frontend. It contains both the identifiers (termId, taxonomy,
	 * authorId) and the user-editable values (termName, termSlug, etc.) that
	 * the frontend syncs back into the same object on field change.
	 *
	 * For taxonomy archives, updates the term via `wp_update_term()`.
	 * For author archives, updates the user via `wp_update_user()`.
	 * Info-only main loop types (date, post_type_archive, search, 404) have no
	 * editable properties and are silently skipped.
	 *
	 * @since ??
	 *
	 * @param array  $settings_data     The mainLoopSettingsData from the frontend request.
	 * @param string $main_loop_type    Non-singular main loop type identifier.
	 * @param array  $expected_context  Server-resolved expected owner context.
	 *
	 * @return void
	 */
	public static function save_non_singular_page_settings( array $settings_data, string $main_loop_type, array $expected_context = [] ): void {
		switch ( $main_loop_type ) {
			case 'category':
			case 'tag':
			case 'taxonomy':
				$term_id           = absint( $settings_data['termId'] ?? 0 );
				$taxonomy          = sanitize_key( $settings_data['taxonomy'] ?? '' );
				$expected_term_id  = absint( $expected_context['termId'] ?? 0 );
				$expected_taxonomy = sanitize_key( $expected_context['taxonomy'] ?? '' );

				if ( 0 === $term_id || empty( $taxonomy ) ) {
					break;
				}

				// Only allow edits to the exact term/taxonomy currently being edited.
				if ( 0 === $expected_term_id || '' === $expected_taxonomy ) {
					break;
				}

				if ( $term_id !== $expected_term_id || $taxonomy !== $expected_taxonomy ) {
					break;
				}

				if ( ! current_user_can( 'edit_term', $term_id ) ) {
					break;
				}

				$term_update = [];

				if ( isset( $settings_data['termName'] ) ) {
					$term_update['name'] = sanitize_text_field( $settings_data['termName'] );
				}

				if ( isset( $settings_data['termSlug'] ) ) {
					$term_update['slug'] = sanitize_title( $settings_data['termSlug'] );
				}

				if ( isset( $settings_data['termDescription'] ) ) {
					$term_update['description'] = wp_kses_post( $settings_data['termDescription'] );
				}

				if ( ! empty( $term_update ) ) {
					wp_update_term( $term_id, $taxonomy, $term_update );
				}
				break;
		}
	}

	/**
	 * Sanitizes the block attributes recursively.
	 *
	 * @since ??
	 *
	 * @param array  $attrs      The array of block attributes to be sanitized.
	 * @param string $block_name The name of the block being sanitized.
	 *
	 * @return array The sanitized block attributes.
	 */
	public static function sanitize_block_attrs( array $attrs, ?string $block_name ): array {
		$current_user_can_unfiltered_html = current_user_can( 'unfiltered_html' );

		return (array) ArrayUtility::map_deep(
			$attrs,
			function ( $value, array $path ) use ( $block_name, $current_user_can_unfiltered_html ) {
				/**
				 * Filter to sanitize the block attributes.
				 *
				 * @since ??
				 *
				 * @param null  $null  The initial value to be sanitized. It should be returned as null if the value is not to be sanitized.
				 * @param array $args {
				 *   An array of arguments.
				 *
				 *   @type string $value The value to be sanitized.
				 *   @type array  $path  Array of keys to represent the path to the current value in the original array.
				 *   @type string $moduleName The block name being sanitized.
				 * }
				 */
				$pre_sanitized = apply_filters(
					'divi_sanitize_block_attrs_pre',
					null,
					[
						'value'      => $value,
						'path'       => $path,
						'moduleName' => $block_name,
					]
				);

				if ( null !== $pre_sanitized ) {
					return $pre_sanitized;
				}

				/**
				 * Filter to skip sanitizing the block attributes.
				 *
				 * This done to add flexibility for developers to skip sanitizing certain attributes within certain modules. By default,
				 * it will skip sanitizing the attribute if the current user has the 'unfiltered_html' capability.
				 *
				 * Discussion & ADR: https://elegantthemes.slack.com/archives/C01CW343ZJ9/p1640351289407000.
				 *
				 * @since ??
				 *
				 * @param bool  $skip_sanitize Whether to skip sanitizing the attribute.
				 * @param array $args {
				 *   An array of arguments.
				 *
				 *   @type string $value The value to be sanitized.
				 *   @type array  $path  Array of keys to represent the path to the current value in the original array.
				 *   @type string $moduleName The block name being sanitized.
				 * }
				 */
				$skip_sanitize = apply_filters(
					'divi_sanitize_block_attrs_skip',
					$current_user_can_unfiltered_html,
					[
						'value'      => $value,
						'path'       => $path,
						'moduleName' => $block_name,
					]
				);

				if ( $skip_sanitize || ! $value ) {
					return $value;
				}

				if ( null === $value || ! is_string( $value ) ) {
					return $value;
				}

				// If the value does not contain any HTML tags, we can return it as is.
				// This is intended to prevent special characters from being unexpectedly encoded as HTML entities
				// during the wp_kses_post() sanitization process.
				if ( ! HTMLUtility::contains_html_tags( $value ) ) {
					return $value;
				}

				$value_for_sanitization = $value;

				// For Divi module attrs, remove script/style blocks completely before KSES.
				// This prevents script bodies (for example `document.body...`) from leaking as plain text.
				if ( ! empty( $block_name ) && 0 === strpos( $block_name, 'divi/' ) ) {
					$value_for_sanitization = self::strip_script_style_blocks( $value_for_sanitization );
				}

				$sanitized = wp_kses_post( $value_for_sanitization );

				return SavingUtility::maybe_decode_angle_brackets_entities( $sanitized, $value );
			}
		);
	}

	/**
	 * Strip script/style blocks (including inner text) from HTML-like strings.
	 *
	 * @since ??
	 *
	 * @param string $value Value to be cleaned.
	 *
	 * @return string
	 */
	private static function strip_script_style_blocks( string $value ): string {
		if ( false === stripos( $value, '<script' ) && false === stripos( $value, '<style' ) ) {
			return $value;
		}

		$without_blocks = preg_replace( '/<(script|style)\b[^>]*>.*?<\/\1\s*>/is', '', $value );

		// Also remove malformed/self-closing script/style tags.
		return (string) preg_replace( '/<(script|style)\b[^>]*\/>/is', '', (string) $without_blocks );
	}

	/**
	 * Check if content is already escaped with slashes.
	 *
	 * This method determines if the given content has already been escaped by WordPress's
	 * wp_slash() function. It does this by unslashing the content and then re-slashing it,
	 * comparing the result to the original. If the re-slashed version matches the original
	 * and differs from the unslashed version, the content is considered already escaped.
	 *
	 * @since ??
	 *
	 * @param string $content The content to check for escaping.
	 *
	 * @return bool Returns `true` if the content is already escaped, `false` otherwise.
	 */
	public static function is_content_wp_slashed( string $content ): bool {
		$unslashed  = wp_unslash( $content );
		$re_slashed = wp_slash( $unslashed );

		// If re-slashing the unslashed version gives the same string,
		// and the unslashed version is different, we treat it as "already slashed".
		return $content === $re_slashed && $content !== $unslashed;
	}

	/**
	 * Conditionally add slashes to content if not already escaped.
	 *
	 * This method checks if content already contains escaped double quotes,
	 * and if not, applies WordPress's wp_slash() function to escape the content.
	 * This prevents double-escaping values that have already been processed,
	 * which is particularly important for plugin compatibility scenarios where
	 * content may be escaped multiple times during duplication or import operations.
	 *
	 * @since ??
	 *
	 * @param string $content The content to potentially escape.
	 *
	 * @return string The escaped content, or the original if already escaped.
	 */
	public static function maybe_add_slash( string $content ): string {
		// If the content is already wp_slashed, return it as is.
		if ( self::is_content_wp_slashed( $content ) ) {
			return $content;
		}

		// Otherwise, escape using wp_slash.
		return wp_slash( $content );
	}

	/**
	 * Sanitizes the group attributes recursively.
	 *
	 * @since ??
	 *
	 * @param array  $attrs      The array of group attributes to be sanitized.
	 * @param string $group_name The name of the group being sanitized.
	 *
	 * @return array The sanitized group attributes.
	 */
	public static function sanitize_group_attrs( array $attrs, ?string $group_name ): array {
		$current_user_can_unfiltered_html = current_user_can( 'unfiltered_html' );

		return (array) ArrayUtility::map_deep(
			$attrs,
			function ( $value, array $path ) use ( $group_name, $current_user_can_unfiltered_html ) {
				/**
				 * Filter to sanitize the group attributes.
				 *
				 * @since ??
				 *
				 * @param null  $null  The initial value to be sanitized. It should be returned as null if the value is not to be sanitized.
				 * @param array $args {
				 *   An array of arguments.
				 *
				 *   @type string $value The value to be sanitized.
				 *   @type array  $path  Array of keys to represent the path to the current value in the original array.
				 *   @type string $groupName The group name being sanitized.
				 * }
				 */
				$pre_sanitized = apply_filters(
					'divi_sanitize_group_attrs_pre',
					null,
					[
						'value'     => $value,
						'path'      => $path,
						'groupName' => $group_name,
					]
				);

				if ( null !== $pre_sanitized ) {
					return $pre_sanitized;
				}

				/**
				 * Filter to skip sanitizing the group attributes.
				 *
				 * This done to add flexibility for developers to skip sanitizing certain attributes within certain modules. By default,
				 * it will skip sanitizing the attribute if the current user has the 'unfiltered_html' capability.
				 *
				 * Discussion & ADR: https://elegantthemes.slack.com/archives/C01CW343ZJ9/p1640351289407000.
				 *
				 * @since ??
				 *
				 * @param bool  $skip_sanitize Whether to skip sanitizing the attribute.
				 * @param array $args {
				 *   An array of arguments.
				 *
				 *   @type string $value The value to be sanitized.
				 *   @type array  $path  Array of keys to represent the path to the current value in the original array.
				 *   @type string $groupName The group name being sanitized.
				 * }
				 */
				$skip_sanitize = apply_filters(
					'divi_sanitize_group_attrs_skip',
					$current_user_can_unfiltered_html,
					[
						'value'     => $value,
						'path'      => $path,
						'groupName' => $group_name,
					]
				);

				if ( $skip_sanitize ) {
					return $value;
				}

				if ( null === $value || ! is_string( $value ) ) {
					return $value;
				}

				// If the value does not contain any HTML tags, we can return it as is.
				// This is intended to prevent special characters from being unexpectedly encoded as HTML entities
				// during the wp_kses_post() sanitization process.
				if ( ! HTMLUtility::contains_html_tags( $value ) ) {
					return $value;
				}

				$sanitized = wp_kses_post( $value );

				return SavingUtility::maybe_decode_angle_brackets_entities( $sanitized, $value );
			}
		);
	}
}
