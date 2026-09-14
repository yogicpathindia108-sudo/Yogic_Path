<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Bundled UA-substring list for commercial uptime-monitoring services.
 *
 * These services MUST NOT be rate-limited — a hosting provider that
 * relies on UptimeRobot to page oncall would raise an incident the
 * first time we throttle one of its probes. The list is deliberately
 * narrow (established, stable service tokens) so that a new vendor
 * does not accidentally get a free pass; site-owner-managed
 * allowlisting is planned for a future release.
 *
 * @since 4.0.0
 */
class MonitoringUserAgents {

	/**
	 * Return every bundled UA-substring token.
	 *
	 * @return array
	 */
	public static function tokens() {
		return array(
			'UptimeRobot',
			'Pingdom',
			'StatusCake',
			'Site24x7',
			'NewRelicPinger',
			'HetrixTools',
			'updown.io',
		);
	}

	/**
	 * Case-insensitive substring match against the bundled tokens.
	 *
	 * @param mixed $user_agent Request User-Agent, typed loosely on purpose.
	 * @return bool
	 */
	public static function matches( $user_agent ) {
		if ( ! is_string( $user_agent ) || '' === $user_agent ) {
			return false;
		}
		foreach ( self::tokens() as $token ) {
			if ( false !== stripos( $user_agent, $token ) ) {
				return true;
			}
		}
		return false;
	}
}
