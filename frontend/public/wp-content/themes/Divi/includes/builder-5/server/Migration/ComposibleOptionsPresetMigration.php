<?php
/**
 * Composible Options Preset Migration.
 *
 * Migrates deprecated Tabs preset attributes:
 * - `inactiveTab.decoration.background` -> `tab.decoration.background`.
 * - Removes `inactiveTab` preset attribute group.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Migration;

use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\StringUtility;
use ET\Builder\Packages\GlobalData\GlobalPreset;

/**
 * Composible options preset migration.
 *
 * @since ??
 */
class ComposibleOptionsPresetMigration extends MigrationPresetsBase {

	/**
	 * Migration name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_name = 'composibleOptionsPreset.v1';

	/**
	 * Migration release version.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_release_version = '5.1.1';

	/**
	 * Suffix for active tab preset IDs.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_active_preset_id_suffix = '--active-tab-text';

	/**
	 * Register migration hooks.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function load(): void {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_migrate_presets' ], 1 );
	}

	/**
	 * Get migration name.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	public static function get_name() {
		return self::$_name;
	}

	/**
	 * Get release version.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	public static function get_release_version(): string {
		return self::$_release_version;
	}

	/**
	 * Get deterministic active preset ID from a base preset ID.
	 *
	 * @since ??
	 *
	 * @param string $preset_id Base preset ID.
	 *
	 * @return string
	 */
	public static function get_active_group_preset_id( string $preset_id ): string {
		if ( '' === $preset_id ) {
			return $preset_id;
		}

		$suffix = self::$_active_preset_id_suffix;
		if ( 0 === substr_compare( $preset_id, $suffix, -1 * strlen( $suffix ) ) ) {
			return $preset_id;
		}

		return $preset_id . $suffix;
	}

	/**
	 * Run migration only in VB contexts.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function maybe_migrate_presets(): void {
		if ( ! (
			Conditions::is_vb_enabled()
			|| Conditions::is_vb_app_window()
			|| Conditions::is_rest_api_request()
		) ) {
			return;
		}

		self::migrate_presets();
	}

	/**
	 * Migrate persisted global preset data.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function migrate_presets(): void {
		$presets_data = GlobalPreset::get_data();

		if ( empty( $presets_data ) ) {
			return;
		}

		if ( ! self::_has_presets_needing_migration( $presets_data ) ) {
			return;
		}

		$changes_made    = false;
		$updated_presets = $presets_data;

		// Module presets.
		if ( isset( $presets_data['module'] ) ) {
			foreach ( $presets_data['module'] as $module_name => $module_presets ) {
				if ( empty( $module_presets['items'] ) ) {
					continue;
				}

				foreach ( $module_presets['items'] as $preset_id => $preset_item ) {
					$preset_version = $preset_item['version'] ?? '0.0.0';

					if ( StringUtility::version_compare( $preset_version, self::$_release_version, '<' ) ) {
						$migrated_preset = self::_migrate_preset_item( $preset_item, $module_name );

						if ( $migrated_preset !== $preset_item ) {
							$changes_made = true;
							$updated_presets['module'][ $module_name ]['items'][ $preset_id ] = $migrated_preset;
						}
					}
				}
			}
		}

		// Group presets.
		if ( isset( $presets_data['group'] ) ) {
			foreach ( $presets_data['group'] as $group_name => $group_data ) {
				if ( empty( $group_data['items'] ) ) {
					continue;
				}

				foreach ( $group_data['items'] as $preset_id => $preset_item ) {
					$preset_version = $preset_item['version'] ?? '0.0.0';
					$module_name    = $preset_item['moduleName'] ?? '';

					if ( StringUtility::version_compare( $preset_version, self::$_release_version, '<' ) ) {
						$migrated_preset = self::_migrate_preset_item( $preset_item, $module_name );

						if ( $migrated_preset !== $preset_item ) {
							$changes_made = true;
							$updated_presets['group'][ $group_name ]['items'][ $preset_id ] = $migrated_preset;
						}

						// Create a dedicated Active Tab Text preset clone for migrated Tabs tab-text presets.
						if (
							'divi/font' === $group_name
							&& 'divi/tabs' === $module_name
							&& self::_preset_item_has_custom_active_tab_colors( $preset_item )
						) {
							$active_preset_id = self::get_active_group_preset_id( $preset_id );
							$has_active_clone = isset( $updated_presets['group'][ $group_name ]['items'][ $active_preset_id ] );

							if ( ! $has_active_clone ) {
								$active_clone_source = ( $migrated_preset !== $preset_item ) ? $migrated_preset : self::_migrate_preset_item( $preset_item, $module_name );
								$updated_presets['group'][ $group_name ]['items'][ $active_preset_id ] = self::_build_active_group_preset_item( $active_clone_source, $active_preset_id );
								$changes_made = true;
							}
						}

						// Mark source preset as migrated when this version gate is met, including
						// side-effect-only cases (for example active-tab clone creation) so migration
						// version tracking remains authoritative.
						$updated_source_preset = $updated_presets['group'][ $group_name ]['items'][ $preset_id ] ?? $migrated_preset;
						if ( ! is_array( $updated_source_preset ) ) {
							$updated_source_preset = $migrated_preset;
						}

						if ( ( $updated_source_preset['version'] ?? '' ) !== self::$_release_version ) {
							$updated_source_preset['version']                               = self::$_release_version;
							$updated_presets['group'][ $group_name ]['items'][ $preset_id ] = $updated_source_preset;
							$changes_made = true;
						}
					}
				}
			}
		}

		if ( $changes_made ) {
			GlobalPreset::save_data( $updated_presets );
		}
	}

	/**
	 * Migrate a single preset item (import/dedup path).
	 *
	 * @since ??
	 *
	 * @param array  $preset_item Preset item.
	 * @param string $module_name Module name.
	 *
	 * @return array
	 */
	public static function migrate_preset_item( array $preset_item, string $module_name ): array {
		return self::_migrate_preset_item( $preset_item, $module_name );
	}

