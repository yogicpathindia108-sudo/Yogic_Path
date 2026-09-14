<?php
/**
 * Module: Module class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\FrontEnd\Assets\StaticCSS;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\GlobalData\GlobalPreset;
use ET\Builder\Packages\GlobalData\GlobalPresetItem;
use ET\Builder\Packages\GlobalData\GlobalPresetItemGroup;
use ET\Builder\Packages\Module\Layout\Components\Classnames;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\NoResultsRenderer\NoResultsRenderer;
use ET\Builder\Packages\Module\Options\Attributes\AttributeUtils;
use ET\Builder\Packages\Module\Layout\Components\Wrapper\ModuleWrapper;
use ET\Builder\Packages\Module\Options\IdClasses\IdClassesClassnames;
use ET\Builder\Packages\Module\Options\Meta\MetaClassnames;
use ET\Builder\Packages\Module\Options\Loop\LoopUtils;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use WP_Block_Type_Registry;

/**
 * Module class.
 *
 * @since ??
 */
class Module {

	/**
	 * Module renderer.
	 *
	 * This function is used to render a module in FE.
	 *
	 * This function is equivalent of JS function:
	 * {@link /api/js/divi-module/functions/Module Module}
	 * in `@divi/module` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array    $attrs                     Optional. Module attributes data. Default `[]`.
	 *     @type array    $htmlAttrs                 Optional. Custom HTML attributes. Default `null`.
	 *     @type string   $id                        Optional. Module ID. Default empty string.
	 *                                               In Visual Builder, the ID of module is a UUIDV4 string.
	 *                                               In FrontEnd, it is module name + order index.
	 *     @type string   $children                  Optional. The children element(s). Default empty string.
	 *     @type string   $childrenIds               Optional. Module inner blocks. Default `[]`.
	 *     @type bool     $hasModule                 Optional. Whether the module has module or not. Default `true`.
	 *     @type string   $moduleCategory            Optional. Module category. Default empty string.
	 *     @type string   $classname                 Optional. Custom CSS class attribute. Default empty string.
	 *     @type bool     $isFirst                   Optional. Is first child flag. Default `false`.
	 *     @type bool     $isLast                    Optional. Is last child flag. Default `false`.
	 *     @type bool     $hasModuleClassName        Optional. Has module class name. Default `true`.
	 *     @type callable $classnamesFunction        Optional. Function that will be invoked to generate module CSS class. Default `null`.
	 *     @type array    $styles                    Optional. Custom inline style attribute. Default `[]`.
	 *     @type string   $tag                       Optional. HTML tag. Default `div`.
	 *     @type bool     $hasModuleWrapper          Optional. Has module wrapper flag. Default `false`.
	 *     @type string   $wrapperTag                Optional. Wrapper HTML tag. Default `div`.
	 *     @type array    $wrapperHtmlAttrs          Optional. Wrapper custom html attributes. Default `[]`.
	 *     @type string   $wrapperClassname          Optional. Wrapper custom CSS class. Default empty string.
	 *     @type callable $wrapperClassnamesFunction Optional. Function that will be invoked to generate module wrapper CSS class. Default `null`.
	 *     @type callable $stylesComponent           Optional. Function that will be invoked to generate module styles. Default `null`.
	 *     @type array    $parentAttrs               Optional. Parent module attributes data. Default `[]`.
	 *     @type string   $parentId                  Optional. Parent Module ID. Default empty string.
	 *                                               In Visual Builder, the ID of module is a UUIDV4 string.
	 *                                               In FrontEnd, it is parent module name + parent order index.
	 *     @type string   $parentName                Optional. Parent module name. Default empty string.
	 *     @type string|null $stickyParentId            Optional. Sticky parent ID. Default `null`.
	 *     @type bool     $isInsideStickyModule      Optional. Whether the module is inside a sticky module. Default `false`.
	 *     @type string|null $stickyParentOrderClass    Optional. Sticky parent order class. Default `null`.
	 *     @type array    $siblingAttrs              Optional. Module sibling attributes data. Default `[]`.
	 *     @type array    $settings                  Optional. Custom settings. Default `[]`.
	 *     @type int      $orderIndex                Optional. Module order index. Default `0`.
	 *     @type int      $storeInstance             Optional. The ID of instance where this block stored in BlockParserStore class. Default `null`.
	 * }
	 *
	 * @return string The module HTML.
	 *
	 * @example:
	 * ```php
	 *  ET_Builder_Module::render( array(
	 *    'arg1' => 'value1',
	 *    'arg2' => 'value2',
	 *  ) );
	 * ```
	 *
	 * @example:
	 * ```php
	 *  $module = new ET_Builder_Module();
	 *  $module->render( array(
	 *    'arg1' => 'value1',
	 *    'arg2' => 'value2',
	 *   ) );
	 * ```
	 */
	public static function render( array $args ): string {
		$name          = $args['name'];
		$module_config = WP_Block_Type_Registry::get_instance()->get_registered( $name );

		// Store original hasModuleWrapper value before defaults are applied.
		$was_wrapper_explicitly_set = array_key_exists( 'hasModuleWrapper', $args );

		$args = array_replace_recursive(
			[
				'attrs'                     => [],
				'elements'                  => null,
				'htmlAttrs'                 => [],
				'htmlAttributesFunction'    => null,
				'id'                        => '',
				'children'                  => '',
				'childrenIds'               => [],
				'defaultPrintedStyleAttrs'  => [],
				'hasModule'                 => true,
				'moduleCategory'            => '',
				'className'                 => '',
				'isFirst'                   => false,
				'isLast'                    => false,
				'hasModuleClassName'        => true,
				'classnamesFunction'        => null,
				'styles'                    => [],
				'tag'                       => $module_config->wrapper['tag'] ?? 'div',
				'hasModuleWrapper'          => $module_config->wrapper['status'] ?? false,
				'wrapperTag'                => $module_config->wrapper['tag'] ?? 'div',
				'wrapperHtmlAttrs'          => [],
				'wrapperClassname'          => '',
				'wrapperClassnamesFunction' => null,
				'wrapperChildren'           => '',
				'stylesComponent'           => null,
				'scriptDataComponent'       => null,
				'parentAttrs'               => [],
				'parentId'                  => '',
				'parentName'                => '',
				'siblingAttrs'              => [],
				'settings'                  => [],

				// FE only.
				'orderIndex'                => null,
				'storeInstance'             => null,
			],
			$args
		);

		$attrs                       = $args['attrs'];
		$elements                    = $args['elements'];
		$html_attrs                  = $args['htmlAttrs'];
		$html_attributes_function    = $args['htmlAttributesFunction'];
		$id                          = $args['id'];
		$children                    = $args['children'];
		$children_ids                = $args['childrenIds'];
		$default_printed_style_attrs = $args['defaultPrintedStyleAttrs'];
		$has_module                  = $args['hasModule'];
		$module_category             = $args['moduleCategory'];
		$class_name                  = $args['className'];
		$is_first                    = $args['isFirst'];
		$is_last                     = $args['isLast'];
		$has_module_class_name       = $args['hasModuleClassName'];
		$classnames_function         = $args['classnamesFunction'];
		$styles                      = $args['styles'];
		$tag                         = $args['tag'];
		$has_module_wrapper          = $args['hasModuleWrapper'];
		$wrapper_tag                 = $args['wrapperTag'];

		// Check if wrapper should be enabled when module has children.
		// Only apply this logic if hasModuleWrapper was not explicitly set in original args
		// and the module has enableWhenChildren configured.
		if ( ! $was_wrapper_explicitly_set ) {
			$enable_when_children = $module_config->wrapper['enableWhenChildren'] ?? false;

			if ( $enable_when_children ) {
				// Only check children count when enableWhenChildren is true to avoid unnecessary processing.
				$has_children = ! empty( $children_ids ) && is_array( $children_ids ) && count( $children_ids ) > 0;

				// If enableWhenChildren is true, wrapper is only enabled when there are children.
				$has_module_wrapper = $has_children;

				// Ensure wrapperTag is set when using enableWhenChildren.
				if ( $has_children && ( empty( $wrapper_tag ) || 'div' === $wrapper_tag ) ) {
					$wrapper_tag = $module_config->wrapper['tag'] ?? 'div';
				}
			}
		}
		$wrapper_html_attrs          = $args['wrapperHtmlAttrs'];
		$wrapper_classname           = $args['wrapperClassname'];
		$wrapper_classnames_function = $args['wrapperClassnamesFunction'];
		$wrapper_children            = $args['wrapperChildren'];
		$styles_component            = $args['stylesComponent'];
		$script_data_component       = $args['scriptDataComponent'];
		$parent_attrs                = $args['parentAttrs'];
		$parent_id                   = $args['parentId'];
		$parent_name                 = $args['parentName'];
		$sticky_parent_id            = $args['stickyParentId'] ?? null;
		$sibling_attrs               = $args['siblingAttrs'];
		$settings                    = $args['settings'];
		$order_index                 = $args['orderIndex'];
		$store_instance              = $args['storeInstance'];

		$settings = array_merge(
			[
				// phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
				'disabledModuleVisibility' => 'hidden', // TODO feat(D5, Frontend Rendering): Set this value dynamically taken from from the builder settings.
			],
			$settings
		);

		// Base classnames params.
		// Both module and wrapper classnames filters need this. Module and wrapper classnames
		// action hooks need this + `classnamesInstance` property.
		$base_classnames_params = [
			'attrs'         => $attrs,
			'childrenIds'   => $children_ids,
			'hasModule'     => $has_module,
			'id'            => $id,
			'isFirst'       => $is_first,
			'isLast'        => $is_last,
			'name'          => $name,
			'parentAttrs'   => $parent_attrs,
			'parentId'      => $parent_id,
			'parentName'    => $parent_name,

			// FE only.
			'storeInstance' => $store_instance,
			'orderIndex'    => $order_index,
			'layoutType'    => BlockParserStore::get_layout_type(),
		];

		/*
		 * In Visual Builder (VB), 'hasModule' correctly indicates whether a Row contains any modules.
		 * However, on the Front-end (FE), 'hasModule' may return TRUE even if the Row only contains empty columns (i.e., no actual modules),
		 * due to differences in how the block structure is parsed. This can lead to incorrect classnames being applied in FE.
		 * To address this, we pass the 'hasModuleInColumns' property, which is computed specifically for FE to reflect whether any columns
		 * actually contain modules. This ensures that module_classnames implementations can apply the correct logic and classnames
		 * (such as 'et-vb-row--no-module') consistently between VB and FE.
		 */
		// Add this after $base_classnames_params is defined.
		if ( isset( $args['hasModuleInColumns'] ) ) {
			$base_classnames_params['hasModuleInColumns'] = $args['hasModuleInColumns'];
		}

		// Module wrapper classnames.
		$wrapper_classnames_instance = new Classnames();
		$wrapper_classnames_params   = array_merge(
			$base_classnames_params,
			[ 'classnamesInstance' => $wrapper_classnames_instance ]
		);

		$wrapper_classnames_instance->add( $wrapper_classname, ! empty( $wrapper_classname ) );

		if ( is_callable( $wrapper_classnames_function ) ) {
			call_user_func( $wrapper_classnames_function, $wrapper_classnames_params );
		}

		// Module classnames.
		$classnames_instance = new Classnames();
		$classnames_params   = array_merge(
			$base_classnames_params,
			[ 'classnamesInstance' => $classnames_instance ]
		);

		$module_class_by_name = ModuleUtils::get_module_class_by_name( $name );

		$module_class_name = ModuleUtils::get_module_class_name( $name );

		if ( ! $module_class_name ) {
			$module_class_name = $module_class_by_name;
		}

		$selector_classname = ModuleUtils::get_module_order_class_name( $id, $store_instance );

		if ( ! $selector_classname ) {
			$selector_classname = $module_class_by_name . '_' . $order_index;
		}

		$classnames_instance->add( $selector_classname );
		$classnames_instance->add( $module_class_by_name, empty( $module_class_name ) );
		$classnames_instance->add( $module_class_name, ! empty( $module_class_name ) );

		if ( is_callable( $classnames_function ) ) {
			call_user_func( $classnames_function, $classnames_params );
		}

		$classnames_instance->add( $class_name, ! empty( $class_name ) );

		MetaClassnames::add_toc_list_heading_classnames( $classnames_instance, $attrs );

		$excluded_categories = [
			'structure',
		];

		$classnames_instance->add(
			'et_pb_module',
			! in_array( $module_category, $excluded_categories, true ) && $has_module_class_name
		);

		// Add flex, grid, and block module classes based on layout.
		// Only check for layout if the module actually has the layout option group configured.
		$module_settings         = ModuleRegistration::get_module_settings( $name );
		$has_layout_option_group = $module_settings && isset( $module_settings->attributes['module']['settings']['decoration']['layout'] );
		$layout_value            = $attrs['module']['decoration']['layout']['desktop']['value']['display'] ?? 'flex';
		$is_module_flex_layout   = $has_layout_option_group && 'flex' === $layout_value;
		$is_module_grid_layout   = $has_layout_option_group && 'grid' === $layout_value;

		$classnames_instance->add(
			'et_flex_module',
			$is_module_flex_layout && ! in_array( $module_category, $excluded_categories, true ) && $has_module_class_name
		);

		$classnames_instance->add(
			'et_grid_module',
			$is_module_grid_layout && ! in_array( $module_category, $excluded_categories, true ) && $has_module_class_name
		);

		// Add et_block_module class when using block layout (not flex or grid).
		$is_module_block_layout = $has_layout_option_group && ! $is_module_flex_layout && ! $is_module_grid_layout && ! in_array( $module_category, $excluded_categories, true ) && $has_module_class_name;
		$classnames_instance->add( 'et_block_module', $is_module_block_layout );

		// Add flex column width classes if parent has flex layout and module has flexType.
		// Blog, Portfolio, Filterable Portfolio, and Gallery modules use *Grid.decoration.layout instead of module.decoration.layout.
		// Layout mode is always determined by desktop breakpoint.
		if ( $has_module_class_name && $parent_attrs ) {
			// Check parent layout at desktop breakpoint to determine layout mode.
			// Detect the module type from parent_attrs structure.
			// Gallery, Blog, and Portfolio modules default to 'grid' layout when display is not set.
			if ( isset( $parent_attrs['blogGrid'] ) ) {
				$parent_layout_display = $parent_attrs['blogGrid']['decoration']['layout']['desktop']['value']['display'] ?? 'grid';
			} elseif ( isset( $parent_attrs['portfolioGrid'] ) ) {
				$parent_layout_display = $parent_attrs['portfolioGrid']['decoration']['layout']['desktop']['value']['display'] ?? 'grid';
			} elseif ( isset( $parent_attrs['galleryGrid'] ) ) {
				$parent_layout_display = $parent_attrs['galleryGrid']['decoration']['layout']['desktop']['value']['display'] ?? 'grid';
			} else {
				$parent_layout_display = $parent_attrs['module']['decoration']['layout']['desktop']['value']['display'] ?? 'flex';
			}

			$is_parent_flex_layout = 'flex' === $parent_layout_display;

			// Only add flex column classes if parent is in flex layout.
			if ( $is_parent_flex_layout ) {
				$breakpoints_mapping = Breakpoint::get_css_class_suffixes();

				// Get default render attributes to use as fallback for flexType.
				$default_render_attrs = ModuleRegistration::generate_default_attrs( $name, 'default' );

				foreach ( $breakpoints_mapping as $breakpoint => $suffix ) {
					if ( ! Breakpoint::is_enabled_for_style( $breakpoint ) ) {
						continue;
					}

					// Get flexType using ModuleUtils::use_attr_value to properly handle defaults and inheritance.
					$sizing_attr         = $attrs['module']['decoration']['sizing'] ?? [];
					$default_sizing_attr = $default_render_attrs['module']['decoration']['sizing'] ?? [];
					// Merge defaults with attrs (defaults first, then attrs override).
					$merged_sizing_attr = array_replace_recursive( $default_sizing_attr, $sizing_attr );
					$flex_type_value    = ModuleUtils::use_attr_value(
						[
							'attr'       => $merged_sizing_attr,
							'breakpoint' => $breakpoint,
							'state'      => 'value',
							'mode'       => 'getOrInheritAll',
						]
					);
					$flex_type          = $flex_type_value['flexType'] ?? null;

					if ( $flex_type && 'none' !== $flex_type ) {
						// If module has a wrapper, apply flex column classes to wrapper instead of module.
						// The wrapper needs to be the direct descendant of the parent flex container.
						if ( $has_module_wrapper ) {
							$wrapper_classnames_instance->add( "et_flex_column_{$flex_type}{$suffix}" );
						} else {
							$classnames_instance->add( "et_flex_column_{$flex_type}{$suffix}" );
						}
					}
				}
			}
		}

		// Module styles output.
		$parent_order_class = $parent_id ? '.' . ModuleUtils::get_module_order_class_name( $parent_id, $store_instance ) : '';

		if ( $parent_id && ! $parent_order_class ) {
			$parent_order_class = '.' . ModuleUtils::get_module_class_by_name( $parent_id );
		}

		// Whether $elements is an instance of ModuleElements.
		$is_module_elements_instance = $elements instanceof ModuleElements;

		if ( $is_module_elements_instance && null !== $order_index ) {
			$elements->set_order_id( (string) $order_index );
		}

		// CSS ID & Classes presets.
		$attrs_with_presets     = $attrs;
		$selected_group_presets = GlobalPreset::get_selected_group_presets(
			[
				'moduleAttrs' => $attrs,
				'moduleName'  => $name,
			]
		);

		foreach ( $selected_group_presets as $group_id => $group_preset_item ) {
			// Only process CSS ID & Classes presets.
			if ( str_contains( $group_id, 'htmlAttributes' )
				&& $group_preset_item->is_exist()
				&& $group_preset_item->has_data_attrs() ) {

				$preset_render_attrs = $group_preset_item->get_data_render_attrs();

				// Only merge CSS ID & Class data specifically.
				$html_attrs = $preset_render_attrs['module']['advanced']['htmlAttributes'] ?? [];
				foreach ( $html_attrs as $device => $device_data ) {
					$value_data = $device_data['value'] ?? [];

					// Merge only 'id' and 'class' fields.
					if ( isset( $value_data['id'] ) ) {
						$attrs_with_presets['module']['advanced']['htmlAttributes'][ $device ]['value']['id'] = $value_data['id'];
					}
					if ( isset( $value_data['class'] ) ) {
						$attrs_with_presets['module']['advanced']['htmlAttributes'][ $device ]['value']['class'] = $value_data['class'];
					}
				}
			}
		}

		// Element Type doesn't support responsive breakpoints, so we only check desktop.value.
		// Check elementType from attrs (presets are handled separately for htmlAttributes only).
		$html_attr          = $attrs['module']['advanced']['html'] ?? [];
		$element_type_value = ModuleUtils::get_attr_subname_value(
			[
				'attr'         => $html_attr,
				'breakpoint'   => 'desktop',
				'state'        => 'value',
				'mode'         => 'get',
				'subname'      => 'elementType',
				'defaultValue' => null,
			]
		);
		// Normalize element type (data is already sanitized on save, so we trust it).
		$normalized_element_type = null;
		if ( is_string( $element_type_value ) && ! empty( $element_type_value ) ) {
			$normalized_element_type = strtolower( trim( $element_type_value ) );
		}
		$module_tag = $normalized_element_type ?? ( $tag ?? ( $module_config->wrapper['tag'] ?? 'div' ) );

		// Fetch module htmlAttributes.
		if ( is_callable( $html_attributes_function ) ) {
			$id_class_values = call_user_func(
				$html_attributes_function,
				[
					'id'    => $id,
					'name'  => $name,
					'attrs' => $attrs_with_presets,
				]
			);
		} else {
			$id_class_values = IdClassesClassnames::get_html_attributes(
				$attrs_with_presets['module']['advanced']['htmlAttributes'] ?? []
			);
		}

		$html_id         = $id_class_values['id'] ?? '';
		$html_classnames = $id_class_values['classNames'] ?? '';

		// Module CSS Id.
		if ( ! empty( $html_id ) ) {
			$html_attrs['id'] = $html_id;
		}

		// Add interaction data attributes if present.
		$interaction_trigger = $attrs['module']['decoration']['interactionTrigger'] ?? '';
		$interaction_target  = $attrs['module']['decoration']['interactionTarget'] ?? '';

		// If interactionTrigger is not set but interactions exist (e.g., from option group presets),
		// extract it from the interactions array to enable addAllTriggerClasses to find this module.
		if ( empty( $interaction_trigger ) ) {
			$interactions_data = $attrs['module']['decoration']['interactions'] ?? null;
			if ( $interactions_data ) {
				$interactions = $interactions_data['desktop']['value']['interactions'] ?? [];
				if ( is_array( $interactions ) && ! empty( $interactions ) ) {
					// Extract trigger ID from first interaction's triggerClass.
					// We use the first interaction's triggerClass to identify this module as a trigger.
					$first_interaction = $interactions[0] ?? null;
					if ( $first_interaction && ! empty( $first_interaction['triggerClass'] ) ) {
						$trigger_class = $first_interaction['triggerClass'];
						// Extract ID from trigger class (e.g., 'et-interaction-trigger-4u03zdntp9' → '4u03zdntp9').
						if ( preg_match( '/et-interaction-trigger-([a-zA-Z0-9]+)/', $trigger_class, $matches ) ) {
							$interaction_trigger = $matches[1];
						}
					}
				}
			}
		}

		// Set data-interaction-trigger on trigger modules (modules that have interactions).
		if ( ! empty( $interaction_trigger ) ) {
			$html_attrs['data-interaction-trigger'] = $interaction_trigger;
		}

		// Set data-interaction-target on target modules (modules that are targets of interactions).
		// Note: This should only be set if this module is actually a target (has interactionTarget attribute),
		// not if this module is a trigger with interactions targeting other modules.
		if ( ! empty( $interaction_target ) ) {
			$html_attrs['data-interaction-target'] = $interaction_target;
		}

		// Add custom attributes if present.
		$custom_attributes_data = $attrs['module']['decoration']['attributes'] ?? [];
		if ( ! empty( $custom_attributes_data ) ) {
			// Separate attributes by target element.
			$separated_attributes = AttributeUtils::separate_attributes_by_target_element( $custom_attributes_data );

			// Only apply main module attributes to the main container.
			$main_module_attributes = $separated_attributes['main'] ?? [];

			foreach ( $main_module_attributes as $attribute_name => $attribute_value ) {
				// Check for attribute collisions and merge appropriately.
				if ( isset( $html_attrs[ $attribute_name ] ) ) {
					// Attribute already exists, merge values based on attribute type.
					$existing_value                = $html_attrs[ $attribute_name ];
					$merged_value                  = AttributeUtils::merge_attribute_values( $attribute_name, $existing_value, $attribute_value );
					$html_attrs[ $attribute_name ] = $merged_value;
				} else {
					// No collision, add the attribute normally.
					$html_attrs[ $attribute_name ] = $attribute_value;
				}
			}
		}

		// Add loop item index data attribute if in loop context.
		$loop_iteration = $attrs['__loop_iteration'] ?? null;

		if ( null !== $loop_iteration && is_int( $loop_iteration ) && $loop_iteration >= 0 ) {
			$html_attrs['data-loop-item'] = $loop_iteration;
		}

		$loop_source_id = $attrs['__loop_source_id'] ?? null;

		if ( is_string( $loop_source_id ) && '' !== $loop_source_id ) {
			$html_attrs['data-loop-source'] = $loop_source_id;
		}

		// Module CSS Class.
		$classnames_instance->add(
			$html_classnames,
			! empty( $html_classnames )
		);

		// Add custom class attribute to classnames instance if present.
		if ( ! empty( $html_attrs['class'] ) ) {
			$classnames_instance->add( $html_attrs['class'] );
			// Remove from html_attrs to avoid duplication.
			unset( $html_attrs['class'] );
		}

		// Condition where current page builder's style has been enqueued as static css.
		$is_style_enqueued_as_static_css = StaticCSS::$styles_manager->enqueued ?? false;

		if ( is_callable( $styles_component ) ) {
			// Selector prefix determination for Theme Builder and Custom Post Type contexts.
			// The prefix '.et-db #et-boc .et-l ' increases CSS specificity for these layouts.
			$is_inner_content    = BlockParserStore::is_rendering_inner_content();
			$should_use_prefix   = false;
			$is_custom_post_type = false;

			// For inner content in REST API (e.g., blog posts rendered via Blog module in VB),
			// check if the Theme Builder flag was set by the calling module (Blog module sets this).
			// This is the most reliable way since VB knows its own context.
			if ( $is_inner_content && Conditions::is_rest_api_request() ) {
				$is_theme_builder_context = Style::get_is_theme_builder_context_for_inner_content();
				if ( $is_theme_builder_context ) {
					$should_use_prefix   = true;
					$is_custom_post_type = true;
				}
			} else {
				// For non-REST contexts (direct page loads), use standard detection methods.
				$is_custom_post_type = Conditions::is_custom_post_type() || Conditions::is_tb_enabled();
				$should_use_prefix   = $is_custom_post_type;
			}

			$selector_prefix = $should_use_prefix ? '.et-db #et-boc .et-l ' : '';

			// Extract sticky parent and layout information for preset rendering.
			$is_inside_sticky_module   = false;
			$sticky_parent_order_class = '';
			$is_parent_flex_layout     = false;
			if ( $elements instanceof ModuleElements ) {
				$is_inside_sticky_module   = $elements->get_is_inside_sticky_module();
				$sticky_parent_order_class = $elements->get_sticky_parent_order_class();
				$is_parent_flex_layout     = $elements->get_is_parent_flex_layout();
			}

			// If stickyParentId is provided but isInsideStickyModule is false, re-derive from ID.
			if ( ! $is_inside_sticky_module && $sticky_parent_id ) {
				$is_inside_sticky_module = true;
				if ( empty( $sticky_parent_order_class ) ) {
					$sticky_parent_order_class = ModuleUtils::get_module_order_class_name( $sticky_parent_id, $store_instance );
				}
			}

			// Render nested group preset styles FIRST (before module presets).
			// This ensures nested group preset CSS is printed before module preset CSS,
			// allowing module preset CSS to override nested group preset CSS.
			// Nested group presets use 'preset' style group (same as module presets) so they sort together.
			self::render_styles_preset_group(
				[
					'parentId'                   => $parent_id,
					'parentName'                 => $parent_name,
					'parentAttrs'                => $parent_attrs,
					'defaultPrintedStyleAttrs'   => $default_printed_style_attrs,
					'name'                       => $name,
					'elements'                   => $elements,
					'classnamesInstance'         => $classnames_instance,
					'wrapperClassnamesInstance'  => $wrapper_classnames_instance,
					'id'                         => $id,
					'storeInstance'              => $store_instance,
					'selectorPrefix'             => $selector_prefix,
					'hasModuleWrapper'           => $has_module_wrapper,
					'isStyleEnqueuedAsStaticCss' => $is_style_enqueued_as_static_css,
					'stylesComponent'            => $styles_component,
					'settings'                   => $settings,
					'orderIndex'                 => $order_index,
					'attrs'                      => $attrs,
					'siblingAttrs'               => $sibling_attrs,
					'renderNestedOnly'           => true,
					'stickyParentId'             => $sticky_parent_id,
					'isInsideStickyModule'       => $is_inside_sticky_module,
					'stickyParentOrderClass'     => $sticky_parent_order_class,
					'isParentFlexLayout'         => $is_parent_flex_layout,
				]
			);

			// Render Preset Styles (module presets).
			self::render_styles_preset_module(
				[
					'name'                       => $name,
					'attrs'                      => $attrs,
					'defaultPrintedStyleAttrs'   => $default_printed_style_attrs,
					'parentId'                   => $parent_id,
					'parentName'                 => $parent_name,
					'id'                         => $id,
					'storeInstance'              => $store_instance,
					'elements'                   => $elements,
					'classnamesInstance'         => $classnames_instance,
					'wrapperClassnamesInstance'  => $wrapper_classnames_instance,
					'selectorPrefix'             => $selector_prefix,
					'hasModuleWrapper'           => $has_module_wrapper,
					'isStyleEnqueuedAsStaticCss' => $is_style_enqueued_as_static_css,
					'stylesComponent'            => $styles_component,
					'settings'                   => $settings,
					'orderIndex'                 => $order_index,
					'siblingAttrs'               => $sibling_attrs,
					'stickyParentId'             => $sticky_parent_id,
					'isInsideStickyModule'       => $is_inside_sticky_module,
					'stickyParentOrderClass'     => $sticky_parent_order_class,
					'isParentFlexLayout'         => $is_parent_flex_layout,
				]
			);

			// Render explicit group preset styles (after module presets).
			// Explicit group presets use 'presetGroup' style group so they render after module presets.
			self::render_styles_preset_group(
				[
					'parentId'                   => $parent_id,
					'parentName'                 => $parent_name,
					'parentAttrs'                => $parent_attrs,
					'defaultPrintedStyleAttrs'   => $default_printed_style_attrs,
					'name'                       => $name,
					'elements'                   => $elements,
					'classnamesInstance'         => $classnames_instance,
					'wrapperClassnamesInstance'  => $wrapper_classnames_instance,
					'id'                         => $id,
					'storeInstance'              => $store_instance,
					'selectorPrefix'             => $selector_prefix,
					'hasModuleWrapper'           => $has_module_wrapper,
					'isStyleEnqueuedAsStaticCss' => $is_style_enqueued_as_static_css,
					'stylesComponent'            => $styles_component,
					'settings'                   => $settings,
					'orderIndex'                 => $order_index,
					'attrs'                      => $attrs,
					'siblingAttrs'               => $sibling_attrs,
					'stickyParentId'             => $sticky_parent_id,
					'isInsideStickyModule'       => $is_inside_sticky_module,
					'stickyParentOrderClass'     => $sticky_parent_order_class,
					'isParentFlexLayout'         => $is_parent_flex_layout,
				]
			);

			// Render Module Styles.
			Style::set_group_style( 'module' );

			if ( $is_module_elements_instance ) {
				$elements->set_style_group( 'module' );
			}

			// Process Module Style output only when module selector is available.
			if ( $selector_classname ) {
				// Order class names.
				$base_order_class = '.' . $selector_classname;
				$order_class      = $selector_prefix . $base_order_class;

				// Wrapper order class names.
				$base_wrapper_order_class = $has_module_wrapper ? '.' . $selector_classname . '_wrapper' : '';
				$wrapper_order_class      = $has_module_wrapper ? $selector_prefix . $base_wrapper_order_class : '';

				if ( $is_module_elements_instance ) {
					$elements->set_base_order_class( $base_order_class );
					$elements->set_order_class( $order_class );
					$elements->set_base_wrapper_order_class( $base_wrapper_order_class );
					$elements->set_wrapper_order_class( $wrapper_order_class );
					$elements->set_module_name_class( $module_class_name );
				}

				if ( ! $is_style_enqueued_as_static_css ) {
					// Set styles for module.
					call_user_func(
						$styles_component,
						[
							'id'                       => $id,
							'isCustomPostType'         => $is_custom_post_type,
							'elements'                 => $elements,
							'name'                     => $name,
							'attrs'                    => $attrs,
							'parentAttrs'              => $parent_attrs,
							'siblingAttrs'             => $sibling_attrs,
							'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
							'isInsideStickyModule'     => $is_inside_sticky_module,
							'stickyParentOrderClass'   => $sticky_parent_order_class,
							'isParentFlexLayout'       => $is_parent_flex_layout,
							'baseOrderClass'           => $base_order_class,
							'orderClass'               => $order_class,
							'parentOrderClass'         => $parent_order_class,
							'baseWrapperOrderClass'    => $base_wrapper_order_class,
							'wrapperOrderClass'        => $wrapper_order_class,
							'selectorPrefix'           => $selector_prefix,
							'settings'                 => $settings,

							// Style's state is only affecting module's style component when module's settings modal is opened (edited).
							'state'                    => 'value',
							'mode'                     => 'frontend',

							// FE only.
							'storeInstance'            => $store_instance,
							'orderIndex'               => $order_index,
							'styleGroup'               => 'module',
						]
					);
				}
			}
		}

		// Registering module's script data.
		if ( is_callable( $script_data_component ) ) {
			call_user_func(
				$script_data_component,
				[
					'name'          => $name,
					'attrs'         => $attrs,
					'parentAttrs'   => $parent_attrs,
					'id'            => $id,
					'selector'      => '.' . $selector_classname,
					'elements'      => $elements,

					// FE only.
					'storeInstance' => $store_instance,
					'orderIndex'    => $order_index,
				]
			);
		}

		$module_classnames_value = $classnames_instance->value();

		/**
		 * Filter the module classnames.
		 *
		 * @since ??
		 *
		 * @param string $module_classnames_value The module classnames value.
		 * @param array  $base_classnames_params  The base classnames params.
		 */
		$module_classname = apply_filters(
			'divi_module_classnames_value',
			$module_classnames_value,
			$base_classnames_params
		);

		$wrapper_classnames_value = $wrapper_classnames_instance->value();

		/**
		 * Filter the module wrapper classnames.
		 *
		 * @since ??
		 *
		 * @param string $wrapper_classnames_value The wrapper classnames value.
		 * @param array  $base_classnames_params   The base classnames params.
		 */
		$module_wrapper_classname = apply_filters(
			'divi_module_wrapper_classnames_value',
			$wrapper_classnames_value,
			$base_classnames_params
		);

		// Enqueue inline font assets.
		if ( ! empty( $attrs['content']['decoration']['inlineFont'] ) ) {
			ModuleUtils::load_module_inline_font( $attrs );
		}

		// Check if this is a loop no-results case.
		// We'll handle it at the end after all processing is complete.
		$is_no_results = isset( $attrs['__loop_no_results'] ) && true === $attrs['__loop_no_results'];

		// Handle no-results case: replace children with no-results message after all processing is complete.
		// This ensures all attributes, classnames, and styles are processed normally, matching Visual Builder behavior.
		if ( $is_no_results ) {
			// Generate module styles for no-results case.
			$module_styles = '';
			if ( is_callable( $styles_component ) && $elements instanceof ModuleElements ) {
				// Set up minimal context for style generation.
				if ( null !== $order_index ) {
					$elements->set_order_id( (string) $order_index );
				}

				// Generate styles using the module's style component.
				$module_styles = $elements->style_components(
					[
						'attrName' => 'module',
					]
				);
			}

			// Get the standardized no-results message content.
			$no_results_content = LoopUtils::render_no_results_found_message();

			// Replace children with styles + no-results message.
			// Styles are included in children (not inline) to match NoResultsRenderer behavior.
			$children = $module_styles . $no_results_content;
			// Clear inline styles since they're now in children.
			$styles = '';
		}

		$module_wrapper = ModuleWrapper::render(
			[
				'children'         => $children,
				'classname'        => $module_classname,
				'name'             => $name,
				'styles'           => $styles,
				'htmlAttrs'        => $html_attrs,
				'parentAttrs'      => $parent_attrs,
				'siblingAttrs'     => $sibling_attrs,
				'tag'              => $module_tag,
				'hasModuleWrapper' => $has_module_wrapper,
				'wrapperTag'       => $wrapper_tag,
				'wrapperHtmlAttrs' => $wrapper_html_attrs,
				'wrapperClassname' => $module_wrapper_classname,
				'wrapperChildren'  => $wrapper_children,
			]
		);

		$module_wrapper_filter_args = array_merge(
			$args,
			[
				'htmlAttrs'              => $html_attrs,
				'moduleClassname'        => $module_classname,
				'moduleWrapperClassname' => $module_wrapper_classname,
			]
		);

		/**
		 * Filter the module wrapper rendered output.
		 *
		 * @since ??
		 *
		 * @param string $module_wrapper             The rendered module wrapper.
		 * @param array  $module_wrapper_filter_args The module wrapper filter args.
		 */
		$module_wrapper_output = apply_filters( 'divi_module_wrapper_render', $module_wrapper, $module_wrapper_filter_args );

		// Get HTML Before and After values (desktop only, not responsive).
		// Note: These values are already sanitized on save via HtmlSecurity::sanitize_html_fields().
		// No need to sanitize again on output since visitors don't have user roles.
		$html_attr         = $attrs_with_presets['module']['advanced']['html'] ?? [];
		$html_before_value = ModuleUtils::get_attr_subname_value(
			[
				'attr'         => $html_attr,
				'breakpoint'   => 'desktop',
				'state'        => 'value',
				'mode'         => 'get',
				'subname'      => 'htmlBefore',
				'defaultValue' => '',
			]
		);
		$html_after_value  = ModuleUtils::get_attr_subname_value(
			[
				'attr'         => $html_attr,
				'breakpoint'   => 'desktop',
				'state'        => 'value',
				'mode'         => 'get',
				'subname'      => 'htmlAfter',
				'defaultValue' => '',
			]
		);

		// Combine HTML Before, module wrapper, and HTML After.
		return $html_before_value . $module_wrapper_output . $html_after_value;
	}

