<?php
/**
 * Module Library: WooCommerceCheckoutPaymentInfo Module
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\CheckoutPaymentInfo;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\Packages\IconLibrary\IconFont\Utils;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\WooCommerce\WooCommerceHooks;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_Block_Type_Registry;
use WP_Block;
use WC_Shortcode_Checkout;

/**
 * WooCommerceCheckoutPaymentInfoModule class.
 *
 * This class implements the functionality of a call-to-action component
 * in a frontend application. It provides functions for rendering the
 * WooCommerceCheckoutPaymentInfo module,
 * managing REST API endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class WooCommerceCheckoutPaymentInfoModule implements DependencyInterface {

	/**
	 * Render callback for the WooCommerceCheckoutPaymentInfo module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ WooCommerceCheckoutPaymentInfoEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $content                     The block's content.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the WooCommerceCheckoutPaymentInfo module.
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
	 * WooCommerceCheckoutPaymentInfoModule::render_callback( $attrs, $content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements ): string {
		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		$checkout_payment_info = self::get_checkout_payment_info();

		// Process custom button icons.
		$button_icon_data = self::process_custom_button_icons( $attrs );

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
				'htmlAttrs'           => $button_icon_data['html_attrs'],
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
							'children'          => $checkout_payment_info,
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
	 * arguments. It is used in the `render_callback` function of the WooCommerceCheckoutPaymentInfo module.
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
	 * WooCommerceCheckoutPaymentInfoModule::module_classnames($args);
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
					'color'       => false,
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

		// Add button icon support classnames.
		$button_icon_data = self::process_custom_button_icons( $attrs );
		if ( $button_icon_data['has_custom_icons'] ) {
			foreach ( $button_icon_data['css_classes'] as $css_class ) {
				$classnames_instance->add( $css_class );
			}
		}
	}

	/**
	 * WooCommerceCheckoutPaymentInfo module script data.
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
	 * WooCommerceCheckoutPaymentInfoModule::module_script_data( $args );
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
	 * Gets module background style declarations.
	 *
	 * This function generates CSS styles for module background based on background values.
	 * It matches the VB implementation in `/checkout-payment-info/style-declarations/background/index.ts`.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     Style declaration parameters.
	 *
	 *     @type array|null $attrValue The background attribute value.
	 * }
	 *
	 * @return string Generated CSS declarations.
	 */
	public static function background_style_declaration( array $params ): string {
		$attr_value = $params['attrValue'] ?? [];
		$color      = $attr_value['color'] ?? '';
		$image      = $attr_value['image'] ?? [];
		$video      = $attr_value['video'] ?? [];
		$pattern    = $attr_value['pattern']['enabled'] ?? 'off';
		$mask       = $attr_value['mask']['enabled'] ?? 'off';
		$gradient   = $attr_value['gradient']['enabled'] ?? 'off';

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => true,
			]
		);

		if ( ! empty( $color ) || ! empty( $image ) || ! empty( $video ) || 'on' === $pattern || 'on' === $mask || 'on' === $gradient ) {
			$style_declarations->add( 'background', 'transparent' );
		}

		return $style_declarations->value();
	}

	/**
	 * Button icon style declaration for WooCommerce Checkout Payment module.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array $attrValue The value (breakpoint > state > value) of module attribute.
	 * }
	 *
	 * @return string The button icon style declaration.
	 */
	public static function button_icon_style_declaration( array $params ): string {
		$attr_value         = $params['attrValue'] ?? [];
		$default_attr_value = $params['defaultAttrValue'] ?? [];

		$icon_settings = $attr_value['icon']['settings'] ?? $default_attr_value['icon']['settings'] ?? [];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => [
					'margin-left' => true,
				],
			]
		);

		// Only add vertical centering and horizontal positioning for default icons to fix alignment issue.
		// All other properties are handled by the centralized ButtonIcon.
		$has_custom_icon = ! empty( $icon_settings['unicode'] );
		$enable          = $attr_value['icon']['enable'] ?? $default_attr_value['icon']['enable'] ?? 'off';
		$on_hover        = $attr_value['icon']['onHover'] ?? $default_attr_value['icon']['onHover'] ?? 'off';
		$placement       = $attr_value['icon']['placement'] ?? $default_attr_value['icon']['placement'] ?? 'right';

		// Only apply positioning when icon is enabled, onHover is 'off', and using default icon.
		if ( 'off' !== $enable && 'off' === $on_hover && ! $has_custom_icon ) {
			// Add vertical centering.
			$style_declarations->add( 'top', '50%' );
			$style_declarations->add( 'transform', 'translateY(-50%)' );

			// Add horizontal positioning.
			if ( 'left' === $placement ) {
				$style_declarations->add( 'margin-left', '-1.3em' );
				$style_declarations->add( 'right', 'auto' );
			} else {
				$style_declarations->add( 'margin-left', '0' );
				$style_declarations->add( 'left', 'auto' );
			}
		}

		return $style_declarations->value();
	}

	/**
	 * WooCommerceCheckoutPaymentInfo Module's style components.
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
		$attrs       = $args['attrs'] ?? [];
		$elements    = $args['elements'];
		$settings    = $args['settings'] ?? [];
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
											'selector' => "{$order_class} .woocommerce-checkout #payment, {$order_class} .woocommerce-order",
											'attr'     => $attrs['module']['advanced']['text'] ?? [],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} .woocommerce-checkout #payment, {$order_class} .woocommerce-order",
											'attr'     => $attrs['module']['decoration']['background'] ?? [],
											'declarationFunction' => [ self::class, 'background_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											// Target the main module container explicitly to prevent background overflow.
											// Background styles (video, pattern) are applied to the main container, so
											// overflow:hidden must be applied here when the border radius is set.
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
					// Content.
					$elements->style(
						[
							'attrName'              => 'content',
							'styleProps'            => [
								'bodyFont' => [
									'selectorFunction' => function ( $params ) use ( $order_class ) {
										$font_tab = $params['customData']['fontTab'] ?? 'body';

										switch ( $font_tab ) {
											case 'link':
												return "{$order_class} .woocommerce-privacy-policy-text, {$order_class} .wc_payment_method";
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
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} #payment #place_order",
											'attr'     => $attrs['button']['decoration']['button'] ?? [],
											'declarationFunction' => [ self::class, 'button_icon_style_declaration' ],
											'selectorFunction' => function ( $params ) {
												$params = wp_parse_args(
													$params,
													[
														'selector'             => null,
														'breakpoint'           => null,
														'state'                => null,
														'attr'                 => [],
														'defaultPrintedStyleAttr' => [],
													]
												);

												$icon_attr = ModuleUtils::use_attr_value(
													[
														'attr'       => $params['attr'],
														'breakpoint' => $params['breakpoint'],
														'state'      => $params['state'],
													]
												);

												$placement      = $icon_attr['icon']['placement'] ?? 'right';
												$pseudo_element = 'left' === $placement ? ':before' : ':after';

												return $params['selector'] . $pseudo_element;
											},
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} #payment #place_order",
											'attr'     => $attrs['button']['decoration']['border'] ?? [],
											'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
										],
									],
								],
							],
						]
					),
					// Form Notice.
					$elements->style(
						[
							'attrName'   => 'formNotice',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} #payment ul.payment_methods li.woocommerce-info, {$order_class} #payment ul.payment_methods div.woocommerce-info",
											'attr'     => $attrs['formNotice']['decoration']['border'] ?? [],
											'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
										],
									],
								],
							],
						]
					),
					// Radio Button.
					$elements->style(
						[
							'attrName'   => 'radioButton',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} #payment .wc_payment_method",
											'attr'     => $attrs['radioButton']['decoration']['border'] ?? [],
											'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
										],
									],
								],
							],
						]
					),
					// Selected Radio Button.
					$elements->style(
						[
							'attrName'   => 'selectedRadioButton',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} #payment .wc_payment_method.et_pb_checked",
											'attr'     => $attrs['selectedRadioButton']['decoration']['border'] ?? [],
											'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
										],
									],
								],
							],
						]
					),
					// Tooltip.
					$elements->style(
						[
							'attrName'   => 'tooltip',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} #payment div.payment_box",
											'attr'     => $attrs['tooltip']['decoration']['border'] ?? [],
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
	 * Get the custom CSS fields for the Divi WooCommerceCheckoutPaymentInfo module.
	 *
	 * This function retrieves the custom CSS fields defined for the Divi WooCommerceCheckoutPaymentInfo module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /api/js/divi-module-library/functions/generateDefaultAttrs cssFields}
	 * located in `@divi/module-library`. Note that this function does not have
	 * a `label` property on each array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi WooCommerceCheckoutPaymentInfo module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = CustomCssTrait::custom_css();
	 * // Returns an array of custom CSS fields for the WooCommerceCheckoutPaymentInfo module.
	 * ```
	 */
	public static function custom_css(): array {
		$registered_block = WP_Block_Type_Registry::get_instance()->get_registered( 'divi/woocommerce-checkout-payment-info' );

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
	 * Loads `WooCommerceCheckoutPaymentInfoModule` and registers Front-End render callback and REST API Endpoints.
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

		$module_json_folder_path = dirname( __DIR__, 5 ) . '/visual-builder/packages/module-library/src/components/woocommerce/checkout-payment-info/';

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
	 * Swaps login form template.
	 *
	 * By default WooCommerce displays these only when logged-out.
	 * However these templates must be shown in VB when logged-in.
	 * Hence we use these templates.
	 *
	 * @param string $template      The template.
	 * @param string $template_name Template name.
	 * @param array  $args          Arguments.
	 * @param string $template_path Template path.
	 * @param string $default_path  Default path.
	 *
	 * @return string The swapped template.
	 */
	public static function swap_template( string $template, string $template_name, array $args, string $template_path, string $default_path ): string {
		$is_template_override = in_array(
			$template_name,
			[
				'checkout/payment.php',
				'checkout/payment-method.php',
			],
			true
		);

		if ( $is_template_override ) {
			return trailingslashit( ET_BUILDER_5_DIR ) . 'server/Packages/WooCommerce/Templates/' . $template_name;
		}

		return $template;
	}

	/**
	 * Reset hooks.
	 *
	 * @return void
	 */
	public static function maybe_reset_hooks(): void {
		WooCommerceHooks::attach_wc_checkout_coupon_form();
		WooCommerceHooks::attach_wc_checkout_login_form();
		WooCommerceHooks::attach_wc_checkout_billing();
		WooCommerceHooks::attach_wc_checkout_shipping();
		WooCommerceHooks::attach_wc_checkout_order_review();
	}

	/**
	 * Handle hooks.
	 *
	 * @return void
	 */
	public static function maybe_handle_hooks(): void {
		WooCommerceHooks::detach_wc_checkout_coupon_form();
		WooCommerceHooks::detach_wc_checkout_login_form();
		WooCommerceHooks::detach_wc_checkout_billing();
		WooCommerceHooks::detach_wc_checkout_shipping();
		WooCommerceHooks::detach_wc_checkout_order_review();
	}

	/**
	 * Gets the Checkout Payment info markup.
	 *
	 * @param array $conditional_tags {
	 *     Array of conditional tags.
	 *
	 *     @type bool $is_tb Is TinyMCE editor.
	 * }
	 *
	 * @return string
	 */
	public static function get_checkout_payment_info( array $conditional_tags = [] ): string {
		if ( ! class_exists( 'WC_Shortcode_Checkout' ) || ! method_exists( 'WC_Shortcode_Checkout', 'output' ) ) {
			return '';
		}

		$is_tb              = $conditional_tags['is_tb'] ?? false;
		$is_use_placeholder = $is_tb || is_et_pb_preview();
		$is_visual_builder  = Conditions::is_rest_api_request() || Conditions::is_vb_app_window() || is_et_pb_preview();

		if ( $is_visual_builder || $is_use_placeholder ) {
			// Ensure WooCommerce objects are properly initialized for VB/TB and preview contexts.
			WooCommerceUtils::ensure_woocommerce_objects_initialized( $conditional_tags );
		}

		self::maybe_handle_hooks();

		$is_cart_empty = function_exists( 'WC' ) && WC()->cart && WC()->cart->is_empty();

		// Set fake cart contents to output Billing when no product is in cart.
		if ( ( $is_cart_empty && $is_visual_builder ) || is_et_pb_preview() ) {
			add_filter(
				'woocommerce_get_cart_contents',
				[ WooCommerceUtils::class, 'set_dummy_cart_contents' ]
			);
		}

		if ( $is_visual_builder || $is_use_placeholder ) {
			/*
			 * Show Login form in VB.
			 *
			 * The swapped login form will display irrespective of the user logged-in status.
			 *
			 * Previously swapped template (FE) will only display the form when
			 * a user is not logged-in therefore we use a different template in VB.
			 */
			add_filter(
				'wc_get_template',
				[
					self::class,
					'swap_template',
				],
				10,
				5
			);
		}

		ob_start();

		if ( is_et_pb_preview() ) {
			printf(
				'<div class="et_pb_wc_inactive__message">%s</div>',
				esc_html__( 'Woo Checkout Payment module can be used on a page and cannot be previewed.', 'et_builder_5' )
			);
		} else {
			WC_Shortcode_Checkout::output( [] );
		}

		$markup = ob_get_clean();

		if ( $is_visual_builder || $is_use_placeholder ) {
			remove_filter(
				'wc_get_template',
				[
					self::class,
					'swap_template',
				],
				10,
				5
			);
		}

		if ( ( $is_cart_empty && $is_visual_builder ) || is_et_pb_preview() ) {
			remove_filter(
				'woocommerce_get_cart_contents',
				[ WooCommerceUtils::class, 'set_dummy_cart_contents' ]
			);
		}

		self::maybe_reset_hooks();

		// Return empty string if markup is not a string.
		if ( ! is_string( $markup ) ) {
			$markup = '';
		}

		return $markup;
	}

	/**
	 * Processes custom button icons for WooCommerce Checkout Payment module.
	 *
	 * This function checks if custom button icons are enabled and returns the necessary
	 * data attributes and CSS class to apply custom icons to WooCommerce buttons.
	 *
	 * This function follows the same pattern as other WooCommerce modules like
	 * ProductAddToCart, CartNotice and CartTotals modules.
	 *
	 * @since ??
	 *
	 * @param array $attrs Module attributes.
	 *
	 * @return array {
	 *     Array containing button icon data.
	 *
	 *     @type bool  $has_custom_icons Whether the module has custom button icons.
	 *     @type array $html_attrs       HTML data attributes for button icons.
	 *     @type array $css_classes      CSS classes to add to the module.
	 * }
	 */
	public static function process_custom_button_icons( array $attrs ): array {
		static $cache = [];

		// Create cache key based on button attributes that affect the result.
		$button_attrs = $attrs['button']['decoration']['button'] ?? [];
		$cache_key    = md5( wp_json_encode( $button_attrs ) );

		// Return cached result if available.
		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		// Enhancement(D5, Button Icons) The button icons needs a comprehensive update that is in line with D5 including support for customizable breakpoints.
		// https://github.com/elegantthemes/Divi/issues/44873.
		// Get icon values for all devices.
		$icon_desktop = $attrs['button']['decoration']['button']['desktop']['value']['icon']['settings'] ?? '';
		$icon_tablet  = $attrs['button']['decoration']['button']['tablet']['value']['icon']['settings'] ?? '';
		$icon_phone   = $attrs['button']['decoration']['button']['phone']['value']['icon']['settings'] ?? '';

		// Check if any custom icon is defined.
		$has_custom_icons = ! empty( $icon_desktop ) || ! empty( $icon_tablet ) || ! empty( $icon_phone );

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
		$processed_icon_desktop = ! empty( $icon_desktop ) ? esc_attr( Utils::process_font_icon( $icon_desktop ) ) : '';
		$processed_icon_tablet  = ! empty( $icon_tablet ) ? esc_attr( Utils::process_font_icon( $icon_tablet ) ) : '';
		$processed_icon_phone   = ! empty( $icon_phone ) ? esc_attr( Utils::process_font_icon( $icon_phone ) ) : '';

		$result = [
			'has_custom_icons' => true,
			'html_attrs'       => [
				'data-button-class'       => '#place_order',
				'data-button-icon'        => $processed_icon_desktop,
				'data-button-icon-tablet' => $processed_icon_tablet,
				'data-button-icon-phone'  => $processed_icon_phone,
			],
			'css_classes'      => [
				'button',
				'et_pb_woo_custom_button_icon',
			],
		];

		// Cache and return result.
		$cache[ $cache_key ] = $result;

		return $result;
	}
}
