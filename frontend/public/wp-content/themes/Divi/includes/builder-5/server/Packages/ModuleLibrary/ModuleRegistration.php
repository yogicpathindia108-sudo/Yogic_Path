<?php
/**
 * ModuleLibrary: Module Registration class
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary;

use ET\Builder\Framework\Utility\StringUtility;
use ET\Builder\Framework\Utility\ArrayUtility;
use ET\Builder\Framework\Utility\Filesystem;
use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Packages\Conversion\Conversion;
use ET\Builder\Packages\Module\Options\Attributes\AttributeUtils;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Options\Sticky\StickyUtils;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use WP_Block_Type;
use WP_Block_Type_Registry;
use WP_Block;
use ET\Builder\Packages\GlobalData\GlobalPreset;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\GlobalData\GlobalPresetItem;
use ET\Builder\Packages\Module\Options\Loop\LoopUtils;
use ET\Builder\Packages\ModuleLibrary\LoopHandler;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable Squiz.Commenting.InlineComment -- Temporarily disabled to get the PR CI pass for now. TODO: Fix this later.
// phpcs:disable Squiz.PHP.CommentedOutCode.Found -- Temporarily disabled to get the PR CI pass for now. TODO: Fix this later.
// phpcs:disable ET.Comments.Todo.TodoFound -- Temporarily disabled to get the PR CI pass for now. TODO: Fix this later.
// phpcs:disable WordPress.NamingConventions.ValidHookName -- Temporarily disabled to get the PR CI pass for now. TODO: Fix this later.

/**
 * ModuleRegistration class.
 *
 * This is a helper class that provides an easier interface to register modules on the backend.
 *
 * @since ??
 */
class ModuleRegistration {

	/**
	 * All registered custom modules folders.
	 *
	 * @var array
	 */
	private static $_all_custom_modules_folders = [];

	/**
	 * All core modules metadata.
	 *
	 * @var array
	 */
	private static $_all_core_modules_metadata;

	/**
	 * All core modules conversion outline.
	 *
	 * @var array
	 */
	private static $_all_core_modules_conversion_outline;

	/**
	 * Module conversion outline cache.
	 *
	 * @var array
	 */
	private static $_module_conversion_outline_cache = [];

	/**
	 * All core modules default printed style attributes.
	 *
	 * @var array
	 */
	private static $_all_core_modules_default_printed_style_attributes;

	/**
	 * All core modules default render attributes.
	 *
	 * @var array
	 */
	private static $_all_core_modules_default_render_attributes;

	/**
	 * All core modules mapping module name to relative directory.
	 *
	 * @var array
	 */
	private static $_all_core_modules_mapping_module_name_to_relative_dir;

	/**
	 * Retrieves the core module name derived from the metadata folder path.
	 *
	 * This function processes the given metadata folder path to extract and
	 * return the core module name in the appropriate format.
	 *
	 * Examples:
	 * - Divi/includes/builder-5/visual-builder/packages/module-library/src/components/[module-name]/module.json
	 * - Divi/includes/builder-5/visual-builder/packages/module-library/src/components/woocommerce/[module-name]/module.json
	 *
	 * @since ??
	 *
	 * @param string $metadata_folder The path to the metadata folder.
	 *
	 * @return string The core module name derived from the metadata folder.
	 */
	public static function get_core_module_name_from_metadata_folder( string $metadata_folder ): string {
		$core_module_relative_dir = str_replace( ET_BUILDER_5_DIR . 'visual-builder/packages/module-library/src/components/', '', $metadata_folder );
		$core_module_relative_dir = rtrim( $core_module_relative_dir, '/' );

		return $core_module_relative_dir;
	}

	/**
	 * Process conversion outline.
	 *
	 * @since ??
	 *
	 * @param array  $metadata                The metadata of the module.
	 * @param string $conversion_outline_file The path to the conversion outline file.
	 *
	 * @return bool True if the conversion outline is processed successfully, false otherwise.
	 */
	public static function process_conversion_outline( array $metadata, ?string $conversion_outline_file = null ): bool {
		$module_conversion_outline = self::get_module_conversion_outline( $metadata['name'], $conversion_outline_file );

		if ( $module_conversion_outline ) {
			$module_attrs_conversion_map = Conversion::getModuleConversionMap( $module_conversion_outline );

			if ( $module_attrs_conversion_map ) {
				add_filter(
					'divi.conversion.moduleLibrary.conversionMap',
					function ( $module_library_conversion_map ) use ( $metadata, $module_attrs_conversion_map ) {
						return array_merge(
							$module_library_conversion_map,
							[ $metadata['name'] => $module_attrs_conversion_map ]
						);
					}
				);

				return true;
			}
		}

		return false;
	}

