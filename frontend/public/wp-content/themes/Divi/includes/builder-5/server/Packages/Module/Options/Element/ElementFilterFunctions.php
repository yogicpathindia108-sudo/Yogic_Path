<?php
/**
 * Module: ElementFilterFunctions class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Element;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * ElementFilterFunctions class.
 *
 * This class provides common filter functions for elements and a filter function map,
 * intended for backward compatibility in type-based element attribute filtering.
 *
 * @since ??
 */
class ElementFilterFunctions {
	/**
	 * Returns button element attrs.
	 *
	 * @param array $attrs The attributes of the element.
	 *
	 * @return array The filtered attributes of the element.
	 */
	public static function button_type_attrs( array $attrs ): array {
		return $attrs;
	}

	/**
	 * Map of filter functions for element attribute.
	 *
	 * @var array $filter_function_map {
	 *     @type string $button Button type attributes filter function.
	 * }
	 *
	 * @since ??
	 */
	public static $filter_function_map = [
		'button' => self::class . '::button_type_attrs',
	];
}
