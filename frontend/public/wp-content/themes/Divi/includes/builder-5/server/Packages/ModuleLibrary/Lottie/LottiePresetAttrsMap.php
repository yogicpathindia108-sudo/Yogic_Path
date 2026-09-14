<?php
/**
 * Lottie Preset Attributes Map
 *
 * @package ET\Builder\Packages\ModuleLibrary\Lottie
 */

namespace ET\Builder\Packages\ModuleLibrary\Lottie;

/**
 * Lottie Preset Attributes Map class.
 */
class LottiePresetAttrsMap {

	/**
	 * Get the preset attributes map for the Lottie module.
	 *
	 * @since ??
	 *
	 * @param array  $map         Existing attribute map.
	 * @param string $module_name Module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, $module_name ) {
		if ( 'divi/lottie' !== $module_name ) {
			return $map;
		}

		return $map;
	}
}
