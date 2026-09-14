<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Ordered composite of AllowlistMatcher instances.
 *
 * Matcher order carries intent: cheapest / most likely matchers first
 * (WP internals → WooCommerce URIs → monitoring UAs). Construction
 * order is preserved, and isAllowlisted() short-circuits on the first
 * match so the tail matchers never run on the typical request.
 *
 * Non-matcher entries in the constructor list are silently dropped so
 * a mis-wired container does not fatal the hot path.
 *
 * @since 4.0.0
 */
class RequestAllowlist {

	/**
	 * Ordered list of matchers kept for first-match short-circuit.
	 *
	 * @var AllowlistMatcher[]
	 */
	private $matchers = array();

	/**
	 * Wire up the composite over an ordered list of matchers.
	 *
	 * @param array $matchers Ordered list of AllowlistMatcher.
	 */
	public function __construct( $matchers ) {
		if ( ! is_array( $matchers ) ) {
			return;
		}
		foreach ( $matchers as $m ) {
			if ( $m instanceof AllowlistMatcher ) {
				$this->matchers[] = $m;
			}
		}
	}

	/**
	 * Whether any registered matcher accepts this request context.
	 *
	 * @param array $context Keys: uri, headers, ip, ua.
	 * @return bool
	 */
	public function isAllowlisted( $context ) {
		foreach ( $this->matchers as $matcher ) {
			if ( $matcher->matches( $context ) ) {
				return true;
			}
		}
		return false;
	}
}
