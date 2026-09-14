<?php
/**
 * Dynamic Assets List Builder.
 *
 * Handles building asset lists for dynamic assets processing.
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\FrontEnd\Assets\DynamicAssets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use ET\Builder\FrontEnd\Assets\DetectFeature;
use ET\Builder\FrontEnd\Assets\DynamicAssets\State\CacheState;
use ET\Builder\FrontEnd\Assets\DynamicAssets\State\DetectionState;
use ET\Builder\FrontEnd\Assets\DynamicAssets\State\FeatureState;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Framework\Settings\PageSettings;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\VisualBuilder\OffCanvas\OffCanvasHooks;

/**
 * Dynamic Assets List Builder class.
 *
 * Handles building asset lists for dynamic assets processing.
 *
 * @since ??
 */
class DynamicAssetsListBuilder {

	/**
	 * Cache state container.
	 *
	 * @var CacheState
	 */
	private CacheState $cache_state;

	/**
	 * Detection state container.
	 *
	 * @var DetectionState
	 */
	private DetectionState $detection_state;

	/**
	 * Feature state container.
	 *
	 * @var FeatureState
	 */
	private FeatureState $feature_state;

	/**
	 * Content handler.
	 *
	 * @var DynamicAssetsContent
	 */
	private DynamicAssetsContent $content;

	/**
	 * Detection handler.
	 *
	 * @var DynamicAssetsDetection
	 */
	private DynamicAssetsDetection $detection;

	/**
	 * Dependency checker.
	 *
	 * @var DynamicAssetsDependencyChecker
	 */
	private DynamicAssetsDependencyChecker $dependency_checker;

	/**
	 * Constructor.
	 *
	 * @since ??
	 *
	 * @param CacheState                     $cache_state        Cache state container.
	 * @param DetectionState                 $detection_state    Detection state container.
	 * @param FeatureState                   $feature_state      Feature state container.
	 * @param DynamicAssetsContent           $content            Content handler.
	 * @param DynamicAssetsDetection         $detection          Detection handler.
	 * @param DynamicAssetsDependencyChecker $dependency_checker Dependency checker.
	 */
	public function __construct(
		CacheState $cache_state,
		DetectionState $detection_state,
		FeatureState $feature_state,
		DynamicAssetsContent $content,
		DynamicAssetsDetection $detection,
		DynamicAssetsDependencyChecker $dependency_checker
	) {
		$this->cache_state        = $cache_state;
		$this->detection_state    = $detection_state;
		$this->feature_state      = $feature_state;
		$this->content            = $content;
		$this->detection          = $detection;
		$this->dependency_checker = $dependency_checker;
	}

	/**
	 * Check if icons exist in presets.
	 *
	 * @since ??
	 *
	 * @param string $icon_type Icon type to check ('divi' or 'fa').
	 *
	 * @return bool True if icon type exists in presets.
	 */
	private function _has_icon_in_presets( string $icon_type ): bool {
		if ( empty( $this->feature_state->presets_attributes ) ) {
			$this->feature_state->presets_attributes = $this->detection->presets_feature_used( $this->content->get_all_content() );
		}

		$key = "icon_font_{$icon_type}";
		return ! empty( $this->feature_state->presets_attributes[ $key ] );
	}

	/**
	 * Get feature value from early_attributes (populated by feature detection map) with preset fallback.
	 *
	 * This reads directly from early_attributes which is populated by the feature detection map
	 * during early detection. No additional detection is performed - this is just reading cached results.
	 *
	 * @since ??
	 *
	 * @param string $feature_name Feature name to check.
	 *
	 * @return bool True if feature is detected in content or presets.
	 */
	private function _is_feature_detected( string $feature_name ): bool {
		// Read directly from early_attributes populated by feature detection map.
		$cached_value = $this->detection_state->early_attributes[ $feature_name ] ?? null;

		if ( null !== $cached_value ) {
			// Extract boolean from array format (e.g., [true] or []).
			if ( is_array( $cached_value ) ) {
				$filtered = array_filter( $cached_value );
				if ( ! empty( $filtered ) && 1 === count( $filtered ) && is_bool( reset( $filtered ) ) ) {
					return reset( $filtered );
				}
				// Non-boolean array feature - return true if not empty.
				return ! empty( $filtered );
			}
			// Direct boolean value.
			return (bool) $cached_value;
		}

		// Fallback to preset features if not found in early_attributes.
		return ! empty( $this->feature_state->presets_feature_used[ $feature_name ] );
	}

	/**
	 * Resolve effective page gutter width using explicit/default contract.
	 *
	 * @since ??
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return int
	 */
	private function _get_effective_page_gutter_width( int $post_id ): int {
		$resolved = PageSettings::resolve_page_gutter_width( $post_id );
		return $resolved['value'];
	}

	/**
	 * Add CSS assets for features detected via the feature detection map.
	 *
	 * @since ??
	 *
	 * @param string $assets_prefix Assets path prefix.
	 *
	 * @return void
	 */
	private function _add_feature_assets_from_map( string $assets_prefix ): void {
		// Mapping of features to their CSS assets.
		// Features are detected via the feature detection map and cached in early_attributes.
		$feature_assets_map = [
			'css_grid_layout_enabled' => function () use ( $assets_prefix ) {
				return $this->get_css_grid_asset_list();
			},
			'sticky_position_enabled' => function () use ( $assets_prefix ) {
				return [
					'sticky' => [
						'css' => "{$assets_prefix}/css/sticky_elements{$this->cache_state->cpt_suffix}.css",
					],
				];
			},
			'block_mode_blog'         => function () use ( $assets_prefix ) {
				return [
					'blog_block' => [
						'css' => "{$assets_prefix}/css/blog_block{$this->cache_state->cpt_suffix}.css",
					],
				];
			},
			'animation_style'         => function () use ( $assets_prefix ) {
				// Also check for circle-counter module which always needs animation CSS.
				$has_animation = $this->_is_feature_detected( 'animation_style' );
				if ( $has_animation || in_array( 'divi/circle-counter', $this->detection_state->processed_modules, true ) ) {
					return [
						'animations' => [
							'css' => "{$assets_prefix}/css/animations{$this->cache_state->cpt_suffix}.css",
						],
					];
				}
				return [];
			},
			'lightbox'                => function () use ( $assets_prefix ) {
				return [
					'et_jquery_magnific_popup' => [
						'css' => "{$assets_prefix}/css/magnific_popup.css",
					],
				];
			},
		];

		// Loop through detected features and add their assets.
		foreach ( $feature_assets_map as $feature_name => $asset_callback ) {
			// Check if feature is detected OR if preset features have it (for features like lightbox).
			$is_detected = $this->_is_feature_detected( $feature_name ) || ! empty( $this->feature_state->presets_feature_used[ $feature_name ] );
			if ( $is_detected ) {
				$assets = $asset_callback();
				if ( ! empty( $assets ) ) {
					$this->feature_state->early_global_asset_list = array_merge(
						$this->feature_state->early_global_asset_list,
						$assets
					);
				}
			}
		}
	}