	/**
	 * Renders the styles preset for a module.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Array of arguments.
	 *
	 *     @type string         $name                            The name of the module.
	 *     @type array          $attrs                           The attributes of the module.
	 *     @type array          $defaultPrintedStyleAttrs        The default printed style attributes.
	 *     @type string         $parentId                        The ID of the parent module.
	 *     @type string         $parentName                      The name of the parent module.
	 *     @type string         $id                              The ID of the module.
	 *     @type int            $storeInstance                   The store instance.
	 *     @type ModuleElements $elements                        The elements of the module.
	 *     @type Classnames     $classnamesInstance              The classnames instance.
	 *     @type Classnames     $wrapperClassnamesInstance       The wrapper classnames instance.
	 *     @type string         $selectorPrefix                  The selector prefix.
	 *     @type bool           $hasModuleWrapper                Whether the module has a wrapper.
	 *     @type bool           $isStyleEnqueuedAsStaticCss      Whether the style is enqueued as static CSS.
	 *     @type callable       $stylesComponent                 The styles component.
	 *     @type array          $settings                        The settings of the module.
	 *     @type int            $orderIndex                      The order index of the module.
	 *     @type string|null    $stickyParentId                  Optional. Sticky parent ID. Default `null`.
	 *     @type bool           $isInsideStickyModule            Optional. Whether the module is inside a sticky module. Default `false`.
	 *     @type string|null    $stickyParentOrderClass          Optional. Sticky parent order class. Default `null`.
	 * }
	 *
	 * @return void
	 */
	public static function render_styles_preset_module( array $args ): void {
		// Extract args.
		$name                            = $args['name'];
		$attrs                           = $args['attrs'];
		$default_printed_style_attrs     = $args['defaultPrintedStyleAttrs'];
		$parent_id                       = $args['parentId'];
		$parent_name                     = $args['parentName'];
		$id                              = $args['id'];
		$store_instance                  = $args['storeInstance'];
		$elements                        = $args['elements'];
		$classnames_instance             = $args['classnamesInstance'];
		$wrapper_classnames_instance     = $args['wrapperClassnamesInstance'];
		$selector_prefix                 = $args['selectorPrefix'];
		$has_module_wrapper              = $args['hasModuleWrapper'];
		$is_style_enqueued_as_static_css = $args['isStyleEnqueuedAsStaticCss'];
		$styles_component                = $args['stylesComponent'];
		$settings                        = $args['settings'];
		$order_index                     = $args['orderIndex'];
		$sibling_attrs                   = $args['siblingAttrs'];
		$sticky_parent_id                = $args['stickyParentId'] ?? null;
		$is_inside_sticky_module         = $args['isInsideStickyModule'] ?? false;
		$sticky_parent_order_class       = $args['stickyParentOrderClass'] ?? '';

		// Extract sticky parent information from ModuleElements.
		if ( $elements instanceof ModuleElements ) {
			// Set store instance if not already set (needed for sticky parent class conversion).
			if ( ! $elements->store_instance && $store_instance ) {
				$elements->store_instance = $store_instance;
				// Recalculate sticky parent order class now that we have store instance.
				$elements->recalculate_sticky_parent_order_class();
			}
			$is_inside_sticky_module   = $elements->get_is_inside_sticky_module();
			$sticky_parent_order_class = $elements->get_sticky_parent_order_class();
		}

		// If stickyParentId is provided but isInsideStickyModule is false, re-derive from ID.
		if ( ! $is_inside_sticky_module && $sticky_parent_id ) {
			$is_inside_sticky_module = true;
			if ( empty( $sticky_parent_order_class ) ) {
				$sticky_parent_order_class = ModuleUtils::get_module_order_class_name( $sticky_parent_id, $store_instance );
			}
		}

		$preset_item = GlobalPreset::get_selected_preset(
			[
				'moduleName'  => $name,
				'moduleAttrs' => $attrs,
			]
		);

		// Get all stacked preset IDs for CSS generation.
		$preset_value       = $attrs['modulePreset'] ?? '';
		$stacked_preset_ids = GlobalPreset::normalize_preset_stack( $preset_value );

		// Ensure default preset styles are generated for detected module types in inner content.
		// Default presets are only generated when modules have NO assigned preset, matching VB behavior.
		// This ensures default preset styles are available for modules without explicit presets.
		$is_inner_content = BlockParserStore::is_rendering_inner_content();
		if ( $is_inner_content ) {
			$detected_module_types = Style::get_detected_module_types_for_inner_content();
			if ( in_array( $name, $detected_module_types, true ) ) {
				// Only generate default preset if module has NO assigned preset.
				// This matches VB behavior where default presets are only generated when no preset is assigned.
				// If a module has an assigned preset, only that preset should be generated (not both).
				if ( empty( $stacked_preset_ids ) ) {
					// Get default preset item (with empty modulePreset to force default).
					$default_preset_item = GlobalPreset::get_selected_preset(
						[
							'moduleName'  => $name,
							'moduleAttrs' => [], // Empty attrs to get default preset.
						]
					);

					// Get the actual default preset selector from the preset item.
					if ( $default_preset_item instanceof GlobalPresetItem ) {
						$default_preset_selector = $default_preset_item->get_selector_class_name();

						// Check if default preset styles have already been generated.
						if ( ! Style::is_preset_selector_processed( $default_preset_selector ) ) {
							// Generate default preset styles if preset item has data.
							if ( $default_preset_item->has_data_attrs() ) {
								self::render_styles_preset(
									[
										'name'             => $name,
										'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
										'elements'         => $elements,
										'classnamesInstance' => $classnames_instance,
										'wrapperClassnamesInstance' => $wrapper_classnames_instance,
										'id'               => $id,
										'storeInstance'    => $store_instance,
										'selectorPrefix'   => $selector_prefix,
										'hasModuleWrapper' => $has_module_wrapper,
										'isStyleEnqueuedAsStaticCss' => $is_style_enqueued_as_static_css,
										'isSelectorProcessed' => false,
										'stylesComponent'  => $styles_component,
										'settings'         => $settings,
										'orderIndex'       => $order_index,
										'styleGroup'       => 'preset',
										'presetItem'       => $default_preset_item,
										'parentPresetItem' => null,
										'siblingPreviousPresetItem' => null,
										'siblingNextPresetItem' => null,
										'stickyParentId'   => $sticky_parent_id,
										'isInsideStickyModule' => $is_inside_sticky_module,
										'stickyParentOrderClass' => $sticky_parent_order_class,
										'isParentFlexLayout' => false,
									]
								);
							}
						}
					}
				}
			}
		}

		// Get all stacked preset class names and add them to the module.
		$stacked_preset_class_names = GlobalPreset::get_module_preset_class_names(
			[
				'moduleName'  => $name,
				'moduleAttrs' => $attrs,
			]
		);

		// Add all stacked preset class names to module.
		if ( $classnames_instance instanceof Classnames && ! empty( $stacked_preset_class_names ) ) {
			foreach ( $stacked_preset_class_names as $stacked_class_name ) {
				$classnames_instance->add( $stacked_class_name );
			}
		}

		// Add all stacked preset class names (wrapper version) to module wrapper.
		if ( $wrapper_classnames_instance instanceof Classnames && ! empty( $stacked_preset_class_names ) ) {
			foreach ( $stacked_preset_class_names as $stacked_class_name ) {
				$wrapper_classnames_instance->add( "{$stacked_class_name}_wrapper" );
			}
		}

		$parent_preset_item = $parent_id ? GlobalPreset::get_selected_preset(
			[
				'moduleName'  => $parent_name,
				'moduleAttrs' => $parent_attrs ?? [],
			]
		) : null;

		$sibling_previous_preset_item = null;

		if ( ! empty( $sibling_attrs['previous'] ) ) {
			$sibling_previous = BlockParserStore::get_sibling( $id, 'before', $store_instance );

			if ( $sibling_previous ) {
				$sibling_previous_preset_item = GlobalPreset::get_selected_preset(
					[
						'moduleName'  => $sibling_previous->blockName,
						'moduleAttrs' => $sibling_previous->attrs ?? [],
					]
				);
			}
		}

		$sibling_next_preset_item = null;

		if ( ! empty( $sibling_attrs['next'] ) ) {
			$sibling_next = BlockParserStore::get_sibling( $id, 'after', $store_instance );

			if ( $sibling_next ) {
				$sibling_next_preset_item = GlobalPreset::get_selected_preset(
					[
						'moduleName'  => $sibling_next->blockName,
						'moduleAttrs' => $sibling_next->attrs ?? [],
					]
				);
			}
		}

		$is_parent_flex_layout = $elements->get_is_parent_flex_layout();

		// Render styles for all stacked presets.
		// If no stacked presets, render with the default preset logic.
		if ( ! empty( $stacked_preset_ids ) ) {
			// Loop through all stacked presets and render CSS for each.
			foreach ( $stacked_preset_ids as $index => $current_preset_id ) {
				// Create a temporary preset item for this specific preset ID.
				// Pass full module attributes to allow proper module name conversion
				// (e.g., divi/section -> divi/specialty-section for specialty sections).
				$current_preset_attrs                 = $attrs;
				$current_preset_attrs['modulePreset'] = [ $current_preset_id ];
				$current_preset_item                  = GlobalPreset::get_selected_preset(
					[
						'moduleName'  => $name,
						'moduleAttrs' => $current_preset_attrs,
					]
				);

				// Only render if this preset has data.
				if ( $current_preset_item instanceof GlobalPresetItem && $current_preset_item->has_data_attrs() ) {
					// Use preset selector class name for the processed check (without module ID suffix).
					// This ensures preset styles are only generated once per preset, not once per module instance.
					$preset_selector_key = $current_preset_item->get_selector_class_name();

					self::render_styles_preset(
						[
							'name'                       => $name,
							'defaultPrintedStyleAttrs'   => $default_printed_style_attrs,
							'elements'                   => $elements,
							'classnamesInstance'         => $classnames_instance,
							'wrapperClassnamesInstance'  => $wrapper_classnames_instance,
							'id'                         => $id,
							'storeInstance'              => $store_instance,
							'selectorPrefix'             => $selector_prefix,
							'hasModuleWrapper'           => $has_module_wrapper,
							'isStyleEnqueuedAsStaticCss' => $is_style_enqueued_as_static_css,
							'isSelectorProcessed'        => Style::is_preset_selector_processed( $preset_selector_key ),
							'stylesComponent'            => $styles_component,
							'settings'                   => $settings,
							'orderIndex'                 => $order_index,
							'styleGroup'                 => 'preset',
							'presetItem'                 => $current_preset_item,
							'parentPresetItem'           => $parent_preset_item,
							'siblingPreviousPresetItem'  => $sibling_previous_preset_item,
							'siblingNextPresetItem'      => $sibling_next_preset_item,
							'isParentFlexLayout'         => $is_parent_flex_layout,
							'isInsideStickyModule'       => $is_inside_sticky_module,
							'stickyParentOrderClass'     => $sticky_parent_order_class,
						]
					);
				}
			}
		} else {
			// No stacked presets - use the default single preset logic.
			$default_preset_selector = $preset_item->get_selector_class_name();

			self::render_styles_preset(
				[
					'name'                       => $name,
					'defaultPrintedStyleAttrs'   => $default_printed_style_attrs,
					'elements'                   => $elements,
					'classnamesInstance'         => $classnames_instance,
					'wrapperClassnamesInstance'  => $wrapper_classnames_instance,
					'id'                         => $id,
					'storeInstance'              => $store_instance,
					'selectorPrefix'             => $selector_prefix,
					'hasModuleWrapper'           => $has_module_wrapper,
					'isStyleEnqueuedAsStaticCss' => $is_style_enqueued_as_static_css,
					'isSelectorProcessed'        => Style::is_preset_selector_processed( $default_preset_selector ),
					'stylesComponent'            => $styles_component,
					'settings'                   => $settings,
					'orderIndex'                 => $order_index,
					'styleGroup'                 => 'preset',
					'presetItem'                 => $preset_item,
					'parentPresetItem'           => $parent_preset_item,
					'siblingPreviousPresetItem'  => $sibling_previous_preset_item,
					'siblingNextPresetItem'      => $sibling_next_preset_item,
					'isParentFlexLayout'         => $is_parent_flex_layout,
					'isInsideStickyModule'       => $is_inside_sticky_module,
					'stickyParentOrderClass'     => $sticky_parent_order_class,
				]
			);
		}
	}

