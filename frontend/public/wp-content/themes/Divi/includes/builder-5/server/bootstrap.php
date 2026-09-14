<?php
/**
 * Builder bootstrap file.
 *
 * @since ??
 * @package Builder
 */

use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\DependencyChangeDetector;
use ET\Builder\VisualBuilder\OffCanvas\OffCanvasHooks;
use ET\Builder\VisualBuilder\Performance\PerformanceLogger;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Requires Autoloader.
 */
require __DIR__ . '/vendor/autoload.php';

// Initialize performance logging when available.
if ( class_exists( PerformanceLogger::class ) ) {
	PerformanceLogger::initialize();
}

/**
 * Define constants.
 */
if ( ! defined( 'ET_BUILDER_5_URI' ) ) {
	/**
	 * Defines ET_BUILDER_5_URI constant.
	 *
	 * @var string ET_BUILDER_5_URI The builder directory URI.
	 */
	define( 'ET_BUILDER_5_URI', get_template_directory_uri() . '/includes/builder' );
}

// Require root files from `/server`.
// Require security fixes early.
require_once __DIR__ . '/Security/Security.php';

/*
 * Register post-filter custom field option cache invalidation only where post meta can change.
 * This must not depend on Modules.php, which is not loaded on standard admin CPT edit screens.
 * Hook registration is lightweight; the flush callbacks only run on meta/status/post save events.
 */
$should_register_post_filter_cache_hooks = (
	Conditions::is_wp_post_edit_screen()
	|| Conditions::is_rest_api_request()
	|| Conditions::is_ajax_request()
);

if ( $should_register_post_filter_cache_hooks ) {
	require_once __DIR__ . '/Packages/ModuleLibrary/PostFilterItem/PostFilterCustomFieldValueOptions.php';

	\ET\Builder\Packages\ModuleLibrary\PostFilterItem\PostFilterCustomFieldValueOptions::register_hooks();
}

/*
 * Only load lf we are:
 * - on a theme builder page,
 * - or on a WP post edit screen,
 * - or on a VB page,
 * - or in ajax request,
 * - or in a REST API request,
 * - but otherwise, not ever in admin
 */
if ( Conditions::is_tb_admin_screen()
	|| Conditions::is_wp_post_edit_screen()
	|| Conditions::is_vb_app_window()
	|| Conditions::is_ajax_request()
	|| Conditions::is_rest_api_request()
	|| ! Conditions::is_admin_request()
) {
	require_once __DIR__ . '/VisualBuilder/VisualBuilder.php';
}

/*
 * Load editor-specific integrations on the post edit screen.
 * Deferred to the current_screen action because at bootstrap time the screen and post
 * are not yet set; we need them to detect whether the Block Editor or Classic Editor is active.
 */
if ( Conditions::is_wp_post_edit_screen() ) {
	add_action(
		'current_screen',
		function () {
			if ( Conditions::is_block_editor() ) {
				require_once __DIR__ . '/Gutenberg/GutenbergEditor/GutenbergEditor.php';
			} else {
				require_once __DIR__ . '/ClassicEditor/ClassicEditor.php';
			}
		}
	);
}

/*
 * Only load lf we are:
 * - on a theme builder page,
 * - or on a VB page,
 * - or in ajax request,
 * - or in a REST API request,
 * - or on the Role Editor page,
 * - but otherwise, not ever in admin
 */
if (
	Conditions::is_tb_admin_screen()
	|| Conditions::is_vb_app_window()
	|| Conditions::is_ajax_request()
	|| Conditions::is_rest_api_request()
	|| ! Conditions::is_admin_request()
	|| Conditions::is_role_editor_page()
) {
	require_once __DIR__ . '/ThemeBuilder/ThemeBuilder.php';
	require_once __DIR__ . '/Packages/ShortcodeModule/ShortcodeModule.php';
	require_once __DIR__ . '/Packages/ModuleLibrary/Modules.php';
	require_once __DIR__ . '/Packages/Module/Layout/Components/DynamicContent/DynamicContent.php';

	// Load migration.
	require_once __DIR__ . '/Migration/Migration.php';
}

/*
 * Load Gutenberg Layout Block integration.
 */
if ( et_core_is_gutenberg_active() ) {
	require_once __DIR__ . '/Gutenberg/Gutenberg.php';
}

/*
 * Load Gutenberg Admin (asset loading, portability, REST save handling).
 * Load in admin requests AND REST API requests (for layout block saves).
 */
if ( Conditions::is_admin_request() || Conditions::is_rest_api_request() ) {
	require_once __DIR__ . '/Gutenberg/Admin.php';
}

/*
 * Only load if we are not in admin.
 * This is for frontend.
 */
if ( ! Conditions::is_admin_request() ) {
	require_once __DIR__ . '/FrontEnd/FrontEnd.php';
}

/*
 * Only load if we are in admin.
 * This is for admin area functionality only.
 */
if ( Conditions::is_admin_request() ) {
	require_once __DIR__ . '/Admin/Admin.php';

	/*
	 * Initialize dependency change detection for attrs maps cache invalidation.
	 * This only needs to run in admin since plugin/theme activation hooks only fire there.
	 */
	DependencyChangeDetector::init();
}

/*
 * Only load off-canvas hooks when:
 * - on a theme builder page,
 * - or on a WP post edit screen,
 * - or on a VB page,
 * - or in ajax request,
 * - or in a REST API request,
 * - or on frontend (not in admin)
 * This ensures the post type is registered and hooks are available when needed,
 * but avoids loading on pure admin pages where off-canvas functionality isn't used.
 *
 * Third-party plugins can force loading via the `divi_off_canvas_should_load` filter.
 * This allows plugins like WPML to register the CPT on their admin pages (e.g., translation dashboard).
 */
$should_load_off_canvas = (
	Conditions::is_tb_admin_screen()
	|| Conditions::is_wp_post_edit_screen()
	|| Conditions::is_vb_app_window()
	|| Conditions::is_ajax_request()
	|| Conditions::is_rest_api_request()
	|| ! Conditions::is_admin_request()
);

/**
 * Filters whether or not the Off Canvas hooks should be loaded for the current request.
 *
 * Allows third-party plugins to force loading of Off Canvas hooks on their admin pages,
 * enabling the Off Canvas CPT to be registered when needed (e.g., for translation management).
 *
 * @since ??
 *
 * @param bool $should_load_off_canvas Whether to load Off Canvas hooks based on default conditions.
 */
$should_load_off_canvas = apply_filters( 'divi_off_canvas_should_load', $should_load_off_canvas );

if ( $should_load_off_canvas ) {
	require_once __DIR__ . '/VisualBuilder/OffCanvas/OffCanvasHooks.php';

	// Initialize off-canvas hooks.
	OffCanvasHooks::init();
}
