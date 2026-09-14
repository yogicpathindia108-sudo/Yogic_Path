<?php
/**
 * Module: TextEffectsPresetAttrsMap class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\TextEffects;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * TextEffectsPresetAttrsMap class.
 *
 * This class provides static map for text effects preset attributes.
 *
 * @since ??
 */
class TextEffectsPresetAttrsMap {
	/**
	 * Get the map for text effects preset attributes.
	 *
	 * @since ??
	 *
	 * @param string $attr_name The attribute name.
	 *
	 * @return array The map for text effects preset attributes.
	 */
	public static function get_map( string $attr_name ) {
		return [
			"{$attr_name}.textEffects__fillType"           => [
				'attrName' => "{$attr_name}.textEffects",
				'preset'   => [ 'style' ],
				'subName'  => 'fillType',
			],
			"{$attr_name}.textEffects__gradient"           => [
				'attrName' => "{$attr_name}.textEffects",
				'preset'   => [ 'style' ],
				'subName'  => 'gradient',
			],
			"{$attr_name}.textEffects__gradient.type"      => [
				'attrName' => "{$attr_name}.textEffects",
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.type',
			],
			"{$attr_name}.textEffects__gradient.direction" => [
				'attrName' => "{$attr_name}.textEffects",
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.direction',
			],
			"{$attr_name}.textEffects__gradient.directionRadial" => [
				'attrName' => "{$attr_name}.textEffects",
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.directionRadial',
			],
			"{$attr_name}.textEffects__gradient.repeat"    => [
				'attrName' => "{$attr_name}.textEffects",
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.repeat',
			],
			"{$attr_name}.textEffects__gradient.length"    => [
				'attrName' => "{$attr_name}.textEffects",
				'preset'   => [ 'style' ],
				'subName'  => 'gradient.length',
			],
			"{$attr_name}.textEffects__imageFill.blend"    => [
				'attrName' => "{$attr_name}.textEffects",
				'preset'   => [ 'style', 'html' ],
				'subName'  => 'imageFill.blend',
			],
			"{$attr_name}.textEffects__imageFill.height"   => [
				'attrName' => "{$attr_name}.textEffects",
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.height',
			],
			"{$attr_name}.textEffects__imageFill.horizontalOffset" => [
				'attrName' => "{$attr_name}.textEffects",
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.horizontalOffset',
			],
			"{$attr_name}.textEffects__imageFill.position" => [
				'attrName' => "{$attr_name}.textEffects",
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.position',
			],
			"{$attr_name}.textEffects__imageFill.repeat"   => [
				'attrName' => "{$attr_name}.textEffects",
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.repeat',
			],
			"{$attr_name}.textEffects__imageFill.size"     => [
				'attrName' => "{$attr_name}.textEffects",
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.size',
			],
			"{$attr_name}.textEffects__imageFill.url"      => [
				'attrName' => "{$attr_name}.textEffects",
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.url',
			],
			"{$attr_name}.textEffects__imageFill.verticalOffset" => [
				'attrName' => "{$attr_name}.textEffects",
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.verticalOffset',
			],
			"{$attr_name}.textEffects__imageFill.width"    => [
				'attrName' => "{$attr_name}.textEffects",
				'preset'   => [ 'style' ],
				'subName'  => 'imageFill.width',
			],
			"{$attr_name}.textEffects__strokeColor"        => [
				'attrName' => "{$attr_name}.textEffects",
				'preset'   => [ 'style' ],
				'subName'  => 'strokeColor',
			],
			"{$attr_name}.textEffects__strokePosition"     => [
				'attrName' => "{$attr_name}.textEffects",
				'preset'   => [ 'style' ],
				'subName'  => 'strokePosition',
			],
			"{$attr_name}.textEffects__strokeWidth"        => [
				'attrName' => "{$attr_name}.textEffects",
				'preset'   => [ 'style' ],
				'subName'  => 'strokeWidth',
			],
		];
	}
}
