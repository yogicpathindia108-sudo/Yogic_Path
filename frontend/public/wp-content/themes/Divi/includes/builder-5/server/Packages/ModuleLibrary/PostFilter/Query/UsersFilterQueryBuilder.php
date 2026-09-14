<?php
/**
 * Users filter query builder.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostFilter\Query;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\ModuleLibrary\PostFilterItem\PostFilterCustomFieldUtils;
use WP_User_Query;

/**
 * Builds WP_User_Query args from post-filter params for user/role loops.
 *
 * Handles role selection and text search against display name, bio, username,
 * and email fields.
 *
 * @since ??
 */
class UsersFilterQueryBuilder {

	/**
	 * Apply post-filter refinements to user queries.
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

		if ( isset( $clauses['role'] ) ) {
			$roles = array_values(
				array_filter(
					array_map( 'sanitize_key', (array) $clauses['role'] )
				)
			);

			if ( ! empty( $roles ) ) {
				$base_query['role__in'] = $roles;
			}

			unset( $clauses['role'] );
		}

		$search_clauses = [];

		foreach ( $clauses as $clause_key => $clause_value ) {
			if (
				PostFilterCustomFieldUtils::is_text_field_param_key( $clause_key )
				&& is_string( $clause_value )
				&& '' !== $clause_value
			) {
				$search_clauses[ $clause_key ] = $clause_value;
			}
		}

		if ( empty( $search_clauses ) ) {
			return self::_apply_sort_params( $base_query, $params );
		}

		$user_id_sets = [];

		foreach ( $search_clauses as $clause_key => $clause_value ) {
			$target         = $params[ $clause_key . '_target' ] ?? '';
			$user_id_sets[] = self::_query_user_ids_for_search( $base_query, $clause_value, (string) $target );
		}

		if ( 1 === count( $user_id_sets ) ) {
			$matching_user_ids = $user_id_sets[0];
		} elseif ( 'or' === $relation ) {
			$matching_user_ids = array_values( array_unique( array_merge( ...$user_id_sets ) ) );
		} else {
			$matching_user_ids = array_values( array_intersect( ...$user_id_sets ) );
		}

		return self::_apply_sort_params(
			self::_restrict_users_query_to_ids( $base_query, $matching_user_ids ),
			$params
		);
	}

	/**
	 * Apply scalar sort params to user query args.
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
	 * Apply one order-by and order pair to user query args.
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
		$has_order_by   = 'default' !== $order_by_value && '' !== $order_by_value;

		if ( $has_order_by && ! in_array( $order_by_value, self::_get_valid_order_by_values(), true ) ) {
			return $base_query;
		}

		if ( $has_order_by ) {
			$base_query = self::_apply_order_by( $base_query, $order_by_value );
		}

		if ( is_string( $order_value ) && 'default' !== $order_value ) {
			$base_query = PostFilterQueryHelpers::apply_order( $base_query, $order_value );
		}

		return $base_query;
	}

	/**
	 * Apply order-by filter values to user query args.
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

		if ( 'description' === $order_by_value ) {
			$base_query['orderby']  = 'meta_value';
			$base_query['meta_key'] = 'description';

			return $base_query;
		}

		$base_query['orderby'] = $order_by_value;

		return $base_query;
	}

	/**
	 * Return supported order-by values for users-loop filter controls.
	 *
	 * @since ??
	 *
	 * @return array<int, string>
	 */
	private static function _get_valid_order_by_values(): array {
		return [
			'display_name',
			'login',
			'email',
			'description',
		];
	}

	/**
	 * Query user IDs that match one keyword search against the selected user search target.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query   Base query args.
	 * @param string              $search_value Submitted search value.
	 * @param string              $target       Search target option value.
	 *
	 * @return array<int, int>
	 */
	private static function _query_user_ids_for_search( array $base_query, string $search_value, string $target ): array {
		$target = self::_resolve_search_target( $target );

		switch ( $target ) {
			case PostFilterCustomFieldUtils::SEARCH_TARGET_USER_USERNAME:
				return self::_query_user_ids_by_columns( $base_query, $search_value, [ 'user_login' ] );

			case PostFilterCustomFieldUtils::SEARCH_TARGET_USER_EMAIL:
				return self::_query_user_ids_by_columns( $base_query, $search_value, [ 'user_email' ] );

			case PostFilterCustomFieldUtils::SEARCH_TARGET_USER_DISPLAY_NAME_AND_BIO:
			default:
				return self::_query_user_ids_for_display_name_and_bio( $base_query, $search_value );
		}
	}

