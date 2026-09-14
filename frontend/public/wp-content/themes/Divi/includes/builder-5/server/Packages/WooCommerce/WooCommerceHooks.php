<?php
/**
 * Handles WooCommerce-specific hooks and dependencies.
 *
 * This class is responsible for registering and managing actions
 * and filters related to WooCommerce integration.
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\Packages\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use Automattic\WooCommerce\Blocks\BlockTypes\Checkout;
use Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils;
use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\FrontEnd\Assets\DetectFeature;
use ET\Builder\Packages\Module\Options\BoxShadow\BoxShadowClassnames;
use ET\Builder\Packages\Module\Options\BoxShadow\BoxShadowOverlay;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use ET\Builder\Packages\Conversion\Conversion;
use ET\Builder\Framework\Settings\PageSettings;
use ET\Builder\ThemeBuilder\Layout;
use ET_Theme_Builder_Request;
use ET_Post_Stack;
use Exception;
use WC_Frontend_Scripts;
use WC_Shortcodes;
use WP_Post;

/**
 * Manages WooCommerce-related hooks and functionalities.
 *
 * This class facilitates the initialization of WooCommerce-specific
 * actions and filters required for proper integration.
 *
 * @since ??
 */
class WooCommerceHooks implements DependencyInterface {
	/**
	 * Post meta-key for product's long-description.
	 *
	 * @since ??
	 */
	const PRODUCT_LONG_DESC_META_KEY = '_et_pb_old_content';

	/**
	 * Post meta-key for product page layout.
	 *
	 * @since ??
	 */
	const PRODUCT_PAGE_LAYOUT_META_KEY = '_et_pb_product_page_layout';

	/**
	 * Post meta-key for product page content status.
	 *
	 * @since ??
	 */
	const PRODUCT_PAGE_CONTENT_STATUS_META_KEY = '_et_pb_woo_page_content_status';

	/**
	 * Flag to track if hook relocation has been executed.
	 *
	 * Prevents duplicate relocation when both `wp` and `et_builder_ready` hooks fire.
	 *
	 * @since ??
	 *
	 * @var bool
	 */
	private static $_relocation_done = false;

	/**
	 * Initializes and registers necessary WooCommerce actions and filters.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		// Bail when WooCommerce plugin is not active.
		if ( ! et_is_woocommerce_plugin_active() ) {
			return;
		}

		// Remove lightbox theme support in Visual Builder to prevent PhotoSwipe initialization.
		add_action( 'after_setup_theme', [ self::class, 'remove_lightbox_theme_support_in_vb' ], 20 );

		// Register Theme Builder notice suppression after D5 modules initialize (mirrors D4 lazy init).
		add_action( 'divi_modules_initialize', [ self::class, 'register_tb_notice_suppression' ] );

		// Also suppress default notices on regular single product pages that include the D5 Woo Notice block.
		// This covers non-Theme Builder contexts where the block is used directly in post content.
		add_action(
			'woocommerce_before_single_product',
			static function (): void {
				global $post;

				if ( $post && is_object( $post ) && has_block( 'divi/woocommerce-cart-notice', $post->post_content ?? '' ) ) {
					remove_action( 'woocommerce_before_single_product', 'woocommerce_output_all_notices', 10 );
				}
			},
			0
		);

		// global $post won't be available with `after_setup_theme` hook and hence `wp` hook is used.
		remove_action( 'wp', 'et_builder_wc_override_default_layout' );
		add_action( 'wp', [ self::class, 'override_default_layout' ] );

		// Add WooCommerce class names on non-`product` CPT which uses builder.
		// Note: following filters are being called from `et_builder_wc_init` for legacy wc modules, too.
		add_filter( 'body_class', [ self::class, 'add_body_class' ] );
		add_filter( 'et_builder_inner_content_class', [ self::class, 'add_inner_content_class' ] );
		add_filter( 'et_pb_preview_wrap_class', [ self::class, 'add_preview_wrap_class' ] );
		add_filter( 'et_builder_outer_content_class', [ self::class, 'add_outer_content_class' ] );

		// Remove legacy hooks.
		remove_action( 'wp_enqueue_scripts', 'et_builder_wc_load_scripts', 15 );
		// Load WooCommerce related scripts.
		add_action( 'wp_enqueue_scripts', [ self::class, 'load_scripts' ], 15 );
		// Ensure Theme Builder partial overrides are available before Woo Coming Soon renders templates.
		add_action( 'wp', [ self::class, 'register_theme_builder_partials_for_coming_soon' ], 8 );

		// Override WooCommerce product thumbnail template to add et_shop_image wrapper.
		remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
		add_action( 'woocommerce_before_shop_loop_item_title', [ self::class, 'template_loop_product_thumbnail' ], 10 );

		// Remove legacy hooks.
		remove_filter(
			'et_builder_skip_content_activation',
			'et_builder_wc_skip_initial_content',
			10
		);

		add_filter(
			'et_builder_skip_content_activation',
			[ self::class, 'skip_initial_content' ],
			10,
			2
		);

		// Remove legacy hook before adding the new one to avoid duplicate functionality.
		remove_filter( 'et_builder_settings_definitions', 'et_builder_wc_add_settings' );
		// Adds WooCommerce Module settings to the Builder settings.
		// Adding in the Builder Settings tab will ensure that the field is available in Extra Theme and
		// Divi Builder Plugin. Divi Theme Options ⟶ Builder ⟶ Post Type Integration.
		add_filter( 'et_builder_settings_definitions', [ self::class, 'add_settings' ] );

		// Remove legacy hooks before adding new ones to avoid duplicate functionality.
		remove_action( 'add_meta_boxes_product', 'et_builder_wc_long_description_metabox_register' );
		remove_action( 'et_pb_old_content_updated', 'et_builder_wc_long_description_metabox_save', 10 );

		// Note: Meta box hooks are registered in Admin\WooCommerce class for WordPress admin area
		// D5 Visual Builder page settings hook must be registered here for frontend context.
		add_action( 'divi_visual_builder_initialize', [ self::class, 'register_page_settings_items' ] );

		// Remove legacy hook.
		remove_filter(
			'et_fb_load_raw_post_content',
			'et_builder_wc_set_prefilled_page_content',
			10
		);

		/*
		 * 01. Sets the initial Content when `Use Divi Builder` button is clicked
		 * in the Admin dashboard.
		 * 02. Sets the initial Content when `Enable Visual Builder` is clicked.
		 */
		add_filter(
			'et_fb_load_raw_post_content',
			[ self::class, 'set_prefilled_page_content' ],
			10,
			2
		);

		// Remove legacy hook.
		remove_action( 'et_save_post', 'et_builder_set_product_page_layout_meta' );
		// Set product page layout meta on post save.
		add_action( 'divi_visual_builder_rest_save_post', [ self::class, 'set_product_page_layout_meta' ] );

		// Remove legacy hook.
		remove_action( 'et_update_post', 'et_builder_wc_set_page_content_status' );

		/*
		 * Set the Product modified status as modified upon save to make sure the default layout is not
		 * loaded more than one time.
		 */
		add_action( 'divi_visual_builder_rest_update_post', [ self::class, 'set_page_content_status' ] );

		remove_filter( 'the_content', 'et_builder_avoid_nested_shortcode_parsing' );
		// Strip Builder shortcodes to prevent nested parsing issues.
		add_filter( 'the_content', [ self::class, 'avoid_nested_shortcode_parsing' ] );

		// Parse product description for shortcode and block output.
		add_filter( 'et_builder_wc_description', [ self::class, 'parse_description' ] );

		remove_action( 'rest_after_insert_page', 'et_builder_wc_delete_post_meta' );
		// Clean up product page content status meta when Builder is disabled.
		add_action( 'rest_after_insert_page', [ self::class, 'delete_post_meta' ] );

		// Add cache invalidation for WooCommerce product descriptions when that product is updated.
		add_action( 'rest_after_insert_page', [ self::class, 'invalidate_product_description_caches' ] );

		// Add cache invalidation for WooCommerce breadcrumbs when a product is updated.
		add_action( 'rest_after_insert_page', [ self::class, 'invalidate_breadcrumb_caches' ] );

		// Remove legacy hook.
		remove_action( 'template_redirect', 'et_builder_wc_template_redirect', 9 );
		// Stop WooCommerce from redirecting Checkout page to Cart when the cart is empty.
		add_action( 'template_redirect', [ self::class, 'template_redirect' ], 9 );

		// Remove legacy hook.
		remove_filter( 'woocommerce_checkout_redirect_empty_cart', 'et_builder_stop_cart_redirect_while_enabling_builder' );
		// Stop redirecting to the Cart page when enabling builder on the Checkout page.
		add_filter( 'woocommerce_checkout_redirect_empty_cart', [ self::class, 'stop_cart_redirect_while_enabling_builder' ] );

		// Remove legacy hook.
		remove_action( 'wp_loaded', 'et_builder_handle_shipping_calculator_update_btn_click' );
		// Handle shipping calculator form's submission for the cart totals module.
		add_action( 'wp_loaded', [ self::class, 'handle_shipping_calculator_update_btn_click' ] );

		// Relocate WooCommerce single product summary hooks to Divi modules.
		// Uses dual-hook strategy to handle both FE and VB contexts:
		// - 'wp' hook: Ensures relocation happens early on FE before WooCommerce templates load
		// - 'et_builder_ready' hook: Handles VB context where 'wp' doesn't fire reliably
		// Static $_relocation_done guard prevents duplicate execution when both hooks fire.
		add_action( 'wp', [ self::class, 'relocate_woocommerce_single_product_summary' ] );
		add_action( 'et_builder_ready', [ self::class, 'relocate_woocommerce_single_product_summary' ] );

		/**
		 * Wrap WooCommerce reviews tab callback with safe wrapper to prevent DivisionByZeroError
		 * when comments_per_page is 0 with pagination enabled (WordPress Trac #61468).
		 *
		 * This filter ensures the reviews tab on single product pages uses et_comments_template_safe()
		 * instead of calling comments_template() directly.
		 *
		 * TODO fix( D4, Comments ): Remove this require and the shim file after WordPress core resolves Trac // 61468. [https://github.com/elegantthemes/Divi/issues/28338]
		 *
		 * @see https://github.com/elegantthemes/Divi/issues/28338
		 */
		add_filter(
			'woocommerce_product_tabs',
			static function ( $tabs ) {
				if ( isset( $tabs['reviews'] ) && 'comments_template' === $tabs['reviews']['callback'] ) {
					$tabs['reviews']['callback'] = static function () {
						et_comments_template_safe();
					};
				}
				return $tabs;
			},
			PHP_INT_MAX
		);

		// Ensure WooCommerce Blocks checkout settings are registered for REST API.
		add_action( 'rest_api_init', [ self::class, 'ensure_woocommerce_checkout_settings_registration' ], 5 );
		add_filter( 'rest_pre_dispatch', [ WooCommerceUtils::class, 'prime_rest_request_params_on_pre_dispatch' ], 10, 3 );

		// Render Terms & Conditions page content with Theme Builder template when applicable.
		// WooCommerce uses action hook 'woocommerce_checkout_terms_and_conditions' with function
		// wc_terms_and_conditions_page_content() at priority 30. We hook at priority 20 to run before
		// the default function and replace its output with Theme Builder rendered content.
		// The Terms & Conditions content is displayed inline above the checkbox when the link is clicked.
		add_action( 'woocommerce_checkout_terms_and_conditions', [ self::class, 'render_terms_and_conditions_with_theme_builder_action' ], 20 );

