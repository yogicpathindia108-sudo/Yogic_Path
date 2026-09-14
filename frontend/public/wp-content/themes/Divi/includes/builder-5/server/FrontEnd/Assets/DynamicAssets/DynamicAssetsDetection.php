<?php
/**
 * Dynamic Assets Detection Handler.
 *
 * Handles feature detection logic for early and late detection.
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\FrontEnd\Assets\DynamicAssets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use ET\Builder\FrontEnd\Assets\DetectFeature;
use ET\Builder\FrontEnd\Assets\DynamicAssets\State\CacheState;
use ET\Builder\FrontEnd\Assets\DynamicAssets\State\DetectionState;
use ET\Builder\FrontEnd\Assets\DynamicAssets\State\FeatureState;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use InvalidArgumentException;

/**
 * Dynamic Assets Detection class.
 *
 * Handles feature detection logic for early and late detection.
 *
 * @since ??
 */
class DynamicAssetsDetection {

	/**
	 * Cache state container.
	 *
	 * @var CacheState
	 */
	private CacheState $cache_state;

	/**
	 * Detection state container.
	 *
	 * @var DetectionState
	 */
	private DetectionState $detection_state;

	/**
	 * Feature state container.
	 *
	 * @var FeatureState
	 */
	private FeatureState $feature_state;

	/**
	 * Cache handler instance.
	 *
	 * @var DynamicAssetsCache
	 */
	private DynamicAssetsCache $cache;

	/**
	 * Callback to get all content.
	 *
	 * @var callable
	 */
	private $_get_all_content_callback;

	/**
	 * Callback to get theme builder template content.
	 *
	 * @var callable
	 */
	private $_get_theme_builder_content_callback;

	/**
	 * Cached result of Theme Builder post type check.
	 * Used to avoid repeated ET_Post_Stack::get() calls during block rendering.
	 *
	 * @var bool|null
	 */
	private ?bool $_is_theme_builder_context = null;

	/**
	 * Seen late-replay block attribute payload hashes for this request.
	 *
	 * @var array<string, bool>
	 */
	private array $_seen_block_attribute_payloads = [];

	/**
	 * Seen late-replay block content payload hashes for this request.
	 *
	 * @var array<string, bool>
	 */
	private array $_seen_late_block_payloads = [];

	/**
	 * Seen late-replay shortcode attribute payload hashes for this request.
	 *
	 * @var array<string, bool>
	 */
	private array $_seen_shortcode_attribute_payloads = [];

	/**
	 * Seen late-replay shortcode content payload hashes for this request.
	 *
	 * @var array<string, bool>
	 */
	private array $_seen_late_shortcode_payloads = [];

	/**
	 * Cached preset feature results for this detection request.
	 *
	 * This stays instance-scoped so tests and later requests cannot reuse stale
	 * preset results computed against a different GlobalPreset state.
	 *
	 * @var array<string, array>
	 */
	private array $_preset_feature_used_cache = [];

	/**
	 * Constructor.
	 *
	 * @since ??
	 *
	 * @param CacheState         $cache_state                    Cache state container.
	 * @param DetectionState     $detection_state               Detection state container.
	 * @param FeatureState       $feature_state                 Feature state container.
	 * @param DynamicAssetsCache $cache                         Cache handler instance.
	 * @param callable           $get_all_content_callback      Callback to get all content.
	 * @param callable           $get_theme_builder_callback    Callback to get theme builder content.
	 */
	public function __construct(
		CacheState $cache_state,
		DetectionState $detection_state,
		FeatureState $feature_state,
		DynamicAssetsCache $cache,
		callable $get_all_content_callback,
		callable $get_theme_builder_callback
	) {
		$this->cache_state                         = $cache_state;
		$this->detection_state                     = $detection_state;
		$this->feature_state                       = $feature_state;
		$this->cache                               = $cache;
		$this->_get_all_content_callback           = $get_all_content_callback;
		$this->_get_theme_builder_content_callback = $get_theme_builder_callback;
	}

	/**
	 * Extract block identifiers from content.
	 *
	 * @since ??
	 *
	 * @param string $content Content to extract block identifiers from.
	 *
	 * @return array Array of block identifiers (hashes of serialized blocks).
	 */
	private function _extract_block_ids( string $content ): array {
		if ( empty( $content ) ) {
			return [];
		}

		// Early return if content doesn't contain block markers.
		if ( false === strpos( $content, '<!-- wp:divi/' ) ) {
			return [];
		}

		// Extract each complete block (<!-- wp:divi/... --> ... <!-- /wp:divi/... -->).
		// For self-closing blocks: <!-- wp:divi/... /-->
		// Use a hash of each complete block as the identifier.
		$blocks = [];

		// Split on block boundaries and extract each block.
		// Match Divi block markers.
		preg_match_all( '/<!-- wp:divi\/[^>]+-->/', $content, $matches );

		if ( ! empty( $matches[0] ) ) {
			// Use each block marker as a unique identifier.
			// If the same block renders twice, it will have the same marker.
			foreach ( $matches[0] as $block_marker ) {
				// Create a hash of the marker to use as ID.
				$blocks[] = md5( $block_marker );
			}
		}

		return $blocks;
	}

	/**
	 * Check if we're currently rendering a Theme Builder template.
	 * Caches the result to avoid repeated ET_Post_Stack::get() calls.
	 *
	 * @since ??
	 *
	 * @return bool True if rendering Theme Builder template, false otherwise.
	 */
	private function _is_theme_builder_template(): bool {
		// Cache the result to avoid repeated ET_Post_Stack::get() calls.
		if ( null === $this->_is_theme_builder_context ) {
			$this->_is_theme_builder_context = false;
			if ( class_exists( '\ET_Post_Stack' ) ) {
				$current_post = \ET_Post_Stack::get();
				if ( $current_post && isset( $current_post->post_type ) ) {
					$tb_post_types                   = [ 'et_header_layout', 'et_footer_layout', 'et_body_layout' ];
					$this->_is_theme_builder_context = in_array( $current_post->post_type, $tb_post_types, true );
				}
			}
		}

		return $this->_is_theme_builder_context;
	}

