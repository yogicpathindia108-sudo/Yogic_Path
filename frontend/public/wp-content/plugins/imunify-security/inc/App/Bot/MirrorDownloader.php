<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Downloads a mirror object, verifies that what arrived is what the mirror
 * meant to send, and retries a corrupt or transient response.
 *
 * The mirror occasionally answers HTTP 200 with a truncated body, and the
 * truncated bytes can be cached at the edge, so a plain retry may hand back
 * the same bad response. Each retry therefore carries no-cache request
 * headers, and a last attempt that keeps getting an `x-cache: hit` adds a
 * throwaway query parameter to force a fresh object.
 *
 * Two integrity modes, chosen by whether the caller knows the expected md5:
 *   - No md5 (the manifest): the byte-size headers are the only integrity
 *     signal, so the request asks for `identity` — WordPress otherwise accepts
 *     gzip and hands back a decoded body while `content-length` / `x-body-size`
 *     still describe the compressed bytes, which no byte count can match.
 *   - With md5 (the dataset): the checksum is a stronger check than any byte
 *     count, so compression stays enabled — and the size comparison still runs
 *     whenever the body arrives unencoded.
 *
 * The size comparison is also skipped for any response that arrived
 * content-encoded, so an edge that ignores `identity` degrades to "cannot
 * verify" instead of failing every refresh.
 *
 * @since 4.1.0
 */
class MirrorDownloader {

	const MAX_ATTEMPTS = 3;

	/**
	 * Wall-clock cap for a whole download sequence. wp-cron often runs on a
	 * visitor's page load, so a broken mirror must not hold the page.
	 */
	const TOTAL_BUDGET_SECONDS = 10;

	/**
	 * Longest a single attempt may take. Passed to the client per request and
	 * shortened to whatever is left of the budget, so production wiring hands
	 * this same constant to {@see WpHttpClient} as its ceiling.
	 */
	const REQUEST_TIMEOUT_SECONDS = 5;

	/**
	 * Shortest attempt worth starting. Below this there is no point waiting out
	 * a backoff only to give the mirror no time to answer.
	 */
	const MIN_ATTEMPT_SECONDS = 1;

	/**
	 * First retry delay in seconds; each further retry doubles it.
	 */
	const BASE_DELAY_SECONDS = 1;

	/**
	 * Query parameter appended to bust an edge-cached corrupt object.
	 */
	const CACHE_BUSTER_ARG = '_imunify_cb';

	/**
	 * Statuses that describe a permanent condition — retrying cannot help.
	 */
	const PERMANENT_STATUSES = array( 400, 403, 404, 410 );

	/**
	 * Injected HTTP client used to perform each attempt.
	 *
	 * @var HttpClient
	 */
	private $http;

	/**
	 * Build a downloader bound to an HTTP client.
	 *
	 * @param HttpClient $http HTTP client (WpHttpClient in production, fake in tests).
	 */
	public function __construct( $http ) {
		$this->http = $http;
	}

	/**
	 * Fetch $url, retrying a corrupt or transient response, and return the
	 * verified body or the details of why it could not be verified.
	 *
	 * @param string $url          Absolute URL.
	 * @param string $expected_md5 md5 the mirror published for this object; '' when unknown.
	 * @return MirrorDownloadResult
	 */
	public function download( $url, $expected_md5 = '' ) {
		$deadline = $this->now() + self::TOTAL_BUDGET_SECONDS;
		$attempt  = 0;
		$context  = array();
		$reason   = MirrorDownloadResult::REASON_TRANSPORT;
		$bust     = false;

		while ( $attempt < self::MAX_ATTEMPTS ) {
			++$attempt;
			$response = $this->http->get(
				$bust && self::MAX_ATTEMPTS === $attempt ? $this->bustedUrl( $url ) : $url,
				$this->requestHeaders( $attempt, $expected_md5 ),
				(int) floor( min( self::REQUEST_TIMEOUT_SECONDS, $deadline - $this->now() ) )
			);
			$reason   = $this->verify( $response, $expected_md5 );
			$context  = $this->describe( $url, $attempt, $response, $reason );

			if ( '' === $reason ) {
				return MirrorDownloadResult::success( $response->body(), $attempt, $context );
			}
			if ( ! $this->isRetryable( $reason, $response->status() ) ) {
				break;
			}
			// Only an explicit verdict moves the flag: a response that says
			// nothing about the cache — a transport error above all — is no
			// evidence that the edge stopped serving the corrupt object.
			$x_cache = $response->header( 'x-cache' );
			if ( '' !== $x_cache ) {
				$bust = false !== stripos( $x_cache, 'hit' );
			}

			if ( self::MAX_ATTEMPTS === $attempt ) {
				break;
			}
			$delay = $this->delay( $attempt, $response );
			// Each attempt is capped by whatever is left of the budget, so the
			// next one only needs room for the wait plus a second of request —
			// which is what lets a timed-out attempt be retried at all.
			if ( $delay + self::MIN_ATTEMPT_SECONDS > $deadline - $this->now() ) {
				break;
			}
			$this->pause( $delay );
		}

		return MirrorDownloadResult::failure( $reason, $attempt, $context );
	}

