<?php
/**
 * Module: DisabledOnStyle class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\DisabledOn;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Layout\Components\Style\Utils\Utils;
use ET\Builder\Packages\StyleLibrary\Declarations\DisabledOn\DisabledOn;

/**
 * DisabledOnStyle class.
 *
 * This class contains functionality to work with disabled on styles.
 *
 * @since ??
 */
class DisabledOnStyle {

	/**
	 * Get disabled-on style component.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/DisabledOnStyle DisabledOnStyle} in
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
	 *                                                   Default `true`.
	 *     @type string        $disabledModuleVisibility Optional. Disabled module visibility. One of `transparent` or `hidden`.
	 *                                                   Default `null`.
	 *     @type string|null   $orderClass               Optional. The selector class name.
	 *     @type bool          $isInsideStickyModule     Optional. Whether the module is inside a sticky module or not. Default `false`.
	 *     @type string|null   $stickyParentOrderClass   Optional. The sticky parent order class name. Default `null`.
	 *     @type string        $returnType               Optional. This is the type of value that the function will return.
	 *                                                   Can be either `string` or `array`. Default `array`.
	 *     @type string        $atRules                  Optional. CSS at-rules to wrap the style declarations in. Default `''`.
	 * }
	 *
	 * @return string|array The disabled-on style component.
	 *
	 * @example:
	 * ```php
	 * // Apply style using default arguments.
	 * $args = [];
	 * $style = DisabledOnStyle::style( $args );
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
	 * $style = DisabledOnStyle::style( $args );
	 * ```
	 */
	public static function style( array $args ) {
		$args = wp_parse_args(
			$args,
			[
				'selectors'                => [],
				'propertySelectors'        => [],
				'selectorFunction'         => null,
				'asStyle'                  => true,
				'disabledModuleVisibility' => null,
				'orderClass'               => null,
				'returnType'               => 'string',
				'atRules'                  => '',
			]
		);

		$selector                   = $args['selector'];
		$selectors                  = $args['selectors'];
		$selector_function          = $args['selectorFunction'];
		$property_selectors         = $args['propertySelectors'];
		$attr                       = $args['attr'];
		$as_style                   = $args['asStyle'];
		$disabled_module_visibility = $args['disabledModuleVisibility'];
		$order_class                = $args['orderClass'];
		$is_inside_sticky_module    = $args['isInsideStickyModule'] ?? false;
		$sticky_parent_order_class  = $args['stickyParentOrderClass'] ?? null;
		$at_rules                   = $args['atRules'];

		// Bail, if noting is there to process.
		if ( empty( $attr ) ) {
			return 'array' === $args['returnType'] ? [] : '';
		}

		// If no custom selectors provided, create selectors for all disabled breakpoints.
		$default_selectors = ! empty( $selectors ) ? $selectors : array_reduce(
			array_keys( $attr ),
			function ( $carry, $breakpoint ) use ( $selector, $attr ) {
				if ( isset( $attr[ $breakpoint ]['value'] ) && 'on' === $attr[ $breakpoint ]['value'] ) {
					$carry[ $breakpoint ] = [ 'value' => $selector ];
				}
				return $carry;
			},
			[]
		);

		$children = Utils::style_statements(
			[
				'selectors'               => $default_selectors,
				'selectorFunction'        => $selector_function,
				'propertySelectors'       => $property_selectors,
				'attr'                    => $attr,
				'defaultPrintedStyleAttr' => $args['defaultPrintedStyleAttr'] ?? [],
				'declarationFunction'     => function ( $props ) use ( $disabled_module_visibility ) {
					return DisabledOn::style_declaration(
						array_merge(
							[
								'disabledModuleVisibility' => $disabled_module_visibility,
							],
							$props
						)
					);
				},
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'isVisibilityContext'     => true,
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
