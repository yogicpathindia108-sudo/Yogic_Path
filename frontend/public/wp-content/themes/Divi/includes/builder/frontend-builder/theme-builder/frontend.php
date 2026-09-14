<?php

use ET\Builder\FrontEnd\Assets\StaticCSS;
use ET\Builder\ThemeBuilder\Layout;

/**
 * Remove the admin bar from the VB when used from the Theme Builder.
 *
 * @since 4.0
 *
 * @return void
 */
function et_theme_builder_frontend_disable_admin_bar() {
	if ( et_builder_tb_enabled() ) {
		add_filter( 'show_admin_bar', '__return_false' );
	}
}
add_filter( 'wp', 'et_theme_builder_frontend_disable_admin_bar' );

/**
 * Add body classes depending on which areas are overridden by TB.
 *
 * @since 4.0
 *
 * @param array $classes
 *
 * @return string[]
 */
function et_theme_builder_frontend_add_body_classes( $classes ) {
	if ( et_builder_bfb_enabled() ) {
		// Do not add any classes in BFB.
		return $classes;
	}

	$layouts = et_theme_builder_get_template_layouts();

	if ( ! empty( $layouts ) ) {
		$classes[] = 'et-tb-has-template';

		if ( $layouts[ ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE ]['override'] ) {
			$classes[] = 'et-tb-has-header';

			if ( ! $layouts[ ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE ]['enabled'] ) {
				$classes[] = 'et-tb-header-disabled';
			}
		}

		if ( $layouts[ ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE ]['override'] ) {
			$classes[] = 'et-tb-has-body';

			if ( ! $layouts[ ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE ]['enabled'] ) {
				$classes[] = 'et-tb-body-disabled';
			}
		}

		if ( $layouts[ ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE ]['override'] ) {
			$classes[] = 'et-tb-has-footer';

			if ( ! $layouts[ ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE ]['enabled'] ) {
				$classes[] = 'et-tb-footer-disabled';
			}
		}
	}

	return $classes;
}
add_filter( 'body_class', 'et_theme_builder_frontend_add_body_classes', 9 );

/**
 * Conditionally override the template being loaded by WordPress based on what the user
 * has created in their Theme Builder.
 * The header and footer are always dealt with as a pair - if the header is replaced the footer is replaced a well.
 *
 * @since 4.0
 *
 * @param string $template
 *
 * @return string
 */
function et_theme_builder_frontend_override_template( $template ) {
	$layouts       = et_theme_builder_get_template_layouts();
	$page_template = locate_template( 'page.php' );

	if ( is_et_theme_builder_live_preview() ) {
		// We are previewing live demo of a theme builder template in FE.
		remove_action( 'wp_body_open', 'wp_admin_bar_render', 0 );

		add_action( 'get_header', 'et_theme_builder_frontend_override_header' );
		add_action( 'get_footer', 'et_theme_builder_frontend_override_footer' );
		et_prioritize_deferred_block_css_for_theme_builder();

		return $page_template;
	}

	$override_header   = et_theme_builder_overrides_layout( ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE );
	$override_body     = et_theme_builder_overrides_layout( ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE );
	$override_footer   = et_theme_builder_overrides_layout( ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE );
	$is_visual_builder = et_core_is_fb_enabled();
	$is_theme_builder  = et_builder_tb_enabled();

	if ( $override_header || $override_footer ) {
		// wp-version >= 5.2.
		remove_action( 'wp_body_open', 'wp_admin_bar_render', 0 );

		add_action( 'get_header', 'et_theme_builder_frontend_override_header' );
		add_action( 'get_footer', 'et_theme_builder_frontend_override_footer' );
		et_prioritize_deferred_block_css_for_theme_builder();
	}

	// For other themes than Divi, use 'frontend-body-template.php'.
	if ( $override_body && ! function_exists( 'et_divi_fonts_url' ) ) {
		return ET_THEME_BUILDER_DIR . 'frontend-body-template.php';
	}

	if ( $override_body && ( $is_theme_builder || ! $is_visual_builder || ! $layouts['et_template'] ) ) {
		return ET_THEME_BUILDER_DIR . 'frontend-body-template.php';
	}

	if ( $override_body && ! is_home() ) {
		return $page_template;
	}

	return $template;
}
// Priority of 98 so it can be overridden by BFB.
add_filter( 'template_include', 'et_theme_builder_frontend_override_template', 98 );


