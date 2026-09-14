<?php
/**
 * Module Library: WooCommerceProductMeta Module
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductMeta;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewUtils;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_Block_Type_Registry;
use WP_Block;

/**
 * WooCommerceProductMetaModule class.
 *
 * This class implements the functionality of a call-to-action component
 * in a frontend application. It provides functions for rendering the
 * WooCommerceProductMeta module,
 * managing REST API endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class WooCommerceProductMetaModule implements DependencyInterface {

	/**
	 * Render callback for the WooCommerceProductMeta module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ WooCommerceProductMetaEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $content                     The block's content.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the WooCommerceProductMeta module.
	 *
	 * @example
	 * ```php
	 * $attrs = [
	 *   'attrName' => 'value',
	 *   //...
	 * ];
	 * $content = 'The block content.';
	 * $block = new WP_Block();
	 * $elements = new ModuleElements();
	 *
	 * WooCommerceProductMetaModule::render_callback( $attrs, $content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements ): string {
		// Get breakpoints states info for dynamic access.
		$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
		$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
		$default_state           = $breakpoints_states_info->default_state();

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		// Get parameters from attributes using MultiViewUtils pattern.
		$product_id      = $attrs['content']['advanced']['product'][ $default_breakpoint ][ $default_state ] ?? WooCommerceUtils::get_default_product();
		$loop_product_id = WooCommerceUtils::get_loop_context_product_id( $attrs, $block );
		if ( $loop_product_id > 0 && 'current' === $product_id ) {
			$product_id = $loop_product_id;
		}

		// Generate meta output.
		$meta_output = self::get_meta(
			[
				'product' => $product_id,
			]
		);

		// Bail early if no meta content is returned to prevent rendering empty wrapper.
		$meta_output_content = trim( wp_strip_all_tags( $meta_output ) );
		if ( '' === $meta_output_content ) {
			return '';
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
				'children'            => [
					$elements->style_components(
						[
							'attrName' => 'module',
						]
					),
					HTMLUtility::render(
						[
							'tag'               => 'div',
							'tagEscaped'        => true,
							'attributes'        => [
								'class' => 'et_pb_module_inner',
							],
							'childrenSanitizer' => 'et_core_esc_previously',
							'children'          => $meta_output,
						]
					),
				],
			]
		);
	}

	/**
	 * Generate classnames for the module.
	 *
	 * This function generates classnames for the module based on the provided
	 * arguments. It is used in the `render_callback` function of the WooCommerceProductMeta module.
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
	 *     @type string $breakpoint         Optional. The breakpoint for responsive attributes.
	 *     @type string $state              Optional. The state for responsive attributes.
	 *     @type string $baseBreakpoint     Optional. The base breakpoint.
	 *     @type array  $breakpointNames    Optional. The breakpoint names.
	 * }
	 *
	 * @return void
	 *
	 * @example
	 * ```php
	 * $args = [
	 *     'classnamesInstance' => $classnamesInstance,
	 *     'attrs' => $attrs,
	 * ];
	 *
	 * WooCommerceProductMetaModule::module_classnames($args);
	 * ```
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];
		$breakpoint          = $args['breakpoint'] ?? null;
		$state               = $args['state'] ?? null;
		$base_breakpoint     = $args['baseBreakpoint'] ?? null;
		$breakpoint_names    = $args['breakpointNames'] ?? null;

		$classnames_instance->add(
			TextClassnames::text_options_classnames(
				$attrs['module']['advanced']['text'] ?? [],
				[
					'orientation' => false,
				]
			),
			true
		);

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

		// Determine if we should use responsive attribute handling or default behavior.
		$use_responsive_handling = null !== $breakpoint && null !== $state;

		if ( $use_responsive_handling ) {
			// Use responsive attribute handling (for tests and multiview scenarios).
			$options = [
				'breakpoint' => $breakpoint,
				'state'      => $state,
				'mode'       => 'getOrInheritAll',
			];

			if ( null !== $base_breakpoint ) {
				$options['baseBreakpoint'] = $base_breakpoint;
			}

			if ( null !== $breakpoint_names ) {
				$options['breakpointNames'] = $breakpoint_names;
			}

			// Get attribute values for conditional styling using responsive handling.
			$show_sku        = ModuleUtils::get_attr_value( array_merge( [ 'attr' => $attrs['elements']['advanced']['showSku'] ?? [] ], $options ) ) ?? 'on';
			$show_categories = ModuleUtils::get_attr_value( array_merge( [ 'attr' => $attrs['elements']['advanced']['showCategories'] ?? [] ], $options ) ) ?? 'on';
			$show_tags       = ModuleUtils::get_attr_value( array_merge( [ 'attr' => $attrs['elements']['advanced']['showTags'] ?? [] ], $options ) ) ?? 'on';
			$meta_layout     = ModuleUtils::get_attr_value( array_merge( [ 'attr' => $attrs['layout']['advanced']['metaLayout'] ?? [] ], $options ) ) ?? 'inline';
		} else {
			// Use default behavior (for frontend rendering).
			// Get breakpoints states info for dynamic access.
			$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
			$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
			$default_state           = $breakpoints_states_info->default_state();

			/*
			 * Apply CSS classes based on element visibility settings.
			 *
			 * These classes control the display of product meta elements (SKU, categories, tags) and are added
			 * for the default breakpoint/state to prevent layout shift on a page load. The same classes
			 * are dynamically applied for all breakpoints/states via MultiViewScriptData.
			 */
			$show_sku        = $attrs['elements']['advanced']['showSku'][ $default_breakpoint ][ $default_state ] ?? 'on';
			$show_categories = $attrs['elements']['advanced']['showCategories'][ $default_breakpoint ][ $default_state ] ?? 'on';
			$show_tags       = $attrs['elements']['advanced']['showTags'][ $default_breakpoint ][ $default_state ] ?? 'on';
			$meta_layout     = $attrs['layout']['advanced']['metaLayout'][ $default_breakpoint ][ $default_state ] ?? 'inline';
		}

		// Add conditional CSS classes based on element visibility settings.
		if ( 'on' !== $show_sku ) {
			$classnames_instance->add( 'et_pb_wc_no_sku' );
		}

		if ( 'on' !== $show_categories ) {
			$classnames_instance->add( 'et_pb_wc_no_categories' );
		}

		if ( 'on' !== $show_tags ) {
			$classnames_instance->add( 'et_pb_wc_no_tags' );
		}

		// Add layout-specific CSS class.
		if ( $meta_layout ) {
			$classnames_instance->add( "et_pb_wc_meta_layout_{$meta_layout}" );
		}
	}

	/**
	 * WooCommerceProductMeta module script data.
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
		$store_instance = $args['storeInstance'] ?? null;
		$elements       = $args['elements'];

		// Element Script Data Options.
		$elements->script_data(
			[
				'attrName' => 'module',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'elements',
			]
		);

		$elements->script_data(
			[
				'attrName' => 'layout',
			]
		);

		// Add responsive class names for show/hide settings.
		MultiViewScriptData::set(
			[
				'id'            => $id,
				'name'          => $name,
				'storeInstance' => $store_instance,
				'hoverSelector' => $selector,
				'setClassName'  => [
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_wc_no_sku' => $attrs['elements']['advanced']['showSku'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_wc_no_categories' => $attrs['elements']['advanced']['showCategories'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_wc_no_tags' => $attrs['elements']['advanced']['showTags'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
				],
			]
		);
	}

	/**
	 * WooCommerceProductMeta Module's style components.
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

		// Extract the order class.
		$order_class = $args['orderClass'] ?? '';

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
											'selector' => "{$order_class}",
											'attr'     => $attrs['module']['advanced']['text'] ?? [],
										],
									],
								],
							],
						]
					),
					// Content.
					$elements->style(
						[
							'attrName'   => 'content',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => implode(
												', ',
												[
													// SKU separator: show when SKU is visible AND anything after it is visible.
													"{$order_class}.et_pb_wc_meta_layout_inline:not(.et_pb_wc_no_sku):is(:not(.et_pb_wc_no_categories), :not(.et_pb_wc_no_tags)) .product_meta .sku_wrapper:not(:last-child):after",

													// Category separator: show when Category is visible AND anything after it is visible.
													// Handles both older WC (Tags only) and newer WC (Tags or Brand).
													"{$order_class}.et_pb_wc_meta_layout_inline:not(.et_pb_wc_no_categories) .product_meta .posted_in:not(:last-child):after",

													// Tags separator: show when Tags is visible AND Brand does not exist (WooCommerce < 8.0).
													"{$order_class}.et_pb_wc_meta_layout_inline:not(.et_pb_wc_no_tags) .product_meta .tagged_as:not(:last-child):after",

													// Tags separator: show when Tags is visible AND Brand exists (WooCommerce >= 8.0).
													"{$order_class}.et_pb_wc_meta_layout_inline:not(.et_pb_wc_no_tags):not(.et_pb_wc_no_categories) .product_meta .tagged_as:after",
												]
											),
											'attr'     => $attrs['content']['advanced']['separator'] ?? [],
											'declarationFunction' => [
												self::class,
												'separator_style_declaration',
											],
										],
									],
								],
							],
						]
					),

					// Module - Only for Custom CSS.
					CssStyle::style(
						[
							'selector'  => $args['orderClass'],
							'attr'      => $attrs['css'] ?? [],
							'cssFields' => self::custom_css(),
						]
					),
				],
			]
		);
	}

	/**
	 * Get meta output
	 *
	 * This function is responsible for rendering the WooCommerce product meta HTML.
	 * It follows the pattern of the legacy ET_Builder_Module_Woocommerce_Meta::get_meta() method
	 * but uses the WooCommerceUtils class to render the template.
	 *
	 * @since ??
	 *
	 * @param array $args Additional arguments.
	 *
	 * @return string The rendered WooCommerce product meta HTML.
	 */
	public static function get_meta( array $args = [] ): string {
		// Needed for product post-type.
		if ( ! isset( $args['product'] ) ) {
			$args['product'] = WooCommerceUtils::get_product_id( 'current' );
		}

		// Render the full WooCommerce meta template.
		// Visibility control is handled via CSS classes applied by the frontend module classnames.
		$meta = WooCommerceUtils::render_module_template(
			'woocommerce_template_single_meta',
			$args
		);

		return $meta;
	}

	/**
	 * Style declaration for WooCommerce Product Meta separator content.
	 *
	 * This is the PHP equivalent of separatorContentStyleDeclaration from TypeScript.
	 * Generates CSS content property with separator value wrapped in spaces.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $attrValue The separator value.
	 *     @type bool   $important Optional. Whether to add `!important` tag. Default `false`.
	 * }
	 *
	 * @return string
	 */
	public static function separator_style_declaration( array $args ) {
		$attr_value = $args['attrValue'] ?? '';
		$important  = $args['important'] ?? false;

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => [
					'content' => true,
				],
			]
		);

		if ( ! empty( $attr_value ) ) {
			$style_declarations->add( 'content', '" ' . $attr_value . ' "' );
		}

		return $style_declarations->value();
	}

	/**
	 * Get the custom CSS fields for the Divi WooCommerceProductMeta module.
	 *
	 * This function retrieves the custom CSS fields defined for the Divi WooCommerceProductMeta module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /api/js/divi-module-library/functions/generateDefaultAttrs cssFields}
	 * located in `@divi/module-library`. Note that this function does not have
	 * a `label` property on each array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi WooCommerceProductMeta module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = CustomCssTrait::custom_css();
	 * // Returns an array of custom CSS fields for the WooCommerceProductMeta module.
	 * ```
	 */
	public static function custom_css(): array {
		$registered_block = WP_Block_Type_Registry::get_instance()->get_registered( 'divi/woocommerce-product-meta' );

		if ( ! $registered_block ) {
			return [];
		}

		$custom_css_fields = $registered_block->customCssFields;

		if ( ! is_array( $custom_css_fields ) ) {
			return [];
		}

		return $custom_css_fields;
	}

	/**
	 * Loads `WooCommerceProductMetaModule` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		/*
		 * Bail if  WooCommerce plugin is not active or the feature-flag `wooProductPageModules` is disabled.
		 */
		if ( ! et_is_woocommerce_plugin_active() ) {
			return;
		}

		// Add a filter for processing dynamic attribute defaults.
		add_filter(
			'divi_module_library_module_default_attributes_divi/woocommerce-product-meta',
			[ WooCommerceUtils::class, 'process_dynamic_attr_defaults' ],
			10,
			2
		);

		$module_json_folder_path = dirname( __DIR__, 5 ) . '/visual-builder/packages/module-library/src/components/woocommerce/product-meta/';

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
