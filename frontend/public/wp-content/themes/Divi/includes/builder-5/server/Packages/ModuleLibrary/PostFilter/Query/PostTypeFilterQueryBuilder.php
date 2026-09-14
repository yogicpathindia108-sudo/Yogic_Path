<?php
/**
 * Post-type filter query builder.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostFilter\Query;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\ModuleLibrary\PostFilterItem\PostFilterCustomFieldQueryBuilder;
use ET\Builder\Packages\ModuleLibrary\PostFilterItem\PostFilterCustomFieldUtils;
use WP_Query;

/**
 * Builds WP_Query args from post-filter params for post-type loops.
 *
 * Handles taxonomy, search, meta, author, and WooCommerce-specific clauses
 * (price range, stock status, product review rating).
 *
 * @since ??
 */
class PostTypeFilterQueryBuilder {

	/**
	 * Synthetic key for grouped range min/max params.
	 *
	 * @var string
	 */
	public const RANGE_CLAUSE_KEY = '__range';

	/**
	 * Apply post-filter refinements to post queries.
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

		if ( 'or' === $relation && count( $clauses ) > 1 ) {
			$base_query = self::_apply_or( $base_query, $clauses, $relation, $params );
		} else {
			$base_query = self::_apply_and( $base_query, $clauses, $relation, $params );
		}

		return self::_apply_sort_params( $base_query, $params );
	}

	/**
	 * Apply all active filter clauses cumulatively (AND semantics).
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query Base query args.
	 * @param array<string,mixed> $clauses    Normalized filter clauses.
	 * @param string              $relation   Filter relation.
	 * @param array<string,mixed> $params     Full sanitized filter params.
	 *
	 * @return array<string,mixed>
	 */
	private static function _apply_and( array $base_query, array $clauses, string $relation, array $params = [] ): array {
		$tax_clauses = [];

		foreach ( $clauses as $clause_key => $clause_value ) {
			if ( PostFilterCustomFieldUtils::is_text_field_param_key( $clause_key ) && is_string( $clause_value ) ) {
				$base_query = self::_apply_search_field_clause( $base_query, $clause_key, $clause_value, $params );
				continue;
			}

			if ( self::RANGE_CLAUSE_KEY === $clause_key && is_array( $clause_value ) ) {
				$base_query = self::_apply_price_range(
					$base_query,
					$clause_value['min'] ?? null,
					$clause_value['max'] ?? null
				);
				continue;
			}

			if ( 'product-status' === $clause_key ) {
				$base_query = self::_apply_stock_status_filter( $base_query, $clause_value );
				continue;
			}

			if ( 'product-review' === $clause_key ) {
				$base_query = self::_apply_rating_filter( $base_query, $clause_value );
				continue;
			}

			if ( 'author' === $clause_key ) {
				$author_ids = array_values(
					array_filter(
						array_map( 'absint', (array) $clause_value )
					)
				);

				if ( ! empty( $author_ids ) ) {
					$base_query['author__in'] = $author_ids;
				}

				continue;
			}

			if ( PostFilterCustomFieldUtils::is_companion_meta_field_param_key( $clause_key ) ) {
				$base_query = self::_apply_companion_meta_field_clause( $base_query, $clause_key, $clause_value, $params );
				continue;
			}

			if ( PostFilterCustomFieldUtils::is_custom_field_option( $clause_key ) ) {
				$base_query = self::_apply_custom_field_clause( $base_query, $clause_key, $clause_value, $params );
				continue;
			}

			if ( taxonomy_exists( $clause_key ) ) {
				$tax_clause = self::_build_taxonomy_clause( $clause_key, (array) $clause_value, $relation );

				if ( null !== $tax_clause ) {
					$tax_clauses[] = $tax_clause;
				}
			}
		}

		if ( ! empty( $tax_clauses ) ) {
			$base_query = self::_merge_tax_query( $base_query, $tax_clauses, 'AND' );
		}

		return $base_query;
	}

