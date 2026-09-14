<?php
/**
 * Module: LoopContext class.
 *
 * @package Builder\Packages\Module\Options\Loop
 */

namespace ET\Builder\Packages\Module\Options\Loop;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * LoopContext class.
 *
 * Provides a safer system for managing loop position context.
 * Focused specifically on loop position functionality in DynamicContentLoopOptions.
 *
 * @since ??
 */
class LoopContext {
	/**
	 * The singleton instance.
	 *
	 * @since ??
	 *
	 * @var LoopContext|null
	 */
	private static $_instance = null;

	/**
	 * Current query results.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	private $_query_results = [];

	/**
	 * Number of columns per row.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	private $_columns_per_row = 1;

	/**
	 * Current loop iteration.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	private $_loop_iteration = 0;

	/**
	 * Original query type from the loop configuration.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private $_query_type = 'post_types';

	/**
	 * Private constructor to prevent direct instantiation.
	 *
	 * @since ??
	 */
	private function __construct() {}

	/**
	 * Set the loop position context.
	 *
	 * @since ??
	 *
	 * @param array  $query_results   The query results array.
	 * @param int    $columns_per_row Number of columns per row.
	 * @param int    $loop_iteration  Current loop iteration.
	 * @param string $query_type      Original query type from loop configuration.
	 *
	 * @return void
	 */
	public static function set_position_context( array $query_results, int $columns_per_row, int $loop_iteration, string $query_type = 'post_types' ) {
		self::$_instance                   = new self();
		self::$_instance->_query_results   = $query_results;
		self::$_instance->_columns_per_row = $columns_per_row;
		self::$_instance->_loop_iteration  = $loop_iteration;
		self::$_instance->_query_type      = $query_type;
	}

	/**
	 * Get the current loop context instance.
	 *
	 * @since ??
	 *
	 * @return LoopContext|null The loop context instance or null if not set.
	 */
	public static function get() {
		return self::$_instance;
	}

	/**
	 * Clear the loop position context.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function clear() {
		self::$_instance = null;
	}

	/**
	 * Get a query result for a specific loop position.
	 *
	 * This method implements the core loop position formula:
	 * calculated_index = (loop_iteration * columns_per_row) + loop_position
	 *
	 * @since ??
	 *
	 * @param int $loop_position_0_based The 0-based loop position.
	 *
	 * @return object|null The query result object or null if not found.
	 */
	public function get_result_for_position( int $loop_position_0_based ) {
		$calculated_index = ( $this->_loop_iteration * $this->_columns_per_row ) + $loop_position_0_based;

		return $this->_query_results[ $calculated_index ] ?? null;
	}

	/**
	 * Get the original query type from the loop configuration.
	 *
	 * @since ??
	 *
	 * @return string The original query type (e.g., 'post_types', 'terms', 'repeater_fieldname').
	 */
	public function get_query_type(): string {
		return $this->_query_type;
	}

	/**
	 * Get the current loop iteration.
	 *
	 * @since ??
	 *
	 * @return int The current loop iteration number (0-based).
	 */
	public function get_current_iteration(): int {
		return $this->_loop_iteration;
	}
}
