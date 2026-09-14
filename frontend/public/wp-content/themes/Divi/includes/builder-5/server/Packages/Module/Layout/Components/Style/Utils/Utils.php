<?php
/**
 * Utils class
 *
 * @package Builder\FrontEnd
 * @since ??
 */

namespace ET\Builder\Packages\Module\Layout\Components\Style\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElementsUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Utils\Utils as StyleLibraryUtils;

/**
 * Utils class is a helper class for working with related CSS functionality.
 *
 * @since ??
 */
class Utils {

	use UtilsTraits\GetStatementsTrait;

	/**
	 * Theme Builder layout marker used in sticky selector processing.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private const THEME_BUILDER_LAYOUT_MARKER = '#et-boc .et-l';

	/**
	 * Theme Builder page-container marker.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private const THEME_BUILDER_PAGE_CONTAINER_MARKER = 'body #page-container';

	/**
	 * Theme Builder page-container marker with `body.et-db`.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private const THEME_BUILDER_EDITOR_PAGE_CONTAINER_MARKER = 'body.et-db #page-container';

	/**
	 * Regex for server-side module class matching.
	 *
	 * Regex101: https://regex101.com/r/QPzntt/1.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private const MODULE_CLASS_PATTERN = '/\.et_pb_(section|row|column|module)(?:_\d+[a-zA-Z0-9_-]*)?(?=\s|$|:|\.)/';

	/**
	 * Regex capture flag for returning match offsets.
	 *
	 * Mirrors `PREG_OFFSET_CAPTURE` and is defined locally to keep static analyzers stable.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	private const REGEX_OFFSET_CAPTURE = 256;

	/**
	 * Generates a hover state selector by adding the `:hover` class to the specified selectors.
	 *
	 * @since ??
	 *
	 * @param string      $breakpoint_base_selector The base selector for the breakpoint.
	 * @param string|null $order_class              Optional. The selector class name (reserved for future use).
	 *
	 * @return string The generated selector string.
	 */
	public static function generate_hover_state_selector( // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Parameter $order_class reserved for future use.
		string $breakpoint_base_selector,
		?string $order_class = null
	): string {
		// The process is different from VB, because in VB we always need to add hover class to the
		// module selector. However, in FE, we should always add the `:hover` at the end of the selector.
		// This is the default behaviour on D4.
		return self::_generate_pseudo_state_selector( $breakpoint_base_selector, 'hover' );
	}

	/**
	 * Generates a focus state selector by adding the `:focus` pseudo-class to the specified selectors.
	 *
	 * @since ??
	 *
	 * @param string $breakpoint_base_selector The base selector for the breakpoint.
	 *
	 * @return string The generated selector string.
	 */
	public static function generate_focus_state_selector( string $breakpoint_base_selector ): string {
		return self::_generate_pseudo_state_selector( $breakpoint_base_selector, 'focus' );
	}

	/**
	 * Generates a checked state selector by adding the `:checked` pseudo-class to the specified selectors.
	 *
	 * @since ??
	 *
	 * @param string $breakpoint_base_selector The base selector for the breakpoint.
	 *
	 * @return string The generated selector string.
	 */
	public static function generate_checked_state_selector( string $breakpoint_base_selector ): string {
		return self::_generate_pseudo_state_selector( $breakpoint_base_selector, 'checked' );
	}

	/**
	 * Generates an active state selector by adding the `:active` pseudo-class to the specified selectors.
	 *
	 * @since ??
	 *
	 * @param string $breakpoint_base_selector The base selector for the breakpoint.
	 *
	 * @return string The generated selector string.
	 */
	public static function generate_active_state_selector( string $breakpoint_base_selector ): string {
		return self::_generate_pseudo_state_selector( $breakpoint_base_selector, 'active' );
	}

	/**
	 * Generates a selector by adding a given pseudo-state to all selectors in a selector list.
	 *
	 * @since ??
	 *
	 * @param string $breakpoint_base_selector The base selector list.
	 * @param string $pseudo_state             The pseudo-state name without colon.
	 *
	 * @return string The generated selector list.
	 */
	private static function _generate_pseudo_state_selector( string $breakpoint_base_selector, string $pseudo_state ): string {
		static $selector_cache = [];
		$cache_key             = $pseudo_state . '::' . $breakpoint_base_selector;

		if ( isset( $selector_cache[ $cache_key ] ) ) {
			return $selector_cache[ $cache_key ];
		}

		$maybe_has_multiple_selectors = array_map( 'trim', preg_split( '/,\s?/', $breakpoint_base_selector ) );
		$breakpoint_base_selectors    = [];
		$pseudo_selector              = ':' . $pseudo_state;

		foreach ( $maybe_has_multiple_selectors as $selector ) {
			if ( str_contains( $selector, $pseudo_selector ) ) {
				$breakpoint_base_selectors[] = $selector;
			} elseif ( str_contains( $selector, ':' ) ) {
				// If the selector has pseudo selector, add the given pseudo-state before the pseudo selector.
				$breakpoint_base_selectors[] = implode( $pseudo_selector . ':', explode( ':', $selector, 2 ) );
			} else {
				$breakpoint_base_selectors[] = $selector . $pseudo_selector;
			}
		}

		$selector_cache[ $cache_key ] = implode( ', ', $breakpoint_base_selectors );

		return $selector_cache[ $cache_key ];
	}

