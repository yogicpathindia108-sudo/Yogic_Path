<?php
/**
 * REST: AnnouncementsController class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\REST\Announcements;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Announcements REST Controller class.
 *
 * @since ??
 */
class AnnouncementsController extends RESTController {
	/**
	 * RSS feed URL for Theme Releases.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private const FEED_URL = 'https://www.elegantthemes.com/blog/category/theme-releases/feed/';

	/**
	 * Maximum number of announcements returned.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	private const MAX_ITEMS = 10;

	/**
	 * Baseline date used for counting "new" announcements.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private const NEW_BASELINE_DATE = '2026-07-08 00:00:00';

	/**
	 * User meta key used to store read announcement IDs.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private const READ_ANNOUNCEMENTS_META_KEY = 'et_divi_builder_read_announcements';

	/**
	 * Option key used for versioned announcements cache.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private const ANNOUNCEMENTS_CACHE_OPTION_KEY = 'et_divi_builder_announcements_cache';

	/**
	 * Cache TTL for announcements (one week).
	 *
	 * @since ??
	 *
	 * @var int
	 */
	private const ANNOUNCEMENTS_CACHE_TTL = 7 * DAY_IN_SECONDS;

	/**
	 * Mark announcement IDs as read.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function mark_read( WP_REST_Request $request ): WP_REST_Response {
		$ids                    = $request->get_param( 'ids' );
		$sanitized_ids          = is_array( $ids ) ? $ids : [];
		$valid_announcement_ids = self::_get_valid_announcement_ids();

		$sanitized_ids = array_values(
			array_filter(
				$sanitized_ids,
				static function ( string $id ) use ( $valid_announcement_ids ): bool {
					return in_array( $id, $valid_announcement_ids, true );
				}
			)
		);

		$existing_read_ids = self::_get_read_announcement_ids();
		$merged_read_ids   = array_values(
			array_unique(
				array_filter(
					array_merge( $existing_read_ids, $sanitized_ids )
				)
			)
		);
		$merged_read_ids   = array_values(
			array_filter(
				$merged_read_ids,
				static function ( string $id ) use ( $valid_announcement_ids ): bool {
					return in_array( $id, $valid_announcement_ids, true );
				}
			)
		);

		self::_save_read_announcement_ids( $merged_read_ids );

		return self::response_success(
			[
				'markedReadCount' => count( $sanitized_ids ),
				'readIds'         => $merged_read_ids,
			]
		);
	}

	/**
	 * Get normalized announcements payload for current user.
	 *
	 * @since ??
	 *
	 * @return array<string, mixed>
	 */
	public static function get_announcements_payload_for_current_user(): array {
		$announcements      = self::_get_cached_announcements_for_current_builder_version();
		$has_read_ids_meta  = self::_has_read_announcement_ids_meta();
		$read_ids           = self::_get_read_announcement_ids();
		$unread_ids         = [];
		$results            = [];

		if ( ! $has_read_ids_meta ) {
			$read_ids = self::_get_initial_read_ids_for_first_time_user( $announcements );
			self::_save_read_announcement_ids( $read_ids );
		}

		foreach ( $announcements as $announcement ) {
			$is_new  = true === ( $announcement['isNew'] ?? false );
			$is_read = ! $is_new || in_array( $announcement['id'], $read_ids, true );
			$payload = $announcement;

			unset( $payload['isNew'] );

			$results[] = array_merge(
				$payload,
				[
					'isRead' => $is_read,
				]
			);

			if ( ! $is_read ) {
				$unread_ids[] = $announcement['id'];
			}
		}

		return [
			'results'     => $results,
			'unreadIds'   => $unread_ids,
			'unreadCount' => count( $unread_ids ),
		];
	}

	/**
	 * Build initial read IDs for first-time users.
	 *
	 * Keeps only the latest "new" announcement unread and marks remaining "new"
	 * announcements as read to avoid flooding first-time users with notifications.
	 *
	 * @since ??
	 *
	 * @param array<int, array<string, mixed>> $announcements Announcements list.
	 *
	 * @return array<int, string>
	 */
	private static function _get_initial_read_ids_for_first_time_user( array $announcements ): array {
		$read_ids            = [];
		$has_latest_unread   = false;

		foreach ( $announcements as $announcement ) {
			$is_new = true === ( $announcement['isNew'] ?? false );

			if ( ! $is_new ) {
				continue;
			}

			if ( ! $has_latest_unread ) {
				$has_latest_unread = true;
				continue;
			}

			$id = self::_sanitize_announcement_id( (string) ( $announcement['id'] ?? '' ) );

			if ( '' === $id ) {
				continue;
			}

			$read_ids[] = $id;
		}

		return array_values( array_unique( $read_ids ) );
	}

