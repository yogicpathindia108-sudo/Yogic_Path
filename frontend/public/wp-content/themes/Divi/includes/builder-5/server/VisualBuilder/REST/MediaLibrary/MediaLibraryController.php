<?php
/**
 * REST: MediaLibraryController class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\REST\MediaLibrary;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\Framework\Utility\RemoteRequestUtility;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Media Library REST controller for compact AI-friendly media search and sideload.
 *
 * @since ??
 */
class MediaLibraryController extends RESTController {

	/**
	 * Search media attachments with compact response output.
	 *
	 * Primary query searches title/description/caption using `s`.
	 * If no results are found, fallback query searches alt text and attached filename.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function search( WP_REST_Request $request ) {
		$search     = trim( (string) $request->get_param( 'search' ) );
		$media_type = (string) $request->get_param( 'media_type' );
		$per_page   = (int) $request->get_param( 'per_page' );
		$page       = (int) $request->get_param( 'page' );

		$per_page = max( 1, min( 0 === $per_page ? 10 : $per_page, 25 ) );
		$page     = max( 1, $page );

		$query_args = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}

		if ( '' !== $media_type ) {
			$query_args['post_mime_type'] = $media_type;
		}

		// Limit results to the current user's uploads when they cannot edit others' attachments.
		if ( ! current_user_can( self::_get_attachment_edit_others_posts_cap() ) ) {
			$query_args['author'] = get_current_user_id();
		}

		$primary_query = new WP_Query( $query_args );
		$active_query  = $primary_query;

		if ( 0 === (int) $primary_query->found_posts && '' !== $search ) {
			unset( $query_args['s'] );
			$query_args['meta_query'] = [
				'relation' => 'OR',
				[
					'key'     => '_wp_attachment_image_alt',
					'value'   => $search,
					'compare' => 'LIKE',
				],
				[
					'key'     => '_wp_attached_file',
					'value'   => $search,
					'compare' => 'LIKE',
				],
			];

			$active_query = new WP_Query( $query_args );
		}

		$active_query_posts       = $active_query->posts;
		$active_query_found_posts = (int) $active_query->found_posts;

		$items = array_map(
			static function ( WP_Post $post ) {
				return self::_format_media_item( $post );
			},
			$active_query_posts
		);

		return self::response_success(
			[
				'total'      => $active_query_found_posts,
				'totalPages' => (int) ceil( $active_query_found_posts / $per_page ),
				'page'       => $page,
				'perPage'    => $per_page,
				'items'      => array_values( $items ),
			]
		);
	}

	/**
	 * Get the arguments for the search action.
	 *
	 * @since ??
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function search_args(): array {
		return [
			'search'     => [
				'required'          => false,
				'type'              => 'string',
				'format'            => 'text-field',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'media_type' => [
				'type' => 'string',
				'enum' => [ 'image', 'video', 'audio' ],
			],
			'per_page'   => [
				'required'          => false,
				'type'              => 'integer',
				'default'           => 10,
				'minimum'           => 1,
				'maximum'           => 25,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'page'       => [
				'required'          => false,
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
		];
	}

	/**
	 * Permission callback for media library search.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function search_permission(): bool {
		return UserRole::can_current_user_use_visual_builder() && current_user_can( 'upload_files' );
	}

	/**
	 * Sideload an external image URL into the WordPress Media Library.
	 *
	 * Ports the legacy `ET_AI_App::et_ai_upload_image()` AJAX handler so D5 agent
	 * tools can import temporary AI server URLs via `@divi/rest`.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function sideload_from_url( WP_REST_Request $request ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$image_url_raw = $request->get_param( 'imageURL' );

		if ( ! is_string( $image_url_raw ) || '' === $image_url_raw ) {
			return self::response_error(
				'invalid_image_url',
				__( 'A valid image URL is required.', 'et_builder_5' ),
				[],
				400
			);
		}

		$filename = self::_sideload_filename_from_url( $image_url_raw );

		if ( is_wp_error( $filename ) ) {
			return self::_sideload_error_response( $filename );
		}

		// Exact-match allowlist is enforced on every hop to satisfy SSRF defense-in-depth.
		// `require_allowlist` fails closed if the effective allowlist is ever empty.
		$download = RemoteRequestUtility::download_file(
			$image_url_raw,
			[
				'allowed_hosts'     => self::_get_sideload_allowed_hosts(),
				'require_allowlist' => true,
			]
		);

		if ( is_wp_error( $download ) ) {
			return self::_sideload_error_response( $download );
		}

		$tmp_name   = $download['file'];
		$file_array = [
			'name'     => $filename,
			'tmp_name' => $tmp_name,
		];

		$post_id = get_the_ID();
		$post_id = $post_id ? $post_id : 0;

		$upload = media_handle_sideload( $file_array, $post_id, null );

		if ( is_wp_error( $upload ) ) {
			if ( file_exists( $tmp_name ) ) {
				wp_delete_file( $tmp_name );
			}

			return self::_sideload_error_response( $upload );
		}

		$attachment_id = $upload;

		add_post_meta( $attachment_id, '_source_url', $image_url_raw );

		$image_path   = get_attached_file( $attachment_id );
		$image_editor = wp_get_image_editor( $image_path );

		if ( ! is_wp_error( $image_editor ) ) {
			$image_editor->set_quality( 80 );
			$saved = $image_editor->save( null, 'image/jpeg' );

			if ( ! is_wp_error( $saved ) ) {
				$jpeg_attachment_id = wp_insert_attachment(
					[
						'post_mime_type' => 'image/jpeg',
						'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $saved['path'] ) ),
						'post_content'   => '',
						'post_status'    => 'inherit',
					],
					$saved['path']
				);

				if ( is_wp_error( $jpeg_attachment_id ) || 0 === $jpeg_attachment_id ) {
					wp_delete_file( $saved['path'] );

					return self::response_error(
						'upload_failed',
						is_wp_error( $jpeg_attachment_id )
							? $jpeg_attachment_id->get_error_message()
							: __( 'Failed to import the converted image.', 'et_builder_5' ),
						[],
						500
					);
				}

				wp_update_attachment_metadata( $jpeg_attachment_id, wp_generate_attachment_metadata( $jpeg_attachment_id, $saved['path'] ) );
				update_post_meta( $jpeg_attachment_id, '_source_url', $image_url_raw );
				wp_delete_attachment( $attachment_id, true );
				$attachment_id = $jpeg_attachment_id;
			}
		}

		if ( 0 === $attachment_id || ! wp_get_attachment_url( $attachment_id ) ) {
			return self::response_error(
				'upload_failed',
				__( 'Failed to import the image into the Media Library.', 'et_builder_5' ),
				[],
				500
			);
		}

		return self::response_success(
			[
				'localImageID'  => $attachment_id,
				'localImageURL' => wp_get_attachment_url( $attachment_id ),
			]
		);
	}

	/**
	 * REST argument schema for sideload-from-url.
	 *
	 * @since ??
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function sideload_from_url_args(): array {
		return [
			'imageURL' => [
				'required'          => true,
				'type'              => 'string',
				'format'            => 'uri',
				'sanitize_callback' => 'esc_url_raw',
				'validate_callback' => 'rest_validate_request_arg',
			],
		];
	}

	/**
	 * Permission callback for sideload-from-url.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function sideload_from_url_permission(): bool {
		return self::search_permission();
	}

	/**
	 * Formats an attachment post for compact AI-friendly responses.
	 *
	 * @since ??
	 *
	 * @param WP_Post $post Attachment post object.
	 *
	 * @return array<string, int|string|null>
	 */
	private static function _format_media_item( WP_Post $post ): array {
		$attachment_id = (int) $post->ID;
		$metadata      = wp_get_attachment_metadata( $attachment_id );
		$width         = is_array( $metadata ) && isset( $metadata['width'] ) ? (int) $metadata['width'] : null;
		$height        = is_array( $metadata ) && isset( $metadata['height'] ) ? (int) $metadata['height'] : null;
		$mime_type     = get_post_mime_type( $attachment_id );

		return [
			'id'     => $attachment_id,
			'title'  => wp_strip_all_tags( get_the_title( $attachment_id ) ),
			'alt'    => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'type'   => self::_normalize_media_type( $mime_type ),
			'url'    => (string) wp_get_attachment_url( $attachment_id ),
			'width'  => $width,
			'height' => $height,
			'date'   => (string) $post->post_date,
		];
	}

