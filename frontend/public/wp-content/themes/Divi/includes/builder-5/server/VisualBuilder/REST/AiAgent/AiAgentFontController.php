<?php
/**
 * REST: AiAgentFontController class.
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
use ET\Builder\VisualBuilder\Fonts\FontsUtility;
use WP_Error;
use WP_REST_Request;

/**
 * REST controller for AI agent chat font attachments.
 *
 * @since ??
 */
class AiAgentFontController extends RESTController {

	/**
	 * Handle multipart font upload into the chat staging folder.
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

		return AiAgentImageUtility::upload_font( $file, $chat_id );
	}

	/**
	 * Arguments for the font upload route.
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
	 * Batch-install staged chat fonts into Custom Fonts.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return array
	 */
	public static function install( WP_REST_Request $request ) {
		$fonts = (array) $request->get_param( 'fonts' );

		$results       = [];
		$updated_fonts = [];
		$success_count = 0;

		foreach ( $fonts as $font ) {
			if ( ! is_array( $font ) ) {
				$results[] = [
					'success' => false,
					'error'   => 'Invalid font payload.',
				];
				continue;
			}

			$url       = isset( $font['url'] ) ? sanitize_url( (string) $font['url'] ) : '';
			$font_name = isset( $font['fontName'] ) ? sanitize_text_field( (string) $font['fontName'] ) : '';

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

			$installed = AiAgentImageUtility::install_to_custom_fonts(
				$parsed['chatId'],
				$parsed['filename'],
				$font_name
			);

			if ( is_wp_error( $installed ) ) {
				$results[] = [
					'success' => false,
					'error'   => $installed->get_error_message(),
				];
				continue;
			}

			++$success_count;
			$updated_fonts = isset( $installed['updated_fonts'] ) && is_array( $installed['updated_fonts'] )
				? $installed['updated_fonts']
				: $updated_fonts;

			$results[] = [
				'success'      => true,
				'uploadedFont' => $installed['uploaded_font'],
			];
		}

		return [
			'success'      => $success_count === count( $results ),
			'message'      => sprintf(
				'%d of %d font(s) installed.',
				$success_count,
				count( $results )
			),
			'updatedFonts' => [] === $updated_fonts ? new \stdClass() : $updated_fonts,
			'results'      => $results,
		];
	}

	/**
	 * Arguments for the install-custom-fonts route.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function install_args(): array {
		return [
			'fonts' => [
				'required'          => true,
				'type'              => 'array',
				'minItems'          => 1,
				'maxItems'          => AiAgentImageUtility::MAX_ATTACHMENTS_PER_CHAT,
				'validate_callback' => 'rest_validate_request_arg',
				'items'             => [
					'type'       => 'object',
					'required'   => [ 'url' ],
					'properties' => [
						'url'      => [
							'type' => 'string',
						],
						'fontName' => [
							'type'      => 'string',
							'maxLength' => FontsUtility::FONT_NAME_MAX_LENGTH,
						],
					],
				],
			],
		];
	}

	/**
	 * Upload and install permission callback.
	 *
	 * Requires WordPress `upload_files`, Visual Builder access, and Divi
	 * `custom_fonts_management`.
	 *
	 * @since ??
	 *
	 * @return bool|WP_Error
	 */
	public static function store_permission() {
		if ( ! current_user_can( 'upload_files' ) ) {
			return self::response_error_permission();
		}

		if ( ! UserRole::can_current_user_use_visual_builder() ) {
			return self::response_error_permission();
		}

		if ( ! et_pb_is_allowed( 'custom_fonts_management' ) ) {
			return self::response_error_permission();
		}

		return true;
	}
}
