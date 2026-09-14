<?php
/**
 * SVG sanitizer utility.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Svg;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * SvgSanitizer class.
 *
 * Applies a strict allowlist for inline SVG markup.
 *
 * @since ??
 */
class SvgSanitizer {
	/**
	 * Same-document fragment reference pattern for `<use>` href attributes.
	 */
	private const SAME_DOCUMENT_FRAGMENT_REFERENCE_PATTERN = '/^#[A-Za-z_][\w:.-]*$/';

	/**
	 * Tag-to-attribute map that requires same-document fragment references.
	 *
	 * @var array<string, array<int, string>>
	 */
	private const FRAGMENT_REFERENCE_ATTRIBUTES_BY_TAG = [
		'use'      => [ 'href', 'xlink:href' ],
		'textpath' => [ 'href', 'xlink:href' ],
	];

	/**
	 * Sanitize inline SVG markup.
	 *
	 * @param string $markup Raw SVG markup.
	 *
	 * @return string
	 */
	public static function sanitize_markup( string $markup ): string {
		$markup = trim( $markup );

		if ( '' === $markup || false === stripos( $markup, '<svg' ) ) {
			return '';
		}

		$sanitized_markup = wp_kses( $markup, wp_kses_array_lc( SvgAllowedList::get_allowed_svg_html() ) );

		// Apply a value-level guard for fragment reference attributes after allowlisting.
		// This prevents external/non-fragment references from surviving sanitization.
		return self::_sanitize_fragment_reference_attributes( $sanitized_markup );
	}

	/**
	 * Restrict fragment reference attributes to same-document fragments.
	 *
	 * @param string $markup Sanitized SVG markup.
	 *
	 * @return string
	 */
	private static function _sanitize_fragment_reference_attributes( string $markup ): string {
		$has_relevant_tag = false;

		foreach ( array_keys( self::FRAGMENT_REFERENCE_ATTRIBUTES_BY_TAG ) as $tag_name ) {
			if ( false !== stripos( $markup, '<' . $tag_name ) ) {
				$has_relevant_tag = true;
				break;
			}
		}

		if ( '' === $markup || ! $has_relevant_tag ) {
			return $markup;
		}

		$internal_errors = libxml_use_internal_errors( true );
		$document        = new \DOMDocument();
		$loaded          = $document->loadXML( $markup, LIBXML_NONET | LIBXML_NOBLANKS );

		if ( ! $loaded ) {
			libxml_clear_errors();
			libxml_use_internal_errors( $internal_errors );

			return $markup;
		}

		foreach ( self::FRAGMENT_REFERENCE_ATTRIBUTES_BY_TAG as $tag_name => $reference_attributes ) {
			if ( false === stripos( $markup, '<' . $tag_name ) ) {
				continue;
			}

			$nodes = $document->getElementsByTagName( $tag_name );

			if ( 0 === $nodes->length && 'textpath' === $tag_name ) {
				$nodes = $document->getElementsByTagName( 'textPath' );
			}

			for ( $index = 0; $index < $nodes->length; $index++ ) {
				$node = $nodes->item( $index );

				if ( null === $node || ! $node->hasAttributes() ) {
					continue;
				}

				$attributes_to_remove = [];

				foreach ( $node->attributes as $attribute ) {
					$attribute_name = strtolower( $attribute->nodeName );

					if ( ! in_array( $attribute_name, $reference_attributes, true ) ) {
						continue;
					}

					$attribute_value = trim( (string) $attribute->nodeValue );
					$is_fragment_ref = 1 === preg_match( self::SAME_DOCUMENT_FRAGMENT_REFERENCE_PATTERN, $attribute_value );

					if ( ! $is_fragment_ref ) {
						$attributes_to_remove[] = $attribute->nodeName;
					}
				}

				foreach ( $attributes_to_remove as $attribute_name ) {
					$node->removeAttribute( $attribute_name );
				}
			}
		}

		$serialized_markup = $document->saveXML( $document->documentElement );

		libxml_clear_errors();
		libxml_use_internal_errors( $internal_errors );

		return is_string( $serialized_markup ) ? $serialized_markup : $markup;
	}
}
