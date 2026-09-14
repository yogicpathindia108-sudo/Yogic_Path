<?php
/**
 * Frontend Style
 *
 * @package Divi
 *
 * @since ??
 */

namespace ET\Builder\FrontEnd\Module;

use ET\Builder\FrontEnd\Assets\CriticalCSS;
use ET\Builder\FrontEnd\Assets\StaticCSS;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Framework\Utility\LoopExcerptRenderContext;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\StyleLibrary\Utils\GradientUtils;
use ET\Builder\Packages\StyleLibrary\Utils\Utils;
use ET_Theme_Builder_Layout;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Frontend Style class.
 *
 * This class is used to store and enqueue module styles.
 */
class Style {

	/**
	 * Media queries key value pairs. {@see get_media_quries()}
	 *
	 * @since ??
	 *
	 * @var array
	 */
	private static $_media_queries = [];

	/**
	 * Styles data holder.
	 *
	 * This static property is used to store an array of styles for
	 * different parts of the Divi. Each style is represented
	 * by an associative array, using a style key determined by {@see self::get_style_key()} and
	 * the 'group' parameter in the {@see self::add()} function.
	 * The styles are structured by a group(string), key(int/string), and other details for each style item.
	 *
	 * Each style item is an array including:
	 * - The media query under which this style item falls or 'general' if it's not specific to any.
	 * - The CSS selector to which these styles apply.
	 * - The CSS declarations which are the styles to be applied.
	 * - The priority of the style, which indicates its order of application.
	 * - An optional 'critical' key indicating if the style is critical (above the fold).
	 *
	 * The $_styles property holds the styles until they are rendered using {@see self::render()}.
	 *
	 * Please note, modifying $_styles directly could lead to inconsistent behavior
	 * and it is recommended to use the provided 'add()' method instead.
	 *
	 * @since ??
	 *
	 * @var array An array of styles, each represented by an associative array with keys for the 'name' and 'value'
	 *      properties.
	 */
	private static $_styles = [];

	/**
	 * Holds an array of already processed preset style selector.
	 *
	 * This static property is utilized in the context of preset style processing.
	 *
	 * It basically acts as a cache mechanism so that once a preset selector has been successfully processed,
	 * the system would not re-process it every time, so that the same processing logic is not repetitively performed.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	private static $_preset_selector_processed = [];

	/**
	 * Cache for ancestor IDs to avoid repeated parent chain walks.
	 *
	 * @since ??
	 *
	 * @var array<string, array>
	 */
	private static $_ancestor_ids_cache = [];

	/**
	 * Cache for style key to avoid repeated function calls.
	 * The style key remains constant only within a single render context.
	 *
	 * @since ??
	 *
	 * @var int|string|null
	 */
	private static $_style_key_cache = null;

	/**
	 * Signature of the render context used for style key cache.
	 *
	 * @since ??
	 *
	 * @var string|null
	 */
	private static $_style_key_cache_context = null;

	/**
	 * Build style key cache context signature.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	private static function _get_style_key_cache_context_signature(): string {
		if ( true === ET_Theme_Builder_Layout::is_theme_builder_layout() ) {
			$theme_builder_layout_id = ET_Theme_Builder_Layout::get_theme_builder_layout_id();
			if ( ! empty( $theme_builder_layout_id ) ) {
				return 'tb:' . (string) $theme_builder_layout_id;
			}

			return 'tb';
		}

		if ( true === StaticCSS::is_wp_editor_template() ) {
			$wp_editor_template_id = StaticCSS::get_wp_editor_template_id();
			if ( ! empty( $wp_editor_template_id ) ) {
				return 'wp:' . (string) $wp_editor_template_id;
			}

			return 'wp';
		}

		return 'post';
	}

	/**
	 * Counter for generating unique keys without calling uniqid().
	 * Used during style rendering to avoid expensive uniqid() calls in hot loops.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	private static $_unique_counter = 0;

	/**
	 * Normalize transition declarations in a CSS declaration string.
	 *
	 * - Merge duplicate `transition-property` declarations by unioning properties.
	 * - Collapse duplicate transition longhands (`transition-duration`,
	 *   `transition-timing-function`, `transition-delay`) to a single declaration,
	 *   preserving the last-defined value (CSS cascade behavior).
	 *
	 * @since ??
	 *
	 * @param string $declaration CSS declaration string.
	 *
	 * @return string
	 */
	private static function _normalize_transition_declarations( string $declaration ): string {
		// Fast path: skip regex work when there are no transition declarations.
		if ( false === stripos( $declaration, 'transition-' ) ) {
			return $declaration;
		}

		// 1. Merge transition-property values.
		$first_transition_property_pos     = stripos( $declaration, 'transition-property' );
		$has_duplicate_transition_property = false !== $first_transition_property_pos
			&& false !== stripos( $declaration, 'transition-property', $first_transition_property_pos + strlen( 'transition-property' ) );

		$transition_property_matches = [];
		if ( $has_duplicate_transition_property ) {
			// Regex test: https://regex101.com/r/7OhI9S/1.
			preg_match_all( '/transition-property\s*:\s*([^;]+)\s*;?/i', $declaration, $transition_property_matches );
		}

		$transition_property_values = $transition_property_matches[1] ?? [];
		if ( count( $transition_property_values ) > 1 ) {
			$has_important                = false;
			$merged_transition_properties = [];
			$seen_transition_properties   = [];

			foreach ( $transition_property_values as $transition_value ) {
				if ( false !== stripos( $transition_value, '!important' ) ) {
					$has_important = true;
				}

				$transition_value = preg_replace( '/\s*!important\s*/i', '', $transition_value );
				$split_values     = explode( ',', $transition_value );

				foreach ( $split_values as $split_value ) {
					$split_value = trim( $split_value );
					if ( '' === $split_value || isset( $seen_transition_properties[ $split_value ] ) ) {
						continue;
					}

					$seen_transition_properties[ $split_value ] = true;
					$merged_transition_properties[]             = $split_value;
				}
			}

			$merged_transition_property_declaration = 'transition-property: ' . implode( ',', $merged_transition_properties ) . ( $has_important ? ' !important' : '' ) . ';';

			// Regex test: https://regex101.com/r/7OhI9S/1.
			$declaration = preg_replace(
				'/transition-property\s*:\s*[^;]+;?/i',
				' ',
				$declaration
			);
			$declaration = trim( preg_replace( '/\s+/', ' ', $declaration ) );
			$declaration = $merged_transition_property_declaration . ( $declaration ? ' ' . $declaration : '' );
		}

		// 2. Collapse duplicate transition longhands to the last value.
		$transition_longhands = [
			'transition-duration',
			'transition-timing-function',
			'transition-delay',
		];

		foreach ( $transition_longhands as $transition_longhand ) {
			$first_longhand_pos = stripos( $declaration, $transition_longhand );
			if ( false === $first_longhand_pos
				|| false === stripos( $declaration, $transition_longhand, $first_longhand_pos + strlen( $transition_longhand ) )
			) {
				continue;
			}

			$longhand_matches = [];
			// Regex test: https://regex101.com/r/lSQhmb/1.
			preg_match_all(
				'/' . preg_quote( $transition_longhand, '/' ) . '\s*:\s*([^;]+)\s*;?/i',
				$declaration,
				$longhand_matches
			);

			$longhand_values = $longhand_matches[1] ?? [];
			if ( count( $longhand_values ) > 1 ) {
				$last_value = trim( end( $longhand_values ) );

				// Regex test: https://regex101.com/r/nfOo7X/1.
				$declaration = preg_replace(
					'/' . preg_quote( $transition_longhand, '/' ) . '\s*:\s*[^;]+;?/i',
					' ',
					$declaration
				);
				$declaration = trim( preg_replace( '/\s+/', ' ', $declaration ) );
				$declaration = $transition_longhand . ': ' . $last_value . ';' . ( $declaration ? ' ' . $declaration : '' );
			}
		}

		return $declaration;
	}

