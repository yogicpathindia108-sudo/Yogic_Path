<?php
/**
 * Divi AI authentication credential lifecycle service.
 *
 * @package Builder\VisualBuilder\REST
 * @since ??
 */

namespace ET\Builder\VisualBuilder\REST\DiviAIAuth;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use WP_Error;

/**
 * Stores and rotates Divi AI credentials for the current WordPress user.
 *
 * @since ??
 */
class DiviAIAuthService {
	private const CREDENTIALS_META_KEY = 'et_divi_ai_auth_credentials';
	private const RENEWAL_LOCK_OPTION_PREFIX = 'et_divi_ai_auth_renewal_lock_';
	private const INSTALLATION_OPTION = 'et_divi_ai_installation_id';
	private const BROKER_BASE_URL = 'https://www.elegantthemes.com/api_v2/divi-ai/auth';
	private const POPUP_URL = 'https://www.elegantthemes.com/members-area/divi-ai/auth/';
	private const LOCK_TIMEOUT = 30;
	private const LOCK_WAIT_MICROSECONDS = 1000000;
	private const LOCK_WAIT_ATTEMPTS = 20;

	/**
	 * Return the ET.com broker base URL.
	 *
	 * Production is the default. A site may define ET_DIVI_AI_BROKER_URL to
	 * target a staging environment; QA cannot otherwise reach a non-production
	 * broker without editing theme source.
	 *
	 * @return string
	 */
	private static function broker_base_url(): string {
		if ( defined( 'ET_DIVI_AI_BROKER_URL' ) && is_string( ET_DIVI_AI_BROKER_URL ) && '' !== ET_DIVI_AI_BROKER_URL ) {
			return untrailingslashit( ET_DIVI_AI_BROKER_URL );
		}

		return self::BROKER_BASE_URL;
	}

	/**
	 * Return the ET.com popup URL.
	 *
	 * Overridable with ET_DIVI_AI_POPUP_URL for the same reason as the broker
	 * base URL. Query parameters are added by the browser, so this stays bare.
	 *
	 * @return string
	 */
	private static function popup_url(): string {
		if ( defined( 'ET_DIVI_AI_POPUP_URL' ) && is_string( ET_DIVI_AI_POPUP_URL ) && '' !== ET_DIVI_AI_POPUP_URL ) {
			return ET_DIVI_AI_POPUP_URL;
		}

		return self::POPUP_URL;
	}

	/**
	 * Return the expected ET.com origin, derived from the popup URL.
	 *
	 * Deriving it keeps the origin the browser validates in step with whichever
	 * environment the popup actually opens.
	 *
	 * @return string
	 */
	private static function popup_origin(): string {
		$parts = wp_parse_url( self::popup_url() );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		$origin = $parts['scheme'] . '://' . $parts['host'];

		return isset( $parts['port'] ) ? $origin . ':' . $parts['port'] : $origin;
	}

	/**
	 * Return the ET.com AI credit Checkout launch endpoint.
	 *
	 * Derived from the popup origin so a staging override reaches Checkout too.
	 * The trailing slash is ET.com's canonical form; without it the request is
	 * redirected before the body arrives.
	 *
	 * @return string
	 */
	private static function checkout_launch_url(): string {
		$origin = self::popup_origin();

		return '' === $origin ? '' : $origin . '/members-area/ai-balance/popup-launch/';
	}

	/**
	 * Return stable popup configuration for the current site and user.
	 *
	 * @return array
	 */
	public static function get_popup_config(): array {
		$credentials = self::get_credentials( get_current_user_id() );

		return [
			'url'                  => self::popup_url(),
			'origin'               => self::popup_origin(),
			'checkoutLaunchUrl'    => self::checkout_launch_url(),
			'connected'            => null !== $credentials,
			'accessTokenExpiresAt' => $credentials['access_token_expires_at'] ?? null,
			'siteId'               => self::get_installation_id(),
			'siteName'             => wp_strip_all_tags( get_bloginfo( 'name' ) ),
			'wordpressUserId'      => (string) get_current_user_id(),
		];
	}

