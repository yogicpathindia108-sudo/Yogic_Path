<?php
/**
 * Module: LayoutStyle class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Layout;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Packages\Module\Layout\Components\Style\Utils\Utils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use ET\Builder\Packages\StyleLibrary\Utils\Utils as StyleUtils;
use ET\Builder\VisualBuilder\Saving\SavingUtility;

/**
 * LayoutStyle class.
 *
 * This class has functionality for handling styles and layout for the layout component.
 *
 * @since ??
 */
class LayoutStyle {

	/**
	 * Get layout style component.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/LayoutStyle LayoutStyle} in
	 * `@divi/module` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string        $selector                 The CSS selector.
	 *     @type array         $selectors                Optional. An array of selectors for each breakpoint and state. Default `[]`.
	 *     @type callable      $selectorFunction         Optional. The function to be called to generate CSS selector. Default `null`.
	 *     @type array         $propertySelectors        Optional. The property selectors that you want to unpack. Default `[]`.
	 *     @type array         $attr                     An array of module attribute data.
	 *     @type array         $defaultPrintedStyleAttr  Optional. An array of default printed style attribute data. Default `[]`.
	 *     @type array|bool    $important               Optional. Whether to apply "!important" flag to the style declarations.
	 *                                                   Default `false`.
	 *     @type bool          $asStyle                  Optional. Whether to wrap the style declaration with style tag or not.
	 *                                                   Default `true`
	 *     @type string|null   $orderClass               Optional. The selector class name.
	 *     @type bool          $isInsideStickyModule     Optional. Whether the module is inside a sticky module or not. Default `false`.
	 *     @type string|null   $stickyParentOrderClass   Optional. The sticky parent order class name. Default `null`.
	 *     @type string        $returnType               Optional. This is the type of value that the function will return.
	 *                                                   Can be either `string` or `array`. Default `array`.
	 *     @type string        $atRules                  Optional. CSS at-rules to wrap the style declarations in. Default `''`.
	 *     @type array          $render                   Optional. With boolean `display`; when true, may emit `display` when printed baseline differs from `attrValue.display`. Default `display` false.
	 * }
	 *
	 * @return string|array The layout style component.
	 */
	public static function style( array $args ) {
		$args = wp_parse_args(
			$args,
			[
				'selectors'         => [],
				'propertySelectors' => [],
				'selectorFunction'  => null,
				'important'         => false,
				'asStyle'           => true,
				'orderClass'        => null,
				'returnType'        => 'array',
				'atRules'           => '',
				'render'            => [
					'display' => false,
				],
			]
		);

		$selector           = $args['selector'];
		$selectors          = $args['selectors'];
		$selector_function  = $args['selectorFunction'];
		$property_selectors = $args['propertySelectors'];
		$attr               = $args['attr'];
		$important          = $args['important'];
		$as_style           = $args['asStyle'];
		$order_class        = $args['orderClass'];

		$default_printed         = $args['defaultPrintedStyleAttr'] ?? [];
		$printed_desktop_value   = $default_printed['desktop']['value'] ?? [];
		$layout_display_baseline = $printed_desktop_value['display'] ?? 'flex';

		$attr_normalized = self::normalize_attr( $attr, $args['defaultPrintedStyleAttr'] ?? [] );

		// Resolve variables before style_statements so string returnType inline child selectors
		// and array returnType child selector rules both receive var(--gvid-xxx) columnGap values.
		$attr_normalized = StyleUtils::resolve_dynamic_variables_recursive( $attr_normalized );

		$is_inside_sticky_module   = $args['isInsideStickyModule'] ?? false;
		$sticky_parent_order_class = $args['stickyParentOrderClass'] ?? null;

		// Bail, if noting is there to process.
		if ( empty( $attr_normalized ) ) {
			return 'array' === $args['returnType'] ? [] : '';
		}

		// Generate main layout styles using a single Utils::style_statements() call.
		// The declarationFunction handles both return types with conditional logic.
		$children = Utils::style_statements(
			[
				'selectors'               => ! empty( $selectors ) ? $selectors : [ 'desktop' => [ 'value' => $selector ] ],
				'selectorFunction'        => $selector_function,
				'propertySelectors'       => $property_selectors,
				'attr'                    => $attr_normalized,
				'defaultPrintedStyleAttr' => $args['defaultPrintedStyleAttr'] ?? [],
				'important'               => $important,
				'declarationFunction'     => function ( $params ) use ( $selector, $args, $layout_display_baseline ) {
					$render_arg             = $args['render'] ?? [ 'display' => false ];
					$render_display_enabled = true === ( $render_arg['display'] ?? false );
					$params_attr_value      = $params['attrValue'] ?? [];
					$attr_value_display     = $params_attr_value['display'] ?? null;
					$params['render']       = [
						'display' => $render_display_enabled && $layout_display_baseline !== $attr_value_display,
					];

					// Get the main layout declarations.
					$layout_declarations = Declarations::layout_style_declaration( $params );

					// Do NOT include embedded CSS rules for array return type.
					if ( 'array' === $args['returnType'] ) {
						return $layout_declarations;
					}

					// Add child selector rule inline for string return type (safe from CSS corruption).
					$params_attr_value      = $params['attrValue'] ?? [];
					$params_attr_full       = $params['attr'] ?? [];
					$display                = $params_attr_full['desktop']['value']['display'] ?? $params_attr_value['display'] ?? '';
					$column_gap             = $params['attrValue']['columnGap'] ?? '';
					$collapse_empty_columns = $params['attrValue']['collapseEmptyColumns'] ?? '';
					$grid_auto_flow         = $params['attrValue']['gridAutoFlow'] ?? 'row';
					$grid_column_widths     = $params['attrValue']['gridColumnWidths'] ?? 'equal';

					if ( 'block' !== $display && $column_gap ) {
						$declaration           = "--horizontal-gap-parent: {$column_gap};";
						$sanitized_declaration = SavingUtility::sanitize_css_properties( $declaration );
						$layout_declarations  .= "} {$selector} > [class*=\"et_flex_column\"] { {$sanitized_declaration}";
					}

					// Hide empty columns when collapse empty columns is enabled.
					// Only applies to grid layouts with row flow and equal column widths.
					if ( 'grid' === $display && 'on' === $collapse_empty_columns && 'row' === $grid_auto_flow && 'equal' === $grid_column_widths ) {
						$declaration           = 'display: none;';
						$sanitized_declaration = SavingUtility::sanitize_css_properties( $declaration );
						$layout_declarations  .= "} {$selector} > .et_pb_column_empty { {$sanitized_declaration}";
					}

					return $layout_declarations;
				},
				'orderClass'              => $order_class,
				'isInsideStickyModule'    => $is_inside_sticky_module,
				'stickyParentOrderClass'  => $sticky_parent_order_class,
				'returnType'              => $args['returnType'],
				'atRules'                 => $args['atRules'],
			]
		);

		// For array return type, we need to generate child selector rules separately to prevent
		// Style::add() from merging them with parent declarations (CSS corruption).
		// For string return type, we can safely embed them inline within the declarationFunction.
		$child_selector_children = [];

		if ( 'array' === $args['returnType'] ) {
			// Get breakpoint settings once before the loop for performance.
			$style_breakpoint_settings = Breakpoint::get_style_breakpoint_settings();

			// Generate child selector rules separately for array return type.
			// Separate style items with different selectors prevent Style::add() merging.
			$processed_rules = []; // Prevent duplicate rules.

			foreach ( $attr_normalized as $breakpoint => $states ) {
				foreach ( $states as $state => $attr_value ) {
					$display                = $attr_normalized['desktop']['value']['display'] ?? $attr_value['display'] ?? '';
					$column_gap             = $attr_value['columnGap'] ?? '';
					$collapse_empty_columns = $attr_value['collapseEmptyColumns'] ?? '';
					$grid_auto_flow         = $attr_value['gridAutoFlow'] ?? 'row';
					$grid_column_widths     = $attr_value['gridColumnWidths'] ?? 'equal';

					// Only generate for non-block display with column gap.
					if ( 'block' !== $display && $column_gap ) {
						// Create unique key to prevent duplicate CSS rules.
						$rule_key = "{$breakpoint}_{$column_gap}";

						if ( ! isset( $processed_rules[ $rule_key ] ) ) {
							$processed_rules[ $rule_key ] = true;
							$child_selector_children[]    = [
								'selector'    => "{$selector} > [class*=\"et_flex_column\"]",
								'declaration' => SavingUtility::sanitize_css_properties( "--horizontal-gap-parent: {$column_gap};" ),
								'atRules'     => Utils::get_at_rules( $breakpoint, $style_breakpoint_settings ),
							];
						}
					}

					// Hide empty columns when collapse empty columns is enabled.
					// Only applies to grid layouts with row flow and equal column widths.
					if ( 'grid' === $display && 'on' === $collapse_empty_columns && 'row' === $grid_auto_flow && 'equal' === $grid_column_widths ) {
						// Create unique key to prevent duplicate CSS rules.
						$rule_key = "{$breakpoint}_collapse_empty";

						if ( ! isset( $processed_rules[ $rule_key ] ) ) {
							$processed_rules[ $rule_key ] = true;
							$child_selector_children[]    = [
								'selector'    => "{$selector} > .et_pb_column_empty",
								'declaration' => SavingUtility::sanitize_css_properties( 'display: none;' ),
								'atRules'     => Utils::get_at_rules( $breakpoint, $style_breakpoint_settings ),
							];
						}
					}
				}
			}
		}

		// Generate grid offset rules CSS.
		$grid_offset_children = self::_generate_grid_offset_rules_css(
			[
				'selector'   => $selector,
				'attr'       => $attr_normalized,
				'important'  => $important,
				'returnType' => $args['returnType'],
			]
		);

		// Combine layout styles + child selector rules + grid offset rules.
		$all_children = $children;

		// Merge child selector rules (only populated for array return type).
		if ( ! empty( $child_selector_children ) ) {
			$all_children = array_merge( $all_children, $child_selector_children );
		}

		if ( ! empty( $grid_offset_children ) ) {
			$all_children = 'array' === $args['returnType']
				? array_merge( $all_children, $grid_offset_children )
				: $all_children . $grid_offset_children;
		}

		return Utils::style_wrapper(
			[
				'attr'     => $attr_normalized,
				'asStyle'  => $as_style,
				'children' => $all_children,
			]
		);
	}

