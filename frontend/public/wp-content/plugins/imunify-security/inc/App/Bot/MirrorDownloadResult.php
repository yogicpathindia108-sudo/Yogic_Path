<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Immutable outcome of a {@see MirrorDownloader} download sequence.
 *
 * A failure carries no body — the caller has nothing to parse or cache — plus
 * the context the Server team needs to match the failure against the mirror
 * logs (`x_req_id`, the three sizes, the URL and the attempt count).
 *
 * @since 4.1.0
 */
class MirrorDownloadResult {

	const REASON_TRANSPORT       = 'transport-error';
	const REASON_HTTP_STATUS     = 'http-status';
	const REASON_PARTIAL_CONTENT = 'partial-content';
	const REASON_EMPTY_BODY      = 'empty-body';
	const REASON_SIZE_MISMATCH   = 'size-mismatch';
	const REASON_MD5_MISMATCH    = 'md5-mismatch';

	/**
	 * Whether the download produced a verified body.
	 *
	 * @var bool
	 */
	private $success;

	/**
	 * Verified body; '' on failure.
	 *
	 * @var string
	 */
	private $body;

	/**
	 * Requests performed.
	 *
	 * @var int
	 */
	private $attempts;

	/**
	 * REASON_* slug; '' on success.
	 *
	 * @var string
	 */
	private $reason;

	/**
	 * Reportable details of the last attempt.
	 *
	 * @var array<string,mixed>
	 */
	private $context;

	/**
	 * Private constructor; use the named factory methods.
	 *
	 * @param bool                $success  Whether the download succeeded.
	 * @param string              $body     Verified body; '' on failure.
	 * @param int                 $attempts Requests performed.
	 * @param string              $reason   REASON_* slug; '' on success.
	 * @param array<string,mixed> $context  Reportable details of the last attempt.
	 */
	private function __construct( $success, $body, $attempts, $reason, $context ) {
		$this->success  = (bool) $success;
		$this->body     = (string) $body;
		$this->attempts = (int) $attempts;
		$this->reason   = (string) $reason;
		$this->context  = $context;
	}

	/**
	 * Build the outcome of a download that produced a verified body.
	 *
	 * @param string              $body     Verified body.
	 * @param int                 $attempts Requests performed.
	 * @param array<string,mixed> $context  Details of the successful attempt.
	 * @return self
	 */
	public static function success( $body, $attempts, $context ) {
		return new self( true, $body, $attempts, '', $context );
	}

	/**
	 * Build the outcome of a download that could not be verified.
	 *
	 * @param string              $reason   REASON_* slug.
	 * @param int                 $attempts Requests performed.
	 * @param array<string,mixed> $context  Details of the last attempt.
	 * @return self
	 */
	public static function failure( $reason, $attempts, $context ) {
		return new self( false, '', $attempts, $reason, $context );
	}

	/**
	 * Whether the download produced a verified body.
	 *
	 * @return bool
	 */
	public function isSuccess() {
		return $this->success;
	}

	/**
	 * Verified body; '' when the download failed.
	 *
	 * @return string
	 */
	public function body() {
		return $this->body;
	}

	/**
	 * Number of requests performed.
	 *
	 * @return int
	 */
	public function attempts() {
		return $this->attempts;
	}

	/**
	 * REASON_* slug; '' when the download succeeded.
	 *
	 * @return string
	 */
	public function reason() {
		return $this->reason;
	}

	/**
	 * Reportable details of the last attempt.
	 *
	 * @return array<string,mixed>
	 */
	public function context() {
		return $this->context;
	}
}
