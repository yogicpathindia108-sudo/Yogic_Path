<?php
/**
 * REST: AiAgentImageController class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\REST\AiAgent;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\VisualBuilder\AiAgent\AiAgentImageUtility;
use WP_Error;
use WP_REST_Request;

/**
 * REST controller for AI agent chat image attachments.
 *
 * @since ??
 */
class AiAgentImageController extends RESTController {

	/**
	 * Handle multipart image upload.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return array|WP_Error
	 */
	public static function store( WP_REST_Request $request ) {
		$chat_id     = (string) $request->get_param( 'chatId' );
		$file_params = $request->get_file_params();
		$file        = isset( $file_params['file'] ) ? $file_params['file'] : [];

		return AiAgentImageUtility::upload_image( $file, $chat_id );
	}

	/**
	 * Arguments for the upload route.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function store_args(): array {
		return [
			'chatId' => [
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Delete a single image file.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return array|WP_Error
	 */
	public static function destroy_file( WP_REST_Request $request ) {
		$chat_id  = (string) $request->get_param( 'chatId' );
		$filename = (string) $request->get_param( 'filename' );
		$deleted  = AiAgentImageUtility::delete_image( $chat_id, $filename );

		if ( is_wp_error( $deleted ) ) {
			return $deleted;
		}

		if ( ! $deleted ) {
			return new WP_Error(
				'delete_failed',
				esc_html__( 'Failed to delete image.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		return [ 'success' => true ];
	}

	/**
	 * Arguments for the single-file delete route.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function destroy_file_args(): array {
		return [
			'chatId' => [
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'filename' => [
				'required'          => true,
				'sanitize_callback' => 'sanitize_file_name',
			],
		];
	}

	/**
	 * Delete all images for a chat.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return array|WP_Error
	 */
	public static function destroy_chat( WP_REST_Request $request ) {
		$chat_id = (string) $request->get_param( 'chatId' );
		$deleted = AiAgentImageUtility::delete_chat_images( $chat_id );

		if ( is_wp_error( $deleted ) ) {
			return $deleted;
		}

		if ( ! $deleted ) {
			return new WP_Error(
				'delete_failed',
				esc_html__( 'Failed to delete chat images.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}

		return [ 'success' => true ];
	}

	/**
	 * Arguments for the chat-folder delete route.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function destroy_chat_args(): array {
		return [
			'chatId' => [
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Batch-import chat attachments into the WordPress media library.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return array
	 */
	public static function import_to_media_library( WP_REST_Request $request ) {
		$images = (array) $request->get_param( 'images' );

		$results         = [];
		$ownership_cache = [];

		foreach ( $images as $index => $image ) {
			if ( ! is_array( $image ) ) {
				$results[] = [
					'success' => false,
					'error'   => 'Invalid image payload.',
				];
				continue;
			}

			$url      = isset( $image['url'] ) ? sanitize_url( (string) $image['url'] ) : '';
			$title    = isset( $image['title'] ) ? sanitize_text_field( (string) $image['title'] ) : '';
			$alt_text = isset( $image['altText'] ) ? sanitize_text_field( (string) $image['altText'] ) : '';

			if ( '' === $url ) {
				$results[] = [
					'success' => false,
					'error'   => 'Missing url.',
				];
				continue;
			}

			$parsed = AiAgentImageUtility::parse_attachment_url( $url );

			if ( is_wp_error( $parsed ) ) {
				$results[] = [
					'success' => false,
					'error'   => $parsed->get_error_message(),
				];
				continue;
			}

			$chat_id = $parsed['chatId'];

			if ( ! isset( $ownership_cache[ $chat_id ] ) ) {
				$ownership_cache[ $chat_id ] = AiAgentImageUtility::assert_chat_ownership( $chat_id );
			}

			if ( is_wp_error( $ownership_cache[ $chat_id ] ) ) {
				$results[] = [
					'success' => false,
					'error'   => $ownership_cache[ $chat_id ]->get_error_message(),
				];
				continue;
			}

			$imported = AiAgentImageUtility::import_to_media_library(
				$parsed['chatId'],
				$parsed['filename'],
				$title,
				$alt_text
			);

			if ( is_wp_error( $imported ) ) {
				$results[] = [
					'success' => false,
					'error'   => $imported->get_error_message(),
				];
				continue;
			}

			$results[] = [
				'success'      => true,
				'attachmentId' => $imported['attachmentId'],
				'url'          => $imported['url'],
				'width'        => $imported['width'],
				'height'       => $imported['height'],
			];
		}

		$success_count = count( array_filter( $results, fn( $r ) => $r['success'] ) );

		return [
			'success' => $success_count === count( $results ),
			'message' => sprintf(
				'%d of %d image(s) imported.',
				$success_count,
				count( $results )
			),
			'results' => $results,
		];
	}

	/**
	 * Arguments for the import-to-media-library route.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function import_to_media_library_args(): array {
		return [
			'images' => [
				'required'          => true,
				'type'              => 'array',
				'minItems'          => 1,
				'maxItems'          => AiAgentImageUtility::MAX_ATTACHMENTS_PER_CHAT,
				'validate_callback' => 'rest_validate_request_arg',
				'items'             => [
					'type'       => 'object',
					'required'   => [ 'url' ],
					'properties' => [
						'url'     => [
							'type' => 'string',
						],
						'title'   => [
							'type' => 'string',
						],
						'altText' => [
							'type' => 'string',
						],
					],
				],
			],
		];
	}

	/**
	 * Upload permission callback.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return bool|WP_Error
	 */
	public static function store_permission( WP_REST_Request $request ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return self::response_error_permission();
		}

		if ( ! UserRole::can_current_user_use_visual_builder() ) {
			return self::response_error_permission();
		}

		$chat_id = $request->get_param( 'chatId' );
		if ( null === $chat_id || '' === $chat_id ) {
			// Import-to-media-library has no chatId; ownership is enforced per URL in the handler.
			return true;
		}

		return AiAgentImageUtility::verify_chat_ownership( (string) $chat_id );
	}

	/**
	 * Delete permission callback — must match store_permission global gates, with chat ownership when chatId is present.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return bool|WP_Error
	 */
	public static function destroy_permission( WP_REST_Request $request ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return self::response_error_permission();
		}

		if ( ! UserRole::can_current_user_use_visual_builder() ) {
			return self::response_error_permission();
		}

		$chat_id = $request->get_param( 'chatId' );
		if ( null === $chat_id || '' === $chat_id ) {
			return true;
		}

		return AiAgentImageUtility::verify_chat_ownership( (string) $chat_id );
	}
}