	/**
	 * Apply filter clauses with OR semantics by unioning matching post IDs.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query Base query args.
	 * @param array<string,mixed> $clauses    Normalized filter clauses.
	 * @param string              $relation   Filter relation.
	 * @param array<string,mixed> $params     Full sanitized filter params.
	 *
	 * @return array<string,mixed>
	 */
	private static function _apply_or( array $base_query, array $clauses, string $relation, array $params = [] ): array {
		$merged_post_ids = [];

		foreach ( $clauses as $clause_key => $clause_value ) {
			$post_ids        = self::_query_post_ids_for_clause( $base_query, $clause_key, $clause_value, $relation, $params );
			$merged_post_ids = array_merge( $merged_post_ids, $post_ids );
		}

		$merged_post_ids = array_values( array_unique( array_map( 'absint', $merged_post_ids ) ) );

		unset( $base_query['s'] );

		$base_query['post__in'] = ! empty( $merged_post_ids ) ? $merged_post_ids : [ 0 ];

		return $base_query;
	}

	/**
	 * Query post IDs that match one filter clause within the base loop context.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query   Base query args.
	 * @param string              $clause_key   Clause key.
	 * @param mixed               $clause_value Clause value.
	 * @param string              $relation     Filter relation.
	 * @param array<string,mixed> $params       Full sanitized filter params.
	 *
	 * @return array<int, int>
	 */
	private static function _query_post_ids_for_clause( array $base_query, string $clause_key, $clause_value, string $relation = 'or', array $params = [] ): array {
		$clause_query = self::_build_base_id_query_args( $base_query );

		if ( PostFilterCustomFieldUtils::is_text_field_param_key( $clause_key ) && is_string( $clause_value ) ) {
			$clause_query = self::_apply_search_field_clause( $clause_query, $clause_key, $clause_value, $params );
		} elseif ( self::RANGE_CLAUSE_KEY === $clause_key && is_array( $clause_value ) ) {
			$clause_query = self::_apply_price_range(
				$clause_query,
				$clause_value['min'] ?? null,
				$clause_value['max'] ?? null
			);
		} elseif ( 'product-status' === $clause_key ) {
			$clause_query = self::_apply_stock_status_filter( $clause_query, $clause_value );
		} elseif ( 'product-review' === $clause_key ) {
			$clause_query = self::_apply_rating_filter( $clause_query, $clause_value );
		} elseif ( 'author' === $clause_key ) {
			$author_ids = array_values(
				array_filter(
					array_map( 'absint', (array) $clause_value )
				)
			);

			if ( empty( $author_ids ) ) {
				return [];
			}

			$clause_query['author__in'] = $author_ids;
		} elseif ( PostFilterCustomFieldUtils::is_companion_meta_field_param_key( $clause_key ) ) {
			$clause_query = self::_apply_companion_meta_field_clause( $clause_query, $clause_key, $clause_value, $params );
		} elseif ( PostFilterCustomFieldUtils::is_custom_field_option( $clause_key ) ) {
			$clause_query = self::_apply_custom_field_clause( $clause_query, $clause_key, $clause_value, $params );
		} elseif ( taxonomy_exists( $clause_key ) ) {
			$tax_clause = self::_build_taxonomy_clause( $clause_key, (array) $clause_value, $relation );

			if ( null === $tax_clause ) {
				return [];
			}

			$clause_query['tax_query'] = [ $tax_clause ];
		} else {
			return [];
		}

		$query = new WP_Query( $clause_query );

		return array_map( 'absint', $query->posts );
	}

	/**
	 * Build base WP_Query args for ID-only clause queries.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query Base loop query args.
	 *
	 * @return array<string,mixed>
	 */
	private static function _build_base_id_query_args( array $base_query ): array {
		$clause_query = [
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'post_status'    => $base_query['post_status'] ?? 'publish',
			'post_type'      => $base_query['post_type'] ?? 'post',
		];

		if ( isset( $base_query['post__in'] ) ) {
			$clause_query['post__in'] = $base_query['post__in'];
		}

		if ( isset( $base_query['post__not_in'] ) ) {
			$clause_query['post__not_in'] = $base_query['post__not_in'];
		}

		return $clause_query;
	}

