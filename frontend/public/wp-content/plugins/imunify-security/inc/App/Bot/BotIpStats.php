<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Pure aggregator over per-IP rows for the dashboard drill-down.
 *
 * Takes the raw rows from {@see HourlyIpStatsStorage::fetchSince()} and derives
 * the top IPs by request volume plus the distinct-IP total (so the UI can show
 * " + N more"). No DB access, so the ranking and the binary-IP rendering are
 * fully unit-testable. {@see byBotCategory()} groups by (named bot, category)
 * for the per-bot drill-down (unnamed traffic skipped), so a slug spanning
 * categories drills to the right IPs under each; {@see byCategory()} groups by
 * category and keeps unnamed traffic, so categories with no named bot still
 * expand to their IPs.
 *
 * @since 4.1.0
 */
class BotIpStats {

	/**
	 * Verdict severity ranks, matching HourlyIpStatsStorage's SQL ordering.
	 *
	 * @var array
	 */
	private static $rank = array(
		RateLimitDecision::ACTION_ALLOW      => 1,
		RateLimitDecision::ACTION_RATE_LIMIT => 2,
		RateLimitDecision::ACTION_BLOCK      => 3,
	);

	/**
	 * Per-IP rows (each an associative array).
	 *
	 * @var array
	 */
	private $rows;

	/**
	 * Wrap a set of per-IP rows for aggregation.
	 *
	 * @param array|null $rows Rows from HourlyIpStatsStorage::fetchSince(); anything
	 *                         that is not an array — including the null it returns
	 *                         on a read failure — is treated as no data.
	 */
	public function __construct( $rows ) {
		$this->rows = is_array( $rows ) ? $rows : array();
	}

	/**
	 * Top IPs per (named bot, category) over the loaded window.
	 *
	 * Grouped by bot slug AND category, so a slug classified in more than one
	 * category (a verified IP and a spoofed IP sending the same UA) yields a
	 * separate IP list per category — matching the per-(bot,category) rows in
	 * the top-bots table and classification accordion, so each row drills to
	 * only the IPs seen under that category.
	 *
	 * @param int $limit Maximum IPs returned per (bot, category).
	 * @return array List of { bot, category, ips:[{ip,req_count,verdict}], total_ips }.
	 */
	public function byBotCategory( $limit = 10 ) {
		$groups = array();
		foreach ( $this->rows as $row ) {
			$bot = (string) $this->field( $row, 'bot' );
			if ( '' === $bot ) {
				continue;
			}
			$category = (string) $this->field( $row, 'category' );
			$ip_text  = $this->renderIp( $this->field( $row, 'ip' ) );
			if ( '' === $ip_text ) {
				continue;
			}
			$verdict = (string) $this->field( $row, 'verdict' );
			$req     = (int) $this->field( $row, 'req_count' );

			$gkey = $bot . "\0" . $category;
			$ikey = $gkey . "\0" . $ip_text;
			if ( ! isset( $groups[ $gkey ] ) ) {
				$groups[ $gkey ] = array(
					'bot'      => $bot,
					'category' => $category,
					'ips'      => array(),
				);
			}
			if ( ! isset( $groups[ $gkey ]['ips'][ $ikey ] ) ) {
				$groups[ $gkey ]['ips'][ $ikey ] = array(
					'ip'        => $ip_text,
					'req_count' => 0,
					'verdict'   => RateLimitDecision::ACTION_ALLOW,
				);
			}
			$groups[ $gkey ]['ips'][ $ikey ]['req_count'] += $req;
			$groups[ $gkey ]['ips'][ $ikey ]['verdict']    = $this->moreSevere(
				$groups[ $gkey ]['ips'][ $ikey ]['verdict'],
				$verdict
			);
		}

		$out = array();
		foreach ( $groups as $group ) {
			$ips = array_values( $group['ips'] );
			usort(
				$ips,
				function ( $a, $b ) {
					if ( $a['req_count'] === $b['req_count'] ) {
						return strcmp( $a['ip'], $b['ip'] );
					}
					return $b['req_count'] - $a['req_count'];
				}
			);
			$out[] = array(
				'bot'       => $group['bot'],
				'category'  => $group['category'],
				'ips'       => $limit > 0 ? array_slice( $ips, 0, $limit ) : $ips,
				'total_ips' => count( $ips ),
			);
		}
		return $out;
	}

