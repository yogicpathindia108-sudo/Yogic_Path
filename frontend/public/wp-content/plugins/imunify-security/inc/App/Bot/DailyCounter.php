<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Rolling 24-hour blocked-request counter backed by the active
 * {@see CounterStorageInterface} (Memory / Null).
 *
 * Phase-1 surface the dashboard widget reads to show "N requests
 * blocked today". Deliberately tiny: one key, one TTL, no rotation —
 * per-event logging and charts are deferred to a future phase.
 *
 * Key layout is namespaced per install via the same 16-char ABSPATH
 * hash {@see RateLimiter} uses, so multiple WordPress installs sharing
 * an APCu or Redis keyspace don't cross-pollute.
 *
 * @since 4.0.0
 */
class DailyCounter {

	/**
	 * Rolling window for the counter. 86400s = 24h.
	 */
	const WINDOW_SECONDS = 86400;

	/**
	 * Key prefix. See the site_id derivation below.
	 */
	const KEY_PREFIX = 'bot:daily_blocked:';

	/**
	 * Backing counter store (Memory / Null).
	 *
	 * @var CounterStorageInterface
	 */
	private $storage;

	/**
	 * Fully-qualified storage key including the site-id suffix.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Construct a counter bound to the given storage and site id.
	 *
	 * @param CounterStorageInterface $storage Backing counter store.
	 * @param string|null             $site_id Optional explicit site prefix.
	 *                                  Production leaves this null — the
	 *                                  constructor derives a 16-char hex
	 *                                  hash from ABSPATH. Tests pass
	 *                                  explicit values to verify key
	 *                                  isolation.
	 */
	public function __construct( $storage, $site_id = null ) {
		$this->storage = $storage;
		$this->key     = self::KEY_PREFIX . ( null !== $site_id ? $site_id : SiteScope::derive() );
	}

	/**
	 * Increment the counter. Only hard ACTION_BLOCK decisions count —
	 * rate-limited and allowed requests are not "blocked".
	 *
	 * This is the always-on source for the widget's "%d blocked in 24h"
	 * line, so it must count the same thing as the block-only "Blocked"
	 * figures in the bot pane's verdict tiles and on the Bot Traffic
	 * screen (both driven by ACTION_BLOCK). Counting rate-limits here too
	 * would inflate the widget line into a block + rate-limit sum that
	 * disagrees with every other surface.
	 *
	 * @param RateLimitDecision $decision Decision returned by RateLimiter::check().
	 * @return void
	 */
	public function recordDecision( $decision ) {
		if ( RateLimitDecision::ACTION_BLOCK !== $decision->getAction() ) {
			return;
		}
		$this->storage->increment( $this->key, self::WINDOW_SECONDS );
	}

	/**
	 * Current blocked (403) count over the 24h window.
	 *
	 * @return int
	 */
	public function current() {
		return (int) $this->storage->get( $this->key );
	}
}
