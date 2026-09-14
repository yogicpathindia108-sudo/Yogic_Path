<?php
/**
 * Module: SizingPresetAttrsMap class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Sizing;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * SizingPresetAttrsMap class.
 *
 * This class provides the static map for the sizing preset attributes.
 *
 * @since ??
 */
class SizingPresetAttrsMap {
	/**
	 * Get the map for the sizing preset attributes.
	 *
	 * @since ??
	 *
	 * @param string $attr_name The attribute name.
	 *
	 * @return array The map for the sizing preset attributes.
	 */
	public static function get_map( string $attr_name ) {
		return [
			"{$attr_name}__width"           => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'width',
			],
			"{$attr_name}__maxWidth"        => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'maxWidth',
			],
			"{$attr_name}__alignSelf"       => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'alignSelf',
			],
			"{$attr_name}__alignment"       => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'alignment',
			],
			"{$attr_name}__flexGrow"        => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'flexGrow',
			],
			"{$attr_name}__flexShrink"      => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'flexShrink',
			],
			"{$attr_name}__gridAlignSelf"   => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridAlignSelf',
			],
			"{$attr_name}__gridColumnSpan"  => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnSpan',
			],
			"{$attr_name}__gridColumnStart" => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnStart',
			],
			"{$attr_name}__gridJustifySelf" => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridJustifySelf',
			],
			"{$attr_name}__gridRowSpan"     => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowSpan',
			],
			"{$attr_name}__gridRowStart"    => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowStart',
			],
			"{$attr_name}__gridColumnEnd"   => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnEnd',
			],
			"{$attr_name}__gridRowEnd"      => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowEnd',
			],
			"{$attr_name}__minHeight"       => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'minHeight',
			],
			"{$attr_name}__size"            => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			"{$attr_name}__height"          => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'height',
			],
			"{$attr_name}__maxHeight"       => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'maxHeight',
			],
			"{$attr_name}__aspectRatio"       => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'aspectRatio',
			],
			"{$attr_name}__flexType"        => [
				'attrName' => $attr_name,
				'preset'   => [ 'html' ],
				'subName'  => 'flexType',
			],
		];
	}
}
