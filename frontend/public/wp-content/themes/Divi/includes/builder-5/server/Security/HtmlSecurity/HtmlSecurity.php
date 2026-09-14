<?php
/**
 * HTML Security class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Security\HtmlSecurity;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\FrontEnd\BlockParser\BlockParserUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;

/**
 * HTML Security class.
 *
 * Sanitizes HTML Before/After fields and validates Element Type field on save.
 * HTML Before/After are sanitized using wp_kses_post based on user capabilities (like code module).
 * Element Type is validated against whitelist and defaults to 'div' if invalid.
 *
 * @since ??
 */
class HtmlSecurity {
	/**
	 * All valid HTML5 element types for module wrappers.
	 * This is a security whitelist to prevent XSS attacks via malicious element types.
	 * Includes semantic elements, text containers, form elements, and other valid HTML5 container elements.
	 *
	 * @since ??
	 *
	 * @var array<string>
	 */
	private const VALID_HTML_ELEMENT_TYPES = [
		'a',
		'article',
		'address',
		'aside',
		'button',
		'details',
		'div',
		'fieldset',
		'figcaption',
		'figure',
		'footer',
		'header',
		'legend',
		'li',
		'main',
		'mark',
		'menu',
		'nav',
		'p',
		'section',
		'search',
		'summary',
		'ul',
	];
	/**
	 * Sanitize HTML Before/After fields and validate Element Type on save.
	 *
	 * HTML Before/After: Users with `unfiltered_html` capability can save any HTML.
	 * Users without it will have their HTML sanitized via wp_kses_post (like code module).
	 *
	 * Element Type: Validated against whitelist, defaults to 'div' if invalid.
	 *
	 * @since ??
	 *
	 * @param array $data An array of slashed post data.
	 *
	 * @return array $data Modified post data.
	 */
	public static function sanitize_html_fields( $data ) {
		// Only process if we have post content and it contains Divi blocks.
		if ( empty( $data['post_content'] ) ) {
			return $data;
		}

		if ( ! str_contains( $data['post_content'], 'wp:divi/' ) ) {
			return $data;
		}

		// Check if content contains HTML fields - account for WordPress slashing.
		$content          = wp_unslash( $data['post_content'] );
		$has_html_before  = str_contains( $content, '"htmlBefore"' );
		$has_html_after   = str_contains( $content, '"htmlAfter"' );
		$has_element_type = str_contains( $content, '"elementType"' );

		if ( ! $has_html_before && ! $has_html_after && ! $has_element_type ) {
			return $data;
		}

		// Parse blocks and sanitize HTML fields and validate Element Type.
		$blocks = BlockParserUtils::parse_blocks_with_layout_context( $content, 'saving_content', 'sanitize_html_fields' );
		// Note $blocks is being passed and modified by reference.
		self::_sanitize_blocks( $blocks );
		ModuleUtils::clean_blocks_empty_array_attributes( $blocks );
		$data['post_content'] = wp_slash( serialize_blocks( $blocks ) );

		return $data;
	}

