<?php
/**
 * Module Library: Icon List Item Module Preset Attributes Map
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\IconListItem;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Class IconListItemPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\IconListItem
 */
class IconListItemPresetAttrsMap {
	/**
	 * Get the preset attributes map for the Icon List Item module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, $module_name ) {
		if ( 'divi/icon-list-item' !== $module_name ) {
			return $map;
		}

		return $map;
	}
}
