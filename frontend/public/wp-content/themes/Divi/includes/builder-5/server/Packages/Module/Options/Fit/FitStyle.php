<?php
/**
 * Module: FitStyle class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Fit;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Layout\Components\Style\Utils\Utils;
use ET\Builder\Packages\StyleLibrary\Declarations\ObjectFit\ObjectFit;

/**
 * FitStyle class.
 *
 * Handles object-fit and object-position for element decoration (e.g. image framing).
 *
 * @since ??
 */
class FitStyle {

	/**
	 * Get fit style component.
	 *
	 * This function is equivalent of JS {@see FitStyle} in `@divi/module`.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string        $selector                 The CSS selector.
	 *     @type array         $selectors                Optional. Selectors per breakpoint/state. Default `[]`.
	 *     @type callable      $selectorFunction         Optional. Selector callback. Default `null`.
	 *     @type array         $propertySelectors        Optional. Property-specific selectors. Default `[]`.
	 *     @type array         $attr                     Fit attribute data.
	 *     @type array         $defaultPrintedStyleAttr  Optional. Default printed style attribute data. Default `[]`.
	 *     @type array|bool    $important                Optional. Whether declarations use `!important`. Default `false`.
	 *     @type bool          $asStyle                  Optional. Wrap in style tag. Default `true`.
	 *     @type string|null   $orderClass               Optional. Module order class name.
	 *     @type bool          $isInsideStickyModule     Optional. Default `false`.
	 *     @type string|null   $stickyParentOrderClass   Optional. Default `null`.
	 *     @type string        $returnType               Optional. `string` or `array`. Default `array`.
	 *     @type string        $atRules                  Optional. CSS at-rules wrapper. Default `''`.
	 * }
	 *
	 * @return string|array
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

		$selector           = $args['selector'];
		$selectors          = $args['selectors'];
		$selector_function  = $args['selectorFunction'];
		$property_selectors = $args['propertySelectors'];
		$attr               = $args['attr'];
		$important          = $args['important'];
		$as_style           = $args['asStyle'];
		$order_class        = $args['orderClass'];

		$is_inside_sticky_module   = $args['isInsideStickyModule'] ?? false;
		$sticky_parent_order_class = $args['stickyParentOrderClass'] ?? null;

		if ( empty( $attr ) && empty( $args['defaultPrintedStyleAttr'] ) ) {
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
				'declarationFunction'     => function ( $params ) {
					return ObjectFit::style_declaration( $params );
				},
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'returnType'              => $args['returnType'],
				'atRules'                 => $args['atRules'],
			]
		);

		$wrapper_attr = ! empty( $attr ) ? $attr : ( $args['defaultPrintedStyleAttr'] ?? [] );

		return Utils::style_wrapper(
			[
				'attr'     => $wrapper_attr,
				'asStyle'  => $as_style,
				'children' => $children,
			]
		);
	}
}
