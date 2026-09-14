<?php
/**
 * Composible Options Migration
 *
 * Migrates deprecated Tabs inactive tab background settings to the
 * new Tab Text background attribute path.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Migration;

use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use ET\Builder\FrontEnd\BlockParser\BlockParser;
use ET\Builder\Framework\Utility\StringUtility;
use ET\Builder\Migration\MigrationContentBase;
use ET\Builder\Migration\MigrationContext;
use ET\Builder\Migration\Utils\MigrationUtils;
use ET\Builder\Packages\GlobalData\GlobalPreset;

/**
 * Composible options migration class.
 *
 * @since ??
 */
class ComposibleOptionsMigration extends MigrationContentBase {

	/**
	 * The migration name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_name = 'composibleOptions.v1';

	/**
	 * The migration release version string.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_release_version = '5.1.1';

	/**
	 * Cached group preset data by group name.
	 *
	 * @since ??
	 *
	 * @var array<string, array>
	 */
	private static $_group_preset_items_cache = null;

	/**
	 * Cached module preset data by module name.
	 *
	 * @since ??
	 *
	 * @var array<string, array>
	 */
	private static $_module_preset_items_cache = null;

	/**
	 * Run the migration.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function load(): void {
		add_filter( 'divi_framework_portability_import_migrated_post_content', [ __CLASS__, 'migrate_import_content' ] );
		add_action( 'wp', [ __CLASS__, 'migrate_fe_content' ] );
		add_filter( 'et_fb_load_raw_post_content', [ __CLASS__, 'migrate_vb_content' ], 10, 2 );
	}

	/**
	 * Get the migration name.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	public static function get_name() {
		return self::$_name;
	}

	/**
	 * Get the release version for this migration.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	public static function get_release_version(): string {
		return self::$_release_version;
	}

	/**
	 * Migrate import content.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string
	 */
	public static function migrate_import_content( $content ) {
		return self::_migrate_the_content( $content );
	}

	/**
	 * Migrate frontend content.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function migrate_fe_content(): void {
		if ( ! Conditions::is_fe_and_should_migrate_content() ) {
			return;
		}

		$content = MigrationUtils::get_current_content();

		if ( $content ) {
			add_filter(
				'the_content',
				function ( $content ) {
					return self::_migrate_block_content( $content );
				},
				8
			);
		}

		$tb_template_ids = DynamicAssetsUtils::get_theme_builder_template_ids();

		if ( ! empty( $tb_template_ids ) ) {
			add_filter(
				'et_builder_render_layout',
				function ( $rendered_content ) {
					return self::_migrate_block_content( $rendered_content );
				},
				8
			);
		}
	}

	/**
	 * Migrate visual builder content.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string
	 */
	public static function migrate_vb_content( $content ) {
		return self::_migrate_the_content( $content );
	}

	/**
	 * Migrate content from all entry points.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string
	 */
	private static function _migrate_the_content( $content ) {
		if (
			! MigrationUtils::content_needs_migration( $content, self::$_release_version, [ 'divi/tabs', 'divi/blurb', 'divi/button', 'divi/comments' ] )
			&& ! self::_content_has_legacy_button_alignment_attr( $content )
			&& ! self::_content_has_button_custom_style_disabled_attr( $content )
		) {
			return $content;
		}

		return self::_migrate_block_content( $content );
	}

	/**
	 * Migrate block-based Tabs content.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string
	 */
	private static function _migrate_block_content( $content ) {
		if ( ! BlockParser::has_any_divi_block( $content ) || '<!-- wp:divi/placeholder -->' === $content ) {
			return $content;
		}

		if (
			! MigrationUtils::content_needs_migration( $content, self::$_release_version, [ 'divi/tabs', 'divi/blurb', 'divi/button', 'divi/comments' ] )
			&& ! self::_content_has_legacy_button_alignment_attr( $content )
			&& ! self::_content_has_button_custom_style_disabled_attr( $content )
		) {
			return $content;
		}

		$content                          = MigrationUtils::ensure_placeholder_wrapper( $content );
		self::$_group_preset_items_cache  = null;
		self::$_module_preset_items_cache = null;

		MigrationContext::start();

		try {
			$flat_objects = MigrationUtils::parse_serialized_post_into_flat_module_object( $content, self::$_name );
			$changes_made = false;

			foreach ( $flat_objects as $module_id => $module_data ) {
				$module_name = $module_data['name'] ?? '';

				$current_version = $module_data['props']['attrs']['builderVersion'] ?? '0.0.0';
				if ( ! StringUtility::version_compare( $current_version, self::$_release_version, '<' ) ) {
					continue;
				}

				$attrs          = $module_data['props']['attrs'] ?? [];
				$module_changed = false;

				if ( 'divi/tabs' === $module_name ) {
					$module_changed = self::_migrate_tabs_module_attrs( $flat_objects, $module_id, $attrs );
				}

				if ( 'divi/blurb' === $module_name ) {
					$module_changed = self::_migrate_blurb_module_attrs( $flat_objects, $module_id, $attrs ) || $module_changed;
				}

				if ( self::_has_legacy_button_alignment_attr( $attrs ) ) {
					$module_changed = self::_migrate_button_module_attrs( $flat_objects, $module_id, $attrs ) || $module_changed;
				}

				$updated_attrs = $flat_objects[ $module_id ]['props']['attrs'] ?? [];
				if ( self::_has_button_custom_style_disabled_attr( $updated_attrs ) ) {
					$module_changed = self::_migrate_disabled_button_custom_style_attrs( $flat_objects, $module_id ) || $module_changed;
				}

				if ( $module_changed ) {
					$changes_made = true;
					$flat_objects[ $module_id ]['props']['attrs']['builderVersion'] = self::$_release_version;
				}
			}

			if ( $changes_made ) {
				return MigrationUtils::serialize_flat_objects( $flat_objects );
			}

			return $content;
		} finally {
			MigrationContext::end();
		}
	}

