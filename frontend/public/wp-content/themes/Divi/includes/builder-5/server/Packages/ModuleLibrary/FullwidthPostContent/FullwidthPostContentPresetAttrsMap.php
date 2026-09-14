<?php
/**
 * Module Library: FullwidthPostContent Module Preset Attributes Map
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\FullwidthPostContent;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


/**
 * Class FullwidthPostContentPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\FullwidthPostContent
 */
class FullwidthPostContentPresetAttrsMap {
	/**
	 * Get the preset attributes map for the Fullwidth Post Content module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( ! in_array( $module_name, [ 'divi/fullwidth-post-content', 'divi/post-content' ], true ) ) {
			return $map;
		}

		return array_merge(
			$map,
			[
				'css__freeForm'                     => [
					'attrName' => 'css',
					'preset'   => [ 'style' ],
					'subName'  => 'freeForm',
				],
				'module.advanced.html__elementType' => [
					'attrName' => 'module.advanced.html',
					'preset'   => [ 'html' ],
					'subName'  => 'elementType',
				],
				'module.advanced.html__htmlAfter'   => [
					'attrName' => 'module.advanced.html',
					'preset'   => [ 'html' ],
					'subName'  => 'htmlAfter',
				],
				'module.advanced.html__htmlBefore'  => [
					'attrName' => 'module.advanced.html',
					'preset'   => [ 'html' ],
					'subName'  => 'htmlBefore',
				],
				'module.decoration.headingFont.h1.textEffects__fillType' => [
					'attrName' => 'module.decoration.headingFont.h1.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'module.decoration.headingFont.h1.textEffects__gradient' => [
					'attrName' => 'module.decoration.headingFont.h1.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'module.decoration.headingFont.h1.textEffects__gradient.type' => [
					'attrName' => 'module.decoration.headingFont.h1.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'module.decoration.headingFont.h1.textEffects__gradient.direction' => [
					'attrName' => 'module.decoration.headingFont.h1.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'module.decoration.headingFont.h1.textEffects__gradient.directionRadial' => [
					'attrName' => 'module.decoration.headingFont.h1.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'module.decoration.headingFont.h1.textEffects__gradient.repeat' => [
					'attrName' => 'module.decoration.headingFont.h1.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'module.decoration.headingFont.h1.textEffects__gradient.length' => [
					'attrName' => 'module.decoration.headingFont.h1.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'module.decoration.headingFont.h1.textEffects__imageFill.blend' => [
					'attrName' => 'module.decoration.headingFont.h1.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'module.decoration.headingFont.h1.textEffects__imageFill.height' => [
					'attrName' => 'module.decoration.headingFont.h1.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'module.decoration.headingFont.h1.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'module.decoration.headingFont.h1.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'module.decoration.headingFont.h1.textEffects__imageFill.position' => [
					'attrName' => 'module.decoration.headingFont.h1.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'module.decoration.headingFont.h1.textEffects__imageFill.repeat' => [
					'attrName' => 'module.decoration.headingFont.h1.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'module.decoration.headingFont.h1.textEffects__imageFill.size' => [
					'attrName' => 'module.decoration.headingFont.h1.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'module.decoration.headingFont.h1.textEffects__imageFill.url' => [
					'attrName' => 'module.decoration.headingFont.h1.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'module.decoration.headingFont.h1.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'module.decoration.headingFont.h1.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'module.decoration.headingFont.h1.textEffects__imageFill.width' => [
					'attrName' => 'module.decoration.headingFont.h1.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'module.decoration.headingFont.h1.textEffects__strokeColor' => [
					'attrName' => 'module.decoration.headingFont.h1.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'module.decoration.headingFont.h1.textEffects__strokeWidth' => [
					'attrName' => 'module.decoration.headingFont.h1.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
				'module.decoration.headingFont.h2.textEffects__fillType' => [
					'attrName' => 'module.decoration.headingFont.h2.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'module.decoration.headingFont.h2.textEffects__gradient' => [
					'attrName' => 'module.decoration.headingFont.h2.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'module.decoration.headingFont.h2.textEffects__gradient.type' => [
					'attrName' => 'module.decoration.headingFont.h2.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'module.decoration.headingFont.h2.textEffects__gradient.direction' => [
					'attrName' => 'module.decoration.headingFont.h2.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'module.decoration.headingFont.h2.textEffects__gradient.directionRadial' => [
					'attrName' => 'module.decoration.headingFont.h2.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'module.decoration.headingFont.h2.textEffects__gradient.repeat' => [
					'attrName' => 'module.decoration.headingFont.h2.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'module.decoration.headingFont.h2.textEffects__gradient.length' => [
					'attrName' => 'module.decoration.headingFont.h2.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'module.decoration.headingFont.h2.textEffects__imageFill.blend' => [
					'attrName' => 'module.decoration.headingFont.h2.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'module.decoration.headingFont.h2.textEffects__imageFill.height' => [
					'attrName' => 'module.decoration.headingFont.h2.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'module.decoration.headingFont.h2.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'module.decoration.headingFont.h2.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'module.decoration.headingFont.h2.textEffects__imageFill.position' => [
					'attrName' => 'module.decoration.headingFont.h2.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'module.decoration.headingFont.h2.textEffects__imageFill.repeat' => [
					'attrName' => 'module.decoration.headingFont.h2.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'module.decoration.headingFont.h2.textEffects__imageFill.size' => [
					'attrName' => 'module.decoration.headingFont.h2.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'module.decoration.headingFont.h2.textEffects__imageFill.url' => [
					'attrName' => 'module.decoration.headingFont.h2.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'module.decoration.headingFont.h2.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'module.decoration.headingFont.h2.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'module.decoration.headingFont.h2.textEffects__imageFill.width' => [
					'attrName' => 'module.decoration.headingFont.h2.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'module.decoration.headingFont.h2.textEffects__strokeColor' => [
					'attrName' => 'module.decoration.headingFont.h2.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'module.decoration.headingFont.h2.textEffects__strokeWidth' => [
					'attrName' => 'module.decoration.headingFont.h2.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
				'module.decoration.headingFont.h3.textEffects__fillType' => [
					'attrName' => 'module.decoration.headingFont.h3.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'module.decoration.headingFont.h3.textEffects__gradient' => [
					'attrName' => 'module.decoration.headingFont.h3.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'module.decoration.headingFont.h3.textEffects__gradient.type' => [
					'attrName' => 'module.decoration.headingFont.h3.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'module.decoration.headingFont.h3.textEffects__gradient.direction' => [
					'attrName' => 'module.decoration.headingFont.h3.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'module.decoration.headingFont.h3.textEffects__gradient.directionRadial' => [
					'attrName' => 'module.decoration.headingFont.h3.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'module.decoration.headingFont.h3.textEffects__gradient.repeat' => [
					'attrName' => 'module.decoration.headingFont.h3.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'module.decoration.headingFont.h3.textEffects__gradient.length' => [
					'attrName' => 'module.decoration.headingFont.h3.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'module.decoration.headingFont.h3.textEffects__imageFill.blend' => [
					'attrName' => 'module.decoration.headingFont.h3.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'module.decoration.headingFont.h3.textEffects__imageFill.height' => [
					'attrName' => 'module.decoration.headingFont.h3.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'module.decoration.headingFont.h3.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'module.decoration.headingFont.h3.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'module.decoration.headingFont.h3.textEffects__imageFill.position' => [
					'attrName' => 'module.decoration.headingFont.h3.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'module.decoration.headingFont.h3.textEffects__imageFill.repeat' => [
					'attrName' => 'module.decoration.headingFont.h3.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'module.decoration.headingFont.h3.textEffects__imageFill.size' => [
					'attrName' => 'module.decoration.headingFont.h3.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'module.decoration.headingFont.h3.textEffects__imageFill.url' => [
					'attrName' => 'module.decoration.headingFont.h3.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'module.decoration.headingFont.h3.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'module.decoration.headingFont.h3.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'module.decoration.headingFont.h3.textEffects__imageFill.width' => [
					'attrName' => 'module.decoration.headingFont.h3.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'module.decoration.headingFont.h3.textEffects__strokeColor' => [
					'attrName' => 'module.decoration.headingFont.h3.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'module.decoration.headingFont.h3.textEffects__strokeWidth' => [
					'attrName' => 'module.decoration.headingFont.h3.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
				'module.decoration.headingFont.h4.textEffects__fillType' => [
					'attrName' => 'module.decoration.headingFont.h4.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'module.decoration.headingFont.h4.textEffects__gradient' => [
					'attrName' => 'module.decoration.headingFont.h4.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'module.decoration.headingFont.h4.textEffects__gradient.type' => [
					'attrName' => 'module.decoration.headingFont.h4.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'module.decoration.headingFont.h4.textEffects__gradient.direction' => [
					'attrName' => 'module.decoration.headingFont.h4.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'module.decoration.headingFont.h4.textEffects__gradient.directionRadial' => [
					'attrName' => 'module.decoration.headingFont.h4.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'module.decoration.headingFont.h4.textEffects__gradient.repeat' => [
					'attrName' => 'module.decoration.headingFont.h4.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'module.decoration.headingFont.h4.textEffects__gradient.length' => [
					'attrName' => 'module.decoration.headingFont.h4.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'module.decoration.headingFont.h4.textEffects__imageFill.blend' => [
					'attrName' => 'module.decoration.headingFont.h4.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'module.decoration.headingFont.h4.textEffects__imageFill.height' => [
					'attrName' => 'module.decoration.headingFont.h4.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'module.decoration.headingFont.h4.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'module.decoration.headingFont.h4.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'module.decoration.headingFont.h4.textEffects__imageFill.position' => [
					'attrName' => 'module.decoration.headingFont.h4.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'module.decoration.headingFont.h4.textEffects__imageFill.repeat' => [
					'attrName' => 'module.decoration.headingFont.h4.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'module.decoration.headingFont.h4.textEffects__imageFill.size' => [
					'attrName' => 'module.decoration.headingFont.h4.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'module.decoration.headingFont.h4.textEffects__imageFill.url' => [
					'attrName' => 'module.decoration.headingFont.h4.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'module.decoration.headingFont.h4.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'module.decoration.headingFont.h4.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'module.decoration.headingFont.h4.textEffects__imageFill.width' => [
					'attrName' => 'module.decoration.headingFont.h4.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'module.decoration.headingFont.h4.textEffects__strokeColor' => [
					'attrName' => 'module.decoration.headingFont.h4.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'module.decoration.headingFont.h4.textEffects__strokeWidth' => [
					'attrName' => 'module.decoration.headingFont.h4.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
				'module.decoration.headingFont.h5.textEffects__fillType' => [
					'attrName' => 'module.decoration.headingFont.h5.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'module.decoration.headingFont.h5.textEffects__gradient' => [
					'attrName' => 'module.decoration.headingFont.h5.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'module.decoration.headingFont.h5.textEffects__gradient.type' => [
					'attrName' => 'module.decoration.headingFont.h5.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'module.decoration.headingFont.h5.textEffects__gradient.direction' => [
					'attrName' => 'module.decoration.headingFont.h5.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'module.decoration.headingFont.h5.textEffects__gradient.directionRadial' => [
					'attrName' => 'module.decoration.headingFont.h5.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'module.decoration.headingFont.h5.textEffects__gradient.repeat' => [
					'attrName' => 'module.decoration.headingFont.h5.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'module.decoration.headingFont.h5.textEffects__gradient.length' => [
					'attrName' => 'module.decoration.headingFont.h5.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'module.decoration.headingFont.h5.textEffects__imageFill.blend' => [
					'attrName' => 'module.decoration.headingFont.h5.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'module.decoration.headingFont.h5.textEffects__imageFill.height' => [
					'attrName' => 'module.decoration.headingFont.h5.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'module.decoration.headingFont.h5.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'module.decoration.headingFont.h5.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'module.decoration.headingFont.h5.textEffects__imageFill.position' => [
					'attrName' => 'module.decoration.headingFont.h5.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'module.decoration.headingFont.h5.textEffects__imageFill.repeat' => [
					'attrName' => 'module.decoration.headingFont.h5.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'module.decoration.headingFont.h5.textEffects__imageFill.size' => [
					'attrName' => 'module.decoration.headingFont.h5.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'module.decoration.headingFont.h5.textEffects__imageFill.url' => [
					'attrName' => 'module.decoration.headingFont.h5.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'module.decoration.headingFont.h5.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'module.decoration.headingFont.h5.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'module.decoration.headingFont.h5.textEffects__imageFill.width' => [
					'attrName' => 'module.decoration.headingFont.h5.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'module.decoration.headingFont.h5.textEffects__strokeColor' => [
					'attrName' => 'module.decoration.headingFont.h5.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'module.decoration.headingFont.h5.textEffects__strokeWidth' => [
					'attrName' => 'module.decoration.headingFont.h5.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
				'module.decoration.headingFont.h6.textEffects__fillType' => [
					'attrName' => 'module.decoration.headingFont.h6.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'module.decoration.headingFont.h6.textEffects__gradient' => [
					'attrName' => 'module.decoration.headingFont.h6.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'module.decoration.headingFont.h6.textEffects__gradient.type' => [
					'attrName' => 'module.decoration.headingFont.h6.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'module.decoration.headingFont.h6.textEffects__gradient.direction' => [
					'attrName' => 'module.decoration.headingFont.h6.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'module.decoration.headingFont.h6.textEffects__gradient.directionRadial' => [
					'attrName' => 'module.decoration.headingFont.h6.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'module.decoration.headingFont.h6.textEffects__gradient.repeat' => [
					'attrName' => 'module.decoration.headingFont.h6.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'module.decoration.headingFont.h6.textEffects__gradient.length' => [
					'attrName' => 'module.decoration.headingFont.h6.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'module.decoration.headingFont.h6.textEffects__imageFill.blend' => [
					'attrName' => 'module.decoration.headingFont.h6.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'module.decoration.headingFont.h6.textEffects__imageFill.height' => [
					'attrName' => 'module.decoration.headingFont.h6.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'module.decoration.headingFont.h6.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'module.decoration.headingFont.h6.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'module.decoration.headingFont.h6.textEffects__imageFill.position' => [
					'attrName' => 'module.decoration.headingFont.h6.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'module.decoration.headingFont.h6.textEffects__imageFill.repeat' => [
					'attrName' => 'module.decoration.headingFont.h6.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'module.decoration.headingFont.h6.textEffects__imageFill.size' => [
					'attrName' => 'module.decoration.headingFont.h6.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'module.decoration.headingFont.h6.textEffects__imageFill.url' => [
					'attrName' => 'module.decoration.headingFont.h6.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'module.decoration.headingFont.h6.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'module.decoration.headingFont.h6.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'module.decoration.headingFont.h6.textEffects__imageFill.width' => [
					'attrName' => 'module.decoration.headingFont.h6.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'module.decoration.headingFont.h6.textEffects__strokeColor' => [
					'attrName' => 'module.decoration.headingFont.h6.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'module.decoration.headingFont.h6.textEffects__strokeWidth' => [
					'attrName' => 'module.decoration.headingFont.h6.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
				'module.decoration.bodyFont.body.textEffects__fillType' => [
					'attrName' => 'module.decoration.bodyFont.body.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'module.decoration.bodyFont.body.textEffects__gradient' => [
					'attrName' => 'module.decoration.bodyFont.body.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'module.decoration.bodyFont.body.textEffects__gradient.type' => [
					'attrName' => 'module.decoration.bodyFont.body.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'module.decoration.bodyFont.body.textEffects__gradient.direction' => [
					'attrName' => 'module.decoration.bodyFont.body.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'module.decoration.bodyFont.body.textEffects__gradient.directionRadial' => [
					'attrName' => 'module.decoration.bodyFont.body.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'module.decoration.bodyFont.body.textEffects__gradient.repeat' => [
					'attrName' => 'module.decoration.bodyFont.body.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'module.decoration.bodyFont.body.textEffects__gradient.length' => [
					'attrName' => 'module.decoration.bodyFont.body.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'module.decoration.bodyFont.body.textEffects__imageFill.blend' => [
					'attrName' => 'module.decoration.bodyFont.body.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'module.decoration.bodyFont.body.textEffects__imageFill.height' => [
					'attrName' => 'module.decoration.bodyFont.body.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'module.decoration.bodyFont.body.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'module.decoration.bodyFont.body.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'module.decoration.bodyFont.body.textEffects__imageFill.position' => [
					'attrName' => 'module.decoration.bodyFont.body.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'module.decoration.bodyFont.body.textEffects__imageFill.repeat' => [
					'attrName' => 'module.decoration.bodyFont.body.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'module.decoration.bodyFont.body.textEffects__imageFill.size' => [
					'attrName' => 'module.decoration.bodyFont.body.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'module.decoration.bodyFont.body.textEffects__imageFill.url' => [
					'attrName' => 'module.decoration.bodyFont.body.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'module.decoration.bodyFont.body.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'module.decoration.bodyFont.body.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'module.decoration.bodyFont.body.textEffects__imageFill.width' => [
					'attrName' => 'module.decoration.bodyFont.body.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'module.decoration.bodyFont.body.textEffects__strokeColor' => [
					'attrName' => 'module.decoration.bodyFont.body.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'module.decoration.bodyFont.body.textEffects__strokeWidth' => [
					'attrName' => 'module.decoration.bodyFont.body.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
				'module.decoration.bodyFont.link.textEffects__fillType' => [
					'attrName' => 'module.decoration.bodyFont.link.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'module.decoration.bodyFont.link.textEffects__gradient' => [
					'attrName' => 'module.decoration.bodyFont.link.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'module.decoration.bodyFont.link.textEffects__gradient.type' => [
					'attrName' => 'module.decoration.bodyFont.link.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'module.decoration.bodyFont.link.textEffects__gradient.direction' => [
					'attrName' => 'module.decoration.bodyFont.link.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'module.decoration.bodyFont.link.textEffects__gradient.directionRadial' => [
					'attrName' => 'module.decoration.bodyFont.link.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'module.decoration.bodyFont.link.textEffects__gradient.repeat' => [
					'attrName' => 'module.decoration.bodyFont.link.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'module.decoration.bodyFont.link.textEffects__gradient.length' => [
					'attrName' => 'module.decoration.bodyFont.link.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'module.decoration.bodyFont.link.textEffects__imageFill.blend' => [
					'attrName' => 'module.decoration.bodyFont.link.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'module.decoration.bodyFont.link.textEffects__imageFill.height' => [
					'attrName' => 'module.decoration.bodyFont.link.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'module.decoration.bodyFont.link.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'module.decoration.bodyFont.link.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'module.decoration.bodyFont.link.textEffects__imageFill.position' => [
					'attrName' => 'module.decoration.bodyFont.link.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'module.decoration.bodyFont.link.textEffects__imageFill.repeat' => [
					'attrName' => 'module.decoration.bodyFont.link.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'module.decoration.bodyFont.link.textEffects__imageFill.size' => [
					'attrName' => 'module.decoration.bodyFont.link.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'module.decoration.bodyFont.link.textEffects__imageFill.url' => [
					'attrName' => 'module.decoration.bodyFont.link.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'module.decoration.bodyFont.link.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'module.decoration.bodyFont.link.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'module.decoration.bodyFont.link.textEffects__imageFill.width' => [
					'attrName' => 'module.decoration.bodyFont.link.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'module.decoration.bodyFont.link.textEffects__strokeColor' => [
					'attrName' => 'module.decoration.bodyFont.link.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'module.decoration.bodyFont.link.textEffects__strokeWidth' => [
					'attrName' => 'module.decoration.bodyFont.link.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
				'module.decoration.bodyFont.ul.textEffects__fillType' => [
					'attrName' => 'module.decoration.bodyFont.ul.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'module.decoration.bodyFont.ul.textEffects__gradient' => [
					'attrName' => 'module.decoration.bodyFont.ul.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'module.decoration.bodyFont.ul.textEffects__gradient.type' => [
					'attrName' => 'module.decoration.bodyFont.ul.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'module.decoration.bodyFont.ul.textEffects__gradient.direction' => [
					'attrName' => 'module.decoration.bodyFont.ul.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'module.decoration.bodyFont.ul.textEffects__gradient.directionRadial' => [
					'attrName' => 'module.decoration.bodyFont.ul.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'module.decoration.bodyFont.ul.textEffects__gradient.repeat' => [
					'attrName' => 'module.decoration.bodyFont.ul.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'module.decoration.bodyFont.ul.textEffects__gradient.length' => [
					'attrName' => 'module.decoration.bodyFont.ul.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'module.decoration.bodyFont.ul.textEffects__imageFill.blend' => [
					'attrName' => 'module.decoration.bodyFont.ul.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'module.decoration.bodyFont.ul.textEffects__imageFill.height' => [
					'attrName' => 'module.decoration.bodyFont.ul.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'module.decoration.bodyFont.ul.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'module.decoration.bodyFont.ul.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'module.decoration.bodyFont.ul.textEffects__imageFill.position' => [
					'attrName' => 'module.decoration.bodyFont.ul.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'module.decoration.bodyFont.ul.textEffects__imageFill.repeat' => [
					'attrName' => 'module.decoration.bodyFont.ul.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'module.decoration.bodyFont.ul.textEffects__imageFill.size' => [
					'attrName' => 'module.decoration.bodyFont.ul.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'module.decoration.bodyFont.ul.textEffects__imageFill.url' => [
					'attrName' => 'module.decoration.bodyFont.ul.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'module.decoration.bodyFont.ul.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'module.decoration.bodyFont.ul.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'module.decoration.bodyFont.ul.textEffects__imageFill.width' => [
					'attrName' => 'module.decoration.bodyFont.ul.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'module.decoration.bodyFont.ul.textEffects__strokeColor' => [
					'attrName' => 'module.decoration.bodyFont.ul.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'module.decoration.bodyFont.ul.textEffects__strokeWidth' => [
					'attrName' => 'module.decoration.bodyFont.ul.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
				'module.decoration.bodyFont.ol.textEffects__fillType' => [
					'attrName' => 'module.decoration.bodyFont.ol.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'module.decoration.bodyFont.ol.textEffects__gradient' => [
					'attrName' => 'module.decoration.bodyFont.ol.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'module.decoration.bodyFont.ol.textEffects__gradient.type' => [
					'attrName' => 'module.decoration.bodyFont.ol.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'module.decoration.bodyFont.ol.textEffects__gradient.direction' => [
					'attrName' => 'module.decoration.bodyFont.ol.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'module.decoration.bodyFont.ol.textEffects__gradient.directionRadial' => [
					'attrName' => 'module.decoration.bodyFont.ol.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'module.decoration.bodyFont.ol.textEffects__gradient.repeat' => [
					'attrName' => 'module.decoration.bodyFont.ol.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'module.decoration.bodyFont.ol.textEffects__gradient.length' => [
					'attrName' => 'module.decoration.bodyFont.ol.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'module.decoration.bodyFont.ol.textEffects__imageFill.blend' => [
					'attrName' => 'module.decoration.bodyFont.ol.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'module.decoration.bodyFont.ol.textEffects__imageFill.height' => [
					'attrName' => 'module.decoration.bodyFont.ol.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'module.decoration.bodyFont.ol.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'module.decoration.bodyFont.ol.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'module.decoration.bodyFont.ol.textEffects__imageFill.position' => [
					'attrName' => 'module.decoration.bodyFont.ol.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'module.decoration.bodyFont.ol.textEffects__imageFill.repeat' => [
					'attrName' => 'module.decoration.bodyFont.ol.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'module.decoration.bodyFont.ol.textEffects__imageFill.size' => [
					'attrName' => 'module.decoration.bodyFont.ol.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'module.decoration.bodyFont.ol.textEffects__imageFill.url' => [
					'attrName' => 'module.decoration.bodyFont.ol.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'module.decoration.bodyFont.ol.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'module.decoration.bodyFont.ol.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'module.decoration.bodyFont.ol.textEffects__imageFill.width' => [
					'attrName' => 'module.decoration.bodyFont.ol.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'module.decoration.bodyFont.ol.textEffects__strokeColor' => [
					'attrName' => 'module.decoration.bodyFont.ol.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'module.decoration.bodyFont.ol.textEffects__strokeWidth' => [
					'attrName' => 'module.decoration.bodyFont.ol.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
				'module.decoration.bodyFont.quote.textEffects__fillType' => [
					'attrName' => 'module.decoration.bodyFont.quote.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'module.decoration.bodyFont.quote.textEffects__gradient' => [
					'attrName' => 'module.decoration.bodyFont.quote.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'module.decoration.bodyFont.quote.textEffects__gradient.type' => [
					'attrName' => 'module.decoration.bodyFont.quote.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'module.decoration.bodyFont.quote.textEffects__gradient.direction' => [
					'attrName' => 'module.decoration.bodyFont.quote.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'module.decoration.bodyFont.quote.textEffects__gradient.directionRadial' => [
					'attrName' => 'module.decoration.bodyFont.quote.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'module.decoration.bodyFont.quote.textEffects__gradient.repeat' => [
					'attrName' => 'module.decoration.bodyFont.quote.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'module.decoration.bodyFont.quote.textEffects__gradient.length' => [
					'attrName' => 'module.decoration.bodyFont.quote.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'module.decoration.bodyFont.quote.textEffects__imageFill.blend' => [
					'attrName' => 'module.decoration.bodyFont.quote.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'module.decoration.bodyFont.quote.textEffects__imageFill.height' => [
					'attrName' => 'module.decoration.bodyFont.quote.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'module.decoration.bodyFont.quote.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'module.decoration.bodyFont.quote.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'module.decoration.bodyFont.quote.textEffects__imageFill.position' => [
					'attrName' => 'module.decoration.bodyFont.quote.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'module.decoration.bodyFont.quote.textEffects__imageFill.repeat' => [
					'attrName' => 'module.decoration.bodyFont.quote.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'module.decoration.bodyFont.quote.textEffects__imageFill.size' => [
					'attrName' => 'module.decoration.bodyFont.quote.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'module.decoration.bodyFont.quote.textEffects__imageFill.url' => [
					'attrName' => 'module.decoration.bodyFont.quote.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'module.decoration.bodyFont.quote.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'module.decoration.bodyFont.quote.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'module.decoration.bodyFont.quote.textEffects__imageFill.width' => [
					'attrName' => 'module.decoration.bodyFont.quote.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'module.decoration.bodyFont.quote.textEffects__strokeColor' => [
					'attrName' => 'module.decoration.bodyFont.quote.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'module.decoration.bodyFont.quote.textEffects__strokeWidth' => [
					'attrName' => 'module.decoration.bodyFont.quote.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
				'module.decoration.bodyFont.dropCap.textShadow__horizontal' => [
					'attrName' => 'module.decoration.bodyFont.dropCap.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'horizontal',
				],
				'module.decoration.bodyFont.dropCap.textShadow__vertical' => [
					'attrName' => 'module.decoration.bodyFont.dropCap.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'vertical',
				],
				'module.decoration.bodyFont.dropCap.textShadow__blur' => [
					'attrName' => 'module.decoration.bodyFont.dropCap.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'blur',
				],
			]
		);
	}
}
