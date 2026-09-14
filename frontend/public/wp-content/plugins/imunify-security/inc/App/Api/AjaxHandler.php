<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Api;

use CloudLinux\Imunify\App\DataStore;
use CloudLinux\Imunify\App\Defender\DisabledRulesManager;
use CloudLinux\Imunify\App\Exception\ApiException;

/**
 * AJAX Handler class.
 */
class AjaxHandler {

	/**
	 * AJAX action name.
	 */
	const AJAX_ACTION = 'imunify_security';

	/**
	 * Nonce name for AJAX requests.
	 *
	 * @var string
	 */
	const AJAX_NONCE_NAME = 'imunify_security_ajax_nonce';

	/**
	 * DataStore instance.
	 *
	 * @var DataStore
	 */
	private $dataStore;

	/**
	 * DisabledRulesManager instance.
	 *
	 * @var DisabledRulesManager
	 */
	private $disabledRulesManager;

	/**
	 * Constructor.
	 *
	 * @param DataStore            $dataStore            Data store instance.
	 * @param DisabledRulesManager $disabledRulesManager Disabled rules manager instance.
	 */
	public function __construct( DataStore $dataStore, DisabledRulesManager $disabledRulesManager ) {
		$this->dataStore            = $dataStore;
		$this->disabledRulesManager = $disabledRulesManager;
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handleAjaxRequest' ) );
	}

	/**
	 * Handle AJAX request.
	 *
	 * @return void
	 */
	public function handleAjaxRequest() {
		list( $statusCode, $response ) = $this->processRequest();
		wp_send_json( $response, $statusCode );
	}

	/**
	 * Process AJAX request and return response code and data.
	 *
	 * @return array Array with status code (int) and response array with 'data', 'messages', and 'result' keys.
	 */
	public function processRequest() {

		// Initialize response data.
		$response = array(
			'data'     => array(),
			'messages' => array(),
			'result'   => 'error',
		);

		// Check user capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			$response['messages'] [] = esc_html__( 'Insufficient permissions.', 'imunify-security' );
			return array( 403, $response );
		}

		// Verify nonce.
		$nonce = isset( $_REQUEST['_ajax_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_ajax_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::AJAX_NONCE_NAME ) ) {
			$response['messages'] [] = esc_html__( 'Invalid security token.', 'imunify-security' );
			return array( 403, $response );
		}

		// Check for the JSON payload.
		$json = file_get_contents( 'php://input' );
		$data = json_decode( $json, true );

		// Check if method and params are set.
		if ( isset( $data['method'] ) && is_array( $data['method'] ) && isset( $data['params'] ) && is_array( $data['params'] ) ) {
			try {
				// Process the method and params.
				$method = $data['method'];
				$params = $data['params'];

				// Check if this is a WordPress rules command to handle locally.
				if ( $this->isWordpressRulesCommand( $method ) ) {
					$response = $this->handleWordpressRulesCommand( $method, $params );
				} else {
					// Get the data from the data store.
					$response = $this->dataStore->loadData( $method, $params );
				}
			} catch ( ApiException $exception ) {
				$response['messages'][] = $exception->getMessage();
			}
		} else {
			$response['messages'][] = esc_html__( 'Invalid input data.', 'imunify-security' );
		}

		return array( 200, $response );
	}

	/**
	 * Check if the method is a WordPress rules command that should be handled locally.
	 *
	 * @since 3.0.0
	 *
	 * @param array $method The method array.
	 *
	 * @return bool True if the command should be handled locally.
	 */
	private function isWordpressRulesCommand( $method ) {
		// Check for wordpress-plugin rules enable/disable commands.
		if ( count( $method ) < 3 ) {
			return false;
		}

		if ( 'wordpress-plugin' !== $method[0] || 'rules' !== $method[1] ) {
			return false;
		}

		return in_array( $method[2], array( 'disable', 'enable' ), true );
	}

	/**
	 * Handle WordPress rules commands locally.
	 *
	 * @since 3.0.0
	 *
	 * @param array $method The method array.
	 * @param array $params The request parameters.
	 *
	 * @return array Response array with 'data', 'messages', and 'result' keys.
	 */
	private function handleWordpressRulesCommand( $method, $params ) {
		$response = array(
			'data'     => array(),
			'messages' => array(),
			'result'   => 'error',
		);

		// Get the rule ID from params.
		$ruleId = isset( $params['rule'] ) ? sanitize_text_field( $params['rule'] ) : '';
		if ( empty( $ruleId ) ) {
			$response['messages'][] = esc_html__( 'Rule ID is required.', 'imunify-security' );
			return $response;
		}

		// Get the current user ID.
		$userId = get_current_user_id();

		$action = $method[2];
		if ( 'disable' === $action ) {
			$this->disabledRulesManager->disableRule( $ruleId, $userId );
			$response['result'] = 'success';
		} elseif ( 'enable' === $action ) {
			$this->disabledRulesManager->enableRule( $ruleId, $userId );
			$response['result'] = 'success';
		}

		return $response;
	}
}
