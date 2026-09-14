<?php
/**
 * VB App Window Frontend Script Bootstrap.
 *
 * Dynamic Assets skip detection/CSS/cache in VB, but the app window reuses the
 * DA script-enqueue path via `disable_js_on_demand()`. This helper only wires
 * combined-script dependencies so `et_pb_init_modules` listeners register
 * before `frontend-scripts` fires.
 *
 * @package Builder\FrontEnd
 *
 * @since ??
 */

namespace ET\Builder\FrontEnd\Assets;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Utility\Conditions;

/**
 * Class VbAppWindowScriptBootstrap.
 *
 * Thin VB app-window combined-deps wiring for DA-enqueued frontend scripts.
 *
 * @since ??
 */
class VbAppWindowScriptBootstrap {

	/**
	 * Make the combined frontend-scripts bundle depend on DA-enqueued handles.
	 *
	 * Call after `DynamicAssetsUtils::enqueue_combined_script()`. Scripts that
	 * listen on `et_pb_init_modules` must be listed here so they register
	 * listeners before the combined bundle fires that event.
	 *
	 * Handles are only wired when already registered (typically by
	 * `DynamicAssetsEnqueue` with `disable_js_on_demand()` in the VB app window).
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function wire_combined_dependencies(): void {
		if ( ! Conditions::is_vb_app_window() ) {
			return;
		}

		$combined_handle = et_get_combined_script_handle();
		$wp_scripts      = wp_scripts();

		if ( ! isset( $wp_scripts->registered[ $combined_handle ] ) ) {
			return;
		}

		foreach ( self::_get_combined_dependency_handles() as $dep ) {
			if ( ! wp_script_is( $dep, 'registered' ) ) {
				continue;
			}

			$wp_scripts->registered[ $combined_handle ]->deps[] = $dep;
		}
	}

	/**
	 * Handles that must load before the combined frontend-scripts bundle.
	 *
	 * Keep React-polled libraries (easypiechart, lottie) out of this list.
	 *
	 * @since ??
	 *
	 * @return string[]
	 */
	private static function _get_combined_dependency_handles(): array {
		return [
			'fitvids',
			'divi-script-library-fitvids-functions',
			'divi-script-library-gallery',
			'divi-script-library-slider',
			'divi-script-library-toggle',
			'divi-script-library-video-slider',
			'divi-script-library-countdown-timer',
			'divi-script-library-map',
			'google-maps-api',
			'divi-script-library-fullwidth-portfolio',
			'divi-script-library-audio',
			'divi-script-library-video-overlay',
			'divi-script-library-search',
			'divi-script-library-comments',
			'divi-script-library-tabs',
			'divi-script-library-testimonial',
			'divi-script-library-sidebar',
			'divi-script-library-menu',
			'divi-script-library-link',
			'divi-script-library-pagination',
			'divi-script-library-filterable-portfolio',
			'divi-script-library-fullwidth-header',
			'divi-script-library-fullscreen-section',
			'divi-script-library-form-conditions',
			'divi-script-library-section-dividers',
			'divi-script-library-split-testing',
			'divi-script-library-dropdown',
			'divi-script-library-woo',
		];
	}
}
