<?php
/**
 * Text class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\StyleLibrary\Declarations\Text;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;

/**
 * Text class.
 *
 * This class provides functionality to work with with text styles.
 *
 * @since ??
 */
class Text {

	/**
	 * Convert legacy Divi orientation values to CSS-compatible values.
	 *
	 * Handles legacy values from:
	 * - Divi 4/shortcode modules: 'force_left' (with underscore).
	 * - Legacy Divi 5 content: 'forceLeft'/'forceRight' (camelCase, removed from UI).
	 *
	 * These are converted to standard CSS values 'left'/'right'.
	 *
	 * @since ??
	 *
	 * @param string|null $orientation The orientation value to convert.
	 * @return string|null The CSS-compatible orientation value.
	 */
	private static function _normalize_orientation_for_css( ?string $orientation ): ?string {
		if ( null === $orientation || '' === $orientation ) {
			return null;
		}

		switch ( $orientation ) {
			case 'force_left':
			case 'forceLeft':
				return 'left';
			case 'force_right':
			case 'forceRight':
				return 'right';
			case 'left':
			case 'center':
			case 'right':
			case 'justify':
				return $orientation;
			default:
				return null;
		}
	}

	/**
	 * Get base (desktop) orientation from full text attribute tree.
	 *
	 * @since ??
	 *
	 * @param array $attr Full text attributes.
	 * @return string|null The base orientation value.
	 */
	private static function _get_base_orientation( array $attr ): ?string {
		return $attr['desktop']['value']['orientation'] ?? null;
	}

	/**
	 * Get text CSS declaration based on given arguments.
	 *
	 * This function accepts an array of arguments that define the style declaration.
	 * It parses the arguments, sets default values, and generates a CSS style declaration based on the provided arguments.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/style-library/text-style-declaration/ textStyleDeclaration}
	 * located in `@divi/style-library` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments that define the style declaration.
	 *
	 *     @type string      $attrValue        The value (breakpoint > state > value) of module attribute.
	 *     @type array       $defaultAttrValue Optional. Default printed style attribute value.
	 *     @type string      $state            Optional. Active style state. Default `value`.
	 *     @type array|bool  $important        Optional. Whether to add `!important` to the CSS. Default `false`.
	 *     @type string      $returnType       Optional. The return type of the style declaration. Default `string`.
	 *                                         One of `string`, or `key_value_pair`
	 *                                           - If `string`, the style declaration will be returned as a string.
	 *                                           - If `key_value_pair`, the style declaration will be returned as an array of key-value pairs.
	 * }
	 *
	 * @return array|string The generated text CSS style declaration.
	 *
	 * @example:
	 * ```php
	 * $args = [
	 *     'attrValue'        => ['orientation' => 'center'], // The attribute value.
	 *     'defaultAttrValue' => ['orientation' => 'center'], // Default printed style attribute value.
	 *     'important'        => true,                        // Whether the declaration should be marked as important.
	 *     'returnType'       => 'key_value_pair',            // The return type of the style declaration.
	 * ];
	 * $style = Text::style_declaration( $args );
	 * ```
	 */
	public static function style_declaration( array $args ) {
		$args = wp_parse_args(
			$args,
			[
				'important'  => false,
				'returnType' => 'string',
			]
		);

		$attr               = $args['attr'] ?? [];
		$attr_value         = $args['attrValue'] ?? [];
		$default_attr_value = $args['defaultAttrValue'] ?? [];
		$state              = $args['state'] ?? 'value';
		$important          = $args['important'];
		$return_type        = $args['returnType'];
		$style_declarations = new StyleDeclarations(
			[
				'important'  => $important,
				'returnType' => $return_type,
			]
		);

		// Text alignment hover and sticky states are not supported by the shared text options UI.
		// Ignore saved non-value alignment state so FE and VB do not emit unsupported hover/sticky rules.
		if ( 'hover' === $state || 'sticky' === $state ) {
			return $style_declarations->value();
		}

		// Use orientation from attrValue if set, otherwise fallback to defaultAttrValue, then
		// fallback to base (desktop) orientation from full attr tree, then to 'start'.
		$raw_orientation = ( isset( $attr_value['orientation'] ) ? $attr_value['orientation'] : null )
			?? ( isset( $default_attr_value['orientation'] ) ? $default_attr_value['orientation'] : null )
			?? self::_get_base_orientation( $attr );
		$orientation     = self::_normalize_orientation_for_css( $raw_orientation ) ?? 'start';

		$style_declarations->add( 'text-align', $orientation );

		return $style_declarations->value();
	}
}
