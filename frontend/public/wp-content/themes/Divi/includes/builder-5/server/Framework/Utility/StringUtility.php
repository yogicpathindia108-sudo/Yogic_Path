<?php
/**
 * StringUtility class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Framework\Utility;

// phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames -- Reserved keywords used intentionally for clarity in utility functions.

/**
 * StringUtility class.
 *
 * This class contains methods to remove characters from strings.
 *
 * @since ??
 */
class StringUtility {

	/**
	 * Compare two version strings with custom prefixes normalization.
	 *
	 * This method normalizes known prefixes to match expected semantic version order
	 * and then uses PHP's version_compare function for the actual comparison.
	 *
	 * @since ??
	 *
	 * @param string      $v1       First version string to compare.
	 * @param string      $v2       Second version string to compare.
	 * @param string|null $operator Optional comparison operator.
	 *
	 * @return bool|int Returns comparison result based on operator, or integer if no operator.
	 */
	public static function version_compare( $v1, $v2, $operator = null ) {
		// Normalize known prefixes to match expected semver order.
		$map = [
			'dev-alpha'    => 'alpha.1',
			'dev-beta'     => 'alpha.2',
			'public-alpha' => 'alpha.3',
			'public-beta'  => 'alpha.4',
		];

		$v1_norm = strtr( $v1, $map );
		$v2_norm = strtr( $v2, $map );

		return $operator
			? version_compare( $v1_norm, $v2_norm, $operator )
			: version_compare( $v1_norm, $v2_norm );
	}

	/**
	 * Remove provided characters from given string.
	 *
	 * @since ??
	 *
	 * @param string $string     The string to trim.
	 * @param array  $characters An array of single character to trim.
	 *
	 * @return string
	 */
	public static function trim_extended( $string, $characters ) {
		// Allow only single character.
		if ( $characters ) {
			$characters = array_filter(
				$characters,
				function ( $character ) {
					return is_string( $character ) && 1 === strlen( $character );
				}
			);
		}

		if ( ! $characters ) {
			return $string;
		}

		$first_char = substr( $string, 0, 1 );

		while ( '' !== $string && in_array( $first_char, $characters, true ) ) {
			// Remove the first character.
			$string = substr_replace( $string, '', 0, 1 );

			if ( '' === $string ) {
				break;
			}

			// Get the first character of the string for next iteration.
			$first_char = substr( $string, 0, 1 );
		}

		$last_char = substr( $string, -1 );

		while ( '' !== $string && in_array( $last_char, $characters, true ) ) {
			// Remove the last character.
			$string = substr_replace( $string, '', -1, 1 );

			if ( '' === $string ) {
				break;
			}

			// Get the last character of the string for next iteration.
			$last_char = substr( $string, -1 );
		}

		return $string;
	}

	/**
	 * Trim string if the first and last character of a string are the same and are in the list of
	 * characters to remove.
	 *
	 * @since ??
	 *
	 * @param string $string     The string to trim.
	 * @param array  $characters An array of single character to trim.
	 *
	 * @return string
	 */
	public static function trim_pair( $string, $characters ) {
		// Allow only single character and not a new line character.
		if ( $characters ) {
			$characters = array_filter(
				$characters,
				function ( $character ) {
					return is_string( $character ) && 1 === strlen( $character );
				}
			);
		}

		if ( ! $characters ) {
			return $string;
		}

		$first_char = substr( $string, 0, 1 );
		$last_char  = substr( $string, -1 );

		while ( '' !== $string && $first_char === $last_char && in_array( $first_char, $characters, true ) ) {
			// Remove the first character.
			$string = substr_replace( $string, '', 0, 1 );

			// Remove the last character.
			$string = substr_replace( $string, '', -1, 1 );

			if ( '' === $string ) {
				break;
			}

			// Get the first character of the string for next iteration.
			$first_char = substr( $string, 0, 1 );

			// Get the last character of the string for next iteration.
			$last_char = substr( $string, -1 );
		}

		return $string;
	}

	/**
	 * Checks if a string starts with a given substring.
	 *
	 * Performs a case-sensitive check indicating if haystack begins with needle.
	 * Wraps PHP's str_starts_with() so third-party plugins and extensions can rely on a stable
	 * ET\Builder API instead of calling that global or re-implementing polyfills across PHP versions.
	 *
	 * @since ??
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle The substring to search for in the haystack.
	 *
	 * @return bool Returns true if haystack begins with needle, false otherwise.
	 */
	public static function starts_with( string $haystack, string $needle ): bool {
		return str_starts_with( $haystack, $needle );
	}

	/**
	 * Checks if a string ends with a given substring.
	 *
	 * Performs a case-sensitive check indicating if haystack ends with needle.
	 * Wraps PHP's str_ends_with() so third-party plugins and extensions can rely on a stable
	 * ET\Builder API instead of calling that global or re-implementing polyfills across PHP versions.
	 *
	 * @since ??
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle The substring to search for in the haystack.
	 *
	 * @return bool Returns true if haystack ends with needle, false otherwise.
	 */
	public static function ends_with( string $haystack, string $needle ): bool {
		return str_ends_with( $haystack, $needle );
	}

