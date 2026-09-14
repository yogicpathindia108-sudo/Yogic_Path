<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * AllowlistMatcher over the bundled monitoring-UA list.
 *
 * Delegates to MonitoringUserAgents so the list itself is a single
 * source of truth shared between this matcher and any future dashboard
 * rendering.
 *
 * @since 4.0.0
 */
class MonitoringUaMatcher implements AllowlistMatcher {

	/**
	 * {@inheritdoc}
	 */
	public function matches( $context ) {
		$ua = isset( $context['ua'] ) ? $context['ua'] : '';
		return MonitoringUserAgents::matches( $ua );
	}
}
