<?php
/**
 * Static CSS
 *
 * @package Divi
 *
 * @since ??
 */

namespace ET\Builder\FrontEnd\Assets;

// phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames -- Reserved keywords used intentionally for clarity in utility functions.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\FrontEnd\Page;
use ET_GB_Block_Layout;
use ET\Builder\FrontEnd\Assets\StaticCSSElement;
use ET\Builder\FrontEnd\Assets\DynamicAssets;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use ET\Builder\Framework\Utility\ArrayUtility;
use ET\Builder\Packages\Module\Options\Loop\LoopUtils;
use ET\Builder\Packages\ModuleLibrary\PostFilter\PostFilterUtils;
use Feature\ContentRetriever\ET_Builder_Content_Retriever;
/**
 * Static CSS class.
 *
 * @since ??
 */
class StaticCSS implements DependencyInterface {
	/**
	 * `ET_Core_PageResource` class instance.
	 *
	 * @var \ET_Core_PageResource
	 */
	public static $styles_manager = null;

	/**
	 * `ET_Core_PageResource` class instance.
	 *
	 * @var \ET_Core_PageResource
	 */
	public static $deferred_styles_manager = null;

	/**
	 * A stack of the current active WP Editor template post type such as:
	 * - wp_template
	 * - wp_template_part
	 *
	 * @var array[]
	 */
	public static $wp_editor_template = [];

	/**
	 * Array of elements.
	 *
	 * @var StaticCSSElement[]
	 */
	private static $_elements = [];

	/**
	 * Load Static CSS class for rendering style as either inline or enqueued
	 *
	 * @since ??
	 */
	public function load(): void {
		// Use `wp` hook because $post should've been ready so `et_core_page_resource_get_the_ID()` returns correct post ID.
		add_action( 'wp', [ self::class, 'setup' ] );

		// Disable static CSS for pages with paginated loops to prevent stale CSS variable caching.
		add_filter( 'et_core_is_static_css_enabled', [ self::class, 'maybe_disable_static_css_for_paginated_loops' ], 10, 1 );

		// Disable static CSS for pages with post-filtered loops to prevent partial loop style caching.
		add_filter( 'et_core_is_static_css_enabled', [ self::class, 'maybe_disable_static_css_for_post_filtered_loops' ], 10, 1 );
	}

	/**
	 * Whether to force inline styles.
	 *
	 * @var bool
	 */
	public static $forced_inline_styles = false;

	/**
	 * Cached return values from {@see self::setup_styles_manager()}.
	 *
	 * @var array<string, array>|null
	 */
	private static $_setup_styles_manager_cache = null;

	/**
	 * Reset all static properties.
	 *
	 * @since ??
	 */
	public static function reset(): void {
		self::$styles_manager                 = null;
		self::$deferred_styles_manager        = null;
		self::$wp_editor_template             = [];
		self::$_elements                      = [];
		self::$forced_inline_styles           = false;
		self::$_setup_styles_manager_cache    = null;
	}

	/**
	 * Setup page resource.
	 *
	 * @since ??
	 *
	 * @internal This is the same mechanism that is used on ET_Builder_Element->__construct() to load page resource.
	 * @internal et_fb_is_enabled() is replaced by Conditions::is_vb_enabled()
	 */
	public static function setup() {
		if ( null === self::$styles_manager && ! is_admin() && ! Conditions::is_vb_enabled() ) {
			// This is needed to set only once.
			Style::set_media_queries();

			$result               = self::setup_styles_manager();
			self::$styles_manager = $result['manager'];

			if ( isset( $result['deferred'] ) ) {
				self::$deferred_styles_manager = $result['deferred'];
			}

			if ( $result['add_hooks'] ) {
				// Schedule callback to run in the footer so we can pass the module design styles to the page resource.
				add_action( 'wp_footer', [ self::class, 'enqueue_or_render_style' ], 19 );

				// Add filter for the resource data so we can prevent theme customizer css from being
				// included with the builder css inline on first-load (since its in the head already).
				add_filter( 'et_core_page_resource_get_data', [ self::class, 'filter_page_resource_data' ], 10, 3 );
			}

			add_action( 'wp_footer', [ self::class, 'maybe_force_inline_styles' ], 19 );
		}
	}

