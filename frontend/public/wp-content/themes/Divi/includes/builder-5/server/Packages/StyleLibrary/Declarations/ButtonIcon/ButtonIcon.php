<?php
/**
 * ButtonIcon class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\StyleLibrary\Declarations\ButtonIcon;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\IconLibrary\IconFont\Utils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;

/**
 * ButtonIcon is a helper class for working with ButtonIcon style declaration.
 *
 * @since ??
 */
class ButtonIcon {
	/**
	 * Get Button Icon's CSS declaration based on given attrValue.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/style-library/button-icon-style-declaration buttonIconStyleDeclaration} in:
	 * `@divi/style-library` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array      $attrValue  The value (breakpoint > state > value) of module attribute.
	 *                                  Note if `icon` key is not set, an empty string is returned.
	 *     @type bool|array $important  Optional. Whether to add `!important` tag. Default `false`.
	 *     @type string     $returnType This is the type of value that the function will return.
	 *                                  Can be either `string` or `key_value_pair`. Default `string`.
	 *     @type string     $breakpoint Optional. The breakpoint name (desktop, tablet, phone, or custom breakpoint). Default `desktop`.
	 * }
	 *
	 * @return array|string
	 */
	public static function style_declaration( array $args ) {
		if ( ! isset( $args['attrValue']['icon'] ) ) {
			return '';
		}

		$args = wp_parse_args(
			$args,
			[
				'important'  => false,
				'returnType' => 'string',
				'breakpoint' => 'desktop',
			]
		);

		$attr_value         = $args['attrValue'];
		$default_attr_value = $args['defaultAttrValue'] ?? [];
		$return_type        = $args['returnType'];
		$breakpoint         = $args['breakpoint'];
		$full_attr          = $args['attr'] ?? [];

		// Fall back to the value state's enable when not present in the current state (e.g. hover).
		// Users often only store changed values in non-value states (e.g. only color), omitting enable.
		$enable = $attr_value['icon']['enable']
			?? $default_attr_value['icon']['enable']
			?? $full_attr[ $breakpoint ]['value']['icon']['enable']
			?? $full_attr['desktop']['value']['icon']['enable']
			?? null;

		$settings           = $attr_value['icon']['settings']
			?? $default_attr_value['icon']['settings']
			?? $full_attr[ $breakpoint ]['value']['icon']['settings']
			?? $full_attr['desktop']['value']['icon']['settings']
			?? [];
		$color              = $attr_value['icon']['color'] ?? $default_attr_value['icon']['color'] ?? null;
		$on_hover           = $attr_value['icon']['onHover'] ?? $default_attr_value['icon']['onHover'] ?? null;
		$placement          = $attr_value['icon']['placement'] ?? $default_attr_value['icon']['placement'] ?? 'right';
		$icon_value         = $settings ? Utils::escape_font_icon( Utils::process_font_icon( $settings ) ) : '';
		$has_custom_styles  = ! empty( $icon_value ) || ! empty( $color ) || 'left' === $placement || 'off' === $on_hover;
		$always_important   = [
			'font-family' => true,
			'font-weight' => true,
			'font-size'   => (bool) ( $settings['unicode'] ?? false ),
			'line-height' => true,
			'margin-left' => true,
		];
		$important          = $args['important'];

		// Keep baseline button icon styling in theme CSS.
		// Emit module CSS only when icon settings deviate from defaults.
		// Exception: default enabled icons still need relative font-size so they scale with button text
		// (theme baseline hardcodes 32px which breaks at non-default button font sizes).
		if ( 'on' !== $enable ) {
			return '';
		}

		if ( ! $has_custom_styles ) {
			$style_declarations = new StyleDeclarations(
				[
					'important'  => false,
					'returnType' => $return_type,
				]
			);
			$style_declarations->add( 'font-size', '1.6em' );

			return $style_declarations->value();
		}

		$is_responsive_breakpoint = 'desktop' !== $breakpoint;

		$style_declarations  = new StyleDeclarations(
			[
				'important'  => is_bool( $important ) ? array_merge(
					$always_important,
					[
						'content' => $is_responsive_breakpoint ? true : $important,
						'display' => $important,
						'color'   => $important,
						'opacity' => $important,
						'left'    => $important,
						'right'   => $important,
					]
				) : array_merge(
					$always_important,
					$important,
					$is_responsive_breakpoint ? [ 'content' => true ] : []
				),
				'returnType' => $return_type,
			]
		);
		$should_process_icon = 'on' === $enable || $is_responsive_breakpoint;

		if ( $should_process_icon ) {
			$weight = isset( $settings['weight'] ) ? $settings['weight'] : '400';

			// Always add font-family and font-weight when icon is enabled, even if no icon is selected.
			$font_family = $settings && Utils::is_fa_icon( $settings ) ? 'FontAwesome' : 'ETmodules';
			$style_declarations->add( 'font-family', "\"{$font_family}\"" );
			$style_declarations->add( 'font-weight', $weight );

			// Use data attributes for responsive breakpoints; desktop uses data-icon when settings exist.
			if ( $is_responsive_breakpoint ) {
				// Convert camelCase breakpoint name to kebab-case for data attribute (e.g., phoneWide -> phone-wide).
				$data_attr_name = 'data-icon-' . strtolower( preg_replace( '/([A-Z])/', '-$1', $breakpoint ) );
				$content_value  = "attr({$data_attr_name})";
				$style_declarations->add( 'content', $content_value );
			} elseif ( $settings && ! empty( $icon_value ) ) {
				$style_declarations->add( 'content', 'attr(data-icon)' );
			}

			// Always add these properties when icon is enabled.
			$style_declarations->add( 'font-size', 'inherit' );
			$style_declarations->add( 'line-height', '1.7em' );
			$style_declarations->add( 'display', 'inline-block' );

			if ( empty( $icon_value ) ) {
				$style_declarations->add( 'font-size', '1.6em' );
			}
		} else {
			$style_declarations->add( 'font-size', '1.6em' );
		}

		// Add margin-left based on icon placement to position the icon correctly.
		if ( 'left' === $placement ) {
			$style_declarations->add( 'margin-left', '-1.3em' );
		} elseif ( ! empty( $settings['unicode'] ) ) {
			$style_declarations->add( 'margin-left', '0.3em' );
		}

		if ( $color ) {
			$style_declarations->add( 'color', $color );
		}

		if ( 'off' === $on_hover ) {
			$style_declarations->add( 'opacity', '1' );
		}

		return $style_declarations->value();
	}

