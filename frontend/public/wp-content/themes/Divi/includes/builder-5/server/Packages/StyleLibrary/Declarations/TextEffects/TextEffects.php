<?php
/**
 * TextEffects class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\StyleLibrary\Declarations\TextEffects;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\StyleLibrary\Utils\GlobalVariableReferenceUtils;
use ET\Builder\Packages\StyleLibrary\Declarations\Background\Background;
use ET\Builder\Packages\StyleLibrary\Declarations\Background\Utils\BackgroundStyleUtils;
use ET\Builder\Packages\StyleLibrary\Utils\GradientUtils;
use ET\Builder\Packages\StyleLibrary\Utils\Utils;

/**
 * TextEffects class.
 *
 * This class provides functionality to work with text effects styles.
 *
 * @since ??
 */
class TextEffects {

	/**
	 * Determine whether a gradient stops string is a valid global gradient reference.
	 *
	 * @since ??
	 *
	 * @param mixed $gradient_stops Gradient stops candidate.
	 *
	 * @return bool
	 */
	private static function _is_gradient_variable_stops_reference( $gradient_stops ): bool {
		if ( ! is_string( $gradient_stops ) || '' === $gradient_stops ) {
			return false;
		}

		if ( '' !== GlobalVariableReferenceUtils::sanitize_css_reference( $gradient_stops, 'gvid' ) ) {
			return true;
		}

		if ( '' !== GlobalVariableReferenceUtils::sanitize_variable_id( $gradient_stops, 'gvid' ) ) {
			return true;
		}

		if ( str_starts_with( $gradient_stops, '$variable(' ) && '$' === substr( $gradient_stops, -1 ) ) {
			$variable_content = substr( $gradient_stops, 10, -2 );
			$variable_data    = json_decode( $variable_content, true );
			$variable_name    = $variable_data['value']['name'] ?? '';

			return is_array( $variable_data )
				&& 'gradient' === ( $variable_data['type'] ?? '' )
				&& is_string( $variable_name )
				&& '' !== GlobalVariableReferenceUtils::sanitize_variable_id( $variable_name, 'gvid' );
		}

		return false;
	}

	/**
	 * Determine whether gradient stops can render into real gradient CSS.
	 *
	 * @since ??
	 *
	 * @param mixed $gradient_stops Gradient stops candidate.
	 *
	 * @return bool
	 */
	private static function _has_renderable_gradient_stops( $gradient_stops ): bool {
		if ( is_array( $gradient_stops ) ) {
			return count( $gradient_stops ) >= 2;
		}

		if ( ! is_string( $gradient_stops ) || '' === $gradient_stops ) {
			return false;
		}

		if ( self::_is_gradient_variable_stops_reference( $gradient_stops ) ) {
			return true;
		}

		$resolved_gradient = GlobalData::resolve_global_gradient_variable( $gradient_stops );
		$resolved_stops    = $resolved_gradient['stops'] ?? null;

		return is_array( $resolved_stops ) && count( $resolved_stops ) >= 2;
	}

	/**
	 * Format image fill URL into CSS background-image value.
	 *
	 * @since ??
	 *
	 * @param mixed $image_url Raw image URL value.
	 *
	 * @return string
	 */
	private static function _get_image_fill_background_image( $image_url ): string {
		$image_url = Utils::resolve_and_sanitize_css_scalar_value( $image_url );

		if ( '' === $image_url ) {
			return '';
		}

		if ( str_contains( $image_url, 'var(' ) ) {
			return GlobalVariableReferenceUtils::sanitize_css_reference( $image_url, 'gvid' );
		}

		return Utils::sanitize_non_variable_image_url_css_reference( $image_url );
	}

