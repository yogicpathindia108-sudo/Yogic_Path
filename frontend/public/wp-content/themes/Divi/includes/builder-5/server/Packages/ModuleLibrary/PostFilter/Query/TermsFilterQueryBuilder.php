<?php
/**
 * Terms filter query builder.
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
 * Builds WP_Term_Query args from post-filter params for terms/taxonomy loops.
 *
 * Handles name/description text search and numeric term-count comparisons.
 *
 * @since ??
 */
class TermsFilterQueryBuilder {

	/**
	 * WP_Term_Query flag used to scope name/description search clause filters.
	 *
	 * @var string
	 */
	private const TERMS_SEARCH_QUERY_FLAG = 'divi_post_filter_term_search';

	/**
	 * WP_Term_Query flag used to scope term count comparison clause filters.
	 *
	 * @var string
	 */
	private const TERMS_COUNT_QUERY_FLAG = 'divi_post_filter_term_count';

	/**
	 * WP_Term_Query flag used to scope taxonomy order-by clause filters.
	 *
	 * @var string
	 */
	public const TERMS_TAXONOMY_ORDER_FLAG = 'divi_post_filter_orderby_taxonomy';

	/**
	 * Apply post-filter refinements to taxonomy term queries.
	 *
	 * Search fields match term name, slug, and description (partial match).
	 * Number fields with term count field value compare against `wp_term_taxonomy.count`.
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

		$clause_term_id_sets = [];

		foreach ( $clauses as $clause_key => $clause_value ) {
			if (
				PostFilterCustomFieldUtils::is_text_field_param_key( $clause_key )
				&& is_string( $clause_value )
				&& '' !== $clause_value
			) {
				$clause_term_id_sets[] = self::_query_term_ids_for_name_or_description_search( $base_query, $clause_value );
				continue;
			}

			if ( PostFilterCustomFieldUtils::is_number_field_param_key( $clause_key ) ) {
				$compare = PostFilterCustomFieldUtils::sanitize_compare_param_value( $params[ $clause_key . '_compare' ] ?? '=' );
				$numeric = PostFilterCustomFieldUtils::parse_numeric_filter_value( $clause_value );

				if ( null !== $compare && null !== $numeric ) {
					$clause_term_id_sets[] = self::_query_term_ids_for_count_comparison( $base_query, $numeric, $compare );
				}
			}
		}

		if ( empty( $clause_term_id_sets ) ) {
			return self::_apply_sort_params( $base_query, $params );
		}

		$matching_term_ids = self::_merge_term_id_sets( $clause_term_id_sets, $relation );

		$base_query = self::_restrict_terms_query_to_ids( $base_query, $matching_term_ids );

		return self::_apply_sort_params( $base_query, $params );
	}

	/**
	 * Filter WP_Term_Query clauses for taxonomy order-by refinements.
	 *
	 * @since ??
	 *
	 * @param array<string, string>     $clauses    Term query SQL clauses.
	 * @param array<int, string>        $taxonomies Taxonomies being queried.
	 * @param array<string, mixed>|null $args       WP_Term_Query arguments.
	 *
	 * @return array<string, string>
	 */
	public static function filter_terms_clauses_for_taxonomy_order( array $clauses, array $taxonomies, $args ): array {
		if ( empty( $args[ self::TERMS_TAXONOMY_ORDER_FLAG ] ) ) {
			return $clauses;
		}

		$direction = 'DESC';

		if ( isset( $args['order'] ) && 'ASC' === strtoupper( (string) $args['order'] ) ) {
			$direction = 'ASC';
		}

		$clauses['orderby'] = "ORDER BY tt.taxonomy {$direction}, t.name ASC";

		return $clauses;
	}

