<?php
/**
 * Module: ImagePresetAttrsMap class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Image;

use ET\Builder\Packages\Module\Options\Border\BorderPresetAttrsMap;
use ET\Builder\Packages\Module\Options\BoxShadow\BoxShadowPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Fit\FitPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Filters\FiltersPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Sizing\SizingPresetAttrsMap;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * ImagePresetAttrsMap class.
 *
 * This class provides the static map for the image preset attributes.
 *
 * @since ??
 */
class ImagePresetAttrsMap {
	/**
	 * Get the map for the image preset attributes.
	 *
	 * @since ??
	 *
	 * @param string $attr_name The attribute name.
	 *
	 * @return array The map for the image preset attributes.
	 */
	public static function get_map( string $attr_name ) {
		$sizing_group     = SizingPresetAttrsMap::get_map( "{$attr_name}.decoration.sizing" );
		$border_group     = BorderPresetAttrsMap::get_map( "{$attr_name}.decoration.border" );
		$box_shadow_group = BoxShadowPresetAttrsMap::get_map( "{$attr_name}.decoration.boxShadow" );
		$framing_group    = FitPresetAttrsMap::get_map( "{$attr_name}.decoration.fit" );
		$filters_group    = FiltersPresetAttrsMap::get_map( "{$attr_name}.decoration.filters" );

		return array_merge(
			[],
			$sizing_group,
			$border_group,
			$box_shadow_group,
			$framing_group,
			$filters_group
		);
	}
}
