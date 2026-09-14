<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * HttpClient implementation that wraps wp_remote_get.
 *
 * Reports whatever came back — status, headers, body — without judging it, so
 * MirrorDownloader can tell a truncated body apart from a 404 and report the
 * mirror's own `x-req-id`. A WP_Error becomes a transport error.
 *
 * @since 4.0.0
 */
class WpHttpClient implements HttpClient {

	/**
	 * Timeout for a single request. Short on purpose: these requests run from
	 * wp-cron, which often piggybacks on a visitor's page load.
	 */
	const DEFAULT_TIMEOUT_SECONDS = 5;

	/**
	 * Upper bound on response body size (bytes) — protects against memory
	 * exhaustion if a compromised or misbehaving source returns an outsized
	 * payload. The largest bundled dataset (Azure service tags) is ~500KB,
	 * so 16MB leaves generous headroom while capping catastrophic growth.
	 */
	const DEFAULT_MAX_BODY_BYTES = 16777216;

	/**
	 * Timeout passed to wp_remote_get (seconds).
	 *
	 * @var int
	 */
	private $timeout;

	/**
	 * Maximum response body size in bytes.
	 *
	 * @var int
	 */
	private $max_body_bytes;

	/**
	 * Build the client with a configurable timeout and response-size cap.
	 *
	 * @param int $timeout        Timeout in seconds.
	 * @param int $max_body_bytes Upper bound on response body size.
	 */
	public function __construct( $timeout = self::DEFAULT_TIMEOUT_SECONDS, $max_body_bytes = self::DEFAULT_MAX_BODY_BYTES ) {
		// max() with a 1-second floor keeps a caller accidentally passing 0 (which
		// wp_remote_get interprets as "no timeout") from parking a request forever.
		$this->timeout        = max( 1, (int) $timeout );
		$this->max_body_bytes = max( 1, (int) $max_body_bytes );
	}

	/**
	 * Fetch $url via wp_remote_get.
	 *
	 * @param string               $url     Absolute URL.
	 * @param array<string,string> $headers Extra request headers.
	 * @param int|null             $timeout Timeout for this request; the client's own
	 *                                      when null, and never longer than it.
	 * @return HttpResponse
	 */
	public function get( $url, $headers = array(), $timeout = null ) {
		$version  = defined( 'IMUNIFY_SECURITY_VERSION' ) ? IMUNIFY_SECURITY_VERSION : '0.0.0';
		$response = wp_remote_get(
			$url,
			array(
				'timeout'             => $this->timeoutFor( $timeout ),
				'sslverify'           => true,
				'user-agent'          => 'ImunifySecurity-BotData/' . $version . ' (+https://imunify360.com)',
				'limit_response_size' => $this->max_body_bytes,
				'headers'             => is_array( $headers ) ? $headers : array(),
			)
		);
		if ( is_wp_error( $response ) ) {
			return HttpResponse::transportError( $response->get_error_message() );
		}
		$body = wp_remote_retrieve_body( $response );
		return HttpResponse::received(
			(int) wp_remote_retrieve_response_code( $response ),
			is_string( $body ) ? $body : '',
			$this->responseHeaders( $response )
		);
	}

	/**
	 * Timeout to use for one request: the caller may shorten the client's
	 * timeout (to stay inside a retry budget) but never extend it, and the
	 * 1-second floor keeps a 0 from being read as "no timeout".
	 *
	 * @param int|null $requested Timeout the caller asked for, or null.
	 * @return int
	 */
	private function timeoutFor( $requested ) {
		if ( null === $requested ) {
			return $this->timeout;
		}
		return max( 1, min( $this->timeout, (int) $requested ) );
	}

	/**
	 * Response headers as a plain array. wp_remote_retrieve_headers() returns a
	 * case-insensitive dictionary object on modern WordPress and a plain array
	 * on older releases, so both shapes are accepted.
	 *
	 * @param array|\WP_Error $response Raw wp_remote_get return value.
	 * @return array<string,mixed>
	 */
	private function responseHeaders( $response ) {
		$headers = wp_remote_retrieve_headers( $response );
		if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
			$headers = $headers->getAll();
		}
		return is_array( $headers ) ? $headers : array();
	}
}