	/**
	 * Detected module types for inner content rendering.
	 *
	 * Stores module types detected in blog post content before rendering.
	 * Used to ensure default preset styles are generated for all detected module types,
	 * even if individual module instances use explicit presets.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	private static $_detected_module_types_for_inner_content = [];

	/**
	 * Flag indicating if inner content is being rendered in Theme Builder context.
	 *
	 * @since ??
	 *
	 * @var bool
	 */
	private static $_is_theme_builder_context_for_inner_content = false;

	/**
	 * Check if a preset selector has been processed.
	 *
	 * @since ??
	 *
	 * @param string $preset_selector_classname The classname of the preset selector to check.
	 *
	 * @return bool True if the preset selector has already been processed, false otherwise.
	 */
	public static function is_preset_selector_processed( string $preset_selector_classname ): bool {
		if ( ! isset( self::$_preset_selector_processed[ $preset_selector_classname ] ) ) {
			// Track processed item to the static variable.
			self::$_preset_selector_processed[ $preset_selector_classname ] = $preset_selector_classname;

			return false;
		}

		return true;
	}

	/**
	 * Retrieve Post ID from 1 of 3 sources depending on which exists:
	 * - get_the_ID()
	 * - $_GET['post']
	 * - $_POST['et_post_id']
	 *
	 * @since ??
	 *
	 * @return int|bool
	 */
	public static function get_current_post_id_reverse() {
		// phpcs:disable WordPress.Security.NonceVerification -- This function does not change any state, and is therefore not susceptible to CSRF.
		$post_id = et_core_get_main_post_id();

		// try to get post id from get_post_ID().
		if ( false !== $post_id ) {
			return $post_id;
		}

		if ( wp_doing_ajax() ) {
			// get the post ID if loading data for VB.
			return isset( $_POST['et_post_id'] ) ? absint( $_POST['et_post_id'] ) : false;
		}

		// fallback to $_GET['post'] to cover the BB data loading.
		return isset( $_GET['post'] ) ? absint( $_GET['post'] ) : false;
		// phpcs:enable
	}

	/**
	 * Get the current TB layout ID if we are rendering one or the current post ID instead.
	 *
	 * @since ??
	 *
	 * @return integer
	 */
	public static function get_layout_id() {
		// TB Layout ID.
		$layout_id = ET_Theme_Builder_Layout::get_theme_builder_layout_id();
		if ( $layout_id ) {
			return $layout_id;
		}

		// WP Template ID.
		$template_id = StaticCSS::get_wp_editor_template_id();
		if ( $template_id ) {
			return $template_id;
		}

		// Post ID by default.
		return self::get_current_post_id_reverse();
	}

	/**
	 * Get style key.
	 *
	 * @return int|string
	 */
	public static function get_style_key() {
		$current_context_signature = self::_get_style_key_cache_context_signature();

		// Cache the style key per render context (post/TB/WP template).
		// A request can legitimately switch contexts while rendering and each context needs its own key.
		if (
			null !== self::$_style_key_cache
			&& $current_context_signature === self::$_style_key_cache_context
		) {
			return self::$_style_key_cache;
		}

		if ( ET_Theme_Builder_Layout::is_theme_builder_layout() || StaticCSS::is_wp_editor_template() ) {
			self::$_style_key_cache = self::get_layout_id();
		} else {
			// Use a generic key in all other cases.
			// For example, injector plugins that repeat a layout in a loop
			// need to group that CSS under the same key.
			self::$_style_key_cache = 'post';
		}

		self::$_style_key_cache_context = $current_context_signature;

		return self::$_style_key_cache;
	}

