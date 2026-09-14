<?php
/**
 * Module Library: BarCountersItem Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\BarCountersItem;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


/**
 * Class BarCountersItemPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\BarCountersItem
 */
class BarCountersItemPresetAttrsMap {
	/**
	 * Get the preset attributes map for the BarCountersItem module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/counter' !== $module_name ) {
			return $map;
		}

		unset( $map['module.advanced.text.text__color'] );

		return array_merge(
			$map,
			[
				'barProgress.decoration.background__color' => [
					'attrName' => 'barProgress.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
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
