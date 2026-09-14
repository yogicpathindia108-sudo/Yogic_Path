<?php
/**
 * Post Filter preview controller scaffold.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostFilter;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

class PostFilterController {
	/**
	 * Return minimal preview payload scaffold.
	 *
	 * @since ??
	 *
	 * @return array<string,mixed>
	 */
	public static function get_preview_data(): array {
		return [
			'items'   => [],
			'total'   => 0,
			'filters' => [],
		];
	}
}
