<?php
/**
 * Canvas Utils Class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleUtils;

use ET\Builder\VisualBuilder\OffCanvas\OffCanvasHooks;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * CanvasUtils class.
 *
 * This class provides utility methods for working with canvas content.
 *
 * @since ??
 */
class CanvasUtils {

	/**
	 * Cache for canvas posts by canvas ID and post ID.
	 * Prevents redundant get_posts() queries during rendering.
	 *
	 * @since ??
	 * @var array
	 */
	private static $_canvas_content_cache = [];

	/**
	 * Resolve the main-loop canvas parent context key for the current request.
	 *
	 * This provides a stable identifier for non-singular pages (archives) where global `$post`
	 * points at the first post in the loop and cannot be used as a canvas parent.
	 *
	 * Supported contexts:
	 * - category/tag/taxonomy: `term:{taxonomy}:{term_id}`.
	 * - author: `user:{user_id}`.
	 *
	 * @since ??
	 *
	 * @param string|null $main_loop_type Optional. mainLoopType from builder request payload.
	 * @param array|null  $main_loop_settings_data Optional. mainLoopSettingsData from builder request payload.
	 *
	 * @return string|null Context key, or null when singular/unsupported.
	 */
	public static function get_main_loop_parent_context_key( ?string $main_loop_type = null, ?array $main_loop_settings_data = null ): ?string {
		// Prefer explicit builder context when provided.
		if ( is_string( $main_loop_type ) && '' !== $main_loop_type && 'singular' !== $main_loop_type ) {
			if ( in_array( $main_loop_type, [ 'category', 'tag', 'taxonomy' ], true ) ) {
				$term_id  = absint( $main_loop_settings_data['termId'] ?? 0 );
				$taxonomy = sanitize_key( $main_loop_settings_data['taxonomy'] ?? '' );
				$has_term = 0 < $term_id;
				$has_tax  = '' !== $taxonomy;

				if ( $has_term && $has_tax ) {
					return sprintf( 'term:%s:%d', $taxonomy, $term_id );
				}
			}

			if ( 'author' === $main_loop_type ) {
				$author_id = absint( $main_loop_settings_data['authorId'] ?? 0 );
				if ( 0 < $author_id ) {
					return sprintf( 'user:%d', $author_id );
				}
			}

			if ( 'search' === $main_loop_type ) {
				return 'search';
			}

			if ( 'home' === $main_loop_type ) {
				return 'home';
			}

			if ( '404' === $main_loop_type ) {
				return '404';
			}

			if ( 'date' === $main_loop_type ) {
				return 'date';
			}

			if ( 'post_type_archive' === $main_loop_type ) {
				$post_type = sanitize_key( $main_loop_settings_data['postType'] ?? '' );
				return '' !== $post_type ? sprintf( 'post_type_archive:%s', $post_type ) : 'post_type_archive';
			}
		}

		// Front-end fallback: resolve from current query conditionals.
		if ( is_singular() ) {
			return null;
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				return sprintf( 'term:%s:%d', sanitize_key( $term->taxonomy ), (int) $term->term_id );
			}
		}

		if ( is_author() ) {
			$author = get_queried_object();
			if ( $author instanceof \WP_User ) {
				return sprintf( 'user:%d', (int) $author->ID );
			}
		}

		if ( is_search() ) {
			return 'search';
		}

		if ( is_home() ) {
			return 'home';
		}

		if ( is_404() ) {
			return '404';
		}

		if ( is_date() ) {
			return 'date';
		}

		if ( is_post_type_archive() ) {
			$queried_object = get_queried_object();
			if ( $queried_object instanceof \WP_Post_Type && ! empty( $queried_object->name ) ) {
				return sprintf( 'post_type_archive:%s', sanitize_key( $queried_object->name ) );
			}

			$post_type = get_query_var( 'post_type' );
			if ( is_array( $post_type ) ) {
				$post_type = $post_type[0] ?? '';
			}
			if ( is_string( $post_type ) && '' !== $post_type ) {
				return sprintf( 'post_type_archive:%s', sanitize_key( $post_type ) );
			}

			return 'post_type_archive';
		}

