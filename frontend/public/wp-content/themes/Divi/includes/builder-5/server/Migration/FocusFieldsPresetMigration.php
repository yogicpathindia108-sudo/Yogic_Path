<?php
/**
 * Focus Fields Preset Migration
 *
 * Migrates legacy form field focus preset attributes (colors and borders) to
 * the new focus state-aware decoration paths.
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
use ET\Builder\Migration\Utils\MigrationUtils;

/**
 * Focus Fields Preset Migration Class.
 *
 * @since ??
 */
class FocusFieldsPresetMigration extends MigrationPresetsBase {
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
	private static $_name = 'focus-fields-preset.v1';

	/**
	 * The migration release version string.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_release_version = '5.3';

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

		if ( empty( $presets_data ) || ! isset( $presets_data['module'] ) ) {
			return;
		}

		$changes_made    = false;
		$updated_presets = $presets_data;

		foreach ( $presets_data['module'] as $module_name => $module_presets ) {
			if ( empty( $module_presets['items'] ) ) {
				continue;
			}

			foreach ( $module_presets['items'] as $preset_id => $preset_item ) {
				$preset_version        = $preset_item['version'] ?? '0.0.0';
				$effective_module_name = self::_resolve_preset_item_module_name( $preset_item, $module_name );
				if ( ! FocusFieldsMigration::should_migrate_module( $effective_module_name ) ) {
					continue;
				}

				if ( ! StringUtility::version_compare( $preset_version, self::$_release_version, '<' ) ) {
					continue;
				}

				$migrated_preset = self::_migrate_preset_item( $preset_item, $effective_module_name );

				if ( $migrated_preset !== $preset_item ) {
					$changes_made = true;
					$updated_presets['module'][ $module_name ]['items'][ $preset_id ] = $migrated_preset;
				}
			}
		}

		if ( isset( $presets_data['group'] ) && is_array( $presets_data['group'] ) ) {
			foreach ( $presets_data['group'] as $group_name => $group_presets ) {
				if ( empty( $group_presets['items'] ) ) {
					continue;
				}

				foreach ( $group_presets['items'] as $preset_id => $preset_item ) {
					$module_name           = $preset_item['moduleName'] ?? '';
					$module_name           = is_string( $module_name ) ? $module_name : '';
					$preset_version        = $preset_item['version'] ?? '0.0.0';
					$effective_module_name = self::_resolve_preset_item_module_name( $preset_item, $module_name );
					if ( ! FocusFieldsMigration::should_migrate_module( $effective_module_name ) ) {
						continue;
					}

					// Group presets should only be migrated by version gate.
					// Unlike module presets, a group preset can validly target
					// module-level hosts (e.g. module.decoration.boxShadow) and
					// must not be remapped by legacy-attrs detection on newer versions.
					if ( ! StringUtility::version_compare( $preset_version, self::$_release_version, '<' ) ) {
						continue;
					}

					$migrated_preset = self::_migrate_preset_item( $preset_item, $effective_module_name );

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
		return self::_migrate_preset_item( $preset_item, $module_name );
	}

	/**
	 * Migrate single preset item.
	 *
	 * @since ??
	 *
	 * @param array  $preset_item Preset item.
	 * @param string $module_name Module name.
	 *
	 * @return array Migrated preset item.
	 */
	private static function _migrate_preset_item( array $preset_item, string $module_name ): array {
		$migrated_preset       = $preset_item;
		$effective_module_name = self::_resolve_preset_item_module_name( $preset_item, $module_name );
		$preset_type           = $preset_item['type'] ?? '';
		$is_group_preset       = is_string( $preset_type ) && 'group' === $preset_type;
		if ( ! FocusFieldsMigration::should_migrate_module( $effective_module_name ) ) {
			return $migrated_preset;
		}

		// Mark preset as migrated to this version.
		$migrated_preset['version'] = self::$_release_version;

		foreach ( self::PRESET_ATTR_GROUPS as $attr_group ) {
			if ( empty( $preset_item[ $attr_group ] ) || ! is_array( $preset_item[ $attr_group ] ) ) {
				continue;
			}

			$migrated_preset[ $attr_group ] = self::_migrate_preset_attrs_tree(
				$preset_item[ $attr_group ],
				$effective_module_name,
				[
					'skip_contact_form_border_and_shadow_migration' => $is_group_preset,
				]
			);
		}

		$migrated_preset = self::_migrate_woocommerce_field_label_group_presets( $migrated_preset, $effective_module_name );

		return $migrated_preset;
	}

