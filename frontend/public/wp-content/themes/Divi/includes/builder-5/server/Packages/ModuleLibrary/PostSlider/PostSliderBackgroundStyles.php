<?php
/**
 * Shared slide background helpers for Post Slider modules.
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostSlider;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\Style\Utils\Utils as StyleUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;

/**
 * Shared background style generation for Post Slider variants.
 *
 * @since ??
 */
class PostSliderBackgroundStyles {
	/**
	 * Builds background attributes for slide background image blending.
	 *
	 * @param array  $background_attrs Background attributes.
	 * @param array  $enabled_attr     Image enabled attribute values.
	 * @param string $slide_image      Slide background image URL.
	 *
	 * @return array
	 */
	public static function build_slide_background_attrs( array $background_attrs, array $enabled_attr, string $slide_image ): array {
		foreach ( $enabled_attr as $attr_breakpoint => $attr_state_values ) {
			foreach ( $attr_state_values as $attr_state => $enabled ) {
				if ( ! array_key_exists( $attr_breakpoint, $background_attrs ) ) {
					$background_attrs[ $attr_breakpoint ] = [];
				}

				if ( ! array_key_exists( $attr_state, $background_attrs[ $attr_breakpoint ] ) ) {
					$background_attrs[ $attr_breakpoint ][ $attr_state ] = [];
				}

				if ( ! array_key_exists( 'image', $background_attrs[ $attr_breakpoint ][ $attr_state ] ) ) {
					$background_attrs[ $attr_breakpoint ][ $attr_state ]['image'] = [];
				}

				if ( 'on' === $enabled ) {
					$background_attrs[ $attr_breakpoint ][ $attr_state ]['image']['url']     = $slide_image;
					$background_attrs[ $attr_breakpoint ][ $attr_state ]['image']['enabled'] = 'on';
				} else {
					$url = $background_attrs[ $attr_breakpoint ][ $attr_state ]['image']['url'] ?? null;

					if ( null === $url && ( 'desktop' !== $attr_breakpoint || 'value' !== $attr_state ) ) {
						$background_attrs[ $attr_breakpoint ][ $attr_state ]['image']['url'] = '';
					}
				}
			}
		}

		return $background_attrs;
	}

	/**
	 * Get slide-level parallax payload.
	 *
	 * @param array  $background_attrs Background attributes.
	 * @param array  $enabled_attr     Image enabled attribute values.
	 * @param string $image_placement  Image placement setting.
	 * @param string $slide_image      Slide background image URL.
	 *
	 * @return array
	 */
	public static function get_slide_parallax_data(
		array $background_attrs,
		array $enabled_attr,
		string $image_placement,
		string $slide_image
	): array {
		$parallax_data = [
			'background_attrs'    => [],
			'is_parallax_enabled' => false,
		];

		if ( 'background' !== $image_placement || empty( $slide_image ) ) {
			return $parallax_data;
		}

		// Resolve parallax status from the module-level attrs before building slide-specific
		// attrs. The parallax flag is module-level and does not require slide URL merging to detect,
		// so an early return here avoids a redundant build_slide_background_attrs() call per slide
		// when parallax is disabled.
		$module_attr_value = ModuleUtils::use_attr_value(
			[
				'attr'         => $background_attrs,
				'breakpoint'   => 'desktop',
				'state'        => 'value',
				'mode'         => 'getAndInheritAll',
				'defaultValue' => [],
			]
		);

		if ( 'on' !== ( $module_attr_value['image']['parallax']['enabled'] ?? 'off' ) ) {
			return $parallax_data;
		}

		$slide_background_attrs = self::build_slide_background_attrs( $background_attrs, $enabled_attr, $slide_image );

		return [
			'background_attrs'    => $slide_background_attrs,
			'is_parallax_enabled' => true,
		];
	}

	/**
	 * Render slide-level parallax background component and script data.
	 *
	 * @param ModuleElements $elements               Module elements instance.
	 * @param string         $module_id              Module block ID.
	 * @param int            $post_id                Slide post ID.
	 * @param array          $slide_background_attrs Slide background attrs.
	 *
	 * @return string
	 */
	public static function get_slide_parallax_component(
		ModuleElements $elements,
		string $module_id,
		int $post_id,
		array $slide_background_attrs
	): string {
		$slide_component_id = sprintf( '%s-post-slide-%d', $module_id, $post_id );
		$component          = $elements->style_components(
			[
				'attrName'             => 'module',
				'trackUsage'           => false,
				'styleComponentsProps' => [
					'id'       => $slide_component_id,
					'attrs'    => [
						'background' => $slide_background_attrs,
					],
					'boxShadow' => false,
				],
			]
		);

		$elements->script_data(
			[
				'attrName'        => 'module',
				'scriptDataProps' => [
					'id' => $slide_component_id,
				],
				'attrsResolver'   => function () use ( $slide_background_attrs ) {
					return [
						'background' => $slide_background_attrs,
					];
				},
			]
		);

		return is_string( $component ) ? $component : '';
	}

