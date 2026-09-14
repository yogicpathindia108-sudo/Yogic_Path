<?php
/**
 * Transition Selector Utilities.
 *
 * Provides utility functions for generating custom transition selectors
 * for advanced style components that require element-specific targeting.
 *
 * @package ET\Builder\Packages\Module\Options\Transition
 *
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Transition;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Transition Selector Utilities class.
 *
 * @since ??
 */
class TransitionSelectorUtils {

	/**
	 * Get the appropriate transition selector for a style component.
	 *
	 * This function determines the correct CSS selector to use for applying
	 * transition styles based on the component type. Some components require
	 * custom selectors to target inner elements rather than the parent.
	 *
	 * Currently supported custom selectors:
	 * - `divi/dividers`: Targets the inner divider element (`.et_pb_{placement}_inside_divider`)
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Arguments for determining the transition selector.
	 *
	 *     @type string $selector       The default/fallback selector (typically the module selector).
	 *     @type string $component_name The name of the style component (e.g., 'divi/dividers').
	 *     @type array  $props          Component props containing additional data needed for selector generation.
	 * }
	 *
	 * @return string The appropriate transition selector for the component.
	 */
	public static function get_advanced_transition_selector( array $args ): string {
		// Extract arguments with defaults.
		$selector       = $args['selector'] ?? '';
		$component_name = $args['component_name'] ?? '';
		$props          = $args['props'] ?? [];

		// Validate required arguments.
		if ( empty( $selector ) || empty( $component_name ) ) {
			return $selector;
		}

		// Use component-specific selector logic.
		switch ( $component_name ) {
			case 'divi/dividers':
				return self::_get_dividers_transition_selector( $selector, $props );
			default:
				// Return the provided selector or fallback to default.
				// Empty strings are treated as invalid and fall back to the default selector.
				$custom_selector = $props['selector'] ?? '';

				return '' !== $custom_selector ? $custom_selector : $selector;
		}
	}

	/**
	 * Get transition selector for dividers component.
	 *
	 * Dividers require targeting the inner element (`.et_pb_{placement}_inside_divider`)
	 * rather than the parent section selector, because the CSS properties that change
	 * (height, background-size, etc.) are applied to the divider element itself.
	 *
	 * @since ??
	 *
	 * @param string $base_selector The base module selector (e.g., '.et_pb_section_0').
	 * @param array  $props         Component props containing placement information.
	 *
	 * @return string The divider-specific transition selector.
	 */
	private static function _get_dividers_transition_selector( string $base_selector, array $props ): string {
		$placement = $props['placement'] ?? '';

		// Validate placement value.
		if ( empty( $placement ) || ! in_array( $placement, [ 'top', 'bottom' ], true ) ) {
			return $base_selector;
		}

		// Build the divider element selector.
		// Example: '.et_pb_section_0.section_has_divider .et_pb_top_inside_divider'.
		return sprintf(
			'%s.section_has_divider .et_pb_%s_inside_divider',
			$base_selector,
			$placement
		);
	}
}
