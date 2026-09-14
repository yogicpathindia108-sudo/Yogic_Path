<?php
/**
 * ModuleLibrary: Toggle Module class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Toggle;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound -- D5 require PHP version >= 7.0
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Element\ElementStyle;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleLibrary\Toggle\TogglePresetAttrsMap;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use WP_Block;
use ET\Builder\Packages\GlobalData\GlobalPresetItemGroup;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;

/**
 * ToggleModule class.
 *
 * This class is contains functions used for Toggle Module such as Front-End rendering, REST API Endpoints etc.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 */
class ToggleModule implements DependencyInterface {

	/**
	 * Get the module classnames for the Toggle module.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/module-library/toggle-module-classnames moduleClassnames}
	 * located in `@divi/module-library` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type object $classnamesInstance An instance of `ET\Builder\Packages\Module\Layout\Components\Classnames` class.
	 *     @type array  $attrs              Block attributes data that is being rendered.
	 * }
	 *
	 * @example:
	 * ```php
	 * // Example 1: Adding classnames for the toggle options.
	 * ToggleModule::module_classnames( [
	 *     'classnamesInstance' => $classnamesInstance,
	 *     'attrs' => [
	 *         'module' => [
	 *             'advanced' => [
	 *                 'text' => ['red', 'bold']
	 *             ]
	 *         ]
	 *     ]
	 * ] );
	 * ```
	 *
	 * @example:
	 * ```php
	 * // Example 2: Adding classnames for the module.
	 * ToggleModule::module_classnames( [
	 *     'classnamesInstance' => $classnamesInstance,
	 *     'attrs' => [
	 *         'module' => [
	 *             'decoration' => ['shadow', 'rounded']
	 *         ]
	 *     ]
	 * ] );
	 * ```
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];
		$open                = $attrs['module']['advanced']['open']['desktop']['value'] ?? false;

		$classnames_instance->add( 'et_pb_toggle_item' );

		// Add appropriate classname for open and closed toggle.
		if ( 'on' === $open ) {
			$classnames_instance->add( 'et_pb_toggle_open', true );
		} else {
			$classnames_instance->add( 'et_pb_toggle_close', true );
		}

		// Module.
		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					// phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
					// TODO feat(D5, Module Attribute Refactor) Once link is merged as part of options property, remove this.
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
	 * Generate the script data for the Toggle module based on the provided arguments.
	 *
	 * This function assigns variables and sets element script data options.
	 * It then uses `MultiViewScriptData` to set module specific FrontEnd (FE) data.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Array of arguments for generating the script data.
	 *
	 *     @type object  $elements       The elements object. An instance of ModuleElements.
	 * }
	 *
	 * @return void
	 *
	 * @example
	 * ```php
	 * // Generate the script data for a module with specific arguments.
	 * $args = array(
	 *     'id'             => 'my-module',
	 *     'name'           => 'My Module',
	 *     'selector'       => '.my-module',
	 *     'attrs'          => array(
	 *         'portfolio' => array(
	 *             'advanced' => array(
	 *                 'showTitle'       => false,
	 *                 'showCategories'  => true,
	 *                 'showPagination' => true,
	 *             )
	 *         )
	 *     ),
	 *     'elements'       => $elements,
	 *     'storeInstance' => 1,
	 * );
	 *
	 * Toggle::module_script_data( $args );
	 * ```
	 */
	public static function module_script_data( array $args ): void {
		// Assign variables.
		$elements = $args['elements'];

		// Element Script Data Options.
		$elements->script_data(
			[
				'attrName' => 'module',
			]
		);
	}

	/**
	 * Get the custom CSS fields for the Toggle module.
	 *
	 * This function is used to retrieve the custom CSS fields for the Toggle module registered with Div.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/module-library/toggle-css-fields cssFields}
	 * located in `@divi/module-library` package.
	 *
	 * A minor difference with the JS const cssFields, this function does not have `label` property on each array item.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields.
	 */
	public static function custom_css(): array {
		return \WP_Block_Type_Registry::get_instance()->get_registered( 'divi/toggle' )->customCssFields;
	}

