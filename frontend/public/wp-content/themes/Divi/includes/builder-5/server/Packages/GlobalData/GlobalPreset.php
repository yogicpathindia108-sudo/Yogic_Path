<?php
/**
 * REST: GlobalPreset class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\GlobalData;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\VisualBuilder\Saving\SavingUtility;
use ET\Builder\Packages\GlobalData\GlobalPresetItem;
use ET\Builder\Packages\GlobalData\GlobalPresetItemGroup;
use ET\Builder\Packages\GlobalData\GlobalPresetItemUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\Conversion\Conversion;
use ET\Builder\Migration\Migration;
use ET\Builder\Framework\Utility\ArrayUtility;
use InvalidArgumentException;
use WP_Block_Type;
use WP_Error;
use ET_Core_PageResource;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;

/**
 * GlobalPreset class.
 *
 * @since ??
 */
class GlobalPreset {

	/**
	 * Store the last legacy preset import result for current request.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	private static $_last_legacy_preset_import_result = [];

	/**
	 * The data cache.
	 *
	 * @since ??
	 *
	 * @var mixed
	 */
	private static $_data = null;

	/**
	 * The legacy data cache.
	 *
	 * @since ??
	 *
	 * @var mixed
	 */
	private static $_legacy_data = null;

	/**
	 * Cache for normalized preset stacks to avoid repeated processing.
	 *
	 * @since ??
	 *
	 * @var array<string, array>
	 */
	private static $_normalized_preset_stack_cache = [];

	/**
	 * Cache for runtime-migrated preset payloads to avoid repeated migration work.
	 *
	 * @since ??
	 *
	 * @var array<string, array>
	 */
	private static $_runtime_migrated_preset_cache = [];

