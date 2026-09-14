<?php
/**
 * Module Library: Table Of Content module preset attributes map.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\TableOfContents;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Class TableOfContentsPresetAttrsMap.
 *
 * @since ??
 */
class TableOfContentsPresetAttrsMap {
	/**
	 * Get preset attributes map for table of contents module.
	 *
	 * @since ??
	 *
	 * @param array  $map         Preset attributes map.
	 * @param string $module_name Module name.
	 *
	 * @return array
	 */
	public static function get_map( array $map, string $module_name ): array {
		if ( 'divi/table-of-contents' !== $module_name ) {
			return $map;
		}

		$map['title.innerContent__text'] = [
			'attrName' => 'title.innerContent',
			'preset'   => 'content',
			'subName'  => 'text',
		];

		return $map;
	}
}