	/**
	 * Normalize the layout attributes.
	 *
	 * Some attributes are not available in all breakpoints and states. This function
	 * will normalize the attributes by filling the missing attributes with the
	 * inherited values.
	 *
	 * Layout `display` is not a responsive field; use only
	 * `$default_printed_style_attr['desktop']['value']['display']` when present so
	 * saved attrs may omit `display` (e.g. grid longhands only) without re-emitting
	 * other printed layout longhands as dynamic CSS.
	 *
	 * @since ??
	 *
	 * @param array $attr The array of attributes to be normalized.
	 * @param array $default_printed_style_attr Optional. Default printed layout; only `display` on `desktop.value` is applied before `$attr`.
	 *
	 * @return array The normalized array of attributes.
	 */
	public static function normalize_attr( array $attr, array $default_printed_style_attr = [] ): array {
		if ( empty( $attr ) && empty( $default_printed_style_attr ) ) {
			return [];
		}

		$default_attr = [
			'desktop' => [
				'value' => [
					'display' => 'flex',
				],
			],
		];

		// Layout `display` is not responsive; printed defaults only define it on `desktop.value`.
		$printed_display      = $default_printed_style_attr['desktop']['value']['display'] ?? null;
		$display_from_printed = [];

		if ( null !== $printed_display && '' !== $printed_display ) {
			$display_from_printed = [
				'desktop' => [
					'value' => [
						'display' => $printed_display,
					],
				],
			];
		}

		$attr_with_default = array_replace_recursive( $default_attr, $display_from_printed, $attr );

		/*
		 * Wide-band breakpoints (e.g. tabletWide) only cover their own viewport band,
		 * so seed enabled breakpoint rows after explicit predecessor data,
		 * allowing use_attr_value to emit CSS for adjacent media query bands.
		 */
		$enabled_breakpoints = array_values( Breakpoint::get_enabled_breakpoint_names() );
		$base_breakpoint     = Breakpoint::get_base_breakpoint_name();
		$breakpoint_count    = count( $enabled_breakpoints );

		for ( $i = 1; $i < $breakpoint_count; $i++ ) {
			$breakpoint      = $enabled_breakpoints[ $i ];
			$prev_breakpoint = $enabled_breakpoints[ $i - 1 ];

			if ( $prev_breakpoint === $base_breakpoint || empty( $attr[ $prev_breakpoint ] ) || ! is_array( $attr[ $prev_breakpoint ] ) ) {
				continue;
			}

			foreach ( array_keys( $attr[ $prev_breakpoint ] ) as $state ) {
				if ( ! isset( $attr_with_default[ $breakpoint ][ $state ] ) ) {
					$attr_with_default[ $breakpoint ][ $state ] = [];
				}
			}
		}

		$attr_normalized = [];

		foreach ( $attr_with_default as $breakpoint => $states ) {
			foreach ( $states as $state => $values ) {
				if ( 'desktop' === $breakpoint && 'value' === $state ) {
					$attr_normalized[ $breakpoint ][ $state ] = $values;
				} else {
					$inherit = ModuleUtils::use_attr_value(
						[
							'attr'       => $attr_with_default,
							'breakpoint' => $breakpoint,
							'state'      => $state,
							'mode'       => 'getAndInheritAll',
						]
					);

					$attr_normalized[ $breakpoint ][ $state ] = array_replace_recursive(
						$inherit,
						$values
					);
				}
			}
		}

		return $attr_normalized;
	}