	/**
	 * Request headers for an attempt.
	 *
	 * @param int    $attempt      1-based attempt number.
	 * @param string $expected_md5 md5 the mirror published for this object; '' when unknown.
	 * @return array<string,string>
	 */
	private function requestHeaders( $attempt, $expected_md5 ) {
		$headers = array();
		if ( '' === $expected_md5 ) {
			$headers['Accept-Encoding'] = 'identity';
		}
		if ( $attempt > 1 ) {
			$headers['Cache-Control'] = 'no-cache';
			$headers['Pragma']        = 'no-cache';
		}
		return $headers;
	}

	/**
	 * Decide whether a response can be handed to the caller.
	 *
	 * @param HttpResponse $response     Response to check.
	 * @param string       $expected_md5 md5 the mirror published for this object; '' when unknown.
	 * @return string REASON_* slug, or '' when the response is intact.
	 */
	private function verify( $response, $expected_md5 ) {
		if ( $response->isTransportError() ) {
			return MirrorDownloadResult::REASON_TRANSPORT;
		}
		// Exactly 200: a mirror object is fetched whole, so every other 2xx —
		// 206 above all — describes something that is not the complete object.
		if ( 200 !== $response->status() ) {
			return MirrorDownloadResult::REASON_HTTP_STATUS;
		}
		// A range response can carry sizes that agree with the fragment it
		// returned, so the size check alone would accept it.
		if ( '' !== $response->header( 'content-range' ) ) {
			return MirrorDownloadResult::REASON_PARTIAL_CONTENT;
		}
		if ( 0 === $response->bodySize() ) {
			return MirrorDownloadResult::REASON_EMPTY_BODY;
		}
		if ( $this->hasWrongSize( $response ) ) {
			return MirrorDownloadResult::REASON_SIZE_MISMATCH;
		}
		if ( '' !== $expected_md5 ) {
			// nosemgrep: php.lang.security.weak-crypto.weak-crypto -- integrity check against server-published checksum, not cryptography.
			return md5( $response->body() ) === $expected_md5
				? ''
				: MirrorDownloadResult::REASON_MD5_MISMATCH;
		}
		return '';
	}

