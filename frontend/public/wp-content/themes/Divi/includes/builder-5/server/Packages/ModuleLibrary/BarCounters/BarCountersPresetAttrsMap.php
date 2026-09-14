<?php
/**
 * Module Library: BarCounters Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\BarCounters;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


/**
 * Class BarCountersPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\BarCounters
 */
class BarCountersPresetAttrsMap {
	/**
	 * Get the preset attributes map for the BarCounters module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/counters' !== $module_name ) {
			return $map;
		}

		return array_merge(
			$map,
			[
				'barProgress.advanced.usePercentages'      => [
					'attrName' => 'barProgress.advanced.usePercentages',
					'preset'   => [ 'html' ],
				],
				'children.barProgress.decoration.background__color' => [
					'attrName' => 'children.barProgress.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'module.decoration.scroll__gridMotion.enable' => [
					'attrName' => 'module.decoration.scroll',
					'preset'   => [ 'script' ],
					'subName'  => 'gridMotion.enable',
				],
				'module.decoration.layout__display'        => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'display',
				],
				'module.decoration.layout__flexDirection'  => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'flexDirection',
				],
				'module.decoration.layout__flexWrap'       => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'flexWrap',
				],
				'module.decoration.layout__gridAutoColumns' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridAutoColumns',
				],
				'module.decoration.layout__gridAutoFlow'   => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridAutoFlow',
				],
				'module.decoration.layout__gridAutoRows'   => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridAutoRows',
				],
				'module.decoration.layout__gridColumnCount' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridColumnCount',
				],
				'module.decoration.layout__gridColumnMinWidth' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridColumnMinWidth',
				],
				'module.decoration.layout__gridColumnWidth' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridColumnWidth',
				],
				'module.decoration.layout__gridColumnWidths' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridColumnWidths',
				],
				'module.decoration.layout__gridDensity'    => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridDensity',
				],
				'module.decoration.layout__gridJustifyItems' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridJustifyItems',
				],
				'module.decoration.layout__gridOffsetRules' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridOffsetRules',
				],
				'module.decoration.layout__gridRowCount'   => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridRowCount',
				],
				'module.decoration.layout__gridRowHeight'  => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridRowHeight',
				],
				'module.decoration.layout__gridRowHeights' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridRowHeights',
				],
				'module.decoration.layout__gridRowMinHeight' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridRowMinHeight',
				],
				'module.decoration.layout__gridTemplateColumns' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridTemplateColumns',
				],
				'module.decoration.layout__gridTemplateRows' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridTemplateRows',
				],
				'module.decoration.layout__justifyContent' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'justifyContent',
				],
				'module.decoration.layout__alignItems'     => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'alignItems',
				],
				'module.decoration.layout__collapseEmptyColumns' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'collapseEmptyColumns',
				],
				'module.decoration.layout__alignContent'   => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'alignContent',
				],
				'module.decoration.layout__columnGap'      => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'columnGap',
				],
				'module.decoration.layout__rowGap'         => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'rowGap',
				],
				'module.advanced.html__elementType'        => [
					'attrName' => 'module.advanced.html',
					'preset'   => [ 'html' ],
					'subName'  => 'elementType',
				],
				'module.advanced.html__htmlAfter'          => [
					'attrName' => 'module.advanced.html',
					'preset'   => [ 'html' ],
					'subName'  => 'htmlAfter',
				],
				'module.advanced.html__htmlBefore'         => [
					'attrName' => 'module.advanced.html',
					'preset'   => [ 'html' ],
					'subName'  => 'htmlBefore',
				],
				'title.decoration.font.textEffects__fillType' => [
					'attrName' => 'title.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'title.decoration.font.textEffects__gradient' => [
					'attrName' => 'title.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'title.decoration.font.textEffects__gradient.type' => [
					'attrName' => 'title.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'title.decoration.font.textEffects__gradient.direction' => [
					'attrName' => 'title.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'title.decoration.font.textEffects__gradient.directionRadial' => [
					'attrName' => 'title.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'title.decoration.font.textEffects__gradient.repeat' => [
					'attrName' => 'title.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'title.decoration.font.textEffects__gradient.length' => [
					'attrName' => 'title.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'title.decoration.font.textEffects__imageFill.blend' => [
					'attrName' => 'title.decoration.font.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'title.decoration.font.textEffects__imageFill.height' => [
					'attrName' => 'title.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'title.decoration.font.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'title.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'title.decoration.font.textEffects__imageFill.position' => [
					'attrName' => 'title.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'title.decoration.font.textEffects__imageFill.repeat' => [
					'attrName' => 'title.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'title.decoration.font.textEffects__imageFill.size' => [
					'attrName' => 'title.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'title.decoration.font.textEffects__imageFill.url' => [
					'attrName' => 'title.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'title.decoration.font.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'title.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'title.decoration.font.textEffects__imageFill.width' => [
					'attrName' => 'title.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'title.decoration.font.textEffects__strokeColor' => [
					'attrName' => 'title.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'title.decoration.font.textEffects__strokeWidth' => [
					'attrName' => 'title.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
				'barProgress.decoration.font.textEffects__fillType' => [
					'attrName' => 'barProgress.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'barProgress.decoration.font.textEffects__gradient' => [
					'attrName' => 'barProgress.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'barProgress.decoration.font.textEffects__gradient.type' => [
					'attrName' => 'barProgress.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'barProgress.decoration.font.textEffects__gradient.direction' => [
					'attrName' => 'barProgress.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'barProgress.decoration.font.textEffects__gradient.directionRadial' => [
					'attrName' => 'barProgress.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'barProgress.decoration.font.textEffects__gradient.repeat' => [
					'attrName' => 'barProgress.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'barProgress.decoration.font.textEffects__gradient.length' => [
					'attrName' => 'barProgress.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'barProgress.decoration.font.textEffects__imageFill.blend' => [
					'attrName' => 'barProgress.decoration.font.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'barProgress.decoration.font.textEffects__imageFill.height' => [
					'attrName' => 'barProgress.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'barProgress.decoration.font.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'barProgress.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'barProgress.decoration.font.textEffects__imageFill.position' => [
					'attrName' => 'barProgress.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'barProgress.decoration.font.textEffects__imageFill.repeat' => [
					'attrName' => 'barProgress.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'barProgress.decoration.font.textEffects__imageFill.size' => [
					'attrName' => 'barProgress.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'barProgress.decoration.font.textEffects__imageFill.url' => [
					'attrName' => 'barProgress.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'barProgress.decoration.font.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'barProgress.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'barProgress.decoration.font.textEffects__imageFill.width' => [
					'attrName' => 'barProgress.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'barProgress.decoration.font.textEffects__strokeColor' => [
					'attrName' => 'barProgress.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'barProgress.decoration.font.textEffects__strokeWidth' => [
					'attrName' => 'barProgress.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
			]
		);
	}
}
