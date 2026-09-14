<?php
/**
 * Visual Builder Workspace.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\Workspace;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\VisualBuilder\AppPreferences\AppPreferences;
use ET\Builder\VisualBuilder\Saving\SavingUtility;

/**
 * Class that handles Visual Builder Workspace.
 *
 * @since ??
 */
class Workspace {
	/**
	 * Check whether workspace ID belongs to premade workspace.
	 *
	 * @since ??
	 *
	 * @param string $workspace_id Workspace ID.
	 *
	 * @return bool
	 */
	private static function _is_premade_preferences_workspace_id( string $workspace_id ): bool {
		return str_starts_with( $workspace_id, 'premade-' );
	}

	/**
	 * WordPress Option name that is used to save workspace items.
	 *
	 * @var string
	 */
	public static $option_name = 'et_divi_builder_workspaces';

	/**
	 * WordPress user meta key that is used to save Builder Settings workspaces.
	 *
	 * @var string
	 */
	public static $preferences_option_name = 'et_divi_builder_preferences_workspaces';

	/**
	 * Get workspace items.
	 *
	 * @since ??
	 */
	public static function get_items() {
		$default_workspace_items = [
			'builtIn' => [
				'last-used' => [
					'name' => esc_html__( 'Last Used', 'et_builder_5' ),
					'id'   => 'last-used',
				],
			],
			'custom'  => [],
			'cloud'   => [],
		];

		$workspace_items = get_option( self::$option_name, $default_workspace_items );

		// NOTE: `custom` and `cloud` workspaces are not used yet. These are placed here as a foundation to ensure data structure
		// remains consistent once workspace is introduced.
		return [
			'builtIn' => $workspace_items['builtIn'] ?? [],
			'custom'  => $workspace_items['custom'] ?? [],
			'cloud'   => $workspace_items['cloud'] ?? [],
		];
	}

	/**
	 * Update workspace item.
	 *
	 * @since ??
	 *
	 * @param string $type      Workspace type.
	 * @param string $name      Workspace name.
	 * @param array  $workspace Workspace settings.
	 */
	public static function update_item( $type, $name, $workspace ) {
		$all_workspace_items = self::get_items();

		$saved_workspace_item = $all_workspace_items[ $type ][ $name ] ?? [];

		// Check if a) item is not empty, and b) different from last save.
		if ( ! empty( $workspace ) && $saved_workspace_item !== $workspace ) {
			$all_workspace_items[ $type ][ $name ] = $workspace;

			update_option( self::$option_name, $all_workspace_items );
		}
	}

	/**
	 * Get global Builder Settings preferences from legacy `et_fb_pref_*` options.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function get_global_preferences(): array {
		$global_preferences = [];
		$app_preferences    = AppPreferences::mapping();

		foreach ( $app_preferences as $preference_key => $preference ) {
			$option_name  = 'et_fb_pref_' . $preference['key'];
			$option_value = et_get_option( $option_name, $preference['default'], '', true );

			if ( isset( $preference['options'] ) ) {
				$options       = $preference['options'];
				$valid_options = isset( $options[0] ) ? $options : array_keys( $options );
				// phpcs:ignore WordPress.PHP.StrictInArray -- $valid_options array has strings and numbers values.
				if ( ! in_array( (string) $option_value, $valid_options ) ) {
					$option_value = $preference['default'];
				}
			}

			if ( 'et_fb_pref_app_theme' === $option_name ) {
				$option_value = 'd5-enhanced';
			}

			$global_preferences[ $preference_key ] = SavingUtility::parse_value_type( $option_value, $preference['type'] );
		}

		return $global_preferences;
	}

	/**
	 * Normalize workspace settings to the canonical preference-key shape.
	 *
	 * Supports both canonical keys (e.g. `viewMode`) and DB keys
	 * (e.g. `view_mode`) to keep older saved workspaces compatible.
	 *
	 * @since ??
	 *
	 * @param array $settings Workspace settings.
	 * @param array $fallback_settings Fallback workspace settings.
	 *
	 * @return array
	 */
	private static function _normalize_preferences_settings( array $settings, array $fallback_settings = [] ): array {
		$normalized      = [];
		$app_preferences = AppPreferences::mapping();

		foreach ( $app_preferences as $preference_key => $preference ) {
			$db_key = $preference['key'];

			if ( array_key_exists( $preference_key, $settings ) ) {
				$value = $settings[ $preference_key ];
			} elseif ( array_key_exists( $db_key, $settings ) ) {
				$value = $settings[ $db_key ];
			} elseif ( array_key_exists( $preference_key, $fallback_settings ) ) {
				$value = $fallback_settings[ $preference_key ];
			} else {
				$value = $preference['default'];
			}

			if ( isset( $preference['options'] ) && ! is_array( $value ) ) {
				$options       = $preference['options'];
				$valid_options = isset( $options[0] ) ? $options : array_keys( $options );
				// phpcs:ignore WordPress.PHP.StrictInArray -- $valid_options array has strings and numbers values.
				if ( ! in_array( (string) $value, $valid_options ) ) {
					$value = $preference['default'];
				}
			}

			$normalized[ $preference_key ] = SavingUtility::parse_value_type( $value, $preference['type'], $preference['default'] );
		}

		return $normalized;
	}