	/**
	 * Begin Divi Builder block output on WP Editor template.
	 *
	 * As identifier od Divi Builder block render template location and the template ID.
	 * Introduced to handle Divi Layout block render on WP Template outside Post Content.
	 * WP Editor templates:
	 * - wp_template
	 * - wp_template_part
	 *
	 * @since ??
	 *
	 * @param int $template_id Template post ID.
	 *
	 * @return void
	 */
	public static function begin_wp_editor_template( int $template_id ) {
		$type = get_post_type( $template_id );

		if ( ! et_builder_is_wp_editor_template_post_type( $type ) ) {
			$type = 'default';
		}

		self::$wp_editor_template[] = [
			'id'   => $template_id,
			'type' => $type,
		];
	}

	/**
	 * End Divi Builder block output on WP Editor template.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function end_wp_editor_template() {
		array_pop( self::$wp_editor_template );
	}

	/**
	 * Whether a module is rendered in WP Editor template or not.
	 *
	 * @since ??
	 *
	 * @return bool WP Editor template status.
	 */
	public static function is_wp_editor_template(): bool {
		return 'default' !== self::get_wp_editor_template_type();
	}

	/**
	 * Get the current WP Editor template id.
	 *
	 * Returns 0 if no template has been started.
	 *
	 * @since ??
	 *
	 * @return integer Template post ID (wp_id).
	 */
	public static function get_wp_editor_template_id() {
		$count = count( self::$wp_editor_template );
		$id    = 0;

		if ( $count > 0 ) {
			$id = et_()->array_get( self::$wp_editor_template, [ $count - 1, 'id' ], 0 );
		}

		// Just want to be safe to not return any unexpected result.
		return is_int( $id ) ? $id : 0;
	}

	/**
	 * Get the current WP Editor template type.
	 *
	 * Returns 'default' if no template has been started.
	 *
	 * @since ??
	 *
	 * @param boolean $is_id_needed Whether template ID is needed or not.
	 *
	 * @return string Template type.
	 */
	public static function get_wp_editor_template_type( bool $is_id_needed = false ): string {
		$count = count( self::$wp_editor_template );
		$type  = '';

		if ( $count > 0 ) {
			$type = et_()->array_get( self::$wp_editor_template, [ $count - 1, 'type' ] );

			// Page may have more than one template parts. So, the wp_id is needed in certain
			// situation as unique identifier.
			if ( $is_id_needed && ET_WP_EDITOR_TEMPLATE_PART_POST_TYPE === $type ) {
				$id    = self::get_wp_editor_template_id();
				$type .= "-{$id}";
			}
		}

		// Just want to be safe to not return any unexpected result.
		return ! empty( $type ) && is_string( $type ) ? $type : 'default';
	}

