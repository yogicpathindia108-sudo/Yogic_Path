<?php
/**
 * Object fit style declaration.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\StyleLibrary\Declarations\ObjectFit;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;

/**
 * ObjectFit helper class.
 *
 * @since ??
 */
class ObjectFit {
	/**
	 * Generate object-fit and object-position style declarations.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array      $attrValue  The value (breakpoint > state > value) of module attribute.
	 *     @type bool|array $important  Optional. Whether to add `!important` tag. Default `false`.
	 *     @type string     $returnType Optional. This is the type of value that the function will return.
	 *                                  Can be either `string` or `key_value_pair`. Default `string`.
	 * }
	 *
	 * @return string|array
	 */
	public static function style_declaration( array $args ) {
		$style_declarations = new StyleDeclarations( $args );
		$fit_attr           = $args['attrValue'] ?? [];

		if ( isset( $fit_attr['objectFit'] ) ) {
			$style_declarations->add( 'object-fit', $fit_attr['objectFit'] );
		}

		if ( isset( $fit_attr['objectPosition'] ) ) {
			$object_position = self::normalize_object_position_for_declaration( $fit_attr['objectPosition'] );

			if ( null !== $object_position ) {
				$style_declarations->add( 'object-position', $object_position );
			}
		}

		return $style_declarations->value();
	}

	/**
	 * Normalize object-position for {@see StyleDeclarations::add()} (string-only contract).
	 *
	 * Accepts canonical strings or legacy `{ x, y }` array shapes from saved markup (#49960).
	 *
	 * @since ??
	 *
	 * @param mixed $value Raw attribute value.
	 *
	 * @return string|null Normalized string or null when the declaration should be skipped.
	 */
	private static function normalize_object_position_for_declaration( $value ): ?string {
		if ( is_string( $value ) ) {
			$normalized_value = trim( $value );

			return '' === $normalized_value ? null : $normalized_value;
		}

		if ( ! is_array( $value ) ) {
			return null;
		}

		if (
			isset( $value['x'], $value['y'] )
			&& is_string( $value['x'] )
			&& is_string( $value['y'] )
		) {
			$normalized_value = trim( $value['x'] . ' ' . $value['y'] );

			return '' === $normalized_value ? null : $normalized_value;
		}

		return null;
	}
}
