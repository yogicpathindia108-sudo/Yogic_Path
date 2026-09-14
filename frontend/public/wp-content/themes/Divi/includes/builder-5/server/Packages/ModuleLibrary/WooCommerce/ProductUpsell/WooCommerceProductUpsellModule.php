<?php
/**
 * Module Library: WooCommerce Product Upsell Module
 *
 * This file contains the WooCommerceProductUpsellModule class which implements
 * functionality for displaying product upsells in WooCommerce stores using the
 * Divi Builder. It handles rendering, styling, and configuration of upsell products.
 *
 * @since ??
 * @package Divi
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductUpsell;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Framework\Utility\ArrayUtility;
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
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\StyleLibrary\Utils\Utils;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use ET\Builder\ThemeBuilder\WooCommerce\WooCommerceProductVariablePlaceholder;
use WP_Block_Type_Registry;
use WP_Block;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;

/**
 * WooCommerceProductUpsellModule class.
 *
 * This class implements the functionality for displaying product upsells
 * in a WooCommerce store. It provides functions for rendering the
 * WooCommerceProductUpsell module, handling product upsell display settings,
 * and managing related functionality.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class WooCommerceProductUpsellModule implements DependencyInterface {
	/**
	 * Holds prop values across static methods.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	public static $static_props;

	/**
	 * Number of products to be offset.
	 *
	 * @since ??
	 *
	 * @var int Default 0.
	 */
	public static $offset = 0;

	/**
	 * Render callback for the WooCommerceProductUpsell module.
	 *
	 * This function generates the HTML output for displaying product upsells on the frontend.
	 * It retrieves product upsell data based on the provided attributes, processes it,
	 * and returns the formatted HTML structure for the module.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ WooCommerceProductUpsellEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $content                     The block's content.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the WooCommerceProductUpsell module.
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
	 * WooCommerceProductUpsellModule::render_callback( $attrs, $content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements ): string {
		// Get breakpoints states info for dynamic access.
		$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
		$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
		$default_state           = $breakpoints_states_info->default_state();

		// Get parameters from attributes.
		$product_id      = $attrs['content']['advanced']['product'][ $default_breakpoint ][ $default_state ] ?? WooCommerceUtils::get_default_product();
		$loop_product_id = WooCommerceUtils::get_loop_context_product_id( $attrs, $block );
		if ( $loop_product_id > 0 && 'current' === $product_id ) {
			$product_id = $loop_product_id;
		}
		$posts_number   = $attrs['content']['advanced']['postsNumber'][ $default_breakpoint ][ $default_state ] ?? WooCommerceUtils::get_default_columns_posts_value();
		$columns_number = $attrs['content']['advanced']['columnsNumber'][ $default_breakpoint ][ $default_state ] ?? WooCommerceUtils::get_default_columns_posts_value();
		$orderby        = $attrs['content']['advanced']['orderby'][ $default_breakpoint ][ $default_state ] ?? null;
		$offset_number  = $attrs['content']['advanced']['offsetNumber'][ $default_breakpoint ][ $default_state ] ?? null;

		// Get the upsells HTML markup.
		$args = [
			'product' => $product_id,
		];

		if ( null !== $posts_number ) {
			$args['posts_number'] = $posts_number;
		}

		if ( null !== $columns_number ) {
			$args['columns_number'] = $columns_number;
		}

		if ( null !== $orderby ) {
			$args['orderby'] = $orderby;
		}

		if ( null !== $offset_number ) {
			$args['offset_number'] = $offset_number;
		}

		// Add shop and category page context handling.
		$is_shop                        = function_exists( 'is_shop' ) && is_shop();
		$is_wc_loop_prop_get_set_exists = function_exists( 'wc_get_loop_prop' ) && function_exists( 'wc_set_loop_prop' );
		$is_product_category            = function_exists( 'is_product_category' ) && is_product_category();

		// Set display type for shop and category pages.
		$display_type = null;
		if ( $is_shop ) {
			$display_type = WooCommerceUtils::set_display_type_to_render_only_products( 'woocommerce_shop_page_display' );
		} elseif ( $is_product_category ) {
			$display_type = WooCommerceUtils::set_display_type_to_render_only_products( 'woocommerce_category_archive_display' );
		}

		// Handle Customizer preview pane.
		// Refers: [https://github.com/elegantthemes/Divi/issues/17998#issuecomment-565955422].
		$is_filtered = null;
		if ( $is_wc_loop_prop_get_set_exists && is_customize_preview() ) {
			$is_filtered = wc_get_loop_prop( 'is_filtered' );
			wc_set_loop_prop( 'is_filtered', true );
		}

		// Pass conditional tags to handle VB context properly.
		$conditional_tags = [
			'is_tb' => et_builder_tb_enabled() ? 'true' : 'false',
		];

		$upsells_html = self::get_upsells( $args, $conditional_tags );

		// Reset customizer preview loop property.
		if ( $is_wc_loop_prop_get_set_exists && is_customize_preview() && isset( $is_filtered ) ) {
			wc_set_loop_prop( 'is_filtered', $is_filtered );
		}

		// Reset display types.
		if ( $is_shop && isset( $display_type ) ) {
			WooCommerceUtils::reset_display_type( 'woocommerce_shop_page_display', $display_type );
		} elseif ( $is_product_category && isset( $display_type ) ) {
			WooCommerceUtils::reset_display_type( 'woocommerce_category_archive_display', $display_type );
		}

		// Render an empty string if no output is generated to avoid unwanted vertical space.
		if ( '' === trim( $upsells_html ) ) {
			return '';
		}

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

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
							'children'          => $upsells_html,
						]
					),
				],
			]
		);
	}

	/**
	 * Generate classnames for the WooCommerceProductUpsell module.
	 *
	 * This function adds appropriate CSS classes to the module based on the provided
	 * attributes. These classes control the styling and appearance of the product upsells
	 * display. It handles text formatting options and element decoration properties.
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
	 *
	 * @example
	 * ```php
	 * $args = [
	 *     'classnamesInstance' => $classnamesInstance,
	 *     'attrs' => $attrs,
	 * ];
	 *
	 * WooCommerceProductUpsellModule::module_classnames($args);
	 * ```
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

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

		// Get breakpoints states info for dynamic access.
		$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
		$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
		$default_state           = $breakpoints_states_info->default_state();

		/*
		 * Apply CSS classes based on element visibility settings.
		 *
		 * These classes control the display of product elements (name, image, etc.) and are added
		 * for the default breakpoint/state to prevent layout shift on a page load. The same classes
		 * are dynamically applied for all breakpoints/states via MultiViewScriptData.
		 */
		$show_name = $attrs['elements']['advanced']['showName'][ $default_breakpoint ][ $default_state ] ?? null;
		if ( 'off' === $show_name ) {
			$classnames_instance->add( 'et_pb_wc_upsells_no_name' );
		}

		$show_image = $attrs['elements']['advanced']['showImage'][ $default_breakpoint ][ $default_state ] ?? null;
		if ( 'off' === $show_image ) {
			$classnames_instance->add( 'et_pb_wc_upsells_no_image' );
		}

		$show_price = $attrs['elements']['advanced']['showPrice'][ $default_breakpoint ][ $default_state ] ?? null;
		if ( 'off' === $show_price ) {
			$classnames_instance->add( 'et_pb_wc_upsells_no_price' );
		}

		$show_rating = $attrs['elements']['advanced']['showRating'][ $default_breakpoint ][ $default_state ] ?? null;
		if ( 'off' === $show_rating ) {
			$classnames_instance->add( 'et_pb_wc_upsells_no_rating' );
		}

		$show_sale_badge = $attrs['elements']['advanced']['showSaleBadge'][ $default_breakpoint ][ $default_state ] ?? null;
		if ( 'off' === $show_sale_badge ) {
			$classnames_instance->add( 'et_pb_wc_upsells_no_sale_badge' );
		}
	}

	/**
	 * Sets up script data for the WooCommerceProductUpsell module.
	 *
	 * This function prepares JavaScript data that will be used by the frontend
	 * to enable interactive features and dynamic behavior for the product upsells display.
	 * It ensures proper initialization of module elements and their associated scripts.
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
							'et_pb_wc_upsells_no_name' => $attrs['elements']['advanced']['showName'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_wc_upsells_no_image' => $attrs['elements']['advanced']['showImage'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_wc_upsells_no_price' => $attrs['elements']['advanced']['showPrice'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_wc_upsells_no_rating' => $attrs['elements']['advanced']['showRating'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_wc_upsells_no_sale_badge' => $attrs['elements']['advanced']['showSaleBadge'] ?? [],
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
	 * Star rating style declaration.
	 *
	 * This function is responsible for declaring the star rating styles for the WooCommerce Product Upsell module.
	 * It handles letter spacing width calculations and text alignment margins.
	 *
	 * This function is the equivalent of the `starRatingStyleDeclaration` JS function located in
	 * visual-builder/packages/module-library/src/components/woocommerce/product-upsell/style-declarations/star-rating/index.ts.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array  $attrValue Optional. The value (breakpoint > state > value) of the module attribute. Default `[]`.
	 * }
	 *
	 * @return string The value of the star rating style declaration.
	 *
	 * @example:
	 * ```php
	 * $params = [
	 *     'attrValue' => [
	 *         'letterSpacing' => '2px',
	 *         'textAlign' => 'center',
	 *     ],
	 *     'important' => [
	 *         'margin-left' => true,
	 *         'margin-right' => true,
	 *     ],
	 *     'returnType' => 'string',
	 * ];
	 *
	 * WooCommerceProductUpsellModule::star_rating_style_declaration($params);
	 * ```
	 */
	public static function star_rating_style_declaration( array $params ): string {
		$attr_value     = $params['attrValue'] ?? [];
		$letter_spacing = $attr_value['letterSpacing'] ?? '';
		$text_align     = $attr_value['textAlign'] ?? '';

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => [
					'margin-left'  => true,
					'margin-right' => true,
				],
			]
		);

		// Handle letter spacing width calculation - D4 behavior: calc(5.4em + (letterSpacing * 4)).
		if ( ! empty( $letter_spacing ) ) {
			$letter_spacing_numeric = SanitizerUtility::numeric_parse_value( $letter_spacing );
			$letter_spacing_value   = $letter_spacing_numeric['valueNumber'] ?? 0;
			$letter_spacing_unit    = $letter_spacing_numeric['valueUnit'] ?? 'px';

			// Convert to consistent unit for calculation.
			if ( 'em' === $letter_spacing_unit || 'rem' === $letter_spacing_unit ) {
				$width_value = sprintf( 'calc(5.4em + (%s * 4))', $letter_spacing );
			} else {
				// For px and other units, convert to em equivalent for the calculation.
				$width_value = sprintf( 'calc(5.4em + (%spx * 4))', $letter_spacing_value );
			}

			$style_declarations->add( 'width', $width_value );
		}

		// Handle text alignment margins - D4 behavior with selective !important.
		if ( ! empty( $text_align ) ) {
			switch ( $text_align ) {
				case 'center':
					$style_declarations->add( 'margin-left', 'auto' );
					$style_declarations->add( 'margin-right', 'auto' );
					break;
				case 'right':
					$style_declarations->add( 'margin-left', 'auto' );
					$style_declarations->add( 'margin-right', '0' );
					break;
				case 'left':
				case 'justify':
				default:
					$style_declarations->add( 'margin-left', '0' );
					$style_declarations->add( 'margin-right', 'auto' );
					break;
			}
		}

		return $style_declarations->value();
	}

	/**
	 * Defines and applies styles for the WooCommerceProductUpsell module.
	 *
	 * This function generates and applies CSS styles for various components of the
	 * product upsells display, including the module container, title, image, price,
	 * rating, and sale badge. It ensures consistent styling across different parts
	 * of the module based on the provided attributes.
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
											'propertySelectors' => [
												'textShadow' => [
													'desktop' => [
														'value' => [
															'text-shadow' => "{$order_class} ul.products h3, {$order_class} ul.products  h1, {$order_class} ul.products  h2, {$order_class} ul.products  h4, {$order_class} ul.products  h5, {$order_class} ul.products  h6, {$order_class} ul.products .price, {$order_class} ul.products .price .amount",
														],
													],
												],
											],
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
					// Image.
					$elements->style(
						[
							'attrName'   => 'image',
							'styleProps' => [
								'background'     => [
									'selector' => "{$order_class}.et_pb_module .et_shop_image",
								],
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class}.et_pb_module .et_shop_image",
											'attr'     => $attrs['image']['decoration']['border'] ?? [],
											'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
										],
									],
								],
							],
						]
					),
					// Overlay.
					$elements->style(
						[
							'attrName' => 'overlay',
						]
					),
					// Overlay Icon.
					$elements->style(
						[
							'attrName' => 'overlayIcon',
						]
					),
					// Price.
					$elements->style(
						[
							'attrName' => 'price',
						]
					),
					// Product Title.
					$elements->style(
						[
							'attrName' => 'productTitle',
						]
					),
					// Rating.
					$elements->style(
						[
							'attrName'   => 'rating',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} ul.products li.product .star-rating",
											'attr'     => $attrs['rating']['decoration']['font']['font'] ?? [],
											'declarationFunction' => [ self::class, 'star_rating_style_declaration' ],
										],
									],
								],
							],
						]
					),
					// Sale Badge.
					$elements->style(
						[
							'attrName'   => 'saleBadge',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} span.onsale",
											'attr'     => $attrs['saleBadge']['decoration']['border'] ?? [],
											'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
										],
									],
								],
							],
						]
					),
					// Sale Price.
					$elements->style(
						[
							'attrName' => 'salePrice',
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
	 * Retrieves custom CSS fields for the WooCommerceProductUpsell module.
	 *
	 * This function returns the custom CSS field definitions that allow users to
	 * apply custom styling to specific elements within the product upsells display.
	 * These fields are used by the Divi Builder's custom CSS feature to target
	 * specific parts of the module for styling.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /api/js/divi-module-library/functions/generateDefaultAttrs cssFields}
	 * located in `@divi/module-library`. Note that this function does not have
	 * a `label` property on each array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi WooCommerceProductUpsell module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = CustomCssTrait::custom_css();
	 * // Returns an array of custom CSS fields for the WooCommerceProductUpsell module.
	 * ```
	 */
	public static function custom_css(): array {
		return WP_Block_Type_Registry::get_instance()->get_registered( 'divi/woocommerce-product-upsell' )->customCssFields;
	}


	/**
	 * Initializes the WooCommerceProductUpsell module functionality.
	 *
	 * This function performs the necessary setup for the module, including:
	 * - Checking if WooCommerce is active
	 * - Adding filters for processing dynamic attribute defaults
	 * - Registering the module with the appropriate render callback
	 *
	 * It ensures the module is properly integrated with the Divi Builder system
	 * and can be used in the Visual Builder.
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
			'divi_module_library_module_default_attributes_divi/woocommerce-product-upsell',
			[ WooCommerceUtils::class, 'process_dynamic_attr_defaults' ],
			10,
			2
		);

		$module_json_folder_path = dirname( __DIR__, 5 ) . '/visual-builder/packages/module-library/src/components/woocommerce/product-upsell/';

		// Ensure that all filters and actions applied during module registration are registered before calling `ModuleRegistration::register_module()`.
		// However, for consistency, register all module-specific filters and actions prior to invoking `ModuleRegistration::register_module()`.
		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}

	/**
	 * Retrieves user-configured display settings for product upsells.
	 *
	 * This function extracts the user-defined configuration for how upsell products
	 * should be displayed, including:
	 * - Number of products to show (posts_per_page)
	 * - Number of columns to display (columns)
	 * - Product ordering method (orderby)
	 *
	 * When settings are not explicitly defined, it applies appropriate default values.
	 * The configuration is stored in the static_props variable, which is populated
	 * by the get_upsells() method.
	 *
	 * @since ??
	 *
	 * @return array An array of display configuration parameters for upsell products.
	 */
	public static function get_selected_upsell_display_args(): array {
		$selected_args = [];

		$selected_args['posts_per_page'] = ArrayUtility::get_value(
			self::$static_props,
			'posts_number',
			''
		);
		$selected_args['columns']        = ArrayUtility::get_value(
			self::$static_props,
			'columns_number',
			''
		);
		$orderby                         = ArrayUtility::get_value(
			self::$static_props,
			'orderby',
			''
		);

		// Process orderby values - convert 'price-desc' format to WooCommerce format.
		if ( ! empty( $orderby ) ) {
			if ( in_array( $orderby, [ 'price-desc', 'date-desc' ], true ) ) {
				// Convert 'price-desc' → 'price' and set order to 'desc'.
				$selected_args['orderby'] = str_replace( '-desc', '', $orderby );
				$selected_args['order']   = 'desc';
			} elseif ( in_array( $orderby, [ 'price', 'date' ], true ) ) {
				$selected_args['orderby'] = $orderby;
				$selected_args['order']   = 'asc';
			} else {
				$selected_args['orderby'] = $orderby;
			}
		}

		// Set default values when parameters are empty.
		$default = WooCommerceUtils::get_default_columns_posts_value();

		if ( empty( $selected_args['posts_per_page'] ) ) {
			$selected_args['posts_per_page'] = $default;
		}
		if ( empty( $selected_args['columns'] ) ) {
			$selected_args['columns'] = $default;
		}

		$selected_args = array_filter( $selected_args, 'strlen' );

		return $selected_args;
	}

	/**
	 * Applies user-configured display settings to WooCommerce upsell arguments.
	 *
	 * This function serves as a filter callback that merges user-defined display
	 * settings with WooCommerce's default upsell display arguments. It ensures that
	 * the module's custom configuration for posts per page, columns, and ordering
	 * is properly applied to the WooCommerce upsell display.
	 *
	 * It works by retrieving the user settings via get_selected_upsell_display_args()
	 * and then merging them with the provided default arguments.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of default WooCommerce upsell display arguments.
	 *
	 *     @type string $posts_per_page Optional. The number of posts per page. Default '-1'.
	 *     @type string $columns        Optional. The number of columns. Default '4'.
	 *     @type string $orderby        Optional. The order by. Default 'rand'.
	 * }
	 *
	 * @return array The merged arguments with user settings taking precedence.
	 */
	public static function set_upsell_display_args( array $args ): array {
		$selected_args = self::get_selected_upsell_display_args();

		return wp_parse_args( $selected_args, $args );
	}

	/**
	 * Applies offset to upsell product IDs.
	 *
	 * This function serves as a filter callback for the `woocommerce_product_get_upsell_ids`
	 * hook to properly implement offset functionality for upsell products. Unlike the previous
	 * approach that used the `woocommerce_shortcode_products_query` filter (which is not
	 * triggered by `woocommerce_upsell_display`), this method directly modifies the upsell
	 * IDs array before WooCommerce processes them.
	 *
	 * The offset value is stored in the class's static $offset property and is applied
	 * using PHP's `array_slice()` function to skip the specified number of products.
	 *
	 * @since ??
	 *
	 * @param array      $upsell_ids Array of upsell product IDs.
	 * @param WC_Product $product    The product object.
	 *
	 * @return array The upsell IDs with offset applied.
	 */
	public static function apply_offset_to_upsell_ids( array $upsell_ids, $product ): array {
		if ( empty( $upsell_ids ) || null === self::$offset || '' === self::$offset || self::$offset <= 0 ) {
			return $upsell_ids;
		}

		// Apply offset using array_slice to skip the specified number of products.
		return array_slice( $upsell_ids, self::$offset );
	}

	/**
	 * Generates HTML markup for product upsells with configurable display options.
	 *
	 * This function is the core implementation for retrieving and displaying product
	 * upsells in the WooCommerce Product Upsell module. It handles:
	 *
	 * 1. Special handling for Theme Builder mode, providing placeholder content when needed
	 * 2. Setting up proper product context based on provided product ID or current product
	 * 3. Applying offset settings for pagination
	 * 4. Configuring display parameters (columns, number of products, ordering)
	 * 5. Rendering the final HTML output using WooCommerce templates
	 *
	 * The function manages all necessary WordPress filters to ensure proper integration
	 * with WooCommerce, and cleans up after itself by removing filters when done.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Optional. An array of arguments for configuring the product upsells display.
	 *
	 *     @type int|string $product       Optional. The product ID or 'current' for current product.
	 *     @type int        $posts_number  Optional. The number of upsell products to display. Default 4.
	 *     @type int        $columns_number Optional. The number of columns to display. Default 4.
	 *     @type string     $orderby       Optional. How to order the products. Default 'date'.
	 *                                     Accepts 'price', 'date', etc.
	 *     @type string     $order         Optional. Sort order direction. Default 'desc'.
	 *     @type int        $offset_number Optional. Number of products to skip. Default 0.
	 * }
	 *
	 * @param array $conditional_tags {
	 *     Optional. An array of conditional tags for special rendering contexts.
	 *
	 *     @type string $is_tb Optional. Whether the theme builder is enabled. Default 'false'.
	 * }
	 *
	 * @return string The rendered HTML markup for product upsells.
	 *
	 * @example
	 * ```php
	 * // Get upsells for current product with default settings
	 * $upsells = WooCommerceProductUpsellModule::get_upsells();
	 *
	 * // Get upsells for a specific product with custom settings
	 * $upsells = WooCommerceProductUpsellModule::get_upsells([
	 *     'product' => 123,
	 *     'posts_number' => 3,
	 *     'columns_number' => 3,
	 *     'orderby' => 'price'
	 * ]);
	 * ```
	 */
	public static function get_upsells( array $args = [], array $conditional_tags = [] ): string {
		self::$static_props = $args;

		// Needed for product post-type.
		if ( ! isset( $args['product'] ) ) {
			$args['product'] = WooCommerceUtils::get_product_id( 'current' );
		}

		$offset_number = ArrayUtility::get_value( $args, 'offset_number', 0 );

		$is_tb = ArrayUtility::get_value( $conditional_tags, 'is_tb', false );

		// Force set product's class to WooCommerceProductVariablePlaceholder in TB
		// (via `woocommerce_product_class` filter) so related product can output visible content based on pre-filled value in TB.
		// Note: sanitize_text_fields() converts booleans to strings ('1' for true, '' for false),
		// so we check for both boolean true and string representations.
		$added_product_class_filter = false;

		if ( in_array( $is_tb, [ true, '1', 'true', 1 ], true ) || is_et_pb_preview() ) {
			// Ensure product class filters are removed before querying upsells to avoid recursion.
			remove_filter( 'woocommerce_product_class', 'et_theme_builder_wc_product_class' );
			remove_filter( 'woocommerce_product_class', [ WooCommerceUtils::class, 'divi_theme_builder_wc_product_class' ] );

			// Set upsells id; adjust it with module's arguments. This is specifically needed if
			// the module is fetching the value via REST API because some fields no longer use default value.
			WooCommerceProductVariablePlaceholder::set_tb_upsells_ids(
				[
					'limit' => ArrayUtility::get_value( $args, 'posts_number', 4 ),
				]
			);

			add_filter( 'woocommerce_product_class', [ WooCommerceUtils::class, 'divi_theme_builder_wc_product_class' ] );
			$added_product_class_filter = true;
		}

		$is_offset_valid = absint( $offset_number ) > 0;

		if ( $is_offset_valid ) {
			self::$offset = $offset_number;

			// Remove the legacy filter.
			remove_filter(
				'woocommerce_shortcode_products_query',
				[ 'ET_Builder_Module_Woocommerce_Upsells', 'append_offset' ]
			);

			// Add filter to properly apply offset to upsell IDs before display.
			add_filter( 'woocommerce_product_get_upsell_ids', [ self::class, 'apply_offset_to_upsell_ids' ], 10, 2 );
		}

		// Remove the legacy filter.
		remove_filter(
			'woocommerce_upsell_display_args',
			[ 'ET_Builder_Module_Woocommerce_Upsells', 'set_upsell_display_args' ]
		);

		add_filter(
			'woocommerce_upsell_display_args',
			[ self::class, 'set_upsell_display_args' ]
		);

		// NOTE: Orderby processing is now handled in get_selected_upsell_display_args()
		// to prevent the woocommerce_upsell_display_args filter from overwriting our values.

		// Check if we need custom popularity sorting.
		$orderby = self::$static_props['orderby'] ?? '';

		if ( 'popularity' === $orderby ) {
			// Use custom product list rendering with proper popularity sorting.
			$display_args = self::get_selected_upsell_display_args();
			$output       = WooCommerceUtils::render_products_sorted_by_popularity( $args, $display_args );
		} else {
			$output = WooCommerceUtils::render_module_template( 'woocommerce_upsell_display', $args );
		}

		remove_filter(
			'woocommerce_upsell_display_args',
			[ self::class, 'set_upsell_display_args' ]
		);

		if ( $is_offset_valid ) {
			remove_filter( 'woocommerce_product_get_upsell_ids', [ self::class, 'apply_offset_to_upsell_ids' ], 10 );

			self::$offset = 0;
		}

		if ( $added_product_class_filter ) {
			remove_filter( 'woocommerce_product_class', [ WooCommerceUtils::class, 'divi_theme_builder_wc_product_class' ] );
		}

		return $output;
	}
}
