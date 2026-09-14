<?php
/**
 * Off-Canvas Hooks for Divi 5 Visual Builder.
 *
 * @package ET\Builder\VisualBuilder\OffCanvas
 * @since ??
 */

namespace ET\Builder\VisualBuilder\OffCanvas;

use ET\Builder\VisualBuilder\Saving\SavingUtility;
use ET\Builder\Packages\Conversion\Utils\ConversionUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\ModuleUtils\CanvasUtils;
use ET\Builder\Packages\ModuleLibrary\CanvasPortal\CanvasPortalModule;
use ET\Builder\FrontEnd\Assets\StaticCSS;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use ET\Builder\FrontEnd\Assets\DetectFeature;
use ET\Builder\FrontEnd\Page;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\BlockParser\OrderIndexResetManager;
use ET\Builder\FrontEnd\BlockParser\SimpleBlockParser;
use ET\Builder\Framework\Utility\Conditions;
use ET_Core_PageResource;
use ET_Post_Stack;

/**
 * Off-Canvas Hooks class.
 *
 * Handles saving, loading, and rendering of off-canvas data including global canvases.
 *
 * @since ??
 */
class OffCanvasHooks {

	/**
	 * Post type name for global canvases.
	 *
	 * @since ??
	 */
	const GLOBAL_CANVAS_POST_TYPE = 'et_pb_canvas';



	/**
	 * Cache for off-canvas metadata (_divi_off_canvas_data).
	 * Prevents redundant database queries during the same request.
	 *
	 * @since ??
	 * @var array
	 */
	private static $_off_canvas_metadata_cache = [];

	/**
	 * Cache for local canvases data.
	 * Prevents redundant database queries during the same request.
	 *
	 * @since ??
	 * @var array
	 */
	private static $_local_canvases_cache = [];

	/**
	 * Cache for global canvases data.
	 * Prevents redundant database queries during the same request.
	 *
	 * @since ??
	 * @var array
	 */
	private static $_global_canvases_cache = [];

	/**
	 * Cache for current post ID per request.
	 * Prevents redundant calls to _get_current_post_id().
	 * Note: This cache should be cleared when the post context changes (e.g., between header/post content/footer rendering).
	 *
	 * @since ??
	 * @var int|false|null
	 */
	private static $_current_post_id_cache = null;

	/**
	 * Cache for rendering context per request.
	 * Prevents redundant calls to _get_rendering_context().
	 * Note: This cache should be cleared when the rendering context changes (e.g., between header/post content/footer rendering).
	 *
	 * @since ??
	 * @var string|null
	 */
	private static $_rendering_context_cache = null;

	/**
	 * Cache for main post object per post ID.
	 * Prevents redundant get_post() calls during interaction detection.
	 *
	 * @since ??
	 * @var array<int, \WP_Post|null>
	 */
	private static $_main_post_cache = [];

	/**
	 * Initialize hooks.
	 *
	 * @since ??
	 */
	public static function init() {
		// Register global canvas post type.
		add_action( 'init', [ __CLASS__, 'register_global_canvas_post_type' ] );

		// Hook into the post save process to save off-canvas data.
		// Accept a second arg for save context (e.g. mainLoopType).
		add_action( 'divi_visual_builder_rest_save_post', [ __CLASS__, 'save_off_canvas_data' ], 10, 2 );

		// Add REST endpoint to get off-canvas data.
		add_action( 'rest_api_init', [ __CLASS__, 'register_rest_routes' ] );

		// Reset orderIndex before HTML rendering starts to ensure consistent numbering.
		// This is necessary because CSS generation may have incremented orderIndex when
		// processing canvas content, so we need to reset it before HTML rendering.
		add_filter( 'the_content', [ __CLASS__, 'reset_order_index_before_rendering' ], 1 );
		add_filter( 'et_builder_render_layout', [ __CLASS__, 'reset_order_index_before_rendering' ], 1 );

		// Reset orderIndex again right before do_blocks() runs, after canvas processing.
		// Canvas processing (process_canvas_content_above_main_content at priority 2) may parse
		// blocks for interaction detection, incrementing orderIndex. We need to reset again
		// right before actual HTML rendering via do_blocks() (which runs at priority 9).
		// Hook to both filters to ensure it runs for both Theme Builder layouts and regular post content.
		add_filter( 'the_content', [ __CLASS__, 'reset_order_index_before_do_blocks' ], 8 );
		add_filter( 'et_builder_render_layout', [ __CLASS__, 'reset_order_index_before_do_blocks' ], 8 );

		// Process and prepend canvases that should be appended above main content.
		add_filter( 'the_content', [ __CLASS__, 'process_canvas_content_above_main_content' ], 2 );
		add_filter( 'et_builder_render_layout', [ __CLASS__, 'process_canvas_content_above_main_content' ], 2 );

		// Hook into Divi's block processing pipeline to collect target IDs.
		add_filter( 'render_block_data', [ __CLASS__, 'detect_and_process_off_canvas_interactions' ], 5, 3 );

		// Process canvas content after all main content blocks are rendered (priority 998).
		// This ensures canvas content continues orderIndex sequence from main content.
		add_filter( 'the_content', [ __CLASS__, 'process_canvas_content_after_main_content' ], 998 );
		add_filter( 'et_builder_render_layout', [ __CLASS__, 'process_canvas_content_after_main_content' ], 998 );

		// Hook into content rendering to inject pre-processed canvas content.
		add_filter( 'the_content', [ __CLASS__, 'inject_canvas_content_for_interactions' ], 999 );
		add_filter( 'et_builder_render_layout', [ __CLASS__, 'inject_canvas_content_for_interactions' ], 999 );

		// Add canvas ID class to wrapper when rendering canvas content with z-index.
		add_filter( 'et_builder_layout_class', [ __CLASS__, 'add_canvas_id_to_wrapper_class' ] );
		add_filter( 'et_builder_layout_extra_attrs', [ __CLASS__, 'add_canvas_name_to_layout_attrs' ] );
	}

	/**
	 * Register the global canvas post type.
	 *
	 * @since ??
	 */
	public static function register_global_canvas_post_type() {
		register_post_type(
			self::GLOBAL_CANVAS_POST_TYPE,
			[
				'label'               => __( 'Global Canvases', 'et_builder' ),
				'labels'              => [
					'name'               => __( 'Global Canvases', 'et_builder' ),
					'singular_name'      => __( 'Global Canvas', 'et_builder' ),
					'add_new'            => __( 'Add New', 'et_builder' ),
					'add_new_item'       => __( 'Add New Global Canvas', 'et_builder' ),
					'edit_item'          => __( 'Edit Global Canvas', 'et_builder' ),
					'new_item'           => __( 'New Global Canvas', 'et_builder' ),
					'view_item'          => __( 'View Global Canvas', 'et_builder' ),
					'search_items'       => __( 'Search Global Canvases', 'et_builder' ),
					'not_found'          => __( 'No global canvases found', 'et_builder' ),
					'not_found_in_trash' => __( 'No global canvases found in Trash', 'et_builder' ),
				],
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'can_export'          => true,
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'rewrite'             => false,
				'capability_type'     => 'post',
				'supports'            => [ 'title', 'editor' ],
				'show_in_rest'        => true,
			]
		);
	}

