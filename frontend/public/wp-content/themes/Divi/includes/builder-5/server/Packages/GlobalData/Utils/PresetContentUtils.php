<?php
/**
 * Preset Content Utilities
 *
 * Shared utilities for applying preset IDs to block content.
 * Used by both migration path and regular import path.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\GlobalData\Utils;

use ET\Builder\Packages\GlobalData\GlobalPreset;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Preset Content Utilities
 *
 * @since ??
 */
class PresetContentUtils {

	/**
	 * Apply default imported preset IDs to block content.
	 *
	 * Only assigns presets when modulePreset is missing, empty, or "default".
	 *
	 * @param string $post_content The post content (Gutenberg blocks).
	 * @param array  $default_imported_presets Default imported module preset IDs. Format: [ 'divi/section' => [ 'presetId' => 'abc123', 'moduleName' => 'divi/section' ], ... ].
	 * @return string The updated post content.
	 */
	public static function apply_default_imported_presets_to_content( string $post_content, array $default_imported_presets ): string {
		if ( empty( $post_content ) || empty( $default_imported_presets ) ) {
			return $post_content;
		}

		$blocks = parse_blocks( $post_content );

		if ( empty( $blocks ) ) {
			return $post_content;
		}

		$blocks = self::_apply_default_imported_presets_to_blocks( $blocks, $default_imported_presets );

		return serialize_blocks( $blocks );
	}

	/**
	 * Apply remapped preset IDs to block content.
	 *
	 * Replaces old preset IDs with new ones in modulePreset and groupPreset block attributes.
	 * This handles cases where preset IDs were remapped during import (e.g., reserved IDs
	 * like `_initial` or `default` that were assigned new unique IDs).
	 *
	 * @since ??
	 *
	 * @param string $post_content The post content (Gutenberg blocks).
	 * @param array  $preset_id_mappings Preset ID mappings from process_presets().
	 *     Format: [
	 *         'module' => [ 'divi/heading' => [ '_initial' => 'new_id' ] ],
	 *         'group'  => [ 'group_name'   => [ 'old_id'   => 'new_id' ] ],
	 *     ].
	 *
	 * @return string The updated post content.
	 */
	public static function apply_preset_id_mappings_to_content( string $post_content, array $preset_id_mappings ): string {
		if ( empty( $post_content ) || empty( $preset_id_mappings ) ) {
			return $post_content;
		}

		$blocks = parse_blocks( $post_content );

		if ( empty( $blocks ) ) {
			return $post_content;
		}

		$blocks = self::_apply_preset_id_mappings_to_blocks( $blocks, $preset_id_mappings );

		return serialize_blocks( $blocks );
	}

	/**
	 * Recursively apply remapped preset IDs to blocks.
	 *
	 * @since ??
	 *
	 * @param array $blocks Blocks array.
	 * @param array $preset_id_mappings Preset ID mappings.
	 *
	 * @return array Updated blocks array.
	 */
	private static function _apply_preset_id_mappings_to_blocks( array $blocks, array $preset_id_mappings ): array {
		$module_mappings = $preset_id_mappings['module'] ?? [];
		$group_mappings  = $preset_id_mappings['group'] ?? [];

		foreach ( $blocks as &$block ) {
			$block_name = $block['blockName'] ?? '';
			$attrs      = $block['attrs'] ?? [];

			if ( ! empty( $block_name ) ) {
				// Remap modulePreset.
				if ( isset( $module_mappings[ $block_name ] ) && isset( $attrs['modulePreset'] ) ) {
					$module_preset = $attrs['modulePreset'];
					$id_map        = $module_mappings[ $block_name ];

					if ( ( is_string( $module_preset ) || is_int( $module_preset ) ) && isset( $id_map[ $module_preset ] ) ) {
						$block['attrs']['modulePreset'] = $id_map[ $module_preset ];
					} elseif ( is_array( $module_preset ) ) {
						$block['attrs']['modulePreset'] = array_map(
							function ( $preset_id ) use ( $id_map ) {
								if ( ( is_string( $preset_id ) || is_int( $preset_id ) ) && isset( $id_map[ $preset_id ] ) ) {
									return $id_map[ $preset_id ];
								}

								return $preset_id;
							},
							$module_preset
						);
					}
				}

				// Remap groupPreset.
				if ( ! empty( $group_mappings ) && isset( $attrs['groupPreset'] ) && is_array( $attrs['groupPreset'] ) ) {
					foreach ( $attrs['groupPreset'] as $group_key => $group_data ) {
						if ( ! is_array( $group_data ) || ! isset( $group_data['presetId'] ) ) {
							continue;
						}

						$group_name = $group_data['groupName'] ?? $group_key;
						$preset_id  = $group_data['presetId'];

						$has_valid_group_name_key = is_string( $group_name ) || is_int( $group_name );

						if ( ! $has_valid_group_name_key || ! isset( $group_mappings[ $group_name ] ) || ! is_array( $group_mappings[ $group_name ] ) ) {
							continue;
						}

						$group_id_map = $group_mappings[ $group_name ];

						if ( ( is_string( $preset_id ) || is_int( $preset_id ) ) && isset( $group_id_map[ $preset_id ] ) ) {
							$block['attrs']['groupPreset'][ $group_key ]['presetId'] = $group_id_map[ $preset_id ];
						} elseif ( is_array( $preset_id ) ) {
							$block['attrs']['groupPreset'][ $group_key ]['presetId'] = array_map(
								function ( $single_preset_id ) use ( $group_id_map ) {
									if ( ( is_string( $single_preset_id ) || is_int( $single_preset_id ) ) && isset( $group_id_map[ $single_preset_id ] ) ) {
										return $group_id_map[ $single_preset_id ];
									}

									return $single_preset_id;
								},
								$preset_id
							);
						}
					}
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::_apply_preset_id_mappings_to_blocks(
					$block['innerBlocks'],
					$preset_id_mappings
				);
			}
		}

		return $blocks;
	}

	/**
	 * Recursively apply default imported preset IDs to blocks.
	 *
	 * @param array $blocks Blocks array.
	 * @param array $default_imported_presets Default imported module preset IDs.
	 * @return array Updated blocks array.
	 */
	private static function _apply_default_imported_presets_to_blocks( array $blocks, array $default_imported_presets ): array {
		foreach ( $blocks as &$block ) {
			$block_name = $block['blockName'] ?? '';

			if ( ! empty( $block_name ) ) {
				if ( isset( $default_imported_presets[ $block_name ] ) ) {
					$attrs              = $block['attrs'] ?? [];
					$module_preset_attr = $attrs['modulePreset'] ?? '';

					$normalized = GlobalPreset::normalize_preset_stack( $module_preset_attr );

					if ( empty( $normalized ) ) {
						$block['attrs']['modulePreset'] = $default_imported_presets[ $block_name ]['presetId'];
					}
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::_apply_default_imported_presets_to_blocks(
					$block['innerBlocks'],
					$default_imported_presets
				);
			}
		}

		return $blocks;
	}
}
