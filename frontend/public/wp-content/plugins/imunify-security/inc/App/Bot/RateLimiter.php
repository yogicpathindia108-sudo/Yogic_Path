<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Per-IP, per-category rate limiter driving the Layer B block decision.
 *
 * Collaborators:
 *   - Category              — classification constants and defaults.
 *   - Preset                — per-preset req/min limits and escalation thresholds.
 *   - IpNormalizer          — collapses IPv6 to /64, de-maps IPv6-mapped IPv4.
 *   - CounterStorageInterface — ephemeral MEMORY engine for rolling-window counters.
 *   - BlockStorageInterface   — durable InnoDB backend for violations and block markers.
 *
 * Storage key layout (namespaced per site and per preset to prevent bleed):
 *   rl:c:<site_id>:<preset>:<cat>:<ip_key>   rolling window counter (TTL = WINDOW_SECONDS)       → CounterStorageInterface
 *   rl:v:<site_id>:<preset>:<cat>:<ip_key>   violation counter      (TTL = ESCALATION_WINDOW_SECONDS) → BlockStorageInterface
 *   rl:b:<preset>:<cat>:<ip_key>             extended-block marker  (TTL = ESCALATION_BLOCK_TTL) → BlockStorageInterface (site_id passed separately)
 *
 * The <site_id> prefix is `substr(md5(ABSPATH), 0, 16)` so that two
 * independent WP installs on the same shared host (same UNIX user,
 * same PHP-FPM pool, same APCu / same Redis) don't share counters.
 * Without this prefix, APCu is incidentally scoped by PHP-FPM pool
 * (usually one per user) — which still leaks across a user's sites —
 * and Redis has no scope at all (every site on the server shares one
 * keyspace). md5 is used purely as a fast fingerprint (not crypto);
 * the semgrep weak-crypto rule is suppressed on the one call site.
 *
 * Decision pipeline:
 *   1. Monitor-only preset                  → ALLOW (observe-only: nothing
 *                                             is blocked, not even malicious)
 *   2. Malicious category                   → BLOCK (403)
 *   3. IP fails to normalise                → ALLOW (fail-open)
 *   4. Extended block marker present        → RATE_LIMIT with ESCALATION_BLOCK_TTL
 *   5. Limit is 0 for (preset, category)    → ALLOW
 *   6. Increment counter, count <= limit    → ALLOW
 *   7. Count > limit                        → record violation
 *        if violations >= escalation threshold → set extended-block marker
 *      In either case                       → RATE_LIMIT
 *
 * @since 4.0.0
 */
class RateLimiter {

	/**
	 * Rolling window in seconds used for the per-minute counter.
	 */
	const WINDOW_SECONDS = 60;

	/**
	 * Window in seconds over which violations accumulate for escalation.
	 */
	const ESCALATION_WINDOW_SECONDS = 3600;

	/**
	 * Duration of the extended block once escalation triggers.
	 */
	const ESCALATION_BLOCK_TTL = 3600;

	/**
	 * Ephemeral MEMORY-engine counter storage for rolling-window rate accounting.
	 *
	 * @var CounterStorageInterface
	 */
	private $counterStorage;

	/**
	 * Durable InnoDB storage for violation counters and block markers.
	 *
	 * @var BlockStorageInterface
	 */
	private $blockStorage;

	/**
	 * Active preset.
	 *
	 * @var string
	 */
	private $preset;

	/**
	 * Site-scoped prefix for all storage keys.
	 *
	 * @var string
	 */
	private $site_id;

	/**
	 * Effective rolling-window duration in seconds.
	 *
	 * @var int
	 */
	private $window_seconds;

