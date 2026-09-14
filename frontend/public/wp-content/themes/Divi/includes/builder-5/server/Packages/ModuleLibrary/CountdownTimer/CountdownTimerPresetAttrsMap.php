<?php
/**
 * Module Library:Countdown Timer Module Preset Attributes Map
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\CountdownTimer;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


/**
 * Class CountdownTimerPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\CountdownTimer
 */
class CountdownTimerPresetAttrsMap {
	/**
	 * Get the preset attributes map for the CountdownTimer module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/countdown-timer' !== $module_name ) {
			return $map;
		}

		unset( $map['separator.decoration.font.font__textAlign'] );

		return array_merge(
			$map,
			[
				'title.decoration.font.font__headingLevel' => [
					'attrName' => 'title.decoration.font.font',
					'preset'   => [ 'html' ],
					'subName'  => 'headingLevel',
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
				'number.decoration.font.textEffects__fillType' => [
					'attrName' => 'number.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'number.decoration.font.textEffects__gradient' => [
					'attrName' => 'number.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'number.decoration.font.textEffects__gradient.type' => [
					'attrName' => 'number.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'number.decoration.font.textEffects__gradient.direction' => [
					'attrName' => 'number.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'number.decoration.font.textEffects__gradient.directionRadial' => [
					'attrName' => 'number.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'number.decoration.font.textEffects__gradient.repeat' => [
					'attrName' => 'number.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'number.decoration.font.textEffects__gradient.length' => [
					'attrName' => 'number.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'number.decoration.font.textEffects__imageFill.blend' => [
					'attrName' => 'number.decoration.font.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'number.decoration.font.textEffects__imageFill.height' => [
					'attrName' => 'number.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'number.decoration.font.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'number.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'number.decoration.font.textEffects__imageFill.position' => [
					'attrName' => 'number.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'number.decoration.font.textEffects__imageFill.repeat' => [
					'attrName' => 'number.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'number.decoration.font.textEffects__imageFill.size' => [
					'attrName' => 'number.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'number.decoration.font.textEffects__imageFill.url' => [
					'attrName' => 'number.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'number.decoration.font.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'number.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'number.decoration.font.textEffects__imageFill.width' => [
					'attrName' => 'number.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'number.decoration.font.textEffects__strokeColor' => [
					'attrName' => 'number.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'number.decoration.font.textEffects__strokeWidth' => [
					'attrName' => 'number.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
				'separator.decoration.font.textEffects__fillType' => [
					'attrName' => 'separator.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'separator.decoration.font.textEffects__gradient' => [
					'attrName' => 'separator.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'separator.decoration.font.textEffects__gradient.type' => [
					'attrName' => 'separator.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'separator.decoration.font.textEffects__gradient.direction' => [
					'attrName' => 'separator.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'separator.decoration.font.textEffects__gradient.directionRadial' => [
					'attrName' => 'separator.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'separator.decoration.font.textEffects__gradient.repeat' => [
					'attrName' => 'separator.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'separator.decoration.font.textEffects__gradient.length' => [
					'attrName' => 'separator.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'separator.decoration.font.textEffects__imageFill.blend' => [
					'attrName' => 'separator.decoration.font.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'separator.decoration.font.textEffects__imageFill.height' => [
					'attrName' => 'separator.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'separator.decoration.font.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'separator.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'separator.decoration.font.textEffects__imageFill.position' => [
					'attrName' => 'separator.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'separator.decoration.font.textEffects__imageFill.repeat' => [
					'attrName' => 'separator.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'separator.decoration.font.textEffects__imageFill.size' => [
					'attrName' => 'separator.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'separator.decoration.font.textEffects__imageFill.url' => [
					'attrName' => 'separator.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'separator.decoration.font.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'separator.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'separator.decoration.font.textEffects__imageFill.width' => [
					'attrName' => 'separator.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'separator.decoration.font.textEffects__strokeColor' => [
					'attrName' => 'separator.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'separator.decoration.font.textEffects__strokeWidth' => [
					'attrName' => 'separator.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
				'label.decoration.font.textEffects__fillType' => [
					'attrName' => 'label.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'label.decoration.font.textEffects__gradient' => [
					'attrName' => 'label.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'label.decoration.font.textEffects__gradient.type' => [
					'attrName' => 'label.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'label.decoration.font.textEffects__gradient.direction' => [
					'attrName' => 'label.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'label.decoration.font.textEffects__gradient.directionRadial' => [
					'attrName' => 'label.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'label.decoration.font.textEffects__gradient.repeat' => [
					'attrName' => 'label.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'label.decoration.font.textEffects__gradient.length' => [
					'attrName' => 'label.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'label.decoration.font.textEffects__imageFill.blend' => [
					'attrName' => 'label.decoration.font.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'label.decoration.font.textEffects__imageFill.height' => [
					'attrName' => 'label.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'label.decoration.font.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'label.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'label.decoration.font.textEffects__imageFill.position' => [
					'attrName' => 'label.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'label.decoration.font.textEffects__imageFill.repeat' => [
					'attrName' => 'label.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'label.decoration.font.textEffects__imageFill.size' => [
					'attrName' => 'label.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'label.decoration.font.textEffects__imageFill.url' => [
					'attrName' => 'label.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'label.decoration.font.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'label.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'label.decoration.font.textEffects__imageFill.width' => [
					'attrName' => 'label.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'label.decoration.font.textEffects__strokeColor' => [
					'attrName' => 'label.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'label.decoration.font.textEffects__strokeWidth' => [
					'attrName' => 'label.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
			]
		);
	}
}