	/**
	 * Migrate Tabs composable attributes.
	 *
	 * @since ??
	 *
	 * @param array  $flat_objects Flat parsed module object map.
	 * @param string $module_id    Current module ID.
	 * @param array  $attrs        Current module attrs.
	 *
	 * @return bool
	 */
	private static function _migrate_tabs_module_attrs( array &$flat_objects, string $module_id, array $attrs ): bool {
		$has_inactive_tab_background   = isset( $attrs['inactiveTab']['decoration']['background'] );
		$has_new_tab_background        = isset( $attrs['tab']['decoration']['background'] );
		$has_deprecated_inactive_group = array_key_exists( 'inactiveTab', $attrs );
		$has_tab_text_group_preset     = isset( $attrs['groupPreset']['designTabText'] );
		$has_active_group_preset       = isset( $attrs['groupPreset']['designActiveTabText'] );
		$module_changed                = false;

		if ( $has_inactive_tab_background && ! $has_new_tab_background ) {
			$flat_objects[ $module_id ]['props']['attrs']['tab']['decoration']['background'] = $attrs['inactiveTab']['decoration']['background'];
			$module_changed = true;
		}

		if ( $has_deprecated_inactive_group ) {
			unset( $flat_objects[ $module_id ]['props']['attrs']['inactiveTab'] );
			$module_changed = true;
		}

		// Keep preset behavior consistent after splitting Tab Text and Active Tab Text.
		if ( $has_tab_text_group_preset && ! $has_active_group_preset ) {
			$tab_text_group_preset = $attrs['groupPreset']['designTabText'];
			$active_group_preset   = $tab_text_group_preset;
			$preset_ids_value      = $tab_text_group_preset['presetId'] ?? [];
			$preset_ids            = is_array( $preset_ids_value ) ? $preset_ids_value : [ $preset_ids_value ];
			$active_preset_ids     = [];

			foreach ( $preset_ids as $preset_id ) {
				if ( ! is_string( $preset_id ) || '' === $preset_id ) {
					continue;
				}

				$active_preset_ids[] = ComposibleOptionsPresetMigration::get_active_group_preset_id( $preset_id );
			}

			if ( ! empty( $active_preset_ids ) ) {
				$existing_active_preset_ids = array_filter(
					$active_preset_ids,
					function ( $preset_id ) {
						return self::_group_preset_exists( 'divi/font', $preset_id );
					}
				);

				if ( ! empty( $existing_active_preset_ids ) ) {
					$active_group_preset['presetId'] = array_values( $existing_active_preset_ids );
					$flat_objects[ $module_id ]['props']['attrs']['groupPreset']['designActiveTabText'] = $active_group_preset;
					$module_changed = true;
				}
			}
		}

		return $module_changed;
	}

