<?php
/**
 * ConstantContact API email provider class.
 *
 * @package ET\Core\API\Email
 */

 // @phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- No need to change the class property.

/**
 * Wrapper for ConstantContact's API.
 *
 * @since   3.0.75
 *
 * @package ET\Core\API\Email
 */
class ET_Core_API_Email_ConstantContact extends ET_Core_API_Email_Provider {

	/**
	 * Constant Contact API v3 access token URL (Authorization Code Flow).
	 *
	 * @var string
	 */
	public $ACCESS_TOKEN_URL = 'https://authz.constantcontact.com/oauth2/default/v1/token'; // phpcs:ignore -- No need to change the class property.

	/**
	 * Constant Contact API v3 authorization URL (Authorization Code Flow).
	 *
	 * @var string
	 */
	public $AUTHORIZATION_URL = 'https://authz.constantcontact.com/oauth2/default/v1/authorize'; // phpcs:ignore -- No need to change the class property.

	/**
	 * Constant Contact API v3 base URL.
	 *
	 * @var string
	 */
	public $BASE_URL = 'https://api.cc.email/v3';  // phpcs:ignore -- No need to change the class property.

	/**
	 * Constant Contact API v3 lists URL.
	 *
	 * @var string
	 */
	public $LISTS_URL = 'https://api.cc.email/v3/contact_lists'; // phpcs:ignore -- No need to change the class property.

	/**
	 * Constant Contact API v3 subscribe URL (sign_up_form endpoint for form submissions).
	 *
	 * @var string
	 */
	public $SUBSCRIBE_URL = 'https://api.cc.email/v3/contacts/sign_up_form'; // phpcs:ignore -- No need to change the class property.

	/**
	 * Constant Contact API v3 subscribers URL.
	 *
	 * @var string
	 */
	public $SUBSCRIBERS_URL = 'https://api.cc.email/v3/contacts'; // phpcs:ignore -- No need to change the class property.

	/**
	 * Subscriber data.
	 *
	 * @var array
	 */
	protected $_subscriber;

	/**
	 * Constant Contact API v3 custom fields scope.
	 *
	 * @var string
	 */
	public $custom_fields_scope = 'account';

	/**
	 * Constant Contact name.
	 *
	 * @var string
	 */
	public $name = 'ConstantContact';

	/**
	 * Constant Contact OAuth version.
	 *
	 * @var string
	 */
	public $oauth_version = '2.0';

	/**
	 * Constant Contact slug.
	 *
	 * @var string
	 */
	public $slug = 'constant_contact';

	/**
	 * Constant Contact uses OAuth.
	 *
	 * @var bool
	 */
	public $uses_oauth = true;

	/**
	 * ET_Core_API_Email_ConstantContact constructor.
	 *
	 * @param string $owner Owner type (builder or admin).
	 * @param string $account_name Account name.
	 * @param string $api_key API key.
	 */
	public function __construct( $owner = '', $account_name = '', $api_key = '' ) {
		parent::__construct( $owner, $account_name, $api_key );

		if ( 'builder' === $owner ) {
			$this->REDIRECT_URL = add_query_arg( 'et-core-api-email-auth', 1, home_url( '', 'https' ) ); // @phpcs:ignore ET.Sniffs.ValidVariableName.UsedPropertyNotSnakeCase -- No need to change prop name.
		} else {
			$this->REDIRECT_URL = admin_url( 'admin.php?page=et_bloom_options', 'https' ); // @phpcs:ignore ET.Sniffs.ValidVariableName.UsedPropertyNotSnakeCase -- No need to change prop name.
		}

		// v3 API uses JSON responses.
		$this->http->expects_json = true;

		$this->_maybe_set_custom_headers();
	}

