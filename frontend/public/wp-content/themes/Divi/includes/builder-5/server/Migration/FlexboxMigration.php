<?php
/**
 * Flexbox Migration
 *
 * Handles the migration of flexbox-related features and configurations.
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Migration;

use ET\Builder\Packages\Conversion\Utils\ConversionUtils;
use ET\Builder\Packages\Conversion\ShortcodeMigration;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\BlockParser\BlockParserBlock;
use ET\Builder\FrontEnd\BlockParser\BlockParser;
use ET\Builder\VisualBuilder\Saving\SavingUtility;
use ET\Builder\Migration\MigrationContext;
use ET\Builder\Framework\Utility\StringUtility;
use ET\Builder\Migration\Utils\MigrationUtils;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use ET\Builder\Migration\MigrationContentBase;

/**
 * Flexbox Migration Class.
 *
 * @since ??
 */
class FlexboxMigration extends MigrationContentBase {

	/**
	 * The migration name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_name = 'flexbox.v1';

	/**
	 * List of module names that use flexbox layout (block format).
	 *
	 * @since ??
	 *
	 * @var string[]
	 */
	private static $_flexbox_modules = [
		'divi/section',
		'divi/row',
		'divi/row-inner',
		'divi/column',
		'divi/column-inner',
		'divi/group',
		'divi/accordion',
		'divi/blog',
		'divi/counters',
		'divi/portfolio',
		'divi/filterable-portfolio',
		'divi/gallery',
		'divi/sidebar',
		'divi/social-media-follow',
		'divi/pricing-table',
	];

	/**
	 * List of module names that use flexbox layout (shortcode format).
	 *
	 * @since ??
	 *
	 * @var string[]
	 */
	private static $_flexbox_shortcode_modules = [
		'et_pb_section',
		'et_pb_row',
		'et_pb_row_inner',
		'et_pb_column_inner',
		'et_pb_column',
		'et_pb_accordion',
		'et_pb_blog',
		'et_pb_counters',
		'et_pb_portfolio',
		'et_pb_filterable_portfolio',
		'et_pb_gallery',
		'et_pb_sidebar',
		'et_pb_social_media_follow',
		'et_pb_pricing_table',
	];

