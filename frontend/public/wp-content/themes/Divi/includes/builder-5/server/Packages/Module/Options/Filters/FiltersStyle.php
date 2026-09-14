<?php
/**
 * Module: FiltersStyle class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Filters;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Layout\Components\Style\Utils\Utils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Declarations\Filters\Filters;

/**
 * FiltersStyle class.
 *
 * This class has functionality for handling styles and filters for the background component.
 *
 * @since ??
 */
class FiltersStyle {

	/**
	 * Get filter style component.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/FilterStyle FiltersStyle} in
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
	 *     @type string        $returnType               Optional. This is the type of value that the function will return.
	 *                                                   Can be either `string` or `array`. Default `array`.
	 *     @type string        $atRules                  Optional. CSS at-rules to wrap the style declarations in. Default `''`.
	 * }
	 *
	 * @return string|array The filter style component.
	 *
	 * @example:
	 * ```php
	 * // Apply style using default arguments.
	 * $args = [];
	 * $style = FilterStyle::style( $args );
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
	 * $style = FilterStyle::style( $args );
	 * ```
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

		// Bail, if noting is there to process.
		if ( empty( $attr ) ) {
			return 'array' === $args['returnType'] ? [] : '';
		}

		$attr_normalized = self::normalize_attr( $attr );

		$children = Utils::style_statements(
			[
				'selectors'               => ! empty( $selectors ) ? $selectors : [ 'desktop' => [ 'value' => $selector ] ],
				'selectorFunction'        => $selector_function,
				'propertySelectors'       => $property_selectors,
				'attr'                    => $attr_normalized,
				'defaultPrintedStyleAttr' => $args['defaultPrintedStyleAttr'] ?? [],
				'important'               => $important,
				'declarationFunction'     => function ( $params ) {
					return Filters::style_declaration( $params );
				},
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'returnType'              => $args['returnType'],
				'atRules'                 => $args['atRules'],
			]
		);

		return Utils::style_wrapper(
			[
				'attr'     => $attr_normalized,
				'asStyle'  => $as_style,
				'children' => $children,
			]
		);
	}

	/**
	 * Normalize filter attributes before style statement generation.
	 *
	 * Ensure missing sticky state entries inherit resolved values so style output
	 * follows normalized attr patterns used by other option styles.
	 *
	 * @since ??
	 *
	 * @param array $attr The array of filters attributes to normalize.
	 *
	 * @return array
	 */
	public static function normalize_attr( array $attr ): array {
		$has_sticky = false;

		foreach ( $attr as $states ) {
			if ( is_array( $states ) && array_key_exists( 'sticky', $states ) ) {
				$has_sticky = true;
				break;
			}
		}

		if ( ! $has_sticky ) {
			return $attr;
		}

		$attr_normalized = null;

		foreach ( array_keys( $attr ) as $breakpoint ) {
			$breakpoint_states = $attr[ $breakpoint ] ?? [];

			if ( is_array( $breakpoint_states ) && array_key_exists( 'sticky', $breakpoint_states ) ) {
				continue;
			}

			$inherited_sticky_value = ModuleUtils::use_attr_value(
				[
					'attr'       => $attr,
					'breakpoint' => $breakpoint,
					'state'      => 'sticky',
					'mode'       => 'getAndInheritAll',
				]
			);

			if ( is_array( $inherited_sticky_value ) && ! empty( $inherited_sticky_value ) ) {
				if ( null === $attr_normalized ) {
					$attr_normalized = $attr;
				}

				$attr_normalized[ $breakpoint ] = array_merge(
					[],
					is_array( $breakpoint_states ) ? $breakpoint_states : [],
					[
						'sticky' => $inherited_sticky_value,
					]
				);
			}
		}

		return null !== $attr_normalized ? $attr_normalized : $attr;
	}
}