	/**
	 * Get text effects CSS declaration based on given arguments.
	 *
	 * This function accepts an array of arguments that define the style declaration.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments that define the style declaration.
	 *
	 *     @type array       $attrValue   The value (breakpoint > state > value) of module attribute.
	 *     @type array|bool  $important   Whether to add `!important` to the CSS.
	 *     @type string      $returnType  Optional. The return type of the style declaration. Default `string`.
	 *                                    One of `string`, or `key_value_pair`.
	 * }
	 *
	 * @return array|string The generated text effects CSS style declaration.
	 */
	public static function style_declaration( array $args ) {
		$attr_value         = $args['attrValue'] ?? [];
		$default_attr_value = $args['defaultAttrValue'] ?? [];
		$important          = $args['important'] ?? false;
		$return_type        = $args['returnType'] ?? 'string';
		$breakpoint         = $args['breakpoint'] ?? null;
		$state              = $args['state'] ?? 'value';
		$merged_attr_value  = array_merge( $default_attr_value, $attr_value );

		$merged_attr_value['gradient']  = array_merge(
			$default_attr_value['gradient'] ?? [],
			$attr_value['gradient'] ?? []
		);
		$merged_attr_value['imageFill'] = array_merge(
			$default_attr_value['imageFill'] ?? [],
			$attr_value['imageFill'] ?? []
		);

		$style_declarations = new StyleDeclarations(
			[
				'important'  => $important,
				'returnType' => $return_type,
			]
		);

		$stroke_width         = $merged_attr_value['strokeWidth'] ?? '';
		$stroke_color         = $merged_attr_value['strokeColor'] ?? '';
		$stroke_position      = $merged_attr_value['strokePosition'] ?? '';
		$fill_type            = $merged_attr_value['fillType'] ?? null;
		$has_explicit_fill    = array_key_exists( 'fillType', $attr_value );
		$raw_current_gradient = is_array( $attr_value['gradient'] ?? null ) ? $attr_value['gradient'] : [];
		$attr                 = $args['attr'] ?? null;
		$inherited_fill_type  = null;

		if ( is_array( $attr ) && is_string( $breakpoint ) ) {
			$inherited_fill_type = ModuleUtils::get_attr_subname_value(
				[
					'attr'         => $attr,
					'subname'      => 'fillType',
					'breakpoint'   => $breakpoint,
					'state'        => $state,
					'mode'         => 'getAndInheritAll',
					'defaultValue' => null,
				]
			);
		}

		if ( is_string( $inherited_fill_type ) && '' !== $inherited_fill_type ) {
			$fill_type = $inherited_fill_type;
		}

		$fill_type = is_string( $fill_type ) && '' !== $fill_type ? $fill_type : 'none';

		if ( '' !== $stroke_width ) {
			$style_declarations->add( '-webkit-text-stroke-width', $stroke_width );
		}

		if ( '' !== $stroke_color ) {
			$style_declarations->add( '-webkit-text-stroke-color', $stroke_color );
		}

		if ( '' !== $stroke_position ) {
			$style_declarations->add( 'paint-order', 'stroke-fill' === $stroke_position ? 'stroke' : 'fill' );
		} elseif ( '' !== $stroke_width ) {
			$normalized_stroke_width = strtolower( trim( (string) $stroke_width ) );
			$parsed_stroke_width     = floatval( $normalized_stroke_width );

			if ( $parsed_stroke_width > 1 ) {
				$style_declarations->add( 'paint-order', 'stroke' );
			}
		}

		if ( 'transparent' === $fill_type ) {
			$style_declarations->add( 'background-image', 'none' );
			$style_declarations->add( '-webkit-text-fill-color', 'transparent' );
		} elseif ( 'gradient' === $fill_type ) {
			$gradient           = GradientUtils::get_effective_gradient_for_breakpoint(
				[
					'attr'              => $attr,
					'breakpoint'        => $breakpoint,
					'state'             => $state,
					'defaultGradient'   => $default_attr_value['gradient'] ?? [],
					'currentGradient'   => $raw_current_gradient,
					'gradientSubFields' => [
						'enabled',
						'stops',
						'type',
						'direction',
						'directionRadial',
						'repeat',
						'length',
					],
				]
			);
			$gradient_stops     = $gradient['stops'] ?? [];
			$has_gradient_stops = self::_has_renderable_gradient_stops( $gradient_stops );

			if ( $has_gradient_stops ) {
				$gradient_css = Background::gradient_style_declaration(
					[
						'type'            => $gradient['type'] ?? 'linear',
						'direction'       => $gradient['direction'] ?? '180deg',
						'directionRadial' => $gradient['directionRadial'] ?? 'center',
						'stops'           => $gradient_stops,
						'repeat'          => $gradient['repeat'] ?? 'off',
						'length'          => $gradient['length'] ?? '100%',
					]
				);

				$style_declarations->add( 'background-image', $gradient_css );
				$style_declarations->add( 'background-repeat', 'no-repeat' );
				$style_declarations->add( '-webkit-background-clip', 'text' );
				$style_declarations->add( 'background-clip', 'text' );
				$style_declarations->add( '-webkit-text-fill-color', 'transparent' );
			}
		} elseif ( 'image' === $fill_type ) {
			$default_image_fill = Background::$background_default_attr['image'] ?? [];
			$image_fill         = $merged_attr_value['imageFill'] ?? [];
			$image_fill         = array_merge( $default_image_fill, $image_fill );
			$image_url          = $image_fill['url'] ?? '';
			$background_image   = self::_get_image_fill_background_image( $image_url );

			if ( '' !== $background_image ) {
				$image_size     = $image_fill['size'] ?? '';
				$image_width    = $image_fill['width'] ?? '';
				$image_height   = $image_fill['height'] ?? '';
				$image_position = $image_fill['position'] ?? 'center';
				$image_h_offset = $image_fill['horizontalOffset'] ?? '0%';
				$image_v_offset = $image_fill['verticalOffset'] ?? '0%';
				$image_repeat   = $image_fill['repeat'] ?? 'no-repeat';
				$image_blend    = $image_fill['blend'] ?? '';
				$default_blend  = $default_image_fill['blend'] ?? '';

				$style_declarations->add( 'background-image', $background_image );
				$style_declarations->add( 'background-size', BackgroundStyleUtils::get_background_size_css( $image_size, $image_width, $image_height, 'image' ) );
				$style_declarations->add( 'background-position', BackgroundStyleUtils::get_background_position_css( $image_position, $image_h_offset, $image_v_offset ) );
				$style_declarations->add( 'background-repeat', $image_repeat );
				if ( '' !== $image_blend && $default_blend !== $image_blend ) {
					$style_declarations->add( 'background-blend-mode', $image_blend );
				}
				$style_declarations->add( '-webkit-background-clip', 'text' );
				$style_declarations->add( 'background-clip', 'text' );
				$style_declarations->add( '-webkit-text-fill-color', 'transparent' );
			}
		} elseif ( $has_explicit_fill ) {
			$style_declarations->add( 'background-image', 'none' );
			$style_declarations->add( '-webkit-background-clip', 'border-box' );
			$style_declarations->add( 'background-clip', 'border-box' );
			$style_declarations->add( '-webkit-text-fill-color', 'initial' );
		}

		return $style_declarations->value();
	}
}