	/**
	 * Generate CSS for grid offset rules.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $selector   The base CSS selector.
	 *     @type array  $attr       The normalized layout attributes.
	 *     @type bool   $important  Whether to add !important to declarations.
	 *     @type string $returnType The return type ('array' or 'string').
	 * }
	 *
	 * @return array|string The grid offset rules CSS.
	 */
	private static function _generate_grid_offset_rules_css( array $args ) {
		$selector    = $args['selector'];
		$attr        = $args['attr'];
		$important   = $args['important'];
		$return_type = $args['returnType'];

		// Use the same style system as main layout styles for proper media query handling.
		return Utils::style_statements(
			[
				'selectors'           => [ 'desktop' => [ 'value' => $selector ] ],
				'attr'                => $attr,
				'important'           => $important,
				'declarationFunction' => function ( $params ) use ( $selector ) {
					$attr_value        = $params['attrValue'];
					$breakpoint        = $params['breakpoint'];
					$state             = $params['state'];
					$important         = $params['important'];
					$grid_offset_rules = $attr_value['gridOffsetRules'] ?? null;
					$display           = $attr_value['display'] ?? '';
					$css_rules         = [];

					// Only process grid offset rules for grid display.
					if ( 'grid' !== $display || empty( $grid_offset_rules ) ) {
						// Return empty string if no grid offset rules.
						return '';
					}

					// Grid offset rules are stored in format { rules: [] }.
					$rules = $grid_offset_rules['rules'] ?? $grid_offset_rules;

					if ( ! is_array( $rules ) || empty( $rules ) ) {
						// Return empty string if no valid grid offset rules.
						return '';
					}

					foreach ( $rules as $rule ) {
						if ( ! is_array( $rule ) ) {
							continue;
						}

						$target_offset = $rule['targetOffset'] ?? '';
						$offset_values = is_array( $rule['offsetValues'] ?? null ) ? $rule['offsetValues'] : [];
						$declarations  = [];

						foreach ( $offset_values as $offset_rule_key => $offset_rule_value ) {
							if ( '' === $offset_rule_value || null === $offset_rule_value ) {
								continue;
							}

							$css_property   = self::_get_css_property_for_offset_rule( (string) $offset_rule_key );
							$css_value      = self::_get_css_value_for_offset_rule( (string) $offset_rule_key, (string) $offset_rule_value );
							$declarations[] = $important ? "{$css_property}: {$css_value} !important;" : "{$css_property}: {$css_value};";
						}

						if ( empty( $target_offset ) || empty( $declarations ) ) {
							continue;
						}

						// Handle different target offset types.
						if ( 'first-child' === $target_offset ) {
							$child_selector = '> *:first-of-type';
						} elseif ( 'last-child' === $target_offset ) {
							$child_selector = '> *:last-of-type';
						} else {
							// Get the actual nth-child value for numbered selectors or custom patterns.
							$nth_child_value           = 'custom' === $target_offset
								? ( $rule['customTargetOffset'] ?? '1' )
								: $target_offset;
							$sanitized_nth_child_value = esc_attr( $nth_child_value );
							$child_selector            = "> *:nth-of-type({$sanitized_nth_child_value})";
						}

						$sanitized_declarations       = array_map(
							function ( string $declaration ): string {
								return SavingUtility::sanitize_css_properties( $declaration );
							},
							$declarations
						);
						$sanitized_declaration_string = implode( ' ', $sanitized_declarations );

						// Create individual CSS rule.
						$css_rules[] = "} {$selector} {$child_selector} { {$sanitized_declaration_string}";
					}

					// Return the CSS rules as a single string.
					return implode( ' ', $css_rules );
				},
				'returnType'          => $return_type,
			]
		);
	}

