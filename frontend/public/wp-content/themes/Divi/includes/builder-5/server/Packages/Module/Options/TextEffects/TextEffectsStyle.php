<?php
/**
 * Module: TextEffectsStyle class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\TextEffects;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Layout\Components\Style\Utils\Utils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Declarations\TextEffects\TextEffects;
use ET\Builder\Packages\StyleLibrary\Utils\GradientUtils;

/**
 * TextEffectsStyle class.
 *
 * @since ??
 */
class TextEffectsStyle {

	/**
	 * Get text effects style component.
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
	 *     @type array|boolean $important                Optional. Whether to apply "!important" flag to the style declarations.
	 *                                                   Default `false`.
	 *     @type bool          $asStyle                  Optional. Whether to wrap the style declaration with style tag or not.
	 *                                                   Default `true`.
	 *     @type string|null   $orderClass               Optional. The selector class name.
	 *     @type bool          $isInsideStickyModule     Optional. Whether the module is inside a sticky module or not. Default `false`.
	 *     @type string|null   $stickyParentOrderClass   Optional. The sticky parent order class name. Default `null`.
	 *     @type string        $returnType               Optional. This is the type of value that the function will return.
	 *                                                   Can be either `string` or `array`. Default `array`.
	 * }
	 *
	 * @return string|array The text effects style component.
	 */
	public static function style( array $args ) {
		$args = wp_parse_args(
			$args,
			[
				'selectors'         => [],
				'propertySelectors' => [],
				'selectorFunction'  => null,
				'important'         => false,
				'asStyle'           => true,
				'orderClass'        => null,
				'returnType'        => 'array',
				'atRules'           => '',
			]
		);

		$selector                   = $args['selector'];
		$selectors                  = $args['selectors'];
		$selector_function          = $args['selectorFunction'];
		$property_selectors         = $args['propertySelectors'];
		$attr                       = $args['attr'] ?? [];
		$default_printed_style_attr = $args['defaultPrintedStyleAttr'] ?? [];
		$important                  = $args['important'];
		$as_style                   = $args['asStyle'];
		$order_class                = $args['orderClass'];
		$at_rules                   = $args['atRules'];
		$is_inside_sticky_module    = $args['isInsideStickyModule'] ?? false;
		$sticky_parent_order_class  = $args['stickyParentOrderClass'] ?? null;

		// Bail, if nothing is there to process.
		if ( empty( $attr ) ) {
			return 'array' === $args['returnType'] ? [] : '';
		}

		$children = Utils::style_statements(
			[
				'selectors'               => ! empty( $selectors ) ? $selectors : [ 'desktop' => [ 'value' => $selector ] ],
				'selectorFunction'        => $selector_function,
				'propertySelectors'       => $property_selectors,
				'attr'                    => $attr,
				'defaultPrintedStyleAttr' => $default_printed_style_attr,
				'important'               => $important,
				'declarationFunction'     => function ( $props ) use ( $default_printed_style_attr ) {
					$breakpoint = $props['breakpoint'] ?? null;
					$state      = $props['state'] ?? 'value';

					$default_gradient  = GradientUtils::get_resolved_default_gradient_for_breakpoint(
						[
							'defaultPrintedStyleAttr' => $default_printed_style_attr,
							'breakpoint'              => $breakpoint,
							'state'                   => $state,
							'fallbackGradient'        => $props['defaultAttrValue']['gradient'] ?? [],
						]
					);
					$default_fill_type = null;
					if ( is_array( $default_printed_style_attr ) && is_string( $breakpoint ) ) {
						$default_fill_type = ModuleUtils::get_attr_subname_value(
							[
								'attr'         => $default_printed_style_attr,
								'subname'      => 'fillType',
								'breakpoint'   => $breakpoint,
								'state'        => $state,
								'mode'         => 'getAndInheritAll',
								'defaultValue' => null,
							]
						);
					}

					$default_attr_value_with_gradient = $props['defaultAttrValue'] ?? [];
					if ( is_array( $default_attr_value_with_gradient ) ) {
						$default_attr_value_with_gradient['gradient'] = $default_gradient;
						if ( is_string( $default_fill_type ) && '' !== $default_fill_type ) {
							$default_attr_value_with_gradient['fillType'] = $default_fill_type;
						}
					}

					return TextEffects::style_declaration(
						array_merge(
							$props,
							[
								'defaultAttrValue' => $default_attr_value_with_gradient,
							]
						)
					);
				},
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'returnType'              => $args['returnType'],
				'atRules'                 => $at_rules,
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
