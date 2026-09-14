<?php
/**
 * App Preferences.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\AppPreferences;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Class for handling application preferences.
 *
 * @since ??
 */
class AppPreferences {
	/**
	 * Get an array mapping of application preferences.
	 *
	 * This function returns an associative array that maps various application preferences to
	 * their corresponding keys, locations, types, and default values.
	 * These preferences are used for configuring the application behavior and user interface settings.
	 *
	 * @since ??
	 *
	 * @return array The array mapping of application preferences.
	 *
	 * @example:
	 * ```php
	 * $preferences = mapping();
	 * ```
	 *
	 * @output:
	 * ```php
	 *  [
	 *     'settingsBarLocation' => [
	 *         'key' => 'settings_bar_location',
	 *         'location' => 'pageSettingsBar.position',
	 *         'type' => 'string',
	 *         'default' => 'bottom',
	 *         'options' => [
	 *             'top-left',
	 *             'top',
	 *             'top-right',
	 *             'right',
	 *             'bottom-right',
	 *             'bottom',
	 *             'bottom-left',
	 *             'left',
	 *         ],
	 *     ],
	 *     ...
	 *  ]
	 * ```
	 */
	public static function mapping(): array {
		return [
			'settingsBarLocation'       => [
				'key'      => 'settings_bar_location',
				'location' => 'pageSettingsBar.position',
				'type'     => 'string',
				'default'  => 'bottom',
				'options'  => [
					'top-left',
					'top',
					'top-right',
					'right',
					'bottom-right',
					'bottom',
					'bottom-left',
					'left',
				],
			],
			'toolbarClick'              => [
				'key'      => 'toolbar_click',
				'location' => 'pageSettingsBar.modes',
				'type'     => 'bool',
				'default'  => false,
			],
			'toolbarDesktop'            => [
				'key'      => 'toolbar_desktop',
				'location' => 'pageSettingsBar.views',
				'type'     => 'bool',
				'default'  => true,
			],
			'toolbarGrid'               => [
				'key'      => 'toolbar_grid',
				'location' => 'pageSettingsBar.modes',
				'type'     => 'bool',
				'default'  => false,
			],
			'toolbarHover'              => [
				'key'      => 'toolbar_hover',
				'location' => 'pageSettingsBar.modes',
				'type'     => 'bool',
				'default'  => false,
			],
			'toolbarPhone'              => [
				'key'      => 'toolbar_phone',
				'location' => 'pageSettingsBar.views',
				'type'     => 'bool',
				'default'  => true,
			],
			'toolbarTablet'             => [
				'key'      => 'toolbar_tablet',
				'location' => 'pageSettingsBar.views',
				'type'     => 'bool',
				'default'  => true,
			],
			'toolbarWireframe'          => [
				'key'      => 'toolbar_wireframe',
				'location' => 'pageSettingsBar.views',
				'type'     => 'bool',
				'default'  => true,
			],
			'toolbarZoom'               => [
				'key'      => 'toolbar_zoom',
				'location' => 'pageSettingsBar.views',
				'type'     => 'bool',
				'default'  => true,
			],
			'builderAnimation'          => [
				'key'      => 'builder_animation',
				'location' => 'interface.animation',
				'type'     => 'bool',
				'default'  => true,
			],
			'builderEnableDummyContent' => [
				'key'      => 'builder_enable_dummy_content',
				'location' => 'module.dummyContent',
				'type'     => 'bool',
				'default'  => true,
			],
			'hideDisabledModules'       => [
				'key'      => 'hide_disabled_modules',
				'location' => 'module.disabled',
				'type'     => 'bool',
				'default'  => false,
			],
			'eventMode'                 => [
				'key'      => 'event_mode',
				'location' => 'app.mode',
				'type'     => 'string',
				'default'  => 'hover',
				'options'  => [
					'hover',
					'click',
					'grid',
				],
			],
			'viewMode'                  => [
				'key'      => 'view_mode',
				'location' => 'app.view',
				'type'     => 'string',
				'default'  => et_builder_bfb_enabled() ? 'wireframe' : 'desktop',
				'options'  => [
					'desktop',
					'tablet',
					'phone',
					'wireframe',
				],
			],
			'appTheme'                  => [
				'key'      => 'app_theme',
				'location' => 'app.theme',
				'type'     => 'string',
				'default'  => 'd5-enhanced',
				'options'  => [
					'd5-standard',
					'd5-enhanced',
				],
			],
			'appColorMode'              => [
				'key'      => 'app_color_mode',
				'location' => 'app.colorMode',
				'type'     => 'string',
				'default'  => 'light',
				'options'  => [
					'light',
					'dark',
				],
			],
			'appColorScheme'            => [
				'key'      => 'app_color_scheme',
				'location' => 'app.colorScheme',
				'type'     => 'string',
				'default'  => 'blue',
				'options'  => [
					'blue',
					'purple',
					'green',
					'red',
					'orange',
				],
			],
			'appAdminBar'               => [
				'key'      => 'app_admin_bar',
				'location' => 'app.adminBar',
				'type'     => 'array',
				'default'  => [
					'visible' => false,
				],
			],
			'appInteractionLayers'      => [
				'key'      => 'app.interaction_layers',
				'location' => 'app.interactionLayers',
				'type'     => 'array',
				'default'  => [
					'actionOnHover'       => true,
					'parentActionOnHover' => true,
					'xRay'                => false,
				],
			],
			'appEnablePrerendering'     => [
				'key'      => 'app_enable_prerendering',
				'location' => 'app.enablePrerendering',
				'type'     => 'bool',
				'default'  => true,
			],
			'historyIntervals'          => [
				'key'      => 'history_intervals',
				'location' => 'history.interval',
				'type'     => 'int',
				'default'  => 1,
				'options'  => [
					'1',
					'10',
					'20',
					'30',
					'40',
				],
			],
			'pageCreationFlow'          => [
				'key'      => 'page_creation_flow',
				'location' => 'pageCreationFlow.onStart',
				'type'     => 'string',
				'default'  => 'buildFromScratch',
				'options'  => et_builder_page_creation_settings(),
			],
			'modalPreference'           => [
				'key'      => 'modal_preference',
				'location' => 'modal.preference',
				'type'     => 'string',
				'default'  => 'right',
			],
			'modalPositionX'            => [
				'key'      => 'modal_position_x',
				'location' => 'modal.positionX',
				'type'     => 'int',
				'default'  => 50,
			],
			'modalPositionY'            => [
				'key'      => 'modal_position_y',
				'location' => 'modal.positionY',
				'type'     => 'int',
				'default'  => 50,
			],
			'modalSnapLocation'         => [
				'key'      => 'modal_snap_location',
				'location' => 'modal.snapLocation',
				'type'     => 'string',
				'default'  => 'right',
			],
			'modalSnap'                 => [
				'key'      => 'modal_snap',
				'location' => 'modal.snap',
				'type'     => 'bool',
				'default'  => false,
			],
			'modalDimensionWidth'       => [
				'key'      => 'modal_dimension_width',
				'location' => 'modal.dimensionWidth',
				'type'     => 'int',
				'default'  => 280,
			],
			'modalDimensionHeight'      => [
				'key'      => 'modal_dimension_height',
				'location' => 'modal.dimensionHeight',
				'type'     => 'int',
				'default'  => 320,
			],
			'modalAlwaysCollapseGroups' => [
				'key'      => 'modal_always_collapse_groups',
				'location' => 'modal.alwaysCollapseGroups',
				'type'     => 'bool',
				'default'  => true,
			],
			'pageBarIcons'              => [
				'key'      => 'page_bar_icons',
				'location' => 'pageBarIcons',
				'type'     => 'array',
				'default'  => [
					'undo'         => false,
					'redo'         => false,
					'history'      => false,
					'portability'  => false,
					'clearLayout'  => false,
					'addToLibrary' => false,
				],
			],
			'builderBarIcons'           => [
				'key'      => 'builder_bar_icons',
				'location' => 'builderBarIcons',
				'type'     => 'array',
				'default'  => [
					'loadLayout'               => true,
					'layers'                   => true,
					'inspector'                => true,
					'variableManager'          => true,
					'presetManager'            => true,
					'pageManager'              => true,
					'commandCenter'            => true,
					'wireframeView'            => true,
					'actionIconsOnHover'       => true,
					'parentActionIconsOnHover' => true,
					'xRay'                     => true,
					'workspace'                => true,
					'help'                     => true,
					'announcements'            => true,
					'aiAgent'                  => true,
					'interfaceModeToggle'      => true,
				],
			],
			'showThemeBuilderTemplates' => [
				'key'      => 'show_theme_builder_templates',
				'location' => 'app.showThemeBuilderTemplates',
				'type'     => 'bool',
				'default'  => true,
			],
			'themeBuilderTemplateEditing' => [
				'key'      => 'theme_builder_template_editing',
				'location' => 'app.themeBuilderTemplateEditing',
				'type'     => 'bool',
				'default'  => true,
			],
		];
	}

	/**
	 * Check if prerendering is enabled for the current user.
	 *
	 * @since 5.0.0
	 *
	 * @return bool True if prerendering is enabled, false otherwise.
	 */
	public static function is_prerendering_enabled(): bool {
		$app_preferences         = self::mapping();
		$enable_prerendering_key = 'et_fb_pref_' . $app_preferences['appEnablePrerendering']['key'];
		$enable_prerendering     = et_get_option( $enable_prerendering_key, $app_preferences['appEnablePrerendering']['default'], '', true );

		return (bool) $enable_prerendering;
	}
}
