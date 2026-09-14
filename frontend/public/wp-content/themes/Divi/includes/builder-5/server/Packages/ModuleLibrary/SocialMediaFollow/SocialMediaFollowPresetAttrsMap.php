<?php
/**
 * Module Library: SocialMediaFollow Module Preset Attributes Map
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\SocialMediaFollow;

use ET\Builder\Packages\Module\Options\Loop\LoopPresetAttrsMap;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


/**
 * Class SocialMediaFollowPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\SocialMediaFollow
 */
class SocialMediaFollowPresetAttrsMap {
	/**
	 * Get the preset attributes map for the SocialMediaFollow module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/social-media-follow' !== $module_name ) {
			return $map;
		}

		$static_attrs = [
			'button.innerContent__linkTarget'              => [
				'attrName' => 'button.innerContent',
				'preset'   => [
					'html',
				],
				'subName'  => 'linkTarget',
			],
			'socialNetwork.advanced.followButton'          => [
				'attrName' => 'socialNetwork.advanced.followButton',
				'preset'   => [
					'html',
				],
			],
			'module.decoration.background__color'          => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'color',
			],
			'module.decoration.background__gradient'       => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
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
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'image.url',
			],
			'module.decoration.background__image.parallax.enabled' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
					'html',
					'script',
				],
				'subName'  => 'image.parallax.enabled',
			],
			'module.decoration.background__image.parallax.method' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'image.parallax.method',
			],
			'module.decoration.background__image.size'     => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'image.size',
			],
			'module.decoration.background__image.width'    => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'image.width',
			],
			'module.decoration.background__image.height'   => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'image.height',
			],
			'module.decoration.background__image.position' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'image.position',
			],
			'module.decoration.background__image.horizontalOffset' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'image.horizontalOffset',
			],
			'module.decoration.background__image.verticalOffset' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'image.verticalOffset',
			],
			'module.decoration.background__image.repeat'   => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'image.repeat',
			],
			'module.decoration.background__image.blend'    => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'image.blend',
			],
			'module.decoration.background__video.mp4'      => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'html',
				],
				'subName'  => 'video.mp4',
			],
			'module.decoration.background__video.webm'     => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'html',
				],
				'subName'  => 'video.webm',
			],
			'module.decoration.background__video.width'    => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'html',
				],
				'subName'  => 'video.width',
			],
			'module.decoration.background__video.height'   => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'html',
				],
				'subName'  => 'video.height',
			],
			'module.decoration.background__video.allowPlayerPause' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'html',
				],
				'subName'  => 'video.allowPlayerPause',
			],
			'module.decoration.background__video.pauseOutsideViewport' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'html',
				],
				'subName'  => 'video.pauseOutsideViewport',
			],
			'module.decoration.background__pattern.style'  => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'pattern.style',
			],
			'module.decoration.background__pattern.enabled' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'pattern.enabled',
			],
			'module.decoration.background__pattern.color'  => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'pattern.color',
			],
			'module.decoration.background__pattern.transform' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'pattern.transform',
			],
			'module.decoration.background__pattern.size'   => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'pattern.size',
			],
			'module.decoration.background__pattern.width'  => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'pattern.width',
			],
			'module.decoration.background__pattern.height' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'pattern.height',
			],
			'module.decoration.background__pattern.repeatOrigin' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'pattern.repeatOrigin',
			],
			'module.decoration.background__pattern.horizontalOffset' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'pattern.horizontalOffset',
			],
			'module.decoration.background__pattern.verticalOffset' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'pattern.verticalOffset',
			],
			'module.decoration.background__pattern.repeat' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'pattern.repeat',
			],
			'module.decoration.background__pattern.blend'  => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'pattern.blend',
			],
			'module.decoration.background__mask.style'     => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'mask.style',
			],
			'module.decoration.background__mask.enabled'   => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'mask.enabled',
			],
			'module.decoration.background__mask.color'     => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'mask.color',
			],
			'module.decoration.background__mask.transform' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'mask.transform',
			],
			'module.decoration.background__mask.aspectRatio' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'mask.aspectRatio',
			],
			'module.decoration.background__mask.size'      => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'mask.size',
			],
			'module.decoration.background__mask.width'     => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'mask.width',
			],
			'module.decoration.background__mask.height'    => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'mask.height',
			],
			'module.decoration.background__mask.position'  => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'mask.position',
			],
			'module.decoration.background__mask.horizontalOffset' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'mask.horizontalOffset',
			],
			'module.decoration.background__mask.verticalOffset' => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'mask.verticalOffset',
			],
			'module.decoration.background__mask.blend'     => [
				'attrName' => 'module.decoration.background',
				'preset'   => [
					'style',
				],
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
				'preset'   => [
					'style',
				],
				'subName'  => 'orientation',
			],
			'icon.advanced.color'                          => [
				'attrName' => 'icon.advanced.color',
				'preset'   => [
					'style',
				],
			],
			'icon.advanced.size__useSize'                  => [
				'attrName' => 'icon.advanced.size',
				'preset'   => [
					'style',
				],
				'subName'  => 'useSize',
			],
			'icon.advanced.size__size'                     => [
				'attrName' => 'icon.advanced.size',
				'preset'   => [
					'style',
				],
				'subName'  => 'size',
			],
			'module.advanced.text.text__color'             => [
				'attrName' => 'module.advanced.text.text',
				'preset'   => [
					'html',
				],
				'subName'  => 'color',
			],
			'module.advanced.text.textShadow__style'       => [
				'attrName' => 'module.advanced.text.textShadow',
				'preset'   => [
					'style',
				],
				'subName'  => 'style',
			],
			'module.advanced.text.textShadow__horizontal'  => [
				'attrName' => 'module.advanced.text.textShadow',
				'preset'   => [
					'style',
				],
				'subName'  => 'horizontal',
			],
			'module.advanced.text.textShadow__vertical'    => [
				'attrName' => 'module.advanced.text.textShadow',
				'preset'   => [
					'style',
				],
				'subName'  => 'vertical',
			],
			'module.advanced.text.textShadow__blur'        => [
				'attrName' => 'module.advanced.text.textShadow',
				'preset'   => [
					'style',
				],
				'subName'  => 'blur',
			],
			'module.advanced.text.textShadow__color'       => [
				'attrName' => 'module.advanced.text.textShadow',
				'preset'   => [
					'style',
				],
				'subName'  => 'color',
			],
			'button.decoration.background__color'          => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'color',
			],
			'button.decoration.background__gradient'       => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'gradient',
			],
			'button.decoration.background__gradient.enabled' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.enabled',
			],
			'button.decoration.background__gradient.type'  => [
				'attrName' => 'button.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.type',
			],
			'button.decoration.background__gradient.direction' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.direction',
			],
			'button.decoration.background__gradient.directionRadial' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.directionRadial',
			],
			'button.decoration.background__gradient.repeat' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.repeat',
			],
			'button.decoration.background__gradient.length' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.length',
			],
			'button.decoration.background__gradient.overlaysImage' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'gradient.overlaysImage',
			],
			'button.decoration.background__image.url'      => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'image.url',
			],
			'button.decoration.background__image.parallax.enabled' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
					'html',
					'script',
				],
				'subName'  => 'image.parallax.enabled',
			],
			'button.decoration.background__image.parallax.method' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'image.parallax.method',
			],
			'button.decoration.background__image.size'     => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'image.size',
			],
			'button.decoration.background__image.width'    => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'image.width',
			],
			'button.decoration.background__image.height'   => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'image.height',
			],
			'button.decoration.background__image.position' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'image.position',
			],
			'button.decoration.background__image.horizontalOffset' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'image.horizontalOffset',
			],
			'button.decoration.background__image.verticalOffset' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'image.verticalOffset',
			],
			'button.decoration.background__image.repeat'   => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'image.repeat',
			],
			'button.decoration.background__image.blend'    => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'image.blend',
			],
			'button.decoration.background__video.mp4'      => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'html',
				],
				'subName'  => 'video.mp4',
			],
			'button.decoration.background__video.webm'     => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'html',
				],
				'subName'  => 'video.webm',
			],
			'button.decoration.background__video.width'    => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'html',
				],
				'subName'  => 'video.width',
			],
			'button.decoration.background__video.height'   => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'html',
				],
				'subName'  => 'video.height',
			],
			'button.decoration.background__video.allowPlayerPause' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'html',
				],
				'subName'  => 'video.allowPlayerPause',
			],
			'button.decoration.background__video.pauseOutsideViewport' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'html',
				],
				'subName'  => 'video.pauseOutsideViewport',
			],
			'button.decoration.background__pattern.style'  => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'pattern.style',
			],
			'button.decoration.background__pattern.enabled' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'pattern.enabled',
			],
			'button.decoration.background__pattern.color'  => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'pattern.color',
			],
			'button.decoration.background__pattern.transform' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'pattern.transform',
			],
			'button.decoration.background__pattern.size'   => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'pattern.size',
			],
			'button.decoration.background__pattern.width'  => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'pattern.width',
			],
			'button.decoration.background__pattern.height' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'pattern.height',
			],
			'button.decoration.background__pattern.repeatOrigin' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'pattern.repeatOrigin',
			],
			'button.decoration.background__pattern.horizontalOffset' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'pattern.horizontalOffset',
			],
			'button.decoration.background__pattern.verticalOffset' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'pattern.verticalOffset',
			],
			'button.decoration.background__pattern.repeat' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'pattern.repeat',
			],
			'button.decoration.background__pattern.blend'  => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'pattern.blend',
			],
			'button.decoration.background__mask.style'     => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'mask.style',
			],
			'button.decoration.background__mask.enabled'   => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'mask.enabled',
			],
			'button.decoration.background__mask.color'     => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'mask.color',
			],
			'button.decoration.background__mask.transform' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'mask.transform',
			],
			'button.decoration.background__mask.aspectRatio' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'mask.aspectRatio',
			],
			'button.decoration.background__mask.size'      => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'mask.size',
			],
			'button.decoration.background__mask.width'     => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'mask.width',
			],
			'button.decoration.background__mask.height'    => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'mask.height',
			],
			'button.decoration.background__mask.position'  => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'mask.position',
			],
			'button.decoration.background__mask.horizontalOffset' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'mask.horizontalOffset',
			],
			'button.decoration.background__mask.verticalOffset' => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'mask.verticalOffset',
			],
			'button.decoration.background__mask.blend'     => [
				'attrName' => 'button.decoration.background',
				'preset'   => [
					'style',
				],
				'subName'  => 'mask.blend',
			],
			'button.decoration.border__radius'             => [
				'attrName' => 'button.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'radius',
			],
			'button.decoration.border__styles'             => [
				'attrName' => 'button.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles',
			],
			'button.decoration.border__styles.all.width'   => [
				'attrName' => 'button.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.all.width',
			],
			'button.decoration.border__styles.top.width'   => [
				'attrName' => 'button.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.top.width',
			],
			'button.decoration.border__styles.right.width' => [
				'attrName' => 'button.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.right.width',
			],
			'button.decoration.border__styles.bottom.width' => [
				'attrName' => 'button.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.bottom.width',
			],
			'button.decoration.border__styles.left.width'  => [
				'attrName' => 'button.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.left.width',
			],
			'button.decoration.border__styles.all.color'   => [
				'attrName' => 'button.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.all.color',
			],
			'button.decoration.border__styles.top.color'   => [
				'attrName' => 'button.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.top.color',
			],
			'button.decoration.border__styles.right.color' => [
				'attrName' => 'button.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.right.color',
			],
			'button.decoration.border__styles.bottom.color' => [
				'attrName' => 'button.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.bottom.color',
			],
			'button.decoration.border__styles.left.color'  => [
				'attrName' => 'button.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.left.color',
			],
			'button.decoration.border__styles.all.style'   => [
				'attrName' => 'button.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.all.style',
			],
			'button.decoration.border__styles.top.style'   => [
				'attrName' => 'button.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.top.style',
			],
			'button.decoration.border__styles.right.style' => [
				'attrName' => 'button.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.right.style',
			],
			'button.decoration.border__styles.bottom.style' => [
				'attrName' => 'button.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.bottom.style',
			],
			'button.decoration.border__styles.left.style'  => [
				'attrName' => 'button.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.left.style',
			],
			'button.decoration.font.font__family'          => [
				'attrName' => 'button.decoration.font.font',
				'preset'   => [
					'style',
				],
				'subName'  => 'family',
			],
			'button.decoration.font.font__weight'          => [
				'attrName' => 'button.decoration.font.font',
				'preset'   => [
					'style',
				],
				'subName'  => 'weight',
			],
			'button.decoration.font.font__style'           => [
				'attrName' => 'button.decoration.font.font',
				'preset'   => [
					'style',
				],
				'subName'  => 'style',
			],
			'button.decoration.font.font__lineColor'       => [
				'attrName' => 'button.decoration.font.font',
				'preset'   => [
					'style',
				],
				'subName'  => 'lineColor',
			],
			'button.decoration.font.font__lineStyle'       => [
				'attrName' => 'button.decoration.font.font',
				'preset'   => [
					'style',
				],
				'subName'  => 'lineStyle',
			],
			'button.decoration.font.font__color'           => [
				'attrName' => 'button.decoration.font.font',
				'preset'   => [
					'style',
				],
				'subName'  => 'color',
			],
			'button.decoration.font.font__size'            => [
				'attrName' => 'button.decoration.font.font',
				'preset'   => [
					'style',
				],
				'subName'  => 'size',
			],
			'button.decoration.font.font__letterSpacing'   => [
				'attrName' => 'button.decoration.font.font',
				'preset'   => [
					'style',
				],
				'subName'  => 'letterSpacing',
			],
			'button.decoration.font.font__lineHeight'      => [
				'attrName' => 'button.decoration.font.font',
				'preset'   => [
					'style',
				],
				'subName'  => 'lineHeight',
			],
			'button.decoration.font.textShadow__style'     => [
				'attrName' => 'button.decoration.font.textShadow',
				'preset'   => [
					'style',
				],
				'subName'  => 'style',
			],
			'button.decoration.font.textShadow__horizontal' => [
				'attrName' => 'button.decoration.font.textShadow',
				'preset'   => [
					'style',
				],
				'subName'  => 'horizontal',
			],
			'button.decoration.font.textShadow__vertical'  => [
				'attrName' => 'button.decoration.font.textShadow',
				'preset'   => [
					'style',
				],
				'subName'  => 'vertical',
			],
			'button.decoration.font.textShadow__blur'      => [
				'attrName' => 'button.decoration.font.textShadow',
				'preset'   => [
					'style',
				],
				'subName'  => 'blur',
			],
			'button.decoration.font.textShadow__color'     => [
				'attrName' => 'button.decoration.font.textShadow',
				'preset'   => [
					'style',
				],
				'subName'  => 'color',
			],
			'button.decoration.spacing__margin'            => [
				'attrName' => 'button.decoration.spacing',
				'preset'   => [
					'style',
				],
				'subName'  => 'margin',
			],
			'button.decoration.spacing__padding'           => [
				'attrName' => 'button.decoration.spacing',
				'preset'   => [
					'style',
				],
				'subName'  => 'padding',
			],
			'button.decoration.boxShadow__style'           => [
				'attrName' => 'button.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'style',
			],
			'button.decoration.boxShadow__horizontal'      => [
				'attrName' => 'button.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'horizontal',
			],
			'button.decoration.boxShadow__vertical'        => [
				'attrName' => 'button.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'vertical',
			],
			'button.decoration.boxShadow__blur'            => [
				'attrName' => 'button.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'blur',
			],
			'button.decoration.boxShadow__spread'          => [
				'attrName' => 'button.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'spread',
			],
			'button.decoration.boxShadow__color'           => [
				'attrName' => 'button.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'color',
			],
			'button.decoration.boxShadow__position'        => [
				'attrName' => 'button.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'position',
			],
			'button.decoration.sizing__width'              => [
				'attrName' => 'button.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'width',
			],
			'button.decoration.sizing__maxWidth'           => [
				'attrName' => 'button.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'maxWidth',
			],
			'button.decoration.sizing__alignSelf'          => [
				'attrName' => 'button.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'alignSelf',
			],
			'button.decoration.sizing__alignment'          => [
				'attrName' => 'button.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'alignment',
			],
			'button.decoration.sizing__flexGrow'           => [
				'attrName' => 'button.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'flexGrow',
			],
			'button.decoration.sizing__flexShrink'         => [
				'attrName' => 'button.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'flexShrink',
			],
			'button.decoration.sizing__gridAlignSelf'      => [
				'attrName' => 'button.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridAlignSelf',
			],
			'button.decoration.sizing__gridColumnSpan'     => [
				'attrName' => 'button.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnSpan',
			],
			'button.decoration.sizing__gridColumnStart'    => [
				'attrName' => 'button.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnStart',
			],
			'button.decoration.sizing__gridJustifySelf'    => [
				'attrName' => 'button.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridJustifySelf',
			],
			'button.decoration.sizing__gridRowSpan'        => [
				'attrName' => 'button.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowSpan',
			],
			'button.decoration.sizing__gridRowStart'       => [
				'attrName' => 'button.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowStart',
			],
			'button.decoration.sizing__gridColumnEnd'      => [
				'attrName' => 'button.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridColumnEnd',
			],
			'button.decoration.sizing__gridRowEnd'         => [
				'attrName' => 'button.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'gridRowEnd',
			],
			'button.decoration.sizing__minHeight'          => [
				'attrName' => 'button.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'minHeight',
			],
			'button.decoration.sizing__size'               => [
				'attrName' => 'button.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'size',
			],
			'button.decoration.sizing__height'             => [
				'attrName' => 'button.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'height',
			],
			'button.decoration.sizing__maxHeight'          => [
				'attrName' => 'button.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'maxHeight',
			],
			'button.decoration.sizing__aspectRatio'        => [
				'attrName' => 'button.decoration.sizing',
				'preset'   => [ 'style' ],
				'subName'  => 'aspectRatio',
			],
			'button.decoration.sizing__flexType'           => [
				'attrName' => 'button.decoration.sizing',
				'preset'   => [ 'html' ],
				'subName'  => 'flexType',
			],
			'button.decoration.font.font__weightFineTune'  => [
				'attrName' => 'button.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'weightFineTune',
			],
			'button.decoration.font.font__opticalSizing'   => [
				'attrName' => 'button.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'opticalSizing',
			],
			'button.decoration.font.font__capitalization'  => [
				'attrName' => 'button.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'capitalization',
			],
			'button.decoration.font.font__lineThickness'   => [
				'attrName' => 'button.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'lineThickness',
			],
			'button.decoration.font.font__underlineOffset' => [
				'attrName' => 'button.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'underlineOffset',
			],
			'button.decoration.font.font__textWrap'        => [
				'attrName' => 'button.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'textWrap',
			],
			'button.decoration.font.font__writingMode'     => [
				'attrName' => 'button.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'writingMode',
			],
			'button.decoration.font.font__hyphens'         => [
				'attrName' => 'button.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'hyphens',
			],
			'button.decoration.font.font__columnCount'     => [
				'attrName' => 'button.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnCount',
			],
			'button.decoration.font.font__columnGap'       => [
				'attrName' => 'button.decoration.font.font',
				'preset'   => [ 'style' ],
				'subName'  => 'columnGap',
			],
			'button.decoration.font.textEffects__strokePosition' => [
				'attrName' => 'button.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokePosition',
			],

			'module.decoration.sizing__width'              => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [
					'style',
				],
				'subName'  => 'width',
			],
			'module.decoration.sizing__maxWidth'           => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [
					'style',
				],
				'subName'  => 'maxWidth',
			],
			'module.decoration.sizing__flexType'           => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [
					'html',
				],
				'subName'  => 'flexType',
			],
			'module.decoration.sizing__alignSelf'          => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [
					'style',
				],
				'subName'  => 'alignSelf',
			],
			'module.decoration.sizing__flexGrow'           => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [
					'style',
				],
				'subName'  => 'flexGrow',
			],
			'module.decoration.sizing__flexShrink'         => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [
					'style',
				],
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
				'preset'   => [
					'style',
				],
				'subName'  => 'size',
			],
			'module.decoration.sizing__alignment'          => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [
					'style',
				],
				'subName'  => 'alignment',
			],
			'module.decoration.sizing__minHeight'          => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [
					'style',
				],
				'subName'  => 'minHeight',
			],
			'module.decoration.sizing__height'             => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [
					'style',
				],
				'subName'  => 'height',
			],
			'module.decoration.sizing__maxHeight'          => [
				'attrName' => 'module.decoration.sizing',
				'preset'   => [
					'style',
				],
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
				'preset'   => [
					'style',
				],
				'subName'  => 'margin',
			],
			'module.decoration.spacing__padding'           => [
				'attrName' => 'module.decoration.spacing',
				'preset'   => [
					'style',
				],
				'subName'  => 'padding',
			],
			'module.decoration.border__radius'             => [
				'attrName' => 'module.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'radius',
			],
			'module.decoration.border__styles'             => [
				'attrName' => 'module.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles',
			],
			'module.decoration.border__styles.all.width'   => [
				'attrName' => 'module.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.all.width',
			],
			'module.decoration.border__styles.top.width'   => [
				'attrName' => 'module.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.top.width',
			],
			'module.decoration.border__styles.right.width' => [
				'attrName' => 'module.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.right.width',
			],
			'module.decoration.border__styles.bottom.width' => [
				'attrName' => 'module.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.bottom.width',
			],
			'module.decoration.border__styles.left.width'  => [
				'attrName' => 'module.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.left.width',
			],
			'module.decoration.border__styles.all.color'   => [
				'attrName' => 'module.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.all.color',
			],
			'module.decoration.border__styles.top.color'   => [
				'attrName' => 'module.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.top.color',
			],
			'module.decoration.border__styles.right.color' => [
				'attrName' => 'module.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.right.color',
			],
			'module.decoration.border__styles.bottom.color' => [
				'attrName' => 'module.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.bottom.color',
			],
			'module.decoration.border__styles.left.color'  => [
				'attrName' => 'module.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.left.color',
			],
			'module.decoration.border__styles.all.style'   => [
				'attrName' => 'module.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.all.style',
			],
			'module.decoration.border__styles.top.style'   => [
				'attrName' => 'module.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.top.style',
			],
			'module.decoration.border__styles.right.style' => [
				'attrName' => 'module.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.right.style',
			],
			'module.decoration.border__styles.bottom.style' => [
				'attrName' => 'module.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.bottom.style',
			],
			'module.decoration.border__styles.left.style'  => [
				'attrName' => 'module.decoration.border',
				'preset'   => [
					'style',
				],
				'subName'  => 'styles.left.style',
			],
			'module.decoration.boxShadow__style'           => [
				'attrName' => 'module.decoration.boxShadow',
				'preset'   => [
					'html',
					'style',
				],
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
				'preset'   => [
					'html',
					'style',
				],
				'subName'  => 'position',
			],
			'module.decoration.filters__hueRotate'         => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [
					'style',
				],
				'subName'  => 'hueRotate',
			],
			'module.decoration.filters__saturate'          => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [
					'style',
				],
				'subName'  => 'saturate',
			],
			'module.decoration.filters__brightness'        => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [
					'style',
				],
				'subName'  => 'brightness',
			],
			'module.decoration.filters__contrast'          => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [
					'style',
				],
				'subName'  => 'contrast',
			],
			'module.decoration.filters__invert'            => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [
					'style',
				],
				'subName'  => 'invert',
			],
			'module.decoration.filters__sepia'             => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [
					'style',
				],
				'subName'  => 'sepia',
			],
			'module.decoration.filters__opacity'           => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [
					'style',
				],
				'subName'  => 'opacity',
			],
			'module.decoration.filters__blur'              => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [
					'style',
				],
				'subName'  => 'blur',
			],
			'module.decoration.filters__backdropBlur'  => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [
					'style',
				],
				'subName'  => 'backdropBlur',
			],
			'module.decoration.filters__backdropInvert' => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [
					'style',
				],
				'subName'  => 'backdropInvert',
			],
			'module.decoration.filters__backdropSepia' => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [
					'style',
				],
				'subName'  => 'backdropSepia',
			],
			'module.decoration.filters__blendMode'         => [
				'attrName' => 'module.decoration.filters',
				'preset'   => [
					'style',
				],
				'subName'  => 'blendMode',
			],
			'module.decoration.transform__scale'           => [
				'attrName' => 'module.decoration.transform',
				'preset'   => [
					'style',
				],
				'subName'  => 'scale',
			],
			'module.decoration.transform__translate'       => [
				'attrName' => 'module.decoration.transform',
				'preset'   => [
					'style',
				],
				'subName'  => 'translate',
			],
			'module.decoration.transform__rotate'          => [
				'attrName' => 'module.decoration.transform',
				'preset'   => [
					'style',
				],
				'subName'  => 'rotate',
			],
			'module.decoration.transform__skew'            => [
				'attrName' => 'module.decoration.transform',
				'preset'   => [
					'style',
				],
				'subName'  => 'skew',
			],
			'module.decoration.transform__origin'          => [
				'attrName' => 'module.decoration.transform',
				'preset'   => [
					'style',
				],
				'subName'  => 'origin',
			],
			'module.decoration.animation__style'           => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [
					'script',
				],
				'subName'  => 'style',
			],
			'module.decoration.animation__direction'       => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [
					'script',
				],
				'subName'  => 'direction',
			],
			'module.decoration.animation__duration'        => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [
					'script',
				],
				'subName'  => 'duration',
			],
			'module.decoration.animation__delay'           => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [
					'script',
				],
				'subName'  => 'delay',
			],
			'module.decoration.animation__intensity.slide' => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [
					'script',
				],
				'subName'  => 'intensity.slide',
			],
			'module.decoration.animation__intensity.zoom'  => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [
					'script',
				],
				'subName'  => 'intensity.zoom',
			],
			'module.decoration.animation__intensity.flip'  => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [
					'script',
				],
				'subName'  => 'intensity.flip',
			],
			'module.decoration.animation__intensity.fold'  => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [
					'script',
				],
				'subName'  => 'intensity.fold',
			],
			'module.decoration.animation__intensity.roll'  => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [
					'script',
				],
				'subName'  => 'intensity.roll',
			],
			'module.decoration.animation__startingOpacity' => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [
					'script',
				],
				'subName'  => 'startingOpacity',
			],
			'module.decoration.animation__speedCurve'      => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [
					'script',
				],
				'subName'  => 'speedCurve',
			],
			'module.decoration.animation__repeat'          => [
				'attrName' => 'module.decoration.animation',
				'preset'   => [
					'script',
				],
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
				'preset'   => [
					'html',
				],
				'subName'  => 'class',
			],
			'module.advanced.html__elementType'            => [
				'attrName' => 'module.advanced.html',
				'preset'   => [
					'html',
				],
				'subName'  => 'elementType',
			],
			'module.advanced.html__htmlAfter'              => [
				'attrName' => 'module.advanced.html',
				'preset'   => [
					'html',
				],
				'subName'  => 'htmlAfter',
			],
			'module.advanced.html__htmlBefore'             => [
				'attrName' => 'module.advanced.html',
				'preset'   => [
					'html',
				],
				'subName'  => 'htmlBefore',
			],
			'css__before'                                  => [
				'attrName' => 'css',
				'preset'   => [
					'style',
				],
				'subName'  => 'before',
			],
			'css__mainElement'                             => [
				'attrName' => 'css',
				'preset'   => [
					'style',
				],
				'subName'  => 'mainElement',
			],
			'css__after'                                   => [
				'attrName' => 'css',
				'preset'   => [
					'style',
				],
				'subName'  => 'after',
			],
			'css__freeForm'                                => [
				'attrName' => 'css',
				'preset'   => [ 'style' ],
				'subName'  => 'freeForm',
			],
			'css__socialFollow'                            => [
				'attrName' => 'css',
				'preset'   => [
					'style',
				],
				'subName'  => 'socialFollow',
			],
			'css__socialIcon'                              => [
				'attrName' => 'css',
				'preset'   => [
					'style',
				],
				'subName'  => 'socialIcon',
			],
			'css__followButton'                            => [
				'attrName' => 'css',
				'preset'   => [
					'style',
				],
				'subName'  => 'followButton',
			],
			'module.decoration.conditions'                 => [
				'attrName' => 'module.decoration.conditions',
				'preset'   => [
					'html',
				],
			],
			'module.decoration.disabledOn'                 => [
				'attrName' => 'module.decoration.disabledOn',
				'preset'   => [
					'style',
					'html',
				],
			],
			'module.decoration.interactions'               => [
				'attrName' => 'module.decoration.interactions',
				'preset'   => [
					'script',
				],
			],
			'module.decoration.overflow__x'                => [
				'attrName' => 'module.decoration.overflow',
				'preset'   => [
					'style',
				],
				'subName'  => 'x',
			],
			'module.decoration.overflow__y'                => [
				'attrName' => 'module.decoration.overflow',
				'preset'   => [
					'style',
				],
				'subName'  => 'y',
			],
			'module.decoration.transition__duration'       => [
				'attrName' => 'module.decoration.transition',
				'preset'   => [
					'style',
				],
				'subName'  => 'duration',
			],
			'module.decoration.transition__delay'          => [
				'attrName' => 'module.decoration.transition',
				'preset'   => [
					'style',
				],
				'subName'  => 'delay',
			],
			'module.decoration.transition__speedCurve'     => [
				'attrName' => 'module.decoration.transition',
				'preset'   => [
					'style',
				],
				'subName'  => 'speedCurve',
			],
			'module.decoration.layout__alignContent'       => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [
					'style',
				],
				'subName'  => 'alignContent',
			],
			'module.decoration.layout__alignItems'         => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [
					'style',
				],
				'subName'  => 'alignItems',
			],
			'module.decoration.layout__collapseEmptyColumns' => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [ 'style' ],
				'subName'  => 'collapseEmptyColumns',
			],
			'module.decoration.layout__columnGap'          => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [
					'style',
				],
				'subName'  => 'columnGap',
			],
			'module.decoration.layout__display'            => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [
					'style',
					'html',
				],
				'subName'  => 'display',
			],
			'module.decoration.layout__flexDirection'      => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [
					'style',
				],
				'subName'  => 'flexDirection',
			],
			'module.decoration.layout__flexWrap'           => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [
					'style',
				],
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
				'preset'   => [
					'style',
				],
				'subName'  => 'justifyContent',
			],
			'module.decoration.layout__rowGap'             => [
				'attrName' => 'module.decoration.layout',
				'preset'   => [
					'style',
				],
				'subName'  => 'rowGap',
			],
			'module.decoration.position__mode'             => [
				'attrName' => 'module.decoration.position',
				'preset'   => [
					'style',
				],
				'subName'  => 'mode',
			],
			'module.decoration.position__origin.relative'  => [
				'attrName' => 'module.decoration.position',
				'preset'   => [
					'style',
				],
				'subName'  => 'origin.relative',
			],
			'module.decoration.position__origin.absolute'  => [
				'attrName' => 'module.decoration.position',
				'preset'   => [
					'style',
				],
				'subName'  => 'origin.absolute',
			],
			'module.decoration.position__origin.fixed'     => [
				'attrName' => 'module.decoration.position',
				'preset'   => [
					'style',
				],
				'subName'  => 'origin.fixed',
			],
			'module.decoration.position__offset.vertical'  => [
				'attrName' => 'module.decoration.position',
				'preset'   => [
					'style',
				],
				'subName'  => 'offset.vertical',
			],
			'module.decoration.position__offset.horizontal' => [
				'attrName' => 'module.decoration.position',
				'preset'   => [
					'style',
				],
				'subName'  => 'offset.horizontal',
			],
			'module.decoration.zIndex'                     => [
				'attrName' => 'module.decoration.zIndex',
				'preset'   => [
					'style',
				],
			],
			'module.decoration.scroll__verticalMotion.enable' => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [
					'script',
				],
				'subName'  => 'verticalMotion.enable',
			],
			'module.decoration.scroll__horizontalMotion.enable' => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [
					'script',
				],
				'subName'  => 'horizontalMotion.enable',
			],
			'module.decoration.scroll__fade.enable'        => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [
					'script',
				],
				'subName'  => 'fade.enable',
			],
			'module.decoration.scroll__scaling.enable'     => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [
					'script',
				],
				'subName'  => 'scaling.enable',
			],
			'module.decoration.scroll__rotating.enable'    => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [
					'script',
				],
				'subName'  => 'rotating.enable',
			],
			'module.decoration.scroll__blur.enable'        => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [
					'script',
				],
				'subName'  => 'blur.enable',
			],
			'module.decoration.scroll__verticalMotion'     => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [
					'script',
				],
				'subName'  => 'verticalMotion',
			],
			'module.decoration.scroll__horizontalMotion'   => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [
					'script',
				],
				'subName'  => 'horizontalMotion',
			],
			'module.decoration.scroll__fade'               => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [
					'script',
				],
				'subName'  => 'fade',
			],
			'module.decoration.scroll__scaling'            => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [
					'script',
				],
				'subName'  => 'scaling',
			],
			'module.decoration.scroll__rotating'           => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [
					'script',
				],
				'subName'  => 'rotating',
			],
			'module.decoration.scroll__blur'               => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [
					'script',
				],
				'subName'  => 'blur',
			],
			'module.decoration.scroll__motionTriggerStart' => [
				'attrName' => 'module.decoration.scroll',
				'preset'   => [
					'script',
				],
				'subName'  => 'motionTriggerStart',
			],
			'module.decoration.sticky__position'           => [
				'attrName' => 'module.decoration.sticky',
				'preset'   => [
					'script',
				],
				'subName'  => 'position',
			],
			'module.decoration.sticky__offset.top'         => [
				'attrName' => 'module.decoration.sticky',
				'preset'   => [
					'script',
				],
				'subName'  => 'offset.top',
			],
			'module.decoration.sticky__offset.bottom'      => [
				'attrName' => 'module.decoration.sticky',
				'preset'   => [
					'script',
				],
				'subName'  => 'offset.bottom',
			],
			'module.decoration.sticky__limit.top'          => [
				'attrName' => 'module.decoration.sticky',
				'preset'   => [
					'script',
				],
				'subName'  => 'limit.top',
			],
			'module.decoration.sticky__limit.bottom'       => [
				'attrName' => 'module.decoration.sticky',
				'preset'   => [
					'script',
				],
				'subName'  => 'limit.bottom',
			],
			'module.decoration.sticky__offset.surrounding' => [
				'attrName' => 'module.decoration.sticky',
				'preset'   => [
					'script',
				],
				'subName'  => 'offset.surrounding',
			],
			'module.decoration.sticky__transition'         => [
				'attrName' => 'module.decoration.sticky',
				'preset'   => [
					'script',
				],
				'subName'  => 'transition',
			],
			'module.decoration.attributes'                 => [
				'attrName' => 'module.decoration.attributes',
				'preset'   => [ 'html' ],
			],
			'button.decoration.button__enable'             => [
				'attrName' => 'button.decoration.button',
				'preset'   => [ 'style' ],
				'subName'  => 'enable',
			],
			'button.decoration.button__alignment'          => [
				'attrName' => 'button.decoration.button',
				'preset'   => [ 'style' ],
				'subName'  => 'alignment',
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
		];

		$loop_preset_attrs = LoopPresetAttrsMap::get_map( 'module.advanced.loop' );

		return array_merge( $static_attrs, $loop_preset_attrs );
	}
}
