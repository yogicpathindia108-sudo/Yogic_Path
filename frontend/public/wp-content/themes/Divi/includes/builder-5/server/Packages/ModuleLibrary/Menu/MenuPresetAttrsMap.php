<?php
/**
 * Module Library: Menu Module Preset Attributes Map
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Menu;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


/**
 * Class MenuPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\Menu
 */
class MenuPresetAttrsMap {
	/**
	 * Get the preset attributes map for the Menu module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/menu' !== $module_name ) {
			return $map;
		}

		unset( $map['module.advanced.text.textShadow__style'] );
		unset( $map['module.advanced.text.textShadow__horizontal'] );
		unset( $map['module.advanced.text.textShadow__vertical'] );
		unset( $map['module.advanced.text.textShadow__blur'] );
		unset( $map['module.advanced.text.textShadow__color'] );
		unset( $map['logo.decoration.sizing__alignment'] );
		unset( $map['logo.decoration.sizing__minHeight'] );
		unset( $map['menu.decoration.font.font__textAlign'] );
		unset( $map['menuDropdown.decoration.font__color'] );
		unset( $map['menuMobile.decoration.font__color'] );
		unset( $map['cartQuantity.decoration.font.font__textAlign'] );
		unset( $map['cartIcon.decoration.font__color'] );
		unset( $map['cartIcon.decoration.font__size'] );
		unset( $map['searchIcon.decoration.font__color'] );
		unset( $map['searchIcon.decoration.font__size'] );
		unset( $map['hamburgerMenuIcon.decoration.font__color'] );
		unset( $map['hamburgerMenuIcon.decoration.font__size'] );
		unset( $map['title.decoration.font.font__headingLevel'] );

		return array_merge(
			$map,
			[
				'menuDropdown.decoration.font.font__color' => [
					'attrName' => 'menuDropdown.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'menuMobile.decoration.font.font__color'   => [
					'attrName' => 'menuMobile.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'cartIcon.decoration.font.font__color'     => [
					'attrName' => 'cartIcon.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'searchIcon.decoration.font.font__color'   => [
					'attrName' => 'searchIcon.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'hamburgerMenuIcon.decoration.font.font__color' => [
					'attrName' => 'hamburgerMenuIcon.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'cartIcon.decoration.font.font__size'      => [
					'attrName' => 'cartIcon.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'size',
				],
				'searchIcon.decoration.font.font__size'    => [
					'attrName' => 'searchIcon.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'size',
				],
				'hamburgerMenuIcon.decoration.font.font__size' => [
					'attrName' => 'hamburgerMenuIcon.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'size',
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
				'menu.decoration.font.textEffects__fillType' => [
					'attrName' => 'menu.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'menu.decoration.font.textEffects__gradient' => [
					'attrName' => 'menu.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'menu.decoration.font.textEffects__gradient.type' => [
					'attrName' => 'menu.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'menu.decoration.font.textEffects__gradient.direction' => [
					'attrName' => 'menu.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'menu.decoration.font.textEffects__gradient.directionRadial' => [
					'attrName' => 'menu.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'menu.decoration.font.textEffects__gradient.repeat' => [
					'attrName' => 'menu.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'menu.decoration.font.textEffects__gradient.length' => [
					'attrName' => 'menu.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'menu.decoration.font.textEffects__imageFill.blend' => [
					'attrName' => 'menu.decoration.font.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'menu.decoration.font.textEffects__imageFill.height' => [
					'attrName' => 'menu.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'menu.decoration.font.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'menu.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'menu.decoration.font.textEffects__imageFill.position' => [
					'attrName' => 'menu.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'menu.decoration.font.textEffects__imageFill.repeat' => [
					'attrName' => 'menu.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'menu.decoration.font.textEffects__imageFill.size' => [
					'attrName' => 'menu.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'menu.decoration.font.textEffects__imageFill.url' => [
					'attrName' => 'menu.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'menu.decoration.font.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'menu.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'menu.decoration.font.textEffects__imageFill.width' => [
					'attrName' => 'menu.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'menu.decoration.font.textEffects__strokeColor' => [
					'attrName' => 'menu.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'menu.decoration.font.textEffects__strokeWidth' => [
					'attrName' => 'menu.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
				'cartQuantity.decoration.font.textEffects__fillType' => [
					'attrName' => 'cartQuantity.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'cartQuantity.decoration.font.textEffects__gradient' => [
					'attrName' => 'cartQuantity.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'cartQuantity.decoration.font.textEffects__gradient.type' => [
					'attrName' => 'cartQuantity.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'cartQuantity.decoration.font.textEffects__gradient.direction' => [
					'attrName' => 'cartQuantity.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'cartQuantity.decoration.font.textEffects__gradient.directionRadial' => [
					'attrName' => 'cartQuantity.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'cartQuantity.decoration.font.textEffects__gradient.repeat' => [
					'attrName' => 'cartQuantity.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'cartQuantity.decoration.font.textEffects__gradient.length' => [
					'attrName' => 'cartQuantity.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'cartQuantity.decoration.font.textEffects__imageFill.blend' => [
					'attrName' => 'cartQuantity.decoration.font.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'cartQuantity.decoration.font.textEffects__imageFill.height' => [
					'attrName' => 'cartQuantity.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'cartQuantity.decoration.font.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'cartQuantity.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'cartQuantity.decoration.font.textEffects__imageFill.position' => [
					'attrName' => 'cartQuantity.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'cartQuantity.decoration.font.textEffects__imageFill.repeat' => [
					'attrName' => 'cartQuantity.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'cartQuantity.decoration.font.textEffects__imageFill.size' => [
					'attrName' => 'cartQuantity.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'cartQuantity.decoration.font.textEffects__imageFill.url' => [
					'attrName' => 'cartQuantity.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'cartQuantity.decoration.font.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'cartQuantity.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'cartQuantity.decoration.font.textEffects__imageFill.width' => [
					'attrName' => 'cartQuantity.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'cartQuantity.decoration.font.textEffects__strokeColor' => [
					'attrName' => 'cartQuantity.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'cartQuantity.decoration.font.textEffects__strokeWidth' => [
					'attrName' => 'cartQuantity.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
			]
		);
	}
}
