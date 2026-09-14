<?php
/**
 * Widget Compatibility for D5 Admin.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Admin;

use ET\Builder\Framework\Utility\Conditions;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Widget Compatibility Class.
 *
 * Ensures D4 widget area creation functionality works in D5 by loading
 * necessary handlers when on the widgets.php admin page.
 *
 * @since ??
 */
class WidgetCompatibility {

	/**
	 * Initialize widget compatibility.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function initialize(): void {
		// Hook into AJAX actions to ensure handlers are available during AJAX requests.
		add_action( 'wp_ajax_et_pb_add_widget_area', [ __CLASS__, 'load_shortcode_core' ], 1 );
		add_action( 'wp_ajax_et_pb_remove_widget_area', [ __CLASS__, 'load_shortcode_core' ], 1 );
	}

	/**
	 * Load D4 shortcode-core.php.
	 *
	 * This ensures D4 widget handlers are available during AJAX calls.
	 * Removes hooks after loading to prevent recursion.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function load_shortcode_core(): void {
		// Only load if we're on widgets admin page or in AJAX context from widgets page.
		if ( ! Conditions::is_widgets_admin_page() && ! wp_doing_ajax() ) {
			return;
		}

		if ( ! function_exists( 'et_pb_add_widget_area' ) ) {
			require_once ET_BUILDER_DIR . 'shortcode-core.php';

			// Remove our hooks to avoid recursion since the real handlers are now loaded.
			remove_action( 'wp_ajax_et_pb_add_widget_area', [ __CLASS__, 'load_shortcode_core' ], 1 );
			remove_action( 'wp_ajax_et_pb_remove_widget_area', [ __CLASS__, 'load_shortcode_core' ], 1 );
		}
	}
}
