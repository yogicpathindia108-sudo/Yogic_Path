<?php
/**
 * Module Library: Sidebar Module Preset Attributes Map
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Sidebar;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Class SidebarPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\Sidebar
 */
class SidebarPresetAttrsMap {
	/**
	 * Get the preset attributes map for the Sidebar module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/sidebar' !== $module_name ) {
			return $map;
		}

		return [
			'sidebar.innerContent__area'                   => [
				'attrName' => 'sidebar.innerContent',
				'preset'   => 'content',
				'subName'  => 'area',
			],
			'sidebar.advanced.layout__layoutStyle'         => [
				'attrName' => 'sidebar.advanced.layout',
				'preset'   => [ 'html' ],
				'subName'  => 'layoutStyle',
			],
			'sidebar.advanced.layout__alignment'           => [
				'attrName' => 'sidebar.advanced.layout',
				'preset'   => [ 'html' ],
				'subName'  => 'alignment',
			],
			'sidebar.advanced.layout__showBorder'          => [
				'attrName' => 'sidebar.advanced.layout',
				'preset'   => [ 'html' ],
				'subName'  => 'showBorder',
			],
			'sidebarWidgets.advanced.flexType'             => [
				'attrName' => 'sidebarWidgets.advanced.flexType',
				'preset'   => [ 'html' ],
			],
			'sidebar.decoration.layout__alignContent'      => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'alignContent',
			],
			'sidebar.decoration.layout__alignItems'        => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'alignItems',
			],
			'sidebar.decoration.layout__columnGap'         => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'columnGap',
			],
			'sidebar.decoration.layout__display'           => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'display',
			],
			'sidebar.decoration.layout__flexDirection'     => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'flexDirection',
			],
			'sidebar.decoration.layout__flexWrap'          => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'flexWrap',
			],
			'sidebar.decoration.layout__justifyContent'    => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'justifyContent',
			],
			'sidebar.decoration.layout__rowGap'            => [
				'attrName' => 'sidebar.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'rowGap',
			],
			'module.decoration.layout__display'            => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'display',
			],
			'module.decoration.layout__flexDirection'      => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'flexDirection',
			],
			'module.decoration.layout__flexWrap'           => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'flexWrap',
			],
			'module.decoration.layout__gridAutoColumns'    => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'gridAutoColumns',
			],
			'module.decoration.layout__gridAutoFlow'       => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gridAutoFlow',
			],
			'module.decoration.layout__gridAutoRows'       => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'gridAutoRows',
			],
			'module.decoration.layout__gridColumnCount'    => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnCount',
			],
			'module.decoration.layout__gridColumnMinWidth' => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnMinWidth',
			],
			'module.decoration.layout__gridColumnWidth'    => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnWidth',
			],
			'module.decoration.layout__gridColumnWidths'   => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gridColumnWidths',
			],
			'module.decoration.layout__gridDensity'        => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gridDensity',
			],
			'module.decoration.layout__gridJustifyItems'   => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gridJustifyItems',
			],
			'module.decoration.layout__gridRowCount'       => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowCount',
			],
			'module.decoration.layout__gridRowHeight'      => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowHeight',
			],
			'module.decoration.layout__gridRowHeights'     => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gridRowHeights',
			],
			'module.decoration.layout__gridRowMinHeight'   => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowMinHeight',
			],
			'module.decoration.layout__gridTemplateColumns' => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gridTemplateColumns',
			],
			'module.decoration.layout__gridTemplateRows'   => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gridTemplateRows',
			],
			'module.decoration.layout__justifyContent'     => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'justifyContent',
			],
			'module.decoration.layout__rowGap'             => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'rowGap',
			],
			'module.advanced.html__elementType'            => [
				'attrName' => 'module.advanced.html',
				'preset'   => [ 'html' ],
				'subName'  => 'elementType',
			],
			'module.advanced.html__htmlAfter'              => [
				'attrName' => 'module.advanced.html',
				'preset'   => [ 'html' ],
				'subName'  => 'htmlAfter',
			],
			'module.advanced.html__htmlBefore'             => [
				'attrName' => 'module.advanced.html',
				'preset'   => [ 'html' ],
				'subName'  => 'htmlBefore',
			],
			'title.decoration.font.textEffects__fillType'  => [
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
			'sidebar.decoration.font.textEffects__fillType' => [
				'attrName' => 'sidebar.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'sidebar.decoration.font.textEffects__gradient' => [
				'attrName' => 'sidebar.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient',
			],
			'sidebar.decoration.font.textEffects__gradient.type' => [
				'attrName' => 'sidebar.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.type',
			],
			'sidebar.decoration.font.textEffects__gradient.direction' => [
				'attrName' => 'sidebar.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.direction',
			],
			'sidebar.decoration.font.textEffects__gradient.directionRadial' => [
				'attrName' => 'sidebar.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.directionRadial',
			],
			'sidebar.decoration.font.textEffects__gradient.repeat' => [
				'attrName' => 'sidebar.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.repeat',
			],
			'sidebar.decoration.font.textEffects__gradient.length' => [
				'attrName' => 'sidebar.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.length',
			],
			'sidebar.decoration.font.textEffects__imageFill.blend' => [
				'attrName' => 'sidebar.decoration.font.textEffects',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'imageFill.blend',
			],
			'sidebar.decoration.font.textEffects__imageFill.height' => [
				'attrName' => 'sidebar.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.height',
			],
			'sidebar.decoration.font.textEffects__imageFill.horizontalOffset' => [
				'attrName' => 'sidebar.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.horizontalOffset',
			],
			'sidebar.decoration.font.textEffects__imageFill.position' => [
				'attrName' => 'sidebar.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.position',
			],
			'sidebar.decoration.font.textEffects__imageFill.repeat' => [
				'attrName' => 'sidebar.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.repeat',
			],
			'sidebar.decoration.font.textEffects__imageFill.size' => [
				'attrName' => 'sidebar.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.size',
			],
			'sidebar.decoration.font.textEffects__imageFill.url' => [
				'attrName' => 'sidebar.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.url',
			],
			'sidebar.decoration.font.textEffects__imageFill.verticalOffset' => [
				'attrName' => 'sidebar.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.verticalOffset',
			],
			'sidebar.decoration.font.textEffects__imageFill.width' => [
				'attrName' => 'sidebar.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.width',
			],
			'sidebar.decoration.font.textEffects__strokeColor' => [
				'attrName' => 'sidebar.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeColor',
			],
			'sidebar.decoration.font.textEffects__strokeWidth' => [
				'attrName' => 'sidebar.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeWidth',
			],
		];
	}
}
