<?php
/**
 * AI Agent HITL approval token mint and consume.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\AiAgent;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use WP_Error;
use WP_REST_Request;

/**
 * Stores hashed single-use approval tokens and consumes them atomically.
 *
 * @since ??
 */
class AiAgentApprovalTokens {
	/**
	 * Token TTL in JS milliseconds (5 minutes).
	 *
	 * @var int
	 */
	public const TTL_MS = 300000;

	/**
	 * Agent credential header name as read by WP_REST_Request::get_header().
	 *
	 * @var string
	 */
	public const CREDENTIAL_HEADER = 'x-et-ai-agent-tool';

	/**
	 * Raw approval token header name as read by WP_REST_Request::get_header().
	 *
	 * @var string
	 */
	public const TOKEN_HEADER = 'x-et-ai-agent-approval';

	/**
	 * Whether this request was made by an AI Agent tool (credential present).
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return bool
	 */
	public static function has_agent_credential( WP_REST_Request $request ): bool {
		return '1' === (string) $request->get_header( self::CREDENTIAL_HEADER );
	}

	/**
	 * After existing cap checks: if the agent credential is absent, return those
	 * caps unchanged. If present, require a matching unused unexpired token.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request    REST request.
	 * @param string          $tool_name  Tool name.
	 * @param array           $args       REST params or model args for binding.
	 * @param bool|WP_Error   $cap_result Existing permission result.
	 * @param bool            $overwrite  Whether this consume is overwrite-bound.
	 *
	 * @return bool|WP_Error
	 */
	public static function authorize_after_caps( WP_REST_Request $request, string $tool_name, array $args, $cap_result, bool $overwrite = false ) {
		if ( true !== $cap_result ) {
			return $cap_result;
		}

		if ( ! self::has_agent_credential( $request ) ) {
			return $cap_result;
		}

		$token = $request->get_header( self::TOKEN_HEADER );

		if ( ! is_string( $token ) || '' === $token ) {
			return self::_invalid_token_error();
		}

		$binding = AiAgentApprovalBinding::from_args( $tool_name, $args );

		if ( null === $binding ) {
			return self::_invalid_token_error();
		}

		$consumed = self::consume(
			$token,
			(int) get_current_user_id(),
			$tool_name,
			$binding['binding_type'],
			$binding['binding_value'],
			$overwrite
		);

		if ( ! $consumed ) {
			return self::_invalid_token_error();
		}

		return true;
	}

