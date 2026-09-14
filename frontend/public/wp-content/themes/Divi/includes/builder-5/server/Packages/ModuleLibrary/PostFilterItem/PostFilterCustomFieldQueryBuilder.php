<?php
/**
 * Post Filter Item custom field query builder.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostFilterItem;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentACFUtils;

/**
 * Applies custom field filter clauses to WP_Query, WP_Term_Query, and WP_User_Query args.
 *
 * All methods accept and return a plain query-args array so callers can chain
 * refinements without coupling to a specific query object type.
 *
 * @since ??
 */
class PostFilterCustomFieldQueryBuilder {
	/**
	 * Apply a custom-field order-by value to WP_Query args.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query     Base query args.
	 * @param string              $order_by_value Sanitized order-by slug.
	 *
	 * @return array<string,mixed>
	 */
	public static function apply_order_by_to_query( array $base_query, string $order_by_value ): array {
		if ( ! PostFilterCustomFieldUtils::is_custom_field_option( $order_by_value ) ) {
			return $base_query;
		}

		$meta_key = PostFilterCustomFieldUtils::resolve_meta_key( $order_by_value, '' );

		if ( '' === $meta_key ) {
			return $base_query;
		}

		$base_query['meta_key'] = $meta_key;
		$base_query['orderby']  = PostFilterCustomFieldUtils::is_numeric_meta_key( $meta_key ) ? 'meta_value_num' : 'meta_value';

		return $base_query;
	}

	/**
	 * Apply a custom field meta_query refinement to post query args.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query   Base query args.
	 * @param string              $clause_key   Query param clause key.
	 * @param mixed               $clause_value Submitted filter value(s).
	 *
	 * @return array<string,mixed>
	 */
	public static function apply_custom_field_filter( array $base_query, string $clause_key, $clause_value ): array {
		$meta_key = PostFilterCustomFieldUtils::resolve_meta_key_from_clause_key( $clause_key );

		if ( '' === $meta_key ) {
			return $base_query;
		}

		$values = array_values(
			array_filter(
				array_map(
					static function ( $value ) {
						return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
					},
					(array) $clause_value
				),
				static function ( $value ) {
					return '' !== $value;
				}
			)
		);

		if ( empty( $values ) ) {
			return $base_query;
		}

		$meta_type = PostFilterCustomFieldUtils::is_numeric_meta_key( $meta_key ) ? 'NUMERIC' : 'CHAR';

		$meta_clause = [
			'key'     => $meta_key,
			'type'    => $meta_type,
			'compare' => 1 === count( $values ) ? '=' : 'IN',
			'value'   => 1 === count( $values ) ? $values[0] : $values,
		];

		if ( ! isset( $base_query['meta_query'] ) || ! is_array( $base_query['meta_query'] ) ) {
			$base_query['meta_query'] = [];
		}

		$base_query['meta_query'][] = $meta_clause;

		return $base_query;
	}

	/**
	 * Apply a numeric custom field meta_query refinement with a comparison operator.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query     Base query args.
	 * @param string              $clause_key     Query param clause key.
	 * @param mixed               $clause_value   Submitted numeric filter value.
	 * @param string              $compare_value  Comparison operator from request params.
	 *
	 * @return array<string,mixed>
	 */
	public static function apply_numeric_custom_field_filter( array $base_query, string $clause_key, $clause_value, string $compare_value ): array {
		$meta_key = PostFilterCustomFieldUtils::resolve_meta_key_from_clause_key( $clause_key );

		if ( '' === $meta_key ) {
			return $base_query;
		}

		return self::apply_numeric_meta_filter( $base_query, $meta_key, $clause_value, $compare_value );
	}

