<?php
/**
 * Module Library: WooCommerceCartProducts Module
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\CartProducts;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewUtils;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Element\ElementStyle;
use ET\Builder\Packages\Module\Options\FormField\FormFieldStyle;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use ET\Builder\Packages\IconLibrary\IconFont\Utils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use Exception;
use WP_Block;
use WP_Block_Type_Registry;

/**
 * WooCommerceCartProductsModule class.
 *
 * Handles rendering and functionality for the WooCommerce Cart Products module.
 * Provides server-side rendering, REST API integration, and builder preview support.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class WooCommerceCartProductsModule implements DependencyInterface {

	/**
	 * Render callback for the WooCommerceCartProducts module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ WooCommerceCartProductsEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs    Block attributes saved by Divi Builder.
	 * @param string         $content  The block's content.
	 * @param WP_Block       $block    Parsed block object being rendered.
	 * @param ModuleElements $elements ModuleElements instance.
	 *
	 * @return string The rendered HTML output.
	 *
	 * @throws \Exception If the module is missing required attributes.
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
	 * WooCommerceCartProductsModule::render_callback( $attrs, $content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements ): string {
		// Get breakpoints states info for dynamic access.
		$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
		$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
		$default_state           = $breakpoints_states_info->default_state();

		// Extract parameters from attributes to pass to get_cart_products.
		$show_update_cart_button = $attrs['elements']['advanced']['showUpdateCartButton'][ $default_breakpoint ][ $default_state ] ?? 'on';

		$args = [
			'show_update_cart_button' => $show_update_cart_button,
		];

		// Get the cart products HTML markup.
		$cart_html = self::get_cart_products( $args, [] );

		// Process custom button icons.
		$button_icons_data = self::process_custom_button_icons( $attrs );

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
				'htmlAttrs'           => $button_icons_data['html_attrs'],
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
					$elements->style_components(
						[
							'attrName' => 'content',
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
							'children'          => [
								$cart_html,
							],
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
	 * arguments. It is used in the `render_callback` function of the WooCommerceCartProducts module.
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
	 * @throws \Exception If the button icon settings are invalid.
	 *
	 * @example
	 * ```php
	 * $args = [
	 *     'classnamesInstance' => $classnamesInstance,
	 *     'attrs' => $attrs,
	 * ];
	 *
	 * WooCommerceCartProductsModule::module_classnames($args);
	 * ```
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		// Get breakpoints states info for dynamic access.
		$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
		$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
		$default_state           = $breakpoints_states_info->default_state();

		// Base WooCommerce classes (first, matching legacy order).
		$classnames_instance->add( 'woocommerce-cart' );
		$classnames_instance->add( 'woocommerce' );

		// Add a button icon support classnames.
		$button_icon_data = self::process_custom_button_icons( $attrs );
		if ( $button_icon_data['has_custom_icons'] ) {
			foreach ( $button_icon_data['css_classes'] as $css_class ) {
				$classnames_instance->add( $css_class );
			}
		}

		// Text Options (includes text orientation classname, matching legacy order).
		$classnames_instance->add(
			TextClassnames::text_options_classnames(
				$attrs['module']['advanced']['text'] ?? [],
				[
					'color'       => false,
					'orientation' => true,
				]
			),
			true
		);

		// Empty cart state class (before conditional visibility classes, matching legacy order).
		if ( WooCommerceUtils::is_woocommerce_cart_available() && WC()->cart->is_empty() ) {
			$classnames_instance->add( 'et_pb_wc_cart_empty' );
		}

		// Row layout classes.
		$row_layout = $attrs['layout']['advanced']['rowLayout'][ $default_breakpoint ][ $default_state ] ?? null;
		if ( $row_layout ) {
			$classnames_instance->add( "et_pb_row_layout_{$row_layout}" );
		}

		// Conditional element visibility classes for default breakpoint/state.
		// These prevent layout shift on a page load while MultiViewScriptData handles all breakpoints/states.
		$show_product_image = $attrs['elements']['advanced']['showProductImage'][ $default_breakpoint ][ $default_state ] ?? null;
		if ( 'off' === $show_product_image ) {
			$classnames_instance->add( 'et_pb_wc_no_product_image' );
		}

		$show_coupon_code = $attrs['elements']['advanced']['showCouponCode'][ $default_breakpoint ][ $default_state ] ?? null;
		if ( 'off' === $show_coupon_code ) {
			$classnames_instance->add( 'et_pb_wc_no_coupon_code' );
		}

		$show_update_cart_button = $attrs['elements']['advanced']['showUpdateCartButton'][ $default_breakpoint ][ $default_state ] ?? null;
		if ( 'off' === $show_update_cart_button ) {
			$classnames_instance->add( 'et_pb_wc_no_update_cart_button' );
		}

		$show_remove_item_icon = $attrs['elements']['advanced']['showRemoveItemIcon'][ $default_breakpoint ][ $default_state ] ?? null;
		if ( 'off' === $show_remove_item_icon ) {
			$classnames_instance->add( 'et_pb_wc_no_remove_item_icon' );
		}

		// Module element classnames (last, to allow proper CSS override cascade).
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
	 * Process custom button icons for the Cart Products module.
	 *
	 * Processes both Apply Coupon button (button) and Update Cart button (disabledButton) icons.
	 *
	 * @since ??
	 *
	 * @param array $attrs            Module attributes.
	 *
	 * @return array {
	 *     Array containing button icon data.
	 *
	 *     @type bool   $has_custom_icons Whether the module has custom button icons.
	 *     @type array  $html_attrs       HTML data attributes for button icons.
	 *     @type array  $css_classes      CSS classes to add to the module.
	 * }
	 * @throws \Exception If the button icon settings are invalid.
	 */
	public static function process_custom_button_icons( array $attrs ): array {
		static $cache = [];

		// Create a cache key based on button attributes that affect the result.
		$button_attrs          = $attrs['button']['decoration']['button'] ?? [];
		$disabled_button_attrs = $attrs['disabledButton']['decoration']['button'] ?? [];
		$cache_key             = md5( wp_json_encode( [ $button_attrs, $disabled_button_attrs ] ) );

		// Return the cached result if available.
		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		// Check if buttons are enabled.
		$has_custom_disabled_button = 'on' === ( $attrs['disabledButton']['decoration']['button']['desktop']['value']['enable'] ?? 'off' );

		// Get Apply Coupon button icon values for all devices.
		$apply_coupon_icon_desktop = $attrs['button']['decoration']['button']['desktop']['value']['icon']['settings'] ?? '';
		$apply_coupon_icon_tablet  = $attrs['button']['decoration']['button']['tablet']['value']['icon']['settings'] ?? '';
		$apply_coupon_icon_phone   = $attrs['button']['decoration']['button']['phone']['value']['icon']['settings'] ?? '';

		// Get Update Cart button icon values for all devices.
		$update_cart_icon_desktop = $has_custom_disabled_button
			? ( $attrs['disabledButton']['decoration']['button']['desktop']['value']['icon']['settings'] ?? '' )
			: '';
		$update_cart_icon_tablet  = $has_custom_disabled_button
			? ( $attrs['disabledButton']['decoration']['button']['tablet']['value']['icon']['settings'] ?? '' )
			: '';
		$update_cart_icon_phone   = $has_custom_disabled_button
			? ( $attrs['disabledButton']['decoration']['button']['phone']['value']['icon']['settings'] ?? '' )
			: '';

		// Check if any custom icon is defined for either button.
		$has_apply_coupon_icons = ! empty( $apply_coupon_icon_desktop ) || ! empty( $apply_coupon_icon_tablet ) || ! empty( $apply_coupon_icon_phone );
		$has_update_cart_icons  = $has_custom_disabled_button && ( ! empty( $update_cart_icon_desktop ) || ! empty( $update_cart_icon_tablet ) || ! empty( $update_cart_icon_phone ) );
		$has_custom_icons       = $has_apply_coupon_icons || $has_update_cart_icons;

		if ( ! $has_custom_icons ) {
			$result = [
				'has_custom_icons' => false,
				'html_attrs'       => [],
				'css_classes'      => [],
			];

			// Cache and return result.
			$cache[ $cache_key ] = $result;
			return $result;
		}

		// Process icons using the same function as D4.
		$processed_apply_coupon_icon_desktop = ! empty( $apply_coupon_icon_desktop ) ? esc_attr( Utils::process_font_icon( $apply_coupon_icon_desktop ) ) : '';
		$processed_apply_coupon_icon_tablet  = ! empty( $apply_coupon_icon_tablet ) ? esc_attr( Utils::process_font_icon( $apply_coupon_icon_tablet ) ) : '';
		$processed_apply_coupon_icon_phone   = ! empty( $apply_coupon_icon_phone ) ? esc_attr( Utils::process_font_icon( $apply_coupon_icon_phone ) ) : '';

		$processed_update_cart_icon_desktop = ! empty( $update_cart_icon_desktop ) ? esc_attr( Utils::process_font_icon( $update_cart_icon_desktop ) ) : '';
		$processed_update_cart_icon_tablet  = ! empty( $update_cart_icon_tablet ) ? esc_attr( Utils::process_font_icon( $update_cart_icon_tablet ) ) : '';
		$processed_update_cart_icon_phone   = ! empty( $update_cart_icon_phone ) ? esc_attr( Utils::process_font_icon( $update_cart_icon_phone ) ) : '';

		$result = [
			'has_custom_icons' => true,
			'html_attrs'       => [
				'data-apply-coupon-icon'        => $processed_apply_coupon_icon_desktop,
				'data-apply-coupon-icon-tablet' => $processed_apply_coupon_icon_tablet,
				'data-apply-coupon-icon-phone'  => $processed_apply_coupon_icon_phone,
				'data-update-cart-icon'         => $processed_update_cart_icon_desktop,
				'data-update-cart-icon-tablet'  => $processed_update_cart_icon_tablet,
				'data-update-cart-icon-phone'   => $processed_update_cart_icon_phone,
			],
			'css_classes'      => [ 'et_pb_woo_custom_button_icon' ],
		];

		// Cache and return result.
		$cache[ $cache_key ] = $result;
		return $result;
	}

	/**
	 * WooCommerceCartProducts module script data.
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
	 * WooCommerceCartProductsModule::module_script_data( $args );
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
							'et_pb_row_layout_default' => $attrs['layout']['advanced']['rowLayout'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'default' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_row_layout_horizontal' => $attrs['layout']['advanced']['rowLayout'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'horizontal' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_row_layout_vertical' => $attrs['layout']['advanced']['rowLayout'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'vertical' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_wc_no_product_image' => $attrs['elements']['advanced']['showProductImage'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_wc_no_coupon_code' => $attrs['elements']['advanced']['showCouponCode'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_wc_no_update_cart_button' => $attrs['elements']['advanced']['showUpdateCartButton'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_wc_no_remove_item_icon' => $attrs['elements']['advanced']['showRemoveItemIcon'] ?? [],
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
	 * Ensure gutter attribute has all breakpoints defined for style iteration.
	 *
	 * The style system iterates over breakpoints present in the 'attr' parameter.
	 * To ensure iteration happens for ALL breakpoints (desktop/tablet/phone), we must
	 * guarantee that at least one gutter field has values defined for each breakpoint.
	 *
	 * This function takes horizontalGutterWidth and ensures desktop, tablet, and phone
	 * breakpoints exist (even if with default '0px' values). This ensures the style
	 * declaration function is called for all three breakpoints, allowing it to fetch
	 * both horizontal AND vertical gutter values for each breakpoint.
	 *
	 * WHY THIS IS NEEDED:
	 * - If horizontalGutterWidth only has desktop: {value: '10px'}, the system only iterates once (desktop).
	 * - Even if verticalGutterWidth has tablet: {value: '5px'}, it would be missed!
	 * - By ensuring all breakpoints exist in the attr we pass, we guarantee full iteration.
	 * - Inside the declaration function, we fetch BOTH horizontal and vertical for each breakpoint.
	 *
	 * @since ??
	 *
	 * @param array $attrs Module attributes.
	 *
	 * @return array horizontalGutterWidth attribute with all breakpoints guaranteed to exist.
	 */
	private static function _ensure_gutter_breakpoints_for_iteration( array $attrs ): array {
		$horizontal_gutter_width_attr = $attrs['table']['advanced']['horizontalGutterWidth'] ?? [];

		$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();

		// Ensure all breakpoints exist to guarantee style system
		// iterates over all breakpoints, even if some of the `horizontalGutterWidth` values are using defaults.
		foreach ( $breakpoints_states_info->mapping() as $breakpoint => $_ ) {
			$horizontal_gutter_width_attr[ $breakpoint ]['value'] = $horizontal_gutter_width_attr[ $breakpoint ]['value'] ?? '0px';
		}

		return $horizontal_gutter_width_attr;
	}

	/**
	 * WooCommerceCartProducts Module's style components.
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
	 *      @type string $id                Module ID. In VB, the ID of the module is UUIDV4. In FE, the ID is an order index.
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
		$attrs                       = $args['attrs'] ?? [];
		$elements                    = $args['elements'];
		$settings                    = $args['settings'] ?? [];
		$style_group                 = $args['styleGroup'] ?? 'module';
		$default_printed_style_attrs = $args['defaultPrintedStyleAttrs'] ?? [];

		$base_order_class = $args['baseOrderClass'] ?? '';

		// Extract the order class.
		$order_class = $args['orderClass'] ?? '';

		$is_inside_sticky_module   = $elements->get_is_inside_sticky_module();
		$sticky_parent_order_class = $elements->get_sticky_parent_order_class();

		// Prepare affecting attributes for button element style.
		// This ensures spacing values from Button Option Group Presets are available
		// for the button icon style declaration, preventing fallback to hard-coded padding.
		$button_affecting_attrs = 'module' === $style_group ? [
			'spacing' => array_replace_recursive(
				$default_printed_style_attrs['button']['decoration']['spacing'] ?? [],
				isset( $elements->preset_printed_style_attrs ) && is_array( $elements->preset_printed_style_attrs ) ? ( $elements->preset_printed_style_attrs['button']['decoration']['spacing'] ?? [] ) : [],
				$attrs['button']['decoration']['spacing'] ?? []
			),
		] : [
			'spacing' => $attrs['button']['decoration']['spacing'] ?? [],
		];

		Style::add(
			[
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => [
					// Image (first, following legacy order).
					$elements->style(
						[
							'attrName'   => 'image',
							'styleProps' => [
								'fit'            => [
									'selector' => "{$order_class} table.cart img",
								],
								'sizing'         => [
									'propertySelectors' => [
										'desktop' => [
											'value' => [
												'aspect-ratio' => "{$order_class} table.cart img",
											],
										],
									],
								],
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} table.cart img",
											'attr'     => $attrs['image']['decoration']['border'] ?? [],
											'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} table.cart img",
											'attr'     => $attrs['image']['decoration']['sizing'] ?? [],
											'declarationFunction' => [ self::class, 'image_max_width_style_declaration' ],
											'additionalParams' => [
												'attrs' => $attrs,
											],
										],
									],
								],
							],
						]
					),
					// Table (second, following legacy order for collapse/borders).
					$elements->style(
						[
							'attrName'   => 'table',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} table.shop_table",
											'attr'     => $attrs['table']['decoration']['border'] ?? [],
											'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} table.shop_table",
											'attr'     => self::_ensure_gutter_breakpoints_for_iteration( $attrs ),
											'declarationFunction' => function ( $params ) use ( $attrs ) {
												return self::collapse_table_gutters_borders_style_declaration( $params, $attrs );
											},
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} table.shop_table",
											'attr'     => $attrs['elements']['advanced']['showProductImage'] ?? [],
											'declarationFunction' => function ( $params ) use ( $attrs ) {
												return self::table_layout_style_declaration( $params, $attrs );
											},
										],
									],
								],
							],
						]
					),
					// Table Header (third, following legacy order).
					$elements->style(
						[
							'attrName' => 'tableHeader',
						]
					),

					// Field - Form Field Style for quantity inputs and coupon fields (placeholder styling).
					FormFieldStyle::style(
						[
							'selector'               => "{$order_class} .quantity input.qty, {$order_class} table.cart td.actions .coupon .input-text",
							'attr'                   => $attrs['field'] ?? [],
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					),
					// Placeholder styles from migrated placeholder group.
					ElementStyle::style(
						[
							'selector'               => implode(
								', ',
								[
									"{$order_class} .quantity input.qty::placeholder",
									"{$order_class} .quantity input.qty:focus::placeholder",
									"{$order_class} table.cart td.actions .coupon .input-text::placeholder",
									"{$order_class} table.cart td.actions .coupon .input-text:focus::placeholder",
								]
							),
							'attrs'                  => [
								'font' => $attrs['field']['decoration']['placeholderFont'] ?? [],
							],
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
							'font'                   => [
								'important' => true,
							],
						]
					),
					// Module (after core styles, before other components).
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
											'selector' => "{$order_class}.et_pb_wc_cart_products",
											'attr'     => $attrs['module']['advanced']['text'] ?? [],
											'propertySelectors' => [
												'textShadow' => [
													'desktop' => [
														'value' => [
															'text-shadow' => "{$order_class}",
														],
													],
												],
											],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class}.et_pb_wc_cart_products",
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
					// Content.
					$elements->style(
						[
							'attrName'              => 'content',
							'styleProps'            => [
								'bodyFont' => [
									'selectorFunction' => function ( $params ) use ( $base_order_class ) {
										$font_tab = $params['customData']['fontTab'] ?? 'body';

										switch ( $font_tab ) {
											case 'link':
												return "{$base_order_class} td.product-name";
											default:
												return $params['selector'];
										}
									},
								],
							],
							'isMergeRecursiveProps' => true,
						]
					),
					// Button.
					$elements->style(
						[
							'attrName'   => 'button',
							'styleProps' => [
								'button'      => [
									'affectingAttrs' => $button_affecting_attrs,
								],
								'attrsFilter' => function ( $decoration_attrs ) use ( $style_group ): ?array {
									// Disable the button icon style for group presets as the button icon styles rendering
									// requires attributes from the spacing group, which is not available at the preset group level.
									if ( 'presetGroup' === $style_group && isset( $decoration_attrs['button'] ) ) {
										return array_merge(
											$decoration_attrs,
											[
												'button' => ModuleUtils::remove_button_icon_attr_value( $decoration_attrs['button'] ),
											]
										);
									}

									return $decoration_attrs;
								},
							],
							'isMergeRecursiveProps' => true,
						]
					),
					// Disabled Button.
					$elements->style(
						[
							'attrName'   => 'disabledButton',
							'styleProps' => [
								'button'      => [
									'affectingAttrs' => $button_affecting_attrs,
								],
								'attrsFilter' => function ( $decoration_attrs ) use ( $style_group ): ?array {
									// Disable the button icon style for group presets as the button icon styles rendering
									// requires attributes from the spacing group, which is not available at the preset group level.
									if ( 'presetGroup' === $style_group && isset( $decoration_attrs['button'] ) ) {
										return array_merge(
											$decoration_attrs,
											[
												'button' => ModuleUtils::remove_button_icon_attr_value( $decoration_attrs['button'] ),
											]
										);
									}

									return $decoration_attrs;
								},
							],
							'isMergeRecursiveProps' => true,
						]
					),
					// Remove Icon.
					$elements->style(
						[
							'attrName' => 'removeIcon',
						]
					),
					// Table Cell.
					$elements->style(
						[
							'attrName'   => 'tableCell',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selectors' => [
												'desktop' => [
													'value' => "{$order_class} table.shop_table td",
													'hover' => "{$order_class} table.shop_table td:hover",
												],
											],
											'attr'      => $attrs['tableCell']['decoration']['border'] ?? [],
											'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selectors' => [
												'desktop' => [
													'value' => "{$order_class} table.shop_table_responsive tr:nth-child(2n) td",
													'hover' => "{$order_class} table.shop_table_responsive tr:nth-child(2n):hover td",
												],
											],
											'attr'      => $attrs['tableCell']['advanced']['alternatingBackground'] ?? [],
											'declarationFunction' => [ self::class, 'alternating_background_color_style_declaration' ],
										],
									],
								],
							],
						]
					),
					// Table Row.
					$elements->style(
						[
							'attrName'   => 'tableRow',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} table.shop_table tr",
											'attr'     => $attrs['tableRow']['decoration']['border'] ?? [],
											'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
										],
									],
								],
							],
						]
					),

					// Module - Only for Custom CSS (last, to allow override).
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
	 * Display mocked variation attribute in VB.
	 *
	 * @since ??
	 *
	 * @param array $cart_item Cart Item.
	 *
	 * @return void
	 */
	public static function display_variation_attribute( array $cart_item ): void {
		$product_id = $cart_item['product_id'];

		switch ( $product_id ) {
			case 1000:
				$item_data = [
					[
						'key'     => 'Size',
						'display' => 'Large',
					],
				];
				break;
			case 1001:
				$item_data = [
					[
						'key'     => 'Color',
						'display' => 'Black',
					],
				];
				break;
			default:
				return;
		}

		wc_get_template( 'cart/cart-item-data.php', [ 'item_data' => $item_data ] );
	}

	/**
	 * Sets dummy permalink.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	public static function set_dummy_permalink(): string {
		return '#';
	}

	/**
	 * Set quantity input readonly.
	 *
	 * @since ??
	 *
	 * @param array $input_args Input arguments.
	 *
	 * @return array
	 */
	public static function set_quantity_input_readonly( array $input_args ): array {
		$input_args['readonly'] = 'readonly';

		return $input_args;
	}

	/**
	 * Whether to load Divi's `cart/cart.php` override (correct form `action`, etc.).
	 *
	 * `conditional_tags['is_tb']` means Theme Builder / REST preview contexts; it must not
	 * be set from `render_callback` on the live cart only to enable this swap, because
	 * {@see get_cart_products()} also uses `is_tb` for placeholder dummy cart content when
	 * the cart is empty. Frontend TB body assignments are detected via
	 * `et_theme_builder_overrides_layout()` instead (#48217).
	 *
	 * @since ??
	 *
	 * @param array $conditional_tags Request tags (e.g. REST `is_tb`).
	 *
	 * @return bool
	 */
	private static function _should_swap_divi_cart_template( array $conditional_tags ): bool {
		if ( Conditions::is_rest_api_request() || is_et_pb_preview() ) {
			return true;
		}

		$is_tb = $conditional_tags['is_tb'] ?? false;
		if ( in_array( $is_tb, [ true, 1, '1', 'true', 'on' ], true ) ) {
			return true;
		}

		if ( function_exists( 'et_theme_builder_overrides_layout' ) && defined( 'ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE' ) ) {
			return et_theme_builder_overrides_layout( ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE );
		}

		return false;
	}

	/**
	 * Handle hooks.
	 *
	 * @since ??
	 *
	 * @param array $conditional_tags Array of conditional tags.
	 *
	 * @return void
	 */
	public static function maybe_handle_hooks( array $conditional_tags ): void {
		remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cart_totals', 10 );
		remove_action( 'woocommerce_before_cart', 'woocommerce_output_all_notices', 10 );

		// Runs on both VB and FE.
		add_filter(
			'wc_get_template',
			[ self::class, 'swap_quantity_input_template' ],
			10,
			5
		);

		if ( self::_should_swap_divi_cart_template( $conditional_tags ) ) {
			add_filter(
				'wc_get_template',
				[ self::class, 'swap_template' ],
				10,
				5
			);
		}
	}

	/**
	 * Reset hooks.
	 *
	 * @since ??
	 *
	 * @param array $conditional_tags Array of conditional tags.
	 *
	 * @return void
	 */
	public static function maybe_reset_hooks( array $conditional_tags ): void {
		add_action( 'woocommerce_cart_collaterals', 'woocommerce_cart_totals', 10 );
		add_action( 'woocommerce_before_cart', 'woocommerce_output_all_notices', 10 );

		remove_filter(
			'wc_get_template',
			[ self::class, 'swap_quantity_input_template' ]
		);

		if ( self::_should_swap_divi_cart_template( $conditional_tags ) ) {
			remove_filter(
				'wc_get_template',
				[ self::class, 'swap_template' ]
			);
		}
	}

	/**
	 * Swaps Quantity input template.
	 *
	 * @since ??
	 *
	 * @param string $template      Template.
	 * @param string $template_name Template name.
	 * @param array  $args          Arguments.
	 * @param string $template_path Template path.
	 * @param string $default_path  Default path.
	 *
	 * @return string
	 */
	public static function swap_quantity_input_template( string $template, string $template_name, array $args, string $template_path, string $default_path ): string {
		$is_template_override = 'global/quantity-input.php' === $template_name;

		if ( $is_template_override ) {
			return trailingslashit( ET_BUILDER_5_DIR ) . 'server/Packages/WooCommerce/Templates/' . $template_name;
		}

		return $template;
	}

	/**
	 * Swaps login form template.
	 *
	 * By default, WooCommerce displays these only when logged-out.
	 * However, these templates must be shown in VB when logged-in. Hence, we use these templates.
	 *
	 * @since ??
	 *
	 * @param string $template      Template.
	 * @param string $template_name Template name.
	 * @param array  $args          Arguments.
	 * @param string $template_path Template path.
	 * @param string $default_path  Default path.
	 *
	 * @return string
	 */
	public static function swap_template( string $template, string $template_name, array $args, string $template_path, string $default_path ): string {
		$is_template_override = 'cart/cart.php' === $template_name;

		if ( $is_template_override ) {
			return trailingslashit( ET_BUILDER_5_DIR ) . 'server/Packages/WooCommerce/Templates/' . $template_name;
		}

		return $template;
	}

	/**
	 * Gets Cart Products markup.
	 *
	 * D5 IMPLEMENTATION DETAILS:
	 * ========================
	 * This method represents the D5 (Divi 5) implementation of the Cart Products module,
	 * which differs significantly from the legacy implementation in several key ways:
	 *
	 * LEGACY vs. D5 APPROACH:
	 * - Legacy: Used AJAX GET requests via `__cart_products` computed property
	 * - D5: Uses REST API POST requests via `/divi/v1/module-data/woocommerce/cart-products/html`
	 *
	 * KEY D5 FEATURES:
	 * 1. REST API Integration: Replaces legacy AJAX with structured REST endpoints
	 * 2. Builder Preview Support: Handles Visual Builder (VB) and Theme Builder (TB) contexts
	 * 3. Fake Content System: Shows placeholder content when the cart is empty in builder modes
	 * 4. Enhanced Permission System: Uses WordPress REST API permission callbacks
	 *
	 * @since ??
	 *
	 * @param array $args             Props containing module configuration (e.g., show_update_cart_button).
	 * @param array $conditional_tags Conditional tags indicating context (VB, TB, REST API, etc.).
	 *
	 * @return string The rendered cart HTML markup.
	 */
	public static function get_cart_products( array $args = [], array $conditional_tags = [] ): string {
		if ( ! class_exists( 'WC_Shortcode_Cart' ) ||
			! method_exists( 'WC_Shortcode_Cart', 'output' ) ) {
			return '';
		}

		$is_tb              = $conditional_tags['is_tb'] ?? false;
		$is_use_placeholder = $is_tb || is_et_pb_preview();

		/*
		 * D5 FAKE CONTENT SYSTEM:
		 * ========================
		 * The $reset_filters flag is central to D5's builder preview functionality.
		 * When true, it indicates that fake content filters have been applied,
		 * allowing the cart template to render even when the actual cart is empty.
		 * This is essential for Visual Builder and Theme Builder previews.
		 */
		$reset_filters = false;

		self::maybe_handle_hooks( $conditional_tags );

		if ( $is_use_placeholder || Conditions::is_rest_api_request() ) {
			// Ensure WooCommerce objects are properly initialized for VB/TB and preview contexts.
			WooCommerceUtils::ensure_woocommerce_objects_initialized( $conditional_tags );
		}

		/*
		 * D5 BUILDER PREVIEW LOGIC:
		 * =========================
		 * Unlike legacy implementation, D5 needs to show cart content in builder contexts
		 * even when the cart is empty. This conditional handles three scenarios:
		 * 1. Visual Builder preview ($is_use_placeholder)
		 * 2. Theme Builder context ($is_tb via $is_use_placeholder)
		 * 3. REST API requests (Conditions::is_rest_api_request())
		 *
		 * When any of these contexts apply AND the cart is empty, we inject fake
		 * content filters to provide realistic preview data for design purposes.
		 */
		if (
			( $is_use_placeholder || Conditions::is_rest_api_request() )
			&& WooCommerceUtils::is_woocommerce_cart_available() && WC()->cart->is_empty()
		) {
			// Add fake cart contents for preview purposes.
			add_filter(
				'woocommerce_get_cart_contents',
				[ WooCommerceUtils::class, 'set_dummy_cart_contents' ]
			);

			// Add fake product permalinks for preview purposes.
			add_filter(
				'woocommerce_cart_item_permalink',
				[ self::class, 'set_dummy_permalink' ]
			);

			// Add fake variation attributes for preview purposes.
			add_action(
				'woocommerce_after_cart_item_name',
				[ self::class, 'display_variation_attribute' ]
			);

			/*
			 * CRITICAL: Set a flag to indicate fake filters are active.
			 * This flag is essential for the cart rendering condition below.
			 */
			$reset_filters = true;
		}

		$show_update_cart_button = $args['show_update_cart_button'] ?? 'on';

		/*
		 * D5 UPDATE BUTTON CONTROL:
		 * =========================
		 * D5 provides granular control over cart functionality via module attributes.
		 * When the update button is disabled, quantity inputs become readonly to prevent
		 * user interaction while maintaining visual presentation.
		 */
		if ( 'off' === $show_update_cart_button ) {
			add_filter(
				'woocommerce_quantity_input_args',
				[ self::class, 'set_quantity_input_readonly' ]
			);
		}

		ob_start();

		/*
		 * D5 CART RENDERING CONDITION:
		 * ====================================================
		 * The D5 implementation uses the same condition as legacy:
		 *
		 * Current condition: `if ( isset( WC()->cart ) && ! WC()->cart->is_empty() )`
		 *
		 * The approach works correctly for both Visual Builder (VB) and test
		 * environments because the fake content filters (applied when $reset_filters = true)
		 * properly populate WC()->cart with dummy data, making it appear non-empty during
		 * preview contexts. This ensures consistent cart rendering across all environments
		 * while maintaining clean, straightforward conditional logic.
		 */
		if ( WooCommerceUtils::is_woocommerce_cart_available() && ( ! WC()->cart->is_empty() ) ) {
			wc_get_template( 'cart/cart.php' );
		}

		$markup = ob_get_clean();

		if ( 'off' === $show_update_cart_button ) {
			remove_filter(
				'woocommerce_quantity_input_args',
				[ self::class, 'set_quantity_input_readonly' ]
			);
		}

		if ( ( $is_use_placeholder || Conditions::is_rest_api_request() ) && $reset_filters ) {
			remove_filter(
				'woocommerce_get_cart_contents',
				[ WooCommerceUtils::class, 'set_dummy_cart_contents' ]
			);
			remove_filter(
				'woocommerce_cart_item_permalink',
				[ self::class, 'set_dummy_permalink' ]
			);
			remove_action(
				'woocommerce_after_cart_item_name',
				[ self::class, 'display_variation_attribute' ]
			);
		}

		self::maybe_reset_hooks( $conditional_tags );

		// Fallback.
		if ( ! is_string( $markup ) ) {
			$markup = '';
		}

		return $markup;
	}

	/**
	 * Alternating background color style declaration.
	 *
	 * This function creates CSS declarations for alternating table row background colors.
	 * It follows the D5 pattern for handling background color attributes by accessing
	 * the color property from the background attribute value.
	 *
	 * This function is the PHP equivalent of the TypeScript function
	 * `alternatingBackgroundColorStyleDeclaration` located in
	 * visual-builder/packages/module-library/src/components/woocommerce/cart-products/style-declarations/alternating-background-color.ts.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type string $attrValue Optional. The background color value. Default empty string.
	 * }
	 *
	 * @return string The CSS declaration string for the alternating background color.
	 */
	public static function alternating_background_color_style_declaration( array $params ): string {
		$attr_value = $params['attrValue'] ?? '';

		if ( empty( $attr_value ) ) {
			return '';
		}

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => true,
			]
		);

		// Add background-color to match legacy D4 behavior.
		$style_declarations->add( 'background-color', $attr_value );

		return $style_declarations->value();
	}

	/**
	 * Collapse table gutters borders style declaration.
	 *
	 * This function creates CSS declarations for table border collapse and spacing.
	 * It handles both collapsed and separated border scenarios with custom gutter values.
	 *
	 * This function is the PHP equivalent of the TypeScript function
	 * `collapseTableGuttersBordersStyleDeclaration` located in
	 * visual-builder/packages/module-library/src/components/woocommerce/cart-products/style-declarations/collapse-table-gutters-borders/index.ts.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type string $attrValue              Optional. Whether to collapse table borders. Default 'off'.
	 *     @type string $state                  Optional. The attribute state. Default empty string.
	 *     @type string $breakpoint             Optional. The current breakpoint. Default empty string.
	 *     @type string $baseBreakpoint         Optional. The base breakpoint. Default empty string.
	 *     @type array  $breakpointNames        Optional. Available breakpoint names. Default empty array.
	 *     @type array  $attrs                  Optional. Module attributes for gutter width values. Default empty array.
	 * }
	 * @param array $attrs {
	 *     An array of arguments.
	 *
	 *     @type array $table {
	 *       An array of arguments.
	 *     }
	 *   }
	 * }
	 *
	 * @return string The CSS declaration string for table border collapse settings.
	 */
	public static function collapse_table_gutters_borders_style_declaration( array $params, array $attrs ): string {
		$breakpoint = $params['breakpoint'] ?? '';
		$state      = $params['state'] ?? '';

		// Ensure we have valid breakpoint and state values.
		// Fall back to defaults if not provided (edge case for non-standard calls).
		if ( empty( $breakpoint ) || empty( $state ) ) {
			$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
			$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
			$default_state           = $breakpoints_states_info->default_state();

			$breakpoint = empty( $breakpoint ) ? $default_breakpoint : $breakpoint;
			$state      = empty( $state ) ? $default_state : $state;
		}

		// Get the collapse toggle value for the current breakpoint/state.
		$collapse_table_gutters_borders = $attrs['table']['advanced']['collapseTableGuttersBorders'][ $breakpoint ][ $state ] ?? 'off';

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		if ( 'on' === $collapse_table_gutters_borders ) {
			$style_declarations->add( 'border-collapse', 'collapse' );
			// Border spacing property has no effect when `border-collapse: collapse`.
			// Hence, we set the border-spacing as `0`.
			$style_declarations->add( 'border-spacing', '0 0' );
		} else {
			// Get gutter values for the current breakpoint/state.
			// These come from the full attrs array, not from the params['attr'] which only
			// ensures iteration happens. This allows us to get BOTH horizontal and vertical
			// values for each breakpoint, regardless of which field triggered the iteration.
			$horizontal_gutter_width = $attrs['table']['advanced']['horizontalGutterWidth'][ $breakpoint ][ $state ] ?? null;
			$vertical_gutter_width   = $attrs['table']['advanced']['verticalGutterWidth'][ $breakpoint ][ $state ] ?? null;

			// Normalize empty strings to null to ensure consistent handling.
			$horizontal_gutter_width = ( '' === $horizontal_gutter_width ) ? null : $horizontal_gutter_width;
			$vertical_gutter_width   = ( '' === $vertical_gutter_width ) ? null : $vertical_gutter_width;

			// Output border-spacing if either horizontal OR vertical gutter is set.
			// Use '0px' as fallback for unset values to ensure valid CSS output.
			if ( ! is_null( $horizontal_gutter_width ) || ! is_null( $vertical_gutter_width ) ) {
				$style_declarations->add( 'border-collapse', 'separate' );
				$style_declarations->add( 'border-spacing', sprintf( '%s %s', $horizontal_gutter_width ?? '0px', $vertical_gutter_width ?? '0px' ) );
			}
		}

		return $style_declarations->value();
	}

	/**
	 * Image maximum width style declaration.
	 *
	 * This function creates CSS declarations for cart product image maximum width.
	 * Extracts maxWidth from sizing attributes - matches legacy image_max_width functionality.
	 * Legacy used the 'width' property for `table.cart img` selector but with maxWidth value.
	 *
	 * This function is the PHP equivalent of the TypeScript function
	 * `imageMaxWidthStyleDeclaration` located in
	 * visual-builder/packages/module-library/src/components/woocommerce/cart-products/style-declarations/image-max-width/index.ts.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array  $attrValue        Optional. The sizing attribute containing maxWidth. Default empty array.
	 *     @type string $state            Optional. The attribute state. Default empty string.
	 *     @type string $breakpoint       Optional. The current breakpoint. Default empty string.
	 *     @type string $baseBreakpoint   Optional. The base breakpoint. Default empty string.
	 *     @type array  $breakpointNames  Optional. Available breakpoint names. Default empty array.
	 *     @type array  $attrs            Optional. Module attributes for additional context. Default empty array.
	 * }
	 *
	 * @return string The CSS declaration string for image maximum width.
	 */
	public static function image_max_width_style_declaration( array $params ): string {
		// Extract sizingAttr from attrValue to match TypeScript pattern: sizingAttr: params?.attrValue || {}.
		$sizing_attr = $params['attrValue'] ?? [];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		// Extract maxWidth from sizing attributes - matches legacy image_max_width functionality.
		// Legacy used the 'width' property for table.cart img selector but with maxWidth value.
		$max_width = $sizing_attr['maxWidth'] ?? '';

		// Only add width style if maxWidth value is provided.
		// This matches the legacy logic: if (!isEmpty(imageMaxWidthValues)).
		if ( ! empty( $max_width ) && trim( $max_width ) !== '' ) {
			$style_declarations->add( 'width', $max_width );
		}

		return $style_declarations->value();
	}


	/**
	 * Table layout style declaration.
	 *
	 * This function creates CSS declarations for the table layout based on visibility settings.
	 * If either showProductImage or showRemoveItemIcon is ON, use a fixed layout.
	 * If both are OFF, use the auto layout. This matches the legacy getTableLayoutCss logic.
	 *
	 * This function is the PHP equivalent of the TypeScript function
	 * `tableLayoutStyleDeclaration` located in
	 * visual-builder/packages/module-library/src/components/woocommerce/cart-products/style-declarations/table-layout/index.ts.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type string $attrValue        Optional. The showProductImage value. Default 'on'.
	 *     @type string $state            Optional. The attribute state. Default empty string.
	 *     @type string $breakpoint       Optional. The current breakpoint. Default empty string.
	 *     @type string $baseBreakpoint   Optional. The base breakpoint. Default empty string.
	 *     @type array  $breakpointNames  Optional. Available breakpoint names. Default empty array.
	 * }
	 * @param array $attrs {
	 *     An array of arguments.
	 *
	 *     @type array $elements {
	 *       An array of arguments.
	 *
	 *       @type array $advanced {
	 *         An array of arguments.
	 *
	 *         @type array $showRemoveItemIcon Module attributes for showRemoveItemIcon.
	 *       }
	 *     }
	 * }
	 *
	 * @return string The CSS declaration string for table layout.
	 */
	public static function table_layout_style_declaration( array $params, array $attrs ): string {
		$breakpoint = $params['breakpoint'] ?? '';
		$state      = $params['state'] ?? '';

		// Ensure we have valid breakpoint and state values.
		// Fall back to defaults if not provided (edge case for non-standard calls).
		if ( empty( $breakpoint ) || empty( $state ) ) {
			$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
			$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
			$default_state           = $breakpoints_states_info->default_state();

			$breakpoint = empty( $breakpoint ) ? $default_breakpoint : $breakpoint;
			$state      = empty( $state ) ? $default_state : $state;
		}

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		// Get both values from attrs using breakpoint and state, matching TypeScript implementation.
		$show_product_image    = $attrs['elements']['advanced']['showProductImage'][ $breakpoint ][ $state ] ?? 'on';
		$show_remove_item_icon = $attrs['elements']['advanced']['showRemoveItemIcon'][ $breakpoint ][ $state ] ?? 'on';

		/*
		 * Determine table layout based on column visibility.
		 * When columns are hidden (display: none), they still exist in the DOM but aren't visible.
		 * - 'fixed' layout: Column widths are determined by the first row only, which can cause layout
		 *   issues when columns are hidden because space is still allocated for them.
		 * - 'auto' layout: Column widths are determined by content across all rows, allowing the browser
		 *   to better handle hidden columns and prevent layout gaps.
		 * Therefore, use 'auto' layout when any column is hidden to prevent layout breaks, and 'fixed'
		 * layout only when all columns are visible for consistent column widths.
		 */
		$table_layout = ( 'off' === $show_product_image || 'off' === $show_remove_item_icon )
			? 'auto'
			: 'fixed';

		$style_declarations->add( 'table-layout', $table_layout );

		return $style_declarations->value();
	}

	/**
	 * Get the custom CSS fields for the Divi WooCommerceCartProducts module.
	 *
	 * This function retrieves the custom CSS fields defined for the Divi WooCommerceCartProducts module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /api/js/divi-module-library/functions/generateDefaultAttrs cssFields}
	 * located in `@divi/module-library`. Note that this function does not have
	 * a `label` property on each array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi WooCommerceCartProducts module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = CustomCssTrait::custom_css();
	 * // Returns an array of custom CSS fields for the WooCommerceCartProducts module.
	 * ```
	 */
	public static function custom_css(): array {
		$registered_block = WP_Block_Type_Registry::get_instance()->get_registered( 'divi/woocommerce-cart-products' );

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
	 * Loads `WooCommerceCartProductsModule` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 * @throws Exception Registration error.
	 */
	public function load(): void {
		/*
		 * Bail if the WooCommerce plugin is not active.
		 */
		if ( ! et_is_woocommerce_plugin_active() ) {
			return;
		}

		$module_json_folder_path = dirname( __DIR__, 5 ) . '/visual-builder/packages/module-library/src/components/woocommerce/cart-products/';

		// Ensure that all filters and actions applied during module registration are registered before calling `ModuleRegistration::register_module()`.
		// However, for consistency, register all module-specific filters and actions before invoking `ModuleRegistration::register_module()`.
		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}
}
