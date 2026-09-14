<?php
/**
 * Conditions class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Framework\Utility;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use Divi\D5_Readiness\Server\Checks\PluginHooksCheck;

/**
 * Conditions class.
 *
 * This class contains helper methods to check for certain conditions.
 *
 * @since ??
 */
class Conditions {

	/**
	 * Determine if Visual Builder (VB) is enabled on a post/page.
	 *
	 * This function is proxy function of existing D4 function `et_core_is_fb_enabled`.
	 * Additionally checks Theme Builder permission for Theme Builder layout post types.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_vb_enabled(): bool {
		// Check Theme Builder permission for Theme Builder layout post types (header, footer, body).
		// This prevents Visual Builder from loading on Theme Builder layout post types if user doesn't have permission.
		global $post;
		if ( $post && isset( $post->post_type ) ) {
			$post_type = $post->post_type;
			if ( in_array( $post_type, et_theme_builder_get_layout_post_types(), true ) && ! et_pb_is_allowed( 'theme_builder' ) ) {
				return false;
			}
		}

		$is_vb_enabled = et_core_is_fb_enabled();

		/**
		 * Filter whether Visual Builder is considered enabled for the current request.
		 *
		 * @since ??
		 *
		 * @param bool $is_vb_enabled Whether Visual Builder is enabled.
		 */
		return apply_filters( 'divi_framework_conditions_is_vb_enabled', $is_vb_enabled );
	}

	/**
	 * Check if the current screen is the Theme Builder administration screen.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_tb_admin_screen() {
		return et_builder_is_tb_admin_screen();
	}

	/**
	 * Check if the current screen is a Gutenberg block editor.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_block_editor() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		return $screen && $screen->is_block_editor;
	}

	/**
	 * Check if the current screen is a WP post edit screen.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_wp_post_edit_screen() {
		global $pagenow;

		return in_array( $pagenow, [ 'edit.php', 'post.php', 'post-new.php' ], true );
	}

	/**
	 * Check if current screen is custom post type page.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_custom_post_type() {
		static $is_custom_post_type = null;

		// Cache the first valid result to avoid context switching issues.
		if ( null !== $is_custom_post_type && ! self::is_test_env() ) {
			return $is_custom_post_type;
		}

		// Default to false if no post type is found.
		$post_type = false;

		// WooCommerce 11+ returns the Shop page WP_Post from get_queried_object().
		// Treat an active Shop request as the product archive so CPT style wrapping matches pre-WC 11 behavior.
		// Same guard as ET_Theme_Builder_Request::from_current().
		if (
			class_exists( 'WooCommerce' )
			&& function_exists( 'is_shop' )
			&& function_exists( 'wc_get_page_id' )
			&& is_shop()
			&& wc_get_page_id( 'shop' ) > 0
		) {
			$post_type = 'product';
		} else {
			// Use queried object as reference of current page's $post object instead of global $post because in loop,
			// global $post refers to current item in the loop thus any TB elements in the loop will be considered
			// as custom post type (eg any header will have `et_header_type` for its $post->post_type value).
			$queried_object = get_queried_object();

			// Handle different types of queried objects.
			if ( $queried_object instanceof \WP_Post_Type ) {
				// Post type archive (e.g., product archive).
				$post_type = $queried_object->name;
			} elseif ( $queried_object && isset( $queried_object->post_type ) ) {
				// Regular post.
				$post_type = $queried_object->post_type;
			} elseif ( $queried_object instanceof \WP_Term ) {
				// Taxonomy archive - check if it belongs to any custom post types.
				$taxonomy = get_taxonomy( $queried_object->taxonomy );
				if ( $taxonomy && ! empty( $taxonomy->object_type ) ) {
					foreach ( $taxonomy->object_type as $object_post_type ) {
						if ( et_builder_is_post_type_custom( $object_post_type ) ) {
							$post_type = $object_post_type;
							break;
						}
					}
				}
			}
		}

		$is_custom_post_type = et_builder_is_post_type_custom( $post_type );

		return $is_custom_post_type;
	}

	/**
	 * Determine if debug mode is enabled.
	 *
	 * This function checks the constant `ET_DEBUG`.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_debug_mode() {
		return defined( 'ET_DEBUG' ) && (bool) ET_DEBUG;
	}

	/**
	 * Check whether D5 is enabled
	 *
	 * This function is proxy function of existing D4 function `et_builder_d5_enabled`.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_d5_enabled() {
		$is_d5_enabled = et_builder_d5_enabled();

		/**
		 * Filter whether Divi 5 is considered enabled for the current request.
		 *
		 * Mirrors `divi_framework_conditions_is_vb_enabled` so callers (and tests)
		 * can override the memoized `et_builder_d5_enabled()` result in-process.
		 *
		 * @since ??
		 *
		 * @param bool $is_d5_enabled Whether Divi 5 is enabled.
		 */
		return apply_filters( 'divi_framework_conditions_is_d5_enabled', $is_d5_enabled );
	}

	// TODO feat(D5, Shortcode) Move this trait into single Shortcode class under Framework https://github.com/elegantthemes/Divi/issues/31411.
	/**
	 * Check if content contains D4 shortcode.
	 *
	 * If this function is called with `content=null`, and the current query is for an existing single post of any post
	 * type (`is_singular=true`), then the function will attempt to get the raw post content via `get_post_field` using
	 * `get_the_ID` to get the post ID.
	 *
	 * @link https://developer.wordpress.org/reference/functions/is_singular/
	 * @link https://developer.wordpress.org/reference/functions/get_post_field/
	 * @link https://developer.wordpress.org/reference/functions/get_the_id/
	 *
	 * @since ??
	 *
	 * @param string $shortcode_suffix Optional. Shortcode tag suffix to check. Default empty string.
	 * @param string $content          Optional. Content to check. Default `null`.
	 *
	 * @return bool
	 */
	public static function has_shortcode( $shortcode_suffix = '', $content = null ) {
		if ( null === $content && is_singular() ) {
			$content = get_post_field( 'post_content', get_the_ID(), 'raw' );
		}

		if ( ! is_string( $content ) ) {
			return false;
		}

		/**
		 * Regex pattern to match paired and self-closing shortcodes with prefix `et_pb_`.
		 *
		 * Test regex https://regex101.com/r/XfqdEC/1
		 */
		$regex_pattern = '/\[et_pb_' . $shortcode_suffix . '[^\]]*\/?\]/';

		return (bool) ( preg_match( $regex_pattern, $content ) );
	}

	// phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
	// TODO feat(D5, Theme Builder):  This function is proxy function of existing D4
	// function `et_builder_tb_enabled`. Replace `et_builder_tb_enabled` once the Theme
	// Builder is implemented in D5.
	// @link https://github.com/elegantthemes/Divi/issues/25149.
	/**
	 * Check whether the Visual Builder is loaded through the Theme Builder.
	 *
	 * This function is proxy function of existing D4 function `et_builder_tb_enabled
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_tb_enabled() {
		return et_builder_tb_enabled();
	}

	/**
	 * Check whether the current request is in Theme Builder context.
	 *
	 * This extends Theme Builder detection to REST contexts where `et_tb` query parameter
	 * is unavailable by falling back to current post type inspection.
	 *
	 * @since ??
	 *
	 * @param int $post_id Optional. Current post ID. Default `0`.
	 *
	 * @return bool
	 */
	public static function is_tb_context( int $post_id = 0 ): bool {
		if ( self::is_tb_enabled() ) {
			return true;
		}

		if ( 0 < $post_id ) {
			$post_type = get_post_type( $post_id );

			return et_theme_builder_is_layout_post_type( $post_type );
		}

		return false;
	}

	/**
	 * Check if the current page is app window of visual builder page.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_vb_app_window() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verification is not required here.
		return self::is_vb_enabled() && isset( $_GET['app_window'] ) && '1' === $_GET['app_window'];
	}

	/**
	 * Check if the current page is top window of visual builder page.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_vb_top_window() {
		return self::is_vb_enabled() && ! self::is_vb_app_window();
	}

	/**
	 * Determine whether current request is running inside Visual Builder context.
	 *
	 * @since 5.0.0
	 *
	 * @return bool
	 */
	public static function is_visual_builder_context(): bool {
		return self::is_vb_enabled() || self::is_vb_top_window() || self::is_vb_app_window();
	}

	/**
	 * Check if this is a WP REST API request.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_rest_api_request() {
		// phpcs:ignore ET.ValidatedSanitizedInput -- This is just check, therefore nonce verification not required.
		$request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );

		/*
		 * Check if the request URI contains the REST prefix.
		 * Handle both root installations (/wp-json/...) and subdirectory installations (/subdir/wp-json/...)
		 * Example: https://regex101.com/r/xdgAIG/1
		 */
		$rest_prefix_check = preg_match( '#/' . preg_quote( rest_get_url_prefix(), '#' ) . '(?:/|$)#', $request_uri );

		// Check if the request URI contains ?rest_route=/.
		$rest_route_check = str_contains( $request_uri, '?rest_route=/' );

		/*
		 * The REST_REQUEST constant is defined in `parse_request` action only, which is why we're looking into
		 * REQUEST_URI as the fallback checks.
		 */
		$result = (
			( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
			$rest_prefix_check ||
			$rest_route_check
		);

		return $result;
	}

	/**
	 * Check if were in Unit Test Environment.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_test_env() {
		return defined( 'WP_TESTS_DOMAIN' );
	}

	/**
	 * Check if this is a WP AJAX request.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_ajax_request() {
		return defined( 'DOING_AJAX' ) && DOING_AJAX;
	}

	/**
	 * Check if this is a WP Cron request.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_cron_request() {
		return defined( 'DOING_CRON' ) && DOING_CRON;
	}

	/**
	 * Check if this is a JSON request.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_json_request() {
		return function_exists( 'wp_is_json_request' ) && wp_is_json_request();
	}

	/**
	 * Check if this is a WP Admin request.
	 */
	public static function is_admin_request() {
		return is_admin();
	}

	/**
	 * Check if this is the Role Editor admin page.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_role_editor_page() {
		// phpcs:disable WordPress.Security.NonceVerification -- Only checking page parameter, no state changes.
		return is_admin() && isset( $_GET['page'] ) && 'et_divi_role_editor' === $_GET['page'];
		// phpcs:enable WordPress.Security.NonceVerification
	}

	/**
	 * Check whether to register all D5 modules.
	 *
	 * This function is used to determine whether to register all D5 modules on this page load.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function should_register_all_d5_modules() {
		$should_register = false;

		// if this is the VB, then we should register all modules.
		if (
			self::is_vb_app_window()
			|| self::is_rest_api_request()
			|| self::is_test_env()
		) {
			$should_register = true;
		}

		// If this is the Role Editor admin page, we need all modules registered.
		// Not doing this will make official module doesn't appear in Role Editor's "Use Modules" permissions group.
		if ( self::is_role_editor_page() ) {
			$should_register = true;
		}

		// If this is an ajax request.
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$load_for_ajax_actions = [
				'et_core_portability_import',
				'et_d5_readiness_convert_d4_to_d5',
				'et_d5_readiness_get_result_list',
				'et_theme_builder_api_import_theme_builder',
				'et_theme_builder_api_import_theme_builder_step',
				'et_pb_submit_subscribe_form',
			];

			// phpcs:disable WordPress.Security.NonceVerification -- It just need to figure out if this correct ajax action.
			if ( ! empty( $_POST['action'] ) && in_array( $_POST['action'], $load_for_ajax_actions, true ) ) {
				$should_register = true;
			}
		}

		/**
		 * Filter whether to register all D5 modules.
		 *
		 * This filter is used to determine whether to register all D5 modules on this page load.
		 *
		 * @since ??
		 *
		 * @param bool $should_register Default is `false`.
		 */
		return apply_filters( 'divi_module_library_should_register_all_d5_modules', $should_register );
	}

	/**
	 * Check if WooCommerce plugin is enabled.
	 */
	public static function is_woocommerce_enabled(): bool {
		return class_exists( 'WooCommerce', false );
	}

	/**
	 * Check if there is active Divi 4 DiviExtension that is not compatible with Divi 5.
	 *
	 * This function is used to determine whether we need to initialize DiviExtensions class.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function has_divi_4_only_extension(): bool {
		// Create an instance of Plugin_Hooks_Check.
		$plugin_hooks_check = new PluginHooksCheck();

		// Run the check.
		$plugin_hooks_check->run_check();

		// Return true if D4 extension is detected.
		return $plugin_hooks_check->detected();
	}

	/**
	 * Check if this is the widgets.php admin page.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_widgets_admin_page(): bool {
		global $hook_suffix;

		return 'widgets.php' === $hook_suffix;
	}

	/**
	 * Check if the current context is a frontend render where content migrations should run.
	 *
	 * This helper method consolidates the logic for determining whether content migrations
	 * should be executed. It returns true only when:
	 * - D5 is enabled AND Visual Builder is not active (frontend rendering)
	 * - AND none of the following admin/special contexts are active:
	 *   - Theme Builder admin screen
	 *   - WordPress post edit screen
	 *   - Visual Builder app window
	 *   - AJAX request
	 *   - REST API request
	 *
	 * This is primarily used by migration classes to determine if they should process
	 * content on the frontend.
	 *
	 * @since ??
	 *
	 * @return bool True if migrations should run in the current context, false otherwise.
	 */
	public static function is_fe_and_should_migrate_content(): bool {
		return ( self::is_d5_enabled() && ! self::is_vb_enabled() )
			&& ! self::is_tb_admin_screen()
			&& ! self::is_wp_post_edit_screen()
			&& ! self::is_vb_app_window()
			&& ! self::is_ajax_request()
			&& ! self::is_rest_api_request();
	}

	/**
	 * Determine whether current request is non-singular and uses Theme Builder templates.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_non_singular_theme_builder_context(): bool {
		// Only run if D5 is enabled.
		if ( ! self::is_d5_enabled() ) {
			return false;
		}

		// Only for non-singular pages.
		if ( is_singular() ) {
			return false;
		}

		// Only if Theme Builder templates are active.
		if ( ! et_fb_is_theme_builder_used_on_page() ) {
			return false;
		}

		return true;
	}

	/**
	 * Determine whether current request should run non-singular Theme Builder VB hooks.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_non_singular_theme_builder_vb_context(): bool {
		return self::is_non_singular_theme_builder_context() && self::is_vb_enabled();
	}

	/**
	 * Check if the current browser is running on iOS WebKit.
	 *
	 * Detects all iOS browsers (Safari, Chrome iOS, Firefox iOS, Edge iOS, etc.). All iOS browsers use WebKit engine
	 * and do not properly support CSS parallax (background-attachment: fixed), so this utility can be used to detect
	 * when JavaScript parallax should be forced instead.
	 *
	 * @since ??
	 *
	 * @param string|null $useragent Optional. User agent string. If not provided, uses $_SERVER['HTTP_USER_AGENT'].
	 *
	 * @return bool True if browser is running on iOS (iPhone/iPad with WebKit), false otherwise.
	 */
	public static function is_ios_webkit( ?string $useragent = null ): bool {
		if ( null === $useragent ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- User Agent is not stored or displayed therefore XSS safe.
			$useragent = ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
		}

		// Detect iOS WebKit: iPhone/iPad with AppleWebKit engine. All iOS browsers (Safari, Chrome iOS, Firefox iOS, etc.)
		// use WebKit and have the same background-attachment: fixed limitation.
		return 1 === preg_match( '/AppleWebKit/i', $useragent ) && 1 === preg_match( '/iphone|ipad/i', $useragent );
	}
}
