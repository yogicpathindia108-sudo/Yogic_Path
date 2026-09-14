<?php
/**
 * Simple Gutenberg Block Parser Implementation
 *
 * This file contains the SimpleBlockParser class which provides lightweight
 * Gutenberg block parsing functionality for the Divi theme. The parser extracts
 * basic block information without maintaining complex hierarchical structures,
 * making it suitable for performance-critical scenarios where full block parsing
 * is unnecessary.
 *
 * The parser focuses on extracting block names and attributes from Gutenberg
 * block comments while providing built-in caching for improved performance.
 * It intentionally omits advanced features like parent-child relationships,
 * block ordering, and nested structures to maintain simplicity and speed.
 *
 * @since ??
 * @package Divi\FrontEnd\BlockParser
 */

namespace ET\Builder\FrontEnd\BlockParser;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use ET\Builder\FrontEnd\BlockParser\SimpleBlock;
use ET\Builder\FrontEnd\BlockParser\SimpleBlockParserStore;

/**
 * Simple Gutenberg Block Parser
 *
 * A lightweight parser for extracting Gutenberg block information from content.
 * This parser provides basic block extraction functionality without the complexity
 * of maintaining hierarchical relationships or advanced parsing features.
 *
 * Key Features:
 * - Extracts block name and attributes from Gutenberg block comments
 * - Built-in caching for performance optimization
 * - Simple, flat array output structure
 * - Error handling for malformed JSON attributes
 *
 * Limitations:
 * - Does NOT preserve parent-child block relationships
 * - Does NOT maintain block order indices
 * - Does NOT support nested block structures
 * - Does NOT parse block content (only comments)
 * - Does NOT provide block positioning information
 *
 * Use Cases:
 * - Quick block identification and attribute extraction
 * - Performance-critical scenarios where full parsing is unnecessary
 * - Simple block analysis and filtering operations
 * - Basic block metadata extraction for processing
 *
 * @since ??
 */
class SimpleBlockParser {

	/**
	 * Cache for the parsed blocks.
	 *
	 * @var array<SimpleBlockParserStore>
	 */
	private static $_cache = [];