	/**
	 * Get the arguments for the mark_read action.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function mark_read_args(): array {
		return [
			'ids' => [
				'default'           => [],
				'type'              => 'array',
				'items'             => [
					'type' => 'string',
				],
				'sanitize_callback' => static function ( $value ): array {
					if ( ! is_array( $value ) ) {
						return [];
					}

					return self::_sanitize_announcement_ids( $value );
				},
			],
		];
	}

	/**
	 * Permission callback for the mark_read action.
	 *
	 * @since ??
	 *
	 * @return bool|\WP_Error
	 */
	public static function mark_read_permission() {
		if ( ! UserRole::can_current_user_use_visual_builder() ) {
			return self::response_error_permission();
		}

		return true;
	}

	/**
	 * Fetch and normalize announcements from RSS.
	 *
	 * @since ??
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function _get_announcements(): array {
		include_once ABSPATH . WPINC . '/feed.php';

		$feed = fetch_feed( self::FEED_URL );

		if ( is_wp_error( $feed ) ) {
			return [];
		}

		$baseline_timestamp = strtotime( self::NEW_BASELINE_DATE . ' UTC' );
		$items              = $feed->get_items( 0, self::MAX_ITEMS );

		if ( ! is_array( $items ) || empty( $items ) ) {
			return [];
		}

		$announcements = [];

		foreach ( $items as $item ) {
			$title = $item->get_title();
			$link  = $item->get_link();

			if ( ! is_string( $title ) || ! is_string( $link ) || '' === $title || '' === $link ) {
				continue;
			}

			$timestamp        = (int) $item->get_date( 'U' );
			$description      = (string) $item->get_description();
			$content          = (string) $item->get_content();
			$video_id         = self::_extract_first_youtube_video_id( $content . "\n" . $description );
			$image_url        = self::_extract_featured_image_url( $item );
			$excerpt_source   = '' !== $description ? $description : $content;
			$stripped_excerpt = wp_strip_all_tags( $excerpt_source );
			$decoded_excerpt  = html_entity_decode( $stripped_excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$excerpt          = wp_trim_words( $decoded_excerpt, 40, '...' );
			$is_new           = $timestamp > $baseline_timestamp;

			$announcements[] = [
				'id'               => md5( $link ),
				'title'            => wp_strip_all_tags( $title ),
				'excerpt'          => $excerpt,
				'url'              => esc_url_raw( $link ),
				'videoId'          => $video_id,
				'featuredImageUrl' => self::_build_announcement_thumbnail_url( $image_url ),
				'publishedAt'      => 0 < $timestamp ? gmdate( 'c', $timestamp ) : '',
				'isNew'            => $is_new,
			];
		}

		return $announcements;
	}

	/**
	 * Get announcements from cache, refreshing once per builder version or weekly.
	 *
	 * @since ??
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function _get_cached_announcements_for_current_builder_version(): array {
		$builder_version   = defined( 'ET_BUILDER_VERSION' ) ? (string) ET_BUILDER_VERSION : '';
		$cache             = get_option( self::ANNOUNCEMENTS_CACHE_OPTION_KEY, [] );
		$cached_items      = self::_normalize_announcement_items( is_array( $cache['items'] ?? null ) ? $cache['items'] : [] );
		$cached_version    = is_string( $cache['builderVersion'] ?? null ) ? $cache['builderVersion'] : '';
		$cached_updated_at = is_numeric( $cache['updatedAt'] ?? null ) ? (int) $cache['updatedAt'] : 0;
		$is_same_version   = '' !== $cached_version && $cached_version === $builder_version;
		$is_cache_fresh    = 0 < $cached_updated_at && ( time() - $cached_updated_at ) < self::ANNOUNCEMENTS_CACHE_TTL;
		$has_image_urls    = self::_announcements_have_featured_images( $cached_items );

		if ( $is_same_version && $is_cache_fresh && $has_image_urls ) {
			return $cached_items;
		}

		$fresh_items = self::_normalize_announcement_items( self::_get_announcements() );

		if ( empty( $fresh_items ) ) {
			// Keep existing cached items when upstream feed is unavailable.
			return $cached_items;
		}

		self::_save_announcements_cache(
			[
				'builderVersion' => $builder_version,
				'items'          => $fresh_items,
				'updatedAt'      => time(),
			]
		);

		return $fresh_items;
	}

	/**
	 * Persist announcements cache with autoload disabled.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $payload Announcements cache payload.
	 *
	 * @return void
	 */
	private static function _save_announcements_cache( array $payload ): void {
		$was_added = add_option( self::ANNOUNCEMENTS_CACHE_OPTION_KEY, $payload, '', 'no' );

		if ( ! $was_added ) {
			update_option( self::ANNOUNCEMENTS_CACHE_OPTION_KEY, $payload, false );
		}
	}