	/**
	 * Read the current user's public credential state.
	 *
	 * @param int $user_id WordPress user ID.
	 *
	 * @return array
	 */
	public static function read( int $user_id ): array {
		$credentials = self::get_credentials( $user_id );

		if ( null === $credentials ) {
			return [ 'connected' => false ];
		}

		return self::public_credentials( $credentials );
	}

	/**
	 * Store credentials returned by the ET.com popup.
	 *
	 * @param int         $user_id              WordPress user ID.
	 * @param array       $payload               Validated credential payload.
	 * @param bool        $clear_lock            Whether to clear a pre-existing renewal lock.
	 * @param string|null $previous_browser_part Previous split-session browser portion.
	 *
	 * @return array|WP_Error
	 */
	public static function store( int $user_id, array $payload, bool $clear_lock = true, ?string $previous_browser_part = null ) {
		$refresh_token = $payload['refresh_token'];
		$save_session  = $payload['save_session'];
		$username      = self::access_token_username( $payload['access_token'] );
		if ( null === $username ) {
			return new WP_Error(
				'divi_ai_auth_invalid_credentials',
				esc_html__( 'The Divi AI credential response does not identify an account.', 'et_builder_5' ),
				[ 'status' => 400 ]
			);
		}
		$credentials   = [
			'token_family_id'         => $payload['token_family_id'],
			'username'                => $username,
			'token_type'              => 'Bearer',
			'access_token'            => $payload['access_token'],
			'revocation_token'        => $payload['revocation_token'],
			'access_token_expires_at' => $payload['access_token_expires_at'],
			'refresh_token_expires_at' => $payload['refresh_token_expires_at'],
			'token_family_expires_at' => $payload['token_family_expires_at'],
			'save_session'            => $save_session,
		];

		if ( $save_session ) {
			$credentials['refresh_token'] = $refresh_token;
			$browser_part                 = null;
		} else {
			try {
				$split = self::split_refresh_token( $refresh_token );
			} catch ( \Exception $exception ) {
				return new WP_Error(
					'divi_ai_auth_randomness_unavailable',
					esc_html__( 'Secure randomness is unavailable. Divi AI credentials were not stored.', 'et_builder_5' ),
					[ 'status' => 503 ]
				);
			}
			$credentials['refresh_token_part'] = $split['wordpress'];
			$browser_part                      = $split['browser'];
			$credentials['browser_part_digest'] = hash( 'sha256', $browser_part );

			if ( null !== $previous_browser_part ) {
				$credentials['previous_browser_part_digest'] = hash( 'sha256', $previous_browser_part );
				$credentials['browser_part_recovery']        = self::protect_browser_part( $browser_part, $previous_browser_part );
			}
		}

		$updated = update_user_meta( $user_id, self::credentials_meta_key(), $credentials );
		if (
			false === $updated
			&& $credentials !== get_user_meta( $user_id, self::credentials_meta_key(), true )
		) {
			return new WP_Error(
				'divi_ai_auth_persistence_failed',
				esc_html__( 'Divi AI credentials could not be stored.', 'et_builder_5' ),
				[ 'status' => 500 ]
			);
		}

		if ( $clear_lock ) {
			delete_site_option( self::renewal_lock_option( $user_id ) );
		}

		$response = self::public_credentials( $credentials );
		if ( null !== $browser_part ) {
			$response['browser_refresh_token_part'] = $browser_part;
		}

		return $response;
	}

