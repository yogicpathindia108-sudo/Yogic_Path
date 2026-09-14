<?php
/**
 * Module: HtmlPresetAttrsMap class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Html;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * HtmlPresetAttrsMap class.
 *
 * This class provides the static map for the HTML group preset attributes.
 *
 * @since ??
 */
class HtmlPresetAttrsMap {
	/**
	 * Get the map for the HTML group preset attributes.
	 *
	 * @since ??
	 *
	 * @param string $attr_name The attribute name.
	 *
	 * @return array The map for the HTML group preset attributes.
	 */
	public static function get_map( string $attr_name ) {
		return [
			"{$attr_name}__elementType" => [
				'attrName' => $attr_name,
				'preset'   => [ 'html' ],
				'subName'  => 'elementType',
			],
			"{$attr_name}__htmlBefore"  => [
				'attrName' => $attr_name,
				'preset'   => [ 'html' ],
				'subName'  => 'htmlBefore',
			],
			"{$attr_name}__htmlAfter"   => [
				'attrName' => $attr_name,
				'preset'   => [ 'html' ],
				'subName'  => 'htmlAfter',
			],
		];
	}
}
