<?php
/**
 * Module: FontPresetAttrsMap class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Font;

use ET\Builder\Packages\Module\Options\TextShadow\TextShadowPresetAttrsMap;
use ET\Builder\Packages\Module\Options\TextEffects\TextEffectsPresetAttrsMap;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * FontPresetAttrsMap class.
 *
 * This class provides static map for the text shadow preset attributes.
 *
 * @since ??
 */
class FontPresetAttrsMap {
	/**
	 * Get the map for the text shadow preset attributes.
	 *
	 * @since ??
	 *
	 * @param string $attr_name The attribute name.
	 * @param array  $args      The arguments.
	 *
	 * @return array The map for the text shadow preset attributes.
	 */
	public static function get_map( string $attr_name, array $args = [] ) {
		$default_args = [
			'has_list'          => false,
			'has_border'        => false,
			'has_heading_level' => false,
			'has_paragraph'     => false,
		];

		$args = array_merge( $default_args, $args );

		$font_attrs_map = [
			"{$attr_name}.font__family"        => [
				'attrName' => "{$attr_name}.font",
				'preset'   => [ 'style' ],
				'subName'  => 'family',
			],
			"{$attr_name}.font__weight"        => [
				'attrName' => "{$attr_name}.font",
				'preset'   => [ 'style' ],
				'subName'  => 'weight',
			],
			"{$attr_name}.font__weightFineTune" => [
				'attrName' => "{$attr_name}.font",
				'preset'   => [ 'style' ],
				'subName'  => 'weightFineTune',
			],
			"{$attr_name}.font__opticalSizing" => [
				'attrName' => "{$attr_name}.font",
				'preset'   => [ 'style' ],
				'subName'  => 'opticalSizing',
			],
			"{$attr_name}.font__style"         => [
				'attrName' => "{$attr_name}.font",
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			"{$attr_name}.font__lineColor"     => [
				'attrName' => "{$attr_name}.font",
				'preset'   => [ 'style' ],
				'subName'  => 'lineColor',
			],
			"{$attr_name}.font__lineStyle"     => [
				'attrName' => "{$attr_name}.font",
				'preset'   => [ 'style' ],
				'subName'  => 'lineStyle',
			],
			"{$attr_name}.font__lineThickness" => [
				'attrName' => "{$attr_name}.font",
				'preset'   => [ 'style' ],
				'subName'  => 'lineThickness',
			],
			"{$attr_name}.font__underlineOffset" => [
				'attrName' => "{$attr_name}.font",
				'preset'   => [ 'style' ],
				'subName'  => 'underlineOffset',
			],
			"{$attr_name}.font__textAlign"     => [
				'attrName' => "{$attr_name}.font",
				'preset'   => [ 'style' ],
				'subName'  => 'textAlign',
			],
			"{$attr_name}.font__textWrap"      => [
				'attrName' => "{$attr_name}.font",
				'preset'   => [ 'style' ],
				'subName'  => 'textWrap',
			],
			"{$attr_name}.font__writingMode"   => [
				'attrName' => "{$attr_name}.font",
				'preset'   => [ 'style' ],
				'subName'  => 'writingMode',
			],
			"{$attr_name}.font__hyphens"       => [
				'attrName' => "{$attr_name}.font",
				'preset'   => [ 'style' ],
				'subName'  => 'hyphens',
			],
			"{$attr_name}.font__columnCount"   => [
				'attrName' => "{$attr_name}.font",
				'preset'   => [ 'style' ],
				'subName'  => 'columnCount',
			],
			"{$attr_name}.font__columnGap"     => [
				'attrName' => "{$attr_name}.font",
				'preset'   => [ 'style' ],
				'subName'  => 'columnGap',
			],
			"{$attr_name}.font__color"         => [
				'attrName' => "{$attr_name}.font",
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			"{$attr_name}.font__capitalization" => [
				'attrName' => "{$attr_name}.font",
				'preset'   => [ 'style' ],
				'subName'  => 'capitalization',
			],
			"{$attr_name}.font__size"          => [
				'attrName' => "{$attr_name}.font",
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			"{$attr_name}.font__letterSpacing" => [
				'attrName' => "{$attr_name}.font",
				'preset'   => [ 'style' ],
				'subName'  => 'letterSpacing',
			],
			"{$attr_name}.font__lineHeight"    => [
				'attrName' => "{$attr_name}.font",
				'preset'   => [ 'style' ],
				'subName'  => 'lineHeight',
			],
		];

		$heading_level_attrs_map = $args['has_heading_level'] ? [
			"{$attr_name}.font__headingLevel" => [
				'attrName' => "{$attr_name}.font",
				'preset'   => [ 'html' ],
				'subName'  => 'headingLevel',
			],
		] : [];

		$list_font_attrs_map = $args['has_list'] ? [
			"{$attr_name}.list__type"       => [
				'attrName' => "{$attr_name}.list",
				'preset'   => [ 'style' ],
				'subName'  => 'type',
			],
			"{$attr_name}.list__position"   => [
				'attrName' => "{$attr_name}.list",
				'preset'   => [ 'style' ],
				'subName'  => 'position',
			],
			"{$attr_name}.list__itemIndent" => [
				'attrName' => "{$attr_name}.list",
				'preset'   => [ 'style' ],
				'subName'  => 'itemIndent',
			],
			"{$attr_name}.list__listSpacing" => [
				'attrName' => "{$attr_name}.list",
				'preset'   => [ 'style' ],
				'subName'  => 'listSpacing',
			],
		] : [];

		$paragraph_spacing_attrs_map = $args['has_paragraph'] ? [
			"{$attr_name}.list__paragraphSpacing" => [
				'attrName' => "{$attr_name}.list",
				'preset'   => [ 'style' ],
				'subName'  => 'paragraphSpacing',
			],
		] : [];

		$border_font_attrs_map = $args['has_border'] ? [
			"{$attr_name}.border__styles.left.width" => [
				'attrName' => "{$attr_name}.border",
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.width',
			],
			"{$attr_name}.border__styles.left.color" => [
				'attrName' => "{$attr_name}.border",
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.color',
			],
		] : [];

		$text_shadow_attrs_map = TextShadowPresetAttrsMap::get_map( "{$attr_name}.textShadow" );
		$text_effects_attrs_map = TextEffectsPresetAttrsMap::get_map( $attr_name );

		return array_merge( $font_attrs_map, $heading_level_attrs_map, $text_effects_attrs_map, $text_shadow_attrs_map, $list_font_attrs_map, $paragraph_spacing_attrs_map, $border_font_attrs_map );
	}
}
