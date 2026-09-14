<?php
/**
 * Font class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\StyleLibrary\Declarations\Font;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\StyleLibrary\Utils\Utils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;

/**
 * Font class.
 *
 * @since ??
 */
class Font {

	/**
	 * Normalize variation axis tag for CSS output.
	 *
	 * Standard axes use lowercase tags in `font-variation-settings`.
	 *
	 * @since ??
	 *
	 * @param string $axis_tag Axis tag.
	 *
	 * @return string
	 */
	private static function _normalize_axis_tag_for_css( $axis_tag ) {
		$standard_axes = [ 'WGHT', 'WDTH', 'OPSZ', 'SLNT', 'ITAL' ];

		return in_array( strtoupper( $axis_tag ), $standard_axes, true )
			? strtolower( $axis_tag )
			: $axis_tag;
	}

	/**
	 * Normalizes and clamps variable axis value to the given range.
	 *
	 * @since ??
	 *
	 * @param string               $raw_axis_value Axis value.
	 * @param array<string, float> $axis_range     Optional axis range.
	 *
	 * @return string|null
	 */
	private static function _normalize_and_clamp_axis_value( $raw_axis_value, $axis_range = null ) {
		$axis_value = trim( (string) $raw_axis_value );
		if ( '' === $axis_value ) {
			return null;
		}

		$is_css_variable = ModuleUtils::is_css_variable( $axis_value );
		if ( $is_css_variable ) {
			return $axis_value;
		}

		if ( ! is_numeric( $axis_value ) ) {
			return null;
		}

		$axis_number = (float) $axis_value;

		if ( ! is_array( $axis_range ) || ! isset( $axis_range['min'], $axis_range['max'] ) ) {
			return (string) $axis_number;
		}

		$clamped_axis_value = min( max( $axis_number, (float) $axis_range['min'] ), (float) $axis_range['max'] );

		return (string) $clamped_axis_value;
	}

	/**
	 * Formats an axis value as percentage-compatible CSS value.
	 *
	 * @since ??
	 *
	 * @param string $axis_value Axis value.
	 *
	 * @return string|null
	 */
	private static function _format_axis_value_for_percentage( $axis_value ) {
		$normalized_axis_value = trim( (string) $axis_value );
		if ( '' === $normalized_axis_value ) {
			return null;
		}

		$is_css_variable = ModuleUtils::is_css_variable( $normalized_axis_value );
		if ( $is_css_variable ) {
			return 'calc(' . $normalized_axis_value . ' * 1%)';
		}

		if ( ! is_numeric( $normalized_axis_value ) ) {
			return null;
		}

		return $normalized_axis_value . '%';
	}

	/**
	 * Get websafe font fallback stack based on font type.
	 *
	 * This function matches D4 behavior from et_builder_get_websafe_font_stack().
	 *
	 * @since ??
	 *
	 * @param string      $type      The font type (sans-serif, serif, cursive, etc.).
	 * @param string|null $font_name The primary font name to exclude from the stack. Default null.
	 *
	 * @return string The appropriate fallback font stack.
	 */
	private static function _get_websafe_font_stack( $type = 'sans-serif', $font_name = null ) {
		switch ( $type ) {
			case 'sans-serif':
				$stack = 'Helvetica, Arial, Lucida, sans-serif';
				break;
			case 'serif':
				$stack = 'Georgia, "Times New Roman", serif';
				break;
			case 'cursive':
				$stack = 'cursive';
				break;
			case 'display':
				// Google Fonts uses 'display' category, map to valid CSS generic 'fantasy'.
				$stack = 'fantasy';
				break;
			case 'handwriting':
				// Google Fonts uses 'handwriting' category, map to valid CSS generic 'cursive'.
				$stack = 'cursive';
				break;
			case 'monospace':
				$stack = 'monospace';
				break;
			default:
				// Fallback to sans-serif for any unknown types.
				$stack = 'sans-serif';
				break;
		}

		// Remove duplicate fonts from the stack to avoid redundancy.
		if ( $font_name ) {
			// Parse font_name into individual fonts.
			$font_name_list = array_map( 'trim', explode( ',', $font_name ) );

			// Check if font_name already ends with a generic keyword.
			// Generic keywords: sans-serif, serif, monospace, cursive, fantasy.
			$generic_keywords = [ 'sans-serif', 'serif', 'monospace', 'cursive', 'fantasy' ];
			$last_font        = end( $font_name_list );
			$last_font_clean  = strtolower( trim( $last_font, '\'"' ) );

			if ( in_array( $last_font_clean, $generic_keywords, true ) ) {
				// Font name already has a generic fallback, don't add more.
				return '';
			}

			// Split the stack into individual fonts.
			$stack_fonts = array_map( 'trim', explode( ',', $stack ) );

			// Build a set of font names for comparison (cleaned, lowercase).
			$font_name_set = [];
			foreach ( $font_name_list as $font ) {
				$font_clean                   = strtolower( trim( $font, '\'"' ) );
				$font_name_set[ $font_clean ] = true;
			}

			// Filter out any fonts from stack that are already in font_name.
			$filtered_fonts = array_filter(
				$stack_fonts,
				function ( $font ) use ( $font_name_set ) {
					$font_clean = strtolower( trim( $font, '\'"' ) );
					return ! isset( $font_name_set[ $font_clean ] );
				}
			);

			// Rebuild the stack.
			$stack = implode( ', ', $filtered_fonts );
		}

		return $stack;
	}