	/**
	 * Icon style declaration.
	 *
	 * This function is responsible for declaring the icon style for a module.
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array      $attrValue  The value (breakpoint > state > value) of the module attribute.
	 * }
	 *
	 * @return string The style declarations for the icon as a string.
	 *
	 * @since ??
	 *
	 * @example
	 * ```php
	 * // Example usage in Blurb Module:
	 * $params = [
	 *     'attrValue' => [
	 *         'useSize' => 'on',
	 *         'size' => '20px',
	 *         'type' => 'fa',
	 *         'weight' => 'bold',
	 *         'unicode' => 'f0a6',
	 *         'color' => '#ffffff',
	 *     ],
	 * ];
	 * $style = ToggleModule::icon_style_declaration( $params );
	 * ```
	 */
	public static function icon_style_declaration( array $params ): string {
		$icon_attr                  = $params['attrValue'] ?? [];
		$use_size                   = $icon_attr['useSize'] ?? '';
		$maybe_global_variable_size = $icon_attr['size'] ?? '';

		$size = GlobalData::resolve_global_variable_value( $maybe_global_variable_size );

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		if ( 'on' === $use_size && ! empty( $size ) ) {
			// Since we can not directly calculate the css math functions in PHP, It can only be calculated on the Browser in runtime.
			// So, the numeric_parse_value( $size ) will return null for the CSS math functions.
			// And now, we have added is_css_math_function() to check, if it is a CSS math function or not.
			// If it is a CSS math function, we are sending the right: property value with its original format.
			// Same applies to CSS variables and CSS keywords (inherit, unset, etc.).
			if ( ModuleUtils::is_css_math_function( $size ) || ModuleUtils::is_css_variable( $size ) || ModuleUtils::is_css_keyword( $size ) ) {
				$style_declarations->add( 'right', $size );
			} else {
				$icon_size         = SanitizerUtility::numeric_parse_value( $size );
				$default_attrs     = ModuleRegistration::get_default_attrs( 'divi/toggle', 'defaultPrintedStyle' );
				$default_icon_size = SanitizerUtility::numeric_parse_value(
					$default_attrs['closedToggleIcon']['decoration']['icon']['desktop']['value']['size']
				);
				$size_diff         = ( $default_icon_size['valueNumber'] ?? 0 ) - ( $icon_size['valueNumber'] ?? 0 );
				$style_declarations->add( 'right', 0 !== $size_diff ? round( $size_diff / 2 ) . $icon_size['valueUnit'] : 0 );
			}
		}
		return $style_declarations->value();
	}


