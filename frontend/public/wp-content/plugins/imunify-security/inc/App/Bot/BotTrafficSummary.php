<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Plain-English one-liners describing a bot's traffic, built from the
 * aggregated top-bot entries produced by {@see BotTrafficStats::topBots()}.
 *
 * Example: "GPTBot made 2,400 requests, peaking at 40/min; rate limiting kept
 * it from impacting your site." No per-URL or per-IP data is referenced — the
 * sentence is derived entirely from IP-free rollup aggregates.
 *
 * @since 4.1.0
 */
class BotTrafficSummary {

	/**
	 * A per-minute peak below this is not worth calling out ("peaking at
	 * 1/min" reads as noise), so the peak clause is omitted.
	 */
	const PEAK_MENTION_THRESHOLD = 2;

	/**
	 * Build a one-line summary for a single top-bot entry.
	 *
	 * @param array $bot A {@see BotTrafficStats::topBots()} entry.
	 * @return string Sentence, or '' when the entry has no bot name.
	 */
	public static function forBot( $bot ) {
		$name = isset( $bot['bot'] ) ? (string) $bot['bot'] : '';
		if ( '' === $name ) {
			return '';
		}

		$req      = isset( $bot['req_count'] ) ? (int) $bot['req_count'] : 0;
		$peak     = isset( $bot['peak_req_min'] ) ? (int) $bot['peak_req_min'] : 0;
		$verdicts = ( isset( $bot['verdicts'] ) && is_array( $bot['verdicts'] ) ) ? $bot['verdicts'] : array();

		$noun = ( 1 === $req )
			? __( 'request', 'imunify-security' )
			: __( 'requests', 'imunify-security' );

		$sentence = sprintf(
			/* translators: 1: bot name, 2: formatted request count, 3: "request"/"requests" */
			__( '%1$s made %2$s %3$s', 'imunify-security' ),
			$name,
			number_format_i18n( $req ),
			$noun
		);

		if ( $peak >= self::PEAK_MENTION_THRESHOLD ) {
			$sentence .= sprintf(
				/* translators: %d: peak requests in a single minute */
				__( ', peaking at %d/min', 'imunify-security' ),
				$peak
			);
		}

		return $sentence . self::actionClause( RateLimitDecision::dominantAction( $verdicts ) );
	}

	/**
	 * Map {@see forBot()} over a list of top bots, dropping unnamed entries.
	 *
	 * @param array $top_bots List of {@see BotTrafficStats::topBots()} entries.
	 * @return array List of sentences.
	 */
	public static function forTopBots( $top_bots ) {
		$out = array();
		if ( ! is_array( $top_bots ) ) {
			return $out;
		}
		foreach ( $top_bots as $bot ) {
			$line = self::forBot( $bot );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	/**
	 * One-line summary for human traffic, shown for context above the bot
	 * lines in "What happened". Empty when there were no human requests.
	 *
	 * @param int $human_requests Human request total.
	 * @param int $total_requests All requests (human + bot), for the share.
	 * @return string
	 */
	public static function forHuman( $human_requests, $total_requests ) {
		$human = (int) $human_requests;
		if ( $human <= 0 ) {
			return '';
		}
		$total = (int) $total_requests;
		$pct   = $total > 0 ? (int) round( 100 * $human / $total ) : 0;
		$noun  = ( 1 === $human )
			? __( 'request', 'imunify-security' )
			: __( 'requests', 'imunify-security' );

		return sprintf(
			/* translators: 1: formatted request count, 2: "request"/"requests", 3: percentage of all traffic. */
			__( 'Human visitors made %1$s %2$s — %3$d%% of all traffic.', 'imunify-security' ),
			number_format_i18n( $human ),
			$noun,
			$pct
		);
	}

	/**
	 * Trailing clause describing what the protection did about the bot.
	 *
	 * @param string $verdict A RateLimitDecision::ACTION_* value.
	 * @return string
	 */
	private static function actionClause( $verdict ) {
		if ( RateLimitDecision::ACTION_BLOCK === $verdict ) {
			return __( '; it was blocked.', 'imunify-security' );
		}
		if ( RateLimitDecision::ACTION_RATE_LIMIT === $verdict ) {
			return __( '; rate limiting kept it from impacting your site.', 'imunify-security' );
		}
		return __( '; all requests were allowed.', 'imunify-security' );
	}
}
