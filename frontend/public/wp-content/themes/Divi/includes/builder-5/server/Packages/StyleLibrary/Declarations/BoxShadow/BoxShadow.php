<?php
/**
 * BoxShadow class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\StyleLibrary\Declarations\BoxShadow;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;

/**
 * BoxShadow class with helper methods for working with BoxShadow style declaration.
 *
 * @since ??
 */
class BoxShadow {

	/**
	 * Presets
	 *
	 * @since ??
	 *
	 * @var array
	 */
	protected static $_presets = [
		'preset1' => [
			'horizontal' => '0px',
			'vertical'   => '2px',
			'blur'       => '18px',
			'spread'     => '0px',
			'position'   => 'outer',
			'color'      => 'rgba(0,0,0,0.3)',
		],
		'preset2' => [
			'horizontal' => '6px',
			'vertical'   => '6px',
			'blur'       => '18px',
			'spread'     => '0px',
			'position'   => 'outer',
			'color'      => 'rgba(0,0,0,0.3)',
		],
		'preset3' => [
			'horizontal' => '0px',
			'vertical'   => '12px',
			'blur'       => '18px',
			'spread'     => '-6px',
			'position'   => 'outer',
			'color'      => 'rgba(0,0,0,0.3)',
		],
		'preset4' => [
			'horizontal' => '10px',
			'vertical'   => '10px',
			'blur'       => '0px',
			'spread'     => '0px',
			'position'   => 'outer',
			'color'      => 'rgba(0,0,0,0.3)',
		],
		'preset5' => [
			'horizontal' => '0px',
			'vertical'   => '6px',
			'blur'       => '0px',
			'spread'     => '10px',
			'position'   => 'outer',
			'color'      => 'rgba(0,0,0,0.3)',
		],
		'preset6' => [
			'horizontal' => '0px',
			'vertical'   => '0px',
			'blur'       => '18px',
			'spread'     => '0px',
			'position'   => 'inner',
			'color'      => 'rgba(0,0,0,0.3)',
		],
		'preset7' => [
			'horizontal' => '10px',
			'vertical'   => '10px',
			'blur'       => '0px',
			'spread'     => '0px',
			'position'   => 'inner',
			'color'      => 'rgba(0,0,0,0.3)',
		],
	];

	/**
	 * Get the box shadow presets data
	 *
	 * @since ??
	 *
	 * @return array The array of presets.
	 */
	public static function presets(): array {
		return self::$_presets;
	}

	/**
	 * Get Box Shadow's CSS property value based on given attrValue.
	 *
	 * @since ??
	 *
	 * @param array $attr_value       The value (breakpoint > state > value) of module attribute.
	 * @param array $default_attr_value Optional default attribute value (from preset or defaultPrintedStyle).
	 *
	 * @return string
	 */
	public static function value( array $attr_value, array $default_attr_value = [] ): string {
		// Check if style key exists to distinguish between missing style and explicit 'none'.
		$style_key_exists = array_key_exists( 'style', $attr_value );
		$style            = $attr_value['style'] ?? 'none';

		// If style is empty or 'none'.
		if ( ! $style || 'none' === $style ) {
			// If style key doesn't exist, treat as missing style - use preset value (return empty string to let preset CSS apply).
			if ( ! $style_key_exists ) {
				return '';
			}

			// Style key exists and is 'none' - this is an explicit override.
			// If defaultAttrValue has a style (preset is active), return 'none' to override preset CSS.
			if ( ! empty( $default_attr_value['style'] ) && 'none' !== $default_attr_value['style'] ) {
				return 'none';
			}
			return '';
		}

		$preset = isset( self::$_presets[ $style ] ) ? self::$_presets[ $style ] : [];

		// Load value on top of preset values; this ensure box-shadow to be properly rendered even there's
		// no selected value (fallback to preset value).
		$box_shadow      = array_merge(
			$preset,
			array_filter( $attr_value, 'strlen' )
		);
		$position        = isset( $box_shadow['position'] ) ? $box_shadow['position'] : '';
		$horizontal      = isset( $box_shadow['horizontal'] ) ? $box_shadow['horizontal'] : '';
		$vertical        = isset( $box_shadow['vertical'] ) ? $box_shadow['vertical'] : '';
		$blur            = isset( $box_shadow['blur'] ) ? $box_shadow['blur'] : '';
		$spread          = isset( $box_shadow['spread'] ) ? $box_shadow['spread'] : '';
		$color           = isset( $box_shadow['color'] ) ? $box_shadow['color'] : '';
		$shadow_position = 'inner' === $position ? 'inset ' : '';
		$shadow_spread   = $spread ? ' ' . $spread : '';
		$shadow_color    = $color ? ' ' . $color : '';

		return $shadow_position . $horizontal . ' ' . $vertical . ' ' . $blur . $shadow_spread . $shadow_color;
	}

	/**
	 * Get Box Shadow's CSS declaration based on given attrValue.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/style-library/box-shadow-declaration boxShadowDeclaration} in:
	 * `@divi/style-library` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array      $attrValue       The value (breakpoint > state > value) of module attribute.
	 *     @type array      $defaultAttrValue Optional default attribute value (from preset or defaultPrintedStyle).
	 *     @type bool|array $important       Whether to add `!important` tag.
	 *     @type string     $returnType      This is the type of value that the function will return.
	 *                                       Can be either `string` or `key_value_pair`.
	 * }
	 *
	 * @return array|string
	 */
	public static function style_declaration( array $args ) {
		$attr_value         = $args['attrValue'] ?? [];
		$default_attr_value = $args['defaultAttrValue'] ?? [];
		$important          = $args['important'];
		$return_type        = $args['returnType'];

		$style_declarations = new StyleDeclarations(
			[
				'important'  => $important,
				'returnType' => $return_type,
			]
		);

		// Bail early if attr_value is not an array (can happen with malformed data).
		if ( ! is_array( $attr_value ) ) {
			return $style_declarations->value();
		}

		$processed_value = self::value( $attr_value, is_array( $default_attr_value ) ? $default_attr_value : [] );

		if ( $processed_value ) {
			$style_declarations->add( 'box-shadow', $processed_value );
		}

		return $style_declarations->value();
	}
}