	/**
	 * The flexbox release version string.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_release_version = '5.0.0-public-alpha.18.2';

	/**
	 * Run the flexbox migration.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function load(): void {
		/**
		 * Hook into the portability import process to migrate flexbox content.
		 *
		 * This filter ensures that imported content is properly migrated to include
		 * flexbox display properties for modules that were created before the flexbox
		 * feature was introduced. The migration applies to both block-based and
		 * shortcode-based content during the import process.
		 *
		 * @see FlexboxMigration::migrate_the_content()
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

		// Handle regular post content.
		if ( $content ) {
			// Update the post content using filter.
			add_filter(
				'the_content',
				function ( $content ) {
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
			self::$_flexbox_modules,
			self::$_flexbox_shortcode_modules
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
	 * Migrate block-based content (original migration).
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
			self::$_flexbox_modules,
			self::$_flexbox_shortcode_modules
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
				// Check if module needs migration based on version comparison.
				if (
				in_array( $module_data['name'], self::$_flexbox_modules, true )
				&& StringUtility::version_compare( $module_data['props']['attrs']['builderVersion'] ?? '0.0.0', self::$_release_version, '<' )
				) {

					$changes_made = true;

					// Special handling for Portfolio module which has different attribute structure.
					if ( 'divi/portfolio' === $module_data['name'] ) {
						$new_value = [
							'props' => [
								'attrs' => [
									'builderVersion' => self::$_release_version,
									'portfolioGrid'  => [
										'decoration' => [
											'layout' => [
												'desktop' => [
													'value' => [
														'display' => 'block',
													],
												],
											],
										],
									],
								],
							],
						];
					} elseif ( 'divi/gallery' === $module_data['name'] ) {
						// Special handling for Gallery module which has different attribute structure.
						$new_value = [
							'props' => [
								'attrs' => [
									'builderVersion' => self::$_release_version,
									'galleryGrid'    => [
										'decoration' => [
											'layout' => [
												'desktop' => [
													'value' => [
														'display' => 'block',
													],
												],
											],
										],
									],
								],
							],
						];
					} elseif ( 'divi/filterable-portfolio' === $module_data['name'] ) {
						// Special handling for Filterable Portfolio module which has different attribute structure.
						$new_value = [
							'props' => [
								'attrs' => [
									'builderVersion' => self::$_release_version,
									'portfolioGrid'  => [
										'decoration' => [
											'layout' => [
												'desktop' => [
													'value' => [
														'display' => 'block',
													],
												],
											],
										],
									],
								],
							],
						];
					} elseif ( 'divi/blog' === $module_data['name'] ) {
						$fullwidth = $module_data['props']['attrs']['fullwidth']['advanced']['enable']['desktop']['value'] ?? 'on';

						if ( 'off' === $fullwidth ) {
							$new_value = [
								'props' => [
									'attrs' => [
										'builderVersion' => self::$_release_version,
										'blogGrid'       => [
											'decoration' => [
												'layout' => [
													'desktop' => [
														'value' => [
															'display' => 'block',
														],
													],
												],
											],
											'advanced'   => [
												'flexType' => [
													'desktop' => [
														'value' => '8_24',
													],
													'tablet' => [
														'value' => '12_24',
													],
													'phone' => [
														'value' => '24_24',
													],
												],
											],
										],
									],
								],
							];
						} else {
							$new_value = [
								'props' => [
									'attrs' => [
										'builderVersion' => self::$_release_version,
										'blogGrid'       => [
											'decoration' => [
												'layout' => [
													'desktop' => [
														'value' => [
															'display' => 'block',
														],
													],
												],
											],
										],
									],
								],
							];
						}
					} elseif ( 'divi/pricing-table' === $module_data['name'] ) {
						// Special handling for Pricing Table module to migrate column width configuration.
						// Get parent column type and count of pricing table siblings.
						$parent_column_type  = MigrationUtils::get_parent_column_type( $module_data, $flat_objects );
						$pricing_table_count = self::_get_pricing_table_count( $module_data, $flat_objects );
						$flex_column_type    = self::_get_flex_column_type_for_pricing_tables( $parent_column_type, $pricing_table_count );

						$decoration = [
							'layout' => [
								'desktop' => [
									'value' => [
										'display' => 'block',
									],
								],
							],
						];

						if ( $flex_column_type ) {
							$decoration['sizing'] = [
								'desktop' => [
									'value' => [
										'flexType' => $flex_column_type,
									],
								],
							];
						}

						$new_value = [
							'props' => [
								'attrs' => [
									'builderVersion' => self::$_release_version,
									'module'         => [
										'decoration' => $decoration,
									],
								],
							],
						];
					} else {
						// Standard handling for other modules (section, row, column, etc.).
						$new_value = [
							'props' => [
								'attrs' => [
									'builderVersion' => self::$_release_version,
									'module'         => [
										'decoration' => [
											'layout' => [
												'desktop' => [
													'value' => [
														'display' => 'block',
													],
												],
											],
										],
									],
								],
							],
						];

						// Check if makeEqual is 'on' and migrate to alignColumns: 'stretch'.
						$make_equal_value = $module_data['props']['attrs']['gutter']['desktop']['value']['makeEqual'] ?? null;
						if ( 'on' === $make_equal_value ) {
							$new_value['props']['attrs']['gutter'] = [
								'desktop' => [
									'value' => [
										'alignColumns' => 'stretch',
									],
								],
							];
						}
					}

					$flat_objects[ $module_id ] = array_replace_recursive( $flat_objects[ $module_id ], $new_value );
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
	 * Migrate parsed shortcodes by adding flexbox layout attributes.
	 *
	 * @since ??
	 *
	 * @param array $parsed_shortcodes The parsed shortcodes array.
	 *
	 * @return array The migrated shortcodes array.
	 */
	public static function migrate_parsed_shortcodes( array $parsed_shortcodes ): array {
		return self::_process_shortcodes_recursive( $parsed_shortcodes, self::$_flexbox_shortcode_modules, self::$_release_version );
	}

