<?php
/**
 * Transform::hovered_style_declaration()
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\StyleLibrary\Declarations\Transform\TransformTraits;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

trait HoveredStyleDeclarationTrait {

	/**
	 * Get the Transform CSS declaration based on the given arguments for hover state.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/style-library/transform-hovered-style-declaration/ transformHoveredStyleDeclaration}
	 * located in `@divi/style-library` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string      $attrValue   The value (breakpoint > state > value) of module attribute.
	 *     @type array|bool  $important   Whether to add `!important` to the CSS.
	 *     @type string      $returnType  Optional. The return type of the style declaration. Default `string`.
	 *                                    One of `string`, or `key_value_pair`
	 *                                      - If `string`, the style declaration will be returned as a string.
	 *                                      - If `key_value_pair`, the style declaration will be returned as an array of key-value pairs.
	 *     @type array       $additional  Optional. Additional data including positionAttrs and normalStateOrigin. Default [].
	 *     @type string      $breakpoint  Optional. Current breakpoint. Default null.
	 *     @type string      $state       Optional. Current state. Default 'hover'.
	 * }
	 *
	 * @return string The CSS declaration string.
	 *
	 * @example:
	 * ```php
	 *     $args = [
	 *         'attrValue' => [
	 *             'translate' => [
	 *                 'x' => '100px',
	 *                 'y' => '50px',
	 *             ],
	 *             'origin' => [
	 *                 'x' => '25%',
	 *                 'y' => '75%',
	 *             ],
	 *         ],
	 *         'important' => true,
	 *         'returnType' => 'key_value_pair',
	 *     ];
	 *     $declaration = Transform::hovered_style_declaration($args);
	 *     echo $declaration;
	 *
	 *     // Output: "transform: translateX(100px) translateY(50px); transform-origin: 25% 75%;"
	 * ```
	 */
	public static function hovered_style_declaration( array $args ) {
		// Wrapper for backwards compatibility. Calls unified style_declaration().
		// Note: PHP uses real :hover pseudo-selector, not .et_pb_hover class like VB.
		return self::style_declaration( $args );
	}
}
