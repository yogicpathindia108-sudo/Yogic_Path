<?php
/**
 * Background::style_declaration()
 *
 * @package Builder\FrontEnd
 * @since ??
 */

namespace ET\Builder\Packages\StyleLibrary\Declarations\Background\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\StyleLibrary\Utils\GlobalVariableReferenceUtils;
use ET\Builder\Packages\StyleLibrary\Utils\GradientUtils;
use ET\Builder\Packages\StyleLibrary\Utils\Utils;
use ET\Builder\Packages\StyleLibrary\Declarations\Background\Background;
use ET\Builder\Packages\StyleLibrary\Declarations\Background\Utils\BackgroundStyleUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Framework\Breakpoint\Breakpoint;

trait StyleDeclarationTrait {

	/**
	 * Build safe CSS background-image value from image URL.
	 *
	 * @since ??
	 *
	 * @param mixed $url Raw image URL or variable token.
	 *
	 * @return string
	 */
	private static function _get_safe_background_image_value( $url ): string {
		$raw_url = $url;
		$url     = Utils::resolve_and_sanitize_css_scalar_value( $url );

		if ( '' === $url ) {
			return '';
		}

		$sanitized_global_variable_reference = GlobalVariableReferenceUtils::sanitize_css_reference( $url, 'gvid' );
		if ( '' !== $sanitized_global_variable_reference ) {
			return $sanitized_global_variable_reference;
		}

		$is_dynamic_variable_token = is_scalar( $raw_url ) && is_string( $raw_url ) && str_contains( $raw_url, '$variable(' );
		$is_dynamic_variable       = 1 === preg_match( '/^var\(--[a-z0-9\-_]+\)$/i', $url );
		if ( $is_dynamic_variable_token && $is_dynamic_variable ) {
			return "url({$url})";
		}

		$sanitized_image_variable = GlobalVariableReferenceUtils::resolve_and_sanitize_image_css_reference( $raw_url, 'gvid' );
		if ( '' !== $sanitized_image_variable ) {
			return $sanitized_image_variable;
		}

		return Utils::sanitize_non_variable_image_url_css_reference( $url );
	}

