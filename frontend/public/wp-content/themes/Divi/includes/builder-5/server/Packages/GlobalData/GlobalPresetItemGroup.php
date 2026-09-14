<?php
/**
 * GlobalPresetItemGroup class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\GlobalData;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\GlobalData\GlobalPresetItem;
use ET\Builder\Packages\GlobalData\GlobalPresetItemUtils;
use ET\Builder\Packages\GlobalData\GlobalPresetItemGroupUtils;
use ET\Builder\Packages\GlobalData\GlobalPresetItemGroupAttrNameResolver;
use ET\Builder\Packages\GlobalData\GlobalPresetItemGroupAttrNameResolved;
use ET\Builder\Framework\Utility\ArrayUtility;
use InvalidArgumentException;

/**
 * GlobalPresetItemGroup class.
 *
 * @since ??
 */
class GlobalPresetItemGroup extends GlobalPresetItem {

	/**
	 * The module name where the preset item being used.
	 *
	 * @var string
	 */
	protected $_module_name;

	/**
	 * The group ID where the preset item being used.
	 *
	 * @var string
	 */
	protected $_group_id;

	/**
	 * Whether this preset is nested (comes from a module preset).
	 *
	 * @var bool
	 */
	protected $_is_nested = false;

	/**
	 * Constructor for the GlobalPresetItem class.
	 *
	 * @param array $args {
	 *     Array of arguments.
	 *
	 *     @type array $data {
	 *         Data array.
	 *
	 *         @type string $type        The type of data (e.g., 'module', 'group').
	 *         @type array  $attrs       Attributes data.
	 *         @type array  $renderAttrs Render attributes data.
	 *         @type array  $styleAttrs  Style attributes data.
	 *         @type string $id          The ID of the data.
	 *         @type string $name        The name of the data.
	 *         @type int    $created     The creation timestamp.
	 *         @type int    $updated     The update timestamp.
	 *         @type string $version     The version of the data.
	 *         @type string $groupName   The name of the group.
	 *         @type string $groupId     The ID of the group.
	 *         @type string $moduleName  The name of the module.
	 *     }
	 *     @type array  $defaultPrintedStyleAttrs Default printed style attributes.
	 *     @type bool   $asDefault                Whether this is set as default.
	 *     @type bool   $isExist                  Whether the preset is exist or not.
	 *     @type string $moduleName               The module name where the preset item being used.
	 *     @type string $groupId                  The group ID where the preset item being used.
	 *     @type bool   $isNested                 Whether this preset is nested (comes from a module preset). Default is false.
	 * }
	 *
	 * @throws InvalidArgumentException If the `moduleName` argument is not provided.
	 * @throws InvalidArgumentException If the `groupId` argument is not provided.
	 */
	public function __construct( array $args ) {
		parent::__construct( $args );

		if ( $this->_is_exist ) {
			$this->_module_name = $args['moduleName'] ?? null;

			if ( ! $this->_module_name ) {
				throw new InvalidArgumentException( 'The `moduleName` argument is required.' );
			}

			$this->_group_id = $args['groupId'] ?? null;

			if ( ! $this->_group_id ) {
				throw new InvalidArgumentException( 'The `groupId` argument is required.' );
			}

			$this->_is_nested = $args['isNested'] ?? false;
		} else {
			// Placeholder items (isExist: false) may have groupId/moduleName from the caller.
			$this->_module_name = $args['moduleName'] ?? '';
			$this->_group_id    = $args['groupId'] ?? '';
			$this->_is_nested   = $args['isNested'] ?? false;
		}
	}

	/**
	 * Retrieves the data group name of the preset item.
	 *
	 * @since ??
	 *
	 * @return string The data group name of the preset item.
	 */
	public function get_data_group_name(): string {
		return $this->_data['groupName'] ?? '';
	}

	/**
	 * Retrieves the data group ID of the preset item.
	 *
	 * @since ??
	 *
	 * @return string The data group ID of the preset item.
	 */
	public function get_data_group_id(): string {
		return $this->_data['groupId'] ?? '';
	}

	/**
	 * Retrieves the data primary attribute name of the preset item.
	 *
	 * @since ??
	 *
	 * @return string The data primary attribute name of the preset item.
	 */
	public function get_data_primary_attr_name(): string {
		return $this->_data['primaryAttrName'] ?? '';
	}

