<?php
/**
 * Font Capitalization Preset Migration
 *
 * Migrates legacy capitalization tokens from `font.style` to
 * `font.capitalization` inside global preset attrs.
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
 * Font Capitalization Preset Migration Class.
 *
 * @since ??
 */
class FontCapitalizationPresetMigration extends MigrationPresetsBase {
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
	private static $_name = 'font-capitalization-preset.v1';

	/**
	 * The migration release version string.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_release_version = '5.8.1';

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
	 * Maybe migrate presets when builder contexts are active.
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

		$updated_presets = $presets_data;
		$changes_made    = false;

		foreach ( [ 'module', 'group' ] as $preset_scope ) {
			if ( empty( $presets_data[ $preset_scope ] ) || ! is_array( $presets_data[ $preset_scope ] ) ) {
				continue;
			}

			foreach ( $presets_data[ $preset_scope ] as $bucket_name => $bucket_data ) {
				$preset_items = $bucket_data['items'] ?? null;

				if ( ! is_array( $preset_items ) ) {
					continue;
				}

				foreach ( $preset_items as $preset_id => $preset_item ) {
					if ( ! is_array( $preset_item ) ) {
						continue;
					}

					$preset_version = $preset_item['version'] ?? '0.0.0';
					if ( ! StringUtility::version_compare( (string) $preset_version, self::$_release_version, '<' ) ) {
						continue;
					}

					$migrated_preset = self::_migrate_preset_item( $preset_item );

					if ( $migrated_preset === $preset_item ) {
						continue;
					}

					$updated_presets[ $preset_scope ][ $bucket_name ]['items'][ $preset_id ] = $migrated_preset;
					$changes_made = true;
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
	 * @return array
	 */
	public static function migrate_preset_item( array $preset_item, string $module_name ): array {
		return self::_migrate_preset_item( $preset_item );
	}

	/**
	 * Migrate one preset item.
	 *
	 * @since ??
	 *
	 * @param array $preset_item Preset item.
	 *
	 * @return array
	 */
	private static function _migrate_preset_item( array $preset_item ): array {
		$migrated_preset = $preset_item;
		$did_migrate     = false;

		foreach ( self::PRESET_ATTR_GROUPS as $attr_group ) {
			$attrs = $preset_item[ $attr_group ] ?? null;

			if ( ! is_array( $attrs ) ) {
				continue;
			}

			if ( ! FontCapitalizationMigration::has_legacy_capitalization_attrs_tree( $attrs ) ) {
				continue;
			}

			$migrated_preset[ $attr_group ] = FontCapitalizationMigration::migrate_attrs_tree( $attrs );
			$did_migrate                    = true;
		}

		if ( $did_migrate ) {
			$migrated_preset['version'] = self::$_release_version;
		}

		return $migrated_preset;
	}
}
