<?php
/**
 * AI Agent: AiAgentThreadOwnership class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\AiAgent;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Shared lookup for `et_divi_ai_chat_threads` ownership.
 *
 * Callers decide policy (strict user match vs admin bypass). This helper
 * only returns the stored `user_id` or null when no row exists.
 *
 * @since ??
 */
class AiAgentThreadOwnership {

	/**
	 * Return the owning user ID for a chat thread, if a row exists.
	 *
	 * @since ??
	 *
	 * @param string $chat_id Chat identifier.
	 *
	 * @return int|null User ID, or null when no thread row exists.
	 */
	public static function get_thread_user_id( string $chat_id ): ?int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$user_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->et_divi_ai_chat_threads} WHERE chat_id = %s LIMIT 1",
				$chat_id
			)
		);

		if ( null === $user_id ) {
			return null;
		}

		return (int) $user_id;
	}
}