	/**
	 * Apply a numeric meta_query refinement for one resolved meta key.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query     Base query args.
	 * @param string              $meta_key       Resolved post meta key.
	 * @param mixed               $clause_value   Submitted numeric filter value.
	 * @param string              $compare_value  Comparison operator from request params.
	 *
	 * @return array<string,mixed>
	 */
	public static function apply_numeric_meta_filter( array $base_query, string $meta_key, $clause_value, string $compare_value ): array {
		if ( '' === $meta_key ) {
			return $base_query;
		}

		$compare = PostFilterCustomFieldUtils::sanitize_compare_operator( $compare_value );

		if ( null === $compare ) {
			return $base_query;
		}

		if ( PostFilterCustomFieldUtils::is_exists_compare_operator( $compare ) ) {
			return PostFilterCustomFieldUtils::append_exists_meta_query_clause( $base_query, $meta_key, $compare, 'NUMERIC' );
		}

		$raw_value = is_array( $clause_value ) ? ( $clause_value[0] ?? '' ) : $clause_value;
		$numeric   = PostFilterCustomFieldUtils::parse_numeric_filter_value( $raw_value );

		if ( null === $numeric ) {
			return $base_query;
		}

		$meta_clause = [
			'key'     => $meta_key,
			'type'    => 'NUMERIC',
			'compare' => $compare,
			'value'   => $numeric,
		];

		if ( ! isset( $base_query['meta_query'] ) || ! is_array( $base_query['meta_query'] ) ) {
			$base_query['meta_query'] = [];
		}

		$base_query['meta_query'][] = $meta_clause;

		return $base_query;
	}

	/**
	 * Apply a meta_query refinement for one resolved meta key and comparison operator.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query     Base query args.
	 * @param string              $meta_key       Resolved post meta key.
	 * @param mixed               $clause_value   Submitted filter value.
	 * @param string              $compare_value  Comparison operator from request params.
	 *
	 * @return array<string,mixed>
	 */
	public static function apply_char_meta_filter( array $base_query, string $meta_key, $clause_value, string $compare_value ): array {
		$meta_key = sanitize_text_field( $meta_key );

		if ( '' === $meta_key ) {
			return $base_query;
		}

		$compare = PostFilterCustomFieldUtils::sanitize_compare_operator( $compare_value );

		if ( null === $compare ) {
			return $base_query;
		}

		if ( PostFilterCustomFieldUtils::is_exists_compare_operator( $compare ) ) {
			$meta_type = PostFilterCustomFieldUtils::is_numeric_meta_key( $meta_key ) ? 'NUMERIC' : 'CHAR';

			return PostFilterCustomFieldUtils::append_exists_meta_query_clause( $base_query, $meta_key, $compare, $meta_type );
		}

		$values = array_values(
			array_filter(
				array_map(
					static function ( $value ) {
						return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
					},
					(array) $clause_value
				),
				static function ( $value ) {
					return '' !== $value;
				}
			)
		);

		if ( empty( $values ) ) {
			return $base_query;
		}

		if ( PostFilterCustomFieldUtils::is_list_compare_operator( $compare ) && 1 === count( $values ) && str_contains( $values[0], ',' ) ) {
			$values = array_values(
				array_filter(
					array_map(
						static function ( $value ) {
							return sanitize_text_field( trim( (string) $value ) );
						},
						explode( ',', $values[0] )
					)
				)
			);
		}

		if ( empty( $values ) ) {
			return $base_query;
		}

		$meta_type        = PostFilterCustomFieldUtils::is_numeric_meta_key( $meta_key ) ? 'NUMERIC' : 'CHAR';
		$resolved_compare = PostFilterCustomFieldUtils::is_list_compare_operator( $compare )
			? $compare
			: ( 1 < count( $values ) ? 'IN' : $compare );
		$meta_clause      = [
			'key'     => $meta_key,
			'type'    => $meta_type,
			'compare' => $resolved_compare,
			'value'   => 1 === count( $values ) ? $values[0] : $values,
		];

		if ( ! isset( $base_query['meta_query'] ) || ! is_array( $base_query['meta_query'] ) ) {
			$base_query['meta_query'] = [];
		}

		$base_query['meta_query'][] = $meta_clause;

		return $base_query;
	}

