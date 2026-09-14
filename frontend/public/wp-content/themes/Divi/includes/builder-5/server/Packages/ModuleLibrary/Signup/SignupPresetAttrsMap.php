<?php
/**
 * Module Library: Signup Module Preset Attributes Map
 *
 * @package Divi
 * @since   ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Signup;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\ModuleLibrary\FormFieldVariantPresetMapTrait;
use ET\Builder\Packages\Module\Options\FormField\FieldDecorationPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Icon\IconPresetAttrsMap;


/**
 * Class SignupPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\Signup
 */
class SignupPresetAttrsMap {
	use FormFieldVariantPresetMapTrait;


	/**
	 * Get the preset attributes map for the Signup module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/signup' !== $module_name ) {
			return $map;
		}

		unset( $map['button.decoration.button.innerContent__text'] );
		unset( $map['button.decoration.button.innerContent__linkUrl'] );
		unset( $map['button.decoration.button.innerContent__linkTarget'] );
		unset( $map['button.decoration.button.innerContent__rel'] );
		unset( $map['button.decoration.button.decoration.button__icon.enable'] );
		unset( $map['button.decoration.button.decoration.button__icon.settings'] );
		unset( $map['button.decoration.button.decoration.button__icon.color'] );
		unset( $map['button.decoration.button.decoration.button__icon.placement'] );
		unset( $map['button.decoration.button.decoration.button__icon.onHover'] );
		unset( $map['button.decoration.button.decoration.button__alignment'] );
		unset( $map['button.decoration.button.decoration.background__color'] );
		unset( $map['button.decoration.button.decoration.background__gradient'] );
		unset( $map['button.decoration.button.decoration.background__gradient.enabled'] );
		unset( $map['button.decoration.button.decoration.background__gradient.type'] );
		unset( $map['button.decoration.button.decoration.background__gradient.direction'] );
		unset( $map['button.decoration.button.decoration.background__gradient.directionRadial'] );
		unset( $map['button.decoration.button.decoration.background__gradient.repeat'] );
		unset( $map['button.decoration.button.decoration.background__gradient.length'] );
		unset( $map['button.decoration.button.decoration.background__gradient.overlaysImage'] );
		unset( $map['button.decoration.button.decoration.background__image.url'] );
		unset( $map['button.decoration.button.decoration.background__image.parallax.enabled'] );
		unset( $map['button.decoration.button.decoration.background__image.parallax.method'] );
		unset( $map['button.decoration.button.decoration.background__image.size'] );
		unset( $map['button.decoration.button.decoration.background__image.width'] );
		unset( $map['button.decoration.button.decoration.background__image.height'] );
		unset( $map['button.decoration.button.decoration.background__image.position'] );
		unset( $map['button.decoration.button.decoration.background__image.horizontalOffset'] );
		unset( $map['button.decoration.button.decoration.background__image.verticalOffset'] );
		unset( $map['button.decoration.button.decoration.background__image.repeat'] );
		unset( $map['button.decoration.button.decoration.background__image.blend'] );
		unset( $map['button.decoration.button.decoration.background__video.mp4'] );
		unset( $map['button.decoration.button.decoration.background__video.webm'] );
		unset( $map['button.decoration.button.decoration.background__video.width'] );
		unset( $map['button.decoration.button.decoration.background__video.height'] );
		unset( $map['button.decoration.button.decoration.background__video.allowPlayerPause'] );
		unset( $map['button.decoration.button.decoration.background__video.pauseOutsideViewport'] );
		unset( $map['button.decoration.button.decoration.background__pattern.style'] );
		unset( $map['button.decoration.button.decoration.background__pattern.enabled'] );
		unset( $map['button.decoration.button.decoration.background__pattern.color'] );
		unset( $map['button.decoration.button.decoration.background__pattern.transform'] );
		unset( $map['button.decoration.button.decoration.background__pattern.size'] );
		unset( $map['button.decoration.button.decoration.background__pattern.width'] );
		unset( $map['button.decoration.button.decoration.background__pattern.height'] );
		unset( $map['button.decoration.button.decoration.background__pattern.repeatOrigin'] );
		unset( $map['button.decoration.button.decoration.background__pattern.horizontalOffset'] );
		unset( $map['button.decoration.button.decoration.background__pattern.verticalOffset'] );
		unset( $map['button.decoration.button.decoration.background__pattern.repeat'] );
		unset( $map['button.decoration.button.decoration.background__pattern.blend'] );
		unset( $map['button.decoration.button.decoration.background__mask.style'] );
		unset( $map['button.decoration.button.decoration.background__mask.enabled'] );
		unset( $map['button.decoration.button.decoration.background__mask.color'] );
		unset( $map['button.decoration.button.decoration.background__mask.transform'] );
		unset( $map['button.decoration.button.decoration.background__mask.aspectRatio'] );
		unset( $map['button.decoration.button.decoration.background__mask.size'] );
		unset( $map['button.decoration.button.decoration.background__mask.width'] );
		unset( $map['button.decoration.button.decoration.background__mask.height'] );
		unset( $map['button.decoration.button.decoration.background__mask.position'] );
		unset( $map['button.decoration.button.decoration.background__mask.horizontalOffset'] );
		unset( $map['button.decoration.button.decoration.background__mask.verticalOffset'] );
		unset( $map['button.decoration.button.decoration.background__mask.blend'] );
		unset( $map['button.decoration.button.decoration.border__radius'] );
		unset( $map['button.decoration.button.decoration.border__styles'] );
		unset( $map['button.decoration.button.decoration.border__styles.all.width'] );
		unset( $map['button.decoration.button.decoration.border__styles.top.width'] );
		unset( $map['button.decoration.button.decoration.border__styles.right.width'] );
		unset( $map['button.decoration.button.decoration.border__styles.bottom.width'] );
		unset( $map['button.decoration.button.decoration.border__styles.left.width'] );
		unset( $map['button.decoration.button.decoration.border__styles.all.color'] );
		unset( $map['button.decoration.button.decoration.border__styles.top.color'] );
		unset( $map['button.decoration.button.decoration.border__styles.right.color'] );
		unset( $map['button.decoration.button.decoration.border__styles.bottom.color'] );
		unset( $map['button.decoration.button.decoration.border__styles.left.color'] );
		unset( $map['button.decoration.button.decoration.border__styles.all.style'] );
		unset( $map['button.decoration.button.decoration.border__styles.top.style'] );
		unset( $map['button.decoration.button.decoration.border__styles.right.style'] );
		unset( $map['button.decoration.button.decoration.border__styles.bottom.style'] );
		unset( $map['button.decoration.button.decoration.border__styles.left.style'] );
		unset( $map['button.decoration.button.decoration.spacing__margin'] );
		unset( $map['button.decoration.button.decoration.spacing__padding'] );
		unset( $map['button.decoration.button.decoration.boxShadow__style'] );
		unset( $map['button.decoration.button.decoration.boxShadow__horizontal'] );
		unset( $map['button.decoration.button.decoration.boxShadow__vertical'] );
		unset( $map['button.decoration.button.decoration.boxShadow__blur'] );
		unset( $map['button.decoration.button.decoration.boxShadow__spread'] );
		unset( $map['button.decoration.button.decoration.boxShadow__color'] );
		unset( $map['button.decoration.button.decoration.boxShadow__position'] );
		unset( $map['button.decoration.button.decoration.font.font__family'] );
		unset( $map['button.decoration.button.decoration.font.font__weight'] );
		unset( $map['button.decoration.button.decoration.font.font__style'] );
		unset( $map['button.decoration.button.decoration.font.font__lineColor'] );
		unset( $map['button.decoration.button.decoration.font.font__lineStyle'] );
		unset( $map['button.decoration.button.decoration.font.font__textAlign'] );
		unset( $map['button.decoration.button.decoration.font.font__color'] );
		unset( $map['button.decoration.button.decoration.font.font__size'] );
		unset( $map['button.decoration.button.decoration.font.font__letterSpacing'] );
		unset( $map['button.decoration.button.decoration.font.font__lineHeight'] );
		unset( $map['button.decoration.button.decoration.font.textShadow__style'] );
		unset( $map['button.decoration.button.decoration.font.textShadow__horizontal'] );
		unset( $map['button.decoration.button.decoration.font.textShadow__vertical'] );
		unset( $map['button.decoration.button.decoration.font.textShadow__blur'] );
		unset( $map['button.decoration.button.decoration.font.textShadow__color'] );
		unset( $map['button.decoration.button.decoration.sizing__width'] );
		unset( $map['button.decoration.button.decoration.sizing__maxWidth'] );
		unset( $map['button.decoration.button.decoration.sizing__alignSelf'] );
		unset( $map['button.decoration.button.decoration.sizing__alignment'] );
		unset( $map['button.decoration.button.decoration.sizing__flexGrow'] );
		unset( $map['button.decoration.button.decoration.sizing__flexShrink'] );
		unset( $map['button.decoration.button.decoration.sizing__gridAlignSelf'] );
		unset( $map['button.decoration.button.decoration.sizing__gridColumnSpan'] );
		unset( $map['button.decoration.button.decoration.sizing__gridColumnStart'] );
		unset( $map['button.decoration.button.decoration.sizing__gridJustifySelf'] );
		unset( $map['button.decoration.button.decoration.sizing__gridRowSpan'] );
		unset( $map['button.decoration.button.decoration.sizing__gridRowStart'] );
		unset( $map['button.decoration.button.decoration.sizing__gridColumnEnd'] );
		unset( $map['button.decoration.button.decoration.sizing__gridRowEnd'] );
		unset( $map['button.decoration.button.decoration.sizing__minHeight'] );
		unset( $map['button.decoration.button.decoration.sizing__size'] );
		unset( $map['button.decoration.button.decoration.sizing__height'] );
		unset( $map['button.decoration.button.decoration.sizing__maxHeight'] );
		unset( $map['button.decoration.button.decoration.sizing__aspectRatio'] );
		unset( $map['button.decoration.button.decoration.sizing__flexType'] );
		unset( $map['button.decoration.button.decoration.font.textEffects__fillType'] );
		unset( $map['button.decoration.button.decoration.font.textEffects__gradient'] );
		unset( $map['button.decoration.button.decoration.font.textEffects__gradient.type'] );
		unset( $map['button.decoration.button.decoration.font.textEffects__gradient.direction'] );
		unset( $map['button.decoration.button.decoration.font.textEffects__gradient.directionRadial'] );
		unset( $map['button.decoration.button.decoration.font.textEffects__gradient.repeat'] );
		unset( $map['button.decoration.button.decoration.font.textEffects__gradient.length'] );
		unset( $map['button.decoration.button.decoration.font.textEffects__imageFill.blend'] );
		unset( $map['button.decoration.button.decoration.font.textEffects__imageFill.height'] );
		unset( $map['button.decoration.button.decoration.font.textEffects__imageFill.horizontalOffset'] );
		unset( $map['button.decoration.button.decoration.font.textEffects__imageFill.position'] );
		unset( $map['button.decoration.button.decoration.font.textEffects__imageFill.repeat'] );
		unset( $map['button.decoration.button.decoration.font.textEffects__imageFill.size'] );
		unset( $map['button.decoration.button.decoration.font.textEffects__imageFill.url'] );
		unset( $map['button.decoration.button.decoration.font.textEffects__imageFill.verticalOffset'] );
		unset( $map['button.decoration.button.decoration.font.textEffects__imageFill.width'] );
		unset( $map['button.decoration.button.decoration.font.textEffects__strokeColor'] );
		unset( $map['button.decoration.button.decoration.font.textEffects__strokeWidth'] );
		unset( $map['button.decoration.button.decoration.font.font__weightFineTune'] );
		unset( $map['button.decoration.button.decoration.font.font__opticalSizing'] );
		unset( $map['button.decoration.button.decoration.font.font__lineThickness'] );
		unset( $map['button.decoration.button.decoration.font.font__underlineOffset'] );
		unset( $map['button.decoration.button.decoration.font.font__textWrap'] );
		unset( $map['button.decoration.button.decoration.font.font__writingMode'] );
		unset( $map['button.decoration.button.decoration.font.font__hyphens'] );
		unset( $map['button.decoration.button.decoration.font.font__columnCount'] );
		unset( $map['button.decoration.button.decoration.font.font__columnGap'] );
		unset( $map['button.decoration.button.decoration.font.font__capitalization'] );
		unset( $map['button.decoration.button.decoration.font.textEffects__strokePosition'] );
		unset( $map['button.decoration.font.font__lineHeight'] );
		unset( $map['customFields.advanced.fields'] );
		unset( $map['customFields.advanced.notice'] );
		unset( $map['field.advanced.focus.font.font__size'] );
		unset( $map['field.advanced.focus.font.font__letterSpacing'] );
		unset( $map['field.advanced.focus.font.font__lineHeight'] );
		$field_decoration_map = FieldDecorationPresetAttrsMap::get_map();

		$merged_map = array_merge(
			$map,
			$field_decoration_map,
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
				'module.advanced.emailService__provider'   => [
					'attrName' => 'module.advanced.emailService',
					'preset'   => 'content',
					'subName'  => 'provider',
				],
				'module.advanced.emailService__account'    => [
					'attrName' => 'module.advanced.emailService',
					'preset'   => 'content',
					'subName'  => 'account',
				],
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
				'button.innerContent__rel'                 => [
					'attrName' => 'button.innerContent',
					'preset'   => [ 'html' ],
					'subName'  => 'rel',
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
				'field.advanced.focus.border__radius'      => [
					'attrName' => 'field.advanced.focus.border',
					'preset'   => [ 'style' ],
					'subName'  => 'radius',
				],
				'field.advanced.focus.border__styles'      => [
					'attrName' => 'field.advanced.focus.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles',
				],
				'field.advanced.focus.border__styles.all.width' => [
					'attrName' => 'field.advanced.focus.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.all.width',
				],
				'field.advanced.focus.border__styles.top.width' => [
					'attrName' => 'field.advanced.focus.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.top.width',
				],
				'field.advanced.focus.border__styles.right.width' => [
					'attrName' => 'field.advanced.focus.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.right.width',
				],
				'field.advanced.focus.border__styles.bottom.width' => [
					'attrName' => 'field.advanced.focus.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.bottom.width',
				],
				'field.advanced.focus.border__styles.left.width' => [
					'attrName' => 'field.advanced.focus.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.left.width',
				],
				'field.advanced.focus.border__styles.all.color' => [
					'attrName' => 'field.advanced.focus.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.all.color',
				],
				'field.advanced.focus.border__styles.top.color' => [
					'attrName' => 'field.advanced.focus.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.top.color',
				],
				'field.advanced.focus.border__styles.right.color' => [
					'attrName' => 'field.advanced.focus.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.right.color',
				],
				'field.advanced.focus.border__styles.bottom.color' => [
					'attrName' => 'field.advanced.focus.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.bottom.color',
				],
				'field.advanced.focus.border__styles.left.color' => [
					'attrName' => 'field.advanced.focus.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.left.color',
				],
				'field.advanced.focus.border__styles.all.style' => [
					'attrName' => 'field.advanced.focus.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.all.style',
				],
				'field.advanced.focus.border__styles.top.style' => [
					'attrName' => 'field.advanced.focus.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.top.style',
				],
				'field.advanced.focus.border__styles.right.style' => [
					'attrName' => 'field.advanced.focus.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.right.style',
				],
				'field.advanced.focus.border__styles.bottom.style' => [
					'attrName' => 'field.advanced.focus.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.bottom.style',
				],
				'field.advanced.focus.border__styles.left.style' => [
					'attrName' => 'field.advanced.focus.border',
					'preset'   => [ 'style' ],
					'subName'  => 'styles.left.style',
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

		$checkbox_map = self::_duplicate_map_entries_by_prefix( $merged_map, 'field.', 'checkbox.' );
		$checkbox_map = self::_filter_form_field_variant_map( $checkbox_map, 'checkbox.' );
		$radio_map    = self::_duplicate_map_entries_by_prefix( $merged_map, 'field.', 'radio.' );
		$radio_map    = self::_filter_form_field_variant_map( $radio_map, 'radio.' );

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
			'resultMessage.decoration.font.textEffects__fillType' => [
				'attrName' => 'resultMessage.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'resultMessage.decoration.font.textEffects__gradient' => [
				'attrName' => 'resultMessage.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient',
			],
			'resultMessage.decoration.font.textEffects__gradient.type' => [
				'attrName' => 'resultMessage.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.type',
			],
			'resultMessage.decoration.font.textEffects__gradient.direction' => [
				'attrName' => 'resultMessage.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.direction',
			],
			'resultMessage.decoration.font.textEffects__gradient.directionRadial' => [
				'attrName' => 'resultMessage.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.directionRadial',
			],
			'resultMessage.decoration.font.textEffects__gradient.repeat' => [
				'attrName' => 'resultMessage.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.repeat',
			],
			'resultMessage.decoration.font.textEffects__gradient.length' => [
				'attrName' => 'resultMessage.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.length',
			],
			'resultMessage.decoration.font.textEffects__imageFill.blend' => [
				'attrName' => 'resultMessage.decoration.font.textEffects',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'imageFill.blend',
			],
			'resultMessage.decoration.font.textEffects__imageFill.height' => [
				'attrName' => 'resultMessage.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.height',
			],
			'resultMessage.decoration.font.textEffects__imageFill.horizontalOffset' => [
				'attrName' => 'resultMessage.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.horizontalOffset',
			],
			'resultMessage.decoration.font.textEffects__imageFill.position' => [
				'attrName' => 'resultMessage.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.position',
			],
			'resultMessage.decoration.font.textEffects__imageFill.repeat' => [
				'attrName' => 'resultMessage.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.repeat',
			],
			'resultMessage.decoration.font.textEffects__imageFill.size' => [
				'attrName' => 'resultMessage.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.size',
			],
			'resultMessage.decoration.font.textEffects__imageFill.url' => [
				'attrName' => 'resultMessage.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.url',
			],
			'resultMessage.decoration.font.textEffects__imageFill.verticalOffset' => [
				'attrName' => 'resultMessage.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.verticalOffset',
			],
			'resultMessage.decoration.font.textEffects__imageFill.width' => [
				'attrName' => 'resultMessage.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.width',
			],
			'resultMessage.decoration.font.textEffects__strokeColor' => [
				'attrName' => 'resultMessage.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeColor',
			],
			'resultMessage.decoration.font.textEffects__strokeWidth' => [
				'attrName' => 'resultMessage.decoration.font.textEffects',
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
			$radio_map,
			$radio_icon_root_map,
			$checkbox_icon_map,
			$radio_icon_map
		);
	}
}
