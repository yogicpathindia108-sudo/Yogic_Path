<?php
/**
 * Module Utils Class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleUtils;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use Divi\D5_Readiness\Server\Checks\FeatureCheck;
use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Framework\Utility\TextTransform;
use ET\Builder\Framework\Utility\ArrayUtility;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewUtils;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Fonts;
use ET\Builder\Packages\GlobalData\GlobalPresetItemUtils;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\GlobalData\GlobalPreset;
use ET\Builder\Packages\GlobalData\GlobalPresetItem;
use ET\Builder\FrontEnd\BlockParser\BlockParserBlock;
use Rogervila\ArrayDiffMultidimensional;
use WP_Block_Type_Registry;
use InvalidArgumentException;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;

/**
 * ModuleUtils class.
 *
 * This class provides utility methods for modules.
 *
 * @since ??
 */
class ModuleUtils {

	/**
	 * Remove text shadow style declaration.
	 *
	 * This function handles the removal of text shadow when style is set to 'none'.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array      $attrValue  The value (breakpoint > state > value) of module attribute.
	 *     @type bool|array $important  If set to true, the CSS will be added with !important.
	 *     @type string     $returnType This is the type of value that the function will return.
	 *                                  Can be either string or key_value_pair.
	 * }
	 *
	 * @return string The generated CSS style declaration.
	 */
	public static function remove_text_shadow_style_declaration( $params ) {
		$style = $params['attrValue']['style'] ?? '';

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				// Intentionally hardcoded to false: text-shadow: unset should not use !important
				// as unset naturally takes precedence and !important could interfere with cascade.
				'important'  => false,
			]
		);

		if ( 'none' === $style ) {
			$style_declarations->add( 'text-shadow', 'unset' );
		}

		return $style_declarations->value();
	}
	/**
	 * Cache group
	 *
	 * @var string
	 */
	public static $cache_group = 'divi_module_utils';



	/**
	 * Get the module breakpoints.
	 *
	 * Retrieves an array of module breakpoints including `desktop`, `tablet`, and `phone`.
	 * This function runs the value through the `divi_module_utils_breakpoints` filter.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module-utils/variables/breakpoints breakpoints } located in `@divi/module-utils`.
	 *
	 * @since ??
	 *
	 * @return array The module breakpoints.
	 *
	 * @example:
	 * ```
	 * $breakpoints = ModuleUtils::breakpoints();
	 *
	 * // Output: ['desktop', 'tablet', 'phone']
	 * ```
	 *
	 * @example:
	 * ```php
	 * $breakpoints = apply_filters( 'divi_module_utils_breakpoints', ['desktop', 'tablet', 'phone'] );
	 *
	 * // Output: ['desktop', 'tablet', 'phone']
	 * ```
	 */
	public static function breakpoints(): array {
		// TODO feat(D5, Deprecated): Create class for handling deprecating functions / methdos / constructor / classes. [https://github.com/elegantthemes/Divi/issues/41575]
		// Right now we're using WordPress' `_deprecated_function()` but technically the second parameter here is
		// expected to be WordPress' version, not Divi version. However due to time constraint, we're using Divi version
		// here at the time being.
		_deprecated_function( __METHOD__, '5.0.0-public-alpha-5', 'Breakpoint::get_enabled_breakpoint_names' );

		return Breakpoint::get_enabled_breakpoint_names();
	}

	/**
	 * Retrieve the inherited attribute value based on the given arguments.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module-utils/functions/inheritAttrValue inheritAttrValue} located in
	 * `@divi/module-utils`.
	 *
	 * This function takes an array of arguments and returns the value of the specified attribute.
	 * It first parses the arguments using `wp_parse_args()` and then retrieves the attribute value based on the provided `breakpoint`, `state`, and `mode`.
	 * If the attribute value for the specified `breakpoint` and `state` is not found, it retrieves the inherited value based on the specified `mode`.
	 * If no value is found, it returns `null`.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array  $attr        An array of attribute data.
	 *     @type string $breakpoint  The breakpoint to inherit from.
	 *     @type string $state       The state of the attribute.
	 *     @type string $inheritMode Optional. The mode of inheritance. Default `all`.
	 * }
	 *
	 * @return mixed|null The value of the attribute based on the specified arguments, or null if no value is found.
	 *
	 * @example:
	 * ```php
	 * // Get the value of the 'color' attribute for the 'tablet' breakpoint and 'hover' state.
	 * $args = [
	 *     'attr' => [
	 *         'desktop' => [
	 *             'hover' => '#000000',
	 *         ],
	 *         'tablet' => [
	 *             'hover' => '#ffffff',
	 *         ],
	 *         'phone' => [
	 *             'hover' => '#cccccc',
	 *         ],
	 *     ],
	 *     'breakpoint' => 'tablet',
	 *     'state' => 'hover',
	 *     'inheritMode' => 'all',
	 * ];
	 *
	 * $value = ModuleUtils::inherit_attr_value( $args );
	 * ```
	 *
	 * @example:
	 * ```php
	 * // Get the value of the 'font-size' attribute for the 'phone' breakpoint and 'value' state,
	 * // and inherit the closest value from larger breakpoints.
	 * $args = [
	 *     'attr' => [
	 *         'desktop' => [
	 *             'value' => '14px',
	 *         ],
	 *         'tablet' => [
	 *             'value' => '16px',
	 *         ],
	 *         'phone' => [
	 *             'value' => '18px',
	 *         ],
	 *     ],
	 *     'breakpoint' => 'phone',
	 *     'state' => 'value',
	 *     'inheritMode' => 'closest',
	 * ];
	 *
	 * $value = ModuleUtils::inherit_attr_value( $args );
	 * ```
	 */
	public static function inherit_attr_value( array $args ) {
		$args = wp_parse_args(
			$args,
			[
				'inheritMode'     => 'all',
				'baseBreakpoint'  => 'desktop',
				'breakpointNames' => Breakpoint::get_default_breakpoint_names(),
			]
		);

		$attr             = $args['attr'];
		$base_breakpoint  = $args['baseBreakpoint'];
		$breakpoint       = $args['breakpoint'];
		$breakpoint_names = $args['breakpointNames'];
		$state            = $args['state'];
		$inherit_mode     = $args['inheritMode'];

		// `state` has no order. If the state is not `value`, it means it'll fallback to existing breakpoint + value
		// before fallback to larger breakpoint + value.
		$is_default_state   = 'value' === $state;
		$is_base_breakpoint = $base_breakpoint === $breakpoint;

		// if baseBreakpoint does not exist on breakpointsName, return null.
		if ( false === array_search( $base_breakpoint, $breakpoint_names, true ) ) {
			return null;
		}

		// No breakpoint / state to fallback into. Exit early.
		if ( $is_base_breakpoint && $is_default_state ) {
			return null;
		}

		// Get base breakpoint index.
		$base_breakpoint_index = array_search( $base_breakpoint, $breakpoint_names, true );

		// Get breakpoint index.
		$breakpoint_index = array_search( $breakpoint, $breakpoint_names, true );

		// Check if current breakpoint is wider (min/max-width of media query wise) than base breakpoint.
		$is_wider_than_base_breakpoint = $base_breakpoint_index > $breakpoint_index;

		// Inherit mechanism is derived from base breakpoint, not simply from larger to smaller. Thus in the case of
		// [`ultraWide`, `widescreen`, `desktop`, `tabletWide`, `tablet`, `phoneWide`, `phone`] where the base
		// breakpoint is `desktop`, the breakpoint larger than `desktop` inherits value from `desktop` (in reverse) while
		// breakpoint smaller than `desktop` ALSO inherits value from `desktop`. See the following slack canvas for more:
		// https://elegantthemes.slack.com/docs/T0J2HJAJ2/F08A2KM7BQB.
		$filtered_breakpoint_names = $is_wider_than_base_breakpoint
			? array_reverse( array_slice( $breakpoint_names, 0, $base_breakpoint_index + 1 ) )
			: array_slice( $breakpoint_names, $base_breakpoint_index, count( $breakpoint_names ) );

		// `breakpoints` are ordered in order (pun intended) of size. Thus breakpoints in previous order are
		// guaranteed to be larger breakpoint and cascaded in terms of order.
		$breakpoint_index_on_filtered_breakpoint_names = array_search( $breakpoint, $filtered_breakpoint_names, true );

		// Breakpoints that has larger order (NOT has larger window width) than given breakpoint.
		// The matching breakpoint then needs to be reserved so it is the fallback order.
		$larger_order_breakpoints = array_reverse( array_slice( $filtered_breakpoint_names, 0, $breakpoint_index_on_filtered_breakpoint_names ) );

		// NOTE: The order should be reversed so it fallback in order.
		// Populate inherited attr value.
		$inherited_attr_value = null;

		// If current state isn't default, get value of current breakpoint's default state value.
		if ( ! $is_default_state && isset( $attr[ $breakpoint ]['value'] ) ) {
			$inherited_attr_value = $attr[ $breakpoint ]['value'];
		}

		// Loop for larger breakpoint's default state value.
		$larger_order_breakpoints_count = count( $larger_order_breakpoints );
		for ( $larger_order_breakpoints_index = 0; $larger_order_breakpoints_index < $larger_order_breakpoints_count; $larger_order_breakpoints_index++ ) {
			$current_larger_breakpoint     = $larger_order_breakpoints[ $larger_order_breakpoints_index ];
			$larger_order_breakpoint_value = $attr[ $current_larger_breakpoint ]['value'] ?? null;

			// If the attribute value is object and inheritMode is all (combined all possible inherited value),
			// merge all object from larger breakpoints.
			if ( is_array( $larger_order_breakpoint_value ) && 'all' === $inherit_mode ) {
				$inherited_attr_value = array_replace_recursive( $larger_order_breakpoint_value, (array) $inherited_attr_value );

				// If the attribute value is 1) not object, or 2) an object but inheritMode is closest,
				// simply overwrite the closest one if it isn't exist yet.
			} elseif ( null !== $larger_order_breakpoint_value && null === $inherited_attr_value ) {
				$inherited_attr_value = $larger_order_breakpoint_value;

				// Break loop once valid inherited attr value is found.
				break;

				// Prevent unnecessary loop. Might fall into this if state is not default and inherited attr value
				// is already found.
			} elseif ( null !== $inherited_attr_value && 'closest' === $inherit_mode ) {
				// Break loop once valid inherited attr value is found.
				break;
			}
		}

		return $inherited_attr_value;
	}

	/**
	 * Get an array of breakpoints used for inheritance.
	 *
	 * The static array returned by this function represents the breakpoints for responsive views used in the inheritance logic.
	 *
	 * Top level keys are of type `breakpoint` and second level keys are of type `AttrState`.
	 * The values of the second level keys are arrays of length 2, where both elements are strings.
	 *
	 * @since ??
	 *
	 * @return array The array of breakpoints used for inheritance.
	 *
	 * @example:
	 * ```php
	 * $inheritance = ModuleUtils::inherit_breakpoints();
	 * // Returns:
	 * // [
	 * //    'phone' => [
	 * //        'sticky' => ['phone', 'value'],
	 * //        'hover' => ['phone', 'value'],
	 * //        'value' => ['tablet', 'value']
	 * //    ],
	 * //    'tablet' => [
	 * //        'sticky' => ['tablet', 'value'],
	 * //        'hover' => ['tablet', 'value'],
	 * //        'value' => ['desktop', 'value']
	 * //    ],
	 * //    'desktop' => [
	 * //        'sticky' => ['desktop', 'value'],
	 * //        'hover' => ['desktop', 'value'],
	 * //        'value' => ['desktop', 'value']
	 * //    ]
	 * // ]
	 * ```
	 */
	public static function inherit_breakpoints(): array {
		// TODO feat(D5, Responsive Views): replace this static array with a dynamic one generated from the Builder's settings [https://github.com/elegantthemes/Divi/issues/41620].
		return [
			'phone'   => [
				'active'  => [
					'phone',
					'value',
				],
				'checked' => [
					'phone',
					'value',
				],
				'sticky'  => [
					'phone',
					'value',
				],
				'focus'   => [
					'phone',
					'value',
				],
				'hover'   => [
					'phone',
					'value',
				],
				'value'   => [
					'tablet',
					'value',
				],
			],
			'tablet'  => [
				'active'  => [
					'tablet',
					'value',
				],
				'checked' => [
					'tablet',
					'value',
				],
				'sticky'  => [
					'tablet',
					'value',
				],
				'focus'   => [
					'tablet',
					'value',
				],
				'hover'   => [
					'tablet',
					'value',
				],
				'value'   => [
					'desktop',
					'value',
				],
			],
			'desktop' => [
				'active'  => [
					'desktop',
					'value',
				],
				'checked' => [
					'desktop',
					'value',
				],
				'sticky'  => [
					'desktop',
					'value',
				],
				'focus'   => [
					'desktop',
					'value',
				],
				'hover'   => [
					'desktop',
					'value',
				],
				'value'   => [
					'desktop',
					'value',
				],
			],
		];
	}

	/**
	 * Generates an inherit breakpoint map based on a base breakpoint and a list of breakpoint names.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $base_breakpoint The base breakpoint name.
	 *     @type array $breakpoint_names List of breakpoint names.
	 * }
	 *
	 * @return array Inherit breakpoint map.
	 */
	public static function get_inherit_breakpoint_map( array $args ): array {
		// Returned value of this method is expected to remain the same throughout the page render.
		// Thus for performance reason calculate this once, cache the result, and return the cached value for next calls.
		$cache_key = __FUNCTION__;

		$cached_value = wp_cache_get( $cache_key, self::$cache_group );

		if ( false !== $cached_value ) {
			return $cached_value;
		}

		$base_breakpoint  = $args['base_breakpoint'] ?? 'desktop';
		$breakpoint_names = $args['breakpoint_names'] ?? [
			'desktop',
			'tablet',
			'phone',
		];

		$inherit_breakpoint_map = [];

		$base_breakpoint_index = array_search( $base_breakpoint, $breakpoint_names, true );

		foreach ( $breakpoint_names as $index => $name ) {
			if ( $base_breakpoint === $name ) {
				$inherit_breakpoint_map[ $name ] = [
					'active'  => [ $name, 'value' ],
					'checked' => [ $name, 'value' ],
					'sticky'  => [ $name, 'value' ],
					'focus'   => [ $name, 'value' ],
					'hover'   => [ $name, 'value' ],
					'value'   => [ $name, 'value' ],
				];
			} else {
				$inherit_breakpoint_index = $index > $base_breakpoint_index ? $index - 1 : $index + 1;

				$inherit_breakpoint_map[ $name ] = [
					'active'  => [ $name, 'value' ],
					'checked' => [ $name, 'value' ],
					'sticky'  => [ $name, 'value' ],
					'focus'   => [ $name, 'value' ],
					'hover'   => [ $name, 'value' ],
					'value'   => [ $breakpoint_names[ $inherit_breakpoint_index ], 'value' ],
				];
			}
		}

		// Cache the result.
		wp_cache_set( $cache_key, $inherit_breakpoint_map, self::$cache_group );

		return $inherit_breakpoint_map;
	}

	/**
	 * Retrieve the value of an attribute based on the provided arguments.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module-utils/functions/getAttrValue/ getAttrValue} located in
	 * `@divi/module-utils`.
	 *
	 * This function takes an array of arguments and returns the value of the specified attribute.
	 * The function first parses the arguments using `wp_parse_args()`. It then retrieves the attribute value based on the specified breakpoint, state, and mode.
	 * If the attribute value for the specified breakpoint and state is not found, it retrieves the inherited value based on the specified mode.
	 * If no value is found, the function returns the default value specified in the arguments.
	 *
	 * Getter and inheritance model can be changed based on `mode` parameter:
	 * 1. `get`                  : Get attr value of given breakpoint + state.
	 * 2. `getAndInheritAll`     : Get attr value combined by all possible inherited attr value on all larger breakpoints.
	 * 3. `getAndInheritClosest` : Get attr value combined by inherited attr value from closest available breakpoint.
	 * 4. `getOrInheritAll`      : Get attr value or inherited attr value from all larger breakpoints.
	 * 5. `getOrInheritClosest`  : Get attr value or inherited attr value from closest available breakpoint.
	 * 6. `inheritAll`           : Get inherited attr value from all larger breakpoints.
	 * 7. `inheritClosest`       : Get inherited attr value from all closest available breakpoint.
	 *
	 *
	 * See below for inherited attribute fallback flow:
	 *
	 * |        | value | hover | sticky |
	 * |--------|-------|-------|--------|
	 * | Desktop|   *   |  <--  |  <--   |
	 * | Tablet |   ^   |  <--  |  <--   |
	 * | Phone  |   ^   |  <--  |  <--   |
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array        $attr          The attribute to retrieve the value from.
	 *     @type string       $breakpoint    The breakpoint.
	 *     @type string       $state         The state.
	 *     @type string       $mode          Optional. The mode. Default `getOrInheritAll`.
	 *     @type mixed|null   $defaultValue  Optional. The default value. Default `null`.
	 *     @type string       $baseBreakpoint Optional. The base breakpoint. Default `desktop`.
	 *     @type array        $breakpointNames Optional. The breakpoint names. Default `['desktop', 'tablet', 'phone']`.
	 * }
	 *
	 * @return mixed|null The value of the attribute based on the specified arguments, or the default value if no value is found.
	 */
	public static function get_attr_value( array $args ) {
		$args = wp_parse_args(
			$args,
			[
				'mode'            => 'getOrInheritAll',
				'defaultValue'    => null,
				'baseBreakpoint'  => 'desktop',
				'breakpointNames' => Breakpoint::get_default_breakpoint_names(),
			]
		);

		$attr             = $args['attr'];
		$base_breakpoint  = $args['baseBreakpoint'];
		$breakpoint       = $args['breakpoint'];
		$breakpoint_names = $args['breakpointNames'];
		$state            = $args['state'];
		$mode             = $args['mode'];
		$default_value    = $args['defaultValue'];

		// Get attribute value.
		$attr_value = isset( $attr[ $breakpoint ][ $state ] ) ? $attr[ $breakpoint ][ $state ] : null;

		// Get inherited value.
		$inherited_attr_value = null;

		switch ( $mode ) {
			case 'getAndInheritClosest':
			case 'getOrInheritClosest':
			case 'inheritClosest':
				$inherited_attr_value = self::inherit_attr_value(
					[
						'attr'            => $attr,
						'baseBreakpoint'  => $base_breakpoint,
						'breakpoint'      => $breakpoint,
						'breakpointNames' => $breakpoint_names,
						'state'           => $state,
						'inheritMode'     => 'closest',
					]
				);
				break;

			// Default is for *InheritAll mode:
			// - 'getAndInheritAll'
			// - 'getOrInheritAll'
			// - 'inheritAll'
			// - 'get'.
			default:
				$inherited_attr_value = self::inherit_attr_value(
					[
						'attr'            => $attr,
						'baseBreakpoint'  => $base_breakpoint,
						'breakpoint'      => $breakpoint,
						'breakpointNames' => $breakpoint_names,
						'state'           => $state,
						'inheritMode'     => 'all',
					]
				);
				break;
		}

		// Get returned value based on its mode.
		$returned_attr_value = null;

		switch ( $mode ) {
			case 'getAndInheritAll':
			case 'getAndInheritClosest':
				// Combine attrValue and inherited value.
				if ( is_array( $attr_value ) && is_array( $inherited_attr_value ) ) {
					$returned_attr_value = array_replace_recursive( $inherited_attr_value, $attr_value );
				} else {
					$returned_attr_value = null !== $attr_value ? $attr_value : $inherited_attr_value;
				}
				break;
			case 'getOrInheritAll':
			case 'getOrInheritClosest':
				$returned_attr_value = null !== $attr_value ? $attr_value : $inherited_attr_value;
				break;
			case 'inheritAll':
			case 'inheritClosest':
				$returned_attr_value = $inherited_attr_value;
				break;

			// Default stands for mode === 'get'.
			default:
				$returned_attr_value = $attr_value;
				break;
		}

		return null !== $returned_attr_value ? $returned_attr_value : $default_value;
	}

	/**
	 * Retrieve the value of an attribute based on the provided arguments and factoring it enabled breakpoints and base
	 * breakpoint value. This is function is wrapper for `ModuleUtils::get_attr_value()` which automatically pass
	 * `breakpointNames` and `baseBreakpoint` property arguments to simplify its usage.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array        $attr          The attribute to retrieve the value from.
	 *     @type string       $breakpoint    The breakpoint.
	 *     @type string       $state         The state.
	 *     @type string       $mode          Optional. The mode. Default `getOrInheritAll`.
	 *     @type mixed|null   $defaultValue  Optional. The default value. Default `null`.
	 * }
	 *
	 * @since ??
	 *
	 * @return mixed|null The value of the attribute based on the specified arguments, or the default value if no value is found.
	 */
	public static function use_attr_value( array $args ) {
		$updated_args = array_merge(
			$args,
			[
				'baseBreakpoint'  => Breakpoint::get_base_breakpoint_name(),
				'breakpointNames' => Breakpoint::get_enabled_breakpoint_names(),
			]
		);

		return self::get_attr_value( $updated_args );
	}

	/**
	 * Get the inheritance breakpoint for a given breakpoint and state.
	 *
	 * This function retrieves the target inheritance breakpoint for a given breakpoint and state.
	 * It is used to determine the inherited attribute values.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $breakpoint The breakpoint to get the inheritance breakpoint for.
	 *     @type string $state      The state to get the inheritance breakpoint for.
	 *     @type string $baseBreakpoint The base breakpoint.
	 *     @type array  $breakpointNames The breakpoint names.
	 * }
	 *
	 * @return string The inheritance breakpoint for the given breakpoint and state.
	 *
	 * @example:
	 * ```php
	 * // Get the inheritance breakpoint for the 'tablet' breakpoint and 'hover' state
	 * $inherit_breakpoint = ModuleUtils::get_inherit_breakpoint(
	 *   [
	 *     'breakpoint'      => 'tablet',
	 *     'state'           => 'hover',
	 *     'baseBreakpoint'  => 'desktop',
	 *     'breakpointNames' => [ 'desktop', 'tablet', 'phone' ],
	 *   ]
	 * );
	 * echo $inherit_breakpoint;
	 *
	 * // Output: 'desktop'
	 * ```

	 * @example:
	 * ```php
	 * // Get the inheritance breakpoint for the default 'desktop' breakpoint and 'value' state
	 * $inherit_breakpoint = ModuleUtils::get_inherit_breakpoint();
	 * echo $inherit_breakpoint;
	 *
	 * // Output: 'desktop'
	 * ```
	 */
	public static function get_inherit_breakpoint( array $args ): string {
		$breakpoint       = $args['breakpoint'] ?? 'desktop';
		$state            = $args['state'] ?? 'value';
		$base_breakpoint  = $args['baseBreakpoint'] ?? 'desktop';
		$breakpoint_names = $args['breakpointNames'] ?? [
			'desktop',
			'tablet',
			'phone',
		];

		$inherit_breakpoints = self::get_inherit_breakpoint_map(
			[
				'base_breakpoint'  => $base_breakpoint,
				'breakpoint_names' => $breakpoint_names,
			]
		);

		return $inherit_breakpoints[ $breakpoint ][ $state ][0];
	}

	/**
	 * Get the inheritance state for a given breakpoint and state.
	 *
	 * This function retrieves the target inheritance state for a given breakpoint and state.
	 * It is used in conjunction with the ModuleUtils::get_inherit_breakpoint()` function to determine the inherited attribute values.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $breakpoint The breakpoint to get the inheritance state for.
	 *     @type string $state      The state to get the inheritance state for.
	 *     @type string $baseBreakpoint The base breakpoint.
	 *     @type array  $breakpointNames The breakpoint names.
	 * }
	 *
	 * @return string The inheritance state for the given breakpoint and state.
	 *
	 * @example:
	 * ```php
	 * // Get the inheritance state for the 'tablet' breakpoint and 'hover' state
	 * $inherit_state = ModuleUtils::get_inherit_state(
	 *   [
	 *     'breakpoint'      => 'tablet',
	 *     'state'           => 'hover',
	 *     'baseBreakpoint'  => 'desktop',
	 *     'breakpointNames' => [ 'desktop', 'tablet', 'phone' ],
	 *   ]
	 * );
	 * echo $inherit_state;
	 *
	 * // Output: 'value_hover'
	 * ```
	 *
	 * @example:
	 * ```php
	 * // Get the inheritance state for the default 'desktop' breakpoint and 'value' state
	 * $inherit_state = ModuleUtils::get_inherit_state();
	 * echo $inherit_state;
	 *
	 * // Output: 'value'
	 * ```
	 */
	public static function get_inherit_state( array $args ): string {
		$breakpoint       = $args['breakpoint'] ?? 'desktop';
		$state            = $args['state'] ?? 'value';
		$base_breakpoint  = $args['baseBreakpoint'] ?? 'desktop';
		$breakpoint_names = $args['breakpointNames'] ?? [
			'desktop',
			'tablet',
			'phone',
		];

		$inherit_breakpoints = self::get_inherit_breakpoint_map(
			[
				'base_breakpoint'  => $base_breakpoint,
				'breakpoint_names' => $breakpoint_names,
			]
		);

		return $inherit_breakpoints[ $breakpoint ][ $state ][1];
	}

	/**
	 * Recursively trim all values in an array.
	 *
	 * This function calls `ModuleUtils::_array_trim()` to trim the values.
	 *
	 * @since ??
	 *
	 * @param array $input The input array.
	 *
	 * @return array The trimmed array.
	 */
	private static function _array_trim( array $input ): array {
		return array_filter(
			$input,
			function ( $value, $key ) {
				if ( is_array( $value ) ) {
					$value = self::_array_trim( $value );
				}
				// In the background, we have "remove" (trash icon) concept where we can remove value from certain breakpoint without
				// inheriting the value from the larger breakpoint. In this case, we need to allow empty string as a valid value for
				// certain properties.
				$is_allowed_empty_string = '' === $value && in_array(
					$key,
					[
						'url',
						'color',
					],
					true
				);

				return ! empty( $value ) || $is_allowed_empty_string;
			},
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * Recursively compare two multidimensional arrays to check if they are the same.
	 *
	 * This function trims all values in the arrays recursively using the `ModuleUtils::_array_trim()` method.
	 * It then uses the `ArrayDiffMultidimensional::compare()` to compare the difference between
	 * the two multidimensional arrays.
	 *
	 * This function works like the PHP `array_diff()` function, but with multidimensional arrays.
	 *
	 * @since ??
	 *
	 * @param array $array1 The first array to compare.
	 * @param array $array2 The second array to compare.
	 *
	 * @return bool Returns `true` if the arrays are the same, `false` otherwise.
	 */
	private static function _is_same( array $array1, array $array2 ): bool {
		$array1 = self::_array_trim( $array1 );
		$array2 = self::_array_trim( $array2 );

		$diff = ArrayDiffMultidimensional::compare( $array1, $array2 );

		return empty( $diff );
	}

	/**
	 * Check if the background attribute setting is enabled.
	 *
	 * This function checks if the `enabled` attribute is set in the given attribute group.
	 *
	 * If the `enabled` attribute is not present and strict comparison is enabled, it returns `false`.
	 * If the `enabled` attribute is not present and strict comparison is not enabled, it returns true.
	 * If the `enabled` attribute is present, it returns `true` if it is set to `'on'`, and `false` otherwise.
	 *
	 * @since ??
	 *
	 * @param array $attr_group The attribute group to check.
	 * @param bool  $strict     Whether to make a strict comparison. Default `false`.
	 *
	 * @return bool Whether the background attribute setting is enabled.
	 *
	 * @example:
	 * ```php
	 *   // Example 1: Check if background is enabled without strict comparison.
	 *   $attr_group = [
	 *       'enabled' => 'on',
	 *       // other attributes...
	 *   ];
	 *   $result = ModuleUtils::_is_background_attr_enabled( $attr_group );
	 *   // Output: true
	 *
	 *   // Example 2: Check if background is enabled with strict comparison.
	 *   $attr_group = [
	 *       'enabled' => 'on',
	 *       // other attributes...
	 *   ];
	 *   $result = ModuleUtils::_is_background_attr_enabled( $attr_group, true) ;
	 *   // Output: false
	 *
	 *   // Example 3: Check if background is disabled without strict comparison.
	 *   $attr_group = [
	 *       'enabled' => 'off',
	 *       // other attributes...
	 *   ];
	 *   $result = ModuleUtils::_is_background_attr_enabled( $attr_group );
	 *   // Output: false
	 * ```
	 */
	private static function _is_background_attr_enabled( array $attr_group, bool $strict = false ): bool {
		$has_enabled = isset( $attr_group['enabled'] );

		// If we're making a strict comparison, we'll presume this is disabled if we
		// don't have an `enabled` attribute.
		if ( ! $has_enabled && $strict ) {
			return false;
		}

		// If we don't have an `enabled` attribute at this point, we'll presume that
		// the setting is enabled.
		if ( ! $has_enabled ) {
			return true;
		}

		// If we have an `enabled` attribute, we'll return whether it's set to `on`.
		return 'on' === $attr_group['enabled'];
	}

	/**
	 * Normalize background attribute groups to arrays for inheritance routines.
	 *
	 * Legacy or migrated content can store gradient/image groups as scalar values
	 * (e.g. a token string). Convert known scalar forms into the expected shape.
	 *
	 * @since ??
	 *
	 * @param mixed  $attr_group Raw attribute group value.
	 * @param string $group_name Group name (`gradient` or `image`).
	 *
	 * @return array
	 */
	private static function _normalize_background_attr_group( $attr_group, string $group_name ): array {
		if ( is_array( $attr_group ) ) {
			return $attr_group;
		}

		if ( ! is_string( $attr_group ) || '' === $attr_group ) {
			return [];
		}

		if ( 'gradient' === $group_name ) {
			return [ 'stops' => $attr_group ];
		}

		if ( 'image' === $group_name ) {
			return [ 'url' => $attr_group ];
		}

		return [];
	}

	/**
	 * Inherit attribute values for background.
	 *
	 * This function takes an array of attribute values with inherited values and a breakpoint and state
	 * to determine the appropriate inheritance. It then merges the attribute values from the specified
	 * breakpoint and state with their parent values, accounting for enabled or disabled attributes.
	 *
	 * @since ??
	 *
	 * @param array  $attr_value_with_inherited The attribute values with inherited values. This is a multi-dimensional array
	 *                                          with breakpoints and states as keys.
	 * @param string $breakpoint                The breakpoint to get the inheritance breakpoint for. One of `desktop`, `tablet`, `phone`.
	 * @param string $state                     The state to get the inheritance breakpoint for.
	 *                                          One of `value`, `hover`, `tablet_value`, `tablet_hover`, `phone_value`, `phone_hover`.
	 *
	 * @return array The attribute values with inherited values.
	 *
	 * @example:
	 * ```php
	 *     $attr_value_with_inherited = [
	 *         'desktop' => [
	 *             'value' => [
	 *                 'color' => '#000',
	 *                 'gradient' => [
	 *                     'enabled' => 'on',
	 *                     'stops' => [
	 *                         'stop1' => '#fff',
	 *                         'stop2' => '#000'
	 *                     ]
	 *                 ],
	 *                 'image' => [
	 *                     'enabled' => 'off',
	 *                     'source' => 'image.jpg'
	 *                 ]
	 *             ]
	 *         ]
	 *     ];
	 *     $breakpoint = 'desktop';
	 *     $state = 'value';
	 *
	 *     $result = ModuleUtils_inherit_background_values( $attr_value_with_inherited, $breakpoint, $state );
	 *
	 *     // $result is:
	 *     // [
	 *     //     'desktop' => [
	 *     //         'value' => [
	 *     //             'color' => '#000',
	 *     //             'gradient' => [
	 *     //                 'enabled' => 'on',
	 *     //                 'stops' => [
	 *     //                     'stop1' => '#fff',
	 *     //                     'stop2' => '#000'
	 *     //                 ]
	 *     //             ],
	 *     //             'image' => [
	 *     //                 'enabled' => 'off',
	 *     //                 'source' => 'image.jpg'
	 *     //             ]
	 *     //         ]
	 *     //     ]
	 *     // ]
	 * ```
	 */
	private static function _inherit_background_values( array $attr_value_with_inherited, string $breakpoint, string $state ): array {
		// TODO feat(D5, Refactor): pass breakpointNames and baseBreakpoint as arguments [https://github.com/elegantthemes/Divi/issues/41620].
		$base_breakpoint    = Breakpoint::get_base_breakpoint_name();
		$breakpoint_names   = Breakpoint::get_enabled_breakpoint_names();
		$inherit_breakpoint = self::get_inherit_breakpoint(
			[
				'breakpoint'      => $breakpoint,
				'state'           => $state,
				'baseBreakpoint'  => $base_breakpoint,
				'breakpointNames' => $breakpoint_names,
			]
		);
		$inherit_state      = self::get_inherit_state(
			[
				'breakpoint'      => $breakpoint,
				'state'           => $state,
				'baseBreakpoint'  => $base_breakpoint,
				'breakpointNames' => $breakpoint_names,
			]
		);

		$attr_values                   = $attr_value_with_inherited[ $breakpoint ][ $state ] ?? [];
		$attr_parent_values            = $attr_value_with_inherited[ $inherit_breakpoint ][ $inherit_state ] ?? [];
		$current_gradient_attr_group   = self::_normalize_background_attr_group( $attr_values['gradient'] ?? [], 'gradient' );
		$inherited_gradient_attr_group = self::_normalize_background_attr_group( $attr_parent_values['gradient'] ?? [], 'gradient' );
		$current_image_attr_group      = self::_normalize_background_attr_group( $attr_values['image'] ?? [], 'image' );
		$inherited_image_attr_group    = self::_normalize_background_attr_group( $attr_parent_values['image'] ?? [], 'image' );

		$attr_value_with_inherited[ $breakpoint ][ $state ] = self::_array_trim(
			[
				'color'    => $attr_values['color'] ?? $attr_parent_values['color'] ?? null,
				'gradient' => self::_array_trim(
					self::_is_background_attr_enabled( $current_gradient_attr_group )
						? array_merge(
							[],
							$inherited_gradient_attr_group,
							$current_gradient_attr_group,
							[
								'stops' => $current_gradient_attr_group['stops'] ?? $inherited_gradient_attr_group['stops'] ?? [],
							]
						)
						: [
							'enabled' => $current_gradient_attr_group['enabled'] ?? 'off',
						]
				),
				'image'    => self::_array_trim(
					self::_is_background_attr_enabled( $current_image_attr_group )
					? array_merge(
						[],
						$inherited_image_attr_group,
						$current_image_attr_group
					)
					: [
						'enabled' => $current_image_attr_group['enabled'] ?? 'off',
					]
				),
				'mask'     => self::_array_trim(
					is_array( $attr_values['mask'] ?? [] ) && self::_is_background_attr_enabled( $attr_values['mask'] ?? [] )
						? array_merge( [], $attr_parent_values['mask'] ?? [], $attr_values['mask'] ?? [] )
						: [
							'enabled' => is_array( $attr_values['mask'] ?? [] ) ? ( $attr_values['mask']['enabled'] ?? 'off' ) : 'off',
						]
				),
				'pattern'  => self::_array_trim(
					is_array( $attr_values['pattern'] ?? [] ) && self::_is_background_attr_enabled( $attr_values['pattern'] ?? [] )
						? array_merge( [], $attr_parent_values['pattern'] ?? [], $attr_values['pattern'] ?? [] )
						: [
							'enabled' => is_array( $attr_values['pattern'] ?? [] ) ? ( $attr_values['pattern']['enabled'] ?? 'off' ) : 'off',
						]
				),
			]
		);

		return $attr_value_with_inherited;
	}

	/**
	 * Whether a background state that matches or collapses to empty should be kept for a
	 * smaller breakpoint when its inheritance parent is not the base breakpoint.
	 *
	 * Deduplication normally removes child rows that duplicate the immediate parent. When the
	 * parent is an intermediate responsive breakpoint (e.g. tabletWide), removing the child
	 * (e.g. tablet) drops markup that frontend parallax CSS expects per viewport band (#48201).
	 *
	 * @param string $breakpoint        Current breakpoint name.
	 * @param string $parent_breakpoint Inheritance parent breakpoint name.
	 * @param string $base_breakpoint   Base breakpoint name (typically desktop).
	 *
	 * @return bool True when the child row should be materialized even if redundant.
	 */
	private static function _should_materialize_responsive_background_row( string $breakpoint, string $parent_breakpoint, string $base_breakpoint ): bool {
		if ( $breakpoint === $parent_breakpoint ) {
			return false;
		}
		if ( $base_breakpoint === $parent_breakpoint ) {
			return false;
		}

		// Legacy desktop → tablet → phone chain: phone still dedupes against tablet when identical.
		// Full breakpoint lists use tabletWide / phoneWide so tablet is not the direct parent of phone.
		if ( 'phone' === $breakpoint && 'tablet' === $parent_breakpoint ) {
			return false;
		}

		// Only materialize the sub-desktop steps that parallax + responsive CSS expect as separate
		// rows. A broad "any parent !== base" rule also matched ultraWide/widescreen pairs and
		// duplicated rows in ways that broke other viewport bands (e.g. tabletWide on FE) (#48201).
		$materialize_pairs = [
			[ 'tablet', 'tabletWide' ],
			[ 'phoneWide', 'tablet' ],
			[ 'phone', 'phoneWide' ],
		];

		foreach ( $materialize_pairs as $pair ) {
			if ( $pair[0] === $breakpoint && $pair[1] === $parent_breakpoint ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the original background attr uses nested multi-view keys (e.g. tablet.desktop.value)
	 * instead of flat breakpoint.state (tablet.value).
	 *
	 * @param array  $original_attr Original attr before inheritance.
	 * @param string $breakpoint      Breakpoint name.
	 * @param string $state           State name.
	 *
	 * @return bool True when layout is nested multi-view.
	 */
	private static function _is_nested_multiview_background_attr( array $original_attr, string $breakpoint, string $state ): bool {
		if ( ! isset( $original_attr[ $breakpoint ] ) || ! is_array( $original_attr[ $breakpoint ] ) ) {
			return false;
		}

		$breakpoint_attr = $original_attr[ $breakpoint ];

		if ( array_key_exists( $state, $breakpoint_attr ) ) {
			return false;
		}

		$nested_view_keys = [ 'desktop', 'tablet', 'phone' ];

		foreach ( array_keys( $breakpoint_attr ) as $key ) {
			if ( in_array( $key, $nested_view_keys, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get attribute values with inherited values.
	 *
	 * This function compares each breakpoint and state using the `inheritBreakpoints` object
	 * and, starting with `phone.sticky` and moving to `desktop.value`, deletes any object
	 * that completely matches its parent breakpoint and state. It will always keep the
	 * `desktop.value` object if it exists. The function retrieves the default `attrValue` on
	 * the current breakpoint and state.
	 *
	 * @since ??
	 *
	 * @param array  $attr_to_be_returned The attribute values with inherited values.
	 * @param string $breakpoint          The breakpoint to get the inheritance breakpoint for. One of `desktop`, `tablet`, `phone`.
	 * @param string $state               The state to get the inheritance breakpoint for.
	 *                                    One of `value`, `hover`, `tablet_value`, `tablet_hover`, `phone_value`, `phone_hover`.
	 * @param array  $original_attr              Original attributes before inheritance. Used for nested multi-view background detection.
	 * @param array  $inherited_attr_for_lookup Pre-dedup inherited tree; used to match TS getAndInheritBackgroundAttr lookups (#48201).
	 *
	 * @return array Cleaned attribute values.
	 *
	 * @example:
	 * ```php
	 * $attr_to_be_returned = [
	 *     'desktop' => [
	 *         'value' => [
	 *             'color'    => '#ffffff',
	 *             'mask'     => [],
	 *             'pattern'  => [],
	 *             'image'    => [],
	 *             'gradient' => [],
	 *         ],
	 *     ],
	 *     'tablet'  => [
	 *         'value' => [
	 *             'color'    => '#000000',
	 *             'mask'     => [],
	 *             'pattern'  => [],
	 *             'image'    => [],
	 *             'gradient' => [],
	 *         ],
	 *     ],
	 *     'phone'   => [
	 *         'value' => [
	 *             'color'    => '#ff0000',
	 *             'mask'     => [],
	 *             'pattern'  => [],
	 *         'image'    => [],
	 *         'gradient' => [],
	 *     ],
	 *    ],
	 * ];
	 * $breakpoint = 'desktop';
	 * $state = 'value';
	 *
	 * $result = ModuleUtils_return_background_values( $attr_to_be_returned, $breakpoint, $state );
	 * // $result is:
	 * // [
	 * //     'desktop' => [
	 * //         'value' => [
	 * //             'color' => '#ffffff',
	 * //         ],
	 * //     ],
	 * //     'tablet' => [
	 * //         'value' => [
	 * //             'color' => '#000000',
	 * //         ],
	 * //     ],
	 * //     'phone' => [
	 * //         'value' => [
	 * //             'color' => '#ff0000',
	 * //         ],
	 * //     ],
	 * // ]
	 * ```
	 */
	private static function _return_background_values( array $attr_to_be_returned, string $breakpoint, string $state, array $original_attr = [], array $inherited_attr_for_lookup = [] ): array {
		// TODO feat(D5, Refactor): pass breakpointNames and baseBreakpoint as arguments [https://github.com/elegantthemes/Divi/issues/41620].
		$base_breakpoint   = Breakpoint::get_base_breakpoint_name();
		$breakpoint_names  = Breakpoint::get_enabled_breakpoint_names();
		$parent_breakpoint = self::get_inherit_breakpoint(
			[
				'breakpoint'      => $breakpoint,
				'state'           => $state,
				'baseBreakpoint'  => $base_breakpoint,
				'breakpointNames' => $breakpoint_names,
			]
		);
		$parent_state      = self::get_inherit_state(
			[
				'breakpoint'      => $breakpoint,
				'state'           => $state,
				'baseBreakpoint'  => $base_breakpoint,
				'breakpointNames' => $breakpoint_names,
			]
		);
		$is_desktop_value  = 'desktop' === $breakpoint && 'value' === $state;

		// Background Color. Match TS getAndInheritBackgroundAttr: parent prefers normalized return row,
		// then pre-dedup inherited; current color is always read from pre-dedup inherited (#48201 / parity).
		$parent_row_returned  = $attr_to_be_returned[ $parent_breakpoint ][ $parent_state ] ?? [];
		$parent_row_inherited = ( $inherited_attr_for_lookup[ $parent_breakpoint ] ?? [] )[ $parent_state ] ?? [];
		$parent_color         = $parent_row_returned['color'] ?? $parent_row_inherited['color'] ?? null;

		$current_row_inherited  = ( $inherited_attr_for_lookup[ $breakpoint ] ?? [] )[ $state ] ?? [];
		$use_inherited_lookup   = ! empty( $inherited_attr_for_lookup );
		$current_color          = $use_inherited_lookup
			? ( $current_row_inherited['color'] ?? null )
			: ( $attr_to_be_returned[ $breakpoint ][ $state ]['color'] ?? null );
		$is_colors_match        = $current_color === $parent_color;
		$is_current_color_empty = is_null( $current_color );

		/*
		 * If the current color is an empty string, then it's intentionally blank
		 * and should not inherit the parent's value.
		 */
		$color_or_initial = ! $is_desktop_value && '' === $current_color ? 'initial' : $current_color;

		// Add inherited background color values if necessary.
		if ( ! $is_current_color_empty && ( $is_desktop_value || ! $is_colors_match ) ) {
			$attr_to_be_returned[ $breakpoint ][ $state ]['color'] = $color_or_initial;
		} else {
			unset( $attr_to_be_returned[ $breakpoint ][ $state ]['color'] );
		}

		// Background Mask. TS returnAttrValues reads mask/pattern/image/gradient from pre-dedup inherited only.
		$parent_mask           = $use_inherited_lookup
			? ( $parent_row_inherited['mask'] ?? [] )
			: ( $attr_to_be_returned[ $parent_breakpoint ][ $parent_state ]['mask'] ?? [] );
		$current_mask          = $use_inherited_lookup
			? ( $current_row_inherited['mask'] ?? [] )
			: ( $attr_to_be_returned[ $breakpoint ][ $state ]['mask'] ?? [] );
		$is_masks_match        = self::_is_same( $current_mask, $parent_mask );
		$is_current_mask_empty = empty( self::_array_trim( $current_mask ) );

		// Add inherited background mask values if necessary.
		if ( ! $is_current_mask_empty && ( $is_desktop_value || ! $is_masks_match ) ) {
			$attr_to_be_returned[ $breakpoint ][ $state ]['mask'] = $current_mask;
		} else {
			unset( $attr_to_be_returned[ $breakpoint ][ $state ]['mask'] );
		}

		// Background Pattern.
		$parent_pattern           = $use_inherited_lookup
			? ( $parent_row_inherited['pattern'] ?? [] )
			: ( $attr_to_be_returned[ $parent_breakpoint ][ $parent_state ]['pattern'] ?? [] );
		$current_pattern          = $use_inherited_lookup
			? ( $current_row_inherited['pattern'] ?? [] )
			: ( $attr_to_be_returned[ $breakpoint ][ $state ]['pattern'] ?? [] );
		$is_patterns_match        = self::_is_same( $current_pattern, $parent_pattern );
		$is_current_pattern_empty = empty( self::_array_trim( $current_pattern ) );

		// Add inherited background pattern values if necessary.
		if ( ! $is_current_pattern_empty && ( $is_desktop_value || ! $is_patterns_match ) ) {
			$attr_to_be_returned[ $breakpoint ][ $state ]['pattern'] = $current_pattern;
		} else {
			unset( $attr_to_be_returned[ $breakpoint ][ $state ]['pattern'] );
		}

		// Background Image and Gradient.
		$parent_gradient           = $use_inherited_lookup
			? ( $parent_row_inherited['gradient'] ?? [] )
			: ( $attr_to_be_returned[ $parent_breakpoint ][ $parent_state ]['gradient'] ?? [] );
		$current_gradient          = $use_inherited_lookup
			? ( $current_row_inherited['gradient'] ?? [] )
			: ( $attr_to_be_returned[ $breakpoint ][ $state ]['gradient'] ?? [] );
		$is_gradients_match        = self::_is_same( $current_gradient, $parent_gradient );
		$is_current_gradient_empty = empty( self::_array_trim( $current_gradient ) );

		$parent_image           = $use_inherited_lookup
			? ( $parent_row_inherited['image'] ?? [] )
			: ( $attr_to_be_returned[ $parent_breakpoint ][ $parent_state ]['image'] ?? [] );
		$current_image          = $use_inherited_lookup
			? ( $current_row_inherited['image'] ?? [] )
			: ( $attr_to_be_returned[ $breakpoint ][ $state ]['image'] ?? [] );
		$is_images_match        = self::_is_same( $current_image, $parent_image );
		$is_current_image_empty = empty( self::_array_trim( $current_image ) );

		$is_image_and_gradient_empty = $is_current_image_empty && $is_current_gradient_empty;

		// Add background image and gradient values together.
		if ( ! $is_image_and_gradient_empty && ( $is_desktop_value || ( ! $is_images_match || ! $is_gradients_match ) ) ) {
			if ( ! $is_current_gradient_empty ) {
				$attr_to_be_returned[ $breakpoint ][ $state ]['gradient'] = $current_gradient;
			}
			if ( ! $is_current_image_empty ) {
				$attr_to_be_returned[ $breakpoint ][ $state ]['image'] = $current_image;
			}
		} elseif ( $is_image_and_gradient_empty ) {
			// If both image and gradient are empty, inherit from parent one at a time.
			if ( ! $is_gradients_match && ! empty( $parent_gradient ) ) {
				$attr_to_be_returned[ $breakpoint ][ $state ]['gradient'] = $parent_gradient;
			}
			if ( ! $is_images_match && ! empty( $parent_image ) ) {
				$attr_to_be_returned[ $breakpoint ][ $state ]['image'] = $parent_image;
			}
		}

		if ( ! $is_desktop_value ) {
			// When an intermediate breakpoint row was removed (e.g. tablet.value deduped), comparisons must
			// still use the immediate parent's merged values so children match TS returnAttrValues (#48201).
			$parent_row_for_dedupe = [];
			if ( isset( $attr_to_be_returned[ $parent_breakpoint ] ) && array_key_exists( $parent_state, $attr_to_be_returned[ $parent_breakpoint ] ) ) {
				$parent_row_for_dedupe = $attr_to_be_returned[ $parent_breakpoint ][ $parent_state ];
			} elseif ( $use_inherited_lookup ) {
				$parent_row_for_dedupe = $parent_row_inherited;
			}

			$is_images_match    = false;
			$is_gradients_match = false;

			if (
				self::_is_same(
					$attr_to_be_returned[ $breakpoint ][ $state ]['image'] ?? [],
					$parent_row_for_dedupe['image'] ?? []
				)
			) {
				$is_images_match = true;
			}

			if (
				self::_is_same(
					$attr_to_be_returned[ $breakpoint ][ $state ]['gradient'] ?? [],
					$parent_row_for_dedupe['gradient'] ?? []
				)
			) {
				$is_gradients_match = true;
			}

			if ( $is_images_match && $is_gradients_match ) {
				unset( $attr_to_be_returned[ $breakpoint ][ $state ]['image'] );
				unset( $attr_to_be_returned[ $breakpoint ][ $state ]['gradient'] );
			}

			if ( isset( $attr_to_be_returned[ $breakpoint ][ $state ] ) ) {
				// If the entire background style is empty, remove it (or materialize for parallax).
				if ( empty( self::_array_trim( $attr_to_be_returned[ $breakpoint ][ $state ] ) ) ) {
					if (
						self::_should_materialize_responsive_background_row( $breakpoint, $parent_breakpoint, $base_breakpoint )
						&& ! self::_is_nested_multiview_background_attr( $original_attr, $breakpoint, $state )
					) {
						$attr_to_be_returned[ $breakpoint ][ $state ] = $parent_row_for_dedupe;
					} else {
						unset( $attr_to_be_returned[ $breakpoint ][ $state ] );
					}
				}

				// If the entire breakpoint style matches the parent, remove it.
				if (
					isset( $attr_to_be_returned[ $breakpoint ][ $state ] )
					&& self::_is_same(
						$attr_to_be_returned[ $breakpoint ][ $state ] ?? [],
						$parent_row_for_dedupe
					)
				) {
					if (
						! self::_should_materialize_responsive_background_row( $breakpoint, $parent_breakpoint, $base_breakpoint )
						|| self::_is_nested_multiview_background_attr( $original_attr, $breakpoint, $state )
					) {
						unset( $attr_to_be_returned[ $breakpoint ][ $state ] );
					}
				}
			}
		}

		if ( ! $is_desktop_value && isset( $attr_to_be_returned[ $breakpoint ][ $state ] ) ) {
			$parent_row_for_dedupe_outer = [];
			if ( isset( $attr_to_be_returned[ $parent_breakpoint ] ) && array_key_exists( $parent_state, $attr_to_be_returned[ $parent_breakpoint ] ) ) {
				$parent_row_for_dedupe_outer = $attr_to_be_returned[ $parent_breakpoint ][ $parent_state ];
			} elseif ( $use_inherited_lookup ) {
				$parent_row_for_dedupe_outer = $parent_row_inherited;
			}

			// If the entire background style is empty, remove it (or materialize for parallax).
			if ( empty( self::_array_trim( $attr_to_be_returned[ $breakpoint ][ $state ] ) ) ) {
				if (
					self::_should_materialize_responsive_background_row( $breakpoint, $parent_breakpoint, $base_breakpoint )
					&& ! self::_is_nested_multiview_background_attr( $original_attr, $breakpoint, $state )
				) {
					$attr_to_be_returned[ $breakpoint ][ $state ] = $parent_row_for_dedupe_outer;
				} else {
					unset( $attr_to_be_returned[ $breakpoint ][ $state ] );
				}
			}

			// If the entire breakpoint style matches the parent, remove it.
			if (
				isset( $attr_to_be_returned[ $breakpoint ][ $state ] )
				&& self::_is_same(
					$attr_to_be_returned[ $breakpoint ][ $state ] ?? [],
					$parent_row_for_dedupe_outer
				)
			) {
				if (
					! self::_should_materialize_responsive_background_row( $breakpoint, $parent_breakpoint, $base_breakpoint )
					|| self::_is_nested_multiview_background_attr( $original_attr, $breakpoint, $state )
				) {
					unset( $attr_to_be_returned[ $breakpoint ][ $state ] );
				}
			}
		}

		return $attr_to_be_returned;
	}

	/**
	 * Get and inherit background attributes for all breakpoints and states.
	 *
	 * Iterates through each breakpoint and state to inherit values from the previous
	 * breakpoint and state if they are not set. Also removes values that are the
	 * same as the inherited value.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array $attr An array of module attribute data.
	 *     @type bool  $hasBackgroundPresets Whether background presets are active.
	 * }
	 *
	 * @return array An array of background attributes with inherited values.
	 *
	 * @example:
	 * ```php
	 * $args = [
	 *     'attr' => [
	 *         'desktop' => [
	 *             'value' => 'red',
	 *             'hover' => 'blue',
	 *         ],
	 *         'tablet' => [
	 *             'value' => null,
	 *             'hover' => 'green',
	 *         ],
	 *     ],
	 * ];
	 *
	 * $result = ModuleUtils::get_and_inherit_background_attr( $args );
	 * ```
	 *
	 * @output:
	 * ```php
	 *   [
	 *       'desktop' => [
	 *           'value' => 'red',
	 *           'hover' => 'blue',
	 *       ],
	 *       'tablet' => [
	 *           'value' => 'red',
	 *           'hover' => 'green',
	 *       ],
	 *   ]
	 * ```
	 */
	public static function get_and_inherit_background_attr( array $args ): array {
		$initial_style_attr     = $args['attr'] ?? [];
		$has_background_presets = $args['hasBackgroundPresets'] ?? false;

		// CRITICAL FIX: When presets are active, preserve all module-level attributes as-is
		// to ensure module overrides take precedence over preset defaults.
		if ( $has_background_presets ) {
			return $initial_style_attr;
		}

		// Pre-populate with the passed style attributes.
		$attr_value_with_inherited = $initial_style_attr;

		// If we have a background style, we need to check if it contains
		// multiple breakpoints and/or states. If it does, we need to step
		// through each breakpoint and state and inherit values from the
		// previous breakpoint and state if they are not set.
		if ( ! empty( $attr_value_with_inherited ) ) {
			// TODO feat(D5, Responsive Views): Replace this with a loop once we have a sort/priority system for breakpoints [https://github.com/elegantthemes/Divi/issues/41620].

			// This implementation partially fulfills the above TO-DO by using dynamic breakpoint processing with proper inheritance order.

			// Get all enabled breakpoints dynamically instead of hardcoding.
			$breakpoint_names = Breakpoint::get_enabled_breakpoint_names();
			$states           = [ 'value', 'hover', 'sticky' ];

			// Sort breakpoints in inheritance order (parents first, children last).
			// This ensures parents are processed before children try to inherit from them.
			$base_breakpoint = Breakpoint::get_base_breakpoint_name();
			$base_index      = array_search( $base_breakpoint, $breakpoint_names, true );

			// Split into larger (parents) and smaller (children) breakpoints.
			$larger_breakpoints  = array_slice( $breakpoint_names, 0, $base_index + 1 );  // ultraWide, widescreen, desktop.
			$smaller_breakpoints = array_slice( $breakpoint_names, $base_index + 1 );    // tabletWide, tablet, phoneWide, phone.

			// Reverse larger breakpoints so desktop comes first, then widescreen, then ultraWide.
			$larger_breakpoints = array_reverse( $larger_breakpoints );

			// Combine: desktop, widescreen, ultraWide, tabletWide, tablet, phoneWide, phone.
			$ordered_breakpoints = array_merge( $larger_breakpoints, $smaller_breakpoints );

			foreach ( $ordered_breakpoints as $breakpoint ) {
				foreach ( $states as $state ) {
					$attr_value_with_inherited = self::_inherit_background_values( $attr_value_with_inherited, $breakpoint, $state );
				}
			}
		}

		// Pre-populate with the passed style attributes.
		$attr_to_be_returned = $attr_value_with_inherited;

		// If we have a background style, we need to check if any values is the
		// same as the inherited breakpoint/state value. If it is, we can delete
		// it from the inheritor.
		if ( ! empty( $attr_to_be_returned ) ) {
			// TODO feat(D5, Responsive Views): Replace this with a loop once we have a sort/priority system for breakpoints [https://github.com/elegantthemes/Divi/issues/41620].
			// phpcs:ignore ET.Comments.Todo.TodoFound -- NOTE comment references TODO but is not a TODO itself.
			// NOTE: This implementation partially fulfills the above TODO by using dynamic breakpoint processing.

			// Process breakpoints so each breakpoint's parent is normalized before deduplication runs on the child.
			// Order: base (desktop) → larger viewports (e.g. widescreen, ultraWide), then smaller (tabletWide → tablet → …).
			// Previously, smallest-first caused cleared colors (e.g. tabletWide '') to match child '' before the parent
			// was converted to `initial`, dropping smaller breakpoints and breaking FE (#48201).
			$breakpoint_names = Breakpoint::get_enabled_breakpoint_names();
			$base_name        = Breakpoint::get_base_breakpoint_name();
			$base_index_bp    = array_search( $base_name, $breakpoint_names, true );
			if ( false === $base_index_bp ) {
				$ordered_breakpoints_for_return = array_reverse( $breakpoint_names );
			} else {
				$larger_slice                   = array_slice( $breakpoint_names, 0, $base_index_bp + 1 );
				$smaller_slice                  = array_slice( $breakpoint_names, $base_index_bp + 1 );
				$ordered_breakpoints_for_return = array_merge( array_reverse( $larger_slice ), $smaller_slice );
			}
			$reversed_states = [ 'sticky', 'hover', 'value' ];

			foreach ( $ordered_breakpoints_for_return as $breakpoint ) {
				if ( array_key_exists( $breakpoint, $attr_to_be_returned ) ) {
					foreach ( $reversed_states as $state ) {
						$attr_to_be_returned = self::_return_background_values( $attr_to_be_returned, $breakpoint, $state, $initial_style_attr, $attr_value_with_inherited );
					}

					// Delete the breakpoint if it is empty.
					if ( empty( self::_array_trim( $attr_to_be_returned[ $breakpoint ] ) ) ) {
						unset( $attr_to_be_returned[ $breakpoint ] );
					}
				}
			}
		}

		return $attr_to_be_returned;
	}

	/**
	 * Inherit icon style attribute values for a given breakpoint and state.
	 *
	 * This function takes an array of attribute values with inherited values and updates them
	 * for a specific breakpoint and state.
	 *
	 * If the breakpoint or state is not set, it will be defined.
	 * If the state is not set, it will be inherited completely from the previous breakpoint.
	 * Finally, it will merge the inherited printed style attribute with the attribute values and return the updated array.
	 *
	 * @since ??
	 *
	 * @param array  $attr_value_with_inherited The attribute values with inherited values.
	 * @param string $breakpoint                The breakpoint to get the inheritance breakpoint for.
	 * @param string $state                     The state to get the inheritance breakpoint for.
	 *
	 * @return array The updated attribute values with inherited values.
	 *
	 * @example:
	 * ```php
	 * $attr_value_with_inherited = [
	 *    'desktop' => [
	 *        'hover' => [
	 *            'useSize' => 'on',
	 *            'size' => '12px',
	 *        ],
	 *    ],
	 *    'tablet' => [
	 *        'value' => [
	 *            'useSize' => 'off',
	 *            'size' => '10px',
	 *        ],
	 *        'hover' => [
	 *            'useSize' => 'on',
	 *            'size' => '20px',
	 *        ],
	 *        'sticky' => [
	 *            'useSize' => 'on',
	 *            'size' => '25px',
	 *        ],
	 *    ],
	 * ];
	 *
	 * $breakpoint = 'tablet';
	 * $state = 'value';
	 *
	 * $updated_attr_value_with_inherited = self::_inherit_icon_style_values( $attr_value_with_inherited, $breakpoint, $state );
	 * ```
	 */
	private static function _inherit_icon_style_values( array $attr_value_with_inherited, string $breakpoint, string $state ): array {
		// TODO feat(D5, Refactor): pass breakpointNames and baseBreakpoint as arguments [https://github.com/elegantthemes/Divi/issues/41620].
		$base_breakpoint    = Breakpoint::get_base_breakpoint_name();
		$breakpoint_names   = Breakpoint::get_enabled_breakpoint_names();
		$inherit_breakpoint = self::get_inherit_breakpoint(
			[
				'breakpoint'      => $breakpoint,
				'state'           => $state,
				'baseBreakpoint'  => $base_breakpoint,
				'breakpointNames' => $breakpoint_names,
			]
		);
		$inherit_state      = self::get_inherit_state(
			[
				'breakpoint'      => $breakpoint,
				'state'           => $state,
				'baseBreakpoint'  => $base_breakpoint,
				'breakpointNames' => $breakpoint_names,
			]
		);

		$attr_values        = $attr_value_with_inherited[ $breakpoint ][ $state ] ?? [];
		$attr_parent_values = $attr_value_with_inherited[ $inherit_breakpoint ][ $inherit_state ] ?? [];

		$attr_value_with_inherited[ $breakpoint ][ $state ] = self::_array_trim(
			[
				'color'          => $attr_values['color'] ?? $attr_parent_values['color'] ?? null,
				'useSize'        => $attr_values['useSize'] ?? $attr_parent_values['useSize'] ?? '',
				'size'           => $attr_values['size'] ?? $attr_parent_values['size'] ?? '',
				'weight'         => $attr_values['weight'] ?? $attr_parent_values['weight'] ?? '',
				'unicode'        => $attr_values['unicode'] ?? $attr_parent_values['unicode'] ?? '',
				'type'           => $attr_values['type'] ?? $attr_parent_values['type'] ?? '',
				'show'           => $attr_values['show'] ?? $attr_parent_values['show'] ?? '',
				'indicatorShape' => $attr_values['indicatorShape'] ?? $attr_parent_values['indicatorShape'] ?? '',
			]
		);

		return $attr_value_with_inherited;
	}

	/**
	 * Return attribute values with inherited icon style CSS declarations.
	 *
	 * This function takes an array of attribute values with inherited values and calculates the final
	 * icon style CSS declarations for a given breakpoint and state.
	 * It checks if the attribute values match the inherited values and removes any redundant
	 * entries (i.e the values are the same as the parent breakpoint and state).
	 * It also filters out empty attribute values.
	 *
	 * @since ??
	 *
	 * @param array  $attr_to_be_returned The attribute values with inherited values.
	 * @param string $breakpoint          The breakpoint to calculate the inheritance for.
	 * @param string $state               The state to calculate the inheritance for.
	 *
	 * @return array The attribute values after applying inheritance and filtering.
	 *
	 * @example:
	 * ```php
	 * // Single usage example:
	 * $attr_to_be_returned = self::_return_icon_style_values( $attr_to_be_returned, 'desktop', 'hover' );
	 *
	 * // Multiple usage example:
	 * if ( array_key_exists( 'phone', $attr_to_be_returned ) ) {
	 *     $attr_to_be_returned = self::_return_icon_style_values( $attr_to_be_returned, 'phone', 'sticky' );
	 *     $attr_to_be_returned = self::_return_icon_style_values( $attr_to_be_returned, 'phone', 'hover' );
	 *     $attr_to_be_returned = self::_return_icon_style_values( $attr_to_be_returned, 'phone', 'value' );
	 * }
	 * ```
	 */
	private static function _return_icon_style_values( array $attr_to_be_returned, string $breakpoint, string $state ): array {
		// TODO feat(D5, Refactor): pass breakpointNames and baseBreakpoint as arguments [https://github.com/elegantthemes/Divi/issues/41620].
		$base_breakpoint   = Breakpoint::get_base_breakpoint_name();
		$breakpoint_names  = Breakpoint::get_enabled_breakpoint_names();
		$parent_breakpoint = self::get_inherit_breakpoint(
			[
				'breakpoint'      => $breakpoint,
				'state'           => $state,
				'baseBreakpoint'  => $base_breakpoint,
				'breakpointNames' => $breakpoint_names,
			]
		);
		$parent_state      = self::get_inherit_state(
			[
				'breakpoint'      => $breakpoint,
				'state'           => $state,
				'baseBreakpoint'  => $base_breakpoint,
				'breakpointNames' => $breakpoint_names,
			]
		);

		$is_desktop_value   = 'desktop' === $breakpoint && 'value' === $state;
		$current_icon_style = $attr_to_be_returned[ $breakpoint ][ $state ] ?? [];
		$parent_icon_style  = $attr_to_be_returned[ $parent_breakpoint ][ $parent_state ] ?? [];
		$icon_styles_match  = self::_is_same( $current_icon_style, $parent_icon_style );
		$is_current_empty   = ! $current_icon_style;

		// Update the attr object to add inherited icon-style values if toJSON matches.
		if ( $is_desktop_value && ! $is_current_empty ) {
			$attr_to_be_returned[ $breakpoint ][ $state ] = $current_icon_style;
		} elseif ( ! $icon_styles_match && ! $is_current_empty ) {
			$attr_to_be_returned[ $breakpoint ][ $state ] = $current_icon_style;
		} else {
			unset( $attr_to_be_returned[ $breakpoint ][ $state ] );
		}

		return $attr_to_be_returned;
	}

	/**
	 * Get and inherit icon style CSS declarations with inheritance for all breakpoints and states.
	 *
	 * This function takes an array of attribute values with inherited values and updates them
	 * for a specific breakpoint and state. If the breakpoint or state is not set, it will be defined.
	 * If the state is not set, it will be inherited completely from the previous breakpoint.
	 * Finally, it will merge the inherited printed style attribute with the attribute values and return the updated array.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array $attr The attribute values with inherited values.
	 * }
	 *
	 * @return array The attribute values with updated inheritance.
	 *
	 * @example:
	 * ```php
	 *   $args = [
	 *     'attr' => [
	 *       'desktop' => [
	 *         'value' => [
	 *           'useSize' => 'on',
	 *           'size' => '2px',
	 *         ],
	 *         'hover' => [
	 *           'useSize' => 'on',
	 *         ],
	 *         'sticky' => [
	 *           'useSize' => 'on',
	 *           'size' => '8px',
	 *         ],
	 *       ],
	 *       'tablet' => [
	 *         'value' => [
	 *           'useSize' => 'on',
	 *           'size' => '22px',
	 *         ],
	 *         'hover' => [
	 *           'size' => '35px',
	 *         ],
	 *         'sticky' => [
	 *           'size' => '2px',
	 *         ],
	 *       ],
	 *       'phone' => [
	 *         'value' => [
	 *           'useSize' => 'on',
	 *           'size' => '12px',
	 *         ],
	 *         'sticky' => [
	 *           'useSize' => 'on',
	 *           'size' => '8px',
	 *         ],
	 *       ]
	 *     ]
	 *   ];
	 *
	 *   $result = ModuleUtils::get_and_inherit_icon_style_attr( $args );
	 * ```

	 * @example:
	 * ```php
	 *   $args = [
	 *     'attr' => [
	 *       'desktop' => [
	 *         'value' => [
	 *           'useSize' => 'on',
	 *           'size' => '2px',
	 *         ],
	 *         'hover' => [
	 *           'useSize' => 'on',
	 *           'size' => '2px',
	 *         ],
	 *         'sticky' => [
	 *           'useSize' => 'on',
	 *           'size' => '8px',
	 *         ],
	 *       ],
	 *       'tablet' => [
	 *         'value' => [
	 *           'useSize' => 'on',
	 *           'size' => '22px',
	 *         ],
	 *         'hover' => [
	 *           'useSize' => 'on',
	 *           'size' => '35px',
	 *         ],
	 *         'sticky' => [
	 *           'useSize' => 'on',
	 *           'size' => '2px',
	 *         ],
	 *       ],
	 *       'phone' => [
	 *         'value' => [
	 *           'useSize' => 'on',
	 *           'size' => '12px',
	 *         ],
	 *         'hover' => [
	 *           'useSize' => 'on',
	 *           'size' => '12px',
	 *         ],
	 *         'sticky' => [
	 *           'useSize' => 'on',
	 *           'size' => '8px',
	 *         ],
	 *       ]
	 *     ]
	 *   ];
	 *
	 *   $result = ModuleUtils::get_and_inherit_icon_style_attr( $args );
	 * ```
	 */
	public static function get_and_inherit_icon_style_attr( array $args ): array {
		$initial_style_attr = $args['attr'] ?? [];

		// Pre-populate with the passed style attributes.
		$attr_value_with_inherited = $initial_style_attr;

		// If we have a icon-style style, we need to check if it contains
		// multiple breakpoints and/or states. If it does, we need to step
		// through each breakpoint and state and inherit values from the
		// previous breakpoint and state if they are not set.
		if ( ! empty( $attr_value_with_inherited ) ) {
			// Process enabled breakpoints in inheritance order.
			$breakpoint_names = Breakpoint::get_enabled_breakpoint_names();
			$states           = [ 'value', 'hover', 'sticky' ];

			// Sort breakpoints in inheritance order (parents first, children last).
			$base_breakpoint     = Breakpoint::get_base_breakpoint_name();
			$base_index          = array_search( $base_breakpoint, $breakpoint_names, true );
			$larger_breakpoints  = array_slice( $breakpoint_names, 0, $base_index + 1 );
			$smaller_breakpoints = array_slice( $breakpoint_names, $base_index + 1 );
			$larger_breakpoints  = array_reverse( $larger_breakpoints );
			$ordered_breakpoints = array_merge( $larger_breakpoints, $smaller_breakpoints );

			foreach ( $ordered_breakpoints as $breakpoint ) {
				foreach ( $states as $state ) {
					$attr_value_with_inherited = self::_inherit_icon_style_values( $attr_value_with_inherited, $breakpoint, $state );
				}
			}
		}

		// Pre-populate with the passed style attributes.
		$attr_to_be_returned = $attr_value_with_inherited;

		// If we have a icon-style style, we need to check if any values is the
		// same as the inherited breakpoint/state value. If it is, we can delete
		// it from the inheritor.
		if ( ! empty( $attr_to_be_returned ) ) {
			// Process enabled breakpoints in reverse inheritance order for cleanup.
			$enabled_breakpoints  = Breakpoint::get_enabled_breakpoint_names();
			$reversed_breakpoints = array_reverse( $enabled_breakpoints );
			$states               = [ 'sticky', 'hover', 'value' ];

			foreach ( $reversed_breakpoints as $breakpoint ) {
				if ( array_key_exists( $breakpoint, $attr_to_be_returned ) ) {
					foreach ( $states as $state ) {
						$attr_to_be_returned = self::_return_icon_style_values( $attr_to_be_returned, $breakpoint, $state );
					}

					// Delete the breakpoint if it is empty.
					if ( empty( self::_array_trim( $attr_to_be_returned[ $breakpoint ] ) ) ) {
						unset( $attr_to_be_returned[ $breakpoint ] );
					}
				}
			}
		}

		return $attr_to_be_returned;
	}

	/**
	 * Inherit text shadow attribute values for a given breakpoint and state.
	 *
	 * This function takes an array of attribute values with inherited values and updates them
	 * for a specific breakpoint and state.
	 *
	 * If the breakpoint or state is not set, it will be defined.
	 * If the state is not set, it will be inherited completely from the previous breakpoint.
	 * Finally, it will merge the inherited printed style attribute with the attribute values and return the updated array.
	 *
	 * @since ??
	 *
	 * @param array  $attr_value_with_inherited The attribute values with inherited values.
	 * @param string $breakpoint                The breakpoint to get the inheritance breakpoint for.
	 * @param string $state                     The state to get the inheritance breakpoint for.
	 *
	 * @return array The updated attribute values with inherited values.
	 *
	 * @example:
	 * ```php
	 * $attr_value_with_inherited = [
	 *    'desktop' => [
	 *        'hover' => [
	 *            'color' => '#000000',
	 *            'text-shadow' => '2px 2px 2px #000000',
	 *        ],
	 *    ],
	 *    'tablet' => [
	 *        'value' => [
	 *            'color' => '#ffffff',
	 *            'text-shadow' => 'none',
	 *        ],
	 *        'hover' => [
	 *            'color' => '#ff0000',
	 *            'text-shadow' => 'none',
	 *        ],
	 *        'sticky' => [
	 *            'color' => '#00ff00',
	 *            'text-shadow' => 'none',
	 *        ],
	 *    ],
	 * ];
	 *
	 * $breakpoint = 'tablet';
	 * $state = 'value';
	 *
	 * $updated_attr_value_with_inherited = ModuleUtils::_inherit_text_shadow_values( $attr_value_with_inherited, $breakpoint, $state );
	 * ```
	 */
	private static function _inherit_text_shadow_values( array $attr_value_with_inherited, string $breakpoint, string $state ): array {
		// TODO feat(D5, Refactor): pass breakpointNames and baseBreakpoint as arguments [https://github.com/elegantthemes/Divi/issues/41620].
		$base_breakpoint    = Breakpoint::get_base_breakpoint_name();
		$breakpoint_names   = Breakpoint::get_enabled_breakpoint_names();
		$inherit_breakpoint = self::get_inherit_breakpoint(
			[
				'breakpoint'      => $breakpoint,
				'state'           => $state,
				'baseBreakpoint'  => $base_breakpoint,
				'breakpointNames' => $breakpoint_names,
			]
		);
		$inherit_state      = self::get_inherit_state(
			[
				'breakpoint'      => $breakpoint,
				'state'           => $state,
				'baseBreakpoint'  => $base_breakpoint,
				'breakpointNames' => $breakpoint_names,
			]
		);

		// If the breakpoint is not set, we need to define it.
		if ( ! isset( $attr_value_with_inherited[ $breakpoint ] ) ) {
			$attr_value_with_inherited[ $breakpoint ] = [];
		}

		// If the state is not set, we need to define it.
		if ( ! isset( $attr_value_with_inherited[ $breakpoint ][ $state ] ) ) {
			$attr_value_with_inherited[ $breakpoint ][ $state ] = [];
		}

		// If the state is not set, we need to inherit it completely from the previous breakpoint.
		if ( isset( $attr_value_with_inherited[ $inherit_breakpoint ][ $inherit_state ] ) && ! isset( $attr_value_with_inherited[ $breakpoint ][ $state ] ) ) {
			$attr_value_with_inherited[ $breakpoint ][ $state ] = $attr_value_with_inherited[ $inherit_breakpoint ][ $inherit_state ];
		}

		// Ensure both previous and current state values are arrays before merging.
		$inherited_values     = $attr_value_with_inherited[ $inherit_breakpoint ][ $inherit_state ] ?? [];
		$current_state_values = $attr_value_with_inherited[ $breakpoint ][ $state ] ?? [];

		// If inherited values are empty and we're not already at desktop, fall back to desktop breakpoint.
		// This handles cases where the immediate parent breakpoint (e.g., tablet) doesn't exist,
		// so we inherit directly from desktop instead.
		if ( empty( $inherited_values ) && $inherit_breakpoint !== $base_breakpoint && $breakpoint !== $base_breakpoint ) {
			$inherited_values = $attr_value_with_inherited[ $base_breakpoint ][ $inherit_state ] ?? [];
		}

		// Merge the inherited printed style attribute with the attribute values.
		// When current values are partial (e.g., only 'color'), ensure missing properties
		// (like 'style', 'horizontal', 'vertical', 'blur') are inherited from parent breakpoint.
		// Start with inherited values as base, then merge current values on top.
		// This ensures inherited properties are preserved when current values are partial.
		$merged_values = $inherited_values;
		foreach ( $current_state_values as $key => $value ) {
			$merged_values[ $key ] = $value;
		}
		$attr_value_with_inherited[ $breakpoint ][ $state ] = $merged_values;

		return $attr_value_with_inherited;
	}

	/**
	 * Return attribute values with inherited text shadow CSS declarations.
	 *
	 * This function takes an array of attribute values with inherited values and calculates the final
	 * text shadow CSS declarations for a given breakpoint and state.
	 * It checks if the attribute values match the inherited values and removes any redundant
	 * entries (i.e the values are the same as the parent breakpoint and state).
	 * It also filters out empty attribute values.
	 *
	 * @since ??
	 *
	 * @param array  $attr_to_be_returned The attribute values with inherited values.
	 * @param string $breakpoint          The breakpoint to calculate the inheritance for.
	 * @param string $state               The state to calculate the inheritance for.
	 *
	 * @return array The attribute values after applying inheritance and filtering.
	 *
	 * @example:
	 * ```php
	 * // Single usage example:
	 * $attr_to_be_returned = ModuleUtils::_return_text_shadow_values( $attr_to_be_returned, 'desktop', 'hover' );
	 *
	 * // Multiple usage example:
	 * if ( array_key_exists( 'phone', $attr_to_be_returned ) ) {
	 *     $attr_to_be_returned = ModuleUtils::_return_text_shadow_values( $attr_to_be_returned, 'phone', 'sticky' );
	 *     $attr_to_be_returned = ModuleUtils::_return_text_shadow_values( $attr_to_be_returned, 'phone', 'hover' );
	 *     $attr_to_be_returned = ModuleUtils::_return_text_shadow_values( $attr_to_be_returned, 'phone', 'value' );
	 * }
	 * ```
	 */
	private static function _return_text_shadow_values( array $attr_to_be_returned, string $breakpoint, string $state ): array {
		// TODO feat(D5, Refactor): pass breakpointNames and baseBreakpoint as arguments [https://github.com/elegantthemes/Divi/issues/41620].
		$base_breakpoint    = Breakpoint::get_base_breakpoint_name();
		$breakpoint_names   = Breakpoint::get_enabled_breakpoint_names();
		$inherit_breakpoint = self::get_inherit_breakpoint(
			[
				'breakpoint'      => $breakpoint,
				'state'           => $state,
				'baseBreakpoint'  => $base_breakpoint,
				'breakpointNames' => $breakpoint_names,
			]
		);
		$inherit_state      = self::get_inherit_state(
			[
				'breakpoint'      => $breakpoint,
				'state'           => $state,
				'baseBreakpoint'  => $base_breakpoint,
				'breakpointNames' => $breakpoint_names,
			]
		);

		// If the inherited breakpoint is not set, return the attribute values.
		if ( ! isset( $attr_to_be_returned[ $inherit_breakpoint ][ $inherit_state ] ) ) {
			return $attr_to_be_returned;
		}

		// If the attribute value matches the inherited value, we can delete it.
		if ( $attr_to_be_returned[ $breakpoint ][ $state ] === $attr_to_be_returned[ $inherit_breakpoint ][ $inherit_state ] ) {
			unset( $attr_to_be_returned[ $breakpoint ][ $state ] );

			return $attr_to_be_returned;
		}

		if ( empty( $attr_to_be_returned[ $breakpoint ][ $state ] ) ) {
			$attr_to_be_returned[ $breakpoint ][ $state ] = array_filter( $attr_to_be_returned[ $breakpoint ][ $state ], 'strlen' );
		}

		return $attr_to_be_returned;
	}

	/**
	 * Get and inherit text shadow CSS declarations with inheritance for all breakpoints and states.
	 *
	 * This function takes an array of attribute values with inherited values and updates them
	 * for a specific breakpoint and state. If the breakpoint or state is not set, it will be defined.
	 * If the state is not set, it will be inherited completely from the previous breakpoint.
	 * Finally, it will merge the inherited printed style attribute with the attribute values and return the updated array.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array $attr The attribute values with inherited values.
	 * }
	 *
	 * @return array The attribute values with updated inheritance.
	 *
	 * @example:
	 * ```php
	 *   $args = [
	 *     'attr' => [
	 *       'desktop' => [
	 *         'value' => 'text-shadow: 2px 2px 2px black;',
	 *         'hover' => 'text-shadow: 4px 4px 4px black;',
	 *         'sticky' => 'text-shadow: 8px 8px 8px black;'
	 *       ],
	 *       'tablet' => [
	 *         'value' => 'text-shadow: 3px 3px 3px black;',
	 *         'hover' => 'text-shadow: 5px 5px 5px black;',
	 *         'sticky' => 'text-shadow: 9px 9px 9px black;'
	 *       ],
	 *       'phone' => [
	 *         'value' => 'text-shadow: 6px 6px 6px black;',
	 *         'hover' => 'text-shadow: 7px 7px 7px black;',
	 *         'sticky' => 'text-shadow: 10px 10px 10px black;'
	 *       ]
	 *     ]
	 *   ];
	 *
	 *   $result = ModuleUtils::get_and_inherit_text_shadow_attr( $args );
	 * ```

	 * @example:
	 * ```php
	 *   $args = [
	 *     'attr' => [
	 *       'desktop' => [
	 *         'value' => 'text-shadow: 2px 2px 2px black;',
	 *         'hover' => 'text-shadow: 4px 4px 4px black;',
	 *         'sticky' => 'text-shadow: 8px 8px 8px black;'
	 *       ]
	 *     ]
	 *   ];
	 *
	 *   $result = ModuleUtils::get_and_inherit_text_shadow_attr( $args );
	 * ```
	 */
	public static function get_and_inherit_text_shadow_attr( array $args ): array {
		$initial_style_attr         = $args['attr'] ?? [];
		$default_printed_style_attr = $args['defaultPrintedStyleAttr'] ?? [];

		// Pre-populate with the passed style attributes.
		$attr_value_with_inherited = $initial_style_attr;

		// If we have default printed style attributes, merge them as base values.
		if ( ! empty( $default_printed_style_attr ) ) {
			foreach ( $default_printed_style_attr as $breakpoint => $breakpoint_data ) {
				if ( ! isset( $attr_value_with_inherited[ $breakpoint ] ) ) {
					$attr_value_with_inherited[ $breakpoint ] = [];
				}
				foreach ( $breakpoint_data as $state => $state_data ) {
					if ( ! isset( $attr_value_with_inherited[ $breakpoint ][ $state ] ) ) {
						$attr_value_with_inherited[ $breakpoint ][ $state ] = $state_data;
					} else {
						// Merge individual properties, giving priority to user values.
						$attr_value_with_inherited[ $breakpoint ][ $state ] = array_merge(
							$state_data,
							$attr_value_with_inherited[ $breakpoint ][ $state ]
						);
					}
				}
			}
		}

		// If we have a text-shadow style, we need to check if it contains
		// multiple breakpoints and/or states. If it does, we need to step
		// through each breakpoint and state and inherit values from the
		// previous breakpoint and state if they are not set.
		if ( ! empty( $attr_value_with_inherited ) ) {
			// TODO feat(D5, Responsive Views): Replace this with a loop once we have a sort/priority system for breakpoints [https://github.com/elegantthemes/Divi/issues/41620].

			// Desktop attributes first, if they exist.
			if ( array_key_exists( 'desktop', $attr_value_with_inherited ) ) {
				$attr_value_with_inherited = self::_inherit_text_shadow_values( $attr_value_with_inherited, 'desktop', 'hover' );
				$attr_value_with_inherited = self::_inherit_text_shadow_values( $attr_value_with_inherited, 'desktop', 'sticky' );
			}

			// Tablet attributes second, if they exist.
			if ( array_key_exists( 'tablet', $attr_value_with_inherited ) ) {
				$attr_value_with_inherited = self::_inherit_text_shadow_values( $attr_value_with_inherited, 'tablet', 'value' );
				$attr_value_with_inherited = self::_inherit_text_shadow_values( $attr_value_with_inherited, 'tablet', 'hover' );
				$attr_value_with_inherited = self::_inherit_text_shadow_values( $attr_value_with_inherited, 'tablet', 'sticky' );
			}

			// Phone attributes last, if they exist.
			if ( array_key_exists( 'phone', $attr_value_with_inherited ) ) {
				$attr_value_with_inherited = self::_inherit_text_shadow_values( $attr_value_with_inherited, 'phone', 'value' );
				$attr_value_with_inherited = self::_inherit_text_shadow_values( $attr_value_with_inherited, 'phone', 'hover' );
				$attr_value_with_inherited = self::_inherit_text_shadow_values( $attr_value_with_inherited, 'phone', 'sticky' );
			}
		}

		// Pre-populate with the passed style attributes.
		$attr_to_be_returned = $attr_value_with_inherited;

		// If we have a text-shadow style, we need to check if any values is the
		// same as the inherited breakpoint/state value. If it is, we can delete
		// it from the inheritor.
		if ( ! empty( $attr_to_be_returned ) ) {
			// TODO feat(D5, Responsive Views): Replace this with a loop once we have a sort/priority system for breakpoints [https://github.com/elegantthemes/Divi/issues/41620].

			// Phone attributes first, if they exist.
			if ( array_key_exists( 'phone', $attr_to_be_returned ) ) {
				$attr_to_be_returned = self::_return_text_shadow_values( $attr_to_be_returned, 'phone', 'sticky' );
				$attr_to_be_returned = self::_return_text_shadow_values( $attr_to_be_returned, 'phone', 'hover' );
				$attr_to_be_returned = self::_return_text_shadow_values( $attr_to_be_returned, 'phone', 'value' );
			}

			// Tablet attributes second, if they exist.
			if ( array_key_exists( 'tablet', $attr_to_be_returned ) ) {
				$attr_to_be_returned = self::_return_text_shadow_values( $attr_to_be_returned, 'tablet', 'sticky' );
				$attr_to_be_returned = self::_return_text_shadow_values( $attr_to_be_returned, 'tablet', 'hover' );
				$attr_to_be_returned = self::_return_text_shadow_values( $attr_to_be_returned, 'tablet', 'value' );
			}
			// Desktop attributes last, if they exist.
			if ( array_key_exists( 'desktop', $attr_to_be_returned ) ) {
				$attr_to_be_returned = self::_return_text_shadow_values( $attr_to_be_returned, 'desktop', 'sticky' );
				$attr_to_be_returned = self::_return_text_shadow_values( $attr_to_be_returned, 'desktop', 'hover' );
			}
		}

		return $attr_to_be_returned;
	}

	/**
	 * Get module class by module name.
	 *
	 * This function is equivalent of JS function getModuleClassByName located in
	 * visual-builder/packages/module-utils/src/get-module-class-by-name/index.ts.
	 *
	 * @since ??
	 *
	 * @param string $namespaced_module_name Module name including namespace.
	 *
	 * @return string Module class name with snake case format. Built-in modules will return
	 * class name with `et_pb_` prefix. Third party modules will return class name with `namespace_` prefix.
	 */
	/**
	 * Get the module class name by the given namespaced module name.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module-utils/functions/getModuleClassByName/ getModuleClassByName} located in
	 * `@divi/module-utils`.
	 *
	 * This function takes a namespaced module name as input and returns the corresponding module class name.
	 * The namespaced module name should be in the format `namespace/module`.
	 * Built-in modules have a `divi` namespace and have a `et_pb_` prefix in the class name.
	 * Third-party modules have a `namespace` namespace and have a `namespace_` prefix in the class name.
	 *
	 * @since ??
	 *
	 * @param string $namespaced_module_name The namespaced module name.
	 *
	 * @return string The module class name with snake case format.
	 */
	public static function get_module_class_by_name( string $namespaced_module_name ): string {
		$parts = explode( '/', $namespaced_module_name, 2 );

		if ( 2 !== count( $parts ) || ! $parts[0] || ! $parts[1] ) {
			return '';
		}

		$prefix = 'divi' === $parts[0] ? 'et_pb' : TextTransform::snake_case( $parts[0] );

		return $prefix . '_' . TextTransform::snake_case( $parts[1] );
	}

	/**
	 * Wrap serialized block content with divi/placeholder block.
	 *
	 * This function is equivalent to JS function located in `@divi/module-utils`.
	 *
	 * @since ??
	 *
	 * @param string $serialized_block_content Content in serialized block format.
	 *
	 * @return string The wrapped content.
	 *
	 * @example:
	 * ```php
	 * $content = '<!-- wp:divi/section {...} -->';
	 * $wrapped = ModuleUtils::wrap_placeholder_block( $content );
	 * // Output: '<!-- wp:divi/placeholder --><!-- wp:divi/section {...} --><!-- /wp:divi/placeholder -->'
	 * ```
	 */
	public static function wrap_placeholder_block( string $serialized_block_content ): string {
		$block_name = 'divi/placeholder';

		return "<!-- wp:{$block_name} -->{$serialized_block_content}<!-- /wp:{$block_name} -->";
	}

	/**
	 * Unwrap divi/placeholder block from the given serialized block content.
	 *
	 * This function is equivalent to JS function:
	 * {@link /api/js/divi-module-utils/functions/maybeUnwrapPlaceholderBlock/ maybeUnwrapPlaceholderBlock} located in
	 * `@divi/module-utils`.
	 *
	 * @since ??
	 *
	 * @param string $serialized_block_content Content that is saved in serialized block format.
	 *
	 * @return string The unwrapped content or original content if no wrapper found.
	 *
	 * @example:
	 * ```php
	 * $content = '<!-- wp:divi/placeholder --><!-- wp:divi/section {...} --><!-- /wp:divi/placeholder -->';
	 * $unwrapped = ModuleUtils::maybe_unwrap_placeholder_block( $content );
	 * // Output: '<!-- wp:divi/section {...} -->'
	 * ```
	 */
	public static function maybe_unwrap_placeholder_block( string $serialized_block_content ): string {
		// Check for self-closing placeholder block.
		if ( preg_match( '/<!-- wp:divi\/placeholder \/-->/', $serialized_block_content ) ) {
			return ''; // Return empty string if the block is self-closing.
		}

		// Check for regular placeholder block with content.
		$pattern = '/<!-- wp:divi\/placeholder -->(.*?)<!-- \/wp:divi\/placeholder -->/s';
		if ( preg_match( $pattern, $serialized_block_content, $matches ) ) {
			return trim( $matches[1] ); // Return the content between the markers.
		}

		// Return the original string if markers are not found.
		return $serialized_block_content;
	}

	/**
	 * Get subname value of attr and/or its inherited value from larger breakpoint / default state.
	 *
	 * This function takes an array of arguments and retrieves the value of a subname attribute based on the provided arguments.
	 *
	 * Getter and inheritance model can be changed based on `mode` parameter:
	 * 1. `get`                  : Get attr value of given breakpoint + state.
	 * 2. `getAndInheritAll`     : Get attr value combined by all possible inherited attr value on all larger breakpoints.
	 * 3. `getAndInheritClosest` : Get attr value combined by inherited attr value from closest available breakpoint.
	 * 4. `getOrInheritAll`      : Get attr value or inherited attr value from all larger breakpoints.
	 * 5. `getOrInheritClosest`  : Get attr value or inherited attr value from closest available breakpoint.
	 * 6. `inheritAll`           : Get inherited attr value from all larger breakpoints.
	 * 7. `inheritClosest`       : Get inherited attr value from all closest available breakpoint.
	 *
	 * See below for inherited attribute fallback flow:
	 *
	 * |        | value | hover | sticky |
	 * |--------|-------|-------|--------|
	 * | Desktop|   *   |  <--  |  <--   |
	 * | Tablet |   ^   |  <--  |  <--   |
	 * | Phone  |   ^   |  <--  |  <--   |
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array $attr           The main attribute array from which the subname value will be extracted.
	 *     @type string $breakpoint    The breakpoint value to consider while retrieving the subname value.
	 *     @type string $state         The state value to consider while retrieving the subname value.
	 *     @type string $defaultValue  Optional. The default value to return if the subname value is not found. Default empty string.
	 *     @type string $mode          Optional. The mode to control the retrieval behavior. Default is `getOrInheritAll`.
	 *     @type string $subname       The subname value to retrieve from the attribute array.
	 * }
	 * @return mixed The retrieved subname value.
	 *               Returns the default value if the subname value is not found.
	 *
	 * @example:
	 * ```php
	 * $args = [
	 *     'attr'           => ['desktop' => ['value' => ['position' => 'none']]],
	 *     'breakpoint'     => 'desktop',
	 *     'state'          => '',
	 *     'defaultValue'   => '',
	 *     'mode'           => 'getOrInheritAll',
	 *     'subname'        => 'position',
	 * ];
	 *
	 * $subname_value = ModuleUtils::get_attr_subname_value( $args );
	 * ```
	 *
	 * @example:
	 * ```php
	 * $args = [
	 *     'attr'           => ['desktop' => ['value' => ['alignment' => 'center']]],
	 *     'breakpoint'     => '',
	 *     'state'          => '',
	 *     'defaultValue'   => '',
	 *     'mode'           => 'getOrInheritAll',
	 *     'subname'        => 'alignment',
	 * ];
	 *
	 * $subname_value = ModuleUtils::get_attr_subname_value( $args );
	 * ```
	 */
	public static function get_attr_subname_value( array $args ) {
		$args = wp_parse_args(
			$args,
			[
				'mode'         => 'getOrInheritAll',
				'defaultValue' => '',
			]
		);

		$attr          = $args['attr'];
		$breakpoint    = $args['breakpoint'];
		$state         = $args['state'];
		$default_value = $args['defaultValue'];
		$mode          = $args['mode'];
		$subname       = $args['subname'];

		$attr_value = self::use_attr_value(
			[
				'attr'       => $attr,
				'breakpoint' => $breakpoint,
				'state'      => $state,
				'mode'       => $mode,
			]
		);

		if ( ! is_array( $attr_value ) ) {
			$attr_value = [];
		}

		return ArrayUtility::get_value( $attr_value, $subname, $default_value );
	}

	/**
	 * Get module states.
	 *
	 * This function returns an array containing the default states of a module.
	 * This function runs the value through the `divi_module_utils_states` filter.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module-utils/variables/states/ states } located in `@divi/module-utils`.
	 *
	 * @since ??
	 *
	 * @return array An array of module states. The default values are `['value', 'hover', 'focus', 'checked', 'active', 'sticky']`.
	 */
	public static function states(): array {
		$states = [
			'value',
			'hover',
			'focus',
			'checked',
			'active',
			'sticky',
		];

		/**
		 * Filters the module states.
		 *
		 * @since ??
		 *
		 * @param array $states The module states. Default `['value', 'hover', 'focus', 'checked', 'active', 'sticky']`.
		 */
		return apply_filters( 'divi_module_utils_states', $states );
	}

	/**
	 * Check if an attribute has a value across breakpoints and states based on specified options.
	 *
	 * @since ??
	 *
	 * @param array $attr    The attribute that needs to be checked.
	 * @param array $options {
	 *     Additional options for checking the value (optional).
	 *
	 *     @type string|null   $breakpoint    Optional. The breakpoint to check for the attribute value. One of `desktop`, `tablet`, `phone`.
	 *                                        Default `null`.
	 *     @type string|null   $state         Optional. The state to check for the attribute value.
	 *                                        One of `value`, `hover`, `tablet_value`, `tablet_hover`, `phone_value`, `phone_hover`.
	 *                                        Default `null`.
	 *     @type string|null   $subName       Optional. The sub-name to extract from the attribute value. Default `null`.
	 *     @type callable|null $valueResolver Optional. A callable function to resolve the attribute value. Default `null`.
	 *     @type string|null   $inheritedMode Optional. The inherit mode specifying how the attribute value will be inherited.
	 *                                        One of `inherited`, `inheritedClosest`, `inheritedAll`, `inheritedOrClosest`,
	 *                                        `inheritedOrAll`, `closest`, `all`. Default `getAndInheritAll`.
	 *
	 * @throws InvalidArgumentException If the provided `$options['valueResolver']` is not a callable function.
	 *
	 * @return bool Whether the attribute has a value based on the specified options.
	 *
	 * @example:
	 * ```php
	 * $attr = [
	 *     'desktop' => [
	 *         'normal' => 'Value for desktop',
	 *         'hover' => 'Hover value for desktop',
	 *     ],
	 *     'mobile' => [
	 *         'normal' => 'Value for mobile',
	 *         'hover' => '',
	 *     ],
	 * ];
	 *
	 * // Check if the attribute has a value for the breakpoint 'desktop' and state 'normal'
	 * $result = ModuleUtils::has_value( $attr, [
	 *     'breakpoint' => 'desktop',
	 *     'state' => 'normal',
	 * ] );
	 *
	 * // Check if the attribute has a value for the breakpoint 'mobile' and state 'hover',
	 * // and extract the sub-name 'hover'
	 * $result = ModuleUtils::has_value( $attr, [
	 *     'breakpoint' => 'mobile',
	 *     'state' => 'hover',
	 *     'subName' => 'hover',
	 * ] );
	 *
	 * // Check if the attribute has a value for any breakpoint and state using a value resolver function
	 * $result = ModuleUtils::has_value( $attr, [
	 *     'valueResolver' => function( $value, $args ) {
	 *         // Custom value resolution logic
	 *         // ...
	 *         return $resolved_value;
	 *     },
	 * ] );
	 *
	 * // Check if the attribute has a value for the breakpoint 'desktop' and state 'hover',
	 * // using the 'inherited' mode for resolving the attribute value
	 * $result = ModuleUtils::has_value( $attr, [
	 *     'breakpoint' => 'desktop',
	 *     'state' => 'hover',
	 *     'inheritedMode' => 'inherited',
	 * ] );
	 * ```
	 */
	public static function has_value( array $attr, array $options = [] ): bool {
		if ( ! $attr ) {
			return false;
		}

		$breakpoint        = $options['breakpoint'] ?? null;
		$state             = $options['state'] ?? null;
		$breakpoint_states = MultiViewUtils::get_breakpoints_states();

		// When both breakpoint and state are specified, do not need to iterate through all breakpoints and states.
		// Simply calculate the value based on the specified breakpoint and state.
		if ( $breakpoint && $state ) {
			if ( ! self::_validate_breakpoint_and_state( $breakpoint, $state, $breakpoint_states ) ) {
				return false;
			}

			return self::_calculate_value(
				$attr,
				array_merge(
					$options,
					[
						'breakpoint' => $breakpoint,
						'state'      => $state,
					]
				)
			);
		}

		foreach ( $breakpoint_states as $breakpoint_check => $states ) {
			foreach ( $states as $state_check ) {
				if ( ! self::_validate_breakpoint_and_state( $breakpoint_check, $state_check, $breakpoint_states ) ) {
					continue;
				}

				if ( $breakpoint && $breakpoint_check !== $breakpoint ) {
					continue;
				}

				if ( $state && $state_check !== $state ) {
					continue;
				}

				if ( self::_calculate_value(
					$attr,
					array_merge(
						$options,
						[
							'breakpoint' => $breakpoint_check,
							'state'      => $state_check,
						]
					)
				) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Calculates the value based on the given attributes and options.
	 *
	 * @since ??
	 *
	 * @param array $attr The attributes array.
	 * @param array $options The options array.
	 *
	 * @return bool Returns true if the value is calculated successfully, false otherwise.
	 *
	 * @throws InvalidArgumentException If the provided `$options['valueResolver']` is not a callable function.
	 */
	private static function _calculate_value( array $attr, array $options ) {
		$breakpoint     = $options['breakpoint'] ?? 'desktop';
		$state          = $options['state'] ?? 'value';
		$sub_name       = $options['subName'] ?? null;
		$value_resolver = $options['valueResolver'] ?? null;
		$inherited_mode = $options['inheritedMode'] ?? 'getAndInheritAll';

		if ( ! isset( $attr[ $breakpoint ][ $state ] ) ) {
			return false;
		}

		if ( $inherited_mode ) {
			$value = self::use_attr_value(
				[
					'attr'       => $attr,
					'breakpoint' => $breakpoint,
					'state'      => $state,
					'mode'       => $inherited_mode,
				]
			);
		} else {
			$value = $attr[ $breakpoint ][ $state ];
		}

		if ( $sub_name ) {
			$value = ArrayUtility::get_value( $value ?? [], $sub_name );
		}

		if ( $value_resolver ) {
			if ( is_callable( $value_resolver ) ) {
				$value = call_user_func(
					$value_resolver,
					$value,
					[
						'breakpoint' => $breakpoint,
						'state'      => $state,
					]
				);
			} else {
				throw new InvalidArgumentException( 'The `valueResolver` argument must be a callable function' );
			}
		}

		if ( is_bool( $value ) ) {
			$has_value = $value;
		} elseif ( is_scalar( $value ) ) {
			// Check the value length.
			$has_value = strlen( strval( $value ) ) > 0;
		} else {
			// Check if the value is not empty.
			$has_value = (bool) $value;
		}

		if ( $has_value ) {
			return true;
		}

		return false;
	}

	/**
	 * Validates the given breakpoint and state against the provided breakpoint-states mapping.
	 *
	 * @param string $breakpoint The breakpoint to validate.
	 * @param string $state The state to validate.
	 * @param array  $breakpoint_states_mapping The mapping of breakpoints to states.
	 * @return bool Returns true if the breakpoint and state are valid, false otherwise.
	 */
	private static function _validate_breakpoint_and_state( string $breakpoint, string $state, array $breakpoint_states_mapping ): bool {
		if ( ! isset( $breakpoint_states_mapping[ $breakpoint ] ) ) {
			return false;
		}

		if ( ! in_array( $state, $breakpoint_states_mapping[ $breakpoint ], true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get module class name defined in module.json config.
	 *
	 * - If moduleClassName property in module.json config is falsy, it will fallback to
	 * use convert module name to class name.
	 *
	 * This function is equivalent of JS function getModuleClassName located in
	 * /visual-builder/packages/module-utils/src/get-module-class-name/index.ts
	 *
	 * @since ??
	 *
	 * @param string $module_name Module name.
	 *
	 * @return string Module class name configured in module.json config. Will return empty string on failure.
	 */
	public static function get_module_class_name( $module_name ) {
		$module_config = WP_Block_Type_Registry::get_instance()->get_registered( $module_name );

		$module_class_name = '';

		if ( $module_config ) {
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block
			$module_class_name = $module_config->moduleClassName ?? '';
		}

		if ( ! $module_class_name ) {
			$module_class_name = self::get_module_class_by_name( $module_name );
		}

		return $module_class_name;
	}

	/**
	 * Get module order class name base defined in module.json config.
	 *
	 * - If moduleOrderClassName property in module.json config is falsy, it will fallback to
	 * use moduleClassName property that is defined in module.json config.
	 * - If moduleClassName property in module.json config is falsy, it will fallback to
	 * convert module name to class name.
	 *
	 * This function is equivalent of JS function getModuleOrderClassBase located in
	 * /visual-builder/packages/module-utils/src/get-module-order-class-base/index.ts
	 *
	 * @since ??
	 *
	 * @param string $module_name Module name.
	 *
	 * @return string Module order class name base. Will return empty string if module is not found.
	 */
	public static function get_module_order_class_name_base( $module_name ) {
		$module_config = WP_Block_Type_Registry::get_instance()->get_registered( $module_name );

		$module_order_class_name_base = '';

		if ( $module_config ) {
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block
			$module_order_class_name_base = $module_config->moduleOrderClassName ?? '';

			if ( ! $module_order_class_name_base ) {
				// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block
				$module_order_class_name_base = $module_config->moduleClassName ?? '';
			}
		}

		if ( ! $module_order_class_name_base ) {
			$module_order_class_name_base = self::get_module_class_by_name( $module_name );
		}

		return $module_order_class_name_base;
	}

	/**
	 * Get module order class name defined in module.json config and add module order index as suffix.
	 *
	 * The base of module order class is populated as follows:
	 * - It will use the moduleOrderClassName property in module.json config if it is not falsy.
	 * - It will use the moduleClassName property in module.json config if it is not falsy.
	 * - It will convert module name to class name
	 *
	 * This function is equivalent of JS function getModuleOrderClassName located in
	 * /visual-builder/packages/module-utils/src/get-module-order-class-name/index.ts
	 *
	 * @since ??
	 *
	 * @param string   $module_id      Module unique ID.
	 * @param int|null $store_instance The ID of instance where this block stored in BlockParserStore class.
	 *
	 * @return string Module order class name. Will return empty string if module is not found.
	 */
	public static function get_module_order_class_name( $module_id, $store_instance = null ) {
		$module_object = BlockParserStore::get( $module_id, $store_instance );

		$layout_type = BlockParserStore::get_layout_type();

		$layout_map = apply_filters(
			'et_builder_order_class_name_suffix_map',
			[
				'default'          => '',
				'et_header_layout' => '_tb_header',
				'et_body_layout'   => '_tb_body',
				'et_footer_layout' => '_tb_footer',
			]
		);

		$selector_suffix = $layout_map[ $layout_type ] ?? '';

		if ( $module_object ) {
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block
			$module_order_class_name_base = self::get_module_order_class_name_base( $module_object->blockName );

			if ( $module_order_class_name_base ) {
				// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block
				return $module_order_class_name_base . '_' . $module_object->orderIndex . $selector_suffix;
			}
		}

		return '';
	}

	/**
	 * Loads inline fonts for a module.
	 *
	 * This function enqueues the inline font from a module's inline fonts list,
	 * such that the font assets will be loaded in the browser.
	 *
	 * @since ??
	 *
	 * @param array $attrs The attributes of the module.
	 *
	 * @returns void
	 *
	 * @example
	 * ```php
	 * $attrs = [
	 *     // ... rest of the attributes
	 *    'content' => [
	 *      'decoration' => [
	 *        'inlineFont' => [
	 *          'desktop' => [
	 *            'value' => [
	 *              'families' => [
	 *                'Arima',
	 *                'Yatra One',
	 *              ],
	 *            ],
	 *          ],
	 *        ],
	 *      ],
	 *   ],
	 * ];
	 *
	 * ModuleUtils::load_module_inline_font( $attrs );
	 * ```
	 */
	public static function load_module_inline_font( array $attrs ): void {
		$inline_font = $attrs['content']['decoration']['inlineFont'] ?? [];

		foreach ( $inline_font as $breakpoint => $states ) {
			foreach ( array_keys( $states ) as $state ) {
				$attr_value = self::use_attr_value(
					[
						'attr'       => $inline_font,
						'breakpoint' => $breakpoint,
						'state'      => $state,
						'mode'       => 'getAndInheritAll',
					]
				);

				$font_family_names = $attr_value['families'] ?? [];

				foreach ( $font_family_names as $font_family ) {
					Fonts::add( $font_family );
				}
			}
		}
	}

	/**
	 * Merge Attrs.
	 *
	 * This function is used to merge attrs with default attrs.
	 *
	 * This function is equivalent of JS function mergeAttrs located in
	 * visual-builder/packages/module-utils/src/merge-attrs/index.ts.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Optional. An array of options.
	 *
	 *     @type array $defaultAttrs Default attrs.
	 *     @type array $presetAttrs Preset attrs.
	 *     @type array $attrs Attrs.
	 * }
	 *
	 * @return array Merged attrs.
	 */
	public static function merge_attrs( array $args = [] ): array {
		$default_attrs = $args['defaultAttrs'] ?? [];
		$preset_attrs  = $args['presetAttrs'] ?? [];
		$attrs         = $args['attrs'] ?? [];

		return array_replace_recursive( [], $default_attrs, $preset_attrs, $attrs );
	}

	/**
	 * This method sorts the breakpoints based on a predetermined order and filters attributes based on breakpoint state.
	 *
	 * It takes an associative array as input and returns a new array that has the keys
	 * sorted based on a defined order. If a key doesn't exist in the defined order, it's assumed
	 * it should be placed last. The order currently is 'desktop', 'desktopAbove', 'tablet',
	 * 'tabletOnly', and then 'phone'.
	 *
	 * Additionally, this function filters out any breakpoints that are not enabled using
	 * `Breakpoint::is_enabled()`, ensuring only active breakpoints are included in the result.
	 *
	 * @since ??
	 *
	 * @param array $attr The associative array which keys are to be sorted.
	 * @param array $breakpoint_order The order of the breakpoints.
	 * @param bool  $enabled_only Whether to filter out disabled breakpoints.
	 *
	 * @return array $sorted_attr An associative array which keys are sorted in the defined order and filtered to include only enabled breakpoints.
	 *
	 * @example
	 *
	 * $input = ['phone' => 'val1', 'tablet' => 'val2', 'desktop' => 'val3'];
	 * print_r(\ModuleUtils::sort_breakpoints($input));
	 * // Outputs: Array('desktop' => 'val3', 'tablet' => 'val2', 'phone' => 'val1')
	 */
	public static function sort_breakpoints(
		array $attr,
		?array $breakpoint_order = null,
		?bool $enabled_only = false
	): array {
		$order = $breakpoint_order ?? [

			// baseDevice.
			'desktop',

			// Smaller than baseDevice, large to small.
			'desktopAbove', // disabled-on specific.
			'tabletWide',
			'tablet',
			'tabletOnly', // disabled-on specific.
			'phoneWide',
			'phone',

			// Larger than baseDevice, small to large.
			'widescreen',
			'ultraWide',
		];

		// A copy of the array keys in their current order.
		$keys = array_keys( $attr );

		// Sort the keys based on their position in $order.
		usort(
			$keys,
			function ( $a, $b ) use ( $order ) {
				$position_a = array_search( $a, $order, true );
				$position_b = array_search( $b, $order, true );

				// If a key is not found in $order, we assume it comes last.
				$position_a = false === $position_a ? count( $order ) : $position_a;
				$position_b = false === $position_b ? count( $order ) : $position_b;

				return $position_a <=> $position_b;
			}
		);

		// Create a new array with the keys sorted as required.
		$sorted_attr = [];
		foreach ( $keys as $key ) {
			// Skip if the breakpoint is not enabled.
			if ( $enabled_only && ! Breakpoint::is_enabled_for_style( $key ) ) {
				continue;
			}

			$sorted_attr[ $key ] = $attr[ $key ];
		}

		return $sorted_attr;
	}

	/**
	 * This method sorts the states based on a predetermined order.
	 *
	 * It takes an associative array as input and returns a new array that has the keys
	 * sorted based on a defined order. If a key doesn't exist in the defined order, it's assumed
	 * it should be placed last. The order currently is 'value', 'hover', 'sticky',
	 * 'tabletOnly', and then 'phone'.
	 *
	 * @since ??
	 *
	 * @param array $attr The associative array which keys are to be sorted.
	 *
	 * @return array $sorted_attr An associative array which keys are sorted in the defined order.
	 *
	 * @example
	 *
	 * $input = ['hover' => 'val1', 'sticky' => 'val2', 'value' => 'val3'];
	 * print_r(\ModuleUtils::sort_breakpoints($input));
	 * // Outputs: Array('value' => 'val3', 'hover' => 'val2', 'sticky' => 'val1')
	 */
	public static function sort_states( array $attr ): array {
		// TODO feat(D5, Responsive Views): Replace when we have a sort/priority system for states [https://github.com/elegantthemes/Divi/issues/41620].
		$order = [
			'value',
			'hover',
			'sticky',
		];

		// A copy of the array keys in their current order.
		$keys = array_keys( $attr );

		// Sort the keys based on their position in $order.
		usort(
			$keys,
			function ( $a, $b ) use ( $order ) {
				$position_a = array_search( $a, $order, true );
				$position_b = array_search( $b, $order, true );

				// If a key is not found in $order, we assume it comes last.
				$position_a = false === $position_a ? count( $order ) : $position_a;
				$position_b = false === $position_b ? count( $order ) : $position_b;

				return $position_a <=> $position_b;
			}
		);

		// Create a new array with the keys sorted as required.
		$sorted_attr = [];
		foreach ( $keys as $key ) {
			$sorted_attr[ $key ] = $attr[ $key ];
		}

		return $sorted_attr;
	}

	/**
	 * Generate preset class name.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $presetType       The Preset type. Can be 'module' or 'group'.
	 *     @type string $presetModuleName The Preset Module Name.
	 *     @type string $presetGroupName  The Preset Group Name.
	 *     @type string $presetId         The Preset ID.
	 * }
	 *
	 * @return string The preset class name.
	 */
	public static function generate_preset_class_name( array $args ): string {
		// TODO feat(D5, Deprecated): Create class for handling deprecating functions / methdos / constructor / classes [https://github.com/elegantthemes/Divi/issues/41805].
		_deprecated_function( __METHOD__, '5.0.0-public-alpha-10', 'GlobalPresetItemUtils::generate_preset_class_name' );

		return GlobalPresetItemUtils::generate_preset_class_name( $args );
	}

	/**
	 * Convert the module name for the section preset.
	 *
	 * @since ??
	 *
	 * @param string $module_name The module name.
	 * @param array  $attrs The module attributes.
	 *
	 * @return string The converted module name.
	 */
	public static function maybe_convert_preset_module_name( string $module_name, array $attrs ): string {
		if ( 'divi/section' === $module_name ) {
			$section_type = $attrs['module']['advanced']['type']['desktop']['value'] ?? null;

			if ( 'fullwidth' === $section_type ) {
					return 'divi/fullwidth-section';
			}

			if ( 'specialty' === $section_type ) {
					return 'divi/specialty-section';
			}
		}

		return $module_name;
	}

	/**
	 * Removes empty attributes.
	 *
	 * This function recursively filters the provided attributes, removing any elements that are empty arrays or null values.
	 * It makes exceptions for specific attributes that are allowed to be empty arrays:
	 * - The 'style' attribute of a 'font' group.
	 * - The 'size' attribute of a 'sizing' group.
	 * - Any attribute for which the `divi_module_utils_remove_empty_array_attributes_pre_filter` filter returns `true`.
	 *
	 * @since ??
	 *
	 * @param array $attrs The array of attributes to filter.
	 * @return array The filtered array with empty attributes removed.
	 */
	public static function remove_empty_array_attributes( array $attrs ): array {
		return ArrayUtility::filter_deep(
			$attrs,
			function ( $value, $key, $path ) {
				// Remove null values (used by migrations to mark attributes for removal).
				if ( null === $value ) {
					return false;
				}

				/**
				 * Filters the keep/remove decision before the built-in empty-value checks run.
				 *
				 * Return `true` to keep the current attribute value, `false` to remove it,
				 * or `null` to fall through to the built-in filtering logic below.
				 *
				 * @since ??
				 *
				 * @param bool|null $pre_filter Override for the keep/remove decision. Default null.
				 * @param mixed     $value      Current attribute value being evaluated.
				 * @param string    $key        Current attribute key being evaluated.
				 * @param array     $path       Path segments from the root of the attrs tree to the parent of $key.
				 */
				$pre_filter = apply_filters( 'divi_module_utils_remove_empty_array_attributes_pre_filter', null, $value, $key, $path );

				// Bail early if the pre-filter returned an explicit boolean decision.
				if ( is_bool( $pre_filter ) ) {
					return $pre_filter;
				}

				// Return true if the value is an empty array and the path is the style attribute of a font group.
				$path_items = array_slice( $path, -3 );

				if ( count( $path_items ) ) {
					// Allow empty array for 'style' attribute of a 'font' group.
					if ( 'font' === $path_items[0] && 'style' === $key ) {
						return true;
					}

					// Allow empty array for 'size' attribute of a 'sizing' group.
					if ( 'sizing' === $path_items[0] && 'size' === $key ) {
						return true;
					}
				}

				// CRITICAL FIX: Never keep empty arrays for 'value' keys in responsive structures.
				// These represent empty objects {} in JS that became arrays [] in PHP during JSON decode.
				// Keeping them causes corruption when merged via array_replace_recursive.
				// Common paths: desktop.value, tablet.value, phone.value, etc.
				if ( 'value' === $key && is_array( $value ) && empty( $value ) ) {
					return false;
				}

				return is_array( $value ) && empty( $value ) ? false : true;
			}
		);
	}

	/**
	 * Remove empty array attributes from all parsed blocks recursively.
	 *
	 * Prevents PHP json_decode/json_encode round-trip corruption where empty
	 * JSON objects {} become empty arrays [] during re-serialization.
	 *
	 * @since ??
	 *
	 * @param array &$blocks Array of parsed blocks to process (passed by reference).
	 *
	 * @return void
	 */
	public static function clean_blocks_empty_array_attributes( array &$blocks ): void {
		foreach ( $blocks as &$block ) {
			if ( ! empty( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
				$block['attrs'] = self::remove_empty_array_attributes( $block['attrs'] );
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				self::clean_blocks_empty_array_attributes( $block['innerBlocks'] );
			}
		}
	}

	/**
	 * Nest an array of attributes under a base path.
	 *
	 * This function takes a base path and an array of attributes and nests the attributes under the base path.
	 * The base path is a string that represents a path to the nested array. The function returns an array with the
	 * attributes nested under the base path.
	 *
	 * @since ??
	 *
	 * @param string $base_path The base path under which the attributes will be nested.
	 * @param array  $attrs     The array of attributes to nest.
	 *
	 * @return array The nested array of attributes.
	 */
	public static function nest_array_attributes( string $base_path, array $attrs ): array {
		$keys   = explode( '.', $base_path );
		$nested = $attrs;

		while ( $keys ) {
			$key    = array_pop( $keys );
			$nested = [ $key => $nested ];
		}

		return $nested;
	}

	/**
	 * Recursively removes keys from a target array that also exist in a reference array.
	 *
	 * This function compares a target array and a reference array. If a key exists in both,
	 * the key-value pair is removed from the target array. This process is performed recursively
	 * for nested arrays. If the reference array is empty, the target array is returned without changes.
	 *
	 * @since ??
	 *
	 * @param array $target_attrs The array from which keys will be removed.
	 * @param array $reference_attrs The array used to determine which keys to remove from the target.
	 *
	 * @return array The target array, modified with keys removed if they exist in the reference array.
	 */
	public static function remove_matching_attrs( array $target_attrs, array $reference_attrs ): array {
		if ( empty( $reference_attrs ) ) {
			return $target_attrs;
		}

		foreach ( $target_attrs as $key => $value ) {
			if ( array_key_exists( $key, $reference_attrs ) ) {
				$reference_value = $reference_attrs[ $key ];

				if ( is_array( $value ) && is_array( $reference_value ) ) {
					$target_attrs[ $key ] = self::remove_matching_attrs( $value, $reference_value );
				} elseif ( is_scalar( $value ) && is_scalar( $reference_value ) ) {
					unset( $target_attrs[ $key ] );
				}
			}
		}

		return $target_attrs;
	}

	/**
	 * Recursively replace value in a target array with value from a reference array.
	 *
	 * This function compares a target array and a reference array. If a key exists in both,
	 * the value in a target array will be replaced with the value from the reference array. This process is performed recursively
	 * for nested arrays. If the reference array is empty, the target array is returned without changes.
	 *
	 * @since ??
	 *
	 * @param array $target_attrs The array from which keys will be removed.
	 * @param array $reference_attrs The array used to determine which keys to remove from the target.
	 *
	 * @return array The target array, modified with keys removed if they exist in the reference array.
	 */
	public static function replace_matching_attrs( array $target_attrs, array $reference_attrs ): array {
		if ( empty( $reference_attrs ) ) {
			return $target_attrs;
		}

		foreach ( $target_attrs as $key => $value ) {
			if ( array_key_exists( $key, $reference_attrs ) ) {
				$reference_value = $reference_attrs[ $key ];

				if ( is_array( $value ) && is_array( $reference_value ) ) {
					$target_attrs[ $key ] = self::replace_matching_attrs( $value, $reference_value );
				} elseif ( is_scalar( $value ) && is_scalar( $reference_value ) ) {
					$target_attrs[ $key ] = $reference_value;
				}
			}
		}

		return $target_attrs;
	}

	/**
	 * Returns whether module attributes include at least one active group preset assignment.
	 *
	 * Mirrors Visual Builder `hasActiveGroupPreset` / `hasActiveGroupPresetAssignment`: only explicit
	 * non-empty `groupPreset` presetId stacks count. Implicit defaults from `GlobalPreset::get_selected_group_presets()`
	 * are excluded so server strip guards stay aligned with import and VB render paths.
	 *
	 * @since ??
	 *
	 * @param array|null $group_preset Group preset map from block attributes.
	 *
	 * @return bool
	 */
	public static function has_active_group_preset_assignment( ?array $group_preset ): bool {
		if ( empty( $group_preset ) ) {
			return false;
		}

		foreach ( $group_preset as $group_preset_item ) {
			if ( ! is_array( $group_preset_item ) ) {
				continue;
			}

			$preset_ids = GlobalPreset::normalize_preset_stack( $group_preset_item['presetId'] ?? '' );

			if ( ! empty( $preset_ids ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns whether module attributes include an active module preset assignment.
	 *
	 * Mirrors Visual Builder `hasActiveModulePresetAssignment`: only explicit non-empty normalized
	 * `modulePreset` stacks count (excludes `default`, `_initial`, and empty values).
	 *
	 * @since ??
	 *
	 * @param mixed $module_preset Module preset stack from block attributes.
	 *
	 * @return bool
	 */
	public static function has_active_module_preset_assignment( $module_preset ): bool {
		return ! empty( GlobalPreset::normalize_preset_stack( $module_preset ?? '' ) );
	}

	/**
	 * Whether module preset `styleAttrs` merge into preset CSS (matches VB `ModuleLibraryPresetStylesContainer`).
	 *
	 * @since ??
	 *
	 * @param string $module_name Module name.
	 *
	 * @return bool
	 */
	public static function module_preset_merges_style_attrs( string $module_name ): bool {
		return in_array(
			$module_name,
			[
				'divi/button',
				'divi/cta',
				'divi/woocommerce-cart-products',
				'divi/woocommerce-cart-totals',
				'divi/social-media-follow-item',
			],
			true
		);
	}

	/**
	 * Recursively removes key-value pairs from a target array that have matching values in a reference array.
	 *
	 * This function compares a target array and a reference array. If a key exists in both and the values are equal,
	 * the key-value pair is removed from the target array. This process is performed recursively for nested arrays.
	 * If the reference array is empty, the target array is returned without changes.
	 *
	 * @since ??
	 *
	 * @param array $target_attrs The array from which key-value pairs will be removed.
	 * @param array $reference_attrs The array used to determine which key-value pairs to remove from the target.
	 *
	 * @return array The target array, modified with key-value pairs removed if they have matching values in the reference array.
	 */
	public static function remove_matching_values( array $target_attrs, array $reference_attrs ): array {
		if ( empty( $reference_attrs ) ) {
			return $target_attrs;
		}

		foreach ( $target_attrs as $key => $value ) {
			if ( array_key_exists( $key, $reference_attrs ) ) {
				$reference_value = $reference_attrs[ $key ];

				if ( is_array( $value ) && is_array( $reference_value ) ) {
					$target_attrs[ $key ] = self::remove_matching_values( $value, $reference_value );
					if ( empty( $target_attrs[ $key ] ) ) {
						unset( $target_attrs[ $key ] );
					}
				} elseif ( $value === $reference_value ) {
					unset( $target_attrs[ $key ] );
				}
			}
		}

		return $target_attrs;
	}

	/**
	 * Extract the title for a link.
	 *
	 * @since ??
	 *
	 * @param string $html_text The HTML content of the link.
	 *
	 * @return string The extracted title.
	 */
	public static function extract_link_title( string $html_text ): string {
		return wp_kses(
			$html_text,
			[
				'strong' => [
					'id'    => [],
					'class' => [],
					'style' => [],
				],
				'em'     => [
					'id'    => [],
					'class' => [],
					'style' => [],
				],
				'i'      => [
					'id'    => [],
					'class' => [],
					'style' => [],
				],
			]
		);
	}

	/**
	 * Processes and inherits position style attributes across breakpoints and states.
	 *
	 * This function calculates the position style attributes for different breakpoints
	 * (desktop, tablet, phone) and states (value, hover, sticky), ensuring that missing
	 * values inherit from parent breakpoints or states. Redundant or empty attributes
	 * are removed to streamline the final output.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments for position style inheritance.
	 *
	 *     @type array $attr                    Position style attributes to process.
	 *                                          to use if values are not explicitly defined.
	 * }
	 * @return array Final processed position style attributes with inheritance applied.
	 *
	 * @example:
	 * ```php
	 * $attr_value_with_inherited = [
	 *    'desktop' => [
	 *        'value' => [
	 *            'mode' => 'relative',
	 *            'offset' => [
	 *                'vertical' => '10px',
	 *                'horizontal' => '20px',
	 *            ],
	 *        ],
	 *    ],
	 *    'tablet' => [
	 *        'value' => [
	 *            'mode' => 'absolute',
	 *            'offset' => [
	 *                'vertical' => '15px',
	 *            ],
	 *        ],
	 *        'hover' => [
	 *            'mode' => 'fixed',
	 *            'offset' => [
	 *                'vertical' => '5px',
	 *                'horizontal' => '10px',
	 *            ],
	 *        ],
	 *    ],
	 * ];
	 *
	 * $breakpoint = 'tablet';
	 * $state = 'value';
	 *
	 * $updated_attr_value_with_inherited = self::get_or_inherit_position_style_attr([
	 *    'attr' => $attr_value_with_inherited
	 * ]);
	 * ```
	 */
	public static function get_or_inherit_position_style_attr( array $args ): array {
		$initial_style_attr         = $args['attr'] ?? [];
		$default_printed_style_attr = $args['defaultPrintedStyleAttr'] ?? [];

		// Get enabled breakpoints.
		$enabled_breakpoints = Breakpoint::get_enabled_breakpoint_names();

		// Pre-populate with the passed style attributes.
		$attr_value_with_inherited = $initial_style_attr;

		// If we have a position-style, handle inheritance for breakpoints and states.
		if ( ! empty( $attr_value_with_inherited ) ) {
			// Process enabled breakpoints in inheritance order.
			$states = [ 'value', 'hover', 'sticky' ];

			foreach ( $enabled_breakpoints as $breakpoint ) {
				if ( array_key_exists( $breakpoint, $attr_value_with_inherited ) ) {
					foreach ( $states as $state ) {
						$attr_value_with_inherited = self::_inherit_position_style_values( $attr_value_with_inherited, $breakpoint, $state, $default_printed_style_attr );
					}
				}
			}
		}

		// Prepare attributes to be returned.
		$attr_to_be_returned = $attr_value_with_inherited;

		// Remove redundant inherited values.
		if ( ! empty( $attr_to_be_returned ) ) {
			// Process enabled breakpoints in reverse order for cleanup.
			$reversed_breakpoints = array_reverse( $enabled_breakpoints );
			$states               = [ 'sticky', 'hover', 'value' ];

			foreach ( $reversed_breakpoints as $breakpoint ) {
				if ( array_key_exists( $breakpoint, $attr_to_be_returned ) ) {
					foreach ( $states as $state ) {
						$attr_to_be_returned = self::_return_position_style_values( $attr_to_be_returned, $breakpoint, $state );
					}

					if ( empty( self::_array_trim( $attr_to_be_returned[ $breakpoint ] ) ) ) {
						unset( $attr_to_be_returned[ $breakpoint ] );
					}
				}
			}
		}

		return $attr_to_be_returned;
	}

	/**
	 * Removes redundant or empty position style values for a specific breakpoint and state.
	 *
	 * This function compares the current position style values for a given breakpoint and state
	 * with the parent breakpoint and state. If the values are identical or empty, they are removed.
	 * Otherwise, the current values are retained in the output.
	 *
	 * @since ??
	 *
	 * @param array  $attr_to_be_returned Position style attributes being processed.
	 * @param string $breakpoint          The breakpoint being processed (e.g., 'desktop', 'tablet', 'phone').
	 * @param string $state               The state being processed (e.g., 'value', 'hover', 'sticky').
	 *
	 * @return array Processed position style attributes with redundant values removed.
	 *
	 * @example:
	 * ```php
	 * $attr_to_be_returned = [
	 *    'desktop' => [
	 *        'value' => [
	 *            'mode' => 'absolute',
	 *            'offset' => [
	 *                'vertical' => '10px',
	 *                'horizontal' => '20px',
	 *            ],
	 *        ],
	 *    ],
	 *    'tablet' => [
	 *        'value' => [
	 *            'mode' => 'absolute',
	 *            'offset' => [
	 *                'vertical' => '15px',
	 *            ],
	 *        ],
	 *    ],
	 * ];
	 *
	 * $breakpoint = 'tablet';
	 * $state = 'value';
	 *
	 * $cleaned_attr_values = self::_return_position_style_values( $attr_to_be_returned, $breakpoint, $state );
	 * ```
	 */
	private static function _return_position_style_values( array $attr_to_be_returned, string $breakpoint, string $state ): array {
		// TODO feat(D5, Refactor): pass breakpointNames and baseBreakpoint as arguments [https://github.com/elegantthemes/Divi/issues/41620].
		$base_breakpoint   = Breakpoint::get_base_breakpoint_name();
		$breakpoint_names  = Breakpoint::get_enabled_breakpoint_names();
		$parent_breakpoint = self::get_inherit_breakpoint(
			[
				'breakpoint'      => $breakpoint,
				'state'           => $state,
				'baseBreakpoint'  => $base_breakpoint,
				'breakpointNames' => $breakpoint_names,
			]
		);
		$parent_state      = self::get_inherit_state(
			[
				'breakpoint'      => $breakpoint,
				'state'           => $state,
				'baseBreakpoint'  => $base_breakpoint,
				'breakpointNames' => $breakpoint_names,
			]
		);

		$is_desktop_value       = 'desktop' === $breakpoint && 'value' === $state;
		$current_position_style = $attr_to_be_returned[ $breakpoint ][ $state ] ?? [];
		$parent_position_style  = $attr_to_be_returned[ $parent_breakpoint ][ $parent_state ] ?? [];
		$position_styles_match  = self::_is_same( $current_position_style, $parent_position_style );
		$is_current_empty       = ! $current_position_style;

		if ( $is_desktop_value && ! $is_current_empty ) {
			$attr_to_be_returned[ $breakpoint ][ $state ] = $current_position_style;
		} elseif ( ! $position_styles_match && ! $is_current_empty ) {
			$attr_to_be_returned[ $breakpoint ][ $state ] = $current_position_style;
		} else {
			unset( $attr_to_be_returned[ $breakpoint ][ $state ] );
		}

		return $attr_to_be_returned;
	}

	/**
	 * Inherits position style values for a specific breakpoint and state.
	 *
	 * This function ensures that missing position style values in a given breakpoint
	 * and state are inherited from the parent breakpoint and state. Defaults are applied
	 * when values are missing in both the current and parent contexts.
	 *
	 * @since ??
	 *
	 * @param array  $attr_value_with_inherited Position style attributes being processed.
	 * @param string $breakpoint                The breakpoint being processed (e.g., 'desktop', 'tablet', 'phone').
	 * @param string $state                     The state being processed (e.g., 'value', 'hover', 'sticky').
	 * @param array  $default_printed_style_attr Default printed style attributes to apply.
	 *
	 * @return array Position style attributes with inherited values applied.
	 *
	 * @example:
	 * ```php
	 * $attr_value_with_inherited = [
	 *    'desktop' => [
	 *        'value' => [
	 *            'mode' => 'relative',
	 *            'offset' => [
	 *                'vertical' => '10px',
	 *                'horizontal' => '20px',
	 *            ],
	 *        ],
	 *    ],
	 *    'tablet' => [],
	 * ];
	 *
	 * $breakpoint = 'tablet';
	 * $state = 'value';
	 *
	 * $updated_attr_value_with_inherited = self::_inherit_position_style_values( $attr_value_with_inherited, $breakpoint, $state );
	 * ```
	 */
	private static function _inherit_position_style_values( array $attr_value_with_inherited, string $breakpoint, string $state, array $default_printed_style_attr = [] ): array {
		// TODO feat(D5, Refactor): pass breakpointNames and baseBreakpoint as arguments [https://github.com/elegantthemes/Divi/issues/41620].
		$base_breakpoint    = Breakpoint::get_base_breakpoint_name();
		$breakpoint_names   = Breakpoint::get_enabled_breakpoint_names();
		$inherit_breakpoint = self::get_inherit_breakpoint(
			[
				'breakpoint'      => $breakpoint,
				'state'           => $state,
				'baseBreakpoint'  => $base_breakpoint,
				'breakpointNames' => $breakpoint_names,
			]
		);
		$inherit_state      = self::get_inherit_state(
			[
				'breakpoint'      => $breakpoint,
				'state'           => $state,
				'baseBreakpoint'  => $base_breakpoint,
				'breakpointNames' => $breakpoint_names,
			]
		);

		$attr_values        = $attr_value_with_inherited[ $breakpoint ][ $state ] ?? [];
		$attr_parent_values = $attr_value_with_inherited[ $inherit_breakpoint ][ $inherit_state ] ?? [];

		// Get the default mode from printed style attributes if available.
		$default_mode = $default_printed_style_attr[ $breakpoint ][ $state ]['mode'] ?? 'default';

		// If parent values are empty, try to inherit from base breakpoint (desktop).
		if ( empty( $attr_parent_values ) && 'desktop' !== $inherit_breakpoint ) {
			$attr_parent_values = $attr_value_with_inherited['desktop'][ $inherit_state ] ?? [];
		}

		// Filter out empty string values from origin before merging to prevent data loss.
		// Empty strings are used by VB to "clear" responsive values, but should not override parent values.
		// This matches TypeScript behavior: {...attrParentValues?.origin, ...attrValues?.origin}.
		$parent_origin  = array_filter(
			$attr_parent_values['origin'] ?? [],
			function ( $value ) {
				return '' !== $value;
			}
		);
		$current_origin = array_filter(
			$attr_values['origin'] ?? [],
			function ( $value ) {
				return '' !== $value;
			}
		);

		// Treat empty string mode as "not set" and inherit from parent.
		// Empty strings are used by VB to "clear" responsive values.
		$current_mode = $attr_values['mode'] ?? null;
		$parent_mode  = $attr_parent_values['mode'] ?? null;
		$final_mode   = ( '' !== $current_mode && null !== $current_mode ) ? $current_mode : ( $parent_mode ?? $default_mode );

		$attr_value_with_inherited[ $breakpoint ][ $state ] = self::_array_trim(
			[
				'mode'   => $final_mode,
				'origin' => array_merge( $parent_origin, $current_origin ),
				'offset' => [
					'vertical'   => $attr_values['offset']['vertical'] ?? $attr_parent_values['offset']['vertical'] ?? '0',
					'horizontal' => $attr_values['offset']['horizontal'] ?? $attr_parent_values['offset']['horizontal'] ?? '0',
				],
			]
		);

		return $attr_value_with_inherited;
	}

	/**
	 * Get all merged attributes for a given block.
	 *
	 * This function will merge the default attributes, module preset attributes, and group preset attributes
	 * with the attributes of the block. The final attributes will be returned as an array.
	 *
	 * @since ??
	 *
	 * @param   BlockParserBlock $block Parsed block.
	 *
	 * @return array   Parent module attributes.
	 */
	public static function get_all_attrs( BlockParserBlock $block ) {
		$default_attrs = ModuleRegistration::get_default_attrs( $block->blockName );
		$attrs         = $block->attrs ?? [];

		$group_presets = GlobalPreset::get_selected_group_presets(
			[
				'moduleName'  => $block->blockName,
				'moduleAttrs' => $attrs,
			]
		);

		$merged_group_preset_attrs = GlobalPreset::merge_selected_group_presets_by_priority( $group_presets );
		$group_render_attrs        = $merged_group_preset_attrs['renderAttrs'];

		// Get preset attributes for this module.
		$item_preset = GlobalPreset::get_selected_preset(
			[
				'moduleName'  => $block->blockName,
				'moduleAttrs' => $attrs ?? [],
			]
		);

		$preset_render_attrs = $item_preset->get_data_render_attrs();

		return array_replace_recursive(
			$default_attrs,
			$preset_render_attrs,
			$group_render_attrs,
			$attrs
		);
	}

	/**
	 * Check if the provided CSS unit is a math function or not.
	 * https://regex101.com/r/eHZbiF/1 - Regex.
	 *
	 * @since ??
	 *
	 * @param string $value CSS unit.
	 *
	 * @return boolean True if the string starts with one of the math functions; otherwise, false.
	 */
	public static function is_css_math_function( string $value ): bool {
		return preg_match( '/^(clamp|min|max|calc)\s*\(/', trim( $value ) ) === 1;
	}

	/**
	 * Check if the provided CSS value is a CSS variable (var()).
	 * https://regex101.com/r/0GqYLE/1 - Regex.
	 *
	 * @since ??
	 *
	 * @param string $value CSS value.
	 *
	 * @return boolean True if the string is a CSS variable; otherwise, false.
	 */
	public static function is_css_variable( string $value ): bool {
		return preg_match( '/^var\s*\(/', trim( $value ) ) === 1;
	}

	/**
	 * Check if the provided CSS value is a CSS global keyword (inherit, unset, initial).
	 * https://regex101.com/r/YExaWm/1 - Regex.
	 *
	 * @since ??
	 *
	 * @param string $value CSS value.
	 *
	 * @return boolean True if the string is a CSS global keyword; otherwise, false.
	 */
	public static function is_css_keyword( string $value ): bool {
		return preg_match( '/^(inherit|unset|initial)$/i', trim( $value ) ) === 1;
	}

	/**
	 * Check if a given CSS unit is a non-relative (absolute) unit.
	 *
	 * Non-relative units (px, pt, pc, in, cm, mm) can be accurately calculated
	 * against other absolute units.
	 *
	 * @since ??
	 *
	 * @param string $unit CSS unit to check (e.g., 'px', 'pt', 'rem').
	 *
	 * @return boolean True if the unit is a non-relative unit; otherwise, false.
	 */
	public static function is_non_relative_css_unit( string $unit ): bool {
		return in_array( $unit, [ 'px', 'pt', 'pc', 'in', 'cm', 'mm' ], true );
	}

	/**
	 * Determines if a module is a grid module that uses *Grid.decoration.layout.
	 *
	 * Gallery, Blog, Portfolio, and Filterable Portfolio modules store their layout
	 * settings at *Grid.decoration.layout instead of module.decoration.layout,
	 * and they default to 'grid' layout mode.
	 *
	 * This function is equivalent of JS function isGridModule
	 * in `@divi/module-utils` package.
	 *
	 * @since ??
	 *
	 * @param string|null $module_name The name of the module (e.g., 'divi/gallery', 'divi/blog').
	 *
	 * @return bool True if the module is a grid module, false otherwise.
	 */
	private static function _is_grid_module( ?string $module_name ): bool {
		if ( ! $module_name ) {
			return false;
		}

		return in_array(
			$module_name,
			[
				'divi/blog',
				'divi/filterable-portfolio',
				'divi/gallery',
				'divi/portfolio',
				'divi/woocommerce-product-gallery',
			],
			true
		);
	}

	/**
	 * Gets the appropriate layout attribute path for a parent module.
	 *
	 * Blog, Portfolio, and Filterable Portfolio modules use non-standard layout attribute paths
	 * (*Grid.decoration.layout) instead of the standard module.decoration.layout.
	 * All other modules including Menu and Fullwidth Menu use the standard module.decoration.layout path.
	 * This utility determines the correct path based on the module type.
	 *
	 * This function is equivalent of JS function getParentLayoutAttrPath
	 * in `@divi/module-utils` package.
	 *
	 * @since ??
	 *
	 * @param string|null $parent_module_name  The name of the parent module (e.g., 'divi/blog', 'divi/portfolio').
	 * @param array|null  $parent_module_attrs The parent module's attributes array.
	 *
	 * @return string The attribute path string for the layout (e.g., 'blogGrid.decoration.layout').
	 */
	private static function _get_parent_layout_attr_path( ?string $parent_module_name, ?array $parent_module_attrs ): string {
		// If module name is provided, use it to determine the path.
		if ( $parent_module_name ) {
			// Check if this is a grid module.
			if ( self::_is_grid_module( $parent_module_name ) ) {
				if ( 'divi/blog' === $parent_module_name ) {
					return 'blogGrid.decoration.layout';
				}
				if ( 'divi/portfolio' === $parent_module_name || 'divi/filterable-portfolio' === $parent_module_name ) {
					return 'portfolioGrid.decoration.layout';
				}
				if ( 'divi/gallery' === $parent_module_name || 'divi/woocommerce-product-gallery' === $parent_module_name ) {
					return 'galleryGrid.decoration.layout';
				}
			}
			// Menu and Fullwidth Menu modules use standard module.decoration.layout path
			// even though their layout styles are applied to an inner container.
			return 'module.decoration.layout';
		}

		// If module name is not available, detect from attrs structure.
		if ( $parent_module_attrs ) {
			// Check if parent has blogGrid (blog module).
			if ( isset( $parent_module_attrs['blogGrid'] ) ) {
				return 'blogGrid.decoration.layout';
			}

			// Check if parent has portfolioGrid (portfolio or filterable portfolio module).
			if ( isset( $parent_module_attrs['portfolioGrid'] ) ) {
				return 'portfolioGrid.decoration.layout';
			}

			// Check if parent has galleryGrid (gallery or woocommerce product gallery module).
			if ( isset( $parent_module_attrs['galleryGrid'] ) ) {
				return 'galleryGrid.decoration.layout';
			}
		}

		// Default to standard module path.
		// This applies to all other modules including Menu and Fullwidth Menu.
		return 'module.decoration.layout';
	}

	/**
	 * Check if parent module's layout is set to flex.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module-utils/functions/isParentFlexLayout isParentFlexLayout}
	 * in `@divi/module-utils` package.
	 *
	 * @since ??
	 *
	 * @param string $module_id The ID of the current module.
	 * @param int    $instance  Optional. The instance of the store you want to use. Default `null`.
	 *
	 * @return bool Returns true if the parent module's layout is set to "flex", otherwise false.
	 */
	public static function is_parent_flex_layout( string $module_id, ?int $instance = null ): bool {
		$parent = BlockParserStore::get_parent( $module_id, $instance );

		if ( ! $parent ) {
			return false;
		}

		$parent_attrs = self::get_all_attrs( $parent );

		// Blog, Portfolio, and Filterable Portfolio modules use *Grid.decoration.layout instead of module.decoration.layout.
		$layout_attr_path = self::_get_parent_layout_attr_path( $parent->blockName ?? null, $parent_attrs );
		$layout_attr_keys = explode( '.', $layout_attr_path );

		// Navigate through the nested array using the keys from the path.
		$layout_attrs = $parent_attrs;
		foreach ( $layout_attr_keys as $key ) {
			if ( ! isset( $layout_attrs[ $key ] ) ) {
				$layout_attrs = [];
				break;
			}
			$layout_attrs = $layout_attrs[ $key ];
		}

		$layout_attr = self::get_attr_value(
			[
				'attr'            => $layout_attrs,
				'breakpoint'      => 'desktop',
				'state'           => 'value',
				'breakpointNames' => Breakpoint::get_enabled_breakpoint_names(),
				'baseBreakpoint'  => 'desktop',
				'mode'            => 'getAndInheritAll',
			]
		);

		// Gallery, Blog, and Portfolio modules default to 'grid' layout when display is not set.
		$parent_name     = $parent->blockName ?? null;
		$default_display = self::_is_grid_module( $parent_name ) ? 'grid' : 'flex';

		return 'flex' === ( $layout_attr['display'] ?? $default_display );
	}

	/**
	 * Determines if the parent module's layout is set to "grid".
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module-utils/functions/isParentGridLayout isParentGridLayout}
	 * in `@divi/module-utils` package.
	 *
	 * @since ??
	 *
	 * @param string $module_id The ID of the current module.
	 * @param int    $instance  Optional. The instance of the store you want to use. Default `null`.
	 *
	 * @return bool Returns true if the parent module's layout is set to "grid", otherwise false.
	 */
	public static function is_parent_grid_layout( string $module_id, ?int $instance = null ): bool {
		$parent = BlockParserStore::get_parent( $module_id, $instance );

		if ( ! $parent ) {
			return false;
		}

		$parent_attrs = self::get_all_attrs( $parent );

		// Blog, Portfolio, and Filterable Portfolio modules use *Grid.decoration.layout instead of module.decoration.layout.
		$layout_attr_path = self::_get_parent_layout_attr_path( $parent->blockName ?? null, $parent_attrs );
		$layout_attr_keys = explode( '.', $layout_attr_path );

		// Navigate through the nested array using the keys from the path.
		$layout_attrs = $parent_attrs;
		foreach ( $layout_attr_keys as $key ) {
			if ( ! isset( $layout_attrs[ $key ] ) ) {
				$layout_attrs = [];
				break;
			}
			$layout_attrs = $layout_attrs[ $key ];
		}

		$layout_attr = self::get_attr_value(
			[
				'attr'            => $layout_attrs,
				'breakpoint'      => 'desktop',
				'state'           => 'value',
				'breakpointNames' => Breakpoint::get_enabled_breakpoint_names(),
				'baseBreakpoint'  => 'desktop',
				'mode'            => 'getAndInheritAll',
			]
		);

		// Gallery, Blog, and Portfolio modules default to 'grid' layout when display is not set.
		$parent_name     = $parent->blockName ?? null;
		$default_display = self::_is_grid_module( $parent_name ) ? 'grid' : 'flex';

		// Check if display is set to 'grid'.
		return 'grid' === ( $layout_attr['display'] ?? $default_display );
	}

	/**
	 * Generate selectors for a given base selector and sub selector.
	 *
	 * If a selector contains ':hover', the sub selector is inserted before ':hover'.
	 * Otherwise, the sub selector is appended to the selector.
	 *
	 * @since ??
	 *
	 * @param string $base_selector Base selector (can be comma-separated).
	 * @param string $sub_selector  Sub selector.
	 *
	 * @return string Combined selectors string.
	 */
	public static function generate_combined_selectors( string $base_selector, string $sub_selector ): string {
		$selectors = array_map(
			function ( $selector ) use ( $sub_selector ) {
				$selector = trim( $selector );
				if ( str_contains( $selector, ':hover' ) ) {
					// Insert sub_selector before :hover pseudo-class.
					return preg_replace( '/:hover/', ' ' . $sub_selector . ':hover', $selector );
				}
				return $selector . ' ' . $sub_selector;
			},
			explode( ',', $base_selector )
		);
		return implode( ', ', $selectors );
	}

	/**
	 * Add category filtering arguments to WP_Query args.
	 *
	 * Consolidates the category filtering logic used across multiple modules (Blog, Portfolio, etc.).
	 * Handles both the simple 'cat' parameter for posts and complex 'tax_query' for custom post types.
	 *
	 * @since ??
	 *
	 * @param array  $query_args  The existing WP_Query arguments array.
	 * @param mixed  $categories  Categories to filter by (string, array, or null).
	 * @param string $post_type   The post type being queried.
	 * @param int    $current_post_id Optional. The ID of the current post. Required for Visual Builder context (REST API calls from VB).
	 *
	 * @return array Modified query_args with category filtering applied.
	 *
	 * @example
	 * ```php
	 * $query_args = [
	 *     'post_type'      => 'post',
	 *     'posts_per_page' => 10,
	 * ];
	 *
	 * // Without explicit post ID (uses context detection)
	 * $query_args = ModuleUtils::add_category_query_args(
	 *     $query_args,
	 *     '1,2,current',
	 *     'post'
	 * );
	 *
	 * // With explicit post ID (required for Visual Builder REST API calls)
	 * $current_post_id = 123;
	 * $query_args = ModuleUtils::add_category_query_args(
	 *     $query_args,
	 *     '1,2,current',
	 *     'project',
	 *     $current_post_id
	 * );
	 *
	 * // For 'post' type, adds: $query_args['cat'] = '1,2,3' (where 3 is current category ID)
	 *
	 * // For custom post types, adds:
	 * // $query_args['tax_query'] = [
	 * //     [
	 * //         'taxonomy' => 'project_category',
	 * //         'field'    => 'id',
	 * //         'terms'    => [1, 2, 3],
	 * //         'operator' => 'IN',
	 * //     ],
	 * // ];
	 * ```
	 */
	public static function add_category_query_args( array $query_args, $categories, string $post_type, int $current_post_id = 0 ): array {
		// Normalize categories input.
		$categories_normalized = $categories;
		if ( is_string( $categories_normalized ) ) {
			$categories_normalized = trim( $categories_normalized );
			if ( empty( $categories_normalized ) || 'undefined' === $categories_normalized || 'null' === $categories_normalized ) {
				$categories_normalized = [];
			} else {
				// Convert comma-separated string to array.
				$categories_normalized = array_map( 'trim', explode( ',', $categories_normalized ) );
			}
		}

		// Ensure we have an array and filter out empty/null values.
		if ( ! is_array( $categories_normalized ) ) {
			$categories_normalized = [];
		} else {
			// Filter out empty strings, null values, and other falsy values except 0.
			$categories_normalized = array_filter( $categories_normalized );
		}

		// Check if "All Categories" is selected (either explicitly as 'all' or when categories is empty/null).
		$is_all_category_selected = empty( $categories_normalized ) || in_array( 'all', $categories_normalized, true );

		// Only apply category filtering if "All Categories" is not selected.
		if ( ! $is_all_category_selected ) {
			// For 'post' type, use simple 'cat' parameter.
			if ( 'post' === $post_type ) {
				// Filter out special values and convert 'current' to actual term ID(s).
				$filtered_categories = [];
				$queried_object      = get_queried_object();
				foreach ( $categories_normalized as $category ) {
					if ( 'current' === $category ) {
						// Prefer the queried category archive term over the current post's full term set (D4 / Woo #48324 / #51023).
						if ( $queried_object instanceof \WP_Term && 'category' === $queried_object->taxonomy ) {
							$filtered_categories[] = (int) $queried_object->term_id;
							continue;
						}

						if ( is_category() ) {
							$archive_term_id = (int) get_queried_object_id();
							if ( $archive_term_id > 0 ) {
								$filtered_categories[] = $archive_term_id;
								continue;
							}
						}

						// Singular / explicit post ID: expand to that post's categories.
						// Use provided ID for Visual Builder context, otherwise detect from context.
						$detected_post_id = $current_post_id ? $current_post_id : self::_get_current_post_id_for_category_filtering();
						if ( $detected_post_id > 0 ) {
							$post_terms = wp_get_object_terms( $detected_post_id, 'category' );
							if ( is_wp_error( $post_terms ) ) {
								continue;
							}
							$current_term_ids    = wp_list_pluck( $post_terms, 'term_id' );
							$filtered_categories = array_merge( $filtered_categories, $current_term_ids );
						}
					} else {
						$filtered_categories[] = (int) $category;
					}
				}
				$filtered_categories = array_filter( array_unique( $filtered_categories ) );

				// Validate that all category IDs exist. If any don't exist, skip category filtering.
				if ( ! empty( $filtered_categories ) ) {
					$valid_categories = [];
					foreach ( $filtered_categories as $category_id ) {
						if ( term_exists( (int) $category_id, 'category' ) ) {
							$valid_categories[] = $category_id;
						}
					}

					// Only apply category filtering if we have valid categories.
					if ( ! empty( $valid_categories ) ) {
						$query_args['cat'] = implode( ',', $valid_categories );
					}
				}
			} else {
				// For other post types, use tax_query with appropriate taxonomy.
				$taxonomy = self::get_taxonomy_for_post_type( $post_type );

				// Filter out special values and convert 'current' to actual term ID(s).
				$filtered_categories     = [];
				$queried_object          = get_queried_object();
				$current_tax_query_value = ! empty( $taxonomy ) ? get_query_var( $taxonomy ) : '';
				foreach ( $categories_normalized as $category ) {
					if ( 'current' === $category ) {
						// Prefer the queried taxonomy archive term over the current post's full term set (D4 / Woo #48324 / #51023).
						if ( $queried_object instanceof \WP_Term && $taxonomy === $queried_object->taxonomy ) {
							$filtered_categories[] = (int) $queried_object->term_id;
							continue;
						}

						// Query-var fallback for edge cases (parity with Woo #48324).
						if ( ! empty( $current_tax_query_value ) ) {
							$current_tax_term = is_numeric( $current_tax_query_value )
								? get_term_by( 'id', (int) $current_tax_query_value, $taxonomy )
								: get_term_by( 'slug', sanitize_title( $current_tax_query_value ), $taxonomy );

							if ( $current_tax_term instanceof \WP_Term ) {
								$filtered_categories[] = (int) $current_tax_term->term_id;
								continue;
							}
						}

						// Singular / explicit post ID: expand to that post's terms.
						// Use provided ID for Visual Builder context, otherwise detect from context.
						$detected_post_id = $current_post_id ? $current_post_id : self::_get_current_post_id_for_category_filtering();
						if ( $detected_post_id > 0 ) {
							$post_terms = wp_get_object_terms( $detected_post_id, $taxonomy );
							if ( is_wp_error( $post_terms ) ) {
								continue;
							}
							$current_term_ids    = wp_list_pluck( $post_terms, 'term_id' );
							$filtered_categories = array_merge( $filtered_categories, $current_term_ids );
						}
						// phpcs:ignore Universal.ControlStructures.DisallowLonelyIf.Found -- Intentional else-if pattern for readability.
					} else {
						// Check if it's a numeric ID.
						if ( is_numeric( $category ) ) {
							$filtered_categories[] = (int) $category;
						} else {
							// Otherwise, treat as slug and return as string.
							$filtered_categories[] = $category;
						}
					}
				}
				$filtered_categories = array_filter( array_unique( $filtered_categories ) );

				if ( ! empty( $filtered_categories ) && ! empty( $taxonomy ) && taxonomy_exists( $taxonomy ) ) {
					$field               = 'id';
					$filtered_categories = array_filter(
						array_map(
							function ( $category ) use ( $taxonomy ) {
								// If it's already numeric, cast to int.
								if ( is_numeric( $category ) ) {
									return (int) $category;
								}

								// If it's a string slug, convert to ID.
								if ( is_string( $category ) ) {
									$term = get_term_by( 'slug', $category, $taxonomy );
									return $term ? $term->term_id : 0;
								}

								return 0;
							},
							$filtered_categories
						)
					);

					// Validate that all term IDs exist.
					$valid_categories = [];
					foreach ( $filtered_categories as $term_id ) {
						if ( term_exists( (int) $term_id, $taxonomy ) ) {
							$valid_categories[] = $term_id;
						}
					}

					// Set up the tax_query for non-post types only if we have valid terms.
					if ( ! empty( $valid_categories ) ) {
						$query_args['tax_query'] = [
							[
								'taxonomy' => $taxonomy,
								'field'    => $field,
								'terms'    => $valid_categories,
								'operator' => 'IN',
							],
						];
					}
				}
			}
		}

		return $query_args;
	}

	/**
	 * Handle archive context for category queries.
	 *
	 * When on a category/taxonomy archive page (e.g., Theme Builder body template on /category/news/),
	 * this method ensures the query uses the current archive's category, overriding module settings.
	 *
	 * This method also filters out the 'all' token from categories, as D4 does this in frontend
	 * processing via filter_meta_categories(). In D5, raw data reaches PHP, so we handle it here.
	 *
	 * Note: Category validation should be done via add_category_query_args() before calling this method.
	 * This function focuses solely on archive context handling and 'all' token filtering.
	 *
	 * This fixes issue #44554 where Post Slider modules didn't show posts correctly on archive pages.
	 *
	 * @since ??
	 *
	 * @param array $query_args The WP_Query arguments to modify.
	 *
	 * @return array Modified query arguments with 'all' filtered and archive context applied.
	 *
	 * @example
	 * ```php
	 * // In Post Slider module
	 * $query_args = ModuleUtils::handle_archive_context_for_query( $query_args );
	 * $query      = new WP_Query( $query_args );
	 * ```
	 */
	public static function handle_archive_context_for_query( array $query_args ): array {
		// Normalize and filter categories.
		if ( isset( $query_args['cat'] ) ) {
			$categories = is_array( $query_args['cat'] ) ? $query_args['cat'] : [ $query_args['cat'] ];

			// Filter out 'all' token and empty values (D4 does this in frontend processing).
			// In D5, raw data reaches PHP, so we handle it here.
			$categories = array_filter(
				$categories,
				function ( $cat ) {
					return 'all' !== $cat && '' !== $cat;
				}
			);

			// Convert to comma-separated string (WP_Query expects string/int, not array).
			if ( ! empty( $categories ) ) {
				$query_args['cat'] = implode( ',', $categories );
			} else {
				$query_args['cat'] = '';
			}
		}

		// Apply archive context when no categories/taxonomies are set.
		// This respects user-selected categories while applying archive context when appropriate.
		if ( empty( $query_args['cat'] ) && empty( $query_args['tax_query'] ) ) {
			$queried_object = get_queried_object();

			// Use current archive's term if on a category/taxonomy archive page.
			if ( $queried_object && isset( $queried_object->taxonomy, $queried_object->term_id ) ) {
				if ( 'category' === $queried_object->taxonomy ) {
					$query_args['cat'] = $queried_object->term_id;
				} else {
					$query_args['tax_query'] = [
						[
							'taxonomy' => $queried_object->taxonomy,
							'field'    => 'term_id',
							'terms'    => $queried_object->term_id,
						],
					];
				}
			}
		}

		return $query_args;
	}

	/**
	 * Get the primary category taxonomy for a given post type.
	 *
	 * Returns the appropriate taxonomy name for category-like terms for the given post type.
	 * Uses known mappings for common post types and falls back to finding the first
	 * hierarchical taxonomy for custom post types.
	 *
	 * @since ??
	 *
	 * @param string $post_type The post type to get the taxonomy for.
	 *
	 * @return string The taxonomy name, or empty string if none found.
	 *
	 * @example
	 * ```php
	 * $taxonomy = ModuleUtils::get_taxonomy_for_post_type( 'post' );     // Returns: 'category'
	 * $taxonomy = ModuleUtils::get_taxonomy_for_post_type( 'project' );  // Returns: 'project_category'
	 * $taxonomy = ModuleUtils::get_taxonomy_for_post_type( 'product' );  // Returns: 'product_cat'
	 * ```
	 */
	public static function get_taxonomy_for_post_type( string $post_type ): string {
		$taxonomy = '';

		switch ( $post_type ) {
			case 'post':
				$taxonomy = 'category';
				break;
			case 'project':
				$taxonomy = 'project_category';
				break;
			case 'product':
				$taxonomy = 'product_cat';
				break;
			default:
				// Taxonomies to exclude (same list as CategoriesRESTController).
				$excluded_taxonomies = [
					'post_tag',
					'project_tag',
					'product_tag',
					'post_format',
					'nav_menu',
					'link_category',
					'post_status',
					'product_type',
					'product_visibility',
					'product_brand',
					'product_shipping_class',
				];

				// Strategy 1: Try common "category" naming patterns first.
				$common_patterns = [
					$post_type . '_category',
					$post_type . '_cat',
					$post_type . 's_category',
					substr( $post_type, 0, -1 ) . '_category', // if post_type ends with 's', try singular.
				];

				foreach ( $common_patterns as $pattern ) {
					if ( taxonomy_exists( $pattern ) && ! in_array( $pattern, $excluded_taxonomies, true ) ) {
						$taxonomy = $pattern;
						break;
					}
				}

				// For custom post types, get all associated taxonomies.
				$taxonomies = get_object_taxonomies( $post_type, 'objects' );

				// Strategy 2: If no common "category" pattern found, look for hierarchical taxonomies first.
				if ( empty( $taxonomy ) ) {
					foreach ( $taxonomies as $tax_slug => $tax_obj ) {
						if ( $tax_obj->hierarchical && ! in_array( $tax_slug, $excluded_taxonomies, true ) ) {
							$taxonomy = $tax_slug;
							break;
						}
					}
				}

				// Strategy 3: If no hierarchical taxonomy found, use the first available taxonomy.
				// This enables support for ACF custom taxonomies which are often non-hierarchical.
				if ( empty( $taxonomy ) ) {
					foreach ( $taxonomies as $tax_slug => $tax_obj ) {
						if ( ! in_array( $tax_slug, $excluded_taxonomies, true ) ) {
							$taxonomy = $tax_slug;
							break;
						}
					}
				}

				break;
		}

		return $taxonomy;
	}

	/**
	 * Format date with flexible formatting options.
	 *
	 * Provides consistent date formatting across all loop handlers and modules.
	 * Supports WordPress default format, custom format from whitelist, or direct format specification.
	 *
	 * @since ??
	 *
	 * @param mixed  $date     Date object (DateTime) or string to format.
	 * @param array  $settings Optional. Date formatting settings. Default [].
	 *                         - 'date_format': 'default'|'custom'|specific format. Default 'default'.
	 *                         - 'custom_date_format': Custom PHP date format when date_format is 'custom'.
	 * @param string $default_format Optional. Default format to use when no settings provided. Default 'default'.
	 *
	 * @return string Formatted date string or empty string on failure.
	 *
	 * @example
	 * ```php
	 * // Use WordPress default date format
	 * $formatted = ModuleUtils::format_date($date);
	 *
	 * // Use custom date format
	 * $formatted = ModuleUtils::format_date($date, [
	 *     'date_format' => 'custom',
	 *     'custom_date_format' => 'Y-m-d H:i:s'
	 * ]);
	 *
	 * // Use specific format directly
	 * $formatted = ModuleUtils::format_date($date, [
	 *     'date_format' => 'M j, Y'
	 * ]);
	 * ```
	 */
	public static function format_date( $date, $settings = [], $default_format = 'default' ): string {
		// Use explicit checks instead of ! $date to avoid falsy behavior with string '0'.
		// In PHP, ! '0' evaluates to true, which would incorrectly reject valid timestamp '0' (Unix epoch).
		if ( null === $date || false === $date || '' === $date ) {
			return '';
		}

		$format        = $settings['date_format'] ?? $default_format;
		$custom_format = $settings['custom_date_format'] ?? '';

		if ( 'custom' === $format ) {
			$format = '' === trim( (string) $custom_format )
				? strval( get_option( 'date_format' ) )
				: str_replace( '\\\\', '\\', $custom_format );
		} elseif ( 'default' === $format ) {
			$format = strval( get_option( 'date_format' ) );
		}

		$formatted_date = '';

		if ( is_object( $date ) && method_exists( $date, 'date' ) ) {
			// DateTime object with date() method (like WooCommerce date objects).
			$formatted_date = $date->date( $format );
		} elseif ( is_object( $date ) && method_exists( $date, 'format' ) ) {
			// Standard DateTime object.
			$formatted_date = $date->format( $format );
		} elseif ( is_string( $date ) || is_numeric( $date ) ) {
			// Handle numeric string timestamps (from WordPress post meta) separately from date strings.
			if ( is_numeric( $date ) && ctype_digit( (string) $date ) ) {
				// Numeric string with only digits - treat as Unix timestamp directly.
				// ctype_digit() ensures we only handle pure digit strings like "1234567890",
				// avoiding decimals ("123.45"), negatives ("-123"), or scientific notation ("1e10").
				// Cast to string for PHP 8.1+ compatibility as ctype_digit() expects string parameter.
				$timestamp      = (int) $date;
				$formatted_date = wp_date( $format, $timestamp );
			} else {
				// String date - use strtotime() for parsing.
				$timestamp = strtotime( $date );

				// Only format if strtotime() succeeded.
				if ( false !== $timestamp ) {
					// Use wp_date() instead of date_i18n() for better timezone handling and WordPress 5.3+ compatibility.
					$formatted_date = wp_date( $format, $timestamp );
				}
			}
		}

		return esc_html( $formatted_date );
	}

	/**
	 * Get formatted date archive title from WordPress query vars.
	 *
	 * Formats date archive titles using query vars (year, monthnum, day) to ensure
	 * correct date is displayed, especially when accessed via Calendar Widget.
	 * Matches WordPress core format with proper prefixes ("Year:", "Month:", "Day:").
	 *
	 * @since ??
	 *
	 * @return string Formatted date archive title, or empty string if not on date archive.
	 *
	 * @example
	 * ```php
	 * if ( is_date() ) {
	 *     $title = ModuleUtils::get_date_archive_title();
	 *     // Returns: "Year: 2023", "Month: January 2023", or "Day: January 1, 2023"
	 * }
	 * ```
	 */
	public static function get_date_archive_title(): string {
		if ( ! is_date() ) {
			return '';
		}

		$year     = absint( get_query_var( 'year' ) );
		$monthnum = max( 1, absint( get_query_var( 'monthnum' ) ) );
		$day      = max( 1, absint( get_query_var( 'day' ) ) );

		if ( is_year() ) {
			return sprintf(
				/* translators: Yearly archive title. %s: Year. */
				__( 'Year: %s', 'et_builder_5' ),
				$year
			);
		} elseif ( is_month() ) {
			if ( ! checkdate( $monthnum, 1, $year ) ) {
				return '';
			}

			$monthnum       = str_pad( (string) $monthnum, 2, '0', STR_PAD_LEFT );
			$archive_date   = sprintf( '%s-%s-01', $year, $monthnum );
			$date_object    = new \DateTimeImmutable( $archive_date, wp_timezone() );
			$formatted_date = wp_date( 'F Y', $date_object->getTimestamp() );
			return sprintf(
				/* translators: Monthly archive title. %s: Month and year. */
				__( 'Month: %s', 'et_builder_5' ),
				$formatted_date
			);
		} elseif ( is_day() ) {
			if ( ! checkdate( $monthnum, $day, $year ) ) {
				return '';
			}

			$monthnum       = str_pad( (string) $monthnum, 2, '0', STR_PAD_LEFT );
			$day            = str_pad( (string) $day, 2, '0', STR_PAD_LEFT );
			$archive_date   = sprintf( '%s-%s-%s', $year, $monthnum, $day );
			$date_object    = new \DateTimeImmutable( $archive_date, wp_timezone() );
			$formatted_date = wp_date( 'F j, Y', $date_object->getTimestamp() );
			return sprintf(
				/* translators: Daily archive title. %s: Date. */
				__( 'Day: %s', 'et_builder_5' ),
				$formatted_date
			);
		}

		return '';
	}

	/**
	 * Get the current post ID for category filtering, handling Theme Builder context.
	 *
	 * In Theme Builder context, the global $post is the template post, not the displayed post.
	 * This method gets the actual post being displayed for proper category filtering.
	 *
	 * Only returns a post ID on singular views. On taxonomy/category archives, returning the
	 * main-query post would expand "Current Category" to that post's full term set (#51023).
	 *
	 * @since ??
	 *
	 * @return int Current post ID, or 0 if not found / not singular.
	 */
	private static function _get_current_post_id_for_category_filtering(): int {
		// Check if we're in Theme Builder context.
		$is_theme_builder = class_exists( '\ET_Theme_Builder_Layout' ) && \ET_Theme_Builder_Layout::is_theme_builder_layout();

		if ( $is_theme_builder && true === is_singular() ) {
			// In Theme Builder on singular pages, get the main post ID (the actual post being displayed).
			$main_post_id = class_exists( '\ET_Post_Stack' ) ? \ET_Post_Stack::get_main_post_id() : 0;
			if ( $main_post_id > 0 ) {
				return $main_post_id;
			}
		}

		// Standard context - use global $post on singular pages.
		if ( is_singular() ) {
			global $post;
			if ( $post && $post->ID > 0 ) {
				return $post->ID;
			}
		}

		// Fallback to get_the_ID() only on singular pages.
		// On archives, get_the_ID() returns loop post IDs which would mis-resolve Current Category.
		$post_id = get_the_ID();
		return $post_id > 0 && is_singular() ? $post_id : 0;
	}

	/**
	 * Get unique ID for a module.
	 *
	 * This function generates a globally unique identifier for a module instance.
	 *
	 * The function uses a deterministic hash derived from render context (layout type,
	 * module ID, order index, and optionally store instance). This ensures uniqueness
	 * across Theme Builder areas and regular page content.
	 *
	 * @since ??
	 *
	 * @param array|string $parsed_block_or_id Optional. Either a parsed block array containing
	 *                                         layout information, or a module ID string. If a string
	 *                                         is provided, `$store_instance` should also be provided
	 *                                         for optimal results. Default `null`.
	 * @param int|null     $store_instance     Optional. The store instance ID. Recommended when
	 *                                         `$parsed_block_or_id` is a string. When provided,
	 *                                         ensures hash uniqueness across store instances.
	 *                                         Default `null`.
	 *
	 * @return string The unique MD5 hash ID generated from the provided parameters.
	 *
	 * @example
	 * ```php
	 * // Using parsed block array (preferred when available).
	 * $unique_id = ModuleUtils::get_unique_module_id( $parsed_block );
	 *
	 * // Using module ID and store instance.
	 * $unique_id = ModuleUtils::get_unique_module_id( $module_id, $store_instance );
	 * ```
	 */
	public static function get_unique_module_id( $parsed_block_or_id = null, $store_instance = null ): string {
		$layout_type = BlockParserStore::get_layout_type();
		$id          = is_string( $parsed_block_or_id ) ? $parsed_block_or_id : '';
		$order_index = 0;

		// Attempt to derive order index from the module ID as a last resort.
		if ( $id && preg_match( '/-(\d+)$/', $id, $matches ) ) {
			$order_index = (int) $matches[1];
		}

		// Determine if we have a parsed block array or need to fetch from store.
		if ( is_array( $parsed_block_or_id ) ) {
			// Use parsed block data directly.
			$layout_type = $parsed_block_or_id['layout_type'] ?? '';
			$id          = $parsed_block_or_id['id'] ?? '';
			$order_index = $parsed_block_or_id['orderIndex'] ?? 0;
		} elseif ( is_string( $parsed_block_or_id ) && null !== $store_instance ) {
			// Fetch block from store using ID and store instance.
			$block = BlockParserStore::get( $parsed_block_or_id, $store_instance );

			if ( $block instanceof BlockParserBlock ) {
				// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block
				$layout_type = $block->layout_type ?? '';
				$id          = $block->id ?? '';
				$order_index = $block->orderIndex ?? 0;
				// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			}
		}

		// Generate deterministic hash from render context.
		$hash_input = $layout_type . '--' . $id . '--' . $order_index;
		$unique_id  = md5( $hash_input );

		return $unique_id;
	}

	/**
	 * Removes the 'icon' property from button attributes across all breakpoints and attribute states.
	 *
	 * This function iterates through all breakpoints and attribute states in the provided button
	 * attributes and creates a new attributes array with the 'icon' property omitted from each
	 * attribute state array.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /api/js/divi-module-utils/functions/removeButtonIconAttrValue removeButtonIconAttrValue}
	 * located in `@divi/module-utils`.
	 *
	 * @since ??
	 *
	 * @param array $button_attrs The button attributes array containing breakpoint and attribute state data.
	 *                           Structure: [breakpoint][state][icon|other_properties].
	 *
	 * @return array A new button attributes array with the 'icon' property removed from all attribute
	 *               states across all breakpoints.
	 *
	 * @example
	 * ```php
	 * $button_attrs = [
	 *   'desktop' => [
	 *     'value' => [
	 *       'icon' => ['settings' => [...]],
	 *       'enable' => 'on',
	 *     ],
	 *   ],
	 * ];
	 *
	 * $removed_icon_attrs = ModuleUtils::remove_button_icon_attr_value( $button_attrs );
	 * // Result: ['desktop' => ['value' => ['enable' => 'on']]]
	 * ```
	 */
	public static function remove_button_icon_attr_value( array $button_attrs ): array {
		$removed_icon_attrs = [];

		foreach ( $button_attrs as $breakpoint_name => $breakpoint_data ) {
			if ( ! is_array( $breakpoint_data ) ) {
				continue;
			}

			$removed_icon_attrs[ $breakpoint_name ] = [];

			foreach ( $breakpoint_data as $attr_state_name => $attr_state_data ) {
				if ( ! is_array( $attr_state_data ) ) {
					$removed_icon_attrs[ $breakpoint_name ][ $attr_state_name ] = $attr_state_data;
					continue;
				}

				// Remove 'icon' key from attribute state data.
				$removed_state_data = $attr_state_data;
				unset( $removed_state_data['icon'] );

				$removed_icon_attrs[ $breakpoint_name ][ $attr_state_name ] = $removed_state_data;
			}
		}

		return $removed_icon_attrs;
	}
}
