<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Deferred Sentry event buffer for the bot-protection storage layer.
 *
 * The bot pipeline runs at muplugins_loaded — before the main plugin
 * boots and registers its Debug error handler. This class buffers
 * storage events (table full, null fallback) during the mu-plugin
 * phase and replays them at plugins_loaded (priority 99), when
 * Debug is guaranteed to be listening on 'imunify_security_set_error'.
 * Events recorded once plugins_loaded has fired — the daily export cron,
 * for one — are reported immediately, since that hook will not fire again.
 *
 * Throttling: each event type is deduplicated within a request (by
 * error code) and across requests (via WP transients, once per hour).
 *
 * @since 4.0.0
 */
class StorageEventBuffer {

	/**
	 * Buffered events keyed by error code (deduplicates within a request).
	 *
	 * @var array
	 */
	private static $events = array();

	/**
	 * Whether the plugins_loaded flush hook has been registered.
	 *
	 * @var bool
	 */
	private static $flush_hooked = false;

	/**
	 * Record a storage event for deferred reporting.
	 *
	 * @param string $message     Human-readable message for Sentry.
	 * @param string $error_code  Throttle key (one report per hour per code per site).
	 * @param array  $fingerprint Sentry fingerprint array for cross-site grouping.
	 * @param array  $context     Extra context data.
	 * @return void
	 */
	public static function record( $message, $error_code, $fingerprint, $context = array() ) {
		if ( isset( self::$events[ $error_code ] ) ) {
			return;
		}
		self::$events[ $error_code ] = array(
			'message'     => $message,
			'error_code'  => $error_code,
			'fingerprint' => $fingerprint,
			'context'     => $context,
		);
		// Callers that run after plugins_loaded — WP-Cron jobs, for one — would
		// never see the deferred hook fire, and Debug is registered by then
		// anyway, so report straight away instead of buffering forever.
		if ( function_exists( 'did_action' ) && did_action( 'plugins_loaded' ) ) {
			self::flush();
			return;
		}

		if ( ! self::$flush_hooked && function_exists( 'add_action' ) ) {
			// The pipeline runs from the mu-plugin (muplugins_loaded),
			// so plugins_loaded has not fired yet during normal requests.
			add_action( 'plugins_loaded', array( __CLASS__, 'flush' ), 99 );
			self::$flush_hooked = true;
		}
	}

	/**
	 * Flush buffered events through the Debug error handler.
	 *
	 * Fires do_action('imunify_security_set_error') for each event
	 * that has not been reported in the last hour (WP transient gate).
	 *
	 * @return void
	 */
	public static function flush() {
		foreach ( self::$events as $event ) {
			$transient_key = 'imunify_security_error_' . $event['error_code'];

			if ( function_exists( 'get_transient' ) && get_transient( $transient_key ) ) {
				continue;
			}

			if ( function_exists( 'set_transient' ) ) {
				set_transient( $transient_key, true, 3600 );
			}

			if ( function_exists( 'do_action' ) ) {
				do_action(
					'imunify_security_set_error',
					E_WARNING,
					$event['message'],
					__FILE__,
					__LINE__,
					array(
						'fingerprint' => $event['fingerprint'],
						'context'     => $event['context'],
					)
				);
			}
		}
		self::$events = array();
	}

	/**
	 * Return the current buffer contents (for testing).
	 *
	 * @return array
	 */
	public static function getBuffer() {
		return self::$events;
	}

	/**
	 * Reset buffer state (for testing).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$events       = array();
		self::$flush_hooked = false;
	}
}