	/**
	 * Gets a list of global asset files.
	 *
	 * @since ??
	 * @return array
	 */
	public function get_global_assets_list(): array {
		if ( ! DynamicAssetsUtils::should_generate_dynamic_assets() || ! DynamicAssetsUtils::use_dynamic_assets() || ! $this->cache_state->folder_name ) {
			return [];
		}

		if ( ! $this->feature_state->use_global_colors ) {
			$this->feature_state->use_global_colors = true;

			// Get the page settings attributes.
			$page_setting_attributes = DynamicAssetsUtils::get_page_setting_attributes(
				$this->cache_state->post_id,
				[
					'et_pb_content_area_background_color',
					'et_pb_section_background_color',
					'et_pb_light_text_color',
					'et_pb_dark_text_color',
					'et_pb_custom_css',
				]
			);

			// Get global color ids from the content and page settings.
			$_early_global_color_ids = DetectFeature::get_global_color_ids( $this->content->get_all_content() . wp_json_encode( $page_setting_attributes ) );

			// Get global color ids from module presets used on this page to ensure preset-only global colors are included.
			$_preset_global_color_ids = DetectFeature::get_preset_global_color_ids( $this->content->get_all_content() );

			// Get global color ids from appended canvases (interaction-targeted and explicitly appended).
			$_canvas_global_color_ids        = [];
			$_canvas_preset_global_color_ids = [];
			if ( $this->cache_state->post_id && is_singular() ) {
				// Get main post content to extract interaction target IDs.
				$main_content = $this->cache_state->original_post_content;

				if ( $main_content ) {
					// Get all appended canvas content.
					$appended_canvas_content = OffCanvasHooks::get_all_appended_canvas_content( $this->cache_state->post_id, $main_content );

					if ( ! empty( $appended_canvas_content ) ) {
						// Extract global color IDs from appended canvas content.
						$_canvas_global_color_ids = DetectFeature::get_global_color_ids( $appended_canvas_content );

						// Extract global color IDs from presets used in appended canvas content.
						$_canvas_preset_global_color_ids = DetectFeature::get_preset_global_color_ids( $appended_canvas_content );
					}
				}
			}

			// Merge global color ids from the content, page settings, presets, and appended canvases,
			// ensuring that global colors from `Customizer` is always included.
			$this->feature_state->early_global_color_ids = DynamicAssetsUtils::get_unique_array_values(
				array_merge( $_early_global_color_ids, $_preset_global_color_ids, $_canvas_global_color_ids, $_canvas_preset_global_color_ids ),
				array_keys( GlobalData::get_customizer_colors() )
			);

			// Store global color IDs in _early_attributes cache so late detection knows they're already processed.
			// This prevents the feature detection map from re-detecting the same colors.
			if ( ! is_array( $this->detection_state->early_attributes ) ) {
				$this->detection_state->early_attributes = [];
			}
			$this->detection_state->early_attributes['global_color_ids'] = $this->feature_state->early_global_color_ids;

			// Set global colors variable for the Critical CSS.
			$this->feature_state->early_global_asset_list['et_early_global_colors'] = [
				'css' => Style::get_global_colors_style( $this->feature_state->early_global_color_ids ),
			];
		}

		$assets_prefix     = DynamicAssetsUtils::get_dynamic_assets_path();
		$dynamic_icons     = DynamicAssetsUtils::use_dynamic_icons();
		$social_icons_deps = [
			'divi/social-media-follow',
			'divi/team-member',
		];

		if ( ! $this->feature_state->use_divi_icons || ! $this->feature_state->use_fa_icons ) {
			// Check for icons existence in presets.
			$maybe_presets_contain_divi_icon = $this->_has_icon_in_presets( 'divi' );
			$maybe_presets_contain_fa_icon   = $this->_has_icon_in_presets( 'fa' );

			$maybe_post_contains_divi_icon = $this->feature_state->use_divi_icons || $maybe_presets_contain_divi_icon;

			if ( ! $maybe_post_contains_divi_icon ) {
				$maybe_post_contains_divi_icon = DetectFeature::has_icon_font( $this->content->get_all_content(), 'divi', $this->detection_state->options );
			}

			// Load the icon font needed based on the icons being used.
			$this->feature_state->use_divi_icons = $this->feature_state->use_divi_icons || ( 'on' !== $dynamic_icons || $maybe_post_contains_divi_icon || $this->detection->check_if_class_exits( 'et-pb-icon', $this->content->get_all_content() ) );

			$this->feature_state->use_fa_icons = $this->feature_state->use_fa_icons || $maybe_presets_contain_fa_icon;

			if ( ! $this->feature_state->use_fa_icons ) {
				$this->feature_state->use_fa_icons = ( $this->dependency_checker->check_for_dependency( DynamicAssetsUtils::get_font_icon_modules(), $this->detection_state->processed_modules ) && DetectFeature::has_icon_font( $this->content->get_all_content(), 'fa', $this->detection_state->options ) );
			}

			// The Payment Button module uses a FontAwesome icon ( PayPal ) as its default. Because this
			// default is not serialized into the saved post content, the icon-font detection above cannot
			// catch it. Ensure FontAwesome is loaded whenever a Payment Button is present on the page.
			if ( ! $this->feature_state->use_fa_icons ) {
				$this->feature_state->use_fa_icons = $this->dependency_checker->check_for_dependency( [ 'divi/payment-button' ], $this->detection_state->processed_modules );
			}
		}

		// Fix for Font Awesome not loading on empty category pages.
		// Check Theme Builder templates for Font Awesome icons when main content detection fails.
		if ( ! $this->feature_state->use_fa_icons && is_category() && ! empty( $this->cache_state->tb_template_ids ) ) {
			$template_content = $this->content->get_theme_builder_template_content();
			if ( ! empty( $template_content ) ) {
				$has_fa_in_templates = DetectFeature::has_icon_font( $template_content, 'fa', $this->detection_state->options );
				if ( $has_fa_in_templates ) {
					$this->feature_state->use_fa_icons = true;
				} elseif ( str_contains( $template_content, 'FontAwesome' ) || str_contains( $template_content, 'fa-' ) || ( str_contains( $template_content, 'unicode' ) && str_contains( $template_content, '"fa"' ) ) || ( str_contains( $template_content, 'type' ) && str_contains( $template_content, '"fa"' ) ) ) {
					// Fallback: Check for Font Awesome patterns manually.
					$this->feature_state->use_fa_icons = true;
				}
			}
		}

		if ( ! $this->feature_state->use_social_icons ) {
			$this->feature_state->use_social_icons = $this->dependency_checker->check_for_dependency( $social_icons_deps, $this->detection_state->processed_modules );

			if ( $this->feature_state->use_social_icons && ! $this->feature_state->use_fa_icons ) {
				$this->feature_state->use_fa_icons = DetectFeature::has_social_follow_icon_font( $this->content->get_all_content(), 'fa', $this->detection_state->options );
			}
		}

		if ( $this->feature_state->use_divi_icons ) {
			$this->feature_state->early_global_asset_list['et_icons_all'] = [
				'css' => "{$assets_prefix}/css/icons_all.css",
			];
		} elseif ( $this->feature_state->use_social_icons ) {
			$this->feature_state->early_global_asset_list['et_icons_social'] = [
				'css' => "{$assets_prefix}/css/icons_base_social.css",
			];
		} else {
			$this->feature_state->early_global_asset_list['et_icons_base'] = [
				'css' => "{$assets_prefix}/css/icons_base.css",
			];
		}

		if ( $this->feature_state->use_fa_icons ) {
			$this->feature_state->early_global_asset_list['et_icons_fa'] = [
				'css' => "{$assets_prefix}/css/icons_fa_all.css",
			];
		}

		// Only include the following assets on post feeds and posts that aren't using the builder.
		if ( ( is_single() && ! $this->cache_state->page_builder_used ) || ( is_home() && ! is_front_page() ) || ! is_singular() ) {
			$this->feature_state->early_global_asset_list['et_post_formats'] = [
				'css' => [
					"{$assets_prefix}/css/post_formats{$this->cache_state->cpt_suffix}.css",
					"{$assets_prefix}/css/slider_base{$this->cache_state->cpt_suffix}.css",
					"{$assets_prefix}/css/slider_controls{$this->cache_state->cpt_suffix}.css",
					"{$assets_prefix}/css/overlay{$this->cache_state->cpt_suffix}.css",
					"{$assets_prefix}/css/audio_player{$this->cache_state->cpt_suffix}.css",
					"{$assets_prefix}/css/video_player{$this->cache_state->cpt_suffix}.css",
					"{$assets_prefix}/css/wp_gallery{$this->cache_state->cpt_suffix}.css",
				],
			];
		}

		// Load posts styles on posts and post feeds.
		if ( ! is_page() ) {
			$this->feature_state->early_global_asset_list['et_posts'] = [
				'css' => "{$assets_prefix}/css/posts{$this->cache_state->cpt_suffix}.css",
			];
		}

		if ( $this->cache_state->is_rtl ) {
			$this->feature_state->early_global_asset_list['et_divi_shared_conditional_rtl'] = [
				'css' => "{$assets_prefix}/css/shared-conditional-style{$this->cache_state->cpt_suffix}-rtl.css",
			];
		}

		// Read specialty_section from early_attributes (populated by feature detection map).
		$specialty_used = $this->_is_feature_detected( 'specialty_section' );

		// Check for custom gutter widths.
		// Use cache-state ownership instead of template conditionals (e.g. is_singular())
		// so gutter asset selection stays aligned with the resolver in generation contexts.
		// Require a positive post ID; -1 is the placeholder used when no post is resolved
		// (set in DynamicAssets::pre_initial_setup), and 0 is WordPress's default invalid ID.
		// Both would produce a wrong gutter lookup, so they must be excluded.
		$page_custom_gutter = $this->cache_state->post_id > 0
			? [ $this->_get_effective_page_gutter_width( $this->cache_state->post_id ) ]
			: [];

		// Add custom gutters in TB templates.
		if ( ! empty( $this->cache_state->tb_template_ids ) ) {
			foreach ( $this->cache_state->tb_template_ids as $template_id ) {
				$page_custom_gutter[] = $this->_get_effective_page_gutter_width( (int) $template_id );
			}
		}

		$preset_gutter_val                    = $this->feature_state->presets_feature_used['gutter_widths'] ?? [];
		$customizer_gutter                    = intval( et_get_option( 'gutter_width', '3' ) );
		$this->feature_state->default_gutters = array_merge( $page_custom_gutter, (array) $customizer_gutter );

		// Combine custom gutters, defaults, and cached gutters, keeping only unique values.
		// Read gutter_widths from early_attributes populated by feature detection map.
		$cached_gutter_widths = $this->detection_state->early_attributes['gutter_widths'] ?? [];
		$gutter_widths        = DynamicAssetsUtils::get_unique_array_values(
			is_array( $cached_gutter_widths ) ? $cached_gutter_widths : [],
			$this->feature_state->default_gutters,
			$preset_gutter_val
		);

		$grid_items_deps = [
			'divi/filterable-portfolio',
			'divi/fullwidth-portfolio',
			'divi/portfolio',
			'divi/gallery',
			'divi/woocommerce-product-gallery',
			'divi/blog',
			'divi/sidebar',
			'divi/shop',
		];

		$grid_items_used = $this->dependency_checker->check_for_dependency( $grid_items_deps, $this->detection_state->processed_modules );

		// Read block_layout_enabled from early_attributes populated by feature detection map.
		$block_used = $this->_is_feature_detected( 'block_layout_enabled' );

		if ( ! empty( $gutter_widths ) && $block_used ) {
			$this->feature_state->early_global_asset_list = array_merge(
				$this->feature_state->early_global_asset_list,
				$this->get_gutters_asset_list( $gutter_widths, $specialty_used, $grid_items_used )
			);
		}

		// Add flex grid assets.
		// Detect responsive breakpoints that have custom flexColumnStructure.
		// Note: flex_grid_responsive_breakpoints is not in feature cache, so always detect.
		$responsive_breakpoints = DetectFeature::get_flex_grid_responsive_breakpoints( $this->content->get_all_content(), $this->detection_state->options );

		$this->feature_state->early_global_asset_list = array_merge(
			$this->feature_state->early_global_asset_list,
			$this->get_flex_grid_asset_list( $responsive_breakpoints )
		);

		// Read flex_layout_enabled from early_attributes populated by feature detection map.
		$flex_used = $this->_is_feature_detected( 'flex_layout_enabled' );

		if ( $flex_used && $grid_items_used ) {
			$this->feature_state->early_global_asset_list['grid_items_flex'] = [
				'css' => [
					"{$assets_prefix}/css/grid_items_flex{$this->cache_state->cpt_suffix}.css",
				],
			];
		}

		if ( $block_used && $grid_items_used ) {
			$this->feature_state->early_global_asset_list['grid_items_block'] = [
				'css' => [
					"{$assets_prefix}/css/grid_items{$this->cache_state->cpt_suffix}.css",
				],
			];
		}

		// Add CSS assets for features detected via the feature detection map.
		$this->_add_feature_assets_from_map( $assets_prefix );

		// Load WooCommerce css when WooCommerce is active.
		if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$this->feature_state->early_global_asset_list['et_divi_woocommerce_modules'] = [
				'css' => [
					"{$assets_prefix}/css/woocommerce{$this->cache_state->cpt_suffix}.css",
					"{$assets_prefix}/css/woocommerce_shared{$this->cache_state->cpt_suffix}.css",
				],
			];
		}

