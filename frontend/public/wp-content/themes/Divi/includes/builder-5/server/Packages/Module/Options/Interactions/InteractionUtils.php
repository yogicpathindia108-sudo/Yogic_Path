<?php
/**
 * Module Options: Interaction Utils Class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Interactions;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * InteractionUtils class.
 *
 * This class provides utility methods for working with interactions.
 *
 * @since ??
 */
class InteractionUtils {

	/**
	 * Check if interactions exist for the given attributes.
	 *
	 * This method checks if any interactions exist in the attribute structure.
	 * It handles both the attribute structure used by script data and element attributes.
	 *
	 * @since ??
	 *
	 * @param array $attr The interactions attributes.
	 *
	 * @return bool True if interactions exist, false otherwise.
	 */
	public static function has_interactions( array $attr ): bool {
		if ( empty( $attr ) ) {
			return false;
		}

		// Handle script data attribute structure.
		$interactions = [];
		if ( isset( $attr['desktop']['value']['interactions'] ) ) {
			$interactions = $attr['desktop']['value']['interactions'];
		} elseif ( isset( $attr['interactions'] ) ) {
			// Handle element attribute structure: $attr['interactions'].
			$interactions = $attr['interactions'];
		}

		return ! empty( $interactions ) && is_array( $interactions );
	}
}
