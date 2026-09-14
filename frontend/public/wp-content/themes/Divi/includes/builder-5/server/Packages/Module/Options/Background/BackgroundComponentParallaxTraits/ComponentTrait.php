<?php
/**
 * BackgroundComponentParallax::component()
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Background\BackgroundComponentParallaxTraits;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\Module\Options\Background\BackgroundClassnames;
use ET\Builder\Packages\StyleLibrary\Declarations\Background\Background;
use ET\Builder\Packages\StyleLibrary\Utils\GradientUtils;
use ET\Builder\Packages\Module\Options\Background\BackgroundComponentParallaxItemTraits\ComponentTrait as ParallaxItemComponentTrait;
use ET\Builder\Packages\GlobalData\GlobalData;

trait ComponentTrait {

	/** Component function for handling background attributes and generating parallax backgrounds.
	 *
	 * This function generates parallax backgrounds based on the provided attributes.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/BackgroundComponentParallax BackgroundComponentParallax} in
	 * `@divi/module` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments for generating the parallax backgrounds.
	 *
	 *     @type array|null  $backgroundAttr   The background attributes. Default `null`.
	 *     @type string|null $moduleId         The module ID. Default `null`.
	 *     @type array       $enable           The enable settings for different states.
	 *                                         Default `[value'  => true, 'hover'  => true, 'sticky' => false,]`.
	 * }
	 *
	 * @return string The generated parallax backgrounds.
	 *
	 * @example:
	 * ```php
	 *     $args = array(
	 *         'backgroundAttr' => array(
	 *             'desktop' => array(
	 *                 'value' => array(
	 *                     'image' => array(
	 *                         'parallax' => array(
	 *                             'enabled' => true,
	 *                             'method'  => 'mouse',
	 *                         ),
	 *                         'url'   => 'https://example.com/image.jpg',
	 *                     ),
	 *                     'gradient' => array(
	 *                         'overlaysImage' => true,
	 *                         'stops'         => array(
	 *                             array(
	 *                                 'color' => '#000000',
	 *                                 'stop'  => '0%',
	 *                             ),
	 *                             array(
	 *                                 'color' => '#ffffff',
	 *                                 'stop'  => '100%',
	 *                             ),
	 *                         ),
	 *                     ),
	 *                 ),
	 *             ),
	 *             // Add other breakpoints and states here.
	 *         ),
	 *         'moduleId' => 'my-module',
	 *         'enable'   => array(
	 *             'value'  => true,
	 *             'hover'  => true,
	 *             'sticky' => false,
	 *         ),
	 *     );
	 *     $parallaxBackgrounds = ClassName::component( $args );
	 * ```
	 */
	public static function component( array $args ): string {
		$args = wp_parse_args(
			$args,
			[
				'backgroundAttr' => null,
				'moduleId'       => null,
				'enable'         => [
					'value'  => true,
					'hover'  => true,
					'sticky' => false,
				],
			]
		);

		$background_default_attr = Background::$background_default_attr;
		$background_attr         = $args['backgroundAttr'];
		$module_id               = $args['moduleId'];
		$enable                  = $args['enable'];

		// Populate exist classnames on all breakpoint and states.
		$exist_classnames    = [];
		$gradient_classnames = [];

		// Background component needs to wait until `existClassNames` gets populated. Thus the component is
		// populated as a function which will be executed later once `existClassNames` have been populated.
		$parallax_background_functions = [];

		// If current breakpoint / state does not have `enabled` value, value of larger breakpoint gets cascaded and used.
		$larger_breakpoint_enabled        = $background_default_attr['image']['parallax']['enabled'] ?? null;
		$larger_breakpoint_method         = $background_default_attr['image']['parallax']['method'] ?? null;
		$larger_breakpoint_overlays_image = $background_default_attr['gradient']['overlaysImage'] ?? null;
		$larger_breakpoint_gradient       = $background_default_attr['gradient'] ?? null;
		$larger_breakpoint_blend          = $background_default_attr['image']['blend'] ?? null;
		$larger_breakpoint_color          = $background_default_attr['color'] ?? null;
		$larger_breakpoint_url            = $background_default_attr['image']['url'] ?? null;

		// Skip if `backgroundAttr` isn't an array.
		if ( is_array( $background_attr ) ) {
			// The order of breakpoint matters because larger breakpoint value cascades into the smaller breakpoint
			// value. Thus loop over the breakpoints list with guaranteed order instead of looping `backgroundAttr` properties.
			foreach ( Breakpoint::get_enabled_breakpoint_names() as $breakpoint ) {
				$state_values = $background_attr[ $breakpoint ] ?? null;

				// Continue if stateValues is an array.
				if ( is_array( $state_values ) ) {
					// The order of state matters because default (`value`) state value cascades into the onther states. Thus
					// loop over the states list with guaranteed order instead of looping `backgroundAttr[breakpoint]` properties.
					foreach ( ModuleUtils::states() as $state ) {
						$current_value = $state_values[ $state ] ?? null;

						if ( ! is_array( $current_value ) ) {
							continue;
						}

						$current_enabled = $current_value['image']['parallax']['enabled'] ?? null;
						$current_method  = $current_value['image']['parallax']['method'] ?? null;
						$current_url     = $current_value['image']['url'] ?? null;

						$current_overlays_image = $current_value['gradient']['overlaysImage'] ?? null;
						$current_gradient       = $current_value['gradient'] ?? null;
						$current_blend          = $current_value['image']['blend'] ?? null;
						// Check if color is explicitly set (even if empty string) vs not set at all.
						// Empty string means explicitly reset, null means inherit from larger breakpoint.
						$current_color = array_key_exists( 'color', $current_value ) ? $current_value['color'] : null;

						$parallax_name = "{$breakpoint}-{$state}";

						// Background parallax is considered valid on current breakpoint + state when:
						// 1. Current enabled + current URL exist (truthy, non-empty).
						// 2. Current enabled doesn't exist + parallax enabled on larger breakpoint + current URL exist (truthy, non-empty).
						// Note: Empty string URL (explicit deletion) prevents inheritance but does not render parallax wrapper,
						// as there is no image to parallax. This ensures wrapper/classname parity and prevents frontend grid breaks.
						$final_url           = ( isset( $current_url ) && '' !== $current_url ) ? $current_url : ( isset( $larger_breakpoint_url ) && '' !== $larger_breakpoint_url ? $larger_breakpoint_url : null );
						$is_parallax_enabled = ( 'on' === $current_enabled || ( is_null( $current_enabled ) && 'on' === $larger_breakpoint_enabled ) ) && 'off' !== $current_enabled && isset( $enable[ $state ] ) && $enable[ $state ] && isset( $final_url ) && '' !== $final_url;

						// Only render wrapper when parallax is actually enabled.
						// When parallax is disabled, CSS background handles the image as a static background.
						// This matches D4 behavior where disabled parallax = static image (no parallax effects).
						$should_render_wrapper = $is_parallax_enabled;

						if ( $should_render_wrapper ) {
							// existClassName is empty string on desktop breakpoint + default (`value`) state.
							$exist_classname = BackgroundClassnames::get_background_parallax_exist_classnames( $breakpoint, $state );

							if ( $exist_classname ) {
								$exist_classnames[ $exist_classname ] = true;
							}

							$effective_gradient        = GradientUtils::get_effective_gradient_for_breakpoint(
								[
									'attr'              => $background_attr,
									'breakpoint'        => $breakpoint,
									'state'             => $state,
									'defaultGradient'   => $background_default_attr['gradient'],
									'currentGradient'   => is_array( $current_gradient ) ? $current_gradient : [],
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
							$current_gradient_enabled  = is_array( $current_gradient ) && array_key_exists( 'enabled', $current_gradient ) ? $current_gradient['enabled'] : null;
							$resolved_gradient_enabled = is_array( $effective_gradient ) && array_key_exists( 'enabled', $effective_gradient ) ? $effective_gradient['enabled'] : null;

							// Check if the gradient is enabled.
							// This must use resolved gradient settings so gradient variable references
							// can contribute `enabled`/`overlaysImage` values.
							$gradient_enabled = ( 'on' === $current_gradient_enabled || true === $current_gradient_enabled )
							|| ( is_null( $current_gradient_enabled ) && ( 'on' === $resolved_gradient_enabled || true === $resolved_gradient_enabled ) );

							// Prepare gradientOverlaysImage to be passed on parallax item component.
							$gradient_overlays_image = $gradient_enabled && (
								'on' === $current_overlays_image
								|| (
									is_null( $current_overlays_image )
									&& (
										'on' === ( $effective_gradient['overlaysImage'] ?? null )
										|| 'on' === $larger_breakpoint_overlays_image
									)
								)
							);

							// gradientClassName is empty string on desktop breakpoint + default (`value`) state.
							$gradient_classname = BackgroundClassnames::get_background_parallax_exist_classnames( $breakpoint, $state, $gradient_overlays_image );

							if ( $gradient_classname ) {
								$gradient_classnames[ $gradient_classname ] = true;
							}

							$gradient_css = '';

							$final_blend = ( isset( $current_blend ) && '' !== $current_blend ) ? $current_blend : ( isset( $larger_breakpoint_blend ) && '' !== $larger_breakpoint_blend ? $larger_breakpoint_blend : null );
							$final_url   = ( isset( $current_url ) && '' !== $current_url ) ? $current_url : ( isset( $larger_breakpoint_url ) && '' !== $larger_breakpoint_url ? $larger_breakpoint_url : null );

							$gradient_to_use = $gradient_enabled && is_array( $effective_gradient ) ? $effective_gradient : null;

							if (
							$gradient_overlays_image
							|| (
								$gradient_enabled
								&& isset( $final_blend )
								&& 'normal' !== $final_blend
								&& isset( $final_url )
								&& '' !== $final_url
							)
							) {
								if ( is_array( $gradient_to_use ) ) {
									// Resolve global colors in gradient stops before converting to CSS.
									// This is necessary because the gradient style is outputted as inline styles in some case.
									if ( isset( $gradient_to_use['stops'] ) && is_array( $gradient_to_use['stops'] ) ) {
										$global_colors = GlobalData::get_global_colors();
										foreach ( $gradient_to_use['stops'] as &$stop ) {
											if ( isset( $stop['color'] ) && '' !== $stop['color'] ) {
												$resolved_color = GlobalData::resolve_global_color_variable(
													$stop['color'],
													$global_colors
												);
												$stop['color']  = $resolved_color;
											}
										}
										unset( $stop ); // Break reference.
									}

									$merged_gradient = array_merge(
										$background_default_attr['gradient'],
										$gradient_to_use
									);
									$gradient_css    = Background::gradient_style_declaration( $merged_gradient );
								}
							}

							$function_args = [
								'background_attr'          => $background_attr,
								'breakpoint'               => $breakpoint,
								'current_enabled'          => $current_enabled,
								'larger_breakpoint_enabled' => $larger_breakpoint_enabled,
								'current_method'           => $current_method,
								'larger_breakpoint_method' => $larger_breakpoint_method,
								'module_id'                => $module_id,
								'state'                    => $state,
								'gradient_overlays_image'  => $gradient_overlays_image,
								'gradient_css'             => $gradient_css,
								'current_blend'            => $current_blend,
								'larger_breakpoint_blend'  => $larger_breakpoint_blend,
								'current_color'            => $current_color,
								'larger_breakpoint_color'  => $larger_breakpoint_color,
								'larger_breakpoint_url'    => $larger_breakpoint_url,
								'all_background_attr'      => $background_attr,
							];

							// Background component needs to wait until `$exist_classnames` and `$gradient_classnames` gets
							// populated. Thus, the component is populated as a function which will be executed later once
							// `$exist_classnames` and `$gradient_classnames` have been populated.
							$parallax_background_functions[] = function ( $all_exist_classnames, $all_gradient_classnames ) use ( $function_args ) {
								// Added for readability, the values are the same as the variables outside of the closure.
								$background_attr           = $function_args['background_attr'];
								$breakpoint                = $function_args['breakpoint'];
								$current_enabled           = $function_args['current_enabled'];
								$larger_breakpoint_enabled = $function_args['larger_breakpoint_enabled'];
								$current_method            = $function_args['current_method'];
								$larger_breakpoint_method  = $function_args['larger_breakpoint_method'];
								$module_id                 = $function_args['module_id'];
								$state                     = $function_args['state'];
								$gradient_overlays_image   = $function_args['gradient_overlays_image'];
								$gradient_css              = $function_args['gradient_css'];
								$current_blend             = $function_args['current_blend'];
								$larger_breakpoint_blend   = $function_args['larger_breakpoint_blend'];
								$current_color             = $function_args['current_color'];
								$larger_breakpoint_color   = $function_args['larger_breakpoint_color'];
								$larger_breakpoint_url     = $function_args['larger_breakpoint_url'];
								$all_background_attr       = $function_args['all_background_attr'];

								$responsive_visibility_classes = self::_generate_responsive_visibility_classes(
									$all_background_attr,
									$breakpoint,
									$state
								);

								// Determine CSS parallax mode based on method setting.
								// CSS parallax (static, no JS effects) when method is 'off', True parallax otherwise.
								$css_parallax_mode = $current_method ? 'off' === $current_method : 'off' === $larger_breakpoint_method;

								// Using anonymous class trick, to invoke recursive call on a trait. Using `self` or `static` does not work here.
								// CRITICAL: When $current_color is explicitly set to empty string (''), it means the color was reset
								// for this breakpoint/state and should NOT inherit from larger breakpoint. Only inherit when
								// $current_color is null (not set). Also, if $larger_breakpoint_color is '' (reset was cascaded),
								// we should return null (no color) instead of trying to use the empty string.
								$final_color = ( null !== $current_color && '' !== $current_color )
									? $current_color
									: ( ( null === $current_color && isset( $larger_breakpoint_color ) && '' !== $larger_breakpoint_color && null !== $larger_breakpoint_color )
										? $larger_breakpoint_color
										: null );
								$final_blend = ( isset( $current_blend ) && '' !== $current_blend ) ? $current_blend : ( isset( $larger_breakpoint_blend ) && '' !== $larger_breakpoint_blend ? $larger_breakpoint_blend : null );

								return ( new class() { use ParallaxItemComponentTrait; } )::component(
									[
										'breakpoint'      => $breakpoint,
										'cssParallax'     => $css_parallax_mode,
										'existClassNames' => $all_exist_classnames,
										'moduleId'        => $module_id,
										'state'           => $state,
										'url'             => $background_attr[ $breakpoint ][ $state ]['image']['url'] ?? $larger_breakpoint_url,
										'gradientOverlaysImage' => $gradient_overlays_image,
										'gradientCSS'     => $gradient_css,
										'blend'           => $final_blend,
										'color'           => $final_color,
										'gradientClassNames' => $all_gradient_classnames,
										'responsiveVisibilityClasses' => $responsive_visibility_classes,
									]
								);
							};

							// Overwrite larger breakpoint values when current state is "value" (default).
							// Only "value" state overwrites the larger breakpoint due to how things are cascading:
							// - desktop => tablet => phone (smaller breakpoint use larger breakpoint value)
							// - hover => value || sticky => value (hover OR sticky use `value` when it doesn't exist).
							if ( 'value' === $state ) {
								if ( ! is_null( $current_enabled ) ) {
									$larger_breakpoint_enabled = $current_enabled;
								}

								if ( ! is_null( $current_method ) ) {
									$larger_breakpoint_method = $current_method;
								}

								if ( ! is_null( $current_overlays_image ) ) {
									$larger_breakpoint_overlays_image = $current_overlays_image;
								}

								if ( ! is_null( $current_gradient ) ) {
									$larger_breakpoint_gradient = $current_gradient;
								}

								if ( ! is_null( $current_url ) ) {
									$larger_breakpoint_url = $current_url;
								}

								if ( ! is_null( $current_blend ) ) {
									$larger_breakpoint_blend = $current_blend;
								}

								if ( ! is_null( $current_color ) ) {
									$larger_breakpoint_color = $current_color;
								}
							}
						}
					}
				}
			}
		}

		if ( empty( $parallax_background_functions ) ) {
			return '';
		}

		$parallax_backgrounds = '';

		foreach ( $parallax_background_functions as $parallax_background_function ) {
			$parallax_backgrounds .= $parallax_background_function( $exist_classnames, $gradient_classnames );
		}

		return $parallax_backgrounds;
	}

	/**
	 * Generate responsive visibility classes for parallax elements.
	 *
	 * This method analyzes the parallax settings across all breakpoints and generates
	 * CSS classes that will hide parallax elements on breakpoints where they should
	 * not be visible due to explicit disabling. This provides a flexible solution
	 * that works with any breakpoint configuration.
	 *
	 * @since ??
	 *
	 * @param array  $all_background_attr All background attributes across breakpoints.
	 * @param string $current_breakpoint  Current breakpoint being processed.
	 * @param string $state              Current state being processed.
	 *
	 * @return array Array of CSS classes for responsive visibility control.
	 */
	private static function _generate_responsive_visibility_classes( array $all_background_attr, string $current_breakpoint, string $state ): array {
		$visibility_classes = [];

		// Only generate visibility classes for breakpoints that have parallax enabled.
		$current_parallax_enabled = $all_background_attr[ $current_breakpoint ][ $state ]['image']['parallax']['enabled'] ?? null;

		if ( 'on' !== $current_parallax_enabled ) {
			return $visibility_classes;
		}

		$enabled_breakpoints = Breakpoint::get_enabled_breakpoints();

		$current_order       = 0;
		$smaller_breakpoints = [];

		foreach ( $enabled_breakpoints as $breakpoint_config ) {
			if ( $breakpoint_config['name'] === $current_breakpoint ) {
				$current_order = $breakpoint_config['order'] ?? 0;
				break;
			}
		}

		foreach ( $enabled_breakpoints as $breakpoint_config ) {
			$bp_name  = $breakpoint_config['name'] ?? '';
			$bp_order = $breakpoint_config['order'] ?? 0;

			if ( $bp_order < $current_order ) {
				$smaller_breakpoints[] = $bp_name;
			}
		}

		foreach ( $smaller_breakpoints as $smaller_bp ) {
			$smaller_parallax_enabled = $all_background_attr[ $smaller_bp ][ $state ]['image']['parallax']['enabled'] ?? null;

			if ( 'off' === $smaller_parallax_enabled ) {
				$visibility_classes[] = "et-pb-parallax-hidden-{$smaller_bp}";
			}
		}

		return $visibility_classes;
	}
}
