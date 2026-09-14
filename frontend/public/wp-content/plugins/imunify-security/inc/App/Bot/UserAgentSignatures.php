<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Compiled-regex matcher over categorised User-Agent signature lists.
 *
 * Accepts a map of category_name => (list of literal UA tokens | path to a
 * bundled PHP signature file). Each category's tokens are preg_quote'd and
 * joined into a single alternation, giving O(1) lookup per request. A UA
 * that contains any token under a category is classified as that category;
 * ties are resolved in registration order.
 *
 * Fail-open: malformed files yield empty categories; a regex that fails to
 * compile leaves the category non-matching rather than throwing.
 *
 * @since 4.0.0
 */
class UserAgentSignatures {

	/**
	 * Canonical category names. Passed to both the constructor (keys of the
	 * $categories map) and matchesCategory() — using the constants on both
	 * sides guarantees the wiring stays consistent.
	 */
	const CATEGORY_MALICIOUS     = 'malicious';
	const CATEGORY_SEARCH_ENGINE = 'search-engine';
	const CATEGORY_AI_CRAWLER    = 'ai-crawler';
	const CATEGORY_SEO_CRAWLER   = 'seo-crawler';

	/**
	 * Category name => compiled regex (or null if the category is empty).
	 *
	 * @var array
	 */
	private $patterns = array();

	/**
	 * Category name => list of non-empty literal tokens, in registration
	 * order. Retained alongside the compiled pattern so {@see matchedToken()}
	 * can report which signature produced a match.
	 *
	 * @var array
	 */
	private $tokens = array();

	/**
	 * Register categories and their signature lists.
	 *
	 * @param array $categories Map of category_name => (array of literal tokens | path to PHP file).
	 */
	public function __construct( $categories = array() ) {
		if ( ! is_array( $categories ) ) {
			return;
		}
		foreach ( $categories as $name => $source ) {
			$name                    = (string) $name;
			$raw                     = BundledData::loadArrayFile( $source, 'signatures' );
			$filtered                = self::filterTokens( $raw );
			$this->tokens[ $name ]   = $filtered;
			$this->patterns[ $name ] = self::compile( $filtered );
		}
	}

	/**
	 * Return the first matching category for $ua, or null.
	 *
	 * @param string $ua User-Agent string.
	 * @return string|null
	 */
	public function classify( $ua ) {
		if ( ! is_string( $ua ) || '' === $ua ) {
			return null;
		}
		foreach ( $this->patterns as $name => $pattern ) {
			if ( null !== $pattern && 1 === preg_match( $pattern, $ua ) ) {
				return $name;
			}
		}
		return null;
	}

	/**
	 * Whether $ua matches any token in $category.
	 *
	 * @param string $ua       User-Agent string.
	 * @param string $category Registered category name.
	 * @return bool
	 */
	public function matchesCategory( $ua, $category ) {
		if ( ! is_string( $ua ) || '' === $ua || ! isset( $this->patterns[ $category ] ) ) {
			return false;
		}
		// isset() above already excludes an unset key and a null (empty-category)
		// pattern, so $pattern is a compiled regex string here.
		$pattern = $this->patterns[ $category ];
		return 1 === preg_match( $pattern, $ua );
	}

	/**
	 * Return the signature token that matches $ua under $category, or ''.
	 *
	 * When several tokens of the category appear in the UA, the one occurring
	 * earliest in the string wins (ties resolve to registration order). The
	 * canonical token from the registered list is returned — not the substring
	 * as it appeared in the UA — so a spoofed lower-cased "gptbot" still yields
	 * the stored slug "GPTBot".
	 *
	 * @param string $ua       User-Agent string.
	 * @param string $category Registered category name.
	 * @return string Matched token, or '' when nothing matches.
	 */
	public function matchedToken( $ua, $category ) {
		if ( ! is_string( $ua ) || '' === $ua || ! isset( $this->tokens[ $category ] ) ) {
			return '';
		}
		$best_pos = null;
		$best_tok = '';
		foreach ( $this->tokens[ $category ] as $token ) {
			$pos = stripos( $ua, $token );
			if ( false === $pos ) {
				continue;
			}
			if ( null === $best_pos || $pos < $best_pos ) {
				$best_pos = $pos;
				$best_tok = $token;
			}
		}
		return $best_tok;
	}

	/**
	 * Enumerate registered category names.
	 *
	 * @return array Category names in registration order.
	 */
	public function categories() {
		return array_keys( $this->patterns );
	}

	/**
	 * Reduce a raw token list to non-empty string tokens, preserving order.
	 *
	 * Empty strings are dropped defensively: an empty branch in the compiled
	 * alternation would match every input (a site-wide outage if it landed in
	 * the malicious category), and an empty token would confuse matchedToken().
	 *
	 * @param array $tokens Raw token list.
	 * @return array Filtered token list.
	 */
	private static function filterTokens( $tokens ) {
		$out = array();
		foreach ( $tokens as $token ) {
			if ( is_string( $token ) && '' !== $token ) {
				$out[] = $token;
			}
		}
		return $out;
	}

	/**
	 * Compile a list of filtered literal tokens into a single alternation regex.
	 *
	 * Every token is preg_quote'd before joining, so the resulting pattern is
	 * guaranteed valid for any string input — no runtime probe needed.
	 *
	 * @param array $tokens Filtered literal UA substrings to match.
	 * @return string|null Compiled pattern, or null when the list is empty.
	 */
	private static function compile( $tokens ) {
		$escaped = array();
		foreach ( $tokens as $token ) {
			$escaped[] = preg_quote( $token, '~' );
		}
		if ( empty( $escaped ) ) {
			return null;
		}
		return '~(?:' . implode( '|', $escaped ) . ')~i';
	}
}