	/**
	 * Renew the current user's credential family through ET.com.
	 *
	 * @param int         $user_id     WordPress user ID.
	 * @param string|null $browser_part Browser-held refresh-token portion.
	 *
	 * @return array|WP_Error
	 */
	public static function renew( int $user_id, ?string $browser_part ) {
		$credentials = self::get_credentials( $user_id );
		if ( null === $credentials ) {
			return self::terminal_error( $user_id, 'Divi AI is not connected for this WordPress user.' );
		}

		$initial_access_token = $credentials['access_token'];
		$lock_token           = self::acquire_renewal_lock( $user_id );
		if ( is_wp_error( $lock_token ) ) {
			return $lock_token;
		}

		$credentials = self::get_credentials( $user_id );
		if ( null === $credentials ) {
			self::release_renewal_lock( $user_id, $lock_token );
			return self::terminal_error( $user_id, 'Divi AI credentials are no longer available.' );
		}

		if ( $initial_access_token !== $credentials['access_token'] ) {
			self::release_renewal_lock( $user_id, $lock_token );
			return self::public_credentials_for_waiter( $credentials, $browser_part );
		}

		if ( ! $credentials['save_session'] ) {
			$current_browser_part = self::current_browser_part( $credentials, $browser_part );
			if ( null === $current_browser_part ) {
				self::release_renewal_lock( $user_id, $lock_token );
				return new WP_Error(
					'divi_ai_auth_browser_part_missing',
					esc_html__( 'This browser does not have the session portion required to renew Divi AI.', 'et_builder_5' ),
					[ 'status' => 409 ]
				);
			}

			if ( $current_browser_part !== $browser_part ) {
				self::release_renewal_lock( $user_id, $lock_token );
				return self::public_credentials_for_waiter( $credentials, $browser_part );
			}
		}

		$refresh_token = self::reconstruct_refresh_token( $credentials, $browser_part );
		if ( null === $refresh_token ) {
			self::release_renewal_lock( $user_id, $lock_token );
			return new WP_Error(
				'divi_ai_auth_browser_part_invalid',
				esc_html__( 'The browser portion of the Divi AI session is invalid.', 'et_builder_5' ),
				[ 'status' => 409 ]
			);
		}

		$refresh_digest = hash( 'sha256', $refresh_token );
		$stored_refresh_digest = $credentials['renewal_refresh_digest'] ?? '';
		if ( ! hash_equals( (string) $stored_refresh_digest, $refresh_digest ) ) {
			$credentials['renewal_request_id']     = strtolower( wp_generate_uuid4() );
			$credentials['renewal_refresh_digest'] = $refresh_digest;
			if ( false === update_user_meta( $user_id, self::credentials_meta_key(), $credentials ) ) {
				self::release_renewal_lock( $user_id, $lock_token );

				return new WP_Error(
					'divi_ai_auth_persistence_failed',
					esc_html__( 'Divi AI could not prepare the session renewal.', 'et_builder_5' ),
					[ 'status' => 500 ]
				);
			}
		}

		$response = self::broker_request(
			'/refresh',
			[
				'refresh_token'      => $refresh_token,
				'renewal_request_id' => $credentials['renewal_request_id'],
				'username'           => $credentials['username'],
			]
		);

		if ( is_wp_error( $response ) ) {
			self::release_renewal_lock( $user_id, $lock_token );
			return $response;
		}

		// ET.com reports the terminal outcome as `error`. `code` is accepted as a
		// fallback so a broker that uses the other key still ends the family
		// instead of looking retryable forever.
		$broker_error = $response['body']['error'] ?? ( $response['body']['code'] ?? '' );

		if ( 409 === $response['status'] && 'refresh_token_terminal' === $broker_error ) {
			$current = self::get_credentials( $user_id );
			if (
				! self::owns_renewal_lock( $user_id, $lock_token )
				|| null === $current
				|| $initial_access_token !== $current['access_token']
			) {
				self::release_renewal_lock( $user_id, $lock_token );

				return null === $current
					? new WP_Error(
						'divi_ai_auth_lifecycle_changed',
						esc_html__( 'The Divi AI session changed while renewal was in progress.', 'et_builder_5' ),
						[ 'status' => 409 ]
					)
					: self::public_credentials_for_waiter( $current, $browser_part );
			}

			if ( ! self::delete_credentials( $user_id ) ) {
				return self::persistence_error();
			}
			return new WP_Error(
				'divi_ai_auth_terminal',
				esc_html__( 'The Divi AI session has expired or was revoked.', 'et_builder_5' ),
				[ 'status' => 409 ]
			);
		}

		if (
			200 !== $response['status']
			|| ! self::is_valid_credential_response( $response['body'] )
			|| $credentials['token_family_id'] !== ( $response['body']['token_family_id'] ?? '' )
		) {
			self::release_renewal_lock( $user_id, $lock_token );
			return new WP_Error(
				'divi_ai_auth_renewal_failed',
				esc_html__( 'Divi AI could not renew the session. You can continue while the current access token remains valid.', 'et_builder_5' ),
				[ 'status' => 502 ]
			);
		}

		$rotated = $response['body'];
		$current = self::get_credentials( $user_id );
		if (
			! self::owns_renewal_lock( $user_id, $lock_token )
			|| null === $current
			|| $initial_access_token !== $current['access_token']
		) {
			self::release_renewal_lock( $user_id, $lock_token );

			return null === $current
				? new WP_Error(
					'divi_ai_auth_lifecycle_changed',
					esc_html__( 'The Divi AI session changed while renewal was in progress.', 'et_builder_5' ),
					[ 'status' => 409 ]
				)
				: self::public_credentials_for_waiter( $current, $browser_part );
		}

		$stored  = self::store(
			$user_id,
			[
				'token_family_id'          => $rotated['token_family_id'],
				'access_token'             => $rotated['access_token'],
				'access_token_expires_at'  => $rotated['access_token_expires_at'],
				'refresh_token'            => $rotated['refresh_token'],
				'revocation_token'         => $credentials['revocation_token'],
				'refresh_token_expires_at' => $rotated['refresh_token_expires_at'],
				'token_family_expires_at'  => $rotated['token_family_expires_at'],
				'save_session'             => (bool) $credentials['save_session'],
			],
			false,
			$browser_part
		);

		self::release_renewal_lock( $user_id, $lock_token );
		return $stored;
	}

