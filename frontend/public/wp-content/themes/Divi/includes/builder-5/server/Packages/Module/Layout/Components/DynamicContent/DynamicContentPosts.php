<?php
/**
 * Module: DynamicContentPosts class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Layout\Components\DynamicContent;

use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Module: DynamicContentPosts class.
 *
 * Some of dynamic content values maybe related to current page or post used. Sometimes,
 * the code to resolve the value is not simple and is used multiple times in the code. This
 * class handles dynamic content value with those cases.
 *
 * This includes:
 * - Current page title. It can be post, page, home, archive, etc.
 * - Taxonomy by post type. It can be category, tag, etc.
 *
 * @since ??
 */
class DynamicContentPosts {

	/**
	 * Get the title for the current page.
	 *
	 * This function retrieves the title for the current page based on different scenarios.
	 *
	 * @since ??
	 *
	 * @param int   $post_id The ID of the post. If the post ID is 0, the current post ID will be used.
	 *                       Defaults to 0.
	 * @param array $data_args Optional. Dynamic content data arguments. Defaults to [].
	 *
	 * @return string The post title.
	 *                If `is_front_page() === true`, the title will be "Home".
	 *                If `is_home() === true`, the title will be "Blog".
	 *                If `is_404() === true`, the title will be "No Results Found".
	 *                If `is_search() === true`, the title will be "Results for "Search Query"".
	 *                If `is_author() === true`, the title will be the queried author name when available,
	 *                otherwise the current post author name.
	 *                If `is_post_type_archive() === true`, the title will be the post type archive title.
	 *                If `is_category() === true`, the title will be the category title.
	 *                If `is_date() === true`, the title will be the formatted date archive title:
	 *                - Year archive: "Year: YYYY" (e.g., "Year: 2023")
	 *                - Month archive: "Month: F Y" (e.g., "Month: January 2023")
	 *                - Day archive: "Day: F j, Y" (e.g., "Day: January 1, 2023")
	 *
	 * @example
	 * ```php
	 * $post_id = 123; // Set a custom post ID
	 * $title = DynamicContentPosts::get_current_page_title($post_id);
	 * echo $title;
	 * ```
	 *
	 * @example
	 * ```php
	 * $title = DynamicContentPosts::get_current_page_title(); // Current post ID will be used
	 * echo $title;
	 * ```
	 */
	public static function get_current_page_title( $post_id = 0, array $data_args = [] ): string {
		$is_auto_resolved_post_id = 0 === $post_id;

		if ( $is_auto_resolved_post_id ) {
			$post_id = self::_resolve_auto_post_id();
		}

		$post_id = (int) $post_id;

		// When rendering inner content (e.g., inside Blog module loop), always return
		// the actual post title regardless of the outer page context (search, archive, etc.).
		// This ensures Post Title modules inside Blog excerpts show the correct post title
		// instead of contextual titles like "Results for [keyword]".
		if ( BlockParserStore::is_rendering_inner_content() ) {
			return get_the_title( $post_id );
		}

		// In REST/VB archive editing, runtime query conditionals can be unavailable.
		// Use archive term context fallback before defaulting to post title.
		$archive_term = DynamicContentUtils::get_archive_term_context( $data_args );
		if ( $archive_term instanceof \WP_Term ) {
			return $archive_term->name;
		}

		// phpcs:ignore ET.Comments.Todo.TodoFound -- TODO has issue reference (#25149) but doesn't match exact PHPCS format requirement.
		// TODO feat(D5, Theme Builder): Replace it once the Theme Builder is implemented in D5.
		// @see https://github.com/elegantthemes/Divi/issues/25149.
		if ( self::_should_return_post_title( $is_auto_resolved_post_id ) ) {
			return get_the_title( $post_id );
		}

		if ( is_front_page() ) {
			return __( 'Home', 'et_builder_5' );
		}

		if ( is_home() ) {
			return __( 'Blog', 'et_builder_5' );
		}

		if ( is_404() ) {
			return __( 'No Results Found', 'et_builder_5' );
		}

		if ( is_search() ) {
			return sprintf( __( 'Results for "%1$s"', 'et_builder_5' ), get_search_query() );
		}

		if ( is_author() ) {
			$queried_object = get_queried_object();

			if ( $queried_object instanceof \WP_User && ! empty( $queried_object->display_name ) ) {
				return $queried_object->display_name;
			}

			return get_the_author();
		}

		if ( is_post_type_archive() ) {
			return post_type_archive_title( '', false );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			return single_term_title( '', false );
		}

		if ( is_date() ) {
			$formatted_title = ModuleUtils::get_date_archive_title();
			if ( ! empty( $formatted_title ) ) {
				return $formatted_title;
			}
			// Fallback to WordPress core function if utility returns empty.
			return get_the_archive_title();
		}

		return get_the_archive_title();
	}