	/**
	 * Resolve effective module name for preset migration.
	 *
	 * Preset bucket keys are not always guaranteed to match canonical module names.
	 * Prefer the preset item's moduleName when available so downstream migration
	 * logic can reliably apply module-specific behavior.
	 *
	 * @since ??
	 *
	 * @param array  $preset_item         Preset item.
	 * @param string $fallback_module_name Fallback module name from caller context.
	 *
	 * @return string Effective module name.
	 */
	private static function _resolve_preset_item_module_name( array $preset_item, string $fallback_module_name ): string {
		$preset_item_module_name = $preset_item['moduleName'] ?? null;

		if ( is_string( $preset_item_module_name ) && '' !== $preset_item_module_name ) {
			return $preset_item_module_name;
		}

		return $fallback_module_name;
	}

	/**
	 * Migrate legacy Woo fieldLabels nested group presets to field labelFont.
	 *
	 * @since ??
	 *
	 * @param array  $preset_item Preset item.
	 * @param string $module_name Module name.
	 *
	 * @return array Migrated preset item.
	 */
	private static function _migrate_woocommerce_field_label_group_presets( array $preset_item, string $module_name ): array {
		if ( ! MigrationUtils::is_woocommerce_field_labels_legacy_module( $module_name ) ) {
			return $preset_item;
		}

		$group_presets = $preset_item['groupPresets'] ?? null;

		if ( ! is_array( $group_presets ) ) {
			return $preset_item;
		}

		$legacy_group_preset_key = '';
		$legacy_group_preset     = null;

		foreach ( self::_get_woocommerce_legacy_field_labels_group_preset_keys() as $legacy_key ) {
			$maybe_legacy_group_preset = $group_presets[ $legacy_key ] ?? null;

			if ( is_array( $maybe_legacy_group_preset ) ) {
				$legacy_group_preset_key = $legacy_key;
				$legacy_group_preset     = $maybe_legacy_group_preset;
				break;
			}
		}

		if ( '' === $legacy_group_preset_key || ! is_array( $legacy_group_preset ) ) {
			return $preset_item;
		}

		$target_group_preset = $group_presets['field.decoration.labelFont'] ?? null;

		if ( ! is_array( $target_group_preset ) ) {
			$group_presets['field.decoration.labelFont'] = $legacy_group_preset;
		} else {
			$legacy_preset_ids = MigrationUtils::normalize_preset_stack_value( $legacy_group_preset['presetId'] ?? '' );
			$target_preset_ids = MigrationUtils::normalize_preset_stack_value( $target_group_preset['presetId'] ?? '' );
			$merged_preset_ids = array_values( array_unique( array_merge( $legacy_preset_ids, $target_preset_ids ) ) );

			$group_presets['field.decoration.labelFont'] = [
				'presetId'  => $merged_preset_ids,
				'groupName' => $target_group_preset['groupName'] ?? $legacy_group_preset['groupName'] ?? '',
			];
		}

		foreach ( self::_get_woocommerce_legacy_field_labels_group_preset_keys() as $legacy_key ) {
			unset( $group_presets[ $legacy_key ] );
		}
		$preset_item['groupPresets'] = $group_presets;

		return $preset_item;
	}

	/**
	 * Migrate preset attrs tree using the content migration logic.
	 *
	 * Keeps preset migration behavior in sync with FocusFieldsMigration for all
	 * supported focus-field legacy attrs.
	 *
	 * @since ??
	 *
	 * @param array  $attrs       Attrs tree.
	 * @param string $module_name Current module name.
	 * @param array  $options     Migration options.
	 *
	 * @return array Migrated attrs tree.
	 */
	private static function _migrate_preset_attrs_tree( array $attrs, string $module_name, array $options = [] ): array {
		return FocusFieldsMigration::migrate_attrs_tree( $attrs, $module_name, $options );
	}

	/**
	 * Get cached WooCommerce legacy field-label group preset keys.
	 *
	 * @since ??
	 *
	 * @return array<int, string> Legacy group preset keys.
	 */
	private static function _get_woocommerce_legacy_field_labels_group_preset_keys(): array {
		static $keys = null;

		if ( ! is_array( $keys ) ) {
			$keys = MigrationUtils::get_woocommerce_legacy_field_labels_group_preset_keys();
		}

		return $keys;
	}
}