	/**
	 * Extract shortcode identifiers from content.
	 *
	 * @since ??
	 *
	 * @param string $content Content to extract shortcode identifiers from.
	 *
	 * @return array Array of shortcode identifiers (hashes of shortcode strings).
	 */
	private function _extract_shortcode_ids( string $content ): array {
		if ( empty( $content ) ) {
			return [];
		}

		// Early return if content doesn't contain shortcode markers.
		if ( false === strpos( $content, '[et_pb_' ) ) {
			return [];
		}

		// Extract each complete shortcode ([et_pb_module_name attr="value"]content[/et_pb_module_name]).
		// For self-closing shortcodes: [et_pb_module_name attr="value" /]
		// Use a hash of each complete shortcode as the identifier.
		$shortcodes = [];

		// Match Divi shortcode patterns.
		// Pattern matches: [et_pb_module_name ...] or [et_pb_module_name ... /].
		preg_match_all( '/\[et_pb_[^\]]+\]/', $content, $matches );

		if ( ! empty( $matches[0] ) ) {
			// Use each shortcode string as a unique identifier.
			// If the same shortcode renders twice, it will have the same string.
			foreach ( $matches[0] as $shortcode_string ) {
				// Create a hash of the shortcode string to use as ID.
				$shortcodes[] = md5( $shortcode_string );
			}
		}

		return $shortcodes;
	}

	/**
	 * Get all content via callback.
	 *
	 * @since ??
	 *
	 * @return string All content.
	 */
	private function _get_all_content(): string {
		return call_user_func( $this->_get_all_content_callback );
	}

	/**
	 * Get theme builder template content via callback.
	 *
	 * @since ??
	 *
	 * @return string Theme builder template content.
	 */
	private function _get_theme_builder_template_content(): string {
		return call_user_func( $this->_get_theme_builder_content_callback );
	}

	/**
	 * Append a late-replay payload once per unique value for this request.
	 *
	 * @since ??
	 *
	 * @param string $payload            Payload to append.
	 * @param string $target_property    Detection state string property name.
	 * @param string $seen_hashes_bucket Runtime bucket property name.
	 *
	 * @return void
	 */
	private function _append_unique_detection_payload( string $payload, string $target_property, string $seen_hashes_bucket ): void {
		if ( '' === $payload ) {
			return;
		}

		$payload_hash = md5( $payload );

		if ( isset( $this->{$seen_hashes_bucket}[ $payload_hash ] ) ) {
			return;
		}

		// Mutate the bucket in place to avoid PHP copy-on-write duplication on each unique append.
		$this->{$seen_hashes_bucket}[ $payload_hash ] = true;
		$this->detection_state->{$target_property}   .= $payload;
	}

	/**
	 * Gets a list of early modules.
	 *
	 * @since ??
	 *
	 * @param string $content Content to analyze.
	 *
	 * @return array Array with 'blocks' and 'shortcodes' keys.
	 */
	public function get_early_modules( string $content ): array {
		// Detect block and shortcode names found in the given post-content.
		// We do this early because the shortcode-framework may not be loaded yet.
		$block_names     = DetectFeature::get_block_names( $content );
		$shortcode_names = DetectFeature::get_shortcode_names( $content );

		// Return all detected block and shortcode names.
		return [
			'blocks'     => $block_names,
			'shortcodes' => $shortcode_names,
		];
	}

	/**
	 * Get all shortcodes (early + late detection).
	 *
	 * @since ??
	 *
	 * @return array All shortcodes.
	 */
	public function get_all_shortcodes(): array {
		// Return cached value if available.
		if ( null !== $this->detection_state->all_shortcodes ) {
			return $this->detection_state->all_shortcodes;
		}

		// Combine early and late detected shortcodes.
		$this->detection_state->all_shortcodes = array_unique(
			array_merge(
				$this->detection_state->early_shortcodes,
				$this->detection_state->shortcode_used
			)
		);

		return $this->detection_state->all_shortcodes;
	}

	/**
	 * Check if the current page has reCaptcha-enabled modules.
	 *
	 * This method leverages the cached feature detection results from DynamicAssets
	 * instead of performing expensive content parsing again.
	 *
	 * @since ??
	 *
	 * @return bool True if reCaptcha-enabled modules found, false otherwise.
	 */
	public function has_recaptcha_enabled(): bool {
		// Check if feature detection has been performed and cached.
		if ( ! empty( $this->detection_state->early_attributes ) && isset( $this->detection_state->early_attributes['recaptcha_enabled'] ) ) {
			// Return cached result - use array_filter to handle boolean arrays.
			$recaptcha_results = array_filter( $this->detection_state->early_attributes['recaptcha_enabled'] );
			return ! empty( $recaptcha_results );
		}

		// If not cached, perform detection using the current content.
		$content = $this->_get_all_content();

		// Include Theme Builder template content if templates exist.
		// The tb_template_ids property is populated during initialization and correctly
		// handles the 404/archive edge case via DynamicAssetsUtils::get_theme_builder_template_ids().
		if ( ! empty( $this->cache_state->tb_template_ids ) ) {
			$tb_content = $this->_get_theme_builder_template_content();

			if ( ! empty( $tb_content ) ) {
				$content .= $tb_content;
			}
		}

		if ( ! empty( $content ) ) {
			return DetectFeature::has_recaptcha_enabled( $content, $this->detection_state->options );
		}

		// Fallback: return false if no content available.
		return false;
	}

	/**
	 * Check if early attributes were loaded from cache and should skip detection.
	 *
	 * @since ??
	 *
	 * @return bool True if detection should be skipped.
	 */
	private function _should_skip_detection(): bool {
		return $this->detection_state->early_attributes_from_cache && ! empty( $this->detection_state->early_attributes );
	}