	/**
	 * Quick pre-check before full migration pass.
	 *
	 * @since ??
	 *
	 * @param array $presets_data Presets data.
	 *
	 * @return bool
	 */
	private static function _has_presets_needing_migration( array $presets_data ): bool {
		$release_version = self::get_release_version();

		$has_old_composible_preset = static function ( array $preset_item, string $module_name ) use ( $release_version ): bool {
			$preset_version = $preset_item['version'] ?? '0.0.0';
			if ( ! StringUtility::version_compare( $preset_version, $release_version, '<' ) ) {
				return false;
			}

			$attr_groups = [ 'attrs', 'renderAttrs', 'styleAttrs' ];
			foreach ( $attr_groups as $attr_group ) {
				$attrs = $preset_item[ $attr_group ] ?? [];

				if ( ! is_array( $attrs ) ) {
					continue;
				}

				if ( 'divi/tabs' === $module_name && isset( $attrs['inactiveTab'] ) ) {
					return true;
				}

				if (
					'divi/blurb' === $module_name
					&& (
						isset( $attrs['imageIcon']['innerContent']['desktop']['value']['animation'] )
						|| isset( $attrs['imageIcon']['innerContent']['tablet']['value']['animation'] )
						|| isset( $attrs['imageIcon']['innerContent']['phone']['value']['animation'] )
						|| self::_has_blurb_legacy_image_icon_background_value( $attrs )
						|| self::_has_blurb_legacy_image_icon_width_value( $attrs )
						|| self::_has_blurb_legacy_image_icon_alignment_value( $attrs )
					)
				) {
					return true;
				}

				if ( self::_has_legacy_button_alignment_attr( $attrs ) ) {
					return true;
				}

				if ( self::_has_button_custom_style_disabled_attr( $attrs ) ) {
					return true;
				}
			}

			if ( 'divi/tabs' === $module_name && self::_preset_item_has_custom_active_tab_colors( $preset_item ) ) {
				return true;
			}

			return false;
		};

		if ( isset( $presets_data['module'] ) && is_array( $presets_data['module'] ) ) {
			foreach ( $presets_data['module'] as $module_name => $module_presets ) {
				foreach ( $module_presets['items'] ?? [] as $preset_item ) {
					if ( $has_old_composible_preset( $preset_item, $module_name ) ) {
						return true;
					}
				}
			}
		}

		if ( isset( $presets_data['group'] ) ) {
			foreach ( $presets_data['group'] as $group_data ) {
				foreach ( $group_data['items'] ?? [] as $preset_item ) {
					$module_name = $preset_item['moduleName'] ?? '';
					if ( $has_old_composible_preset( $preset_item, $module_name ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Migrate one preset item.
	 *
	 * @since ??
	 *
	 * @param array  $preset_item Preset item.
	 * @param string $module_name Module name.
	 *
	 * @return array
	 */
	private static function _migrate_preset_item( array $preset_item, string $module_name ): array {
		$migrated_preset = $preset_item;
		$changes_made    = false;
		$attr_groups     = [ 'attrs', 'renderAttrs', 'styleAttrs' ];

		foreach ( $attr_groups as $attr_group ) {
			if ( empty( $preset_item[ $attr_group ] ) || ! is_array( $preset_item[ $attr_group ] ) ) {
				continue;
			}

			$migrated_attrs = $preset_item[ $attr_group ];

			if ( 'divi/tabs' === $module_name ) {
				$migrated_attrs = self::_migrate_preset_attributes( $migrated_attrs );
			}

			if ( 'divi/blurb' === $module_name ) {
				$migrated_attrs = self::_migrate_blurb_preset_attributes( $migrated_attrs );
			}

			if ( self::_has_legacy_button_alignment_attr( $migrated_attrs ) ) {
				$migrated_attrs = self::_migrate_button_preset_attributes( $migrated_attrs );
			}

			if ( self::_has_button_custom_style_disabled_attr( $migrated_attrs ) ) {
				$migrated_attrs = self::_migrate_disabled_button_custom_style_preset_attributes( $migrated_attrs );
			}

			if ( $migrated_attrs !== $preset_item[ $attr_group ] ) {
				$changes_made                   = true;
				$migrated_preset[ $attr_group ] = $migrated_attrs;
			}
		}

		if ( $changes_made ) {
			$migrated_preset['version'] = self::$_release_version;
		}

		return $migrated_preset;
	}

	/**
	 * Migrate tabs preset attributes.
	 *
	 * @since ??
	 *
	 * @param array $attrs Preset attrs.
	 *
	 * @return array
	 */
	private static function _migrate_preset_attributes( array $attrs ): array {
		$migrated_attrs                = $attrs;
		$has_inactive_tab_background   = isset( $migrated_attrs['inactiveTab']['decoration']['background'] );
		$has_new_tab_background        = isset( $migrated_attrs['tab']['decoration']['background'] );
		$has_deprecated_inactive_group = array_key_exists( 'inactiveTab', $migrated_attrs );

		if ( $has_inactive_tab_background && ! $has_new_tab_background ) {
			$migrated_attrs['tab']['decoration']['background'] = $migrated_attrs['inactiveTab']['decoration']['background'];
		}

		if ( $has_deprecated_inactive_group ) {
			unset( $migrated_attrs['inactiveTab'] );
		}

		return self::_normalize_active_tab_attributes( $migrated_attrs );
	}

	/**
	 * Migrate Blurb preset attributes.
	 *
	 * @since ??
	 *
	 * @param array $attrs Preset attrs.
	 *
	 * @return array
	 */
	private static function _migrate_blurb_preset_attributes( array $attrs ): array {
		$migrated_attrs       = $attrs;
		$module_changed       = false;
		$breakpoints          = [ 'desktop', 'tablet', 'phone' ];
		$has_legacy_animation = false;
		$has_new_animation    = false;

		foreach ( $breakpoints as $breakpoint ) {
			$new_sizing_alignment = $migrated_attrs['imageIcon']['decoration']['sizing'][ $breakpoint ]['value']['alignment'] ?? null;
			$legacy_alignment     = $migrated_attrs['imageIcon']['advanced']['alignment'][ $breakpoint ]['value'] ?? null;

			if ( ! is_string( $legacy_alignment ) || '' === $legacy_alignment ) {
				$legacy_alignment = $migrated_attrs['imageIcon']['advanced']['alignment']['desktop']['value'] ?? null;
			}

			if ( ( ! is_string( $new_sizing_alignment ) || '' === $new_sizing_alignment ) && is_string( $legacy_alignment ) && '' !== $legacy_alignment ) {
				$migrated_attrs['imageIcon']['decoration']['sizing'][ $breakpoint ]['value']['alignment'] = $legacy_alignment;
				$module_changed = true;
			}

			$legacy_image_width = $migrated_attrs['imageIcon']['advanced']['width'][ $breakpoint ]['value']['image'] ?? null;
			$legacy_icon_width  = $migrated_attrs['imageIcon']['advanced']['width'][ $breakpoint ]['value']['icon'] ?? null;
			$sizing_width_attr  = self::_get_blurb_image_sizing_width_attr( $legacy_image_width );
			$new_image_width    = $migrated_attrs['imageIcon']['decoration']['sizing'][ $breakpoint ]['value'][ $sizing_width_attr ] ?? null;
			$new_icon_size      = $migrated_attrs['imageIcon']['decoration']['sizing'][ $breakpoint ]['value']['iconFontSize'] ?? null;

			if ( ( ! is_string( $new_image_width ) || '' === $new_image_width ) && is_string( $legacy_image_width ) && '' !== $legacy_image_width ) {
				$migrated_attrs['imageIcon']['decoration']['sizing'][ $breakpoint ]['value'][ $sizing_width_attr ] = $legacy_image_width;
				$module_changed = true;
			}

			if ( ( ! is_string( $new_icon_size ) || '' === $new_icon_size ) && is_string( $legacy_icon_width ) && '' !== $legacy_icon_width ) {
				$migrated_attrs['imageIcon']['decoration']['sizing'][ $breakpoint ]['value']['iconFontSize'] = $legacy_icon_width;
				$module_changed = true;
			}

			$legacy_background = $migrated_attrs['imageIcon']['decoration']['background'][ $breakpoint ]['value'] ?? null;
			if ( is_string( $legacy_background ) ) {
				$migrated_attrs['imageIcon']['decoration']['background'][ $breakpoint ]['value'] = [
					'color' => $legacy_background,
				];
				$module_changed = true;
			}

			$new_animation = $migrated_attrs['imageIcon']['decoration']['animation'][ $breakpoint ]['value'] ?? null;
			if ( is_array( $new_animation ) && ! empty( $new_animation['style'] ) ) {
				$has_new_animation = true;
			}

			$legacy_animation = $migrated_attrs['imageIcon']['innerContent'][ $breakpoint ]['value']['animation'] ?? null;

			if ( ! is_string( $legacy_animation ) || '' === $legacy_animation ) {
				continue;
			}

			$has_legacy_animation = true;

			$animation_style     = 'off' === $legacy_animation ? 'none' : 'slide';
			$animation_direction = in_array( $legacy_animation, [ 'top', 'left', 'right', 'bottom' ], true ) ? $legacy_animation : 'top';

			$migrated_attrs['imageIcon']['decoration']['animation'][ $breakpoint ]['value'] = [
				'style'     => $animation_style,
				'direction' => $animation_direction,
				'intensity' => [
					'slide' => '2',
				],
				'duration'  => '600ms',
			];

			unset( $migrated_attrs['imageIcon']['innerContent'][ $breakpoint ]['value']['animation'] );
			$module_changed = true;
		}

		if ( isset( $migrated_attrs['imageIcon']['advanced']['width'] ) ) {
			unset( $migrated_attrs['imageIcon']['advanced']['width'] );
			$module_changed = true;
		}

		if ( isset( $migrated_attrs['imageIcon']['advanced']['alignment'] ) ) {
			unset( $migrated_attrs['imageIcon']['advanced']['alignment'] );
			$module_changed = true;
		}

		if ( ! $has_legacy_animation && ! $has_new_animation ) {
			$migrated_attrs['imageIcon']['decoration']['animation']['desktop']['value'] = [
				'style'     => 'slide',
				'direction' => 'top',
				'intensity' => [
					'slide' => '2',
				],
				'duration'  => '600ms',
			];
			$module_changed = true;
		}

		return $module_changed ? $migrated_attrs : $attrs;
	}

	/**
	 * Determine target sizing width attribute for legacy blurb image width migration.
	 *
	 * Maps pixel widths to width and all other units to maxWidth.
	 *
	 * @since ??
	 *
	 * @param string|null $legacy_image_width Legacy image width value.
	 *
	 * @return string
	 */
	private static function _get_blurb_image_sizing_width_attr( ?string $legacy_image_width ): string {
		$legacy_image_width = is_string( $legacy_image_width ) ? strtolower( trim( $legacy_image_width ) ) : '';

		return StringUtility::ends_with( $legacy_image_width, 'px' ) ? 'width' : 'maxWidth';
	}

	/**
	 * Migrate Button preset attributes from legacy button alignment to sizing alignment.
	 *
	 * @since ??
	 *
	 * @param array $attrs Preset attrs.
	 *
	 * @return array
	 */
	private static function _migrate_button_preset_attributes( array $attrs ): array {
		$migrated_attrs = $attrs;
		$breakpoints    = [ 'desktop', 'tablet', 'phone' ];
		$states         = [ 'value', 'hover', 'sticky' ];
		$module_changed = false;

		foreach ( $breakpoints as $breakpoint ) {
			foreach ( $states as $state ) {
				$legacy_alignment = $migrated_attrs['button']['decoration']['button'][ $breakpoint ][ $state ]['alignment'] ?? null;
				$new_alignment    = $migrated_attrs['button']['decoration']['sizing'][ $breakpoint ][ $state ]['alignment'] ?? null;

				if ( ! is_string( $legacy_alignment ) || '' === $legacy_alignment ) {
					continue;
				}

				if ( ! is_string( $new_alignment ) || '' === $new_alignment ) {
					$migrated_attrs['button']['decoration']['sizing'][ $breakpoint ][ $state ]['alignment'] = $legacy_alignment;
					$module_changed = true;
				}

				unset( $migrated_attrs['button']['decoration']['button'][ $breakpoint ][ $state ]['alignment'] );
				if ( self::_cleanup_button_legacy_alignment_path( $migrated_attrs, $breakpoint, $state ) ) {
					$module_changed = true;
				}
				$module_changed = true;
			}
		}

		return $module_changed ? $migrated_attrs : $attrs;
	}

	/**
	 * Determine whether attrs contain legacy button alignment values.
	 *
	 * @since ??
	 *
	 * @param array $attrs Preset attrs.
	 *
	 * @return bool
	 */
	private static function _has_legacy_button_alignment_attr( array $attrs ): bool {
		$breakpoints = [ 'desktop', 'tablet', 'phone' ];
		$states      = [ 'value', 'hover', 'sticky' ];

		foreach ( $breakpoints as $breakpoint ) {
			foreach ( $states as $state ) {
				$legacy_alignment = $attrs['button']['decoration']['button'][ $breakpoint ][ $state ]['alignment'] ?? null;

				if ( is_string( $legacy_alignment ) && '' !== $legacy_alignment ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Cleanup empty legacy button alignment containers after migration.
	 *
	 * @since ??
	 *
	 * @param array  $attrs      Preset attrs.
	 * @param string $breakpoint Breakpoint key.
	 * @param string $state      State key.
	 *
	 * @return bool
	 */
	private static function _cleanup_button_legacy_alignment_path( array &$attrs, string $breakpoint, string $state ): bool {
		$module_changed = false;

		if ( empty( $attrs['button']['decoration']['button'][ $breakpoint ][ $state ] ?? null ) ) {
			unset( $attrs['button']['decoration']['button'][ $breakpoint ][ $state ] );
			$module_changed = true;
		}

		if ( empty( $attrs['button']['decoration']['button'][ $breakpoint ] ?? null ) ) {
			unset( $attrs['button']['decoration']['button'][ $breakpoint ] );
			$module_changed = true;
		}

		return $module_changed;
	}

	/**
	 * Determine whether attrs contain disabled button custom style toggle.
	 *
	 * @since ??
	 *
	 * @param array $attrs Preset attrs.
	 *
	 * @return bool
	 */
	private static function _has_button_custom_style_disabled_attr( array $attrs ): bool {
		return 'off' === ( $attrs['button']['decoration']['button']['desktop']['value']['enable'] ?? null );
	}

	/**
	 * Migrate preset attrs when button custom styles were disabled.
	 *
	 * Clears button decoration attrs while preserving sizing alignment values.
	 *
	 * @since ??
	 *
	 * @param array $attrs Preset attrs.
	 *
	 * @return array
	 */
	private static function _migrate_disabled_button_custom_style_preset_attributes( array $attrs ): array {
		$migrated_attrs = $attrs;

		if ( ! isset( $migrated_attrs['button']['decoration'] ) || ! is_array( $migrated_attrs['button']['decoration'] ) ) {
			return $attrs;
		}

		$alignment_sizing = self::_extract_button_alignment_sizing_attrs( $migrated_attrs );

		unset( $migrated_attrs['button']['decoration'] );

		if ( ! empty( $alignment_sizing ) ) {
			$migrated_attrs['button']['decoration']['sizing'] = $alignment_sizing;
		}

		return $migrated_attrs;
	}

	/**
	 * Extract button sizing alignment values from both new and legacy paths.
	 *
	 * @since ??
	 *
	 * @param array $attrs Preset attrs.
	 *
	 * @return array
	 */
	private static function _extract_button_alignment_sizing_attrs( array $attrs ): array {
		$breakpoints      = [ 'desktop', 'tablet', 'phone' ];
		$states           = [ 'value', 'hover', 'sticky' ];
		$alignment_sizing = [];

		foreach ( $breakpoints as $breakpoint ) {
			foreach ( $states as $state ) {
				$sizing_alignment = $attrs['button']['decoration']['sizing'][ $breakpoint ][ $state ]['alignment'] ?? null;
				$legacy_alignment = $attrs['button']['decoration']['button'][ $breakpoint ][ $state ]['alignment'] ?? null;
				$alignment_value  = $sizing_alignment ?? $legacy_alignment;

				if ( is_string( $alignment_value ) && '' !== $alignment_value ) {
					$alignment_sizing[ $breakpoint ][ $state ]['alignment'] = $alignment_value;
				}
			}
		}

		return $alignment_sizing;
	}

	/**
	 * Check whether Blurb attrs still store legacy image/icon background color values.
	 *
	 * @since ??
	 *
	 * @param array $attrs Preset attrs.
	 *
	 * @return bool
	 */
	private static function _has_blurb_legacy_image_icon_background_value( array $attrs ): bool {
		$breakpoints = [ 'desktop', 'tablet', 'phone' ];

		foreach ( $breakpoints as $breakpoint ) {
			$background_value = $attrs['imageIcon']['decoration']['background'][ $breakpoint ]['value'] ?? null;

			if ( is_string( $background_value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether Blurb attrs still store legacy image/icon width values.
	 *
	 * @since ??
	 *
	 * @param array $attrs Preset attrs.
	 *
	 * @return bool
	 */
	private static function _has_blurb_legacy_image_icon_width_value( array $attrs ): bool {
		$breakpoints = [ 'desktop', 'tablet', 'phone' ];

		foreach ( $breakpoints as $breakpoint ) {
			$image_width = $attrs['imageIcon']['advanced']['width'][ $breakpoint ]['value']['image'] ?? null;
			$icon_width  = $attrs['imageIcon']['advanced']['width'][ $breakpoint ]['value']['icon'] ?? null;

			if (
				( is_string( $image_width ) && '' !== $image_width )
				|| ( is_string( $icon_width ) && '' !== $icon_width )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether Blurb attrs still store legacy image/icon alignment values.
	 *
	 * @since ??
	 *
	 * @param array $attrs Preset attrs.
	 *
	 * @return bool
	 */
	private static function _has_blurb_legacy_image_icon_alignment_value( array $attrs ): bool {
		$breakpoints = [ 'desktop', 'tablet', 'phone' ];

		foreach ( $breakpoints as $breakpoint ) {
			$alignment_value = $attrs['imageIcon']['advanced']['alignment'][ $breakpoint ]['value'] ?? null;

			if ( is_string( $alignment_value ) && '' !== $alignment_value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether preset item includes custom active tab text/background colors.
	 *
	 * @since ??
	 *
	 * @param array $preset_item Preset item.
	 *
	 * @return bool
	 */
	private static function _preset_item_has_custom_active_tab_colors( array $preset_item ): bool {
		$attr_groups = [ 'attrs', 'renderAttrs', 'styleAttrs' ];

		foreach ( $attr_groups as $attr_group ) {
			$attrs = $preset_item[ $attr_group ] ?? [];

			if (
				! empty( $attrs )
				&& is_array( $attrs )
				&& isset( $attrs['activeTab'] )
				&& is_array( $attrs['activeTab'] )
				&& self::_active_tab_has_custom_colors( $attrs['activeTab'] )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether active tab attrs include custom text/background colors.
	 *
	 * @since ??
	 *
	 * @param array $active_tab Active tab attrs.
	 *
	 * @return bool
	 */
	private static function _active_tab_has_custom_colors( array $active_tab ): bool {
		$decoration = $active_tab['decoration'] ?? [];

		if ( ! is_array( $decoration ) ) {
			return false;
		}

		$font = $decoration['font'] ?? [];
		if ( is_array( $font ) ) {
			$font_value = isset( $font['font'] ) && is_array( $font['font'] ) ? $font['font'] : $font;

			if ( self::_has_responsive_color_value( $font_value ) ) {
				return true;
			}
		}

		$background = $decoration['background'] ?? [];
		if ( is_array( $background ) && self::_has_responsive_color_value( $background ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Build dedicated active group preset item from legacy combined preset.
	 *
	 * @since ??
	 *
	 * @param array  $preset_item      Source preset item.
	 * @param string $active_preset_id Active clone preset ID.
	 *
	 * @return array
	 */
	private static function _build_active_group_preset_item( array $preset_item, string $active_preset_id ): array {
		$active_preset_item = $preset_item;
		$attr_groups        = [ 'attrs', 'renderAttrs', 'styleAttrs' ];

		foreach ( $attr_groups as $attr_group ) {
			$attrs = $preset_item[ $attr_group ] ?? [];

			if ( ! is_array( $attrs ) ) {
				continue;
			}

			$active_preset_item[ $attr_group ] = self::_extract_active_tab_attributes( $attrs );
		}

		if ( isset( $active_preset_item['name'] ) && is_string( $active_preset_item['name'] ) ) {
			$active_preset_item['name'] = self::_append_active_suffix_to_label( $active_preset_item['name'] );
		}

		if ( isset( $active_preset_item['adminLabel'] ) && is_string( $active_preset_item['adminLabel'] ) ) {
			$active_preset_item['adminLabel'] = self::_append_active_suffix_to_label( $active_preset_item['adminLabel'] );
		}

		$active_preset_item['id']              = $active_preset_id;
		$active_preset_item['groupId']         = 'designActiveTabText';
		$active_preset_item['primaryAttrName'] = 'activeTab';
		$active_preset_item['version']         = self::$_release_version;

		return $active_preset_item;
	}

	/**
	 * Extract active tab attribute payload from combined tabs preset attrs.
	 *
	 * @since ??
	 *
	 * @param array $attrs Source attrs.
	 *
	 * @return array
	 */
	private static function _extract_active_tab_attributes( array $attrs ): array {
		$active_attrs = [];

		if ( isset( $attrs['activeTab'] ) && is_array( $attrs['activeTab'] ) ) {
			$active_attrs['activeTab'] = $attrs['activeTab'];
		}

		return $active_attrs;
	}

	/**
	 * Check if responsive value map contains at least one non-empty color.
	 *
	 * @since ??
	 *
	 * @param array $value Responsive value map.
	 *
	 * @return bool
	 */
	private static function _has_responsive_color_value( array $value ): bool {
		foreach ( $value as $device_value ) {
			if ( ! is_array( $device_value ) || ! array_key_exists( 'value', $device_value ) ) {
				continue;
			}

			$raw_value = $device_value['value'];

			if ( is_string( $raw_value ) && '' !== $raw_value ) {
				return true;
			}

			if (
				is_array( $raw_value )
				&& ! empty( $raw_value['color'] )
				&& is_string( $raw_value['color'] )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize legacy active tab attributes into composable group structures.
	 *
	 * @since ??
	 *
	 * @param array $attrs Preset attrs.
	 *
	 * @return array
	 */
	private static function _normalize_active_tab_attributes( array $attrs ): array {
		$active_tab = $attrs['activeTab'] ?? [];
		if ( ! is_array( $active_tab ) ) {
			return $attrs;
		}

		$decoration = $active_tab['decoration'] ?? [];
		if ( ! is_array( $decoration ) ) {
			return $attrs;
		}

		$font = $decoration['font'] ?? [];
		if ( is_array( $font ) ) {
			$font_responsive_value = [];

			if ( isset( $font['font'] ) && is_array( $font['font'] ) ) {
				$font_responsive_value = self::_normalize_responsive_color_picker_values( $font['font'] );
				$font['font']          = $font_responsive_value;
			} else {
				$font_responsive_value = self::_normalize_responsive_color_picker_values( $font );
				if ( ! empty( $font_responsive_value ) ) {
					$font = [
						'font' => $font_responsive_value,
					];
				}
			}

			$decoration['font'] = $font;
		}

		$background = $decoration['background'] ?? [];
		if ( is_array( $background ) ) {
			$decoration['background'] = self::_normalize_responsive_color_picker_values( $background );
		}

		$active_tab['decoration'] = $decoration;
		$attrs['activeTab']       = $active_tab;

		return $attrs;
	}

	/**
	 * Normalize responsive color-picker values to group-compatible shape.
	 *
	 * Converts `desktop.value = "#fff"` to `desktop.value.color = "#fff"`.
	 *
	 * @since ??
	 *
	 * @param array $value Responsive value map.
	 *
	 * @return array
	 */
	private static function _normalize_responsive_color_picker_values( array $value ): array {
		$normalized = $value;

		foreach ( $normalized as $device => $device_value ) {
			if ( ! is_array( $device_value ) || ! array_key_exists( 'value', $device_value ) ) {
				continue;
			}

			$raw_value = $device_value['value'];

			if ( is_string( $raw_value ) && '' !== $raw_value ) {
				$normalized[ $device ]['value'] = [
					'color' => $raw_value,
				];
			}
		}

		return $normalized;
	}

	/**
	 * Append " (Active)" suffix to a preset label.
	 *
	 * @since ??
	 *
	 * @param string $label Preset label.
	 *
	 * @return string
	 */
	private static function _append_active_suffix_to_label( string $label ): string {
		$suffix = ' (Active)';
		if ( 0 === substr_compare( $label, $suffix, -1 * strlen( $suffix ) ) ) {
			return $label;
		}

		return $label . $suffix;
	}
}