	/**
	 * Whether the main WordPress query is a singular content request.
	 *
	 * Theme Builder layout rendering can spoof or invalidate the global post, so callers must
	 * infer singular context from the main query state rather than from `ET_Post_Stack` alone.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function _is_main_query_singular_content(): bool {
		global $wp_query;

		if ( ! $wp_query instanceof \WP_Query ) {
			return false;
		}

		if ( $wp_query->is_archive || $wp_query->is_search ) {
			return false;
		}

		if ( $wp_query->is_home && ! $wp_query->is_front_page ) {
			return false;
		}

		return (bool) $wp_query->is_singular;
	}

	/**
	 * Resolve the post ID when the caller did not provide one.
	 *
	 * In a Theme Builder layout, prefer the stack's main post ID. If the stack is invalid
	 * (e.g., The Events Calendar Default Page Template leaves `$wp_query->post` empty) and
	 * the main query is still singular, fall back to the queried object. Otherwise fall back
	 * to the current global post ID.
	 *
	 * @since ??
	 *
	 * @return int The resolved post ID.
	 */
	private static function _resolve_auto_post_id(): int {
		if ( \ET_Theme_Builder_Layout::is_theme_builder_layout() ) {
			$post_id = \ET_Post_Stack::get_main_post_id();

			if ( 0 < $post_id ) {
				return $post_id;
			}

			if ( self::_is_main_query_singular_content() ) {
				$post_id = get_queried_object_id();

				if ( 0 < $post_id ) {
					return $post_id;
				}
			}
		}

		return get_the_ID();
	}

	/**
	 * Whether the current page should return a post title instead of a contextual title.
	 *
	 * @since ??
	 *
	 * @param bool $is_auto_resolved_post_id Whether the post ID was auto-resolved.
	 *
	 * @return bool
	 */
	private static function _should_return_post_title( bool $is_auto_resolved_post_id ): bool {
		if ( is_singular() ) {
			return true;
		}

		if ( $is_auto_resolved_post_id && self::_is_main_query_singular_content() ) {
			return true;
		}

		if ( ! \ET_Theme_Builder_Layout::is_theme_builder_layout() && ! is_archive() ) {
			return true;
		}

		return false;
	}

	/**
	 * Get all public taxonomies associated with a given post type.
	 *
	 * This function retrieves all the public taxonomies (categories and tags) associated with a specified post type.
	 *
	 * @since ??
	 *
	 * @param string $post_type The post type for which to retrieve the taxonomies.
	 *
	 * @return array An array of taxonomies associated with the specified post type.
	 *               The array is in the format of `[taxonomy_name => taxonomy_label]`.
	 */
	public static function get_taxonomy_by_post_type( string $post_type ): array {
		$taxonomies = get_object_taxonomies( $post_type, 'object' );
		$list       = [];

		if ( empty( $taxonomies ) ) {
			return $list;
		}

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! empty( $taxonomy ) && $taxonomy->public && $taxonomy->show_ui ) {
				$list[ $taxonomy->name ] = $taxonomy->label;
			}
		}

		return $list;
	}
}