	/**
	 * Save off-canvas data to post meta when page is saved.
	 *
	 * @since ??
	 *
	 * @param int   $post_id      Post ID.
	 * @param array $save_context Save context.
	 */
	public static function save_off_canvas_data( $post_id, $save_context = [] ) {
		// Determine whether the user can edit the saved post.
		// On non-singular pages, the VB "post" context can be the first post in the loop;
		// canvas persistence must rely on per-owner capability checks instead of this alone.
		$can_edit_saved_post = current_user_can( 'edit_post', $post_id );

		// For singular pages, editing the saved post is a hard requirement.
		$main_loop_type_for_guard = is_array( $save_context ) ? ( $save_context['mainLoopType'] ?? null ) : null;
		if ( ! is_string( $main_loop_type_for_guard ) || '' === $main_loop_type_for_guard ) {
			$main_loop_type_for_guard = 'singular';
		}

		if ( ! $can_edit_saved_post ) {
			if ( ! is_string( $main_loop_type_for_guard ) || 'singular' === $main_loop_type_for_guard ) {
				return;
			}
		}
		// Theme Builder layout canvases (header/body/footer template owners) require
		// explicit Theme Builder capability in addition to post edit capability.
		$can_edit_theme_builder_canvases = (bool) et_pb_is_allowed( 'theme_builder' );
		$can_manage_global_canvases      = current_user_can( 'edit_posts' );

		// Get off-canvas data from the global variable set by SyncToServerController.
		// The caller is responsible for normalizing the data to array format.
		$canvas_data = $GLOBALS['divi_off_canvas_data'] ?? null;

		if ( ! $canvas_data || ! is_array( $canvas_data ) ) {
			return;
		}

		// Save canvas metadata (only activeCanvasId and mainCanvasName, not canvas data).
		$canvas_metadata = [
			'activeCanvasId' => $canvas_data['activeCanvasId'] ?? '',
			'mainCanvasName' => '', // Will be set if main canvas exists.
		];

		// Batch fetch all existing canvas posts to avoid N queries.
		// Fetch all canvas posts (both local to this post and global) in one query.
		$existing_canvas_posts_map = [];
		$local_parent_post_ids     = [ (int) $post_id ];
		$local_parent_context_keys = [];

		// Resolve default main-loop context key for this save (non-singular pages).
		$main_loop_type          = is_array( $save_context ) ? ( $save_context['mainLoopType'] ?? 'singular' ) : 'singular';
		$main_loop_settings_data = is_array( $save_context ) ? ( $save_context['mainLoopSettingsData'] ?? [] ) : [];
		$default_context_key     = CanvasUtils::get_main_loop_parent_context_key(
			is_string( $main_loop_type ) ? $main_loop_type : null,
			is_array( $main_loop_settings_data ) ? $main_loop_settings_data : null
		);
		$allowed_parent_post_ids = self::_get_allowed_canvas_parent_post_ids( (int) $post_id, $can_edit_theme_builder_canvases );

		$allowed_parent_context_keys = [];
		if ( is_string( $default_context_key ) && '' !== $default_context_key ) {
			$allowed_parent_context_keys[] = $default_context_key;
		}

		$local_parent_post_ids     = $allowed_parent_post_ids;
		$local_parent_context_keys = $allowed_parent_context_keys;

		if ( isset( $canvas_data['canvases'] ) && is_array( $canvas_data['canvases'] ) ) {
			foreach ( $canvas_data['canvases'] as $canvas ) {
				$is_global = $canvas['isGlobal'] ?? false;
				if ( $is_global ) {
					continue;
				}

				$canvas_parent_post_id = absint( $canvas['parentPostId'] ?? 0 );
				if ( $canvas_parent_post_id > 0 ) {
					if ( in_array( $canvas_parent_post_id, $allowed_parent_post_ids, true ) ) {
						$local_parent_post_ids[] = $canvas_parent_post_id;
					}
					continue;
				}

				$canvas_parent_context = isset( $canvas['parentContextKey'] ) && is_string( $canvas['parentContextKey'] )
					? sanitize_text_field( $canvas['parentContextKey'] )
					: '';

				if ( '' === $canvas_parent_context && is_string( $default_context_key ) ) {
					$canvas_parent_context = $default_context_key;
				}

				if ( '' !== $canvas_parent_context && in_array( $canvas_parent_context, $allowed_parent_context_keys, true ) ) {
					$local_parent_context_keys[] = $canvas_parent_context;
				}
			}
		}
		$local_parent_post_ids     = array_values( array_unique( $local_parent_post_ids ) );
		$local_parent_context_keys = array_values( array_unique( $local_parent_context_keys ) );

		// Fetch local canvases for all possible parent posts involved in this save.
		$local_posts = [];
		foreach ( $local_parent_post_ids as $local_parent_post_id ) {
			$local_posts = array_merge(
				$local_posts,
				CanvasUtils::get_local_canvas_posts( (int) $local_parent_post_id, [ 'post_status' => 'any' ] )
			);
		}

		// Fetch context-backed local canvases for all possible parent contexts involved in this save.
		if ( ! empty( $local_parent_context_keys ) ) {
			$local_posts = array_merge(
				$local_posts,
				CanvasUtils::get_context_canvas_posts_for_contexts( $local_parent_context_keys, [ 'post_status' => 'any' ] )
			);
		}

		// Fetch global canvases (no parent_post_id).
		$global_posts = CanvasUtils::get_canvas_posts( true, null, [ 'post_status' => 'any' ] );

		// Combine and build map of canvas_id => post.
		$all_existing_posts = array_merge( $local_posts, $global_posts );
		$append_to_main_map = [];
		$z_index_map        = [];
		$parent_post_id_map = [];
		if ( ! empty( $all_existing_posts ) ) {
			// Batch fetch all meta we need upfront in a single query instead of 4 queries.
			$post_ids           = array_map(
				function ( $post ) {
					return $post->ID;
				},
				$all_existing_posts
			);
			$all_meta           = DynamicAssetsUtils::_batch_get_post_meta(
				$post_ids,
				[
					'_divi_canvas_id',
					'_divi_canvas_append_to_main',
					'_divi_canvas_z_index',
					'_divi_canvas_parent_post_id',
					'_divi_canvas_parent_context',
				]
			);
			$canvas_id_map      = $all_meta['_divi_canvas_id'] ?? [];
			$append_to_main_map = $all_meta['_divi_canvas_append_to_main'] ?? [];
			$z_index_map        = $all_meta['_divi_canvas_z_index'] ?? [];
			$parent_post_id_map = $all_meta['_divi_canvas_parent_post_id'] ?? [];
			$parent_context_map = $all_meta['_divi_canvas_parent_context'] ?? [];

			// Build map of canvas_id => post.
			foreach ( $all_existing_posts as $post ) {
				$canvas_id = $canvas_id_map[ $post->ID ] ?? '';
				if ( $canvas_id ) {
					$existing_canvas_posts_map[ $canvas_id ] = $post;
				}
			}
		}

		// Find and save main canvas name (but skip saving its content - it's in post_content).
		// First, get existing main canvas name from database to preserve it if main canvas is not in payload.
		// Use static cache to avoid duplicate queries if get_off_canvas_data_for_post was already called.
		if ( ! isset( self::$_off_canvas_metadata_cache[ $post_id ] ) ) {
			self::$_off_canvas_metadata_cache[ $post_id ] = get_post_meta( $post_id, '_divi_off_canvas_data', true );
		}
		$existing_canvas_metadata  = self::$_off_canvas_metadata_cache[ $post_id ];
		$existing_main_canvas_name = $existing_canvas_metadata['mainCanvasName'] ?? '';
		$main_canvas_name          = $existing_main_canvas_name; // Preserve existing name by default.

		if ( isset( $canvas_data['canvases'] ) && is_array( $canvas_data['canvases'] ) ) {
			foreach ( $canvas_data['canvases'] as $canvas_id => $canvas ) {
				$is_main_canvas = $canvas['isMain'] ?? false;
				$is_global      = $canvas['isGlobal'] ?? false;

				// Handle global canvases FIRST - save as posts in et_pb_canvas post type.
				// This must be checked before isMain check to ensure global canvases that are
				// set as main canvas are still saved as global canvas posts.
				// Only save if the canvas content actually changed.
				if ( $is_global ) {
					if ( ! $can_manage_global_canvases ) {
						continue;
					}

					// Only process if canvas has content (was actually edited).
					if ( isset( $canvas['content'] ) && ! empty( $canvas['content'] ) ) {
						// Check for existing canvas using batch-fetched map.
						$existing_post = $existing_canvas_posts_map[ $canvas_id ] ?? null;

						if ( ! $existing_post ) {
							// New global canvas - save it.
							self::_save_global_canvas( $canvas_id, $canvas, $post_id );
						} else {
							// Existing canvas - check if content changed.
							$new_content            = self::_prepare_canvas_content( $canvas['content'] );
							$existing_content       = wp_unslash( $existing_post->post_content ?? '' );
							$normalized_new_content = wp_unslash( $new_content );

							if ( $normalized_new_content !== $existing_content ) {
								// Content changed - save and clear cache.
								// _save_global_canvas will handle conversion from local to global if needed.
								self::_save_global_canvas( $canvas_id, $canvas, $post_id );
							} else {
								// Content unchanged - only update metadata if it changed.
								// Use batch-fetched append_to_main value.
								$existing_append_to_main = $append_to_main_map[ $existing_post->ID ] ?? '';
								$new_append_to_main      = $canvas['appendToMainCanvas'] ?? null;

								// Normalize for comparison (null, empty string, and false are equivalent).
								$existing_append_to_main = ( '' === $existing_append_to_main || false === $existing_append_to_main ) ? null : $existing_append_to_main;
								$new_append_to_main      = ( '' === $new_append_to_main || false === $new_append_to_main ) ? null : $new_append_to_main;

								if ( $existing_append_to_main !== $new_append_to_main ) {
									update_post_meta( $existing_post->ID, '_divi_canvas_append_to_main', $new_append_to_main );
								}

								// Use batch-fetched z_index value.
								$existing_z_index = $z_index_map[ $existing_post->ID ] ?? '';
								$new_z_index      = $canvas['zIndex'] ?? null;

								// Normalize for comparison (null, empty string, and false are equivalent).
								$existing_z_index = ( '' === $existing_z_index || false === $existing_z_index ) ? null : $existing_z_index;
								$new_z_index      = ( '' === $new_z_index || false === $new_z_index ) ? null : $new_z_index;

								if ( $existing_z_index !== $new_z_index ) {
									update_post_meta( $existing_post->ID, '_divi_canvas_z_index', $new_z_index );
								}

								// Update canvas title when metadata changes are saved without content changes.
								$existing_name = $existing_post->post_title;
								$new_name      = $canvas['name'] ?? 'Global Canvas';
								if ( $existing_name !== $new_name ) {
									wp_update_post(
										[
											'ID'         => $existing_post->ID,
											'post_title' => $new_name,
										]
									);
								}

								// Remove parent_post_id meta to convert local canvas to global (if converting).
								delete_post_meta( $existing_post->ID, '_divi_canvas_parent_post_id' );
								delete_post_meta( $existing_post->ID, '_divi_canvas_parent_context' );
							}
						}
					}

					// If this global canvas is also the main canvas, save its name.
					// Main canvas content is saved to post_content by the frontend.
					if ( $is_main_canvas ) {
						$main_canvas_name = $canvas['name'] ?? 'Main Canvas';
					}

					continue; // Skip adding to local metadata.
				}

				// Save main canvas name (but skip its content - it's in post_content).
				// This only applies to local canvases that are main.
				if ( $is_main_canvas ) {
					$main_canvas_name = $canvas['name'] ?? 'Main Canvas';
					continue;
				}

				// Save local canvas as a post in et_pb_canvas post type.
				// Only save if the canvas content actually changed.
				if ( isset( $canvas['content'] ) && ! empty( $canvas['content'] ) ) {
					$canvas_parent_post_id  = absint( $canvas['parentPostId'] ?? 0 );
					$canvas_parent_context  = isset( $canvas['parentContextKey'] ) && is_string( $canvas['parentContextKey'] )
						? sanitize_text_field( $canvas['parentContextKey'] )
						: '';
					$theme_builder_layout   = isset( $canvas['themeBuilderLayout'] ) && is_string( $canvas['themeBuilderLayout'] )
						? sanitize_key( $canvas['themeBuilderLayout'] )
						: '';
					$is_slot_template_owned = false;

					// For template-owned canvases, always resolve owner post ID from layout slot.
					// This avoids relying on stale/missing parentPostId coming from payload.
					if ( in_array( $theme_builder_layout, [ 'header', 'body', 'footer' ], true ) ) {
						$template_owner_post_id = self::_get_theme_builder_layout_post_id_by_slot( (int) $post_id, $theme_builder_layout );
						if ( 0 < $template_owner_post_id ) {
							$canvas_parent_post_id  = $template_owner_post_id;
							$canvas_parent_context  = '';
							$is_slot_template_owned = true;
						}
					}

					if ( ! $is_slot_template_owned && '' === $canvas_parent_context && is_string( $default_context_key ) ) {
						$canvas_parent_context = $default_context_key;
					}

					$is_context_owned  = '' !== $canvas_parent_context;
					$resolved_post_id  = $canvas_parent_post_id > 0 ? $canvas_parent_post_id : (int) $post_id;
					$is_template_owned = $is_context_owned ? false : ( (int) $resolved_post_id !== (int) $post_id );
					$storage_canvas_id = (string) $canvas_id;

					// Slot template canvases are persisted using canonical/base IDs in DB.
					// Builder runtime IDs can be prefixed (e.g. `header-<id>`), so normalize
					// before lookup/write to avoid creating duplicate prefixed rows.
					if ( $is_slot_template_owned ) {
						$normalized_template_canvas_id = self::_normalize_slot_prefixed_canvas_id( (string) $canvas_id );
						if ( '' !== $normalized_template_canvas_id ) {
							$storage_canvas_id = $normalized_template_canvas_id;
						}
					}

					if ( $is_context_owned && ! in_array( $canvas_parent_context, $allowed_parent_context_keys, true ) ) {
						continue;
					}

					if ( ! $is_context_owned && ! in_array( (int) $resolved_post_id, $allowed_parent_post_ids, true ) ) {
						continue;
					}

					// Prevent editing template-owned canvases without Theme Builder permission.
					if ( $is_template_owned && ! $can_edit_theme_builder_canvases ) {
						continue;
					}

					// Capability checks must be based on the resolved owner type.
					if ( $is_context_owned ) {
						if ( ! self::_current_user_can_edit_canvas_context( $canvas_parent_context ) ) {
							continue;
						}
					} elseif ( ! current_user_can( 'edit_post', $resolved_post_id ) ) {
						// Defense in depth: ensure user can edit the resolved parent post.
						continue;
					}

					// Resolve existing local canvas by trying canonical + raw runtime IDs.
					$existing_post               = null;
					$lookup_canvas_id_candidates = array_values(
						array_unique(
							array_filter(
								[
									$storage_canvas_id,
									(string) $canvas_id,
								],
								static function ( $candidate_id ) {
									return is_string( $candidate_id ) && '' !== $candidate_id;
								}
							)
						)
					);

					foreach ( $lookup_canvas_id_candidates as $lookup_canvas_id_candidate ) {
						$candidate_post = $existing_canvas_posts_map[ $lookup_canvas_id_candidate ] ?? null;
						if ( ! $candidate_post ) {
							continue;
						}

						$parent_post_id = $parent_post_id_map[ $candidate_post->ID ] ?? '';
						$parent_context = $parent_context_map[ $candidate_post->ID ] ?? '';

						if ( $is_context_owned ) {
							if ( (string) $parent_context !== (string) $canvas_parent_context ) {
								continue;
							}
						} elseif ( (int) $parent_post_id !== (int) $resolved_post_id ) {
							continue;
						}

						$existing_post = $candidate_post;
						break;
					}

					if ( ! $existing_post ) {
						// New canvas - save it.
						if ( $is_context_owned ) {
							self::_save_local_canvas_for_context( $storage_canvas_id, $canvas, $canvas_parent_context );
						} else {
							self::_save_local_canvas( $storage_canvas_id, $canvas, $resolved_post_id );
						}
					} else {
						// Existing canvas - check if content changed.
						$new_content            = self::_prepare_canvas_content( $canvas['content'] );
						$existing_content       = wp_unslash( $existing_post->post_content ?? '' );
						$normalized_new_content = wp_unslash( $new_content );

						if ( $normalized_new_content !== $existing_content ) {
							// Content changed - save and clear cache.
							if ( $is_context_owned ) {
								self::_save_local_canvas_for_context( $storage_canvas_id, $canvas, $canvas_parent_context );
							} else {
								self::_save_local_canvas( $storage_canvas_id, $canvas, $resolved_post_id );
							}
						} else {
							// Content unchanged - only update metadata if it changed.
							// Use batch-fetched append_to_main value.
							$existing_append_to_main = $append_to_main_map[ $existing_post->ID ] ?? '';
							$new_append_to_main      = $canvas['appendToMainCanvas'] ?? null;

							// Normalize for comparison (null, empty string, and false are equivalent).
							$existing_append_to_main = ( '' === $existing_append_to_main || false === $existing_append_to_main ) ? null : $existing_append_to_main;
							$new_append_to_main      = ( '' === $new_append_to_main || false === $new_append_to_main ) ? null : $new_append_to_main;

							if ( $existing_append_to_main !== $new_append_to_main ) {
								update_post_meta( $existing_post->ID, '_divi_canvas_append_to_main', $new_append_to_main );
							}

							// Use batch-fetched z_index value.
							$existing_z_index = $z_index_map[ $existing_post->ID ] ?? '';
							$new_z_index      = $canvas['zIndex'] ?? null;

							// Normalize for comparison (null, empty string, and false are equivalent).
							$existing_z_index = ( '' === $existing_z_index || false === $existing_z_index ) ? null : $existing_z_index;
							$new_z_index      = ( '' === $new_z_index || false === $new_z_index ) ? null : $new_z_index;

							if ( $existing_z_index !== $new_z_index ) {
								update_post_meta( $existing_post->ID, '_divi_canvas_z_index', $new_z_index );
							}

							// Update other metadata if changed.
							$existing_name = $existing_post->post_title;
							$new_name      = $canvas['name'] ?? 'Local Canvas';
							if ( $existing_name !== $new_name ) {
								wp_update_post(
									[
										'ID'         => $existing_post->ID,
										'post_title' => $new_name,
									]
								);
							}
						}
					}
				}
			}
		}

		// Save main canvas name to metadata.
		$canvas_metadata['mainCanvasName'] = $main_canvas_name;

		// Delete only the specific canvases that were explicitly marked as deleted (both global and local).
		// This is safer than deleting all canvases not in the payload, which could wipe
		// all canvases in case of an error.
		$deleted_canvas_ids = $canvas_data['deletedCanvasIds'] ?? [];

		foreach ( $deleted_canvas_ids as $deleted_canvas_id ) {
			if ( ! is_string( $deleted_canvas_id ) || empty( $deleted_canvas_id ) ) {
				continue;
			}

			// Find the canvas post by canvas ID (works for both global and local canvases).
			// First try to find as local canvas regardless of parent post.
			$posts_to_delete = get_posts(
				[
					'post_type'      => self::GLOBAL_CANVAS_POST_TYPE,
					'posts_per_page' => 1,
					'post_status'    => 'any',
					'meta_query'     => [
						[
							'key'     => '_divi_canvas_id',
							'value'   => $deleted_canvas_id,
							'compare' => '=',
						],
						[
							'key'     => '_divi_canvas_parent_post_id',
							'value'   => $local_parent_post_ids,
							'compare' => 'IN',
						],
					],
				]
			);

			// If not found as post-backed local, try as context-backed local (non-singular).
			if ( empty( $posts_to_delete ) && ! empty( $local_parent_context_keys ) ) {
				$posts_to_delete = get_posts(
					[
						'post_type'      => self::GLOBAL_CANVAS_POST_TYPE,
						'posts_per_page' => 1,
						'post_status'    => 'any',
						'meta_query'     => [
							[
								'key'     => '_divi_canvas_id',
								'value'   => $deleted_canvas_id,
								'compare' => '=',
							],
							[
								'key'     => '_divi_canvas_parent_context',
								'value'   => $local_parent_context_keys,
								'compare' => 'IN',
							],
						],
					]
				);
			}

			// If not found as local, try as global canvas (no parent_post_id).
			if ( empty( $posts_to_delete ) && $can_manage_global_canvases ) {
				$posts_to_delete = get_posts(
					[
						'post_type'      => self::GLOBAL_CANVAS_POST_TYPE,
						'posts_per_page' => 1,
						'post_status'    => 'any',
						'meta_query'     => [
							[
								'key'     => '_divi_canvas_id',
								'value'   => $deleted_canvas_id,
								'compare' => '=',
							],
							[
								'key'     => '_divi_canvas_parent_post_id',
								'compare' => 'NOT EXISTS',
							],
							[
								'key'     => '_divi_canvas_parent_context',
								'compare' => 'NOT EXISTS',
							],
						],
					]
				);
			}

			if ( empty( $posts_to_delete ) ) {
				continue;
			}

			$post_to_delete = $posts_to_delete[0];
			wp_delete_post( $post_to_delete->ID, true );
		}

		// Save metadata (only activeCanvasId and mainCanvasName).
		if ( $can_edit_saved_post && ( ! empty( $canvas_metadata['activeCanvasId'] ) || ! empty( $canvas_metadata['mainCanvasName'] ) ) ) {
			update_post_meta( $post_id, '_divi_off_canvas_data', $canvas_metadata );
			// Update cache with new value to keep it in sync.
			self::$_off_canvas_metadata_cache[ $post_id ] = $canvas_metadata;
		} elseif ( $can_edit_saved_post ) {
			// Clean up metadata if empty.
			delete_post_meta( $post_id, '_divi_off_canvas_data' );
			// Clear cache when metadata is deleted.
			unset( self::$_off_canvas_metadata_cache[ $post_id ] );
		}

		// Clean up global variable.
		unset( $GLOBALS['divi_off_canvas_data'] );
	}

	/**
	 * Register REST API routes for off-canvas data.
	 *
	 * @since ??
	 */
	public static function register_rest_routes() {
		register_rest_route(
			'divi/v1',
			'/off-canvas/(?P<post_id>\d+)',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_off_canvas_data' ],
				'permission_callback' => [ __CLASS__, 'get_off_canvas_data_permission' ],
				'args'                => [
					'post_id' => [
						'required'    => true,
						'type'        => 'integer',
						'description' => 'Post ID to get off-canvas data for.',
					],
				],
			]
		);

