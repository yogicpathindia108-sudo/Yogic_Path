<?php
/**
 * Speculation Rules API handler for Visual Builder prerendering.
 *
 * @package Divi
 * @since 5.0.0
 */

namespace ET\Builder\VisualBuilder\Assets;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\VisualBuilder\AppPreferences\AppPreferences;

/**
 * SpeculationRules class.
 *
 * Handles adding Speculation Rules API for prerendering Visual Builder URLs.
 * Prerenders once per user per Divi version to cache Visual Builder assets in the browser.
 *
 * @since 5.0.0
 */
class SpeculationRules implements DependencyInterface {

	/**
	 * Custom post type name for speculation prerendering.
	 *
	 * @since 5.0.0
	 *
	 * @var string
	 */
	private const POST_TYPE = '_et_pb_speculation';

	/**
	 * User meta key for tracking prerendered version.
	 *
	 * @since 5.0.0
	 *
	 * @var string
	 */
	private const USER_META_KEY = '_et_vb_prerendered_version';

	/**
	 * Shared script handle prefix.
	 *
	 * @since 5.0.0
	 *
	 * @var string
	 */
	private const SCRIPT_HANDLE_PREFIX = 'et-speculation-rules-';

	/**
	 * Maximum total prerenders for wp-admin post/page list hover flow.
	 *
	 * @since 5.0.0
	 *
	 * @var int
	 */
	private const POST_LIST_MAX_TOTAL_PRERENDERS = 5;

	/**
	 * Guard to avoid duplicate hook registration.
	 *
	 * @var bool
	 */
	private static $_is_loaded = false;

	/**
	 * Cached prerendering preference for current request.
	 *
	 * @var null|bool
	 */
	private static $_is_prerendering_enabled = null;

	/**
	 * Cached request exclusion result for current request.
	 *
	 * @var null|bool
	 */
	private static $_is_request_excluded = null;

	/**
	 * Tracks whether version-based speculation was enqueued this request.
	 *
	 * @var bool
	 */
	private static $_did_enqueue_version_based_rules = false;

	/**
	 * Method that is automatically loaded by class which implements `DependencyInterface`.
	 *
	 * @since 5.0.0
	 */
	public function load() {
		if ( self::$_is_loaded ) {
			return;
		}
		self::$_is_loaded = true;

		// Register custom post type for speculation prerendering.
		add_action( 'init', [ self::class, 'register_post_type' ] );

		// Add speculation rules on frontend (when not in Visual Builder).
		add_action( 'wp_enqueue_scripts', [ self::class, 'maybe_prerender_vb_assets' ], 20 );

		// Add speculation rules in WordPress editor.
		add_action( 'admin_enqueue_scripts', [ self::class, 'maybe_prerender_vb_assets' ], 20 );

		// Add speculation rules for admin bar "Edit With Divi" button.
		// Use wp_enqueue_scripts with late priority to ensure admin bar is registered.
		add_action( 'wp_enqueue_scripts', [ self::class, 'maybe_prerender_admin_bar_link' ], 999 );

		// Add speculation rules for post/page list "Edit With Divi" links.
		add_action( 'admin_enqueue_scripts', [ self::class, 'maybe_prerender_post_list_links' ], 20 );

		// Add No-Vary-Search header to allow prerender activation across query param differences.
		add_action( 'send_headers', [ self::class, 'add_no_vary_search_header' ] );
	}

	/**
	 * Register custom post type for speculation prerendering.
	 *
	 * Registers a hidden custom post type used exclusively for prerendering
	 * Visual Builder assets. This post type is not visible in the admin UI.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'              => [
					'name'          => __( 'Speculation Rules', 'et_builder_5' ),
					'singular_name' => __( 'Speculation Rule', 'et_builder_5' ),
				],
				'public'              => false,
				'publicly_queryable'  => true,  // Allow frontend queries for prerendering.
				'show_ui'             => false, // Hide from admin UI.
				'show_in_menu'        => false, // Hide from admin menu.
				'show_in_nav_menus'   => false, // Hide from navigation menus.
				'show_in_admin_bar'   => false, // Hide from admin bar.
				'show_in_rest'        => false, // Hide from REST API.
				'exclude_from_search' => true,  // Exclude from search results.
				'has_archive'         => false,
				'rewrite'             => false,
				'capability_type'     => 'page',
				'supports'            => [ 'title', 'editor', 'author' ],
			]
		);
	}

	/**
	 * Check if user has already prerendered for current Divi version.
	 *
	 * @since 5.0.0
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool True if already prerendered for current version, false otherwise.
	 */
	private static function _has_prerendered_for_version( int $user_id ): bool {
		$prerendered_version = get_user_meta( $user_id, self::USER_META_KEY, true );

		if ( empty( $prerendered_version ) ) {
			return false;
		}

		// Check if prerendered version matches current Divi version.
		return self::_get_current_version() === $prerendered_version;
	}

