<?php
/**
 * Shared CSS property keys for checkbox/radio option label text-decoration routing.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Label text-decoration font property keys for FormFieldStyle propertySelectors.
 *
 * @since ??
 */
class LabelTextDecorationFontProperties {

	/**
	 * CSS properties that must target option label text, not the custom icon.
	 *
	 * @var string[]
	 */
	public const CSS_PROPERTIES = [
		'text-decoration-line',
		'text-decoration-color',
		'text-decoration-style',
		'text-decoration-thickness',
		'text-underline-offset',
		'font-variant',
		'font-variant-caps',
	];
}