	/**
	 * Get Button Icon's Hover CSS declaration based on given placement.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/style-library/button-icon-hover-style-declaration buttonIconHoverStyleDeclaration} in:
	 * `@divi/style-library` package. buttonIconHoverStyleDeclaration located in:
	 * visual-builder/packages/style-library/src/declarations/button-icon/index.ts.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array      $attrValue  The value (breakpoint > state > value) of module attribute.
	 *     @type bool|array $important  Optional. Whether to add `!important` tag. Default `false`.
	 *     @type string     $returnType This is the type of value that the function will return.
	 *                                  Can be either `string` or `key_value_pair`. Default `string`.
	 * }
	 *
	 * @return array|string
	 */
	public static function hover_style_declaration( array $args ) {
		$args = wp_parse_args(
			$args,
			[
				'returnType' => 'string',
			]
		);

		$attr_value         = $args['attrValue'];
		$default_attr_value = $args['defaultAttrValue'] ?? [];
		$return_type        = $args['returnType'];
		$enable             = $attr_value['icon']['enable'] ?? $default_attr_value['icon']['enable'] ?? null;
		$placement          = $attr_value['icon']['placement'] ?? $default_attr_value['icon']['placement'] ?? 'right';
		$on_hover           = $attr_value['icon']['onHover'] ?? $default_attr_value['icon']['onHover'] ?? null;

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => $return_type,
			]
		);

		// Left icon placement uses :before and needs explicit hover opacity.
		if ( 'on' === $enable && 'off' !== $on_hover && 'left' === $placement ) {
			$style_declarations->add( 'opacity', '1' );
		}

		return $style_declarations->value();
	}

	/**
	 * Hide Button Right Icon only if the placement is set to the left.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/style-library/button-right-icon-style-declaration buttonRightIconStyleDeclaration} in:
	 * `@divi/style-library` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array      $attrValue  The value (breakpoint > state > value) of module attribute.
	 *     @type bool|array $important  Optional. Whether to add `!important` tag. Default `false`.
	 *     @type string     $returnType This is the type of value that the function will return.
	 *                                  Can be either `string` or `key_value_pair`. Default `string`.
	 * }
	 *
	 * @return array|string
	 */
	public static function right_style_declaration( array $args ) {
		$args = wp_parse_args(
			$args,
			[
				'important'  => false,
				'returnType' => 'string',
			]
		);

		$attr_value         = $args['attrValue'];
		$default_attr_value = $args['defaultAttrValue'] ?? [];
		$return_type        = $args['returnType'];
		$enable             = $attr_value['icon']['enable'] ?? $default_attr_value['icon']['enable'] ?? null;
		$placement          = $attr_value['icon']['placement'] ?? $default_attr_value['icon']['placement'] ?? null;
		$important          = $args['important'];

		$style_declarations = new StyleDeclarations(
			[
				'important'  => $important,
				'returnType' => $return_type,
			]
		);

		if ( 'on' === $enable && 'left' === $placement ) {
			$style_declarations->add( 'display', 'none' );
		}

		return $style_declarations->value();
	}

	/**
	 * Disable the icon if `Show Button Icon` is set to the `false`.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/style-library/disable-button-icon-style-declaration disableButtonIconStyleDeclaration} in:
	 * `@divi/style-library` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array      $attrValue  The value (breakpoint > state > value) of module attribute.
	 *     @type bool|array $important  Whether to add `!important` tag. Default `false`.
	 *     @type string     $returnType This is the type of value that the function will return.'
	 *                                  Can be either `string` or `key_value_pair`. Default `string`.
	 * }
	 *
	 * @return string|array
	 */
	public static function disable_style_declaration( array $args ) {
		$args = wp_parse_args(
			$args,
			[
				'important'  => false,
				'returnType' => 'string',
			]
		);

		$attr_value         = $args['attrValue'];
		$default_attr_value = $args['defaultAttrValue'] ?? [];
		$return_type        = $args['returnType'];
		$enable             = $attr_value['icon']['enable'] ?? $default_attr_value['icon']['enable'] ?? null;

		$style_declarations = new StyleDeclarations(
			[
				'important'  => true,
				'returnType' => $return_type,
			]
		);

		if ( 'off' === $enable ) {
			$style_declarations->add( 'display', 'none' );
		}

		return $style_declarations->value();
	}
}
