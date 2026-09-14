<?php
/**
 * Global Color Migration
 *
 * Handles the migration of global colors from CSS variable format to $variable() syntax.
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Migration;

use ET\Builder\Packages\Conversion\Utils\ConversionUtils;
use ET\Builder\FrontEnd\BlockParser\BlockParserBlock;
use ET\Builder\Migration\MigrationContext;
use ET\Builder\Framework\Utility\StringUtility;
use ET\Builder\Migration\Utils\MigrationUtils;
use ET\Builder\Migration\MigrationContentBase;

/**
 * Global Color Migration Class.
 *
 * @since ??
 */
class GlobalColorMigration extends MigrationContentBase {

	/**
	 * The migration name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_name = 'globalColor.v1';

	/**
	 * The Global Color migration release version string.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_release_version = '5.0.0-public-alpha.17.1';

	/**
	 * CSS Variable pattern for global colors.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_css_variable_pattern = '/var\(--(gcid-[0-9a-z-]+)\)/';

	/**
	 * Run the Global Color migration.
	 *
	 * For the time being, this migration only run when the content is loaded in Visual Builder.
	 * No need for migrating render on Frontend because existing Global Color mechanism will handle it.
	 * Migration on content import will be added once https://github.com/elegantthemes/Divi/issues/43481 is addressed.
	 *
	 * @since ??
	 */
	public static function load(): void {
		add_action( 'et_fb_load_raw_post_content', [ __CLASS__, 'migrate_vb_content' ], 10, 2 );
		add_filter( 'divi_visual_builder_rest_divi_library_load', [ __CLASS__, 'migrate_rest_divi_library_load' ] );
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
	 * Migrate the Visual Builder content.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 * @return string The migrated content.
	 */
	public static function migrate_vb_content( $content ) {
		return str_starts_with( $content, '<!-- wp:divi' )
			? self::_migrate_block_content( $content )
			: $content;
	}

	/**
	 * Migrate the REST Divi Library load response.
	 *
	 * @since ??
	 *
	 * @param WP_Post $post The post object.
	 *
	 * @return WP_Post The migrated post object.
	 */
	public static function migrate_rest_divi_library_load( $post ) {

		// If post content exist, run migration on loaded Divi Library's content.
		if ( isset( $post->post_content ) ) {
			$post->post_content = self::_migrate_block_content( $post->post_content );
		}

		return $post;
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
	private static function _migrate_block_content( $content ) {
		// Quick check: Skip if content doesn't need migration.
		// Note: GlobalColorMigration applies to all modules, so no module filtering.
		if ( ! MigrationUtils::content_needs_migration( $content, self::$_release_version ) ) {
			return $content;
		}

		// Store original content to return if no changes are made.
		$original_content = $content;

		// Ensure the content is wrapped by wp:divi/placeholder if not empty.
		$content = MigrationUtils::ensure_placeholder_wrapper( $content );

		// Start migration context to prevent global layout expansion during migration.
		MigrationContext::start();

		try {
			$flat_objects = MigrationUtils::parse_serialized_post_into_flat_module_object( $content, self::$_name );

			$changes_made = false;

			foreach ( $flat_objects as $module_id => $module_data ) {
				// Skip Global Color migration on shortcode module because there's no D5 Global Color in shortcode module.
				$is_shortcode_module = 'divi/shortcode-module' === $module_data['name'];

				// Check if module needs migration based on version comparison.
				$builder_version = $module_data['props']['attrs']['builderVersion'] ?? '0.0.0';

				if ( ! $is_shortcode_module && StringUtility::version_compare( $builder_version, self::$_release_version, '<' ) ) {

					$migrated_attrs = self::_migrate_module_attributes( $module_data['props']['attrs'] );

					if ( $migrated_attrs !== $module_data['props']['attrs'] ) {
						$changes_made = true;

						// Update builder version and apply migrated attributes.
						$flat_objects[ $module_id ]['props']['attrs'] = array_merge(
							$migrated_attrs,
							[ 'builderVersion' => self::$_release_version ]
						);
					}
				}
			}

			if ( $changes_made ) {
				// Serialize the flat objects back into the content.
				$new_content = MigrationUtils::serialize_flat_objects( $flat_objects );
			} else {
				$new_content = $original_content;
			}

			return $new_content;
		} finally {
			// Always end migration context, even if an exception occurs.
			MigrationContext::end();
		}
	}

	/**
	 * Migrate module attributes by converting CSS variables to $variable() syntax.
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
	 * Recursively migrate attributes to convert global color CSS variables.
	 *
	 * @since ??
	 *
	 * @param mixed $value The value to check and migrate.
	 *
	 * @return mixed The migrated value.
	 */
	private static function _migrate_attributes_recursive( $value ) {
		if ( is_string( $value ) ) {
			return self::_convert_global_color_css_variable( $value );
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
	 * Convert CSS variable global color to $variable() syntax.
	 *
	 * @since ??
	 *
	 * @param string $value The value to convert.
	 *
	 * @return string The converted value.
	 */
	private static function _convert_global_color_css_variable( string $value ): string {
		if ( ! preg_match( self::$_css_variable_pattern, $value, $matches ) ) {
			return $value;
		}

		$global_color_id = $matches[1]; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DeprecatedWhitelistCommentFound -- Now includes 'gcid-' prefix.

		// Convert to $variable() syntax.
		$variable_data = wp_json_encode(
			[
				'type'  => 'color',
				'value' => [
					'name'     => $global_color_id, // Keep the full 'gcid-' prefixed ID.
					'settings' => new \stdClass(), // Empty object for settings.
				],
			],
			JSON_UNESCAPED_SLASHES
		);

		return '$variable(' . $variable_data . ')$';
	}

	/**
	 * Migrate content from shortcode format.
	 *
	 * This method handles the migration of global colors in shortcode-based content.
	 * Currently returns the content unchanged as shortcode modules do not support
	 * Divi 5 Global Color functionality.
	 *
	 * @since ??
	 *
	 * @param string $content The shortcode content to migrate.
	 *
	 * @return string The original content as no need to do migration on shortcode content.
	 */
	public static function migrate_content_shortcode( string $content ): string {
		return $content;
	}

	/**
	 * Migrate content from block format.
	 *
	 * This method handles the migration of global colors in block-based content.
	 * Currently returns the content unchanged as this migration is handled
	 * by the main migration process in _migrate_block_content().
	 *
	 * @since ??
	 *
	 * @param string $content The block content to migrate.
	 *
	 * @return string The migrated content (currently unchanged).
	 */
	public static function migrate_content_block( string $content ): string {
		if ( ! self::has_divi_block( $content ) ) {
			return $content;
		}

		return self::_migrate_block_content( $content );
	}
}
