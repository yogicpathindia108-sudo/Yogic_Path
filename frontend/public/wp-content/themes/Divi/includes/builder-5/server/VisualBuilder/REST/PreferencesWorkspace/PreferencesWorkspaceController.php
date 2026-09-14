<?php
/**
 * REST: PreferencesWorkspaceController class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\REST\PreferencesWorkspace;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\VisualBuilder\Saving\SavingUtility;
use ET\Builder\VisualBuilder\Workspace\Workspace;
use ET\Builder\VisualBuilder\Workspace\WorkspacePayloadSanitizer;
use WP_REST_Request;
use WP_REST_Response;

/**
 * PreferencesWorkspaceController class.
 *
 * This class handles REST requests for Builder Settings custom workspaces.
 *
 * @since ??
 */
class PreferencesWorkspaceController extends RESTController {
	/**
	 * Sanitize workspace ID value.
	 *
	 * @since ??
	 *
	 * @param mixed $workspace_id Workspace ID value.
	 *
	 * @return string
	 */
	public static function sanitize_workspace_id( $workspace_id ): string {
		return sanitize_text_field( (string) $workspace_id );
	}

	/**
	 * Validate workspace ID format.
	 *
	 * @since ??
	 *
	 * @param mixed           $workspace_id Workspace ID value.
	 * @param WP_REST_Request $request      Request object.
	 * @param string          $param        Parameter name.
	 *
	 * @return bool
	 */
	public static function validate_workspace_id( $workspace_id, WP_REST_Request $request, string $param ): bool {
		$workspace_id = self::sanitize_workspace_id( $workspace_id );

		if ( '' === $workspace_id ) {
			return false;
		}

		// Support built-in global workspace and custom generated workspace IDs.
		return 'global' === $workspace_id || str_starts_with( $workspace_id, 'workspace-' ) || str_starts_with( $workspace_id, 'premade-' );
	}

	/**
	 * Sanitize preferences payload.
	 *
	 * @since ??
	 *
	 * @param mixed $preferences Preferences payload.
	 *
	 * @return array
	 */
	public static function sanitize_preferences_payload( $preferences ): array {
		return SavingUtility::sanitize_app_preferences( is_array( $preferences ) ? $preferences : [] );
	}

	/**
	 * Sanitize workspace UI payload.
	 *
	 * @since ??
	 *
	 * @param mixed $workspace Workspace UI payload.
	 *
	 * @return array
	 */
	public static function sanitize_workspace_payload( $workspace ): array {
		if ( ! is_array( $workspace ) ) {
			return [];
		}

		$allowed_workspace_keys = [
			'id',
			'label',
			'window',
			'elements',
			'appFramePseudoWrapper',
			'sidebarRows',
			'activeFloatingModals',
			'view',
			'breakpoint',
			'attributeState',
			'interactionLayers',
		];
		$sanitized_workspace    = [];

		foreach ( $allowed_workspace_keys as $key ) {
			if ( ! array_key_exists( $key, $workspace ) ) {
				continue;
			}

			$value = $workspace[ $key ];

			if ( in_array( $key, [ 'id', 'label', 'view', 'breakpoint', 'attributeState' ], true ) ) {
				if ( is_string( $value ) ) {
					$sanitized_workspace[ $key ] = sanitize_text_field( $value );
				}

				continue;
			}

			if ( is_array( $value ) ) {
				$sanitized_workspace[ $key ] = WorkspacePayloadSanitizer::sanitize_nested_array( $value );
			}
		}

		return $sanitized_workspace;
	}