	/**
	 * Migrate Blurb legacy image/icon attrs to composable option groups.
	 *
	 * @since ??
	 *
	 * @param array  $flat_objects Flat parsed module object map.
	 * @param string $module_id    Current module ID.
	 * @param array  $attrs        Current module attrs.
	 *
	 * @return bool
	 */
	private static function _migrate_blurb_module_attrs( array &$flat_objects, string $module_id, array $attrs ): bool {
		$image_icon_inner_content = $attrs['imageIcon']['innerContent'] ?? [];
		$breakpoints              = [ 'desktop', 'tablet', 'phone' ];
		$module_changed           = false;
		$has_legacy_animation     = false;
		$has_new_animation        = false;

		foreach ( $breakpoints as $breakpoint ) {
			$new_sizing_alignment = $attrs['imageIcon']['decoration']['sizing'][ $breakpoint ]['value']['alignment'] ?? null;
			$legacy_alignment     = $attrs['imageIcon']['advanced']['alignment'][ $breakpoint ]['value'] ?? null;

			if ( ! is_string( $legacy_alignment ) || '' === $legacy_alignment ) {
				$legacy_alignment = $attrs['imageIcon']['advanced']['alignment']['desktop']['value'] ?? null;
			}

			if ( ( ! is_string( $new_sizing_alignment ) || '' === $new_sizing_alignment ) && is_string( $legacy_alignment ) && '' !== $legacy_alignment ) {
				$flat_objects[ $module_id ]['props']['attrs']['imageIcon']['decoration']['sizing'][ $breakpoint ]['value']['alignment'] = $legacy_alignment;
				$module_changed = true;
			}

			$legacy_image_width = $attrs['imageIcon']['advanced']['width'][ $breakpoint ]['value']['image'] ?? null;
			$legacy_icon_width  = $attrs['imageIcon']['advanced']['width'][ $breakpoint ]['value']['icon'] ?? null;
			$sizing_width_attr  = self::_get_blurb_image_sizing_width_attr( $legacy_image_width );
			$new_image_width    = $attrs['imageIcon']['decoration']['sizing'][ $breakpoint ]['value'][ $sizing_width_attr ] ?? null;
			$new_icon_size      = $attrs['imageIcon']['decoration']['sizing'][ $breakpoint ]['value']['iconFontSize'] ?? null;

			if ( ( ! is_string( $new_image_width ) || '' === $new_image_width ) && is_string( $legacy_image_width ) && '' !== $legacy_image_width ) {
				$flat_objects[ $module_id ]['props']['attrs']['imageIcon']['decoration']['sizing'][ $breakpoint ]['value'][ $sizing_width_attr ] = $legacy_image_width;
				$module_changed = true;
			}

			if ( ( ! is_string( $new_icon_size ) || '' === $new_icon_size ) && is_string( $legacy_icon_width ) && '' !== $legacy_icon_width ) {
				$flat_objects[ $module_id ]['props']['attrs']['imageIcon']['decoration']['sizing'][ $breakpoint ]['value']['iconFontSize'] = $legacy_icon_width;
				$module_changed = true;
			}

			$legacy_background = $attrs['imageIcon']['decoration']['background'][ $breakpoint ]['value'] ?? null;
			if ( is_string( $legacy_background ) ) {
				$flat_objects[ $module_id ]['props']['attrs']['imageIcon']['decoration']['background'][ $breakpoint ]['value'] = [
					'color' => $legacy_background,
				];
				$module_changed = true;
			}

			$new_animation = $attrs['imageIcon']['decoration']['animation'][ $breakpoint ]['value'] ?? null;
			if ( is_array( $new_animation ) && ! empty( $new_animation['style'] ) ) {
				$has_new_animation = true;
			}

			if ( ! is_array( $image_icon_inner_content ) || empty( $image_icon_inner_content ) ) {
				continue;
			}

			$legacy_animation = $image_icon_inner_content[ $breakpoint ]['value']['animation'] ?? null;

			if ( ! is_string( $legacy_animation ) || '' === $legacy_animation ) {
				continue;
			}

			$has_legacy_animation = true;

			$animation_style     = 'off' === $legacy_animation ? 'none' : 'slide';
			$animation_direction = in_array( $legacy_animation, [ 'top', 'left', 'right', 'bottom' ], true ) ? $legacy_animation : 'top';

			$flat_objects[ $module_id ]['props']['attrs']['imageIcon']['decoration']['animation'][ $breakpoint ]['value'] = [
				'style'     => $animation_style,
				'direction' => $animation_direction,
				'intensity' => [
					'slide' => '2',
				],
				'duration'  => '600ms',
			];

			unset( $flat_objects[ $module_id ]['props']['attrs']['imageIcon']['innerContent'][ $breakpoint ]['value']['animation'] );
			$module_changed = true;
		}

		if ( isset( $attrs['imageIcon']['advanced']['width'] ) ) {
			unset( $flat_objects[ $module_id ]['props']['attrs']['imageIcon']['advanced']['width'] );
			$module_changed = true;
		}

		if ( isset( $attrs['imageIcon']['advanced']['alignment'] ) ) {
			unset( $flat_objects[ $module_id ]['props']['attrs']['imageIcon']['advanced']['alignment'] );
			$module_changed = true;
		}

		if ( ! $has_legacy_animation && ! $has_new_animation ) {
			if ( self::_has_blurb_inherited_preset_with_legacy_image_icon_animation( $attrs ) ) {
				return $module_changed;
			}

			$flat_objects[ $module_id ]['props']['attrs']['imageIcon']['decoration']['animation']['desktop']['value'] = [
				'style'     => 'slide',
				'direction' => 'top',
				'intensity' => [
					'slide' => '2',
				],
				'duration'  => '600ms',
			];
			$module_changed = true;
		}

		return $module_changed;
	}