	/**
	 * Top IPs per category over the loaded window.
	 *
	 * Parallel to {@see byBot()} but grouped by category rather than bot slug,
	 * and — crucially — it keeps unnamed traffic (bot ''). It feeds the "By
	 * classification" accordion for categories that carry no named bot
	 * (UNKNOWN_AUTOMATED, honeypot-triggered MALICIOUS_BOT): those rows would
	 * otherwise be dropped by byBot(), leaving the category non-drillable, so
	 * here the category itself becomes the group and expands straight to its
	 * IPs. Callers use this only for categories without named bots, so it never
	 * double-counts against the per-bot drill-down.
	 *
	 * @param int $limit Maximum IPs returned per category.
	 * @return array List of { category, ips:[{ip,req_count,verdict}], total_ips }.
	 */
	public function byCategory( $limit = 10 ) {
		$groups = array();
		foreach ( $this->rows as $row ) {
			$category = (string) $this->field( $row, 'category' );
			if ( '' === $category ) {
				continue;
			}
			$ip_text = $this->renderIp( $this->field( $row, 'ip' ) );
			if ( '' === $ip_text ) {
				continue;
			}
			$verdict = (string) $this->field( $row, 'verdict' );
			$req     = (int) $this->field( $row, 'req_count' );

			$ikey = $category . "\0" . $ip_text;
			if ( ! isset( $groups[ $category ] ) ) {
				$groups[ $category ] = array(
					'category' => $category,
					'ips'      => array(),
				);
			}
			if ( ! isset( $groups[ $category ]['ips'][ $ikey ] ) ) {
				$groups[ $category ]['ips'][ $ikey ] = array(
					'ip'        => $ip_text,
					'req_count' => 0,
					'verdict'   => RateLimitDecision::ACTION_ALLOW,
				);
			}
			$groups[ $category ]['ips'][ $ikey ]['req_count'] += $req;
			$groups[ $category ]['ips'][ $ikey ]['verdict']    = $this->moreSevere(
				$groups[ $category ]['ips'][ $ikey ]['verdict'],
				$verdict
			);
		}

		$out = array();
		foreach ( $groups as $group ) {
			$ips = array_values( $group['ips'] );
			usort(
				$ips,
				function ( $a, $b ) {
					if ( $a['req_count'] === $b['req_count'] ) {
						return strcmp( $a['ip'], $b['ip'] );
					}
					return $b['req_count'] - $a['req_count'];
				}
			);
			$out[] = array(
				'category'  => $group['category'],
				'ips'       => $limit > 0 ? array_slice( $ips, 0, $limit ) : $ips,
				'total_ips' => count( $ips ),
			);
		}
		return $out;
	}

	/**
	 * Render a binary (inet_pton) IP to text, '' when unparseable.
	 *
	 * @param mixed $ip_bin Binary IP as stored.
	 * @return string
	 */
	private function renderIp( $ip_bin ) {
		if ( ! is_string( $ip_bin ) || '' === $ip_bin ) {
			return '';
		}
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- inet_ntop warns on a bad length; we want '' and a skip.
		$text = @inet_ntop( $ip_bin );
		return false === $text ? '' : $text;
	}

	/**
	 * Return the more severe of two verdicts (block > rate_limit > allow).
	 *
	 * @param string $a First verdict.
	 * @param string $b Second verdict.
	 * @return string
	 */
	private function moreSevere( $a, $b ) {
		$ra = isset( self::$rank[ $a ] ) ? self::$rank[ $a ] : 0;
		$rb = isset( self::$rank[ $b ] ) ? self::$rank[ $b ] : 0;
		return $rb > $ra ? $b : $a;
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
