<?php
/**
 * Module: SizingStyle class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Sizing;

use ET\Builder\Packages\Module\Layout\Components\Style\Utils\Utils;
use ET\Builder\Packages\StyleLibrary\Declarations\Sizing\Sizing;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * SizingStyle class.
 *
 * This class represents a style for sizing elements.
 *
 * @since ??
 */
class SizingStyle {

	/**
	 * Get sizing (width, max-width, alignment, min-height, height, max-height) styles.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/SizingStyle SizingStyle} in
	 * `@divi/module` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string        $selector                 The CSS selector.
	 *     @type array         $selectors                Optional. An array of selectors for each breakpoint and state. Default `[]`.
	 *     @type callable      $selectorFunction         Optional. The function to be called to generate CSS selector. Default `null`.
	 *     @type array         $propertySelectors        Optional. The property selectors that you want to unpack. Default `[]`.
	 *     @type array         $attr                     An array of module attribute data.
	 *     @type array         $defaultPrintedStyleAttr  Optional. An array of default printed style attribute data. Default `[]`.
	 *     @type array|bool    $important                Optional. Whether to apply "!important" flag to the style declarations.
	 *                                                   Default `false`.
	 *     @type bool          $asStyle                  Optional. Whether to wrap the style declaration with style tag or not.
	 *                                                   Default `true`
	 *     @type string|null   $orderClass               Optional. The selector class name.
	 *     @type bool          $isInsideStickyModule     Optional. Whether the module is inside a sticky module or not. Default `false`.
	 *     @type string|null   $stickyParentOrderClass   Optional. The sticky parent order class name. Default `null`.
	 *     @type string        $attrs_json               Optional. The JSON string of module attribute data, use to improve performance.
	 *     @type string        $returnType               Optional. This is the type of value that the function will return.
	 *                                                   Can be either `string` or `array`. Default `array`.
	 *     @type bool          $skipDefaults             Optional. Whether to skip printing default values. Default `false`.
	 * }
	 *
	 * @return string|array The sizing style component.
	 *
	 * @example:
	 * ```php
	 * // Apply style using default arguments.
	 * $args = [];
	 * $style = self::style($args);
	 *
	 * // Apply style with specific selectors and properties.
	 * $args = [
	 *     'selectors' => [
	 *         '.element1',
	 *         '.element2',
	 *     ],
	 *     'propertySelectors' => [
	 *         '.element1 .property1',
	 *         '.element2 .property2',
	 *     ]
	 * ];
	 * $style = self::style($args);
	 * ```
	 */
	public static function style( array $args ) {
		$args = wp_parse_args(
			$args,
			[
				'selectors'              => [],
				'propertySelectors'      => [],
				'selectorFunction'       => null,
				'important'              => false,
				'asStyle'                => true,
				'orderClass'             => null,
				'attrs_json'             => null,
				'returnType'             => 'array',
				'atRules'                => '',
				'skipDefaults'           => false,
				'disableAlignmentStyles' => false,
			]
		);

		$selector           = $args['selector'];
		$selectors          = $args['selectors'];
		$selector_function  = $args['selectorFunction'];
		$property_selectors = $args['propertySelectors'];
		$attr               = $args['attr'];
		$important          = $args['important'];
		$as_style           = $args['asStyle'];
		$order_class        = $args['orderClass'];
		$at_rules           = $args['atRules'];

		$is_inside_sticky_module   = $args['isInsideStickyModule'] ?? false;
		$sticky_parent_order_class = $args['stickyParentOrderClass'] ?? null;
		$is_parent_flex_layout     = $args['isParentFlexLayout'] ?? false;
		$is_parent_grid_layout     = $args['isParentGridLayout'] ?? false;

		// Bail, if noting is there to process.
		if ( empty( $attr ) ) {
			return 'array' === $args['returnType'] ? [] : '';
		}

		$children = Utils::style_statements(
			[
				'selectors'               => ! empty( $selectors ) ? $selectors : [ 'desktop' => [ 'value' => $selector ] ],
				'selectorFunction'        => $selector_function,
				'propertySelectors'       => $property_selectors,
				'attr'                    => $attr,
				'defaultPrintedStyleAttr' => $args['defaultPrintedStyleAttr'] ?? [],
				'important'               => $important,
				'declarationFunction'     => Sizing::class . '::style_declaration',
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'isParentFlexLayout'      => $is_parent_flex_layout,
				'isParentGridLayout'      => $is_parent_grid_layout,
				'disableAlignmentStyles'  => $args['disableAlignmentStyles'],
				'returnType'              => $args['returnType'],
				'atRules'                 => $at_rules,
				'skipDefaults'            => $args['skipDefaults'],
			]
		);

		return Utils::style_wrapper(
			[
				'attr'     => $attr,
				'asStyle'  => $as_style,
				'children' => $children,
			]
		);
	}
}