	/**
	 * Return style array from {@see self::$internal_modules_styles} or {@see self::$styles}.
	 *
	 * @param string     $group Style Group.
	 * @param int|string $key   Style Key.
	 *
	 * @return array
	 */
	public static function get_style_array( string $group = 'module', $key = 0 ): array {
		$styles_raw = self::$_styles;

		if ( 0 === $key ) {
			$key = self::get_style_key();
		}

		return $styles_raw[ $key ][ $group ] ?? [];
	}

	/**
	 * Return media query from the media query name.
	 * E.g For max_width_767 media query name, this function return "@media only screen and ( max-width: 767px )".
	 *
	 * @since ??
	 *
	 * @param string $name Media query name e.g max_width_767, max_width_980.
	 *
	 * @return bool|mixed
	 */
	public static function get_media_query( string $name ) {
		if ( ! isset( self::$_media_queries[ $name ] ) ) {
			return false;
		}

		return self::$_media_queries[ $name ];
	}

	/**
	 * Return media query key value pairs.
	 *
	 * @since ??
	 *
	 * @param bool $for_js Whether media queries is for js ETBuilderBackend.et_builder_css_media_queries variable.
	 *
	 * @return array|mixed|void
	 */
	public static function get_media_quries( bool $for_js = false ) {
		$media_queries = [
			'min_width_1405' => '@media only screen and ( min-width: 1405px )',
			'1100_1405'      => '@media only screen and ( min-width: 1100px ) and ( max-width: 1405px)',
			'981_1405'       => '@media only screen and ( min-width: 981px ) and ( max-width: 1405px)',
			// phpcs:ignore Universal.Arrays.DuplicateArrayKey.Found -- Different media query ranges, key is intentional.
			'981_1100'       => '@media only screen and ( min-width: 981px ) and ( max-width: 1100px )',
			'min_width_981'  => '@media only screen and ( min-width: 981px )',
			'max_width_980'  => '@media only screen and ( max-width: 980px )',
			'768_980'        => '@media only screen and ( min-width: 768px ) and ( max-width: 980px )',
			'min_width_768'  => '@media only screen and ( min-width: 768px )',
			'max_width_767'  => '@media only screen and ( max-width: 767px )',
			'max_width_479'  => '@media only screen and ( max-width: 479px )',
		];

		$media_queries['mobile'] = $media_queries['max_width_767'];

		$media_queries = apply_filters( 'et_builder_media_queries', $media_queries );

		if ( 'for_js' === $for_js ) {
			$processed_queries = [];

			foreach ( $media_queries as $key => $value ) {
				$processed_queries[] = [ $key, $value ];
			}
		} else {
			$processed_queries = $media_queries;
		}

		return $processed_queries;
	}

	/**
	 * Set media queries key value pairs.
	 *
	 * @since ??
	 */
	public static function set_media_queries() {
		self::$_media_queries = self::get_media_quries();
	}

	/**
	 * Add a new style.
	 *
	 * Adds a new style to the CSS styles data. The style will be enqueued by `self::enqueue()`.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments for adding a style.
	 *
	 *     @type string    $id              The ID of the style.
	 *     @type int       $orderIndex      The order index of the style.
	 *     @type array     $styles          Optional. An array of CSS styles for the style. Default `[]`.
	 *     @type object    $storeInstance   Optional. The instance of the store. Default `null`.
	 *     @type int       $priority        Optional. The priority of the style. Default `10`.
	 *     @type string    $group           Optional. The group of the style. Default `module`.
	 * }
	 *
	 * @return void
	 *
	 * @example
	 * ```php
	 * self::add( [
	 *     'id'          => 'style-1',
	 *     'styles'      => ['color' => '#000', 'font-size' => '16px'],
	 *     'storeInstance' => $store,
	 *     'orderIndex'  => 1,
	 *     'priority'    => 20,
	 * ] );
	 * ```
	 */
	/**
	 * Merge free-form CSS (empty selector) declaration strings.
	 *
	 * Appends with a space separator (no `;` between full rules), matching {@see self::add()}.
	 *
	 * @param string $existing            Prior declaration text.
	 * @param string $declaration         Incoming declaration text.
	 * @param bool   $append_when_identical When true, always append with a space (legacy {@see self::add()} behavior).
	 *                                      When false, return `$existing` unchanged if both strings are identical.
	 *
	 * @return string Combined declaration string.
	 */
	private static function _merge_free_form_declaration_strings( string $existing, string $declaration, bool $append_when_identical ): string {
		if ( ! $append_when_identical && $declaration === $existing ) {
			return $existing;
		}

		return sprintf(
			'%1$s %2$s',
			$existing,
			$declaration
		);
	}

	/**
	 * Merge two declaration strings for the same selector (non-empty), matching {@see self::add()}.
	 *
	 * @param string $existing_declaration Prior declaration.
	 * @param string $declaration         Incoming declaration (must differ from existing when caller requires an update).
	 *
	 * @return string Combined declarations with correct `;` separation.
	 */
	private static function _merge_keyed_selector_declaration_strings( string $existing_declaration, string $declaration ): string {
		$last_char = substr( $existing_declaration, -1 );

		if ( ';' === $last_char ) {
			return $existing_declaration . ' ' . $declaration;
		}

		$existing_trimmed = rtrim( $existing_declaration, '; ' );
		$new_trimmed      = rtrim( $declaration, '; ' );

		return $existing_trimmed . '; ' . $new_trimmed;
	}

