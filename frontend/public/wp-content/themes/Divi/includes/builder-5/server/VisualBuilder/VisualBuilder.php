<?php
/**
 * VisualBuilder: Visual Builder class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\DependencyManagement\DependencyTree;
use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Framework\Settings\PageSettings;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\FrontEnd\Module\ScriptData;
use ET\Builder\VisualBuilder\Assets\AssetsUtility;
use ET\Builder\VisualBuilder\Assets\LazyAssetLoader;
use ET\Builder\VisualBuilder\Assets\PackageBuildManager;
use ET\Builder\VisualBuilder\Assets\SpeculationRules;
use ET\Builder\VisualBuilder\ClassicEditor\ClassicEditor;
use ET\Builder\VisualBuilder\Hooks\HooksRegistration;
use ET\Builder\VisualBuilder\REST\RESTRegistration;
use ET\Builder\VisualBuilder\SettingsData\SettingsData;
use ET\Builder\VisualBuilder\TopWindow;

/**
 * VisualBuilder class.
 *
 * This class essentially initiates the Visual Builder on the backend and loads all the functionalities needed for
 * Visual Builder to work.
 *
 * It accepts a `DependencyTree` on construction which tells `VisualBuilder` its dependencies and the priorities to
 * load them.
 *
 * @since ??
 */
class VisualBuilder {
	/**
	 * App-window first-render signal used for lazy asset loading coordination.
	 *
	 * @var string
	 */
	private const APP_WINDOW_FIRST_RENDER_SIGNAL = 'divi:app-window:first-render';
	private const APP_WINDOW_FIRST_RENDER_FLAG   = '__diviAppWindowFirstRenderDone';

	/**
	 * Stores dependencies that were passed to constructor.
	 *
	 * @since ??
	 *
	 * @var DependencyTree Dependency tree for VisualBuilder to load.
	 */
	private $_dependency_tree;

	/**
	 * Create an instance of the VisualBuilder class.
	 *
	 * Constructs class and sets dependencies for `VisualBuilder` to load.
	 *
	 * @since ??
	 *
	 * @param DependencyTree $dependency_tree Dependency tree for VisualBuilder to load.
	 */
	public function __construct( DependencyTree $dependency_tree ) {
		$this->_dependency_tree = $dependency_tree;
	}