	/**
	 * Get the CSS property for a grid offset rule.
	 *
	 * @since ??
	 *
	 * @param string $offset_rule The offset rule type.
	 * @return string The CSS property.
	 */
	private static function _get_css_property_for_offset_rule( string $offset_rule ): string {
		switch ( $offset_rule ) {
			case 'columnSpan':
				return 'grid-column-end';
			case 'columnStart':
				return 'grid-column-start';
			case 'columnEnd':
				return 'grid-column-end';
			case 'rowSpan':
				return 'grid-row-end';
			case 'rowStart':
				return 'grid-row-start';
			case 'rowEnd':
				return 'grid-row-end';
			case 'gridTemplateColumns':
				return 'grid-template-columns';
			default:
				return 'grid-column';
		}
	}

	/**
	 * Get the CSS value for a grid offset rule.
	 *
	 * @since ??
	 *
	 * @param string $offset_rule The offset rule type.
	 * @param string $offset_value The offset value.
	 * @return string The CSS value.
	 */
	private static function _get_css_value_for_offset_rule( string $offset_rule, string $offset_value ): string {
		$sanitized_offset_value = esc_attr( $offset_value );

		switch ( $offset_rule ) {
			case 'columnSpan':
			case 'rowSpan':
				return "span {$sanitized_offset_value}";
			case 'columnStart':
			case 'columnEnd':
			case 'rowStart':
			case 'rowEnd':
				return $sanitized_offset_value;
			case 'gridTemplateColumns':
				return $sanitized_offset_value;
			default:
				return $sanitized_offset_value;
		}
	}
}
