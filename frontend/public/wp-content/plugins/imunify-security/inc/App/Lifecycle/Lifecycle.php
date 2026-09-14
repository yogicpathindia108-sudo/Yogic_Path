<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Lifecycle;

use CloudLinux\Imunify\App\Bot\BotLifecycle;
use CloudLinux\Imunify\App\Defender\DefenderLifecycle;

/**
 * Fans plugin activation, deactivation, and uninstall out to the per-domain
 * handlers. Activation / deactivation are owned by the bot feature (cron and
 * the mu-plugin shim); uninstall additionally clears the WAF and generic data
 * each domain owns.
 */
class Lifecycle {

	/**
	 * Activate the plugin.
	 *
	 * @return void
	 */
	public static function activate() {
		BotLifecycle::activate();
	}

	/**
	 * Deactivate the plugin.
	 *
	 * @return void
	 */
	public static function deactivate() {
		BotLifecycle::deactivate();
	}

	/**
	 * Uninstall the plugin.
	 *
	 * @return void
	 */
	public static function uninstall() {
		BotLifecycle::uninstall();
		DefenderLifecycle::uninstall();
		CoreLifecycle::uninstall();
	}
}
