<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Persists the most recent loopback (self-reachability) probe result in a
 * WordPress option so the dashboard widget can surface a warning.
 *
 * Stored shape: array( state, reason, checked_at, dismissed ). The widget
 * reads the derived `warning` flag (a non-OK, non-dismissed state). The
 * option is deleted on plugin uninstall by {@see BotLifecycle}.
 *
 * @since 4.1.0
 */
class LoopbackStatus {

	/**
	 * Non-autoloaded option holding the last probe result.
	 */
	const OPTION_NAME = 'imunify_bots_loopback_status';

	/**
	 * Persist a fresh probe result.
	 *
	 * A new result clears any prior dismissal — an activation or an explicit
	 * re-check is fresh news the admin should see.
	 *
	 * @param LoopbackResult $result Probe outcome.
	 * @return void
	 */
	public function record( LoopbackResult $result ) {
		update_option(
			self::OPTION_NAME,
			array(
				'state'      => $result->getState(),
				'reason'     => $result->getReason(),
				'checked_at' => time(),
				'dismissed'  => false,
			),
			false
		);
	}

	/**
	 * Mark the current warning acknowledged so the widget stops nagging,
	 * preserving the underlying state and timestamp for diagnostics.
	 *
	 * @return void
	 */
	public function dismiss() {
		$current = $this->read();
		update_option(
			self::OPTION_NAME,
			array(
				'state'      => $current['state'],
				'reason'     => $current['reason'],
				'checked_at' => $current['checked_at'],
				'dismissed'  => true,
			),
			false
		);
	}

	/**
	 * Normalized view-model for the widget.
	 *
	 * @return array{state:string,reason:string,checked_at:int,dismissed:bool,warning:bool}
	 */
	public function read() {
		$raw = get_option( self::OPTION_NAME );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$state      = isset( $raw['state'] ) ? (string) $raw['state'] : '';
		$reason     = isset( $raw['reason'] ) ? (string) $raw['reason'] : '';
		$checked_at = isset( $raw['checked_at'] ) ? (int) $raw['checked_at'] : 0;
		$dismissed  = ! empty( $raw['dismissed'] );
		$warning    = ! $dismissed
			&& ( LoopbackResult::STATE_FAILED === $state || LoopbackResult::STATE_INCONCLUSIVE === $state );

		return array(
			'state'      => $state,
			'reason'     => $reason,
			'checked_at' => $checked_at,
			'dismissed'  => $dismissed,
			'warning'    => $warning,
		);
	}
}
