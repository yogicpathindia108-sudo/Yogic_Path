<?php
/**
 * Module: LayoutPresetAttrsMap class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Layout;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * LayoutPresetAttrsMap class.
 *
 * This class provides the static map for the layout preset attributes.
 *
 * @since ??
 */
class LayoutPresetAttrsMap {
	/**
	 * Get the map for the layout preset attributes.
	 *
	 * @since ??
	 *
	 * @param string $attr_name The attribute name.
	 *
	 * @return array The map for the layout preset attributes.
	 */
	public static function get_map( string $attr_name ) {
		return [
			"{$attr_name}__alignContent"         => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'alignContent',
			],
			"{$attr_name}__alignItems"           => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'alignItems',
			],
			"{$attr_name}__collapseEmptyColumns" => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'collapseEmptyColumns',
			],
			"{$attr_name}__columnGap"            => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'columnGap',
			],
			"{$attr_name}__display"              => [
				'attrName' => $attr_name,
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'display',
			],
			"{$attr_name}__flexDirection"        => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'flexDirection',
			],
			"{$attr_name}__flexWrap"             => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'flexWrap',
			],
			"{$attr_name}__gridAutoColumns"      => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridAutoColumns',
			],
			"{$attr_name}__gridAutoFlow"         => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridAutoFlow',
			],
			"{$attr_name}__gridAutoRows"         => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridAutoRows',
			],
			"{$attr_name}__gridColumnCount"      => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnCount',
			],
			"{$attr_name}__gridColumnMinWidth"   => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnMinWidth',
			],
			"{$attr_name}__gridColumnWidth"      => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnWidth',
			],
			"{$attr_name}__gridColumnWidths"     => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnWidths',
			],
			"{$attr_name}__gridDensity"          => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridDensity',
			],
			"{$attr_name}__gridJustifyItems"     => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridJustifyItems',
			],
			"{$attr_name}__gridRowCount"         => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowCount',
			],
			"{$attr_name}__gridRowHeight"        => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowHeight',
			],
			"{$attr_name}__gridRowHeights"       => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowHeights',
			],
			"{$attr_name}__gridRowMinHeight"     => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowMinHeight',
			],
			"{$attr_name}__gridTemplateColumns"  => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridTemplateColumns',
			],
			"{$attr_name}__gridTemplateRows"     => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridTemplateRows',
			],
			"{$attr_name}__justifyContent"       => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'justifyContent',
			],
			"{$attr_name}__rowGap"               => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'rowGap',
			],
			"{$attr_name}__gridOffsetRules"      => [
				'attrName' => $attr_name,
				'preset'   => [ 'style' ],
				'subName'  => 'gridOffsetRules',
			],
		];
	}
}
