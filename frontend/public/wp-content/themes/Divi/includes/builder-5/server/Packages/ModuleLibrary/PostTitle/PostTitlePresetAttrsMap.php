<?php
/**
 * Module Library: PostTitle Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostTitle;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


/**
 * Class PostTitlePresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\PostTitle
 */
class PostTitlePresetAttrsMap {
	/**
	 * Get the preset attributes map for the PostTitle module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/post-title' !== $module_name ) {
			return $map;
		}

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
				'meta.decoration.font.textEffects__fillType' => [
					'attrName' => 'meta.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'meta.decoration.font.textEffects__gradient' => [
					'attrName' => 'meta.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'meta.decoration.font.textEffects__gradient.type' => [
					'attrName' => 'meta.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'meta.decoration.font.textEffects__gradient.direction' => [
					'attrName' => 'meta.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'meta.decoration.font.textEffects__gradient.directionRadial' => [
					'attrName' => 'meta.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'meta.decoration.font.textEffects__gradient.repeat' => [
					'attrName' => 'meta.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'meta.decoration.font.textEffects__gradient.length' => [
					'attrName' => 'meta.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'meta.decoration.font.textEffects__imageFill.blend' => [
					'attrName' => 'meta.decoration.font.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'meta.decoration.font.textEffects__imageFill.height' => [
					'attrName' => 'meta.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'meta.decoration.font.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'meta.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'meta.decoration.font.textEffects__imageFill.position' => [
					'attrName' => 'meta.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'meta.decoration.font.textEffects__imageFill.repeat' => [
					'attrName' => 'meta.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'meta.decoration.font.textEffects__imageFill.size' => [
					'attrName' => 'meta.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'meta.decoration.font.textEffects__imageFill.url' => [
					'attrName' => 'meta.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'meta.decoration.font.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'meta.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'meta.decoration.font.textEffects__imageFill.width' => [
					'attrName' => 'meta.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'meta.decoration.font.textEffects__strokeColor' => [
					'attrName' => 'meta.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'meta.decoration.font.textEffects__strokeWidth' => [
					'attrName' => 'meta.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
			]
		);
	}
}
