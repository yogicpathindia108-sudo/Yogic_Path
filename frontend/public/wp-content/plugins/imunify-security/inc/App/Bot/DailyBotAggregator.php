<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Pure daily aggregator turning one calendar day of hourly-rollup and per-IP
 * rows into the export's rollup records — one per (category, bot).
 *
 * Takes the raw rows from {@see HourlyStatsStorage::fetchSince()} and
 * {@see HourlyIpStatsStorage::fetchSince()} (which have no upper bucket bound),
 * clips them to the target day's inclusive [first, last] bucket range, and
 * derives per (category, bot): verdict-split counts, the largest per-minute
 * peak, and up to {@see TOP_IPS_MAX} top offender IPs by request count. No DB
 * access, so the arithmetic is fully unit-testable. Row values arrive as
 * strings (from wpdb) and are cast to int here; the per-IP `ip` field arrives
 * as inet_pton binary and is decoded back to text.
 *
 * @since 4.1.0
 */
class DailyBotAggregator {

	/**
	 * Maximum top-offender IPs embedded per (category, bot) row. Mirrors the
	 * per-group cap enforced upstream in {@see HourlyIpStatsStorage::IP_CAP}.
	 */
	const TOP_IPS_MAX = 50;

	/**
	 * Hourly rollup rows (each an associative array).
	 *
	 * @var array
	 */
	private $hourlyRows;

	/**
	 * Per-IP rows (each an associative array; `ip` in binary form).
	 *
	 * @var array
	 */
	private $ipRows;

	/**
	 * Inclusive lower bound on hour_bucket for the target day.
	 *
	 * @var int
	 */
	private $firstBucket;

	/**
	 * Inclusive upper bound on hour_bucket for the target day.
	 *
	 * @var int
	 */
	private $lastBucket;

	/**
	 * Wrap one day's raw hourly + per-IP rows for aggregation.
	 *
	 * @param array $hourly_rows  Rows from HourlyStatsStorage::fetchSince().
	 * @param array $ip_rows      Rows from HourlyIpStatsStorage::fetchSince().
	 * @param int   $first_bucket Inclusive lower bound on hour_bucket.
	 * @param int   $last_bucket  Inclusive upper bound on hour_bucket.
	 */
	public function __construct( $hourly_rows, $ip_rows, $first_bucket, $last_bucket ) {
		$this->hourlyRows  = is_array( $hourly_rows ) ? $hourly_rows : array();
		$this->ipRows      = is_array( $ip_rows ) ? $ip_rows : array();
		$this->firstBucket = (int) $first_bucket;
		$this->lastBucket  = (int) $last_bucket;
	}

	/**
	 * Build the export rollup records, one per (category, bot). The record set
	 * is driven by the hourly rows (the count authority); top IPs are attached
	 * where present and default to an empty array otherwise.
	 *
	 * @return array List of rollup records.
	 */
	public function rollups() {
		$top_ips = $this->aggregateTopIps();
		$bots    = array();

		foreach ( $this->hourlyRows as $row ) {
			if ( ! $this->inRange( $row ) ) {
				continue;
			}
			$category = (string) $this->field( $row, 'category' );
			if ( '' === $category ) {
				continue;
			}
			$bot     = (string) $this->field( $row, 'bot' );
			$verdict = (string) $this->field( $row, 'verdict' );
			$key     = $category . "\0" . $bot;

			if ( ! isset( $bots[ $key ] ) ) {
				$bots[ $key ] = array(
					'type'         => 'rollup',
					'category'     => $category,
					'bot'          => $bot,
					'counts'       => array(
						RateLimitDecision::ACTION_ALLOW => 0,
						RateLimitDecision::ACTION_RATE_LIMIT => 0,
						RateLimitDecision::ACTION_BLOCK => 0,
					),
					'peak_req_min' => 0,
					'top_ips'      => isset( $top_ips[ $key ] ) ? $top_ips[ $key ] : array(),
				);
			}

			if ( isset( $bots[ $key ]['counts'][ $verdict ] ) ) {
				$bots[ $key ]['counts'][ $verdict ] += (int) $this->field( $row, 'req_count' );
			}
			$peak = (int) $this->field( $row, 'peak_req_min' );
			if ( $peak > $bots[ $key ]['peak_req_min'] ) {
				$bots[ $key ]['peak_req_min'] = $peak;
			}
		}

		return array_values( $bots );
	}

	/**
	 * Sum per-IP request counts across the day per (category, bot, ip), then
	 * keep the top {@see TOP_IPS_MAX} IPs per (category, bot) ordered by request
	 * count desc (ties broken by IP text asc for a stable result).
	 *
	 * @return array Map of "category\0bot" => list of { ip, req_count }.
	 */
	private function aggregateTopIps() {
		$groups = array();
		foreach ( $this->ipRows as $row ) {
			if ( ! $this->inRange( $row ) ) {
				continue;
			}
			$category = (string) $this->field( $row, 'category' );
			if ( '' === $category ) {
				continue;
			}
			$ip_bin = $this->field( $row, 'ip' );
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- inet_ntop warns on a bad length; we want false and a skip.
			$ip_text = is_string( $ip_bin ) ? @inet_ntop( $ip_bin ) : false;
			if ( false === $ip_text || '' === $ip_text ) {
				continue;
			}
			$bot                        = (string) $this->field( $row, 'bot' );
			$key                        = $category . "\0" . $bot;
			$prev                       = isset( $groups[ $key ][ $ip_text ] ) ? $groups[ $key ][ $ip_text ] : 0;
			$groups[ $key ][ $ip_text ] = $prev + (int) $this->field( $row, 'req_count' );
		}

		$out = array();
		foreach ( $groups as $key => $ips ) {
			$list = array();
			foreach ( $ips as $ip => $count ) {
				$list[] = array(
					'ip'        => (string) $ip,
					'req_count' => (int) $count,
				);
			}
			usort(
				$list,
				function ( $a, $b ) {
					if ( $a['req_count'] === $b['req_count'] ) {
						return strcmp( $a['ip'], $b['ip'] );
					}
					return $b['req_count'] - $a['req_count'];
				}
			);
			if ( count( $list ) > self::TOP_IPS_MAX ) {
				$list = array_slice( $list, 0, self::TOP_IPS_MAX );
			}
			$out[ $key ] = $list;
		}
		return $out;
	}

	/**
	 * Whether a row's hour_bucket falls inside the target day.
	 *
	 * @param array $row Row.
	 * @return bool
	 */
	private function inRange( $row ) {
		$bucket = (int) $this->field( $row, 'hour_bucket' );
		return $bucket >= $this->firstBucket && $bucket <= $this->lastBucket;
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
