<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Defender;

use CloudLinux\Imunify\App\Helpers\TransientCleaner;

/**
 * Removes the WAF / security-rule transients this feature caches: the rule
 * set, the disabled-rule list, per-rule hit counters, incident rate-limit
 * counters, and probe-firing throttles. Each prefix is owned by the class
 * that writes it.
 */
class DefenderLifecycle {

	/**
	 * Remove WAF transients on uninstall.
	 *
	 * @return void
	 */
	public static function uninstall() {
		TransientCleaner::deleteByPrefix( RuleProvider::RULES_TRANSIENT );
		TransientCleaner::deleteByPrefix( DisabledRulesManager::TRANSIENT_KEY );
		TransientCleaner::deleteByPrefix( RuleHitTracker::TRANSIENT_PREFIX );
		TransientCleaner::deleteByPrefix( RateLimiter::TRANSIENT_PREFIX );
		TransientCleaner::deleteByPrefix( ConditionEvaluator::PROBE_TRANSIENT_PREFIX );
	}
}