	/**
	 * Parse Gutenberg blocks from content as a flattened array.
	 *
	 * This method extracts Gutenberg block information from content and returns
	 * a simplified, flattened array structure. It does NOT preserve parent-child
	 * relationships, block order index, or other advanced hierarchical information
	 * that would be available in a full block parser. Each block is treated as
	 * an independent entity with only basic name and attributes data.
	 *
	 * The parsed results are cached using MD5 hash of the content for performance.
	 * Note: Caching is disabled when a filter function is provided.
	 *
	 * @param string $content The content containing Gutenberg block comments to parse.
	 * @param array  $options {
	 *    Optional. Configuration options for parsing behavior.
	 *
	 *    @type string $blockName Optional. The specific block name to filter for (e.g., 'divi/button').
	 *                           When provided, only blocks matching this exact name will be returned.
	 *                           Split with comma to match multiple block names.
	 *                           When empty, all blocks in the content will be parsed.
	 *    @type callable $filter Optional. A callable function to filter blocks after parsing.
	 *                          The function receives a SimpleBlock instance and should return
	 *                          true to include the block, false to exclude it.
	 *                          Example: function( SimpleBlock $block ) { return 'divi/button' === $block->name(); }.
	 *                          Note: Providing a filter disables caching for this parse operation.
	 *    @type bool $excludeError Optional. Whether to exclude blocks with parsing errors. Default true.
	 *                             When true, blocks with errors (malformed JSON, missing names) are excluded.
	 *                             When false, all blocks are included regardless of parsing errors.
	 *    @type int $limit Optional. Maximum number of blocks to return. Default 0 (no limit).
	 *                     When greater than 0, only the first N matching blocks will be returned.
	 * }
	 *
	 * @return SimpleBlockParserStore
	 */
	public static function parse( string $content, array $options = [] ): SimpleBlockParserStore {
		// Extract options with defaults.
		$block_name    = $options['blockName'] ?? '';
		$filter        = $options['filter'] ?? null;
		$exclude_error = $options['excludeError'] ?? true;
		$limit         = (int) ( $options['limit'] ?? 0 );

		if ( null === $filter ) {
			// Include block name, exclude_error, and limit in cache key to ensure different results are cached separately.
			$error_flag = $exclude_error ? 'exclude_errors' : 'include_errors';
			$limit_flag = $limit > 0 ? 'limit_' . $limit : 'no_limit';
			$cache_key  = md5( $content . '|' . $block_name . '|' . $error_flag . '|' . $limit_flag );
		} else {
			// Disable caching when filter is provided since filters can be dynamic and complex.
			$cache_key = null;
		}

		if ( $cache_key && isset( self::$_cache[ $cache_key ] ) ) {
			return self::$_cache[ $cache_key ];
		}

		$parsed = new SimpleBlockParserStore( [] );

		if ( empty( $content ) ) {
			return $parsed;
		}

		// Create regex pattern based on whether block name is specified.
		if ( ! empty( $block_name ) ) {
			// Split by comma, trim whitespace, and remove empty segments to avoid empty alternations.
			$block_names = array_values(
				array_filter(
					array_map(
						function ( $name ) {
							return trim( $name );
						},
						explode( ',', $block_name )
					),
					function ( $name ) {
						return '' !== $name;
					}
				)
			);

			// If there are no valid block names after sanitization, simply return empty results.
			if ( empty( $block_names ) ) {
				if ( $cache_key ) {
					self::$_cache[ $cache_key ] = $parsed;
				}

				return $parsed;
			}

			// Escape special regex characters in the block names.
			$escaped_block_names = array_map(
				function ( $block_name_to_map ) {
					return preg_quote( $block_name_to_map, '/' );
				},
				$block_names
			);

			// Regex to match only the specified block names with optional JSON attributes.
			// Supports blocks with or without attributes.
			// Test regex: https://regex101.com/r/mDJ4oC/1.
			$regex = '/<!-- wp:(?P<block_name>' . implode( '|', $escaped_block_names ) . ')\s+(?:\{(?P<json>.*?)\})?\s*\/?-->/s';
		} else {
			// Original regex to match any Gutenberg block comments, capturing the block name and optional JSON attributes.
			// Supports blocks with or without attributes.
			// Test regex: https://regex101.com/r/okrckj/1.
			$regex = '/<!-- wp:(?P<block_name>[a-zA-Z0-9_-]+\/[a-zA-Z0-9_-]+)\s+(?:\{(?P<json>.*?)\})?\s*\/?-->/s';
		}

		preg_match_all( $regex, $content, $matches, PREG_SET_ORDER, 0 );

		$added_count = 0;
		foreach ( $matches as $match ) {
			$raw          = $match[0];
			$name         = $match['block_name'] ?? '';
			$json_content = $match['json'] ?? '';
			$json         = '{' . $json_content . '}';
			$attrs        = json_decode( $json, true );

			$block = new SimpleBlock(
				[
					'raw'   => $raw,
					'name'  => $name,
					'attrs' => $attrs ?? [],
					'json'  => $json,
					'error' => '' === $name || null === $attrs,
				]
			);

			// Exclude error blocks if exclude_error is true.
			if ( $exclude_error && $block->error() ) {
				continue;
			}

			// Apply filter function if provided.
			if ( null !== $filter && ! $filter( $block ) ) {
				continue;
			}

			$parsed->add( $block );
			++$added_count;

			// Check if we've reached the limit.
			if ( $limit > 0 && $added_count >= $limit ) {
				break;
			}
		}

		if ( $cache_key ) {
			self::$_cache[ $cache_key ] = $parsed;
		}

		return $parsed;
	}
}
