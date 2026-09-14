<?php
/**
 * Shortcode class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Framework\Utility;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Shortcode class.
 *
 * @since ??
 */
class Shortcode {

		/**
		 * Check if content contains Divi Builder shortcodes.
		 *
		 * This method detects any registered Divi module shortcodes (including third-party modules),
		 * while avoiding non-Divi shortcodes to prevent false positives.
		 *
		 * If this function is called with `content=null`, and the current query is for an existing single post of any post
		 * type (`is_singular=true`), then the function will attempt to get the raw post content via `get_post_field` using
		 * `get_the_ID` to get the post ID.
		 *
		 * @since ??
		 *
		 * @param string $content Optional. Content to check. Default `null`.
		 *
		 * @return bool
		 */
	public static function has_builder_shortcode( $content = null ): bool {
		// Use raw post content so shortcodes are not altered by filters or formatting.
		if ( null === $content && is_singular() ) {
			$content = get_post_field( 'post_content', get_the_ID(), 'raw' );
		}

		if ( ! is_string( $content ) ) {
			return false;
		}

		if ( false === strpos( $content, '[' ) ) {
			return false;
		}

		// Check if content contains D4 core module shortcode (et_pb_).
		if ( self::has_core_module_shortcode( '', $content ) ) {
			return true;
		}

		// Check if content contains third-party module shortcode.
		return self::has_third_party_shortcode( $content );
	}

	/**
	 * Check if content contains D4 core module shortcode (et_pb_).
	 *
	 * If this function is called with `content=null`, and the current query is for an existing single post of any post
	 * type (`is_singular=true`), then the function will attempt to get the raw post content via `get_post_field` using
	 * `get_the_ID` to get the post ID.
	 *
	 * @since ??
	 *
	 * @see Conditions::has_shortcode() - Equivalent function for checking D4 core module shortcodes.
	 *
	 * @param string $shortcode_suffix Optional. Shortcode tag suffix to check. Default empty string.
	 * @param string $content          Optional. Content to check. Default `null`.
	 *
	 * @return bool
	 */
	public static function has_core_module_shortcode( $shortcode_suffix = '', $content = null ): bool {
		// If content is not provided, try to get it from the current post.
		if ( null === $content && is_singular() ) {
			$content = get_post_field( 'post_content', get_the_ID(), 'raw' );
		}

		if ( ! is_string( $content ) ) {
			return false;
		}

		/**
		 * Regex pattern to match paired and self-closing shortcodes with prefix `et_pb_`.
		 *
		 * Test regex https://regex101.com/r/XfqdEC/1
		 */
		$regex_pattern = '/\[et_pb_' . $shortcode_suffix . '[^\]]*\/?\]/';

		return (bool) ( preg_match( $regex_pattern, $content ) );
	}

	/**
	 * Check if content contains third-party module shortcodes.
	 *
	 * @since ??
	 *
	 * @param string $content Optional. Content to check. Default `null`.
	 *
	 * @return bool
	 */
	private static function has_third_party_shortcode( $content = null ): bool {
		// If content is not provided, try to get it from the current post.
		if ( null === $content && is_singular() ) {
			$content = get_post_field( 'post_content', get_the_ID(), 'raw' );
		}

		if ( ! is_string( $content ) ) {
			return false;
		}

		// Use the persisted list to avoid loading the shortcode framework in detection paths.
		// This keeps the gate lightweight and avoids side effects from initializing extensions.
		$shortcode_slugs = array_filter( et_get_option( 'all_third_party_shortcode_slugs', [], '', true ) );

		if ( empty( $shortcode_slugs ) ) {
			return false;
		}

		$regex_pattern = get_shortcode_regex( $shortcode_slugs );

		/**
		 * Regex pattern to match third-party module shortcodes.
		 *
		 * Test regex https://regex101.com/r/OzPZpQ/1
		 */
		return (bool) preg_match( '/' . $regex_pattern . '/s', $content );
	}
}
