<?php
/**
 * Module Library: ContactField Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\ContactField;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\ModuleLibrary\FormFieldVariantPresetMapTrait;
use ET\Builder\Packages\Module\Options\FormField\FieldDecorationPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Icon\IconPresetAttrsMap;


/**
 * Class ContactFieldPresetAttrsMap
 *
 * @since ??
 *
 * @package ET\Builder\Packages\ModuleLibrary\ContactField
 */
class ContactFieldPresetAttrsMap {
	use FormFieldVariantPresetMapTrait;

	/**
	 * Get the preset attributes map for the ContactField module.
	 *
	 * @since ??
	 *
	 * @param array  $map         The preset attributes map.
	 * @param string $module_name The module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ) {
		if ( 'divi/contact-field' !== $module_name ) {
			return $map;
		}

		$keys_to_remove = [
			'module.advanced.text.text__orientation',
			'module.advanced.text.text__color',
			'module.advanced.text.textShadow__style',
			'module.advanced.text.textShadow__horizontal',
			'module.advanced.text.textShadow__vertical',
			'module.advanced.text.textShadow__blur',
			'module.advanced.text.textShadow__color',
			'module.decoration.disabledOn',
			'module.decoration.sticky__position',
			'module.decoration.sticky__offset.top',
			'module.decoration.sticky__offset.bottom',
			'module.decoration.sticky__limit.top',
			'module.decoration.sticky__limit.bottom',
			'module.decoration.sticky__offset.surrounding',
			'module.decoration.sticky__transition',
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
		];

		foreach ( $keys_to_remove as $key ) {
			unset( $map[ $key ] );
		}

		$field_decoration_map = FieldDecorationPresetAttrsMap::get_map();

		$merged_map = array_merge(
			$map,
			$field_decoration_map,
			[
				'field.advanced.focus.background__color'  => [
					'attrName' => 'field.advanced.focus.background',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'field.advanced.focus.font.font__color'   => [
					'attrName' => 'field.advanced.focus.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'fieldTitle.decoration.font.font__color'  => [
					'attrName' => 'fieldTitle.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'fieldTitle.decoration.font.font__family' => [
					'attrName' => 'fieldTitle.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'family',
				],
				'fieldTitle.decoration.font.font__letterSpacing' => [
					'attrName' => 'fieldTitle.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'letterSpacing',
				],
				'fieldTitle.decoration.font.font__lineColor' => [
					'attrName' => 'fieldTitle.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'lineColor',
				],
				'fieldTitle.decoration.font.font__lineHeight' => [
					'attrName' => 'fieldTitle.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'lineHeight',
				],
				'fieldTitle.decoration.font.font__lineStyle' => [
					'attrName' => 'fieldTitle.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'lineStyle',
				],
				'fieldTitle.decoration.font.font__size'   => [
					'attrName' => 'fieldTitle.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'size',
				],
				'fieldTitle.decoration.font.font__style'  => [
					'attrName' => 'fieldTitle.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'style',
				],
				'fieldTitle.decoration.font.font__textAlign' => [
					'attrName' => 'fieldTitle.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'textAlign',
				],
				'fieldTitle.decoration.font.font__weight' => [
					'attrName' => 'fieldTitle.decoration.font.font',
					'preset'   => [ 'style' ],
					'subName'  => 'weight',
				],
				'fieldTitle.decoration.font.textShadow__blur' => [
					'attrName' => 'fieldTitle.decoration.font.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'blur',
				],
				'fieldTitle.decoration.font.textShadow__color' => [
					'attrName' => 'fieldTitle.decoration.font.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'color',
				],
				'fieldTitle.decoration.font.textShadow__horizontal' => [
					'attrName' => 'fieldTitle.decoration.font.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'horizontal',
				],
				'fieldTitle.decoration.font.textShadow__style' => [
					'attrName' => 'fieldTitle.decoration.font.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'style',
				],
				'fieldTitle.decoration.font.textShadow__vertical' => [
					'attrName' => 'fieldTitle.decoration.font.textShadow',
					'preset'   => [ 'style' ],
					'subName'  => 'vertical',
				],
				'module.advanced.text__orientation'       => [
					'attrName' => 'module.advanced.text',
					'preset'   => [ 'html' ],
					'subName'  => 'orientation',
				],
				'module.advanced.html__elementType'       => [
					'attrName' => 'module.advanced.html',
					'preset'   => [ 'html' ],
					'subName'  => 'elementType',
				],
				'module.advanced.html__htmlAfter'         => [
					'attrName' => 'module.advanced.html',
					'preset'   => [ 'html' ],
					'subName'  => 'htmlAfter',
				],
				'module.advanced.html__htmlBefore'        => [
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
			'fieldTitle.decoration.font.textEffects__fillType' => [
				'attrName' => 'fieldTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			'fieldTitle.decoration.font.textEffects__gradient' => [
				'attrName' => 'fieldTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient',
			],
			'fieldTitle.decoration.font.textEffects__gradient.type' => [
				'attrName' => 'fieldTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.type',
			],
			'fieldTitle.decoration.font.textEffects__gradient.direction' => [
				'attrName' => 'fieldTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.direction',
			],
			'fieldTitle.decoration.font.textEffects__gradient.directionRadial' => [
				'attrName' => 'fieldTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.directionRadial',
			],
			'fieldTitle.decoration.font.textEffects__gradient.repeat' => [
				'attrName' => 'fieldTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.repeat',
			],
			'fieldTitle.decoration.font.textEffects__gradient.length' => [
				'attrName' => 'fieldTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.length',
			],
			'fieldTitle.decoration.font.textEffects__imageFill.blend' => [
				'attrName' => 'fieldTitle.decoration.font.textEffects',
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'imageFill.blend',
			],
			'fieldTitle.decoration.font.textEffects__imageFill.height' => [
				'attrName' => 'fieldTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.height',
			],
			'fieldTitle.decoration.font.textEffects__imageFill.horizontalOffset' => [
				'attrName' => 'fieldTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.horizontalOffset',
			],
			'fieldTitle.decoration.font.textEffects__imageFill.position' => [
				'attrName' => 'fieldTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.position',
			],
			'fieldTitle.decoration.font.textEffects__imageFill.repeat' => [
				'attrName' => 'fieldTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.repeat',
			],
			'fieldTitle.decoration.font.textEffects__imageFill.size' => [
				'attrName' => 'fieldTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.size',
			],
			'fieldTitle.decoration.font.textEffects__imageFill.url' => [
				'attrName' => 'fieldTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.url',
			],
			'fieldTitle.decoration.font.textEffects__imageFill.verticalOffset' => [
				'attrName' => 'fieldTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.verticalOffset',
			],
			'fieldTitle.decoration.font.textEffects__imageFill.width' => [
				'attrName' => 'fieldTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.width',
			],
			'fieldTitle.decoration.font.textEffects__strokeColor' => [
				'attrName' => 'fieldTitle.decoration.font.textEffects',
				'preset'   => [ 'style' ],
				'subName'  => 'strokeColor',
			],
			'fieldTitle.decoration.font.textEffects__strokeWidth' => [
				'attrName' => 'fieldTitle.decoration.font.textEffects',
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