	/**
	 * Renders styles for a preset group.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Array of arguments.
	 *
	 *     @type array          $attrs                           Attributes of the module.
	 *     @type string         $parentId                        ID of the parent module.
	 *     @type string         $parentName                      Name of the parent module.
	 *     @type array          $parentAttrs                     Attributes of the parent module.
	 *     @type array          $defaultPrintedStyleAttrs        Default printed style attributes.
	 *     @type string         $name                            Name of the module.
	 *     @type ModuleElements $elements                        Elements of the module.
	 *     @type Classnames     $classnamesInstance              Instance of classnames.
	 *     @type Classnames     $wrapperClassnamesInstance       Instance of wrapper classnames.
	 *     @type string         $id                              ID of the module.
	 *     @type int            $storeInstance                   Instance of the store.
	 *     @type string         $selectorPrefix                  Prefix for the selector.
	 *     @type bool           $hasModuleWrapper                Whether the module has a wrapper.
	 *     @type bool           $isStyleEnqueuedAsStaticCss      Whether the style is enqueued as static CSS.
	 *     @type callable       $stylesComponent                 Component for styles.
	 *     @type array          $settings                        Settings for the module.
	 *     @type array          $siblingAttrs                    Attributes of sibling modules.
	 *     @type string|null    $stickyParentId                  Optional. Sticky parent ID. Default `null`.
	 *     @type bool           $isInsideStickyModule            Optional. Whether the module is inside a sticky module. Default `false`.
	 *     @type string|null    $stickyParentOrderClass          Optional. Sticky parent order class. Default `null`.
	 *     @type int            $orderIndex                      Order index of the module.
	 * }
	 *
	 * @return void
	 */
	public static function render_styles_preset_group( array $args ): void {
		// Extract args.
		$attrs                           = $args['attrs'];
		$parent_id                       = $args['parentId'];
		$parent_name                     = $args['parentName'];
		$parent_attrs                    = $args['parentAttrs'];
		$default_printed_style_attrs     = $args['defaultPrintedStyleAttrs'];
		$name                            = $args['name'];
		$elements                        = $args['elements'];
		$classnames_instance             = $args['classnamesInstance'];
		$wrapper_classnames_instance     = $args['wrapperClassnamesInstance'];
		$id                              = $args['id'];
		$store_instance                  = $args['storeInstance'];
		$selector_prefix                 = $args['selectorPrefix'];
		$has_module_wrapper              = $args['hasModuleWrapper'];
		$is_style_enqueued_as_static_css = $args['isStyleEnqueuedAsStaticCss'];
		$styles_component                = $args['stylesComponent'];
		$settings                        = $args['settings'];
		$order_index                     = $args['orderIndex'];
		$sibling_attrs                   = $args['siblingAttrs'];
		$sticky_parent_id                = $args['stickyParentId'] ?? null;
		$is_inside_sticky_module         = $args['isInsideStickyModule'] ?? false;
		$sticky_parent_order_class       = $args['stickyParentOrderClass'] ?? '';

		// Extract sticky parent and layout information from ModuleElements.
		$is_parent_flex_layout = $args['isParentFlexLayout'] ?? false;

		if ( $elements instanceof ModuleElements ) {
			// Set store instance if not already set (needed for sticky parent class conversion).
			if ( ! $elements->store_instance && $store_instance ) {
				$elements->store_instance = $store_instance;
				// Recalculate sticky parent order class now that we have store instance.
				$elements->recalculate_sticky_parent_order_class();
			}
			$is_inside_sticky_module   = $elements->get_is_inside_sticky_module();
			$sticky_parent_order_class = $elements->get_sticky_parent_order_class();

			if ( ! $is_parent_flex_layout ) {
				$is_parent_flex_layout = $elements->get_is_parent_flex_layout();
			}
		}

		// If stickyParentId is provided but isInsideStickyModule is false, re-derive from ID.
		if ( ! $is_inside_sticky_module && $sticky_parent_id ) {
			$is_inside_sticky_module = true;
			if ( empty( $sticky_parent_order_class ) ) {
				$sticky_parent_order_class = ModuleUtils::get_module_order_class_name( $sticky_parent_id, $store_instance );
			}
		}

		// Check if module has an assigned preset.
		$preset_value       = $attrs['modulePreset'] ?? '';
		$stacked_preset_ids = GlobalPreset::normalize_preset_stack( $preset_value );
		$has_module_preset  = ! empty( $stacked_preset_ids );

		$selected_group_presets          = GlobalPreset::get_selected_group_presets(
			[
				'moduleAttrs' => $attrs,
				'moduleName'  => $name,
			]
		);
		$groups_with_non_default_presets = [];

		foreach ( $selected_group_presets as $selected_group_preset ) {
			if ( $selected_group_preset instanceof GlobalPresetItemGroup
				&& $selected_group_preset->is_exist()
				&& ! $selected_group_preset->as_default() ) {
				$groups_with_non_default_presets[ $selected_group_preset->get_group_id() ] = true;
			}
		}

		$parent_selected_group_presets = ( $parent_id && $parent_name ) ? GlobalPreset::get_selected_group_presets(
			[
				'moduleAttrs' => $parent_attrs ?? [],
				'moduleName'  => $parent_name,
			]
		) : [];

		$sibling_previous_selected_group_presets = [];

		if ( ! empty( $sibling_attrs['previous'] ) ) {
			$sibling_previous = BlockParserStore::get_sibling( $id, 'before', $store_instance );

			if ( $sibling_previous ) {
				$sibling_previous_selected_group_presets = GlobalPreset::get_selected_group_presets(
					[
						'moduleAttrs' => $sibling_previous->attrs ?? [],
						'moduleName'  => $sibling_previous->blockName,
					]
				);
			}
		}

		$sibling_next_selected_group_presets = [];

		if ( ! empty( $sibling_attrs['next'] ) ) {
			$sibling_next = BlockParserStore::get_sibling( $id, 'after', $store_instance );

			if ( $sibling_next ) {
				$sibling_next_selected_group_presets = GlobalPreset::get_selected_group_presets(
					[
						'moduleAttrs' => $sibling_next->attrs ?? [],
						'moduleName'  => $sibling_next->blockName,
					]
				);
			}
		}

		// Filter presets based on renderNestedOnly flag.
		$render_nested_only   = $args['renderNestedOnly'] ?? false;
		$render_explicit_only = $args['renderExplicitOnly'] ?? false;

		foreach ( $selected_group_presets as $array_key => $group_preset_item ) {
			if ( ! $group_preset_item->is_exist() ) {
				continue;
			}

			// Skip presets without attributes to avoid adding empty preset classes.
			// This check is necessary because preset classes are now added outside of render_styles_preset().
			// In the old version, classes were added inside render_styles_preset() which had a has_data_attrs() check.
			if ( ! $group_preset_item->has_data_attrs() ) {
				continue;
			}

			// Skip default non-nested group presets only when the same group has
			// an explicit/nested non-default override.
			if ( $has_module_preset && $group_preset_item instanceof GlobalPresetItemGroup ) {
				$is_default                      = $group_preset_item->as_default();
				$is_nested                       = $group_preset_item->is_nested();
				$group_id                        = $group_preset_item->get_group_id();
				$has_non_default_preset_in_group = ! empty( $groups_with_non_default_presets[ $group_id ] );

				if ( $is_default && ! $is_nested && $has_non_default_preset_in_group ) {
					continue;
				}
			}

			// Get the actual group ID from the preset item (not the array key, which may include preset ID for stacking).
			$group_id = $group_preset_item->get_group_id();

			// Add group preset class name to module (always add class names, regardless of render filter).
			$group_preset_class_name = $group_preset_item->get_selector_class_name();
			if ( $classnames_instance instanceof Classnames && ! empty( $group_preset_class_name ) ) {
				$classnames_instance->add( $group_preset_class_name );
			}

			// Add group preset class name (wrapper version) to module wrapper.
			if ( $wrapper_classnames_instance instanceof Classnames && ! empty( $group_preset_class_name ) ) {
				$wrapper_classnames_instance->add( "{$group_preset_class_name}_wrapper" );
			}

			// Filter by nested/explicit status if requested (only affects CSS rendering, not class names).
			if ( $render_nested_only && ( ! $group_preset_item instanceof GlobalPresetItemGroup || ! $group_preset_item->is_nested() ) ) {
				continue;
			}

			if ( $render_explicit_only && ( $group_preset_item instanceof GlobalPresetItemGroup && $group_preset_item->is_nested() ) ) {
				continue;
			}

			$parent_group_preset_item = $parent_selected_group_presets[ $array_key ] ?? null;

			// Populate sibling previous module preset for current group.
			$sibling_previous_group_preset_item = $sibling_previous_selected_group_presets[ $array_key ] ?? null;

			// Populate sibling nrxt module preset for current group.
			$sibling_next_group_preset_item = $sibling_next_selected_group_presets[ $array_key ] ?? null;

			// Use 'presetNested' style group for nested group presets so they render before module presets.
			// Use 'presetGroup' style group for explicit group presets so they render after module presets.
			$style_group = ( $group_preset_item instanceof GlobalPresetItemGroup && $group_preset_item->is_nested() )
				? 'presetNested'
				: 'presetGroup';

			$group_preset_selector_key = $group_preset_item->get_selector_class_name() . '--' . $name . '--' . $array_key;

			self::render_styles_preset(
				[
					'name'                       => $name,
					'defaultPrintedStyleAttrs'   => $default_printed_style_attrs,
					'elements'                   => $elements,
					'classnamesInstance'         => $classnames_instance,
					'wrapperClassnamesInstance'  => $wrapper_classnames_instance,
					'id'                         => $id,
					'storeInstance'              => $store_instance,
					'selectorPrefix'             => $selector_prefix,
					'hasModuleWrapper'           => $has_module_wrapper,
					'isStyleEnqueuedAsStaticCss' => $is_style_enqueued_as_static_css,
					'isSelectorProcessed'        => Style::is_preset_selector_processed( $group_preset_selector_key ),
					'stylesComponent'            => $styles_component,
					'settings'                   => $settings,
					'orderIndex'                 => $order_index,
					'styleGroup'                 => $style_group,
					'presetItem'                 => $group_preset_item,
					'parentPresetItem'           => $parent_group_preset_item,
					'siblingPreviousPresetItem'  => $sibling_previous_group_preset_item,
					'siblingNextPresetItem'      => $sibling_next_group_preset_item,
					'isParentFlexLayout'         => $is_parent_flex_layout,
					'isInsideStickyModule'       => $is_inside_sticky_module,
					'stickyParentOrderClass'     => $sticky_parent_order_class,
				]
			);
		}
	}

