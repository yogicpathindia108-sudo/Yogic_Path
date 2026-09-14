<?php
/**
 * Dynamic Content Post ID Migration
 *
 * Handles the migration of slug-based post_id values to numeric IDs in dynamic content
 * during JSON layout import. This ensures that imported layouts with slug-based post_id
 * values are converted to numeric IDs for better stability and reliability.
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Migration;

use ET\Builder\Migration\MigrationContext;
use ET\Builder\Migration\MigrationContentBase;
use ET\Builder\Migration\Utils\MigrationUtils;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentOptionCustomPostLinkUrl;
use ET\Builder\Packages\Module\Layout\Components\DynamicData\DynamicData;
use ET\Builder\Framework\Utility\StringUtility;

/**
 * Dynamic Content Post ID Migration Class.
 *
 * @since ??
 */
class DynamicContentPostIdMigration extends MigrationContentBase {

	/**
	 * The migration name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_name = 'dynamicContentPostId.v1';

	/**
	 * The Dynamic Content Post ID migration release version string.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_release_version = '5.0.0-public-beta.7';

	/**
	 * Run the Dynamic Content Post ID migration.
	 *
	 * @since ??
	 */
	public static function load(): void {
		/**
		 * Hook into the portability import process to migrate dynamic content post_id values.
		 *
		 * This filter ensures that imported content with slug-based post_id values
		 * in dynamic content are converted to numeric IDs during import.
		 *
		 * @see DynamicContentPostIdMigration::migrate_import_content()
		 */
		add_filter( 'divi_framework_portability_import_migrated_post_content', [ __CLASS__, 'migrate_import_content' ] );
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
	 * Migrate the content.
	 *
	 * It will migrate both D5 and D4 content.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string The migrated content.
	 */
	private static function _migrate_the_content( string $content ): string {
		// Quick check: Skip if content doesn't need migration.
		if ( ! MigrationUtils::content_needs_migration( $content, self::$_release_version ) ) {
			return $content;
		}

		// Then, handle block-based migration.
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
	private static function _migrate_block_content( string $content ): string {
		// Only process if content contains D5 blocks.
		if ( ! self::has_divi_block( $content ) || '<!-- wp:divi/placeholder -->' === $content ) {
			return $content;
		}

		// Quick check: Skip if content doesn't need migration.
		if ( ! MigrationUtils::content_needs_migration( $content, self::$_release_version ) ) {
			return $content;
		}

		// Store original content before wrapping to return unchanged if no migration needed.
		$original_content = $content;

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
					// Migrate module attributes recursively.
					$original_attrs = $module_data['props']['attrs'];
					$migrated_attrs = self::_migrate_module_attributes( $original_attrs );

					// Check if any changes were made.
					if ( $original_attrs !== $migrated_attrs ) {
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
				// Return original unwrapped content if no changes were made.
				$new_content = $original_content;
			}

			return $new_content;
		} finally {
			// Always end migration context, even if an exception occurs.
			MigrationContext::end();
		}
	}

	/**
	 * Migrate module attributes by converting slug-based post_id to numeric ID in dynamic content.
	 *
	 * @since ??
	 *
	 * @param array $attrs Module attributes.
	 *
	 * @return array Migrated attributes.
	 */
	private static function _migrate_module_attributes( array $attrs ): array {
		return self::_migrate_attributes_recursive( $attrs );
	}

	/**
	 * Recursively migrate attributes to convert slug-based post_id to numeric ID in dynamic content.
	 *
	 * @since ??
	 *
	 * @param mixed $value The value to check and migrate.
	 *
	 * @return mixed The migrated value.
	 */
	private static function _migrate_attributes_recursive( $value ) {
		if ( is_string( $value ) ) {
			return self::_convert_slug_to_id_in_dynamic_content( $value );
		}

		if ( is_array( $value ) ) {
			$migrated = [];
			foreach ( $value as $key => $item ) {
				$migrated[ $key ] = self::_migrate_attributes_recursive( $item );
			}
			return $migrated;
		}

		return $value;
	}

	/**
	 * Convert slug-based post_id to numeric ID in dynamic content string.
	 *
	 * @since ??
	 *
	 * @param string $value The dynamic content string value to convert.
	 *
	 * @return string The converted value.
	 */
	private static function _convert_slug_to_id_in_dynamic_content( string $value ): string {
		// Check if value is a dynamic content string.
		if ( ! str_contains( $value, '$variable(' ) || '$' !== substr( $value, -1 ) ) {
			return $value;
		}

		// Extract the JSON content from $variable() wrapper.
		if ( ! preg_match( '/\$variable\((.+?)\)\$/', $value, $matches ) ) {
			return $value;
		}

		$json_content = $matches[1];

		// Parse the JSON content.
		$data_value = DynamicData::get_data_value( $json_content );

		if ( empty( $data_value ) || ! isset( $data_value['type'], $data_value['value'] ) ) {
			return $value;
		}

		// Only process 'content' type dynamic content.
		if ( 'content' !== $data_value['type'] ) {
			return $value;
		}

		$dynamic_value = $data_value['value'];
		$name          = $dynamic_value['name'] ?? '';

		// Only process post_link_url_* options.
		if ( empty( $name ) || ! str_starts_with( $name, 'post_link_url_' ) ) {
			return $value;
		}

		$settings = $dynamic_value['settings'] ?? [];
		$post_id  = $settings['post_id'] ?? '';

		// Skip if post_id is not set or is already numeric.
		if ( empty( $post_id ) || is_numeric( $post_id ) ) {
			return $value;
		}

		// Extract post type from name (e.g., "post_link_url_page" -> "page").
		$post_type = str_replace( 'post_link_url_', '', $name );

		// Get post ID by slug.
		$resolved_post_id = DynamicContentOptionCustomPostLinkUrl::get_post_id_by_slug( $post_id, $post_type );

		// If post ID was found, update the dynamic content string.
		if ( null !== $resolved_post_id ) {
			// Update settings with numeric ID.
			$settings['post_id'] = (string) $resolved_post_id;

			// Reconstruct the dynamic content string.
			$new_data_value = [
				'type'  => 'content',
				'value' => [
					'name'     => $name,
					'settings' => $settings,
				],
			];

			$new_json_content = wp_json_encode( $new_data_value, JSON_UNESCAPED_UNICODE );

			return '$variable(' . $new_json_content . ')$';
		}

		// If post ID was not found, return original value (preserve slug for backward compatibility).
		return $value;
	}

	/**
	 * Migrate content from shortcode format.
	 *
	 * This method handles the migration of slug-based post_id values in shortcode-based content.
	 * Currently returns the content unchanged as shortcode modules do not support
	 * Divi 5 dynamic content format.
	 *
	 * @since ??
	 *
	 * @param string $content The shortcode content to migrate.
	 *
	 * @return string The migrated content (currently unchanged).
	 */
	public static function migrate_content_shortcode( string $content ): string {
		return $content;
	}

	/**
	 * Migrate content from block format.
	 *
	 * This method handles the migration of slug-based post_id values in block-based content.
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

		return self::_migrate_block_content( $content );
	}
}
