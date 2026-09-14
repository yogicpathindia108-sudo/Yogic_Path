<?php
/**
 * Module Library: Breadcrumbs module.
 *
 * @package Divi
 * @since   ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Breadcrumbs;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\DynamicData\DynamicData;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewUtils;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use WP_Block;
use WP_Post;
use WP_Term;
use WP_User;

/**
 * Breadcrumbs module class.
 *
 * @since ??
 */
class BreadcrumbsModule implements DependencyInterface {
	/**
	 * Breadcrumbs module script data.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Optional. Script data args.
	 *
	 *     @type ModuleElements $elements ModuleElements instance.
	 * }
	 *
	 * @return void
	 */
	public static function module_script_data( array $args ): void {
		$id             = $args['id'] ?? '';
		$name           = $args['name'] ?? '';
		$selector       = $args['selector'] ?? '';
		$attrs          = $args['attrs'] ?? [];
		$store_instance = $args['storeInstance'] ?? null;
		$elements       = $args['elements'];

		$elements->script_data(
			[
				'attrName' => 'module',
			]
		);
		$elements->script_data(
			[
				'attrName' => 'breadcrumb',
			]
		);
		$elements->script_data(
			[
				'attrName' => 'breadcrumbLink',
			]
		);
		$elements->script_data(
			[
				'attrName' => 'home',
			]
		);
		$elements->script_data(
			[
				'attrName' => 'separator',
			]
		);

		$home_inner_content      = self::_normalize_grouped_inner_content( $attrs['home']['innerContent'] ?? [] );
		$separator_inner_content = self::_normalize_grouped_inner_content( $attrs['separator']['innerContent'] ?? [] );

		MultiViewScriptData::set(
			[
				'id'            => $id,
				'name'          => $name,
				'selector'      => $selector,
				'storeInstance' => $store_instance,
				'setContent'    => [
					[
						'selector'      => "{$selector} .et_pb_breadcrumbs--trail a.et_pb_breadcrumbs--home",
						'hoverSelector' => "{$selector} .et_pb_breadcrumbs--trail a.et_pb_breadcrumbs--home",
						'data'          => $home_inner_content,
						'valueResolver' => static function ( $value ): string {
							$text = DynamicData::get_processed_dynamic_data( (string) ( $value['text'] ?? '' ) ) ?? '';
							$text = trim( $text );

							return '' === $text ? __( 'Home', 'et_builder_5' ) : $text;
						},
						'sanitizer'     => 'esc_html',
					],
					[
						'selector'      => "{$selector} .et_pb_breadcrumbs--trail .et_pb_breadcrumbs--separator",
						'hoverSelector' => "{$selector} .et_pb_breadcrumbs--trail .et_pb_breadcrumbs--separator",
						'data'          => $separator_inner_content,
						'valueResolver' => static function ( $value ): string {
							$separator = DynamicData::get_processed_dynamic_data( (string) ( $value['text'] ?? '' ) ) ?? '';

							return '' === $separator ? '/' : $separator;
						},
						'sanitizer'     => 'esc_html',
					],
				],
				'setAttrs'      => [
					[
						'selector'      => "{$selector} .et_pb_breadcrumbs--trail a.et_pb_breadcrumbs--home",
						'hoverSelector' => "{$selector} .et_pb_breadcrumbs--trail a.et_pb_breadcrumbs--home",
						'data'          => [
							'href' => $home_inner_content,
						],
						'subName'       => 'url',
						'valueResolver' => static function ( $value ): string {
							$resolved_url = DynamicData::get_processed_dynamic_data( (string) $value ) ?? '';
							$resolved_url = trim( $resolved_url );

							return '' === $resolved_url ? get_home_url() : $resolved_url;
						},
						'sanitizers'    => [
							'href' => 'esc_url',
						],
						'tag'           => 'a',
					],
				],
			]
		);
	}