	/**
	 * Get cached feature value from _early_attributes or run detection.
	 *
	 * This helper method checks the cached feature detection results first,
	 * avoiding expensive content parsing when features are already cached.
	 * This is the primary method for all feature detection - it ensures cache
	 * is always checked before running expensive detection methods.
	 *
	 * @since ??
	 *
	 * @param string   $feature_name      Feature name key in _early_attributes.
	 * @param callable $detection_callback Detection callback to run if not cached.
	 * @param array    $callback_args     Arguments to pass to detection callback.
	 * @param mixed    $default_value     Default value if feature not found and detection returns falsy.
	 *
	 * @return mixed Cached value or result from detection callback.
	 */
	public function get_cached_or_detect_feature( string $feature_name, callable $detection_callback, array $callback_args = [], $default_value = false ) {
		// Check if feature detection has been performed and cached.
		// Check if _early_attributes exists and has this feature key.
		if ( is_array( $this->detection_state->early_attributes ) && isset( $this->detection_state->early_attributes[ $feature_name ] ) ) {
			// Return cached result - handle both boolean arrays and array values.
			$cached_value = $this->detection_state->early_attributes[ $feature_name ];

			if ( is_array( $cached_value ) ) {
				// For boolean features stored as arrays (e.g., [true] or []).
				// Extract boolean: if array has truthy values, return true; otherwise return default.
				$filtered = array_filter( $cached_value );
				if ( ! empty( $filtered ) ) {
					// If it's a single boolean value in array, return the boolean.
					// Otherwise return the array (for features like gutter_widths, global_color_ids).
					if ( 1 === count( $filtered ) && is_bool( reset( $filtered ) ) ) {
						return reset( $filtered );
					}
					// Array feature - return the full array.
					return $cached_value;
				}

				// Empty array means feature was not detected. Return default.
				return $default_value;
			}
			return $cached_value;
		}

		// If not cached, perform detection.
		$detection_result = call_user_func_array( $detection_callback, $callback_args );

		// Store detection result in _early_attributes so late detection can reuse it within the same request.
		// This prevents re-detecting the same feature multiple times in one page load.
		// Initialize _early_attributes as an array if it doesn't exist yet.
		if ( ! is_array( $this->detection_state->early_attributes ) ) {
			$this->detection_state->early_attributes = [];
		}

		// Store boolean results as arrays for consistency with cached format.
		if ( is_bool( $detection_result ) ) {
			$this->detection_state->early_attributes[ $feature_name ] = $detection_result ? [ $detection_result ] : [];
		} elseif ( is_array( $detection_result ) ) {
			$this->detection_state->early_attributes[ $feature_name ] = $detection_result;
		} else {
			$this->detection_state->early_attributes[ $feature_name ] = [ $detection_result ];
		}

		return $detection_result;
	}

	/**
	 * Process feature detection map with automatic cache checking.
	 *
	 * This method processes the feature detection map and automatically checks
	 * _early_attributes cache before running expensive detection methods.
	 * This ensures all feature detection respects the postmeta cache.
	 *
	 * @since ??
	 *
	 * @param array  $feature_detection_map Feature detection map from DynamicAssetsUtils::get_feature_detection_map().
	 * @param string $content               Content to pass to detection callbacks if not cached.
	 *
	 * @return array Feature detection results, same format as _early_attributes.
	 */
	public function process_feature_detection_map_with_cache( array $feature_detection_map, string $content ): array {
		// If early_attributes was loaded from postmeta cache, all features are already cached.
		// Return cached values directly without running any detection.
		if ( $this->_should_skip_detection() ) {
			$feature_used = [];
			foreach ( $feature_detection_map as $name => $params ) {
				if ( isset( $this->detection_state->early_attributes[ $name ] ) ) {
					$feature_used[ $name ] = $this->detection_state->early_attributes[ $name ];
				}
			}
			return $feature_used;
		}

		$feature_used = [];

		foreach ( $feature_detection_map as $name => $params ) {
			// Check cache first.
			if ( ! empty( $this->detection_state->early_attributes ) && isset( $this->detection_state->early_attributes[ $name ] ) ) {
				// Use cached value.
				$feature_used[ $name ] = $this->detection_state->early_attributes[ $name ];
				continue;
			}

			// Not cached, run detection.
			if ( ! isset( $feature_used[ $name ] ) ) {
				$feature_used[ $name ] = [];
			}

			if ( is_callable( $params['callback'] ) ) {
				$result = call_user_func_array(
					$params['callback'],
					array_merge(
						[ 'content' => $content ],
						$params['additional_args']
					)
				);

				// Also store in _early_attributes so late detection can reuse it within the same request.
				// Initialize _early_attributes as an array if it doesn't exist yet.
				if ( ! is_array( $this->detection_state->early_attributes ) ) {
					$this->detection_state->early_attributes = [];
				}

				$feature_used[ $name ]                            = $this->merge_unique_features( $feature_used[ $name ], $result );
				$this->detection_state->early_attributes[ $name ] = $feature_used[ $name ];
			}
		}

		return $feature_used;
	}

	/**
	 * Get cached feature value and apply it to a target array.
	 *
	 * Helper method to reduce duplication in detect_preset_feature_use and detect_attribute_feature_use.
	 * Checks _early_attributes cache and merges the cached value into the target array if found.
	 *
	 * @since ??
	 *
	 * @param string $feature_name Feature name key in _early_attributes.
	 * @param array  $target_array Target array to merge cached value into.
	 *
	 * @return array|null Returns merged array if cached, null if not cached.
	 */
	public function get_and_apply_cached_feature( string $feature_name, array $target_array ) {
		if ( empty( $this->detection_state->early_attributes ) || ! isset( $this->detection_state->early_attributes[ $feature_name ] ) ) {
			return null;
		}

		$cached_value = $this->detection_state->early_attributes[ $feature_name ];

		if ( is_bool( $cached_value ) ) {
			$target_array[] = $cached_value;
		} elseif ( is_array( $cached_value ) ) {
			$target_array = array_unique(
				array_merge(
					$target_array,
					$cached_value
				)
			);
		}

		return $target_array;
	}

