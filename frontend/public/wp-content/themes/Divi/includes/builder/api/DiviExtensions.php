<?php
/**
 * Extension API: DiviExtensions class.
 *
 * @package Builder
 * @subpackage API
 */

/**
 * Composite class to manage all Divi Extensions.
 */
class DiviExtensions {

	/**
	 * Utility class instance.
	 *
	 * @since 3.1
	 *
	 * @var ET_Core_Data_Utils
	 */
	protected static $_;

	/**
	 * The first extension to enable debug mode for itself. Only one Divi Extension can be in
	 * debug mode at a time.
	 *
	 * @var DiviExtension
	 */
	protected static $_debugging_extension;

	/**
	 * List of all instances of the Divi Extension.
	 *
	 * @since 3.1
	 *
	 * @var DiviExtension[] {
	 *     All current Divi Extension instances
	 *
	 *     @type DiviExtension $name Instance
	 * }
	 */
	private static $_extensions;

	/**
	 * Register a Divi Extension instance.
	 *
	 * @since 3.1
	 *
	 * @param DiviExtension $instance Instance.
	 */
	public static function add( $instance ) {
		if ( ! isset( self::$_extensions[ $instance->name ] ) ) {
			self::$_extensions[ $instance->name ] = $instance;
		} else {
			et_error( "A Divi Extension named {$instance->name} already exists!" );
		}
	}

	/**
	 * Get one or all Divi Extension instances.
	 *
	 * @since 3.1
	 *
	 * @param string $name The extension name. Default: 'all'.
	 *
	 * @return DiviExtension|DiviExtension[]|null
	 */
	public static function get( $name = 'all' ) {
		if ( 'all' === $name ) {
			return self::$_extensions;
		}

		return self::$_->array_get( self::$_extensions, $name, null );
	}

	/**
	 * Initialize the base `DiviExtension` class.
	 */
	public static function initialize() {
		self::$_ = ET_Core_Data_Utils::instance();

		require_once ET_BUILDER_DIR . 'api/DiviExtension.php';

		/**
		 * Fires when the {@see DiviExtension} base class is available.
		 *
		 * @since 3.1
		 */
		do_action( 'divi_extensions_init' );

		/**
		 * Divi 5 backward compatibility for Divi 4 extensions.
		 *
		 * Some plugins register hooks on later WordPress hooks like 'woocommerce_loaded', etc.
		 * In this case, the normal Divi 4 initialization sequence doesn't occur, so `et_builder_ready` never fires.
		 * This breaks D4 extensions that rely on this hook for module loading.
		 *
		 * We ensure backward compatibility by loading the framework and firing `et_builder_ready`
		 * when it hasn't been fired yet during the normal loading process.
		 *
		 * @since 5.0.0
		 */
		add_action( 'wp_loaded', array( __CLASS__, 'maybe_trigger_et_builder_ready' ), 15 );
	}

	/**
	 * Ensure Divi 4 extension backward compatibility.
	 *
	 * Loads the shortcode framework and fires `et_builder_ready` hook if it hasn't fired yet.
	 * This ensures D4 extensions work properly in Divi 5.
	 *
	 * @since 5.0.0
	 */
	public static function maybe_trigger_et_builder_ready() {
		// Only run on frontend, not admin or AJAX.
		if ( is_admin() || defined( 'DOING_AJAX' ) ) {
			return;
		}

		// Only proceed if `et_builder_ready` hasn't fired yet.
		if ( did_action( 'et_builder_ready' ) ) {
			return;
		}

		// Check if we have plugins that need late extension support.
		$needs_late_support = false;

		// This is a list of plugins that register modules on late hooks.
		// For example, the Divi MyAccount Page plugin registers modules on the woocommerce_loaded hook.
		// We are making a hardcoded list of plugins to avoid unnecessary shortcode framework loading.
		// Entries can be class names (checked via class_exists) or plugin file paths (checked via is_plugin_active).
		$late_registration_plugins = array(
			'DICM_DiviMyAccountModules', // Divi MyAccount Page plugin (class name).
			'floating-divimenus/floating-divimenus.php', // Floating DiviMenus plugin (plugin file path).
		);

		// Load plugin.php if needed for is_plugin_active() checks.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( $late_registration_plugins as $plugin_identifier ) {
			// Plugin file paths contain a slash; class names do not.
			if ( false !== strpos( $plugin_identifier, '/' ) ) {
				// Plugin file path - check if plugin is active.
				if ( is_plugin_active( $plugin_identifier ) ) {
					$needs_late_support = true;
					break;
				}
			} elseif ( class_exists( $plugin_identifier ) ) {
				// Class name - check if class exists.
				$needs_late_support = true;
				break;
			}
		}

		// Only load framework if we have plugins that need it.
		if ( ! $needs_late_support ) {
			return;
		}

		// Load the shortcode framework if not already loaded.
		if ( function_exists( 'et_load_shortcode_framework' ) ) {
			et_load_shortcode_framework();
		}

		// Fire `et_builder_ready` if the framework is loaded but the hook hasn't fired.
		if ( class_exists( 'ET_Builder_Element' ) ) {
			/**
			 * Fires after the builder's structural element classes are loaded.
			 * This is fired by Divi 5 compatibility system to ensure D4 extensions work.
			 *
			 * @since 4.10.0
			 */
			do_action( 'et_builder_ready' );
		}
	}

	/**
	 * Whether or not a Divi Extension is in debug mode.
	 *
	 * @since 3.1
	 *
	 * @return bool
	 */
	public static function is_debugging_extension() {
		return ! is_null( self::$_debugging_extension );
	}

	/**
	 * Register's an extension instance for debug mode if one hasn't already been registered.
	 *
	 * @since 3.1
	 *
	 * @param DiviExtension $instance Instance.
	 *
	 * @return bool Whether or not request was successful
	 */
	public static function register_debug_mode( $instance ) {
		if ( ! self::$_debugging_extension ) {
			self::$_debugging_extension = $instance;

			return true;
		}

		return false;
	}
}

DiviExtensions::initialize();
