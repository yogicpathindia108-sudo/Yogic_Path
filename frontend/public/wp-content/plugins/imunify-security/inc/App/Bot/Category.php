<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Bot classification categories and their default rate-limit contract.
 *
 * Values are kept as plain strings so they round-trip cleanly through log
 * lines, configuration files, and the agent RPC layer.
 *
 * Default rate limits are encoded on this class as a single source of
 * truth for the downstream rate limiter. A limit of 0 means
 * "no per-request rate check applies" — either because the category is
 * unlimited (HUMAN) or because it short-circuits to a 403 block
 * (MALICIOUS_BOT). Use isBlocking() / isRateLimited() to distinguish.
 *
 * @since 4.0.0
 */
class Category {

	const VERIFIED_SEARCH_ENGINE = 'verified_search_engine';
	const VERIFIED_AI_CRAWLER    = 'verified_ai_crawler';
	const VERIFIED_SEO_CRAWLER   = 'verified_seo_crawler';
	const UNVERIFIED_BOT         = 'unverified_bot';
	const UNKNOWN_AUTOMATED      = 'unknown_automated';
	const MALICIOUS_BOT          = 'malicious_bot';
	const HUMAN                  = 'human';

	/**
	 * Return every canonical category value.
	 *
	 * @return array
	 */
	public static function all() {
		return array_keys( self::defaultLimits() );
	}

	/**
	 * Human-readable label for a category. Single source of truth for the
	 * category names shown in the widget limits table and the Bot Traffic
	 * dashboard. Unknown values fall back to the raw identifier.
	 *
	 * @param string $category Category value.
	 * @return string
	 */
	public static function label( $category ) {
		$map = array(
			self::VERIFIED_SEARCH_ENGINE => __( 'Verified search engines', 'imunify-security' ),
			self::VERIFIED_AI_CRAWLER    => __( 'Verified AI crawlers', 'imunify-security' ),
			self::VERIFIED_SEO_CRAWLER   => __( 'Verified SEO crawlers', 'imunify-security' ),
			self::UNKNOWN_AUTOMATED      => __( 'Unknown automated', 'imunify-security' ),
			self::UNVERIFIED_BOT         => __( 'Unverified bots', 'imunify-security' ),
			self::MALICIOUS_BOT          => __( 'Malicious bots', 'imunify-security' ),
			self::HUMAN                  => __( 'Humans', 'imunify-security' ),
		);
		return isset( $map[ $category ] ) ? $map[ $category ] : (string) $category;
	}

	/**
	 * Default request-per-minute limit for a category.
	 *
	 * @param string $category Category value.
	 * @return int Requests per minute, or 0 when no rate limit applies.
	 */
	public static function defaultLimit( $category ) {
		if ( ! is_string( $category ) ) {
			return 0;
		}
		$map = self::defaultLimits();
		return isset( $map[ $category ] ) ? $map[ $category ] : 0;
	}

	/**
	 * Single source of truth for the classification categories and their
	 * req/min defaults. Backs both all() and defaultLimit().
	 *
	 * @return array
	 */
	private static function defaultLimits() {
		return array(
			self::VERIFIED_SEARCH_ENGINE => 300,
			self::VERIFIED_AI_CRAWLER    => 10,
			self::VERIFIED_SEO_CRAWLER   => 60,
			self::UNVERIFIED_BOT         => 2,
			self::UNKNOWN_AUTOMATED      => 5,
			self::MALICIOUS_BOT          => 0,
			self::HUMAN                  => 0,
		);
	}

	/**
	 * Whether a category short-circuits to an outright 403 block.
	 *
	 * @param string $category Category value.
	 * @return bool
	 */
	public static function isBlocking( $category ) {
		return self::MALICIOUS_BOT === $category;
	}

	/**
	 * Whether a category has a positive per-request rate limit that should be enforced.
	 *
	 * @param string $category Category value.
	 * @return bool
	 */
	public static function isRateLimited( $category ) {
		return self::defaultLimit( $category ) > 0;
	}

	/**
	 * Whether $category is one of the canonical values.
	 *
	 * @param mixed $category Category value to test.
	 * @return bool
	 */
	public static function isValid( $category ) {
		if ( ! is_string( $category ) ) {
			return false;
		}
		return in_array( $category, self::all(), true );
	}
}