	/**
	 * Revoke remotely when possible and always remove local credentials.
	 *
	 * @param int $user_id WordPress user ID.
	 *
	 * @return array|WP_Error
	 */
	public static function disconnect( int $user_id ) {
		$credentials         = self::get_credentials( $user_id );
		$initial_access_token = null === $credentials ? null : $credentials['access_token'];
		$lock_token          = null;
		$confirmed           = false;

		if ( null !== $credentials ) {
			$lock_token = self::acquire_renewal_lock( $user_id );
			if ( is_wp_error( $lock_token ) ) {
				$credentials = null;
			} else {
				$credentials          = self::get_credentials( $user_id );
				$initial_access_token = null === $credentials ? null : $credentials['access_token'];
			}

			if ( null !== $credentials ) {
				$response  = self::broker_request(
					'/revoke',
					[
						'token_family_id'  => $credentials['token_family_id'],
						'revocation_token' => $credentials['revocation_token'],
					]
				);
				$confirmed = ! is_wp_error( $response ) && 204 === $response['status'];
			}
		}

		$current = self::get_credentials( $user_id );
		if (
			is_string( $lock_token )
			&& self::owns_renewal_lock( $user_id, $lock_token )
			&& null !== $current
			&& $initial_access_token === $current['access_token']
		) {
			if ( ! self::delete_credentials( $user_id ) ) {
				return self::persistence_error();
			}
		} elseif ( is_string( $lock_token ) ) {
			self::release_renewal_lock( $user_id, $lock_token );
		} elseif ( null === $current || $initial_access_token === ( $current['access_token'] ?? null ) ) {
			if ( ! self::delete_credentials( $user_id ) ) {
				return self::persistence_error();
			}
		}

		return [
			'disconnected'                => true,
			'remote_revocation_confirmed' => $confirmed,
		];
	}