	/**
	 * Count characters in a string with UTF-8 awareness.
	 *
	 * Prefer mbstring (mb_strlen) when available, fall back to iconv, and finally fall back to a
	 * byte-based approach that skips UTF-8 continuation bytes.
	 *
	 * @since ??
	 *
	 * @param string $value String value.
	 *
	 * @return int Character count.
	 */
	public static function strlen_utf8( string $value ): int {
		if ( '' === $value ) {
			return 0;
		}

		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $value, 'UTF-8' );
		}

		if ( function_exists( 'iconv_strlen' ) ) {
			$iconv_length = iconv_strlen( $value, 'UTF-8' );

			if ( false !== $iconv_length ) {
				return $iconv_length;
			}
		}

		// Count characters by skipping UTF-8 continuation bytes.
		$length = strlen( $value );
		$chars  = 0;

		for ( $i = 0; $i < $length; $i++ ) {
			$byte = ord( $value[ $i ] );

			if ( $byte < 0x80 || $byte >= 0xC0 ) {
				++$chars;
			}
		}

		return $chars;
	}

	/**
	 * True when \\uXXXX decodes to a BMP private-use code point (U+E000–U+F8FF): treat as accidental text (#49457).
	 *
	 * @param int $code_point Hex-decoded XXXX from uXXXX.
	 *
	 * @return bool Whether to strip the backslash in heal_false_pua_json_escapes_in_canvas_cache_content().
	 */
	private static function is_bmp_private_use_u_escape_false_positive( int $code_point ): bool {
		return 0xE000 <= $code_point && 0xF8FF >= $code_point;
	}

	/**
	 * Strip erroneous `\\uXXXX` when XXXX is a BMP private-use code point (U+E000–U+F8FF).
	 *
	 * Used when reading `_divi_dynamic_assets_canvases_used` so mistaken sequences (e.g. `\\uEBEC` inside “Quebec”)
	 * are normalized before render. Leaves real escapes such as `\\u003c` unchanged.
	 *
	 * @since ??
	 *
	 * @param string $content Cached canvas markup from post meta.
	 *
	 * @return string Healed content.
	 */
	public static function heal_false_pua_json_escapes_in_canvas_cache_content( string $content ): string {
		if ( '' === $content ) {
			return $content;
		}

		$healed = preg_replace_callback(
			'/\\\\u([0-9a-f]{4})/i',
			function ( array $matches ): string {
				$code_point = hexdec( $matches[1] );

				if ( self::is_bmp_private_use_u_escape_false_positive( $code_point ) ) {
					return 'u' . $matches[1];
				}

				return $matches[0];
			},
			$content
		);

		return is_string( $healed ) ? $healed : $content;
	}

	/**
	 * Preserve JSON Unicode escape sequences in content for WordPress meta operations.
	 *
	 * WordPress automatically unslashes content when retrieving from the database (via get_posts(),
	 * get_post(), etc.), which strips backslashes from JSON Unicode escape sequences (e.g., \u003c -> u003c).
	 * Additionally, update_post_meta() internally calls wp_unslash() which can corrupt Unicode escapes.
	 *
	 * This function:
	 * 1. Restores backslashes to malformed Unicode escape sequences (u003c -> \u003c)
	 * 2. Applies wp_slash() to preserve escapes through update_post_meta() operations
	 *
	 * @since ??
	 *
	 * @param string $content Content that may contain malformed Unicode escape sequences.
	 *
	 * @return string Content with preserved Unicode escape sequences, ready for WordPress meta operations.
	 *
	 * @example
	 * ```php
	 * // Content retrieved from database with corrupted Unicode escapes
	 * $content = '{"value":"u003cpu003eHellou003c/pu003e"}';
	 *
	 * // Preserve Unicode escapes for caching
	 * $preserved = StringUtility::preserve_unicode_escapes_for_meta( $content );
	 * // Result: '{"value":"\\u003cp\\u003eHello\\u003c/p\\u003e"}' (slashed)
	 *
	 * // Now safe to use with update_post_meta()
	 * update_post_meta( $post_id, 'cached_content', $preserved );
	 * ```
	 */
	public static function preserve_unicode_escapes_for_meta( string $content ): string {
		if ( empty( $content ) || ! is_string( $content ) ) {
			return $content;
		}

		// Restore backslashes to JSON Unicode escape sequences that were stripped by wp_unslash.
		// Pattern: u followed by 4 hex digits (but not already preceded by backslash).
		$content = preg_replace( '/(?<!\\\\)u([0-9a-f]{4})/i', '\\\\u$1', $content );

		// update_post_meta() internally calls wp_unslash() which strips backslashes from Unicode escapes.
		// Apply wp_slash() to preserve the escapes through the update_post_meta() process.
		return wp_slash( $content );
	}
}