/**
 * Render a custom partial overriding the original one.
 *
 * @since 4.0
 *
 * @param string $partial
 * @param string $action
 * @param string $name
 *
 * @return void
 */
function et_theme_builder_frontend_override_partial( $partial, $name, $action = '' ) {
	global $wp_filter;

	$tb_theme_head = '';

	/**
	 * Slightly adjusted version of WordPress core code in order to mimic behavior.
	 *
	 * @link https://core.trac.wordpress.org/browser/tags/5.0.3/src/wp-includes/general-template.php#L33
	 */
	$templates = array();
	$name      = (string) $name;
	if ( '' !== $name ) {
		$templates[] = "{$partial}-{$name}.php";
	}
	$templates[] = "{$partial}.php";

	// Buffer and discard the original partial forcing a require_once so it doesn't load again later.
	$buffered = ob_start();
	if ( $buffered ) {
		$actions = array();

		if ( ! empty( $action ) ) {
			// Skip any partial-specific actions so they don't run twice.
			$actions = et_()->array_get( $wp_filter, $action, array() );
			unset( $wp_filter[ $action ] );
		}

		locate_template( $templates, true, true );
		$html = ob_get_clean();

		if ( 'wp_head' === $action ) {
			$tb_theme_head = et_theme_builder_extract_head( $html );
		}

		if ( ! empty( $action ) ) {
			// Restore skipped actions.
			$wp_filter[ $action ] = $actions;
		}
	}

	require_once ET_THEME_BUILDER_DIR . "frontend-{$partial}-template.php";
}

/**
 * Extract <head> tag contents.
 *
 * @since 4.0.8
 *
 * @param string $html
 *
 * @return string
 */
function et_theme_builder_extract_head( $html ) {
	// We could use DOMDocument here to guarantee proper parsing but we need
	// the most performant solution since we cannot reliably cache the result.
	$head = array();
	preg_match( '/^[\s\S]*?<head[\s\S]*?>([\s\S]*?)<\/head>[\s\S]*$/i', $html, $head );

	return ! empty( $head[1] ) ? trim( $head[1] ) : '';
}

/**
 * Override the default header template.
 *
 * @since 4.0
 *
 * @param string $name
 *
 * @return void
 */
function et_theme_builder_frontend_override_header( $name ) {
	et_theme_builder_frontend_override_partial( 'header', $name, 'wp_head' );
}

/**
 * Override the default footer template.
 *
 * @since 4.0
 *
 * @param string $name
 *
 * @return void
 */
function et_theme_builder_frontend_override_footer( $name ) {
	et_theme_builder_frontend_override_partial( 'footer', $name, 'wp_footer' );
}

/**
 * Filter builder content wrapping as Theme Builder Layouts are wrapped collectively instead of individually.
 *
 * @since 4.0
 *
 * @param bool $wrap
 *
 * @return bool
 */
function et_theme_builder_frontend_filter_add_outer_content_wrap( $wrap ) {
	$override_header = et_theme_builder_overrides_layout( ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE );
	$override_footer = et_theme_builder_overrides_layout( ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE );

	// Theme Builder layouts must not be individually wrapped as they are wrapped
	// collectively, with the exception of the BFB or body layout.
	if ( ( $override_header || $override_footer ) && ! et_builder_bfb_enabled() ) {
		$wrap = false;
	}

	return $wrap;
}
add_filter( 'et_builder_add_outer_content_wrap', 'et_theme_builder_frontend_filter_add_outer_content_wrap' );

/**
 * Get all dynamic content fields in a given string.
 *
 * @since 5.0.0
 *
 * @param string $content Value content.
 *
 * @return array
 */
function et_builder_frontend_get_dynamic_contents( $content ) {
	$pattern                = ET_THEME_BUILDER_DYNAMIC_CONTENT_REGEX;
	$maybe_gutenberg_format = false !== strpos( $content, '<!-- wp:' );

	if ( $maybe_gutenberg_format ) {
		$pattern = ET_THEME_BUILDER_DYNAMIC_CONTENT_REGEX_D5;
	}

	$is_matched = preg_match_all( $pattern, $content, $matches );

	if ( ! $is_matched ) {
		return array();
	}

	return $matches[0];
}

/**
 * Render a header layout.
 *
 * @since 4.0
 *
 * @param integer $layout_id The layout id or 0.
 * @param boolean $layout_enabled
 * @param integer $template_id The template id or 0.
 *
 * @return void
 */