	/**
	 * Gets a value from `trail.advanced.*` based on the default breakpoint and state.
	 *
	 * @since ??
	 *
	 * @param array       $attrs     Module attrs.
	 * @param string      $attr_name Attr key.
	 * @param string|null $fallback  Fallback value.
	 *
	 * @return string|null
	 */
	private static function _get_trail_advanced_value( array $attrs, string $attr_name, ?string $fallback = null ): ?string {
		$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
		$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
		$default_state           = $breakpoints_states_info->default_state();

		return $attrs['trail']['advanced'][ $attr_name ][ $default_breakpoint ][ $default_state ] ?? $fallback;
	}

	/**
	 * Gets a value from grouped `*.innerContent` based on default breakpoint/state.
	 *
	 * @since ??
	 *
	 * @param array       $attrs        Module attrs.
	 * @param string      $element_name Element key.
	 * @param string      $attr_name    Attr key.
	 * @param string|null $fallback     Fallback value.
	 *
	 * @return string|null
	 */
	private static function _get_inner_content_value( array $attrs, string $element_name, string $attr_name, ?string $fallback = null ): ?string {
		$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
		$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
		$default_state           = $breakpoints_states_info->default_state();

		$inner_content = self::_normalize_grouped_inner_content( $attrs[ $element_name ]['innerContent'] ?? [] );

		return $inner_content[ $default_breakpoint ][ $default_state ][ $attr_name ] ?? $fallback;
	}

	/**
	 * Normalizes grouped/legacy/malformed innerContent into grouped breakpoint/state shape.
	 *
	 * @since ??
	 *
	 * @param mixed $inner_content Inner content attributes.
	 *
	 * @return array
	 */
	private static function _normalize_grouped_inner_content( $inner_content ): array {
		if ( ! is_array( $inner_content ) ) {
			return [];
		}

		$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
		$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
		$default_state           = $breakpoints_states_info->default_state();

		// Grouped shape: [breakpoint][state][subField].
		if ( isset( $inner_content[ $default_breakpoint ][ $default_state ] ) && is_array( $inner_content[ $default_breakpoint ][ $default_state ] ) ) {
			return $inner_content;
		}

		$normalized = [];

		// Legacy shape: [subField][breakpoint][state].
		foreach ( $inner_content as $sub_field => $breakpoints ) {
			if ( ! is_array( $breakpoints ) ) {
				continue;
			}

			foreach ( $breakpoints as $breakpoint => $states ) {
				if ( ! is_array( $states ) ) {
					continue;
				}

				foreach ( $states as $state => $value ) {
					$normalized[ $breakpoint ][ $state ][ $sub_field ] = $value;
				}
			}
		}

		return $normalized;
	}

	/**
	 * Builds breadcrumb items from the current WordPress context.
	 *
	 * @since ??
	 *
	 * @param string $home_text Home text.
	 * @param string $home_url  Home URL.
	 * @param array  $context   Optional request context.
	 *
	 * @return array<int, array{label:string,url:string,isCurrent:bool}>
	 */
	private static function _get_breadcrumb_items( string $home_text, string $home_url, array $context = [] ): array {
		$items   = [
			self::_create_breadcrumb_item( $home_text, $home_url ),
		];
		$context = self::_parse_breadcrumb_context( $context );

		if ( self::_is_home_request( $context['request_type'], $context ) ) {
			return $items;
		}

		$posts_page_items = self::_get_posts_page_index_items( $items, $context );

		if ( null !== $posts_page_items ) {
			return $posts_page_items;
		}

		$archive_items = self::_get_archive_preview_items( $items, $context );

		if ( null !== $archive_items ) {
			return $archive_items;
		}

		$preview_post_items = self::_get_preview_post_items( $items, $context );

		if ( null !== $preview_post_items ) {
			return $preview_post_items;
		}

		$singular_items = self::_get_singular_items( $items );

		if ( null !== $singular_items ) {
			return $singular_items;
		}

		$taxonomy_items = self::_get_taxonomy_items( $items );

		if ( null !== $taxonomy_items ) {
			return $taxonomy_items;
		}

		$search_items = self::_get_search_items( $items );

		if ( null !== $search_items ) {
			return $search_items;
		}

		$error_items = self::_get_404_items( $items );

		if ( null !== $error_items ) {
			return $error_items;
		}

		$author_items = self::_get_author_items( $items );

		if ( null !== $author_items ) {
			return $author_items;
		}

		return self::_get_fallback_items( $items );
	}

