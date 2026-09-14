<?php
/**
 * Preset Stack Migration
 *
 * Handles the migration of preset attributes from string format to array format.
 * Previously, module and group presets were stored as strings (e.g., "1233").
 * With stacked presets, they are now stored as arrays (e.g., ["1233"]).
 *
 * This migration converts:
 * - modulePreset: from string "1233" to array ["1233"]
 * - groupPreset[groupId].presetId: from string "1233" to array ["1233"]
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Migration;

use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\StringUtility;
use ET\Builder\Migration\MigrationContentBase;
use ET\Builder\Migration\MigrationContext;
use ET\Builder\Migration\Utils\MigrationUtils;

/**
 * Preset Stack Migration Class.
 *
 * @since ??
 */
class PresetStackMigration extends MigrationContentBase {

	/**
	 * The migration name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_name = 'preset-stack.v1';

	/**
	 * The preset stack release version string.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_release_version = '5.0.0-public-beta.2';

	/**
	 * Run the preset stack migration.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function load(): void {
		/**
		 * Hook into the portability import process to migrate preset attributes.
		 *
		 * This filter ensures that imported content has preset attributes
		 * converted from string format to array format for stacked presets support.
		 *
		 * @see PresetStackMigration::migrate_import_content()
		 */
		add_filter( 'divi_framework_portability_import_migrated_post_content', [ __CLASS__, 'migrate_import_content' ] );

		add_action( 'wp', [ __CLASS__, 'migrate_fe_content' ] );
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
	 * Migrate the content for the frontend.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function migrate_fe_content(): void {
		// Return if it not FE.
		if ( ! Conditions::is_fe_and_should_migrate_content() ) {
			return;
		}

		$content = MigrationUtils::get_current_content();

		if ( empty( $content ) ) {
			return;
		}

		$migrated_content = self::_migrate_the_content( $content );

		if ( $migrated_content !== $content ) {
			global $post;

			if ( $post instanceof \WP_Post ) {
				// Update the post content with migrated content.
				$post->post_content = $migrated_content;
			}
		}
	}

	/**
	 * Migrate the content for the visual builder.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 * @param int    $post_id The post ID.
	 *
	 * @return string The migrated content.
	 */
	public static function migrate_vb_content( string $content, int $post_id ): string {
		return self::_migrate_the_content( $content );
	}

	/**
	 * Migrate the content (both shortcode and block).
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string The migrated content.
	 */
	private static function _migrate_the_content( string $content ): string {
		if ( '' === $content ) {
			return $content;
		}

		// Migrate both shortcode and block content.
		return self::migrate_content_both( $content );
	}

	/**
	 * Migrate content from shortcode format.
	 *
	 * @since ??
	 *
	 * @param string $content The shortcode content to migrate.
	 *
	 * @return string The migrated content (currently unchanged).
	 */
	public static function migrate_content_shortcode( string $content ): string {
		// Preset stack migrations only apply to block-based content.
		// D4 shortcodes are converted to D5 blocks before migrations run.
		return $content;
	}

	/**
	 * Migrate content from block format.
	 *
	 * This method handles the migration of preset attributes in block-based format.
	 *
	 * @since ??
	 *
	 * @param string $content The block content to migrate.
	 *
	 * @return string The migrated content.
	 */
	public static function migrate_content_block( string $content ): string {
		if ( ! self::has_divi_block( $content ) ) {
			return $content;
		}

		// Quick check: Skip if content doesn't need migration.
		if ( ! MigrationUtils::content_needs_migration(
			$content,
			self::$_release_version,
			[] // Check all modules.
		) ) {
			return $content;
		}

		// Quick check: Skip if content doesn't contain preset attributes.
		if ( ! str_contains( $content, '"modulePreset"' ) && ! str_contains( $content, '"presetId"' ) ) {
			return $content;
		}

		// Ensure the content is wrapped by wp:divi/placeholder if not empty.
		$content = MigrationUtils::ensure_placeholder_wrapper( $content );

		// Start migration context to prevent global layout expansion during migration.
		MigrationContext::start();

		try {
			// Parse content into flat module objects using the established pattern.
			$flat_objects = MigrationUtils::parse_serialized_post_into_flat_module_object( $content, self::$_name );

			$changes_made = false;

			// Process each module to migrate preset attributes.
			foreach ( $flat_objects as $module_id => $module_data ) {
				$builder_version = $module_data['props']['attrs']['builderVersion'] ?? '0.0.0';

				// Skip if module is already at or above the release version.
				if ( StringUtility::version_compare( $builder_version, self::$_release_version, '>=' ) ) {
					continue;
				}

				// Attempt to migrate preset attributes.
				if ( self::_migrate_module_presets( $flat_objects[ $module_id ]['props']['attrs'] ) ) {
					// Update builder version after successful migration.
					$flat_objects[ $module_id ]['props']['attrs']['builderVersion'] = self::$_release_version;
					$changes_made = true;
				}
			}

			// If changes were made, serialize the flat objects back into content.
			if ( $changes_made ) {
				return MigrationUtils::serialize_flat_objects( $flat_objects );
			}

			return $content;
		} finally {
			MigrationContext::end();
		}
	}

	/**
	 * Migrate preset attributes in a module's attributes array.
	 *
	 * @since ??
	 *
	 * @param array $module_attrs Module attributes array (passed by reference).
	 *
	 * @return bool True if any changes were made, false otherwise.
	 */
	private static function _migrate_module_presets( array &$module_attrs ): bool {
		$modified = false;

		// Migrate modulePreset from string to array.
		if ( isset( $module_attrs['modulePreset'] ) && is_string( $module_attrs['modulePreset'] ) ) {
			$preset_value = trim( $module_attrs['modulePreset'] );

			if ( '' !== $preset_value && 'default' !== $preset_value && '_initial' !== $preset_value ) {
				$module_attrs['modulePreset'] = [ $preset_value ];
				$modified                     = true;
			} else {
				unset( $module_attrs['modulePreset'] );
				$modified = true;
			}
		}

		// Migrate groupPreset presetId from string to array.
		if ( isset( $module_attrs['groupPreset'] ) && is_array( $module_attrs['groupPreset'] ) ) {
			foreach ( $module_attrs['groupPreset'] as $group_id => &$group_preset ) {
				if ( isset( $group_preset['presetId'] ) && is_string( $group_preset['presetId'] ) ) {
					$preset_id = trim( $group_preset['presetId'] );

					if ( '' !== $preset_id && 'default' !== $preset_id && '_initial' !== $preset_id ) {
						$group_preset['presetId'] = [ $preset_id ];
						$modified                 = true;
					} else {
						unset( $group_preset['presetId'] );
						$modified = true;
					}
				}
			}
			unset( $group_preset );

			// Remove empty groupPreset objects.
			$module_attrs['groupPreset'] = array_filter(
				$module_attrs['groupPreset'],
				function ( $group_preset ) {
					$has_preset_id  = isset( $group_preset['presetId'] ) && ! empty( $group_preset['presetId'] );
					$has_group_name = isset( $group_preset['groupName'] ) && '' !== trim( $group_preset['groupName'] ?? '' );
					return $has_preset_id && $has_group_name;
				}
			);

			if ( empty( $module_attrs['groupPreset'] ) ) {
				unset( $module_attrs['groupPreset'] );
				$modified = true;
			}
		}

		return $modified;
	}
}
