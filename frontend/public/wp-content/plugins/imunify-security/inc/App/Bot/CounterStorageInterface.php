<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Ephemeral counter storage for high-frequency rate-limit accounting.
 *
 * Backed by a MEMORY engine table. Lost on MariaDB restart — acceptable
 * given 60-second rate-limit windows. Implementations must be fail-open:
 * any error returns a safe default (0) rather than throwing.
 *
 * @since 4.0.0
 */
interface CounterStorageInterface {

	/**
	 * Atomically increment a counter, creating it if missing.
	 *
	 * @param string $key         Counter key.
	 * @param int    $ttl_seconds TTL applied on initial create.
	 * @return int New counter value, or 0 on failure (fail-open).
	 */
	public function increment( $key, $ttl_seconds );

	/**
	 * Read a counter value.
	 *
	 * @param string $key Counter key.
	 * @return int Current value, or 0 when missing.
	 */
	public function get( $key );

	/**
	 * Store a value with a TTL.
	 *
	 * @param string $key         Key.
	 * @param int    $value       Integer value.
	 * @param int    $ttl_seconds TTL in seconds.
	 * @return void
	 */
	public function set( $key, $value, $ttl_seconds );

	/**
	 * Remove a key.
	 *
	 * @param string $key Key.
	 * @return void
	 */
	public function reset( $key );

	/**
	 * Remove expired rows and reclaim MEMORY engine space.
	 *
	 * @return void
	 */
	public function cleanup();

	/**
	 * Short identifier for telemetry ("memory", "null").
	 *
	 * @return string
	 */
	public function name();
}
