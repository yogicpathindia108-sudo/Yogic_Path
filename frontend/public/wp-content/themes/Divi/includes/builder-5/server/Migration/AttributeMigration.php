<?php
/**
 * Attributes Migration
 *
 * Handles the migration of deprecated attributes into the new custom attributes system.
 * This includes:
 * - CSS ID and CSS Class from htmlAttributes
 * - Blurb module image alt text from imageIcon.innerContent.alt
 * - Slide module image alt text from image.innerContent.alt
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Migration;

use ET\Builder\Packages\Conversion\Utils\ConversionUtils;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\BlockParser\BlockParserBlock;
use ET\Builder\FrontEnd\BlockParser\BlockParser;
use ET\Builder\Migration\MigrationContext;
use ET\Builder\Framework\Utility\StringUtility;
use ET\Builder\Migration\Utils\MigrationUtils;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use ET\Builder\Migration\MigrationContentBase;

/**
 * Attributes Migration Class.
 *
 * @since ??
 */
class AttributeMigration extends MigrationContentBase {

	/**
	 * The migration name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_name = 'attributes.v1';

	/**
	 * The attributes migration release version string.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_release_version = '5.0.0-public-alpha.23';

	/**
	 * Run the attributes migration.
	 *
	 * @since ??
	 */
	public static function load(): void {
		/**
		 * Hook into the portability import process to migrate attributes content.
		 *
		 * This filter ensures that imported content properly migrates deprecated attributes
		 * to the new custom attributes system.
		 *
		 * @see AttributeMigration::migrate_the_content()
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
		// Note: AttributeMigration applies to all modules (CSS ID/Class), so no module filtering.
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
	private static function _migrate_block_content( $content ) {
		// Only process if content contains D5 blocks.
		if ( ! BlockParser::has_any_divi_block( $content ) || '<!-- wp:divi/placeholder -->' === $content ) {
			return $content;
		}

		// Quick check: Skip if content doesn't need migration.
		// Note: AttributeMigration applies to all modules, so no module filtering.
		if ( ! MigrationUtils::content_needs_migration( $content, self::$_release_version ) ) {
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

					// Check if module has CSS ID or CSS Class to migrate.
					$html_attributes = $module_data['props']['attrs']['module']['advanced']['htmlAttributes']['desktop']['value'] ?? [];
					$css_id          = $html_attributes['id'] ?? '';
					$css_class       = $html_attributes['class'] ?? '';

					if ( ! empty( $css_id ) || ! empty( $css_class ) ) {
						$changes_made = true;

						// Prepare new attributes array.
						$new_attributes = [];

						// Generate unique IDs for each attribute.
						if ( ! empty( $css_id ) ) {
							$new_attributes[] = [
								'id'         => \ET_Core_Data_Utils::uuid_v4(),
								'name'       => 'id',
								'value'      => $css_id,
								'adminLabel' => 'CSS ID',
							];
						}

						if ( ! empty( $css_class ) ) {
							$new_attributes[] = [
								'id'         => \ET_Core_Data_Utils::uuid_v4(),
								'name'       => 'class',
								'value'      => $css_class,
								'adminLabel' => 'CSS Class',
							];
						}

						// Update the module with migrated attributes.
						$new_value = [
							'props' => [
								'attrs' => [
									'builderVersion' => self::$_release_version,
									'module'         => [
										'decoration' => [
											'attributes' => [
												'desktop' => [
													'value' => [
														'attributes' => $new_attributes,
													],
												],
											],
										],
										'advanced'   => [
											'htmlAttributes' => [
												'desktop' => [
													'value' => [
														// Remove migrated values but keep any other attributes.
														'id'    => '',
														'class' => '',
													],
												],
											],
										],
									],
								],
							],
						];

						$flat_objects[ $module_id ] = array_replace_recursive( $flat_objects[ $module_id ], $new_value );
					}

					// Check if this is a Blurb module with image alt text to migrate.
					if ( 'divi/blurb' === ( $module_data['name'] ?? '' ) ) {
						$image_alt = $module_data['props']['attrs']['imageIcon']['innerContent']['desktop']['value']['alt'] ?? '';

						if ( ! empty( $image_alt ) ) {
							$changes_made = true;

							// Prepare new attributes array for Blurb image alt.
							$blurb_attributes = [
								[
									'id'            => \ET_Core_Data_Utils::uuid_v4(),
									'name'          => 'alt',
									'value'         => $image_alt,
									'adminLabel'    => 'Image Alt',
									'targetElement' => 'imageIcon',
								],
							];

							// Get existing custom attributes if any.
							$existing_attributes = $flat_objects[ $module_id ]['props']['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [];
							if ( is_array( $existing_attributes ) ) {
								$blurb_attributes = array_merge( $existing_attributes, $blurb_attributes );
							}

							// Update the module with migrated Blurb image alt attributes.
							$blurb_value = [
								'props' => [
									'attrs' => [
										'builderVersion' => self::$_release_version,
										'module'         => [
											'decoration' => [
												'attributes' => [
													'desktop' => [
														'value' => [
															'attributes' => $blurb_attributes,
														],
													],
												],
											],
										],
									],
								],
							];

							$flat_objects[ $module_id ] = array_replace_recursive( $flat_objects[ $module_id ], $blurb_value );

							// Clear the old alt value.
							$flat_objects[ $module_id ]['props']['attrs']['imageIcon']['innerContent']['desktop']['value']['alt'] = '';
						}
					}

					// Check if this is a Menu or Fullwidth Menu module with logo alt text to migrate.
					if ( in_array( $module_data['name'] ?? '', [ 'divi/menu', 'divi/fullwidth-menu' ], true ) ) {
						$logo_alt = $module_data['props']['attrs']['logo']['innerContent']['desktop']['value']['alt'] ?? '';

						if ( ! empty( $logo_alt ) ) {
							$changes_made = true;

							$logo_attributes = [
								[
									'id'            => \ET_Core_Data_Utils::uuid_v4(),
									'name'          => 'alt',
									'value'         => $logo_alt,
									'adminLabel'    => 'Logo Alt',
									'targetElement' => 'logo',
								],
							];

							// Get existing custom attributes if any.
							$existing_attributes = $flat_objects[ $module_id ]['props']['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [];
							if ( is_array( $existing_attributes ) ) {
								$logo_attributes = array_merge( $existing_attributes, $logo_attributes );
							}

							// Update the module with migrated Menu logo alt attributes.
							$logo_value = [
								'props' => [
									'attrs' => [
										'builderVersion' => self::$_release_version,
										'module'         => [
											'decoration' => [
												'attributes' => [
													'desktop' => [
														'value' => [
															'attributes' => $logo_attributes,
														],
													],
												],
											],
										],
									],
								],
							];

							$flat_objects[ $module_id ] = array_replace_recursive( $flat_objects[ $module_id ], $logo_value );

							// Clear the old alt value.
							unset( $flat_objects[ $module_id ]['props']['attrs']['logo']['innerContent']['desktop']['value']['alt'] );
						}
					}

					// Check if this is an Icon module with title attribute to migrate.
					if ( 'divi/icon' === ( $module_data['name'] ?? '' ) ) {
						$icon_title = $module_data['props']['attrs']['icon']['innerContent']['desktop']['value']['title'] ?? '';

						if ( ! empty( $icon_title ) ) {
							$changes_made = true;

							$icon_attributes = [
								[
									'id'            => \ET_Core_Data_Utils::uuid_v4(),
									'name'          => 'title',
									'value'         => $icon_title,
									'adminLabel'    => 'Icon Link Title',
									'targetElement' => 'iconLink',
								],
							];

							// Get existing custom attributes if any.
							$existing_attributes = $flat_objects[ $module_id ]['props']['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [];
							if ( is_array( $existing_attributes ) ) {
								$icon_attributes = array_merge( $existing_attributes, $icon_attributes );
							}

							// Update the module with migrated Icon title attributes.
							$icon_value = [
								'props' => [
									'attrs' => [
										'builderVersion' => self::$_release_version,
										'module'         => [
											'decoration' => [
												'attributes' => [
													'desktop' => [
														'value' => [
															'attributes' => $icon_attributes,
														],
													],
												],
											],
										],
									],
								],
							];

							$flat_objects[ $module_id ] = array_replace_recursive( $flat_objects[ $module_id ], $icon_value );

							// Clear the old title value.
							unset( $flat_objects[ $module_id ]['props']['attrs']['icon']['innerContent']['desktop']['value']['title'] );
						}
					}

					// Check if this is a Fullwidth Header module with header image and logo attributes to migrate.
					if ( 'divi/fullwidth-header' === ( $module_data['name'] ?? '' ) ) {
						$header_alt   = $module_data['props']['attrs']['image']['innerContent']['desktop']['value']['alt'] ?? '';
						$header_title = $module_data['props']['attrs']['image']['innerContent']['desktop']['value']['title'] ?? '';
						$logo_alt     = $module_data['props']['attrs']['logo']['innerContent']['desktop']['value']['alt'] ?? '';
						$logo_title   = $module_data['props']['attrs']['logo']['innerContent']['desktop']['value']['title'] ?? '';

						$fullwidth_header_attributes = [];

						// Handle header image alt attribute migration.
						if ( ! empty( $header_alt ) ) {
							$changes_made                  = true;
							$fullwidth_header_attributes[] = [
								'id'            => \ET_Core_Data_Utils::uuid_v4(),
								'name'          => 'alt',
								'value'         => $header_alt,
								'adminLabel'    => 'Image Alt',
								'targetElement' => 'image',
							];

							// Clear the old header image alt value.
							unset( $flat_objects[ $module_id ]['props']['attrs']['image']['innerContent']['desktop']['value']['alt'] );
						}

						// Handle header image title attribute migration.
						if ( ! empty( $header_title ) ) {
							$changes_made                  = true;
							$fullwidth_header_attributes[] = [
								'id'            => \ET_Core_Data_Utils::uuid_v4(),
								'name'          => 'title',
								'value'         => $header_title,
								'adminLabel'    => 'Image Title',
								'targetElement' => 'image',
							];

							// Clear the old header image title value.
							unset( $flat_objects[ $module_id ]['props']['attrs']['image']['innerContent']['desktop']['value']['title'] );
						}

						// Handle logo image alt attribute migration.
						if ( ! empty( $logo_alt ) ) {
							$changes_made                  = true;
							$fullwidth_header_attributes[] = [
								'id'            => \ET_Core_Data_Utils::uuid_v4(),
								'name'          => 'alt',
								'value'         => $logo_alt,
								'adminLabel'    => 'Logo Alt',
								'targetElement' => 'logo',
							];

							// Clear the old logo alt value.
							unset( $flat_objects[ $module_id ]['props']['attrs']['logo']['innerContent']['desktop']['value']['alt'] );
						}

						// Handle logo image title attribute migration.
						if ( ! empty( $logo_title ) ) {
							$changes_made                  = true;
							$fullwidth_header_attributes[] = [
								'id'            => \ET_Core_Data_Utils::uuid_v4(),
								'name'          => 'title',
								'value'         => $logo_title,
								'adminLabel'    => 'Logo Title',
								'targetElement' => 'logo',
							];

							// Clear the old logo title value.
							unset( $flat_objects[ $module_id ]['props']['attrs']['logo']['innerContent']['desktop']['value']['title'] );
						}

						// Apply migration if any attributes were found.
						if ( ! empty( $fullwidth_header_attributes ) ) {
							// Get existing custom attributes if any.
							$existing_attributes = $flat_objects[ $module_id ]['props']['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [];
							if ( is_array( $existing_attributes ) ) {
								$fullwidth_header_attributes = array_merge( $existing_attributes, $fullwidth_header_attributes );
							}

							// Update the module with migrated Fullwidth Header attributes.
							$fullwidth_header_value = [
								'props' => [
									'attrs' => [
										'builderVersion' => self::$_release_version,
										'module'         => [
											'decoration' => [
												'attributes' => [
													'desktop' => [
														'value' => [
															'attributes' => $fullwidth_header_attributes,
														],
													],
												],
											],
										],
									],
								],
							];

							$flat_objects[ $module_id ] = array_replace_recursive( $flat_objects[ $module_id ], $fullwidth_header_value );
						}
					}

					// Check if this is a Slide module with image alt text to migrate.
					if ( 'divi/slide' === ( $module_data['name'] ?? '' ) ) {
						$image_alt = $module_data['props']['attrs']['image']['innerContent']['desktop']['value']['alt'] ?? '';

						if ( ! empty( $image_alt ) ) {
							$changes_made = true;

							// Prepare new attributes array for Slide image alt.
							$slide_attributes = [
								[
									'id'            => \ET_Core_Data_Utils::uuid_v4(),
									'name'          => 'alt',
									'value'         => $image_alt,
									'adminLabel'    => 'Image Alt',
									'targetElement' => 'image',
								],
							];

							// Get existing custom attributes if any.
							$existing_attributes = $flat_objects[ $module_id ]['props']['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [];
							if ( is_array( $existing_attributes ) ) {
								$slide_attributes = array_merge( $existing_attributes, $slide_attributes );
							}

							// Update the module with migrated Slide image alt attributes.
							$slide_value = [
								'props' => [
									'attrs' => [
										'builderVersion' => self::$_release_version,
										'module'         => [
											'decoration' => [
												'attributes' => [
													'desktop' => [
														'value' => [
															'attributes' => $slide_attributes,
														],
													],
												],
											],
										],
									],
								],
							];

							$flat_objects[ $module_id ] = array_replace_recursive( $flat_objects[ $module_id ], $slide_value );

							// Clear the old alt value.
							unset( $flat_objects[ $module_id ]['props']['attrs']['image']['innerContent']['desktop']['value']['alt'] );
						}
					}

					// Check if this is an Image or Fullwidth Image module with image attributes to migrate.
					if ( in_array( $module_data['name'] ?? '', [ 'divi/image', 'divi/fullwidth-image' ], true ) ) {
						$image_alt   = $module_data['props']['attrs']['image']['innerContent']['desktop']['value']['alt'] ?? '';
						$image_title = $module_data['props']['attrs']['image']['innerContent']['desktop']['value']['titleText'] ?? '';

						$image_attributes = [];

						// Handle alt attribute migration.
						if ( ! empty( $image_alt ) ) {
							$changes_made       = true;
							$image_attributes[] = [
								'id'            => \ET_Core_Data_Utils::uuid_v4(),
								'name'          => 'alt',
								'value'         => $image_alt,
								'adminLabel'    => 'Image Alt',
								'targetElement' => 'image',
							];

							// Clear the old alt value.
							unset( $flat_objects[ $module_id ]['props']['attrs']['image']['innerContent']['desktop']['value']['alt'] );
						}

						// Handle title attribute migration.
						if ( ! empty( $image_title ) ) {
							$changes_made       = true;
							$image_attributes[] = [
								'id'            => \ET_Core_Data_Utils::uuid_v4(),
								'name'          => 'title',
								'value'         => $image_title,
								'adminLabel'    => 'Image Title',
								'targetElement' => 'image',
							];

							// Clear the old titleText value.
							unset( $flat_objects[ $module_id ]['props']['attrs']['image']['innerContent']['desktop']['value']['titleText'] );
						}

						// Apply the migration if we have attributes to migrate.
						if ( ! empty( $image_attributes ) ) {
							// Get existing custom attributes if any.
							$existing_attributes = $flat_objects[ $module_id ]['props']['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [];
							if ( is_array( $existing_attributes ) ) {
								$image_attributes = array_merge( $existing_attributes, $image_attributes );
							}

							// Update the module with migrated image attributes.
							$image_migration_value = [
								'props' => [
									'attrs' => [
										'builderVersion' => self::$_release_version,
										'module'         => [
											'decoration' => [
												'attributes' => [
													'desktop' => [
														'value' => [
															'attributes' => $image_attributes,
														],
													],
												],
											],
										],
									],
								],
							];

							$flat_objects[ $module_id ] = array_replace_recursive( $flat_objects[ $module_id ], $image_migration_value );
						}
					}

					// Check if this is a Section module with column-specific CSS ID and class attributes to migrate.
					if ( 'divi/section' === ( $module_data['name'] ?? '' ) ) {
						$section_attributes = [];

						// Define the paths to check for htmlAttributes and their corresponding target elements.
						// Note: Excluding 'module.advanced.htmlAttributes' as it's handled by existing main container migration.
						$html_attr_paths = [
							'column1.advanced.htmlAttributes' => 'column_1_main',
							'column2.advanced.htmlAttributes' => 'column_2_main',
							'column3.advanced.htmlAttributes' => 'column_3_main',
						];

						foreach ( $html_attr_paths as $attr_path => $target_element ) {
							// Navigate to the htmlAttributes data using the path.
							$path_parts   = explode( '.', $attr_path );
							$current_data = $module_data['props']['attrs'] ?? [];

							foreach ( $path_parts as $part ) {
								$current_data = $current_data[ $part ] ?? [];
							}

							$html_attrs = $current_data['desktop']['value'] ?? [];
							$css_id     = $html_attrs['id'] ?? '';
							$css_class  = $html_attrs['class'] ?? '';

							// Migrate CSS ID if present.
							if ( ! empty( $css_id ) ) {
								$changes_made         = true;
								$section_attributes[] = [
									'id'            => \ET_Core_Data_Utils::uuid_v4(),
									'name'          => 'id',
									'value'         => $css_id,
									'adminLabel'    => 'main' === $target_element ? 'Section ID' : str_replace( [ '_main', '_' ], [ '', ' ' ], ucfirst( $target_element ) ) . ' ID',
									'targetElement' => $target_element,
								];
							}

							// Migrate CSS Class if present.
							if ( ! empty( $css_class ) ) {
								$changes_made         = true;
								$section_attributes[] = [
									'id'            => \ET_Core_Data_Utils::uuid_v4(),
									'name'          => 'class',
									'value'         => $css_class,
									'adminLabel'    => 'main' === $target_element ? 'Section Class' : str_replace( [ '_main', '_' ], [ '', ' ' ], ucfirst( $target_element ) ) . ' Class',
									'targetElement' => $target_element,
								];
							}

							// Clear the old htmlAttributes if we found any attributes.
							if ( ! empty( $css_id ) || ! empty( $css_class ) ) {
								// Clear the specific htmlAttributes path based on which one we're processing.
								switch ( $attr_path ) {
									case 'module.advanced.htmlAttributes':
										unset( $flat_objects[ $module_id ]['props']['attrs']['module']['advanced']['htmlAttributes'] );
										break;
									case 'column1.advanced.htmlAttributes':
										unset( $flat_objects[ $module_id ]['props']['attrs']['column1']['advanced']['htmlAttributes'] );
										break;
									case 'column2.advanced.htmlAttributes':
										unset( $flat_objects[ $module_id ]['props']['attrs']['column2']['advanced']['htmlAttributes'] );
										break;
									case 'column3.advanced.htmlAttributes':
										unset( $flat_objects[ $module_id ]['props']['attrs']['column3']['advanced']['htmlAttributes'] );
										break;
								}
							}
						}

						// Apply the migration if we have attributes to migrate.
						if ( ! empty( $section_attributes ) ) {
							// Get existing custom attributes if any.
							$existing_attributes = $flat_objects[ $module_id ]['props']['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [];
							if ( is_array( $existing_attributes ) ) {
								$section_attributes = array_merge( $existing_attributes, $section_attributes );
							}

							// Update the module with migrated section attributes.
							$section_migration_value = [
								'props' => [
									'attrs' => [
										'builderVersion' => self::$_release_version,
										'module'         => [
											'decoration' => [
												'attributes' => [
													'desktop' => [
														'value' => [
															'attributes' => $section_attributes,
														],
													],
												],
											],
										],
									],
								],
							];

							$flat_objects[ $module_id ] = array_replace_recursive( $flat_objects[ $module_id ], $section_migration_value );
						}
					}

					// Check if this module has button rel attributes to migrate (any module type).
					$button_rel = $module_data['props']['attrs']['button']['innerContent']['desktop']['value']['rel'] ?? [];

					// Also check for slider-style button path (children.button.innerContent).
					if ( empty( $button_rel ) ) {
						$button_rel = $module_data['props']['attrs']['children']['button']['innerContent']['desktop']['value']['rel'] ?? [];
					}

					if ( ! empty( $button_rel ) && is_array( $button_rel ) ) {
						$changes_made = true;

						// Convert rel array to space-separated string.
						$rel_value = implode( ' ', $button_rel );

						// For the button module itself, target the main module element.
						// For other modules, target the 'button' sub-element.
						$module_name           = $module_data['name'] ?? '';
						$button_target_element = 'divi/button' === $module_name ? '' : 'button';

						// Prepare new attributes array for button rel.
						$button_rel_attributes = [
							[
								'id'            => \ET_Core_Data_Utils::uuid_v4(),
								'name'          => 'rel',
								'value'         => $rel_value,
								'adminLabel'    => 'Button Rel',
								'targetElement' => $button_target_element,
							],
						];

						// Get existing custom attributes if any.
						$existing_attributes = $flat_objects[ $module_id ]['props']['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [];
						if ( is_array( $existing_attributes ) ) {
							$button_rel_attributes = array_merge( $existing_attributes, $button_rel_attributes );
						}

						// Clear the old button rel value first (check both possible paths).
						if ( isset( $flat_objects[ $module_id ]['props']['attrs']['button']['innerContent']['desktop']['value']['rel'] ) ) {
							unset( $flat_objects[ $module_id ]['props']['attrs']['button']['innerContent']['desktop']['value']['rel'] );
						}
						if ( isset( $flat_objects[ $module_id ]['props']['attrs']['children']['button']['innerContent']['desktop']['value']['rel'] ) ) {
							unset( $flat_objects[ $module_id ]['props']['attrs']['children']['button']['innerContent']['desktop']['value']['rel'] );
						}

						// Update the module with migrated button rel attributes.
						$button_rel_migration_value = [
							'props' => [
								'attrs' => [
									'builderVersion' => self::$_release_version,
									'module'         => [
										'decoration' => [
											'attributes' => [
												'desktop' => [
													'value' => [
														'attributes' => $button_rel_attributes,
													],
												],
											],
										],
									],
								],
							],
						];

						$flat_objects[ $module_id ] = array_replace_recursive( $flat_objects[ $module_id ], $button_rel_migration_value );
					}

					// Special handling for fullwidth header module with multiple buttons.
					if ( 'divi/fullwidth-header' === ( $module_data['name'] ?? '' ) ) {
						// Check buttonOne.innerContent.desktop.value.rel.
						$button_one_rel = $module_data['props']['attrs']['buttonOne']['innerContent']['desktop']['value']['rel'] ?? [];
						// Check buttonTwo.innerContent.desktop.value.rel.
						$button_two_rel = $module_data['props']['attrs']['buttonTwo']['innerContent']['desktop']['value']['rel'] ?? [];

						// Handle Button One rel migration.
						if ( ! empty( $button_one_rel ) && is_array( $button_one_rel ) ) {
							$changes_made = true;
							$rel_value    = implode( ' ', $button_one_rel );

							$button_one_attributes = [
								[
									'id'            => \ET_Core_Data_Utils::uuid_v4(),
									'name'          => 'rel',
									'value'         => $rel_value,
									'adminLabel'    => 'Button One Rel',
									'targetElement' => 'buttonOne',
								],
							];

							// Get existing custom attributes and merge.
							$existing_attributes = $flat_objects[ $module_id ]['props']['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [];
							if ( is_array( $existing_attributes ) ) {
								$button_one_attributes = array_merge( $existing_attributes, $button_one_attributes );
							}

							// Clear old buttonOne rel value.
							unset( $flat_objects[ $module_id ]['props']['attrs']['buttonOne']['innerContent']['desktop']['value']['rel'] );

							// Update with Button One attributes.
							$button_one_migration_value = [
								'props' => [
									'attrs' => [
										'builderVersion' => self::$_release_version,
										'module'         => [
											'decoration' => [
												'attributes' => [
													'desktop' => [
														'value' => [
															'attributes' => $button_one_attributes,
														],
													],
												],
											],
										],
									],
								],
							];

							$flat_objects[ $module_id ] = array_replace_recursive( $flat_objects[ $module_id ], $button_one_migration_value );
							$existing_attributes        = $button_one_attributes; // Update for next button.
						}

						// Handle Button Two rel migration.
						if ( ! empty( $button_two_rel ) && is_array( $button_two_rel ) ) {
							$changes_made = true;
							$rel_value    = implode( ' ', $button_two_rel );

							$button_two_attributes = [
								[
									'id'            => \ET_Core_Data_Utils::uuid_v4(),
									'name'          => 'rel',
									'value'         => $rel_value,
									'adminLabel'    => 'Button Two Rel',
									'targetElement' => 'buttonTwo',
								],
							];

							// Get existing custom attributes (including any from Button One) and merge.
							$existing_attributes = $flat_objects[ $module_id ]['props']['attrs']['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [];
							if ( is_array( $existing_attributes ) ) {
								$button_two_attributes = array_merge( $existing_attributes, $button_two_attributes );
							}

							// Clear old buttonTwo rel value.
							unset( $flat_objects[ $module_id ]['props']['attrs']['buttonTwo']['innerContent']['desktop']['value']['rel'] );

							// Update with Button Two attributes.
							$button_two_migration_value = [
								'props' => [
									'attrs' => [
										'builderVersion' => self::$_release_version,
										'module'         => [
											'decoration' => [
												'attributes' => [
													'desktop' => [
														'value' => [
															'attributes' => $button_two_attributes,
														],
													],
												],
											],
										],
									],
								],
							];

							$flat_objects[ $module_id ] = array_replace_recursive( $flat_objects[ $module_id ], $button_two_migration_value );
						}
					}

					// If no specific migrations were applied, just update builder version.
					if ( ! $changes_made ) {
						$flat_objects[ $module_id ]['props']['attrs']['builderVersion'] = self::$_release_version;
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
		return $content;
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
