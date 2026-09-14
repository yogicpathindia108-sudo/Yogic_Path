<?php
/**
 * Sizing class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\StyleLibrary\Declarations\Sizing;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;

/**
 * Sizing class
 *
 * This class provides sizing functionality.
 *
 * @since ??
 */
class Sizing {

	/**
	 * Get sizing CSS declaration based on given arguments.
	 *
	 * This function accepts an array of arguments that define the style declaration.
	 * It parses the arguments, sets default values, and generates a CSS style declaration based on the provided arguments.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/style-library/sizing-style-declaration/ sizingStyleDeclaration}
	 * located in `@divi/style-library` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments that define the style declaration.
	 *
	 *     @type string      $attrValue   The value (breakpoint > state > value) of module attribute.
	 *     @type array|bool  $important   Optional. Whether to add `!important` to the CSS. Default `false`.
	 *     @type string      $returnType  Optional. The return type of the style declaration. Default `string`.
	 *                                    One of `string`, or `key_value_pair`
	 *                                      - If `string`, the style declaration will be returned as a string.
	 *                                      - If `key_value_pair`, the style declaration will be returned as an array of key-value pairs.
	 *     @type bool        $skipDefaults Optional. Whether to skip printing default values. Default `false`.
	 * }
	 *
	 * @return array|string The generated sizing CSS style declaration.
	 *
	 * @example:
	 * ```php
	 * $args = [
	 *     'attrValue'   => ['orientation' => 'center'], // The attribute value.
	 *     'important'   => true,                        // Whether the declaration should be marked as important.
	 *     'returnType'  => 'key_value_pair',            // The return type of the style declaration.
	 * ];
	 * $style = Sizing::style_declaration( $args );
	 * ```
	 */
	public static function style_declaration( array $args ) {
		$args = wp_parse_args(
			$args,
			[
				'important'              => false,
				'returnType'             => 'string',
				'defaultAttrValue'       => [],
				'skipDefaults'           => false,
				'disableAlignmentStyles' => false,
			]
		);

		$attr_value               = $args['attrValue'];
		$default_attr_value       = $args['defaultAttrValue'];
		$skip_defaults            = $args['skipDefaults'];
		$disable_alignment_styles = (bool) $args['disableAlignmentStyles'];
		$important                = $args['important'];
		$return_type              = $args['returnType'];
		$width                    = isset( $attr_value['width'] ) ? $attr_value['width'] : null;
		$max_width                = isset( $attr_value['maxWidth'] ) ? $attr_value['maxWidth'] : null;
		$min_width                = isset( $attr_value['minWidth'] ) ? $attr_value['minWidth'] : null;
		$alignment                = isset( $attr_value['alignment'] ) ? $attr_value['alignment'] : null;
		$min_height               = isset( $attr_value['minHeight'] ) ? $attr_value['minHeight'] : null;
		$height                   = isset( $attr_value['height'] ) ? $attr_value['height'] : null;
		$max_height               = isset( $attr_value['maxHeight'] ) ? $attr_value['maxHeight'] : null;
		$aspect_ratio             = isset( $attr_value['aspectRatio'] ) && is_array( $attr_value['aspectRatio'] )
			? $attr_value['aspectRatio']
			: null;
		$aspect_ratio_width       = $aspect_ratio['width'] ?? null;
		$aspect_ratio_height      = $aspect_ratio['height'] ?? null;

		// Flexbox sizing options.
		$size        = isset( $attr_value['size'] ) ? $attr_value['size'] : null;
		$flex_shrink = isset( $attr_value['flexShrink'] ) ? $attr_value['flexShrink'] : null;
		$flex_grow   = isset( $attr_value['flexGrow'] ) ? $attr_value['flexGrow'] : null;
		$align_self  = isset( $attr_value['alignSelf'] ) ? $attr_value['alignSelf'] : null;

		// Grid-specific sizing options.
		$grid_column_span  = isset( $attr_value['gridColumnSpan'] ) ? $attr_value['gridColumnSpan'] : null;
		$grid_row_span     = isset( $attr_value['gridRowSpan'] ) ? $attr_value['gridRowSpan'] : null;
		$grid_column_start = isset( $attr_value['gridColumnStart'] ) ? $attr_value['gridColumnStart'] : null;
		$grid_row_start    = isset( $attr_value['gridRowStart'] ) ? $attr_value['gridRowStart'] : null;
		$grid_column_end   = isset( $attr_value['gridColumnEnd'] ) ? $attr_value['gridColumnEnd'] : null;
		$grid_row_end      = isset( $attr_value['gridRowEnd'] ) ? $attr_value['gridRowEnd'] : null;
		$grid_align_self   = isset( $attr_value['gridAlignSelf'] ) ? $attr_value['gridAlignSelf'] : null;
		$grid_justify_self = isset( $attr_value['gridJustifySelf'] ) ? $attr_value['gridJustifySelf'] : null;

		$is_parent_flex_layout = $args['isParentFlexLayout'] ?? false;
		$is_parent_grid_layout = $args['isParentGridLayout'] ?? false;

		// Always add important flags for grid properties.
		$grid_important_props = [
			'grid-column'       => true,
			'grid-column-start' => true,
			'grid-column-end'   => true,
			'grid-row'          => true,
			'grid-row-start'    => true,
			'grid-row-end'      => true,
			'align-self'        => true,
			'justify-self'      => true,
		];

		$enhanced_important = is_array( $important )
			? array_merge( $grid_important_props, $important )
			: ( true === $important ? array_merge(
				$grid_important_props,
				[
					'width'        => true,
					'max-width'    => true,
					'min-width'    => true,
					'margin-left'  => true,
					'margin-right' => true,
					'min-height'   => true,
					'height'       => true,
					'max-height'   => true,
					'aspect-ratio' => true,
				]
			) : $grid_important_props );

		$style_declarations = new StyleDeclarations(
			[
				'important'  => $enhanced_important,
				'returnType' => $return_type,
			]
		);

		$has_custom_width_context = self::has_custom_width_context( $args );

		if ( $is_parent_flex_layout && isset( $size ) && is_array( $size ) ) {
			if ( in_array( 'custom', $size, true ) ) {
				// Custom mode is selected.
				// Apply flex-grow only if the value is set, not empty string, and not '0'.
				if ( isset( $flex_grow ) && '' !== $flex_grow && '0' !== $flex_grow ) {
					$style_declarations->add( 'flex-grow', $flex_grow );
				}
				// Apply flex-shrink only if the value is set, not empty string, and not '1'.
				if ( isset( $flex_shrink ) && '' !== $flex_shrink && '1' !== $flex_shrink ) {
					$style_declarations->add( 'flex-shrink', $flex_shrink );
				}
			} else {
				// Custom mode is NOT selected. Handle individual toggles.
				// If 'flexGrow' toggle is selected, apply flex-grow: 1.
				if ( in_array( 'flexGrow', $size, true ) ) {
					$style_declarations->add( 'flex-grow', '1' );
				} else {
					// If 'flexGrow' toggle is NOT selected, apply flex-grow: 0.
					// This ensures responsive inheritance is properly overridden.
					$style_declarations->add( 'flex-grow', '0' );
				}
				// If 'flexShrink' toggle is NOT selected, apply flex-shrink: 0.
				// (If 'flexShrink' is selected, it implies default shrink behavior, so print nothing).
				if ( ! in_array( 'flexShrink', $size, true ) ) {
					$style_declarations->add( 'flex-shrink', '0' );
				}
			}
		}

		if ( $is_parent_flex_layout && null !== $align_self ) {
			$style_declarations->add( 'align-self', $align_self );
		}

		$default_width = $default_attr_value['width'] ?? null;
		if ( null !== $width && '' !== $width && ! ( $skip_defaults && $width === $default_width ) ) {
			$style_declarations->add( 'width', $width );
		}

		$default_max_width = $default_attr_value['maxWidth'] ?? null;
		if ( null !== $max_width && '' !== $max_width && ! ( $skip_defaults && $max_width === $default_max_width ) ) {
			$style_declarations->add( 'max-width', $max_width );
		}

		$default_min_width = $default_attr_value['minWidth'] ?? null;
		if ( null !== $min_width && '' !== $min_width && ! ( $skip_defaults && $min_width === $default_min_width ) ) {
			$style_declarations->add( 'min-width', $min_width );
		}

		if ( ! $disable_alignment_styles ) {
			switch ( $alignment ) {
				case 'left':
					$style_declarations->add( 'margin-left', '0' );
					$style_declarations->add( 'margin-right', 'auto' );
					break;

				case 'center':
					if ( $has_custom_width_context ) {
						$style_declarations->add( 'margin-left', 'auto' );
						$style_declarations->add( 'margin-right', 'auto' );
					}
					break;

				case 'right':
					$style_declarations->add( 'margin-left', 'auto' );
					$style_declarations->add( 'margin-right', '0' );
					break;

				default:
					// Do nothing.
			}
		}

		$default_min_height = $default_attr_value['minHeight'] ?? null;
		if ( null !== $min_height && '' !== $min_height && ! ( $skip_defaults && $min_height === $default_min_height ) ) {
			$style_declarations->add( 'min-height', $min_height );
		}

		$default_height = $default_attr_value['height'] ?? null;
		if ( null !== $height && '' !== $height && ! ( $skip_defaults && $height === $default_height ) ) {
			$style_declarations->add( 'height', $height );
		}

		$default_max_height = $default_attr_value['maxHeight'] ?? null;
		if ( null !== $max_height && '' !== $max_height && ! ( $skip_defaults && $max_height === $default_max_height ) ) {
			$style_declarations->add( 'max-height', $max_height );
		}

		if (
			null !== $aspect_ratio_width &&
			null !== $aspect_ratio_height &&
			'' !== $aspect_ratio_width &&
			'' !== $aspect_ratio_height &&
			'auto' !== $aspect_ratio_width &&
			'auto' !== $aspect_ratio_height
		) {
			$style_declarations->add( 'aspect-ratio', $aspect_ratio_width . ' / ' . $aspect_ratio_height );
		}

		// Grid-specific sizing properties.
		if ( $is_parent_grid_layout ) {
			// Handle grid-column properties (start, end, span).
			if ( $grid_column_start || $grid_column_end || $grid_column_span ) {
				if ( $grid_column_start && $grid_column_end ) {
					// If both start and end are set, use shorthand syntax.
					$style_declarations->add( 'grid-column', $grid_column_start . ' / ' . $grid_column_end );
				} elseif ( $grid_column_start && $grid_column_span ) {
					// If start and span are set, use shorthand syntax.
					$style_declarations->add( 'grid-column', $grid_column_start . ' / span ' . $grid_column_span );
				} elseif ( $grid_column_end && $grid_column_span ) {
					// If end and span are set, use shorthand syntax.
					$style_declarations->add( 'grid-column', 'span ' . $grid_column_span . ' / ' . $grid_column_end );
				} elseif ( $grid_column_start ) {
					$style_declarations->add( 'grid-column-start', $grid_column_start );
				} elseif ( $grid_column_end ) {
					$style_declarations->add( 'grid-column-end', $grid_column_end );
				} elseif ( $grid_column_span ) {
					$style_declarations->add( 'grid-column', 'span ' . $grid_column_span );
				}
			}

			// Handle grid-row properties (start, end, span).
			if ( $grid_row_start || $grid_row_end || $grid_row_span ) {
				if ( $grid_row_start && $grid_row_end ) {
					// If both start and end are set, use shorthand syntax.
					$style_declarations->add( 'grid-row', $grid_row_start . ' / ' . $grid_row_end );
				} elseif ( $grid_row_start && $grid_row_span ) {
					// If start and span are set, use shorthand syntax.
					$style_declarations->add( 'grid-row', $grid_row_start . ' / span ' . $grid_row_span );
				} elseif ( $grid_row_end && $grid_row_span ) {
					// If end and span are set, use shorthand syntax.
					$style_declarations->add( 'grid-row', 'span ' . $grid_row_span . ' / ' . $grid_row_end );
				} elseif ( $grid_row_start ) {
					$style_declarations->add( 'grid-row-start', $grid_row_start );
				} elseif ( $grid_row_end ) {
					$style_declarations->add( 'grid-row-end', $grid_row_end );
				} elseif ( $grid_row_span ) {
					$style_declarations->add( 'grid-row', 'span ' . $grid_row_span );
				}
			}

			if ( null !== $grid_align_self ) {
				$style_declarations->add( 'align-self', $grid_align_self );
			}

			if ( null !== $grid_justify_self ) {
				$style_declarations->add( 'justify-self', $grid_justify_self );
			}
		}

		return $style_declarations->value();
	}

