<?php
/**
 * Module Library: PostNavigation Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostNavigation;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


/**
 * Class PostNavigationPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\PostNavigation
 */
class PostNavigationPresetAttrsMap {
	/**
	 * Get the preset attributes map for the PostNavigation module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/post-nav' !== $module_name ) {
			return $map;
		}

		return [
			'links.advanced.prevText'                      => [
				'attrName' => 'links.advanced.prevText',
				'preset'   => 'content',
			],
			'links.advanced.nextText'                      => [
				'attrName' => 'links.advanced.nextText',
				'preset'   => 'content',
			],
			'module.advanced.inSameTerm'                   => [
				'attrName' => 'module.advanced.inSameTerm',
				'preset'   => 'content',
			],
			'module.advanced.taxonomyName'                 => [
				'attrName' => 'module.advanced.taxonomyName',
				'preset'   => 'content',
			],
			'module.advanced.targetLoop'                   => [
				'attrName' => 'module.advanced.targetLoop',
				'preset'   => 'content',
			],
			'links.advanced.showPrev'                      => [
				'attrName' => 'links.advanced.showPrev',
				'preset'   => [ 'html' ],
			],
			'links.advanced.showNext'                      => [
				'attrName' => 'links.advanced.showNext',
				'preset'   => [ 'html' ],
			],
			'links.decoration.background__color'           => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'links.decoration.background__gradient'        => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient',
			],
			'links.decoration.background__gradient.enabled' => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.enabled',
			],
			'links.decoration.background__gradient.type'   => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.type',
			],
			'links.decoration.background__gradient.direction' => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.direction',
			],
			'links.decoration.background__gradient.directionRadial' => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.directionRadial',
			],
			'links.decoration.background__gradient.repeat' => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.repeat',
			],
			'links.decoration.background__gradient.length' => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.length',
			],
			'links.decoration.background__gradient.overlaysImage' => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.overlaysImage',
			],
			'links.decoration.background__image.url'       => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'image.url',
			],
			'links.decoration.background__image.parallax.enabled' => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style', 'html', 'script' ],
				'subName'  => 'image.parallax.enabled',
			],
			'links.decoration.background__image.parallax.method' => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'image.parallax.method',
			],
			'links.decoration.background__image.size'      => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.size',
			],
			'links.decoration.background__image.width'     => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.width',
			],
			'links.decoration.background__image.height'    => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.height',
			],
			'links.decoration.background__image.position'  => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.position',
			],
			'links.decoration.background__image.horizontalOffset' => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.horizontalOffset',
			],
			'links.decoration.background__image.verticalOffset' => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.verticalOffset',
			],
			'links.decoration.background__image.repeat'    => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.repeat',
			],
			'links.decoration.background__image.blend'     => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'image.blend',
			],
			'links.decoration.background__video.mp4'       => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.mp4',
			],
			'links.decoration.background__video.webm'      => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.webm',
			],
			'links.decoration.background__video.width'     => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.width',
			],
			'links.decoration.background__video.height'    => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.height',
			],
			'links.decoration.background__video.allowPlayerPause' => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.allowPlayerPause',
			],
			'links.decoration.background__video.pauseOutsideViewport' => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.pauseOutsideViewport',
			],
			'links.decoration.background__pattern.style'   => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'pattern.style',
			],
			'links.decoration.background__pattern.enabled' => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'pattern.enabled',
			],
			'links.decoration.background__pattern.color'   => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'pattern.color',
			],
			'links.decoration.background__pattern.transform' => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'pattern.transform',
			],
			'links.decoration.background__pattern.size'    => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.size',
			],
			'links.decoration.background__pattern.width'   => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.width',
			],
			'links.decoration.background__pattern.height'  => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.height',
			],
			'links.decoration.background__pattern.repeatOrigin' => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.repeatOrigin',
			],
			'links.decoration.background__pattern.horizontalOffset' => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.horizontalOffset',
			],
			'links.decoration.background__pattern.verticalOffset' => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.verticalOffset',
			],
			'links.decoration.background__pattern.repeat'  => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.repeat',
			],
			'links.decoration.background__pattern.blend'   => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.blend',
			],
			'links.decoration.background__mask.style'      => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.style',
			],
			'links.decoration.background__mask.enabled'    => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.enabled',
			],
			'links.decoration.background__mask.color'      => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.color',
			],
			'links.decoration.background__mask.transform'  => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.transform',
			],
			'links.decoration.background__mask.aspectRatio' => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.aspectRatio',
			],
			'links.decoration.background__mask.size'       => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.size',
			],
			'links.decoration.background__mask.width'      => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.width',
			],
			'links.decoration.background__mask.height'     => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.height',
			],
			'links.decoration.background__mask.position'   => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.position',
			],
			'links.decoration.background__mask.horizontalOffset' => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.horizontalOffset',
			],
			'links.decoration.background__mask.verticalOffset' => [
				'attrName' => 'links.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.verticalOffset',
			],
			'links.decoration.background__mask.blend'      => [
				'attrName' => 'links.decoration.background',
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
			'links.decoration.font.font__family'           => [
				'attrName' => 'links.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'family',
			],
			'links.decoration.font.font__weight'           => [
				'attrName' => 'links.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weight',
			],
			'links.decoration.font.font__weightFineTune'   => [
				'attrName' => 'links.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weightFineTune',
			],
			'links.decoration.font.font__opticalSizing'    => [
				'attrName' => 'links.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'opticalSizing',
			],
			'links.decoration.font.font__style'            => [
				'attrName' => 'links.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'links.decoration.font.font__lineColor'        => [
				'attrName' => 'links.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineColor',
			],
			'links.decoration.font.font__lineThickness'    => [
				'attrName' => 'links.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineThickness',
			],
			'links.decoration.font.font__underlineOffset'  => [
				'attrName' => 'links.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'underlineOffset',
			],
			'links.decoration.font.font__lineStyle'        => [
				'attrName' => 'links.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineStyle',
			],
			'links.decoration.font.font__color'            => [
				'attrName' => 'links.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'links.decoration.font.font__size'             => [
				'attrName' => 'links.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			'links.decoration.font.font__letterSpacing'    => [
				'attrName' => 'links.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'letterSpacing',
			],
			'links.decoration.font.font__lineHeight'       => [
				'attrName' => 'links.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineHeight',
			],
			'links.decoration.font.font__capitalization'   => [
				'attrName' => 'links.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'capitalization',
			],
			'links.decoration.font.font__textWrap'         => [
				'attrName' => 'links.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textWrap',
			],
			'links.decoration.font.font__writingMode'      => [
				'attrName' => 'links.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'writingMode',
			],
			'links.decoration.font.font__hyphens'          => [
				'attrName' => 'links.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'hyphens',
			],
			'links.decoration.font.font__columnCount'      => [
				'attrName' => 'links.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnCount',
			],
			'links.decoration.font.font__columnGap'        => [
				'attrName' => 'links.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnGap',
			],
			'links.decoration.font.textShadow__style'      => [
				'attrName' => 'links.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'links.decoration.font.textShadow__horizontal' => [
				'attrName' => 'links.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'horizontal',
			],
			'links.decoration.font.textShadow__vertical'   => [
				'attrName' => 'links.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'vertical',
			],
			'links.decoration.font.textShadow__blur'       => [
				'attrName' => 'links.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'blur',
			],
			'links.decoration.font.textShadow__color'      => [
				'attrName' => 'links.decoration.font.textShadow',
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
			'module.decoration.sizing__alignment'          => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'alignment',
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
			'module.decoration.sizing__gridColumnEnd'      => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnEnd',
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
			'module.decoration.sizing__gridRowEnd'         => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowEnd',
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
			'module.decoration.sizing__minHeight'          => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'minHeight',
			],
			'module.decoration.sizing__size'               => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
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
			'links.decoration.spacing__margin'             => [
				'attrName' => 'links.decoration.spacing',
				'preset'   => [ 'style' ],
				'subName'  => 'margin',
			],
			'links.decoration.spacing__padding'            => [
				'attrName' => 'links.decoration.spacing',
				'preset'   => [ 'style' ],
				'subName'  => 'padding',
			],
			'links.decoration.border__radius'              => [
				'attrName' => 'links.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'radius',
			],
			'links.decoration.border__styles'              => [
				'attrName' => 'links.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles',
			],
			'links.decoration.border__styles.all.width'    => [
				'attrName' => 'links.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.width',
			],
			'links.decoration.border__styles.top.width'    => [
				'attrName' => 'links.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.width',
			],
			'links.decoration.border__styles.right.width'  => [
				'attrName' => 'links.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.width',
			],
			'links.decoration.border__styles.bottom.width' => [
				'attrName' => 'links.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.width',
			],
			'links.decoration.border__styles.left.width'   => [
				'attrName' => 'links.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.width',
			],
			'links.decoration.border__styles.all.color'    => [
				'attrName' => 'links.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.color',
			],
			'links.decoration.border__styles.top.color'    => [
				'attrName' => 'links.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.color',
			],
			'links.decoration.border__styles.right.color'  => [
				'attrName' => 'links.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.color',
			],
			'links.decoration.border__styles.bottom.color' => [
				'attrName' => 'links.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.color',
			],
			'links.decoration.border__styles.left.color'   => [
				'attrName' => 'links.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.color',
			],
			'links.decoration.border__styles.all.style'    => [
				'attrName' => 'links.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.style',
			],
			'links.decoration.border__styles.top.style'    => [
				'attrName' => 'links.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.style',
			],
			'links.decoration.border__styles.right.style'  => [
				'attrName' => 'links.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.style',
			],
			'links.decoration.border__styles.bottom.style' => [
				'attrName' => 'links.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.style',
			],
			'links.decoration.border__styles.left.style'   => [
				'attrName' => 'links.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.style',
			],
			'links.decoration.boxShadow__style'            => [
				'attrName' => 'links.decoration.boxShadow',
				'preset'   => [ 'html', 'style' ],
				'subName'  => 'style',
			],
			'links.decoration.boxShadow__horizontal'       => [
				'attrName' => 'links.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'horizontal',
			],
			'links.decoration.boxShadow__vertical'         => [
				'attrName' => 'links.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'vertical',
			],
			'links.decoration.boxShadow__blur'             => [
				'attrName' => 'links.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'blur',
			],
			'links.decoration.boxShadow__spread'           => [
				'attrName' => 'links.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'spread',
			],
			'links.decoration.boxShadow__color'            => [
				'attrName' => 'links.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'color',
			],
			'links.decoration.boxShadow__position'         => [
				'attrName' => 'links.decoration.boxShadow',
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
			'links.decoration.font.textEffects__fillType'  => [
				'attrName' => 'links.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'links.decoration.font.textEffects__gradient'  => [
				'attrName' => 'links.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient',
			],
			'links.decoration.font.textEffects__gradient.type' => [
				'attrName' => 'links.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.type',
			],
			'links.decoration.font.textEffects__gradient.direction' => [
				'attrName' => 'links.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.direction',
			],
			'links.decoration.font.textEffects__gradient.directionRadial' => [
				'attrName' => 'links.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.directionRadial',
			],
			'links.decoration.font.textEffects__gradient.repeat' => [
				'attrName' => 'links.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.repeat',
			],
			'links.decoration.font.textEffects__gradient.length' => [
				'attrName' => 'links.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.length',
			],
			'links.decoration.font.textEffects__imageFill.blend' => [
				'attrName' => 'links.decoration.font.textEffects',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'imageFill.blend',
			],
			'links.decoration.font.textEffects__imageFill.height' => [
				'attrName' => 'links.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.height',
			],
			'links.decoration.font.textEffects__imageFill.horizontalOffset' => [
				'attrName' => 'links.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.horizontalOffset',
			],
			'links.decoration.font.textEffects__imageFill.position' => [
				'attrName' => 'links.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.position',
			],
			'links.decoration.font.textEffects__imageFill.repeat' => [
				'attrName' => 'links.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.repeat',
			],
			'links.decoration.font.textEffects__imageFill.size' => [
				'attrName' => 'links.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.size',
			],
			'links.decoration.font.textEffects__imageFill.url' => [
				'attrName' => 'links.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.url',
			],
			'links.decoration.font.textEffects__imageFill.verticalOffset' => [
				'attrName' => 'links.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.verticalOffset',
			],
			'links.decoration.font.textEffects__imageFill.width' => [
				'attrName' => 'links.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.width',
			],
			'links.decoration.font.textEffects__strokeColor' => [
				'attrName' => 'links.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeColor',
			],
			'links.decoration.font.textEffects__strokeWidth' => [
				'attrName' => 'links.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeWidth',
			],
			'links.decoration.font.textEffects__strokePosition' => [
				'attrName' => 'links.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokePosition',
			],
			'module.advanced.htmlAttributes__class'        => [
				'attrName' => 'module.advanced.htmlAttributes',
				'preset'   => [ 'html' ],
				'subName'  => 'class',
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
			'css__links'                                   => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'links',
			],
			'css__prevLink'                                => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'prevLink',
			],
			'css__prevLinkArrow'                           => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'prevLinkArrow',
			],
			'css__nextLink'                                => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'nextLink',
			],
			'css__nextLinkArrow'                           => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'nextLinkArrow',
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
			'module.decoration.layout__alignContent'       => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'alignContent',
			],
			'module.decoration.layout__alignItems'         => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'alignItems',
			],
			'module.decoration.layout__collapseEmptyColumns' => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'collapseEmptyColumns',
			],
			'module.decoration.layout__columnGap'          => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'columnGap',
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
				'preset'   => [ 'style' ],
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
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnWidths',
			],
			'module.decoration.layout__gridDensity'        => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'gridDensity',
			],
			'module.decoration.layout__gridJustifyItems'   => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'gridJustifyItems',
			],
			'module.decoration.layout__gridOffsetRules'    => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'gridOffsetRules',
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
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowHeights',
			],
			'module.decoration.layout__gridRowMinHeight'   => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowMinHeight',
			],
			'module.decoration.layout__gridTemplateColumns' => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'gridTemplateColumns',
			],
			'module.decoration.layout__gridTemplateRows'   => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
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
	}
}
