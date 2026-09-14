<?php
/**
 * InstagramAPI class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Services\InstagramAccountService;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use WP_Error;

/**
 * Lightweight Instagram Graph API client.
 *
 * @since ??
 */
class InstagramAPI {
	/**
	 * Graph API base URL.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	const GRAPH_BASE = 'https://graph.instagram.com/v25.0';

	/**
	 * Media fields requested from the Graph API.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	const MEDIA_FIELDS = 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp';

	/**
	 * Request timeout in seconds.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	const REQUEST_TIMEOUT = 15;

	/**
	 * Perform a GET request to Instagram Graph API.
	 *
	 * @since ??
	 *
	 * @param string $endpoint     API endpoint.
	 * @param array  $query_params Query parameters.
	 *
	 * @return array|WP_Error
	 */
	private static function _request( string $endpoint, array $query_params ) {
		$url = add_query_arg( $query_params, self::GRAPH_BASE . $endpoint );

		$response = wp_remote_get(
			$url,
			[
				'timeout' => self::REQUEST_TIMEOUT,
			]
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'instagram_api_error', $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );
		$decoded_body  = json_decode( $body, true );

		if ( ! is_array( $decoded_body ) ) {
			return new WP_Error( 'instagram_api_error', esc_html__( 'Invalid response from Instagram API.', 'et_builder_5' ) );
		}

		if ( $response_code < 200 || $response_code >= 300 ) {
			$error_message = $decoded_body['error']['message'] ?? esc_html__( 'Instagram API request failed.', 'et_builder_5' );

			return new WP_Error( 'instagram_api_error', sanitize_text_field( $error_message ) );
		}

		if ( isset( $decoded_body['error'] ) && is_array( $decoded_body['error'] ) ) {
			$error_message = $decoded_body['error']['message'] ?? esc_html__( 'Instagram API request failed.', 'et_builder_5' );

			return new WP_Error( 'instagram_api_error', sanitize_text_field( $error_message ) );
		}

		return $decoded_body;
	}

	/**
	 * Fetch account profile for a token.
	 *
	 * @since ??
	 *
	 * @param string $access_token Instagram access token.
	 *
	 * @return array|WP_Error
	 */
	public static function get_me( string $access_token ) {
		return self::_request(
			'/me',
			[
				'fields'       => 'user_id,username',
				'access_token' => $access_token,
			]
		);
	}

	/**
	 * Fetch media for a token.
	 *
	 * @since ??
	 *
	 * @param string $access_token Instagram access token.
	 * @param int    $limit        Requested limit.
	 *
	 * @return array|WP_Error
	 */
	public static function get_media( string $access_token, int $limit ) {
		return self::_request(
			'/me/media',
			[
				'fields'       => self::MEDIA_FIELDS,
				'limit'        => $limit,
				'access_token' => $access_token,
			]
		);
	}

	/**
	 * Normalize Instagram media payload into module item shape.
	 *
	 * @since ??
	 *
	 * @param array $raw_response Raw API response payload.
	 *
	 * @return array
	 */
	public static function normalize_items( array $raw_response ): array {
		$data = $raw_response['data'] ?? [];

		if ( ! is_array( $data ) ) {
			return [];
		}

		$items = [];

		foreach ( $data as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$id            = isset( $item['id'] ) ? sanitize_text_field( (string) $item['id'] ) : '';
			$media_type    = isset( $item['media_type'] ) ? strtolower( sanitize_text_field( (string) $item['media_type'] ) ) : 'image';
			$media_url_raw = isset( $item['media_url'] ) ? esc_url_raw( (string) $item['media_url'] ) : '';
			$thumb_url_raw = isset( $item['thumbnail_url'] ) ? esc_url_raw( (string) $item['thumbnail_url'] ) : '';
			$permalink     = isset( $item['permalink'] ) ? esc_url_raw( (string) $item['permalink'] ) : '';
			$caption       = isset( $item['caption'] ) ? sanitize_text_field( (string) $item['caption'] ) : '';
			$timestamp     = isset( $item['timestamp'] ) ? sanitize_text_field( (string) $item['timestamp'] ) : '';

			if ( '' === $id ) {
				continue;
			}

			$media_url = $media_url_raw;

			if ( 'video' === $media_type && '' !== $thumb_url_raw ) {
				$media_url = $thumb_url_raw;
			}

			if ( '' === $media_url ) {
				continue;
			}

			$items[] = [
				'id'           => $id,
				'mediaType'    => $media_type,
				'mediaUrl'     => $media_url,
				'thumbnailUrl' => $thumb_url_raw,
				'videoUrl'     => $media_url_raw,
				'permalink'    => $permalink,
				'caption'      => $caption,
				'timestamp'    => $timestamp,
			];
		}

		return $items;
	}
}
