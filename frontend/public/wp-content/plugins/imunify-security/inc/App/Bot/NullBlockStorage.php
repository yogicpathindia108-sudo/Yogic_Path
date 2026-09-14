<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * No-op block storage used when the database is unavailable.
 *
 * @since 4.0.0
 */
class NullBlockStorage implements BlockStorageInterface {

	/** {@inheritdoc} */
	public function isBlocked( $ip_key, $site_id ) {
		return false;
	}

	/** {@inheritdoc} */
	public function writeBlock( $ip_key, $site_id, $category, $preset, $ttl_seconds ) {
	}

	/** {@inheritdoc} */
	public function incrementViolation( $key, $ttl_seconds ) {
		return 0;
	}

	/** {@inheritdoc} */
	public function getViolationCount( $key ) {
		return 0;
	}

	/** {@inheritdoc} */
	public function cleanup() {
	}

	/** {@inheritdoc} */
	public function name() {
		return 'null';
	}
}
