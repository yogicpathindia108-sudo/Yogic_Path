<?php
/**
 * InstagramAccountService class.
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
 * Instagram account service.
 *
 * @since ??
 */
class InstagramAccountService {
	/**
	 * Option name used to persist Instagram accounts.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	const OPTION_NAME = 'et_builder_5_instagram_accounts';

	/**
	 * Prefix used for media transients.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	const MEDIA_TRANSIENT_PREFIX = 'et_ig_media_';

	/**
	 * Cache TTL for media responses.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	const MEDIA_TTL = HOUR_IN_SECONDS;

	/**
	 * Maximum amount of media items fetched from API.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	const MAX_LIMIT = 24;

	/**
	 * Get persisted Instagram accounts.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	private static function _get_accounts(): array {
		$accounts = get_option( self::OPTION_NAME, [] );

		return is_array( $accounts ) ? $accounts : [];
	}

	/**
	 * Persist Instagram accounts.
	 *
	 * @since ??
	 *
	 * @param array $accounts Instagram accounts.
	 *
	 * @return void
	 */
	private static function _set_accounts( array $accounts ): void {
		update_option( self::OPTION_NAME, $accounts, false );
	}

	/**
	 * Return account definition payload.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function definition(): array {
		$accounts = self::_get_accounts();

		foreach ( $accounts as $account_id => $account ) {
			if ( is_array( $account ) && isset( $account['access_token'] ) && is_string( $account['access_token'] ) ) {
				$accounts[ $account_id ]['access_token'] = str_repeat( '*', strlen( $account['access_token'] ) );
			}
		}

		return $accounts;
	}

	/**
	 * Fetch account data.
	 *
	 * @since ??
	 *
	 * @param string $account_id Optional account ID.
	 *
	 * @return array
	 */
	public static function fetch_account( string $account_id = '' ): array {
		$accounts = self::_get_accounts();

		if ( '' !== $account_id && isset( $accounts[ $account_id ] ) ) {
			$accounts[ $account_id ]['updated_at'] = gmdate( 'c' );
			self::_set_accounts( $accounts );
		}

		return $accounts;
	}

	/**
	 * Fetch media for the selected account.
	 *
	 * @since ??
	 *
	 * @param string $account_id     The account ID.
	 * @param int    $limit          Number of media items.
	 * @param bool   $force_refresh  Whether cache should be bypassed.
	 *
	 * @return array|WP_Error
	 */
	public static function fetch_media( string $account_id, int $limit = self::MAX_LIMIT, bool $force_refresh = false ) {
		$accounts = self::_get_accounts();

		if ( '' === $account_id || ! isset( $accounts[ $account_id ] ) ) {
			return new WP_Error( 'instagram_account_missing', esc_html__( 'Instagram account not found.', 'et_builder_5' ) );
		}

		$account = $accounts[ $account_id ];
		$token   = isset( $account['access_token'] ) ? sanitize_text_field( (string) $account['access_token'] ) : '';

		if ( '' === $token ) {
			return new WP_Error( 'instagram_access_token_missing', esc_html__( 'Instagram access token is missing.', 'et_builder_5' ) );
		}

		$normalized_limit = max( 1, min( self::MAX_LIMIT, $limit ) );
		$cache_key        = self::MEDIA_TRANSIENT_PREFIX . $account_id;

		if ( $force_refresh ) {
			delete_transient( $cache_key );
		}

		$cached_items = get_transient( $cache_key );

		if ( is_array( $cached_items ) ) {
			return array_slice( $cached_items, 0, $normalized_limit );
		}

		$raw_response = InstagramAPI::get_media( $token, self::MAX_LIMIT );

		if ( is_wp_error( $raw_response ) ) {
			return $raw_response;
		}

		$normalized_items = InstagramAPI::normalize_items( $raw_response );

		set_transient( $cache_key, $normalized_items, self::MEDIA_TTL );

		return array_slice( $normalized_items, 0, $normalized_limit );
	}

	/**
	 * Delete an account.
	 *
	 * @since ??
	 *
	 * @param string $account_id The account ID.
	 *
	 * @return array
	 */
	public static function delete_account( string $account_id ): array {
		$accounts = self::_get_accounts();

		if ( isset( $accounts[ $account_id ] ) ) {
			unset( $accounts[ $account_id ] );
			self::_set_accounts( $accounts );
			delete_transient( self::MEDIA_TRANSIENT_PREFIX . $account_id );
		}

		return $accounts;
	}

	/**
	 * Create an account.
	 *
	 * @since ??
	 *
	 * @param string $account_name The account label.
	 * @param string $access_token The account access token.
	 *
	 * @return array
	 */
	public static function create_account( string $account_name, string $access_token = '' ): array {
		$normalized_account_name = sanitize_text_field( $account_name );
		$normalized_access_token = sanitize_text_field( $access_token );

		if ( '' === $normalized_account_name || '' === $normalized_access_token ) {
			return self::_get_accounts();
		}

		$accounts = self::_get_accounts();

		do {
			$id = sanitize_key( uniqid( 'ig_', true ) );
		} while ( isset( $accounts[ $id ] ) );

		$now      = gmdate( 'c' );
		$status     = 'active';
		$ig_user_id = '';
		$username   = '';
		$expires_at = '';

		$profile_response = InstagramAPI::get_me( $normalized_access_token );

		if ( is_wp_error( $profile_response ) ) {
			$status = 'expired';
		} else {
			$ig_user_id = isset( $profile_response['user_id'] ) ? sanitize_text_field( (string) $profile_response['user_id'] ) : '';
			$username   = isset( $profile_response['username'] ) ? sanitize_text_field( (string) $profile_response['username'] ) : '';
		}

		$accounts[ $id ] = [
			'id'           => $id,
			'account_name' => $normalized_account_name,
			'ig_user_id'   => $ig_user_id,
			'username'     => $username,
			'access_token' => $normalized_access_token,
			'expires_at'   => $expires_at,
			'status'       => $status,
			'created_at'   => $now,
			'updated_at'   => $now,
		];

		self::_set_accounts( $accounts );
		self::fetch_media( $id, self::MAX_LIMIT, true );

		return $accounts;
	}
}
