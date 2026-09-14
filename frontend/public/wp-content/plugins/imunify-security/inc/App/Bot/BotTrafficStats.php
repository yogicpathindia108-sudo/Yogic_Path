<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Pure aggregator over hourly-rollup rows for the bot-traffic dashboard.
 *
 * Takes the raw rows produced by {@see HourlyStatsStorage::fetchSince()} and
 * derives the four dashboard views — category breakdown, verdict counters,
 * hourly timeline, and top named bots — with no DB access of its own, so the
 * arithmetic is fully unit-testable. Row values arrive as strings (from wpdb)
 * and are cast to int here.
 *
 * @since 4.1.0
 */
class BotTrafficStats {

	/**
	 * Rollup rows (each an associative array).
	 *
	 * @var array
	 */
	private $rows;

	/**
	 * Wrap a set of rollup rows for aggregation.
	 *
	 * @param array|null $rows Rows from HourlyStatsStorage::fetchSince(); anything
	 *                         that is not an array — including the null it returns
	 *                         on a read failure — is treated as no data.
	 */
	public function __construct( $rows ) {
		$this->rows = is_array( $rows ) ? $rows : array();
	}

	/**
	 * Total recorded bot requests across all rows.
	 *
	 * @return int
	 */
	public function totalRequests() {
		$total = 0;
		foreach ( $this->rows as $row ) {
			$total += (int) $this->field( $row, 'req_count' );
		}
		return $total;
	}

	/**
	 * Request totals per category, sorted highest-first.
	 *
	 * @return array Map of category => request count.
	 */
	public function categoryBreakdown() {
		$out = array();
		foreach ( $this->rows as $row ) {
			$cat         = (string) $this->field( $row, 'category' );
			$out[ $cat ] = ( isset( $out[ $cat ] ) ? $out[ $cat ] : 0 ) + (int) $this->field( $row, 'req_count' );
		}
		arsort( $out );
		return $out;
	}

	/**
	 * Request totals per verdict. Always exposes all three verdict keys
	 * (zero-filled) so the dashboard's counters render without isset() checks.
	 *
	 * @return array Map of verdict => request count.
	 */
	public function verdictCounters() {
		$out = array(
			RateLimitDecision::ACTION_ALLOW      => 0,
			RateLimitDecision::ACTION_RATE_LIMIT => 0,
			RateLimitDecision::ACTION_BLOCK      => 0,
		);
		foreach ( $this->rows as $row ) {
			$verdict = (string) $this->field( $row, 'verdict' );
			if ( isset( $out[ $verdict ] ) ) {
				$out[ $verdict ] += (int) $this->field( $row, 'req_count' );
			}
		}
		return $out;
	}

	/**
	 * Request totals per hour bucket split by verdict, ascending by hour, so
	 * the dashboard can stack allow / rate_limit / block columns. Sparse — only
	 * hours with recorded traffic appear; the dashboard zero-fills its window.
	 * Each hour always carries all three verdict keys (zero-filled).
	 *
	 * @return array Map of hour_bucket => { verdict => request count }.
	 */
	public function timeline() {
		$out = array();
		foreach ( $this->rows as $row ) {
			// Bot-requests-over-time only: humans feed the classification chart
			// and the Human-visitors KPI, not this verdict-stacked timeline, so
			// their aggregate row must not inflate the allow stack.
			if ( Category::HUMAN === (string) $this->field( $row, 'category' ) ) {
				continue;
			}
			$hour    = (int) $this->field( $row, 'hour_bucket' );
			$verdict = (string) $this->field( $row, 'verdict' );
			if ( ! isset( $out[ $hour ] ) ) {
				$out[ $hour ] = array(
					RateLimitDecision::ACTION_ALLOW      => 0,
					RateLimitDecision::ACTION_RATE_LIMIT => 0,
					RateLimitDecision::ACTION_BLOCK      => 0,
				);
			}
			if ( isset( $out[ $hour ][ $verdict ] ) ) {
				$out[ $hour ][ $verdict ] += (int) $this->field( $row, 'req_count' );
			}
		}
		ksort( $out );
		return $out;
	}

