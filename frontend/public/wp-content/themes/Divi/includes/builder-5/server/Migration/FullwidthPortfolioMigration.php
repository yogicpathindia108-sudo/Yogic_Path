<?php
/**
 * Fullwidth Portfolio Migration
 *
 * Handles the migration of Fullwidth Portfolio modules from Divi 4 to Divi 5
 * by adding the missing post_type attribute with the correct default value.
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Migration;

use ET\Builder\Packages\Conversion\ShortcodeMigration;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\FrontEnd\BlockParser\BlockParser;
use ET\Builder\Migration\MigrationContext;
use ET\Builder\Framework\Utility\StringUtility;
use ET\Builder\Migration\Utils\MigrationUtils;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use ET\Builder\Migration\MigrationContentBase;

/**
 * Fullwidth Portfolio Migration Class.
 *
 * @since ??
 */
class FullwidthPortfolioMigration extends MigrationContentBase {

	/**
	 * The migration name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_name = 'fullwidthPortfolio.v1';

	/**
	 * List of module names that need post_type migration (block format).
	 *
	 * @since ??
	 *
	 * @var string[]
	 */
	private static $_portfolio_modules = [
		'divi/fullwidth-portfolio',
	];

	/**
	 * List of module names that need post_type migration (shortcode format).
	 *
	 * @since ??
	 *
	 * @var string[]
	 */
	private static $_portfolio_shortcode_modules = [
		'et_pb_fullwidth_portfolio',
	];

	/**
	 * The fullwidth portfolio migration release version string.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_release_version = '5.0.0-public-alpha.24';

	/**
	 * Run the fullwidth portfolio migration.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function load(): void {
		/**
		 * Hook into the portability import process to migrate fullwidth portfolio content.
		 *
		 * This filter ensures that imported D4 content gets the missing post_type attribute
		 * added with the correct default value ('project') to preserve D4 behavior while
		 * allowing new D5 modules to default to 'post' for semantic consistency.
		 *
		 * @see FullwidthPortfolioMigration::migrate_import_content()
		 */
		add_filter( 'divi_framework_portability_import_migrated_post_content', [ __CLASS__, 'migrate_import_content' ] );

		add_action( 'wp', [ __CLASS__, 'migrate_fe_content' ] );
		add_filter( 'et_fb_load_raw_post_content', [ __CLASS__, 'migrate_vb_content' ], 10, 2 );
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
	 * Migrate content during import process.
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
	 * Migrate frontend content when loading pages.
	 *
	 * Uses content filters to transform content on-the-fly without database updates.
	 * This ensures D4 Fullwidth Portfolio modules display projects instead of posts.
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

		// Handle regular post content.
		if ( $content ) {
			// Update the post content using filter.
			add_filter(
				'the_content',
				function ( $content ) {
					// FE only processes block content with _migrate_block_content. Shortcode will be processed by D4.
					return self::_migrate_block_content( $content );
				},
				8 // BEFORE do_blocks().
			);
		}

		// Handle Theme Builder templates with filters.
		$tb_template_ids = DynamicAssetsUtils::get_theme_builder_template_ids();

		if ( ! empty( $tb_template_ids ) ) {
			// Apply migration via the et_builder_render_layout filter for TB templates.
			add_filter(
				'et_builder_render_layout',
				function ( $rendered_content ) {
					// Apply migration to all content rendered through et_builder_render_layout.
					// FE only processes block content with _migrate_block_content. Shortcode will be processed by D4.
					return self::_migrate_block_content( $rendered_content );
				},
				8 // BEFORE do_blocks().
			);
		}
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
	 * It will migrate both D5 and D4 content.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string The migrated content.
	 */
	private static function _migrate_the_content( $content ) {
		// Quick check: Skip if content doesn't need migration.
		if ( ! MigrationUtils::content_needs_migration(
			$content,
			self::$_release_version,
			self::$_portfolio_modules,
			self::$_portfolio_shortcode_modules
		) ) {
			return $content;
		}

		// First, handle shortcode-based migration (new migration).
		$content = self::_migrate_shortcode_content( $content );

		// Then, handle block-based migration (original migration).
		$content = self::_migrate_block_content( $content );

		return $content;
	}