	/**
	 * Normalize attachment mime type to high-level media type.
	 *
	 * @since ??
	 *
	 * @param string|false $mime_type Attachment mime type.
	 *
	 * @return string
	 */
	private static function _normalize_media_type( $mime_type ): string {
		if ( ! is_string( $mime_type ) || '' === $mime_type ) {
			return 'unknown';
		}

		if ( str_starts_with( $mime_type, 'image/' ) ) {
			return 'image';
		}
		if ( str_starts_with( $mime_type, 'video/' ) ) {
			return 'video';
		}
		if ( str_starts_with( $mime_type, 'audio/' ) ) {
			return 'audio';
		}

		return 'unknown';
	}

	/**
	 * Map a sideload failure to the appropriate REST error response.
	 *
	 * Transient download failures (temporary AI server URLs not yet available) return
	 * `image_not_ready` with HTTP 403 so the client can retry. Permanent failures return
	 * `upload_failed` with 4xx/5xx and must not be retried.
	 *
	 * @since ??
	 *
	 * @param WP_Error $upload Sideload error from the secure downloader or media import.
	 *
	 * @return WP_Error
	 */
	private static function _sideload_error_response( WP_Error $upload ): WP_Error {
		if ( self::_is_transient_sideload_error( $upload ) ) {
			return self::response_error(
				'image_not_ready',
				$upload->get_error_message(),
				[],
				403
			);
		}

		return self::response_error(
			'upload_failed',
			$upload->get_error_message(),
			[],
			self::_get_permanent_sideload_error_status( $upload )
		);
	}