	/**
	 * Return a stable random installation identifier.
	 *
	 * @return string
	 */
	private static function get_installation_id(): string {
		$installation_id = get_option( self::INSTALLATION_OPTION, '' );
		if ( is_string( $installation_id ) && preg_match( '/^[a-f0-9]{64}$/', $installation_id ) ) {
			return $installation_id;
		}

		try {
			$candidate = bin2hex( random_bytes( 32 ) );
		} catch ( \Exception $exception ) {
			$candidate = hash( 'sha256', home_url( '/' ) . '|' . wp_salt( 'auth' ) );
		}
		if ( add_option( self::INSTALLATION_OPTION, $candidate, '', false ) ) {
			return $candidate;
		}

		$installation_id = get_option( self::INSTALLATION_OPTION, '' );
		if ( is_string( $installation_id ) && preg_match( '/^[a-f0-9]{64}$/', $installation_id ) ) {
			return $installation_id;
		}

		update_option( self::INSTALLATION_OPTION, $candidate, false );

		return $candidate;
	}

	/**
	 * Load a validated credential record.
	 *
	 * @param int $user_id WordPress user ID.
	 *
	 * @return array|null
	 */
	private static function get_credentials( int $user_id ): ?array {
		$credentials = get_user_meta( $user_id, self::credentials_meta_key(), true );

		return is_array( $credentials ) && self::is_valid_stored_credentials( $credentials ) ? $credentials : null;
	}

	/**
	 * Validate persisted credential state before using it.
	 *
	 * @param array $credentials Persisted record.
	 *
	 * @return bool
	 */
	private static function is_valid_stored_credentials( array $credentials ): bool {
		$required_strings = [ 'token_family_id', 'username', 'access_token', 'revocation_token' ];
		$required_times   = [ 'access_token_expires_at', 'refresh_token_expires_at', 'token_family_expires_at' ];

		foreach ( $required_strings as $key ) {
			if ( ! isset( $credentials[ $key ] ) || ! is_string( $credentials[ $key ] ) || '' === $credentials[ $key ] ) {
				return false;
			}
		}

		foreach ( $required_times as $key ) {
			if ( ! isset( $credentials[ $key ] ) || ! is_int( $credentials[ $key ] ) || 0 >= $credentials[ $key ] ) {
				return false;
			}
		}

		if ( ! isset( $credentials['save_session'] ) || ! is_bool( $credentials['save_session'] ) ) {
			return false;
		}

		$refresh_key = $credentials['save_session'] ? 'refresh_token' : 'refresh_token_part';

		return isset( $credentials[ $refresh_key ] ) && is_string( $credentials[ $refresh_key ] ) && '' !== $credentials[ $refresh_key ];
	}

	/**
	 * Build the public read response without refresh-token material.
	 *
	 * @param array $credentials Persisted record.
	 *
	 * @return array
	 */
	private static function public_credentials( array $credentials ): array {
		return [
			'connected'                 => true,
			'token_family_id'           => $credentials['token_family_id'],
			'token_type'                => 'Bearer',
			'access_token'              => $credentials['access_token'],
			'access_token_expires_at'   => $credentials['access_token_expires_at'],
			'refresh_token_expires_at'  => $credentials['refresh_token_expires_at'],
			'token_family_expires_at'   => $credentials['token_family_expires_at'],
			'save_session'              => $credentials['save_session'],
		];
	}

	/**
	 * Build a concurrent waiter's response, including its replacement browser part.
	 *
	 * @param array       $credentials Persisted record after another request rotated it.
	 * @param string|null $browser_part Browser portion used for the completed rotation.
	 *
	 * @return array
	 */
	private static function public_credentials_for_waiter( array $credentials, ?string $browser_part ): array {
		$response = self::public_credentials( $credentials );
		if ( $credentials['save_session'] || null === $browser_part ) {
			return $response;
		}

		$expected_digest = $credentials['previous_browser_part_digest'] ?? '';
		$protected_part  = $credentials['browser_part_recovery'] ?? '';
		if (
			! is_string( $expected_digest )
			|| ! is_string( $protected_part )
			|| ! hash_equals( $expected_digest, hash( 'sha256', $browser_part ) )
		) {
			return $response;
		}

		$recovered = self::recover_browser_part( $protected_part, $browser_part );
		if ( null !== $recovered ) {
			$response['browser_refresh_token_part'] = $recovered;
		}

		return $response;
	}

