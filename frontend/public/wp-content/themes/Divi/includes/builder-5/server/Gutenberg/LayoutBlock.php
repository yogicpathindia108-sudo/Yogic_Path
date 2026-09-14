<?php
/**
 * Gutenberg: Layout Block Core Class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Gutenberg;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\FrontEnd\Assets\StaticCSS;
use ET\Builder\FrontEnd\Assets\CriticalCSS;
use ET\Builder\FrontEnd\Page;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Packages\Conversion\Conversion;

/**
 * Layout Block class.
 *
 * Handles core Layout Block functionality including registration and frontend rendering using D5 StaticCSS.
 *
 * @since ??
 */
class LayoutBlock {

	/**
	 * Register the divi/layout block type.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function register_block() {
		// Return early if block is already registered.
		if ( \WP_Block_Type_Registry::get_instance()->is_registered( 'divi/layout' ) ) {
			return;
		}

		register_block_type(
			'divi/layout',
			[
				'attributes' => [
					'layoutContent' => [
						'type' => 'string',
					],
				],
			]
		);
	}

	/**
	 * Register WordPress hooks for Layout Block.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_filter( 'render_block', [ self::class, 'render_block' ], 100, 2 );
		add_filter( 'body_class', [ self::class, 'add_body_classnames' ] );
		add_filter( 'get_post_metadata', [ self::class, 'modify_layout_content_builder_meta' ], 10, 4 );
		add_action( 'template_include', [ self::class, 'register_preview_template' ] );
	}

	/**
	 * Check if current page is layout block preview page.
	 *
	 * Delegates to ET_GB_Block_Layout utility class.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_layout_block_preview(): bool {
		return \ET_GB_Block_Layout::is_layout_block_preview();
	}

	/**
	 * Check if current page is using layout block (non-preview).
	 *
	 * Delegates to ET_GB_Block_Layout utility class.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function is_layout_block(): bool {
		return \ET_GB_Block_Layout::is_layout_block();
	}

	/**
	 * Render Layout Block on frontend.
	 *
	 * Uses D5 StaticCSS and Page::custom_css() - NO ET_Builder_Element::get_style().
	 *
	 * @since ??
	 *
	 * @param string $block_content Block content.
	 * @param array  $block         Block data.
	 *
	 * @return string Modified block content.
	 */
	public static function render_block( $block_content, $block ) {
		if ( 'divi/layout' !== $block['blockName'] ) {
			return $block_content;
		}

		global $et_is_layout_block, $et_layout_block_info;

		$template        = self::get_wp_editor_template_on_render();
		$template_id     = isset( $template->wp_id ) ? (int) $template->wp_id : 0;
		$block_to_render = class_exists( 'WP_Block_Supports' ) && ! empty( \WP_Block_Supports::$block_to_render )
			? \WP_Block_Supports::$block_to_render
			: [];

		$et_is_layout_block   = true;
		$et_layout_block_info = [
			'block'           => $block,
			'block_to_render' => $block_to_render,
			'template'        => $template,
		];

		if ( ! empty( $template ) && $template_id > 0 ) {
			StaticCSS::begin_wp_editor_template( $template_id );
		}

		$block_content = do_shortcode( $block_content );

		if ( ! empty( $template ) && $template_id > 0 ) {
			$block_content = et_builder_get_layout_opening_wrapper() . $block_content . et_builder_get_layout_closing_wrapper();

			$wrap = apply_filters( 'et_builder_add_outer_content_wrap', true );

			if ( $wrap ) {
				$block_content = et_builder_get_builder_content_opening_wrapper() . $block_content . et_builder_get_builder_content_closing_wrapper();
			}

			// D5 STYLE MANAGEMENT - Uses Page::custom_css() like ThemeBuilder\Layout does.
			$post_id = is_singular() ? \ET_Post_Stack::get_main_post_id() : $template_id;

			$result                  = StaticCSS::setup_styles_manager( $post_id );
			$styles_manager          = $result['manager'];
			$deferred_styles_manager = $result['deferred'] ?? null;

			if ( StaticCSS::$forced_inline_styles || ! $styles_manager->has_file() || $styles_manager->forced_inline ) {
				$custom = Page::custom_css( $template_id );

				StaticCSS::style_output(
					[
						'styles_manager'          => $styles_manager,
						'deferred_styles_manager' => $deferred_styles_manager,
						'custom'                  => $custom,
						'element_id'              => $template_id,
					]
				);
			}

			StaticCSS::end_wp_editor_template();
		}

		$et_is_layout_block   = false;
		$et_layout_block_info = false;

		return $block_content;
	}