	/**
	 * Generates a sticky state selector by adding the `et_pb_sticky` class to the specified selectors.
	 *
	 * NOTE ON REGEX ARCHITECTURE:
	 * This function uses environment-specific regexes to distinguish between Server/Frontend
	 * (Numeric-based) and Visual Builder (UUID-based) module order classes. Server regexes
	 * are optimized for positional integer indices to maintain performance.
	 *
	 * @since ??
	 *
	 * @param string      $breakpoint_base_selector    The base selector for the breakpoint.
	 * @param string|null $order_class                 Optional. The selector class name.
	 * @param bool|null   $is_inside_sticky_module     Optional. Whether the module is inside sticky module or not.
	 * @param string|null $sticky_parent_order_class   Optional. The order class of the sticky parent module.
	 *
	 * @return string The generated selector string.
	 */
	public static function generate_sticky_state_selector(
		string $breakpoint_base_selector,
		?string $order_class = null,
		?bool $is_inside_sticky_module = false,
		?string $sticky_parent_order_class = null
	): string {
		$theme_builder_layout_marker_length = strlen( self::THEME_BUILDER_LAYOUT_MARKER );
		$maybe_has_multiple_selectors       = ! str_contains( $breakpoint_base_selector, ',' )
			? [ trim( $breakpoint_base_selector ) ]
			: array_map( 'trim', preg_split( '/,\s?/', $breakpoint_base_selector ) );
		$breakpoint_base_selectors          = [];
		$has_sticky_parent_order_class      = ! empty( $sticky_parent_order_class );
		$sticky_parent_base_class           = null;
		$sticky_parent_generic_class_regex  = null;

		// Precompute sticky-parent regex once for all selectors in this invocation.
		if ( $has_sticky_parent_order_class ) {
			$sticky_parent_base_class          = preg_replace( '/_\d+.*$/', '', $sticky_parent_order_class );
			$sticky_parent_generic_class_regex = '/\.(' . preg_quote( $sticky_parent_base_class, '/' ) . ')(?=\s|$|:|\\.)/';
		}

		// Check if we should use CPT logic for selector processing.
		static $cached_should_use_theme_builder_context_logic = null;
		$should_use_theme_builder_context_logic               = null === $cached_should_use_theme_builder_context_logic
			? ( Conditions::is_custom_post_type() || Conditions::is_tb_enabled() )
			: $cached_should_use_theme_builder_context_logic;
		$cached_should_use_theme_builder_context_logic        = $should_use_theme_builder_context_logic;

		// Only check selector patterns if conditional tags didn't match.
		// This avoids unnecessary string operations when context is already known.
		if ( ! $should_use_theme_builder_context_logic ) {
			// Detect Theme Builder context from selector patterns.
			// On the frontend, $is_theme_builder is false (no et_tb query param), but Theme Builder
			// layouts still need pattern-based selector processing. Check for TB selector patterns:
			// - '#et-boc .et-l' indicates Theme Builder/CPT context in editor or frontend.
			// - 'body #page-container' indicates Theme Builder layout on frontend.
			// - 'body.et-db #page-container' indicates Theme Builder layout with .et-db class.
			$has_theme_builder_pattern = str_contains( $breakpoint_base_selector, self::THEME_BUILDER_LAYOUT_MARKER )
				|| str_contains( $breakpoint_base_selector, self::THEME_BUILDER_PAGE_CONTAINER_MARKER )
				|| str_contains( $breakpoint_base_selector, self::THEME_BUILDER_EDITOR_PAGE_CONTAINER_MARKER );

			$should_use_theme_builder_context_logic = $has_theme_builder_pattern;
		}

		foreach ( $maybe_has_multiple_selectors as $selector ) {
			// Check if selector already has `.et_pb_sticky` class to prevent double sticky classes (issue #45901).
			if ( str_contains( $selector, '.et_pb_sticky' ) ) {
				$breakpoint_base_selectors[] = $selector;
				continue;
			}

			if ( $is_inside_sticky_module ) {
				// At this point, if the module itself is inside another sticky module, it means
				// we shouldn't add the `et_pb_sticky` class directly to the module selector due
				// to the sticky styles won't be triggered anyway.

				if ( $should_use_theme_builder_context_logic ) {
					// Fast path: If a sticky parent order class is provided and found in the selector, use it directly.
					if ( $has_sticky_parent_order_class && str_contains( $selector, $sticky_parent_order_class ) ) {
						// Apply .et_pb_sticky to the specific sticky parent module class.
						// This handles cases where sticky is on ROW or COLUMN, not just SECTION.
						// Example: 'body #page-container .et_pb_section_0 .et_pb_row_2 .et_pb_button_0'
						// → 'body #page-container .et_pb_section_0 .et_pb_row_2.et_pb_sticky .et_pb_button_0'.
						$selector = str_replace( $sticky_parent_order_class, $sticky_parent_order_class . '.et_pb_sticky', $selector );
					} elseif ( $has_sticky_parent_order_class ) {
						$selector_has_theme_builder_page_container_marker = str_contains( $selector, self::THEME_BUILDER_PAGE_CONTAINER_MARKER )
							|| str_contains( $selector, self::THEME_BUILDER_EDITOR_PAGE_CONTAINER_MARKER );
						$selector_has_theme_builder_layout_marker         = str_contains( $selector, self::THEME_BUILDER_LAYOUT_MARKER );

						if ( ! $selector_has_theme_builder_page_container_marker && ! $selector_has_theme_builder_layout_marker ) {
							$breakpoint_base_selectors[] = '.et_pb_sticky ' . $selector;
							continue;
						}

						// Sticky parent class is provided but not found in selector - we need to insert it.
						// CRITICAL: Check if selector has a generic base class matching the sticky parent's base.
						//
						// WHY THIS MATTERS:
						// Base selectors often use generic classes like '.et_pb_section' for broad targeting.
						// But in HTML, '.et_pb_section' and '.et_pb_section_0_tb_header' are the SAME element:
						// <div class="et_pb_section_0_tb_header et_pb_section et_pb_sticky">
						//
						// WRONG: '.et_pb_section .et_pb_section_0_tb_header.et_pb_sticky' (descendant selector)
						// RIGHT: '.et_pb_section_0_tb_header.et_pb_sticky' (chained classes on the same element)
						//
						// REGEX STRATEGY (Server vs Visual Builder):
						// The Server/Frontend uses positional integer indices. To maintain performance and
						// avoid matching UUIDs used in the Visual Builder, Server-specific regexes are
						// optimized for numeric suffixes.
						//
						// SCOPE DIFFERENCE (Stripping vs Identification):
						// This stripping logic ( /_\d+.*$/ ) is greedy to intentionally match and discard
						// everything after the suffix (including pseudo-classes like :hover) to extract
						// the clean base class name. In contrast, Identification regexes use lookaheads
						// to identify boundaries for precise injection of the .et_pb_sticky class.
						//
						// Extract base class from sticky parent (e.g., 'et_pb_section' from 'et_pb_section_0_tb_header').
						// Regex101: https://regex101.com/r/QPzntt/1.
						$generic_class_matches = [];
						if ( preg_match( $sticky_parent_generic_class_regex, $selector, $generic_class_matches, self::REGEX_OFFSET_CAPTURE ) ) {
							// REPLACE generic class with specific sticky parent order class + .et_pb_sticky.
							// Example: 'body #page-container .et_pb_section .et_pb_button_0'
							// → 'body #page-container .et_pb_section_0_tb_header.et_pb_sticky .et_pb_button_0'.
							$match_position = (int) $generic_class_matches[0][1];
							$match_length   = strlen( $generic_class_matches[0][0] );
							$selector       = substr( $selector, 0, $match_position ) . '.' . $sticky_parent_order_class . '.et_pb_sticky' . substr( $selector, $match_position + $match_length );
						} else {
							// No generic base class found - INSERT sticky parent class with .et_pb_sticky.
							// Priority 1: Check for the Theme Builder pattern '#et-boc .et-l'.
							$pattern_position     = strpos( $selector, self::THEME_BUILDER_LAYOUT_MARKER );
							$module_class_matches = [];
							if ( false !== $pattern_position ) {
								// Theme Builder selector: Insert sticky parent after .et-l.
								$before_pattern = substr( $selector, 0, $pattern_position + $theme_builder_layout_marker_length );
								$after_pattern  = substr( $selector, $pattern_position + $theme_builder_layout_marker_length );
								$selector       = $before_pattern . ' .' . $sticky_parent_order_class . '.et_pb_sticky' . $after_pattern;
							} elseif ( preg_match_all( self::MODULE_CLASS_PATTERN, $selector, $module_class_matches, self::REGEX_OFFSET_CAPTURE ) ) {
								// Priority 2: Fallback to finding the last module class.
								// Broad Regex: Matches module classes followed by space, end of string, or other selectors.
								// Supports numeric indices and Theme Builder suffixes.
								// Regex101: https://regex101.com/r/QPzntt/1.
								// Get the last match (the closest container ancestor to the target).
								$last_match     = end( $module_class_matches[0] );
								$match_position = (int) $last_match[1];
								$match_length   = strlen( $last_match[0] );

								// Insert sticky parent with .et_pb_sticky AFTER the last container module class.
								// Example: 'body #page-container .et_pb_section_0 .et_pb_button_0'
								// → 'body #page-container .et_pb_section_0 .et_pb_row_999.et_pb_sticky .et_pb_button_0'.
								$selector = substr( $selector, 0, $match_position + $match_length ) . ' .' . $sticky_parent_order_class . '.et_pb_sticky' . substr( $selector, $match_position + $match_length );
							} else {
								// No module class found, prepend sticky parent.
								$selector = '.' . $sticky_parent_order_class . '.et_pb_sticky ' . $selector;
							}
						}
					} else {
						// Pattern-based selector generation for Theme Builder context.
						// NOTE: This "standalone" broad fallback is triggered when stickyParentOrderClass is null or unresolved.
						// This ensures backward compatibility with Custom Selectors or Legacy Divi layouts.
						// Priority 1: Check for Theme Builder pattern '#et-boc .et-l'.
						$pattern_position = strpos( $selector, self::THEME_BUILDER_LAYOUT_MARKER );
						if ( false !== $pattern_position ) {
							// Theme Builder selector: Insert .et_pb_sticky after .et-l.
							// Handles: '.et-db #et-boc .et-l .et_pb_button_0' and 'body.et-db #page-container #et-boc .et-l .et_pb_button_0'.
							$before_pattern = substr( $selector, 0, $pattern_position + $theme_builder_layout_marker_length );
							$after_pattern  = substr( $selector, $pattern_position + $theme_builder_layout_marker_length );
							$selector       = $before_pattern . ' .et_pb_sticky' . $after_pattern;
						} elseif (
							str_contains( $selector, self::THEME_BUILDER_PAGE_CONTAINER_MARKER )
							|| str_contains( $selector, self::THEME_BUILDER_EDITOR_PAGE_CONTAINER_MARKER )
						) {
							// Priority 2: Selector contains 'body #page-container' but not Theme Builder pattern.
							// Find the first module class and insert .et_pb_sticky after it to create a valid selector.
							// Handles: 'body #page-container .et_pb_section_0_tb_header .et_pb_button_0_tb_header'.
							//
							// REGEX STRATEGY: Specifically matches core structural modules and numeric indices (Server-side).
							// Identification Scope: Uses lookahead to identify boundaries for precise injection
							// of .et_pb_sticky before pseudo-classes or combinators.
							// Regex101: https://regex101.com/r/QPzntt/1.
							$module_class_match = [];
							if ( preg_match( self::MODULE_CLASS_PATTERN, $selector, $module_class_match, self::REGEX_OFFSET_CAPTURE ) ) {
								$match_position = (int) $module_class_match[0][1];
								$match_length   = strlen( $module_class_match[0][0] );
								$selector       = substr( $selector, 0, $match_position + $match_length ) . '.et_pb_sticky' . substr( $selector, $match_position + $match_length );
							} else {
								// No module class found, use regular sticky logic.
								$selector = '.et_pb_sticky ' . $selector;
							}
						} else {
							// Priority 3: Regular selector - prepend .et_pb_sticky.
							// Handles: '.et_pb_button_0', '.et_pb_module .et_pb_button_0', etc.
							$selector = '.et_pb_sticky ' . $selector;
						}
					}
				} else {
					// We should add sticky class followed by single space
					// to catch parent/ancestor module with sticky enabled.
					$selector = '.et_pb_sticky ' . $selector;
				}

				$breakpoint_base_selectors[] = $selector;
			} elseif ( empty( $selector ) ) { // When selector is empty string.
				$breakpoint_base_selectors[] = '.et_pb_sticky';
			} elseif ( is_null( $order_class ) || empty( $order_class ) ) { // When orderClass isn't provided or empty, use the fallback process.
				// In order for the `et_pb_sticky` to work, it needs to be added to the parent selector
				// and some selectors include child selectors. So we need to split the selector and
				// extract the parent selector and child selectors and add the `et_pb_sticky` to the
				// parent selector to generate the proper styles when hover state is activated.
				$maybe_has_parent_selector = explode( ' ', $selector );

				// If there is more than one selector, then it means there is a parent selector and
				// we only need to apply `et_pb_sticky` class to the parent selector.
				// Simplified example:
				// `.et_pb_blurb .et_pb_module_header` must be changed to:
				// `.et_pb_blurb.et_pb_sticky .et_pb_module_header`.
				if ( count( $maybe_has_parent_selector ) > 1 ) {
					// Extract the parent selector.
					$parent_selector = array_shift( $maybe_has_parent_selector );

					// Create a selector string from the child selectors.
					$child_selectors = implode( ' ', $maybe_has_parent_selector );

					// Add the `et_pb_sticky` to the parent selector and append the child selectors.
					$breakpoint_base_selectors[] = $parent_selector . '.et_pb_sticky ' . $child_selectors;
				} else {
					$breakpoint_base_selectors[] = $selector . '.et_pb_sticky';
				}
			} else {
				// Create regex pattern for matching orderClass within the selector, but only when it forms
				// its own complete selector (not part of a larger selector).
				// Match example: https://regex101.com/r/P38jdN/1.
				$escaped_order_class = preg_quote( $order_class, '/' );
				$order_class_regex   = '/(?<=\\s|^|>|\\.)' . $escaped_order_class . '(?=\\s|$|:|\\.|>)/';
				$replacement         = $order_class . '.et_pb_sticky';

				// Append "et_pb_sticky" to the $selector or $order_class.
				$selector_output             = preg_replace( $order_class_regex, $replacement, $selector );
				$breakpoint_base_selectors[] = empty( $order_class ) || $selector_output === $selector
					? $selector . '.et_pb_sticky'
					: $selector_output;
			}
		}

		return implode( ', ', $breakpoint_base_selectors );
	}

