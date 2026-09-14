<?php
/**
 * Module Library: MapItem Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\MapItem;

use ET\Builder\Packages\Module\Options\Loop\LoopPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Meta\MetaPresetAttrsMap;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


/**
 * Class MapItemPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\MapItem
 */
class MapItemPresetAttrsMap {
	/**
	 * Get the preset attributes map for the MapItem module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/map-pin' !== $module_name ) {
			return $map;
		}

		$static_attrs = [
			'title.innerContent'   => [
				'attrName' => 'title.innerContent',
				'preset'   => 'content',
			],
			'content.innerContent' => [
				'attrName' => 'content.innerContent',
				'preset'   => [ 'style' ],
			],
			'pin.innerContent'     => [
				'attrName' => 'pin.innerContent',
				'preset'   => 'content',
			],
		];

		$loop_preset_attrs = LoopPresetAttrsMap::get_map( 'pin.advanced.loop' );
		$meta_preset_attrs = MetaPresetAttrsMap::get_map( 'pin.meta' );

		return array_merge( $static_attrs, $loop_preset_attrs, $meta_preset_attrs );
	}
}