	/**
	 * Wire up a limiter over split storage backends and a preset.
	 *
	 * @param CounterStorageInterface $counter_storage Ephemeral MEMORY-engine counter store.
	 * @param BlockStorageInterface   $block_storage   Durable InnoDB store for violations and blocks.
	 * @param string                  $preset          Preset identifier (defaults to Balanced).
	 * @param string|null             $site_id         Optional explicit site prefix. Production
	 *                                                 leaves this null — the constructor derives
	 *                                                 a 16-char hex hash from ABSPATH. Tests pass
	 *                                                 explicit values to verify key isolation.
	 * @param int|null                $window_seconds  Optional override for the rolling window
	 *                                                 duration. When null, uses WINDOW_SECONDS.
	 *                                                 Last because only tests and wp-config
	 *                                                 overrides supply it.
	 */
	public function __construct( $counter_storage, $block_storage, $preset = Preset::BALANCED, $site_id = null, $window_seconds = null ) {
		$this->counterStorage = $counter_storage;
		$this->blockStorage   = $block_storage;
		$this->preset         = Preset::isValid( $preset ) ? $preset : Preset::DEFAULT_PRESET;
		$this->window_seconds = null !== $window_seconds ? max( 1, (int) $window_seconds ) : self::WINDOW_SECONDS;
		$this->site_id        = null !== $site_id ? $site_id : SiteScope::derive();
	}

	/**
	 * Active preset identifier.
	 *
	 * @return string
	 */
	public function getPreset() {
		return $this->preset;
	}

	/**
	 * Effective rolling-window duration in seconds.
	 *
	 * @return int
	 */
	public function getWindowSeconds() {
		return $this->window_seconds;
	}

	/**
	 * Evaluate a classified request.
	 *
	 * @param string $category Category constant.
	 * @param string $ip       Resolved client IP (post-RealIpResolver).
	 * @return RateLimitDecision
	 */
	public function check( $category, $ip ) {
		// Monitor-only is observe-only: nothing is blocked, not even a
		// malicious/honeypot hit. This short-circuit is deliberately ahead
		// of the malicious block so "Monitor" means exactly what the widget
		// shows — malicious bots are logged, not blocked.
		if ( Preset::isMonitorOnly( $this->preset ) ) {
			return RateLimitDecision::allow();
		}
		if ( Category::isBlocking( $category ) ) {
			return RateLimitDecision::block();
		}

		$ip_key = IpNormalizer::forRateLimit( $ip );
		if ( '' === $ip_key ) {
			return RateLimitDecision::allow();
		}

		if ( $this->blockStorage->isBlocked( $this->blockKey( $category, $ip_key ), $this->site_id ) ) {
			return RateLimitDecision::rateLimit( self::ESCALATION_BLOCK_TTL );
		}

		$limit = Preset::limitFor( $this->preset, $category );
		if ( $limit <= 0 ) {
			return RateLimitDecision::allow();
		}

		$count = $this->counterStorage->increment(
			$this->counterKey( $category, $ip_key ),
			$this->window_seconds
		);

		if ( $count <= $limit ) {
			return RateLimitDecision::allow();
		}

		$violations = $this->blockStorage->incrementViolation(
			$this->violationKey( $category, $ip_key ),
			self::ESCALATION_WINDOW_SECONDS
		);
		$threshold  = Preset::escalationThreshold( $this->preset );

		if ( $threshold > 0 && $violations >= $threshold ) {
			$this->blockStorage->writeBlock(
				$this->blockKey( $category, $ip_key ),
				$this->site_id,
				$category,
				$this->preset,
				self::ESCALATION_BLOCK_TTL
			);
			return RateLimitDecision::rateLimit( self::ESCALATION_BLOCK_TTL );
		}

		return RateLimitDecision::rateLimit( $this->window_seconds );
	}

	/**
	 * Rolling-window counter key.
	 *
	 * @param string $category Category constant.
	 * @param string $ip_key   Normalised IP.
	 * @return string
	 */
	private function counterKey( $category, $ip_key ) {
		return 'rl:c:' . $this->site_id . ':' . $this->preset . ':' . $category . ':' . $ip_key;
	}

	/**
	 * Escalation-window violation counter key.
	 *
	 * @param string $category Category constant.
	 * @param string $ip_key   Normalised IP.
	 * @return string
	 */
	private function violationKey( $category, $ip_key ) {
		return 'rl:v:' . $this->site_id . ':' . $this->preset . ':' . $category . ':' . $ip_key;
	}

	/**
	 * Extended-block marker key (no site_id — passed separately to BlockStorageInterface).
	 *
	 * @param string $category Category constant.
	 * @param string $ip_key   Normalised IP.
	 * @return string
	 */
	private function blockKey( $category, $ip_key ) {
		return 'rl:b:' . $this->preset . ':' . $category . ':' . $ip_key;
	}
}