	/**
	 * Get Font's CSS declaration based on given attrValue.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/style-library/font-style-declaration fontStyleDeclaration} in:
	 * `@divi/style-library` package.
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
	 *     @type array|null $fonts      Optional. Websafe fonts data for MS version handling. Default `null`.
	 * }
	 *
	 * @return array|string
	 */
	public static function style_declaration( array $args ) {
		$args = wp_parse_args(
			$args,
			[
				'important'  => false,
				'returnType' => 'string',
				'breakpoint' => 'desktop',
				'state'      => 'value',
				'fonts'      => null,
			]
		);

		$attr        = $args['attr'];
		$attr_value  = $args['attrValue'];
		$important   = $args['important'];
		$return_type = $args['returnType'];
		$breakpoint  = $args['breakpoint'];
		$state       = $args['state'];
		$fonts       = $args['fonts'];

		$style_declarations = new StyleDeclarations(
			[
				'important'  => $important,
				'returnType' => $return_type,
			]
		);
		$default_attr_value = $args['defaultAttrValue'] ?? [];

		$resolved_attr_value = ModuleUtils::use_attr_value(
			[
				'attr'       => $attr,
				'breakpoint' => $breakpoint,
				'state'      => $state,
				'mode'       => 'getOrInheritAll',
			]
		);
		$resolved_attr_value = is_array( $resolved_attr_value ) ? $resolved_attr_value : [];

		$inherited_attr_value          = ModuleUtils::use_attr_value(
			[
				'attr'       => $attr,
				'breakpoint' => $breakpoint,
				'state'      => $state,
				'mode'       => 'inheritAll',
			]
		);
		$inherited_attr_value          = is_array( $inherited_attr_value ) ? $inherited_attr_value : [];
		$inherited_font_style          = isset( $inherited_attr_value['style'] ) && is_array( $inherited_attr_value['style'] )
			? $inherited_attr_value['style']
			: [];
		$inherited_font_capitalization = $inherited_attr_value['capitalization'] ?? '';
		if ( is_array( $inherited_font_capitalization ) ) {
			$inherited_font_capitalization = $inherited_font_capitalization[0] ?? '';
		}
		$inherited_font_capitalization = is_scalar( $inherited_font_capitalization ) ? (string) $inherited_font_capitalization : '';
		$default_font_capitalization   = $default_attr_value['capitalization'] ?? '';
		if ( is_array( $default_font_capitalization ) ) {
			$default_font_capitalization = $default_font_capitalization[0] ?? '';
		}
		$default_font_capitalization = is_scalar( $default_font_capitalization ) ? (string) $default_font_capitalization : '';

		$resolved_font_family = array_key_exists( 'family', $attr_value )
			? $attr_value['family']
			: ( $resolved_attr_value['family'] ?? null );
		$resolved_font_family = is_null( $resolved_font_family ) ? '' : (string) $resolved_font_family;

		if ( '' !== $resolved_font_family ) {
			// Resolve $variable(...)$ encoded references before CSS variable detection.
			// This handles global font variables in presets which are stored in encoded format.
			$resolved_font_family = Utils::resolve_dynamic_variable( $resolved_font_family );
		}

		if ( in_array( strtolower( $resolved_font_family ), [ 'none', 'default' ], true ) ) {
			$resolved_font_family = '';
		}

		if ( '' !== $resolved_font_family ) {

			/**
			 * Check if font family is a CSS variable.
			 * Test regex https://regex101.com/r/4cTjiQ/1.
			 */
			$regex           = '/var\(\s*(-{2,})([a-zA-Z0-9-_]+)\)/i';
			$is_css_variable = preg_match( $regex, $resolved_font_family ) === 1;

			// The check has been done to avoid adding single quotes to CSS variable.
			if ( $is_css_variable ) {
				// Normalize CSS variable format to ensure consistent processing for both VB and FE.
				$font_family = preg_replace_callback(
					$regex,
					function ( $matches ) {
						// Always use exactly two dashes for CSS variables.
						return 'var(--' . $matches[2] . ')';
					},
					$resolved_font_family
				);
			} else {
				// Handle MS version for websafe fonts (Issue #45473).
				$font_data = null;
				if ( $fonts ) {
					$font_data = $fonts[ $resolved_font_family ] ?? null;
				}

				// Check if this font needs MS version (websafe fonts like Trebuchet).
				if ( $font_data && isset( $font_data['add_ms_version'] ) && $font_data['add_ms_version'] ) {
					$font_family = "'" . $resolved_font_family . " MS', '" . $resolved_font_family . "'";
				} else {
					$font_family = "'" . $resolved_font_family . "'";
				}

				// Get font type and append websafe fallback stack (Issue #46031).
				// Add fallback for all registered fonts (Google fonts, websafe fonts).
				// Default to 'sans-serif' if type is missing or empty.
				$font_type      = ! empty( $font_data['type'] ) ? $font_data['type'] : 'sans-serif';
				$fallback_stack = self::_get_websafe_font_stack( $font_type, $resolved_font_family );
				// Only append fallback stack if it's not empty.
				if ( ! empty( $fallback_stack ) ) {
					$font_family = $font_family . ', ' . $fallback_stack;
				}
			}

			$style_declarations->add( 'font-family', $font_family );
		}

		$variable_axis_values          = [];
		$resolved_weight               = array_key_exists( 'weight', $attr_value )
			? $attr_value['weight']
			: ( $resolved_attr_value['weight'] ?? null );
		$resolved_weight               = is_null( $resolved_weight ) ? '' : (string) $resolved_weight;
		$resolved_weight_fine_tune     = isset( $attr_value['weightFineTune'] ) ? (string) $attr_value['weightFineTune'] : '';
		$axis_range_map                = [];
		$resolved_variation_settings   = array_key_exists( 'variationSettings', $attr_value )
			? $attr_value['variationSettings']
			: ( $resolved_attr_value['variationSettings'] ?? null );
		$resolved_variation_settings   = is_array( $resolved_variation_settings ) ? $resolved_variation_settings : [];
		$normalized_variation_settings = [];

		foreach ( $resolved_variation_settings as $raw_axis_tag => $raw_axis_value ) {
			$normalized_axis_tag = strtoupper( (string) $raw_axis_tag );

			if ( '' === $normalized_axis_tag ) {
				continue;
			}

			$normalized_variation_settings[ $normalized_axis_tag ] = $raw_axis_value;
		}

		$has_weight_fine_tune    = '' !== trim( (string) $resolved_weight_fine_tune );
		$has_variation_weight    = '' !== trim( (string) ( $normalized_variation_settings['WGHT'] ?? '' ) );
		$is_variable_weight_mode = 'variable' === $resolved_weight || $has_weight_fine_tune || $has_variation_weight;

		$font_family = $resolved_font_family;
		if ( is_array( $fonts ) && ! empty( $font_family ) && isset( $fonts[ $font_family ]['axes'] ) && is_array( $fonts[ $font_family ]['axes'] ) ) {
			foreach ( $fonts[ $font_family ]['axes'] as $axis ) {
				if ( ! is_array( $axis ) ) {
					continue;
				}

				$axis_tag = strtoupper( (string) ( $axis['tag'] ?? '' ) );
				$axis_min = isset( $axis['min'] ) && is_numeric( $axis['min'] ) ? (float) $axis['min'] : null;
				$axis_max = isset( $axis['max'] ) && is_numeric( $axis['max'] ) ? (float) $axis['max'] : null;

				if ( '' === $axis_tag || null === $axis_min || null === $axis_max ) {
					continue;
				}

				$axis_range_map[ $axis_tag ] = [
					'min' => $axis_min,
					'max' => $axis_max,
				];
			}
		}

		$has_axis_ranges                  = ! empty( $axis_range_map );
		$has_variable_weight_axis         = isset( $axis_range_map['WGHT'] ) && is_array( $axis_range_map['WGHT'] );
		$is_variable_weight_resolved_mode = $is_variable_weight_mode && $has_variable_weight_axis;

		if ( ! empty( $normalized_variation_settings ) ) {
			foreach ( $normalized_variation_settings as $axis_tag => $raw_axis_value ) {
				$axis_range = $axis_range_map[ $axis_tag ] ?? null;

				if ( '' === $axis_tag || 'OPSZ' === $axis_tag ) {
					continue;
				}

				if ( 'WGHT' === $axis_tag && ! $is_variable_weight_resolved_mode ) {
					continue;
				}

				// When font metadata is available, only allow known axes. If metadata is
				// unavailable (e.g. early hydration), still preserve user-defined axes.
				if ( $has_axis_ranges && ! is_array( $axis_range ) ) {
					continue;
				}

				$axis_val = self::_normalize_and_clamp_axis_value( $raw_axis_value, $axis_range );
				if ( null !== $axis_val ) {
					$variable_axis_values[ $axis_tag ] = $axis_val;
				}
			}
		}

		$raw_variation_weight = isset( $normalized_variation_settings['WGHT'] )
			? trim( (string) $normalized_variation_settings['WGHT'] )
			: '';
		$raw_variable_weight  = '' !== $raw_variation_weight
			? $raw_variation_weight
			: trim( (string) $resolved_weight_fine_tune );

		$fallback_variable_weight = self::_normalize_and_clamp_axis_value(
			$raw_variable_weight,
			$axis_range_map['WGHT'] ?? null
		);
		$variable_weight          = $variable_axis_values['WGHT'] ?? $fallback_variable_weight;

		if ( null !== $variable_weight ) {
			$style_declarations->add( 'font-weight', $variable_weight );
			$variable_axis_values['WGHT'] = $variable_weight;
		} elseif ( $is_variable_weight_mode && ! $is_variable_weight_resolved_mode ) {
			$style_declarations->add( 'font-weight', '400' );
		} elseif ( '' !== $resolved_weight && 'variable' !== $resolved_weight ) {
			$style_declarations->add( 'font-weight', $resolved_weight );
		}

		// Do not emit font-stretch when using variable width axis.
		// Some browsers clamp percentage values and override WDTH interpolation below 100.

		// Do not emit `font-style: oblique ...` from SLNT axis.
		// Let SLNT be controlled solely via `font-variation-settings` to avoid double-application.

		$resolved_discrete_weight         = self::_normalize_and_clamp_axis_value( $resolved_weight, $axis_range_map['WGHT'] ?? null );
		$has_custom_variable_axis         = ! empty( array_diff( array_keys( $variable_axis_values ), [ 'WGHT' ] ) );
		$should_include_weight_axis_value =
			! isset( $variable_axis_values['WGHT'] )
			&& $has_custom_variable_axis
			&& $has_variable_weight_axis
			&& null !== $resolved_discrete_weight;

		// Keep WGHT available in font-variation-settings when custom axes are active.
		// Some browser/font combinations drop interpolation at exact static weights unless
		// WGHT remains explicit alongside axes like WDTH.
		if ( $should_include_weight_axis_value ) {
			$variable_axis_values['WGHT'] = $resolved_discrete_weight;
		}

		$variable_width = $variable_axis_values['WDTH'] ?? null;

		if ( null !== $variable_width && '' !== trim( (string) $variable_width ) ) {
			$formatted_variable_width = self::_format_axis_value_for_percentage( $variable_width );
			if ( null !== $formatted_variable_width ) {
				$style_declarations->add( 'font-stretch', $formatted_variable_width );
			}
		}

		$custom_axis_settings = [];
		foreach ( $variable_axis_values as $axis_tag => $axis_value ) {
			if ( 'WGHT' === strtoupper( (string) $axis_tag ) && ! $should_include_weight_axis_value ) {
				continue;
			}
			if ( 'WDTH' === strtoupper( (string) $axis_tag ) ) {
				continue;
			}
			$custom_axis_settings[] = '"' . self::_normalize_axis_tag_for_css( $axis_tag ) . '" ' . $axis_value;
		}

		if ( ! empty( $custom_axis_settings ) ) {
			$style_declarations->add( 'font-variation-settings', implode( ', ', $custom_axis_settings ) );
		}

		$resolved_optical_sizing = array_key_exists( 'opticalSizing', $attr_value )
			? $attr_value['opticalSizing']
			: ( $resolved_attr_value['opticalSizing'] ?? null );
		$optical_sizing          = is_null( $resolved_optical_sizing ) ? 'auto' : $resolved_optical_sizing;

		if ( in_array( $optical_sizing, [ 'none', 'off' ], true ) ) {
			$style_declarations->add( 'font-optical-sizing', 'none' );
		}

		$font_style = isset( $attr_value['style'] ) ? $attr_value['style'] : null;

		// Normalize font style to always be an array for consistent processing.
		if ( ! is_array( $font_style ) && null !== $font_style ) {
			// Handle legacy string values by converting to array.
			$font_style = [ $font_style ];
		}

		$has_font_capitalization = array_key_exists( 'capitalization', $attr_value );
		$font_capitalization     = null;
		if ( $has_font_capitalization ) {
			$font_capitalization = $attr_value['capitalization'];
			if ( is_array( $font_capitalization ) ) {
				$font_capitalization = $font_capitalization[0] ?? '';
			}
			$font_capitalization = is_scalar( $font_capitalization ) ? (string) $font_capitalization : '';
		}

		if ( is_array( $font_style ) ) {
			// Empty font style array indicates explicit reset to override inherited or preset styles.
			$is_empty_font_style = empty( $font_style );

			if ( in_array( 'italic', $font_style, true ) ) {
				$style_declarations->add( 'font-style', 'italic' );
			} elseif ( in_array( 'italic', $inherited_font_style, true ) || $is_empty_font_style ) {
				$style_declarations->add( 'font-style', 'normal' );
			}

			$text_decoration_lines = [];

			if ( in_array( 'underline', $font_style, true ) ) {
				$text_decoration_lines[] = 'underline';
			}

			if ( in_array( 'overline', $font_style, true ) ) {
				$text_decoration_lines[] = 'overline';
			}

			if ( in_array( 'strikethrough', $font_style, true ) ) {
				$text_decoration_lines[] = 'line-through';
			}

			if ( ! empty( $text_decoration_lines ) ) {
				$style_declarations->add( 'text-decoration-line', implode( ' ', $text_decoration_lines ) );
			} elseif (
				in_array( 'underline', $inherited_font_style, true ) ||
				in_array( 'overline', $inherited_font_style, true ) ||
				in_array( 'strikethrough', $inherited_font_style, true ) ||
				$is_empty_font_style
			) {
				$style_declarations->add( 'text-decoration-line', 'none' );
			}
		}

		if ( 'uppercase' === $font_capitalization ) {
			$style_declarations->add( 'text-transform', 'uppercase' );
		} elseif ( 'lowercase' === $font_capitalization ) {
			$style_declarations->add( 'text-transform', 'lowercase' );
		} elseif ( 'capitalize' === $font_capitalization ) {
			$style_declarations->add( 'text-transform', 'capitalize' );
		} elseif (
			$has_font_capitalization &&
			'' === $font_capitalization &&
			(
				'uppercase' === $inherited_font_capitalization ||
				'lowercase' === $inherited_font_capitalization ||
				'capitalize' === $inherited_font_capitalization ||
				( '' === $inherited_font_capitalization && 'uppercase' === $default_font_capitalization ) ||
				( '' === $inherited_font_capitalization && 'lowercase' === $default_font_capitalization ) ||
				( '' === $inherited_font_capitalization && 'capitalize' === $default_font_capitalization )
			)
		) {
			$style_declarations->add( 'text-transform', 'none' );
		}

		if ( 'allSmallCaps' === $font_capitalization ) {
			$style_declarations->add( 'font-variant-caps', 'all-small-caps' );
		} elseif ( 'smallCaps' === $font_capitalization ) {
			$style_declarations->add( 'font-variant-caps', 'small-caps' );
		} elseif (
			$has_font_capitalization &&
			'' === $font_capitalization &&
			(
				'smallCaps' === $inherited_font_capitalization ||
				'allSmallCaps' === $inherited_font_capitalization ||
				( '' === $inherited_font_capitalization && 'smallCaps' === $default_font_capitalization ) ||
				( '' === $inherited_font_capitalization && 'allSmallCaps' === $default_font_capitalization )
			)
		) {
			$style_declarations->add( 'font-variant-caps', 'normal' );
		}

		if ( isset( $attr_value['lineColor'] ) ) {
			$style_declarations->add( 'text-decoration-color', $attr_value['lineColor'] );
		}

		$line_style = isset( $attr_value['lineStyle'] ) ? $attr_value['lineStyle'] : 'solid';

		if (
			is_array( $font_style ) &&
			(
				in_array( 'strikethrough', $font_style, true ) ||
				in_array( 'underline', $font_style, true ) ||
				in_array( 'overline', $font_style, true )
			)
		) {
			$style_declarations->add( 'text-decoration-style', $line_style );
		}

		if ( isset( $attr_value['lineThickness'] ) && '' !== (string) $attr_value['lineThickness'] ) {
			$style_declarations->add( 'text-decoration-thickness', $attr_value['lineThickness'] );
		}

		if ( isset( $attr_value['underlineOffset'] ) && '' !== (string) $attr_value['underlineOffset'] ) {
			$style_declarations->add( 'text-underline-offset', $attr_value['underlineOffset'] );
		}

		if ( isset( $attr_value['textWrap'] ) && '' !== (string) $attr_value['textWrap'] ) {
			$style_declarations->add( 'text-wrap', $attr_value['textWrap'] );
		}

		if ( isset( $attr_value['hyphens'] ) && '' !== (string) $attr_value['hyphens'] ) {
			if ( 'on' === $attr_value['hyphens'] ) {
				$style_declarations->add( 'hyphens', 'auto' );
				$style_declarations->add( 'word-wrap', 'break-word' );
			} elseif ( 'off' === $attr_value['hyphens'] ) {
				$style_declarations->add( 'hyphens', 'none' );
			} else {
				$style_declarations->add( 'hyphens', $attr_value['hyphens'] );
			}
		}

		$column_gap = isset( $attr_value['columnGap'] ) ? strtolower( trim( (string) $attr_value['columnGap'] ) ) : '';

		// Keep default "0px" gap as UI-only unless user sets a non-zero gap.
		if ( '' !== $column_gap && ! in_array( $column_gap, [ '0', '0px', '0em', '0rem', '0%' ], true ) ) {
			$style_declarations->add( 'column-gap', $attr_value['columnGap'] );
		}

		if ( isset( $attr_value['columnCount'] ) && '' !== (string) $attr_value['columnCount'] ) {
			$column_count                          = (float) $attr_value['columnCount'];
			$inherited_column_count                = isset( $inherited_attr_value['columnCount'] ) && is_numeric( $inherited_attr_value['columnCount'] )
				? (float) $inherited_attr_value['columnCount']
				: null;
			$default_column_count                  = isset( $default_attr_value['columnCount'] ) && is_numeric( $default_attr_value['columnCount'] )
				? (float) $default_attr_value['columnCount']
				: null;
			$has_multi_column_inherited_or_default = ( null !== $inherited_column_count && $inherited_column_count > 1 )
				|| ( null !== $default_column_count && $default_column_count > 1 );

			// Keep the default "1" column as UI-only and avoid emitting unnecessary CSS.
			if ( $column_count > 1 ) {
				$style_declarations->add( 'column-count', $attr_value['columnCount'] );

				if ( isset( $attr_value['columnRuleWidth'] ) && '' !== (string) $attr_value['columnRuleWidth'] ) {
					$style_declarations->add( 'column-rule-width', $attr_value['columnRuleWidth'] );
				}

				$column_rule_width           = isset( $attr_value['columnRuleWidth'] ) ? strtolower( trim( (string) $attr_value['columnRuleWidth'] ) ) : '';
				$non_zero_column_rule_values = [ '0', '0px', '0em', '0rem', '0%' ];
				$parsed_column_rule_width    = is_numeric( $column_rule_width ) ? (float) $column_rule_width : null;
				$has_non_zero_rule_width     = '' !== $column_rule_width
					&& ! in_array( $column_rule_width, $non_zero_column_rule_values, true )
					&& ( null === $parsed_column_rule_width || $parsed_column_rule_width > 0 );

				if ( isset( $attr_value['columnRuleStyle'] ) && '' !== (string) $attr_value['columnRuleStyle'] ) {
					$style_declarations->add( 'column-rule-style', $attr_value['columnRuleStyle'] );
				} elseif ( $has_non_zero_rule_width ) {
					$style_declarations->add( 'column-rule-style', $default_attr_value['columnRuleStyle'] ?? 'solid' );
				}

				if ( isset( $attr_value['columnRuleColor'] ) && '' !== (string) $attr_value['columnRuleColor'] ) {
					$style_declarations->add( 'column-rule-color', $attr_value['columnRuleColor'] );
				} elseif ( $has_non_zero_rule_width ) {
					$style_declarations->add( 'column-rule-color', $default_attr_value['columnRuleColor'] ?? '#333' );
				}
			} elseif ( 1.0 === $column_count && $has_multi_column_inherited_or_default ) {
				$style_declarations->add( 'column-count', '1' );
			}
		}

		if ( isset( $attr_value['color'] ) ) {
			$style_declarations->add( 'color', $attr_value['color'] );
		}

		if ( isset( $attr_value['size'] ) ) {
			// Normalize font-size to ensure it has a unit.
			// Add 'px' as default unit for unitless numeric values.
			// This handles migrated D4 layouts that may have unitless font-size values.
			$font_size = $attr_value['size'];

			// Check if value is numeric without unit.
			if ( is_numeric( $font_size ) ) {
				// Value is purely numeric - add 'px' unit.
				$font_size = $font_size . 'px';
			}

			$style_declarations->add( 'font-size', $font_size );
		}

		if ( isset( $attr_value['letterSpacing'] ) ) {
			$style_declarations->add( 'letter-spacing', $attr_value['letterSpacing'] );
		}

		if ( isset( $attr_value['lineHeight'] ) ) {
			$style_declarations->add( 'line-height', $attr_value['lineHeight'] );
		}

		if ( isset( $attr_value['dropCapLineSize'] ) && '' !== (string) $attr_value['dropCapLineSize'] ) {
			$style_declarations->add( 'initial-letter', $attr_value['dropCapLineSize'] );

			// Fallback for browsers that don't support `initial-letter`.
			$style_declarations->add( 'line-height', '1' );
			$style_declarations->add( 'font-size', 'calc(' . $attr_value['dropCapLineSize'] . ' * 1em)' );
		}

		if ( isset( $attr_value['dropCapSpacing'] ) && '' !== (string) $attr_value['dropCapSpacing'] ) {
			$style_declarations->add( 'margin-inline-end', $attr_value['dropCapSpacing'] );
		}

		$inherited_writing_mode = isset( $inherited_attr_value['writingMode'] )
			? (string) $inherited_attr_value['writingMode']
			: '';
		$default_writing_mode   = isset( $default_attr_value['writingMode'] )
			? (string) $default_attr_value['writingMode']
			: '';
		$writing_mode_to_reset  = '' !== $inherited_writing_mode ? $inherited_writing_mode : $default_writing_mode;

		if ( isset( $attr_value['writingMode'] ) && '' !== (string) $attr_value['writingMode'] ) {
			$is_vertical_lr = 'vertical-lr' === $attr_value['writingMode'];

			if ( 'vertical-lr' === $attr_value['writingMode'] ) {
				$style_declarations->add( 'writing-mode', 'vertical-rl' );
				$style_declarations->add( 'transform', 'rotate(180deg)' );
			} elseif ( 'vertical-lr' === $writing_mode_to_reset ) {
				$style_declarations->add( 'transform', 'none' );
			}

			if ( 'horizontal-tb' === $attr_value['writingMode'] ) {
				if ( '' !== $writing_mode_to_reset && 'horizontal-tb' !== $writing_mode_to_reset ) {
					$style_declarations->add( 'writing-mode', 'horizontal-tb' );
				}
			} elseif ( ! $is_vertical_lr && 'horizontal-tb' !== $attr_value['writingMode'] ) {
				$style_declarations->add( 'writing-mode', $attr_value['writingMode'] );
			}
		}

		if ( isset( $attr_value['textAlign'] ) ) {
			$style_declarations->add( 'text-align', $attr_value['textAlign'] );
		}

		return $style_declarations->value();
	}
}