	/**
	 * Get CSS At-rules based on given breakpoint name.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/GetAtRules getAtRules}
	 * in `@divi/module` package.
	 *
	 * @since ??
	 *
	 * @param string $breakpoint Breakpoint name.
	 * @param array  $style_breakpoint_settings Style breakpoint settings.
	 * @param bool   $is_visibility_context Optional. Whether this is for visibility/disabled-on context. Default false.
	 *
	 * @return string|false Will return false if the breakpoint does not exist.
	 */
	public static function get_at_rules(
		string $breakpoint,
		array $style_breakpoint_settings,
		bool $is_visibility_context = false
	) {
		$breakpoint_setting = $style_breakpoint_settings[ $breakpoint ] ?? null;

		if ( $breakpoint_setting ) {
			// Base device breakpoints should not be wrapped in media queries for regular styles.
			// For visibility options (disabled-on), base devices can use media queries.
			if ( ! $is_visibility_context && ! empty( $breakpoint_setting['baseDevice'] ) ) {
				return false;
			}

			$max_width             = $breakpoint_setting['maxWidth']['value'] ?? null;
			$min_width             = $breakpoint_setting['minWidth']['value'] ?? null;
			$breakpoint_order      = $breakpoint_setting['order'] ?? null;
			$base_breakpoint_order = null;

			foreach ( $style_breakpoint_settings as $setting ) {
				if ( ! empty( $setting['baseDevice'] ) ) {
					$base_breakpoint_order = $setting['order'] ?? null;
					break;
				}
			}

			$is_below_base_breakpoint        = is_int( $breakpoint_order )
				&& is_int( $base_breakpoint_order )
				&& $breakpoint_order < $base_breakpoint_order;
			$is_visibility_pseudo_breakpoint = in_array( $breakpoint, [ 'desktopAbove', 'tabletOnly' ], true );

			// For style generation (not visibility), breakpoints below the base device should use max-width only.
			// Keep min/max ranges for visibility context to preserve disabled-on behavior.
			if ( ! $is_visibility_context && $is_below_base_breakpoint && ! $is_visibility_pseudo_breakpoint && $max_width ) {
				return sprintf( '@media only screen and (max-width: %1$s)', $max_width );
			}

			if ( $max_width && $min_width ) {
				return sprintf(
					'@media only screen and (min-width: %1$s) and (max-width: %2$s)',
					$min_width,
					$max_width
				);
			}

			if ( $max_width ) {
				return sprintf( '@media only screen and (max-width: %1$s)', $max_width );
			}

			if ( $min_width ) {
				return sprintf( '@media only screen and (min-width: %1$s)', $min_width );
			}
		}

		// Infer `disabled on` rules from breakpoint settings. This used to be fixed value, but now it needs to infer
		// the value from customizable breakpoints' state value property.
		$rules = Breakpoint::get_disabled_on_rules();

		if ( isset( $rules[ $breakpoint ] ) ) {
			return $rules[ $breakpoint ];
		}

		return false;
	}