	/**
	 * Creates a breadcrumb item array.
	 *
	 * @since ??
	 *
	 * @param string $label      Breadcrumb label.
	 * @param string $url        Breadcrumb URL.
	 * @param bool   $is_current Whether the item is current.
	 *
	 * @return array{label:string,url:string,isCurrent:bool}
	 */
	private static function _create_breadcrumb_item( string $label, string $url = '', bool $is_current = false ): array {
		return [
			'label'     => $label,
			'url'       => $url,
			'isCurrent' => $is_current,
		];
	}

	/**
	 * Parses breadcrumb context into a normalized structure.
	 *
	 * @since ??
	 *
	 * @param array $context Optional request context.
	 *
	 * @return array{request_type:string,page_id:int,page_title:string,term_id:int,taxonomy:string}
	 */
	private static function _parse_breadcrumb_context( array $context ): array {
		$current_page            = $context['current_page'] ?? [];
		$main_loop_settings_data = isset( $current_page['mainLoopSettingsData'] ) && is_array( $current_page['mainLoopSettingsData'] )
			? $current_page['mainLoopSettingsData']
			: [];

		return [
			'request_type' => sanitize_text_field( (string) ( $context['request_type'] ?? '' ) ),
			'page_id'      => isset( $current_page['id'] ) ? absint( $current_page['id'] ) : 0,
			'page_title'   => isset( $current_page['title'] ) ? sanitize_text_field( (string) $current_page['title'] ) : '',
			'term_id'      => isset( $main_loop_settings_data['termId'] ) ? absint( $main_loop_settings_data['termId'] ) : 0,
			'taxonomy'     => isset( $main_loop_settings_data['taxonomy'] ) ? sanitize_key( (string) $main_loop_settings_data['taxonomy'] ) : '',
		];
	}

	/**
	 * Determines whether the current request is the posts page index (not the site front page).
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function _is_posts_page_index(): bool {
		$page_for_posts = (int) get_option( 'page_for_posts' );

		return $page_for_posts > 0 && is_home() && ! is_front_page();
	}

	/**
	 * Determines whether the current request should only show the home item.
	 *
	 * @since ??
	 *
	 * @param string $request_type Request type.
	 * @param array  $context      Parsed breadcrumb context.
	 *
	 * @return bool
	 */
	private static function _is_home_request( string $request_type, array $context = [] ): bool {
		if ( self::_is_posts_page_index() ) {
			return false;
		}

		$page_for_posts = (int) get_option( 'page_for_posts' );

		if ( $page_for_posts > 0 && 'home' === $request_type && '' !== $context['page_title'] ) {
			$posts_page_title = get_the_title( $page_for_posts );

			if ( $posts_page_title === $context['page_title'] ) {
				return false;
			}
		}

		return 'home' === $request_type || is_front_page() || is_home();
	}