	/**
	 * Add style declarations to the internal style store.
	 *
	 * @param array $args Style payload and metadata.
	 *
	 * @return void
	 */
	public static function add( array $args ): void {
		// Do not register module CSS for nested renders that exist only to build loop excerpt text (#48251).
		if ( LoopExcerptRenderContext::is_foreign_post_loop_excerpt_render() ) {
			return;
		}

		$id             = $args['id'] ?? null;
		$styles         = $args['styles'] ?? [];
		$store_instance = $args['storeInstance'] ?? null;

		// Try to get preset priority from:
		// 1. Explicit 'priority' parameter (highest priority).
		// 2. Explicit 'presetPriority' parameter.
		// 3. ModuleElements static current preset priority.
		// 4. Default to 10.
		$priority = $args['priority'] ?? $args['presetPriority'] ?? null;

		if ( null === $priority ) {
			$preset_priority_from_static = ModuleElements::get_current_preset_priority();
			if ( null !== $preset_priority_from_static ) {
				$priority = $preset_priority_from_static;
			}
		}

		$priority    = $priority ?? 10;
		$group       = $args['group'] ?? self::get_group_style();
		$order_index = $args['orderIndex'] ?? null;

		// Warn when $styles is string.
		if ( is_string( $styles ) ) {
			et_error( "You're Doing It Wrong! Provided styles must be in array format." );
		}

		// Remove empty styles.
		$styles = is_array( $styles ) ? array_filter( $styles ) : [];

		// Bail when there are no styles found.
		if ( ! $styles ) {
			return;
		}

		// Cache ancestor IDs to avoid repeated parent chain walks.
		$cache_key = ( null !== $id && is_string( $id ) && null !== $store_instance )
			? $id . '|' . $store_instance
			: null;

		if ( null !== $cache_key && isset( self::$_ancestor_ids_cache[ $cache_key ] ) ) {
			$parent_ids = self::$_ancestor_ids_cache[ $cache_key ];
		} else {
			$parent_ids = ( null !== $id && is_string( $id ) ) ? BlockParserStore::get_ancestor_ids(
				$id,
				$store_instance
			) : [];

			if ( null !== $cache_key ) {
				self::$_ancestor_ids_cache[ $cache_key ] = $parent_ids;
			}
		}

		// We're padding block index and parent counts into priority to sort items by priority and parents.
		$priority = (int) ( $priority . $order_index . count( $parent_ids ) );

		/*
		 * When critical CSS should be generated, styles are split into two:
		 * - Above the fold styles is marked as `critical`.
		 * - Below the fold styles doesn't have `critical` mark.
		 *
		 * When critical CSS should not be generated, all styles doesn't have the`critical` mark.
		 */
		if ( CriticalCSS::should_generate_critical_css() ) {
			if ( CriticalCSS::is_above_the_fold() ) {
				$style_type = 'critical';
			} else {
				$style_type = 'default';
			}
		} else {
			$style_type = 'default';
		}

		$style_key        = self::get_style_key();
		$styles_flattened = self::get_style_array( $group );

		foreach ( $styles as $item ) {
			// Ignore string data.
			if ( is_string( $item ) ) {
				continue;
			}

			// Remove empty styles.
			$item_styles = array_filter( $item ) ?? [];

			if ( ! $item_styles ) {
				continue;
			}

			foreach ( $item_styles as $item_style ) {
				// Skip if $item_style is empty or not an array.
				if ( ! $item_style || ! is_array( $item_style ) ) {
					continue;
				}

				$media_query = ! empty( $item_style['atRules'] ) ? $item_style['atRules'] : 'general';
				$selector    = $item_style['selector'];
				$declaration = $item_style['declaration'];

				// Special handling for free-form CSS (empty selector).
				// Free-form CSS contains complete rules and should not be concatenated with semicolons.
				if ( '' === $selector && isset( $styles_flattened[ $media_query ][ $selector ]['declaration'] ) ) {
					$existing_declaration = $styles_flattened[ $media_query ][ $selector ]['declaration'];

					// Append free-form CSS with space separator (no semicolons between complete rules).
					$styles_flattened[ $media_query ][ $selector ]['declaration'] = self::_merge_free_form_declaration_strings(
						$existing_declaration,
						$declaration,
						true
					);

					$styles_flattened[ $media_query ][ $selector ]['priority'] = $priority;

					if ( 'critical' === $style_type ) {
						$styles_flattened[ $media_query ][ $selector ]['critical'] = 1;
					}

					continue;
				}

				// Prepare styles for internal content. Used in Blog/Slider modules if they contain Divi modules.
				if ( isset( $styles_flattened[ $media_query ][ $selector ]['declaration'] ) ) {
					$existing_declaration = $styles_flattened[ $media_query ][ $selector ]['declaration'];

					if ( $declaration !== $existing_declaration ) {
						// Ensure proper semicolon separation between CSS declarations.
						$styles_flattened[ $media_query ][ $selector ]['declaration'] = self::_merge_keyed_selector_declaration_strings(
							$existing_declaration,
							$declaration
						);
						$styles_flattened[ $media_query ][ $selector ]['declaration'] = self::_normalize_transition_declarations(
							$styles_flattened[ $media_query ][ $selector ]['declaration']
						);
					}
				} else {
					$styles_flattened[ $media_query ][ $selector ]['declaration'] = $declaration;
				}

				$styles_flattened[ $media_query ][ $selector ]['priority'] = $priority;

				if ( 'critical' === $style_type ) {
					$styles_flattened[ $media_query ][ $selector ]['critical'] = 1;
				}
			}
		}

		// Store styles without sorting. Media query sorting is deferred to render time for better performance.
		// This avoids expensive regex matching and sorting operations on every Style::add() call.
		self::$_styles[ $style_key ][ $group ] = $styles_flattened;
	}

