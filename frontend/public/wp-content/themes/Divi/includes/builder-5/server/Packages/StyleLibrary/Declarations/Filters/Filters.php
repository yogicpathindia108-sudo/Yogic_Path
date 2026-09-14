<?php
/**
 * Filters class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\StyleLibrary\Declarations\Filters;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Framework\Breakpoint\Breakpoint;

/**
 * Filters class.
 *
 * @since ??
 */
class Filters {

	/**
	 * Get filter's CSS property value based on given attrValue.
	 *
	 * @since ??
	 *
	 * @param array $attr_value       The value (breakpoint > state > value) of module attribute.
	 * @param array $filter_functions The map of attribute keys and CSS function names.
	 *
	 * @return string
	 */
	public static function value( array $attr_value, array $filter_functions ): string {
		$filter_value = [];

		foreach ( $filter_functions as $attr_key => $function_name ) {
			if ( ! isset( $attr_value[ $attr_key ] ) ) {
				continue;
			}

			$function_value = $attr_value[ $attr_key ];

			if ( ! $function_value ) {
				continue;
			}

			$filter_value[] = $function_name . '(' . $function_value . ')';
		}

		return implode( ' ', $filter_value );
	}

	/**
	 * Get Filter's CSS declaration based on given attrValue.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/style-library/filters-style-declaration filtersStyleDeclaration} in:
	 * `@divi/style-library` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array      $attrValue  The value (breakpoint > state > value) of module attribute.
	 *     @type bool|array $important  Optional. Whether to add `!important` tag. Default `false`.
	 *     @type string     $returnType Optional. This is the type of value that the function will return.
	 *                                  Can be either string or key_value_pair. Default `string`.
	 *     @type array      $attr       Optional. The full attribute object for inheritance processing. Default `[]`.
	 *     @type string     $breakpoint Optional. Current breakpoint for inheritance processing. Default `desktop`.
	 *     @type string     $state      Optional. Current state for inheritance processing (`value`, `hover`, etc). Default `value`.
	 * }
	 *
	 * @return array|string
	 */
	public static function style_declaration( array $args ) {
		$args = wp_parse_args(
			$args,
			[
				'important'  => false,
				'returnType' => 'string',
				'breakpoint' => 'desktop',
				'state'      => 'value',
			]
		);

		$attr_value  = $args['attrValue'];
		$important   = $args['important'];
		$return_type = $args['returnType'];
		$attr        = $args['attr'] ?? [];
		$breakpoint  = $args['breakpoint'];
		$state       = $args['state'];

		// Use ModuleUtils::use_attr_value() for proper state inheritance.
		// Hover-state output is generated from the resolved state value in this shared declaration.
		// This follows the established codebase pattern and handles all edge cases internally.
		// The utility will return defaultValue when inheritance is not applicable or available.
		$final_attr_value = ModuleUtils::use_attr_value(
			[
				'attr'         => $attr,
				'breakpoint'   => $breakpoint,
				'state'        => $state,
				'mode'         => 'getAndInheritAll',
				'defaultValue' => $attr_value,
			]
		);

		$style_declarations = new StyleDeclarations(
			[
				'important'  => $important,
				'returnType' => $return_type,
			]
		);

		$filter_declaration = self::value(
			$final_attr_value,
			[
				'hueRotate'  => 'hue-rotate',
				'saturate'   => 'saturate',
				'brightness' => 'brightness',
				'contrast'   => 'contrast',
				'invert'     => 'invert',
				'sepia'      => 'sepia',
				'opacity'    => 'opacity',
				'blur'       => 'blur',
			]
		);
		$backdrop_filter_declaration = self::value(
			$final_attr_value,
			[
				'backdropBlur'   => 'blur',
				'backdropInvert' => 'invert',
				'backdropSepia'  => 'sepia',
			]
		);

		if ( $filter_declaration ) {
			$style_declarations->add( 'filter', $filter_declaration );
		}

		if ( $backdrop_filter_declaration ) {
			$style_declarations->add( 'backdrop-filter', $backdrop_filter_declaration );
			$style_declarations->add( '-webkit-backdrop-filter', $backdrop_filter_declaration );
		}

		if ( isset( $final_attr_value['blendMode'] ) ) {
			$style_declarations->add( 'mix-blend-mode', $final_attr_value['blendMode'] );
		}

		return $style_declarations->value();
	}
}
