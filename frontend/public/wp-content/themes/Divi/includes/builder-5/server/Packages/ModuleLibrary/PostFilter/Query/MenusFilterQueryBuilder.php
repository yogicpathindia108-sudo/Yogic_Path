<?php
/**
 * Menus filter query builder.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostFilter\Query;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\ModuleLibrary\PostFilterItem\PostFilterCustomFieldUtils;

/**
 * Builds nav-menu query args from post-filter params for menus loops.
 *
 * Handles text search against menu item fields and numeric menu-order comparisons.
 *
 * @since ??
 */
class MenusFilterQueryBuilder {

	/**
	 * Apply post-filter refinements to menus queries.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query Base query args.
	 * @param array<string,mixed> $params     Sanitized filter params.
	 * @param string              $relation   Filter relation (`and` or `or`).
	 *
	 * @return array<string,mixed>
	 */
	public static function apply( array $base_query, array $params, string $relation ): array {
		$clauses = PostFilterQueryHelpers::extract_filter_clauses( $params );

		if ( empty( $clauses ) ) {
			return self::_apply_sort_params( $base_query, $params );
		}

		$clause_menu_item_id_sets = [];

		foreach ( $clauses as $clause_key => $clause_value ) {
			if (
				PostFilterCustomFieldUtils::is_text_field_param_key( $clause_key )
				&& is_string( $clause_value )
				&& '' !== $clause_value
			) {
				$target                     = $params[ $clause_key . '_target' ] ?? '';
				$clause_menu_item_id_sets[] = self::_query_menu_item_ids_for_search( $base_query, $clause_value, (string) $target );
				continue;
			}

			if ( PostFilterCustomFieldUtils::is_number_field_param_key( $clause_key ) ) {
				$compare = PostFilterCustomFieldUtils::sanitize_compare_param_value( $params[ $clause_key . '_compare' ] ?? '=' );
				$numeric = PostFilterCustomFieldUtils::parse_numeric_filter_value( $clause_value );

				if ( null !== $compare && null !== $numeric ) {
					$clause_menu_item_id_sets[] = self::_query_menu_item_ids_for_menu_order_comparison( $base_query, $numeric, $compare );
				}
			}
		}

		if ( empty( $clause_menu_item_id_sets ) ) {
			return self::_apply_sort_params( $base_query, $params );
		}

		$matching_menu_item_ids = self::_merge_menu_item_id_sets( $clause_menu_item_id_sets, $relation );

		$base_query = self::_restrict_menus_query_to_ids( $base_query, $matching_menu_item_ids );

		return self::_apply_sort_params( $base_query, $params );
	}

	/**
	 * Merge menu item ID sets using the configured filter relation.
	 *
	 * @since ??
	 *
	 * @param array<int, array<int, int>> $clause_menu_item_id_sets Menu item ID sets from each active clause.
	 * @param string                      $relation                 Filter relation (`and` or `or`).
	 *
	 * @return array<int, int>
	 */
	private static function _merge_menu_item_id_sets( array $clause_menu_item_id_sets, string $relation ): array {
		if ( 'or' === $relation && count( $clause_menu_item_id_sets ) > 1 ) {
			return array_values( array_unique( array_merge( ...$clause_menu_item_id_sets ) ) );
		}

		$matching_menu_item_ids = null;

		foreach ( $clause_menu_item_id_sets as $menu_item_id_set ) {
			if ( null === $matching_menu_item_ids ) {
				$matching_menu_item_ids = $menu_item_id_set;
				continue;
			}

			$matching_menu_item_ids = array_values( array_intersect( $matching_menu_item_ids, $menu_item_id_set ) );
		}

		return array_values( array_map( 'absint', $matching_menu_item_ids ?? [] ) );
	}

	/**
	 * Restrict menus query args to one explicit menu item ID set.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query    Base query args.
	 * @param array<int, mixed>   $menu_item_ids Matching menu item IDs.
	 *
	 * @return array<string,mixed>
	 */
	private static function _restrict_menus_query_to_ids( array $base_query, array $menu_item_ids ): array {
		$menu_item_ids = array_values(
			array_filter(
				array_map( 'absint', $menu_item_ids )
			)
		);

		if ( isset( $base_query['include'] ) && is_array( $base_query['include'] ) ) {
			$menu_item_ids = array_values( array_intersect( $base_query['include'], $menu_item_ids ) );
		}

		$base_query['include'] = ! empty( $menu_item_ids ) ? $menu_item_ids : [ 0 ];

		return $base_query;
	}

