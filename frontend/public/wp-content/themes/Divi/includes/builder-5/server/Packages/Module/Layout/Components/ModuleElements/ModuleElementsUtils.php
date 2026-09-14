<?php
/**
 * ModuleElementsUtils Class
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Packages\Module\Layout\Components\ModuleElements;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentUtils;
use ET_Builder_Post_Features;

/**
 * ModuleElementsUtils class.
 *
 * @since ??
 */
class ModuleElementsUtils {

	/**
	 * Get first non-empty nested string value by key.
	 *
	 * @since ??
	 *
	 * @param mixed  $value    Nested array-like value to search.
	 * @param string $key_name Key name to resolve.
	 *
	 * @return string
	 */
	public static function get_first_nested_string_by_key( $value, string $key_name ): string {
		if ( '' === $key_name || ! is_array( $value ) ) {
			return '';
		}

		$candidate = $value[ $key_name ] ?? null;
		if ( is_string( $candidate ) && '' !== $candidate ) {
			return $candidate;
		}

		foreach ( $value as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$resolved = self::get_first_nested_string_by_key( $item, $key_name );
			if ( '' !== $resolved ) {
				return $resolved;
			}
		}

		return '';
	}

	/**
	 * Interpolate a selector template with a value.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/InterpolateSelector interpolateSelector} in
	 * `@divi/module` packages.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $value                  The value to interpolate.
	 *     @type string|array $selectorTemplate The selector template to interpolate.
	 *     @type string $placeholder            Optional. The placeholder to replace. Default `{{selector}}`.
	 * }
	 *
	 * @return string|array The interpolated selector.
	 *                      If the selector template is a string, a string is returned.
	 *                      Otherwise an array is returned.
	 */
	public static function interpolate_selector( array $args ) {
		static $cached = null;

		$cache_key = md5( wp_json_encode( $args ) );

		if ( isset( $cached[ $cache_key ] ) ) {
			return $cached[ $cache_key ];
		}

		$value             = $args['value'];
		$selector_template = $args['selectorTemplate'];
		$placeholder       = $args['placeholder'] ?? '{{selector}}';

		if ( is_string( $selector_template ) ) {
			$cached[ $cache_key ] = str_replace( $placeholder, $value, $selector_template );

			return $cached[ $cache_key ];
		}

		$stringify_selector_template = wp_json_encode( $selector_template );

		$updated_selector_template = str_replace( $placeholder, $value, $stringify_selector_template );

		$cached[ $cache_key ] = json_decode( $updated_selector_template, true );

		return $cached[ $cache_key ];
	}

	/**
	 * Extracts the attachment URL from the image source.
	 *
	 * @since ??
	 *
	 * @param string $image_src The URL of the image attachment.
	 * @return array {
	 *    An array containing the image path without the scaling suffix and the query string,
	 *    and the scaling suffix if found.
	 *
	 *    @type string $path   The image path without the scaling suffix and query string.
	 *    @type string $suffix The scaling suffix if found. Otherwise an empty string.
	 * }
	 */
	public static function extract_attachment_url( string $image_src ): array {
		// Remove the query string from the image URL.
		list( $image_src ) = explode( '?', $image_src );

		// If the image source contains a scaling suffix, extract it.
		// The scaling suffix is in the format of "-{width}x{height}.".
		// Regex pattern test: https://regex101.com/r/USnFl3/1.
		if ( strpos( $image_src, 'x' ) && preg_match( '/-\d+x\d+\./', $image_src, $match ) ) {
			return [
				'path'   => str_replace( $match[0], '.', $image_src ),
				'suffix' => $match[0],
			];
		}

		return [
			'path'   => $image_src,
			'suffix' => '',
		];
	}

