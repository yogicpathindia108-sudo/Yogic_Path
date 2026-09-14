<?php
/**
 * ModuleLibrary: Sidebar Module class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Sidebar;

use ET\Builder\Framework\Breakpoint\Breakpoint;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Theme\Theme;
use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use ET\Builder\Packages\GlobalData\GlobalData;
use WP_Block;

/**
 * SidebarModule class.
 *
 * This class contains functions used for Sidebar Module such as Front-End rendering, REST API Endpoints etc.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 */
class SidebarModule implements DependencyInterface {

	/**
	 * Get the module classnames for the Sidebar module.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/module-library/sidebar-member-module-classnames moduleClassnames}
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
	 * SidebarModule::module_classnames( [
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
	 * SidebarModule::module_classnames( [
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

		$orientation = $attrs['sidebar']['advanced']['layout']['desktop']['value']['alignment'] ?? 'left';
		$show_border = $attrs['sidebar']['advanced']['layout']['desktop']['value']['showBorder'] ?? 'on';

		// Module classname.
		$classnames_instance->add(
			[
				'et_pb_widget_area',
				'clearfix',
				"et_pb_widget_area_{$orientation}",
			],
			true
		);

		// Remove default module classname.
		$classnames_instance->remove( 'et_pb_sidebar' );

		// Text Options.
		$classnames_instance->add( TextClassnames::text_options_classnames( $attrs['module']['advanced']['text'] ?? [] ), true );

		$classnames_instance->add( 'et_pb_sidebar_no_border', 'on' !== $show_border );

		// Module.
		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					'attrs' => array_merge(
						$attrs['module']['decoration'] ?? [],
						[
							'link' => $args['attrs']['module']['advanced']['link'] ?? [],
						]
					),
				]
			)
		);
	}

	/**
	 * Generate the script data for the Sidebar module.
	 *
	 * This function sets element script data options and uses `MultiViewScriptData` to set module-specific FrontEnd data.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Array of arguments for generating the script data.
	 *
	 *     @type object      $elements       The elements object.
	 * }
	 *
	 * @return void
	 *
	 * @example:
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
	 *     'store_instance' => 123,
	 * );
	 *
	 * Sidebar::module_script_data( $args );
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

		$elements->script_data(
			[
				'attrName' => 'sidebarWidgets',
			]
		);
	}

	/**
	 * Retrieve the custom CSS fields for the Divi sidebar block.
	 *
	 * This function returns an array of custom CSS fields that can be used with the Divi sidebar block.
	 *
	 * This function is equivalent of JS const:
	 * {@link /docs/builder-api/js/module-library/sidebar-css-fields cssFields}
	 * located in `@divi/module-library` package.
	 *
	 * A minor difference with the JS const cssFields, this function did not have `label` property on each array item.
	 *
	 * @since ??
	 *
	 * @return array The array of custom CSS fields for the Divi sidebar block.
	 */
	public static function custom_css(): array {
		return \WP_Block_Type_Registry::get_instance()->get_registered( 'divi/sidebar' )->customCssFields;
	}


