<?php
/**
 * Module Library:Fullwidth Header Module Preset Attributes Map
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\FullwidthHeader;

use ET\Builder\Packages\Module\Options\Fit\FitPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Loop\LoopPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Sizing\SizingPresetAttrsMap;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


/**
 * Class FullwidthHeaderPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\FullwidthHeader
 */
class FullwidthHeaderPresetAttrsMap {
	/**
	 * Get the preset attributes map for the Fullwidth Header module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/fullwidth-header' !== $module_name ) {
			return $map;
		}

		$static_attrs = [
			'title.innerContent'                           => [
				'attrName' => 'title.innerContent',
				'preset'   => 'content',
			],
			'subhead.innerContent'                         => [
				'attrName' => 'subhead.innerContent',
				'preset'   => 'content',
			],
			'buttonOne.innerContent__text'                 => [
				'attrName' => 'buttonOne.innerContent',
				'preset'   => 'content',
				'subName'  => 'text',
			],
			'buttonTwo.innerContent__text'                 => [
				'attrName' => 'buttonTwo.innerContent',
				'preset'   => 'content',
				'subName'  => 'text',
			],
			'content.innerContent'                         => [
				'attrName' => 'content.innerContent',
				'preset'   => 'content',
			],
			'logo.innerContent__src'                       => [
				'attrName' => 'logo.innerContent',
				'preset'   => 'content',
				'subName'  => 'src',
			],
			'image.innerContent__src'                      => [
				'attrName' => 'image.innerContent',
				'preset'   => 'content',
				'subName'  => 'src',
			],
			'buttonOne.innerContent__linkUrl'              => [
				'attrName' => 'buttonOne.innerContent',
				'preset'   => 'content',
				'subName'  => 'linkUrl',
			],
			'buttonTwo.innerContent__linkUrl'              => [
				'attrName' => 'buttonTwo.innerContent',
				'preset'   => 'content',
				'subName'  => 'linkUrl',
			],
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
			'module.decoration.background__color'          => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'module.decoration.background__gradient'       => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient',
			],
			'module.decoration.background__gradient.enabled' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.enabled',
			],
			'module.decoration.background__gradient.type'  => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.type',
			],
			'module.decoration.background__gradient.direction' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.direction',
			],
			'module.decoration.background__gradient.directionRadial' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.directionRadial',
			],
			'module.decoration.background__gradient.repeat' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.repeat',
			],
			'module.decoration.background__gradient.length' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.length',
			],
			'module.decoration.background__gradient.overlaysImage' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.overlaysImage',
			],
			'module.decoration.background__image.url'      => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'image.url',
			],
			'module.decoration.background__image.parallax.enabled' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style', 'html', 'script' ],
				'subName'  => 'image.parallax.enabled',
			],
			'module.decoration.background__image.parallax.method' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'image.parallax.method',
			],
			'module.decoration.background__image.size'     => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.size',
			],
			'module.decoration.background__image.width'    => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.width',
			],
			'module.decoration.background__image.height'   => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.height',
			],
			'module.decoration.background__image.position' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.position',
			],
			'module.decoration.background__image.horizontalOffset' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.horizontalOffset',
			],
			'module.decoration.background__image.verticalOffset' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.verticalOffset',
			],
			'module.decoration.background__image.repeat'   => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.repeat',
			],
			'module.decoration.background__image.blend'    => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'image.blend',
			],
			'module.decoration.background__video.mp4'      => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.mp4',
			],
			'module.decoration.background__video.webm'     => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.webm',
			],
			'module.decoration.background__video.width'    => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.width',
			],
			'module.decoration.background__video.height'   => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.height',
			],
			'module.decoration.background__video.allowPlayerPause' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.allowPlayerPause',
			],
			'module.decoration.background__video.pauseOutsideViewport' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.pauseOutsideViewport',
			],
			'module.decoration.background__pattern.style'  => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'pattern.style',
			],
			'module.decoration.background__pattern.enabled' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'pattern.enabled',
			],
			'module.decoration.background__pattern.color'  => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'pattern.color',
			],
			'module.decoration.background__pattern.transform' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'pattern.transform',
			],
			'module.decoration.background__pattern.size'   => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.size',
			],
			'module.decoration.background__pattern.width'  => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.width',
			],
			'module.decoration.background__pattern.height' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.height',
			],
			'module.decoration.background__pattern.repeatOrigin' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.repeatOrigin',
			],
			'module.decoration.background__pattern.horizontalOffset' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.horizontalOffset',
			],
			'module.decoration.background__pattern.verticalOffset' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.verticalOffset',
			],
			'module.decoration.background__pattern.repeat' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.repeat',
			],
			'module.decoration.background__pattern.blend'  => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.blend',
			],
			'module.decoration.background__mask.style'     => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.style',
			],
			'module.decoration.background__mask.enabled'   => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.enabled',
			],
			'module.decoration.background__mask.color'     => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.color',
			],
			'module.decoration.background__mask.transform' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.transform',
			],
			'module.decoration.background__mask.aspectRatio' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.aspectRatio',
			],
			'module.decoration.background__mask.size'      => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.size',
			],
			'module.decoration.background__mask.width'     => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.width',
			],
			'module.decoration.background__mask.height'    => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.height',
			],
			'module.decoration.background__mask.position'  => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.position',
			],
			'module.decoration.background__mask.horizontalOffset' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.horizontalOffset',
			],
			'module.decoration.background__mask.verticalOffset' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.verticalOffset',
			],
			'module.decoration.background__mask.blend'     => [
				'attrName' => 'module.decoration.background',
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
			'module.advanced.text.text__orientation'       => [
				'attrName' => 'module.advanced.text.text',
				'preset'   => [ 'html' ],
				'subName'  => 'orientation',
			],
			'module.advanced.headerFullscreen'             => [
				'attrName' => 'module.advanced.headerFullscreen',
				'preset'   => [ 'html' ],
			],
			'scrollDown.decoration.icon__show'             => [
				'attrName' => 'scrollDown.decoration.icon',
				'preset'   => [ 'html' ],
				'subName'  => 'show',
			],
			'scrollDown.decoration.icon'                   => [
				'attrName' => 'scrollDown.decoration.icon',
				'preset'   => [ 'html', 'style' ],
			],
			'scrollDown.decoration.icon__color'            => [
				'attrName' => 'scrollDown.decoration.icon',
				'preset'   => [ 'html', 'style' ],
				'subName'  => 'color',
			],
			'scrollDown.decoration.icon__size'             => [
				'attrName' => 'scrollDown.decoration.icon',
				'preset'   => [ 'html', 'style' ],
				'subName'  => 'size',
			],
			'image.advanced.orientation'                   => [
				'attrName' => 'image.advanced.orientation',
				'preset'   => [ 'html' ],
			],
			'image.decoration.border__radius'              => [
				'attrName' => 'image.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'radius',
			],
			'image.decoration.border__styles'              => [
				'attrName' => 'image.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles',
			],
			'image.decoration.border__styles.all.width'    => [
				'attrName' => 'image.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.width',
			],
			'image.decoration.border__styles.top.width'    => [
				'attrName' => 'image.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.width',
			],
			'image.decoration.border__styles.right.width'  => [
				'attrName' => 'image.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.width',
			],
			'image.decoration.border__styles.bottom.width' => [
				'attrName' => 'image.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.width',
			],
			'image.decoration.border__styles.left.width'   => [
				'attrName' => 'image.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.width',
			],
			'image.decoration.border__styles.all.color'    => [
				'attrName' => 'image.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.color',
			],
			'image.decoration.border__styles.top.color'    => [
				'attrName' => 'image.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.color',
			],
			'image.decoration.border__styles.right.color'  => [
				'attrName' => 'image.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.color',
			],
			'image.decoration.border__styles.bottom.color' => [
				'attrName' => 'image.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.color',
			],
			'image.decoration.border__styles.left.color'   => [
				'attrName' => 'image.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.color',
			],
			'image.decoration.border__styles.all.style'    => [
				'attrName' => 'image.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.style',
			],
			'image.decoration.border__styles.top.style'    => [
				'attrName' => 'image.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.style',
			],
			'image.decoration.border__styles.right.style'  => [
				'attrName' => 'image.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.style',
			],
			'image.decoration.border__styles.bottom.style' => [
				'attrName' => 'image.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.style',
			],
			'image.decoration.border__styles.left.style'   => [
				'attrName' => 'image.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.style',
			],
			'image.decoration.boxShadow__style'            => [
				'attrName' => 'image.decoration.boxShadow',
				'preset'   => [ 'html', 'style' ],
				'subName'  => 'style',
			],
			'image.decoration.boxShadow__horizontal'       => [
				'attrName' => 'image.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'horizontal',
			],
			'image.decoration.boxShadow__vertical'         => [
				'attrName' => 'image.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'vertical',
			],
			'image.decoration.boxShadow__blur'             => [
				'attrName' => 'image.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'blur',
			],
			'image.decoration.boxShadow__spread'           => [
				'attrName' => 'image.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'spread',
			],
			'image.decoration.boxShadow__color'            => [
				'attrName' => 'image.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'color',
			],
			'image.decoration.boxShadow__position'         => [
				'attrName' => 'image.decoration.boxShadow',
				'preset'   => [ 'html', 'style' ],
				'subName'  => 'position',
			],
			'image.decoration.filters__hueRotate'          => [
				'attrName' => 'image.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'hueRotate',
			],
			'image.decoration.filters__saturate'           => [
				'attrName' => 'image.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'saturate',
			],
			'image.decoration.filters__brightness'         => [
				'attrName' => 'image.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'brightness',
			],
			'image.decoration.filters__contrast'           => [
				'attrName' => 'image.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'contrast',
			],
			'image.decoration.filters__invert'             => [
				'attrName' => 'image.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'invert',
			],
			'image.decoration.filters__sepia'              => [
				'attrName' => 'image.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'sepia',
			],
			'image.decoration.filters__opacity'            => [
				'attrName' => 'image.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'opacity',
			],
			'image.decoration.filters__blur'               => [
				'attrName' => 'image.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'blur',
			],
			'image.decoration.filters__backdropBlur'  => [
				'attrName' => 'image.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'backdropBlur',
			],
			'image.decoration.filters__backdropInvert' => [
				'attrName' => 'image.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'backdropInvert',
			],
			'image.decoration.filters__backdropSepia' => [
				'attrName' => 'image.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'backdropSepia',
			],
			'image.decoration.filters__blendMode'          => [
				'attrName' => 'image.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'blendMode',
			],
			'overlay.decoration.background__color'         => [
				'attrName' => 'overlay.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'content.advanced.orientation'                 => [
				'attrName' => 'content.advanced.orientation',
				'preset'   => [ 'html' ],
			],
			'module.advanced.text.text__color'             => [
				'attrName' => 'module.advanced.text.text',
				'preset'   => [ 'html' ],
				'subName'  => 'color',
			],
			'module.advanced.text.textShadow__style'       => [
				'attrName' => 'module.advanced.text.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'module.advanced.text.textShadow__horizontal'  => [
				'attrName' => 'module.advanced.text.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'horizontal',
			],
			'module.advanced.text.textShadow__vertical'    => [
				'attrName' => 'module.advanced.text.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'vertical',
			],
			'module.advanced.text.textShadow__blur'        => [
				'attrName' => 'module.advanced.text.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'blur',
			],
			'module.advanced.text.textShadow__color'       => [
				'attrName' => 'module.advanced.text.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'title.decoration.font.font__headingLevel'     => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'html' ],
				'subName'  => 'headingLevel',
			],
			'title.decoration.font.font__family'           => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'family',
			],
			'title.decoration.font.font__weight'           => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weight',
			],
			'title.decoration.font.font__style'            => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'title.decoration.font.font__lineColor'        => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineColor',
			],
			'title.decoration.font.font__lineStyle'        => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineStyle',
			],
			'title.decoration.font.font__textAlign'        => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textAlign',
			],
			'title.decoration.font.font__color'            => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'title.decoration.font.font__size'             => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			'title.decoration.font.font__letterSpacing'    => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'letterSpacing',
			],
			'title.decoration.font.font__lineHeight'       => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineHeight',
			],
			'title.decoration.font.textShadow__style'      => [
				'attrName' => 'title.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'title.decoration.font.textShadow__horizontal' => [
				'attrName' => 'title.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'horizontal',
			],
			'title.decoration.font.textShadow__vertical'   => [
				'attrName' => 'title.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'vertical',
			],
			'title.decoration.font.textShadow__blur'       => [
				'attrName' => 'title.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'blur',
			],
			'title.decoration.font.textShadow__color'      => [
				'attrName' => 'title.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
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
			'subhead.decoration.font.font__family'         => [
				'attrName' => 'subhead.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'family',
			],
			'subhead.decoration.font.font__weight'         => [
				'attrName' => 'subhead.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weight',
			],
			'subhead.decoration.font.font__style'          => [
				'attrName' => 'subhead.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'subhead.decoration.font.font__lineColor'      => [
				'attrName' => 'subhead.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineColor',
			],
			'subhead.decoration.font.font__lineStyle'      => [
				'attrName' => 'subhead.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineStyle',
			],
			'subhead.decoration.font.font__textAlign'      => [
				'attrName' => 'subhead.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textAlign',
			],
			'subhead.decoration.font.font__color'          => [
				'attrName' => 'subhead.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'subhead.decoration.font.font__size'           => [
				'attrName' => 'subhead.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			'subhead.decoration.font.font__letterSpacing'  => [
				'attrName' => 'subhead.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'letterSpacing',
			],
			'subhead.decoration.font.font__lineHeight'     => [
				'attrName' => 'subhead.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineHeight',
			],
			'subhead.decoration.font.textShadow__style'    => [
				'attrName' => 'subhead.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'subhead.decoration.font.textShadow__horizontal' => [
				'attrName' => 'subhead.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'horizontal',
			],
			'subhead.decoration.font.textShadow__vertical' => [
				'attrName' => 'subhead.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'vertical',
			],
			'subhead.decoration.font.textShadow__blur'     => [
				'attrName' => 'subhead.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'blur',
			],
			'subhead.decoration.font.textShadow__color'    => [
				'attrName' => 'subhead.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'buttonOne.decoration.background__color'       => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'buttonOne.decoration.background__gradient'    => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient',
			],
			'buttonOne.decoration.background__gradient.enabled' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.enabled',
			],
			'buttonOne.decoration.background__gradient.type' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.type',
			],
			'buttonOne.decoration.background__gradient.direction' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.direction',
			],
			'buttonOne.decoration.background__gradient.directionRadial' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.directionRadial',
			],
			'buttonOne.decoration.background__gradient.repeat' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.repeat',
			],
			'buttonOne.decoration.background__gradient.length' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.length',
			],
			'buttonOne.decoration.background__gradient.overlaysImage' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.overlaysImage',
			],
			'buttonOne.decoration.background__image.url'   => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'image.url',
			],
			'buttonOne.decoration.background__image.parallax.enabled' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style', 'html', 'script' ],
				'subName'  => 'image.parallax.enabled',
			],
			'buttonOne.decoration.background__image.parallax.method' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'image.parallax.method',
			],
			'buttonOne.decoration.background__image.size'  => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.size',
			],
			'buttonOne.decoration.background__image.width' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.width',
			],
			'buttonOne.decoration.background__image.height' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.height',
			],
			'buttonOne.decoration.background__image.position' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.position',
			],
			'buttonOne.decoration.background__image.horizontalOffset' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.horizontalOffset',
			],
			'buttonOne.decoration.background__image.verticalOffset' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.verticalOffset',
			],
			'buttonOne.decoration.background__image.repeat' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.repeat',
			],
			'buttonOne.decoration.background__image.blend' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'image.blend',
			],
			'buttonOne.decoration.background__video.mp4'   => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.mp4',
			],
			'buttonOne.decoration.background__video.webm'  => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.webm',
			],
			'buttonOne.decoration.background__video.width' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.width',
			],
			'buttonOne.decoration.background__video.height' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.height',
			],
			'buttonOne.decoration.background__video.allowPlayerPause' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.allowPlayerPause',
			],
			'buttonOne.decoration.background__video.pauseOutsideViewport' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.pauseOutsideViewport',
			],
			'buttonOne.decoration.background__pattern.style' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'pattern.style',
			],
			'buttonOne.decoration.background__pattern.enabled' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'pattern.enabled',
			],
			'buttonOne.decoration.background__pattern.color' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'pattern.color',
			],
			'buttonOne.decoration.background__pattern.transform' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'pattern.transform',
			],
			'buttonOne.decoration.background__pattern.size' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.size',
			],
			'buttonOne.decoration.background__pattern.width' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.width',
			],
			'buttonOne.decoration.background__pattern.height' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.height',
			],
			'buttonOne.decoration.background__pattern.repeatOrigin' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.repeatOrigin',
			],
			'buttonOne.decoration.background__pattern.horizontalOffset' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.horizontalOffset',
			],
			'buttonOne.decoration.background__pattern.verticalOffset' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.verticalOffset',
			],
			'buttonOne.decoration.background__pattern.repeat' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.repeat',
			],
			'buttonOne.decoration.background__pattern.blend' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.blend',
			],
			'buttonOne.decoration.background__mask.style'  => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.style',
			],
			'buttonOne.decoration.background__mask.enabled' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.enabled',
			],
			'buttonOne.decoration.background__mask.color'  => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.color',
			],
			'buttonOne.decoration.background__mask.transform' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.transform',
			],
			'buttonOne.decoration.background__mask.aspectRatio' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.aspectRatio',
			],
			'buttonOne.decoration.background__mask.size'   => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.size',
			],
			'buttonOne.decoration.background__mask.width'  => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.width',
			],
			'buttonOne.decoration.background__mask.height' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.height',
			],
			'buttonOne.decoration.background__mask.position' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.position',
			],
			'buttonOne.decoration.background__mask.horizontalOffset' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.horizontalOffset',
			],
			'buttonOne.decoration.background__mask.verticalOffset' => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.verticalOffset',
			],
			'buttonOne.decoration.background__mask.blend'  => [
				'attrName' => 'buttonOne.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.blend',
			],
			'buttonOne.decoration.border__radius'          => [
				'attrName' => 'buttonOne.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'radius',
			],
			'buttonOne.decoration.border__styles'          => [
				'attrName' => 'buttonOne.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles',
			],
			'buttonOne.decoration.border__styles.all.width' => [
				'attrName' => 'buttonOne.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.width',
			],
			'buttonOne.decoration.border__styles.top.width' => [
				'attrName' => 'buttonOne.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.width',
			],
			'buttonOne.decoration.border__styles.right.width' => [
				'attrName' => 'buttonOne.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.width',
			],
			'buttonOne.decoration.border__styles.bottom.width' => [
				'attrName' => 'buttonOne.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.width',
			],
			'buttonOne.decoration.border__styles.left.width' => [
				'attrName' => 'buttonOne.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.width',
			],
			'buttonOne.decoration.border__styles.all.color' => [
				'attrName' => 'buttonOne.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.color',
			],
			'buttonOne.decoration.border__styles.top.color' => [
				'attrName' => 'buttonOne.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.color',
			],
			'buttonOne.decoration.border__styles.right.color' => [
				'attrName' => 'buttonOne.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.color',
			],
			'buttonOne.decoration.border__styles.bottom.color' => [
				'attrName' => 'buttonOne.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.color',
			],
			'buttonOne.decoration.border__styles.left.color' => [
				'attrName' => 'buttonOne.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.color',
			],
			'buttonOne.decoration.border__styles.all.style' => [
				'attrName' => 'buttonOne.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.style',
			],
			'buttonOne.decoration.border__styles.top.style' => [
				'attrName' => 'buttonOne.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.style',
			],
			'buttonOne.decoration.border__styles.right.style' => [
				'attrName' => 'buttonOne.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.style',
			],
			'buttonOne.decoration.border__styles.bottom.style' => [
				'attrName' => 'buttonOne.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.style',
			],
			'buttonOne.decoration.border__styles.left.style' => [
				'attrName' => 'buttonOne.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.style',
			],
			'buttonOne.decoration.font.font__family'       => [
				'attrName' => 'buttonOne.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'family',
			],
			'buttonOne.decoration.font.font__weight'       => [
				'attrName' => 'buttonOne.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weight',
			],
			'buttonOne.decoration.font.font__style'        => [
				'attrName' => 'buttonOne.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'buttonOne.decoration.font.font__lineColor'    => [
				'attrName' => 'buttonOne.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineColor',
			],
			'buttonOne.decoration.font.font__lineStyle'    => [
				'attrName' => 'buttonOne.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineStyle',
			],
			'buttonOne.decoration.font.font__textAlign'    => [
				'attrName' => 'buttonOne.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textAlign',
			],
			'buttonOne.decoration.font.font__color'        => [
				'attrName' => 'buttonOne.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'buttonOne.decoration.font.font__size'         => [
				'attrName' => 'buttonOne.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			'buttonOne.decoration.font.font__letterSpacing' => [
				'attrName' => 'buttonOne.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'letterSpacing',
			],
			'buttonOne.decoration.font.textShadow__style'  => [
				'attrName' => 'buttonOne.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'buttonOne.decoration.font.textShadow__horizontal' => [
				'attrName' => 'buttonOne.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'horizontal',
			],
			'buttonOne.decoration.font.textShadow__vertical' => [
				'attrName' => 'buttonOne.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'vertical',
			],
			'buttonOne.decoration.font.textShadow__blur'   => [
				'attrName' => 'buttonOne.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'blur',
			],
			'buttonOne.decoration.font.textShadow__color'  => [
				'attrName' => 'buttonOne.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'buttonOne.decoration.button__icon.enable'     => [
				'attrName' => 'buttonOne.decoration.button',
				'preset'   => [ 'style' ],
				'subName'  => 'icon.enable',
			],
			'buttonOne.decoration.button__icon.settings'   => [
				'attrName' => 'buttonOne.decoration.button',
				'preset'   => [ 'html', 'style' ],
				'subName'  => 'icon.settings',
			],
			'buttonOne.decoration.button__icon.color'      => [
				'attrName' => 'buttonOne.decoration.button',
				'preset'   => [ 'style' ],
				'subName'  => 'icon.color',
			],
			'buttonOne.decoration.button__icon.placement'  => [
				'attrName' => 'buttonOne.decoration.button',
				'preset'   => [ 'style' ],
				'subName'  => 'icon.placement',
			],
			'buttonOne.decoration.button__icon.onHover'    => [
				'attrName' => 'buttonOne.decoration.button',
				'preset'   => [ 'style' ],
				'subName'  => 'icon.onHover',
			],
			'buttonOne.decoration.sizing__width'           => [
				'attrName' => 'buttonOne.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'width',
			],
			'buttonOne.decoration.sizing__maxWidth'        => [
				'attrName' => 'buttonOne.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'maxWidth',
			],
			'buttonOne.decoration.sizing__flexGrow'        => [
				'attrName' => 'buttonOne.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'flexGrow',
			],
			'buttonOne.decoration.sizing__flexShrink'      => [
				'attrName' => 'buttonOne.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'flexShrink',
			],
			'buttonOne.decoration.sizing__gridColumnSpan'  => [
				'attrName' => 'buttonOne.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnSpan',
			],
			'buttonOne.decoration.sizing__gridColumnStart' => [
				'attrName' => 'buttonOne.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnStart',
			],
			'buttonOne.decoration.sizing__gridRowSpan'     => [
				'attrName' => 'buttonOne.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowSpan',
			],
			'buttonOne.decoration.sizing__gridRowStart'    => [
				'attrName' => 'buttonOne.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowStart',
			],
			'buttonOne.decoration.sizing__gridColumnEnd'   => [
				'attrName' => 'buttonOne.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnEnd',
			],
			'buttonOne.decoration.sizing__gridRowEnd'      => [
				'attrName' => 'buttonOne.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowEnd',
			],
			'buttonOne.decoration.sizing__minHeight'       => [
				'attrName' => 'buttonOne.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'minHeight',
			],
			'buttonOne.decoration.sizing__size'            => [
				'attrName' => 'buttonOne.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			'buttonOne.decoration.sizing__height'          => [
				'attrName' => 'buttonOne.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'height',
			],
			'buttonOne.decoration.sizing__maxHeight'       => [
				'attrName' => 'buttonOne.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'maxHeight',
			],
			'buttonOne.decoration.sizing__aspectRatio'     => [
				'attrName' => 'buttonOne.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'aspectRatio',
			],
			'buttonOne.decoration.sizing__flexType'        => [
				'attrName' => 'buttonOne.decoration.sizing',
				'preset'   => [ 'html' ],
				'subName'  => 'flexType',
			],
			'buttonOne.decoration.spacing__margin'         => [
				'attrName' => 'buttonOne.decoration.spacing',
				'preset'   => [ 'style' ],
				'subName'  => 'margin',
			],
			'buttonOne.decoration.spacing__padding'        => [
				'attrName' => 'buttonOne.decoration.spacing',
				'preset'   => [ 'style' ],
				'subName'  => 'padding',
			],
			'buttonOne.decoration.boxShadow__style'        => [
				'attrName' => 'buttonOne.decoration.boxShadow',
				'preset'   => [ 'html', 'style' ],
				'subName'  => 'style',
			],
			'buttonOne.decoration.boxShadow__horizontal'   => [
				'attrName' => 'buttonOne.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'horizontal',
			],
			'buttonOne.decoration.boxShadow__vertical'     => [
				'attrName' => 'buttonOne.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'vertical',
			],
			'buttonOne.decoration.boxShadow__blur'         => [
				'attrName' => 'buttonOne.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'blur',
			],
			'buttonOne.decoration.boxShadow__spread'       => [
				'attrName' => 'buttonOne.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'spread',
			],
			'buttonOne.decoration.boxShadow__color'        => [
				'attrName' => 'buttonOne.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'color',
			],
			'buttonOne.decoration.boxShadow__position'     => [
				'attrName' => 'buttonOne.decoration.boxShadow',
				'preset'   => [ 'html', 'style' ],
				'subName'  => 'position',
			],
			'buttonTwo.decoration.background__color'       => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'buttonTwo.decoration.background__gradient'    => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient',
			],
			'buttonTwo.decoration.background__gradient.enabled' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.enabled',
			],
			'buttonTwo.decoration.background__gradient.type' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.type',
			],
			'buttonTwo.decoration.background__gradient.direction' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.direction',
			],
			'buttonTwo.decoration.background__gradient.directionRadial' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.directionRadial',
			],
			'buttonTwo.decoration.background__gradient.repeat' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.repeat',
			],
			'buttonTwo.decoration.background__gradient.length' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.length',
			],
			'buttonTwo.decoration.background__gradient.overlaysImage' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.overlaysImage',
			],
			'buttonTwo.decoration.background__image.url'   => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'image.url',
			],
			'buttonTwo.decoration.background__image.parallax.enabled' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style', 'html', 'script' ],
				'subName'  => 'image.parallax.enabled',
			],
			'buttonTwo.decoration.background__image.parallax.method' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'image.parallax.method',
			],
			'buttonTwo.decoration.background__image.size'  => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.size',
			],
			'buttonTwo.decoration.background__image.width' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.width',
			],
			'buttonTwo.decoration.background__image.height' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.height',
			],
			'buttonTwo.decoration.background__image.position' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.position',
			],
			'buttonTwo.decoration.background__image.horizontalOffset' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.horizontalOffset',
			],
			'buttonTwo.decoration.background__image.verticalOffset' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.verticalOffset',
			],
			'buttonTwo.decoration.background__image.repeat' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'image.repeat',
			],
			'buttonTwo.decoration.background__image.blend' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'image.blend',
			],
			'buttonTwo.decoration.background__video.mp4'   => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.mp4',
			],
			'buttonTwo.decoration.background__video.webm'  => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.webm',
			],
			'buttonTwo.decoration.background__video.width' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.width',
			],
			'buttonTwo.decoration.background__video.height' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.height',
			],
			'buttonTwo.decoration.background__video.allowPlayerPause' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.allowPlayerPause',
			],
			'buttonTwo.decoration.background__video.pauseOutsideViewport' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'html' ],
				'subName'  => 'video.pauseOutsideViewport',
			],
			'buttonTwo.decoration.background__pattern.style' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'pattern.style',
			],
			'buttonTwo.decoration.background__pattern.enabled' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'pattern.enabled',
			],
			'buttonTwo.decoration.background__pattern.color' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'pattern.color',
			],
			'buttonTwo.decoration.background__pattern.transform' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'pattern.transform',
			],
			'buttonTwo.decoration.background__pattern.size' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.size',
			],
			'buttonTwo.decoration.background__pattern.width' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.width',
			],
			'buttonTwo.decoration.background__pattern.height' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.height',
			],
			'buttonTwo.decoration.background__pattern.repeatOrigin' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.repeatOrigin',
			],
			'buttonTwo.decoration.background__pattern.horizontalOffset' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.horizontalOffset',
			],
			'buttonTwo.decoration.background__pattern.verticalOffset' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.verticalOffset',
			],
			'buttonTwo.decoration.background__pattern.repeat' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.repeat',
			],
			'buttonTwo.decoration.background__pattern.blend' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'pattern.blend',
			],
			'buttonTwo.decoration.background__mask.style'  => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.style',
			],
			'buttonTwo.decoration.background__mask.enabled' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.enabled',
			],
			'buttonTwo.decoration.background__mask.color'  => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.color',
			],
			'buttonTwo.decoration.background__mask.transform' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.transform',
			],
			'buttonTwo.decoration.background__mask.aspectRatio' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'mask.aspectRatio',
			],
			'buttonTwo.decoration.background__mask.size'   => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.size',
			],
			'buttonTwo.decoration.background__mask.width'  => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.width',
			],
			'buttonTwo.decoration.background__mask.height' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.height',
			],
			'buttonTwo.decoration.background__mask.position' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.position',
			],
			'buttonTwo.decoration.background__mask.horizontalOffset' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.horizontalOffset',
			],
			'buttonTwo.decoration.background__mask.verticalOffset' => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.verticalOffset',
			],
			'buttonTwo.decoration.background__mask.blend'  => [
				'attrName' => 'buttonTwo.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'mask.blend',
			],
			'buttonTwo.decoration.border__radius'          => [
				'attrName' => 'buttonTwo.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'radius',
			],
			'buttonTwo.decoration.border__styles'          => [
				'attrName' => 'buttonTwo.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles',
			],
			'buttonTwo.decoration.border__styles.all.width' => [
				'attrName' => 'buttonTwo.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.width',
			],
			'buttonTwo.decoration.border__styles.top.width' => [
				'attrName' => 'buttonTwo.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.width',
			],
			'buttonTwo.decoration.border__styles.right.width' => [
				'attrName' => 'buttonTwo.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.width',
			],
			'buttonTwo.decoration.border__styles.bottom.width' => [
				'attrName' => 'buttonTwo.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.width',
			],
			'buttonTwo.decoration.border__styles.left.width' => [
				'attrName' => 'buttonTwo.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.width',
			],
			'buttonTwo.decoration.border__styles.all.color' => [
				'attrName' => 'buttonTwo.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.color',
			],
			'buttonTwo.decoration.border__styles.top.color' => [
				'attrName' => 'buttonTwo.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.color',
			],
			'buttonTwo.decoration.border__styles.right.color' => [
				'attrName' => 'buttonTwo.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.color',
			],
			'buttonTwo.decoration.border__styles.bottom.color' => [
				'attrName' => 'buttonTwo.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.color',
			],
			'buttonTwo.decoration.border__styles.left.color' => [
				'attrName' => 'buttonTwo.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.color',
			],
			'buttonTwo.decoration.border__styles.all.style' => [
				'attrName' => 'buttonTwo.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.style',
			],
			'buttonTwo.decoration.border__styles.top.style' => [
				'attrName' => 'buttonTwo.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.style',
			],
			'buttonTwo.decoration.border__styles.right.style' => [
				'attrName' => 'buttonTwo.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.style',
			],
			'buttonTwo.decoration.border__styles.bottom.style' => [
				'attrName' => 'buttonTwo.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.style',
			],
			'buttonTwo.decoration.border__styles.left.style' => [
				'attrName' => 'buttonTwo.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.style',
			],
			'buttonTwo.decoration.font.font__family'       => [
				'attrName' => 'buttonTwo.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'family',
			],
			'buttonTwo.decoration.font.font__weight'       => [
				'attrName' => 'buttonTwo.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weight',
			],
			'buttonTwo.decoration.font.font__style'        => [
				'attrName' => 'buttonTwo.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'buttonTwo.decoration.font.font__lineColor'    => [
				'attrName' => 'buttonTwo.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineColor',
			],
			'buttonTwo.decoration.font.font__lineStyle'    => [
				'attrName' => 'buttonTwo.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineStyle',
			],
			'buttonTwo.decoration.font.font__textAlign'    => [
				'attrName' => 'buttonTwo.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textAlign',
			],
			'buttonTwo.decoration.font.font__color'        => [
				'attrName' => 'buttonTwo.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'buttonTwo.decoration.font.font__size'         => [
				'attrName' => 'buttonTwo.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			'buttonTwo.decoration.font.font__letterSpacing' => [
				'attrName' => 'buttonTwo.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'letterSpacing',
			],
			'buttonTwo.decoration.font.textShadow__style'  => [
				'attrName' => 'buttonTwo.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'buttonTwo.decoration.font.textShadow__horizontal' => [
				'attrName' => 'buttonTwo.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'horizontal',
			],
			'buttonTwo.decoration.font.textShadow__vertical' => [
				'attrName' => 'buttonTwo.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'vertical',
			],
			'buttonTwo.decoration.font.textShadow__blur'   => [
				'attrName' => 'buttonTwo.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'blur',
			],
			'buttonTwo.decoration.font.textShadow__color'  => [
				'attrName' => 'buttonTwo.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'buttonTwo.decoration.button__icon.enable'     => [
				'attrName' => 'buttonTwo.decoration.button',
				'preset'   => [ 'style' ],
				'subName'  => 'icon.enable',
			],
			'buttonTwo.decoration.button__icon.settings'   => [
				'attrName' => 'buttonTwo.decoration.button',
				'preset'   => [ 'html', 'style' ],
				'subName'  => 'icon.settings',
			],
			'buttonTwo.decoration.button__icon.color'      => [
				'attrName' => 'buttonTwo.decoration.button',
				'preset'   => [ 'style' ],
				'subName'  => 'icon.color',
			],
			'buttonTwo.decoration.button__icon.placement'  => [
				'attrName' => 'buttonTwo.decoration.button',
				'preset'   => [ 'style' ],
				'subName'  => 'icon.placement',
			],
			'buttonTwo.decoration.button__icon.onHover'    => [
				'attrName' => 'buttonTwo.decoration.button',
				'preset'   => [ 'style' ],
				'subName'  => 'icon.onHover',
			],
			'buttonTwo.decoration.sizing__width'           => [
				'attrName' => 'buttonTwo.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'width',
			],
			'buttonTwo.decoration.sizing__maxWidth'        => [
				'attrName' => 'buttonTwo.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'maxWidth',
			],
			'buttonTwo.decoration.sizing__flexGrow'        => [
				'attrName' => 'buttonTwo.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'flexGrow',
			],
			'buttonTwo.decoration.sizing__flexShrink'      => [
				'attrName' => 'buttonTwo.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'flexShrink',
			],
			'buttonTwo.decoration.sizing__gridColumnSpan'  => [
				'attrName' => 'buttonTwo.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnSpan',
			],
			'buttonTwo.decoration.sizing__gridColumnStart' => [
				'attrName' => 'buttonTwo.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnStart',
			],
			'buttonTwo.decoration.sizing__gridRowSpan'     => [
				'attrName' => 'buttonTwo.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowSpan',
			],
			'buttonTwo.decoration.sizing__gridRowStart'    => [
				'attrName' => 'buttonTwo.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowStart',
			],
			'buttonTwo.decoration.sizing__gridColumnEnd'   => [
				'attrName' => 'buttonTwo.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnEnd',
			],
			'buttonTwo.decoration.sizing__gridRowEnd'      => [
				'attrName' => 'buttonTwo.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowEnd',
			],
			'buttonTwo.decoration.sizing__minHeight'       => [
				'attrName' => 'buttonTwo.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'minHeight',
			],
			'buttonTwo.decoration.sizing__size'            => [
				'attrName' => 'buttonTwo.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			'buttonTwo.decoration.sizing__height'          => [
				'attrName' => 'buttonTwo.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'height',
			],
			'buttonTwo.decoration.sizing__maxHeight'       => [
				'attrName' => 'buttonTwo.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'maxHeight',
			],
			'buttonTwo.decoration.sizing__aspectRatio'     => [
				'attrName' => 'buttonTwo.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'aspectRatio',
			],
			'buttonTwo.decoration.sizing__flexType'        => [
				'attrName' => 'buttonTwo.decoration.sizing',
				'preset'   => [ 'html' ],
				'subName'  => 'flexType',
			],
			'buttonTwo.decoration.spacing__margin'         => [
				'attrName' => 'buttonTwo.decoration.spacing',
				'preset'   => [ 'style' ],
				'subName'  => 'margin',
			],
			'buttonTwo.decoration.spacing__padding'        => [
				'attrName' => 'buttonTwo.decoration.spacing',
				'preset'   => [ 'style' ],
				'subName'  => 'padding',
			],
			'buttonTwo.decoration.boxShadow__style'        => [
				'attrName' => 'buttonTwo.decoration.boxShadow',
				'preset'   => [ 'html', 'style' ],
				'subName'  => 'style',
			],
			'buttonTwo.decoration.boxShadow__horizontal'   => [
				'attrName' => 'buttonTwo.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'horizontal',
			],
			'buttonTwo.decoration.boxShadow__vertical'     => [
				'attrName' => 'buttonTwo.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'vertical',
			],
			'buttonTwo.decoration.boxShadow__blur'         => [
				'attrName' => 'buttonTwo.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'blur',
			],
			'buttonTwo.decoration.boxShadow__spread'       => [
				'attrName' => 'buttonTwo.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'spread',
			],
			'buttonTwo.decoration.boxShadow__color'        => [
				'attrName' => 'buttonTwo.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'color',
			],
			'buttonTwo.decoration.boxShadow__position'     => [
				'attrName' => 'buttonTwo.decoration.boxShadow',
				'preset'   => [ 'html', 'style' ],
				'subName'  => 'position',
			],
			'content.advanced.maxWidth'                    => [
				'attrName' => 'content.advanced.maxWidth',
				'preset'   => [ 'style' ],
			],
			'buttonOne.decoration.font.font__weightFineTune' => [
				'attrName' => 'buttonOne.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weightFineTune',
			],
			'buttonOne.decoration.font.font__opticalSizing' => [
				'attrName' => 'buttonOne.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'opticalSizing',
			],
			'buttonOne.decoration.font.font__capitalization' => [
				'attrName' => 'buttonOne.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'capitalization',
			],
			'buttonOne.decoration.font.font__lineThickness' => [
				'attrName' => 'buttonOne.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineThickness',
			],
			'buttonOne.decoration.font.font__underlineOffset' => [
				'attrName' => 'buttonOne.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'underlineOffset',
			],
			'buttonOne.decoration.font.font__textWrap'     => [
				'attrName' => 'buttonOne.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textWrap',
			],
			'buttonOne.decoration.font.font__writingMode'  => [
				'attrName' => 'buttonOne.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'writingMode',
			],
			'buttonOne.decoration.font.font__hyphens'      => [
				'attrName' => 'buttonOne.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'hyphens',
			],
			'buttonOne.decoration.font.font__columnCount'  => [
				'attrName' => 'buttonOne.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnCount',
			],
			'buttonOne.decoration.font.font__columnGap'    => [
				'attrName' => 'buttonOne.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnGap',
			],
			'buttonTwo.decoration.font.font__weightFineTune' => [
				'attrName' => 'buttonTwo.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weightFineTune',
			],
			'buttonTwo.decoration.font.font__opticalSizing' => [
				'attrName' => 'buttonTwo.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'opticalSizing',
			],
			'buttonTwo.decoration.font.font__capitalization' => [
				'attrName' => 'buttonTwo.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'capitalization',
			],
			'buttonTwo.decoration.font.font__lineThickness' => [
				'attrName' => 'buttonTwo.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineThickness',
			],
			'buttonTwo.decoration.font.font__underlineOffset' => [
				'attrName' => 'buttonTwo.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'underlineOffset',
			],
			'buttonTwo.decoration.font.font__textWrap'     => [
				'attrName' => 'buttonTwo.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textWrap',
			],
			'buttonTwo.decoration.font.font__writingMode'  => [
				'attrName' => 'buttonTwo.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'writingMode',
			],
			'buttonTwo.decoration.font.font__hyphens'      => [
				'attrName' => 'buttonTwo.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'hyphens',
			],
			'buttonTwo.decoration.font.font__columnCount'  => [
				'attrName' => 'buttonTwo.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnCount',
			],
			'buttonTwo.decoration.font.font__columnGap'    => [
				'attrName' => 'buttonTwo.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnGap',
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
			'subhead.decoration.font.font__weightFineTune' => [
				'attrName' => 'subhead.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weightFineTune',
			],
			'subhead.decoration.font.font__opticalSizing'  => [
				'attrName' => 'subhead.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'opticalSizing',
			],
			'subhead.decoration.font.font__capitalization' => [
				'attrName' => 'subhead.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'capitalization',
			],
			'subhead.decoration.font.font__lineThickness'  => [
				'attrName' => 'subhead.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineThickness',
			],
			'subhead.decoration.font.font__underlineOffset' => [
				'attrName' => 'subhead.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'underlineOffset',
			],
			'subhead.decoration.font.font__textWrap'       => [
				'attrName' => 'subhead.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textWrap',
			],
			'subhead.decoration.font.font__writingMode'    => [
				'attrName' => 'subhead.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'writingMode',
			],
			'subhead.decoration.font.font__hyphens'        => [
				'attrName' => 'subhead.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'hyphens',
			],
			'subhead.decoration.font.font__columnCount'    => [
				'attrName' => 'subhead.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnCount',
			],
			'subhead.decoration.font.font__columnGap'      => [
				'attrName' => 'subhead.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnGap',
			],
			'title.decoration.font.font__weightFineTune'   => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weightFineTune',
			],
			'title.decoration.font.font__opticalSizing'    => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'opticalSizing',
			],
			'title.decoration.font.font__capitalization'   => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'capitalization',
			],
			'title.decoration.font.font__lineThickness'    => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineThickness',
			],
			'title.decoration.font.font__underlineOffset'  => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'underlineOffset',
			],
			'title.decoration.font.font__textWrap'         => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textWrap',
			],
			'title.decoration.font.font__writingMode'      => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'writingMode',
			],
			'title.decoration.font.font__hyphens'          => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'hyphens',
			],
			'title.decoration.font.font__columnCount'      => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnCount',
			],
			'title.decoration.font.font__columnGap'        => [
				'attrName' => 'title.decoration.font.font',
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
			'buttonOne.decoration.font.textEffects__strokePosition' => [
				'attrName' => 'buttonOne.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokePosition',
			],
			'buttonTwo.decoration.font.textEffects__strokePosition' => [
				'attrName' => 'buttonTwo.decoration.font.textEffects',
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
			'subhead.decoration.font.textEffects__strokePosition' => [
				'attrName' => 'subhead.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokePosition',
			],
			'title.decoration.font.textEffects__strokePosition' => [
				'attrName' => 'title.decoration.font.textEffects',
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
			'module.decoration.sizing__size'               => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
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
			'css__headerContainer'                         => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'headerContainer',
			],
			'css__headerImage'                             => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'headerImage',
			],
			'css__logo'                                    => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'logo',
			],
			'css__title'                                   => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'title',
			],
			'css__content'                                 => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'content',
			],
			'css__subtitle'                                => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'subtitle',
			],
			'css__button1'                                 => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'button1',
			],
			'css__button2'                                 => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'button2',
			],
			'css__scrollButton'                            => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'scrollButton',
			],
			'logo.innerContent__alt'                       => [
				'attrName' => 'logo.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'alt',
			],
			'logo.innerContent__title'                     => [
				'attrName' => 'logo.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'title',
			],
			'image.innerContent__alt'                      => [
				'attrName' => 'image.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'alt',
			],
			'image.innerContent__title'                    => [
				'attrName' => 'image.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'title',
			],
			'buttonOne.innerContent__rel'                  => [
				'attrName' => 'buttonOne.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'rel',
			],
			'buttonTwo.innerContent__rel'                  => [
				'attrName' => 'buttonTwo.innerContent',
				'preset'   => [ 'html' ],
				'subName'  => 'rel',
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
			'buttonOne.decoration.button__enable'          => [
				'attrName' => 'buttonOne.decoration.button',
				'preset'   => [ 'style' ],
				'subName'  => 'enable',
			],
			'buttonTwo.decoration.button__enable'          => [
				'attrName' => 'buttonTwo.decoration.button',
				'preset'   => [ 'style' ],
				'subName'  => 'enable',
			],
			'title.decoration.font.textEffects__fillType'  => [
				'attrName' => 'title.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'title.decoration.font.textEffects__gradient'  => [
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
			'subhead.decoration.font.textEffects__fillType' => [
				'attrName' => 'subhead.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'subhead.decoration.font.textEffects__gradient' => [
				'attrName' => 'subhead.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient',
			],
			'subhead.decoration.font.textEffects__gradient.type' => [
				'attrName' => 'subhead.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.type',
			],
			'subhead.decoration.font.textEffects__gradient.direction' => [
				'attrName' => 'subhead.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.direction',
			],
			'subhead.decoration.font.textEffects__gradient.directionRadial' => [
				'attrName' => 'subhead.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.directionRadial',
			],
			'subhead.decoration.font.textEffects__gradient.repeat' => [
				'attrName' => 'subhead.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.repeat',
			],
			'subhead.decoration.font.textEffects__gradient.length' => [
				'attrName' => 'subhead.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.length',
			],
			'subhead.decoration.font.textEffects__imageFill.blend' => [
				'attrName' => 'subhead.decoration.font.textEffects',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'imageFill.blend',
			],
			'subhead.decoration.font.textEffects__imageFill.height' => [
				'attrName' => 'subhead.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.height',
			],
			'subhead.decoration.font.textEffects__imageFill.horizontalOffset' => [
				'attrName' => 'subhead.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.horizontalOffset',
			],
			'subhead.decoration.font.textEffects__imageFill.position' => [
				'attrName' => 'subhead.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.position',
			],
			'subhead.decoration.font.textEffects__imageFill.repeat' => [
				'attrName' => 'subhead.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.repeat',
			],
			'subhead.decoration.font.textEffects__imageFill.size' => [
				'attrName' => 'subhead.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.size',
			],
			'subhead.decoration.font.textEffects__imageFill.url' => [
				'attrName' => 'subhead.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.url',
			],
			'subhead.decoration.font.textEffects__imageFill.verticalOffset' => [
				'attrName' => 'subhead.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.verticalOffset',
			],
			'subhead.decoration.font.textEffects__imageFill.width' => [
				'attrName' => 'subhead.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.width',
			],
			'subhead.decoration.font.textEffects__strokeColor' => [
				'attrName' => 'subhead.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeColor',
			],
			'subhead.decoration.font.textEffects__strokeWidth' => [
				'attrName' => 'subhead.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeWidth',
			],
			'buttonOne.decoration.font.textEffects__fillType' => [
				'attrName' => 'buttonOne.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'buttonOne.decoration.font.textEffects__gradient' => [
				'attrName' => 'buttonOne.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient',
			],
			'buttonOne.decoration.font.textEffects__gradient.type' => [
				'attrName' => 'buttonOne.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.type',
			],
			'buttonOne.decoration.font.textEffects__gradient.direction' => [
				'attrName' => 'buttonOne.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.direction',
			],
			'buttonOne.decoration.font.textEffects__gradient.directionRadial' => [
				'attrName' => 'buttonOne.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.directionRadial',
			],
			'buttonOne.decoration.font.textEffects__gradient.repeat' => [
				'attrName' => 'buttonOne.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.repeat',
			],
			'buttonOne.decoration.font.textEffects__gradient.length' => [
				'attrName' => 'buttonOne.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.length',
			],
			'buttonOne.decoration.font.textEffects__imageFill.blend' => [
				'attrName' => 'buttonOne.decoration.font.textEffects',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'imageFill.blend',
			],
			'buttonOne.decoration.font.textEffects__imageFill.height' => [
				'attrName' => 'buttonOne.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.height',
			],
			'buttonOne.decoration.font.textEffects__imageFill.horizontalOffset' => [
				'attrName' => 'buttonOne.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.horizontalOffset',
			],
			'buttonOne.decoration.font.textEffects__imageFill.position' => [
				'attrName' => 'buttonOne.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.position',
			],
			'buttonOne.decoration.font.textEffects__imageFill.repeat' => [
				'attrName' => 'buttonOne.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.repeat',
			],
			'buttonOne.decoration.font.textEffects__imageFill.size' => [
				'attrName' => 'buttonOne.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.size',
			],
			'buttonOne.decoration.font.textEffects__imageFill.url' => [
				'attrName' => 'buttonOne.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.url',
			],
			'buttonOne.decoration.font.textEffects__imageFill.verticalOffset' => [
				'attrName' => 'buttonOne.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.verticalOffset',
			],
			'buttonOne.decoration.font.textEffects__imageFill.width' => [
				'attrName' => 'buttonOne.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.width',
			],
			'buttonOne.decoration.font.textEffects__strokeColor' => [
				'attrName' => 'buttonOne.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeColor',
			],
			'buttonOne.decoration.font.textEffects__strokeWidth' => [
				'attrName' => 'buttonOne.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeWidth',
			],
			'buttonTwo.decoration.font.textEffects__fillType' => [
				'attrName' => 'buttonTwo.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'buttonTwo.decoration.font.textEffects__gradient' => [
				'attrName' => 'buttonTwo.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient',
			],
			'buttonTwo.decoration.font.textEffects__gradient.type' => [
				'attrName' => 'buttonTwo.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.type',
			],
			'buttonTwo.decoration.font.textEffects__gradient.direction' => [
				'attrName' => 'buttonTwo.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.direction',
			],
			'buttonTwo.decoration.font.textEffects__gradient.directionRadial' => [
				'attrName' => 'buttonTwo.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.directionRadial',
			],
			'buttonTwo.decoration.font.textEffects__gradient.repeat' => [
				'attrName' => 'buttonTwo.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.repeat',
			],
			'buttonTwo.decoration.font.textEffects__gradient.length' => [
				'attrName' => 'buttonTwo.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.length',
			],
			'buttonTwo.decoration.font.textEffects__imageFill.blend' => [
				'attrName' => 'buttonTwo.decoration.font.textEffects',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'imageFill.blend',
			],
			'buttonTwo.decoration.font.textEffects__imageFill.height' => [
				'attrName' => 'buttonTwo.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.height',
			],
			'buttonTwo.decoration.font.textEffects__imageFill.horizontalOffset' => [
				'attrName' => 'buttonTwo.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.horizontalOffset',
			],
			'buttonTwo.decoration.font.textEffects__imageFill.position' => [
				'attrName' => 'buttonTwo.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.position',
			],
			'buttonTwo.decoration.font.textEffects__imageFill.repeat' => [
				'attrName' => 'buttonTwo.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.repeat',
			],
			'buttonTwo.decoration.font.textEffects__imageFill.size' => [
				'attrName' => 'buttonTwo.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.size',
			],
			'buttonTwo.decoration.font.textEffects__imageFill.url' => [
				'attrName' => 'buttonTwo.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.url',
			],
			'buttonTwo.decoration.font.textEffects__imageFill.verticalOffset' => [
				'attrName' => 'buttonTwo.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.verticalOffset',
			],
			'buttonTwo.decoration.font.textEffects__imageFill.width' => [
				'attrName' => 'buttonTwo.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.width',
			],
			'buttonTwo.decoration.font.textEffects__strokeColor' => [
				'attrName' => 'buttonTwo.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeColor',
			],
			'buttonTwo.decoration.font.textEffects__strokeWidth' => [
				'attrName' => 'buttonTwo.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeWidth',
			],
		];

		$loop_preset_attrs  = LoopPresetAttrsMap::get_map( 'module.advanced.loop' );
		$image_sizing_attrs = SizingPresetAttrsMap::get_map( 'image.decoration.sizing' );
		$image_fit_attrs    = FitPresetAttrsMap::get_map( 'image.decoration.fit' );

		return array_merge( $static_attrs, $loop_preset_attrs, $image_sizing_attrs, $image_fit_attrs );
	}
}