	/**
	 * Get current property's important value based on given breakpoint and state.
	 *
	 * See below for fallback flow:
	 *
	 * |       | value | hover | sticky |
	 * |-------|-------|-------|--------|
	 * | Desktop |   *   |  <--  |  <--   |
	 * | Tablet  |   ^   |  <--  |  <--   |
	 * | Phone   |   ^   |  <--  |  <--   |
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/GetCurrentImportant getCurrentImportant}
	 * in `@divi/module` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array|boolean $important                     An array key-value pairs with property name
	 *                                                        as the key and selectors as the value.
	 *                                                        Note if an array value is passed, then it should not be in shorthand format.
	 *                                                        Use `Utils::get_expanded_shorthand_important()` to expand
	 *                                                        shorthand format property before passing it to this function.
	 *     @type string        $breakpoint                    The breakpoint of the selector.
	 *                                                        Can be either `desktop`, `tablet`, or `phone`.
	 *     @type string        $state                         The state of the selector.
	 *                                                        Can be either `value`, `hover`, or `sticky`.
	 * }
	 *
	 * @return array|bool
	 */
	public static function get_current_important( array $args ) {
		$important  = $args['important'];
		$breakpoint = $args['breakpoint'];
		$state      = $args['state'];

		if ( is_bool( $important ) ) {
			return $important;
		}

		return ModuleUtils::use_attr_value(
			[
				'attr'         => $important,
				'breakpoint'   => $breakpoint,
				'state'        => $state,
				'mode'         => 'getOrInheritAll',
				'defaultValue' => [],
			]
		);
	}