	/**
	 * Create subscriber data array for sign_up_form endpoint.
	 * The /contacts/sign_up_form endpoint uses a different structure:
	 * - email_address as a string (not object)
	 * - list_memberships as array of strings (list_id values)
	 *
	 * @param array $args Subscriber data array.
	 *
	 * @return array Subscriber data array.
	 */
	protected function _create_subscriber_data_array( $args ) {
		$subscriber = $this->transform_data_to_provider_format( $args, 'subscriber' );

		// Constant Contact API v3 requires create_source field for compliance reasons.
		// Value "Account" indicates the account owner added the contact (not the contact adding themselves).
		$subscriber['create_source'] = 'Account';

		// /contacts/sign_up_form endpoint requires email_address as a string, not an object.
		// Extract email address from various possible formats.
		$email_address = '';
		if ( isset( $subscriber['email_addresses'] ) && is_array( $subscriber['email_addresses'] ) && ! empty( $subscriber['email_addresses'] ) ) {
			// Get first email address object from array.
			$email_obj = $subscriber['email_addresses'][0];
			if ( isset( $email_obj['email_address'] ) ) {
				$email_address = $email_obj['email_address'];
			} elseif ( isset( $email_obj['address'] ) ) {
				$email_address = $email_obj['address'];
			} elseif ( is_string( $email_obj ) ) {
				$email_address = $email_obj;
			}
		} elseif ( isset( $subscriber['email'] ) ) {
			$email_address = $subscriber['email'];
		} elseif ( isset( $args['email'] ) ) {
			$email_address = $args['email'];
		}

		// Set email_address as string (required by sign_up_form endpoint).
		if ( ! empty( $email_address ) ) {
			$subscriber['email_address'] = $email_address;
		}

		// Remove email_addresses array and email field if they exist (we're using email_address string now).
		unset( $subscriber['email_addresses'], $subscriber['email'] );

		// /contacts/sign_up_form endpoint requires list_memberships as array of strings (list_id values).
		// Not lists as array of objects.
		$list_id = isset( $args['list_id'] ) ? (string) $args['list_id'] : '';
		if ( ! empty( $list_id ) ) {
			// Initialize list_memberships array if it doesn't exist.
			if ( ! isset( $subscriber['list_memberships'] ) || ! is_array( $subscriber['list_memberships'] ) ) {
				$subscriber['list_memberships'] = array();
			}
			// Check if list_id already exists in list_memberships array.
			$list_exists = false;
			foreach ( $subscriber['list_memberships'] as $membership_list_id ) {
				if ( (string) $membership_list_id === $list_id ) {
					$list_exists = true;
					break;
				}
			}
			// Add list_id to list_memberships array if not already present.
			if ( ! $list_exists ) {
				$subscriber['list_memberships'][] = $list_id;
			}
		}

		// Remove lists array if it exists (sign_up_form uses list_memberships instead).
		unset( $subscriber['lists'] );

		return $subscriber;
	}

	/**
	 * Fetch custom fields.
	 *
	 * @param string $list_id List ID.
	 * @param array  $list List data array.
	 *
	 * @return array Custom fields array.
	 */
	protected function _fetch_custom_fields( $list_id = '', $list = array() ) {
		$fields = array();

		foreach ( range( 1, 15 ) as $i ) {
			$fields[ "custom_field_{$i}" ] = array(
				'field_id' => "custom_field_{$i}",
				'name'     => "custom_field_{$i}",
				'type'     => 'any',
			);
		}

		return $fields;
	}

	/**
	 * Get list from subscriber.
	 *
	 * @param array  $subscriber Subscriber data array.
	 * @param string $list_id List ID.
	 *
	 * @return array List data array.
	 */
	protected function _get_list_from_subscriber( $subscriber, $list_id ) {
		if ( ! isset( $subscriber['lists'] ) ) {
			return false;
		}

		foreach ( $subscriber['lists'] as &$list ) {
			if ( $list['list_id'] === $list_id ) {
				return $list;
			}
		}

		return false;
	}