	/**
	 * Normalize workspace UI state payload used for modal/layout restoration.
	 *
	 * @since ??
	 *
	 * @param mixed $workspace Workspace UI state payload.
	 *
	 * @return array
	 */
	private static function _normalize_preferences_workspace_ui_state( $workspace ): array {
		if ( ! is_array( $workspace ) ) {
			return [];
		}

		$normalized = [];

		if ( is_string( $workspace['id'] ?? null ) ) {
			$normalized['id'] = sanitize_text_field( $workspace['id'] );
		}

		if ( is_string( $workspace['label'] ?? null ) ) {
			$normalized['label'] = sanitize_text_field( $workspace['label'] );
		}

		if ( is_array( $workspace['window'] ?? null ) ) {
			$normalized['window'] = WorkspacePayloadSanitizer::sanitize_nested_array( $workspace['window'] );
		}

		if ( is_array( $workspace['elements'] ?? null ) ) {
			$normalized['elements'] = WorkspacePayloadSanitizer::sanitize_nested_array( $workspace['elements'] );
		}

		if ( is_array( $workspace['appFramePseudoWrapper'] ?? null ) ) {
			$normalized['appFramePseudoWrapper'] = WorkspacePayloadSanitizer::sanitize_nested_array( $workspace['appFramePseudoWrapper'] );
		}

		if ( is_array( $workspace['sidebarRows'] ?? null ) ) {
			$normalized['sidebarRows'] = WorkspacePayloadSanitizer::sanitize_nested_array( $workspace['sidebarRows'] );
		}

		if ( is_array( $workspace['activeFloatingModals'] ?? null ) ) {
			$normalized['activeFloatingModals'] = WorkspacePayloadSanitizer::sanitize_nested_array( $workspace['activeFloatingModals'] );
		}

		if ( is_string( $workspace['view'] ?? null ) ) {
			$normalized['view'] = sanitize_text_field( $workspace['view'] );
		}

		if ( is_string( $workspace['breakpoint'] ?? null ) ) {
			$normalized['breakpoint'] = sanitize_text_field( $workspace['breakpoint'] );
		}

		if ( is_string( $workspace['attributeState'] ?? null ) ) {
			$normalized['attributeState'] = sanitize_text_field( $workspace['attributeState'] );
		}

		if ( is_array( $workspace['interactionLayers'] ?? null ) ) {
			$normalized['interactionLayers'] = WorkspacePayloadSanitizer::sanitize_nested_array( $workspace['interactionLayers'] );
		}

		return $normalized;
	}

	/**
	 * Resolve preference values for the user's default Builder Settings workspace.
	 *
	 * Mirrors startup workspace resolution in VB (`SettingsDataCallbacks::workspaces()`).
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function get_default_workspace_preferences(): array {
		$global_preferences     = self::get_global_preferences();
		$preferences_workspaces = self::get_preferences_workspaces();
		$custom_preferences     = is_array( $preferences_workspaces['custom'] ?? null ) ? $preferences_workspaces['custom'] : [];
		$default_workspace_id   = is_string( $preferences_workspaces['defaultWorkspaceId'] ?? null ) ? $preferences_workspaces['defaultWorkspaceId'] : 'global';

		if (
			'global' !== $default_workspace_id &&
			! isset( $custom_preferences[ $default_workspace_id ] ) &&
			! self::_is_premade_preferences_workspace_id( $default_workspace_id )
		) {
			$default_workspace_id = 'global';
		}

		if ( 'global' === $default_workspace_id ) {
			return $global_preferences;
		}

		if ( is_array( $custom_preferences[ $default_workspace_id ]['settings'] ?? null ) ) {
			return $custom_preferences[ $default_workspace_id ]['settings'];
		}

		return $global_preferences;
	}

	/**
	 * Get interface color mode for the user's default workspace.
	 *
	 * @since ??
	 *
	 * @return string `light` or `dark`.
	 */
	public static function get_default_app_color_mode(): string {
		$preferences = self::get_default_workspace_preferences();
		$color_mode  = is_string( $preferences['appColorMode'] ?? null ) ? $preferences['appColorMode'] : 'light';

		return 'dark' === $color_mode ? 'dark' : 'light';
	}

