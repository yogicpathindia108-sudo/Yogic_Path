<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Model;

/**
 * Shared helpers for reasoning about the Imunify license edition
 * (the license_type string the agent surfaces).
 */
class Edition {

	/**
	 * Whether an edition string names the ImunifyAV family (ImunifyAV or
	 * ImunifyAV+), for which AI bot management and CVE/WAF protection run in
	 * monitor-only mode. Matching is anchored to the whole string and is
	 * case-insensitive; a longer edition that merely starts with "imunifyav",
	 * a non-string, or an empty value returns false, so an Imunify360 edition
	 * is never treated as AV.
	 *
	 * Single source of truth for the AV-family rule: callers that read the
	 * edition from different files (plugin_config.php on the bot hot path,
	 * scan_data.php on the admin path) delegate here so the two can never
	 * disagree.
	 *
	 * @param mixed $licenseType Edition value surfaced by the agent.
	 * @return bool
	 */
	public static function isImunifyAvFamily( $licenseType ) {
		if ( ! is_string( $licenseType ) || '' === $licenseType ) {
			return false;
		}
		return 1 === preg_match( '/^imunifyav(\+|plus)?$/i', $licenseType );
	}
}
