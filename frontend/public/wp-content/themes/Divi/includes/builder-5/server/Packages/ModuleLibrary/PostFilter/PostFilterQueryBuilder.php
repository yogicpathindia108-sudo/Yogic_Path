<?php
/**
 * Post Filter query builder.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostFilter;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\ModuleLibrary\PostFilter\Query\MenusFilterQueryBuilder;
use ET\Builder\Packages\ModuleLibrary\PostFilter\Query\PostTypeFilterQueryBuilder;
use ET\Builder\Packages\ModuleLibrary\PostFilter\Query\TermsFilterQueryBuilder;
use ET\Builder\Packages\ModuleLibrary\PostFilter\Query\UsersFilterQueryBuilder;

/**
 * Refines loop query args from post-filter URL state.
 *
 * Acts as the public entry point and dispatcher to domain-specific query builders.
 *
 * @since ??
 */
class PostFilterQueryBuilder {

	/**
	 * WP_Term_Query flag used to scope taxonomy order-by clause filters.
	 *
	 * Forwarded from TermsFilterQueryBuilder to preserve the public constant API.
	 *
	 * @var string
	 */
	public const TERMS_TAXONOMY_ORDER_FLAG = TermsFilterQueryBuilder::TERMS_TAXONOMY_ORDER_FLAG;

	/**
	 * Apply post-filter refinements during loop execution.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $loop_data   Loop data generated from block attributes.
	 * @param array<string,mixed> $block_attrs Block attributes.
	 * @param array<string,mixed> $block       Parsed block array.
	 *
	 * @return array<string,mixed>
	 */
	public static function apply_to_loop_data( array $loop_data, array $block_attrs, array $block ): array {
		$loop_id = $block_attrs['module']['advanced']['loop']['desktop']['value']['loopId'] ?? '';

		if ( ! is_string( $loop_id ) || '' === $loop_id ) {
			return $loop_data;
		}

		$store_instance = $block['storeInstance'] ?? null;

		return self::refine_loop_data( $loop_data, $loop_id, $store_instance );
	}

	/**
	 * Refine loop data using namespaced filter params for one loop id.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $loop_data      Loop data generated from block attributes.
	 * @param string              $loop_id        Target loop id.
	 * @param int|null            $store_instance Optional block parser store instance.
	 *
	 * @return array<string,mixed>
	 */
	public static function refine_loop_data( array $loop_data, string $loop_id, $store_instance = null ): array {
		$filter_params = PostFilterUtils::get_loop_filter_params_from_request( $loop_id );

		if ( empty( $filter_params ) ) {
			return $loop_data;
		}

		$relation = PostFilterUtils::resolve_filter_relation_for_loop( $loop_id, $store_instance );

		$loop_data['query_args'] = self::build_args(
			$loop_data['query_args'] ?? [],
			[
				'relation' => $relation,
				'params'   => $filter_params,
			],
			$loop_data['query_type'] ?? 'post_types'
		);

		return $loop_data;
	}

	/**
	 * Build refined query args from base query.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query Base loop query args.
	 * @param array<string,mixed> $filters    Normalized filter payload.
	 * @param string              $query_type Loop query type.
	 *
	 * @return array<string,mixed>
	 */
	public static function build_args( array $base_query, array $filters, string $query_type = 'post_types' ): array {
		$params = $filters['params'] ?? [];

		if ( empty( $params ) || ! is_array( $params ) ) {
			return $base_query;
		}

		$relation = $filters['relation'] ?? 'and';
		$relation = 'or' === $relation ? 'or' : 'and';

		if ( self::_is_user_query_type( $query_type ) ) {
			return UsersFilterQueryBuilder::apply( $base_query, $params, $relation );
		}

		if ( self::_is_terms_query_type( $query_type ) ) {
			return TermsFilterQueryBuilder::apply( $base_query, $params, $relation );
		}

		if ( self::_is_menus_query_type( $query_type ) ) {
			return MenusFilterQueryBuilder::apply( $base_query, $params, $relation );
		}

		return PostTypeFilterQueryBuilder::apply( $base_query, $params, $relation );
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
		return TermsFilterQueryBuilder::filter_terms_clauses_for_taxonomy_order( $clauses, $taxonomies, $args );
	}

	/**
	 * Whether the loop query type resolves users.
	 *
	 * @since ??
	 *
	 * @param string $query_type Loop query type.
	 *
	 * @return bool
	 */
	private static function _is_user_query_type( string $query_type ): bool {
		return in_array( $query_type, [ 'user_roles', 'users' ], true );
	}

	/**
	 * Whether the loop query type resolves taxonomy terms.
	 *
	 * @since ??
	 *
	 * @param string $query_type Loop query type.
	 *
	 * @return bool
	 */
	private static function _is_terms_query_type( string $query_type ): bool {
		return in_array( $query_type, [ 'terms', 'post_taxonomies' ], true );
	}

	/**
	 * Whether the loop query type resolves menu items.
	 *
	 * @since ??
	 *
	 * @param string $query_type Loop query type.
	 *
	 * @return bool
	 */
	private static function _is_menus_query_type( string $query_type ): bool {
		return 'menus' === $query_type;
	}
}
