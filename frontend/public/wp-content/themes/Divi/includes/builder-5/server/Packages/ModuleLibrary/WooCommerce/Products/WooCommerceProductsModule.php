<?php
/**
 * Module Library: WooCommerceProducts Module
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\Products;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\ArrayUtility;
use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\Framework\Utility\StringUtility;
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
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\StyleLibrary\Utils\Utils;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use Exception;
use WP_Block_Type_Registry;
use WP_Block;

/**
 * WooCommerceProductsModule class.
 *
 * Server-side implementation of the Divi WooCommerce Products module. It
 * renders the module output, provides style/script data hooks, and integrates
 * with WooCommerce queries and filters to produce the expected product lists.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class WooCommerceProductsModule implements DependencyInterface {

	/**
	 * Static offset value for pagination handling.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	public static int $offset = 0;

	/**
	 * Flag to indicate when offset exceeds total products, requiring empty state display.
	 *
	 * @since ??
	 *
	 * @var bool
	 */
	public static bool $should_show_empty = false;

	/**
	 * Static properties for passing arguments to WooCommerce filters.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	public static array $static_props = [];

	/**
	 * Render callback for the WooCommerceProducts module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ WooCommerceProductsEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes saved by Divi Builder.
	 * @param string         $content                     The block's content.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the WooCommerceProducts module.
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
	 * WooCommerceProductsModule::render_callback( $attrs, $content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements ): string {
		// Get breakpoints states info for dynamic access.
		$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
		$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
		$default_state           = $breakpoints_states_info->default_state();

		// Extract parameters from attributes to pass to get_shop_html.
		$type               = $attrs['content']['advanced']['type'][ $default_breakpoint ][ $default_state ] ?? 'recent';
		$use_current_loop   = $attrs['content']['advanced']['useCurrentLoop'][ $default_breakpoint ][ $default_state ] ?? 'off';
		$posts_number       = $attrs['content']['advanced']['postsNumber'][ $default_breakpoint ][ $default_state ] ?? 12;
		$include_categories = $attrs['content']['advanced']['includeCategories'][ $default_breakpoint ][ $default_state ] ?? [];
		$columns_number     = $attrs['content']['advanced']['columnsNumber'][ $default_breakpoint ][ $default_state ] ?? 0;
		$orderby            = $attrs['content']['advanced']['orderby'][ $default_breakpoint ][ $default_state ] ?? 'default';
		$offset_number      = $attrs['content']['advanced']['offsetNumber'][ $default_breakpoint ][ $default_state ] ?? 0;
		// Check if pagination is enabled across all breakpoints and states.
		$show_pagination = ModuleUtils::has_value(
			$attrs['elements']['advanced']['showPagination'] ?? [],
			[
				'valueResolver' => function ( $value ) {
					return 'on' === $value;
				},
			]
		);

		$args = [
			'type'              => $type,
			'includeCategories' => $include_categories,
			'postsNumber'       => $posts_number,
			'orderby'           => $orderby,
			'columnsNumber'     => $columns_number,
			'showPagination'    => $show_pagination,
			'useCurrentLoop'    => $use_current_loop,
			'offsetNumber'      => $offset_number,
		];

		// Get the shop HTML markup.
		$shop_html = self::get_shop_html( $args, [], [ 'id' => Style::get_current_post_id_reverse() ] );

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		// Add the data-shortcode_index attribute for frontend JavaScript shop item class generation.
		// This ensures that et_pb_shop_item_{shop_index}_{item_index} classes are properly generated
		// instead of et_pb_shop_item_undefined_{item_index}.
		$html_attrs = [ 'data-shortcode_index' => $block->parsed_block['orderIndex'] ];

		return Module::render(
			[
				// FE only.
				'orderIndex'          => $block->parsed_block['orderIndex'],
				'storeInstance'       => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'attrs'               => $attrs,
				'elements'            => $elements,
				'htmlAttrs'           => $html_attrs,
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
					// Previously escaped.
					$shop_html,
				],
			]
		);
	}

	/**
	 * Generate classnames for the module.
	 *
	 * This function generates classnames for the module based on the provided
	 * arguments. It is used in the `render_callback` function of the WooCommerceProducts module.
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
	 * WooCommerceProductsModule::module_classnames($args);
	 * ```
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		// Text Options.
		$classnames_instance->add(
			TextClassnames::text_options_classnames(
				$attrs['module']['advanced']['text'] ?? [],
				[
					'color'       => true,
					'orientation' => true,
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

		// Add grid class when columns_number is '0' (default).
		$columns_number = $attrs['content']['advanced']['columnsNumber'][ $default_breakpoint ][ $default_state ] ?? null;
		if ( '0' === $columns_number ) {
			$classnames_instance->add( 'et_pb_shop_grid' );
		}

		/*
		 * Apply CSS classes based on element visibility settings.
		 *
		 * These classes control the display of product elements (name, image, etc.) and are added
		 * for the default breakpoint/state to prevent layout shift on a page load. The same classes
		 * are dynamically applied for all breakpoints/states via MultiViewScriptData.
		 */
		$show_name = $attrs['elements']['advanced']['showName'][ $default_breakpoint ][ $default_state ] ?? null;
		if ( 'off' === $show_name ) {
			$classnames_instance->add( 'et_pb_shop_no_name' );
		}

		$show_image = $attrs['elements']['advanced']['showImage'][ $default_breakpoint ][ $default_state ] ?? null;
		if ( 'off' === $show_image ) {
			$classnames_instance->add( 'et_pb_shop_no_image' );
		}

		$show_price = $attrs['elements']['advanced']['showPrice'][ $default_breakpoint ][ $default_state ] ?? null;
		if ( 'off' === $show_price ) {
			$classnames_instance->add( 'et_pb_shop_no_price' );
		}

		$show_rating = $attrs['elements']['advanced']['showRating'][ $default_breakpoint ][ $default_state ] ?? null;
		if ( 'off' === $show_rating ) {
			$classnames_instance->add( 'et_pb_shop_no_rating' );
		}

		$show_sale_badge = $attrs['elements']['advanced']['showSaleBadge'][ $default_breakpoint ][ $default_state ] ?? null;
		if ( 'off' === $show_sale_badge ) {
			$classnames_instance->add( 'et_pb_shop_no_sale_badge' );
		}
	}

	/**
	 * WooCommerceProducts module script data.
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
							'et_pb_shop_no_name' => $attrs['elements']['advanced']['showName'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_shop_no_image' => $attrs['elements']['advanced']['showImage'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_shop_no_price' => $attrs['elements']['advanced']['showPrice'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_shop_no_rating' => $attrs['elements']['advanced']['showRating'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_shop_no_sale_badge' => $attrs['elements']['advanced']['showSaleBadge'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
				],
				'setVisibility' => [
					[
						'selector'      => implode(
							', ',
							[
								$selector . ' nav.woocommerce-pagination',
								$selector . ' p.woocommerce-result-count',
								$selector . ' form.woocommerce-ordering',
							]
						),
						'data'          => $attrs['elements']['advanced']['showPagination'] ?? [],
						'valueResolver' => function ( $value ) {
							return 'off' !== $value ? 'visible' : 'hidden';
						},
					],
				],
			]
		);
	}

	/**
	 * WooCommerceProducts Module's style components.
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
	 *     @type string         $id                 Module ID. In VB the ID is UUIDv4; in FE it is the order index.
	 *     @type string         $name               Module name.
	 *     @type int            $orderIndex         Order index for FE rendering.
	 *     @type int            $storeInstance      Store instance identifier.
	 *     @type array          $attrs              Module attributes.
	 *     @type array          $parentAttrs        Parent attributes.
	 *     @type string         $orderClass         Selector class name.
	 *     @type string         $parentOrderClass   Parent selector class name.
	 *     @type string         $wrapperOrderClass  Wrapper selector class name.
	 *     @type array          $settings           Custom settings.
	 *     @type string|null    $state              Attributes state.
	 *     @type string|null    $mode               Style mode.
	 *     @type ModuleElements $elements           ModuleElements instance.
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
											'selector' => "{$order_class}.et_pb_shop",
											'attr'     => $attrs['module']['advanced']['text'] ?? [],
											'propertySelectors' => [
												'textShadow' => [
													'desktop' => [
														'value' => [
															'text-shadow' => "{$order_class}.et_pb_shop .woocommerce ul.products h3, {$order_class}.et_pb_shop .woocommerce ul.products  h1, {$order_class}.et_pb_shop .woocommerce ul.products  h2, {$order_class}.et_pb_shop .woocommerce ul.products  h4, {$order_class}.et_pb_shop .woocommerce ul.products  h5, {$order_class}.et_pb_shop .woocommerce ul.products  h6, {$order_class}.et_pb_shop .woocommerce ul.products .price, {$order_class}.et_pb_shop .woocommerce ul.products .price .amount",
														],
													],
												],
											],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class}.et_pb_shop",
											'attr'     => $attrs['module']['decoration']['border'] ?? [],
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
					// Image.
					$elements->style(
						[
							'attrName'   => 'image',
							'styleProps' => [
								'background'     => [
									'selector'  => "{$order_class}.et_pb_shop .et_shop_image > img, {$order_class}.et_pb_shop .et_shop_image .et_overlay",
									'important' => true,
								],
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class}.et_pb_shop .et_shop_image > img, {$order_class}.et_pb_shop .et_shop_image .et_overlay",
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
											'selector' => "{$order_class}.et_pb_shop span.onsale",
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
	 * Star rating style declaration for WooCommerce Products module.
	 *
	 * This function is the equivalent of the `starRatingStyleDeclaration` JS function located in
	 * the module library style declarations.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of parameters for the style declaration.
	 *
	 *     @type array $attrValue The attribute value containing font settings.
	 * }.
	 *
	 * @return string The star rating style declarations.
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
	 * Get the custom CSS fields for the Divi WooCommerce Products module.
	 *
	 * Retrieves the custom CSS fields defined for the module. Unlike the
	 * JavaScript version, this server-side method does not provide a `label`
	 * property on each item.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /api/js/divi-module-library/functions/generateDefaultAttrs cssFields}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = WooCommerceProductsModule::custom_css();
	 * // Returns an array of custom CSS fields for the WooCommerce Products module.
	 * ```
	 */
	public static function custom_css(): array {
		$registered_block = WP_Block_Type_Registry::get_instance()->get_registered( 'divi/shop' );

		if ( ! $registered_block ) {
			return [];
		}

		$custom_css = $registered_block->customCssFields;

		if ( ! is_array( $custom_css ) ) {
			return [];
		}

		return $custom_css;
	}

	/**
	 * Append offset to WooCommerce products query.
	 *
	 * When Product Offset is combined with pagination, WP_Query prefers `offset`
	 * over `paged`, so the SQL start must be advanced manually per page.
	 *
	 * @since ??
	 *
	 * @param array $query_args WooCommerce query arguments.
	 *
	 * @return array Modified query arguments with offset.
	 *
	 * @see https://codex.wordpress.org/Making_Custom_Queries_using_Offset_and_Pagination
	 */
	public static function append_offset( array $query_args ): array {
		if ( self::$offset <= 0 ) {
			return $query_args;
		}

		/*
		 * Offset + pagination don't play well. Manual offset calculation required
		 * so each product-page advances past the module base offset.
		 *
		 * @see: https://codex.wordpress.org/Making_Custom_Queries_using_Offset_and_Pagination
		 */
		$paged          = isset( $query_args['paged'] ) ? (int) $query_args['paged'] : 0;
		$posts_per_page = isset( $query_args['posts_per_page'] ) ? (int) $query_args['posts_per_page'] : 0;

		if ( $paged > 1 && $posts_per_page > 0 ) {
			$query_args['offset'] = ( ( $paged - 1 ) * $posts_per_page ) + self::$offset;
		} else {
			$query_args['offset'] = self::$offset;
		}

		return $query_args;
	}

	/**
	 * Fix WooCommerce popularity sorting to respect DESC order.
	 *
	 * @since ??
	 *
	 * @param array $query_args WP_Query arguments.
	 * @param array $attributes Shortcode attributes (optional).
	 *
	 * @return array Modified query arguments.
	 */
	public static function fix_popularity_sorting( array $query_args, array $attributes = [] ): array {
		// Override popularity sorting to use proper DESC order.
		if ( isset( $attributes['orderby'] ) && 'popularity' === $attributes['orderby'] ) {
			$query_args['meta_key'] = 'total_sales';
			$query_args['orderby']  = 'meta_value_num';
			$query_args['order']    = 'DESC';
		}

		return $query_args;
	}

	/**
	 * Adjust offset pagination for WooCommerce products query results.
	 *
	 * @since ??
	 *
	 * @param object $results WooCommerce query results.
	 *
	 * @return object Modified query results.
	 */
	public static function adjust_offset_pagination( object $results ): object {
		if ( self::$offset > 0 ) {
			/*
			 * Use returned product IDs for empty detection, not offset >= total.
			 * With paginate=false (module default), WC sets total to count( $query->posts )
			 * (page size), not catalog found_posts — so offset > Product Count falsely
			 * looked "past end" even when products were returned.
			 */
			$product_ids = isset( $results->ids ) ? $results->ids : [];
			$ids_count   = is_array( $product_ids ) ? count( $product_ids ) : 0;

			if ( empty( $product_ids ) ) {
				self::$should_show_empty = true;
			}

			/*
			 * Only subtract offset from catalog-sized totals (paginate=true / found_posts).
			 * When paginate=false, total === count( ids ). Subtracting offset then zeros
			 * wc_get_loop_prop( 'total' ) and WC skips rendering despite non-empty ids.
			 */
			$original_total = isset( $results->total ) ? (int) $results->total : 0;

			if ( $ids_count < $original_total ) {
				$results->total = max( 0, $original_total - self::$offset );
				if ( property_exists( $results, 'total_products' ) ) {
					$results->total_products = max( 0, (int) $results->total_products - self::$offset );
				}

				// WC pagination uses total_pages; WP max_num_pages ignores module offset.
				$per_page             = isset( $results->per_page ) ? max( 1, (int) $results->per_page ) : 1;
				$results->total_pages = (int) ceil( $results->total / $per_page );
			}
		}

		return $results;
	}

	/**
	 * Filter included categories for WooCommerce products.
	 *
	 * @since ??
	 *
	 * @param array  $include_categories Array of term ids and special keywords.
	 * @param int    $post_id Post ID.
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return array Filtered category IDs.
	 */
	protected static function _filter_include_categories( array $include_categories, int $post_id = 0, string $taxonomy = 'product_cat' ): array {
		$categories = [];

		if ( ! empty( $include_categories ) ) {
			$categories = self::_filter_meta_categories( $include_categories, $post_id, $taxonomy );
		}

		return $categories;
	}

	/**
	 * Convert category list to array of term IDs, handling meta categories.
	 *
	 * @since ??
	 *
	 * @param array  $categories Array of term ids and special keywords.
	 * @param int    $post_id Optional post id to resolve "current" categories.
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return array Term IDs.
	 */
	protected static function _filter_meta_categories( array $categories, int $post_id = 0, string $taxonomy = 'product_cat' ): array {
		$raw_term_ids = $categories;

		if ( in_array( 'all', $raw_term_ids, true ) ) {
			// If "All Categories" is selected return an empty array so it works for all terms.
			return [];
		}

		$term_ids                = [];
		$queried_object          = get_queried_object();
		$current_tax_query_value = get_query_var( $taxonomy );

		foreach ( $raw_term_ids as $value ) {
			if ( 'current' === $value ) {
				if ( $queried_object instanceof \WP_Term && $taxonomy === $queried_object->taxonomy ) {
					$term_ids[] = (int) $queried_object->term_id;
					continue;
				}

				$current_tax_term = null;

				if ( ! empty( $current_tax_query_value ) ) {
					$current_tax_term = is_numeric( $current_tax_query_value )
						? get_term_by( 'id', (int) $current_tax_query_value, $taxonomy )
						: get_term_by( 'slug', sanitize_title( $current_tax_query_value ), $taxonomy );
				}

				if ( $current_tax_term instanceof \WP_Term ) {
					$term_ids[] = (int) $current_tax_term->term_id;
					continue;
				}

				if ( $post_id > 0 ) {
					$post_terms = wp_get_object_terms( $post_id, $taxonomy );

					if ( is_wp_error( $post_terms ) ) {
						continue;
					}

					$term_ids = array_merge( $term_ids, wp_list_pluck( $post_terms, 'term_id' ) );
				}

				continue;
			}
			$term_ids[] = (int) $value;
		}

		$term_ids = self::filter_invalid_term_ids( array_unique( array_filter( $term_ids ) ), $taxonomy );

		return $term_ids;
	}

	/**
	 * Filters out invalid term ids from an array.
	 * Following D4 pattern from PostBased module.
	 *
	 * @since ??
	 *
	 * @param array  $term_ids Term IDs to validate.
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return array Valid term IDs.
	 */
	public static function filter_invalid_term_ids( array $term_ids, string $taxonomy ): array {
		$valid_term_ids = [];

		foreach ( $term_ids as $term_id ) {
			$term_id = intval( $term_id );
			$term    = term_exists( $term_id, $taxonomy );
			if ( ! empty( $term ) ) {
				$valid_term_ids[] = $term_id;
			}
		}

		return $valid_term_ids;
	}

	/**
	 * Get shop details for shop module (D5 static implementation).
	 *
	 * @since ??
	 *
	 * @param array $args Arguments that affect shop output.
	 * @param array $conditional_tags Passed conditional tag for update process.
	 * @param array $current_page Passed current page params.
	 *
	 * @return string HTML markup for shop module.
	 */
	public static function get_shop( array $args = [], array $conditional_tags = [], array $current_page = [] ): string {
		// Store args in static props for filter callbacks.
		self::$static_props = $args;

		// Extract parameters using D5 ArrayUtility pattern.
		$post_id         = isset( $current_page['id'] ) ? (int) $current_page['id'] : Style::get_current_post_id_reverse();
		$type            = ArrayUtility::get_value( $args, 'type', 'recent' );
		$posts_number    = ArrayUtility::get_value( $args, 'posts_number', 12 );
		$orderby         = ArrayUtility::get_value( $args, 'orderby', 'default' );
		$order           = 'ASC';
		$columns         = ArrayUtility::get_value( $args, 'columns_number', 0 );
		$show_pagination = ArrayUtility::get_value( $args, 'show_pagination', 'off' );
		// Handle both boolean and string values for backward compatibility.
		$pagination         = is_bool( $show_pagination ) ? $show_pagination : 'on' === $show_pagination;
		$product_categories = [];
		$product_tags       = [];
		$use_current_loop   = 'on' === ArrayUtility::get_value( $args, 'use_current_loop', 'off' );
		$use_current_loop   = $use_current_loop && ( is_post_type_archive( 'product' ) || is_search() || et_is_product_taxonomy() );
		$product_attribute  = '';
		$product_terms      = [];
		$offset_number      = ArrayUtility::get_value( $args, 'offset_number', 0 );

		// Resolve '0' (columnsNumber default) to actual default column count based on context (Theme Builder vs sidebar pages).
		if ( '0' === $columns || 0 === $columns ) {
			$columns = WooCommerceUtils::get_default_columns_posts_value();
		}

		// D5 always stores categories as string[] for consistency, and legacy module content is converted
		// to string[].
		$include_categories = ArrayUtility::get_value( $args, 'include_categories', [] );
		$has_current_filter = is_array( $include_categories ) && in_array( 'current', $include_categories, true );

		if ( $use_current_loop ) {
			// When using the current loop, we start with default category handling.
			// This ensures all products are included by default.
			$include_categories = [ 'all' ];

			// Override categories based on current page context.
			if ( is_product_category() ) {
				// Show products only from the current product category (creates string[]).
				$include_categories = [ (string) get_queried_object_id() ];
			} elseif ( is_product_tag() ) {
				// Show products with the current product tag.
				$product_tags = [ get_queried_object()->slug ];
			} elseif ( is_product_taxonomy() ) {
				$term = get_queried_object();

				// Product attribute taxonomy slugs start with pa_.
				if ( StringUtility::starts_with( $term->taxonomy, 'pa_' ) ) {
					$product_attribute = $term->taxonomy;
					$product_terms[]   = $term->slug;
				}
			}
		}

		// Handle both product_category type and current loop with categories.
		if ( 'product_category' === $type || ( $use_current_loop && ! empty( $include_categories ) ) ) {
			$all_shop_categories     = et_builder_get_shop_categories();
			$all_shop_categories_map = [];

			// Safety check: Ensure $include_categories is always an array before processing.
			// This prevents TypeError when legacy content or unexpected data types are encountered.
			if ( ! is_array( $include_categories ) ) {
				$include_categories = [];
			}

			// Filter and validate the category IDs - this function expects array input.
			$raw_product_categories = self::_filter_include_categories( $include_categories, $post_id, 'product_cat' );

			// Build mapping of term IDs to slugs for all available shop categories.
			foreach ( $all_shop_categories as $term ) {
				if ( is_object( $term ) && is_a( $term, 'WP_Term' ) ) {
					$all_shop_categories_map[ $term->term_id ] = $term->slug;
				}
			}

			// Default to all category slugs if no specific categories are filtered.
			$product_categories = array_values( $all_shop_categories_map );

			// If we have specific filtered categories, intersect with available categories.
			if ( ! empty( $raw_product_categories ) ) {
				$product_categories = array_intersect_key(
					$all_shop_categories_map,
					array_flip( $raw_product_categories )
				);
			}

			// When "current" is used with product_category type, omit shortcode category parameter to prevent conflict with exact tax_query constraint.
			if ( 'product_category' === $type && $has_current_filter && ! empty( $raw_product_categories ) ) {
				$product_categories = [];
			}
		}

		// Recent was the default option in Divi once, so it is added here for the websites created before the change.
		if ( 'default' === $orderby && ( 'default' === $type || 'recent' === $type ) ) {
			// Leave the attribute empty to allow WooCommerce to take over and use the default sorting.
			$orderby = '';
		}

		if ( 'latest' === $type ) {
			$orderby = 'date-desc';
		}

		if ( in_array( $orderby, [ 'price-desc', 'date-desc' ], true ) ) {
			// Supported orderby arguments (as defined by WC_Query->get_catalog_ordering_args() ):
			// rand | date | price | popularity | rating | title.
			$orderby = str_replace( '-desc', '', $orderby );
			// Switch to descending order if orderby is 'price-desc' or 'date-desc'.
			$order = 'DESC';
		}

		// Set descending order for popularity and rating sorting (most popular/highest rated products first).
		if ( in_array( $orderby, [ 'popularity', 'rating' ], true ) ) {
			$order = 'DESC';
		}

		$ids             = [];
		$wc_custom_view  = '';
		$wc_custom_views = [
			'sale'         => [ 'on_sale', 'true' ],
			'best_selling' => [ 'best_selling', 'true' ],
			'top_rated'    => [ 'top_rated', 'true' ],
			'featured'     => [ 'visibility', 'featured' ],
		];

		if ( in_array( $type, array_keys( $wc_custom_views ), true ) ) {
			$custom_view_data = $wc_custom_views[ $type ];
			$wc_custom_view   = sprintf( '%1$s="%2$s"', esc_attr( $custom_view_data[0] ), esc_attr( $custom_view_data[1] ) );
		}

		// Handle GET request orderby parameters following D4 pattern.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reason wp_nonce is not required here as data from get requests go through something like "whitelisting" via `in_array` function.
		$request_orderby_value                      = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : '';
		$allowed_orderby_values                     = [ 'menu_order', 'popularity', 'rating', 'date', 'price', 'price-desc', 'date-desc' ];
		$maybe_request_price_value_in_order_options = ! empty( $request_orderby_value ) && in_array( $request_orderby_value, $allowed_orderby_values, true ) && str_contains( strtolower( $request_orderby_value ), 'price' );

		if ( $maybe_request_price_value_in_order_options ) {
			$orderby = 'price';
			$order   = str_contains( strtolower( $request_orderby_value ), 'desc' ) ? 'DESC' : 'ASC';
		}

		if ( 'date' === $request_orderby_value ) {
			$order = 'DESC';
		}

		add_filter( 'woocommerce_default_catalog_orderby', [ self::class, 'set_default_orderby' ] );

		// Add targeted filter for popularity sorting only.
		if ( 'popularity' === $orderby ) {
			add_filter( 'woocommerce_shortcode_products_query', [ self::class, 'fix_popularity_sorting' ], 15, 2 );
		}

		$shortcode = sprintf(
			'[products %1$s limit="%2$s" orderby="%3$s" columns="%4$s" %5$s order="%6$s" %7$s %8$s %9$s %10$s %11$s]',
			et_core_intentionally_unescaped( $wc_custom_view, 'fixed_string' ),
			esc_attr( $posts_number ),
			esc_attr( $orderby ),
			esc_attr( $columns ),
			$product_categories ? sprintf( 'category="%s"', esc_attr( implode( ',', $product_categories ) ) ) : '',
			esc_attr( $order ),
			$pagination ? 'paginate="true"' : '',
			$ids ? sprintf( 'ids="%s"', esc_attr( implode( ',', $ids ) ) ) : '',
			$product_tags ? sprintf( 'tag="%s"', esc_attr( implode( ',', $product_tags ) ) ) : '',
			$product_attribute ? sprintf( 'attribute="%s"', esc_attr( $product_attribute ) ) : '',
			$product_terms ? sprintf( 'terms="%s"', esc_attr( implode( ',', $product_terms ) ) ) : ''
		);

		do_action( 'et_pb_shop_before_print_shop' );

		global $wp_the_query;
		$query_backup = $wp_the_query;

		$is_offset_valid = absint( $offset_number ) > 0;
		if ( $is_offset_valid ) {
			self::$offset = $offset_number;

			add_filter(
				'woocommerce_shortcode_products_query',
				[ self::class, 'append_offset' ]
			);

			add_filter(
				'woocommerce_shortcode_products_query_results',
				[ self::class, 'adjust_offset_pagination' ]
			);
		}

		if ( 'product_category' === $type || $use_current_loop ) {
			add_filter( 'woocommerce_shortcode_products_query', [ self::class, 'filter_products_query' ] );
			add_action( 'pre_get_posts', [ self::class, 'apply_woo_widget_filters' ], 10 );
		}

		if ( $use_current_loop ) {
			add_filter( 'woocommerce_shortcode_products_query', [ self::class, 'filter_vendors_products_query' ] );
			add_filter( 'woocommerce_shortcode_products_query', [ self::class, 'filter_brands_products_query' ] );
		}

		$shop = do_shortcode( $shortcode );

		if ( $is_offset_valid ) {
			// Check if offset exceeded total products and show empty state if needed.
			if ( self::$should_show_empty ) {
				$shop = self::get_no_results_template();
			}

			remove_filter(
				'woocommerce_shortcode_products_query',
				[ self::class, 'append_offset' ]
			);

			remove_filter(
				'woocommerce_shortcode_products_query_results',
				[ self::class, 'adjust_offset_pagination' ]
			);

			self::$offset            = 0;
			self::$should_show_empty = false;
		}

		remove_filter( 'woocommerce_default_catalog_orderby', [ self::class, 'set_default_orderby' ] );

		// Remove popularity sorting filter.
		if ( 'popularity' === $orderby ) {
			remove_filter( 'woocommerce_shortcode_products_query', [ self::class, 'fix_popularity_sorting' ], 15 );
		}

		if ( $use_current_loop ) {
			remove_filter( 'woocommerce_shortcode_products_query', [ self::class, 'filter_vendors_products_query' ] );
			remove_filter( 'woocommerce_shortcode_products_query', [ self::class, 'filter_brands_products_query' ] );
		}

		if ( 'product_category' === $type || $use_current_loop ) {
			remove_action( 'pre_get_posts', [ self::class, 'apply_woo_widget_filters' ], 10 );
			remove_filter( 'woocommerce_shortcode_products_query', [ self::class, 'filter_products_query' ] );
		}

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring backed up global query after shortcode execution, following D4 pattern.
		$wp_the_query = $query_backup;

		do_action( 'et_pb_shop_after_print_shop' );

		$is_shop_empty = preg_match( '/<div class="woocommerce columns-([0-9 ]+)"><\/div>+/', $shop );

		if ( $is_shop_empty || StringUtility::starts_with( $shop, $shortcode ) ) {
			$shop = self::get_no_results_template();
		}

		return $shop;
	}

	/**
	 * Set correct default value for the orderby menu depending on module settings.
	 *
	 * @since ??
	 *
	 * @param string $default_orderby Default orderby value from woocommerce settings.
	 *
	 * @return string Updated orderby value for current module.
	 */
	public static function set_default_orderby( string $default_orderby ): string {
		$orderby = ArrayUtility::get_value( self::$static_props, 'orderby', '' );

		if ( '' === $orderby || 'default' === $orderby ) {
			return $default_orderby;
		}

		// Should check this explicitly since it's the only option which supports '-desc' suffix.
		if ( 'price-desc' === $orderby ) {
			return 'price-desc';
		}

		// Remove '-desc' suffix from other options where Divi may add it.
		$orderby = str_replace( '-desc', '', $orderby );

		return $orderby;
	}

	/**
	 * Add product class name to post classes.
	 *
	 * @since ??
	 *
	 * @param array $classes Post classes.
	 *
	 * @return array Modified post classes.
	 */
	public static function add_product_class_name( array $classes ): array {
		$classes[] = 'product';

		return $classes;
	}

	/**
	 * Apply an exact product_cat tax_query constraint to a products query.
	 *
	 * @since ??
	 *
	 * @param array $query_args Query array.
	 * @param array $term_ids   Product category term IDs.
	 *
	 * @return array Modified query arguments.
	 */
	protected static function _apply_exact_product_cat_tax_query( array $query_args, array $term_ids ): array {
		if ( empty( $term_ids ) ) {
			return $query_args;
		}

		// Avoid conflicting category conditions from shortcode/category filters and enforce
		// a single exact current-category constraint.
		if ( is_array( $query_args['tax_query'] ?? null ) ) {
			$query_args['tax_query'] = array_filter(
				$query_args['tax_query'],
				function ( $tax_query_clause ) {
					return ! is_array( $tax_query_clause ) || 'product_cat' !== ( $tax_query_clause['taxonomy'] ?? '' );
				}
			);
		}

		$exact_current_category_clause = [
			'taxonomy'         => 'product_cat',
			'field'            => 'term_id',
			'terms'            => array_values( array_unique( array_map( 'intval', $term_ids ) ) ),
			'operator'         => 'IN',
			// Keep WooCommerce hierarchical taxonomy behavior for parent category archives.
			'include_children' => true,
		];

		if ( is_array( $query_args['tax_query'] ?? null ) ) {
			$query_args['tax_query'][] = $exact_current_category_clause;
		} else {
			$query_args['tax_query'] = [ $exact_current_category_clause ];
		}

		return $query_args;
	}

	/**
	 * Filter the products query arguments.
	 *
	 * @since ??
	 *
	 * @param array $query_args Query array.
	 *
	 * @return array Modified query arguments.
	 */
	public static function filter_products_query( array $query_args ): array {
		if ( is_search() ) {
			$query_args['s'] = get_search_query();
		}

		if ( function_exists( 'WC' ) ) {
			$query_args['meta_query'] = WC()->query->get_meta_query( $query_args['meta_query'] ?? [], true );
			$query_args['tax_query']  = WC()->query->get_tax_query( $query_args['tax_query'] ?? [], true );

			// Add fake cache-busting argument as the filtering is actually done in self::apply_woo_widget_filters().
			$query_args['nocache'] = microtime( true );
		}

		$type               = ArrayUtility::get_value( self::$static_props, 'type', 'recent' );
		$include_categories = ArrayUtility::get_value( self::$static_props, 'include_categories', [] );
		$use_current_loop   = 'on' === ArrayUtility::get_value( self::$static_props, 'use_current_loop', 'off' );
		$has_current        = is_array( $include_categories ) && in_array( 'current', $include_categories, true );
		$current_term_ids   = [];

		if ( 'product_category' === $type && $has_current ) {
			$current_term_ids = self::_filter_include_categories(
				$include_categories,
				Style::get_current_post_id_reverse(),
				'product_cat'
			);
		} elseif ( $use_current_loop && is_product_category() ) {
			$queried_object_id = (int) get_queried_object_id();

			if ( $queried_object_id > 0 ) {
				$current_term_ids = [ $queried_object_id ];
			}
		}

		if ( ! empty( $current_term_ids ) ) {
			$query_args = self::_apply_exact_product_cat_tax_query( $query_args, $current_term_ids );
		}

		return $query_args;
	}

	/**
	 * Filter the vendors products query arguments on vendor archive page.
	 *
	 * @since ??
	 *
	 * @param array $query_args WP_Query arguments.
	 *
	 * @return array Modified query arguments.
	 */
	public static function filter_vendors_products_query( array $query_args ): array {
		if ( ! class_exists( 'WC_Product_Vendors' ) ) {
			return $query_args;
		}

		if ( defined( 'WC_PRODUCT_VENDORS_TAXONOMY' )
			&& is_tax( WC_PRODUCT_VENDORS_TAXONOMY ) ) {
			$term_id = get_queried_object_id(); // Vendor id.
			$args    = [
				'taxonomy' => WC_PRODUCT_VENDORS_TAXONOMY,
				'field'    => 'id',
				'terms'    => $term_id,
			];

			if ( is_array( $query_args['tax_query'] ?? null ) ) {
				$query_args['tax_query'][] = $args;
			} else {
				$query_args['tax_query'] = [ $args ];
			}
		}

		return $query_args;
	}

	/**
	 * Filter products query to show only products from the current brand when viewing a brand archive page.
	 *
	 * @since ??
	 *
	 * @param array $query_args Query arguments for WooCommerce products shortcode.
	 * @return array Modified query arguments with brand taxonomy filter.
	 */
	public static function filter_brands_products_query( array $query_args ): array {
		if ( is_tax( 'product_brand' ) ) {
			$term_id = get_queried_object_id(); // Brand term id.
			$args    = [
				'taxonomy' => 'product_brand',
				'field'    => 'id',
				'terms'    => $term_id,
			];

			if ( is_array( $query_args['tax_query'] ?? null ) ) {
				$query_args['tax_query'][] = $args;
			} else {
				$query_args['tax_query'] = [ $args ];
			}
		}

		return $query_args;
	}

	/**
	 * Filter the products shortcode query so Woo widget filters apply.
	 *
	 * @since ??
	 *
	 * @param \WP_Query $query WP QUERY object.
	 */
	public static function apply_woo_widget_filters( \WP_Query $query ): void {
		global $wp_the_query;

		// Trick Woo filters into thinking the products shortcode query is the
		// main page query as some widget filters have is_main_query checks.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required for WooCommerce widget filters compatibility, following the D4 pattern.
		$wp_the_query = $query;

		// Set a flag to track that the main query is falsified.
		$wp_the_query->et_pb_shop_query = true;

		if ( function_exists( 'WC' ) ) {
			add_filter( 'posts_clauses', [ WC()->query, 'price_filter_post_clauses' ], 10, 2 );
		}
	}

	/**
	 * Get shop HTML for shop module (D5 static implementation).
	 *
	 * @since ??
	 *
	 * @param array $args Arguments that affect shop output.
	 * @param array $conditional_tags Passed conditional tag for update process.
	 * @param array $current_page Passed current page params.
	 *
	 * @return string HTML markup for shop module.
	 */
	public static function get_shop_html( array $args = [], array $conditional_tags = [], array $current_page = [] ): string {
		do_action( 'et_pb_get_shop_html_before' );

		$props                       = [];
		$props['type']               = ArrayUtility::get_value( $args, 'type', 'recent' );
		$props['include_categories'] = ArrayUtility::get_value( $args, 'includeCategories', [] );
		$props['posts_number']       = ArrayUtility::get_value( $args, 'postsNumber', 12 );
		$props['orderby']            = ArrayUtility::get_value( $args, 'orderby', 'default' );
		$props['columns_number']     = ArrayUtility::get_value( $args, 'columnsNumber', 0 );
		$props['show_pagination']    = ArrayUtility::get_value( $args, 'showPagination', 'off' );
		$props['use_current_loop']   = ArrayUtility::get_value( $args, 'useCurrentLoop', 'off' );
		$props['offset_number']      = ArrayUtility::get_value( $args, 'offsetNumber', 0 );

		// Force product loop to have 'product' class name. It appears that 'product' class disappears
		// when get_shop() is being called for update / from admin-ajax.php.
		add_filter( 'post_class', [ self::class, 'add_product_class_name' ] );

		// Get product HTML using static method.
		$output = self::get_shop( $props, $conditional_tags, $current_page );

		// Remove 'product' class addition to product loop's post class.
		remove_filter( 'post_class', [ self::class, 'add_product_class_name' ] );

		do_action( 'et_pb_get_shop_html_after' );

		return $output;
	}

	/**
	 * Get no results template for empty shop.
	 *
	 * @since ??
	 *
	 * @return string HTML markup for no results template.
	 */
	public static function get_no_results_template(): string {
		return '<div class="woocommerce"><div class="woocommerce-info">' . esc_html__( 'No products were found matching your selection.', 'divi' ) . '</div></div>';
	}

	/**
	 * Loads `WooCommerceProductsModule` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 *
	 * @throws Exception Registration error.
	 */
	public function load(): void {
		/*
		 * Bail if the WooCommerce plugin is not active.
		 */
		if ( ! et_is_woocommerce_plugin_active() ) {
			return;
		}

		// Add a filter for processing dynamic attribute defaults.
		add_filter(
			'divi_module_library_module_default_attributes_divi/shop',
			[ WooCommerceUtils::class, 'process_dynamic_attr_defaults' ],
			10,
			2
		);

		$module_json_folder_path = dirname( __DIR__, 5 ) . '/visual-builder/packages/module-library/src/components/woocommerce/products/';

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