	/**
	 * Maybe set custom headers.
	 *
	 * @return void
	 */
	protected function _maybe_set_custom_headers() {
		if ( empty( $this->custom_headers ) ) {
			// Use OAuth token from access_secret if available, otherwise fall back to manual token for backward compatibility.
			$token = isset( $this->data['access_secret'] ) ? $this->data['access_secret'] : ( isset( $this->data['token'] ) ? $this->data['token'] : '' );

			if ( ! empty( $token ) ) {
				$this->custom_headers = array(
					'Authorization' => 'Bearer ' . sanitize_text_field( $token ),
				);
			}
		}
	}

	/**
	 * Process custom fields.
	 *
	 * @param array $args Subscriber data array.
	 *
	 * @return array Subscriber data array.
	 */
	protected function _process_custom_fields( $args ) {
		if ( ! isset( $args['custom_fields'] ) ) {
			return $args;
		}

		$fields           = $args['custom_fields'];
		$processed_fields = array();

		unset( $args['custom_fields'], $this->_subscriber['custom_fields_unprocessed'] );

		foreach ( $fields as $field_id => $value ) {
			if ( is_array( $value ) && $value ) {
				// This is a multiple choice field (eg. checkbox, radio, select).
				$value = array_values( $value );

				if ( count( $value ) > 1 ) {
					$value = implode( ',', $value );
				} else {
					$value = array_pop( $value );
				}
			}

			$processed_fields[] = array(
				'name'  => $field_id,
				'value' => $value,
			);
		}

		if ( isset( $this->_subscriber['custom_fields'] ) ) {
			$processed_fields = array_merge( $processed_fields, $this->_subscriber['custom_fields'] );
		}

		$this->_subscriber['custom_fields'] = array_unique( $processed_fields, SORT_REGULAR );

		return $args;
	}

	/**
	 * Get account fields.
	 *
	 * @return array Account fields array.
	 */
	public function get_account_fields() {
		return array(
			'api_key'       => array(
				'label'    => esc_html__( 'API Key (Client ID)', 'et_core' ),
				'required' => 'https',
				'show_if'  => array( 'function.protocol' => 'https' ),
			),
			'client_secret' => array(
				'label'    => esc_html__( 'Client Secret', 'et_core' ),
				'required' => 'https',
				'show_if'  => array( 'function.protocol' => 'https' ),
			),
		);
	}

	/**
	 * Get data keymap.
	 *
	 * @param array $keymap Keymap array.
	 *
	 * @return array Keymap array.
	 *
	 * @since 5.0.0 Updated to support ConstantContact V3 API which uses different response structure than V2.
	 */
	public function get_data_keymap( $keymap = array() ) {
		$keymap = array(
			'list'         => array(
				'list_id'           => 'list_id', // v3 uses UUID list_id instead of numeric id.
				'name'              => 'name',
				'subscribers_count' => 'membership_count', // v3 uses membership_count.
			),
			'subscriber'   => array(
				'name'          => 'first_name',
				'last_name'     => 'last_name',
				'email'         => 'email_addresses.[0].email_address',
				'contact_id'    => 'contact_id', // v3 uses contact_id (UUID) instead of numeric id.
				'list_id'       => 'lists.[0].list_id', // v3 uses list_id (UUID).
				'custom_fields' => 'custom_fields_unprocessed',
			),
			'error'        => array(
				'error_message' => '[0].error_message',
			),
			'custom_field' => array(
				'field_id' => 'name',
				'name'     => 'name',
			),
		);

		return parent::get_data_keymap( $keymap );
	}