	/**
	 * Merge module style data from multiple Theme Builder layout passes into one structure.
	 *
	 * Module order classes (e.g. `.et_pb_blurb_0`) reset per layout, so identical rules can be
	 * collected under separate style keys. Outputting each layout's module styles separately then
	 * duplicates identical selector/declaration pairs in unified CSS. This merge drops exact
	 * duplicates while preserving cascade order and concatenating conflicting declarations on the
	 * same selector like {@see self::add()}.
	 *
	 * @since ??
	 *
	 * @param array $into Existing styles data keyed by media query then selector.
	 * @param array $from Styles data to merge in (same shape as {@see self::get_style_array()}).
	 *
	 * @return array Merged styles data.
	 */
	public static function merge_module_styles_data( array $into, array $from ): array {
		foreach ( $from as $media_query => $selectors ) {
			if ( ! is_array( $selectors ) ) {
				continue;
			}

			if ( ! isset( $into[ $media_query ] ) ) {
				$into[ $media_query ] = [];
			}

			foreach ( $selectors as $selector => $settings ) {
				if ( ! is_array( $settings ) ) {
					continue;
				}

				$declaration = $settings['declaration'] ?? '';

				// Free-form CSS (empty selector): append full rule strings.
				if ( '' === $selector && isset( $into[ $media_query ][ $selector ]['declaration'] ) ) {
					$existing_declaration = $into[ $media_query ][ $selector ]['declaration'];

					if ( $declaration !== $existing_declaration ) {
						$into[ $media_query ][ $selector ]['declaration'] = self::_merge_free_form_declaration_strings(
							$existing_declaration,
							$declaration,
							false
						);
					}

					if ( ! empty( $settings['priority'] ) ) {
						$into[ $media_query ][ $selector ]['priority'] = $settings['priority'];
					}

					if ( ! empty( $settings['critical'] ) ) {
						$into[ $media_query ][ $selector ]['critical'] = 1;
					}

					continue;
				}

				if ( '' === $selector ) {
					$into[ $media_query ][ $selector ] = $settings;
					continue;
				}

				if ( ! isset( $into[ $media_query ][ $selector ] ) ) {
					$into[ $media_query ][ $selector ] = $settings;
					continue;
				}

				$existing_declaration = $into[ $media_query ][ $selector ]['declaration'] ?? '';

				if ( $declaration === $existing_declaration ) {
					continue;
				}

				$into[ $media_query ][ $selector ]['declaration'] = self::_merge_keyed_selector_declaration_strings(
					$existing_declaration,
					$declaration
				);

				if ( ! empty( $settings['priority'] ) ) {
					$into[ $media_query ][ $selector ]['priority'] = $settings['priority'];
				}

				if ( ! empty( $settings['critical'] ) ) {
					$into[ $media_query ][ $selector ]['critical'] = 1;
				}
			}
		}

		return $into;
	}

	/**
	 * Sort an array of items by their priority.
	 *
	 * This function takes an array of items. The function then sorts the array of priorities in ascending
	 * order. If two items have the same priority, they will be sorted by their original index
	 * within the input array.
	 *
	 * @since ??
	 *
	 * @param array $collection The array to be sorted. Each child item in the array should have a 'priority' key.
	 *
	 * @return array An array of items sorted by priority. The array will maintain the same keys as the input array.
	 *
	 * @example
	 * ```php
	 * $collection = [
	 *     'selector1' => ['priority' => 5, 'item' => 'A'],
	 *     'selector2' => ['priority' => 10, 'item' => 'B'],
	 *     'selector3' => ['priority' => 5, 'item' => 'C'],
	 * ];
	 *
	 * $sortedCollection = sort_by_priority($collection);
	 *
	 * // $sortedCollection will be:
	 * // [
	 * //     'selector1' => ['priority' => 5, 'item' => 'A'],
	 * //     'selector3' => ['priority' => 5, 'item' => 'C'],
	 * //     'selector2' => ['priority' => 10, 'item' => 'B'],
	 * // ]
	 * ```
	 */
	public static function sort_by_priority( array &$collection ): array {
		$keys_order = array_flip( array_keys( $collection ) );

		uksort(
			$collection,
			function ( $a, $b ) use ( $keys_order, $collection ) {
				if ( $collection[ $a ]['priority'] === $collection[ $b ]['priority'] ) {
					return $keys_order[ $a ] - $keys_order[ $b ];
				}

				return $collection[ $a ]['priority'] - $collection[ $b ]['priority'];
			}
		);

		unset( $keys_order );

		return $collection;
	}

	/**
	 * Enqueue styles from the Style class.
	 *
	 * This function retrieves the styles data from the Style class and enqueues the styles on the
	 * page. It concatenates the styles into a single string and echoes them within `style` tags.
	 * The styles are sanitized and escaped before being output to the page.
	 *
	 * @since ??
	 *
	 * @param string $style_type The type of styles to enqueue.
	 * @param string $group The group of styles to enqueue. Default is 'module'.
	 * @param string $key   Optional. The element id.
	 *
	 * @return void
	 *
	 * @example: Enqueue styles
	 * ```php
	 * MyStyles::enqueue();
	 * ```
	 */
	public static function enqueue( string $style_type = 'default', string $group = 'module', $key = 0 ): void {
		$styles_output = self::render( $style_type, $group, $key );

		if ( $styles_output ) {
			echo '<style>';
			echo et_core_esc_previously( $styles_output );
			echo '</style>';
		}
	}

	/**
	 * Render sorted styles as string.
	 *
	 * @since ??
	 *
	 * @param string $style_type The type of styles to enqueue.
	 * @param string $group The group of styles to enqueue. Default is 'module'.
	 * @param string $key        Optional. The element id.
	 *
	 * @example: Render styles
	 * ```php
	 * MyStyles::render();
	 * ```
	 */
	public static function render( string $style_type = 'default', string $group = 'module', $key = 0 ): string {
		$styles_data = self::get_style_array( $group, $key );
		return self::render_by_styles_data( $styles_data, $style_type );
	}