	/**
	 * Migrate block-based content
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string The migrated content.
	 */
	private static function _migrate_block_content( $content ) {
		// Only process if content contains D5 blocks.
		if ( ! BlockParser::has_any_divi_block( $content ) || '<!-- wp:divi/placeholder -->' === $content ) {
			return $content;
		}

		// Quick check: Skip if content doesn't need migration.
		if ( ! MigrationUtils::content_needs_migration(
			$content,
			self::$_release_version,
			self::$_portfolio_modules,
			self::$_portfolio_shortcode_modules
		) ) {
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
				// Skip if not a fullwidth portfolio module.
				if ( ! in_array( $module_data['name'], self::$_portfolio_modules, true ) ) {
					continue;
				}

				// Get current builder version for this module.
				$current_version = $module_data['props']['attrs']['builderVersion'] ?? '0.0.0';

				// Only migrate if current version is less than release version.
				if ( StringUtility::version_compare( $current_version, self::$_release_version, '<' ) ) {
					// Check if post_type is already set (avoid overriding existing values).
					$existing_post_type = $module_data['props']['attrs']['portfolio']['innerContent']['desktop']['value']['type'] ?? null;

					if ( null === $existing_post_type ) {
						// Add post_type with D4's default value.
						$new_value = [
							'props' => [
								'attrs' => [
									'portfolio'      => [
										'innerContent' => [
											'desktop' => [
												'value' => [
													'type' => 'project',
												],
											],
										],
									],
									'builderVersion' => self::$_release_version,
								],
							],
						];

						$flat_objects[ $module_id ] = array_replace_recursive( $flat_objects[ $module_id ], $new_value );
						$changes_made               = true;
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
	 * Migrate shortcode-based content (new migration).
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string The migrated content.
	 */
	private static function _migrate_shortcode_content( $content ) {
		// Remove shortcode-module blocks to avoid false positives.
		$clean_content = preg_replace( '/<!-- wp:divi\/shortcode-module.*?<!-- \/wp:divi\/shortcode-module -->/s', '', $content );

		// Check if content starts with shortcodes that need migration.
		if ( ! str_starts_with( $clean_content, '[et_pb_' ) ) {
			return $content;
		}

		// Parse shortcodes from content.
		$parsed_shortcodes = ShortcodeMigration::process_shortcode( $content );

		// Migrate the parsed shortcodes.
		$parsed_shortcodes = self::migrate_parsed_shortcodes( $parsed_shortcodes );

		// Convert back to shortcode string (filter will be applied automatically).
		$new_content = ShortcodeMigration::process_to_shortcode( $parsed_shortcodes );

		return $new_content;
	}

	/**
	 * Migrate parsed shortcodes by adding post_type attributes.
	 *
	 * @since ??
	 *
	 * @param array $parsed_shortcodes The parsed shortcodes array.
	 *
	 * @return array The migrated shortcodes array.
	 */
	public static function migrate_parsed_shortcodes( array $parsed_shortcodes ): array {
		return self::_process_shortcodes_recursive( $parsed_shortcodes, self::$_portfolio_shortcode_modules, self::$_release_version );
	}

	/**
	 * Recursively processes shortcodes to add post_type attributes.
	 *
	 * @since ??
	 *
	 * @param array  $shortcodes The shortcodes to process.
	 * @param array  $portfolio_modules The modules that need post_type attributes.
	 * @param string $release_version The release version to set.
	 *
	 * @return array The processed shortcodes.
	 */
	private static function _process_shortcodes_recursive( array $shortcodes, array $portfolio_modules, string $release_version ): array {
		foreach ( $shortcodes as &$shortcode ) {
			// Check if this is a fullwidth portfolio module.
			if ( in_array( $shortcode['name'], $portfolio_modules, true ) ) {
				// Get current builder version, default to '0.0.0' if not set.
				$current_version = $shortcode['attributes']['_builder_version'] ?? '0.0.0';

				// Only migrate if current version is less than release version.
				if ( StringUtility::version_compare( $current_version, $release_version, '<' ) ) {
					// Check if post_type is already set (avoid overriding existing values).
					if ( ! isset( $shortcode['attributes']['post_type'] ) ) {
						// Add post_type attribute with D4's default value.
						$shortcode['attributes']['post_type'] = 'project';
					}

					// Update builder version.
					$shortcode['attributes']['_builder_version'] = $release_version;
				}
			}

			// Recursively process nested content if it's an array.
			if ( isset( $shortcode['content'] ) && is_array( $shortcode['content'] ) ) {
				$shortcode['content'] = self::_process_shortcodes_recursive( $shortcode['content'], $portfolio_modules, $release_version );
			}
		}

		return $shortcodes;
	}

	/**
	 * Migrate content from shortcode format.
	 *
	 * This method handles the migration of deprecated attributes in shortcode-based content.
	 * Currently returns the content unchanged as shortcode modules do not support
	 * the new custom attributes system that this migration targets.
	 *
	 * @since ??
	 *
	 * @param string $content The shortcode content to migrate.
	 *
	 * @return string The migrated content (currently unchanged).
	 */
	public static function migrate_content_shortcode( string $content ): string {
		if ( ! self::has_divi_shortcode( $content ) ) {
			return $content;
		}

		return self::_migrate_shortcode_content( $content );
	}

	/**
	 * Migrate content from block format.
	 *
	 * This method handles the migration of deprecated attributes in block-based content.
	 * Currently returns the content unchanged as this migration is handled
	 * by the main migration process in migrate_the_content().
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
