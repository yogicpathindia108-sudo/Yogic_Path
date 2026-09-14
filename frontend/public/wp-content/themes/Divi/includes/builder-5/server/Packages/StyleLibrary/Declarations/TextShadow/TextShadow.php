<?php
/**
 * TextShadow class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\StyleLibrary\Declarations\TextShadow;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;

/**
 * TextShadow class.
 *
 * This class provides functionality to work with with text shadow styles.
 *
 * @since ??
 */
class TextShadow {

	/**
	 * Presets for text shadow effect.
	 *
	 * This trait provides preset values for text shadow effect.
	 * The presets array contains multiple presets, each represented as an associative array with the following keys:
	 * - horizontal: The horizontal offset of the effect in `em` units.
	 * - vertical: The vertical offset of the effect in `em` units.
	 * - blur: The blur radius of the effect in `em` units.
	 * - color: The color of the effect in `rgba` format.
	 *
	 * @since ??
	 *
	 * @var array $_presets The array of presets for the effect.
	 */
	protected static $_presets = [
		'preset1' => [
			'horizontal' => '0em',
			'vertical'   => '0.1em',
			'blur'       => '0.1em',
			'color'      => 'rgba(0,0,0,0.4)',
		],
		'preset2' => [
			'horizontal' => '0.08em',
			'vertical'   => '0.08em',
			'blur'       => '0.08em',
			'color'      => 'rgba(0,0,0,0.4)',
		],
		'preset3' => [
			'horizontal' => '0em',
			'vertical'   => '0em',
			'blur'       => '0.3em',
			'color'      => 'rgba(0,0,0,0.4)',
		],
		'preset4' => [
			'horizontal' => '0em',
			'vertical'   => '0.08em',
			'blur'       => '0em',
			'color'      => 'rgba(0,0,0,0.4)',
		],
		'preset5' => [
			'horizontal' => '0.08em',
			'vertical'   => '0.08em',
			'blur'       => '0em',
			'color'      => 'rgba(0,0,0,0.4)',
		],
	];

	/**
	 * Get Text Shadow CSS property value based on given attributes.
	 *
	 * This function retrieves the CSS property value for Text Shadow based on a given attribute value.
	 * Note: if no color is given, CSS' text-shadow will use element's `color` as text-shadow's color.
	 *
	 * @since ??
	 *
	 * @param array  $attr_value {
	 *     The value (breakpoint > state > value) of the module attribute.
	 *
	 *     @type string $style      Optional. The style of the Text Shadow. Default `none`.
	 *     @type string $horizontal Optional. The horizontal offset of the Text Shadow.
	 *     @type string $vertical   Optional. The vertical offset of the Text Shadow.
	 *     @type string $blur       Optional. The blur radius of the Text Shadow.
	 *     @type string $color      Optional. The color of the Text Shadow.
	 * }
	 * @param string $breakpoint Optional. The breakpoint name (desktop, tablet, phone). Default empty.
	 *
	 * @return string The computed Text Shadow CSS property value.
	 *
	 * @example:
	 * ```php
	 * TextShadow::value( [
	 *     'style' => 'solid',
	 *     'horizontal' => '2px',
	 *     'vertical' => '2px',
	 *     'blur' => '5px',
	 *     'color' => '#000000',
	 * ], 'desktop' );
	 * ```
	 */
	public static function value( array $attr_value, string $breakpoint = '' ): string {
		// Get selected preset.
		$style  = $attr_value['style'] ?? null;
		$preset = isset( $style ) && isset( self::$_presets[ $style ] ) ? self::$_presets[ $style ] : [];

		// Handle explicit "none" style first - return empty string regardless of breakpoint or dimensions.
		if ( 'none' === $style ) {
			return '';
		}

		// Check for responsive breakpoints with explicit dimensions.
		// This ensures that explicit zero values on responsive breakpoints override desktop shadow.
		$is_responsive_breakpoint = ! empty( $breakpoint ) && 'desktop' !== $breakpoint;
		$horizontal_exists        = isset( $attr_value['horizontal'] ) && null !== $attr_value['horizontal'];
		$vertical_exists          = isset( $attr_value['vertical'] ) && null !== $attr_value['vertical'];
		$blur_exists              = isset( $attr_value['blur'] ) && null !== $attr_value['blur'];

		if ( $is_responsive_breakpoint && ( $horizontal_exists || $vertical_exists || $blur_exists ) ) {
			// On responsive breakpoints, if dimensions are explicitly set (even zeros),
			// generate CSS to override desktop shadow.
			// Merge preset with attr_value first to ensure preset color is included when preset is selected.
			$text_shadow  = array_merge( $preset, $attr_value );
			$horizontal   = $text_shadow['horizontal'] ?? '0em';
			$vertical     = $text_shadow['vertical'] ?? '0em';
			$blur         = $text_shadow['blur'] ?? '0em';
			$color        = $text_shadow['color'] ?? '';
			$shadow_color = $color ? ' ' . $color : '';

			return $horizontal . ' ' . $vertical . ' ' . $blur . $shadow_color;
		}

		// If no style or preset, don't generate CSS.
		if ( ! $style || empty( $preset ) ) {
			return '';
		}

		// Load value on top of preset values; this ensure text-shadow to be properly rendered even there's
		// no selected value (fallback to preset value).
		$text_shadow = array_merge( $preset, $attr_value );
		$horizontal  = isset( $text_shadow['horizontal'] ) ? $text_shadow['horizontal'] : '';
		$vertical    = isset( $text_shadow['vertical'] ) ? $text_shadow['vertical'] : '';
		$blur        = isset( $text_shadow['blur'] ) ? $text_shadow['blur'] : '';
		$color       = isset( $text_shadow['color'] ) ? $text_shadow['color'] : '';

		// Check if all values are effectively zero/transparent on responsive breakpoint.
		// If so, generate CSS with zeros to override desktop shadow.
		if ( $is_responsive_breakpoint ) {
			// Check if the final merged values have all dimensions set to zero.
			// This handles the case where user overrides preset values to zeros.
			// Note: We check dimensions only, not color transparency, because setting all dimensions to zero
			// should override desktop shadow even if color isn't transparent.
			$is_zero_horizontal = empty( $horizontal ) || '0' === $horizontal || '0em' === $horizontal || '0px' === $horizontal;
			$is_zero_vertical   = empty( $vertical ) || '0' === $vertical || '0em' === $vertical || '0px' === $vertical;
			$is_zero_blur       = empty( $blur ) || '0' === $blur || '0em' === $blur || '0px' === $blur;

			if ( $is_zero_horizontal && $is_zero_vertical && $is_zero_blur ) {
				// All dimensions are zero on responsive breakpoint: generate CSS with zeros to override desktop.
				// Use the color from merged values (even if not transparent) to maintain consistency.
				$shadow_color = $color ? ' ' . $color : '';
				return ( $horizontal ? $horizontal : '0em' ) . ' ' . ( $vertical ? $vertical : '0em' ) . ' ' . ( $blur ? $blur : '0em' ) . $shadow_color;
			}
		}

		$shadow_color = $color ? ' ' . $color : '';

		return $horizontal . ' ' . $vertical . ' ' . $blur . $shadow_color;
	}