	/**
	 * Merge term ID sets using the configured filter relation.
	 *
	 * @since ??
	 *
	 * @param array<int, array<int, int>> $clause_term_id_sets Term ID sets from each active clause.
	 * @param string                      $relation            Filter relation (`and` or `or`).
	 *
	 * @return array<int, int>
	 */
	private static function _merge_term_id_sets( array $clause_term_id_sets, string $relation ): array {
		if ( 'or' === $relation && count( $clause_term_id_sets ) > 1 ) {
			$matching_term_ids = [];

			foreach ( $clause_term_id_sets as $term_id_set ) {
				$matching_term_ids = array_merge( $matching_term_ids, $term_id_set );
			}

			return array_values( array_unique( array_map( 'absint', $matching_term_ids ) ) );
		}

		$matching_term_ids = null;

		foreach ( $clause_term_id_sets as $term_id_set ) {
			if ( null === $matching_term_ids ) {
				$matching_term_ids = $term_id_set;
				continue;
			}

			$matching_term_ids = array_values( array_intersect( $matching_term_ids, $term_id_set ) );
		}

		return array_values( array_map( 'absint', $matching_term_ids ?? [] ) );
	}

	/**
	 * Restrict WP_Term_Query args to one explicit term ID set.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query Base query args.
	 * @param array<int, mixed>   $term_ids   Matching term IDs.
	 *
	 * @return array<string,mixed>
	 */
	private static function _restrict_terms_query_to_ids( array $base_query, array $term_ids ): array {
		unset( $base_query['s'], $base_query['search'] );

		if ( isset( $base_query['include'] ) && is_array( $base_query['include'] ) ) {
			$term_ids = array_values( array_intersect( $base_query['include'], $term_ids ) );
		}

		$base_query['include'] = ! empty( $term_ids ) ? $term_ids : [ 0 ];

		return $base_query;
	}

	/**
	 * Query term IDs whose name, slug, or description matches one search value.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query   Base loop query args.
	 * @param string              $search_value Submitted search value.
	 *
	 * @return array<int, int>
	 */
	private static function _query_term_ids_for_name_or_description_search( array $base_query, string $search_value ): array {
		$query_args = [
			'taxonomy'   => $base_query['taxonomy'] ?? '',
			'hide_empty' => $base_query['hide_empty'] ?? false,
			'fields'     => 'ids',
		];

		if ( isset( $base_query['parent'] ) ) {
			$query_args['parent'] = $base_query['parent'];
		}

		if ( isset( $base_query['include'] ) && is_array( $base_query['include'] ) ) {
			$query_args['include'] = $base_query['include'];
		}

		if ( isset( $base_query['exclude'] ) && is_array( $base_query['exclude'] ) ) {
			$query_args['exclude'] = $base_query['exclude'];
		}

		$query_args[ self::TERMS_SEARCH_QUERY_FLAG ] = true;

		$search_flag     = self::TERMS_SEARCH_QUERY_FLAG;
		$filter_callback = static function ( array $clauses, $taxonomies, $args ) use ( $search_value, $search_flag ): array {
			if ( empty( $args[ $search_flag ] ) ) {
				return $clauses;
			}

			global $wpdb;

			$like = '%' . $wpdb->esc_like( $search_value ) . '%';

			$clauses['where'] .= $wpdb->prepare(
				' AND (t.name LIKE %s OR t.slug LIKE %s OR tt.description LIKE %s)',
				$like,
				$like,
				$like
			);

			return $clauses;
		};

		add_filter( 'terms_clauses', $filter_callback, 10, 3 );

		try {
			$term_ids = get_terms( $query_args );
		} finally {
			remove_filter( 'terms_clauses', $filter_callback, 10 );
		}

		if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
			return [];
		}

