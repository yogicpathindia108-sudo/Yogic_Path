<?php
/**
 * Order Index Reset Manager for Divi 5 Builder.
 *
 * Centralizes orderIndex reset logic to handle complex rendering scenarios including
 * Theme Builder templates, nested/appended canvases, and Canvas Portal modules.
 *
 * INTERACTION DETECTION:
 * The system filters interactions at detection time (in OffCanvasHooks::detect_and_process_off_canvas_interactions).
 * Only interactions targeting elements on a DIFFERENT canvas will trigger canvas appending and affect
 * orderIndex reset logic. Same-canvas interactions (where both source and target are on the main canvas)
 * are filtered out immediately and never enter the off-canvas processing pipeline, ensuring they don't
 * unnecessarily affect OrderIndexResetManager's reset decisions.
 *
 * @package ET\Builder\FrontEnd\BlockParser
 * @since ??
 */

namespace ET\Builder\FrontEnd\BlockParser;

use ET\Builder\Framework\Utility\Conditions;

/**
 * OrderIndexResetManager class.
 *
 * Manages orderIndex resets across different rendering contexts to ensure
 * consistent module numbering while handling edge cases like Theme Builder
 * templates, canvas portals, and interaction detection.
 *
 * @since ??
 */
class OrderIndexResetManager {

	/**
	 * Reset context tracking.
	 *
	 * Tracks reset state per layout type and reset phase to prevent
	 * duplicate resets while allowing proper resets for each Theme Builder area.
	 *
	 * @since ??
	 * @var array
	 */
	private static $_reset_contexts = [];

	/**
	 * Appended canvas processing flag.
	 *
	 * Tracks which layout type had appended canvases processed.
	 * Set before canvas rendering begins so it's available during specific phases.
	 * Stores the layout type string, or false if no appended canvas was processed.
	 *
	 * @since ??
	 * @var string|false
	 */
	private static $_appended_canvas_processed_layout = false;

	/**
	 * Last layout type that had a reset at PHASE_BEFORE_RENDERING.
	 *
	 * Tracks the last layout type that was reset to detect when we move to a new area.
	 * This helps distinguish between multiple do_blocks() calls in the same area vs
	 * moving to a new area (which should reset even for 'default' layout type).
	 *
	 * @since ??
	 * @var string|null
	 */
	private static $_last_reset_layout_type = null;

	/**
	 * Previous layout type before the current reset.
	 *
	 * Tracks the layout type from before the current reset to detect area transitions
	 * in phases that run after PHASE_BEFORE_RENDERING (like PHASE_BEFORE_DO_BLOCKS).
	 *
	 * @since ??
	 * @var string|null
	 */
	private static $_previous_layout_type_before_reset = null;

	/**
	 * Cache for expensive block checks per layout type.
	 *
	 * Prevents redundant loops through blocks when checking the same layout type
	 * multiple times in the same request.
	 *
	 * @since ??
	 * @var array<string, bool>
	 */
	private static $_blocks_check_cache = [];

	/**
	 * Reset phases.
	 *
	 * Defines when resets should occur in the rendering pipeline.
	 *
	 * @since ??
	 */
	const PHASE_BEFORE_RENDERING   = 'before_rendering';
	const PHASE_BEFORE_DO_BLOCKS   = 'before_do_blocks';
	const PHASE_NEW_STORE_INSTANCE = 'new_store_instance';

