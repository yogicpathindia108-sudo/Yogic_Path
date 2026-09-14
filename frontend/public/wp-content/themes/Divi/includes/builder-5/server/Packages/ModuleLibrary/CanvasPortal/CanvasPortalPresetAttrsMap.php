<?php
/**
 * Module Library: Canvas Portal Module Preset Attributes Map
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\CanvasPortal;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Class CanvasPortalPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\CanvasPortal
 */
class CanvasPortalPresetAttrsMap {
	/**
	 * Get the preset attributes map for the Canvas Portal module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/canvas-portal' !== $module_name ) {
			return $map;
		}

		return $map;
	}
}