	/**
	 * Add Toggle module style components.
	 *
	 * This function adds styles for a module to the Style class.
	 * It takes an array of arguments and uses them to define the styles for the module.
	 * The styles are then added to the Style class instance using the `Style::add()` method.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/module-library/toggle-module-styles ModuleStyles}
	 * located in `@divi/module-library` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments for defining the module styles.
	 *
	 *     @type string  $id               Optional. The ID of the module. Default empty string.
	 *                                     In Visual Builder (VB), the ID of a module is a UUIDV4 string.
	 *                                     In FrontEnd (FE), the ID is order index.
	 *     @type string  $name             Optional. The name of the module. Default empty string.
	 *     @type int     $orderIndex       The order index of the module style.
	 *     @type array   $attrs            Optional. The attributes of the module. Default `[]`.
	 *     @type object  $elements         The elements object.
	 *     @type array   $settings         Optional. An array of settings for the module style. Default `[]`.
	 *     @type integer $storeInstance    Optional. The ID of instance where this block is stored in BlockParserStore. Default `null`.
	 *     @type string  $orderClass       The order class for the module style.
	 *     @type ModuleElements $elements  ModuleElements instance.
	 * }
	 *
	 * @return void
	 *
	 * @example:
	 * ```php
	 *     // Example usage of the module_styles() function.
	 *     ToggleModule::module_styles( [
	 *         'id'            => 'my-module-style',
	 *         'name'          => 'My Module Style',
	 *         'orderIndex'    => 1,
	 *         'storeInstance' => null,
	 *         'attrs'         => [
	 *             'css' => [
	 *                 'color' => 'red',
	 *             ],
	 *         ],
	 *         'elements'      => $elements,
	 *         'settings'      => [
	 *             'disabledModuleVisibility' => true,
	 *         ],
	 *         'orderClass'    => '.my-module',
	 *     ] );
	 * ```
	 *
	 * @example:
	 * ```php
	 *     // Another example usage of the module_styles() function.
	 *     $args = [
	 *         'id'            => 'my-module-style',
	 *         'name'          => 'My Module Style',
	 *         'orderIndex'    => 1,
	 *         'storeInstance' => null,
	 *         'attrs'         => [
	 *             'css' => [
	 *                 'color' => 'blue',
	 *             ],
	 *         ],
	 *         'elements'      => $elements,
	 *         'settings'      => [
	 *             'disabledModuleVisibility' => false,
	 *         ],
	 *         'orderClass'    => '.my-module',
	 *     ];
	 *     ToggleModule::module_styles( $args );
	 * ```
	 */
	public static function module_styles( array $args ): void {
		$attrs                     = $args['attrs'] ?? [];
		$elements                  = $args['elements'];
		$settings                  = $args['settings'] ?? [];
		$order_class               = $args['orderClass'] ?? '';
		$is_inside_sticky_module   = $elements->get_is_inside_sticky_module();
		$sticky_parent_order_class = $elements->get_sticky_parent_order_class();

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
										'componentName' => 'divi/text',
										'props'         => [
											'attr' => $attrs['module']['advanced']['text'] ?? [],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'attr' => $attrs['module']['decoration']['border'] ?? [],
											'declarationFunction' => function ( $params ) use ( $attrs ) {
												$overflow_attr = $attrs['module']['decoration']['overflow'] ?? [];
												return Declarations::overflow_for_border_radius_style_declaration( $params, $overflow_attr );
											},
										],
									],
								],
							],
						]
					),
					// Toggle Open Icon.
					$elements->style(
						[
							'attrName'   => 'openToggleIcon',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => implode(
												',',
												[
													"{$args['orderClass']}.et_pb_toggle_open .et_pb_toggle_title:before",
													"{$args['orderClass']}.et_pb_toggle_open .et_vb_toggle_overlay",
												]
											),
											'attr'     => $attrs['openToggleIcon']['decoration']['icon'] ?? [],
											'declarationFunction' => [ self::class, 'icon_style_declaration' ],
										],
									],
								],
							],
						]
					),
					// Toggle Close Icon.
					$elements->style(
						[
							'attrName'   => 'closedToggleIcon',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => implode(
												',',
												[
													"{$args['orderClass']}.et_pb_toggle_close .et_pb_toggle_title:before",
													"{$args['orderClass']}.et_pb_toggle_close .et_vb_toggle_overlay",
												]
											),
											'attr'     => $attrs['closedToggleIcon']['decoration']['icon'] ?? [],
											'declarationFunction' => [ self::class, 'icon_style_declaration' ],
										],
									],
								],
							],
						]
					),
					// Toggle Open.
					$elements->style(
						[
							'attrName'              => 'openToggle',
							'styleProps'            => [
								'font' => [
									'important' => [
										'font' => [
											'desktop' => [
												'value' => [
													'color' => true,
												],
											],
										],
									],
								],
							],
							'isMergeRecursiveProps' => true,
						]
					),
					// Toggle Close.
					$elements->style(
						[
							'attrName' => 'closedToggle',
						]
					),
					// Toggle Open Title.
					$elements->style(
						[
							'attrName' => 'title',
						]
					),
					// Toggle Close Title.
					$elements->style(
						[
							'attrName'              => 'closedTitle',
							'styleProps'            => [
								'font' => [
									'important' => [
										'font' => [
											'desktop' => [
												'value' => [
													'color' => true,
												],
											],
										],
									],
								],
							],
							'isMergeRecursiveProps' => true,
						]
					),
					// Content.
					$elements->style(
						[
							'attrName' => 'content',
						]
					),
					ElementStyle::style(
						[
							'selector'               => "{$args['orderClass']} .et_pb_toggle_content",
							'attrs'                  => $attrs['content']['decoration'] ?? [],
							'bodyFont'               => [
								'selectors' => [
									'desktop' => [
										'value' => "{$args['orderClass']} .et_pb_toggle_content",
										'hover' => "{$args['orderClass']}:hover .et_pb_toggle_content",
									],
								],
							],
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					),
					// Module - Only for Custom CSS.
					CssStyle::style(
						[
							'selector'  => $args['orderClass'] . '.et_pb_toggle',

							'attr'      => $attrs['css'] ?? [],
							'cssFields' => self::custom_css(),
						]
					),
				],
			]
		);
	}

	/**
	 * Renders the output HTML of the Toggle module on the FrontEnd (FE).
	 *
	 * @since ??
	 *
	 * @param array          $attrs                 The block attributes that were saved by the VB.
	 * @param string         $child_modules_content Block content from child modules.
	 * @param WP_Block       $block                 The parsed block object being rendered.
	 * @param ModuleElements $elements              The ModuleElements instance.
	 *
	 * @return string The rendered HTML of the Toggle module.
	 *
	 * @example:
	 * ```php
	 *     $attrs = [
	 *         'attrName' => 'value',
	 *         // other attributes...
	 *     ];
	 *     $child_modules_content = 'This is the content';
	 *     $block = new WP_Block();
	 *     $elements = new ModuleElements();
	 *
	 *     $output = Toggle::render_callback($attrs, $child_modules_content, $block, $elements);
	 * ```
	 */
	public static function render_callback( array $attrs, string $child_modules_content, WP_Block $block, ModuleElements $elements ): string {
		// Layout classes for content element.
		// These classes are merged with the existing 'et_pb_toggle_content' class from metadata.
		$layout_display_value = $attrs['module']['decoration']['layout']['desktop']['value']['display'] ?? 'flex';
		$content_classes      = HTMLUtility::classnames(
			'et_pb_toggle_content clearfix',
			[
				'et_flex_module' => 'flex' === $layout_display_value,
				'et_grid_module' => 'grid' === $layout_display_value,
			]
		);

		// Extract child modules IDs using helper utility.
		$children_ids = ChildrenUtils::extract_children_ids( $block );

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		return Module::render(
			[
				// Front-end only.
				'orderIndex'          => $block->parsed_block['orderIndex'],
				'storeInstance'       => $block->parsed_block['storeInstance'],

				// Visual Builder equivalent.
				'attrs'               => $attrs,
				'elements'            => $elements,
				'id'                  => $block->parsed_block['id'],
				'name'                => $block->block_type->name,
				'classnamesFunction'  => [ self::class, 'module_classnames' ],
				'moduleCategory'      => $block->block_type->category,
				'stylesComponent'     => [ self::class, 'module_styles' ],
				'scriptDataComponent' => [ self::class, 'module_script_data' ],
				'parentAttrs'         => $parent->attrs ?? [],
				'parentId'            => $parent->id ?? '',
				'parentName'          => $parent->blockName ?? '',
				'childrenIds'         => $children_ids,
				'children'            => [
					$elements->style_components(
						[
							'attrName' => 'module',
						]
					),
					$elements->render(
						[
							'attrName' => 'title',
						]
					),
					$elements->render(
						[
							'attrName'        => 'content',
							'attributes'      => [
								'class' => $content_classes,
							],
							'children'        => $child_modules_content,
							'allowEmptyValue' => ! empty( $child_modules_content ), // Allow empty content if children exist.
						]
					),
				],
			]
		);
	}

	/**
	 * Load and register the Toggle module.
	 *
	 * This function registers the toggle module to be used in the Visual Builder (VB).
	 * It adds an action to the WordPress 'init' hook that calls the `register_module` method
	 * of the `ModuleRegistration` class, passing the module JSON folder path and the render callback
	 * function as arguments.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/toggle/';

		add_filter( 'divi_conversion_presets_attrs_map', [ TogglePresetAttrsMap::class, 'get_map' ], 10, 2 );

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