	/**
	 * Get property selector names of current breakpoint + state based on given property selectors.
	 *
	 * See below for fallback flow:
	 *
	 * |       | value | hover | sticky |
	 * |-------|-------|-------|--------|
	 * | Desktop |   *   |  <--  |  <--   |
	 * | Tablet  |   ^   |  <--  |  <--   |
	 * | Phone   |   ^   |  <--  |  <--   |
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/GetCurrentPropertySelectorNames getCurrentPropertySelectorNames}
	 * in `@divi/module` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array  $propertySelectors An array key-value pairs with property name as the key and selectors as the value.
	 *     @type string $breakpoint        The breakpoint of the selector. Can be either `desktop`, `tablet`, or `phone`.
	 *     @type string $state             The state of the selector. Can be either `value`, `hover`, or `sticky`.
	 * }
	 *
	 * @return array
	 */
	public static function get_current_property_selector_names( array $args ): array {
		$property_selectors = $args['propertySelectors'];
		$breakpoint         = $args['breakpoint'];
		$state              = $args['state'];

		$property_selector = ModuleUtils::use_attr_value(
			[
				'attr'       => $property_selectors,
				'breakpoint' => $breakpoint,
				'state'      => $state,
				'mode'       => 'getAndInheritAll',
			]
		);

		return is_array( $property_selector ) ? array_keys( $property_selector ) : [];
	}

	/**
	 * Returns expanded shorthand property values for important.
	 *
	 * This functions expands any shorthand property values for important based on the provided `$propertySelectorsShorthandMap`.
	 * If the given important value is a boolean, it is returned as is.
	 * If important is an array and it contains any shorthand property, theses are expanded to longhand properties.
	 * We then update the values in the important array and return the updated important array.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/GetExpandedShorthandImportant getExpandedShorthandImportant}
	 * in `@divi/module` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array|boolean $important                     An array key-value pairs with property name
	 *                                                        as the key and selectors as the value.
	 *                                                        This can be shorthand format property or longhand format property.
	 *     @type array         $propertySelectorsShorthandMap Optional. This is the map of shorthand properties
	 *                                                        to their longhand properties.
	 *                                                        Default `[]`.
	 * }
	 *
	 * @return array|bool
	 */
	public static function get_expanded_shorthand_important( array $args ) {
		static $cache = [];

		// Create a unique key for/using the given arguments.
		$key = md5( wp_json_encode( $args ) );

		// Return cached value if available.
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}

