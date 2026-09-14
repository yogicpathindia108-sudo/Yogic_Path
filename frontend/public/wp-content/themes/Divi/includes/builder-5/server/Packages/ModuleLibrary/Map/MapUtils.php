<?php
/**
 * Map: MapUtils.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Map;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\FrontEnd\Assets\CriticalCSS;

/**
 * Map utility functions.
 *
 * @since ??
 */
class MapUtils {

	/**
	 * Check if Google Maps script should be deferred based on whether module is below the fold.
	 *
	 * This function checks if Critical CSS is enabled and if the current module is below the fold.
	 * If so, the Google Maps script should be deferred (loaded on-demand via IntersectionObserver).
	 *
	 * @since ??
	 *
	 * @return bool True if map script should be deferred, false otherwise.
	 */
	public static function should_defer_map_loading(): bool {
		// Check if Critical CSS is enabled and module is below the fold.
		if ( ! CriticalCSS::should_generate_critical_css() ) {
			return false;
		}

		return CriticalCSS::is_current_module_below_fold_for_lazy_load();
	}
}
