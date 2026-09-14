<?php
/**
 * Font Capitalization Migration
 *
 * Moves legacy capitalization tokens from `font.style` into
 * the dedicated `font.capitalization` attribute path.
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

/**
 * Font Capitalization Migration Class.
 *
 * @since ??
 */
class FontCapitalizationMigration extends MigrationContentBase {
	/**
	 * The migration name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_name = 'font-capitalization.v1';

	/**
	 * The migration release version string.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_release_version = '5.8.1';

	/**
	 * Capitalization values that should be migrated out of `style`.
	 *
	 * @since ??
	 *
	 * @var array<int, string>
	 */
	private const CAPITALIZATION_VALUES = [ 'uppercase', 'lowercase', 'capitalize', 'smallCaps', 'allSmallCaps' ];

	/**
	 * Legacy `style` token remap for capitalization values.
	 *
	 * In legacy rendering, `style: ['capitalize']` produced small-caps output,
	 * so migrate that token to `smallCaps` in the dedicated capitalization attr.
	 *
	 * @since ??
	 *
	 * @param string $value Capitalization token from style.
	 *
	 * @return string
	 */
	private static function _map_legacy_style_capitalization_value( string $value ): string {
		return 'capitalize' === $value ? 'smallCaps' : $value;
	}

	/**
	 * Normalize capitalization attr into a single valid token.
	 *
	 * @since ??
	 *
	 * @param mixed $value Capitalization attr value.
	 *
	 * @return string|null
	 */
	private static function _normalize_capitalization_value( $value ): ?string {
		if ( is_string( $value ) && in_array( $value, self::CAPITALIZATION_VALUES, true ) ) {
			return $value;
		}

		if ( ! is_array( $value ) ) {
			return null;
		}

		$normalized = null;

		foreach ( $value as $capitalization_value ) {
			if ( is_string( $capitalization_value ) && in_array( $capitalization_value, self::CAPITALIZATION_VALUES, true ) ) {
				$normalized = $capitalization_value;
			}
		}

		return $normalized;
	}

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

		if ( ! self::_content_needs_migration( $content ) ) {
			return $content;
		}

		if ( ! MigrationUtils::content_needs_migration( $content, self::$_release_version ) ) {
			return $content;
		}

		$content = MigrationUtils::ensure_placeholder_wrapper( $content );

		$flat_objects = MigrationUtils::parse_serialized_post_into_flat_module_object( $content, self::$_name );

		if ( empty( $flat_objects ) ) {
			return $content;
		}

		$changes_made = false;

		foreach ( $flat_objects as $module_id => $module_data ) {
			$module_attrs = $module_data['props']['attrs'] ?? null;

			if ( ! is_array( $module_attrs ) ) {
				continue;
			}

			$current_version = $module_attrs['builderVersion'] ?? '0.0.0';
			if ( ! StringUtility::version_compare( (string) $current_version, self::$_release_version, '<' ) ) {
				continue;
			}

			if ( ! self::has_legacy_capitalization_attrs_tree( $module_attrs ) ) {
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
	}

	/**
	 * Migrate content entry point.
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

		if ( ! MigrationUtils::content_needs_migration( $content, self::$_release_version ) ) {
			return $content;
		}

		if ( ! self::_content_needs_migration( $content ) ) {
			return $content;
		}

		return self::migrate_content_both( $content );
	}

	/**
	 * Fast pre-check for content signatures.
	 *
	 * @since ??
	 *
	 * @param string $content The content to inspect.
	 *
	 * @return bool
	 */
	private static function _content_needs_migration( string $content ): bool {
		return str_contains( $content, '"style"' ) && str_contains( $content, '"font"' );
	}

	/**
	 * Migrate a complete attrs tree.
	 *
	 * @since ??
	 *
	 * @param array $attrs The attrs tree.
	 *
	 * @return array
	 */
	public static function migrate_attrs_tree( array $attrs ): array {
		$migrated_attrs = $attrs;

		foreach ( $migrated_attrs as $key => $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}

			if ( 'font' === (string) $key && self::_is_breakpoint_state_attr( $value ) ) {
				$migrated_attrs[ $key ] = self::_migrate_font_breakpoint_state_attr( $value );
				continue;
			}

			$migrated_attrs[ $key ] = self::migrate_attrs_tree( $value );
		}

		return $migrated_attrs;
	}

