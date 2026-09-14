<?php
/**
 * Module: MetaPresetAttrsMap class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Meta;

use ET\Builder\Packages\Module\Options\AdminLabel\AdminLabelPresetAttrsMap;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * MetaPresetAttrsMap class.
 *
 * This class provides the static map for the meta preset attributes.
 *
 * @since ??
 */
class MetaPresetAttrsMap {
	/**
	 * Get the map for the meta preset attributes.
	 *
	 * @since ??
	 *
	 * @param string $attr_name The attribute name (e.g., 'module.meta.meta').
	 *
	 * @return array The map for the meta preset attributes.
	 */
	public static function get_map( string $attr_name ) {
		// The meta composite group structure is: meta: { meta: {} }
		// Because the group uses 'element-part' attributeType, getGroupComponentAttrName returns
		// `${elementName}.${elementPart}` which is 'module.meta' (not 'module.meta.meta').
		// adminLabel should stay at its original location (module.meta.adminLabel),
		// while forceVisible should be at the new location (module.meta.meta.forceVisible).
		if ( substr( $attr_name, -10 ) === '.meta.meta' ) {
			// Case: 'module.meta.meta' -> extract 'module.meta' for adminLabel, keep 'module.meta.meta' for forceVisible.
			$admin_label_base_path   = substr( $attr_name, 0, -5 ); // Remove '.meta' to get 'module.meta'.
			$force_visible_base_path = $attr_name; // Keep 'module.meta.meta' for forceVisible.
		} elseif ( 'module.meta' === $attr_name || substr( $attr_name, -5 ) === '.meta' ) {
			// Case: 'module.meta' (normal case for element-part) -> adminLabel at 'module.meta.adminLabel',
			// forceVisible at 'module.meta.meta.forceVisible' (append '.meta' to attr_name).
			$admin_label_base_path   = $attr_name; // Keep 'module.meta' for adminLabel.
			$force_visible_base_path = "{$attr_name}.meta"; // Add '.meta' to get 'module.meta.meta' for forceVisible.
		} else {
			// Case: other -> use as-is for both.
			$admin_label_base_path   = $attr_name;
			$force_visible_base_path = $attr_name;
		}

		// The meta composite group contains both adminLabel and forceVisible fields.
		// adminLabel stays at its original location: module.meta.adminLabel.
		// forceVisible goes to the new location: module.meta.meta.forceVisible.
		$admin_label_attr_name   = "{$admin_label_base_path}.adminLabel";
		$force_visible_attr_name    = "{$force_visible_base_path}.forceVisible";
		$toc_list_heading_attr_name = "{$force_visible_base_path}.tocListHeading";

		// Get the adminLabel mapping.
		$admin_label_map = AdminLabelPresetAttrsMap::get_map( $admin_label_attr_name );

		// Add the forceVisible mapping.
		$force_visible_map = [
			$force_visible_attr_name => [
				'attrName' => $force_visible_attr_name,
				'preset'   => 'meta',
			],
		];

		$toc_list_heading_map = [
			$toc_list_heading_attr_name => [
				'attrName' => $toc_list_heading_attr_name,
				'preset'   => 'meta',
			],
		];

		// Merge both mappings.
		return array_merge( $admin_label_map, $force_visible_map, $toc_list_heading_map );
	}
}
