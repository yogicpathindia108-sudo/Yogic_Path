<?php
/**
 * Module: NativeChoicePresetAttrsMap class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\FormField;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * NativeChoicePresetAttrsMap class.
 *
 * Shared preset attribute map for native choice controls (radio/checkbox-style inputs).
 *
 * @since ??
 */
class NativeChoicePresetAttrsMap {
	/**
	 * Get the map for a native-choice control accent color preset attribute.
	 *
	 * @since ??
	 *
	 * @param string $attr_name Native choice control attribute name.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_map( string $attr_name ) {
		$accent_color_attr_name = "{$attr_name}.decoration.accentColor";

		return [
			$accent_color_attr_name => [
				'attrName' => $accent_color_attr_name,
				'preset'   => [ 'style' ],
			],
		];
	}
}
