<?php
/**
 * Visual Builder Settings.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\SettingsData;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\VisualBuilder\SettingsData\SettingsDataCallbacks;

/**
 * Class for handling Visual Builder settings data.
 *
 * @since ??
 */
class SettingsData implements DependencyInterface {
	/**
	 * Array of functions that returns setting data that is executed on app load.
	 *
	 * @var array
	 */
	private static $_settings_data_functions_on_app_load = [];

	/**
	 * Array of functions that returns setting data that is executed after app load (delivered via REST request).
	 *
	 * @var array
	 */
	private static $_settings_data_functions_after_app_load = [];

	/**
	 * Load the class.
	 */
	public function load() {
		self::register_items();

		// Inserted settings data on app load via filter.
		add_filter(
			'divi_visual_builder_settings_data',
			[ self::class, 'insert_settings_data_functions_on_app_load' ]
		);
	}

	/**
	 * Registering settings data items.
	 *
	 * @since ??
	 */
	public static function register_items() {
		self::register_item(
			[
				'name'               => 'breakpoints',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'breakpoints' ],
			]
		);

		self::register_item(
			[
				'name'               => 'conditionalTags',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'conditional_tags' ],
			]
		);

		self::register_item(
			[
				'name'               => 'currentPage',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'current_page' ],
			]
		);

		self::register_item(
			[
				'name'               => 'currentUser',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'current_user' ],
			]
		);

		self::register_item(
			[
				'name'               => 'customizer',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'customizer' ],
			]
		);

		self::register_item(
			[
				'name'               => 'dynamicContent',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'dynamic_content_app_load' ],
			]
		);

		self::register_item(
			[
				'name'               => 'dynamicContent',
				'usage'              => 'after_app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'dynamic_content' ],
			]
		);

		self::register_item(
			[
				'name'               => 'fonts',
				'usage'              => 'after_app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'fonts' ],
			]
		);

		self::register_item(
			[
				'name'               => 'globalPresets',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'global_presets' ],
			]
		);

		self::register_item(
			[
				'name'               => 'google',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'google' ],
			]
		);

		self::register_item(
			[
				'name'               => 'layout',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'layout' ],
			]
		);

		self::register_item(
			[
				'name'               => 'markups',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'markups_app_load' ],
			]
		);

		self::register_item(
			[
				'name'               => 'markups',
				'usage'              => 'after_app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'markups' ],
			]
		);

		self::register_item(
			[
				'name'               => 'navMenus',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'nav_menus' ],
			]
		);

		if ( class_exists( '\WPCF7_ContactForm' ) ) {
			self::register_item(
				[
					'name'               => 'contactForm7',
					'usage'              => 'app_load',
					'get_value_function' => [ SettingsDataCallbacks::class, 'contact_form_7' ],
				]
			);
		}

		self::register_item(
			[
				'name'               => 'gravityForms',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'gravity_forms' ],
			]
		);

		self::register_item(
			[
				'name'               => 'nonces',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'nonces' ],
			]
		);

		self::register_item(
			[
				'name'               => 'nonces',
				'usage'              => 'after_app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'nonces_after_app_load' ],
			]
		);

		self::register_item(
			[
				'name'               => 'post',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'post' ],
			]
		);

		self::register_item(
			[
				'name'               => 'postTypes',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'post_types' ],
			]
		);

		self::register_item(
			[
				'name'               => 'preferences',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'preferences' ],
			]
		);

		self::register_item(
			[
				'name'               => 'services',
				'usage'              => 'after_app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'services' ],
			]
		);

		self::register_item(
			[
				'name'               => 'announcements',
				'usage'              => 'after_app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'announcements' ],
			]
		);

		self::register_item(
			[
				'name'               => 'settings',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'settings' ],
			]
		);

		self::register_item(
			[
				'name'               => 'shortcodeModuleDefinitions',
				'usage'              => 'after_app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'shortcode_module_definitions' ],
			]
		);

		self::register_item(
			[
				'name'               => 'structureModuleDefinitions',
				'usage'              => 'after_app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'structure_module_definitions' ],
			]
		);

		self::register_item(
			[
				'name'               => 'shortcodeTags',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'shortcode_tags' ],
			]
		);

		self::register_item(
			[
				'name'               => 'styles',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'styles' ],
			]
		);

		self::register_item(
			[
				'name'               => 'taxonomy',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'taxonomy_app_load' ],
			]
		);

		self::register_item(
			[
				'name'               => 'taxonomy',
				'usage'              => 'after_app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'taxonomy' ],
			]
		);

		self::register_item(
			[
				'name'               => 'dependencyChangeDetection',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'dependency_change_detection' ],
			]
		);

		self::register_item(
			[
				'name'               => 'themeBuilder',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'theme_builder' ],
			]
		);

		self::register_item(
			[
				'name'               => 'themeBuilderTemplates',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'theme_builder_templates' ],
			]
		);

		self::register_item(
			[
				'name'               => 'tinymce',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'tinymce' ],
			]
		);

		self::register_item(
			[
				'name'               => 'urls',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'urls' ],
			]
		);

		self::register_item(
			[
				'name'               => 'woocommerce',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'woocommerce' ],
			]
		);

		self::register_item(
			[
				'name'               => 'imagelyGallery',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'imagely_gallery' ],
			]
		);

		self::register_item(
			[
				'name'               => 'workspaces',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'workspaces' ],
			]
		);
		self::register_item(
			[
				'name'               => 'builderVersion',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'get_the_builder_version' ],
			]
		);

		self::register_item(
			[
				'name'               => 'legacyAttributeNames',
				'usage'              => 'app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'legacy_attribute_names' ],
			]
		);

		self::register_item(
			[
				'name'               => 'offCanvas',
				'usage'              => 'after_app_load',
				'get_value_function' => [ SettingsDataCallbacks::class, 'off_canvas' ],
			]
		);
	}

	/**
	 * Register a settings data item.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *   Array of arguments.
	 *
	 *   @type string $name               Name of the item.
	 *   @type string $usage              Usage of the item. 'app_load', 'after_app_load', or 'both'.
	 *   @type callable $get_value_function Function that returns the value of the item.
	 * }
	 */
	public static function register_item( $args ) {
		// Get the parameters.
		$name               = $args['name'] ?? '';
		$get_value_function = $args['get_value_function'] ?? null;
		$usage              = $args['usage'] ?? 'all';

		// Decide the usage timing.
		$on_app_load    = 'app_load' === $usage;
		$after_app_load = 'after_app_load' === $usage;
		$both           = ! $on_app_load && ! $after_app_load;

		// Required arguments should be given in expected type.
		if ( empty( $name ) || ! is_callable( $get_value_function ) ) {
			return;
		}

		// Register setting data function that will be executed on app load.
		if ( $on_app_load || $both ) {
			self::$_settings_data_functions_on_app_load[ $name ] = $get_value_function;
		}

		// Register settings data function that will be executed after app load.
		if ( $after_app_load || $both ) {
			self::$_settings_data_functions_after_app_load[ $name ] = $get_value_function;
		}

		/**
		 * Fires after a settings data item is registered.
		 *
		 * @since ??
		 *
		 * @param string $name  Settings data item name.
		 * @param string $usage Usage of the item. 'app_load', 'after_app_load', or 'both'.
		 */
		do_action( 'divi_visual_builder_settings_data_register_item', $name, $usage );
	}

	/**
	 * Get registered settings data item names by usage.
	 *
	 * @since ??
	 *
	 * @param string $usage Usage of the settings data. 'app_load' or 'after_app_load'.
	 *
	 * @return array<string> List of registered item names.
	 */
	public static function get_registered_item_names( string $usage ): array {
		if ( 'after_app_load' === $usage ) {
			return array_keys( self::$_settings_data_functions_after_app_load );
		}

		return array_keys( self::$_settings_data_functions_on_app_load );
	}

	/**
	 * Get settings data: array of returned value of registered settings data functions.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *   Array of arguments.
	 *
	 *  @type string $usage Usage of the settings data. 'app_load' or 'after_app_load'.
	 * }
	 */
	public static function get_settings_data( $args ) {
		$usage       = $args['usage'] ?? 'app_load';
		$on_app_load = 'app_load' === $usage;

		// When translations are disabled, generate settings data in English locale.
		$disable_translations = et_get_option( 'divi_disable_translations', 'off' );
		$locale_switched      = false;

		if ( 'on' === $disable_translations && function_exists( 'switch_to_locale' ) ) {
			$locale_switched = switch_to_locale( 'en_US' );
		}

		// Array of returned values of registered settings data functions.
		$values = [];

		try {
			// Get array of registered settings data functions.
			$registered_settings_data = $on_app_load
				? self::$_settings_data_functions_on_app_load
				: self::$_settings_data_functions_after_app_load;

			// Populate the returned values.
			foreach ( $registered_settings_data as $item_name => $get_value_function ) {
				if ( is_callable( $get_value_function ) ) {
					/**
					 * Fires before retrieving a settings data item.
					 *
					 * The dynamic portion of the hook name, `$item_name`, refers to the
					 * settings data key being retrieved.
					 *
					 * @since ??
					 *
					 * @param string $usage Usage of the settings data. 'app_load' or 'after_app_load'.
					 */
					do_action( "divi_visual_builder_settings_data_before_get_{$item_name}", $usage );

					$values[ $item_name ] = $get_value_function();

					/**
					 * Fires after retrieving a settings data item.
					 *
					 * The dynamic portion of the hook name, `$item_name`, refers to the
					 * settings data key being retrieved.
					 *
					 * @since ??
					 *
					 * @param string $usage Usage of the settings data. 'app_load' or 'after_app_load'.
					 */
					do_action( "divi_visual_builder_settings_data_after_get_{$item_name}", $usage );
				}
			}

			return $values;
		} finally {
			if ( $locale_switched && function_exists( 'restore_previous_locale' ) ) {
				restore_previous_locale();
			}
		}
	}

	/**
	 * Callback function that is used to insert settings data to DiviSettingsData value on app load.
	 *
	 * @since ??
	 *
	 * @param array $settings Array of settings data.
	 */
	public static function insert_settings_data_functions_on_app_load( $settings ) {
		return array_merge(
			$settings,
			self::get_settings_data( [ 'usage' => 'app_load' ] )
		);
	}
}
