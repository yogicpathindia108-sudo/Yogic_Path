<?php
/**
 * Module: FormFieldStyle class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\FormField;

use ET\Builder\Packages\Module\Layout\Components\StyleCommon\CommonStyle;
use ET\Builder\Packages\Module\Layout\Components\Style\Utils\Utils;
use ET\Builder\Packages\Module\Options\Element\ElementStyle;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * FormFieldStyle class.
 *
 * This class is responsible for applying styles to form fields.
 *
 * @since ??
 */
class FormFieldStyle {

	/**
	 * Get form-field styles.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/FormFieldStyle FormFieldStyle} in
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
	 *     @type bool          $disableLabelStyle        Optional. Whether to disable label style output. Default `false`.
	 *     @type array         $layout                   Optional. Layout style props passed to the primary ElementStyle call. Default `[]`.
	 *     @type string        $returnType               Optional. This is the type of value that the function will return.
	 *                                                   Can be either `string` or `array`. Default `array`.
	 * }
	 *
	 * @return string|array The form-field style component.
	 *
	 * @example:
	 * ```php
	 * // Apply style using default arguments.
	 * $args = [];
	 * $style = self::style( $args );
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
	 * $style = self::style( $args );
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
				'returnType'             => 'array',
				'isInsideStickyModule'   => false,
				'stickyParentOrderClass' => null,
				'disableLabelStyle'      => false,
			]
		);

		$selector                     = $args['selector'];
		$selectors                    = $args['selectors'];
		$selector_function            = $args['selectorFunction'];
		$property_selectors           = $args['propertySelectors'];
		$attr                         = $args['attr'];
		$important                    = $args['important'];
		$as_style                     = $args['asStyle'];
		$order_class                  = $args['orderClass'];
		$return_as_array              = 'array' === $args['returnType'];
		$is_inside_sticky_module      = $args['isInsideStickyModule'];
		$sticky_parent_order_class    = $args['stickyParentOrderClass'];
		$children                     = $return_as_array ? [] : '';
		$has_explicit_label_selectors = ! empty( $property_selectors['label']['font'] ?? [] );
		$disable_label_style          = $args['disableLabelStyle'];
		$layout                       = $args['layout'] ?? [];
		$focus_placeholder_font_attr  = self::_get_focus_font_attr_from_decoration_font( $attr['decoration']['font'] ?? [] );
		$placeholder_font_attr        = $attr['decoration']['placeholderFont'] ?? [];

		// Bail, if noting is there to process.
		if ( empty( $attr ) ) {
			return 'array' === $args['returnType'] ? [] : '';
		}

		$element_style = ElementStyle::style(
			[
				'selector'               => $selector,
				'attrs'                  => $attr['decoration'] ?? [],
				'orderClass'             => $order_class,
				'returnType'             => $args['returnType'],
				'isInsideStickyModule'   => $is_inside_sticky_module,
				'stickyParentOrderClass' => $sticky_parent_order_class,
				'background'             => [
					'selectors'         => $selectors,
					'selectorFunction'  => $selector_function,
					'propertySelectors' => $property_selectors['background'] ?? [],
					'important'         => is_bool( $important ) ? $important : ( $important['background'] ?? false ),
				],
				'border'                 => [
					'selectors'         => $selectors,
					'selectorFunction'  => $selector_function,
					'propertySelectors' => $property_selectors['border'] ?? [],
					'important'         => is_bool( $important ) ? $important : ( $important['border'] ?? false ),
				],
				'boxShadow'              => [
					'selectors'         => $selectors,
					'selectorFunction'  => $selector_function,
					'propertySelectors' => $property_selectors['boxShadow'] ?? [],
					'important'         => is_bool( $important ) ? $important : ( $important['boxShadow'] ?? false ),
				],
				'font'                   => [
					'selectors'         => $selectors,
					'selectorFunction'  => $selector_function,
					'propertySelectors' => $property_selectors['font'] ?? [],
					'important'         => is_bool( $important ) ? $important : ( $important['font'] ?? false ),
				],
				'spacing'                => [
					'selectors'         => $selectors,
					'selectorFunction'  => $selector_function,
					'propertySelectors' => $property_selectors['spacing'] ?? [],
					'important'         => is_bool( $important ) ? $important : ( $important['spacing'] ?? false ),
				],
				'layout'                 => $layout,
			]
		);

		if ( $element_style && $return_as_array ) {
			array_push( $children, ...$element_style );
		} elseif ( $element_style ) {
			$children .= $element_style;
		}

		if ( ! $disable_label_style ) {
			$element_label_style = ElementStyle::style(
				[
					'selector'               => $selector,
					'orderClass'             => $order_class,
					'returnType'             => $args['returnType'],
					'isInsideStickyModule'   => $is_inside_sticky_module,
					'stickyParentOrderClass' => $sticky_parent_order_class,
					'attrs'                  => [
						'font' => $attr['decoration']['labelFont'] ?? [],
					],
					'font'                   => [
						'selectorFunction'  => function ( $params ) use ( $selector_function, $has_explicit_label_selectors ) {
							$maybe_multiple_selectors = is_callable( $selector_function ) ? call_user_func( $selector_function, $params ) : ( $params['selector'] ?? '' );

							if ( $has_explicit_label_selectors ) {
								return $maybe_multiple_selectors;
							}

							$splitted_selectors = explode( ',', $maybe_multiple_selectors );

							$modified_selectors = array_map(
								function ( $splitted_selector ) {
									$trimmed_selector = rtrim( $splitted_selector );

									if ( str_contains( $trimmed_selector, 'label' ) ) {
										return $trimmed_selector;
									}

									return $trimmed_selector . ' label';
								},
								$splitted_selectors
							);

							return implode( ',', $modified_selectors );
						},
						'propertySelectors' => $property_selectors['label']['font'] ?? [],
						'important'         => is_bool( $important ) ? $important : ( $important['label']['font'] ?? false ),
					],
				]
			);

			if ( $element_label_style && $return_as_array ) {
				array_push( $children, ...$element_label_style );
			} elseif ( $element_label_style ) {
				$children .= $element_label_style;
			}
		}

		$accent_color_style = CommonStyle::style(
			[
				'selector'               => $selector,
				'attr'                   => $attr['decoration']['accentColor'] ?? '',
				'property'               => 'accent-color',
				'orderClass'             => $order_class,
				'returnType'             => $args['returnType'],
				'isInsideStickyModule'   => $is_inside_sticky_module,
				'stickyParentOrderClass' => $sticky_parent_order_class,
			]
		);

		if ( $accent_color_style && $return_as_array ) {
			array_push( $children, ...$accent_color_style );
		} elseif ( $accent_color_style ) {
			$children .= $accent_color_style;
		}

		// ::*placeholder style can't handle multiple selectors used the same statements.
		$element_placeholder_style = ElementStyle::style(
			[
				'selector'               => $selector,
				'orderClass'             => $order_class,
				'returnType'             => $args['returnType'],
				'isInsideStickyModule'   => $is_inside_sticky_module,
				'stickyParentOrderClass' => $sticky_parent_order_class,
				'attrs'                  => [
					'font' => $placeholder_font_attr,
				],
				'font'                   => [
					'selectorFunction'  => function ( $params ) use ( $selector_function ) {
						$maybe_multiple_selectors = is_callable( $selector_function ) ? call_user_func( $selector_function, $params ) : ( $params['selector'] ?? '' );
						$splitted_selectors       = explode( ',', $maybe_multiple_selectors );

						$modified_selectors = array_map(
							function ( $splitted_selector ) {
								return rtrim( $splitted_selector ) . '::placeholder';
							},
							$splitted_selectors
						);

						return implode( ',', $modified_selectors );
					},
					'propertySelectors' => $property_selectors['placeholder']['font'] ?? [],
					'important'         => is_bool( $important ) ? $important : ( $important['placeholder']['font'] ?? false ),
				],
			]
		);

		if ( $element_placeholder_style && $return_as_array ) {
			array_push( $children, ...$element_placeholder_style );
		} elseif ( $element_placeholder_style ) {
			$children .= $element_placeholder_style;
		}

		// more placeholder styles to cover focus placeholders.

		$element_placeholder_style = ElementStyle::style(
			[
				'selector'               => $selector,
				'orderClass'             => $order_class,
				'returnType'             => $args['returnType'],
				'isInsideStickyModule'   => $is_inside_sticky_module,
				'stickyParentOrderClass' => $sticky_parent_order_class,
				'attrs'                  => [
					'font' => $focus_placeholder_font_attr,
				],
				'font'                   => [
					'selectorFunction'  => function ( $params ) use ( $selector_function ) {
						$maybe_multiple_selectors = is_callable( $selector_function ) ? call_user_func( $selector_function, $params ) : ( $params['selector'] ?? '' );
						$splitted_selectors       = explode( ',', $maybe_multiple_selectors );

						$modified_selectors = array_map(
							function ( $splitted_selector ) {
								return rtrim( $splitted_selector ) . ':focus::placeholder';
							},
							$splitted_selectors
						);

						return implode( ',', $modified_selectors );
					},
					'propertySelectors' => $property_selectors['placeholder']['font'] ?? [],
					'important'         => is_bool( $important ) ? $important : ( $important['placeholder']['font'] ?? false ),
				],
			]
		);

		if ( $element_placeholder_style && $return_as_array ) {
			array_push( $children, ...$element_placeholder_style );
		} elseif ( $element_placeholder_style ) {
			$children .= $element_placeholder_style;
		}

		return Utils::style_wrapper(
			[
				'attr'     => $attr,
				'asStyle'  => $as_style,
				'children' => $children,
			]
		);
	}

	/**
	 * Extract focus font values from decoration font into value state shape.
	 *
	 * @since ??
	 *
	 * @param array $decoration_font_attr Decoration font group attr.
	 *
	 * @return array
	 */
	private static function _get_focus_font_attr_from_decoration_font( array $decoration_font_attr ): array {
		$font_breakpoint_states = $decoration_font_attr['font'] ?? null;
		if ( ! is_array( $font_breakpoint_states ) ) {
			return [];
		}

		$focus_font_attr = [ 'font' => [] ];

		foreach ( $font_breakpoint_states as $breakpoint => $states ) {
			if ( ! is_array( $states ) || ! is_array( $states['focus'] ?? null ) ) {
				continue;
			}

			$focus_font_attr['font'][ $breakpoint ] = [
				'value' => $states['focus'],
			];
		}

		return $focus_font_attr['font'] ? $focus_font_attr : [];
	}
}
