<?php
/**
 * Nested Module Migration
 *
 * Handles the migration of nested module layout display properties.
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Migration;

use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Framework\Utility\ArrayUtility;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\StringUtility;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use ET\Builder\FrontEnd\BlockParser\BlockParser;
use ET\Builder\Migration\MigrationContentBase;
use ET\Builder\Migration\MigrationContext;
use ET\Builder\Migration\Utils\MigrationUtils;
use ET\Builder\Packages\Conversion\AdvancedOptionConversion;
use ET\Builder\Packages\GlobalData\GlobalPreset;

/**
 * Nested Module Migration Class.
 *
 * @since ??
 */
class NestedModuleMigration extends MigrationContentBase {

	/**
	 * The migration name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_name = 'nested-modules.v1';

	/**
	 * List of module names that need nested module layout (block format).
	 *
	 * @since ??
	 *
	 * @var string[]
	 */
	private static $_block_modules = [
		'divi/accordion',
		'divi/accordion-item',
		'divi/audio',
		'divi/blurb',
		'divi/button',
		'divi/circle-counter',
		'divi/comments',
		'divi/countdown-timer',
		'divi/counter',
		'divi/counters',
		'divi/cta',
		'divi/heading',
		'divi/hero',
		'divi/icon',
		'divi/image',
		'divi/login',
		'divi/number-counter',
		'divi/pagination',
		'divi/post-slider',
		'divi/post-title',
		'divi/pricing-table',
		'divi/search',
		'divi/slide',
		'divi/slider',
		'divi/social-media-follow',
		'divi/social-media-follow-item',
		'divi/tab',
		'divi/testimonial',
		'divi/text',
		'divi/toggle',
		'divi/video',
	];

	/**
	 * List of grid modules that use layout display properties.
	 *
	 * @since ??
	 *
	 * @var string[]
	 */
	private static $_grid_modules = [
		'divi/portfolio',
		'divi/filterable-portfolio',
		'divi/blog',
		'divi/gallery',
		'divi/fullwidth-portfolio',
	];

	/**
	 * List of large column types.
	 *
	 * @since ??
	 *
	 * @var string[]
	 */
	private static $_large_columns = [ '2_3', '3_4', '4_4' ];

	/**
	 * List of full-width column types.
	 *
	 * @since ??
	 *
	 * @var string[]
	 */
	private static $_fullwidth_columns = [ '1_1', '4_4' ];

	/**
	 * List of column module names.
	 *
	 * @since ??
	 *
	 * @var string[]
	 */
	private static $_column_modules = [ 'divi/column', 'divi/column-inner' ];

	/**
	 * List of column-like modules that need nested module migration.
	 *
	 * @since ??
	 *
	 * @var string[]
	 */
	private static $_column_like_modules = [
		'divi/column',
		'divi/column-inner',
		'divi/pricing-table',
	];

	/**
	 * List of signup/email optin modules.
	 *
	 * @since ??
	 *
	 * @var string[]
	 */
	private static $_signup_modules = [
		'divi/signup',
	];

	/**
	 * List of team member modules.
	 *
	 * @since ??
	 *
	 * @var string[]
	 */
	private static $_team_member_modules = [
		'divi/team-member',
	];

	/**
	 * List of contact field modules.
	 *
	 * @since ??
	 *
	 * @var string[]
	 */
	private static $_contact_field_modules = [
		'divi/contact-field',
	];

	/**
	 * The nested module release version string.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_release_version = '5.0.0-public-beta.1';

	/**
	 * Run the nested module migration.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function load(): void {
		/**
		 * Hook into the portability import process to migrate nested module content.
		 *
		 * This filter ensures that imported content is properly migrated to include
		 * layout display properties for modules that were created before the nested
		 * module feature was introduced. The migration applies to both block-based and
		 * shortcode-based content during the import process.
		 *
		 * @see NestedModuleMigration::migrate_the_content()
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
		// Combine all module lists that this migration affects.
		$all_modules = array_merge(
			self::$_block_modules,
			self::$_grid_modules,
			self::$_column_like_modules,
			self::$_signup_modules,
			self::$_team_member_modules,
			self::$_contact_field_modules
		);

		if ( ! MigrationUtils::content_needs_migration(
			$content,
			self::$_release_version,
			$all_modules
		) ) {
			return $content;
		}

		// Handle block-based migration.
		// Note: D4 shortcodes are converted to D5 blocks before migrations run,
		// so we only need to handle block-based content here.
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
		// Combine all module arrays for the version check.
		$all_modules = array_merge(
			self::$_block_modules,
			self::$_grid_modules,
			self::$_column_like_modules,
			self::$_signup_modules,
			self::$_team_member_modules,
			self::$_contact_field_modules
		);

		if ( ! MigrationUtils::content_needs_migration(
			$content,
			self::$_release_version,
			$all_modules
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

			// Migration 5: Column gap inheritance.
			// This must run BEFORE other migrations to set inherited columnGap values on children.
			// Process all rows to propagate columnGap values to children.
			// IMPORTANT: Process rows from deepest to shallowest (bottom-up) so that closer
			// parents override values set by distant parents.
			$rows_by_depth = MigrationUtils::sort_modules_by_depth( $flat_objects, [ 'divi/row', 'divi/row-inner' ] );

			foreach ( $rows_by_depth as $module_id ) {
				$module_data           = $flat_objects[ $module_id ];
				$column_gap_migrations = self::_migrate_column_gap_inheritance( $module_data, $flat_objects );
				if ( ! empty( $column_gap_migrations ) ) {
					$changes_made = true;
					foreach ( $column_gap_migrations as $child_id => $migration_data ) {
						$flat_objects[ $child_id ] = array_replace_recursive( $flat_objects[ $child_id ], $migration_data );
					}
				}
			}

			foreach ( $flat_objects as $module_id => $module_data ) {
				$module_name     = $module_data['name'] ?? '';
				$builder_version = $module_data['props']['attrs']['builderVersion'] ?? '0.0.0';

				// Skip if module is already at or above the release version.
				if ( StringUtility::version_compare( $builder_version, self::$_release_version, '>=' ) ) {
					continue;
				}

				$new_value = null;

				// Migration 1: Nested modules layout display.
				if ( in_array( $module_name, self::$_block_modules, true ) ) {
					// Check if layout display is already set.
					$existing_display = $module_data['props']['attrs']['module']['decoration']['layout']['desktop']['value']['display'] ?? null;

					// Only set display to 'block' if no value is currently set.
					if ( null === $existing_display ) {
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
					}
				}

				// Migration 2: Email Optin (Signup) module layout.
				if ( in_array( $module_name, self::$_signup_modules, true ) ) {
					$email_optin_migration = self::_migrate_email_optin_module( $module_data, $flat_objects );
					if ( $email_optin_migration ) {
						$new_value = $email_optin_migration;
					}
				}

				// Migration 3: Grid modules (Portfolio, Blog, Filterable Portfolio, Gallery).
				if ( in_array( $module_name, self::$_grid_modules, true ) ) {
					$grid_migration = self::_migrate_grid_module( $module_data, $flat_objects );
					if ( $grid_migration ) {
						$new_value = $grid_migration;
					}
				}

				// Migration 4: Team Member module layout.
				if ( in_array( $module_name, self::$_team_member_modules, true ) ) {
					$team_member_migration = self::_migrate_team_member_module( $module_data, $flat_objects );
					if ( $team_member_migration ) {
						$new_value = $team_member_migration;
					}
				}

				// Migration 5: Column and Pricing Table Item flexType attribute location.
				// Migrate module.advanced.flexType to module.decoration.sizing.flexType.
				if ( in_array( $module_name, self::$_column_like_modules, true ) ) {
					$flex_type_migration = self::_migrate_column_flex_type( $module_data );
					if ( $flex_type_migration ) {
						$new_value = $flex_type_migration;
					}
				}

				// Migration 6: Contact Field fullwidth to flexType migration.
				// Migrate fieldItem.advanced.fullwidth to module.decoration.sizing.flexType.
				if ( in_array( $module_name, self::$_contact_field_modules, true ) ) {
					$contact_field_migration = self::_migrate_contact_field_fullwidth( $module_data );
					if ( $contact_field_migration ) {
						$new_value = $contact_field_migration;
					}
				}

				// Apply the migration if one was generated.
				if ( $new_value ) {
					$changes_made               = true;
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
	 * Migrate Email Optin (Signup) module layout.
	 *
	 * @since ??
	 *
	 * @param array $module_data   The module data to migrate.
	 * @param array $flat_objects  All flat module objects (for parent detection).
	 *
	 * @return array|null The migration data structure or null if no migration needed.
	 */
	private static function _migrate_email_optin_module( array $module_data, array $flat_objects ): ?array {
		// Get the current layout value. Default is 'left_right' if not set.
		$layout_attr = $module_data['props']['attrs']['module']['advanced']['layout'] ?? null;

		if ( is_array( $layout_attr ) ) {
			// D5 format: nested responsive structure.
			$layout_value = $layout_attr['desktop']['value'] ?? 'left_right';
		} elseif ( is_string( $layout_attr ) ) {
			// D4 format: simple string.
			$layout_value = $layout_attr;
		} else {
			// Not set: default to left_right.
			$layout_value = 'left_right';
		}

		// Get parent column type to determine if we're in a small column.
		$parent_column_type = self::_get_parent_column_type( $module_data, $flat_objects );
		$is_small_column    = ! in_array( $parent_column_type, self::$_large_columns, true );

		// Check if module is within an inner row and parent is not 4_4.
		$has_inner_row_parent = self::_has_inner_row_in_hierarchy( $module_data, $flat_objects );
		$is_inner_row_case    = $has_inner_row_parent && '4_4' !== $parent_column_type;

		// If within inner row with non-full-width column, treat as small column.
		if ( $is_inner_row_case ) {
			$is_small_column = true;
		}

		// Determine the flex direction based on layout value and column size.
		$flex_direction_desktop = self::_get_flex_direction_for_layout( $layout_value, $is_small_column );
		$flex_direction_tablet  = self::_get_tablet_flex_direction( $layout_value, $is_small_column );
		$flex_direction_phone   = self::_get_phone_flex_direction( $flex_direction_desktop, $flex_direction_tablet );

		// Determine if layout is horizontal (row) or vertical (column) based on flex direction.
		// Horizontal layouts (left_right, right_left) use D4 spacing-preserving column gap.
		// Vertical layouts (top_bottom, bottom_top) use rowGap: 25px.
		$is_horizontal_layout = in_array( $flex_direction_desktop, [ 'row', 'row-reverse' ], true );
		$column_gap           = self::_get_signup_horizontal_column_gap( $module_data );

		// Build the base new value structure with appropriate gap based on layout direction.
		$new_value = [
			'props' => [
				'attrs' => [
					'builderVersion' => self::$_release_version,
					'module'         => [
						'decoration' => [
							'layout' => [
								'desktop' => [
									'value' => [
										'display'   => 'flex',
										'columnGap' => $is_horizontal_layout ? $column_gap : '0px',
										'rowGap'    => $is_horizontal_layout ? '0px' : '25px',
									],
								],
							],
						],
					],
				],
			],
		];

		// Only set flexDirection on desktop if it's not 'column' (the actual default for signup module).
		if ( 'column' !== $flex_direction_desktop ) {
			$new_value['props']['attrs']['module']['decoration']['layout']['desktop']['value']['flexDirection'] = $flex_direction_desktop;
		}

		// Add tablet and phone flex direction if they differ from desktop.
		if ( $flex_direction_tablet && $flex_direction_tablet !== $flex_direction_desktop ) {
			$new_value['props']['attrs']['module']['decoration']['layout']['tablet'] = [
				'value' => [
					'flexDirection' => $flex_direction_tablet,
				],
			];
		}

		if ( $flex_direction_phone && $flex_direction_phone !== $flex_direction_desktop ) {
			$new_value['props']['attrs']['module']['decoration']['layout']['phone'] = [
				'value' => [
					'flexDirection' => $flex_direction_phone,
				],
			];
		}

		return $new_value;
	}

