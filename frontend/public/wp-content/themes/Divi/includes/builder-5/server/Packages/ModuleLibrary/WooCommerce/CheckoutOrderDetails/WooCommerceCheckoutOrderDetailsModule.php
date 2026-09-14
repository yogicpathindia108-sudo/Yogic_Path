<?php
/**
 * Module Library: WooCommerceCheckoutOrderDetails Module
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\CheckoutOrderDetails;

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
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewUtils;
use ET\Builder\Packages\WooCommerce\WooCommerceHooks;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_Block_Type_Registry;
use WP_Block;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;

/**
 * WooCommerceCheckoutOrderDetailsModule class.
 *
 * This class implements the functionality of a call-to-action component
 * in a frontend application. It provides functions for rendering the
 * WooCommerceCheckoutOrderDetails module,
 * managing REST API endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class WooCommerceCheckoutOrderDetailsModule implements DependencyInterface {

	/**
	 * Render callback for the WooCommerceCheckoutOrderDetails module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ WooCommerceCheckoutOrderDetailsEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $content                     The block's content.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the WooCommerceCheckoutOrderDetails module.
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
	 * WooCommerceCheckoutOrderDetailsModule::render_callback( $attrs, $content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements ): string {
		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return '';
		}

		// Return empty string if this is the order pay page, we should not render the module on this page.
		// to avoid showing duplicates of the module.
		global $wp;
		if ( ! empty( $wp->query_vars['order-pay'] ) ) {
			return '';
		}

		/**
		 * Check for cart error state to return empty string.
		 *
		 * This is done because if an error exists, the WooCommerce template output will simply be
		 * "There are some issues with the items in your cart.
		 * Please go back to the cart page and resolve these issues before checking out."
		 * We do not want duplicates of this message on the checkout page, so we return empty string.
		*/
		if (
			WooCommerceUtils::is_woocommerce_cart_method_callable( 'check_cart_items' )
			&& ! is_et_pb_preview()
		) {
			WC()->cart->check_cart_items();

			if ( function_exists( 'wc_notice_count' ) && wc_notice_count( 'error' ) > 0 ) {
				return '';
			}
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
							'children'          => self::get_checkout_order_details( [] ),
						]
					),
				],
			]
		);
	}

	/**
	 * Gets the Checkout Order Details markup for frontend rendering.
	 *
	 * Based on D4's ET_Builder_Module_Woocommerce_Checkout_Order_Details::get_checkout_order_details()
	 * but adapted for D5 frontend context.
	 *
	 * @since ??
	 *
	 * @param array $conditional_tags Array of conditional tags.
	 *
	 * @return string The rendered checkout order details HTML.
	 */
	public static function get_checkout_order_details( array $conditional_tags = [] ): string {
		// Handle WooCommerce hooks for checkout isolation.
		self::maybe_handle_hooks( $conditional_tags );

		$is_cart_empty      = WooCommerceUtils::is_woocommerce_cart_available() && WC()->cart->is_empty();
		$is_rest_context    = Conditions::is_rest_api_request();
		$is_tb              = $conditional_tags['is_tb'] ?? false;
		$is_preview_context = $is_rest_context || $is_tb || is_et_pb_preview();

		// Set dummy cart contents for preview when cart is empty.
		if ( $is_cart_empty && $is_preview_context ) {
			add_filter(
				'woocommerce_get_cart_contents',
				[ WooCommerceUtils::class, 'set_dummy_cart_contents' ]
			);
		}

		WooCommerceUtils::start_isolated_checkout_section_render();

		$initial_buffer_level = ob_get_level();
		ob_start();

		try {
			// Render the WooCommerce checkout order details.
			\WC_Shortcode_Checkout::output( [] );

			$markup = ob_get_clean();
		} finally {
			if ( ob_get_level() > $initial_buffer_level ) {
				ob_end_clean();
			}

			WooCommerceUtils::stop_isolated_checkout_section_render();
		}

		// Remove dummy cart contents filter.
		if ( $is_cart_empty && $is_preview_context ) {
			remove_filter(
				'woocommerce_get_cart_contents',
				[ WooCommerceUtils::class, 'set_dummy_cart_contents' ]
			);
		}

		// Reset WooCommerce hooks.
		self::maybe_reset_hooks( $conditional_tags );

		// Fallback to empty string if markup is not valid.
		if ( ! is_string( $markup ) ) {
			$markup = '';
		}

		return $markup;
	}

	/**
	 * Handle WooCommerce hooks to isolate checkout order details section.
	 *
	 * @since ??
	 *
	 * @param array $conditional_tags Array of conditional tags.
	 *
	 * @return void
	 */
	public static function maybe_handle_hooks( array $conditional_tags ): void {
		$is_tb           = $conditional_tags['is_tb'] ?? false;
		$is_rest_context = Conditions::is_rest_api_request();

		WooCommerceHooks::detach_wc_checkout_coupon_form();
		WooCommerceHooks::detach_wc_checkout_login_form();
		WooCommerceHooks::detach_wc_checkout_billing();
		WooCommerceHooks::detach_wc_checkout_shipping();
		WooCommerceHooks::detach_wc_checkout_payment();

		// Add template swapping filter for VB/REST contexts.
		if ( $is_rest_context || $is_tb ) {
			add_filter(
				'wc_get_template',
				[ self::class, 'swap_template' ],
				10,
				5
			);
		}
	}

	/**
	 * Reset WooCommerce hooks after rendering.
	 *
	 * @since ??
	 *
	 * @param array $conditional_tags Array of conditional tags.
	 *
	 * @return void
	 */
	public static function maybe_reset_hooks( array $conditional_tags ): void {
		$is_tb           = $conditional_tags['is_tb'] ?? false;
		$is_rest_context = Conditions::is_rest_api_request();

		WooCommerceHooks::attach_wc_checkout_coupon_form();
		WooCommerceHooks::attach_wc_checkout_login_form();
		WooCommerceHooks::attach_wc_checkout_billing();
		WooCommerceHooks::attach_wc_checkout_shipping();
		WooCommerceHooks::attach_wc_checkout_payment();

		// Remove template swapping filter for VB/REST contexts.
		if ( $is_rest_context || $is_tb ) {
			remove_filter(
				'wc_get_template',
				[ self::class, 'swap_template' ],
				10,
				5
			);
		}
	}

	/**
	 * Swap WooCommerce template for checkout order details in VB/REST contexts.
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
		$is_template_override = 'checkout/review-order.php' === $template_name;

		if ( $is_template_override ) {
			return trailingslashit( ET_BUILDER_5_DIR ) . 'server/Packages/WooCommerce/Templates/' . $template_name;
		}

		return $template;
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
	 * Collapse table gutters borders style declaration.
	 *
	 * This function creates CSS declarations for table border collapse and spacing.
	 * It handles both collapsed and separated border scenarios with custom gutter values.
	 *
	 * This function is the PHP equivalent of the TypeScript function
	 * `collapseTableGuttersBordersStyleDeclaration` located in
	 * visual-builder/packages/module-library/src/components/woocommerce/checkout-order-details/style-declarations/collapse-table-gutters-borders/index.ts.
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
	 * }
	 * @param array $attrs                      Module attributes for gutter width values.
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
	 * Generate classnames for the module.
	 *
	 * This function generates classnames for the module based on the provided
	 * arguments. It is used in the `render_callback` function of the WooCommerceCheckoutOrderDetails module.
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
	 * WooCommerceCheckoutOrderDetailsModule::module_classnames($args);
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
	}

	/**
	 * WooCommerceCheckoutOrderDetails module script data.
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
	 * WooCommerceCheckoutOrderDetailsModule::module_script_data( $args );
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
	 * WooCommerceCheckoutOrderDetails Module's style components.
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
											'selector' => "{$order_class} h3, table.shop_table th, table.shop_table tr td",
											'attr'     => $attrs['module']['advanced']['text'] ?? [],
											'propertySelectors' => [
												'textShadow' => [
													'desktop' => [
														'value' => [
															'text-shadow' => "{$order_class} h3, table.shop_table th, table.shop_table tr td",
														],
													],
												],
											],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class}",
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
					// Content.
					$elements->style(
						[
							'attrName' => 'content',
						]
					),
					// Column Label.
					$elements->style(
						[
							'attrName' => 'columnLabel',
						]
					),
					// Table.
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
								],
							],
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
											'selector' => "{$order_class} table.shop_table tr th, {$order_class} table.shop_table tr td",
											'attr'     => $attrs['tableCell']['decoration']['border'] ?? [],
											'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
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
	 * Get the custom CSS fields for the Divi WooCommerceCheckoutOrderDetails module.
	 *
	 * This function retrieves the custom CSS fields defined for the Divi WooCommerceCheckoutOrderDetails module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /api/js/divi-module-library/functions/generateDefaultAttrs cssFields}
	 * located in `@divi/module-library`. Note that this function does not have
	 * a `label` property on each array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi WooCommerceCheckoutOrderDetails module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = CustomCssTrait::custom_css();
	 * // Returns an array of custom CSS fields for the WooCommerceCheckoutOrderDetails module.
	 * ```
	 */
	public static function custom_css(): array {
		$registered_block = WP_Block_Type_Registry::get_instance()->get_registered( 'divi/woocommerce-checkout-order-details' );

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
	 * Loads `WooCommerceCheckoutOrderDetailsModule` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		/*
		 * Bail if the WooCommerce plugin is not active.
		 */
		if ( ! et_is_woocommerce_plugin_active() ) {
			return;
		}

		$module_json_folder_path = dirname( __DIR__, 5 ) . '/visual-builder/packages/module-library/src/components/woocommerce/checkout-order-details/';

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