	/**
	 * Resolves a single string image URL from image inner content for responsive metadata.
	 *
	 * Top-level `src` then `url` are resolved to a string URL. String values and nested
	 * object shapes (e.g. composite `src` payloads) are supported. Non-resolvable values
	 * yield an empty string so callers never pass invalid types into URL-only APIs.
	 *
	 * @since ??
	 *
	 * @param array $image_attr_value Image attribute value for one breakpoint/state.
	 *
	 * @return string URL string, or empty string when none is valid.
	 */
	private static function _resolve_string_image_source_for_responsive_attrs( array $image_attr_value ): string {
		return self::resolve_image_src( $image_attr_value['src'] ?? $image_attr_value['url'] ?? '' );
	}

	/**
	 * Gets responsive image attributes for an image attachment.
	 *
	 * This function calculates and returns responsive image attributes such as width,
	 * height, srcset, and sizes for a given image. It uses caching to avoid repeated
	 * calculations and respects WordPress responsive images settings.
	 *
	 * @since ??
	 *
	 * @param array $image_attr_value {
	 *     An array of image attribute values.
	 *
	 *     @type string      $src Optional. The image source URL. Default empty string.
	 *     @type string      $url Optional. Alternative key for image source URL. Default empty string.
	 *     @type int|string  $id  Optional. The attachment ID. Default 0.
	 * }
	 *
	 * @return array {
	 *     An array of responsive image attributes. Returns empty array if cached data is invalid.
	 *
	 *     @type int         $id      The attachment ID.
	 *     @type array|false $meta    The attachment metadata from wp_get_attachment_metadata().
	 *     @type string      $width   Optional. The image width as a string.
	 *     @type string      $height  Optional. The image height as a string.
	 *     @type string      $srcset  Optional. The srcset attribute value for responsive images.
	 *     @type string      $sizes   Optional. The sizes attribute value for responsive images.
	 * }
	 */
	public static function get_responsive_image_attrs( array $image_attr_value ): array {
		static $is_responsive_images_enabled = null;

		if ( null === $is_responsive_images_enabled ) {
			$is_responsive_images_enabled = et_is_responsive_images_enabled();
		}

		$image_src     = self::_resolve_string_image_source_for_responsive_attrs( $image_attr_value );

		// Resolve id before URL branch so composite innerContent still merges a real attachment id (#49908).
		$attachment_id = absint( $image_attr_value['id'] ?? 0 );

		if ( $image_src ) {
			if ( ! $attachment_id ) {
				$attachment_id = self::attachment_url_to_id( $image_src );
			}
		} elseif ( $attachment_id ) {
			$attachment_url = wp_get_attachment_url( $attachment_id );
			if ( is_string( $attachment_url ) && $attachment_url ) {
				$image_src = $attachment_url;
			}
		}

		$cache_key = 'attachment_image_meta_' . $attachment_id;

		if ( $image_src ) {
			$cache_key .= '_' . md5( $image_src );
		}

		if ( $is_responsive_images_enabled ) {
			$cache_key .= '_responsive';
		} else {
			$cache_key .= '_non_responsive';
		}

		$cached_data = ET_Builder_Post_Features::instance()->get(
			// Cache key.
			$cache_key,
			// Callback function if the cache key is not found.
			function () use ( $image_src, $attachment_id, $is_responsive_images_enabled ) {
				$responsive_attrs = [
					'id' => $attachment_id,
				];

				if ( $image_src && $attachment_id ) {
					$image_meta = wp_get_attachment_metadata( $attachment_id );

					if ( $image_meta ) {
						$size_array = wp_image_src_get_dimensions( $image_src, $image_meta, $attachment_id );

						// Only proceed if the image size array is available.
						if ( $size_array ) {
							$responsive_attrs['width']  = strval( $size_array[0] );
							$responsive_attrs['height'] = strval( $size_array[1] );

							// Calculate srcset and sizes if responsive images are enabled.
							if ( $is_responsive_images_enabled ) {
								$image_srcset = wp_calculate_image_srcset( $size_array, $image_src, $image_meta, $attachment_id );

								if ( is_string( $image_srcset ) ) {
									$responsive_attrs['srcset'] = $image_srcset;
								} elseif ( $image_src && isset( $size_array[0] ) ) {
									// Fallback: WordPress may return false for small images.
									// Inject fallback srcset using current image src and dimensions.
									$responsive_attrs['srcset'] = $image_src . ' ' . $size_array[0] . 'w';
								}

								$image_sizes = wp_calculate_image_sizes( $size_array, $image_src, $image_meta, $attachment_id );

								if ( is_string( $image_sizes ) ) {
									$responsive_attrs['sizes'] = $image_sizes;
								}
							}
						} elseif ( isset( $image_meta['width'], $image_meta['height'] ) ) {
							$responsive_attrs['width']  = strval( $image_meta['width'] );
							$responsive_attrs['height'] = strval( $image_meta['height'] );

							$image_sizes = wp_calculate_image_sizes( 'full', $image_src, $image_meta, $attachment_id );

							if ( is_string( $image_sizes ) ) {
								$responsive_attrs['sizes'] = $image_sizes;
							}
						}
					}
				}

				return $responsive_attrs;
			},
			// Cache group.
			'attachment_image_meta',
			// Whether to forcefully update the cache,
			// in this case we are setting to true, because we want to update the cache,
			// even if the attachment ID is not found, so that we don't have to make the same
			// query again and again.
			true
		);

		if ( ! is_array( $cached_data ) ) {
			return [];
		}

		return $cached_data;
	}

