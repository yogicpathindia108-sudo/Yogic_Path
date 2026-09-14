<?php
/**
 * Grid Offset Rules Migration
 *
 * Migrates legacy grid offset rule shape (`offsetRule`/`offsetValue`) to the
 * canonical `offsetValues` map for all Divi 5 block attrs trees.
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
 * Grid Offset Rules Migration Class.
 *
 * @since ??
 */
class GridOffsetRulesMigration extends MigrationContentBase {
	/**
	 * The migration name.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_name = 'grid-offset-rules.v1';

	/**
	 * The migration release version string.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_release_version = '5.9.0';

	/**
	 * Allowed canonical grid offset rule keys.
	 *
	 * @since ??
	 *
	 * @var array<int, string>
	 */
	private const GRID_OFFSET_RULE_KEYS = [
		'columnSpan',
		'columnStart',
		'columnEnd',
		'rowSpan',
		'rowStart',
		'rowEnd',
		'gridTemplateColumns',
	];

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
	 * Get release version.
	 *
	 * @since ??
	 *
	 * @return string The release version.
	 */
	public static function get_release_version(): string {
		return self::$_release_version;
	}

	/**
	 * Migrate portability-import content.
	 *
	 * @since ??
	 *
	 * @param string $content Content to migrate.
	 *
	 * @return string Migrated content.
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
					return self::_migrate_block_content( $the_content );
				},
				8 // BEFORE do_blocks().
			);
		}

		$tb_template_ids = DynamicAssetsUtils::get_theme_builder_template_ids();
		if ( ! empty( $tb_template_ids ) ) {
			add_filter(
				'et_builder_render_layout',
				function ( $rendered_content ) {
					return self::_migrate_block_content( $rendered_content );
				},
				8 // BEFORE do_blocks().
			);
		}
	}

	/**
	 * Migrate Visual Builder content.
	 *
	 * @since ??
	 *
	 * @param string $content Content to migrate.
	 *
	 * @return string Migrated content.
	 */
	public static function migrate_vb_content( $content ) {
		return self::_migrate_the_content( $content );
	}

	/**
	 * Migrate block content.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string The migrated content.
	 */
	public static function migrate_content_block( string $content ): string {
		if ( ! self::has_divi_block( $content ) ) {
			return $content;
		}

		return self::_migrate_block_content( $content );
	}

	/**
	 * Migrate shortcode content.
	 *
	 * This migration only applies to block content.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string Unchanged content.
	 */
	public static function migrate_content_shortcode( string $content ): string {
		return $content;
	}

	/**
	 * Migrate attrs tree.
	 *
	 * @since ??
	 *
	 * @param array $attrs Attrs tree.
	 *
	 * @return array Migrated attrs tree.
	 */
	public static function migrate_attrs_tree( array $attrs ): array {
		$migrated_attrs = $attrs;

		foreach ( $migrated_attrs as $key => $value ) {
			if ( is_array( $value ) ) {
				$migrated_attrs[ $key ] = self::migrate_attrs_tree( $value );
			}
		}

		if ( array_key_exists( 'gridOffsetRules', $migrated_attrs ) ) {
			$migrated_attrs['gridOffsetRules'] = self::_normalize_grid_offset_rules_value( $migrated_attrs['gridOffsetRules'] );
		}

		$migrated_attrs = self::_normalize_layout_column_widths_node( $migrated_attrs );

		return $migrated_attrs;
	}