	/**
	 * Process late detection.
	 *
	 * Get blocks from the feature manager that might have been missed during early detection.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function get_late_blocks(): void {
		// Note: late_blocks is now populated in real-time by log_block_used() when blocks are actually rendered late.
		// We don't need to set it here from blocks_used.

		// Track missed modules (new module types not found during early detection).
		$this->detection_state->missed_blocks     = array_diff( $this->detection_state->blocks_used, $this->detection_state->early_blocks );
		$this->detection_state->missed_shortcodes = array_diff( $this->detection_state->shortcode_used, $this->detection_state->early_shortcodes );

		$all_blocks     = array_merge(
			$this->detection_state->missed_blocks,
			$this->detection_state->early_blocks
		);
		$all_shortcodes = array_merge(
			$this->detection_state->missed_shortcodes,
			$this->detection_state->early_shortcodes
		);

		// Update _missed_modules.
		if ( $this->detection_state->missed_shortcodes ) {
			$this->detection_state->missed_modules = array_unique(
				array_merge(
					$this->detection_state->missed_blocks,
					array_map( [ DynamicAssetsUtils::class, 'get_block_name_from_shortcode' ], $this->detection_state->missed_shortcodes )
				)
			);
		} else {
			$this->detection_state->missed_modules = $this->detection_state->missed_blocks;
		}

		if ( $this->detection_state->missed_blocks || $this->detection_state->missed_shortcodes ) {
			$this->detection_state->need_late_generation = true;

			// Cache the all blocks/all shortcodes to the meta.
			$this->cache->metadata_set(
				'_divi_dynamic_assets_cached_modules',
				[
					'blocks'     => $all_blocks,
					'shortcodes' => $all_shortcodes,
				]
			);

			// Update block/shortcode use.
			$this->detection_state->options['has_block']     = ! empty( $all_blocks );
			$this->detection_state->options['has_shortcode'] = ! empty( $all_shortcodes );
		}

		// Update _all_modules.
		$this->detection_state->all_modules = array_unique(
			array_merge(
				$all_blocks,
				array_map( [ DynamicAssetsUtils::class, 'get_block_name_from_shortcode' ], $all_shortcodes )
			)
		);
	}

	/**
	 * Get module attributes used from the feature manager.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function get_late_attributes(): void {
		global $post;

		// If early_attributes was loaded from postmeta cache, all features are already cached.
		// Skip all detection and use cached values directly.
		if ( $this->detection_state->early_attributes_from_cache ) {
			$feature_used = $this->detection_state->early_attributes;

			// Set preset features to empty since they're already in early_attributes.
			if ( empty( $this->feature_state->presets_feature_used ) ) {
				$this->feature_state->presets_feature_used = [];
			}

			// Process cached features into _late_use_* flags and return early.
			$this->_process_feature_used_to_late_flags( $feature_used );
			return;
		}

		// Track whether we have late modules with genuinely new content (not just Theme Builder re-renders).
		// Only consider it late modules if we have late_block_content or late_shortcode_content (genuinely new blocks/shortcodes) OR missed modules.
		$has_late_modules = ! empty( $this->detection_state->late_block_content ) || ! empty( $this->detection_state->late_shortcode_content ) || ! empty( $this->detection_state->missed_blocks ) || ! empty( $this->detection_state->missed_shortcodes );

		// Track whether we have missed modules (new module types not found during early detection).
		$has_missed_blocks     = ! empty( $this->detection_state->missed_blocks );
		$has_missed_shortcodes = ! empty( $this->detection_state->missed_shortcodes );
		$has_missed_modules    = $has_missed_blocks || $has_missed_shortcodes;

		// Cache early_content to avoid multiple calls to _get_all_content().
		// Only fetch if we need it for block/shortcode ID comparison or preset detection.
		$early_content               = null;
		$has_new_block_instances     = false;
		$has_new_shortcode_instances = false;

		// Check if late-rendered blocks/shortcodes are genuinely new (not just Theme Builder re-renders).
		// Only do this expensive check if we have late block or shortcode content to compare.
		if ( $has_late_modules && ( ! empty( $this->detection_state->late_block_content ) || ! empty( $this->detection_state->late_shortcode_content ) ) ) {
			// Early detection scans all database content and captures block/shortcode IDs.
			// Compare late block/shortcode IDs against early block/shortcode IDs to detect truly new content.
			$early_content = $this->_get_all_content();

			// Check for new block instances.
			if ( ! empty( $this->detection_state->late_block_content ) ) {
				$early_block_ids = $this->_extract_block_ids( $early_content );
				$late_block_ids  = $this->_extract_block_ids( $this->detection_state->late_block_content );

				// Find block IDs in late content that weren't in early content.
				$new_block_ids           = array_diff( $late_block_ids, $early_block_ids );
				$has_new_block_instances = ! empty( $new_block_ids );
			}

			// Check for new shortcode instances.
			if ( ! empty( $this->detection_state->late_shortcode_content ) ) {
				$early_shortcode_ids = $this->_extract_shortcode_ids( $early_content );
				$late_shortcode_ids  = $this->_extract_shortcode_ids( $this->detection_state->late_shortcode_content );

				// Find shortcode IDs in late content that weren't in early content.
				$new_shortcode_ids           = array_diff( $late_shortcode_ids, $early_shortcode_ids );
				$has_new_shortcode_instances = ! empty( $new_shortcode_ids );
			}
		}

		// Detect feature used based on block/shortcode attributes if new block or shortcode instances were added.
		if ( $has_new_block_instances || $has_new_shortcode_instances ) {
			$this->detect_attribute_feature_use( $this->detection_state->attribute_used );
		}

		// If there's no late content (no missed modules and no late_block_content/late_shortcode_content), skip late detection processing.
		// We still need to cache early_attributes if this is the first load, but we don't need to:
		// - Re-detect preset features (already done in early detection)
		// - Re-merge preset features (already done in early detection)
		// - Process to late flags (only needed for genuinely new content).
		if ( ! $has_missed_modules && empty( $this->detection_state->late_block_content ) && empty( $this->detection_state->late_shortcode_content ) ) {
			// Cache early_attributes if this is the first load (needed for future requests).
			// Only save cache for cacheable requests (check folder_name exists as proxy for cacheable request).
			if ( ! empty( $this->detection_state->early_attributes ) && ! empty( $this->cache_state->folder_name ) ) {
				$this->cache->metadata_set( '_divi_dynamic_assets_cached_feature_used', $this->detection_state->early_attributes );
			}

			// Skip preset detection, merging, and late flag processing since there's no late content.
			return;
		}

		// Process missed modules and early attributes if they exist.
		$feature_used = $this->_build_feature_used( $has_missed_modules, $early_content );

		// Load preset features for processing into _late_use_* flags.
		$this->_detect_preset_features( $has_missed_modules, $has_new_block_instances, $has_new_shortcode_instances, $early_content );

		// Merge preset features with existing features (preset features take precedence).
		$feature_used = $this->_merge_preset_features( $feature_used );

		// Cache the final merged feature_used.
		// Since we didn't return early, we have missed modules or late content.
		// Only save cache for cacheable requests (check folder_name exists as proxy for cacheable request).
		if ( ! empty( $feature_used ) && ! empty( $this->cache_state->folder_name ) ) {
			$this->cache->metadata_set( '_divi_dynamic_assets_cached_feature_used', $feature_used );
		}

		// Process all detected features into _late_use_* flags.
		// Only process if we have genuinely new content (missed modules or late block/shortcode content).
		$this->_process_feature_used_to_late_flags( $feature_used );
	}

	/**
	 * Build feature_used array based on missed modules and early attributes.
	 *
	 * @since ??
	 *
	 * @param bool        $has_missed_modules Whether missed modules were detected.
	 * @param string|null $early_content      Early content (may be null, will be fetched if needed).
	 *
	 * @return array Feature detection results.
	 */
	private function _build_feature_used( bool $has_missed_modules, ?string $early_content ): array {
		if ( $has_missed_modules ) {
			return $this->_build_feature_used_from_missed_modules();
		}

		// Check if we need to run feature detection from content.
		// Run detection if: no early_attributes OR empty late_block_content/late_shortcode_content.
		// Note: early_attributes can be empty array [] after preset merging, which is falsy.
		if ( ! $this->detection_state->early_attributes && empty( $this->detection_state->late_block_content ) && empty( $this->detection_state->late_shortcode_content ) ) {
			// No missed modules and no early attributes - first page load, detect from content.
			if ( null === $early_content ) {
				$early_content = $this->_get_all_content();
			}
			if ( ! empty( $early_content ) ) {
				$feature_detection_map = DynamicAssetsUtils::get_feature_detection_map( $this->detection_state->options );
				return $this->process_feature_detection_map_with_cache( $feature_detection_map, $early_content );
			}
		}

		// Use early_attributes if available, otherwise empty array.
		return $this->detection_state->early_attributes ?? [];
	}