function et_theme_builder_frontend_render_header( $layout_id, $layout_enabled, $template_id ) {
	/**
	 * Fires before theme builder page wrappers are output.
	 * Example use case is to add opening wrapping html tags for the entire page.
	 *
	 * @since 4.0
	 *
	 * @param integer $layout_id The layout id or 0.
	 * @param bool $layout_enabled
	 * @param integer $template_id The template id or 0.
	 */
	do_action( 'et_theme_builder_template_before_page_wrappers', $layout_id, $layout_enabled, $template_id );

	et_theme_builder_frontend_render_common_wrappers( 'common', true );

	/**
	 * Fires before theme builder template header is output.
	 * Example use case is to add opening wrapping html tags for the header and/or the entire page.
	 *
	 * @since 4.0
	 *
	 * @param integer $layout_id The layout id or 0.
	 * @param bool $layout_enabled
	 * @param integer $template_id The template id or 0.
	 */
	do_action( 'et_theme_builder_template_before_header', $layout_id, $layout_enabled, $template_id );

	if ( $layout_enabled ) {
		Layout::render( ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE, $layout_id );
	}

	/**
	 * Fires after theme builder template header is output.
	 * Example use case is to add closing wrapping html tags for the header.
	 *
	 * @since 4.0
	 *
	 * @param integer $layout_id The layout id or 0.
	 * @param bool $layout_enabled
	 * @param integer $template_id The template id or 0.
	 */
	do_action( 'et_theme_builder_template_after_header', $layout_id, $layout_enabled, $template_id );
}

/**
 * Render a body layout.
 *
 * @since 4.0
 *
 * @param integer $layout_id The layout id or 0.
 * @param boolean $layout_enabled
 * @param integer $template_id The template id or 0.
 *
 * @return void
 */
function et_theme_builder_frontend_render_body( $layout_id, $layout_enabled, $template_id ) {
	/**
	 * Fires before theme builder template body is output.
	 * Example use case is to add opening wrapping html tags for the body.
	 *
	 * @since 4.0
	 *
	 * @param integer $layout_id The layout id or 0.
	 * @param bool $layout_enabled
	 * @param integer $template_id The template id or 0.
	 *
	 * @return void
	 */
	do_action( 'et_theme_builder_template_before_body', $layout_id, $layout_enabled, $template_id );

	if ( $layout_enabled ) {
		Layout::render( ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE, $layout_id );
	}

	/**
	 * Fires after theme builder template body is output.
	 * Example use case is to add closing wrapping html tags for the body.
	 *
	 * @since 4.0
	 *
	 * @param integer $layout_id The layout id or 0.
	 * @param bool $layout_enabled
	 * @param integer $template_id The template id or 0.
	 *
	 * @return void
	 */
	do_action( 'et_theme_builder_template_after_body', $layout_id, $layout_enabled, $template_id );
}

/**
 * Render a footer layout.
 *
 * @since 4.0
 *
 * @param integer $layout_id The layout id or 0.
 * @param boolean $layout_enabled
 * @param integer $template_id The template id or 0.
 *
 * @return void
 */
function et_theme_builder_frontend_render_footer( $layout_id, $layout_enabled, $template_id ) {
	/**
	 * Fires before theme builder template footer is output.
	 * Example use case is to add opening wrapping html tags for the footer.
	 *
	 * @since 4.0
	 *
	 * @param integer $layout_id The layout id or 0.
	 * @param bool $layout_enabled
	 * @param integer $template_id The template id or 0.
	 *
	 * @return void
	 */
	do_action( 'et_theme_builder_template_before_footer', $layout_id, $layout_enabled, $template_id );

	if ( $layout_enabled ) {
		Layout::render( ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE, $layout_id );
	}

	/**
	 * Fires after theme builder template footer is output.
	 * Example use case is to add closing wrapping html tags for the footer and/or the entire page.
	 *
	 * @since 4.0
	 *
	 * @param integer $layout_id The layout id or 0.
	 * @param bool $layout_enabled
	 * @param integer $template_id The template id or 0.
	 *
	 * @return void
	 */
	do_action( 'et_theme_builder_template_after_footer', $layout_id, $layout_enabled, $template_id );

	et_theme_builder_frontend_render_common_wrappers( 'common', false );

	/**
	 * Fires after theme builder page wrappers are output.
	 * Example use case is to add closing wrapping html tags for the entire page.
	 *
	 * @since 4.0
	 *
	 * @param integer $layout_id The layout id or 0.
	 * @param bool $layout_enabled
	 * @param integer $template_id The template id or 0.
	 */
	do_action( 'et_theme_builder_template_after_page_wrappers', $layout_id, $layout_enabled, $template_id );
}

