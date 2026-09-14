<?php
/**
 * Attribute Security class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Security\AttributeSecurity;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Options\Attributes\AttributeUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserUtils;

/**
 * AttributeSecurity Class.
 *
 * This class handles security for custom module attributes, ensuring they are
 * properly sanitized according to HTMLUtility whitelist regardless of user capabilities.
 *
 * @since ??
 */
class AttributeSecurity {

	/**
	 * Sanitize custom attributes on save.
	 *
	 * This function ensures that custom module attributes are properly sanitized
	 * regardless of user capabilities. Custom attributes are a security feature
	 * and should always be validated against the HTMLUtility whitelist.
	 *
	 * @since ??
	 *
	 * @param array $data  An array of slashed post data.
	 *
	 * @return array $data Modified post data.
	 */
	public static function sanitize_custom_attributes_fields( $data ) {
		// Only process if we have post content and it contains Divi blocks.
		if ( empty( $data['post_content'] ) ) {
			return $data;
		}

		if ( ! str_contains( $data['post_content'], 'wp:divi/' ) ) {
			return $data;
		}

		// Check if content contains custom attributes - account for WordPress slashing.
		$content        = wp_unslash( $data['post_content'] );
		$has_decoration = str_contains( $content, '"decoration":{' );
		$has_attributes = str_contains( $content, '"attributes":{' );

		if ( ! $has_decoration || ! $has_attributes ) {
			return $data;
		}

		// Parse blocks and sanitize custom attributes.
		$blocks = BlockParserUtils::parse_blocks_with_layout_context( $content, 'saving_content', 'sanitize_custom_attributes_fields' );
		// Note $blocks is being passed and modified by reference.
		self::_sanitize_blocks( $blocks );
		ModuleUtils::clean_blocks_empty_array_attributes( $blocks );
		$data['post_content'] = wp_slash( serialize_blocks( $blocks ) );

		return $data;
	}