	/**
	 * Normalize image field value (string, composite array, or nested src object) to a URL string.
	 *
	 * @since ??
	 *
	 * @param mixed $image_field_value Raw image field value from innerContent.
	 *
	 * @return string Resolved image URL string.
	 */
	public static function resolve_image_field_url_string( $image_field_value ): string {
		$resolved_url = '';

		if ( is_string( $image_field_value ) ) {
			$resolved_url = $image_field_value;
		} elseif ( is_array( $image_field_value ) ) {
			if ( isset( $image_field_value['src'] ) && is_string( $image_field_value['src'] ) ) {
				$resolved_url = $image_field_value['src'];
			} elseif ( isset( $image_field_value['src'] ) && is_array( $image_field_value['src'] ) ) {
				$resolved_url = self::resolve_image_field_url_string( $image_field_value['src'] );
			} elseif ( isset( $image_field_value['url'] ) && is_string( $image_field_value['url'] ) ) {
				$resolved_url = $image_field_value['url'];
			} else {
				$attachment_id = isset( $image_field_value['id'] ) ? absint( $image_field_value['id'] ) : 0;

				if ( $attachment_id ) {
					$attachment_url = wp_get_attachment_url( $attachment_id );
					if ( is_string( $attachment_url ) && $attachment_url ) {
						$resolved_url = $attachment_url;
					}
				}
			}
		}

		return $resolved_url;
	}

	/**
	 * Resolves image source value to a string URL.
	 *
	 * @since ??
	 *
	 * @param mixed $image_src_value Raw image source value.
	 *
	 * @return string
	 */
	private static function resolve_image_src( $image_src_value ): string {
		return self::resolve_image_field_url_string( $image_src_value );
	}

