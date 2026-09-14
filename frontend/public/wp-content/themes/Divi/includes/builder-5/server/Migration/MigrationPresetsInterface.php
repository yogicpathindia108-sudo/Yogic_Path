<?php
/**
 * Migration Presets Interface
 *
 * Defines the contract for migration classes that handle preset data migration.
 *
 * @package Divi
 */

namespace ET\Builder\Migration;

/**
 * Migration Presets Interface
 *
 * Defines the contract for migration classes that handle preset data migration
 * from Divi 4 to Divi 5 format. This interface ensures consistent implementation
 * of preset migration functionality.
 *
 * @package Divi
 * @since ??
 */
interface MigrationPresetsInterface {

	/**
	 * Migrate a single preset item from Divi 4 to Divi 5 format.
	 *
	 * This method processes individual preset items, converting their structure
	 * and attributes to be compatible with Divi 5's preset system.
	 *
	 * @since ??
	 *
	 * @param array  $preset_item The preset item data to migrate.
	 * @param string $module_name The name of the module this preset belongs to.
	 *
	 * @return array The migrated preset item in Divi 5 format.
	 */
	public static function migrate_preset_item( array $preset_item, string $module_name ): array;

	/**
	 * Migrate all presets for the implementing module.
	 *
	 * This method handles the bulk migration of all presets associated with
	 * a specific module type, ensuring they are properly converted to Divi 5
	 * format and stored in the appropriate location.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function migrate_presets(): void;
}
