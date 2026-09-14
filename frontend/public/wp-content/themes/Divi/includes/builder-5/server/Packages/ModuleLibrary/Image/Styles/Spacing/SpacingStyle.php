<?php
/**
 * Module Library: Image Module Spacing Style
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Image\Styles\Spacing;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * `SpacingStyle` class.
 *
 * Image spacing style wrapper. Declaration logic delegates to StyleLibrary Spacing.
 *
 * @since ??
 */
class SpacingStyle {

	use SpacingStyleTraits\StyleTrait;
	use SpacingStyleTraits\StyleDeclarationTrait;
}
