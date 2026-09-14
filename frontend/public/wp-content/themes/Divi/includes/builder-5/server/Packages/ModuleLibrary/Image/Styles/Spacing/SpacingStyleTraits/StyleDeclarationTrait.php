<?php
/**
 * Module Library: Image Module Spacing Style Declaration Trait
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Image\Styles\Spacing\SpacingStyleTraits;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\StyleLibrary\Declarations\Spacing\Spacing;

trait StyleDeclarationTrait {

	/**
	 * Get Spacing's CSS declaration based on given attrValue.
	 *
	 * Delegates to StyleLibrary Spacing — do not duplicate declaration logic here.
	 *
	 * @since ??
	 *
	 * @param array $args An array of arguments.
	 *
	 * @return string The string containing the CSS style declarations for the spacing.
	 */
	public static function style_declaration( array $args ): string {
		return Spacing::style_declaration( $args );
	}
}
