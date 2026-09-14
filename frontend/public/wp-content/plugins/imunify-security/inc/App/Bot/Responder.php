<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Emits the HTTP response that corresponds to a RateLimitDecision.
 *
 * Centralising exit() in a single class keeps the Pipeline pure: it
 * returns a decision and Responder either terminates or returns to the
 * caller. forTests() swaps PHP's side-effecting header() / exit for
 * in-memory capture so unit tests assert on the captured state
 * without crashing the test runner.
 *
 * Response bodies are intentionally plain text: a rate-limited bot
 * does not render HTML, and a short body keeps the hot path cheap.
 *
 * @since 4.0.0
 */
class Responder {

	/**
	 * Whether respond() should capture output instead of emitting and exiting.
	 *
	 * @var bool
	 */
	private $testMode;

	/**
	 * In-memory capture of the last emitted response when test mode is on.
	 *
	 * @var array
	 */
	private $captured = array(
		'status'  => null,
		'headers' => array(),
		'body'    => '',
		'exited'  => false,
	);

	/**
	 * Construct a production or test-mode responder.
	 *
	 * @param bool $test_mode Pass true to capture output instead of emitting it.
	 */
	public function __construct( $test_mode = false ) {
		$this->testMode = (bool) $test_mode;
	}

	/**
	 * Named constructor used by unit tests.
	 *
	 * @return self
	 */
	public static function forTests() {
		return new self( true );
	}

	/**
	 * Emit (or capture) the response matching $decision.
	 *
	 * @param RateLimitDecision $decision Decision from RateLimiter::check().
	 * @return void
	 */
	public function respond( $decision ) {
		if ( $decision->isAllowed() ) {
			return;
		}
		$status = $decision->getHttpStatus();
		$this->setStatus( $status );

		if ( RateLimitDecision::ACTION_RATE_LIMIT === $decision->getAction() ) {
			$this->header( 'Retry-After: ' . $decision->getRetryAfter() );
			$body = "Rate limit exceeded. Please retry later.\n";
		} else {
			$body = "Forbidden.\n";
		}
		$this->header( 'Content-Type: text/plain; charset=UTF-8' );
		$this->write( $body );
		$this->terminate();
	}

	/**
	 * Test-mode introspection.
	 *
	 * @return array
	 */
	public function captured() {
		return $this->captured;
	}

	/**
	 * Emit or capture an HTTP status code.
	 *
	 * @param int $status HTTP status code.
	 * @return void
	 */
	private function setStatus( $status ) {
		if ( $this->testMode ) {
			$this->captured['status'] = $status;
			return;
		}
		// @phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- required to set HTTP response code before WP loads.
		http_response_code( $status );
	}

	/**
	 * Emit or capture a single HTTP header line.
	 *
	 * @param string $line Full header line including name: value.
	 * @return void
	 */
	private function header( $line ) {
		if ( $this->testMode ) {
			$this->captured['headers'][] = $line;
			return;
		}
		// @phpcs:ignore WordPress.Security.EscapeOutput -- literal / integer header values only.
		header( $line );
	}

	/**
	 * Emit or capture the response body.
	 *
	 * @param string $body Response body.
	 * @return void
	 */
	private function write( $body ) {
		if ( $this->testMode ) {
			$this->captured['body'] = $body;
			return;
		}
		// @phpcs:ignore WordPress.Security.EscapeOutput -- plain-text literal response body.
		echo $body;
	}

	/**
	 * Terminate the request (exit) or mark capture in test mode.
	 *
	 * @return void
	 */
	private function terminate() {
		if ( $this->testMode ) {
			$this->captured['exited'] = true;
			return;
		}
		exit;
	}
}