	/**
	 * Recursively sanitize HTML Before/After fields and validate Element Type in blocks and their inner blocks.
	 *
	 * @since ??
	 *
	 * @param array &$blocks Array of blocks to process (passed by reference).
	 *
	 * @return void
	 */
	private static function _sanitize_blocks( &$blocks ) {
		foreach ( $blocks as &$block ) {
			// Check if this block has HTML fields.
			if ( isset( $block['attrs']['module']['advanced']['html'] ) ) {
				$html_fields = &$block['attrs']['module']['advanced']['html'];

				// Sanitize HTML Before (raw HTML - sanitize like code module).
				if ( isset( $html_fields['htmlBefore']['desktop']['value'] ) ) {
					$html_fields['htmlBefore']['desktop']['value'] = self::_sanitize_html_value(
						$html_fields['htmlBefore']['desktop']['value']
					);
				}

				// Sanitize HTML After (raw HTML - sanitize like code module).
				if ( isset( $html_fields['htmlAfter']['desktop']['value'] ) ) {
					$html_fields['htmlAfter']['desktop']['value'] = self::_sanitize_html_value(
						$html_fields['htmlAfter']['desktop']['value']
					);
				}

				// Validate Element Type (string validation against whitelist).
				if ( isset( $html_fields['elementType']['desktop']['value'] ) ) {
					$html_fields['elementType']['desktop']['value'] = self::_sanitize_element_type(
						$html_fields['elementType']['desktop']['value']
					);
				}
			}

			// Recursively process inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				self::_sanitize_blocks( $block['innerBlocks'] );
			}
		}
	}

	/**
	 * Sanitize HTML value based on user capabilities.
	 *
	 * Users with `unfiltered_html` capability can save any HTML.
	 * Users without it will have their HTML sanitized via wp_kses_post (like code module).
	 *
	 * @since ??
	 *
	 * @param string $html_value The HTML value to sanitize.
	 *
	 * @return string Sanitized HTML value.
	 */
	private static function _sanitize_html_value( $html_value ) {
		if ( ! is_string( $html_value ) || empty( $html_value ) ) {
			return $html_value;
		}

		// Users with unfiltered_html capability can save any HTML.
		if ( current_user_can( 'unfiltered_html' ) ) {
			return $html_value;
		}

		// Sanitize HTML using wp_kses_post (like code module).
		return wp_kses_post( $html_value );
	}

	/**
	 * Sanitize element type by validating against whitelist.
	 *
	 * Validates the element type string against the whitelist and defaults to 'div' if invalid.
	 *
	 * @since ??
	 *
	 * @param string $element_type The element type to sanitize.
	 *
	 * @return string Sanitized element type (validated against whitelist, defaults to 'div' if invalid).
	 */
	private static function _sanitize_element_type( $element_type ) {
		if ( ! is_string( $element_type ) || empty( $element_type ) ) {
			return 'div';
		}

		// Normalize element type (trim and lowercase).
		$normalized_element_type = null;
		if ( is_string( $element_type ) && ! empty( $element_type ) ) {
			$normalized_element_type = strtolower( trim( $element_type ) );
		}

		// If normalization resulted in empty string, default to 'div'.
		if ( empty( $normalized_element_type ) ) {
			return 'div';
		}

		// Validate element type against whitelist.
		$sanitized_element_type = self::_sanitize_element_type_against_whitelist( $normalized_element_type );
		if ( null === $sanitized_element_type ) {
			// Invalid element type - default to 'div'.
			return 'div';
		}

		return $sanitized_element_type;
	}

	/**
	 * Check if an element type is valid for use as a module wrapper.
	 * Validates against the whitelist of allowed HTML5 elements.
	 *
	 * @since ??
	 *
	 * @param string $element_type The element type to validate.
	 *
	 * @return bool True if the element type is valid, false otherwise.
	 */
	private static function _is_valid_element_type( $element_type ) {
		if ( ! is_string( $element_type ) || ! trim( $element_type ) ) {
			return false;
		}

		$normalized_type = strtolower( trim( $element_type ) );

		// Reject empty or whitespace-only types.
		if ( ! $normalized_type ) {
			return false;
		}

		// Check against the whitelist of valid HTML element types.
		return in_array( $normalized_type, self::VALID_HTML_ELEMENT_TYPES, true );
	}

	/**
	 * Sanitize element type by validating against whitelist.
	 * Returns the normalized element type if valid, or null if invalid.
	 *
	 * @since ??
	 *
	 * @param string $element_type The element type to sanitize.
	 *
	 * @return string|null Sanitized element type, or null if invalid.
	 */
	private static function _sanitize_element_type_against_whitelist( $element_type ) {
		if ( ! self::_is_valid_element_type( $element_type ) ) {
			return null;
		}

		return strtolower( trim( $element_type ) );
	}

	/**
	 * Get all valid HTML element types.
	 *
	 * @since ??
	 *
	 * @return array<string> Array of valid HTML element type names.
	 */
	public static function get_valid_element_types() {
		return self::VALID_HTML_ELEMENT_TYPES;
	}
}
