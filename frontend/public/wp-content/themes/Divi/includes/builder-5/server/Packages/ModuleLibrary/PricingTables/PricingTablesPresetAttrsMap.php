<?php
/**
 * Module Library: PricingTables Module Preset Attributes Map
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PricingTables;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


/**
 * Class PricingTablesPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\PricingTables
 */
class PricingTablesPresetAttrsMap {
	/**
	 * Get the preset attributes map for the Pricing Tables module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/pricing-tables' !== $module_name ) {
			return $map;
		}

		// Keys to unset.
		$keys_to_unset = [
			'module.advanced.text.text__color',
			'featuredTitle.decoration.font__color',
			'featuredContent.decoration.font__color',
			'featuredSubtitle.decoration.font__color',
			'featuredPrice.decoration.font__color',
			'featuredCurrencyFrequency.decoration.font__color',
			'featuredExcluded.decoration.font__color',
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
			'button.decoration.font.font__lineHeight',
			'children.innerContent__rel',
			'button.innerContent__text',
			'button.innerContent__linkUrl',
			'button.innerContent__linkTarget',
			'module.advanced.featured',
			'button.innerContent__rel',
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
				'content.advanced.bulletColor'             => [
					'attrName' => 'content.advanced.bulletColor',
					'preset'   => [ 'style' ],
				],
				'title.decoration.font.font__headingLevel' => [
					'attrName' => 'title.decoration.font.font',
					'preset'   => [ 'html' ],
					'subName'  => 'headingLevel',
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
				'content.advanced.showBullet'              => [
					'attrName' => 'content.advanced.showBullet',
					'preset'   => [ 'html' ],
				],
				'featuredTitle.decoration.font.font__color' => [
					'attrName' => 'featuredTitle.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'featuredContent.decoration.font.font__color' => [
					'attrName' => 'featuredContent.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'featuredSubtitle.decoration.font.font__color' => [
					'attrName' => 'featuredSubtitle.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'featuredPrice.decoration.font.font__color' => [
					'attrName' => 'featuredPrice.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'featuredCurrencyFrequency.decoration.font.font__color' => [
					'attrName' => 'featuredCurrencyFrequency.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'featuredExcluded.decoration.font.font__color' => [
					'attrName' => 'featuredExcluded.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'children.button.innerContent__rel'        => [
					'attrName' => 'children.button.innerContent',
					'preset'   => [ 'html' ],
					'subName'  => 'rel',
				],
				'module.decoration.layout__alignContent'   => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'alignContent',
				],
				'module.decoration.layout__alignItems'     => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'alignItems',
				],
				'module.decoration.layout__collapseEmptyColumns' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'collapseEmptyColumns',
				],
				'module.decoration.layout__columnGap'      => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'columnGap',
				],
				'module.decoration.layout__display'        => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'display',
				],
				'module.decoration.layout__flexDirection'  => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'flexDirection',
				],
				'module.decoration.layout__flexWrap'       => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'flexWrap',
				],
				'module.decoration.layout__gridAutoColumns' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridAutoColumns',
				],
				'module.decoration.layout__gridAutoFlow'   => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridAutoFlow',
				],
				'module.decoration.layout__gridAutoRows'   => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridAutoRows',
				],
				'module.decoration.layout__gridColumnCount' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridColumnCount',
				],
				'module.decoration.layout__gridColumnMinWidth' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridColumnMinWidth',
				],
				'module.decoration.layout__gridColumnWidth' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridColumnWidth',
				],
				'module.decoration.layout__gridColumnWidths' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridColumnWidths',
				],
				'module.decoration.layout__gridDensity'    => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridDensity',
				],
				'module.decoration.layout__gridJustifyItems' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridJustifyItems',
				],
				'module.decoration.layout__gridOffsetRules' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridOffsetRules',
				],
				'module.decoration.layout__gridRowCount'   => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridRowCount',
				],
				'module.decoration.layout__gridRowHeight'  => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridRowHeight',
				],
				'module.decoration.layout__gridRowHeights' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridRowHeights',
				],
				'module.decoration.layout__gridRowMinHeight' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridRowMinHeight',
				],
				'module.decoration.layout__gridTemplateColumns' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridTemplateColumns',
				],
				'module.decoration.layout__gridTemplateRows' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'gridTemplateRows',
				],
				'module.decoration.layout__justifyContent' => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'justifyContent',
				],
				'module.decoration.layout__rowGap'         => [
					'attrName' => 'module.decoration.layout',
					'preset'   => [ 'style' ],
					'subName'  => 'rowGap',
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
				'subtitle.decoration.font.textEffects__fillType' => [
					'attrName' => 'subtitle.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'subtitle.decoration.font.textEffects__gradient' => [
					'attrName' => 'subtitle.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'subtitle.decoration.font.textEffects__gradient.type' => [
					'attrName' => 'subtitle.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'subtitle.decoration.font.textEffects__gradient.direction' => [
					'attrName' => 'subtitle.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'subtitle.decoration.font.textEffects__gradient.directionRadial' => [
					'attrName' => 'subtitle.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'subtitle.decoration.font.textEffects__gradient.repeat' => [
					'attrName' => 'subtitle.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'subtitle.decoration.font.textEffects__gradient.length' => [
					'attrName' => 'subtitle.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'subtitle.decoration.font.textEffects__imageFill.blend' => [
					'attrName' => 'subtitle.decoration.font.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'subtitle.decoration.font.textEffects__imageFill.height' => [
					'attrName' => 'subtitle.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'subtitle.decoration.font.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'subtitle.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'subtitle.decoration.font.textEffects__imageFill.position' => [
					'attrName' => 'subtitle.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'subtitle.decoration.font.textEffects__imageFill.repeat' => [
					'attrName' => 'subtitle.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'subtitle.decoration.font.textEffects__imageFill.size' => [
					'attrName' => 'subtitle.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'subtitle.decoration.font.textEffects__imageFill.url' => [
					'attrName' => 'subtitle.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'subtitle.decoration.font.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'subtitle.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'subtitle.decoration.font.textEffects__imageFill.width' => [
					'attrName' => 'subtitle.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'subtitle.decoration.font.textEffects__strokeColor' => [
					'attrName' => 'subtitle.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'subtitle.decoration.font.textEffects__strokeWidth' => [
					'attrName' => 'subtitle.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
				'price.decoration.font.textEffects__fillType' => [
					'attrName' => 'price.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'price.decoration.font.textEffects__gradient' => [
					'attrName' => 'price.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'price.decoration.font.textEffects__gradient.type' => [
					'attrName' => 'price.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'price.decoration.font.textEffects__gradient.direction' => [
					'attrName' => 'price.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'price.decoration.font.textEffects__gradient.directionRadial' => [
					'attrName' => 'price.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'price.decoration.font.textEffects__gradient.repeat' => [
					'attrName' => 'price.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'price.decoration.font.textEffects__gradient.length' => [
					'attrName' => 'price.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'price.decoration.font.textEffects__imageFill.blend' => [
					'attrName' => 'price.decoration.font.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'price.decoration.font.textEffects__imageFill.height' => [
					'attrName' => 'price.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'price.decoration.font.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'price.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'price.decoration.font.textEffects__imageFill.position' => [
					'attrName' => 'price.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'price.decoration.font.textEffects__imageFill.repeat' => [
					'attrName' => 'price.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'price.decoration.font.textEffects__imageFill.size' => [
					'attrName' => 'price.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'price.decoration.font.textEffects__imageFill.url' => [
					'attrName' => 'price.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'price.decoration.font.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'price.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'price.decoration.font.textEffects__imageFill.width' => [
					'attrName' => 'price.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'price.decoration.font.textEffects__strokeColor' => [
					'attrName' => 'price.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'price.decoration.font.textEffects__strokeWidth' => [
					'attrName' => 'price.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
				'currencyFrequency.decoration.font.textEffects__fillType' => [
					'attrName' => 'currencyFrequency.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'currencyFrequency.decoration.font.textEffects__gradient' => [
					'attrName' => 'currencyFrequency.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'currencyFrequency.decoration.font.textEffects__gradient.type' => [
					'attrName' => 'currencyFrequency.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'currencyFrequency.decoration.font.textEffects__gradient.direction' => [
					'attrName' => 'currencyFrequency.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'currencyFrequency.decoration.font.textEffects__gradient.directionRadial' => [
					'attrName' => 'currencyFrequency.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'currencyFrequency.decoration.font.textEffects__gradient.repeat' => [
					'attrName' => 'currencyFrequency.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'currencyFrequency.decoration.font.textEffects__gradient.length' => [
					'attrName' => 'currencyFrequency.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'currencyFrequency.decoration.font.textEffects__imageFill.blend' => [
					'attrName' => 'currencyFrequency.decoration.font.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'currencyFrequency.decoration.font.textEffects__imageFill.height' => [
					'attrName' => 'currencyFrequency.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'currencyFrequency.decoration.font.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'currencyFrequency.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'currencyFrequency.decoration.font.textEffects__imageFill.position' => [
					'attrName' => 'currencyFrequency.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'currencyFrequency.decoration.font.textEffects__imageFill.repeat' => [
					'attrName' => 'currencyFrequency.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'currencyFrequency.decoration.font.textEffects__imageFill.size' => [
					'attrName' => 'currencyFrequency.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'currencyFrequency.decoration.font.textEffects__imageFill.url' => [
					'attrName' => 'currencyFrequency.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'currencyFrequency.decoration.font.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'currencyFrequency.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'currencyFrequency.decoration.font.textEffects__imageFill.width' => [
					'attrName' => 'currencyFrequency.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'currencyFrequency.decoration.font.textEffects__strokeColor' => [
					'attrName' => 'currencyFrequency.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'currencyFrequency.decoration.font.textEffects__strokeWidth' => [
					'attrName' => 'currencyFrequency.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeWidth',
				],
				'excluded.decoration.font.textEffects__fillType' => [
					'attrName' => 'excluded.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'fillType',
				],
				'excluded.decoration.font.textEffects__gradient' => [
					'attrName' => 'excluded.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient',
				],
				'excluded.decoration.font.textEffects__gradient.type' => [
					'attrName' => 'excluded.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.type',
				],
				'excluded.decoration.font.textEffects__gradient.direction' => [
					'attrName' => 'excluded.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.direction',
				],
				'excluded.decoration.font.textEffects__gradient.directionRadial' => [
					'attrName' => 'excluded.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.directionRadial',
				],
				'excluded.decoration.font.textEffects__gradient.repeat' => [
					'attrName' => 'excluded.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.repeat',
				],
				'excluded.decoration.font.textEffects__gradient.length' => [
					'attrName' => 'excluded.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'gradient.length',
				],
				'excluded.decoration.font.textEffects__imageFill.blend' => [
					'attrName' => 'excluded.decoration.font.textEffects',
					'preset'   => [ 'style', 'html' ],
					'subName'  => 'imageFill.blend',
				],
				'excluded.decoration.font.textEffects__imageFill.height' => [
					'attrName' => 'excluded.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.height',
				],
				'excluded.decoration.font.textEffects__imageFill.horizontalOffset' => [
					'attrName' => 'excluded.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.horizontalOffset',
				],
				'excluded.decoration.font.textEffects__imageFill.position' => [
					'attrName' => 'excluded.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.position',
				],
				'excluded.decoration.font.textEffects__imageFill.repeat' => [
					'attrName' => 'excluded.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.repeat',
				],
				'excluded.decoration.font.textEffects__imageFill.size' => [
					'attrName' => 'excluded.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.size',
				],
				'excluded.decoration.font.textEffects__imageFill.url' => [
					'attrName' => 'excluded.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.url',
				],
				'excluded.decoration.font.textEffects__imageFill.verticalOffset' => [
					'attrName' => 'excluded.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.verticalOffset',
				],
				'excluded.decoration.font.textEffects__imageFill.width' => [
					'attrName' => 'excluded.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'imageFill.width',
				],
				'excluded.decoration.font.textEffects__strokeColor' => [
					'attrName' => 'excluded.decoration.font.textEffects',
					'preset'   => [ 'style' ],
					'subName'  => 'strokeColor',
				],
				'excluded.decoration.font.textEffects__strokeWidth' => [
					'attrName' => 'excluded.decoration.font.textEffects',
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
