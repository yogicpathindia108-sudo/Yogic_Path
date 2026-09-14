<?php
/**
 * Module Library: Dropdown Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Dropdown;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\ArrayUtility;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use WP_Block;

/**
 * DropdownModule class.
 *
 * This class implements the functionality of a dropdown component in a frontend
 * application. It provides functions for rendering the dropdown, managing REST API
 * endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class DropdownModule implements DependencyInterface {

	/**
	 * Dropdown module script data.
	 *
	 * This function assigns variables and sets script data options for the module.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /api/js/divi-module-library/functions/generateDefaultAttrs ModuleScriptData}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Optional. An array of arguments for setting the module script data.
	 *
	 *     @type string         $id            The module ID.
	 *     @type string         $name          The module name.
	 *     @type string         $selector      The module selector.
	 *     @type array          $attrs         The module attributes.
	 *     @type int            $storeInstance The ID of the instance where this block is stored in the `BlockParserStore` class.
	 *     @type ModuleElements $elements      The `ModuleElements` instance.
	 * }
	 *
	 * @return void
	 */
	public static function module_script_data( array $args ): void {
		// Assign variables.
		$id             = $args['id'] ?? '';
		$name           = $args['name'] ?? '';
		$selector       = $args['selector'] ?? '';
		$attrs          = $args['attrs'] ?? [];
		$elements       = $args['elements'];
		$store_instance = $args['storeInstance'] ?? null;

		// Element Script Data Options.
		$elements->script_data(
			[
				'attrName' => 'module',
			]
		);

		// Set responsive dropdown data using MultiView system.
		self::_set_responsive_dropdown_data(
			[
				'id'            => $id,
				'selector'      => $selector,
				'attrs'         => $attrs,
				'storeInstance' => $store_instance,
			]
		);
	}

	/**
	 * Generate classnames for the module.
	 *
	 * This function generates classnames for the module based on the provided
	 * arguments. It is used in the `render_callback` function of the Dropdown module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /docs/builder-api/js/module-library/module-classnames moduleClassnames}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type object $classnamesInstance Module classnames instance.
	 *     @type array  $attrs              Block attributes data for rendering the module.
	 * }
	 *
	 * @return void
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		// Add default hidden class (dropdown is hidden by default until shown via script).
		$classnames_instance->add( 'et_pb_dropdown_hidden', true );

		// Module.
		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					'attrs' => array_merge(
						$attrs['module']['decoration'] ?? [],
						[
							'link' => $attrs['module']['advanced']['link'] ?? [],
						]
					),
				]
			)
		);
	}


	/**
	 * Dropdown Module's style components.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /docs/builder-api/js/module-library/module-styles moduleStyles}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *      @type string $id                Module ID. In VB, the ID of module is UUIDV4. In FE, the ID is order index.
	 *      @type string $name              Module name.
	 *      @type string $attrs             Module attributes.
	 *      @type string $parentAttrs       Parent attrs.
	 *      @type string $orderClass        Selector class name.
	 *      @type string $parentOrderClass  Parent selector class name.
	 *      @type string $wrapperOrderClass Wrapper selector class name.
	 *      @type string $settings          Custom settings.
	 *      @type string $state             Attributes state.
	 *      @type string $mode              Style mode.
	 *      @type ModuleElements $elements  ModuleElements instance.
	 * }
	 *
	 * @return void
	 */
	public static function module_styles( array $args ): void {
		$attrs    = $args['attrs'] ?? [];
		$elements = $args['elements'];
		$settings = $args['settings'] ?? [];

		// Extract dropdown attributes for styling.
		$dropdown_attr_for_style = $attrs['module']['advanced']['dropdown'] ?? [];
		$position_attr           = [];

		// Extract position subname following BlockParserStore pattern.
		if ( is_array( $dropdown_attr_for_style ) && ! empty( $dropdown_attr_for_style ) ) {
			foreach ( $dropdown_attr_for_style as $breakpoint => $breakpoint_value ) {
				if ( is_array( $breakpoint_value ) ) {
					$position_attr[ $breakpoint ] = [];

					foreach ( $breakpoint_value as $state => $state_value ) {
						if ( is_array( $state_value ) && isset( $state_value['position'] ) ) {
							$position_attr[ $breakpoint ][ $state ] = $state_value['position'];
						}
					}
				}
			}
		}

		Style::add(
			[
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => [
					// Module.
					$elements->style(
						[
							'attrName'   => 'module',
							'styleProps' => [
								'disabledOn'     => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'attr' => $position_attr,
											'declarationFunction' => [ self::class, 'dropdown_position_style_declaration' ],
										],
									],
								],
							],
						]
					),
					// Module - Only for Custom CSS.
					CssStyle::style(
						[
							'selector' => $args['orderClass'],
							'attr'     => $attrs['css'] ?? [],
						]
					),
				],
			]
		);
	}

	/**
	 * Render callback for the Dropdown module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ DropdownEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                Block attributes that were saved by Divi Builder.
	 * @param string         $child_modules_content The block's content (child modules content).
	 * @param WP_Block       $block                Parsed block object that is being rendered.
	 * @param ModuleElements $elements             An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the Dropdown module.
	 */
	public static function render_callback( array $attrs, string $child_modules_content, WP_Block $block, ModuleElements $elements ): string {
		// Get dropdown settings from attributes.
		$dropdown_attr      = $attrs['module']['advanced']['dropdown'] ?? [];
		$show_dropdown_on   = ModuleUtils::get_attr_subname_value(
			[
				'attr'         => $dropdown_attr,
				'breakpoint'   => 'desktop',
				'state'        => 'value',
				'subname'      => 'showOn',
				'defaultValue' => 'hover',
			]
		);
		$dropdown_direction = ModuleUtils::get_attr_subname_value(
			[
				'attr'         => $dropdown_attr,
				'breakpoint'   => 'desktop',
				'state'        => 'value',
				'subname'      => 'direction',
				'defaultValue' => 'below',
			]
		);
		$dropdown_alignment = ModuleUtils::get_attr_subname_value(
			[
				'attr'         => $dropdown_attr,
				'breakpoint'   => 'desktop',
				'state'        => 'value',
				'subname'      => 'alignment',
				'defaultValue' => 'start',
			]
		);
		$dropdown_offset    = ModuleUtils::get_attr_subname_value(
			[
				'attr'         => $dropdown_attr,
				'breakpoint'   => 'desktop',
				'state'        => 'value',
				'subname'      => 'offset',
				'defaultValue' => '20px',
			]
		);
		$dropdown_position  = ModuleUtils::get_attr_subname_value(
			[
				'attr'         => $dropdown_attr,
				'breakpoint'   => 'desktop',
				'state'        => 'value',
				'subname'      => 'position',
				'defaultValue' => 'floating',
			]
		);
		$dropdown_horizontal_mode = ModuleUtils::get_attr_subname_value(
			[
				'attr'       => $dropdown_attr,
				'breakpoint' => 'desktop',
				'state'      => 'value',
				'subname'    => 'horizontalMode',
			]
		);

		$dropdown_offset = self::normalize_dropdown_offset( $dropdown_offset );

		// Extract child modules IDs using helper utility.
		$children_ids = ChildrenUtils::extract_children_ids( $block );

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );
		$html_attrs = [
			'data-show-dropdown-on'   => esc_attr( $show_dropdown_on ),
			'data-dropdown-direction' => esc_attr( $dropdown_direction ),
			'data-dropdown-alignment' => esc_attr( $dropdown_alignment ),
			'data-dropdown-offset'    => esc_attr( $dropdown_offset ),
			'data-dropdown-position'  => esc_attr( $dropdown_position ),
		];

		if ( in_array( $dropdown_horizontal_mode, [ 'row', 'viewport' ], true ) ) {
			$html_attrs['data-dropdown-horizontal-mode'] = esc_attr( $dropdown_horizontal_mode );
		}

		return Module::render(
			[
				// FE only.
				'orderIndex'          => $block->parsed_block['orderIndex'],
				'storeInstance'       => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'attrs'               => $attrs,
				'elements'            => $elements,
				'id'                  => $block->parsed_block['id'],
				'name'                => $block->block_type->name,
				'htmlAttrs'           => $html_attrs,
				'classnamesFunction'  => [ self::class, 'module_classnames' ],
				'moduleCategory'      => $block->block_type->category,
				'stylesComponent'     => [ self::class, 'module_styles' ],
				'scriptDataComponent' => [ self::class, 'module_script_data' ],
				'parentAttrs'         => $parent->attrs ?? [],
				'parentId'            => $parent->id ?? '',
				'parentName'          => $parent->blockName ?? '',
				'childrenIds'         => $children_ids,
				'children'            => $elements->style_components(
					[
						'attrName' => 'module',
					]
				) . $child_modules_content,
			]
		);
	}

	/**
	 * Normalize dropdown offset by applying default when value is effectively empty.
	 *
	 * @since ??
	 *
	 * @param mixed $offset Dropdown offset value.
	 *
	 * @return string Normalized offset.
	 */
	private static function normalize_dropdown_offset( $offset ): string {
		if ( ! is_string( $offset ) ) {
			return '20px';
		}

		return '' === trim( $offset ) ? '20px' : $offset;
	}

	/**
	 * Set responsive dropdown data using MultiView system.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments for setting the responsive dropdown data.
	 *
	 *     @type string   $id            The module ID.
	 *     @type string   $selector       The module selector.
	 *     @type array    $attrs         The module attributes for all breakpoints.
	 *     @type int|null $storeInstance The store instance ID.
	 * }
	 * @return void
	 */
	private static function _set_responsive_dropdown_data( array $args ): void {
		// Script data is not needed in VB.
		if ( Conditions::is_vb_enabled() ) {
			return;
		}

		$id             = $args['id'] ?? '';
		$selector       = $args['selector'] ?? '';
		$attrs          = $args['attrs'] ?? [];
		$store_instance = $args['storeInstance'] ?? null;

		if ( empty( $attrs ) || empty( $selector ) || empty( $id ) ) {
			return;
		}

		// Get dropdown attribute for MultiView script data.
		$dropdown_attr = $attrs['module']['advanced']['dropdown'] ?? [];

		if ( empty( $dropdown_attr ) ) {
			return;
		}

		// Map of dropdown data attributes and their corresponding subnames.
		$dropdown_data_attrs = [
			'data-show-dropdown-on'   => 'showOn',
			'data-dropdown-direction' => 'direction',
			'data-dropdown-alignment' => 'alignment',
			'data-dropdown-offset'    => 'offset',
			'data-dropdown-position'  => 'position',
			'data-dropdown-horizontal-mode' => 'horizontalMode',
		];

		// Set MultiView attributes for each dropdown setting using subName parameter.
		foreach ( $dropdown_data_attrs as $data_attr => $subname ) {
			MultiViewScriptData::set_attrs(
				[
					'id'            => $id,
					'name'          => 'divi/dropdown',
					'selector'      => $selector,
					'hoverSelector' => $selector,
					'data'          => [
						$data_attr => $dropdown_attr,
					],
					'subName'       => $subname,
					'sanitizers'    => [
						$data_attr => 'esc_attr',
					],
					'storeInstance' => $store_instance,
				]
			);
		}
	}


	/**
	 * Dropdown position style declaration.
	 *
	 * Applies position: relative when dropdownPosition is set to 'inline'.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/dropdown-position-style-declaration dropdownPositionStyleDeclaration}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array $params Style declaration parameters.
	 *
	 * @return string The generated CSS style declaration.
	 */
	public static function dropdown_position_style_declaration( array $params ): string {
		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
			]
		);

		$attr_value = $params['attrValue'] ?? null;

		if ( 'inline' === $attr_value ) {
			$style_declarations->add( 'position', 'relative' );
		}

		return $style_declarations->value();
	}

	/**
	 * Loads `DropdownModule` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		// phpcs:ignore PHPCompatibility.FunctionUse.NewFunctionParameters.dirname_levelsFound -- We have PHP 7 support now, This can be deleted once PHPCS config is updated.
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/dropdown/';

		// Ensure that all filters and actions applied during module registration are registered before calling `ModuleRegistration::register_module()`.
		// However, for consistency, register all module-specific filters and actions prior to invoking `ModuleRegistration::register_module()`.
		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}
}