	/**
	 * Resolves the attribute name for a global preset item group.
	 *
	 * This method generates a resolved attribute name by utilizing the
	 * `GlobalPresetItemGroupAttrNameResolver` class. It combines the provided
	 * attribute name with module and group-specific data to produce the final
	 * resolved name.
	 *
	 * @since ??
	 *
	 * @param string $attr_name The original attribute name to be resolved.
	 *
	 * @return GlobalPresetItemGroupAttrNameResolved The resolved attribute name.
	 */
	public function get_resolved_attr_name( string $attr_name ): GlobalPresetItemGroupAttrNameResolved {
		$module_name            = $this->get_module_name();
		$group_id               = $this->get_group_id();
		$data_module_name       = $this->get_data_module_name();
		$data_group_id          = $this->get_data_group_id();
		$data_primary_attr_name = $this->get_data_primary_attr_name() ?? '';

		return GlobalPresetItemGroupAttrNameResolver::get_attr_name(
			$attr_name,
			$module_name,
			$group_id,
			$data_module_name,
			$data_group_id,
			$data_primary_attr_name
		);
	}

	/**
	 * Collect dynamic subgroup attribute names from visibility tree.
	 *
	 * @since ??
	 *
	 * @param mixed    $value The visibility tree node.
	 * @param string   $path The current path as a dot-delimited string.
	 * @param string[] $accumulator The accumulated attribute names.
	 *
	 * @return string[] The collected dynamic subgroup attribute names.
	 */
	private function _collect_dynamic_subgroup_attr_names( $value, string $path = '', array $accumulator = [] ): array {
		if ( true === $value ) {
			if ( '' !== $path ) {
				$accumulator[] = $path;
			}

			return $accumulator;
		}

		if ( ! is_array( $value ) ) {
			return $accumulator;
		}

		foreach ( $value as $key => $nested_value ) {
			$next_path   = '' === $path ? (string) $key : $path . '.' . $key;
			$accumulator = $this->_collect_dynamic_subgroup_attr_names( $nested_value, $next_path, $accumulator );
		}

		return $accumulator;
	}

	/**
	 * Append the full dynamicOptionGroups branch to resolved attrs.
	 *
	 * @since ??
	 *
	 * @param array $attrs The source attrs.
	 * @param array $resolved_attrs The resolved attrs accumulator.
	 *
	 * @return array Resolved attrs with dynamicOptionGroups appended when available.
	 */
	private function _maybe_append_dynamic_option_groups( array $attrs, array $resolved_attrs ): array {
		$dynamic_option_groups = ArrayUtility::get_value_by_array_path( $attrs, [ 'dynamicOptionGroups' ], null );

		if ( ! is_array( $dynamic_option_groups ) || empty( $dynamic_option_groups ) ) {
			return $resolved_attrs;
		}

		return GlobalPresetItemGroupUtils::maybe_set_attrs(
			[
				'attr'         => $dynamic_option_groups,
				'propertyPath' => [ 'dynamicOptionGroups' ],
				'accumulator'  => $resolved_attrs,
			]
		);
	}

	/**
	 * Read attribute value from array path.
	 *
	 * @since ??
	 *
	 * @param array $attrs The source attributes.
	 * @param array $property_path Attribute path.
	 *
	 * @return mixed|null
	 */
	private static function _get_attr_from_path( array $attrs, array $property_path ) {
		$missing = new \stdClass();
		$value   = ArrayUtility::get_value_by_array_path( $attrs, $property_path, $missing );

		return $missing === $value ? null : $value;
	}

	/**
	 * Resolve value from canonical path with root-alias fallback.
	 *
	 * Some persisted group presets can store values under root aliases
	 * (for example, `designSizing.*`) while canonical metadata resolves to
	 * `module.*`. Prefer alias path first to avoid dropping edited values.
	 *
	 * @since ??
	 *
	 * @param array  $attrs Source attributes.
	 * @param array  $canonical_path Canonical path segments.
	 * @param string $root_group_id Root group identifier.
	 *
	 * @return mixed|null
	 */
	private static function _get_attr_from_canonical_or_root_alias( array $attrs, array $canonical_path, string $root_group_id ) {
		if ( '' === $root_group_id || str_contains( $root_group_id, '.' ) || 1 >= count( $canonical_path ) ) {
			return self::_get_attr_from_path( $attrs, $canonical_path );
		}

		$canonical_root = $canonical_path[0];
		$alias_path     = array_merge( [ $root_group_id ], array_slice( $canonical_path, 1 ) );
		$alias_attr     = self::_get_attr_from_path( $attrs, $alias_path );

		if ( $canonical_root !== $root_group_id && null !== $alias_attr ) {
			return $alias_attr;
		}

		$canonical_attr = self::_get_attr_from_path( $attrs, $canonical_path );

		return null !== $canonical_attr ? $canonical_attr : $alias_attr;
	}