	/**
	 * Load Sidebar module style components.
	 *
	 * This function is responsible for loading styles for the module. It takes an array of arguments
	 * which includes the module ID, name, attributes, settings, and other details. The function then
	 * uses these arguments to dynamically generate and add the required styles.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/module-library/sidebar-module-styles ModuleStyles}
	 * located in `@divi/module-library` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string         $id                       The module ID. In Visual Builder (VB), the ID of the module is a UUIDV4 string.
	 *                                                    In FrontEnd (FE), the ID is the order index.
	 *     @type string         $name                     The module name.
	 *     @type array          $attrs                    Optional. The module attributes. Default `[]`.
	 *     @type array          $elements                 The module elements.
	 *     @type array          $settings                 Optional. The module settings. Default `[]`.
	 *     @type string         $orderClass               The selector class name.
	 *     @type int            $orderIndex               The order index of the module.
	 *     @type int            $storeInstance            The ID of instance where this block stored in BlockParserStore.
	 *     @type ModuleElements $elements                 ModuleElements instance.
	 * }
	 *
	 * @return void
	 *
	 * @example:
	 * ```php
	 *     SidebarModule::module_styles([
	 *         'id'        => 'module-1',
	 *         'name'      => 'Accordion Module',
	 *         'attrs'     => [],
	 *         'elements'  => $elementsInstance,
	 *         'settings'  => $moduleSettings,
	 *         'orderClass'=> '.accordion-module'
	 *     ]);
	 * ```
	 */
	public static function module_styles( array $args ): void {
		$attrs    = $args['attrs'] ?? [];
		$elements = $args['elements'];
		$settings = $args['settings'] ?? [];

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
											'selector' => implode(
												', ',
												[
													$args['orderClass'],
													"{$args['orderClass']} .widgettitle",
												]
											),
											'attr'     => $attrs['module']['advanced']['text'] ?? [],
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

					// Title.
					$elements->style(
						[
							'attrName' => 'title',
						]
					),

					// Content.
					$elements->style(
						[
							'attrName' => 'sidebar',
						]
					),

					// Sidebar Widgets.
					$elements->style(
						[
							'attrName' => 'sidebarWidgets',
						]
					),

					// Module - Only for Custom CSS.
					CssStyle::style(
						[
							'selector'  => $args['orderClass'] . '.et_pb_widget_area',
							'attr'      => $attrs['css'] ?? [],
							'cssFields' => self::custom_css(),
						]
					),
				],
			]
		);
	}

	/**
	 * Render callback function for the Sidebar module.
	 *
	 * This function generates HTML for rendering on the FrontEnd (FE).
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       The attributes passed to the block.
	 * @param string         $child_modules_content       The content inside the block (child modules content).
	 * @param WP_Block       $block                       The parsed block object.
	 * @param ModuleElements $elements                    The elements object containing style components.
	 *
	 * @return string The rendered HTML content.
	 *
	 * @example:
	 * ```php
	 * // Render the block with an empty content and default attributes.
	 * $attrs = [];
	 * $content = '';
	 * $block = new Block();
	 * $elements = new Elements();
	 *
	 * $rendered_content = SidebarModule::render_callback($attrs, $content, $block, $elements);
	 * ```

	 * @example:
	 * ```php
	 * // Render the block with custom attributes and content.
	 * $attrs = [
	 *     'param1' => 'value1',
	 *     'param2' => 'value2',
	 * ];
	 * $content = '<p>Block content</p>';
	 * $block = new Block();
	 * $elements = new Elements();
	 *
	 * $rendered_content = SidebarModule::render_callback($attrs, $content, $block, $elements);
	 * ```
	 */
	public static function render_callback( array $attrs, string $child_modules_content, WP_Block $block, ModuleElements $elements ): string {
		// Extract child modules IDs using helper utility.
		$children_ids = ChildrenUtils::extract_children_ids( $block );

		// Get Parent.
		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		$area = $attrs['sidebar']['innerContent']['desktop']['value']['area'] ?? '';

		// Get any available widget areas so it isn't empty.
		if ( '' === $area ) {
			$area = Theme::get_default_area();
		}

		// Get layout configuration for flex column classes.
		$layout_style    = $attrs['sidebar']['advanced']['layout']['desktop']['value']['layoutStyle'] ?? 'flex';
		$module_layout   = $attrs['module']['decoration']['layout']['desktop']['value']['display'] ?? 'flex';
		$is_flex_layout  = 'flex' === $module_layout;
		$is_layout_flex  = 'flex' === $layout_style;
		$should_use_flex = $is_layout_flex && $is_flex_layout;

		$children = '';

		ob_start();

		if ( is_active_sidebar( $area ) ) {
			dynamic_sidebar( $area );
		}

		$widgets = ob_get_clean();

		// Add flex column classes to widgets if layout is flex.
		if ( $should_use_flex ) {
			// Early performance checks to avoid unnecessary regex operations.
			if ( empty( $widgets ) || false === strpos( $widgets, 'et_pb_widget' ) ) {
				$children .= normalize_whitespace( $widgets );
			} else {
				$breakpoints_mapping = Breakpoint::get_css_class_suffixes();

				$flex_classes = [ 'et_flex_column' ];

				foreach ( $breakpoints_mapping as $breakpoint => $suffix ) {
					if ( ! Breakpoint::is_enabled_for_style( $breakpoint ) ) {
						continue;
					}

					$flex_type = $attrs['sidebarWidgets']['advanced']['flexType'][ $breakpoint ]['value'] ?? null;

					if ( $flex_type && 'none' !== $flex_type ) {
						$flex_classes[] = "et_flex_column_{$flex_type}{$suffix}";
					}
				}

				$flex_class_string = implode( ' ', $flex_classes );

				// Add flex column classes directly to each widget wrapper.
				// Regex test: https://regex101.com/r/1QzaLc/1.
				$widgets = preg_replace(
					'/(<div[^>]*class=")([^"]*et_pb_widget[^"]*)"([^>]*>)/',
					'$1$2 ' . esc_attr( $flex_class_string ) . '"$3',
					$widgets
				);

				$children .= normalize_whitespace( $widgets );
			}
		} else {
			$children .= normalize_whitespace( $widgets );
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
				) . $children . $child_modules_content,
			]
		);
	}

	/**
	 * Loads `SidebarModule` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @return void
	 */
	/**
	 * Load the module library.
	 *
	 * This function is responsible for registering the module and its render callback
	 * on the WordPress 'init' action. It utilizes the ModuleRegistration class to register
	 * the module with the specified folder path and render callback.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/sidebar/';

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
