<?php
/**
 * Loop: LoopHooks.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Loop;

use ET\Builder\Packages\ModuleLibrary\LoopQueryRegistry;
use ET_Core_PageResource;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Loop option custom hooks.
 */
class LoopHooks {

	/**
	 * Register the loop option custom hooks for cache invalidation.
	 *
	 * This method registers WordPress hooks that fire when posts, terms, or users
	 * are created, updated, or deleted. When these hooks fire, the LoopQueryRegistry
	 * cache is cleared to ensure loop queries use current post/term/user counts.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'divi_framework_portability_import_migrated_post_content', [ __CLASS__, 'deduplicate_imported_post_content' ], 20, 1 );
		add_action( 'divi_visual_builder_rest_save_post', [ __CLASS__, 'deduplicate_on_layout_save' ], 30, 2 );
		add_action( 'added_post_meta', [ __CLASS__, 'deduplicate_on_template_layout_meta_change' ], 10, 4 );
		add_action( 'updated_post_meta', [ __CLASS__, 'deduplicate_on_template_layout_meta_change' ], 10, 4 );

		// Post hooks - invalidate when posts are created/published or change status.
		add_action( 'wp_insert_post', [ __CLASS__, 'invalidate_cache_on_post_insert' ], 10, 3 );
		add_action( 'post_updated', [ __CLASS__, 'invalidate_cache_on_post_update' ], 10, 3 );
		add_action( 'transition_post_status', [ __CLASS__, 'invalidate_cache_on_post_change' ], 10, 3 );
		add_action( 'delete_post', [ __CLASS__, 'invalidate_cache_on_post_change' ], 10, 1 );
		add_action( 'wp_trash_post', [ __CLASS__, 'invalidate_cache_on_post_change' ], 10, 1 );
		add_action( 'trashed_post', [ __CLASS__, 'invalidate_cache_on_post_change' ], 10, 1 );
		add_action( 'wp_untrash_post', [ __CLASS__, 'invalidate_cache_on_post_change' ], 10, 1 );

		// Post meta hooks - invalidate when featured image (_thumbnail_id) changes.
		add_action( 'updated_post_meta', [ __CLASS__, 'invalidate_cache_on_thumbnail_change' ], 10, 4 );
		add_action( 'added_post_meta', [ __CLASS__, 'invalidate_cache_on_thumbnail_change' ], 10, 4 );
		add_action( 'deleted_post_meta', [ __CLASS__, 'invalidate_cache_on_thumbnail_change' ], 10, 4 );

		// Term hooks - fires when terms are created or deleted.
		add_action( 'created_term', [ __CLASS__, 'invalidate_cache_on_content_change' ], 10, 1 );
		add_action( 'delete_term', [ __CLASS__, 'invalidate_cache_on_content_change' ], 10, 1 );

		// User hooks - fires when users are registered or deleted.
		add_action( 'user_register', [ __CLASS__, 'invalidate_cache_on_content_change' ], 10, 1 );
		add_action( 'delete_user', [ __CLASS__, 'invalidate_cache_on_content_change' ], 10, 1 );
	}

	/**
	 * Deduplicate loop IDs in imported post content against sibling TB layout contents.
	 *
	 * Side-effect free: only transforms and returns the importing layout's content string.
	 * Sibling layouts are deduplicated on save via deduplicate_on_layout_save().
	 *
	 * @since ??
	 *
	 * @param string $post_content Imported post content.
	 *
	 * @return string Patched post content.
	 */
	public static function deduplicate_imported_post_content( string $post_content ): string {
		if ( '' === $post_content || ! str_contains( $post_content, 'loop-' ) ) {
			return $post_content;
		}

		$post_id = isset( $_REQUEST['post'] ) ? absint( $_REQUEST['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only context lookup during import.

		if ( $post_id <= 0 || ! function_exists( 'et_theme_builder_is_layout_post_type' ) || ! et_theme_builder_is_layout_post_type( get_post_type( $post_id ) ) ) {
			return $post_content;
		}

		$template_id = LoopIdDeduplicationUtils::get_template_id_for_layout( $post_id );

		if ( $template_id <= 0 ) {
			return $post_content;
		}

		$layout_entries = LoopIdDeduplicationUtils::get_template_layout_contents_for_dedup( $template_id );

		if ( count( $layout_entries ) < 2 ) {
			return $post_content;
		}

		$contents = [];

		foreach ( $layout_entries as $meta_key => $entry ) {
			$contents[ $meta_key ] = (int) $entry['id'] === $post_id ? $post_content : $entry['content'];
		}

		$deduped = LoopIdDeduplicationUtils::deduplicate_loop_ids_across_contents( $contents );

		foreach ( $layout_entries as $meta_key => $entry ) {
			if ( (int) $entry['id'] === $post_id ) {
				return $deduped[ $meta_key ] ?? $post_content;
			}
		}

		return $post_content;
	}

	/**
	 * Deduplicate loop IDs across sibling TB layouts after a Visual Builder save.
	 *
	 * Sync-to-server saves TB header/body/footer layout posts separately but fires this hook
	 * with the main page post ID. Resolve the parent template from either the layout post ID
	 * or the page's assigned TB template layouts.
	 *
	 * @since ??
	 *
	 * @param int        $post_id Saved post ID.
	 * @param array|null $context Save request context (unused).
	 *
	 * @return void
	 */
	public static function deduplicate_on_layout_save( int $post_id, ?array $context = null ): void {
		unset( $context );

		if ( $post_id <= 0 ) {
			return;
		}

		if ( function_exists( 'et_theme_builder_is_layout_post_type' ) && et_theme_builder_is_layout_post_type( get_post_type( $post_id ) ) ) {
			LoopIdDeduplicationUtils::deduplicate_sibling_layouts_for_layout_post( $post_id );
			return;
		}

		// Prefer the effective header/body/footer set for this page request so shared/global
		// layouts co-rendered with the page are included, not only one et_template's direct meta.
		$effective_layouts = LoopIdDeduplicationUtils::get_effective_tb_layouts_for_post( $post_id );
		$effective_entries = LoopIdDeduplicationUtils::get_layout_contents_from_tb_layouts( $effective_layouts );

		if ( 2 <= count( $effective_entries ) ) {
			LoopIdDeduplicationUtils::deduplicate_layout_entries( $effective_entries );
			return;
		}

		$template_id = LoopIdDeduplicationUtils::get_template_id_for_tb_save_context( $post_id );

		if ( $template_id > 0 ) {
			LoopIdDeduplicationUtils::deduplicate_template_sibling_layouts( $template_id );
		}
	}

	/**
	 * Deduplicate loop IDs when a TB template is linked to layout posts.
	 *
	 * @since ??
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $object_id  Template post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $_meta_value Meta value (unused).
	 *
	 * @return void
	 */
	public static function deduplicate_on_template_layout_meta_change( $meta_id, $object_id, $meta_key, $_meta_value ): void {
		unset( $meta_id, $_meta_value );

		if ( ! defined( 'ET_THEME_BUILDER_TEMPLATE_POST_TYPE' ) || ET_THEME_BUILDER_TEMPLATE_POST_TYPE !== get_post_type( $object_id ) ) {
			return;
		}

		if ( ! in_array( $meta_key, LoopIdDeduplicationUtils::TEMPLATE_LAYOUT_META_KEYS, true ) ) {
			return;
		}

		LoopIdDeduplicationUtils::deduplicate_template_sibling_layouts( (int) $object_id );
	}

	/**
	 * Invalidate cache when posts are inserted (created).
	 *
	 * This callback fires when posts are inserted, including new posts with any status.
	 * It clears both loop query cache and static CSS cache for all new posts.
	 *
	 * @since ??
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 *
	 * @return void
	 */
	public static function invalidate_cache_on_post_insert( $post_id, $post, $update ): void {
		// Only clear cache for new posts (not updates).
		if ( $update ) {
			return;
		}

		// Skip if this is an autosave or revision.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Skip if this is not a post type we care about (only clear cache for public post types).
		if ( isset( $post->post_type ) && ! et_builder_is_post_type_public( $post->post_type ) ) {
			return;
		}

		// Clear all cache when a new post is created (post count changed).
		self::_invalidate_cache();
	}

	/**
	 * Invalidate cache when posts change (status, delete, trash, untrash).
	 *
	 * This callback handles multiple post-related hooks:
	 * - transition_post_status: receives ($new_status, $old_status, $post)
	 * - delete_post, wp_trash_post, trashed_post, wp_untrash_post: receive ($post_id)
	 *
	 * @since ??
	 *
	 * @param mixed $post_id_or_status Post ID (for delete/trash hooks) or new status (for transition_post_status).
	 * @param mixed $old_status         Old status (for transition_post_status only).
	 * @param mixed $post               Post object (for transition_post_status only).
	 *
	 * @return void
	 */
	public static function invalidate_cache_on_post_change( $post_id_or_status, $old_status = null, $post = null ): void {
		// For transition_post_status hook: only invalidate if status actually changed.
		// WordPress fires this hook even when saving without status change.
		if ( null !== $old_status && null !== $post ) {
			// This is transition_post_status hook - check if status actually changed.
			$new_status = $post_id_or_status;
			if ( $new_status === $old_status ) {
				// Status didn't change - don't invalidate cache.
				return;
			}
			// Status changed - invalidate cache for this post.
			self::_invalidate_cache_for_post( $post );
			return;
		}

		// For other hooks (delete_post, wp_trash_post, etc.): always invalidate.
		$post = null !== $post ? $post : get_post( $post_id_or_status );
		if ( $post ) {
			self::_invalidate_cache_for_post( $post );
		}
	}

	/**
	 * Invalidate cache when post date or content changes.
	 *
	 * This callback fires when posts are updated via the `post_updated` hook.
	 * It compares old and new post dates and content, invalidating cache when either changes.
	 * Date changes affect loop ordering (CSS variables for post-to-image mappings),
	 * while content changes affect archive pages that render full post content
	 * (e.g. Blog module with "Show Content"), whose dynamic CSS and detection
	 * metadata are cached independently from individual post caches.
	 *
	 * @since ??
	 *
	 * @param int     $post_id    Post ID.
	 * @param WP_Post $post_after Post object after update.
	 * @param WP_Post $post_before Post object before update.
	 *
	 * @return void
	 */
	public static function invalidate_cache_on_post_update( $post_id, $post_after, $post_before ): void {
		// Skip if this is an autosave or revision.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Skip if post type is not public.
		if ( ! isset( $post_after->post_type ) || ! et_builder_is_post_type_public( $post_after->post_type ) ) {
			return;
		}

		// Compare post dates - only invalidate if date actually changed.
		if ( ! isset( $post_before->post_date ) || ! isset( $post_after->post_date ) ) {
			// If dates are missing, err on side of invalidating cache to ensure correctness.
			self::_invalidate_cache();
			return;
		}

		if ( $post_before->post_date !== $post_after->post_date ) {
			self::_invalidate_cache();
			return;
		}

		// Content changes on posts/CPTs: taxonomy archive CSS (Blog "Show Content") is not
		// cleared by save_post_cb (#49189). Pages are skipped — save_post_cb is enough (#47215).
		if ( $post_before->post_content !== $post_after->post_content && 'page' !== $post_after->post_type ) {
			self::_invalidate_archive_caches_for_post();
		}
	}

	/**
	 * Invalidate cache when post featured image (_thumbnail_id) meta changes.
	 *
	 * This callback fires when post meta is updated, added, or deleted.
	 * It filters for the `_thumbnail_id` meta key and invalidates cache to ensure
	 * loop modules using featured images as backgrounds reflect current images.
	 *
	 * @since ??
	 *
	 * @param int|array $meta_id_or_ids Meta ID (for updated/added) or array of meta IDs (for deleted).
	 * @param int       $object_id       Post ID.
	 * @param string    $meta_key        Meta key.
	 * @param mixed     $_meta_value     Meta value (not used).
	 *
	 * @return void
	 */
	public static function invalidate_cache_on_thumbnail_change( $meta_id_or_ids, $object_id, $meta_key, $_meta_value ): void {
		// Only invalidate cache for featured image meta key.
		if ( '_thumbnail_id' !== $meta_key ) {
			return;
		}

		// Get the post and delegate to existing invalidation method.
		$post = get_post( $object_id );
		if ( $post ) {
			self::_invalidate_cache_for_post( $post );
		}
	}

	/**
	 * Invalidate cache when terms or users change.
	 *
	 * This callback handles term and user hooks (created_term, delete_term, user_register, delete_user).
	 * No post type checking is needed for these hooks.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function invalidate_cache_on_content_change(): void {
		self::_invalidate_cache();
	}

	/**
	 * Invalidate cache for a specific post if it's a public post type.
	 *
	 * @since ??
	 *
	 * @param WP_Post|null $post Post object.
	 *
	 * @return void
	 */
	private static function _invalidate_cache_for_post( $post ): void {
		if ( ! self::_is_loop_query_registry_available() ) {
			return;
		}

		if ( ! $post || ! isset( $post->post_type ) ) {
			return;
		}

		// Skip if this is not a post type we care about (only clear cache for public post types).
		if ( ! et_builder_is_post_type_public( $post->post_type ) ) {
			return;
		}

		// Clear all cache when a post is deleted or status changes (post count/visibility changed).
		self::_invalidate_cache();
	}

	/**
	 * Clear both loop query cache and static CSS cache.
	 *
	 * Always clears all cache because post/term/user count changes affect all loop queries.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	private static function _invalidate_cache(): void {
		if ( ! self::_is_loop_query_registry_available() ) {
			return;
		}

		LoopQueryRegistry::clear();
		ET_Core_PageResource::remove_static_resources( 'all', 'all' );
	}

	/**
	 * Invalidate taxonomy archive Divi caches when post/CPT content changes (#49189).
	 *
	 * Stale-marks all taxonomy archive CSS and purges dynamic-assets detection metadata.
	 * Does not call remove_static_resources( 'all' ) or et_core_clear_wp_cache() (#47215).
	 *
	 * @since ??
	 *
	 * @return void
	 */
	private static function _invalidate_archive_caches_for_post(): void {
		ET_Core_PageResource::remove_all_taxonomy_archive_resources();

		if ( class_exists( \ET_Builder_Dynamic_Assets_Feature::class ) ) {
			\ET_Builder_Dynamic_Assets_Feature::purge_cache();
		}
	}

	/**
	 * Check if LoopQueryRegistry is available without triggering autoload.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function _is_loop_query_registry_available(): bool {
		if ( class_exists( LoopQueryRegistry::class, false ) ) {
			return true;
		}

		if ( ! defined( 'ET_BUILDER_5_DIR' ) ) {
			return false;
		}

		$registry_file = ET_BUILDER_5_DIR . 'server/Packages/ModuleLibrary/LoopQueryRegistry.php';

		if ( ! file_exists( $registry_file ) ) {
			return false;
		}

		require_once $registry_file;

		return class_exists( LoopQueryRegistry::class, false );
	}
}
