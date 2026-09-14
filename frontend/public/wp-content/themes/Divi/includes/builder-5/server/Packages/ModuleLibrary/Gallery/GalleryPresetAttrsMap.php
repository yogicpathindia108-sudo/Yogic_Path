<?php
/**
 * Module Library: Gallery Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Gallery;

use ET\Builder\Packages\Module\Options\Fit\FitPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Sizing\SizingPresetAttrsMap;
use ET\Builder\Packages\Module\Options\TextEffects\TextEffectsPresetAttrsMap;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Class GalleryPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\Gallery
 */
class GalleryPresetAttrsMap {
	/**
	 * Get the preset attributes map for the Gallery module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/gallery' !== $module_name ) {
			return $map;
		}

		$keys_to_remove = [
			'pagination.decoration.font.font__textAlign',
			'button.decoration.button.decoration.font.textEffects__fillType',
			'button.decoration.button.decoration.font.textEffects__gradient',
			'button.decoration.button.decoration.font.textEffects__gradient.type',
			'button.decoration.button.decoration.font.textEffects__gradient.direction',
			'button.decoration.button.decoration.font.textEffects__gradient.directionRadial',
			'button.decoration.button.decoration.font.textEffects__gradient.repeat',
			'button.decoration.button.decoration.font.textEffects__gradient.length',
			'button.decoration.button.decoration.font.textEffects__imageFill.blend',
			'button.decoration.button.decoration.font.textEffects__imageFill.height',
			'button.decoration.button.decoration.font.textEffects__imageFill.horizontalOffset',
			'button.decoration.button.decoration.font.textEffects__imageFill.position',
			'button.decoration.button.decoration.font.textEffects__imageFill.repeat',
			'button.decoration.button.decoration.font.textEffects__imageFill.size',
			'button.decoration.button.decoration.font.textEffects__imageFill.url',
			'button.decoration.button.decoration.font.textEffects__imageFill.verticalOffset',
			'button.decoration.button.decoration.font.textEffects__imageFill.width',
			'button.decoration.button.decoration.font.textEffects__strokeColor',
			'button.decoration.button.decoration.font.textEffects__strokeWidth',
		];

		foreach ( $keys_to_remove as $key ) {
			unset( $map[ $key ] );
		}

		return array_merge(
			$map,
			TextEffectsPresetAttrsMap::get_map( 'title.decoration.font' ),
			TextEffectsPresetAttrsMap::get_map( 'caption.decoration.font' ),
			TextEffectsPresetAttrsMap::get_map( 'pagination.decoration.font' ),
			[
				'module.decoration.scroll__gridMotion.enable' => [
					'attrName' => 'module.decoration.scroll',
					'preset'   => [
						'script',
					],
					'subName'  => 'gridMotion.enable',
				],
				'galleryGrid.decoration.layout__alignContent' => [
					'attrName' => 'galleryGrid.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'alignContent',
				],
				'galleryGrid.decoration.layout__alignItems' => [
					'attrName' => 'galleryGrid.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'alignItems',
				],
				'galleryGrid.decoration.layout__columnGap' => [
					'attrName' => 'galleryGrid.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'columnGap',
				],
				'galleryGrid.decoration.layout__display'   => [
					'attrName' => 'galleryGrid.decoration.layout',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'display',
				],
				'galleryGrid.decoration.layout__flexDirection' => [
					'attrName' => 'galleryGrid.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'flexDirection',
				],
				'galleryGrid.decoration.layout__flexWrap'  => [
					'attrName' => 'galleryGrid.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'flexWrap',
				],
				'galleryGrid.decoration.layout__justifyContent' => [
					'attrName' => 'galleryGrid.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'justifyContent',
				],
				'galleryGrid.decoration.layout__rowGap'    => [
					'attrName' => 'galleryGrid.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'rowGap',
				],
				'pagination.decoration.font__textAlign'    => [
					'attrName' => 'pagination.decoration.font',
					'preset'   => [
						'style',
					],
					'subName'  => 'textAlign',
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
			],
			SizingPresetAttrsMap::get_map( 'image.decoration.sizing' ),
			FitPresetAttrsMap::get_map( 'image.decoration.fit' )
		);
	}
}
