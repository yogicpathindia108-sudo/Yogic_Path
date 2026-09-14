<?php
/**
 * Plugin compatibility for KK Filterable
 *
 * Handles CSS conflicts between kk-filterable plugin and Divi admin styles.
 *
 * @package Divi
 * @since 5.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Plugin compatibility for KK Filterable
 *
 * @since 5.0.0
 * @link https://wordpress.org/plugins/kk-filterable/
 */
class ET_Builder_Plugin_Compat_KK_Filterable extends ET_Builder_Plugin_Compat_Base {
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->plugin_id = 'kk-filterable/kk_filterable.php';
		$this->init_hooks();
	}

	/**
	 * Hook methods to WordPress
	 * Latest plugin version: ??.
	 *
	 * @return void
	 */
	public function init_hooks() {
		// Bail if there's no version found.
		if ( ! $this->get_plugin_version() ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'handle_kk_filterable_compatibility' ), 100 );
	}

	/**
	 * Handle kk-filterable plugin compatibility.
	 *
	 * This function handles CSS conflicts between kk-filterable plugin and Divi admin.
	 * On plugin's admin page: dequeue D5 styles that override plugin's original styling.
	 * On all other admin pages: dequeue plugin styles that interfere with Divi.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public function handle_kk_filterable_compatibility() {
		$screen         = get_current_screen();
		$is_plugin_page = $screen && 'divi_page_kk_mods_options' === $screen->base;

		if ( $is_plugin_page ) {
			// On plugin admin page: dequeue D5 styles that override plugin's original styling.
			$d5_admin_handles = array(
				'et-core-admin-epanel',    // D5 core admin styles.
				'epanel-style',            // D5 panel.css with conflicting checkbox styles.
				'epanel-theme-style',      // D5 theme epanel styles.
			);

			foreach ( $d5_admin_handles as $handle ) {
				if ( wp_style_is( $handle, 'enqueued' ) ) {
					wp_dequeue_style( $handle );
				}
			}
		} else {
			// On all other admin pages: dequeue plugin styles that interfere with Divi.
			$problematic_handles = array(
				'kk_admin_style_metabox',
			);

			foreach ( $problematic_handles as $handle ) {
				if ( wp_style_is( $handle, 'enqueued' ) ) {
					wp_dequeue_style( $handle );
				}
			}
		}
	}
}

new ET_Builder_Plugin_Compat_KK_Filterable();