	/**
	 * Apply a typed date/datetime/time meta_query refinement for one resolved meta key.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query        Base query args.
	 * @param string              $meta_key          Resolved post meta key.
	 * @param mixed               $clause_value      Submitted picker value.
	 * @param string              $compare_value     Comparison operator from request params.
	 * @param string              $filter_field_type Post-filter field type.
	 *
	 * @return array<string,mixed>
	 */
	public static function apply_date_meta_filter(
		array $base_query,
		string $meta_key,
		$clause_value,
		string $compare_value,
		string $filter_field_type
	): array {
		if ( '' === $meta_key || ! PostFilterCustomFieldUtils::is_date_filter_field_type( $filter_field_type ) ) {
			return $base_query;
		}

		if ( ! PostFilterCustomFieldUtils::is_meta_key_compatible_with_date_filter_field_type( $meta_key, $filter_field_type ) ) {
			return $base_query;
		}

		$compare = PostFilterCustomFieldUtils::sanitize_compare_operator( $compare_value );

		if ( null === $compare ) {
			return $base_query;
		}

		if ( PostFilterCustomFieldUtils::is_exists_compare_operator( $compare ) ) {
			$meta_type = PostFilterCustomFieldUtils::resolve_meta_query_type_for_filter_field_type( $filter_field_type );

			return PostFilterCustomFieldUtils::append_exists_meta_query_clause( $base_query, $meta_key, $compare, $meta_type );
		}

		$values = self::_extract_scalar_filter_values( $clause_value );

		if ( empty( $values ) ) {
			return $base_query;
		}

		$use_list_matching = PostFilterCustomFieldUtils::is_list_compare_operator( $compare ) || count( $values ) > 1;

		if ( $use_list_matching ) {
			$normalized_values = [];

			foreach ( $values as $raw_value ) {
				$normalized_value = PostFilterCustomFieldUtils::normalize_html_picker_value_to_acf_storage(
					$raw_value,
					$filter_field_type
				);

				if ( '' !== $normalized_value ) {
					$normalized_values[] = $normalized_value;
				}
			}

			if ( empty( $normalized_values ) ) {
				return $base_query;
			}

			$resolved_compare = PostFilterCustomFieldUtils::is_list_compare_operator( $compare )
				? $compare
				: ( 1 < count( $normalized_values ) ? 'IN' : $compare );
			$meta_clause      = [
				'key'     => $meta_key,
				'type'    => PostFilterCustomFieldUtils::resolve_meta_query_type_for_filter_field_type( $filter_field_type ),
				'compare' => $resolved_compare,
				'value'   => 1 === count( $normalized_values ) ? $normalized_values[0] : $normalized_values,
			];

			if ( ! isset( $base_query['meta_query'] ) || ! is_array( $base_query['meta_query'] ) ) {
				$base_query['meta_query'] = [];
			}

			$base_query['meta_query'][] = $meta_clause;

			return $base_query;
		}

		$raw_value = $values[0];

		$normalized_value = PostFilterCustomFieldUtils::normalize_html_picker_value_to_acf_storage(
			$raw_value,
			$filter_field_type
		);

		if ( '' === $normalized_value ) {
			return $base_query;
		}

		$meta_clause = [
			'key'     => $meta_key,
			'type'    => PostFilterCustomFieldUtils::resolve_meta_query_type_for_filter_field_type( $filter_field_type ),
			'compare' => $compare,
			'value'   => $normalized_value,
		];

		if ( ! isset( $base_query['meta_query'] ) || ! is_array( $base_query['meta_query'] ) ) {
			$base_query['meta_query'] = [];
		}

		$base_query['meta_query'][] = $meta_clause;

		return $base_query;
	}