		return null;
	}

	/**
	 * Get canvas content by canvas ID for a specific post context.
	 *
	 * Each canvas is stored as a unique post in the `et_pb_canvas` post type, identified
	 * by the `_divi_canvas_id` meta field.
	 *
	 * A canvas can be either:
	 * - **Local**: Has `_divi_canvas_parent_post_id` meta pointing to a specific post (post-specific)
	 * - **Global**: Has no `_divi_canvas_parent_post_id` meta (shared across all posts)
	 *
	 * When rendering a post, we check if the canvas is local to that post. If not, we check
	 * if it's a global canvas (which can be used by any post). We do not return local canvases
	 * that belong to other posts.
	 *
	 * **Cache Strategy:**
	 * Results are cached using a composite key of `"{$canvas_id}_{$post_id}"` to cache
	 * the resolution result for this specific canvas/post combination.
	 *
	 * @since ??
	 *
	 * @param string $canvas_id Canvas ID to look up.
	 * @param int    $post_id   Post ID that provides context for local canvas lookup and cache key.
	 *
	 * @return string|null Canvas content (post_content from the canvas post) or null if not found.
	 */
	public static function get_canvas_content( string $canvas_id, int $post_id ): ?string {
		if ( empty( $canvas_id ) || ! $post_id ) {
			return null;
		}

		// Include request context key in cache to prevent cross-context leakage on non-singular pages.
		$context_key = self::get_main_loop_parent_context_key();
		// When a context key exists, cache by context (not by $post_id).
		// On non-singular pages `$post_id` can be the first post in the loop and is not stable.
		$cache_key = $context_key ? "{$canvas_id}_context_{$context_key}" : "{$canvas_id}_{$post_id}";
		if ( isset( self::$_canvas_content_cache[ $cache_key ] ) ) {
			return self::$_canvas_content_cache[ $cache_key ];
		}

		// Resolve precedence:
		// - On non-singular pages prefer context-backed local canvases,
		// - Then fall back to post-backed local canvases (when relevant),
		// - Finally fall back to global canvases.
		$canvas_content = ( $context_key ? self::_fetch_canvas_post_content_by_context( $canvas_id, $context_key ) : null )
			?? self::_fetch_canvas_post_content( $canvas_id, $post_id )
			?? self::_fetch_canvas_post_content( $canvas_id );

		// Cache the result (even if null) to avoid redundant queries.
		self::$_canvas_content_cache[ $cache_key ] = $canvas_content;

		return $canvas_content;
	}

	/**
	 * Fetch canvas post content by canvas ID.
	 *
	 * @since ??
	 *
	 * @param string   $canvas_id Canvas ID.
	 * @param int|null $parent_post_id Optional. Parent post ID for local canvas lookup.
	 *
	 * @return string|null Canvas post content or null if not found.
	 */
	private static function _fetch_canvas_post_content( string $canvas_id, ?int $parent_post_id = null ): ?string {
		$meta_query = [
			[
				'key'   => '_divi_canvas_id',
				'value' => $canvas_id,
			],
		];

		if ( null === $parent_post_id ) {
			// Global canvas: parent_post_id meta key should not exist.
			$meta_query[] = [
				'key'     => '_divi_canvas_parent_post_id',
				'compare' => 'NOT EXISTS',
			];
			// Context-backed canvases are not global.
			$meta_query[] = [
				'key'     => '_divi_canvas_parent_context',
				'compare' => 'NOT EXISTS',
			];
		} else {
			// Local canvas: parent_post_id must match.
			$meta_query[] = [
				'key'   => '_divi_canvas_parent_post_id',
				'value' => $parent_post_id,
			];
		}

		$posts = get_posts(
			[
				'post_type'      => OffCanvasHooks::GLOBAL_CANVAS_POST_TYPE,
				'posts_per_page' => 1,
				'meta_query'     => $meta_query,
			]
		);

		return ! empty( $posts ) ? $posts[0]->post_content : null;
	}

	/**
	 * Fetch canvas post content by canvas ID for a main-loop context key.
	 *
	 * @since ??
	 *
	 * @param string $canvas_id Canvas ID.
	 * @param string $parent_context_key Parent context key for context-backed local canvas lookup.
	 *
	 * @return string|null Canvas post content or null if not found.
	 */
	private static function _fetch_canvas_post_content_by_context( string $canvas_id, string $parent_context_key ): ?string {
		if ( '' === $parent_context_key ) {
			return null;
		}

		$posts = get_posts(
			[
				'post_type'      => OffCanvasHooks::GLOBAL_CANVAS_POST_TYPE,
				'posts_per_page' => 1,
				'meta_query'     => [
					[
						'key'   => '_divi_canvas_id',
						'value' => $canvas_id,
					],
					[
						'key'   => '_divi_canvas_parent_context',
						'value' => $parent_context_key,
					],
				],
			]
		);

		return ! empty( $posts ) ? $posts[0]->post_content : null;
	}

	/**
	 * Pre-populate canvas content cache with batch-fetched canvas content.
	 *
	 * This allows render_callback() to reuse cached content instead of fetching again.
	 *
	 * @since ??
	 *
	 * @param array $canvas_content_map Map of canvas_id => post_content.
	 * @param int   $post_id            Post ID for cache key.
	 *
	 * @return void
	 */
	public static function pre_populate_cache( array $canvas_content_map, int $post_id ): void {
		foreach ( $canvas_content_map as $canvas_id => $canvas_content ) {
			$cache_key = "{$canvas_id}_{$post_id}";
			if ( ! isset( self::$_canvas_content_cache[ $cache_key ] ) ) {
				self::$_canvas_content_cache[ $cache_key ] = $canvas_content;
			}
		}
	}

	/**
	 * Get canvas posts (local or global).
	 *
	 * Local canvases are linked to their parent post via `_divi_canvas_parent_post_id` meta.
	 * Global canvases do not have `_divi_canvas_parent_post_id` meta (they are shared across all posts).
	 *
	 * @since ??
	 *
	 * @param bool  $is_global Whether to fetch global canvas posts. If true, ignores $post_id.
	 * @param ?int  $post_id   Parent post ID (required when $is_global is false).
	 * @param array $args      Optional. Additional arguments to pass to get_posts(). Defaults to:
	 *                         - 'posts_per_page' => -1.
	 *                         - 'post_status' => 'publish'.
	 *
	 * @return array Array of WP_Post objects.
	 */
	public static function get_canvas_posts( bool $is_global, ?int $post_id = null, array $args = [] ): array {
		$defaults = [
			'post_type'      => OffCanvasHooks::GLOBAL_CANVAS_POST_TYPE,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		];

		if ( $is_global ) {
			$defaults['meta_query'] = [
				[
					'key'     => '_divi_canvas_parent_post_id',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_divi_canvas_parent_context',
					'compare' => 'NOT EXISTS',
				],
			];
		} else {
			if ( null === $post_id ) {
				return [];
			}
			$defaults['meta_query'] = [
				[
					'key'   => '_divi_canvas_parent_post_id',
					'value' => $post_id,
				],
			];
		}

		// Merge user args, but preserve meta_query structure.
		if ( isset( $args['meta_query'] ) ) {
			// If user provided meta_query, merge it with our required one.
			$args['meta_query'] = array_merge( $defaults['meta_query'], $args['meta_query'] );
		}

		$query_args = array_merge( $defaults, $args );

		return get_posts( $query_args );
	}

	/**
	 * Get local canvas posts for a specific post.
	 *
	 * Local canvases are linked to their parent post via `_divi_canvas_parent_post_id` meta.
	 *
	 * @since ??
	 *
	 * @param int   $post_id Parent post ID.
	 * @param array $args    Optional. Additional arguments to pass to get_posts(). Defaults to:
	 *                       - 'posts_per_page' => -1.
	 *                       - 'post_status' => 'publish'.
	 *
	 * @return array Array of WP_Post objects.
	 */
	public static function get_local_canvas_posts( int $post_id, array $args = [] ): array {
		return self::get_canvas_posts( false, $post_id, $args );
	}

	/**
	 * Get local canvas posts for multiple parent posts in a single query.
	 *
	 * Local canvases are linked to their parent post via `_divi_canvas_parent_post_id` meta.
	 *
	 * @since ??
	 *
	 * @param array $post_ids Parent post IDs.
	 * @param array $args Optional. Additional arguments to pass to get_posts(). Defaults to:
	 *                    - 'posts_per_page' => -1.
	 *                    - 'post_status' => 'publish'.
	 *
	 * @return array Array of WP_Post objects.
	 */
	public static function get_local_canvas_posts_for_post_ids( array $post_ids, array $args = [] ): array {
		$post_ids = array_values(
			array_filter(
				array_unique( array_map( 'absint', $post_ids ) )
			)
		);

		if ( empty( $post_ids ) ) {
			return [];
		}

		$defaults = [
			'post_type'      => OffCanvasHooks::GLOBAL_CANVAS_POST_TYPE,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => [
				[
					'key'     => '_divi_canvas_parent_post_id',
					'value'   => $post_ids,
					'compare' => 'IN',
				],
			],
		];

		if ( isset( $args['meta_query'] ) ) {
			$args['meta_query'] = array_merge( $defaults['meta_query'], $args['meta_query'] );
		}

		$query_args = array_merge( $defaults, $args );

		return get_posts( $query_args );
	}

	/**
	 * Get context-backed local canvas posts for a specific non-singular context key.
	 *
	 * @since ??
	 *
	 * @param string $context_key Parent context key (e.g. `term:category:12`).
	 * @param array  $args Optional. Additional get_posts args.
	 *
	 * @return array Array of WP_Post objects.
	 */
	public static function get_context_canvas_posts( string $context_key, array $args = [] ): array {
		if ( '' === $context_key ) {
			return [];
		}

		$defaults = [
			'post_type'      => OffCanvasHooks::GLOBAL_CANVAS_POST_TYPE,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => [
				[
					'key'   => '_divi_canvas_parent_context',
					'value' => $context_key,
				],
			],
		];

		// Merge user args, but preserve meta_query structure.
		if ( isset( $args['meta_query'] ) ) {
			$args['meta_query'] = array_merge( $defaults['meta_query'], $args['meta_query'] );
		}

		$query_args = array_merge( $defaults, $args );

		return get_posts( $query_args );
	}

	/**
	 * Get context-backed local canvas posts for multiple context keys.
	 *
	 * @since ??
	 *
	 * @param array $context_keys Array of context keys.
	 * @param array $args Optional. Additional get_posts args.
	 *
	 * @return array Array of WP_Post objects.
	 */
	public static function get_context_canvas_posts_for_contexts( array $context_keys, array $args = [] ): array {
		$context_keys = array_values(
			array_filter(
				array_map(
					static function ( $key ) {
						return is_string( $key ) ? $key : '';
					},
					$context_keys
				)
			)
		);

		if ( empty( $context_keys ) ) {
			return [];
		}

		$defaults = [
			'post_type'      => OffCanvasHooks::GLOBAL_CANVAS_POST_TYPE,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => [
				[
					'key'     => '_divi_canvas_parent_context',
					'value'   => $context_keys,
					'compare' => 'IN',
				],
			],
		];

		if ( isset( $args['meta_query'] ) ) {
			$args['meta_query'] = array_merge( $defaults['meta_query'], $args['meta_query'] );
		}

		$query_args = array_merge( $defaults, $args );

		return get_posts( $query_args );
	}
}
