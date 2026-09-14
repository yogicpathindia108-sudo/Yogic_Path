<?php
/**
 * Module Library: Icon List Module Font Style
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\IconList\Styles;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;

/**
 * FontStyle class.
 *
 * This class provides style declaration functionality for Icon List text alignment.
 * Declarations use `text-align` on `.et_pb_icon_list_text` so inline HTML keeps spaces;
 * flexbox `justify-content` is not used on that element.
 *
 * @since ??
 */
class FontStyle {

	/**
	 * Generate CSS for Icon List text alignment.
	 *
	 * Maps font textAlign values to text-align declarations.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     Parameters for the text alignment CSS declaration.
	 *
	 *     @type array $attrValue The font attribute value containing textAlign.
	 * }
	 *
	 * @return string The CSS for text alignment.
	 */
	public static function text_alignment_declaration( array $params ): string {
		$font_attr = $params['attrValue'];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		// Extract textAlign value from font attribute.
		$text_align = isset( $font_attr['textAlign'] ) ? $font_attr['textAlign'] : null;

		if ( $text_align ) {
			switch ( $text_align ) {
				case 'left':
					$style_declarations->add( 'text-align', 'left' );
					break;
				case 'center':
					$style_declarations->add( 'text-align', 'center' );
					break;
				case 'right':
					$style_declarations->add( 'text-align', 'right' );
					break;
				case 'justify':
					$style_declarations->add( 'text-align', 'justify' );
					break;
				default:
					$style_declarations->add( 'text-align', 'left' );
					break;
			}
		}

		return $style_declarations->value();
	}

	/**
	 * Generate CSS so list item text descendants inherit Body Text color.
	 *
	 * Hyperlinks inside `.et_pb_icon_list_text` otherwise use the theme's global
	 * anchor color instead of the module's Body Text color on the text wrapper.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     Parameters for the text color inherit CSS declaration.
	 *
	 *     @type array $attrValue Unused; declaration is always emitted.
	 * }
	 *
	 * @return string The CSS for descendant color inheritance.
	 */
	public static function text_color_inherit_declaration( array $params ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature matches declarationFunction contract.
		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		$style_declarations->add( 'color', 'inherit' );

		return $style_declarations->value();
	}
}
