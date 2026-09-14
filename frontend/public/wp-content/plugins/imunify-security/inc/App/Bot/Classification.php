<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Immutable result of {@see Classifier::classify()}.
 *
 * Pairs the six-value {@see Category} with the matched User-Agent signature
 * token (e.g. "GPTBot") that produced it. The bot token is the empty string
 * for categories that never come from a signature match — HUMAN,
 * UNKNOWN_AUTOMATED, honeypot-triggered MALICIOUS_BOT, and empty-UA
 * UNVERIFIED_BOT — leaving it populated only for requests that carried a
 * recognised bot User-Agent.
 *
 * @since 4.1.0
 */
class Classification {

	/**
	 * One of the {@see Category} constants.
	 *
	 * @var string
	 */
	private $category;

	/**
	 * Matched signature token, or '' when no named signature applied.
	 *
	 * @var string
	 */
	private $bot;

	/**
	 * Build a classification result.
	 *
	 * @param string $category One of the Category constants.
	 * @param string $bot      Matched signature token, or '' when unnamed.
	 */
	public function __construct( $category, $bot = '' ) {
		$this->category = (string) $category;
		$this->bot      = is_string( $bot ) ? $bot : '';
	}

	/**
	 * Bot classification category.
	 *
	 * @return string
	 */
	public function getCategory() {
		return $this->category;
	}

	/**
	 * Matched signature token, or '' when the request carried no recognised
	 * bot User-Agent.
	 *
	 * @return string
	 */
	public function getBot() {
		return $this->bot;
	}
}