	/**
	 * Normalize root-group alias path to canonical attr path.
	 *
	 * @since ??
	 *
	 * @param string $attr_name Source attribute path.
	 * @param string $group_id Source group id.
	 * @param string $module_name Source module name.
	 *
	 * @return string
	 */
	private static function _normalize_root_group_alias_to_canonical_attr_name( string $attr_name, string $group_id, string $module_name ): string {
		if ( '' === $attr_name || '' === $group_id || str_contains( $group_id, '.' ) || ! str_contains( $attr_name, '.' ) ) {
			return $attr_name;
		}

		$canonical_attr_names = array_values(
			array_filter(
				GlobalPresetItemGroupAttrNameResolver::get_attr_names_by_group( $module_name, $group_id ),
				function ( $candidate ) use ( $group_id ) {
					return is_string( $candidate ) && str_contains( $candidate, '.' ) && $candidate !== $group_id;
				}
			)
		);
		$attr_name_parts      = explode( '.', $attr_name );
		$canonical_attr_name  = ArrayUtility::find(
			$canonical_attr_names,
			function ( $candidate ) use ( $attr_name_parts, $group_id ) {
				$canonical_parts = explode( '.', $candidate );
				return count( $attr_name_parts ) > 1
					&& count( $canonical_parts ) > 1
					&& $canonical_parts[0] !== $group_id
					&& $attr_name_parts[1] === $canonical_parts[1];
			}
		);
		$canonical_attr_name  = ( ! is_string( $canonical_attr_name ) || '' === $canonical_attr_name )
			? ( $canonical_attr_names[0] ?? '' )
			: $canonical_attr_name;

		if ( '' === $canonical_attr_name ) {
			return $attr_name;
		}

		$canonical_parts     = explode( '.', $canonical_attr_name );
		$parts_count         = count( $canonical_parts );
		$has_root_compatible = count( $attr_name_parts ) > 1
			&& $parts_count > 1
			&& $attr_name_parts[1] === $canonical_parts[1];
		$has_full_compatible = count( $attr_name_parts ) >= $parts_count
			&& implode( '.', array_slice( $attr_name_parts, 1, $parts_count - 1 ) ) === implode( '.', array_slice( $canonical_parts, 1 ) );

		if ( ! $has_root_compatible ) {
			return $attr_name;
		}

		if ( $has_full_compatible ) {
			return GlobalPresetItemGroupAttrNameResolver::replace_attr_name_prefix( $attr_name, $canonical_attr_name );
		}

		return GlobalPresetItemGroupAttrNameResolver::replace_attr_name_prefix( $attr_name, $canonical_parts[0] );
	}