	/**
	 * Normalize cached announcement items to expected shape.
	 *
	 * @since ??
	 *
	 * @param array<int, mixed> $items Raw announcement items.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function _normalize_announcement_items( array $items ): array {
		$normalized_items = [];

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$id = self::_sanitize_announcement_id( (string) ( $item['id'] ?? '' ) );

			if ( '' === $id ) {
				continue;
			}

			$normalized_items[] = [
				'id'               => $id,
				'title'            => wp_strip_all_tags( (string) ( $item['title'] ?? '' ) ),
				'excerpt'          => wp_strip_all_tags( (string) ( $item['excerpt'] ?? '' ) ),
				'url'              => esc_url_raw( (string) ( $item['url'] ?? '' ) ),
				'videoId'          => sanitize_text_field( (string) ( $item['videoId'] ?? '' ) ),
				'featuredImageUrl' => esc_url_raw( (string) ( $item['featuredImageUrl'] ?? '' ) ),
				'publishedAt'      => sanitize_text_field( (string) ( $item['publishedAt'] ?? '' ) ),
				'isNew'            => true === ( $item['isNew'] ?? false ),
			];
		}

		return $normalized_items;
	}

	/**
	 * Determine whether cached announcements include image URLs.
	 *
	 * @since ??
	 *
	 * @param array<int, array<string, mixed>> $items Cached announcement items.
	 *
	 * @return bool
	 */
	private static function _announcements_have_featured_images( array $items ): bool {
		if ( empty( $items ) ) {
			return true;
		}

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				return false;
			}

			$image_url = $item['featuredImageUrl'] ?? null;