	/**
	 * Recursively processes shortcodes to add flexbox attributes.
	 *
	 * @since ??
	 *
	 * @param array  $shortcodes The shortcodes to process.
	 * @param array  $flexbox_modules The modules that need flexbox attributes.
	 * @param string $release_version The release version to set.
	 * @param array  $parent_context Additional context for nested processing.
	 *
	 * @return array The processed shortcodes.
	 */
	private static function _process_shortcodes_recursive( array $shortcodes, array $flexbox_modules, string $release_version, array $parent_context = [] ): array {
		foreach ( $shortcodes as $index => &$shortcode ) {
			// Check if this is a flexbox module.
			if ( in_array( $shortcode['name'], $flexbox_modules, true ) ) {
				// Get current builder version, default to '0.0.0' if not set.
				$current_version = $shortcode['attributes']['_builder_version'] ?? '0.0.0';

				// Only migrate if current version is less than release version.
				if ( StringUtility::version_compare( $current_version, $release_version, '<' ) ) {
					// Special handling for individual pricing table.
					if ( 'et_pb_pricing_table' === $shortcode['name'] ) {
						$shortcode['attributes']['layout_display']   = 'block';
						$shortcode['attributes']['_builder_version'] = $release_version;

						// Get parent column type and count siblings.
						$parent_column_type  = $parent_context['column_type'] ?? null;
						$pricing_table_count = $parent_context['pricing_table_count'] ?? 0;

						// Calculate flexType based on parent column and sibling count.
						$flex_column_type = self::_get_flex_column_type_for_pricing_tables( $parent_column_type, $pricing_table_count );

						if ( $flex_column_type ) {
							$shortcode['attributes']['layout_grid_type'] = $flex_column_type;
						}

						continue;
					}

					// Default handling for other modules.
					$shortcode['attributes']['layout_display']   = 'block';
					$shortcode['attributes']['_builder_version'] = $release_version;

					if ( 'et_pb_blog' === $shortcode['name'] && 'off' === ( $shortcode['attributes']['fullwidth'] ?? 'on' ) ) {
						$shortcode['attributes']['layout_grid_type']             = '8_24';
						$shortcode['attributes']['layout_grid_type_tablet']      = '12_24';
						$shortcode['attributes']['layout_grid_type_phone']       = '24_24';
						$shortcode['attributes']['layout_grid_type_last_edited'] = 'on';
					}
				}
			}

			// Track column type and pricing table count for nested processing.
			$nested_context = $parent_context;
			if ( in_array( $shortcode['name'], [ 'et_pb_column', 'et_pb_column_inner' ], true ) ) {
				$column_type                   = $shortcode['attributes']['type'] ?? null;
				$nested_context['column_type'] = $column_type;
			}

			// Count pricing tables for et_pb_pricing_tables parent.
			if ( 'et_pb_pricing_tables' === $shortcode['name'] && isset( $shortcode['content'] ) && is_array( $shortcode['content'] ) ) {
				// Update builder version for pricing_tables parent if needed.
				$current_version = $shortcode['attributes']['_builder_version'] ?? '0.0.0';
				if ( StringUtility::version_compare( $current_version, $release_version, '<' ) ) {
					$shortcode['attributes']['_builder_version'] = $release_version;
				}

				// Count pricing table children for context.
				$pricing_table_count = 0;
				foreach ( $shortcode['content'] as $child ) {
					if ( isset( $child['name'] ) && 'et_pb_pricing_table' === $child['name'] ) {
						++$pricing_table_count;
					}
				}
				$nested_context['pricing_table_count'] = $pricing_table_count;
			}

			// Recursively process nested content if it's an array.
			if ( isset( $shortcode['content'] ) && is_array( $shortcode['content'] ) ) {
				$shortcode['content'] = self::_process_shortcodes_recursive( $shortcode['content'], $flexbox_modules, $release_version, $nested_context );
			}
		}

		return $shortcodes;
	}