	/**
	 * Renders preset styles.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Array of arguments.
	 *
	 *     @type string           $styleGroup                  The style group. Either 'preset' or 'presetGroup'.
	 *     @type string           $name                        The name of the module.
	 *     @type array            $defaultPrintedStyleAttrs    Default printed style attributes.
	 *     @type ModuleElements   $elements                    Instance of ModuleElements.
	 *     @type Classnames       $classnamesInstance          Instance of Classnames for the module.
	 *     @type Classnames       $wrapperClassnamesInstance   Instance of Classnames for the module wrapper.
	 *     @type string           $id                          The ID of the module.
	 *     @type int              $storeInstance               Instance of the store.
	 *     @type string           $selectorPrefix              The selector prefix.
	 *     @type bool             $hasModuleWrapper            Whether the module has a wrapper.
	 *     @type bool             $isStyleEnqueuedAsStaticCss  Whether the style is enqueued as static CSS.
	 *     @type bool             $isSelectorProcessed         Whether the selector has been processed.
	 *     @type callable         $stylesComponent             The styles component callback.
	 *     @type array            $settings                    The settings array.
	 *     @type int              $orderIndex                  The order index.
	 *     @type GlobalPresetItem $presetItem                  Instance of GlobalPresetItem for the current preset.
	 *     @type GlobalPresetItem $parentPresetItem            Instance of GlobalPresetItem for the parent preset.
	 *     @type GlobalPresetItem $siblingPreviousPresetItem   Instance of GlobalPresetItem for the previous sibling preset.
	 *     @type GlobalPresetItem $siblingNextPresetItem       Instance of GlobalPresetItem for the next sibling preset.
	 *     @type string           $stickyParentId              Optional. Sticky parent ID. Default empty string.
	 *     @type bool             $isInsideStickyModule        Optional. Whether the module is inside a sticky module. Default `false`.
	 *     @type string           $stickyParentOrderClass      Optional. Sticky parent order class. Default empty string.
	 * }
	 *
	 * @return void
	 */
	public static function render_styles_preset( array $args ): void {
		// Extract args.
		$style_group                     = $args['styleGroup'];
		$name                            = $args['name'];
		$default_printed_style_attrs     = $args['defaultPrintedStyleAttrs'];
		$elements                        = $args['elements'];
		$classnames_instance             = $args['classnamesInstance'];
		$wrapper_classnames_instance     = $args['wrapperClassnamesInstance'];
		$id                              = $args['id'];
		$store_instance                  = $args['storeInstance'];
		$selector_prefix                 = $args['selectorPrefix'];
		$has_module_wrapper              = $args['hasModuleWrapper'];
		$is_style_enqueued_as_static_css = $args['isStyleEnqueuedAsStaticCss'];
		$is_selector_processed           = $args['isSelectorProcessed'];
		$styles_component                = $args['stylesComponent'];
		$settings                        = $args['settings'];
		$order_index                     = $args['orderIndex'];
		$preset_item                     = $args['presetItem'];
		$parent_preset_item              = $args['parentPresetItem'];
		$sibling_previous_preset_item    = $args['siblingPreviousPresetItem'];
		$sibling_next_preset_item        = $args['siblingNextPresetItem'];
		$sticky_parent_id                = $args['stickyParentId'] ?? null;
		$is_inside_sticky_module         = $args['isInsideStickyModule'] ?? false;
		$sticky_parent_order_class       = $args['stickyParentOrderClass'] ?? '';

		// Extract sticky parent and layout information from ModuleElements.
		$is_parent_flex_layout = $args['isParentFlexLayout'] ?? false;

		if ( $elements instanceof ModuleElements ) {
			$is_inside_sticky_module   = $elements->get_is_inside_sticky_module();
			$sticky_parent_order_class = $elements->get_sticky_parent_order_class();

			if ( ! $is_parent_flex_layout ) {
				$is_parent_flex_layout = $elements->get_is_parent_flex_layout();
			}
		}

		// If stickyParentId is provided but isInsideStickyModule is false, re-derive from ID.
		if ( ! $is_inside_sticky_module && $sticky_parent_id ) {
			$is_inside_sticky_module = true;
			if ( empty( $sticky_parent_order_class ) ) {
				$sticky_parent_order_class = ModuleUtils::get_module_order_class_name( $sticky_parent_id, $store_instance );
			}
		}

		// Only proceed if the preset item has data attributes.
		if ( $preset_item instanceof GlobalPresetItem && $preset_item->has_data_attrs() ) {
			Style::set_group_style( $style_group );

			// Get the priority from the preset item for CSS rendering order.
			// Higher priority presets will be rendered last for proper CSS cascade.
			// Note: We don't manipulate priority for nested presets - the order is controlled by
			// rendering nested group presets before module presets, matching the JS implementation.
			$preset_priority = $preset_item->get_data_priority();

			if ( $elements instanceof ModuleElements ) {
				$elements->set_style_group( $style_group );
				$elements->set_preset_priority( $preset_priority );
			}

			// Preset's selector class name (kept for backward compatibility with sibling/parent logic).
			$preset_item_selector_class_name = $preset_item->get_selector_class_name();
			$is_default_preset               = str_contains( $preset_item_selector_class_name, '--default' );

			// Note: Stacked preset class names are now added earlier in this function.
			// The single preset class name logic is removed to avoid duplication.

			// Populate parent module preset data.
			$parent_preset_item_attrs               = [];
			$parent_preset_item_selector_class_name = '';
			$parent_preset_item_order_class         = '';

			if ( $parent_preset_item instanceof GlobalPresetItem && $parent_preset_item->has_data_attrs() ) {
				$parent_preset_item_attrs               = $parent_preset_item->get_data_attrs();
				$parent_preset_item_selector_class_name = $parent_preset_item->get_selector_class_name();
				$parent_preset_item_order_class         = '.' . $parent_preset_item_selector_class_name;
			}

			// Populate sibling module preset data.
			$siblings_preset_item_attrs = [
				'previous' => [],
				'next'     => [],
			];

			if ( $sibling_previous_preset_item instanceof GlobalPresetItem && $sibling_previous_preset_item->has_data_attrs() ) {
				$sibling_previous_preset_attrs                        = $sibling_previous_preset_item->get_data_attrs();
				$siblings_preset_item_attrs['previous']['background'] = $sibling_previous_preset_attrs['module']['decoration']['background'] ?? null;
			}

			if ( $sibling_next_preset_item instanceof GlobalPresetItem && $sibling_next_preset_item->has_data_attrs() ) {
				$sibling_next_preset_attrs                        = $sibling_next_preset_item->get_data_attrs();
				$siblings_preset_item_attrs['next']['background'] = $sibling_next_preset_attrs['module']['decoration']['background'] ?? null;
			}

			// Preset's order class names.
			$preset_item_base_order_class = '.' . $preset_item_selector_class_name;
			$preset_item_order_class      = $selector_prefix . $preset_item_base_order_class;

			// Set styles for presets.
			if ( $elements instanceof ModuleElements ) {
				$elements->set_order_class( $preset_item_order_class );
				$elements->set_base_order_class( $preset_item_base_order_class );
			}

			// Preset wrapper order class names.
			$preset_item_base_wrapper_order_class = $has_module_wrapper ? $preset_item_base_order_class . '_wrapper' : '';
			$preset_item_wrapper_order_class      = $has_module_wrapper ? $selector_prefix . $preset_item_base_wrapper_order_class : '';

			if ( $elements instanceof ModuleElements ) {
				$elements->set_wrapper_order_class( $preset_item_wrapper_order_class );
			}

			// If the style has not been enqueued as static CSS and the preset style selector hasn't been.
			// processed, then we need to call the styles component.
			// EXCEPTION: Always render preset styles inline when inside a sticky parent, because sticky state
			// CSS is dynamic and cannot be pre-generated in static CSS files.
			$is_inside_sticky_module = $elements instanceof ModuleElements ? $elements->get_is_inside_sticky_module() : false;
			$should_render_inline    = ! $is_style_enqueued_as_static_css || $is_inside_sticky_module;
			if ( $should_render_inline && ! $is_selector_processed ) {
				$preset_item_attrs_raw = $preset_item->get_data_attrs();

				if ( ModuleUtils::module_preset_merges_style_attrs( $name ) ) {
					$preset_item_style_attrs = $preset_item->get_data_style_attrs();

					if ( ! empty( $preset_item_style_attrs ) ) {
						$preset_item_attrs_raw = array_replace_recursive( $preset_item_attrs_raw, $preset_item_style_attrs );
					}
				}

				$preset_item_attrs = ModuleUtils::remove_matching_values( $preset_item_attrs_raw, $default_printed_style_attrs );

				// Set preset attributes as the attributes data that are used by the ModuleElements instance during the styles rendering.
				if ( $elements instanceof ModuleElements ) {
					$elements->use_custom_module_attrs( $preset_item_attrs );
				}

				// Get the priority from the preset item for CSS rendering order.
				// Higher priority presets will be rendered last for proper CSS cascade.
				// Note: We don't manipulate priority for nested presets - the order is controlled by
				// rendering nested group presets before module presets, matching the JS implementation.
				$preset_priority = $preset_item->get_data_priority();

				// Calls the styles component.
				call_user_func(
					$styles_component,
					[
						'id'                       => $id,
						'elements'                 => $elements,
						'name'                     => $name,
						'attrs'                    => $preset_item_attrs,
						'parentAttrs'              => $parent_preset_item_attrs,
						'siblingAttrs'             => $siblings_preset_item_attrs,
						'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
						'baseOrderClass'           => $preset_item_base_order_class,
						'orderClass'               => $preset_item_order_class,
						'parentOrderClass'         => $parent_preset_item_order_class,
						'baseWrapperOrderClass'    => $preset_item_base_wrapper_order_class,
						'wrapperOrderClass'        => $preset_item_wrapper_order_class,
						'isParentFlexLayout'       => $is_parent_flex_layout,
						'settings'                 => $settings,

						// Preset's state is set to 'value'. This is to ensure that these styles specifically affect.
						// the style component when the module's settings modal is open (being edited).
						'state'                    => 'value',
						'mode'                     => 'frontend',
						'styleGroup'               => $style_group,

						// Following parameters are only for the FrontEnd.
						'storeInstance'            => $store_instance,
						'orderIndex'               => $order_index,
						'presetPriority'           => $preset_priority,
					]
				);

				// Reset the custom module attributes so the next styles rendering will use the original module attributes.
				if ( $elements instanceof ModuleElements ) {
					$elements->clear_custom_attributes();
				}
			}

			// Clear the current preset priority after rendering is complete.
			if ( $elements instanceof ModuleElements ) {
				ModuleElements::clear_current_preset_priority();
			}
		}
	}
}