	/**
	 * Resolve the current split-session browser portion, including one rotation of recovery.
	 *
	 * @param array       $credentials Persisted credential record.
	 * @param string|null $browser_part Browser-held portion.
	 *
	 * @return string|null
	 */
	private static function current_browser_part( array $credentials, ?string $browser_part ): ?string {
		if ( null === $browser_part || '' === $browser_part ) {
			return null;
		}

		$current_digest = $credentials['browser_part_digest'] ?? '';
		if ( is_string( $current_digest ) && hash_equals( $current_digest, hash( 'sha256', $browser_part ) ) ) {
			return $browser_part;
		}

		$previous_digest = $credentials['previous_browser_part_digest'] ?? '';
		$protected_part  = $credentials['browser_part_recovery'] ?? '';
		if (
			is_string( $previous_digest )
			&& is_string( $protected_part )
			&& hash_equals( $previous_digest, hash( 'sha256', $browser_part ) )
		) {
			return self::recover_browser_part( $protected_part, $browser_part );
		}

		return null;
	}

	/**
	 * Split a refresh token using a random one-time pad.
	 *
	 * @param string $refresh_token Refresh token.
	 *
	 * @return array
	 */
	private static function split_refresh_token( string $refresh_token ): array {
		$pad      = random_bytes( strlen( $refresh_token ) );
		$wp_part  = $refresh_token ^ $pad;

		return [
			'wordpress' => self::base64url_encode( $wp_part ),
			'browser'   => self::base64url_encode( $pad ),
		];
	}

	/**
	 * Protect a replacement browser portion for concurrent waiters.
	 *
	 * WordPress stores only ciphertext. Reconstructing the browser portion also
	 * requires the previous browser-held portion supplied by a waiting caller.
	 *
	 * @param string $browser_part          Replacement browser portion.
	 * @param string $previous_browser_part Previous browser portion.
	 *
	 * @return string
	 */
	private static function protect_browser_part( string $browser_part, string $previous_browser_part ): string {
		$stream = self::browser_part_keystream( $previous_browser_part, strlen( $browser_part ) );

		return self::base64url_encode( $browser_part ^ $stream );
	}

	/**
	 * Recover a replacement browser portion for a concurrent waiter.
	 *
	 * @param string $protected_part        Protected replacement portion.
	 * @param string $previous_browser_part Previous browser portion.
	 *
	 * @return string|null
	 */
	private static function recover_browser_part( string $protected_part, string $previous_browser_part ): ?string {
		$ciphertext = self::base64url_decode( $protected_part );
		if ( null === $ciphertext ) {
			return null;
		}

		$stream    = self::browser_part_keystream( $previous_browser_part, strlen( $ciphertext ) );
		$recovered = $ciphertext ^ $stream;

		return preg_match( '/^[A-Za-z0-9_-]+$/', $recovered ) ? $recovered : null;
	}

	/**
	 * Expand the previous browser portion into a deterministic byte stream.
	 *
	 * @param string $key    Previous browser portion.
	 * @param int    $length Required stream length.
	 *
	 * @return string
	 */
	private static function browser_part_keystream( string $key, int $length ): string {
		$stream  = '';
		$counter = 0;
		while ( strlen( $stream ) < $length ) {
			$stream .= hash_hmac( 'sha256', 'divi-ai-browser-part:' . $counter, $key, true );
			$counter++;
		}

		return substr( $stream, 0, $length );
	}

	/**
	 * Reconstruct a saved or split refresh token.
	 *
	 * @param array       $credentials Persisted record.
	 * @param string|null $browser_part Browser-held portion.
	 *
	 * @return string|null
	 */
	private static function reconstruct_refresh_token( array $credentials, ?string $browser_part ): ?string {
		if ( $credentials['save_session'] ) {
			return $credentials['refresh_token'];
		}

		if ( null === $browser_part || '' === $browser_part ) {
			return null;
		}

		$wp_part = self::base64url_decode( $credentials['refresh_token_part'] );
		$pad     = self::base64url_decode( $browser_part );
		if ( null === $wp_part || null === $pad || strlen( $wp_part ) !== strlen( $pad ) ) {
			return null;
		}

		return $wp_part ^ $pad;
	}