	/**
	 * Load and initialize the Divi Builder if `et_builder_d5_enabled()` is true.
	 *
	 * If D5 is enabled in settings, the Visual Builder will be loaded, otherwise D4 is loaded.
	 *
	 * Checks if the content is built with Divi, if the Divi 5 Visual Builder should be loaded,
	 * and if the Classic Editor is enabled. If the content meets all the conditions, the Visual Builder
	 * will be forced to be used for the Classic Editor in the backend. The D4 default actions are removed,
	 * and new actions are added to include CSS and HTML prefixes and suffixes for the Visual Builder.
	 *
	 * @since ??
	 *
	 * @return void
	 *
	 * @example:
	 * ```php
	 *   // This function is typically called during the initialization of the Divi Builder,
	 *   // to set up the necessary actions and configurations for the Visual Builder.
	 *
	 *   VisualBuilder::initialize();
	 * ```
	 */
	public function initialize(): void {
		global $typenow, $pagenow;

		// Is this content built with Divi?
		$is_built_with_divi = $this->is_built_with_divi();

		// Should we load the Divi 5 Visual Builder?
		$load_d5 = $this->should_load_d5_visual_builder();

		// Determine post type: Use WordPress's $typenow global if available (handles default 'post' type correctly),
		// otherwise fall back to $_GET-based detection. For new posts, default to 'post' if not specified.
		$post_type = $this->_get_current_post_type();

		// Define whether the Divi Classic Editor is enabled.
		$divi_classic_editor_enabled = false;
		if ( class_exists( 'ET\\Builder\\VisualBuilder\\ClassicEditor\\ClassicEditor' ) ) {
			$divi_classic_editor_enabled = ClassicEditor::is_enabled();
		}

		// If the WordPress Classic Editor Plugin exists, define whether it is used on the current page.
		$wp_classic_editor_enabled = false;
		if ( class_exists( 'Classic_Editor' ) ) {
			$wp_classic_editor_replace_option = get_option( 'classic-editor-replace' );

			$wp_classic_editor_block_settings = [ 'block', 'no-replace' ];

			// If the option value is not set to 'block' or 'no-replace', then the Classic Editor is enabled here.
			$wp_classic_editor_enabled = ! in_array( $wp_classic_editor_replace_option, $wp_classic_editor_block_settings, true );
		}

		// Check if post type is `et_pb_layout` or WooCommerce `product` because these use Classic Editor by default.
		$is_et_pb_layout_post_type        = ET_BUILDER_LAYOUT_POST_TYPE === $post_type;
		$is_woocommerce_product_post_type = et_is_woocommerce_plugin_active() && 'product' === $post_type;
		$is_classic_editor_post_type      = $is_et_pb_layout_post_type || $is_woocommerce_product_post_type;

		// Check if either Divi Classic Editor or WordPress Classic Editor is enabled.
		$classic_editor_enabled = $divi_classic_editor_enabled || $wp_classic_editor_enabled || $is_classic_editor_post_type;

		// If we are in the classic Editor, remove unused D4 actions and add D5 Classic Editor CSS.
		if ( is_admin() && $classic_editor_enabled ) {
			// Add CSS & JS that styles the UI for switching between Divi and the Classic Editor.
			add_action( 'admin_enqueue_scripts', [ ClassicEditor::class, 'add_scripts' ] );
		}

		// If we are in the classic Editor and the builder is not enabled, add the Use The Divi Builder button.
		// Only show the button if the Divi Builder is enabled for this post type.
		// Delay post type check if wp_loaded hasn't fired yet to make sure all post types are registered.
		if ( is_admin() && ! $is_built_with_divi && $classic_editor_enabled ) {
			if ( ! did_action( 'wp_loaded' ) ) {
				// Delay the check until after all post types are registered.
				// Check $typenow inside the closure when wp_loaded fires (it will be set by then).
				add_action(
					'wp_loaded',
					function () {
						$post_type = $this->_get_current_post_type();

						if ( et_builder_enabled_for_post_type( $post_type ) ) {
							add_action( 'edit_form_after_title', [ ClassicEditor::class, 'html_enable_divi_button' ] );
						}
					}
				);

				return;
			}

			if ( et_builder_enabled_for_post_type( $post_type ) ) {
				add_action( 'edit_form_after_title', [ ClassicEditor::class, 'html_enable_divi_button' ] );

				return;
			}
		}

		// If we are in the Classic Editor and the builder is enabled, hide the Classic Editor and add the Divi Builder block.
		if ( is_admin() && $is_built_with_divi && $load_d5 && $classic_editor_enabled ) {
			// Remove the default D4 actions.
			ClassicEditor::remove_d4_actions();

			add_action( 'edit_form_after_title', [ ClassicEditor::class, 'html_prefix' ] );
			add_action( 'edit_form_after_editor', [ ClassicEditor::class, 'html_suffix' ] );

			return;
		}

		// phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
		// TODO feat(D5, Cache) Switching from D5 to D4 or the reverse might require some refreshes before the cache
		// is fully replaced. Try exiting Visual Builder to Front End then go back to Visual Builder.

		$this->_dependency_tree->load_dependencies();

		if ( $this->should_load_d5_visual_builder() ) {
			add_action( 'template_redirect', [ self::class, 'disable_app_window_admin_bar' ], -1 );
			add_action( 'et_fb_framework_loaded', [ $this, 'load_d5_visual_builder' ] );

			do_action( 'divi_visual_builder_initialize' );

			/**
			 * Fire et_fb_framework_loaded hook for D5 Visual Builder.
			 *
			 * D4 code in includes/builder/frontend-builder/init.php now early-returns (line 137)
			 * to prevent D4 VB from loading, which also prevents the hook from firing there.
			 * We need to fire it from D5 code so that D5 assets get enqueued.
			 *
			 * We use 'template_redirect' with priority 5 because:
			 * - It runs after the main query is set up (WP_Query is available).
			 * - It runs before wp_enqueue_scripts (priority 1), so D5 assets can be registered.
			 * - It only runs on frontend requests (not admin), matching VB frontend context.
			 */
			if ( ! is_admin() ) {
				add_action(
					'template_redirect',
					function () {
						if ( ! Conditions::is_visual_builder_context() ) {
							return;
						}

						// Fire the hook that triggers load_d5_visual_builder() callback.
						do_action( 'et_fb_framework_loaded' );
					},
					5
				);
			}
		}
	}