		// REST endpoint for deleting global canvas.
		register_rest_route(
			'divi/v1',
			'/global-canvas/(?P<canvas_id>[a-zA-Z0-9-]+)',
			[
				'methods'             => 'DELETE',
				'callback'            => [ __CLASS__, 'delete_global_canvas' ],
				'permission_callback' => [ __CLASS__, 'global_canvas_permission' ],
				'args'                => [
					'canvas_id' => [
						'required'    => true,
						'type'        => 'string',
						'description' => 'Canvas ID to delete.',
					],
				],
			]
		);
	}

	/**
	 * Prepare canvas content for storage (serialize if needed).
	 * Note: Security sanitization (AttributeSecurity, DynamicContent) is handled automatically
	 * by the Security class via the update_post_metadata filter for post meta saves,
	 * and via wp_insert_post_data filter for global canvas post_content saves.
	 *
	 * @since ??
	 *
	 * @param mixed $canvas_content Canvas content (string or array).
	 *
	 * @return string Prepared content string.
	 */
	private static function _prepare_canvas_content( $canvas_content ) {
		if ( is_string( $canvas_content ) && str_starts_with( $canvas_content, '<!-- wp:' ) ) {
			// Content is already serialized block format - sanitize like post_content.
			return SavingUtility::prepare_content_for_db( $canvas_content );
		}

		// Content is flat module objects - serialize like frontend does for post_content.
		$blocks = self::_convert_module_data_to_blocks( $canvas_content );

		// Sanitize blocks using the same method as post_content.
		$serialized_content = SavingUtility::serialize_sanitize_blocks( $blocks );

		// Wrap with divi/placeholder like main content (post_content).
		return "<!-- wp:divi/placeholder -->\n" . $serialized_content . "\n<!-- /wp:divi/placeholder -->";
	}

	/**
	 * Save a local canvas as a post in the et_pb_canvas post type.
	 * Local canvases are linked to their parent post via _divi_canvas_parent_post_id meta.
	 *
	 * @since ??
	 *
	 * @param string $canvas_id Canvas ID.
	 * @param array  $canvas    Canvas data.
	 * @param int    $post_id   Parent post ID.
	 */
	private static function _save_local_canvas( $canvas_id, $canvas, $post_id ) {
		// Security check: Verify user has permission to edit the parent post.
		// This is a defense-in-depth measure (main check is in save_off_canvas_data).
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Clear local canvases cache for this post since canvas data has changed.
		// Clear both parsed and unparsed cache entries.
		unset( self::$_local_canvases_cache[ $post_id . '_parsed' ] );
		unset( self::$_local_canvases_cache[ $post_id . '_unparsed' ] );
		// Check if local canvas post already exists.
		$existing_posts = get_posts(
			[
				'post_type'      => self::GLOBAL_CANVAS_POST_TYPE,
				'posts_per_page' => 1,
				'meta_query'     => [
					[
						'key'   => '_divi_canvas_id',
						'value' => $canvas_id,
					],
					[
						'key'   => '_divi_canvas_parent_post_id',
						'value' => $post_id,
					],
				],
			]
		);

		$post_data = [
			'post_title'  => $canvas['name'] ?? 'Local Canvas',
			'post_status' => 'publish',
			'post_type'   => self::GLOBAL_CANVAS_POST_TYPE,
			'meta_input'  => [
				'_divi_canvas_id'             => $canvas_id,
				'_divi_canvas_parent_post_id' => $post_id,
				'_divi_canvas_created_at'     => $canvas['createdAt'] ?? current_time( 'mysql' ),
				'_divi_canvas_append_to_main' => $canvas['appendToMainCanvas'] ?? null,
				'_divi_canvas_z_index'        => $canvas['zIndex'] ?? null,
			],
		];

		// Prepare content.
		if ( isset( $canvas['content'] ) && ! empty( $canvas['content'] ) ) {
			$post_data['post_content'] = wp_slash( self::_prepare_canvas_content( $canvas['content'] ) );
		}

		if ( ! empty( $existing_posts ) ) {
			// Update existing post.
			$post_data['ID'] = $existing_posts[0]->ID;
			wp_update_post( $post_data );
			// Update meta separately since wp_update_post doesn't always handle meta_input reliably.
			update_post_meta( $existing_posts[0]->ID, '_divi_canvas_append_to_main', $canvas['appendToMainCanvas'] ?? null );
			update_post_meta( $existing_posts[0]->ID, '_divi_canvas_z_index', $canvas['zIndex'] ?? null );
		} else {
			// Create new post.
			wp_insert_post( $post_data );
		}

		// Clear Dynamic Assets cache for the parent post when a local canvas content changes.
		ET_Core_PageResource::remove_static_resources( $post_id, 'all', true, 'dynamic', true );
		// Clear cached canvas IDs for this post.
		DynamicAssetsUtils::clear_canvas_ids_cache( $post_id );
	}

	/**
	 * Save a context-backed local canvas as a post in the et_pb_canvas post type.
	 * Context-backed canvases are linked to their parent context via _divi_canvas_parent_context meta.
	 *
	 * @since ??
	 *
	 * @param string $canvas_id Canvas ID.
	 * @param array  $canvas Canvas data.
	 * @param string $context_key Parent context key (e.g. `term:category:12`).
	 */
	private static function _save_local_canvas_for_context( $canvas_id, $canvas, $context_key ) {
		$context_key = is_string( $context_key ) ? sanitize_text_field( $context_key ) : '';
		if ( '' === $context_key ) {
			return;
		}

		// Clear global/local caches since this canvas can affect non-singular requests.
		unset( self::$_global_canvases_cache['parsed'] );
		unset( self::$_global_canvases_cache['unparsed'] );

		// Check if context-backed local canvas post already exists.
		$existing_posts = get_posts(
			[
				'post_type'      => self::GLOBAL_CANVAS_POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'meta_query'     => [
					[
						'key'   => '_divi_canvas_id',
						'value' => $canvas_id,
					],
					[
						'key'   => '_divi_canvas_parent_context',
						'value' => $context_key,
					],
				],
			]
		);

		$post_data = [
			'post_title'  => $canvas['name'] ?? 'Local Canvas',
			'post_status' => 'publish',
			'post_type'   => self::GLOBAL_CANVAS_POST_TYPE,
			'meta_input'  => [
				'_divi_canvas_id'             => $canvas_id,
				'_divi_canvas_parent_context' => $context_key,
				'_divi_canvas_created_at'     => $canvas['createdAt'] ?? current_time( 'mysql' ),
				'_divi_canvas_append_to_main' => $canvas['appendToMainCanvas'] ?? null,
				'_divi_canvas_z_index'        => $canvas['zIndex'] ?? null,
			],
		];

		// Prepare content.
		if ( isset( $canvas['content'] ) && ! empty( $canvas['content'] ) ) {
			$post_data['post_content'] = wp_slash( self::_prepare_canvas_content( $canvas['content'] ) );
		}

		if ( ! empty( $existing_posts ) ) {
			$post_data['ID'] = $existing_posts[0]->ID;
			wp_update_post( $post_data );
			update_post_meta( $existing_posts[0]->ID, '_divi_canvas_append_to_main', $canvas['appendToMainCanvas'] ?? null );
			update_post_meta( $existing_posts[0]->ID, '_divi_canvas_z_index', $canvas['zIndex'] ?? null );

			// Ensure it is not treated as post-backed local.
			delete_post_meta( $existing_posts[0]->ID, '_divi_canvas_parent_post_id' );
		} else {
			wp_insert_post( $post_data );
		}

		// Clear Dynamic Assets cache site-wide (context-backed canvases can appear on multiple URLs).
		// Preserve VB CSS files to prevent visual builder from losing its styles.
		ET_Core_PageResource::remove_static_resources( 'all', 'all', true, 'dynamic', true );
		DynamicAssetsUtils::clear_canvas_ids_cache( 'all' );
	}

	/**
	 * Save a global canvas as a post in the et_pb_canvas post type.
	 * This method handles both new global canvases and converting local canvases to global.
	 *
	 * @since ??
	 *
	 * @param string $canvas_id Canvas ID.
	 * @param array  $canvas    Canvas data.
	 * @param int    $post_id   Parent post ID.
	 */
	private static function _save_global_canvas( $canvas_id, $canvas, $post_id ) {
		// Security check: Verify user has permission to edit the parent post.
		// This is a defense-in-depth measure (main check is in save_off_canvas_data).
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Clear global canvases cache since canvas data has changed.
		// Clear both parsed and unparsed cache entries.
		unset( self::$_global_canvases_cache['parsed'] );
		unset( self::$_global_canvases_cache['unparsed'] );
		// Find existing canvas post by canvas ID (could be local or global).
		$existing_posts = get_posts(
			[
				'post_type'      => self::GLOBAL_CANVAS_POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'meta_query'     => [
					[
						'key'   => '_divi_canvas_id',
						'value' => $canvas_id,
					],
				],
			]
		);

		$post_data = [
			'post_title'  => $canvas['name'] ?? 'Global Canvas',
			'post_status' => 'publish',
			'post_type'   => self::GLOBAL_CANVAS_POST_TYPE,
			'meta_input'  => [
				'_divi_canvas_id'             => $canvas_id,
				'_divi_canvas_created_at'     => $canvas['createdAt'] ?? current_time( 'mysql' ),
				'_divi_canvas_append_to_main' => $canvas['appendToMainCanvas'] ?? null,
				'_divi_canvas_z_index'        => $canvas['zIndex'] ?? null,
			],
		];

		// Prepare content.
		if ( isset( $canvas['content'] ) && ! empty( $canvas['content'] ) ) {
			$post_data['post_content'] = wp_slash( self::_prepare_canvas_content( $canvas['content'] ) );
		}

		if ( ! empty( $existing_posts ) ) {
			// Update existing canvas post (could be converting from local to global).
			$post_data['ID'] = $existing_posts[0]->ID;
			wp_update_post( $post_data );
			// Update meta separately since wp_update_post doesn't always handle meta_input reliably.
			update_post_meta( $existing_posts[0]->ID, '_divi_canvas_append_to_main', $canvas['appendToMainCanvas'] ?? null );
			update_post_meta( $existing_posts[0]->ID, '_divi_canvas_z_index', $canvas['zIndex'] ?? null );
			// Remove parent_post_id meta to convert local canvas to global.
			delete_post_meta( $existing_posts[0]->ID, '_divi_canvas_parent_post_id' );
			delete_post_meta( $existing_posts[0]->ID, '_divi_canvas_parent_context' );
		} else {
			// Create new global canvas post.
			wp_insert_post( $post_data );
		}

		// Clear Dynamic Assets cache for all posts when a global canvas content changes.
		// Global canvases can be appended to any post, so we need to clear cache site-wide.
		// Preserve VB CSS files to prevent visual builder from losing its styles.
		ET_Core_PageResource::remove_static_resources( 'all', 'all', true, 'dynamic', true );
		// Clear cached canvas IDs for all posts.
		DynamicAssetsUtils::clear_canvas_ids_cache( 'all' );
	}

	/**
	 * Get off-canvas data for a post (public method for SettingsData system).
	 *
	 * NOTE: This function parses canvas content into module data format for Visual Builder.
	 * Parsing increments orderIndex, so this should ONLY be called in Visual Builder context
	 * (REST API), never during frontend rendering.
	 *
	 * @since ??
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array Off-canvas data including canvases, activeCanvasId, and mainCanvasName.
	 */
	public static function get_off_canvas_data_for_post( $post_id ) {
		$post_id                = absint( $post_id );
		$post_type              = get_post_type( $post_id );
		$is_tb_layout_post_type = ! empty( $post_type ) && et_theme_builder_is_layout_post_type( $post_type );

		if ( 0 === $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return [
				'canvases'       => [],
				'activeCanvasId' => '',
				'mainCanvasName' => '',
			];
		}

		// Get canvas metadata (only activeCanvasId and mainCanvasName now).
		// Use static cache to avoid duplicate queries if save_off_canvas_data was already called.
		if ( ! isset( self::$_off_canvas_metadata_cache[ $post_id ] ) ) {
			self::$_off_canvas_metadata_cache[ $post_id ] = get_post_meta( $post_id, '_divi_off_canvas_data', true );
		}
		$canvas_metadata = self::$_off_canvas_metadata_cache[ $post_id ];

		// Get cached canvas data for metadata (avoids DB queries).
		$canvas_data         = DynamicAssetsUtils::get_all_canvas_data_for_post( $post_id );
		$all_canvas_metadata = $canvas_data['all_canvas_metadata'] ?? [];

		// Collect post IDs for batch fetching.
		$canvas_post_ids = [];
		foreach ( $all_canvas_metadata as $canvas_meta ) {
			if ( ! empty( $canvas_meta['postId'] ) ) {
				$canvas_post_ids[] = $canvas_meta['postId'];
			}
		}

		// Reuse post objects from get_all_canvas_data_for_post if available (avoids duplicate get_posts query).
		$canvas_posts_map        = [];
		$created_at_map          = [];
		$all_parent_post_ids     = [];
		$all_parent_context_keys = [];
		if ( ! empty( $canvas_post_ids ) ) {
			// Check if post objects are cached from get_all_canvas_data_for_post.
			$cached_posts = DynamicAssetsUtils::get_cached_canvas_posts( $post_id );
			if ( ! empty( $cached_posts ) ) {
				// Use cached post objects - filter to only include the ones we need.
				foreach ( $canvas_post_ids as $canvas_post_id ) {
					if ( isset( $cached_posts[ $canvas_post_id ] ) ) {
						$canvas_posts_map[ $canvas_post_id ] = $cached_posts[ $canvas_post_id ];
					}
				}
			}

			// If we don't have all posts cached, fetch missing ones.
			$missing_post_ids = array_diff( $canvas_post_ids, array_keys( $canvas_posts_map ) );
			if ( ! empty( $missing_post_ids ) ) {
				$canvas_posts = get_posts(
					[
						'post__in'       => array_unique( $missing_post_ids ),
						'posts_per_page' => -1,
						'post_type'      => self::GLOBAL_CANVAS_POST_TYPE,
					]
				);
				foreach ( $canvas_posts as $canvas_post ) {
					$canvas_posts_map[ $canvas_post->ID ] = $canvas_post;
				}
			}

			// Build created_at_map from cached metadata first (if available).
			foreach ( $all_canvas_metadata as $canvas_meta ) {
				$post_id_for_meta = $canvas_meta['postId'] ?? null;
				// Check if createdAt exists in cached metadata (even if empty string).
				if ( $post_id_for_meta && isset( $canvas_meta['createdAt'] ) ) {
					$created_at_map[ $post_id_for_meta ] = $canvas_meta['createdAt'];
				}
			}

			// Only fetch createdAt and parent_post_id from database if not in cached metadata.
			$missing_created_at_ids  = array_diff( $canvas_post_ids, array_keys( $created_at_map ) );
			$parent_meta_maps        = DynamicAssetsUtils::_batch_get_post_meta(
				$canvas_post_ids,
				[ '_divi_canvas_parent_post_id', '_divi_canvas_parent_context' ]
			);
			$all_parent_post_ids     = $parent_meta_maps['_divi_canvas_parent_post_id'] ?? [];
			$all_parent_context_keys = $parent_meta_maps['_divi_canvas_parent_context'] ?? [];
			if ( ! empty( $missing_created_at_ids ) ) {
				// Single key mode returns [ post_id => meta_value ] directly.
				$fetched_created_at = DynamicAssetsUtils::_batch_get_post_meta( $missing_created_at_ids, '_divi_canvas_created_at' );
				$created_at_map     = array_merge( $created_at_map, $fetched_created_at );
			}
		}

		// Parse content for Visual Builder (this increments orderIndex, so only call in VB context).
		$canvases = [];
		foreach ( $all_canvas_metadata as $canvas_id => $canvas_meta ) {
			$canvas_content = $canvas_meta['content'] ?? '';
			$module_data    = null;

			// Parse content into module data format for Visual Builder.
			if ( $canvas_content ) {
				try {
					$unwrapped_content = ModuleUtils::maybe_unwrap_placeholder_block( $canvas_content );
					$module_data       = ConversionUtils::parseSerializedPostIntoFlatModuleObject( $unwrapped_content );
				} catch ( \Exception $e ) {
					$module_data = null;
				}
			}

			// Get canvas post from batch-fetched map.
			$canvas_post       = $canvas_posts_map[ $canvas_meta['postId'] ] ?? null;
			$canvas_created_at = $created_at_map[ $canvas_meta['postId'] ] ?? null;
			$canvas_created_at = $canvas_created_at ? $canvas_created_at : ( $canvas_post ? $canvas_post->post_date : '' );

			$canvases[ $canvas_id ] = [
				'id'                 => $canvas_id,
				'name'               => $canvas_post ? $canvas_post->post_title : '',
				'isMain'             => false,
				'isGlobal'           => $canvas_meta['isGlobal'] ?? false,
				'parentPostId'       => isset( $all_parent_post_ids[ $canvas_meta['postId'] ] ) ? absint( $all_parent_post_ids[ $canvas_meta['postId'] ] ) : null,
				'parentContextKey'   => isset( $all_parent_context_keys[ $canvas_meta['postId'] ] ) && is_string( $all_parent_context_keys[ $canvas_meta['postId'] ] )
					? sanitize_text_field( $all_parent_context_keys[ $canvas_meta['postId'] ] )
					: null,
				'themeBuilderLayout' => null,
				'appendToMainCanvas' => $canvas_meta['appendToMainCanvas'] ?? null,
				'zIndex'             => $canvas_meta['zIndex'] ?? null,
				'content'            => $module_data,
				'createdAt'          => $canvas_created_at,
			];
		}
		// In Theme Builder layout editor, local canvases must be scoped to the
		// current layout owner only. Keep global canvases available.
		if ( $is_tb_layout_post_type ) {
			foreach ( $canvases as $canvas_id => $canvas ) {
				$is_global = ! empty( $canvas['isGlobal'] );
				if ( $is_global ) {
					continue;
				}

				$canvas_parent_post_id = isset( $canvas['parentPostId'] ) ? absint( $canvas['parentPostId'] ) : 0;
				if ( $post_id !== $canvas_parent_post_id ) {
					unset( $canvases[ $canvas_id ] );
				}
			}
		}

		return [
			'canvases'       => $canvases,
			'activeCanvasId' => $canvas_metadata['activeCanvasId'] ?? '',
			'mainCanvasName' => $canvas_metadata['mainCanvasName'] ?? '', // Main canvas name (content is in post_content).
		];
	}

	/**
	 * Get off-canvas data for a context-backed owner (non-singular pages).
	 *
	 * NOTE: This function parses canvas content into module data format for Visual Builder.
	 * Parsing increments orderIndex, so this should ONLY be called in Visual Builder context,
	 * never during frontend rendering.
	 *
	 * @since ??
	 *
	 * @param string $context_key Parent context key (e.g. `term:category:12`).
	 *
	 * @return array Off-canvas data including canvases.
	 */
	public static function get_off_canvas_data_for_context( string $context_key ) {
		$context_key = sanitize_text_field( $context_key );
		if ( '' === $context_key ) {
			return [
				'canvases'       => [],
				'activeCanvasId' => '',
				'mainCanvasName' => '',
			];
		}

		if ( ! self::_current_user_can_edit_canvas_context( $context_key ) ) {
			return [
				'canvases'       => [],
				'activeCanvasId' => '',
				'mainCanvasName' => '',
			];
		}

		// Fetch context-backed local canvases.
		$canvas_posts = CanvasUtils::get_context_canvas_posts( $context_key, [ 'post_status' => 'any' ] );
		if ( empty( $canvas_posts ) ) {
			return [
				'canvases'       => [],
				'activeCanvasId' => '',
				'mainCanvasName' => '',
			];
		}

		// Batch fetch required meta.
		$post_ids           = array_map(
			static function ( $post ) {
				return $post->ID;
			},
			$canvas_posts
		);
		$all_meta           = DynamicAssetsUtils::_batch_get_post_meta(
			$post_ids,
			[
				'_divi_canvas_id',
				'_divi_canvas_append_to_main',
				'_divi_canvas_z_index',
				'_divi_canvas_created_at',
				'_divi_canvas_parent_context',
			]
		);
		$canvas_id_map      = $all_meta['_divi_canvas_id'] ?? [];
		$append_to_main_map = $all_meta['_divi_canvas_append_to_main'] ?? [];
		$z_index_map        = $all_meta['_divi_canvas_z_index'] ?? [];
		$created_at_map     = $all_meta['_divi_canvas_created_at'] ?? [];
		$parent_context_map = $all_meta['_divi_canvas_parent_context'] ?? [];

		$canvases = [];
		foreach ( $canvas_posts as $canvas_post ) {
			$canvas_id = $canvas_id_map[ $canvas_post->ID ] ?? '';
			if ( '' === $canvas_id ) {
				continue;
			}

			// Ensure the post is actually owned by this context.
			$parent_context = $parent_context_map[ $canvas_post->ID ] ?? '';
			if ( (string) $parent_context !== (string) $context_key ) {
				continue;
			}

			$module_data = null;
			$content     = $canvas_post->post_content ?? '';

			// Do not wp_unslash() here: WP_Post->post_content is already unslashed, and wp_unslash()
			// would corrupt legitimate backslash sequences (e.g. \u003c in block attrs JSON).
			if ( $content ) {
				try {
					$unwrapped_content = ModuleUtils::maybe_unwrap_placeholder_block( $content );
					$module_data       = ConversionUtils::parseSerializedPostIntoFlatModuleObject( $unwrapped_content );
				} catch ( \Exception $e ) {
					$module_data = null;
				}
			}

			$append_to_main = $append_to_main_map[ $canvas_post->ID ] ?? '';
			$append_to_main = ( '' === $append_to_main || false === $append_to_main ) ? null : $append_to_main;

			$z_index = $z_index_map[ $canvas_post->ID ] ?? '';
			$z_index = ( '' === $z_index || false === $z_index ) ? null : $z_index;

			$created_at = $created_at_map[ $canvas_post->ID ] ?? '';
			$created_at = $created_at ? $created_at : $canvas_post->post_date;

			$canvases[ $canvas_id ] = [
				'id'                 => $canvas_id,
				'name'               => $canvas_post->post_title,
				'isMain'             => false,
				'isGlobal'           => false,
				'parentPostId'       => null,
				'parentContextKey'   => $context_key,
				'themeBuilderLayout' => null,
				'appendToMainCanvas' => $append_to_main,
				'zIndex'             => $z_index,
				'content'            => $module_data,
				'createdAt'          => $created_at,
			];
		}

		return [
			'canvases'       => $canvases,
			'activeCanvasId' => '',
			'mainCanvasName' => '',
		];
	}

	/**
	 * Get off-canvas data for a post.
	 *
	 * @since ??
	 *
	 * @param \WP_REST_Request $request REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_off_canvas_data( \WP_REST_Request $request ) {
		$post_id = $request->get_param( 'post_id' );

		return rest_ensure_response(
			self::get_off_canvas_data_for_post( $post_id )
		);
	}

	/**
	 * Check if any canvases exist (local or global).
	 * Optimized to use cached canvas data when available to avoid queries.
	 *
	 * @since ??
	 *
	 * @param int|null $post_id Optional. Post ID to check for local canvases. If null, only checks global canvases.
	 *
	 * @return bool True if any canvases exist, false otherwise.
	 */
	private static function _has_any_canvases( $post_id = null ) {
		// If post_id is provided, try to use cached canvas data first (avoids queries).
		if ( $post_id ) {
			$cached_data = get_post_meta( $post_id, '_divi_dynamic_assets_canvases_used', true );
			if ( is_array( $cached_data ) && isset( $cached_data['all_canvas_metadata'] ) ) {
				// Cache exists - check if it has any canvases.
				$all_canvas_metadata = $cached_data['all_canvas_metadata'] ?? [];
				if ( ! empty( $all_canvas_metadata ) ) {
					return true;
				}
			}
		}

		// Cache miss or no post_id - fall back to lightweight queries.
		// Check global canvases first (most common case).
		// Use 'fields' => 'ids' and 'posts_per_page' => 1 for lightweight check.
		$global_posts = CanvasUtils::get_canvas_posts(
			true,
			null,
			[
				'posts_per_page' => 1,
				'fields'         => 'ids', // Only get IDs for performance.
			]
		);

		if ( ! empty( $global_posts ) ) {
			return true;
		}

		// Check local canvases if post_id provided.
		if ( $post_id ) {
			$local_posts = CanvasUtils::get_local_canvas_posts(
				$post_id,
				[
					'posts_per_page' => 1,
					'fields'         => 'ids', // Only get IDs for performance.
				]
			);

			if ( ! empty( $local_posts ) ) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Permission callback for getting off-canvas data.
	 *
	 * @since ??
	 *
	 * @param \WP_REST_Request $request REST request.
	 *
	 * @return bool
	 */
	public static function get_off_canvas_data_permission( \WP_REST_Request $request ) {
		$post_id = $request->get_param( 'post_id' );

		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Permission callback for global canvas operations.
	 *
	 * @since ??
	 *
	 * @param \WP_REST_Request $request REST request.
	 *
	 * @return bool
	 */
	public static function global_canvas_permission( \WP_REST_Request $request ) {
		// Verify request is valid (required by WordPress REST API callback signature).
		if ( ! $request instanceof \WP_REST_Request ) {
			return false;
		}

		// User must be able to edit posts to manage global canvases.
		return current_user_can( 'edit_posts' );
	}


	/**
	 * Delete a global canvas.
	 *
	 * @since ??
	 *
	 * @param \WP_REST_Request $request REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function delete_global_canvas( \WP_REST_Request $request ) {
		$canvas_id = $request->get_param( 'canvas_id' );

		if ( ! $canvas_id ) {
			return new \WP_Error( 'missing_canvas_id', __( 'Canvas ID is required.', 'et_builder' ), [ 'status' => 400 ] );
		}

		// Find the global canvas post.
		$existing_posts = get_posts(
			[
				'post_type'      => self::GLOBAL_CANVAS_POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'any', // Include all statuses to find the canvas even if status changed.
				'meta_query'     => [
					[
						'key'     => '_divi_canvas_id',
						'value'   => $canvas_id,
						'compare' => '=',
					],
					[
						'key'     => '_divi_canvas_parent_post_id',
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => '_divi_canvas_parent_context',
						'compare' => 'NOT EXISTS',
					],
				],
			]
		);

		if ( empty( $existing_posts ) ) {
			return new \WP_Error(
				'canvas_not_found',
				__( 'Global canvas not found.', 'et_builder' ),
				[
					'status'    => 404,
					'canvas_id' => $canvas_id,
				]
			);
		}

		$post_id = $existing_posts[0]->ID;

		// Delete the post permanently.
		$deleted = wp_delete_post( $post_id, true );

		if ( ! $deleted ) {
			return new \WP_Error(
				'delete_failed',
				__( 'Failed to delete global canvas.', 'et_builder' ),
				[
					'status'    => 500,
					'canvas_id' => $canvas_id,
				]
			);
		}

		return rest_ensure_response(
			[
				'success'   => true,
				'canvas_id' => $canvas_id,
			]
		);
	}


	/**
	 * Detect and process off-canvas interactions during block data processing.
	 * This runs early in Divi's block pipeline to pre-process needed canvas content.
	 *
	 * IMPORTANT: This method filters out same-canvas interactions at detection time.
	 * Only interactions targeting elements on a DIFFERENT canvas will:
	 * - Be stored in $GLOBALS['divi_off_canvas_target_ids']
	 * - Trigger canvas appending via _process_off_canvas_content_for_targets()
	 * - Potentially affect OrderIndexResetManager logic
	 *
	 * Same-canvas interactions (where both source and target are on the main canvas)
	 * are filtered out immediately and never enter the off-canvas processing pipeline.
	 *
	 * @since ??
	 *
	 * @param array         $parsed_block The block being rendered.
	 * @param array         $source_block An un-modified copy of $parsed_block.
	 * @param null|WP_Block $parent_block If this is a nested block, a reference to the parent block.
	 *
	 * @return array The unmodified parsed block.
	 */
	public static function detect_and_process_off_canvas_interactions( $parsed_block, $source_block, $parent_block ) {
		// Verify parameters are valid (required by WordPress block filter callback signature).
		if ( ! is_array( $parsed_block ) || ! is_array( $source_block ) ) {
			return $parsed_block;
		}

		// Verify parent_block is valid if provided (required by WordPress block filter callback signature).
		if ( null !== $parent_block && ! $parent_block instanceof \WP_Block ) {
			return $parsed_block;
		}

		// Only process Divi blocks.
		if ( ! isset( $parsed_block['blockName'] ) || ! str_starts_with( $parsed_block['blockName'], 'divi/' ) ) {
			return $parsed_block;
		}

		// Only run on frontend (not in admin or visual builder).
		if ( Conditions::is_admin_request() || Conditions::is_vb_enabled() ) {
			return $parsed_block;
		}

		// Check for interactions in block attributes.
		$block_attrs       = $parsed_block['attrs'] ?? [];
		$interactions_data = $block_attrs['module']['decoration']['interactions'] ?? null;

		if ( ! $interactions_data ) {
			return $parsed_block;
		}

		$interactions = $interactions_data['desktop']['value']['interactions'] ?? [];

		if ( ! is_array( $interactions ) || empty( $interactions ) ) {
			return $parsed_block;
		}

		// Extract target IDs from interactions.
		$target_ids              = [];
		$hide_on_load_target_ids = [];
		foreach ( $interactions as $interaction ) {
			$target_class = $interaction['target']['targetClass'] ?? '';

			if ( $target_class && preg_match( '/et-interaction-target-([a-zA-Z0-9-]+)/', $target_class, $matches ) ) {
				$target_id    = $matches[1];
				$target_ids[] = $target_id;

				// Track target IDs that will be hidden on load (trigger === 'load' AND effect === 'removeVisibility').
				$trigger = $interaction['trigger'] ?? '';
				$effect  = $interaction['effect'] ?? '';
				if ( 'load' === $trigger && 'removeVisibility' === $effect ) {
					$hide_on_load_target_ids[] = $target_id;
				}
			}
		}

		if ( empty( $target_ids ) ) {
			return $parsed_block;
		}

		// Get the current post ID to track target IDs per template/post.
		// This ensures canvases are only appended to the template/post that targets them.
		$post_id = self::_get_current_post_id();
		if ( ! $post_id ) {
			return $parsed_block;
		}

		// Store hide-on-load target IDs before filtering for off-canvas detection.
		// This ensures main-canvas targets are pre-hidden before JS initializes.
		if ( ! empty( $hide_on_load_target_ids ) ) {
			$existing_hide_on_load_target_ids = self::_get_per_post_global_value( 'divi_hide_on_load_target_ids', $post_id, [] );
			$merged_hide_on_load_target_ids   = array_unique( array_merge( $existing_hide_on_load_target_ids, $hide_on_load_target_ids ) );
			self::_set_per_post_global_value( 'divi_hide_on_load_target_ids', $post_id, $merged_hide_on_load_target_ids );
		}

		// Filter out targets that are on the main canvas BEFORE storing them.
		// This prevents same-canvas interactions from triggering any off-canvas processing,
		// including OrderIndexResetManager logic. Only interactions targeting elements on
		// a different canvas should cause canvas appending and affect order index.
		// Cache post object to avoid redundant get_post() calls for multiple blocks with interactions.
		// This is critical for performance when many blocks have interactions on large pages.
		if ( ! isset( self::$_main_post_cache[ $post_id ] ) ) {
			self::$_main_post_cache[ $post_id ] = get_post( $post_id );
		}
		$main_post           = self::$_main_post_cache[ $post_id ];
		$main_canvas_content = $main_post ? $main_post->post_content : '';

		if ( ! empty( $main_canvas_content ) ) {
			$target_ids = array_filter(
				$target_ids,
				function ( $target_id ) use ( $main_canvas_content ) {
					// Only keep target IDs that are NOT on the main canvas.
					return ! self::canvas_block_content_contains_target( $main_canvas_content, $target_id );
				}
			);

			// Filter hide-on-load target IDs to only include off-canvas targets.
			$hide_on_load_target_ids = array_filter(
				$hide_on_load_target_ids,
				function ( $target_id ) use ( $main_canvas_content ) {
					// Only keep target IDs that are NOT on the main canvas.
					return ! self::canvas_block_content_contains_target( $main_canvas_content, $target_id );
				}
			);
		}

		// Only store and process target IDs if there are actually off-canvas targets.
		// This ensures same-canvas interactions never affect OrderIndexResetManager or trigger canvas processing.
		if ( empty( $target_ids ) ) {
			return $parsed_block;
		}

		// Store target IDs per post_id for later processing after all main content blocks are rendered.
		// This ensures canvas content continues the orderIndex sequence from main content,
		// so CSS class names match between CSS generation and HTML output.
		// Keying by post_id ensures canvases are only appended to the template/post that targets them.
		$existing_target_ids = self::_get_per_post_global_value( 'divi_off_canvas_target_ids', $post_id, [] );
		$merged_target_ids   = array_unique( array_merge( $existing_target_ids, $target_ids ) );
		self::_set_per_post_global_value( 'divi_off_canvas_target_ids', $post_id, $merged_target_ids );

		// Store hide-on-load target IDs per post_id for off-canvas CSS generation.
		// These target IDs will be hidden via CSS before JavaScript executes to prevent flash.
		if ( ! empty( $hide_on_load_target_ids ) ) {
			$existing_hide_on_load_target_ids = self::_get_per_post_global_value( 'divi_off_canvas_hide_on_load_target_ids', $post_id, [] );
			$merged_hide_on_load_target_ids   = array_unique( array_merge( $existing_hide_on_load_target_ids, $hide_on_load_target_ids ) );
			self::_set_per_post_global_value( 'divi_off_canvas_hide_on_load_target_ids', $post_id, $merged_hide_on_load_target_ids );
		}

		return $parsed_block;
	}

	/**
	 * Reset orderIndex before HTML rendering starts.
	 *
	 * Uses the unified OrderIndexResetManager to handle reset logic, ensuring
	 * consistent orderIndex numbering across Theme Builder templates, canvases,
	 * and Canvas Portal modules.
	 *
	 * @since ??
	 *
	 * @param string $content The content being rendered.
	 *
	 * @return string The unmodified content.
	 */
	public static function reset_order_index_before_rendering( $content ) {
		// Initialize page-wide off-canvas tracking once per HTTP request.
		OrderIndexResetManager::init_page_render();

		// Clear caches when starting a new rendering context (header/post content/footer).
		// This ensures cached post ID and rendering context are refreshed for each area.
		self::reset_caches();

		// Use unified reset manager to handle reset logic.
		OrderIndexResetManager::maybe_reset( OrderIndexResetManager::PHASE_BEFORE_RENDERING );

		return $content;
	}

	/**
	 * Reset orderIndex right before do_blocks() runs, after canvas processing.
	 *
	 * Canvas processing (process_canvas_content_above_main_content) may parse blocks
	 * for interaction detection, which increments orderIndex. This filter runs at
	 * priority 8, right before et_builder_render_layout_do_blocks (priority 9) calls
	 * do_blocks(), ensuring orderIndex starts from 0 for actual HTML rendering.
	 *
	 * Uses the unified OrderIndexResetManager to handle reset logic.
	 *
	 * @since ??
	 *
	 * @param string $content The content being rendered.
	 *
	 * @return string The unmodified content.
	 */
	public static function reset_order_index_before_do_blocks( $content ) {
		// Use unified reset manager to handle reset logic.
		OrderIndexResetManager::maybe_reset( OrderIndexResetManager::PHASE_BEFORE_DO_BLOCKS );

		return $content;
	}

	/**
	 * Process canvas content that should be appended above main content.
	 * This renders canvases with appendToMainCanvas set to 'above'.
	 *
	 * @since ??
	 *
	 * @param string $content The main post content (passed through filter).
	 *
	 * @return string The unmodified content (rendering happens as side effect).
	 */
	public static function process_canvas_content_above_main_content( $content ) {
		// Only run on frontend (not in admin or visual builder).
		if ( Conditions::is_admin_request() || Conditions::is_vb_enabled() ) {
			return $content;
		}

		// Prevent infinite recursion when rendering canvas content.
		if ( ! empty( $GLOBALS['divi_off_canvas_rendering'] ) ) {
			return $content;
		}

		$current_post_id = self::_get_current_post_id();
		if ( ! $current_post_id ) {
			return $content;
		}

		// Skip canvas processing during the_content only for Post Content inside TB body layouts.
		if ( self::_should_skip_off_canvas_during_the_content( $current_post_id ) ) {
			return $content;
		}

		// Skip appending canvases when rendering inner content (e.g., Canvas Portal modules).
		// Append/prepend should only apply to the main canvas, not to Canvas Portal content.
		if ( BlockParserStore::is_rendering_inner_content() ) {
			return $content;
		}

		// In Theme Builder layouts, always use the layout post ID for canvas processing.
		$canvas_post_id = self::_get_canvas_processing_post_id( $current_post_id );

		// Process canvases that should be appended above main content.
		// The flag is set inside _process_appended_canvases before rendering
		// so it's available during canvas rendering when PHASE_NEW_STORE_INSTANCE might be triggered.
		self::_process_appended_canvases( $canvas_post_id, 'above' );

		// Return content unchanged (rendering happens as side effect).
		return $content;
	}

	/**
	 * Process canvas content after all main content blocks are rendered.
	 * This ensures canvas content continues the orderIndex sequence from main content,
	 * so CSS class names match between CSS generation and HTML output.
	 *
	 * @since ??
	 *
	 * @param string $content The main post content (passed through filter).
	 *
	 * @return string The unmodified content (rendering happens as side effect).
	 */
	public static function process_canvas_content_after_main_content( $content ) {
		// Only run on frontend (not in admin or visual builder).
		if ( Conditions::is_admin_request() || Conditions::is_vb_enabled() ) {
			return $content;
		}

		// Prevent infinite recursion when rendering canvas content.
		if ( ! empty( $GLOBALS['divi_off_canvas_rendering'] ) ) {
			return $content;
		}

		$current_post_id = self::_get_current_post_id();
		if ( ! $current_post_id ) {
			return $content;
		}

		// Skip canvas processing during the_content only for Post Content inside TB body layouts.
		if ( self::_should_skip_off_canvas_during_the_content( $current_post_id ) ) {
			return $content;
		}

		// Skip appending canvases when rendering inner content (e.g., Canvas Portal modules).
		// Append/prepend should only apply to the main canvas, not to Canvas Portal content.
		if ( BlockParserStore::is_rendering_inner_content() ) {
			return $content;
		}

		// In Theme Builder layouts, always use the layout post ID for canvas processing.
		// This ensures interactions defined in layouts use the correct canvas storage location.
		$canvas_post_id = self::_get_canvas_processing_post_id( $current_post_id );

		// Process target IDs for the canvas post (layout post in TB context).
		// This ensures canvases are processed using the correct post ID where they're stored.
		$target_ids = self::_get_per_post_global_value( 'divi_off_canvas_target_ids', $canvas_post_id, [] );

		// Process canvases that should be appended below main content before interaction targets.
		// Append is the canonical render path for global canvases with appendToMainCanvas (#50537).
		if ( $canvas_post_id ) {
			self::_process_appended_canvases( $canvas_post_id, 'below' );
		}

		// Process off-canvas content for interaction targets.
		if ( ! empty( $target_ids ) ) {
			// This runs after all main content blocks have been rendered (orderIndex assigned),
			// so canvas content continues the orderIndex sequence sequentially.
			self::_process_off_canvas_content_for_targets( $target_ids, $canvas_post_id );

			// Clean up target IDs for the canvas post.
			unset( $GLOBALS['divi_off_canvas_target_ids'][ $canvas_post_id ] );
		}

		// Return content unchanged (rendering happens as side effect).
		return $content;
	}

	/**
	 * Get the Theme Builder layout post ID if we're in a layout context.
	 * This is used to ensure canvas processing uses the correct post ID for Theme Builder layouts.
	 *
	 * @since ??
	 *
	 * @param int $current_post_id The current post ID.
	 *
	 * @return int|null Layout post ID if in TB context, null otherwise.
	 */
	private static function _get_theme_builder_layout_post_id( $current_post_id ) {
		// Check if we're in a Theme Builder layout context.
		if ( class_exists( '\ET_Post_Stack' ) ) {
			$stacked_post = \ET_Post_Stack::get();
			if ( $stacked_post && isset( $stacked_post->ID ) && $stacked_post->ID !== $current_post_id ) {
				// We're in a stacked post context (likely TB layout), use the stacked post ID.
				return $stacked_post->ID;
			}
		}

		// Check for active Theme Builder layout.
		if ( class_exists( '\ET_Theme_Builder_Layout' ) && method_exists( '\ET_Theme_Builder_Layout', 'get_theme_builder_layout_id' ) ) {
			$layout_id = \ET_Theme_Builder_Layout::get_theme_builder_layout_id();
			if ( $layout_id > 0 && $layout_id !== $current_post_id ) {
				return $layout_id;
			}
		}

		return null;
	}

	/**
	 * Get the current post ID, handling both regular posts and Theme Builder layouts.
	 * Cached per request to avoid redundant calls, but should be cleared when post context changes.
	 *
	 * @since ??
	 *
	 * @return int|false Post ID or false if not available.
	 */
	private static function _get_current_post_id() {
		// Native main-post the_content must win over cached TB layout IDs from prior header/footer passes.
		if ( doing_filter( 'the_content' ) ) {
			$the_id = self::_get_the_content_post_id();
			if ( self::_is_native_content_post_id( $the_id ) ) {
				self::$_current_post_id_cache = $the_id;
				return self::$_current_post_id_cache;
			}
		}

		// Return cached value if available.
		if ( null !== self::$_current_post_id_cache ) {
			return self::$_current_post_id_cache;
		}

		// Check if we're in a Theme Builder layout context.
		// When Layout::render() is called, it uses ET_Post_Stack::replace() to set the layout post.
		// For older Theme Builder code, check if we have an active layout ID.
		if ( class_exists( '\ET_Post_Stack' ) ) {
			$current_post = \ET_Post_Stack::get();
			if ( $current_post && isset( $current_post->ID ) ) {
				self::$_current_post_id_cache = $current_post->ID;
				return self::$_current_post_id_cache;
			}
		}

		// Check for Theme Builder layout context (fallback for older code).
		if ( class_exists( '\ET_Theme_Builder_Layout' ) && method_exists( '\ET_Theme_Builder_Layout', 'get_theme_builder_layout_id' ) ) {
			$layout_id = \ET_Theme_Builder_Layout::get_theme_builder_layout_id();
			if ( $layout_id > 0 ) {
				self::$_current_post_id_cache = $layout_id;
				return self::$_current_post_id_cache;
			}
		}

		// Fall back to get_the_ID() for regular posts.
		self::$_current_post_id_cache = get_the_ID();
		return self::$_current_post_id_cache;
	}

	/**
	 * Process off-canvas content for target IDs through Divi's rendering pipeline.
	 * This ensures proper CSS generation by using Divi's normal block processing.
	 *
	 * @since ??
	 *
	 * @param array $target_ids Array of interaction target IDs.
	 * @param int   $post_id    Post ID to track rendered canvases per template/post.
	 */
	private static function _process_off_canvas_content_for_targets( $target_ids, $post_id ) {
		if ( ! $post_id ) {
			return;
		}

		// Track processed canvases per post_id to avoid duplicate rendering.
		$processed_canvases = self::_get_per_post_global_value( 'divi_off_canvas_processed_canvases', $post_id, [] );
		if ( ! isset( $GLOBALS['divi_off_canvas_local_interaction_rendered'] ) || ! is_array( $GLOBALS['divi_off_canvas_local_interaction_rendered'] ) ) {
			$GLOBALS['divi_off_canvas_local_interaction_rendered'] = [];
		}

		// Get all canvas data (this will cache if not already cached).
		$canvas_data              = DynamicAssetsUtils::get_all_canvas_data_for_post( $post_id );
		$canvas_portal_canvas_ids = $canvas_data['canvas_portal_ids'] ?? [];
		$all_canvas_metadata      = $canvas_data['all_canvas_metadata'] ?? [];

		// Get main canvas content (post_content) to check if targets are on the main canvas.
		// If a target is on the main canvas, we don't need to process any canvas content for it.
		$main_canvas_content = '';
		$main_post           = get_post( $post_id );
		if ( $main_post && isset( $main_post->post_content ) ) {
			$main_canvas_content = $main_post->post_content;
		}

		// Convert metadata to the format expected by the rest of the function.
		$all_canvases = [];
		foreach ( $all_canvas_metadata as $canvas_id => $canvas_meta ) {
			$all_canvases[ $canvas_id ] = [
				'id'                 => $canvas_id,
				'isMain'             => false,
				'isGlobal'           => $canvas_meta['isGlobal'] ?? false,
				'appendToMainCanvas' => $canvas_meta['appendToMainCanvas'] ?? null,
			];
		}

		// Find which canvases contain the target modules.
		$canvases_to_process = [];
		foreach ( $target_ids as $target_id ) {
			// Defense-in-depth: Skip targets that are on the main canvas.
			// Note: These should already be filtered out in detect_and_process_off_canvas_interactions(),
			// but we keep this check as a safety net to ensure same-canvas interactions never
			// trigger canvas appending, even if something changes in the detection logic.
			if ( ! empty( $main_canvas_content ) && self::canvas_block_content_contains_target( $main_canvas_content, $target_id ) ) {
				continue;
			}

			foreach ( $all_canvases as $canvas_id => $canvas_meta ) {
				// Skip already processed canvases.
				if ( in_array( $canvas_id, $processed_canvases, true ) ) {
					continue;
				}

				// Skip main canvas.
				if ( $canvas_meta['isMain'] ?? false ) {
					continue;
				}

				// Skip canvases that are already included via Canvas Portal.
				if ( in_array( $canvas_id, $canvas_portal_canvas_ids, true ) ) {
					continue;
				}

				// Get canvas content from cached metadata.
				$canvas_meta_data = $all_canvas_metadata[ $canvas_id ] ?? null;
				if ( ! $canvas_meta_data ) {
					continue;
				}

				$canvas_content = $canvas_meta_data['content'] ?? null;
				if ( ! $canvas_content ) {
					continue;
				}

				// Check if this canvas contains the target module.
				if ( ! self::canvas_block_content_contains_target( $canvas_content, $target_id ) ) {
					continue;
				}

				$is_global = $canvas_meta_data['isGlobal'] ?? false;
				if ( $is_global ) {
					$append_to_main = $canvas_meta_data['appendToMainCanvas'] ?? null;

					// Global canvases with append positioning are rendered via the append path.
					// Interaction pre-render must not claim them and block append (#50537).
					if ( 'above' === $append_to_main || 'below' === $append_to_main ) {
						continue;
					}

					// Skip global canvases already rendered via append or a prior interaction (#50537).
					if ( self::_is_global_canvas_already_rendered_for_page( $canvas_id ) ) {
						continue;
					}
				} else {
					$canonical_local_canvas_id = self::_normalize_slot_prefixed_canvas_id( (string) $canvas_id );
					if ( '' === $canonical_local_canvas_id ) {
						continue;
					}

					// Builder hydration dedupes template-local duplicates by canonical UID.
					// Mirror that on frontend interaction rendering so duplicate local rows
					// (e.g., `header-<uid>` and `<uid>`) do not both render.
					if ( isset( $GLOBALS['divi_off_canvas_local_interaction_rendered'][ $canonical_local_canvas_id ] ) ) {
						continue;
					}
				}

				$canvases_to_process[] = [
					'canvas_id' => $canvas_id,
					'is_global' => $is_global,
				];
				$processed_canvases[]  = $canvas_id;
				if ( ! $is_global ) {
					$GLOBALS['divi_off_canvas_local_interaction_rendered'][ $canonical_local_canvas_id ] = true;
				}
			}
		}
		// Update processed canvases tracking.
		if ( ! empty( $processed_canvases ) ) {
			self::_set_per_post_global_value( 'divi_off_canvas_processed_canvases', $post_id, $processed_canvases );
		}

		if ( empty( $canvases_to_process ) ) {
			return;
		}

		// Process each canvas through Divi's rendering pipeline.
		foreach ( $canvases_to_process as $canvas_info ) {
			$canvas_id = $canvas_info['canvas_id'];
			$is_global = $canvas_info['is_global'] ?? false;

			self::_render_off_canvas_content_with_css( $canvas_id, $post_id );

			// Mark interaction-only global canvases so other TB areas do not duplicate (#50537).
			if ( $is_global ) {
				if ( ! isset( $GLOBALS['divi_off_canvas_global_rendered'] ) ) {
					$GLOBALS['divi_off_canvas_global_rendered'] = [];
				}

				$canonical_canvas_id = self::_get_canonical_global_canvas_id( $canvas_id );
				$GLOBALS['divi_off_canvas_global_rendered'][ $canonical_canvas_id ] = self::_get_rendering_context();
			}
		}
	}

	/**
	 * Normalize a global canvas ID for cross-context deduplication.
	 *
	 * @since ??
	 *
	 * @param string $canvas_id Raw canvas ID.
	 *
	 * @return string Canonical global canvas ID.
	 */
	private static function _get_canonical_global_canvas_id( string $canvas_id ): string {
		$canonical_canvas_id = self::_normalize_slot_prefixed_canvas_id( $canvas_id );

		return '' !== $canonical_canvas_id ? $canonical_canvas_id : $canvas_id;
	}

	/**
	 * Check whether a global canvas has already been rendered on this page request.
	 *
	 * @since ??
	 *
	 * @param string $canvas_id Canvas ID.
	 *
	 * @return bool
	 */
	private static function _is_global_canvas_already_rendered_for_page( string $canvas_id ): bool {
		$canonical_canvas_id = self::_get_canonical_global_canvas_id( $canvas_id );
		$candidate_ids       = array_unique( [ $canvas_id, $canonical_canvas_id ] );

		if ( ! empty( $GLOBALS['divi_off_canvas_global_rendered'] ) && is_array( $GLOBALS['divi_off_canvas_global_rendered'] ) ) {
			foreach ( $candidate_ids as $candidate_id ) {
				if ( isset( $GLOBALS['divi_off_canvas_global_rendered'][ $candidate_id ] ) ) {
					return true;
				}
			}
		}

		if ( empty( $GLOBALS['divi_off_canvas_rendered'] ) || ! is_array( $GLOBALS['divi_off_canvas_rendered'] ) ) {
			return false;
		}

		foreach ( $GLOBALS['divi_off_canvas_rendered'] as $post_rendered ) {
			if ( ! is_array( $post_rendered ) ) {
				continue;
			}

			foreach ( $candidate_ids as $candidate_id ) {
				if ( isset( $post_rendered[ $candidate_id ] ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Register a canvas injection for this page request.
	 *
	 * @since ??
	 *
	 * @param string      $canvas_id       Canvas ID.
	 * @param bool|null   $is_global       Whether the canvas is global. Null assumes global for legacy paths.
	 * @param int         $owner_post_id   Post/layout ID whose rendered bucket is being injected.
	 * @param string|null $append_to_main  Append position from canvas metadata when known.
	 *
	 * @return bool True when injection should proceed.
	 */
	private static function _register_canvas_page_injection(
		string $canvas_id,
		?bool $is_global = null,
		int $owner_post_id = 0,
		?string $append_to_main = null
	): bool {
		if ( ! isset( $GLOBALS['divi_off_canvas_injected_canvas_ids'] ) || ! is_array( $GLOBALS['divi_off_canvas_injected_canvas_ids'] ) ) {
			$GLOBALS['divi_off_canvas_injected_canvas_ids'] = [];
		}

		$tracking_id = ( null === $is_global || $is_global )
			? self::_get_canonical_global_canvas_id( $canvas_id )
			: ( self::_normalize_slot_prefixed_canvas_id( $canvas_id ) ?: $canvas_id );

		$is_page_wide_global_append = ( null === $is_global || $is_global )
			&& ( 'above' === $append_to_main || 'below' === $append_to_main );

		$dedupe_key = $is_page_wide_global_append
			? 'global-append:' . $tracking_id
			: (string) $owner_post_id . ':' . $tracking_id;

		if ( isset( $GLOBALS['divi_off_canvas_injected_canvas_ids'][ $dedupe_key ] ) ) {
			return false;
		}

		$GLOBALS['divi_off_canvas_injected_canvas_ids'][ $dedupe_key ] = true;

		return true;
	}

	/**
	 * Whether off-canvas hooks should skip during the_content.
	 *
	 * Skips only when Post Content renders inside a TB body layout (stack is et_body_layout).
	 * Native main-post the_content on TB pages must still run when header/footer stacks are active.
	 *
	 * @since ??
	 *
	 * @param int $current_post_id Current post ID.
	 *
	 * @return bool
	 */
	private static function _should_skip_off_canvas_during_the_content( int $current_post_id ): bool {
		if ( ! doing_filter( 'the_content' ) ) {
			return false;
		}

		if ( null === self::_get_theme_builder_layout_post_id( $current_post_id ) ) {
			return false;
		}

		if ( class_exists( '\ET_Post_Stack' ) ) {
			$stacked_post = \ET_Post_Stack::get();
			if ( $stacked_post && isset( $stacked_post->post_type ) && 'et_body_layout' === $stacked_post->post_type ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a post ID refers to native page/post content (not a TB layout post type).
	 *
	 * @since ??
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool
	 */
	private static function _is_native_content_post_id( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}

		$post_type = get_post_type( $post_id );

		return is_string( $post_type )
			&& ! in_array( $post_type, [ 'et_header_layout', 'et_body_layout', 'et_footer_layout' ], true );
	}

	/**
	 * Resolve the post ID used for canvas render buckets and injection.
	 *
	 * Native main-post the_content must use the filtered post ID even when a TB header/footer
	 * layout remains on ET_Post_Stack after template rendering.
	 *
	 * @since ??
	 *
	 * @param int $current_post_id Current post ID from _get_current_post_id().
	 *
	 * @return int
	 */
	private static function _get_canvas_processing_post_id( int $current_post_id ): int {
		if ( doing_filter( 'the_content' ) ) {
			$the_content_post_id = self::_get_the_content_post_id();
			if ( self::_is_native_content_post_id( $the_content_post_id ) ) {
				return $the_content_post_id;
			}
		}

		$layout_post_id = self::_get_theme_builder_layout_post_id( $current_post_id );

		return null !== $layout_post_id ? $layout_post_id : $current_post_id;
	}

	/**
	 * Resolve the post ID for the_content filter execution.
	 *
	 * TB header/footer rendering can leave a layout on ET_Post_Stack while native page
	 * body is filtered via the_content without setup_postdata().
	 *
	 * @since ??
	 *
	 * @return int Post ID or 0 when unknown.
	 */
	private static function _get_the_content_post_id(): int {
		$candidates = [];

		$the_id = get_the_ID();
		if ( $the_id > 0 ) {
			$candidates[] = (int) $the_id;
		}

		global $post;
		if ( $post && isset( $post->ID ) && (int) $post->ID > 0 ) {
			$candidates[] = (int) $post->ID;
		}

		if ( class_exists( '\ET_Post_Stack' ) ) {
			$main_post = \ET_Post_Stack::get_main_post();
			if ( $main_post && isset( $main_post->ID ) && (int) $main_post->ID > 0 ) {
				$candidates[] = (int) $main_post->ID;
			}
		}

		if ( is_singular() ) {
			$queried_id = get_queried_object_id();
			if ( $queried_id > 0 ) {
				$candidates[] = (int) $queried_id;
			}
		}

		global $wp_query;
		if ( isset( $wp_query->post->ID ) && (int) $wp_query->post->ID > 0 ) {
			$candidates[] = (int) $wp_query->post->ID;
		}

		foreach ( $candidates as $candidate_id ) {
			if ( self::_is_native_content_post_id( $candidate_id ) ) {
				return $candidate_id;
			}
		}

		return $candidates[0] ?? 0;
	}

	/**
	 * Normalize slot-prefixed canvas IDs to canonical UID.
	 *
	 * @since ??
	 *
	 * @param string $canvas_id Raw canvas ID.
	 *
	 * @return string
	 */
	private static function _normalize_slot_prefixed_canvas_id( string $canvas_id ): string {
		$canvas_id = sanitize_text_field( $canvas_id );
		if ( '' === $canvas_id ) {
			return '';
		}

		$prefixes = [ 'header-', 'body-', 'footer-' ];
		$changed  = true;

		while ( $changed ) {
			$changed = false;

			foreach ( $prefixes as $prefix ) {
				if ( str_starts_with( $canvas_id, $prefix ) ) {
					$canvas_id = substr( $canvas_id, strlen( $prefix ) );
					$changed   = true;
					break;
				}
			}
		}

		return $canvas_id;
	}

	/**
	 * Check whether a local canvas is owned by the given post ID.
	 *
	 * Local canvases merged from other owners (e.g. page canvases while rendering a TB header)
	 * must not be appended from the wrong rendering context.
	 *
	 * @since ??
	 *
	 * @param array $canvas_meta Canvas metadata from DynamicAssetsUtils.
	 * @param int   $post_id     Expected owner post ID.
	 *
	 * @return bool
	 */
	private static function _local_canvas_belongs_to_post( array $canvas_meta, int $post_id ): bool {
		$canvas_post_id = absint( $canvas_meta['postId'] ?? 0 );
		if ( 0 === $canvas_post_id ) {
			return false;
		}

		$parent_post_id = absint( get_post_meta( $canvas_post_id, '_divi_canvas_parent_post_id', true ) );

		return $parent_post_id === $post_id;
	}

	/**
	 * Render off-canvas content through Divi's normal block processing to generate CSS.
	 *
	 * @since ??
	 *
	 * @param string $canvas_id Canvas ID to render.
	 * @param int    $post_id   Post ID (for CSS context).
	 */
	private static function _render_off_canvas_content_with_css( $canvas_id, $post_id ) {
		// Get canvas content from cached data.
		$canvas_data         = DynamicAssetsUtils::get_all_canvas_data_for_post( $post_id );
		$all_canvas_metadata = $canvas_data['all_canvas_metadata'] ?? [];
		$canvas_meta         = $all_canvas_metadata[ $canvas_id ] ?? null;

		if ( ! $canvas_meta ) {
			return;
		}

		$is_global = $canvas_meta['isGlobal'] ?? false;
		if ( $is_global && self::_is_global_canvas_already_rendered_for_page( $canvas_id ) ) {
			return;
		}

		$canvas_content = $canvas_meta['content'] ?? null;
		if ( ! $canvas_content ) {
			return;
		}

		$canvas_name = is_string( $canvas_meta['name'] ?? '' ) ? $canvas_meta['name'] : '';
		// Fallback for stale metadata caches created before canvas name was included.
		// Re-uses the post object batch already loaded by get_all_canvas_data_for_post() to
		// avoid an extra DB query per stale canvas.
		if ( '' === $canvas_name ) {
			$canvas_post_id = absint( $canvas_meta['postId'] ?? 0 );
			if ( 0 !== $canvas_post_id ) {
				$cached_posts = DynamicAssetsUtils::get_cached_canvas_posts( $post_id );
				if ( isset( $cached_posts[ $canvas_post_id ] ) ) {
					$canvas_name = $cached_posts[ $canvas_post_id ]->post_title;
				}
			}
		}

		// Unwrap the placeholder to get raw block content.
		$unwrapped_content = ModuleUtils::maybe_unwrap_placeholder_block( $canvas_content );

		if ( ! $unwrapped_content ) {
			return;
		}

		// Set flag to prevent infinite recursion when rendering canvas content.
		$GLOBALS['divi_off_canvas_rendering'] = true;
		// Store current canvas context for wrapper filters.
		$GLOBALS['divi_off_canvas_current_id']   = $canvas_id;
		$GLOBALS['divi_off_canvas_current_name'] = is_string( $canvas_name ) ? $canvas_name : '';

		// Collect z-index data for CSS output and store canvas ID for wrapper class.
		$z_index                                       = $all_canvas_metadata[ $canvas_id ]['zIndex'] ?? null;
		$has_z_index                                  = null !== $z_index && '' !== $z_index && 'auto' !== $z_index;
		$GLOBALS['divi_off_canvas_current_has_z_index'] = $has_z_index;

		if ( $has_z_index ) {
			// Initialize global array if needed.
			if ( ! isset( $GLOBALS['divi_canvas_z_index_styles'] ) ) {
				$GLOBALS['divi_canvas_z_index_styles'] = [];
			}

			// Determine selector based on current post type context and canvas ID.
			$base_class = self::_get_base_layout_class_from_post_type();
			$selector   = '.' . $base_class . '--' . esc_attr( $canvas_id );

			// Store CSS rule for later output (deduplicated by selector).
			$GLOBALS['divi_canvas_z_index_styles'][ $selector ] = [
				'selector' => $selector,
				'z_index'  => $z_index,
			];
		}

		try {
			// Use Theme Builder's established pattern for rendering Divi content.
			// This ensures proper CSS generation and processing.
			$rendered_html = apply_filters( 'et_builder_render_layout', $unwrapped_content );
		} finally {
			// Always clear the flag, even if rendering throws an exception.
			unset( $GLOBALS['divi_off_canvas_rendering'] );
			// Clear canvas context after rendering.
			if ( isset( $GLOBALS['divi_off_canvas_current_id'] ) ) {
				unset( $GLOBALS['divi_off_canvas_current_id'] );
			}
			if ( isset( $GLOBALS['divi_off_canvas_current_name'] ) ) {
				unset( $GLOBALS['divi_off_canvas_current_name'] );
			}
			if ( isset( $GLOBALS['divi_off_canvas_current_has_z_index'] ) ) {
				unset( $GLOBALS['divi_off_canvas_current_has_z_index'] );
			}
		}

		// Set up styles manager for this canvas content (following Theme Builder pattern).
		$result         = StaticCSS::setup_styles_manager( $post_id );
		$styles_manager = $result['manager'];
		if ( isset( $result['deferred'] ) ) {
			$deferred_styles_manager = $result['deferred'];
		}

		// Output styles if needed (following Theme Builder pattern).
		if ( StaticCSS::$forced_inline_styles || ! $styles_manager->has_file() || $styles_manager->forced_inline ) {
			$custom = Page::custom_css( $post_id );

			// Pass styles to the page resource.
			StaticCSS::style_output(
				[
					'styles_manager'          => $styles_manager,
					'deferred_styles_manager' => $deferred_styles_manager ?? null,
					'custom'                  => $custom,
					'element_id'              => $post_id,
				]
			);
		}

		// Store the rendered content for later injection, keyed by post_id.
		// This ensures canvases are only injected into the template/post that targeted them.
		$rendered_array               = self::_get_per_post_global_value( 'divi_off_canvas_rendered', $post_id, [] );
		$rendered_array[ $canvas_id ] = $rendered_html;
		self::_set_per_post_global_value( 'divi_off_canvas_rendered', $post_id, $rendered_array );
	}

	/**
	 * Process canvases that should be appended to main content.
	 *
	 * @since ??
	 *
	 * @param int    $post_id Post ID.
	 * @param string $position Position to append ('above' or 'below').
	 */
	private static function _process_appended_canvases( $post_id, $position ) {
		// Get cached canvas data (no parsing needed for rendering).
		$canvas_data         = DynamicAssetsUtils::get_all_canvas_data_for_post( $post_id );
		$all_canvas_metadata = $canvas_data['all_canvas_metadata'] ?? [];

		// Initialize global canvas tracking if needed.
		if ( ! isset( $GLOBALS['divi_off_canvas_global_rendered'] ) ) {
			$GLOBALS['divi_off_canvas_global_rendered'] = [];
		}
		if ( ! isset( $GLOBALS['divi_off_canvas_local_rendered'] ) || ! is_array( $GLOBALS['divi_off_canvas_local_rendered'] ) ) {
			$GLOBALS['divi_off_canvas_local_rendered'] = [];
		}

		// Convert metadata to the format expected by the rest of the function.
		$all_canvases = [];
		foreach ( $all_canvas_metadata as $canvas_id => $canvas_meta ) {
			$all_canvases[ $canvas_id ] = [
				'id'                 => $canvas_id,
				'isMain'             => false,
				'isGlobal'           => $canvas_meta['isGlobal'] ?? false,
				'appendToMainCanvas' => $canvas_meta['appendToMainCanvas'] ?? null,
			];
		}

		// Find canvases that should be appended at this position.
		$canvases_to_process = [];
		foreach ( $all_canvases as $canvas_id => $canvas_meta ) {
			// Skip main canvas.
			if ( $canvas_meta['isMain'] ?? false ) {
				continue;
			}

			// Check if this canvas should be appended at this position.
			$append_to_main = $canvas_meta['appendToMainCanvas'] ?? null;
			if ( $append_to_main !== $position ) {
				continue;
			}

			$is_global = $canvas_meta['isGlobal'] ?? false;

			// For global canvases, check if they should be rendered.
			// Global canvases should only be rendered once, with priority:
			// post content > body template > header > footer.
			if ( $is_global ) {
				$rendering_context = self::_get_rendering_context();

				// If we're rendering in a template context, check if post content will render.
				// If post content will render, skip rendering in templates (post content has higher priority).
				if ( in_array( $rendering_context, [ 'header_template', 'footer_template', 'body_template' ], true ) ) {
					if ( self::_will_post_content_render() ) {
						continue;
					}
				}

				// Check if this global canvas has already been rendered anywhere on this page (#50537).
				if ( self::_is_global_canvas_already_rendered_for_page( $canvas_id ) ) {
					continue;
				}

				// Store rendering context for marking after successful render.
				$canvases_to_process[] = [
					'canvas_id'         => $canvas_id,
					'is_global'         => $is_global,
					'rendering_context' => $rendering_context,
				];
			} else {
				$canvas_meta_data = $all_canvas_metadata[ $canvas_id ] ?? null;
				if ( ! $canvas_meta_data || ! self::_local_canvas_belongs_to_post( $canvas_meta_data, $post_id ) ) {
					continue;
				}

				// Local canvases can be reachable from multiple owner contexts (post + active templates)
				// in the same request. Render each local canvas UID once to prevent duplicate output.
				$local_canvas_key = self::_normalize_slot_prefixed_canvas_id( (string) $canvas_id );
				if ( '' === $local_canvas_key ) {
					$local_canvas_key = (string) $canvas_id;
				}

				if ( isset( $GLOBALS['divi_off_canvas_local_rendered'][ $local_canvas_key ] ) ) {
					continue;
				}

				$canvases_to_process[] = [
					'canvas_id'        => $canvas_id,
					'is_global'        => $is_global,
					'local_canvas_key' => $local_canvas_key,
				];
			}
		}

		if ( empty( $canvases_to_process ) ) {
			return;
		}

		// Set flag BEFORE processing canvases so it's available when PHASE_NEW_STORE_INSTANCE
		// runs during main content rendering (after canvas rendering completes).
		// Track which layout type had the appended canvas so we don't skip resets for other layouts.
		$current_layout_type = BlockParserStore::get_layout_type();
		OrderIndexResetManager::set_appended_canvas_processed( $current_layout_type );

		// Process each canvas through Divi's rendering pipeline.
		foreach ( $canvases_to_process as $canvas_info ) {
			$canvas_id = $canvas_info['canvas_id'];
			$is_global = $canvas_info['is_global'];

			self::_render_off_canvas_content_with_css( $canvas_id, $post_id );

			// Mark global canvas as rendered after successful render.
			if ( $is_global && isset( $canvas_info['rendering_context'] ) ) {
				$canonical_canvas_id = self::_get_canonical_global_canvas_id( $canvas_id );
				$GLOBALS['divi_off_canvas_global_rendered'][ $canonical_canvas_id ] = $canvas_info['rendering_context'];
			} elseif ( ! $is_global ) {
				$local_canvas_key = $canvas_info['local_canvas_key'] ?? (string) $canvas_id;
				$GLOBALS['divi_off_canvas_local_rendered'][ $local_canvas_key ] = true;
			}
		}
	}

	/**
	 * Get the current rendering context.
	 * Cached per request to avoid redundant calls, but should be cleared when rendering context changes.
	 *
	 * @since ??
	 *
	 * @return string Rendering context: 'post_content', 'body_template', 'header_template', 'footer_template'.
	 */
	private static function _get_rendering_context() {
		// Native post/page body via the_content wins over cached TB template context from prior passes.
		if ( doing_filter( 'the_content' ) ) {
			$the_id = self::_get_the_content_post_id();
			if ( self::_is_native_content_post_id( $the_id ) ) {
				self::$_rendering_context_cache = 'post_content';
				return self::$_rendering_context_cache;
			}
		}

		// Return cached value if available.
		if ( null !== self::$_rendering_context_cache ) {
			return self::$_rendering_context_cache;
		}

		// Check if we're rendering Theme Builder template content.
		$current_post = null;
		if ( class_exists( '\ET_Post_Stack' ) ) {
			$current_post = \ET_Post_Stack::get();
		}

		// Also check global $post as fallback.
		if ( ! $current_post ) {
			global $post;
			$current_post = $post;
		}

		// Check for Theme Builder template post types.
		if ( $current_post && isset( $current_post->post_type ) ) {
			$post_type = $current_post->post_type;
			if ( 'et_body_layout' === $post_type ) {
				self::$_rendering_context_cache = 'body_template';
				return self::$_rendering_context_cache;
			}
			if ( 'et_header_layout' === $post_type ) {
				self::$_rendering_context_cache = 'header_template';
				return self::$_rendering_context_cache;
			}
			if ( 'et_footer_layout' === $post_type ) {
				self::$_rendering_context_cache = 'footer_template';
				return self::$_rendering_context_cache;
			}
		}

		// Default to post content context (the_content filter or regular posts).
		self::$_rendering_context_cache = 'post_content';
		return self::$_rendering_context_cache;
	}

	/**
	 * Check if post content will be rendered on this page.
	 * This helps determine if we should skip rendering global canvases in templates.
	 *
	 * @since ??
	 *
	 * @return bool True if post content will be rendered.
	 */
	private static function _will_post_content_render() {
		// Must be a singular page/post (not archive, search, etc.).
		if ( ! is_singular() ) {
			return false;
		}

		// Get the main post from the post stack (this works even when rendering templates).
		$main_post = null;
		if ( class_exists( '\ET_Post_Stack' ) ) {
			$main_post = \ET_Post_Stack::get_main_post();
		}

		// Fallback to global $wp_query.
		if ( ! $main_post ) {
			global $wp_query;
			$main_post = $wp_query->post ?? null;
		}

		// Check if we have a main post with content.
		return $main_post && isset( $main_post->post_content ) && ! empty( trim( $main_post->post_content ) );
	}

	/**
	 * Inject canvas content for interactions and appended canvases on the frontend.
	 * This injects pre-processed off-canvas content that was rendered during block processing.
	 *
	 * @since ??
	 *
	 * @param string $content The main post content.
	 *
	 * @return string The content with injected canvas content if needed.
	 */
	public static function inject_canvas_content_for_interactions( $content ) {
		// Only run on frontend (not in admin or visual builder).
		if ( Conditions::is_admin_request() || Conditions::is_vb_enabled() ) {
			return $content;
		}

		// Prevent infinite recursion when rendering canvas content.
		if ( ! empty( $GLOBALS['divi_off_canvas_rendering'] ) ) {
			return $content;
		}

		$current_post_id = self::_get_current_post_id();
		if ( ! $current_post_id ) {
			return $content;
		}

		// Skip canvas injection during the_content only for Post Content inside TB body layouts.
		if ( self::_should_skip_off_canvas_during_the_content( $current_post_id ) ) {
			return $content;
		}

		// Skip injecting appended canvases when rendering inner content (e.g., Canvas Portal modules).
		// Append/prepend should only apply to the main canvas, not to Canvas Portal content.
		// However, we still need to inject interaction-targeted canvases even when rendering inner content,
		// as those are needed for interactions to work within Canvas Portal content.
		$is_rendering_inner_content = BlockParserStore::is_rendering_inner_content();

		// In Theme Builder layouts, always use the layout post ID for canvas injection.
		// This ensures interactions defined in layouts inject the correct canvas content.
		$canvas_post_id = self::_get_canvas_processing_post_id( $current_post_id );

		// Only inject canvases that were rendered for the canvas post (layout post in TB context).
		// This ensures canvases are injected using the correct post ID where they're stored.
		$rendered_canvases = self::_get_per_post_global_value( 'divi_off_canvas_rendered', $canvas_post_id, [] );
		$hide_on_load_css  = self::_generate_hide_on_load_css();

		if ( empty( $rendered_canvases ) ) {
			return $hide_on_load_css . $content;
		}

		// Get all canvas data (this will cache if not already cached).
		$canvas_data              = DynamicAssetsUtils::get_all_canvas_data_for_post( $canvas_post_id );
		$canvas_portal_canvas_ids = $canvas_data['canvas_portal_ids'] ?? [];
		$all_canvas_metadata      = $canvas_data['all_canvas_metadata'] ?? [];

		// Convert metadata to the format expected by the rest of the function.
		$all_canvases = [];
		foreach ( $all_canvas_metadata as $canvas_id => $canvas_meta ) {
			$all_canvases[ $canvas_id ] = [
				'id'                 => $canvas_id,
				'isMain'             => false,
				'isGlobal'           => $canvas_meta['isGlobal'] ?? false,
				'appendToMainCanvas' => $canvas_meta['appendToMainCanvas'] ?? null,
			];
		}

		// Separate canvases by position.
		$above_content       = '';
		$below_content       = '';
		$interaction_content = '';

		foreach ( $rendered_canvases as $canvas_id => $rendered_html ) {
			$canvas_meta = $all_canvases[ $canvas_id ] ?? null;
			$is_global   = $canvas_meta['isGlobal'] ?? ( null === $canvas_meta );

			if ( ! $canvas_meta ) {
				// If canvas metadata not found, treat as interaction-targeted (legacy behavior).
				// Skip if already included via Canvas Portal.
				if ( ! in_array( $canvas_id, $canvas_portal_canvas_ids, true ) ) {
					if ( self::_register_canvas_page_injection( $canvas_id, $is_global, $canvas_post_id ) ) {
						$interaction_content .= $rendered_html;
					}
				}
				continue;
			}

			$append_to_main = $canvas_meta['appendToMainCanvas'] ?? null;

			// When rendering inner content (e.g., Canvas Portal), skip appended canvases.
			// Only inject interaction-targeted canvases, as those are needed for interactions.
			if ( $is_rendering_inner_content ) {
				// Skip appended canvases (above/below) when rendering inner content.
				if ( 'above' === $append_to_main || 'below' === $append_to_main ) {
					continue;
				}
				// Still inject interaction-targeted canvases.
				if ( ! in_array( $canvas_id, $canvas_portal_canvas_ids, true ) ) {
					if ( self::_register_canvas_page_injection( $canvas_id, $is_global, $canvas_post_id, $append_to_main ) ) {
						$interaction_content .= $rendered_html;
					}
				}
				continue;
			}

			// Normal rendering: handle all canvas types.
			if ( 'above' === $append_to_main ) {
				if ( self::_register_canvas_page_injection( $canvas_id, $is_global, $canvas_post_id, $append_to_main ) ) {
					$above_content .= $rendered_html;
				}
			} elseif ( 'below' === $append_to_main ) {
				if ( self::_register_canvas_page_injection( $canvas_id, $is_global, $canvas_post_id, $append_to_main ) ) {
					$below_content .= $rendered_html;
				}
			} elseif ( ! in_array( $canvas_id, $canvas_portal_canvas_ids, true ) ) {
				// Interaction-targeted canvas (no appendToMainCanvas setting).
				// Skip if already included via Canvas Portal.
				if ( self::_register_canvas_page_injection( $canvas_id, $is_global, $canvas_post_id, $append_to_main ) ) {
					$interaction_content .= $rendered_html;
				}
			}
		}
		// Build final content: CSS + above + main + below + interactions.
		// When rendering inner content, above_content and below_content will be empty.
		$final_content = $hide_on_load_css . $above_content . $content . $below_content . $interaction_content;

		return $final_content;
	}

	/**
	 * Generate CSS rules to hide elements that will be hidden by "On Load" → "Remove Visibility" interactions.
	 *
	 * This prevents canvas content from flashing before JavaScript executes and hides the elements.
	 * CSS uses `display: none !important` to match JavaScript's `hideElement()` behavior.
	 *
	 * Collects target IDs from all posts that have hide-on-load interactions to ensure complete coverage.
	 * CSS is only output once per page to prevent duplicates.
	 *
	 * @since ??
	 *
	 * @return string CSS rules wrapped in `<style>` tag, or empty string if no targets or already output.
	 */
	private static function _generate_hide_on_load_css() {
		// Ensure CSS is only output once per page to prevent duplicates.
		if ( ! empty( $GLOBALS['divi_off_canvas_hide_on_load_css_output'] ) ) {
			return '';
		}

		// Collect hide-on-load target IDs from all posts.
		// Use the same global storage pattern as other per-post values.
		$all_hide_on_load_target_ids = [];
		$hide_on_load_sources        = [
			'divi_hide_on_load_target_ids',
			'divi_off_canvas_hide_on_load_target_ids',
		];

		foreach ( $hide_on_load_sources as $source_key ) {
			if ( empty( $GLOBALS[ $source_key ] ) || ! is_array( $GLOBALS[ $source_key ] ) ) {
				continue;
			}

			foreach ( $GLOBALS[ $source_key ] as $post_target_ids ) {
				if ( is_array( $post_target_ids ) ) {
					$all_hide_on_load_target_ids = array_merge( $all_hide_on_load_target_ids, $post_target_ids );
				}
			}
		}

		// Remove duplicates.
		$all_hide_on_load_target_ids = array_unique( $all_hide_on_load_target_ids );

		if ( empty( $all_hide_on_load_target_ids ) ) {
			return '';
		}

		// Generate CSS rules for each target ID.
		// Use [data-interaction-target="{id}] selector to match elements that JavaScript will target.
		// Use display: none !important to match JavaScript's hideElement() behavior.
		$css_rules = [];
		foreach ( $all_hide_on_load_target_ids as $target_id ) {
			$css_rules[] = sprintf(
				'[data-interaction-target="%s"] { display: none !important; }',
				esc_attr( $target_id )
			);
		}

		if ( empty( $css_rules ) ) {
			return '';
		}

		// Mark CSS as output to prevent duplicates.
		$GLOBALS['divi_off_canvas_hide_on_load_css_output'] = true;

		// Output CSS in a style tag before canvas content.
		// This ensures elements are hidden before HTML is rendered, preventing flash.
		return '<style id="divi-off-canvas-hide-on-load">' . "\n" . implode( "\n", $css_rules ) . "\n" . '</style>' . "\n";
	}





	/**
	 * Check if canvas block content contains a module with the specified interaction target.
	 *
	 * @since ??
	 *
	 * @param string $canvas_content Canvas content in Gutenberg block format.
	 * @param string $target_id Target ID to search for.
	 *
	 * @return bool True if canvas contains the target module.
	 */
	public static function canvas_block_content_contains_target( $canvas_content, $target_id ) {
		// Optimize: Try fast checks first before expensive parsing.
		// Method 1: Quick string search for target ID - fastest check.
		// Use strict comparison for better performance.
		if ( ! str_contains( $canvas_content, $target_id ) ) {
			// Target ID not found at all, skip expensive parsing.
			return false;
		}

		// Method 2: Check for interactionTarget attribute (target element).
		// Target elements have: module.decoration.interactionTarget = "{ID}".
		// Optimized: Use faster string search before regex when possible.
		// Check for the attribute name first to avoid regex if not present.
		if ( ! str_contains( $canvas_content, 'interactionTarget' ) ) {
			return false;
		}

		// Only use regex if we found both the target ID and the attribute name.
		// Regex test: https://regex101.com/r/kPQf15/1.
		$pattern = '/"interactionTarget"\s*:\s*"' . preg_quote( $target_id, '/' ) . '"/';
		return 1 === preg_match( $pattern, $canvas_content );
	}

	/**
	 * Recursively search parsed blocks for a target ID.
	 *
	 * @since ??
	 *
	 * @param array  $blocks Parsed WordPress blocks.
	 * @param string $target_id Target ID to search for.
	 *
	 * @return bool True if target found.
	 */
	private static function _search_blocks_for_target( $blocks, $target_id ) {
		foreach ( $blocks as $block ) {
			// Search in block attributes (convert to JSON string for easy searching).
			if ( ! empty( $block['attrs'] ) ) {
				$attrs_json = wp_json_encode( $block['attrs'] );
				if ( str_contains( $attrs_json, $target_id ) ) {
					return true;
				}
			}

			// Search in inner blocks recursively.
			if ( ! empty( $block['innerBlocks'] ) ) {
				if ( self::_search_blocks_for_target( $block['innerBlocks'], $target_id ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get canvas content for canvas portal canvas IDs.
	 * Returns content from all canvases (local and global) referenced by canvas portal blocks.
	 *
	 * @since ??
	 *
	 * @param array $canvas_ids Array of canvas IDs from canvas portal blocks.
	 * @param int   $post_id Post ID to get local canvases from.
	 *
	 * @return string Combined canvas content from all matching canvases.
	 */
	public static function get_canvas_content_for_canvas_portals( $canvas_ids, $post_id ) {
		if ( empty( $canvas_ids ) || ! $post_id ) {
			return '';
		}

		// Remove duplicates to avoid processing same canvas multiple times.
		$canvas_ids = array_unique( $canvas_ids );

		// Get cached canvas data.
		$canvas_data         = DynamicAssetsUtils::get_all_canvas_data_for_post( $post_id );
		$all_canvas_metadata = $canvas_data['all_canvas_metadata'] ?? [];

		$canvas_contents        = [];
		$processed_canvas_ids   = [];
		$all_canvas_content_map = [];

		// Process each canvas ID using cached metadata.
		foreach ( $canvas_ids as $canvas_id ) {
			// Skip already processed canvases.
			if ( in_array( $canvas_id, $processed_canvas_ids, true ) ) {
				continue;
			}

			$canvas_meta = $all_canvas_metadata[ $canvas_id ] ?? null;
			if ( ! $canvas_meta ) {
				continue;
			}

			$canvas_content = $canvas_meta['content'] ?? null;
			if ( ! $canvas_content ) {
				continue;
			}

			// Store raw content in map for cache pre-population.
			$all_canvas_content_map[ $canvas_id ] = $canvas_content;

			// Unwrap placeholder block if needed.
			$unwrapped_content = ModuleUtils::maybe_unwrap_placeholder_block( $canvas_content );
			if ( $unwrapped_content ) {
				$canvas_contents[]      = $unwrapped_content;
				$processed_canvas_ids[] = $canvas_id;
			}
		}

		// Pre-populate CanvasPortalModule cache with batch-fetched content.
		// This allows render_callback() to reuse cached content instead of fetching again.
		if ( ! empty( $all_canvas_content_map ) ) {
			CanvasPortalModule::pre_populate_canvas_content_cache( $all_canvas_content_map, $post_id );
		}

		// Combine all canvas contents.
		return implode( '', $canvas_contents );
	}

	/**
	 * Extract interaction target IDs from post content blocks.
	 * This is used by DynamicAssets to find which canvases need to be processed.
	 *
	 * @since ??
	 *
	 * @param string $content Post content in Gutenberg block format.
	 *
	 * @return array Array of unique target IDs found in interactions.
	 */
	public static function extract_interaction_target_ids_from_content( $content ) {
		$target_ids = [];

		if ( ! function_exists( 'parse_blocks' ) ) {
			return $target_ids;
		}

		// Early exit: Use DynamicAssets detection to check if content has interactions before expensive parsing.
		if ( ! DetectFeature::has_interactions_enabled( $content, [ 'has_block' => true ] ) ) {
			return $target_ids;
		}

		$blocks = parse_blocks( $content );
		if ( empty( $blocks ) ) {
			return $target_ids;
		}

		// Recursively search blocks for interactions.
		self::_extract_target_ids_from_blocks( $blocks, $target_ids );

		$unique_target_ids = array_unique( $target_ids );

		return $unique_target_ids;
	}

	/**
	 * Recursively extract target IDs from blocks.
	 *
	 * @since ??
	 *
	 * @param array $blocks Parsed WordPress blocks.
	 * @param array $target_ids Array to populate with target IDs (passed by reference).
	 */
	private static function _extract_target_ids_from_blocks( $blocks, &$target_ids ) {
		foreach ( $blocks as $block ) {
			// Check for interactions in block attributes.
			if ( ! empty( $block['attrs'] ) ) {
				$block_attrs       = $block['attrs'];
				$interactions_data = $block_attrs['module']['decoration']['interactions'] ?? null;

				if ( $interactions_data ) {
					$interactions = $interactions_data['desktop']['value']['interactions'] ?? [];

					if ( is_array( $interactions ) && ! empty( $interactions ) ) {
						foreach ( $interactions as $interaction ) {
							$target_class = $interaction['target']['targetClass'] ?? '';

							if ( $target_class && preg_match( '/et-interaction-target-([a-zA-Z0-9-]+)/', $target_class, $matches ) ) {
								$target_ids[] = $matches[1];
							}
						}
					}
				}
			}

			// Search in inner blocks recursively.
			if ( ! empty( $block['innerBlocks'] ) ) {
				self::_extract_target_ids_from_blocks( $block['innerBlocks'], $target_ids );
			}
		}
	}

	/**
	 * Get base layout class from current post type.
	 *
	 * Returns the base layout class (e.g., 'et-l--post', 'et-l--header', 'et-l--footer')
	 * based on the current post type, using the same logic as et_builder_get_layout_opening_wrapper().
	 *
	 * @since ??
	 *
	 * @param string $post_type Optional. Post type. Defaults to current post type.
	 *
	 * @return string Base layout class.
	 */
	private static function _get_base_layout_class_from_post_type( $post_type = '' ) {
		if ( empty( $post_type ) ) {
			$post_type = get_post_type();
		}

		// Use same logic as et_builder_get_layout_opening_wrapper().
		switch ( $post_type ) {
			case ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE:
				return 'et-l--header';

			case ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE:
				return 'et-l--body';

			case ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE:
				return 'et-l--footer';

			default:
				return 'et-l--post';
		}
	}

	/**
	 * Add canvas ID class to wrapper when rendering canvas content with z-index.
	 *
	 * This filter adds a canvas-specific class to the `.et-l` wrapper
	 * (e.g., `.et-l--post--{canvas_id}`) so the CSS selector can target it.
	 *
	 * @since ??
	 *
	 * @param array $layout_class Array of layout classes.
	 *
	 * @return array Modified array of layout classes.
	 */
	public static function add_canvas_id_to_wrapper_class( $layout_class ) {
		// Only add canvas class when rendering canvas content with z-index.
		if ( ! isset( $GLOBALS['divi_off_canvas_rendering'] ) || ! isset( $GLOBALS['divi_off_canvas_current_id'] ) ) {
			return $layout_class;
		}

		$has_z_index = $GLOBALS['divi_off_canvas_current_has_z_index'] ?? false;
		if ( true !== $has_z_index ) {
			return $layout_class;
		}

		$canvas_id = $GLOBALS['divi_off_canvas_current_id'];
		if ( ! $canvas_id ) {
			return $layout_class;
		}

		// Get base class using the same logic as et_builder_get_layout_opening_wrapper().
		$base_class = self::_get_base_layout_class_from_post_type();

		// Add canvas ID class: et-l--post--{canvas_id} or et-l--header--{canvas_id}, etc.
		$canvas_class   = $base_class . '--' . esc_attr( $canvas_id );
		$layout_class[] = $canvas_class;

		return $layout_class;
	}

	/**
	 * Inject data-canvas attribute into the layout opening wrapper when rendering Off Canvas content.
	 *
	 * Hooked onto `et_builder_layout_extra_attrs` so that D4 core.php stays decoupled from the
	 * D5 Off Canvas rendering system. Only fires when divi_off_canvas_rendering is set, and only
	 * when a non-empty canvas name is available.
	 *
	 * @since ??
	 *
	 * @param string $attrs Existing extra attribute string (may be empty).
	 *
	 * @return string Attribute string, with data-canvas appended when applicable.
	 */
	public static function add_canvas_name_to_layout_attrs( $attrs ) {
		if ( empty( $GLOBALS['divi_off_canvas_rendering'] ) ) {
			return $attrs;
		}

		$canvas_name = $GLOBALS['divi_off_canvas_current_name'] ?? '';

		if ( ! is_string( $canvas_name ) || '' === $canvas_name ) {
			return $attrs;
		}

		return $attrs . sprintf( 'data-canvas="%s" ', esc_attr( $canvas_name ) );
	}

	/**
	 * Get canvas content for interaction target IDs.
	 * Returns content from all canvases (local and global) that contain the target modules.
	 *
	 * @since ??
	 *
	 * @param array $target_ids Array of interaction target IDs.
	 * @param int   $post_id Post ID to get local canvases from.
	 *
	 * @return string Combined canvas content from all matching canvases.
	 */
	public static function get_canvas_content_for_targets( $target_ids, $post_id ) {
		if ( empty( $target_ids ) || ! $post_id ) {
			return '';
		}

		// Get cached canvas data.
		$canvas_data              = DynamicAssetsUtils::get_all_canvas_data_for_post( $post_id );
		$all_canvas_metadata      = $canvas_data['all_canvas_metadata'] ?? [];
		$interaction_targets      = $canvas_data['interaction_targets'] ?? [];
		$canvas_portal_canvas_ids = $canvas_data['canvas_portal_ids'] ?? [];

		// Collect canvas IDs for all targets from cache.
		$canvas_ids_to_process = [];
		foreach ( $target_ids as $target_id ) {
			$canvas_ids_for_target = $interaction_targets[ $target_id ] ?? [];
			$canvas_ids_to_process = array_merge( $canvas_ids_to_process, $canvas_ids_for_target );
		}

		// Remove duplicates.
		$canvas_ids_to_process = array_unique( $canvas_ids_to_process );

		// Filter out canvases that are already included via Canvas Portal.
		$canvas_ids_to_process = array_filter(
			$canvas_ids_to_process,
			function ( $canvas_id ) use ( $canvas_portal_canvas_ids ) {
				return ! in_array( $canvas_id, $canvas_portal_canvas_ids, true );
			}
		);

		if ( empty( $canvas_ids_to_process ) ) {
			return '';
		}

		$canvas_contents = [];

		// Process canvases using cached metadata.
		foreach ( $canvas_ids_to_process as $canvas_id ) {
			$canvas_meta = $all_canvas_metadata[ $canvas_id ] ?? null;
			if ( ! $canvas_meta ) {
				continue;
			}

			$canvas_content = $canvas_meta['content'] ?? '';
			if ( ! $canvas_content ) {
				continue;
			}

			// Unwrap placeholder block if needed.
			$unwrapped_content = ModuleUtils::maybe_unwrap_placeholder_block( $canvas_content );
			if ( $unwrapped_content ) {
				$canvas_contents[ $canvas_id ] = $unwrapped_content;
			}
		}

		// Combine all canvas contents.
		return implode( '', $canvas_contents );
	}

	/**
	 * Get canvas content for canvases that are appended to main canvas (above or below).
	 * Returns content from all canvases (local and global) with appendToMainCanvas set.
	 *
	 * @since ??
	 *
	 * @param int $post_id Post ID to get local canvases from.
	 *
	 * @return string Combined canvas content from all appended canvases.
	 */
	public static function get_canvas_content_for_appended( $post_id ) {
		if ( ! $post_id ) {
			return '';
		}

		// Get cached canvas data.
		$canvas_data         = DynamicAssetsUtils::get_all_canvas_data_for_post( $post_id );
		$all_canvas_metadata = $canvas_data['all_canvas_metadata'] ?? [];
		$appended_above      = $canvas_data['appended_above'] ?? [];
		$appended_below      = $canvas_data['appended_below'] ?? [];

		// Combine above and below canvas IDs.
		$appended_canvas_ids = array_merge( $appended_above, $appended_below );

		if ( empty( $appended_canvas_ids ) ) {
			return '';
		}

		$canvas_contents = [];

		// Process appended canvases using cached metadata.
		foreach ( $appended_canvas_ids as $canvas_id ) {
			$canvas_meta = $all_canvas_metadata[ $canvas_id ] ?? null;
			if ( ! $canvas_meta ) {
				continue;
			}

			$canvas_content = $canvas_meta['content'] ?? '';
			if ( ! $canvas_content ) {
				continue;
			}

			// Unwrap placeholder block if needed.
			$unwrapped_content = ModuleUtils::maybe_unwrap_placeholder_block( $canvas_content );
			if ( $unwrapped_content ) {
				$canvas_contents[] = $unwrapped_content;
			}
		}

		// Combine all canvas contents.
		return implode( '', $canvas_contents );
	}

	/**
	 * Get all appended canvas content (both interaction-targeted and explicitly appended).
	 * This is used to extract global color IDs from canvases that will be rendered on the front end.
	 *
	 * @since ??
	 *
	 * @param int    $post_id Post ID to get local canvases from.
	 * @param string $main_content Main post content to extract interaction target IDs from.
	 *
	 * @return string Combined canvas content from all appended canvases.
	 */
	public static function get_all_appended_canvas_content( $post_id, $main_content = '' ) {
		static $cache = [];

		$cache_key = md5( (string) $post_id . '|' . (string) $main_content );
		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		if ( ! $post_id ) {
			$cache[ $cache_key ] = '';
			return $cache[ $cache_key ];
		}

		// Skip expensive canvas content fetching when not in a cacheable frontend request.
		// The builder doesn't need this for dynamic assets detection, and it causes
		// performance issues during builder load. Canvas content will be handled
		// on the client side in the builder.
		if ( ! DynamicAssetsUtils::is_dynamic_front_end_request() ) {
			$cache[ $cache_key ] = '';
			return $cache[ $cache_key ];
		}

		// Early exit: Check if any canvases exist before doing expensive operations.
		// This avoids database queries and content parsing when no canvases exist.
		if ( ! self::_has_any_canvases( $post_id ) ) {
			$cache[ $cache_key ] = '';
			return $cache[ $cache_key ];
		}

		$all_canvas_content = '';

		// Get explicitly appended canvases (above/below).
		$appended_content = self::get_canvas_content_for_appended( $post_id );
		if ( ! empty( $appended_content ) ) {
			$all_canvas_content .= $appended_content;
		}

		// Get all canvas data (this will cache if not already cached).
		$canvas_data = DynamicAssetsUtils::get_all_canvas_data_for_post( $post_id, $main_content );

		// Get interaction-targeted canvases.
		if ( ! empty( $main_content ) ) {
			$target_ids = self::extract_interaction_target_ids_from_content( $main_content );

			if ( ! empty( $target_ids ) ) {
				// Filter out targets that are on the main canvas.
				// When an interaction on the main canvas targets an element on the same canvas,
				// the target already exists in the rendered HTML, so we don't need to process any canvas content.
				// This prevents orderIndex from being incremented incorrectly during CSS generation.
				$filtered_target_ids = [];
				foreach ( $target_ids as $target_id ) {
					if ( ! self::canvas_block_content_contains_target( $main_content, $target_id ) ) {
						$filtered_target_ids[] = $target_id;
					}
				}

				if ( ! empty( $filtered_target_ids ) ) {
					$interaction_content = self::get_canvas_content_for_targets( $filtered_target_ids, $post_id );
					if ( ! empty( $interaction_content ) ) {
						$all_canvas_content .= $interaction_content;
					}
				}
			}

			/*
			 * Expand canvas portal module canvases,
			 * including portals found inside appended/interaction canvas content,
			 * so Dynamic Assets early detection includes tokens (e.g. `gcid-*`) from portal-rendered modules.
			 */
			$canvas_portal_ids_from_main     = $canvas_data['canvas_portal_ids'] ?? [];
			$canvas_portal_ids_from_appended = [];

			// Only parse appended/interaction content for portals when it actually contains portal markers.
			if ( is_string( $all_canvas_content ) && str_contains( $all_canvas_content, 'canvas-portal' ) ) {
				$canvas_portal_ids_from_appended = DynamicAssetsUtils::extract_canvas_portal_canvas_ids_from_content( $all_canvas_content );
			}

			$canvas_portal_ids_to_expand = array_unique( array_merge( $canvas_portal_ids_from_main, $canvas_portal_ids_from_appended ) );

			// Expand portal-referenced canvases recursively, with hard limits and deduplication for safety.
			$portal_expansion_iteration_limit = 10;
			$portal_expansion_iteration       = 0;
			$expanded_portal_canvas_ids       = [];

			while ( $portal_expansion_iteration < $portal_expansion_iteration_limit ) {
				$portal_ids_to_process = array_values( array_diff( $canvas_portal_ids_to_expand, $expanded_portal_canvas_ids ) );
				if ( empty( $portal_ids_to_process ) ) {
					break;
				}

				++$portal_expansion_iteration;
				$expanded_portal_canvas_ids = array_merge( $expanded_portal_canvas_ids, $portal_ids_to_process );

				$canvas_portal_content = self::get_canvas_content_for_canvas_portals( $portal_ids_to_process, $post_id );
				if ( empty( $canvas_portal_content ) ) {
					continue;
				}

				$all_canvas_content .= $canvas_portal_content;

				// If the newly included portal content contains nested portals, queue those for expansion.
				if ( str_contains( $canvas_portal_content, 'canvas-portal' ) ) {
					$nested_portal_ids = DynamicAssetsUtils::extract_canvas_portal_canvas_ids_from_content( $canvas_portal_content );
					if ( ! empty( $nested_portal_ids ) ) {
						$canvas_portal_ids_to_expand = array_unique( array_merge( $canvas_portal_ids_to_expand, $nested_portal_ids ) );
					}
				}
			}
		}

		$cache[ $cache_key ] = $all_canvas_content;

		return $cache[ $cache_key ];
	}

	/**
	 * Get all appended canvas content for the current post and active Theme Builder templates.
	 *
	 * @since ??
	 *
	 * @param int    $post_id      Current post ID.
	 * @param string $main_content Main content used to resolve interaction targets.
	 *
	 * @return string Combined canvas content for post and active templates.
	 */
	public static function get_all_appended_canvas_content_for_post_and_templates( int $post_id, string $main_content = '' ): string {
		if ( ! $post_id ) {
			return '';
		}

		$all_canvas_content = self::get_all_appended_canvas_content( $post_id, $main_content );
		$tb_template_ids    = DynamicAssetsUtils::get_theme_builder_template_ids();

		if ( empty( $tb_template_ids ) || ! is_array( $tb_template_ids ) ) {
			return $all_canvas_content;
		}

		$processed_post_ids = [ $post_id => true ];

		foreach ( $tb_template_ids as $tb_template_id ) {
			$tb_template_id = (int) $tb_template_id;

			if ( 0 >= $tb_template_id || isset( $processed_post_ids[ $tb_template_id ] ) ) {
				continue;
			}

			$processed_post_ids[ $tb_template_id ] = true;
			$tb_canvas_content                     = self::get_all_appended_canvas_content( $tb_template_id, $main_content );

			if ( ! empty( $tb_canvas_content ) ) {
				$all_canvas_content .= $tb_canvas_content;
			}
		}

		return $all_canvas_content;
	}

	/**
	 * Convert flat module objects to WordPress block array.
	 * Based on the pattern from FlexboxMigration and GlobalColorMigration.
	 *
	 * @since ??
	 *
	 * @param array $flat_objects The flat module objects.
	 *
	 * @return array The block array structure.
	 */
	private static function _convert_module_data_to_blocks( $flat_objects ) {
		if ( ! is_array( $flat_objects ) ) {
			return [];
		}

		// Find the actual root object (should have no parent or parent=null).
		$root = null;
		foreach ( $flat_objects as $id => $object ) {
			// Look for object with no parent or null parent (this is the actual root).
			if ( ! isset( $object['parent'] ) || null === $object['parent'] || 'no-parent' === $object['parent'] ) {
				$root = $object;
				break;
			}
		}

		if ( ! $root ) {
			// Try to find root by ID.
			$root = $flat_objects['root'] ?? null;
		}

		if ( ! $root ) {
			return [];
		}

		$blocks = [];
		foreach ( $root['children'] ?? [] as $child_id ) {
			$block = self::_build_block_from_flat( $child_id, $flat_objects );
			if ( $block ) {
				$blocks[] = $block;
			}
		}

		return $blocks;
	}

	/**
	 * Recursively build a block from a flat object.
	 * Based on the pattern from FlexboxMigration and GlobalColorMigration.
	 *
	 * @since ??
	 *
	 * @param string $id The object ID.
	 * @param array  $flat_objects The flat module objects.
	 *
	 * @return array The block array.
	 */
	private static function _build_block_from_flat( $id, $flat_objects ) {
		if ( ! isset( $flat_objects[ $id ] ) ) {
			return null;
		}

		$object = $flat_objects[ $id ];
		$block  = [
			'blockName'    => $object['name'],
			'attrs'        => $object['props']['attrs'] ?? [],
			'innerBlocks'  => [],
			'innerContent' => [],
		];

		if ( ! empty( $object['children'] ) ) {
			foreach ( $object['children'] as $child_id ) {
				$child_block = self::_build_block_from_flat( $child_id, $flat_objects );
				if ( $child_block ) {
					$block['innerBlocks'][]  = $child_block;
					$block['innerContent'][] = null; // Placeholder, will be filled by serializer.
				}
			}
		}

		if ( isset( $object['props']['innerHTML'] ) ) {
			$block['innerContent'][] = $object['props']['innerHTML'];
		}

		return $block;
	}

	/**
	 * Get a value from a per-post global array.
	 * Returns the value if it exists, or the default value if not.
	 *
	 * @since ??
	 *
	 * @param string $global_key    Global variable key (e.g., 'divi_off_canvas_target_ids').
	 * @param int    $post_id       Post ID.
	 * @param mixed  $default_value Default value to return if not found. Default empty array.
	 *
	 * @return mixed The value from the array or the default value.
	 */
	private static function _get_per_post_global_value( $global_key, $post_id, $default_value = [] ) {
		if ( ! isset( $GLOBALS[ $global_key ] ) || ! isset( $GLOBALS[ $global_key ][ $post_id ] ) ) {
			return $default_value;
		}

		return $GLOBALS[ $global_key ][ $post_id ];
	}

	/**
	 * Set a value in a per-post global array.
	 * Initializes the global array structure if needed.
	 *
	 * @since ??
	 *
	 * @param string $global_key Global variable key (e.g., 'divi_off_canvas_target_ids').
	 * @param int    $post_id    Post ID.
	 * @param mixed  $value      Value to set.
	 *
	 * @return void
	 */
	private static function _set_per_post_global_value( $global_key, $post_id, $value ) {
		if ( ! isset( $GLOBALS[ $global_key ] ) ) {
			$GLOBALS[ $global_key ] = [];
		}

		$GLOBALS[ $global_key ][ $post_id ] = $value;
	}

	/**
	 * Resolve allowed post-backed canvas owners for this save operation.
	 *
	 * @since ??
	 *
	 * @param int  $post_id Main saved post ID.
	 * @param bool $can_edit_theme_builder_canvases Whether Theme Builder canvases are allowed.
	 *
	 * @return array
	 */
	private static function _get_allowed_canvas_parent_post_ids( int $post_id, bool $can_edit_theme_builder_canvases ): array {
		$allowed_post_ids = [ $post_id ];

		if ( ! $can_edit_theme_builder_canvases ) {
			return $allowed_post_ids;
		}

		$theme_builder_layouts = et_theme_builder_get_template_layouts();

		if ( empty( $theme_builder_layouts ) && 0 < $post_id ) {
			$tb_request = \ET_Theme_Builder_Request::from_post( $post_id );
			if ( $tb_request ) {
				$theme_builder_layouts = et_theme_builder_get_template_layouts( $tb_request );
			}
		}

		$layout_definitions = [
			ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE => 'et_header_layout',
			ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE   => 'et_body_layout',
			ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE => 'et_footer_layout',
		];

		foreach ( $layout_definitions as $layout_key => $expected_post_type ) {
			$layout_data = $theme_builder_layouts[ $layout_key ] ?? [];

			// Respect explicit enabled/override flags when present.
			// Some payloads may omit these keys; in that case, do not block by default.
			if ( array_key_exists( 'enabled', $layout_data ) && empty( $layout_data['enabled'] ) ) {
				continue;
			}

			if ( array_key_exists( 'override', $layout_data ) && empty( $layout_data['override'] ) ) {
				continue;
			}

			$layout_post_id = absint( $layout_data['id'] ?? 0 );
			if ( 0 === $layout_post_id ) {
				continue;
			}

			if ( get_post_type( $layout_post_id ) !== $expected_post_type ) {
				continue;
			}

			if ( ! current_user_can( 'edit_post', $layout_post_id ) ) {
				continue;
			}

			$allowed_post_ids[] = $layout_post_id;
		}

		return array_values( array_unique( array_map( 'absint', $allowed_post_ids ) ) );
	}

	/**
	 * Resolve Theme Builder layout post ID by slot name.
	 *
	 * @since ??
	 *
	 * @param int    $post_id Main saved post ID.
	 * @param string $layout_slot Theme Builder slot name (header/body/footer).
	 *
	 * @return int
	 */
	private static function _get_theme_builder_layout_post_id_by_slot( int $post_id, string $layout_slot ): int {
		$layout_slot = sanitize_key( $layout_slot );
		if ( ! in_array( $layout_slot, [ 'header', 'body', 'footer' ], true ) ) {
			return 0;
		}

		$theme_builder_layouts = et_theme_builder_get_template_layouts();
		if ( empty( $theme_builder_layouts ) && 0 < $post_id ) {
			$tb_request = \ET_Theme_Builder_Request::from_post( $post_id );
			if ( $tb_request ) {
				$theme_builder_layouts = et_theme_builder_get_template_layouts( $tb_request );
			}
		}

		$layout_key_by_slot = [
			'header' => ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE,
			'body'   => ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE,
			'footer' => ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE,
		];
		$layout_key         = $layout_key_by_slot[ $layout_slot ] ?? '';

		if ( '' === $layout_key ) {
			return 0;
		}

		return absint( $theme_builder_layouts[ $layout_key ]['id'] ?? 0 );
	}

	/**
	 * Check whether the current user can edit a context-backed canvas owner.
	 *
	 * Supported keys:
	 * - `term:{taxonomy}:{term_id}`.
	 * - `user:{user_id}`.
	 *
	 * @since ??
	 *
	 * @param string $context_key Parent context key.
	 *
	 * @return bool True when user can edit the target context.
	 */
	private static function _current_user_can_edit_canvas_context( string $context_key ): bool {
		$context_key = sanitize_text_field( $context_key );
		if ( '' === $context_key ) {
			return false;
		}

		$term_matches = [];
		if ( 1 === preg_match( '/^term:([a-z0-9_\\-]+):(\\d+)$/', $context_key, $term_matches ) ) {
			$term_id = absint( $term_matches[2] ?? 0 );
			if ( 0 < $term_id ) {
				return current_user_can( 'edit_term', $term_id );
			}
			return false;
		}

		$user_matches = [];
		if ( 1 === preg_match( '/^user:(\\d+)$/', $context_key, $user_matches ) ) {
			$user_id = absint( $user_matches[1] ?? 0 );
			if ( 0 < $user_id ) {
				return current_user_can( 'edit_user', $user_id );
			}
			return false;
		}

		/*
		 * Context keys without an explicit WP object owner.
		 *
		 * For these contexts we fall back to a general editing capability, because there is no
		 * term/user/post to run a granular capability check against.
		 *
		 * Examples:
		 * - `search`, `home`, `404`, `date`,
		 * - `post_type_archive` or `post_type_archive:{postType}`.
		 */
		$has_general_edit_cap = current_user_can( 'edit_posts' );

		if ( in_array( $context_key, [ 'search', 'home', '404', 'date', 'post_type_archive' ], true ) ) {
			return $has_general_edit_cap;
		}

		if ( 1 === preg_match( '/^post_type_archive:([a-z0-9_\\-]+)$/', $context_key ) ) {
			return $has_general_edit_cap;
		}

		return false;
	}

	/**
	 * Reset caches for testing or when rendering context changes.
	 *
	 * This method clears cached values that may become stale when the post context
	 * or rendering context changes (e.g., when moving from header to post content to footer).
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function reset_caches() {
		self::$_current_post_id_cache   = null;
		self::$_rendering_context_cache = null;
		self::$_main_post_cache         = [];
	}
}
