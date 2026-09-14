<?php
/**
 * Module: DynamicData main class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Layout\Components\DynamicData;

use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentUtils;
use ET\Builder\Packages\Shortcode\ShortcodeUtils;
use ET\Builder\Packages\StyleLibrary\Utils\Utils;
use ET\Builder\Framework\Utility\StringUtility;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Module: DynamicData class.
 *
 * Dynamic data is a special type of dynamic value that can be used in the content.
 * It can be `content`, `color`, `preset`, etc. The dynamic data is identified and wrapped
 * in `$variables()` format. At this moment, it only process `content` type value and
 * it's called dynamic content.
 *
 * {@see ET\Builder\Packages\Module\Layout\Components\DynamicContent}
 *
 * This class handle dynamic data processing. This includes:
 * - Extracting the `$variables()` from the given content.
 * - Converting variables to data values.
 * - Processing the dynamic data based on the type (i.e. `content`).
 * - Replacing the `$variables()` with the processed dynamic data.
 *
 * @since ??
 */
class DynamicData {

	/**
	 * Retrieves the data value based on the given string value.
	 *
	 * This function takes a string value, decodes it from JSON format,
	 * and returns it as an associative array. Any escaped double quotes
	 * ("\u0022") in the string value are replaced with actual double quotes
	 * before decoding.
	 *
	 * @since ??
	 *
	 * @param string $string_value The string value to be decoded.
	 *
	 * @return array The decoded data value. If the decoded value is not an array,
	 *               an empty array is returned.
	 *
	 * @example:
	 * ```php
	 * // Decode a JSON string value with unescaped double quotes
	 * $string_value = '{"type":"content", "value":{"name":"site_title"}}';
	 * $data_value = DynamicData::get_data_value($string_value);
	 * print_r( $data_value );
	 * ```
	 *
	 * @output:
	 * ```php
	 *  [
	 *    "type" => "content",
	 *    "value" => [
	 *      "name" => "site_title"
	 *    ]
	 *  ]
	 * ```
	 *
	 * @example:
	 * ```php
	 * // Decode a JSON string value with escaped double quotes
	 * $string_value = '{\\u0022type\\u0022:\\u0022content\\u0022,\\u0022value\\u0022:{\\u0022name\\u0022:\\u0022site_title\\u0022}}';
	 * $data_value = DynamicData::get_data_value($string_value);
	 * print_r( $data_value );
	 * ```
	 *
	 * @output:
	 * ```php
	 *  [
	 *    "type" => "content",
	 *    "value" => [
	 *      "name" => "site_title"
	 *    ]
	 *  ]
	 * ```
	 *
	 * @example:
	 * ```php
	 * // Decode a JSON string value with escaped single quotes (invalid JSON string)
	 * $string_value = '{\\u0027type\\u0027:\\u0027content\\u0027,\\u0027value\\u0027:{\\u0027name\\u0027:\\u0027site_title\\u0027}}';
	 * $data_value = DynamicData::get_data_value($string_value);
	 * print_r( $data_value );
	 * ```
	 *
	 * @output:
	 * ```php
	 *  []
	 * ```
	 */
	public static function get_data_value( string $string_value ): array {
		$json_value = self::construct_json_string( $string_value );

		$data_value = json_decode( $json_value, true );

		if ( ! is_array( $data_value ) ) {
			// Some serialized dynamic data values arrive with escaped Unicode quotes/backslashes.
			// Unslashing makes the JSON readable before the second decode attempt.
			$data_value = json_decode( wp_unslash( $json_value ), true );
		}

		return is_array( $data_value ) ? $data_value : [];
	}

	/**
	 * Process HTML quote entities more carefully to avoid breaking HTML content.
	 *
	 * Uses a simple heuristic: if the string contains HTML tags, be conservative.
	 * Otherwise, do complete replacement.
	 *
	 * @since ??
	 *
	 * @param string $string_value The string value to process.
	 * @return string The processed string with replaced HTML quote entities.
	 */
	public static function process_html_quote_entities( string $string_value ): string {

		// Simple check: if string contains HTML tags, be conservative.
		if ( str_contains( $string_value, '<' ) && str_contains( $string_value, '>' ) ) {
			// Contains HTML - use safe patterns only.
			return self::replace_safe_structural_quotes( $string_value );
		}

		// No HTML detected - safe to replace all &quot; entities.
		return str_replace( '&quot;', '"', $string_value );
	}

	/**
	 * Replace &quot; entities using conservative patterns that won't affect content.
	 *
	 * This method uses only the safest patterns that are very unlikely to match
	 * within JSON string content values.
	 *
	 * @since ??
	 *
	 * @param string $string_value The JSON string to process.
	 * @return string The processed string with safe structural quotes replaced.
	 */
	public static function replace_safe_structural_quotes( string $string_value ): string {
		// Only use the safest patterns that are very unlikely to match within content.
		$safe_patterns = [
			'/\{&quot;/' => '{"',    // After opening brace.
			'/&quot;:/'  => '":',    // Before colon (JSON key).
			'/&quot;\}/' => '"}',    // Before closing brace.
		];

		foreach ( $safe_patterns as $pattern => $replacement ) {
			$string_value = preg_replace( $pattern, $replacement, $string_value );
		}

		return $string_value;
	}

