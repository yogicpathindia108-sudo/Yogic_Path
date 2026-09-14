<?php
/**
 * Video: VideoUtils.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Video;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\FrontEnd\Assets\CriticalCSS;

/**
 * Video utility functions.
 *
 * @since ??
 */
class VideoUtils {

	/**
	 * Defer video loading by replacing src with data-src in video elements if module is below the fold.
	 *
	 * This function checks if Critical CSS is enabled and if the current module is below the fold.
	 * If so, it replaces `src` attributes with `data-src` attributes in:
	 * - `<source>` tags (for regular video files: MP4, WebM)
	 * - `<iframe>` tags (for oEmbed videos: YouTube, Vimeo, etc.)
	 *
	 * @since ??
	 *
	 * @param string $video_html The video HTML markup to process.
	 *
	 * @return string The processed video HTML with deferred loading if below the fold.
	 */
	public static function maybe_defer_video_loading( string $video_html ): string {
		// Early return if video HTML is empty.
		if ( empty( $video_html ) ) {
			return $video_html;
		}

		// Check if Critical CSS is enabled and module is below the fold.
		$is_below_the_fold = false;
		if ( CriticalCSS::should_generate_critical_css() ) {
			$is_below_the_fold = CriticalCSS::is_current_module_below_fold_for_lazy_load();
		}

		// If not below the fold, return original HTML.
		if ( ! $is_below_the_fold ) {
			return $video_html;
		}

		// Replace src="..." with data-src="..." in <source> tags (for regular video files).
		// Pattern matches: <source ... src="..." ...>
		// Preserves other attributes and whitespace.
		$deferred_html = preg_replace(
			'/(<source[^>]*\s)src\s*=\s*["\']([^"\']+)["\']/i',
			'$1data-src="$2"',
			$video_html
		);

		// Replace src="..." with data-src="..." in <iframe> tags (for oEmbed videos like YouTube/Vimeo).
		// Pattern matches: <iframe ... src="..." ...>
		// Preserves other attributes and whitespace.
		$deferred_html = preg_replace(
			'/(<iframe[^>]*\s)src\s*=\s*["\']([^"\']+)["\']/i',
			'$1data-src="$2"',
			$deferred_html
		);

		return $deferred_html;
	}
}