	/**
	 * Add body classnames for layout block preview.
	 *
	 * @since ??
	 *
	 * @param array $classes Body classes.
	 *
	 * @return array Modified body classes.
	 */
	public static function add_body_classnames( $classes ) {
		if ( self::is_layout_block_preview() ) {
			$classes[] = 'et-db';
			$classes[] = 'et-block-layout-preview';
		}

		return $classes;
	}

	/**
	 * Get WP Editor template on render.
	 *
	 * @since ??
	 *
	 * @return object|null Template object or null.
	 */
	public static function get_wp_editor_template_on_render() {
		global $post;

		static $templates_result = null;

		if ( ! function_exists( 'get_block_template' ) ) {
			return null;
		}

		if ( ! is_singular() ) {
			return null;
		}

		if ( ! empty( et_theme_builder_get_template_layouts() ) ) {
			$override_header = et_theme_builder_overrides_layout( ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE );
			$override_body   = et_theme_builder_overrides_layout( ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE );
			$override_footer = et_theme_builder_overrides_layout( ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE );

			if ( $override_header || $override_body || $override_footer ) {
				return null;
			}
		}

		$block_to_render      = class_exists( 'WP_Block_Supports' ) && ! empty( \WP_Block_Supports::$block_to_render )
			? \WP_Block_Supports::$block_to_render
			: [];
		$block_to_render_name = et_()->array_get( $block_to_render, 'blockName', '' );

		if ( 'core/post-content' === $block_to_render_name ) {
			return null;
		}

		$template_type = '';
		$template_slug = '';

		if ( 'core/template-part' === $block_to_render_name ) {
			$template_type = ET_WP_EDITOR_TEMPLATE_PART_POST_TYPE;
			$template_slug = et_()->array_get( $block_to_render, [ 'attrs', 'slug' ], '' );
		} else {
			$template_type = ET_WP_EDITOR_TEMPLATE_POST_TYPE;
			$template_slug = ! empty( $post->page_template ) ? $post->page_template : self::get_default_template_slug();
		}

		$template_type_slug = "{$template_type}-{$template_slug}";

		if ( ! empty( $templates_result[ $template_type_slug ] ) ) {
			return $templates_result[ $template_type_slug ];
		}

		$template = ! empty( $template_type ) && ! empty( $template_slug )
			? get_block_template( get_stylesheet() . '//' . $template_slug, $template_type )
			: null;

		$templates_result[ $template_type_slug ] = $template;

		return $template;
	}

	/**
	 * Get default template slug for current post.
	 *
	 * @since ??
	 *
	 * @return string Template slug.
	 */
	public static function get_default_template_slug() {
		if ( ! is_singular() ) {
			return '';
		}

		if ( is_page() ) {
			return 'page';
		} elseif ( is_single() ) {
			return 'single';
		}

		return '';
	}

	/**
	 * Register preview template for layout block.
	 *
	 * Uses a headerless/footerless template for VB editing.
	 *
	 * @since ??
	 *
	 * @param string $template Template path.
	 *
	 * @return string Modified template path.
	 */
	public static function register_preview_template( $template ) {
		if ( ! self::is_layout_block_preview() ) {
			return $template;
		}

		// Disable admin bar.
		show_admin_bar( false );

		// Use layout block specific template (headerless and footerless).
		return ET_BUILDER_DIR . 'templates/block-layout-preview.php';
	}

	/**
	 * Modify post metadata for layout block preview.
	 *
	 * Forces builder to be enabled and static CSS to be disabled.
	 *
	 * @since ??
	 *
	 * @param mixed  $value     Metadata value.
	 * @param int    $object_id Object ID.
	 * @param string $meta_key  Meta key.
	 * @param bool   $single    Whether to return a single value (unused).
	 *
	 * @return mixed Modified metadata value.
	 */
	public static function modify_layout_content_builder_meta( $value, $object_id, $meta_key, $single ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress filter requires 4 parameters.
		if ( ! self::is_layout_block_preview() ) {
			return $value;
		}

		// Force enable builder on layout block preview page request.
		if ( '_et_pb_use_builder' === $meta_key ) {
			return 'on';
		}

		// Force disable static CSS on layout block preview page request.
		if ( '_et_pb_static_css_file' === $meta_key ) {
			return 'off';
		}

		return $value;
	}
}
