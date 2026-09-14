<?php
/**
 * Module Library: WooCommerceRelatedProducts Module
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\RelatedProducts;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block
// phpcs:disable ElegantThemes.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

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
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_Block_Type_Registry;
use WP_Block;
use ET\Builder\Packages\Module\Options\BoxShadow\BoxShadowClassnames;
use ET\Builder\Packages\Module\Options\BoxShadow\BoxShadowOverlay;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;

/**
 * WooCommerceRelatedProductsModule class.
 *
 * This class implements the functionality of a call-to-action component
 * in a frontend application. It provides functions for rendering the
 * WooCommerceRelatedProducts module,
 * managing REST API endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class WooCommerceRelatedProductsModule implements DependencyInterface {
	/**
	 * Context transient TTL for related-products cache fingerprinting.
	 *
	 * Keep this bounded so context entries do not become non-expiring/autoloaded options.
	 *
	 * @var int
	 */
	const RELATED_PRODUCTS_CONTEXT_CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * Static properties for the WooCommerceRelatedProducts module.
	 *
	 * These static properties are used across static methods of this class.
	 *
	 * @var array
	 */
	public static $static_props = [];

	/**
	 * Number of products to be offset.
	 *
	 * @var int Default 0.
	 */
	public static $offset = 0;

	/**
	 * Cache for thumbnail overlay attributes.
	 *
	 * @var array
	 */
	private static $_thumbnail_overlay_attr = [];

	/**
	 * Render callback for the WooCommerceRelatedProducts module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ WooCommerceRelatedProductsEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $content                     The block's content.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the WooCommerceRelatedProducts module.
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
	 * WooCommerceRelatedProductsModule::render_callback( $attrs, $content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements ): string {
		// Extract parameters from attributes - following the same pattern as the Controller.
		$product_id      = $attrs['content']['advanced']['product']['desktop']['value'] ?? WooCommerceUtils::get_default_product();
		$loop_product_id = WooCommerceUtils::get_loop_context_product_id( $attrs, $block );
		if ( $loop_product_id > 0 && 'current' === $product_id ) {
			$product_id = $loop_product_id;
		}

		$include_categories = $attrs['content']['advanced']['includeCategories']['desktop']['value'] ?? [];
		$show_price         = $attrs['content']['advanced']['showPrice']['desktop']['value'] ?? 'on';
		$offset_number      = $attrs['content']['advanced']['offsetNumber']['desktop']['value'] ?? 0;
		$posts_number       = $attrs['content']['advanced']['postsNumber']['desktop']['value'] ?? '';
		$columns_number     = $attrs['content']['advanced']['columnsNumber']['desktop']['value'] ?? '';
		$orderby            = $attrs['content']['advanced']['orderby']['desktop']['value'] ?? '';

		// Sanitize numeric parameters to handle invalid data gracefully.
		// Follows the same pattern as get_related_products() which uses absint().
		if ( ! is_numeric( $offset_number ) ) {
			$offset_number = 0;
		}
		if ( ! is_numeric( $posts_number ) && '' !== $posts_number ) {
			$posts_number = '';
		}
		if ( ! is_numeric( $columns_number ) && '' !== $columns_number ) {
			$columns_number = '';
		}

		// Build args array for get_related_products().
		$args = [ 'product' => $product_id ];

		if ( ! empty( $include_categories ) ) {
			$args['include_categories'] = $include_categories;
		}
		if ( ! empty( $show_price ) ) {
			$args['show_price'] = $show_price;
		}
		if ( ! empty( $offset_number ) ) {
			$args['offset_number'] = $offset_number;
		}
		if ( 0 === $posts_number || ! empty( $posts_number ) ) {
			$args['posts_number'] = $posts_number;
		}
		if ( ! empty( $columns_number ) ) {
			$args['columns_number'] = $columns_number;
		}
		if ( ! empty( $orderby ) ) {
			$args['orderby'] = $orderby;
		}

		// Add image attributes for box shadow overlay support.
		$args['image'] = $attrs['image'] ?? [];

		// Get the related products HTML markup.
		$related_products_html = self::get_related_products( $args, [] );

		// Render empty string if no output is generated to avoid unwanted vertical space.
		if ( '' === $related_products_html ) {
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
							'children'          => $related_products_html,
						]
					),
				],
			]
		);
	}

	/**
	 * Style declaration for WooCommerce Related Products star rating styles.
	 *
	 * Handles letter spacing width calculation and text alignment margins for star ratings.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     Declaration function parameters.
	 *
	 *     @type array  $attrValue  Attribute value containing letterSpacing and textAlign.
	 *     @type string $breakpoint Current breakpoint (desktop, tablet, phone).
	 * }
	 *
	 * @return string CSS declarations.
	 *
	 * @example
	 * ```php
	 * $params = [
	 *     'attrValue' => [
	 *         'letterSpacing' => '2px',
	 *         'textAlign'     => 'center',
	 *     ],
	 *     'breakpoint' => 'desktop',
	 * ];
	 *
	 * $css = WooCommerceRelatedProductsModule::star_rating_style_declaration($params);
	 * // Returns: 'width: calc(5.4em + (2px * 4)); margin-left: auto !important; margin-right: auto !important;'
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
	 * Generate classnames for the module.
	 *
	 * This function generates classnames for the module based on the provided
	 * arguments. It is used in the `render_callback` function of the WooCommerceRelatedProducts module.
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
	 * WooCommerceRelatedProductsModule::module_classnames($args);
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

		$show_name       = $attrs['elements']['advanced']['showName'][ $default_breakpoint ][ $default_state ] ?? 'on';
		$show_image      = $attrs['elements']['advanced']['showImage'][ $default_breakpoint ][ $default_state ] ?? 'on';
		$show_price      = $attrs['elements']['advanced']['showPrice'][ $default_breakpoint ][ $default_state ] ?? 'on';
		$show_rating     = $attrs['elements']['advanced']['showRating'][ $default_breakpoint ][ $default_state ] ?? 'on';
		$show_sale_badge = $attrs['elements']['advanced']['showSaleBadge'][ $default_breakpoint ][ $default_state ] ?? 'on';

		// Add conditional CSS classes based on element visibility settings.
		if ( 'on' !== $show_name ) {
			$classnames_instance->add( 'et_pb_wc_related_products_no_name' );
		}

		if ( 'on' !== $show_image ) {
			$classnames_instance->add( 'et_pb_wc_related_products_no_image' );
		}

		if ( 'on' !== $show_price ) {
			$classnames_instance->add( 'et_pb_wc_related_products_no_price' );
		}

		if ( 'on' !== $show_rating ) {
			$classnames_instance->add( 'et_pb_wc_related_products_no_rating' );
		}

		if ( 'on' !== $show_sale_badge ) {
			$classnames_instance->add( 'et_pb_wc_related_products_no_sale_badge' );
		}
	}

	/**
	 * WooCommerceRelatedProducts module script data.
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
	 * WooCommerceRelatedProductsModule::module_script_data( $args );
	 * ```
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

		// Add responsive class names for element show/hide settings.
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
							'et_pb_wc_related_products_no_name' => $attrs['elements']['advanced']['showName'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_wc_related_products_no_image' => $attrs['elements']['advanced']['showImage'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_wc_related_products_no_price' => $attrs['elements']['advanced']['showPrice'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_wc_related_products_no_rating' => $attrs['elements']['advanced']['showRating'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_wc_related_products_no_sale_badge' => $attrs['elements']['advanced']['showSaleBadge'] ?? [],
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
	 * WooCommerceRelatedProducts Module's style components.
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
								'border'         => [
									'propertySelectors' => [
										'desktop' => [
											'hover' => [
												'border-radius' => "{$order_class}.et_pb_module .et_shop_image > img{{:hover}}, {$order_class}.et_pb_module .et_shop_image .et_overlay",
											],
											'value' => [
												'border-radius' => "{$order_class}.et_pb_module .et_shop_image > img, {$order_class}.et_pb_module .et_shop_image .et_overlay",
												'border-style'  => "{$order_class}.et_pb_module .et_shop_image > img, {$order_class}.et_pb_module .et_shop_image .et_overlay",
											],
										],
									],
									'important'         => true,
								],
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class}.et_pb_module .et_shop_image > img, {$order_class}.et_pb_module .et_shop_image .et_overlay",
											'attr'     => $attrs['image']['decoration']['border'] ?? [],
											'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
										],
									],
								],
							],
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
							'attrName' => 'saleBadge',
						]
					),
					// Sale Badge Text.
					$elements->style(
						[
							'attrName' => 'saleBadgeText',
						]
					),
					// Sale Price.
					$elements->style(
						[
							'attrName' => 'salePrice',
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
	 * Get the custom CSS fields for the Divi WooCommerceRelatedProducts module.
	 *
	 * This function retrieves the custom CSS fields defined for the Divi WooCommerceRelatedProducts module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /api/js/divi-module-library/functions/generateDefaultAttrs cssFields}
	 * located in `@divi/module-library`. Note that this function does not have
	 * a `label` property on each array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi WooCommerceRelatedProducts module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = CustomCssTrait::custom_css();
	 * // Returns an array of custom CSS fields for the WooCommerceRelatedProducts module.
	 * ```
	 */
	public static function custom_css(): array {
		$registered_block = WP_Block_Type_Registry::get_instance()->get_registered( 'divi/woocommerce-related-products' );

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
	 * Loads `WooCommerceRelatedProductsModule` and registers Front-End render callback and REST API Endpoints.
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
			'divi_module_library_module_default_attributes_divi/woocommerce-related-products',
			[ WooCommerceUtils::class, 'process_dynamic_attr_defaults' ],
			10,
			2
		);

		$module_json_folder_path = dirname( __DIR__, 5 ) . '/visual-builder/packages/module-library/src/components/woocommerce/related-products/';

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
	 * Filters the related product category IDs.
	 *
	 * @since ??
	 *
	 * @param array $term_ids Term IDs.
	 *
	 * @return array
	 */
	public static function set_related_products_categories( array $term_ids ): array {
		$normalized_include_categories = self::_normalize_include_categories(
			ArrayUtility::get_value( self::$static_props, 'include_categories', [] )
		);
		$include_mode                  = $normalized_include_categories['mode'];
		$include_cats                  = $normalized_include_categories['categories'];

		if ( 'all' === $include_mode ) {
			return self::_get_all_product_category_ids();
		}

		// WooCommerce by default handles Current Category based on the global $product.
		if ( 'current' === $include_mode || 'none' === $include_mode ) {
			return $term_ids;
		}

		// Return user-selected categories.
		return $include_cats;
	}

	/**
	 * Normalize include_categories values from module attributes.
	 *
	 * @since ??
	 *
	 * @param mixed $include_categories Raw include categories value.
	 *
	 * @return array{
	 *     mode: string,
	 *     categories: array
	 * }
	 */
	private static function _normalize_include_categories( $include_categories ): array {
		$normalized_include_categories = [
			'mode'       => 'none',
			'categories' => [],
		];

		if ( ! is_array( $include_categories ) ) {
			if ( 'all' === $include_categories ) {
				$normalized_include_categories['mode'] = 'all';
			} elseif ( 'current' === $include_categories ) {
				$normalized_include_categories['mode'] = 'current';
			} elseif ( is_string( $include_categories ) && '' !== $include_categories ) {
				$normalized_include_categories['mode']       = 'explicit';
				$normalized_include_categories['categories'] = [ $include_categories ];
			}

			return $normalized_include_categories;
		}

		if ( in_array( 'all', $include_categories, true ) ) {
			$normalized_include_categories['mode'] = 'all';

			return $normalized_include_categories;
		}

		$selected_categories = array_values(
			array_filter(
				$include_categories,
				static function ( $category ) {
					return is_scalar( $category ) && 'current' !== $category && '' !== (string) $category;
				}
			)
		);

		if ( empty( $selected_categories ) && in_array( 'current', $include_categories, true ) ) {
			$normalized_include_categories['mode'] = 'current';

			return $normalized_include_categories;
		}

		if ( ! empty( $selected_categories ) ) {
			$normalized_include_categories['mode']       = 'explicit';
			$normalized_include_categories['categories'] = $selected_categories;
		}

		return $normalized_include_categories;
	}

	/**
	 * Builds a deterministic fingerprint for related-products category context.
	 *
	 * @since ??
	 *
	 * @param array $normalized_include_categories Normalized include categories payload.
	 *
	 * @return string
	 */
	private static function _get_related_products_cache_context_fingerprint( array $normalized_include_categories ): string {
		$include_mode = $normalized_include_categories['mode'] ?? 'none';

		if ( 'explicit' !== $include_mode ) {
			return $include_mode;
		}

		$selected_categories = array_map( 'strval', $normalized_include_categories['categories'] ?? [] );

		sort( $selected_categories, SORT_STRING );

		return $include_mode . ':' . implode( ',', $selected_categories );
	}

	/**
	 * Invalidates WooCommerce related-products cache when category context changes.
	 *
	 * @since ??
	 *
	 * @param int   $product_id                   Current product ID.
	 * @param array $normalized_include_categories Normalized include categories payload.
	 *
	 * @return void
	 */
	private static function _maybe_invalidate_related_products_cache( int $product_id, array $normalized_include_categories ): void {
		if ( 0 >= $product_id ) {
			return;
		}

		$context_transient_name = 'et_wc_related_context_' . $product_id;
		$context_fingerprint    = self::_get_related_products_cache_context_fingerprint( $normalized_include_categories );
		$cached_context         = get_transient( $context_transient_name );

		if ( $context_fingerprint === $cached_context ) {
			return;
		}

		delete_transient( 'wc_related_' . $product_id );
		set_transient( $context_transient_name, $context_fingerprint, self::RELATED_PRODUCTS_CONTEXT_CACHE_TTL );
	}

	/**
	 * Get all product category IDs.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	private static function _get_all_product_category_ids(): array {
		$category_ids = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'fields'     => 'ids',
				'hide_empty' => false,
			]
		);

		if ( is_wp_error( $category_ids ) || ! is_array( $category_ids ) ) {
			return [];
		}

		return array_map( 'absint', $category_ids );
	}

	/**
	 * Applies offset to the related products array.
	 *
	 * This method uses array_slice to remove the specified number of products
	 * from the beginning of the related products array, effectively implementing
	 * the offset functionality that WooCommerce's query offset doesn't support
	 * for related products.
	 *
	 * @since ??
	 *
	 * @param array $related_posts Array of related product IDs.
	 * @param int   $product_id    Current product ID.
	 * @param array $args          WooCommerce related products arguments.
	 *
	 * @return array Modified array of related product IDs with offset applied.
	 */
	public static function apply_related_products_offset( $related_posts, $product_id, $args ) {
		if ( ! is_array( $related_posts ) || 0 === self::$offset ) {
			return $related_posts;
		}

		// Apply offset using array_slice to remove products from the beginning.
		return array_slice( $related_posts, self::$offset );
	}


	/**
	 * Returns the user selected posts-per-page, columns and order-by values for WooCommerce.
	 *
	 * This function merges the user selected values with the provided arguments.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments {@see woocommerce_output_related_products()}.
	 *
	 *     @type string $posts_per_page Optional. The number of posts per page. Default '-1'.
	 *     @type string $columns        Optional. The number of columns. Default '4'.
	 * }
	 *
	 * @return array The selected args.
	 */
	public static function set_related_products_args( $args ) {
		$selected_args = self::get_selected_related_product_args();

		return wp_parse_args( $selected_args, $args );
	}

	/**
	 * Gets the user set posts-per-page, columns and order-by values.
	 *
	 * This function is used to get the user set posts-per-page, columns and order-by values.
	 * Default values are set when parameters are empty, and are retrieved from {@see WooCommerceUtils::get_columns_posts_default_value()}.
	 *
	 * The static variable used in this method is set by {@see WooCommerceProductUpsellModule::get_related_products()}.
	 *
	 * @since ??
	 *
	 * @return array The selected args.
	 */
	public static function get_selected_related_product_args(): array {
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
		$selected_args['orderby']        = ArrayUtility::get_value(
			self::$static_props,
			'orderby',
			''
		);

		// Set default values when parameters are empty.
		$default = WooCommerceUtils::get_default_columns_posts_value();

		if ( empty( $selected_args['posts_per_page'] ) ) {
			$selected_args['posts_per_page'] = $default;
		}
		if ( empty( $selected_args['columns'] ) ) {
			$selected_args['columns'] = $default;
		}

		$selected_args = array_filter( $selected_args, 'strlen' );

		if ( isset( $selected_args['orderby'] ) ) {
			$orderby = $selected_args['orderby'];

			if ( in_array( $orderby, [ 'price-desc', 'date-desc' ], true ) ) {
				// For the list of all allowed orderby values, refer to {@see wc_products_array_orderby}.
				$selected_args['orderby'] = str_replace( '-desc', '', $orderby );
			} else {
				// Implicitly specify when ascending is required since `desc` is the default value. {@see woocommerce_related_products()}.
				$selected_args['order'] = 'asc';
			}
		}

		return $selected_args;
	}

	/**
	 * Retrieves the related products for a given set of arguments.
	 *
	 * This renders the module template for the related products based on the provided arguments.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Optional. An array of arguments for rendering the related products.
	 *
	 *     @type string $product_id         Optional. The product identifier. Default 'current'.
	 *     @type int $offset_number         Optional. The number of products to offset. Default 0.
	 *     @type string $include_categories Optional. The categories to include. Default ''.
	 *     @type string $show_price         Optional. Whether to show the price. Default 'on' (on).
	 * }
	 *
	 * @param array $conditional_tags {
	 *     Optional. An array of conditional tags.
	 *
	 *     @type bool $is_tb Optional. Whether the theme builder is enabled. Default false.
	 * }
	 *
	 * @return string The rendered related products or a placeholder if in theme builder mode.
	 *
	 * @example:
	 * ```php
	 * $title = WooCommerceRelatedProductsModule::get_related_products();
	 * // Returns the related products for the current product.
	 *
	 * $title = WooCommerceRelatedProductsModule::get_related_products( [ 'product' => 123 ] );
	 * // Returns the related products for the product with ID 123.
	 * ```
	 */
	public static function get_related_products( array $args = [], array $conditional_tags = [] ): string {
		/*
		 * User selected posts-per-page, columns and orderby values are passed to WooCommerce
		 * using the `woocommerce_output_related_products_args` filter.
		 * Since we cannot directly pass the `$args` as argument to the filter,
		 * we pass them via a static variable.
		 */
		self::$static_props = $args;

		// Needed for product post-type.
		if ( ! isset( $args['product'] ) ) {
			$args['product'] = WooCommerceUtils::get_product_id( 'current' );
		}

		$normalized_include_categories = self::_normalize_include_categories( ArrayUtility::get_value( $args, 'include_categories', [] ) );
		$is_include_cats               = in_array( $normalized_include_categories['mode'], [ 'all', 'explicit' ], true );
		$should_disable_tag_matching   = in_array( $normalized_include_categories['mode'], [ 'all', 'current', 'explicit' ], true );
		$offset_number                 = ArrayUtility::get_value( $args, 'offset_number', 0 );
		$show_price                    = ArrayUtility::get_value( $args, 'show_price', 'on' );
		$product_id                    = WooCommerceUtils::get_product_id_from_attributes( $args );

		/*
		 * WooCommerce related-product cache is keyed by product ID only,
		 * so we reset it whenever include-categories context changes.
		 */
		self::_maybe_invalidate_related_products_cache( $product_id, $normalized_include_categories );

		// Force set product's class to WooCommerceProductVariablePlaceholder
		// in TB, so related product can output visible content based on pre-filled value in TB.
		$added_product_class_filter = false;
		if ( 'true' === ArrayUtility::get_value( $conditional_tags, 'is_tb', false ) || is_et_pb_preview() ) {
			remove_filter( 'woocommerce_product_class', 'et_theme_builder_wc_product_class' );
			remove_filter( 'woocommerce_product_class', [ WooCommerceUtils::class, 'divi_theme_builder_wc_product_class' ] );
			add_filter( 'woocommerce_product_class', [ WooCommerceUtils::class, 'divi_theme_builder_wc_product_class' ] );
			$added_product_class_filter = true;
		}

		$is_offset_valid = absint( $offset_number ) > 0;

		if ( $is_offset_valid ) {
			self::$offset = $offset_number;

			add_filter(
				'woocommerce_related_products',
				[
					self::class,
					// phpcs:ignore WordPress.Arrays.CommaAfterArrayItem.NoComma -- This is a function call.
					'apply_related_products_offset',
				],
				10,
				3
			);
		}

		if ( $is_include_cats ) {
			add_filter(
				'woocommerce_get_related_product_cat_terms',
				[
					self::class,
					// phpcs:ignore WordPress.Arrays.CommaAfterArrayItem.NoComma -- This is a function call.
					'set_related_products_categories',
				]
			);
		}

		if ( $should_disable_tag_matching ) {
			// Disable tag-based matching so current/selected category scopes stay strict.
			add_filter( 'woocommerce_product_related_posts_relate_by_tag', '__return_false' );
		}

		add_filter(
			'woocommerce_output_related_products_args',
			[
				self::class,
				// phpcs:ignore WordPress.Arrays.CommaAfterArrayItem.NoComma -- This is a function call.
				'set_related_products_args',
			]
		);

		if ( 'off' === $show_price ) {
			remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price' );
		}

		$image_box_shadow              = ArrayUtility::get_value( $args, 'image.decoration.boxShadow', [] );
		self::$_thumbnail_overlay_attr = $image_box_shadow;

		// Hook into D5's WooCommerce thumbnail template to add box shadow overlay.
		// Use a priority that ensures it runs early and stays active during rendering.
		add_filter( 'et_d5_woocommerce_product_thumbnail_overlay_attr', [ self::class, 'get_cached_thumbnail_overlay_attr' ], 5, 0 );

		$output = WooCommerceUtils::render_module_template( 'woocommerce_output_related_products', $args );

		// Remove the filter after rendering.
		remove_filter( 'et_d5_woocommerce_product_thumbnail_overlay_attr', [ self::class, 'get_cached_thumbnail_overlay_attr' ], 5 );

		remove_filter(
			'woocommerce_output_related_products_args',
			[
				self::class,
				// phpcs:ignore WordPress.Arrays.CommaAfterArrayItem.NoComma -- This is a function call.
				'set_related_products_args',
			]
		);

		if ( $is_include_cats ) {
			remove_filter(
				'woocommerce_get_related_product_cat_terms',
				[
					self::class,
					// phpcs:ignore WordPress.Arrays.CommaAfterArrayItem.NoComma -- This is a function call.
					'set_related_products_categories',
				]
			);
		}

		if ( $should_disable_tag_matching ) {
			remove_filter( 'woocommerce_product_related_posts_relate_by_tag', '__return_false' );
		}

		if ( $is_offset_valid ) {
			remove_filter(
				'woocommerce_related_products',
				[
					self::class,
					// phpcs:ignore WordPress.Arrays.CommaAfterArrayItem.NoComma -- This is a function call.
					'apply_related_products_offset',
				],
				10
			);

			self::$offset = 0;
		}

		if ( $added_product_class_filter ) {
			remove_filter( 'woocommerce_product_class', [ WooCommerceUtils::class, 'divi_theme_builder_wc_product_class' ] );
		}

		if ( 'off' === $show_price ) {
			add_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price' );
		}

		return $output;
	}

	/**
	 * Gets the cached thumbnail overlay attributes.
	 *
	 * @since ??
	 *
	 * @return array Box shadow attributes for thumbnail overlay.
	 */
	public static function get_cached_thumbnail_overlay_attr(): array {
		// Return the actual cached image box shadow attributes from the module.
		return self::$_thumbnail_overlay_attr;
	}
}