	/**
	 * Apply scalar and multiple-order sort params to post query args.
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
			return self::_apply_multiple_order( $base_query, $multiple_order_rows );
		}

		$post_type_for_order = $base_query['post_type'] ?? 'post';
		$order_by_value      = $params['order_by'] ?? null;
		$order_value         = $params['order'] ?? null;

		if ( is_string( $order_by_value ) ) {
			$base_query = self::_apply_order_by( $base_query, $order_by_value, $post_type_for_order );
		}

		if ( is_string( $order_value ) ) {
			$base_query = PostFilterQueryHelpers::apply_order( $base_query, $order_value );
		}

		return $base_query;
	}

	/**
	 * Apply order-by filter values to post query args.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query          Base query args.
	 * @param string              $order_by_value      Order-by slug from filter control.
	 * @param mixed               $post_type_for_order Resolved post type for whitelist checks.
	 *
	 * @return array<string,mixed>
	 */
	private static function _apply_order_by( array $base_query, string $order_by_value, $post_type_for_order ): array {
		$order_by_value = PostFilterCustomFieldUtils::sanitize_clause_key( $order_by_value );

		if ( 'default' === $order_by_value || '' === $order_by_value ) {
			return $base_query;
		}

		if ( PostFilterCustomFieldUtils::is_custom_field_option( $order_by_value ) ) {
			return PostFilterCustomFieldQueryBuilder::apply_order_by_to_query( $base_query, $order_by_value );
		}

		$valid_order_by = self::_get_valid_order_by_values( $post_type_for_order );

		if ( in_array( $order_by_value, $valid_order_by, true ) ) {
			$base_query['orderby'] = $order_by_value;
		}

		return $base_query;
	}

	/**
	 * Apply multiple-order rows to post query args.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed>                                   $base_query Base query args.
	 * @param array<int, array{order_by?: string, order?: string}>  $rows       Multiple-order rows.
	 *
	 * @return array<string,mixed>
	 */
	private static function _apply_multiple_order( array $base_query, array $rows ): array {
		$post_type_for_order = $base_query['post_type'] ?? 'post';
		$orderby             = [];
		$meta_order_clauses  = [];
		$clause_index        = 0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$order_by_value = isset( $row['order_by'] ) ? PostFilterCustomFieldUtils::sanitize_clause_key( (string) $row['order_by'] ) : 'default';
			$order_value    = isset( $row['order'] ) ? sanitize_key( (string) $row['order'] ) : 'default';

			if ( 'default' === $order_by_value ) {
				continue;
			}

			$direction = 'DESC';

			if ( 'ascending' === $order_value ) {
				$direction = 'ASC';
			} elseif ( 'default' !== $order_value && 'descending' !== $order_value ) {
				$direction = 'DESC';
			} elseif ( 'default' === $order_value ) {
				$direction = isset( $base_query['order'] ) && 'ASC' === strtoupper( (string) $base_query['order'] ) ? 'ASC' : 'DESC';
			}

			if ( PostFilterCustomFieldUtils::is_custom_field_option( $order_by_value ) ) {
				$resolved_meta_key = PostFilterCustomFieldUtils::resolve_meta_key( $order_by_value, '' );

				if ( '' === $resolved_meta_key ) {
					continue;
				}

				$clause_name = 'post_filter_order_' . $clause_index;
				$clause_index++;

				$meta_order_clauses[ $clause_name ] = [
					'key'     => $resolved_meta_key,
					'compare' => 'EXISTS',
					'type'    => PostFilterCustomFieldUtils::is_numeric_meta_key( $resolved_meta_key ) ? 'NUMERIC' : 'CHAR',
				];

				$orderby[ $clause_name ] = $direction;

				continue;
			}

			$valid_order_by = self::_get_valid_order_by_values( $post_type_for_order );

			if ( in_array( $order_by_value, $valid_order_by, true ) ) {
				$orderby[ $order_by_value ] = $direction;
			}
		}

		if ( empty( $orderby ) ) {
			return $base_query;
		}

		if ( ! empty( $meta_order_clauses ) ) {
			if ( ! isset( $base_query['meta_query'] ) || ! is_array( $base_query['meta_query'] ) ) {
				$base_query['meta_query'] = [];
			}

			foreach ( $meta_order_clauses as $clause_name => $clause ) {
				$base_query['meta_query'][ $clause_name ] = $clause;
			}

			unset( $base_query['meta_key'] );
		}

		$base_query['orderby'] = $orderby;
		unset( $base_query['order'] );