	/**
	 * Build feature_used array when missed modules are detected.
	 *
	 * @since ??
	 *
	 * @return array Feature detection results.
	 */
	private function _build_feature_used_from_missed_modules(): array {
		global $post;

		$this->detection_state->need_late_generation = true;

		if ( ! $this->detection_state->early_attributes ) {
			// Handle password-protected posts that may not have run main content blocks.
			if ( post_password_required() ) {
				do_blocks( $post->post_content );
				if ( et_is_shortcode_framework_loaded() ) {
					do_shortcode( $post->post_content );
				}
			}
			return $this->detection_state->block_feature_used;
		}

		// Merge early attributes with block feature used from missed modules.
		return array_replace_recursive( $this->detection_state->early_attributes, $this->detection_state->block_feature_used );
	}

	/**
	 * Merge preset features with existing features.
	 *
	 * @since ??
	 *
	 * @param array $feature_used Current feature detection results.
	 *
	 * @return array Merged feature detection results.
	 */
	private function _merge_preset_features( array $feature_used ): array {
		// Only merge if we have preset features detected.
		if ( empty( $this->feature_state->presets_feature_used ) ) {
			return $feature_used;
		}

		// Merge preset features into feature_used.
		// Preset features were already detected during early detection and stored in presets_feature_used.
		// Filter out empty arrays (features not found in presets) to avoid overwriting content-detected features.
		// Only merge meaningful (non-empty) preset features to ensure they're included in the cache.
		$non_empty_preset_features = DynamicAssetsUtils::filter_meaningful_features( $this->feature_state->presets_feature_used );
		if ( ! empty( $non_empty_preset_features ) ) {
			$feature_used = array_replace_recursive( $feature_used, $non_empty_preset_features );
		}

		// If preset features contain meaningful features, we need to generate late assets.
		if ( ! $this->detection_state->need_late_generation ) {
			$meaningful_features = DynamicAssetsUtils::filter_meaningful_features( $this->feature_state->presets_feature_used );
			if ( ! empty( $meaningful_features ) ) {
				$this->detection_state->need_late_generation = true;
			}
		}

		return $feature_used;
	}

	/**
	 * Detect preset features based on content and detection state.
	 *
	 * @since ??
	 *
	 * @param bool        $has_missed_modules           Whether missed modules were detected.
	 * @param bool        $has_new_block_instances      Whether new block instances were found.
	 * @param bool        $has_new_shortcode_instances  Whether new shortcode instances were found.
	 * @param string|null $early_content                 Early content (may be null, will be fetched if needed).
	 *
	 * @return void
	 */
	private function _detect_preset_features( bool $has_missed_modules, bool $has_new_block_instances, bool $has_new_shortcode_instances, ?string $early_content ): void {
		// Detect from late block content if we have new block instances.
		// This is important because late content may contain presets that weren't in early content.
		if ( ! empty( $this->detection_state->late_block_content ) && $has_new_block_instances ) {
			$late_preset_features = $this->presets_feature_used( $this->detection_state->late_block_content );

			// Merge with existing preset features if they exist.
			if ( ! empty( $this->feature_state->presets_feature_used ) ) {
				$this->feature_state->presets_feature_used = array_replace_recursive( $this->feature_state->presets_feature_used, $late_preset_features );
			} else {
				$this->feature_state->presets_feature_used = $late_preset_features;
			}
		}

		// Detect from late shortcode content if we have new shortcode instances.
		// This is important because late content may contain presets that weren't in early content.
		if ( ! empty( $this->detection_state->late_shortcode_content ) && $has_new_shortcode_instances ) {
			$late_shortcode_preset_features = $this->presets_feature_used( $this->detection_state->late_shortcode_content );

			// Merge with existing preset features if they exist.
			if ( ! empty( $this->feature_state->presets_feature_used ) ) {
				$this->feature_state->presets_feature_used = array_replace_recursive( $this->feature_state->presets_feature_used, $late_shortcode_preset_features );
			} else {
				$this->feature_state->presets_feature_used = $late_shortcode_preset_features;
			}
		}

		// Skip if preset features already detected (from early detection or late content above).
		if ( ! empty( $this->feature_state->presets_feature_used ) ) {
			return;
		}

		// Detect from early content if we have missed modules or no late content.
		if ( $has_missed_modules || ( empty( $this->detection_state->late_block_content ) && empty( $this->detection_state->late_shortcode_content ) ) ) {
			if ( null === $early_content ) {
				$early_content = $this->_get_all_content();
			}
			if ( ! empty( $early_content ) ) {
				$this->feature_state->presets_feature_used = $this->presets_feature_used( $early_content );
			}
		}
	}