	/**
	 * Recursively sanitize custom attributes in blocks and their inner blocks.
	 *
	 * @since ??
	 *
	 * @param array &$blocks Array of blocks to process (passed by reference).
	 *
	 * @return void
	 */
	private static function _sanitize_blocks( &$blocks ) {
		foreach ( $blocks as &$block ) {
			// Check if this block has custom attributes.
			if ( isset( $block['attrs']['module']['decoration']['attributes'] ) ) {
				$block['attrs']['module']['decoration']['attributes'] = self::sanitize_module_attributes(
					$block['attrs']['module']['decoration']['attributes']
				);
			}

			// Recursively process inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				self::_sanitize_blocks( $block['innerBlocks'] );
			}
		}
	}

	/**
	 * Sanitize attribute name.
	 * Validates that the attribute name exists in the HTML Utils list.
	 * Blocks event handler attributes for users without unfiltered_html capability.
	 *
	 * @since ??
	 *
	 * @param string $name The attribute name to sanitize.
	 *
	 * @return string Sanitized attribute name, or empty string if not allowed.
	 */
	public static function sanitize_attribute_name( $name ) {
		if ( ! is_string( $name ) || ! trim( $name ) ) {
			return '';
		}

		// Basic normalization only.
		$normalized = strtolower( trim( $name ) );

		// Check if the attribute exists in the HTML utility list.
		$attribute_details = HTMLUtility::get_attribute_details( $normalized );
		if ( empty( $attribute_details ) ) {
			// Attribute is not in the HTML utility list - block it.
			return '';
		}

		// Additional security: Block event handler attributes for users without unfiltered_html capability.
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$event_handlers = HTMLUtility::get_event_handler_attributes();
			if ( isset( $event_handlers[ $normalized ] ) ) {
				// User doesn't have unfiltered_html capability and this is an event handler - block it.
				return '';
			}
		}

		return $normalized;
	}

	/**
	 * Sanitize attributes data by processing each individual attribute.
	 *
	 * @since ??
	 *
	 * @param array|object $attributes_data The attributes data to sanitize.
	 *
	 * @return array|object Sanitized attributes data.
	 */
	private static function _sanitize_attributes_data( $attributes_data ) {
		// Preserve original type.
		$was_object = is_object( $attributes_data );

		// Convert to array for processing.
		if ( $was_object ) {
			$attributes_data = (array) $attributes_data;
		}

		// Handle responsive attribute structure (post content only uses this format).
		if ( isset( $attributes_data['desktop']['value']['attributes'] ) ) {
			// Responsive structure.
			$attributes_data['desktop']['value']['attributes'] = self::_sanitize_attributes_list(
				$attributes_data['desktop']['value']['attributes']
			);
		}

		// Convert back to object if it was originally an object.
		if ( $was_object ) {
			$attributes_data = (object) $attributes_data;
		}

		return $attributes_data;
	}

	/**
	 * Sanitize a list of attributes.
	 *
	 * @since ??
	 *
	 * @param array $attributes_list The list of attributes to sanitize.
	 *
	 * @return array Sanitized attributes list.
	 */
	private static function _sanitize_attributes_list( $attributes_list ) {
		if ( ! is_array( $attributes_list ) ) {
			return [];
		}

		$sanitized_attributes = [];

		foreach ( $attributes_list as $attribute ) {
			if ( ! is_array( $attribute ) && ! is_object( $attribute ) ) {
				continue;
			}

			// Convert to array if needed.
			if ( is_object( $attribute ) ) {
				$attribute = (array) $attribute;
			}

			// Get attribute details.
			$name           = $attribute['name'] ?? '';
			$value          = $attribute['value'] ?? '';
			$target_element = $attribute['targetElement'] ?? '';

			// Skip if no name.
			if ( empty( $name ) ) {
				continue;
			}

			// Sanitize the attribute name.
			$sanitized_name = self::sanitize_attribute_name( $name );

			// Skip if name didn't pass sanitization.
			if ( empty( $sanitized_name ) ) {
				continue;
			}

			// Sanitize the attribute value.
			$sanitized_value = self::_sanitize_attribute_value( $value, $sanitized_name );

			// Skip only if value didn't pass sanitization (returned null).
			// Preserve all other values including:
			// - Empty string '' (for stripped JavaScript URLs - security audit trail)
			// - String '0' and other falsy values (valid attribute values).
			if ( null === $sanitized_value ) {
				continue;
			}

			// Preserve the original structure and only update sanitized fields.
			$sanitized_attribute          = $attribute;
			$sanitized_attribute['name']  = $sanitized_name;
			$sanitized_attribute['value'] = $sanitized_value;

			$sanitized_attributes[] = $sanitized_attribute;
		}

		return $sanitized_attributes;
	}

	/**
	 * Sanitize custom attributes data.
	 *
	 * @param array $attributes_data The attributes data to sanitize.
	 *
	 * @return array Sanitized attributes data.
	 */
	public static function sanitize_module_attributes( $attributes_data ) {
		if ( ! is_array( $attributes_data ) && ! is_object( $attributes_data ) ) {
			return $attributes_data;
		}

		// Convert to array for processing.
		if ( is_object( $attributes_data ) ) {
			$attributes_data = (array) $attributes_data;
		}

		// Sanitize the existing structure.
		return self::_sanitize_attributes_data( $attributes_data );
	}

	/**
	 * Sanitize an attribute value.
	 *
	 * This method handles comprehensive sanitization of attribute values including:
	 * - URL sanitization for attributes that expect URLs (based on HTMLUtility sanitizer)
	 * - Basic text field sanitization for other attributes
	 * - JavaScript URL stripping for users without unfiltered_html capability
	 *
	 * @since ??
	 *
	 * @param mixed  $value The attribute value to sanitize.
	 * @param string $name  The attribute name to determine appropriate sanitization method.
	 *
	 * @return string The sanitized attribute value.
	 */
	private static function _sanitize_attribute_value( $value, $name = '' ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		// Get attribute details from HTMLUtility to determine appropriate sanitizer.
		$sanitized_value = $value;
		if ( ! empty( $name ) ) {
			$normalized_name   = strtolower( trim( $name ) );
			$attribute_details = HTMLUtility::get_attribute_details( $normalized_name );

			// Check if the attribute should use URL sanitization.
			if ( ! empty( $attribute_details['sanitizer'] ) && 'esc_url' === $attribute_details['sanitizer'] ) {
				$sanitized_value = esc_url_raw( $value );
			} else {
				$sanitized_value = sanitize_text_field( $value );
			}
		} else {
			// Fallback to basic text field sanitization if no attribute name provided.
			$sanitized_value = sanitize_text_field( $value );
		}

		// Additional security: Strip javascript: URLs for users without unfiltered_html capability.
		if ( ! current_user_can( 'unfiltered_html' ) && ! empty( $sanitized_value ) ) {
			$sanitized_value = self::_strip_javascript_urls( $sanitized_value );
		}

		return $sanitized_value;
	}

	/**
	 * Strip javascript: URLs from attribute values.
	 *
	 * This method provides additional security by removing javascript: URLs from attribute values
	 * when users don't have unfiltered_html capability. It handles various encoding and bypass attempts.
	 *
	 * @since ??
	 *
	 * @param string $value The attribute value to sanitize.
	 *
	 * @return string The sanitized attribute value with javascript: URLs removed.
	 */
	private static function _strip_javascript_urls( $value ) {
		if ( ! is_string( $value ) || empty( $value ) ) {
			return $value;
		}

		// Decode common URL and HTML encodings first to catch encoded bypass attempts.
		$decoded_value = html_entity_decode( urldecode( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Remove various javascript: URL patterns (case-insensitive).
		// This handles sophisticated bypass attempts including.
		// - Basic javascript: URLs.
		// - Whitespace insertion between characters.
		// - URL-encoded characters (%20, %09, etc.).
		// - HTML entity encoding (&#x20;, &#32;, etc.).
		// - Mixed case attempts.
		// - Various other obfuscation techniques.
		$patterns = [
			// Comprehensive pattern that handles all whitespace injection attempts between any characters.
			// This covers: javascript:, java script:, j a v a s c r i p t:, and any other spacing variations.
			'/^\s*j\s*a\s*v\s*a\s*s\s*c\s*r\s*i\s*p\s*t\s*:/i',
		];

		// Apply patterns to both original and decoded values for maximum security.
		$values_to_check = [ $value, $decoded_value ];

		foreach ( $values_to_check as $check_value ) {
			foreach ( $patterns as $pattern ) {
				if ( preg_match( $pattern, $check_value ) ) {
					// If any pattern matches, return empty string (completely remove the value).
					return '';
				}
			}
		}

		// If no javascript: patterns found, return the original value.
		return $value;
	}
}