	/**
	 * Converts an attachment URL to its corresponding ID.
	 *
	 * @since ??
	 *
	 * @param string $image_src The URL of the attachment image.
	 * @return int The ID of the attachment.
	 */
	public static function attachment_url_to_id( string $image_src ): int {
		// If the image source is a data URL, return 0.
		if ( str_starts_with( $image_src, 'data:' ) ) {
			return 0;
		}

		// Get the instance of ET_Builder_Post_Features.
		$post_features = ET_Builder_Post_Features::instance();

		// Get the attachment ID from the cache.
		$attachment_id = $post_features->get(
			// Cache key.
			$image_src,
			// Callback function if the cache key is not found.
			function () use ( $image_src ) {
				$extracted_image_src = ModuleElementsUtils::extract_attachment_url( $image_src );

				// First attempt to get the attachment ID from the image source URL.
				$attachment_id = attachment_url_to_postid( $extracted_image_src['path'] );

				// If no attachment ID is found and the image source contains a scaling suffix, try to get the attachment ID from the image source with `-scaled.` suffix.
				// This could happens when the uploaded image larger than the threshold size (threshold being either width or height of 2560px), WordPress core system
				// will generate image file name with `-scaled.` suffix.
				//
				// @see https://make.wordpress.org/core/2019/10/09/introducing-handling-of-big-images-in-wordpress-5-3/
				// @see https://wordpress.org/support/topic/media-images-renamed-to-xyz-scaled-jpg/.
				if ( ! $attachment_id && $extracted_image_src['suffix'] ) {
					$attachment_id = attachment_url_to_postid( str_replace( $extracted_image_src['suffix'], '-scaled.', $image_src ) );
				}

				return $attachment_id;
			},
			// Cache group.
			'attachment_url_to_id',
			// Whether to forcefully update the cache,
			// in this case we are setting to true, because we want to update the cache,
			// even if the attachment ID is not found, so that we don't have to make the same
			// query again and again.
			true
		);

		return absint( $attachment_id );
	}

	/**
	 * Populates the image element attributes with additional information.
	 *
	 * This function takes an array of attributes and populates it with additional information
	 * related to the image element, such as the attachment ID, width, height, srcset, and sizes.
	 *
	 * @since ??
	 *
	 * @param array $attrs The array of attributes to be populated.
	 * @return array The updated array of attributes.
	 */
	public static function populate_image_element_attrs( array $attrs ): array {
		foreach ( $attrs as $breakpoint => $states ) {
			foreach ( $states as $state => $state_value ) {
				if ( ! $state_value || ! is_array( $state_value ) ) {
					continue;
				}

				$responsive_image_attrs = self::get_responsive_image_attrs( $state_value );

				if ( $responsive_image_attrs ) {
					foreach ( $responsive_image_attrs as $responsive_image_attr_key => $responsive_image_attr_value ) {
						$attrs[ $breakpoint ][ $state ][ $responsive_image_attr_key ] = $responsive_image_attr_value;
					}
				}

				if ( isset( $attrs[ $breakpoint ][ $state ]['src'] ) && ! is_string( $attrs[ $breakpoint ][ $state ]['src'] ) ) {
					$attrs[ $breakpoint ][ $state ]['src'] = '';
				}

				if ( isset( $attrs[ $breakpoint ][ $state ]['url'] ) && ! is_string( $attrs[ $breakpoint ][ $state ]['url'] ) ) {
					$attrs[ $breakpoint ][ $state ]['url'] = '';
				}
			}
		}

		return $attrs;
	}

	/**
	 * Detects if any breakpoint/state contains a featured image URL from Loop Builder.
	 *
	 * @since ??
	 *
	 * @param array $inner_content The inner content array with breakpoints and states.
	 * @return string|null The featured image URL if found, null otherwise.
	 */
	public static function detect_featured_image_url( array $inner_content ): ?string {
		global $divi_loop_image_ids;

		if ( ! isset( $divi_loop_image_ids ) || ! is_array( $divi_loop_image_ids ) ) {
			return null;
		}

		foreach ( $inner_content as $breakpoint => $states ) {
			foreach ( $states as $state => $state_value ) {
				if ( ! is_array( $state_value ) ) {
					continue;
				}

				$src_value = $state_value['value']['src'] ?? $state_value['src'] ?? '';
				if ( $src_value && isset( $divi_loop_image_ids[ esc_url( $src_value ) ] ) ) {
					return $src_value;
				}
			}
		}

		return null;
	}

