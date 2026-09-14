<?php
/**
 * Portfolio Utils Class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleUtils;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * ImageUtils class.
 *
 * This class provides utility methods for grid-based modules with flexbox layouts.
 * Used by Portfolio, Filterable Portfolio, Blog, and Gallery modules.
 *
 * @since ??
 */
class ImageUtils {

	/**
	 * Parse flexType value to extract column fraction.
	 *
	 * This utility function is used by Blog, Gallery, Portfolio and Filterable Portfolio modules
	 * to determine the column fraction from flexType values like "12_24", "6_24", "24_24".
	 *
	 * @since ??
	 *
	 * @param string $flex_type FlexType value like "12_24", "6_24", "24_24".
	 *
	 * @return float Column fraction (0.0 to 1.0). Returns 1.0 on failure.
	 *
	 * @example:
	 * ```php
	 * $fraction = PortfolioUtils::parse_flex_type('12_24');
	 * // Returns: 0.5 (50% width)
	 *
	 * $fraction = PortfolioUtils::parse_flex_type('6_24');
	 * // Returns: 0.25 (25% width)
	 *
	 * $fraction = PortfolioUtils::parse_flex_type('invalid');
	 * // Returns: 1.0 (fallback to full width)
	 * ```
	 */
	public static function parse_flex_type( string $flex_type ): float {
		// Handle empty or invalid input.
		if ( empty( $flex_type ) || ! is_string( $flex_type ) ) {
			return 1.0;
		}

		// Parse flexType format: "numerator_denominator".
		if ( preg_match( '/^(\d+)_(\d+)$/', $flex_type, $matches ) ) {
			$numerator   = (int) $matches[1];
			$denominator = (int) $matches[2];

			// Prevent division by zero.
			if ( 0 === $denominator ) {
				return 1.0;
			}

			// Calculate and clamp fraction between 0.0 and 1.0.
			$fraction = $numerator / $denominator;
			return max( 0.0, min( 1.0, $fraction ) );
		}

		// Return full width for unrecognized formats.
		return 1.0;
	}

	/**
	 * Select optimal image size based on layout and responsive column configuration.
	 *
	 * This utility function determines the best WordPress image size to use for grid-based modules
	 * based on layout type (fullwidth vs grid) and responsive column settings.
	 *
	 * The logic prioritizes image quality by using large images if tablet or desktop need them,
	 * and only uses small images when both tablet and desktop have sufficiently small columns.
	 * Mobile phones always use small images since the screen size makes them sufficient.
	 *
	 * @since ??
	 *
	 * @param array  $attrs           Module attributes containing layout and flexType configuration.
	 * @param string $layout          Layout type ('fullwidth' or 'grid').
	 * @param string $grid_attr_path  Grid attribute path (e.g., 'portfolioGrid' or 'blogGrid').
	 *
	 * @return string WordPress image size name to use.
	 */
	public static function select_optimal_image_size( array $attrs, string $layout, string $grid_attr_path = 'portfolioGrid' ): string {
		// Check Layout Style (display property) instead of deprecated layout option.
		// If using CSS Grid layout, use smaller thumbnail size.
		$layout_display = $attrs[ $grid_attr_path ]['decoration']['layout']['desktop']['value']['display'] ?? '';
		if ( 'grid' === $layout_display ) {
			return self::_get_small_image_size( $grid_attr_path );
		}

		// If no attributes provided, use default grid image.
		if ( empty( $attrs ) ) {
			return self::_get_small_image_size( $grid_attr_path );
		}

		// For flex/block layouts - check all breakpoints with device-specific thresholds.
		$flex_type_attr = $attrs[ $grid_attr_path ]['advanced']['flexType'] ?? [];

		// Device-specific thresholds for large image usage.
		// Note: We skip phone breakpoint since small images are sufficient on mobile regardless of columns.
		$breakpoint_thresholds = [
			'tablet'  => 0.5, // Use large image if column > 1/2 (2/3, 1/1).
			'desktop' => 0.34, // Use large image if column > 1/3 (1/2, 2/3, 1/1).
		];

		// Check tablet and desktop breakpoints - use large image if any needs it.
		foreach ( [ 'tablet', 'desktop' ] as $breakpoint ) {
			$flex_type = $flex_type_attr[ $breakpoint ]['value'] ?? '';
			if ( ! empty( $flex_type ) ) {
				$column_fraction = self::parse_flex_type( $flex_type );
				$threshold       = $breakpoint_thresholds[ $breakpoint ];

				// If column fraction is greater than or equal to threshold, use large image.
				if ( $column_fraction > $threshold ) {
					return 'et-pb-post-main-image-fullwidth'; // 1080×675.
				}
			}
		}

		// Use small image only if both tablet and desktop have many small columns.
		return self::_get_small_image_size( $grid_attr_path );
	}