		// Load PageNavi css when PageNavi is active.
		if ( is_plugin_active( 'wp-pagenavi/wp-pagenavi.php' ) ) {
			$this->feature_state->early_global_asset_list['et_divi_wp_pagenavi'] = [
				'css' => "{$assets_prefix}/css/wp-page_navi{$this->cache_state->cpt_suffix}.css",
			];
		}

		// Animation assets are added via _add_feature_assets_from_map() above.
		$has_animation_style = $this->_is_feature_detected( 'animation_style' ) || in_array( 'divi/circle-counter', $this->detection_state->processed_modules, true );

		// Lightbox assets are added via _add_feature_assets_from_map() above.
		$show_in_lightbox = $this->_is_feature_detected( 'lightbox' ) || ! empty( $this->feature_state->presets_feature_used['lightbox'] );

		// Block mode blog and sticky assets are added via _add_feature_assets_from_map() above.

		// Collect and pass all needed assets arguments.
		$assets_args = [
			'assets_prefix'       => $assets_prefix,
			'dynamic_icons'       => $dynamic_icons,
			'cpt_suffix'          => $this->cache_state->cpt_suffix,
			'use_all_icons'       => $this->feature_state->use_divi_icons,
			'show_in_lightbox'    => $show_in_lightbox,
			'has_animation_style' => $has_animation_style,
			'sticky_used'         => $this->_is_feature_detected( 'sticky_position_enabled' ),
			// Gutter/grid items processed info.
			'gutter_widths'       => $gutter_widths,
			'gutter_length'       => count( $gutter_widths ),
			'specialty_used'      => $specialty_used,
			'grid_items_used'     => $grid_items_used,
		];

		// Value for the filter.
		$early_global_asset_list = $this->feature_state->early_global_asset_list;

		/**
		 * Use this filter to add additional assets to the global asset list.
		 *
		 * This filter is the replacement of Divi 4 filter `et_global_assets_list`.
		 *
		 * @since ??
		 *
		 * @param array                    $early_global_asset_list global assets on the list.
		 * @param array                    $assets_args             Additional assets arguments.
		 * @param DynamicAssetsListBuilder $this                    Instance of DynamicAssetsListBuilder class.
		 */
		$this->feature_state->early_global_asset_list = apply_filters(
			'divi_frontend_assets_dynamic_assets_global_assets_list',
			$early_global_asset_list,
			$assets_args,
			$this
		);