	/**
	 * Determine target sizing width attribute for legacy blurb image width migration.
	 *
	 * Maps pixel widths to width and all other units to maxWidth.
	 *
	 * @since ??
	 *
	 * @param string|null $legacy_image_width Legacy image width value.
	 *
	 * @return string
	 */
	private static function _get_blurb_image_sizing_width_attr( ?string $legacy_image_width ): string {
		$legacy_image_width = is_string( $legacy_image_width ) ? strtolower( trim( $legacy_image_width ) ) : '';

		return StringUtility::ends_with( $legacy_image_width, 'px' ) ? 'width' : 'maxWidth';
	}

	/**
	 * Migrate Button legacy alignment attrs to nested sizing alignment attrs.
	 *
	 * @since ??
	 *
	 * @param array  $flat_objects Flat parsed module object map.
	 * @param string $module_id    Current module ID.
	 * @param array  $attrs        Current module attrs.
	 *
	 * @return bool
	 */
	private static function _migrate_button_module_attrs( array &$flat_objects, string $module_id, array $attrs ): bool {
		$breakpoints    = [ 'desktop', 'tablet', 'phone' ];
		$states         = [ 'value', 'hover', 'sticky' ];
		$module_changed = false;

		foreach ( $breakpoints as $breakpoint ) {
			foreach ( $states as $state ) {
				$legacy_alignment = $attrs['button']['decoration']['button'][ $breakpoint ][ $state ]['alignment'] ?? null;
				$new_alignment    = $attrs['button']['decoration']['sizing'][ $breakpoint ][ $state ]['alignment'] ?? null;

				if ( is_string( $legacy_alignment ) && '' !== $legacy_alignment ) {
					if ( ! is_string( $new_alignment ) || '' === $new_alignment ) {
						$flat_objects[ $module_id ]['props']['attrs']['button']['decoration']['sizing'][ $breakpoint ][ $state ]['alignment'] = $legacy_alignment;
						$module_changed = true;
					}

					unset( $flat_objects[ $module_id ]['props']['attrs']['button']['decoration']['button'][ $breakpoint ][ $state ]['alignment'] );
					if ( self::_cleanup_button_legacy_alignment_path( $flat_objects[ $module_id ]['props']['attrs'], $breakpoint, $state ) ) {
						$module_changed = true;
					}
					$module_changed = true;
				}
			}
		}

		return $module_changed;
	}

	/**
	 * Determine whether serialized content may contain legacy button alignment attrs.
	 *
	 * @since ??
	 *
	 * @param string $content Serialized block content.
	 *
	 * @return bool
	 */
	private static function _content_has_legacy_button_alignment_attr( string $content ): bool {
		return str_contains( $content, '"button":{"decoration":{"button"' ) && str_contains( $content, '"alignment"' );
	}

	/**
	 * Determine whether serialized content may contain disabled button custom style attrs.
	 *
	 * @since ??
	 *
	 * @param string $content Serialized block content.
	 *
	 * @return bool
	 */
	private static function _content_has_button_custom_style_disabled_attr( string $content ): bool {
		return str_contains( $content, '"button":{"decoration":{"button"' ) && str_contains( $content, '"enable":"off"' );
	}

