<?php
/**
 * Module Library:Search Module Preset Attributes Map
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Search;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


/**
 * Class SearchPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\Search
 */
class SearchPresetAttrsMap {
	/**
	 * Get the preset attributes map for the Search module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/search' !== $module_name ) {
			return $map;
		}

		unset( $map['field.decoration.spacing__margin'] );
		unset( $map['field.decoration.spacing__padding'] );

		unset( $map['button.decoration.font.font__textAlign'] );
		unset( $map['title.decoration.font.font__headingLevel'] );

		return array_merge(
			$map,
			[
				'searchPlaceholder.innerContent'           => [
					'attrName' => 'searchPlaceholder.innerContent',
					'preset'   => 'content',
				],
				'field.advanced.placeholder.font.font__color' => [
					'attrName' => 'field.advanced.placeholder.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'field.advanced.placeholder.font.font__size' => [
					'attrName' => 'field.advanced.placeholder.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'size',
				],
				'field.advanced.placeholder.font.font__letterSpacing' => [
					'attrName' => 'field.advanced.placeholder.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'letterSpacing',
				],
				'field.advanced.placeholder.font.font__lineHeight' => [
					'attrName' => 'field.advanced.placeholder.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'lineHeight',
				],
				'field.decoration.placeholderFont.font__family' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'family',
				],
				'field.decoration.placeholderFont.font__weight' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'weight',
				],
				'field.decoration.placeholderFont.font__weightFineTune' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'weightFineTune',
				],
				'field.decoration.placeholderFont.font__opticalSizing' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'opticalSizing',
				],
				'field.decoration.placeholderFont.font__style' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'style',
				],
				'field.decoration.placeholderFont.font__lineColor' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'lineColor',
				],
				'field.decoration.placeholderFont.font__lineThickness' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'lineThickness',
				],
				'field.decoration.placeholderFont.font__underlineOffset' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'underlineOffset',
				],
				'field.decoration.placeholderFont.font__lineStyle' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'lineStyle',
				],
				'field.decoration.placeholderFont.font__textAlign' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'textAlign',
				],
				'field.decoration.placeholderFont.font__color' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'field.decoration.placeholderFont.font__size' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'size',
				],
				'field.decoration.placeholderFont.font__letterSpacing' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'letterSpacing',
				],
				'field.decoration.placeholderFont.font__lineHeight' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'lineHeight',
				],
				'field.decoration.placeholderFont.font__capitalization' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'capitalization',
				],
				'field.decoration.placeholderFont.font__textWrap' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'textWrap',
				],
				'field.decoration.placeholderFont.font__writingMode' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'writingMode',
				],
				'field.decoration.placeholderFont.font__hyphens' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'hyphens',
				],
				'field.decoration.placeholderFont.font__columnCount' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'columnCount',
				],
				'field.decoration.placeholderFont.font__columnGap' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'columnGap',
				],
				'field.decoration.placeholderFont.textShadow__style' => [
					'attrName' => 'field.decoration.placeholderFont.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'style',
				],
				'field.decoration.placeholderFont.textShadow__horizontal' => [
					'attrName' => 'field.decoration.placeholderFont.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'horizontal',
				],
				'field.decoration.placeholderFont.textShadow__vertical' => [
					'attrName' => 'field.decoration.placeholderFont.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'vertical',
				],
				'field.decoration.placeholderFont.textShadow__blur' => [
					'attrName' => 'field.decoration.placeholderFont.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'blur',
				],
				'field.decoration.placeholderFont.textShadow__color' => [
					'attrName' => 'field.decoration.placeholderFont.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'field.advanced.focus.background__color'   => [
					'attrName' => 'field.advanced.focus.background',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'field.advanced.focus.font.font__color'    => [
					'attrName' => 'field.advanced.focus.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'field.decoration.font.font__headingLevel' => [
					'attrName' => 'field.decoration.font.font',
					'preset'   => [ 'html' ],
					'subName'  => 'headingLevel',
				],
				'field.decoration.font.textEffects__fillType' => [
					'attrName' => 'field.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'field.decoration.font.textEffects__gradient' => [
					'attrName' => 'field.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'field.decoration.font.textEffects__gradient.type' => [
					'attrName' => 'field.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'field.decoration.font.textEffects__gradient.direction' => [
					'attrName' => 'field.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'field.decoration.font.textEffects__gradient.directionRadial' => [
					'attrName' => 'field.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'field.decoration.font.textEffects__gradient.repeat' => [
					'attrName' => 'field.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'field.decoration.font.textEffects__gradient.length' => [
					'attrName' => 'field.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'field.decoration.font.textEffects__imageFill.blend' => [
					'attrName' => 'field.decoration.font.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'field.decoration.font.textEffects__imageFill.height' => [
					'attrName' => 'field.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'field.decoration.font.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'field.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'field.decoration.font.textEffects__imageFill.position' => [
					'attrName' => 'field.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'field.decoration.font.textEffects__imageFill.repeat' => [
					'attrName' => 'field.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'field.decoration.font.textEffects__imageFill.size' => [
					'attrName' => 'field.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'field.decoration.font.textEffects__imageFill.url' => [
					'attrName' => 'field.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'field.decoration.font.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'field.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'field.decoration.font.textEffects__imageFill.width' => [
					'attrName' => 'field.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'field.decoration.font.textEffects__strokeColor' => [
					'attrName' => 'field.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'field.decoration.font.textEffects__strokeWidth' => [
					'attrName' => 'field.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
				'field.decoration.placeholderFont.textEffects__fillType' => [
					'attrName' => 'field.decoration.placeholderFont.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'field.decoration.placeholderFont.textEffects__gradient' => [
					'attrName' => 'field.decoration.placeholderFont.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'field.decoration.placeholderFont.textEffects__gradient.type' => [
					'attrName' => 'field.decoration.placeholderFont.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'field.decoration.placeholderFont.textEffects__gradient.direction' => [
					'attrName' => 'field.decoration.placeholderFont.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'field.decoration.placeholderFont.textEffects__gradient.directionRadial' => [
					'attrName' => 'field.decoration.placeholderFont.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'field.decoration.placeholderFont.textEffects__gradient.repeat' => [
					'attrName' => 'field.decoration.placeholderFont.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'field.decoration.placeholderFont.textEffects__gradient.length' => [
					'attrName' => 'field.decoration.placeholderFont.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'field.decoration.placeholderFont.textEffects__imageFill.blend' => [
					'attrName' => 'field.decoration.placeholderFont.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'field.decoration.placeholderFont.textEffects__imageFill.height' => [
					'attrName' => 'field.decoration.placeholderFont.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'field.decoration.placeholderFont.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'field.decoration.placeholderFont.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'field.decoration.placeholderFont.textEffects__imageFill.position' => [
					'attrName' => 'field.decoration.placeholderFont.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'field.decoration.placeholderFont.textEffects__imageFill.repeat' => [
					'attrName' => 'field.decoration.placeholderFont.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'field.decoration.placeholderFont.textEffects__imageFill.size' => [
					'attrName' => 'field.decoration.placeholderFont.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'field.decoration.placeholderFont.textEffects__imageFill.url' => [
					'attrName' => 'field.decoration.placeholderFont.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'field.decoration.placeholderFont.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'field.decoration.placeholderFont.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'field.decoration.placeholderFont.textEffects__imageFill.width' => [
					'attrName' => 'field.decoration.placeholderFont.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'field.decoration.placeholderFont.textEffects__strokeColor' => [
					'attrName' => 'field.decoration.placeholderFont.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'field.decoration.placeholderFont.textEffects__strokeWidth' => [
					'attrName' => 'field.decoration.placeholderFont.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
				'field.decoration.placeholderFont.textEffects__strokePosition' => [
					'attrName' => 'field.decoration.placeholderFont.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokePosition',
				],
				'button.decoration.font.textEffects__fillType' => [
					'attrName' => 'button.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'button.decoration.font.textEffects__gradient' => [
					'attrName' => 'button.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'button.decoration.font.textEffects__gradient.type' => [
					'attrName' => 'button.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'button.decoration.font.textEffects__gradient.direction' => [
					'attrName' => 'button.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'button.decoration.font.textEffects__gradient.directionRadial' => [
					'attrName' => 'button.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'button.decoration.font.textEffects__gradient.repeat' => [
					'attrName' => 'button.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'button.decoration.font.textEffects__gradient.length' => [
					'attrName' => 'button.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'button.decoration.font.textEffects__imageFill.blend' => [
					'attrName' => 'button.decoration.font.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'button.decoration.font.textEffects__imageFill.height' => [
					'attrName' => 'button.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'button.decoration.font.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'button.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'button.decoration.font.textEffects__imageFill.position' => [
					'attrName' => 'button.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'button.decoration.font.textEffects__imageFill.repeat' => [
					'attrName' => 'button.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'button.decoration.font.textEffects__imageFill.size' => [
					'attrName' => 'button.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'button.decoration.font.textEffects__imageFill.url' => [
					'attrName' => 'button.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'button.decoration.font.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'button.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'button.decoration.font.textEffects__imageFill.width' => [
					'attrName' => 'button.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'button.decoration.font.textEffects__strokeColor' => [
					'attrName' => 'button.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'button.decoration.font.textEffects__strokeWidth' => [
					'attrName' => 'button.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
			]
		);
	}
}