		return $this->feature_state->early_global_asset_list;
	}

	/**
	 * Gets a list of global asset files during late detection.
	 *
	 * @since ??
	 * @return array
	 */
	public function get_late_global_assets_list(): array {
		if ( ! DynamicAssetsUtils::should_generate_dynamic_assets() || ! DynamicAssetsUtils::use_dynamic_assets() || ! $this->cache_state->folder_name ) {
			return [];
		}

		// Get global colors based on attribute used, excluding what already included in early detections.
		if ( $this->feature_state->late_global_color_ids ) {
			$late_global_color_ids = array_diff( $this->feature_state->late_global_color_ids, $this->feature_state->early_global_color_ids );

			// Only add customizer colors if there are actually new late colors to process.
			// Customizer colors are already included in early detection, so we shouldn't duplicate them.
			// However, if we have NEW colors (not in early), ensure customizer colors are included with them.
			if ( ! empty( $late_global_color_ids ) ) {
				$customizer_color_ids  = array_keys( GlobalData::get_customizer_colors() );
				$late_global_color_ids = DynamicAssetsUtils::get_unique_array_values(
					$late_global_color_ids,
					$customizer_color_ids
				);
			}

			// Set global colors variable for the late CSS, if any.
			if ( ! empty( $late_global_color_ids ) ) {
				$this->feature_state->late_global_asset_list['et_late_global_colors'] = [
					'css' => Style::get_global_colors_style( $late_global_color_ids ),
				];
			}
		}

		$assets_prefix = DynamicAssetsUtils::get_dynamic_assets_path();

		if ( $this->feature_state->late_custom_icon ) {
			$this->feature_state->late_global_asset_list['et_icons_all'] = [
				'css' => "{$assets_prefix}/css/icons_all.css",
			];
		} elseif ( $this->feature_state->late_social_icon ) {
			$this->feature_state->late_global_asset_list['et_icons_social'] = [
				'css' => "{$assets_prefix}/css/icons_base_social.css",
			];
		}

		if ( $this->feature_state->late_fa_icon ) {
			$this->feature_state->late_global_asset_list['et_icons_fa'] = [
				'css' => "{$assets_prefix}/css/icons_fa_all.css",
			];
		}

		$gutter_length = count( $this->feature_state->late_gutter_width );

		// Only calculate gutter_widths for late detection if NEW gutters were actually detected.
		// If _late_gutter_width is empty, that means no new gutters were found, so we shouldn't
		// add default gutters again (they were already added in early detection).
		$gutter_widths = [];
		if ( ! empty( $this->feature_state->late_gutter_width ) ) {
			// New gutters were detected - merge with defaults to get final list.
			$gutter_widths = DynamicAssetsUtils::get_unique_array_values( $this->feature_state->late_gutter_width, $this->feature_state->default_gutters );
		}

		$grid_items_deps = [
			'divi/filterable-portfolio',
			'divi/fullwidth-portfolio',
			'divi/portfolio',
			'divi/gallery',
			'divi/woocommerce-product-gallery',
			'divi/blog',
			'divi/sidebar',
			'divi/shop',
		];

		$grid_items_used = $this->dependency_checker->check_for_dependency( $grid_items_deps, $this->detection_state->processed_modules );

		// Add flex grid assets.
		// Detect responsive breakpoints that have custom flexColumnStructure.
		$responsive_breakpoints = DetectFeature::get_flex_grid_responsive_breakpoints( $this->content->get_all_content(), $this->detection_state->options );

		$this->feature_state->late_global_asset_list = array_merge(
			$this->feature_state->late_global_asset_list,
			$this->get_flex_grid_asset_list( $responsive_breakpoints )
		);

		// Read flex_layout_enabled from early_attributes populated by feature detection map.
		$flex_used = $this->_is_feature_detected( 'flex_layout_enabled' );

		if ( $flex_used && $grid_items_used ) {
			$this->feature_state->late_global_asset_list['grid_items_flex'] = [
				'css' => [
					"{$assets_prefix}/css/grid_items_flex{$this->cache_state->cpt_suffix}.css",
				],
			];
		}

		// Read block_layout_enabled from early_attributes populated by feature detection map.
		$block_used = $this->_is_feature_detected( 'block_layout_enabled' );

		if ( $block_used && $grid_items_used ) {
			$this->feature_state->late_global_asset_list['grid_items_block'] = [
				'css' => [
					"{$assets_prefix}/css/grid_items{$this->cache_state->cpt_suffix}.css",
				],
			];
		}

		// Add CSS Grid assets for late detection (read from early_attributes populated by feature detection map).
		$css_grid_used = $this->_is_feature_detected( 'css_grid_layout_enabled' );

		if ( $css_grid_used ) {
			$this->feature_state->late_global_asset_list = array_merge(
				$this->feature_state->late_global_asset_list,
				$this->get_css_grid_asset_list()
			);
		}

		if ( ! empty( $gutter_widths ) ) {
			$this->feature_state->late_global_asset_list = array_merge(
				$this->feature_state->late_global_asset_list,
				$this->get_gutters_asset_list( $gutter_widths, $this->feature_state->late_use_specialty, $grid_items_used )
			);
		}

		if ( $this->feature_state->late_show_in_lightbox ) {
			$this->feature_state->late_global_asset_list['et_jquery_magnific_popup'] = [
				'css' => "{$assets_prefix}/css/magnific_popup.css",
			];
		}

		if ( $this->feature_state->late_use_animation_style ) {
			$this->feature_state->late_global_asset_list['animations'] = [
				'css' => "{$assets_prefix}/css/animations{$this->cache_state->cpt_suffix}.css",
			];
		}

		if ( $this->feature_state->late_use_block_mode_blog ) {
			$this->feature_state->late_global_asset_list['blog_block'] = [
				'css' => "{$assets_prefix}/css/blog_block{$this->cache_state->cpt_suffix}.css",
			];
		}

		if ( $this->feature_state->late_use_sticky ) {
			$this->feature_state->late_global_asset_list['sticky'] = [
				'css' => "{$assets_prefix}/css/sticky_elements{$this->cache_state->cpt_suffix}.css",
			];
		}

		// Collect and pass all needed assets arguments.
		$assets_args = [
			'assets_prefix'       => $assets_prefix,
			'dynamic_icons'       => DynamicAssetsUtils::use_dynamic_icons(),
			'cpt_suffix'          => $this->cache_state->cpt_suffix,
			'use_all_icons'       => $this->feature_state->late_custom_icon,
			'show_in_lightbox'    => $this->feature_state->late_show_in_lightbox,
			'has_animation_style' => $this->feature_state->late_use_animation_style,
			'sticky_used'         => $this->feature_state->late_use_sticky,
			// Gutter/grid items processed info.
			'gutter_widths'       => $gutter_widths,
			'gutter_length'       => $gutter_length,
			'specialty_used'      => $this->feature_state->late_use_specialty,
			'grid_items_used'     => $grid_items_used,
		];

		// Value for the filter.
		$late_global_asset_list = $this->feature_state->late_global_asset_list;

		/**
		 * Use this filter to add additional assets to the late global asset list.
		 *
		 * This filter is the replacement of Divi 4 filter `et_late_global_assets_list`.
		 *
		 * @since ??
		 *
		 * @param array                    $late_global_asset_list Current late global assets on the list.
		 * @param array                    $assets_args            Additional assets arguments.
		 * @param DynamicAssetsListBuilder $this                   Instance of DynamicAssetsListBuilder class.
		 */
		$this->feature_state->late_global_asset_list = apply_filters(
			'divi_frontend_assets_dynamic_assets_late_global_assets_list',
			$late_global_asset_list,
			$assets_args,
			$this
		);

		return $this->feature_state->late_global_asset_list;
	}

	/**
	 * Generate gutters CSS file list.
	 *
	 * @since  ?? Removed `$gutter_length` parameter.
	 * @since  4.10.0
	 *
	 * @param array $gutter_widths array of gutter widths used.
	 * @param bool  $specialty     are specialty sections used.
	 * @param bool  $grid_items    are grid modules used.
	 *
	 * @return array  $assets_list of gutter assets
	 */
	public function get_gutters_asset_list( array $gutter_widths, bool $specialty = false, bool $grid_items = false ): array {
		$assets_list = [];

		$temp_widths      = $gutter_widths;
		$gutter_length    = count( $gutter_widths );
		$specialty_suffix = $specialty ? '_specialty' : '';
		$assets_prefix    = DynamicAssetsUtils::get_dynamic_assets_path();

		// Put default gutter `3` at beginning, otherwise it would mess up the layout.
		if ( in_array( 3, $temp_widths, true ) ) {
			$gutter_widths = array_diff( $temp_widths, [ 3 ] );
			array_unshift( $gutter_widths, 3 );
		}

		// Replace legacy gutter width values of 0 with 1.
		$gutter_widths = str_replace( 0, 1, $gutter_widths );

		for ( $i = 0; $i < $gutter_length; $i++ ) {
			$assets_list[ 'et_divi_gutters' . $gutter_widths[ $i ] ] = [
				'css' => "{$assets_prefix}/css/gutters" . $gutter_widths[ $i ] . "{$this->cache_state->cpt_suffix}.css",
			];

			$assets_list[ 'et_divi_gutters' . $gutter_widths[ $i ] . "{$specialty_suffix}" ] = [
				'css' => "{$assets_prefix}/css/gutters" . $gutter_widths[ $i ] . "{$specialty_suffix}{$this->cache_state->cpt_suffix}.css",
			];

			if ( $grid_items ) {
				$assets_list[ 'et_divi_gutters' . $gutter_widths[ $i ] . '_grid_items' ] = [
					'css' => "{$assets_prefix}/css/gutters" . $gutter_widths[ $i ] . "_grid_items{$this->cache_state->cpt_suffix}.css",
				];

				$assets_list[ 'et_divi_gutters' . $gutter_widths[ $i ] . "{$specialty_suffix}_grid_items" ] = [
					'css' => "{$assets_prefix}/css/gutters" . $gutter_widths[ $i ] . "{$specialty_suffix}_grid_items{$this->cache_state->cpt_suffix}.css",
				];
			}
		}

		return $assets_list;
	}

	/**
	 * Generate flex grid CSS file list when flexbox experiment is enabled.
	 * Note: Flex grid system doesn't use specialty sections or fullwidth modules.
	 *
	 * @since ??
	 *
	 * @param array $responsive_breakpoints Array of responsive breakpoints that need CSS assets.
	 *
	 * @return array $assets_list of flex grid assets
	 */
	public function get_flex_grid_asset_list( array $responsive_breakpoints = [] ): array {
		$assets_prefix = DynamicAssetsUtils::get_dynamic_assets_path();
		$assets_list   = [];

		// Add base flex grid CSS file.
		$assets_list['et_divi_flex_grid'] = [
			'css' => [
				"{$assets_prefix}/css/flex_grid{$this->cache_state->cpt_suffix}.css",
			],
		];

		// Add responsive flex grid CSS files for each breakpoint.
		foreach ( $responsive_breakpoints as $breakpoint ) {
			// Convert breakpoint to lowercase to match the SCSS file name.
			$breakpoint = strtolower( $breakpoint );

			$assets_list[ "et_divi_flex_grid_{$breakpoint}" ] = [
				'css' => "{$assets_prefix}/css/flex_grid_{$breakpoint}{$this->cache_state->cpt_suffix}.css",
			];
		}

		return $assets_list;
	}

	/**
	 * Generate CSS Grid asset list when CSS Grid layout is enabled.
	 * Note: CSS Grid system is used for grid-based layouts with CSS Grid.
	 *
	 * @since ??
	 *
	 * @return array $assets_list of CSS Grid assets
	 */
	public function get_css_grid_asset_list(): array {
		$assets_prefix = DynamicAssetsUtils::get_dynamic_assets_path();
		$assets_list   = [];

		// Add base CSS Grid CSS file.
		$assets_list['et_divi_css_grid'] = [
			'css' => [
				"{$assets_prefix}/css/css_grid_grid{$this->cache_state->cpt_suffix}.css",
			],
		];

		return $assets_list;
	}

	/**
	 * Gets a list of asset files and can be useful for getting all Divi module blocks.
	 *
	 * @since ??
	 *
	 * @param bool $used_modules if blocks are used.
	 *
	 * @return array
	 */
	public function get_block_assets_list( bool $used_modules = true ): array {
		$assets_prefix    = DynamicAssetsUtils::get_dynamic_assets_path();
		$specialty_suffix = '';

		$all_content = $this->content->get_all_content();

		// When on 404 page, we need to get the content from theme builder templates.
		if ( is_404() ) {
			$all_content = $this->content->get_theme_builder_template_content();
		}

		// Read specialty_section from early_attributes (populated by feature detection map) or late detection.
		// Note: On 404 pages, $all_content may be different (theme builder content), so we check late_use_specialty as fallback.
		$specialty_used = $this->_is_feature_detected( 'specialty_section' ) || $this->feature_state->late_use_specialty;

		if ( $specialty_used ) {
			$specialty_suffix = '_specialty';
		}

		$assets_list = DynamicAssetsUtils::get_assets_list(
			[
				'prefix'           => $assets_prefix,
				'suffix'           => $this->cache_state->cpt_suffix,
				'specialty_suffix' => $specialty_suffix,
			]
		);

		// Add block_row.css to divi/row when block layout is enabled.
		// Read block_layout_enabled from early_attributes (populated by feature detection map).
		// Note: On 404 pages, $all_content may be different, but cached value should still be valid.
		if ( $this->_is_feature_detected( 'block_layout_enabled' ) && isset( $assets_list['divi/row'] ) ) {
			// Convert single CSS file to array if needed.
			if ( is_string( $assets_list['divi/row']['css'] ) ) {
				$assets_list['divi/row']['css'] = [ $assets_list['divi/row']['css'] ];
			}

			// Add block_row.css to the CSS array.
			$assets_list['divi/row']['css'][] = "{$assets_prefix}/css/block_row{$this->cache_state->cpt_suffix}.css";
		}

		// Add block_row.css to divi/row-inner when block layout is enabled.
		// Read block_layout_enabled from early_attributes (populated by feature detection map).
		if ( $this->_is_feature_detected( 'block_layout_enabled' ) && isset( $assets_list['divi/row-inner'] ) ) {
			// Convert single CSS file to array if needed.
			if ( is_string( $assets_list['divi/row-inner']['css'] ) ) {
				$assets_list['divi/row-inner']['css'] = [ $assets_list['divi/row-inner']['css'] ];
			}

			// Add block_row.css to the CSS array.
			$assets_list['divi/row-inner']['css'][] = "{$assets_prefix}/css/block_row{$this->cache_state->cpt_suffix}.css";
		}

		// Add D4-specific CSS files for modules when they're used as shortcodes (not blocks).
		// This allows D4 shortcode modules to load legacy CSS while D5 blocks use modern CSS.
		$assets_list = $this->_add_shortcode_specific_assets( $assets_list, $assets_prefix );

		// Initial value for the apply_filters.
		$required_assets = [];

		/**
		 * This filter can be used to force loading of a certain Divi module in case their custom one relies on its styles.
		 *
		 * This filter is the replacement of Divi 4 filter `et_required_module_assets`.
		 *
		 * @since ??
		 *
		 * @param array  $required_assets Custom required module slugs.
		 * @param string $all_content     All content.
		 */
		$required_assets = apply_filters(
			'divi_frontend_assets_dynamic_assets_required_module_assets',
			$required_assets,
			$all_content
		);

		if ( $used_modules ) {
			foreach ( $assets_list as $asset => $asset_data ) {
				if (
					! in_array( $asset, $this->detection_state->processed_modules, true ) &&
					! in_array( $asset, $required_assets, true )
				) {
					unset( $assets_list[ $asset ] );
				}
			}
		}

		return $assets_list;
	}

	/**
	 * Add D4-specific CSS files for modules when they're used as shortcodes.
	 *
	 * This method provides a centralized way to add legacy D4 CSS files to modules
	 * when they are rendered as shortcodes (not D5 blocks). This is useful for
	 * maintaining backward compatibility with D4 layouts while allowing D5 blocks
	 * to use modern CSS.
	 *
	 * To add D4-specific CSS for a module:
	 * 1. Add an entry to the $shortcode_specific_assets array
	 * 2. Map the shortcode tag to its block name and CSS file(s)
	 *
	 * @since ??
	 *
	 * @param array  $assets_list   The current assets list.
	 * @param string $assets_prefix The path prefix for asset files.
	 *
	 * @return array Modified assets list with shortcode-specific CSS added.
	 */
	private function _add_shortcode_specific_assets( array $assets_list, string $assets_prefix ): array {
		// Get all detected shortcodes (cached).
		$all_shortcodes = $this->detection->get_all_shortcodes();

		// Return early if no shortcodes are used.
		if ( empty( $all_shortcodes ) ) {
			return $assets_list;
		}

		/**
		 * Map of shortcode tags to their D4-specific CSS assets.
		 *
		 * Format:
		 * 'shortcode_tag' => [
		 *     'block_name' => 'divi/block-name',  // The D5 block name
		 *     'css_files'  => [ 'file1.css', 'file2.css' ],  // D4-specific CSS files to add
		 * ]
		 *
		 * The CSS files will be added to the module's asset list only when
		 * the shortcode version is detected (not when using the D5 block).
		 */
		$shortcode_specific_assets = [
			'et_pb_signup'               => [
				'block_name' => 'divi/signup',
				'css_files'  => [ 'forms_d4' ],
			],
			'et_pb_contact_form'         => [
				'block_name' => 'divi/contact-form',
				'css_files'  => [ 'forms_d4' ],
			],
			'et_pb_portfolio'            => [
				'block_name' => 'divi/portfolio',
				'css_files'  => [ 'portfolio_d4', 'grid_items_d4' ],
			],
			'et_pb_filterable_portfolio' => [
				'block_name' => 'divi/filterable-portfolio',
				'css_files'  => [ 'portfolio_d4', 'grid_items_d4' ],
			],
			'et_pb_blog'                 => [
				'block_name' => 'divi/blog',
				'css_files'  => [ 'blog_d4', 'grid_items_d4' ],
			],
			'et_pb_gallery'              => [
				'block_name' => 'divi/gallery',
				'css_files'  => [ 'grid_items_d4' ],
			],
			'et_pb_team_member'          => [
				'block_name' => 'divi/team-member',
				'css_files'  => [ 'team_member_d4' ],
			],
			'et_pb_pricing_tables'       => [
				'block_name' => 'divi/pricing-tables',
				'css_files'  => [ 'pricing_tables_d4' ],
			],
		];

		/**
		 * Filters the shortcode-specific assets map.
		 *
		 * Allows third-party developers to add D4-specific CSS for their custom modules
		 * when used as shortcodes.
		 *
		 * @since ??
		 *
		 * @param array $shortcode_specific_assets Map of shortcode tags to their D4-specific assets.
		 * @param array $all_shortcodes            All shortcodes detected on the page.
		 */
		$shortcode_specific_assets = apply_filters(
			'divi_frontend_assets_dynamic_assets_shortcode_specific_assets',
			$shortcode_specific_assets,
			$all_shortcodes
		);

		// Process each shortcode that has specific assets defined.
		foreach ( $shortcode_specific_assets as $shortcode_tag => $config ) {
			// Skip if this shortcode isn't used on the page.
			if ( ! in_array( $shortcode_tag, $all_shortcodes, true ) ) {
				continue;
			}

			$block_name = $config['block_name'] ?? '';
			$css_files  = $config['css_files'] ?? [];

			// Skip if configuration is invalid.
			if ( empty( $block_name ) || empty( $css_files ) || ! isset( $assets_list[ $block_name ] ) ) {
				continue;
			}

			// Ensure the module's CSS is an array.
			if ( is_string( $assets_list[ $block_name ]['css'] ) ) {
				$assets_list[ $block_name ]['css'] = [ $assets_list[ $block_name ]['css'] ];
			}

			// Add each D4-specific CSS file.
			foreach ( $css_files as $css_file ) {
				$css_path = "{$assets_prefix}/css/{$css_file}{$this->cache_state->cpt_suffix}.css";

				// Only add if not already present.
				if ( ! in_array( $css_path, $assets_list[ $block_name ]['css'], true ) ) {
					$assets_list[ $block_name ]['css'][] = $css_path;
				}
			}
		}

		return $assets_list;
	}

	/**
	 * Get custom global assets list.
	 *
	 * @since ??
	 *
	 * @param string $content The content to process.
	 *
	 * @return array
	 */
	public function get_custom_global_assets_list( string $content ): array {
		// Save the current values of some properties.
		$all_content = $this->content->get_all_content();
		$all_modules = $this->detection_state->all_modules;

		if ( '' === $content ) {
			$this->detection_state->all_modules = [];
		}

		// Since `get_global_assets_list` has no parameters, the only way to run it on custom content
		// is to change `_all_content` and `_all_modules`. The current values were previosly saved.
		// and will be restored right after the method call.
		$this->content->set_all_content( $content );
		$list = $this->get_global_assets_list();
		$this->content->set_all_content( $all_content );
		$this->detection_state->all_modules = $all_modules;

		return $list;
	}

	/**
	 * Get global assets data.
	 *
	 * @since ??
	 *
	 * @param object $split_content      Above the fold and Bellow the fold content.
	 * @param array  $global_assets_list List of global assets.
	 *
	 * @return array
	 */
	public function split_global_assets_data( object $split_content, array $global_assets_list ): array {
		// Value for the filter.
		$include = false;

		/**
		 * Filters whether Required Assets should be considered Above The Fold.
		 *
		 * This filter is the replacement of Divi 4 filter `et_dynamic_assets_atf_includes_required`.
		 *
		 * @since ??
		 *
		 * @param bool $include Whether to consider Required Assets Above The Fold or not.
		 */
		$atf_includes_required = apply_filters( 'divi_frontend_assets_dynamic_assets_atf_includes_required', $include );

		$required    = $atf_includes_required ? [] : array_keys( $this->get_custom_global_assets_list( '' ) );
		$content_atf = ! empty( $split_content->atf ) ? $split_content->atf : '';
		$atf         = $this->get_custom_global_assets_list( $content_atf );
		$assets      = $global_assets_list;
		$has_btf     = ! empty( $split_content->btf );

		global $post;

		$post_id = (int) ! empty( $post ) ? $post->ID : 0;

		if ( $post_id > 0 ) {
			// Value for the filter.
			$img_attrs = [];

			/**
			 * Filters omit image attributes.
			 *
			 * This filter is the replacement of Divi 4 filter `et_dynamic_assets_atf_omit_image_attributes`.
			 *
			 * @since ??
			 *
			 * @param array $img_attrs Image attributes.
			 */
			$additional_img_attrs = apply_filters( 'divi_frontend_assets_dynamic_assets_atf_omit_image_attributes', $img_attrs );
			$default_img_attrs    = [
				'src',
				'image_url',
				'image',
				'logo_image_url',
				'header_image_url',
				'logo',
				'portrait_url',
				'image_src',
			];

			if ( ! is_array( $additional_img_attrs ) ) {
				$additional_img_attrs = [];
			}

			$sanitized_additional_img_attrs = [];
			foreach ( $additional_img_attrs as $attr ) {
				$sanitized_additional_img_attrs[] = sanitize_text_field( $attr );
			}

			$img_attrs   = array_merge( $default_img_attrs, $sanitized_additional_img_attrs );
			$img_pattern = '';

			foreach ( $img_attrs as $img_attr ) {
				$or_conj      = ! empty( $img_pattern ) ? '|' : '';
				$img_pattern .= "{$or_conj}({$img_attr}=)";
			}

			$result = preg_match_all( '/' . $img_pattern . '/', $content_atf, $matches );

			$matched_attrs = $result ? count( $matches[0] ) : 0;
			$skip_images   = max( $matched_attrs, 0 );

			if ( $skip_images > 1 ) {
				update_post_meta(
					$post_id,
					'_et_builder_dynamic_assets_loading_attr_threshold',
					$skip_images
				);
			}
		}

		$atf = array_keys( $atf );
		$all = array_keys( $global_assets_list );

		$icon_set   = false;
		$icons_sets = [
			'et_icons_base',
			'et_icons_social',
			'et_icons_all',
		];

		foreach ( $icons_sets as $set ) {
			if ( in_array( $set, $all, true ) ) {
				$icon_set = $set;
				break;
			}
		}

		if ( false !== $icon_set ) {
			$replace = function ( $value ) use ( $icon_set, $icons_sets ) {
				return in_array( $value, $icons_sets, true ) ? $icon_set : $value;
			};
			$atf     = array_values( array_unique( array_map( $replace, $atf ) ) );
			if ( ! empty( $required ) ) {
				$required = array_values( array_unique( array_map( $replace, $required ) ) );
			}
		}

		if ( empty( $required ) ) {
			$atf = array_flip( $atf );
		} else {
			$atf = array_flip( array_diff( $atf, $required ) );
		}

		$atf_assets = [];
		$btf_assets = [];

		foreach ( $assets as $key => $asset ) {
			$has_css     = isset( $asset['css'] );
			$is_required = isset( $required[ $key ] );
			$is_atf      = isset( $atf[ $key ] );
			$is_atf      = $is_atf || ( $atf_includes_required && $is_required );
			$force_defer = $has_btf && isset( $asset['maybe_defer'] );

			// In order for a (global) asset to be considered Above The Fold:
			// 1.0 It needs to include a CSS section (some of the assets are JS only).
			// 2.0 It needs to be used in the ATF Content.
			// 2.1 Or is a required asset (as in always used, doesn't depends on content) and
			// required assets are considered ATF (configurable behaviour via WP filter)
			// 3.0 It needs not be marked as `maybe_defer`, which are basically required assets
			// that will be deferred if the page has Below The Fold Content.
			if ( $has_css && $is_atf && ! $force_defer ) {
				$atf_assets[ $key ]['css'] = $asset['css'];
				unset( $asset['css'] );
			}

			// Some assets are CSS only (no JS), hence if they considered ATF by the previous code
			// there will be nothing else to do for them when processing BTF Content.
			if ( ! empty( $asset ) ) {
				$btf_assets[ $key ] = $asset;
			}
		}

		return [
			'atf' => $atf_assets,
			'btf' => $btf_assets,
		];
	}

	/**
	 * Get block assets data.
	 *
	 * @since ??
	 *
	 * @param array $asset_list Assets list.
	 *
	 * @return array
	 */
	public function get_assets_data( array $asset_list = [] ): array {
		global $wp_filesystem;

		$assets_data           = [];
		$newly_processed_files = [];
		$files_with_url        = [ 'signup', 'icons_base', 'icons_base_social', 'icons_all', 'icons_fa_all' ];
		$no_protocol_path      = str_replace( [ 'http:', 'https:' ], '', $this->cache_state->product_dir );

		foreach ( $asset_list as $asset => $asset_data ) {
			foreach ( $asset_data as $file_type => $files ) {
				$files = (array) $files;

				foreach ( $files as $file ) {
					// Make sure same file's content is not loaded more than once.
					if ( in_array( $file, $this->detection_state->processed_files, true ) ) {
						continue;
					}

					$newly_processed_files[] = $file;

					// For global colors css, we're passing the content instead of file path.
					if ( ( 'et_early_global_colors' === $asset || 'et_late_global_colors' === $asset ) && 'css' === $file_type ) {
						$file_content = $file;
					} else {
						$file_content = $wp_filesystem->get_contents( $file );

						if ( in_array( basename( $file, '.css' ), $files_with_url, true ) ) {
							$file_content = preg_replace( '/#dynamic-product-dir/i', $no_protocol_path, $file_content );
						}

						// Replace hardcoded breakpoint values in flex grid CSS files with custom breakpoint values.
						$file_content = $this->_replace_flex_grid_breakpoints( $file, $file_content );

						$file_content = trim( $file_content );
					}

					if ( empty( $file_content ) ) {
						continue;
					}

					$assets_data[ $file_type ]['assets'][]  = $asset;
					$assets_data[ $file_type ]['content'][] = $file_content;

					// Skip RTL processing for global colors (they're dynamically generated content, not file paths).
					if ( $this->cache_state->is_rtl && ( 'et_early_global_colors' !== $asset && 'et_late_global_colors' !== $asset ) ) {
						$file_rtl = str_replace( ".{$file_type}", "-rtl.{$file_type}", $file );

						if ( file_exists( $file_rtl ) ) {
							$file_content_rtl = $wp_filesystem->get_contents( $file_rtl );

							if ( in_array( basename( $file, '.css' ), $files_with_url, true ) ) {
								$file_content_rtl = preg_replace( '/#dynamic-product-dir/i', $no_protocol_path, $file_content_rtl );
							}

							// Replace hardcoded breakpoint values in flex grid CSS files with custom breakpoint values.
							$file_content_rtl = $this->_replace_flex_grid_breakpoints( $file_rtl, $file_content_rtl );

							$file_content_rtl = trim( $file_content_rtl );

							// Only add RTL content if it's not empty.
							if ( ! empty( $file_content_rtl ) ) {
								$assets_data[ $file_type ]['assets'][]  = "{$asset}-rtl";
								$assets_data[ $file_type ]['content'][] = $file_content_rtl;
							}
						}
					}
				}
			}
		}

		$this->detection_state->processed_files = DynamicAssetsUtils::get_unique_array_values( $this->detection_state->processed_files, $newly_processed_files );

		return $assets_data;
	}

	/**
	 * Replace hardcoded breakpoint values in flex grid CSS files with custom breakpoint values.
	 *
	 * This method replaces the hardcoded media query breakpoint values (e.g., 980px, 767px)
	 * in pre-compiled flex grid CSS files with the user's custom breakpoint values from
	 * the breakpoint settings. This ensures column classes respect customized breakpoint widths.
	 *
	 * @since ??
	 *
	 * @param string $file         The file path being processed.
	 * @param string $file_content The CSS content to process.
	 *
	 * @return string The CSS content with breakpoint values replaced.
	 */
	private function _replace_flex_grid_breakpoints( string $file, string $file_content ): string {
		// Get the file basename without extension, -rtl suffix, and _cpt suffix.
		$basename = basename( $file, '.css' );
		$basename = preg_replace( '/-rtl$/', '', $basename );
		$basename = preg_replace( '/_cpt$/', '', $basename );

		// Map of flex grid file basenames to their breakpoint names and default values.
		// Format: 'file_basename' => ['breakpoint_name', 'default_value', 'width_type'].
		$flex_grid_breakpoint_map = [
			'flex_grid_phone'      => [ 'phone', '767px', 'maxWidth' ],
			'flex_grid_phonewide'  => [ 'phoneWide', '860px', 'maxWidth' ],
			'flex_grid_tablet'     => [ 'tablet', '980px', 'maxWidth' ],
			'flex_grid_tabletwide' => [ 'tabletWide', '1024px', 'maxWidth' ],
			'flex_grid_widescreen' => [ 'widescreen', '1280px', 'minWidth' ],
			'flex_grid_ultrawide'  => [ 'ultraWide', '1440px', 'minWidth' ],
		];

		// Check if this file is a flex grid responsive CSS file.
		if ( ! isset( $flex_grid_breakpoint_map[ $basename ] ) ) {
			return $file_content;
		}

		[ $breakpoint_name, $default_value, $width_type ] = $flex_grid_breakpoint_map[ $basename ];

		// Get the custom breakpoint settings.
		$breakpoint_settings = Breakpoint::get_settings_values();
		$breakpoint_item     = $breakpoint_settings['items'][ $breakpoint_name ] ?? null;

		if ( ! $breakpoint_item ) {
			return $file_content;
		}

		// Get the custom value for this breakpoint.
		$custom_value = $breakpoint_item[ $width_type ]['value'] ?? null;

		// If no custom value or it matches the default, no replacement needed.
		if ( ! $custom_value || $custom_value === $default_value ) {
			return $file_content;
		}

		// Build the regex pattern to match the media query with the default breakpoint value.
		// Pattern matches: @media only screen and (max-width: 980px) or @media only screen and (min-width: 1280px).
		$width_property = 'maxWidth' === $width_type ? 'max-width' : 'min-width';
		// regex101:https://regex101.com/r/z89Zmn/1.
		$pattern     = '/@media\s+only\s+screen\s+and\s+\(' . preg_quote( $width_property, '/' ) . ':\s*' . preg_quote( $default_value, '/' ) . '\)/i';
		$replacement = "@media only screen and ({$width_property}: {$custom_value})";

		return preg_replace( $pattern, $replacement, $file_content );
	}

	/**
	 * Gets a list of global asset files.
	 *
	 * @since ??
	 *
	 * @param array $global_list List of globally needed assets.
	 *
	 * @return array
	 */
	public function divi_get_global_assets_list( array $global_list ): array {
		$post_id                = get_the_ID();
		$assets_list            = [];
		$assets_prefix          = get_template_directory() . '/css/dynamic-assets';
		$js_assets_prefix       = get_template_directory() . '/js/src/dynamic-assets';
		$shared_assets_prefix   = get_template_directory() . '/includes/builder/feature/dynamic-assets/assets';
		$is_page_builder_used   = et_pb_is_pagebuilder_used( $post_id );
		$side_nav               = get_post_meta( $post_id, '_et_pb_side_nav', true );
		$has_tb_header          = false;
		$has_tb_body            = false;
		$has_tb_footer          = false;
		$layouts                = et_theme_builder_get_template_layouts();
		$is_blank_page_tpl      = is_page_template( 'page-template-blank.php' );
		$vertical_nav           = et_get_option( 'vertical_nav', false );
		$header_style           = et_get_option( 'header_style', 'left' );
		$et_slide_header        = in_array( $header_style, [ 'slide', 'fullscreen' ], true );
		$color_scheme           = et_get_option( 'color_schemes', 'none' );
		$gutter_width           = (string) $this->_get_effective_page_gutter_width( (int) $post_id );
		$back_to_top            = et_get_option( 'divi_back_to_top', 'false' );
		$et_secondary_nav_items = et_divi_get_top_nav_items();
		$et_top_info_defined    = $et_secondary_nav_items->top_info_defined;
		$button_icon            = et_get_option( 'all_buttons_selected_icon', '5' );
		$page_layout            = get_post_meta( $post_id, '_et_pb_page_layout', true );

		if ( ! empty( $layouts ) ) {
			if ( $layouts[ ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE ]['override'] ) {
				$has_tb_header = true;
			}
			if ( $layouts[ ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE ]['override'] ) {
				$has_tb_body = true;
			}
			if ( $layouts[ ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE ]['override'] ) {
				$has_tb_footer = true;
			}
		}

		if ( '5' !== $button_icon ) {
			$assets_list['et_icons'] = [
				'css' => "{$shared_assets_prefix}/css/icons_all.css",
			];
		}

		if ( ! $has_tb_header && ! $is_blank_page_tpl ) {
			$assets_list['et_divi_header'] = [
				'css' => [
					"{$assets_prefix}/header.css",
					"{$shared_assets_prefix}/css/header_animations.css",
					"{$shared_assets_prefix}/css/header_shared.css",
				],
			];

			if ( et_divi_is_transparent_primary_nav() ) {
				$assets_list['et_divi_transparent_nav'] = [
					'css' => "{$assets_prefix}/transparent_nav.css",
				];
			}

			if ( $et_top_info_defined && ! $et_slide_header ) {
				$assets_list['et_divi_secondary_nav'] = [
					'css' => "{$assets_prefix}/secondary_nav.css",
				];
			}

			switch ( $header_style ) {
				case 'slide':
					$assets_list['et_divi_header_slide_in'] = [
						'css' => "{$assets_prefix}/slide_in_menu.css",
					];
					break;

				case 'fullscreen':
					$assets_list['et_divi_header_fullscreen'] = [
						'css' => [
							"{$assets_prefix}/slide_in_menu.css",
							"{$assets_prefix}/fullscreen_header.css",
						],
					];
					break;

				case 'centered':
					$assets_list['et_divi_header_centered'] = [
						'css' => "{$assets_prefix}/centered_header.css",
					];
					break;

				case 'split':
					$assets_list['et_divi_header_split'] = [
						'css' => [
							"{$assets_prefix}/centered_header.css",
							"{$assets_prefix}/split_header.css",
						],
					];
					break;

				default:
					break;
			}

			if ( $vertical_nav ) {
				$assets_list['et_divi_vertical_nav'] = [
					'css' => "{$assets_prefix}/vertical_nav.css",
				];
			}
		}

		if ( ! $has_tb_footer && ! $is_blank_page_tpl ) {
			$assets_list['et_divi_footer'] = [
				'css' => "{$assets_prefix}/footer.css",
			];

			$assets_list['et_divi_gutters_footer'] = [
				'css' => "{$assets_prefix}/gutters{$gutter_width}_footer.css",
			];
		}

		if ( ( ! $has_tb_header || ! $has_tb_footer ) && ! $is_blank_page_tpl ) {
			$assets_list['et_divi_social_icons'] = [
				'css' => "{$assets_prefix}/social_icons.css",
			];
		}

		if ( et_divi_is_boxed_layout() ) {
			$assets_list['et_divi_boxed_layout'] = [
				'css' => "{$assets_prefix}/boxed_layout.css",
			];
		}

		if ( is_singular( 'project' ) ) {
			$assets_list['et_divi_project'] = [
				'css' => "{$assets_prefix}/project.css",
			];
		}

		if ( $is_page_builder_used && is_single() ) {
			$assets_list['et_divi_pagebuilder_posts'] = [
				'css' => "{$assets_prefix}/pagebuilder_posts.css",
			];
		}

		if ( // Sidebar exists on the homepage blog feed.
			( is_home() )
			// Sidebar exists on all non-singular pages, such as categories, except when using a theme builder template.
			|| ( ! is_singular() && ! $has_tb_body )
			// Sidebar exists on posts, except when using a theme builder body template or a page template that doesn't include a sidebar.
			|| ( is_single() && ! $has_tb_body && ! in_array( $page_layout, [ 'et_full_width_page', 'et_no_sidebar' ], true ) )
			// Sidebar is used on pages when the builder is disabled.
			|| ( ( is_page() || is_front_page() ) && ! $has_tb_body && ! $is_page_builder_used && ! in_array( $page_layout, [ 'et_full_width_page', 'et_no_sidebar' ], true ) )
		) {
			$assets_list['et_divi_sidebar'] = [
				'css' => "{$assets_prefix}/sidebar.css",
			];
		}

		if ( ( is_single() || is_page() || is_home() ) && comments_open( $post_id ) ) {
			$assets_list['et_divi_comments'] = [
				'css' => [
					"{$assets_prefix}/comments.css",
					"{$shared_assets_prefix}/css/comments_shared.css",
				],
			];
		}

		if ( DynamicAssetsUtils::has_builder_widgets() ) {
			$assets_list['et_divi_widgets_shared'] = [
				'css' => "{$shared_assets_prefix}/css/widgets_shared.css",
			];
		}

		if (
			is_active_widget( false, false, 'calendar' ) || DynamicAssetsUtils::is_active_block_widget( 'core/calendar' )
		) {
			$assets_list['et_divi_widget_calendar'] = [
				'css' => "{$assets_prefix}/widget_calendar.css",
			];
		}

		if (
			is_active_widget( false, false, 'search' ) || DynamicAssetsUtils::is_active_block_widget( 'core/search' )
		) {
			$assets_list['et_divi_widget_search'] = [
				'css' => "{$assets_prefix}/widget_search.css",
			];
		}

		if (
			is_active_widget( false, false, 'tag_cloud' ) || DynamicAssetsUtils::is_active_block_widget( 'core/tag-cloud' )
		) {
			$assets_list['et_divi_widget_tag_cloud'] = [
				'css' => "{$assets_prefix}/widget_tag_cloud.css",
			];
		}

		if (
			is_active_widget( false, false, 'media_gallery' ) || DynamicAssetsUtils::is_active_block_widget( 'core/gallery' )
		) {
			$assets_list['et_divi_widget_gallery'] = [
				'css' => [
					"{$shared_assets_prefix}/css/wp_gallery.css",
					"{$shared_assets_prefix}/css/magnific_popup.css",
				],
			];
		}

		if ( is_active_widget( false, false, 'aboutmewidget' ) ) {
			$assets_list['et_divi_widget_about'] = [
				'css' => "{$assets_prefix}/widget_about.css",
			];
		}

		if ( ( is_singular() || is_home() || is_front_page() ) && 'on' === $side_nav && $is_page_builder_used ) {
			$assets_list['et_divi_side_nav'] = [
				'css' => "{$assets_prefix}/side_nav.css",
			];
		}

		if ( 'on' === $back_to_top ) {
			$assets_list['et_divi_back_to_top'] = [
				'css' => "{$assets_prefix}/back_to_top.css",
			];
		}

		if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$assets_list['et_divi_woocommerce'] = [
				'css' => [
					"{$assets_prefix}/woocommerce.css",
					"{$shared_assets_prefix}/css/woocommerce_shared.css",
				],
			];
		}

		if ( ! is_customize_preview() && 'none' !== $color_scheme ) {
			$assets_list['et_color_scheme'] = [
				'css' => "{$assets_prefix}/color_scheme_{$color_scheme}.css",
			];
		}

		if ( is_rtl() ) {
			$assets_list['et_divi_rtl'] = [
				'css' => "{$assets_prefix}/rtl.css",
			];
		}

		return array_merge( $global_list, $assets_list );
	}
}