	/**
	 * Render styles data as CSS string.
	 *
	 * Processes an array of styles data, sorts them by media queries and priority,
	 * merges styles with identical declarations, and filters by style type (default or critical).
	 * Returns the rendered CSS as a string with proper media query wrapping.
	 *
	 * @since ??
	 *
	 * @param array  $styles_data Array of styles data organized by media queries.
	 *                            Each style item contains selector, declaration, priority, and optional critical flag.
	 * @param string $style_type  The type of styles to render. Default is 'default'.
	 *                            Use 'critical' to render only critical styles.
	 *
	 * @return string The rendered CSS string, or empty string if no styles data provided.
	 */
	public static function render_by_styles_data( array $styles_data, string $style_type = 'default' ): string {
		// Bail, if there are np data to process.
		if ( ! $styles_data ) {
			return '';
		}

		// Reset unique counter at the start of each render.
		self::$_unique_counter = 0;

		$critical = 'critical' === $style_type;

		// Sometimes module will have set setting only on desktop and mobile while tablet being turned off.
		// In that case there will be only two @-rules inside the $styles_data (desktop and mobile).
		// If one of the next attribute has tablet styles, they will be added after mobile styles in
		// $styles_data, effectively enabling tablet styles to override the mobile ones in FE.
		// For this reason we need to make sure @-rules are sorted properly by priority.
		// Preprocess media queries for sorting priorities.
		$media_priorities = [];
		$media_query_keys = array_keys( $styles_data );

		foreach ( $media_query_keys as $key ) {
			if ( 'general' === $key ) {
				// Ensure 'general' styles appear first.
				$media_priorities[ $key ] = -PHP_INT_MAX;
			} else {
				// Match media queries min-width and max-width values.
				// Fixed pattern to account for optional spaces around parentheses.
				// https://regex101.com/r/mL9v1T/1.
				$pattern = '/@media only screen and \(\s*(min|max)-width:\s*(\d+)px\s*\)/';
				if ( preg_match( $pattern, $key, $matches ) ) {
					$type  = $matches[1];
					$value = (int) $matches[2];

					// Return a calculated priority: max-width sorted descending, min-width ascending.
					// Use signed magnitudes only — adding PHP_INT_MAX collapses nearby min-width values as floats.
					$media_priorities[ $key ] = 'max' === $type ? -$value : $value;
				} else {
					// Default for unknown media queries.
					$media_priorities[ $key ] = PHP_INT_MAX;
				}
			}
		}

		// Sort the styles by their media query priorities.
		uksort(
			$styles_data,
			function ( $a, $b ) use ( $media_priorities ) {
				return ( $media_priorities[ $a ] ?? PHP_INT_MAX ) <=> ( $media_priorities[ $b ] ?? PHP_INT_MAX );
			}
		);

		$styles_by_media_queries = $styles_data;

		$output = '';

		global $et_user_fonts_queue;

		// phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
		// TODO feat(D5, FE Rendering): Need to rewrite et_builder_enqueue_user_fonts in D5.
		if ( ! empty( $et_user_fonts_queue ) ) {
			$output .= et_builder_enqueue_user_fonts( $et_user_fonts_queue );
		}

		foreach ( $styles_by_media_queries as $media_query => $styles ) {
			// Skip wrong values which were added during the array sorting.
			if ( ! is_array( $styles ) ) {
				continue;
			}

			$media_query_output    = '';
			$wrap_into_media_query = 'general' !== $media_query;

			// Sort styles by priority.
			self::sort_by_priority( $styles );

			// Merge styles with identical declarations.
			$merged_declarations = [];
			foreach ( $styles as $selector => $settings ) {

				if ( false === $critical && isset( $settings['critical'] ) ) {
					continue;
				} elseif ( true === $critical && empty( $settings['critical'] ) ) {
					continue;
				}

				// Use the actual declaration as the key for grouping.
				// This is faster than md5() and has zero collision risk since we're using the actual value.
				// We can safely use declarations as array keys in PHP.
				$this_declaration = $settings['declaration'];

				// Optimize selector checks: check for empty selector first (most common),
				// then check all conditions in a single pass to avoid multiple strpos() calls.
				$is_special_selector = '' === $selector;
				if ( ! $is_special_selector ) {
					// Only do string searches if selector is not empty.
					// Combine checks to minimize function calls.
					$selector_lower      = $selector; // Keep original case for comparison.
					$is_special_selector = str_contains( $selector, ':-' )
						|| str_contains( $selector, '@keyframes' )
						|| str_contains( $selector, 'preset--' );
				}

				// We want to skip combining anything with psuedo selectors or keyframes or free-form-css (which has
				// empty selector) or preset selectors.
				if ( $is_special_selector ) {
					// Use fast counter to create unique keys for special selectors.
					// These won't be merged, so we need guaranteed unique keys.
					$unique_key                         = 'special_' . ( ++self::$_unique_counter );
					$merged_declarations[ $unique_key ] = [
						'declaration' => $settings['declaration'],
						'selector'    => $selector,
					];

					if ( ! empty( $settings['priority'] ) ) {
						$merged_declarations[ $unique_key ]['priority'] = $settings['priority'];
					}

					continue;
				}

				if ( empty( $merged_declarations[ $this_declaration ] ) ) {
					$merged_declarations[ $this_declaration ] = [
						'selector' => '',
						'priority' => '',
					];
				}

				$new_selector = ! empty( $merged_declarations[ $this_declaration ]['selector'] )
					? $merged_declarations[ $this_declaration ]['selector'] . ', ' . $selector
					: $selector;

				$merged_declarations[ $this_declaration ] = [
					'declaration' => $settings['declaration'],
					'selector'    => $new_selector,
				];

				if ( ! empty( $settings['priority'] ) ) {
					$merged_declarations[ $this_declaration ]['priority'] = $settings['priority'];
				}
			}

			$styles_index = 0;

			// Get each rule in a media query.
			foreach ( $merged_declarations as $settings ) {
				// Build prefix once (newline + optional tab).
				$prefix = ( 0 === $styles_index ) ? '' : "\n";
				if ( $wrap_into_media_query ) {
					$prefix .= "\t";
				}

				if ( empty( $settings['selector'] ) ) {
					// If the selector is empty, just append the declaration directly without brackets.
					// This is needed for free-form-css output.
					// Direct concatenation is faster than sprintf for simple cases.
					$media_query_output .= $prefix . $settings['declaration'];
				} else {
					// If the selector is not empty, use direct concatenation with brackets.
					$media_query_output .= $prefix . $settings['selector'] . ' {' . $settings['declaration'] . '}';
				}

				++$styles_index;
			}

			// All css rules that don't use media queries are assigned to the "general" key.
			// Wrap all non-general settings into media query.
			if ( $wrap_into_media_query && '' !== $media_query_output ) {
				// Direct concatenation is faster than sprintf.
				$media_query_output = "\n\n" . $media_query . ' {' . $media_query_output . "\n}";
			}

			$output .= $media_query_output;
		}

		return $output;
	}

