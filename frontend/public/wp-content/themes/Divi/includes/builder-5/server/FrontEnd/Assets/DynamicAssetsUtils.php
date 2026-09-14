<?php
/**
 * Utils class for Dynamic Assets.
 *
 * This file combines the logic from the following Divi-4 file:
 * - includes/builder/feature/dynamic-assets/dynamic-assets.php
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\FrontEnd\Assets;

use ET\Builder\Framework\Utility\ArrayUtility;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\StringUtility;
use ET\Builder\FrontEnd\Assets\DetectFeature;
use ET\Builder\FrontEnd\Module\ScriptData;
use ET\Builder\Packages\GlobalData\GlobalPreset;
use ET\Builder\Framework\Settings\Settings;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use ET\Builder\Packages\ModuleUtils\CanvasUtils;
use ET\Builder\FrontEnd\BlockParser\SimpleBlockParser;
use ET\Builder\VisualBuilder\OffCanvas\OffCanvasHooks;
use ET_GB_Block_Layout;
use ET_Post_Stack;

/**
 * Utils CLass.
 *
 * @since ??
 */
class DynamicAssetsUtils {

	/**
	 * Cache for batch post meta queries.
	 * Prevents redundant database queries during the same request.
	 *
	 * @since ??
	 * @var array
	 */
	private static $_batch_post_meta_cache = [];


	/**
	 * In-memory static cache for canvas data per post.
	 * Prevents redundant calls to get_all_canvas_data_for_post() within the same request.
	 *
	 * @since ??
	 * @var array
	 */
	private static $_canvas_data_static_cache = [];

	/**
	 * In-memory static cache for canvas post objects per post.
	 * Prevents redundant get_posts() queries when post objects are needed.
	 *
	 * @since ??
	 * @var array Map of post_id => array of post objects.
	 */
	private static $_canvas_posts_static_cache = [];

	/**
	 * In-memory cache for local canvas posts grouped by owner IDs.
	 * Prevents redundant multi-owner lookups across header/body/footer/post contexts.
	 *
	 * @since ??
	 * @var array Map of owner-set key => array{posts: array}.
	 */
	private static $_local_canvas_posts_by_owner_set_cache = [];

	/**
	 * Memoized Dynamic Assets front-end request state.
	 *
	 * @since ??
	 * @var bool|null
	 */
	private static $_is_dynamic_front_end_request = null;

	/**
	 * Memoized Dynamic Assets generation gate state.
	 *
	 * Stores the final filtered decision for the current request so hot paths
	 * such as render_block_data / pre_do_shortcode_tag callbacks do not re-run
	 * apply_filters() on every block or shortcode.
	 *
	 * @since ??
	 * @var bool|null
	 */
	private static $_should_generate_dynamic_assets = null;

	/**
	 * Memoized Dynamic Assets usage gate state.
	 *
	 * @since ??
	 * @var bool|null
	 */
	private static $_use_dynamic_assets = null;

	/**
	 * An associative array mapping Divi block module identifiers to their corresponding
	 * WooCommerce shortcode module identifiers.
	 *
	 * @var string[]
	 *
	 * @since ??
	 */
	public static $woocommerce_modules_map = [
		'divi/shop'                                 => 'et_pb_shop',
		'divi/woocommerce-breadcrumb'               => 'et_pb_wc_breadcrumb',
		'divi/woocommerce-cart-notice'              => 'et_pb_wc_cart_notice',
		'divi/woocommerce-cart-products'            => 'et_pb_wc_cart_products',
		'divi/woocommerce-cart-totals'              => 'et_pb_wc_cart_totals',
		'divi/woocommerce-checkout-additional-info' => 'et_pb_wc_checkout_additional_info',
		'divi/woocommerce-checkout-billing'         => 'et_pb_wc_checkout_billing',
		'divi/woocommerce-checkout-order-details'   => 'et_pb_wc_checkout_order_details',
		'divi/woocommerce-checkout-payment-info'    => 'et_pb_wc_checkout_payment_info',
		'divi/woocommerce-checkout-shipping'        => 'et_pb_wc_checkout_shipping',
		'divi/woocommerce-cross-sells'              => 'et_pb_wc_cross_sells',
		'divi/woocommerce-product-add-to-cart'      => 'et_pb_wc_add_to_cart',
		'divi/woocommerce-product-additional-info'  => 'et_pb_wc_additional_info',
		'divi/woocommerce-product-description'      => 'et_pb_wc_description',
		'divi/woocommerce-product-gallery'          => 'et_pb_wc_gallery',
		'divi/woocommerce-product-images'           => 'et_pb_wc_images',
		'divi/woocommerce-product-meta'             => 'et_pb_wc_meta',
		'divi/woocommerce-product-price'            => 'et_pb_wc_price',
		'divi/woocommerce-product-rating'           => 'et_pb_wc_rating',
		'divi/woocommerce-product-reviews'          => 'et_pb_wc_reviews',
		'divi/woocommerce-product-stock'            => 'et_pb_wc_stock',
		'divi/woocommerce-product-tabs'             => 'et_pb_wc_tabs',
		'divi/woocommerce-product-title'            => 'et_pb_wc_title',
		'divi/woocommerce-product-upsell'           => 'et_pb_wc_upsells',
		'divi/woocommerce-related-products'         => 'et_pb_wc_related_products',
	];

	/**
	 * Memoized result for disable_js_on_demand().
	 *
	 * Cleared by DynamicAssetsUtils::reset() so filter/request-context changes
	 * can be re-evaluated in the same process (e.g. PHPUnit).
	 *
	 * @since ??
	 *
	 * @var bool|null
	 */
	private static $_disable_js_on_demand = null;

