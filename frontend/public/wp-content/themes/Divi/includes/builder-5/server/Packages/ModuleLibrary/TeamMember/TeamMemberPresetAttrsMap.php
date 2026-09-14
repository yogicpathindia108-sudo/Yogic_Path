<?php
/**
 * Module Library: TeamMember Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\TeamMember;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Options\Border\BorderPresetAttrsMap;
use ET\Builder\Packages\Module\Options\BoxShadow\BoxShadowPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Filters\FiltersPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Fit\FitPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Sizing\SizingPresetAttrsMap;


/**
 * Class TeamMemberPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\TeamMember
 */
class TeamMemberPresetAttrsMap {
	/**
	 * Get the preset attributes map for the TeamMember module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/team-member' !== $module_name ) {
			return $map;
		}

		unset( $map['social.decoration.icon__style_html'] );

		return array_merge(
			$map,
			SizingPresetAttrsMap::get_map( 'image.decoration.sizing' ),
			BorderPresetAttrsMap::get_map( 'image.decoration.border' ),
			BoxShadowPresetAttrsMap::get_map( 'image.decoration.boxShadow' ),
			FitPresetAttrsMap::get_map( 'image.decoration.fit' ),
			FiltersPresetAttrsMap::get_map( 'image.decoration.filters' ),
			[
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
			]
		);
	}
}
