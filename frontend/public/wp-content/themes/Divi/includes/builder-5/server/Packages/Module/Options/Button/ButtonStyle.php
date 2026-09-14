<?php
/**
 * Module: ButtonStyle class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Button;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Layout\Components\Style\Utils\Utils;
use ET\Builder\Packages\Module\Layout\Components\StyleCommon\CommonStyle;
use ET\Builder\Packages\Module\Options\Button\Style\StyleDeclarations;
use ET\Builder\Packages\StyleLibrary\Declarations\ButtonIcon\ButtonIcon;

/**
 * ButtonStyle class.
 *
 * A class for managing button styles.
 *
 * @since ??
 */
class ButtonStyle {

	/**
	 * Get button style component.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/ButtonStyle ButtonStyle} in
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
	 *     @type bool|array    $affectingAttrs           Optional. An array of affecting attributes. Default `[]`.
	 *     @type string|null   $orderClass               Optional. The selector class name.
	 *     @type bool          $isInsideStickyModule     Optional. Whether the module is inside a sticky module or not. Default `false`.
	 *     @type string        $stickyParentOrderClass   Optional. The sticky parent order class name.
	 *     @type string        $attrs_json               Optional. The JSON string of module attribute data, use to improve performance.
	 *     @type string        $returnType               Optional. This is the type of value that the function will return.
	 *                                                   Can be either `string` or `array`. Default `array`.
	 * }
	 *
	 * @return string|array The transform style component.
	 *
	 * @example:
	 * ```php
	 * // Apply style using default arguments.
	 * $args = [];
	 * $style = ButtonStyle::style( $args );
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
	 * $style = ButtonStyle::style( $args );
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
				'attr'                   => [],
				'affectingAttrs'         => [],
				'orderClass'             => null,
				'attrs_json'             => null,
				'returnType'             => 'array',
				'isInsideStickyModule'   => false,
				'stickyParentOrderClass' => null,
			]
		);

		$selector           = $args['selector'];
		$selectors          = $args['selectors'];
		$attr               = $args['attr'];
		$affecting_attrs    = $args['affectingAttrs'];
		$important          = $args['important'];
		$selector_function  = $args['selectorFunction'];
		$property_selectors = $args['propertySelectors'];
		$order_class        = $args['orderClass'];
		$return_as_array    = 'array' === $args['returnType'];
		$children           = $return_as_array ? [] : '';

		$default_printed_style_attr = $args['defaultPrintedStyleAttr'] ?? [];

		$is_inside_sticky_module   = $args['isInsideStickyModule'];
		$sticky_parent_order_class = $args['stickyParentOrderClass'];

		// Bail, if noting is there to process.
		if ( empty( $attr ) ) {
			return $children;
		}

		// If attrs_json is provided use that, otherwise JSON encode the attributes array.
		$attr_json = null === $args['attrs_json'] ? wp_json_encode( $attr ) : $args['attrs_json'];

		// Selector for icon.
		$icon_selectors = ! empty( $selectors ) ? $selectors : [ 'desktop' => [ 'value' => $selector ] ];

		$children_statements = Utils::style_statements(
			[
				'selectors'               => $icon_selectors,
				'propertySelectors'       => $property_selectors,
				'attr'                    => $attr,
				'defaultPrintedStyleAttr' => $default_printed_style_attr,
				'important'               => $important,
				'declarationFunction'     => ButtonIcon::class . '::style_declaration',
				'selectorFunction'        => function ( $params ) {
					$params = wp_parse_args(
						$params,
						[
							'selector'   => null,
							'breakpoint' => null,
							'state'      => null,
						]
					);

					$selector   = $params['selector'];
					$breakpoint = $params['breakpoint'];
					$state      = $params['state'];
					$attr       = $params['attr'];

					$default_printed_style_attr = $params['defaultPrintedStyleAttr'] ?? [];

					$default_placement = 'right';
					$is_main           = 'desktop' === $breakpoint && 'value' === $state;
					$main_placement    = $attr['desktop']['value']['icon']['placement']
						?? $default_printed_style_attr['desktop']['value']['icon']['placement']
						?? $default_placement;
					$current_placement = $is_main
						? $main_placement
						: $attr[ $breakpoint ][ $state ]['icon']['placement']
							?? $default_printed_style_attr[ $breakpoint ][ $state ]['icon']['placement']
							?? $main_placement;

					if ( 'left' === $current_placement ) {
						return self::apply_pseudo_element( $selector, ':before' );
					}

					return self::apply_pseudo_element( $selector, ':after' );
				},
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'returnType'              => $args['returnType'],
			]
		);

		if ( $children_statements && $return_as_array ) {
			array_push( $children, ...$children_statements );
		} elseif ( $children_statements ) {
			$children .= $children_statements;
		}

		$children_hover = Utils::style_statements(
			[
				'selectors'               => $icon_selectors,
				'propertySelectors'       => $property_selectors,
				'attr'                    => $attr,
				'defaultPrintedStyleAttr' => $default_printed_style_attr,
				'important'               => $important,
				'declarationFunction'     => ButtonIcon::class . '::hover_style_declaration',
				'selectorFunction'        => function ( $params ) {
					$params = wp_parse_args(
						$params,
						[
							'selector'   => null,
							'breakpoint' => null,
							'state'      => null,
						]
					);

					$selector   = $params['selector'];
					$breakpoint = $params['breakpoint'];
					$state      = $params['state'];
					$attr       = $params['attr'];

					$default_printed_style_attr = $params['defaultPrintedStyleAttr'] ?? [];

					$default_placement = 'right';
					$is_main           = 'desktop' === $breakpoint && 'value' === $state;
					$main_placement    = $attr['desktop']['value']['icon']['placement']
						?? $default_printed_style_attr['desktop']['value']['icon']['placement']
						?? $default_placement;
					$current_placement = $is_main
						? $main_placement
						: $attr[ $breakpoint ][ $state ]['icon']['placement']
							?? $default_printed_style_attr[ $breakpoint ][ $state ]['icon']['placement']
							?? $main_placement;

					if ( 'left' === $current_placement ) {
						// phpcs:ignore ET.Comments.Todo.TodoFound -- TODO has issue reference (#33635) but doesn't match exact PHPCS format requirement.
						// TODO feat(D5, Module Styles): Avoid adding double :hover to the selector
						// @see https://github.com/elegantthemes/Divi/issues/33635.
						return str_contains( $selector, ':hover' )
							? $selector . ':before'
							: $selector . ':hover:before';
					}

					// phpcs:ignore ET.Comments.Todo.TodoFound -- TODO has issue reference (#33635) but doesn't match exact PHPCS format requirement.
					// TODO feat(D5, Module Styles): Avoid adding double :hover to the selector
					// @see https://github.com/elegantthemes/Divi/issues/33635.
					return str_contains( $selector, ':hover' )
						? self::apply_pseudo_element( $selector, ':after' )
						: self::apply_pseudo_element( $selector, ':hover:after' );
				},
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'returnType'              => $args['returnType'],
			]
		);

		if ( $children_hover && $return_as_array ) {
			array_push( $children, ...$children_hover );
		} elseif ( $children_hover ) {
			$children .= $children_hover;
		}

		$children_right = Utils::style_statements(
			[
				'selectors'               => $icon_selectors,
				'propertySelectors'       => $property_selectors,
				'attr'                    => $attr,
				'defaultPrintedStyleAttr' => $default_printed_style_attr,
				'important'               => $important,
				'declarationFunction'     => ButtonIcon::class . '::right_style_declaration',
				'selectorFunction'        => function ( $params ) {
					$params = wp_parse_args(
						$params,
						[
							'selector' => null,
						]
					);

					$selector = $params['selector'];

					return self::apply_pseudo_element( $selector, ':after' );
				},
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'returnType'              => $args['returnType'],
			]
		);

		if ( $children_right && $return_as_array ) {
			array_push( $children, ...$children_right );
		} elseif ( $children_right ) {
			$children .= $children_right;
		}

		$children_disable = Utils::style_statements(
			[
				'selectors'               => $icon_selectors,
				'propertySelectors'       => $property_selectors,
				'attr'                    => $attr,
				'defaultPrintedStyleAttr' => $default_printed_style_attr,
				'important'               => $important,
				'declarationFunction'     => ButtonIcon::class . '::disable_style_declaration',
				'selectorFunction'        => function ( $params ) {
					$params = wp_parse_args(
						$params,
						[
							'selector' => null,
						]
					);

					$selector = $params['selector'];

					$before_selector = self::apply_pseudo_element( $selector, ':before' );
					$after_selector  = self::apply_pseudo_element( $selector, ':after' );

					return implode( ', ', [ $before_selector, $after_selector ] );
				},
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'returnType'              => $args['returnType'],
			]
		);

		if ( $children_disable && $return_as_array ) {
			array_push( $children, ...$children_disable );
		} elseif ( $children_disable ) {
			$children .= $children_disable;
		}

		$children_statements = CommonStyle::style(
			[
				'selector'                => $selector,
				'selectors'               => $selectors,
				'selectorFunction'        => $selector_function,
				'attrs_json'              => $attr_json,
				'attr'                    => array_merge_recursive(
					[],
					$affecting_attrs['spacing'] ?? [],
					$attr
				),
				'defaultPrintedStyleAttr' => $default_printed_style_attr,
				'asStyle'                 => false,
				'declarationFunction'     => function ( $params ) {
					return StyleDeclarations::spacing_icon_style_declaration( $params );
				},
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'returnType'              => $args['returnType'],
			]
		);

		if ( $children_statements && $return_as_array ) {
			array_push( $children, ...$children_statements );
		} elseif ( $children_statements ) {
			$children .= $children_statements;
		}

		$children_statements = CommonStyle::style(
			[
				'selector'                => $selector,
				'selectors'               => $selectors,
				'selectorFunction'        => function ( $params ) {
						// phpcs:ignore ET.Comments.Todo.TodoFound -- TODO has issue reference (#33635) but doesn't match exact PHPCS format requirement.
						// TODO feat(D5, Module Styles): Avoid adding double :hover to the selector
						// @see https://github.com/elegantthemes/Divi/issues/33635.
						return str_contains( $params['selector'], ':hover' )
							? $params['selector']
							: self::apply_pseudo_element( $params['selector'], ':hover' );
				},
				'attrs_json'              => $attr_json,
				'attr'                    => array_merge_recursive(
					[],
					$affecting_attrs['spacing'] ?? [],
					$attr
				),
				'defaultPrintedStyleAttr' => $default_printed_style_attr,
				'asStyle'                 => false,
				'declarationFunction'     => function ( $params ) {
					return StyleDeclarations::spacing_icon_hover_style_declaration( $params );
				},
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'returnType'              => $args['returnType'],
			]
		);

		if ( $children_statements && $return_as_array ) {
			array_push( $children, ...$children_statements );
		} elseif ( $children_statements ) {
			$children .= $children_statements;
		}

		return Utils::style_wrapper(
			[
				'attr'     => $attr,
				'children' => $children,
			]
		);
	}

	/**
	 * Apply pseudo element to selector.
	 *
	 * This function will handle comma-separated selectors by adding the pseudo element to each selector.
	 *
	 * @param string $selector The selector string. This can be a comma-separated list of selectors.
	 * @param string $pseudo_element The pseudo element.
	 *
	 * @return string The selector(s) with pseudo element.
	 */
	public static function apply_pseudo_element( string $selector, string $pseudo_element ): string {
		$selectors = explode( ',', $selector );

		$selectors = array_map(
			function ( $item ) use ( $pseudo_element ) {
				return trim( $item ) . $pseudo_element;
			},
			$selectors
		);

		return implode( ', ', $selectors );
	}
}