	/**
	 * Process feature_used array into _late_use_* flags.
	 *
	 * @since ??
	 *
	 * @param array $feature_used Feature detection results.
	 *
	 * @return void
	 */
	private function _process_feature_used_to_late_flags( array $feature_used ): void {
		if ( empty( $feature_used ) ) {
			return;
		}

		foreach ( $feature_used as $attribute => $value ) {
			switch ( $attribute ) {
				case 'animation_style':
					$this->feature_state->late_use_animation_style = ! empty( $value );
					break;

				case 'excerpt_content_on':
					$this->feature_state->late_show_content = ! empty( $value );
					break;

				case 'block_mode_blog':
					$this->feature_state->late_use_block_mode_blog = ! empty( $value );
					break;

				case 'gutter_widths':
					$this->feature_state->late_gutter_width = ! empty( $value ) ? array_map( 'intval', $value ) : [];
					break;

				case 'link_enabled':
					$this->feature_state->late_use_link = ! empty( $value );
					break;

				case 'specialty_section':
					$this->feature_state->late_use_specialty = ! empty( $value );
					break;

				case 'sticky_position_enabled':
					$this->feature_state->late_use_sticky = ! empty( $value );
					break;

				case 'scroll_effects_enabled':
					$this->feature_state->late_use_motion_effect = ! empty( $value );
					break;

				case 'icon_font_divi':
					$this->feature_state->late_custom_icon = ! empty( $value );
					break;

				case 'social_follow_icon_font_divi':
					$this->feature_state->late_social_icon = ! empty( $value );
					break;

				case 'icon_font_fa':
				case 'social_follow_icon_font_fa':
					$this->feature_state->late_fa_icon = ! empty( $value );
					break;

				case 'lightbox':
					$this->feature_state->late_show_in_lightbox = ! empty( $value );
					break;
			}
		}
	}

	/**
	 * Merge unique feature values into an array.
	 *
	 * Helper method to reduce duplication when merging feature detection results.
	 *
	 * @since ??
	 *
	 * @param array $existing   Existing feature values.
	 * @param mixed $new_value   New feature value(s) to merge.
	 *
	 * @return array Merged unique values.
	 */
	public function merge_unique_features( array $existing, $new_value ): array {
		if ( is_bool( $new_value ) ) {
			$existing[] = $new_value;
			return array_unique( array_filter( $existing ) );
		}

		if ( is_array( $new_value ) ) {
			return array_unique( array_merge( $existing, $new_value ) );
		}

		return $existing;
	}

	/**
	 * Detect preset feature use.
	 *
	 * @since ??
	 *
	 * @param string $content Content to analyze for preset features.
	 *
	 * @return array Preset features detected.
	 */
	public function presets_feature_used( string $content ): array {
		// Create a unique key for/using the given arguments.
		$key = md5( $content );

		// Return cached value if available.
		if ( isset( $this->_preset_feature_used_cache[ $key ] ) ) {
			return $this->_preset_feature_used_cache[ $key ];
		}

		// If early_attributes was loaded from postmeta cache, preset features were already processed.
		if ( $this->_should_skip_detection() ) {
			$this->_preset_feature_used_cache[ $key ] = [];
			return $this->_preset_feature_used_cache[ $key ];
		}

		$preset_content = '';

		if ( $this->detection_state->options['has_block'] ) {
			// Get module and group preset IDs separately.
			$module_preset_ids = DetectFeature::get_block_preset_ids( $content );
			$group_preset_ids  = DetectFeature::get_group_preset_ids( $content );

			// Get attributes from both preset types.
			$module_attrs = DynamicAssetsUtils::get_module_preset_attributes( $module_preset_ids );
			$group_attrs  = DynamicAssetsUtils::get_group_preset_attributes( $group_preset_ids );

			$combined_attrs = array_merge( $module_attrs, $group_attrs );

			if ( ! empty( $combined_attrs ) ) {
				// Merge all preset attribute arrays into a single flat object for feature detection.
				// This ensures the JSON format matches what detection regex expects.
				$merged_preset_attrs = [];
				foreach ( $combined_attrs as $attr_set ) {
					$merged_preset_attrs = array_replace_recursive( $merged_preset_attrs, $attr_set );
				}
				$preset_content = wp_json_encode( $merged_preset_attrs );
			}
		}

		if ( $this->detection_state->options['has_shortcode'] ) {
			$shortcode_preset_ids         = DetectFeature::get_shortcode_preset_ids( $content );
			$shortcode_presets_attributes = DynamicAssetsUtils::get_shortcode_preset_attributes( $shortcode_preset_ids );

			if ( ! empty( $shortcode_presets_attributes ) ) {
				foreach ( $shortcode_presets_attributes as $attribute_group ) {
					// Add `[` at the start as a proxy to shortcode format.
					$shortcode_preset_content = ' [';

					foreach ( $attribute_group as $name => $value ) {
						$shortcode_preset_content = $shortcode_preset_content . ' ' . $name . '="' . $value . '" ';
					}

					// Add `]` at the end as a proxy to shortcode format.
					$preset_content = $preset_content . $shortcode_preset_content . ' ]';
				}
			}
		}

		// Cache the results.
		$this->_preset_feature_used_cache[ $key ] = $this->detect_preset_feature_use( $preset_content );

		return $this->_preset_feature_used_cache[ $key ];
	}

