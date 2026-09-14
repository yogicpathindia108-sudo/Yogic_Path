<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Durable block list and violation counter storage.
 *
 * Blocks are dual-written to InnoDB (durable) and a MEMORY engine
 * table (fast per-request lookup). Violation counters live in InnoDB
 * to survive MariaDB restarts. Implementations must be fail-open.
 *
 * @since 4.0.0
 */
interface BlockStorageInterface {

	/**
	 * Check whether an IP is currently blocked for a given site.
	 *
	 * @param string $ip_key  Normalised IP key.
	 * @param string $site_id 16-char site scope identifier.
	 * @return bool True if an active (non-expired) block exists.
	 */
	public function isBlocked( $ip_key, $site_id );

	/**
	 * Write a block record (dual-write to durable + active tables).
	 *
	 * @param string $ip_key     Normalised IP key.
	 * @param string $site_id    16-char site scope identifier.
	 * @param string $category   Bot category constant.
	 * @param string $preset     Active preset identifier.
	 * @param int    $ttl_seconds Block duration in seconds.
	 * @return void
	 */
	public function writeBlock( $ip_key, $site_id, $category, $preset, $ttl_seconds );

	/**
	 * Atomically increment a violation counter, creating it if missing.
	 *
	 * @param string $key         Violation counter key.
	 * @param int    $ttl_seconds TTL applied on initial create.
	 * @return int New counter value, or 0 on failure (fail-open).
	 */
	public function incrementViolation( $key, $ttl_seconds );

	/**
	 * Read a violation counter.
	 *
	 * @param string $key Violation counter key.
	 * @return int Current value, or 0 when missing.
	 */
	public function getViolationCount( $key );

	/**
	 * Remove expired rows from all tables and reclaim MEMORY engine space.
	 *
	 * @return void
	 */
	public function cleanup();

	/**
	 * Short identifier for telemetry ("db", "null").
	 *
	 * @return string
	 */
	public function name();
}
