<?php
/**
 * MediaUtility class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Framework\Utility;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * MediaUtility class.
 *
 * This class contains methods for WordPress media and image operations.
 *
 * @since ??
 */
class MediaUtility {

	/**
	 * Get available WordPress image sizes for dropdown options.
	 *
	 * @since ??
	 *
	 * @return array Array of image sizes with labels and values.
	 */
	public static function get_image_sizes_options(): array {
		$sizes       = [];
		$image_sizes = get_intermediate_image_sizes();

		// Add full size first.
		$sizes['full'] = esc_html__( 'Full Size (Original)', 'et_builder_5' );

		// Add intermediate sizes.
		foreach ( $image_sizes as $size ) {
			$size_data = wp_get_additional_image_sizes()[ $size ] ?? [];

			if ( ! empty( $size_data ) ) {
				$width  = $size_data['width'] ?? 0;
				$height = $size_data['height'] ?? 0;
				$label  = ucfirst( str_replace( '_', ' ', $size ) );

				if ( $width && $height ) {
					$sizes[ $size ] = sprintf( '%s (%dx%d)', $label, $width, $height );
				} else {
					$sizes[ $size ] = $label;
				}
			} else {
				// For default WordPress sizes.
				switch ( $size ) {
					case 'thumbnail':
						$width          = get_option( 'thumbnail_size_w', 150 );
						$height         = get_option( 'thumbnail_size_h', 150 );
						$sizes[ $size ] = sprintf( '%s (%dx%d)', esc_html__( 'Thumbnail', 'et_builder_5' ), $width, $height );
						break;
					case 'medium':
						$width          = get_option( 'medium_size_w', 300 );
						$height         = get_option( 'medium_size_h', 300 );
						$sizes[ $size ] = sprintf( '%s (%dx%d)', esc_html__( 'Medium', 'et_builder_5' ), $width, $height );
						break;
					case 'large':
						$width          = get_option( 'large_size_w', 1024 );
						$height         = get_option( 'large_size_h', 1024 );
						$sizes[ $size ] = sprintf( '%s (%dx%d)', esc_html__( 'Large', 'et_builder_5' ), $width, $height );
						break;
					default:
						$sizes[ $size ] = ucfirst( str_replace( '_', ' ', $size ) );
				}
			}
		}

		return $sizes;
	}

	/**
	 * Replace YouTube video thumbnails to high resolution if the high resolution image exists.
	 *
	 * Based on legacy: et_pb_set_video_oembed_thumbnail_resolution() function.
	 *
	 * @since ??
	 *
	 * @param string|null $image_src  Thumbnail image src.
	 * @param string      $resolution Thumbnail image resolutions.
	 *
	 * @return string
	 */
	public static function set_video_oembed_thumbnail_resolution( $image_src, string $resolution = 'default' ): string {
		if ( empty( $image_src ) ) {
			return '';
		}

		// Replace YouTube video thumbnails to high resolution if the high-resolution image exists.
		if ( 'high' === $resolution && str_contains( $image_src, 'hqdefault.jpg' ) ) {
			$cache = \ET_Core_Cache_File::get( 'video_oembed_thumbnail_resolution' );

			if ( isset( $cache[ $image_src ] ) ) {
				return (string) $cache[ $image_src ];
			}

			$host = wp_parse_url( (string) $image_src, PHP_URL_HOST );

			// If the host is not set, it's not a valid remote YouTube URL.
			if ( empty( $host ) ) {
				$cache[ $image_src ] = $image_src;
				\ET_Core_Cache_File::set( 'video_oembed_thumbnail_resolution', $cache );
				return (string) $image_src;
			}

			// We only want to process YouTube thumbnail URLs.
			$youtube_hosts = [
				'i.ytimg.com',
				'img.youtube.com',
				'i1.ytimg.com',
				'i2.ytimg.com',
				'i3.ytimg.com',
				'i4.ytimg.com',
				'i5.ytimg.com',
			];

			if ( ! in_array( $host, $youtube_hosts, true ) ) {
				$cache[ $image_src ] = $image_src;
				\ET_Core_Cache_File::set( 'video_oembed_thumbnail_resolution', $cache );
				return (string) $image_src;
			}

			$high_res_image_src  = str_replace( 'hqdefault.jpg', 'maxresdefault.jpg', (string) $image_src );
			$protocol            = is_ssl() ? 'https://' : 'http://';
			$processed_image_url = $high_res_image_src;

			if ( str_starts_with( $processed_image_url, '//' ) ) {
				$processed_image_url = $protocol . substr( $processed_image_url, 2 );
			} elseif ( is_ssl() && str_starts_with( $processed_image_url, 'http://' ) ) {
				$processed_image_url = 'https://' . substr( $processed_image_url, 7 );
			}

			$processed_image_url = esc_url( $processed_image_url, [ 'http', 'https' ] );
			$response            = wp_remote_get( $processed_image_url, [ 'timeout' => 30 ] );

			// YouTube doesn't guarantee that a high-res image exists for any video, so we need to check whether it
			// exists and fall back to the default image in case of error.
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				$cache[ $image_src ] = $image_src;
				\ET_Core_Cache_File::set( 'video_oembed_thumbnail_resolution', $cache );
				return (string) $image_src;
			}

			$cache[ $image_src ] = $high_res_image_src;
			\ET_Core_Cache_File::set( 'video_oembed_thumbnail_resolution', $cache );
			return $high_res_image_src;
		}

		return (string) $image_src;
	}
}