		return array_map( 'absint', $term_ids );
	}

	/**
	 * Query term IDs whose taxonomy count matches one numeric comparison.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query  Base loop query args.
	 * @param float               $count_value Submitted numeric filter value.
	 * @param string              $compare     Sanitized comparison operator.
	 *
	 * @return array<int, int>
	 */
	private static function _query_term_ids_for_count_comparison( array $base_query, float $count_value, string $compare ): array {
		$query_args = [
			'taxonomy'   => $base_query['taxonomy'] ?? '',
			'hide_empty' => $base_query['hide_empty'] ?? false,
			'fields'     => 'ids',
		];

		if ( isset( $base_query['parent'] ) ) {
			$query_args['parent'] = $base_query['parent'];
		}

		if ( isset( $base_query['include'] ) && is_array( $base_query['include'] ) ) {
			$query_args['include'] = $base_query['include'];
		}

		if ( isset( $base_query['exclude'] ) && is_array( $base_query['exclude'] ) ) {
			$query_args['exclude'] = $base_query['exclude'];
		}

		$query_args[ self::TERMS_COUNT_QUERY_FLAG ] = [
			'value'   => $count_value,
			'compare' => $compare,
		];

		$count_flag      = self::TERMS_COUNT_QUERY_FLAG;
		$filter_callback = static function ( array $clauses, $taxonomies, $args ) use ( $count_flag ): array {
			if ( empty( $args[ $count_flag ] ) || ! is_array( $args[ $count_flag ] ) ) {
				return $clauses;
			}

			global $wpdb;

			$value   = $args[ $count_flag ]['value'] ?? null;
			$compare = PostFilterCustomFieldUtils::sanitize_compare_operator( (string) ( $args[ $count_flag ]['compare'] ?? '' ) );

			if ( null === $value || null === $compare ) {
				return $clauses;
			}

			$clauses['where'] .= $wpdb->prepare( " AND tt.count {$compare} %d", (int) $value );

			return $clauses;
		};

		add_filter( 'terms_clauses', $filter_callback, 10, 3 );

		try {
			$term_ids = get_terms( $query_args );
		} finally {
			remove_filter( 'terms_clauses', $filter_callback, 10 );
		}

		if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
			return [];
		}

		return array_map( 'absint', $term_ids );
	}

	/**
	 * Apply scalar and multiple-order sort params to term query args.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query Base query args.
	 * @param array<string,mixed> $params     Sanitized filter params.
	 *
	 * @return array<string,mixed>
	 */
	private static function _apply_sort_params( array $base_query, array $params ): array {
		$multiple_order_rows = $params['multiple-order'] ?? null;

		if ( is_array( $multiple_order_rows ) && ! empty( $multiple_order_rows ) ) {
			$first_row = $multiple_order_rows[0] ?? [];

			if ( ! is_array( $first_row ) ) {
				return $base_query;
			}

			$order_by_value = isset( $first_row['order_by'] ) ? (string) $first_row['order_by'] : 'default';
			$order_value    = isset( $first_row['order'] ) ? (string) $first_row['order'] : 'default';

			return self::_apply_scalar_sort( $base_query, $order_by_value, $order_value );
		}

		$order_by_value = $params['order_by'] ?? null;
		$order_value    = $params['order'] ?? null;

		return self::_apply_scalar_sort(
			$base_query,
			is_string( $order_by_value ) ? $order_by_value : 'default',
			is_string( $order_value ) ? $order_value : 'default'
		);
	}

	/**
	 * Apply one order-by and order pair to term query args.
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
	 * Apply order-by filter values to term query args.
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

		unset( $base_query[ self::TERMS_TAXONOMY_ORDER_FLAG ] );

		if ( 'taxonomy' === $order_by_value ) {
			unset( $base_query['orderby'] );
			$base_query[ self::TERMS_TAXONOMY_ORDER_FLAG ] = true;

			return $base_query;
		}

		$base_query['orderby'] = $order_by_value;

		return $base_query;
	}

	/**
	 * Return supported order-by values for terms-loop filter controls.
	 *
	 * @since ??
	 *
	 * @return array<int, string>
	 */
	private static function _get_valid_order_by_values(): array {
		return [
			'name',
			'description',
			'count',
			'taxonomy',
		];
	}
}
