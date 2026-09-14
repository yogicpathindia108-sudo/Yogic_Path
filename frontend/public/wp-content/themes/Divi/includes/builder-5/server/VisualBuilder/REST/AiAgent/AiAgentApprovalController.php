<?php
/**
 * REST: AiAgentApprovalController class.
 *
 * POST /divi/v1/ai-agent/approvals/mint
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\REST\AiAgent;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\VisualBuilder\AiAgent\AiAgentApprovalTokens;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for minting HITL approval tokens.
 *
 * @since ??
 */
class AiAgentApprovalController extends RESTController {
	/**
	 * Max gated calls accepted in one mint request.
	 *
	 * @var int
	 */
	public const MAX_MINT_CALLS = 50;

	/**
	 * Mint one token per mintable gated call. All-or-nothing.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function mint( WP_REST_Request $request ) {
		$calls = $request->get_param( 'calls' );

		if ( ! is_array( $calls ) ) {
			return self::response_error(
				'mint_failed',
				esc_html__( 'Failed to mint approval tokens.', 'et_builder_5' ),
				[],
				500
			);
		}

		$result = AiAgentApprovalTokens::mint( $calls );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return self::response_success( $result );
	}

	/**
	 * Mint route args.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function mint_args(): array {
		return [
			'calls' => [
				'required'          => true,
				'type'              => 'array',
				'maxItems'          => self::MAX_MINT_CALLS,
				'validate_callback' => 'rest_validate_request_arg',
				'items'             => [
					'type'       => 'object',
					'properties' => [
						'toolCallId' => [
							'type'     => 'string',
							'required' => true,
						],
						'toolName'   => [
							'type'     => 'string',
							'required' => true,
						],
						'args'       => [
							'type'    => 'object',
							'default' => [],
						],
						'overwrite'  => [
							'type'    => 'boolean',
							'default' => false,
						],
					],
				],
			],
		];
	}

	/**
	 * Same permission as AI Agent chat persistence.
	 *
	 * Mint has no `chat_id` route arg, so chat ownership is skipped and this
	 * still enforces logged-in, Visual Builder, and `edit_posts`.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return bool|WP_Error
	 */
	public static function permission( WP_REST_Request $request ) {
		return AiAgentChatController::permission( $request );
	}
}
