<?php
/**
 * Module Library:Testimonial Module Preset Attributes Map
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Testimonial;

use ET\Builder\Packages\Module\Options\Fit\FitPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Loop\LoopPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Sizing\SizingPresetAttrsMap;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


/**
 * Class TestimonialPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\Testimonial
 */
class TestimonialPresetAttrsMap {
	/**
	 * Get the preset attributes map for the Testimonial module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/testimonial' !== $module_name ) {
			return $map;
		}

		$static_attrs = [
			'author.innerContent'                          => [
				'attrName' => 'author.innerContent',
				'preset'   => 'content',
			],
			'jobTitle.innerContent'                        => [
				'attrName' => 'jobTitle.innerContent',
				'preset'   => 'content',
			],
			'company.innerContent__text'                   => [
				'attrName' => 'company.innerContent',
				'preset'   => 'content',
				'subName'  => 'text',
			],
			'content.innerContent'                         => [
				'attrName' => 'content.innerContent',
				'preset'   => 'content',
			],
			'portrait.innerContent__src'                   => [
				'attrName' => 'portrait.innerContent',
				'preset'   => 'content',
				'subName'  => 'src',
			],
			'quoteIcon.decoration.icon__show'              => [
				'attrName' => 'quoteIcon.decoration.icon',
				'preset'   => [ 'html' ],
				'subName'  => 'show',
			],
			'company.innerContent__linkUrl'                => [
				'attrName' => 'company.innerContent',
				'preset'   => 'content',
				'subName'  => 'linkUrl',
			],
			'company.innerContent__linkTarget'             => [
				'attrName' => 'company.innerContent',
				'preset'   => 'content',
				'subName'  => 'linkTarget',
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
			'quoteIcon.decoration.background__color'       => [
				'attrName' => 'quoteIcon.decoration.background',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'quoteIcon.decoration.icon__color'             => [
				'attrName' => 'quoteIcon.decoration.icon',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'quoteIcon.decoration.icon'                    => [
				'attrName' => 'quoteIcon.decoration.icon',
				'preset'   => [ 'style', 'html' ],
			],
			'quoteIcon.decoration.icon__useSize'           => [
				'attrName' => 'quoteIcon.decoration.icon',
				'preset'   => [ 'style' ],
				'subName'  => 'useSize',
			],
			'quoteIcon.decoration.icon__size'              => [
				'attrName' => 'quoteIcon.decoration.icon',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			'portrait.decoration.sizing__width'            => [
				'attrName' => 'portrait.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'width',
			],
			'portrait.decoration.sizing__height'           => [
				'attrName' => 'portrait.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'height',
			],
			'portrait.decoration.border__radius'           => [
				'attrName' => 'portrait.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'radius',
			],
			'portrait.decoration.border__styles'           => [
				'attrName' => 'portrait.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles',
			],
			'portrait.decoration.border__styles.all.width' => [
				'attrName' => 'portrait.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.width',
			],
			'portrait.decoration.border__styles.top.width' => [
				'attrName' => 'portrait.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.width',
			],
			'portrait.decoration.border__styles.right.width' => [
				'attrName' => 'portrait.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.width',
			],
			'portrait.decoration.border__styles.bottom.width' => [
				'attrName' => 'portrait.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.width',
			],
			'portrait.decoration.border__styles.left.width' => [
				'attrName' => 'portrait.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.width',
			],
			'portrait.decoration.border__styles.all.color' => [
				'attrName' => 'portrait.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.color',
			],
			'portrait.decoration.border__styles.top.color' => [
				'attrName' => 'portrait.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.color',
			],
			'portrait.decoration.border__styles.right.color' => [
				'attrName' => 'portrait.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.color',
			],
			'portrait.decoration.border__styles.bottom.color' => [
				'attrName' => 'portrait.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.color',
			],
			'portrait.decoration.border__styles.left.color' => [
				'attrName' => 'portrait.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.color',
			],
			'portrait.decoration.border__styles.all.style' => [
				'attrName' => 'portrait.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.all.style',
			],
			'portrait.decoration.border__styles.top.style' => [
				'attrName' => 'portrait.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.top.style',
			],
			'portrait.decoration.border__styles.right.style' => [
				'attrName' => 'portrait.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.right.style',
			],
			'portrait.decoration.border__styles.bottom.style' => [
				'attrName' => 'portrait.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.bottom.style',
			],
			'portrait.decoration.border__styles.left.style' => [
				'attrName' => 'portrait.decoration.border',
				'preset'   => [ 'style' ],
				'subName'  => 'styles.left.style',
			],
			'portrait.decoration.boxShadow__style'         => [
				'attrName' => 'portrait.decoration.boxShadow',
				'preset'   => [ 'html', 'style' ],
				'subName'  => 'style',
			],
			'portrait.decoration.boxShadow__horizontal'    => [
				'attrName' => 'portrait.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'horizontal',
			],
			'portrait.decoration.boxShadow__vertical'      => [
				'attrName' => 'portrait.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'vertical',
			],
			'portrait.decoration.boxShadow__blur'          => [
				'attrName' => 'portrait.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'blur',
			],
			'portrait.decoration.boxShadow__spread'        => [
				'attrName' => 'portrait.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'spread',
			],
			'portrait.decoration.boxShadow__color'         => [
				'attrName' => 'portrait.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'color',
			],
			'portrait.decoration.boxShadow__position'      => [
				'attrName' => 'portrait.decoration.boxShadow',
				'preset'   => [ 'html', 'style' ],
				'subName'  => 'position',
			],
			'portrait.decoration.filters__hueRotate'       => [
				'attrName' => 'portrait.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'hueRotate',
			],
			'portrait.decoration.filters__saturate'        => [
				'attrName' => 'portrait.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'saturate',
			],
			'portrait.decoration.filters__brightness'      => [
				'attrName' => 'portrait.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'brightness',
			],
			'portrait.decoration.filters__contrast'        => [
				'attrName' => 'portrait.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'contrast',
			],
			'portrait.decoration.filters__invert'          => [
				'attrName' => 'portrait.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'invert',
			],
			'portrait.decoration.filters__sepia'           => [
				'attrName' => 'portrait.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'sepia',
			],
			'portrait.decoration.filters__opacity'         => [
				'attrName' => 'portrait.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'opacity',
			],
			'portrait.decoration.filters__blur'            => [
				'attrName' => 'portrait.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'blur',
			],
			'portrait.decoration.filters__backdropBlur'  => [
				'attrName' => 'portrait.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'backdropBlur',
			],
			'portrait.decoration.filters__backdropInvert' => [
				'attrName' => 'portrait.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'backdropInvert',
			],
			'portrait.decoration.filters__backdropSepia' => [
				'attrName' => 'portrait.decoration.filters',
				'preset'   => [ 'style' ],
				'subName'  => 'backdropSepia',
			],
			'portrait.decoration.filters__blendMode'       => [
				'attrName' => 'portrait.decoration.filters',
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
			'author.decoration.font.font__family'          => [
				'attrName' => 'author.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'family',
			],
			'author.decoration.font.font__weight'          => [
				'attrName' => 'author.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weight',
			],
			'author.decoration.font.font__style'           => [
				'attrName' => 'author.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'author.decoration.font.font__lineColor'       => [
				'attrName' => 'author.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineColor',
			],
			'author.decoration.font.font__lineStyle'       => [
				'attrName' => 'author.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineStyle',
			],
			'author.decoration.font.font__textAlign'       => [
				'attrName' => 'author.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textAlign',
			],
			'author.decoration.font.font__color'           => [
				'attrName' => 'author.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'author.decoration.font.font__size'            => [
				'attrName' => 'author.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			'author.decoration.font.font__letterSpacing'   => [
				'attrName' => 'author.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'letterSpacing',
			],
			'author.decoration.font.font__lineHeight'      => [
				'attrName' => 'author.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineHeight',
			],
			'author.decoration.font.textShadow__style'     => [
				'attrName' => 'author.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'author.decoration.font.textShadow__horizontal' => [
				'attrName' => 'author.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'horizontal',
			],
			'author.decoration.font.textShadow__vertical'  => [
				'attrName' => 'author.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'vertical',
			],
			'author.decoration.font.textShadow__blur'      => [
				'attrName' => 'author.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'blur',
			],
			'author.decoration.font.textShadow__color'     => [
				'attrName' => 'author.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'jobTitle.decoration.font.font__family'        => [
				'attrName' => 'jobTitle.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'family',
			],
			'jobTitle.decoration.font.font__weight'        => [
				'attrName' => 'jobTitle.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weight',
			],
			'jobTitle.decoration.font.font__style'         => [
				'attrName' => 'jobTitle.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'jobTitle.decoration.font.font__lineColor'     => [
				'attrName' => 'jobTitle.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineColor',
			],
			'jobTitle.decoration.font.font__lineStyle'     => [
				'attrName' => 'jobTitle.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineStyle',
			],
			'jobTitle.decoration.font.font__color'         => [
				'attrName' => 'jobTitle.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'jobTitle.decoration.font.font__size'          => [
				'attrName' => 'jobTitle.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			'jobTitle.decoration.font.font__letterSpacing' => [
				'attrName' => 'jobTitle.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'letterSpacing',
			],
			'jobTitle.decoration.font.font__lineHeight'    => [
				'attrName' => 'jobTitle.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineHeight',
			],
			'jobTitle.decoration.font.textShadow__style'   => [
				'attrName' => 'jobTitle.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'jobTitle.decoration.font.textShadow__horizontal' => [
				'attrName' => 'jobTitle.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'horizontal',
			],
			'jobTitle.decoration.font.textShadow__vertical' => [
				'attrName' => 'jobTitle.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'vertical',
			],
			'jobTitle.decoration.font.textShadow__blur'    => [
				'attrName' => 'jobTitle.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'blur',
			],
			'jobTitle.decoration.font.textShadow__color'   => [
				'attrName' => 'jobTitle.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'company.decoration.font.font__family'         => [
				'attrName' => 'company.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'family',
			],
			'company.decoration.font.font__weight'         => [
				'attrName' => 'company.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weight',
			],
			'company.decoration.font.font__style'          => [
				'attrName' => 'company.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'company.decoration.font.font__lineColor'      => [
				'attrName' => 'company.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineColor',
			],
			'company.decoration.font.font__lineStyle'      => [
				'attrName' => 'company.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineStyle',
			],
			'company.decoration.font.font__color'          => [
				'attrName' => 'company.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'company.decoration.font.font__size'           => [
				'attrName' => 'company.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			'company.decoration.font.font__letterSpacing'  => [
				'attrName' => 'company.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'letterSpacing',
			],
			'company.decoration.font.font__lineHeight'     => [
				'attrName' => 'company.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineHeight',
			],
			'company.decoration.font.textShadow__style'    => [
				'attrName' => 'company.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'style',
			],
			'company.decoration.font.textShadow__horizontal' => [
				'attrName' => 'company.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'horizontal',
			],
			'company.decoration.font.textShadow__vertical' => [
				'attrName' => 'company.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'vertical',
			],
			'company.decoration.font.textShadow__blur'     => [
				'attrName' => 'company.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'blur',
			],
			'company.decoration.font.textShadow__color'    => [
				'attrName' => 'company.decoration.font.textShadow',
				'preset'   => [ 'style' ],
				'subName'  => 'color',
			],
			'author.decoration.font.font__weightFineTune'  => [
				'attrName' => 'author.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weightFineTune',
			],
			'author.decoration.font.font__opticalSizing'   => [
				'attrName' => 'author.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'opticalSizing',
			],
			'author.decoration.font.font__capitalization'  => [
				'attrName' => 'author.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'capitalization',
			],
			'author.decoration.font.font__lineThickness'   => [
				'attrName' => 'author.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineThickness',
			],
			'author.decoration.font.font__underlineOffset' => [
				'attrName' => 'author.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'underlineOffset',
			],
			'author.decoration.font.font__textWrap'        => [
				'attrName' => 'author.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textWrap',
			],
			'author.decoration.font.font__writingMode'     => [
				'attrName' => 'author.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'writingMode',
			],
			'author.decoration.font.font__hyphens'         => [
				'attrName' => 'author.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'hyphens',
			],
			'author.decoration.font.font__columnCount'     => [
				'attrName' => 'author.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnCount',
			],
			'author.decoration.font.font__columnGap'       => [
				'attrName' => 'author.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnGap',
			],
			'company.decoration.font.font__weightFineTune' => [
				'attrName' => 'company.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weightFineTune',
			],
			'company.decoration.font.font__opticalSizing'  => [
				'attrName' => 'company.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'opticalSizing',
			],
			'company.decoration.font.font__capitalization' => [
				'attrName' => 'company.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'capitalization',
			],
			'company.decoration.font.font__lineThickness'  => [
				'attrName' => 'company.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineThickness',
			],
			'company.decoration.font.font__underlineOffset' => [
				'attrName' => 'company.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'underlineOffset',
			],
			'company.decoration.font.font__textWrap'       => [
				'attrName' => 'company.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textWrap',
			],
			'company.decoration.font.font__writingMode'    => [
				'attrName' => 'company.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'writingMode',
			],
			'company.decoration.font.font__hyphens'        => [
				'attrName' => 'company.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'hyphens',
			],
			'company.decoration.font.font__columnCount'    => [
				'attrName' => 'company.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnCount',
			],
			'company.decoration.font.font__columnGap'      => [
				'attrName' => 'company.decoration.font.font',
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
			'jobTitle.decoration.font.font__weightFineTune' => [
				'attrName' => 'jobTitle.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weightFineTune',
			],
			'jobTitle.decoration.font.font__opticalSizing' => [
				'attrName' => 'jobTitle.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'opticalSizing',
			],
			'jobTitle.decoration.font.font__capitalization' => [
				'attrName' => 'jobTitle.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'capitalization',
			],
			'jobTitle.decoration.font.font__lineThickness' => [
				'attrName' => 'jobTitle.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineThickness',
			],
			'jobTitle.decoration.font.font__underlineOffset' => [
				'attrName' => 'jobTitle.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'underlineOffset',
			],
			'jobTitle.decoration.font.font__textWrap'      => [
				'attrName' => 'jobTitle.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textWrap',
			],
			'jobTitle.decoration.font.font__writingMode'   => [
				'attrName' => 'jobTitle.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'writingMode',
			],
			'jobTitle.decoration.font.font__hyphens'       => [
				'attrName' => 'jobTitle.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'hyphens',
			],
			'jobTitle.decoration.font.font__columnCount'   => [
				'attrName' => 'jobTitle.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnCount',
			],
			'jobTitle.decoration.font.font__columnGap'     => [
				'attrName' => 'jobTitle.decoration.font.font',
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
			'author.decoration.font.textEffects__strokePosition' => [
				'attrName' => 'author.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokePosition',
			],
			'company.decoration.font.textEffects__strokePosition' => [
				'attrName' => 'company.decoration.font.textEffects',
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
			'jobTitle.decoration.font.textEffects__strokePosition' => [
				'attrName' => 'jobTitle.decoration.font.textEffects',
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
			'author.decoration.font.textEffects__fillType' => [
				'attrName' => 'author.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'author.decoration.font.textEffects__gradient' => [
				'attrName' => 'author.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient',
			],
			'author.decoration.font.textEffects__gradient.type' => [
				'attrName' => 'author.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.type',
			],
			'author.decoration.font.textEffects__gradient.direction' => [
				'attrName' => 'author.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.direction',
			],
			'author.decoration.font.textEffects__gradient.directionRadial' => [
				'attrName' => 'author.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.directionRadial',
			],
			'author.decoration.font.textEffects__gradient.repeat' => [
				'attrName' => 'author.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.repeat',
			],
			'author.decoration.font.textEffects__gradient.length' => [
				'attrName' => 'author.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.length',
			],
			'author.decoration.font.textEffects__imageFill.blend' => [
				'attrName' => 'author.decoration.font.textEffects',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'imageFill.blend',
			],
			'author.decoration.font.textEffects__imageFill.height' => [
				'attrName' => 'author.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.height',
			],
			'author.decoration.font.textEffects__imageFill.horizontalOffset' => [
				'attrName' => 'author.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.horizontalOffset',
			],
			'author.decoration.font.textEffects__imageFill.position' => [
				'attrName' => 'author.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.position',
			],
			'author.decoration.font.textEffects__imageFill.repeat' => [
				'attrName' => 'author.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.repeat',
			],
			'author.decoration.font.textEffects__imageFill.size' => [
				'attrName' => 'author.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.size',
			],
			'author.decoration.font.textEffects__imageFill.url' => [
				'attrName' => 'author.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.url',
			],
			'author.decoration.font.textEffects__imageFill.verticalOffset' => [
				'attrName' => 'author.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.verticalOffset',
			],
			'author.decoration.font.textEffects__imageFill.width' => [
				'attrName' => 'author.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.width',
			],
			'author.decoration.font.textEffects__strokeColor' => [
				'attrName' => 'author.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeColor',
			],
			'author.decoration.font.textEffects__strokeWidth' => [
				'attrName' => 'author.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeWidth',
			],
			'jobTitle.decoration.font.textEffects__fillType' => [
				'attrName' => 'jobTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'jobTitle.decoration.font.textEffects__gradient' => [
				'attrName' => 'jobTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient',
			],
			'jobTitle.decoration.font.textEffects__gradient.type' => [
				'attrName' => 'jobTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.type',
			],
			'jobTitle.decoration.font.textEffects__gradient.direction' => [
				'attrName' => 'jobTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.direction',
			],
			'jobTitle.decoration.font.textEffects__gradient.directionRadial' => [
				'attrName' => 'jobTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.directionRadial',
			],
			'jobTitle.decoration.font.textEffects__gradient.repeat' => [
				'attrName' => 'jobTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.repeat',
			],
			'jobTitle.decoration.font.textEffects__gradient.length' => [
				'attrName' => 'jobTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.length',
			],
			'jobTitle.decoration.font.textEffects__imageFill.blend' => [
				'attrName' => 'jobTitle.decoration.font.textEffects',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'imageFill.blend',
			],
			'jobTitle.decoration.font.textEffects__imageFill.height' => [
				'attrName' => 'jobTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.height',
			],
			'jobTitle.decoration.font.textEffects__imageFill.horizontalOffset' => [
				'attrName' => 'jobTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.horizontalOffset',
			],
			'jobTitle.decoration.font.textEffects__imageFill.position' => [
				'attrName' => 'jobTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.position',
			],
			'jobTitle.decoration.font.textEffects__imageFill.repeat' => [
				'attrName' => 'jobTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.repeat',
			],
			'jobTitle.decoration.font.textEffects__imageFill.size' => [
				'attrName' => 'jobTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.size',
			],
			'jobTitle.decoration.font.textEffects__imageFill.url' => [
				'attrName' => 'jobTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.url',
			],
			'jobTitle.decoration.font.textEffects__imageFill.verticalOffset' => [
				'attrName' => 'jobTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.verticalOffset',
			],
			'jobTitle.decoration.font.textEffects__imageFill.width' => [
				'attrName' => 'jobTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.width',
			],
			'jobTitle.decoration.font.textEffects__strokeColor' => [
				'attrName' => 'jobTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeColor',
			],
			'jobTitle.decoration.font.textEffects__strokeWidth' => [
				'attrName' => 'jobTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeWidth',
			],
			'company.decoration.font.textEffects__fillType' => [
				'attrName' => 'company.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'company.decoration.font.textEffects__gradient' => [
				'attrName' => 'company.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient',
			],
			'company.decoration.font.textEffects__gradient.type' => [
				'attrName' => 'company.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.type',
			],
			'company.decoration.font.textEffects__gradient.direction' => [
				'attrName' => 'company.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.direction',
			],
			'company.decoration.font.textEffects__gradient.directionRadial' => [
				'attrName' => 'company.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.directionRadial',
			],
			'company.decoration.font.textEffects__gradient.repeat' => [
				'attrName' => 'company.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.repeat',
			],
			'company.decoration.font.textEffects__gradient.length' => [
				'attrName' => 'company.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.length',
			],
			'company.decoration.font.textEffects__imageFill.blend' => [
				'attrName' => 'company.decoration.font.textEffects',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'imageFill.blend',
			],
			'company.decoration.font.textEffects__imageFill.height' => [
				'attrName' => 'company.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.height',
			],
			'company.decoration.font.textEffects__imageFill.horizontalOffset' => [
				'attrName' => 'company.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.horizontalOffset',
			],
			'company.decoration.font.textEffects__imageFill.position' => [
				'attrName' => 'company.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.position',
			],
			'company.decoration.font.textEffects__imageFill.repeat' => [
				'attrName' => 'company.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.repeat',
			],
			'company.decoration.font.textEffects__imageFill.size' => [
				'attrName' => 'company.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.size',
			],
			'company.decoration.font.textEffects__imageFill.url' => [
				'attrName' => 'company.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.url',
			],
			'company.decoration.font.textEffects__imageFill.verticalOffset' => [
				'attrName' => 'company.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.verticalOffset',
			],
			'company.decoration.font.textEffects__imageFill.width' => [
				'attrName' => 'company.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.width',
			],
			'company.decoration.font.textEffects__strokeColor' => [
				'attrName' => 'company.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeColor',
			],
			'company.decoration.font.textEffects__strokeWidth' => [
				'attrName' => 'company.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeWidth',
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
			'css__testimonialPortrait'                     => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'testimonialPortrait',
			],
			'css__testimonialDescription'                  => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'testimonialDescription',
			],
			'css__testimonialAuthor'                       => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'testimonialAuthor',
			],
			'css__testimonialMeta'                         => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'testimonialMeta',
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
		];

		$loop_preset_attrs = LoopPresetAttrsMap::get_map( 'module.advanced.loop' );

		return array_merge(
			$static_attrs,
			SizingPresetAttrsMap::get_map( 'portrait.decoration.sizing' ),
			FitPresetAttrsMap::get_map( 'portrait.decoration.fit' ),
			$loop_preset_attrs
		);
	}
}
