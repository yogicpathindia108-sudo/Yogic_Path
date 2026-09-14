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
 * Self-healing check that keeps the mu-plugin shim in sync with the
 * ai_bot_protection feature gate.
 *
 * Sites that upgrade from a pre-4.0 plugin version to 4.0+ receive the
 * new code but never trigger register_activation_hook, so the mu-plugin
 * is never written. This class runs on every request via plugins_loaded,
 * installing the mu-plugin when the gate is on but the file is missing,
 * and removing it when the gate is off but the file is present (e.g.
 * after the gate flips from on to off).
 *
 * When it installs the shim, it also records a self-reachability probe:
 * activation is the only other place that probes, and it does not run on
 * gate-flipped-on / upgraded sites, so without this the dashboard loopback
 * warning would never appear for them. The probe is deferred to shutdown so
 * its HTTP round-trip never blocks the current (possibly front-end) request.
 *
 * @since 4.0.1
 */
class MuPluginSelfHealer {

	/**
	 * Autoloaded option that stores the timestamp of the last check.
	 */
	const OPTION_NAME = 'imunify_bots_mu_checked';

	/**
	 * Default seconds between filesystem checks. Overridable via the
	 * IMUNIFY_BOTS_MU_TTL constant (e.g. 0 to force a re-check every request).
	 */
	const TTL_SECONDS = 3600;

	/**
	 * Production entry point — wired to plugins_loaded.
	 *
	 * Reads the autoloaded TTL option (zero overhead — already in
	 * memory from wp_load_alloptions). When the TTL is fresh, returns
	 * immediately. Otherwise delegates to checkWith() and refreshes
	 * the timestamp.
	 *
	 * @since 4.0.1
	 * @return void
	 */
	public static function check() {
		// Skip under WP-CLI to avoid conflicts with CLI executions.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		$last_check = get_option( self::OPTION_NAME );
		$now        = time();
		$ttl        = defined( 'IMUNIFY_BOTS_MU_TTL' ) ? (int) constant( 'IMUNIFY_BOTS_MU_TTL' ) : self::TTL_SECONDS;
		if ( false !== $last_check && ( $now - (int) $last_check ) < $ttl ) {
			return;
		}

		$mu_dir = defined( 'WPMU_PLUGIN_DIR' ) && '' !== WPMU_PLUGIN_DIR
			? (string) WPMU_PLUGIN_DIR
			: '';
		if ( '' === $mu_dir ) {
			return;
		}
		$wp_content_dir = defined( 'WP_CONTENT_DIR' ) ? (string) WP_CONTENT_DIR : '';
		if ( '' === $wp_content_dir ) {
			return;
		}

		$cfg           = MuLoader::loadPluginConfig( $wp_content_dir );
		$installed_now = self::checkWith( new MuPluginInstaller( $mu_dir ), $cfg );
		// Refresh TTL even when install()/uninstall() fails to avoid hammering a broken filesystem every request.
		update_option( self::OPTION_NAME, $now, true );

		if ( $installed_now ) {
			self::scheduleLoopbackProbe();
		}
	}

	/**
	 * Deterministic entry point for unit tests. Reconciles installed state
	 * with the feature gate in both directions.
	 *
	 * @since 4.0.1
	 * @param MuPluginInstaller $installer Installer collaborator.
	 * @param PluginConfig      $cfg       Decoded plugin_config.php.
	 * @return bool True iff a shim was just installed (gate on, file was
	 *              missing, write succeeded) — the signal to probe self-
	 *              reachability, since activation did not for this site.
	 */
	public static function checkWith( $installer, PluginConfig $cfg ) {
		if ( $cfg->isIndeterminate() ) {
			// Couldn't confirm the gate's real state this cycle (missing /
			// unreadable / unparseable plugin_config.php) — leave the shim
			// as-is rather than guessing. Acting on this the same as a
			// confirmed "off" would tear down an already-working shim on
			// nothing more than a transient read glitch; the next TTL-gated
			// check retries once the file is readable again.
			return false;
		}
		$installed = $installer->isInstalled();
		$enabled   = $cfg->isAiBotProtectionEnabled();

		if ( $installed && ! $enabled ) {
			$installer->uninstall();
			return false;
		}
		if ( ! $installed && $enabled ) {
			return (bool) $installer->install();
		}
		return false;
	}

	/**
	 * Register the self-reachability probe on shutdown.
	 *
	 * Hooked on shutdown rather than run inline so the shim heal — which can
	 * land on any request, including an anonymous front-end one — never blocks
	 * the response on the probe's HTTP timeout. Bounded by the same TTL and
	 * install-transition guard as the heal itself, so it fires at most once per
	 * check window and only when a shim was actually written.
	 *
	 * @since 4.1.0
	 * @return void
	 */
	public static function scheduleLoopbackProbe() {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'shutdown', array( __CLASS__, 'runLoopbackProbe' ) );
	}

	/**
	 * Shutdown callback: probe self-reachability and persist the result so the
	 * dashboard warning reflects a shim installed outside activation.
	 *
	 * Unauthenticated, mirroring activation — this may run on an anonymous
	 * request, and forwarding a visitor's credentials would be wrong.
	 *
	 * @since 4.1.0
	 * @return void
	 */
	public static function runLoopbackProbe() {
		$home_url = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
		self::recordProbe( new LoopbackSafetyTest(), new LoopbackStatus(), $home_url );
	}

	/**
	 * Run the probe and record its result. Collaborators are injected so the
	 * record step is unit-testable without the WordPress HTTP stack.
	 *
	 * @since 4.1.0
	 * @param LoopbackSafetyTest $probe    Self-reachability probe.
	 * @param LoopbackStatus     $status   Result persistence.
	 * @param string             $home_url Site home URL to probe.
	 * @return void
	 */
	public static function recordProbe( LoopbackSafetyTest $probe, LoopbackStatus $status, $home_url ) {
		$status->record( $probe->run( $home_url ) );
	}
}
