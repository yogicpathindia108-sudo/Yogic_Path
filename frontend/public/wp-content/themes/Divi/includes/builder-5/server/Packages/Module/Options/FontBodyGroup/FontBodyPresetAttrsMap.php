<?php
/**
 * Module: FontBodyPresetAttrsMap class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\FontBodyGroup;

use ET\Builder\Packages\Module\Options\Font\FontPresetAttrsMap;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * FontBodyPresetAttrsMap class.
 *
 * This class provides static map for the body font preset attributes.
 *
 * @since ??
 */
class FontBodyPresetAttrsMap {
	/**
	 * Get the map for the body font preset attributes.
	 *
	 * @since ??
	 *
	 * @param string $attr_name The attribute name.
	 *
	 * @return array The map for the body font preset attributes.
	 */
	public static function get_map( string $attr_name ) {
		$body_font_attrs_map  = FontPresetAttrsMap::get_map(
			"{$attr_name}.body",
			[
				'has_paragraph' => true,
			]
		);
		$link_font_attrs_map  = FontPresetAttrsMap::get_map( "{$attr_name}.link" );
		$ul_font_attrs_map    = FontPresetAttrsMap::get_map(
			"{$attr_name}.ul",
			[
				'has_list' => true,
			]
		);
		$ol_font_attrs_map    = FontPresetAttrsMap::get_map(
			"{$attr_name}.ol",
			[
				'has_list' => true,
			]
		);
		$quote_font_attrs_map = FontPresetAttrsMap::get_map(
			"{$attr_name}.quote",
			[
				'has_border' => true,
			]
		);
		$drop_cap_attrs_map   = [
			"{$attr_name}.dropCap.font__dropCapLineSize" => [
				'attrName' => "{$attr_name}.dropCap.font",
				'preset'   => [ 'style' ],
				'subName'  => 'dropCapLineSize',
			],
			"{$attr_name}.dropCap.font__dropCapSpacing"  => [
				'attrName' => "{$attr_name}.dropCap.font",
				'preset'   => [ 'style' ],
				'subName'  => 'dropCapSpacing',
			],
		];

		$drop_cap_font_attrs_map = FontPresetAttrsMap::get_map( "{$attr_name}.dropCap" );
		$drop_cap_font_attrs_map = array_filter(
			$drop_cap_font_attrs_map,
			function ( $preset_attr ) {
				$sub_name = $preset_attr['subName'] ?? '';
				return in_array(
					$sub_name,
					[
						'family',
						'weight',
						'weightFineTune',
						'opticalSizing',
						'color',
						'capitalization',
						'style',
						'lineColor',
						'lineThickness',
						'underlineOffset',
						'lineStyle',
						'dropCapLineSize',
						'dropCapSpacing',
					],
					true
				);
			}
		);

		return array_merge( $body_font_attrs_map, $link_font_attrs_map, $ul_font_attrs_map, $ol_font_attrs_map, $quote_font_attrs_map, $drop_cap_attrs_map, $drop_cap_font_attrs_map );
	}
}