	/**
	 * Get Builder Settings workspaces payload for current user.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function get_preferences_workspaces(): array {
		$defaults = self::_get_default_preferences_workspaces();
		$user_id  = get_current_user_id();

		if ( 0 === $user_id ) {
			return $defaults;
		}

		$saved = get_user_meta( $user_id, self::$preferences_option_name, true );

		if ( ! is_array( $saved ) ) {
			return $defaults;
		}

		$custom_workspaces = is_array( $saved['custom'] ?? null ) ? $saved['custom'] : [];
		$sanitized_custom  = [];
		$global_workspace  = self::_normalize_preferences_workspace_ui_state( $saved['global']['workspace'] ?? [] );

		foreach ( $custom_workspaces as $workspace_id => $workspace_data ) {
			if ( ! is_array( $workspace_data ) ) {
				continue;
			}

			$id = is_string( $workspace_data['id'] ?? null ) && '' !== $workspace_data['id']
				? $workspace_data['id']
				: ( is_string( $workspace_id ) ? $workspace_id : '' );

			if ( '' === $id ) {
				continue;
			}

			$name = is_string( $workspace_data['name'] ?? null ) && '' !== $workspace_data['name']
				? $workspace_data['name']
				: $id;

			$sanitized_custom[ $id ] = [
				'id'        => $id,
				'name'      => $name,
				'settings'  => self::_normalize_preferences_settings(
					is_array( $workspace_data['settings'] ?? null ) ? $workspace_data['settings'] : []
				),
				'createdAt' => absint( $workspace_data['createdAt'] ?? 0 ),
				'updatedAt' => absint( $workspace_data['updatedAt'] ?? 0 ),
			];

			$workspace_ui_state = self::_normalize_preferences_workspace_ui_state( $workspace_data['workspace'] ?? [] );
			if ( ! empty( $workspace_ui_state ) ) {
				$sanitized_custom[ $id ]['workspace'] = $workspace_ui_state;
			}
		}

		$active_workspace_id  = 'global';
		$default_workspace_id = is_string( $saved['defaultWorkspaceId'] ?? null ) ? $saved['defaultWorkspaceId'] : 'global';

		if (
			'global' !== $active_workspace_id &&
			! isset( $sanitized_custom[ $active_workspace_id ] ) &&
			! self::_is_premade_preferences_workspace_id( $active_workspace_id )
		) {
			$active_workspace_id = 'global';
		}

		if (
			'global' !== $default_workspace_id &&
			! isset( $sanitized_custom[ $default_workspace_id ] ) &&
			! self::_is_premade_preferences_workspace_id( $default_workspace_id )
		) {
			$default_workspace_id = 'global';
		}

		$defaults['activeWorkspaceId']  = $active_workspace_id;
		$defaults['defaultWorkspaceId'] = $default_workspace_id;
		if ( ! empty( $global_workspace ) ) {
			$defaults['global']['workspace'] = $global_workspace;
		}
		$defaults['custom'] = $sanitized_custom;

		return $defaults;
	}

	/**
	 * Persist Builder Settings workspaces payload for current user.
	 *
	 * @since ??
	 *
	 * @param array $preferences_workspaces Preferences workspaces payload.
	 */
	public static function save_preferences_workspaces( array $preferences_workspaces ): void {
		$user_id = get_current_user_id();

		if ( 0 === $user_id ) {
			return;
		}

		$defaults             = self::_get_default_preferences_workspaces();
		$custom_workspaces    = is_array( $preferences_workspaces['custom'] ?? null ) ? $preferences_workspaces['custom'] : [];
		$sanitized_custom     = [];
		$global_workspace     = self::_normalize_preferences_workspace_ui_state( $preferences_workspaces['global']['workspace'] ?? [] );
		$active_workspace_id  = 'global';
		$default_workspace_id = is_string( $preferences_workspaces['defaultWorkspaceId'] ?? null ) ? sanitize_text_field( $preferences_workspaces['defaultWorkspaceId'] ) : 'global';

		foreach ( $custom_workspaces as $workspace_id => $workspace_data ) {
			if ( ! is_array( $workspace_data ) ) {
				continue;
			}

			$id = is_string( $workspace_data['id'] ?? null ) && '' !== $workspace_data['id']
				? sanitize_text_field( $workspace_data['id'] )
				: ( is_string( $workspace_id ) ? sanitize_text_field( $workspace_id ) : '' );

			if ( '' === $id || 'global' === $id ) {
				continue;
			}

			$name = is_string( $workspace_data['name'] ?? null ) && '' !== trim( $workspace_data['name'] )
				? sanitize_text_field( $workspace_data['name'] )
				: $id;

			$sanitized_custom[ $id ] = [
				'id'        => $id,
				'name'      => $name,
				'settings'  => self::_normalize_preferences_settings(
					is_array( $workspace_data['settings'] ?? null ) ? $workspace_data['settings'] : []
				),
				'createdAt' => absint( $workspace_data['createdAt'] ?? 0 ),
				'updatedAt' => absint( $workspace_data['updatedAt'] ?? 0 ),
			];

			$workspace_ui_state = self::_normalize_preferences_workspace_ui_state( $workspace_data['workspace'] ?? [] );
			if ( ! empty( $workspace_ui_state ) ) {
				$sanitized_custom[ $id ]['workspace'] = $workspace_ui_state;
			}
		}

		if (
			'global' !== $default_workspace_id &&
			! isset( $sanitized_custom[ $default_workspace_id ] ) &&
			! self::_is_premade_preferences_workspace_id( $default_workspace_id )
		) {
			$default_workspace_id = 'global';
		}

		$sanitized_preferences_workspaces                       = $defaults;
		$sanitized_preferences_workspaces['activeWorkspaceId']  = $active_workspace_id;
		$sanitized_preferences_workspaces['defaultWorkspaceId'] = $default_workspace_id;
		if ( ! empty( $global_workspace ) ) {
			$sanitized_preferences_workspaces['global']['workspace'] = $global_workspace;
		}
		$sanitized_preferences_workspaces['custom'] = $sanitized_custom;

		update_user_meta( $user_id, self::$preferences_option_name, $sanitized_preferences_workspaces );
	}

