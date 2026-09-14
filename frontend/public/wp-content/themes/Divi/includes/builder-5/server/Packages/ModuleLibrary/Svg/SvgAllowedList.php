<?php
/**
 * SVG allowlist policy utility.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Svg;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * SvgAllowedList class.
 *
 * Centralized allowlist contract used by sanitizer and post-sanitize attribute application.
 *
 * @since ??
 */
class SvgAllowedList {
	/**
	 * Common attributes shared by root and shape tags.
	 *
	 * @return array<string, bool>
	 */
	private static function _common_attributes(): array {
		return [
			'class'             => true,
			'fill'              => true,
			'fill-opacity'      => true,
			'stroke'            => true,
			'stroke-width'      => true,
			'stroke-opacity'    => true,
			'stroke-linecap'    => true,
			'stroke-linejoin'   => true,
			'stroke-dasharray'  => true,
			'stroke-dashoffset' => true,
			'stroke-miterlimit' => true,
			'opacity'           => true,
			'transform'         => true,
			'clip-path'         => true,
			'mask'              => true,
			'filter'            => true,
			'id'                => true,
			'serif:id'          => true,
		];
	}

	/**
	 * Root svg attributes used by sanitizer policy.
	 *
	 * @return array<string, bool>
	 */
	private static function _root_svg_base_attributes(): array {
		return array_merge(
			self::_common_attributes(),
			[
				'xmlns'               => true,
				'xmlns:serif'         => true,
				'xmlns:xlink'         => true,
				'viewBox'             => true,
				'viewbox'             => true,
				'width'               => true,
				'height'              => true,
				'preserveAspectRatio' => true,
				'preserveaspectratio' => true,
				'role'                => true,
				'aria-hidden'         => true,
				'aria-label'          => true,
				'focusable'           => true,
			]
		);
	}

	/**
	 * Text-specific SVG attributes.
	 *
	 * @return array<string, bool>
	 */
	private static function _text_attributes(): array {
		return [
			'x'            => true,
			'y'            => true,
			'dx'           => true,
			'dy'           => true,
			'rotate'       => true,
			'textLength'   => true,
			'textlength'   => true,
			'lengthAdjust' => true,
			'lengthadjust' => true,
		];
	}

	/**
	 * textPath-specific SVG attributes.
	 *
	 * @return array<string, bool>
	 */
	private static function _text_path_attributes(): array {
		return [
			'href'         => true,
			'xlink:href'   => true,
			'startOffset'  => true,
			'startoffset'  => true,
			'method'       => true,
			'spacing'      => true,
			'side'         => true,
			'textLength'   => true,
			'textlength'   => true,
			'lengthAdjust' => true,
			'lengthadjust' => true,
		];
	}

	/**
	 * Get root svg custom attributes used in post-sanitize merge path.
	 *
	 * @return array<string, bool>
	 */
	public static function get_allowed_root_svg_custom_attributes(): array {
		return array_merge(
			self::_root_svg_base_attributes(),
			[
				'style' => true,
				'title' => true,
			]
		);
	}

	/**
	 * Get full allowed SVG tags and attributes for wp_kses sanitization.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function get_allowed_svg_html(): array {
		$common_attributes = self::_common_attributes();
		$text_attributes   = self::_text_attributes();

		return [
			'svg'            => self::_root_svg_base_attributes(),
			'g'              => $common_attributes,
			'path'           => array_merge( $common_attributes, [ 'd' => true ] ),
			'circle'         => array_merge(
				$common_attributes,
				[
					'cx' => true,
					'cy' => true,
					'r'  => true,
				]
			),
			'ellipse'        => array_merge(
				$common_attributes,
				[
					'cx' => true,
					'cy' => true,
					'rx' => true,
					'ry' => true,
				]
			),
			'rect'           => array_merge(
				$common_attributes,
				[
					'x'      => true,
					'y'      => true,
					'width'  => true,
					'height' => true,
					'rx'     => true,
					'ry'     => true,
				]
			),
			'line'           => array_merge(
				$common_attributes,
				[
					'x1' => true,
					'y1' => true,
					'x2' => true,
					'y2' => true,
				]
			),
			'polyline'       => array_merge( $common_attributes, [ 'points' => true ] ),
			'polygon'        => array_merge( $common_attributes, [ 'points' => true ] ),
			'text'           => array_merge( $common_attributes, $text_attributes ),
			'tspan'          => array_merge( $common_attributes, $text_attributes ),
			'textPath'       => array_merge( $common_attributes, self::_text_path_attributes() ),
			'defs'           => [],
			'title'          => [],
			'desc'           => [],
			'linearGradient' => [
				'id'                => true,
				'x1'                => true,
				'y1'                => true,
				'x2'                => true,
				'y2'                => true,
				'gradientUnits'     => true,
				'gradientunits'     => true,
				'gradientTransform' => true,
				'gradienttransform' => true,
			],
			'radialGradient' => [
				'id'                => true,
				'cx'                => true,
				'cy'                => true,
				'r'                 => true,
				'fx'                => true,
				'fy'                => true,
				'gradientUnits'     => true,
				'gradientunits'     => true,
				'gradientTransform' => true,
				'gradienttransform' => true,
			],
			'stop'           => [
				'offset'       => true,
				'stop-color'   => true,
				'stop-opacity' => true,
			],
			'clipPath'       => [
				'id'            => true,
				'clipPathUnits' => true,
				'clippathunits' => true,
			],
			'mask'           => [
				'id'        => true,
				'x'         => true,
				'y'         => true,
				'width'     => true,
				'height'    => true,
				'maskUnits' => true,
				'maskunits' => true,
			],
			'symbol'         => [
				'id'                  => true,
				'viewBox'             => true,
				'viewbox'             => true,
				'preserveAspectRatio' => true,
				'preserveaspectratio' => true,
			],
			'use'            => [
				'href'       => true,
				'xlink:href' => true,
				'x'          => true,
				'y'          => true,
				'width'      => true,
				'height'     => true,
			],
		];
	}
}
