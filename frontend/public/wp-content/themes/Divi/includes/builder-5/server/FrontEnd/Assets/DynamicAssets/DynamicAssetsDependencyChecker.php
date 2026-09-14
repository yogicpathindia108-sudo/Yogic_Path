<?php
/**
 * Dynamic Assets Dependency Checker.
 *
 * Handles dependency checking logic for determining when assets should be enqueued.
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\FrontEnd\Assets\DynamicAssets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use ET\Builder\FrontEnd\Assets\DynamicAssets\State\CacheState;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;

/**
 * Dynamic Assets Dependency Checker class.
 *
 * Handles dependency checking logic for determining when assets should be enqueued.
 *
 * @since ??
 */
class DynamicAssetsDependencyChecker {

	/**
	 * Cache state container.
	 *
	 * @var CacheState
	 */
	private CacheState $cache_state;

	/**
	 * Constructor.
	 *
	 * @since ??
	 *
	 * @param CacheState $cache_state Cache state container.
	 */
	public function __construct( CacheState $cache_state ) {
		$this->cache_state = $cache_state;
	}

	/**
	 * Check if the current post could require assets for post formats.
	 * For example, audio scripts and css could be required on a post using
	 * the audio post format, as well as on index pages where posts with the
	 * post format may be listed.
	 *
	 * @since ??
	 *
	 * @param string $format Post format to check for, such as "audio" or "video".
	 *
	 * @return bool True if assets for this post format should be enqueued.
	 */
	public function check_post_format_dependency( string $format = 'standard' ): bool {
		// If this is a category page with posts, return true.
		// We don't know what post formats might show up in the list.
		if ( ( is_home() && ! is_front_page() ) || ! is_singular() ) {
			return true;
		}

		// If this is a single post with the builder disabled and the post format in question, return true.
		if ( is_single() && ! $this->cache_state->page_builder_used && has_post_format( $format ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if the current post is a non-builder post that matches a specific post type.
	 *
	 * @since ??
	 *
	 * @param string $type Post type to check for, such "product".
	 *
	 * @return bool True if assets for this post type should be enqueued.
	 */
	public function check_post_type_dependency( string $type = 'post' ): bool {
		// If this is a category page with posts, return true.
		// We don't know what post formats might show up in the list.
		if ( is_singular( $type ) && ! $this->cache_state->page_builder_used ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if a list of dependencies exist in the content.
	 *
	 * @since ??
	 *
	 * @param array $needles  Shortcodes/modules to detect.
	 * @param array $haystack All blocks/modules to search in.
	 *
	 * @return bool True if any dependency is found.
	 */
	public function check_for_dependency( array $needles = [], array $haystack = [] ): bool {
		$detected = false;

		foreach ( $needles as $needle ) {
			if ( in_array( $needle, $haystack, true ) ) {
				$detected = true;
			}
		}

		return $detected;
	}

	/**
	 * Check if script should be enqueued based on flag or disable_js_on_demand setting.
	 *
	 * @since ??
	 *
	 * @param bool $flag The enqueue flag to check.
	 *
	 * @return bool True if script should be enqueued.
	 */
	public function should_enqueue( bool $flag ): bool {
		return $flag || DynamicAssetsUtils::disable_js_on_demand();
	}
}