	/**
	 * Detect feature use in the content.
	 *
	 * @since ??
	 *
	 * @param string $preset_content Block Attribute string / Shortcode string.
	 *
	 * @return array Detected features.
	 *
	 * @throws InvalidArgumentException In case the callback is not a callable function.
	 */
	public function detect_preset_feature_use( string $preset_content ): array {
		// Detect feature use in the preset content.
		$feature_detection_map = DynamicAssetsUtils::get_feature_detection_map( $this->detection_state->options );
		$preset_feature_used   = [];

		foreach ( $feature_detection_map as $name => $params ) {
			if ( ! isset( $preset_feature_used[ $name ] ) ) {
				$preset_feature_used[ $name ] = [];
			}

			// Check cache first - if feature is already detected as true in page content, skip preset detection.
			// For boolean features: if already true, no need to check presets.
			// For array features: always check presets to merge values.
			// Note: If postmeta cache exists and there are no missed modules, this function shouldn't be called
			// (presets_feature_used() handles that case). This check is for when we do need to detect presets.
			if ( is_array( $this->detection_state->early_attributes ) && isset( $this->detection_state->early_attributes[ $name ] ) ) {
				$cached_value = $this->detection_state->early_attributes[ $name ];

				// If it's a boolean feature stored as array with single true value, skip detection.
				if ( is_array( $cached_value ) && 1 === count( $cached_value ) && true === reset( $cached_value ) ) {
					// Feature already detected as true in page content, skip preset detection.
					$preset_feature_used[ $name ] = $cached_value;
					continue;
				}
				// Otherwise: false boolean features or array features - continue to detect and merge.
				// This handles cases where we have missed modules (new content detected) and need to check presets.
			}
			// Run detection on preset content.
			// Get feature detection data from the provided functions.
			if ( is_callable( $params['callback'] ) ) {
				// Build arguments array for the callback.
				// First argument is always the content, followed by additional args.
				$callback_args = [ $preset_content ];
				if ( ! empty( $params['additional_args'] ) ) {
					$callback_args = array_merge( $callback_args, array_values( $params['additional_args'] ) );
				}

				$result = call_user_func_array(
					$params['callback'],
					$callback_args
				);

				$preset_feature_used[ $name ] = $this->merge_unique_features( $preset_feature_used[ $name ], $result );
			} else {
				throw new InvalidArgumentException( 'The argument must be a callable function' );
			}
		}

		return $preset_feature_used;
	}

	/**
	 * Detect attribute feature use.
	 *
	 * @since ??
	 *
	 * @param string $content Content to analyze.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException If callback is not callable.
	 */
	public function detect_attribute_feature_use( string $content ): void {
		if ( empty( $content ) ) {
			return;
		}

		// If early_attributes was loaded from postmeta cache, all features are already cached - skip detection.
		if ( $this->_should_skip_detection() ) {
			return;
		}

		$feature_detection_map = DynamicAssetsUtils::get_feature_detection_map( $this->detection_state->options );

		foreach ( $feature_detection_map as $name => $params ) {
			if ( ! isset( $this->detection_state->block_feature_used[ $name ] ) ) {
				$this->detection_state->block_feature_used[ $name ] = [];
			}

			// Performance optimization: Skip if already detected as true.
			if ( ! empty( $this->detection_state->block_feature_used[ $name ] ) && in_array( true, $this->detection_state->block_feature_used[ $name ], true ) ) {
				continue;
			}

			// Check cache first using helper method.
			$cached_result = $this->get_and_apply_cached_feature( $name, $this->detection_state->block_feature_used[ $name ] );
			if ( null !== $cached_result ) {
				$this->detection_state->block_feature_used[ $name ] = $cached_result;
				continue;
			}

			// Get feature detection data from the provided functions.
			if ( is_callable( $params['callback'] ) ) {
				$result = call_user_func_array(
					$params['callback'],
					array_merge(
						[ 'content' => $content ],
						$params['additional_args']
					)
				);

				$this->detection_state->block_feature_used[ $name ] = $this->merge_unique_features( $this->detection_state->block_feature_used[ $name ], $result );
			} else {
				throw new InvalidArgumentException( 'The argument must be a callable function' );
			}
		}
	}

	/**
	 * Log the Block name.
	 *
	 * @since ??
	 *
	 * @param mixed $parsed_block Parsed Block.
	 *
	 * @return mixed
	 */
	public function log_block_used( $parsed_block ) {
		if ( ! DynamicAssetsUtils::should_generate_dynamic_assets() || ! DynamicAssetsUtils::should_run_detection() ) {
			return $parsed_block;
		}

		// If no `parentId` is found, this block isn't Divi 5 module thus it can be skipped.
		if ( empty( $parsed_block['parentId'] ) ) {
			return $parsed_block;
		}

		// Module name.
		$block_name = $parsed_block['blockName'];

		// Log the block tags used.
		if ( in_array( $block_name, $this->detection_state->verified_blocks, true ) ) {

			// Log the block used.
			if ( ! in_array( $block_name, $this->detection_state->blocks_used, true ) ) {
				$this->detection_state->blocks_used[]        = $block_name;
				$this->detection_state->options['has_block'] = true;
			}

			// Get block attributes for both early and late detection.
			$block_attrs = $parsed_block['attrs'] ?? [];

			// Only store block content for late detection if early detection is complete.
			if ( $this->detection_state->early_detection_complete ) {
				// Check if we're currently rendering a Theme Builder template.
				// Theme Builder templates are re-rendered during late detection, but we already
				// detected their content during early detection, so we skip them here.
				$is_from_theme_builder = $this->_is_theme_builder_template();

				// Track this block type as rendered late.
				if ( ! in_array( $block_name, $this->detection_state->late_blocks, true ) ) {
					$this->detection_state->late_blocks[] = $block_name;
				}

				// Only add to late_block_content if it's NOT from Theme Builder.
				// Theme Builder blocks are already detected during early detection.
				// We only want to capture genuinely new content (widgets, plugins, etc.).
				if ( ! $is_from_theme_builder ) {
					// Store serialized block content for late preset detection.
					$block_inner_content = '';
					$serialized_block    = get_comment_delimited_block_content( $block_name, $block_attrs, $block_inner_content );
					$this->_append_unique_detection_payload( $serialized_block, 'late_block_content', '_seen_late_block_payloads' );

					// Accumulate late block attributes for late feature detection.
					$encoded_block_attrs = wp_json_encode( $block_attrs );
					if ( false !== $encoded_block_attrs ) {
						$this->_append_unique_detection_payload( $encoded_block_attrs, 'attribute_used', '_seen_block_attribute_payloads' );
					}
				}
			}
		}

		return $parsed_block;
	}