	/**
	 * Check whether attrs tree needs grid-offset-rules migration.
	 *
	 * @since ??
	 *
	 * @param array $attrs Attrs tree.
	 *
	 * @return bool True when migration is needed.
	 */
	public static function attrs_tree_needs_migration( array $attrs ): bool {
		if ( self::_node_needs_migration( $attrs ) ) {
			return true;
		}

		foreach ( $attrs as $value ) {
			if ( is_array( $value ) && self::attrs_tree_needs_migration( $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Migrate content entry point.
	 *
	 * @since ??
	 *
	 * @param string $content Content to migrate.
	 *
	 * @return string Migrated content.
	 */
	private static function _migrate_the_content( $content ) {
		if ( ! MigrationUtils::content_needs_migration( $content, self::$_release_version ) ) {
			return $content;
		}

		if ( ! self::_content_needs_migration( $content ) ) {
			return $content;
		}

		return self::_migrate_block_content( $content );
	}

	/**
	 * Migrate block-based content (D5 blocks).
	 *
	 * @since ??
	 *
	 * @param string $content Content to migrate.
	 *
	 * @return string Migrated content.
	 */
	private static function _migrate_block_content( $content ) {
		if ( ! BlockParser::has_any_divi_block( $content ) || '<!-- wp:divi/placeholder -->' === $content ) {
			return $content;
		}

		if ( ! MigrationUtils::content_needs_migration( $content, self::$_release_version ) ) {
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
				if ( ! StringUtility::version_compare(
					$module_data['props']['attrs']['builderVersion'] ?? '0.0.0',
					self::$_release_version,
					'<'
				)
				) {
					continue;
				}

				$attrs = $module_data['props']['attrs'] ?? null;
				if ( ! is_array( $attrs ) || ! self::attrs_tree_needs_migration( $attrs ) ) {
					continue;
				}

				$migrated_attrs = self::migrate_attrs_tree( $attrs );
				if ( $migrated_attrs === $attrs ) {
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
	 * Fast pre-check for migration signature in content.
	 *
	 * @since ??
	 *
	 * @param string $content Content to check.
	 *
	 * @return bool True when migration signature exists.
	 */
	private static function _content_needs_migration( string $content ): bool {
		$has_grid_offset_rules_signature = str_contains( $content, '"gridOffsetRules"' )
			&& ( str_contains( $content, '"offsetRule"' ) || str_contains( $content, '"offsetValue"' ) );

		$has_column_widths_mode_signature = str_contains( $content, '"gridColumnWidths":"equalMinimum"' )
			|| str_contains( $content, '"gridColumnWidths":"equalFixed"' );

		return $has_grid_offset_rules_signature || $has_column_widths_mode_signature;
	}

	/**
	 * Check whether current attrs node needs migration.
	 *
	 * @since ??
	 *
	 * @param array $node Attr node.
	 *
	 * @return bool True when node needs migration.
	 */
	private static function _node_needs_migration( array $node ): bool {
		if ( self::_layout_column_widths_node_needs_migration( $node ) ) {
			return true;
		}

		if ( ! array_key_exists( 'gridOffsetRules', $node ) ) {
			return false;
		}

		$grid_offset_rules = $node['gridOffsetRules'];
		if ( ! is_array( $grid_offset_rules ) ) {
			return false;
		}

		$has_rules_key = array_key_exists( 'rules', $grid_offset_rules );
		$rules         = $has_rules_key ? $grid_offset_rules['rules'] : $grid_offset_rules;
		if ( ! is_array( $rules ) ) {
			return false;
		}

		if ( ! $has_rules_key ) {
			return true;
		}

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			if ( array_key_exists( 'offsetRule', $rule ) || array_key_exists( 'offsetValue', $rule ) ) {
				return true;
			}

			if ( ! is_array( $rule['offsetValues'] ?? null ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize `gridOffsetRules` value to canonical shape.
	 *
	 * @since ??
	 *
	 * @param mixed $grid_offset_rules Raw `gridOffsetRules` value.
	 *
	 * @return mixed Canonical value when input is valid, otherwise original input.
	 */
	private static function _normalize_grid_offset_rules_value( $grid_offset_rules ) {
		if ( ! is_array( $grid_offset_rules ) ) {
			return $grid_offset_rules;
		}

		$rules = $grid_offset_rules['rules'] ?? $grid_offset_rules;
		if ( ! is_array( $rules ) ) {
			return $grid_offset_rules;
		}

		$normalized_rules = [];
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				$normalized_rules[] = $rule;
				continue;
			}

			$offset_values_raw = $rule['offsetValues'] ?? [];
			$offset_values     = self::_normalize_offset_values( $offset_values_raw );

			$legacy_rule_key = $rule['offsetRule'] ?? null;
			if ( is_string( $legacy_rule_key )
				&& in_array( $legacy_rule_key, self::GRID_OFFSET_RULE_KEYS, true )
				&& ! array_key_exists( $legacy_rule_key, $offset_values )
			) {
				$legacy_rule_value = $rule['offsetValue'] ?? '';
				if ( is_scalar( $legacy_rule_value ) ) {
					$offset_values[ $legacy_rule_key ] = (string) $legacy_rule_value;
				}
			}

			unset( $rule['offsetRule'] );
			unset( $rule['offsetValue'] );
			$rule['offsetValues'] = $offset_values;

			$normalized_rules[] = $rule;
		}

		if ( array_key_exists( 'rules', $grid_offset_rules ) ) {
			$normalized_grid_offset_rules          = $grid_offset_rules;
			$normalized_grid_offset_rules['rules'] = $normalized_rules;

			return $normalized_grid_offset_rules;
		}

		return [ 'rules' => $normalized_rules ];
	}

	/**
	 * Check whether current attrs node has legacy layout column-widths mode.
	 *
	 * @since ??
	 *
	 * @param array $node Attr node.
	 *
	 * @return bool True when layout column-widths mode must be normalized.
	 */
	private static function _layout_column_widths_node_needs_migration( array $node ): bool {
		if ( ! array_key_exists( 'gridColumnWidths', $node ) ) {
			return false;
		}

		$grid_column_widths = $node['gridColumnWidths'];
		if ( ! is_string( $grid_column_widths ) ) {
			return false;
		}

		if ( 'equalMinimum' === $grid_column_widths ) {
			return self::_is_empty_layout_track_value( $node['gridColumnMinWidth'] ?? null );
		}

		if ( 'equalFixed' === $grid_column_widths ) {
			return self::_is_empty_layout_track_value( $node['gridColumnWidth'] ?? null );
		}

		return false;
	}

	/**
	 * Normalize node-level layout column-widths mode.
	 *
	 * @since ??
	 *
	 * @param array $node Attr node.
	 *
	 * @return array Normalized attr node.
	 */
	private static function _normalize_layout_column_widths_node( array $node ): array {
		$normalized_node = $node;

		if ( ! self::_layout_column_widths_node_needs_migration( $normalized_node ) ) {
			return $normalized_node;
		}

		$normalized_node['gridColumnWidths'] = 'equal';

		return $normalized_node;
	}

	/**
	 * Determine whether a layout track field value is empty.
	 *
	 * @since ??
	 *
	 * @param mixed $track_value Track field value.
	 *
	 * @return bool True when value is null/empty-string/whitespace-only.
	 */
	private static function _is_empty_layout_track_value( $track_value ): bool {
		if ( null === $track_value ) {
			return true;
		}

		if ( ! is_scalar( $track_value ) ) {
			return false;
		}

		return '' === trim( (string) $track_value );
	}

	/**
	 * Normalize `offsetValues` map.
	 *
	 * @since ??
	 *
	 * @param mixed $offset_values_raw Raw offsetValues value.
	 *
	 * @return array<string, string> Normalized offset values map.
	 */
	private static function _normalize_offset_values( $offset_values_raw ): array {
		if ( ! is_array( $offset_values_raw ) ) {
			return [];
		}

		$normalized_offset_values = [];
		foreach ( self::GRID_OFFSET_RULE_KEYS as $rule_key ) {
			if ( ! array_key_exists( $rule_key, $offset_values_raw ) ) {
				continue;
			}

			$rule_value = $offset_values_raw[ $rule_key ];
			if ( null === $rule_value || is_scalar( $rule_value ) ) {
				$normalized_offset_values[ $rule_key ] = null === $rule_value ? '' : (string) $rule_value;
			}
		}

		return $normalized_offset_values;
	}
}
