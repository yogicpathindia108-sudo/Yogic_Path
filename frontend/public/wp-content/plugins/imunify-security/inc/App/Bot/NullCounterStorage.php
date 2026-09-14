<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * No-op counter storage used when MEMORY engine is unavailable.
 *
 * All methods return safe defaults. The rate limiter becomes
 * "classify + log, block malicious only" mode.
 *
 * @since 4.0.0
 */
class NullCounterStorage implements CounterStorageInterface {

	/** {@inheritdoc} */
	public function increment( $key, $ttl_seconds ) {
		return 0;
	}

	/** {@inheritdoc} */
	public function get( $key ) {
		return 0;
	}

	/** {@inheritdoc} */
	public function set( $key, $value, $ttl_seconds ) {
	}

	/** {@inheritdoc} */
	public function reset( $key ) {
	}

	/** {@inheritdoc} */
	public function cleanup() {
	}

	/** {@inheritdoc} */
	public function name() {
		return 'null';
	}
}