/**
 * Open or close common builder wrappers (e.g. #et-boc) in order to avoid having triple wrappers - one for every layout.
 *
 * Useful
 *
 * @param $area
 * @param $open
 */
function et_theme_builder_frontend_render_common_wrappers( $area, $open ) {
	static $wrapper = '';

	if ( $open ) {
		// Open wrappers only if there are no other open wrappers already.
		if ( '' === $wrapper ) {
			$wrapper = $area;
			echo et_builder_get_builder_content_opening_wrapper();
		}
		return;
	}

	if ( '' === $wrapper || $area !== $wrapper ) {
		// Do not close wrappers if the opener does not match the current area.
		return;
	}

	echo et_builder_get_builder_content_closing_wrapper();
}

/**
 * Get the html representing the post content for the current post.
 *
 * @since 4.0
 *
 * @return string
 */
function et_theme_builder_frontend_render_post_content() {
	static $__prevent_recursion = false;
	global $wp_query;

	if ( is_et_theme_builder_template_preview() ) {
		return et_theme_builder_get_post_content_placeholder();
	}

	if ( ET_Theme_Builder_Layout::get_theme_builder_layout_type() !== ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE ) {
		// Prevent usage on non-body layouts.
		return '';
	}

	if ( ! is_singular() ) {
		// Do not output anything on non-singular pages.
		return '';
	}

	$main_query_post = ET_Post_Stack::get_main_post();

	if ( ! $main_query_post ) {
		// Bail if there is no current post.
		return '';
	}

	if ( true === $__prevent_recursion ) {
		// Failsafe just in case.
		return '';
	}

	$__prevent_recursion = true;
	$html                = '';
	$buffered            = ob_start();

	if ( $buffered ) {
		ET_Post_Stack::replace( $main_query_post );

		ET_Theme_Builder_Layout::begin_theme_builder_layout( get_the_ID() );

		do_action_ref_array( 'loop_start', array( &$wp_query ) );

		/**
		 * Fires before the_content() is called within et_theme_builder_frontend_render_post_content().
		 * This allows filters to detect when the_content() is being called from the Post Content Module.
		 *
		 * @since 5.0.0
		 */
		do_action( 'et_theme_builder_before_render_post_content' );

		the_content();

		/**
		 * Fires after the_content() is called within et_theme_builder_frontend_render_post_content().
		 *
		 * @since 5.0.0
		 */
		do_action( 'et_theme_builder_after_render_post_content' );

		do_action_ref_array( 'loop_end', array( &$wp_query ) );

		ET_Theme_Builder_Layout::end_theme_builder_layout();

		ET_Post_Stack::restore();

		$html = ob_get_clean();
	}

	$__prevent_recursion = false;

	return $html;
}

/**
 * Global flag to track if we're currently rendering post content via et_theme_builder_frontend_render_post_content().
 *
 * @var bool
 */
global $et_theme_builder_rendering_post_content;
$et_theme_builder_rendering_post_content = false;

/**
 * Checks if the_content() is currently being called from within et_theme_builder_frontend_render_post_content().
 *
 * This is used to determine if the_content() is being called from the Post Content Module
 * versus from the default WooCommerce content rendering location.
 *
 * @since 5.0.0
 *
 * @return bool True if rendering post content, false otherwise.
 */
function et_theme_builder_is_rendering_post_content() {
	global $et_theme_builder_rendering_post_content;

	// Check if we're currently inside the post content rendering action.
	return $et_theme_builder_rendering_post_content || doing_action( 'et_theme_builder_before_render_post_content' ) || doing_action( 'et_theme_builder_after_render_post_content' );
}

/**
 * Set the global flag to indicate that we're currently rendering post content.
 *
 * @return void
 */
function set_et_theme_builder_rendering_post_content_flag() {
	global $et_theme_builder_rendering_post_content;
	$et_theme_builder_rendering_post_content = true;
}

/**
 * Clear the global flag to indicate that we're no longer rendering post content.
 *
 * @return void
 */
function clear_et_theme_builder_rendering_post_content_flag() {
	global $et_theme_builder_rendering_post_content;
	$et_theme_builder_rendering_post_content = false;
}

add_action( 'et_theme_builder_before_render_post_content', 'set_et_theme_builder_rendering_post_content_flag' );
add_action( 'et_theme_builder_after_render_post_content', 'clear_et_theme_builder_rendering_post_content_flag' );