	/**
	 * Get text-shadow CSS declaration based on given arguments.
	 *
	 * This function accepts an array of arguments that define the style declaration.
	 * It parses the arguments, sets default values, and generates a CSS style declaration based on the provided arguments.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/style-library/text-shadow-style-declaration/ textShadowStyleDeclaration}
	 * located in `@divi/style-library` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments that define the style declaration.
	 *
	 *     @type string      $attrValue   The value (breakpoint > state > value) of module attribute.
	 *     @type array|bool  $important   Whether to add `!important` to the CSS.
	 *     @type string      $returnType  Optional. The return type of the style declaration. Default `string`.
	 *                                    One of `string`, or `key_value_pair`
	 *                                      - If `string`, the style declaration will be returned as a string.
	 *                                      - If `key_value_pair`, the style declaration will be returned as an array of key-value pairs.
	 *     @type string      $breakpoint  Optional. The breakpoint name (desktop, tablet, phone). Default empty.
	 *     @type string      $state       Optional. The state name (default, hover, sticky). Default empty.
	 * }
	 *
	 * @return array|string The generated text-shadow CSS style declaration.
	 *
	 * @example:
	 * ```php
	 * $args = [
	 *     'attrValue' => '#ff0000',
	 *     'important' => true,
	 *     'returnType' => 'string',
	 * ];
	 * $declaration = TextShadow::style_declaration( $args );
	 *
	 * // Result: "text-shadow: #ff0000 !important;"
	 * ```
	 */
	public static function style_declaration( array $args ) {
		$attr_value  = $args['attrValue'] ?? [];
		$important   = $args['important'];
		$return_type = $args['returnType'] ?? 'string';
		$breakpoint  = $args['breakpoint'] ?? '';

		$style_declarations = new StyleDeclarations(
			[
				'important'  => $important,
				'returnType' => $return_type,
			]
		);

		$processed_value = self::value( $attr_value, $breakpoint );

		if ( $processed_value ) {
			$style_declarations->add( 'text-shadow', $processed_value );
		}

		return $style_declarations->value();
	}
}