	/**
	 * Determine if the current content in the WordPress Admin is built using the Divi.
	 *
	 * This function checks if the current page is a WP-Admin "Add" or "Edit" page and determines if Divi
	 * has been used to build the content. It accesses the `$_GET` global variable to get the post ID and
	 * performs a check to verify if the content is built with Divi using the `et_pb_is_pagebuilder_used` function.
	 *
	 * @since ??
	 *
	 * @return bool Whether the current content is built with Divi (`true`) or not (`false`).
	 *
	 * @example:
	 * ```php
	 *   // This function can be used to conditionally load certain resources or configurations
	 *   // specific to Divi-built content. It can be called in different parts of the code to
	 *   // determine if Divi has been used to build the current page.
	 *
	 *   $is_built_with_divi = $this->is_built_with_divi();
	 *   if ( $is_built_with_divi ) {
	 *       // Perform specific actions for Divi-built content
	 *   } else {
	 *       // Perform actions for non-Divi built content
	 *   }
	 * ```
	 */
	public function is_built_with_divi(): bool {
		global $pagenow;

		$is_divi_page = false;
		$post_id      = 0;

		// Is this a WP-Admin "Add" or "Edit" page?
		$is_admin_add_or_edit_page = in_array( $pagenow, [ 'edit.php', 'post-new.php', 'post.php' ], true );

		if ( $is_admin_add_or_edit_page ) {
			// reason: Since we are accessing $_GET only for the comparision, nonce verification is not required.
			// phpcs:disable WordPress.Security.NonceVerification
			$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
			// phpcs:enable WordPress.Security.NonceVerification
		}

		if ( $post_id ) {
			// Has Divi has been used to build the page?
			$is_divi_page = et_pb_is_pagebuilder_used( $post_id );
		}

		return $is_divi_page;
	}

	/**
	 * Get the current post type for admin screens.
	 *
	 * Determines the post type by checking WordPress globals ($typenow, $pagenow)
	 * and $_GET parameters. Handles the default 'post' type case where WordPress
	 * omits it from URLs for new posts.
	 *
	 * @since ??
	 *
	 * @return string|false Post type string or false if unable to determine.
	 */
	private function _get_current_post_type() {
		global $typenow, $pagenow;

		// Determine post type: Use WordPress's $typenow global if available (handles default 'post' type correctly),
		// otherwise fall back to $_GET-based detection. For new posts, default to 'post' if not specified.
		// phpcs:disable WordPress.Security.NonceVerification -- Nonce verification is not required, we're only reading GET parameters for comparison, not changing state.
		if ( ! empty( $typenow ) ) {
			return $typenow;
		} elseif ( isset( $_GET['post'] ) ) {
			return get_post_type( intval( $_GET['post'] ) );
		} elseif ( isset( $_GET['post_type'] ) ) {
			return sanitize_key( $_GET['post_type'] );
		} elseif ( isset( $pagenow ) && 'post-new.php' === $pagenow ) {
			// For new posts, default to 'post' if not specified (matches Divi 4 pattern).
			return 'post';
		}
		// phpcs:enable WordPress.Security.NonceVerification

		return false;
	}