	/**
	 * Construct JSON string by replacing Unicode escape double quotes.
	 *
	 * - Replaces `\\u0022:{` with `":{`.
	 * - Replaces `\\u0022:[` with `":[`.
	 * - Replaces `\\u0022:\\u0022` with `":"`.
	 * - Replaces `\\u0022,\\u0022` with `","`.
	 * - Replaces `{\\u0022` with `{"`.
	 * - Replaces `\\u0022}` with `"}`.
	 * - Carefully replaces `&quot;` with `"` only when it's part of JSON structure.
	 *
	 * @since ??
	 *
	 * @param string $string_value The string value to process.
	 * @return string The processed string with replaced Unicode escape double quotes.
	 */
	public static function construct_json_string( string $string_value ): string {
		// Restore JSON-escaped quotes inside nested string values (e.g. upload sub-field JSON)
		// before normalizing structural \u0022 sequences. Block serialization encodes inner \"
		// as \u005c\u0022.
		$string_value = str_replace( '\\u005c\\u0022', '\\"', $string_value );

		$string_value = str_replace( '\\u0022:{', '":{', $string_value );
		$string_value = str_replace( '\\u0022:[', '":[', $string_value );
		$string_value = str_replace( '\\u0022:\\u0022', '":"', $string_value );
		$string_value = str_replace( '\\u0022,\\u0022', '","', $string_value );
		$string_value = str_replace( '{\\u0022', '{"', $string_value );
		$string_value = str_replace( '\\u0022}', '"}', $string_value );

		// More restrictive condition - only allow if we're confident it's JSON structure.
		if ( str_contains( $string_value, '&quot;' ) ) {
			// Only process if it looks like actual JSON with structural patterns.
			if ( preg_match( '/\{.*&quot;.*\}/', $string_value ) ) {
				return self::process_html_quote_entities( $string_value );
			}
		}
		return $string_value; // No processing if uncertain.
	}