	/**
	 * Small thumbnail size for grid-based modules.
	 *
	 * Blog uses 400×250; Portfolio/Gallery use 400×284.
	 *
	 * @since ??
	 *
	 * @param string $grid_attr_path Grid attribute path.
	 *
	 * @return string WordPress image size name.
	 */
	private static function _get_small_image_size( string $grid_attr_path ): string {
		if ( 'blogGrid' === $grid_attr_path ) {
			return 'et-pb-post-main-image'; // 400×250.
		}

		return 'et-pb-portfolio-image'; // 400×284.
	}

	/**
	 * Check if a URL or file path has a specific file extension.
	 *
	 * Handles query strings and fragments automatically by removing them before
	 * checking the extension. This matches the D5 TypeScript implementation
	 * and improves upon D4's PHP pathinfo() approach which fails with query params.
	 *
	 * The D4 approach using pathinfo() has issues:
	 * - pathinfo('image.svg?v=1.2.3') returns extension 'svg?v=1' (wrong!),
	 * - pathinfo('image.svg#anchor') returns extension 'svg#anchor' (wrong!),
	 * - pathinfo('image.svg?ver=1.2.3') fails due to dots in query params.
	 *
	 * This D5 implementation properly strips query strings and fragments first,
	 * matching the behavior of the TypeScript isFileExtension utility in the
	 * divi/module-utils package.
	 *
	 * @since ??
	 *
	 * @param string $url       The file path or URL to check.
	 * @param string $extension The file extension to check for (without the dot).
	 *
	 * @return bool True if the URL has the specified extension, false otherwise.
	 *
	 * @example
	 * ```php
	 * ImageUtils::is_file_extension( 'image.svg', 'svg' ); // true
	 * ImageUtils::is_file_extension( 'image.svg?v=1.2.3', 'svg' ); // true (D4 pathinfo fails)
	 * ImageUtils::is_file_extension( 'image.svg#anchor', 'svg' ); // true (D4 pathinfo fails)
	 * ImageUtils::is_file_extension( 'image.png', 'svg' ); // false
	 * ImageUtils::is_file_extension( 'my.image.svg', 'svg' ); // true
	 * ImageUtils::is_file_extension( '', 'svg' ); // false
	 * ```
	 */
	public static function is_file_extension( string $url, string $extension ): bool {
		if ( empty( $url ) ) {
			return false;
		}

		// Remove query string and fragment first to avoid issues with dots in parameters.
		$clean_url = explode( '?', $url )[0];
		$clean_url = explode( '#', $clean_url )[0];

		$parts = explode( '.', $clean_url );

		if ( count( $parts ) < 2 ) {
			return false; // No extension.
		}

		// Get the last element without modifying the array's internal pointer.
		$actual_extension = $parts[ count( $parts ) - 1 ];

		// Case-insensitive comparison to handle uppercase extensions from cameras/Windows systems.
		return strtolower( $actual_extension ) === strtolower( $extension );
	}

	/**
	 * Check if a URL has an image or video file extension.
	 *
	 * Uses self::is_file_extension() to properly handle query strings and fragments.
	 * Checks against all supported image and video extensions used in Divi portability.
	 * The extension list matches HooksRegistration::check_filetype_and_ext_image().
	 *
	 * Note: This method includes all supported formats regardless of site-specific mime type
	 * restrictions. For import validation, use HooksRegistration::check_filetype_and_ext_image()
	 * which respects allowed mime types.
	 *
	 * @since ??
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool True if the URL has an image or video extension, false otherwise.
	 *
	 * @example
	 * ```php
	 * ImageUtils::is_image_or_video_url( 'image.jpg' ); // true
	 * ImageUtils::is_image_or_video_url( 'video.mp4?v=1.2.3' ); // true
	 * ImageUtils::is_image_or_video_url( 'document.pdf' ); // false
	 * ImageUtils::is_image_or_video_url( 'image.svg#anchor' ); // true
	 * ```
	 */
	public static function is_image_or_video_url( string $url ): bool {
		// Supported image and video extensions from HooksRegistration::check_filetype_and_ext_image().
		$image_video_extensions = [
			// Images.
			'jpg',
			'jpeg',
			'jpe',
			'gif',
			'png',
			'bmp',
			'tiff',
			'tif',
			'webp',
			'avif',
			'ico',
			'heic',
			'svg',
			'svgz',
			// Videos.
			'mp4',
			'webm',
			'ogv',
			'avi',
			'mov',
			'wmv',
			'flv',
		];

		foreach ( $image_video_extensions as $extension ) {
			if ( self::is_file_extension( $url, $extension ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a URL has an image/video extension, or a JSON extension when requested.
	 *
	 * Uses self::is_file_extension() to properly handle query strings and fragments.
	 *
	 * @since ??
	 *
	 * @param string $url          The URL to check.
	 * @param bool   $include_json Whether to treat `.json` as transferable. Default `false`.
	 *
	 * @return bool True if the URL has a supported extension, false otherwise.
	 */
	public static function is_media_url( string $url, bool $include_json = false ): bool {
		if ( self::is_image_or_video_url( $url ) ) {
			return true;
		}

		return $include_json && self::is_file_extension( $url, 'json' );
	}
}