	/**
	 * Named bots ranked by request volume.
	 *
	 * Rows are grouped by signature slug (unnamed traffic — bot '' — is
	 * excluded, since there is nothing to name). Each entry carries the total
	 * request count, the dominant (highest-volume) category, the largest
	 * per-minute peak seen, and a per-verdict breakdown.
	 *
	 * @param int $limit Maximum number of bots to return.
	 * @return array List of bot entries, highest-volume first.
	 */
	public function topBots( $limit = 10 ) {
		$list = $this->aggregateBots();
		if ( $limit > 0 && count( $list ) > $limit ) {
			$list = array_slice( $list, 0, $limit );
		}
		return $list;
	}

	/**
	 * Named bots grouped by their dominant category, each group ordered
	 * highest-volume first. Drives the "By classification" drill-down (a
	 * category expands to its bots). Categories with no named bots are absent.
	 *
	 * @return array Map of category => list of bot entries.
	 */
	public function botsByCategory() {
		$out = array();
		foreach ( $this->aggregateBots() as $bot ) {
			$cat = $bot['category'];
			if ( ! isset( $out[ $cat ] ) ) {
				$out[ $cat ] = array();
			}
			$out[ $cat ][] = $bot;
		}
		return $out;
	}

	/**
	 * Collapse the rows into one entry per (named bot, category) pair — total
	 * requests, largest per-minute peak and per-verdict breakdown — sorted by
	 * volume. A slug classified in more than one category yields one entry per
	 * category, so both {@see topBots()} and {@see botsByCategory()} can show it
	 * under each. Shared by both.
	 *
	 * @return array
	 */
	private function aggregateBots() {
		$bots = array();
		foreach ( $this->rows as $row ) {
			$bot = (string) $this->field( $row, 'bot' );
			if ( '' === $bot ) {
				continue;
			}
			$req     = (int) $this->field( $row, 'req_count' );
			$verdict = (string) $this->field( $row, 'verdict' );
			$cat     = (string) $this->field( $row, 'category' );
			$peak    = (int) $this->field( $row, 'peak_req_min' );

			// Key by (bot, category): a slug seen in more than one category —
			// a real verified IP and a spoofed IP sending the same UA — surfaces
			// once per category, so the dashboard can list it under each rather
			// than collapsing it into a single dominant-category bucket.
			$key = $bot . "\0" . $cat;
			if ( ! isset( $bots[ $key ] ) ) {
				$bots[ $key ] = array(
					'bot'          => $bot,
					'category'     => $cat,
					'req_count'    => 0,
					'peak_req_min' => 0,
					'verdicts'     => array(
						RateLimitDecision::ACTION_ALLOW => 0,
						RateLimitDecision::ACTION_RATE_LIMIT => 0,
						RateLimitDecision::ACTION_BLOCK => 0,
					),
				);
			}

			$bots[ $key ]['req_count']   += $req;
			$bots[ $key ]['peak_req_min'] = max( $bots[ $key ]['peak_req_min'], $peak );
			if ( isset( $bots[ $key ]['verdicts'][ $verdict ] ) ) {
				$bots[ $key ]['verdicts'][ $verdict ] += $req;
			}
		}

		$list = array_values( $bots );

		usort(
			$list,
			function ( $a, $b ) {
				if ( $a['req_count'] === $b['req_count'] ) {
					// Deterministic tie-break so the list is stable across runs:
					// bot slug, then category (a slug can span two categories).
					$by_bot = strcmp( $a['bot'], $b['bot'] );
					return 0 !== $by_bot ? $by_bot : strcmp( $a['category'], $b['category'] );
				}
				return $b['req_count'] - $a['req_count'];
			}
		);

		return $list;
	}

	/**
	 * Read a row field with a null default.
	 *
	 * @param array  $row Row.
	 * @param string $key Field name.
	 * @return mixed
	 */
	private function field( $row, $key ) {
		return isset( $row[ $key ] ) ? $row[ $key ] : null;
	}
}