	/**
	 * Get processed dynamic data.
	 *
	 * Proceessing dynamic data includes:
	 * - Extracting the `$variables()` from the given content.
	 * - Cache the resolved value to be used later for the same variables.
	 * - Converting variables to data values.
	 * - Processing the dynamic data based on the type (i.e. `content`).
	 * - Replacing the `$variables()` with the processed dynamic data.
	 *
	 * If `$serialize=true` the following will be done:
	 * - any `--` will be replaced with `\u002d\u002d`.
	 * - any `<` will be replaced with `\u003c`.
	 * - any `>` will be replaced with `\u003e`.
	 * - any `&` will be replaced with `\u0026`.
	 * - any `"` will be replaced with `\u0022`.
	 *
	 * This function can currently only process dynamic content type.
	 *
	 * @since ??
	 *
	 * @param string      $content          Content to process.
	 * @param int|null    $post_id          Optional. The post ID. Default `null`.
	 * @param bool        $serialize        Optional. Flag to serialize the resolved value. Default `false`.
	 * @param int|null    $loop_id          Optional. The loop post ID for loop context. Default `null`.
	 * @param string|null $loop_query_type  Optional. The loop query type. Default `null`.
	 * @param object      $loop_object      Optional. The loop object (WP_Post, WP_User, WP_Term, etc.). Default `null`.
	 * @param array|null  $request_context  Optional. The request context from the Visual Builder. Default `null`.
	 *
	 * @return string|null Processed dynamic data.
	 */
	public static function get_processed_dynamic_data(
		string $content,
		?int $post_id = null,
		bool $serialize = false,
		?int $loop_id = null,
		?string $loop_query_type = null,
		$loop_object = null,
		?array $request_context = null
	): ?string {
		static $cache = [];

		// Bail early if no dynamic data `$variable` found.
		if ( ! str_contains( $content, '$variable(' ) ) {
			return $content;
		}

		$string_values = self::get_variable_values( $content );

		foreach ( $string_values as $string_value ) {
			$resolved_value = null;

			// Create composite cache key for loop contexts to avoid collisions.
			$cache_key = $string_value;
			if ( null !== $loop_id && $loop_query_type ) {
				// Extract pagination parameters from $_GET for loop contexts.
				$pagination_params = [];
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL parameter extraction for cache key, no security risk.
				if ( isset( $_GET ) && is_array( $_GET ) ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- URL parameter extraction for cache key, no security risk.
					foreach ( $_GET as $param => $value ) {
						// Check if parameter looks like a loop pagination parameter (starts with 'loop-' and has numeric value > 1).
						if ( is_string( $param ) && str_starts_with( $param, 'loop-' ) && is_numeric( $value ) && (int) $value > 1 ) {
							$pagination_params[ $param ] = (int) $value;
						}
					}
				}
				// Sort pagination parameters by key for consistent cache key generation.
				ksort( $pagination_params );
				// Create MD5 hash of sorted pagination parameters.
				$pagination_key = ! empty( $pagination_params ) ? md5(
					implode(
						'|',
						array_map(
							function ( $k, $v ) {
								return $k . '=' . $v;
							},
							array_keys( $pagination_params ),
							$pagination_params
						)
					)
				) : '';
				$cache_key      = $string_value . '|' . $loop_id . '|' . $loop_query_type . ( ! empty( $pagination_key ) ? '|' . $pagination_key : '' );
			} elseif ( null !== $post_id ) {
				$cache_key = $string_value . '|' . $post_id . '|post';
			}

			if (
				isset( $cache[ $cache_key ] ) &&
				null === $loop_id &&
				! StringUtility::starts_with( $loop_query_type ?? '', 'repeater' )
			) {
				// Use cached resolved value just in case this function is being called again
				// and the same variables exist.
				$resolved_value = $cache[ $cache_key ];
			} else {
				$data_value = self::get_data_value( $string_value );
				$type       = $data_value['type'] ?? '';
				$value      = $data_value['value'] ?? [];
				$name       = $value['name'] ?? '';

				if ( $post_id && ! isset( $value['post_id'] ) ) {
					$value['post_id'] = $post_id;
				}

				// Customizer fonts saved as css variables already. Just keep it.
				if ( ! empty( $name ) && in_array( $name, [ '--et_global_body_font', '--et_global_heading_font' ], true ) ) {
					$resolved_value = sprintf( 'var(%s)', $name );
				} elseif ( 'content' === $type ) {
					// Currently only process `content` type for Dynamic Content.
					$resolved_value = DynamicContentUtils::get_processed_dynamic_content( $value, $loop_id, $loop_query_type, $loop_object, $request_context );
				} elseif ( 'shortcode' === $type ) {
					// Process raw shortcode content with the correct post context.
					// Used by the Visual Builder when loop items contain shortcodes in their content fields.
					// Match ModuleElements / DynamicContentUtils: run embed preprocessing before do_shortcode().
					$shortcode_content = ShortcodeUtils::get_processed_embed_shortcode( (string) ( $value['content'] ?? '' ) );
					$shortcode_post_id = isset( $value['post_id'] ) ? (int) $value['post_id'] : (int) $post_id;

					if ( $shortcode_post_id > 0 ) {
						$resolved_value = DynamicContentUtils::with_loop_post_context(
							$shortcode_post_id,
							function () use ( $shortcode_content ) {
								return do_shortcode( $shortcode_content );
							}
						);
					} else {
						$resolved_value = do_shortcode( $shortcode_content );
					}
				} elseif ( 'color' === $type ) {
					// Process color type dynamic data using StyleLibrary Utils.
					// Pass post ID as global context for ACF resolution.
					global $et_dynamic_data_post_id;
					$et_dynamic_data_post_id = $post_id;
					$resolved_value          = Utils::resolve_dynamic_variable( '$variable(' . wp_json_encode( $data_value ) . ')$' );
					unset( $GLOBALS['et_dynamic_data_post_id'] );
				}

				// Serialize the resolved value if required.
				if ( $serialize && null !== $resolved_value ) {
						// Serialize the resolved value using serialize_block_attributes function to ensure
						// that characters potentially interfering with block attributes parsing are escaped.
						$serialized = serialize_block_attributes( [ 'value' => $resolved_value ] );

						// Extract the serialized resolved value by trimming specific parts:
						// - Remove the first 10 characters (`{"value":"`).
						// - Remove the last 2 characters (`"}`).
						$resolved_value = substr( $serialized, 10, -2 );
				}

				$resolved_value_args = [
					'type'    => $type,
					'value'   => $value,
					'content' => $content,
				];

				/**
				 * Filter dynamic data resolved value to resolve based on provided value and arguments.
				 *
				 * @since ??
				 *
				 * @param string $resolved_value      Dynamic data resolved value.
				 * @param array  $resolved_value_args {
				 *     An array of arguments.
				 *
				 *     @type string $type    Dynamic data type i.e. `content`.
				 *     @type array  $value   Dynamic data value before processed.
				 *     @type string $content Post content or document (blocks).
				 * }
				 */
				$resolved_value = apply_filters( 'divi_module_dynamic_data_resolved_value', $resolved_value, $resolved_value_args );
			}

			// Replace the variable string with the resolved value. We should not cache `null`
			// value as well to anticipate the dynamic data value is intentionally set to `null`
			// to skip or repeat the dynamic data resolving process.
			if ( null !== $resolved_value ) {
				$cache[ $cache_key ] = $resolved_value;
				$content             = str_replace( '$variable(' . $string_value . ')$', $resolved_value, $content );
			}
		}

		return $content;
	}

	/**
	 * Get dynamic data variable values based on the given content.
	 *
	 * This function uses regex to find the variables value in the given content.
	 * {@link https://regex101.com/r/534mcR/1 Regex101}
	 *
	 * @since ??
	 *
	 * @param string $content Content to search for variables.
	 *
	 * @return array Matched variable values.
	 */
	public static function get_variable_values( string $content ): array {
		preg_match_all( '/\$variable\((.+?)\)\$/', $content, $variable_matches );

		return $variable_matches[1];
	}
}
