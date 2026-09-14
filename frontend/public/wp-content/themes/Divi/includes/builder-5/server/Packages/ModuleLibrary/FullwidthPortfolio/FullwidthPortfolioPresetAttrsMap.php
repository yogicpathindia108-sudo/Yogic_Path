<?php
/**
 * Module Library:FullwidthPortfolio Module Preset Attributes Map
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\FullwidthPortfolio;

use ET\Builder\Packages\Module\Options\Fit\FitPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Sizing\SizingPresetAttrsMap;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


/**
 * Class FullwidthPortfolioPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\FullwidthPortfolio
 */
class FullwidthPortfolioPresetAttrsMap {
	/**
	 * Get the preset attributes map for the FullwidthPortfolio module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/fullwidth-portfolio' !== $module_name ) {
			return $map;
		}

		$map = [
			'title.innerContent'                           => [
				'attrName' => 'title.innerContent',
				'preset'   => 'content',
			],
			'portfolio.innerContent__includedCategories'   => [
				'attrName' => 'portfolio.innerContent',
				'preset'   => 'content',
				'subName'  => 'includedCategories',
			],
			'portfolio.innerContent__postsNumber'          => [
				'attrName' => 'portfolio.innerContent',
				'preset'   => 'content',
				'subName'  => 'postsNumber',
			],
			'portfolio.innerContent__type'                 => [
				'attrName' => 'portfolio.innerContent',
				'preset'   => 'content',
				'subName'  => 'type',
			],
			'portfolio.advanced.showTitle'                 => [
				'attrName' => 'portfolio.advanced.showTitle',
				'preset'   => [ 'html' ],
			],
			'portfolio.advanced.showDate'                  => [
				'attrName' => 'portfolio.advanced.showDate',
				'preset'   => [ 'html' ],
			],
			'portfolioGrid.advanced.flexType'              => [
				'attrName' => 'portfolioGrid.advanced.flexType',
				'preset'   => [ 'html' ],
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
			'portfolio.advanced.layout'                    => [
				'attrName' => 'portfolio.advanced.layout',
				'preset'   => [ 'html' ],
			],
			'overlay.decoration.background__color'         => [
				'attrName' => 'overlay.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'overlay.decoration.icon__color'               => [
				'attrName' => 'overlay.decoration.icon',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'overlay.decoration.icon'                      => [
				'attrName' => 'overlay.decoration.icon',
				'preset'   => [ 'style', 'html' ],
			],
			'overlay.decoration.icon__size'                => [
				'attrName' => 'overlay.decoration.icon',
				'preset'   => 'content',
				'subName'  => 'size',
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
			'module.advanced.text.text__orientation'       => [
				'attrName' => 'module.advanced.text.text',
				'preset'   => [ 'html' ],
				'subName'  => 'orientation',
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
			'portfolio.decoration.font.font__headingLevel' => [
				'attrName' => 'portfolio.decoration.font.font',
				'preset'   => [ 'html' ],
				'subName'  => 'headingLevel',
			],
			'portfolio.decoration.font.font__family'       => [
				'attrName' => 'portfolio.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'family',
			],
			'portfolio.decoration.font.font__weight'       => [
				'attrName' => 'portfolio.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weight',
			],
			'portfolio.decoration.font.font__style'        => [
				'attrName' => 'portfolio.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'portfolio.decoration.font.font__lineColor'    => [
				'attrName' => 'portfolio.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineColor',
			],
			'portfolio.decoration.font.font__lineStyle'    => [
				'attrName' => 'portfolio.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineStyle',
			],
			'portfolio.decoration.font.font__textAlign'    => [
				'attrName' => 'portfolio.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textAlign',
			],
			'portfolio.decoration.font.font__color'        => [
				'attrName' => 'portfolio.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'portfolio.decoration.font.font__size'         => [
				'attrName' => 'portfolio.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			'portfolio.decoration.font.font__letterSpacing' => [
				'attrName' => 'portfolio.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'letterSpacing',
			],
			'portfolio.decoration.font.font__lineHeight'   => [
				'attrName' => 'portfolio.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineHeight',
			],
			'portfolio.decoration.font.textShadow__style'  => [
				'attrName' => 'portfolio.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'portfolio.decoration.font.textShadow__horizontal' => [
				'attrName' => 'portfolio.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'horizontal',
			],
			'portfolio.decoration.font.textShadow__vertical' => [
				'attrName' => 'portfolio.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'vertical',
			],
			'portfolio.decoration.font.textShadow__blur'   => [
				'attrName' => 'portfolio.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'blur',
			],
			'portfolio.decoration.font.textShadow__color'  => [
				'attrName' => 'portfolio.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'meta.decoration.font.font__family'            => [
				'attrName' => 'meta.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'family',
			],
			'meta.decoration.font.font__weight'            => [
				'attrName' => 'meta.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weight',
			],
			'meta.decoration.font.font__style'             => [
				'attrName' => 'meta.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'meta.decoration.font.font__lineColor'         => [
				'attrName' => 'meta.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineColor',
			],
			'meta.decoration.font.font__lineStyle'         => [
				'attrName' => 'meta.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineStyle',
			],
			'meta.decoration.font.font__textAlign'         => [
				'attrName' => 'meta.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textAlign',
			],
			'meta.decoration.font.font__color'             => [
				'attrName' => 'meta.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'meta.decoration.font.font__size'              => [
				'attrName' => 'meta.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			'meta.decoration.font.font__letterSpacing'     => [
				'attrName' => 'meta.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'letterSpacing',
			],
			'meta.decoration.font.font__lineHeight'        => [
				'attrName' => 'meta.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineHeight',
			],
			'meta.decoration.font.textShadow__style'       => [
				'attrName' => 'meta.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'meta.decoration.font.textShadow__horizontal'  => [
				'attrName' => 'meta.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'horizontal',
			],
			'meta.decoration.font.textShadow__vertical'    => [
				'attrName' => 'meta.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'vertical',
			],
			'meta.decoration.font.textShadow__blur'        => [
				'attrName' => 'meta.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'blur',
			],
			'meta.decoration.font.textShadow__color'       => [
				'attrName' => 'meta.decoration.font.textShadow',
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
			'module.advanced.autoRotate'                   => [
				'attrName' => 'module.advanced.autoRotate',
				'preset'   => [ 'script' ],
			],
			'module.advanced.autoRotateSpeed'              => [
				'attrName' => 'module.advanced.autoRotateSpeed',
				'preset'   => [ 'script' ],
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
			'css__portfolioTitle'                          => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'portfolioTitle',
			],
			'css__portfolioItem'                           => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'portfolioItem',
			],
			'css__portfolioOverlay'                        => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'portfolioOverlay',
			],
			'css__portfolioItemTitle'                      => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'portfolioItemTitle',
			],
			'css__portfolioMeta'                           => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'portfolioMeta',
			],
			'css__portfolioArrows'                         => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'portfolioArrows',
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
			'module.decoration.scroll__gridMotion.enable'  => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [ 'script' ],
				'subName'  => 'gridMotion.enable',
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
			'portfolio.decoration.font.textEffects__fillType' => [
				'attrName' => 'portfolio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'portfolio.decoration.font.textEffects__gradient' => [
				'attrName' => 'portfolio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient',
			],
			'portfolio.decoration.font.textEffects__gradient.type' => [
				'attrName' => 'portfolio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.type',
			],
			'portfolio.decoration.font.textEffects__gradient.direction' => [
				'attrName' => 'portfolio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.direction',
			],
			'portfolio.decoration.font.textEffects__gradient.directionRadial' => [
				'attrName' => 'portfolio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.directionRadial',
			],
			'portfolio.decoration.font.textEffects__gradient.repeat' => [
				'attrName' => 'portfolio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.repeat',
			],
			'portfolio.decoration.font.textEffects__gradient.length' => [
				'attrName' => 'portfolio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.length',
			],
			'portfolio.decoration.font.textEffects__imageFill.blend' => [
				'attrName' => 'portfolio.decoration.font.textEffects',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'imageFill.blend',
			],
			'portfolio.decoration.font.textEffects__imageFill.height' => [
				'attrName' => 'portfolio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.height',
			],
			'portfolio.decoration.font.textEffects__imageFill.horizontalOffset' => [
				'attrName' => 'portfolio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.horizontalOffset',
			],
			'portfolio.decoration.font.textEffects__imageFill.position' => [
				'attrName' => 'portfolio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.position',
			],
			'portfolio.decoration.font.textEffects__imageFill.repeat' => [
				'attrName' => 'portfolio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.repeat',
			],
			'portfolio.decoration.font.textEffects__imageFill.size' => [
				'attrName' => 'portfolio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.size',
			],
			'portfolio.decoration.font.textEffects__imageFill.url' => [
				'attrName' => 'portfolio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.url',
			],
			'portfolio.decoration.font.textEffects__imageFill.verticalOffset' => [
				'attrName' => 'portfolio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.verticalOffset',
			],
			'portfolio.decoration.font.textEffects__imageFill.width' => [
				'attrName' => 'portfolio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.width',
			],
			'portfolio.decoration.font.textEffects__strokeColor' => [
				'attrName' => 'portfolio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeColor',
			],
			'portfolio.decoration.font.textEffects__strokeWidth' => [
				'attrName' => 'portfolio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeWidth',
			],
			'meta.decoration.font.textEffects__fillType'   => [
				'attrName' => 'meta.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'meta.decoration.font.textEffects__gradient'   => [
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
			'meta.decoration.font.font__capitalization'    => [
				'attrName' => 'meta.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'capitalization',
			],
			'meta.decoration.font.font__columnCount'       => [
				'attrName' => 'meta.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnCount',
			],
			'meta.decoration.font.font__columnGap'         => [
				'attrName' => 'meta.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnGap',
			],
			'meta.decoration.font.font__hyphens'           => [
				'attrName' => 'meta.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'hyphens',
			],
			'meta.decoration.font.font__lineThickness'     => [
				'attrName' => 'meta.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineThickness',
			],
			'meta.decoration.font.font__opticalSizing'     => [
				'attrName' => 'meta.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'opticalSizing',
			],
			'meta.decoration.font.font__textWrap'          => [
				'attrName' => 'meta.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textWrap',
			],
			'meta.decoration.font.font__underlineOffset'   => [
				'attrName' => 'meta.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'underlineOffset',
			],
			'meta.decoration.font.font__weightFineTune'    => [
				'attrName' => 'meta.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weightFineTune',
			],
			'meta.decoration.font.font__writingMode'       => [
				'attrName' => 'meta.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'writingMode',
			],
			'meta.decoration.font.textEffects__strokePosition' => [
				'attrName' => 'meta.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokePosition',
			],
			'portfolio.decoration.font.font__capitalization' => [
				'attrName' => 'portfolio.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'capitalization',
			],
			'portfolio.decoration.font.font__columnCount'  => [
				'attrName' => 'portfolio.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnCount',
			],
			'portfolio.decoration.font.font__columnGap'    => [
				'attrName' => 'portfolio.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnGap',
			],
			'portfolio.decoration.font.font__hyphens'      => [
				'attrName' => 'portfolio.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'hyphens',
			],
			'portfolio.decoration.font.font__lineThickness' => [
				'attrName' => 'portfolio.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineThickness',
			],
			'portfolio.decoration.font.font__opticalSizing' => [
				'attrName' => 'portfolio.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'opticalSizing',
			],
			'portfolio.decoration.font.font__textWrap'     => [
				'attrName' => 'portfolio.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textWrap',
			],
			'portfolio.decoration.font.font__underlineOffset' => [
				'attrName' => 'portfolio.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'underlineOffset',
			],
			'portfolio.decoration.font.font__weightFineTune' => [
				'attrName' => 'portfolio.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weightFineTune',
			],
			'portfolio.decoration.font.font__writingMode'  => [
				'attrName' => 'portfolio.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'writingMode',
			],
			'portfolio.decoration.font.textEffects__strokePosition' => [
				'attrName' => 'portfolio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokePosition',
			],
			'title.decoration.font.font__capitalization'   => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'capitalization',
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
			'title.decoration.font.font__hyphens'          => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'hyphens',
			],
			'title.decoration.font.font__lineThickness'    => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineThickness',
			],
			'title.decoration.font.font__opticalSizing'    => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'opticalSizing',
			],
			'title.decoration.font.font__textWrap'         => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textWrap',
			],
			'title.decoration.font.font__underlineOffset'  => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'underlineOffset',
			],
			'title.decoration.font.font__weightFineTune'   => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weightFineTune',
			],
			'title.decoration.font.font__writingMode'      => [
				'attrName' => 'title.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'writingMode',
			],
			'title.decoration.font.textEffects__strokePosition' => [
				'attrName' => 'title.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokePosition',
			],
		];

		return array_merge(
			$map,
			SizingPresetAttrsMap::get_map( 'image.decoration.sizing' ),
			FitPresetAttrsMap::get_map( 'image.decoration.fit' )
		);
	}
}