	/**
	 * Log the Shortcode Tag/Slug.
	 *
	 * @since  ??
	 * @access public
	 *
	 * @param mixed  $override Whether to override do_shortcode return value or not.
	 * @param string $tag      Shortcode tag.
	 * @param array  $attrs    Shortcode attrs.
	 * @param array  $m        Shortcode match array.
	 *
	 * @return mixed
	 */
	public function log_shortcode_used( $override, string $tag, array $attrs, array $m ) {
		if ( ! DynamicAssetsUtils::should_generate_dynamic_assets() || ! DynamicAssetsUtils::should_run_detection() ) {
			return $override;
		}

		if ( in_array( $tag, $this->detection_state->verified_shortcodes, true ) ) {
			// Log the shortcode tags used.
			if ( ! in_array( $tag, $this->detection_state->shortcode_used, true ) ) {
				$this->detection_state->shortcode_used[]         = $tag;
				$this->detection_state->options['has_shortcode'] = true;
			}

			// Check for shortcode attribute that we're interested in.
			$found_interested_attribute = array_intersect( array_keys( $attrs ), $this->detection_state->interested_attrs );

			// Track shortcode content for late detection comparison (similar to blocks).
			if ( $this->detection_state->early_detection_complete ) {
				// Check if we're currently rendering a Theme Builder template.
				// Theme Builder templates are re-rendered during late detection, but we already
				// detected their content during early detection, so we skip them here.
				$is_from_theme_builder = $this->_is_theme_builder_template();

				// Only add to late_shortcode_content if it's NOT from Theme Builder.
				// Theme Builder shortcodes are already detected during early detection.
				// We only want to capture genuinely new content (widgets, plugins, etc.).
				if ( ! $is_from_theme_builder && isset( $m[0] ) ) {
					if ( $found_interested_attribute ) {
						// Set `$m[0]` for late detection, which is the shortcode string.
						$this->_append_unique_detection_payload( $m[0], 'attribute_used', '_seen_shortcode_attribute_payloads' );
					}

					// Store shortcode string for late preset detection.
					$this->_append_unique_detection_payload( $m[0], 'late_shortcode_content', '_seen_late_shortcode_payloads' );
				}
			}
		}

		return $override;
	}

	/**
	 * Check for available attributes.
	 *
	 * @since ??
	 *
	 * @param string $feature Feature to check.
	 * @param string $content Content to search for feature in.
	 *
	 * @return bool True if attribute exists.
	 */
	public function check_if_attribute_exits( string $feature, string $content ): bool {
		$feature_detection_map = DynamicAssetsUtils::get_feature_detection_map( $this->detection_state->options );

		$has_attribute = false;

		if ( isset( $feature_detection_map[ $feature ] ) ) {
			$params = $feature_detection_map[ $feature ];

			// Try to use cached value first.
			$has_attribute = $this->get_cached_or_detect_feature(
				$feature,
				$params['callback'],
				array_merge(
					[ 'content' => $content ],
					$params['additional_args']
				),
				false
			);

			// Extract boolean from array format if needed.
			if ( is_array( $has_attribute ) ) {
				$filtered      = array_filter( $has_attribute );
				$has_attribute = ! empty( $filtered );
			}
		}

		if ( $has_attribute ) {
			return true;
		}

		if ( ! empty( $this->feature_state->presets_feature_used ) ) {
			// Fallback to preset features when attribute detection failed.
			return isset( $this->feature_state->presets_feature_used[ $feature ] ) && ! empty( $this->feature_state->presets_feature_used[ $feature ] );
		}

		return false;
	}

	/**
	 * Check class exists in post content.
	 *
	 * @since ??
	 *
	 * @param string $class_name CSS class to check.
	 * @param string $content     Content to search for class in.
	 *
	 * @return bool True if class exists.
	 */
	public function check_if_class_exits( string $class_name, string $content ): bool {
		return (bool) preg_match( '/class=".*' . preg_quote( $class_name, '/' ) . '/', $content );
	}

	/**
	 * Check if salvattore should be enqueued for shortcodes.
	 *
	 * @since ??
	 *
	 * @return bool True if salvattore should be enqueued.
	 */
	public function should_enqueue_salvattore_for_shortcodes(): bool {
		// Return early if no shortcodes are used.
		if ( empty( $this->detection_state->options['has_shortcode'] ) ) {
			return false;
		}

		// Get all detected shortcodes (cached).
		$all_shortcodes = $this->get_all_shortcodes();

		// Return early if no shortcodes are actually present.
		if ( empty( $all_shortcodes ) ) {
			return false;
		}

		// Shortcodes that might need salvattore (when they use block mode layout).
		$salvattore_dependent_shortcodes = [
			'et_pb_blog',
		];

		/**
		 * Filters the list of shortcodes that may depend on salvattore.
		 *
		 * Allows third-party developers to add their custom shortcodes that use salvattore.
		 *
		 * @since ??
		 *
		 * @param array $salvattore_dependent_shortcodes Array of shortcode tags that may need salvattore.
		 * @param array $all_shortcodes                  All shortcodes detected on the page.
		 */
		$salvattore_dependent_shortcodes = apply_filters(
			'divi_frontend_assets_dynamic_assets_salvattore_dependent_shortcodes',
			$salvattore_dependent_shortcodes,
			$all_shortcodes
		);

		// Check if any salvattore-dependent shortcodes are present.
		$has_salvattore_dependent_shortcode = ! empty( array_intersect( $all_shortcodes, $salvattore_dependent_shortcodes ) );

		if ( ! $has_salvattore_dependent_shortcode ) {
			return false;
		}

		// Check if any of the salvattore-dependent shortcodes use block mode layout.
		$content = $this->_get_all_content();

		return DetectFeature::has_block_layout_enabled( $content, $this->detection_state->options );
	}
}