	/**
	 * Mark user as having prerendered for current Divi version.
	 *
	 * @since 5.0.0
	 *
	 * @param int $user_id User ID.
	 *
	 * @return void
	 */
	private static function _mark_prerendered( int $user_id ): void {
		update_user_meta( $user_id, self::USER_META_KEY, self::_get_current_version() );
	}

	/**
	 * Get current Divi version.
	 *
	 * @since 5.0.0
	 *
	 * @return string Current Divi version.
	 */
	private static function _get_current_version(): string {
		if ( ! defined( 'ET_BUILDER_VERSION' ) ) {
			return '';
		}

		return ET_BUILDER_VERSION;
	}

	/**
	 * Determine whether current request should be excluded from prerender flow.
	 *
	 * Excludes non-page utility requests such as Chrome DevTools probes under
	 * `/.well-known/*` which can otherwise consume once-per-version prerender state.
	 *
	 * @since 5.0.0
	 *
	 * @return bool
	 */
	private static function _is_request_excluded_from_prerender(): bool {
		if ( null !== self::$_is_request_excluded ) {
			return self::$_is_request_excluded;
		}

		$request_uri = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL );

		if ( ! is_string( $request_uri ) || '' === $request_uri ) {
			self::$_is_request_excluded = false;
			return self::$_is_request_excluded;
		}

