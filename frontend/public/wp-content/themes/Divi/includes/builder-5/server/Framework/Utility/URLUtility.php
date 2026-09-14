<?php
/**
 * URL utility class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Framework\Utility;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * URLUtility class.
 *
 * This class contains methods to work with request and site URLs.
 *
 * @since ??
 */
class URLUtility {

	/**
	 * Canonical absolute URL for the current frontend HTTP request.
	 *
	 * `REQUEST_URI` is rooted at the web server and repeats the WordPress home path on
	 * subdirectory installs; passing it to `home_url()` duplicates that segment. This builds
	 * `home_url`-relative path and query instead.
	 *
	 * @since ??
	 *
	 * @return string Absolute URL for the active request.
	 */
	public static function get_canonical_current_request_url(): string {
		$request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );

		if ( '' === $request_uri ) {
			return home_url( '/' );
		}

		$parsed = wp_parse_url( $request_uri );
		$path   = isset( $parsed['path'] ) ? $parsed['path'] : '/';
		$query  = isset( $parsed['query'] ) ? $parsed['query'] : '';

		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		if ( is_string( $home_path ) && '' !== $home_path && '/' !== $home_path ) {
			$normalized_base = untrailingslashit( $home_path );

			if ( str_starts_with( $path, $normalized_base . '/' ) ) {
				$path = substr( $path, strlen( $normalized_base ) );
			} elseif ( $normalized_base === $path || $normalized_base . '/' === $path ) {
				$path = '/';
			}

			if ( '' === $path ) {
				$path = '/';
			}
		}

		if ( '/' === $path || '' === $path ) {
			$url = home_url( '/' );
		} else {
			$url = home_url( '/' . ltrim( $path, '/' ) );
		}

		if ( '' !== $query ) {
			// Preserve the raw query string. Rebuilding via parse_str() + add_query_arg()
			// corrupts values that contain `=` (for example `>=`, `<=`, and `!=` compare operators).
			$url .= '?' . $query;
		}

		return $url;
	}
}