	/**
	 * Update default Builder Settings workspace for current user.
	 *
	 * @since ??
	 *
	 * @param string $workspace_id Workspace ID.
	 */
	public static function set_default_preferences_workspace( string $workspace_id ): void {
		$preferences_workspaces = self::get_preferences_workspaces();

		if (
			'global' !== $workspace_id &&
			! isset( $preferences_workspaces['custom'][ $workspace_id ] ) &&
			! self::_is_premade_preferences_workspace_id( $workspace_id )
		) {
			$workspace_id = 'global';
		}

		$preferences_workspaces['defaultWorkspaceId'] = $workspace_id;
		self::save_preferences_workspaces( $preferences_workspaces );
	}

	/**
	 * Save workspace settings and UI state for selected workspace.
	 *
	 * @since ??
	 *
	 * @param string      $workspace_id        Workspace ID.
	 * @param string|null $workspace_name      Workspace name.
	 * @param array       $settings            Sanitized settings values.
	 * @param array       $workspace_ui_state  Workspace UI state.
	 */
	public static function save_preferences_workspace( string $workspace_id, ?string $workspace_name, array $settings, array $workspace_ui_state ): void {
		$normalized_workspace = self::_normalize_preferences_workspace_ui_state( $workspace_ui_state );

		if ( 'global' === $workspace_id ) {
			$normalized_settings = self::_normalize_preferences_settings( $settings, self::get_global_preferences() );
			$app_preferences     = AppPreferences::mapping();

			foreach ( $app_preferences as $preference_key => $preference ) {
				$option_name  = 'et_fb_pref_' . $preference['key'];
				$option_value = $normalized_settings[ $preference_key ] ?? $preference['default'];

				et_update_option( $option_name, $option_value );
			}

			$workspace_items                         = self::get_items();
			$workspace_items['builtIn']['last-used'] = $normalized_workspace;
			update_option( self::$option_name, $workspace_items );

			$preferences_workspaces = self::get_preferences_workspaces();
			if ( ! empty( $normalized_workspace ) ) {
				$preferences_workspaces['global']['workspace'] = $normalized_workspace;
			} else {
				unset( $preferences_workspaces['global']['workspace'] );
			}
			self::save_preferences_workspaces( $preferences_workspaces );
			return;
		}

		$preferences_workspaces = self::get_preferences_workspaces();

		if ( ! isset( $preferences_workspaces['custom'][ $workspace_id ] ) ) {
			return;
		}

		$existing_settings   = is_array( $preferences_workspaces['custom'][ $workspace_id ]['settings'] ?? null )
			? $preferences_workspaces['custom'][ $workspace_id ]['settings']
			: [];
		$normalized_settings = self::_normalize_preferences_settings( $settings, $existing_settings );

		$preferences_workspaces['custom'][ $workspace_id ]['settings']  = $normalized_settings;
		$preferences_workspaces['custom'][ $workspace_id ]['updatedAt'] = time();

		if ( is_string( $workspace_name ) ) {
			$sanitized_workspace_name = sanitize_text_field( $workspace_name );

			if ( '' !== trim( $sanitized_workspace_name ) ) {
				$preferences_workspaces['custom'][ $workspace_id ]['name'] = $sanitized_workspace_name;
			}
		}

		if ( ! empty( $normalized_workspace ) ) {
			$preferences_workspaces['custom'][ $workspace_id ]['workspace'] = $normalized_workspace;
		} else {
			unset( $preferences_workspaces['custom'][ $workspace_id ]['workspace'] );
		}

		self::save_preferences_workspaces( $preferences_workspaces );
	}