	/**
	 * Get Signup horizontal column gap value for migration.
	 *
	 * Uses D4 form field right margin when available, then falls back to 30px
	 * to preserve D4 default spacing behavior.
	 *
	 * @since ??
	 *
	 * @param array $module_data The module data.
	 *
	 * @return string
	 */
	private static function _get_signup_horizontal_column_gap( array $module_data ): string {
		// Default fallback preserving D4 behavior for Signup form fields.
		$default_column_gap = '30px';

		// Prefer already-converted spacing attribute if available.
		$field_margin = $module_data['props']['attrs']['field']['decoration']['spacing']['desktop']['value']['margin'] ?? null;

		if ( is_array( $field_margin ) ) {
			$right_margin = $field_margin['right'] ?? '';
			if ( ! empty( $right_margin ) ) {
				return $right_margin;
			}
		}

		// Fallback to D4 source attribute (if still available in payload).
		$form_field_custom_margin = $module_data['props']['attrs']['form_field_custom_margin']
			?? $module_data['props']['attrs']['module']['advanced']['formFieldCustomMargin']['desktop']['value']
			?? '';

		if ( empty( $form_field_custom_margin ) || ! is_string( $form_field_custom_margin ) ) {
			return $default_column_gap;
		}

		$spacing      = AdvancedOptionConversion::convertSpacing( $form_field_custom_margin );
		$right_margin = $spacing['right'] ?? '';

		return ! empty( $right_margin ) ? $right_margin : $default_column_gap;
	}

	/**
	 * Get parent column type for a module.
	 *
	 * @since ??
	 *
	 * @param array $module_data  The module data.
	 * @param array $flat_objects All flat module objects.
	 *
	 * @return string|null The parent column type or null if not found.
	 */
	private static function _get_parent_column_type( array $module_data, array $flat_objects ): ?string {
		$parent_id = $module_data['parent'] ?? null;
		if ( ! $parent_id || ! isset( $flat_objects[ $parent_id ] ) ) {
			return null;
		}

		$parent_module = $flat_objects[ $parent_id ];

		// Verify parent is a column.
		if ( ! in_array( $parent_module['name'], self::$_column_modules, true ) ) {
			return null;
		}

		// Try to get column type (block column format: 4_4, 3_4, 2_3, etc.).
		$column_type = $parent_module['props']['attrs']['module']['advanced']['type']['desktop']['value'] ?? null;

		// If not found, check for flexType in new location and map it back to old column type.
		if ( ! $column_type ) {
			// Check new location first.
			$flex_type = $parent_module['props']['attrs']['module']['decoration']['sizing']['desktop']['value']['flexType'] ?? null;

			// Fallback to old location for backwards compatibility during migration.
			if ( ! $flex_type ) {
				$flex_type = $parent_module['props']['attrs']['module']['advanced']['flexType']['desktop']['value'] ?? null;
			}

			$column_type = MigrationUtils::map_flex_type_to_column_type( $flex_type );
		}

		return $column_type;
	}

	/**
	 * Check if a module has an inner row in its parent hierarchy.
	 *
	 * @since ??
	 *
	 * @param array $module_data  The module data.
	 * @param array $flat_objects All flat module objects.
	 *
	 * @return bool True if an inner row exists in the parent hierarchy.
	 */
	private static function _has_inner_row_in_hierarchy( array $module_data, array $flat_objects ): bool {
		$current_id = $module_data['parent'] ?? null;

		// Traverse up the parent hierarchy.
		while ( $current_id && isset( $flat_objects[ $current_id ] ) ) {
			$current_module = $flat_objects[ $current_id ];

			// Check if this is an inner row.
			if ( 'divi/row-inner' === $current_module['name'] ) {
				return true;
			}

			// Move to the next parent.
			$current_id = $current_module['parent'] ?? null;
		}

		return false;
	}