	/**
	 * Matches and processes attributes based on group IDs.
	 *
	 * This function checks if the provided attributes array matches certain conditions
	 * based on the data group ID and group ID. If the conditions are met, it processes
	 * and returns the attributes accordingly. Otherwise, it applies a filter to the
	 * attributes and returns the result.
	 *
	 * @since ??
	 *
	 * @param string $attrs_key The attribute key to be matched.
	 *
	 * @return array The processed attributes array.
	 */
	private function _attrs_resolver( string $attrs_key ): array {
		if ( ! $this->is_exist() ) {
			return [];
		}

		$attrs = $this->_data[ $attrs_key ] ?? [];

		if ( ! $attrs ) {
			return $attrs;
		}

		$module_name      = $this->get_module_name();
		$group_id         = $this->get_group_id();
		$data_module_name = $this->get_data_module_name();
		$data_group_id    = $this->get_data_group_id();
		$data_group_name  = $this->get_data_group_name();

		// CSS ID & Classes option groups have a `-id-classes` suffix in their groupId
		// (e.g., 'module.advanced.htmlAttributes-id-classes') but the actual data is stored
		// without this suffix (e.g., 'module.advanced.htmlAttributes.desktop.value.id').
		// Strip the suffix to match the stored data structure.
		$group_id = str_ends_with( $group_id, '-id-classes' ) ? substr( $group_id, 0, -strlen( '-id-classes' ) ) : $group_id;

		// Handle composite groups like Button OG by stripping element suffix.
		// For "button.decoration.button" with groupName "divi/button", extract base "button"
		// to resolve ALL button group attributes (button, background, border, font, etc.).
		// This ensures all sibling properties are included in CSS generation.
		$normalized_group_id = $group_id;
		$is_button_group     = 'divi/button' === $data_group_name;
		$is_image_root_hosts = ! str_contains( $group_id, '.' )
			&& ! str_contains( $data_group_id, '.' )
			&& 1 === preg_match( '/image/i', $group_id )
			&& 1 === preg_match( '/image/i', $data_group_id );
		$is_image_group      = 'divi/image' === $data_group_name || $is_image_root_hosts;
		if ( $is_button_group && str_ends_with( $group_id, '.decoration.button' ) ) {
			$normalized_group_id = preg_replace( '/\.decoration\.button$/', '', $group_id );
		}
		if ( $is_image_group && str_ends_with( $group_id, '.decoration.image' ) ) {
			$normalized_group_id = preg_replace( '/\.decoration\.image$/', '', $group_id );
		}

		// Use normalizedGroupId to determine if we should use the shortcut or call get_attr_names_by_group.
		// After normalization, "buttonOne.decoration.button" becomes "buttonOne" (no dot),
		// so we'll call get_attr_names_by_group to get ALL button attributes for that element.
		$attr_names                       = strpos( $normalized_group_id, '.' )
			? [ $normalized_group_id ]
			: GlobalPresetItemGroupAttrNameResolver::get_attr_names_by_group(
				$module_name,
				$normalized_group_id
			);
		$dynamic_subgroup_visibility_tree = ArrayUtility::get_value_by_array_path(
			$attrs,
			array_merge( [ 'dynamicOptionGroups' ], explode( '.', $group_id ) ),
			null
		);
		$dynamic_subgroup_attr_names      = $this->_collect_dynamic_subgroup_attr_names( $dynamic_subgroup_visibility_tree );
		$attr_names_merged                = array_values( array_unique( array_merge( $attr_names, $dynamic_subgroup_attr_names ) ) );

		// Normalize dataGroupId for composite button groups to ensure proper attribute path conversion.
		// When applying a button preset from Button module to modules with different button attribute names,
		// we need to normalize "button.decoration.button" to "button" so the resolver can
		// correctly match and convert all button attributes (button.* → buttonOne.*, buttonTwo.*, etc.).
		$normalized_data_group_id = $data_group_id;
		if ( $is_button_group && str_ends_with( $data_group_id, '.decoration.button' ) ) {
			$normalized_data_group_id = preg_replace( '/\.decoration\.button$/', '', $data_group_id );
		}
		if ( $is_image_group && str_ends_with( $data_group_id, '.decoration.image' ) ) {
			$normalized_data_group_id = preg_replace( '/\.decoration\.image$/', '', $data_group_id );
		}
		$data_primary_attr_name = $this->get_data_primary_attr_name() ?? '';

		$is_image_root_to_root_mapping = $is_image_group
			&& ! str_contains( $normalized_group_id, '.' )
			&& ! str_contains( $normalized_data_group_id, '.' );

		if ( $is_image_root_to_root_mapping ) {
			$source_image_attr = ArrayUtility::get_value_by_array_path( $attrs, explode( '.', $normalized_data_group_id ) );

			if ( null !== $source_image_attr ) {
				return $this->_maybe_append_dynamic_option_groups(
					$attrs,
					GlobalPresetItemGroupUtils::maybe_set_attrs(
						[
							'attr'         => $source_image_attr,
							'propertyPath' => explode( '.', $normalized_group_id ),
							'accumulator'  => [],
						]
					)
				);
			}
		}

		if ( $attr_names_merged ) {
			// Normalize $data_group_id for comparison (handle -id-classes suffix).
			$normalized_data_group_id = str_ends_with( $data_group_id, '-id-classes' ) ? substr( $data_group_id, 0, -strlen( '-id-classes' ) ) : $data_group_id;
			// Compare normalized versions: use $normalized_group_id which is what's actually used for attribute extraction.
			if ( $data_module_name === $module_name && $normalized_data_group_id === $normalized_group_id ) {
				$resolved_attrs = array_reduce(
					$attr_names_merged,
					function ( array $accumulator, string $attr_name ) use ( $attrs, $normalized_group_id ): array {
						$property_path = explode( '.', $attr_name );
						$attr          = self::_get_attr_from_canonical_or_root_alias( $attrs, $property_path, $normalized_group_id );

						return GlobalPresetItemGroupUtils::maybe_set_attrs(
							[
								'attr'         => $attr,
								'propertyPath' => $property_path,
								'accumulator'  => $accumulator,
							]
						);
					},
					[]
				);

				return $this->_maybe_append_dynamic_option_groups( $attrs, $resolved_attrs );
			}

			$resolved_attrs = array_reduce(
				$attr_names_merged,
				function (
					$accumulator,
					$attr_name
				) use (
					$attrs,
					$module_name,
					$normalized_group_id,
					$data_module_name,
					$normalized_data_group_id,
					$data_primary_attr_name
				) {
					$attr_name_resolved = GlobalPresetItemGroupAttrNameResolver::get_attr_name(
						$attr_name,
						$module_name,
						$normalized_group_id,
						$data_module_name,
						$normalized_data_group_id,
						$data_primary_attr_name
					);

					if ( null !== $attr_name_resolved->get_attr_callback() ) {
						$attr = call_user_func( $attr_name_resolved->get_attr_callback(), $attrs );
					} else {
						$resolved_attr_name        = $attr_name_resolved->get_attr_name();
						$resolved_attr_name        = self::_normalize_root_group_alias_to_canonical_attr_name(
							$resolved_attr_name,
							$normalized_data_group_id,
							$data_module_name
						);
						$direct_attr               = self::_get_attr_from_path( $attrs, explode( '.', $attr_name ) );
						$mapped_attr               = self::_get_attr_from_path( $attrs, explode( '.', $resolved_attr_name ) );
						$should_prefer_mapped_attr = $resolved_attr_name !== $attr_name;
						$attr                      = $should_prefer_mapped_attr ? ( $mapped_attr ?? $direct_attr ) : ( $direct_attr ?? $mapped_attr );
					}

					if ( null !== $attr_name_resolved->get_property_path_callback() ) {
						$property_path = call_user_func( $attr_name_resolved->get_property_path_callback() );
					} else {
						$property_path = explode( '.', $attr_name );
					}

					return GlobalPresetItemGroupUtils::maybe_set_attrs(
						[
							'attr'         => $attr,
							'propertyPath' => $property_path,
							'accumulator'  => $accumulator,
						]
					);
				},
				[]
			);

			return $this->_maybe_append_dynamic_option_groups( $attrs, $resolved_attrs );
		}

		$property_path = explode( '.', $attrs_key );
		$attr          = ArrayUtility::get_value_by_array_path( $attrs, $property_path );

		return GlobalPresetItemGroupUtils::maybe_set_attrs(
			[
				'attr'         => $attr,
				'propertyPath' => $property_path,
				'accumulator'  => [],
			]
		);
	}

