<?php
/**
 * Post Filter hooks.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostFilter;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Registers post-filter loop refinement hooks.
 */
class PostFilterHooks {

	/**
	 * Register post-filter hooks.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'divi_loop_data_after_execution', [ PostFilterQueryBuilder::class, 'apply_to_loop_data' ], 10, 3 );
		add_filter( 'divi_module_options_loop_post_type_results_query_args', [ self::class, 'apply_loop_filter_to_query_args' ], 10, 2 );
		add_filter( 'divi_module_options_loop_terms_results_query_args', [ self::class, 'apply_loop_filter_to_query_args' ], 10, 2 );
		add_filter( 'divi_module_options_loop_users_results_query_args', [ self::class, 'apply_loop_filter_to_query_args' ], 10, 2 );
		add_filter( 'divi_module_options_loop_menus_results_query_args', [ self::class, 'apply_loop_filter_to_query_args' ], 10, 2 );
		add_filter( 'terms_clauses', [ PostFilterQueryBuilder::class, 'filter_terms_clauses_for_taxonomy_order' ], 10, 3 );
	}

	/**
	 * Apply post-filter refinements to Visual Builder loop query args.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $query_args Loop query arguments (`WP_Query` or `WP_Term_Query`).
	 * @param array<string,mixed> $params     REST request params.
	 *
	 * @return array<string,mixed>
	 */
	public static function apply_loop_filter_to_query_args( array $query_args, array $params ): array {
		$loop_id = isset( $params['loop_id'] ) ? sanitize_text_field( (string) $params['loop_id'] ) : '';

		if ( '' === $loop_id ) {
			return $query_args;
		}

		$filter_params = PostFilterUtils::get_loop_filter_params_from_params( $params, $loop_id );

		if ( empty( $filter_params ) ) {
			return $query_args;
		}

		$relation   = PostFilterUtils::get_loop_filter_relation_from_params( $params, $loop_id ) ?? 'and';
		$query_type = self::_map_rest_query_type_to_loop_query_type( $params );

		return PostFilterQueryBuilder::build_args(
			$query_args,
			[
				'relation' => $relation,
				'params'   => $filter_params,
			],
			$query_type
		);
	}

	/**
	 * Map REST loop query type to post-filter loop query type.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $params REST request params.
	 *
	 * @return string
	 */
	private static function _map_rest_query_type_to_loop_query_type( array $params ): string {
		$query_type = isset( $params['query_type'] ) ? sanitize_text_field( (string) $params['query_type'] ) : 'post_type';

		if ( 'users' === $query_type ) {
			return 'user_roles';
		}

		if ( in_array( $query_type, [ 'terms', 'post_taxonomies' ], true ) ) {
			return 'terms';
		}

		if ( 'menus' === $query_type ) {
			return 'menus';
		}

		return 'post_types';
	}
}
