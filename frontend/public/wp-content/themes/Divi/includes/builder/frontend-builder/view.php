<?php

/**
 * Boots Frond End Builder App,
 *
 * @return string Front End Builder wrap if main query, $content otherwise.
 */
function et_fb_app_boot( $content ) {
	// Instances of React app
	static $instances = 0;
	$is_new_page      = isset( $_GET['is_new_page'] ) && '1' === $_GET['is_new_page']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No need to use nonce.
	$has_editable_theme_builder_template = et_fb_is_enabled_on_any_template();
	$is_non_singular_theme_builder_context = ! is_singular()
		&& $has_editable_theme_builder_template
		&& et_pb_is_allowed( 'theme_builder' );

	$main_query_post      = ET_Post_Stack::get_main_post();
	$main_query_post_type = $main_query_post ? $main_query_post->post_type : '';

	if ( ET_Theme_Builder_Layout::is_theme_builder_layout()
		&& ! et_theme_builder_is_layout_post_type( $main_query_post_type )
		&& is_singular() ) {
		// Prevent boot if we are rendering a TB layout and not the real WP Query post.
		return $content;
	}

	// Don't boot the app if the builder is not in use.
	// On non-singular pages with active Theme Builder templates, boot should not depend on loop post builder meta.
	if ( ( ! $is_non_singular_theme_builder_context && ! et_pb_is_pagebuilder_used( get_the_ID() ) ) || doing_filter( 'get_the_excerpt' ) ) {
		// Skip this when content should be loaded from other post or page to not mess with the default content
		if ( $is_new_page ) {
			return;
		}

		return $content;
	}

	$class = apply_filters( 'et_fb_app_preloader_class', 'et-fb-page-preloading' );

	if ( '' !== $class ) {
		$class = sprintf( ' class="%1$s"', esc_attr( $class ) );
	}

	// Only return React app wrapper for the main query.
	if ( is_main_query() ) {
		// In app window sidebar layouts, app wrapper is printed around main content via
		// `et_before_main_content` / `et_after_main_content` hooks instead of `the_content`.
		if ( et_fb_should_render_app_wrapper_around_main_content() ) {
			$body_root_output = '<div id="et-fb-app-body-root" class="et-fb-root-area"></div>';
			return $body_root_output;
		}

		// Keep track of instances in case is_main_query() is true multiple times for the same page
		// This happens in 2017 theme when multiple Divi enabled pages are assigned to Front Page Sections
		$instances++;
		$output = sprintf( '<div id="et-fb-app"%1$s></div>', $class );
		// No need to add fallback content on a new page.
		if ( $instances > 1 && ! $is_new_page ) {
			// uh oh, we might have multiple React app in the same page, let's also add rendered content and deal with it later using JS
			$output .= sprintf( '<div class="et_fb_fallback_content" style="display: none">%s</div>', $content );
			// Stop shortcode object processor so that shortcode in the content are treated normaly.
			et_fb_reset_shortcode_object_processing();
		}
		return $output;
	}

	// Stop shortcode object processor so that shortcode in the content are treated normaly.
	et_fb_reset_shortcode_object_processing();

	return $content;
}
add_filter( 'the_content', 'et_fb_app_boot', 1 );
add_filter( 'et_builder_render_layout', 'et_fb_app_boot', 1 );

/**
 * Determine whether app wrapper should be rendered around `#main-content`.
 *
 * @return bool
 */