	/**
	 * Mint one token per mintable call. All-or-nothing (transaction + rollback).
	 *
	 * `clear_layout` is skipped. Any other call that cannot bind fails the batch.
	 *
	 * @since ??
	 *
	 * @param array $calls Calls: `{ toolCallId, toolName, args, overwrite }`.
	 *
	 * @return array{tokens: array<int, array{toolCallId: string, token: string}>}|WP_Error
	 */
	public static function mint( array $calls ) {
		global $wpdb;

		$user_id = (int) get_current_user_id();

		if ( 0 === $user_id ) {
			return RESTController::response_error( 'not_logged_in', esc_html__( 'You must be logged in.', 'et_builder_5' ), [], 401 );
		}

		$prepared = [];

		foreach ( $calls as $call ) {
			if ( $call instanceof \stdClass ) {
				$call = (array) $call;
			}

			if ( ! is_array( $call ) ) {
				return self::_mint_failed_error();
			}

			$tool_call_id = isset( $call['toolCallId'] ) && is_string( $call['toolCallId'] ) ? $call['toolCallId'] : '';
			$tool_name    = isset( $call['toolName'] ) && is_string( $call['toolName'] ) ? $call['toolName'] : '';
			// REST `type: object` may arrive as stdClass instead of an array.
			$raw_args = $call['args'] ?? [];

			if ( $raw_args instanceof \stdClass ) {
				$raw_args = (array) $raw_args;
			}

			$args      = is_array( $raw_args ) ? $raw_args : [];
			$overwrite = ! empty( $call['overwrite'] );

			if ( '' === $tool_call_id || '' === $tool_name ) {
				return self::_mint_failed_error();
			}

			if ( 'clear_layout' === $tool_name ) {
				continue;
			}

			$binding = AiAgentApprovalBinding::from_args( $tool_name, $args );

			if ( null === $binding ) {
				return self::_mint_failed_error();
			}

			$overwrite_flag = 0;

			if ( $overwrite && ( 'assign_template' === $tool_name || 'assign_menu_location' === $tool_name ) ) {
				$overwrite_flag = 1;
			}

			$prepared[] = [
				'tool_call_id'  => $tool_call_id,
				'tool_name'     => $tool_name,
				'binding_type'  => $binding['binding_type'],
				'binding_value' => $binding['binding_value'],
				'overwrite'     => $overwrite_flag,
			];
		}

		if ( [] === $prepared ) {
			return [
				'tokens' => [],
			];
		}

		$now_ms     = self::now_ms();
		$expires_at = $now_ms + self::TTL_MS;
		$tokens     = [];

		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		foreach ( $prepared as $row ) {
			try {
				$raw_token = bin2hex( random_bytes( 32 ) );
			} catch ( \Exception $exception ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				return self::_mint_failed_error();
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$inserted = $wpdb->insert(
				$wpdb->et_divi_ai_approval_tokens,
				[
					'token_hash'    => hash( 'sha256', $raw_token ),
					'user_id'       => $user_id,
					'tool_name'     => $row['tool_name'],
					'binding_type'  => $row['binding_type'],
					'binding_value' => $row['binding_value'],
					'overwrite'     => $row['overwrite'],
					'expires_at'    => $expires_at,
					'created_at'    => $now_ms,
				],
				[ '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d' ]
			);

			if ( false === $inserted ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				return self::_mint_failed_error();
			}

			$tokens[] = [
				'toolCallId' => $row['tool_call_id'],
				'token'      => $raw_token,
			];
		}

		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		return [
			'tokens' => $tokens,
		];
	}

	/**
	 * Consume a raw token. Succeeds only when exactly one unused unexpired row
	 * matches user + tool + binding + overwrite.
	 *
	 * @since ??
	 *
	 * @param string $raw_token     Raw token from the approval header.
	 * @param int    $user_id       Current user id.
	 * @param string $tool_name     Tool name.
	 * @param string $binding_type  `id` or `args_hash`.
	 * @param string $binding_value Binding value.
	 * @param bool   $overwrite     Overwrite-bound consume.
	 *
	 * @return bool
	 */
	public static function consume( string $raw_token, int $user_id, string $tool_name, string $binding_type, string $binding_value, bool $overwrite ): bool {
		global $wpdb;

		if ( '' === $raw_token || 0 === $user_id ) {
			return false;
		}

		$now_ms     = self::now_ms();
		$token_hash = hash( 'sha256', $raw_token );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->et_divi_ai_approval_tokens} SET consumed_at = %d WHERE token_hash = %s AND consumed_at IS NULL AND expires_at > %d AND user_id = %d AND tool_name = %s AND binding_type = %s AND binding_value = %s AND overwrite = %d",
				$now_ms,
				$token_hash,
				$now_ms,
				$user_id,
				$tool_name,
				$binding_type,
				$binding_value,
				$overwrite ? 1 : 0
			)
		);

		return 1 === (int) $wpdb->rows_affected;
	}

	/**
	 * Current time in JS-millisecond epoch form.
	 *
	 * @since ??
	 *
	 * @return int
	 */
	public static function now_ms(): int {
		return (int) round( microtime( true ) * 1000 );
	}

	/**
	 * HTTP 500 mint failure. Callers must rollback any open transaction first.
	 *
	 * @since ??
	 *
	 * @return WP_Error
	 */
	private static function _mint_failed_error(): WP_Error {
		return RESTController::response_error(
			'mint_failed',
			esc_html__( 'Failed to mint approval tokens.', 'et_builder_5' ),
			[],
			500
		);
	}

	/**
	 * HTTP 403 when the agent credential is present but the token is missing or invalid.
	 *
	 * @since ??
	 *
	 * @return WP_Error
	 */
	private static function _invalid_token_error(): WP_Error {
		return RESTController::response_error(
			'invalid_approval_token',
			esc_html__( 'A valid approval token is required.', 'et_builder_5' ),
			[],
			403
		);
	}
}
