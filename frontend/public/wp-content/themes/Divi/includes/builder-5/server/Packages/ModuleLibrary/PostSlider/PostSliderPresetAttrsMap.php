<?php
/**
 * Module Library: PostSlider Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostSlider;

use ET\Builder\Packages\Module\Options\Fit\FitPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Sizing\SizingPresetAttrsMap;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Options\TextEffects\TextEffectsPresetAttrsMap;

/**
 * Class PostSliderPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\PostSlider
 */
class PostSliderPresetAttrsMap {
	/**
	 * Get the preset attributes map for the PostSlider module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/post-slider' !== $module_name ) {
			return $map;
		}

		$keys_to_remove = [
			'content.decoration.sizing__alignment',
			'content.decoration.sizing__minHeight',
			'content.decoration.sizing__height',
			'content.decoration.sizing__maxHeight',
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
			'button.decoration.font.font__textAlign',
			'button.decoration.font.font__lineHeight',
			'contentOverlay.decoration.border__styles',
			'contentOverlay.decoration.border__styles.all.width',
			'contentOverlay.decoration.border__styles.top.width',
			'contentOverlay.decoration.border__styles.right.width',
			'contentOverlay.decoration.border__styles.bottom.width',
			'contentOverlay.decoration.border__styles.left.width',
			'contentOverlay.decoration.border__styles.all.color',
			'contentOverlay.decoration.border__styles.top.color',
			'contentOverlay.decoration.border__styles.right.color',
			'contentOverlay.decoration.border__styles.bottom.color',
			'contentOverlay.decoration.border__styles.left.color',
			'contentOverlay.decoration.border__styles.all.style',
			'contentOverlay.decoration.border__styles.top.style',
			'contentOverlay.decoration.border__styles.right.style',
			'contentOverlay.decoration.border__styles.bottom.style',
			'contentOverlay.decoration.border__styles.left.style',
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

		foreach ( $keys_to_remove as $key ) {
			unset( $map[ $key ] );
		}

		return array_merge(
			$map,
			TextEffectsPresetAttrsMap::get_map( 'title.decoration.font' ),
			TextEffectsPresetAttrsMap::get_map( 'meta.decoration.font' ),
			TextEffectsPresetAttrsMap::get_map( 'content.decoration.bodyFont.body' ),
			TextEffectsPresetAttrsMap::get_map( 'content.decoration.bodyFont.link' ),
			TextEffectsPresetAttrsMap::get_map( 'content.decoration.bodyFont.ul' ),
			TextEffectsPresetAttrsMap::get_map( 'content.decoration.bodyFont.ol' ),
			TextEffectsPresetAttrsMap::get_map( 'content.decoration.bodyFont.quote' ),
			TextEffectsPresetAttrsMap::get_map( 'button.decoration.font' ),
			[
				'button.innerContent__text'                => [
					'attrName' => 'button.innerContent',
					'preset'   => 'content',
					'subName'  => 'text',
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
				'button.innerContent__rel'                 => [
					'attrName' => 'button.innerContent',
					'preset'   => [ 'html' ],
					'subName'  => 'rel',
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
			],
			SizingPresetAttrsMap::get_map( 'image.decoration.sizing' ),
			FitPresetAttrsMap::get_map( 'image.decoration.fit' )
		);
	}
}