	/**
	 * Get subscriber.
	 *
	 * @param string $email Email address.
	 *
	 * @return array Subscriber data array.
	 */
	public function get_subscriber( $email ) {
		$url = add_query_arg( 'email', $email, $this->SUBSCRIBERS_URL );  // phpcs:ignore -- No need to change the class property.

		$this->_maybe_set_custom_headers();
		$this->prepare_request( $url, 'GET', false );
		$this->make_remote_request();

		// Handle token refresh if request returned 401 error.
		if ( 401 === $this->response->STATUS_CODE && ! empty( $this->data['refresh_token'] ) ) {
			if ( $this->_refresh_access_token() ) {
				// Retry the request after successful token refresh.
				$this->_maybe_set_custom_headers();
				$this->prepare_request( $url, 'GET', false );
				$this->make_remote_request();
			} else {
				// Refresh failed, return empty array.
				return array();
			}
		}

		if ( $this->response->ERROR || ! isset( $this->response->DATA['contacts'] ) ) {
			return array();
		}

		return $this->response->DATA['contacts'];
	}

	/**
	 * Authenticate using OAuth 2.0 Authorization Code Flow (v3 API).
	 * Constant Contact v3 API uses Authorization Code Flow which requires client_secret.
	 * According to: https://v3.developer.constantcontact.com/api_guide/server_flow.html
	 *
	 * @return array|bool
	 */
	public function authenticate() {
		et_core_nonce_verified_previously();

		// Check if we're handling the OAuth callback with authorization code.
		// For Authorization Code Flow, Constant Contact returns 'code' as query parameter.
		if ( ! empty( $_GET['code'] ) ) {
			// Set consumer credentials for OAuth helper (maps to client_id:client_secret for Basic Auth).
			$this->data['consumer_key']    = $this->data['api_key'];
			$this->data['consumer_secret'] = $this->data['client_secret'];

			// Exchange authorization code for access token.
			// v3 API requires Basic Auth: base64(client_id:client_secret) in Authorization header.
			return $this->_do_oauth_access_token_request_v3();
		}

		// If already authenticated, return true.
		if ( $this->is_authenticated() ) {
			return true;
		}

		// Initiate OAuth Authorization Code Flow authorization.
		// According to Constant Contact API v3 docs: https://v3.developer.constantcontact.com/api_guide/server_flow.html
		// Required parameters: response_type=code, client_id (API key), redirect_uri, state
		// Optional: scope (contact_data campaign_data offline_access).
		$nonce = wp_create_nonce( 'et_core_api_service_oauth2' );
		$args  = array(
			'client_id'     => $this->data['api_key'],
			'response_type' => 'code', // Authorization Code Flow uses 'code'.
			'state'         => rawurlencode( "ET_Core|{$this->slug}|{$this->account_name}|{$nonce}" ),
			'redirect_uri'  => $this->REDIRECT_URL, // @phpcs:ignore -- No need to change the class property
			'scope'         => rawurlencode( 'contact_data campaign_data offline_access' ), // Required scopes for v3 API.
		);

		$this->save_data();

		return array( 'redirect_url' => add_query_arg( $args, $this->AUTHORIZATION_URL ) ); // phpcs:ignore -- No need to change the class property.
	}

