<?php
/**
 * Module: CssStyleUtils class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Css;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElementsUtils;
use ET\Builder\Packages\Module\Layout\Components\Style\Utils\GroupedStatements;
use ET\Builder\Packages\Module\Layout\Components\Style\Utils\Utils;

/**
 * CssStyleUtils class.
 *
 * @since ??
 */
class CssStyleUtils {

	/**
	 * Split selector list by top-level commas only.
	 *
	 * Respects parentheses nesting (e.g., :is(), :where(), :has()) and only splits
	 * at commas that are not inside any parentheses. This prevents splitting selector
	 * lists inside pseudo-class functions.
	 *
	 * @since ??
	 *
	 * @param string $selector_list Comma-separated selector list.
	 *
	 * @return array Array of selectors split at top-level commas only.
	 *
	 * @example
	 * ```php
	 * self::_split_selector_list('.a, .b');
	 * // Returns: ['.a', ' .b']
	 *
	 * self::_split_selector_list(':is(.a, .b), .c');
	 * // Returns: [':is(.a, .b)', ' .c']
	 * ```
	 */
	private static function _split_selector_list( $selector_list ) {
		$segments = [];
		$current  = '';
		$depth    = 0;
		$length   = strlen( $selector_list );

		for ( $index = 0; $index < $length; $index++ ) {
			$char = $selector_list[ $index ];

			if ( '(' === $char ) {
				++$depth;
			} elseif ( ')' === $char && 0 < $depth ) {
				--$depth;
			}

			if ( ',' === $char && 0 === $depth ) {
				$segments[] = $current;
				$current    = '';
				continue;
			}

			$current .= $char;
		}

		$segments[] = $current;

		return $segments;
	}

