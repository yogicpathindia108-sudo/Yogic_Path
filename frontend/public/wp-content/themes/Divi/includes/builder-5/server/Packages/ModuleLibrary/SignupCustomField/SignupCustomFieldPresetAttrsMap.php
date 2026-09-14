<?php
/**
 * Module Library: Signup Custom Field Module Preset Attributes Map
 *
 * @package Divi
 * @since   ??
 */

namespace ET\Builder\Packages\ModuleLibrary\SignupCustomField;

use ET\Builder\Packages\ModuleLibrary\FormFieldVariantPresetMapTrait;
use ET\Builder\Packages\Module\Options\Icon\IconPresetAttrsMap;
use ET\Builder\Packages\Module\Options\FormField\FormFieldPresetAttrsMap;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


/**
 * Class SignupCustomFieldPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\SignupCustomField
 */
class SignupCustomFieldPresetAttrsMap {
	use FormFieldVariantPresetMapTrait;


	/**
	 * Get the preset attributes map for the Signup Custom Field module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/signup-custom-field' !== $module_name ) {
			return $map;
		}

		unset( $map['module.advanced.text.text__color'] );
		unset( $map['module.advanced.text.textShadow__style'] );
		unset( $map['module.advanced.text.textShadow__horizontal'] );
		unset( $map['module.advanced.text.textShadow__vertical'] );
		unset( $map['module.advanced.text.textShadow__blur'] );
		unset( $map['module.advanced.text.textShadow__color'] );
		unset( $map['module.decoration.disabledOn'] );
		unset( $map['fieldItem.advanced.predefinedField'] );
		unset( $map['fieldItem.advanced.hidden'] );

		$merged_map     = array_merge(
			$map,
			FormFieldPresetAttrsMap::get_map( 'field' ),
			[
				'fieldItem.advanced.hidden'                => [
					'attrName' => 'fieldItem.advanced.hidden',
					'preset'   => [ 'html' ],
				],
				'field.decoration.font.font__headingLevel' => [
					'attrName' => 'field.decoration.font.font',
					'preset'   => [ 'html' ],
					'subName'  => 'headingLevel',
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
				'field.decoration.labelFont.font__family'  => [
					'attrName' => 'field.decoration.labelFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'family',
				],
				'field.decoration.labelFont.font__weight'  => [
					'attrName' => 'field.decoration.labelFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'weight',
				],
				'field.decoration.labelFont.font__weightFineTune' => [
					'attrName' => 'field.decoration.labelFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'weightFineTune',
				],
				'field.decoration.labelFont.font__opticalSizing' => [
					'attrName' => 'field.decoration.labelFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'opticalSizing',
				],
				'field.decoration.labelFont.font__style'   => [
					'attrName' => 'field.decoration.labelFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'style',
				],
				'field.decoration.labelFont.font__lineColor' => [
					'attrName' => 'field.decoration.labelFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'lineColor',
				],
				'field.decoration.labelFont.font__lineThickness' => [
					'attrName' => 'field.decoration.labelFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'lineThickness',
				],
				'field.decoration.labelFont.font__underlineOffset' => [
					'attrName' => 'field.decoration.labelFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'underlineOffset',
				],
				'field.decoration.labelFont.font__lineStyle' => [
					'attrName' => 'field.decoration.labelFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'lineStyle',
				],
				'field.decoration.labelFont.font__textWrap' => [
					'attrName' => 'field.decoration.labelFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'textWrap',
				],
				'field.decoration.labelFont.font__writingMode' => [
					'attrName' => 'field.decoration.labelFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'writingMode',
				],
				'field.decoration.labelFont.font__hyphens' => [
					'attrName' => 'field.decoration.labelFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'hyphens',
				],
				'field.decoration.labelFont.font__textAlign' => [
					'attrName' => 'field.decoration.labelFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'textAlign',
				],
				'field.decoration.labelFont.font__capitalization' => [
					'attrName' => 'field.decoration.labelFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'capitalization',
				],
				'field.decoration.labelFont.font__color'   => [
					'attrName' => 'field.decoration.labelFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'field.decoration.labelFont.font__size'    => [
					'attrName' => 'field.decoration.labelFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'size',
				],
				'field.decoration.labelFont.font__letterSpacing' => [
					'attrName' => 'field.decoration.labelFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'letterSpacing',
				],
				'field.decoration.labelFont.font__lineHeight' => [
					'attrName' => 'field.decoration.labelFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'lineHeight',
				],
				'field.decoration.labelFont.font__columnCount' => [
					'attrName' => 'field.decoration.labelFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'columnCount',
				],
				'field.decoration.labelFont.font__columnGap' => [
					'attrName' => 'field.decoration.labelFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'columnGap',
				],
				'field.decoration.labelFont.textShadow__style' => [
					'attrName' => 'field.decoration.labelFont.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'style',
				],
				'field.decoration.labelFont.textShadow__horizontal' => [
					'attrName' => 'field.decoration.labelFont.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'horizontal',
				],
				'field.decoration.labelFont.textShadow__vertical' => [
					'attrName' => 'field.decoration.labelFont.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'vertical',
				],
				'field.decoration.labelFont.textShadow__blur' => [
					'attrName' => 'field.decoration.labelFont.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'blur',
				],
				'field.decoration.labelFont.textShadow__color' => [
					'attrName' => 'field.decoration.labelFont.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'field.decoration.placeholderFont.font__family' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'family',
				],
				'field.decoration.placeholderFont.font__weight' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'weight',
				],
				'field.decoration.placeholderFont.font__weightFineTune' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'weightFineTune',
				],
				'field.decoration.placeholderFont.font__opticalSizing' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'opticalSizing',
				],
				'field.decoration.placeholderFont.font__style' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'style',
				],
				'field.decoration.placeholderFont.font__lineColor' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'lineColor',
				],
				'field.decoration.placeholderFont.font__lineThickness' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'lineThickness',
				],
				'field.decoration.placeholderFont.font__underlineOffset' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'underlineOffset',
				],
				'field.decoration.placeholderFont.font__lineStyle' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'lineStyle',
				],
				'field.decoration.placeholderFont.font__textWrap' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'textWrap',
				],
				'field.decoration.placeholderFont.font__writingMode' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'writingMode',
				],
				'field.decoration.placeholderFont.font__hyphens' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'hyphens',
				],
				'field.decoration.placeholderFont.font__textAlign' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'textAlign',
				],
				'field.decoration.placeholderFont.font__capitalization' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'capitalization',
				],
				'field.decoration.placeholderFont.font__color' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'field.decoration.placeholderFont.font__size' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'size',
				],
				'field.decoration.placeholderFont.font__letterSpacing' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'letterSpacing',
				],
				'field.decoration.placeholderFont.font__lineHeight' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'lineHeight',
				],
				'field.decoration.placeholderFont.font__columnCount' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'columnCount',
				],
				'field.decoration.placeholderFont.font__columnGap' => [
					'attrName' => 'field.decoration.placeholderFont.font',
					'preset'   => [ 'style' ],
					'subName'  => 'columnGap',
				],
				'field.decoration.placeholderFont.textShadow__style' => [
					'attrName' => 'field.decoration.placeholderFont.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'style',
				],
				'field.decoration.placeholderFont.textShadow__horizontal' => [
					'attrName' => 'field.decoration.placeholderFont.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'horizontal',
				],
				'field.decoration.placeholderFont.textShadow__vertical' => [
					'attrName' => 'field.decoration.placeholderFont.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'vertical',
				],
				'field.decoration.placeholderFont.textShadow__blur' => [
					'attrName' => 'field.decoration.placeholderFont.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'blur',
				],
				'field.decoration.placeholderFont.textShadow__color' => [
					'attrName' => 'field.decoration.placeholderFont.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
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
		$keys_to_remove = [
			'field.advanced.placeholder.font.font__family',
			'field.advanced.placeholder.font.font__weight',
			'field.advanced.placeholder.font.font__style',
			'field.advanced.placeholder.font.font__lineColor',
			'field.advanced.placeholder.font.font__lineStyle',
			'field.advanced.placeholder.font.font__textAlign',
			'field.advanced.placeholder.font.textShadow__style',
			'field.advanced.placeholder.font.textShadow__horizontal',
			'field.advanced.placeholder.font.textShadow__vertical',
			'field.advanced.placeholder.font.textShadow__blur',
			'field.advanced.placeholder.font.textShadow__color',
			'field.advanced.focus.font.font__family',
			'field.advanced.focus.font.font__weight',
			'field.advanced.focus.font.font__style',
			'field.advanced.focus.font.font__lineColor',
			'field.advanced.focus.font.font__lineStyle',
			'field.advanced.focus.font.font__textAlign',
			'field.advanced.focus.font.font__size',
			'field.advanced.focus.font.font__letterSpacing',
			'field.advanced.focus.font.font__lineHeight',
			'field.advanced.focus.font.textShadow__style',
			'field.advanced.focus.font.textShadow__horizontal',
			'field.advanced.focus.font.textShadow__vertical',
			'field.advanced.focus.font.textShadow__blur',
			'field.advanced.focus.font.textShadow__color',
			'field.advanced.placeholder.font.textEffects__fillType',
			'field.advanced.placeholder.font.textEffects__gradient',
			'field.advanced.placeholder.font.textEffects__gradient.type',
			'field.advanced.placeholder.font.textEffects__gradient.direction',
			'field.advanced.placeholder.font.textEffects__gradient.directionRadial',
			'field.advanced.placeholder.font.textEffects__gradient.repeat',
			'field.advanced.placeholder.font.textEffects__gradient.length',
			'field.advanced.placeholder.font.textEffects__imageFill.blend',
			'field.advanced.placeholder.font.textEffects__imageFill.height',
			'field.advanced.placeholder.font.textEffects__imageFill.horizontalOffset',
			'field.advanced.placeholder.font.textEffects__imageFill.position',
			'field.advanced.placeholder.font.textEffects__imageFill.repeat',
			'field.advanced.placeholder.font.textEffects__imageFill.size',
			'field.advanced.placeholder.font.textEffects__imageFill.url',
			'field.advanced.placeholder.font.textEffects__imageFill.verticalOffset',
			'field.advanced.placeholder.font.textEffects__imageFill.width',
			'field.advanced.placeholder.font.textEffects__strokeColor',
			'field.advanced.placeholder.font.textEffects__strokeWidth',
			'field.advanced.placeholder.font.textEffects__strokePosition',
			'field.advanced.placeholder.font.font__weightFineTune',
			'field.advanced.placeholder.font.font__opticalSizing',
			'field.advanced.placeholder.font.font__lineThickness',
			'field.advanced.placeholder.font.font__underlineOffset',
			'field.advanced.placeholder.font.font__textWrap',
			'field.advanced.placeholder.font.font__writingMode',
			'field.advanced.placeholder.font.font__hyphens',
			'field.advanced.placeholder.font.font__columnCount',
			'field.advanced.placeholder.font.font__columnGap',
			'field.advanced.placeholder.font.font__capitalization',
			'field.advanced.focus.font.textEffects__fillType',
			'field.advanced.focus.font.textEffects__gradient',
			'field.advanced.focus.font.textEffects__gradient.type',
			'field.advanced.focus.font.textEffects__gradient.direction',
			'field.advanced.focus.font.textEffects__gradient.directionRadial',
			'field.advanced.focus.font.textEffects__gradient.repeat',
			'field.advanced.focus.font.textEffects__gradient.length',
			'field.advanced.focus.font.textEffects__imageFill.blend',
			'field.advanced.focus.font.textEffects__imageFill.height',
			'field.advanced.focus.font.textEffects__imageFill.horizontalOffset',
			'field.advanced.focus.font.textEffects__imageFill.position',
			'field.advanced.focus.font.textEffects__imageFill.repeat',
			'field.advanced.focus.font.textEffects__imageFill.size',
			'field.advanced.focus.font.textEffects__imageFill.url',
			'field.advanced.focus.font.textEffects__imageFill.verticalOffset',
			'field.advanced.focus.font.textEffects__imageFill.width',
			'field.advanced.focus.font.textEffects__strokeColor',
			'field.advanced.focus.font.textEffects__strokeWidth',
			'field.advanced.focus.font.textEffects__strokePosition',
			'field.advanced.focus.font.font__weightFineTune',
			'field.advanced.focus.font.font__opticalSizing',
			'field.advanced.focus.font.font__lineThickness',
			'field.advanced.focus.font.font__underlineOffset',
			'field.advanced.focus.font.font__textWrap',
			'field.advanced.focus.font.font__writingMode',
			'field.advanced.focus.font.font__hyphens',
			'field.advanced.focus.font.font__columnCount',
			'field.advanced.focus.font.font__columnGap',
			'field.advanced.focus.font.font__capitalization',
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
			unset( $merged_map[ $key ] );
		}

		$checkbox_map = self::_duplicate_map_entries_by_prefix( $merged_map, 'field.', 'checkbox.' );
		$checkbox_map = self::_filter_form_field_variant_map( $checkbox_map, 'checkbox.', true );
		$radio_map    = self::_duplicate_map_entries_by_prefix( $merged_map, 'field.', 'radio.' );
		$radio_map    = self::_filter_form_field_variant_map( $radio_map, 'radio.', true );

		$checkbox_icon_map      = IconPresetAttrsMap::get_map( 'checkbox.decoration.icon' );
		$radio_icon_map         = IconPresetAttrsMap::get_map( 'radio.decoration.icon' );
		$checkbox_icon_root_map = [
			'checkbox.decoration.icon' => [
				'attrName' => 'checkbox.decoration.icon',
				'preset'   => [ 'style', 'html' ],
			],
		];
		$radio_icon_root_map    = [
			'radio.decoration.icon'                       => [
				'attrName' => 'radio.decoration.icon',
				'preset'   => [ 'style', 'html' ],
			],
			'field.decoration.font.textEffects__fillType' => [
				'attrName' => 'field.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'field.decoration.font.textEffects__gradient' => [
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
			'field.decoration.labelFont.textEffects__strokePosition' => [
				'attrName' => 'field.decoration.labelFont.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokePosition',
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
			'field.decoration.placeholderFont.textEffects__strokePosition' => [
				'attrName' => 'field.decoration.placeholderFont.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokePosition',
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
			'radio.decoration.font.textEffects__fillType' => [
				'attrName' => 'radio.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'radio.decoration.font.textEffects__gradient' => [
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
