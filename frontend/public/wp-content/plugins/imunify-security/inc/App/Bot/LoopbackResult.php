<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Immutable outcome of a self-reachability (loopback) probe.
 *
 * Three states, mirroring WordPress core's Site Health loopback handling:
 *   - OK           — the site answered HTTP 200; it can reach itself.
 *   - FAILED       — transport error (WP_Error / empty URL); the request
 *                    never completed.
 *   - INCONCLUSIVE — the site answered, but with a non-200 status (a WAF
 *                    403, a 5xx, a redirect). Treated as a warning, not a
 *                    hard failure, because the cause is often a front-end
 *                    layer rather than a broken site.
 *
 * The reason string carries a short diagnostic (an error message or the
 * observed HTTP code) for persistence and support, never user-facing copy.
 *
 * @since 4.1.0
 */
class LoopbackResult {

	const STATE_OK           = 'ok';
	const STATE_FAILED       = 'failed';
	const STATE_INCONCLUSIVE = 'inconclusive';

	/**
	 * One of the STATE_* constants.
	 *
	 * @var string
	 */
	private $state;

	/**
	 * Short diagnostic reason (error message or "HTTP <code>"). Empty for OK.
	 *
	 * @var string
	 */
	private $reason;

	/**
	 * Private constructor; use the named factory methods.
	 *
	 * @param string $state  STATE_* constant.
	 * @param string $reason Short diagnostic reason.
	 */
	private function __construct( $state, $reason ) {
		$this->state  = $state;
		$this->reason = (string) $reason;
	}

	/**
	 * Build an OK result (HTTP 200, no reason).
	 *
	 * @return self
	 */
	public static function ok() {
		return new self( self::STATE_OK, '' );
	}

	/**
	 * Build a FAILED result (transport error / unreachable).
	 *
	 * @param string $reason Short diagnostic reason.
	 * @return self
	 */
	public static function failed( $reason ) {
		return new self( self::STATE_FAILED, $reason );
	}

	/**
	 * Build an INCONCLUSIVE result (answered with a non-200 status).
	 *
	 * @param string $reason Short diagnostic reason.
	 * @return self
	 */
	public static function inconclusive( $reason ) {
		return new self( self::STATE_INCONCLUSIVE, $reason );
	}

	/**
	 * Result state (one of the STATE_* constants).
	 *
	 * @return string
	 */
	public function getState() {
		return $this->state;
	}

	/**
	 * Short diagnostic reason. Empty string for an OK result.
	 *
	 * @return string
	 */
	public function getReason() {
		return $this->reason;
	}

	/**
	 * Whether the site successfully reached itself.
	 *
	 * @return bool
	 */
	public function isOk() {
		return self::STATE_OK === $this->state;
	}
}
