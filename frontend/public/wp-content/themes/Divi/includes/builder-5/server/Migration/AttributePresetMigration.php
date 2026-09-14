<?php
/**
 * Attribute Preset Migration
 *
 * Handles the migration of deprecated attributes in presets to the new custom attributes system.
 * This includes:
 * - CSS ID and CSS Class from htmlAttributes
 * - Blurb module image alt text from imageIcon.innerContent.alt
 * - Slide module image alt text from image.innerContent.alt
 * - Button rel attributes from button.innerContent.rel
 * - And other module-specific attribute migrations
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

/**
 * Attribute Preset Migration Class.
 *
 * @since ??
 */
class AttributePresetMigration extends MigrationPresetsBase {

	/**
	 * The migration name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_name = 'attribute-preset.v1';

	/**
	 * The attribute preset migration release version string.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_release_version = '5.0.0-public-alpha.23';

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
		if ( ! (
			Conditions::is_vb_enabled() ||
			Conditions::is_vb_app_window() ||
			Conditions::is_rest_api_request()
		) ) {
			return;
		}

		self::migrate_presets();
	}

	/**
	 * Migrate presets that need attribute updates.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function migrate_presets(): void {
		$presets_data = GlobalPreset::get_data();

		if ( empty( $presets_data ) || ! isset( $presets_data['module'] ) ) {
			return;
		}

		// Performance optimization: Check if any presets need migration before processing.
		if ( ! self::_has_presets_needing_migration( $presets_data ) ) {
			return;
		}

		$changes_made    = false;
		$updated_presets = $presets_data;

		// Process each module type's presets.
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

		// Quick scan through all presets to see if any need migration.
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
	 * Migrate preset attributes for deprecated patterns.
	 *
	 * @since ??
	 *
	 * @param array  $attrs The preset attributes to migrate.
	 * @param string $module_name The module name for context.
	 *
	 * @return array The migrated attributes.
	 */
	private static function _migrate_preset_attributes( array $attrs, string $module_name ): array {
		$migrated_attrs = $attrs;

		// Migrate CSS ID and CSS Class from htmlAttributes.
		$migrated_attrs = self::_migrate_html_attributes( $migrated_attrs );

		// Migrate module-specific attributes based on module type.
		switch ( $module_name ) {
			case 'divi/blurb':
				$migrated_attrs = self::_migrate_blurb_attributes( $migrated_attrs );
				break;

			case 'divi/menu':
			case 'divi/fullwidth-menu':
				$migrated_attrs = self::_migrate_menu_attributes( $migrated_attrs );
				break;

			case 'divi/icon':
				$migrated_attrs = self::_migrate_icon_attributes( $migrated_attrs );
				break;

			case 'divi/fullwidth-header':
				$migrated_attrs = self::_migrate_fullwidth_header_attributes( $migrated_attrs );
				break;

			case 'divi/slide':
				$migrated_attrs = self::_migrate_slide_attributes( $migrated_attrs );
				break;

			case 'divi/image':
			case 'divi/fullwidth-image':
				$migrated_attrs = self::_migrate_image_attributes( $migrated_attrs );
				break;

			case 'divi/section':
				$migrated_attrs = self::_migrate_section_attributes( $migrated_attrs );
				break;

			default:
				// Check for generic button rel attributes in any module.
				$migrated_attrs = self::_migrate_button_rel_attributes( $migrated_attrs, $module_name );
				break;
		}

		return $migrated_attrs;
	}