	/**
	 * Exchange authorization code for access token (v3 API).
	 * v3 API requires Basic Auth with base64(client_id:client_secret) in Authorization header.
	 *
	 * @return bool
	 */
	private function _do_oauth_access_token_request_v3() {
		if ( empty( $this->data['api_key'] ) || empty( $this->data['client_secret'] ) ) {
			return false;
		}

		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No need to verify nonce.

		// Create Basic Auth header: base64(client_id:client_secret).
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- No need to change the function name.
		$credentials = base64_encode( $this->data['api_key'] . ':' . $this->data['client_secret'] );

		// Prepare token request URL with query parameters.
		// According to v3 API docs, parameters go in query string, not body.
		$token_url = add_query_arg(
			array(
				'code'         => $code,
				'redirect_uri' => $this->REDIRECT_URL, // @phpcs:ignore -- No need to change the class property
				'grant_type'   => 'authorization_code',
			),
			$this->ACCESS_TOKEN_URL // phpcs:ignore -- No need to change the class property.
		);

		// Prepare request - POST with empty body (parameters in query string).
		$this->prepare_request( $token_url, 'POST', false, '', false );
		$this->request->HEADERS['Authorization'] = 'Basic ' . $credentials;
		$this->request->HEADERS['Accept']        = 'application/json';
		$this->request->HEADERS['Content-Type']  = 'application/x-www-form-urlencoded';
		$this->request->BODY                     = ''; // phpcs:ignore -- No need to change the class property. Empty body for POST with query params.

		// Ensure JSON response is expected and will be decoded.
		$this->http->expects_json = true;

		// Temporarily disable OAuth processing since we're handling Basic Auth manually.
		$uses_oauth_backup = $this->uses_oauth;
		$this->uses_oauth  = false;

		$this->make_remote_request();

		// Restore OAuth setting.
		$this->uses_oauth = $uses_oauth_backup;

		// Log detailed error information for debugging.
		if ( $this->response->ERROR ) {
			// Redact sensitive Authorization header before logging to prevent credential exposure.
			$logged_headers = $this->request->HEADERS;
			if ( isset( $logged_headers['Authorization'] ) ) {
				$logged_headers['Authorization'] = '[REDACTED]';
			}

			$error_details = array(
				'status_code' => isset( $this->response->STATUS_CODE ) ? $this->response->STATUS_CODE : 'N/A',
				'error'       => isset( $this->response->ERROR ) ? $this->response->ERROR : 'Unknown error',
				'response'    => isset( $this->response->DATA ) ? $this->response->DATA : 'No response data',
				'url'         => $token_url,
				'headers'     => $logged_headers,
			);
			ET_Core_Logger::debug( 'Constant Contact OAuth token exchange failed: ' . wp_json_encode( $error_details ) );
			return false;
		}

		// Process response - v3 API returns JSON with access_token, refresh_token, etc.
		$response_data = $this->response->DATA;

		if ( empty( $response_data['access_token'] ) ) {
			$error_details = array(
				'status_code' => isset( $this->response->STATUS_CODE ) ? $this->response->STATUS_CODE : 'N/A',
				'response'    => $response_data,
				'url'         => $this->ACCESS_TOKEN_URL, // phpcs:ignore -- No need to change the class property
			);
			ET_Core_Logger::debug( 'Constant Contact OAuth token exchange: access_token missing in response: ' . wp_json_encode( $error_details ) );
			return false;
		}

		// Store tokens.
		$this->data['access_secret'] = sanitize_text_field( $response_data['access_token'] );
		$this->data['is_authorized'] = true;

		if ( ! empty( $response_data['refresh_token'] ) ) {
			$this->data['refresh_token'] = sanitize_text_field( $response_data['refresh_token'] );
		}

		// Ensure consumer credentials are set for OAuth helper initialization.
		$this->data['consumer_key']    = $this->data['api_key'];
		$this->data['consumer_secret'] = $this->data['client_secret'];

		$this->save_data();

		// Initialize OAuth helper after successful authentication so it can be used for subsequent API requests.
		$this->_initialize_oauth_helper();

		return true;
	}

