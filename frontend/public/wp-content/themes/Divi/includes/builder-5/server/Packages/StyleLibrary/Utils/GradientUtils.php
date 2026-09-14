<?php
/**
 * Gradient style utility methods.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\StyleLibrary\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Utility\ArrayUtility;
use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Declarations\Background\Utils\BackgroundStyleUtils;
use ET\Builder\Packages\StyleLibrary\Declarations\Sizing\Sizing;
use ET\Builder\Packages\StyleLibrary\Utils\GlobalVariableReferenceUtils;

/**
 * Utility class for gradient style declarations.
 *
 * @since ??
 */
class GradientUtils {

	/**
	 * Get resolved default gradient for a breakpoint/state from default printed attrs.
	 *
	 * Falls back to nearest larger breakpoint when current breakpoint does not define
	 * gradient defaults, then resolves tokenized stops to full gradient settings.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of args.
	 *
	 *     @type array       $defaultPrintedStyleAttr Responsive default printed style attr map.
	 *     @type string|null $breakpoint              Current breakpoint.
	 *     @type string      $state                   Current state. Default `value`.
	 *     @type array       $fallbackGradient        Fallback gradient settings.
	 * }
	 *
	 * @return array
	 */
	public static function get_resolved_default_gradient_for_breakpoint( array $args ): array {
		$args = wp_parse_args(
			$args,
			[
				'state'            => 'value',
				'fallbackGradient' => [],
			]
		);

		$default_printed_style_attr = $args['defaultPrintedStyleAttr'] ?? [];
		$breakpoint                 = $args['breakpoint'] ?? null;
		$state                      = $args['state'] ?? 'value';
		$fallback_gradient          = $args['fallbackGradient'] ?? [];

		$default_gradient_from_printed_attr = null;

		if ( is_array( $default_printed_style_attr ) && is_string( $breakpoint ) ) {
			$default_gradient_from_printed_attr = ModuleUtils::get_attr_subname_value(
				[
					'attr'         => $default_printed_style_attr,
					'subname'      => 'gradient',
					'breakpoint'   => $breakpoint,
					'state'        => $state,
					'mode'         => 'getAndInheritClosest',
					'defaultValue' => null,
				]
			);
		}

		$default_gradient = is_array( $fallback_gradient ) ? $fallback_gradient : [];
		if ( is_array( $default_gradient_from_printed_attr ) ) {
			$default_gradient = array_merge( $default_gradient, $default_gradient_from_printed_attr );
		}

		if ( isset( $default_gradient['stops'] ) && is_string( $default_gradient['stops'] ) ) {
			$resolved_default_gradient = GlobalData::resolve_global_gradient_variable( $default_gradient['stops'] );

			if ( is_array( $resolved_default_gradient ) && ! empty( $resolved_default_gradient ) ) {
				$default_gradient = array_merge( $default_gradient, $resolved_default_gradient );
			}
		}

		return $default_gradient;
	}

	/**
	 * Resolve inherited gradient settings from larger breakpoint gradient token.
	 *
	 * This is used when current breakpoint only overrides `gradient.stops` with
	 * explicit color stops array and needs to inherit type/direction metadata.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of args.
	 *
	 *     @type array  $attr       Full responsive gradient attr structure.
	 *     @type string $breakpoint Current breakpoint.
	 *     @type string $state      Current state. Default `value`.
	 *     @type array  $gradient   Current merged gradient value.
	 * }
	 *
	 * @return array
	 */
	public static function get_inherited_gradient_resolved_settings( array $args ): array {
		$args = wp_parse_args(
			$args,
			[
				'state' => 'value',
			]
		);

		$attr       = $args['attr'] ?? null;
		$breakpoint = $args['breakpoint'] ?? null;
		$state      = $args['state'] ?? 'value';
		$gradient   = $args['gradient'] ?? null;

		if (
			! is_array( $attr ) ||
			! is_string( $breakpoint ) ||
			! is_array( $gradient ) ||
			! isset( $gradient['stops'] ) ||
			! is_array( $gradient['stops'] )
		) {
			return [];
		}

		$breakpoint_names = Breakpoint::get_enabled_breakpoint_names();
		$current_index    = array_search( $breakpoint, $breakpoint_names, true );

		if ( false === $current_index ) {
			return [];
		}

		for ( $i = $current_index - 1; $i >= 0; $i-- ) {
			$larger_gradient_stops = $attr[ $breakpoint_names[ $i ] ][ $state ]['gradient']['stops'] ?? null;

			if ( is_string( $larger_gradient_stops ) && '' !== $larger_gradient_stops ) {
				$resolved_gradient = GlobalData::resolve_global_gradient_variable( $larger_gradient_stops );

				if ( is_array( $resolved_gradient ) && ! empty( $resolved_gradient ) ) {
					return $resolved_gradient;
				}

				break;
			}
		}

		return [];
	}

