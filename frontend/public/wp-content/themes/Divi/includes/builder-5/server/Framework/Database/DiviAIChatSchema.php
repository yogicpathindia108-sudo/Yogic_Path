<?php
/**
 * DiviAIChatSchema.
 *
 * Installs and upgrades the custom MySQL tables used by the D5 AI Agent
 * chat history persistence feature and HITL approval tokens.
 *
 * Tables:
 *   - {prefix}et_divi_ai_chat_threads      — one row per chat session
 *   - {prefix}et_divi_ai_chat_messages     — user + assistant messages
 *   - {prefix}et_divi_ai_chat_checkpoints  — LangGraph checkpoint blobs
 *   - {prefix}et_divi_ai_chat_pending_writes — LangGraph pending-write blobs
 *   - {prefix}et_divi_ai_chat_restore_points — "Restore to here" builder snapshots
 *   - {prefix}et_divi_ai_approval_tokens   — single-use HITL approval tokens
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Framework\Database;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * DiviAIChatSchema class.
 *
 * @since ??
 */
class DiviAIChatSchema {

	/**
	 * Schema version constant. Bump this string whenever the schema changes
	 * so that `maybe_install()` runs `dbDelta()` again.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	const DB_VERSION = '0.4';

	/**
	 * WordPress option key that stores the installed schema version.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'et_divi_ai_chat_db_version';

	/**
	 * Register $wpdb table aliases and install/upgrade schema if needed.
	 *
	 * Safe to call on every request — only runs dbDelta() when the stored
	 * version differs from DB_VERSION or the tables are missing.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function maybe_install(): void {
		global $wpdb;

		// Always register $wpdb aliases so controller queries never fail even
		// when no schema upgrade is needed.
		self::_register_table_aliases();

		$installed_version = get_option( self::DB_VERSION_OPTION, '' );

		if ( self::DB_VERSION === $installed_version ) {
			return;
		}

		self::_install();
	}

	/**
	 * Register the six $wpdb table-name aliases.
	 *
	 * Called unconditionally by maybe_install() so that all controller code
	 * can reference $wpdb->et_divi_ai_chat_threads etc. without worrying about
	 * whether the schema has been upgraded yet.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	private static function _register_table_aliases(): void {
		global $wpdb;

		$wpdb->et_divi_ai_chat_threads        = $wpdb->prefix . 'et_divi_ai_chat_threads';
		$wpdb->et_divi_ai_chat_messages       = $wpdb->prefix . 'et_divi_ai_chat_messages';
		$wpdb->et_divi_ai_chat_checkpoints    = $wpdb->prefix . 'et_divi_ai_chat_checkpoints';
		$wpdb->et_divi_ai_chat_pending_writes = $wpdb->prefix . 'et_divi_ai_chat_pending_writes';
		$wpdb->et_divi_ai_chat_restore_points = $wpdb->prefix . 'et_divi_ai_chat_restore_points';
		$wpdb->et_divi_ai_approval_tokens     = $wpdb->prefix . 'et_divi_ai_approval_tokens';
	}

	/**
	 * Run dbDelta() to create or upgrade all six tables, then persist the
	 * installed version.
	 *
	 * NOTE: dbDelta() quirks that MUST be preserved:
	 *   - Two spaces between the column list and PRIMARY KEY.
	 *   - Use KEY, not INDEX, for secondary indexes.
	 *   - No backticks around the table name in the CREATE TABLE statement.
	 *   - No IF NOT EXISTS — dbDelta() manages idempotency itself.
	 *   - $wpdb->get_charset_collate() must be appended to every CREATE TABLE.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	private static function _install(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// -----------------------------------------------------------------
		// Table 1: threads — one row per chat session.
		//
		// created_at/updated_at are stored as JS-millisecond BIGINT (not
		// MySQL DATETIME) so no TZ/format conversion is needed between the
		// client and DB — a MySQL DATETIME string like "2026-07-04 00:13:00"
		// has no timezone info, and `new Date(...)` on the client parses
		// that format inconsistently across browsers (some treat it as
		// local time, some as invalid), which would desync `updated_at`
		// sort order from what the server actually stored.
		// -----------------------------------------------------------------
		//
		// `context` (LONGTEXT, nullable) holds the JSON-encoded chat-context blob
		// (`schemaVersion`, `objective`, `notes[]`, `decisions[]`, `blockers[]`,
		// `todos[]`, `updatedAt`) — the per-chat continuation state the AI agent
		// maintains across turns. It is a full-blob replace written by a dedicated
		// `save_context` endpoint that intentionally does NOT bump `updated_at`, so
		// TODO/context ticks never reorder the history list. LONGTEXT (not TEXT)
		// because a long-running chat's notes/todos can exceed TEXT's ~65KB cap;
		// it is only surfaced by `show_thread` (never in the lean `list_threads`).
		$threads = "CREATE TABLE {$wpdb->et_divi_ai_chat_threads} (
			id bigint(20) unsigned NOT NULL auto_increment,
			chat_id varchar(128) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			runtime_thread_id varchar(256) NOT NULL,
			title varchar(255) NOT NULL DEFAULT '',
			interaction_mode varchar(16) NOT NULL DEFAULT 'ask',
			context longtext NULL,
			created_at bigint(20) unsigned NOT NULL,
			updated_at bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY chat_id (chat_id),
			UNIQUE KEY runtime_thread_id (runtime_thread_id(191)),
			KEY user_id (user_id),
			KEY user_updated (user_id, updated_at)
		) $charset_collate;";

		// -----------------------------------------------------------------
		// Table 2: messages — user and final assistant messages.
		//
		// `timestamp` (client-supplied JS-ms epoch) is the message's sole
		// time field — it's both the ordering key and the value the client
		// re-hydrates into `Message.timestamp`. No separate `created_at`
		// column: a server-generated DATETIME default would be redundant
		// with `timestamp` and would break the "everything is JS-ms BIGINT"
		// convention used across the other three tables.
		// -----------------------------------------------------------------
		$messages = "CREATE TABLE {$wpdb->et_divi_ai_chat_messages} (
			id bigint(20) unsigned NOT NULL auto_increment,
			chat_id varchar(128) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			message_uuid varchar(128) NOT NULL,
			role varchar(16) NOT NULL,
			content longtext NOT NULL,
			steps longtext NOT NULL,
			timestamp bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY chat_message_uuid (chat_id(30), message_uuid(60)),
			KEY chat_id_timestamp (chat_id, timestamp),
			KEY user_id (user_id)
		) $charset_collate;";

		// -----------------------------------------------------------------
		// Table 3: checkpoints — LangGraph checkpoint blobs.
		//
		// Index prefix widths are intentionally conservative rather than
		// indexing the full VARCHAR columns: InnoDB's default max index
		// entry size is 767 bytes (COMPACT/REDUNDANT row format, or any
		// row format on a host without `innodb_large_prefix` — still common
		// on older MySQL 5.6/MariaDB installs), and at utf8mb4 (4 bytes/char)
		// the full-width columns (256 + 64 + 64 = 384 chars → 1536 bytes)
		// would exceed that limit and fail on those installs even though
		// they fit comfortably under the newer 3072-byte DYNAMIC-row limit.
		// thread_id(60) + checkpoint_ns(10) + checkpoint_id(36) = 106 chars
		// × 4 = 424 bytes, safely under 767 on every supported MySQL/MariaDB
		// version. In practice this project's `checkpoint_ns` is always ''
		// (no LangGraph subgraph composition is used) and `checkpoint_id`
		// is a 36-char UUID, so no real value is ever truncated; `thread_id`
		// values (`chat-modal-<ts>` or `<base>-step-<N>`) are well under 60
		// chars too — see `runtime_thread_id(191)` above for the same
		// 767-byte-limit rationale applied to the threads table.
		// -----------------------------------------------------------------
		$checkpoints = "CREATE TABLE {$wpdb->et_divi_ai_chat_checkpoints} (
			id bigint(20) unsigned NOT NULL auto_increment,
			chat_id varchar(128) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			thread_id varchar(256) NOT NULL,
			checkpoint_ns varchar(64) NOT NULL DEFAULT '',
			checkpoint_id varchar(64) NOT NULL,
			parent_checkpoint_id varchar(64) NOT NULL DEFAULT '',
			value_type varchar(16) NOT NULL DEFAULT 'json',
			value longtext NOT NULL,
			metadata longtext NOT NULL,
			created_at bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY chat_thread_ns_cp (chat_id(30), thread_id(60), checkpoint_ns(10), checkpoint_id(36)),
			KEY chat_id (chat_id),
			KEY user_id (user_id),
			KEY thread_ns_created (thread_id(60), checkpoint_ns(10), created_at)
		) $charset_collate;";

		// -----------------------------------------------------------------
		// Table 4: pending_writes — LangGraph pending-write blobs.
		//
		// Same conservative prefix-width rationale as Table 3 (see above):
		// thread_id(60) + checkpoint_ns(10) + checkpoint_id(36) + task_id(36)
		// = 142 chars × 4 = 568 bytes, safely under the 767-byte limit that
		// applies on MySQL/MariaDB installs without `innodb_large_prefix`.
		//
		// NOTE: the natural key per the approved schema is
		// (thread_id, checkpoint_ns, checkpoint_id, task_id, write_index) —
		// `write_index` distinguishes multiple writes to the same channel
		// within one task. It is included below; omitting it (as an earlier
		// draft of this table did) would silently collapse distinct writes
		// that share a channel into a single upserted row.
		// -----------------------------------------------------------------
		$pending_writes = "CREATE TABLE {$wpdb->et_divi_ai_chat_pending_writes} (
			id bigint(20) unsigned NOT NULL auto_increment,
			chat_id varchar(128) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			thread_id varchar(256) NOT NULL,
			checkpoint_ns varchar(64) NOT NULL DEFAULT '',
			checkpoint_id varchar(64) NOT NULL,
			task_id varchar(64) NOT NULL,
			channel varchar(128) NOT NULL,
			write_index int(11) NOT NULL DEFAULT 0,
			value_type varchar(16) NOT NULL DEFAULT 'json',
			value longtext NOT NULL,
			created_at bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY chat_thread_ns_cp_task_idx (chat_id(30), thread_id(60), checkpoint_ns(10), checkpoint_id(36), task_id(36), write_index),
			KEY chat_id (chat_id),
			KEY user_id (user_id),
			KEY checkpoint_id (checkpoint_id)
		) $charset_collate;";

		// -----------------------------------------------------------------
		// Table 5: restore_points — "Restore to here" builder snapshots.
		//
		// One row per mutating Build-mode user message (G2). `snapshot_json`
		// is a serialized `RestorePointSnapshot` (postContent + optional
		// themeBuilderTemplates/offCanvas/globals, each an already-serialized
		// Gutenberg-format string) — LONGTEXT because a full serialized page
		// can exceed TEXT's ~65KB cap. `mutated_domains` is a small JSON
		// array (e.g. `["layout"]`), so VARCHAR is sufficient — no LONGTEXT
		// needed there. Unique on (chat_id, message_uuid): one restore point
		// per anchor message, upserted in place on retry.
		// -----------------------------------------------------------------
		$restore_points = "CREATE TABLE {$wpdb->et_divi_ai_chat_restore_points} (
			id bigint(20) unsigned NOT NULL auto_increment,
			chat_id varchar(128) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			message_uuid varchar(128) NOT NULL,
			captured_at bigint(20) unsigned NOT NULL,
			mutated_domains varchar(255) NOT NULL DEFAULT '[]',
			snapshot_json longtext NOT NULL,
			created_at bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY chat_message_uuid (chat_id(30), message_uuid(60)),
			KEY user_id (user_id)
		) $charset_collate;";

		// -----------------------------------------------------------------
		// Table 6: approval_tokens — single-use HITL REST tokens.
		//
		// `token_hash` is SHA-256 hex of the raw token (never stored raw).
		// `expires_at` / `created_at` / `consumed_at` are JS-ms BIGINT like
		// the other AI chat tables. TTL is 5 minutes from created_at.
		// Consume is one UPDATE … consumed_at IS NULL; rows_affected === 1.
		// -----------------------------------------------------------------
		$approval_tokens = "CREATE TABLE {$wpdb->et_divi_ai_approval_tokens} (
			id bigint(20) unsigned NOT NULL auto_increment,
			token_hash char(64) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			tool_name varchar(64) NOT NULL,
			binding_type varchar(16) NOT NULL,
			binding_value varchar(191) NOT NULL,
			overwrite tinyint(1) NOT NULL DEFAULT 0,
			expires_at bigint(20) unsigned NOT NULL,
			consumed_at bigint(20) unsigned NULL,
			created_at bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash)
		) $charset_collate;";

		dbDelta( $threads );
		dbDelta( $messages );
		dbDelta( $checkpoints );
		dbDelta( $pending_writes );
		dbDelta( $restore_points );
		dbDelta( $approval_tokens );

		$tables = [
			$wpdb->et_divi_ai_chat_threads,
			$wpdb->et_divi_ai_chat_messages,
			$wpdb->et_divi_ai_chat_checkpoints,
			$wpdb->et_divi_ai_chat_pending_writes,
			$wpdb->et_divi_ai_chat_restore_points,
			$wpdb->et_divi_ai_approval_tokens,
		];

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
				// Table creation failed, don't update version so maybe_install() retries next time.
				return;
			}
		}

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Bootstraps the schema on `after_setup_theme`.
	 *
	 * Hook priority 20 ensures settings and $wpdb are fully available.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( did_action( 'after_setup_theme' ) ) {
			self::maybe_install();
		} else {
			add_action( 'after_setup_theme', [ self::class, 'maybe_install' ], 20 );
		}
	}
}