	/**
	 * Refresh OAuth access token using refresh token (v3 API).
	 * Called automatically when API requests return 401 Unauthorized errors.
	 *
	 * @return bool True if token refresh succeeded, false otherwise.
	 */
	private function _refresh_access_token() {
		// Check if refresh_token exists.
		if ( empty( $this->data['refresh_token'] ) ) {
			return false;
		}

		// Check if client credentials are available.
		if ( empty( $this->data['api_key'] ) || empty( $this->data['client_secret'] ) ) {
			return false;
		}

		// Create Basic Auth header: base64(client_id:client_secret).
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- No need to change the function name.
		$credentials = base64_encode( $this->data['api_key'] . ':' . $this->data['client_secret'] );

		// Prepare refresh token request URL with query parameters.
		// According to v3 API docs, parameters go in query string, not body.
		$token_url = add_query_arg(
			array(
				'refresh_token' => $this->data['refresh_token'],
				'redirect_uri'  => $this->REDIRECT_URL, // @phpcs:ignore -- No need to change the class property
				'grant_type'    => 'refresh_token',
			),
			$this->ACCESS_TOKEN_URL // phpcs:ignore -- No need to change the class property.
		);

		// Prepare request - POST with empty body (parameters in query string).
		$this->prepare_request( $token_url, 'POST', false, '', false );

		// Clear custom_headers to prevent Bearer token from overwriting Basic Auth.
		$this->custom_headers = array();

		// Set headers on http->request directly since make_remote_request() uses that object.
		$this->http->request->HEADERS['Authorization'] = 'Basic ' . $credentials;
		$this->http->request->HEADERS['Accept']        = 'application/json';
		$this->http->request->HEADERS['Content-Type']  = 'application/x-www-form-urlencoded';
		$this->http->request->BODY                     = ''; // phpcs:ignore -- No need to change the class property. Empty body for POST with query params.
		// Also update $this->request for consistency.
		$this->request->HEADERS['Authorization'] = 'Basic ' . $credentials;
		$this->request->HEADERS['Accept']        = 'application/json';
		$this->request->HEADERS['Content-Type']  = 'application/x-www-form-urlencoded';
		$this->request->BODY                     = ''; // phpcs:ignore -- No need to change the class property. Empty body for POST with query params.

		// Ensure JSON response is expected and will be decoded.
		$this->http->expects_json = true;

		// Temporarily disable OAuth processing since we're handling Basic Auth manually.
		$uses_oauth_backup = $this->uses_oauth;
		$this->uses_oauth  = false;

		$this->make_remote_request();

		// Restore OAuth setting.
		$this->uses_oauth = $uses_oauth_backup;

		// Check if refresh request failed.
		if ( $this->response->ERROR || empty( $this->response->DATA['access_token'] ) ) {
			$error_details = array(
				'status_code' => isset( $this->response->STATUS_CODE ) ? $this->response->STATUS_CODE : 'N/A',
				'error'       => isset( $this->response->ERROR ) ? $this->response->ERROR : 'Unknown error',
				'response'    => isset( $this->response->DATA ) ? $this->response->DATA : 'No response data',
				'url'         => $token_url,
			);
			ET_Core_Logger::debug( 'Constant Contact OAuth token refresh failed: ' . wp_json_encode( $error_details ) );
			return false;
		}

		// Process response - v3 API returns JSON with access_token and optionally new refresh_token.
		$response_data = $this->response->DATA;

		// Store new access token.
		$this->data['access_secret'] = sanitize_text_field( $response_data['access_token'] );

		// Update refresh_token if new one is provided (some APIs issue new refresh tokens).
		if ( ! empty( $response_data['refresh_token'] ) ) {
			$this->data['refresh_token'] = sanitize_text_field( $response_data['refresh_token'] );
		}

		// Ensure consumer credentials are set for OAuth helper initialization.
		$this->data['consumer_key']    = $this->data['api_key'];
		$this->data['consumer_secret'] = $this->data['client_secret'];

		$this->save_data();

		// Reinitialize OAuth helper with new token.
		$this->_maybe_set_custom_headers();
		$this->_initialize_oauth_helper();

		return true;
	}