	/**
	 * Builds breadcrumb items for the WordPress posts page index.
	 *
	 * @since ??
	 *
	 * @param array<int, array{label:string,url:string,isCurrent:bool}>                            $items   Seed breadcrumb items.
	 * @param array{request_type:string,page_id:int,page_title:string,term_id:int,taxonomy:string} $context Parsed breadcrumb context.
	 *
	 * @return array<int, array{label:string,url:string,isCurrent:bool}>|null
	 */
	private static function _get_posts_page_index_items( array $items, array $context ): ?array {
		$page_for_posts = (int) get_option( 'page_for_posts' );

		if ( 0 >= $page_for_posts ) {
			return null;
		}

		$is_posts_page_index = self::_is_posts_page_index();

		if ( ! $is_posts_page_index ) {
			$posts_page_title = get_the_title( $page_for_posts );

			if ( 'home' !== $context['request_type'] || $posts_page_title !== $context['page_title'] ) {
				return null;
			}
		}

		$title = '' !== $context['page_title'] ? $context['page_title'] : get_the_title( $page_for_posts );

		if ( '' === $title ) {
			return null;
		}

		$items[] = self::_create_breadcrumb_item( $title, '', true );

		return $items;
	}

	/**
	 * Builds archive preview breadcrumb items when current page context includes a term.
	 *
	 * @since ??
	 *
	 * @param array<int, array{label:string,url:string,isCurrent:bool}>                            $items   Seed breadcrumb items.
	 * @param array{request_type:string,page_id:int,page_title:string,term_id:int,taxonomy:string} $context Parsed breadcrumb context.
	 *
	 * @return array<int, array{label:string,url:string,isCurrent:bool}>|null
	 */
	private static function _get_archive_preview_items( array $items, array $context ): ?array {
		if ( 'archive' !== $context['request_type'] ) {
			return null;
		}

		$archive_term_id = 0 < $context['page_id'] ? $context['page_id'] : $context['term_id'];
		$preview_term    = null;

		if ( 0 < $archive_term_id ) {
			$preview_term = '' !== $context['taxonomy'] ? get_term( $archive_term_id, $context['taxonomy'] ) : get_term( $archive_term_id );
		}

		if ( $preview_term instanceof WP_Term ) {
			$items = self::_append_term_ancestor_items( $items, $preview_term );

			return self::_append_term_item( $items, $preview_term, true );
		}

		if ( '' !== $context['page_title'] ) {
			$items[] = self::_create_breadcrumb_item( $context['page_title'], '', true );

			return $items;
		}

		return null;
	}

	/**
	 * Builds preview breadcrumb items from explicit current page context.
	 *
	 * @since ??
	 *
	 * @param array<int, array{label:string,url:string,isCurrent:bool}>                            $items   Seed breadcrumb items.
	 * @param array{request_type:string,page_id:int,page_title:string,term_id:int,taxonomy:string} $context Parsed breadcrumb context.
	 *
	 * @return array<int, array{label:string,url:string,isCurrent:bool}>|null
	 */
	private static function _get_preview_post_items( array $items, array $context ): ?array {
		if ( 0 === $context['page_id'] || in_array( $context['request_type'], [ 'home', '404' ], true ) ) {
			return null;
		}

		$preview_post = get_post( $context['page_id'] );

		if ( ! $preview_post instanceof WP_Post ) {
			return null;
		}

		return self::_build_post_items( $items, $preview_post );
	}

	/**
	 * Builds breadcrumb items for runtime singular requests.
	 *
	 * @since ??
	 *
	 * @param array<int, array{label:string,url:string,isCurrent:bool}> $items Seed breadcrumb items.
	 *
	 * @return array<int, array{label:string,url:string,isCurrent:bool}>|null
	 */
	private static function _get_singular_items( array $items ): ?array {
		if ( ! is_singular() ) {
			return null;
		}

		$post = get_queried_object();

		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		return self::_build_post_items( $items, $post );
	}

	/**
	 * Builds breadcrumb items for runtime taxonomy requests.
	 *
	 * @since ??
	 *
	 * @param array<int, array{label:string,url:string,isCurrent:bool}> $items Seed breadcrumb items.
	 *
	 * @return array<int, array{label:string,url:string,isCurrent:bool}>|null
	 */
	private static function _get_taxonomy_items( array $items ): ?array {
		if ( ! is_category() && ! is_tag() && ! is_tax() ) {
			return null;
		}

		$term = get_queried_object();

		if ( ! $term instanceof WP_Term ) {
			return null;
		}

		$items = self::_append_term_ancestor_items( $items, $term );

		return self::_append_term_item( $items, $term, true );
	}