	/**
	 * Determine if a reset should occur and perform it if needed.
	 *
	 * This method consolidates all reset logic into a single decision point,
	 * handling:
	 * - Theme Builder templates (header, body, footer) - each resets separately
	 * - Off-canvas content - continues sequential numbering
	 * - Canvas Portal modules - continues sequential numbering
	 * - Multiple do_blocks() calls - prevents duplicate resets
	 * - Canvas processing that parses blocks - resets before actual rendering
	 * - New store instance creation - resets when starting new rendering phase
	 *
	 * @since ??
	 *
	 * @param string $phase The reset phase (PHASE_BEFORE_RENDERING, PHASE_BEFORE_DO_BLOCKS, or PHASE_NEW_STORE_INSTANCE).
	 * @return bool True if reset was performed, false otherwise.
	 */
	public static function maybe_reset( $phase ) {
		// Only run on frontend (not in admin or visual builder).
		// Allow resets in test environments to ensure tests work correctly.
		$is_test_env = Conditions::is_test_env();
		if ( ( Conditions::is_admin_request() || Conditions::is_vb_enabled() ) && ! $is_test_env ) {
			return false;
		}

		// Prevent reset when rendering off-canvas content.
		// This ensures orderIndex continues sequentially across prepended canvases,
		// main content, and appended canvases.
		$is_rendering_off_canvas = ! empty( $GLOBALS['divi_off_canvas_rendering'] );

		// Prevent reset when rendering inner content (e.g., Canvas Portal modules).
		// This ensures orderIndex continues sequentially from the parent canvas through
		// the portal content, maintaining proper module order integration.
		$is_rendering_inner_content = BlockParserStore::is_rendering_inner_content();

		if ( $is_rendering_off_canvas || $is_rendering_inner_content ) {
			return false;
		}

		$current_layout_type = BlockParserStore::get_layout_type();
		$context_key         = self::_get_context_key( $current_layout_type, $phase );

		// PERFORMANCE OPTIMIZATION: Early return if we've already reset for this context.
		// This prevents duplicate resets and avoids expensive operations.
		// For large pages with many modules, this check will short-circuit 99% of calls,
		// avoiding all the complex conditional logic below.
		if ( isset( self::$_reset_contexts[ $context_key ] ) ) {
			// Special handling for PHASE_BEFORE_DO_BLOCKS and PHASE_NEW_STORE_INSTANCE:
			// These phases may need to clear context if moving to a new area, but area transitions
			// are rare (only 3-4 times per page). Check if we're in an area transition before
			// falling through to the expensive logic below.
			if ( self::PHASE_BEFORE_DO_BLOCKS === $phase || self::PHASE_NEW_STORE_INSTANCE === $phase ) {
				// Quick check: Are we potentially in an area transition?
				// This avoids expensive checks for the common case (no area transition).
				$might_be_area_transition = null !== self::$_last_reset_layout_type && self::$_last_reset_layout_type !== $current_layout_type;
				if ( ! $might_be_area_transition ) {
					// Not an area transition, safe to return early.
					return false;
				}
				// Might be an area transition - fall through to check below.
			} else {
				// For PHASE_BEFORE_RENDERING, we can safely return early.
				return false;
			}
		}

		// Clear appended canvas flag when starting a new layout type's rendering.
		// The flag is specific to each layout area (header/body/footer/post content),
		// so when we move to a different layout type, clear the flag to prevent it
		// from affecting the new layout type's reset logic.
		if ( self::PHASE_BEFORE_RENDERING === $phase && self::$_appended_canvas_processed_layout && self::$_appended_canvas_processed_layout !== $current_layout_type ) {
			self::set_appended_canvas_processed( false );
		}

		// Phase-specific validation checks.
		// Each phase may have additional conditions that prevent reset.
		if ( self::PHASE_BEFORE_RENDERING === $phase ) {
			// PERFORMANCE: Cache layout type check to avoid repeated string comparisons.
			$is_default_layout_type       = 'default' === $current_layout_type;
			$has_already_reset_for_layout = isset( self::$_reset_contexts[ $context_key ] );
			$is_moving_to_new_area        = null !== self::$_last_reset_layout_type && self::$_last_reset_layout_type !== $current_layout_type;

			// For 'default' layout type (no area), multiple do_blocks() calls should continue incrementing
			// without resetting. Only reset at the start of the next area (header, body, footer, post content).
			// However, if we're moving to a different layout type (indicating a new area), we should reset
			// even for 'default' layout type.
			if ( $is_default_layout_type && $has_already_reset_for_layout && ! $is_moving_to_new_area ) {
				// For 'default' layout type, skip reset after the first reset ONLY if we're still in the same area.
				// This allows continuous incrementing across multiple do_blocks() calls in the same area.
				return false;
			}

			if ( $is_moving_to_new_area ) {
				// Moving to a new area - we should reset even if we've reset before for this layout type.
				// Clear ALL reset contexts for this layout type to ensure a fresh start for the new area.
				// This is important because header's appended canvas might have incremented the order index
				// for 'default' layout type, and we need to reset it when moving to post content.
				// Clear all contexts for this layout type (all phases).
				foreach ( [ self::PHASE_BEFORE_RENDERING, self::PHASE_BEFORE_DO_BLOCKS, self::PHASE_NEW_STORE_INSTANCE ] as $phase_to_clear ) {
					$context_to_clear = self::_get_context_key( $current_layout_type, $phase_to_clear );
					if ( isset( self::$_reset_contexts[ $context_to_clear ] ) ) {
						unset( self::$_reset_contexts[ $context_to_clear ] );
					}
				}
				// Clear block check cache when moving to a new area to prevent stale cache.
				// Cache keys are layout-type specific, so we only clear entries for the new layout type.
				$cache_prefixes_to_clear = [
					"has_parsed_blocks_globally:{$current_layout_type}",
					"has_blocks_current:{$current_layout_type}",
					"has_blocks_previous:{$current_layout_type}",
				];
				foreach ( $cache_prefixes_to_clear as $prefix ) {
					unset( self::$_blocks_check_cache[ $prefix ] );
				}
			}

			// PERFORMANCE: Only check for parsed blocks if we haven't already determined we should skip the reset.
			// This expensive global check should be avoided whenever possible.
			if ( $has_already_reset_for_layout && ! $is_moving_to_new_area ) {
				// Cache key for this expensive check (avoid string concatenation overhead).
				$cache_key = "has_parsed_blocks_globally:{$current_layout_type}";

				// Check cache first to avoid redundant has_parsed_blocks() calls.
				if ( ! isset( self::$_blocks_check_cache[ $cache_key ] ) ) {
					// PERFORMANCE: This is an expensive check that loops through all instances.
					// Only call it once per layout type per request and cache the result.
					self::$_blocks_check_cache[ $cache_key ] = BlockParserStore::has_parsed_blocks();
				}
				$has_parsed_blocks_globally = self::$_blocks_check_cache[ $cache_key ];

				// Only skip reset if blocks have been parsed globally AND we've already reset once for this layout type.
				// This handles the case where do_blocks() is called multiple times in the same request for the same layout type.
				// If blocks were parsed globally (e.g., during CSS generation) but we haven't reset for this layout type yet,
				// we need to reset to ensure HTML rendering starts from 0.
				if ( $has_parsed_blocks_globally ) {
					return false;
				}
			}
		} elseif ( self::PHASE_BEFORE_DO_BLOCKS === $phase || self::PHASE_NEW_STORE_INSTANCE === $phase ) {
			// Check if we're moving to a new area. Use previous layout type to detect transitions
			// that happened before PHASE_BEFORE_RENDERING updated $_last_reset_layout_type.
			// This handles the case where header's appended canvas increments 'default' layout type's
			// order index, but the flag is set for 'et_header_layout'.
			$previous_layout       = self::$_previous_layout_type_before_reset ?? self::$_last_reset_layout_type;
			$is_moving_to_new_area = null !== $previous_layout && $previous_layout !== $current_layout_type;

			if ( $is_moving_to_new_area ) {
				// Moving to a new area - clear appended canvas flag and reset contexts to ensure proper reset.
				// This is critical because header's appended canvas might have incremented 'default' layout type's
				// order index, and we need to reset it when moving to post content.
				// Clear appended canvas flag when moving to a new area.
				if ( self::$_appended_canvas_processed_layout !== $current_layout_type ) {
					self::set_appended_canvas_processed( false );
				}
				// Clear all reset contexts for this layout type to allow reset.
				foreach ( [ self::PHASE_BEFORE_RENDERING, self::PHASE_BEFORE_DO_BLOCKS, self::PHASE_NEW_STORE_INSTANCE ] as $phase_to_clear ) {
					$context_to_clear = self::_get_context_key( $current_layout_type, $phase_to_clear );
					if ( isset( self::$_reset_contexts[ $context_to_clear ] ) ) {
						unset( self::$_reset_contexts[ $context_to_clear ] );
					}
				}
			}

			// For PHASE_BEFORE_DO_BLOCKS, this phase is specifically designed to reset after canvas/interaction
			// processing (priority 2) but before do_blocks() (priority 9). If we've already reset at
			// PHASE_BEFORE_RENDERING, but then canvas/interaction processing incremented the order index,
			// we need to reset again here. The only exception is if an appended canvas was processed
			// for THIS layout type (in which case we want to continue the sequence).
			if ( self::PHASE_BEFORE_DO_BLOCKS === $phase ) {
				// Check if appended canvas was processed FOR THIS LAYOUT TYPE.
				// If so, don't reset - we want to continue the sequence from the canvas.
				// PERFORMANCE: Direct property comparison - no function call overhead.
				$appended_canvas_processed = self::$_appended_canvas_processed_layout === $current_layout_type;
				if ( $appended_canvas_processed ) {
					// Appended canvas was processed for this layout type, don't reset.
					// Mark this context as "reset" to prevent future resets, even though
					// we didn't actually reset. This ensures the context tracking works correctly.
					self::$_reset_contexts[ $context_key ] = true;
					return false;
				}

				// PHASE_BEFORE_DO_BLOCKS should reset if we haven't reset at this phase yet.
				// This ensures that if canvas/interaction processing incremented the order index
				// after PHASE_BEFORE_RENDERING, we reset it before do_blocks() runs.
				// The reset will happen naturally below if the context isn't set.
				// Note: We don't need to check $has_reset_before_rendering or $has_reset_before_do_blocks
				// here because those checks don't affect whether we should reset - they're just for tracking.
			}

			// For PHASE_NEW_STORE_INSTANCE, check if appended canvases were processed FOR THIS LAYOUT TYPE.
			// Appended canvases (processed at priority 2) render before do_blocks() and increment the order index.
			// We should NOT reset in this case, so the main content continues the sequential numbering from the appended canvas.
			// We check per layout type because appended canvases are specific to each layout area.
			if ( self::PHASE_NEW_STORE_INSTANCE === $phase ) {
				// PERFORMANCE: Direct property comparison - no function call overhead.
				$appended_canvas_processed = self::$_appended_canvas_processed_layout === $current_layout_type;

				if ( $appended_canvas_processed ) {
					// Appended canvas was processed, don't reset.
					// Mark this context as "reset" to prevent future resets, even though
					// we didn't actually reset. This ensures the context tracking works correctly.
					self::$_reset_contexts[ $context_key ] = true;
					// Clear the flag only after PHASE_NEW_STORE_INSTANCE check (final check).
					// Check if order index has incremented before clearing - this ensures the flag
					// persists through both canvas rendering AND main content rendering.
					// Only clear if this is the same layout type that had the appended canvas.
					if ( self::$_appended_canvas_processed_layout === $current_layout_type ) {
						$current_index           = \ET_Builder_Module_Order::get_index( 'section', 'et_pb_section', $current_layout_type );
						$order_index_incremented = $current_index >= 0;
						if ( $order_index_incremented ) {
							self::set_appended_canvas_processed( false );
						}
					}
					return false;
				}
			}

			// For PHASE_NEW_STORE_INSTANCE, check if blocks have been parsed for this layout type.
			// If PHASE_BEFORE_RENDERING has already run, we need to check if blocks exist in PREVIOUS
			// instances (from CSS generation) vs current instance (from this do_blocks() call).
			// If PHASE_BEFORE_RENDERING hasn't run, use original logic (check current instance only).
			if ( self::PHASE_NEW_STORE_INSTANCE === $phase ) {
				// PERFORMANCE: Calculate context keys once and reuse them throughout this block.
				// Avoid redundant _get_context_key() calls which do string concatenation.
				$before_rendering_context_key       = self::_get_context_key( $current_layout_type, self::PHASE_BEFORE_RENDERING );
				$before_do_blocks_context_key       = self::_get_context_key( $current_layout_type, self::PHASE_BEFORE_DO_BLOCKS );
				$has_already_reset_before_rendering = isset( self::$_reset_contexts[ $before_rendering_context_key ] );
				$has_reset_before_do_blocks         = isset( self::$_reset_contexts[ $before_do_blocks_context_key ] );

				// PERFORMANCE: Check for 'default' layout type once and cache the result.
				$is_default_layout_type = 'default' === $current_layout_type;

				// If PHASE_BEFORE_RENDERING has run but PHASE_BEFORE_DO_BLOCKS hasn't, we need to reset
				// here to account for any order index increments from interaction detection or canvas processing.
				// This handles the case where PHASE_BEFORE_DO_BLOCKS never runs (e.g., when there's no header).
				// However, we only reset if this is NOT the first store instance (previous_instance !== null),
				// because the first instance is created before blocks are parsed, so we can't check if blocks exist yet.
				if ( $has_already_reset_before_rendering && ! $has_reset_before_do_blocks ) {
					// For 'default' layout type, always reset here if before_do_blocks hasn't run.
					// This ensures that if interaction detection or canvas processing incremented the order index,
					// we reset it before rendering. The reset will happen naturally below if the context isn't set.
					if ( ! $is_default_layout_type ) {
						// For non-default layout types, skip reset to allow continuous incrementing.
						self::$_reset_contexts[ $context_key ] = true;
						return false;
					}
					// For 'default' layout type, don't set the context here - let it reset below if needed.
					// But also don't return false - continue to check other conditions.
				} elseif ( $has_already_reset_before_rendering && $has_reset_before_do_blocks ) {
					// Both PHASE_BEFORE_RENDERING and PHASE_BEFORE_DO_BLOCKS have run.
					// For 'default' layout type, skip reset to allow continuous incrementing.
					if ( $is_default_layout_type ) {
						self::$_reset_contexts[ $context_key ] = true;
						return false;
					}
				}

				// PERFORMANCE: Only check for blocks if we need to (avoid expensive operations when possible).
				$already_reset_for_new_instance = isset( self::$_reset_contexts[ $context_key ] );
				$before_rendering_has_run       = $has_already_reset_before_rendering;

				// Early return if we've already reset and don't need to check blocks.
				if ( $already_reset_for_new_instance && ! $before_rendering_has_run ) {
					// Already reset and before_rendering hasn't run - no need to check blocks.
					// This avoids expensive block checks on large pages when context is already set.
					return false;
				}

				if ( $before_rendering_has_run ) {
					// If PHASE_BEFORE_RENDERING has already run (HTML rendering phase):
					// - If blocks exist in current instance, they're from this do_blocks() call - don't reset (continue sequential).
					// - If blocks exist only in previous instances, they're from CSS generation - reset to account for them.
					// Only check if we haven't already reset (to avoid unnecessary expensive check).
					if ( ! $already_reset_for_new_instance ) {
						// Cache expensive block check.
						$cache_key_current = "has_blocks_current:{$current_layout_type}";
						if ( ! isset( self::$_blocks_check_cache[ $cache_key_current ] ) ) {
							self::$_blocks_check_cache[ $cache_key_current ] = BlockParserStore::has_blocks_for_layout_type( $current_layout_type );
						}
						$has_blocks_in_current_instance = self::$_blocks_check_cache[ $cache_key_current ];
						if ( $has_blocks_in_current_instance ) {
							// Blocks in current instance - this is a subsequent do_blocks() call, continue sequentially.
							return false;
						}
					}
					// No blocks in current instance - check if blocks exist in previous instances (CSS generation).
					// Only check if we've already reset for this instance (to avoid unnecessary expensive check).
					if ( $already_reset_for_new_instance ) {
						// Cache expensive block check (most expensive - loops through ALL instances).
						$cache_key_previous = "has_blocks_previous:{$current_layout_type}";
						if ( ! isset( self::$_blocks_check_cache[ $cache_key_previous ] ) ) {
							self::$_blocks_check_cache[ $cache_key_previous ] = BlockParserStore::has_blocks_for_layout_type_anywhere( $current_layout_type );
						}
						$has_blocks_in_previous_instances = self::$_blocks_check_cache[ $cache_key_previous ];
						if ( $has_blocks_in_previous_instances ) {
							// Blocks exist in previous instances but not current - likely from CSS generation.
							// Remove context to reset and account for CSS generation blocks.
							unset( self::$_reset_contexts[ $context_key ] );
						}
					}
				} else {
					// PHASE_BEFORE_RENDERING hasn't run - restore the original multi-do_blocks contract
					// from OrderIndexResetManager introduction (1de4d033): if any store instance already
					// has parsed blocks, skip reset so consecutive do_blocks() calls continue sequentially.
					//
					// IMPORTANT: Check across instances via has_parsed_blocks(), not the current instance.
					// new_instance() switches $_instance to the new empty store before maybe_reset() runs,
					// so has_blocks_for_layout_type() on the current instance would always be false here.
					$cache_key_parsed = "has_parsed_blocks_globally:{$current_layout_type}";
					if ( ! isset( self::$_blocks_check_cache[ $cache_key_parsed ] ) ) {
						self::$_blocks_check_cache[ $cache_key_parsed ] = BlockParserStore::has_parsed_blocks();
					}
					if ( self::$_blocks_check_cache[ $cache_key_parsed ] ) {
						self::$_reset_contexts[ $context_key ] = true;
						return false;
					}
				}
			}
		}

		// Check if we've already reset for this context (after phase-specific checks that might clear it).
		// This prevents duplicate resets for the same layout type and phase combination.
		if ( isset( self::$_reset_contexts[ $context_key ] ) ) {
			return false;
		}

		// Perform the reset.
		\ET_Builder_Module_Order::reset_indexes( $current_layout_type );

		// Track that we've reset for this context.
		self::$_reset_contexts[ $context_key ] = true;

		// Track the last layout type that was reset at PHASE_BEFORE_RENDERING.
		// This helps detect when we move to a new area.
		if ( self::PHASE_BEFORE_RENDERING === $phase ) {
			// Store the previous layout type before updating, so other phases can detect the transition.
			self::$_previous_layout_type_before_reset = self::$_last_reset_layout_type;
			self::$_last_reset_layout_type            = $current_layout_type;
			BlockParserStore::set_has_reset_order_index( true );
		}

		return true;
	}