	/**
	 * Determine if the Divi 5 Visual Builder should be loaded.
	 *
	 * This function checks the URL parameters to determine if the Divi 5 Visual Builder should be loaded.
	 * It checks for the presence of the following parameters: `et_tb`, `et_bfb`, `et_block_layout_preview` in the URL.
	 * If any of these parameters are present with a value of '1', the function returns `true`, indicating that
	 * the Divi 5 Visual Builder should be loaded. Otherwise, it returns `false`.
	 *
	 * Note: This is a temporary solution to prevent D5 Visual Builder from loading on certain pages.
	 *
	 * @since ??
	 *
	 * @return bool Returns `true` if the Divi 5 Visual Builder should be loaded, `false` otherwise.
	 *
	 * @example:
	 * ```php
	 * if ( should_load_d5_visual_builder() ) {
	 *     // Load the Divi 5 Visual Builder
	 *     load_d5_visual_builder();
	 * }
	 * ```
	 */
	public function should_load_d5_visual_builder(): bool {
		// reason: Since we are accessing $_GET only for the comparision, nonce verification is not required.
		// phpcs:disable WordPress.Security.NonceVerification

		// Check for a declaration of BFB in the URL.
		$is_using_bfb = isset( $_GET['et_bfb'] ) && '1' === $_GET['et_bfb'];

		// Check for a declaration of the Layout Block in the URL.
		$is_using_layout_block = isset( $_GET['et_block_layout_preview'] ) && '1' === $_GET['et_block_layout_preview'];

		// phpcs:enable WordPress.Security.NonceVerification

		// Force D4 if any of the known exceptions are true.
		// Note: Layout Block now supports D5, so it's no longer forced to D4.
		$force_d4 = $is_using_bfb;

		// If D5 is enabled and we are not forcing D4, then we should load D5.
		if ( \et_builder_d5_enabled() && ! $force_d4 ) {
			return true;
		}

		// If D5 is disabled or we are forcing D4, then we should load D4.
		return false;
	}

	/**
	 * Load D5 Visual Builder and all its dependencies.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load_d5_visual_builder(): void {
		// Load Divi Cloud class to enable import and export functionality.
		if ( defined( 'ET_BUILDER_PLUGIN_ACTIVE' ) && defined( 'ET_BUILDER_PLUGIN_DIR' ) ) {
			require_once \ET_BUILDER_PLUGIN_DIR . '/cloud/cloud-app.php';
		} else {
			require_once get_template_directory() . '/cloud/cloud-app.php';
		}

		/**
		 * Since `wp-color-picker` and `wp-color-picker-alpha` need `wp-i18n` but don't declare it as their dependency,
		 * we'll manually enqueue it here.
		 *
		 * This is to be removed once `wp-color-picker` and `wp-color-picker-alpha` are removed from `visual-builder` dependencies
		 * in `GetPackageListTrait:get_package_list()`.
		 */
		wp_enqueue_script( 'wp-i18n' );

		// Disable D4 preboot script.
		remove_action( 'wp_head', 'et_builder_inject_preboot_script', 0 );

		// Dequeue D4 scripts.
		remove_action( 'wp_footer', 'et_fb_wp_footer' );

		// Injected script that is required early (loading external .js will be too late).
		add_action( 'wp_head', [ AssetsUtility::class, 'inject_preboot_script' ], 0 );

		// Injected style that is required early (loading external .css will be too late).
		add_action( 'wp_head', [ AssetsUtility::class, 'inject_preboot_style' ], 0 );

		// Always enforce HTTPS for Google Fonts URLs in Visual Builder contexts.
		add_filter( 'style_loader_src', [ AssetsUtility::class, 'force_https_google_fonts_src' ], PHP_INT_MAX, 2 );

