<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Immutable HTTP GET outcome: status, body and response headers, or the
 * transport error that stopped the request from completing.
 *
 * Carries the whole response rather than just the body so callers can verify
 * what they received (`content-length` / `x-body-size` against the real byte
 * count) and report traceable values (`x-req-id`, `x-cache`) when it does not
 * add up.
 *
 * @since 4.1.0
 */
class HttpResponse {

	/**
	 * HTTP status code; 0 when the request never completed.
	 *
	 * @var int
	 */
	private $status;

	/**
	 * Response body as received.
	 *
	 * @var string
	 */
	private $body;

	/**
	 * Response headers keyed by lowercased name.
	 *
	 * @var array<string,string>
	 */
	private $headers;

	/**
	 * Transport error message; empty when the request completed.
	 *
	 * @var string
	 */
	private $error;

	/**
	 * Private constructor; use the named factory methods.
	 *
	 * @param int                 $status  HTTP status code.
	 * @param string              $body    Response body.
	 * @param array<string,mixed> $headers Response headers.
	 * @param string              $error   Transport error message.
	 */
	private function __construct( $status, $body, $headers, $error ) {
		$this->status  = (int) $status;
		$this->body    = (string) $body;
		$this->headers = self::normalize( $headers );
		$this->error   = (string) $error;
	}

	/**
	 * Build a response for a request that reached the server.
	 *
	 * @param int                 $status  HTTP status code.
	 * @param string              $body    Response body.
	 * @param array<string,mixed> $headers Response headers.
	 * @return self
	 */
	public static function received( $status, $body, $headers = array() ) {
		return new self( $status, $body, $headers, '' );
	}

	/**
	 * Build a response for a request that never completed (DNS, TLS, timeout).
	 *
	 * @param string $message Transport error message.
	 * @return self
	 */
	public static function transportError( $message ) {
		return new self( 0, '', array(), $message );
	}

	/**
	 * HTTP status code, 0 for a transport error.
	 *
	 * @return int
	 */
	public function status() {
		return $this->status;
	}

	/**
	 * Response body.
	 *
	 * @return string
	 */
	public function body() {
		return $this->body;
	}

	/**
	 * Number of bytes received in the body.
	 *
	 * @return int
	 */
	public function bodySize() {
		return strlen( $this->body );
	}

	/**
	 * Header value, or '' when the response does not carry it.
	 *
	 * @param string $name Header name, any case.
	 * @return string
	 */
	public function header( $name ) {
		$key = strtolower( (string) $name );
		return isset( $this->headers[ $key ] ) ? $this->headers[ $key ] : '';
	}

	/**
	 * Header value as a byte count, or null when absent or not a plain
	 * non-negative integer (`chunked`, an HTTP date, a malformed value).
	 *
	 * @param string $name Header name, any case.
	 * @return int|null
	 */
	public function intHeader( $name ) {
		$raw = trim( $this->header( $name ) );
		if ( '' === $raw || ! ctype_digit( $raw ) ) {
			return null;
		}
		return (int) $raw;
	}

	/**
	 * Whether the request failed before a response was received.
	 *
	 * @return bool
	 */
	public function isTransportError() {
		return '' !== $this->error;
	}

	/**
	 * Transport error message; '' when the request completed.
	 *
	 * @return string
	 */
	public function errorMessage() {
		return $this->error;
	}

	/**
	 * Lowercase header names and flatten repeated headers to their first value.
	 *
	 * @param array<string,mixed> $headers Raw headers.
	 * @return array<string,string>
	 */
	private static function normalize( $headers ) {
		$out = array();
		if ( ! is_array( $headers ) ) {
			return $out;
		}
		foreach ( $headers as $name => $value ) {
			if ( is_array( $value ) ) {
				$value = count( $value ) > 0 ? reset( $value ) : '';
			}
			if ( is_scalar( $value ) ) {
				$out[ strtolower( (string) $name ) ] = (string) $value;
			}
		}
		return $out;
	}
}