	/**
	 * Build effective gradient settings for a breakpoint/state.
	 *
	 * This helper consolidates common gradient inheritance behavior:
	 * - Fill missing gradient sub-fields from closest inherited attr value.
	 * - Resolve inherited token-based settings from larger breakpoints.
	 * - Merge default, inherited resolved, and current gradient in proper order.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of args.
	 *
	 *     @type array|null $attr            Full responsive attr structure.
	 *     @type string|null $breakpoint     Current breakpoint.
	 *     @type string $state               Current state. Default `value`.
	 *     @type array $defaultGradient      Default gradient settings.
	 *     @type array $currentGradient      Current gradient settings.
	 *     @type array $gradientSubFields    Optional. Gradient subfields to inherit when missing.
	 * }
	 *
	 * @return array
	 */
	public static function get_effective_gradient_for_breakpoint( array $args ): array {
		$args = wp_parse_args(
			$args,
			[
				'state'             => 'value',
				'defaultGradient'   => [],
				'currentGradient'   => [],
				'gradientSubFields' => [
					'enabled',
					'stops',
					'type',
					'direction',
					'directionRadial',
					'repeat',
					'length',
				],
			]
		);

		$attr                = $args['attr'] ?? null;
		$breakpoint          = $args['breakpoint'] ?? null;
		$state               = $args['state'] ?? 'value';
		$default_gradient    = $args['defaultGradient'] ?? [];
		$current_gradient    = $args['currentGradient'] ?? [];
		$gradient_sub_fields = $args['gradientSubFields'] ?? [];

		$gradient = is_array( $current_gradient ) ? $current_gradient : [];

		if ( is_array( $attr ) && is_string( $breakpoint ) && is_array( $gradient_sub_fields ) ) {
			foreach ( $gradient_sub_fields as $gradient_sub_field ) {
				if ( ! is_string( $gradient_sub_field ) || array_key_exists( $gradient_sub_field, $gradient ) ) {
					continue;
				}

				$inherited_sub_value = ModuleUtils::get_attr_subname_value(
					[
						'attr'         => $attr,
						'subname'      => "gradient.{$gradient_sub_field}",
						'breakpoint'   => $breakpoint,
						'state'        => $state,
						'mode'         => 'getAndInheritClosest',
						'defaultValue' => null,
					]
				);

				if ( null !== $inherited_sub_value ) {
					$gradient[ $gradient_sub_field ] = $inherited_sub_value;
				}
			}
		}

		$inherited_gradient_resolved_settings = self::get_inherited_gradient_resolved_settings(
			[
				'attr'       => $attr,
				'breakpoint' => $breakpoint,
				'state'      => $state,
				'gradient'   => $gradient,
			]
		);
		$current_gradient_resolved_settings   = [];

		if ( isset( $gradient['stops'] ) && is_string( $gradient['stops'] ) ) {
			$resolved_gradient_settings = GlobalData::resolve_global_gradient_variable( $gradient['stops'] );

			if ( is_array( $resolved_gradient_settings ) && ! empty( $resolved_gradient_settings ) ) {
				$current_gradient_resolved_settings = $resolved_gradient_settings;
			}
		}

		return array_merge(
			is_array( $default_gradient ) ? $default_gradient : [],
			$inherited_gradient_resolved_settings,
			$current_gradient_resolved_settings,
			$gradient
		);
	}