	/**
	 * Gets slide background styles for background image blending.
	 *
	 * @param array  $background_attrs Background attributes.
	 * @param array  $enabled_attr     Image enabled attribute values.
	 * @param string $image_placement  Image placement setting.
	 * @param string $slide_image      Slide background image URL.
	 *
	 * @return array
	 */
	public static function get_slide_background_styles(
		array $background_attrs,
		array $enabled_attr,
		string $image_placement,
		string $slide_image
	): array {
		if ( 'background' !== $image_placement || empty( $slide_image ) ) {
			return [];
		}

		$background_attrs      = self::build_slide_background_attrs( $background_attrs, $enabled_attr, $slide_image );
		$background_attr_value = ModuleUtils::use_attr_value(
			[
				'attr'         => $background_attrs,
				'breakpoint'   => 'desktop',
				'state'        => 'value',
				'mode'         => 'getAndInheritAll',
				'defaultValue' => [],
			]
		);

		$background_styles = Declarations::background_style_declaration(
			[
				'attr'       => $background_attrs,
				'attrValue'  => $background_attr_value,
				'breakpoint' => 'desktop',
				'state'      => 'value',
				'returnType' => 'key_value_pair',
				'keyFormat'  => 'param-case',
			]
		);

		return is_array( $background_styles ) ? $background_styles : [];
	}

	/**
	 * Gets responsive slide background styles for breakpoint-specific overrides.
	 *
	 * @param array  $background_attrs Background attributes.
	 * @param array  $enabled_attr     Image enabled attribute values.
	 * @param string $image_placement  Image placement setting.
	 * @param string $slide_image      Slide background image URL.
	 * @param string $slide_selector   Slide selector.
	 *
	 * @return array
	 */
	public static function get_slide_background_responsive_styles(
		array $background_attrs,
		array $enabled_attr,
		string $image_placement,
		string $slide_image,
		string $slide_selector
	): array {
		if ( 'background' !== $image_placement || empty( $slide_image ) || '' === $slide_selector ) {
			return [];
		}

		$background_attrs = self::build_slide_background_attrs( $background_attrs, $enabled_attr, $slide_image );
		$breakpoints      = self::_get_slide_background_breakpoint_settings();
		$styles           = [];

		foreach ( Breakpoint::get_enabled_breakpoint_names() as $breakpoint ) {
			if ( Breakpoint::get_base_breakpoint_name() === $breakpoint ) {
				continue;
			}

			$background_attr_value = ModuleUtils::use_attr_value(
				[
					'attr'         => $background_attrs,
					'breakpoint'   => $breakpoint,
					'state'        => 'value',
					'mode'         => 'getAndInheritAll',
					'defaultValue' => [],
				]
			);

			$background_style = Declarations::background_style_declaration(
				[
					'attr'       => $background_attrs,
					'attrValue'  => $background_attr_value,
					'breakpoint' => $breakpoint,
					'state'      => 'value',
					'important'  => true,
				]
			);

			if ( ! is_string( $background_style ) || '' === trim( $background_style ) ) {
				continue;
			}

			$at_rules = StyleUtils::get_at_rules( $breakpoint, $breakpoints );

			$styles[] = array_filter(
				[
					'selector'    => $slide_selector,
					'declaration' => $background_style,
					'atRules'     => $at_rules,
				]
			);
		}

		return $styles;
	}

	/**
	 * Build breakpoint settings for responsive slide styles.
	 *
	 * @return array
	 */
	private static function _get_slide_background_breakpoint_settings(): array {
		$breakpoint_settings = [];

		foreach ( Breakpoint::get_enabled_breakpoints() as $breakpoint ) {
			$breakpoint_name = $breakpoint['name'] ?? '';

			if ( '' === $breakpoint_name ) {
				continue;
			}

			$breakpoint_settings[ $breakpoint_name ] = [
				'baseDevice' => ! empty( $breakpoint['baseDevice'] ),
				'order'      => $breakpoint['order'] ?? 0,
				'maxWidth'   => [
					'value' => $breakpoint['maxWidth']['value'] ?? null,
				],
				'minWidth'   => [
					'value' => $breakpoint['minWidth']['value'] ?? null,
				],
			];
		}

		return $breakpoint_settings;
	}
}