	/**
	 * Setup the advanced styles manager
	 *
	 * @since ??
	 *
	 * @param int $post_id The post ID.
	 */
	public static function setup_styles_manager( int $post_id = 0 ) {
		if ( 0 === $post_id && et_core_page_resource_is_singular() ) {
			// It doesn't matter if post id is 0 because we're going to force inline styles.
			$post_id = et_core_page_resource_get_the_ID();
		}

		$should_generate_critical_css = CriticalCSS::should_generate_critical_css();

		// Check if page has paginated loops - disable static CSS to prevent stale CSS variable caching.
		$has_paginated_loops = LoopUtils::has_paginated_loops();

		// Check if page has random order loops - disable static CSS to prevent stale CSS variable caching.
		$current_page_has_random_order_loops = LoopUtils::current_page_has_random_order_loops();

		// Check if page has post-filtered loops - disable static CSS to prevent partial loop style caching.
		$has_post_filtered_loops = PostFilterUtils::has_active_loop_filter_clauses_in_request();

		// Check blog style mode for post feeds.
		$blog_style_mode = 'on' === et_get_option( 'divi_blog_style', 'off' );

		// Check if page has excerpt_content_on enabled (blog module showing full content).
		// DynamicAssets caches this value in pre_initial_setup() which runs before StaticCSS::setup().
		// This avoids redundant get_entire_page_content() calls and feature detection.
		$has_excerpt_content_on = false;
		if ( 0 === $post_id && ! et_core_page_resource_is_singular() ) {
			$dynamic_assets = DynamicAssets::get_instance();
			if ( null !== $dynamic_assets ) {
				$has_excerpt_content_on = $dynamic_assets->get_cached_feature_detection( 'excerpt_content_on', false );
			}
		}

		// Extract pagination parameters from $_GET (module loop IDs as parameter names, page numbers as values).
		$pagination_params = [];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL parameter extraction for cache key, no security risk.
		if ( isset( $_GET ) && is_array( $_GET ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL parameter extraction for cache key, no security risk.
			foreach ( $_GET as $param => $value ) {
				// Check if parameter looks like a loop pagination parameter (starts with 'loop-' and has numeric value > 1).
				if ( is_string( $param ) && str_starts_with( $param, 'loop-' ) && is_numeric( $value ) && (int) $value > 1 ) {
					$pagination_params[ $param ] = (int) $value;
				}
			}
		}
		// Sort pagination parameters by key for consistent cache key generation.
		ksort( $pagination_params );
		// Create MD5 hash of sorted pagination parameters.
		$pagination_key = ! empty( $pagination_params ) ? md5(
			implode(
				'|',
				array_map(
					function ( $k, $v ) {
						return $k . '=' . $v;
					},
					array_keys( $pagination_params ),
					$pagination_params
				)
			)
		) : '';

		// Include pagination, random order state, post-filter state, blog style mode, and excerpt_content_on in cache key to prevent cross-request caching issues.
		$cache_key = $post_id . intval( $should_generate_critical_css ) . intval( $has_paginated_loops ) . intval( $current_page_has_random_order_loops ) . intval( $has_post_filtered_loops ) . intval( $blog_style_mode ) . intval( $has_excerpt_content_on ) . $pagination_key;

		if ( isset( self::$_setup_styles_manager_cache[ $cache_key ] ) ) {
			return self::$_setup_styles_manager_cache[ $cache_key ];
		}

		$deferred         = false;
		$is_preview       = is_preview() || is_et_pb_preview();
		$forced_in_footer = $post_id && 'on' === et_get_option( 'et_pb_css_in_footer', 'off' );

		// Check is static CSS is disabled.
		$static_css_is_disabled = ! et_core_is_static_css_enabled();

		// On post feeds (when post_id is 0), only force inline if blog style mode is enabled or excerpt_content_on is true.
		// When blog style mode is disabled and excerpt_content_on is false, allow static CSS caching on post feeds.
		$is_post_feed = ! is_singular();

		// Force inline for post feeds when blog style mode is enabled OR excerpt_content_on is true (blog module showing full content).
		// For non-post-feed pages with no post_id, keep existing behavior (force inline).
		$force_inline_no_post_id = ! $post_id && ( ! $is_post_feed || $blog_style_mode || $has_excerpt_content_on );

		// All things considered, should we force inline styles?
		$forced_inline = $force_inline_no_post_id || $is_preview || $forced_in_footer || $static_css_is_disabled || $has_paginated_loops || $current_page_has_random_order_loops || $has_post_filtered_loops || et_core_is_safe_mode_active() || ET_GB_Block_Layout::is_layout_block_preview();

		// Are we using unified styles?
		$unified_styles = ! $forced_inline && ! $forced_in_footer;

		$resource_owner = $unified_styles ? 'core' : 'builder';
		$resource_slug  = $unified_styles ? 'unified' : 'module-design';

		$resource_slug .= $unified_styles && Conditions::is_custom_post_type() ? '-cpt' : '';

		// Temporarily keep resource slug before TB slug processing.
		$temp_resource_slug = $resource_slug;

		$resource_slug = et_theme_builder_decorate_page_resource_slug( $post_id, $resource_slug );

		// Append pagination parameters to resource slug when pagination exists to ensure unique CSS filenames
		// per paginated page and prevent CSS file reuse. This ensures each paginated page gets a unique
		// CSS filename, preventing the file existence check from reusing Page 1's CSS file for Page 2.
		if ( ! empty( $pagination_params ) ) {
			$resource_slug .= '-' . $pagination_key;
		}

		// TB should be prioritized over WP Editor. If resource slug is not changed, it is
		// not for TB. Ensure current module is one of WP Editor template before checking.
		if ( $temp_resource_slug === $resource_slug && self::is_wp_editor_template() ) {
			$resource_slug = et_builder_wp_editor_decorate_page_resource_slug( $post_id, $resource_slug );
		}

		// If the post is password protected and a password has not been provided yet,
		// no content (including any custom style) will be printed.
		// When static css file option is enabled this will result in missing styles.
		if ( ! $forced_inline && post_password_required( $post_id ? $post_id : null ) ) {
			$forced_inline = true;
		}

		if ( $is_preview ) {
			// Don't let previews cause existing saved static css files to be modified.
			$resource_slug .= '-preview';
		}

		$manager  = et_core_page_resource_get( $resource_owner, $resource_slug, $post_id, 40 );
		$has_file = $manager->has_file();

		$manager_data = [
			'manager'   => $manager,
			'add_hooks' => true,
		];

		if ( $should_generate_critical_css ) {
			$deferred                 = et_core_page_resource_get( $resource_owner, $resource_slug . '-deferred', $post_id, 40 );
			$has_file                 = $has_file && $deferred->has_file();
			$manager_data['deferred'] = $deferred;
		}

		if ( ! $forced_inline && ! $forced_in_footer && $has_file ) {
			// This post currently has a fully configured styles manager.
			$manager_data['add_hooks'] = false;

			/**
			 * Filters the Style Managers used to output Critical/Deferred Builder CSS.
			 *
			 * This filter is the replacement of Divi 4 filter `et_builder_module_style_manager`.
			 *
			 * @since ??
			 *
			 * @param array $manager_data Style Managers.
			 */
			$manager_data = apply_filters( 'divi_frontend_assets_static_css_module_style_manager', $manager_data );

			return $manager_data;
		}

		$manager->forced_inline       = $forced_inline;
		$manager->write_file_location = 'footer';

		if ( $deferred ) {
			$deferred->forced_inline       = $forced_inline;
			$deferred->write_file_location = 'footer';
		}

		if ( $forced_in_footer || $forced_inline ) {
			// Restore legacy behavior--output inline styles in the footer.
			$manager->set_output_location( 'footer' );
			if ( $deferred ) {
				$deferred->set_output_location( 'footer' );
			}
		}

		/** This filter is documented in StaticCSS.php */
		$manager_data = apply_filters( 'divi_frontend_assets_static_css_module_style_manager', $manager_data );

		// Cache $manager_data.
		self::$_setup_styles_manager_cache[ $cache_key ] = $manager_data;

		return $manager_data;
	}

	/**
	 * Disable static CSS for pages with paginated loops.
	 *
	 * @since ??
	 *
	 * @param bool $is_enabled Whether static CSS is enabled.
	 *
	 * @return bool Modified enabled status.
	 */
	public static function maybe_disable_static_css_for_paginated_loops( bool $is_enabled ): bool {
		// Only disable if static CSS was originally enabled.
		if ( ! $is_enabled ) {
			return $is_enabled;
		}

		// Check if page has paginated loops.
		$has_paginated_loops = LoopUtils::has_paginated_loops();

		// Disable static CSS if paginated loops detected.
		return ! $has_paginated_loops;
	}

	/**
	 * Disable static CSS for pages with post-filtered loops.
	 *
	 * @since ??
	 *
	 * @param bool $is_enabled Whether static CSS is enabled.
	 *
	 * @return bool Modified enabled status.
	 */
	public static function maybe_disable_static_css_for_post_filtered_loops( bool $is_enabled ): bool {
		// Only disable if static CSS was originally enabled.
		if ( ! $is_enabled ) {
			return $is_enabled;
		}

		// Check if page has post-filtered loops.
		$has_post_filtered_loops = PostFilterUtils::has_active_loop_filter_clauses_in_request();

		// Disable static CSS if post-filtered loops detected.
		return ! $has_post_filtered_loops;
	}

	/**
	 * Enqueue or render the styles.
	 *
	 * This method passes the styles to the advanced style manager (ET_Core_PageResource) instance and it'll decide
	 * whether the style should be rendered as inline style or enqueued.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_or_render_style(): void {
		if ( ! is_admin() && ! Conditions::is_vb_enabled() ) {
			// Get canvas z-index styles, if any.
			$canvas_z_index_css = Page::canvas_z_index_css();

			// Add a separate static CSS element for canvas z-index styles.
			if ( ! empty( $canvas_z_index_css ) ) {
				self::add_element(
					new StaticCSSElement(
						'canvas_z_index',
						0,
						$canvas_z_index_css,
						self::$styles_manager,
						self::$deferred_styles_manager ?? null
					)
				);
			}
			// Get the cutom css, if any.
			$custom = et_core_is_builder_used_on_current_request() ? Page::custom_css() : '';

			// Add a main page content static CSS element.
			self::add_element(
				new StaticCSSElement(
					'default',
					0,
					$custom,
					self::$styles_manager,
					self::$deferred_styles_manager ?? null
				)
			);

			// Extract all layout IDs from the static CSS elements for batch processing.
			$all_layout_ids = array_map(
				function ( StaticCSSElement $element ) {
					return $element->get_layout_id();
				},
				self::$_elements
			);

			// Collect all preset styles from all layouts to be output once at the beginning.
			// Preset styles include presetNested, preset, and presetGroup which are shared styles.
			$preset_styles_data = [];
			$styles_groups      = [ 'presetNested', 'preset', 'presetGroup' ];

			// Loop through all layouts and collect preset styles from each.
			foreach ( $all_layout_ids as $layout_id ) {
				foreach ( $styles_groups as $styles_group ) {
					$styles_data = Style::get_style_array( $styles_group, $layout_id );

					if ( empty( $styles_data ) ) {
						continue;
					}

					// Merge preset styles from all layouts into a single array structure.
					foreach ( $styles_data as $styles_data_type => $styles_data_items ) {
						if ( ! empty( $styles_data_items ) ) {
							if ( ! isset( $preset_styles_data[ $styles_group ] ) ) {
								$preset_styles_data[ $styles_group ] = [];
							}

							if ( ! isset( $preset_styles_data[ $styles_group ][ $styles_data_type ] ) ) {
								$preset_styles_data[ $styles_group ][ $styles_data_type ] = [];
							}

							$preset_styles_data[ $styles_group ][ $styles_data_type ] = array_merge(
								$preset_styles_data[ $styles_group ][ $styles_data_type ],
								$styles_data_items
							);
						}
					}
				}
			}

			// Track whether preset styles have been output to ensure they're only output once.
			$preset_styles_data_used = false;

			// Find the element with layout_id 0 (main page content) to ensure preset and module styles are on the same element.
			$main_content_element = null;
			foreach ( self::$_elements as $element ) {
				if ( 0 === $element->get_layout_id() && 'default' === $element->get_layout_type() ) {
					$main_content_element = $element;
					break;
				}
			}

			// Merge module styles from all static CSS elements (Theme Builder layouts + main) in render order.
			// Deduplicates identical rules that repeat because module order indices reset per TB layout.
			$merged_module_styles_data = [];
			foreach ( self::$_elements as $element_for_merge ) {
				$layout_id         = $element_for_merge->get_layout_id();
				$module_for_layout = Style::get_style_array( 'module', $layout_id );

				if ( ! empty( $module_for_layout ) ) {
					$merged_module_styles_data = Style::merge_module_styles_data(
						$merged_module_styles_data,
						$module_for_layout
					);
				}
			}

			// Process each element in priority order.
			foreach ( self::$_elements as $element_index => $element ) {
				// Output all collected preset styles with the main content element (layout_id 0) or first element if not found.
				// This ensures preset styles and module styles for layout_id 0 are on the same element.
				$should_set_preset_styles = ! $preset_styles_data_used && ! empty( $preset_styles_data ) && ( $main_content_element === $element || ( null === $main_content_element && 0 === $element_index ) );

				if ( $should_set_preset_styles ) {
					foreach ( $preset_styles_data as $styles_group => $styles_data_items ) {
						$element->set_styles_data( $styles_group, $styles_data_items );
					}
					$preset_styles_data_used = true;
				}

				// Output merged module styles once on the main content element (same target as merged presets).
				$is_main_content_target = $main_content_element === $element || ( null === $main_content_element && 0 === $element_index );

				if ( $is_main_content_target ) {
					if ( ! empty( $merged_module_styles_data ) ) {
						$element->set_styles_data( 'module', $merged_module_styles_data );
					} else {
						$module_styles_data = Style::get_style_array( 'module', $element->get_layout_id() );

						if ( ! empty( $module_styles_data ) ) {
							$element->set_styles_data( 'module', $module_styles_data );
						}
					}
				}

				// Output the styles for this element.
				self::style_output_by_element( $element );
			}
		}
	}

	/**
	 * Output the styles.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     Parameters for styling.
	 *
	 *     @var \ET_Core_PageResource $styles_manager          The style manager.
	 *     @var \ET_Core_PageResource $deferred_styles_manager The deferred style manager.
	 *     @var string                $custom                  The custom CSS.
	 *     @var string                $element_id              The Element ID.
	 * }
	 */
	public static function style_output( array $params ) {
		/**
		 * Style Manager.
		 *
		 * @var \ET_Core_PageResource $styles_manager.
		 */
		$styles_manager = $params['styles_manager'] ?? null;

		/**
		 * Deferred Style Manager.
		 *
		 * @var \ET_Core_PageResource $deferred_styles_manager.
		 */
		$deferred_styles_manager = $params['deferred_styles_manager'] ?? null;

		// Get the provided CSS.
		$custom = $params['custom'] ?? '';

		// Get element ID.
		$element_id = $params['element_id'] ?? 0;

		/**
		 * Filters whether Critical CSS should be generated or not.
		 *
		 * @since ??
		 *
		 * @param bool $enabled Critical CSS enabled value.
		 */
		$should_generate_critical_css = CriticalCSS::should_generate_critical_css();

		$critical = '';

		/*
		 * When critical CSS should be generated, styles are split into two:
		 * - Above the fold styles is marked as `critical`.
		 * - Below the fold styles doesn't have `critical` mark.
		 *
		 * When critical CSS should not be generated, all styles doesn't have the`critical` mark.
		 *
		 * The order of the styles is:
		 * - Nested group preset styles (from module presets) come first.
		 * - Module preset styles come next and can override nested group preset styles.
		 * - Explicit group preset styles come after module presets and can override them.
		 * - Module styles come last and can override all preset styles.
		 */
		if ( $should_generate_critical_css ) {
			$critical = Style::render(
				'critical',
				'presetNested',
				$element_id
			) . Style::render(
				'critical',
				'preset',
				$element_id
			) . Style::render(
				'critical',
				'presetGroup',
				$element_id
			) . Style::render(
				'critical',
				'module',
				$element_id
			);

			$styles = Style::render(
				'default',
				'presetNested',
				$element_id
			) . Style::render(
				'default',
				'preset',
				$element_id
			) . Style::render(
				'default',
				'presetGroup',
				$element_id
			) . Style::render(
				'default',
				'module',
				$element_id
			);
		} else {
			$styles = Style::render(
				'default',
				'presetNested',
				$element_id
			) . Style::render(
				'default',
				'preset',
				$element_id
			) . Style::render(
				'default',
				'presetGroup',
				$element_id
			) . Style::render(
				'default',
				'module',
				$element_id
			);
		}

		// if the shortcode framework is loaded, get the shortcode element styles.
		if ( et_is_shortcode_framework_loaded() ) {
			if ( $should_generate_critical_css ) {
				$critical = $critical . \ET_Builder_Element::get_style( false, $element_id, true ) . \ET_Builder_Element::get_style( true, $element_id, true );
			}

			$styles = $styles . \ET_Builder_Element::get_style( false, $element_id ) . \ET_Builder_Element::get_style( true, $element_id );
		}

		if ( empty( $critical ) ) {
			// No critical styles defined, just enqueue everything as usual.
			$styles = $custom . $styles;
			if ( ! empty( $styles ) ) {
				if ( isset( $deferred_styles_manager ) ) {
					$deferred_styles_manager->set_data( $styles, 40 );
				} else {
					$styles_manager->set_data( $styles, 40 );
				}
			}
		} else {
			// Add page css to the critical section.
			$critical = $custom . $critical;
			$styles_manager->set_data( $critical, 40 );
			if ( ! empty( $styles ) && isset( $deferred_styles_manager ) ) {
				// Defer everything else.
				$deferred_styles_manager->set_data( $styles, 40 );
			}
		}

		// Cleanup.
		unset( $styles_manager, $deferred_styles_manager, $custom, $critical, $styles );
	}

	/**
	 * Output styles for a static CSS element.
	 *
	 * Processes styles data from the provided static CSS element and outputs them
	 * to the appropriate style managers. When critical CSS is enabled, styles are
	 * split into critical (above the fold) and deferred (below the fold) styles.
	 * Otherwise, all styles are output to the main styles manager.
	 *
	 * The method handles:
	 * - Custom CSS from the element
	 * - Styles data organized by groups (presetNested, preset, presetGroup, module)
	 * - Shortcode framework styles if available
	 * - Critical CSS separation when enabled
	 *
	 * @since ??
	 *
	 * @param StaticCSSElement $static_css_element The static CSS element containing
	 *                                            layout information, custom CSS, styles data,
	 *                                            and style managers.
	 *
	 * @return void
	 */
	public static function style_output_by_element( StaticCSSElement $static_css_element ) {
		/**
		 * Style Manager.
		 *
		 * @var \ET_Core_PageResource $styles_manager.
		 */
		$styles_manager = $static_css_element->get_styles_manager();

		/**
		 * Deferred Style Manager.
		 *
		 * @var \ET_Core_PageResource $deferred_styles_manager.
		 */
		$deferred_styles_manager = $static_css_element->get_deferred_styles_manager();

		// Get the provided CSS.
		$custom = $static_css_element->get_custom_css();

		// Get element ID.
		$element_id = $static_css_element->get_layout_id();

		$styles_data = $static_css_element->get_styles_data();

		/**
		 * Filters whether Critical CSS should be generated or not.
		 *
		 * @since ??
		 *
		 * @param bool $enabled Critical CSS enabled value.
		 */
		$should_generate_critical_css = CriticalCSS::should_generate_critical_css();

		$critical = '';
		$styles   = '';

		/*
		 * When critical CSS should be generated, styles are split into two:
		 * - Above the fold styles is marked as `critical`.
		 * - Below the fold styles doesn't have `critical` mark.
		 *
		 * When critical CSS should not be generated, all styles doesn't have the`critical` mark.
		 *
		 * The order of the styles is:
		 * - Nested group preset styles (from module presets) come first.
		 * - Module preset styles come next and can override nested group preset styles.
		 * - Explicit group preset styles come after module presets and can override them.
		 * - Module styles come last and can override all preset styles.
		 */
		if ( $should_generate_critical_css ) {
			foreach ( $styles_data as $styles_group => $styles_data_items ) {
				if ( ! empty( $styles_data_items ) ) {
					$critical .= Style::render_by_styles_data(
						$styles_data_items,
						'critical'
					);

					$styles .= Style::render_by_styles_data(
						$styles_data_items,
						'default'
					);
				}
			}
		} else {
			foreach ( $styles_data as $styles_group => $styles_data_items ) {
				if ( ! empty( $styles_data_items ) ) {
					$styles .= Style::render_by_styles_data(
						$styles_data_items,
						'default'
					);
				}
			}
		}

		// if the shortcode framework is loaded, get the shortcode element styles.
		if ( et_is_shortcode_framework_loaded() ) {
			if ( $should_generate_critical_css ) {
				$critical = $critical . \ET_Builder_Element::get_style( false, $element_id, true ) . \ET_Builder_Element::get_style( true, $element_id, true );
			}

			$styles = $styles . \ET_Builder_Element::get_style( false, $element_id ) . \ET_Builder_Element::get_style( true, $element_id );
		}

		if ( empty( $critical ) ) {
			// No critical styles defined, just enqueue everything as usual.
			$styles = $custom . $styles;
			if ( ! empty( $styles ) ) {
				if ( isset( $deferred_styles_manager ) ) {
					$deferred_styles_manager->set_data( $styles, 40 );
				} else {
					$styles_manager->set_data( $styles, 40 );
				}
			}
		} else {
			// Add page css to the critical section.
			$critical = $custom . $critical;
			$styles_manager->set_data( $critical, 40 );
			if ( ! empty( $styles ) && isset( $deferred_styles_manager ) ) {
				// Defer everything else.
				$deferred_styles_manager->set_data( $styles, 40 );
			}
		}

		// Cleanup.
		unset( $styles_manager, $deferred_styles_manager, $custom, $critical, $styles );
	}

	/**
	 * Add an element to the elements array.
	 *
	 * @since ??
	 *
	 * @param StaticCSSElement $static_css_element The static CSS element.
	 *
	 * @return void
	 */
	public static function add_element( StaticCSSElement $static_css_element ) {
		self::$_elements[] = $static_css_element;

		if ( count( self::$_elements ) > 1 ) {
			self::$_elements = ArrayUtility::sort(
				self::$_elements,
				function ( $a, $b ) {
					return $a->get_priority() <=> $b->get_priority();
				}
			);
		}
	}

	/**
	 * Filters the unified page resource data. The data is an array of arrays of strings keyed by
	 * priority. The builder's styles are set with a priority of 40. Here we want to make sure
	 * only the builder's styles are output in the footer on first-page load so we aren't
	 * duplicating the customizer and custom css styles which are already in the <head>.
	 * {@see 'et_core_page_resource_get_data'}
	 *
	 * @since ??
	 *
	 * @param array[]               $data     {
	 *     Arrays of strings keyed by priority.
	 *
	 *     @type string[]           $priority Resource data.
	 *     ...
	 * }.
	 *
	 * @param string                $context  Where the data will be used. Accepts 'inline', 'file'.
	 * @param \ET_Core_PageResource $resource The resource instance.
	 *
	 * @return array
	 */
	public static function filter_page_resource_data( array $data, string $context, \ET_Core_PageResource $resource ): array {
		global $wp_current_filter;

		if ( 'inline' !== $context || ! in_array( 'wp_footer', $wp_current_filter, true ) ) {
			return $data;
		}

		if ( ! str_contains( $resource->slug, 'unified' ) ) {
			return $data;
		}

		if ( 'footer' !== $resource->location ) {
			// This is the first load of a page that doesn't currently have a unified static css file.
			// The theme customizer and custom css have already been inlined in the <head> using the
			// unified resource's ID. It's invalid HTML to have duplicated IDs on the page so we'll
			// fix that here since it only applies to this page load anyway.
			$resource->slug = $resource->slug . '-2';
		}

		return isset( $data[40] ) ? [ 40 => $data[40] ] : [];
	}

	/**
	 * Set {@see StaticCSS::$styles_manager} to force inline styles.
	 */
	public static function maybe_force_inline_styles() {
		if ( et_core_is_fb_enabled() || self::$styles_manager->forced_inline || ! self::$forced_inline_styles ) {
			return;
		}

		self::$styles_manager->forced_inline       = true;
		self::$styles_manager->write_file_location = 'footer';
		self::$styles_manager->set_output_location( 'footer' );

		if ( isset( self::$deferred_styles_manager ) ) {
			self::$deferred_styles_manager->forced_inline       = true;
			self::$deferred_styles_manager->write_file_location = 'footer';
			self::$deferred_styles_manager->set_output_location( 'footer' );
		}
	}
}