	/**
	 * Apply scalar sort params to menus query args.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query Base query args.
	 * @param array<string,mixed> $params     Sanitized filter params.
	 *
	 * @return array<string,mixed>
	 */
	private static function _apply_sort_params( array $base_query, array $params ): array {
		$order_by_value = $params['order_by'] ?? null;
		$order_value    = $params['order'] ?? null;

		return self::_apply_scalar_sort(
			$base_query,
			is_string( $order_by_value ) ? $order_by_value : 'default',
			is_string( $order_value ) ? $order_value : 'default'
		);
	}

	/**
	 * Apply one order-by and order pair to menus query args.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query     Base query args.
	 * @param string              $order_by_value Order-by slug from filter control.
	 * @param string              $order_value    Order slug from filter control.
	 *
	 * @return array<string,mixed>
	 */
	private static function _apply_scalar_sort( array $base_query, string $order_by_value, string $order_value ): array {
		$order_by_value = PostFilterCustomFieldUtils::sanitize_clause_key( $order_by_value );

		if ( 'default' !== $order_by_value && '' !== $order_by_value ) {
			$base_query = self::_apply_order_by( $base_query, $order_by_value );
		}

		if ( is_string( $order_value ) && 'default' !== $order_value ) {
			$base_query = PostFilterQueryHelpers::apply_order( $base_query, $order_value );
		}

		return $base_query;
	}

	/**
	 * Apply order-by filter values to menus query args.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query     Base query args.
	 * @param string              $order_by_value Order-by slug from filter control.
	 *
	 * @return array<string,mixed>
	 */
	private static function _apply_order_by( array $base_query, string $order_by_value ): array {
		if ( ! in_array( $order_by_value, self::_get_valid_order_by_values(), true ) ) {
			return $base_query;
		}

		$base_query['order_by'] = $order_by_value;

		return $base_query;
	}

	/**
	 * Return supported order-by values for menus-loop filter controls.
	 *
	 * @since ??
	 *
	 * @return array<int, string>
	 */
	private static function _get_valid_order_by_values(): array {
		return [
			'menu_text',
			'attr_title',
			'classes',
			'xfn',
			'description',
		];
	}

	/**
	 * Query menu item IDs whose configured search target partially matches one keyword.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query   Base query args.
	 * @param string              $search_value Submitted search value.
	 * @param string              $target       Search target option value.
	 *
	 * @return array<int, int>
	 */
	private static function _query_menu_item_ids_for_search( array $base_query, string $search_value, string $target ): array {
		$menu_items = self::_get_menu_items_from_base_query( $base_query );
		$target     = self::_resolve_search_target( $target );
		$needle     = strtolower( $search_value );
		$matches    = [];

		foreach ( $menu_items as $menu_item ) {
			if ( self::_menu_item_matches_search( $menu_item, $needle, $target ) ) {
				$matches[] = (int) $menu_item->ID;
			}
		}

		return $matches;
	}

	/**
	 * Query menu item IDs whose menu order matches one numeric comparison.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query Base query args.
	 * @param float               $menu_order Submitted numeric filter value.
	 * @param string              $compare    Sanitized comparison operator.
	 *
	 * @return array<int, int>
	 */
	private static function _query_menu_item_ids_for_menu_order_comparison( array $base_query, float $menu_order, string $compare ): array {
		$menu_items = self::_get_menu_items_from_base_query( $base_query );
		$matches    = [];

		foreach ( $menu_items as $menu_item ) {
			if ( self::_numeric_comparison_matches( (float) ( $menu_item->menu_order ?? 0 ), $menu_order, $compare ) ) {
				$matches[] = (int) $menu_item->ID;
			}
		}

		return $matches;
	}

