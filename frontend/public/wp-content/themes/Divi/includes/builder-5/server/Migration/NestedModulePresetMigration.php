<?php
/**
 * Nested Module Preset Migration
 *
 * Handles the migration of nested module layout display properties in presets.
 * This includes both module-level presets and group presets (sizing, layout, etc.).
 *
 * Migrations applied:
 * - Column and Pricing Table Item flexType attribute location (module and sizing group presets)
 * - Contact Field fullwidth to flexType migration
 * - Email Optin layout to flexDirection migration (simplified without parent column context)
 * - Grid modules (Portfolio, Blog, Filterable Portfolio, Gallery) layout to grid display (flexType-based column count)
 *
 * Note: Unlike the NestedModuleMigration which handles content, this preset migration
 * uses simplified logic without parent column context. Grid modules use flexType to determine
 * column count, and Email Optin uses a simplified layout-to-flexDirection mapping.
 * Team Member module is excluded as it has parent-dependent migration logic.
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
 * Nested Module Preset Migration Class.
 *
 * @since ??
 */
class NestedModulePresetMigration extends MigrationPresetsBase {


	/**
	 * The migration name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_name = 'nested-modules-preset.v1';


	/**
	 * The nested module release version string.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_release_version = '5.0.0-public-beta.1';

	/**
	 * Run the preset migration.
	 *
	 * @since ??
	 */
	public static function load(): void {
		// Hook into the visual builder initialization to migrate presets.
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
	 * Get the release version for this migration.
	 *
	 * @since ??
	 *
	 * @return string The release version.
	 */
	public static function get_release_version(): string {
		return self::$_release_version;
	}

	/**
	 * Maybe migrate presets if visual builder is loading.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function maybe_migrate_presets(): void {
		// Only run during visual builder contexts.
		if ( ! ( Conditions::is_vb_enabled()
			|| Conditions::is_vb_app_window()
			|| Conditions::is_rest_api_request() )
		) {
			return;
		}

		self::migrate_presets();
	}

	/**
	 * Migrate presets that need nested module updates.
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

		// Performance optimization: Check if any presets need migration before processing.
		if ( ! self::_has_presets_needing_migration( $presets_data ) ) {
			return;
		}

		$changes_made    = false;
		$updated_presets = $presets_data;

		// Process module presets.
		if ( isset( $presets_data['module'] ) ) {
			foreach ( $presets_data['module'] as $module_name => $module_presets ) {
				if ( empty( $module_presets['items'] ) ) {
					continue;
				}

				// Process each preset item for this module.
				foreach ( $module_presets['items'] as $preset_id => $preset_item ) {
					$preset_version = $preset_item['version'] ?? '0.0.0';

					// Check if preset needs migration based on version comparison.
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

		// Process group presets (sizing, layout, etc.).
		if ( isset( $presets_data['group'] ) ) {
			foreach ( $presets_data['group'] as $group_name => $group_data ) {
				if ( empty( $group_data['items'] ) ) {
					continue;
				}

				// Process each preset item in this group.
				foreach ( $group_data['items'] as $preset_id => $preset_item ) {
					$preset_version = $preset_item['version'] ?? '0.0.0';
					$module_name    = $preset_item['moduleName'] ?? '';

					// Check if preset needs migration based on version comparison.
					if ( StringUtility::version_compare( $preset_version, self::$_release_version, '<' ) ) {
						$migrated_preset = self::_migrate_preset_item( $preset_item, $module_name );

						if ( $migrated_preset !== $preset_item ) {
							$changes_made = true;
							$updated_presets['group'][ $group_name ]['items'][ $preset_id ] = $migrated_preset;
						}
					}
				}
			}
		}

		// Save the updated presets if any changes were made.
		if ( $changes_made ) {
			GlobalPreset::save_data( $updated_presets );
		}
	}

	/**
	 * Migrate a single preset item for individual processing.
	 *
	 * This public method allows individual preset items to be migrated
	 * without processing the entire site's preset database. Used for
	 * duplicate detection during preset imports.
	 *
	 * @since ??
	 *
	 * @param array  $preset_item The preset item to migrate.
	 * @param string $module_name The module name for this preset.
	 *
	 * @return array The migrated preset item.
	 */
	public static function migrate_preset_item( array $preset_item, string $module_name ): array {
		return self::_migrate_preset_item( $preset_item, $module_name );
	}

	/**
	 * Check if any presets need migration to avoid unnecessary processing.
	 *
	 * @since ??
	 *
	 * @param array $presets_data The presets data to check.
	 *
	 * @return bool True if any presets need migration, false otherwise.
	 */
	private static function _has_presets_needing_migration( array $presets_data ): bool {
		$release_version = self::get_release_version();

		// Check module presets.
		if ( isset( $presets_data['module'] ) ) {
			foreach ( $presets_data['module'] as $module_presets ) {
				if ( empty( $module_presets['items'] ) ) {
					continue;
				}

				foreach ( $module_presets['items'] as $preset_item ) {
					$preset_version = $preset_item['version'] ?? '0.0.0';

					// If we find any preset with an older version, migration is needed.
					if ( StringUtility::version_compare( $preset_version, $release_version, '<' ) ) {
						return true;
					}
				}
			}
		}

		// Check group presets.
		if ( isset( $presets_data['group'] ) ) {
			foreach ( $presets_data['group'] as $group_data ) {
				if ( empty( $group_data['items'] ) ) {
					continue;
				}

				foreach ( $group_data['items'] as $preset_item ) {
					$preset_version = $preset_item['version'] ?? '0.0.0';

					// If we find any preset with an older version, migration is needed.
					if ( StringUtility::version_compare( $preset_version, $release_version, '<' ) ) {
						return true;
					}
				}
			}
		}

		// No presets need migration.
		return false;
	}

	/**
	 * Migrate a single preset item's attributes.
	 *
	 * @since ??
	 *
	 * @param array  $preset_item The preset item to migrate.
	 * @param string $module_name The module name for this preset.
	 *
	 * @return array The migrated preset item.
	 */
	private static function _migrate_preset_item( array $preset_item, string $module_name ): array {
		$migrated_preset     = $preset_item;
		$preset_changes_made = false;

		// Update the version to the current migration version.
		$migrated_preset['version'] = self::$_release_version;

		// Migrate each attribute group (attrs, renderAttrs, styleAttrs).
		$attr_groups = [ 'attrs', 'renderAttrs', 'styleAttrs' ];

		foreach ( $attr_groups as $attr_group ) {
			if ( empty( $preset_item[ $attr_group ] ) ) {
				continue;
			}

			$migrated_attrs = self::_migrate_preset_attributes(
				$preset_item[ $attr_group ],
				$module_name
			);

			if ( $migrated_attrs !== $preset_item[ $attr_group ] ) {
				$preset_changes_made            = true;
				$migrated_preset[ $attr_group ] = $migrated_attrs;
			}
		}

		return $migrated_preset;
	}

	/**
	 * Migrate preset attributes for nested module patterns.
	 *
	 * @since ??
	 *
	 * @param array  $attrs       The preset attributes to migrate.
	 * @param string $module_name The module name for context.
	 *
	 * @return array The migrated attributes.
	 */
	private static function _migrate_preset_attributes( array $attrs, string $module_name ): array {
		$migrated_attrs = $attrs;

		// Migration 1: Column and Pricing Table Item flexType attribute location.
		if ( in_array( $module_name, [ 'divi/column', 'divi/column-inner', 'divi/pricing-tables-item' ], true ) ) {
			$migrated_attrs = self::_migrate_column_flex_type( $migrated_attrs );
		}

		// Migration 2: Contact Field fullwidth to flexType migration.
		if ( 'divi/contact-field' === $module_name ) {
			$migrated_attrs = self::_migrate_contact_field_fullwidth( $migrated_attrs );
		}

		// Migration 3: Email Optin layout to flexDirection migration.
		if ( 'divi/signup' === $module_name ) {
			$migrated_attrs = self::_migrate_email_optin_layout( $migrated_attrs );
		}

		// Migration 4: Grid modules (Portfolio, Blog, Filterable Portfolio, Gallery).
		if ( in_array( $module_name, [ 'divi/portfolio', 'divi/blog', 'divi/filterable-portfolio', 'divi/gallery' ], true ) ) {
			$migrated_attrs = self::_migrate_grid_module_preset( $migrated_attrs, $module_name );
		}

		return $migrated_attrs;
	}

	/**
	 * Migrate module-level flexType attribute from old to new location.
	 *
	 * Migrates module.advanced.flexType to module.decoration.sizing.flexType
	 * This change makes flexType a proper sub-attribute of the sizing group.
	 *
	 * Applies to: Column, Column Inner, and Pricing Tables Item modules.
	 * Note: Does NOT apply to nested module attributes (e.g., portfolioGrid.advanced.flexType,
	 * sidebarWidgets.advanced.flexType) which remain at their current locations.
	 *
	 * @since ??
	 *
	 * @param array $attrs The preset attributes to migrate.
	 *
	 * @return array The migrated attributes.
	 */
	private static function _migrate_column_flex_type( array $attrs ): array {
		// Check if the old flexType attribute exists.
		$old_flex_type = $attrs['module']['advanced']['flexType'] ?? null;

		// If no old flexType attribute exists, no migration needed.
		if ( ! $old_flex_type ) {
			return $attrs;
		}

		// Initialize the new structure if it doesn't exist.
		if ( ! isset( $attrs['module'] ) ) {
			$attrs['module'] = [];
		}
		if ( ! isset( $attrs['module']['decoration'] ) ) {
			$attrs['module']['decoration'] = [];
		}
		if ( ! isset( $attrs['module']['decoration']['sizing'] ) ) {
			$attrs['module']['decoration']['sizing'] = [];
		}

		// Migrate all breakpoints and states.
		// Old structure: module.advanced.flexType.{breakpoint}.{state} = "12_24".
		// Example: module.advanced.flexType.desktop.value = "12_24".
		// New structure: module.decoration.sizing.{breakpoint}.{state}.flexType = "12_24".
		// Example: module.decoration.sizing.desktop.value.flexType = "12_24".
		foreach ( $old_flex_type as $breakpoint => $breakpoint_data ) {
			if ( ! is_array( $breakpoint_data ) ) {
				continue;
			}

			// Initialize breakpoint if not exists.
			if ( ! isset( $attrs['module']['decoration']['sizing'][ $breakpoint ] ) ) {
				$attrs['module']['decoration']['sizing'][ $breakpoint ] = [];
			}

			foreach ( $breakpoint_data as $state => $flex_type_value ) {
				// $state is like 'value', 'hover', etc.
				// $flex_type_value is the actual flexType like '12_24'.
				if ( ! isset( $attrs['module']['decoration']['sizing'][ $breakpoint ][ $state ] ) ) {
					$attrs['module']['decoration']['sizing'][ $breakpoint ][ $state ] = [];
				}

				// Structure: sizing.{breakpoint}.{state}.flexType (where state is 'value', 'hover', etc.).
				$attrs['module']['decoration']['sizing'][ $breakpoint ][ $state ]['flexType'] = $flex_type_value;
			}
		}

		// Remove the old flexType attribute from module.advanced.
		unset( $attrs['module']['advanced']['flexType'] );

		return $attrs;
	}

	/**
	 * Migrate Contact Field fullwidth attribute to flexType.
	 *
	 * Migrates the deprecated fieldItem.advanced.fullwidth attribute to
	 * module.decoration.sizing.flexType.
	 *
	 * Migration logic:
	 * - If fullwidth is 'on': No flexType is set (defaults to full width)
	 * - If fullwidth is 'off' or not set: Set flexType to '12_24' (50% width)
	 * - Remove the deprecated fullwidth attribute
	 *
	 * @since ??
	 *
	 * @param array $attrs The preset attributes to migrate.
	 *
	 * @return array The migrated attributes.
	 */
	private static function _migrate_contact_field_fullwidth( array $attrs ): array {
		// Get the fullwidth attribute.
		$fullwidth_attr = $attrs['fieldItem']['advanced']['fullwidth'] ?? null;

		// If no fullwidth attribute exists, no migration needed.
		if ( ! $fullwidth_attr ) {
			return $attrs;
		}

		// Check desktop breakpoint value.
		$desktop_value = null;
		if ( is_array( $fullwidth_attr ) && isset( $fullwidth_attr['desktop']['value'] ) ) {
			$desktop_value = $fullwidth_attr['desktop']['value'];
		} elseif ( is_string( $fullwidth_attr ) ) {
			// D4 format.
			$desktop_value = $fullwidth_attr;
		}

		// If fullwidth is NOT 'on', set flexType to 12_24 (50% width).
		if ( 'on' !== $desktop_value ) {
			// Initialize the structure if it doesn't exist.
			if ( ! isset( $attrs['module'] ) ) {
				$attrs['module'] = [];
			}
			if ( ! isset( $attrs['module']['decoration'] ) ) {
				$attrs['module']['decoration'] = [];
			}
			if ( ! isset( $attrs['module']['decoration']['sizing'] ) ) {
				$attrs['module']['decoration']['sizing'] = [];
			}
			if ( ! isset( $attrs['module']['decoration']['sizing']['desktop'] ) ) {
				$attrs['module']['decoration']['sizing']['desktop'] = [];
			}
			if ( ! isset( $attrs['module']['decoration']['sizing']['desktop']['value'] ) ) {
				$attrs['module']['decoration']['sizing']['desktop']['value'] = [];
			}

			$attrs['module']['decoration']['sizing']['desktop']['value']['flexType'] = '12_24';
		}

		// Clear the deprecated fullwidth attribute by setting it to null.
		// This will remove it from the attributes during serialization.
		$attrs['fieldItem']['advanced']['fullwidth'] = null;

		return $attrs;
	}

	/**
	 * Migrate Email Optin layout attribute to flexDirection.
	 *
	 * Migrates the deprecated module.advanced.layout attribute to
	 * module.decoration.layout.flexDirection and sets display to flex.
	 * Removes the old layout attribute after migration.
	 *
	 * For presets, we use a simplified mapping without parent column context.
	 * We only set flexDirection for non-default values (not 'row'):
	 * - left_right: No flex direction set (row is the CSS default)
	 * - right_left: row-reverse (Body On Right, Form On Left)
	 * - top_bottom: column (Body On Top, Form On Bottom)
	 * - bottom_top: column-reverse (Form On Top, Body On Bottom)
	 *
	 * @since ??
	 *
	 * @param array $attrs The preset attributes to migrate.
	 *
	 * @return array The migrated attributes.
	 */
	private static function _migrate_email_optin_layout( array $attrs ): array {
		// Get the layout attribute.
		$layout_attr = $attrs['module']['advanced']['layout'] ?? null;

		// If no layout attribute exists, no migration needed.
		if ( ! $layout_attr ) {
			return $attrs;
		}

		// Extract the layout value.
		$layout_value = null;
		if ( is_array( $layout_attr ) && isset( $layout_attr['desktop']['value'] ) ) {
			// D5 format: nested responsive structure.
			$layout_value = $layout_attr['desktop']['value'];
		} elseif ( is_string( $layout_attr ) ) {
			// D4 format: simple string.
			$layout_value = $layout_attr;
		}

		// If no valid layout value, no migration needed.
		if ( ! $layout_value ) {
			return $attrs;
		}

		// Map layout value to flex direction.
		// Returns null for 'left_right' since 'row' is the CSS default.
		$flex_direction = self::_get_flex_direction_for_preset_layout( $layout_value );

		// If flex direction is null (default case), just remove the deprecated attribute.
		if ( null === $flex_direction ) {
			$attrs['module']['advanced']['layout'] = null;
			return $attrs;
		}

		// Initialize the structure if it doesn't exist.
		if ( ! isset( $attrs['module'] ) ) {
			$attrs['module'] = [];
		}
		if ( ! isset( $attrs['module']['decoration'] ) ) {
			$attrs['module']['decoration'] = [];
		}
		if ( ! isset( $attrs['module']['decoration']['layout'] ) ) {
			$attrs['module']['decoration']['layout'] = [];
		}
		if ( ! isset( $attrs['module']['decoration']['layout']['desktop'] ) ) {
			$attrs['module']['decoration']['layout']['desktop'] = [];
		}
		if ( ! isset( $attrs['module']['decoration']['layout']['desktop']['value'] ) ) {
			$attrs['module']['decoration']['layout']['desktop']['value'] = [];
		}

		// Set the flex direction and display.
		$attrs['module']['decoration']['layout']['desktop']['value']['display']       = 'flex';
		$attrs['module']['decoration']['layout']['desktop']['value']['flexDirection'] = $flex_direction;

		// Remove the deprecated layout attribute by setting it to null.
		// This will remove it from the attributes during serialization.
		$attrs['module']['advanced']['layout'] = null;

		return $attrs;
	}

	/**
	 * Get the flex direction for a given Email Optin layout value in presets.
	 *
	 * This is a simplified version for presets that doesn't consider parent column context.
	 * Returns null for the default case (left_right) since row is the CSS default.
	 *
	 * @since ??
	 *
	 * @param string $layout_value The layout value from the Form Layout option.
	 *
	 * @return string|null The flex direction to apply, or null for default.
	 */
	private static function _get_flex_direction_for_preset_layout( string $layout_value ): ?string {
		switch ( $layout_value ) {
			case 'left_right':
				// Body On Left, Form On Right.
				// Don't set flexDirection - 'row' is the CSS default.
				return null;

			case 'right_left':
				// Body On Right, Form On Left.
				return 'row-reverse';

			case 'bottom_top':
				// Form On Top, Body On Bottom.
				return 'column-reverse';

			case 'top_bottom':
			default:
				// Body On Top, Form On Bottom.
				return 'column';
		}
	}

	/**
	 * Migrate Grid module layout for presets.
	 *
	 * Simplified version for presets that uses flexType to determine column count,
	 * without parent column context.
	 *
	 * @since ??
	 *
	 * @param array  $attrs       The preset attributes to migrate.
	 * @param string $module_name The module name.
	 *
	 * @return array The migrated attributes.
	 */
	private static function _migrate_grid_module_preset( array $attrs, string $module_name ): array {
		// Get layout configuration based on module type.
		$layout_config = self::_get_grid_layout_config_for_preset( $module_name, $attrs );

		if ( ! $layout_config ) {
			return $attrs;
		}

		$layout_value         = $layout_config['layout_value'];
		$flex_type_value      = $layout_config['flex_type_value'];
		$grid_attr_path       = $layout_config['grid_attr_path'];
		$layout_attr_path     = $layout_config['layout_attr_path'];
		$layout_display_value = $layout_config['layout_display_value'] ?? null;

		// Determine if this is a grid or fullwidth layout.
		$is_fullwidth = ( 'fullwidth' === $layout_value || 'on' === $layout_value );
		$is_grid      = ( 'grid' === $layout_value || 'off' === $layout_value );

		if ( ! $is_fullwidth && ! $is_grid ) {
			// No migration needed if layout value is not recognized.
			return $attrs;
		}

		// Special case: If module is set to Grid and Layout Style is already 'grid' (not 'flex'),
		// then the module is already in grid mode and we don't need to migrate.
		// This means the module was already migrated or explicitly set to use grid layout.
		if ( $is_grid && 'grid' === $layout_display_value ) {
			// Skip migration - module is already in grid mode with grid layout style.
			return $attrs;
		}

		// Special case: Gallery fullwidth presets preserve the fullwidth attribute.
		// For Gallery, module.advanced.fullwidth controls slider vs grid mode (behavior, not just layout display).
		if ( $is_fullwidth && 'divi/gallery' === $module_name ) {
			// Skip migration - preserve Gallery fullwidth attribute for slider mode.
			return $attrs;
		}

		// Clear the old layout attribute (except for Gallery which needs to keep it).
		// For Gallery, module.advanced.fullwidth controls slider vs grid mode.
		$layout_path_parts       = explode( '.', $layout_attr_path );
		$layout_path_parts_count = count( $layout_path_parts );
		$current                 = &$attrs;
		for ( $i = 0; $i < $layout_path_parts_count - 1; $i++ ) {
			if ( ! isset( $current[ $layout_path_parts[ $i ] ] ) ) {
				break;
			}
			$current = &$current[ $layout_path_parts[ $i ] ];
		}
		if ( isset( $current[ $layout_path_parts[ $layout_path_parts_count - 1 ] ] )
			&& 'divi/gallery' !== $module_name
		) {
			$current[ $layout_path_parts[ $layout_path_parts_count - 1 ] ] = null;
		}

		// Migrate layout based on the layout value.
		if ( $is_grid ) {
			// For presets, use flexType to determine column count (no parent column context).
			// This handles the case where Layout is Grid but Layout Style is flex.
			// Determine default column count based on module type.
			// Gallery module default is 4 columns, others default to 3.
			$default_column_count = ( 'divi/gallery' === $module_name ) ? 4 : 3;
			$grid_column_count    = $flex_type_value
				? MigrationUtils::map_flex_type_to_column_count( $flex_type_value )
				: $default_column_count;

			// Build layout config for desktop only (presets don't have parent column context for responsive).
			$layout_config_value = [
				'desktop' => [
					'value' => [
						'display'         => 'grid',
						'gridColumnCount' => (string) $grid_column_count,
					],
				],
			];

			// Set the grid layout in the appropriate path.
			$grid_path_parts = explode( '.', $grid_attr_path );
			$current         = &$attrs;
			foreach ( $grid_path_parts as $part ) {
				if ( ! isset( $current[ $part ] ) ) {
					$current[ $part ] = [];
				}
				$current = &$current[ $part ];
			}
			if ( ! isset( $current['decoration'] ) ) {
				$current['decoration'] = [];
			}
			$current['decoration']['layout'] = $layout_config_value;
		}

		return $attrs;
	}

	/**
	 * Get layout configuration for a grid module preset.
	 *
	 * @since ??
	 *
	 * @param string $module_name The module name.
	 * @param array  $attrs       The preset attributes.
	 *
	 * @return array|null Layout configuration or null if not applicable.
	 */
	private static function _get_grid_layout_config_for_preset( string $module_name, array $attrs ): ?array {
		$config = [];

		switch ( $module_name ) {
			case 'divi/portfolio':
				$layout_attr         = $attrs['portfolio']['advanced']['layout'] ?? null;
				$flex_type_attr      = $attrs['portfolioGrid']['advanced']['flexType'] ?? null;
				$layout_display_attr = $attrs['portfolioGrid']['decoration']['layout'] ?? null;

				$config = [
					'layout_attr_path' => 'portfolio.advanced.layout',
					'grid_attr_path'   => 'portfolioGrid',
				];
				break;

			case 'divi/blog':
				$layout_attr         = $attrs['fullwidth']['advanced']['enable'] ?? null;
				$flex_type_attr      = $attrs['blogGrid']['advanced']['flexType'] ?? null;
				$layout_display_attr = $attrs['blogGrid']['decoration']['layout'] ?? null;

				$config = [
					'layout_attr_path' => 'fullwidth.advanced.enable',
					'grid_attr_path'   => 'blogGrid',
				];
				break;

			case 'divi/filterable-portfolio':
				$layout_attr         = $attrs['portfolio']['advanced']['layout'] ?? null;
				$flex_type_attr      = $attrs['portfolioGrid']['advanced']['flexType'] ?? null;
				$layout_display_attr = $attrs['portfolioGrid']['decoration']['layout'] ?? null;

				$config = [
					'layout_attr_path' => 'portfolio.advanced.layout',
					'grid_attr_path'   => 'portfolioGrid',
				];
				break;

			case 'divi/gallery':
				$layout_attr         = $attrs['module']['advanced']['fullwidth'] ?? null;
				$flex_type_attr      = $attrs['galleryGrid']['advanced']['flexType'] ?? null;
				$layout_display_attr = $attrs['galleryGrid']['decoration']['layout'] ?? null;

				$config = [
					'layout_attr_path' => 'module.advanced.fullwidth',
					'grid_attr_path'   => 'galleryGrid',
				];
				break;

			default:
				return null;
		}

		// Extract layout value from responsive structure.
		if ( is_array( $layout_attr ) ) {
			// D5 format: nested responsive structure.
			$layout_value = $layout_attr['desktop']['value'] ?? null;
		} elseif ( is_string( $layout_attr ) ) {
			// D4 format or simple string.
			$layout_value = $layout_attr;
		} else {
			$layout_value = null;
		}

		// Extract flex type value from responsive structure.
		if ( is_array( $flex_type_attr ) ) {
			// D5 format: flexType is directly a responsive structure.
			// Path is: flexType.desktop.value.
			$flex_type_value = $flex_type_attr['desktop']['value'] ?? null;
		} elseif ( is_string( $flex_type_attr ) ) {
			// D4 format or simple string.
			$flex_type_value = $flex_type_attr;
		} else {
			$flex_type_value = null;
		}

		// Extract layout display value (the current Layout Style setting).
		$layout_display_value = null;
		if ( is_array( $layout_display_attr ) ) {
			// Check for D5 format: desktop.value.display.
			$layout_display_value = $layout_display_attr['desktop']['value']['display'] ?? null;
		}

		$config['layout_value']         = $layout_value;
		$config['flex_type_value']      = $flex_type_value;
		$config['layout_display_value'] = $layout_display_value;

		return $config;
	}
}
