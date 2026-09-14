<?php
/**
 * Module Library: Icon List Module Text Style
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
 * TextStyle class.
 *
 * This class provides style declaration functionality for Icon List text orientation alignment.
 * Text alignment is applied to `.et_pb_icon_list_text` using `text-align` so inline HTML
 * preserves normal whitespace; flexbox `justify-content` is not used on that element.
 * This handles module.advanced.text.text.desktop.value.orientation attributes.
 *
 * @since ??
 */
class TextStyle {

	/**
	 * Generate CSS for Icon List text orientation alignment.
	 *
	 * Maps text orientation values to text-align declarations.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     Parameters for the text orientation CSS declaration.
	 *
	 *     @type array $attrValue The text attribute value containing orientation.
	 * }
	 *
	 * @return string The CSS for text orientation alignment.
	 */
	public static function text_orientation_declaration( array $params ): string {
		$text_attr = $params['attrValue'];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		// Extract orientation value from text attribute.
		$orientation = isset( $text_attr['orientation'] ) ? $text_attr['orientation'] : null;

		if ( $orientation ) {
			switch ( $orientation ) {
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
}
