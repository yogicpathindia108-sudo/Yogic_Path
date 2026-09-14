<?php
/**
 * Module Library: Icon List Icon Font Size Style
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\IconList\Styles;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;

/**
 * IconFontSizeStyle class.
 *
 * Emits `font-size` for Icon List / Icon List Item icon size attrs.
 * Accepts canonical string values and legacy `{useSize, size}` objects.
 *
 * @since ??
 */
class IconFontSizeStyle {

	/**
	 * Generate CSS font-size for Icon List icon size attrs.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     Parameters for the icon font-size CSS declaration.
	 *
	 *     @type string|array $attrValue Icon size attribute value.
	 * }
	 *
	 * @return string The CSS for icon font-size.
	 */
	public static function icon_font_size_declaration( array $params ): string {
		$attr_value = $params['attrValue'] ?? null;
		$size       = '';

		if ( is_string( $attr_value ) ) {
			$size = $attr_value;
		} elseif ( is_array( $attr_value ) ) {
			$use_size = $attr_value['useSize'] ?? '';
			$size_val = $attr_value['size'] ?? '';

			if ( 'on' === $use_size && is_string( $size_val ) && '' !== $size_val ) {
				$size = $size_val;
			}
		}

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => $params['important'] ?? false,
			]
		);

		if ( '' !== $size ) {
			$resolved_size = GlobalData::resolve_global_variable_value( $size );

			if ( is_string( $resolved_size ) && '' !== $resolved_size ) {
				$style_declarations->add( 'font-size', $resolved_size );
			}
		}

		return $style_declarations->value();
	}
}