	/**
	 * Check whether an attrs tree still has legacy capitalization in style arrays.
	 *
	 * @since ??
	 *
	 * @param array $attrs The attrs tree.
	 *
	 * @return bool
	 */
	public static function has_legacy_capitalization_attrs_tree( array $attrs ): bool {
		foreach ( $attrs as $key => $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}

			if ( 'font' === (string) $key && self::_is_breakpoint_state_attr( $value ) ) {
				if ( self::_font_breakpoint_state_has_legacy_capitalization( $value ) ) {
					return true;
				}

				continue;
			}

			if ( self::has_legacy_capitalization_attrs_tree( $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether the given attr looks like a breakpoint/state tree.
	 *
	 * @since ??
	 *
	 * @param array $value Attr value to inspect.
	 *
	 * @return bool
	 */
	private static function _is_breakpoint_state_attr( array $value ): bool {
		foreach ( $value as $breakpoint_values ) {
			if ( is_array( $breakpoint_values ) && self::_looks_like_state_map( $breakpoint_values ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether a value looks like a breakpoint state map.
	 *
	 * @since ??
	 *
	 * @param array $value Candidate state map.
	 *
	 * @return bool
	 */
	private static function _looks_like_state_map( array $value ): bool {
		return array_key_exists( 'value', $value );
	}

	/**
	 * Check whether any style array in breakpoint/state attr has legacy capitalization values.
	 *
	 * @since ??
	 *
	 * @param array $font_attr Font breakpoint/state attr.
	 *
	 * @return bool
	 */
	private static function _font_breakpoint_state_has_legacy_capitalization( array $font_attr ): bool {
		foreach ( $font_attr as $states ) {
			if ( ! is_array( $states ) || ! self::_looks_like_state_map( $states ) ) {
				continue;
			}

			foreach ( $states as $state_attr ) {
				if ( ! is_array( $state_attr ) ) {
					continue;
				}

				$capitalization_value      = $state_attr['capitalization'] ?? null;
				$normalized_capitalization = self::_normalize_capitalization_value( $capitalization_value );
				$has_capitalization_key    = array_key_exists( 'capitalization', $state_attr );
				$is_explicit_clear         = '' === $capitalization_value;
				if ( $has_capitalization_key && ! $is_explicit_clear && $normalized_capitalization !== $capitalization_value ) {
					return true;
				}

				$style = $state_attr['style'] ?? null;
				if ( ! is_array( $style ) ) {
					continue;
				}

				foreach ( $style as $style_value ) {
					if ( is_string( $style_value ) && in_array( $style_value, self::CAPITALIZATION_VALUES, true ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Move capitalization values from style arrays into capitalization strings.
	 *
	 * @since ??
	 *
	 * @param array $font_attr Font breakpoint/state attr.
	 *
	 * @return array
	 */
	private static function _migrate_font_breakpoint_state_attr( array $font_attr ): array {
		$migrated_font_attr = $font_attr;

		foreach ( $migrated_font_attr as $breakpoint_key => $states ) {
			if ( ! is_array( $states ) || ! self::_looks_like_state_map( $states ) ) {
				continue;
			}

			foreach ( $states as $state_key => $state_attr ) {
				if ( ! is_array( $state_attr ) ) {
					continue;
				}

				$style                     = $state_attr['style'] ?? null;
				$capitalization_from_style = null;
				$decoration_style_values   = is_array( $style ) ? [] : $style;

				if ( is_array( $style ) ) {
					foreach ( $style as $style_value ) {
						if ( ! is_string( $style_value ) ) {
							continue;
						}

						if ( in_array( $style_value, self::CAPITALIZATION_VALUES, true ) ) {
							$capitalization_from_style = self::_map_legacy_style_capitalization_value( $style_value );
							continue;
						}

						$decoration_style_values[] = $style_value;
					}
				}

				$capitalization_value    = $state_attr['capitalization'] ?? null;
				$existing_capitalization = self::_normalize_capitalization_value( $capitalization_value );
				if ( '' === $capitalization_value ) {
					$existing_capitalization = '';
				}
				$final_capitalization   = $existing_capitalization ?? $capitalization_from_style;
				$has_style_caps         = null !== $capitalization_from_style;
				$has_capitalization_key = array_key_exists( 'capitalization', $state_attr );

				if ( ! $has_style_caps && ! $has_capitalization_key ) {
					continue;
				}

				if ( $has_style_caps ) {
					$migrated_font_attr[ $breakpoint_key ][ $state_key ]['style'] = $decoration_style_values;
				}

				if ( null === $final_capitalization ) {
					unset( $migrated_font_attr[ $breakpoint_key ][ $state_key ]['capitalization'] );
					continue;
				}

				$migrated_font_attr[ $breakpoint_key ][ $state_key ]['capitalization'] = $final_capitalization;
			}
		}

		return $migrated_font_attr;
	}
}