	/**
	 * Retrieves the data attributes.
	 *
	 *  @since ??
	 *
	 * @return array The data attributes.
	 */
	public function get_data_attrs(): array {
		return $this->_attrs_resolver( 'attrs' );
	}

	/**
	 * Get the render attrs.
	 *
	 * @since ??
	 *
	 * @return array The render attrs of the preset.
	 */
	public function get_data_render_attrs(): array {
		return $this->_attrs_resolver( 'renderAttrs' );
	}

	/**
	 * Retrieves the data style attributes.
	 *
	 * @since ??
	 *
	 * @return array The data style attributes.
	 */
	public function get_data_style_attrs(): array {
		return $this->_attrs_resolver( 'styleAttrs' );
	}

	/**
	 * Retrieves the module name where the preset item being used.
	 *
	 * @since ??
	 *
	 * @return string The module name where the preset item being used.
	 */
	public function get_module_name(): string {
		return $this->_module_name;
	}

	/**
	 * Retrieves the group ID where the preset item being used.
	 *
	 * @since ??
	 *
	 * @return string The group ID where the preset item being used.
	 */
	public function get_group_id(): string {
		return $this->_group_id;
	}

	/**
	 * Get the selector class name.
	 *
	 * @since ??
	 *
	 * @return string The selector class name.
	 */
	public function get_selector_class_name(): string {
		return GlobalPresetItemUtils::generate_preset_class_name(
			[
				'presetType'       => $this->get_data_type(),
				'presetModuleName' => $this->get_module_name(),
				'presetGroupName'  => $this->get_data_group_name(),
				'presetGroupId'    => $this->get_group_id(),
				'presetId'         => $this->as_default() ? 'default' : $this->get_data_id(),
				'isNested'         => $this->_is_nested,
			]
		);
	}