		return $base_query;
	}

	/**
	 * Return supported order-by values for one post type context.
	 *
	 * @since ??
	 *
	 * @param mixed $post_type_for_order Resolved post type.
	 *
	 * @return array<int, string>
	 */
	private static function _get_valid_order_by_values( $post_type_for_order ): array {
		$valid_order_by = [
			'none',
			'ID',
			'author',
			'title',
			'name',
			'type',
			'date',
			'modified',
			'parent',
			'rand',
			'comment_count',
			'menu_order',
		];

		$post_types = is_array( $post_type_for_order ) ? $post_type_for_order : [ $post_type_for_order ];

		if ( in_array( 'product', $post_types, true ) && function_exists( 'WC' ) ) {
			$valid_order_by[] = 'price';
			$valid_order_by[] = 'popularity';
			$valid_order_by[] = 'rating';
		}

		return $valid_order_by;
	}

	/**
	 * Apply WooCommerce stock status refinements.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query   Base query args.
	 * @param mixed               $status_value Stock status value(s).
	 *
	 * @return array<string,mixed>
	 */
	private static function _apply_stock_status_filter( array $base_query, $status_value ): array {
		$allowed_statuses = function_exists( 'wc_get_product_stock_status_options' )
			? array_keys( wc_get_product_stock_status_options() )
			: [];
		$status_values    = is_array( $status_value ) ? $status_value : [ $status_value ];
		$status_slug      = '';

		foreach ( $status_values as $candidate_status ) {
			$sanitized_status = sanitize_key( (string) $candidate_status );

			if ( in_array( $sanitized_status, $allowed_statuses, true ) ) {
				$status_slug = $sanitized_status;
				break;
			}
		}

		if ( '' === $status_slug ) {
			return $base_query;
		}

		if ( ! isset( $base_query['meta_query'] ) || ! is_array( $base_query['meta_query'] ) ) {
			$base_query['meta_query'] = [];
		}

		$base_query['meta_query'][] = [
			'key'     => '_stock_status',
			'value'   => $status_slug,
			'compare' => '=',
		];

		return $base_query;
	}

	/**
	 * Apply WooCommerce product review rating refinements.
	 *
	 * Uses WooCommerce `product_visibility` taxonomy terms (`rated-1` through `rated-5`),
	 * matching native WooCommerce rating filter behavior.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query   Base query args.
	 * @param mixed               $rating_value Selected rating value.
	 *
	 * @return array<string,mixed>
	 */
	private static function _apply_rating_filter( array $base_query, $rating_value ): array {
		if ( ! function_exists( 'wc_get_product_visibility_term_ids' ) ) {
			return $base_query;
		}

		$rating_values = is_array( $rating_value ) ? $rating_value : [ $rating_value ];
		$rating        = 0;

		foreach ( $rating_values as $candidate_rating ) {
			$sanitized_rating = absint( $candidate_rating );

			if ( 1 <= $sanitized_rating && 5 >= $sanitized_rating ) {
				$rating = $sanitized_rating;
				break;
			}
		}

		if ( 0 === $rating ) {
			return $base_query;
		}

		$product_visibility_terms = wc_get_product_visibility_term_ids();
		$term_id                  = $product_visibility_terms[ 'rated-' . $rating ] ?? null;

		if ( empty( $term_id ) ) {
			return $base_query;
		}

		if ( ! isset( $base_query['tax_query'] ) || ! is_array( $base_query['tax_query'] ) ) {
			$base_query['tax_query'] = [];
		}

		$base_query['tax_query'][] = [
			'taxonomy'      => 'product_visibility',
			'field'         => 'term_taxonomy_id',
			'terms'         => [ $term_id ],
			'operator'      => 'IN',
			'rating_filter' => true,
		];

		return $base_query;
	}

	/**
	 * Apply WooCommerce price range refinements.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query Base query args.
	 * @param string|null         $range_min  Minimum submitted value.
	 * @param string|null         $range_max  Maximum submitted value.
	 *
	 * @return array<string,mixed>
	 */
	private static function _apply_price_range( array $base_query, $range_min, $range_max ): array {
		$min_value = self::_parse_numeric_filter_value( $range_min );
		$max_value = self::_parse_numeric_filter_value( $range_max );

		if ( null === $min_value && null === $max_value ) {
			return $base_query;
		}

		$price_clause = [
			'key'     => '_price',
			'type'    => 'NUMERIC',
			'compare' => 'BETWEEN',
		];

		if ( null !== $min_value && null !== $max_value ) {
			$price_clause['value'] = [ $min_value, $max_value ];
		} elseif ( null !== $min_value ) {
			$price_clause['compare'] = '>=';
			$price_clause['value']   = $min_value;
		} else {
			$price_clause['compare'] = '<=';
			$price_clause['value']   = $max_value;
		}

		if ( ! isset( $base_query['meta_query'] ) || ! is_array( $base_query['meta_query'] ) ) {
			$base_query['meta_query'] = [];
		}

		$base_query['meta_query'][] = $price_clause;

		return $base_query;
	}

	/**
	 * Parse numeric digits from a submitted display value.
	 *
	 * @since ??
	 *
	 * @param mixed $value Submitted filter value.
	 *
	 * @return float|null
	 */
	private static function _parse_numeric_filter_value( $value ): ?float {
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			return null;
		}

		$digits_only = preg_replace( '/[^0-9.]/', '', (string) $value );

		if ( null === $digits_only || '' === $digits_only ) {
			return null;
		}

		return (float) $digits_only;
	}

	/**
	 * Apply one search field clause using companion target metadata params.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query   Base query args.
	 * @param string              $clause_key   Search field param key.
	 * @param string              $clause_value Submitted search value.
	 * @param array<string,mixed> $params       Full sanitized filter params.
	 *
	 * @return array<string,mixed>
	 */
	private static function _apply_search_field_clause( array $base_query, string $clause_key, string $clause_value, array $params ): array {
		if ( '' === $clause_value ) {
			return $base_query;
		}

		$target              = $params[ $clause_key . '_target' ] ?? PostFilterCustomFieldUtils::SEARCH_TARGET_POST_CONTENT;
		$target_meta         = $params[ $clause_key . '_target_meta' ] ?? '';
		$has_post_content    = PostFilterCustomFieldUtils::SEARCH_TARGET_POST_CONTENT === $target || '' === $target;

		if ( $has_post_content ) {
			$base_query['s'] = $clause_value;

			return $base_query;
		}

		if ( PostFilterCustomFieldUtils::is_custom_field_option( (string) $target ) ) {
			$meta_key = self::_resolve_search_meta_key( (string) $target, $target_meta );

			if ( '' !== $meta_key ) {
				$base_query = PostFilterCustomFieldQueryBuilder::apply_search_meta_filter( $base_query, $meta_key, $clause_value );
			}
		}

		return $base_query;
	}

	/**
	 * Resolve a post meta key from one search target option and companion meta param.
	 *
	 * @since ??
	 *
	 * @param string $target_option Search target option value.
	 * @param mixed  $target_meta   Companion `_meta` param value.
	 *
	 * @return string
	 */
	private static function _resolve_search_meta_key( string $target_option, $target_meta ): string {
		if (
			'' === $target_option
			|| ! PostFilterCustomFieldUtils::is_custom_field_option( $target_option )
		) {
			return '';
		}

		if ( is_string( $target_meta ) && '' !== $target_meta ) {
			return sanitize_text_field( $target_meta );
		}

		return PostFilterCustomFieldUtils::resolve_meta_key( $target_option, '' );
	}

	/**
	 * Restrict query args to one explicit post ID set.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query Base query args.
	 * @param array<int, mixed>   $post_ids   Matching post IDs.
	 *
	 * @return array<string,mixed>
	 */
	private static function _restrict_query_to_post_ids( array $base_query, array $post_ids ): array {
		$post_ids = array_values(
			array_filter(
				array_map( 'absint', $post_ids )
			)
		);

		unset( $base_query['s'] );

		if ( isset( $base_query['post__in'] ) && is_array( $base_query['post__in'] ) ) {
			$post_ids = array_values( array_intersect( $base_query['post__in'], $post_ids ) );
		}

		$base_query['post__in'] = ! empty( $post_ids ) ? $post_ids : [ 0 ];

		return $base_query;
	}

	/**
	 * Apply one companion-meta field clause using `_meta` and `_compare` params.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query   Base query args.
	 * @param string              $clause_key   Module-scoped field param key.
	 * @param mixed               $clause_value Submitted filter value.
	 * @param array<string,mixed> $params       Full sanitized filter params.
	 *
	 * @return array<string,mixed>
	 */
	private static function _apply_companion_meta_field_clause( array $base_query, string $clause_key, $clause_value, array $params ): array {
		$meta_key = $params[ $clause_key . '_meta' ] ?? '';
		$meta_key = is_string( $meta_key ) ? sanitize_key( $meta_key ) : '';

		if ( '' === $meta_key ) {
			return $base_query;
		}

		$compare_value = PostFilterCustomFieldUtils::sanitize_compare_param_value( $params[ $clause_key . '_compare' ] ?? '=' );

		if ( null === $compare_value ) {
			$compare_value = '=';
		}

		$date_filter_field_type = PostFilterCustomFieldUtils::resolve_date_filter_field_type_from_param_key( $clause_key );

		if ( null !== $date_filter_field_type ) {
			if ( PostFilterCustomFieldUtils::is_post_date_field_value_option( $meta_key ) ) {
				return PostFilterCustomFieldQueryBuilder::apply_date_post_column_filter(
					$base_query,
					$meta_key,
					$clause_value,
					$compare_value,
					$date_filter_field_type
				);
			}

			return PostFilterCustomFieldQueryBuilder::apply_date_meta_filter(
				$base_query,
				$meta_key,
				$clause_value,
				$compare_value,
				$date_filter_field_type
			);
		}

		if ( PostFilterCustomFieldUtils::is_number_field_param_key( $clause_key ) ) {
			return PostFilterCustomFieldQueryBuilder::apply_numeric_meta_filter( $base_query, $meta_key, $clause_value, $compare_value );
		}

		return PostFilterCustomFieldQueryBuilder::apply_char_meta_filter( $base_query, $meta_key, $clause_value, $compare_value );
	}

	/**
	 * Apply one custom field clause, using numeric comparison when a compare param is present.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query   Base query args.
	 * @param string              $clause_key   Query param clause key.
	 * @param mixed               $clause_value Submitted filter value(s).
	 * @param array<string,mixed> $params       Full sanitized filter params.
	 *
	 * @return array<string,mixed>
	 */
	private static function _apply_custom_field_clause( array $base_query, string $clause_key, $clause_value, array $params ): array {
		$compare_key   = $clause_key . '_compare';
		$compare_value = $params[ $compare_key ] ?? null;

		if ( is_string( $compare_value ) && '' !== $compare_value ) {
			return PostFilterCustomFieldQueryBuilder::apply_numeric_custom_field_filter(
				$base_query,
				$clause_key,
				$clause_value,
				$compare_value
			);
		}

		return PostFilterCustomFieldQueryBuilder::apply_custom_field_filter( $base_query, $clause_key, $clause_value );
	}

	/**
	 * Build one taxonomy clause for submitted term slugs.
	 *
	 * Multiple values within one taxonomy respect relation: `and` requires every
	 * selected term (`operator` AND), `or` matches any selected term (`operator` IN).
	 *
	 * @since ??
	 *
	 * @param string            $taxonomy    Taxonomy slug.
	 * @param array<int, mixed> $term_values Raw submitted term values.
	 * @param string            $relation    Filter relation (`and` or `or`).
	 *
	 * @return array<string, mixed>|null
	 */
	private static function _build_taxonomy_clause( string $taxonomy, array $term_values, string $relation ): ?array {
		$term_slugs = array_values(
			array_filter(
				array_map( 'sanitize_title', $term_values )
			)
		);

		if ( empty( $term_slugs ) ) {
			return null;
		}

		$operator = ( 'and' === $relation && count( $term_slugs ) > 1 ) ? 'AND' : 'IN';

		return [
			'taxonomy' => sanitize_key( $taxonomy ),
			'field'    => 'slug',
			'terms'    => $term_slugs,
			'operator' => $operator,
		];
	}

	/**
	 * Merge filter tax clauses into existing query tax_query args.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed>             $base_query  Base query args.
	 * @param array<int, array<string,mixed>> $tax_clauses Taxonomy clauses to append.
	 * @param string                          $relation    Relation between new clauses.
	 *
	 * @return array<string,mixed>
	 */
	private static function _merge_tax_query( array $base_query, array $tax_clauses, string $relation ): array {
		if ( empty( $tax_clauses ) ) {
			return $base_query;
		}

		$new_tax_query = 1 === count( $tax_clauses )
			? $tax_clauses
			: array_merge( [ 'relation' => $relation ], $tax_clauses );

		if ( empty( $base_query['tax_query'] ) || ! is_array( $base_query['tax_query'] ) ) {
			$base_query['tax_query'] = $new_tax_query;

			return $base_query;
		}

		$base_query['tax_query'] = [
			'relation' => 'AND',
			$base_query['tax_query'],
			$new_tax_query,
		];

		return $base_query;
	}
}
