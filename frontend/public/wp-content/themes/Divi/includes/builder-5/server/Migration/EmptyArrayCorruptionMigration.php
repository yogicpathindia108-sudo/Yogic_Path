<?php
/**
 * Empty Array Corruption Migration
 *
 * Fixes corrupted module attributes where empty objects {} were incorrectly saved as empty arrays []
 * during JSON encoding/decoding in PHP. This corruption prevented users from editing font properties
 * and other nested attributes.
 *
 * The corruption occurred because:
 * 1. JavaScript creates attributes with empty objects: {desktop: {value: {}}}
 * 2. PHP JSON decode converts empty JS objects to empty PHP arrays: {desktop: {value: []}}
 * 3. These empty arrays were not properly filtered out before save
 * 4. When merged via array_replace_recursive, arrays replaced objects causing corruption
 *
 * This migration finds and removes all empty arrays at 'value' keys in responsive structures.
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Migration;

use ET\Builder\Framework\Utility\StringUtility;
use ET\Builder\Migration\MigrationContext;
use ET\Builder\Migration\Utils\MigrationUtils;
use ET\Builder\Migration\MigrationContentBase;

/**
 * Empty Array Corruption Migration Class.
 *
 * @since ??
 */
class EmptyArrayCorruptionMigration extends MigrationContentBase {

	/**
	 * The migration name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_name = 'empty-array-corruption.v1';

	/**
	 * The migration release version string.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_release_version = '5.0.0-public-beta.7.1';

	/**
	 * Run the migration.
	 *
	 * @since ??
	 */
	public static function load(): void {
		add_filter( 'divi_framework_portability_import_migrated_post_content', [ __CLASS__, 'migrate_import_content' ] );
		add_action( 'et_fb_load_raw_post_content', [ __CLASS__, 'migrate_vb_content' ], 10, 2 );
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
	 * Migrate the import content.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string The migrated content.
	 */
	public static function migrate_import_content( $content ) {
		return self::_migrate_the_content( $content );
	}

	/**
	 * Migrate the Visual Builder content.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 * @return string The migrated content.
	 */
	public static function migrate_vb_content( $content ) {
		return self::_migrate_the_content( $content );
	}

	/**
	 * Migrate the content.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string The migrated content.
	 */
	private static function _migrate_the_content( $content ) {
		// Quick check: Skip if no modules need migration based on version.
		if ( ! MigrationUtils::content_needs_migration( $content, self::$_release_version ) ) {
			return $content;
		}

		// Quick check: Skip if content doesn't have the corruption signature.
		if ( ! self::_content_needs_migration( $content ) ) {
			return $content;
		}

		// Handle block-based migration.
		$content = self::_migrate_block_content( $content );

		return $content;
	}

	/**
	 * Migrate block-based content (D5 blocks).
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string The migrated content.
	 */
	private static function _migrate_block_content( $content ) {
		// Only process if content contains D5 blocks.
		if ( ! self::has_divi_block( $content ) || '<!-- wp:divi/placeholder -->' === $content ) {
			return $content;
		}

		// Quick check: Skip if no modules need migration based on version.
		if ( ! MigrationUtils::content_needs_migration( $content, self::$_release_version ) ) {
			return $content;
		}

		// Quick check: Skip if content doesn't have the corruption signature.
		if ( ! self::_content_needs_migration( $content ) ) {
			return $content;
		}

		// Ensure the content is wrapped by wp:divi/placeholder if not empty.
		$content = MigrationUtils::ensure_placeholder_wrapper( $content );

		// Start migration context to prevent global layout expansion during migration.
		MigrationContext::start();

		try {
			$flat_objects = MigrationUtils::parse_serialized_post_into_flat_module_object( $content, self::$_name );

			$changes_made = false;

			foreach ( $flat_objects as $module_id => $module_data ) {
				// Check if module needs migration based on version comparison.
				if (
					StringUtility::version_compare( $module_data['props']['attrs']['builderVersion'] ?? '0.0.0', self::$_release_version, '<' )
				) {
					// Check if module attrs need fixing.
					if ( empty( $module_data['props']['attrs'] ) || ! is_array( $module_data['props']['attrs'] ) ) {
						continue;
					}

					$migrated_attrs = self::_fix_empty_arrays_in_attrs( $module_data['props']['attrs'] );

					if ( $migrated_attrs !== $module_data['props']['attrs'] ) {
						$flat_objects[ $module_id ]['props']['attrs']                   = $migrated_attrs;
						$flat_objects[ $module_id ]['props']['attrs']['builderVersion'] = self::$_release_version;
						$changes_made = true;
					}
				}
			}

			if ( $changes_made ) {
				// Serialize the flat objects back into the content.
				$new_content = MigrationUtils::serialize_flat_objects( $flat_objects );
			} else {
				$new_content = $content;
			}

			return $new_content;
		} finally {
			// Always end migration context, even if an exception occurs.
			MigrationContext::end();
		}
	}

	/**
	 * Migrate block content.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string The migrated content.
	 */
	public static function migrate_content_block( string $content ): string {
		if ( ! self::has_divi_block( $content ) ) {
			return $content;
		}

		return self::_migrate_block_content( $content );
	}

	/**
	 * Check if content needs migration.
	 *
	 * @since ??
	 *
	 * @param string $content The content to check.
	 *
	 * @return bool True if content needs migration.
	 */
	private static function _content_needs_migration( string $content ): bool {
		// Quick check: look for empty arrays in value keys.
		// This is a fast pre-check before parsing the entire content.
		return str_contains( $content, '"value":[]' );
	}

	/**
	 * Migrate shortcode content.
	 *
	 * This migration only applies to block content, so shortcode content
	 * is returned unchanged.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string The migrated content.
	 */
	public static function migrate_content_shortcode( string $content ): string {
		return $content;
	}

	/**
	 * Recursively fix empty arrays in attributes.
	 *
	 * Removes empty arrays at 'value' keys in responsive structures like:
	 * - desktop.value: []
	 * - tablet.value: []
	 * - phone.value: []
	 * - etc.
	 *
	 * @since ??
	 *
	 * @param array $attrs The attributes to fix.
	 *
	 * @return array The fixed attributes.
	 */
	private static function _fix_empty_arrays_in_attrs( array $attrs ): array {
		foreach ( $attrs as $key => $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}

			// If this is a 'value' key with an empty array, remove it.
			// This handles corruption like desktop.value: [] or tablet.value: [].
			if ( 'value' === $key && empty( $value ) ) {
				unset( $attrs[ $key ] );
				continue;
			}

			// Recursively process nested arrays.
			$fixed_value = self::_fix_empty_arrays_in_attrs( $value );

			// If the fixed value is empty after removing corrupted arrays, remove the parent key too.
			if ( empty( $fixed_value ) ) {
				unset( $attrs[ $key ] );
			} else {
				$attrs[ $key ] = $fixed_value;
			}
		}

		return $attrs;
	}
}