	/**
	 * Registers a module with the given metadata folder and arguments.
	 *
	 * This method reads the metadata `module.json` file from the specified folder, decodes it,
	 * and merges the metadata with the default arguments. It then registers the block type
	 * using the merged arguments and returns the registered block type.
	 *
	 * @since          ??
	 *
	 * @param string $metadata_folder The path to the metadata folder.
	 * @param array  $args             Optional. An array of arguments to merge with the default arguments.
	 *                                 Default `[]`.
	 *                                 Accepts any public property of `WP_Block_Type`. See
	 *                                 `WP_Block_Type::__construct()` for more information on accepted arguments.
	 *
	 * @return WP_Block_Type|null The registered block type or `null` if the metadata file does not exist or cannot be
	 *                            decoded.
	 *
	 * @throws \Exception If the metadata file cannot be read or decoded.
	 * @example        :
	 *                 ```php
	 *                 ModuleRegistration::register_module(
	 *                 '/path/to/metadata/folder',
	 *                 [
	 *                 'title' => 'Custom Title',
	 *                 'attributes' => [
	 *                 'attr1' => 'value1',
	 *                 'attr2' => 'value2',
	 *                 ],
	 *                 ]
	 *                 );
	 *                 ```
	 * @example        :
	 *                 ```php
	 *                 ModuleRegistration::register_module( '/path/to/metadata/folder' );
	 *                 ```
	 */
	public static function register_module( string $metadata_folder, array $args = [] ): ?WP_Block_Type {
		// Remove trailing slash from metadata folder path.
		$metadata_folder = untrailingslashit( $metadata_folder );

		/*
		 * Normalize the path to this file and the passed metadata folder path before comparing them.
		 * (Do not use ET_BUILDER_5_DIR yet, because this causes false negatives when the Divi theme is
		 * symlinked into a WordPress themes directory. Once the core module check is complete, we'll
		 * replace the metadata folder path with ET_BUILDER_5_DIR.)
		 */
		$metadata_folder = wp_normalize_path( trailingslashit( realpath( $metadata_folder ) ) );
		$et_builder_dir  = wp_normalize_path( trailingslashit( realpath( dirname( __DIR__, 3 ) ) ) );
		$core_module_dir = wp_normalize_path( $et_builder_dir . '/visual-builder/packages/module-library/' );

		$core_modules_metadata = self::get_all_core_modules_metadata();

		// Get the metadata file path.
		$metadata_file = wp_normalize_path( $metadata_folder . '/module.json' );

		// Check if the metadata directory is a core module directory.
		$is_core_module = str_starts_with( $metadata_folder, $core_module_dir );

		// If the module is not a core module, the metadata file must exist.
		$metadata_file_exists = $is_core_module || file_exists( $metadata_file );

		/*
		 * Exit early.
		 * If no metadata file exists and the module isn't a core module, we don't have anything to add.
		 */
		if ( ! $metadata_file_exists ) {
			return null;
		}

		// Use the same base path if $metadata_folder and ET_BUILDER_5_DIR resolve to the same location.
		if ( StringUtility::starts_with( $metadata_folder, $et_builder_dir ) ) {
			$metadata_folder = str_replace( $et_builder_dir, ET_BUILDER_5_DIR, $metadata_folder );
		}

		// Try to get metadata from the static cache for core modules.
		$metadata = [];
		if ( $is_core_module ) {
			$core_module_relative_dir = self::get_core_module_name_from_metadata_folder( $metadata_folder );
			if ( ! empty( $core_modules_metadata[ $core_module_relative_dir ] ) ) {
				$metadata = $core_modules_metadata[ $core_module_relative_dir ];
			}
		}

		// If metadata is not found in the static cache, read it from the file.
		if ( empty( $metadata ) ) {
			// modeling after WP's wp_json_file_decode() function.
			// but wihh silent failing allowed, whereas
			// wp_json_file_decode() will trigger_error() if it fails.
			$filename = wp_normalize_path( realpath( $metadata_file ) );

			if ( ! $filename ) {
				return null;
			}

			$metadata = json_decode( Filesystem::get()->get_contents( $filename ), true );
		}

		if ( JSON_ERROR_NONE !== json_last_error() || empty( $metadata ) ) {
			return null;
		}

		$base_args_defaults = [
			'title'      => 'Module',
			'titles'     => 'Modules',
			'textdomain' => 'et_builder_5',
			'moduleIcon' => 'divi/module',
			'category'   => 'module',
			'attributes' => [],
		];

		$register_args = array_merge( $base_args_defaults, $metadata, $args );

		// Generate default, default printed style, and default settings attributes from module metadata.
		if ( isset( $register_args['render_callback'] ) ) {
			$render_callback = $register_args['render_callback'];

			// Wrap render_callback with loop handling logic.
			$loop_wrapped_render_callback = LoopUtils::wrap_render_callback_for_loop_no_results( $render_callback );

			// Modify module's render callback. Insert generated defaults attributes and ModuleElements instance.
			$register_args['render_callback'] = function ( $block_attributes, $content, WP_Block $block ) use ( $loop_wrapped_render_callback, $metadata ) {

				$default_printed_style_attrs = ModuleRegistration::get_default_attrs( $block->name, 'defaultPrintedStyle', $metadata );
				$default_render_attrs        = ModuleRegistration::get_default_attrs( $block->name, 'default', $metadata );

				// Check if module has default render background color.
				// This is used to apply !important when user removes the background color.
				$background_color       = $default_render_attrs['module']['decoration']['background']['desktop']['value']['color'] ?? null;
				$has_default_background = ! empty( $background_color ) && 'module' === $metadata['category'];

				// Get merged attributes from all stacked presets (preset-only, without module attributes).
				// This is used for preset detection and to populate preset printed style attributes.
				$preset_attrs_raw = GlobalPreset::get_merged_attrs(
					[
						'moduleName'  => $block->name,
						'moduleAttrs' => $block_attributes ?? [],
						'presetOnly'  => true,
					]
				);
				$preset_attrs     = ModuleUtils::remove_matching_values( $preset_attrs_raw, $default_printed_style_attrs );

				// Get merged render attributes from all stacked presets.
				// This ensures that HTML structure attributes from earlier presets are preserved
				// when later presets don't explicitly override them.
				$preset_render_attrs = GlobalPreset::get_merged_preset_render_attrs(
					[
						'moduleName'  => $block->name,
						'moduleAttrs' => $block_attributes ?? [],
					]
				);

				// Get group presets and their render/style attributes before attribute merge.
				$group_presets = GlobalPreset::get_selected_group_presets(
					[
						'moduleName'  => $block->name,
						'moduleAttrs' => $block_attributes ?? [],
					]
				);

				$merged_group_preset_attrs = GlobalPreset::merge_selected_group_presets_by_priority( $group_presets );
				$group_render_attrs        = $merged_group_preset_attrs['renderAttrs'];
				$group_style_attrs         = $merged_group_preset_attrs['styleAttrs'];

				// Reference for stripping baked preset duplicates from module attrs (attrs + styleAttrs only).
				$preset_cleanup_reference  = array_replace_recursive( [], $preset_attrs_raw, $group_style_attrs );
				$has_module_preset         = ModuleUtils::has_active_module_preset_assignment( $block_attributes['modulePreset'] ?? null );
				$has_group_preset          = ModuleUtils::has_active_group_preset_assignment( $block_attributes['groupPreset'] ?? null );
				$should_strip_preset_dupes = ( $has_module_preset || $has_group_preset ) && ! empty( $preset_cleanup_reference );

				// Strip block attributes before preset/default merge so baked import values are not re-injected.
				if ( $should_strip_preset_dupes && ! empty( $block_attributes ) ) {
					$block_attributes = ModuleUtils::remove_matching_values( $block_attributes, $preset_cleanup_reference );
				}

				// Remove preset attributes that are presents in block attributes.
				if ( $preset_attrs && $block_attributes ) {
					$preset_attrs = ModuleUtils::remove_matching_attrs( $preset_attrs, $block_attributes );
				}

				// Get default attributes for this module.
				$default_attributes = ModuleRegistration::get_default_attrs( $block->name, 'default', $metadata );

				// Replace default attributes with corresponding preset attributes.
				if ( $default_attributes && $preset_attrs ) {
					$default_attributes = ModuleUtils::replace_matching_attrs( $default_attributes, $preset_attrs );
				}

				// Create separate preset printed style attributes instead of merging into default printed style attrs.
				// This allows proper separation of concerns between default and preset styles in the module style renderer.
				$preset_printed_style_attrs = array_replace_recursive(
					[],
					$preset_attrs_raw,
					$group_style_attrs
				);

				// Merge default attributes, preset attributes, group render attributes and user defined attributes. This ensures every module's attribute parameter
				// has considered default, preset, group render and user defined attributes on rendering component.
				$module_attrs_with_default = array_replace_recursive(
					$default_attributes,
					$preset_render_attrs,
					$group_render_attrs,
					$block_attributes
				);

				// Special handling for fields that should be merged instead of replaced.
				// array_replace_recursive replaces arrays, but some fields need custom merge logic.
				$module_attrs_with_default = ArrayUtility::apply_mergeable_fields_logic(
					$module_attrs_with_default,
					$preset_render_attrs,
					$group_render_attrs,
					$block_attributes
				);

				$filter_args = [
					'name'          => $block->name,
					'attrs'         => $module_attrs_with_default,
					'id'            => $block->parsed_block['id'] ?? null,
					'storeInstance' => $block->parsed_block['storeInstance'] ?? null,
				];

				$module_attrs = $module_attrs_with_default;

				if ( 'child-module' === $block->block_type->category ) {
					$only_block_attributes     = array_diff_multidimensional( $block_attributes, $default_attributes, true ); // WP merge default attributes with $block_attributes. But we need to only block attributes without default attributes.
					$block_id_for_parent       = $block->parsed_block['id'] ?? null;
					$store_instance_for_parent = $block->parsed_block['storeInstance'] ?? null;
					$parent                    = ( null !== $block_id_for_parent && is_string( $block_id_for_parent ) ) ? BlockParserStore::get_parent( $block_id_for_parent, $store_instance_for_parent ) : null;

					if ( null === $parent ) {
						// Use default module attributes without parent merging.
						$module_attrs = $module_attrs_with_default;
					} else {
						$parent_attrs              = $parent->attrs ?? [];
						$parent_default_attributes = ModuleRegistration::get_default_attrs( $parent->blockName ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Matches WordPress block parser conventions.

						// Get preset attributes for parent module.
						$parent_item_preset  = GlobalPreset::get_selected_preset(
							[
								'moduleName'  => $parent->blockName, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Matches WordPress block parser conventions.
								'moduleAttrs' => $parent_attrs ?? [],
							]
						);
						$parent_preset_attrs = $parent_item_preset->get_data_attrs();

						$parent_group_presets = GlobalPreset::get_selected_group_presets(
							[
								'moduleName'  => $parent->blockName, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Matches WordPress block parser conventions.
								'moduleAttrs' => $parent_attrs ?? [],
							]
						);

						$parent_group_child_attrs = [];
						foreach ( $parent_group_presets as $group_id => $group_preset_item ) {
							if ( $group_preset_item instanceof GlobalPresetItem ) {
								$parent_group_preset_attrs = $group_preset_item->get_data_attrs();
								$parent_group_child_attrs  = array_replace_recursive(
									$parent_group_child_attrs,
									$parent_group_preset_attrs['children'] ?? []
								);
							}
						}

						$module_attrs = array_replace_recursive(
							$parent_default_attributes['children'] ?? [],
							$default_attributes ?? [],
							$parent_preset_attrs['children'] ?? [],
							$parent_group_child_attrs,
							$preset_attrs,
							$preset_render_attrs,
							$group_render_attrs,
							$parent_attrs['children'] ?? [],
							$only_block_attributes
						);

						$filter_args['parentAttrs'] = $parent_attrs;
					}
				}

				$module_attrs       = apply_filters( 'divi_module_library_register_module_attrs', $module_attrs, $filter_args );
				$module_style_attrs = $module_attrs;

				// Keep runtime attrs intact for classnames/script data/render decisions, while stripping
				// preset duplicates only from style attrs to avoid module-level CSS overriding preset CSS.
				if ( $should_strip_preset_dupes && ! empty( $module_style_attrs ) ) {
					$module_style_attrs = ModuleUtils::remove_matching_values( $module_style_attrs, $preset_cleanup_reference );
				}

				$is_disabled_everywhere = ModuleRegistration::_is_disabled_on_all_breakpoints( $module_attrs );
				$is_interaction_target  = ModuleRegistration::_is_interaction_target( $module_attrs );
				$is_generating_excerpt  = isset( $GLOBALS['divi_generating_excerpt'] ) && true === $GLOBALS['divi_generating_excerpt'];

				if ( $is_disabled_everywhere && ! $is_interaction_target ) {
					return '';
				}

				// Skip selected modules during excerpt generation to prevent UI labels/noise in automatic excerpts.
				if ( $is_generating_excerpt && ModuleRegistration::_is_excerpt_excluded_module( $block->name ) ) {
					return '';
				}

				// Check whether the current module is inside another sticky module or not. The FE
				// implementation is bit different than VB where we use store related function due
				// to we need access to the store instance to get all blocks. Meanwhile in FE, we
				// can directly check all blocks from the parsed block.
				$block_id_for_sticky = $block->parsed_block['id'] ?? null;
				$sticky_parent_id    = null;
				if ( null !== $block_id_for_sticky && is_string( $block_id_for_sticky ) ) {
					$store_instance_for_sticky = $block->parsed_block['storeInstance'] ?? null;
					$all_blocks                = BlockParserStore::get_all( $store_instance_for_sticky );
					$sticky_parent_id          = StickyUtils::is_inside_sticky_module(
						$block_id_for_sticky,
						$all_blocks
					);
				}

				// Check if the current module is nested. Only module with `nestable` property set to true will be
				// check for actual nested module condition for performance reason (most module isn't nestable, it is faster
				// to skip the check for them).
				$block_id_for_nested = $block->parsed_block['id'] ?? null;
				$is_nested_module    = false;
				if ( null !== $block_id_for_nested && is_string( $block_id_for_nested ) && self::is_nestable( $block->name ) ) {
					$store_instance_for_nested = $block->parsed_block['storeInstance'] ?? null;
					$is_nested_module          = BlockParserStore::is_nested_module(
						$block_id_for_nested,
						$store_instance_for_nested
					);
				}

				// Get parent layout style.

				// Process targeted custom attributes before creating ModuleElements.
				$targeted_attributes    = [];
				$custom_attributes_data = $module_attrs['module']['decoration']['attributes'] ?? [];
				if ( ! empty( $custom_attributes_data ) ) {
					$targeted_attributes = AttributeUtils::separate_attributes_by_target_element( $custom_attributes_data );
				}

				// Create instance of ModuleElements and pass the instance as parameter for consistency and simplicity.
				$block_id_for_layout   = $block->parsed_block['id'] ?? null;
				$is_parent_flex_layout = false;
				$is_parent_grid_layout = false;
				if ( null !== $block_id_for_layout && is_string( $block_id_for_layout ) ) {
					$store_instance_for_layout = $block->parsed_block['storeInstance'] ?? null;
					$is_parent_flex_layout     = ModuleUtils::is_parent_flex_layout( $block_id_for_layout, $store_instance_for_layout );
					$is_parent_grid_layout     = ModuleUtils::is_parent_grid_layout( $block_id_for_layout, $store_instance_for_layout );
				}
				$elements = new ModuleElements(
					[
						'id'                       => $block->parsed_block['id'] ?? '',
						'is_custom_post_type'      => Conditions::is_custom_post_type(),
						'stickyParentId'           => $sticky_parent_id,
						'is_nested_module'         => $is_nested_module,
						'name'                     => $block->name,
						'moduleAttrs'              => $module_style_attrs,
						'runtimeModuleAttrs'       => $module_attrs,
						'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
						'presetPrintedStyleAttrs'  => $preset_printed_style_attrs,
						'hasDefaultBackground'     => $has_default_background,
						'moduleMetadata'           => $block->block_type,
						'orderIndex'               => $block->parsed_block['orderIndex'] ?? null,
						'storeInstance'            => $block->parsed_block['storeInstance'] ?? null,
						'is_parent_flex_layout'    => $is_parent_flex_layout,
						'is_parent_grid_layout'    => $is_parent_grid_layout,
						'targetedAttributes'       => $targeted_attributes,
					]
				);

				// Prevent rendering modules restricted to specific Theme Builder layouts.
				// For example, `divi/post-content` with `themeBuilderLayouts: ['body']` should
				// not render on header or footer layouts even if it exists in those layouts.
				$appearance_tb_layouts = $metadata['appearance']['themeBuilderLayouts'] ?? null;

				if ( ! empty( $appearance_tb_layouts ) ) {
					$layout_type_to_tb_layout = [
						'et_header_layout' => 'header',
						'et_body_layout'   => 'body',
						'et_footer_layout' => 'footer',
					];

					$current_layout_type = BlockParserStore::get_layout_type();
					$current_tb_layout   = $layout_type_to_tb_layout[ $current_layout_type ] ?? null;

					if ( $current_tb_layout && ! in_array( $current_tb_layout, $appearance_tb_layouts, true ) ) {
						return '';
					}
				}

				/**
				 * Filters the module render block.
				 *
				 * Useful for disabling/enabling module render block functionality.
				 *
				 * @since ??
				 *
				 * @param boolean True to display the module render block, False to hide it.
				 */
				$is_displayable = apply_filters( 'divi_module_library_register_module_render_block', true, $block, $module_attrs );

				// Always render the module to ensure styles are registered (for Dynamic Assets),
				// but conditionally return the output based on display conditions.
				// This follows the same pattern as Divi 4: render first, then decide to keep/remove output.
				$module_output = call_user_func(
					$loop_wrapped_render_callback,
					$module_attrs,
					$content,
					$block,
					$elements,
					$default_printed_style_attrs
				);

				// If not displayable, return empty string.
				// Note: Elements disabled on all breakpoints are removed from DOM unless they're used as interaction targets.
				// Elements used as interaction targets are rendered (but hidden with CSS) to ensure they are present in the DOM for interactions to work correctly.
				if ( ! $is_displayable ) {
					return '';
				}

				// Return the rendered output.
				return $module_output;
			};
		}

		// TODO, create the equivalent of this TS implementation in PHP:
		// const conversionOutline = getModuleConversionOutline(config, metadata?.d4Shortcode);
		// const getModuleConversionOutline = (
		//  config: Omit<ModuleLibrary.Module.RegisterDefinition, 'metadata'>,
		//  d4Shortcode = '',
		//   ): ModuleConversionOutline => {
		//  if (config?.conversionOutline) {
		//    return config.conversionOutline;
		//  }

		//  if (! d4Shortcode) {
		//    return {};
		//  }

		//  return getPossibleModuleConversionOutline(d4Shortcode);
		// };

		$conversion_outline_file = $metadata_folder . 'conversion-outline.json';

		/**
		 * Filters the module conversion outline file for a Divi module during conversion.
		 *
		 * This filter allows developers to modify the module conversion outline file for a Divi module during conversion.
		 *
		 * By default, the module conversion outline file is located in the module's metadata directory.
		 *
		 * @since ??
		 *
		 * @param string $conversion_outline_file The default module conversion outline file path.
		 * @param string $module_name             The module name.
		 */
		$conversion_outline_file = apply_filters( 'divi.moduleLibrary.conversion.moduleConversionOutlineFile', $conversion_outline_file, $metadata['name'] );

		// We need a conversion outline when we import d4 library items to d5 with presets.
		// We can't load the conversion file without a specific hook, for performance reasons.
		add_action(
			'et_pb_before_library_preset_import',
			function () use ( $metadata, $conversion_outline_file ) {
				ModuleRegistration::process_conversion_outline( $metadata, $conversion_outline_file );
			}
		);

		// Also process conversion outlines before D4 to D5 conversion in visual builder.
		// This is needed because conversion outlines are normally only processed in admin.
		add_action(
			'divi_visual_builder_before_d4_conversion',
			function () use ( $metadata, $conversion_outline_file ) {
				ModuleRegistration::process_conversion_outline( $metadata, $conversion_outline_file );
			}
		);

		// Let's not do all conversion processing here, if not needed,
		// because this will be a performance hit, as this code runs on every page load,
		// and were not going to be converting modules on every page load.
		// is admin or is PHP Unit test
		if ( ( is_admin() || defined( 'WP_TESTS_DOMAIN' ) ) && file_exists( $conversion_outline_file ) ) {
			self::process_conversion_outline( $metadata, $conversion_outline_file );
		}

		// we need to roll our own version of register_block_type_from_metadata()
		// because inside of that, there is file_exists check, and also fetching and json decoding the file,
		// which we just did above, so lets save the time from doing that all again
		// additionally, they have the concept of a PHP array, so they can even skip the json_decode step
		// so lets do that as well, and we can skip the file_exists check, because we know it exists
		// for OUR core modules.
		// The old way: register_block_type( $metadata['name'], $register_args ).
		$registered_block_type = self::register_block_type_from_metadata( $metadata['name'], $metadata_file, $register_args );

		if ( false === $registered_block_type ) {
			return null;
		}

		// Store the folder path for custom modules to track their location.
		if ( ! $is_core_module ) {
			self::$_all_custom_modules_folders[ $registered_block_type->name ] = $metadata_folder;
		}

		return $registered_block_type;
	}

	/**
	 * Registers a block type from the metadata stored in the `block.json` file.
	 *
	 * @param string $block_type    Block type name including namespace prefix.
	 * @param string $metadata_file Path to the block metadata file.
	 * @param array  $metadata      Block type metadata.
	 * @return WP_Block_Type|false The registered block type on success, or false on failure.
	 */
	public static function register_block_type_from_metadata( $block_type, $metadata_file, $metadata = [] ) {
		/*
		Divi Note:
		Skipping this section, which was for core WP blocks, because we don't need it
		Skipping from here:
		/*
		* Get an array of metadata from a PHP file.
		...
		(skipping whole $core_blocks_meta section)
		...
		// If metadata is not found in the static cache, read it from the file.
		if ( $metadata_file_exists && empty( $metadata ) ) {
			$metadata = wp_json_file_decode( $metadata_file, array( 'associative' => true ) );
		}
		... end of skipping
		*/

		// Divi Note: the below is NOT identical to the core function register_block_type_from_metadata().
		// We are skipping the file_exists check, because we know it exists.
		if ( ! is_array( $metadata ) || empty( $metadata['name'] ) ) {
			return false;
		}
		$metadata['file'] = wp_normalize_path( realpath( $metadata_file ) );
		// /Divi Note.

		// Divi Note: the below is identical to the core function register_block_type_from_metadata().
		/**
		 * Filters the metadata provided for registering a block type.
		 *
		 * @since 5.7.0
		 *
		 * @param array $metadata Metadata for registering a block type.
		 */
		$metadata = apply_filters( 'block_type_metadata', $metadata );
		// /Divi Note.

		// Divi Note: Skipping this section, which was for core WP blocks, because we don't need it
		// Add `style` and `editor_style` for core blocks if missing.
		// /Divi Note.

		// Divi Note: the below is identical to the core function register_block_type_from_metadata().
		$settings          = [];
		$property_mappings = [
			'apiVersion'      => 'api_version',
			'name'            => 'name',
			'title'           => 'title',
			'category'        => 'category',
			'parent'          => 'parent',
			'ancestor'        => 'ancestor',
			'icon'            => 'icon',
			'description'     => 'description',
			'keywords'        => 'keywords',
			'attributes'      => 'attributes',
			'providesContext' => 'provides_context',
			'usesContext'     => 'uses_context',
			'selectors'       => 'selectors',
			'supports'        => 'supports',
			'styles'          => 'styles',
			'variations'      => 'variations',
			'example'         => 'example',
			'allowedBlocks'   => 'allowed_blocks',
		];
		$textdomain        = ! empty( $metadata['textdomain'] ) ? $metadata['textdomain'] : null;
		$i18n_schema       = get_block_metadata_i18n_schema();

		foreach ( $property_mappings as $key => $mapped_key ) {
			if ( isset( $metadata[ $key ] ) ) {
				$settings[ $mapped_key ] = $metadata[ $key ];
				// Divi Note: Skipping the file exists check, because we know it exists.
				if ( /* $metadata_file_exists && */ $textdomain && isset( $i18n_schema->$key ) ) {
					$settings[ $mapped_key ] = translate_settings_using_i18n_schema( $i18n_schema->$key, $settings[ $mapped_key ], $textdomain );
				}
			}
		}

		if ( ! empty( $metadata['render'] ) ) {
			$template_path = wp_normalize_path(
				realpath(
					dirname( $metadata['file'] ) . '/' .
					remove_block_asset_path_prefix( $metadata['render'] )
				)
			);
			if ( $template_path ) {
				/**
				 * Renders the block on the server.
				 *
				 * @since 6.1.0
				 *
				 * @param array    $attributes Block attributes.
				 * @param string   $content    Block default content.
				 * @param WP_Block $block      Block instance.
				 *
				 * @return string Returns the block content.
				 */
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Required by filter signature.
				$settings['render_callback'] = static function ( $attributes, $content, $block ) use ( $template_path ) {
					ob_start();
					require $template_path;
					return ob_get_clean();
				};
			}
		}

		// Divi Note: We pass in $metadata directly because we already have the metadata from the file.
		// Merge in metadata while preserving translated mapped settings (e.g. `title`).
		$settings = array_merge( $metadata, $settings );

		$script_fields = [
			'editorScript' => 'editor_script_handles',
			'script'       => 'script_handles',
			'viewScript'   => 'view_script_handles',
		];
		foreach ( $script_fields as $metadata_field_name => $settings_field_name ) {
			if ( ! empty( $settings[ $metadata_field_name ] ) ) {
				$metadata[ $metadata_field_name ] = $settings[ $metadata_field_name ];
			}
			if ( ! empty( $metadata[ $metadata_field_name ] ) ) {
				$scripts           = $metadata[ $metadata_field_name ];
				$processed_scripts = [];
				if ( is_array( $scripts ) ) {
					// phpcs:ignore Generic.CodeAnalysis.ForLoopWithTestFunctionCall.NotAllowed, Squiz.PHP.DisallowSizeFunctionsInLoops.Found -- This is from core.
					for ( $index = 0; $index < count( $scripts ); $index++ ) {
						$result = register_block_script_handle(
							$metadata,
							$metadata_field_name,
							$index
						);
						if ( $result ) {
							$processed_scripts[] = $result;
						}
					}
				} else {
					$result = register_block_script_handle(
						$metadata,
						$metadata_field_name
					);
					if ( $result ) {
						$processed_scripts[] = $result;
					}
				}
				$settings[ $settings_field_name ] = $processed_scripts;
			}
		}

		$module_fields = [
			'viewScriptModule' => 'view_script_module_ids',
		];
		foreach ( $module_fields as $metadata_field_name => $settings_field_name ) {
			if ( ! empty( $settings[ $metadata_field_name ] ) ) {
				$metadata[ $metadata_field_name ] = $settings[ $metadata_field_name ];
			}
			if ( ! empty( $metadata[ $metadata_field_name ] ) ) {
				$modules           = $metadata[ $metadata_field_name ];
				$processed_modules = [];
				if ( is_array( $modules ) ) {
					// phpcs:ignore Generic.CodeAnalysis.ForLoopWithTestFunctionCall.NotAllowed, Squiz.PHP.DisallowSizeFunctionsInLoops.Found -- This is from core.
					for ( $index = 0; $index < count( $modules ); $index++ ) {
						$result = register_block_script_module_id(
							$metadata,
							$metadata_field_name,
							$index
						);
						if ( $result ) {
							$processed_modules[] = $result;
						}
					}
				} else {
					$result = register_block_script_module_id(
						$metadata,
						$metadata_field_name
					);
					if ( $result ) {
						$processed_modules[] = $result;
					}
				}
				$settings[ $settings_field_name ] = $processed_modules;
			}
		}

		$style_fields = [
			'editorStyle' => 'editor_style_handles',
			'style'       => 'style_handles',
			'viewStyle'   => 'view_style_handles',
		];
		foreach ( $style_fields as $metadata_field_name => $settings_field_name ) {
			if ( ! empty( $settings[ $metadata_field_name ] ) ) {
				$metadata[ $metadata_field_name ] = $settings[ $metadata_field_name ];
			}
			if ( ! empty( $metadata[ $metadata_field_name ] ) ) {
				$styles           = $metadata[ $metadata_field_name ];
				$processed_styles = [];
				if ( is_array( $styles ) ) {
					// phpcs:ignore Generic.CodeAnalysis.ForLoopWithTestFunctionCall.NotAllowed, Squiz.PHP.DisallowSizeFunctionsInLoops.Found -- This is from core.
					for ( $index = 0; $index < count( $styles ); $index++ ) {
						$result = register_block_style_handle(
							$metadata,
							$metadata_field_name,
							$index
						);
						if ( $result ) {
							$processed_styles[] = $result;
						}
					}
				} else {
					$result = register_block_style_handle(
						$metadata,
						$metadata_field_name
					);
					if ( $result ) {
						$processed_styles[] = $result;
					}
				}
				$settings[ $settings_field_name ] = $processed_styles;
			}
		}

		if ( ! empty( $metadata['blockHooks'] ) ) {
			/**
			 * Map camelCased position string (from block.json) to snake_cased block type position.
			 *
			 * @var array
			 */
			$position_mappings = [
				'before'     => 'before',
				'after'      => 'after',
				'firstChild' => 'first_child',
				'lastChild'  => 'last_child',
			];

			$settings['block_hooks'] = [];
			foreach ( $metadata['blockHooks'] as $anchor_block_name => $position ) {
				// Avoid infinite recursion (hooking to itself).
				if ( $metadata['name'] === $anchor_block_name ) {
					_doing_it_wrong(
						__METHOD__,
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Not escaping because it's a code block, and this is from core.
						__( 'Cannot hook block to itself.' ),
						'6.4.0'
					);
					continue;
				}

				if ( ! isset( $position_mappings[ $position ] ) ) {
					continue;
				}

				$settings['block_hooks'][ $anchor_block_name ] = $position_mappings[ $position ];
			}
		}

		/**
		 * Filters the settings determined from the block type metadata.
		 *
		 * @since 5.7.0
		 *
		 * @param array $settings Array of determined settings for registering a block type.
		 * @param array $metadata Metadata provided for registering a block type.
		 */
		$settings = apply_filters( 'block_type_metadata_settings', $settings, $metadata );

		$metadata['name'] = ! empty( $settings['name'] ) ? $settings['name'] : $metadata['name'];

		return WP_Block_Type_Registry::get_instance()->register(
			$metadata['name'],
			$settings
		);
	}

	/**
	 * Retrieve the default attributes of a registered block module.
	 *
	 * This function retrieves the default attributes of a registered block module based on the provided module name.
	 * It checks if the default attributes are already cached to optimize performance and returns the cached attributes if available.
	 * It check if default attributes definition file exists in the module folder. If it exists, it retrieves the default attributes from the file.
	 * If the default attributes are not cached, it retrieves the registered module using the `WP_Block_Type_Registry` class.
	 * If the registered module is found, it retrieves the attributes of the module and extracts the default values into an array.
	 *
	 * @since ??
	 *
	 * @param string     $module_name The name of the module.
	 * @param string     $default_property_name Optional. The name of the default property to use. It can be either `'default'` or `'defaultPrintedStyle'`. Default `'default'`.
	 * @param array|null $metadata Optional. The metadata of the module. Default `null`.
	 *
	 * @return array An array of default attributes for the module.
	 */
	public static function get_default_attrs( string $module_name, string $default_property_name = 'default', $metadata = null ): array {
		return self::generate_default_attrs( $module_name, $default_property_name, $metadata );
	}

	/**
	 * Get fully resolved parent module attributes for use in a child module's render callback.
	 *
	 * `BlockParserStore::get_parent()` returns raw saved block attributes only — preset render
	 * attributes are never written to the block store. This helper centralises the 3-layer merge
	 * (defaults → presetRenderAttrs → rawAttrs) so that child module callbacks can retrieve a
	 * parent's preset-resolved state without duplicating the merge logic or mutating the block.
	 *
	 * @since ??
	 *
	 * @param WP_Block_Parser_Block $parent_block The parent block object returned by BlockParserStore::get_parent().
	 *
	 * @return array Fully resolved parent attributes:
	 *              defaults → preset render attrs → raw saved attrs (raw attrs win).
	 */
	public static function get_resolved_parent_attrs( $parent_block ): array {
		$raw_attrs     = $parent_block->attrs ?? [];
		$default_attrs = self::get_default_attrs( $parent_block->blockName ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Matches WordPress block parser conventions.

		$preset_render_attrs = GlobalPreset::get_merged_preset_render_attrs(
			[
				'moduleName'  => $parent_block->blockName, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Matches WordPress block parser conventions.
				'moduleAttrs' => $raw_attrs,
			]
		);

		return array_replace_recursive( $default_attrs, $preset_render_attrs, $raw_attrs );
	}

	/**
	 * Get the default attributes for a module.
	 *
	 * This function returns the default attributes for the module with the provided module name and default property name.
	 * The attributes are  defined and retrieved from the module's `module.json` file.
	 *
	 * @since ??
	 *
	 * @param string     $module_name           The name of the module to retrieve the default attributes for.
	 * @param string     $default_property_name Optional. The name of the default property to use. It can be either `'default'` or `'defaultPrintedStyle'`. Default `'default'`.
	 * @param array|null $metadata              Optional. The metadata of the module. Default `null`.
	 *
	 * @return array The default attributes for the module.
	 *
	 * @example:
	 * ```php
	 * // Retrieve the default attributes for a module called 'my_module'.
	 * $default_attrs = ModuleRegistration::generate_default_attrs( 'my_module' );
	 *
	 * // Retrieve the default attributes for a module called 'another_module' using a custom default property called 'custom'.
	 * $default_attrs = ModuleRegistration::generate_default_attrs( 'another_module', 'custom' );
	 * ```
	 */
	public static function generate_default_attrs( string $module_name, string $default_property_name = 'default', $metadata = null ): array {
		static $cached = [];

		$cache_key = $module_name . '--' . $default_property_name;

		if ( isset( $cached[ $cache_key ] ) ) {
			return $cached[ $cache_key ];
		}

		$default_attributes = [];

		if ( null === $metadata ) {
			// Core module metadata.
			$metadata = self::get_core_module_metadata( $module_name );

			// Non core module metadata. Try to get metadata from custom module folder.
			if ( empty( $metadata ) ) {
				$custom_folder = self::get_custom_metadata_folder( $module_name );

				if ( $custom_folder ) {
					$metadata_file = $custom_folder . '/module.json';

					if ( file_exists( $metadata_file ) ) {
						$metadata = wp_json_file_decode( $metadata_file, [ 'associative' => true ] );
					}
				}
			}
		}

		if ( empty( $metadata ) ) {
			return [];
		}

		$default_filter_name = 'defaultPrintedStyle' === $default_property_name ? 'default_printed_style' : 'default';

		if ( 'defaultPrintedStyle' === $default_property_name ) {
			$default_data = self::get_module_default_printed_style_attributes( $module_name );
		} else {
			$default_data = self::_get_module_default_render_attributes( $module_name );
		}

		if ( null !== $default_data ) {
			/**
			 * Filters the module default or default printed style attributes.
			 *
			 * This filter allows developers to modify the module default or default printed style attributes for Divi
			 * modules during registering.
			 *
			 * To make sure the implementation is aligned with `registerModule` store function in VB, we need to filter the
			 * default or default printed style attributes before we merge them with the metadata attributes.
			 *
			 * @since ??
			 *
			 * @param array $default_data The module default or default printed style attributes.
			 * @param array $metadata The module metadata.
			 *
			 * @return array The modified module default or default printed style attributes.
			 */
			$default_data = apply_filters( "divi_module_library_module_{$default_filter_name}_attributes", $default_data, $metadata );

			/**
			 * Filters the module default or default printed style attributes for specific module name.
			 *
			 * This filter allows developers to modify module default or default printed style attributes for specific Divi
			 * module during registering.
			 *
			 * To make sure the implementation is aligned with `registerModule` store function in VB, we need to filter the
			 * default or default printed style attributes before we merge them with the metadata attributes.
			 *
			 * @since ??
			 *
			 * @param array $default_data The module default or default printed style attributes.
			 * @param array $metadata The module metadata.
			 *
			 * @return array The modified module default or default printed style attributes.
			 */
			$default_data = apply_filters( "divi_module_library_module_{$default_filter_name}_attributes_{$module_name}", $default_data, $metadata );

			foreach ( $metadata['attributes'] ?? [] as $attr_name => $metadata_attribute ) {
				$default_attribute = array_replace_recursive(
					$metadata_attribute[ $default_property_name ] ?? [],
					$default_data[ $attr_name ] ?? []
				);

				if ( $default_attribute ) {
					$default_attributes[ $attr_name ] = $default_attribute;
				}
			}

			$cached[ $cache_key ] = $default_attributes;
		} else {
			foreach ( $metadata['attributes'] ?? [] as $attr_name => $metadata_attribute ) {
				$default_attribute = $metadata_attribute[ $default_property_name ] ?? null;

				if ( null !== $default_attribute ) {
					$default_attributes[ $attr_name ] = $default_attribute;
				}
			}

			$cached[ $cache_key ] = $default_attributes;
		}

		return $default_attributes;
	}

	/**
	 * Retrieve module selectors.
	 *
	 * Get the selectors associated with the attributes of a registered block that is defined in the module.json file.
	 *
	 * @since ??
	 *
	 * @param string $module_name The name of the module for which to retrieve the selectors.
	 *
	 * @return array An array of selectors where the key is the module attribute name and the value is the selector.
	 *
	 * @example:
	 * ```php
	 *     $selectors = ModuleRegistration::get_selectors( 'module_name' );
	 *     // Returns an array of selectors for the specified module.
	 *     // Example: ['attribute_name' => '.selector']
	 * ```
	 */
	public static function get_selectors( string $module_name ): array {
		static $cached = [];

		if ( isset( $cached[ $module_name ] ) ) {
			return $cached[ $module_name ];
		}

		$selectors         = [];
		$registered_module = WP_Block_Type_Registry::get_instance()->get_registered( $module_name );

		if ( $registered_module ) {
			$attrs = $registered_module->get_attributes();

			foreach ( $attrs as $key => $value ) {
				if ( ! isset( $value['selector'] ) ) {
					continue;
				}

				$selectors[ $key ] = $value['selector'];
			}
		}

		$cached[ $module_name ] = $selectors;

		return $selectors;
	}

	/**
	 * Check if a module is a child module.
	 *
	 * @since ??
	 *
	 * @param string $module_name The name of the module to check.
	 *
	 * @return bool True if the module is a child module, false otherwise.
	 */
	public static function is_child_module( $module_name ) {
		$registered_module = WP_Block_Type_Registry::get_instance()->get_registered( $module_name );

		$category = $registered_module->category ?? 'module';

		return 'child-module' === $category;
	}

	/**
	 * Check if a module is nestable.
	 *
	 * @since ??
	 *
	 * @param string $module_name The name of the module to check.
	 *
	 * @return bool True if the module is nestable, false otherwise.
	 */
	public static function is_nestable( $module_name ) {
		$registered_module = self::get_module_settings( $module_name );

		$nestable = $registered_module->nestable ?? false;

		return boolval( $nestable );
	}

	/**
	 * Retrieves the settings for a specified module.
	 *
	 * This function attempts to get the module settings from the registered block types.
	 * If the module is not registered, it falls back to retrieving metadata from a PHP file
	 * to improve performance for core modules.
	 *
	 * @since ??
	 *
	 * @param string $module_name The name of the module to retrieve settings for.
	 * @return WP_Block_Type|null The module settings if found, or null if the module is not registered.
	 */
	public static function get_module_settings( $module_name ): ?WP_Block_Type {
		/**
		 * Cache the registered modules to improve performance.
		 */
		static $registered_modules = [];

		if ( isset( $registered_modules[ $module_name ] ) ) {
			return $registered_modules[ $module_name ];
		}

		$module_settings = WP_Block_Type_Registry::get_instance()->get_registered( $module_name );

		if ( $module_settings ) {
			$registered_modules[ $module_name ] = $module_settings;

			return $module_settings;
		}

		$core_modules_metadata = self::get_all_core_modules_metadata();

		$found_metadata = ArrayUtility::find(
			$core_modules_metadata,
			function ( $module_metadata ) use ( $module_name ) {
				return $module_metadata['name'] === $module_name;
			}
		);

		if ( $found_metadata ) {
			$registered_modules[ $module_name ] = new WP_Block_Type( $module_name, $found_metadata );

			return $registered_modules[ $module_name ];
		}

		return null;
	}

	/**
	 * Retrieves all module metadata from the generated metadata file.
	 *
	 * This method implements lazy loading for the module metadata array. On first call,
	 * it loads the metadata from the automatically generated `_all_modules_metadata.php`
	 * file and caches it in the static property. Subsequent calls return the cached data.
	 *
	 * The metadata file contains comprehensive information about all available Divi 5
	 * modules including their names, titles, categories, icons, child modules, and
	 * other configuration data required for module registration and rendering.
	 *
	 * @since ??
	 *
	 * @return array Associative array containing metadata for all available modules.
	 *
	 * @example
	 * ```php
	 * $metadata = ModuleRegistration::get_all_core_modules_metadata();
	 * $accordion_data = $metadata['accordion'];
	 * echo $accordion_data['title']; // Outputs: "Accordion"
	 * ```
	 */
	public static function get_all_core_modules_metadata(): array {
		if ( ! self::$_all_core_modules_metadata ) {
			self::$_all_core_modules_metadata = require ET_BUILDER_5_DIR . 'server/_all_modules_metadata.php';
		}

		return self::$_all_core_modules_metadata;
	}

	/**
	 * Retrieves the core module metadata folder path for a specific module.
	 *
	 * The metadata folder path is constructed as:
	 * ET_BUILDER_5_DIR/visual-builder/packages/module-library/src/components/{metadata_relative_dir}
	 *
	 * @since ??
	 *
	 * @param string $module_name The name of the module to get the metadata folder for.
	 *
	 * @return string|null The metadata folder path if found, null otherwise.
	 */
	public static function get_core_metadata_folder( string $module_name ): ?string {
		$metadata_relative_dir = self::_get_core_module_relative_dir_from_module_name( $module_name );

		if ( ! $metadata_relative_dir ) {
			return null;
		}

		return ET_BUILDER_5_DIR . 'visual-builder/packages/module-library/src/components/' . $metadata_relative_dir;
	}

	/**
	 * Retrieves the metadata folder path for a custom module.
	 *
	 * This method looks up the folder path for a custom (third-party) module from the
	 * cached registry. Custom modules are registered during the module registration process
	 * and their folder paths are stored in the static property.
	 *
	 * @since ??
	 *
	 * @param string $module_name The name of the custom module to get the metadata folder for.
	 *
	 * @return string|null The metadata folder path if found, null otherwise.
	 */
	public static function get_custom_metadata_folder( string $module_name ): ?string {
		return self::$_all_custom_modules_folders[ $module_name ] ?? null;
	}

	/**
	 * Retrieves the metadata for a specific core module.
	 *
	 * This method implements lazy loading for individual module metadata. On first call,
	 * it loads the complete metadata from the automatically generated `_all_modules_metadata.php`
	 * file and caches it in the static property. Subsequent calls return the cached data.
	 * The method maps the module name to its relative directory path and returns the
	 * corresponding metadata array for that module.
	 *
	 * @since ??
	 *
	 * @param string $module_name The name of the module (e.g., 'divi/accordion', 'divi/button').
	 *
	 * @return array The metadata array for the specified module, or an empty array if the module
	 *               is not found or the module name cannot be mapped to a relative directory.
	 *
	 * @example
	 * ```php
	 * // Get metadata for accordion module.
	 * $metadata = ModuleRegistration::get_core_module_metadata( 'divi/accordion' );
	 * if ( ! empty( $metadata ) ) {
	 *     echo $metadata['title']; // Outputs: "Accordion"
	 *     echo $metadata['description']; // Outputs module description
	 * }
	 *
	 * // Get metadata for button module.
	 * $button_metadata = ModuleRegistration::get_core_module_metadata( 'divi/button' );
	 * ```
	 */
	public static function get_core_module_metadata( string $module_name ): array {
		$relative_dir = self::_get_core_module_relative_dir_from_module_name( $module_name );

		if ( ! $relative_dir ) {
			return [];
		}

		if ( ! self::$_all_core_modules_metadata ) {
			self::$_all_core_modules_metadata = require ET_BUILDER_5_DIR . 'server/_all_modules_metadata.php';
		}

		return self::$_all_core_modules_metadata[ $relative_dir ] ?? [];
	}

	/**
	 * Retrieves the module conversion outline for a specific Divi module.
	 *
	 * This method implements lazy loading and caching for module conversion outlines. On first call,
	 * it loads the conversion outline data from either the core modules conversion outline file
	 * or from an individual module conversion outline JSON file. Subsequent calls return the cached data.
	 *
	 * @since ??
	 *
	 * @param string      $module_name                  The name of the module to get conversion outline for.
	 * @param string|null $conversion_outline_json_file Optional path to individual module conversion outline JSON file.
	 *                                                  Used when the module is not found in core modules.
	 *
	 * @return array The module conversion outline array containing field mappings and transformation rules.
	 *               Returns empty array if no conversion outline is found.
	 */
	public static function get_module_conversion_outline( string $module_name, ?string $conversion_outline_json_file = null ): ?array {
		if ( isset( self::$_module_conversion_outline_cache[ $module_name ] ) ) {
			return self::$_module_conversion_outline_cache[ $module_name ];
		}

		$conversion_outline = [];

		$relative_dir = self::_get_core_module_relative_dir_from_module_name( $module_name );

		if ( $relative_dir ) {
			// Load core modules conversion outline if not already loaded.
			if ( ! self::$_all_core_modules_conversion_outline ) {
				self::$_all_core_modules_conversion_outline = require ET_BUILDER_5_DIR . 'server/_all_modules_conversion_outline.php';
			}

			// Get conversion outline for this specific module from the core modules array.
			$conversion_outline = self::$_all_core_modules_conversion_outline[ $relative_dir ] ?? [];
		} elseif ( $conversion_outline_json_file && file_exists( $conversion_outline_json_file ) ) {
			// Load conversion outline from individual conversion outline JSON file.
			$conversion_outline = wp_json_file_decode( $conversion_outline_json_file, [ 'associative' => true ] );
		}

		/**
		 * Filters the module conversion outline for a Divi module during conversion.
		 *
		 * This filter allows developers to modify the module conversion outline for a Divi module during conversion.
		 * The module conversion outline is used to define how the different properties and values
		 * for the module will be ported from D4 to D5.
		 *
		 * @since ??
		 *
		 * @param array $conversion_outline The default module conversion outline.
		 * @param string $module_name The name of the module.
		 */
		$conversion_outline = apply_filters( 'divi.moduleLibrary.conversion.moduleConversionOutline', $conversion_outline, $module_name );

		self::$_module_conversion_outline_cache[ $module_name ] = $conversion_outline;

		return $conversion_outline;
	}

	/**
	 * Retrieves the default printed style attributes for a core module.
	 *
	 * This method implements lazy loading for the module default printed style attributes data. On first call,
	 * it loads the attributes from the automatically generated `_all_modules_default_printed_style_attributes.php`
	 * file and caches it in the static property. Subsequent calls return the cached data.
	 *
	 * Default printed style attributes define the CSS styles that are automatically applied to modules
	 * when they are rendered on the frontend. These attributes ensure consistent styling across all
	 * instances of a module type and provide the foundation for user customization.
	 *
	 * @since ??
	 *
	 * @param string $module_name The name of the module to retrieve the default printed style attributes for.
	 *
	 * @return array|null The default printed style attributes array for the specified module, or null if not found.
	 *
	 * @example
	 * ```php
	 * $style_attrs = ModuleRegistration::get_module_default_printed_style_attributes( 'divi/button' );
	 * // Returns default CSS styles for button module
	 * ```
	 */
	public static function get_module_default_printed_style_attributes( string $module_name ): ?array {
		// Core module default printed style attributes.
		$relative_dir = self::_get_core_module_relative_dir_from_module_name( $module_name );

		if ( $relative_dir ) {
			if ( ! self::$_all_core_modules_default_printed_style_attributes ) {
				self::$_all_core_modules_default_printed_style_attributes = require ET_BUILDER_5_DIR . 'server/_all_modules_default_printed_style_attributes.php';
			}

			return self::$_all_core_modules_default_printed_style_attributes[ $relative_dir ] ?? null;
		}

		// Non core module default printed style attributes.
		$module_folder = self::get_custom_metadata_folder( $module_name );

		if ( $module_folder ) {
			$default_data_json_file = $module_folder . '/module-default-printed-style-attributes.json';

			if ( file_exists( $default_data_json_file ) ) {
				return wp_json_file_decode( $default_data_json_file, [ 'associative' => true ] );
			}
		}

		return null;
	}

	/**
	 * Retrieves the default render attributes for a core module.
	 *
	 * This method implements lazy loading for the module default render attributes data. On first call,
	 * it loads the attributes from the automatically generated `_all_modules_default_render_attributes.php`
	 * file and caches it in the static property. Subsequent calls return the cached data.
	 *
	 * Default render attributes define the initial values and configuration for module attributes
	 * when they are first rendered. These attributes serve as the baseline for module behavior
	 * and can be overridden by user-defined values or preset configurations.
	 *
	 * @since ??
	 *
	 * @param string $module_name The name of the module to retrieve the default render attributes for.
	 *
	 * @return array|null The default render attributes array for the specified module, or null if not found.
	 *
	 * @example
	 * ```php
	 * $render_attrs = ModuleRegistration::_get_module_default_render_attributes( 'divi/text' );
	 * // Returns default attribute values for text module rendering
	 * ```
	 */
	private static function _get_module_default_render_attributes( string $module_name ): ?array {
		// Core module default render attributes.
		$relative_dir = self::_get_core_module_relative_dir_from_module_name( $module_name );

		if ( $relative_dir ) {

			if ( ! self::$_all_core_modules_default_render_attributes ) {
				self::$_all_core_modules_default_render_attributes = require ET_BUILDER_5_DIR . 'server/_all_modules_default_render_attributes.php';
			}

			return self::$_all_core_modules_default_render_attributes[ $relative_dir ] ?? null;
		}

		// Non core module default render attributes.
		$module_folder = self::get_custom_metadata_folder( $module_name );

		if ( $module_folder ) {
			$default_data_json_file = $module_folder . '/module-default-render-attributes.json';

			if ( file_exists( $default_data_json_file ) ) {
				return wp_json_file_decode( $default_data_json_file, [ 'associative' => true ] );
			}
		}

		return null;
	}

	/**
	 * Maps a module name to its relative directory path.
	 *
	 * This private helper method creates a mapping between module names and their corresponding
	 * relative directory paths within the module library structure. It implements lazy loading
	 * by building the mapping cache on first call and reusing it for subsequent requests.
	 *
	 * The mapping is essential for locating module-specific files such as conversion outlines,
	 * default attributes, and other module metadata that are stored in organized directory structures.
	 *
	 * @since ??
	 *
	 * @param string $module_name The name of the module to map to its relative directory.
	 *
	 * @return string The relative directory path for the specified module, or null if not found.
	 */
	private static function _get_core_module_relative_dir_from_module_name( string $module_name ): ?string {
		if ( ! self::$_all_core_modules_mapping_module_name_to_relative_dir ) {
			$core_modules_metadata = self::get_all_core_modules_metadata();

			foreach ( $core_modules_metadata as $metadata_relative_dir => $metadata ) {
				self::$_all_core_modules_mapping_module_name_to_relative_dir[ $metadata['name'] ] = $metadata_relative_dir;
			}
		}

		return self::$_all_core_modules_mapping_module_name_to_relative_dir[ $module_name ] ?? null;
	}

	/**
	 * Determine if a module is disabled on all breakpoints.
	 *
	 * @since ??
	 *
	 * @param array $module_attrs Module attributes including decoration values.
	 *
	 * @return bool True when disabled on every breakpoint.
	 */
	private static function _is_disabled_on_all_breakpoints( array $module_attrs ): bool {
		$disabled_on = $module_attrs['module']['decoration']['disabledOn'] ?? null;

		if ( empty( $disabled_on ) || ! is_array( $disabled_on ) ) {
			return false;
		}

		// Check if all old breakpoints were enabled (desktopAbove, tabletOnly, and phone).
		// This indicates the module was disabled on all devices in the old system.
		$desktop_above_enabled = isset( $disabled_on['desktopAbove'] ) && self::_is_disabled_toggle_on( $disabled_on['desktopAbove'] );
		$tablet_only_enabled   = isset( $disabled_on['tabletOnly'] ) && self::_is_disabled_toggle_on( $disabled_on['tabletOnly'] );
		$phone_enabled         = isset( $disabled_on['phone'] ) && self::_is_disabled_toggle_on( $disabled_on['phone'] );

		if ( $desktop_above_enabled && $tablet_only_enabled && $phone_enabled ) {
			return true;
		}

		// Get enabled breakpoints for disabled-on.
		$enabled_breakpoints = Breakpoint::get_enabled_breakpoints();

		if ( empty( $enabled_breakpoints ) ) {
			return false;
		}

		// Check if module is disabled on every enabled breakpoint.
		// If any breakpoint allows the module, return false.
		foreach ( $enabled_breakpoints as $breakpoint ) {
			$breakpoint_name = $breakpoint['name'] ?? '';
			if ( empty( $breakpoint_name ) ) {
				continue;
			}
			// If breakpoint is not in $disabled_on, treat it as 'off' (not disabled).
			$breakpoint_value = $disabled_on[ $breakpoint_name ] ?? null;
			if ( ! self::_is_disabled_toggle_on( $breakpoint_value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Determine if a disabled-on value is enabled for gating.
	 *
	 * @since ??
	 *
	 * @param mixed $value Disabled-on value for a breakpoint.
	 *
	 * @return bool True when value represents disabled state.
	 */
	private static function _is_disabled_toggle_on( $value ): bool {
		if ( is_array( $value ) ) {
			if ( array_key_exists( 'value', $value ) ) {
				return self::_is_disabled_toggle_on( $value['value'] );
			}

			if ( 1 === count( $value ) ) {
				return self::_is_disabled_toggle_on( array_values( $value )[0] );
			}
		}

		return true === $value || 'on' === $value;
	}

	/**
	 * Check whether the module is an interaction target.
	 *
	 * @since ??
	 *
	 * @param array $module_attrs Module attributes including decoration values.
	 *
	 * @return bool True when module is targeted by an interaction.
	 */
	private static function _is_interaction_target( array $module_attrs ): bool {
		$interaction_target = $module_attrs['module']['decoration']['interactionTarget'] ?? '';

		if ( is_array( $interaction_target ) && array_key_exists( 'value', $interaction_target ) ) {
			$interaction_target = $interaction_target['value'];
		}

		return ! empty( $interaction_target );
	}

	/**
	 * Determine if a module should be skipped during excerpt generation.
	 *
	 * @since ??
	 *
	 * @param string $module_name Module name.
	 *
	 * @return bool True when module should be excluded from automatic excerpt rendering.
	 */
	private static function _is_excerpt_excluded_module( string $module_name ): bool {
		/**
		 * Filter modules excluded from automatic excerpt rendering.
		 *
		 * @since ??
		 *
		 * @param array $excluded_modules List of module names excluded from excerpt rendering.
		 */
		$excluded_modules = apply_filters(
			'et_builder_excerpt_excluded_modules',
			[
				'divi/bar-counter',
				'divi/bar-counters',
				'divi/canvas-portal',
				'divi/circle-counter',
				'divi/contact-field',
				'divi/contact-form',
				'divi/contact-form-field',
				'divi/counter',
				'divi/counters',
				'divi/divider',
				'divi/fullwidth-map',
				'divi/global-layout',
				'divi/icon',
				'divi/lottie',
				'divi/map',
				'divi/number-counter',
				'divi/pricing-table',
				'divi/pricing-tables',
				'divi/signup-custom-field',
				'divi/video',
				'divi/video-slider',
				'divi/video-slider-item',
				'divi/woocommerce-breadcrumb',
			]
		);

		if ( ! is_array( $excluded_modules ) ) {
			return false;
		}

		return in_array( $module_name, $excluded_modules, true );
	}
}