	/**
	 * Resolve a user-loop search target option to a supported target slug.
	 *
	 * @since ??
	 *
	 * @param string $target Search target option value.
	 *
	 * @return string
	 */
	private static function _resolve_search_target( string $target ): string {
		$valid_targets = [
			PostFilterCustomFieldUtils::SEARCH_TARGET_USER_DISPLAY_NAME_AND_BIO,
			PostFilterCustomFieldUtils::SEARCH_TARGET_USER_USERNAME,
			PostFilterCustomFieldUtils::SEARCH_TARGET_USER_EMAIL,
		];

		if ( in_array( $target, $valid_targets, true ) ) {
			return $target;
		}

		return PostFilterCustomFieldUtils::SEARCH_TARGET_USER_DISPLAY_NAME_AND_BIO;
	}

	/**
	 * Query user IDs that match one keyword search against specific user table columns.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query     Base query args.
	 * @param string              $search_value   Submitted search value.
	 * @param array<int, string>  $search_columns User table columns to search.
	 *
	 * @return array<int, int>
	 */
	private static function _query_user_ids_by_columns( array $base_query, string $search_value, array $search_columns ): array {
		$query_args                   = self::_build_base_id_query_args( $base_query );
		$query_args['search']         = '*' . $search_value . '*';
		$query_args['search_columns'] = $search_columns;
		$user_query                   = new WP_User_Query( $query_args );

		return array_map( 'absint', $user_query->get_results() );
	}

	/**
	 * Query user IDs whose display name or bio/description matches one keyword search.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query   Base query args.
	 * @param string              $search_value Submitted search value.
	 *
	 * @return array<int, int>
	 */
	private static function _query_user_ids_for_display_name_and_bio( array $base_query, string $search_value ): array {
		$display_name_ids = self::_query_user_ids_by_columns( $base_query, $search_value, [ 'display_name' ] );
		$bio_ids          = self::_query_user_ids_for_bio_search( $base_query, $search_value );

		return array_values( array_unique( array_merge( $display_name_ids, $bio_ids ) ) );
	}

	/**
	 * Query user IDs whose bio/description meta value matches one keyword search.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query   Base query args.
	 * @param string              $search_value Submitted search value.
	 *
	 * @return array<int, int>
	 */
	private static function _query_user_ids_for_bio_search( array $base_query, string $search_value ): array {
		$query_args               = self::_build_base_id_query_args( $base_query );
		$query_args['meta_query'] = [
			[
				'key'     => 'description',
				'value'   => $search_value,
				'compare' => 'LIKE',
			],
		];
		$user_query               = new WP_User_Query( $query_args );

		return array_map( 'absint', $user_query->get_results() );
	}

	/**
	 * Build base user ID query args from one loop query context.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query Base query args.
	 *
	 * @return array<string,mixed>
	 */
	private static function _build_base_id_query_args( array $base_query ): array {
		$query_args = [
			'fields' => 'ID',
		];

		foreach ( [ 'role__in', 'role__not_in', 'include', 'exclude', 'number', 'offset', 'orderby', 'order' ] as $key ) {
			if ( isset( $base_query[ $key ] ) ) {
				$query_args[ $key ] = $base_query[ $key ];
			}
		}

		return $query_args;
	}

	/**
	 * Restrict user query args to one explicit user ID set.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query Base query args.
	 * @param array<int, mixed>   $user_ids   Matching user IDs.
	 *
	 * @return array<string,mixed>
	 */
	private static function _restrict_users_query_to_ids( array $base_query, array $user_ids ): array {
		$user_ids = array_values(
			array_filter(
				array_map( 'absint', $user_ids )
			)
		);

		unset( $base_query['search'] );

		if ( isset( $base_query['include'] ) && is_array( $base_query['include'] ) ) {
			$user_ids = array_values( array_intersect( $base_query['include'], $user_ids ) );
		}

		$base_query['include'] = ! empty( $user_ids ) ? $user_ids : [ 0 ];

		return $base_query;
	}
}
