<?php
/**
 * Module: InteractionClassnames class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Element;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Layout\Components\Classnames;

/**
 * Interaction Classnames class.
 *
 * This class provides methods to generate CSS classes for interaction targeting.
 *
 * @since ??
 */
class InteractionClassnames {

	/**
	 * Generate interaction target classnames.
	 *
	 * Generates CSS classes for modules that are targets of interactions. Uses the persistent
	 * interactionTarget attribute stored on the module rather than ephemeral module IDs.
	 *
	 * @since ??
	 *
	 * @param string $interaction_target_id The interaction target ID.
	 *
	 * @return string The target CSS class if this module is an interaction target.
	 */
	public static function target_classnames( string $interaction_target_id ): string {
		if ( empty( $interaction_target_id ) ) {
			return '';
		}

		// Generate the target class using the persistent target ID.
		return 'et-interaction-target-' . esc_attr( $interaction_target_id );
	}
}