	/**
	 * Get the count of pricing table siblings.
	 *
	 * @since ??
	 *
	 * @param array $module_data The pricing table module data.
	 * @param array $flat_objects All flat module objects.
	 *
	 * @return int The number of pricing table siblings.
	 */
	private static function _get_pricing_table_count( array $module_data, array $flat_objects ): int {
		// Get parent (pricing tables).
		$parent_id = $module_data['parent'] ?? null;
		if ( ! $parent_id || ! isset( $flat_objects[ $parent_id ] ) ) {
			return 1;
		}

		$parent_module = $flat_objects[ $parent_id ];
		$children      = $parent_module['children'] ?? [];

		// Count pricing table children.
		$count = 0;
		foreach ( $children as $child_id ) {
			if ( isset( $flat_objects[ $child_id ] ) && 'divi/pricing-table' === $flat_objects[ $child_id ]['name'] ) {
				++$count;
			}
		}

		return $count > 0 ? $count : 1;
	}

	/**
	 * Get flex column type based on parent column type and pricing table count.
	 *
	 * Mimics the original CSS logic where single pricing tables always get 100% width,
	 * and column type overrides table count for multiple tables.
	 *
	 * @since ??
	 *
	 * @param string|null $parent_column_type The parent column type.
	 * @param int         $pricing_table_count The number of pricing tables.
	 *
	 * @return string|null The flex column type or null if not applicable.
	 */
	private static function _get_flex_column_type_for_pricing_tables( ?string $parent_column_type, int $pricing_table_count ): ?string {
		// 1. Single pricing table → ALWAYS 100% width regardless of column type.
		if ( 1 === $pricing_table_count ) {
			return '24_24';
		}

		if ( ! $parent_column_type ) {
			return '8_24'; // Default: 33.33% (3 columns).
		}

		// Define column categories based on CSS rules.
		$small_columns  = [ '3_8', '1_3', '2_5', '1_4', '1_5', '1_6' ];
		$medium_columns = [ '2_3', '1_2', '3_5' ];
		$large_columns  = [ '3_4' ];
		$full_columns   = [ '4_4' ];

		// Apply CSS logic priority for multiple tables (column type overrides table count).

		// 2. Small columns → ALWAYS 100% width (1 column) regardless of table count.
		if ( in_array( $parent_column_type, $small_columns, true ) ) {
			return '24_24';
		}

		// 3. Medium columns → ALWAYS 50% width (2 columns) regardless of table count.
		if ( in_array( $parent_column_type, $medium_columns, true ) ) {
			return '12_24';
		}

		// 4. Large columns → ALWAYS 33.33% width (3 columns) regardless of table count.
		if ( in_array( $parent_column_type, $large_columns, true ) ) {
			return '8_24';
		}

		// 5. Full width columns (4_4) → special logic based on table count.
		if ( in_array( $parent_column_type, $full_columns, true ) ) {
			if ( 2 === $pricing_table_count ) {
				return '12_24'; // 50% width (2 columns).
			}
			if ( 3 === $pricing_table_count ) {
				return '8_24'; // 33.33% (3 columns) - CSS override for .et_pb_pricing_3.
			}
			if ( 4 === $pricing_table_count ) {
				return '6_24'; // 25% width (4 columns).
			}
			return '6_24'; // 25% (5+ columns) - default for full width.
		}

		// 6. Default fallback for unknown column types → always 33.33% width (3 columns).
		return '8_24';
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
