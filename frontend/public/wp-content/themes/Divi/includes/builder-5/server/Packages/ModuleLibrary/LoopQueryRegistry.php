<?php
/**
 * Loop Query Registry for storing and retrieving WP_Query objects.
 *
 * This registry provides a centralized way to store WP_Query objects created by
 * LoopHandler and retrieve them later for pagination purposes. It uses loopId
 * as the storage key to ensure proper loop identification.
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary;

use ET\Builder\Framework\Utility\Memoize;
use ET\Builder\Packages\Module\Options\Loop\LoopUtils;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * LoopQueryRegistry class.
 *
 * Static registry for storing and retrieving loop queries by loopId.
 * Supports WP_Query, WP_Term_Query, and WP_User_Query objects.
 *
 * @since ??
 */
class LoopQueryRegistry {

	/**
	 * Storage for loop queries keyed by loopId.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	private static $_queries = [];

	/**
	 * Store a query object by loopId.
	 *
	 * Adds an optional arguments signature to ensure safe reuse across multiple loop instances.
	 *
	 * @since ??
	 *
	 * @param string $loop_id     The unique loop identifier.
	 * @param mixed  $query       The query object (WP_Query, WP_Term_Query, WP_User_Query).
	 * @param array  $query_args  Optional. Original query arguments used to build the query. Default [].
	 * @param string $query_type  Optional. The loop query type (e.g., 'post_types', 'terms'). Default ''.
	 *
	 * @return void
	 */
	public static function store( string $loop_id, $query, array $query_args = [], string $query_type = '' ): void {
		if ( empty( $loop_id ) ) {
			return;
		}

		$normalized_args = is_array( $query_args ) ? $query_args : [];
		$normalized_type = is_string( $query_type ) ? $query_type : '';
		$args_signature  = self::_build_signature( $normalized_args, $normalized_type );

		self::$_queries[ $loop_id ] = [
			'query'          => $query,
			'type'           => get_class( $query ),
			'stored_at'      => microtime( true ),
			'found_posts'    => method_exists( $query, 'found_posts' ) ? $query->found_posts : 0,
			'args_signature' => $args_signature,
			'query_type'     => $query_type,
		];
	}

	/**
	 * Retrieve a query object by loopId.
	 *
	 * If the query is not found in the registry, this method will automatically
	 * attempt to generate it predictively using LoopUtils::generate_predictive_query().
	 * This eliminates the need for calling code to manually handle the fallback logic.
	 *
	 * @since ??
	 *
	 * @param string   $loop_id        The unique loop identifier.
	 * @param int|null $store_instance Optional. The store instance to search in. Default null.
	 *
	 * @return mixed|null The query object or null if not found.
	 */
	public static function get_query( string $loop_id, $store_instance = null ) {
		if ( empty( $loop_id ) ) {
			return null;
		}

		// First, check if we already have the query stored.
		if ( isset( self::$_queries[ $loop_id ] ) ) {
			return self::$_queries[ $loop_id ]['query'];
		}

		// If not found, attempt predictive query generation.
		// This handles the case where pagination is placed before the looped element.
		$predictive_query = LoopUtils::generate_predictive_query( $loop_id, $store_instance );

		// Note: LoopUtils::generate_predictive_query() automatically stores the query
		// in the registry if successful, so subsequent calls will use the cached version.

		return $predictive_query;
	}

	/**
	 * Retrieve a query object only if it matches the expected args and type.
	 *
	 * Prevents leaking a cached query from one loop instance to another when loop IDs collide.
	 *
	 * @since ??
	 *
	 * @param string $loop_id              The unique loop identifier.
	 * @param array  $expected_query_args  The expected query args.
	 * @param string $expected_query_type  The expected query type.
	 *
	 * @return mixed|null The query object or null if not found or mismatched.
	 */
	public static function get_query_if_matches( string $loop_id, array $expected_query_args, string $expected_query_type ) {
		if ( empty( $loop_id ) || ! isset( self::$_queries[ $loop_id ] ) ) {
			return null;
		}

		$stored = self::$_queries[ $loop_id ];

		if ( empty( $stored['args_signature'] ) ) {
			return null;
		}

		$expected_signature = self::_build_signature( $expected_query_args, $expected_query_type );

		if ( $expected_signature === $stored['args_signature'] ) {
			return $stored['query'];
		}

		return null;
	}

	/**
	 * Check if a query exists for the given loopId.
	 *
	 * @since ??
	 *
	 * @param string $loop_id The unique loop identifier.
	 *
	 * @return bool True if query exists, false otherwise.
	 */
	public static function has_query( string $loop_id ): bool {
		return ! empty( $loop_id ) && isset( self::$_queries[ $loop_id ] );
	}

	/**
	 * Get all stored loop IDs.
	 *
	 * @since ??
	 *
	 * @return array Array of loop IDs.
	 */
	public static function get_all_loop_ids(): array {
		return array_keys( self::$_queries );
	}

	/**
	 * Clear all stored queries.
	 *
	 * This should be called after page rendering to free memory.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function clear(): void {
		self::$_queries = [];
	}

	/**
	 * Get query metadata without retrieving the full query object.
	 *
	 * @since ??
	 *
	 * @param string $loop_id The unique loop identifier.
	 *
	 * @return array|null Query metadata or null if not found.
	 */
	public static function get_query_metadata( string $loop_id ): ?array {
		if ( empty( $loop_id ) || ! isset( self::$_queries[ $loop_id ] ) ) {
			return null;
		}

		$data = self::$_queries[ $loop_id ];

		return [
			'type'          => $data['type'],
			'stored_at'     => $data['stored_at'],
			'found_posts'   => $data['found_posts'],
			'age_seconds'   => microtime( true ) - $data['stored_at'],
			'has_signature' => ! empty( $data['args_signature'] ),
		];
	}

	/**
	 * Build a stable signature for query args and type.
	 *
	 * @since ??
	 *
	 * @param array  $args The query args.
	 * @param string $type The query type.
	 *
	 * @return string The signature string.
	 */
	private static function _build_signature( array $args, string $type ): string {
		return Memoize::generate_key( 'divi_loop_query', $args, $type );
	}
}