		$important                        = $args['important'] ?? [];
		$property_selectors_shorthand_map = $args['propertySelectorsShorthandMap'] ?? [];

		if ( is_bool( $important ) ) {
			$cache[ $key ] = $important;

			return $important;
		}

		foreach ( $important as $breakpoint => $state_values ) {
			foreach ( $state_values as $state => $important_value ) {

				$important_values          = $important['desktop']['value'] ?? [];
				$complete_important_values = [];

				// Create complete important values for properties from the shorthand property important values.
				if ( ! empty( $important_value ) ) {
					foreach ( array_keys( $important_value ) as $possible_shorthand_property ) {
						if ( array_key_exists( $possible_shorthand_property, $property_selectors_shorthand_map ) ) {
							foreach ( $property_selectors_shorthand_map as $property_name_shorthand => $property_name_array ) {
								if ( $property_name_shorthand === $possible_shorthand_property ) {
									// Iterate over the mapped list of possible shorthand properties and construct a new list
									// of important values. For instance, transform `{ margin: true }` to
									// `{ margin-top: true, margin-right: true, margin-bottom: true, margin-left: true }`.

									foreach ( $property_name_array as $property_name ) {
										$complete_important_values[ $property_name ] = $important_value[ $possible_shorthand_property ];
									}
								}
							}
						}
					}

					if ( ! empty( $complete_important_values ) ) {
						$important[ $breakpoint ][ $state ] = $complete_important_values;
					}
				}
			}
		}

		$cache[ $key ] = $important;

