<?php
/**
 * REST: AiAgentChatController class.
 *
 * Handles all 16 REST endpoints for the D5 AI Agent chat history persistence
 * feature:
 *
 *   GET    /divi/v1/ai-agent-chat/threads          list_threads
 *   POST   /divi/v1/ai-agent-chat/threads/create   create_thread
 *   POST   /divi/v1/ai-agent-chat/threads/reorder  reorder_threads (per-user display order)
 *   GET    /divi/v1/ai-agent-chat/threads/show      show_thread
 *   POST   /divi/v1/ai-agent-chat/threads/update    update_thread
 *   POST   /divi/v1/ai-agent-chat/threads/context   save_context   (JSON blob, no updated_at bump)
 *   POST   /divi/v1/ai-agent-chat/threads/delete    delete_thread  (cascades all 4 tables)
 *   POST   /divi/v1/ai-agent-chat/messages/upsert   upsert_message
 *   POST   /divi/v1/ai-agent-chat/checkpoints/upsert   upsert_checkpoint  (+ inline prune)
 *   POST   /divi/v1/ai-agent-chat/checkpoints/delete   delete_thread_checkpoints
 *   POST   /divi/v1/ai-agent-chat/pending-writes/upsert upsert_pending_write
 *   POST   /divi/v1/ai-agent-chat/restore-points/upsert upsert_restore_point
 *   GET    /divi/v1/ai-agent-chat/restore-points/show   show_restore_point
 *   POST   /divi/v1/ai-agent-chat/threads/restore       restore_thread  (deletes truncated messages/restore-points/post-cut checkpoints)
 *   POST   /divi/v1/ai-agent-chat/checkpoints/upsert/batch   upsert_checkpoint_batch
 *   POST   /divi/v1/ai-agent-chat/pending-writes/upsert/batch upsert_pending_write_batch
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
use WP_REST_Request;
use WP_REST_Response;

/**
 * AI Agent Chat Controller class.
 *
 * @since ??
 */
class AiAgentChatController extends RESTController {

	/**
	 * Maximum accepted character length of `chat_id`.
	 *
	 * Matches the child-table unique-key prefix `chat_id(30)` so two distinct
	 * IDs cannot collide on `INSERT ... ON DUPLICATE KEY UPDATE`.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	const CHAT_ID_MAX_LENGTH = 30;

	/**
	 * Maximum number of chat IDs accepted by the reorder endpoint.
	 *
	 * Larger than a single `list_threads` page (`per_page` max 100) so a
	 * user who has loaded multiple history pages can persist the full
	 * in-session order. Cap is still required so a malformed payload cannot
	 * write an unbounded user-meta array.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	const MAX_THREAD_ORDER_ITEMS = 1000;

	/**
	 * Per-user, per-site option key for the History panel display order.
	 *
	 * Inert display metadata: `list_threads` still scopes strictly by
	 * `user_id`, so a stray/foreign id in this array cannot leak another
	 * user's threads.
	 *
	 * @since ??
	 *
	 * `get_user_option()`/`update_user_option()` store this in user meta and
	 * apply the current site's prefix on multisite, matching the site-scoped
	 * AI chat tables.
	 *
	 * @var string
	 */
	const THREAD_ORDER_OPTION_KEY = 'et_divi_ai_chat_thread_order';

	// -------------------------------------------------------------------------
	// Permission helpers
	// -------------------------------------------------------------------------

	/**
	 * Shared permission callback for all endpoints.
	 *
	 * Checks (in order):
	 *   1. User is logged in.
	 *   2. User can use the Visual Builder.
	 *   3. User has the `edit_posts` capability.
	 *   4. For routes with a `chat_id` argument (except create, batch, and
	 *      payload-cap writes), thread ownership.
	 *
	 * Payload-cap write routes defer ownership to their handlers so an
	 * oversized request returns 413 instead of disclosing thread existence
	 * via permission-time 404. Handlers still verify ownership before SQL.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public static function permission( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return self::response_error( 'not_logged_in', esc_html__( 'You must be logged in.', 'et_builder_5' ), [], 401 );
		}

		if ( ! UserRole::can_current_user_use_visual_builder() ) {
			return self::response_error_permission();
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return self::response_error_permission();
		}

		// Routes without a registered `chat_id` arg (list, batch) cannot validate ownership
		// at callback time; handlers validate per-item ownership inside `data[]` payloads.
		$route_args = $request->get_attributes()['args'] ?? [];
		if ( ! isset( $route_args['chat_id'] ) ) {
			return true;
		}

		// create_thread accepts `chat_id` but creates a new thread rather than accessing an existing one.
		if ( '/divi/v1/ai-agent-chat/threads/create' === $request->get_route() ) {
			return true;
		}

		// Payload-cap writes validate size/shape before ownership in the handler.
		$ownership_deferred_routes = [
			'/divi/v1/ai-agent-chat/threads/context',
			'/divi/v1/ai-agent-chat/messages/upsert',
			'/divi/v1/ai-agent-chat/checkpoints/upsert',
			'/divi/v1/ai-agent-chat/pending-writes/upsert',
			'/divi/v1/ai-agent-chat/restore-points/upsert',
		];

		if ( in_array( $request->get_route(), $ownership_deferred_routes, true ) ) {
			return true;
		}

		$chat_id = $request->get_param( 'chat_id' );
		if ( ! is_string( $chat_id ) || '' === $chat_id ) {
			return true;
		}

		$chat_id = sanitize_text_field( $chat_id );

		// Only validate `thread_id` when the route declares it; `get_param()` can
		// still return body/query values on routes that ignore them (e.g. show,
		// messages/upsert), which would incorrectly fail with `invalid_thread_id`.
		$thread_id = null;
		if ( isset( $route_args['thread_id'] ) ) {
			$thread_id = $request->get_param( 'thread_id' );
			$thread_id = is_string( $thread_id ) && '' !== $thread_id ? sanitize_text_field( $thread_id ) : null;
		}

		return self::verify_thread_ownership( $chat_id, $thread_id );
	}

	/**
	 * Current time in JS-millisecond epoch form.
	 *
	 * All `created_at`/`updated_at` columns store JS milliseconds (BIGINT),
	 * not MySQL DATETIME, so there's no TZ/format translation needed between
	 * the client and the DB (see schema comments in `DiviAIChatSchema`).
	 *
	 * @since ??
	 *
	 * @return int Current time in milliseconds since the epoch.
	 */
	private static function now_ms(): int {
		return (int) round( microtime( true ) * 1000 );
	}