	/**
	 * Check whether sizing has a specific width value.
	 *
	 * @since ??
	 *
	 * @param array $args Style declaration args.
	 *
	 * @return boolean True if the sizing has a specific width value, false otherwise.
	 */
	private static function has_custom_width_context( array $args ): bool {
		$width     = self::get_effective_width_value( $args, 'width' );
		$max_width = self::get_effective_width_value( $args, 'maxWidth' );
		$min_width = self::get_effective_width_value( $args, 'minWidth' );

		return self::is_custom_width_value( $width ) || self::is_custom_width_value( $max_width ) || self::is_custom_width_value( $min_width );
	}

	/**
	 * Resolve effective width-style value for current breakpoint/state.
	 *
	 * @since ??
	 *
	 * @param array  $args    Style declaration args.
	 * @param string $subname Width subname (`width`, `maxWidth`, `minWidth`).
	 *
	 * @return mixed Effective width value.
	 */
	private static function get_effective_width_value( array $args, string $subname ) {
		$attr_value  = $args['attrValue'] ?? [];
		$local_value = $attr_value[ $subname ] ?? null;

		// Use local value whenever the current breakpoint/state explicitly sets this subname.
		// Fallback to inherited value only when the local subname is absent.
		if ( array_key_exists( $subname, $attr_value ) ) {
			return $local_value;
		}

		$attr       = $args['attr'] ?? null;
		$breakpoint = $args['breakpoint'] ?? null;
		$state      = $args['state'] ?? 'value';

		if ( ! is_array( $attr ) || ! is_string( $breakpoint ) || '' === $breakpoint || ! is_string( $state ) || '' === $state ) {
			return $local_value;
		}

		$effective_attr_value = ModuleUtils::use_attr_value(
			[
				'attr'         => $attr,
				'breakpoint'   => $breakpoint,
				'state'        => $state,
				'mode'         => 'getAndInheritAll',
				'defaultValue' => $attr_value,
			]
		);

		if ( is_array( $effective_attr_value ) && array_key_exists( $subname, $effective_attr_value ) ) {
			return $effective_attr_value[ $subname ];
		}

		return $local_value;
	}

