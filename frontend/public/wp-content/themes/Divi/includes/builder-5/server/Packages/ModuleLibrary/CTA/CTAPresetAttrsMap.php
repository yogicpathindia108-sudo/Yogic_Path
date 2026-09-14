<?php
/**
 * Module Library:CTA Module Preset Attributes Map
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\CTA;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


/**
 * Class CTAPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\CTA
 */
class CTAPresetAttrsMap {
	/**
	 * Get the preset attributes map for the CTA module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/cta' !== $module_name ) {
			return $map;
		}

		// Keys to unset.
		$keys_to_unset = [
			'button.decoration.font.font__lineHeight',
			'button.decoration.button.innerContent__text',
			'button.decoration.button.innerContent__linkUrl',
			'button.decoration.button.innerContent__linkTarget',
			'button.decoration.button.innerContent__rel',
			'button.decoration.button.decoration.button__icon.enable',
			'button.decoration.button.decoration.button__icon.settings',
			'button.decoration.button.decoration.button__icon.color',
			'button.decoration.button.decoration.button__icon.placement',
			'button.decoration.button.decoration.button__icon.onHover',
			'button.decoration.button.decoration.button__alignment',
			'button.decoration.button.decoration.background__color',
			'button.decoration.button.decoration.background__gradient',
			'button.decoration.button.decoration.background__gradient.enabled',
			'button.decoration.button.decoration.background__gradient.type',
			'button.decoration.button.decoration.background__gradient.direction',
			'button.decoration.button.decoration.background__gradient.directionRadial',
			'button.decoration.button.decoration.background__gradient.repeat',
			'button.decoration.button.decoration.background__gradient.length',
			'button.decoration.button.decoration.background__gradient.overlaysImage',
			'button.decoration.button.decoration.background__image.url',
			'button.decoration.button.decoration.background__image.parallax.enabled',
			'button.decoration.button.decoration.background__image.parallax.method',
			'button.decoration.button.decoration.background__image.size',
			'button.decoration.button.decoration.background__image.width',
			'button.decoration.button.decoration.background__image.height',
			'button.decoration.button.decoration.background__image.position',
			'button.decoration.button.decoration.background__image.horizontalOffset',
			'button.decoration.button.decoration.background__image.verticalOffset',
			'button.decoration.button.decoration.background__image.repeat',
			'button.decoration.button.decoration.background__image.blend',
			'button.decoration.button.decoration.background__video.mp4',
			'button.decoration.button.decoration.background__video.webm',
			'button.decoration.button.decoration.background__video.width',
			'button.decoration.button.decoration.background__video.height',
			'button.decoration.button.decoration.background__video.allowPlayerPause',
			'button.decoration.button.decoration.background__video.pauseOutsideViewport',
			'button.decoration.button.decoration.background__pattern.style',
			'button.decoration.button.decoration.background__pattern.enabled',
			'button.decoration.button.decoration.background__pattern.color',
			'button.decoration.button.decoration.background__pattern.transform',
			'button.decoration.button.decoration.background__pattern.size',
			'button.decoration.button.decoration.background__pattern.width',
			'button.decoration.button.decoration.background__pattern.height',
			'button.decoration.button.decoration.background__pattern.repeatOrigin',
			'button.decoration.button.decoration.background__pattern.horizontalOffset',
			'button.decoration.button.decoration.background__pattern.verticalOffset',
			'button.decoration.button.decoration.background__pattern.repeat',
			'button.decoration.button.decoration.background__pattern.blend',
			'button.decoration.button.decoration.background__mask.style',
			'button.decoration.button.decoration.background__mask.enabled',
			'button.decoration.button.decoration.background__mask.color',
			'button.decoration.button.decoration.background__mask.transform',
			'button.decoration.button.decoration.background__mask.aspectRatio',
			'button.decoration.button.decoration.background__mask.size',
			'button.decoration.button.decoration.background__mask.width',
			'button.decoration.button.decoration.background__mask.height',
			'button.decoration.button.decoration.background__mask.position',
			'button.decoration.button.decoration.background__mask.horizontalOffset',
			'button.decoration.button.decoration.background__mask.verticalOffset',
			'button.decoration.button.decoration.background__mask.blend',
			'button.decoration.button.decoration.border__radius',
			'button.decoration.button.decoration.border__styles',
			'button.decoration.button.decoration.border__styles.all.width',
			'button.decoration.button.decoration.border__styles.top.width',
			'button.decoration.button.decoration.border__styles.right.width',
			'button.decoration.button.decoration.border__styles.bottom.width',
			'button.decoration.button.decoration.border__styles.left.width',
			'button.decoration.button.decoration.border__styles.all.color',
			'button.decoration.button.decoration.border__styles.top.color',
			'button.decoration.button.decoration.border__styles.right.color',
			'button.decoration.button.decoration.border__styles.bottom.color',
			'button.decoration.button.decoration.border__styles.left.color',
			'button.decoration.button.decoration.border__styles.all.style',
			'button.decoration.button.decoration.border__styles.top.style',
			'button.decoration.button.decoration.border__styles.right.style',
			'button.decoration.button.decoration.border__styles.bottom.style',
			'button.decoration.button.decoration.border__styles.left.style',
			'button.decoration.button.decoration.spacing__margin',
			'button.decoration.button.decoration.spacing__padding',
			'button.decoration.button.decoration.boxShadow__style',
			'button.decoration.button.decoration.boxShadow__horizontal',
			'button.decoration.button.decoration.boxShadow__vertical',
			'button.decoration.button.decoration.boxShadow__blur',
			'button.decoration.button.decoration.boxShadow__spread',
			'button.decoration.button.decoration.boxShadow__color',
			'button.decoration.button.decoration.boxShadow__position',
			'button.decoration.button.decoration.font.font__family',
			'button.decoration.button.decoration.font.font__weight',
			'button.decoration.button.decoration.font.font__style',
			'button.decoration.button.decoration.font.font__lineColor',
			'button.decoration.button.decoration.font.font__lineStyle',
			'button.decoration.button.decoration.font.font__textAlign',
			'button.decoration.button.decoration.font.font__color',
			'button.decoration.button.decoration.font.font__size',
			'button.decoration.button.decoration.font.font__letterSpacing',
			'button.decoration.button.decoration.font.font__lineHeight',
			'button.decoration.button.decoration.font.textShadow__style',
			'button.decoration.button.decoration.font.textShadow__horizontal',
			'button.decoration.button.decoration.font.textShadow__vertical',
			'button.decoration.button.decoration.font.textShadow__blur',
			'button.decoration.button.decoration.font.textShadow__color',
			'button.decoration.button.decoration.sizing__width',
			'button.decoration.button.decoration.sizing__maxWidth',
			'button.decoration.button.decoration.sizing__alignSelf',
			'button.decoration.button.decoration.sizing__alignment',
			'button.decoration.button.decoration.sizing__flexGrow',
			'button.decoration.button.decoration.sizing__flexShrink',
			'button.decoration.button.decoration.sizing__gridAlignSelf',
			'button.decoration.button.decoration.sizing__gridColumnSpan',
			'button.decoration.button.decoration.sizing__gridColumnStart',
			'button.decoration.button.decoration.sizing__gridJustifySelf',
			'button.decoration.button.decoration.sizing__gridRowSpan',
			'button.decoration.button.decoration.sizing__gridRowStart',
			'button.decoration.button.decoration.sizing__gridColumnEnd',
			'button.decoration.button.decoration.sizing__gridRowEnd',
			'button.decoration.button.decoration.sizing__minHeight',
			'button.decoration.button.decoration.sizing__size',
			'button.decoration.button.decoration.sizing__height',
			'button.decoration.button.decoration.sizing__maxHeight',
			'button.decoration.button.decoration.sizing__aspectRatio',
			'button.decoration.button.decoration.sizing__flexType',
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
			'button.decoration.button.decoration.font.font__weightFineTune',
			'button.decoration.button.decoration.font.font__opticalSizing',
			'button.decoration.button.decoration.font.font__lineThickness',
			'button.decoration.button.decoration.font.font__underlineOffset',
			'button.decoration.button.decoration.font.font__textWrap',
			'button.decoration.button.decoration.font.font__writingMode',
			'button.decoration.button.decoration.font.font__hyphens',
			'button.decoration.button.decoration.font.font__columnCount',
			'button.decoration.button.decoration.font.font__columnGap',
			'button.decoration.button.decoration.font.font__capitalization',
			'button.decoration.button.decoration.font.textEffects__strokePosition',
		];

		// Unset the keys.
		foreach ( $keys_to_unset as $key ) {
			unset( $map[ $key ] );
		}

		return array_merge(
			$map,
			[
				'title.innerContent'                       => [
					'attrName' => 'title.innerContent',
					'preset'   => 'content',
				],
				'button.innerContent__text'                => [
					'attrName' => 'button.innerContent',
					'preset'   => 'content',
					'subName'  => 'text',
				],
				'button.innerContent__linkUrl'             => [
					'attrName' => 'button.innerContent',
					'preset'   => 'content',
					'subName'  => 'linkUrl',
				],
				'button.innerContent__linkTarget'          => [
					'attrName' => 'button.innerContent',
					'preset'   => 'content',
					'subName'  => 'linkTarget',
				],
				'button.innerContent__rel'                 => [
					'attrName' => 'button.innerContent',
					'preset'   => [
						'html',
					],
					'subName'  => 'rel',
				],
				'button.decoration.sizing__width'          => [
					'attrName' => 'button.decoration.sizing',
					'preset'   => [ 'style' ],
					'subName'  => 'width',
				],
				'button.decoration.sizing__maxWidth'       => [
					'attrName' => 'button.decoration.sizing',
					'preset'   => [ 'style' ],
					'subName'  => 'maxWidth',
				],
				'button.decoration.sizing__alignSelf'      => [
					'attrName' => 'button.decoration.sizing',
					'preset'   => [ 'style' ],
					'subName'  => 'alignSelf',
				],
				'button.decoration.sizing__alignment'      => [
					'attrName' => 'button.decoration.sizing',
					'preset'   => [ 'style' ],
					'subName'  => 'alignment',
				],
				'button.decoration.sizing__flexGrow'       => [
					'attrName' => 'button.decoration.sizing',
					'preset'   => [ 'style' ],
					'subName'  => 'flexGrow',
				],
				'button.decoration.sizing__flexShrink'     => [
					'attrName' => 'button.decoration.sizing',
					'preset'   => [ 'style' ],
					'subName'  => 'flexShrink',
				],
				'button.decoration.sizing__gridAlignSelf'  => [
					'attrName' => 'button.decoration.sizing',
					'preset'   => [ 'style' ],
					'subName'  => 'gridAlignSelf',
				],
				'button.decoration.sizing__gridColumnSpan' => [
					'attrName' => 'button.decoration.sizing',
					'preset'   => [ 'style' ],
					'subName'  => 'gridColumnSpan',
				],
				'button.decoration.sizing__gridColumnStart' => [
					'attrName' => 'button.decoration.sizing',
					'preset'   => [ 'style' ],
					'subName'  => 'gridColumnStart',
				],
				'button.decoration.sizing__gridJustifySelf' => [
					'attrName' => 'button.decoration.sizing',
					'preset'   => [ 'style' ],
					'subName'  => 'gridJustifySelf',
				],
				'button.decoration.sizing__gridRowSpan'    => [
					'attrName' => 'button.decoration.sizing',
					'preset'   => [ 'style' ],
					'subName'  => 'gridRowSpan',
				],
				'button.decoration.sizing__gridRowStart'   => [
					'attrName' => 'button.decoration.sizing',
					'preset'   => [ 'style' ],
					'subName'  => 'gridRowStart',
				],
				'button.decoration.sizing__gridColumnEnd'  => [
					'attrName' => 'button.decoration.sizing',
					'preset'   => [ 'style' ],
					'subName'  => 'gridColumnEnd',
				],
				'button.decoration.sizing__gridRowEnd'     => [
					'attrName' => 'button.decoration.sizing',
					'preset'   => [ 'style' ],
					'subName'  => 'gridRowEnd',
				],
				'button.decoration.sizing__minHeight'      => [
					'attrName' => 'button.decoration.sizing',
					'preset'   => [ 'style' ],
					'subName'  => 'minHeight',
				],
				'button.decoration.sizing__size'           => [
					'attrName' => 'button.decoration.sizing',
					'preset'   => [ 'style' ],
					'subName'  => 'size',
				],
				'button.decoration.sizing__height'         => [
					'attrName' => 'button.decoration.sizing',
					'preset'   => [ 'style' ],
					'subName'  => 'height',
				],
				'button.decoration.sizing__maxHeight'      => [
					'attrName' => 'button.decoration.sizing',
					'preset'   => [ 'style' ],
					'subName'  => 'maxHeight',
				],
				'button.decoration.sizing__flexType'       => [
					'attrName' => 'button.decoration.sizing',
					'preset'   => [ 'html' ],
					'subName'  => 'flexType',
				],
				'button.decoration.button__icon.enable'    => [
					'attrName' => 'button.decoration.button',
					'preset'   => [ 'style' ],
					'subName'  => 'icon.enable',
				],
				'button.decoration.button__icon.settings'  => [
					'attrName' => 'button.decoration.button',
					'preset'   => [ 'html', 'style' ],
					'subName'  => 'icon.settings',
				],
				'button.decoration.button__icon.color'     => [
					'attrName' => 'button.decoration.button',
					'preset'   => [ 'style' ],
					'subName'  => 'icon.color',
				],
				'button.decoration.button__icon.placement' => [
					'attrName' => 'button.decoration.button',
					'preset'   => [ 'style' ],
					'subName'  => 'icon.placement',
				],
				'button.decoration.button__icon.onHover'   => [
					'attrName' => 'button.decoration.button',
					'preset'   => [ 'style' ],
					'subName'  => 'icon.onHover',
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
			]
		);
	}
}