			if ( ! is_string( $image_url ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Extract first YouTube video ID from content.
	 *
	 * @since ??
	 *
	 * @param string $content Source content.
	 *
	 * @return string
	 */
	private static function _extract_first_youtube_video_id( string $content ): string {
		// Regex Text: Covered by AnnouncementsControllerTest::testExtractFirstYoutubeVideoId().
		$patterns = [
			'/https?:\/\/(?:www\.)?youtube\.com\/watch\?(?:[^#\s]*&)?v=([A-Za-z0-9_-]{11})(?![A-Za-z0-9_-])/i',
			'/https?:\/\/(?:www\.)?youtu\.be\/([A-Za-z0-9_-]{11})(?![A-Za-z0-9_-])/i',
			'/https?:\/\/(?:www\.)?youtube\.com\/embed\/([A-Za-z0-9_-]{11})(?![A-Za-z0-9_-])/i',
		];

		foreach ( $patterns as $pattern ) {
			$has_match = preg_match( $pattern, $content, $matches );

			if ( 1 === $has_match && ! empty( $matches[1] ) ) {
				return sanitize_text_field( $matches[1] );
			}
		}

		return '';
	}

	/**
	 * Extract featured image URL from RSS enclosure metadata.
	 *
	 * @since ??
	 *
	 * @param mixed $item Feed item.
	 *
	 * @return string
	 */
	private static function _extract_featured_image_url( $item ): string {
		if ( ! is_object( $item ) || ! method_exists( $item, 'get_enclosure' ) ) {
			return '';
		}

		$enclosure = $item->get_enclosure( 0 );

		if ( ! is_object( $enclosure ) || ! method_exists( $enclosure, 'get_link' ) ) {
			return '';
		}

		$link = (string) $enclosure->get_link();

		if ( '' === $link ) {
			return '';
		}

		return self::_sanitize_featured_image_url( $link );
	}

	/**
	 * Build announcement thumbnail URL at 300x169 size.
	 *
	 * @since ??
	 *
	 * @param string $image_url Source image URL.
	 *
	 * @return string
	 */
	private static function _build_announcement_thumbnail_url( string $image_url ): string {
		$sanitized_url = self::_sanitize_featured_image_url( $image_url );

		if ( '' === $sanitized_url ) {
			return '';
		}

		$thumbnail_url = self::_replace_thumbnail_size_suffix( $sanitized_url );

		if ( ! is_string( $thumbnail_url ) || '' === $thumbnail_url ) {
			return $sanitized_url;
		}

		return self::_sanitize_featured_image_url( $thumbnail_url );
	}

	/**
	 * Replace image size suffix with the 300x169 thumbnail size.
	 *
	 * @since ??
	 *
	 * @param string $image_url Source image URL.
	 *
	 * @return string
	 */
	private static function _replace_thumbnail_size_suffix( string $image_url ): string {
		// Regex Text: Covered by AnnouncementsControllerTest::testReplaceThumbnailSizeSuffix().
		$thumbnail_url = preg_replace(
			'/([^\/?#]+?)(?:-\d+x\d+)?(\.[a-z0-9]+)(?=(?:[?#]|$))/i',
			'$1-300x169$2',
			$image_url,
			1
		);

		if ( ! is_string( $thumbnail_url ) || '' === $thumbnail_url ) {
			return $image_url;
		}

		return $thumbnail_url;
	}

	/**
	 * Sanitize and validate featured image URL from external feed.
	 *
	 * @since ??
	 *
	 * @param string $image_url Candidate image URL.
	 *
	 * @return string
	 */
	private static function _sanitize_featured_image_url( string $image_url ): string {
		$sanitized_url = esc_url_raw( $image_url, [ 'https' ] );

		if ( '' === $sanitized_url || ! wp_http_validate_url( $sanitized_url ) ) {
			return '';
		}

		$parsed_url = wp_parse_url( $sanitized_url );

		if ( ! is_array( $parsed_url ) ) {
			return '';
		}

		$host = strtolower( (string) ( $parsed_url['host'] ?? '' ) );
		$path = (string) ( $parsed_url['path'] ?? '' );

		if ( ! in_array( $host, [ 'www.elegantthemes.com', 'elegantthemes.com' ], true ) ) {
			return '';
		}

		if ( '' === $path || 1 !== preg_match( '/\.(?:jpe?g|png|gif|webp)$/i', $path ) ) {
			return '';
		}

		return $sanitized_url;
	}

	/**
	 * Retrieve read announcement IDs for current user.
	 *
	 * @since ??
	 *
	 * @return array<int, string>
	 */
	private static function _get_read_announcement_ids(): array {
		$user_id = get_current_user_id();

		if ( 0 === $user_id ) {
			return [];
		}

		$stored_ids = get_user_meta( $user_id, self::READ_ANNOUNCEMENTS_META_KEY, true );

		if ( ! is_array( $stored_ids ) ) {
			return [];
		}

		return self::_sanitize_announcement_ids( $stored_ids );
	}

	/**
	 * Check whether read announcement IDs meta row exists for current user.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function _has_read_announcement_ids_meta(): bool {
		$user_id = get_current_user_id();

		if ( 0 === $user_id ) {
			return false;
		}

		return metadata_exists( 'user', $user_id, self::READ_ANNOUNCEMENTS_META_KEY );
	}

	/**
	 * Persist read announcement IDs for current user.
	 *
	 * @since ??
	 *
	 * @param array<int, string> $ids Read announcement IDs.
	 *
	 * @return void
	 */
	private static function _save_read_announcement_ids( array $ids ): void {
		$user_id = get_current_user_id();

		if ( 0 === $user_id ) {
			return;
		}

		$sanitized_ids = self::_sanitize_announcement_ids( $ids );

		update_user_meta( $user_id, self::READ_ANNOUNCEMENTS_META_KEY, $sanitized_ids );
	}

	/**
	 * Normalize and sanitize a list of announcement IDs.
	 *
	 * @since ??
	 *
	 * @param array<int, mixed> $ids Candidate announcement IDs.
	 *
	 * @return array<int, string>
	 */
	private static function _sanitize_announcement_ids( array $ids ): array {
		return array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $id ): string {
							return self::_sanitize_announcement_id( (string) $id );
						},
						$ids
					)
				)
			)
		);
	}

	/**
	 * Normalize and validate announcement ID values.
	 *
	 * @since ??
	 *
	 * @param string $id Candidate announcement ID.
	 *
	 * @return string
	 */
	private static function _sanitize_announcement_id( string $id ): string {
		$normalized_id = sanitize_key( $id );

		if ( 1 !== preg_match( '/^[a-f0-9]{32}$/', $normalized_id ) ) {
			return '';
		}

		return $normalized_id;
	}

	/**
	 * Get currently valid announcement IDs from the cached payload.
	 *
	 * @since ??
	 *
	 * @return array<int, string>
	 */
	private static function _get_valid_announcement_ids(): array {
		$items = self::_get_cached_announcements_for_current_builder_version();

		return array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $item ): string {
							if ( ! is_array( $item ) ) {
								return '';
							}

							return self::_sanitize_announcement_id( (string) ( $item['id'] ?? '' ) );
						},
						$items
					)
				)
			)
		);
	}
}
