<?php
/**
 * Toggle Title Text Color Migration
 *
 * Migrates the legacy Toggle "Title Text Color" value into "Closed Title Text Color"
 * for D5-to-D5 content upgrades. The redundant Title Text Color field was removed from
 * the Toggle UI; existing content that still stores a color on
 * `title.decoration.font.font.*.color` should have that value moved to
 * `closedTitle.decoration.font.font.*.color` when the closed-title color is absent,
 * mirroring the D4-to-D5 conversion fallback behavior.
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Migration;

use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\StringUtility;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use ET\Builder\FrontEnd\BlockParser\BlockParser;
use ET\Builder\Migration\MigrationContentBase;
use ET\Builder\Migration\MigrationContext;
use ET\Builder\Migration\Utils\MigrationUtils;

/**
 * Toggle Title Text Color Migration Class.
 *
 * @since ??
 */
class ToggleTitleTextColorMigration extends MigrationContentBase {

	/**
	 * The migration name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_name = 'toggle-title-text-color.v1';

	/**
	 * The migration release version string.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_release_version = '5.10.0';

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
		if ( ! Conditions::is_fe_and_should_migrate_content() ) {
			return;
		}

		$content = MigrationUtils::get_current_content();

		if ( $content ) {
			add_filter(
				'the_content',
				function ( $the_content ) {
					return self::migrate_content_block( $the_content );
				},
				8 // BEFORE do_blocks().
			);
		}

		$tb_template_ids = DynamicAssetsUtils::get_theme_builder_template_ids();

		if ( ! empty( $tb_template_ids ) ) {
			add_filter(
				'et_builder_render_layout',
				function ( $rendered_content ) {
					return self::migrate_content_block( $rendered_content );
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
	 *
	 * @return string The migrated content.
	 */
	public static function migrate_vb_content( $content ) {
		return self::_migrate_the_content( $content );
	}

	/**
	 * Migrate content from shortcode format.
	 *
	 * This migration only applies to block content.
	 *
	 * @since ??
	 *
	 * @param string $content The shortcode content to migrate.
	 *
	 * @return string Unchanged content.
	 */
	public static function migrate_content_shortcode( string $content ): string {
		return $content;
	}

	/**
	 * Migrate content from block format.
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

		if ( ! self::_content_needs_migration( $content ) ) {
			return $content;
		}

		if ( ! MigrationUtils::content_needs_migration( $content, self::$_release_version, [ 'divi/toggle' ] ) ) {
			return $content;
		}

		return self::_migrate_block_content( $content );
	}

	/**
	 * Migrate content entry point.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string The migrated content.
	 */
	private static function _migrate_the_content( $content ) {
		if ( '' === $content ) {
			return $content;
		}

		if ( ! MigrationUtils::content_needs_migration( $content, self::$_release_version, [ 'divi/toggle' ] ) ) {
			return $content;
		}

		if ( ! self::_content_needs_migration( $content ) ) {
			return $content;
		}

		return self::migrate_content_both( $content );
	}

	/**
	 * Fast pre-check for Toggle title color migration signatures.
	 *
	 * @since ??
	 *
	 * @param string $content The content to inspect.
	 *
	 * @return bool True when migration signature exists.
	 */
	private static function _content_needs_migration( string $content ): bool {
		return str_contains( $content, 'divi/toggle' ) && str_contains( $content, '"title"' ) && str_contains( $content, '"decoration"' );
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
		if ( ! BlockParser::has_any_divi_block( $content ) || '<!-- wp:divi/placeholder -->' === $content ) {
			return $content;
		}

		if ( ! MigrationUtils::content_needs_migration( $content, self::$_release_version, [ 'divi/toggle' ] ) ) {
			return $content;
		}

		if ( ! self::_content_needs_migration( $content ) ) {
			return $content;
		}

		$content = MigrationUtils::ensure_placeholder_wrapper( $content );

		MigrationContext::start();

		try {
			$flat_objects = MigrationUtils::parse_serialized_post_into_flat_module_object( $content, self::$_name );
			$changes_made = false;

			foreach ( $flat_objects as $module_id => $module_data ) {
				if ( 'divi/toggle' !== ( $module_data['name'] ?? '' ) ) {
					continue;
				}

				$module_attrs = $module_data['props']['attrs'] ?? null;

				if ( ! is_array( $module_attrs ) ) {
					continue;
				}

				$current_version = $module_attrs['builderVersion'] ?? '0.0.0';
				if ( ! StringUtility::version_compare( (string) $current_version, self::$_release_version, '<' ) ) {
					continue;
				}

				$migrated_attrs = self::migrate_attrs_tree( $module_attrs );

				if ( $migrated_attrs === $module_attrs ) {
					continue;
				}

				$flat_objects[ $module_id ]['props']['attrs']                   = $migrated_attrs;
				$flat_objects[ $module_id ]['props']['attrs']['builderVersion'] = self::$_release_version;
				$changes_made = true;
			}

			if ( ! $changes_made ) {
				return $content;
			}

			return MigrationUtils::serialize_flat_objects( $flat_objects );
		} finally {
			MigrationContext::end();
		}
	}