	/**
	 * Whether the body is shorter or longer than the mirror declared. False
	 * when neither size header arrived, and for a content-encoded body whose
	 * declared sizes describe the on-wire bytes rather than what the caller
	 * sees — both are "cannot verify", not "wrong".
	 *
	 * @param HttpResponse $response Response to check.
	 * @return bool
	 */
	private function hasWrongSize( $response ) {
		if ( $this->isEncoded( $response ) ) {
			return false;
		}
		foreach ( array( 'x-body-size', 'content-length' ) as $header ) {
			$declared = $response->intHeader( $header );
			if ( null !== $declared && $declared !== $response->bodySize() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether the body arrived transformed, making the declared byte sizes
	 * describe something other than what the caller sees.
	 *
	 * @param HttpResponse $response Response to check.
	 * @return bool
	 */
	private function isEncoded( $response ) {
		$encoding = strtolower( trim( $response->header( 'content-encoding' ) ) );
		return '' !== $encoding && 'identity' !== $encoding;
	}

	/**
	 * Whether another attempt could plausibly succeed.
	 *
	 * @param string $reason REASON_* slug of the failure.
	 * @param int    $status HTTP status of the failed attempt.
	 * @return bool
	 */
	private function isRetryable( $reason, $status ) {
		if ( MirrorDownloadResult::REASON_HTTP_STATUS !== $reason ) {
			return true;
		}
		if ( in_array( $status, self::PERMANENT_STATUSES, true ) ) {
			return false;
		}
		// A 2xx that was refused (206 and friends) is an intermediary quirk
		// rather than a permanent answer, so it is worth one more ask.
		return 429 === $status || $status >= 500 || ( $status >= 200 && $status < 300 );
	}

	/**
	 * Delay before the next attempt: exponential backoff with ±25% jitter,
	 * or the server's `Retry-After` when it asked to be left alone.
	 *
	 * @param int          $attempt  1-based number of the attempt that just failed.
	 * @param HttpResponse $response Response of that attempt.
	 * @return float Seconds.
	 */
	private function delay( $attempt, $response ) {
		if ( 429 === $response->status() || 503 === $response->status() ) {
			$retry_after = $response->intHeader( 'retry-after' );
			if ( null !== $retry_after ) {
				return (float) $retry_after;
			}
		}
		return self::BASE_DELAY_SECONDS * pow( 2, $attempt - 1 ) * $this->jitterFactor();
	}

	/**
	 * Same URL with a throwaway query parameter, so an edge cache holding a
	 * corrupt object has to fetch a fresh one.
	 *
	 * @param string $url Absolute URL.
	 * @return string
	 */
	private function bustedUrl( $url ) {
		return add_query_arg( self::CACHE_BUSTER_ARG, $this->cacheBusterToken(), $url );
	}

	/**
	 * Reportable details of an attempt. Absent headers are reported as
	 * `missing` rather than dropped — which header the edge omitted is part of
	 * the diagnosis.
	 *
	 * @param string       $url      URL as requested by the caller.
	 * @param int          $attempt  1-based attempt number.
	 * @param HttpResponse $response Response of that attempt.
	 * @param string       $reason   REASON_* slug; '' when intact.
	 * @return array<string,mixed>
	 */
	private function describe( $url, $attempt, $response, $reason ) {
		$x_body_size    = $response->intHeader( 'x-body-size' );
		$content_length = $response->intHeader( 'content-length' );
		$req_id         = $response->header( 'x-req-id' );

		return array(
			'url'              => $url,
			'attempts'         => $attempt,
			'status'           => $response->status(),
			'x_body_size'      => null === $x_body_size ? 'missing' : $x_body_size,
			'content_length'   => null === $content_length ? 'missing' : $content_length,
			'actual_body_size' => $response->bodySize(),
			'x_req_id'         => '' === $req_id ? 'missing' : $req_id,
			'x_cache'          => $response->header( 'x-cache' ),
			'content_encoding' => $response->header( 'content-encoding' ),
			'failure_reason'   => $reason,
			'last_error'       => $response->errorMessage(),
		);
	}

	/**
	 * Current timestamp in seconds. Overridable so a test can drive the
	 * wall-clock budget without waiting on a real clock.
	 *
	 * @return float
	 */
	protected function now() {
		return (float) microtime( true );
	}

	/**
	 * Block for $seconds. Overridable so a test can observe the backoff instead
	 * of sleeping through it.
	 *
	 * @param float $seconds Seconds to wait.
	 */
	protected function pause( $seconds ) {
		usleep( (int) round( $seconds * 1000000 ) );
	}

	/**
	 * Multiplier spreading a backoff delay ±25%, so sites cut off by the same
	 * mirror hiccup do not all retry in lockstep. Overridable to make the delay
	 * deterministic in tests.
	 *
	 * @return float Between 0.75 and 1.25.
	 */
	protected function jitterFactor() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- retry jitter, not security; wp_rand() is pluggable and may be unavailable this early.
		return mt_rand( 750, 1250 ) / 1000;
	}

	/**
	 * Throwaway value that makes a cache-busting URL unique. Overridable to keep
	 * the busted URL predictable in tests.
	 *
	 * @return string
	 */
	protected function cacheBusterToken() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- cache-buster token, not security.
		return (string) mt_rand( 100000, 999999 );
	}
}
