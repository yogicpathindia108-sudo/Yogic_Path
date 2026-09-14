<?php
/**
 * Module Library: Icon List Module Preset Attributes Map
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\IconList;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Class IconListPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\IconList
 */
class IconListPresetAttrsMap {
	/**
	 * Get the preset attributes map for the Icon List module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, $module_name ) {
		if ( 'divi/icon-list' !== $module_name ) {
			return $map;
		}

		return $map;
	}
}