		return $important;
	}

	/**
	 * Get CSS ruleset based on given selector and declaration.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/GetRuleset getRuleset}
	 * in `@divi/module` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $selector    The CSS selector.
	 *     @type string $declaration The CSS declaration.
	 * }
	 *
	 * @return string
	 */
	public static function get_ruleset( array $args ): string {
		$selector    = $args['selector'];
		$declaration = $args['declaration'];

		if ( '' === $selector ) {
			return $declaration;
		}

		return sprintf( '%1$s {%2$s}', $selector, $declaration );
	}

	/**
	 * Get CSS selector from property selectors based on sets of selectors, current state, and property name given.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/GetSelectorOfPropertySelectors getSelectorOfPropertySelectors}
	 * in `@divi/module` package.
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array  $selectors            The selectors data.
	 *     @type string $propertyName         The name of the property.
	 *     @type string $breakpoint           Optional. The attribute breakpoint.
	 *                                        Can be either `desktop`, `tablet`, or `phone`. Default `desktop`.
	 *     @type string $state                Optional. The state of the selector.
	 *                                        Can be either `value`, `hover`, or `sticky`. Default `value`.
	 *     @type string $orderClass           Optional. Module CSS selector.
	 *     @type bool   $isInsideStickyModule Optional. Whether the module is inside sticky module or not.
	 *     @type string|null $stickyParentOrderClass Optional. The sticky parent order class name. Default `null`.
	 * }
	 *
	 * @return string
	 */
	public static function get_selector_of_property_selectors( array $args ): string {
		$args = array_merge(
			[
				'breakpoint' => 'desktop',
				'state'      => 'value',
			],
			$args
		);

		$selectors     = $args['selectors'];
		$property_name = $args['propertyName'];
		$breakpoint    = $args['breakpoint'];
		$state         = $args['state'];
		$order_class   = $args['orderClass'] ?? null;

		$is_inside_sticky_module   = $args['isInsideStickyModule'] ?? false;
		$sticky_parent_order_class = $args['stickyParentOrderClass'] ?? null;

		// Ger base selector for fallback in case current breakpoint + state selector doesn't exist.
		$base_selector            = $selectors['desktop']['value'][ $property_name ] ?? '';
		$is_desktop               = 'desktop' === $breakpoint;
		$is_default_state         = 'value' === $state;
		$breakpoint_base_selector = ! $is_desktop && ! $is_default_state && isset( $selectors[ $breakpoint ]['value'][ $property_name ] )
			? $selectors[ $breakpoint ]['value'][ $property_name ]
			: $base_selector;

		$current_state_selector   = $selectors[ $breakpoint ][ $state ][ $property_name ] ?? '';
		$effective_state_selector = ! $current_state_selector ? $breakpoint_base_selector : $current_state_selector;

		if ( 'hover' === $state ) {
			// We'll need to always add `:hover` to the selector.
			return self::generate_hover_state_selector(
				$effective_state_selector,
				$order_class
			);
		}

		if ( 'focus' === $state ) {
			// We'll need to always add `:focus` to the selector.
			return self::generate_focus_state_selector(
				$effective_state_selector
			);
		}

		if ( 'checked' === $state ) {
			// We'll need to always add `:checked` to the selector.
			return self::generate_checked_state_selector(
				$effective_state_selector
			);
		}

		if ( 'active' === $state ) {
			// We'll need to always add `:active` to the selector.
			return self::generate_active_state_selector(
				$effective_state_selector
			);
		}

		if ( 'sticky' === $state ) {
			// We'll need to always add `.et_pb_sticky` to the selector.
			// When module is inside a sticky parent, check if the sticky selector already contains
			// .et_pb_sticky to avoid double sticky classes.
			$sticky_base_selector = $effective_state_selector;

			if ( $is_inside_sticky_module && $current_state_selector && str_contains( $current_state_selector, '.et_pb_sticky' ) ) {
				// If sticky selector already contains .et_pb_sticky, use base selector to avoid double classes.
				$sticky_base_selector = $breakpoint_base_selector;
			}

			return self::generate_sticky_state_selector(
				$sticky_base_selector,
				$order_class,
				$is_inside_sticky_module,
				$sticky_parent_order_class
			);
		}

		return '' !== $current_state_selector ? $current_state_selector : $breakpoint_base_selector;
	}

	/**
	 * Get CSS selector based on sets of selectors, breakpoint, and state is given.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/GetSelector getSelector}
	 * in `@divi/module` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *      @type array  $selectors            An array of selectors for each breakpoint and state.
	 *      @type string $breakpoint           Optional. The breakpoint of the selector.
	 *                                         Can be either `desktop`, `tablet`, or `phone`. Default `desktop`.
	 *      @type string $state                Optional. The state of the selector.
	 *                                         Can be either `value`, `hover`, or `sticky`. Default `value`.
	 *      @type string $orderClass           Optional. The selector class name.
	 *      @type bool   $isInsideStickyModule Optional. Whether the module is inside sticky module or not.
	 *      @type string $stickyParentOrderClass Optional. The sticky parent order class name.
	 * }
	 *
	 * @return string
	 */
	public static function get_selector( array $args ): string {
		$args = array_merge(
			[
				'breakpoint' => 'desktop',
				'state'      => 'value',
			],
			$args
		);

		$selectors   = $args['selectors'];
		$breakpoint  = $args['breakpoint'];
		$state       = $args['state'];
		$order_class = $args['orderClass'] ?? null;

		$is_inside_sticky_module   = $args['isInsideStickyModule'] ?? false;
		$sticky_parent_order_class = $args['stickyParentOrderClass'] ?? null;

		// Get base selector for fallback in case current breakpoint + state selector doesn't exist.
		$base_selector            = $selectors['desktop']['value'] ?? '';
		$is_desktop               = 'desktop' === $breakpoint;
		$is_default_state         = 'value' === $state;
		$breakpoint_base_selector = ! $is_desktop && ! $is_default_state && isset( $selectors[ $breakpoint ]['value'] )
		? $selectors[ $breakpoint ]['value']
		: $base_selector;

		$current_state_selector   = $selectors[ $breakpoint ][ $state ] ?? '';
		$effective_state_selector = ! $current_state_selector ? $breakpoint_base_selector : $current_state_selector;

		if ( 'hover' === $state ) {
			// We'll need to always add `:hover` to the selector.
			return self::generate_hover_state_selector(
				$effective_state_selector,
				$order_class
			);
		}

		if ( 'focus' === $state ) {
			// We'll need to always add `:focus` to the selector.
			return self::generate_focus_state_selector(
				$effective_state_selector
			);
		}

		if ( 'checked' === $state ) {
			// We'll need to always add `:checked` to the selector.
			return self::generate_checked_state_selector(
				$effective_state_selector
			);
		}

		if ( 'active' === $state ) {
			// We'll need to always add `:active` to the selector.
			return self::generate_active_state_selector(
				$effective_state_selector
			);
		}

		if ( 'sticky' === $state ) {
			// We'll need to always add `.et_pb_sticky` to the selector.
			// When module is inside a sticky parent, check if the sticky selector already contains
			// .et_pb_sticky to avoid double sticky classes.
			$sticky_base_selector = $effective_state_selector;

			if ( $is_inside_sticky_module && $current_state_selector && str_contains( $current_state_selector, '.et_pb_sticky' ) ) {
				// If sticky selector already contains .et_pb_sticky, use base selector to avoid double classes.
				$sticky_base_selector = $breakpoint_base_selector;
			}

			return self::generate_sticky_state_selector(
				$sticky_base_selector,
				$order_class,
				$is_inside_sticky_module,
				$sticky_parent_order_class
			);
		}

		return '' !== $current_state_selector ? $current_state_selector : $breakpoint_base_selector;
	}

	/**
	 * Get CSS statement based on given At-rules and ruleset.
	 *
	 * If a non-string value is provided for `atRules`, the `ruleset` is returned as-is.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/GetStatement getStatement}
	 * in `@divi/module` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string|boolean $atRules At rules.
	 *     @type string         $ruleset The ruleset.
	 * }
	 *
	 * @return string
	 */
	public static function get_statement( array $args ): string {
		$at_rules = $args['atRules'];
		$ruleset  = $args['ruleset'];

		if ( is_string( $at_rules ) ) {
			return sprintf( '%1$s {%2$s}', $at_rules, $ruleset );
		}

		return $ruleset;
	}

	/**
	 * Group object of declarations based on given group name.
	 *
	 * Group declaration that has no group name
	 * into `otherDeclaration` group.
	 * Initially created to group declaration that has property-specific selector.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/GroupDeclarationsByPropertySelectorNames groupDeclarationsByPropertySelectorNames}
	 * in `@divi/module` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array $propertySelectorNames Zero indexed array of property selector names.
	 *     @type array $declarations          Key-value pair array of CSS property as the key and CSS declaration as the value.
	 * }
	 *
	 * @return array
	 */
	public static function group_declarations_by_property_selector_names( array $args ): array {
		$property_selector_names = $args['propertySelectorNames'];
		$declarations            = $args['declarations'];
		$grouped                 = [];
		$ungrouped               = [];

		foreach ( $declarations as $css_property => $declaration ) {
			if ( in_array( $css_property, $property_selector_names, true ) ) {
				$grouped[ $css_property ] = $css_property . ': ' . $declaration . ';';
			} else {
				$ungrouped[] = $css_property . ': ' . $declaration;
			}
		}

		if ( $ungrouped ) {
			$grouped['ungrouped'] = StyleLibraryUtils::join_declarations( $ungrouped );
		}

		return $grouped;
	}

	/**
	 * Checks if any of the given selectors has the 'hover' key.
	 *
	 * @since ??
	 *
	 * @param array $selectors The array of selectors to check.
	 *
	 * @return bool Returns true if any of the selectors has the 'hover' key, otherwise false.
	 */
	public static function has_hover_selectors( array $selectors ): bool {
		if ( empty( $selectors ) ) {
			return false;
		}

		foreach ( $selectors as $selector ) {
			if ( isset( $selector['hover'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Replaces the hover selector placeholder in a given selector template.
	 *
	 * In FE, `{{:hover}}` suffix being replaced with ':hover'.
	 *
	 * @since ??
	 *
	 * @param array $selectors An array containing selectors property.
	 *
	 * @return array The modified selector template.
	 */
	public static function replace_hover_selector_placeholder( array $selectors ): array {
		return ModuleElementsUtils::interpolate_selector(
			[
				'selectorTemplate' => $selectors,
				'value'            => ':hover',
				'placeholder'      => '{{:hover}}',
			]
		);
	}

	/**
	 * Utils component to wrap style component output.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/StyleWrapper StyleWrapper}
	 * in `@divi/module` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *      @type array  $attr     An array of module attribute data.
	 *      @type string $children The content of the style tag.
	 * }
	 *
	 * @return string|array
	 */
	public static function style_wrapper( array $args ) {
		$attr     = $args['attr'];
		$children = $args['children'];

		if ( empty( $attr ) ) {
			return is_string( $children ) ? '' : [];
		}

		return $children;
	}

	/**
	 * Unpack property selectors shorthand map into property selectors.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/UnpackPropertySelectorsShorthandMap unpackPropertySelectorsShorthandMap}
	 * in `@divi/module` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array $propertySelectors             The property selectors that you want to unpack.
	 *     @type array $propertySelectorsShorthandMap This is the map of shorthand properties to their longhand properties.
	 * }
	 *
	 * @return array
	 */
	public static function unpack_property_selectors_shorthand_map( array $args ): array {
		$property_selectors               = $args['propertySelectors'];
		$property_selectors_shorthand_map = $args['propertySelectorsShorthandMap'];

		// No need to continue if empty `propertySelectors` is given.
		if ( ! $property_selectors ) {
			return $property_selectors;
		}

		// No need to continue if empty `propertySelectorsShorthandMap` is given.
		if ( ! $property_selectors_shorthand_map ) {
			return $property_selectors;
		}

		// Passed property selector and unpacked property selector based from shorthand map
		// should be populated differently so later both can be merged in order to ensure passed
		// property selector has higher priority than unpacked selector.
		$specific_selectors = [];
		$unpacked_selectors = [];

		// Extracted shorthand css properties based on keys of shorthand map.
		$shorthand_css_properties = array_keys( $property_selectors_shorthand_map );

		foreach ( $property_selectors as $breakpoint => $state_value ) {
			foreach ( $state_value as $attr_state => $property_selector ) {
				foreach ( $property_selector as $property_name => $selector ) {
					if ( in_array( $property_name, $shorthand_css_properties, true ) ) {
						if ( isset( $property_selectors_shorthand_map[ $property_name ] ) && is_array( $property_selectors_shorthand_map[ $property_name ] ) ) {
							foreach ( $property_selectors_shorthand_map[ $property_name ] as $shorthand_property_name ) {
								if ( ! isset( $unpacked_selectors[ $breakpoint ] ) ) {
									$unpacked_selectors[ $breakpoint ] = [];
								}

								if ( ! isset( $unpacked_selectors[ $breakpoint ][ $attr_state ] ) ) {
									$unpacked_selectors[ $breakpoint ][ $attr_state ] = [];
								}

								$unpacked_selectors[ $breakpoint ][ $attr_state ][ $shorthand_property_name ] = $selector;
							}
						}
					} else {
						if ( ! isset( $specific_selectors[ $breakpoint ] ) ) {
							$specific_selectors[ $breakpoint ] = [];
						}

						if ( ! isset( $specific_selectors[ $breakpoint ][ $attr_state ] ) ) {
							$specific_selectors[ $breakpoint ][ $attr_state ] = [];
						}

						if ( ! isset( $specific_selectors[ $breakpoint ][ $attr_state ][ $property_name ] ) ) {
							$specific_selectors[ $breakpoint ][ $attr_state ][ $property_name ] = [];
						}

						$specific_selectors[ $breakpoint ][ $attr_state ][ $property_name ] = $selector;
					}
				}
			}
		}

		return array_replace_recursive( $unpacked_selectors, $specific_selectors );
	}
}
