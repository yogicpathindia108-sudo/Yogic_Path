<?php
/**
 * Class for initializing D5 Readiness App.
 *
 * @since ??
 *
 * @package Divi
 */

use Divi\D5_Readiness\Helpers;
use Divi\D5_Readiness\Server\AJAXEndpoints\CompatibilityChecks;
use Divi\D5_Readiness\Server\AJAXEndpoints\Upgrade;
use Divi\D5_Readiness\Server\AJAXEndpoints\Rollback;

/**
 * This class handles the d5-readiness process.
 *
 * @package Divi
 */
class ET_D5_Readiness {
	/**
	 * Class instance.
	 *
	 * @var ET_D5_Readiness
	 */
	private static $_instance;

	/**
	 * Get the class instance.
	 *
	 * @since ??
	 *
	 * @return ET_D5_Readiness
	 */
	public static function instance() {
		if ( ! self::$_instance ) {
			self::$_instance = new self();

			// initialize hooks here.
		}

		return self::$_instance;
	}

	/**
	 * Includes files.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function includes() {
		$builder_5_dir = trailingslashit( get_template_directory() . '/includes/builder-5/' );

		$files_to_include = [
			'helpers.php',
			'server/AdminPage.php',
			'server/Conversion.php',
			'server/PostTypes.php',
			'server/AJAXEndpoints/CompatibilityChecks.php',
			'server/AJAXEndpoints/Upgrade.php',
			'server/AJAXEndpoints/Rollback.php',
			'server/Checks/FeatureCheck.php',
			'server/Checks/PluginHooksCheck.php',
			'server/Checks/PostFeatureCheck.php',
			'server/Checks/PostFeatureCheckManager.php',
			'server/Checks/PresetFeatureCheck.php',
			'server/Checks/WidgetFeatureCheck.php',
			'server/Checks/PostFeature/ModuleUsage.php',
			'server/Checks/PostFeature/SplitTestUsage.php',
			untrailingslashit( $builder_5_dir ) . '/server/Packages/Conversion/Conversion.php',
		];

		foreach ( $files_to_include as $file ) {
			require_once $file;
		}
	}

	/**
	 * Initialize the hooks.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function init_hooks() {
		self::includes();

		add_filter( 'et_builder_load_requests', [ self::class, 'update_ajax_calls_list' ] );
		add_filter( 'et_should_load_shortcode_framework', [ self::class, 'force_load_shortcode_framework' ] );
		add_filter( 'et_builder_load_woocommerce_modules', [ self::class, 'load_woocommerce_modules' ] );
		add_action( 'after_switch_theme', [ self::class, 'clear_module_cache_on_theme_switch' ] );
		add_action( 'upgrader_process_complete', [ self::class, 'clear_module_cache_on_upgrade' ], 10, 2 );

		CompatibilityChecks::register_endpoints();
		Upgrade::register_endpoints();
		Rollback::register_endpoints();
	}

	/**
	 * Even if woocommerce is not active, we need to load the modules.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function load_woocommerce_modules() {
		return true;
	}

	/**
	 * Update the ajax calls list.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function update_ajax_calls_list() {
		return [
			'action' => array(
				'et_d5_readiness_get_overview_status',
				'et_d5_readiness_get_result_list',
				'et_d5_readiness_rollback_d5_to_d4',
			),
		];
	}

	/**
	 * Callback for `et_should_load_shortcode_framework` filter to force shortcode framework to be loaded
	 * when AJAX request is made for getting post check result list on D5 Readiness app. Shortcode framework
	 * is not loaded on AJAX requests by default for performance reason.
	 *
	 * @since ??
	 *
	 * @param bool $value The current value.
	 *
	 * @return bool
	 */
	public static function force_load_shortcode_framework( $value ) {
		global $pagenow;

		$is_d5_readiness_page = 'admin.php' === $pagenow && isset( $_GET['page'] ) && 'et_d5_readiness' === $_GET['page'];
		// phpcs:disable WordPress.Security.NonceVerification -- It just need to figure out if this is get result list.
		if ( $is_d5_readiness_page || ( wp_doing_ajax() && isset( $_POST['action'] ) && 'et_d5_readiness_get_result_list' === $_POST['action'] ) ) {
			return true;
		}

		return $value;
	}

	/**
	 * Clear module cache transient when theme is switched.
	 *
	 * This ensures that when users switch from D4 to D5 after editing content in D4,
	 * the migrator starts with fresh data and doesn't show stale module counts.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function clear_module_cache_on_theme_switch() {
		Helpers\clear_modules_conversation_cache();
	}

	/**
	 * Clear module cache transient when Divi theme is updated.
	 *
	 * This ensures that after a theme update, the migrator uses fresh data since
	 * module conversion capabilities may have changed in the new version.
	 *
	 * @since ??
	 *
	 * @param WP_Upgrader $upgrader WP_Upgrader instance.
	 * @param array       $options  Array of bulk item update data.
	 *
	 * @return void
	 */
	public static function clear_module_cache_on_upgrade( $upgrader, $options ) {
		// Only clear cache if this is a theme update.
		if ( ! isset( $options['action'], $options['type'] ) || 'update' !== $options['action'] || 'theme' !== $options['type'] ) {
			return;
		}

		/*
		 * Check if the current (parent) theme was updated.
		 *
		 * WordPress passes:
		 * - `$options['themes']` (array) for bulk theme updates,
		 * - `$options['theme']` (string) for single theme updates / auto-updates.
		 */
		$updated_themes = [];

		if ( isset( $options['themes'] ) && is_array( $options['themes'] ) ) {
			$updated_themes = array_merge( $updated_themes, $options['themes'] );
		}

		if ( isset( $options['theme'] ) && is_string( $options['theme'] ) ) {
			$updated_themes[] = $options['theme'];
		}

		$updated_themes = array_values(
			array_unique(
				array_filter( $updated_themes, 'is_string' )
			)
		);

		if ( empty( $updated_themes ) ) {
			return;
		}

		$current_theme = get_template();
		if ( in_array( $current_theme, $updated_themes, true ) ) {
			Helpers\clear_modules_conversation_cache();
		}
	}
}

ET_D5_Readiness::instance();