	/**
	 * Create a new custom Builder Settings workspace.
	 *
	 * @since ??
	 *
	 * @param string $name         Workspace name.
	 * @param array  $settings     Workspace settings.
	 * @param bool   $make_default Whether this workspace becomes default.
	 * @param array  $workspace_ui_state Workspace UI state.
	 *
	 * @return array
	 */
	public static function create_preferences_workspace( string $name, array $settings, bool $make_default = false, array $workspace_ui_state = [] ): array {
		$preferences_workspaces = self::get_preferences_workspaces();
		$workspace_id           = 'workspace-' . wp_generate_uuid4();
		$current_time           = time();

		$preferences_workspaces['custom'][ $workspace_id ] = [
			'id'        => $workspace_id,
			'name'      => $name,
			'settings'  => self::_normalize_preferences_settings( $settings ),
			'createdAt' => $current_time,
			'updatedAt' => $current_time,
		];

		$normalized_workspace = self::_normalize_preferences_workspace_ui_state( $workspace_ui_state );
		if ( ! empty( $normalized_workspace ) ) {
			$preferences_workspaces['custom'][ $workspace_id ]['workspace'] = $normalized_workspace;
		}
		if ( $make_default ) {
			$preferences_workspaces['defaultWorkspaceId'] = $workspace_id;
		}

		self::save_preferences_workspaces( $preferences_workspaces );

		return $preferences_workspaces['custom'][ $workspace_id ];
	}

	/**
	 * Delete custom Builder Settings workspace.
	 *
	 * @since ??
	 *
	 * @param string $workspace_id Workspace ID.
	 */
	public static function delete_preferences_workspace( string $workspace_id ): void {
		if ( 'global' === $workspace_id ) {
			return;
		}

		$preferences_workspaces = self::get_preferences_workspaces();

		if ( ! isset( $preferences_workspaces['custom'][ $workspace_id ] ) ) {
			return;
		}

		unset( $preferences_workspaces['custom'][ $workspace_id ] );

		if ( $preferences_workspaces['defaultWorkspaceId'] === $workspace_id ) {
			$preferences_workspaces['defaultWorkspaceId'] = 'global';
		}

		self::save_preferences_workspaces( $preferences_workspaces );
	}

	/**
	 * Get default Builder Settings workspaces payload.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	private static function _get_default_preferences_workspaces(): array {
		return [
			'activeWorkspaceId'  => 'global',
			'defaultWorkspaceId' => 'global',
			'global'             => [],
			'custom'             => [],
		];
	}
}