	/**
	 * Reset styles data.
	 *
	 * Resets the styles data to an empty array `[]`.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function reset() {
		self::$_styles                                     = [];
		self::$_media_queries                              = [];
		self::$_preset_selector_processed                  = [];
		self::$_ancestor_ids_cache                         = [];
		self::$_style_key_cache                            = null;
		self::$_style_key_cache_context                    = null;
		self::$_unique_counter                             = 0;
		self::$_detected_module_types_for_inner_content    = [];
		self::$_is_theme_builder_context_for_inner_content = false;
	}

	/**
	 * Clear styles for a specific key.
	 *
	 * Clears all collected styles for a given key. This is useful when rendering
	 * multiple independent content blocks (like blog posts) in a single request,
	 * where styles should not accumulate across blocks.
	 *
	 * @since ??
	 *
	 * @param int|string $key The style key to clear. Default 0 (uses current style key).
	 *
	 * @return void
	 */
	public static function clear_styles_for_key( $key = 0 ) {
		if ( 0 === $key || '' === $key ) {
			$key = self::get_style_key();
		}

		if ( isset( self::$_styles[ $key ] ) ) {
			unset( self::$_styles[ $key ] );
		}
	}

	/**
	 * Set detected module types for inner content rendering.
	 *
	 * Stores module types detected in blog post content before rendering.
	 * Used to ensure default preset styles are generated for all detected module types.
	 *
	 * @since ??
	 *
	 * @param array $module_types Array of module type names (e.g., ['divi/text', 'divi/button']).
	 *
	 * @return void
	 */
	public static function set_detected_module_types_for_inner_content( array $module_types ): void {
		self::$_detected_module_types_for_inner_content = $module_types;
	}

	/**
	 * Get detected module types for inner content rendering.
	 *
	 * @since ??
	 *
	 * @return array Array of module type names.
	 */
	public static function get_detected_module_types_for_inner_content(): array {
		return self::$_detected_module_types_for_inner_content;
	}

	/**
	 * Set Theme Builder context flag for inner content rendering.
	 *
	 * Stores whether inner content is being rendered in Theme Builder context.
	 * Used to determine if the `.et-db #et-boc .et-l` selector prefix should be applied.
	 *
	 * @since ??
	 *
	 * @param bool $is_theme_builder Whether rendering in Theme Builder context.
	 *
	 * @return void
	 */
	public static function set_is_theme_builder_context_for_inner_content( bool $is_theme_builder ): void {
		self::$_is_theme_builder_context_for_inner_content = $is_theme_builder;
	}

	/**
	 * Get Theme Builder context flag for inner content rendering.
	 *
	 * @since ??
	 *
	 * @return bool Whether rendering in Theme Builder context.
	 */
	public static function get_is_theme_builder_context_for_inner_content(): bool {
		return self::$_is_theme_builder_context_for_inner_content;
	}

	/**
	 * Provides styles for global colors.
	 *
	 * This function retrieves and prepares style data from global colors data. The values are then
	 * sanitized and escaped for secure use.
	 *
	 * It can be used in two ways:
	 * 1. Without any parameters - In this case, it returns styles for all available global colors.
	 * 2. With an array of $global_color_ids - It only returns styles for the colors associated with the provided ids.
	 *
	 * @since ??
	 *
	 * @param array $global_color_ids An optional parameter. When provided, the function will only include
	 *                                the styles for the global colors associated with these ids.
	 *                                If not provided or an empty array is passed, styles for all global colors
	 *                                will be included.
	 *
	 * @return string Returns a string containing the styles for the global colors.
	 */
	public static function get_global_colors_style( array $global_color_ids = [] ): string {
		$global_colors_style = '';
		$global_colors       = GlobalData::get_global_colors();

		// If specific global color IDs are provided, collect all their dependencies.
		if ( ! empty( $global_color_ids ) ) {
			$global_color_ids = GlobalData::collect_global_color_dependencies( $global_color_ids );
		}

		// Distinguish between no parameters passed (null) and empty array passed ([]).
		$include_all_colors = func_num_args() === 0;

		foreach ( $global_colors as $key => $value ) {
			if ( ! empty( $value['color'] ) ) {
				$color = $value['color'];

				// Process $variable syntax to handle nested global colors.
				$processed_color = Utils::resolve_dynamic_variable( $color );

				// When ids are provided, include the styles for the global colors associated with the ids.
				if ( ! empty( $global_color_ids ) && in_array( $key, $global_color_ids, true ) ) {
					$global_colors_style .= '--' . esc_html( $key ) . ': ' . esc_html( $processed_color ) . ';';
				}

				// If no parameters were passed (not even an empty array), include all global colors.
				if ( $include_all_colors ) {
					$global_colors_style .= '--' . esc_html( $key ) . ': ' . esc_html( $processed_color ) . ';';
				}
			}
		}

		if ( ! empty( $global_colors_style ) ) {
			$global_colors_style = ':root{' . $global_colors_style . '}';
		}

		return $global_colors_style;
	}

