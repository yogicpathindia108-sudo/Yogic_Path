<?php
/**
 * Post Filter query helpers.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostFilter\Query;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\ModuleLibrary\PostFilter\PostFilterUtils;

/**
 * Shared utility methods used across all post-filter query builders.
 *
 * @since ??
 */
class PostFilterQueryHelpers {

	/**
	 * Synthetic key for grouped range min/max params.
	 *
	 * Stored here (rather than PostTypeFilterQueryBuilder) to avoid a
	 * circular dependency, since extract_filter_clauses must inject this key.
	 *
	 * @var string
	 */
	public const RANGE_CLAUSE_KEY = '__range';

	/**
	 * Extract active filter clauses from submitted params.
	 *
	 * Strips sort, relation, and companion metadata keys so only
	 * filterable field keys remain.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $params Sanitized filter params.
	 *
	 * @return array<string,mixed>
	 */
	public static function extract_filter_clauses( array $params ): array {
		$clauses = [];

		foreach ( $params as $param_key => $param_value ) {
			if ( in_array( $param_key, [ 'order', 'order_by', 'multiple-order' ], true ) || PostFilterUtils::FILTER_RELATION_PARAM_KEY === $param_key ) {
				continue;
			}

			if ( in_array( $param_key, [ 'product-range_min', 'product-range_max' ], true ) ) {
				continue;
			}

			if (
				str_ends_with( $param_key, '_compare' )
				|| str_ends_with( $param_key, '_meta' )
				|| str_ends_with( $param_key, '_target' )
				|| str_ends_with( $param_key, '_target_meta' )
				|| str_ends_with( $param_key, '_additional_target' )
				|| str_ends_with( $param_key, '_additional_meta' )
			) {
				continue;
			}

			$clauses[ $param_key ] = $param_value;
		}

		$range_min = $params['product-range_min'] ?? null;
		$range_max = $params['product-range_max'] ?? null;

		if ( null !== $range_min || null !== $range_max ) {
			$clauses[ self::RANGE_CLAUSE_KEY ] = [
				'min' => $range_min,
				'max' => $range_max,
			];
		}

		return $clauses;
	}

	/**
	 * Apply order filter values to query args.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query  Base query args.
	 * @param string              $order_value Order slug from filter control.
	 *
	 * @return array<string,mixed>
	 */
	public static function apply_order( array $base_query, string $order_value ): array {
		switch ( sanitize_key( $order_value ) ) {
			case 'ascending':
				$base_query['order'] = 'ASC';
				break;
			case 'descending':
				$base_query['order'] = 'DESC';
				break;
			case 'default':
			default:
				break;
		}

		return $base_query;
	}
}