	/**
	 * Create a new custom workspace from current preferences.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function create( WP_REST_Request $request ): WP_REST_Response {
		$name                = trim( sanitize_text_field( (string) $request->get_param( 'name' ) ) );
		$make_default        = (bool) $request->get_param( 'makeDefault' );
		$preferences         = self::sanitize_update_preferences_payload( $request->get_param( 'preferences' ) );
		$workspace           = self::sanitize_workspace_payload( $request->get_param( 'workspace' ) );
		$name                = '' !== $name ? $name : esc_html__( 'Untitled Workspace', 'et_builder_5' );
		$created_workspace   = Workspace::create_preferences_workspace( $name, $preferences, $make_default, $workspace );
		$preferences_payload = Workspace::get_preferences_workspaces();

		return self::response_success(
			[
				'workspace'          => $created_workspace,
				'activeWorkspaceId'  => $preferences_payload['activeWorkspaceId'] ?? 'global',
				'defaultWorkspaceId' => $preferences_payload['defaultWorkspaceId'] ?? 'global',
			]
		);
	}

	/**
	 * Set default workspace ID.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function set_default( WP_REST_Request $request ): WP_REST_Response {
		$workspace_id = self::sanitize_workspace_id( $request->get_param( 'workspaceId' ) );
		Workspace::set_default_preferences_workspace( $workspace_id );
		$preferences_payload = Workspace::get_preferences_workspaces();

		return self::response_success(
			[
				'defaultWorkspaceId' => $preferences_payload['defaultWorkspaceId'] ?? 'global',
			]
		);
	}

	/**
	 * Update selected workspace settings and UI state.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function update( WP_REST_Request $request ): WP_REST_Response {
		$workspace_id = self::sanitize_workspace_id( $request->get_param( 'workspaceId' ) );
		$preferences  = self::sanitize_update_preferences_payload( $request->get_param( 'preferences' ) );
		$workspace    = self::sanitize_workspace_payload( $request->get_param( 'workspace' ) );
		$name         = $request->get_param( 'name' );
		$name         = is_string( $name ) ? trim( sanitize_text_field( $name ) ) : null;
		$name         = '' !== $name ? $name : null;

		Workspace::save_preferences_workspace( $workspace_id, $name, $preferences, $workspace );
		$preferences_payload = Workspace::get_preferences_workspaces();

		return self::response_success(
			[
				'activeWorkspaceId'  => $preferences_payload['activeWorkspaceId'] ?? 'global',
				'defaultWorkspaceId' => $preferences_payload['defaultWorkspaceId'] ?? 'global',
				'global'             => [
					'settings'  => $preferences_payload['global']['settings'] ?? [],
					'workspace' => $preferences_payload['global']['workspace'] ?? [],
				],
				'custom'             => $preferences_payload['custom'][ $workspace_id ] ?? null,
			]
		);
	}

	/**
	 * Delete selected workspace.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function delete( WP_REST_Request $request ): WP_REST_Response {
		$workspace_id = self::sanitize_workspace_id( $request->get_param( 'workspaceId' ) );

		Workspace::delete_preferences_workspace( $workspace_id );
		$preferences_payload = Workspace::get_preferences_workspaces();

		return self::response_success(
			[
				'activeWorkspaceId'  => $preferences_payload['activeWorkspaceId'] ?? 'global',
				'defaultWorkspaceId' => $preferences_payload['defaultWorkspaceId'] ?? 'global',
			]
		);
	}

	/**
	 * Sanitize update preferences payload.
	 *
	 * @since ??
	 *
	 * @param mixed $preferences Preferences payload.
	 *
	 * @return array
	 */
	public static function sanitize_update_preferences_payload( $preferences ): array {
		if ( ! is_array( $preferences ) ) {
			return [];
		}

		$preferences   = WorkspacePayloadSanitizer::sanitize_nested_array( $preferences );
		$sanitized     = [];
		$app           = is_array( $preferences['app'] ?? null ) ? $preferences['app'] : [];
		$history       = is_array( $preferences['history'] ?? null ) ? $preferences['history'] : [];
		$modal         = is_array( $preferences['modal'] ?? null ) ? $preferences['modal'] : [];
		$module        = is_array( $preferences['module'] ?? null ) ? $preferences['module'] : [];
		$page_flow     = is_array( $preferences['pageCreationFlow'] ?? null ) ? $preferences['pageCreationFlow'] : [];
		$page_icons    = is_array( $preferences['pageBarIcons'] ?? null ) ? $preferences['pageBarIcons'] : [];
		$builder_icons = is_array( $preferences['builderBarIcons'] ?? null ) ? $preferences['builderBarIcons'] : [];

		$view_mode = $app['view'] ?? $preferences['viewMode'] ?? null;
		if ( is_string( $view_mode ) ) {
			$sanitized['viewMode'] = $view_mode;
		}

		$app_color_mode = $app['colorMode'] ?? $preferences['appColorMode'] ?? null;
		if ( is_string( $app_color_mode ) ) {
			$sanitized['appColorMode'] = $app_color_mode;
		}

		$app_color_scheme = $app['colorScheme'] ?? $preferences['appColorScheme'] ?? null;
		if ( is_string( $app_color_scheme ) ) {
			$sanitized['appColorScheme'] = $app_color_scheme;
		}

		$app_admin_bar = $app['adminBar'] ?? $preferences['appAdminBar'] ?? null;
		if ( is_array( $app_admin_bar ) ) {
			$sanitized['appAdminBar'] = [
				'visible' => rest_sanitize_boolean( $app_admin_bar['visible'] ?? false ),
			];
		}

		$app_interaction_layers = $app['interactionLayers'] ?? $preferences['appInteractionLayers'] ?? null;
		if ( is_array( $app_interaction_layers ) ) {
			$sanitized['appInteractionLayers'] = [
				'actionOnHover'       => rest_sanitize_boolean( $app_interaction_layers['actionOnHover'] ?? true ),
				'parentActionOnHover' => rest_sanitize_boolean( $app_interaction_layers['parentActionOnHover'] ?? true ),
				'xRay'                => rest_sanitize_boolean( $app_interaction_layers['xRay'] ?? false ),
			];
		}

		$show_theme_builder_templates = $app['showThemeBuilderTemplates'] ?? $preferences['showThemeBuilderTemplates'] ?? null;
		if ( null !== $show_theme_builder_templates && ! is_array( $show_theme_builder_templates ) ) {
			$sanitized['showThemeBuilderTemplates'] = rest_sanitize_boolean( $show_theme_builder_templates );
		}

		$theme_builder_template_editing = $app['themeBuilderTemplateEditing'] ?? $preferences['themeBuilderTemplateEditing'] ?? null;
		if ( null !== $theme_builder_template_editing && ! is_array( $theme_builder_template_editing ) ) {
			$sanitized['themeBuilderTemplateEditing'] = rest_sanitize_boolean( $theme_builder_template_editing );
		}

		$app_enable_prerendering = $app['enablePrerendering'] ?? $preferences['appEnablePrerendering'] ?? null;
		if ( null !== $app_enable_prerendering && ! is_array( $app_enable_prerendering ) ) {
			$sanitized['appEnablePrerendering'] = rest_sanitize_boolean( $app_enable_prerendering );
		}

		$history_interval = $history['interval'] ?? $preferences['historyIntervals'] ?? null;
		if ( null !== $history_interval && ! is_array( $history_interval ) ) {
			$sanitized['historyIntervals'] = absint( $history_interval );
		}

		$modal_preference = $modal['preference'] ?? $preferences['modalPreference'] ?? null;
		if ( is_string( $modal_preference ) ) {
			$sanitized['modalPreference'] = $modal_preference;
		}

		$modal_collapse_groups = $modal['alwaysCollapseGroups'] ?? $preferences['modalAlwaysCollapseGroups'] ?? null;
		if ( null !== $modal_collapse_groups && ! is_array( $modal_collapse_groups ) ) {
			$sanitized['modalAlwaysCollapseGroups'] = rest_sanitize_boolean( $modal_collapse_groups );
		}

		$module_dummy_content = $module['dummyContent'] ?? $preferences['builderEnableDummyContent'] ?? null;
		if ( null !== $module_dummy_content && ! is_array( $module_dummy_content ) ) {
			$sanitized['builderEnableDummyContent'] = rest_sanitize_boolean( $module_dummy_content );
		}

		$module_disabled_mode = $module['disabled'] ?? null;
		if ( null === $module_disabled_mode && array_key_exists( 'hideDisabledModules', $preferences ) ) {
			$module_disabled_mode = rest_sanitize_boolean( $preferences['hideDisabledModules'] ) ? 'hidden' : 'transparent';
		}
		if ( is_string( $module_disabled_mode ) ) {
			$sanitized['hideDisabledModules'] = 'transparent' !== $module_disabled_mode;
		}

		$page_creation_flow = $page_flow['onStart'] ?? $preferences['pageCreationFlow'] ?? null;
		if ( is_string( $page_creation_flow ) ) {
			$sanitized['pageCreationFlow'] = strtolower( preg_replace( '/([A-Z])/', '_$1', $page_creation_flow ) );
		}

		if ( empty( $page_icons ) && is_array( $preferences['pageBarIcons'] ?? null ) ) {
			$page_icons = $preferences['pageBarIcons'];
		}
		if ( is_array( $page_icons ) ) {
			$sanitized['pageBarIcons'] = [
				'undo'         => rest_sanitize_boolean( $page_icons['undo'] ?? true ),
				'redo'         => rest_sanitize_boolean( $page_icons['redo'] ?? true ),
				'history'      => rest_sanitize_boolean( $page_icons['history'] ?? true ),
				'portability'  => rest_sanitize_boolean( $page_icons['portability'] ?? true ),
				'clearLayout'  => rest_sanitize_boolean( $page_icons['clearLayout'] ?? true ),
				'addToLibrary' => rest_sanitize_boolean( $page_icons['addToLibrary'] ?? true ),
			];
		}

		if ( empty( $builder_icons ) && is_array( $preferences['builderBarIcons'] ?? null ) ) {
			$builder_icons = $preferences['builderBarIcons'];
		}
		if ( is_array( $builder_icons ) ) {
			$sanitized['builderBarIcons'] = [
				'loadLayout'               => rest_sanitize_boolean( $builder_icons['loadLayout'] ?? true ),
				'layers'                   => rest_sanitize_boolean( $builder_icons['layers'] ?? true ),
				'inspector'                => rest_sanitize_boolean( $builder_icons['inspector'] ?? true ),
				'variableManager'          => rest_sanitize_boolean( $builder_icons['variableManager'] ?? true ),
				'presetManager'            => rest_sanitize_boolean( $builder_icons['presetManager'] ?? true ),
				'pageManager'              => rest_sanitize_boolean( $builder_icons['pageManager'] ?? true ),
				'commandCenter'            => rest_sanitize_boolean( $builder_icons['commandCenter'] ?? true ),
				'wireframeView'            => rest_sanitize_boolean( $builder_icons['wireframeView'] ?? true ),
				'actionIconsOnHover'       => rest_sanitize_boolean( $builder_icons['actionIconsOnHover'] ?? true ),
				'parentActionIconsOnHover' => rest_sanitize_boolean( $builder_icons['parentActionIconsOnHover'] ?? true ),
				'xRay'                     => rest_sanitize_boolean( $builder_icons['xRay'] ?? true ),
				'workspace'                => rest_sanitize_boolean( $builder_icons['workspace'] ?? true ),
				'help'                     => rest_sanitize_boolean( $builder_icons['help'] ?? true ),
				'announcements'            => rest_sanitize_boolean( $builder_icons['announcements'] ?? true ),
				'aiAgent'                  => rest_sanitize_boolean( $builder_icons['aiAgent'] ?? true ),
				'interfaceModeToggle'      => rest_sanitize_boolean( $builder_icons['interfaceModeToggle'] ?? true ),
			];
		}

		return $sanitized;
	}