	/**
	 * Fetch subscriber lists.
	 *
	 * @return string
	 */
	public function fetch_subscriber_lists() {
		// Check if OAuth credentials are provided (v3 API requires both api_key and client_secret).
		if ( isset( $this->data['api_key'], $this->data['client_secret'] ) && ! empty( $this->data['api_key'] ) && ! empty( $this->data['client_secret'] ) ) {
			// Fetch lists if user already authenticated.
			if ( $this->is_authenticated() ) {
				// Ensure consumer credentials are set for OAuth helper initialization.
				if ( ! isset( $this->data['consumer_key'] ) ) {
					$this->data['consumer_key']    = $this->data['api_key'];
					$this->data['consumer_secret'] = $this->data['client_secret'];
				}
				// Ensure OAuth helper is initialized for authenticated requests.
				$this->_initialize_oauth_helper();
				return $this->_fetch_subscriber_lists();
			}

			$authenticated = $this->authenticate();
			// If the authenticating process returns an array with redirect url to complete OAuth authorization.
			if ( is_array( $authenticated ) ) {
				return $authenticated;
			}

			if ( true === $authenticated ) {
				// OAuth helper already initialized in _do_oauth_access_token_request_v3() after token exchange.
				return $this->_fetch_subscriber_lists();
			}

			return false;
		}

		// Fallback to manual token for backward compatibility (v2 API).
		if ( empty( $this->data['api_key'] ) || empty( $this->data['token'] ) ) {
			return $this->API_KEY_REQUIRED; // phpcs:ignore -- No need to change the class property.
		}

		$this->_maybe_set_custom_headers();

		$this->response_data_key = false;

		$this->LISTS_URL = add_query_arg( 'api_key', $this->data['api_key'], $this->LISTS_URL ); // phpcs:ignore -- No need to change the class property.

		return parent::fetch_subscriber_lists();
	}

	/**
	 * Fetch subscriber lists from Constant Contact API v3.
	 *
	 * @return string
	 */
	protected function _fetch_subscriber_lists() {
		$this->_maybe_set_custom_headers();

		// v3 API doesn't use api_key query parameter - uses Bearer token in Authorization header.
		$this->response_data_key = 'lists'; // v3 API returns lists in 'lists' array.

		$result = parent::fetch_subscriber_lists();

		// Handle token refresh if request returned 401 error.
		if ( 401 === $this->response->STATUS_CODE && ! empty( $this->data['refresh_token'] ) ) {
			if ( $this->_refresh_access_token() ) {
				// Retry the request after successful token refresh.
				$this->_maybe_set_custom_headers();
				$result = parent::fetch_subscriber_lists();
			} else {
				// Refresh failed, return error message.
				return $this->get_error_message();
			}
		}

		return $result;
	}

	/**
	 * Subscribe to a list.
	 * Uses /contacts/sign_up_form endpoint which automatically handles both creating new contacts
	 * and updating existing contacts based on email address.
	 *
	 * @param array  $args Subscriber data array.
	 * @param string $url Subscribe URL (ignored, always uses sign_up_form endpoint).
	 *
	 * @return string Result.
	 */
	public function subscribe( $args, $url = '' ) {
		// v3 API uses Bearer token authentication only (no query parameters).
		$this->_maybe_set_custom_headers();
		$args['list_id'] = (string) $args['list_id'];

		// /contacts/sign_up_form endpoint handles both creating and updating contacts automatically.
		// No need to check if subscriber exists first - the endpoint does this automatically.
		$subscriber = $this->_create_subscriber_data_array( $args );

		$this->_subscriber = &$subscriber;

		$args = $this->_process_custom_fields( $args );

		// Always use POST to /contacts/sign_up_form endpoint.
		$this->prepare_request( $this->SUBSCRIBE_URL, 'POST', false, $subscriber, true ); // phpcs:ignore -- No need to change the class property.

		// Make the request.
		$this->make_remote_request();

		// Handle token refresh if request returned 401 error.
		if ( 401 === $this->response->STATUS_CODE && ! empty( $this->data['refresh_token'] ) ) {
			if ( $this->_refresh_access_token() ) {
				// Retry the request after successful token refresh.
				$this->_maybe_set_custom_headers();
				$this->prepare_request( $this->SUBSCRIBE_URL, 'POST', false, $subscriber, true ); // phpcs:ignore -- No need to change the class property.
				$this->make_remote_request();
			} else {
				// Refresh failed, return error.
				return $this->get_error_message();
			}
		}

		$result = $this->response->ERROR ? $this->get_error_message() : 'success';

		return $result;
	}
}