	/**
	 * Migrate CSS ID and CSS Class from htmlAttributes.
	 *
	 * @since ??
	 *
	 * @param array $attrs The attributes to migrate.
	 *
	 * @return array The migrated attributes.
	 */
	private static function _migrate_html_attributes( array $attrs ): array {
		$html_attributes = $attrs['module']['advanced']['htmlAttributes']['desktop']['value'] ?? [];
		$css_id          = $html_attributes['id'] ?? '';
		$css_class       = $html_attributes['class'] ?? '';

		if ( empty( $css_id ) && empty( $css_class ) ) {
			return $attrs;
		}

		// Prepare new custom attributes array.
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

		// Get existing custom attributes if any.
		$existing_attributes = $attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [];
		if ( is_array( $existing_attributes ) ) {
			$new_attributes = array_merge( $existing_attributes, $new_attributes );
		}

		// Update the attributes with migrated custom attributes.
		$attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] = $new_attributes;

		// Clear the old htmlAttributes.
		unset( $attrs['module']['advanced']['htmlAttributes']['desktop']['value']['id'] );
		unset( $attrs['module']['advanced']['htmlAttributes']['desktop']['value']['class'] );

		return $attrs;
	}

	/**
	 * Migrate Blurb module image alt text.
	 *
	 * @since ??
	 *
	 * @param array $attrs The attributes to migrate.
	 *
	 * @return array The migrated attributes.
	 */
	private static function _migrate_blurb_attributes( array $attrs ): array {
		$image_alt = $attrs['imageIcon']['innerContent']['desktop']['value']['alt'] ?? '';

		if ( empty( $image_alt ) ) {
			return $attrs;
		}

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
		$existing_attributes = $attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [];
		if ( is_array( $existing_attributes ) ) {
			$blurb_attributes = array_merge( $existing_attributes, $blurb_attributes );
		}

		// Update the attributes with migrated Blurb image alt attributes.
		$attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] = $blurb_attributes;

		// Clear the old alt value.
		unset( $attrs['imageIcon']['innerContent']['desktop']['value']['alt'] );

		return $attrs;
	}

	/**
	 * Migrate Menu module logo alt text.
	 *
	 * @since ??
	 *
	 * @param array $attrs The attributes to migrate.
	 *
	 * @return array The migrated attributes.
	 */
	private static function _migrate_menu_attributes( array $attrs ): array {
		$logo_alt = $attrs['logo']['innerContent']['desktop']['value']['alt'] ?? '';

		if ( empty( $logo_alt ) ) {
			return $attrs;
		}

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
		$existing_attributes = $attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [];
		if ( is_array( $existing_attributes ) ) {
			$logo_attributes = array_merge( $existing_attributes, $logo_attributes );
		}

		// Update the attributes with migrated Menu logo alt attributes.
		$attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] = $logo_attributes;

		// Clear the old alt value.
		unset( $attrs['logo']['innerContent']['desktop']['value']['alt'] );

		return $attrs;
	}

	/**
	 * Migrate Icon module title attribute.
	 *
	 * @since ??
	 *
	 * @param array $attrs The attributes to migrate.
	 *
	 * @return array The migrated attributes.
	 */
	private static function _migrate_icon_attributes( array $attrs ): array {
		$icon_title = $attrs['icon']['innerContent']['desktop']['value']['title'] ?? '';

		if ( empty( $icon_title ) ) {
			return $attrs;
		}

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
		$existing_attributes = $attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [];
		if ( is_array( $existing_attributes ) ) {
			$icon_attributes = array_merge( $existing_attributes, $icon_attributes );
		}

		// Update the attributes with migrated Icon title attributes.
		$attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] = $icon_attributes;

		// Clear the old title value.
		unset( $attrs['icon']['innerContent']['desktop']['value']['title'] );

		return $attrs;
	}

	/**
	 * Migrate Fullwidth Header module attributes.
	 *
	 * @since ??
	 *
	 * @param array $attrs The attributes to migrate.
	 *
	 * @return array The migrated attributes.
	 */
	private static function _migrate_fullwidth_header_attributes( array $attrs ): array {
		$header_alt   = $attrs['image']['innerContent']['desktop']['value']['alt'] ?? '';
		$header_title = $attrs['image']['innerContent']['desktop']['value']['title'] ?? '';
		$logo_alt     = $attrs['logo']['innerContent']['desktop']['value']['alt'] ?? '';
		$logo_title   = $attrs['logo']['innerContent']['desktop']['value']['title'] ?? '';

		$fullwidth_header_attributes = [];

		// Handle header image alt attribute migration.
		if ( ! empty( $header_alt ) ) {
			$fullwidth_header_attributes[] = [
				'id'            => \ET_Core_Data_Utils::uuid_v4(),
				'name'          => 'alt',
				'value'         => $header_alt,
				'adminLabel'    => 'Image Alt',
				'targetElement' => 'image',
			];

			// Clear the old header image alt value.
			unset( $attrs['image']['innerContent']['desktop']['value']['alt'] );
		}

		// Handle header image title attribute migration.
		if ( ! empty( $header_title ) ) {
			$fullwidth_header_attributes[] = [
				'id'            => \ET_Core_Data_Utils::uuid_v4(),
				'name'          => 'title',
				'value'         => $header_title,
				'adminLabel'    => 'Image Title',
				'targetElement' => 'image',
			];

			// Clear the old header image title value.
			unset( $attrs['image']['innerContent']['desktop']['value']['title'] );
		}

		// Handle logo image alt attribute migration.
		if ( ! empty( $logo_alt ) ) {
			$fullwidth_header_attributes[] = [
				'id'            => \ET_Core_Data_Utils::uuid_v4(),
				'name'          => 'alt',
				'value'         => $logo_alt,
				'adminLabel'    => 'Logo Alt',
				'targetElement' => 'logo',
			];

			// Clear the old logo alt value.
			unset( $attrs['logo']['innerContent']['desktop']['value']['alt'] );
		}

		// Handle logo image title attribute migration.
		if ( ! empty( $logo_title ) ) {
			$fullwidth_header_attributes[] = [
				'id'            => \ET_Core_Data_Utils::uuid_v4(),
				'name'          => 'title',
				'value'         => $logo_title,
				'adminLabel'    => 'Logo Title',
				'targetElement' => 'logo',
			];

			// Clear the old logo title value.
			unset( $attrs['logo']['innerContent']['desktop']['value']['title'] );
		}

		// Handle Button One rel migration.
		$button_one_rel = $attrs['buttonOne']['innerContent']['desktop']['value']['rel'] ?? [];
		if ( ! empty( $button_one_rel ) && is_array( $button_one_rel ) ) {
			$rel_value = implode( ' ', $button_one_rel );

			$fullwidth_header_attributes[] = [
				'id'            => \ET_Core_Data_Utils::uuid_v4(),
				'name'          => 'rel',
				'value'         => $rel_value,
				'adminLabel'    => 'Button One Rel',
				'targetElement' => 'button1',
			];

			// Clear old buttonOne rel value.
			unset( $attrs['buttonOne']['innerContent']['desktop']['value']['rel'] );
		}

		// Handle Button Two rel migration.
		$button_two_rel = $attrs['buttonTwo']['innerContent']['desktop']['value']['rel'] ?? [];
		if ( ! empty( $button_two_rel ) && is_array( $button_two_rel ) ) {
			$rel_value = implode( ' ', $button_two_rel );

			$fullwidth_header_attributes[] = [
				'id'            => \ET_Core_Data_Utils::uuid_v4(),
				'name'          => 'rel',
				'value'         => $rel_value,
				'adminLabel'    => 'Button Two Rel',
				'targetElement' => 'button2',
			];

			// Clear old buttonTwo rel value.
			unset( $attrs['buttonTwo']['innerContent']['desktop']['value']['rel'] );
		}

		// Apply migration if any attributes were found.
		if ( ! empty( $fullwidth_header_attributes ) ) {
			// Get existing custom attributes if any.
			$existing_attributes = $attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [];
			if ( is_array( $existing_attributes ) ) {
				$fullwidth_header_attributes = array_merge( $existing_attributes, $fullwidth_header_attributes );
			}

			// Update the attributes with migrated Fullwidth Header attributes.
			$attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] = $fullwidth_header_attributes;
		}

		return $attrs;
	}

	/**
	 * Migrate Slide module image alt text.
	 *
	 * @since ??
	 *
	 * @param array $attrs The attributes to migrate.
	 *
	 * @return array The migrated attributes.
	 */
	private static function _migrate_slide_attributes( array $attrs ): array {
		$image_alt = $attrs['image']['innerContent']['desktop']['value']['alt'] ?? '';

		if ( empty( $image_alt ) ) {
			return $attrs;
		}

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
		$existing_attributes = $attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [];
		if ( is_array( $existing_attributes ) ) {
			$slide_attributes = array_merge( $existing_attributes, $slide_attributes );
		}

		// Update the attributes with migrated Slide image alt attributes.
		$attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] = $slide_attributes;

		// Clear the old alt value.
		unset( $attrs['image']['innerContent']['desktop']['value']['alt'] );

		return $attrs;
	}

	/**
	 * Migrate Image module attributes.
	 *
	 * @since ??
	 *
	 * @param array $attrs The attributes to migrate.
	 *
	 * @return array The migrated attributes.
	 */
	private static function _migrate_image_attributes( array $attrs ): array {
		$image_alt   = $attrs['image']['innerContent']['desktop']['value']['alt'] ?? '';
		$image_title = $attrs['image']['innerContent']['desktop']['value']['titleText'] ?? '';

		$image_attributes = [];

		// Handle alt attribute migration.
		if ( ! empty( $image_alt ) ) {
			$image_attributes[] = [
				'id'            => \ET_Core_Data_Utils::uuid_v4(),
				'name'          => 'alt',
				'value'         => $image_alt,
				'adminLabel'    => 'Image Alt',
				'targetElement' => 'image',
			];

			// Clear the old alt value.
			unset( $attrs['image']['innerContent']['desktop']['value']['alt'] );
		}

		// Handle title attribute migration.
		if ( ! empty( $image_title ) ) {
			$image_attributes[] = [
				'id'            => \ET_Core_Data_Utils::uuid_v4(),
				'name'          => 'title',
				'value'         => $image_title,
				'adminLabel'    => 'Image Title',
				'targetElement' => 'image',
			];

			// Clear the old titleText value.
			unset( $attrs['image']['innerContent']['desktop']['value']['titleText'] );
		}

		// Apply the migration if we have attributes to migrate.
		if ( ! empty( $image_attributes ) ) {
			// Get existing custom attributes if any.
			$existing_attributes = $attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [];
			if ( is_array( $existing_attributes ) ) {
				$image_attributes = array_merge( $existing_attributes, $image_attributes );
			}

			// Update the attributes with migrated image attributes.
			$attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] = $image_attributes;
		}

		return $attrs;
	}

	/**
	 * Migrate Section module column-specific CSS ID and class attributes.
	 *
	 * @since ??
	 *
	 * @param array $attrs The attributes to migrate.
	 *
	 * @return array The migrated attributes.
	 */
	private static function _migrate_section_attributes( array $attrs ): array {
		$section_attributes = [];

		// Define the paths to check for htmlAttributes and their corresponding target elements.
		$html_attr_paths = [
			'column1.advanced.htmlAttributes' => 'column_1_main',
			'column2.advanced.htmlAttributes' => 'column_2_main',
			'column3.advanced.htmlAttributes' => 'column_3_main',
		];

		foreach ( $html_attr_paths as $attr_path => $target_element ) {
			// Navigate to the htmlAttributes data using the path.
			$path_parts   = explode( '.', $attr_path );
			$current_data = $attrs;

			foreach ( $path_parts as $part ) {
				$current_data = $current_data[ $part ] ?? [];
			}

			$html_attrs = $current_data['desktop']['value'] ?? [];
			$css_id     = $html_attrs['id'] ?? '';
			$css_class  = $html_attrs['class'] ?? '';

			// Migrate CSS ID if present.
			if ( ! empty( $css_id ) ) {
				$section_attributes[] = [
					'id'            => \ET_Core_Data_Utils::uuid_v4(),
					'name'          => 'id',
					'value'         => $css_id,
					'adminLabel'    => str_replace( [ '_main', '_' ], [ '', ' ' ], ucfirst( $target_element ) ) . ' ID',
					'targetElement' => $target_element,
				];
			}

			// Migrate CSS Class if present.
			if ( ! empty( $css_class ) ) {
				$section_attributes[] = [
					'id'            => \ET_Core_Data_Utils::uuid_v4(),
					'name'          => 'class',
					'value'         => $css_class,
					'adminLabel'    => str_replace( [ '_main', '_' ], [ '', ' ' ], ucfirst( $target_element ) ) . ' Class',
					'targetElement' => $target_element,
				];
			}

			// Clear the old htmlAttributes if we found any attributes.
			if ( ! empty( $css_id ) || ! empty( $css_class ) ) {
				// Clear the specific htmlAttributes path based on which one we're processing.
				switch ( $attr_path ) {
					case 'column1.advanced.htmlAttributes':
						unset( $attrs['column1']['advanced']['htmlAttributes'] );
						break;
					case 'column2.advanced.htmlAttributes':
						unset( $attrs['column2']['advanced']['htmlAttributes'] );
						break;
					case 'column3.advanced.htmlAttributes':
						unset( $attrs['column3']['advanced']['htmlAttributes'] );
						break;
				}
			}
		}

		// Apply the migration if we have attributes to migrate.
		if ( ! empty( $section_attributes ) ) {
			// Get existing custom attributes if any.
			$existing_attributes = $attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [];
			if ( is_array( $existing_attributes ) ) {
				$section_attributes = array_merge( $existing_attributes, $section_attributes );
			}

			// Update the attributes with migrated section attributes.
			$attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] = $section_attributes;
		}

		return $attrs;
	}

	/**
	 * Migrate button rel attributes for any module.
	 *
	 * @since ??
	 *
	 * @param array  $attrs The attributes to migrate.
	 * @param string $module_name The module name for context.
	 *
	 * @return array The migrated attributes.
	 */
	private static function _migrate_button_rel_attributes( array $attrs, string $module_name ): array {
		// Check if this module has button rel attributes to migrate.
		$button_rel = $attrs['button']['innerContent']['desktop']['value']['rel'] ?? [];

		// Also check for slider-style button path (children.button.innerContent).
		if ( empty( $button_rel ) ) {
			$button_rel = $attrs['children']['button']['innerContent']['desktop']['value']['rel'] ?? [];
		}

		if ( ! empty( $button_rel ) && is_array( $button_rel ) ) {
			// Convert rel array to space-separated string.
			$rel_value = implode( ' ', $button_rel );

			// For the button module itself, target the main module element.
			// For other modules, target the 'button' sub-element.
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
			$existing_attributes = $attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] ?? [];
			if ( is_array( $existing_attributes ) ) {
				$button_rel_attributes = array_merge( $existing_attributes, $button_rel_attributes );
			}

			// Clear the old button rel value first (check both possible paths).
			if ( isset( $attrs['button']['innerContent']['desktop']['value']['rel'] ) ) {
				unset( $attrs['button']['innerContent']['desktop']['value']['rel'] );
			}
			if ( isset( $attrs['children']['button']['innerContent']['desktop']['value']['rel'] ) ) {
				unset( $attrs['children']['button']['innerContent']['desktop']['value']['rel'] );
			}

			// Update the attributes with migrated button rel attributes.
			$attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] = $button_rel_attributes;
		}

		return $attrs;
	}
}