		wp_register_script(
			'react-tiny-mce',
			ET_BUILDER_5_URI . '/visual-builder/assets/tinymce/tinymce.min.js',
			[],
			ET_BUILDER_VERSION,
			false
		);

		// WordPress editor/media stack for VB windows (getUserSetting, media modal, Divi media library). Before vendor + package enqueues.
		add_action( 'wp_enqueue_scripts', [ AssetsUtility::class, 'enqueue_vb_window_wordpress_editor_scripts' ], 5 );

		// Enqueue visual builder's core dependencies, which are built by WebPack as externals e.g. react, wp-data, wp-blocks.
		add_action( 'wp_enqueue_scripts', [ AssetsUtility::class, 'enqueue_visual_builder_dependencies' ] );

		// Enqueue visual builder's packages' styles and scripts.
		add_action( 'wp_enqueue_scripts', [ PackageBuildManager::class, 'enqueue_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ PackageBuildManager::class, 'enqueue_styles' ] );

		// Dequeue D4 assets (styles & scripts).
		remove_action( 'wp_enqueue_scripts', 'et_builder_enqueue_assets_main', 99999999 );

		// The front-end theme bootstrap is expensive and not required for the VB top window.
		// Keep it for app window because module edit views still rely on legacy globals it registers.
		if ( Conditions::is_vb_top_window() ) {
			remove_action( 'wp_enqueue_scripts', 'et_divi_load_scripts_styles', 10 );
			remove_action( 'wp_enqueue_scripts', 'et_builder_preprint_font', 10 );
			// Prevent legacy Divi font queue printers from enqueuing heading/body fonts in top window.
			remove_action( 'wp_head', 'et_builder_preprint_font', 0 );
			remove_action( 'wp_footer', 'et_builder_print_font', 10 );
		}

		/**
		 * Fix plugin conflicts for D5 Visual Builder.
		 *
		 * Disables Autoptimize plugin and WP Super Cache when D5 Visual Builder is loaded.
		 * These plugins can interfere with the Visual Builder's functionality.
		 */
		if ( function_exists( 'et_fb_fix_plugin_conflicts' ) ) {
			et_fb_fix_plugin_conflicts();
		}

		wp_enqueue_style( 'wp-color-picker' );

		// Enqueue cor font family, which is used for Visual Builder UI.
		et_core_load_main_fonts();

		// Dequeue scripts that are enqueued but not needed on top window.
		add_action( 'wp_enqueue_scripts', [ AssetsUtility::class, 'dequeue_top_window_early_scripts' ] );
		add_action( 'wp_footer', [ AssetsUtility::class, 'dequeue_top_window_late_scripts' ] );

		/**
		 * Validate enqueued scripts dependencies and trigger error if non-existing script is found.
		 *
		 * By default, WordPress lacks a validation mechanism for script dependencies. This leads to a silent failure
		 * and the script is not enqueued if any of its dependencies are missing. Given our extensive use of scripts
		 * a missing dependency can lead us down to the rabbit hole.
		 */
		add_action( 'wp_enqueue_scripts', [ AssetsUtility::class, 'validate_enqueue_script_dependencies' ], PHP_INT_MAX );

		/**
		 * Remove WP Admin Bar on the app window.
		 */
		if ( Conditions::is_vb_app_window() ) {
			self::disable_app_window_admin_bar();
			add_action( 'wp_footer', [ $this, 'enqueue_cloud_app_after_first_render' ], 1 );
			add_action( 'wp_footer', [ $this, 'enqueue_breakpoint_script_data' ], 11 );

			// Load Divi Cloud app after initial app window render to avoid blocking first paint.
		} else {
			// Ensure admin bar is always loaded in VB top window context for CSS visibility control.
			add_filter( 'show_admin_bar', '__return_true' );

			add_action( 'wp_footer', [ $this, 'enqueue_top_window_cloud_assets_after_app_ready' ], 1 );
		}

		/**
		 * Set editor available cookie for D5 Visual Builder.
		 *
		 * This cookie indicates that the Visual Builder is available for the current post.
		 * It is used for various frontend/backend integration purposes.
		 */
		global $post;
		$post_id = isset( $post->ID ) ? $post->ID : false;
		if ( ! headers_sent() && ! empty( $post_id ) ) {
			setcookie( 'et-editor-available-post-' . $post_id . '-fb', 'fb', time() + ( MINUTE_IN_SECONDS * 30 ), SITECOOKIEPATH, false, is_ssl() );
		}
	}

	/**
	 * Disable the WordPress admin bar before core initializes it for the VB app window.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function disable_app_window_admin_bar(): void {
		if ( ! Conditions::is_vb_app_window() ) {
			return;
		}

		add_filter( 'show_admin_bar', '__return_false' );
	}

	/**
	 * Enqueue breakpoint script data for Visual Builder app window.
	 *
	 * Breakpoint data must be available to frontend script-library bundles (for example
	 * interactions breakpoint triggers) in the VB app window without depending on builder
	 * stores or builder-only globals.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function enqueue_breakpoint_script_data(): void {
		if ( ! Conditions::is_vb_app_window() ) {
			return;
		}

		Breakpoint::set_script_data();
		ScriptData::enqueue_data( 'breakpoint' );
	}

	/**
	 * Get cache-buster value for lazy-loaded bundle URLs.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	private function _get_lazy_assets_cache_buster(): string {
		$et_debug     = defined( 'ET_DEBUG' ) && ET_DEBUG;
		$core_version = defined( 'ET_CORE_VERSION' ) ? (string) ET_CORE_VERSION : '';

		return (string) ( $et_debug ? wp_rand() / mt_getrandmax() : $core_version );
	}

	/**
	 * Get AI app script source and localized data for a specific window context.
	 *
	 * @since ??
	 *
	 * @param string $data_key Script data key (`data_app_window` or `data_top_window`).
	 *
	 * @return array
	 */
	private function _get_ai_lazy_asset_payload( string $data_key ): array {
		$ai_package = PackageBuildManager::get_package_build( 'et-ai-app' );
		$ai_script  = is_array( $ai_package['script'] ?? null ) ? $ai_package['script'] : [];
		$ai_src     = is_string( $ai_script['src'] ?? null ) ? $ai_script['src'] : '';
		$ai_data    = is_array( $ai_script[ $data_key ] ?? null ) ? $ai_script[ $data_key ] : [];
		$ai_version = is_string( $ai_package['version'] ?? null ) ? $ai_package['version'] : '';

		return [
			'src'  => $this->_add_version_query_arg( $ai_src, $ai_version ),
			'data' => $ai_data,
		];
	}

	/**
	 * Get package script source.
	 *
	 * @since ??
	 *
	 * @param string $package_name Divi package name.
	 *
	 * @return string
	 */
	private function _get_package_script_src( string $package_name ): string {
		$package = PackageBuildManager::get_package_build( $package_name );
		$script  = is_array( $package['script'] ?? null ) ? $package['script'] : [];
		$src     = is_string( $script['src'] ?? null ) ? $script['src'] : '';
		$version = is_string( $package['version'] ?? null ) ? $package['version'] : '';

		return $this->_add_version_query_arg( $src, $version );
	}

	/**
	 * Add version query parameter to a source URL.
	 *
	 * @since ??
	 *
	 * @param string $src     Source URL.
	 * @param string $version Asset version.
	 *
	 * @return string
	 */
	private function _add_version_query_arg( string $src, string $version ): string {
		if ( '' === $src || '' === $version ) {
			return $src;
		}

		return add_query_arg( 'ver', $version, $src );
	}

	/**
	 * Get package style source.
	 *
	 * @since ??
	 *
	 * @param string $package_name Divi package name.
	 *
	 * @return string
	 */
	private function _get_package_style_src( string $package_name ): string {
		$package = PackageBuildManager::get_package_build( $package_name );
		$style   = is_array( $package['style'] ?? null ) ? $package['style'] : [];

		return is_string( $style['src'] ?? null ) ? $style['src'] : '';
	}

	/**
	 * Get Divi Cloud app stylesheet source URL.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	private function _get_cloud_style_src(): string {
		$style_uri = \ET_Cloud_App::get_style_uri();
		return is_string( $style_uri ) ? $style_uri : '';
	}

	/**
	 * Build lazy-loader assets payload for cloud and AI bundles.
	 *
	 * @since ??
	 *
	 * @param string $cloud_script_id  Script element id used for cloud app bundle.
	 * @param string $cloud_script_src Cloud app bundle source URL.
	 * @param string $ai_script_id     Script element id used for AI app bundle.
	 * @param string $ai_script_src    AI app bundle source URL.
	 * @param array  $ai_data          AI app localized data.
	 *
	 * @return array
	 */
	private function _build_cloud_and_ai_lazy_assets_payload(
		string $cloud_script_id,
		string $cloud_script_src,
		string $ai_script_id,
		string $ai_script_src,
		array $ai_data
	): array {
		$lazy_assets = [
			'scripts' => [
				$cloud_script_id => $cloud_script_src,
			],
			'styles'  => [],
			'globals' => [
				'et_cloud_data' => \ET_Cloud_App::get_cloud_helpers(),
			],
		];

		if ( '' !== $ai_script_src ) {
			$lazy_assets['scripts'][ $ai_script_id ] = $ai_script_src;
		}

		if ( ! empty( $ai_data ) ) {
			$lazy_assets['globals']['EtAiAppData'] = $ai_data;
		}

		return $lazy_assets;
	}

	/**
	 * Enqueue a lazy loader that inserts Divi Cloud assets after the app window's first render.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function enqueue_cloud_app_after_first_render(): void {
		if ( ! Conditions::is_vb_app_window() ) {
			return;
		}

		$cache_buster                 = $this->_get_lazy_assets_cache_buster();
		$ai_payload                   = $this->_get_ai_lazy_asset_payload( 'data_app_window' );
		$ai_agent_src                 = $this->_get_package_script_src( 'divi-ai-agent' );
		$mask_lib_src                 = $this->_get_package_script_src( 'divi-mask-and-pattern-library' );
		$variable_generator_modal_src = $this->_get_package_script_src( 'divi-variable-generator-modal' );
		$grid_editor_modal_src        = $this->_get_package_script_src( 'divi-grid-editor-modal' );
		$cloud_css                    = $this->_get_package_style_src( 'divi-cloud-app' );
		$lazy_assets                  = $this->_build_cloud_and_ai_lazy_assets_payload(
			'et-cloud-app-lazy-script',
			add_query_arg( 'ver', $cache_buster, \ET_Cloud_App::get_bundle_uri() ),
			'et-ai-app-lazy-script',
			$ai_payload['src'],
			$ai_payload['data']
		);

		if ( '' !== $ai_agent_src ) {
			$lazy_assets['scripts']['divi-ai-agent-lazy-script'] = $ai_agent_src;
		}

		if ( '' !== $mask_lib_src ) {
			// Lazy-load mask/pattern runtime and let VB refresh CSS declarations after runtime is ready.
			$lazy_assets['scripts']['divi-mask-and-pattern-library-lazy-script'] = $mask_lib_src;
		}

		if ( '' !== $variable_generator_modal_src ) {
			$lazy_assets['scripts']['divi-variable-generator-modal-lazy-script'] = $variable_generator_modal_src;
		}

		if ( '' !== $grid_editor_modal_src ) {
			$lazy_assets['scripts']['divi-grid-editor-modal-lazy-script'] = $grid_editor_modal_src;
		}

		if ( '' !== $cloud_css ) {
			$lazy_assets['styles']['et-cloud-app-lazy-style'] = add_query_arg( 'ver', $cache_buster, $cloud_css );
		}

		LazyAssetLoader::enqueue_loader(
			'divi-cloud-app-lazy-loader',
			$lazy_assets,
			[
				// Wait for the app-render signal and preloader removal before loading cloud and AI bundles.
				'trigger_event'      => self::APP_WINDOW_FIRST_RENDER_SIGNAL,
				'wait_for_preloader' => true,
				'auto_attempt'       => true,
			]
		);
	}

	/**
	 * Enqueue top-window cloud assets after app-window render completes.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function enqueue_top_window_cloud_assets_after_app_ready(): void {
		if ( ! Conditions::is_vb_top_window() ) {
			return;
		}

		$cache_buster = $this->_get_lazy_assets_cache_buster();
		$ai_payload   = $this->_get_ai_lazy_asset_payload( 'data_top_window' );
		$cloud_css    = $this->_get_cloud_style_src();
		$lazy_assets  = $this->_build_cloud_and_ai_lazy_assets_payload(
			'et-cloud-app-top-window-script',
			add_query_arg( 'ver', $cache_buster, \ET_Cloud_App::get_bundle_uri() ),
			'et-ai-app-top-window-lazy-script',
			$ai_payload['src'],
			$ai_payload['data']
		);

		if ( '' !== $cloud_css ) {
			$lazy_assets['styles']['et-cloud-app-top-window-lazy-style'] = add_query_arg( 'ver', $cache_buster, $cloud_css );
		}

		LazyAssetLoader::enqueue_loader(
			'divi-cloud-app-top-window-lazy-loader',
			$lazy_assets,
			[
				// Top window listens for the app-window postMessage signal before loading secondary bundles.
				'trigger_message_type' => self::APP_WINDOW_FIRST_RENDER_SIGNAL,
				'trigger_window_flag'  => self::APP_WINDOW_FIRST_RENDER_FLAG,
				'auto_attempt'         => false,
			]
		);
	}

	/**
	 * Remove actions from D4 which are deprecated in D5, and then add new actions for the D5.
	 *
	 * phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
	 * TODO: feat(d5, refactor) Utilize this method to replace deprecated D4 action/filter hooks.
	 *
	 * @link https://elegantthemes.slack.com/archives/C04S2RE9N1J/p1694531769374299?thread_ts=1694530913.949609&cid=C04S2RE9N1J
	 *
	 * @since ??
	 *
	 * @ignore
	 *
	 * @return void
	 */
	public function replace_deprecated_actions() {
		// D5 has deprecated the `et_save_post` action with `divi_visual_builder_rest_save_post` hook.
		remove_action( 'et_save_post', 'et_divi_save_post', 1 );
		add_action( 'divi_visual_builder_rest_save_post', 'et_divi_save_post', 1 );

		remove_action( 'et_save_post', 'et_theme_builder_clear_wp_post_cache' );
		add_action( 'divi_visual_builder_rest_save_post', 'et_theme_builder_clear_wp_post_cache' );
	}
}

$dependency_tree = new DependencyTree();
$dependency_tree->add_dependency( new RESTRegistration() );
$dependency_tree->add_dependency( new HooksRegistration() );
$dependency_tree->add_dependency( new PageSettings() );
$dependency_tree->add_dependency( new PackageBuildManager() );
$dependency_tree->add_dependency( new SettingsData() );
$dependency_tree->add_dependency( new SpeculationRules() );
$dependency_tree->add_dependency( new TopWindow() );
$dependency_tree->add_dependency( new \ET\Builder\Packages\WooCommerce\WooCommerceHooks() );

$visual_builder = new VisualBuilder( $dependency_tree );
$visual_builder->initialize();