		// Reuse the Divi checkout template whenever a checkout module renders an isolated section.
		add_filter( 'wc_get_template', [ self::class, 'maybe_swap_isolated_checkout_form_template' ], 10, 5 );
	}

	/**
	 * Register Theme Builder header/footer partial overrides for Woo Coming Soon requests.
	 *
	 * Woo Coming Soon exits early from `template_include` in non-FSE themes, which can bypass
	 * Theme Builder's own template override wiring. This method pre-registers the same
	 * header/footer override callbacks so Coming Soon pages can still render inside Divi's
	 * Theme Builder shell.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function register_theme_builder_partials_for_coming_soon(): void {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! self::is_store_pages_coming_soon_enabled() ) {
			return;
		}

		if ( ! self::is_store_page_request() ) {
			return;
		}

		if ( ! self::has_theme_builder_header_or_footer_override() ) {
			return;
		}

		if (
			! function_exists( 'et_theme_builder_frontend_override_header' )
			|| ! function_exists( 'et_theme_builder_frontend_override_footer' )
		) {
			return;
		}

		// Match Divi Theme Builder behavior by preventing default admin bar output in body open.
		remove_action( 'wp_body_open', 'wp_admin_bar_render', 0 );

		if ( false === has_action( 'get_header', 'et_theme_builder_frontend_override_header' ) ) {
			add_action( 'get_header', 'et_theme_builder_frontend_override_header' );
		}

		if ( false === has_action( 'get_footer', 'et_theme_builder_frontend_override_footer' ) ) {
			add_action( 'get_footer', 'et_theme_builder_frontend_override_footer' );
		}

		if ( function_exists( 'et_prioritize_deferred_block_css_for_theme_builder' ) ) {
			et_prioritize_deferred_block_css_for_theme_builder();
		}
	}

	/**
	 * Determine whether WooCommerce "Coming Soon" is enabled for store pages only.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function is_store_pages_coming_soon_enabled(): bool {
		$is_coming_soon_enabled = 'yes' === get_option( 'woocommerce_coming_soon', 'no' );
		$is_store_pages_only    = 'yes' === get_option( 'woocommerce_store_pages_only', 'no' );

		return $is_coming_soon_enabled && $is_store_pages_only;
	}

	/**
	 * Determine whether current request targets a WooCommerce store page.
	 *
	 * Mirrors WooCommerce's "store pages" scope used by Site Visibility settings.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function is_store_page_request(): bool {
		$is_shop             = function_exists( 'is_shop' ) && is_shop();
		$is_cart             = function_exists( 'is_cart' ) && is_cart();
		$is_checkout         = function_exists( 'is_checkout' ) && is_checkout();
		$is_account_page     = function_exists( 'is_account_page' ) && is_account_page();
		$is_product          = function_exists( 'is_product' ) && is_product();
		$is_product_category = function_exists( 'is_product_category' ) && is_product_category();
		$is_product_tag      = function_exists( 'is_product_tag' ) && is_product_tag();
		$is_product_taxonomy = function_exists( 'is_product_taxonomy' ) && is_product_taxonomy();

		return (
			$is_shop
			|| $is_cart
			|| $is_checkout
			|| $is_account_page
			|| $is_product
			|| $is_product_category
			|| $is_product_tag
			|| $is_product_taxonomy
		);
	}

	/**
	 * Determine whether Theme Builder overrides header or footer on current request.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function has_theme_builder_header_or_footer_override(): bool {
		if ( ! function_exists( 'et_theme_builder_get_template_layouts' ) ) {
			return false;
		}

		$layouts = et_theme_builder_get_template_layouts();

		if ( empty( $layouts ) ) {
			return false;
		}

		// Read override flags directly so a partial layouts array (e.g. header-only
		// test fixtures) cannot trigger undefined-index notices from
		// et_theme_builder_overrides_layout(), which assumes all layout keys exist.
		$has_header_override = defined( 'ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE' )
			&& ! empty( $layouts[ ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE ]['override'] );
		$has_footer_override = defined( 'ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE' )
			&& ! empty( $layouts[ ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE ]['override'] );

		return $has_header_override || $has_footer_override;
	}

	/**
	 * Swap the checkout form template for isolated checkout section renders.
	 *
	 * @since ??
	 *
	 * @param string      $template      Template.
	 * @param string      $template_name Template name.
	 * @param array       $args          Arguments.
	 * @param string|null $template_path Template path.
	 * @param string|null $default_path  Default path.
	 *
	 * @return string
	 */
	public static function maybe_swap_isolated_checkout_form_template( string $template, string $template_name, array $args, ?string $template_path = null, ?string $default_path = null ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Filter signature requires all parameters.
		if ( 'checkout/form-checkout.php' !== $template_name ) {
			return $template;
		}

		if ( ! WooCommerceUtils::is_isolated_checkout_section_render() ) {
			return $template;
		}

		return trailingslashit( ET_BUILDER_5_DIR ) . 'server/Packages/WooCommerce/Templates/' . $template_name;
	}

	/**
	 * Register Theme Builder notice suppression hook.
	 *
	 * Mirrors legacy D4 timing (lazy shortcode registration) by running after D5 modules initialize.
	 *
	 * @since ??
	 * @return void
	 */
	public static function register_tb_notice_suppression(): void {
		if ( ! et_is_woocommerce_plugin_active() ) {
			return;
		}

		// Suppress default notices only for Theme Builder body layouts that contain the D5 Woo Notice block.
		add_action(
			'et_theme_builder_after_layout_opening_wrappers',
			static function ( $layout_type, $layout_id ): void {
				if ( 'et_body_layout' !== $layout_type ) {
					return;
				}

				$layout = get_post( $layout_id );
				if ( ! $layout ) {
					return;
				}

				if ( has_block( 'divi/woocommerce-cart-notice', $layout->post_content ) ) {
					remove_action( 'woocommerce_before_single_product', 'woocommerce_output_all_notices', 10 );
				}
			},
			5,
			2
		);
	}

	/**
	 * Adds WooCommerce settings to the Builder settings.
	 *
	 * This method adds WooCommerce-specific settings to the Builder settings via the
	 * 'et_builder_settings_definitions' filter. It includes settings for product page
	 * layouts and product layout.
	 *
	 * Legacy function: et_builder_wc_add_settings()
	 *
	 * @since ??
	 *
	 * @param array $builder_settings_fields Current builder settings.
	 * @return array Modified builder settings with WooCommerce options.
	 */
	public static function add_settings( array $builder_settings_fields ): array {
		// Bail early if WooCommerce is not active.
		if ( ! function_exists( 'wc_get_product' ) ) {
			return $builder_settings_fields;
		}

		$fields = [
			'et_pb_woocommerce_product_layout' => [
				'type'            => 'select',
				'id'              => 'et_pb_woocommerce_product_layout',
				'index'           => -1,
				'label'           => esc_html__( 'Product Layout', 'et_builder_5' ),
				'description'     => esc_html__( 'Here you can choose Product Page Layout for WooCommerce.', 'et_builder_5' ),
				'options'         => [
					'et_right_sidebar'   => esc_html__( 'Right Sidebar', 'et_builder_5' ),
					'et_left_sidebar'    => esc_html__( 'Left Sidebar', 'et_builder_5' ),
					'et_no_sidebar'      => esc_html__( 'No Sidebar', 'et_builder_5' ),
					'et_full_width_page' => esc_html__( 'Fullwidth', 'et_builder_5' ),
				],
				'default'         => 'et_right_sidebar',
				'validation_type' => 'simple_text',
				'et_save_values'  => true,
				'tab_slug'        => 'post_type_integration',
				'toggle_slug'     => 'performance',
			],
			'et_pb_woocommerce_page_layout'    => [
				'type'            => 'select',
				'id'              => 'et_pb_woocommerce_product_page_layout',
				'index'           => -1,
				'label'           => esc_html__( 'Product Content', 'et_builder_5' ),
				'description'     => esc_html__( '\"Build From Scratch\" loads a pre-built WooCommerce page layout, with which you build on when the Divi Builder is enabled. \"Default\" option lets you use default WooCommerce page layout.', 'et_builder_5' ),
				'options'         => WooCommerceUtils::get_page_layouts(),
				'default'         => 'et_build_from_scratch',
				'validation_type' => 'simple_text',
				'et_save_values'  => true,
				'tab_slug'        => 'post_type_integration',
				'toggle_slug'     => 'performance',
			],
		];

		// Hide setting in Divi Builder Plugin.
		if ( et_is_builder_plugin_active() ) {
			unset( $fields['et_pb_woocommerce_product_layout'] );
		}

		return array_merge( $builder_settings_fields, $fields );
	}

	/**
	 * Identify whether Woo v2 should replace content on Cart & Checkout pages.
	 *
	 * This method handles both legacy shortcode content and Gutenberg block content.
	 * It checks if the content contains only default blocks/shortcodes that indicate
	 * the user hasn't customized the page.
	 *
	 * Based on legacy function: et_builder_wc_should_replace_content().
	 *
	 * @since ??
	 *
	 * @param string $content Post content (can be shortcode or Gutenberg blocks).
	 *
	 * @return bool True if content should be replaced, false otherwise.
	 */
	public static function should_replace_content( $content ): bool {
		$should_replace_content = true;

		// Handle Gutenberg block content.
		if ( function_exists( 'parse_blocks' ) && has_blocks( $content ) ) {
			$blocks         = parse_blocks( $content );
			$default_blocks = [
				'divi/section',
				'divi/row',
				'divi/column',
				'divi/text',
				'divi/placeholder',
				'woocommerce/cart',
				'woocommerce/checkout',
				'core/paragraph',
				'core/heading',
			];

			foreach ( $blocks as $block ) {
				// Skip empty blocks.
				if ( empty( $block['blockName'] ) ) {
					continue;
				}

				// If a block exists that is not a default block, don't replace content.
				if ( ! in_array( $block['blockName'], $default_blocks, true ) ) {
					$should_replace_content = false;
					break;
				}

				// Also check inner blocks recursively.
				if ( ! empty( $block['innerBlocks'] ) ) {
					$should_replace_content = self::_should_replace_inner_blocks( $block['innerBlocks'], $default_blocks );
					if ( ! $should_replace_content ) {
						break;
					}
				}
			}
		} else {
			// Handle legacy shortcode content.
			$default_shortcodes = [
				'et_pb_section',
				'et_pb_row',
				'et_pb_column',
				'et_pb_text',
				'woocommerce_cart',
				'woocommerce_checkout',
			];

			// Get all shortcodes on the page.
			preg_match_all( '@\[([^<>&/\[\]\x00-\x20=]++)@', $content, $matches );

			$matched_shortcodes = $matches[1];

			foreach ( $matched_shortcodes as $shortcode ) {
				// If a shortcode exists that is not a default shortcode, don't replace content.
				if ( ! in_array( $shortcode, $default_shortcodes, true ) ) {
					$should_replace_content = false;
					break;
				}
			}
		}

		return $should_replace_content;
	}

	/**
	 * Recursively checks inner blocks to determine if content should be replaced.
	 *
	 * @since ??
	 *
	 * @param array $inner_blocks Array of inner blocks to check.
	 * @param array $default_blocks Array of default block names.
	 *
	 * @return bool True if all inner blocks are default blocks, false otherwise.
	 */
	private static function _should_replace_inner_blocks( array $inner_blocks, array $default_blocks ): bool {
		foreach ( $inner_blocks as $inner_block ) {
			// Skip empty blocks.
			if ( empty( $inner_block['blockName'] ) ) {
				continue;
			}

			// If an inner block exists that is not a default block, don't replace content.
			if ( ! in_array( $inner_block['blockName'], $default_blocks, true ) ) {
				return false;
			}

			// Check nested inner blocks recursively.
			if ( ! empty( $inner_block['innerBlocks'] ) ) {
				if ( ! self::_should_replace_inner_blocks( $inner_block['innerBlocks'], $default_blocks ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Load WooCommerce related scripts. This function basically redoes what
	 * `WC_Frontend_Scripts::load_scripts()` does without the `product` CPT limitation.
	 *
	 * Once more WooCommerce Modules are added (checkout, account, etc), revisit this method and
	 * compare it against `WC_Frontend_Scripts::load_scripts()`. Some of the script queues are
	 * removed here because there is currently no WooCommerce module equivalent of them.
	 *
	 * Legacy function: et_builder_wc_load_scripts().
	 *
	 * @since ??
	 */
	public static function load_scripts() {
		// Bail early for VB Top Window.
		if ( Conditions::is_vb_top_window() ) {
			return;
		}

		global $woocommerce;

		$is_shop     = function_exists( 'is_shop' ) && is_shop();
		$is_checkout = function_exists( 'is_checkout' ) && is_checkout();

		/*
		 * The `is_product_taxonomy()` function doesn't return `true` for product category and tag
		 * archives, so we need to check for them explicitly. This ensures that logic that
		 * relies on identifying these pages works correctly.
		 */
		$is_product_category = function_exists( 'is_product_category' ) && is_product_category();
		$is_product_tag      = function_exists( 'is_product_tag' ) && is_product_tag();

		// If the current page is not non-`product` CPT which using builder, stop early.
		if (
			! WooCommerceUtils::is_non_product_post_type()
			&& ! et_core_is_fb_enabled()
			&& ! $is_shop
			&& ! $is_checkout
			&& ! $is_product_category
			&& ! $is_product_tag
		) {
			return;
		}

		// Simply enqueue the scripts; All of them have been registered.
		if ( 'yes' === get_option( 'woocommerce_enable_ajax_add_to_cart' ) ) {
			wp_enqueue_script( 'wc-add-to-cart' );
		}

		if ( current_theme_supports( 'wc-product-gallery-zoom' ) ) {
			if ( version_compare( $woocommerce->version, '10.3.0', '>=' ) ) {
				// WooCommerce 10.3.0+ uses wc-zoom as the new handle name.
				wp_enqueue_script( 'wc-zoom' );
			} else {
				// For WooCommerce < 10.3.0, continue using zoom.
				wp_enqueue_script( 'zoom' );
			}
		}

		if ( current_theme_supports( 'wc-product-gallery-slider' ) ) {
			if ( version_compare( $woocommerce->version, '10.3.0', '>=' ) ) {
				// WooCommerce 10.3.0+ uses wc-flexslider as the new handle name.
				wp_enqueue_script( 'wc-flexslider' );
			} else {
				// For WooCommerce < 10.3.0, continue using flexslider.
				wp_enqueue_script( 'flexslider' );
			}
		}

		if ( current_theme_supports( 'wc-product-gallery-lightbox' ) && ! et_core_is_fb_enabled() ) {
			if ( version_compare( $woocommerce->version, '10.3.0', '>=' ) ) {
				// WooCommerce 10.3.0+ uses wc-photoswipe-ui-default as the new handle name.
				wp_enqueue_script( 'wc-photoswipe-ui-default' );
			} else {
				// For WooCommerce < 10.3.0, continue using photoswipe-ui-default.
				wp_enqueue_script( 'photoswipe-ui-default' );
			}

			wp_enqueue_style( 'photoswipe-default-skin' );

			add_action( 'wp_footer', 'woocommerce_photoswipe' );
		}

		wp_enqueue_script( 'wc-single-product' );

		if ( 'geolocation_ajax' === get_option( 'woocommerce_default_customer_address' ) ) {
			$ua = strtolower( wc_get_user_agent() ); // Exclude common bots from geolocation by user agent.

			if ( ! strstr( $ua, 'bot' ) && ! strstr( $ua, 'spider' ) && ! strstr( $ua, 'crawl' ) ) {
				wp_enqueue_script( 'wc-geolocation' );
			}
		}

		wp_enqueue_script( 'woocommerce' );
		wp_enqueue_script( 'wc-cart-fragments' );
		wp_enqueue_script( 'wc-checkout' );

		if ( version_compare( $woocommerce->version, '10.3.0', '>=' ) ) {
			// WooCommerce 10.3.0+ uses wc-select2 as the new handle name.
			wp_enqueue_script( 'wc-select2' );
		} else {
			// For WooCommerce < 10.3.0, continue using select2.
			wp_enqueue_script( 'select2' );
		}

		wp_enqueue_script( 'selectWoo' );
		wp_enqueue_style( 'select2' );

		// Enqueue style. WC_Frontend_Scripts::get_styles() may not be available in all
		// environments (e.g. when WooCommerce frontend includes are not loaded), so guard
		// the call explicitly rather than relying on the upstream page-type gate above.
		if ( ! class_exists( 'WC_Frontend_Scripts' ) ) {
			return;
		}

		$wc_styles = WC_Frontend_Scripts::get_styles();

		/*
		 * The `woocommerce_enqueue_styles` filter expects an array, but an invalid value could be
		 * passed, causing an error. To prevent this, we ensure `$wc_styles` is an array before
		 * proceeding.
		 *
		 * @see https://github.com/elegantthemes/divi-builder/issues/1268
		 */
		if ( ! is_array( $wc_styles ) ) {
			return;
		}

		foreach ( $wc_styles as $style_handle => $wc_style ) {
			if ( ! isset( $wc_style['has_rtl'] ) ) {
				$wc_style['has_rtl'] = false;
			}

			wp_enqueue_style( $style_handle, $wc_style['src'], $wc_style['deps'], $wc_style['version'], $wc_style['media'] );
		}
	}

	/**
	 * Determines if current page is WooCommerce's shop page + uses builder.
	 *
	 * Based on D4's `et_builder_used_in_wc_shop` function but simplified for D5.
	 * This method checks if we're on a shop page that uses the Divi builder.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_builder_used_in_wc_shop(): bool {
		static $cached_result = null;

		// If the result is already cached, return it.
		if ( null !== $cached_result ) {
			return $cached_result;
		}

		// Bail early if WooCommerce functions aren't available.
		if ( ! function_exists( 'is_shop' ) || ! function_exists( 'wc_get_page_id' ) ) {
			$cached_result = false;
			return $cached_result;
		}

		// Check if we're on a shop page.
		if ( ! is_shop() ) {
			$cached_result = false;
			return $cached_result;
		}

		// Get the shop page ID and check if builder is used.
		$shop_page_id = wc_get_page_id( 'shop' );
		if ( $shop_page_id <= 0 ) {
			$cached_result = false;
			return $cached_result;
		}

		// Check if the shop page uses the builder.
		$cached_result = et_pb_is_pagebuilder_used( $shop_page_id );

		return $cached_result;
	}

	/**
	 * Add WooCommerce body class name on non `product` CPT builder page
	 *
	 * Based on the legacy `et_builder_wc_add_body_class` function.
	 *
	 * @since ??
	 *
	 * @param array $classes CSS class names.
	 *
	 * @return array
	 */
	public static function add_body_class( array $classes ): array {
		if ( WooCommerceUtils::is_non_product_post_type() || is_et_pb_preview() ) {
			$classes[] = 'woocommerce';
			$classes[] = 'woocommerce-page';
		}

		// This matches D4 behavior and prevents legacy CSS rules from overriding WooCommerce.
		if ( self::is_builder_used_in_wc_shop() ) {
			$classes = array_diff( $classes, [ 'woocommerce-page' ] );
		}

		return $classes;
	}

	/**
	 * Add product class name on inner content wrapper page on non `product` CPT builder page with woocommerce modules
	 * And on Product posts.
	 *
	 * Based on legacy `et_builder_wc_add_inner_content_class` function.
	 *
	 * @since ??
	 *
	 * @param array $classes Product class names.
	 *
	 * @return array
	 */
	public static function add_inner_content_class( array $classes ): array {
		// The class is required on any post with woocommerce modules and on product pages.
		if ( WooCommerceUtils::is_non_product_post_type() || is_product() || is_et_pb_preview() ) {
			$classes[] = 'product';
		}

		return $classes;
	}

	/**
	 * Add WooCommerce class names on Divi Shop Page (not WooCommerce Shop).
	 *
	 * Based on legacy `et_builder_wc_add_outer_content_class` function.
	 *
	 * @since ??
	 *
	 * @param array $classes Array of Classes.
	 *
	 * @return array
	 */
	public static function add_outer_content_class( array $classes ): array {
		// Bail early if not on the WooCommerce shop page or if the shop page is not built using Divi.
		if ( ! ( function_exists( 'is_shop' ) && is_shop() && WooCommerceUtils::is_non_product_post_type() ) ) {
			return $classes;
		}

		// Get body classes once and ensure it's an array.
		$body_classes = get_body_class();
		if ( ! is_array( $body_classes ) ) {
			return $classes;
		}

		// Check if both required WooCommerce classes are already present.
		$woocommerce_classes = [ 'woocommerce', 'woocommerce-page' ];
		if ( array_intersect( $woocommerce_classes, $body_classes ) === $woocommerce_classes ) {
			return $classes;
		}

		// Append WooCommerce classes to the array.
		$classes = array_merge( $classes, $woocommerce_classes );

		return $classes;
	}

	/**
	 * Adds the Preview class to the wrapper.
	 *
	 * Based on legacy `et_builder_wc_add_preview_wrap_class` function.
	 *
	 * @since ??
	 *
	 * @param string $maybe_class_string Classnames string.
	 *
	 * @return string
	 */
	public static function add_preview_wrap_class( string $maybe_class_string ): string {
		// Sanity Check.
		if ( ! is_string( $maybe_class_string ) ) {
			return $maybe_class_string;
		}

		$classes   = explode( ' ', $maybe_class_string );
		$classes[] = 'product';

		return implode( ' ', $classes );
	}

	/**
	 * Stop WooCommerce from redirecting Checkout page to Cart when the cart is empty.
	 *
	 * Divi Builder stops redirection only for logged-in admins.
	 *
	 * Legacy function: et_builder_wc_template_redirect().
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function template_redirect(): void {
		$checkout_page_id = wc_get_page_id( 'checkout' );

		// Get the current page ID to properly compare with checkout page ID.
		global $post;
		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}

		$is_checkout_page = $post->ID === $checkout_page_id;

		if ( ! $is_checkout_page ) {
			return;
		}

		if ( ! et_core_is_fb_enabled() ) {
			return;
		}

		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$is_builder_content = has_block( 'divi/section', $post->post_content ) || has_shortcode( $post->post_content, 'et_pb_section' );

		if ( ! $is_builder_content ) {
			return;
		}

		add_filter( 'woocommerce_checkout_redirect_empty_cart', '__return_false' );
	}

	/**
	 * Disable all default WooCommerce single layout hooks.
	 *
	 * @since ??
	 */
	public static function disable_default_layout() {
		// To remove a hook, the $function_to_remove and $priority arguments must match
		// with which the hook was added.
		remove_action(
			'woocommerce_before_main_content',
			'woocommerce_breadcrumb',
			20
		);

		remove_action(
			'woocommerce_before_single_product_summary',
			'woocommerce_show_product_sale_flash',
			10
		);
		remove_action(
			'woocommerce_before_single_product_summary',
			'woocommerce_show_product_images',
			20
		);
		remove_action(
			'woocommerce_single_product_summary',
			'woocommerce_template_single_title',
			5
		);
		remove_action(
			'woocommerce_single_product_summary',
			'woocommerce_template_single_rating',
			10
		);
		remove_action(
			'woocommerce_single_product_summary',
			'woocommerce_template_single_price',
			10
		);
		remove_action(
			'woocommerce_single_product_summary',
			'woocommerce_template_single_excerpt',
			20
		);
		remove_action(
			'woocommerce_single_product_summary',
			'woocommerce_template_single_add_to_cart',
			30
		);
		remove_action(
			'woocommerce_single_product_summary',
			'woocommerce_template_single_meta',
			40
		);
		remove_action(
			'woocommerce_single_product_summary',
			'woocommerce_template_single_sharing',
			50
		);
		remove_action(
			'woocommerce_after_single_product_summary',
			'woocommerce_output_product_data_tabs',
			10
		);
		remove_action(
			'woocommerce_after_single_product_summary',
			'woocommerce_upsell_display',
			15
		);
		remove_action(
			'woocommerce_after_single_product_summary',
			'woocommerce_output_related_products',
			20
		);
	}

	/**
	 * Deletes PRODUCT_PAGE_CONTENT_STATUS_META_KEY when Builder is OFF.
	 *
	 * The deletion allows switching between Divi Builder and the GB builder smoothly.
	 *
	 * Legacy function: et_builder_wc_delete_post_meta()
	 *
	 * @link https://github.com/elegantthemes/Divi/issues/22477
	 *
	 * @since ??
	 *
	 * @param WP_Post $post Post Object.
	 *
	 * @return void
	 */
	public static function delete_post_meta( $post ): void {
		if ( ! ( $post instanceof \WP_Post ) ) {
			return;
		}

		if ( et_pb_is_pagebuilder_used( $post->ID ) ) {
			return;
		}

			delete_post_meta( $post->ID, self::get_product_page_content_status_meta_key() );
	}

	/**
	 * Invalidate product description cache.
	 *
	 * The product description is rendered in Divi modules using the `et_builder_wc_description` filter,
	 * which caches the result. This function invalidates both short and long description caches for a
	 * product when it's updated. This is to ensure that the product description is always up to date
	 * in Divi modules that display product descriptions.
	 *
	 * @since ??
	 *
	 * @param WP_Post|int $post Post Object or Post ID.
	 *
	 * @return void
	 */
	public static function invalidate_product_description_caches( $post ): void {
		$post_id = $post instanceof WP_Post ? $post->ID : $post;

		if ( ! $post_id ) {
			return;
		}

		// Invalidate both short and long description caches.
		foreach ( [ 'short_description', 'description' ] as $desc_type ) {
			$cache_key = 'divi_wc_product_desc_' . md5( $post_id . '_' . $desc_type );
			delete_transient( $cache_key );
		}
	}

	/**
	 * Invalidate breadcrumb caches.
	 *
	 * The breadcrumb HTML is cached using transients for performance. This function invalidates
	 * all breadcrumb caches when a product is updated. This is necessary because breadcrumbs
	 * often include category hierarchies that might be affected by changes to any product.
	 *
	 * @since ??
	 *
	 * @param WP_Post $post Post Object.
	 *
	 * @return void
	 */
	public static function invalidate_breadcrumb_caches( $post ): void {
		if ( ! ( $post instanceof \WP_Post ) ) {
			return;
		}

		// We can't know all possible cache keys since they depend on various arguments,
		// so we'll use a wildcard pattern to delete all transients that might contain
		// breadcrumb HTML.
		global $wpdb;

		// Get the option name prefix for transients.
		$prefix = '_transient_divi_wc_breadcrumb_';

		// Delete all breadcrumb transients - this is a broader approach
		// but ensures all breadcrumb caches are refreshed when any product changes
		// since breadcrumbs often include category hierarchies that might be affected
		// by changes to other products.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->options WHERE option_name LIKE %s",
				$prefix . '%'
			)
		);
	}

	/**
	 * Gets the prefilled Cart Page content built using Divi Woo Modules.
	 *
	 * This method returns pre-converted Gutenberg block content for cart pages.
	 * The content is pre-converted from shortcode format to avoid runtime conversion costs
	 * and follows the same pattern as legacy et_builder_wc_get_prefilled_cart_page_content().
	 *
	 * Legacy function: et_builder_wc_get_prefilled_cart_page_content().
	 *
	 * @since ??
	 *
	 * @return string The prefilled content for cart pages.
	 */
	public static function get_prefilled_cart_page_content(): string {
		// Gets Parent theme's info in case child theme is used.
		$page_title_block = '';
		if ( 'Extra' !== et_core_get_theme_info( 'Name' ) ) {
			$page_title_block = '<!-- wp:divi/post-title {"meta":{"advanced":{"showMeta":{"desktop":{"value":"off"}}}},"image":{"advanced":{"enabled":{"desktop":{"value":"off"}}}}} --><!-- /wp:divi/post-title -->';
		}

		// Pre-converted content from shortcode to Gutenberg block format.
		// This content was generated by running Conversion::maybeConvertContent on the shortcode content
		// from legacy et_builder_wc_get_prefilled_cart_page_content function.
		return '<!-- wp:divi/placeholder --><!-- wp:divi/section {"module":{"decoration":{"layout":{"desktop":{"value":{"display":"block"}}}}}} --><!-- wp:divi/row {"module":{"decoration":{"layout":{"desktop":{"value":{"display":"block"}}}}}} --><!-- wp:divi/column {"module":{"advanced":{"type":{"desktop":{"value":"4_4"}}},"decoration":{"layout":{"desktop":{"value":{"display":"block"}}}}}} -->' . $page_title_block . '<!-- wp:divi/woocommerce-cart-notice {"content":{"advanced":{"pageType":{"desktop":{"value":"cart"}}}}} --><!-- /wp:divi/woocommerce-cart-notice --><!-- wp:divi/woocommerce-cart-products  --><!-- /wp:divi/woocommerce-cart-products --><!-- /wp:divi/column --><!-- /wp:divi/row --><!-- wp:divi/row {"module":{"advanced":{"columnStructure":{"desktop":{"value":"1_2,1_2"}}},"decoration":{"layout":{"desktop":{"value":{"display":"block"}}}}}} --><!-- wp:divi/column {"module":{"advanced":{"type":{"desktop":{"value":"1_2"}}},"decoration":{"layout":{"desktop":{"value":{"display":"block"}}}}}} --><!-- wp:divi/woocommerce-cross-sells  --><!-- /wp:divi/woocommerce-cross-sells --><!-- /wp:divi/column --><!-- wp:divi/column {"module":{"advanced":{"type":{"desktop":{"value":"1_2"}}},"decoration":{"layout":{"desktop":{"value":{"display":"block"}}}}}} --><!-- wp:divi/woocommerce-cart-totals  --><!-- /wp:divi/woocommerce-cart-totals --><!-- /wp:divi/column --><!-- /wp:divi/row --><!-- /wp:divi/section --><!-- /wp:divi/placeholder -->';
	}

	/**
	 * Gets the prefilled Checkout Page content built using Divi Woo Modules.
	 *
	 * This method returns pre-converted Gutenberg block content for checkout pages.
	 * The content is pre-converted from shortcode format to avoid runtime conversion costs
	 * and follows the same pattern as legacy et_builder_wc_get_prefilled_checkout_page_content().
	 *
	 * Legacy function: et_builder_wc_get_prefilled_checkout_page_content().
	 *
	 * @since ??
	 *
	 * @return string The prefilled content for checkout pages.
	 */
	public static function get_prefilled_checkout_page_content(): string {
		// Gets Parent theme's info in case child theme is used.
		$page_title_block = '';
		if ( 'Extra' !== et_core_get_theme_info( 'Name' ) ) {
			$page_title_block = '<!-- wp:divi/post-title {"meta":{"advanced":{"showMeta":{"desktop":{"value":"off"}}}},"image":{"advanced":{"enabled":{"desktop":{"value":"off"}}}}} --><!-- /wp:divi/post-title -->';
		}

		// Pre-converted content from shortcode to Gutenberg block format.
		// This content was generated by running Conversion::maybeConvertContent on the shortcode content
		// from legacy et_builder_wc_get_prefilled_checkout_page_content function.
		return '<!-- wp:divi/placeholder --><!-- wp:divi/section {"module":{"decoration":{"layout":{"desktop":{"value":{"display":"block"}}}}}} --><!-- wp:divi/row {"module":{"decoration":{"spacing":{"desktop":{"value":{"padding":{"top":"","right":"","bottom":"0%","left":"","syncVertical":"off","syncHorizontal":"off"}}}},"layout":{"desktop":{"value":{"display":"block"}}}}}} --><!-- wp:divi/column {"module":{"advanced":{"type":{"desktop":{"value":"4_4"}}},"decoration":{"layout":{"desktop":{"value":{"display":"block"}}}}}} -->' . $page_title_block . '<!-- wp:divi/woocommerce-cart-notice {"content":{"advanced":{"pageType":{"desktop":{"value":"checkout"}}}}} --><!-- /wp:divi/woocommerce-cart-notice --><!-- /wp:divi/column --><!-- /wp:divi/row --><!-- wp:divi/row {"module":{"advanced":{"columnStructure":{"desktop":{"value":"1_2,1_2"}}},"decoration":{"layout":{"desktop":{"value":{"display":"block"}}}}}} --><!-- wp:divi/column {"module":{"advanced":{"type":{"desktop":{"value":"1_2"}}},"decoration":{"layout":{"desktop":{"value":{"display":"block"}}}}}} --><!-- wp:divi/woocommerce-checkout-billing  --><!-- /wp:divi/woocommerce-checkout-billing --><!-- /wp:divi/column --><!-- wp:divi/column {"module":{"advanced":{"type":{"desktop":{"value":"1_2"}}},"decoration":{"layout":{"desktop":{"value":{"display":"block"}}}}}} --><!-- wp:divi/woocommerce-checkout-shipping  --><!-- /wp:divi/woocommerce-checkout-shipping --><!-- wp:divi/woocommerce-checkout-additional-info  --><!-- /wp:divi/woocommerce-checkout-additional-info --><!-- /wp:divi/column --><!-- /wp:divi/row --><!-- wp:divi/row {"module":{"decoration":{"layout":{"desktop":{"value":{"display":"block"}}}}}} --><!-- wp:divi/column {"module":{"advanced":{"type":{"desktop":{"value":"4_4"}}},"decoration":{"layout":{"desktop":{"value":{"display":"block"}}}}}} --><!-- wp:divi/woocommerce-checkout-order-details  --><!-- /wp:divi/woocommerce-checkout-order-details --><!-- wp:divi/woocommerce-checkout-payment-info  --><!-- /wp:divi/woocommerce-checkout-payment-info --><!-- /wp:divi/column --><!-- /wp:divi/row --><!-- /wp:divi/section --><!-- /wp:divi/placeholder -->';
	}

	/**
	 * Gets the pre-built layout for WooCommerce product pages.
	 *
	 * This method returns a string containing the prefilled content for product pages.
	 * It includes a default layout with common product modules and applies a filter
	 * to allow customization.
	 *
	 * The content is pre-converted from shortcode format to Gutenberg block format
	 *  to avoid calling Conversion::maybeConvertContent every time this method is called.
	 *  The function also handles existing shortcode content by converting it and
	 *  appending it, as well as existing block content by appending it directly.
	 *
	 * Legacy function: et_builder_wc_get_prefilled_product_page_content().
	 *
	 * @since ??
	 *
	 * @param array $args Additional args.
	 * @return string The prefilled content for product pages.
	 */
	public static function get_prefilled_product_page_content( array $args = [] ): string {
		/**
		 * Filters the Top section Background in the default WooCommerce Modules layout.
		 *
		 * @since ??
		 *
		 * @param string $color Default empty.
		 */
		$et_builder_wc_initial_top_section_bg = apply_filters( 'et_builder_wc_initial_top_section_bg', '' );

		// Pre-converted content from shortcode to Gutenberg block format.
		// This content was generated by running Conversion::maybeConvertContent on the shortcode content
		// from legacy et_builder_wc_get_prefilled_product_page_content function.
		$content  = '<!-- wp:divi/placeholder -->';
		$content .= '<!-- wp:divi/section {"module":{"decoration":{"spacing":{"desktop":{"value":{"padding":{"top":"0px","right":"","bottom":"","left":"","syncVertical":"off","syncHorizontal":"off"}}}},"background":{"desktop":{"value":{"color":"' . esc_attr( $et_builder_wc_initial_top_section_bg ) . '"}}},"layout":{"desktop":{"value":{"display":"block"}}}}}} --><!-- wp:divi/row {"module":{"decoration":{"sizing":{"desktop":{"value":{"width":"100%"}}},"spacing":{"desktop":{"value":{"padding":{"top":"0px","right":"","bottom":"0px","left":"","syncVertical":"off","syncHorizontal":"off"}}}},"layout":{"desktop":{"value":{"display":"block"}}}}}} --><!-- wp:divi/column {"module":{"advanced":{"type":{"desktop":{"value":"4_4"}}},"decoration":{"layout":{"desktop":{"value":{"display":"block"}}}}}} --><!-- wp:divi/woocommerce-breadcrumb  --><!-- /wp:divi/woocommerce-breadcrumb --><!-- wp:divi/woocommerce-cart-notice  --><!-- /wp:divi/woocommerce-cart-notice --><!-- /wp:divi/column --><!-- /wp:divi/row --><!-- wp:divi/row {"module":{"decoration":{"spacing":{"desktop":{"value":{"padding":{"top":"0px","right":"","bottom":"","left":"","syncVertical":"off","syncHorizontal":"off"}}}},"sizing":{"desktop":{"value":{"width":"100%"}}},"layout":{"desktop":{"value":{"display":"block"}}}}}} --><!-- wp:divi/column {"module":{"advanced":{"type":{"desktop":{"value":"1_2"}}},"decoration":{"layout":{"desktop":{"value":{"display":"block"}}}}}} --><!-- wp:divi/woocommerce-product-images  --><!-- /wp:divi/woocommerce-product-images --><!-- /wp:divi/column --><!-- wp:divi/column {"module":{"advanced":{"type":{"desktop":{"value":"1_2"}}},"decoration":{"layout":{"desktop":{"value":{"display":"block"}}}}}} --><!-- wp:divi/woocommerce-product-title  --><!-- /wp:divi/woocommerce-product-title --><!-- wp:divi/woocommerce-product-rating  --><!-- /wp:divi/woocommerce-product-rating --><!-- wp:divi/woocommerce-product-price  --><!-- /wp:divi/woocommerce-product-price --><!-- wp:divi/woocommerce-product-description  --><!-- /wp:divi/woocommerce-product-description --><!-- wp:divi/woocommerce-product-add-to-cart  --><!-- /wp:divi/woocommerce-product-add-to-cart --><!-- wp:divi/woocommerce-product-meta  --><!-- /wp:divi/woocommerce-product-meta --><!-- /wp:divi/column --><!-- /wp:divi/row --><!-- wp:divi/row {"module":{"decoration":{"sizing":{"desktop":{"value":{"width":"100%"}}},"layout":{"desktop":{"value":{"display":"block"}}}}}} --><!-- wp:divi/column {"module":{"advanced":{"type":{"desktop":{"value":"4_4"}}},"decoration":{"layout":{"desktop":{"value":{"display":"block"}}}}}} --><!-- wp:divi/woocommerce-product-tabs {"content":{"desktop":{"value":"\n\t\t\t\t\t"}}} --><!-- /wp:divi/woocommerce-product-tabs --><!-- wp:divi/woocommerce-product-upsell {"content":{"advanced":{"columnsNumber":{"desktop":{"value":"3"}}}}} --><!-- /wp:divi/woocommerce-product-upsell --><!-- wp:divi/woocommerce-related-products {"content":{"advanced":{"columnsNumber":{"desktop":{"value":"3"}}}}} --><!-- /wp:divi/woocommerce-related-products --><!-- /wp:divi/column --><!-- /wp:divi/row --><!-- /wp:divi/section -->';

		if ( ! empty( $args['existing_content'] ) ) {
			// If there's existing shortcode content, append it.
			$content .= $args['existing_content'];
		}

		$content .= '<!-- /wp:divi/placeholder -->';

		// Maybe convert legacy content.
		$content = Conversion::maybeConvertContent( $content );

		/**
		 * Filters the prefilled content for product pages.
		 *
		 * @since ??
		 *
		 * @param string $content Prefilled content for product pages.
		 * @param array  $args    Additional args.
		 */
		return apply_filters( 'divi_woocommerce_prefilled_product_page_content', $content, $args );
	}

	/**
	 * Gets the Product layout for a given Post ID.
	 *
	 * Based on legacy function: et_builder_wc_get_product_layout().
	 *
	 * @since ??
	 *
	 * @param int $post_id Post Id.
	 *
	 * @return string The return value will be one of the values from
	 *                {@see WooCommerceUtils::get_page_layouts()} when the Post ID is valid.
	 *                Empty string otherwise.
	 */
	public static function get_product_layout( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return false;
		}

		return get_post_meta( $post_id, self::get_product_page_layout_meta_key(), true );
	}

	/**
	 * Overrides the default WooCommerce layout.
	 *
	 * This method customizes the WooCommerce product page layout by checking various conditions,
	 * such as the current post type, layout configurations, and supported themes. It disables
	 * the default WooCommerce layout and registers custom layout logic, ensuring compatibility
	 * with Divi and Extra themes.
	 *
	 * @see woocommerce/includes/wc-template-functions.php
	 *
	 * @since ??
	 */
	public static function override_default_layout() {
		// Bail if the current page is not a single product page.
		if ( ! is_singular( 'product' ) ) {
			return;
		}

		// The `global $post` variable is required here as it's not available during `after_setup_theme`.
		global $post;

		// Bail if the current page is not using the page builder.
		if ( ! et_pb_is_pagebuilder_used( $post->ID ) ) {
			return;
		}

		// Get the product page layout setting for this page and the content's modification status.
		$product_page_layout                 = WooCommerceUtils::get_product_layout( $post->ID );
		$is_product_content_modified         = 'modified' === get_post_meta( $post->ID, self::get_product_page_content_status_meta_key(), true );
		$is_preview_loading                  = is_preview();

		/*
		 * Bail if the layout is set to "build from scratch" (`et_build_from_scratch`),
		 * but the product content hasn't been modified yet and it's not in preview mode.
		 */
		if ( 'et_build_from_scratch' === $product_page_layout && ! $is_product_content_modified && ! $is_preview_loading ) {
			return;
		}

		/*
		 * Bail if:
		 * 1. No specific product page layout is configured, and the front-end builder is not enabled.
		 * 2. A specific layout is configured, but it's not "build from scratch".
		 */
		if (
			( ! $product_page_layout && ! et_core_is_fb_enabled() ) ||
			( $product_page_layout && 'et_build_from_scratch' !== $product_page_layout )
		) {
			return;
		}

		/*
		 * If the active theme is not Divi or Extra, enforce WooCommerce's default templates.
		 * This ensures compatibility with themes that may use custom templates (e.g., child themes or DBP).
		 */
		if ( ! in_array( wp_get_theme()->get( 'Name' ), [ 'Divi', 'Extra' ], true ) ) {
			// Override the WooCommerce template part logic using a custom filter.
			add_filter( 'wc_get_template_part', [ self::class, 'override_template_part' ], 10, 3 );
		}

		// Disable all default WooCommerce layout hooks for single product pages.
		self::disable_default_layout();

		// When Theme Builder is active, suppress the original product content to prevent it from.
		// appearing above the Theme Builder template. This is especially important when.
		// WooCommerce modules are used, as they trigger special handling that would otherwise.
		// cause both the original content and the Theme Builder template to render.
		add_filter( 'the_content', [ self::class, 'suppress_product_content_for_theme_builder' ], 5 );

		// Trigger an action hook to notify that custom Divi layout registration is about to occur.
		/**
		 * Fires before custom Divi WooCommerce layout registration occurs.
		 *
		 * @since ??
		 */
		do_action( 'et_builder_wc_product_before_render_layout_registration' );

		/**
		 * Fires before custom Divi WooCommerce layout registration occurs.
		 *
		 * @since ??
		 */
		do_action( 'divi_woocommerce_product_before_render_layout_registration' );

		// Remove the legacy function that renders content on the single product page.
		remove_action( 'woocommerce_after_single_product_summary', 'et_builder_wc_product_render_layout', 5 );

		// Add the updated function to render the content on the single product page.
		add_action( 'woocommerce_after_single_product_summary', [ self::class, 'product_render_layout' ], 5 );
	}

	/**
	 * Force WooCommerce to load default template over theme's custom template when builder's
	 * et_builder_from_scratch is used to prevent unexpected custom layout which makes builder
	 * experience inconsistent
	 *
	 * @since ??
	 *
	 * @param string $template  Path to template file.
	 * @param string $slug      Template slug.
	 * @param string $name      Template name.
	 *
	 * @return string
	 */
	public static function override_template_part( string $template, string $slug, string $name ): string {
		// Only force load default `content-single-product.php` template.
		$is_content_single_product = 'content' === $slug && 'single-product' === $name;

		return $is_content_single_product ? WC()->plugin_path() . "/templates/{$slug}-{$name}.php" : $template;
	}

	/**
	 * Suppresses the original product content when Theme Builder is active to prevent it from
	 * appearing above the Theme Builder template.
	 *
	 * This filter is specifically applied when the override_default_layout method runs,
	 * ensuring that Theme Builder templates completely replace the product content rather
	 * than appearing alongside it.
	 *
	 * @since ??
	 *
	 * @param string $content The original post content.
	 *
	 * @return string Empty string to suppress the content, or original content if not applicable.
	 */
	public static function suppress_product_content_for_theme_builder( string $content ): string {
		// Only suppress content for single product pages.
		if ( ! is_singular( 'product' ) ) {
			return $content;
		}

		// Only suppress when in the main query and main post.
		if ( ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		// Do not suppress content when visual builder is active.
		// This allows individual product pages to be edited normally in the visual builder
		// while preserving theme builder template behavior on the frontend.
		if ( et_core_is_fb_enabled() ) {
			return $content;
		}

		// Check if Theme Builder is actually overriding the content.
		$tb_layouts     = et_theme_builder_get_template_layouts();
		$tb_body_layout = ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE;

		// Safely check if Theme Builder body layout exists and is overriding.
		$tb_body_override = ! empty( $tb_layouts )
			&& isset( $tb_layouts[ $tb_body_layout ] )
			&& ! empty( $tb_layouts[ $tb_body_layout ]['override'] );

		// Only suppress content if Theme Builder is actually overriding the body layout.
		if ( ! $tb_body_override ) {
			return $content;
		}

		// Safely get the Theme Builder template content to check for WooCommerce modules and Post Content Module.
		$tb_body_layout_id          = isset( $tb_layouts[ $tb_body_layout ]['id'] ) ? $tb_layouts[ $tb_body_layout ]['id'] : 0;
		$tb_body_content            = $tb_body_layout_id ? get_post_field( 'post_content', $tb_body_layout_id ) : '';
		$has_woocommerce_module_tb  = DetectFeature::has_woocommerce_module_block( $tb_body_content );
		$has_post_content_module_tb = DetectFeature::has_post_content_module_block( $tb_body_content );

		// Only suppress content if the Theme Builder template contains WooCommerce modules.
		// This prevents suppression when Theme Builder is active but doesn't use WooCommerce modules.
		if ( ! $has_woocommerce_module_tb ) {
			return $content;
		}

		// If Post Content Module is present in the template, we need special handling:
		// - Suppress content when called from default location (to prevent duplication)
		// - Allow content when called from Post Content Module (so it can render).
		if ( $has_post_content_module_tb ) {
			// Check if the_content() is being called from within Post Content Module rendering.
			if ( et_theme_builder_is_rendering_post_content() ) {
				// Allow content to render when called from Post Content Module.
				return $content;
			}

			// Suppress content when called from default location to prevent duplication.
			return '';
		}

		// Suppress the content to prevent it from appearing above Theme Builder template.
		return '';
	}

	/**
	 * Parses and formats the WooCommerce product description for use in Divi modules.
	 *
	 * This method processes the product description by:
	 * - Stripping builder-specific shortcodes to avoid nested or duplicate rendering
	 * - Running WordPress embed and shortcode processing to convert shortcodes and embeds to HTML
	 * - Optionally running block parsing (do_blocks) for Gutenberg compatibility
	 * - Wrapping the result in `<p>` tags for proper HTML formatting
	 * - Caching the result for performance
	 *
	 * This ensures that product descriptions are rendered consistently and safely in Divi WooCommerce modules,
	 * whether the content comes from the post content, custom meta, or is dynamically generated. It is especially
	 * important for modules that display product descriptions in custom layouts, as it prevents issues with
	 * nested shortcodes, missing formatting, or unprocessed blocks.
	 *
	 * Based on legacy `et_builder_wc_parse_description` function.
	 *
	 * This function is registered on the `et_builder_wc_description` filter.
	 *
	 * Currently used by:
	 * - WooCommerce Product Description module (@see WooCommerceProductDescriptionModule::get_description) for
	 * a long description when builder is used.
	 *
	 * To be used by:
	 * - WooCommerce Tabs module (@see ET_Builder_Module_Woocommerce_Tabs).
	 * In Divi 4, the equivalent filter is applied to tab content for the product description tab.
	 * As of now, there is no direct equivalent in D5, or the filter is not yet applied in a D5 Tabs module.
	 *
	 * TODO feat(D5, WooCommerce Modules): Integrate this filter into the Divi 5 WooCommerce Product Tabs module to match Divi 4 behavior and ensure consistent description parsing. [https://github.com/elegantthemes/Divi/issues/43121]
	 *
	 * @param string|mixed $description Product description (e.g., post content, excerpt, or custom meta).
	 *
	 * @return string|mixed Parsed and formatted product description.
	 */
	public static function parse_description( $description ) {
		if ( ! is_string( $description ) ) {
			return $description;
		}

		// Use cached description if available, otherwise parse the description and cache it for future use.
		static $cache = [];
		$cache_key    = md5( $description );
		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		global $wp_embed;

		// Strip unnecessary shortcodes.
		$parsed_description = et_strip_shortcodes( $description );

		// Run shortcode.
		$parsed_description = $wp_embed->run_shortcode( $parsed_description );

		// Run do_blocks if available and log timing.
		if ( function_exists( 'has_blocks' ) && has_blocks( $parsed_description ) ) {
			$parsed_description = do_blocks( $parsed_description );
		}

		// If the shortcode framework is loaded, process shortcodes.
		if ( et_is_shortcode_framework_loaded() ) {
			$parsed_description = do_shortcode( $parsed_description );
		}

		$parsed_description  = wpautop( $parsed_description );
		$cache[ $cache_key ] = $parsed_description;

		return $parsed_description;
	}

	/**
	 * Renders the content.
	 *
	 * Rendering the content will enable Divi Builder to take over the entire
	 * post content area.
	 *
	 * @since ??
	 */
	public static function product_render_layout() {
		/**
		 * Fires before rendering WooCommerce product layout content.
		 *
		 * @since ??
		 */
		do_action( 'et_builder_wc_product_before_render_layout' );

		/**
		 * Fires before rendering WooCommerce product layout content.
		 *
		 * @since ??
		 */
		do_action( 'divi_woocommerce_product_before_render_layout' );

		// Check if Theme Builder is active with WooCommerce modules to prevent content duplication.
		// This mirrors the logic from suppress_product_content_for_theme_builder but executes
		// in the WooCommerce hook context where the_content() is called outside the main loop.
		$should_suppress_content = false;

		if ( is_singular( 'product' ) ) {
			// Check if Theme Builder body layout is overriding.
			$tb_layouts     = et_theme_builder_get_template_layouts();
			$tb_body_layout = ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE;

			$tb_body_override = ! empty( $tb_layouts )
				&& isset( $tb_layouts[ $tb_body_layout ] )
				&& ! empty( $tb_layouts[ $tb_body_layout ]['override'] );

			if ( $tb_body_override ) {
				// Check if Theme Builder template contains WooCommerce modules and Post Content Module.
				$tb_body_layout_id          = isset( $tb_layouts[ $tb_body_layout ]['id'] ) ? $tb_layouts[ $tb_body_layout ]['id'] : 0;
				$tb_body_content            = $tb_body_layout_id ? get_post_field( 'post_content', $tb_body_layout_id ) : '';
				$has_woocommerce_module_tb  = DetectFeature::has_woocommerce_module_block( $tb_body_content );
				$has_post_content_module_tb = DetectFeature::has_post_content_module_block( $tb_body_content );

				// Suppress content if WooCommerce modules exist AND Post Content Module does not exist.
				// When Post Content Module exists, it will handle rendering the_content(), so we suppress
				// the default rendering to prevent duplication.
				if ( $has_woocommerce_module_tb && ! $has_post_content_module_tb ) {
					$should_suppress_content = true;
				} elseif ( $has_woocommerce_module_tb && $has_post_content_module_tb ) {
					// Post Content Module exists, so suppress default content rendering to prevent duplication.
					// The Post Content Module will render the content via et_theme_builder_frontend_render_post_content().
					$should_suppress_content = true;
				}
			}
		}

		// Only render the_content() if Theme Builder suppression conditions are not met.
		if ( ! $should_suppress_content ) {
			the_content();
		}

		/**
		 * Fires after rendering WooCommerce product layout content.
		 *
		 * @since ??
		 */
		do_action( 'et_builder_wc_product_after_render_layout' );

		/**
		 * Fires after rendering WooCommerce product layout content.
		 *
		 * @since ??
		 */
		do_action( 'divi_woocommerce_product_after_render_layout' );
	}

	/**
	 * Renders Terms & Conditions page content with Theme Builder template when applicable.
	 *
	 * Hooked into 'woocommerce_checkout_terms_and_conditions' action to intercept
	 * WooCommerce's Terms & Conditions page content loading and render Theme Builder
	 * template content instead of raw page content when Theme Builder template exists.
	 *
	 * WooCommerce's default function `wc_terms_and_conditions_page_content()` outputs
	 * raw post content directly, bypassing WordPress's normal rendering pipeline (the_content filter)
	 * and Theme Builder template system. This method intercepts that output and renders
	 * the Theme Builder body layout when applicable.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function render_terms_and_conditions_with_theme_builder_action(): void {
		// Get Terms & Conditions page ID.
		$terms_page_id = wc_get_page_id( 'terms' );

		if ( ! $terms_page_id || $terms_page_id <= 0 ) {
			return;
		}

		// Get the Terms & Conditions page post object.
		$terms_page = get_post( $terms_page_id );

		if ( ! $terms_page || 'page' !== $terms_page->post_type ) {
			return;
		}

		// Get Theme Builder layouts for the Terms & Conditions page.
		// This uses ET_Theme_Builder_Request::from_post() to create a request object
		// for the specific post ID, which et_theme_builder_get_template_layouts() can use
		// to determine which templates apply to that page.
		$tb_request = ET_Theme_Builder_Request::from_post( $terms_page_id );

		if ( ! $tb_request ) {
			return;
		}

		$tb_layouts = et_theme_builder_get_template_layouts( $tb_request );

		if ( empty( $tb_layouts ) ) {
			return;
		}

		$tb_body_layout = ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE;

		// Check if Theme Builder body layout exists and is overriding for this page.
		$tb_body_override = ! empty( $tb_layouts )
			&& isset( $tb_layouts[ $tb_body_layout ] )
			&& ! empty( $tb_layouts[ $tb_body_layout ]['override'] );

		if ( ! $tb_body_override ) {
			return;
		}

		// Get the Theme Builder body layout ID.
		$tb_body_layout_id = isset( $tb_layouts[ $tb_body_layout ]['id'] ) ? $tb_layouts[ $tb_body_layout ]['id'] : 0;

		if ( ! $tb_body_layout_id || $tb_body_layout_id <= 0 ) {
			return;
		}

		// Remove the default WooCommerce function from running at priority 30.
		// This prevents duplicate content output.
		remove_action( 'woocommerce_checkout_terms_and_conditions', 'wc_terms_and_conditions_page_content', 30 );

		// Store original post and query context for rendering.
		// We need to switch context so that Post Content modules can access the Terms & Conditions page content.
		global $post, $wp_query;

		$render_original_post               = $post;
		$render_original_query_post         = isset( $wp_query->post ) ? $wp_query->post : null;
		$render_original_query_post_was_set = isset( $wp_query->post );

		// Temporarily set $wp_query->post to Terms & Conditions page for rendering.
		// This ensures ET_Post_Stack::get_main_post() returns the Terms & Conditions page,
		// which Post Content modules in Theme Builder templates use.
		$wp_query->post = $terms_page;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Necessary for Theme Builder context.
		$post = $terms_page;
		setup_postdata( $terms_page );

		// Set Terms & Conditions page in the post stack as well.
		ET_Post_Stack::replace( $terms_page );

		// Render the Theme Builder body layout wrapped in WooCommerce's expected HTML structure.
		try {
			// Output WooCommerce's expected wrapper div.
			echo '<div class="woocommerce-terms-and-conditions" style="display: none; max-height: 200px; overflow: auto;">';

			// Render the Theme Builder body layout.
			// This will render the template structure (sections, rows, modules) that wraps the page content.
			// Post Content modules inside will use ET_Post_Stack::get_main_post() which now returns
			// the Terms & Conditions page (via $wp_query->post).
			Layout::render( $tb_body_layout, $tb_body_layout_id );

			echo '</div>';
		} catch ( Exception $e ) {
			// If rendering fails, let WooCommerce handle it normally.
			// Restore original query and post context.
			if ( $render_original_query_post_was_set ) {
				$wp_query->post = $render_original_query_post;
			} else {
				unset( $wp_query->post );
			}
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring original state.
			$post = $render_original_post;
			if ( $render_original_post ) {
				setup_postdata( $render_original_post );
			}
			wp_reset_postdata();
			ET_Post_Stack::restore();

			// Re-add WooCommerce's default function so it can output content.
			add_action( 'woocommerce_checkout_terms_and_conditions', 'wc_terms_and_conditions_page_content', 30 );
			return;
		}

		// Restore original query and post context after rendering.
		if ( $render_original_query_post_was_set ) {
			$wp_query->post = $render_original_query_post;
		} else {
			unset( $wp_query->post );
		}
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring original state.
		$post = $render_original_post;
		if ( $render_original_post ) {
			setup_postdata( $render_original_post );
		}
		wp_reset_postdata();
		ET_Post_Stack::restore();
	}

	/**
	 * Relocates all registered callbacks from `woocommerce_single_product_summary` hook to suitable WooCommerce modules.
	 *
	 * This function is responsible for relocating the WooCommerce single product summary hooks to
	 * suitable modules. It checks if the current page is a product-related page, whether the
	 * Theme Builder is enabled, and if the WooCommerce modules are present in the content.
	 * It then copies the hooks to the appropriate modules and removes them from the original
	 * location if necessary.
	 *
	 * This function is based on legacy `et_builder_wc_relocate_single_product_summary` function.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function relocate_woocommerce_single_product_summary(): void {
		// Prevent duplicate execution when multiple hooks fire (wp + et_builder_ready).
		if ( self::$_relocation_done ) {
			return;
		}

		global $post, $wp_filter;

		// Handle VB/REST context where $post might not be set initially.
		if ( ! $post && ( Conditions::is_rest_api_request() || et_core_is_fb_enabled() ) ) {
			$post_id = DynamicAssetsUtils::get_current_post_id();
			if ( $post_id ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Necessary for VB/REST context.
				$post = get_post( $post_id );
			}
		}

		$tb_body_layout = ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE;
		$tb_layouts     = et_theme_builder_get_template_layouts();
		// Get whether TB overrides the specified layout for the current request.
		$tb_body_override          = ! empty( $tb_layouts ) && $tb_layouts[ $tb_body_layout ]['override'];
		$tb_body_layout_id         = $tb_body_override ? $tb_layouts[ $tb_body_layout ]['id'] : false;
		$tb_body_content           = $tb_body_layout_id ? get_post_field( 'post_content', $tb_body_layout_id ) : '';
		$post_id                   = $post ? $post->ID : false;
		$has_woocommerce_module    = $post ? DetectFeature::has_woocommerce_module_block( $post->post_content ) : false;
		$has_woocommerce_module_tb = DetectFeature::has_woocommerce_module_block( $tb_body_content );
		$hook                      = $wp_filter['woocommerce_single_product_summary'] ?? null;

		// Bail early if there is no `woocommerce_single_product_summary` hook callbacks or
		// if there is no WooCommerce module in the content of current page and TB body layout.
		if ( empty( $hook->callbacks ) || ( ! $has_woocommerce_module && ! $has_woocommerce_module_tb ) ) {
			return;
		}

		$is_copy_needed = false;
		$is_move_needed = false;

		// Product related pages.
		$is_product          = function_exists( 'is_product' ) && is_product();
		$is_shop             = function_exists( 'is_shop' ) && is_shop();
		$is_product_category = function_exists( 'is_product_category' ) && is_product_category();
		$is_product_tag      = function_exists( 'is_product_tag' ) && is_product_tag();

		// Copy single product summary hooks when current page is:
		// - Product related pages: single, shop, category, & tag.
		// - Theme Builder or Page Builder.
		// - Before & after components AJAX request.
		// - Has TB layouts contain WC modules.
		if (
			$is_product
			|| $is_shop
			|| $is_product_category
			|| $is_product_tag
			|| et_builder_tb_enabled()
			|| et_core_is_fb_enabled()
			|| et_fb_is_before_after_components_callback_ajax()
			|| Conditions::is_rest_api_request()
			|| WooCommerceUtils::is_non_product_post_type()
		) {
			$is_copy_needed = true;
		}

		// Move single product summary hooks when current page is single product with:
		// - Builder is used.
		// - TB Body layout overrides the content.
		if ( $is_product && ( et_pb_is_pagebuilder_used( $post_id ) || $tb_body_override ) ) {
			$is_move_needed = true;
		}

		// In VB/REST context, also move hooks if we have WooCommerce modules on a product page.
		if ( Conditions::is_rest_api_request() && $is_product && $has_woocommerce_module ) {
			$is_move_needed = true;
		}

		/**
		 * Filters whether to copy single product summary hooks output or not.
		 *
		 * 3rd-party plugins can use this filter to force enable or disable this action.
		 *
		 * @since 4.14.5
		 *
		 * @param boolean $is_copy_needed Whether to copy single product summary or not.
		 */
		$is_copy_needed = apply_filters( 'et_builder_wc_relocate_single_product_summary_is_copy_needed', $is_copy_needed );

		/**
		 * Filters whether to copy single product summary hooks output or not.
		 *
		 * 3rd-party plugins can use this filter to force enable or disable this action.
		 *
		 * @since ??
		 *
		 * @param boolean $is_copy_needed Whether to copy single product summary or not.
		 */
		$is_copy_needed = apply_filters( 'divi_woocommerce_relocate_single_product_summary_is_copy_needed', $is_copy_needed );

		/**
		 * Filters whether to move (remove the original) single product summary or not.
		 *
		 * 3rd-party plugins can use this filter to force enable or disable this action.
		 *
		 * @since 4.14.5
		 *
		 * @param boolean $is_move_needed Whether to move single product summary or not.
		 */
		$is_move_needed = apply_filters( 'et_builder_wc_relocate_single_product_summary_is_move_needed', $is_move_needed );

		/**
		 * Filters whether to move (remove the original) single product summary or not.
		 *
		 * 3rd-party plugins can use this filter to force enable or disable this action.
		 *
		 * @since ??
		 *
		 * @param boolean $is_move_needed Whether to move single product summary or not.
		 */
		$is_move_needed = apply_filters( 'divi_woocommerce_relocate_single_product_summary_is_move_needed', $is_move_needed );

		// Bail early if copy action is not needed.
		if ( ! $is_copy_needed ) {
			return;
		}

		$modules_with_relocation = [];

		/**
		 * Filters the list of ignored `woocommerce_single_product_summary` hook callbacks.
		 *
		 * 3rd-party plugins can use this filter to keep their callbacks so they won't be
		 * relocated from `woocommerce_single_product_summary` hook. The value is string of
		 * `function_name` or `class::method` combination. By default, it contanis all single
		 * product summary actions from WooCommerce plugin.
		 *
		 * @since 4.14.5
		 *
		 * @param array $ignored_callbacks List of ignored callbacks.
		 */
		$ignored_callbacks = apply_filters(
			'et_builder_wc_relocate_single_product_summary_ignored_callbacks',
			[
				'WC_Structured_Data::generate_product_data',
				'woocommerce_template_single_title',
				'woocommerce_template_single_rating',
				'woocommerce_template_single_price',
				'woocommerce_template_single_excerpt',
				'woocommerce_template_single_add_to_cart',
				'woocommerce_template_single_meta',
				'woocommerce_template_single_sharing',
			]
		);

		/**
		 * Filters the list of ignored `woocommerce_single_product_summary` hook callbacks.
		 *
		 * 3rd-party plugins can use this filter to keep their callbacks so they won't be
		 * relocated from `woocommerce_single_product_summary` hook. The value is string of
		 * `function_name` or `class::method` combination. By default, it contanis all single
		 * product summary actions from WooCommerce plugin.
		 *
		 * @since ??
		 *
		 * @param array $ignored_callbacks List of ignored callbacks.
		 */
		$ignored_callbacks = apply_filters(
			'divi_woocommerce_relocate_single_product_summary_ignored_callbacks',
			$ignored_callbacks
		);

		// Pair of WooCommerce layout priority numbers and WooCommerce module slugs.
		$modules_priority = [
			'5'  => 'divi/woocommerce-product-title',
			'10' => 'divi/woocommerce-product-price', // `divi/woocommerce-product-rating` also has the same priority.
			'20' => 'divi/woocommerce-product-description', // Description defaults to `excerpt` on WooCommerce default layout.
			'30' => 'divi/woocommerce-product-add-to-cart',
			'40' => 'divi/woocommerce-product-meta',
		];

		foreach ( $hook->callbacks as $callback_priority => $callbacks ) {
			foreach ( $callbacks as $callback_args ) {
				// 1. Generate 'callback name' (string).
				// Get the callback name stored on the `function` argument.
				$callback_function = $callback_args['function'] ?? '';
				$callback_name     = $callback_function;

				// Bail early if the callback is not callable to avoid any unexpected issue.
				if ( ! is_callable( $callback_function ) ) {
					continue;
				}

				// If the `function` is an array, it's probably a class based function.
				// We should convert it into string based callback name for validating purpose.
				if ( is_array( $callback_function ) ) {
					$callback_name   = '';
					$callback_object = $callback_function[0] ?? '';
					$callback_method = $callback_function[1] ?? '';

					// Ensure the index `0` is an object and the index `1` is string. We're going to
					// use the class::method combination as callback name.
					if ( is_object( $callback_object ) && is_string( $callback_method ) ) {
						$callback_class = get_class( $callback_object );
						$callback_name  = "{$callback_class}::{$callback_method}";
					}
				}

				// Bail early if callback name is not string or empty to avoid unexpected issues.
				if ( ! is_string( $callback_name ) || empty( $callback_name ) ) {
					continue;
				}

				// Bail early if current callback is listed on ignored callbacks list.
				if ( in_array( $callback_name, $ignored_callbacks, true ) ) {
					continue;
				}

				// 2. Generate 'module priority' to get suitable 'module slug'.
				// Find the module priority number by round down the priority to the nearest 10.
				// It's needed to get suitable WooCommerce module. For example, a callback with priority
				// 41 means we have to put it on module with priority 40 which is `et_pb_wc_meta`.
				$rounded_callback_priority = intval( floor( $callback_priority / 10 ) * 10 );
				$module_priority           = $rounded_callback_priority;

				// Additional rules for module priority:
				// - 0  : Make it 5 as default to target `et_pb_wc_title` because there is no
				// module with priority less than 5.
				// - 50 : Make it 40 as default to target `et_pb_wc_meta` because there is no
				// module with priority more than 40.
				if ( 0 === $rounded_callback_priority ) {
					$module_priority = 5;
				} elseif ( $rounded_callback_priority >= 50 ) {
					$module_priority = 40;
				}

				$module_slug = $modules_priority[ $module_priority ] ?? '';

				/**
				 * Filters target module for the current callback.
				 *
				 * 3rd-party plugins can use this filter to target different module slug.
				 *
				 * @since 4.14.5
				 *
				 * @param string $module_slug     Module slug.
				 * @param string $callback_name   Callback name.
				 * @param string $module_priority Module priority.
				 */
				$module_slug = apply_filters( 'et_builder_wc_relocate_single_product_summary_module_slug', $module_slug, $callback_name, $module_priority );

				/**
				 * Filters target module for the current callback.
				 *
				 * 3rd-party plugins can use this filter to target different module slug.
				 *
				 * @since ??
				 *
				 * @param string $module_slug     Module slug.
				 * @param string $callback_name   Callback name.
				 * @param string $module_priority Module priority.
				 */
				$module_slug = apply_filters( 'divi_woocommerce_relocate_single_product_summary_module_slug', $module_slug, $callback_name, $module_priority );

				// Bail early if module slug is empty.
				if ( empty( $module_slug ) ) {
					continue;
				}

				// 3. Determine 'output location'.
				// Move the callback to the suitable WooCommerce module. Since we can't call the action
				// inside the module render, we have to buffer the output and prepend/append it
				// to the module output or preview. By default, the default location is 'after'
				// the module output or preview. But, for priority less than 5, we have to put it
				// before the `et_pb_wc_title` because there is no module on that location.
				$output_location = $callback_priority < 5 ? 'before' : 'after';

				/**
				 * Filters output location for the current module and callback.
				 *
				 * 3rd-party plugins can use this filter to change the output location.
				 *
				 * @since 4.14.5
				 *
				 * @param string $output_location   Output location.
				 * @param string $callback_name     Callback name.
				 * @param string $module_slug       Module slug.
				 * @param string $callback_priority Callback priority.
				 */
				$output_location = apply_filters( 'et_builder_wc_relocate_single_product_summary_output_location', $output_location, $callback_name, $module_slug, $callback_priority );

				/**
				 * Filters output location for the current module and callback.
				 *
				 * 3rd-party plugins can use this filter to change the output location.
				 *
				 * @since ??
				 *
				 * @param string $output_location   Output location.
				 * @param string $callback_name     Callback name.
				 * @param string $module_slug       Module slug.
				 * @param string $callback_priority Callback priority.
				 */
				$output_location = apply_filters( 'divi_woocommerce_relocate_single_product_summary_output_location', $output_location, $callback_name, $module_slug, $callback_priority );

				// Bail early if the output location is not 'before' or 'after'.
				if ( ! in_array( $output_location, [ 'before', 'after' ], true ) ) {
					continue;
				}

				// 4. Determine 'module output priority'.
				// Get the "{$module_slug}_{$hook_suffix_name}}" filter priority number by sum up
				// default hook priority number (10) and the remainder. This part is important,
				// so we can prepend and append the layout output more accurate. For example:
				// Callback A with priority 42 should be added after callback B with priority 41
				// on `et_pb_wc_meta` module. So, "et_pb_wc_meta_{$hook_suffix_name}_output" hook
				// priority for callback A will be 12, meanwhile callback B will be 11.
				$remainder_priority = $rounded_callback_priority > 0 ? $callback_priority % 10 : $callback_priority - 5;
				$output_priority    = 10 + $remainder_priority;

				/**
				 * Filters module output priority number for the current module and callback.
				 *
				 * 3rd-party plugins can use this filter to rearrange the output priority.
				 *
				 * @since 4.14.5
				 *
				 * @param string $output_priority   Module output priority number.
				 * @param string $callback_name     Callback name.
				 * @param string $module_slug       Module slug.
				 * @param string $callback_priority Callback priority.
				 */
				$output_priority = apply_filters( 'et_builder_wc_relocate_single_product_summary_output_priority', $output_priority, $callback_name, $module_slug, $callback_priority );

				/**
				 * Filters module output priority number for the current module and callback.
				 *
				 * 3rd-party plugins can use this filter to rearrange the output priority.
				 *
				 * @since ??
				 *
				 * @param string $output_priority   Module output priority number.
				 * @param string $callback_name     Callback name.
				 * @param string $module_slug       Module slug.
				 * @param string $callback_priority Callback priority.
				 */
				$output_priority = apply_filters( 'divi_woocommerce_relocate_single_product_summary_output_priority', $output_priority, $callback_name, $module_slug, $callback_priority );

				// Remove the callback from `woocommerce_single_product_summary` when it's needed.
				if ( $is_move_needed ) {
					remove_action( 'woocommerce_single_product_summary', $callback_function, $callback_priority );
				}

				// And, copy and paste it to suitable location & module.
				add_action( "et_builder_wc_single_product_summary_{$output_location}_{$module_slug}", $callback_function, $output_priority );

				// And, copy and paste it to suitable location & module.
				add_action( "divi_woocommerce_single_product_summary_{$output_location}_{$module_slug}", $callback_function, $output_priority );

				$modules_with_relocation[] = $module_slug;
			}
		}

		// Finally, move it to suitable WooCommerce modules.
		if ( ! empty( $modules_with_relocation ) ) {
			foreach ( $modules_with_relocation as $module_slug ) {
				// Builder - Before and/or after components.
				add_filter( "{$module_slug}_fb_before_after_components", [ self::class, 'single_product_summary_before_after_components' ], 10, 3 );

				// FE - Shortcode output only (matches D4 pattern).
				// Blocks get relocated content through render_module_template() → manual do_action() calls.
				// Shortcodes get relocated content through _shortcode_output filter wrapper.
				add_filter( "{$module_slug}_shortcode_output", 'et_builder_wc_single_product_summary_module_output', 10, 3 );
			}
		}

		self::$_relocation_done = true;
	}

	/**
	 * Sets the meta to indicate that the Divi content has been modified.
	 *
	 * This avoids setting the default WooCommerce Modules layout more than once.
	 *
	 * Legacy function: et_builder_wc_set_page_content_status()
	 *
	 * @link https://github.com/elegantthemes/Divi/issues/16420
	 *
	 * @since ??
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public static function set_page_content_status( int $post_id ): void {
		if ( 0 === absint( $post_id ) ) {
			return;
		}

		/**
		 * The ID page of the Checkout page set in WooCommerce Settings page.
		 *
		 * WooCommerce — Settings — Advanced — Checkout page
		 */
		$checkout_page_id = wc_get_page_id( 'checkout' );

		/**
		 * The ID page of the Cart page set in WooCommerce Settings page.
		 *
		 * WooCommerce — Settings — Advanced — Cart page
		 */
		$cart_page_id = wc_get_page_id( 'cart' );

		$is_cart     = $post_id === $cart_page_id;
		$is_checkout = $post_id === $checkout_page_id;
		$is_product  = 'product' === get_post_type( $post_id );

		// Take action only on the Product, Cart and Checkout pages. Bail early otherwise.
		if ( ! ( $is_product || $is_cart || $is_checkout ) ) {
			return;
		}

		$modified_status            = 'modified';
		$is_content_status_modified = get_post_meta( $post_id, self::get_product_page_content_status_meta_key(), true ) === $modified_status;

		if ( $is_content_status_modified ) {
			return;
		}

		update_post_meta( $post_id, self::get_product_page_content_status_meta_key(), $modified_status );
	}

	/**
	 * Sets the Product page layout post meta on two occurrences.
	 *
	 * They are 1) On WP Admin Publish/Update post 2) On VB Save.
	 *
	 * Legacy function: et_builder_set_product_page_layout_meta().
	 *
	 * @since ??
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public static function set_product_page_layout_meta( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		/*
		 * The Product page layout post meta adds no meaning to the Post when the Builder is not used.
		 * Hence the meta key/value is removed, when the Builder is turned off.
		 */
		if ( ! et_pb_is_pagebuilder_used( $post_id ) ) {
			delete_post_meta( $post_id, self::get_product_page_layout_meta_key() );
			return;
		}

		// The meta key is to be used only on Product post types.
		// Hence, remove the meta if exists on other post-types.
		$is_non_product_post_type = 'product' !== $post->post_type;
		if ( $is_non_product_post_type ) {
			// Returns FALSE when no meta key is found.
			delete_post_meta( $post_id, self::get_product_page_layout_meta_key() );

			return;
		}

		// Do not update the Product page layout post meta when it contains a value.
		$product_page_layout = get_post_meta(
			$post_id,
			self::get_product_page_layout_meta_key(),
			true
		);
		if ( $product_page_layout ) {
			return;
		}

		$product_page_layout = et_get_option(
			'et_pb_woocommerce_page_layout',
			'et_build_from_scratch'
		);

		update_post_meta(
			$post_id,
			self::get_product_page_layout_meta_key(),
			sanitize_text_field( $product_page_layout )
		);
	}

	/**
	 * Sets the pre-filled Divi Woo Pages layout content.
	 *
	 * The following are the three types of WooCommerce pages that have pre-filled content.
	 *
	 * 1. WooCommerce Product page
	 * 2. WooCommerce Cart page
	 * 3. WooCommerce Checkout page
	 *
	 * Based on legacy function: et_builder_wc_set_prefilled_page_content().
	 *
	 * @param string $maybe_builder_content Maybe et builder content.
	 * @param int    $post_id Post ID.
	 *
	 * @return string
	 */
	public static function set_prefilled_page_content( string $maybe_builder_content, int $post_id ): string {
		$post = get_post( absint( $post_id ) );
		if ( ! $post ) {
			return $maybe_builder_content;
		}

		/**
		 * The ID page of the Checkout page set in WooCommerce Settings page.
		 *
		 * WooCommerce — Settings — Advanced — Checkout page
		 */
		$checkout_page_id = wc_get_page_id( 'checkout' );

		/**
		 * The ID page of the Cart page set in WooCommerce Settings page.
		 *
		 * WooCommerce — Settings — Advanced — Cart page
		 */
		$cart_page_id = wc_get_page_id( 'cart' );

		$is_cart     = $post_id === $cart_page_id;
		$is_checkout = $post_id === $checkout_page_id;
		$is_product  = ( $post instanceof WP_Post ) && 'product' === $post->post_type;

		// Bail early when none of the conditions are met.
		if ( ! ( $is_product || $is_checkout || $is_cart ) ) {
			return $maybe_builder_content;
		}

		// Bail early if the Page already has an initial content set.
		$is_content_status_modified = 'modified' === get_post_meta( $post_id, self::get_product_page_content_status_meta_key(), true );

		if ( $is_content_status_modified ) {
			return $maybe_builder_content;
		}

		$should_replace_content = true;
		if ( $is_cart || $is_checkout ) {
			$should_replace_content = self::should_replace_content( $maybe_builder_content );
		}

		if ( $is_cart && $should_replace_content ) {
			return self::get_prefilled_cart_page_content();
		} elseif ( $is_checkout && $should_replace_content ) {
			return self::get_prefilled_checkout_page_content();
		} elseif ( $is_product ) {
			$args                = [];
			$product_page_layout = self::get_product_layout( $post_id );

			/*
			 * When FALSE, this means the Product doesn't use Builder at all;
			 * Or the Product has been using the Builder before WooCommerce Modules QF launched.
			 */
			if ( ! $product_page_layout ) {
				$product_page_layout = et_get_option(
					'et_pb_woocommerce_page_layout',
					'et_build_from_scratch'
				);
			}

			// Load default content.
			if ( 'et_default_layout' === $product_page_layout ) {
				return $maybe_builder_content;
			}

			$has_builder_content               = has_shortcode( $maybe_builder_content, 'et_pb_section' ) || has_block( 'divi/section', $maybe_builder_content );
			$is_layout_type_build_from_scratch = 'et_build_from_scratch' === $product_page_layout;

			if ( $has_builder_content && $is_layout_type_build_from_scratch ) {
				$args['existing_content'] = $maybe_builder_content;
			}

			return self::get_prefilled_product_page_content( $args );
		}

		return $maybe_builder_content;
	}

	/**
	 * Skips setting default content on Product post type during Builder activation.
	 *
	 * Otherwise, the description would be shown in both Product Tabs and at the end of the
	 * default WooCommerce layout set at @see self::get_prefilled_product_page_content().
	 *
	 * @since 3.29
	 *
	 * @param bool    $flag Whether to skips the content activation.
	 * @param WP_Post $post Post.
	 *
	 * @return bool
	 */
	public static function skip_initial_content( bool $flag, $post ): bool {
		if ( ! ( $post instanceof WP_Post ) ) {
			return $flag;
		}

		if ( 'product' !== $post->post_type ) {
			return $flag;
		}

		return true;
	}

	/**
	 * Prepends and/or append callback output to the suitable module output on FE.
	 *
	 * This function is responsible for processing the output of WooCommerce modules in the FE.
	 * It checks if the module output is a string and retrieves the current product.
	 * It then appends the before and after components to the module's output.
	 * The function also handles the case where the WooCommerce module is being used in the Theme Builder or FE.
	 * It ensures that the global product and post objects are set correctly based on the target product ID.
	 * The function returns the processed module output.
	 *
	 * This function is based on legacy `et_builder_wc_single_product_summary_module_output` function.
	 *
	 * @since ??
	 *
	 * @param string       $module_output             Module output.
	 * @param string|array $module_slug_or_block      Module slug (shortcode) or parsed block array (block).
	 * @param mixed        $maybe_block_or_product_id Product ID (shortcode) or WP_Block instance (block).
	 *
	 * @return string Processed module output.
	 */
	public static function single_product_summary_module_output( string $module_output, $module_slug_or_block, $maybe_block_or_product_id = null ): string {
		// Ensure hooks are relocated before processing module output.
		// The static guard in relocate_woocommerce_single_product_summary() prevents duplicate execution.
		self::relocate_woocommerce_single_product_summary();

		global $post, $product;

		$original_post    = $post;
		$original_product = $product;
		$target_id        = '';
		$is_overwritten   = false;

		// Resolve module slug and product id for both shortcode and block contexts.
		$module_slug = '';
		$product_id  = null;
		if ( is_string( $module_slug_or_block ) ) {
			// Shortcode context: (output, module_slug, product_id).
			$module_slug = $module_slug_or_block;
			$product_id  = $maybe_block_or_product_id;
		} elseif ( is_array( $module_slug_or_block ) ) {
			// Block context: (output, parsed_block, WP_Block).
			$module_slug = isset( $module_slug_or_block['blockName'] ) ? (string) $module_slug_or_block['blockName'] : '';
		}

		if ( ! empty( $product_id ) ) {
			// Get target ID if any.
			$target_id = WoocommerceUtils::get_product_id( $product_id );
		}

		// Determine whether global product and post objects need to be overwritten or not.
		if ( 'current' !== $target_id ) {
			$target_product = wc_get_product( $target_id );

			if ( $target_product instanceof \WC_Product ) {
				$is_overwritten = false;
				$product        = $target_product;
				$post           = get_post( $product->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- Overriding global post is safe as original $post is restored at the function end.
			}
		}

		// Get before & after outputs only if product is WC_Product instance.
		if ( $product instanceof \WC_Product ) {
			$before_output = $module_slug ? self::single_product_summary_before_module( $module_slug ) : '';
			$after_output  = $module_slug ? self::single_product_summary_after_module( $module_slug ) : '';
			$module_output = $before_output . $module_output . $after_output;
		}

		// Reset product and/or post object.
		if ( $is_overwritten ) {
			$product = $original_product;
			$post    = $original_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- Restoring global post.
		}

		return $module_output;
	}

	/**
	 * Sets callback output as before and/or after components on builder.
	 *
	 * This function is responsible for processing the before and after components
	 * of a WooCommerce module. It checks if the module is a WooCommerce module,
	 * retrieves the current product, and appends the before and after components
	 * to the module's output. The function also handles the case where the
	 * WooCommerce module is being used in the Theme Builder or Frontend Builder.
	 * It ensures that the global product and post objects are set correctly
	 * based on the target product ID. The function returns the processed module
	 * before and after components.
	 *
	 * This function is based on legacy `et_builder_wc_single_product_summary_before_after_components` function.
	 *
	 * @since ??
	 *
	 * @param array  $module_components Default module before & after components.
	 * @param string $module_slug       Module slug.
	 * @param array  $module_data       Module data.
	 *
	 * @return array Processed module before & after components.
	 */
	public static function single_product_summary_before_after_components( array $module_components, string $module_slug, array $module_data ): array {
		// Bail early if module components variable is not an array.
		if ( ! is_array( $module_components ) ) {
			return $module_components;
		}

		global $post, $product;

		$original_post    = $post;
		$original_product = $product;
		$target_id        = '';
		$overwritten_by   = '';
		$is_tb_enabled    = et_builder_tb_enabled();
		$is_fb_enabled    = et_core_is_fb_enabled() || is_et_pb_preview();

		if ( ! empty( $module_data ) ) {
			// Get target ID if any.
			$target_id = WooCommerceUtils::get_product_id( et_()->array_get( $module_data, [ 'module_attrs', 'product' ] ) );
		}

		// Determine whether global product and post objects need to be overwritten or not.
		// - Dummy product:  TB and FB initial load.
		// - Target product: Components request from builder.
		if ( $is_tb_enabled || $is_fb_enabled ) {
			WooCommerceUtils::reset_global_objects_for_theme_builder( [ 'is_tb' => true ] );
			$overwritten_by = 'dummy_product';
		} elseif ( 'current' !== $target_id && Conditions::is_rest_api_request() ) {
			$target_product = wc_get_product( $target_id );

			if ( $target_product instanceof \WC_Product ) {
				$overwritten_by = 'target_product';
				$product        = $target_product;
				$post           = get_post( $product->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- Overriding global post is safe as original $post is restored at the function end.
			}
		}

		// Get before and after components only if product is WC_Product instance.
		if ( $product instanceof \WC_Product ) {
			$default_before_component = et_()->array_get( $module_components, '__before_component', '' );
			$default_after_component  = et_()->array_get( $module_components, '__after_component', '' );
			$current_before_component = self::single_product_summary_before_module( $module_slug );
			$current_after_component  = self::single_product_summary_after_module( $module_slug );

			$module_components['has_components']     = true;
			$module_components['__before_component'] = $default_before_component . $current_before_component;
			$module_components['__after_component']  = $default_after_component . $current_after_component;
		}

		// Reset product and/or post-object.
		if ( 'dummy_product' === $overwritten_by ) {
			WooCommerceUtils::reset_global_objects_for_theme_builder( [ 'is_tb' => true ] );
		} elseif ( 'target_product' === $overwritten_by ) {
			$product = $original_product;
			$post    = $original_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- Restoring global post.
		}

		return $module_components;
	}

	/**
	 * Renders single product summary before WooCommerce module output.
	 *
	 * This function is responsible for rendering the output before a specific WooCommerce module.
	 * It captures the output of the action hook
	 * `divi_woocommerce_single_product_summary_before_{module_slug}` and returns it as a string.
	 * This allows for additional content or modifications to be added before the module's output.
	 *
	 * This function is based on legacy `et_builder_wc_single_product_summary_before_module` function.
	 *
	 * @since ??
	 *
	 * @param string $module_slug Module slug.
	 *
	 * @return string Rendered output.
	 */
	public static function single_product_summary_before_module( string $module_slug ): string {
		ob_start();

		/**
		 * Fires additional output for single product summary before module output.
		 *
		 * @since 4.14.5
		 */
		do_action( "et_builder_wc_single_product_summary_before_{$module_slug}" );

		/**
		 * Fires additional output for single product summary before module output.
		 *
		 * @since ??
		 */
		do_action( "divi_woocommerce_single_product_summary_before_{$module_slug}" );

		return ob_get_clean();
	}

	/**
	 * Renders single product summary after WooCommerce module output.
	 *
	 * This function is responsible for rendering the output after a specific WooCommerce module.
	 * It captures the output of the action hook
	 * `divi_woocommerce_single_product_summary_after_{module_slug}` and returns it as a string.
	 * This allows for additional content or modifications to be added after the module's output.
	 *
	 * This function is based on legacy `et_builder_wc_single_product_summary_after_module` function.
	 *
	 * @since ??
	 *
	 * @param string $module_slug Module slug.
	 *
	 * @return string Rendered output.
	 */
	public static function single_product_summary_after_module( string $module_slug ): string {
		ob_start();

		/**
		 * Fires additional output for single product summary after module output.
		 *
		 * @since ??
		 */
		do_action( "divi_woocommerce_single_product_summary_after_{$module_slug}" );

		return ob_get_clean();
	}

	/**
	 * Saves the WooCommerce long description metabox content.
	 *
	 * The content is stored as post-meta w/ the key returned by `self::get_long_desc_meta_key()`.
	 *
	 * Legacy function: et_builder_wc_long_description_metabox_save()
	 *
	 * @since ??
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post    Post Object.
	 * @param array   $request The $_POST Request variables.
	 *
	 * @return void
	 */
	public static function long_description_metabox_save( int $post_id, WP_Post $post, array $request ): void {

		if ( ! isset( $request['et_bfb_long_description_nonce'] ) ) {
			return;
		}

		// First, verify the nonce.
		$nonce_valid = wp_verify_nonce( $request['et_bfb_long_description_nonce'], '_et_bfb_long_description_nonce' );
		if ( ! $nonce_valid ) {
			return;
		}

		// Then, check if the user can edit posts.
		// Skip this check in test environments.
		if ( ! Conditions::is_test_env() && ! current_user_can( 'edit_posts', $post_id ) ) {
			return;
		}

		if ( 'product' !== $post->post_type ) {
			return;
		}

		if ( ! isset( $request['et_builder_wc_product_long_description'] ) ) {
			return;
		}

		$long_desc_content = $request['et_builder_wc_product_long_description'];
		$sanitized_content = wp_kses_post( $long_desc_content );

		update_post_meta( $post_id, self::get_long_desc_meta_key(), $sanitized_content );
	}

	/**
	 * Output Callback for Product long description metabox.
	 *
	 * Legacy function: et_builder_wc_long_description_metabox_render()
	 *
	 * @since ??
	 *
	 * @param WP_Post $post Post.
	 *
	 * @return void
	 */
	public static function long_description_metabox_render( WP_Post $post ): void {

		$settings = [
			'textarea_name' => 'et_builder_wc_product_long_description',
			'quicktags'     => [ 'buttons' => 'em,strong,link' ],
			'tinymce'       => [
				'theme_advanced_buttons1' => 'bold,italic,strikethrough,separator,bullist,numlist,separator,blockquote,separator,justifyleft,justifycenter,justifyright,separator,link,unlink,separator,undo,redo,separator',
				'theme_advanced_buttons2' => '',
			],
			'editor_css'    => '<style>#wp-et_builder_wc_product_long_description-editor-container .wp-editor-area{height:175px; width:100%;}</style>',
		];

		// Since we use $post_id in more than one place, use a variable.
		$post_id = $post->ID;

		// Long description metabox content. Default Empty.
		$long_desc_content = get_post_meta( $post_id, self::get_long_desc_meta_key(), true );
		$long_desc_content = ! empty( $long_desc_content ) ? $long_desc_content : '';

		/**
		 * Filters the wp_editor settings used in the Long description metabox.
		 *
		 * @since ??
		 *
		 * @param array $settings WP Editor settings.
		 */
		$settings = apply_filters( 'divi_woocommerce_product_long_description_editor_settings', $settings );

		wp_nonce_field( '_et_bfb_long_description_nonce', 'et_bfb_long_description_nonce' );

		wp_editor(
			$long_desc_content,
			'et_builder_wc_product_long_description',
			$settings
		);
	}

	/**
	 * Adds the Long description metabox to Product post type.
	 *
	 * Legacy function: et_builder_wc_long_description_metabox_register()
	 *
	 * @since ??
	 *
	 * @param WP_Post $post WP Post.
	 *
	 * @return void
	 */
	public static function long_description_metabox_register( WP_Post $post ): void {
		// Use the proper utility function that handles both D4 and D5 meta keys.
		$is_pagebuilder_used = et_pb_is_pagebuilder_used( $post->ID );

		if ( ! $is_pagebuilder_used ) {
			return;
		}

		add_meta_box(
			'et_builder_wc_product_long_description_metabox',
			__( 'Product long description', 'et_builder_5' ),
			[ self::class, 'long_description_metabox_render' ],
			'product',
			'normal'
		);
	}

	/**
	 * Strip Builder shortcodes to avoid nested parsing.
	 *
	 * Legacy function: et_builder_avoid_nested_shortcode_parsing()
	 *
	 * @see   https://github.com/elegantthemes/Divi/issues/18682
	 *
	 * @since ??
	 *
	 * @param string $content Post content.
	 *
	 * @return string
	 */
	public static function avoid_nested_shortcode_parsing( string $content ): string {
		if ( is_et_pb_preview() ) {
			return $content;
		}

		// Strip shortcodes only on non-builder pages that contain Builder shortcodes.
		if ( et_pb_is_pagebuilder_used( get_the_ID() ) ) {
			return $content;
		}

		// WooCommerce layout loads when the builder is not enabled.
		// So strip builder shortcodes from Post content.
		if ( function_exists( 'is_product' ) && is_product() ) {
			return et_strip_shortcodes( $content );
		}

		// Strip builder shortcodes from non-product pages.
		// Only Tabs shortcode is checked since that causes nested rendering.
		if ( has_shortcode( $content, 'et_pb_wc_tabs' ) ) {
			return et_strip_shortcodes( $content );
		}

		return $content;
	}

	/**
	 * WooCommerce product thumbnail template with et_shop_image wrapper.
	 *
	 * This function replaces the theme's woocommerce_template_loop_product_thumbnail()
	 * to ensure a consistent et_shop_image wrapper application in D5 modules.
	 *
	 * Based on the legacy function: woocommerce_template_loop_product_thumbnail().
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function template_loop_product_thumbnail(): void {
		// Check if any module wants to add box shadow overlay.
		/**
		 * Filters the overlay attributes for WooCommerce product thumbnails.
		 *
		 * @since ??
		 *
		 * @param array $overlay_attr The overlay attributes.
		 */
		$overlay_attr = apply_filters( 'et_d5_woocommerce_product_thumbnail_overlay_attr', [] );

		if ( ! empty( $overlay_attr ) ) {
			$overlay_class = BoxShadowClassnames::has_overlay( $overlay_attr );

			if ( $overlay_class ) {
				$overlay_component = BoxShadowOverlay::component( [ 'attr' => $overlay_attr ] );

				printf(
					'<span class="et_shop_image %1$s">%2$s%3$s<span class="et_overlay"></span></span>',
					esc_attr( $overlay_class ),
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- BoxShadowOverlay component returns escaped HTML.
					$overlay_component,
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce function already returns escaped HTML.
					woocommerce_get_product_thumbnail()
				);
				return;
			}
		}

		// Default rendering without box shadow overlay.
		printf(
			'<span class="et_shop_image">%1$s<span class="et_overlay"></span></span>',
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce function already returns escaped HTML.
			woocommerce_get_product_thumbnail()
		);
	}

	/**
	 * Register Page Settings items for WooCommerce products.
	 *
	 * Registers Product Long Description field for D5 Visual Builder Page Settings
	 * to replace the D4 backend meta box functionality.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function register_page_settings_items(): void {
		PageSettings::register_item(
			[
				'get_name'               => self::get_long_desc_meta_key(),
				'name'                   => 'wooCommerceProductLongDescription',
				'default_value'          => '',
				'save_sanitize_function' => 'wp_kses_post',
				'get_value_function'     => function ( $post_id, $default_value ) {
					$post_type = get_post_type( $post_id );

					// Only show for WooCommerce product post type.
					if ( 'product' !== $post_type ) {
						return '';
					}

					$saved_value  = get_post_meta( $post_id, self::get_long_desc_meta_key(), true );
					$return_value = '' !== $saved_value ? $saved_value : $default_value;

					return $return_value;
				},
			]
		);
	}

	/**
	 * Adds a `woocommerce_before_checkout_form` hook callback.
	 *
	 * This functions adds the `woocommerce_checkout_coupon_form` callback to the `woocommerce_before_checkout_form` action.
	 * The added callback renders the `Checkout Coupon` form.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function attach_wc_checkout_coupon_form(): void {
		add_action(
			'woocommerce_before_checkout_form',
			'woocommerce_checkout_coupon_form',
			10
		);
	}

	/**
	 * Adds a `woocommerce_before_checkout_form` hook callback.
	 *
	 * This functions adds the `woocommerce_checkout_login_form` callback to the `woocommerce_before_checkout_form` action.
	 * The added callback renders the `Login` form.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function attach_wc_checkout_login_form(): void {
		add_action(
			'woocommerce_before_checkout_form',
			'woocommerce_checkout_login_form',
			10
		);
	}

	/**
	 * Adds a `woocommerce_checkout_order_review` hook callback.
	 *
	 * This functions adds the `woocommerce_order_review` callback to the `woocommerce_checkout_order_review` action.
	 * The added callback renders the `Order review` (Mini cart).
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function attach_wc_checkout_order_review(): void {
		add_action( 'woocommerce_checkout_order_review', 'woocommerce_order_review', 10 );
	}

	/**
	 * Adds a `woocommerce_checkout_order_review` hook callback.
	 *
	 * This functions adds the `woocommerce_checkout_payment` callback to the `woocommerce_checkout_order_review` action.
	 * The added callback renders the `Checkout Payment` form.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function attach_wc_checkout_payment(): void {
		add_action(
			'woocommerce_checkout_order_review',
			'woocommerce_checkout_payment',
			20
		);
	}

	/**
	 * Adds the checkout form billing rendering `woocommerce_checkout_billing` hook callback.
	 *
	 * This function is used to add the `checkout_form_billing` callback to the `woocommerce_checkout_billing` action.
	 * The added callback renders the `Checkout Billing` form.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function attach_wc_checkout_billing(): void {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		$class = get_class( WC() );
		if ( ! method_exists( $class, 'checkout' ) ) {
			return;
		}

		$checkout = WC()->checkout();

		add_action(
			'woocommerce_checkout_billing',
			[ $checkout, 'checkout_form_billing' ]
		);
	}

	/**
	 * Adds the checkout form shipping rendering `woocommerce_checkout_shipping` hook callback.
	 *
	 * This function is used to add the `checkout_form_shipping` callback to the `woocommerce_checkout_shipping` action.
	 * The added callback renders the `Checkout Shipping` form.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function attach_wc_checkout_shipping(): void {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		$class = get_class( WC() );
		if ( ! method_exists( $class, 'checkout' ) ) {
			return;
		}

		$checkout = WC()->checkout();

		add_action(
			'woocommerce_checkout_shipping',
			[ $checkout, 'checkout_form_shipping' ]
		);
	}

	/**
	 * Removes the coupon rendering `woocommerce_before_checkout_form` hook callback.
	 *
	 * This function is used to remove the `woocommerce_checkout_coupon_form` callback from the `woocommerce_before_checkout_form` action.
	 * Removing the callback stops the `Checkout Coupon` form from rendering.
	 *
	 * The removed callback is typically added via WooCommerceHooks::attach_wc_checkout_coupon_form()
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function detach_wc_checkout_coupon_form(): void {
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
	}

	/**
	 * Removes the login rendering `woocommerce_before_checkout_form` hook callback.
	 *
	 * This function is used to remove the `woocommerce_checkout_login_form` callback from the `woocommerce_before_checkout_form` action.
	 * Removing the callback stops the `Login` form from rendering.
	 *
	 * The removed callback is typically added via WooCommerceHooks::attach_wc_checkout_login_form()
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function detach_wc_checkout_login_form() {
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
	}

	/**
	 * Removes the order review rendering `woocommerce_checkout_order_review` hook callback.
	 *
	 * This function is used to remove the `woocommerce_order_review` callback from the `woocommerce_checkout_order_review` action.
	 * Removing the callback stops the `Order review` (Mini cart) from rendering.
	 *
	 * The removed callback is typically added via WooCommerceHooks::attach_wc_checkout_order_review()
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function detach_wc_checkout_order_review(): void {
		remove_action(
			'woocommerce_checkout_order_review',
			'woocommerce_order_review',
			10
		);
	}

	/**
	 * Removes the checkout payment rendering `woocommerce_checkout_order_review` hook callback.
	 *
	 * This function is used to remove the `woocommerce_checkout_payment` callback from the `woocommerce_checkout_order_review` action.
	 * Removing the callback stops the `Checkout Payment` form from rendering.
	 *
	 * The removed callback is typically added via WooCommerceHooks::attach_wc_checkout_payment()
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function detach_wc_checkout_payment(): void {
		remove_action(
			'woocommerce_checkout_order_review',
			'woocommerce_checkout_payment',
			20
		);
	}

	/**
	 * Removes the checkout form billing rendering `woocommerce_checkout_billing` hook callback.
	 *
	 * This function is used to remove the `checkout_form_billing` callback from the `woocommerce_checkout_billing` action.
	 * Removing the callback stops the `Checkout Billing` form from rendering.
	 *
	 * The removed callback is typically added via WooCommerceHooks::attach_wc_checkout_billing()
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function detach_wc_checkout_billing(): void {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		$class = get_class( WC() );
		if ( ! method_exists( $class, 'checkout' ) ) {
			return;
		}

		$checkout = WC()->checkout();

		remove_action(
			'woocommerce_checkout_billing',
			[ $checkout, 'checkout_form_billing' ]
		);
	}

	/**
	 * Removes the checkout form shipping rendering `woocommerce_checkout_shipping` hook callback.
	 *
	 * This function is used to remove the `checkout_form_shipping` callback from the `woocommerce_checkout_shipping` action.
	 * Removing the callback stops the `Checkout Payment` form from rendering.
	 *
	 * The removed callback is typically added via WooCommerceHooks::attach_wc_checkout_shipping()
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function detach_wc_checkout_shipping(): void {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		$class = get_class( WC() );
		if ( ! method_exists( $class, 'checkout' ) ) {
			return;
		}

		$checkout = WC()->checkout();

		remove_action(
			'woocommerce_checkout_shipping',
			[ $checkout, 'checkout_form_shipping' ]
		);
	}

	/**
	 * Stop redirecting to Cart page when enabling builder on Checkout page.
	 *
	 * Legacy function: et_builder_stop_cart_redirect_while_enabling_builder()
	 *
	 * @since ??
	 *
	 * @link https://github.com/elegantthemes/Divi/issues/23873
	 *
	 * @param bool $flag Flag indicating whether to redirect.
	 *
	 * @return bool Modified flag.
	 */
	public static function stop_cart_redirect_while_enabling_builder( bool $flag ): bool {
		/*
		 * Don't need to check if the current page is a Checkout page since this filter
		 * `woocommerce_checkout_redirect_empty_cart` only fires if the
		 * current page is a Checkout page.
		 */

		$post_id = get_the_ID();

		if ( is_array( $_GET ) && isset( $_GET['et_fb'] ) && '1' === $_GET['et_fb'] ) {
			$is_builder_activation_request = true;
		} else {
			// Verify if the request is a valid Builder activation request.
			$is_builder_activation_request = et_core_security_check(
				'',
				"et_fb_activation_nonce_{$post_id}",
				'et_fb_activation_nonce',
				'_REQUEST',
				false
			);
		}

		return $is_builder_activation_request ? false : $flag;
	}

	/**
	 * Handles Shipping calculator Update button click.
	 *
	 * `wc-form-handler` handles shipping calculator update ONLY when WooCommerce shortcode is used.
	 * Hence, Cart Total's shipping calculator update is handled this way.
	 *
	 * Legacy function: et_builder_handle_shipping_calculator_update_btn_click()
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function handle_shipping_calculator_update_btn_click(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is handled by WooCommerce plugin.
		if ( ! isset( $_POST['woocommerce-shipping-calculator-nonce'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is handled by WooCommerce plugin.
		if ( ! isset( $_POST['_wp_http_referer'] ) ) {
			return;
		}

		$nonce_verified = false;

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verification is handled by WordPress.
		if ( isset( $_POST['woocommerce-shipping-calculator-nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce-shipping-calculator-nonce'] ) ), 'woocommerce-shipping-calculator' ) ) { // WPCS: input var ok.
			// We can safely move forward.
			$nonce_verified = true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is handled by WooCommerce plugin.
		$referrer         = isset( $_POST['_wp_http_referer'] ) ? esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) ) : '';
		$referrer_page_id = url_to_postid( $referrer );
		$cart_page_id     = wc_get_page_id( 'cart' );

		// Bail when nonce failed, or $referrer_page_id isn't equal to $cart_page_id.
		if ( ! $nonce_verified || $cart_page_id !== $referrer_page_id ) {
			return;
		}

		if ( ( ! class_exists( 'WC_Shortcodes' ) ) ||
			( ! method_exists( 'WC_Shortcodes', 'cart' ) ) ) {
				return;
		}

		WC_Shortcodes::cart();
	}

	/**
	 * Output the cart shipping calculator.
	 *
	 * Legacy function: et_builder_woocommerce_shipping_calculator()
	 *
	 * @since ??
	 *
	 * @param string $button_text Text for the shipping calculation toggle.
	 *
	 * @return void
	 */
	public static function shipping_calculator( string $button_text = '' ): void {
		wp_enqueue_script( 'wc-country-select' );
		wc_get_template(
			'cart/shipping-calculator.php',
			[
				'button_text' => $button_text,
			]
		);
	}

	/**
	 * Message to be displayed in Checkout Payment Info module in VB mode.
	 *
	 * So styling the Notice becomes easier.
	 *
	 * Legacy function: et_builder_wc_no_available_payment_methods_message()
	 *
	 * @since ??
	 *
	 * @return string Payment methods message.
	 */
	public static function get_no_available_payment_methods_message(): string {
		// Fallback.
		$message = esc_html__( 'Sorry, it seems that there are no available payment methods for your state. Please contact us if you require assistance or wish to make alternate arrangements.', 'woocommerce' );

		if ( ! function_exists( 'WC' ) ) {
			return $message;
		}

		if ( ! isset( WC()->customer ) || ! method_exists( WC()->customer, 'get_billing_country' ) ) {
			return $message;
		}

		$message = WC()->customer->get_billing_country()
			? esc_html__( 'Sorry, it seems that there are no available payment methods for your state. Please contact us if you require assistance or wish to make alternate arrangements.', 'et_builder_5' )
			: esc_html__( 'Please fill in your details above to see available payment methods.', 'et_builder_5' );

		/**
		 * Filters the no available payment methods message.
		 *
		 * @since ??
		 *
		 * @param string $message The payment methods message.
		 */
		return apply_filters(
			'woocommerce_no_available_payment_methods_message',
			$message
		);
	}

	/**
	 * Gets the Checkout modules notice to be displayed on non-checkout pages.
	 *
	 * Legacy function: et_builder_wc_get_non_checkout_page_notice()
	 *
	 * @since ??
	 *
	 * @used-by et_fb_get_static_backend_helpers()
	 *
	 * @return string Notice message.
	 */
	public static function get_non_checkout_page_notice(): string {
		return esc_html__( 'This module will not function properly on the front end of your website because this is not the assigned Checkout page.', 'et_builder_5' );
	}

	/**
	 * Gets the Checkout notice to be displayed on Checkout Payment Info module.
	 *
	 * Legacy function: et_builder_wc_get_checkout_notice()
	 *
	 * @since ??
	 *
	 * @param string $woocommerce_ship_to_destination Shipping destination setting. Default 'shipping'.
	 *
	 * @used-by et_fb_get_static_backend_helpers()
	 *
	 * @return string Checkout notice message.
	 */
	public static function get_checkout_notice( string $woocommerce_ship_to_destination = 'shipping' ): string {
		$settings_modal_notice = '';

		if ( 'billing_only' === $woocommerce_ship_to_destination ) {
			$settings_modal_notice = wp_kses(
				__( '<strong>Woo Billing Address Module</strong> must be added to this page to allow users to submit orders.', 'et_builder_5' ),
				[ 'strong' => [] ]
			);
		} else {
			$settings_modal_notice = wp_kses(
				__( '<strong>Woo Billing Address Module</strong> and <strong>Woo Shipping Address Module</strong> must be added to this page to allow users to submit orders.', 'et_builder_5' ),
				[ 'strong' => [] ]
			);
		}

		return $settings_modal_notice;
	}

	/**
	 * Ensure WooCommerce Blocks checkout settings are registered for REST API.
	 *
	 * This method addresses the issue where WooCommerce checkout field settings
	 * (company_field, phone_field, address_2_field) are not available in the
	 * /wp/v2/settings REST API endpoint when Divi theme is active.
	 *
	 * The issue occurs because WooCommerce Blocks may not be properly initialized
	 * when Divi is active, preventing the checkout block from registering its
	 * settings via the rest_api_init hook.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function ensure_woocommerce_checkout_settings_registration(): void {
		// Only run if WooCommerce Blocks Checkout class exists.
		if ( ! class_exists( Checkout::class ) ) {
			return;
		}

		// Check if the settings are already registered.
		$registered_settings = get_registered_settings();

		// List of required WooCommerce checkout field settings.
		$required_settings = [
			'woocommerce_checkout_company_field',
			'woocommerce_checkout_phone_field',
			'woocommerce_checkout_address_2_field',
		];

		// Find any missing settings.
		$missing_settings = array_filter(
			$required_settings,
			function ( $setting ) use ( $registered_settings ) {
				return ! isset( $registered_settings[ $setting ] );
			}
		);

		// If any settings are missing, register them directly.
		if ( ! empty( $missing_settings ) ) {
			try {
				// Check if required WooCommerce Blocks utility class exists.
				if ( ! class_exists( CartCheckoutUtils::class ) ) {
					return;
				}

				// Register the missing settings directly using the same parameters as WooCommerce.
				foreach ( $missing_settings as $setting ) {
					switch ( $setting ) {
						case 'woocommerce_checkout_phone_field':
							register_setting(
								'options',
								'woocommerce_checkout_phone_field',
								[
									'type'         => 'object',
									'description'  => __( 'Controls the display of the phone field in checkout.', 'woocommerce' ),
									'label'        => __( 'Phone number', 'woocommerce' ),
									'show_in_rest' => [
										'name'   => 'woocommerce_checkout_phone_field',
										'schema' => [
											'type' => 'string',
											'enum' => [ 'optional', 'required', 'hidden' ],
										],
									],
									'default'      => CartCheckoutUtils::get_phone_field_visibility(),
								]
							);
							break;

						case 'woocommerce_checkout_company_field':
							register_setting(
								'options',
								'woocommerce_checkout_company_field',
								[
									'type'         => 'object',
									'description'  => __( 'Controls the display of the company field in checkout.', 'woocommerce' ),
									'label'        => __( 'Company', 'woocommerce' ),
									'show_in_rest' => [
										'name'   => 'woocommerce_checkout_company_field',
										'schema' => [
											'type' => 'string',
											'enum' => [ 'optional', 'required', 'hidden' ],
										],
									],
									'default'      => CartCheckoutUtils::get_company_field_visibility(),
								]
							);
							break;

						case 'woocommerce_checkout_address_2_field':
							register_setting(
								'options',
								'woocommerce_checkout_address_2_field',
								[
									'type'         => 'object',
									'description'  => __( 'Controls the display of the apartment (address_2) field in checkout.', 'woocommerce' ),
									'label'        => __( 'Address Line 2', 'woocommerce' ),
									'show_in_rest' => [
										'name'   => 'woocommerce_checkout_address_2_field',
										'schema' => [
											'type' => 'string',
											'enum' => [ 'optional', 'required', 'hidden' ],
										],
									],
									'default'      => CartCheckoutUtils::get_address_2_field_visibility(),
								]
							);
							break;
					}
				}
			} catch ( Exception $e ) {
				// Log error for debugging.
				if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'Divi: Error registering WooCommerce checkout settings: ' . $e->getMessage() );
				}
			}
		}
	}

	/**
	 * Remove WooCommerce lightbox theme support in Visual Builder.
	 *
	 * Prevents PhotoSwipe lightbox initialization in Visual Builder by removing
	 * theme support for `wc-product-gallery-lightbox`. This ensures the lightbox
	 * doesn't interfere with Visual Builder's click handling system while
	 * preserving slider and zoom functionality.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function remove_lightbox_theme_support_in_vb(): void {
		if ( Conditions::is_vb_enabled() ) {
			remove_theme_support( 'wc-product-gallery-lightbox' );
		}
	}

	/**
	 * Gets the Long Description Meta Key safely.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	public static function get_long_desc_meta_key(): string {
		return defined( 'ET_BUILDER_WC_PRODUCT_LONG_DESC_META_KEY' ) ? constant( 'ET_BUILDER_WC_PRODUCT_LONG_DESC_META_KEY' ) : self::PRODUCT_LONG_DESC_META_KEY;
	}

	/**
	 * Gets the Product Page Layout Meta Key safely.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	public static function get_product_page_layout_meta_key(): string {
		return defined( 'ET_BUILDER_WC_PRODUCT_PAGE_LAYOUT_META_KEY' ) ? constant( 'ET_BUILDER_WC_PRODUCT_PAGE_LAYOUT_META_KEY' ) : self::PRODUCT_PAGE_LAYOUT_META_KEY;
	}

	/**
	 * Gets the Product Page Content Status Meta Key safely.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	public static function get_product_page_content_status_meta_key(): string {
		return defined( 'ET_BUILDER_WC_PRODUCT_PAGE_CONTENT_STATUS_META_KEY' ) ? constant( 'ET_BUILDER_WC_PRODUCT_PAGE_CONTENT_STATUS_META_KEY' ) : self::PRODUCT_PAGE_CONTENT_STATUS_META_KEY;
	}
}

/*
 * Define required constants.
 *
 * The constants are copied from legacy (D4) code in `includes/builder/feature/woocommerce-modules.php` which would
 * define these constants if D5 page has woo modules shortcodes, however, these constants won't be fined otherwise.
 * This is why it's being copied here for a page which has only D5 WC modules.
 *
 * If needed, these constants would be modified for D5 purposes in a future iteration.
 */
if ( ! defined( 'ET_BUILDER_WC_PRODUCT_LONG_DESC_META_KEY' ) ) {
	// Post meta key to retrieve/save Long description metabox content.
	define( 'ET_BUILDER_WC_PRODUCT_LONG_DESC_META_KEY', WooCommerceHooks::PRODUCT_LONG_DESC_META_KEY );
}

if ( ! defined( 'ET_BUILDER_WC_PRODUCT_PAGE_LAYOUT_META_KEY' ) ) {
	// Post meta key to retrieve/save Long description metabox content.
	define( 'ET_BUILDER_WC_PRODUCT_PAGE_LAYOUT_META_KEY', WooCommerceHooks::PRODUCT_PAGE_LAYOUT_META_KEY );
}

if ( ! defined( 'ET_BUILDER_WC_PRODUCT_PAGE_CONTENT_STATUS_META_KEY' ) ) {
	// Post meta key to track Product page content status changes.
	define( 'ET_BUILDER_WC_PRODUCT_PAGE_CONTENT_STATUS_META_KEY', WooCommerceHooks::PRODUCT_PAGE_CONTENT_STATUS_META_KEY );
}
