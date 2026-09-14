<?php
/**
 * OverflowForBorderRadius class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\StyleLibrary\Declarations\OverflowForBorderRadius;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;

/**
 * OverflowForBorderRadius class.
 *
 * This class provides functionality to set overflow style declaration when border radius is used.
 *
 * This class is equivalent of JS function:
 * {@link /docs/builder-api/js/style-library/overflow-for-border-radius-style-declaration/ overflowForBorderRadiusStyleDeclaration}
 * located in `@divi/style-library` package.
 *
 * @since ??
 */
class OverflowForBorderRadius {

	/**
	 * Sets the overflow style declaration when border radius used.
	 *
	 * This function adds `overflow: hidden` when border-radius is applied to ensure
	 * content clips at rounded corners. However, if the user has explicitly set a
	 * non-default overflow value (via Visibility options), their setting takes precedence.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/style-library/overflow-for-border-radius-style-declaration/ overflowForBorderRadiusStyleDeclaration}
	 * located in `@divi/style-library` package.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of parameters.
	 *
	 *     @type array  $attrValue    The attribute value containing border radius information.
	 *     @type string $breakpoint   Optional. The current breakpoint being rendered. Default `desktop`.
	 *     @type string $state        Optional. The current state. Default `value`.
	 * }
	 * @param array $overflow_attr Overflow attribute containing module decoration overflow settings.
	 *
	 * @return string The style declaration.
	 *
	 * @example
	 * ```php
	 * $params = [
	 *     'attrValue' => [
	 *         'radius' => [
	 *             'topLeft' => '10px',
	 *             'topRight' => '10px',
	 *             'bottomLeft' => '10px',
	 *             'bottomRight' => '10px',
	 *         ],
	 *     ],
	 *     'overflowAttr' => [
	 *         'desktop' => [
	 *             'value' => [
	 *                 'x' => 'visible',
	 *                 'y' => 'visible',
	 *             ],
	 *         ],
	 *     ],
	 *     'breakpoint' => 'desktop',
	 *     'state' => 'value',
	 * ];
	 * $overflow_declaration = OverflowForBorderRadius::style_declaration( $params );
	 * // Returns: 'overflow: hidden;'
	 * ```
	 */
	public static function style_declaration( array $params, array $overflow_attr = [] ): string {
		$radius = $params['attrValue']['radius'] ?? [];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		// If radius is empty, not an array, or has no keys, return empty string.
		// Non-array values (e.g. string shorthand "12px") must not reach count()/foreach.
		if ( ! is_array( $radius ) || empty( $radius ) ) {
			return $style_declarations->value();
		}

		$all_corners_zero = true;

		// Check whether all corners are zero.
		// If any corner is not zero, update the variable and break the loop.
		foreach ( $radius as $corner => $value ) {
			if ( 'sync' === $corner ) {
				continue;
			}

			// If value contains global variable, apply overflow:hidden.
			// Global variables can contain complex CSS (clamp, calc, vw, rem, etc.) that can't be parsed numerically.
			if ( GlobalData::is_global_variable_value( $value ?? '' ) ) {
				$all_corners_zero = false;
				break;
			}

			$corner_value = SanitizerUtility::numeric_parse_value( $value ?? '' );
			if ( null === $corner_value || 0.0 !== ( $corner_value['valueNumber'] ?? 0.0 ) ) {
				$all_corners_zero = false;
				break;
			}
		}

		// If all corners are zero, return empty string.
		if ( $all_corners_zero ) {
			return $style_declarations->value();
		}

		$current_breakpoint = $params['breakpoint'] ?? 'desktop';

		// Get overflow value at current breakpoint/state with inheritance.
		$overflow_value = ModuleUtils::use_attr_value(
			[
				'attr'         => $overflow_attr,
				'breakpoint'   => $current_breakpoint,
				'state'        => $params['state'] ?? 'value',
				'mode'         => 'getOrInheritAll',
				'defaultValue' => null,
			]
		);

		// Get overflow-x and overflow-y values at current breakpoint/state.
		$overflow_x = $overflow_value['x'] ?? null;
		$overflow_y = $overflow_value['y'] ?? null;

		// Check if overflow-x or overflow-y are explicitly set and not 'hidden' or 'default'.
		$has_overflow_x = ! empty( $overflow_x ) && 'default' !== $overflow_x && 'hidden' !== $overflow_x;
		$has_overflow_y = ! empty( $overflow_y ) && 'default' !== $overflow_y && 'hidden' !== $overflow_y;

		// Apply Divi 4 logic: respect explicit overflow settings.
		if ( $has_overflow_x && $has_overflow_y ) {
			// Both axes explicitly set to non-hidden: don't add overflow hidden.
			return $style_declarations->value();
		}

		if ( $has_overflow_x ) {
			// Only overflow-x set: hide overflow-y only.
			$style_declarations->add( 'overflow-y', 'hidden' );
		} elseif ( $has_overflow_y ) {
			// Only overflow-y set: hide overflow-x only.
			$style_declarations->add( 'overflow-x', 'hidden' );
		} else {
			// No explicit overflow settings: add overflow hidden for border radius clipping.
			$style_declarations->add( 'overflow', 'hidden' );
		}

		return $style_declarations->value();
	}
}