	/**
	 * The group of the style where it will be added.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private static $_group_style = 'module';

	/**
	 * Set the group of the style where it will be added.
	 *
	 * @since ??
	 *
	 * @param string $group The group of the style.
	 *
	 * @return void
	 */
	public static function set_group_style( string $group ): void {
		self::$_group_style = $group;
	}

	/**
	 * Get the group of the style where it will be added.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	public static function get_group_style(): string {
		return self::$_group_style;
	}

	/**
	 * Get global variable groups as CSS styles.
	 *
	 * This function retrieves active numeric, font, image, and gradient global variables from global data and formats
	 * them into CSS custom property declarations for `:root`.
	 *
	 * Image values are normalized to CSS image values and wrapped with `url(...)` only when they are not
	 * already wrapped.
	 *
	 * @since ??
	 *
	 * @param array $global_variable_ids Optional list of global variable IDs to include.
	 *                                   If omitted, all active numeric, font, image, and gradient variables are included.
	 *
	 * @return string The generated `:root{...}` CSS style block containing numeric, font, image, and gradient variables.
	 */
	public static function get_global_numeric_and_fonts_vars_style( array $global_variable_ids = [] ): string {
		$global_variables                = GlobalData::get_global_variables();
		$numeric_global_variables        = $global_variables['numbers'] ?? (object) [];
		$font_global_variables           = $global_variables['fonts'] ?? (object) [];
		$image_global_variables          = $global_variables['images'] ?? (object) [];
		$gradient_global_variables       = $global_variables['gradients'] ?? (object) [];
		$global_colors                   = null;
		$css_statements                  = '';
		$merged_global_variables         = array_merge(
			(array) $numeric_global_variables,
			(array) $font_global_variables,
			(array) $image_global_variables,
			(array) $gradient_global_variables
		);
		$font_global_variables_array     = (array) $font_global_variables;
		$image_global_variables_array    = (array) $image_global_variables;
		$gradient_global_variables_array = (array) $gradient_global_variables;
		$include_all_variables           = func_num_args() === 0;

		foreach ( $merged_global_variables as $key => $value ) {
			if ( is_array( $value ) ) {
				$id     = $value['id'] ?? '';
				$result = $value['value'];
				$status = $value['status'] ?? '';

				// When specific IDs are passed (detected in page content), include those
				// variables regardless of status — they are actively referenced in CSS.
				// When no IDs are passed (include_all_variables), only include active ones
				// to avoid outputting soft-deleted variables.
				if ( $include_all_variables && 'active' !== $status ) {
					continue;
				}

				// When IDs are passed, include only the variables associated with those IDs.
				if ( ! $include_all_variables && ! empty( $global_variable_ids ) && ! in_array( $id, $global_variable_ids, true ) ) {
					continue;
				}

				// If there are no ids provided, include the styles for all the global variables.
				if ( ! empty( $result ) ) {
					// Wrap font values in quotes to handle font names with spaces.
					// Check using both $key and $id to handle different data structures.
					$is_font = isset( $font_global_variables_array[ $key ] )
						|| isset( $font_global_variables_array[ $id ] )
						|| ( is_string( $id ) && ( str_contains( $id, '_font' ) || str_contains( $id, '-font' ) ) );

					if ( $is_font ) {
						$formatted_font_value = FontUtils::format_font_value_with_ms_version( $result );
						// If MS version was applied, value already includes quotes. Otherwise, add quotes.
						if ( str_contains( $formatted_font_value, " MS', '" ) ) {
							$result = $formatted_font_value;
						} else {
							$result = "'" . $formatted_font_value . "'";
						}
					} elseif ( isset( $image_global_variables_array[ $key ] ) || isset( $image_global_variables_array[ $id ] ) ) {
						$is_already_wrapped = 1 === preg_match( '/^\s*url\(/i', $result );
						$result             = $is_already_wrapped ? $result : "url({$result})";
					} elseif ( isset( $gradient_global_variables_array[ $key ] ) || isset( $gradient_global_variables_array[ $id ] ) ) {
						$resolved_gradient_settings = GlobalData::resolve_global_gradient_variable( $result );

						if ( is_array( $resolved_gradient_settings ) && ! empty( $resolved_gradient_settings ) ) {
							if ( is_array( $resolved_gradient_settings['stops'] ?? null ) ) {
								if ( null === $global_colors ) {
									$global_colors = GlobalData::get_global_colors();
								}

								foreach ( $resolved_gradient_settings['stops'] as &$stop ) {
									if ( isset( $stop['color'] ) && '' !== $stop['color'] ) {
										$stop['color'] = GlobalData::resolve_global_color_variable(
											$stop['color'],
											$global_colors
										);
									}
								}
								unset( $stop ); // Break reference.
							}

							$result = GradientUtils::gradient_style_declaration(
								[
									'type'            => $resolved_gradient_settings['type'] ?? 'linear',
									'direction'       => $resolved_gradient_settings['direction'] ?? '180deg',
									'directionRadial' => $resolved_gradient_settings['directionRadial'] ?? 'center',
									'stops'           => $resolved_gradient_settings['stops'] ?? [],
									'repeat'          => $resolved_gradient_settings['repeat'] ?? 'off',
									'length'          => $resolved_gradient_settings['length'] ?? '100%',
								]
							);
							$result = esc_html( $result );
						} else {
							continue;
						}
					} else {
						$result = esc_html( $result );
					}
					$css_property    = str_starts_with( $id, '--' ) ? esc_html( $id ) : '--' . esc_html( $id );
					$css_statements .= $css_property . ': ' . $result . ';';
				}
			}
		}

		if ( ! empty( $css_statements ) ) {
			$css_statements = ':root{' . $css_statements . '}';
		}

		return $css_statements;
	}
}