	/**
	 * Builds breadcrumb items for runtime search requests.
	 *
	 * @since ??
	 *
	 * @param array<int, array{label:string,url:string,isCurrent:bool}> $items Seed breadcrumb items.
	 *
	 * @return array<int, array{label:string,url:string,isCurrent:bool}>|null
	 */
	private static function _get_search_items( array $items ): ?array {
		if ( ! is_search() ) {
			return null;
		}

		$items[] = self::_create_breadcrumb_item(
			sprintf(
				/* translators: %s: search query. */
				__( 'Search Results for "%s"', 'et_builder_5' ),
				get_search_query()
			),
			'',
			true
		);

		return $items;
	}

	/**
	 * Builds breadcrumb items for runtime 404 requests.
	 *
	 * @since ??
	 *
	 * @param array<int, array{label:string,url:string,isCurrent:bool}> $items Seed breadcrumb items.
	 *
	 * @return array<int, array{label:string,url:string,isCurrent:bool}>|null
	 */
	private static function _get_404_items( array $items ): ?array {
		if ( ! is_404() ) {
			return null;
		}

		$items[] = self::_create_breadcrumb_item( __( '404 Not Found', 'et_builder_5' ), '', true );

		return $items;
	}

	/**
	 * Builds breadcrumb items for runtime author archive requests.
	 *
	 * Uses the queried author object instead of `get_the_archive_title()`.
	 * Core archive titles call `get_the_author()`, which follows the current
	 * post after Theme Builder swaps in a layout post and can show the wrong name.
	 *
	 * @since ??
	 *
	 * @param array<int, array{label:string,url:string,isCurrent:bool}> $items Seed breadcrumb items.
	 *
	 * @return array<int, array{label:string,url:string,isCurrent:bool}>|null
	 */
	private static function _get_author_items( array $items ): ?array {
		if ( ! is_author() ) {
			return null;
		}

		$author = get_queried_object();

		if ( ! $author instanceof WP_User ) {
			return null;
		}

		$items[] = self::_create_breadcrumb_item(
			sprintf(
				/* translators: 1: Title prefix. 2: Title. */
				_x( '%1$s %2$s', 'archive title', 'et_builder_5' ),
				_x( 'Author:', 'author archive title prefix', 'et_builder_5' ),
				$author->display_name
			),
			'',
			true
		);

		return $items;
	}

	/**
	 * Builds breadcrumb items for the generic fallback branch.
	 *
	 * @since ??
	 *
	 * @param array<int, array{label:string,url:string,isCurrent:bool}> $items Seed breadcrumb items.
	 *
	 * @return array<int, array{label:string,url:string,isCurrent:bool}>
	 */
	private static function _get_fallback_items( array $items ): array {
		// Strip core archive-title markup (e.g. Year: <span>2020</span>) so labels stay plain text under esc_html.
		$title = wp_strip_all_tags( get_the_archive_title() );

		if ( '' === $title ) {
			$title = wp_strip_all_tags( wp_get_document_title() );
		}

		$items[] = self::_create_breadcrumb_item(
			'' !== $title ? $title : __( 'Current Page', 'et_builder_5' ),
			'',
			true
		);

		return $items;
	}

	/**
	 * Appends hierarchical breadcrumb items for a post object.
	 *
	 * @since ??
	 *
	 * @param array<int, array{label:string,url:string,isCurrent:bool}> $items Seed breadcrumb items.
	 * @param WP_Post                                                   $post  Post object.
	 *
	 * @return array<int, array{label:string,url:string,isCurrent:bool}>
	 */
	private static function _build_post_items( array $items, WP_Post $post ): array {
		if ( 'post' === $post->post_type ) {
			$items = self::_append_post_category_items( $items, $post );
		} else {
			$items = self::_append_post_ancestor_items( $items, $post );
		}

		$items[] = self::_create_breadcrumb_item( get_the_title( $post->ID ), '', true );

		return $items;
	}