	/**
	 * Apply a WP_Date_Query refinement for published or modified post columns.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query        Base query args.
	 * @param string              $option_key        Selected post date field value option key.
	 * @param mixed               $clause_value      Submitted picker value.
	 * @param string              $compare_value     Comparison operator from request params.
	 * @param string              $filter_field_type Post-filter field type.
	 *
	 * @return array<string,mixed>
	 */
	public static function apply_date_post_column_filter(
		array $base_query,
		string $option_key,
		$clause_value,
		string $compare_value,
		string $filter_field_type
	): array {
		if ( ! PostFilterCustomFieldUtils::is_post_date_field_value_option( $option_key ) ) {
			return $base_query;
		}

		$column = PostFilterCustomFieldUtils::resolve_post_date_column_from_option_key( $option_key );

		if ( '' === $column ) {
			return $base_query;
		}

		$compare = PostFilterCustomFieldUtils::sanitize_compare_operator( $compare_value );

		if ( null === $compare ) {
			return $base_query;
		}

		$values = self::_extract_scalar_filter_values( $clause_value );

		if ( empty( $values ) ) {
			return $base_query;
		}

		$use_list_matching = PostFilterCustomFieldUtils::is_list_compare_operator( $compare ) || count( $values ) > 1;

		if ( $use_list_matching ) {
			$or_clauses = [];

			foreach ( $values as $raw_value ) {
				$date_components = PostFilterCustomFieldUtils::parse_html_picker_value_to_date_query_components(
					$raw_value,
					$filter_field_type
				);

				if ( empty( $date_components ) ) {
					continue;
				}

				$clause = PostFilterCustomFieldUtils::build_post_column_date_query_clause(
					$column,
					'=',
					$date_components,
					$filter_field_type
				);

				if ( ! empty( $clause ) ) {
					$or_clauses[] = $clause;
				}
			}

			if ( empty( $or_clauses ) ) {
				return $base_query;
			}

			$date_clause = 1 === count( $or_clauses )
				? $or_clauses[0]
				: array_merge( [ 'relation' => 'OR' ], $or_clauses );
		} else {
			$date_components = PostFilterCustomFieldUtils::parse_html_picker_value_to_date_query_components(
				$values[0],
				$filter_field_type
			);

			if ( empty( $date_components ) ) {
				return $base_query;
			}

			$date_clause = PostFilterCustomFieldUtils::build_post_column_date_query_clause(
				$column,
				$compare,
				$date_components,
				$filter_field_type
			);

			if ( empty( $date_clause ) ) {
				return $base_query;
			}
		}

		if ( ! isset( $base_query['date_query'] ) || ! is_array( $base_query['date_query'] ) ) {
			$base_query['date_query'] = [];
		}

		$base_query['date_query'][] = $date_clause;

		return $base_query;
	}

	/**
	 * Normalize submitted filter values into a trimmed scalar list.
	 *
	 * @since ??
	 *
	 * @param mixed $clause_value Submitted filter value.
	 *
	 * @return array<int, string>
	 */
	private static function _extract_scalar_filter_values( $clause_value ): array {
		return array_values(
			array_filter(
				array_map(
					static function ( $value ) {
						return is_scalar( $value ) ? trim( (string) $value ) : '';
					},
					(array) $clause_value
				),
				static function ( $value ) {
					return '' !== $value;
				}
			)
		);
	}

	/**
	 * Apply a partial-match meta_query refinement for one search field target.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query   Base query args.
	 * @param string              $meta_key     Resolved post meta key.
	 * @param string              $search_value Submitted search value.
	 *
	 * @return array<string,mixed>
	 */
	public static function apply_search_meta_filter( array $base_query, string $meta_key, string $search_value ): array {
		$meta_key = sanitize_text_field( $meta_key );

		if ( '' === $meta_key || '' === $search_value ) {
			return $base_query;
		}

		$meta_keys = DynamicContentACFUtils::resolve_search_meta_keys( $meta_key );

		return self::apply_search_meta_keys_filter( $base_query, $meta_keys, $search_value );
	}

	/**
	 * Apply a partial-match meta_query refinement across one or more meta keys (OR relation).
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query   Base query args.
	 * @param array<int, string>  $meta_keys    Resolved post meta keys.
	 * @param string              $search_value Submitted search value.
	 *
	 * @return array<string,mixed>
	 */
	public static function apply_search_meta_keys_filter( array $base_query, array $meta_keys, string $search_value ): array {
		$meta_keys = array_values(
			array_filter(
				array_map(
					static function ( $meta_key ): string {
						return sanitize_text_field( (string) $meta_key );
					},
					$meta_keys
				)
			)
		);

		if ( empty( $meta_keys ) || '' === $search_value ) {
			return $base_query;
		}

		$search_value = sanitize_text_field( $search_value );

		if ( ! isset( $base_query['meta_query'] ) || ! is_array( $base_query['meta_query'] ) ) {
			$base_query['meta_query'] = [];
		}

		if ( 1 === count( $meta_keys ) ) {
			$base_query['meta_query'][] = [
				'key'     => $meta_keys[0],
				'value'   => $search_value,
				'compare' => 'LIKE',
			];

			return $base_query;
		}

		$or_clauses = [
			'relation' => 'OR',
		];

		foreach ( $meta_keys as $meta_key ) {
			$or_clauses[] = [
				'key'     => $meta_key,
				'value'   => $search_value,
				'compare' => 'LIKE',
			];
		}

		$base_query['meta_query'][] = $or_clauses;

		return $base_query;
	}
}