	/**
	 * Check if there are new D4 presets that haven't been converted yet.
	 *
	 * This function compares D4 presets with existing D5 presets to determine
	 * if there are any new presets that need to be converted.
	 *
	 * @param array $d4_presets The D4 presets data.
	 *
	 * @return bool True if there are new D4 presets to convert.
	 */
	private static function _has_new_d4_presets( array $d4_presets ): bool {
		$existing_d5_presets = self::get_data();

		// Build a flat lookup table of all existing D5 preset IDs for O(1) lookups.
		$existing_preset_ids = [];
		if ( isset( $existing_d5_presets['module'] ) ) {
			foreach ( $existing_d5_presets['module'] as $d5_module_data ) {
				if ( isset( $d5_module_data['items'] ) ) {
					$existing_preset_ids = array_merge( $existing_preset_ids, array_keys( $d5_module_data['items'] ) );
				}
			}
		}

		// Convert to hash map for O(1) lookups.
		$existing_preset_ids = array_flip( $existing_preset_ids );

		foreach ( $d4_presets as $d4_module_data ) {
			if ( ! isset( $d4_module_data['presets'] ) || ! is_array( $d4_module_data['presets'] ) ) {
				continue;
			}

			foreach ( $d4_module_data['presets'] as $preset_id => $preset_data ) {
				$is_reserved_legacy_id = in_array( $preset_id, [ '_initial', 'default' ], true );

				// Reserved legacy IDs are intentionally remapped in D5.
				// They are not stable identifiers for "new preset" detection and can
				// otherwise trigger repeated no-op conversions.
				if ( $is_reserved_legacy_id ) {
					continue;
				}

				if ( ! isset( $existing_preset_ids[ $preset_id ] ) ) {
					// Found a D4 preset that doesn't exist in D5.
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get the option name for the global presets.
	 *
	 * @since ??
	 *
	 * @return string The option name.
	 */
	public static function option_name(): string {
		return 'builder_global_presets_d5';
	}

	/**
	 * Get the option name to check the legacy preset's import check.
	 *
	 * @since ??
	 *
	 * @return string The option name.
	 */
	public static function is_legacy_presets_imported_option_name(): string {
		return 'builder_is_legacy_presets_imported_to_d5';
	}

	/**
	 * Delete the data from the DB.
	 *
	 * @since ??
	 */
	public static function delete_data(): void {
		et_delete_option( self::option_name() );

		// Reset the data cache.
		self::$_data                             = null;
		self::$_legacy_data                      = null;
		self::$_last_legacy_preset_import_result = [];
		self::$_normalized_preset_stack_cache    = [];
	}


	/**
	 * Get the data from the DB.
	 *
	 * @since ??
	 *
	 * @return array The data from the DB. The array structure is aligns with GlobalData.Presets.Items TS interface.
	 */
	public static function get_data(): array {
		if ( null !== self::$_data ) {
			return self::$_data;
		}

		$data = et_get_option( self::option_name(), [], '', true, false, '', '', true );

		if ( is_array( $data ) ) {
			self::$_data = $data;
			return $data;
		}

		// Defensive fallback for environments where presets option is still a serialized string.
		if ( is_string( $data ) ) {
			$parsed_data = maybe_unserialize( $data );
			if ( is_array( $parsed_data ) ) {
				self::$_data = $parsed_data;
				return $parsed_data;
			}
		}

		return [];
	}

	/**
	 * Get the data from the DB for legacy presets import check.
	 *
	 * @since ??
	 *
	 * @return string The data from the DB.
	 */
	public static function is_legacy_presets_imported(): string {
		$data = et_get_option( self::is_legacy_presets_imported_option_name(), '', '', true, false, '', '', true );

		return $data;
	}

	/**
	 * Prepare the data to be saved to DB.
	 *
	 * @since ??
	 *
	 * @param array $schema_items The schema items. The array structure is aligns with GlobalData.Presets.RestSchemaItems TS interface.
	 *
	 * @return array Prepared data to be saved to DB. The array structure is aligns with GlobalData.Presets.Items TS interface.
	 */
	public static function prepare_data( array $schema_items ): array {
		$prepared   = [];
		$attrs_keys = [
			'attrs',
			'renderAttrs',
			'styleAttrs',
		];

		foreach ( $schema_items as $preset_type => $schema_item ) {
			if ( ! isset( $prepared[ $preset_type ] ) ) {
				$prepared[ $preset_type ] = [];
			}

			foreach ( $schema_item as $record ) {
				$default = $record['default'];
				$items   = $record['items'];

				foreach ( $items as $item ) {
					if ( 'module' === $preset_type ) {
						$preset_sub_type = $item['moduleName'];
					} elseif ( 'group' === $preset_type ) {
						$preset_sub_type = $item['groupName'];
					}

					if ( ! isset( $prepared[ $preset_type ][ $preset_sub_type ] ) ) {
						$prepared[ $preset_type ][ $preset_sub_type ] = [
							'default' => $default,
							'items'   => [],
						];
					}

					foreach ( $attrs_keys as $key ) {
						if ( isset( $item[ $key ] ) ) {
							$preset_attrs = $item[ $key ];

							if ( ! is_array( $preset_attrs ) ) {
								unset( $item[ $key ] );
								continue;
							}

							$preset_attrs = ModuleUtils::remove_empty_array_attributes( $preset_attrs );

							if ( empty( $preset_attrs ) ) {
								unset( $item[ $key ] );
								continue;
							}

							if ( 'module' === $preset_type ) {
								$item[ $key ] = SavingUtility::sanitize_block_attrs( $preset_attrs, $preset_sub_type );
							} elseif ( 'group' === $preset_type ) {
								$item[ $key ] = SavingUtility::sanitize_group_attrs( $preset_attrs, $preset_sub_type );
							}
						}
					}

					$prepared[ $preset_type ][ $preset_sub_type ]['items'][ $item['id'] ] = $item;
				}
			}
		}

		return $prepared;
	}

	/**
	 * Counts the total number of presets in a preset data structure.
	 *
	 * @since ??
	 *
	 * @param array|null $presets_data The preset data to count. The array structure aligns with GlobalData.Presets.Items TS interface.
	 *
	 * @return array{modulePresets: int, groupPresets: int, total: int} The count of presets.
	 */
	public static function count_presets( ?array $presets_data ): array {
		if ( empty( $presets_data ) || ! is_array( $presets_data ) ) {
			return [
				'modulePresets' => 0,
				'groupPresets'  => 0,
				'total'         => 0,
			];
		}

		// Count module presets.
		$module_presets_count = 0;
		if ( isset( $presets_data['module'] ) && is_array( $presets_data['module'] ) ) {
			foreach ( $presets_data['module'] as $module_preset_group ) {
				if ( isset( $module_preset_group['items'] ) && is_array( $module_preset_group['items'] ) ) {
					$module_presets_count += count( $module_preset_group['items'] );
				}
			}
		}

		// Count group presets.
		$group_presets_count = 0;
		if ( isset( $presets_data['group'] ) && is_array( $presets_data['group'] ) ) {
			foreach ( $presets_data['group'] as $group_preset_group ) {
				if ( isset( $group_preset_group['items'] ) && is_array( $group_preset_group['items'] ) ) {
					$group_presets_count += count( $group_preset_group['items'] );
				}
			}
		}

		return [
			'modulePresets' => $module_presets_count,
			'groupPresets'  => $group_presets_count,
			'total'         => $module_presets_count + $group_presets_count,
		];
	}

	/**
	 * Validates that preset count doesn't decrease unless it's a delete action.
	 *
	 * This is a safety check to prevent accidental preset deletion during sync operations.
	 * Only explicit DELETE actions should reduce the preset count.
	 *
	 * @since ??
	 *
	 * @param array  $current_presets The current presets in the database. The array structure aligns with GlobalData.Presets.Items TS interface.
	 * @param array  $presets_to_sync The presets being saved. The array structure aligns with GlobalData.Presets.Items TS interface.
	 * @param string $action_type The action type being performed (e.g., 'DELETE_MODULE_PRESET', 'SAVE_PRESET', etc.).
	 *
	 * @return WP_Error|null Returns WP_Error if validation fails, null if validation passes.
	 */
	public static function validate_preset_count( array $current_presets, array $presets_to_sync, string $action_type ): ?WP_Error {
		// DELETE actions are allowed to reduce preset count.
		$is_delete_action = 'DELETE_MODULE_PRESET' === $action_type || 'DELETE_OPTION_GROUP_PRESET' === $action_type;

		$current_count = self::count_presets( $current_presets );
		$sync_count    = self::count_presets( $presets_to_sync );

		// If it's a delete action, we allow preset count to decrease.
		if ( $is_delete_action ) {
			return null;
		}

		// Check module and group presets separately first for more detailed error reporting.
		if ( $sync_count['modulePresets'] < $current_count['modulePresets'] ) {
			return new WP_Error(
				'preset_count_decreased',
				sprintf(
					'CRITICAL: Module preset count decreased during sync! Current: %d, Sync: %d. Action: %s',
					$current_count['modulePresets'],
					$sync_count['modulePresets'],
					$action_type
				),
				[
					'status'             => 400,
					'actionType'         => $action_type,
					'currentModuleCount' => $current_count['modulePresets'],
					'syncModuleCount'    => $sync_count['modulePresets'],
					'currentCount'       => $current_count,
					'syncCount'          => $sync_count,
				]
			);
		}

		if ( $sync_count['groupPresets'] < $current_count['groupPresets'] ) {
			return new WP_Error(
				'preset_count_decreased',
				sprintf(
					'CRITICAL: Group preset count decreased during sync! Current: %d, Sync: %d. Action: %s',
					$current_count['groupPresets'],
					$sync_count['groupPresets'],
					$action_type
				),
				[
					'status'            => 400,
					'actionType'        => $action_type,
					'currentGroupCount' => $current_count['groupPresets'],
					'syncGroupCount'    => $sync_count['groupPresets'],
					'currentCount'      => $current_count,
					'syncCount'         => $sync_count,
				]
			);
		}

		// Fallback: Check total count for any other cases.
		if ( $sync_count['total'] < $current_count['total'] ) {
			return new WP_Error(
				'preset_count_decreased',
				sprintf(
					'CRITICAL: Preset count decreased during sync! Current: %d, Sync: %d. Action: %s',
					$current_count['total'],
					$sync_count['total'],
					$action_type
				),
				[
					'status'       => 400,
					'actionType'   => $action_type,
					'currentCount' => $current_count,
					'syncCount'    => $sync_count,
				]
			);
		}

		return null;
	}

	/**
	 * Save the data to DB.
	 *
	 * @since ??
	 *
	 * @param array $data The data to be saved. The array structure is aligns with GlobalData.Presets.Items TS interface.
	 *
	 * @return array The saved data. The array structure is aligns with GlobalData.Presets.Items TS interface.
	 */
	public static function save_data( array $data ): array {
		et_update_option( self::option_name(), $data, false, '', '', true );

		// We need to clear the entire website cache when updating a preset.
		// Preserve VB CSS files to prevent visual builder from losing its styles.
		ET_Core_PageResource::remove_static_resources( 'all', 'all', true, 'all', true );

		// Reset the data cache.
		self::$_data = null;

		return self::get_data();
	}

	/**
	 * Save conversion data to DB for legacy presets import check.
	 *
	 * @since ??
	 *
	 * @param bool $data The data to be saved.
	 *
	 * @return void
	 */
	public static function save_is_legacy_presets_imported( bool $data ): void {
		et_update_option( self::is_legacy_presets_imported_option_name(), $data ? 'yes' : '', false, '', '', true );

		// We need to clear the entire website cache when updating a preset.
		// Preserve VB CSS files to prevent visual builder from losing its styles.
		ET_Core_PageResource::remove_static_resources( 'all', 'all', true, 'all', true );
	}

	/**
	 * Get the legacy D4 global presets data from the DB for presets format.
	 *
	 * @since ??
	 *
	 * @return array The data from the DB. The array structure is in D4 which needs to be used for converting to D5 format.
	 */
	public static function get_legacy_data(): array {
		if ( null !== self::$_legacy_data ) {
			return self::$_legacy_data;
		}

		$all_builder_presets = et_get_option( 'builder_global_presets_ng', (object) [], '', true, false, '', '', true );
		self::$_legacy_data  = [];

		// If there is no global presets then return empty array.
		if ( empty( $all_builder_presets ) ) {
			return self::$_legacy_data;
		}

		foreach ( $all_builder_presets as $module => $module_presets ) {
			$module_presets = is_array( $module_presets ) ? (object) $module_presets : $module_presets;

			if ( ! is_object( $module_presets ) ) {
				continue;
			}

			foreach ( $module_presets->presets as $key => $value ) {
				if ( empty( (array) $value->settings ) ) {
					continue;
				}

				// Convert preset settings object to array format.
				$value_settings  = json_decode( wp_json_encode( $value->settings ), true );
				$value->settings = (array) $value_settings;
				unset( $value->is_temp );

				self::$_legacy_data[ $module ]['presets'][ $key ] = (array) $value;
			}

			// Get the default preset id.
			$default_preset_id = $module_presets->default;

			// If presets are available then only set default preset id.
			if ( ! empty( self::$_legacy_data[ $module ]['presets'] ) ) {
				// Set the default preset id if default preset id is there otherwise set as blank.
				self::$_legacy_data[ $module ]['default'] = $default_preset_id;
			}
		}

		return self::$_legacy_data;
	}

	/**
	 * Retrieve the selected preset from a module.
	 *
	 * For stacked presets, this returns the last (highest priority) preset in the stack.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $moduleName  The module name.
	 *     @type array  $moduleAttrs The module attributes.
	 *     @type array  $allData     The all data. If not provided, it will be fetched using `GlobalPreset::get_data()`.
	 * }
	 *
	 * @throws InvalidArgumentException If the `moduleName` argument is not provided.
	 * @throws InvalidArgumentException If the `moduleAttrs` argument is not provided.
	 *
	 * @return GlobalPresetItem The selected preset instance.
	 */
	public static function get_selected_preset( array $args ): GlobalPresetItem {
		if ( ! isset( $args['moduleName'] ) ) {
			throw new InvalidArgumentException( 'The `moduleName` argument is required.' );
		}

		if ( ! isset( $args['moduleAttrs'] ) ) {
			throw new InvalidArgumentException( 'The `moduleAttrs` argument is required.' );
		}

		// Extract the arguments.
		$module_name  = $args['moduleName'];
		$module_attrs = $args['moduleAttrs'];
		$all_data     = $args['allData'] ?? self::get_data();

		// Convert the module name to the preset module name.
		$module_name_converted = ModuleUtils::maybe_convert_preset_module_name( $module_name, $module_attrs );

		$default_preset_id = $all_data['module'][ $module_name_converted ]['default'] ?? '';
		$preset_value      = $module_attrs['modulePreset'] ?? '';

		// Normalize the preset value to an array.
		$preset_ids = self::normalize_preset_stack( $preset_value );

		// If no presets in the stack, return the default.
		if ( empty( $preset_ids ) ) {
			$default_preset_data = $all_data['module'][ $module_name_converted ]['items'][ $default_preset_id ] ?? [];
			$default_preset_data = self::_maybe_runtime_migrate_preset_data(
				$default_preset_data,
				$module_name_converted
			);

			return new GlobalPresetItem(
				[
					'data'      => $default_preset_data,
					'asDefault' => true,
					'isExist'   => isset( $all_data['module'][ $module_name_converted ]['items'][ $default_preset_id ] ),
				]
			);
		}

		// Get the last preset in the stack (highest priority).
		$preset_id = end( $preset_ids );

		// If the preset ID is found, then use the preset ID.
		if ( isset( $all_data['module'][ $module_name_converted ]['items'][ $preset_id ] ) ) {
			// Check if this preset ID matches the default preset ID.
			// If it does, mark it as default so the CSS selector uses 'default' instead of the actual preset ID.
			// This ensures CSS generation matches the class name generation logic.
			$is_default = self::is_preset_id_as_default( $preset_id, $default_preset_id );

			$preset_data = $all_data['module'][ $module_name_converted ]['items'][ $preset_id ];
			$preset_data = self::_maybe_runtime_migrate_preset_data(
				$preset_data,
				$module_name_converted
			);

			return new GlobalPresetItem(
				[
					'data'      => $preset_data,
					'asDefault' => $is_default,
					'isExist'   => true,
				]
			);
		}

		$default_preset_data = $all_data['module'][ $module_name_converted ]['items'][ $default_preset_id ] ?? [];
		$default_preset_data = self::_maybe_runtime_migrate_preset_data(
			$default_preset_data,
			$module_name_converted
		);

		return new GlobalPresetItem(
			[
				'data'      => $default_preset_data,
				'asDefault' => true,
				'isExist'   => isset( $all_data['module'][ $module_name_converted ]['items'][ $default_preset_id ] ),
			]
		);
	}

	/**
	 * Get merged render attributes from all stacked presets.
	 *
	 * This method merges renderAttrs from all presets in the stack, ensuring that
	 * non-style attributes (HTML structure attributes) from earlier presets are preserved
	 * when later presets don't override them. This fixes the issue where structural
	 * attributes like Image/Icon Placement in the Blurb module were being lost when
	 * multiple presets were stacked.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $moduleName  The module name.
	 *     @type array  $moduleAttrs The module attributes.
	 *     @type array  $allData     The all data. If not provided, it will be fetched using `GlobalPreset::get_data()`.
	 * }
	 *
	 * @throws InvalidArgumentException If the `moduleName` argument is not provided.
	 * @throws InvalidArgumentException If the `moduleAttrs` argument is not provided.
	 *
	 * @return array The merged render attributes from all stacked presets.
	 */
	public static function get_merged_preset_render_attrs( array $args ): array {
		if ( ! isset( $args['moduleName'] ) ) {
			throw new InvalidArgumentException( 'The `moduleName` argument is required.' );
		}

		if ( ! isset( $args['moduleAttrs'] ) ) {
			throw new InvalidArgumentException( 'The `moduleAttrs` argument is required.' );
		}

		// Extract the arguments.
		$module_name  = $args['moduleName'];
		$module_attrs = $args['moduleAttrs'];
		$all_data     = $args['allData'] ?? self::get_data();

		// Convert the module name to the preset module name.
		$module_name_converted = ModuleUtils::maybe_convert_preset_module_name( $module_name, $module_attrs );

		$default_preset_id = $all_data['module'][ $module_name_converted ]['default'] ?? '';
		$preset_value      = $module_attrs['modulePreset'] ?? '';

		// Normalize the preset value to an array for stacked presets.
		$preset_ids = self::normalize_preset_stack( $preset_value );

		// If no presets in the stack, use the default preset if available.
		if ( empty( $preset_ids ) ) {
			if ( ! empty( $default_preset_id ) && isset( $all_data['module'][ $module_name_converted ]['items'][ $default_preset_id ] ) ) {
				$default_preset_data = $all_data['module'][ $module_name_converted ]['items'][ $default_preset_id ];
				$default_preset_data = self::_maybe_runtime_migrate_preset_data(
					$default_preset_data,
					$module_name_converted
				);

				if ( isset( $default_preset_data['renderAttrs'] ) && is_array( $default_preset_data['renderAttrs'] ) ) {
					return $default_preset_data['renderAttrs'];
				}
			}
			return [];
		}

		$merged_render_attrs = [];

		// Collect presets with their priorities.
		$presets_with_priority = [];
		foreach ( $preset_ids as $preset_id ) {
			if ( isset( $all_data['module'][ $module_name_converted ]['items'][ $preset_id ] ) ) {
				$preset_data = $all_data['module'][ $module_name_converted ]['items'][ $preset_id ];
				$preset_data = self::_maybe_runtime_migrate_preset_data(
					$preset_data,
					$module_name_converted
				);
				$priority    = $preset_data['priority'] ?? 10;

				$presets_with_priority[] = [
					'id'       => $preset_id,
					'data'     => $preset_data,
					'priority' => $priority,
				];
			}
		}

		// Sort presets by priority (ascending: lower priority first, higher priority last).
		// This ensures higher priority presets are merged later and take precedence.
		usort(
			$presets_with_priority,
			function ( $a, $b ) {
				return $a['priority'] <=> $b['priority'];
			}
		);

		// Merge renderAttrs from all presets in priority order.
		// Lower priority presets are applied first, higher priority presets override them.
		foreach ( $presets_with_priority as $preset_item ) {
			$preset_data = $preset_item['data'];

			// Check if preset has renderAttrs.
			if ( isset( $preset_data['renderAttrs'] ) && is_array( $preset_data['renderAttrs'] ) ) {
				$merged_render_attrs = array_replace_recursive( $merged_render_attrs, $preset_data['renderAttrs'] );
			}
		}

		return $merged_render_attrs;
	}

	/**
	 * Group selected group preset items by usage groupId.
	 *
	 * @since ??
	 *
	 * @param array $selected_group_presets Selected group presets from get_selected_group_presets().
	 *
	 * @return array<string, GlobalPresetItemGroup[]> Presets grouped by groupId.
	 */
	private static function _group_selected_group_presets_by_group_id( array $selected_group_presets ): array {
		$presets_by_group = [];

		foreach ( $selected_group_presets as $selected_group_preset ) {
			if ( ! ( $selected_group_preset instanceof GlobalPresetItemGroup ) || ! $selected_group_preset->is_exist() ) {
				continue;
			}

			$group_id = $selected_group_preset->get_group_id();

			if ( ! isset( $presets_by_group[ $group_id ] ) ) {
				$presets_by_group[ $group_id ] = [];
			}

			$presets_by_group[ $group_id ][] = $selected_group_preset;
		}

		return $presets_by_group;
	}

	/**
	 * Sort group preset items for merge: nested presets first, then explicit; priority ascending within each segment.
	 *
	 * Mirrors VB mergePresetStack segment ordering (nested before explicit, higher priority merged last).
	 *
	 * @since ??
	 *
	 * @param GlobalPresetItemGroup[] $group_presets Group preset items sharing the same groupId.
	 *
	 * @return GlobalPresetItemGroup[] Sorted preset items.
	 */
	private static function _sort_group_presets_for_merge( array $group_presets ): array {
		$nested_presets   = [];
		$explicit_presets = [];

		foreach ( $group_presets as $preset_item ) {
			if ( $preset_item->is_nested() ) {
				$nested_presets[] = $preset_item;
			} else {
				$explicit_presets[] = $preset_item;
			}
		}

		$sort_by_priority = function ( GlobalPresetItemGroup $a, GlobalPresetItemGroup $b ): int {
			return $a->get_data_priority() <=> $b->get_data_priority();
		};

		usort( $nested_presets, $sort_by_priority );
		usort( $explicit_presets, $sort_by_priority );

		return array_merge( $nested_presets, $explicit_presets );
	}

	/**
	 * Merge selected group preset attrs, renderAttrs, and styleAttrs with priority ordering within each groupId.
	 *
	 * Stacked presets on the same group are sorted by priority (ascending) so higher priority values win.
	 * Nested presets merge before explicit presets within each group, matching VB mergePresetStack behavior.
	 *
	 * @since ??
	 *
	 * @param array $selected_group_presets Selected group presets from get_selected_group_presets().
	 *
	 * @return array{
	 *     attrs: array,
	 *     renderAttrs: array,
	 *     styleAttrs: array,
	 * }
	 */
	public static function merge_selected_group_presets_by_priority( array $selected_group_presets ): array {
		$merged_attrs        = [];
		$merged_render_attrs = [];
		$merged_style_attrs  = [];

		$presets_by_group = self::_group_selected_group_presets_by_group_id( $selected_group_presets );

		foreach ( $presets_by_group as $group_presets ) {
			$sorted_presets = self::_sort_group_presets_for_merge( $group_presets );

			$group_attrs        = [];
			$group_render_attrs = [];
			$group_style_attrs  = [];

			foreach ( $sorted_presets as $preset_item ) {
				$group_attrs        = array_replace_recursive( $group_attrs, $preset_item->get_data_attrs() );
				$group_render_attrs = array_replace_recursive( $group_render_attrs, $preset_item->get_data_render_attrs() );
				$group_style_attrs  = array_replace_recursive( $group_style_attrs, $preset_item->get_data_style_attrs() );
			}

			$merged_attrs        = array_replace_recursive( $merged_attrs, $group_attrs );
			$merged_render_attrs = array_replace_recursive( $merged_render_attrs, $group_render_attrs );
			$merged_style_attrs  = array_replace_recursive( $merged_style_attrs, $group_style_attrs );
		}

		return [
			'attrs'       => $merged_attrs,
			'renderAttrs' => $merged_render_attrs,
			'styleAttrs'  => $merged_style_attrs,
		];
	}

	/**
	 * Get all module preset class names for stacked presets.
	 *
	 * This function returns an array of CSS class names for all presets in the stack,
	 * allowing multiple presets to be applied to a module simultaneously.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $moduleName  The module name.
	 *     @type array  $moduleAttrs The module attributes.
	 *     @type array  $allData     The all data. If not provided, it will be fetched using `GlobalPreset::get_data()`.
	 * }
	 *
	 * @throws InvalidArgumentException If the `moduleName` argument is not provided.
	 * @throws InvalidArgumentException If the `moduleAttrs` argument is not provided.
	 *
	 * @return array An array of preset class names.
	 */
	public static function get_module_preset_class_names( array $args ): array {
		if ( ! isset( $args['moduleName'] ) ) {
			throw new InvalidArgumentException( 'The `moduleName` argument is required.' );
		}

		if ( ! isset( $args['moduleAttrs'] ) ) {
			throw new InvalidArgumentException( 'The `moduleAttrs` argument is required.' );
		}

		// Extract the arguments.
		$module_name  = $args['moduleName'];
		$module_attrs = $args['moduleAttrs'];
		$all_data     = $args['allData'] ?? self::get_data();

		// Convert the module name to the preset module name.
		$module_name_converted = ModuleUtils::maybe_convert_preset_module_name( $module_name, $module_attrs );

		$default_preset_id = $all_data['module'][ $module_name_converted ]['default'] ?? '';
		$preset_value      = $module_attrs['modulePreset'] ?? '';

		// Normalize the preset value to an array.
		$preset_ids = self::normalize_preset_stack( $preset_value );

		// If no presets in the stack, check if default preset should be applied.
		if ( empty( $preset_ids ) ) {
			// Only apply default preset class if it exists and has attributes.
			if ( ! empty( $default_preset_id ) && isset( $all_data['module'][ $module_name_converted ]['items'][ $default_preset_id ]['attrs'] ) ) {
				// Generate class name for default preset.
				$default_class_name = GlobalPresetItemUtils::generate_preset_class_name(
					[
						'presetType'       => 'module',
						'presetModuleName' => $module_name_converted,
						'presetId'         => 'default',
					]
				);

				return ! empty( $default_class_name ) ? [ $default_class_name ] : [];
			}

			return [];
		}

		$class_names = [];

		// Generate class name for each preset in the stack.
		foreach ( $preset_ids as $preset_id ) {
			// Determine if this preset ID is the default.
			$is_default          = self::is_preset_id_as_default( $preset_id, $default_preset_id );
			$effective_preset_id = $is_default ? 'default' : $preset_id;

			// Generate the class name using the utility function.
			$class_name = GlobalPresetItemUtils::generate_preset_class_name(
				[
					'presetType'       => 'module',
					'presetModuleName' => $module_name_converted,
					'presetId'         => $effective_preset_id,
				]
			);

			if ( ! empty( $class_name ) ) {
				$class_names[] = $class_name;
			}
		}

		return $class_names;
	}

	/**
	 * Retrieve the preset item.
	 *
	 * This method is used to find the preset item for a module. It will convert the module name to the preset module name if needed.
	 *
	 * @since ??
	 *
	 * @param string $module_name  The module name.
	 * @param array  $module_attrs The module attributes.
	 * @param array  $default_printed_style_attrs The default printed style attributes.
	 *
	 * @return GlobalPresetItem The preset item instance.
	 */
	public static function get_item( string $module_name, array $module_attrs, array $default_printed_style_attrs = [] ): GlobalPresetItem {
		// TODO feat(D5, Deprecated) Create class for handling deprecating functions / methdos / constructor / classes. [https://github.com/elegantthemes/Divi/issues/41805].
		_deprecated_function( __METHOD__, '5.0.0-public-alpha-9', 'GlobalPreset::get_selected_preset' );

		return self::get_selected_preset(
			[
				'moduleName'               => $module_name,
				'moduleAttrs'              => $module_attrs,
				'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
			]
		);
	}

	/**
	 * Retrieve the preset item by ID.
	 *
	 * @since ??
	 *
	 * @param string $module_name The module name. The module name should be already converted to the preset module name.
	 * @param string $preset_id The module attributes. The preset ID should be the actual preset ID.
	 *
	 * @return GlobalPresetItem The preset item instance.
	 */
	public static function get_item_by_id( string $module_name, string $preset_id ): GlobalPresetItem {
		// TODO feat(D5, Deprecated) Create class for handling deprecating functions / methdos / constructor / classes. [https://github.com/elegantthemes/Divi/issues/41805].
		_deprecated_function( __METHOD__, '5.0.0-public-alpha-9', 'GlobalPreset::get_selected_preset' );

		return self::get_selected_preset(
			[
				'moduleName'               => $module_name,
				'moduleAttrs'              => [
					'modulePreset' => $preset_id,
				],
				'defaultPrintedStyleAttrs' => [],
			]
		);
	}

	/**
	 * Remove a preset from the D4 legacy option.
	 *
	 * This method removes a preset from the D4 legacy option (`builder_global_presets_ng`)
	 * when it's deleted in D5 to prevent re-migration on page refresh.
	 *
	 * @since ??
	 *
	 * @param string $preset_id The preset ID to remove.
	 * @param string $module_name The module name (for module presets) or group name (for group presets).
	 *
	 * @return bool True if the preset was removed or didn't exist, false on error.
	 */
	public static function remove_preset_from_legacy_option( string $preset_id, string $module_name ): bool {
		// Convert D5 module name to D4 shortcode name for legacy data lookup.
		$d4_module_name = self::_convert_d5_to_d4_module_name( $module_name );
		if ( null === $d4_module_name ) {
			return false;
		}

		// Get the current legacy data.
		$legacy_data = self::get_legacy_data();

		// If no legacy data exists, nothing to remove.
		if ( empty( $legacy_data ) || ! isset( $legacy_data[ $d4_module_name ] ) ) {
			return true;
		}

		$module_data = $legacy_data[ $d4_module_name ];

		// Ensure module data is an object for manipulation.
		if ( is_array( $module_data ) ) {
			$module_data = (object) $module_data;
		}

		// Check if the preset exists in the legacy data.
		if ( ! isset( $module_data->presets ) || ! isset( $module_data->presets[ $preset_id ] ) ) {
			// Preset doesn't exist in legacy data, nothing to remove.
			return true;
		}

		// Remove the preset from the legacy data.
		unset( $module_data->presets[ $preset_id ] );

		// Handle edge cases after removal.
		$presets_still_exist = ! empty( (array) $module_data->presets );

		if ( ! $presets_still_exist ) {
			// No presets left for this module/group, remove the entire module entry.
			unset( $legacy_data[ $d4_module_name ] );
		} else {
			// Check if the deleted preset was the default preset.
			if ( isset( $module_data->default ) && $module_data->default === $preset_id ) {
				// Find the first remaining preset to be the new default.
				$remaining_presets    = (array) $module_data->presets;
				$first_preset_id      = key( $remaining_presets );
				$module_data->default = $first_preset_id;
			}

			// Convert presets back to objects to match database format before saving.
			if ( isset( $module_data->presets ) && is_array( $module_data->presets ) ) {
				foreach ( $module_data->presets as $preset_id => $preset_data ) {
					if ( is_array( $preset_data ) ) {
						$module_data->presets[ $preset_id ] = (object) $preset_data;
					}
				}
			}

			// Update the legacy data with the modified module data.
			$legacy_data[ $d4_module_name ] = $module_data;
		}

		// Save the updated legacy data back to the database.
		$result = et_update_option( 'builder_global_presets_ng', $legacy_data, false, '', '', true );

		// Clear the static cache to ensure fresh data on next load.
		self::$_legacy_data = null;

		return false !== $result;
	}

	/**
	 * Get deleted preset IDs by comparing current vs incoming presets.
	 *
	 * This method compares the current D5 presets (before deletion) with the incoming
	 * presets (after deletion) to identify which preset IDs were removed.
	 *
	 * @since ??
	 *
	 * @param array  $current_presets The current presets in the database (before deletion).
	 * @param array  $incoming_presets The incoming presets from the request (after deletion).
	 * @param string $preset_type The preset type ('module' or 'group').
	 *
	 * @return array Array of deleted preset info with module/group names. Format: [['id' => 'preset_id', 'moduleName' => 'module_name'], ...]
	 */
	public static function get_deleted_preset_ids( array $current_presets, array $incoming_presets, string $preset_type ): array {
		$deleted_presets = [];

		// Get current preset IDs for this type.
		$current_preset_ids = [];
		if ( isset( $current_presets[ $preset_type ] ) ) {
			foreach ( $current_presets[ $preset_type ] as $module_name => $module_data ) {
				if ( isset( $module_data['items'] ) && is_array( $module_data['items'] ) ) {
					foreach ( array_keys( $module_data['items'] ) as $preset_id ) {
						$current_preset_ids[ $preset_id ] = $module_name;
					}
				}
			}
		}

		// Get incoming preset IDs for this type.
		$incoming_preset_ids = [];
		if ( isset( $incoming_presets[ $preset_type ] ) ) {
			foreach ( $incoming_presets[ $preset_type ] as $module_name => $module_data ) {
				if ( isset( $module_data['items'] ) && is_array( $module_data['items'] ) ) {
					foreach ( array_keys( $module_data['items'] ) as $preset_id ) {
						$incoming_preset_ids[ $preset_id ] = $module_name;
					}
				}
			}
		}

		// Find preset IDs that exist in current but not in incoming (deletions).
		foreach ( $current_preset_ids as $preset_id => $module_name ) {
			if ( ! isset( $incoming_preset_ids[ $preset_id ] ) ) {
				$deleted_presets[] = [
					'id'         => $preset_id,
					'moduleName' => $module_name,
				];
			}
		}

		return $deleted_presets;
	}

	/**
	 * Convert D5 module name back to D4 shortcode name for legacy data lookup.
	 *
	 * This method maps D5 module names (e.g., 'divi/blurb') back to their D4 shortcode
	 * equivalents (e.g., 'et_pb_blurb') for looking up presets in legacy data.
	 *
	 * @since ??
	 *
	 * @param string $d5_module_name The D5 module name.
	 *
	 * @return string|null The D4 shortcode name, or null if no mapping found.
	 */
	private static function _convert_d5_to_d4_module_name( string $d5_module_name ): ?string {
		// Get the module collections to find the reverse mapping.
		$module_collections = \ET\Builder\Packages\Conversion\Conversion::getModuleCollections();

		foreach ( $module_collections as $module ) {
			if ( isset( $module['name'] ) && $module['name'] === $d5_module_name && isset( $module['d4Shortcode'] ) ) {
				return $module['d4Shortcode'];
			}
		}

		// Handle special cases for sections.
		if ( 'divi/fullwidth-section' === $d5_module_name ) {
			return 'et_pb_section_fullwidth';
		}
		if ( 'divi/specialty-section' === $d5_module_name ) {
			return 'et_pb_section_specialty';
		}

		return null;
	}

	/**
	 * Check if a preset ID already exists across all modules in the current preset data.
	 *
	 * This method checks for ID collisions to determine if we need to generate
	 * a new ID or can preserve the original preset ID during conversion.
	 *
	 * @since ??
	 *
	 * @param string $preset_id The preset ID to check for collision.
	 * @param array  $existing_presets All existing presets data.
	 *
	 * @return bool True if collision detected, false if ID is available.
	 */
	private static function _has_preset_id_collision( string $preset_id, array $existing_presets ): bool {
		// Check module presets for collision.
		foreach ( $existing_presets['module'] ?? [] as $module_presets ) {
			if ( isset( $module_presets['items'][ $preset_id ] ) ) {
				return true;
			}
		}

		// Check group presets for collision.
		foreach ( $existing_presets['group'] ?? [] as $group_presets ) {
			if ( isset( $group_presets['items'][ $preset_id ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a preset has content (styles or attributes).
	 *
	 * This helper safely checks if a preset contains actual styling data
	 * by examining the styleAttrs and attrs properties.
	 *
	 * @since ??
	 *
	 * @param array $preset The preset data to check.
	 * @return bool True if preset has content, false if empty.
	 */
	private static function _preset_has_content( array $preset ): bool {
		// Check styleAttrs.
		if ( isset( $preset['styleAttrs'] ) && is_array( $preset['styleAttrs'] ) && ! empty( $preset['styleAttrs'] ) ) {
			// Check if styleAttrs has any non-empty values.
			foreach ( $preset['styleAttrs'] as $value ) {
				if ( ! empty( $value ) || 0 === $value || '0' === $value ) {
					return true;
				}
			}
		}

		// Check attrs.
		if ( isset( $preset['attrs'] ) && is_array( $preset['attrs'] ) && ! empty( $preset['attrs'] ) ) {
			// Check if attrs has any non-empty values.
			foreach ( $preset['attrs'] as $value ) {
				if ( ! empty( $value ) || 0 === $value || '0' === $value ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if two presets have identical content.
	 *
	 * @param array $preset The preset data to compare.
	 * @param array $other_preset The preset data to compare against.
	 * @return bool True if content matches, false otherwise.
	 */
	private static function _preset_content_matches( array $preset, array $other_preset ): bool {
		$preset_attrs       = $preset['attrs'] ?? [];
		$other_preset_attrs = $other_preset['attrs'] ?? [];

		if ( $preset_attrs !== $other_preset_attrs ) {
			return false;
		}

		$preset_style       = $preset['styleAttrs'] ?? [];
		$other_preset_style = $other_preset['styleAttrs'] ?? [];

		return $preset_style === $other_preset_style;
	}

	/**
	 * Process the presets with ID collision handling.
	 * This function handles server-side preset processing (replaces the former client-side `processPresets`)
	 * to use during Readiness migration. This function takes an array of converted D5 presets and processes them by merging them with the existing presets.
	 *
	 * IMPORTANT: This now preserves original preset IDs to maintain compatibility with
	 * existing site content that references those IDs, especially during D4→D5 conversion.
	 * New IDs are only generated when there's an actual collision with existing preset IDs.
	 *
	 * @param array $presets The array of presets to be processed.
	 * @param bool  $is_migration Whether the presets were converted from D4. Default false.
	 * @return array The processed presets with newIds mapping for preset ID replacement in content.
	 */
	public static function process_presets( $presets, bool $is_migration = false ) {
		$processed_presets               = [
			'module' => [],
			'group'  => [],
		];
		$all_presets                     = self::get_data();
		$new_ids                         = [
			'module' => [],
			'group'  => [],
		];
		$default_imported_module_presets = [];
		$default_imported_group_presets  = [];

		// Process module presets.
		if ( isset( $presets['module'] ) && is_array( $presets['module'] ) ) {
			foreach ( $presets['module'] as $module_name => $preset_items ) {
				if ( empty( $module_name ) ) {
					continue;
				}

				// Skip if preset_items is not an array or doesn't have 'items' key.
				if ( ! is_array( $preset_items ) || ! isset( $preset_items['items'] ) || ! is_array( $preset_items['items'] ) ) {
					continue;
				}

				$processed_items      = [];
				$processed_default_id = '';

				// Check all existing presets for this module.
				$existing_module_data = $all_presets['module'][ $module_name ] ?? [];
				$existing_presets     = $existing_module_data['items'] ?? [];
				$existing_default_id  = $existing_module_data['default'] ?? '';
				$has_d5_default       = ! empty( $existing_default_id ) && isset( $existing_presets[ $existing_default_id ] );

				foreach ( $preset_items['items'] as $item_id => $item ) {
					if ( empty( $item ) ) {
						continue;
					}

					$current_timestamp   = time();
					$preset_name         = $item['name'];
					$is_duplicate        = false;
					$name_conflict_found = false;

					// Migrate incoming preset for duplicate/content comparison only.
					$migrated_incoming_item = Migration::get_instance()->migrate_preset_item( $item, $module_name );
					if ( ! is_array( $migrated_incoming_item ) ) {
						$migrated_incoming_item = [];
					}

					$should_update_existing = false;
					$existing_preset_data   = null;
					$default_preset_id      = $preset_items['default'] ?? '';
					$is_default_preset      = $default_preset_id === $item_id;
					$is_migration_default   = $is_migration && '_initial' === $default_preset_id && $item_id === $default_preset_id;
					$should_remap_default   = $is_migration_default && $has_d5_default;
					$is_reserved_id         = '_initial' === $item_id || 'default' === $item_id;

					if ( $should_remap_default ) {
						foreach ( $existing_presets as $existing_id => $existing_preset ) {
							if ( self::_preset_content_matches( $migrated_incoming_item, $existing_preset ) ) {
								// Reuse existing preset to avoid creating duplicates.
								if ( ! isset( $new_ids['module'][ $module_name ] ) ) {
									$new_ids['module'][ $module_name ] = [];
								}
								$new_ids['module'][ $module_name ][ $item_id ]   = $existing_id;
								$default_imported_module_presets[ $module_name ] = [
									'presetId'   => $existing_id,
									'moduleName' => $module_name,
								];
								continue 2;
							}
						}
					}

					foreach ( $existing_presets as $existing_id => $existing_preset ) {
						$existing_name = $existing_preset['name'];

						// Check for exact duplicate (same name AND same ID).
						if ( $preset_name === $existing_name && $item_id === $existing_id ) {
							// Reserved IDs should never be treated as true duplicates because
							// the existing DB entry also has a broken ID that needs remapping.
							if ( $is_reserved_id ) {
								$name_conflict_found = true;
								continue;
							}

							if ( $is_migration_default ) {
								$name_conflict_found = true;
								continue;
							}

							if ( $is_default_preset ) {
								// For default presets, check if existing is empty but incoming has content.
								// This handles the case where a previous migration failed after creating an empty preset.
								// Or DB has empty preset but incoming has content.
								$incoming_has_content = self::_preset_has_content( $migrated_incoming_item );
								$existing_has_content = self::_preset_has_content( $existing_preset );

								if ( $incoming_has_content && ! $existing_has_content ) {
									// Incoming has styles but existing is empty → Update existing instead of skipping.
									$should_update_existing = true;
									$existing_preset_data   = $existing_preset;
									break;
								} else {
									// Both have content, both empty, or incoming empty → True duplicate, skip.
									$is_duplicate = true;
									break;
								}
							} else {
								// For custom presets, same name + same ID = true duplicate.
								$is_duplicate = true;
								break;
							}
						} elseif ( $preset_name === $existing_name ) {
							// Name conflict but different ID.
							$name_conflict_found = true;
						}
					}

					// Update existing preset if incoming has styles but existing doesn't.
					if ( $should_update_existing && $existing_preset_data ) {
						// Preserve existing preset metadata (created timestamp, ID).
						$created_timestamp           = $existing_preset_data['created'] ?? $current_timestamp;
						$processed_items[ $item_id ] = array_merge(
							$item,
							[
								'id'      => $item_id,
								'name'    => $preset_name,
								'created' => $created_timestamp,
								'updated' => $current_timestamp,
							]
						);

						// Track default preset for default assignment (same as regular processing).
						if ( $is_default_preset ) {
							$default_imported_module_presets[ $module_name ] = [
								'presetId'   => $item_id,
								'moduleName' => $module_name,
							];
						}
						// Preserve D4 default as D5 default when no D5 default exists.
						if ( $is_default_preset && $is_migration && ! $has_d5_default ) {
							$processed_default_id = $item_id;
						}

						continue; // Move to next preset.
					}

					// Skip if this is a true duplicate (same name + same ID + same/no content).
					if ( $is_duplicate ) {
						continue;
					}

					// Handle name conflicts by adding "imported" suffix.
					$final_name = $name_conflict_found ? $preset_name . ' imported' : $preset_name;

					// Try to preserve original preset ID to maintain compatibility with existing content.
					// Only generate new ID if there's an actual collision with existing presets,
					// or if the ID is a reserved system value that would be filtered out everywhere.
					$final_id = $item_id;

					if ( $should_remap_default ) {
						$final_id = uniqid();
					} elseif ( $is_reserved_id || self::_has_preset_id_collision( $item_id, $all_presets ) ) {
						$final_id = uniqid();
					}

					if ( $final_id !== $item_id ) {
						// Track the mapping from original ID to new ID for content replacement.
						if ( ! isset( $new_ids['module'][ $module_name ] ) ) {
							$new_ids['module'][ $module_name ] = [];
						}
						$new_ids['module'][ $module_name ][ $item_id ] = $final_id;
					}

					// Track defaults for import, but only track migration defaults when remap is needed.
					$should_track_default_import = $is_default_preset && ( ! $is_migration || $should_remap_default );
					if ( $should_track_default_import ) {
						$default_imported_module_presets[ $module_name ] = [
							'presetId'   => $final_id,
							'moduleName' => $module_name,
						];
					}
					// Preserve D4 default as D5 default when no D5 default exists.
					if ( $is_default_preset && $is_migration && ! $has_d5_default ) {
						$processed_default_id = $final_id;
					}

					// Persist imported preset and let full preset migrations run after save.
					$processed_items[ $final_id ] = array_merge(
						$item,
						[
							'id'      => $final_id,
							'name'    => $final_name,
							'created' => $current_timestamp,
							'updated' => $current_timestamp,
						]
					);
				}

				// Only return processed presets if any were actually processed (not duplicates).
				if ( ! empty( $processed_items ) ) {
					$processed_presets['module'][ $module_name ] = [
						'items' => $processed_items,
					];

					if ( $is_migration && $processed_default_id ) {
						$processed_presets['module'][ $module_name ]['default'] = $processed_default_id;
					}
				}
			}
		}

		// Process group presets.
		if ( isset( $presets['group'] ) && is_array( $presets['group'] ) ) {
			foreach ( $presets['group'] as $group_name => $preset_items ) {
				if ( empty( $group_name ) ) {
					continue;
				}

				// Skip if preset_items is not an array or doesn't have 'items' key.
				if ( ! is_array( $preset_items ) || ! isset( $preset_items['items'] ) || ! is_array( $preset_items['items'] ) ) {
					continue;
				}

				$processed_items = [];

				foreach ( $preset_items['items'] as $item_id => $item ) {
					if ( empty( $item ) ) {
						continue;
					}

					// Get preset name for duplicate checking.
					$preset_name = $item['name'] ?? '';

					// Get module name from the preset item for migration.
					$module_name = $item['moduleName'] ?? '';

					// Migrate incoming preset for duplicate/content comparison only.
					$migrated_incoming_item = Migration::get_instance()->migrate_preset_item( $item, $module_name );
					if ( ! is_array( $migrated_incoming_item ) ) {
						$migrated_incoming_item = [];
					}

					// Check existing presets for this group to detect duplicates/conflicts.
					$existing_presets    = $all_presets['group'][ $group_name ]['items'] ?? [];
					$is_duplicate        = false;
					$name_conflict_found = false;
					$is_reserved_id      = '_initial' === $item_id || 'default' === $item_id;

					foreach ( $existing_presets as $existing_id => $existing_preset ) {
						$existing_name = $existing_preset['name'];

						// Check for exact duplicate (same name AND same ID).
						if ( $preset_name === $existing_name && $item_id === $existing_id ) {
							// Reserved IDs should never be treated as true duplicates because
							// the existing DB entry also has a broken ID that needs remapping.
							if ( $is_reserved_id ) {
								$name_conflict_found = true;
								continue;
							}
							$is_duplicate = true;
							break;
						} elseif ( $preset_name === $existing_name ) {
							// Name conflict but different ID.
							$name_conflict_found = true;
						}
					}

					// Skip if this is a true duplicate (same name + same ID).
					if ( $is_duplicate ) {
						continue;
					}

					// Check if this is a default preset.
					$is_default_preset = isset( $preset_items['default'] ) && $preset_items['default'] === $item_id;

					// Handle name conflicts by adding "imported" suffix.
					// Default presets get the same treatment as regular presets.
					$final_name = $name_conflict_found ? $preset_name . ' imported' : $preset_name;

					// Try to preserve original preset ID to maintain compatibility.
					// Only generate new ID if there's an actual collision with existing presets,
					// or if the ID is a reserved system value that would be filtered out everywhere.
					$final_id = $item_id;

					if ( $is_reserved_id || self::_has_preset_id_collision( $item_id, $all_presets ) ) {
						$final_id = uniqid();

						// Track the mapping from original ID to new ID for content replacement.
						// Use nested structure to match preset organization and avoid collisions.
						if ( ! isset( $new_ids['group'][ $group_name ] ) ) {
							$new_ids['group'][ $group_name ] = [];
						}
						$new_ids['group'][ $group_name ][ $item_id ] = $final_id;
					}

					// Track default imported group presets (consistent structure with module presets).
					if ( $is_default_preset ) {
						$default_imported_group_presets[ $group_name ] = [
							'presetId'  => $final_id,
							'groupName' => $group_name, // Use group name for semantic clarity.
						];
					}

					$current_timestamp = time();

					// Persist imported preset and let full preset migrations run after save.
					$processed_items[ $final_id ] = array_merge(
						$item,
						[
							'id'      => $final_id,
							'name'    => $final_name,
							'created' => $current_timestamp,
							'updated' => $current_timestamp,
						]
					);
				}

				// Only return processed presets if any were actually processed (not duplicates).
				if ( ! empty( $processed_items ) ) {
					$processed_presets['group'][ $group_name ] = [
						'items' => $processed_items,
					];
				}
			}
		}

		if ( ! empty( $new_ids['group'] ) ) {
			$processed_presets = self::_apply_group_preset_id_mappings_to_processed_presets(
				$processed_presets,
				$new_ids['group']
			);
		}

		return [
			'presets'                        => $processed_presets,
			'newIds'                         => $new_ids,
			'defaultImportedModulePresetIds' => $default_imported_module_presets,
			'defaultImportedGroupPresetIds'  => $default_imported_group_presets,
		];
	}

	/**
	 * Apply remapped group preset IDs to imported preset payloads.
	 *
	 * This keeps nested `groupPreset` references inside module/group presets valid
	 * when imported group preset IDs are rewritten due collisions or reserved IDs.
	 *
	 * @since ??
	 *
	 * @param array $processed_presets Processed presets to be saved.
	 * @param array $group_mappings Group preset ID mappings keyed by group name.
	 *
	 * @return array Processed presets with nested references remapped.
	 */
	private static function _apply_group_preset_id_mappings_to_processed_presets( array $processed_presets, array $group_mappings ): array {
		// Remap group preset references nested under module presets.
		foreach ( $processed_presets['module'] ?? [] as $module_name => $module_data ) {
			$items = $module_data['items'] ?? [];
			if ( ! is_array( $items ) ) {
				continue;
			}

			foreach ( $items as $preset_id => $preset_item ) {
				if ( ! is_array( $preset_item ) ) {
					continue;
				}

				$group_presets = $preset_item['groupPresets'] ?? [];
				if ( ! is_array( $group_presets ) ) {
					continue;
				}

				$processed_presets['module'][ $module_name ]['items'][ $preset_id ]['groupPresets'] = self::_remap_group_preset_references(
					$group_presets,
					$group_mappings
				);
			}
		}

		// Remap group preset references nested under group preset attrs.
		foreach ( $processed_presets['group'] ?? [] as $group_name => $group_data ) {
			$items = $group_data['items'] ?? [];
			if ( ! is_array( $items ) ) {
				continue;
			}

			foreach ( $items as $preset_id => $preset_item ) {
				if ( ! is_array( $preset_item ) ) {
					continue;
				}

				$group_presets = $preset_item['attrs']['groupPreset'] ?? [];
				if ( ! is_array( $group_presets ) ) {
					continue;
				}

				$processed_presets['group'][ $group_name ]['items'][ $preset_id ]['attrs']['groupPreset'] = self::_remap_group_preset_references(
					$group_presets,
					$group_mappings
				);
			}
		}

		return $processed_presets;
	}

	/**
	 * Remap presetId values for a groupPreset reference map.
	 *
	 * @since ??
	 *
	 * @param array $group_preset_references Group preset references keyed by group ID.
	 * @param array $group_mappings Group preset ID mappings keyed by group name.
	 *
	 * @return array Updated group preset references.
	 */
	private static function _remap_group_preset_references( array $group_preset_references, array $group_mappings ): array {
		foreach ( $group_preset_references as $group_id => $group_ref ) {
			if ( ! is_array( $group_ref ) ) {
				continue;
			}

			$group_name      = $group_ref['groupName'] ?? '';
			$preset_id_value = $group_ref['presetId'] ?? null;
			$id_map          = $group_mappings[ $group_name ] ?? null;
			if ( ! is_string( $group_name ) || '' === $group_name || ! is_array( $id_map ) || null === $preset_id_value ) {
				continue;
			}

			$preset_ids = self::_extract_preset_ids_for_remap( $preset_id_value );
			if ( empty( $preset_ids ) ) {
				continue;
			}

			$remapped_ids = array_values(
				array_unique(
					array_map(
						function ( string $preset_id ) use ( $id_map ): string {
							return $id_map[ $preset_id ] ?? $preset_id;
						},
						$preset_ids
					)
				)
			);
			if ( empty( $remapped_ids ) ) {
				continue;
			}

			$group_preset_references[ $group_id ]['presetId'] = is_array( $preset_id_value )
				? $remapped_ids
				: $remapped_ids[0];
		}

		return $group_preset_references;
	}

	/**
	 * Extract preset IDs from legacy string or stacked array formats.
	 *
	 * Unlike normalize_preset_stack(), this keeps reserved IDs so they can be
	 * remapped when import replaced them with generated IDs.
	 *
	 * @since ??
	 *
	 * @param mixed $preset_id_value Raw presetId value from preset payload.
	 *
	 * @return string[] Preset IDs eligible for remapping.
	 */
	private static function _extract_preset_ids_for_remap( $preset_id_value ): array {
		if ( is_string( $preset_id_value ) ) {
			$preset_id = trim( $preset_id_value );
			return '' === $preset_id ? [] : [ $preset_id ];
		}

		if ( ! is_array( $preset_id_value ) ) {
			return [];
		}

		$ids = [];
		foreach ( $preset_id_value as $preset_id ) {
			if ( ! is_scalar( $preset_id ) ) {
				continue;
			}

			$preset_id_as_string = trim( (string) $preset_id );
			if ( '' === $preset_id_as_string ) {
				continue;
			}

			$ids[] = $preset_id_as_string;
		}

		return $ids;
	}


	/**
	 * Convert D4 presets to D5 format if not already converted.
	 *
	 * This is a shared utility method used by both the D5 readiness system
	 * and the visual builder settings system for consistent D4→D5 preset conversion.
	 *
	 * @since ??
	 *
	 * @return bool True if conversion was performed, false if already converted or no D4 presets found.
	 */
	public static function maybe_convert_legacy_presets(): bool {
		// Get the legacy global presets settings of D4.
		$d4_presets = self::get_legacy_data();
		if ( empty( $d4_presets ) ) {
			return false;
		}

		// Check if the legacy presets are already imported.
		$is_legacy_presets_imported = self::is_legacy_presets_imported();
		if ( ! empty( $is_legacy_presets_imported ) ) {
			// Legacy presets were imported before, but check if there are NEW D4 presets
			// that were added since the last migration (e.g., from importing another layout).
			// This allows multiple migrations when new content is added to D4.
			$new_presets_found = self::_has_new_d4_presets( $d4_presets );
			if ( ! $new_presets_found ) {
				return false;
			}
			// Continue with migration since new D4 presets were found.
		}

		// Ensure shortcode framework is initialized for conversion.
		Conversion::initialize_shortcode_framework();

		/**
		 * Fires before D4 to D5 preset conversion begins.
		 *
		 * This action allows other components to prepare for the D4 to D5 conversion process.
		 * It's particularly important for ensuring that all necessary module definitions
		 * and dependencies are properly initialized before the conversion starts.
		 *
		 * This hook is fired during the legacy preset conversion process, specifically
		 * after the shortcode framework has been initialized but before the actual
		 * preset processing and conversion begins.
		 *
		 * @since ??
		 *
		 * @hook divi_visual_builder_before_d4_conversion
		 */
		do_action( 'divi_visual_builder_before_d4_conversion' );

		// Use the core preset processing workflow to handle conversion, merge, and save.
		$import_result                           = self::process_presets_for_import( $d4_presets, true );
		self::$_last_legacy_preset_import_result = is_array( $import_result ) ? $import_result : [];

		// Mark legacy presets as imported to prevent duplicate conversion.
		self::save_is_legacy_presets_imported( true );

		return true;
	}

	/**
	 * Get the last legacy preset import result for current request.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function get_last_legacy_preset_import_result(): array {
		return self::$_last_legacy_preset_import_result;
	}

	/**
	 * Process presets and merge with existing preset data.
	 *
	 * This utility handles the complete preset processing workflow:
	 * 1. Process incoming presets (deduplication, renaming)
	 * 2. Get existing presets from database
	 * 3. Merge processed presets with existing presets
	 *
	 * @since ??
	 *
	 * @param array $incoming_presets The raw presets to process and merge.
	 * @param bool  $is_migration Whether the presets were converted from D4. Default false.
	 * @return array The complete merged preset data with preset_id_mappings.
	 */
	public static function merge_new_presets_with_existing( array $incoming_presets, bool $is_migration = false ): array {
		// Step 1: Process incoming presets (deduplication, renaming).
		$processed_result   = self::process_presets( $incoming_presets, $is_migration );
		$processed_presets  = $processed_result['presets'];
		$preset_id_mappings = $processed_result['newIds'];

		// Step 2: Get existing presets from database.
		$existing_presets = self::get_data();

		// Step 3: If no processed presets to merge, return existing presets unchanged.
		if ( empty( $processed_presets['module'] ) && empty( $processed_presets['group'] ) ) {
			return [
				'presets'            => $existing_presets,
				'preset_id_mappings' => [],
			];
		}

		// Step 4: Merge processed presets with existing presets.
		foreach ( $processed_presets['module'] as $module_name => $module_data ) {
			// Initialize module if it doesn't exist.
			if ( ! isset( $existing_presets['module'][ $module_name ] ) ) {
				$existing_presets['module'][ $module_name ] = [
					'items'   => [],
					'default' => '',
				];
			}

			// Merge processed items with existing items.
			$existing_items                                      = $existing_presets['module'][ $module_name ]['items'] ?? [];
			$processed_items                                     = $module_data['items'] ?? [];
			$existing_presets['module'][ $module_name ]['items'] = array_merge( $existing_items, $processed_items );
			// Preserve incoming default only when no valid D5 default exists.
			$processed_default_id = $module_data['default'] ?? '';
			$existing_default_id  = $existing_presets['module'][ $module_name ]['default'] ?? '';
			$has_valid_default    = ! empty( $existing_default_id ) && isset( $existing_presets['module'][ $module_name ]['items'][ $existing_default_id ] );
			if ( ! $has_valid_default && ! empty( $processed_default_id ) ) {
				$existing_presets['module'][ $module_name ]['default'] = $processed_default_id;
			}
		}

		// Step 5: Merge processed group presets with existing presets.
		foreach ( $processed_presets['group'] as $group_name => $group_data ) {
			// Initialize group if it doesn't exist.
			if ( ! isset( $existing_presets['group'][ $group_name ] ) ) {
				$existing_presets['group'][ $group_name ] = [
					'items'   => [],
					'default' => '',
				];
			}

			// Merge processed items with existing items.
			$existing_items                                    = $existing_presets['group'][ $group_name ]['items'] ?? [];
			$processed_items                                   = $group_data['items'] ?? [];
			$existing_presets['group'][ $group_name ]['items'] = array_merge( $existing_items, $processed_items );
		}

		return [
			'presets'                        => $existing_presets,
			'preset_id_mappings'             => $preset_id_mappings,
			'defaultImportedModulePresetIds' => $processed_result['defaultImportedModulePresetIds'] ?? [],
			'defaultImportedGroupPresetIds'  => $processed_result['defaultImportedGroupPresetIds'] ?? [],
		];
	}

	/**
	 * Create default presets for modules that have presets but no default preset ID.
	 *
	 * This replicates the client-side addDefaultModulePreset logic.
	 * After importing D4 presets, modules need empty default presets created.
	 *
	 * @since ??
	 *
	 * @param array $processed_presets The processed presets that were just imported.
	 * @return array The updated presets data with any new default presets added.
	 */
	public static function maybe_create_default_presets_after_import( array $processed_presets ): array {
		$current_data      = $processed_presets;
		$current_timestamp = time();

		// Check each module that had presets imported.
		foreach ( $processed_presets['module'] ?? [] as $module_name => $module_presets ) {
			// Check if this module now has presets but no valid default preset ID.
			$existing_module_data = $current_data['module'][ $module_name ] ?? [];
			$has_presets          = ! empty( $existing_module_data['items'] );
			$default_id           = $existing_module_data['default'] ?? '';
			$has_valid_default    = ! empty( $default_id ) && isset( $existing_module_data['items'][ $default_id ] );

			// If module has presets but no valid default, create a new default preset.
			if ( $has_presets && ! $has_valid_default ) {
				// Get module label from module registration.
				$module_label = self::_get_module_label_for_preset( $module_name );

				// Generate a new default preset.
				$default_preset = self::_generate_new_preset(
					$existing_module_data['items'] ?? [],
					$module_label,
					$module_name,
					$current_timestamp
				);

				// Add the new default preset to current data.
				$current_data['module'][ $module_name ]['items'][ $default_preset['id'] ] = $default_preset;
				$current_data['module'][ $module_name ]['default']                        = $default_preset['id'];
			}
		}

		// Check each group that had presets imported.
		foreach ( $processed_presets['group'] ?? [] as $group_name => $group_presets ) {
			// Check if this group now has presets but no valid default preset ID.
			$existing_group_data = $current_data['group'][ $group_name ] ?? [];
			$has_presets         = ! empty( $existing_group_data['items'] );
			$default_id          = $existing_group_data['default'] ?? '';
			$has_valid_default   = ! empty( $default_id ) && isset( $existing_group_data['items'][ $default_id ] );

			// If group has presets but no valid default, create a new default preset.
			if ( $has_presets && ! $has_valid_default ) {
				// Get group label from group name (e.g., 'divi/font' -> 'Font').
				$group_label = self::_get_group_label_for_preset( $group_name );

				// Extract module name and group ID from the first preset item if available.
				$first_preset_item = reset( $existing_group_data['items'] );
				$module_name       = $first_preset_item['moduleName'] ?? '';
				$group_id          = $first_preset_item['groupId'] ?? '';

				// Generate a new default group preset.
				$default_preset = self::_generate_new_group_preset(
					$existing_group_data['items'] ?? [],
					$group_label,
					$group_name,
					$module_name,
					$group_id,
					$current_timestamp
				);

				// Add the new default preset to current data.
				$current_data['group'][ $group_name ]['items'][ $default_preset['id'] ] = $default_preset;
				$current_data['group'][ $group_name ]['default']                        = $default_preset['id'];
			}
		}

		return $current_data;
	}

	/**
	 * Generate a new default preset for a module.
	 *
	 * PHP equivalent of the client-side generateNewPreset function.
	 *
	 * @since ??
	 *
	 * @param array  $existing_presets Existing preset items for the module.
	 * @param string $module_label The module label.
	 * @param string $module_name The module name.
	 * @param int    $timestamp Current timestamp.
	 * @return array The new preset item.
	 */
	private static function _generate_new_preset( array $existing_presets, string $module_label, string $module_name, int $timestamp ): array {
		// Generate unique ID and name.
		$preset_info = self::_generate_default_preset_name( $existing_presets, $module_label );

		return [
			'id'         => $preset_info['id'],
			'name'       => $preset_info['name'],
			'moduleName' => $module_name,
			'version'    => ET_BUILDER_VERSION,
			'type'       => 'module',
			'created'    => $timestamp,
			'updated'    => $timestamp,
		];
	}

	/**
	 * Generate a unique preset ID and name based on existing presets.
	 *
	 * PHP equivalent of the client-side generateDefaultPresetName function.
	 *
	 * @since ??
	 *
	 * @param array  $presets Existing preset items.
	 * @param string $prefix_label The module label prefix.
	 * @return array Array with 'id' and 'name' keys.
	 */
	private static function _generate_default_preset_name( array $presets, string $prefix_label ): array {
		$highest_number = 0;
		$regex_pattern  = '/^' . preg_quote( $prefix_label, '/' ) . ' (\d+)$/i';

		// Find the highest number in existing preset names.
		foreach ( $presets as $preset ) {
			$preset_name = $preset['name'] ?? '';
			if ( preg_match( $regex_pattern, $preset_name, $matches ) ) {
				$highest_number = max( $highest_number, (int) $matches[1] );
			}
		}

		return [
			'id'   => \ET_Core_Data_Utils::uuid_v4(),
			'name' => $prefix_label . ' ' . ( $highest_number + 1 ),
		];
	}

	/**
	 * Generate a new default preset for a group.
	 *
	 * PHP equivalent of the client-side generateNewOptionGroupPreset function.
	 *
	 * @since ??
	 *
	 * @param array  $existing_presets Existing preset items for the group.
	 * @param string $group_label The group label.
	 * @param string $group_name The group name (e.g., 'divi/font').
	 * @param string $module_name The module name (e.g., 'divi/blurb').
	 * @param string $group_id The group ID (e.g., 'title.decoration.font').
	 * @param int    $timestamp Current timestamp.
	 * @return array The new preset item.
	 */
	private static function _generate_new_group_preset( array $existing_presets, string $group_label, string $group_name, string $module_name, string $group_id, int $timestamp ): array {
		// Generate unique ID and name.
		$preset_info = self::_generate_default_preset_name( $existing_presets, $group_label );

		return [
			'id'         => $preset_info['id'],
			'name'       => $preset_info['name'],
			'type'       => 'group',
			'groupName'  => $group_name,
			'moduleName' => $module_name,
			'groupId'    => $group_id,
			'version'    => ET_BUILDER_VERSION,
			'created'    => $timestamp,
			'updated'    => $timestamp,
		];
	}

	/**
	 * Get module label for preset generation.
	 *
	 * Uses existing module registration to get the module title, with fallback logic.
	 * This is the PHP equivalent of the client-side getModuleTitle selector.
	 *
	 * @since ??
	 *
	 * @param string $module_name The module name (e.g., 'divi/blurb').
	 * @return string The module label (e.g., 'Blurb').
	 */
	private static function _get_module_label_for_preset( string $module_name ): string {
		$module_settings = ModuleRegistration::get_module_settings( $module_name );
		return $module_settings->title ?? '';
	}

	/**
	 * Get group label for preset generation.
	 *
	 * Converts group name to a readable label (e.g., 'divi/font' -> 'Font').
	 *
	 * @since ??
	 *
	 * @param string $group_name The group name (e.g., 'divi/font').
	 * @return string The group label (e.g., 'Font').
	 */
	private static function _get_group_label_for_preset( string $group_name ): string {
		// Extract the last part after the slash (e.g., 'divi/font' -> 'font').
		$parts     = explode( '/', $group_name );
		$last_part = end( $parts );

		// Convert to title case (e.g., 'font' -> 'Font', 'box-shadow' -> 'Box Shadow').
		$label = str_replace( '-', ' ', $last_part );
		$label = ucwords( $label );

		return $label;
	}

	/**
	 * Get default preset ID for a specific preset type.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Array of arguments.
	 *
	 *     @type string $preset_type     The preset type (module/group).
	 *     @type string $preset_sub_type The preset subtype (module name/group name).
	 * }
	 *
	 * @return string
	 */
	public static function get_default_preset_id( array $args ): string {
		$all_presets = self::get_data();
		$preset_type = $args['preset_type'] ?? 'module';
		$sub_type    = $args['preset_sub_type'] ?? '';

		if ( 'group' === $preset_type ) {
			return $all_presets['group'][ $sub_type ]['default'] ?? '';
		}

		$module_name = ModuleUtils::maybe_convert_preset_module_name(
			$sub_type,
			[]
		);

		return $all_presets['module'][ $module_name ]['default'] ?? '';
	}

	/**
	 * Get preset class name.
	 *
	 * @since ??
	 *
	 * @param string $group_name Group name.
	 * @param string $preset_id Preset ID.
	 * @param string $preset_type Preset type (module/group).
	 *
	 * @return string
	 */
	public static function get_preset_class_name(
		string $group_name,
		string $preset_id,
		string $preset_type = 'group'
	): string {
		$normalized_group  = str_replace( '/', '-', $group_name );
		$normalized_preset = 'default' === $preset_id ? 'default' : $preset_id;

		return sprintf(
			'divi-%s-%s-%s',
			$preset_type,
			$normalized_group,
			$normalized_preset
		);
	}

	/**
	 * Get group preset class names for a module.
	 *
	 * @since ??
	 *
	 * @param array $presets Group presets data.
	 *
	 * @return array
	 */
	public static function get_group_preset_class_name_for_module( array $presets ): array {
		// Early bail if presets are not provided or invalid.
		if ( empty( $presets ) || ! is_array( $presets ) ) {
			return [];
		}

		// Cache for default preset IDs to avoid repeated lookups.
		$default_group_preset_ids = [];

		// Transform presets into a normalized array of preset items.
		// Each item contains group name, preset ID, default status, and generated class name.
		$normalized = array_reduce(
			$presets,
			function ( $acc, $preset_item ) use ( &$default_group_preset_ids ) {
				if ( ! $preset_item instanceof GlobalPresetItemGroup ) {
					return $acc;
				}

				$group_name = $preset_item->get_data_group_name() ?? '';
				$preset_id  = $preset_item->get_data_id() ?? '';

				// Skip presets with missing required data.
				// Note: preset_id can be empty for default presets, so we check for attrs instead.
				if ( '' === $group_name || empty( $preset_item->get_data_attrs() ) ) {
					return $acc;
				}

				// Handle stacked presets: When presets are stacked, get_group_preset returns
				// a merged preset with a Stack ID (array) (e.g., ['preset1', 'preset2']).
				// The ID is always an array - single-element array for single presets, multiple elements for stacked presets.
				// We need to normalize to an array to generate separate class names for each preset.
				$preset_ids = $preset_id;
				if ( is_array( $preset_id ) ) {
					// Already an array, filter out invalid IDs.
					$preset_ids = array_filter(
						$preset_id,
						function ( $id ) {
							return '' !== $id && 'default' !== $id && '_initial' !== $id;
						}
					);
				} else {
					// If not an array (shouldn't happen after migration, but handle for safety).
					// Empty preset_id means use the default.
					$preset_ids = [];
				}

				// Cache defaultPresetId to avoid redundant calls.
				if ( ! isset( $default_group_preset_ids[ $group_name ] ) ) {
					$default_group_preset_ids[ $group_name ] = self::get_default_preset_id(
						[
							'preset_type'     => 'group',
							'preset_sub_type' => $group_name,
						]
					);
				}

				$default_preset_id = $default_group_preset_ids[ $group_name ];

				// If no preset IDs were found (empty preset_id case), use the default preset.
				if ( empty( $preset_ids ) && ! empty( $default_preset_id ) ) {
					$preset_ids = [ $default_preset_id ];
				}

				// Get module name and nested status from preset item.
				$module_name = $preset_item->get_module_name() ?? '';
				$is_nested   = $preset_item instanceof GlobalPresetItemGroup && $preset_item->is_nested();

				// Generate class name for each preset in the stack.
				foreach ( $preset_ids as $single_preset_id ) {
					$is_default = self::is_preset_id_as_default( $single_preset_id, $default_preset_id )
					|| ( $preset_item instanceof GlobalPresetItem && $preset_item->as_default() );

					// Generate class name for this preset using the proper method that supports nested presets.
					$class_name = GlobalPresetItemUtils::generate_preset_class_name(
						[
							'presetType'       => 'group',
							'presetModuleName' => $module_name,
							'presetGroupName'  => $group_name,
							'presetGroupId'    => $preset_item->get_group_id(),
							'presetId'         => $is_default ? 'default' : $single_preset_id,
							'isNested'         => $is_nested,
						]
					);

					// Add normalized preset item to the accumulator.
					$acc[] = [
						'groupName' => $group_name,
						'groupId'   => $preset_item->get_group_id(),
						'id'        => $single_preset_id,
						'isDefault' => $is_default,
						'className' => $class_name,
					];
				}

				return $acc;
			},
			[]
		);

		// Filter out default presets that have non-default duplicates.
		// This ensures that when a group has both a default preset and a custom (non-default) preset,
		// only the custom preset is retained in the final result.
		$filtered = array_filter(
			$normalized,
			function ( $item ) use ( $normalized ) {
				if ( $item['isDefault'] ) {
					// Check if there's a non-default preset with the same group name and host group ID.
					foreach ( $normalized as $normalized_item ) {
						if (
							$normalized_item['groupName'] === $item['groupName']
							&& $normalized_item['groupId'] === $item['groupId']
							&& ! $normalized_item['isDefault']
						) {
							// Exclude this default preset since a custom preset exists for this group.
							return false;
						}
					}
				}

				return true;
			}
		);

		// Extract class names from filtered items.
		return array_values(
			array_map(
				function ( $item ) {
					return $item['className'];
				},
				$filtered
			)
		);
	}

	/**
	 * Retrieve the selected group presets.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array  $moduleAttrs The module attributes.
	 *     @type string|WP_Block_Type  $moduleData  The module name or configuration data.
	 *     @type array  $allData     The all data. If not provided, it will be fetched using `GlobalPreset::get_data()`.
	 * }
	 *
	 * @throws InvalidArgumentException If the `moduleAttrs` argument is not provided.
	 *
	 * @return array<GlobalPresetItemGroup> The selected group presets.
	 */
	public static function get_selected_group_presets( array $args ): array {
		if ( ! isset( $args['moduleName'] ) || empty( $args['moduleName'] ) ) {
			throw new InvalidArgumentException( 'The `moduleName` argument is required.' );
		}

		if ( ! isset( $args['moduleAttrs'] ) ) {
			throw new InvalidArgumentException( 'The `moduleAttrs` argument is required.' );
		}

		// Extract the arguments.
		$module_name  = $args['moduleName'];
		$module_attrs = $args['moduleAttrs'];
		$all_data     = $args['allData'] ?? self::get_data();

		$selected      = [];
		$module_config = ModuleRegistration::get_module_settings( $module_name );

		// Get default and merged group presets.
		$default_group_preset_attrs = self::get_group_preset_default_attr( $module_config );

		// Build group presets in canonical order: start with defaults,
		// then nested group presets from module preset, then explicitly requested presets.
		$group_presets = $default_group_preset_attrs;

		// Get nested group presets from all stacked module presets.
		$module_preset_value = $module_attrs['modulePreset'] ?? '';
		$module_preset_ids   = self::normalize_preset_stack( $module_preset_value );

		// Track which groups have nested presets (not defaults).
		$nested_group_ids = [];
		// Track which preset IDs are nested for each group ID.
		$nested_preset_ids_by_group = [];
		// Queue of group preset references to traverse recursively for nested group presets.
		$nested_group_preset_queue = [];
		// Guard against cyclic references while traversing nested group presets.
		$visited_group_preset_refs = [];

		// Convert module name if needed (for preset compatibility).
		$module_name_converted = ModuleUtils::maybe_convert_preset_module_name( $module_name, $module_attrs );

		// If no explicit module preset is assigned, check for default module preset.
		if ( empty( $module_preset_ids ) ) {
			$default_preset_id = $all_data['module'][ $module_name_converted ]['default'] ?? '';

			// If a default preset exists, use it to extract nested group presets.
			if ( ! empty( $default_preset_id ) ) {
				$module_preset_ids = [ $default_preset_id ];
			}
		}

		if ( ! empty( $module_preset_ids ) ) {
			// Collect module presets with their priorities for proper ordering.
			$presets_with_priority = [];
			foreach ( $module_preset_ids as $module_preset_id ) {
				$module_preset_data = $all_data['module'][ $module_name_converted ]['items'][ $module_preset_id ] ?? null;

				if ( $module_preset_data ) {
					$module_preset_data = self::_maybe_runtime_migrate_preset_data(
						$module_preset_data,
						$module_name_converted
					);
					$priority           = $module_preset_data['priority'] ?? 10;

					$presets_with_priority[] = [
						'id'       => $module_preset_id,
						'data'     => $module_preset_data,
						'priority' => $priority,
					];
				}
			}

			// Sort presets by priority (ascending: lower priority first, higher priority last).
			// This ensures higher priority presets are processed later and their group presets come after.
			usort(
				$presets_with_priority,
				function ( $a, $b ) {
					return $a['priority'] <=> $b['priority'];
				}
			);

			// Merge group presets from all stacked module presets in priority order.
			// When multiple module presets have nested group presets for the same group ID,
			// their preset IDs are stacked together (not replaced) to allow all nested presets to be applied.
			foreach ( $presets_with_priority as $preset_item ) {
				$module_preset_data = $preset_item['data'];

				if ( ! empty( $module_preset_data['groupPresets'] ) && is_array( $module_preset_data['groupPresets'] ) ) {
					// Apply nested group presets from this module preset.
					foreach ( $module_preset_data['groupPresets'] as $group_id => $group_preset_ref ) {
						if ( ! empty( $group_preset_ref['presetId'] ) && ! empty( $group_preset_ref['groupName'] ) ) {
							// Normalize preset ID to array.
							$preset_ids = self::normalize_preset_stack( $group_preset_ref['presetId'] );

							if ( empty( $preset_ids ) ) {
								continue;
							}

							// If this group ID already exists, stack the preset IDs together.
							if ( isset( $group_presets[ $group_id ] ) ) {
								$existing_preset_ids = self::normalize_preset_stack( $group_presets[ $group_id ]['presetId'] ?? '' );
								$stacked_preset_ids  = array_merge( $existing_preset_ids, $preset_ids );

								$group_presets[ $group_id ] = [
									'presetId'  => $stacked_preset_ids,
									'groupName' => $group_presets[ $group_id ]['groupName'] ?? $group_preset_ref['groupName'],
								];
							} else {
								// First time seeing this group ID, add it as-is.
								$group_presets[ $group_id ] = [
									'presetId'  => $preset_ids,
									'groupName' => $group_preset_ref['groupName'],
								];
							}

							// Track that this group has a nested preset.
							$nested_group_ids[ $group_id ] = true;
							// Track which preset IDs are nested for this group.
							if ( ! isset( $nested_preset_ids_by_group[ $group_id ] ) ) {
								$nested_preset_ids_by_group[ $group_id ] = [];
							}
							$nested_preset_ids_by_group[ $group_id ] = array_merge( $nested_preset_ids_by_group[ $group_id ], $preset_ids );

							// Queue these nested refs to discover deeper nested presets recursively.
							foreach ( $preset_ids as $preset_id ) {
								if ( ! empty( $preset_id ) ) {
									$nested_group_preset_queue[] = [
										'groupId'   => $group_id,
										'groupName' => $group_preset_ref['groupName'],
										'presetId'  => $preset_id,
									];
								}
							}
						}
					}
				}
			}
		}

		// Overlay explicitly requested presets from module attributes.
		// When both nested and explicit presets exist for the same group, stack them together
		// (nested presets first, then explicit presets) rather than replacing.
		// Explicit presets should override defaults when no nested preset exists.
		foreach ( ( $module_attrs['groupPreset'] ?? [] ) as $gid => $attr ) {
			if ( isset( $nested_group_ids[ $gid ] ) ) {
				// Both nested and explicit exist: stack them together (nested first, then explicit).
				$nested_preset_ids   = self::normalize_preset_stack( $group_presets[ $gid ]['presetId'] ?? '' );
				$explicit_preset_ids = self::normalize_preset_stack( $attr['presetId'] ?? '' );
				$stacked_preset_ids  = array_merge( $nested_preset_ids, $explicit_preset_ids );

				$group_presets[ $gid ] = [
					'presetId'  => $stacked_preset_ids,
					'groupName' => $attr['groupName'] ?? $group_presets[ $gid ]['groupName'] ?? '',
				];
			} else {
				// Only explicit exists (no nested preset), so override any default.
				$group_presets[ $gid ] = $attr;
			}

			// Explicit group presets are roots for recursive nested discovery.
			if ( ! empty( $attr['groupName'] ) ) {
				$explicit_preset_ids = self::normalize_preset_stack( $attr['presetId'] ?? '' );
				foreach ( $explicit_preset_ids as $explicit_preset_id ) {
					if ( ! empty( $explicit_preset_id ) ) {
						$nested_group_preset_queue[] = [
							'groupId'   => $gid,
							'groupName' => $attr['groupName'],
							'presetId'  => $explicit_preset_id,
						];
					}
				}
			}
		}

		// Recursively discover nested group presets inside assigned group presets.
		// This enables group presets nested inside other group presets to be processed on FE.
		// Iterate by index to avoid O(n) cost of repeatedly shifting arrays.
		for ( $queue_index = 0; isset( $nested_group_preset_queue[ $queue_index ] ); $queue_index++ ) {
			$current_ref = $nested_group_preset_queue[ $queue_index ];
			if ( ! is_array( $current_ref ) ) {
				continue;
			}

			$current_group_name = $current_ref['groupName'] ?? '';
			$current_group_id   = $current_ref['groupId'] ?? '';
			$current_preset_id  = $current_ref['presetId'] ?? '';
			if ( empty( $current_group_name ) || empty( $current_preset_id ) ) {
				continue;
			}

			// Include module and group context so shared preset IDs remain unambiguous across scopes.
			$visit_key = $module_name_converted . '::' . $current_group_id . '::' . $current_group_name . '::' . $current_preset_id;
			if ( isset( $visited_group_preset_refs[ $visit_key ] ) ) {
				continue;
			}
			$visited_group_preset_refs[ $visit_key ] = true;

			$current_group_preset_data = $all_data['group'][ $current_group_name ]['items'][ $current_preset_id ] ?? null;
			if ( ! is_array( $current_group_preset_data ) ) {
				continue;
			}

			$current_group_preset_data = self::_maybe_runtime_migrate_preset_data(
				$current_group_preset_data,
				$module_name
			);

			$nested_group_refs = $current_group_preset_data['attrs']['groupPreset'] ?? [];
			if ( ! is_array( $nested_group_refs ) ) {
				continue;
			}

			foreach ( $nested_group_refs as $nested_group_id => $nested_group_ref ) {
				if ( ! is_array( $nested_group_ref ) ) {
					continue;
				}

				$source_group_id          = $current_group_preset_data['groupId'] ?? '';
				$resolved_nested_group_id = $nested_group_id;

				// Remap nested host paths from the preset's authoring context to the current usage host.
				// Example: applying a `divi/image` preset authored under `image` to Blurb `imageIcon`
				// should register nested refs as `imageIcon.*` (not `image.*`).
				if (
					is_string( $source_group_id )
					&& '' !== $source_group_id
					&& is_string( $current_group_id )
					&& '' !== $current_group_id
					&& GlobalPresetItemGroupAttrNameResolver::is_attr_name_prefix_matched( $nested_group_id, $source_group_id )
				) {
					$resolved_nested_group_id = GlobalPresetItemGroupAttrNameResolver::replace_attr_name_prefix(
						$nested_group_id,
						$current_group_id
					);
				}

				$nested_group_name = $nested_group_ref['groupName'] ?? '';
				if ( empty( $nested_group_name ) ) {
					continue;
				}

				$nested_preset_ids = self::normalize_preset_stack( $nested_group_ref['presetId'] ?? '' );
				if ( empty( $nested_preset_ids ) ) {
					continue;
				}

				$existing_preset_ids = self::normalize_preset_stack( $group_presets[ $resolved_nested_group_id ]['presetId'] ?? '' );
				$stacked_preset_ids  = array_values( array_unique( array_merge( $existing_preset_ids, $nested_preset_ids ) ) );

				$group_presets[ $resolved_nested_group_id ] = [
					'presetId'  => $stacked_preset_ids,
					'groupName' => $group_presets[ $resolved_nested_group_id ]['groupName'] ?? $nested_group_name,
				];

				$nested_group_ids[ $resolved_nested_group_id ] = true;
				if ( ! isset( $nested_preset_ids_by_group[ $resolved_nested_group_id ] ) ) {
					$nested_preset_ids_by_group[ $resolved_nested_group_id ] = [];
				}
				$nested_preset_ids_by_group[ $resolved_nested_group_id ] = array_values(
					array_unique(
						array_merge( $nested_preset_ids_by_group[ $resolved_nested_group_id ], $nested_preset_ids )
					)
				);

				// Continue traversing deeper nested references.
				foreach ( $nested_preset_ids as $nested_preset_id ) {
					if ( ! empty( $nested_preset_id ) ) {
						$nested_group_preset_queue[] = [
							'groupId'   => $resolved_nested_group_id,
							'groupName' => $nested_group_name,
							'presetId'  => $nested_preset_id,
						];
					}
				}
			}
		}

		// Transform group presets into normalized array with preset items.
		// Each item contains group ID, group name, item instance, and default status.
		$all_items = [];
		foreach ( $group_presets as $group_id => $attr_value ) {
			$group_name        = $attr_value['groupName'] ?? '';
			$preset_id_value   = $attr_value['presetId'] ?? '';
			$default_preset_id = $all_data['group'][ $group_name ]['default'] ?? '';

			// Normalize preset ID (handle both string and array for preset stacking).
			$preset_ids = self::normalize_preset_stack( $preset_id_value );

			// If no presets in the stack, use default.
			if ( empty( $preset_ids ) ) {
				// Tabs active background defaults can override the expected tab-state visuals.
				// Skip FE fallback for this group on Tabs while preserving normal fallback
				// behavior for all other groups and modules.
				if (
					'divi/tabs' === $module_name
					&& in_array(
						$group_id,
						[
							'activeTab.decoration.background',
						],
						true
					)
				) {
					continue;
				}

				$use_default    = true;
				$data_preset_id = $default_preset_id;

				// Check if default preset is nested (comes from a module preset).
				$is_nested = false;
				if ( isset( $nested_preset_ids_by_group[ $group_id ] ) ) {
					$is_nested = in_array( $default_preset_id, $nested_preset_ids_by_group[ $group_id ], true );
				}

				$item = new GlobalPresetItemGroup(
					[
						'data'       => self::_maybe_runtime_migrate_preset_data(
							$all_data['group'][ $group_name ]['items'][ $data_preset_id ] ?? [],
							$module_name
						),
						'asDefault'  => $use_default,
						'isExist'    => isset( $all_data['group'][ $group_name ]['items'][ $data_preset_id ] ),
						'groupId'    => $group_id,
						'moduleName' => $module_name,
						'isNested'   => $is_nested,
					]
				);

				$all_items[] = [
					'groupId'    => $group_id,
					'groupName'  => $group_name,
					'item'       => $item,
					'useDefault' => $use_default,
				];
			} else {
				// Create a separate item for EACH preset in the stack (for style generation).
				foreach ( $preset_ids as $preset_id ) {
					$use_default    = self::is_preset_id_as_default( $preset_id, $default_preset_id );
					$data_preset_id = $use_default ? $default_preset_id : $preset_id;

					// Check if this preset ID is nested (comes from a module preset).
					$is_nested = false;
					if ( isset( $nested_preset_ids_by_group[ $group_id ] ) ) {
						$is_nested = in_array( $preset_id, $nested_preset_ids_by_group[ $group_id ], true )
							|| ( $use_default && in_array( $default_preset_id, $nested_preset_ids_by_group[ $group_id ], true ) );
					}

					$item = new GlobalPresetItemGroup(
						[
							'data'       => self::_maybe_runtime_migrate_preset_data(
								$all_data['group'][ $group_name ]['items'][ $data_preset_id ] ?? [],
								$module_name
							),
							'asDefault'  => $use_default,
							'isExist'    => isset( $all_data['group'][ $group_name ]['items'][ $data_preset_id ] ),
							'groupId'    => $group_id,
							'moduleName' => $module_name,
							'isNested'   => $is_nested,
						]
					);

					$all_items[] = [
						'groupId'    => $group_id,
						'groupName'  => $group_name,
						'item'       => $item,
						'useDefault' => $use_default,
					];
				}
			}
		}

		// Filter out default presets that have custom duplicates with the same groupName.
		// This ensures that when a groupName has both default and custom presets,
		// only the custom presets are retained in the final result.
		$filtered = array_filter(
			$all_items,
			function ( $item_data ) use ( $all_items ) {
				if ( $item_data['useDefault'] ) {
					// Check if there's a custom preset with the same group name.
					foreach ( $all_items as $other_item ) {
						if ( $other_item['groupName'] === $item_data['groupName'] && ! $other_item['useDefault'] ) {
							// Exclude this default preset since a custom preset exists for this group.
							return false;
						}
					}
				}

				return true;
			}
		);

		// Extract preset items indexed by a unique key.
		// For single presets, use groupId as key for backward compatibility.
		// For stacked presets, use groupId + presetId to ensure uniqueness.
		foreach ( $filtered as $item_data ) {
			$item      = $item_data['item'];
			$preset_id = $item->get_data_id();
			$group_id  = $item_data['groupId'];

			// Check if there are multiple presets with the same groupId in the filtered array.
			$has_multiple_with_same_group = false;
			foreach ( $filtered as $check_item ) {
				if ( $check_item['groupId'] === $group_id && $check_item['item']->get_data_id() !== $preset_id ) {
					$has_multiple_with_same_group = true;
					break;
				}
			}

			// Use combined key for stacked presets, simple groupId for single presets.
			if ( $has_multiple_with_same_group ) {
				$selected[ $group_id . '--' . $preset_id ] = $item;
			} else {
				$selected[ $group_id ] = $item;
			}
		}

		// Ensure all requested group IDs are present in the result even if no data exists.
		foreach ( ( $module_attrs['groupPreset'] ?? [] ) as $group_id => $attr_value ) {
			if ( ! isset( $selected[ $group_id ] ) ) {
				$selected[ $group_id ] = new GlobalPresetItemGroup(
					[
						'data'       => [],
						'asDefault'  => true,
						'isExist'    => false,
						'groupId'    => $group_id,
						'moduleName' => $module_name,
					]
				);
			}
		}

		return $selected;
	}

	/**
	 * Get default group preset attributes from module configuration.
	 *
	 * @since ??
	 *
	 * @param string|WP_Block_Type $module_data Module name or configuration object.
	 *
	 * @return array<string, array<string, string>> The default group preset attributes.
	 */
	public static function get_group_preset_default_attr( $module_data ): array {
		static $group_preset_cache = [];

		$module_name = $module_data->name ?? '';

		if ( isset( $group_preset_cache[ $module_name ] ) ) {
			return $group_preset_cache[ $module_name ];
		}

		$default_attrs = [];
		$attributes    = $module_data->attributes ?? [];

		foreach ( $attributes as $attr_name => $attribute ) {
			$settings = $attribute['settings'] ?? [];

			foreach ( [ 'decoration', 'advanced' ] as $attr_type ) {
				$groups = $settings[ $attr_type ] ?? [];

				foreach ( $groups as $group_id => $group_config ) {
					$group_name = '';

					// Check for presetGroup prop override (for decoration groups).
					$preset_group_name = $group_config['component']['props']['presetGroup'] ?? '';
					if ( ! empty( $preset_group_name ) ) {
						$group_name = $preset_group_name;
					} elseif ( empty( $group_config ) || ! isset( $group_config['groupType'] ) ) {
						// Empty group or missing groupType.
						$group_name = self::get_default_group_name( $attr_type, $group_id );
					} elseif ( 'group' === $group_config['groupType'] ) {
						$group_name = $group_config['groupName'] ?? self::get_default_group_name( $attr_type, $group_id );

						// Skip if grouped prop is explicitly false.
						if ( isset( $group_config['component']['props']['grouped'] )
							&& false === $group_config['component']['props']['grouped'] ) {
							continue;
						}
					} elseif ( 'group-item' === $group_config['groupType'] ) {
						// Nested group item.
						$item       = $group_config['item'] ?? [];
						$group_slug = $item['groupSlug'] ?? '';

						// Check if group-item references a composite group via groupSlug.
						if ( ! empty( $group_slug ) && isset( $module_data->settings['groups'] ) ) {
							$composite_groups               = $module_data->settings['groups'];
							$composite_group                = $composite_groups[ $group_slug ] ?? null;
							$composite_group_component_name = $composite_group['component']['name'] ?? '';

							if ( self::_is_supported_composite_group_component( $composite_group ) ) {
								$preset_group_name = $composite_group['component']['props']['presetGroup'] ?? '';
								if ( ! empty( $preset_group_name ) ) {
									$group_name = $preset_group_name;
								} elseif ( self::_should_use_component_name_as_preset_group( $composite_group ) ) {
									$group_name = $composite_group_component_name;
								}
							}
						}

						// Fallback to checking item component if groupSlug resolution didn't work.
						if ( empty( $group_name ) ) {
							$item_component = $item['component'] ?? [];
							if ( 'group' === ( $item_component['type'] ?? '' ) && ( false !== ( $item_component['props']['grouped'] ?? true ) ) ) {
								$group_name = $item_component['name'] ?? '';
							}
						}
					} elseif ( 'group-items' === $group_config['groupType'] ) {
						$group_items = $group_config['items'] ?? [];

						foreach ( $group_items as $group_item ) {
							$item_attr_name = $group_item['attrName'] ?? '';

							if ( empty( $item_attr_name ) ) {
								$item_attr_name = $group_item['component']['props']['attrName'] ?? '';
							}

							if ( empty( $item_attr_name ) ) {
								continue;
							}

							$item_group_name = $group_item['component']['props']['presetGroup'] ?? '';

							if ( empty( $item_group_name ) ) {
								$item_component = $group_item['component'] ?? [];

								if ( 'group' === ( $item_component['type'] ?? '' ) && ( false !== ( $item_component['props']['grouped'] ?? true ) ) ) {
									$item_group_name = $item_component['name'] ?? '';
								}
							}

							if ( empty( $item_group_name ) ) {
								$item_group_name = self::get_default_group_name( $attr_type, $group_id );
							}

							if ( ! empty( $item_group_name ) ) {
								$default_attrs[ $item_attr_name ] = [
									'groupName' => $item_group_name,
								];
							}
						}

						continue;
					}

					// Final fallback to default name.
					if ( empty( $group_name ) ) {
						$group_name = self::get_default_group_name( $attr_type, $group_id );
					}

					if ( ! empty( $group_name ) ) {
						$default_attrs[ "{$attr_name}.{$attr_type}.{$group_id}" ] = [
							'groupName' => $group_name,
						];
					}
				}
			}
		}

		// Process composite groups from module metadata.
		$composite_groups = $module_data->settings['groups'] ?? [];

		foreach ( $composite_groups as $group_id => $group ) {
			$component_name = $group['component']['name'] ?? '';

			if ( ! self::_is_supported_composite_group_component( $group ) ) {
				continue;
			}

			$preset_group_name = $group['component']['props']['presetGroup'] ?? '';
			$resolved_group_id = $group['component']['props']['attrName'] ?? $group_id;

			if ( ! is_string( $resolved_group_id ) || empty( $resolved_group_id ) ) {
				$resolved_group_id = $group_id;
			}

			if ( empty( $preset_group_name ) && self::_should_use_component_name_as_preset_group( $group ) ) {
				$preset_group_name = $component_name;
			}

			if ( ! empty( $preset_group_name ) ) {
				$default_attrs[ $resolved_group_id ] = [
					'groupName' => $preset_group_name,
				];
			}
		}

		$group_preset_cache[ $module_name ] = $default_attrs;

		return $default_attrs;
	}

	/**
	 * Get default group name mapping.
	 *
	 * @since ??
	 *
	 * @param string $attr_type Attribute type (decoration/advanced).
	 * @param string $group_id  Group ID.
	 *
	 * @return string
	 */
	public static function get_default_group_name( string $attr_type, string $group_id ): string {
		$group_name_map = [
			'decoration' => [
				'animation'   => 'divi/animation',
				'background'  => 'divi/background',
				'bodyFont'    => 'divi/font-body',
				'border'      => 'divi/border',
				'boxShadow'   => 'divi/box-shadow',
				'button'      => 'divi/button',
				'conditions'  => 'divi/conditions',
				'disabledOn'  => 'divi/disabled-on',
				'filters'     => 'divi/filters',
				'font'        => 'divi/font',
				'headingFont' => 'divi/font-header',
				'image'       => 'divi/image',
				'layout'      => 'divi/layout',
				'overflow'    => 'divi/overflow',
				'position'    => 'divi/position',
				'scroll'      => 'divi/scroll',
				'sizing'      => 'divi/sizing',
				'spacing'     => 'divi/spacing',
				'sticky'      => 'divi/sticky',
				'transform'   => 'divi/transform',
				'transition'  => 'divi/transition',
				'zIndex'      => 'divi/z-index',
			],
			'advanced'   => [
				'html'           => 'divi/html',
				'htmlAttributes' => 'divi/id-classes',
				'loop'           => 'divi/loop',
				'text'           => 'divi/text',
			],
		];

		return $group_name_map[ $attr_type ][ $group_id ] ?? '';
	}

	/**
	 * Determine whether a group component is supported for composite preset processing.
	 *
	 * @since ??
	 *
	 * @param mixed $group Group configuration.
	 *
	 * @return bool
	 */
	private static function _is_supported_composite_group_component( $group ): bool {
		if ( ! is_array( $group ) ) {
			return false;
		}

		$component_name = $group['component']['name'] ?? '';

		if ( 'divi/composite' === $component_name ) {
			return true;
		}

		return self::_should_use_component_name_as_preset_group( $group );
	}

	/**
	 * Determine whether preset group should fallback to component name.
	 *
	 * @since ??
	 *
	 * @param array $group Group configuration.
	 *
	 * @return bool
	 */
	private static function _should_use_component_name_as_preset_group( array $group ): bool {
		$component_name  = $group['component']['name'] ?? '';
		$component_props = $group['component']['props'] ?? [];
		$flag            = $component_props['useComponentNameAsPresetGroup'] ?? false;

		if ( is_bool( $flag ) && $flag ) {
			return true;
		}

		// Backward compatibility for existing metadata.
		return in_array( $component_name, [ 'divi/form-field', 'divi/checkbox', 'divi/checkboxes', 'divi/radio', 'divi/radios' ], true );
	}

	/**
	 * Find preset data by ID from global presets.
	 *
	 * @since ??
	 *
	 * @param string $preset_id The preset ID to find.
	 *
	 * @return array|null The preset data if found, null otherwise.
	 */
	public static function find_preset_data_by_id( string $preset_id ): ?array {
		$all_presets = self::get_data();

		if ( empty( $all_presets ) ) {
			return null;
		}

		// Search through module presets.
		if ( isset( $all_presets['module'] ) ) {
			foreach ( $all_presets['module'] as $module_name => $module_data ) {
				if ( isset( $module_data['items'][ $preset_id ] ) ) {
					return array_merge(
						$module_data['items'][ $preset_id ],
						[
							'moduleName' => $module_name,
							'type'       => 'module',
						]
					);
				}
			}
		}

		// Search through group presets.
		if ( isset( $all_presets['group'] ) ) {
			foreach ( $all_presets['group'] as $group_name => $group_data ) {
				if ( isset( $group_data['items'][ $preset_id ] ) ) {
					return array_merge(
						$group_data['items'][ $preset_id ],
						[
							'groupName' => $group_name,
							'type'      => 'group',
						]
					);
				}
			}
		}

		return null;
	}

	/**
	 * Core preset processing workflow: convert, merge, and prepare presets.
	 *
	 * This utility method handles the core workflow for importing presets:
	 * 1. Auto-detects D4 vs D5 format and converts D4→D5 if needed
	 * 2. Processes and merges with existing presets (with deduplication)
	 * 3. Creates default presets for modules that need them
	 * 4. Returns the final preset data (caller decides whether to save)
	 *
	 * @since ??
	 *
	 * @param array $presets The presets to process (D4 or D5 format).
	 * @param bool  $auto_save Whether to automatically save the result. Default true.
	 *
	 * @return array Returns processed result with preset_id_mappings for content replacement.
	 */
	public static function process_presets_for_import( array $presets, bool $auto_save = true ): array {
		if ( empty( $presets ) ) {
			return [
				'presets'                        => self::get_data(),
				'preset_id_mappings'             => [],
				'defaultImportedModulePresetIds' => [],
				'defaultImportedGroupPresetIds'  => [],
			];
		}

		$is_migration = ! Conversion::is_global_data_presets_items( $presets );

		// Step 1: Auto-detect format and convert D4→D5 if needed.
		$converted_presets = Conversion::maybe_convert_presets_data( $presets );

		// Step 2: Process and merge presets with existing presets.
		$merge_result = self::merge_new_presets_with_existing( $converted_presets, $is_migration );

		// Step 3: Extract presets data from merge result.
		$presets_data = $merge_result['presets'] ?? [];

		// Step 4: Create default presets for modules that now have presets but no default.
		$final_presets = self::maybe_create_default_presets_after_import( $presets_data );

		// Step 5: Optionally save the complete presets.
		if ( $auto_save ) {
			self::save_data( $final_presets );
			Migration::get_instance()->execute_preset_migrations();
			$final_presets = self::get_data();
		}

		// Return the complete result with mappings.
		return [
			'presets'                        => $final_presets,
			'preset_id_mappings'             => $merge_result['preset_id_mappings'] ?? [],
			'defaultImportedModulePresetIds' => $merge_result['defaultImportedModulePresetIds'] ?? [],
			'defaultImportedGroupPresetIds'  => $merge_result['defaultImportedGroupPresetIds'] ?? [],
		];
	}

	/**
	 * Filters a preset stack array to remove invalid preset IDs.
	 *
	 * This utility filters out empty, 'default', and '_initial' preset IDs from an array,
	 * returning only valid preset IDs. Presets are always stored as arrays.
	 *
	 * @since ??
	 *
	 * @param array|null $preset_value The preset value array to filter.
	 *
	 * @return array An array of valid preset IDs, or empty array if no valid presets.
	 */
	public static function normalize_preset_stack( $preset_value ): array {
		// Handle empty values early (most common case).
		if ( empty( $preset_value ) ) {
			return [];
		}

		// Create cache key from the input value.
		// For arrays, use serialize which is faster than wp_json_encode (no UTF-8 encoding overhead).
		// For strings, use the string directly with a prefix to avoid collisions.
		// serialize() is ~2x faster than wp_json_encode for typical preset arrays.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Safe use: only serializing controlled preset IDs for cache key generation, not for data storage or transmission.
		$cache_key = is_array( $preset_value ) ? serialize( $preset_value ) : 's:' . (string) $preset_value;

		// Check cache first to avoid repeated processing.
		if ( isset( self::$_normalized_preset_stack_cache[ $cache_key ] ) ) {
			return self::$_normalized_preset_stack_cache[ $cache_key ];
		}

		// Convert string to array for backward compatibility with unmigrated content.
		if ( is_string( $preset_value ) ) {
			$preset_value = trim( $preset_value );
			if ( '' === $preset_value || 'default' === $preset_value || '_initial' === $preset_value ) {
				self::$_normalized_preset_stack_cache[ $cache_key ] = [];
				return [];
			}
			$preset_value = [ $preset_value ];
		}

		// Ensure it's an array at this point.
		if ( ! is_array( $preset_value ) ) {
			self::$_normalized_preset_stack_cache[ $cache_key ] = [];
			return [];
		}

		$normalized = [];
		foreach ( $preset_value as $id ) {
			if ( empty( $id ) || 'default' === $id || '_initial' === $id ) {
				continue;
			}

			$normalized[] = $id;
		}

		$result = array_values( $normalized );

		// Cache the result for future use.
		self::$_normalized_preset_stack_cache[ $cache_key ] = $result;

		return $result;
	}

	/**
	 * Checks if the given preset ID is considered as a default preset.
	 *
	 * This function determines if the provided preset ID matches any of the default
	 * preset identifiers: an empty string, 'default', '_initial' (for legacy presets), or equal to the default preset ID.
	 *
	 * @since ??
	 *
	 * @param string $preset_id The preset ID to check.
	 * @param string $default_preset_id The default preset ID.
	 *
	 * @return bool True if the preset ID is a default preset, false otherwise.
	 */
	public static function is_preset_id_as_default( string $preset_id, string $default_preset_id ): bool {
		return '' === $preset_id || 'default' === $preset_id || '_initial' === $preset_id || $default_preset_id === $preset_id;
	}

	/**
	 * Merges module attributes with preset and group preset attributes.
	 *
	 * This method retrieves and merges attributes from a specified module,
	 * its selected preset, and any applicable group presets.
	 *
	 * Special handling for module.decoration.attributes:
	 * - The attributes field contains an array of attribute items
	 * - Module-level attributes should be combined with preset attributes, not replace them
	 * - This allows users to add module-specific attributes while preserving preset attributes
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $moduleName  The module name.
	 *     @type array  $moduleAttrs The module attributes.
	 *     @type array  $allData     The all data. If not provided, it will be fetched using `GlobalPreset::get_data()`.
	 *     @type bool   $presetOnly  If true, returns only preset attributes without module attributes. Default false.
	 * }
	 *
	 * @throws InvalidArgumentException If 'moduleName' or 'moduleAttrs' is not provided.
	 *
	 * @return array The merged attributes array.
	 */
	public static function get_merged_attrs( array $args ): array {
		if ( ! isset( $args['moduleName'] ) ) {
			throw new InvalidArgumentException( 'The `moduleName` argument is required.' );
		}

		if ( ! isset( $args['moduleAttrs'] ) ) {
			throw new InvalidArgumentException( 'The `moduleAttrs` argument is required.' );
		}

		// Extract the arguments.
		$module_name  = $args['moduleName'];
		$module_attrs = $args['moduleAttrs'];
		$all_data     = $args['allData'] ?? self::get_data();
		$preset_only  = $args['presetOnly'] ?? false;

		$module_presets_attrs = [];

		// Convert the module name to the preset module name.
		$module_name_converted = ModuleUtils::maybe_convert_preset_module_name( $module_name, $module_attrs );

		$default_preset_id = $all_data['module'][ $module_name_converted ]['default'] ?? '';
		$preset_value      = $module_attrs['modulePreset'] ?? '';

		// Normalize the preset value to an array for stacked presets.
		$preset_ids = self::normalize_preset_stack( $preset_value );

		// If no presets in the stack, include the default preset if available.
		if ( empty( $preset_ids ) && ! empty( $default_preset_id ) ) {
			$preset_ids = [ $default_preset_id ];
		}

		// Collect presets with their priorities.
		$presets_with_priority = [];
		foreach ( $preset_ids as $preset_id ) {
			if ( isset( $all_data['module'][ $module_name_converted ]['items'][ $preset_id ] ) ) {
				$preset_data = $all_data['module'][ $module_name_converted ]['items'][ $preset_id ];
				$preset_data = self::_maybe_runtime_migrate_preset_data(
					$preset_data,
					$module_name_converted
				);
				$priority    = $preset_data['priority'] ?? 10;

				$presets_with_priority[] = [
					'id'       => $preset_id,
					'data'     => $preset_data,
					'priority' => $priority,
				];
			}
		}

		// Sort presets by priority (ascending: lower priority first, higher priority last).
		// This ensures higher priority presets are merged later and take precedence.
		usort(
			$presets_with_priority,
			function ( $a, $b ) {
				return $a['priority'] <=> $b['priority'];
			}
		);

		// Merge attributes from all presets in priority order.
		// Lower priority presets are applied first, higher priority presets override them.
		foreach ( $presets_with_priority as $preset_item ) {
			$preset_data = $preset_item['data'];

			// Check if preset has attrs.
			if ( isset( $preset_data['attrs'] ) && is_array( $preset_data['attrs'] ) ) {
				$module_presets_attrs = array_replace_recursive( $module_presets_attrs, $preset_data['attrs'] );
			}
		}

		$group_presets_attrs        = [];
		$group_presets_render_attrs = [];

		$selected_group_presets = self::get_selected_group_presets(
			[
				'moduleName'  => $module_name,
				'moduleAttrs' => $module_attrs,
				'allData'     => $all_data,
			]
		);

		$merged_group_presets = self::merge_selected_group_presets_by_priority( $selected_group_presets );

		$group_presets_attrs        = $merged_group_presets['attrs'];
		$group_presets_render_attrs = $merged_group_presets['renderAttrs'];

		// Merge preset and group preset attributes (without module attributes).
		// This is used for preset detection and style rendering.
		$preset_only_attrs = array_replace_recursive( $module_presets_attrs, $group_presets_attrs );

		// If preset-only mode is requested, return preset attributes without module attributes.
		if ( $preset_only ) {
			return $preset_only_attrs;
		}

		// Standard merge for all attributes, including renderAttrs from option group presets.
		$merged_attrs = array_replace_recursive( $module_presets_attrs, $group_presets_attrs, $group_presets_render_attrs, $module_attrs );

		// Special handling for fields that should be merged instead of replaced.
		// array_replace_recursive replaces arrays, but some fields need custom merge logic.
		$merged_attrs = ArrayUtility::apply_mergeable_fields_logic(
			$merged_attrs,
			$module_presets_attrs,
			$group_presets_attrs,
			$module_attrs
		);

		return $merged_attrs;
	}

	/**
	 * Migrate preset item at read-time for FE/VB style resolution.
	 *
	 * @since ??
	 *
	 * @param array  $preset_data Preset payload.
	 * @param string $module_name Module name.
	 * @return array
	 */
	private static function _maybe_runtime_migrate_preset_data( array $preset_data, string $module_name ): array {
		if ( empty( $preset_data ) ) {
			return $preset_data;
		}

		$migration = Migration::get_instance();
		if ( ! $migration->preset_item_needs_migration( $preset_data ) ) {
			return $preset_data;
		}

		$cache_key_payload = wp_json_encode( [ $module_name, $preset_data ] );
		$cache_key         = is_string( $cache_key_payload ) ? md5( $cache_key_payload ) : '';

		if ( '' !== $cache_key && isset( self::$_runtime_migrated_preset_cache[ $cache_key ] ) ) {
			return self::$_runtime_migrated_preset_cache[ $cache_key ];
		}

		$migrated = $migration->migrate_preset_item( $preset_data, $module_name );

		if ( ! is_array( $migrated ) ) {
			return $preset_data;
		}

		if ( '' !== $cache_key ) {
			self::$_runtime_migrated_preset_cache[ $cache_key ] = $migrated;
		}

		return $migrated;
	}

	/**
	 * Check if a preset has a specific attribute at a given path.
	 *
	 * This method checks whether a module's selected preset contains a non-null value
	 * at the specified attribute path. It returns false if:
	 * - The module has no preset selected
	 * - The preset is using the default preset
	 * - The preset doesn't exist
	 * - The attribute path doesn't exist in the preset
	 * - The attribute value is null
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $moduleName     The module name.
	 *     @type array  $moduleAttrs    The module attributes.
	 *     @type string $attributePath  Dot-separated path to the attribute (e.g., 'module.decoration.layout.desktop.value.display').
	 * }
	 *
	 * @throws InvalidArgumentException If 'moduleName' argument is not provided.
	 * @throws InvalidArgumentException If 'moduleAttrs' argument is not provided.
	 * @throws InvalidArgumentException If 'attributePath' argument is not provided.
	 *
	 * @return bool True if preset has the attribute set (non-null), false otherwise.
	 */
	public static function preset_has_attribute( array $args ): bool {
		if ( ! isset( $args['moduleName'] ) ) {
			throw new InvalidArgumentException( 'The `moduleName` argument is required.' );
		}

		if ( ! isset( $args['moduleAttrs'] ) ) {
			throw new InvalidArgumentException( 'The `moduleAttrs` argument is required.' );
		}

		if ( ! isset( $args['attributePath'] ) ) {
			throw new InvalidArgumentException( 'The `attributePath` argument is required.' );
		}

		$module_name    = $args['moduleName'];
		$module_attrs   = $args['moduleAttrs'];
		$attribute_path = $args['attributePath'];

		// Check if module has a preset selected.
		$preset_id = $module_attrs['modulePreset'] ?? '';
		if ( empty( $preset_id ) || 'default' === $preset_id || '_initial' === $preset_id ) {
			return false;
		}

		try {
			// Get the selected preset.
			$selected_preset = self::get_selected_preset(
				[
					'moduleName'  => $module_name,
					'moduleAttrs' => $module_attrs,
				]
			);

			// If preset doesn't exist or is using default, return false.
			if ( ! $selected_preset->is_exist() || $selected_preset->as_default() ) {
				return false;
			}

			// Get preset attributes.
			$preset_attrs = $selected_preset->get_data_attrs();

			// Navigate the attribute path.
			$path_parts = explode( '.', $attribute_path );
			$current    = $preset_attrs;

			foreach ( $path_parts as $part ) {
				if ( ! is_array( $current ) || ! isset( $current[ $part ] ) ) {
					// Path doesn't exist in preset.
					return false;
				}
				$current = $current[ $part ];
			}

			// Check if the final value is non-null.
			return null !== $current;
		} catch ( \Exception $e ) {
			// If any error occurs, assume preset doesn't have the attribute.
			return false;
		}
	}
}