	/**
	 * Determine whether attrs contain legacy button alignment values.
	 *
	 * @since ??
	 *
	 * @param array $attrs Module attrs.
	 *
	 * @return bool
	 */
	private static function _has_legacy_button_alignment_attr( array $attrs ): bool {
		$breakpoints = [ 'desktop', 'tablet', 'phone' ];
		$states      = [ 'value', 'hover', 'sticky' ];

		foreach ( $breakpoints as $breakpoint ) {
			foreach ( $states as $state ) {
				$legacy_alignment = $attrs['button']['decoration']['button'][ $breakpoint ][ $state ]['alignment'] ?? null;

				if ( is_string( $legacy_alignment ) && '' !== $legacy_alignment ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Determine whether attrs contain disabled button custom style toggle.
	 *
	 * @since ??
	 *
	 * @param array $attrs Module attrs.
	 *
	 * @return bool
	 */
	private static function _has_button_custom_style_disabled_attr( array $attrs ): bool {
		return 'off' === ( $attrs['button']['decoration']['button']['desktop']['value']['enable'] ?? null );
	}

	/**
	 * Migrate module attrs when button custom styles were disabled.
	 *
	 * Clears button decoration attrs while preserving sizing alignment values.
	 *
	 * @since ??
	 *
	 * @param array  $flat_objects Flat parsed module object map.
	 * @param string $module_id    Current module ID.
	 *
	 * @return bool
	 */
	private static function _migrate_disabled_button_custom_style_attrs( array &$flat_objects, string $module_id ): bool {
		$module_attrs = $flat_objects[ $module_id ]['props']['attrs'] ?? [];
		$before_attrs = $module_attrs;

		if ( ! isset( $module_attrs['button']['decoration'] ) || ! is_array( $module_attrs['button']['decoration'] ) ) {
			return false;
		}

		$alignment_sizing = self::_extract_button_alignment_sizing_attrs( $module_attrs );

		unset( $module_attrs['button']['decoration'] );

		if ( ! empty( $alignment_sizing ) ) {
			$module_attrs['button']['decoration']['sizing'] = $alignment_sizing;
		}

		$flat_objects[ $module_id ]['props']['attrs'] = $module_attrs;

		return $before_attrs !== $module_attrs;
	}

	/**
	 * Extract button sizing alignment values from both new and legacy paths.
	 *
	 * @since ??
	 *
	 * @param array $attrs Module attrs.
	 *
	 * @return array
	 */
	private static function _extract_button_alignment_sizing_attrs( array $attrs ): array {
		$breakpoints      = [ 'desktop', 'tablet', 'phone' ];
		$states           = [ 'value', 'hover', 'sticky' ];
		$alignment_sizing = [];

		foreach ( $breakpoints as $breakpoint ) {
			foreach ( $states as $state ) {
				$sizing_alignment = $attrs['button']['decoration']['sizing'][ $breakpoint ][ $state ]['alignment'] ?? null;
				$legacy_alignment = $attrs['button']['decoration']['button'][ $breakpoint ][ $state ]['alignment'] ?? null;
				$alignment_value  = $sizing_alignment ?? $legacy_alignment;

				if ( is_string( $alignment_value ) && '' !== $alignment_value ) {
					$alignment_sizing[ $breakpoint ][ $state ]['alignment'] = $alignment_value;
				}
			}
		}

		return $alignment_sizing;
	}

	/**
	 * Cleanup empty legacy button alignment containers after migration.
	 *
	 * @since ??
	 *
	 * @param array  $attrs      Module attrs.
	 * @param string $breakpoint Breakpoint key.
	 * @param string $state      State key.
	 *
	 * @return bool
	 */
	private static function _cleanup_button_legacy_alignment_path( array &$attrs, string $breakpoint, string $state ): bool {
		$module_changed = false;

		if ( empty( $attrs['button']['decoration']['button'][ $breakpoint ][ $state ] ?? null ) ) {
			unset( $attrs['button']['decoration']['button'][ $breakpoint ][ $state ] );
			$module_changed = true;
		}

		if ( empty( $attrs['button']['decoration']['button'][ $breakpoint ] ?? null ) ) {
			unset( $attrs['button']['decoration']['button'][ $breakpoint ] );
			$module_changed = true;
		}

		return $module_changed;
	}

	/**
	 * Check whether applied blurb preset inheritance contains legacy image/icon animation attrs.
	 *
	 * @since ??
	 *
	 * @param array $attrs Module attrs.
	 *
	 * @return bool
	 */
	private static function _has_blurb_inherited_preset_with_legacy_image_icon_animation( array $attrs ): bool {
		if ( self::_has_blurb_module_preset_with_legacy_image_icon_animation( $attrs ) ) {
			return true;
		}

		return self::_has_blurb_group_preset_with_legacy_image_icon_animation( $attrs );
	}

	/**
	 * Check whether applied blurb module preset contains legacy image/icon animation attrs.
	 *
	 * @since ??
	 *
	 * @param array $attrs Module attrs.
	 *
	 * @return bool
	 */
	private static function _has_blurb_module_preset_with_legacy_image_icon_animation( array $attrs ): bool {
		$module_preset_value = $attrs['modulePreset'] ?? [];
		$module_preset_ids   = is_array( $module_preset_value ) ? $module_preset_value : [ $module_preset_value ];

		foreach ( $module_preset_ids as $preset_id ) {
			if ( ! is_string( $preset_id ) || '' === $preset_id || 'default' === $preset_id || '_initial' === $preset_id ) {
				continue;
			}

			$preset_item = self::_get_module_preset_item( 'divi/blurb', $preset_id );

			if ( is_array( $preset_item ) && self::_preset_item_has_blurb_image_icon_animation_override( $preset_item ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether applied blurb group presets contain legacy image/icon animation attrs.
	 *
	 * @since ??
	 *
	 * @param array $attrs Module attrs.
	 *
	 * @return bool
	 */
	private static function _has_blurb_group_preset_with_legacy_image_icon_animation( array $attrs ): bool {
		$group_presets = $attrs['groupPreset'] ?? [];
		if ( ! is_array( $group_presets ) || empty( $group_presets ) ) {
			return false;
		}

		foreach ( $group_presets as $group_preset ) {
			if ( ! is_array( $group_preset ) ) {
				continue;
			}

			$group_name = $group_preset['groupName'] ?? '';
			if ( ! is_string( $group_name ) || '' === $group_name ) {
				continue;
			}

			$preset_ids_value = $group_preset['presetId'] ?? [];
			$preset_ids       = is_array( $preset_ids_value ) ? $preset_ids_value : [ $preset_ids_value ];

			foreach ( $preset_ids as $preset_id ) {
				if ( ! is_string( $preset_id ) || '' === $preset_id || 'default' === $preset_id || '_initial' === $preset_id ) {
					continue;
				}

				$preset_item = self::_get_group_preset_item( $group_name, $preset_id );
				if ( ! is_array( $preset_item ) ) {
					continue;
				}

				if ( self::_preset_item_has_blurb_image_icon_animation_override( $preset_item ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Determine whether blurb use icon toggle is enabled for a breakpoint.
	 *
	 * Falls back to desktop value when the current breakpoint value is missing.
	 *
	 * @since ??
	 *
	 * @param array  $attrs      Module attrs.
	 * @param string $breakpoint Breakpoint key.
	 *
	 * @return bool
	 */
	private static function _is_blurb_use_icon_enabled_for_breakpoint( array $attrs, string $breakpoint ): bool {
		$use_icon = $attrs['imageIcon']['innerContent'][ $breakpoint ]['value']['useIcon'] ?? null;

		if ( ! is_string( $use_icon ) || '' === $use_icon ) {
			$use_icon = $attrs['imageIcon']['innerContent']['desktop']['value']['useIcon'] ?? null;
		}

		if ( ! is_string( $use_icon ) || '' === $use_icon ) {
			$use_icon = self::_get_blurb_inherited_use_icon_value( $attrs, $breakpoint );
		}

		return 'on' === $use_icon;
	}

	/**
	 * Resolve inherited blurb use icon toggle value from applied presets.
	 *
	 * @since ??
	 *
	 * @param array  $attrs      Module attrs.
	 * @param string $breakpoint Breakpoint key.
	 *
	 * @return string|null
	 */
	private static function _get_blurb_inherited_use_icon_value( array $attrs, string $breakpoint ): ?string {
		$module_preset_use_icon = self::_get_blurb_module_preset_use_icon_value( $attrs, $breakpoint );

		if ( is_string( $module_preset_use_icon ) && '' !== $module_preset_use_icon ) {
			return $module_preset_use_icon;
		}

		return self::_get_blurb_group_preset_use_icon_value( $attrs, $breakpoint );
	}

	/**
	 * Resolve inherited blurb use icon value from applied module presets.
	 *
	 * @since ??
	 *
	 * @param array  $attrs      Module attrs.
	 * @param string $breakpoint Breakpoint key.
	 *
	 * @return string|null
	 */
	private static function _get_blurb_module_preset_use_icon_value( array $attrs, string $breakpoint ): ?string {
		$module_preset_value = $attrs['modulePreset'] ?? [];
		$module_preset_ids   = is_array( $module_preset_value ) ? $module_preset_value : [ $module_preset_value ];

		foreach ( $module_preset_ids as $preset_id ) {
			if ( ! is_string( $preset_id ) || '' === $preset_id || '_initial' === $preset_id ) {
				continue;
			}

			$preset_item    = self::_get_module_preset_item( 'divi/blurb', $preset_id );
			$use_icon_value = self::_get_blurb_use_icon_value_from_preset_item( $preset_item, $breakpoint );

			if ( is_string( $use_icon_value ) && '' !== $use_icon_value ) {
				return $use_icon_value;
			}
		}

		return null;
	}

	/**
	 * Resolve inherited blurb use icon value from applied group presets.
	 *
	 * @since ??
	 *
	 * @param array  $attrs      Module attrs.
	 * @param string $breakpoint Breakpoint key.
	 *
	 * @return string|null
	 */
	private static function _get_blurb_group_preset_use_icon_value( array $attrs, string $breakpoint ): ?string {
		$group_presets = $attrs['groupPreset'] ?? [];

		if ( ! is_array( $group_presets ) || empty( $group_presets ) ) {
			return null;
		}

		foreach ( $group_presets as $group_preset ) {
			if ( ! is_array( $group_preset ) ) {
				continue;
			}

			$group_name = $group_preset['groupName'] ?? '';
			if ( ! is_string( $group_name ) || '' === $group_name ) {
				continue;
			}

			$preset_ids_value = $group_preset['presetId'] ?? [];
			$preset_ids       = is_array( $preset_ids_value ) ? $preset_ids_value : [ $preset_ids_value ];

			foreach ( $preset_ids as $preset_id ) {
				if ( ! is_string( $preset_id ) || '' === $preset_id || '_initial' === $preset_id ) {
					continue;
				}

				$preset_item    = self::_get_group_preset_item( $group_name, $preset_id );
				$use_icon_value = self::_get_blurb_use_icon_value_from_preset_item( $preset_item, $breakpoint );

				if ( is_string( $use_icon_value ) && '' !== $use_icon_value ) {
					return $use_icon_value;
				}
			}
		}

		return null;
	}

	/**
	 * Resolve blurb use icon value from preset item attrs/style attrs.
	 *
	 * @since ??
	 *
	 * @param array|null $preset_item Preset item.
	 * @param string     $breakpoint  Breakpoint key.
	 *
	 * @return string|null
	 */
	private static function _get_blurb_use_icon_value_from_preset_item( ?array $preset_item, string $breakpoint ): ?string {
		if ( ! is_array( $preset_item ) ) {
			return null;
		}

		$attr_groups = [ 'attrs', 'renderAttrs', 'styleAttrs' ];

		foreach ( $attr_groups as $attr_group ) {
			$attrs = $preset_item[ $attr_group ] ?? [];

			if ( ! is_array( $attrs ) ) {
				continue;
			}

			$use_icon_value = $attrs['imageIcon']['innerContent'][ $breakpoint ]['value']['useIcon'] ?? null;

			if ( ! is_string( $use_icon_value ) || '' === $use_icon_value ) {
				$use_icon_value = $attrs['imageIcon']['innerContent']['desktop']['value']['useIcon'] ?? null;
			}

			if ( is_string( $use_icon_value ) && '' !== $use_icon_value ) {
				return $use_icon_value;
			}
		}

		return null;
	}

	/**
	 * Resolve inherited legacy blurb icon width from applied presets for a breakpoint.
	 *
	 * @since ??
	 *
	 * @param array  $attrs      Module attrs.
	 * @param string $breakpoint Breakpoint key.
	 *
	 * @return string|null
	 */
	private static function _get_blurb_inherited_legacy_icon_width( array $attrs, string $breakpoint ): ?string {
		$module_preset_width = self::_get_blurb_module_preset_legacy_icon_width( $attrs, $breakpoint );

		if ( is_string( $module_preset_width ) && '' !== $module_preset_width ) {
			return $module_preset_width;
		}

		return self::_get_blurb_group_preset_legacy_icon_width( $attrs, $breakpoint );
	}

	/**
	 * Resolve inherited legacy blurb icon width from applied module presets.
	 *
	 * @since ??
	 *
	 * @param array  $attrs      Module attrs.
	 * @param string $breakpoint Breakpoint key.
	 *
	 * @return string|null
	 */
	private static function _get_blurb_module_preset_legacy_icon_width( array $attrs, string $breakpoint ): ?string {
		$module_preset_value = $attrs['modulePreset'] ?? [];
		$module_preset_ids   = is_array( $module_preset_value ) ? $module_preset_value : [ $module_preset_value ];

		foreach ( $module_preset_ids as $preset_id ) {
			if ( ! is_string( $preset_id ) || '' === $preset_id || '_initial' === $preset_id ) {
				continue;
			}

			$preset_item = self::_get_module_preset_item( 'divi/blurb', $preset_id );
			$width_value = self::_get_blurb_legacy_icon_width_from_preset_item( $preset_item, $breakpoint );

			if ( is_string( $width_value ) && '' !== $width_value ) {
				return $width_value;
			}
		}

		return null;
	}

	/**
	 * Resolve inherited legacy blurb icon width from applied group presets.
	 *
	 * @since ??
	 *
	 * @param array  $attrs      Module attrs.
	 * @param string $breakpoint Breakpoint key.
	 *
	 * @return string|null
	 */
	private static function _get_blurb_group_preset_legacy_icon_width( array $attrs, string $breakpoint ): ?string {
		$group_presets = $attrs['groupPreset'] ?? [];

		if ( ! is_array( $group_presets ) || empty( $group_presets ) ) {
			return null;
		}

		foreach ( $group_presets as $group_preset ) {
			if ( ! is_array( $group_preset ) ) {
				continue;
			}

			$group_name = $group_preset['groupName'] ?? '';
			if ( ! is_string( $group_name ) || '' === $group_name ) {
				continue;
			}

			$preset_ids_value = $group_preset['presetId'] ?? [];
			$preset_ids       = is_array( $preset_ids_value ) ? $preset_ids_value : [ $preset_ids_value ];

			foreach ( $preset_ids as $preset_id ) {
				if ( ! is_string( $preset_id ) || '' === $preset_id || '_initial' === $preset_id ) {
					continue;
				}

				$preset_item = self::_get_group_preset_item( $group_name, $preset_id );
				$width_value = self::_get_blurb_legacy_icon_width_from_preset_item( $preset_item, $breakpoint );

				if ( is_string( $width_value ) && '' !== $width_value ) {
					return $width_value;
				}
			}
		}

		return null;
	}

	/**
	 * Resolve legacy blurb icon width from preset item attrs/style attrs.
	 *
	 * @since ??
	 *
	 * @param array|null $preset_item Preset item.
	 * @param string     $breakpoint  Breakpoint key.
	 *
	 * @return string|null
	 */
	private static function _get_blurb_legacy_icon_width_from_preset_item( ?array $preset_item, string $breakpoint ): ?string {
		if ( ! is_array( $preset_item ) ) {
			return null;
		}

		$attr_groups = [ 'attrs', 'renderAttrs', 'styleAttrs' ];

		foreach ( $attr_groups as $attr_group ) {
			$attrs = $preset_item[ $attr_group ] ?? [];

			if ( ! is_array( $attrs ) ) {
				continue;
			}

			$width_value = $attrs['imageIcon']['advanced']['width'][ $breakpoint ]['value']['icon'] ?? null;

			if ( ! is_string( $width_value ) || '' === $width_value ) {
				$width_value = $attrs['imageIcon']['advanced']['width']['desktop']['value']['icon'] ?? null;
			}

			if ( is_string( $width_value ) && '' !== $width_value ) {
				return $width_value;
			}
		}

		return null;
	}

	/**
	 * Get module preset item by module and preset id.
	 *
	 * @since ??
	 *
	 * @param string $module_name Module name.
	 * @param string $preset_id   Preset ID.
	 *
	 * @return array|null
	 */
	private static function _get_module_preset_item( string $module_name, string $preset_id ): ?array {
		if ( '' === $module_name || '' === $preset_id ) {
			return null;
		}

		if ( null === self::$_module_preset_items_cache ) {
			$presets_data                     = GlobalPreset::get_data();
			self::$_module_preset_items_cache = [];

			foreach ( $presets_data['module'] ?? [] as $preset_module_name => $module_data ) {
				self::$_module_preset_items_cache[ $preset_module_name ] = $module_data['items'] ?? [];
			}
		}

		$preset_item = self::$_module_preset_items_cache[ $module_name ][ $preset_id ] ?? null;

		return is_array( $preset_item ) ? $preset_item : null;
	}

	/**
	 * Migrate shortcode content.
	 *
	 * @since ??
	 *
	 * @param string $content The shortcode content.
	 *
	 * @return string
	 */
	public static function migrate_content_shortcode( string $content ): string {
		return $content;
	}

	/**
	 * Migrate block content.
	 *
	 * @since ??
	 *
	 * @param string $content The block content.
	 *
	 * @return string
	 */
	public static function migrate_content_block( string $content ): string {
		if ( ! self::has_divi_block( $content ) ) {
			return $content;
		}

		return self::_migrate_block_content( $content );
	}

	/**
	 * Check if group preset exists in global preset data.
	 *
	 * @since ??
	 *
	 * @param string $group_name Group preset name.
	 * @param string $preset_id  Preset ID.
	 *
	 * @return bool
	 */
	private static function _group_preset_exists( string $group_name, string $preset_id ): bool {
		return null !== self::_get_group_preset_item( $group_name, $preset_id );
	}

	/**
	 * Get group preset item by group and preset id.
	 *
	 * @since ??
	 *
	 * @param string $group_name Group preset name.
	 * @param string $preset_id  Preset ID.
	 *
	 * @return array|null
	 */
	private static function _get_group_preset_item( string $group_name, string $preset_id ): ?array {
		if ( '' === $group_name || '' === $preset_id ) {
			return null;
		}

		if ( null === self::$_group_preset_items_cache ) {
			$presets_data                    = GlobalPreset::get_data();
			self::$_group_preset_items_cache = [];

			foreach ( $presets_data['group'] ?? [] as $preset_group_name => $group_data ) {
				self::$_group_preset_items_cache[ $preset_group_name ] = $group_data['items'] ?? [];
			}
		}

		$preset_item = self::$_group_preset_items_cache[ $group_name ][ $preset_id ] ?? null;

		return is_array( $preset_item ) ? $preset_item : null;
	}

	/**
	 * Check whether preset item contains blurb image/icon animation override attrs.
	 *
	 * @since ??
	 *
	 * @param array $preset_item Preset item.
	 *
	 * @return bool
	 */
	private static function _preset_item_has_blurb_image_icon_animation_override( array $preset_item ): bool {
		$attr_groups = [ 'attrs', 'renderAttrs', 'styleAttrs' ];

		foreach ( $attr_groups as $attr_group ) {
			$attrs = $preset_item[ $attr_group ] ?? [];

			if ( ! is_array( $attrs ) ) {
				continue;
			}

			if (
				isset( $attrs['imageIcon']['innerContent']['desktop']['value']['animation'] )
				|| isset( $attrs['imageIcon']['innerContent']['tablet']['value']['animation'] )
				|| isset( $attrs['imageIcon']['innerContent']['phone']['value']['animation'] )
				|| isset( $attrs['imageIcon']['decoration']['animation']['desktop']['value']['style'] )
				|| isset( $attrs['imageIcon']['decoration']['animation']['tablet']['value']['style'] )
				|| isset( $attrs['imageIcon']['decoration']['animation']['phone']['value']['style'] )
			) {
				return true;
			}
		}

		return false;
	}
}
