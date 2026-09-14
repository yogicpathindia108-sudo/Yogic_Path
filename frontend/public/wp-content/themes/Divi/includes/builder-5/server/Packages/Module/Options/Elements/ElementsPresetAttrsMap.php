<?php
/**
 * Module: ElementsPresetAttrsMap class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * ElementsPresetAttrsMap class.
 *
 * This class provides the static map for the elements preset attributes.
 *
 * @since ??
 */
class ElementsPresetAttrsMap {
	/**
	 * Get the map for the elements preset attributes.
	 *
	 * @since ??
	 *
	 * @param string $attr_name The attribute name.
	 *
	 * @return array The map for the elements preset attributes.
	 */
	public static function get_map( string $attr_name ) {
		return [
			"{$attr_name}.structure" => [
				'attrName' => "{$attr_name}.structure",
				'preset'   => 'content',
			],
		];
	}
}
