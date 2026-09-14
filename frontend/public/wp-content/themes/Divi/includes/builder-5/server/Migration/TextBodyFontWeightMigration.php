<?php
/**
 * Text Body Font Weight Migration
 *
 * Backfills the default body font weight for legacy Text modules that carried
 * a body font family but lost the saved desktop weight during older imports.
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Migration;

use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\StringUtility;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use ET\Builder\Migration\MigrationContentBase;
use ET\Builder\Migration\Utils\MigrationUtils;
use ET\Builder\Packages\GlobalData\GlobalPreset;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;

/**
 * Text Body Font Weight Migration Class.
 *
 * @since ??
 */
class TextBodyFontWeightMigration extends MigrationContentBase {
	/**
	 * Supported block modules for this migration.
	 *
	 * @since ??
	 *
	 * @var string[]
	 */
	private const SUPPORTED_MODULES = [ 'divi/text' ];

	/**
	 * The migration name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_name = 'text-body-font-weight.v1';

	/**
	 * The migration release version string.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_release_version = '5.4.3';

	/**
	 * Fallback Text body font weight.
	 *
	 * `divi/text` does not always expose its body font defaults through
	 * `ModuleRegistration::generate_default_attrs()` during portability import,
	 * so keep the known default contract here for legacy import backfills.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_fallback_default_weight = '400';

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
				function ( $the_content ) {
					return self::migrate_content_block( $the_content );
				},
				8
			);
		}

		$tb_template_ids = DynamicAssetsUtils::get_theme_builder_template_ids();

		if ( ! empty( $tb_template_ids ) ) {
			add_filter(
				'et_builder_render_layout',
				function ( $rendered_content ) {
					return self::migrate_content_block( $rendered_content );
				},
				8
			);
		}
	}

	/**
	 * Migrate Visual Builder content.
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
	 * Migrate the content.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string
	 */
	private static function _migrate_the_content( string $content ): string {
		if ( '' === $content ) {
			return $content;
		}

		if ( ! MigrationUtils::content_needs_migration( $content, self::$_release_version, self::SUPPORTED_MODULES ) ) {
			return $content;
		}

		return self::migrate_content_both( $content );
	}

	/**
	 * Migrate shortcode content.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
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
	 * @param string $content The content to migrate.
	 *
	 * @return string
	 */
	public static function migrate_content_block( string $content ): string {
		if ( ! self::has_divi_block( $content ) ) {
			return $content;
		}

		if ( ! str_contains( $content, 'divi/text' ) || ! str_contains( $content, '"bodyFont"' ) ) {
			return $content;
		}

		if ( ! MigrationUtils::content_needs_migration( $content, self::$_release_version, self::SUPPORTED_MODULES ) ) {
			return $content;
		}

		$default_weight = self::_get_default_text_body_font_weight();

		if ( '' === $default_weight ) {
			return $content;
		}

		// Disable Divi's custom block parser so parse_blocks() uses WordPress's native
		// WP_Block_Parser, which does not create BlockParserBlock instances and therefore
		// does not increment ET_Builder_Module_Order order indexes. Incrementing those
		// counters here (at priority 8, before do_blocks) would shift the section indexes
		// seen by the HTML renderer at priority 9, causing et_pb_section_0 to be absent.
		add_filter( 'divi_module_library_block_parser_enable', '__return_false', 99 );
		$blocks = parse_blocks( $content );
		remove_filter( 'divi_module_library_block_parser_enable', '__return_false', 99 );

		if ( empty( $blocks ) ) {
			return $content;
		}

		$changes_made = false;
		$blocks       = self::_backfill_blocks( $blocks, $default_weight, $changes_made );

		if ( ! $changes_made ) {
			return $content;
		}

		return serialize_blocks( $blocks );
	}

	/**
	 * Recursively backfill legacy text block weights.
	 *
	 * @since ??
	 *
	 * @param array  $blocks Blocks to inspect.
	 * @param string $default_weight Default Text weight.
	 * @param bool   $changes_made Whether a block was updated.
	 *
	 * @return array
	 */
	private static function _backfill_blocks( array $blocks, string $default_weight, bool &$changes_made ): array {
		foreach ( $blocks as &$block ) {
			$attrs = $block['attrs'] ?? [];

			if ( 'divi/text' === ( $block['blockName'] ?? '' ) && self::_should_backfill_attrs( $attrs ) ) {
				$block['attrs']['content']['decoration']['bodyFont']['body']['font']['desktop']['value']['weight'] = $default_weight;
				$block['attrs']['builderVersion'] = self::$_release_version;
				$changes_made                     = true;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::_backfill_blocks( $block['innerBlocks'], $default_weight, $changes_made );
			}
		}

		return $blocks;
	}

	/**
	 * Check whether the text module attrs need the default body font weight backfill.
	 *
	 * @since ??
	 *
	 * @param array $attrs Parsed block attrs.
	 *
	 * @return bool
	 */
	private static function _should_backfill_attrs( array $attrs ): bool {
		if ( empty( $attrs ) ) {
			return false;
		}

		$builder_version = $attrs['builderVersion'] ?? '0.0.0';
		if ( ! is_string( $builder_version ) || '' === $builder_version ) {
			$builder_version = '0.0.0';
		}

		if ( StringUtility::version_compare( $builder_version, self::$_release_version, '>=' ) ) {
			return false;
		}

		$preset_ids = GlobalPreset::normalize_preset_stack( $attrs['modulePreset'] ?? '' );
		if ( ! empty( $preset_ids ) ) {
			return false;
		}

		$font_value = $attrs['content']['decoration']['bodyFont']['body']['font']['desktop']['value'] ?? [];
		if ( ! is_array( $font_value ) ) {
			return false;
		}

		$has_family = ! empty( $font_value['family'] );
		$has_weight = isset( $font_value['weight'] ) && '' !== (string) $font_value['weight'];

		return $has_family && ! $has_weight;
	}

	/**
	 * Get the default desktop body font weight for the Text module.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	private static function _get_default_text_body_font_weight(): string {
		static $default_weight = null;

		if ( null !== $default_weight ) {
			return $default_weight;
		}

		$default_attrs = ModuleRegistration::generate_default_attrs( 'divi/text', 'default' );
		$weight        = $default_attrs['content']['decoration']['bodyFont']['body']['font']['desktop']['value']['weight'] ?? '';

		$default_weight = is_scalar( $weight ) && '' !== (string) $weight
			? (string) $weight
			: self::$_fallback_default_weight;

		return $default_weight;
	}
}