	/**
	 * Check whether a width-style value is set to a specific width value.
	 *
	 * @since ??
	 *
	 * @param mixed $value Width-style value.
	 *
	 * @return boolean True if the value is not set to specific width values, false otherwise.
	 */
	private static function is_custom_width_value( $value ): bool {
		if ( ! $value ) {
			return false;
		}

		$normalized_value = strtolower( trim( (string) $value ) );

		return ! in_array( $normalized_value, [ 'auto', '100%', 'none' ], true );
	}

	/**
	 * Array of sizing units.
	 *
	 * This array contains various sizing units that can be used for CSS properties, such as `width`, `height`, `font-size`, etc.
	 * These units define the measurement of the value assigned to the CSS property.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/style-library/sizing-units/ sizingUnits}
	 * located in `@divi/style-library` package.
	 *
	 * @var array $sizing_units Array of sizing units.
	 *                          Default `['%', 'ch', 'cm', 'em', 'ex', 'in', 'mm', 'pc', 'pt', 'px', 'rem', 'vh', 'vmax', 'vmin', 'vw']`.
	 *
	 * @since ??
	 */
	public static $sizing_units = [
		'%',
		'ch',
		'cm',
		'em',
		'ex',
		'in',
		'mm',
		'pc',
		'pt',
		'px',
		'rem',
		'vh',
		'vmax',
		'vmin',
		'vw',
	];
}