	/**
	 * Appends ancestor breadcrumb items for a taxonomy term.
	 *
	 * @since ??
	 *
	 * @param array<int, array{label:string,url:string,isCurrent:bool}> $items Seed breadcrumb items.
	 * @param WP_Term                                                   $term  Term object.
	 *
	 * @return array<int, array{label:string,url:string,isCurrent:bool}>
	 */
	private static function _append_term_ancestor_items( array $items, WP_Term $term ): array {
		$ancestor_ids = array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) );

		foreach ( $ancestor_ids as $ancestor_id ) {
			$ancestor_term = get_term( $ancestor_id, $term->taxonomy );

			if ( $ancestor_term instanceof WP_Term ) {
				$items[] = self::_create_breadcrumb_item( $ancestor_term->name, get_term_link( $ancestor_term ) );
			}
		}

		return $items;
	}

	/**
	 * Appends a breadcrumb item for a taxonomy term.
	 *
	 * @since ??
	 *
	 * @param array<int, array{label:string,url:string,isCurrent:bool}> $items      Seed breadcrumb items.
	 * @param WP_Term                                                   $term       Term object.
	 * @param bool                                                      $is_current Whether the term is current.
	 *
	 * @return array<int, array{label:string,url:string,isCurrent:bool}>
	 */
	private static function _append_term_item( array $items, WP_Term $term, bool $is_current ): array {
		$items[] = self::_create_breadcrumb_item(
			$term->name,
			$is_current ? '' : get_term_link( $term ),
			$is_current
		);

		return $items;
	}

	/**
	 * Appends breadcrumb items for a post's primary category path.
	 *
	 * @since ??
	 *
	 * @param array<int, array{label:string,url:string,isCurrent:bool}> $items Seed breadcrumb items.
	 * @param WP_Post                                                   $post  Post object.
	 *
	 * @return array<int, array{label:string,url:string,isCurrent:bool}>
	 */
	private static function _append_post_category_items( array $items, WP_Post $post ): array {
		$categories = get_the_category( $post->ID );
		$category   = $categories[0] ?? null;

		if ( ! $category instanceof WP_Term ) {
			return $items;
		}

		$items = self::_append_term_ancestor_items( $items, $category );

		return self::_append_term_item( $items, $category, false );
	}

	/**
	 * Appends ancestor breadcrumb items for a hierarchical post.
	 *
	 * @since ??
	 *
	 * @param array<int, array{label:string,url:string,isCurrent:bool}> $items Seed breadcrumb items.
	 * @param WP_Post                                                   $post  Post object.
	 *
	 * @return array<int, array{label:string,url:string,isCurrent:bool}>
	 */
	private static function _append_post_ancestor_items( array $items, WP_Post $post ): array {
		$ancestor_ids = array_reverse( get_post_ancestors( $post->ID ) );

		if ( empty( $ancestor_ids ) ) {
			return $items;
		}

		$front_page_id = 'page' === get_option( 'show_on_front' )
			? (int) get_option( 'page_on_front' )
			: 0;

		foreach ( $ancestor_ids as $ancestor_id ) {
			if ( $front_page_id > 0 && ( (int) $ancestor_id ) === $front_page_id ) {
				continue;
			}

			$items[] = self::_create_breadcrumb_item(
				get_the_title( $ancestor_id ),
				get_permalink( $ancestor_id )
			);
		}

		return $items;
	}

	/**
	 * Returns rendered breadcrumb HTML.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Breadcrumb options.
	 *
	 *     @type string     $home_text        Home text.
	 *     @type string     $home_url         Home URL.
	 *     @type string     $separator        Separator character.
	 *     @type string     $html_tag         HTML tag for wrapper.
	 *     @type string     $request_type     Request type.
	 *     @type array      $current_page     Current page context.
	 *     @type bool       $use_placeholders Whether to use placeholders.
	 * }
	 *
	 * @return string
	 */
	public static function get_breadcrumb( array $args = [] ): string {
		$use_placeholders = $args['use_placeholders'] ?? false;
		$home_text        = isset( $args['home_text'] ) ? $args['home_text'] : __( 'Home', 'et_builder_5' );
		$home_url         = isset( $args['home_url'] ) ? $args['home_url'] : get_home_url();
		$separator        = isset( $args['separator'] ) ? $args['separator'] : '/';
		$html_tag         = isset( $args['html_tag'] ) ? $args['html_tag'] : 'nav';
		$request_type     = isset( $args['request_type'] ) ? $args['request_type'] : '';
		$current_page     = isset( $args['current_page'] ) ? $args['current_page'] : [];

		if ( $use_placeholders ) {
			$home_text = '%HOME_TEXT%';
			$home_url  = '%HOME_URL%';
			$separator = '%SEPARATOR%';
		} else {
			$home_text = DynamicData::get_processed_dynamic_data( (string) $home_text ) ?? '';
			$home_url  = DynamicData::get_processed_dynamic_data( (string) $home_url ) ?? '';
			$separator = DynamicData::get_processed_dynamic_data( (string) $separator ) ?? '';
			$home_text = '' === trim( (string) $home_text ) ? __( 'Home', 'et_builder_5' ) : (string) $home_text;
			$home_url  = '' === trim( (string) $home_url ) ? get_home_url() : (string) $home_url;
			$separator = '' === (string) $separator ? '/' : (string) $separator;
		}

		$allowed_tags = [ 'nav', 'div', 'span', 'p' ];
		$html_tag     = in_array( $html_tag, $allowed_tags, true ) ? $html_tag : 'nav';

		$items = self::_get_breadcrumb_items(
			$home_text,
			$home_url,
			[
				'request_type' => $request_type,
				'current_page' => is_array( $current_page ) ? $current_page : [],
			]
		);

		if ( 0 === count( $items ) ) {
			return '';
		}

		$item_html = [];

		foreach ( $items as $index => $item ) {
			$is_last_item = count( $items ) - 1 === $index;
			$label        = (string) $item['label'];
			$url          = (string) $item['url'];

			if ( ! $is_last_item && '' !== $url ) {
				$link_classes = 0 === $index ? 'et_pb_breadcrumbs--breadcrumb et_pb_breadcrumbs--home' : 'et_pb_breadcrumbs--breadcrumb';

				$item_html[] = HTMLUtility::render(
					[
						'tag'        => 'a',
						'tagEscaped' => true,
						'attributes' => [
							'href'  => '%HOME_URL%' === $url ? $url : esc_url( $url ),
							'class' => $link_classes,
						],
						'children'   => $label,
					]
				);
			} else {
				$item_html[] = HTMLUtility::render(
					[
						'tag'        => 'span',
						'tagEscaped' => true,
						'attributes' => [
							'class' => 'et_pb_breadcrumbs--current et_pb_breadcrumbs--breadcrumb',
						],
						'children'   => $label,
					]
				);
			}

			if ( ! $is_last_item ) {
				$item_html[] = HTMLUtility::render(
					[
						'tag'        => 'span',
						'tagEscaped' => true,
						'attributes' => [
							'class' => 'et_pb_breadcrumbs--separator',
						],
						'children'   => $separator,
					]
				);
			}
		}

		return HTMLUtility::render(
			[
				'tag'               => $html_tag,
				'tagEscaped'        => true,
				'attributes'        => [
					'class'      => 'et_pb_breadcrumbs--trail',
					'aria-label' => __( 'Breadcrumb', 'et_builder_5' ),
				],
				'children'          => implode( '', $item_html ),
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);
	}

	/**
	 * Generates classnames for the module.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Module classname arguments.
	 *
	 *     @type object $classnamesInstance Module classnames instance.
	 *     @type array  $attrs              Block attributes for rendering.
	 * }
	 *
	 * @return void
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					'attrs' => $attrs['module']['decoration'] ?? [],
				]
			)
		);
	}

	/**
	 * Breadcrumbs module style components.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Style generation args.
	 *
	 *     @type string         $id            Module ID.
	 *     @type string         $name          Module name.
	 *     @type int            $orderIndex    Module order index.
	 *     @type int            $storeInstance Store instance.
	 *     @type string         $orderClass    Module order class selector.
	 *     @type array          $attrs         Module attrs.
	 *     @type array          $settings      Module settings.
	 *     @type ModuleElements $elements      ModuleElements instance.
	 * }
	 *
	 * @return void
	 */
	public static function module_styles( array $args ): void {
		$attrs    = $args['attrs'] ?? [];
		$elements = $args['elements'];
		$settings = $args['settings'] ?? [];

		Style::add(
			[
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => [
					$elements->style(
						[
							'attrName'   => 'module',
							'styleProps' => [
								'disabledOn' => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
							],
						]
					),
					$elements->style(
						[
							'attrName' => 'breadcrumb',
						]
					),
					$elements->style(
						[
							'attrName' => 'breadcrumbLink',
						]
					),
					$elements->style(
						[
							'attrName' => 'home',
						]
					),
					$elements->style(
						[
							'attrName' => 'separator',
						]
					),
					CssStyle::style(
						[
							'selector' => $args['orderClass'],
							'attr'     => $attrs['css'] ?? [],
						]
					),
				],
			]
		);
	}

	/**
	 * Render callback for the breadcrumbs module.
	 *
	 * @since ??
	 *
	 * @param array          $attrs    Block attrs.
	 * @param string         $content  Block content.
	 * @param WP_Block       $block    Parsed block.
	 * @param ModuleElements $elements Module elements.
	 *
	 * @return string
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements ): string {
		$home_text = self::_get_inner_content_value( $attrs, 'home', 'text', __( 'Home', 'et_builder_5' ) );
		$home_url  = self::_get_inner_content_value( $attrs, 'home', 'url', get_home_url() );
		$separator = self::_get_inner_content_value( $attrs, 'separator', 'text', '/' );
		$html_tag  = self::_get_trail_advanced_value( $attrs, 'htmlTag', 'nav' );

		$trail_html = self::get_breadcrumb(
			[
				'home_text' => $home_text,
				'home_url'  => $home_url,
				'separator' => $separator,
				'html_tag'  => $html_tag,
			]
		);

		if ( '' === $trail_html ) {
			return '';
		}

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		return Module::render(
			[
				'orderIndex'          => $block->parsed_block['orderIndex'],
				'storeInstance'       => $block->parsed_block['storeInstance'],
				'attrs'               => $attrs,
				'elements'            => $elements,
				'id'                  => $block->parsed_block['id'],
				'name'                => $block->block_type->name,
				'classnamesFunction'  => [ self::class, 'module_classnames' ],
				'moduleCategory'      => $block->block_type->category,
				'stylesComponent'     => [ self::class, 'module_styles' ],
				'scriptDataComponent' => [ self::class, 'module_script_data' ],
				'parentAttrs'         => $parent->attrs ?? [],
				'parentId'            => $parent->id ?? '',
				'parentName'          => $parent->block_name ?? '',
				'children'            => $elements->style_components(
					[
						'attrName' => 'module',
					]
				) . HTMLUtility::render(
					[
						'tag'               => 'div',
						'tagEscaped'        => true,
						'attributes'        => [
							'class' => 'et_pb_module_inner',
						],
						'children'          => $trail_html,
						'childrenSanitizer' => 'et_core_esc_previously',
					]
				) . $content,
			]
		);
	}

	/**
	 * Loads and registers the module.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/breadcrumbs/';

		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}
}