	/**
	 * Create arguments.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function create_args(): array {
		return [
			'name'        => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'makeDefault' => [
				'default'           => false,
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
			],
			'preferences' => [
				'default'           => [],
				'type'              => 'object',
				'sanitize_callback' => [ __CLASS__, 'sanitize_update_preferences_payload' ],
			],
			'workspace'   => [
				'default'           => [],
				'type'              => 'object',
				'sanitize_callback' => [ __CLASS__, 'sanitize_workspace_payload' ],
			],
		];
	}

	/**
	 * Set default arguments.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function set_default_args(): array {
		return [
			'workspaceId' => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => [ __CLASS__, 'sanitize_workspace_id' ],
				'validate_callback' => [ __CLASS__, 'validate_workspace_id' ],
			],
		];
	}

	/**
	 * Update arguments.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function update_args(): array {
		return [
			'workspaceId' => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => [ __CLASS__, 'sanitize_workspace_id' ],
				'validate_callback' => [ __CLASS__, 'validate_workspace_id' ],
			],
			'preferences' => [
				'default'           => [],
				'type'              => 'object',
				'sanitize_callback' => [ __CLASS__, 'sanitize_update_preferences_payload' ],
			],
			'name'        => [
				'default'           => '',
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'workspace'   => [
				'default'           => [],
				'type'              => 'object',
				'sanitize_callback' => [ __CLASS__, 'sanitize_workspace_payload' ],
			],
		];
	}

	/**
	 * Delete arguments.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function delete_args(): array {
		return [
			'workspaceId' => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => [ __CLASS__, 'sanitize_workspace_id' ],
				'validate_callback' => [ __CLASS__, 'validate_workspace_id' ],
			],
		];
	}

	/**
	 * Permission for create.
	 *
	 * @since ??
	 *
	 * @return bool|\WP_Error
	 */
	public static function create_permission() {
		if ( ! UserRole::can_current_user_use_visual_builder() || ! current_user_can( 'edit_posts' ) ) {
			return self::response_error_permission();
		}

		return true;
	}

	/**
	 * Permission for set default.
	 *
	 * @since ??
	 *
	 * @return bool|\WP_Error
	 */
	public static function set_default_permission() {
		if ( ! UserRole::can_current_user_use_visual_builder() || ! current_user_can( 'edit_posts' ) ) {
			return self::response_error_permission();
		}

		return true;
	}

	/**
	 * Permission for update.
	 *
	 * @since ??
	 *
	 * @return bool|\WP_Error
	 */
	public static function update_permission() {
		if ( ! UserRole::can_current_user_use_visual_builder() || ! current_user_can( 'edit_posts' ) ) {
			return self::response_error_permission();
		}

		return true;
	}

	/**
	 * Permission for delete.
	 *
	 * @since ??
	 *
	 * @return bool|\WP_Error
	 */
	public static function delete_permission() {
		if ( ! UserRole::can_current_user_use_visual_builder() || ! current_user_can( 'edit_posts' ) ) {
			return self::response_error_permission();
		}

		return true;
	}
}