	/**
	 * Check if JavaScript On Demand is enabled.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function disable_js_on_demand(): bool {

		// phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
		// TODO feat(D5, Dynamic Assets) Remove this or deprecate the function during Divi 5 test.
		// We are temporarily returning overriding this function to force Dynamic Assets to be on to improve performance.
		// Treat VB (including filtered VB test context) like FB so script enqueue can opt into disable_js_on_demand.
		if ( ! Conditions::is_vb_enabled() && ! is_preview() && ! is_customize_preview() ) {
			return false;
		}

		global $shortname;

		if ( null === self::$_disable_js_on_demand ) {
			if ( et_is_builder_plugin_active() ) {
				$options              = get_option( 'et_pb_builder_options', [] );
				$dynamic_js_libraries = $options['performance_main_dynamic_js_libraries'] ?? 'on';
			} else {
				$dynamic_js_libraries = et_get_option( $shortname . '_dynamic_js_libraries', 'on' );
			}

			if ( // Disable when theme option not enabled.
				'on' !== $dynamic_js_libraries
				// Disable when not applicable front-end request.
				|| ! self::is_dynamic_front_end_request()
			) {
				self::$_disable_js_on_demand = true;
			} else {
				self::$_disable_js_on_demand = false;
			}

			/**
			 * Filters whether to disable JS on demand.
			 *
			 * This filter is the replacement of Divi 4 filter `et_disable_js_on_demand`.
			 *
			 * @since ??
			 *
			 * @param bool $et_disable_js_on_demand
			 */
			self::$_disable_js_on_demand = apply_filters(
				'divi_frontend_assets_dynamic_assets_utils_disable_js_on_demand',
				(bool) self::$_disable_js_on_demand
			);
		}

		return self::$_disable_js_on_demand;
	}

	/**
	 * Ensure cache directory exists.
	 *
	 * @since ??
	 */
	public static function ensure_cache_directory_exists() {
		// Create the base cache directory, if not exists already.
		$cache_dir = et_core_cache_dir()->path;

		et_()->ensure_directory_exists( $cache_dir );
	}

	/**
	 * Enqueues D5 Easypiechart script, and dequeues D4 version of the Easypiechart script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_easypiechart_script() {
		wp_dequeue_script( 'easypiechart' );
		wp_deregister_script( 'easypiechart' );

		wp_enqueue_script(
			'easypiechart',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-easypiechart.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 toggle script, used for toggle and accordion modules.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_toggle_script() {
		wp_enqueue_script(
			'divi-script-library-toggle',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-toggle.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 tooltip module script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_tooltip_script() {
		wp_enqueue_script(
			'divi-module-library-script-tooltip',
			ET_BUILDER_5_URI . '/visual-builder/build/module-library-script-tooltip.js',
			[ 'jquery', 'divi-script-library' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 dropdown script, used for dropdown modules.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_dropdown_script() {
		wp_enqueue_script(
			'divi-script-library-dropdown',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-dropdown.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 audio script, used for audio modules and audio post types.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_audio_script() {
		// Enqueue WordPress MediaElement.js library (required for audio player initialization).
		wp_enqueue_style( 'wp-mediaelement' );
		wp_enqueue_script( 'wp-mediaelement' );

		wp_enqueue_script(
			'divi-script-library-audio',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-audio.js',
			[ 'jquery', 'wp-mediaelement' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 video overlay script, used for video/blog modules and on video post formats.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_video_overlay_script() {
		wp_enqueue_script(
			'divi-script-library-video-overlay',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-video-overlay.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 search script, used for search modules.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_search_script() {
		wp_enqueue_script(
			'divi-script-library-search',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-search.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 woo script, used for woo modules.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_woo_script() {
		wp_enqueue_script(
			'divi-script-library-woo',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-woo.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues WooCommerce cart scripts for Cart Products modules.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_woocommerce_cart_scripts() {
		wp_enqueue_script( 'wc-cart' );
		wp_enqueue_script( 'wc-add-to-cart' );
	}

	/**
	 * Enqueues D5 WooCommerce cart totals script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_woocommerce_cart_totals_script() {
		$dependencies = [ 'jquery' ];

		// Cart totals module includes shipping calculator which requires wc-country-select.
		// Only add as dependency if WooCommerce has registered it (WooCommerce registers
		// all scripts during initialization, but wc-country-select is only enqueued conditionally).
		if ( wp_script_is( 'wc-country-select', 'registered' ) ) {
			$dependencies[] = 'wc-country-select';
			// Ensure it's enqueued since cart totals includes shipping calculator functionality.
			if ( ! wp_script_is( 'wc-country-select', 'enqueued' ) ) {
				wp_enqueue_script( 'wc-country-select' );
			}
		}

		wp_enqueue_script(
			'divi-module-library-script-woocommerce-cart-totals',
			ET_BUILDER_5_URI . '/visual-builder/build/module-library-script-woocommerce-cart-totals.js',
			$dependencies,
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Returns the list of WooCommerce module dependencies for dynamic assets.
	 *
	 * @since ??
	 *
	 * @return string[] Array of WooCommerce module block names.
	 */
	public static function woo_deps(): array {
		return [
			'divi/shop',
			'divi/woocommerce-product-add-to-cart',
			'divi/woocommerce-product-upsell',
			'divi/woocommerce-related-products',
			'divi/woocommerce-cart-totals',
			'divi/woocommerce-product-meta',
			'divi/woocommerce-product-rating',
			'divi/woocommerce-checkout-shipping',
			'divi/woocommerce-checkout-payment-info',
			'divi/woocommerce-checkout-billing',
			'divi/woocommerce-cart-notice',
			'divi/woocommerce-cart-products',
			'divi/woocommerce-checkout-order-details',
			'divi/woocommerce-product-images',
		];
	}

	/**
	 * Enqueues D5 fullwidth header script, used for fullwidth header modules.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_fullwidth_header_script() {
		wp_enqueue_script(
			'divi-script-library-fullwidth-header',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-fullwidth-header.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 blog script, used for modules with ajax blog.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_blog_script() {
		wp_enqueue_script(
			'divi-module-library-script-blog',
			ET_BUILDER_5_URI . '/visual-builder/build/module-library-script-blog.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 pagination script, used for modules with ajax pagination.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_pagination_script() {
		wp_enqueue_script(
			'divi-script-library-pagination',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-pagination.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 fullscreen section script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_fullscreen_section_script() {
		wp_enqueue_script(
			'divi-script-library-fullscreen-section',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-fullscreen-section.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 section dividers script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_section_dividers_script() {
		wp_enqueue_script(
			'divi-script-library-section-dividers',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-section-dividers.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 link script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_link_script() {
		wp_enqueue_script(
			'divi-script-library-link',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-link.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 slider script, used for slider modules.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_slider_script() {
		wp_enqueue_script(
			'divi-script-library-slider',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-slider.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 before-after-image script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_before_after_image_script() {
		wp_enqueue_script(
			'divi-module-library-script-before-after-image',
			ET_BUILDER_5_URI . '/visual-builder/build/module-library-script-before-after-image.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 map script, used for map modules.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_map_script() {
		wp_enqueue_script(
			'divi-script-library-map',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-map.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 sidebar script, used for sidebar modules.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_sidebar_script() {
		wp_enqueue_script(
			'divi-script-library-sidebar',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-sidebar.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 testimonial script, used for testimonial modules.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_testimonial_script() {
		wp_enqueue_script(
			'divi-module-library-script-testimonial',
			ET_BUILDER_5_URI . '/visual-builder/build/module-library-script-testimonial.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);

		wp_enqueue_script(
			'divi-script-library-testimonial',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-testimonial.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 comments script, used for comments modules.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_comments_script() {
		wp_enqueue_script(
			'divi-script-library-comments',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-comments.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 tabs script, used for tabs modules and WooCommerce product pages.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_tabs_script() {
		wp_enqueue_script(
			'divi-script-library-tabs',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-tabs.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 table of contents script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_table_of_contents_script() {
		wp_enqueue_script(
			'divi-module-library-script-table-of-contents',
			ET_BUILDER_5_URI . '/visual-builder/build/module-library-script-table-of-contents.js',
			[ 'jquery', 'divi-script-library' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 fullwidth portfolio script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_fullwidth_portfolio_script() {
		wp_enqueue_script(
			'divi-script-library-fullwidth-portfolio',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-fullwidth-portfolio.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 filterable portfolio script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_filterable_portfolio_script() {
		wp_enqueue_script(
			'divi-script-library-filterable-portfolio',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-filterable-portfolio.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 video slider script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_video_slider_script() {
		wp_enqueue_script(
			'divi-script-library-video-slider',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-video-slider.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 video lazy load script.
	 *
	 * This script handles lazy loading of videos that are below the fold
	 * by converting data-src attributes to src when videos are about to enter the viewport.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_video_lazy_load_script() {
		wp_enqueue_script(
			'divi-script-library-video-lazy-load',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-video-lazy-load.js',
			[], // No dependencies - uses native IntersectionObserver API.
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 signup script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_signup_script() {
		wp_enqueue_script(
			'divi-module-library-script-signup',
			ET_BUILDER_5_URI . '/visual-builder/build/module-library-script-signup.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 countdown timer script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_countdown_timer_script() {
		wp_enqueue_script(
			'divi-script-library-countdown-timer',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-countdown-timer.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 bar counter script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_bar_counter_script() {
		wp_enqueue_script(
			'divi-module-library-script-counter',
			ET_BUILDER_5_URI . '/visual-builder/build/module-library-script-counter.js',
			[
				'jquery',
				'easypiechart',
			],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 circle counter script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_circle_counter_script() {
		wp_enqueue_script(
			'divi-module-library-script-circle-counter',
			ET_BUILDER_5_URI . '/visual-builder/build/module-library-script-circle-counter.js',
			[
				'jquery',
				'easypiechart',
			],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 number counter script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_number_counter_script() {
		wp_enqueue_script(
			'divi-module-library-script-number-counter',
			ET_BUILDER_5_URI . '/visual-builder/build/module-library-script-number-counter.js',
			[
				'jquery',
				'easypiechart',
			],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 contact form script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_contact_form_script() {
		wp_enqueue_script(
			'divi-module-library-script-contact-form',
			ET_BUILDER_5_URI . '/visual-builder/build/module-library-script-contact-form.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 post filter item script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_post_filter_item_script() {
		wp_enqueue_script(
			'divi-module-library-script-post-filter-item',
			ET_BUILDER_5_URI . '/visual-builder/build/module-library-script-post-filter-item.js',
			[],
			ET_CORE_VERSION,
			true
		);

		wp_add_inline_script(
			'divi-module-library-script-post-filter-item',
			'window.diviModulePostFilterItemRangeInit&&window.diviModulePostFilterItemRangeInit();window.diviModulePostFilterItemMultipleOrderInit&&window.diviModulePostFilterItemMultipleOrderInit();',
			'after'
		);
	}

	/**
	 * Enqueues D5 post filter script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_post_filter_script() {
		wp_enqueue_script(
			'divi-module-library-script-post-filter',
			ET_BUILDER_5_URI . '/visual-builder/build/module-library-script-post-filter.js',
			[ 'divi-module-library-script-post-filter-item' ],
			ET_CORE_VERSION,
			true
		);

		wp_add_inline_script(
			'divi-module-library-script-post-filter',
			'window.diviModulePostFilterAutoInit&&window.diviModulePostFilterAutoInit();window.diviModulePostFilterSubmitInit&&window.diviModulePostFilterSubmitInit();window.diviModulePostFilterResetInit&&window.diviModulePostFilterResetInit();',
			'after'
		);
	}

	/**
	 * Enqueues D5 form conditions script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_form_conditions_script() {
		wp_enqueue_script(
			'divi-script-library-form-conditions',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-form-conditions.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 split testing script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_split_testing_script() {
		wp_enqueue_script(
			'divi-script-library-split-testing',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-split-testing.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 menu module script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_menu_script() {
		wp_enqueue_script(
			'divi-script-library-menu',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-menu.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 animation module script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_animation_script() {
		wp_enqueue_script(
			'divi-script-library-animation',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-animation.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 interactions module script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_interactions_script() {
		// Ensure script is registered before enqueuing (required for wp_localize_script to work).
		if ( ! wp_script_is( 'divi-script-library-interactions', 'registered' ) ) {
			wp_register_script(
				'divi-script-library-interactions',
				ET_BUILDER_5_URI . '/visual-builder/build/script-library-interactions.js',
				[ 'jquery' ],
				ET_CORE_VERSION,
				true
			);
		}
		wp_enqueue_script( 'divi-script-library-interactions' );
	}

	/**
	 * Enqueues D5 gallery module script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_gallery_script() {
		wp_enqueue_script(
			'divi-script-library-gallery',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-gallery.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 scripts only needed when logged in.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_logged_in_script() {
		wp_enqueue_script(
			'divi-script-library-logged-in',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-logged-in.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 fitvids script, and dequeues D4 version of the fitvids script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_fitvids_script() {
		wp_dequeue_script( 'fitvids' );
		wp_deregister_script( 'fitvids' );

		wp_enqueue_script(
			'fitvids',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-jquery.fitvids.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);

		wp_enqueue_script(
			'divi-script-library-fitvids-functions',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-fitvids-functions.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 jquery-mobile script, and dequeues D4 version of the jquery-mobile script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_jquery_mobile_script() {
		wp_dequeue_script( 'jquery-mobile' );
		wp_deregister_script( 'jquery-mobile' );

		wp_enqueue_script(
			'jquery-mobile',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-jquery.mobile.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 magnific-popup script, and dequeues D4 version of the magnific-popup script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_magnific_popup_script() {
		wp_dequeue_script( 'magnific-popup' );
		wp_deregister_script( 'magnific-popup' );

		wp_enqueue_script(
			'magnific-popup',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-magnific-popup.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 salvattore script, and dequeues D4 version of the salvattore script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_salvattore_script() {
		wp_dequeue_script( 'salvattore' );
		wp_deregister_script( 'salvattore' );

		wp_enqueue_script(
			'salvattore',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-salvattore.js',
			[ 'jquery', 'divi-script-library' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 Google Maps API script, and dequeues D4 version of the Google Maps API script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_google_maps_script() {
		wp_dequeue_script( 'google-maps-api' );
		wp_deregister_script( 'google-maps-api' );

		wp_enqueue_script(
			'google-maps-api',
			esc_url(
				add_query_arg(
					[
						'key'       => et_pb_get_google_api_key(),
						'libraries' => 'marker',
						'loading'   => 'async',
					],
					is_ssl() ? 'https://maps.googleapis.com/maps/api/js' : 'http://maps.googleapis.com/maps/api/js'
				)
			),
			[],
			'3',
			true
		);
	}

	/**
	 * Enqueues D5 scroll-effects script, and dequeues D4 version of the scroll-effects script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_scroll_script() {
		wp_dequeue_script( 'et-builder-modules-script-motion' );
		wp_deregister_script( 'et-builder-modules-script-motion' );

		// Enqueue scroll-effects js.
		wp_enqueue_script(
			'et-builder-modules-script-motion',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-motion-effects.js',
			[
				'jquery',
				et_get_combined_script_handle(),
			],
			ET_CORE_VERSION,
			true
		);

		// if the shortcode framework is loaded, localize the motion elements.
		if ( et_is_shortcode_framework_loaded() ) {
			wp_localize_script(
				'et-builder-modules-script-motion',
				'et_pb_motion_elements',
				\ET_Builder_Element::$_scroll_effects_fields
			);
		}

		ScriptData::enqueue_data( 'scroll' );
	}

	/**
	 * Enqueues D5 sticky script, and dequeues D4 version of the sticky script.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_sticky_script() {
		wp_dequeue_script( 'et-builder-modules-script-sticky' );
		wp_deregister_script( 'et-builder-modules-script-sticky' );

		wp_enqueue_script(
			'et-builder-modules-script-sticky',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-sticky-elements.js',
			[
				'jquery',
				et_get_combined_script_handle(),
			],
			ET_CORE_VERSION,
			true
		);

		// if the shortcode framework is loaded, localize the motion elements.
		if ( et_is_shortcode_framework_loaded() ) {
			wp_localize_script(
				'et-builder-modules-script-sticky',
				'et_pb_sticky_elements',
				\ET_Builder_Element::$sticky_elements
			);
		}

		ScriptData::enqueue_data( 'sticky' );
	}

	/**
	 * Enqueues D5 `et_get_combined_script_handle()` script.
	 *
	 * Fix for issue #43883: Conditionally ensure proper script loading order by making
	 * combined script depend on link script only when links are actually used on the page.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_combined_script() {
		wp_dequeue_script( et_get_combined_script_handle() );
		wp_deregister_script( et_get_combined_script_handle() );

		// Check if DynamicAssets has determined that link scripts are needed.
		$needs_link_dependency = self::should_add_link_dependency();
		$dependencies          = [ 'jquery' ];

		// Only add link script dependency when links are actually needed.
		if ( $needs_link_dependency ) {
			self::enqueue_link_script();
			$dependencies[] = 'divi-script-library-link';
		}

		// Enqueue combined script with conditional dependencies.
		wp_enqueue_script(
			et_get_combined_script_handle(),
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-frontend-scripts.js',
			$dependencies,
			ET_CORE_VERSION,
			true
		);
	}


	/**
	 * Check if we should add link script dependency based on DynamicAssets detection.
	 *
	 * This method trusts DynamicAssets' comprehensive detection completely. If DynamicAssets
	 * found links with its comprehensive analysis (Theme Builder, global modules, etc.),
	 * the link script will be registered.
	 *
	 * @since ??
	 *
	 * @return bool True if link script dependency should be added.
	 */
	public static function should_add_link_dependency(): bool {
		// Trust DynamicAssets' comprehensive detection - if it found links, script will be registered.
		return wp_script_is( 'divi-script-library-link', 'enqueued' ) || wp_script_is( 'divi-script-library-link', 'registered' );
	}


	/**
	 * Get Extra Taxonomy layout ID.
	 *
	 * @since ??
	 *
	 * @return int|null
	 */
	public static function extra_get_tax_layout_id() {
		if ( function_exists( 'extra_get_tax_layout_id' ) ) {
			return extra_get_tax_layout_id();
		}
		return null;
	}

	/**
	 * Get Extra Home layout ID.
	 *
	 * @since ??
	 *
	 * @return int|null
	 */
	public static function extra_get_home_layout_id() {
		if ( function_exists( 'extra_get_home_layout_id' ) ) {
			return extra_get_home_layout_id();
		}
		return null;
	}

	/**
	 * Get all active block widgets.
	 *
	 * This method will collect all active block widgets first. Later on, the result will be
	 * cached to improve the performance.
	 *
	 * @since ??
	 *
	 * @return array List of active block widgets.
	 */
	public static function get_active_block_widgets(): array {
		global $wp_version;
		static $active_block_widgets = null;

		if ( null === $active_block_widgets ) {
			$wp_major_version = substr( $wp_version, 0, 3 );

			// Bail early if were pre WP 5.8, when block widgets were introduced.
			if ( version_compare( $wp_major_version, '5.8', '<' ) ) {
				return [];
			}

			global $wp_widget_factory;

			$active_block_widgets = [];
			$block_instance       = $wp_widget_factory->get_widget_object( 'block' );
			$block_settings       = $block_instance->get_settings();

			// Bail early if there is no active block widgets.
			if ( empty( $block_settings ) ) {
				return $active_block_widgets;
			}

			// Collect all active blocks.
			foreach ( $block_settings as $block_setting ) {
				$block_content = ArrayUtility::get_value( $block_setting, 'content' );
				$block_parsed  = parse_blocks( $block_content );
				$block_name    = ArrayUtility::get_value( $block_parsed, '0.blockName' );

				// Save and cache there result.
				if ( ! in_array( $block_name, $active_block_widgets, true ) ) {
					$active_block_widgets[] = $block_name;
				}
			}
		}

		return $active_block_widgets;
	}

	/**
	 * Returns assets list with file path.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $prefix Asset Prefix.
	 *     @type string $suffix Asset Suffix.
	 *     @type string $specialty_suffix Suffix for Specialty section.
	 * }
	 *
	 * @return array
	 */
	public static function get_assets_list( array $args = [] ): array {
		$prefix = $args['prefix'] ?? '';
		$suffix = $args['suffix'] ?? '';

		$specialty_suffix = $args['specialty_suffix'] ?? '';

		return [
			// Structure elements.
			'divi/section'                              => [
				'css' => [
					"{$prefix}/css/section{$suffix}.css",
					"{$prefix}/css/row{$suffix}.css", // Some fullwidth section modules use the et_pb_row class.
				],
			],
			'divi/row'                                  => [
				'css' => "{$prefix}/css/row{$suffix}.css",
			],
			'divi/row-inner'                            => [
				'css' => "{$prefix}/css/row{$suffix}.css",
			],
			'divi/column'                               => [],
			'divi/column-inner'                         => [],

			// Module elements.
			'divi/accordion'                            => [
				'css' => [
					"{$prefix}/css/accordion{$suffix}.css",
					"{$prefix}/css/toggle{$suffix}.css",
				],
			],
			'divi/accordion-item'                       => [],
			'divi/audio'                                => [
				'css' => [
					"{$prefix}/css/audio{$suffix}.css",
					"{$prefix}/css/audio_player{$suffix}.css",
				],
			],
			'divi/before-after-image'                   => [
				'css' => "{$prefix}/css/before_after_image{$suffix}.css",
			],
			'divi/counters'                             => [],
			'divi/counter'                              => [
				'css' => "{$prefix}/css/counter{$suffix}.css",
			],
			'divi/blog'                                 => [
				'css' => [
					"{$prefix}/css/blog{$suffix}.css",
					"{$prefix}/css/posts{$suffix}.css",
					"{$prefix}/css/post_formats{$suffix}.css",
					"{$prefix}/css/overlay{$suffix}.css",
					"{$prefix}/css/audio_player{$suffix}.css",
					"{$prefix}/css/video_player{$suffix}.css",
					"{$prefix}/css/slider_base{$suffix}.css",
					"{$prefix}/css/slider_controls{$suffix}.css",
					"{$prefix}/css/wp_gallery{$suffix}.css",
					"{$prefix}/css/css_grid_grid{$suffix}.css",
				],
			],
			'divi/breadcrumbs'                          => [
				'css' => "{$prefix}/css/breadcrumbs{$suffix}.css",
			],
			'divi/blurb'                                => [
				'css' => [
					"{$prefix}/css/blurb{$suffix}.css",
				],
			],
			'divi/button'                               => [
				'css' => [
					"{$prefix}/css/button{$suffix}.css",
					"{$prefix}/css/buttons{$suffix}.css",
				],
			],
			'divi/payment-button'                       => [
				'css' => [
					"{$prefix}/css/button{$suffix}.css",
					"{$prefix}/css/buttons{$suffix}.css",
				],
			],
			'divi/group-carousel'                       => [
				'css' => [
					"{$prefix}/css/group_carousel{$suffix}.css",
				],
			],
			'divi/circle-counter'                       => [
				'css' => "{$prefix}/css/circle_counter{$suffix}.css",
			],
			'divi/charts'                               => [
				'css' => "{$prefix}/css/charts{$suffix}.css",
			],
			'divi/code'                                 => [
				'css' => "{$prefix}/css/code{$suffix}.css",
			],
			'divi/comments'                             => [
				'css' => [
					"{$prefix}/css/comments{$suffix}.css",
					"{$prefix}/css/comments_shared{$suffix}.css",
					"{$prefix}/css/buttons{$suffix}.css",
				],
			],
			'divi/contact-field'                        => [],
			'divi/contact-form'                         => [
				'css' => [
					"{$prefix}/css/contact_form{$suffix}.css",
					"{$prefix}/css/forms{$suffix}.css",
					"{$prefix}/css/forms{$specialty_suffix}{$suffix}.css",
					"{$prefix}/css/fields{$suffix}.css",
					"{$prefix}/css/buttons{$suffix}.css",
				],
			],
			'divi/contact-form-7'                       => [
				'css' => [
					"{$prefix}/css/contact_form_7{$suffix}.css",
					"{$prefix}/css/buttons{$suffix}.css",
				],
			],
			'divi/gravity-forms'                        => [
				'css' => "{$prefix}/css/gravity_forms{$suffix}.css",
			],
			'divi/countdown-timer'                      => [
				'css' => "{$prefix}/css/countdown_timer{$suffix}.css",
			],
			'divi/cta'                                  => [
				'css' => "{$prefix}/css/cta{$suffix}.css",
				"{$prefix}/css/buttons{$suffix}.css",
			],
			'divi/divider'                              => [
				'css' => "{$prefix}/css/divider{$suffix}.css",
			],
			'divi/dropdown'                             => [
				'css' => "{$prefix}/css/dropdown{$suffix}.css",
			],
			'divi/filterable-portfolio'                 => [
				'css' => [
					"{$prefix}/css/filterable_portfolio{$suffix}.css",
					"{$prefix}/css/portfolio{$suffix}.css",
					"{$prefix}/css/overlay{$suffix}.css",
					"{$prefix}/css/css_grid_grid{$suffix}.css",
				],
			],
			'divi/fullwidth-code'                       => [
				'css' => "{$prefix}/css/fullwidth_code{$suffix}.css",
			],
			'divi/fullwidth-header'                     => [
				'css' => "{$prefix}/css/fullwidth_header{$suffix}.css",
				"{$prefix}/css/buttons{$suffix}.css",
			],
			'divi/fullwidth-image'                      => [
				'css' => [
					"{$prefix}/css/fullwidth_image{$suffix}.css",
					"{$prefix}/css/overlay{$suffix}.css",
				],
			],
			'divi/fullwidth-map'                        => [
				'css' => [
					"{$prefix}/css/map{$suffix}.css",
					"{$prefix}/css/fullwidth_map{$suffix}.css",
				],
			],
			'divi/fullwidth-menu'                       => [
				'css' => [
					"{$prefix}/css/menus{$suffix}.css",
					"{$prefix}/css/fullwidth_menu{$suffix}.css",
					"{$prefix}/css/header_animations.css",
					"{$prefix}/css/header_shared{$suffix}.css",
				],
			],
			'divi/fullwidth-portfolio'                  => [
				'css' => [
					"{$prefix}/css/fullwidth_portfolio{$suffix}.css",
					"{$prefix}/css/overlay{$suffix}.css",
					"{$prefix}/css/slider_controls{$suffix}.css",
				],
			],
			'divi/fullwidth-post-content'               => [],
			'divi/fullwidth-post-slider'                => [
				'css' => [
					"{$prefix}/css/post_slider{$suffix}.css",
					"{$prefix}/css/fullwidth_post_slider{$suffix}.css",
					"{$prefix}/css/slider_modules{$suffix}.css",
					"{$prefix}/css/slider_base{$suffix}.css",
					"{$prefix}/css/slider_controls{$suffix}.css",
					"{$prefix}/css/posts{$suffix}.css",
					"{$prefix}/css/buttons{$suffix}.css",
				],
			],
			'divi/fullwidth-post-title'                 => [
				'css' => [
					"{$prefix}/css/post_title{$suffix}.css",
					"{$prefix}/css/fullwidth_post_title{$suffix}.css",
					"{$prefix}/css/buttons{$suffix}.css",
				],
			],
			'divi/fullwidth-slider'                     => [
				'css' => [
					"{$prefix}/css/fullwidth_slider{$suffix}.css",
					"{$prefix}/css/slider_modules{$suffix}.css",
					"{$prefix}/css/slider_base{$suffix}.css",
					"{$prefix}/css/slider_controls{$suffix}.css",
					"{$prefix}/css/buttons{$suffix}.css",
				],
			],
			'divi/gallery'                              => [
				'css' => [
					"{$prefix}/css/gallery{$suffix}.css",
					"{$prefix}/css/overlay{$suffix}.css",
					"{$prefix}/css/slider_base{$suffix}.css",
					"{$prefix}/css/slider_controls{$suffix}.css",
					"{$prefix}/css/magnific_popup.css",
					"{$prefix}/css/css_grid_grid{$suffix}.css",
				],
			],
			'core/gallery'                              => [
				'css' => [
					"{$prefix}/css/wp_gallery{$suffix}.css",
					"{$prefix}/css/magnific_popup.css",
					"{$prefix}/css/overlay{$suffix}.css",
					"{$prefix}/css/css_grid_grid{$suffix}.css",
				],
			],
			'divi/heading'                              => [
				'css' => [
					"{$prefix}/css/heading{$suffix}.css",
				],
			],
			'divi/icon'                                 => [
				'css' => [
					"{$prefix}/css/icon{$suffix}.css",
				],
			],
			'divi/image'                                => [
				'css' => [
					"{$prefix}/css/image{$suffix}.css",
					"{$prefix}/css/overlay{$suffix}.css",
				],
			],
			'divi/imagely-gallery'                      => [
				'css' => "{$prefix}/css/imagely_gallery{$suffix}.css",
			],
			'divi/instagram-feed'                       => [
				'css' => [
					"{$prefix}/css/instagram_feed{$suffix}.css",
				],
			],
			'divi/link'                                 => [
				'css' => "{$prefix}/css/link{$suffix}.css",
			],
			'divi/login'                                => [
				'css' => [
					"{$prefix}/css/login{$suffix}.css",
					"{$prefix}/css/forms{$suffix}.css",
					"{$prefix}/css/forms{$specialty_suffix}{$suffix}.css",
					"{$prefix}/css/fields{$suffix}.css",
					"{$prefix}/css/buttons{$suffix}.css",
				],
			],
			'divi/lottie'                               => [
				'css' => [
					"{$prefix}/css/lottie{$suffix}.css",
				],
			],
			'divi/map'                                  => [
				'css' => "{$prefix}/css/map{$suffix}.css",
			],
			'divi/map-item'                             => [],
			'divi/menu'                                 => [
				'css' => [
					"{$prefix}/css/menus{$suffix}.css",
					"{$prefix}/css/menu{$suffix}.css",
					"{$prefix}/css/header_animations.css",
					"{$prefix}/css/header_shared{$suffix}.css",
				],
			],
			'divi/number-counter'                       => [
				'css' => "{$prefix}/css/number_counter{$suffix}.css",
			],
			'divi/portfolio'                            => [
				'css' => [
					"{$prefix}/css/portfolio{$suffix}.css",
					"{$prefix}/css/overlay{$suffix}.css",
					"{$prefix}/css/css_grid_grid{$suffix}.css",
				],
			],
			'divi/post-content'                         => [],
			'divi/post-filter'                          => [
				'css' => [
					"{$prefix}/css/post_filter{$suffix}.css",
					"{$prefix}/css/post_filter_item{$suffix}.css",
				],
			],
			'divi/post-filter-item'                     => [
				'css' => "{$prefix}/css/post_filter_item{$suffix}.css",
			],
			'divi/post-nav'                             => [
				'css' => "{$prefix}/css/post_nav{$suffix}.css",
			],
			'divi/post-slider'                          => [
				'css' => [
					"{$prefix}/css/post_slider{$suffix}.css",
					"{$prefix}/css/posts{$suffix}.css",
					"{$prefix}/css/slider_modules{$suffix}.css",
					"{$prefix}/css/slider_base{$suffix}.css",
					"{$prefix}/css/slider_controls{$suffix}.css",
					"{$prefix}/css/buttons{$suffix}.css",
				],
			],
			'divi/post-title'                           => [
				'css' => [
					"{$prefix}/css/post_title{$suffix}.css",
					"{$prefix}/css/buttons{$suffix}.css",
				],
			],
			'divi/pricing-tables'                       => [
				'css' => [
					"{$prefix}/css/pricing_tables{$suffix}.css",
					"{$prefix}/css/buttons{$suffix}.css",
				],
			],
			'divi/pricing-tables-item'                  => [],
			'divi/search'                               => [
				'css' => "{$prefix}/css/search{$suffix}.css",
			],
			'divi/shop'                                 => [
				'css' => [
					"{$prefix}/css/shop{$suffix}.css",
					"{$prefix}/css/overlay{$suffix}.css",
				],
			],
			'divi/sidebar'                              => [
				'css' => [
					"{$prefix}/css/sidebar{$suffix}.css",
					"{$prefix}/css/widgets_shared{$suffix}.css",
				],
			],
			'divi/signup'                               => [
				'css' => [
					"{$prefix}/css/signup{$suffix}.css",
					"{$prefix}/css/forms{$suffix}.css",
					"{$prefix}/css/forms{$specialty_suffix}{$suffix}.css",
					"{$prefix}/css/fields{$suffix}.css",
					"{$prefix}/css/buttons{$suffix}.css",
				],
			],
			'divi/signup-custom-field'                  => [],
			'divi/slide'                                => [],
			'divi/slider'                               => [
				'css' => [
					"{$prefix}/css/slider{$suffix}.css",
					"{$prefix}/css/slider_modules{$suffix}.css",
					"{$prefix}/css/slider_base{$suffix}.css",
					"{$prefix}/css/slider_controls{$suffix}.css",
					"{$prefix}/css/buttons{$suffix}.css",
				],
			],
			'divi/social-media-follow'                  => [
				'css' => "{$prefix}/css/social_media_follow{$suffix}.css",
			],
			'divi/social-media-follow-network'          => [],
			'divi/svg'                                  => [
				'css' => "{$prefix}/css/svg{$suffix}.css",
			],
			'divi/tab'                                  => [],
			'divi/tabs'                                 => [
				'css' => "{$prefix}/css/tabs{$suffix}.css",
			],
			'divi/table-of-contents'                    => [
				'css' => "{$prefix}/css/table_of_contents{$suffix}.css",
			],
			'divi/team-member'                          => [
				'css' => [
					"{$prefix}/css/team_member{$suffix}.css",
					"{$prefix}/css/legacy_animations{$suffix}.css",
				],
			],
			'divi/testimonial'                          => [
				'css' => "{$prefix}/css/testimonial{$suffix}.css",
			],
			'divi/text'                                 => [
				'css' => "{$prefix}/css/text{$suffix}.css",
			],
			'divi/timeline'                             => [
				'css' => "{$prefix}/css/timeline{$suffix}.css",
			],
			'divi/timeline-item'                        => [],
			'divi/toggle'                               => [
				'css' => "{$prefix}/css/toggle{$suffix}.css",
			],
			'divi/tooltip'                              => [
				'css' => "{$prefix}/css/tooltip{$suffix}.css",
			],
			'divi/video'                                => [
				'css' => [
					"{$prefix}/css/video{$suffix}.css",
					"{$prefix}/css/video_player{$suffix}.css",
				],
			],
			'divi/video-slider'                         => [
				'css' => [
					"{$prefix}/css/video_slider{$suffix}.css",
					"{$prefix}/css/video_player{$suffix}.css",
					"{$prefix}/css/slider_base{$suffix}.css",
					"{$prefix}/css/slider_controls{$suffix}.css",
				],
			],
			'divi/video-slider-item'                    => [],
			'divi/woocommerce-breadcrumb'               => [
				'css' => [
					"{$prefix}/css/woo_breadcrumb{$suffix}.css",
				],
			],
			'divi/woocommerce-cart-notice'              => [
				'css' => [
					"{$prefix}/css/woo_cart_notice{$suffix}.css",
					"{$prefix}/css/buttons{$suffix}.css",
				],
			],
			'divi/woocommerce-cart-products'            => [
				'css' => [
					"{$prefix}/css/woo_cart_products{$suffix}.css",
				],
			],
			'divi/woocommerce-cart-totals'              => [
				'css' => [
					"{$prefix}/css/woo_cart_totals{$suffix}.css",
				],
			],
			'divi/woocommerce-checkout-additional-info' => [
				'css' => [
					"{$prefix}/css/woo_checkout_info{$suffix}.css",
				],
			],
			'divi/woocommerce-checkout-billing'         => [
				'css' => [
					"{$prefix}/css/woo_checkout_billing{$suffix}.css",
				],
			],
			'divi/woocommerce-checkout-order-details'   => [
				'css' => [
					"{$prefix}/css/woo_checkout_details{$suffix}.css",
				],
			],
			'divi/woocommerce-checkout-payment-info'    => [
				'css' => [
					"{$prefix}/css/woo_checkout_payment{$suffix}.css",
				],
			],
			'divi/woocommerce-checkout-shipping'        => [
				'css' => [
					"{$prefix}/css/woo_checkout_shipping{$suffix}.css",
				],
			],
			'divi/woocommerce-cross-sells'              => [
				'css' => [
					"{$prefix}/css/woo_cross_sells{$suffix}.css",
				],
			],
			'divi/woocommerce-product-add-to-cart'      => [
				'css' => [
					"{$prefix}/css/woo_add_to_cart{$suffix}.css",
					"{$prefix}/css/buttons{$suffix}.css",
				],
			],
			'divi/woocommerce-product-additional-info'  => [
				'css' => [
					"{$prefix}/css/woo_additional_info{$suffix}.css",
				],
			],
			'divi/woocommerce-product-description'      => [
				'css' => [
					"{$prefix}/css/woo_description{$suffix}.css",
				],
			],
			'divi/woocommerce-product-gallery'          => [
				'css' => [
					"{$prefix}/css/gallery{$suffix}.css",
					"{$prefix}/css/overlay{$suffix}.css",
					"{$prefix}/css/magnific_popup.css",
					"{$prefix}/css/slider_base{$suffix}.css",
					"{$prefix}/css/slider_controls{$suffix}.css",
				],
			],
			'divi/woocommerce-product-images'           => [
				'css' => [
					"{$prefix}/css/image{$suffix}.css",
					"{$prefix}/css/overlay{$suffix}.css",
					"{$prefix}/css/woo_images{$suffix}.css",
				],
			],
			'divi/woocommerce-product-meta'             => [
				'css' => [
					"{$prefix}/css/woo_meta{$suffix}.css",
				],
			],
			'divi/woocommerce-product-price'            => [
				'css' => [
					"{$prefix}/css/woo_price{$suffix}.css",
				],
			],
			'divi/woocommerce-product-rating'           => [
				'css' => [
					"{$prefix}/css/woo_rating{$suffix}.css",
				],
			],
			'divi/woocommerce-product-reviews'          => [
				'css' => [
					"{$prefix}/css/woo_reviews{$suffix}.css",
				],
			],
			'divi/woocommerce-product-stock'            => [
				'css' => [
					"{$prefix}/css/woo_stock{$suffix}.css",
				],
			],
			'divi/woocommerce-product-tabs'             => [
				'css' => [
					"{$prefix}/css/tabs{$suffix}.css",
					"{$prefix}/css/woo_tabs{$suffix}.css",
				],
			],
			'divi/woocommerce-product-title'            => [
				'css' => [
					"{$prefix}/css/woo_title{$suffix}.css",
				],
			],
			'divi/woocommerce-product-upsell'           => [
				'css' => [
					"{$prefix}/css/woo_related_products_upsells{$suffix}.css",
					"{$prefix}/css/overlay{$suffix}.css",
				],
			],
			'divi/woocommerce-related-products'         => [
				'css' => [
					"{$prefix}/css/woo_related_products_upsells{$suffix}.css",
					"{$prefix}/css/overlay{$suffix}.css",
				],
			],
			'divi/icon-list'                            => [
				'css' => [
					"{$prefix}/css/icon_list{$suffix}.css",
				],
			],
		];
	}

	/**
	 * Retrieves a block name corresponding to a given shortcode.
	 * The method uses predefined mappings, special cases, and a caching mechanism
	 * to efficiently resolve shortcodes into block names.
	 *
	 * The conversion is done to de-dupe assets.
	 *
	 * @since ??
	 *
	 * @param string $shortcode The shortcode to resolve into a block name.
	 *
	 * @return string The block name corresponding to the shortcode.
	 */
	public static function get_block_name_from_shortcode( string $shortcode ): string {
		// 1. Special case for core/gallery.
		if ( 'gallery' === $shortcode ) {
			return 'core/gallery';
		}

		static $cached = [];

		if ( isset( $cached[ $shortcode ] ) ) {
			return $cached[ $shortcode ];
		}

		// 2. Use array_flip for a fast lookup (shortcode => block_name).
		static $shortcode_to_block = null;

		if ( null === $shortcode_to_block ) {
			$shortcode_to_block = array_flip( self::$woocommerce_modules_map );
		}

		if ( isset( $shortcode_to_block[ $shortcode ] ) ) {
			$cached[ $shortcode ] = $shortcode_to_block[ $shortcode ];

			return $cached[ $shortcode ];
		}

		// 3. Replace` et_pb_` and treat as block (e.g., `et_pb_button` -> `divi/button`).
		if ( str_starts_with( $shortcode, 'et_pb_' ) ) {
			$block_name = str_replace( 'et_pb_', 'divi/', $shortcode );
			$block_name = str_replace( '_', '-', $block_name );

			$cached[ $shortcode ] = $block_name;
		}

		return $cached[ $shortcode ] ?? $shortcode;
	}

	/**
	 * Retrieves the shortcode name associated with a given block name.
	 * This function maps specific block names to their corresponding shortcode
	 * names or generates a shortcode name based on certain patterns.
	 *
	 * The conversion is done to de-dupe assets.
	 *
	 * @since ??
	 *
	 * @param string $block_name The block name to retrieve the shortcode name for.
	 *
	 * @return string The corresponding shortcode name. If no specific mapping
	 *                or transformation is found, returns the original block name.
	 */
	public static function get_shortcode_name_from_block( string $block_name ): string {
		// 1. Literal match for `core/gallery`.
		if ( 'core/gallery' === $block_name ) {
			return 'gallery';
		}

		static $cached = [];

		if ( isset( $cached[ $block_name ] ) ) {
			return $cached[ $block_name ];
		}

		// 2. Match against WooCommerce modules.
		if ( isset( self::$woocommerce_modules_map[ $block_name ] ) ) {
			$cached[ $block_name ] = self::$woocommerce_modules_map[ $block_name ];

			return $cached[ $block_name ];
		}

		// 3. Replace `divi/` and treat as shortcode (e.g., `divi/button` -> `et_pb_button`).
		if ( str_starts_with( $block_name, 'divi/' ) ) {
			$shortcode = str_replace( 'divi/', 'et_pb_', $block_name );
			$shortcode = str_replace( '-', '_', $shortcode );

			$cached[ $block_name ] = $shortcode;
		}

		return $cached[ $block_name ] ?? $block_name;
	}

	/**
	 * Retrieve Post ID from 1 of 4 sources depending on which exists:
	 * - $_POST['current_page']['id']
	 * - $_POST['et_post_id']
	 * - $_GET['post']
	 * - get_the_ID()
	 *
	 * @since ?? Copied from `ET_Builder_Element::get_current_post_id()`.
	 *
	 * @return int|bool
	 */
	public static function get_current_post_id() {
		// GET correct post id in rest api request.
		// phpcs:disable WordPress.Security.NonceVerification -- This function does not change any state, and is therefore not susceptible to CSRF.
		if ( Conditions::is_rest_api_request() ) {
			// We use the static variable because WP_REST_Request (equivalent to `$_GET` in REST API request) is not available in this context.
			// The static variable is set in `WooCommerceUtils::validate_product_id()` which is called early/first in the request.
			$query_params = WooCommerceUtils::get_current_rest_request_query_params();

			if ( ArrayUtility::get_value( $query_params, 'currentPage.id' ) ) {
				return absint( ArrayUtility::get_value( $query_params, 'currentPage.id' ) );
			}

			if ( isset( $query_params['et_post_id'] ) ) {
				return absint( $query_params['et_post_id'] );
			}

			if ( isset( $query_params['post'] ) ) {
				return absint( $query_params['post'] );
			}

			return ET_Post_Stack::get_main_post_id();
		}

		// Getting correct post id in computed_callback request.
		if ( wp_doing_ajax() && ArrayUtility::get_value( $_POST, 'current_page.id' ) ) {
			return absint( ArrayUtility::get_value( $_POST, 'current_page.id' ) );
		}

		if ( wp_doing_ajax() && isset( $_POST['et_post_id'] ) ) {
			return absint( $_POST['et_post_id'] );
		}

		if ( isset( $_POST['post'] ) ) {
			return absint( $_POST['post'] );
		}
		// phpcs:enable

		if ( self::should_respect_post_interference() ) {
			return get_the_ID();
		}

		return ET_Post_Stack::get_main_post_id();
	}

	/**
	 * Returns Block names based on assets list.
	 *
	 * @since ??
	 */
	public static function get_divi_block_names(): array {
		return array_keys( self::get_assets_list() );
	}

	/**
	 * Returns Shortcode slugs based on assets list.
	 *
	 * @since ??
	 */
	public static function get_divi_shortcode_slugs(): array {
		static $shortcode_slugs = null;

		if ( null !== $shortcode_slugs ) {
			return $shortcode_slugs;
		}

		$block_names     = self::get_divi_block_names();
		$shortcode_slugs = array_map( [ self::class, 'get_shortcode_name_from_block' ], $block_names );

		return $shortcode_slugs;
	}

	/**
	 * Gets the assets directory.
	 *
	 * @since ??
	 *
	 * @param bool $url check if url.
	 *
	 * @return string
	 */
	public static function get_dynamic_assets_path( bool $url = false ): string {
		$is_builder_active = et_is_builder_plugin_active();

		$template_address = $url ? get_template_directory_uri() : get_template_directory();

		if ( $is_builder_active ) {
			$template_address = $url ? \ET_BUILDER_PLUGIN_URI : \ET_BUILDER_PLUGIN_DIR;
		}

		// Value for the filter.
		$template_address = $template_address . '/includes/builder/feature/dynamic-assets/assets';

		/**
		 * Filters prefix for assets path.
		 *
		 * This filter is the replacement of Divi 4 filter `et_dynamic_assets_prefix`.
		 *
		 * @since ??
		 *
		 * @param string $template_address
		 */
		return apply_filters( 'divi_frontend_assets_dynamic_assets_utils_prefix', $template_address );
	}

	/**
	 * Disable dynamic icons if TP modules are present.
	 *
	 * @since ??
	 */
	public static function get_dynamic_icons_default_value(): string {
		require_once get_template_directory() . '/includes/builder/api/DiviExtensions.php';

		$tp_extensions = \DiviExtensions::get();

		if ( ! empty( $tp_extensions ) || ( is_child_theme() && ! et_is_builder_plugin_active() ) ) {
			return 'off';
		}

		return 'on';
	}

	/**
	 * Retrieves the feature detection map.
	 *
	 * @since ??
	 *
	 * @param array $options Feature Detection Options.
	 *
	 * @return array The feature detection map.
	 */
	public static function get_feature_detection_map( array $options = [] ): array {
		static $cache = [];

		$cached_key = md5( intval( $options['has_block'] ) . intval( $options['has_shortcode'] ) );

		if ( isset( $cache[ $cached_key ] ) ) {
			return $cache[ $cached_key ];
		}

		// Value for the filter.
		$feature_detection_map = [
			'animation_style'              => [
				'callback'        => [ DetectFeature::class, 'has_animation_style' ],
				'additional_args' => [
					'options' => $options,
				],
			],
			'interactions_enabled'         => [
				'callback'        => [ DetectFeature::class, 'has_interactions_enabled' ],
				'additional_args' => [
					'options' => $options,
				],
			],
			'excerpt_content_on'           => [
				'callback'        => [ DetectFeature::class, 'has_excerpt_content_on' ],
				'additional_args' => [
					'options' => $options,
				],
			],
			'gutter_widths'                => [
				'callback'        => [ DetectFeature::class, 'get_gutter_widths' ],
				'additional_args' => [
					'options' => $options,
				],
			],
			'icon_font_divi'               => [
				'callback'        => [ DetectFeature::class, 'has_icon_font' ],
				'additional_args' => [
					'type'    => 'divi',
					'options' => $options,
				],
			],
			'icon_font_fa'                 => [
				'callback'        => [ DetectFeature::class, 'has_icon_font' ],
				'additional_args' => [
					'type'    => 'fa',
					'options' => $options,
				],
			],
			'lightbox'                     => [
				'callback'        => [ DetectFeature::class, 'has_lightbox' ],
				'additional_args' => [
					'options' => $options,
				],
			],
			'fullscreen_section_enabled'   => [
				'callback'        => [ DetectFeature::class, 'has_fullscreen_section_enabled' ],
				'additional_args' => [
					'options' => $options,
				],
			],
			'scroll_effects_enabled'       => [
				'callback'        => [ DetectFeature::class, 'has_scroll_effects_enabled' ],
				'additional_args' => [
					'options' => $options,
				],
			],
			'section_dividers_enabled'     => [
				'callback'        => [ DetectFeature::class, 'has_section_dividers_enabled' ],
				'additional_args' => [
					'options' => $options,
				],
			],
			'link_enabled'                 => [
				'callback'        => [ DetectFeature::class, 'has_link_enabled' ],
				'additional_args' => [
					'options' => $options,
				],
			],
			'split_testing_enabled'        => [
				'callback'        => [ DetectFeature::class, 'has_split_testing_enabled' ],
				'additional_args' => [
					'options' => $options,
				],
			],
			'social_follow_icon_font_divi' => [
				'callback'        => [ DetectFeature::class, 'has_social_follow_icon_font' ],
				'additional_args' => [
					'type'    => 'divi',
					'options' => $options,
				],
			],
			'social_follow_icon_font_fa'   => [
				'callback'        => [ DetectFeature::class, 'has_social_follow_icon_font' ],
				'additional_args' => [
					'type'    => 'fa',
					'options' => $options,
				],
			],
			'media_embedded_in_content'    => [
				'callback'        => [ self::class, 'is_media_embedded_in_content' ],
				'additional_args' => [],
			],
			'specialty_section'            => [
				'callback'        => [ DetectFeature::class, 'has_specialty_section' ],
				'additional_args' => [
					'options' => $options,
				],
			],
			'sticky_position_enabled'      => [
				'callback'        => [ DetectFeature::class, 'has_sticky_position_enabled' ],
				'additional_args' => [
					'options' => $options,
				],
			],
			'global_color_ids'             => [
				'callback'        => [ DetectFeature::class, 'get_global_color_ids' ],
				'additional_args' => [],
			],
			'css_grid_layout_enabled'      => [
				'callback'        => [ DetectFeature::class, 'has_css_grid_layout_enabled' ],
				'additional_args' => [
					'options' => $options,
				],
			],
			'flex_layout_enabled'          => [
				'callback'        => [ DetectFeature::class, 'has_flex_layout_enabled' ],
				'additional_args' => [
					'options' => $options,
				],
			],
			'block_layout_enabled'         => [
				'callback'        => [ DetectFeature::class, 'has_block_layout_enabled' ],
				'additional_args' => [
					'options' => $options,
				],
			],
			'block_mode_blog'              => [
				'callback'        => [ DetectFeature::class, 'has_block_mode_blog_enabled' ],
				'additional_args' => [
					'options' => $options,
				],
			],
		];

		/**
		 * Filters feature detection map to detect use on the page.
		 *
		 * This filter is the replacement of Divi 4 filter `et_builder_module_attrs_values_used` .
		 *
		 * @since ??
		 *
		 * @param array $feature_detection_functions Feature detection callbacks.
		 */
		$feature_detection_map = (array) apply_filters(
			'divi_frontend_assets_dynamic_assets_utils_module_feature_detection_map',
			$feature_detection_map
		);

		$cache[ $cached_key ] = $feature_detection_map;

		return $feature_detection_map;
	}

	/**
	 * Extract font family names from the post content.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array List of unique font family names.
	 */
	public static function extract_used_fonts_from_content( $post_id ) {
		$content = get_post_field( 'post_content', $post_id );

		if ( empty( $content ) ) {
			return [];
		}

		$fonts = [];

		// Get both types of presets.
		$module_preset_ids = DetectFeature::get_block_preset_ids( $content );
		$group_preset_ids  = DetectFeature::get_group_preset_ids( $content );

		// Get attributes from both preset types.
		$module_attrs = self::get_module_preset_attributes( $module_preset_ids );
		$group_attrs  = self::get_group_preset_attributes( $group_preset_ids );

		// Process font families from module preset attributes.
		if ( ! empty( $module_attrs ) ) {
			foreach ( $module_attrs as $attrs ) {
				DetectFeature::extract_font_from_preset_attrs( $attrs, $fonts );
			}
		}

		// Process font families from group preset attributes.
		if ( ! empty( $group_attrs ) ) {
			foreach ( $group_attrs as $attrs ) {
				DetectFeature::extract_font_from_preset_attrs( $attrs, $fonts );
			}
		}

		// Regex to match font family declarations for any screen size or custom breakpoint.
		// It matches the following structure: `{"font":{"${breakpoint}":{"value":{"family":`.
		// Regex101 link: https://regex101.com/r/L9BX72/1.
		$pattern = '/"font":\s*\{[^}]*"([^"]+)":\s*\{[^}]*"value":\s*\{[^}]*"family":\s*"([^"]+)"/';
		preg_match_all( $pattern, $content, $matches );

		if ( ! empty( $matches[2] ) ) {
			foreach ( $matches[2] as $font ) {
				if ( str_starts_with( $font, '$variable(' ) ) {
					continue;
				}
				$fonts[] = sanitize_text_field( $font );
			}
		}

		if ( preg_match_all( '/\$variable\((.*?)\)\$/', $content, $var_matches ) ) {
			$global_variables = GlobalData::get_global_variables();
			$global_fonts     = (array) ( $global_variables['fonts'] ?? [] );

			foreach ( $var_matches[1] as $var_json ) {
				$decoded_json = json_decode( '"' . $var_json . '"' );
				$data         = json_decode( $decoded_json, true );

				if ( isset( $data['type'], $data['value']['name'] ) && 'content' === $data['type'] ) {
					$target_id = $data['value']['name'];

					if ( isset( $global_fonts[ $target_id ]['value'] ) && ( 'active' === $global_fonts[ $target_id ]['status'] ?? '' ) ) {
						$fonts[] = sanitize_text_field( $global_fonts[ $target_id ]['value'] );
					}
				}
			}
		}

		// Return an array of unique fonts.
		return array_unique( $fonts );
	}

	/**
	 * Returns the list of Divi modules with `icon` option.
	 *
	 * D4 version of the function: `et_pb_get_font_icon_modules`.
	 *
	 * @since ??
	 *
	 * @param string $group certain group of modules .
	 *
	 * @return array
	 */
	public static function get_font_icon_modules( $group = false ) {

		$font_icon_modules_used_in_migrations = [
			'button'  => [
				'divi/button',
				'divi/payment-button',
				'divi/comments',
				'divi/contact-form',
				'divi/cta',
				'divi/fullwidth-header',
				'divi/fullwidth-post-slider',
				'divi/group-carousel',
				'divi/instagram-feed',
				'divi/login',
				'divi/post-slider',
				'divi/pricing-tables',
				'divi/pricing-table',
				'divi/signup',
				'divi/slider',
				'divi/slide',
				'divi/woocommerce-cart-notice',
				'divi/woocommerce-product-add-to-cart',
			],
			'blurb'   => [
				'divi/blurb',
			],
			'overlay' => [
				'divi/blog',
				'divi/filterable-portfolio',
				'divi/fullwidth-image',
				'divi/fullwidth-portfolio',
				'divi/gallery',
				'divi/image',
				'divi/portfolio',
				'divi/shop',
				'divi/woocommerce-product-upsell',
				'divi/woocommerce-related-products',
			],
			'toggle'  => [
				'divi/toggle',
			],
		];

		$other_select_icon_modules = [
			'select_icon' => [
				'divi/icon',
				'divi/link',
				'divi/video',
				'divi/video-slider',
				'divi/video-slider-item',
				'divi/testimonial',
				'divi/accordion',
				'divi/accordion-item',
				'divi/icon-list',
				'divi/icon-list-item',
				'divi/timeline-item',
			],
		];

		if ( false === $group ) {
			// Return all modules that use select_icon.
			$all_modules             = [];
			$all_select_icon_modules = array_merge( $font_icon_modules_used_in_migrations, $other_select_icon_modules );
			foreach ( $all_select_icon_modules as $select_icon_module ) {
				$all_modules = array_merge( $all_modules, $select_icon_module );
			}
			return $all_modules;
		} elseif ( isset( $font_icon_modules_used_in_migrations[ $group ] ) ) {
			// Return certain modules list by $group flag.
			return $font_icon_modules_used_in_migrations[ $group ];
		}

		return [];
	}

	/**
	 * Find array values in array_1 that do not exist in array_2.
	 *
	 * @since ??
	 *
	 * @param array $array_1 First array.
	 * @param array $array_2 Second array.
	 */
	public static function get_new_array_values( array $array_1, array $array_2 ): array {
		$new_array_values = [];

		foreach ( $array_1 as $key => $value ) {
			if ( empty( $array_2[ $key ] ) ) {
				$new_array_values[ $key ] = $value;
			}
		}

		return $new_array_values;
	}

	/**
	 * Get the module preset attributes for the given data.
	 *
	 * @since ??
	 *
	 * @param array $preset_ids Containing block_name and preset_id.
	 *
	 * @return array The preset attributes for the given block data.
	 */
	public static function get_module_preset_attributes( array $preset_ids ): array {
		$all_presets       = GlobalPreset::get_data();
		$preset_attributes = [];

		foreach ( $preset_ids as $block ) {
			// Bail early if required keys are missing.
			if ( ! isset( $block['block_name'], $block['preset_id'] ) ) {
				continue;
			}

			$module_name = $block['block_name'];
			$preset_id   = $block['preset_id'];

			// Get default preset id.
			if ( 'default' === $preset_id || '_initial' === $preset_id ) {
				$preset_id = $all_presets['module'][ $module_name ]['default'] ?? '';
			}

			// Include preset attrs when found.
			if ( isset( $all_presets['module'][ $module_name ]['items'][ $preset_id ]['attrs'] ) ) {
				$preset_attributes[] = $all_presets['module'][ $module_name ]['items'][ $preset_id ]['attrs'];
			}
		}

		return $preset_attributes;
	}


	/**
	 * Get the page setting attributes for the given data.
	 *
	 * This method retrieves the page setting attributes for the given post ID.
	 * The attributes are filtered based on the provided attribute names.
	 * If no attribute names are provided, all page setting attributes are returned.
	 *
	 * @since ??
	 *
	 * @param int    $post_id The post ID.
	 * @param ?array $attributes The attributes to filter and return.
	 *
	 * @return array The filtered page setting attributes for the given post ID.
	 */
	public static function get_page_setting_attributes( int $post_id, ?array $attributes ): array {
		$all_page_setting_attributes = Settings::get_values( 'page', $post_id );

		if ( ! empty( $attributes ) ) {
			$filtered_attributes = [];

			foreach ( $attributes as $key => $attribute_name ) {
				if ( isset( $all_page_setting_attributes[ $attribute_name ] ) ) {
					$filtered_attributes[ $attribute_name ] = $all_page_setting_attributes[ $attribute_name ];
				}
			}

			return $filtered_attributes;
		}

		return $all_page_setting_attributes;
	}

	/**
	 * Retrieves attribute arrays for multiple group presets.
	 *
	 * This method takes an array of group preset configurations and collects their
	 * corresponding attribute sets. For each preset, it resolves default preset IDs
	 * to their actual values and retrieves the preset attributes if they exist.
	 *
	 * @since ??
	 *
	 * @param array $group_preset_ids Array of group preset configurations. Each item should contain 'group_name' and 'preset_id' keys.
	 *
	 * @return array Array of group preset attribute sets. Only non-empty attribute sets
	 *               are included in the returned array.
	 */
	public static function get_group_preset_attributes( array $group_preset_ids ): array {
		$all_presets       = GlobalPreset::get_data();
		$preset_attributes = [];

		foreach ( $group_preset_ids as $preset ) {
			// Bail early if required keys are missing.
			if ( ! isset( $preset['group_name'], $preset['preset_id'] ) ) {
				continue;
			}
			$group_name = $preset['group_name'];
			$preset_id  = $preset['preset_id'];

			$default_group_preset_id = $all_presets['group'][ $group_name ]['default'] ?? '';
			// Handle default preset IDs.
			if ( GlobalPreset::is_preset_id_as_default( $preset_id, $default_group_preset_id ) ) {
				$preset_id = $all_presets['group'][ $group_name ]['default'] ?? '';
			}

			$attrs = $all_presets['group'][ $group_name ]['items'][ $preset_id ]['attrs'] ?? [];

			if ( ! empty( $attrs ) ) {
				$preset_attributes[] = $attrs;
			}
		}

		return $preset_attributes;
	}

	/**
	 * Get the shortcode preset attributes for the given data.
	 *
	 * @since ??
	 *
	 * @param array $preset_ids Containing shortcode_name and preset_id.
	 *
	 * @return array The preset attributes for the given shortcode data.
	 */
	public static function get_shortcode_preset_attributes( array $preset_ids ): array {
		$all_presets       = GlobalPreset::get_legacy_data();
		$preset_attributes = [];

		foreach ( $preset_ids as $data ) {
			$module_name = $data['shortcode_name'];
			$preset_id   = $data['preset_id'];

			// Get default preset id.
			if ( 'default' === $preset_id || '_initial' === $preset_id ) {
				$preset_id = $all_presets[ $module_name ]['default'] ?? '';
			}

			// Include preset attrs when found.
			if ( isset( $all_presets[ $module_name ]['presets'][ $preset_id ]['settings'] ) ) {
				$preset_attributes[] = $all_presets[ $module_name ]['presets'][ $preset_id ]['settings'];
			}
		}

		return $preset_attributes;
	}

	/**
	 * Get the post IDs of active Theme Builder templates.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function get_theme_builder_template_ids(): array {
		$tb_layouts   = et_theme_builder_get_template_layouts();
		$template_ids = [];

		// On 404/archive pages, TB can render templates with override=true but enabled=false.
		// Check for this edge case to ensure all rendered templates are included.
		$is_special_page = is_404() || is_archive();

		// Extract layout ids used in current request.
		if ( ! empty( $tb_layouts ) ) {
			if ( $tb_layouts[ ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE ]['override'] ) {
				// Include if enabled OR if on special page with override=true.
				if ( ! empty( $tb_layouts[ ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE ]['enabled'] ) || $is_special_page ) {
					$template_ids[] = intval( $tb_layouts[ ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE ]['id'] );
				}
			}
			if ( $tb_layouts[ ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE ]['override'] ) {
				// Include if enabled OR if on special page with override=true.
				if ( ! empty( $tb_layouts[ ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE ]['enabled'] ) || $is_special_page ) {
					$template_ids[] = intval( $tb_layouts[ ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE ]['id'] );
				}
			}
			if ( $tb_layouts[ ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE ]['override'] ) {
				// Include if enabled OR if on special page with override=true.
				if ( ! empty( $tb_layouts[ ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE ]['enabled'] ) || $is_special_page ) {
					$template_ids[] = intval( $tb_layouts[ ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE ]['id'] );
				}
			}
		}

		return $template_ids;
	}

	/**
	 * Resolve all post-backed canvas owners that should be considered for interaction target matching.
	 *
	 * The owner set includes:
	 * - The current rendering owner (`$post_id`).
	 * - Active Theme Builder template owners (header/body/footer).
	 * - Main singular post owner when rendering inside a Theme Builder template.
	 *
	 * @since ??
	 *
	 * @param int $post_id Current rendering owner post ID.
	 *
	 * @return array<int> Owner post IDs.
	 */
	private static function _get_interaction_target_owner_post_ids( int $post_id ): array {
		$post_id         = absint( $post_id );
		$tb_template_ids = self::get_theme_builder_template_ids();
		$owner_post_ids  = array_merge( [ $post_id ], $tb_template_ids );

		// When rendering template content, include the main post owner so
		// template -> post-content local canvas targeting can resolve.
		$main_post_id = absint( ET_Post_Stack::get_main_post_id() );
		if ( 0 < $main_post_id ) {
			$owner_post_ids[] = $main_post_id;
		}

		/**
		 * Filters post-backed canvas owners considered during interaction target matching.
		 *
		 * @since ??
		 *
		 * @param array<int> $owner_post_ids Candidate owner post IDs.
		 * @param int        $post_id Current rendering owner post ID.
		 */
		$owner_post_ids = apply_filters( 'divi_dynamic_assets_interaction_target_owner_post_ids', $owner_post_ids, $post_id );
		if ( ! is_array( $owner_post_ids ) ) {
			$owner_post_ids = [];
		}

		return array_values(
			array_filter(
				array_unique(
					array_map( 'absint', $owner_post_ids )
				)
			)
		);
	}

	/**
	 * Fetch local canvas posts for a resolved owner set with per-owner-set caching.
	 *
	 * @since ??
	 *
	 * @param array<int> $owner_post_ids Owner post IDs.
	 *
	 * @return array Array of local canvas posts.
	 */
	private static function _get_local_canvas_posts_for_owner_post_ids( array $owner_post_ids ): array {
		$owner_post_ids = array_values(
			array_filter(
				array_unique(
					array_map( 'absint', $owner_post_ids )
				)
			)
		);

		if ( empty( $owner_post_ids ) ) {
			return [];
		}

		sort( $owner_post_ids, SORT_NUMERIC );
		$owner_set_key = implode( ',', $owner_post_ids );

		if ( ! isset( self::$_local_canvas_posts_by_owner_set_cache[ $owner_set_key ] ) ) {
			self::$_local_canvas_posts_by_owner_set_cache[ $owner_set_key ] = [
				'posts' => CanvasUtils::get_local_canvas_posts_for_post_ids( $owner_post_ids ),
			];
		}

		$cached_posts = self::$_local_canvas_posts_by_owner_set_cache[ $owner_set_key ]['posts'] ?? [];

		return is_array( $cached_posts ) ? $cached_posts : [];
	}

	/**
	 * Merge multiple arrays and returns an array with unique values.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function get_unique_array_values(): array {
		$merged_array = [];

		foreach ( func_get_args() as $array_of_value ) {
			if ( empty( $array_of_value ) ) {
				continue;
			}

			$merged_array = array_merge( $merged_array, $array_of_value );
		}

		return array_values( array_unique( $merged_array ) );
	}

	/**
	 * Extract canvas IDs from canvas portal blocks in post content.
	 * This is used by DynamicAssets to find which canvases need to be processed.
	 *
	 * Optimized to use SimpleBlockParser instead of parse_blocks() for better performance.
	 * SimpleBlockParser uses regex-based parsing and has built-in caching, making it
	 * significantly faster than WordPress's full block parser for this use case.
	 *
	 * @since ??
	 *
	 * @param string $content Post content in Gutenberg block format.
	 *
	 * @return array Array of unique canvas IDs found in canvas portal blocks.
	 */
	public static function extract_canvas_portal_canvas_ids_from_content( $content ) {
		$canvas_ids = [];

		if ( empty( $content ) ) {
			return $canvas_ids;
		}

		// Quick string check: if content doesn't contain "canvas-portal", skip parsing entirely.
		// This avoids expensive parsing when there are no canvas portal blocks.
		if ( ! str_contains( $content, 'canvas-portal' ) ) {
			return $canvas_ids;
		}

		// Use SimpleBlockParser for faster regex-based parsing with built-in caching.
		// Filter by block name to only parse canvas-portal blocks, avoiding parsing all blocks.
		$parsed_blocks = SimpleBlockParser::parse(
			$content,
			[
				'blockName'    => 'divi/canvas-portal',
				'excludeError' => true,
			]
		)->results();

		// Extract canvas IDs from parsed blocks.
		foreach ( $parsed_blocks as $block ) {
			$block_attrs = $block->attrs();
			$canvas_id   = $block_attrs['canvas']['advanced']['canvasId']['desktop']['value'] ?? '';

			if ( ! empty( $canvas_id ) ) {
				$canvas_ids[] = $canvas_id;
			}
		}

		return array_unique( $canvas_ids );
	}

	/**
	 * Get all canvas data for a post in a structured, cached format.
	 * This function queries the database once and categorizes all canvases by their usage type.
	 *
	 * Cache structure:
	 * [
	 *   'canvas_portal_ids' => ['canvas1', 'canvas2'], // Canvas IDs from canvas portal blocks
	 *   'appended_above' => ['canvas3'], // Canvas IDs with appendToMainCanvas = 'above'
	 *   'appended_below' => ['canvas4'], // Canvas IDs with appendToMainCanvas = 'below'
	 *   'interaction_targets' => [
	 *     'target1' => ['canvas5', 'canvas6'], // Canvas IDs that contain interaction target 'target1'
	 *     'target2' => ['canvas7'],
	 *   ],
	 *   'all_canvas_metadata' => [
	 *     'canvas1' => ['isGlobal' => true, 'name' => 'Canvas Name', 'appendToMainCanvas' => null, ...],
	 *     ...
	 *   ],
	 * ]
	 *
	 * @since ??
	 *
	 * @param int    $post_id Post ID to get canvas data for.
	 * @param string $main_content Optional. Main post content to extract interaction targets from.
	 *                            If not provided, will fetch from post.
	 *
	 * @return array Structured canvas data.
	 */
	public static function get_all_canvas_data_for_post( $post_id, $main_content = '' ) {
		if ( ! $post_id ) {
			return [
				'canvas_portal_ids'   => [],
				'appended_above'      => [],
				'appended_below'      => [],
				'interaction_targets' => [],
				'all_canvas_metadata' => [],
			];
		}

		// Non-singular requests can have a stable main-loop context key (e.g. term:category:4, search).
		// When present, we merge context-backed local canvases into the returned dataset for THIS request.
		//
		// IMPORTANT: If a main-loop context key exists, we MUST NOT persist the merged dataset to the
		// per-post postmeta cache (`_divi_dynamic_assets_canvases_used`) because that cache is keyed only by $post_id.
		// A Theme Builder layout post (or other shared content) can be rendered under different contexts
		// (different terms, authors, searches, etc.), so persisting context-specific canvas IDs would leak
		// canvases across pages and make the cache incorrect.
		$main_loop_context_key  = CanvasUtils::get_main_loop_parent_context_key();
		$has_main_loop_context  = is_string( $main_loop_context_key ) && '' !== $main_loop_context_key;
		$owner_post_ids         = self::_get_interaction_target_owner_post_ids( (int) $post_id );
		$can_persist_post_cache = ! $has_main_loop_context;

		// Check if this is a cacheable frontend request (for postmeta caching).
		// Visual Builder and REST API requests need canvas data but shouldn't cache to postmeta.
		$is_cacheable_request = self::is_dynamic_front_end_request();

		// Create cache key based on post_id and main_content hash (if provided).
		// Note: Persistent cache is only keyed by post_id, but static cache includes main_content hash
		// to handle cases where different main_content is passed.
		$cache_key = $post_id . '_' . md5( $main_content );

		// Check in-memory static cache first (fastest - no DB query).
		// This works for both cacheable and non-cacheable requests (e.g., VB might call multiple times).
		if ( isset( self::$_canvas_data_static_cache[ $cache_key ] ) ) {
			$cached_data = self::$_canvas_data_static_cache[ $cache_key ];
			return $cached_data;
		}

		// Check persistent cache (post meta) - Visual Builder can read from cache but not write to it.
		// Cacheable requests can both read and write to postmeta cache.
		$cached_data = null;
		// Allow both cacheable and non-cacheable requests to read from postmeta cache.
		// Visual Builder can safely read cached data, but we won't write to cache for non-cacheable requests.
		$cached_data = get_post_meta( $post_id, '_divi_dynamic_assets_canvases_used', true );

		// Check if cache exists and has canvas metadata - if so, use it immediately.
		// This works for both cacheable and non-cacheable requests (Visual Builder can use cached data).
		if ( is_array( $cached_data ) && isset( $cached_data['all_canvas_metadata'] ) && is_array( $cached_data['all_canvas_metadata'] ) ) {
			// Cached data has processed content (for meta storage). WordPress already applied wp_unslash()
			// when reading from post meta, so the content is already in the correct format for REST API/builder.
			$unprocessed_cached_data = $cached_data;

			// Normalize cached canvas strings after post meta read (#49457: false \\uXXXX in plain text).
			foreach ( $unprocessed_cached_data['all_canvas_metadata'] as $canvas_id => &$canvas_meta_entry ) {
				if ( isset( $canvas_meta_entry['content'] ) && is_string( $canvas_meta_entry['content'] ) ) {
					$canvas_meta_entry['content'] = StringUtility::heal_false_pua_json_escapes_in_canvas_cache_content( $canvas_meta_entry['content'] );
				}
			}
			unset( $canvas_meta_entry );

			// Merge context-backed local canvases for this request when applicable.
			if ( $has_main_loop_context ) {
				$unprocessed_cached_data = self::_add_context_canvas_posts_to_canvas_data(
					$unprocessed_cached_data,
					$main_loop_context_key,
					$main_content
				);
			}

			// Store unprocessed data in static cache for subsequent calls in same request.
			// Store with both the current cache_key AND a base cache_key (post_id + empty main_content)
			// for cases where main_content wasn't provided initially but is provided later (or vice versa).
			self::$_canvas_data_static_cache[ $cache_key ] = $unprocessed_cached_data;
			$base_cache_key                                = $post_id . '_' . md5( '' );
			if ( $base_cache_key !== $cache_key ) {
				self::$_canvas_data_static_cache[ $base_cache_key ] = $unprocessed_cached_data;
			}

			return $unprocessed_cached_data;
		}

		// Note: We continue to fetch canvas data even for non-cacheable requests (VB, REST API, etc.)
		// if cache doesn't exist. Visual Builder can read from cache but won't write to postmeta cache.

		// If cache exists but is old format (just array of IDs), we need to rebuild.
		$canvas_data = [
			'canvas_portal_ids'   => [],
			'appended_above'      => [],
			'appended_below'      => [],
			'interaction_targets' => [],
			'all_canvas_metadata' => [],
		];

		// Skip expensive canvas portal ID extraction when not in a frontend request.
		// Canvas portal IDs are only needed for frontend rendering, not for builder/REST API.
		// The builder needs canvas metadata but doesn't need to know which canvases are referenced by portals.
		if ( $is_cacheable_request ) {
			// Get main post content if not provided.
			if ( empty( $main_content ) ) {
				$post = get_post( $post_id );
				if ( $post ) {
					$main_content = $post->post_content;
				}
			}

			// Extract canvas portal IDs from main post content.
			$canvas_portal_ids = self::extract_canvas_portal_canvas_ids_from_content( $main_content );

			// Extract canvas portal IDs from Theme Builder templates.
			$tb_template_ids = self::get_theme_builder_template_ids();
			if ( ! empty( $tb_template_ids ) ) {
				$tb_template_ids_to_fetch = array_diff( $tb_template_ids, [ $post_id ] );
				if ( ! empty( $tb_template_ids_to_fetch ) ) {
					$tb_posts = get_posts(
						[
							'post__in'       => $tb_template_ids_to_fetch,
							'posts_per_page' => -1,
							'post_type'      => 'any',
						]
					);

					foreach ( $tb_posts as $tb_post ) {
						$tb_canvas_ids     = self::extract_canvas_portal_canvas_ids_from_content( $tb_post->post_content );
						$canvas_portal_ids = array_merge( $canvas_portal_ids, $tb_canvas_ids );
					}
				}
			}

			$canvas_data['canvas_portal_ids'] = array_unique( $canvas_portal_ids );
		}

		// Resolve local canvases from one unified owner set.
		$local_canvas_posts = self::_get_local_canvas_posts_for_owner_post_ids( $owner_post_ids );

		// Merge in context-backed local canvases for non-singular requests.
		if ( $has_main_loop_context ) {
			$context_canvas_posts = CanvasUtils::get_context_canvas_posts( $main_loop_context_key );
			if ( ! empty( $context_canvas_posts ) ) {
				$local_canvas_posts = array_merge( $local_canvas_posts, $context_canvas_posts );
			}
		}

		// Fetch global canvases.
		$global_canvas_posts = CanvasUtils::get_canvas_posts( true );

		// Build canvas metadata and categorize by usage type.
		$all_canvas_metadata = [];
		$canvas_owner_ids    = [];

		// Process all canvases together (local and global use the same logic).
		// Mark each canvas with its type (local vs global) for later use.
		$tag_canvas_type = function ( $post, $is_global ) {
			return [
				'post'     => $post,
				'isGlobal' => $is_global,
			];
		};

		$all_canvas_posts = array_merge(
			array_map( fn( $post ) => $tag_canvas_type( $post, false ), $local_canvas_posts ),
			array_map( fn( $post ) => $tag_canvas_type( $post, true ), $global_canvas_posts )
		);

		// Batch fetch all canvas metadata in 1 query instead of 2N queries.
		$canvas_post_ids = array_map(
			function ( $canvas_post_data ) {
				return $canvas_post_data['post']->ID;
			},
			$all_canvas_posts
		);

		// Batch fetch canvas_id, append_to_main, z_index, created_at, and owner post ID in one query.
		$all_meta           = self::_batch_get_post_meta(
			$canvas_post_ids,
			[ '_divi_canvas_id', '_divi_canvas_append_to_main', '_divi_canvas_z_index', '_divi_canvas_created_at', '_divi_canvas_parent_post_id' ]
		);
		$canvas_id_map      = $all_meta['_divi_canvas_id'] ?? [];
		$append_to_main_map = $all_meta['_divi_canvas_append_to_main'] ?? [];
		$z_index_map        = $all_meta['_divi_canvas_z_index'] ?? [];
		$created_at_map     = $all_meta['_divi_canvas_created_at'] ?? [];
		$parent_post_id_map = $all_meta['_divi_canvas_parent_post_id'] ?? [];
		$owner_type_cache   = [];
		$canvas_raw_ids     = [];

		$is_theme_builder_area_owner = static function ( int $owner_post_id ) use ( &$owner_type_cache ): bool {
			if ( 0 >= $owner_post_id ) {
				return false;
			}

			if ( isset( $owner_type_cache[ $owner_post_id ] ) ) {
				return $owner_type_cache[ $owner_post_id ];
			}

			$owner_post_type                    = get_post_type( $owner_post_id );
			$owner_type_cache[ $owner_post_id ] = in_array( $owner_post_type, [ 'et_header_layout', 'et_body_layout', 'et_footer_layout' ], true );

			return $owner_type_cache[ $owner_post_id ];
		};

		foreach ( $all_canvas_posts as $canvas_post_data ) {
			$canvas_post = $canvas_post_data['post'];
			$is_global   = $canvas_post_data['isGlobal'];

			// Get canvas_id from batch-fetched map.
			$canvas_id = $canvas_id_map[ $canvas_post->ID ] ?? '';
			if ( ! $canvas_id ) {
				continue;
			}

			// Get append_to_main from batch-fetched map.
			$append_to_main = $append_to_main_map[ $canvas_post->ID ] ?? '';
			$append_to_main = '' === $append_to_main ? null : $append_to_main;

			// Get z_index from batch-fetched map.
			$z_index = $z_index_map[ $canvas_post->ID ] ?? '';
			$z_index = '' === $z_index ? null : $z_index;

			// Get created_at from batch-fetched map.
			$created_at = $created_at_map[ $canvas_post->ID ] ?? '';

			$candidate_owner_id = absint( $parent_post_id_map[ $canvas_post->ID ] ?? 0 );

			$dedupe_canvas_id = $is_global
				? $canvas_id
				: self::_normalize_local_canvas_id_for_owner( $canvas_id, $candidate_owner_id );
			if ( '' === $dedupe_canvas_id ) {
				continue;
			}

			// Store raw content in metadata (used by REST API for Visual Builder).
			// Content will be processed for meta operations only when caching to post meta.
			$canvas_content = $canvas_post->post_content;
			$candidate_meta = [
				'isGlobal'           => $is_global,
				'name'               => $canvas_post->post_title,
				'appendToMainCanvas' => $append_to_main,
				'zIndex'             => $z_index,
				'postId'             => $canvas_post->ID,
				'content'            => $canvas_content,
				'createdAt'          => $created_at,
			];

			if ( isset( $all_canvas_metadata[ $dedupe_canvas_id ] ) ) {
				$existing_meta     = $all_canvas_metadata[ $dedupe_canvas_id ];
				$existing_owner_id = $canvas_owner_ids[ $dedupe_canvas_id ] ?? 0;
				$existing_raw_id   = $canvas_raw_ids[ $dedupe_canvas_id ] ?? $dedupe_canvas_id;
				$candidate_matches = $canvas_id === $dedupe_canvas_id;
				$existing_matches  = $existing_raw_id === $dedupe_canvas_id;

				// Default behavior keeps latest entry. For duplicate LOCAL IDs, prefer Theme Builder area owner.
				// This aligns frontend with builder hydration dedupe expectations for template-owned canvases.
				$replace_existing = true;
				if ( empty( $existing_meta['isGlobal'] ) && ! $is_global ) {
					$existing_is_area_owner  = $is_theme_builder_area_owner( $existing_owner_id );
					$candidate_is_area_owner = $is_theme_builder_area_owner( $candidate_owner_id );
					if ( $existing_is_area_owner && ! $candidate_is_area_owner ) {
						$replace_existing = false;
					} elseif ( $existing_is_area_owner === $candidate_is_area_owner ) {
						// If owner type ties, prefer the raw ID that already matches the normalized key.
						// Example: keep `header-<uid>` over `header-header-<uid>`.
						if ( $existing_matches && ! $candidate_matches ) {
							$replace_existing = false;
						} elseif ( ! $existing_matches && $candidate_matches ) {
							$replace_existing = true;
						}
					}
				}

				if ( ! $replace_existing ) {
					continue;
				}
			}

			$all_canvas_metadata[ $dedupe_canvas_id ] = $candidate_meta;
			$canvas_owner_ids[ $dedupe_canvas_id ]    = $candidate_owner_id;
			$canvas_raw_ids[ $dedupe_canvas_id ]      = $canvas_id;
		}

		$appended_above = [];
		$appended_below = [];

		foreach ( $all_canvas_metadata as $canvas_id => $canvas_meta ) {
			$append_to_main = $canvas_meta['appendToMainCanvas'] ?? null;
			if ( 'above' === $append_to_main ) {
				$appended_above[] = $canvas_id;
			} elseif ( 'below' === $append_to_main ) {
				$appended_below[] = $canvas_id;
			}
		}

		$canvas_data['appended_above']      = array_values( array_unique( $appended_above ) );
		$canvas_data['appended_below']      = array_values( array_unique( $appended_below ) );
		$canvas_data['all_canvas_metadata'] = $all_canvas_metadata;

		// Extract interaction target IDs from content and find which canvases contain them.
		if ( ! empty( $main_content ) ) {
			$target_ids = OffCanvasHooks::extract_interaction_target_ids_from_content( $main_content );
			if ( ! empty( $target_ids ) ) {
				$interaction_targets = [];

				foreach ( $target_ids as $target_id ) {
					$canvas_ids_for_target = [];

					// Check each canvas to see if it contains this target.
					foreach ( $all_canvas_metadata as $canvas_id => $canvas_meta ) {
						$canvas_content = $canvas_meta['content'] ?? '';
						if ( empty( $canvas_content ) ) {
							continue;
						}

						// Check if canvas contains the target module.
						if ( OffCanvasHooks::canvas_block_content_contains_target( $canvas_content, $target_id ) ) {
							$canvas_ids_for_target[] = $canvas_id;
						}
					}

					if ( ! empty( $canvas_ids_for_target ) ) {
						$interaction_targets[ $target_id ] = $canvas_ids_for_target;
					}
				}

				$canvas_data['interaction_targets'] = $interaction_targets;
			}
		}

		// Cache the structured data in persistent storage.
		// Note: If cache existed with all_canvas_metadata, we would have returned already above.
		// So at this point, cache doesn't exist and we need to create it.
		// Only cache to postmeta for cacheable frontend requests (not VB, previews, etc.),
		// and only when the result is NOT context-specific (see $can_persist_post_cache).
		if ( $is_cacheable_request && $can_persist_post_cache ) {
			// Preserve JSON Unicode escape sequences for WordPress meta operations.
			// WordPress unslashes content when retrieving from DB and update_post_meta() unslashes again,
			// which can corrupt Unicode escapes (\u003c -> u003c). Process content only for caching.
			$cached_canvas_data = $canvas_data;
			if ( isset( $cached_canvas_data['all_canvas_metadata'] ) && is_array( $cached_canvas_data['all_canvas_metadata'] ) ) {
				foreach ( $cached_canvas_data['all_canvas_metadata'] as $canvas_id => $canvas_meta ) {
					if ( isset( $canvas_meta['content'] ) && is_string( $canvas_meta['content'] ) ) {
						$cached_canvas_data['all_canvas_metadata'][ $canvas_id ]['content'] = StringUtility::preserve_unicode_escapes_for_meta( $canvas_meta['content'] );
					}
				}
			}
			update_post_meta( $post_id, '_divi_dynamic_assets_canvases_used', $cached_canvas_data );
		}

		// Also store in static cache for subsequent calls in same request.
		// Store with both the current cache_key AND a base cache_key (post_id only) for cases where
		// main_content wasn't provided initially but is provided later (or vice versa).
		self::$_canvas_data_static_cache[ $cache_key ] = $canvas_data;
		// Also store with base key (post_id + empty main_content hash) for calls without main_content.
		$base_cache_key = $post_id . '_' . md5( '' );
		if ( $base_cache_key !== $cache_key ) {
			self::$_canvas_data_static_cache[ $base_cache_key ] = $canvas_data;
		}

		// Cache post objects for reuse (e.g., by get_off_canvas_data_for_post).
		// Build map of post_id => post object from all_canvas_posts.
		$posts_map = [];
		foreach ( $all_canvas_posts as $canvas_data_item ) {
			$posts_map[ $canvas_data_item['post']->ID ] = $canvas_data_item['post'];
		}
		self::$_canvas_posts_static_cache[ $post_id ] = $posts_map;

		return $canvas_data;
	}

	/**
	 * Normalize a local canvas ID to the owner-aware dedupe key.
	 *
	 * @since ??
	 *
	 * @param string $canvas_id Raw canvas ID.
	 * @param int    $owner_post_id Local owner post ID.
	 *
	 * @return string
	 */
	private static function _normalize_local_canvas_id_for_owner( string $canvas_id, int $owner_post_id ): string {
		$canvas_id = sanitize_text_field( $canvas_id );
		if ( '' === $canvas_id ) {
			return '';
		}

		$owner_post_type = get_post_type( $owner_post_id );
		$layout_map      = [
			'et_header_layout' => 'header',
			'et_body_layout'   => 'body',
			'et_footer_layout' => 'footer',
		];
		$layout          = $layout_map[ $owner_post_type ] ?? '';

		if ( '' === $layout ) {
			return $canvas_id;
		}

		$prefix = "{$layout}-";
		if ( ! str_starts_with( $canvas_id, $prefix ) ) {
			return $canvas_id;
		}

		$base_canvas_id = $canvas_id;
		while ( str_starts_with( $base_canvas_id, $prefix ) ) {
			$base_canvas_id = substr( $base_canvas_id, strlen( $prefix ) );
		}

		if ( '' === $base_canvas_id ) {
			return '';
		}

		return $prefix . $base_canvas_id;
	}

	/**
	 * Merge context-backed local canvases into an existing structured canvas dataset.
	 *
	 * This is used when the persistent postmeta cache exists but the current request is non-singular
	 * (e.g. category archive). Context-backed canvases must be resolved per-request and must never be
	 * written back into postmeta cache keyed only by post/template ID.
	 *
	 * @since ??
	 *
	 * @param array  $canvas_data Structured canvas data.
	 * @param string $context_key Parent context key (e.g. `term:category:12`).
	 * @param string $main_content Optional. Main content to recompute interaction targets when provided.
	 *
	 * @return array Updated structured canvas data.
	 */
	private static function _add_context_canvas_posts_to_canvas_data( array $canvas_data, string $context_key, string $main_content = '' ): array {
		if ( '' === $context_key ) {
			return $canvas_data;
		}

		$context_posts = CanvasUtils::get_context_canvas_posts( $context_key );
		if ( empty( $context_posts ) ) {
			return $canvas_data;
		}

		$existing_metadata = $canvas_data['all_canvas_metadata'] ?? [];
		if ( ! is_array( $existing_metadata ) ) {
			$existing_metadata = [];
		}

		$appended_above = $canvas_data['appended_above'] ?? [];
		$appended_below = $canvas_data['appended_below'] ?? [];
		$appended_above = is_array( $appended_above ) ? $appended_above : [];
		$appended_below = is_array( $appended_below ) ? $appended_below : [];

		$context_post_ids = wp_list_pluck( $context_posts, 'ID' );

		$all_meta           = self::_batch_get_post_meta(
			$context_post_ids,
			[ '_divi_canvas_id', '_divi_canvas_append_to_main', '_divi_canvas_z_index', '_divi_canvas_created_at' ]
		);
		$canvas_id_map      = $all_meta['_divi_canvas_id'] ?? [];
		$append_to_main_map = $all_meta['_divi_canvas_append_to_main'] ?? [];
		$z_index_map        = $all_meta['_divi_canvas_z_index'] ?? [];
		$created_at_map     = $all_meta['_divi_canvas_created_at'] ?? [];

		foreach ( $context_posts as $context_post ) {
			$canvas_id = $canvas_id_map[ $context_post->ID ] ?? '';
			if ( ! $canvas_id ) {
				continue;
			}

			// Do not override existing metadata for the same canvas_id (post-backed wins).
			if ( isset( $existing_metadata[ $canvas_id ] ) ) {
				continue;
			}

			$append_to_main = $append_to_main_map[ $context_post->ID ] ?? '';
			$append_to_main = '' === $append_to_main ? null : $append_to_main;

			$z_index = $z_index_map[ $context_post->ID ] ?? '';
			$z_index = '' === $z_index ? null : $z_index;

			$created_at = $created_at_map[ $context_post->ID ] ?? '';

			$existing_metadata[ $canvas_id ] = [
				'isGlobal'           => false,
				'name'               => $context_post->post_title,
				'appendToMainCanvas' => $append_to_main,
				'zIndex'             => $z_index,
				'postId'             => $context_post->ID,
				'content'            => $context_post->post_content,
				'createdAt'          => $created_at,
			];

			if ( 'above' === $append_to_main ) {
				$appended_above[] = $canvas_id;
			} elseif ( 'below' === $append_to_main ) {
				$appended_below[] = $canvas_id;
			}
		}

		$canvas_data['appended_above']      = array_values( array_unique( $appended_above ) );
		$canvas_data['appended_below']      = array_values( array_unique( $appended_below ) );
		$canvas_data['all_canvas_metadata'] = $existing_metadata;

		// Recompute interaction targets when main content is available (ensures context canvases are included).
		if ( '' !== $main_content ) {
			$target_ids = OffCanvasHooks::extract_interaction_target_ids_from_content( $main_content );
			if ( ! empty( $target_ids ) ) {
				$interaction_targets = [];
				foreach ( $target_ids as $target_id ) {
					$canvas_ids_for_target = [];
					foreach ( $existing_metadata as $id => $meta ) {
						$content = $meta['content'] ?? '';
						if ( '' === $content ) {
							continue;
						}
						if ( OffCanvasHooks::canvas_block_content_contains_target( $content, $target_id ) ) {
							$canvas_ids_for_target[] = $id;
						}
					}
					if ( ! empty( $canvas_ids_for_target ) ) {
						$interaction_targets[ $target_id ] = $canvas_ids_for_target;
					}
				}
				$canvas_data['interaction_targets'] = $interaction_targets;
			}
		}

		return $canvas_data;
	}

	/**
	 * Batch fetch post meta values for multiple post IDs in a single database query.
	 * Optimizes performance by avoiding N database roundtrips.
	 *
	 * @since ??
	 *
	 * @param array        $post_ids  Array of post IDs.
	 * @param string|array $meta_keys Single meta key or array of meta keys to fetch.
	 *
	 * @return array Map of meta_key => [ post_id => meta_value ].
	 *              If single meta key provided, returns [ post_id => meta_value ] for backward compatibility.
	 */
	public static function _batch_get_post_meta( $post_ids, $meta_keys ) {
		if ( empty( $post_ids ) || empty( $meta_keys ) ) {
			return [];
		}

		// Normalize meta_keys to array.
		$single_key_mode = is_string( $meta_keys );
		$meta_keys       = (array) $meta_keys;

		// Sanitize post IDs to integers for SQL IN clause.
		$post_ids = array_map( 'absint', $post_ids );
		$post_ids = array_filter( $post_ids ); // Remove any invalid IDs.
		if ( empty( $post_ids ) ) {
			return [];
		}

		// Sort for consistent cache keys.
		sort( $post_ids, SORT_NUMERIC );
		sort( $meta_keys, SORT_STRING );

		// Use hash for cache key to prevent huge keys.
		$cache_key = md5( implode( ',', $meta_keys ) . '_' . implode( ',', $post_ids ) );

		// Check cache first to avoid redundant queries.
		if ( isset( self::$_batch_post_meta_cache[ $cache_key ] ) ) {
			return self::$_batch_post_meta_cache[ $cache_key ];
		}

		global $wpdb;

		// Build IN clause placeholders.
		$post_ids_placeholders  = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
		$meta_keys_placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );

		// Build query format string with placeholders.
		$query_format = 'SELECT post_id, meta_key, meta_value FROM ' . $wpdb->postmeta . '
			WHERE post_id IN (' . $post_ids_placeholders . ')
			AND meta_key IN (' . $meta_keys_placeholders . ')';

		// Prepare query: [format_string, post_id1, ..., meta_key1, ...].
		$prepare_args = array_merge( [ $query_format ], $post_ids, $meta_keys );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Query IS prepared via call_user_func_array([$wpdb, 'prepare'], ...).
		// phpcs cannot statically verify call_user_func_array calls, but we are correctly using $wpdb->prepare() with proper placeholders.
		$prepared_query = call_user_func_array( [ $wpdb, 'prepare' ], $prepare_args );
		$results        = $wpdb->get_results( $prepared_query, ARRAY_A );
		// phpcs:enable

		// Build map: meta_key => [ post_id => meta_value ].
		$meta_map = [];
		if ( $results ) {
			foreach ( $results as $row ) {
				$meta_key = $row['meta_key'];
				$post_id  = (int) $row['post_id'];

				if ( ! isset( $meta_map[ $meta_key ] ) ) {
					$meta_map[ $meta_key ] = [];
				}

				$meta_map[ $meta_key ][ $post_id ] = $row['meta_value'];
			}
		}

		// If single key mode (backward compatibility), return just that key's map.
		if ( $single_key_mode ) {
			$single_key = $meta_keys[0];
			$result     = $meta_map[ $single_key ] ?? [];
		} else {
			// Return full multi-key structure.
			$result = $meta_map;
		}

		// Cache the results for this request.
		self::$_batch_post_meta_cache[ $cache_key ] = $result;

		return $result;
	}

	/**
	 * Get canvas portal canvas IDs for a post (backward compatibility wrapper).
	 *
	 * @since ??
	 *
	 * @param int $post_id Post ID to get canvas IDs for.
	 *
	 * @return array Array of unique canvas IDs found in canvas portal blocks.
	 */
	public static function get_canvas_portal_canvas_ids_for_post( $post_id ) {
		$canvas_data = self::get_all_canvas_data_for_post( $post_id );
		return $canvas_data['canvas_portal_ids'] ?? [];
	}

	/**
	 * Get cached canvas post objects for a post.
	 * This reuses post objects fetched by get_all_canvas_data_for_post() to avoid duplicate queries.
	 *
	 * @since ??
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array Map of post_id => post object, or empty array if not cached.
	 */
	public static function get_cached_canvas_posts( $post_id ) {
		return self::$_canvas_posts_static_cache[ $post_id ] ?? [];
	}

	/**
	 * Clear in-memory static cache for canvas data.
	 *
	 * NOTE: Postmeta cache (_divi_dynamic_assets_canvases_used) is cleared by
	 * ET_Core_PageResource::remove_static_resources() when clearing dynamic assets cache.
	 * This function only clears the in-memory static cache which is unique to this class.
	 *
	 * @since ??
	 *
	 * @param int|string $post_id Post ID to clear cache for, or 'all' to clear for all posts.
	 *
	 * @return void
	 */
	public static function clear_canvas_ids_cache( $post_id ) {
		if ( 'all' === $post_id ) {
			// Clear static cache for all posts.
			self::$_canvas_data_static_cache              = [];
			self::$_canvas_posts_static_cache             = [];
			self::$_local_canvas_posts_by_owner_set_cache = [];
		} elseif ( $post_id ) {
			// Clear static cache for this post (remove all cache keys starting with post_id).
			foreach ( array_keys( self::$_canvas_data_static_cache ) as $key ) {
				if ( str_starts_with( $key, (string) $post_id . '_' ) ) {
					unset( self::$_canvas_data_static_cache[ $key ] );
				}
			}
			// Clear cached post objects for this post.
			unset( self::$_canvas_posts_static_cache[ $post_id ] );
			// Owner-set cache can include this post in multiple owner groups; clear defensively.
			self::$_local_canvas_posts_by_owner_set_cache = [];
		}
	}

	/**
	 * Get the post IDs of active WP Editor templates and template parts.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function get_wp_editor_template_ids(): array {
		$templates    = et_builder_get_wp_editor_templates();
		$template_ids = [];

		// Bail early if current page doesn't have templates.
		if ( empty( $templates ) ) {
			return $template_ids;
		}

		foreach ( $templates as $template ) {
			$template_ids[] = isset( $template->wp_id ) ? (int) $template->wp_id : 0;
		}

		return $template_ids;
	}

	/**
	 * Check if any widgets are currently active.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function has_builder_widgets(): bool {
		global $wp_registered_sidebars;

		$sidebars = get_option( 'sidebars_widgets' );

		foreach ( $wp_registered_sidebars as $sidebar_key => $sidebar_options ) {
			if ( ! empty( $sidebars[ $sidebar_key ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether current block widget is active or not.
	 *
	 * @since ??
	 *
	 * @param string $block_widget_name Block widget name.
	 *
	 * @return boolean Whether current block widget is active or not.
	 */
	public static function is_active_block_widget( string $block_widget_name ): bool {
		return in_array( $block_widget_name, self::get_active_block_widgets(), true );
	}

	/**
	 * Get Extra Home layout ID.
	 *
	 * @since ??
	 *
	 * @return int|null
	 */
	public static function get_extra_home_layout_id() {
		if ( function_exists( 'extra_get_home_layout_id' ) ) {
			return extra_get_home_layout_id();
		}
		return null;
	}

	/**
	 *  Get Extra Taxonomy layout ID.
	 *
	 * @since 4.17.5
	 *
	 * @return int|null
	 */
	public static function get_extra_tax_layout_id() {
		if ( function_exists( 'extra_get_tax_layout_id' ) ) {
			return extra_get_tax_layout_id();
		}
		return null;
	}

	/**
	 * Check whether Extra Home layout is being used.
	 *
	 * @since ??
	 *
	 * @return boolean whether Extra Home layout is being used.
	 */
	public static function is_extra_layout_used_as_front(): bool {
		return function_exists( 'et_extra_show_home_layout' ) && et_extra_show_home_layout() && is_front_page();
	}

	/**
	 * Check whether Extra Home layout is being used.
	 *
	 * @since ??
	 *
	 * @return boolean whether Extra Home layout is being used.
	 */
	public static function is_extra_layout_used_as_home(): bool {
		return function_exists( 'et_extra_show_home_layout' ) && et_extra_show_home_layout() && is_home();
	}

	/**
	 * Check if the current request is for a static file that doesn't need processing.
	 *
	 * Static files include CSS/JS source maps, well-known files, and other assets
	 * that are served directly without needing feature detection or dynamic asset processing.
	 *
	 * This check is only active during development (when ET_DEBUG is true) because static
	 * file requests are primarily an issue when browser dev tools are open. In production,
	 * these files are typically cached by CDN/browser and don't trigger PHP processing.
	 *
	 * When dev tools are open, they can trigger many additional HTTP requests for source maps
	 * and other static assets. Without this check, these requests would unnecessarily run through
	 * the entire DynamicAssets pipeline, filling logs and slowing down the website.
	 *
	 * @since ??
	 *
	 * @return bool True if this is a static file request, false otherwise.
	 */
	public static function is_static_file_request(): bool {
		// Only filter static files during development (ET_DEBUG mode).
		// In production, these requests are typically handled by CDN/browser cache.
		if ( ! defined( 'ET_DEBUG' ) || ! ET_DEBUG ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized -- REQUEST_URI used only for pattern matching, not output.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

		if ( empty( $request_uri ) ) {
			return false;
		}

		// Skip CSS/JS map files, well-known files, and other static assets.
		return preg_match( '/\.(css\.map|js\.map)$/i', $request_uri ) ||
				str_contains( $request_uri, '/.well-known/' );
	}

	/**
	 * Check to see if this is a front end request applicable to Dynamic Assets.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_dynamic_front_end_request(): bool {
		if ( null === self::$_is_dynamic_front_end_request ) {
			self::$_is_dynamic_front_end_request = false;

			// WordPress auto-update scrape requests are health checks and should not generate cache files.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request inspection for cache-safety behavior.
			$is_wp_auto_update_scrape_request = isset( $_GET['wp_scrape_key'], $_GET['wp_scrape_nonce'] );

			if ( // Skip static file requests.
				! self::is_static_file_request()
				// Disable for WordPress auto-update scrape health-check requests.
				&& ! $is_wp_auto_update_scrape_request
				// Disable for WordPress admin requests.
				&& ! is_admin()
				// Disable for non-front-end requests.
				&& ! wp_doing_ajax()
				&& ! wp_doing_cron()
				&& ! wp_is_json_request()
				&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
				&& ! ( defined( 'WP_CLI' ) && WP_CLI )
				&& ! ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
				&& ! is_trackback()
				&& ! is_feed()
				&& ! get_query_var( 'sitemap' )
				// Disable when in preview modes.
				&& ! is_customize_preview()
				&& ! is_et_pb_preview()
				&& ! ET_GB_Block_Layout::is_layout_block_preview()
				&& ! is_preview()
				// Disable when using the visual builder.
				&& ! et_fb_is_enabled()
				// Disable on paginated index pages when blog style mode is enabled and when using the Divi Builder plugin.
				&& ! ( is_paged() && ( 'on' === et_get_option( 'divi_blog_style', 'off' ) || et_is_builder_plugin_active() ) )
			) {
				self::$_is_dynamic_front_end_request = true;
			}
		}

		return self::$_is_dynamic_front_end_request;
	}

	/**
	 * Check if current page is a taxonomy page.
	 *
	 * @since ??
	 *
	 * @return boolean
	 */
	public static function is_taxonomy(): bool {
		return is_tax() || is_category() || is_tag();
	}

	/**
	 * Check if current page is virtual.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_virtual_page(): bool {
		global $wp;
		$slug = $wp->request;

		// Value for the filter.
		$valid_virtual_pages = [
			'homes-for-sale-search',
			'homes-for-sale-search-advanced',
		];

		/**
		 * Valid virtual pages for which dynamic css should be enabled.
		 * Virtual pages are just custom enpoints or links added via rewrite hooks,
		 * Meaning, it's not an actual page but it does have a valid link possibly,
		 * custom generated by a plugin.
		 *
		 * Add more virtual pages slug if there are known compatibility issues.
		 *
		 * This filter is the replacement of Divi 4 filter `et_builder_dynamic_css_virtual_pages`.
		 *
		 * @since ??
		 *
		 * @return array $valid_virtual_pages
		 */
		$valid_virtual_pages = apply_filters(
			'divi_frontend_assets_dynamic_assets_utils_virtual_pages',
			$valid_virtual_pages
		);

		if ( in_array( $slug, $valid_virtual_pages, true ) ) {
			return true;
		}

		// Usually custom rewrite rules will return as page but will have no ID.
		if ( is_page() && 0 === get_the_ID() ) {
			return true;
		}

		return false;
	}

	/**
	 * Get embedded media from post content.
	 *
	 * Also checks WordPress page context conditions for fitvids script enqueuing:
	 * - Single posts without builder
	 * - Home page (not front page)
	 * - Non-singular pages (archives, etc.)
	 *
	 * @since ??
	 *
	 * @param string $content Post Content.
	 *
	 * @return boolean false on failure, true on success.
	 */
	public static function is_media_embedded_in_content( string $content ): bool {
		// Check WordPress page context conditions first (for fitvids script).
		// These page types may contain embedded media that needs fitvids.
		global $post;
		$page_builder_used = is_singular() && et_pb_is_pagebuilder_used( $post->ID ?? 0 );
		if ( ( is_single() && ! $page_builder_used ) || ( is_home() && ! is_front_page() ) || ! is_singular() ) {
			return true;
		}

		// Check content for embedded media.
		if ( empty( $content ) ) {
			return false;
		}

		// regex match for youtube and vimeo urls in $content.
		$pattern = '~https?://(?:www\.)?(?:youtube\.com/watch\?v=|youtu\.be/|vimeo\.com/)([^\s]+)~i';
		preg_match_all( $pattern, $content, $matches );

		if ( empty( $matches[0] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check to see if we should initiate initial class logic.
	 *
	 * @since ??
	 *
	 * @return bool.
	 */
	public static function should_initiate_dynamic_assets(): bool {
		// Bail if this is not a front-end or builder page request.
		if ( ! et_builder_is_frontend_or_builder() ) {
			return false;
		}

		// Bail on VB top window and app window.
		if ( Conditions::is_vb_top_window() || Conditions::is_vb_app_window() ) {
			return false;
		}

		// Bail if Dynamic CSS and Dynamic JS are both disabled.
		if ( ! self::use_dynamic_assets() && self::disable_js_on_demand() ) {
			return false;
		}

		// Bail if feed since CSS isn't needed for RSS/Atom.
		if ( is_feed() ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if the current request should generate Dynamic Assets.
	 * We only generate dynamic assets on the front end and when cache dir is writable.
	 *
	 * The filtered result is memoized for the request. Call DynamicAssetsUtils::reset()
	 * before re-evaluating after filter or request-context changes.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function should_generate_dynamic_assets(): bool {
		if ( null === self::$_should_generate_dynamic_assets ) {
			$should_generate_assets = false;

			if ( // Cache directory must be writable.
				et_core_cache_dir()->can_write
				// Request must be an applicable front-end request.
				&& self::is_dynamic_front_end_request()
			) {
				$should_generate_assets = true;
			}

			/**
			 * Filters whether to generate dynamic assets.
			 *
			 * This filter is the replacement of Divi 4 filter `et_should_generate_dynamic_assets`.
			 *
			 * @since ??
			 *
			 * @param bool $should_generate_assets
			 */
			self::$_should_generate_dynamic_assets = (bool) apply_filters(
				'divi_frontend_assets_dynamic_assets_utils_should_generate_dynamic_assets',
				$should_generate_assets
			);
		}

		return self::$_should_generate_dynamic_assets;
	}

	/**
	 * Check if the dynamic assets detection pipeline should run on this request.
	 *
	 * When false, early content parsing, render-time logging, and late detection are skipped.
	 * Generation and enqueue remain governed by should_generate_dynamic_assets() and use_dynamic_assets().
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function should_run_detection(): bool {
		static $should_run_detection = null;

		if ( null === $should_run_detection ) {
			$should_run_detection = true;

			/**
			 * Filters whether to run the dynamic assets detection pipeline.
			 *
			 * Skips early content parsing, render_block_data / pre_do_shortcode_tag logging,
			 * and late detection when false. Does not affect generation or enqueue.
			 *
			 * @since ??
			 *
			 * @param bool $should_run_detection Whether to run detection.
			 */
			$should_run_detection = apply_filters(
				'divi_frontend_assets_dynamic_assets_utils_should_run_detection',
				(bool) $should_run_detection
			);
		}

		return $should_run_detection;
	}

	/**
	 * Get whether third party post interference should be respected.
	 * Current use case is for plugins like Toolset that render a
	 * loop within a layout which renders another layout for
	 * each post - in this case we must NOT override the
	 * current post so the loop works as expected.
	 *
	 * @since ?? Copied from `ET_Builder_Element::_should_respect_post_interference()`.
	 *
	 * @return boolean
	 */
	public static function should_respect_post_interference(): bool {
		$post = ET_Post_Stack::get();

		return null !== $post && get_the_ID() !== $post->ID;
	}

	/**
	 * Check if Dynamic CSS is enabled.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function use_dynamic_assets(): bool {
		/**
		 * IMPORTANT: We must use `should_generate_dynamic_assets`, which uses `is_dynamic_front_end_request`, to
		 * ensure Dynamic Assets is **are** disabled on paginated index pages when "Blog Style Mode" is enabled and
		 * when using the Divi Builder plugin, along with other cases where it should be **disabled**.
		 *
		 * Otherwise, pages like `example.com/category/uncategorized/page/2/` will have broken styles when
		 * Theme Builder templates are in use; and there could be other potential issues.
		 *
		 * In addition to the mentioned cases above, `divi_frontend_assets_dynamic_assets_utils_use_dynamic_assets`
		 * filter is used to disable Dynamic Assets for preview pages and/or the test environment. Therefore, the
		 * apply_filters here needs to function correctly.
		 */

		if ( null === self::$_use_dynamic_assets ) {
			/*
			 * Removed the `{$shortname}_dynamic_css` or `et_pb_builder_options` option check to force Dynamic Assets
			 * to be on to improve performance.
			 */
			self::$_use_dynamic_assets = self::should_generate_dynamic_assets();

			/**
			 * Filters whether to use dynamic CSS.
			 *
			 * This filter is the replacement of Divi 4 filter `et_use_dynamic_css`.
			 *
			 * @since ??
			 *
			 * @param bool $use_dynamic_assets
			 */
			self::$_use_dynamic_assets = apply_filters( 'divi_frontend_assets_dynamic_assets_utils_use_dynamic_assets', self::$_use_dynamic_assets );
		}

		return self::$_use_dynamic_assets;
	}

	/**
	 * Check if Dynamic Icons are enabled.
	 *
	 * @since ??
	 */
	public static function use_dynamic_icons() {
		global $shortname;
		$child_theme_active = is_child_theme();

		if ( et_is_builder_plugin_active() ) {
			$options       = get_option( 'et_pb_builder_options', [] );
			$dynamic_icons = $options['performance_main_dynamic_icons'] ?? self::get_dynamic_icons_default_value();
		} else {
			$dynamic_icons = et_get_option( $child_theme_active ? $shortname . '_dynamic_icons_child_theme' : $shortname . '_dynamic_icons', self::get_dynamic_icons_default_value() );
		}

		return $dynamic_icons;
	}

	/**
	 * Enqueues D5 group carousel script, used for group carousel modules.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_group_carousel_script() {
		wp_enqueue_script(
			'divi-module-library-script-group-carousel',
			ET_BUILDER_5_URI . '/visual-builder/build/module-library-script-group-carousel.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 lottie script, used for lottie modules.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_lottie_script() {
		wp_enqueue_script(
			'divi-module-library-script-lottie',
			ET_BUILDER_5_URI . '/visual-builder/build/module-library-script-lottie.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Enqueues D5 charts script, used for charts modules.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function enqueue_charts_script() {
		wp_enqueue_script(
			'divi-module-library-script-charts',
			ET_BUILDER_5_URI . '/visual-builder/build/module-library-script-charts.js',
			[ 'jquery' ],
			ET_CORE_VERSION,
			true
		);
	}

	/**
	 * Check if a feature value is meaningful (non-empty).
	 *
	 * Empty arrays mean "not found" and shouldn't be treated as valid feature detections.
	 * This is used to filter out empty preset detection results that shouldn't block content detection.
	 *
	 * @since ??
	 *
	 * @param mixed $value Feature value to check (can be array, bool, or other types).
	 *
	 * @return bool True if value is meaningful (non-empty), false otherwise.
	 */
	public static function is_meaningful_feature_value( $value ): bool {
		if ( is_array( $value ) ) {
			return ! empty( array_filter( $value ) );
		}
		return ! empty( $value );
	}

	/**
	 * Filter feature array to only include meaningful (non-empty) values.
	 *
	 * Empty arrays mean "not found" and shouldn't be cached or merged into _early_attributes.
	 * This prevents empty preset detection results from blocking content detection.
	 *
	 * @since ??
	 *
	 * @param array $features Array of feature detection results.
	 *
	 * @return array Filtered array containing only meaningful (non-empty) feature values.
	 */
	public static function filter_meaningful_features( array $features ): array {
		return array_filter(
			$features,
			[ self::class, 'is_meaningful_feature_value' ]
		);
	}

	/**
	 * Reset static caches.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$_batch_post_meta_cache                 = [];
		self::$_canvas_data_static_cache              = [];
		self::$_canvas_posts_static_cache             = [];
		self::$_local_canvas_posts_by_owner_set_cache = [];
		self::$_is_dynamic_front_end_request          = null;
		self::$_should_generate_dynamic_assets        = null;
		self::$_use_dynamic_assets                    = null;
		self::$_disable_js_on_demand                  = null;
	}
}