	/**
	 * Whether a sideload error indicates the remote image is not yet downloadable.
	 *
	 * Only actual origin 403/404 responses are retryable. Policy, pin, proxy,
	 * timeout, overflow, and other HTTP failures must not match this path.
	 *
	 * @since ??
	 *
	 * @param WP_Error $error Sideload error.
	 *
	 * @return bool
	 */
	private static function _is_transient_sideload_error( WP_Error $error ): bool {
		return in_array( $error->get_error_code(), [ 'http_403', 'http_404' ], true );
	}

	/**
	 * HTTP status for permanent sideload failures.
	 *
	 * @since ??
	 *
	 * @param WP_Error $error Sideload error.
	 *
	 * @return int
	 */
	private static function _get_permanent_sideload_error_status( WP_Error $error ): int {
		if ( 'remote_file_too_large' === $error->get_error_code() ) {
			return 413;
		}

		$client_error_codes = [
			'invalid_image',
			'filetype_forbidden',
			'invalid_url',
			'image_sideload_failed',
			'rest_invalid_param',
		];

		if ( in_array( $error->get_error_code(), $client_error_codes, true ) ) {
			return 422;
		}

		$data = $error->get_error_data();

		if ( is_array( $data ) && isset( $data['status'] ) ) {
			$status = (int) $data['status'];

			// Origin 401 must not reuse the REST unauthenticated status.
			if ( 401 === $status ) {
				return 422;
			}

			if ( $status >= 400 && $status < 500 ) {
				return $status;
			}
		}

		return 500;
	}

	/**
	 * Extract the original-URL filename using core's image sideload extension preflight.
	 *
	 * The filename is taken from the caller URL, not a later redirect, so a CDN
	 * hop without an extension still imports under the original name.
	 *
	 * @since ??
	 *
	 * @param string $file Image URL.
	 *
	 * @return string|WP_Error
	 */
	private static function _sideload_filename_from_url( string $file ) {
		$allowed_extensions = [ 'jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp' ];

		/**
		 * Filters the list of allowed file extensions when sideloading an image from a URL.
		 *
		 * @since ??
		 *
		 * @param string[] $allowed_extensions Array of allowed file extensions.
		 * @param string   $file               The URL of the image to download.
		 */
		$allowed_extensions = apply_filters( 'image_sideload_extensions', $allowed_extensions, $file );
		$allowed_extensions = array_map( 'preg_quote', $allowed_extensions );

		preg_match( '/[^\?]+\.(' . implode( '|', $allowed_extensions ) . ')\b/i', $file, $matches );

		if ( ! $matches ) {
			return new WP_Error(
				'image_sideload_failed',
				__( 'Invalid image URL.', 'et_builder_5' )
			);
		}

		return wp_basename( $matches[0] );
	}

	/**
	 * Default exact hostnames allowed for media-library sideload-from-url.
	 *
	 * Only the fixed CloudFront distribution hostname used for generated images is accepted.
	 *
	 * @since ??
	 *
	 * @var string[]
	 */
	private const SIDELOAD_DEFAULT_ALLOWED_HOSTS = [
		'du0s2z4onr5xx.cloudfront.net',
	];

	/**
	 * Exact hostnames allowed for media-library sideload-from-url.
	 *
	 * Falls back to the built-in default when `et_builder_5_sideload_allowed_hosts`
	 * normalizes to an empty list, so a misbehaving filter cannot widen sideload to
	 * arbitrary public hosts (`require_allowlist` on `download_file()` fails closed
	 * on any other empty-list edge case).
	 *
	 * @since ??
	 *
	 * @return string[]
	 */
	private static function _get_sideload_allowed_hosts(): array {
		$hosts = self::SIDELOAD_DEFAULT_ALLOWED_HOSTS;

		/**
		 * Filter exact hostnames allowed for media-library sideload-from-url.
		 *
		 * @since ??
		 *
		 * @param string[] $hosts Normalized hostnames (exact match, every hop).
		 */
		$hosts = apply_filters( 'et_builder_5_sideload_allowed_hosts', $hosts );

		$normalized_hosts = [];

		if ( is_array( $hosts ) ) {
			foreach ( $hosts as $host ) {
				if ( ! is_string( $host ) ) {
					continue;
				}

				$normalized_host = RemoteRequestUtility::normalize_host( $host );

				if ( '' === trim( $normalized_host ) ) {
					continue;
				}

				$normalized_hosts[] = $normalized_host;
			}
		}

		if ( [] === $normalized_hosts ) {
			return array_map(
				[ RemoteRequestUtility::class, 'normalize_host' ],
				self::SIDELOAD_DEFAULT_ALLOWED_HOSTS
			);
		}

		return array_values( $normalized_hosts );
	}

	/**
	 * Resolve the attachment edit-others capability string.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	private static function _get_attachment_edit_others_posts_cap(): string {
		$attachment_type = get_post_type_object( 'attachment' );

		if ( isset( $attachment_type->cap->edit_others_posts ) && is_string( $attachment_type->cap->edit_others_posts ) ) {
			return $attachment_type->cap->edit_others_posts;
		}

		return 'edit_others_posts';
	}
}
