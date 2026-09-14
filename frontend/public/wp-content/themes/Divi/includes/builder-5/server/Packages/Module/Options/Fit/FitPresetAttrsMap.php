<?php
/**
 * Module: FitPresetAttrsMap class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Fit;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * FitPresetAttrsMap class.
 *
 * This class provides the static map for the fit preset attributes.
 *
 * @since ??
 */
class FitPresetAttrsMap {
	/**
	 * Get the map for the fit preset attributes.
	 *
	 * @since ??
	 *
	 * @param string $attr_name The attribute name.
	 *
	 * @return array The map for the fit preset attributes.
	 */
	public static function get_map( string $attr_name ) {
		return [
			"{$attr_name}__objectFit"      => [
				'attrName' => $attr_name,
				'subName'  => 'objectFit',
				'preset'   => [ 'style' ],
			],
			"{$attr_name}__objectPosition" => [
				'attrName' => $attr_name,
				'subName'  => 'objectPosition',
				'preset'   => [ 'style' ],
			],
		];
	}
}
