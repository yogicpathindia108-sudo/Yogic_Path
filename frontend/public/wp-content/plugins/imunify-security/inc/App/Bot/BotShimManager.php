<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

use CloudLinux\Imunify\App\Model\PluginConfig;

/**
 * Installs the bot-protection mu-plugin shim and records a self-reachability
 * probe result, surfacing a warning when the site cannot reach itself.
 *
 * Warn-only: the probe never decides whether the shim stays installed. The
 * shim itself fails open (it wraps the pipeline in a Throwable/Exception
 * catch), so a bug in bot-protection code cannot break the site, and
 * MuPluginSelfHealer reinstalls the shim idempotently on every request — a
 * rollback would be both destructive and pointless. A failed probe instead
 * means scheduled (wp-cron) tasks may not run, which the dashboard widget
 * surfaces from the recorded {@see LoopbackStatus}.
 *
 * Collaborators are constructor-injected so the install/record flow is unit
 * testable; the WordPress-facing wiring lives in BotLifecycle.
 *
 * @since 4.0.0
 */
class BotShimManager {

	/**
	 * Shim file installer.
	 *
	 * @var MuPluginInstaller
	 */
	private $installer;

	/**
	 * Post-install self-reachability probe.
	 *
	 * @var LoopbackSafetyTest
	 */
	private $loopback;

	/**
	 * Persistence for the probe result read by the dashboard widget.
	 *
	 * @var LoopbackStatus
	 */
	private $status;

	/**
	 * Construct with real collaborators.
	 *
	 * @param MuPluginInstaller  $installer Shim file installer.
	 * @param LoopbackSafetyTest $loopback  Post-install self-reachability probe.
	 * @param LoopbackStatus     $status    Probe-result persistence.
	 */
	public function __construct( $installer, $loopback, $status ) {
		$this->installer = $installer;
		$this->loopback  = $loopback;
		$this->status    = $status;
	}

	/**
	 * Install the shim, then probe self-reachability and record the result.
	 *
	 * The probe is informational only — a non-OK result is recorded for the
	 * dashboard warning but never removes the shim (see the class docblock).
	 * Does nothing when the server-level ai_bot_protection gate is off.
	 *
	 * @param string       $home_url Site home URL for the loopback probe.
	 * @param PluginConfig $cfg      Decoded plugin_config.php.
	 * @return bool True when the gate is off (nothing to do), or when the
	 *              shim was written; false when the shim write failed.
	 */
	public function install( $home_url, PluginConfig $cfg ) {
		if ( ! $cfg->isAiBotProtectionEnabled() ) {
			return true;
		}
		if ( ! $this->installer->install() ) {
			return false;
		}
		$this->status->record( $this->loopback->run( $home_url ) );
		return true;
	}

	/**
	 * Remove the shim.
	 *
	 * @return bool
	 */
	public function remove() {
		return $this->installer->uninstall();
	}
}
