<?php
/**
 * Utilities for validating global variable ids and CSS references.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\StyleLibrary\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Utility class for strict global variable reference validation.
 *
 * @since ??
 */
class GlobalVariableReferenceUtils {

	/**
	 * Sanitize and validate global variable id by prefix.
	 *
	 * @since ??
	 *
	 * @param mixed  $variable_id Candidate variable id.
	 * @param string $prefix      Allowed id prefix. Defaults to `gvid`.
	 *
	 * @return string
	 */
	public static function sanitize_variable_id( $variable_id, string $prefix = 'gvid' ): string {
		if ( ! is_scalar( $variable_id ) || '' === $prefix ) {
			return '';
		}

		$sanitized_id = wp_check_invalid_utf8( (string) $variable_id );
		$sanitized_id = wp_kses_no_null( $sanitized_id );
		$sanitized_id = wp_strip_all_tags( $sanitized_id );
		$sanitized_id = trim( $sanitized_id );

		$escaped_prefix = preg_quote( $prefix, '/' );

		// Regex test: https://regex101.com/r/VgZYJ1/2.
		return 1 === preg_match( "/^{$escaped_prefix}-[a-z0-9\\-]+$/i", $sanitized_id ) ? $sanitized_id : '';
	}

	/**
	 * Sanitize and validate strict CSS variable reference by prefix.
	 *
	 * @since ??
	 *
	 * @param mixed  $value  Candidate CSS variable reference.
	 * @param string $prefix Allowed id prefix. Defaults to `gvid`.
	 *
	 * @return string
	 */
	public static function sanitize_css_reference( $value, string $prefix = 'gvid' ): string {
		if ( ! is_scalar( $value ) || '' === $prefix ) {
			return '';
		}

		$sanitized_value = wp_check_invalid_utf8( (string) $value );
		$sanitized_value = wp_kses_no_null( $sanitized_value );
		$sanitized_value = wp_strip_all_tags( $sanitized_value );
		$sanitized_value = trim( $sanitized_value );

		$escaped_prefix = preg_quote( $prefix, '/' );

		// Regex test: https://regex101.com/r/lDDGKJ/3.
		return 1 === preg_match( "/^var\\(--{$escaped_prefix}-[a-z0-9\\-]+\\)$/i", $sanitized_value ) ? $sanitized_value : '';
	}

	/**
	 * Resolve dynamic/global image variable tokens and validate strict CSS reference output.
	 *
	 * @since ??
	 *
	 * @param mixed  $value  Candidate variable token.
	 * @param string $prefix Allowed id prefix. Defaults to `gvid`.
	 *
	 * @return string
	 */
	public static function resolve_and_sanitize_image_css_reference( $value, string $prefix = 'gvid' ): string {
		if ( ! is_scalar( $value ) || '' === $prefix ) {
			return '';
		}

		$value = (string) $value;
		if ( ! str_contains( $value, 'var(' ) && ! Utils::is_global_image_variable( $value ) ) {
			return '';
		}

		$resolved_value = Utils::resolve_dynamic_variable( $value );

		return self::sanitize_css_reference( $resolved_value, $prefix );
	}
}