	/**
	 * Populates alt and title attributes across all breakpoints and states.
	 *
	 * Alt/title attributes are not breakpoint-specific - they come from Media Library
	 * attachment metadata and are constant across all breakpoints/states.
	 *
	 * @since ??
	 *
	 * @param array  $inner_content The inner content array with breakpoints and states.
	 * @param string $alt_text      The alt text to populate (empty string if not available).
	 * @param string $title_text    The title text to populate (empty string if not available).
	 * @param bool   $has_alt_text  Whether alt text should be populated.
	 * @param bool   $has_title_text Whether title text should be populated.
	 * @param string $alt_key       Optional. The key to use for alt attribute. Default 'alt'.
	 * @param string $title_key     Optional. The key to use for title attribute. Default 'titleText'.
	 * @return array The updated inner content array.
	 */
	public static function populate_alt_title_across_breakpoints(
		array $inner_content,
		string $alt_text,
		string $title_text,
		bool $has_alt_text,
		bool $has_title_text,
		string $alt_key = 'alt',
		string $title_key = 'titleText'
	): array {
		foreach ( $inner_content as $breakpoint => $states ) {
			foreach ( $states as $state => $state_value ) {
				if ( ! is_array( $state_value ) ) {
					continue;
				}

				$has_value_key = isset( $state_value['value'] ) && is_array( $state_value['value'] );

				if ( $has_value_key ) {
					$has_alt   = ! empty( $state_value['value'][ $alt_key ] );
					$has_title = ! empty( $state_value['value'][ $title_key ] );
				} else {
					$has_alt   = ! empty( $state_value[ $alt_key ] );
					$has_title = ! empty( $state_value[ $title_key ] );
				}

				if ( ! $has_alt && $has_alt_text ) {
					if ( $has_value_key ) {
						$inner_content[ $breakpoint ][ $state ]['value'][ $alt_key ] = $alt_text;
					} else {
						$inner_content[ $breakpoint ][ $state ][ $alt_key ] = $alt_text;
					}
				}

				if ( ! $has_title && $has_title_text ) {
					if ( $has_value_key ) {
						$inner_content[ $breakpoint ][ $state ]['value'][ $title_key ] = $title_text;
					} else {
						$inner_content[ $breakpoint ][ $state ][ $title_key ] = $title_text;
					}
				}
			}
		}

		return $inner_content;
	}

	/**
	 * Gets the current post ID, handling Theme Builder context.
	 *
	 * In Theme Builder layouts, get_the_ID() returns the layout ID, not the actual post ID.
	 * This function uses ET_Post_Stack::get_main_post_id() for Theme Builder contexts.
	 *
	 * @since ??
	 *
	 * @return int The current post ID, or 0 if not available.
	 */
	private static function _get_current_post_id(): int {
		// Check if we're in Theme Builder context.
		$is_theme_builder = class_exists( '\ET_Theme_Builder_Layout' ) && \ET_Theme_Builder_Layout::is_theme_builder_layout();

		if ( $is_theme_builder && class_exists( '\ET_Post_Stack' ) ) {
			// In Theme Builder, get the main post ID (the actual post being displayed).
			$main_post_id = \ET_Post_Stack::get_main_post_id();
			if ( $main_post_id > 0 ) {
				return $main_post_id;
			}
		}

		// Standard context - use get_the_ID().
		$post_id = get_the_ID();
		return $post_id > 0 ? $post_id : 0;
	}

	/**
	 * Gets the attachment ID from inner content.
	 *
	 * Extracts the attachment ID from the first breakpoint/state that has it.
	 *
	 * @since ??
	 *
	 * @param array $inner_content The inner content array with breakpoints and states.
	 * @return int The attachment ID, or 0 if not found.
	 */
	private static function _get_attachment_id_from_inner_content( array $inner_content ): int {
		foreach ( $inner_content as $breakpoint => $states ) {
			foreach ( $states as $state => $state_value ) {
				if ( ! is_array( $state_value ) ) {
					continue;
				}

				$attachment_id = absint( $state_value['id'] ?? 0 );
				if ( $attachment_id ) {
					return $attachment_id;
				}
			}
		}

		return 0;
	}