	/**
	 * Sanitize CSS fragment value.
	 *
	 * @since ??
	 *
	 * @param mixed $value Raw CSS fragment value.
	 *
	 * @return string
	 */
	private static function _sanitize_css_fragment( $value ): string {
		return Utils::resolve_and_sanitize_css_scalar_value( $value );
	}

	/**
	 * Split a CSS fragment into top-level whitespace-separated tokens.
	 *
	 * @since ??
	 *
	 * @param string $value CSS fragment.
	 *
	 * @return array<string>
	 */
	private static function _split_top_level_css_tokens( string $value ): array {
		$tokens = [];
		$token  = '';
		$depth  = 0;
		$length = strlen( $value );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $value[ $i ];

			if ( '(' === $char ) {
				++$depth;
				$token .= $char;
				continue;
			}

			if ( ')' === $char ) {
				$depth  = max( 0, $depth - 1 );
				$token .= $char;
				continue;
			}

			if ( 0 === $depth && preg_match( '/\s/', $char ) ) {
				if ( '' !== $token ) {
					$tokens[] = $token;
					$token    = '';
				}
				continue;
			}

			$token .= $char;
		}

		if ( '' !== $token ) {
			$tokens[] = $token;
		}

		return $tokens;
	}

	/**
	 * Check whether the provided color is a strict plain hsl/hsla() function.
	 *
	 * @since ??
	 *
	 * @param string $color Candidate color.
	 *
	 * @return bool
	 */
	private static function _is_safe_plain_hsl_function( string $color ): bool {
		// Regex test: https://regex101.com/r/o7fqSq/1/.
		return 1 === preg_match(
			'/(hsl|hsla)\(\s*-?(?:\d+|\d+\.\d+|\.\d+)(?:deg|grad|rad|turn)?\s*,\s*-?(?:\d+|\d+\.\d+|\.\d+)%\s*,\s*-?(?:\d+|\d+\.\d+|\.\d+)%(?:\s*(?:,|\/)\s*(?:0|1|0?\.\d+|(?:\d+|\d+\.\d+|\.\d+)%))?\s*\)/i',
			$color
		);
	}

	/**
	 * Check whether the provided color is a strict plain rgb/rgba() function.
	 *
	 * @since ??
	 *
	 * @param string $color Candidate color.
	 *
	 * @return bool
	 */
	private static function _is_safe_plain_rgb_function( string $color ): bool {
		// Regex test: https://regex101.com/r/aASuAJ/1/.
		return 1 === preg_match(
			'/(rgb|rgba)\(\s*-?(?:\d+|\d+\.\d+|\.\d+)%?\s*,\s*-?(?:\d+|\d+\.\d+|\.\d+)%?\s*,\s*-?(?:\d+|\d+\.\d+|\.\d+)%?(?:\s*(?:,|\/)\s*(?:0|1|0?\.\d+|(?:\d+|\d+\.\d+|\.\d+)%))?\s*\)/i',
			$color
		);
	}

	/**
	 * Check whether a token is an allowed relative-HSL source token.
	 *
	 * @since ??
	 *
	 * @param string $source Candidate source token.
	 * @param int    $depth  Current recursive depth.
	 *
	 * @return bool
	 */
	private static function _is_safe_hsl_source_token( string $source, int $depth = 0 ): bool {
		if ( 4 < $depth ) {
			return false;
		}

		$source_trimmed = trim( $source );

		if ( '' === $source_trimmed ) {
			return false;
		}

		// Regex test: https://regex101.com/r/7ABUyq/1/unit-tests.
		if ( 1 === preg_match( '/^var\(--[a-z0-9\-_]+\)$/i', $source_trimmed ) ) {
			return true;
		}
		// Regex test: https://regex101.com/r/lK40oc/1/unit-tests.
		if ( 1 === preg_match( '/^#[a-f0-9]{3,8}$/i', $source_trimmed ) ) {
			return true;
		}

		if ( 'currentcolor' === strtolower( $source_trimmed ) ) {
			return true;
		}

		if ( self::_is_safe_plain_rgb_function( $source_trimmed ) || self::_is_safe_plain_hsl_function( $source_trimmed ) ) {
			return true;
		}

		return self::_is_safe_relative_hsl_function( $source_trimmed, $depth + 1 );
	}

	/**
	 * Check whether the provided color is a strict relative hsl/hsla() function.
	 *
	 * @since ??
	 *
	 * @param string $color Candidate color.
	 * @param int    $depth Current recursive depth.
	 *
	 * @return bool
	 */
	private static function _is_safe_relative_hsl_function( string $color, int $depth = 0 ): bool {
		if ( 4 < $depth ) {
			return false;
		}

		$trimmed_color = trim( $color );

		// Reject declaration-break payloads early.
		if ( str_contains( $trimmed_color, ';' ) ) {
			return false;
		}

		// Regex test: https://regex101.com/r/OBY5aM/1/unit-tests.
		if ( 1 !== preg_match( '/^(hsl|hsla)\(([^;]*)\)$/is', $trimmed_color, $matches ) ) {
			return false;
		}

		$inner = trim( $matches[2] ?? '' );
		if ( ! str_starts_with( strtolower( $inner ), 'from ' ) ) {
			return false;
		}

		$after_from = trim( substr( $inner, 5 ) );
		$tokens     = self::_split_top_level_css_tokens( $after_from );

		if ( 4 > count( $tokens ) ) {
			return false;
		}

		$source_token = array_shift( $tokens );
		$h_component  = array_shift( $tokens );
		$s_component  = array_shift( $tokens );
		$l_component  = array_shift( $tokens );
		$alpha_tail   = trim( implode( ' ', $tokens ) );

		if ( ! is_string( $source_token ) || ! self::_is_safe_hsl_source_token( $source_token, $depth + 1 ) ) {
			return false;
		}

		// Regex test: https://regex101.com/r/UBIWS7/1/unit-tests.
		if (
			! is_string( $h_component ) || 1 !== preg_match( '/^calc\(\s*h\s*[+\-]\s*-?(?:\d+|\d+\.\d+|\.\d+)(?:deg)?\s*\)$/i', $h_component ) ||
			! is_string( $s_component ) || 1 !== preg_match( '/^calc\(\s*s\s*[+\-]\s*-?(?:\d+|\d+\.\d+|\.\d+)%?\s*\)$/i', $s_component ) ||
			! is_string( $l_component ) || 1 !== preg_match( '/^calc\(\s*l\s*[+\-]\s*-?(?:\d+|\d+\.\d+|\.\d+)%?\s*\)$/i', $l_component )
		) {
			return false;
		}

		if ( '' === $alpha_tail ) {
			return true;
		}

		// Regex test: https://regex101.com/r/7O05ue/1/unit-tests.
		return 1 === preg_match( '/^\/\s*(?:0|1|0?\.\d+|(?:\d+|\d+\.\d+|\.\d+)%)$/', $alpha_tail );
	}

	/**
	 * Replace safe hsl()/hsla() functions in a CSS value with a placeholder color.
	 *
	 * @since ??
	 *
	 * @param string $value             CSS value.
	 * @param int    $placeholder_count Number of replacements made.
	 *
	 * @return string
	 */
	private static function _replace_safe_hsl_functions_with_placeholder( string $value, int &$placeholder_count ): string {
		$placeholder_count = 0;
		$result            = '';
		$length            = strlen( $value );

		for ( $i = 0; $i < $length; $i++ ) {
			$prefix_4       = strtolower( substr( $value, $i, 4 ) );
			$prefix_5       = strtolower( substr( $value, $i, 5 ) );
			$is_hsl_start   = 'hsl(' === $prefix_4 || 'hsla(' === $prefix_5;
			$has_word_bound = 0 === $i || 1 !== preg_match( '/[a-z0-9\-_]/i', $value[ $i - 1 ] );

			if ( ! $is_hsl_start || ! $has_word_bound ) {
				$result .= $value[ $i ];
				continue;
			}

			$open_paren_index = $i + ( 'hsl(' === $prefix_4 ? 3 : 4 );
			$depth            = 0;
			$end_index        = null;

			for ( $j = $open_paren_index; $j < $length; $j++ ) {
				if ( '(' === $value[ $j ] ) {
					++$depth;
				} elseif ( ')' === $value[ $j ] ) {
					--$depth;

					if ( 0 === $depth ) {
						$end_index = $j;
						break;
					}
				}
			}

			if ( ! is_int( $end_index ) ) {
				$result .= $value[ $i ];
				continue;
			}

			$hsl_token = substr( $value, $i, $end_index - $i + 1 );
			if ( self::_is_safe_plain_hsl_function( $hsl_token ) || self::_is_safe_relative_hsl_function( $hsl_token ) ) {
				$result .= '#000000';
				++$placeholder_count;
			} else {
				$result .= $hsl_token;
			}

			$i = $end_index;
		}

		return $result;
	}

	/**
	 * Sanitize CSS property value using WordPress CSS sanitizer.
	 *
	 * @since ??
	 *
	 * @param string $property CSS property name.
	 * @param mixed  $value    Raw CSS property value.
	 *
	 * @return string
	 */
	private static function _sanitize_css_property_value( string $property, $value ): string {
		$sanitized_value = self::_sanitize_css_fragment( $value );

		if ( '' === $sanitized_value ) {
			return '';
		}

		$sanitized_declaration = safecss_filter_attr( "{$property}: {$sanitized_value};" );

		// WordPress safecss_filter_attr() can reject gradients containing strict global
		// variable tokens and relative HSL expressions in color stops. Validate with
		// strict placeholders that only match trusted token forms (no var() fallback)
		// to avoid re-emitting user-supplied payloads hidden inside tokens.
		if (
			( ! is_string( $sanitized_declaration ) || '' === trim( $sanitized_declaration ) ) &&
			'background-image' === $property &&
			str_contains( $sanitized_value, 'gradient(' )
		) {
			$placeholder_count = 0;
			$placeholder_value = $sanitized_value;

			if ( is_string( $placeholder_value ) ) {
				$hsl_placeholder_count = 0;
				$placeholder_value     = self::_replace_safe_hsl_functions_with_placeholder(
					$placeholder_value,
					$hsl_placeholder_count
				);
				$placeholder_count    += (int) $hsl_placeholder_count;
			}

			if ( is_string( $placeholder_value ) ) {
				$placeholder_value = preg_replace(
					// Regex test: https://regex101.com/r/4pwHby/2.
					'/var\(--[a-z0-9\-_]+\)/i',
					'#000000',
					$placeholder_value,
					-1,
					$var_placeholder_count
				);
				$placeholder_count += (int) $var_placeholder_count;
			}

			if ( is_string( $placeholder_value ) && 0 < $placeholder_count ) {
				$placeholder_declaration = safecss_filter_attr( "{$property}: {$placeholder_value};" );

				if ( is_string( $placeholder_declaration ) && '' !== trim( $placeholder_declaration ) ) {
					$sanitized_declaration = "{$property}: {$sanitized_value};";
				}
			}
		}

		if ( ! is_string( $sanitized_declaration ) || '' === trim( $sanitized_declaration ) ) {
			return '';
		}

		$declaration_parts = explode( ':', $sanitized_declaration, 2 );
		if ( 2 !== count( $declaration_parts ) ) {
			return '';
		}

		$sanitized_property = trim( $declaration_parts[0] );
		if ( $property !== $sanitized_property ) {
			return '';
		}

		$clean_value = rtrim( trim( $declaration_parts[1] ), ';' );

		return trim( $clean_value );
	}

	/**
	 * Validate gradient stop position.
	 *
	 * @since ??
	 *
	 * @param mixed $position Gradient stop position.
	 *
	 * @return string
	 */
	private static function _sanitize_gradient_stop_position( $position ): string {
		$position_value = self::_sanitize_css_fragment( $position );

		// Regex test: https://regex101.com/r/dn5Kuq/1.
		if ( '' === $position_value || 1 !== preg_match( '/^-?(?:\d+|\d+\.\d+|\.\d+)$/', $position_value ) ) {
			return '';
		}

		return $position_value;
	}

	/**
	 * Sanitize gradient stop color value.
	 *
	 * @since ??
	 *
	 * @param mixed $color Raw stop color value.
	 *
	 * @return string
	 */
	private static function _sanitize_gradient_stop_color( $color ): string {
		$sanitized_color = self::_sanitize_css_fragment( $color );
		if ( '' === $sanitized_color ) {
			return '';
		}

		$resolved_color = Utils::resolve_global_color_to_value( $sanitized_color );
		if ( ! is_string( $resolved_color ) ) {
			return '';
		}

		return trim( $resolved_color );
	}

	/**
	 * Sanitize gradient angle value.
	 *
	 * @since ??
	 *
	 * @param mixed  $value   Candidate angle.
	 * @param string $fallback Fallback angle.
	 *
	 * @return string
	 */
	private static function _sanitize_gradient_angle( $value, string $fallback = '180deg' ): string {
		$sanitized_value = self::_sanitize_css_fragment( $value );

		// Regex test: https://regex101.com/r/LGZYDi/1/unit-tests.
		if ( 1 === preg_match( '/^-?(?:\d+|\d+\.\d+|\.\d+)(?:deg|grad|rad|turn)$/i', $sanitized_value ) ) {
			return $sanitized_value;
		}

		return $fallback;
	}

	/**
	 * Sanitize gradient position keyword(s).
	 *
	 * @since ??
	 *
	 * @param mixed  $value   Candidate position.
	 * @param string $fallback Fallback position.
	 *
	 * @return string
	 */
	private static function _sanitize_gradient_position( $value, string $fallback = 'center' ): string {
		$sanitized_value = strtolower( self::_sanitize_css_fragment( $value ) );

		// Regex test: https://regex101.com/r/hg0uCd/1/.
		if ( 1 === preg_match( '/^(?:center|top|bottom|left|right)(?:\s+(?:center|top|bottom|left|right))?$/', $sanitized_value ) ) {
			return $sanitized_value;
		}

		return $fallback;
	}

	/**
	 * Sanitize linear gradient direction.
	 *
	 * @since ??
	 *
	 * @param mixed $value Candidate linear direction.
	 *
	 * @return string
	 */
	private static function _sanitize_linear_gradient_direction( $value ): string {
		$sanitized_value    = strtolower( self::_sanitize_css_fragment( $value ) );
		$allowed_directions = [
			'to top',
			'to top right',
			'to right',
			'to bottom right',
			'to bottom',
			'to bottom left',
			'to left',
			'to top left',
		];
		$is_keyword_valid   = in_array( $sanitized_value, $allowed_directions, true );
		// Regex test: https://regex101.com/r/CFKEFR/1.
		$is_angle_valid = 1 === preg_match( '/^-?(?:\d+|\d+\.\d+|\.\d+)(?:deg|grad|rad|turn)$/i', $sanitized_value );

		if ( $is_keyword_valid || $is_angle_valid ) {
			return $sanitized_value;
		}

		return '180deg';
	}

	/**
	 * Style declaration for gradient background.
	 *
	 * @since ??
	 *
	 * @param array $gradient Gradient settings.
	 *
	 * @return string
	 */
	public static function gradient_style_declaration( array $gradient ): string {
		$type           = null;
		$direction      = null;
		$stops          = [];
		$length_default = '100%';
		$unit_default   = BackgroundStyleUtils::get_unit_from_length( $length_default );
		$length         = isset( $gradient['length'] ) ? self::_sanitize_css_property_value( 'width', $gradient['length'] ) : $length_default;
		$length         = '' !== $length ? $length : $length_default;
		$sizing_units   = Sizing::$sizing_units;
		$unit_find      = ArrayUtility::find(
			$sizing_units,
			function ( $sizing_unit ) use ( $length ) {
				return BackgroundStyleUtils::get_unit_from_length( $length ) === $sizing_unit;
			}
		);
		$unit           = null !== $unit_find ? $unit_find : $unit_default;
		$max_length     = $length ? " {$length}" : '';

		if ( isset( $gradient['stops'] ) && is_string( $gradient['stops'] ) ) {
			$gradient_variable_id = '';
			$gradient_stops_value = $gradient['stops'];

			if ( str_starts_with( $gradient_stops_value, 'var(--gvid-' ) ) {
				preg_match( '/--(gvid-[a-z0-9\-]+)/i', $gradient_stops_value, $global_variable_match );
				$gradient_variable_id = GlobalVariableReferenceUtils::sanitize_variable_id( $global_variable_match[1] ?? '', 'gvid' );
			} elseif ( str_starts_with( $gradient_stops_value, 'gvid-' ) ) {
				$gradient_variable_id = GlobalVariableReferenceUtils::sanitize_variable_id( $gradient_stops_value, 'gvid' );
			} elseif ( str_starts_with( $gradient_stops_value, '$variable(' ) && '$' === substr( $gradient_stops_value, -1 ) ) {
				$variable_content = substr( $gradient_stops_value, 10, -2 );
				$variable_data    = json_decode( $variable_content, true );
				$variable_name    = $variable_data['value']['name'] ?? '';

				if (
					is_array( $variable_data ) &&
					'gradient' === ( $variable_data['type'] ?? '' ) &&
					is_string( $variable_name ) &&
					str_starts_with( $variable_name, 'gvid-' )
				) {
					$gradient_variable_id = GlobalVariableReferenceUtils::sanitize_variable_id( $variable_name, 'gvid' );
				}
			}

			if ( '' !== $gradient_variable_id ) {
				return "var(--{$gradient_variable_id})";
			}

			$resolved_gradient_settings = GlobalData::resolve_global_gradient_variable( $gradient['stops'] );

			if ( is_array( $resolved_gradient_settings ) && ! empty( $resolved_gradient_settings ) ) {
				$gradient = array_merge( $resolved_gradient_settings, $gradient );

				// When local stops is still a token string, use resolved concrete stops so
				// CSS can be rendered while preserving local non-stop overrides (e.g. type).
				if ( isset( $resolved_gradient_settings['stops'] ) && is_string( $gradient['stops'] ) ) {
					$gradient['stops'] = $resolved_gradient_settings['stops'];
				}
			}
		}

		if ( isset( $gradient['stops'] ) && is_array( $gradient['stops'] ) && count( $gradient['stops'] ) >= 2 ) {
			foreach ( $gradient['stops'] as $stop ) {
				$color    = self::_sanitize_gradient_stop_color( $stop['color'] ?? '' );
				$position = self::_sanitize_gradient_stop_position( $stop['position'] ?? '' );

				if ( '' === $color || '' === $position ) {
					continue;
				}

				$stop_string = "{$color} {$position}{$unit}";
				$stops[]     = $stop_string;
			}
		}

		switch ( $gradient['type'] ?? 'linear' ) {
			case 'conic':
				$type      = 'conic';
				$direction = 'from ' . self::_sanitize_gradient_angle( $gradient['direction'] ?? '180deg' ) . ' at ' . self::_sanitize_gradient_position( $gradient['directionRadial'] ?? 'center' );
				break;
			case 'elliptical':
				$type      = 'radial';
				$direction = 'ellipse at ' . self::_sanitize_gradient_position( $gradient['directionRadial'] ?? 'center' );
				break;
			case 'circular':
				$type      = 'radial';
				$direction = 'circle at ' . self::_sanitize_gradient_position( $gradient['directionRadial'] ?? 'center' );
				break;
			case 'linear':
			default:
				$type      = 'linear';
				$direction = self::_sanitize_linear_gradient_direction( $gradient['direction'] ?? '180deg' );
		}

		$direction = '' !== $direction ? $direction : '180deg';

		// Apply gradient repeat (if set).
		$type = isset( $gradient['repeat'] ) && 'on' === $gradient['repeat'] ? "repeating-{$type}" : $type;

		// Check if last stop's position equals $max_length to avoid duplicate position values.
		$should_append_max_length = true;
		if ( ! empty( $stops ) && ! empty( $max_length ) ) {
			$last_stop = end( $stops );
			if ( preg_match( '/\s+(\d+(?:\.\d+)?' . preg_quote( $unit, '/' ) . ')$/', $last_stop, $matches ) ) {
				$last_stop_position = $matches[1];
				if ( $last_stop_position === $length ) {
					$should_append_max_length = false;
				}
			}
		}

		$max_length_append = $should_append_max_length ? $max_length : '';

		$final_css = "{$type}-gradient({$direction}, " . implode( ',', $stops ) . "{$max_length_append})";
		return self::_sanitize_css_property_value( 'background-image', $final_css );
	}
}