	/**
	 * Bumps a thread's `updated_at` to now, so the history panel's
	 * `ORDER BY updated_at DESC` reflects real conversation activity
	 * (new messages), not just explicit rename/mode-change events.
	 *
	 * Best-effort: intentionally does not check `$wpdb->update()`'s return
	 * value — a missed recency bump is not worth failing the parent request.
	 *
	 * @since ??
	 *
	 * @param string $chat_id The thread's chat_id.
	 *
	 * @return void
	 */
	private static function touch_thread( string $chat_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$wpdb->et_divi_ai_chat_threads,
			[ 'updated_at' => self::now_ms() ],
			[ 'chat_id' => $chat_id ],
			[ '%d' ],
			[ '%s' ]
		);
	}

	/**
	 * Verify that the given `chat_id` belongs to the current user.
	 * Optionally verifies that `$thread_id` belongs to the chat's runtime_thread_id.
	 *
	 * Returns 404 (not 403) on mismatch — this avoids confirming that another
	 * user's thread exists. Admins with `manage_options` bypass the ownership check,
	 * but `thread_id` validation is always enforced.
	 *
	 * @since ??
	 *
	 * @param string      $chat_id   The chat/thread ID to verify.
	 * @param string|null $thread_id Optional. The LangGraph thread_id to validate against this chat.
	 *
	 * @return bool|\WP_Error True if ownership/thread confirmed, WP_Error otherwise.
	 */
	private static function verify_thread_ownership( string $chat_id, ?string $thread_id = null ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id, runtime_thread_id FROM {$wpdb->et_divi_ai_chat_threads} WHERE chat_id = %s LIMIT 1",
				$chat_id
			)
		);

		if ( null === $row ) {
			return self::response_error( 'not_found', esc_html__( 'Thread not found.', 'et_builder_5' ), [], 404 );
		}

		if ( ! current_user_can( 'manage_options' ) && (int) $row->user_id !== (int) get_current_user_id() ) {
			return self::response_error( 'not_found', esc_html__( 'Thread not found.', 'et_builder_5' ), [], 404 );
		}

		if ( null !== $thread_id && ! self::is_base_thread_or_legal_child( (string) $row->runtime_thread_id, $thread_id ) ) {
			return self::response_error( 'invalid_thread_id', esc_html__( 'Invalid thread_id for this chat.', 'et_builder_5' ), [], 403 );
		}

		return true;
	}

	/**
	 * Whether `$candidate` is `$base` or an ownership-legal child thread.
	 *
	 * Legal children are legacy `-step-N`, Build-mode `-turn-N-step-M`, and
	 * asynchronous sub agents under a Build step (`-turn-N-step-M-sub-P`).
	 * `$base` is preg_quoted so metacharacters are treated as literals.
	 *
	 * @since ??
	 *
	 * @param string $base      The posted or stored runtime thread id.
	 * @param string $candidate The thread_id to test.
	 *
	 * @return bool
	 */
	private static function is_base_thread_or_legal_child( string $base, string $candidate ): bool {
		if ( $base === $candidate ) {
			return true;
		}

		return 1 === preg_match( '/^' . preg_quote( $base, '/' ) . '(-step-\d+|-turn-\d+-step-\d+(-sub-\d+)?)$/', $candidate );
	}

	/**
	 * Posted thread_id plus ownership-legal children found among candidates.
	 *
	 * Always includes `$base` so the SQL `IN` list is never empty.
	 *
	 * @since ??
	 *
	 * @param string   $base       The posted or stored runtime thread id.
	 * @param string[] $candidates Distinct `thread_id` values from the database.
	 *
	 * @return string[]
	 */
	private static function collect_legal_thread_ids( string $base, array $candidates ): array {
		$matched = [];

		foreach ( $candidates as $candidate ) {
			$candidate = (string) $candidate;
			if ( self::is_base_thread_or_legal_child( $base, $candidate ) ) {
				$matched[] = $candidate;
			}
		}

		return array_values( array_unique( array_merge( [ $base ], $matched ) ) );
	}

	/**
	 * Shared REST schema for the `chat_id` argument.
	 *
	 * Caps length at `CHAT_ID_MAX_LENGTH` so WP REST enforces the same width as
	 * the child unique-key prefix. `validate_callback` is required; WP does not
	 * apply `maxLength` unless `validate_callback` is set.
	 *
	 * @since ??
	 *
	 * @return array<string, mixed>
	 */
	private static function chat_id_arg(): array {
		return [
			'required'          => true,
			'type'              => 'string',
			'maxLength'         => self::CHAT_ID_MAX_LENGTH,
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		];
	}

	/**
	 * Returns the current user's saved thread display order.
	 *
	 * Raw saved array — no existence/ownership pruning. Client selectors
	 * already discard `chatOrder` ids that are not in the loaded `chats` map.
	 *
	 * @since ??
	 *
	 * @return string[]
	 */
	private static function get_thread_order(): array {
		$order = get_user_option( self::THREAD_ORDER_OPTION_KEY, get_current_user_id() );

		if ( ! is_array( $order ) ) {
			return [];
		}

		return self::sanitize_thread_order( $order );
	}

	/**
	 * Sanitizes, dedupes, and caps a list of chat IDs for user-meta storage.
	 *
	 * @since ??
	 *
	 * @param mixed $chat_ids Raw chat ID list.
	 *
	 * @return string[]
	 */
	private static function sanitize_thread_order( $chat_ids ): array {
		if ( ! is_array( $chat_ids ) ) {
			return [];
		}

		$sanitized = [];
		$seen      = [];

		foreach ( $chat_ids as $chat_id ) {
			if ( ! is_string( $chat_id ) ) {
				continue;
			}

			$chat_id = sanitize_text_field( $chat_id );

			if ( '' === $chat_id || isset( $seen[ $chat_id ] ) ) {
				continue;
			}

			$seen[ $chat_id ] = true;
			$sanitized[]      = $chat_id;

			if ( self::MAX_THREAD_ORDER_ITEMS <= count( $sanitized ) ) {
				break;
			}
		}

		return $sanitized;
	}

	/**
	 * Drops chat IDs that are not live threads owned by the current user.
	 *
	 * Used by `reorder_threads` so a stale persist cannot resurrect a deleted
	 * id, and remainder-append cannot keep re-attaching it. Incoming order is
	 * preserved. Unknown/foreign ids are ignored rather than rejected.
	 *
	 * @since ??
	 *
	 * @param string[] $chat_ids Ordered chat IDs.
	 *
	 * @return string[]
	 */
	private static function filter_existing_thread_ids( array $chat_ids ): array {
		if ( [] === $chat_ids ) {
			return [];
		}

		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $chat_ids ), '%s' ) );
		$user_id      = get_current_user_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- table name and IN placeholders.
		$existing = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT chat_id FROM {$wpdb->et_divi_ai_chat_threads} WHERE user_id = %d AND chat_id IN ($placeholders)",
				...array_merge( [ $user_id ], $chat_ids )
			)
		);

		if ( ! is_array( $existing ) || [] === $existing ) {
			return [];
		}

		$existing_set = array_fill_keys( $existing, true );

		return array_values(
			array_filter(
				$chat_ids,
				static function ( $chat_id ) use ( $existing_set ) {
					return isset( $existing_set[ $chat_id ] );
				}
			)
		);
	}

	/**
	 * Persists the current user's thread display order.
	 *
	 * @since ??
	 *
	 * @param string[] $chat_ids Ordered chat IDs.
	 *
	 * @return void
	 */
	private static function save_thread_order( array $chat_ids ): void {
		update_user_option(
			get_current_user_id(),
			self::THREAD_ORDER_OPTION_KEY,
			self::sanitize_thread_order( $chat_ids )
		);
	}

	/**
	 * Prepends a chat ID to the saved order (new chats appear at the top).
	 *
	 * @since ??
	 *
	 * @param string $chat_id Chat ID to prepend.
	 *
	 * @return void
	 */
	private static function prepend_thread_order( string $chat_id ): void {
		$order = array_values(
			array_filter(
				self::get_thread_order(),
				static function ( $id ) use ( $chat_id ) {
					return $id !== $chat_id;
				}
			)
		);

		array_unshift( $order, $chat_id );
		self::save_thread_order( $order );
	}

	/**
	 * Removes a chat ID from the saved order.
	 *
	 * @since ??
	 *
	 * @param string $chat_id Chat ID to remove.
	 *
	 * @return void
	 */
	private static function remove_from_thread_order( string $chat_id ): void {
		$order = array_values(
			array_filter(
				self::get_thread_order(),
				static function ( $id ) use ( $chat_id ) {
					return $id !== $chat_id;
				}
			)
		);

		self::save_thread_order( $order );
	}

	// -------------------------------------------------------------------------
	// Endpoint: list_threads  GET /threads
	// -------------------------------------------------------------------------

	/**
	 * Returns a paginated list of thread summaries for the current user,
	 * ordered by updated_at DESC.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response
	 */
	public static function list_threads( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$user_id  = get_current_user_id();
		$page     = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
		$per_page = max( 1, min( 100, absint( $request->get_param( 'per_page' ) ?: 50 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT chat_id, runtime_thread_id, title, interaction_mode, created_at, updated_at
				 FROM {$wpdb->et_divi_ai_chat_threads}
				 WHERE user_id = %d
				 ORDER BY updated_at DESC
				 LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->et_divi_ai_chat_threads} WHERE user_id = %d",
				$user_id
			)
		);

		$threads = array_map(
			static function ( $row ) {
				return [
					'chat_id'           => $row->chat_id,
					'runtime_thread_id' => $row->runtime_thread_id,
					'title'             => $row->title,
					'interaction_mode'  => $row->interaction_mode,
					// $wpdb returns every column as a string; cast the JS-ms
					// epoch columns back to int so they serialize as JSON
					// numbers, matching the `number` type on the client.
					'created_at'        => (int) $row->created_at,
					'updated_at'        => (int) $row->updated_at,
				];
			},
			$rows ?: []
		);

		$response = [
			'threads'     => $threads,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
			'page'        => $page,
			'per_page'    => $per_page,
		];

		// The client seeds persisted display order only during cold page-1
		// hydration (and rollback also re-fetches page 1). Avoid repeating the
		// full order array on every later pagination response.
		if ( 1 === $page ) {
			$response['chat_order'] = self::get_thread_order();
		}

		return self::response_success( $response );
	}

	/**
	 * Args for list_threads.
	 *
	 * @return array<string, mixed>
	 */
	public static function list_threads_args(): array {
		return [
			'page'     => [
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 1,
			],
			'per_page' => [
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 50,
			],
		];
	}

	// -------------------------------------------------------------------------
	// Endpoint: reorder_threads  POST /threads/reorder
	// -------------------------------------------------------------------------

	/**
	 * Persists the current user's History panel display order.
	 *
	 * `chat_order` is inert per-user display metadata. This handler does not
	 * call `verify_thread_ownership` per id: `list_threads` already scopes by
	 * `user_id`, and a per-id ownership 403 would race with in-flight duplicate-
	 * chat creation. Incoming ids are the currently loaded (and possibly
	 * reordered) prefix; previously saved ids that were not in the payload
	 * are appended so a page-1-only reorder cannot truncate not-yet-hydrated
	 * threads. The merged list is then filtered to live threads for this user
	 * so a stale persist cannot resurrect a deleted chat.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response
	 */
	public static function reorder_threads( WP_REST_Request $request ): WP_REST_Response {
		$incoming  = self::sanitize_thread_order( $request->get_param( 'chat_ids' ) );
		$seen      = array_fill_keys( $incoming, true );
		$previous  = self::get_thread_order();
		$remainder = [];

		foreach ( $previous as $chat_id ) {
			if ( ! isset( $seen[ $chat_id ] ) ) {
				$remainder[] = $chat_id;
			}
		}

		self::save_thread_order( self::filter_existing_thread_ids( array_merge( $incoming, $remainder ) ) );

		return self::response_success( [ 'success' => true ] );
	}

	/**
	 * Args for reorder_threads.
	 *
	 * @since ??
	 *
	 * @return array<string, mixed>
	 */
	public static function reorder_threads_args(): array {
		return [
			'chat_ids' => [
				'required'          => true,
				'type'              => 'array',
				'maxItems'          => self::MAX_THREAD_ORDER_ITEMS,
				'sanitize_callback' => static function ( $value ) {
					if ( ! is_array( $value ) ) {
						return $value;
					}

					return self::sanitize_thread_order( $value );
				},
				'validate_callback' => 'rest_validate_request_arg',
				'items'             => [
					'type'      => 'string',
					'maxLength' => self::CHAT_ID_MAX_LENGTH,
				],
			],
		];
	}

	// -------------------------------------------------------------------------
	// Endpoint: create_thread  POST /threads/create
	// -------------------------------------------------------------------------

	/**
	 * Creates a new thread row. Idempotent: re-sending the same `chat_id`
	 * (e.g. the automatic single retry on a failed write, see `syncFetch`
	 * client-side) updates the existing row instead of erroring on the
	 * unique-key conflict.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function create_thread( WP_REST_Request $request ) {
		global $wpdb;

		$chat_id           = sanitize_text_field( $request->get_param( 'chat_id' ) );
		$runtime_thread_id = sanitize_text_field( $request->get_param( 'runtime_thread_id' ) );
		$title             = sanitize_text_field( $request->get_param( 'title' ) );
		$interaction_mode  = sanitize_text_field( $request->get_param( 'interaction_mode' ) ?: 'ask' );
		$interaction_mode  = in_array( $interaction_mode, [ 'ask', 'build' ], true ) ? $interaction_mode : 'ask';

		if ( ! $chat_id || ! $runtime_thread_id ) {
			return self::response_error( 'missing_required', esc_html__( 'chat_id and runtime_thread_id are required.', 'et_builder_5' ) );
		}

		$user_id = get_current_user_id();
		$now     = self::now_ms();

		// Check for an existing row first rather than a blind
		// `INSERT ... ON DUPLICATE KEY UPDATE` — a chat_id collision with
		// ANOTHER user's thread must be rejected, not silently overwritten.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->et_divi_ai_chat_threads} WHERE chat_id = %s LIMIT 1",
				$chat_id
			)
		);

		if ( $existing ) {
			if ( (int) $existing->user_id !== $user_id && ! current_user_can( 'manage_options' ) ) {
				// Same generic message/status as verify_thread_ownership() — avoid
				// confirming that another user's chat_id already exists.
				return self::response_error( 'not_found', esc_html__( 'Thread not found.', 'et_builder_5' ), [], 404 );
			}

			// Idempotent retry of an already-created thread (e.g. the client's
			// automatic single retry after a request that actually succeeded
			// server-side but errored/timed out client-side) — update in place.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->update(
				$wpdb->et_divi_ai_chat_threads,
				[
					'title'            => $title,
					'interaction_mode' => $interaction_mode,
				],
				[ 'chat_id' => $chat_id ],
				[ '%s', '%s' ],
				[ '%s' ]
			);

			if ( false === $result ) {
				return self::response_error( 'update_failed', esc_html__( 'Failed to update thread.', 'et_builder_5' ), [], 500 );
			}

			return self::response_success( [ 'success' => true ] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$wpdb->et_divi_ai_chat_threads,
			[
				'chat_id'           => $chat_id,
				'user_id'           => $user_id,
				'runtime_thread_id' => $runtime_thread_id,
				'title'             => $title,
				'interaction_mode'  => $interaction_mode,
				'created_at'        => $now,
				'updated_at'        => $now,
			],
			[ '%s', '%d', '%s', '%s', '%s', '%d', '%d' ]
		);

		if ( false === $result ) {
			return self::response_error( 'create_failed', esc_html__( 'Failed to create thread.', 'et_builder_5' ), [], 500 );
		}

		self::prepend_thread_order( $chat_id );

		return self::response_success( [ 'success' => true ] );
	}

	/**
	 * Args for create_thread.
	 *
	 * @return array<string, mixed>
	 */
	public static function create_thread_args(): array {
		return [
			'chat_id'           => self::chat_id_arg(),
			'runtime_thread_id' => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'title'             => [
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			],
			'interaction_mode'  => [
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'ask',
			],
		];
	}

	// -------------------------------------------------------------------------
	// Endpoint: show_thread  GET /threads/show
	// -------------------------------------------------------------------------

	/**
	 * Returns full detail for a single thread: metadata + messages +
	 * latest 2 checkpoints + their pending-writes.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function show_thread( WP_REST_Request $request ) {
		global $wpdb;

		$chat_id = sanitize_text_field( $request->get_param( 'chat_id' ) );

		if ( ! $chat_id ) {
			return self::response_error( 'missing_chat_id', esc_html__( 'chat_id is required.', 'et_builder_5' ) );
		}

		$ownership = self::verify_thread_ownership( $chat_id );
		if ( is_wp_error( $ownership ) ) {
			return $ownership;
		}

		// Thread metadata.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$thread = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT chat_id, runtime_thread_id, title, interaction_mode, context, created_at, updated_at
				 FROM {$wpdb->et_divi_ai_chat_threads}
				 WHERE chat_id = %s LIMIT 1",
				$chat_id
			)
		);

		if ( null === $thread ) {
			return self::response_error( 'not_found', esc_html__( 'Thread not found.', 'et_builder_5' ), [], 404 );
		}

		// Messages.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$message_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT message_uuid, role, content, steps, timestamp
				 FROM {$wpdb->et_divi_ai_chat_messages}
				 WHERE chat_id = %s
				 ORDER BY timestamp ASC, id ASC",
				$chat_id
			)
		);

		$messages = array_map(
			static function ( $row ) {
				return [
					'message_uuid' => $row->message_uuid,
					'role'         => $row->role,
					'content'      => $row->content,
					'steps'        => json_decode( $row->steps, true ) ?: [],
					'timestamp'    => (int) $row->timestamp,
				];
			},
			$message_rows ?: []
		);

		// Checkpoints for this chat (already bounded to the newest 2 per
		// (thread_id, checkpoint_ns) by the prune-on-write in upsert_checkpoint).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$checkpoint_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT thread_id, checkpoint_ns, checkpoint_id, parent_checkpoint_id,
				        value_type, value, metadata, created_at
				 FROM {$wpdb->et_divi_ai_chat_checkpoints}
				 WHERE chat_id = %s
				 ORDER BY created_at DESC",
				$chat_id
			)
		);

		$checkpoints = array_map(
			static function ( $row ) {
				return [
					'thread_id'            => $row->thread_id,
					'checkpoint_ns'        => $row->checkpoint_ns,
					'checkpoint_id'        => $row->checkpoint_id,
					'parent_checkpoint_id' => $row->parent_checkpoint_id,
					'value_type'           => $row->value_type,
					'value'                => $row->value,
					'metadata'             => $row->metadata,
					// Required so the client can restore LangGraph's own
					// checkpoint ordering/time-travel semantics on resume —
					// without it every hydrated checkpoint looks identical
					// in age to a freshly-created one.
					'created_at'           => (int) $row->created_at,
				];
			},
			$checkpoint_rows ?: []
		);

		// Pending writes for all returned checkpoints.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$pending_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT thread_id, checkpoint_ns, checkpoint_id, task_id, channel,
				        write_index, value_type, value
				 FROM {$wpdb->et_divi_ai_chat_pending_writes}
				 WHERE chat_id = %s
				 ORDER BY id ASC",
				$chat_id
			)
		);

		$pending_writes = array_map(
			static function ( $row ) {
				return [
					'thread_id'     => $row->thread_id,
					'checkpoint_ns' => $row->checkpoint_ns,
					'checkpoint_id' => $row->checkpoint_id,
					'task_id'       => $row->task_id,
					'channel'       => $row->channel,
					'write_index'   => (int) $row->write_index,
					'value_type'    => $row->value_type,
					'value'         => $row->value,
				];
			},
			$pending_rows ?: []
		);

		// Restore-point metadata for this chat. `snapshot_json` is omitted here
		// and fetched lazily via `show_restore_point()` when the user confirms
		// a layout restore — see issue #50546 performance review.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$restore_point_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT message_uuid, captured_at, mutated_domains
				 FROM {$wpdb->et_divi_ai_chat_restore_points}
				 WHERE chat_id = %s
				 ORDER BY captured_at ASC, id ASC",
				$chat_id
			)
		);

		$restore_points = array_map(
			static function ( $row ) {
				return [
					'message_uuid'    => $row->message_uuid,
					'captured_at'     => (int) $row->captured_at,
					'mutated_domains' => json_decode( $row->mutated_domains, true ) ?: [],
				];
			},
			$restore_point_rows ?: []
		);

		// Chat-context blob. Stored as a JSON string; decode into an object for
		// the client (which validates it against `CHAT_CONTEXT_SCHEMA_VERSION`
		// and forward-migrates). Returns null when empty or unparseable so the
		// client falls back to a fresh empty context.
		$context = null;
		if ( is_string( $thread->context ) && '' !== $thread->context ) {
			$decoded = json_decode( $thread->context, true );
			if ( is_array( $decoded ) ) {
				$context = $decoded;
			}
		}

		return self::response_success(
			[
				'chat_id'           => $thread->chat_id,
				'runtime_thread_id' => $thread->runtime_thread_id,
				'title'             => $thread->title,
				'interaction_mode'  => $thread->interaction_mode,
				'context'           => $context,
				'created_at'        => (int) $thread->created_at,
				'updated_at'        => (int) $thread->updated_at,
				'messages'          => $messages,
				'checkpoints'       => $checkpoints,
				'pending_writes'    => $pending_writes,
				'restore_points'    => $restore_points,
			]
		);
	}

	/**
	 * Args for show_thread.
	 *
	 * @return array<string, mixed>
	 */
	public static function show_thread_args(): array {
		return [
			'chat_id' => self::chat_id_arg(),
		];
	}

	// -------------------------------------------------------------------------
	// Endpoint: update_thread  POST /threads/update
	// -------------------------------------------------------------------------

	/**
	 * Updates thread metadata (title and/or interaction_mode).
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function update_thread( WP_REST_Request $request ) {
		global $wpdb;

		$chat_id = sanitize_text_field( $request->get_param( 'chat_id' ) );

		if ( ! $chat_id ) {
			return self::response_error( 'missing_chat_id', esc_html__( 'chat_id is required.', 'et_builder_5' ) );
		}

		$ownership = self::verify_thread_ownership( $chat_id );
		if ( is_wp_error( $ownership ) ) {
			return $ownership;
		}

		$update = [];
		$format = [];

		$title = $request->get_param( 'title' );
		if ( null !== $title ) {
			$update['title'] = sanitize_text_field( $title );
			$format[]        = '%s';
		}

		$mode = $request->get_param( 'interaction_mode' );
		if ( null !== $mode && in_array( $mode, [ 'ask', 'build' ], true ) ) {
			$update['interaction_mode'] = $mode;
			$format[]                   = '%s';
		}

		if ( empty( $update ) ) {
			return self::response_error( 'nothing_to_update', esc_html__( 'No fields to update.', 'et_builder_5' ) );
		}

		// updated_at is BIGINT (JS ms), not MySQL DATETIME, so there's no
		// `ON UPDATE CURRENT_TIMESTAMP` to rely on — bump it explicitly.
		$update['updated_at'] = self::now_ms();
		$format[]             = '%d';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			$wpdb->et_divi_ai_chat_threads,
			$update,
			[ 'chat_id' => $chat_id ],
			$format,
			[ '%s' ]
		);

		if ( false === $result ) {
			return self::response_error( 'update_failed', esc_html__( 'Failed to update thread.', 'et_builder_5' ), [], 500 );
		}

		return self::response_success( [ 'success' => true ] );
	}

	/**
	 * Args for update_thread.
	 *
	 * @return array<string, mixed>
	 */
	public static function update_thread_args(): array {
		return [
			'chat_id'          => self::chat_id_arg(),
			'title'            => [
				'required' => false,
				'type'     => 'string',
			],
			'interaction_mode' => [
				'required' => false,
				'type'     => 'string',
			],
		];
	}

	// -------------------------------------------------------------------------
	// Endpoint: save_context  POST /threads/context
	// -------------------------------------------------------------------------

	/**
	 * Maximum accepted size (in bytes) of the JSON-encoded chat-context blob.
	 *
	 * The context holds the agent's objective, working notes, and TODO list —
	 * small in normal use. This cap guards against a runaway/abusive payload
	 * bloating the `context` LONGTEXT column while staying well under it.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	const MAX_CONTEXT_BYTES = 262144;

	/**
	 * Maximum accepted size (in bytes) of each ordinary persisted field.
	 *
	 * Applied separately to message `content`, encoded `steps`, checkpoint
	 * `metadata`, and pending-write `value`. Matches `MAX_CONTEXT_BYTES`.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	const MAX_STORED_FIELD_BYTES = 262144;

	/**
	 * Maximum accepted size (in bytes) of a serialized LangGraph checkpoint value.
	 *
	 * Checkpoints contain the complete resumable agent-message state, including
	 * provider response metadata, and legitimately exceed the ordinary-field
	 * budget. This remains a finite, checkpoint-only ceiling; metadata retains
	 * the ordinary-field limit and batches retain their aggregate limit.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	const MAX_CHECKPOINT_VALUE_BYTES = 1048576;

	/**
	 * Maximum accepted size (in bytes) of a restore-point `snapshot_json`.
	 *
	 * Restore snapshots are larger than ordinary chat fields by design (serialized
	 * layout plus optional Theme Builder / off-canvas / globals). This is a finite
	 * ceiling, not LONGTEXT capacity.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	const MAX_RESTORE_SNAPSHOT_BYTES = 2097152;

	/**
	 * Maximum number of items accepted by a single batch upsert request.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	const MAX_BATCH_ITEMS = 50;

	/**
	 * Maximum aggregate size (in bytes) of normalized stored strings in one batch.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	const MAX_BATCH_BYTES = 2097152;

	/**
	 * JSON-encodes an array for persistence.
	 *
	 * @since ??
	 *
	 * @param array  $value Array to encode.
	 * @param string $code  Error code returned when encoding fails.
	 *
	 * @return string|\WP_Error Encoded JSON string, or WP_Error on failure.
	 */
	private static function encode_stored_json( array $value, string $code = 'invalid_json' ) {
		$encoded = wp_json_encode( $value );

		if ( false === $encoded || ! is_string( $encoded ) ) {
			return self::response_error( $code, esc_html__( 'Failed to encode stored JSON.', 'et_builder_5' ) );
		}

		return $encoded;
	}

	/**
	 * Rejects a stored string that exceeds the named byte budget.
	 *
	 * Measured with `strlen()` (bytes), matching `save_context()`. Safe metadata
	 * only — never echoes the payload.
	 *
	 * @since ??
	 *
	 * @param string   $value      Exact string that would be bound to SQL.
	 * @param string   $field      Field name reported in the error payload.
	 * @param int      $limit      Byte limit for this field.
	 * @param int|null $item_index Optional 0-based batch item index.
	 *
	 * @return true|\WP_Error True when within budget.
	 */
	private static function assert_stored_string_within_limit( string $value, string $field, int $limit = self::MAX_STORED_FIELD_BYTES, ?int $item_index = null ) {
		if ( strlen( $value ) <= $limit ) {
			return true;
		}

		$data = [
			'field' => $field,
			'limit' => $limit,
		];

		if ( null !== $item_index ) {
			$data['item_index'] = $item_index;
		}

		return self::response_error(
			'payload_too_large',
			esc_html__( 'Payload is too large.', 'et_builder_5' ),
			$data,
			413
		);
	}

	/**
	 * Normalizes checkpoint metadata to the exact string persisted in SQL.
	 *
	 * @since ??
	 *
	 * @param mixed $metadata Raw request metadata (string or array).
	 *
	 * @return string|\WP_Error Normalized metadata string, or WP_Error on failure.
	 */
	private static function normalize_checkpoint_metadata( $metadata ) {
		if ( is_array( $metadata ) ) {
			return self::encode_stored_json( $metadata, 'invalid_metadata' );
		}

		if ( is_string( $metadata ) ) {
			return $metadata;
		}

		return self::response_error( 'invalid_metadata', esc_html__( 'metadata must be a string or object.', 'et_builder_5' ) );
	}

	/**
	 * Validates restore-point snapshot JSON before persistence.
	 *
	 * Size is checked before `json_decode()` so oversized garbage is rejected
	 * cheaply. Shape is object + required string `postContent`; unknown keys
	 * are allowed. The original JSON string is what callers persist.
	 *
	 * @since ??
	 *
	 * @param mixed $snapshot_json Raw `snapshot_json` request value.
	 *
	 * @return true|\WP_Error True when valid and within budget.
	 */
	private static function validate_restore_snapshot_json( $snapshot_json ) {
		if ( ! is_string( $snapshot_json ) || '' === $snapshot_json ) {
			return self::response_error( 'missing_required', esc_html__( 'chat_id, message_uuid, and snapshot_json are required.', 'et_builder_5' ) );
		}

		$size = self::assert_stored_string_within_limit( $snapshot_json, 'snapshot_json', self::MAX_RESTORE_SNAPSHOT_BYTES );
		if ( is_wp_error( $size ) ) {
			return $size;
		}

		$decoded = json_decode( $snapshot_json );
		if ( ! is_object( $decoded ) ) {
			return self::response_error( 'invalid_snapshot', esc_html__( 'Restore point snapshot is invalid.', 'et_builder_5' ) );
		}

		if ( ! isset( $decoded->postContent ) || ! is_string( $decoded->postContent ) ) {
			return self::response_error( 'invalid_snapshot', esc_html__( 'Restore point snapshot is invalid.', 'et_builder_5' ) );
		}

		return true;
	}

	/**
	 * Returns a top-level 413 when a batch's item count or aggregate bytes exceed the cap.
	 *
	 * @since ??
	 *
	 * @param string   $field      Field name reported in the error payload.
	 * @param int      $limit      Count or byte limit that was exceeded.
	 * @param int|null $item_index Optional 0-based batch item index.
	 *
	 * @return \WP_Error
	 */
	private static function payload_too_large_error( string $field, int $limit, ?int $item_index = null ): \WP_Error {
		$data = [
			'field' => $field,
			'limit' => $limit,
		];

		if ( null !== $item_index ) {
			$data['item_index'] = $item_index;
		}

		return self::response_error(
			'payload_too_large',
			esc_html__( 'Payload is too large.', 'et_builder_5' ),
			$data,
			413
		);
	}

	/**
	 * Persists the per-chat continuation context (objective, working notes, and
	 * TODO list) as a JSON blob on the thread row.
	 *
	 * Intentionally a full-blob replace of the single `context` column — the
	 * client is the source of truth for the active session and always sends the
	 * whole (small) context. This endpoint does NOT bump `updated_at`/call
	 * `touch_thread()`: TODO/context ticks must not reorder the history list.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function save_context( WP_REST_Request $request ) {
		global $wpdb;

		$chat_id = sanitize_text_field( $request->get_param( 'chat_id' ) );
		$context = $request->get_param( 'context' );

		if ( ! $chat_id ) {
			return self::response_error( 'missing_chat_id', esc_html__( 'chat_id is required.', 'et_builder_5' ) );
		}

		if ( ! is_string( $context ) || '' === $context ) {
			return self::response_error( 'missing_context', esc_html__( 'context is required.', 'et_builder_5' ) );
		}

		if ( strlen( $context ) > self::MAX_CONTEXT_BYTES ) {
			return self::response_error( 'context_too_large', esc_html__( 'Context payload is too large.', 'et_builder_5' ), [], 413 );
		}

		// Guard against storing a malformed blob — the client always sends a
		// JSON-encoded object, so anything else is a bug/abuse and rejected
		// rather than persisted where it would fail to hydrate later.
		$decoded = json_decode( $context, true );
		if ( ! is_array( $decoded ) ) {
			return self::response_error( 'invalid_context', esc_html__( 'context must be a valid JSON object.', 'et_builder_5' ) );
		}

		$ownership = self::verify_thread_ownership( $chat_id );
		if ( is_wp_error( $ownership ) ) {
			return $ownership;
		}

		// Write ONLY the context column — deliberately no `updated_at` bump.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			$wpdb->et_divi_ai_chat_threads,
			[ 'context' => $context ],
			[ 'chat_id' => $chat_id ],
			[ '%s' ],
			[ '%s' ]
		);

		if ( false === $result ) {
			return self::response_error( 'save_context_failed', esc_html__( 'Failed to save context.', 'et_builder_5' ), [], 500 );
		}

		return self::response_success( [ 'success' => true ] );
	}

	/**
	 * Args for save_context.
	 *
	 * @return array<string, mixed>
	 */
	public static function save_context_args(): array {
		return [
			'chat_id' => self::chat_id_arg(),
			// `context` is a JSON string persisted verbatim; do NOT sanitize
			// with `sanitize_text_field` (it would strip/alter JSON). Validated
			// and size-guarded inside `save_context()` instead.
			'context' => [
				'required' => true,
				'type'     => 'string',
			],
		];
	}

	// -------------------------------------------------------------------------
	// Endpoint: delete_thread  POST /threads/delete
	// -------------------------------------------------------------------------

	/**
	 * Deletes a thread and all associated rows (messages, checkpoints,
	 * pending_writes) in a single transaction, then purges the chat upload
	 * directory.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function delete_thread( WP_REST_Request $request ) {
		global $wpdb;

		$chat_id = sanitize_text_field( $request->get_param( 'chat_id' ) );

		if ( ! $chat_id ) {
			return self::response_error( 'missing_chat_id', esc_html__( 'chat_id is required.', 'et_builder_5' ) );
		}

		$ownership = self::verify_thread_ownership( $chat_id );
		if ( is_wp_error( $ownership ) ) {
			return $ownership;
		}

		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$res1 = $wpdb->delete( $wpdb->et_divi_ai_chat_pending_writes, [ 'chat_id' => $chat_id ], [ '%s' ] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$res2 = $wpdb->delete( $wpdb->et_divi_ai_chat_checkpoints, [ 'chat_id' => $chat_id ], [ '%s' ] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$res3 = $wpdb->delete( $wpdb->et_divi_ai_chat_messages, [ 'chat_id' => $chat_id ], [ '%s' ] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$res4 = $wpdb->delete( $wpdb->et_divi_ai_chat_restore_points, [ 'chat_id' => $chat_id ], [ '%s' ] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$res5 = $wpdb->delete( $wpdb->et_divi_ai_chat_threads, [ 'chat_id' => $chat_id ], [ '%s' ] );

		if ( false === $res1 || false === $res2 || false === $res3 || false === $res4 || false === $res5 ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return self::response_error( 'delete_failed', esc_html__( 'Failed to delete thread.', 'et_builder_5' ), [], 500 );
		}

		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		self::remove_from_thread_order( $chat_id );

		AiAgentImageUtility::purge_chat_upload_dir( $chat_id );

		return self::response_success( [ 'success' => true ] );
	}

	/**
	 * Args for delete_thread.
	 *
	 * @return array<string, mixed>
	 */
	public static function delete_thread_args(): array {
		return [
			'chat_id' => self::chat_id_arg(),
		];
	}

	// -------------------------------------------------------------------------
	// Endpoint: upsert_message  POST /messages/upsert
	// -------------------------------------------------------------------------

	/**
	 * Inserts or updates a single message row (idempotent by message_uuid).
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function upsert_message( WP_REST_Request $request ) {
		global $wpdb;

		$chat_id      = sanitize_text_field( $request->get_param( 'chat_id' ) );
		$message_uuid = sanitize_text_field( $request->get_param( 'message_uuid' ) );
		$role         = sanitize_text_field( $request->get_param( 'role' ) );
		$content      = $request->get_param( 'content' ) ?? '';
		$steps        = $request->get_param( 'steps' );
		$timestamp    = absint( $request->get_param( 'timestamp' ) );

		if ( ! $chat_id || ! $message_uuid || ! $role ) {
			return self::response_error( 'missing_required', esc_html__( 'chat_id, message_uuid, and role are required.', 'et_builder_5' ) );
		}

		$role = in_array( $role, [ 'user', 'assistant' ], true ) ? $role : 'user';

		if ( ! is_string( $content ) ) {
			return self::response_error( 'invalid_content', esc_html__( 'content must be a string.', 'et_builder_5' ) );
		}

		$content_limit = self::assert_stored_string_within_limit( $content, 'content' );
		if ( is_wp_error( $content_limit ) ) {
			return $content_limit;
		}

		if ( ! is_array( $steps ) ) {
			$steps = [];
		} else {
			$steps = array_values( $steps );
		}

		$steps_json = self::encode_stored_json( $steps, 'invalid_steps' );
		if ( is_wp_error( $steps_json ) ) {
			return $steps_json;
		}

		$steps_limit = self::assert_stored_string_within_limit( $steps_json, 'steps' );
		if ( is_wp_error( $steps_limit ) ) {
			return $steps_limit;
		}

		$ownership = self::verify_thread_ownership( $chat_id );
		if ( is_wp_error( $ownership ) ) {
			return $ownership;
		}

		$user_id = get_current_user_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->et_divi_ai_chat_messages}
				 (chat_id, user_id, message_uuid, role, content, steps, timestamp)
				 VALUES (%s, %d, %s, %s, %s, %s, %d)
				 ON DUPLICATE KEY UPDATE
				   content = VALUES(content),
				   steps   = VALUES(steps),
				   role    = VALUES(role)",
				$chat_id,
				$user_id,
				$message_uuid,
				$role,
				$content,
				$steps_json,
				$timestamp
			)
		);

		if ( false === $result ) {
			return self::response_error( 'upsert_failed', esc_html__( 'Failed to upsert message.', 'et_builder_5' ), [], 500 );
		}

		// Bump the parent thread's updated_at so the history panel's
		// `ORDER BY updated_at DESC` reflects actual conversation activity,
		// not just explicit rename/mode-change events.
		self::touch_thread( $chat_id );

		return self::response_success( [ 'success' => true ] );
	}

	/**
	 * Args for upsert_message.
	 *
	 * @return array<string, mixed>
	 */
	public static function upsert_message_args(): array {
		return [
			'chat_id'      => self::chat_id_arg(),
			'message_uuid' => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'role'         => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'content'      => [
				'required' => false,
				'type'     => 'string',
				'default'  => '',
			],
			'steps'        => [
				'required' => false,
				'type'     => 'array',
				'default'  => [],
			],
			'timestamp'    => [
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
		];
	}

	// -------------------------------------------------------------------------
	// Endpoint: upsert_checkpoint  POST /checkpoints/upsert
	// -------------------------------------------------------------------------

	/**
	 * Inserts or updates a checkpoint row, then prunes to keep only the
	 * newest 2 checkpoints per (thread_id, checkpoint_ns).
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function upsert_checkpoint( WP_REST_Request $request ) {
		global $wpdb;

		$chat_id              = sanitize_text_field( $request->get_param( 'chat_id' ) );
		$thread_id            = sanitize_text_field( $request->get_param( 'thread_id' ) );
		$checkpoint_ns        = sanitize_text_field( $request->get_param( 'checkpoint_ns' ) ?? '' );
		$checkpoint_id        = sanitize_text_field( $request->get_param( 'checkpoint_id' ) );
		$parent_checkpoint_id = sanitize_text_field( $request->get_param( 'parent_checkpoint_id' ) ?? '' );
		$value_type           = sanitize_text_field( $request->get_param( 'value_type' ) ?: 'json' );
		$value                = $request->get_param( 'value' ) ?? '';
		$metadata             = $request->get_param( 'metadata' ) ?? 'null';
		// The client's LangGraph-assigned `checkpoint.createdAt` is preferred
		// over a server-generated timestamp so ordering reflects when the
		// checkpoint actually occurred, not when the (possibly debounced or
		// retried) sync request happened to reach the server.
		$created_at           = absint( $request->get_param( 'created_at' ) ) ?: self::now_ms();

		if ( ! $chat_id || ! $thread_id || ! $checkpoint_id ) {
			return self::response_error( 'missing_required', esc_html__( 'chat_id, thread_id, and checkpoint_id are required.', 'et_builder_5' ) );
		}

		if ( ! is_string( $value ) ) {
			return self::response_error( 'invalid_value', esc_html__( 'value must be a string.', 'et_builder_5' ) );
		}

		$metadata_json = self::normalize_checkpoint_metadata( $metadata );
		if ( is_wp_error( $metadata_json ) ) {
			return $metadata_json;
		}

		$value_limit = self::assert_stored_string_within_limit( $value, 'value', self::MAX_CHECKPOINT_VALUE_BYTES );
		if ( is_wp_error( $value_limit ) ) {
			return $value_limit;
		}

		$metadata_limit = self::assert_stored_string_within_limit( $metadata_json, 'metadata' );
		if ( is_wp_error( $metadata_limit ) ) {
			return $metadata_limit;
		}

		$ownership = self::verify_thread_ownership( $chat_id, $thread_id );
		if ( is_wp_error( $ownership ) ) {
			return $ownership;
		}

		$user_id = get_current_user_id();

		// Upsert checkpoint. created_at is intentionally left untouched on
		// conflict (`created_at = created_at`) — a duplicate checkpoint_id
		// means a retried re-send of the exact same checkpoint, so the
		// original insert-time value should win, not the retry's.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->et_divi_ai_chat_checkpoints}
				 (chat_id, user_id, thread_id, checkpoint_ns, checkpoint_id, parent_checkpoint_id, value_type, value, metadata, created_at)
				 VALUES (%s, %d, %s, %s, %s, %s, %s, %s, %s, %d)
				 ON DUPLICATE KEY UPDATE
				   parent_checkpoint_id = VALUES(parent_checkpoint_id),
				   value_type           = VALUES(value_type),
				   value                = VALUES(value),
				   metadata             = VALUES(metadata),
				   created_at           = created_at",
				$chat_id,
				$user_id,
				$thread_id,
				$checkpoint_ns,
				$checkpoint_id,
				$parent_checkpoint_id,
				$value_type,
				$value,
				$metadata_json,
				$created_at
			)
		);

		if ( false === $result ) {
			return self::response_error( 'upsert_failed', esc_html__( 'Failed to upsert checkpoint.', 'et_builder_5' ), [], 500 );
		}

		// Inline prune: keep newest 2 per (thread_id, checkpoint_ns).
		self::prune_checkpoints( $chat_id, $thread_id, $checkpoint_ns );

		return self::response_success( [ 'success' => true ] );
	}

	/**
	 * Prunes checkpoints to keep only the newest 2 per (thread_id, checkpoint_ns),
	 * then deletes orphaned pending_writes for the evicted checkpoints.
	 *
	 * @since ??
	 *
	 * @param string $chat_id       The chat ID.
	 * @param string $thread_id     The thread ID.
	 * @param string $checkpoint_ns The checkpoint namespace.
	 *
	 * @return void
	 */
	private static function prune_checkpoints( string $chat_id, string $thread_id, string $checkpoint_ns ): void {
		global $wpdb;

		// Collect IDs and checkpoint_ids of rows to prune (all but the newest 2).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows_to_delete = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, checkpoint_id
				 FROM {$wpdb->et_divi_ai_chat_checkpoints}
				 WHERE chat_id = %s AND thread_id = %s AND checkpoint_ns = %s
				 ORDER BY created_at DESC
				 LIMIT 18446744073709551615 OFFSET 2",
				$chat_id,
				$thread_id,
				$checkpoint_ns
			)
		);

		if ( empty( $rows_to_delete ) ) {
			return;
		}

		$ids             = array_map( 'intval', wp_list_pluck( $rows_to_delete, 'id' ) );
		$evicted_cp_ids  = array_map( 'sanitize_text_field', wp_list_pluck( $rows_to_delete, 'checkpoint_id' ) );
		$ids_placeholder = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// Delete evicted checkpoints.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->et_divi_ai_chat_checkpoints} WHERE id IN ($ids_placeholder)", ...$ids ) );

		// Delete orphaned pending_writes.
		if ( ! empty( $evicted_cp_ids ) ) {
			$cp_placeholder = implode( ',', array_fill( 0, count( $evicted_cp_ids ), '%s' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->et_divi_ai_chat_pending_writes}
					 WHERE chat_id = %s AND thread_id = %s AND checkpoint_ns = %s AND checkpoint_id IN ($cp_placeholder)",
					...array_merge( [ $chat_id, $thread_id, $checkpoint_ns ], $evicted_cp_ids )
				)
			);
		}
	}

	/**
	 * Args for upsert_checkpoint.
	 *
	 * @return array<string, mixed>
	 */
	public static function upsert_checkpoint_args(): array {
		return [
			'chat_id'              => self::chat_id_arg(),
			'thread_id'            => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'checkpoint_ns'        => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
			'checkpoint_id'        => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'parent_checkpoint_id' => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
			'value_type'           => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'json' ],
			'value'                => [ 'required' => true, 'type' => 'string' ],
			'metadata'             => [ 'required' => false, 'default' => 'null' ],
			'created_at'           => [ 'required' => false, 'type' => 'integer', 'sanitize_callback' => 'absint' ],
		];
	}

	// -------------------------------------------------------------------------
	// Endpoint: delete_thread_checkpoints  POST /checkpoints/delete
	// -------------------------------------------------------------------------

	/**
	 * Deletes checkpoints and pending_writes for the posted thread_id and its
	 * ownership-legal children (`-step-N`, `-turn-N-step-M`, and `-turn-N-step-M-sub-P`).
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function delete_thread_checkpoints( WP_REST_Request $request ) {
		global $wpdb;

		$chat_id   = sanitize_text_field( $request->get_param( 'chat_id' ) );
		$thread_id = sanitize_text_field( $request->get_param( 'thread_id' ) );

		if ( ! $chat_id || ! $thread_id ) {
			return self::response_error( 'missing_required', esc_html__( 'chat_id and thread_id are required.', 'et_builder_5' ) );
		}

		$ownership = self::verify_thread_ownership( $chat_id, $thread_id );
		if ( is_wp_error( $ownership ) ) {
			return $ownership;
		}

		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		// Enumerate literal thread_id values from both tables so orphan pending
		// writes (no matching checkpoint) are still collected, then match
		// children in PHP. MySQL only sees an IN list of exact strings.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$checkpoint_thread_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT thread_id FROM {$wpdb->et_divi_ai_chat_checkpoints} WHERE chat_id = %s",
				$chat_id
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$pending_write_thread_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT thread_id FROM {$wpdb->et_divi_ai_chat_pending_writes} WHERE chat_id = %s",
				$chat_id
			)
		);

		$match_ids          = self::collect_legal_thread_ids(
			$thread_id,
			array_merge(
				is_array( $checkpoint_thread_ids ) ? $checkpoint_thread_ids : [],
				is_array( $pending_write_thread_ids ) ? $pending_write_thread_ids : []
			)
		);
		$thread_placeholder = implode( ',', array_fill( 0, count( $match_ids ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$res1 = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->et_divi_ai_chat_pending_writes} WHERE chat_id = %s AND thread_id IN ($thread_placeholder)",
				...array_merge( [ $chat_id ], $match_ids )
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$res2 = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->et_divi_ai_chat_checkpoints} WHERE chat_id = %s AND thread_id IN ($thread_placeholder)",
				...array_merge( [ $chat_id ], $match_ids )
			)
		);

		if ( false === $res1 || false === $res2 ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return self::response_error( 'delete_failed', esc_html__( 'Failed to delete checkpoints.', 'et_builder_5' ), [], 500 );
		}

		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		return self::response_success( [ 'success' => true ] );
	}

	/**
	 * Args for delete_thread_checkpoints.
	 *
	 * @return array<string, mixed>
	 */
	public static function delete_thread_checkpoints_args(): array {
		return [
			'chat_id'   => self::chat_id_arg(),
			'thread_id' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
		];
	}

	// -------------------------------------------------------------------------
	// Endpoint: upsert_pending_write  POST /pending-writes/upsert
	// -------------------------------------------------------------------------

	/**
	 * Inserts or updates a pending-write row (idempotent by the unique key
	 * thread_id + checkpoint_ns + checkpoint_id + task_id).
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function upsert_pending_write( WP_REST_Request $request ) {
		global $wpdb;

		$chat_id       = sanitize_text_field( $request->get_param( 'chat_id' ) );
		$thread_id     = sanitize_text_field( $request->get_param( 'thread_id' ) );
		$checkpoint_ns = sanitize_text_field( $request->get_param( 'checkpoint_ns' ) ?? '' );
		$checkpoint_id = sanitize_text_field( $request->get_param( 'checkpoint_id' ) );
		$task_id       = sanitize_text_field( $request->get_param( 'task_id' ) );
		$channel       = sanitize_text_field( $request->get_param( 'channel' ) );
		$write_index   = absint( $request->get_param( 'write_index' ) );
		$value_type    = sanitize_text_field( $request->get_param( 'value_type' ) ?: 'json' );
		$value         = $request->get_param( 'value' ) ?? '';

		if ( ! $chat_id || ! $thread_id || ! $checkpoint_id || ! $task_id || ! $channel ) {
			return self::response_error( 'missing_required', esc_html__( 'chat_id, thread_id, checkpoint_id, task_id, and channel are required.', 'et_builder_5' ) );
		}

		if ( ! is_string( $value ) ) {
			return self::response_error( 'invalid_value', esc_html__( 'value must be a string.', 'et_builder_5' ) );
		}

		$value_limit = self::assert_stored_string_within_limit( $value, 'value' );
		if ( is_wp_error( $value_limit ) ) {
			return $value_limit;
		}

		$ownership = self::verify_thread_ownership( $chat_id, $thread_id );
		if ( is_wp_error( $ownership ) ) {
			return $ownership;
		}

		$user_id    = get_current_user_id();
		$created_at = self::now_ms();

		// Note: write_index is part of the unique key itself (see
		// DiviAIChatSchema) — it's included in VALUES() below but never in
		// the ON DUPLICATE KEY UPDATE clause since a conflict already means
		// it matched.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->et_divi_ai_chat_pending_writes}
				 (chat_id, user_id, thread_id, checkpoint_ns, checkpoint_id, task_id, channel, write_index, value_type, value, created_at)
				 VALUES (%s, %d, %s, %s, %s, %s, %s, %d, %s, %s, %d)
				 ON DUPLICATE KEY UPDATE
				   channel    = VALUES(channel),
				   value_type = VALUES(value_type),
				   value      = VALUES(value)",
				$chat_id,
				$user_id,
				$thread_id,
				$checkpoint_ns,
				$checkpoint_id,
				$task_id,
				$channel,
				$write_index,
				$value_type,
				$value,
				$created_at
			)
		);

		if ( false === $result ) {
			return self::response_error( 'upsert_failed', esc_html__( 'Failed to upsert pending write.', 'et_builder_5' ), [], 500 );
		}

		return self::response_success( [ 'success' => true ] );
	}

	/**
	 * Args for upsert_pending_write.
	 *
	 * @return array<string, mixed>
	 */
	public static function upsert_pending_write_args(): array {
		return [
			'chat_id'       => self::chat_id_arg(),
			'thread_id'     => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'checkpoint_ns' => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
			'checkpoint_id' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'task_id'       => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'channel'       => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'write_index'   => [ 'required' => false, 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 0 ],
			'value_type'    => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'json' ],
			'value'         => [ 'required' => true, 'type' => 'string' ],
		];
	}
	// -------------------------------------------------------------------------
	// Endpoint: upsert_restore_point  POST /restore-points/upsert
	// -------------------------------------------------------------------------

	/**
	 * Inserts or updates a single restore-point row (idempotent by
	 * (chat_id, message_uuid)) — the capture-time half of the "Restore to
	 * here" feature. Persisting here is what allows a restore point
	 * to survive a page reload; the restore-time deletion happens separately
	 * in `restore_thread()`.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function upsert_restore_point( WP_REST_Request $request ) {
		global $wpdb;

		$chat_id         = sanitize_text_field( $request->get_param( 'chat_id' ) );
		$message_uuid    = sanitize_text_field( $request->get_param( 'message_uuid' ) );
		$captured_at     = absint( $request->get_param( 'captured_at' ) );
		$mutated_domains = $request->get_param( 'mutated_domains' );
		$snapshot_json   = $request->get_param( 'snapshot_json' ) ?? '';

		if ( ! $chat_id || ! $message_uuid ) {
			return self::response_error( 'missing_required', esc_html__( 'chat_id, message_uuid, and snapshot_json are required.', 'et_builder_5' ) );
		}

		$snapshot_valid = self::validate_restore_snapshot_json( $snapshot_json );
		if ( is_wp_error( $snapshot_valid ) ) {
			return $snapshot_valid;
		}

		if ( ! is_array( $mutated_domains ) ) {
			$mutated_domains = [];
		}

		$mutated_domains_json = self::encode_stored_json( array_values( $mutated_domains ), 'invalid_mutated_domains' );
		if ( is_wp_error( $mutated_domains_json ) ) {
			return $mutated_domains_json;
		}

		$ownership = self::verify_thread_ownership( $chat_id );
		if ( is_wp_error( $ownership ) ) {
			return $ownership;
		}

		$user_id = get_current_user_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->et_divi_ai_chat_restore_points}
				 (chat_id, user_id, message_uuid, captured_at, mutated_domains, snapshot_json, created_at)
				 VALUES (%s, %d, %s, %d, %s, %s, %d)
				 ON DUPLICATE KEY UPDATE
				   captured_at     = VALUES(captured_at),
				   mutated_domains = VALUES(mutated_domains),
				   snapshot_json   = VALUES(snapshot_json)",
				$chat_id,
				$user_id,
				$message_uuid,
				$captured_at,
				$mutated_domains_json,
				$snapshot_json,
				self::now_ms()
			)
		);

		if ( false === $result ) {
			return self::response_error( 'upsert_failed', esc_html__( 'Failed to upsert restore point.', 'et_builder_5' ), [], 500 );
		}

		return self::response_success( [ 'success' => true ] );
	}

	/**
	 * Args for upsert_restore_point.
	 *
	 * @return array<string, mixed>
	 */
	public static function upsert_restore_point_args(): array {
		return [
			'chat_id'         => self::chat_id_arg(),
			'message_uuid'    => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'captured_at'     => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ],
			'mutated_domains' => [ 'required' => false, 'type' => 'array', 'default' => [] ],
			'snapshot_json'   => [ 'required' => true, 'type' => 'string' ],
		];
	}

	// -------------------------------------------------------------------------
	// Endpoint: show_restore_point  GET /restore-points/show
	// -------------------------------------------------------------------------

	/**
	 * Returns a single restore-point row including its layout snapshot.
	 *
	 * Fetched lazily on restore confirm so `show_thread` can hydrate
	 * metadata-only restore points without transferring every LONGTEXT
	 * snapshot on thread open/reload.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function show_restore_point( WP_REST_Request $request ) {
		global $wpdb;

		$chat_id      = sanitize_text_field( $request->get_param( 'chat_id' ) );
		$message_uuid = sanitize_text_field( $request->get_param( 'message_uuid' ) );

		if ( ! $chat_id || ! $message_uuid ) {
			return self::response_error( 'missing_required', esc_html__( 'chat_id and message_uuid are required.', 'et_builder_5' ) );
		}

		$ownership = self::verify_thread_ownership( $chat_id );
		if ( is_wp_error( $ownership ) ) {
			return $ownership;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT message_uuid, captured_at, mutated_domains, snapshot_json
				 FROM {$wpdb->et_divi_ai_chat_restore_points}
				 WHERE chat_id = %s AND message_uuid = %s
				 LIMIT 1",
				$chat_id,
				$message_uuid
			)
		);

		if ( ! $row ) {
			return self::response_error( 'not_found', esc_html__( 'Restore point not found.', 'et_builder_5' ), [], 404 );
		}

		$snapshot = json_decode( $row->snapshot_json, true );
		if ( ! is_array( $snapshot ) ) {
			return self::response_error( 'invalid_snapshot', esc_html__( 'Restore point snapshot is invalid.', 'et_builder_5' ), [], 500 );
		}

		return self::response_success(
			[
				'message_uuid'    => $row->message_uuid,
				'captured_at'     => (int) $row->captured_at,
				'mutated_domains' => json_decode( $row->mutated_domains, true ) ?: [],
				'snapshot'        => $snapshot,
			]
		);
	}

	/**
	 * Args for show_restore_point.
	 *
	 * @return array<string, mixed>
	 */
	public static function show_restore_point_args(): array {
		return [
			'chat_id'      => self::chat_id_arg(),
			'message_uuid' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
		];
	}

	// -------------------------------------------------------------------------
	// Endpoint: restore_thread  POST /threads/restore
	// -------------------------------------------------------------------------

	/**
	 * Deletes messages after the restore-target user prompt (and their
	 * restore-point rows) plus post-cut checkpoints/pending-writes. The
	 * restore-target prompt itself is kept. Restore-time half of
	 * "Restore to here".
	 *
	 * This endpoint only deletes; it never writes/creates restore-point rows
	 * (those are persisted separately by `upsert_restore_point()` at capture
	 * time).
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function restore_thread( WP_REST_Request $request ) {
		global $wpdb;

		$chat_id              = sanitize_text_field( $request->get_param( 'chat_id' ) );
		$thread_id            = sanitize_text_field( $request->get_param( 'thread_id' ) );
		$delete_message_uuids = $request->get_param( 'delete_message_uuids' );
		$checkpoint_cut_at    = absint( $request->get_param( 'checkpoint_cut_at' ) );

		if ( ! $chat_id || ! $thread_id || ! is_array( $delete_message_uuids ) ) {
			return self::response_error( 'missing_required', esc_html__( 'chat_id, thread_id, and delete_message_uuids are required.', 'et_builder_5' ) );
		}

		$ownership = self::verify_thread_ownership( $chat_id, $thread_id );
		if ( is_wp_error( $ownership ) ) {
			return $ownership;
		}

		// Empty list is valid when restoring the last user prompt (keep the
		// anchor; nothing after it). Checkpoint cut still runs below.
		$message_uuids = array_map( 'sanitize_text_field', $delete_message_uuids );

		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		$res1 = 0;
		$res2 = 0;

		if ( ! empty( $message_uuids ) ) {
			$placeholder = implode( ',', array_fill( 0, count( $message_uuids ), '%s' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$res1 = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->et_divi_ai_chat_messages} WHERE chat_id = %s AND message_uuid IN ($placeholder)",
					...array_merge( [ $chat_id ], $message_uuids )
				)
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$res2 = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->et_divi_ai_chat_restore_points} WHERE chat_id = %s AND message_uuid IN ($placeholder)",
					...array_merge( [ $chat_id ], $message_uuids )
				)
			);
		}

		/*
		 * Enumerate literal thread_id values for this chat,
		 * then match children in PHP (preg_quote + ownership suffixes).
		 * MySQL only sees an IN list of exact strings.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$candidate_thread_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT thread_id FROM {$wpdb->et_divi_ai_chat_checkpoints} WHERE chat_id = %s",
				$chat_id
			)
		);

		$match_ids          = self::collect_legal_thread_ids(
			$thread_id,
			is_array( $candidate_thread_ids ) ? $candidate_thread_ids : []
		);
		$thread_placeholder = implode( ',', array_fill( 0, count( $match_ids ), '%s' ) );

		// Delete pending writes whose *owning checkpoint* is post-cut — not by
		// the pending-write row's own `created_at` (stamped at upsert/sync time
		// and often much later than the checkpoint). Matches the client
		// reducer's owning-checkpoint cut decision. Must run BEFORE deleting
		// the post-cut checkpoints so the JOIN still finds them.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$res3 = $wpdb->query(
			$wpdb->prepare(
				"DELETE pw FROM {$wpdb->et_divi_ai_chat_pending_writes} pw
				 INNER JOIN {$wpdb->et_divi_ai_chat_checkpoints} cp
				   ON pw.chat_id = cp.chat_id
				  AND pw.thread_id = cp.thread_id
				  AND pw.checkpoint_ns = cp.checkpoint_ns
				  AND pw.checkpoint_id = cp.checkpoint_id
				 WHERE pw.chat_id = %s
				   AND pw.thread_id IN ($thread_placeholder)
				   AND cp.created_at > %d",
				...array_merge( [ $chat_id ], $match_ids, [ $checkpoint_cut_at ] )
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$res4 = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->et_divi_ai_chat_checkpoints} WHERE chat_id = %s AND thread_id IN ($thread_placeholder) AND created_at > %d",
				...array_merge( [ $chat_id ], $match_ids, [ $checkpoint_cut_at ] )
			)
		);

		if ( false === $res1 || false === $res2 || false === $res3 || false === $res4 ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return self::response_error( 'restore_failed', esc_html__( 'Failed to restore thread.', 'et_builder_5' ), [], 500 );
		}

		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		return self::response_success( [ 'success' => true ] );
	}

	/**
	 * Args for restore_thread.
	 *
	 * @return array<string, mixed>
	 */
	public static function restore_thread_args(): array {
		return [
			'chat_id'              => self::chat_id_arg(),
			'thread_id'            => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'delete_message_uuids' => [ 'required' => true, 'type' => 'array' ],
			'checkpoint_cut_at'    => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ],
		];
	}

	/**
	 * Args for batch routes.
	 *
	 * @return array<string, mixed>
	 */
	public static function batch_args(): array {
		return [
			'data' => [
				'required' => true,
				'type'     => 'array',
			],
		];
	}

	/**
	 * Inserts or updates multiple checkpoints.
	 *
	 * Size/count violations reject the whole request with HTTP 413 before any
	 * ownership lookup or mutation. Ordinary missing-field / type / ownership
	 * failures stay index-aligned HTTP 200 entries.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function upsert_checkpoint_batch( WP_REST_Request $request ) {
		global $wpdb;
		$data = $request->get_param( 'data' );

		if ( ! is_array( $data ) || empty( $data ) ) {
			return self::response_error( 'invalid_data', esc_html__( 'Data must be a non-empty array.', 'et_builder_5' ) );
		}

		if ( count( $data ) > self::MAX_BATCH_ITEMS ) {
			return self::payload_too_large_error( 'data', self::MAX_BATCH_ITEMS );
		}

		$responses       = [];
		$accepted        = [];
		$aggregate_bytes = 0;

		foreach ( $data as $index => $item ) {
			if ( ! is_array( $item ) ) {
				$responses[ $index ] = [ 'error' => esc_html__( 'Invalid batch item.', 'et_builder_5' ) ];
				continue;
			}

			$chat_id              = sanitize_text_field( $item['chat_id'] ?? '' );
			$thread_id            = sanitize_text_field( $item['thread_id'] ?? '' );
			$checkpoint_ns        = sanitize_text_field( $item['checkpoint_ns'] ?? '' );
			$checkpoint_id        = sanitize_text_field( $item['checkpoint_id'] ?? '' );
			$parent_checkpoint_id = sanitize_text_field( $item['parent_checkpoint_id'] ?? '' );
			$value_type           = sanitize_text_field( ! empty( $item['value_type'] ) ? $item['value_type'] : 'json' );
			$value                = $item['value'] ?? '';
			$metadata             = $item['metadata'] ?? 'null';
			$created_at           = absint( $item['created_at'] ?? 0 ) ?: self::now_ms();

			if ( ! $chat_id || ! $thread_id || ! $checkpoint_id ) {
				$responses[ $index ] = [ 'error' => esc_html__( 'chat_id, thread_id, and checkpoint_id are required.', 'et_builder_5' ) ];
				continue;
			}

			if ( self::CHAT_ID_MAX_LENGTH < mb_strlen( $chat_id, 'UTF-8' ) ) {
				$responses[ $index ] = [
					'error' => sprintf(
						/* translators: %d: maximum chat_id character length. */
						esc_html__( 'chat_id must be %d characters or fewer.', 'et_builder_5' ),
						self::CHAT_ID_MAX_LENGTH
					),
				];
				continue;
			}

			if ( ! is_string( $value ) ) {
				$responses[ $index ] = [ 'error' => esc_html__( 'value must be a string.', 'et_builder_5' ) ];
				continue;
			}

			$metadata_json = self::normalize_checkpoint_metadata( $metadata );
			if ( is_wp_error( $metadata_json ) ) {
				$responses[ $index ] = [ 'error' => $metadata_json->get_error_message() ];
				continue;
			}

			$value_limit = self::assert_stored_string_within_limit( $value, 'value', self::MAX_CHECKPOINT_VALUE_BYTES, (int) $index );
			if ( is_wp_error( $value_limit ) ) {
				return $value_limit;
			}

			$metadata_limit = self::assert_stored_string_within_limit( $metadata_json, 'metadata', self::MAX_STORED_FIELD_BYTES, (int) $index );
			if ( is_wp_error( $metadata_limit ) ) {
				return $metadata_limit;
			}

			$item_bytes = strlen( $value ) + strlen( $metadata_json );
			if ( ( $aggregate_bytes + $item_bytes ) > self::MAX_BATCH_BYTES ) {
				return self::payload_too_large_error( 'data', self::MAX_BATCH_BYTES, (int) $index );
			}

			$aggregate_bytes   += $item_bytes;
			$accepted[ $index ] = [
				'chat_id'              => $chat_id,
				'thread_id'            => $thread_id,
				'checkpoint_ns'        => $checkpoint_ns,
				'checkpoint_id'        => $checkpoint_id,
				'parent_checkpoint_id' => $parent_checkpoint_id,
				'value_type'           => $value_type,
				'value'                => $value,
				'metadata'             => $metadata_json,
				'created_at'           => $created_at,
			];
		}

		$user_id         = get_current_user_id();
		$ownership_cache = [];
		$values          = [];
		$placeholders    = [];
		$prune_targets   = [];

		foreach ( $accepted as $index => $row ) {
			$cache_key = $row['chat_id'] . '|' . $row['thread_id'];
			if ( ! isset( $ownership_cache[ $cache_key ] ) ) {
				$ownership_cache[ $cache_key ] = self::verify_thread_ownership( $row['chat_id'], $row['thread_id'] );
			}
			if ( is_wp_error( $ownership_cache[ $cache_key ] ) ) {
				$responses[ $index ] = [ 'error' => $ownership_cache[ $cache_key ]->get_error_message() ];
				continue;
			}

			array_push(
				$values,
				$row['chat_id'],
				$user_id,
				$row['thread_id'],
				$row['checkpoint_ns'],
				$row['checkpoint_id'],
				$row['parent_checkpoint_id'],
				$row['value_type'],
				$row['value'],
				$row['metadata'],
				$row['created_at']
			);

			$placeholders[] = '(%s, %d, %s, %s, %s, %s, %s, %s, %s, %d)';
			$prune_targets[ $row['thread_id'] . '|' . $row['checkpoint_ns'] ] = [
				$row['chat_id'],
				$row['thread_id'],
				$row['checkpoint_ns'],
			];
			$responses[ $index ] = [ 'success' => true ];
		}

		ksort( $responses );

		if ( ! empty( $values ) ) {
			$query = "INSERT INTO {$wpdb->et_divi_ai_chat_checkpoints}
				 (chat_id, user_id, thread_id, checkpoint_ns, checkpoint_id, parent_checkpoint_id, value_type, value, metadata, created_at)
				 VALUES " . implode( ', ', $placeholders ) . "
				 ON DUPLICATE KEY UPDATE
				   parent_checkpoint_id = VALUES(parent_checkpoint_id),
				   value_type           = VALUES(value_type),
				   value                = VALUES(value),
				   metadata             = VALUES(metadata),
				   created_at           = created_at";

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->query( $wpdb->prepare( $query, ...$values ) );

			if ( false === $result ) {
				return self::response_error( 'upsert_batch_failed', esc_html__( 'Failed to upsert checkpoints batch.', 'et_builder_5' ), [], 500 );
			}

			foreach ( $prune_targets as $target ) {
				self::prune_checkpoints( $target[0], $target[1], $target[2] );
			}
		}

		return rest_ensure_response( array_values( $responses ) );
	}

	/**
	 * Inserts or updates multiple pending writes.
	 *
	 * Size/count violations reject the whole request with HTTP 413 before any
	 * ownership lookup or mutation. Ordinary missing-field / type / ownership
	 * failures stay index-aligned HTTP 200 entries.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function upsert_pending_write_batch( WP_REST_Request $request ) {
		global $wpdb;
		$data = $request->get_param( 'data' );

		if ( ! is_array( $data ) || empty( $data ) ) {
			return self::response_error( 'invalid_data', esc_html__( 'Data must be a non-empty array.', 'et_builder_5' ) );
		}

		if ( count( $data ) > self::MAX_BATCH_ITEMS ) {
			return self::payload_too_large_error( 'data', self::MAX_BATCH_ITEMS );
		}

		$responses       = [];
		$accepted        = [];
		$aggregate_bytes = 0;

		foreach ( $data as $index => $item ) {
			if ( ! is_array( $item ) ) {
				$responses[ $index ] = [ 'error' => esc_html__( 'Invalid batch item.', 'et_builder_5' ) ];
				continue;
			}

			$chat_id       = sanitize_text_field( $item['chat_id'] ?? '' );
			$thread_id     = sanitize_text_field( $item['thread_id'] ?? '' );
			$checkpoint_ns = sanitize_text_field( $item['checkpoint_ns'] ?? '' );
			$checkpoint_id = sanitize_text_field( $item['checkpoint_id'] ?? '' );
			$task_id       = sanitize_text_field( $item['task_id'] ?? '' );
			$channel       = sanitize_text_field( $item['channel'] ?? '' );
			$write_index   = absint( $item['write_index'] ?? 0 );
			$value_type    = sanitize_text_field( ! empty( $item['value_type'] ) ? $item['value_type'] : 'json' );
			$value         = $item['value'] ?? '';
			$created_at    = self::now_ms();

			if ( ! $chat_id || ! $thread_id || ! $checkpoint_id || ! $task_id || ! $channel ) {
				$responses[ $index ] = [ 'error' => esc_html__( 'chat_id, thread_id, checkpoint_id, task_id, and channel are required.', 'et_builder_5' ) ];
				continue;
			}

			if ( self::CHAT_ID_MAX_LENGTH < mb_strlen( $chat_id, 'UTF-8' ) ) {
				$responses[ $index ] = [
					'error' => sprintf(
						/* translators: %d: maximum chat_id character length. */
						esc_html__( 'chat_id must be %d characters or fewer.', 'et_builder_5' ),
						self::CHAT_ID_MAX_LENGTH
					),
				];
				continue;
			}

			if ( ! is_string( $value ) ) {
				$responses[ $index ] = [ 'error' => esc_html__( 'value must be a string.', 'et_builder_5' ) ];
				continue;
			}

			$value_limit = self::assert_stored_string_within_limit( $value, 'value', self::MAX_STORED_FIELD_BYTES, (int) $index );
			if ( is_wp_error( $value_limit ) ) {
				return $value_limit;
			}

			$item_bytes = strlen( $value );
			if ( ( $aggregate_bytes + $item_bytes ) > self::MAX_BATCH_BYTES ) {
				return self::payload_too_large_error( 'data', self::MAX_BATCH_BYTES, (int) $index );
			}

			$aggregate_bytes   += $item_bytes;
			$accepted[ $index ] = [
				'chat_id'       => $chat_id,
				'thread_id'     => $thread_id,
				'checkpoint_ns' => $checkpoint_ns,
				'checkpoint_id' => $checkpoint_id,
				'task_id'       => $task_id,
				'channel'       => $channel,
				'write_index'   => $write_index,
				'value_type'    => $value_type,
				'value'         => $value,
				'created_at'    => $created_at,
			];
		}

		$user_id         = get_current_user_id();
		$ownership_cache = [];
		$values          = [];
		$placeholders    = [];

		foreach ( $accepted as $index => $row ) {
			$cache_key = $row['chat_id'] . '|' . $row['thread_id'];
			if ( ! isset( $ownership_cache[ $cache_key ] ) ) {
				$ownership_cache[ $cache_key ] = self::verify_thread_ownership( $row['chat_id'], $row['thread_id'] );
			}
			if ( is_wp_error( $ownership_cache[ $cache_key ] ) ) {
				$responses[ $index ] = [ 'error' => $ownership_cache[ $cache_key ]->get_error_message() ];
				continue;
			}

			array_push(
				$values,
				$row['chat_id'],
				$user_id,
				$row['thread_id'],
				$row['checkpoint_ns'],
				$row['checkpoint_id'],
				$row['task_id'],
				$row['channel'],
				$row['write_index'],
				$row['value_type'],
				$row['value'],
				$row['created_at']
			);

			$placeholders[]      = '(%s, %d, %s, %s, %s, %s, %s, %d, %s, %s, %d)';
			$responses[ $index ] = [ 'success' => true ];
		}

		ksort( $responses );

		if ( ! empty( $values ) ) {
			$query = "INSERT INTO {$wpdb->et_divi_ai_chat_pending_writes}
				 (chat_id, user_id, thread_id, checkpoint_ns, checkpoint_id, task_id, channel, write_index, value_type, value, created_at)
				 VALUES " . implode( ', ', $placeholders ) . "
				 ON DUPLICATE KEY UPDATE
				   channel    = VALUES(channel),
				   value_type = VALUES(value_type),
				   value      = VALUES(value)";

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->query( $wpdb->prepare( $query, ...$values ) );

			if ( false === $result ) {
				return self::response_error( 'upsert_batch_failed', esc_html__( 'Failed to upsert pending writes batch.', 'et_builder_5' ), [], 500 );
			}
		}

		return rest_ensure_response( array_values( $responses ) );
	}
}
