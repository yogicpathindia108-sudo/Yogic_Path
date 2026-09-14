<?php
/**
 * ArrayUtility::is_assoc()
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Framework\Utility\ArrayUtilityTraits;

// phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames -- Reserved keywords used intentionally for clarity in utility functions.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Utility\ArrayUtility;

trait IsAssocTrait {

	/**
	 * Checks whether a given array is an associative array.
	 *
	 * @since ??
	 *
	 * @param array $array The array being evaluated.
	 *
	 * @return bool Returns true if array is associative, false otherwise.
	 **/
	public static function is_assoc( array $array ): bool {
		return ! ArrayUtility::is_list( $array );
	}
}