	/**
	 * Migrate a complete Toggle attrs tree.
	 *
	 * Shared with the preset migration class.
	 *
	 * @since ??
	 *
	 * @param array $attrs The attrs tree.
	 *
	 * @return array The migrated attrs tree.
	 */
	public static function migrate_attrs_tree( array $attrs ): array {
		$title_font = $attrs['title']['decoration']['font']['font'] ?? null;

		if ( ! is_array( $title_font ) || empty( $title_font ) ) {
			return $attrs;
		}

		$closed_title_font = $attrs['closedTitle']['decoration']['font']['font'] ?? [];
		if ( ! is_array( $closed_title_font ) ) {
			$closed_title_font = [];
		}

		$migrated_closed_title_font = $closed_title_font;
		$migrated_title_font        = $title_font;
		$did_migrate                = false;

		foreach ( $title_font as $breakpoint => $states ) {
			if ( ! is_array( $states ) ) {
				continue;
			}

			foreach ( $states as $state => $state_values ) {
				if ( ! is_array( $state_values ) ) {
					continue;
				}

				$color_value = $state_values['color'] ?? null;

				if ( ! self::_is_meaningful_color_value( $color_value ) ) {
					continue;
				}

				$closed_state_values = $closed_title_font[ $breakpoint ][ $state ] ?? [];
				if ( ! is_array( $closed_state_values ) ) {
					$closed_state_values = [];
				}

				$closed_color_value = $closed_state_values['color'] ?? null;

				if ( self::_is_meaningful_color_value( $closed_color_value ) ) {
					continue;
				}

				$migrated_closed_title_font[ $breakpoint ][ $state ]['color'] = $color_value;
				unset( $migrated_title_font[ $breakpoint ][ $state ]['color'] );
				$did_migrate = true;

				if ( empty( $migrated_title_font[ $breakpoint ][ $state ] ) ) {
					unset( $migrated_title_font[ $breakpoint ][ $state ] );
				}
			}

			if ( empty( $migrated_title_font[ $breakpoint ] ) ) {
				unset( $migrated_title_font[ $breakpoint ] );
			}
		}

		if ( ! $did_migrate ) {
			return $attrs;
		}

		$migrated_attrs = $attrs;

		if ( empty( $migrated_title_font ) ) {
			unset( $migrated_attrs['title']['decoration']['font']['font'] );
		} else {
			$migrated_attrs['title']['decoration']['font']['font'] = $migrated_title_font;
		}

		if ( empty( $migrated_attrs['title']['decoration']['font'] ) ) {
			unset( $migrated_attrs['title']['decoration']['font'] );
		}

		if ( empty( $migrated_attrs['title']['decoration'] ) ) {
			unset( $migrated_attrs['title']['decoration'] );
		}

		$migrated_attrs['closedTitle']['decoration']['font']['font'] = $migrated_closed_title_font;

		return $migrated_attrs;
	}

	/**
	 * Check whether a color value is meaningful (non-empty string).
	 *
	 * @since ??
	 *
	 * @param mixed $value The color value to check.
	 *
	 * @return bool True when the value is a meaningful color.
	 */
	private static function _is_meaningful_color_value( $value ): bool {
		return is_string( $value ) && '' !== $value;
	}
}