		$path = wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			self::$_is_request_excluded = false;
			return self::$_is_request_excluded;
		}

		// Exclude only the known Chrome DevTools probe endpoint.
		self::$_is_request_excluded = '/.well-known/appspecific/com.chrome.devtools.json' === $path;
		return self::$_is_request_excluded;
	}

	/**
	 * Determine whether current request context should skip speculation logic.
	 *
	 * @since 5.0.0
	 *
	 * @return bool
	 */
	private static function _is_invalid_request_context(): bool {
		if ( Conditions::is_ajax_request() ) {
			return true;
		}

		if ( Conditions::is_cron_request() ) {
			return true;
		}

		if ( Conditions::is_rest_api_request() ) {
			return true;
		}

		if ( Conditions::is_json_request() ) {
			return true;
		}

		$is_favicon_request = function_exists( 'is_favicon' ) && is_favicon();

		return is_feed() || is_embed() || is_robots() || is_trackback() || $is_favicon_request;
	}

	/**
	 * Get cached prerendering preference for current request.
	 *
	 * @since 5.0.0
	 *
	 * @return bool
	 */
	private static function _is_prerendering_enabled(): bool {
		if ( null === self::$_is_prerendering_enabled ) {
			self::$_is_prerendering_enabled = AppPreferences::is_prerendering_enabled();
		}

		return self::$_is_prerendering_enabled;
	}

	/**
	 * Enqueue speculation rules script and localize config payload.
	 *
	 * @since 5.0.0
	 *
	 * @param string $handle_suffix Unique script handle suffix.
	 * @param array  $config Script config passed to diviSpeculationRules.
	 *
	 * @return void
	 */
	private static function _enqueue_speculation_rules_script( string $handle_suffix, array $config ): void {
		$handle = self::SCRIPT_HANDLE_PREFIX . $handle_suffix;

		wp_enqueue_script(
			$handle,
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-speculation-rules.js',
			[],
			ET_BUILDER_PRODUCT_VERSION,
			true
		);

		wp_localize_script(
			$handle,
			'diviSpeculationRules',
			$config
		);
	}


	/**
	 * Maybe prerender Visual Builder assets.
	 *
	 * Prerenders once per user per Divi version to cache Visual Builder assets.
	 * Uses the home page URL (which always exists) to ensure speculation works
	 * even on new sites with no posts.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public static function maybe_prerender_vb_assets(): void {
		if ( self::_is_request_excluded_from_prerender() || self::_is_invalid_request_context() ) {
			return;
		}

		// Don't add on Visual Builder pages.
		if ( Conditions::is_visual_builder_context() ) {
			return;
		}

		// Only add for logged-in users.
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Check if prerendering is enabled.
		if ( ! self::_is_prerendering_enabled() ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		// Check if already prerendered for current version.
		if ( self::_has_prerendered_for_version( $user_id ) ) {
			return;
		}

		// Get or create a reusable post for prerendering.
		$post_id = self::_get_or_create_prerender_post();

		if ( ! $post_id ) {
			return;
		}

		// Build Visual Builder URL.
		$post_url = get_permalink( $post_id );
		$vb_url   = add_query_arg( 'et_fb', '1', $post_url );

		if ( empty( $vb_url ) ) {
			return;
		}

		self::_enqueue_speculation_rules_script(
			'admin-prerender',
			[
				'urls'            => [ $vb_url ],
				'speculationType' => 'prerender',
				'eagerness'       => 'immediate',
				'dataAttribute'   => 'data-vb-admin-prerender',
			]
		);
		self::$_did_enqueue_version_based_rules = true;

		// Mark as prerendered for this version.
		self::_mark_prerendered( $user_id );
	}

	/**
	 * Maybe prerender Visual Builder URL from admin bar "Edit With Divi" button.
	 *
	 * Enqueues speculation rules with proximity-based prerendering to prerender the Visual Builder URL
	 * when the mouse cursor gets within the proximity threshold of the admin bar "Edit With Divi" link
	 * on the frontend (but not when already in the Visual Builder).
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public static function maybe_prerender_admin_bar_link(): void {
		// Avoid enqueuing the same script file twice in one request.
		// This only skips when version-based rules already enqueued in this request.
		if ( self::$_did_enqueue_version_based_rules ) {
			return;
		}

		if ( self::_is_request_excluded_from_prerender() || self::_is_invalid_request_context() ) {
			return;
		}

		// Only proceed if we're on the frontend and the admin bar is showing.
		if ( is_admin() || ! is_admin_bar_showing() ) {
			return;
		}

		// Don't prerender if we're already in the Visual Builder.
		if ( Conditions::is_visual_builder_context() ) {
			return;
		}

		// Only prerender if user is logged in.
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Check if prerendering is enabled.
		if ( ! self::_is_prerendering_enabled() ) {
			return;
		}

		// Only prerender if user has permission to use the Visual Builder.
		if ( ! et_pb_is_allowed( 'use_visual_builder' ) ) {
			return;
		}

		// Check if user can edit the current post (if on a singular post).
		if ( is_singular() ) {
			$post_id = get_the_ID();
			if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			// Only prerender when Divi is already enabled for this post.
			if ( ! et_builder_enabled_for_post( $post_id ) ) {
				return;
			}
		}

		// Pass proximity-based configuration.
		// Selector targets admin bar links containing the Visual Builder query parameter.
		self::_enqueue_speculation_rules_script(
			'admin-bar',
			[
				'hoverSelector'      => '#wpadminbar a[href*="et_fb=1"]',
				'proximityThreshold' => 600, // pixels.
				'eagerness'          => 'immediate',
				'dataAttribute'      => 'data-vb-admin-bar-prerender',
			]
		);
	}

	/**
	 * Maybe prerender Visual Builder URLs from post/page list "Edit With Divi" links.
	 *
	 * Enqueues speculation rules with document selector to prerender Visual Builder URLs
	 * when the mouse cursor gets within the proximity threshold of "Edit With Divi" links
	 * in the post/page list tables.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public static function maybe_prerender_post_list_links(): void {
		// Avoid enqueuing the same script file twice in one request.
		// This only skips when version-based rules already enqueued in this request.
		if ( self::$_did_enqueue_version_based_rules ) {
			return;
		}

		// Only proceed if we're on a post list screen in wp-admin.
		$screen = get_current_screen();

		if ( ! $screen || 'edit' !== $screen->base ) {
			return;
		}

		// Only proceed if user has permission to use Visual Builder.
		if ( ! et_pb_is_allowed( 'use_visual_builder' ) ) {
			return;
		}

		// Check if prerendering is enabled.
		if ( ! self::_is_prerendering_enabled() ) {
			return;
		}

		// Pass hover-based configuration.
		self::_enqueue_speculation_rules_script(
			'post-list',
			[
				'hoverSelector'        => '.row-actions .divi a',
				'proximityThreshold'   => 100, // pixels.
				'hoverDelayMs'         => 200, // milliseconds.
				'maxTotalPrerenders'   => self::POST_LIST_MAX_TOTAL_PRERENDERS,
				'requirePostStateDivi' => true,
				'eagerness'            => 'immediate',
				'dataAttribute'        => 'data-vb-post-list-prerender',
			]
		);
	}

	/**
	 * Get or create a reusable post for prerendering Visual Builder assets.
	 *
	 * Creates a single hidden post using a custom post type specifically for prerendering.
	 * The post is reused across all users and versions to avoid database clutter.
	 * The post must exist so the browser can successfully prerender the URL.
	 *
	 * @since 5.0.0
	 *
	 * @return int|false Post ID on success, false on failure.
	 */
	private static function _get_or_create_prerender_post() {
		// Ensure post type is registered before querying.
		// This handles edge cases where this might be called before 'init' hook.
		if ( ! post_type_exists( self::POST_TYPE ) ) {
			self::register_post_type();
		}

		// Check if prerender post already exists.
		$existing_post = get_posts(
			[
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'publish',
				'numberposts'            => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'suppress_filters'       => true,
			]
		);

		if ( ! empty( $existing_post ) ) {
			return $existing_post[0];
		}

		// Create a new hidden post for prerendering using custom post type.
		$post_id = wp_insert_post(
			[
				'post_title'   => 'Visual Builder Asset Prerender',
				'post_content' => '<!-- wp:divi/section {"builderVersion":"' . ET_BUILDER_VERSION . '"} /-->',
				'post_status'  => 'publish',
				'post_type'    => self::POST_TYPE,
				'post_author'  => get_current_user_id(),
			]
		);

		if ( is_wp_error( $post_id ) ) {
			return false;
		}

		// Enable Divi Builder for this post.
		update_post_meta( $post_id, '_et_pb_use_builder', 'on' );
		update_post_meta( $post_id, '_et_pb_page_layout', 'et_no_sidebar' );

		return $post_id;
	}

	/**
	 * Add No-Vary-Search HTTP header to allow prerender activation.
	 *
	 * The No-Vary-Search header tells Chrome that the page content doesn't vary based
	 * on certain query parameters.
	 *
	 * Important: `et_fb` must NOT be ignored because it changes the response context
	 * (Visual Builder vs frontend). Ignoring it can cause unrelated links (including
	 * empty href links that resolve to the current path) to activate a Visual Builder
	 * prerender unexpectedly.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public static function add_no_vary_search_header(): void {
		// Don't add headers in test environment (headers may already be sent).
		if ( Conditions::is_test_env() ) {
			return;
		}

		// Only add header on frontend pages (not admin, not AJAX, not REST API).
		if ( Conditions::is_admin_request() || Conditions::is_ajax_request() || Conditions::is_rest_api_request() ) {
			return;
		}

		// Only add for pages that support the builder.
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_the_ID();

		if ( ! $post_id ) {
			return;
		}

		// Check if Visual Builder is enabled for this post type.
		if ( ! et_builder_enabled_for_post_type( get_post_type( $post_id ) ) ) {
			return;
		}

		// Don't send headers if they've already been sent (safety check).
		if ( headers_sent() ) {
			return;
		}

		// Add No-Vary-Search header to ignore only PageSpeed query parameter.
		// Do not ignore et_fb because it changes page context and content.
		// Syntax: params=("param1") tells browser this param does not affect content.
		header( 'No-Vary-Search: params=("PageSpeed")' );
	}
}