	/**
	 * Get the flex direction for a given layout value and column size.
	 *
	 * @since ??
	 *
	 * @param string $layout_value   The layout value from the Form Layout option.
	 * @param bool   $is_small_column Whether the module is in a small column.
	 *
	 * @return string|null The flex direction to apply, or null if no mapping is needed.
	 */
	private static function _get_flex_direction_for_layout( string $layout_value, bool $is_small_column ): ?string {
		switch ( $layout_value ) {
			case 'left_right':
				// Body On Left, Form On Right.
				// In small columns, force column direction instead of row.
				return $is_small_column ? 'column' : 'row';

			case 'right_left':
				// Body On Right, Form On Left.
				return $is_small_column ? 'column-reverse' : 'row-reverse';

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
	 * Get the tablet breakpoint flex direction for small columns.
	 *
	 * @since ??
	 *
	 * @param string $layout_value   The layout value from the Form Layout option.
	 * @param bool   $is_small_column Whether the module is in a small column.
	 *
	 * @return string|null The tablet flex direction, or null if no tablet-specific direction is needed.
	 */
	private static function _get_tablet_flex_direction( string $layout_value, bool $is_small_column ): ?string {
		// Only set tablet direction for small columns that were forced to column on desktop.
		if ( ! $is_small_column ) {
			return null;
		}

		switch ( $layout_value ) {
			case 'left_right':
				// Small columns use column on desktop, but row on tablet.
				return 'row';

			case 'right_left':
				// Small columns use column-reverse on desktop, but row-reverse on tablet.
				return 'row-reverse';

			case 'top_bottom':
			case 'bottom_top':
			default:
				// Column layouts don't need tablet-specific overrides.
				return null;
		}
	}

	/**
	 * Get the phone breakpoint flex direction.
	 *
	 * @since ??
	 *
	 * @param string|null $desktop_flex_direction The desktop flex direction.
	 * @param string|null $tablet_flex_direction  The tablet flex direction.
	 *
	 * @return string|null The phone flex direction, or null if no phone-specific direction is needed.
	 */
	private static function _get_phone_flex_direction( ?string $desktop_flex_direction, ?string $tablet_flex_direction ): ?string {
		// If tablet has row direction set, phone needs to override it to column.
		if ( $tablet_flex_direction ) {
			switch ( $tablet_flex_direction ) {
				case 'row':
					return 'column';

				case 'row-reverse':
					return 'column-reverse';
			}
		}

		// If no tablet override, check desktop direction.
		if ( ! $desktop_flex_direction ) {
			return null;
		}

		switch ( $desktop_flex_direction ) {
			case 'row':
				return 'column';

			case 'row-reverse':
				return 'column-reverse';

			case 'column':
			case 'column-reverse':
			default:
				// Column layouts don't need phone-specific overrides (unless tablet had row).
				return null;
		}
	}

	/**
	 * Migrate Team Member module layout.
	 *
	 * For modules with version less than 5.0:
	 * - If NOT in a full-width column (1_1 or 4_4), set flex direction to column
	 * - If in a full-width column, no migration is applied
	 *
	 * NOTE: This migration respects layout set by Conversion.php for Person modules.
	 * If display is 'block', migration is skipped (#47406). If display is 'flex' with
	 * row or row-reverse (D4→D5 conversion default), migration is skipped so nested
	 * migration does not replace horizontal layout with flex column (#49701).
	 *
	 * @since ??
	 *
	 * @param array $module_data   The module data to migrate.
	 * @param array $flat_objects  All flat module objects (for parent detection).
	 *
	 * @return array|null The migration data structure or null if no migration needed.
	 */
	private static function _migrate_team_member_module( array $module_data, array $flat_objects ): ?array {
		// Get the module's builder version.
		$builder_version = $module_data['props']['attrs']['builderVersion'] ?? '0.0.0';

		// Only apply migration if version is less than 5.0.
		if ( version_compare( $builder_version, '5.0', '>=' ) ) {
			return null;
		}

		// Get parent column type.
		$parent_column_type = self::_get_parent_column_type( $module_data, $flat_objects );

		// Check if in a full-width column.
		$is_full_width_column = in_array( $parent_column_type, self::$_fullwidth_columns, true );

		// Only apply migration if NOT in a full-width column.
		if ( $is_full_width_column ) {
			return null;
		}

		// Respect layout already set by Conversion.php or saved content.
		$layout_value       = $module_data['props']['attrs']['module']['decoration']['layout']['desktop']['value'] ?? [];
		$existing_display   = $layout_value['display'] ?? null;
		$existing_direction = $layout_value['flexDirection'] ?? null;

		// Preserve block layout (#47406).
		if ( 'block' === $existing_display ) {
			return null;
		}

		// Preserve flex row from D4→D5 conversion; do not replace with flex column (#49701).
		if ( 'flex' === $existing_display && ( 'row' === $existing_direction || 'row-reverse' === $existing_direction ) ) {
			return null;
		}

		// Build the new value structure with flex direction set to column.
		$new_value = [
			'props' => [
				'attrs' => [
					'builderVersion' => self::$_release_version,
					'module'         => [
						'decoration' => [
							'layout' => [
								'desktop' => [
									'value' => [
										'display'       => 'flex',
										'flexDirection' => 'column',
									],
								],
							],
						],
					],
				],
			],
		];

		return $new_value;
	}

	/**
	 * Migrate Grid module layout.
	 *
	 * @since ??
	 *
	 * @param array $module_data   The module data to migrate.
	 * @param array $flat_objects  All flat module objects (for parent detection).
	 *
	 * @return array|null The migration data structure or null if no migration needed.
	 */
	private static function _migrate_grid_module( array $module_data, array $flat_objects ): ?array {
		$module_name = $module_data['name'] ?? '';

		// Get layout configuration based on module type.
		$layout_config = self::_get_grid_layout_config( $module_name, $module_data );

		if ( ! $layout_config ) {
			return null;
		}

		$layout_value         = $layout_config['layout_value'];
		$flex_type_value      = $layout_config['flex_type_value'];
		$grid_attr_path       = $layout_config['grid_attr_path'];
		$layout_display_value = $layout_config['layout_display_value'];
		$layout_attr_original = $layout_config['layout_attr'] ?? null;

		// Determine if this is a grid or fullwidth layout.
		$is_fullwidth = ( 'fullwidth' === $layout_value || 'on' === $layout_value );
		$is_grid      = ( 'grid' === $layout_value || 'off' === $layout_value );

		if ( ! $is_fullwidth && ! $is_grid ) {
			// No migration needed if layout value is not recognized.
			return null;
		}

		$layout_display_from_module = $layout_config['layout_display_from_module'] ?? false;

		// Special case: If module is set to fullwidth and Layout Style is already 'flex',
		// AND this display value came from the module itself (not from a preset),
		// then the module is already in fullwidth mode and we don't need to migrate.
		// This means the module was already migrated or explicitly set to use flex layout.
		// Note: If display:flex came from a preset, we still need to migrate to apply it to the module.
		if ( $is_fullwidth && 'flex' === $layout_display_value && $layout_display_from_module ) {
			// Skip migration - module itself already has display:flex set.
			return null;
		}

		// Special case: If module is set to Grid and Layout Style is already 'grid' (not 'flex'),
		// AND this display value came from the module itself (not from a preset),
		// then the module is already in grid mode and we don't need to migrate.
		// This means the module was already migrated or explicitly set to use grid layout.
		// Note: If display:grid came from a preset, we still need to migrate to apply it to the module.
		if ( $is_grid && 'grid' === $layout_display_value && $layout_display_from_module ) {
			// Skip migration - module itself already has display:grid set.
			return null;
		}

		// Build the new value structure.
		$new_value = [
			'props' => [
				'attrs' => [
					'builderVersion' => self::$_release_version,
				],
			],
		];

		// Clear the old layout attribute (except for fullwidth-portfolio and gallery which need to keep it).
		// For fullwidth-portfolio, portfolio.advanced.layout controls carousel vs grid mode.
		// For gallery, module.advanced.fullwidth controls slider vs grid mode.
		if ( isset( $layout_config['layout_attr_path'] )
			&& 'divi/fullwidth-portfolio' !== $module_name
			&& 'divi/gallery' !== $module_name
		) {
			$new_value = ArrayUtility::set_value(
				$new_value,
				explode( '.', 'props.attrs.' . $layout_config['layout_attr_path'] ),
				null
			);
		}

		// Migrate based on layout type.
		if ( $is_fullwidth ) {
			// Fullwidth mode: Set layout style to flex.
			// But first check: if the layout attribute was NULL (using default) and the module
			// has a preset with a layout style set, don't override it.
			$layout_attr_original = $layout_config['layout_attr'] ?? null;

			if ( null === $layout_attr_original ) {
				// Layout attribute doesn't exist (using default).
				// Check if module has a preset with layout style set.
				$layout_style_path = $grid_attr_path . '.decoration.layout.desktop.value.display';
				$module_name       = $module_data['name'] ?? '';
				$module_attrs      = $module_data['props']['attrs'] ?? [];

				if ( GlobalPreset::preset_has_attribute(
					[
						'moduleName'    => $module_name,
						'moduleAttrs'   => $module_attrs,
						'attributePath' => $layout_style_path,
					]
				) ) {
					// Preset has layout style set - don't override it.
					return $new_value;
				}
			}

			// Set layout style to flex (or block for Blog - D4 fullwidth used float/block-based CSS).
			$display_value       = ( 'divi/blog' === $module_name ) ? 'block' : 'flex';
			$layout_config_value = [
				'desktop' => [
					'value' => [
						'display' => $display_value,
					],
				],
			];

			$new_value = ArrayUtility::set_value(
				$new_value,
				explode( '.', 'props.attrs.' . $grid_attr_path . '.decoration.layout' ),
				$layout_config_value
			);

			return $new_value;
		}

		// Grid mode: Migrate to grid layout with column counts.
		// Check if the existing Layout Style is set to 'flex' or 'block' (flex-based layouts).
		// Note: 'block' is the default flex-based layout style for modules.
		// If Layout Style is 'flex' and Layout is Grid, we need to map flexType to grid column count.
		$is_flex_layout    = 'flex' === $layout_display_value;
		$is_block_layout   = 'block' === $layout_display_value;
		$has_layout_preset = $layout_config['has_layout_preset'] ?? false;

		// Determine default column count based on module type.
		// Gallery module default is 4 columns, others default to 3.
		$default_column_count = ( 'divi/gallery' === $module_name ) ? 4 : 3;

		// Determine the grid column count.
		$uses_parent_column = false;

		// Get parent column type once.
		$parent_column_type = self::_get_parent_column_type( $module_data, $flat_objects );

		// Determine whether to use flex-based sizing (flexType) or parent column detection.
		// Priority:
		// 1. If layout_display_value is explicitly 'flex', use flexType logic.
		// 2. If layout_display_value is explicitly 'block', use parent column detection.
		// 3. If layout_display_value is null (not set):
		// a. If flexType is set (either at module level or from preset), use flex logic with flexType.
		// b. If there's a layout preset applied (even with null flexType), use flex logic (default flexType).
		// c. If no flexType and no preset, default to block layout (parent column detection).

		if ( $is_flex_layout ) {
			// Explicitly set to flex: Use flexType to determine column count.
			$grid_column_count = MigrationUtils::map_flex_type_to_column_count( $flex_type_value );
		} elseif ( $is_block_layout ) {
			// Explicitly set to block: Use parent column width to determine column count.
			if ( $parent_column_type ) {
				$grid_column_count  = MigrationUtils::map_parent_column_to_module_column_breakdown( $parent_column_type, $module_name );
				$uses_parent_column = true;
			} else {
				$grid_column_count = $default_column_count;
			}
		} elseif ( null !== $flex_type_value ) {
			// No explicit layout display, but flexType is set (module or preset): Use flex logic.
			$grid_column_count = MigrationUtils::map_flex_type_to_column_count( $flex_type_value );
		} elseif ( $has_layout_preset ) {
			// No explicit layout display and no flexType, but layout preset is applied: Use flex logic with default flexType.
			// This handles layout presets that set layout to grid but leave flexType and display as defaults.
			$grid_column_count = MigrationUtils::map_flex_type_to_column_count( null ); // Default: 4 columns.
			// phpcs:ignore Universal.ControlStructures.DisallowLonelyIf.Found -- Intentional else-if pattern for readability.
		} else {
			// No explicit layout display, no flexType, and no preset: Default to block layout (parent column detection).
			// This preserves the original behavior for modules with no layout customization.
			if ( $parent_column_type ) {
				$grid_column_count  = MigrationUtils::map_parent_column_to_module_column_breakdown( $parent_column_type, $module_name );
				$uses_parent_column = true;
			} else {
				$grid_column_count = $default_column_count;
			}
		}

		// Build layout config with desktop, tablet, and phone values.
		$layout_config_value = [
			'desktop' => [
				'value' => [
					'display'         => 'grid',
					'gridColumnCount' => (string) $grid_column_count,
				],
			],
		];

		// If we used parent column detection, also set tablet and phone column counts.
		if ( $uses_parent_column ) {
			// Gallery module defaults to 3 columns on tablet (matching D4 behavior).
			$tablet_column_count = ( 'divi/gallery' === $module_name ) ? '3' : '2';

			$layout_config_value['tablet'] = [
				'value' => [
					'gridColumnCount' => $tablet_column_count,
				],
			];
			$layout_config_value['phone']  = [
				'value' => [
					'gridColumnCount' => '1',
				],
			];
		} else {
			// We used flexType mapping - check for responsive flexType values.
			$flex_type_attr_full = $layout_config['flex_type_attr'] ?? null;

			if ( is_array( $flex_type_attr_full ) ) {
				// Map flexType for each responsive breakpoint that exists in the flexType structure.
				// Skip desktop as it's already handled above.
				foreach ( $flex_type_attr_full as $breakpoint => $breakpoint_data ) {
					if ( 'desktop' === $breakpoint ) {
						continue; // Desktop already set above.
					}

					$breakpoint_flex_type = $breakpoint_data['value'] ?? null;
					if ( $breakpoint_flex_type ) {
						$breakpoint_column_count                                        = MigrationUtils::map_flex_type_to_column_count( $breakpoint_flex_type );
						$layout_config_value[ $breakpoint ]['value']['gridColumnCount'] = (string) $breakpoint_column_count;
					}
				}
			} else {
				// No flex_type_attr, but we might have flexType from layout option group preset.
				// Check for tablet and phone breakpoints in the preset.
				$module_attrs        = $module_data['props']['attrs'] ?? [];
				$flex_type_attr_path = $layout_config['flex_type_attr_path'] ?? null;

				if ( $flex_type_attr_path ) {
					// Get all enabled breakpoint names dynamically.
					$enabled_breakpoints = Breakpoint::get_enabled_breakpoint_names();

					foreach ( $enabled_breakpoints as $breakpoint ) {
						if ( 'desktop' === $breakpoint ) {
							continue; // Desktop already set above.
						}

						// Check if preset has a flexType value for this breakpoint.
						$breakpoint_flex_type = self::_get_attribute_from_layout_group_preset(
							$module_name,
							$module_attrs,
							$flex_type_attr_path . '.' . $breakpoint . '.value'
						);

						if ( $breakpoint_flex_type ) {
							$breakpoint_column_count                                        = MigrationUtils::map_flex_type_to_column_count( $breakpoint_flex_type );
							$layout_config_value[ $breakpoint ]['value']['gridColumnCount'] = (string) $breakpoint_column_count;
						}
					}
				}
			}
		}

		$new_value = ArrayUtility::set_value(
			$new_value,
			explode( '.', 'props.attrs.' . $grid_attr_path . '.decoration.layout' ),
			$layout_config_value
		);

		return $new_value;
	}

	/**
	 * Get layout configuration for a grid module.
	 *
	 * @since ??
	 *
	 * @param string $module_name  The module name.
	 * @param array  $module_data  The module data.
	 *
	 * @return array|null Layout configuration or null if not applicable.
	 */
	private static function _get_grid_layout_config( string $module_name, array $module_data ): ?array {
		$config = [];

		switch ( $module_name ) {
			case 'divi/portfolio':
				$layout_attr         = $module_data['props']['attrs']['portfolio']['advanced']['layout'] ?? null;
				$flex_type_attr      = $module_data['props']['attrs']['portfolioGrid']['advanced']['flexType'] ?? null;
				$layout_display_attr = $module_data['props']['attrs']['portfolioGrid']['decoration']['layout'] ?? null;

				$config = [
					'layout_attr_path'    => 'portfolio.advanced.layout',
					'flex_type_attr_path' => 'portfolioGrid.advanced.flexType',
					'grid_attr_path'      => 'portfolioGrid',
				];
				break;

			case 'divi/blog':
				$layout_attr         = $module_data['props']['attrs']['fullwidth']['advanced']['enable'] ?? null;
				$flex_type_attr      = $module_data['props']['attrs']['blogGrid']['advanced']['flexType'] ?? null;
				$layout_display_attr = $module_data['props']['attrs']['blogGrid']['decoration']['layout'] ?? null;

				$config = [
					'layout_attr_path'    => 'fullwidth.advanced.enable',
					'flex_type_attr_path' => 'blogGrid.advanced.flexType',
					'grid_attr_path'      => 'blogGrid',
				];
				break;

			case 'divi/filterable-portfolio':
				$layout_attr         = $module_data['props']['attrs']['portfolio']['advanced']['layout'] ?? null;
				$flex_type_attr      = $module_data['props']['attrs']['portfolioGrid']['advanced']['flexType'] ?? null;
				$layout_display_attr = $module_data['props']['attrs']['portfolioGrid']['decoration']['layout'] ?? null;

				$config = [
					'layout_attr_path'    => 'portfolio.advanced.layout',
					'flex_type_attr_path' => 'portfolioGrid.advanced.flexType',
					'grid_attr_path'      => 'portfolioGrid',
				];
				break;

			case 'divi/fullwidth-portfolio':
				$layout_attr         = $module_data['props']['attrs']['portfolio']['advanced']['layout'] ?? null;
				$flex_type_attr      = $module_data['props']['attrs']['portfolioGrid']['advanced']['flexType'] ?? null;
				$layout_display_attr = $module_data['props']['attrs']['module']['decoration']['layout'] ?? null;

				$config = [
					'layout_attr_path'    => 'portfolio.advanced.layout',
					'flex_type_attr_path' => 'portfolioGrid.advanced.flexType',
					'grid_attr_path'      => 'module',
				];
				break;

			case 'divi/gallery':
				$layout_attr         = $module_data['props']['attrs']['module']['advanced']['fullwidth'] ?? null;
				$flex_type_attr      = $module_data['props']['attrs']['galleryGrid']['advanced']['flexType'] ?? null;
				$layout_display_attr = $module_data['props']['attrs']['galleryGrid']['decoration']['layout'] ?? null;

				$config = [
					'layout_attr_path'    => 'module.advanced.fullwidth',
					'flex_type_attr_path' => 'galleryGrid.advanced.flexType',
					'grid_attr_path'      => 'galleryGrid',
				];
				break;

			default:
				return null;
		}

		$module_attrs = $module_data['props']['attrs'] ?? [];

		// Check if module has a layout option group preset applied.
		// This is important to distinguish between "no attributes at all" vs "preset with defaults".
		$has_layout_preset = self::_has_layout_group_preset( $module_attrs );

		// Extract layout value from responsive structure.
		if ( is_array( $layout_attr ) ) {
			// D5 format: nested responsive structure.
			$layout_value = $layout_attr['desktop']['value'] ?? null;
		} elseif ( is_string( $layout_attr ) ) {
			// D4 format or simple string.
			$layout_value = $layout_attr;
		} else {
			// No layout attribute set - check layout option group preset.
			$layout_value = self::_get_attribute_from_layout_group_preset(
				$module_name,
				$module_attrs,
				$config['layout_attr_path'] . '.desktop.value'
			);

			// Also check module preset for Blog module.
			if ( 'divi/blog' === $module_name && null === $layout_value && ! empty( $module_attrs['modulePreset'] ?? '' ) ) {
				$preset_ids = GlobalPreset::normalize_preset_stack( $module_attrs['modulePreset'] );
				$all_data   = GlobalPreset::get_data();
				foreach ( $preset_ids as $preset_id ) {
					// First, check for fullwidth.advanced.enable (for backward compatibility with un-migrated presets).
					$preset_fullwidth_attr = $all_data['module']['divi/blog']['items'][ $preset_id ]['attrs']['fullwidth']['advanced']['enable'] ?? null;
					if ( null !== $preset_fullwidth_attr ) {
						// Handle both D4 format (string) and D5 format (responsive structure).
						if ( is_array( $preset_fullwidth_attr ) ) {
							// D5 format: nested responsive structure.
							$preset_layout_value = $preset_fullwidth_attr['desktop']['value'] ?? null;
						} elseif ( is_string( $preset_fullwidth_attr ) ) {
							// D4 format or simple string.
							$preset_layout_value = $preset_fullwidth_attr;
						} else {
							$preset_layout_value = null;
						}

						if ( null !== $preset_layout_value ) {
							$layout_value = $preset_layout_value;
							break;
						}
					}

					// If fullwidth.advanced.enable not found, check blogGrid.decoration.layout.display
					// (preset migration removes fullwidth.advanced.enable but sets display = 'grid' when original was 'off').
					$preset_display_attr = $all_data['module']['divi/blog']['items'][ $preset_id ]['attrs']['blogGrid']['decoration']['layout']['desktop']['value']['display'] ?? null;
					if ( null !== $preset_display_attr ) {
						// If display = 'grid', original was fullwidth.advanced.enable = 'off' (grid layout).
						// If display = 'flex' or 'block', original was fullwidth.advanced.enable = 'on' (fullwidth layout).
						if ( 'grid' === $preset_display_attr ) {
							$layout_value = 'off';
							break;
						} elseif ( 'flex' === $preset_display_attr || 'block' === $preset_display_attr ) {
							$layout_value = 'on';
							break;
						}
					}
				}
			}

			// If no preset value, use default based on module type.
			if ( null === $layout_value ) {
				// Blog: fullwidth.advanced.enable default is 'on' (fullwidth).
				// Portfolio/Filterable Portfolio: portfolio.advanced.layout default is 'fullwidth'.
				// Fullwidth Portfolio: portfolio.advanced.layout default is 'on' (carousel/fullwidth).
				// Gallery: module.advanced.fullwidth default is 'off' (grid mode).
				if ( 'divi/gallery' === $module_name ) {
					$layout_value = 'off'; // Gallery default is grid.
				} elseif ( 'divi/portfolio' === $module_name || 'divi/filterable-portfolio' === $module_name ) {
					$layout_value = 'fullwidth'; // Portfolio/Filterable Portfolio default is fullwidth.
				} else {
					$layout_value = 'on'; // Blog/Fullwidth Portfolio default is fullwidth (on).
				}
			}
		}

		// Store the full flex_type_attr for responsive breakpoint processing.
		$config['flex_type_attr'] = $flex_type_attr;

		// Extract flex type value from responsive structure.
		if ( is_array( $flex_type_attr ) ) {
			// D5 format: flexType is directly a responsive structure.
			// Path is: flexType.desktop.value.
			$flex_type_value = $flex_type_attr['desktop']['value'] ?? null;

			// If desktop value is null but array exists, check preset for desktop value.
			// This handles the case where responsive overrides exist (tablet/phone) but desktop uses preset.
			if ( null === $flex_type_value ) {
				$flex_type_value = self::_get_attribute_from_layout_group_preset(
					$module_name,
					$module_attrs,
					$config['flex_type_attr_path'] . '.desktop.value'
				);
			}
		} elseif ( is_string( $flex_type_attr ) ) {
			// D4 format or simple string.
			$flex_type_value = $flex_type_attr;
		} else {
			// No flexType attribute set - check layout option group preset.
			$flex_type_value = self::_get_attribute_from_layout_group_preset(
				$module_name,
				$module_attrs,
				$config['flex_type_attr_path'] . '.desktop.value'
			);
		}

		// Extract layout display value (the current Layout Style setting).
		$layout_display_value       = null;
		$layout_display_from_module = false; // Track if display value is from module (not preset).

		if ( is_array( $layout_display_attr ) ) {
			// Check for D5 format: desktop.value.display.
			$layout_display_value = $layout_display_attr['desktop']['value']['display'] ?? null;
			if ( null !== $layout_display_value ) {
				$layout_display_from_module = true;
			}
		}

		// If no layout display value, check layout option group preset.
		if ( null === $layout_display_value ) {
			$layout_display_value = self::_get_attribute_from_layout_group_preset(
				$module_name,
				$module_attrs,
				$config['grid_attr_path'] . '.decoration.layout.desktop.value.display'
			);
			// layout_display_from_module remains false since we got it from preset.
		}

		$config['layout_value']               = $layout_value;
		$config['flex_type_value']            = $flex_type_value;
		$config['layout_display_value']       = $layout_display_value;
		$config['layout_display_from_module'] = $layout_display_from_module; // Track if from module vs preset.
		$config['layout_attr']                = $layout_attr; // Store original for preset checking.
		$config['has_layout_preset']          = $has_layout_preset; // Track if layout preset is applied.

		return $config;
	}



	/**
	 * Check if module has a layout option group preset applied.
	 *
	 * @since ??
	 *
	 * @param array $module_attrs The module attributes.
	 *
	 * @return bool True if layout preset is applied, false otherwise.
	 */
	private static function _has_layout_group_preset( array $module_attrs ): bool {
		if ( empty( $module_attrs['groupPreset'] ) ) {
			return false;
		}

		// Check if any of the group presets is a layout preset.
		foreach ( $module_attrs['groupPreset'] as $preset_key => $preset_info ) {
			// Check if this is a layout preset by examining groupName.
			if ( isset( $preset_info['groupName'] ) ) {
				$group_name = $preset_info['groupName'];
				// Layout presets have groupName 'divi/layout' or contain 'layout'.
				if ( 'divi/layout' === $group_name || str_contains( $group_name, 'layout' ) ) {
					return true;
				}
			}
		}

		return false;
	}



	/**
	 * Migrate column gap inheritance.
	 *
	 * For rows that have a custom columnGap value and Layout Style NOT set to 'block',
	 * propagate the columnGap value to child rows and specific grid modules that don't
	 * already have their own columnGap value.
	 *
	 * @since ??
	 *
	 * @param array $row_data      The row module data.
	 * @param array $flat_objects  All flat module objects.
	 *
	 * @return array Array of migrations keyed by module ID, or empty array if no migration needed.
	 */
	private static function _migrate_column_gap_inheritance( array $row_data, array $flat_objects ): array {
		// Check if the row has Layout Style set to 'block'.
		$layout_display = $row_data['props']['attrs']['module']['decoration']['layout']['desktop']['value']['display'] ?? null;

		// If layout is block, don't propagate gaps.
		if ( 'block' === $layout_display ) {
			return [];
		}

		// Get the row's columnGap values from both direct attributes and presets.
		$column_gaps = [];
		$layout_attr = $row_data['props']['attrs']['module']['decoration']['layout'] ?? [];

		// First, collect from direct attributes.
		foreach ( $layout_attr as $breakpoint => $value ) {
			$column_gap = $value['value']['columnGap'] ?? null;
			if ( null !== $column_gap && '' !== $column_gap ) {
				$column_gaps[ $breakpoint ] = $column_gap;
			}
		}

		// Then, check presets for any breakpoints not already set.
		$module_name  = $row_data['name'] ?? '';
		$module_attrs = $row_data['props']['attrs'] ?? [];

		// Only check presets if module has preset attributes.
		$has_module_preset = ! empty( $module_attrs['modulePreset'] );
		$has_group_preset  = ! empty( $module_attrs['groupPreset'] );

		if ( $has_module_preset || $has_group_preset ) {
			// Get all enabled breakpoint names dynamically.
			$enabled_breakpoints = Breakpoint::get_enabled_breakpoint_names();

			foreach ( $enabled_breakpoints as $breakpoint ) {
				// Skip if already set by direct attribute.
				if ( isset( $column_gaps[ $breakpoint ] ) ) {
					continue;
				}

				// Check if preset has a value for this breakpoint.
				$preset_column_gap = self::_get_column_gap_from_preset_for_breakpoint(
					$module_name,
					$module_attrs,
					'module.decoration.layout',
					$breakpoint
				);

				if ( null !== $preset_column_gap ) {
					$column_gaps[ $breakpoint ] = $preset_column_gap;
				}
			}
		}

		// If there are no custom columnGap values, no migration needed.
		if ( empty( $column_gaps ) ) {
			return [];
		}

		// Early return if row has no children.
		$children = $row_data['children'] ?? [];
		if ( empty( $children ) ) {
			return [];
		}

		// Recursively find all descendant modules that need columnGap inheritance.
		$migrations = [];
		foreach ( $children as $child_id ) {
			self::_collect_column_gap_inheritance_targets( $child_id, $column_gaps, $flat_objects, $migrations );
		}

		return $migrations;
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
	 * @param array $module_data The module data to migrate.
	 *
	 * @return array|null The migration data structure or null if no migration needed.
	 */
	private static function _migrate_column_flex_type( array $module_data ): ?array {
		// Check if the old flexType attribute exists.
		$old_flex_type = $module_data['props']['attrs']['module']['advanced']['flexType'] ?? null;

		// If no old flexType attribute exists, no migration needed.
		if ( ! $old_flex_type ) {
			return null;
		}

		$migration = [
			'props' => [
				'attrs' => [
					'builderVersion' => self::$_release_version,
					'module'         => [
						'advanced'   => [
							'flexType' => null, // Remove old flexType attribute.
						],
						'decoration' => [
							'sizing' => [],
						],
					],
				],
			],
		];

		// Migrate all breakpoints and states.
		// Old structure: module.advanced.flexType.{breakpoint}.{state} = "12_24".
		// Example: module.advanced.flexType.desktop.value = "12_24".
		// New structure: module.decoration.sizing.{breakpoint}.{state}.flexType = "12_24".
		// Example: module.decoration.sizing.desktop.value.flexType = "12_24".
		foreach ( $old_flex_type as $breakpoint => $breakpoint_data ) {
			if ( ! is_array( $breakpoint_data ) ) {
				continue;
			}

			foreach ( $breakpoint_data as $state => $flex_type_value ) {
				// $state is like 'value', 'hover', etc.
				// $flex_type_value is the actual flexType string like '12_24'.

				// Skip if the value is not a string (actual flexType value).
				if ( ! is_string( $flex_type_value ) ) {
					continue;
				}

				// Copy the flexType value to the new location.
				// Structure: sizing.{breakpoint}.{state}.flexType (where state is 'value', 'hover', etc.).
				$migration['props']['attrs']['module']['decoration']['sizing'][ $breakpoint ][ $state ]['flexType'] = $flex_type_value;
			}
		}

		// Check if we actually added any values.
		if ( empty( $migration['props']['attrs']['module']['decoration']['sizing'] ) ) {
			return null;
		}

		return $migration;
	}

	/**
	 * Migrate Contact Field fullwidth attribute to flexType.
	 *
	 * Migrates the deprecated fieldItem.advanced.fullwidth attribute to
	 * module.decoration.sizing.flexType.
	 *
	 * Migration logic:
	 * - If flexType already exists (from preset or other source): Preserve it and skip migration
	 * - If fullwidth attribute exists:
	 *   - If fullwidth is 'on': Set flexType to '24_24' (100% width)
	 *   - If fullwidth is 'off': Set flexType to '12_24' (50% width)
	 * - If fullwidth attribute doesn't exist AND flexType doesn't exist: Default to '12_24' (half-width) to match D4 default behavior
	 * - Remove the deprecated fullwidth attribute
	 *
	 * @since ??
	 *
	 * @param array $module_data The module data to migrate.
	 *
	 * @return array|null The migration data structure or null if no migration needed.
	 */
	private static function _migrate_contact_field_fullwidth( array $module_data ): ?array {
		// Get the fullwidth attribute.
		$fullwidth_attr = $module_data['props']['attrs']['fieldItem']['advanced']['fullwidth'] ?? null;

		// Check if flexType already exists (from preset or other source).
		// If it exists, preserve it and skip migration to avoid overriding intentional configurations.
		$existing_flex_type = $module_data['props']['attrs']['module']['decoration']['sizing']['desktop']['value']['flexType'] ?? null;
		if ( null !== $existing_flex_type ) {
			return null;
		}

		$migration = [
			'props' => [
				'attrs' => [
					'builderVersion' => self::$_release_version,
				],
			],
		];

		// Check desktop breakpoint value.
		$desktop_value = null;
		if ( is_array( $fullwidth_attr ) && isset( $fullwidth_attr['desktop']['value'] ) ) {
			$desktop_value = $fullwidth_attr['desktop']['value'];
		} elseif ( is_string( $fullwidth_attr ) ) {
			// D4 format.
			$desktop_value = $fullwidth_attr;
		}

		// Set flexType based on fullwidth value.
		if ( 'on' === $desktop_value ) {
			// Fullwidth is explicitly 'on' - set flexType to 24_24 (100% width).
			$migration['props']['attrs']['module']['decoration']['sizing']['desktop']['value']['flexType'] = '24_24';
		} elseif ( null !== $desktop_value ) {
			// Fullwidth is explicitly 'off' or any other value - set flexType to 12_24 (50% width).
			$migration['props']['attrs']['module']['decoration']['sizing']['desktop']['value']['flexType'] = '12_24';
		} else {
			// Fullwidth attribute doesn't exist - default to 12_24 (half-width) to match D4 default behavior.
			// D4 default is fullwidth_field="off" (half-width), so missing attribute should default to half-width.
			$migration['props']['attrs']['module']['decoration']['sizing']['desktop']['value']['flexType'] = '12_24';
		}

		// Clear the deprecated fullwidth attribute by setting it to null.
		// This will remove it from the attributes during serialization.
		$migration['props']['attrs']['fieldItem']['advanced']['fullwidth'] = null;

		return $migration;
	}

	/**
	 * Recursively collect modules that should inherit columnGap from parent row.
	 *
	 * @since ??
	 *
	 * @param string $module_id     The module ID to check.
	 * @param array  $column_gaps   The columnGap values from parent row (keyed by breakpoint).
	 * @param array  $flat_objects  All flat module objects.
	 * @param array  $migrations    Array to collect migrations (passed by reference).
	 *
	 * @return void
	 */
	private static function _collect_column_gap_inheritance_targets( string $module_id, array $column_gaps, array $flat_objects, array &$migrations ): void {
		if ( ! isset( $flat_objects[ $module_id ] ) ) {
			return;
		}

		$module_data = $flat_objects[ $module_id ];
		$module_name = $module_data['name'] ?? '';

		// Define grid modules once.
		$grid_modules = [ 'divi/portfolio', 'divi/filterable-portfolio', 'divi/blog', 'divi/gallery', 'divi/fullwidth-portfolio' ];
		$is_row       = 'divi/row' === $module_name || 'divi/row-inner' === $module_name;
		$is_grid      = in_array( $module_name, $grid_modules, true );

		// Check if this is a target module (row or grid module).
		if ( $is_row || $is_grid ) {
			// Check if this module already has its own columnGap values.
			$has_own_column_gap = false;
			$layout_attr        = $module_data['props']['attrs']['module']['decoration']['layout'] ?? [];

			foreach ( $layout_attr as $breakpoint => $value ) {
				if ( isset( $value['value']['columnGap'] ) ) {
					$has_own_column_gap = true;
					break;
				}
			}

			// For grid modules, also check the grid-specific layout path.
			if ( ! $has_own_column_gap && $is_grid ) {
				$grid_attr_path = self::_get_grid_attr_path_for_column_gap( $module_name );
				$grid_layout    = $module_data['props']['attrs'][ $grid_attr_path ]['decoration']['layout'] ?? [];

				foreach ( $grid_layout as $breakpoint => $value ) {
					if ( isset( $value['value']['columnGap'] ) ) {
						$has_own_column_gap = true;
						break;
					}
				}
			}

			// Only apply migration if module doesn't have its own columnGap.
			if ( ! $has_own_column_gap ) {
				// Determine which breakpoint gaps to inherit based on preset values.
				// We need to check each breakpoint individually - a preset might have columnGap
				// for some breakpoints but not others.
				$attr_path       = $is_grid ? self::_get_grid_attr_path_for_column_gap( $module_name ) . '.decoration.layout' : 'module.decoration.layout';
				$gaps_to_inherit = [];

				foreach ( $column_gaps as $breakpoint => $gap_value ) {
					// Check if this specific breakpoint has columnGap in preset.
					if ( ! self::_has_column_gap_in_preset_for_breakpoint( $module_name, $module_data['props']['attrs'] ?? [], $attr_path, $breakpoint ) ) {
						// No preset value for this breakpoint, so inherit parent's value.
						$gaps_to_inherit[ $breakpoint ] = $gap_value;
					}
				}

				// Only create migration if we have gaps to inherit.
				if ( ! empty( $gaps_to_inherit ) ) {
					// For grid modules, apply columnGap to the grid-specific layout path.
					if ( $is_grid ) {
						$grid_attr_path = self::_get_grid_attr_path_for_column_gap( $module_name );
						$migration_data = [ 'props' => [ 'attrs' => [ $grid_attr_path => [ 'decoration' => [ 'layout' => [] ] ] ] ] ];

						foreach ( $gaps_to_inherit as $breakpoint => $gap_value ) {
							$migration_data['props']['attrs'][ $grid_attr_path ]['decoration']['layout'][ $breakpoint ]['value']['columnGap'] = $gap_value;
						}
					} else {
						// For rows, apply to module.decoration.layout path.
						$migration_data = [ 'props' => [ 'attrs' => [ 'module' => [ 'decoration' => [ 'layout' => [] ] ] ] ] ];

						foreach ( $gaps_to_inherit as $breakpoint => $gap_value ) {
							$migration_data['props']['attrs']['module']['decoration']['layout'][ $breakpoint ]['value']['columnGap'] = $gap_value;
						}
					}

					$migrations[ $module_id ] = $migration_data;
				}
			}
		}

		// For container modules (rows, columns, and groups), recursively check their children.
		// Groups can contain rows, so we must recurse into them to propagate columnGap.
		$is_container = $is_row
			|| 'divi/column' === $module_name
			|| 'divi/column-inner' === $module_name
			|| 'divi/group' === $module_name;

		if ( $is_container ) {
			$children = $module_data['children'] ?? [];
			foreach ( $children as $child_id ) {
				self::_collect_column_gap_inheritance_targets( $child_id, $column_gaps, $flat_objects, $migrations );
			}
		}
	}

	/**
	 * Get the grid attribute path for columnGap migration.
	 *
	 * @since ??
	 *
	 * @param string $module_name The module name.
	 *
	 * @return string|null The attribute path or null if not a grid module.
	 */
	private static function _get_grid_attr_path_for_column_gap( string $module_name ): ?string {
		switch ( $module_name ) {
			case 'divi/portfolio':
			case 'divi/filterable-portfolio':
				return 'portfolioGrid';

			case 'divi/fullwidth-portfolio':
				return 'module';

			case 'divi/blog':
				return 'blogGrid';

			case 'divi/gallery':
				return 'galleryGrid';

			default:
				return null;
		}
	}

	/**
	 * Get attribute value from layout option group preset.
	 *
	 * This method retrieves an attribute value from the layout option group preset,
	 * if one is applied to the module.
	 *
	 * @since ??
	 *
	 * @param string $module_name  The module name.
	 * @param array  $module_attrs The module attributes.
	 * @param string $attr_path    The full attribute path (e.g., 'portfolioGrid.advanced.flexType.desktop.value').
	 *
	 * @return mixed The attribute value from preset, or null if not found.
	 */
	private static function _get_attribute_from_layout_group_preset( string $module_name, array $module_attrs, string $attr_path ) {
		// Early return if no group presets are defined.
		$has_group_preset = ! empty( $module_attrs['groupPreset'] );

		if ( ! $has_group_preset ) {
			return null;
		}

		$group_presets = $module_attrs['groupPreset'];

		// Group preset keys are the groupId (e.g., 'designLayout', 'designSizing').
		// The groupName for layout presets is typically 'divi/layout'.
		// We need to find the layout-related preset by matching the groupName.
		$layout_group_preset = null;
		$preset_id           = '';
		$group_name          = '';
		$preset_key          = '';

		foreach ( $group_presets as $key => $preset_config ) {
			$preset_group_name = $preset_config['groupName'] ?? '';

			// Check if this is a layout preset by checking the groupName.
			// Layout presets have groupName like 'divi/layout' or contain 'layout' in their groupName.
			if ( 'divi/layout' === $preset_group_name || str_contains( $preset_group_name, 'layout' ) ) {
				$layout_group_preset = $preset_config;
				$preset_id           = $preset_config['presetId'] ?? '';
				$group_name          = $preset_group_name;
				$preset_key          = $key;
				break;
			}
		}

		if ( ! $layout_group_preset ) {
			return null;
		}

		if ( empty( $preset_id ) || 'default' === $preset_id || '_initial' === $preset_id || empty( $group_name ) ) {
			return null;
		}

		try {
			// Get the raw preset data directly from the database using the preset ID.
			// This gives us the full unfiltered preset data including all attrs, renderAttrs, and styleAttrs.
			$raw_preset_data = GlobalPreset::find_preset_data_by_id( $preset_id );

			if ( ! $raw_preset_data ) {
				return null;
			}

			// The raw preset data structure has attrs, renderAttrs, and styleAttrs as top-level keys.
			// We need to merge them to search for the attribute path.
			$preset_attrs        = $raw_preset_data['attrs'] ?? [];
			$preset_render_attrs = $raw_preset_data['renderAttrs'] ?? [];
			$preset_style_attrs  = $raw_preset_data['styleAttrs'] ?? [];

			// Merge all attribute types. Priority: styleAttrs (lowest) -> renderAttrs -> attrs (highest).
			// This ensures that attrs takes precedence over renderAttrs, which takes precedence over styleAttrs.
			$merged_attrs = array_replace_recursive( $preset_style_attrs, $preset_render_attrs, $preset_attrs );

			// The preset might have been created for a different module (e.g., a blog preset applied to gallery).
			// We need to map the attribute prefix from the current module to the preset's module.
			// For example: galleryGrid -> blogGrid, portfolioGrid -> blogGrid, etc.
			$preset_module_name = $raw_preset_data['moduleName'] ?? '';

			// Define grid attribute prefix mappings between modules.
			$grid_prefix_map = [
				'divi/blog'                 => 'blogGrid',
				'divi/gallery'              => 'galleryGrid',
				'divi/portfolio'            => 'portfolioGrid',
				'divi/filterable-portfolio' => 'portfolioGrid',
				'divi/fullwidth-portfolio'  => 'portfolioGrid',
			];

			// Get the current module's grid prefix and the preset module's grid prefix.
			$current_grid_prefix = $grid_prefix_map[ $module_name ] ?? null;
			$preset_grid_prefix  = $grid_prefix_map[ $preset_module_name ] ?? null;

			// If the attr_path starts with the current module's grid prefix but the preset uses a different prefix,
			// we need to translate the path.
			$search_paths = [ $attr_path ];
			if ( $current_grid_prefix && $preset_grid_prefix && $current_grid_prefix !== $preset_grid_prefix ) {
				// Check if the attr_path starts with the current module's grid prefix.
				if ( str_starts_with( $attr_path, $current_grid_prefix . '.' ) ) {
					// Replace the current grid prefix with the preset's grid prefix.
					$translated_path = $preset_grid_prefix . substr( $attr_path, strlen( $current_grid_prefix ) );
					$search_paths[]  = $translated_path;
				}
			}

			// Also handle fullwidth -> module translation for gallery.
			if ( str_starts_with( $attr_path, 'module.advanced.fullwidth' ) ) {
				$search_paths[] = str_replace( 'module.advanced.fullwidth', 'fullwidth.advanced.enable', $attr_path );
			}

			// Try each search path.
			foreach ( $search_paths as $search_path ) {
				$current    = $merged_attrs;
				$path_parts = explode( '.', $search_path );
				$found      = true;

				foreach ( $path_parts as $part ) {
					if ( ! is_array( $current ) || ! isset( $current[ $part ] ) ) {
						$found = false;
						break;
					}
					$current = $current[ $part ];
				}

				if ( $found ) {
					// Return the value if found and not empty.
					if ( ( is_string( $current ) && '' !== $current ) || is_numeric( $current ) ) {
						return $current;
					}
				}
			}
		} catch ( \Exception $e ) {
			return null;
		}

		return null;
	}

	/**
	 * Get columnGap value from preset for a specific breakpoint.
	 *
	 * This method retrieves the columnGap value for a specific breakpoint from:
	 * 1. Module preset (if one is selected)
	 * 2. Layout option group preset (if one is selected)
	 *
	 * @since ??
	 *
	 * @param string $module_name  The module name.
	 * @param array  $module_attrs The module attributes.
	 * @param string $layout_path  The layout attribute path (e.g., 'module.decoration.layout' or 'portfolioGrid.decoration.layout').
	 * @param string $breakpoint   The breakpoint to check (e.g., 'desktop', 'tablet', 'phone').
	 *
	 * @return string|null The columnGap value from preset, or null if not found.
	 */
	private static function _get_column_gap_from_preset_for_breakpoint( string $module_name, array $module_attrs, string $layout_path, string $breakpoint ): ?string {
		// Early return if no presets are defined.
		$has_module_preset = ! empty( $module_attrs['modulePreset'] );
		$has_group_preset  = ! empty( $module_attrs['groupPreset'] );

		if ( ! $has_module_preset && ! $has_group_preset ) {
			return null;
		}

		// First check module preset for columnGap at this breakpoint.
		$column_gap_path = $layout_path . '.' . $breakpoint . '.value.columnGap';
		if ( $has_module_preset && GlobalPreset::preset_has_attribute(
			[
				'moduleName'    => $module_name,
				'moduleAttrs'   => $module_attrs,
				'attributePath' => $column_gap_path,
			]
		) ) {
			// Get the actual value from the module preset.
			try {
				$preset_value = GlobalPreset::get_selected_preset(
					[
						'moduleName'  => $module_name,
						'moduleAttrs' => $module_attrs,
					]
				);
				if ( $preset_value && ! $preset_value->as_default() ) {
					$preset_attrs = $preset_value->get_data_attrs();
					$current      = $preset_attrs;
					$path_parts   = explode( '.', $column_gap_path );

					foreach ( $path_parts as $part ) {
						if ( ! is_array( $current ) || ! isset( $current[ $part ] ) ) {
							break;
						}
						$current = $current[ $part ];
					}

					if ( is_string( $current ) && '' !== $current ) {
						return $current;
					}
				}
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Intentionally continue to group preset check on error.
				// Continue to check group preset.
			}
		}

		// Check layout option group preset for columnGap (only if group preset exists).
		if ( ! $has_group_preset ) {
			return null;
		}

		$group_presets = $module_attrs['groupPreset'];

		// Group presets can be stored with different keys (e.g., 'designLayout', 'module.decoration.layout').
		// We need to find a layout-related preset by checking the groupName.
		$layout_group_preset = null;
		$preset_id           = '';
		$group_name          = '';

		foreach ( $group_presets as $preset_key => $preset_config ) {
			$preset_group_name = $preset_config['groupName'] ?? '';
			// Check if this is a layout preset (groupName contains 'layout').
			if ( str_contains( $preset_group_name, 'layout' ) ) {
				$layout_group_preset = $preset_config;
				$preset_id           = $preset_config['presetId'] ?? '';
				$group_name          = $preset_group_name;
				break;
			}
		}

		if ( ! $layout_group_preset ) {
			return null;
		}

		if ( empty( $preset_id ) || 'default' === $preset_id || '_initial' === $preset_id || empty( $group_name ) ) {
			return null;
		}

		try {
			$selected_group_presets = GlobalPreset::get_selected_group_presets(
				[
					'moduleName'  => $module_name,
					'moduleAttrs' => $module_attrs,
				]
			);

			foreach ( $selected_group_presets as $group_preset ) {
				$preset_group_name = $group_preset->get_data_group_name();

				if ( $group_name !== $preset_group_name ) {
					continue;
				}

				if ( ! $group_preset->is_exist() || $group_preset->as_default() ) {
					continue;
				}

				$preset_attrs = $group_preset->get_data_attrs();
				$current      = $preset_attrs;
				$path_parts   = explode( '.', $layout_path . '.' . $breakpoint . '.value.columnGap' );

				foreach ( $path_parts as $part ) {
					if ( ! is_array( $current ) || ! isset( $current[ $part ] ) ) {
						return null;
					}
					$current = $current[ $part ];
				}

				if ( is_string( $current ) && '' !== $current ) {
					return $current;
				}
			}
		} catch ( \Exception $e ) {
			return null;
		}

		return null;
	}

	/**
	 * Check if module has columnGap for a specific breakpoint in its module preset or layout option group preset.
	 *
	 * This method checks whether a module has a columnGap value set for a specific breakpoint in:
	 * 1. Its module preset (if one is selected)
	 * 2. Its layout option group preset (if one is selected)
	 *
	 * @since ??
	 *
	 * @param string $module_name  The module name.
	 * @param array  $module_attrs The module attributes.
	 * @param string $layout_path  The layout attribute path (e.g., 'module.decoration.layout' or 'portfolioGrid.decoration.layout').
	 * @param string $breakpoint   The breakpoint to check (e.g., 'desktop', 'tablet', 'phone').
	 *
	 * @return bool True if the module has columnGap for this breakpoint in any preset, false otherwise.
	 */
	private static function _has_column_gap_in_preset_for_breakpoint( string $module_name, array $module_attrs, string $layout_path, string $breakpoint ): bool {
		return null !== self::_get_column_gap_from_preset_for_breakpoint( $module_name, $module_attrs, $layout_path, $breakpoint );
	}


	/**
	 * Migrate content from shortcode format.
	 *
	 * This method handles the migration of nested module content in shortcode-based format.
	 * Currently returns the content unchanged as nested modules are only migrated in block format.
	 *
	 * @since ??
	 *
	 * @param string $content The shortcode content to migrate.
	 *
	 * @return string The migrated content (currently unchanged).
	 */
	public static function migrate_content_shortcode( string $content ): string {
		// Nested module migrations only apply to block-based content.
		// D4 shortcodes are converted to D5 blocks before migrations run.
		return $content;
	}

	/**
	 * Migrate content from block format.
	 *
	 * This method handles the migration of nested module content in block-based format.
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

		// Combine all module arrays for the version check.
		$all_modules = array_merge(
			self::$_block_modules,
			self::$_grid_modules,
			self::$_column_like_modules,
			self::$_signup_modules,
			self::$_team_member_modules,
			self::$_contact_field_modules
		);

		if ( ! MigrationUtils::content_needs_migration(
			$content,
			self::$_release_version,
			$all_modules
		) ) {
			return $content;
		}

		return self::_migrate_block_content( $content );
	}
}
