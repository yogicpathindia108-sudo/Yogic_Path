<?php
/**
 * Grid Offset Rules Preset Migration
 *
 * Migrates legacy grid offset rule shape (`offsetRule`/`offsetValue`) in preset
 * attrs trees to the canonical `offsetValues` map.
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Migration;

use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\StringUtility;
use ET\Builder\Packages\GlobalData\GlobalPreset;
use ET\Builder\Migration\MigrationPresetsBase;

/**
 * Grid Offset Rules Preset Migration Class.
 *
 * @since ??
 */
class GridOffsetRulesPresetMigration extends MigrationPresetsBase {
	/**
	 * Preset attr groups that may contain module attrs.
	 *
	 * @since ??
	 *
	 * @var array<int, string>
	 */
	private const PRESET_ATTR_GROUPS = [ 'attrs', 'renderAttrs', 'styleAttrs' ];

	/**
	 * The migration name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_name = 'grid-offset-rules-preset.v1';

	/**
	 * The migration release version string.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_release_version = '5.9.0';

	/**
	 * Run preset migration.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function load(): void {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_migrate_presets' ], 1 );
	}

	/**
	 * Get the migration name.
	 *
	 * @since ??
	 *
	 * @return string The migration name.
	 */
	public static function get_name() {
		return self::$_name;
	}

	/**
	 * Get release version.
	 *
	 * @since ??
	 *
	 * @return string The release version.
	 */
	public static function get_release_version(): string {
		return self::$_release_version;
	}

	/**
	 * Maybe migrate presets if in Visual Builder context.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function maybe_migrate_presets(): void {
		if ( ! (
			Conditions::is_vb_enabled() ||
			Conditions::is_vb_app_window() ||
			Conditions::is_rest_api_request()
		) ) {
			return;
		}

		self::migrate_presets();
	}

	/**
	 * Migrate all presets.
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

		$changes_made    = false;
		$updated_presets = $presets_data;

		if ( isset( $presets_data['module'] ) && is_array( $presets_data['module'] ) ) {
			foreach ( $presets_data['module'] as $module_name => $module_presets ) {
				if ( empty( $module_presets['items'] ) ) {
					continue;
				}

				foreach ( $module_presets['items'] as $preset_id => $preset_item ) {
					$preset_version = $preset_item['version'] ?? '0.0.0';
					if ( ! StringUtility::version_compare( $preset_version, self::$_release_version, '<' ) ) {
						continue;
					}

					$migrated_preset = self::_migrate_preset_item( $preset_item );
					if ( $migrated_preset !== $preset_item ) {
						$changes_made = true;
						$updated_presets['module'][ $module_name ]['items'][ $preset_id ] = $migrated_preset;
					}
				}
			}
		}

		if ( isset( $presets_data['group'] ) && is_array( $presets_data['group'] ) ) {
			foreach ( $presets_data['group'] as $group_name => $group_presets ) {
				if ( empty( $group_presets['items'] ) ) {
					continue;
				}

				foreach ( $group_presets['items'] as $preset_id => $preset_item ) {
					$preset_version = $preset_item['version'] ?? '0.0.0';
					if ( ! StringUtility::version_compare( $preset_version, self::$_release_version, '<' ) ) {
						continue;
					}

					$migrated_preset = self::_migrate_preset_item( $preset_item );
					if ( $migrated_preset !== $preset_item ) {
						$changes_made = true;
						$updated_presets['group'][ $group_name ]['items'][ $preset_id ] = $migrated_preset;
					}
				}
			}
		}

		if ( $changes_made ) {
			GlobalPreset::save_data( $updated_presets );
		}
	}

	/**
	 * Migrate single preset item for import duplicate detection flow.
	 *
	 * @since ??
	 *
	 * @param array  $preset_item Preset item.
	 * @param string $module_name Module name.
	 *
	 * @return array Migrated preset item.
	 */
	public static function migrate_preset_item( array $preset_item, string $module_name ): array {
		return self::_migrate_preset_item( $preset_item );
	}

	/**
	 * Migrate single preset item.
	 *
	 * @since ??
	 *
	 * @param array $preset_item Preset item.
	 *
	 * @return array Migrated preset item.
	 */
	private static function _migrate_preset_item( array $preset_item ): array {
		$migrated_preset = $preset_item;
		$has_changes     = false;

		foreach ( self::PRESET_ATTR_GROUPS as $attr_group ) {
			if ( empty( $preset_item[ $attr_group ] ) || ! is_array( $preset_item[ $attr_group ] ) ) {
				continue;
			}

			if ( ! GridOffsetRulesMigration::attrs_tree_needs_migration( $preset_item[ $attr_group ] ) ) {
				continue;
			}

			$migrated_preset[ $attr_group ] = GridOffsetRulesMigration::migrate_attrs_tree( $preset_item[ $attr_group ] );
			$has_changes                    = true;
		}

		if ( $has_changes ) {
			$migrated_preset['version'] = self::$_release_version;
		}

		return $migrated_preset;
	}
}
