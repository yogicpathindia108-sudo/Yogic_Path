<?php
/**
 * Module Library: ContactForm Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\ContactForm;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\ModuleLibrary\FormFieldVariantPresetMapTrait;
use ET\Builder\Packages\Module\Options\FormField\FieldDecorationPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Icon\IconPresetAttrsMap;


/**
 * Class ContactFormPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\ContactForm
 */
class ContactFormPresetAttrsMap {
	use FormFieldVariantPresetMapTrait;

	/**
	 * Get the preset attributes map for the ContactForm module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/contact-form' !== $module_name ) {
			return $map;
		}

		$keys_to_remove = [
			'module.advanced.text.text__color',
			'button.decoration.font.font__lineHeight',
			'captcha.decoration.font.font__textAlign',
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

		foreach ( $keys_to_remove as $key ) {
			unset( $map[ $key ] );
		}
		$field_decoration_map = FieldDecorationPresetAttrsMap::get_map();

		$merged_map = array_merge(
			$map,
			$field_decoration_map,
			[
				'module.advanced.spamProtection__enabled'  => [
					'attrName' => 'module.advanced.spamProtection',
					'preset'   => 'content',
					'subName'  => 'enabled',
				],
				'module.advanced.spamProtection__provider' => [
					'attrName' => 'module.advanced.spamProtection',
					'preset'   => 'content',
					'subName'  => 'provider',
				],
				'module.advanced.spamProtection__account'  => [
					'attrName' => 'module.advanced.spamProtection',
					'preset'   => 'content',
					'subName'  => 'account',
				],
				'module.advanced.spamProtection__minScore' => [
					'attrName' => 'module.advanced.spamProtection',
					'preset'   => 'content',
					'subName'  => 'minScore',
				],
				'module.advanced.spamProtection__useBasicCaptcha' => [
					'attrName' => 'module.advanced.spamProtection',
					'preset'   => 'content',
					'subName'  => 'useBasicCaptcha',
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
				'button.decoration.background__color'      => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'button.decoration.background__gradient'   => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'gradient',
				],
				'button.decoration.background__gradient.enabled' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'gradient.enabled',
				],
				'button.decoration.background__gradient.type' => [
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
				'button.decoration.background__image.url'  => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'image.url',
				],
				'button.decoration.background__image.parallax.enabled' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style', 'html', 'script' ],
					'subName'  => 'image.parallax.enabled',
				],
				'button.decoration.background__image.parallax.method' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'image.parallax.method',
				],
				'button.decoration.background__image.size' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'image.size',
				],
				'button.decoration.background__image.width' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'image.width',
				],
				'button.decoration.background__image.height' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'image.height',
				],
				'button.decoration.background__image.position' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'image.position',
				],
				'button.decoration.background__image.horizontalOffset' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'image.horizontalOffset',
				],
				'button.decoration.background__image.verticalOffset' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'image.verticalOffset',
				],
				'button.decoration.background__image.repeat' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'image.repeat',
				],
				'button.decoration.background__image.blend' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'image.blend',
				],
				'button.decoration.background__video.mp4'  => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'html' ],
					'subName'  => 'video.mp4',
				],
				'button.decoration.background__video.webm' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'html' ],
					'subName'  => 'video.webm',
				],
				'button.decoration.background__video.width' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'html' ],
					'subName'  => 'video.width',
				],
				'button.decoration.background__video.height' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'html' ],
					'subName'  => 'video.height',
				],
				'button.decoration.background__video.allowPlayerPause' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'html' ],
					'subName'  => 'video.allowPlayerPause',
				],
				'button.decoration.background__video.pauseOutsideViewport' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'html' ],
					'subName'  => 'video.pauseOutsideViewport',
				],
				'button.decoration.background__pattern.style' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'pattern.style',
				],
				'button.decoration.background__pattern.enabled' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'pattern.enabled',
				],
				'button.decoration.background__pattern.color' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'pattern.color',
				],
				'button.decoration.background__pattern.transform' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'pattern.transform',
				],
				'button.decoration.background__pattern.size' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.size',
				],
				'button.decoration.background__pattern.width' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.width',
				],
				'button.decoration.background__pattern.height' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.height',
				],
				'button.decoration.background__pattern.repeatOrigin' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.repeatOrigin',
				],
				'button.decoration.background__pattern.horizontalOffset' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.horizontalOffset',
				],
				'button.decoration.background__pattern.verticalOffset' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.verticalOffset',
				],
				'button.decoration.background__pattern.repeat' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.repeat',
				],
				'button.decoration.background__pattern.blend' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'pattern.blend',
				],
				'button.decoration.background__mask.style' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'mask.style',
				],
				'button.decoration.background__mask.enabled' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'mask.enabled',
				],
				'button.decoration.background__mask.color' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'mask.color',
				],
				'button.decoration.background__mask.transform' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'mask.transform',
				],
				'button.decoration.background__mask.aspectRatio' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'mask.aspectRatio',
				],
				'button.decoration.background__mask.size'  => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'mask.size',
				],
				'button.decoration.background__mask.width' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'mask.width',
				],
				'button.decoration.background__mask.height' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'mask.height',
				],
				'button.decoration.background__mask.position' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'mask.position',
				],
				'button.decoration.background__mask.horizontalOffset' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'mask.horizontalOffset',
				],
				'button.decoration.background__mask.verticalOffset' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'mask.verticalOffset',
				],
				'button.decoration.background__mask.blend' => [
					'attrName' => 'button.decoration.background',
					'preset'   => [ 'style' ],
					'subName'  => 'mask.blend',
				],
				'button.decoration.border__styles.all.width' => [
					'attrName' => 'button.decoration.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.all.width',
				],
				'button.decoration.border__styles.top.width' => [
					'attrName' => 'button.decoration.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.top.width',
				],
				'button.decoration.border__styles.right.width' => [
					'attrName' => 'button.decoration.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.right.width',
				],
				'button.decoration.border__styles.bottom.width' => [
					'attrName' => 'button.decoration.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.bottom.width',
				],
				'button.decoration.border__styles.left.width' => [
					'attrName' => 'button.decoration.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.left.width',
				],
				'button.decoration.border__styles.all.color' => [
					'attrName' => 'button.decoration.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.all.color',
				],
				'button.decoration.border__styles.top.color' => [
					'attrName' => 'button.decoration.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.top.color',
				],
				'button.decoration.border__styles.right.color' => [
					'attrName' => 'button.decoration.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.right.color',
				],
				'button.decoration.border__styles.bottom.color' => [
					'attrName' => 'button.decoration.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.bottom.color',
				],
				'button.decoration.border__styles.left.color' => [
					'attrName' => 'button.decoration.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.left.color',
				],
				'button.decoration.border__styles.all.style' => [
					'attrName' => 'button.decoration.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.all.style',
				],
				'button.decoration.border__styles.top.style' => [
					'attrName' => 'button.decoration.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.top.style',
				],
				'button.decoration.border__styles.right.style' => [
					'attrName' => 'button.decoration.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.right.style',
				],
				'button.decoration.border__styles.bottom.style' => [
					'attrName' => 'button.decoration.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.bottom.style',
				],
				'button.decoration.border__styles.left.style' => [
					'attrName' => 'button.decoration.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.left.style',
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
				'button.decoration.border__radius'         => [
					'attrName' => 'button.decoration.border',
					'preset'   => [ 'style' ],
					'subName'  => 'radius',
				],
				'button.decoration.border__styles'         => [
					'attrName' => 'button.decoration.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles',
				],
				'button.decoration.font.font__family'      => [
					'attrName' => 'button.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'family',
				],
				'button.decoration.font.font__weight'      => [
					'attrName' => 'button.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'weight',
				],
				'button.decoration.font.font__style'       => [
					'attrName' => 'button.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'style',
				],
				'button.decoration.font.font__lineColor'   => [
					'attrName' => 'button.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'lineColor',
				],
				'button.decoration.font.font__lineStyle'   => [
					'attrName' => 'button.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'lineStyle',
				],
				'button.decoration.font.font__color'       => [
					'attrName' => 'button.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'button.decoration.font.font__size'        => [
					'attrName' => 'button.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'size',
				],
				'button.decoration.font.font__letterSpacing' => [
					'attrName' => 'button.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'letterSpacing',
				],
				'button.decoration.font.textShadow__style' => [
					'attrName' => 'button.decoration.font.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'style',
				],
				'button.decoration.font.textShadow__horizontal' => [
					'attrName' => 'button.decoration.font.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'horizontal',
				],
				'button.decoration.font.textShadow__vertical' => [
					'attrName' => 'button.decoration.font.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'vertical',
				],
				'button.decoration.font.textShadow__blur'  => [
					'attrName' => 'button.decoration.font.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'blur',
				],
				'button.decoration.font.textShadow__color' => [
					'attrName' => 'button.decoration.font.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'button.decoration.spacing__margin'        => [
					'attrName' => 'button.decoration.spacing',
					'preset'   => [ 'style' ],
					'subName'  => 'margin',
				],
				'button.decoration.spacing__padding'       => [
					'attrName' => 'button.decoration.spacing',
					'preset'   => [ 'style' ],
					'subName'  => 'padding',
				],
				'button.decoration.boxShadow__style'       => [
					'attrName' => 'button.decoration.boxShadow',
					'preset'   => [ 'html', 'style' ],
					'subName'  => 'style',
				],
				'button.decoration.boxShadow__horizontal'  => [
					'attrName' => 'button.decoration.boxShadow',
					'preset'   => [
						'html',
						'style',
					],
					'subName'  => 'horizontal',
				],
				'button.decoration.boxShadow__vertical'    => [
					'attrName' => 'button.decoration.boxShadow',
					'preset'   => [
						'html',
						'style',
					],
					'subName'  => 'vertical',
				],
				'button.decoration.boxShadow__blur'        => [
					'attrName' => 'button.decoration.boxShadow',
					'preset'   => [
						'html',
						'style',
					],
					'subName'  => 'blur',
				],
				'button.decoration.boxShadow__spread'      => [
					'attrName' => 'button.decoration.boxShadow',
					'preset'   => [
						'html',
						'style',
					],
					'subName'  => 'spread',
				],
				'button.decoration.boxShadow__color'       => [
					'attrName' => 'button.decoration.boxShadow',
					'preset'   => [
						'html',
						'style',
					],
					'subName'  => 'color',
				],
				'button.decoration.boxShadow__position'    => [
					'attrName' => 'button.decoration.boxShadow',
					'preset'   => [ 'html', 'style' ],
					'subName'  => 'position',
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
			]
		);

		$checkbox_map = self::_duplicate_map_entries_by_prefix(
			$merged_map,
			'field.',
			'checkbox.'
		);
		$checkbox_map = self::_filter_form_field_variant_map(
			$checkbox_map,
			'checkbox.'
		);
		$radio_map    = self::_duplicate_map_entries_by_prefix(
			$merged_map,
			'field.',
			'radio.'
		);
		$radio_map    = self::_filter_form_field_variant_map(
			$radio_map,
			'radio.'
		);

		$checkbox_icon_map      = IconPresetAttrsMap::get_map( 'checkbox.decoration.icon' );
		$radio_icon_map         = IconPresetAttrsMap::get_map( 'radio.decoration.icon' );
		$checkbox_icon_root_map = [
			'checkbox.decoration.icon' => [
				'attrName' => 'checkbox.decoration.icon',
				'preset'   => [ 'style', 'html' ],
			],
		];
		$radio_icon_root_map    = [
			'radio.decoration.icon'                        => [
				'attrName' => 'radio.decoration.icon',
				'preset'   => [ 'style', 'html' ],
			],
			'field.decoration.font.textEffects__fillType'  => [
				'attrName' => 'field.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'field.decoration.font.textEffects__gradient'  => [
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
			'field.decoration.labelFont.textEffects__fillType' => [
				'attrName' => 'field.decoration.labelFont.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'field.decoration.labelFont.textEffects__gradient' => [
				'attrName' => 'field.decoration.labelFont.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient',
			],
			'field.decoration.labelFont.textEffects__gradient.type' => [
				'attrName' => 'field.decoration.labelFont.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.type',
			],
			'field.decoration.labelFont.textEffects__gradient.direction' => [
				'attrName' => 'field.decoration.labelFont.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.direction',
			],
			'field.decoration.labelFont.textEffects__gradient.directionRadial' => [
				'attrName' => 'field.decoration.labelFont.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.directionRadial',
			],
			'field.decoration.labelFont.textEffects__gradient.repeat' => [
				'attrName' => 'field.decoration.labelFont.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.repeat',
			],
			'field.decoration.labelFont.textEffects__gradient.length' => [
				'attrName' => 'field.decoration.labelFont.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.length',
			],
			'field.decoration.labelFont.textEffects__imageFill.blend' => [
				'attrName' => 'field.decoration.labelFont.textEffects',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'imageFill.blend',
			],
			'field.decoration.labelFont.textEffects__imageFill.height' => [
				'attrName' => 'field.decoration.labelFont.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.height',
			],
			'field.decoration.labelFont.textEffects__imageFill.horizontalOffset' => [
				'attrName' => 'field.decoration.labelFont.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.horizontalOffset',
			],
			'field.decoration.labelFont.textEffects__imageFill.position' => [
				'attrName' => 'field.decoration.labelFont.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.position',
			],
			'field.decoration.labelFont.textEffects__imageFill.repeat' => [
				'attrName' => 'field.decoration.labelFont.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.repeat',
			],
			'field.decoration.labelFont.textEffects__imageFill.size' => [
				'attrName' => 'field.decoration.labelFont.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.size',
			],
			'field.decoration.labelFont.textEffects__imageFill.url' => [
				'attrName' => 'field.decoration.labelFont.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.url',
			],
			'field.decoration.labelFont.textEffects__imageFill.verticalOffset' => [
				'attrName' => 'field.decoration.labelFont.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.verticalOffset',
			],
			'field.decoration.labelFont.textEffects__imageFill.width' => [
				'attrName' => 'field.decoration.labelFont.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.width',
			],
			'field.decoration.labelFont.textEffects__strokeColor' => [
				'attrName' => 'field.decoration.labelFont.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeColor',
			],
			'field.decoration.labelFont.textEffects__strokeWidth' => [
				'attrName' => 'field.decoration.labelFont.textEffects',
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
			'checkbox.decoration.font.textEffects__fillType' => [
				'attrName' => 'checkbox.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'checkbox.decoration.font.textEffects__gradient' => [
				'attrName' => 'checkbox.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient',
			],
			'checkbox.decoration.font.textEffects__gradient.type' => [
				'attrName' => 'checkbox.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.type',
			],
			'checkbox.decoration.font.textEffects__gradient.direction' => [
				'attrName' => 'checkbox.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.direction',
			],
			'checkbox.decoration.font.textEffects__gradient.directionRadial' => [
				'attrName' => 'checkbox.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.directionRadial',
			],
			'checkbox.decoration.font.textEffects__gradient.repeat' => [
				'attrName' => 'checkbox.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.repeat',
			],
			'checkbox.decoration.font.textEffects__gradient.length' => [
				'attrName' => 'checkbox.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.length',
			],
			'checkbox.decoration.font.textEffects__imageFill.blend' => [
				'attrName' => 'checkbox.decoration.font.textEffects',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'imageFill.blend',
			],
			'checkbox.decoration.font.textEffects__imageFill.height' => [
				'attrName' => 'checkbox.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.height',
			],
			'checkbox.decoration.font.textEffects__imageFill.horizontalOffset' => [
				'attrName' => 'checkbox.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.horizontalOffset',
			],
			'checkbox.decoration.font.textEffects__imageFill.position' => [
				'attrName' => 'checkbox.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.position',
			],
			'checkbox.decoration.font.textEffects__imageFill.repeat' => [
				'attrName' => 'checkbox.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.repeat',
			],
			'checkbox.decoration.font.textEffects__imageFill.size' => [
				'attrName' => 'checkbox.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.size',
			],
			'checkbox.decoration.font.textEffects__imageFill.url' => [
				'attrName' => 'checkbox.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.url',
			],
			'checkbox.decoration.font.textEffects__imageFill.verticalOffset' => [
				'attrName' => 'checkbox.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.verticalOffset',
			],
			'checkbox.decoration.font.textEffects__imageFill.width' => [
				'attrName' => 'checkbox.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.width',
			],
			'checkbox.decoration.font.textEffects__strokeColor' => [
				'attrName' => 'checkbox.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeColor',
			],
			'checkbox.decoration.font.textEffects__strokeWidth' => [
				'attrName' => 'checkbox.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeWidth',
			],
			'radio.decoration.font.textEffects__fillType'  => [
				'attrName' => 'radio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'radio.decoration.font.textEffects__gradient'  => [
				'attrName' => 'radio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient',
			],
			'radio.decoration.font.textEffects__gradient.type' => [
				'attrName' => 'radio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.type',
			],
			'radio.decoration.font.textEffects__gradient.direction' => [
				'attrName' => 'radio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.direction',
			],
			'radio.decoration.font.textEffects__gradient.directionRadial' => [
				'attrName' => 'radio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.directionRadial',
			],
			'radio.decoration.font.textEffects__gradient.repeat' => [
				'attrName' => 'radio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.repeat',
			],
			'radio.decoration.font.textEffects__gradient.length' => [
				'attrName' => 'radio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.length',
			],
			'radio.decoration.font.textEffects__imageFill.blend' => [
				'attrName' => 'radio.decoration.font.textEffects',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'imageFill.blend',
			],
			'radio.decoration.font.textEffects__imageFill.height' => [
				'attrName' => 'radio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.height',
			],
			'radio.decoration.font.textEffects__imageFill.horizontalOffset' => [
				'attrName' => 'radio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.horizontalOffset',
			],
			'radio.decoration.font.textEffects__imageFill.position' => [
				'attrName' => 'radio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.position',
			],
			'radio.decoration.font.textEffects__imageFill.repeat' => [
				'attrName' => 'radio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.repeat',
			],
			'radio.decoration.font.textEffects__imageFill.size' => [
				'attrName' => 'radio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.size',
			],
			'radio.decoration.font.textEffects__imageFill.url' => [
				'attrName' => 'radio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.url',
			],
			'radio.decoration.font.textEffects__imageFill.verticalOffset' => [
				'attrName' => 'radio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.verticalOffset',
			],
			'radio.decoration.font.textEffects__imageFill.width' => [
				'attrName' => 'radio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.width',
			],
			'radio.decoration.font.textEffects__strokeColor' => [
				'attrName' => 'radio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeColor',
			],
			'radio.decoration.font.textEffects__strokeWidth' => [
				'attrName' => 'radio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeWidth',
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
			'captcha.decoration.font.textEffects__fillType' => [
				'attrName' => 'captcha.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'captcha.decoration.font.textEffects__gradient' => [
				'attrName' => 'captcha.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient',
			],
			'captcha.decoration.font.textEffects__gradient.type' => [
				'attrName' => 'captcha.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.type',
			],
			'captcha.decoration.font.textEffects__gradient.direction' => [
				'attrName' => 'captcha.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.direction',
			],
			'captcha.decoration.font.textEffects__gradient.directionRadial' => [
				'attrName' => 'captcha.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.directionRadial',
			],
			'captcha.decoration.font.textEffects__gradient.repeat' => [
				'attrName' => 'captcha.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.repeat',
			],
			'captcha.decoration.font.textEffects__gradient.length' => [
				'attrName' => 'captcha.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.length',
			],
			'captcha.decoration.font.textEffects__imageFill.blend' => [
				'attrName' => 'captcha.decoration.font.textEffects',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'imageFill.blend',
			],
			'captcha.decoration.font.textEffects__imageFill.height' => [
				'attrName' => 'captcha.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.height',
			],
			'captcha.decoration.font.textEffects__imageFill.horizontalOffset' => [
				'attrName' => 'captcha.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.horizontalOffset',
			],
			'captcha.decoration.font.textEffects__imageFill.position' => [
				'attrName' => 'captcha.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.position',
			],
			'captcha.decoration.font.textEffects__imageFill.repeat' => [
				'attrName' => 'captcha.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.repeat',
			],
			'captcha.decoration.font.textEffects__imageFill.size' => [
				'attrName' => 'captcha.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.size',
			],
			'captcha.decoration.font.textEffects__imageFill.url' => [
				'attrName' => 'captcha.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.url',
			],
			'captcha.decoration.font.textEffects__imageFill.verticalOffset' => [
				'attrName' => 'captcha.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.verticalOffset',
			],
			'captcha.decoration.font.textEffects__imageFill.width' => [
				'attrName' => 'captcha.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.width',
			],
			'captcha.decoration.font.textEffects__strokeColor' => [
				'attrName' => 'captcha.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeColor',
			],
			'captcha.decoration.font.textEffects__strokeWidth' => [
				'attrName' => 'captcha.decoration.font.textEffects',
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
		];

		return array_merge(
			$merged_map,
			$checkbox_map,
			$checkbox_icon_root_map,
			$checkbox_icon_map,
			$radio_map,
			$radio_icon_root_map,
			$radio_icon_map
		);
	}
}