	/**
	 * Make a JSON broker request without leaking credential material.
	 *
	 * @param string $path Endpoint path.
	 * @param array  $body Request body.
	 *
	 * @return array|WP_Error
	 */
	private static function broker_request( string $path, array $body ) {
		$response = wp_remote_post(
			self::broker_base_url() . $path,
			[
				'timeout' => 15,
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => wp_json_encode( $body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'divi_ai_auth_broker_unavailable',
				esc_html__( 'Divi AI could not contact Elegant Themes to update the session.', 'et_builder_5' ),
				[ 'status' => 502 ]
			);
		}

		$status   = (int) wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );
		$decoded  = '' === $raw_body ? [] : json_decode( $raw_body, true );

		return [
			'status' => $status,
			'body'   => is_array( $decoded ) ? $decoded : [],
		];
	}

	/**
	 * Validate a successful broker credential response.
	 *
	 * @param array $body Response body.
	 *
	 * @return bool
	 */
	private static function is_valid_credential_response( array $body ): bool {
		$strings = [ 'token_family_id', 'access_token', 'refresh_token' ];
		$times   = [ 'access_token_expires_at', 'refresh_token_expires_at', 'token_family_expires_at' ];

		foreach ( $strings as $key ) {
			if ( ! isset( $body[ $key ] ) || ! is_string( $body[ $key ] ) || '' === $body[ $key ] ) {
				return false;
			}
		}

		if ( 128 < strlen( $body['token_family_id'] ) || 8192 < strlen( $body['access_token'] ) || 1024 < strlen( $body['refresh_token'] ) ) {
			return false;
		}

		foreach ( $times as $key ) {
			if ( ! isset( $body[ $key ] ) || ! is_int( $body[ $key ] ) || 0 >= $body[ $key ] ) {
				return false;
			}
		}

		return 'Bearer' === ( $body['token_type'] ?? '' )
			&& $body['access_token_expires_at'] < $body['token_family_expires_at']
			&& $body['refresh_token_expires_at'] <= $body['token_family_expires_at'];
	}

	/**
	 * Read the acting username claim for untrusted renewal lookup metadata.
	 *
	 * WordPress does not use this value for authorization and therefore does not
	 * need to verify the JWT signature. ET.com binds it to the refresh token.
	 *
	 * @param string $access_token Signed access token.
	 *
	 * @return string|null
	 */
	private static function access_token_username( string $access_token ): ?string {
		$segments = explode( '.', $access_token );
		if ( 3 !== count( $segments ) ) {
			return null;
		}

		$payload  = self::base64url_decode( $segments[1] );
		$claims   = null === $payload ? null : json_decode( $payload, true );
		$username = is_array( $claims ) ? ( $claims['username'] ?? null ) : null;

		return is_string( $username ) && '' !== $username && 255 >= strlen( $username )
			? $username
			: null;
	}

	/**
	 * Acquire a per-user renewal lock, waiting for a concurrent rotation.
	 *
	 * The caller re-reads credentials immediately after acquisition, so polling
	 * only the lock is enough to detect a completed renewal without repeatedly
	 * reading user metadata while another request owns the lock.
	 *
	 * @param int $user_id WordPress user ID.
	 *
	 * @return string|WP_Error
	 */
	private static function acquire_renewal_lock( int $user_id ) {
		for ( $attempt = 0; $attempt < self::LOCK_WAIT_ATTEMPTS; $attempt++ ) {
			$token = wp_generate_uuid4();
			$value = [ 'token' => $token, 'created_at' => time() ];

			$option_name = self::renewal_lock_option( $user_id );
			if ( add_site_option( $option_name, $value ) ) {
				return $token;
			}

			$current_lock = get_site_option( $option_name, null );
			if ( is_array( $current_lock ) && time() - (int) ( $current_lock['created_at'] ?? 0 ) >= self::LOCK_TIMEOUT ) {
				delete_site_option( $option_name );
				continue;
			}

			usleep( self::LOCK_WAIT_MICROSECONDS );
		}

		return new WP_Error(
			'divi_ai_auth_renewal_busy',
			esc_html__( 'Another Divi AI renewal is still in progress.', 'et_builder_5' ),
			[ 'status' => 409 ]
		);
	}

