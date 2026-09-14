<?php
/**
 * ModuleLibrary: Text Module class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Text;

use ET\Builder\Packages\ModuleLibrary\Text\TextPresetAttrsMap;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\GlobalData\GlobalPresetItemGroup;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use WP_Block;

/**
 * TextModule class.
 *
 * This class contains functions used for Text Module such as Front-End rendering, REST API Endpoints etc.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 */
class TextModule implements DependencyInterface {

	/**
	 * Get the module classnames for the Text module.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/module-library/text-module-classnames moduleClassnames}
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
	 * // Example 1: Adding classnames for the text options.
	 * TextModule::module_classnames( [
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
	 * TextModule::module_classnames( [
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

		// Text options.
		// Disable global orientation classes to prevent CSS specificity conflicts (issue #43802).
		// Module-specific CSS handles text alignment with full responsive breakpoint support.
		$classnames_instance->add( TextClassnames::text_options_classnames( $attrs['module']['advanced']['text'] ?? [], [ 'orientation' => false ] ), true );

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
	 * Generate the script data for the Text module based on the provided arguments.
	 *
	 * This function assigns variables and sets element script data options.
	 * It then uses `MultiViewScriptData` to set module specific FrontEnd (FE) data.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Array of arguments for generating the script data.
	 *
	 *     @type string  $id             Optional. The ID of the module. Default empty string.
	 *     @type string  $name           Optional. The name of the module. Default empty string.
	 *     @type string  $selector       Optional. The selector of the module. Default empty string.
	 *     @type array   $attrs          Optional. The attributes of the module. Default `[]`.
	 *     @type object  $elements       The elements object.
	 *     @type integer $storeInstance  Optional. The ID of instance where this block is stored in BlockParserStore. Default `null`.
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
	 * Text::module_script_data( $args );
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
	 * Add Text module style components.
	 *
	 * This function adds styles for a module to the Style class.
	 * It takes an array of arguments and uses them to define the styles for the module.
	 * The styles are then added to the Style class instance using the `Style::add()` method.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/module-library/text-module-styles ModuleStyles}
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
	 *     TextModule::module_styles( [
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
	 *     TextModule::module_styles( $args );
	 * ```
	 */
	public static function module_styles( array $args ): void {
		$attrs                       = $args['attrs'] ?? [];
		$elements                    = $args['elements'];
		$settings                    = $args['settings'] ?? [];
		$default_printed_style_attrs = $args['defaultPrintedStyleAttrs'] ?? [];

		$preset_printed_module_text = isset( $elements->preset_printed_style_attrs ) && is_array( $elements->preset_printed_style_attrs )
			? ( $elements->preset_printed_style_attrs['module']['advanced']['text'] ?? [] )
			: [];

		$module_text_default_printed_style_attr = array_replace_recursive(
			$default_printed_style_attrs['module']['advanced']['text'] ?? [],
			$preset_printed_module_text
		);

		$color_important = [
			'font' => [
				'desktop' => [
					'value' => [
						'color' => true,
					],
				],
			],
		];

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
											'attr'                    => $attrs['module']['advanced']['text'] ?? [],
											'defaultPrintedStyleAttr' => $module_text_default_printed_style_attr,
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
					// Content.
					// Selector is set to "{$args['orderClass']} .et_pb_text_inner" to differentiate from module element
					// selector and prevent CSS transition-property conflicts.
					$elements->style(
						[
							'attrName'   => 'content',
							'styleProps' => [
								'selector' => "{$args['orderClass']} .et_pb_text_inner",
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
	 * Render callback for the Text module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the FrontEnd (FE).
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/module-library/text-edit TextEdit}
	 * located in `@divi/module-library` package.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       The block attributes that were saved by the Visual Builder.
	 * @param string         $child_modules_content       The block content.
	 * @param WP_Block       $block                       The parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 * @param array          $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string The rendered HTML for the module.
	 *
	 * @example:
	 * ```php
	 * $attrs = [
	 *     'attrName' => 'value',
	 *     //...
	 * ];
	 * $content = 'This is the content';
	 * $block = new WP_Block();
	 * $elements = new ModuleElements();
	 *
	 * $html = Text::render_callback( $attrs, $content, $block, $elements );
	 * echo $html;
	 * ```
	 */
	public static function render_callback( array $attrs, string $child_modules_content, WP_Block $block, ModuleElements $elements, array $default_printed_style_attrs ): string {
		// Content.
		$content = $elements->render(
			[
				'attrName' => 'content',
			]
		);

		// Process Gutenberg blocks in text content on WooCommerce cart and checkout pages.
		// This handles cases where WooCommerce blocks are embedded in text modules when VB prefills the page.
		// We limit this to cart/checkout pages to minimize any potential side effects.
		$is_wc_active        = function_exists( 'is_cart' ) && function_exists( 'is_checkout' );
		$is_cart_or_checkout = $is_wc_active && ( is_cart() || is_checkout() );

		if ( $is_cart_or_checkout && false !== strpos( $content, '<!-- wp:' ) ) {
			// Clean up paragraph tags around block comments that wpautop might have added.
			// Pattern matches: <p><!-- wp:block --> or <!-- /wp:block --></p> or both.
			// This ensures block comments are properly formatted for do_blocks() processing.
			$content = preg_replace( '/(<p>)?<!-- (\/)?wp:(.+?) (\/?)-->(<\/p>)?/', '<!-- $2wp:$3 $4-->', $content );

			// Process Gutenberg blocks through WordPress's block rendering system.
			$content = et_builder_render_layout_do_blocks( $content );
		}

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		// Extract child modules IDs using helper utility.
		$children_ids = ChildrenUtils::extract_children_ids( $block );

		return Module::render(
			[
				// FE only.
				'orderIndex'          => $block->parsed_block['orderIndex'],
				'storeInstance'       => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'attrs'                    => $attrs,
				'elements'                 => $elements,
				'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
				'id'                       => $block->parsed_block['id'],
				'name'                     => $block->block_type->name,
				'classnamesFunction'       => [ self::class, 'module_classnames' ],
				'moduleCategory'           => $block->block_type->category,
				'stylesComponent'          => [ self::class, 'module_styles' ],
				'scriptDataComponent'      => [ self::class, 'module_script_data' ],
				'parentAttrs'              => $parent->attrs ?? [],
				'parentId'                 => $parent->id ?? '',
				'parentName'               => $parent->blockName ?? '',
				'childrenIds'              => $children_ids,
				'children'            => $elements->style_components(
					[
						'attrName' => 'module',
					]
				) . $content . $child_modules_content,
			]
		);
	}

	/**
	 * Generate text alignment classes from converted attributes for migration compatibility.
	 *
	 * This function generates alignment classes (et_pb_text_align_center, etc.) from
	 * text orientation attributes and returns them in a format suitable for adding to
	 * custom attributes during D4 to D5 conversion.
	 *
	 * @since ??
	 *
	 * @param array $converted_attrs The converted attributes array.
	 *
	 * @return string The generated alignment classnames, or empty string if none.
	 */
	/**
	 * Generate text alignment classes from converted or original attributes.
	 *
	 * @since ??
	 *
	 * @param array $converted_attrs The converted attributes array.
	 * @param array $original_attrs   The original D4 attributes array.
	 *
	 * @return string The generated alignment classnames, or empty string if none.
	 */
	public static function get_migration_alignment_classes( array $converted_attrs, array $original_attrs = [] ): string {
		// Text orientation is stored at module.advanced.text.text[breakpoint].value.orientation
		// The first 'text' is the advanced option, the second 'text' is the sub-option.
		$text_attrs = $converted_attrs['module']['advanced']['text']['text'] ?? [];

		// Fallback: If converted attributes don't have text orientation, check original D4 attributes.
		if ( empty( $text_attrs ) && ! empty( $original_attrs ) ) {
			$text_attrs = self::_get_text_orientation_from_d4_attrs( $original_attrs );
		}

		if ( empty( $text_attrs ) || ! is_array( $text_attrs ) ) {
			return '';
		}

		$classnames        = [];
		$valid_orientation = [ 'left', 'center', 'right', 'justify' ];

		foreach ( $text_attrs as $breakpoint => $attr_values ) {
			if ( ! is_array( $attr_values ) || ! isset( $attr_values['value']['orientation'] ) ) {
				continue;
			}

			$orientation = $attr_values['value']['orientation'];
			if ( ! in_array( $orientation, $valid_orientation, true ) ) {
				continue;
			}

			$orientation_class = 'justify' === $orientation ? 'justified' : $orientation;
			$suffix            = 'desktop' === $breakpoint ? '' : '-' . $breakpoint;

			$classnames[] = 'et_pb_text_align_' . $orientation_class . $suffix;
		}

		return implode( ' ', $classnames );
	}

	/**
	 * Extract text orientation from D4 attributes and convert to D5 structure.
	 *
	 * @since ??
	 *
	 * @param array $original_attrs The original D4 attributes.
	 *
	 * @return array Text attributes in D5 structure format.
	 */
	private static function _get_text_orientation_from_d4_attrs( array $original_attrs ): array {
		$text_attrs  = [];
		$breakpoints = [
			'desktop' => '',
			'tablet'  => '_tablet',
			'phone'   => '_phone',
		];

		foreach ( $breakpoints as $breakpoint => $suffix ) {
			$d4_attr_name = 'text_orientation' . $suffix;
			$orientation  = $original_attrs[ $d4_attr_name ] ?? null;

			if ( empty( $orientation ) ) {
				continue;
			}

			// Convert 'justified' to 'justify' if needed (D4 sometimes uses 'justified').
			if ( 'justified' === $orientation ) {
				$orientation = 'justify';
			}

			$text_attrs[ $breakpoint ] = [
				'value' => [
					'orientation' => $orientation,
				],
			];
		}

		return $text_attrs;
	}

	/**
	 * Add text alignment classes to custom attributes during D4 to D5 conversion.
	 *
	 * This filter callback adds alignment classes (et_pb_text_align_center, etc.) to
	 * module custom attributes during migration to preserve backward compatibility with
	 * D4 custom CSS without reintroducing CSS specificity conflicts.
	 *
	 * @since ??
	 *
	 * @param array  $converted_attrs      The converted attributes array.
	 * @param string $module_name          The module name.
	 * @param array  $original_attrs       The original D4 attributes.
	 * @param bool   $is_preset_conversion Whether this is a preset conversion.
	 *
	 * @return array The modified converted attributes array.
	 */
	public static function add_migration_alignment_classes( array $converted_attrs, string $module_name, array $original_attrs, bool $is_preset_conversion ): array {
		// Only process Text module and skip preset conversions.
		if ( 'divi/text' !== $module_name || $is_preset_conversion ) {
			return $converted_attrs;
		}

		$alignment_classes = self::get_migration_alignment_classes( $converted_attrs, $original_attrs );

		if ( empty( $alignment_classes ) ) {
			return $converted_attrs;
		}

		// Ensure attributes structure exists.
		if ( ! isset( $converted_attrs['module']['decoration']['attributes'] ) ) {
			$converted_attrs['module']['decoration']['attributes'] = [];
		}
		if ( ! isset( $converted_attrs['module']['decoration']['attributes']['desktop'] ) ) {
			$converted_attrs['module']['decoration']['attributes']['desktop'] = [];
		}
		if ( ! isset( $converted_attrs['module']['decoration']['attributes']['desktop']['value'] ) ) {
			$converted_attrs['module']['decoration']['attributes']['desktop']['value'] = [];
		}
		if ( ! isset( $converted_attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] ) ) {
			$converted_attrs['module']['decoration']['attributes']['desktop']['value']['attributes'] = [];
		}

		// Find next available index for custom attributes array.
		$existing_attributes = $converted_attrs['module']['decoration']['attributes']['desktop']['value']['attributes'];
		$next_index          = is_array( $existing_attributes ) ? count( $existing_attributes ) : 0;

		// Check if class attribute already exists and merge values.
		$class_attr_index = null;
		if ( is_array( $existing_attributes ) ) {
			foreach ( $existing_attributes as $index => $attr ) {
				if ( isset( $attr['name'] ) && 'class' === $attr['name'] ) {
					$class_attr_index = $index;
					break;
				}
			}
		}

		if ( null !== $class_attr_index ) {
			// Merge with existing class attribute.
			$existing_class_value = $converted_attrs['module']['decoration']['attributes']['desktop']['value']['attributes'][ $class_attr_index ]['value'] ?? '';
			$merged_classes       = trim( $existing_class_value . ' ' . $alignment_classes );
			$converted_attrs['module']['decoration']['attributes']['desktop']['value']['attributes'][ $class_attr_index ]['value'] = $merged_classes;
		} else {
			// Add new class attribute.
			$converted_attrs['module']['decoration']['attributes']['desktop']['value']['attributes'][ $next_index ] = [
				'id'            => uniqid(),
				'name'          => 'class',
				'value'         => $alignment_classes,
				'adminLabel'    => 'CSS Class',
				'targetElement' => '',
			];
		}

		return $converted_attrs;
	}

	/**
	 * Load the module by registering a render callback for the Text module.
	 *
	 * This function is responsible for registering the module for the Text module
	 * by adding the render callback to the WordPress `init` action hook.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/text/';

		add_filter( 'divi_conversion_presets_attrs_map', [ TextPresetAttrsMap::class, 'get_map' ], 10, 2 );
		add_filter( 'divi.conversion.postConvertAttrs', [ self::class, 'add_migration_alignment_classes' ], 10, 4 );

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
