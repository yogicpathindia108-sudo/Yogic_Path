<?php
/**
 * Module Library: Tabs Module Preset Attributes Map
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Tabs;

use ET\Builder\Packages\Module\Options\Loop\LoopPresetAttrsMap;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


/**
 * Class TabsPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\Tabs
 */
class TabsPresetAttrsMap {
	/**
	 * Get the preset attributes map for the Tabs module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/tabs' !== $module_name ) {
			return $map;
		}

		$loop_preset_attrs = LoopPresetAttrsMap::get_map( 'module.advanced.loop' );

		$static_attrs = [
			'module.advanced.link__url'                    => [
				'attrName' => 'module.advanced.link',
				'preset'   => 'content',
				'subName'  => 'url',
			],
			'module.advanced.link__target'                 => [
				'attrName' => 'module.advanced.link',
				'preset'   => 'content',
				'subName'  => 'target',
			],
			'content.decoration.background__color'         => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'content.decoration.background__gradient'      => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient',
			],
			'content.decoration.background__gradient.enabled' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.enabled',
			],
			'content.decoration.background__gradient.type' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.type',
			],
			'content.decoration.background__gradient.direction' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.direction',
			],
			'content.decoration.background__gradient.directionRadial' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.directionRadial',
			],
			'content.decoration.background__gradient.repeat' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.repeat',
			],
			'content.decoration.background__gradient.length' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.length',
			],
			'content.decoration.background__gradient.overlaysImage' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.overlaysImage',
			],
			'content.decoration.background__image.url'     => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'image.url',
			],
			'content.decoration.background__image.parallax.enabled' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style', 'html', 'script' ],
				'subName'  => 'image.parallax.enabled',
			],
			'content.decoration.background__image.parallax.method' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'image.parallax.method',
			],
			'content.decoration.background__image.size'    => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.size',
			],
			'content.decoration.background__image.width'   => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.width',
			],
			'content.decoration.background__image.height'  => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.height',
			],
			'content.decoration.background__image.position' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.position',
			],
			'content.decoration.background__image.horizontalOffset' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.horizontalOffset',
			],
			'content.decoration.background__image.verticalOffset' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.verticalOffset',
			],
			'content.decoration.background__image.repeat'  => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.repeat',
			],
			'content.decoration.background__image.blend'   => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'image.blend',
			],
			'content.decoration.background__video.mp4'     => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.mp4',
			],
			'content.decoration.background__video.webm'    => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.webm',
			],
			'content.decoration.background__video.width'   => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.width',
			],
			'content.decoration.background__video.height'  => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.height',
			],
			'content.decoration.background__video.allowPlayerPause' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.allowPlayerPause',
			],
			'content.decoration.background__video.pauseOutsideViewport' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.pauseOutsideViewport',
			],
			'content.decoration.background__pattern.style' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'pattern.style',
			],
			'content.decoration.background__pattern.enabled' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'pattern.enabled',
			],
			'content.decoration.background__pattern.color' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'pattern.color',
			],
			'content.decoration.background__pattern.transform' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'pattern.transform',
			],
			'content.decoration.background__pattern.size'  => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.size',
			],
			'content.decoration.background__pattern.width' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.width',
			],
			'content.decoration.background__pattern.height' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.height',
			],
			'content.decoration.background__pattern.repeatOrigin' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.repeatOrigin',
			],
			'content.decoration.background__pattern.horizontalOffset' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.horizontalOffset',
			],
			'content.decoration.background__pattern.verticalOffset' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.verticalOffset',
			],
			'content.decoration.background__pattern.repeat' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.repeat',
			],
			'content.decoration.background__pattern.blend' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.blend',
			],
			'content.decoration.background__mask.style'    => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.style',
			],
			'content.decoration.background__mask.enabled'  => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.enabled',
			],
			'content.decoration.background__mask.color'    => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.color',
			],
			'content.decoration.background__mask.transform' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.transform',
			],
			'content.decoration.background__mask.aspectRatio' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.aspectRatio',
			],
			'content.decoration.background__mask.size'     => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.size',
			],
			'content.decoration.background__mask.width'    => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.width',
			],
			'content.decoration.background__mask.height'   => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.height',
			],
			'content.decoration.background__mask.position' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.position',
			],
			'content.decoration.background__mask.horizontalOffset' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.horizontalOffset',
			],
			'content.decoration.background__mask.verticalOffset' => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.verticalOffset',
			],
			'content.decoration.background__mask.blend'    => [
				'attrName' => 'content.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.blend',
			],
			'module.meta.adminLabel'                       => [
				'attrName' => 'module.meta.adminLabel',
				'preset'   => 'meta',
			],
			'module.meta.meta.forceVisible'                => [
				'attrName' => 'module.meta.meta.forceVisible',
				'preset'   => 'meta',
			],
			'module.meta.meta.tocListHeading'              => [
				'attrName' => 'module.meta.meta.tocListHeading',
				'preset'   => 'meta',
			],
			'content.decoration.bodyFont.body.font__family' => [
				'attrName' => 'content.decoration.bodyFont.body.font',
				'preset'   => [ 'style' ],
				'subName'  => 'family',
			],
			'content.decoration.bodyFont.body.font__weight' => [
				'attrName' => 'content.decoration.bodyFont.body.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weight',
			],
			'content.decoration.bodyFont.body.font__style' => [
				'attrName' => 'content.decoration.bodyFont.body.font',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'content.decoration.bodyFont.body.font__lineColor' => [
				'attrName' => 'content.decoration.bodyFont.body.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineColor',
			],
			'content.decoration.bodyFont.body.font__lineStyle' => [
				'attrName' => 'content.decoration.bodyFont.body.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineStyle',
			],
			'content.decoration.bodyFont.body.font__textAlign' => [
				'attrName' => 'content.decoration.bodyFont.body.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textAlign',
			],
			'content.decoration.bodyFont.body.font__color' => [
				'attrName' => 'content.decoration.bodyFont.body.font',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'content.decoration.bodyFont.body.font__size'  => [
				'attrName' => 'content.decoration.bodyFont.body.font',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			'content.decoration.bodyFont.body.font__letterSpacing' => [
				'attrName' => 'content.decoration.bodyFont.body.font',
				'preset'   => [ 'style' ],
				'subName'  => 'letterSpacing',
			],
			'content.decoration.bodyFont.body.font__lineHeight' => [
				'attrName' => 'content.decoration.bodyFont.body.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineHeight',
			],
			'content.decoration.bodyFont.body.textShadow__style' => [
				'attrName' => 'content.decoration.bodyFont.body.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'content.decoration.bodyFont.body.textShadow__horizontal' => [
				'attrName' => 'content.decoration.bodyFont.body.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'horizontal',
			],
			'content.decoration.bodyFont.body.textShadow__vertical' => [
				'attrName' => 'content.decoration.bodyFont.body.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'vertical',
			],
			'content.decoration.bodyFont.body.textShadow__blur' => [
				'attrName' => 'content.decoration.bodyFont.body.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'blur',
			],
			'content.decoration.bodyFont.body.textShadow__color' => [
				'attrName' => 'content.decoration.bodyFont.body.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'content.decoration.bodyFont.link.font__family' => [
				'attrName' => 'content.decoration.bodyFont.link.font',
				'preset'   => [ 'style' ],
				'subName'  => 'family',
			],
			'content.decoration.bodyFont.link.font__weight' => [
				'attrName' => 'content.decoration.bodyFont.link.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weight',
			],
			'content.decoration.bodyFont.link.font__style' => [
				'attrName' => 'content.decoration.bodyFont.link.font',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'content.decoration.bodyFont.link.font__lineColor' => [
				'attrName' => 'content.decoration.bodyFont.link.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineColor',
			],
			'content.decoration.bodyFont.link.font__lineStyle' => [
				'attrName' => 'content.decoration.bodyFont.link.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineStyle',
			],
			'content.decoration.bodyFont.link.font__textAlign' => [
				'attrName' => 'content.decoration.bodyFont.link.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textAlign',
			],
			'content.decoration.bodyFont.link.font__color' => [
				'attrName' => 'content.decoration.bodyFont.link.font',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'content.decoration.bodyFont.link.font__size'  => [
				'attrName' => 'content.decoration.bodyFont.link.font',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			'content.decoration.bodyFont.link.font__letterSpacing' => [
				'attrName' => 'content.decoration.bodyFont.link.font',
				'preset'   => [ 'style' ],
				'subName'  => 'letterSpacing',
			],
			'content.decoration.bodyFont.link.font__lineHeight' => [
				'attrName' => 'content.decoration.bodyFont.link.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineHeight',
			],
			'content.decoration.bodyFont.link.textShadow__style' => [
				'attrName' => 'content.decoration.bodyFont.link.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'content.decoration.bodyFont.link.textShadow__horizontal' => [
				'attrName' => 'content.decoration.bodyFont.link.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'horizontal',
			],
			'content.decoration.bodyFont.link.textShadow__vertical' => [
				'attrName' => 'content.decoration.bodyFont.link.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'vertical',
			],
			'content.decoration.bodyFont.link.textShadow__blur' => [
				'attrName' => 'content.decoration.bodyFont.link.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'blur',
			],
			'content.decoration.bodyFont.link.textShadow__color' => [
				'attrName' => 'content.decoration.bodyFont.link.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'content.decoration.bodyFont.ul.font__family'  => [
				'attrName' => 'content.decoration.bodyFont.ul.font',
				'preset'   => [ 'style' ],
				'subName'  => 'family',
			],
			'content.decoration.bodyFont.ul.font__weight'  => [
				'attrName' => 'content.decoration.bodyFont.ul.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weight',
			],
			'content.decoration.bodyFont.ul.font__style'   => [
				'attrName' => 'content.decoration.bodyFont.ul.font',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'content.decoration.bodyFont.ul.font__lineColor' => [
				'attrName' => 'content.decoration.bodyFont.ul.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineColor',
			],
			'content.decoration.bodyFont.ul.font__lineStyle' => [
				'attrName' => 'content.decoration.bodyFont.ul.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineStyle',
			],
			'content.decoration.bodyFont.ul.font__textAlign' => [
				'attrName' => 'content.decoration.bodyFont.ul.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textAlign',
			],
			'content.decoration.bodyFont.ul.font__color'   => [
				'attrName' => 'content.decoration.bodyFont.ul.font',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'content.decoration.bodyFont.ul.font__size'    => [
				'attrName' => 'content.decoration.bodyFont.ul.font',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			'content.decoration.bodyFont.ul.font__letterSpacing' => [
				'attrName' => 'content.decoration.bodyFont.ul.font',
				'preset'   => [ 'style' ],
				'subName'  => 'letterSpacing',
			],
			'content.decoration.bodyFont.ul.font__lineHeight' => [
				'attrName' => 'content.decoration.bodyFont.ul.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineHeight',
			],
			'content.decoration.bodyFont.ul.textShadow__style' => [
				'attrName' => 'content.decoration.bodyFont.ul.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'content.decoration.bodyFont.ul.textShadow__horizontal' => [
				'attrName' => 'content.decoration.bodyFont.ul.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'horizontal',
			],
			'content.decoration.bodyFont.ul.textShadow__vertical' => [
				'attrName' => 'content.decoration.bodyFont.ul.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'vertical',
			],
			'content.decoration.bodyFont.ul.textShadow__blur' => [
				'attrName' => 'content.decoration.bodyFont.ul.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'blur',
			],
			'content.decoration.bodyFont.ul.textShadow__color' => [
				'attrName' => 'content.decoration.bodyFont.ul.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'content.decoration.bodyFont.ul.list__type'    => [
				'attrName' => 'content.decoration.bodyFont.ul.list',
				'preset'   => [ 'style' ],
				'subName'  => 'type',
			],
			'content.decoration.bodyFont.ul.list__position' => [
				'attrName' => 'content.decoration.bodyFont.ul.list',
				'preset'   => [ 'style' ],
				'subName'  => 'position',
			],
			'content.decoration.bodyFont.ul.list__itemIndent' => [
				'attrName' => 'content.decoration.bodyFont.ul.list',
				'preset'   => [ 'style' ],
				'subName'  => 'itemIndent',
			],
			'content.decoration.bodyFont.ol.font__family'  => [
				'attrName' => 'content.decoration.bodyFont.ol.font',
				'preset'   => [ 'style' ],
				'subName'  => 'family',
			],
			'content.decoration.bodyFont.ol.font__weight'  => [
				'attrName' => 'content.decoration.bodyFont.ol.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weight',
			],
			'content.decoration.bodyFont.ol.font__style'   => [
				'attrName' => 'content.decoration.bodyFont.ol.font',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'content.decoration.bodyFont.ol.font__lineColor' => [
				'attrName' => 'content.decoration.bodyFont.ol.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineColor',
			],
			'content.decoration.bodyFont.ol.font__lineStyle' => [
				'attrName' => 'content.decoration.bodyFont.ol.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineStyle',
			],
			'content.decoration.bodyFont.ol.font__textAlign' => [
				'attrName' => 'content.decoration.bodyFont.ol.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textAlign',
			],
			'content.decoration.bodyFont.ol.font__color'   => [
				'attrName' => 'content.decoration.bodyFont.ol.font',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'content.decoration.bodyFont.ol.font__size'    => [
				'attrName' => 'content.decoration.bodyFont.ol.font',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			'content.decoration.bodyFont.ol.font__letterSpacing' => [
				'attrName' => 'content.decoration.bodyFont.ol.font',
				'preset'   => [ 'style' ],
				'subName'  => 'letterSpacing',
			],
			'content.decoration.bodyFont.ol.font__lineHeight' => [
				'attrName' => 'content.decoration.bodyFont.ol.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineHeight',
			],
			'content.decoration.bodyFont.ol.textShadow__style' => [
				'attrName' => 'content.decoration.bodyFont.ol.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'content.decoration.bodyFont.ol.textShadow__horizontal' => [
				'attrName' => 'content.decoration.bodyFont.ol.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'horizontal',
			],
			'content.decoration.bodyFont.ol.textShadow__vertical' => [
				'attrName' => 'content.decoration.bodyFont.ol.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'vertical',
			],
			'content.decoration.bodyFont.ol.textShadow__blur' => [
				'attrName' => 'content.decoration.bodyFont.ol.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'blur',
			],
			'content.decoration.bodyFont.ol.textShadow__color' => [
				'attrName' => 'content.decoration.bodyFont.ol.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'content.decoration.bodyFont.ol.list__type'    => [
				'attrName' => 'content.decoration.bodyFont.ol.list',
				'preset'   => [ 'style' ],
				'subName'  => 'type',
			],
			'content.decoration.bodyFont.ol.list__position' => [
				'attrName' => 'content.decoration.bodyFont.ol.list',
				'preset'   => [ 'style' ],
				'subName'  => 'position',
			],
			'content.decoration.bodyFont.ol.list__itemIndent' => [
				'attrName' => 'content.decoration.bodyFont.ol.list',
				'preset'   => [ 'style' ],
				'subName'  => 'itemIndent',
			],
			'content.decoration.bodyFont.quote.font__family' => [
				'attrName' => 'content.decoration.bodyFont.quote.font',
				'preset'   => [ 'style' ],
				'subName'  => 'family',
			],
			'content.decoration.bodyFont.quote.font__weight' => [
				'attrName' => 'content.decoration.bodyFont.quote.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weight',
			],
			'content.decoration.bodyFont.quote.font__style' => [
				'attrName' => 'content.decoration.bodyFont.quote.font',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'content.decoration.bodyFont.quote.font__lineColor' => [
				'attrName' => 'content.decoration.bodyFont.quote.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineColor',
			],
			'content.decoration.bodyFont.quote.font__lineStyle' => [
				'attrName' => 'content.decoration.bodyFont.quote.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineStyle',
			],
			'content.decoration.bodyFont.quote.font__textAlign' => [
				'attrName' => 'content.decoration.bodyFont.quote.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textAlign',
			],
			'content.decoration.bodyFont.quote.font__color' => [
				'attrName' => 'content.decoration.bodyFont.quote.font',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'content.decoration.bodyFont.quote.font__size' => [
				'attrName' => 'content.decoration.bodyFont.quote.font',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			'content.decoration.bodyFont.quote.font__letterSpacing' => [
				'attrName' => 'content.decoration.bodyFont.quote.font',
				'preset'   => [ 'style' ],
				'subName'  => 'letterSpacing',
			],
			'content.decoration.bodyFont.quote.font__lineHeight' => [
				'attrName' => 'content.decoration.bodyFont.quote.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineHeight',
			],
			'content.decoration.bodyFont.quote.textShadow__style' => [
				'attrName' => 'content.decoration.bodyFont.quote.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'content.decoration.bodyFont.quote.textShadow__horizontal' => [
				'attrName' => 'content.decoration.bodyFont.quote.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'horizontal',
			],
			'content.decoration.bodyFont.quote.textShadow__vertical' => [
				'attrName' => 'content.decoration.bodyFont.quote.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'vertical',
			],
			'content.decoration.bodyFont.quote.textShadow__blur' => [
				'attrName' => 'content.decoration.bodyFont.quote.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'blur',
			],
			'content.decoration.bodyFont.quote.textShadow__color' => [
				'attrName' => 'content.decoration.bodyFont.quote.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'content.decoration.bodyFont.quote.border__styles.left.width' => [
				'attrName' => 'content.decoration.bodyFont.quote.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.width',
			],
			'content.decoration.bodyFont.quote.border__styles.left.color' => [
				'attrName' => 'content.decoration.bodyFont.quote.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.color',
			],
			'activeTab.decoration.background__color'       => [
				'attrName' => 'activeTab.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'activeTab.decoration.font.font__color'        => [
				'attrName' => 'activeTab.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'tab.decoration.font.font__family'             => [
				'attrName' => 'tab.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'family',
			],
			'tab.decoration.font.font__weight'             => [
				'attrName' => 'tab.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weight',
			],
			'tab.decoration.font.font__style'              => [
				'attrName' => 'tab.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'tab.decoration.font.font__lineColor'          => [
				'attrName' => 'tab.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineColor',
			],
			'tab.decoration.font.font__lineStyle'          => [
				'attrName' => 'tab.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineStyle',
			],
			'tab.decoration.font.font__color'              => [
				'attrName' => 'tab.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'tab.decoration.font.font__size'               => [
				'attrName' => 'tab.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			'tab.decoration.font.font__letterSpacing'      => [
				'attrName' => 'tab.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'letterSpacing',
			],
			'tab.decoration.font.font__lineHeight'         => [
				'attrName' => 'tab.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineHeight',
			],
			'tab.decoration.font.textShadow__style'        => [
				'attrName' => 'tab.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'tab.decoration.font.textShadow__horizontal'   => [
				'attrName' => 'tab.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'horizontal',
			],
			'tab.decoration.font.textShadow__vertical'     => [
				'attrName' => 'tab.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'vertical',
			],
			'tab.decoration.font.textShadow__blur'         => [
				'attrName' => 'tab.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'blur',
			],
			'tab.decoration.font.textShadow__color'        => [
				'attrName' => 'tab.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'content.decoration.bodyFont.body.font__weightFineTune' => [
				'attrName' => 'content.decoration.bodyFont.body.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weightFineTune',
			],
			'content.decoration.bodyFont.body.font__opticalSizing' => [
				'attrName' => 'content.decoration.bodyFont.body.font',
				'preset'   => [ 'style' ],
				'subName'  => 'opticalSizing',
			],
			'content.decoration.bodyFont.body.font__capitalization' => [
				'attrName' => 'content.decoration.bodyFont.body.font',
				'preset'   => [ 'style' ],
				'subName'  => 'capitalization',
			],
			'content.decoration.bodyFont.body.font__lineThickness' => [
				'attrName' => 'content.decoration.bodyFont.body.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineThickness',
			],
			'content.decoration.bodyFont.body.font__underlineOffset' => [
				'attrName' => 'content.decoration.bodyFont.body.font',
				'preset'   => [ 'style' ],
				'subName'  => 'underlineOffset',
			],
			'content.decoration.bodyFont.body.font__textWrap' => [
				'attrName' => 'content.decoration.bodyFont.body.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textWrap',
			],
			'content.decoration.bodyFont.body.font__writingMode' => [
				'attrName' => 'content.decoration.bodyFont.body.font',
				'preset'   => [ 'style' ],
				'subName'  => 'writingMode',
			],
			'content.decoration.bodyFont.body.font__hyphens' => [
				'attrName' => 'content.decoration.bodyFont.body.font',
				'preset'   => [ 'style' ],
				'subName'  => 'hyphens',
			],
			'content.decoration.bodyFont.body.font__columnCount' => [
				'attrName' => 'content.decoration.bodyFont.body.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnCount',
			],
			'content.decoration.bodyFont.body.font__columnGap' => [
				'attrName' => 'content.decoration.bodyFont.body.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnGap',
			],
			'content.decoration.bodyFont.body.list__paragraphSpacing' => [
				'attrName' => 'content.decoration.bodyFont.body.list',
				'preset'   => [ 'style' ],
				'subName'  => 'paragraphSpacing',
			],
			'content.decoration.bodyFont.link.font__weightFineTune' => [
				'attrName' => 'content.decoration.bodyFont.link.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weightFineTune',
			],
			'content.decoration.bodyFont.link.font__opticalSizing' => [
				'attrName' => 'content.decoration.bodyFont.link.font',
				'preset'   => [ 'style' ],
				'subName'  => 'opticalSizing',
			],
			'content.decoration.bodyFont.link.font__capitalization' => [
				'attrName' => 'content.decoration.bodyFont.link.font',
				'preset'   => [ 'style' ],
				'subName'  => 'capitalization',
			],
			'content.decoration.bodyFont.link.font__lineThickness' => [
				'attrName' => 'content.decoration.bodyFont.link.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineThickness',
			],
			'content.decoration.bodyFont.link.font__underlineOffset' => [
				'attrName' => 'content.decoration.bodyFont.link.font',
				'preset'   => [ 'style' ],
				'subName'  => 'underlineOffset',
			],
			'content.decoration.bodyFont.link.font__textWrap' => [
				'attrName' => 'content.decoration.bodyFont.link.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textWrap',
			],
			'content.decoration.bodyFont.link.font__writingMode' => [
				'attrName' => 'content.decoration.bodyFont.link.font',
				'preset'   => [ 'style' ],
				'subName'  => 'writingMode',
			],
			'content.decoration.bodyFont.link.font__hyphens' => [
				'attrName' => 'content.decoration.bodyFont.link.font',
				'preset'   => [ 'style' ],
				'subName'  => 'hyphens',
			],
			'content.decoration.bodyFont.link.font__columnCount' => [
				'attrName' => 'content.decoration.bodyFont.link.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnCount',
			],
			'content.decoration.bodyFont.link.font__columnGap' => [
				'attrName' => 'content.decoration.bodyFont.link.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnGap',
			],
			'content.decoration.bodyFont.ol.font__weightFineTune' => [
				'attrName' => 'content.decoration.bodyFont.ol.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weightFineTune',
			],
			'content.decoration.bodyFont.ol.font__opticalSizing' => [
				'attrName' => 'content.decoration.bodyFont.ol.font',
				'preset'   => [ 'style' ],
				'subName'  => 'opticalSizing',
			],
			'content.decoration.bodyFont.ol.font__capitalization' => [
				'attrName' => 'content.decoration.bodyFont.ol.font',
				'preset'   => [ 'style' ],
				'subName'  => 'capitalization',
			],
			'content.decoration.bodyFont.ol.font__lineThickness' => [
				'attrName' => 'content.decoration.bodyFont.ol.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineThickness',
			],
			'content.decoration.bodyFont.ol.font__underlineOffset' => [
				'attrName' => 'content.decoration.bodyFont.ol.font',
				'preset'   => [ 'style' ],
				'subName'  => 'underlineOffset',
			],
			'content.decoration.bodyFont.ol.font__textWrap' => [
				'attrName' => 'content.decoration.bodyFont.ol.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textWrap',
			],
			'content.decoration.bodyFont.ol.font__writingMode' => [
				'attrName' => 'content.decoration.bodyFont.ol.font',
				'preset'   => [ 'style' ],
				'subName'  => 'writingMode',
			],
			'content.decoration.bodyFont.ol.font__hyphens' => [
				'attrName' => 'content.decoration.bodyFont.ol.font',
				'preset'   => [ 'style' ],
				'subName'  => 'hyphens',
			],
			'content.decoration.bodyFont.ol.font__columnCount' => [
				'attrName' => 'content.decoration.bodyFont.ol.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnCount',
			],
			'content.decoration.bodyFont.ol.font__columnGap' => [
				'attrName' => 'content.decoration.bodyFont.ol.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnGap',
			],
			'content.decoration.bodyFont.quote.font__weightFineTune' => [
				'attrName' => 'content.decoration.bodyFont.quote.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weightFineTune',
			],
			'content.decoration.bodyFont.quote.font__opticalSizing' => [
				'attrName' => 'content.decoration.bodyFont.quote.font',
				'preset'   => [ 'style' ],
				'subName'  => 'opticalSizing',
			],
			'content.decoration.bodyFont.quote.font__capitalization' => [
				'attrName' => 'content.decoration.bodyFont.quote.font',
				'preset'   => [ 'style' ],
				'subName'  => 'capitalization',
			],
			'content.decoration.bodyFont.quote.font__lineThickness' => [
				'attrName' => 'content.decoration.bodyFont.quote.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineThickness',
			],
			'content.decoration.bodyFont.quote.font__underlineOffset' => [
				'attrName' => 'content.decoration.bodyFont.quote.font',
				'preset'   => [ 'style' ],
				'subName'  => 'underlineOffset',
			],
			'content.decoration.bodyFont.quote.font__textWrap' => [
				'attrName' => 'content.decoration.bodyFont.quote.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textWrap',
			],
			'content.decoration.bodyFont.quote.font__writingMode' => [
				'attrName' => 'content.decoration.bodyFont.quote.font',
				'preset'   => [ 'style' ],
				'subName'  => 'writingMode',
			],
			'content.decoration.bodyFont.quote.font__hyphens' => [
				'attrName' => 'content.decoration.bodyFont.quote.font',
				'preset'   => [ 'style' ],
				'subName'  => 'hyphens',
			],
			'content.decoration.bodyFont.quote.font__columnCount' => [
				'attrName' => 'content.decoration.bodyFont.quote.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnCount',
			],
			'content.decoration.bodyFont.quote.font__columnGap' => [
				'attrName' => 'content.decoration.bodyFont.quote.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnGap',
			],
			'content.decoration.bodyFont.ul.font__weightFineTune' => [
				'attrName' => 'content.decoration.bodyFont.ul.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weightFineTune',
			],
			'content.decoration.bodyFont.ul.font__opticalSizing' => [
				'attrName' => 'content.decoration.bodyFont.ul.font',
				'preset'   => [ 'style' ],
				'subName'  => 'opticalSizing',
			],
			'content.decoration.bodyFont.ul.font__capitalization' => [
				'attrName' => 'content.decoration.bodyFont.ul.font',
				'preset'   => [ 'style' ],
				'subName'  => 'capitalization',
			],
			'content.decoration.bodyFont.ul.font__lineThickness' => [
				'attrName' => 'content.decoration.bodyFont.ul.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineThickness',
			],
			'content.decoration.bodyFont.ul.font__underlineOffset' => [
				'attrName' => 'content.decoration.bodyFont.ul.font',
				'preset'   => [ 'style' ],
				'subName'  => 'underlineOffset',
			],
			'content.decoration.bodyFont.ul.font__textWrap' => [
				'attrName' => 'content.decoration.bodyFont.ul.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textWrap',
			],
			'content.decoration.bodyFont.ul.font__writingMode' => [
				'attrName' => 'content.decoration.bodyFont.ul.font',
				'preset'   => [ 'style' ],
				'subName'  => 'writingMode',
			],
			'content.decoration.bodyFont.ul.font__hyphens' => [
				'attrName' => 'content.decoration.bodyFont.ul.font',
				'preset'   => [ 'style' ],
				'subName'  => 'hyphens',
			],
			'content.decoration.bodyFont.ul.font__columnCount' => [
				'attrName' => 'content.decoration.bodyFont.ul.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnCount',
			],
			'content.decoration.bodyFont.ul.font__columnGap' => [
				'attrName' => 'content.decoration.bodyFont.ul.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnGap',
			],
			'tab.decoration.font.font__weightFineTune'     => [
				'attrName' => 'tab.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weightFineTune',
			],
			'tab.decoration.font.font__opticalSizing'      => [
				'attrName' => 'tab.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'opticalSizing',
			],
			'tab.decoration.font.font__capitalization'     => [
				'attrName' => 'tab.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'capitalization',
			],
			'tab.decoration.font.font__lineThickness'      => [
				'attrName' => 'tab.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineThickness',
			],
			'tab.decoration.font.font__underlineOffset'    => [
				'attrName' => 'tab.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'underlineOffset',
			],
			'tab.decoration.font.font__textWrap'           => [
				'attrName' => 'tab.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textWrap',
			],
			'tab.decoration.font.font__writingMode'        => [
				'attrName' => 'tab.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'writingMode',
			],
			'tab.decoration.font.font__hyphens'            => [
				'attrName' => 'tab.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'hyphens',
			],
			'tab.decoration.font.font__columnCount'        => [
				'attrName' => 'tab.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnCount',
			],
			'tab.decoration.font.font__columnGap'          => [
				'attrName' => 'tab.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnGap',
			],
			'content.decoration.bodyFont.ol.list__listSpacing' => [
				'attrName' => 'content.decoration.bodyFont.ol.list',
				'preset'   => [ 'style' ],
				'subName'  => 'listSpacing',
			],
			'content.decoration.bodyFont.ul.list__listSpacing' => [
				'attrName' => 'content.decoration.bodyFont.ul.list',
				'preset'   => [ 'style' ],
				'subName'  => 'listSpacing',
			],
			'activeTab.decoration.font.textEffects__strokePosition' => [
				'attrName' => 'activeTab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokePosition',
			],
			'content.decoration.bodyFont.body.textEffects__strokePosition' => [
				'attrName' => 'content.decoration.bodyFont.body.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokePosition',
			],
			'content.decoration.bodyFont.link.textEffects__strokePosition' => [
				'attrName' => 'content.decoration.bodyFont.link.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokePosition',
			],
			'content.decoration.bodyFont.ol.textEffects__strokePosition' => [
				'attrName' => 'content.decoration.bodyFont.ol.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokePosition',
			],
			'content.decoration.bodyFont.quote.textEffects__strokePosition' => [
				'attrName' => 'content.decoration.bodyFont.quote.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokePosition',
			],
			'content.decoration.bodyFont.ul.textEffects__strokePosition' => [
				'attrName' => 'content.decoration.bodyFont.ul.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokePosition',
			],
			'tab.decoration.font.textEffects__strokePosition' => [
				'attrName' => 'tab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokePosition',
			],
			'content.decoration.bodyFont.dropCap.font__dropCapLineSize' => [
				'attrName' => 'content.decoration.bodyFont.dropCap.font',
				'preset'   => [ 'style' ],
				'subName'  => 'dropCapLineSize',
			],
			'content.decoration.bodyFont.dropCap.font__dropCapSpacing' => [
				'attrName' => 'content.decoration.bodyFont.dropCap.font',
				'preset'   => [ 'style' ],
				'subName'  => 'dropCapSpacing',
			],
			'content.decoration.bodyFont.dropCap.font__family' => [
				'attrName' => 'content.decoration.bodyFont.dropCap.font',
				'preset'   => [ 'style' ],
				'subName'  => 'family',
			],
			'content.decoration.bodyFont.dropCap.font__weight' => [
				'attrName' => 'content.decoration.bodyFont.dropCap.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weight',
			],
			'content.decoration.bodyFont.dropCap.font__weightFineTune' => [
				'attrName' => 'content.decoration.bodyFont.dropCap.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weightFineTune',
			],
			'content.decoration.bodyFont.dropCap.font__opticalSizing' => [
				'attrName' => 'content.decoration.bodyFont.dropCap.font',
				'preset'   => [ 'style' ],
				'subName'  => 'opticalSizing',
			],
			'content.decoration.bodyFont.dropCap.font__color' => [
				'attrName' => 'content.decoration.bodyFont.dropCap.font',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'content.decoration.bodyFont.dropCap.font__capitalization' => [
				'attrName' => 'content.decoration.bodyFont.dropCap.font',
				'preset'   => [ 'style' ],
				'subName'  => 'capitalization',
			],
			'content.decoration.bodyFont.dropCap.font__style' => [
				'attrName' => 'content.decoration.bodyFont.dropCap.font',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'content.decoration.bodyFont.dropCap.font__lineColor' => [
				'attrName' => 'content.decoration.bodyFont.dropCap.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineColor',
			],
			'content.decoration.bodyFont.dropCap.font__lineThickness' => [
				'attrName' => 'content.decoration.bodyFont.dropCap.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineThickness',
			],
			'content.decoration.bodyFont.dropCap.font__underlineOffset' => [
				'attrName' => 'content.decoration.bodyFont.dropCap.font',
				'preset'   => [ 'style' ],
				'subName'  => 'underlineOffset',
			],
			'content.decoration.bodyFont.dropCap.font__lineStyle' => [
				'attrName' => 'content.decoration.bodyFont.dropCap.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineStyle',
			],
			'content.decoration.bodyFont.dropCap.textShadow__style' => [
				'attrName' => 'content.decoration.bodyFont.dropCap.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'content.decoration.bodyFont.dropCap.textShadow__horizontal' => [
				'attrName' => 'content.decoration.bodyFont.dropCap.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'horizontal',
			],
			'content.decoration.bodyFont.dropCap.textShadow__vertical' => [
				'attrName' => 'content.decoration.bodyFont.dropCap.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'vertical',
			],
			'content.decoration.bodyFont.dropCap.textShadow__blur' => [
				'attrName' => 'content.decoration.bodyFont.dropCap.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'blur',
			],
			'content.decoration.bodyFont.dropCap.textShadow__color' => [
				'attrName' => 'content.decoration.bodyFont.dropCap.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],

			'module.decoration.sizing__width'              => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'width',
			],
			'module.decoration.sizing__maxWidth'           => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'maxWidth',
			],
			'module.decoration.sizing__flexType'           => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'html' ],
				'subName'  => 'flexType',
			],
			'module.decoration.sizing__alignSelf'          => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'alignSelf',
			],
			'module.decoration.sizing__flexGrow'           => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'flexGrow',
			],
			'module.decoration.sizing__flexShrink'         => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'flexShrink',
			],
			'module.decoration.sizing__gridAlignSelf'      => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridAlignSelf',
			],
			'module.decoration.sizing__gridColumnSpan'     => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnSpan',
			],
			'module.decoration.sizing__gridColumnStart'    => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnStart',
			],
			'module.decoration.sizing__gridJustifySelf'    => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridJustifySelf',
			],
			'module.decoration.sizing__gridRowSpan'        => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowSpan',
			],
			'module.decoration.sizing__gridRowStart'       => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowStart',
			],
			'module.decoration.sizing__gridColumnEnd'      => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnEnd',
			],
			'module.decoration.sizing__gridRowEnd'         => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowEnd',
			],
			'module.decoration.sizing__size'               => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			'module.decoration.sizing__alignment'          => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'alignment',
			],
			'module.decoration.sizing__minHeight'          => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'minHeight',
			],
			'module.decoration.sizing__height'             => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'height',
			],
			'module.decoration.sizing__maxHeight'          => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'maxHeight',
			],
			'module.decoration.sizing__aspectRatio'        => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [
					'style',
				],
				'subName'  => 'aspectRatio',
			],
			'module.decoration.spacing__margin'            => [
				'attrName' => 'module.decoration.spacing',
				'preset'   => [ 'style' ],
				'subName'  => 'margin',
			],
			'module.decoration.spacing__padding'           => [
				'attrName' => 'module.decoration.spacing',
				'preset'   => [ 'style' ],
				'subName'  => 'padding',
			],
			'module.decoration.border__radius'             => [
				'attrName' => 'module.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'radius',
			],
			'module.decoration.border__styles'             => [
				'attrName' => 'module.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles',
			],
			'module.decoration.border__styles.all.width'   => [
				'attrName' => 'module.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.width',
			],
			'module.decoration.border__styles.top.width'   => [
				'attrName' => 'module.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.width',
			],
			'module.decoration.border__styles.right.width' => [
				'attrName' => 'module.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.width',
			],
			'module.decoration.border__styles.bottom.width' => [
				'attrName' => 'module.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.width',
			],
			'module.decoration.border__styles.left.width'  => [
				'attrName' => 'module.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.width',
			],
			'module.decoration.border__styles.all.color'   => [
				'attrName' => 'module.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.color',
			],
			'module.decoration.border__styles.top.color'   => [
				'attrName' => 'module.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.color',
			],
			'module.decoration.border__styles.right.color' => [
				'attrName' => 'module.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.color',
			],
			'module.decoration.border__styles.bottom.color' => [
				'attrName' => 'module.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.color',
			],
			'module.decoration.border__styles.left.color'  => [
				'attrName' => 'module.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.color',
			],
			'module.decoration.border__styles.all.style'   => [
				'attrName' => 'module.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.style',
			],
			'module.decoration.border__styles.top.style'   => [
				'attrName' => 'module.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.style',
			],
			'module.decoration.border__styles.right.style' => [
				'attrName' => 'module.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.style',
			],
			'module.decoration.border__styles.bottom.style' => [
				'attrName' => 'module.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.style',
			],
			'module.decoration.border__styles.left.style'  => [
				'attrName' => 'module.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.style',
			],
			'module.decoration.boxShadow__style'           => [
				'attrName' => 'module.decoration.boxShadow',
				'preset'   => [ 'html', 'style' ],
				'subName'  => 'style',
			],
			'module.decoration.boxShadow__horizontal'      => [
				'attrName' => 'module.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'horizontal',
			],
			'module.decoration.boxShadow__vertical'        => [
				'attrName' => 'module.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'vertical',
			],
			'module.decoration.boxShadow__blur'            => [
				'attrName' => 'module.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'blur',
			],
			'module.decoration.boxShadow__spread'          => [
				'attrName' => 'module.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'spread',
			],
			'module.decoration.boxShadow__color'           => [
				'attrName' => 'module.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'color',
			],
			'module.decoration.boxShadow__position'        => [
				'attrName' => 'module.decoration.boxShadow',
				'preset'   => [ 'html', 'style' ],
				'subName'  => 'position',
			],
			'module.decoration.filters__hueRotate'         => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'hueRotate',
			],
			'module.decoration.filters__saturate'          => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'saturate',
			],
			'module.decoration.filters__brightness'        => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'brightness',
			],
			'module.decoration.filters__contrast'          => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'contrast',
			],
			'module.decoration.filters__invert'            => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'invert',
			],
			'module.decoration.filters__sepia'             => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'sepia',
			],
			'module.decoration.filters__opacity'           => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'opacity',
			],
			'module.decoration.filters__blur'              => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'blur',
			],
			'module.decoration.filters__backdropBlur'  => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'backdropBlur',
			],
			'module.decoration.filters__backdropInvert' => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'backdropInvert',
			],
			'module.decoration.filters__backdropSepia' => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'backdropSepia',
			],
			'module.decoration.filters__blendMode'         => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'blendMode',
			],
			'module.decoration.transform__scale'           => [
				'attrName' => 'module.decoration.transform',
				'preset'   => [ 'style' ],
				'subName'  => 'scale',
			],
			'module.decoration.transform__translate'       => [
				'attrName' => 'module.decoration.transform',
				'preset'   => [ 'style' ],
				'subName'  => 'translate',
			],
			'module.decoration.transform__rotate'          => [
				'attrName' => 'module.decoration.transform',
				'preset'   => [ 'style' ],
				'subName'  => 'rotate',
			],
			'module.decoration.transform__skew'            => [
				'attrName' => 'module.decoration.transform',
				'preset'   => [ 'style' ],
				'subName'  => 'skew',
			],
			'module.decoration.transform__origin'          => [
				'attrName' => 'module.decoration.transform',
				'preset'   => [ 'style' ],
				'subName'  => 'origin',
			],
			'module.decoration.animation__style'           => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [ 'script' ],
				'subName'  => 'style',
			],
			'module.decoration.animation__direction'       => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [ 'script' ],
				'subName'  => 'direction',
			],
			'module.decoration.animation__duration'        => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [ 'script' ],
				'subName'  => 'duration',
			],
			'module.decoration.animation__delay'           => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [ 'script' ],
				'subName'  => 'delay',
			],
			'module.decoration.animation__intensity.slide' => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [ 'script' ],
				'subName'  => 'intensity.slide',
			],
			'module.decoration.animation__intensity.zoom'  => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [ 'script' ],
				'subName'  => 'intensity.zoom',
			],
			'module.decoration.animation__intensity.flip'  => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [ 'script' ],
				'subName'  => 'intensity.flip',
			],
			'module.decoration.animation__intensity.fold'  => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [ 'script' ],
				'subName'  => 'intensity.fold',
			],
			'module.decoration.animation__intensity.roll'  => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [ 'script' ],
				'subName'  => 'intensity.roll',
			],
			'module.decoration.animation__startingOpacity' => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [ 'script' ],
				'subName'  => 'startingOpacity',
			],
			'module.decoration.animation__speedCurve'      => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [ 'script' ],
				'subName'  => 'speedCurve',
			],
			'module.decoration.animation__repeat'          => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [ 'script' ],
				'subName'  => 'repeat',
			],
			'module.advanced.htmlAttributes__id'           => [
				'attrName' => 'module.advanced.htmlAttributes',
				'preset'   => 'content',
				'subName'  => 'id',
			],
			'module.advanced.elements.structure'           => [
				'attrName' => 'module.advanced.elements.structure',
				'preset'   => 'content',
			],
			'module.advanced.htmlAttributes__class'        => [
				'attrName' => 'module.advanced.htmlAttributes',
				'preset'   => [ 'html' ],
				'subName'  => 'class',
			],
			'content.decoration.bodyFont.body.textEffects__fillType' => [
				'attrName' => 'content.decoration.bodyFont.body.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'content.decoration.bodyFont.body.textEffects__gradient' => [
				'attrName' => 'content.decoration.bodyFont.body.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient',
			],
			'content.decoration.bodyFont.body.textEffects__gradient.type' => [
				'attrName' => 'content.decoration.bodyFont.body.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.type',
			],
			'content.decoration.bodyFont.body.textEffects__gradient.direction' => [
				'attrName' => 'content.decoration.bodyFont.body.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.direction',
			],
			'content.decoration.bodyFont.body.textEffects__gradient.directionRadial' => [
				'attrName' => 'content.decoration.bodyFont.body.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.directionRadial',
			],
			'content.decoration.bodyFont.body.textEffects__gradient.repeat' => [
				'attrName' => 'content.decoration.bodyFont.body.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.repeat',
			],
			'content.decoration.bodyFont.body.textEffects__gradient.length' => [
				'attrName' => 'content.decoration.bodyFont.body.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.length',
			],
			'content.decoration.bodyFont.body.textEffects__imageFill.blend' => [
				'attrName' => 'content.decoration.bodyFont.body.textEffects',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'imageFill.blend',
			],
			'content.decoration.bodyFont.body.textEffects__imageFill.height' => [
				'attrName' => 'content.decoration.bodyFont.body.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.height',
			],
			'content.decoration.bodyFont.body.textEffects__imageFill.horizontalOffset' => [
				'attrName' => 'content.decoration.bodyFont.body.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.horizontalOffset',
			],
			'content.decoration.bodyFont.body.textEffects__imageFill.position' => [
				'attrName' => 'content.decoration.bodyFont.body.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.position',
			],
			'content.decoration.bodyFont.body.textEffects__imageFill.repeat' => [
				'attrName' => 'content.decoration.bodyFont.body.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.repeat',
			],
			'content.decoration.bodyFont.body.textEffects__imageFill.size' => [
				'attrName' => 'content.decoration.bodyFont.body.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.size',
			],
			'content.decoration.bodyFont.body.textEffects__imageFill.url' => [
				'attrName' => 'content.decoration.bodyFont.body.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.url',
			],
			'content.decoration.bodyFont.body.textEffects__imageFill.verticalOffset' => [
				'attrName' => 'content.decoration.bodyFont.body.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.verticalOffset',
			],
			'content.decoration.bodyFont.body.textEffects__imageFill.width' => [
				'attrName' => 'content.decoration.bodyFont.body.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.width',
			],
			'content.decoration.bodyFont.body.textEffects__strokeColor' => [
				'attrName' => 'content.decoration.bodyFont.body.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeColor',
			],
			'content.decoration.bodyFont.body.textEffects__strokeWidth' => [
				'attrName' => 'content.decoration.bodyFont.body.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeWidth',
			],
			'content.decoration.bodyFont.link.textEffects__fillType' => [
				'attrName' => 'content.decoration.bodyFont.link.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'content.decoration.bodyFont.link.textEffects__gradient' => [
				'attrName' => 'content.decoration.bodyFont.link.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient',
			],
			'content.decoration.bodyFont.link.textEffects__gradient.type' => [
				'attrName' => 'content.decoration.bodyFont.link.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.type',
			],
			'content.decoration.bodyFont.link.textEffects__gradient.direction' => [
				'attrName' => 'content.decoration.bodyFont.link.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.direction',
			],
			'content.decoration.bodyFont.link.textEffects__gradient.directionRadial' => [
				'attrName' => 'content.decoration.bodyFont.link.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.directionRadial',
			],
			'content.decoration.bodyFont.link.textEffects__gradient.repeat' => [
				'attrName' => 'content.decoration.bodyFont.link.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.repeat',
			],
			'content.decoration.bodyFont.link.textEffects__gradient.length' => [
				'attrName' => 'content.decoration.bodyFont.link.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.length',
			],
			'content.decoration.bodyFont.link.textEffects__imageFill.blend' => [
				'attrName' => 'content.decoration.bodyFont.link.textEffects',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'imageFill.blend',
			],
			'content.decoration.bodyFont.link.textEffects__imageFill.height' => [
				'attrName' => 'content.decoration.bodyFont.link.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.height',
			],
			'content.decoration.bodyFont.link.textEffects__imageFill.horizontalOffset' => [
				'attrName' => 'content.decoration.bodyFont.link.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.horizontalOffset',
			],
			'content.decoration.bodyFont.link.textEffects__imageFill.position' => [
				'attrName' => 'content.decoration.bodyFont.link.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.position',
			],
			'content.decoration.bodyFont.link.textEffects__imageFill.repeat' => [
				'attrName' => 'content.decoration.bodyFont.link.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.repeat',
			],
			'content.decoration.bodyFont.link.textEffects__imageFill.size' => [
				'attrName' => 'content.decoration.bodyFont.link.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.size',
			],
			'content.decoration.bodyFont.link.textEffects__imageFill.url' => [
				'attrName' => 'content.decoration.bodyFont.link.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.url',
			],
			'content.decoration.bodyFont.link.textEffects__imageFill.verticalOffset' => [
				'attrName' => 'content.decoration.bodyFont.link.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.verticalOffset',
			],
			'content.decoration.bodyFont.link.textEffects__imageFill.width' => [
				'attrName' => 'content.decoration.bodyFont.link.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.width',
			],
			'content.decoration.bodyFont.link.textEffects__strokeColor' => [
				'attrName' => 'content.decoration.bodyFont.link.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeColor',
			],
			'content.decoration.bodyFont.link.textEffects__strokeWidth' => [
				'attrName' => 'content.decoration.bodyFont.link.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeWidth',
			],
			'content.decoration.bodyFont.ul.textEffects__fillType' => [
				'attrName' => 'content.decoration.bodyFont.ul.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'content.decoration.bodyFont.ul.textEffects__gradient' => [
				'attrName' => 'content.decoration.bodyFont.ul.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient',
			],
			'content.decoration.bodyFont.ul.textEffects__gradient.type' => [
				'attrName' => 'content.decoration.bodyFont.ul.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.type',
			],
			'content.decoration.bodyFont.ul.textEffects__gradient.direction' => [
				'attrName' => 'content.decoration.bodyFont.ul.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.direction',
			],
			'content.decoration.bodyFont.ul.textEffects__gradient.directionRadial' => [
				'attrName' => 'content.decoration.bodyFont.ul.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.directionRadial',
			],
			'content.decoration.bodyFont.ul.textEffects__gradient.repeat' => [
				'attrName' => 'content.decoration.bodyFont.ul.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.repeat',
			],
			'content.decoration.bodyFont.ul.textEffects__gradient.length' => [
				'attrName' => 'content.decoration.bodyFont.ul.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.length',
			],
			'content.decoration.bodyFont.ul.textEffects__imageFill.blend' => [
				'attrName' => 'content.decoration.bodyFont.ul.textEffects',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'imageFill.blend',
			],
			'content.decoration.bodyFont.ul.textEffects__imageFill.height' => [
				'attrName' => 'content.decoration.bodyFont.ul.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.height',
			],
			'content.decoration.bodyFont.ul.textEffects__imageFill.horizontalOffset' => [
				'attrName' => 'content.decoration.bodyFont.ul.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.horizontalOffset',
			],
			'content.decoration.bodyFont.ul.textEffects__imageFill.position' => [
				'attrName' => 'content.decoration.bodyFont.ul.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.position',
			],
			'content.decoration.bodyFont.ul.textEffects__imageFill.repeat' => [
				'attrName' => 'content.decoration.bodyFont.ul.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.repeat',
			],
			'content.decoration.bodyFont.ul.textEffects__imageFill.size' => [
				'attrName' => 'content.decoration.bodyFont.ul.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.size',
			],
			'content.decoration.bodyFont.ul.textEffects__imageFill.url' => [
				'attrName' => 'content.decoration.bodyFont.ul.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.url',
			],
			'content.decoration.bodyFont.ul.textEffects__imageFill.verticalOffset' => [
				'attrName' => 'content.decoration.bodyFont.ul.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.verticalOffset',
			],
			'content.decoration.bodyFont.ul.textEffects__imageFill.width' => [
				'attrName' => 'content.decoration.bodyFont.ul.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.width',
			],
			'content.decoration.bodyFont.ul.textEffects__strokeColor' => [
				'attrName' => 'content.decoration.bodyFont.ul.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeColor',
			],
			'content.decoration.bodyFont.ul.textEffects__strokeWidth' => [
				'attrName' => 'content.decoration.bodyFont.ul.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeWidth',
			],
			'content.decoration.bodyFont.ol.textEffects__fillType' => [
				'attrName' => 'content.decoration.bodyFont.ol.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'content.decoration.bodyFont.ol.textEffects__gradient' => [
				'attrName' => 'content.decoration.bodyFont.ol.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient',
			],
			'content.decoration.bodyFont.ol.textEffects__gradient.type' => [
				'attrName' => 'content.decoration.bodyFont.ol.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.type',
			],
			'content.decoration.bodyFont.ol.textEffects__gradient.direction' => [
				'attrName' => 'content.decoration.bodyFont.ol.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.direction',
			],
			'content.decoration.bodyFont.ol.textEffects__gradient.directionRadial' => [
				'attrName' => 'content.decoration.bodyFont.ol.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.directionRadial',
			],
			'content.decoration.bodyFont.ol.textEffects__gradient.repeat' => [
				'attrName' => 'content.decoration.bodyFont.ol.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.repeat',
			],
			'content.decoration.bodyFont.ol.textEffects__gradient.length' => [
				'attrName' => 'content.decoration.bodyFont.ol.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.length',
			],
			'content.decoration.bodyFont.ol.textEffects__imageFill.blend' => [
				'attrName' => 'content.decoration.bodyFont.ol.textEffects',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'imageFill.blend',
			],
			'content.decoration.bodyFont.ol.textEffects__imageFill.height' => [
				'attrName' => 'content.decoration.bodyFont.ol.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.height',
			],
			'content.decoration.bodyFont.ol.textEffects__imageFill.horizontalOffset' => [
				'attrName' => 'content.decoration.bodyFont.ol.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.horizontalOffset',
			],
			'content.decoration.bodyFont.ol.textEffects__imageFill.position' => [
				'attrName' => 'content.decoration.bodyFont.ol.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.position',
			],
			'content.decoration.bodyFont.ol.textEffects__imageFill.repeat' => [
				'attrName' => 'content.decoration.bodyFont.ol.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.repeat',
			],
			'content.decoration.bodyFont.ol.textEffects__imageFill.size' => [
				'attrName' => 'content.decoration.bodyFont.ol.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.size',
			],
			'content.decoration.bodyFont.ol.textEffects__imageFill.url' => [
				'attrName' => 'content.decoration.bodyFont.ol.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.url',
			],
			'content.decoration.bodyFont.ol.textEffects__imageFill.verticalOffset' => [
				'attrName' => 'content.decoration.bodyFont.ol.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.verticalOffset',
			],
			'content.decoration.bodyFont.ol.textEffects__imageFill.width' => [
				'attrName' => 'content.decoration.bodyFont.ol.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.width',
			],
			'content.decoration.bodyFont.ol.textEffects__strokeColor' => [
				'attrName' => 'content.decoration.bodyFont.ol.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeColor',
			],
			'content.decoration.bodyFont.ol.textEffects__strokeWidth' => [
				'attrName' => 'content.decoration.bodyFont.ol.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeWidth',
			],
			'content.decoration.bodyFont.quote.textEffects__fillType' => [
				'attrName' => 'content.decoration.bodyFont.quote.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'content.decoration.bodyFont.quote.textEffects__gradient' => [
				'attrName' => 'content.decoration.bodyFont.quote.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient',
			],
			'content.decoration.bodyFont.quote.textEffects__gradient.type' => [
				'attrName' => 'content.decoration.bodyFont.quote.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.type',
			],
			'content.decoration.bodyFont.quote.textEffects__gradient.direction' => [
				'attrName' => 'content.decoration.bodyFont.quote.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.direction',
			],
			'content.decoration.bodyFont.quote.textEffects__gradient.directionRadial' => [
				'attrName' => 'content.decoration.bodyFont.quote.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.directionRadial',
			],
			'content.decoration.bodyFont.quote.textEffects__gradient.repeat' => [
				'attrName' => 'content.decoration.bodyFont.quote.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.repeat',
			],
			'content.decoration.bodyFont.quote.textEffects__gradient.length' => [
				'attrName' => 'content.decoration.bodyFont.quote.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.length',
			],
			'content.decoration.bodyFont.quote.textEffects__imageFill.blend' => [
				'attrName' => 'content.decoration.bodyFont.quote.textEffects',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'imageFill.blend',
			],
			'content.decoration.bodyFont.quote.textEffects__imageFill.height' => [
				'attrName' => 'content.decoration.bodyFont.quote.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.height',
			],
			'content.decoration.bodyFont.quote.textEffects__imageFill.horizontalOffset' => [
				'attrName' => 'content.decoration.bodyFont.quote.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.horizontalOffset',
			],
			'content.decoration.bodyFont.quote.textEffects__imageFill.position' => [
				'attrName' => 'content.decoration.bodyFont.quote.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.position',
			],
			'content.decoration.bodyFont.quote.textEffects__imageFill.repeat' => [
				'attrName' => 'content.decoration.bodyFont.quote.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.repeat',
			],
			'content.decoration.bodyFont.quote.textEffects__imageFill.size' => [
				'attrName' => 'content.decoration.bodyFont.quote.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.size',
			],
			'content.decoration.bodyFont.quote.textEffects__imageFill.url' => [
				'attrName' => 'content.decoration.bodyFont.quote.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.url',
			],
			'content.decoration.bodyFont.quote.textEffects__imageFill.verticalOffset' => [
				'attrName' => 'content.decoration.bodyFont.quote.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.verticalOffset',
			],
			'content.decoration.bodyFont.quote.textEffects__imageFill.width' => [
				'attrName' => 'content.decoration.bodyFont.quote.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.width',
			],
			'content.decoration.bodyFont.quote.textEffects__strokeColor' => [
				'attrName' => 'content.decoration.bodyFont.quote.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeColor',
			],
			'content.decoration.bodyFont.quote.textEffects__strokeWidth' => [
				'attrName' => 'content.decoration.bodyFont.quote.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeWidth',
			],
			'tab.decoration.font.textEffects__fillType'    => [
				'attrName' => 'tab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'tab.decoration.font.textEffects__gradient'    => [
				'attrName' => 'tab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient',
			],
			'tab.decoration.font.textEffects__gradient.type' => [
				'attrName' => 'tab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.type',
			],
			'tab.decoration.font.textEffects__gradient.direction' => [
				'attrName' => 'tab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.direction',
			],
			'tab.decoration.font.textEffects__gradient.directionRadial' => [
				'attrName' => 'tab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.directionRadial',
			],
			'tab.decoration.font.textEffects__gradient.repeat' => [
				'attrName' => 'tab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.repeat',
			],
			'tab.decoration.font.textEffects__gradient.length' => [
				'attrName' => 'tab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.length',
			],
			'tab.decoration.font.textEffects__imageFill.blend' => [
				'attrName' => 'tab.decoration.font.textEffects',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'imageFill.blend',
			],
			'tab.decoration.font.textEffects__imageFill.height' => [
				'attrName' => 'tab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.height',
			],
			'tab.decoration.font.textEffects__imageFill.horizontalOffset' => [
				'attrName' => 'tab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.horizontalOffset',
			],
			'tab.decoration.font.textEffects__imageFill.position' => [
				'attrName' => 'tab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.position',
			],
			'tab.decoration.font.textEffects__imageFill.repeat' => [
				'attrName' => 'tab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.repeat',
			],
			'tab.decoration.font.textEffects__imageFill.size' => [
				'attrName' => 'tab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.size',
			],
			'tab.decoration.font.textEffects__imageFill.url' => [
				'attrName' => 'tab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.url',
			],
			'tab.decoration.font.textEffects__imageFill.verticalOffset' => [
				'attrName' => 'tab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.verticalOffset',
			],
			'tab.decoration.font.textEffects__imageFill.width' => [
				'attrName' => 'tab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.width',
			],
			'tab.decoration.font.textEffects__strokeColor' => [
				'attrName' => 'tab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeColor',
			],
			'tab.decoration.font.textEffects__strokeWidth' => [
				'attrName' => 'tab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeWidth',
			],
			'activeTab.decoration.font.textEffects__fillType' => [
				'attrName' => 'activeTab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'activeTab.decoration.font.textEffects__gradient' => [
				'attrName' => 'activeTab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient',
			],
			'activeTab.decoration.font.textEffects__gradient.type' => [
				'attrName' => 'activeTab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.type',
			],
			'activeTab.decoration.font.textEffects__gradient.direction' => [
				'attrName' => 'activeTab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.direction',
			],
			'activeTab.decoration.font.textEffects__gradient.directionRadial' => [
				'attrName' => 'activeTab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.directionRadial',
			],
			'activeTab.decoration.font.textEffects__gradient.repeat' => [
				'attrName' => 'activeTab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.repeat',
			],
			'activeTab.decoration.font.textEffects__gradient.length' => [
				'attrName' => 'activeTab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.length',
			],
			'activeTab.decoration.font.textEffects__imageFill.blend' => [
				'attrName' => 'activeTab.decoration.font.textEffects',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'imageFill.blend',
			],
			'activeTab.decoration.font.textEffects__imageFill.height' => [
				'attrName' => 'activeTab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.height',
			],
			'activeTab.decoration.font.textEffects__imageFill.horizontalOffset' => [
				'attrName' => 'activeTab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.horizontalOffset',
			],
			'activeTab.decoration.font.textEffects__imageFill.position' => [
				'attrName' => 'activeTab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.position',
			],
			'activeTab.decoration.font.textEffects__imageFill.repeat' => [
				'attrName' => 'activeTab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.repeat',
			],
			'activeTab.decoration.font.textEffects__imageFill.size' => [
				'attrName' => 'activeTab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.size',
			],
			'activeTab.decoration.font.textEffects__imageFill.url' => [
				'attrName' => 'activeTab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.url',
			],
			'activeTab.decoration.font.textEffects__imageFill.verticalOffset' => [
				'attrName' => 'activeTab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.verticalOffset',
			],
			'activeTab.decoration.font.textEffects__imageFill.width' => [
				'attrName' => 'activeTab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.width',
			],
			'activeTab.decoration.font.textEffects__strokeColor' => [
				'attrName' => 'activeTab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeColor',
			],
			'activeTab.decoration.font.textEffects__strokeWidth' => [
				'attrName' => 'activeTab.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeWidth',
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
			'css__before'                                  => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'before',
			],
			'css__mainElement'                             => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'mainElement',
			],
			'css__after'                                   => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'after',
			],
			'css__freeForm'                                => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'freeForm',
			],
			'css__tabsControls'                            => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'tabsControls',
			],
			'inactiveTab.decoration.background'            => [
				'attrName' => 'inactiveTab.decoration.background',
				'preset'   => [ 'style' ],
			],
			'css__tab'                                     => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'tab',
			],
			'css__activeTab'                               => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'activeTab',
			],
			'css__tabsContent'                             => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'tabsContent',
			],
			'module.decoration.conditions'                 => [
				'attrName' => 'module.decoration.conditions',
				'preset'   => [ 'html' ],
			],
			'module.decoration.disabledOn'                 => [
				'attrName' => 'module.decoration.disabledOn',
				'preset'   => [ 'style', 'html' ],
			],
			'module.decoration.interactions'               => [
				'attrName' => 'module.decoration.interactions',
				'preset'   => [ 'script' ],
			],
			'module.decoration.overflow__x'                => [
				'attrName' => 'module.decoration.overflow',
				'preset'   => [ 'style' ],
				'subName'  => 'x',
			],
			'module.decoration.overflow__y'                => [
				'attrName' => 'module.decoration.overflow',
				'preset'   => [ 'style' ],
				'subName'  => 'y',
			],
			'module.decoration.transition__duration'       => [
				'attrName' => 'module.decoration.transition',
				'preset'   => [ 'style' ],
				'subName'  => 'duration',
			],
			'module.decoration.transition__delay'          => [
				'attrName' => 'module.decoration.transition',
				'preset'   => [ 'style' ],
				'subName'  => 'delay',
			],
			'module.decoration.transition__speedCurve'     => [
				'attrName' => 'module.decoration.transition',
				'preset'   => [ 'style' ],
				'subName'  => 'speedCurve',
			],
			'module.decoration.position__mode'             => [
				'attrName' => 'module.decoration.position',
				'preset'   => [ 'style' ],
				'subName'  => 'mode',
			],
			'module.decoration.position__origin.relative'  => [
				'attrName' => 'module.decoration.position',
				'preset'   => [ 'style' ],
				'subName'  => 'origin.relative',
			],
			'module.decoration.position__origin.absolute'  => [
				'attrName' => 'module.decoration.position',
				'preset'   => [ 'style' ],
				'subName'  => 'origin.absolute',
			],
			'module.decoration.position__origin.fixed'     => [
				'attrName' => 'module.decoration.position',
				'preset'   => [ 'style' ],
				'subName'  => 'origin.fixed',
			],
			'module.decoration.position__offset.vertical'  => [
				'attrName' => 'module.decoration.position',
				'preset'   => [ 'style' ],
				'subName'  => 'offset.vertical',
			],
			'module.decoration.position__offset.horizontal' => [
				'attrName' => 'module.decoration.position',
				'preset'   => [ 'style' ],
				'subName'  => 'offset.horizontal',
			],
			'module.decoration.zIndex'                     => [
				'attrName' => 'module.decoration.zIndex',
				'preset'   => [ 'style' ],
			],
			'module.decoration.scroll__verticalMotion.enable' => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [ 'script' ],
				'subName'  => 'verticalMotion.enable',
			],
			'module.decoration.scroll__horizontalMotion.enable' => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [ 'script' ],
				'subName'  => 'horizontalMotion.enable',
			],
			'module.decoration.scroll__fade.enable'        => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [ 'script' ],
				'subName'  => 'fade.enable',
			],
			'module.decoration.scroll__scaling.enable'     => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [ 'script' ],
				'subName'  => 'scaling.enable',
			],
			'module.decoration.scroll__rotating.enable'    => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [ 'script' ],
				'subName'  => 'rotating.enable',
			],
			'module.decoration.scroll__blur.enable'        => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [ 'script' ],
				'subName'  => 'blur.enable',
			],
			'module.decoration.scroll__verticalMotion'     => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [ 'script' ],
				'subName'  => 'verticalMotion',
			],
			'module.decoration.scroll__horizontalMotion'   => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [ 'script' ],
				'subName'  => 'horizontalMotion',
			],
			'module.decoration.scroll__fade'               => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [ 'script' ],
				'subName'  => 'fade',
			],
			'module.decoration.scroll__scaling'            => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [ 'script' ],
				'subName'  => 'scaling',
			],
			'module.decoration.scroll__rotating'           => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [ 'script' ],
				'subName'  => 'rotating',
			],
			'module.decoration.scroll__blur'               => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [ 'script' ],
				'subName'  => 'blur',
			],
			'module.decoration.scroll__motionTriggerStart' => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [ 'script' ],
				'subName'  => 'motionTriggerStart',
			],
			'module.decoration.sticky__position'           => [
				'attrName' => 'module.decoration.sticky',
				'preset'   => [ 'script' ],
				'subName'  => 'position',
			],
			'module.decoration.sticky__offset.top'         => [
				'attrName' => 'module.decoration.sticky',
				'preset'   => [ 'script' ],
				'subName'  => 'offset.top',
			],
			'module.decoration.sticky__offset.bottom'      => [
				'attrName' => 'module.decoration.sticky',
				'preset'   => [ 'script' ],
				'subName'  => 'offset.bottom',
			],
			'module.decoration.sticky__limit.top'          => [
				'attrName' => 'module.decoration.sticky',
				'preset'   => [ 'script' ],
				'subName'  => 'limit.top',
			],
			'module.decoration.sticky__limit.bottom'       => [
				'attrName' => 'module.decoration.sticky',
				'preset'   => [ 'script' ],
				'subName'  => 'limit.bottom',
			],
			'module.decoration.sticky__offset.surrounding' => [
				'attrName' => 'module.decoration.sticky',
				'preset'   => [ 'script' ],
				'subName'  => 'offset.surrounding',
			],
			'module.decoration.sticky__transition'         => [
				'attrName' => 'module.decoration.sticky',
				'preset'   => [ 'script' ],
				'subName'  => 'transition',
			],
			'module.decoration.attributes'                 => [
				'attrName' => 'module.decoration.attributes',
				'preset'   => [ 'html' ],
			],
		];

		$static_attrs = array_merge(
			$static_attrs,
			[
				'tab.decoration.background__color'         => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'tab.decoration.background__gradient'      => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'gradient',
				],
				'tab.decoration.background__gradient.enabled' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'gradient.enabled',
				],
				'tab.decoration.background__gradient.type' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'gradient.type',
				],
				'tab.decoration.background__gradient.direction' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'gradient.direction',
				],
				'tab.decoration.background__gradient.directionRadial' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'gradient.directionRadial',
				],
				'tab.decoration.background__gradient.repeat' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'gradient.repeat',
				],
				'tab.decoration.background__gradient.length' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'gradient.length',
				],
				'tab.decoration.background__gradient.overlaysImage' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'gradient.overlaysImage',
				],
				'tab.decoration.background__image.url'     => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'image.url',
				],
				'tab.decoration.background__image.parallax.enabled' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style', 'html', 'script' ],
					'subName'  => 'image.parallax.enabled',
				],
				'tab.decoration.background__image.parallax.method' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'image.parallax.method',
				],
				'tab.decoration.background__image.size'    => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'image.size',
				],
				'tab.decoration.background__image.width'   => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'image.width',
				],
				'tab.decoration.background__image.height'  => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'image.height',
				],
				'tab.decoration.background__image.position' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'image.position',
				],
				'tab.decoration.background__image.horizontalOffset' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'image.horizontalOffset',
				],
				'tab.decoration.background__image.verticalOffset' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'image.verticalOffset',
				],
				'tab.decoration.background__image.repeat'  => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'image.repeat',
				],
				'tab.decoration.background__image.blend'   => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'image.blend',
				],
				'tab.decoration.background__video.mp4'     => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'html' ],
					'subName'  => 'video.mp4',
				],
				'tab.decoration.background__video.webm'    => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'html' ],
					'subName'  => 'video.webm',
				],
				'tab.decoration.background__video.width'   => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'html' ],
					'subName'  => 'video.width',
				],
				'tab.decoration.background__video.height'  => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'html' ],
					'subName'  => 'video.height',
				],
				'tab.decoration.background__video.allowPlayerPause' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'html' ],
					'subName'  => 'video.allowPlayerPause',
				],
				'tab.decoration.background__video.pauseOutsideViewport' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'html' ],
					'subName'  => 'video.pauseOutsideViewport',
				],
				'tab.decoration.background__pattern.style' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'pattern.style',
				],
				'tab.decoration.background__pattern.enabled' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'pattern.enabled',
				],
				'tab.decoration.background__pattern.color' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'pattern.color',
				],
				'tab.decoration.background__pattern.transform' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'pattern.transform',
				],
				'tab.decoration.background__pattern.size'  => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.size',
				],
				'tab.decoration.background__pattern.width' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.width',
				],
				'tab.decoration.background__pattern.height' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.height',
				],
				'tab.decoration.background__pattern.repeatOrigin' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.repeatOrigin',
				],
				'tab.decoration.background__pattern.horizontalOffset' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.horizontalOffset',
				],
				'tab.decoration.background__pattern.verticalOffset' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.verticalOffset',
				],
				'tab.decoration.background__pattern.repeat' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.repeat',
				],
				'tab.decoration.background__pattern.blend' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.blend',
				],
				'tab.decoration.background__mask.style'    => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'mask.style',
				],
				'tab.decoration.background__mask.enabled'  => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'mask.enabled',
				],
				'tab.decoration.background__mask.color'    => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'mask.color',
				],
				'tab.decoration.background__mask.transform' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'mask.transform',
				],
				'tab.decoration.background__mask.aspectRatio' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'mask.aspectRatio',
				],
				'tab.decoration.background__mask.size'     => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'mask.size',
				],
				'tab.decoration.background__mask.width'    => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'mask.width',
				],
				'tab.decoration.background__mask.height'   => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'mask.height',
				],
				'tab.decoration.background__mask.position' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'mask.position',
				],
				'tab.decoration.background__mask.horizontalOffset' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'mask.horizontalOffset',
				],
				'tab.decoration.background__mask.verticalOffset' => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'mask.verticalOffset',
				],
				'tab.decoration.background__mask.blend'    => [
					'attrName' => 'tab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'mask.blend',
				],
				'activeTab.decoration.font.font__family'   => [
					'attrName' => 'activeTab.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'family',
				],
				'activeTab.decoration.font.font__weight'   => [
					'attrName' => 'activeTab.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'weight',
				],
				'activeTab.decoration.font.font__weightFineTune' => [
					'attrName' => 'activeTab.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'weightFineTune',
				],
				'activeTab.decoration.font.font__opticalSizing' => [
					'attrName' => 'activeTab.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'opticalSizing',
				],
				'activeTab.decoration.font.font__style'    => [
					'attrName' => 'activeTab.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'style',
				],
				'activeTab.decoration.font.font__lineColor' => [
					'attrName' => 'activeTab.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'lineColor',
				],
				'activeTab.decoration.font.font__lineThickness' => [
					'attrName' => 'activeTab.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'lineThickness',
				],
				'activeTab.decoration.font.font__underlineOffset' => [
					'attrName' => 'activeTab.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'underlineOffset',
				],
				'activeTab.decoration.font.font__lineStyle' => [
					'attrName' => 'activeTab.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'lineStyle',
				],
				'activeTab.decoration.font.font__textWrap' => [
					'attrName' => 'activeTab.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'textWrap',
				],
				'activeTab.decoration.font.font__writingMode' => [
					'attrName' => 'activeTab.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'writingMode',
				],
				'activeTab.decoration.font.font__hyphens'  => [
					'attrName' => 'activeTab.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'hyphens',
				],
				'activeTab.decoration.font.font__size'     => [
					'attrName' => 'activeTab.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'size',
				],
				'activeTab.decoration.font.font__capitalization' => [
					'attrName' => 'activeTab.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'capitalization',
				],
				'activeTab.decoration.font.font__letterSpacing' => [
					'attrName' => 'activeTab.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'letterSpacing',
				],
				'activeTab.decoration.font.font__lineHeight' => [
					'attrName' => 'activeTab.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'lineHeight',
				],
				'activeTab.decoration.font.font__columnCount' => [
					'attrName' => 'activeTab.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'columnCount',
				],
				'activeTab.decoration.font.font__columnGap' => [
					'attrName' => 'activeTab.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'columnGap',
				],
				'activeTab.decoration.font.textShadow__style' => [
					'attrName' => 'activeTab.decoration.font.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'style',
				],
				'activeTab.decoration.font.textShadow__horizontal' => [
					'attrName' => 'activeTab.decoration.font.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'horizontal',
				],
				'activeTab.decoration.font.textShadow__vertical' => [
					'attrName' => 'activeTab.decoration.font.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'vertical',
				],
				'activeTab.decoration.font.textShadow__blur' => [
					'attrName' => 'activeTab.decoration.font.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'blur',
				],
				'activeTab.decoration.font.textShadow__color' => [
					'attrName' => 'activeTab.decoration.font.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'activeTab.decoration.background__gradient' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'gradient',
				],
				'activeTab.decoration.background__gradient.enabled' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'gradient.enabled',
				],
				'activeTab.decoration.background__gradient.type' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'gradient.type',
				],
				'activeTab.decoration.background__gradient.direction' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'gradient.direction',
				],
				'activeTab.decoration.background__gradient.directionRadial' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'gradient.directionRadial',
				],
				'activeTab.decoration.background__gradient.repeat' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'gradient.repeat',
				],
				'activeTab.decoration.background__gradient.length' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'gradient.length',
				],
				'activeTab.decoration.background__gradient.overlaysImage' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'gradient.overlaysImage',
				],
				'activeTab.decoration.background__image.url' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'image.url',
				],
				'activeTab.decoration.background__image.parallax.enabled' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style', 'html', 'script' ],
					'subName'  => 'image.parallax.enabled',
				],
				'activeTab.decoration.background__image.parallax.method' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'image.parallax.method',
				],
				'activeTab.decoration.background__image.size' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'image.size',
				],
				'activeTab.decoration.background__image.width' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'image.width',
				],
				'activeTab.decoration.background__image.height' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'image.height',
				],
				'activeTab.decoration.background__image.position' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'image.position',
				],
				'activeTab.decoration.background__image.horizontalOffset' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'image.horizontalOffset',
				],
				'activeTab.decoration.background__image.verticalOffset' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'image.verticalOffset',
				],
				'activeTab.decoration.background__image.repeat' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'image.repeat',
				],
				'activeTab.decoration.background__image.blend' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'image.blend',
				],
				'activeTab.decoration.background__video.mp4' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'html' ],
					'subName'  => 'video.mp4',
				],
				'activeTab.decoration.background__video.webm' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'html' ],
					'subName'  => 'video.webm',
				],
				'activeTab.decoration.background__video.width' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'html' ],
					'subName'  => 'video.width',
				],
				'activeTab.decoration.background__video.height' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'html' ],
					'subName'  => 'video.height',
				],
				'activeTab.decoration.background__video.allowPlayerPause' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'html' ],
					'subName'  => 'video.allowPlayerPause',
				],
				'activeTab.decoration.background__video.pauseOutsideViewport' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'html' ],
					'subName'  => 'video.pauseOutsideViewport',
				],
				'activeTab.decoration.background__pattern.style' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'pattern.style',
				],
				'activeTab.decoration.background__pattern.enabled' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'pattern.enabled',
				],
				'activeTab.decoration.background__pattern.color' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'pattern.color',
				],
				'activeTab.decoration.background__pattern.transform' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'pattern.transform',
				],
				'activeTab.decoration.background__pattern.size' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.size',
				],
				'activeTab.decoration.background__pattern.width' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.width',
				],
				'activeTab.decoration.background__pattern.height' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.height',
				],
				'activeTab.decoration.background__pattern.repeatOrigin' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.repeatOrigin',
				],
				'activeTab.decoration.background__pattern.horizontalOffset' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.horizontalOffset',
				],
				'activeTab.decoration.background__pattern.verticalOffset' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.verticalOffset',
				],
				'activeTab.decoration.background__pattern.repeat' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.repeat',
				],
				'activeTab.decoration.background__pattern.blend' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.blend',
				],
				'activeTab.decoration.background__mask.style' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'mask.style',
				],
				'activeTab.decoration.background__mask.enabled' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'mask.enabled',
				],
				'activeTab.decoration.background__mask.color' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'mask.color',
				],
				'activeTab.decoration.background__mask.transform' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'mask.transform',
				],
				'activeTab.decoration.background__mask.aspectRatio' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'mask.aspectRatio',
				],
				'activeTab.decoration.background__mask.size' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'mask.size',
				],
				'activeTab.decoration.background__mask.width' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'mask.width',
				],
				'activeTab.decoration.background__mask.height' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'mask.height',
				],
				'activeTab.decoration.background__mask.position' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'mask.position',
				],
				'activeTab.decoration.background__mask.horizontalOffset' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'mask.horizontalOffset',
				],
				'activeTab.decoration.background__mask.verticalOffset' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'mask.verticalOffset',
				],
				'activeTab.decoration.background__mask.blend' => [
					'attrName' => 'activeTab.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'mask.blend',
				],
			]
		);

		return array_merge( $static_attrs, $loop_preset_attrs );
	}
}
