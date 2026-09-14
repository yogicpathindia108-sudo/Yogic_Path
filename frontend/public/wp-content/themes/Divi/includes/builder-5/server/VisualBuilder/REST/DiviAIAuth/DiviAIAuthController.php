<?php
/**
 * Divi AI authentication REST controller.
 *
 * @package Builder\VisualBuilder\REST
 * @since ??
 */

namespace ET\Builder\VisualBuilder\REST\DiviAIAuth;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use WP_REST_Request;
use WP_Error;
use WP_REST_Response;

/**
 * Current-user credential lifecycle endpoints.
 *
 * @since ??
 */
class DiviAIAuthController extends RESTController {
	/**
	 * Read the current user's authentication state.
	 *
	 * @return WP_REST_Response
	 */
	public static function read(): WP_REST_Response {
		return self::response_success( DiviAIAuthService::read( get_current_user_id() ) );
	}

	/**
	 * Store popup-issued credentials.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function store( WP_REST_Request $request ) {
		$payload = [];
		foreach ( array_keys( self::store_args() ) as $key ) {
			$payload[ $key ] = $request->get_param( $key );
		}
		if (
			! is_array( $payload )
			|| $payload['access_token_expires_at'] >= $payload['token_family_expires_at']
			|| $payload['refresh_token_expires_at'] > $payload['token_family_expires_at']
		) {
			return self::response_error(
				'divi_ai_auth_invalid_credentials',
				esc_html__( 'The Divi AI credential response is invalid.', 'et_builder_5' )
			);
		}

		$response = DiviAIAuthService::store( get_current_user_id(), $payload );

		return is_wp_error( $response ) ? $response : self::response_success( $response );
	}

	/**
	 * Renew the current credential family.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function renew( WP_REST_Request $request ) {
		$browser_part = $request->get_param( 'browser_refresh_token_part' );
		$response     = DiviAIAuthService::renew( get_current_user_id(), is_string( $browser_part ) ? $browser_part : null );

		return is_wp_error( $response ) ? $response : self::response_success( $response );
	}

	/**
	 * Disconnect locally and attempt remote revocation.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function delete() {
		$response = DiviAIAuthService::disconnect( get_current_user_id() );

		return is_wp_error( $response ) ? $response : self::response_success( $response );
	}

	/**
	 * Require a WordPress user who can use the Visual Builder.
	 *
	 * @return bool|\WP_Error
	 */
	public static function permission() {
		return 0 < get_current_user_id() && UserRole::can_current_user_use_visual_builder()
			? true
			: self::response_error_permission();
	}

	/**
	 * Store endpoint arguments.
	 *
	 * @return array
	 */
	public static function store_args(): array {
		$bounded_string = static function ( $value, int $maximum ): bool {
			return is_string( $value ) && '' !== $value && strlen( $value ) <= $maximum;
		};
		$positive_integer = static function ( $value ): bool {
			return is_int( $value ) && 0 < $value;
		};
		$credential_token = static function ( $value, int $maximum ) use ( $bounded_string ): bool {
			return $bounded_string( $value, $maximum ) && self::sanitize_credential_token( $value ) === $value;
		};

		return [
			'token_family_id'          => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => static function ( $value ) use ( $bounded_string ): bool { return $bounded_string( $value, 128 ) && sanitize_text_field( $value ) === $value; } ],
			'token_type'               => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => static function ( $value ): bool { return 'Bearer' === $value; } ],
			'access_token'             => [ 'required' => true, 'sanitize_callback' => [ self::class, 'sanitize_credential_token' ], 'validate_callback' => static function ( $value ) use ( $credential_token ): bool { return $credential_token( $value, 8192 ); } ],
			'access_token_expires_at'  => [ 'required' => true, 'sanitize_callback' => 'absint', 'validate_callback' => $positive_integer ],
			'refresh_token'            => [ 'required' => true, 'sanitize_callback' => [ self::class, 'sanitize_credential_token' ], 'validate_callback' => static function ( $value ) use ( $credential_token ): bool { return $credential_token( $value, 1024 ); } ],
			'revocation_token'         => [ 'required' => true, 'sanitize_callback' => [ self::class, 'sanitize_credential_token' ], 'validate_callback' => static function ( $value ) use ( $credential_token ): bool { return $credential_token( $value, 1024 ); } ],
			'refresh_token_expires_at' => [ 'required' => true, 'sanitize_callback' => 'absint', 'validate_callback' => $positive_integer ],
			'token_family_expires_at'  => [ 'required' => true, 'sanitize_callback' => 'absint', 'validate_callback' => $positive_integer ],
			'save_session'             => [ 'required' => true, 'sanitize_callback' => 'rest_sanitize_boolean', 'validate_callback' => static function ( $value ): bool { return is_bool( $value ); } ],
		];
	}

	/**
	 * Optional browser-part argument used for split-session renewal.
	 *
	 * @return array
	 */
	public static function browser_part_args(): array {
		return [
			'browser_refresh_token_part' => [
				'required'          => false,
				'sanitize_callback' => [ self::class, 'sanitize_credential_token' ],
				'validate_callback' => static function ( $value ): bool {
					return is_string( $value )
						&& '' !== $value
						&& strlen( $value ) <= 2048
						&& self::sanitize_credential_token( $value ) === $value;
				},
			],
		];
	}

	/**
	 * Preserve opaque credential bytes and reject control characters.
	 *
	 * @param mixed $value Candidate credential token.
	 *
	 * @return string
	 */
	public static function sanitize_credential_token( $value ): string {
		return is_string( $value ) && preg_match( '/^[^\x00-\x20\x7F]+$/u', $value )
			? $value
			: '';
	}
}
