<?php
/**
 * Workspace payload sanitizer utility.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\Workspace;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Utility class for deep sanitization of workspace payload arrays.
 *
 * @since ??
 */
class WorkspacePayloadSanitizer {
	/**
	 * Maximum depth for nested payload sanitization.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	private static $_max_depth = 20;

	/**
	 * Deep sanitize nested workspace payload array values.
	 *
	 * @since ??
	 *
	 * @param array $value Nested workspace value.
	 * @param int   $depth Current traversal depth.
	 *
	 * @return array
	 */
	public static function sanitize_nested_array( array $value, int $depth = 0 ): array {
		if ( self::$_max_depth <= $depth ) {
			return [];
		}

		$sanitized = [];

		foreach ( $value as $key => $item ) {
			$sanitized_value = self::sanitize_nested_value( $item, $depth + 1 );

			if ( null === $sanitized_value ) {
				continue;
			}

			if ( is_string( $key ) ) {
				$sanitized_key = sanitize_text_field( $key );

				if ( '' === $sanitized_key ) {
					continue;
				}

				$sanitized[ $sanitized_key ] = $sanitized_value;
				continue;
			}

			$sanitized[ $key ] = $sanitized_value;
		}

		return $sanitized;
	}

	/**
	 * Sanitize a nested workspace payload value.
	 *
	 * @since ??
	 *
	 * @param mixed $value Nested workspace value.
	 * @param int   $depth Current traversal depth.
	 *
	 * @return mixed
	 */
	public static function sanitize_nested_value( $value, int $depth = 0 ) {
		if ( is_array( $value ) ) {
			return self::sanitize_nested_array( $value, $depth );
		}

		if ( is_string( $value ) ) {
			return sanitize_text_field( $value );
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		if ( is_bool( $value ) ) {
			return rest_sanitize_boolean( $value );
		}

		return null;
	}
}
