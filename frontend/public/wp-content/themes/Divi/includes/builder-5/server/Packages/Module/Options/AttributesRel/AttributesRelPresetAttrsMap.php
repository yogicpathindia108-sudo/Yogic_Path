<?php
/**
 * Module: AttributesRelPresetAttrsMap class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\AttributesRel;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * AttributesRelPresetAttrsMap class.
 *
 * Handle legacy button rel attributes.
 *
 * @since ??
 */
class AttributesRelPresetAttrsMap {
	/**
	 * Get the map for the attributes preset attributes.
	 *
	 * @since ??
	 *
	 * @param string $attr_name The attribute name.
	 *
	 * @return array The map for the attributes preset attributes.
	 */
	public static function get_map( string $attr_name ) {
		return [
			"{$attr_name}__rel" => [
				'attrName' => $attr_name,
				'preset'   => [ 'html' ],
				'subName'  => 'rel',
			],
		];
	}
}