	/**
	 * Load menu items for one menus-loop base query.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query Base query args.
	 *
	 * @return array<int, object>
	 */
	private static function _get_menu_items_from_base_query( array $base_query ): array {
		$menu_id = $base_query['menu_id'] ?? '';

		if ( empty( $menu_id ) ) {
			return [];
		}

		$menu_ids = [];

		if ( is_string( $menu_id ) && str_contains( $menu_id, ',' ) ) {
			$menu_ids = array_map( 'trim', explode( ',', $menu_id ) );
		} elseif ( is_string( $menu_id ) ) {
			$menu_ids = [ $menu_id ];
		} elseif ( is_array( $menu_id ) ) {
			$menu_ids = $menu_id;
		}

		$menu_ids = array_values( array_filter( array_map( 'absint', $menu_ids ) ) );

		if ( empty( $menu_ids ) ) {
			return [];
		}

		$menu_items = [];

		foreach ( $menu_ids as $id ) {
			$items = wp_get_nav_menu_items( $id );

			if ( ! empty( $items ) && ! is_wp_error( $items ) ) {
				$menu_items = array_merge( $menu_items, $items );
			}
		}

		if ( isset( $base_query['include'] ) && is_array( $base_query['include'] ) ) {
			$include    = array_map( 'absint', $base_query['include'] );
			$menu_items = array_values(
				array_filter(
					$menu_items,
					static function ( $menu_item ) use ( $include ): bool {
						return in_array( (int) $menu_item->ID, $include, true );
					}
				)
			);
		}

		return $menu_items;
	}

	/**
	 * Resolve a menus-loop search target option to a supported target slug.
	 *
	 * @since ??
	 *
	 * @param string $target Search target option value.
	 *
	 * @return string
	 */
	private static function _resolve_search_target( string $target ): string {
		$valid_targets = [
			PostFilterCustomFieldUtils::SEARCH_TARGET_MENU_TEXT,
			PostFilterCustomFieldUtils::SEARCH_TARGET_MENU_ATTR_TITLE,
			PostFilterCustomFieldUtils::SEARCH_TARGET_MENU_CLASSES,
			PostFilterCustomFieldUtils::SEARCH_TARGET_MENU_XFN,
			PostFilterCustomFieldUtils::SEARCH_TARGET_MENU_DESCRIPTION,
		];

		if ( in_array( $target, $valid_targets, true ) ) {
			return $target;
		}

		return PostFilterCustomFieldUtils::SEARCH_TARGET_MENU_TEXT;
	}

	/**
	 * Whether one menu item field partially matches one keyword search.
	 *
	 * @since ??
	 *
	 * @param object $menu_item Menu item object.
	 * @param string $needle    Lowercased search keyword.
	 * @param string $target    Search target option value.
	 *
	 * @return bool
	 */
	private static function _menu_item_matches_search( $menu_item, string $needle, string $target ): bool {
		$field_value = '';

		switch ( $target ) {
			case PostFilterCustomFieldUtils::SEARCH_TARGET_MENU_ATTR_TITLE:
				$field_value = (string) ( $menu_item->attr_title ?? '' );
				break;
			case PostFilterCustomFieldUtils::SEARCH_TARGET_MENU_CLASSES:
				$classes     = $menu_item->classes ?? [];
				$field_value = is_array( $classes ) ? implode( ' ', $classes ) : (string) $classes;
				break;
			case PostFilterCustomFieldUtils::SEARCH_TARGET_MENU_XFN:
				$field_value = (string) ( $menu_item->xfn ?? '' );
				break;
			case PostFilterCustomFieldUtils::SEARCH_TARGET_MENU_DESCRIPTION:
				$field_value = (string) ( $menu_item->description ?? '' );
				break;
			case PostFilterCustomFieldUtils::SEARCH_TARGET_MENU_TEXT:
			default:
				$field_value = (string) ( $menu_item->title ?? '' );
				break;
		}

		return '' !== $field_value && str_contains( strtolower( $field_value ), $needle );
	}

	/**
	 * Evaluate one numeric comparison.
	 *
	 * @since ??
	 *
	 * @param float  $left    Left-hand value.
	 * @param float  $right   Right-hand value.
	 * @param string $compare Comparison operator.
	 *
	 * @return bool
	 */
	private static function _numeric_comparison_matches( float $left, float $right, string $compare ): bool {
		switch ( $compare ) {
			case '>':
				return $left > $right;
			case '>=':
				return $left >= $right;
			case '<':
				return $left < $right;
			case '<=':
				return $left <= $right;
			case '!=':
				return $left != $right;
			case '=':
			default:
				return $left == $right;
		}
	}
}
