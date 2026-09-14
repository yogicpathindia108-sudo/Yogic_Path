<?php
/**
 * Module: InteractionsPresetAttrsMap class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Interactions;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * InteractionsPresetAttrsMap class.
 *
 * This class provides the static map for the interactions preset attributes.
 *
 * @since ??
 */
class InteractionsPresetAttrsMap {
	/**
	 * Get the map for the interactions preset attributes.
	 *
	 * @since ??
	 *
	 * @param string $attr_name The attribute name.
	 *
	 * @return array The map for the interactions preset attributes.
	 */
	public static function get_map( string $attr_name ) {
		return [
			$attr_name => [
				'attrName' => $attr_name,
				'preset'   => [ 'script' ],
			],
		];
	}
}