function et_fb_should_render_app_wrapper_around_main_content() {
	static $cached_should_wrap = null;

	if ( null !== $cached_should_wrap ) {
		return $cached_should_wrap;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only URL context check.
	$is_app_window = isset( $_GET['app_window'] ) && '1' === $_GET['app_window'];
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( ! $is_app_window || ! et_core_is_fb_enabled() ) {
		$cached_should_wrap = false;
		return false;
	}

	$has_sidebar_class = false;

	$computed_body_classes = et_divi_sidebar_class( [] );
	$has_sidebar_class     = in_array( 'et_right_sidebar', $computed_body_classes, true ) || in_array( 'et_left_sidebar', $computed_body_classes, true );
	$has_no_sidebar_class  = in_array( 'et_no_sidebar', $computed_body_classes, true );

	// Fallback for environments where theme helper is unavailable.
	// Respect explicit `et_no_sidebar` to avoid forcing wrapper rendering on templates like Blank Page.
	if ( ! $has_sidebar_class && ! $has_no_sidebar_class ) {
		$main_post_id = ET_Post_Stack::get_main_post_id();
		$post_id      = $main_post_id ? $main_post_id : get_queried_object_id();
		$page_layout  = $post_id ? get_post_meta( $post_id, '_et_pb_page_layout', true ) : '';

		if ( ! $page_layout ) {
			$default_sidebar = et_get_option( 'divi_sidebar' );
			$page_layout     = $default_sidebar ? $default_sidebar : ( is_rtl() ? 'et_left_sidebar' : 'et_right_sidebar' );
		}

		$has_sidebar_class = in_array( $page_layout, [ 'et_left_sidebar', 'et_right_sidebar' ], true );
	}

	if ( ! $has_sidebar_class ) {
		$cached_should_wrap = false;
		return false;
	}

	$has_editable_theme_builder_template = et_fb_is_enabled_on_any_template();
	$is_non_singular_theme_builder_context = ! is_singular()
		&& $has_editable_theme_builder_template
		&& et_pb_is_allowed( 'theme_builder' );

	$main_post_id = ET_Post_Stack::get_main_post_id();
	$post_id      = $main_post_id ? $main_post_id : get_the_ID();
	$should_wrap  = $is_non_singular_theme_builder_context || ( $post_id && et_pb_is_pagebuilder_used( $post_id ) );

	$cached_should_wrap = (bool) $should_wrap;

	return $cached_should_wrap;
}

/**
 * Print app wrapper opening markup before main content.
 *
 * @return void
 */
function et_fb_print_app_wrapper_before_main_content() {
	global $et_fb_app_wrapper_opened;

	if ( ! et_fb_should_render_app_wrapper_around_main_content() ) {
		return;
	}

	$class = apply_filters( 'et_fb_app_preloader_class', 'et-fb-page-preloading' );
	$class = '' !== $class ? sprintf( ' class="%1$s"', esc_attr( $class ) ) : '';

	echo et_core_intentionally_unescaped(
		sprintf(
			'<div id="et-fb-app"%1$s><div id="et-fb-app-header-root" class="et-fb-root-area"></div>',
			$class
		),
		'html'
	);
	$et_fb_app_wrapper_opened = true;
}
add_action( 'et_before_main_content', 'et_fb_print_app_wrapper_before_main_content', 1 );

/**
 * Print app wrapper closing markup after main content.
 *
 * @return void
 */
function et_fb_print_app_wrapper_after_main_content() {
	global $et_fb_app_wrapper_closed;

	if ( $et_fb_app_wrapper_closed || ! et_fb_should_render_app_wrapper_around_main_content() ) {
		return;
	}

	echo '<div id="et-fb-app-footer-root" class="et-fb-root-area"></div></div>';
	$et_fb_app_wrapper_closed = true;
}
add_action( 'et_after_main_content', 'et_fb_print_app_wrapper_after_main_content', 999 );

/**
 * Ensure app wrapper is closed even when templates skip `et_after_main_content`.
 *
 * @return void
 */
function et_fb_ensure_app_wrapper_closed_before_footer() {
	global $et_fb_app_wrapper_opened;
	global $et_fb_app_wrapper_closed;

	if ( ! $et_fb_app_wrapper_opened || $et_fb_app_wrapper_closed ) {
		return;
	}

	et_fb_print_app_wrapper_after_main_content();
}
add_action( 'get_footer', 'et_fb_ensure_app_wrapper_closed_before_footer', 0 );

function et_fb_wp_nav_menu( $menu ) {
	// Ensure we fix any unclosed HTML tags in menu since they would break the VB
	return et_core_fix_unclosed_html_tags( $menu );
}
add_filter( 'wp_nav_menu', 'et_fb_wp_nav_menu' );

function et_builder_maybe_include_bfb_template( $template ) {
	if ( et_builder_bfb_enabled() && ! is_admin() ) {
		return ET_BUILDER_DIR . 'frontend-builder/bfb-template.php';
	}

	// Load custom page template when editing Cloud Item.
	if ( isset( $_GET['cloudItem'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- This function does not change any state, and is therefore not susceptible to CSRF.
		if ( current_user_can( 'manage_options' ) || current_user_can( 'editor' ) ) {
			wp_admin_bar_render();
		}

		return ET_BUILDER_DIR . 'templates/block-layout-preview.php';
	}

	return $template;
}
add_filter( 'template_include', 'et_builder_maybe_include_bfb_template', 99 );


function et_fb_dynamic_sidebar_ob_start() {
	global $et_fb_dynamic_sidebar_buffering;

	if ( $et_fb_dynamic_sidebar_buffering ) {
		echo force_balance_tags( ob_get_clean() );
	}

	$et_fb_dynamic_sidebar_buffering = true;

	ob_start();
}
add_action( 'dynamic_sidebar', 'et_fb_dynamic_sidebar_ob_start' );

function et_fb_dynamic_sidebar_after_ob_get_clean() {
	global $et_fb_dynamic_sidebar_buffering;

	if ( $et_fb_dynamic_sidebar_buffering ) {
		echo force_balance_tags( ob_get_clean() );

		$et_fb_dynamic_sidebar_buffering = false;
	}
}
add_action( 'dynamic_sidebar_after', 'et_fb_dynamic_sidebar_after_ob_get_clean' );


/**
 * Added frontend builder specific body class
 *
 * @todo load conditionally, only when the frontend builder is used
 *
 * @param array  initial <body> classes
 * @return array modified <body> classes
 */
function et_fb_add_body_class( $classes ) {
	$classes[] = 'et-fb';

	if ( is_rtl() && 'on' === et_get_option( 'divi_disable_translations', 'off' ) ) {
		$classes[] = 'et-fb-no-rtl';
	}

	if ( et_builder_bfb_enabled() ) {
		$classes[] = 'et-bfb';
	}

	if ( et_builder_tb_enabled() ) {
		$classes[] = 'et-tb';
	}

	if ( isset( $_GET['cloudItem'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- This function does not change any state, and is therefore not susceptible to CSRF.
		$classes[] = 'et-cloud-item-editor';
	}

	return $classes;
}
add_filter( 'body_class', 'et_fb_add_body_class' );

/**
 * Added BFB specific body class
 *
 * @todo load conditionally, only when the frontend builder is used
 *
 * @param string initial <body> classes
 * @return string modified <body> classes
 */
function et_fb_add_admin_body_class( $classes ) {
	if ( is_rtl() && 'on' === et_get_option( 'divi_disable_translations', 'off' ) ) {
		$classes .= ' et-fb-no-rtl';
	}

	if ( et_builder_bfb_enabled() ) {
		$classes .= ' et-bfb';

		$post_id   = et_core_page_resource_get_the_ID();
		$post_type = get_post_type( $post_id );

		// Add layout classes when on library page
		if ( 'et_pb_layout' === $post_type ) {
			$layout_type  = et_fb_get_layout_type( $post_id );
			$layout_scope = et_fb_get_layout_term_slug( $post_id, 'scope' );

			$classes .= " et_pb_library_page_top-{$layout_type}";
			$classes .= " et_pb_library_page_top-{$layout_scope}";
		}
	}

	return $classes;
}
add_filter( 'admin_body_class', 'et_fb_add_admin_body_class' );

/**
 * Remove visual builder preloader classname on BFB because BFB spins the preloader on parent level to avoid flash of unstyled elements
 *
 * @param string builder preloader classname
 * @return string modified builder preloader classname
 */
function et_bfb_app_preloader_class( $classname ) {
	return et_builder_bfb_enabled() ? '' : $classname;
}
add_filter( 'et_fb_app_preloader_class', 'et_bfb_app_preloader_class' );

function et_builder_inject_preboot_script() {
	$et_debug = defined( 'ET_DEBUG' ) && ET_DEBUG;
	$preboot  = array(
		'debug'  => $et_debug || DiviExtensions::is_debugging_extension(),
		'is_BFB' => et_builder_bfb_enabled(),
		'is_TB'  => et_builder_tb_enabled(),
	);

	$preboot_path = ET_BUILDER_DIR . 'frontend-builder/build/preboot.js';
	if ( file_exists( $preboot_path ) ) {
		$preboot_script = et_()->WPFS()->get_contents( $preboot_path );
	} else {
		// if the file doesn't exists, it means we're using `yarn hot`
		$site_url = wp_parse_url( get_site_url() );
		$hot      = "{$site_url['scheme']}://{$site_url['host']}:31495/preboot.js";
		$curl     = curl_init();
		curl_setopt_array(
			$curl,
			array(
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_URL            => $hot,
			)
		);
		$preboot_script = curl_exec( $curl );
	}

	echo "
		<script id='et-builder-preboot'>
			var et_fb_preboot = " . wp_json_encode( $preboot ) . ";

			// Disable Google Tag Manager
			window.dataLayer = [{'gtm.blacklist': ['google', 'nonGoogleScripts', 'customScripts', 'customPixels', 'nonGooglePixels']}];

			{$preboot_script}
		</script>
	";
}
add_action( 'wp_head', 'et_builder_inject_preboot_script', 0 );