	/**
	 * Detects if any breakpoint/state contains the post's featured image.
	 *
	 * Checks if the attachment ID in any breakpoint/state matches the current post's featured image ID.
	 * This works after `populate_image_element_attrs()` has populated the attachment ID from the resolved image URL.
	 *
	 * @since ??
	 *
	 * @param array $inner_content The inner content array with breakpoints and states.
	 * @return bool True if the image matches the post's featured image, false otherwise.
	 */
	public static function detect_post_featured_image_dynamic_content( array $inner_content ): bool {
		$post_id = self::_get_current_post_id();

		if ( ! $post_id ) {
			return false;
		}

		$featured_image_id = get_post_thumbnail_id( $post_id );

		if ( ! $featured_image_id ) {
			return false;
		}

		foreach ( $inner_content as $breakpoint => $states ) {
			foreach ( $states as $state => $state_value ) {
				if ( ! is_array( $state_value ) ) {
					continue;
				}

				$attachment_id = $state_value['id'] ?? 0;

				if ( $attachment_id && absint( $attachment_id ) === absint( $featured_image_id ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Populates image element attributes and auto-populates alt/title for post_featured_image dynamic content.
	 *
	 * This function combines populate_image_element_attrs() and populate_post_featured_image_alt_title()
	 * to populate image attributes (ID, width, height, srcset, sizes) and auto-populate alt/title
	 * from featured image Media Library metadata when using post_featured_image dynamic content.
	 *
	 * @since ??
	 *
	 * @param array  $inner_content The inner content array with breakpoints and states.
	 * @param string $alt_key       The key to use for alt attribute (typically 'alt').
	 * @param string $title_key     The key to use for title attribute ('title' or 'titleText').
	 * @return array The updated inner content array with attributes and alt/title populated.
	 */
	public static function populate_image_element_attrs_with_featured_image_alt_title( array $inner_content, string $alt_key, string $title_key ): array {
		$inner_content = self::populate_image_element_attrs( $inner_content );

		if ( ! self::detect_post_featured_image_dynamic_content( $inner_content ) ) {
			return $inner_content;
		}

		$attachment_id = self::_get_attachment_id_from_inner_content( $inner_content );

		if ( ! $attachment_id ) {
			return $inner_content;
		}

		// Get alt and title directly from attachment metadata.
		$alt_text   = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		$title_text = get_the_title( $attachment_id );

		return self::populate_alt_title_across_breakpoints(
			$inner_content,
			$alt_text,
			$title_text,
			( false !== $alt_text && '' !== $alt_text ),
			(bool) $title_text,
			$alt_key,
			$title_key
		);
	}

	/**
	 * Populates alt and title attributes for post_featured_image dynamic content.
	 *
	 * When the image src uses post_featured_image dynamic content and alt/title are empty,
	 * this method resolves the alt/title from the featured image's Media Library metadata.
	 *
	 * @since ??
	 *
	 * @param array  $inner_content The inner content array with breakpoints and states.
	 * @param string $alt_key       The key to use for alt attribute (typically 'alt').
	 * @param string $title_key     The key to use for title attribute ('title' or 'titleText').
	 * @return array The updated inner content array with alt/title populated.
	 */
	public static function populate_post_featured_image_alt_title( array $inner_content, string $alt_key, string $title_key ): array {
		if ( ! self::detect_post_featured_image_dynamic_content( $inner_content ) ) {
			return $inner_content;
		}

		$attachment_id = self::_get_attachment_id_from_inner_content( $inner_content );

		if ( ! $attachment_id ) {
			return $inner_content;
		}

		// Get alt and title directly from attachment metadata.
		$alt_text   = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		$title_text = get_the_title( $attachment_id );

		return self::populate_alt_title_across_breakpoints(
			$inner_content,
			$alt_text,
			$title_text,
			( false !== $alt_text && '' !== $alt_text ),
			(bool) $title_text,
			$alt_key,
			$title_key
		);
	}
}
