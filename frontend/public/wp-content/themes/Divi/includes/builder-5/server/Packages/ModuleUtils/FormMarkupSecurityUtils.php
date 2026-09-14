<?php
/**
 * Module utils: Form markup security utils.
 *
 * @package Builder\Packages\ModuleUtils
 * @since ??
 */

namespace ET\Builder\Packages\ModuleUtils;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Utility\HTMLUtility;

/**
 * Shared utilities for safe regex-based form-markup transforms.
 *
 * @since ??
 */
class FormMarkupSecurityUtils {

	/**
	 * Normalizes class names from raw class attribute text.
	 *
	 * @since ??
	 *
	 * @param string $raw_class_names Raw class attribute value.
	 *
	 * @return array<int, string>
	 */
	public static function normalize_class_names( string $raw_class_names ): array {
		$class_tokens = preg_split( '/\s+/', trim( $raw_class_names ) );

		if ( ! is_array( $class_tokens ) ) {
			return [];
		}

		return self::sanitize_class_tokens( $class_tokens );
	}

	/**
	 * Sanitizes class tokens and removes invalid or duplicate values.
	 *
	 * @since ??
	 *
	 * @param array<int, string> $classes Class tokens.
	 *
	 * @return array<int, string>
	 */
	public static function sanitize_class_tokens( array $classes ): array {
		$sanitized_classes = [];
		$seen              = [];

		foreach ( $classes as $class_name ) {
			if ( ! is_string( $class_name ) ) {
				continue;
			}

			$sanitized = sanitize_html_class( trim( $class_name ) );

			if ( '' === $sanitized || isset( $seen[ $sanitized ] ) ) {
				continue;
			}

			$seen[ $sanitized ]  = true;
			$sanitized_classes[] = $sanitized;
		}

		return $sanitized_classes;
	}

	/**
	 * Builds a KSES allowlist for an HTML element from centralized HTMLUtility definitions.
	 *
	 * @since ??
	 *
	 * @param string $element              Element name (for example `input` or `button`).
	 * @param bool   $allow_event_handlers Whether to allow inline event handler attributes.
	 *
	 * @return array<string, bool>
	 */
	public static function get_allowed_attributes_for_element_for_kses( string $element, bool $allow_event_handlers = false ): array {
		static $cache = [];

		$element = strtolower( trim( $element ) );

		if ( '' === $element ) {
			return [];
		}

		$cache_key = $element . '|' . ( $allow_event_handlers ? '1' : '0' );

		if ( isset( $cache[ $cache_key ] ) && is_array( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		$allowed_attributes = [];

		foreach ( HTMLUtility::get_all_attributes() as $attribute_name => $attribute_details ) {
			$elements = $attribute_details['elements'] ?? [];

			if ( ! is_array( $elements ) || [] === $elements || in_array( $element, $elements, true ) ) {
				$allowed_attributes[ $attribute_name ] = true;
			}
		}

		if ( ! $allow_event_handlers ) {
			foreach ( array_keys( HTMLUtility::get_event_handler_attributes() ) as $attribute_name ) {
				unset( $allowed_attributes[ $attribute_name ] );
			}
		}

		$cache[ $cache_key ] = $allowed_attributes;

		return $allowed_attributes;
	}

	/**
	 * Gets allowed button attributes for KSES sanitization.
	 *
	 * @since ??
	 *
	 * @return array<string, bool>
	 */
	public static function get_allowed_button_attributes_for_kses(): array {
		static $allowed_button_attributes = null;

		if ( is_array( $allowed_button_attributes ) ) {
			return $allowed_button_attributes;
		}

		$allowed_button_attributes         = self::get_allowed_attributes_for_element_for_kses( 'button' );
		$allowed_button_attributes['type'] = true;

		return $allowed_button_attributes;
	}

	/**
	 * Gets allowed input attributes for KSES sanitization.
	 *
	 * @since ??
	 *
	 * @param bool $allow_event_handlers Whether to allow inline event handler attributes.
	 *
	 * @return array<string, bool>
	 */
	public static function get_allowed_input_attributes_for_kses( bool $allow_event_handlers ): array {
		$allowed_input_attributes          = self::get_allowed_attributes_for_element_for_kses( 'input', $allow_event_handlers );
		$allowed_input_attributes['type']  = true;
		$allowed_input_attributes['value'] = true;

		return $allowed_input_attributes;
	}
}
