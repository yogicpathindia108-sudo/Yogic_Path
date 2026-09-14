<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Shared site-scope identifier used to key per-site storage buckets.
 *
 * RateLimiter (pipeline writes) and DailyCounter (widget reads) must
 * agree on this identifier to the byte — if either diverges, the
 * widget's "blocked today" counter permanently reads from a different
 * keyspace than the pipeline writes to. Centralising the derivation
 * here eliminates that risk at the cost of one class file.
 *
 * The identifier is a non-cryptographic fingerprint of ABSPATH, so
 * multiple WordPress installs sharing a Redis/APCu instance don't
 * collide in their rate-limit buckets. It is never reversed or used
 * for authentication.
 *
 * @since 4.0.0
 */
class SiteScope {

	/**
	 * Derive the 16-hex-char site identifier.
	 *
	 * Falls back to a constant literal when ABSPATH is undefined
	 * (CLI / pre-bootstrap contexts); those paths don't race real
	 * request traffic, so sharing a bucket among them is fine.
	 *
	 * @return string
	 */
	public static function derive() {
		$basis = defined( 'ABSPATH' ) ? (string) ABSPATH : 'no-abspath';
		// nosemgrep: php.lang.security.weak-crypto.weak-crypto -- non-cryptographic fingerprint of ABSPATH.
		return substr( md5( $basis ), 0, 16 );
	}
}
