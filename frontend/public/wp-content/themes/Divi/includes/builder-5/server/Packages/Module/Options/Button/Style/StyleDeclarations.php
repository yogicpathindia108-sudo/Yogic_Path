<?php
/**
 * Module: StyleDeclarations class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Button\Style;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations as UtilsStyleDeclarations;


/**
 * StyleDeclarations class.
 *
 * This class contains functionality for working with style declarations for spacing and icon styles.
 *
 * @since ??
 */
class StyleDeclarations {
	/**
	 * Retrieve the style declarations for the spacing icon.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/SpacingIconStyleDeclaration spacingIconStyleDeclaration} in
	 * `@divi/module` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array $attrValue The value (breakpoint > state > value) of the module attribute.
	 * }
	 *
	 * @return string The generated style declarations.
	 *
	 * @example:
	 * ```php
	 *      $args = [
	 *          'attrValue' => [
	 *              'icon' => [
	 *                  'placement' => 'left',
	 *                  'onHover' => 'off',
	 *                  'enable' => 'on',
	 *              ],
	 *              'padding' => [],
	 *          ],
	 *      ];
	 *
	 *      $styleDeclarations = StyleDeclarations::spacing_icon_style_declaration( $args );
	 * ```
	 */
	public static function spacing_icon_style_declaration( array $args ): string {
		$style_declarations = new UtilsStyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => [
					'padding-left'  => true,
					'padding-right' => true,
				],
			]
		);

		$placement = $args['attrValue']['icon']['placement'] ?? $args['defaultAttrValue']['icon']['placement'] ?? null;
		$on_hover  = $args['attrValue']['icon']['onHover'] ?? $args['defaultAttrValue']['icon']['onHover'] ?? null;
		$enable    = $args['attrValue']['icon']['enable'] ?? $args['defaultAttrValue']['icon']['enable'] ?? null;
		$padding   = $args['attrValue']['padding'] ?? $args['defaultAttrValue']['padding'] ?? [];

		$is_button_icon_left   = 'left' === $placement;
		$current_right_padding = isset( $padding['right'] ) ? $padding['right'] : null;
		$current_left_padding  = isset( $padding['left'] ) ? $padding['left'] : null;

		if ( ! $current_right_padding && 'off' === $on_hover && 'on' === $enable ) {
			$style_declarations->add( 'padding-right', ! $is_button_icon_left ? '2em' : '0.7em' );
		}

		if ( ! $current_left_padding && 'off' === $on_hover && 'on' === $enable ) {
			$style_declarations->add( 'padding-left', ! $is_button_icon_left ? '0.7em' : '2em' );
		}

		return $style_declarations->value();
	}

	/**
	 * Retrieve the style declarations for the spacing icon hover.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/SpacingIconHoverStyleDeclaration spacingIconHoverStyleDeclaration} in
	 * `@divi/module` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array $attrValue The value (breakpoint > state > value) of the module attribute.
	 * }
	 *
	 * @return string The generated style declarations.
	 *
	 * @example:
	 * ```php
	 *      $args = [
	 *          'attrValue' => [
	 *              'icon' => [
	 *                  'placement' => 'left',
	 *                  'onHover' => 'off',
	 *                  'enable' => 'on',
	 *              ],
	 *              'padding' => [],
	 *          ],
	 *      ];
	 *
	 *      $styleDeclarations = StyleDeclarations::spacing_icon_hover_style_declaration( $args );
	 * ```
	 */
	public static function spacing_icon_hover_style_declaration( array $args ): string {
		$style_declarations = new UtilsStyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => [
					'padding'       => true,
					'padding-left'  => true,
					'padding-right' => true,
				],
			]
		);

		$placement = $args['attrValue']['icon']['placement'] ?? $args['defaultAttrValue']['icon']['placement'] ?? null;
		$on_hover  = $args['attrValue']['icon']['onHover'] ?? $args['defaultAttrValue']['icon']['onHover'] ?? null;
		$enable    = $args['attrValue']['icon']['enable'] ?? $args['defaultAttrValue']['icon']['enable'] ?? null;
		$padding   = $args['attrValue']['padding'] ?? $args['defaultAttrValue']['padding'] ?? [];

		$is_button_icon_left   = 'left' === $placement;
		$current_right_padding = isset( $padding['right'] ) ? $padding['right'] : null;
		$current_left_padding  = isset( $padding['left'] ) ? $padding['left'] : null;

		if ( ! $current_right_padding && $is_button_icon_left && ( 'on' === $on_hover || null === $on_hover ) && 'on' === $enable ) {
			$style_declarations->add( 'padding-right', ! $is_button_icon_left ? '2em' : '0.7em' );
		}

		if ( ! $current_left_padding && $is_button_icon_left && ( 'on' === $on_hover || null === $on_hover ) && 'on' === $enable ) {
			$style_declarations->add( 'padding-left', ! $is_button_icon_left ? '0.7em' : '2em' );
		}

		if ( 'off' === $enable ) {
			$desktop_padding = $args['attr']['desktop']['value']['padding'] ?? $args['defaultAttrValue']['padding'] ?? [];

			$effective_right_padding = $current_right_padding ?? ( $desktop_padding['right'] ?? null );
			$effective_left_padding  = $current_left_padding ?? ( $desktop_padding['left'] ?? null );

			// When icon is disabled, only normalize missing side paddings.
			// Avoid shorthand fallback so hover-top/bottom custom values are preserved.
			if ( ! $effective_right_padding ) {
				$style_declarations->add( 'padding-right', '1em' );
			}

			if ( ! $effective_left_padding ) {
				$style_declarations->add( 'padding-left', '1em' );
			}
		}

		return $style_declarations->value();
	}
}
