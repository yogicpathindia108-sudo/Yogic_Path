<?php
/**
 * Transform::style_declaration()
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\StyleLibrary\Declarations\Transform\TransformTraits;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;

trait StyleDeclarationTrait {

	use ValueTrait;

	/**
	 * Build responsive translate resets for inherited centered origins.
	 *
	 * When the active responsive mode no longer has its own origin entry, the
	 * saved position data can still carry inherited origins from another mode
	 * such as desktop `absolute`. Only reset axes that were previously centered
	 * so we avoid emitting broader transform changes than necessary.
	 *
	 * @since ??
	 *
	 * @param array|null  $origin Position origin values keyed by mode.
	 * @param string|null $breakpoint Current breakpoint.
	 *
	 * @return array|null Array with `x` and/or `y` reset values, or null if no reset is needed.
	 */
	private static function _get_inherited_position_translate_resets( $origin, $breakpoint ) {
		if ( 'desktop' === $breakpoint || ! is_array( $origin ) || empty( $origin ) ) {
			return null;
		}

		$translates = [];

		foreach ( $origin as $origin_value ) {
			if ( ! is_string( $origin_value ) || '' === $origin_value ) {
				continue;
			}

			$origin_parts = explode( ' ', $origin_value );
			$vertical     = $origin_parts[0] ?? null;
			$horizontal   = $origin_parts[1] ?? null;

			if ( 'center' === $horizontal ) {
				$translates['x'] = '0px';
			}

			if ( 'center' === $vertical ) {
				$translates['y'] = '0px';
			}
		}

		return ! empty( $translates ) ? $translates : null;
	}

	/**
	 * Extract position-based translates from position attributes.
	 * Follows D4's pattern: adds translateX/Y when position uses center alignment.
	 *
	 * @since ??
	 *
	 * @param array|null  $position_attrs Position attributes.
	 * @param string|null $breakpoint Current breakpoint.
	 * @param string      $state Current state ('value', 'hover', 'sticky'). Defaults to 'value'.
	 *
	 * @return array|null Array with 'x' and/or 'y' keys, or null if no translates.
	 */
	private static function _get_position_translates( $position_attrs, $breakpoint, $state = 'value' ) {
		if ( ! $position_attrs || ! $breakpoint ) {
			return null;
		}

		$position_breakpoint = $position_attrs[ $breakpoint ] ?? null;
		if ( ! $position_breakpoint ) {
			return null;
		}

		// Extract state-specific position value.
		// For hover state, do NOT fall back to normal state - only use hover state if it exists.
		// For other states (value, sticky), fall back to value state if state-specific data doesn't exist.
		$state_specific_value = $position_breakpoint[ $state ] ?? null;
		$position_value       = 'hover' === $state
			? $state_specific_value
			: ( $state_specific_value ?? ( $position_breakpoint['value'] ?? null ) );

		if ( ! $position_value ) {
			return null;
		}

		$mode   = $position_value['mode'] ?? null;
		$origin = $position_value['origin'] ?? null;
		if ( ! $origin || ! $mode || 'default' === $mode ) {
			return null;
		}

		// Get the origin value for the current mode.
		// Mode is checked above to be 'relative', 'absolute', or 'fixed' (not 'default').
		$origin_value = $origin[ $mode ] ?? null;
		if ( ! $origin_value ) {
			return self::_get_inherited_position_translate_resets( $origin, $breakpoint );
		}

		$translates = [];

		// Parse origin value (e.g., 'center center', 'top center', etc.).
		$origin_parts = explode( ' ', $origin_value );
		$vertical     = $origin_parts[0] ?? null;
		$horizontal   = $origin_parts[1] ?? null;

		// Check for center horizontal positioning.
		if ( 'center' === $horizontal ) {
			$translates['x'] = '-50%';
		} elseif ( 'desktop' !== $breakpoint ) {
			// Reset translate on responsive breakpoints if not center.
			$translates['x'] = '0px';
		}

		// Check for center vertical positioning.
		if ( 'center' === $vertical ) {
			$translates['y'] = '-50%';
		} elseif ( 'desktop' !== $breakpoint ) {
			// Reset translate on responsive breakpoints if not center.
			$translates['y'] = '0px';
		}

		return ! empty( $translates ) ? $translates : null;
	}

	/**
	 * Get position translates for hover state with proper override logic.
	 * If hover position is set, use it. If not, inherit desktop.
	 * If hover doesn't need centering but desktop does, override with 0px.
	 *
	 * @since ??
	 *
	 * @param array  $position_attrs Position attributes.
	 * @param string $breakpoint Current breakpoint.
	 *
	 * @return array|null Array with 'x' and/or 'y' keys, or null if no translates.
	 */
	private static function _get_hover_position_translates( $position_attrs, $breakpoint ) {
		$position_breakpoint        = $position_attrs[ $breakpoint ] ?? null;
		$hover_position_exists      = ! empty( $position_breakpoint['hover'] );
		$hover_position_translates  = self::_get_position_translates( $position_attrs, $breakpoint, 'hover' );
		$normal_position_translates = self::_get_position_translates( $position_attrs, $breakpoint, 'value' );

		if ( $hover_position_exists ) {
			// Hover position is explicitly set.
			if ( $hover_position_translates ) {
				// Hover needs centering (e.g., center center).
				return $hover_position_translates;
			}

			// Hover doesn't need centering (e.g., top left) but desktop might.
			// Override desktop translates with 0px if they exist.
			if ( $normal_position_translates ) {
				$override = [];
				if ( isset( $normal_position_translates['x'] ) ) {
					$override['x'] = '0px';
				}
				if ( isset( $normal_position_translates['y'] ) ) {
					$override['y'] = '0px';
				}
				return ! empty( $override ) ? $override : null;
			}

			return null;
		}

		// Hover position not set - inherit from desktop.
		return $normal_position_translates;
	}

	/**
	 * Get transform CSS declaration based on given arguments.
	 * Handles both normal and hover states with appropriate position translate logic.
	 *
	 * This function accepts an array of arguments that define the style declaration.
	 * It parses the arguments, sets default values, and generates a CSS style declaration based on the provided arguments.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/style-library/transform-style-declaration/ transformStyleDeclaration}
	 * located in `@divi/style-library` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments that define the style declaration.
	 *
	 *     @type string      $attrValue   The value (breakpoint > state > value) of module attribute.
	 *     @type array|bool  $important   Optional. Whether to add `!important` to the CSS. Default `false`.
	 *     @type string      $returnType  Optional. The return type of the style declaration. Default `string`.
	 *                                    One of `string`, or `key_value_pair`
	 *                                      - If `string`, the style declaration will be returned as a string.
	 *                                      - If `key_value_pair`, the style declaration will be returned as an array of key-value pairs.
	 *     @type array       $additional  Optional. Additional data including positionAttrs and normalStateOrigin. Default [].
	 *     @type string      $breakpoint  Optional. Current breakpoint. Default null.
	 *     @type string      $state       Optional. Current state (value, hover, sticky, etc.). Default 'value'.
	 * }
	 *
	 * @return array|string The generated transform CSS style declaration.
	 *
	 * @example:
	 * ```php
	 * // Example: Generating style declarations with custom transform and transform origin
	 * $args = [
	 *     'attrValue'   => ['origin' => ['x' => '25%', 'y' => '75%']],
	 *     'important'   => true,
	 *     'returnType'  => 'array',
	 * ];
	 * $styleDeclarations = Transform::style_declaration($args);
	 * // Output: [
	 * //     'transform'          => 'none',
	 * //     'transform-origin'   => '25% 75% !important',
	 * // ]
	 * ```
	 */
	public static function style_declaration( array $args ) {
		$args = wp_parse_args(
			$args,
			[
				'important'  => false,
				'returnType' => 'string',
				'additional' => [],
				'breakpoint' => null,
				'state'      => 'value',
			]
		);

		$attr_value         = $args['attrValue'];
		$important          = $args['important'];
		$return_type        = $args['returnType'];
		$additional         = $args['additional'];
		$breakpoint         = $args['breakpoint'];
		$state              = $args['state'];
		$is_hover_state     = 'hover' === $state;
		$style_declarations = new StyleDeclarations(
			[
				'important'  => $important,
				'returnType' => $return_type,
			]
		);

		// Determine origin: for hover, inherit from normal state if not set.
		$origin = $is_hover_state
			? ( $attr_value['origin'] ?? $additional['normalStateOrigin'] ?? null )
			: ( $attr_value['origin'] ?? null );

		// Get position translates based on state.
		$position_attrs      = $additional['positionAttrs'] ?? null;
		$position_translates = null;

		if ( $breakpoint && $position_attrs ) {
			if ( $is_hover_state ) {
				$position_translates = self::_get_hover_position_translates( $position_attrs, $breakpoint );
			} else {
				$position_translates = self::_get_position_translates( $position_attrs, $breakpoint, $state );
			}
		}

		// Merge position translates into attr_value.
		if ( $is_hover_state && $position_translates ) {
			// Hover state: start with empty translate to avoid inheriting desktop position translates.
			$merged_attr_value = array_merge(
				$attr_value,
				[
					'translate' => array_merge(
						isset( $attr_value['translate']['x'] ) ? [ 'x' => $attr_value['translate']['x'] ] : [],
						isset( $attr_value['translate']['y'] ) ? [ 'y' => $attr_value['translate']['y'] ] : [],
						$position_translates
					),
				]
			);
		} elseif ( $position_translates ) {
			// Normal state: position translates are fallback.
			$merged_attr_value = array_merge(
				$attr_value,
				[
					'translate' => [
						'x' => $attr_value['translate']['x'] ?? $position_translates['x'] ?? null,
						'y' => $attr_value['translate']['y'] ?? $position_translates['y'] ?? null,
					],
				]
			);
		} elseif ( $is_hover_state ) {
			// Hover state with no position translates: ensure translate is array.
			$has_translate     = isset( $attr_value['translate'] )
				&& ( isset( $attr_value['translate']['x'] ) || isset( $attr_value['translate']['y'] ) );
			$merged_attr_value = array_merge(
				$attr_value,
				[
					'translate' => $has_translate ? $attr_value['translate'] : [],
				]
			);
		} else {
			$merged_attr_value = $attr_value;
		}

		// Use value() method to process ALL transform properties (scale, rotate, skew, translate).
		$transform_declaration = self::value( $merged_attr_value );

		// Parse transform property.
		if ( ! empty( $transform_declaration ) ) {
			$style_declarations->add( 'transform', $transform_declaration );
		} elseif ( $is_hover_state ) {
			// Hover state: explicitly set transform to 'none' to override normal state.
			$style_declarations->add( 'transform', 'none' );
		}

		// Parse transform-origin property.
		if ( $origin ) {
			$default_origin   = [
				'x' => '50%',
				'y' => '50%',
			];
			$transform_origin = array_merge( $default_origin, $origin );

			$style_declarations->add( 'transform-origin', "{$transform_origin['x']} {$transform_origin['y']}" );
		}

		return $style_declarations->value();
	}
}