	/**
	 * Release the lock only when owned by this request.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $token   Lock token.
	 *
	 * @return void
	 */
	private static function release_renewal_lock( int $user_id, string $token ): void {
		$option_name = self::renewal_lock_option( $user_id );
		$current     = get_site_option( $option_name, null );
		if ( is_array( $current ) && hash_equals( (string) ( $current['token'] ?? '' ), $token ) ) {
			delete_site_option( $option_name );
		}
	}

	/**
	 * Determine whether this request still owns the lifecycle lock.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $token   Lock token.
	 *
	 * @return bool
	 */
	private static function owns_renewal_lock( int $user_id, string $token ): bool {
		$current = get_site_option( self::renewal_lock_option( $user_id ), null );

		return is_array( $current ) && hash_equals( (string) ( $current['token'] ?? '' ), $token );
	}

	/**
	 * Delete all local credential and lock state.
	 *
	 * @param int $user_id WordPress user ID.
	 *
	 * @return bool
	 */
	private static function delete_credentials( int $user_id ): bool {
		$deleted = delete_user_meta( $user_id, self::credentials_meta_key() );
		delete_site_option( self::renewal_lock_option( $user_id ) );

		return false !== $deleted || '' === get_user_meta( $user_id, self::credentials_meta_key(), true );
	}

	/**
	 * Build the current site's atomic lifecycle lock option name.
	 *
	 * @param int $user_id WordPress user ID.
	 *
	 * @return string
	 */
	private static function renewal_lock_option( int $user_id ): string {
		return self::RENEWAL_LOCK_OPTION_PREFIX . get_current_blog_id() . '_' . $user_id;
	}

	/**
	 * Return the current site's per-user credential meta key.
	 *
	 * @return string
	 */
	private static function credentials_meta_key(): string {
		return self::CREDENTIALS_META_KEY . '_' . get_current_blog_id();
	}

	/**
	 * Clear local state and return a terminal lifecycle error.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $message Error message.
	 *
	 * @return WP_Error
	 */
	private static function terminal_error( int $user_id, string $message ): WP_Error {
		if ( ! self::delete_credentials( $user_id ) ) {
			return self::persistence_error();
		}

		return new WP_Error( 'divi_ai_auth_terminal', esc_html( $message ), [ 'status' => 409 ] );
	}

	/**
	 * Build a credential persistence failure.
	 *
	 * @return WP_Error
	 */
	private static function persistence_error(): WP_Error {
		return new WP_Error(
			'divi_ai_auth_persistence_failed',
			esc_html__( 'Divi AI credentials could not be deleted.', 'et_builder_5' ),
			[ 'status' => 500 ]
		);
	}

	/**
	 * Base64url encode binary data.
	 *
	 * @param string $value Binary value.
	 *
	 * @return string
	 */
	private static function base64url_encode( string $value ): string {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	/**
	 * Strictly decode base64url data.
	 *
	 * @param string $value Encoded value.
	 *
	 * @return string|null
	 */
	private static function base64url_decode( string $value ): ?string {
		if ( ! preg_match( '/^[A-Za-z0-9_-]+$/', $value ) ) {
			return null;
		}

		$padding = ( 4 - strlen( $value ) % 4 ) % 4;
		$decoded = base64_decode( strtr( $value . str_repeat( '=', $padding ), '-_', '+/' ), true );

		return false === $decoded ? null : $decoded;
	}
}