	/**
	 * Initialize page-wide off-canvas tracking for the current HTTP request.
	 *
	 * Called from reset_order_index_before_rendering() on each et_builder_render_layout
	 * pass, so arrays are initialized only when absent — not cleared mid-request.
	 * PHP resets $GLOBALS on each HTTP request; tests unset these keys between cases.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function init_page_render() {
		if ( ! isset( $GLOBALS['divi_off_canvas_global_rendered'] ) ) {
			$GLOBALS['divi_off_canvas_global_rendered'] = [];
		}

		if ( ! isset( $GLOBALS['divi_off_canvas_injected_canvas_ids'] ) ) {
			$GLOBALS['divi_off_canvas_injected_canvas_ids'] = [];
		}
	}

	/**
	 * Reset all tracking state (useful for testing or edge cases).
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function reset_tracking() {
		self::$_reset_contexts                    = [];
		self::$_appended_canvas_processed_layout  = false;
		self::$_last_reset_layout_type            = null;
		self::$_previous_layout_type_before_reset = null;
		self::$_blocks_check_cache                = [];
	}

	/**
	 * Get context key for tracking resets.
	 *
	 * Creates a unique key based on layout type and phase to track
	 * whether a reset has occurred for a specific context.
	 *
	 * @since ??
	 *
	 * @param string $layout_type The current layout type.
	 * @param string $phase        The reset phase.
	 * @return string The context key.
	 */
	private static function _get_context_key( $layout_type, $phase ) {
		return "{$layout_type}:{$phase}";
	}

	/**
	 * Set the appended canvas processed flag for a specific layout type.
	 *
	 * @since ??
	 *
	 * @param string|false $layout_type The layout type that had appended canvases processed, or false to clear.
	 */
	public static function set_appended_canvas_processed( $layout_type ) {
		self::$_appended_canvas_processed_layout = false === $layout_type ? false : $layout_type;
	}

	/**
	 * Check if appended canvases have been processed for a specific layout type.
	 *
	 * @since ??
	 *
	 * @param string $layout_type The layout type to check.
	 * @return bool True if appended canvases have been processed for this layout type.
	 */
	public static function has_appended_canvas_processed( $layout_type ) {
		return self::$_appended_canvas_processed_layout === $layout_type;
	}
}
