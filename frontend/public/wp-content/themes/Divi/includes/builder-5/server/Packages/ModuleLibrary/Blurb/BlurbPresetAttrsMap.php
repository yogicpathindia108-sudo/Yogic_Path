<?php
/**
 * Module Library: Blurb Module Preset Attributes Map.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Blurb;

use ET\Builder\Packages\Module\Options\Border\BorderPresetAttrsMap;
use ET\Builder\Packages\Module\Options\BoxShadow\BoxShadowPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Fit\FitPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Filters\FiltersPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Sizing\SizingPresetAttrsMap;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Class BlurbPresetAttrsMap.
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\Blurb
 */
class BlurbPresetAttrsMap {
	/**
	 * Get the preset attributes map for the Blurb module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/blurb' !== $module_name ) {
			return $map;
		}

		return array_merge(
			$map,
			[
				'imageIcon.decoration.sizing__iconFontSize' => [
					'attrName' => 'imageIcon.decoration.sizing',
					'preset'   => [ 'style' ],
					'subName'  => 'iconFontSize',
				],
				'content.decoration.bodyFont.dropCap.textShadow__horizontal' => [
					'attrName' => 'content.decoration.bodyFont.dropCap.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'horizontal',
				],
				'content.decoration.bodyFont.dropCap.textShadow__vertical' => [
					'attrName' => 'content.decoration.bodyFont.dropCap.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'vertical',
				],
				'content.decoration.bodyFont.dropCap.textShadow__blur' => [
					'attrName' => 'content.decoration.bodyFont.dropCap.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'blur',
				],
			],
			BorderPresetAttrsMap::get_map( 'imageIcon.decoration.border' ),
			BoxShadowPresetAttrsMap::get_map( 'imageIcon.decoration.boxShadow' ),
			FiltersPresetAttrsMap::get_map( 'imageIcon.decoration.filters' ),
			SizingPresetAttrsMap::get_map( 'imageIcon.decoration.sizing' ),
			FitPresetAttrsMap::get_map( 'imageIcon.decoration.fit' )
		);
	}
}
