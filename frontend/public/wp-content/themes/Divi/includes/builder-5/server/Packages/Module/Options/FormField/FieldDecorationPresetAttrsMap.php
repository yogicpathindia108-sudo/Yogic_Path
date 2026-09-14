<?php
/**
 * Module: FieldDecorationPresetAttrsMap class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\FormField;

use ET\Builder\Packages\Module\Options\Background\BackgroundPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Border\BorderPresetAttrsMap;
use ET\Builder\Packages\Module\Options\BoxShadow\BoxShadowPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Font\FontPresetAttrsMap;
use ET\Builder\Packages\Module\Options\Spacing\SpacingPresetAttrsMap;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * FieldDecorationPresetAttrsMap class.
 *
 * Shared preset attribute map for `field.decoration.*` groups used by form-style modules.
 *
 * @since ??
 */
class FieldDecorationPresetAttrsMap {

	/**
	 * Preset map keys for field decoration (font, labelFont, placeholderFont, spacing, background, border, box shadow).
	 *
	 * @since ??
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_map(): array {
		$decoration_prefix = 'field.decoration';

		return array_merge(
			FontPresetAttrsMap::get_map( "{$decoration_prefix}.font" ),
			FontPresetAttrsMap::get_map( "{$decoration_prefix}.labelFont" ),
			FontPresetAttrsMap::get_map( "{$decoration_prefix}.placeholderFont" ),
			SpacingPresetAttrsMap::get_map( "{$decoration_prefix}.spacing" ),
			BackgroundPresetAttrsMap::get_map( "{$decoration_prefix}.background" ),
			BorderPresetAttrsMap::get_map( "{$decoration_prefix}.border" ),
			BoxShadowPresetAttrsMap::get_map( "{$decoration_prefix}.boxShadow" ),
			[
				"{$decoration_prefix}.background__backgroundColor" => [
					'attrName' => "{$decoration_prefix}.background",
					'preset'   => [ 'style' ],
					'subName'  => 'backgroundColor',
				],
			]
		);
	}
}