	/**
	 * Generate background CSS declaration, dynamically comparing with parent breakpoint.
	 * Only outputs properties that differ from parent to prevent duplicate CSS.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/style-library/background-style-declaration backgroundStyleDeclaration} in:
	 * `@divi/style-library` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array      $attr       Original attribute structure for dynamic breakpoint comparison.
	 *     @type array      $attrValue  Processed background attribute value.
	 *     @type string     $breakpoint Current breakpoint being processed.
	 *     @type string     $state      Current state (value, hover, sticky).
	 *     @type bool|array $important  Optional. Whether declarations should be marked important.
	 *     @type string     $returnType Optional. Return type ('string' or 'key_value_pair').
	 *     @type string     $keyFormat  Optional. Key format for declarations.
	 *     @type bool       $hasBackgroundPresets Optional. Whether presets are actively applied. Default `false`.
	 * }
	 *
	 * @return array|string
	 */
	public static function style_declaration( array $args ) {
		$args = wp_parse_args(
			$args,
			[
				'important'            => false,
				'returnType'           => 'string',
				'keyFormat'            => 'param-case',
				'hasBackgroundPresets' => false,
			]
		);

		$important              = $args['important'];
		$return_type            = $args['returnType'];
		$key_format             = $args['keyFormat'];
		$has_background_presets = $args['hasBackgroundPresets'];
		$breakpoint             = $args['breakpoint'] ?? null;
		$state                  = $args['state'] ?? 'value';
		$attr                   = $args['attr'] ?? null;
		$attr_value             = $args['attrValue'] ?? null;
		$preview                = $attr_value['preview'] ?? false;
		$color                  = $attr_value['color'] ?? null;
		$gradient               = $attr_value['gradient'] ?? null;
		$image                  = $attr_value['image'] ?? [];
		$style_declarations     = new StyleDeclarations(
			[
				'important'  => $important,
				'returnType' => $return_type,
				'keyFormat'  => $key_format,
			]
		);

		$is_image_not_enabled    = is_array( $image ) && array_key_exists( 'enabled', $image ) && 'off' === $image['enabled'];
		$is_gradient_not_enabled = is_array( $gradient ) && array_key_exists( 'enabled', $gradient ) && 'off' === $gradient['enabled'];

		$background_default_attr = Background::$background_default_attr;
		$default_attr            = $attr_value['defaultAttr'] ?? $background_default_attr;
		$default_attr            = array_merge( $background_default_attr, $default_attr );
		$background_images       = [];

		// Extract responsive width, height, and URL values for current breakpoint.
		// Use full responsive attr structure if available, otherwise fallback to attrValue.
		$responsive_width                 = null;
		$responsive_height                = null;
		$responsive_horizontal_offset     = null;
		$responsive_vertical_offset       = null;
		$responsive_url                   = null;
		$has_parent_empty_string_deletion = false;

		if ( $attr && $breakpoint ) {
			// Extract responsive width using D5's inheritance system.
			$responsive_width = ModuleUtils::get_attr_subname_value(
				[
					'attr'         => $attr,
					'subname'      => 'image.width',
					'breakpoint'   => $breakpoint,
					'state'        => $state,
					'mode'         => 'getOrInheritClosest',
					'defaultValue' => $default_attr['image']['width'],
				]
			);

			// Extract responsive height using D5's inheritance system.
			$responsive_height = ModuleUtils::get_attr_subname_value(
				[
					'attr'         => $attr,
					'subname'      => 'image.height',
					'breakpoint'   => $breakpoint,
					'state'        => $state,
					'mode'         => 'getOrInheritClosest',
					'defaultValue' => $default_attr['image']['height'],
				]
			);

			// Extract responsive horizontal offset using D5's inheritance system.
			$responsive_horizontal_offset = ModuleUtils::get_attr_subname_value(
				[
					'attr'         => $attr,
					'subname'      => 'image.horizontalOffset',
					'breakpoint'   => $breakpoint,
					'state'        => $state,
					'mode'         => 'getOrInheritClosest',
					'defaultValue' => $default_attr['image']['horizontalOffset'],
				]
			);

			// Extract responsive vertical offset using D5's inheritance system.
			$responsive_vertical_offset = ModuleUtils::get_attr_subname_value(
				[
					'attr'         => $attr,
					'subname'      => 'image.verticalOffset',
					'breakpoint'   => $breakpoint,
					'state'        => $state,
					'mode'         => 'getOrInheritClosest',
					'defaultValue' => $default_attr['image']['verticalOffset'],
				]
			);

			// Extract responsive URL using D5's inheritance system.
			// Manually check inheritance chain (same logic as background style component) to respect empty string deletions.
			// Empty string ('') is falsy, so truthy check correctly skips it and stops inheritance.
			$responsive_url = null;

			// Get dynamic breakpoint order to support custom breakpoints like phoneWide.
			$breakpoint_names = Breakpoint::get_enabled_breakpoint_names();
			$current_index    = array_search( $breakpoint, $breakpoint_names, true );

			if ( false !== $current_index ) {
				// First, check if current breakpoint has explicit deletion.
				$current_breakpoint_data = $attr[ $breakpoint ][ $state ]['image'] ?? null;
				$current_breakpoint_url  = $current_breakpoint_data['url'] ?? null;

				// CRITICAL: If current breakpoint has empty string, it means explicit deletion.
				// We must NOT inherit from any larger breakpoint.
				if ( '' === $current_breakpoint_url ) {
					$has_parent_empty_string_deletion = true;
				} else {
					// Only check larger breakpoints if current breakpoint doesn't have explicit deletion.
					// Loop through larger breakpoints (closest first), same as background style component.
					for ( $i = $current_index - 1; $i >= 0; $i-- ) {
						$larger_breakpoint      = $breakpoint_names[ $i ];
						$larger_breakpoint_data = $attr[ $larger_breakpoint ][ $state ]['image'] ?? null;
						$larger_breakpoint_url  = $larger_breakpoint_data['url'] ?? null;

						// CRITICAL: If we encounter an empty string, it means explicit deletion at this breakpoint.
						// We must STOP inheritance entirely, not continue to larger breakpoints.
						if ( '' === $larger_breakpoint_url ) {
							$has_parent_empty_string_deletion = true;
							break;
						}

						// Truthy check: if URL exists and is not empty, inherit it.
						// Same logic as background style component: `if (largerBreakpointData?.url && ...)`.
						if ( $larger_breakpoint_url ) {
							$responsive_url = $larger_breakpoint_url;
							break;
						}
					}
				}
			}
		}

		// Load default so if the attribute lacks required value, it'll be rendered using default.
		$image_values = array_merge( $default_attr['image'], $image );
		$parallax     = $image_values['parallax'];

		// Override width, height with responsive values if available.
		if ( null !== $responsive_width ) {
			$image_values['width'] = $responsive_width;
		}
		if ( null !== $responsive_height ) {
			$image_values['height'] = $responsive_height;
		}
		// Override horizontal and vertical offsets with responsive values if available.
		if ( null !== $responsive_horizontal_offset ) {
			$image_values['horizontalOffset'] = $responsive_horizontal_offset;
		}
		if ( null !== $responsive_vertical_offset ) {
			$image_values['verticalOffset'] = $responsive_vertical_offset;
		}
		// Use inherited URL if current breakpoint doesn't have explicit URL and inherited URL is valid.
		// Only add URL if: URL is null (not set), responsiveUrl exists, not empty, and not default value.
		// Empty string ('') means explicit deletion and should NOT inherit.
		if ( ! isset( $image['url'] ) && $responsive_url && '' !== $responsive_url && $default_attr['image']['url'] !== $responsive_url ) {
			$image_values['url'] = $responsive_url;
		}

		// CRITICAL: If a parent breakpoint has empty string deletion, clear the URL ONLY if current breakpoint
		// doesn't have its own explicit URL. This handles the case where phone inherited desktop's URL,
		// but then tablet deleted it. However, if phone has its own explicit URL, don't override it.
		if ( $has_parent_empty_string_deletion && ! isset( $image['url'] ) ) {
			$image_values['url'] = '';
		}

		if ( $image && ! $is_image_not_enabled ) {
			$url               = $image_values['url'];
			$size              = $image_values['size'];
			$width             = $image_values['width'];
			$height            = $image_values['height'];
			$position          = $image_values['position'];
			$horizontal_offset = $image_values['horizontalOffset'];
			$vertical_offset   = $image_values['verticalOffset'];
			$repeat            = $image_values['repeat'];
			$blend             = $image_values['blend'];

			$should_output_property = function ( $prop, $value, $attr, $breakpoint, $state ) use ( $image ) {
				if ( 'desktop' === $breakpoint || null === $attr ) {
					return ! empty( $value );
				}

				$breakpoints   = array_keys( $attr );
				$current_index = array_search( $breakpoint, $breakpoints, true );

				if ( false === $current_index ) {
					return isset( $attr[ $breakpoint ][ $state ]['image'][ $prop ] );
				}

				$current_raw_value = $attr[ $breakpoint ][ $state ]['image'][ $prop ] ?? null;

				// For position property, check if offsets changed even if position itself is not set.
				// background-position CSS is generated from position + horizontalOffset + verticalOffset combined.
				if ( 'position' === $prop ) {
					$current_horizontal_offset = $attr[ $breakpoint ][ $state ]['image']['horizontalOffset'] ?? null;
					$current_vertical_offset   = $attr[ $breakpoint ][ $state ]['image']['verticalOffset'] ?? null;

					// If offsets are set on current breakpoint, check if they differ from parent.
					if ( null !== $current_horizontal_offset || null !== $current_vertical_offset ) {
						// Loop through parent breakpoints to find first one with position or offsets.
						for ( $i = $current_index - 1; $i >= 0; $i-- ) {
							$parent_breakpoint        = $breakpoints[ $i ];
							$parent_raw_value         = $attr[ $parent_breakpoint ][ $state ]['image'][ $prop ] ?? null;
							$parent_horizontal_offset = $attr[ $parent_breakpoint ][ $state ]['image']['horizontalOffset'] ?? null;
							$parent_vertical_offset   = $attr[ $parent_breakpoint ][ $state ]['image']['verticalOffset'] ?? null;

							// Output if position changed OR if horizontalOffset changed OR if verticalOffset changed.
							return (
								( null !== $current_raw_value && $current_raw_value !== $parent_raw_value ) ||
								$current_horizontal_offset !== $parent_horizontal_offset ||
								$current_vertical_offset !== $parent_vertical_offset
							);
						}

						// No parent breakpoint found, offsets are set on current breakpoint.
						return true;
					}
				}

				if ( null === $current_raw_value ) {
					return false;
				}

				for ( $i = $current_index - 1; $i >= 0; $i-- ) {
					$parent_breakpoint = $breakpoints[ $i ];
					if ( isset( $attr[ $parent_breakpoint ][ $state ]['image'][ $prop ] ) ) {
						$parent_raw_value = $attr[ $parent_breakpoint ][ $state ]['image'][ $prop ];

						// For custom size, check if underlying width/height values changed.
						if ( 'size' === $prop && 'custom' === $current_raw_value && 'custom' === $parent_raw_value ) {
							$has_changed =
							( $attr[ $breakpoint ][ $state ]['image']['width'] ?? null ) !== ( $attr[ $parent_breakpoint ][ $state ]['image']['width'] ?? null ) ||
							( $attr[ $breakpoint ][ $state ]['image']['height'] ?? null ) !== ( $attr[ $parent_breakpoint ][ $state ]['image']['height'] ?? null );
						} elseif ( 'position' === $prop ) {
							// For position, check if underlying horizontalOffset/verticalOffset values changed.
							// background-position CSS is generated from position + horizontalOffset + verticalOffset combined.
							$current_horizontal_offset = $attr[ $breakpoint ][ $state ]['image']['horizontalOffset'] ?? null;
							$parent_horizontal_offset  = $attr[ $parent_breakpoint ][ $state ]['image']['horizontalOffset'] ?? null;
							$current_vertical_offset   = $attr[ $breakpoint ][ $state ]['image']['verticalOffset'] ?? null;
							$parent_vertical_offset    = $attr[ $parent_breakpoint ][ $state ]['image']['verticalOffset'] ?? null;

							// Output if position changed OR if horizontalOffset changed OR if verticalOffset changed.
							$has_changed =
							$current_raw_value !== $parent_raw_value ||
							$current_horizontal_offset !== $parent_horizontal_offset ||
							$current_vertical_offset !== $parent_vertical_offset;
						} else {
							$has_changed = $current_raw_value !== $parent_raw_value;
						}

						return $has_changed;
					}
				}
				return true;
			};

			$can_add_background_image = isset( $image_values['url'] ) && '' !== $image_values['url'] && isset( $parallax['enabled'] ) && 'on' !== $parallax['enabled'];

			if ( $can_add_background_image ) {
				$background_url = self::_get_safe_background_image_value( $url );

				if ( '' !== $background_url && ! in_array( $background_url, $background_images, true ) ) {
					$background_images[] = $background_url;
				}

				$properties = [
					'size'     => [
						'css'       => 'background-size',
						'value'     => $size,
						'generator' => function () use ( $size, $width, $height ) {
							return BackgroundStyleUtils::get_background_size_css( $size, $width, $height, 'image' );
						},
					],
					'position' => [
						'css'       => 'background-position',
						'value'     => $position,
						'generator' => function () use ( $position, $horizontal_offset, $vertical_offset ) {
							return BackgroundStyleUtils::get_background_position_css( $position, $horizontal_offset, $vertical_offset );
						},
					],
					'repeat'   => [
						'css'       => 'background-repeat',
						'value'     => $repeat,
						'generator' => function () use ( $repeat ) {
							return $repeat;
						},
					],
					'blend'    => [
						'css'       => 'background-blend-mode',
						'value'     => $blend,
						'generator' => function () use ( $blend ) {
							return $blend;
						},
					],
				];

				foreach ( $properties as $prop => $config ) {
					$has_explicit_value = isset( $image[ $prop ] );

					if ( 'repeat' === $prop ) {
						// - No presets active: Always generate background-repeat for Divi 4 compatibility
						// Issue reference https://github.com/elegantthemes/Divi/issues/32583
						// - Presets active: Only generate background-repeat when explicitly set by user
						// This prevents Option Group Presets with "repeat" from being overridden by "no-repeat" defaults.
						if ( $has_background_presets ? isset( $image['repeat'] ) : isset( $repeat ) ) {
							$should_output_default = $should_output_property( $prop, $repeat, $attr, $breakpoint, $state );
							if ( $should_output_default ) {
								$style_declarations->add( 'background-repeat', $repeat );
							}
						}
					} elseif ( 'position' === $prop ) {
						// For position, check if position or offsets are explicitly set, since background-position CSS depends on both.
						// Only check explicitly set values in image object (not defaults from inheritance).
						$has_position_or_offsets =
							$has_explicit_value ||
							isset( $image['horizontalOffset'] ) ||
							isset( $image['verticalOffset'] );

						if ( $has_position_or_offsets ) {
							$should_output = $should_output_property( $prop, $config['value'], $attr, $breakpoint, $state );

							if ( $should_output ) {
								$style_declarations->add( $config['css'], $config['generator']() );
							}
						}
					} elseif ( $has_explicit_value ) {
						$should_output = $should_output_property( $prop, $config['value'], $attr, $breakpoint, $state );

						if ( $should_output ) {
							$style_declarations->add( $config['css'], $config['generator']() );
						}
					}
				}
			}

			if ( $preview && $image_values['url'] && '' !== $image_values['url'] && isset( $parallax['enabled'] ) && 'on' === $parallax['enabled'] ) {
				if ( $should_output_property( 'url', $url, $attr, $breakpoint, $state ) ) {
					$background_url = self::_get_safe_background_image_value( $url );

					if ( '' !== $background_url && ! in_array( $background_url, $background_images, true ) ) {
						$background_images[] = $background_url;
					}
				}

				// Background styles for preview area when parallax is on.
				$style_declarations->add( 'background-size', 'cover' );
				$style_declarations->add( 'background-position', 'center' );
				$style_declarations->add( 'background-repeat', 'no-repeat' );
				$style_declarations->add( 'background-blend-mode', $blend );
			}

			// Render 'none' when image has empty string deletion (explicit or inherited) and breakpoint isn't desktop.
			// Empty string ('') means user explicitly deleted the image on this breakpoint OR inherited deletion from parent.
			// Check both RAW attr (explicit deletion) AND $url (inherited deletion from parent breakpoint).
			// Same pattern as gradient disabled (line 420-422).

			// CRITICAL: Only check when breakpoint is explicitly provided (not null/empty).
			// When breakpoint is null/empty, we're rendering for preview/unknown context and shouldn't add 'none'.
			if ( $breakpoint && 'desktop' !== $breakpoint ) {
				$raw_image_url = $attr[ $breakpoint ][ $state ]['image']['url'] ?? null;
				if ( '' === $raw_image_url || '' === $url ) {
					$background_images[] = 'none';
				}
			}
		}

		if ( $gradient ) {
			$gradient                      = GradientUtils::get_effective_gradient_for_breakpoint(
				[
					'attr'              => $attr,
					'breakpoint'        => $breakpoint,
					'state'             => $state,
					'defaultGradient'   => $default_attr['gradient'],
					'currentGradient'   => is_array( $gradient ) ? $gradient : [],
					'gradientSubFields' => [
						'enabled',
						'stops',
						'type',
						'direction',
						'directionRadial',
						'repeat',
						'overlaysImage',
						'length',
					],
				]
			);
			$resolved_gradient_enabled     = $gradient['enabled'] ?? ( ! empty( $gradient['stops'] ) ? 'on' : 'off' );
			$gradient_overlays_image_check = $gradient['overlaysImage'] ?? 'off';

			// Render gradient when enabled.
			if ( 'on' === $resolved_gradient_enabled ) {
				// D4 compatibility: When parallax AND gradient overlays image are both enabled in non-preview mode,
				// don't add gradient to parent element. The parallax container will have the gradient background.
				$parallax_image_url   = $image_values['url'] ?? '';
				$has_parallax_image   = is_string( $parallax_image_url ) && '' !== $parallax_image_url;
				$should_skip_gradient = isset( $parallax['enabled'] ) && 'on' === $parallax['enabled'] && 'on' === $gradient_overlays_image_check && $has_parallax_image && ! $preview;

				if ( ! $should_skip_gradient ) {
					$background_images[] = Background::gradient_style_declaration( $gradient );
				}
			}

			// Render 'none' when disabled and breakpoint isn't desktop.
			if ( 'desktop' !== $breakpoint && $is_gradient_not_enabled ) {
				$background_images[] = 'none';
			}
		}

		// CRITICAL FIX: Ensure inherited images are included when gradient is rendered.
		// When gradient is added to $background_images but image wasn't (because URL didn't change from parent),
		// we must add the inherited image so both appear in the CSS output together.
		// This prevents gradient-only output that would override and remove the inherited image.
		// Note: Image is prepended (added to beginning) to maintain default order [image, gradient].
		// The overlaysImage logic below will reverse this if needed to put gradient on top.
		if ( ! empty( $background_images ) && $gradient && isset( $gradient['enabled'] ) && 'on' === $gradient['enabled'] && ! empty( $gradient['stops'] ) ) {
			if ( ( $image || $responsive_url ) && ! $is_image_not_enabled && isset( $image_values['url'] ) && '' !== $image_values['url'] && isset( $parallax['enabled'] ) && 'on' !== $parallax['enabled'] ) {
				$image_url      = $image_values['url'];
				$background_url = self::_get_safe_background_image_value( $image_url );

				// Only add if it's not already in the array (it may have been added earlier if URL changed).
				if ( '' !== $background_url && ! in_array( $background_url, $background_images, true ) ) {
					// Prepend image to beginning of array to maintain default order [image, gradient].
					// This ensures overlaysImage logic works correctly.
					array_unshift( $background_images, $background_url );
				}
			}
		}

		// D4 compatibility: When parallax and gradient overlays image are both enabled, set background-image to initial.
		// This prevents the parent element from having the background, which is handled by the parallax container.
		// Resolve overlaysImage with responsive inheritance: check current gradient, then inherit from larger
		// breakpoints via $attr, then fall back to default. This ensures OGP presets that only override gradient
		// stops still inherit the overlaysImage setting from the desktop breakpoint.
		$gradient_overlays_image = $gradient['overlaysImage'] ?? null;

		if ( null === $gradient_overlays_image && $attr && $breakpoint ) {
			$breakpoint_names = Breakpoint::get_enabled_breakpoint_names();
			$current_index    = array_search( $breakpoint, $breakpoint_names, true );

			if ( false !== $current_index ) {
				for ( $i = $current_index - 1; $i >= 0; $i-- ) {
					$larger_bp_overlays = $attr[ $breakpoint_names[ $i ] ][ $state ]['gradient']['overlaysImage'] ?? null;
					if ( null !== $larger_bp_overlays ) {
						$gradient_overlays_image = $larger_bp_overlays;
						break;
					}
				}
			}
		}

		$gradient_overlays_image = $gradient_overlays_image ?? $default_attr['gradient']['overlaysImage'] ?? 'off';
		$parallax_enabled        = $parallax['enabled'] ?? 'off';
		$parallax_image_url      = $image_values['url'] ?? '';
		$has_parallax_image      = is_string( $parallax_image_url ) && '' !== $parallax_image_url;

		if ( ! $preview && 'on' === $parallax_enabled && 'on' === $gradient_overlays_image && $has_parallax_image ) {
			$background_images = [ 'initial' ];
		} elseif ( ! empty( $background_images ) ) {
			// Swap background gradient on top of background image when gradient has stops and overlayImage option is on.
			if ( $gradient && ! empty( $gradient['stops'] ) && 'on' === $gradient_overlays_image ) {
				$background_images = array_reverse( $background_images );
			}
		} elseif ( $is_image_not_enabled || $is_gradient_not_enabled ) {
			// If both image and gradient are disabled, empty the array.
			$background_images = [ 'initial' ];
		}

		if ( ! empty( $background_images ) ) {
			$style_declarations->add( 'background-image', implode( ', ', $background_images ) );
		}

		// Determine if we should force initial based on gradient + image + blend (D4 logic).
		$should_force_initial = count( $background_images ) >= 2 && 'normal' !== $image_values['blend'];

		if ( $color ) {
			// When color IS set: use 'initial' if shouldForceInitial, otherwise use the color.
			$background_color = $should_force_initial ? 'initial' : $color;

			$style_declarations->add( 'background-color', $background_color );
		} elseif ( $should_force_initial ) {
			// When color is NOT set: still add 'initial' for gradient + image + blend.
			// This fixes issue #41171 and #44645.
			$style_declarations->add( 'background-color', 'initial' );
		}

		// Add `background-repeat: no-repeat` when gradient is on and no background image is present.
		// This needed to solve the linear gradient seam or "white line" issues in Chrome.
		$background_image_values = $style_declarations->get_property_value( 'background-image' );
		$background_image_value  = implode( ', ', $background_image_values );

		if ( ! $style_declarations->has_property( 'background-repeat' )
		&& $style_declarations->has_property( 'background-image' )
		&& str_contains( $background_image_value, 'linear-gradient(' )
		&& ! str_contains( $background_image_value, 'url(' ) ) {
			$style_declarations->add( 'background-repeat', 'no-repeat' );
		}

		return $style_declarations->value();
	}
}
