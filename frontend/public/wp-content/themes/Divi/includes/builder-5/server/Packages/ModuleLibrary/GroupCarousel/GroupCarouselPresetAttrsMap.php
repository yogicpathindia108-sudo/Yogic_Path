<?php
/**
 * Group Carousel Preset Attributes Map
 *
 * @package ET\Builder\Packages\ModuleLibrary\GroupCarousel
 */

namespace ET\Builder\Packages\ModuleLibrary\GroupCarousel;

/**
 * Group Carousel Preset Attributes Map class.
 */
class GroupCarouselPresetAttrsMap {

	/**
	 * Get preset attributes map for group carousel module.
	 *
	 * @param array  $map         Existing map.
	 * @param string $module_name Module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ): array {
		if ( 'divi/group-carousel' !== $module_name ) {
			return $map;
		}

		return $map;
	}
}