	/**
	 * Get custom CSS statements based on given params.
	 *
	 * This function retrieves the CSS statements based on the provided arguments.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/GetStatements getStatements} in
	 * `@divi/module` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array         $selectors        Optional. An array of selectors for each breakpoint and state. Default `[]`.
	 *     @type callable      $selectorFunction Optional. The function to be called to generate CSS selector. Default `null`.
	 *     @type array         $attr             Optional. An array of module attribute data. Default `[]`.
	 *     @type array         $cssFields        Optional. An array of CSS fields. Default `[]`.
	 *     @type string|null   $orderClass             Optional. The selector class name.
	 *     @type string|null   $baseOrderClass         Optional. The base selector class name without prefixes.
	 *     @type bool          $isInsideStickyModule   Optional. Whether the module is inside sticky module or not. Default `false`.
	 *     @type string|null   $stickyParentOrderClass Optional. The sticky parent order class name. Default `null`.
	 *     @type bool          $isCustomPostType Optional. Whether the module is on a custom post type page. Default `false`.
	 *     @type string        $returnType       Optional. This is the type of value that the function will return.
	 *                                           Can be either `string` or `array`. Default `array`.
	 * }
	 *
	 * @return string|array The CSS statements formatted as a string.
	 *
	 * @example:
	 * ```php
	 * // Usage Example 1: Simple usage with default arguments.
	 * $args = [
	 *     'selectors'         => ['.element'],
	 *     'selectorFunction'  => null,
	 *     'attr'              => [
	 *         'desktop' => [
	 *             'state1' => [
	 *                 'custom_css1' => 'color: red;',
	 *                 'custom_css2' => 'font-weight: bold;',
	 *             ],
	 *             'state2' => [
	 *                 'custom_css1' => 'color: blue;',
	 *             ],
	 *         ],
	 *         'tablet'  => [
	 *             'state1' => [
	 *                 'custom_css1' => 'color: green;',
	 *                 'custom_css2' => 'font-size: 16px;',
	 *             ],
	 *         ],
	 *     ],
	 *     'cssFields'         => [
	 *         'custom_css1' => [
	 *             'selectorSuffix' => '::before',
	 *         ],
	 *         'custom_css2' => [
	 *             'selectorSuffix' => '::after',
	 *         ],
	 *     ],
	 * ];
	 *
	 * $cssStatements = MyClass::get_statements( $args );
	 * ```
	 * @example:
	 * ```php
	 * // Usage Example 2: Custom selector function to modify the selector and additional at-rules.
	 * $args = [
	 *     'selectors'         => ['.element'],
	 *     'selectorFunction'  => function( $args ) {
	 *         $defaultSelector = $args['selector'];
	 *         $breakpoint = $args['breakpoint'];
	 *         $state = $args['state'];
	 *         $attr = $args['attr'];
	 *
	 *         // Append breakpoint and state to the default selector.
	 *         $modifiedSelector = $defaultSelector . '-' . $breakpoint . '-' . $state;
	 *
	 *         return $modifiedSelector;
	 *     },
	 *     'attr'              => [
	 *         'desktop' => [
	 *             'state1' => [
	 *                 'custom_css1' => 'color: red;',
	 *                 'custom_css2' => 'font-weight: bold;',
	 *             ],
	 *         ],
	 *     ],
	 *     'cssFields'         => [
	 *         'custom_css1' => [
	 *             'selectorSuffix' => '::before',
	 *         ],
	 *         'custom_css2' => [
	 *             'selectorSuffix' => '::after',
	 *         ],
	 *     ],
	 * ];
	 *
	 * $cssStatements = MyClass::get_statements( $args );
	 * ```
	 */
	public static function get_statements( array $args ) {
		$args = wp_parse_args(
			$args,
			[
				'selectors'              => [],
				'selectorFunction'       => null,
				'attr'                   => [],
				'cssFields'              => [],
				'orderClass'             => null,
				'baseOrderClass'         => null,
				'isInsideStickyModule'   => false,
				'stickyParentOrderClass' => null,
				'isCustomPostType'       => false,
				'returnType'             => 'array',
				'atRules'                => '',
			]
		);

		$selectors                 = $args['selectors'];
		$selector_function         = $args['selectorFunction'];
		$attr                      = $args['attr'];
		$css_fields                = $args['cssFields'];
		$order_class               = $args['orderClass'];
		$base_order_class          = $args['baseOrderClass'];
		$is_inside_sticky_module   = $args['isInsideStickyModule'];
		$sticky_parent_order_class = $args['stickyParentOrderClass'];
		$is_custom_post_type       = $args['isCustomPostType'];
		$at_rules                  = $args['atRules'];

		$get_selector_suffix = function ( $css_name ) use ( $css_fields ) {
			// The `mainElement` is just like the other CSS fields. It's possible to have a `selectorSuffix` for it. However,
			// unlike other CSS fields, it has a special case where it returns an empty string as fallback. This is because
			// the `mainElement` is the module element that use main selector and it doesn't need a suffix by default.
			if ( 'mainElement' === $css_name ) {
				return $css_fields['mainElement']['selectorSuffix'] ?? '';
			}

			if ( 'freeForm' === $css_name ) {
				return '';
			}

			if ( 'before' === $css_name ) {
				return ':before';
			}

			if ( 'after' === $css_name ) {
				return ':after';
			}

			if ( isset( $css_fields[ $css_name ]['selectorSuffix'] ) ) {
				return $css_fields[ $css_name ]['selectorSuffix'];
			}

			return false;
		};

		$grouped_statements = new GroupedStatements();

		// Check if module has hover states by checking if any breakpoint has a 'hover' state.
		$has_hover_state = false;
		foreach ( $attr as $breakpoint => $state_values ) {
			if ( isset( $state_values['hover'] ) ) {
				$has_hover_state = true;
				break;
			}
		}

		foreach ( $attr as $breakpoint => $state_values ) {
			foreach ( $state_values as $state => $attr_value ) {
				// Each of the `css` subname value is literally entire CSS statement which requires its own
				// selector hence the additional loop on this `divi/css` specific getStatements() function.
				foreach ( $attr_value as $custom_css_name => $css_declaration ) {
					$order_selector  = Utils::get_selector(
						[
							'selectors'              => $selectors,
							'breakpoint'             => $breakpoint,
							'state'                  => $state,
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					);
					$selector_suffix = call_user_func( $get_selector_suffix, $custom_css_name );
					$selector_prefix = $css_fields[ $custom_css_name ]['selectorPrefix'] ?? false;

					// Require at least one of selectorSuffix or selectorPrefix (NOTE: mainElement returns empty string for suffix).
					$has_selector_suffix = false !== $selector_suffix && is_string( $selector_suffix );
					$has_selector_prefix = false !== $selector_prefix && is_string( $selector_prefix ) && '' !== $selector_prefix;

					if ( ! $has_selector_suffix && ! $has_selector_prefix ) {
						continue;
					}

					$style_breakpoint_settings = Breakpoint::get_style_breakpoint_settings();

					// If mainElement CSS contains transition and module has hover states, add !important to transition declarations.
					if ( 'mainElement' === $custom_css_name && $has_hover_state && preg_match( '/\btransition\b/i', $css_declaration ) ) {
						// Add !important to all transition-related declarations.
						// Match patterns like: transition: ...; or transition-property: ...; etc.
						$css_declaration = preg_replace_callback(
							'/\b(transition(?:-property|-duration|-timing-function|-delay)?)\s*:\s*([^;]+)(;|$)/i',
							function ( $matches ) {
								$property_name  = $matches[1];
								$property_value = trim( $matches[2] );
								$semicolon      = $matches[3] ?? '';

								// Only add !important if it's not already present.
								if ( ! str_contains( $property_value, '!important' ) ) {
									return $property_name . ': ' . $property_value . ' !important' . $semicolon;
								}

								return $matches[0];
							},
							$css_declaration
						);
					}

					if ( 'freeForm' === $custom_css_name ) {
						// Replace the word 'selector'/'.selector'/'#selector' in $css_declaration with the actual selector.
						// Use token-boundary pattern to match selector only as a complete CSS token, not when embedded
						// within other identifiers (e.g., '#my-selector' should not match 'selector' inside it).
						// Pattern matches selector when preceded by start-of-string, whitespace, or CSS punctuation
						// and followed by whitespace, CSS punctuation, colon, dot (for class chaining), hash (for id chaining),
						// bracket (for attribute selectors), or end-of-string.
						// Regex test: https://regex101.com/r/hJHpqY/1.

						$all_matches = [];
						preg_match_all( '/(^|\s|[{};,])\.?#?selector(?=\s|[{};,]|:|\.|#|\[|$)/', $css_declaration, $all_matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER );

						$modified_css_declaration = $css_declaration;
						$user_prefixes_to_remove  = [];

						if ( ! empty( $all_matches ) ) {
							// Process matches in reverse order to avoid issues with offset changes.
							for ( $i = count( $all_matches ) - 1; $i >= 0; $i-- ) {
								$match        = $all_matches[ $i ];
								$full_match   = $match[0][0]; // The entire matched string (e.g., " selector").
								$match_offset = $match[0][1]; // Offset of the full match.
								$prefix       = $match[1][0]; // The captured prefix (e.g., " ").

								$selector_to_use = $order_selector;

								// Extract the user-provided prefix before the 'selector' keyword.
								// Split by CSS rule boundaries to find the selector prefix in the current rule only.
								// Regex test: https://regex101.com/r/9wfRi7/1.

								$user_prefix_start_pos = $match_offset + strlen( $prefix );
								$before_match          = substr( $css_declaration, 0, $user_prefix_start_pos );
								$segments              = preg_split( '/[{;\n\r]+/', $before_match );
								$last_segment          = ! empty( $segments ) ? end( $segments ) : '';
								$last_segment          = trim( $last_segment );
								// Extract CSS selector at end of string: #id, .class, or element name.
								// Regex test: https://regex101.com/r/c2IdrQ/1.
								$user_prefix_matches = [];

								preg_match( '/([#.a-zA-Z-][^\s]*)$/', $last_segment, $user_prefix_matches );
								$user_prefix = ! empty( $user_prefix_matches ) ? trim( $user_prefix_matches[1] ) : '';

								// Handle Theme Builder with user-provided selector prefix.
								// When a user provides a selector prefix (e.g., #page-container, .my-wrapper),
								// insert it after .et-db to maintain correct DOM hierarchy.
								if ( $user_prefix && str_starts_with( $selector_to_use, '.et-db #et-boc .et-l ' ) ) {
									// Remove user prefix from selector if already present (to avoid duplication).
									// preg_quote() escapes special regex characters automatically.
									// Check if user prefix exists in selector with word boundaries.
									// Regex test: https://regex101.com/r/8hkPwz/1.

									$escaped_user_prefix = preg_quote( $user_prefix, '/' );
									if ( preg_match( '/\s' . $escaped_user_prefix . '(?=\s|$)/', $selector_to_use ) ) {
										$selector_to_use = preg_replace( '/\s' . $escaped_user_prefix . '(?=\s|$)/', '', $selector_to_use );
									} else {
										$user_prefixes_to_remove[] = $user_prefix;
									}

									// Insert user prefix after .et-db for correct hierarchy.
									$selector_to_use = preg_replace( '/^\.et-db/', '.et-db ' . $user_prefix, $selector_to_use );
								}

								$replacement              = $prefix . $selector_to_use;
								$modified_css_declaration = substr_replace( $modified_css_declaration, $replacement, $match_offset, strlen( $full_match ) );
							}

							// Remove user-provided prefixes that are now included in the selector
							// to prevent duplication like "#page-container .et-db #page-container ...".
							foreach ( $user_prefixes_to_remove as $user_prefix_to_remove ) {
								// preg_quote() escapes special regex characters automatically.
								// Remove duplicate user prefix before .et-db (Theme Builder hierarchy fix).
								// Regex test: https://regex101.com/r/9ItyFb/1.

								$escaped_prefix           = preg_quote( $user_prefix_to_remove, '/' );
								$modified_css_declaration = preg_replace(
									'/' . $escaped_prefix . '\s+(?=\.et-db)/',
									'',
									$modified_css_declaration
								);
							}
						}

						$grouped_statements->add(
							[
								'atRules'     => ! empty( $at_rules ) ? $at_rules : Utils::get_at_rules( $breakpoint, $style_breakpoint_settings ),
								'selector'    => '', // Empty selector indicating the free-form-css.
								'declaration' => wp_strip_all_tags( $modified_css_declaration ),
							]
						);
					} else {
						// Getting selector from cssFields if available.
						$selector = $css_fields[ $custom_css_name ]['selector'] ?? '';

						// Use customPostTypeSelector if on a custom post type page and it's defined.
						if ( $is_custom_post_type ) {
							$has_custom_post_type_selector = $css_fields[ $custom_css_name ]['customPostTypeSelector'] ?? false;
							if ( $has_custom_post_type_selector ) {
								$selector = $has_custom_post_type_selector;
							}
						}

						// If selector is empty, use the order selector as fallback.
						if ( empty( $selector ) ) {
							$selector = $order_selector;
						}

						// Replace the word '{{selector}}' with the actual selector.
						if ( str_contains( $selector, '{{selector}}' ) ) {
							$selector = ModuleElementsUtils::interpolate_selector(
								[
									'selectorTemplate' => $selector,
									'value'            => $order_class,
									'placeholder'      => '{{selector}}',
								]
							);
						}

						// Replace the word '{{baseSelector}}' with the base selector (without prefixes).
						if ( str_contains( $selector, '{{baseSelector}}' ) && ! empty( $base_order_class ) ) {
							$selector = ModuleElementsUtils::interpolate_selector(
								[
									'selectorTemplate' => $selector,
									'value'            => $base_order_class,
									'placeholder'      => '{{baseSelector}}',
								]
							);
						}

						// If selector still contains uninterpolated {{baseSelector}} placeholder, fall back to orderSelector.
						if ( str_contains( $selector, '{{baseSelector}}' ) ) {
							$selector = $order_selector;
						}

						// Determine the main selector.
						$main_selector = is_callable( $selector_function )
							? call_user_func(
								$selector_function,
								[
									'selector'   => $selector,
									'breakpoint' => $breakpoint,
									'state'      => $state,
									'attr'       => $attr,
								]
							)
							: $selector;

						// Build the CSS selector with optional prefix and suffix.
						$base_selectors = self::_split_selector_list( $main_selector );
						$css_selector   = implode(
							',',
							array_map(
								function ( $base_selector ) use ( $selector_prefix, $selector_suffix, $has_selector_suffix, $has_selector_prefix ) {
									// Apply prefix if provided.
									$prefixed_selector = $has_selector_prefix ? $selector_prefix . $base_selector : $base_selector;

									// Apply suffix if provided.
									if ( $has_selector_suffix ) {
										$selector_suffixes = self::_split_selector_list( $selector_suffix );
										return implode(
											', ',
											array_map(
												fn( $suffix ) => $prefixed_selector . $suffix,
												$selector_suffixes
											)
										);
									}

									// If only prefix is provided, return the prefixed selector.
									return $prefixed_selector;
								},
								$base_selectors
							)
						);

						$grouped_statements->add(
							[
								'atRules'     => ! empty( $at_rules ) ? $at_rules : Utils::get_at_rules( $breakpoint, $style_breakpoint_settings ),
								'selector'    => $css_selector,
								'declaration' => $css_declaration,
							]
						);
					}
				}
			}
		}

		if ( 'array' === $args['returnType'] ) {
			return $grouped_statements->value_as_array();
		}

		return $grouped_statements->value();
	}
}