	/**
	 * Check if this preset is nested (comes from a module preset).
	 *
	 * @since ??
	 *
	 * @return bool True if nested, false otherwise.
	 */
	public function is_nested(): bool {
		return $this->_is_nested;
	}

	/**
	 * Replaces preset attributes paths with their corresponding values from the provided attributes.
	 *
	 * @since ??
	 *
	 * @param array  $attrs_items The attributes items mapping.
	 * @param string $attrs_key The attribute key to be matched.
	 *
	 * @return array The filtered attributes.
	 */
	public function maybe_replace_attrs_path( array $attrs_items, string $attrs_key ): array {
		$attrs = $this->_data[ $attrs_key ] ?? [];

		if ( ! $attrs ) {
			return $attrs;
		}

		$module_name      = $this->get_module_name();
		$group_id         = $this->get_group_id();
		$data_module_name = $this->get_data_module_name();
		$data_group_id    = $this->get_data_group_id();
		$preset_item      = $this;

		// If the module name and group ID are the same as the data module name and data group ID,
		// it indicates that the preset item is created by the same module and group that being edited.
		// In this case, we can return the attributes as is.
		if ( $data_module_name === $module_name && $data_group_id === $group_id ) {
			return array_reduce(
				array_keys( $attrs_items ),
				function ( array $accumulator, string $attr_name ) use ( $attrs ): array {
					$property_path = explode( '.', $attr_name );
					$attr          = ArrayUtility::get_value_by_array_path( $attrs, $property_path );

					return GlobalPresetItemGroupUtils::maybe_set_attrs(
						[
							'attr'         => $attr,
							'propertyPath' => $property_path,
							'accumulator'  => $accumulator,
						]
					);
				},
				[]
			);
		}

		return array_reduce(
			array_keys( $attrs_items ),
			function (
				$accumulator,
				$attr_name
			) use (
				$attrs,
				$attrs_items,
				$preset_item,
				$attrs_key
			) {
				$attrs_item   = $attrs_items[ $attr_name ] ?? [];
				$use_fallback = $attrs_item['useFallback'] ?? true;

				if ( is_callable( $use_fallback ) ) {
					$falback_result = call_user_func(
						$use_fallback,
						[
							'attrsKey'   => $attrs_key,
							'presetItem' => $preset_item,
						]
					);

					if ( $falback_result ) {
						return GlobalPresetItemGroupUtils::maybe_set_attrs(
							[
								'attr'         => $falback_result['attr'],
								'propertyPath' => $falback_result['propertyPath'],
								'accumulator'  => $accumulator,
							]
						);
					}

					return $accumulator;
				}

				if ( true === $use_fallback ) {
					$attr_name_resolved = $preset_item->get_resolved_attr_name( $attr_name );

					$property_path = explode( '.', $attr_name );
					$attr_path     = ( $attr_name_resolved->get_attr_name() === $attr_name ) ? $property_path : explode( '.', $attr_name_resolved->get_attr_name() );
					$attr          = ArrayUtility::get_value_by_array_path( $attrs, $attr_path );

					return GlobalPresetItemGroupUtils::maybe_set_attrs(
						[
							'attr'         => $attr,
							'propertyPath' => $property_path,
							'accumulator'  => $accumulator,
						]
					);
				}

				$property_path = explode( '.', $attr_name );
				$attr          = ArrayUtility::get_value_by_array_path( $attrs, $property_path );

				return GlobalPresetItemGroupUtils::maybe_set_attrs(
					[
						'attr'         => $attr,
						'propertyPath' => $property_path,
						'accumulator'  => $accumulator,
					]
				);
			},
			[]
		);
	}
}
