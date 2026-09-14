<?php
/**
 * Module Library: WooCommerceProductAddToCart Module
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductAddToCart;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\IconLibrary\IconFont\Utils;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewUtils;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\FormField\FormFieldStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_Block_Type_Registry;
use WP_Block;

/**
 * WooCommerceProductAddToCartModule class.
 *
 * This class implements the functionality of a call-to-action component
 * in a frontend application. It provides functions for rendering the
 * WooCommerceProductAddToCart module,
 * managing REST API endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class WooCommerceProductAddToCartModule implements DependencyInterface {

	/**
	 * Render callback for the WooCommerceProductAddToCart module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ WooCommerceProductAddToCartEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $content                     The block's content.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the WooCommerceProductAddToCart module.
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
	 * WooCommerceProductAddToCartModule::render_callback( $attrs, $content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements ): string {
		// Get breakpoints states info for dynamic access to attributes.
		$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
		$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
		$default_state           = $breakpoints_states_info->default_state();

		// Get parameters from attributes.
		$product_value       = $attrs['content']['advanced']['product'][ $default_breakpoint ][ $default_state ] ?? null;
		$product_id          = $product_value ?? WooCommerceUtils::get_default_product();
		$loop_post_id        = absint( $attrs['__loop_post_id'] ?? 0 );
		$loop_parsed_post_id = absint( $block->parsed_block['attrs']['__loop_post_id'] ?? 0 );
		$parent              = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );
		$parent_loop_post_id = $parent && isset( $parent->attrs['__loop_post_id'] )
			? absint( $parent->attrs['__loop_post_id'] )
			: 0;

		$loop_context_post_id = $loop_post_id > 0
			? $loop_post_id
			: ( $loop_parsed_post_id > 0 ? $loop_parsed_post_id : $parent_loop_post_id );
		// Use the loop item product when "current" is selected in a loop.
		if ( $loop_context_post_id > 0 && ( 'current' === $product_id || null === $product_value ) ) {
			$product_id = $loop_context_post_id;
		}

		$add_to_cart_html = self::get_add_to_cart(
			[
				'product' => $product_id,
			]
		);

		// Render empty string if no output is generated to avoid unwanted vertical space.
		if ( '' === $add_to_cart_html ) {
			return '';
		}

		// Process custom button icons.
		$button_icons_data = self::process_custom_button_icons( $attrs );

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
					HTMLUtility::render(
						[
							'tag'               => 'div',
							'tagEscaped'        => true,
							'attributes'        => [
								'class' => 'et_pb_module_inner',
							],
							'childrenSanitizer' => 'et_core_esc_previously',
							'children'          => $add_to_cart_html,
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
	 * arguments. It is used in the `render_callback` function of the WooCommerceProductAddToCart module.
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
	 * WooCommerceProductAddToCartModule::module_classnames($args);
	 * ```
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		// Get breakpoints states info for dynamic access to attributes.
		$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
		$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
		$default_state           = $breakpoints_states_info->default_state();

		$show_quantity        = $attrs['elements']['advanced']['showQuantity'][ $default_breakpoint ][ $default_state ] ?? 'on';
		$show_stock           = $attrs['elements']['advanced']['showStock'][ $default_breakpoint ][ $default_state ] ?? 'on';
		$use_focus_border     = $attrs['dropdownMenus']['advanced']['focusUseBorder'][ $default_breakpoint ][ $default_state ] ?? 'off';
		$field_label_position = $attrs['fieldLabels']['advanced']['fieldLabelPosition'][ $default_breakpoint ][ $default_state ] ?? 'inline';

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

		if ( 'off' === $show_quantity ) {
			$classnames_instance->add( 'et_pb_hide_input_quantity' );
		}

		if ( 'off' === $show_stock ) {
			$classnames_instance->add( 'et_pb_hide_stock' );
		}

		if ( 'on' === $use_focus_border ) {
			$classnames_instance->add( 'et_pb_with_focus_border' );
		}

		$classnames_instance->add( "et_pb_fields_label_position_{$field_label_position}" );

		// Add custom button icon class if needed.
		$button_icons_data = self::process_custom_button_icons( $attrs );
		if ( $button_icons_data['has_custom_icons'] ) {
			$classnames_instance->add( $button_icons_data['css_classes'], true );
		}

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
	 * WooCommerceProductAddToCart module script data.
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
	 * WooCommerceProductAddToCartModule::module_script_data( $args );
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
				'attrName'        => 'module',
				'scriptDataProps' => [
					'animation' => [
						'selector' => $selector,
					],
				],
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
							'et_pb_hide_input_quantity' => $attrs['elements']['advanced']['showQuantity'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_hide_stock' => $attrs['elements']['advanced']['showStock'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_with_focus_border' => $attrs['dropdownMenus']['advanced']['focusUseBorder'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_fields_label_position_inline' => $attrs['fieldLabels']['advanced']['fieldLabelPosition'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'inline' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_fields_label_position_stacked' => $attrs['fieldLabels']['advanced']['fieldLabelPosition'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'stacked' === $value ? 'add' : 'remove';
						},
					],
				],
			]
		);
	}

	/**
	 * Dropdown arrow positioning style declaration.
	 *
	 * Calculates dropdown arrow margin values based on the dropdown menu's margin values.
	 * The Dropdown's arrow margin values depend on the actual Dropdown margin values.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/dropdown-arrow-positioning dropdownArrowPositioningStyleDeclaration}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type array $attrValue The attribute value containing margin information.
	 *     @type bool  $important Optional. Whether to add `!important` to the CSS. Default `false`.
	 * }
	 *
	 * @return string The generated CSS style declaration.
	 */
	public static function dropdown_arrow_positioning_style_declaration( array $args ): string {
		$attr_value = $args['attrValue'] ?? [];
		$important  = $args['important'] ?? false;

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => $important,
			]
		);

		$margin = $attr_value['margin'] ?? null;

		if ( $margin ) {
			$margin_bottom = $margin['bottom'] ?? null;
			$margin_left   = $margin['left'] ?? null;

			// Only add styles if we have bottom or left margin values.
			if ( $margin_bottom || $margin_left ) {
				$bottom_value = empty( $margin_bottom ) ? '0px' : $margin_bottom;
				$left_value   = empty( $margin_left ) ? '0px' : $margin_left;

				$style_declarations->add( 'margin-top', "calc(3px - {$bottom_value})" );
				$style_declarations->add( 'right', "calc(10px - {$left_value})" );
			}
		}

		return $style_declarations->value();
	}

	/**
	 * Processes custom button icons for WooCommerce modules.
	 *
	 * This function checks if custom button icons are enabled and returns the necessary
	 * data attributes and CSS class to apply custom icons to WooCommerce buttons.
	 *
	 * This function is equivalent to the D4 function
	 * {@link ET_Builder_Module_Helper_Woocommerce_Modules::process_custom_button_icons}
	 * located in `includes/builder/module/helpers/WoocommerceModules.php`.
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

		$module_defaults = ModuleRegistration::generate_default_attrs( 'divi/woocommerce-product-add-to-cart', 'default' );
		$defaults_button = $module_defaults['button']['decoration']['button'] ?? [];
		$saved_button    = $attrs['button']['decoration']['button'] ?? [];
		$merged_button   = array_replace_recursive( $defaults_button, $saved_button );

		// Create cache key based on merged button attributes that affect the result.
		$cache_key = md5( wp_json_encode( $merged_button ) );

		// Return cached result if available.
		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		// Enhancement(D5, Button Icons) The button icons needs a comprehensive update that is in line with D5 including support for customizable breakpoints.
		// Merge defaults before reading `icon.enable` so exports match FE/CSS (saved layouts often omit `enable`).
		$desktop_value = $merged_button['desktop']['value'] ?? [];
		$tablet_value  = $merged_button['tablet']['value'] ?? [];
		$phone_value   = $merged_button['phone']['value'] ?? [];

		$icon_enable = $desktop_value['icon']['enable'] ?? null;

		// Align with `button_icon_style_declaration`: only explicit `off` disables icons.
		$has_custom_button = 'off' !== $icon_enable;

		// Get icon values for all devices.
		$icon_desktop = $has_custom_button ? ( $desktop_value['icon']['settings'] ?? '' ) : '';
		$icon_tablet  = $has_custom_button ? ( $tablet_value['icon']['settings'] ?? '' ) : '';
		$icon_phone   = $has_custom_button ? ( $phone_value['icon']['settings'] ?? '' ) : '';

		// Check if any custom icon is defined.
		$has_custom_icons = $has_custom_button && ( ! empty( $icon_desktop ) || ! empty( $icon_tablet ) || ! empty( $icon_phone ) );

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
				'data-button-class'          => 'single_add_to_cart_button',
				'data-button-icon'           => $processed_icon_desktop,
				'data-button-icon-tablet'    => $processed_icon_tablet,
				'data-button-icon-phone'     => $processed_icon_phone,
				'data-button-icon-placement' => $desktop_value['icon']['placement'] ?? 'right',
				'data-button-icon-on-hover'  => $desktop_value['icon']['onHover'] ?? 'on',
			],
			'css_classes'      => [ 'et_pb_woo_custom_button_icon' ],
		];

		// Cache and return result.
		$cache[ $cache_key ] = $result;
		return $result;
	}

	/**
	 * Button icon style declaration for WooCommerce Product Add To Cart module.
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
					'color'       => true,
					'content'     => true,
					'display'     => true,
					'font-family' => true,
					'font-size'   => true,
					'font-weight' => true,
					'line-height' => true,
					'margin-left' => true,
					'opacity'     => true,
					'left'        => true,
					'right'       => true,
				],
			]
		);

		$enable        = $attr_value['icon']['enable'] ?? $default_attr_value['icon']['enable'] ?? null;
		$color         = $attr_value['icon']['color'] ?? $default_attr_value['icon']['color'] ?? null;
		$on_hover      = $attr_value['icon']['onHover'] ?? $default_attr_value['icon']['onHover'] ?? null;
		$raw_placement = $attr_value['icon']['placement'] ?? $default_attr_value['icon']['placement'] ?? 'right';
		$placement     = 'left' === $raw_placement ? 'left' : 'right';
		$breakpoint    = $params['breakpoint'] ?? 'desktop';

		$has_custom_icon = ! empty( $icon_settings['unicode'] );

		if ( 'off' !== $enable && ! empty( $icon_settings ) && Utils::process_font_icon( $icon_settings ) ) {
			$font_family    = Utils::is_fa_icon( $icon_settings ) ? 'FontAwesome' : 'ETmodules';
			$font_weight    = $icon_settings['weight'] ?? '400';
			$data_attr_name = 'desktop' === $breakpoint ? 'data-icon' : 'data-icon-' . strtolower( preg_replace( '/([A-Z])/', '-$1', $breakpoint ) );

			$style_declarations->add( 'content', "attr({$data_attr_name})" );
			$style_declarations->add( 'display', 'inline-block' );
			$style_declarations->add( 'font-family', $font_family );
			$style_declarations->add( 'font-weight', $font_weight );
			$style_declarations->add( 'font-size', 'inherit' );
			$style_declarations->add( 'line-height', '1.7em' );
		}

		if ( 'off' !== $enable && 'off' === $on_hover ) {
			$style_declarations->add( 'opacity', '1' );
		}

		if ( 'off' !== $enable && $has_custom_icon ) {
			$style_declarations->add( 'margin-left', 'left' === $placement ? '-1.3em' : '0.3em' );
		}

		if ( 'off' !== $enable && $color ) {
			$style_declarations->add( 'color', $color );
		}

		// Central ButtonIcon styles handle icon content, font, opacity, and shared spacing.
		if ( 'off' !== $enable && 'off' === $on_hover && ! $has_custom_icon ) {
			$style_declarations->add( 'top', '50%' );
			$style_declarations->add( 'transform', 'translateY(-50%)' );

			if ( 'left' === $placement ) {
				$style_declarations->add( 'margin-left', '-1.3em' );
				$style_declarations->add( 'right', 'auto' );
			} else {
				$style_declarations->add( 'margin-left', '0' );
				$style_declarations->add( 'left', 'auto' );
			}

			$style_declarations->add( $placement, 'inherit' );
		}

		return $style_declarations->value();
	}

	/**
	 * Hide WooCommerce / Divi default button pseudo-element icons when the module disables icons.
	 *
	 * Theme and Woo styles often add an ETmodules glyph on `:before` or `:after`. Without this rule,
	 * disabling icons removes Divi's icon CSS but leaves those defaults visible on the frontend.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array $attrValue The value (breakpoint > state > value) of module attribute.
	 *     @type array $defaultAttrValue The default value of module attribute.
	 * }
	 *
	 * @return string Style declarations for disabled-icon suppression.
	 */
	public static function button_icon_disabled_style_declaration( array $params ): string {
		$attr_value         = $params['attrValue'] ?? [];
		$default_attr_value = $params['defaultAttrValue'] ?? [];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => [
					'display' => true,
				],
			]
		);

		$enable = $attr_value['icon']['enable'] ?? $default_attr_value['icon']['enable'] ?? null;

		if ( 'off' === $enable ) {
			$style_declarations->add( 'display', 'none' );
		}

		return $style_declarations->value();
	}

	/**
	 * Button icon opposite pseudo-element style declaration for WooCommerce Product Add To Cart module.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array $attrValue The value (breakpoint > state > value) of module attribute.
	 *     @type array $defaultAttrValue The default value of module attribute.
	 * }
	 *
	 * @return string The opposite pseudo-element style declaration.
	 */
	public static function button_icon_opposite_style_declaration( array $params ): string {
		$attr_value         = $params['attrValue'] ?? [];
		$default_attr_value = $params['defaultAttrValue'] ?? [];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => [
					'display' => true,
				],
			]
		);

		$enable = $attr_value['icon']['enable'] ?? $default_attr_value['icon']['enable'] ?? null;

		if ( 'off' !== $enable ) {
			$style_declarations->add( 'display', 'none' );
		}

		return $style_declarations->value();
	}

	/**
	 * Button spacing icon style declaration for WooCommerce Product Add To Cart module.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array $attrValue The value (breakpoint > state > value) of module attribute.
	 *     @type array $defaultAttrValue The default value of module attribute.
	 * }
	 *
	 * @return string The button spacing icon style declaration.
	 */
	public static function button_spacing_icon_style_declaration( array $params ): string {
		$attr_value         = $params['attrValue'] ?? [];
		$default_attr_value = $params['defaultAttrValue'] ?? [];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => [
					'padding'       => true,
					'padding-left'  => true,
					'padding-right' => true,
				],
			]
		);

		$placement = $attr_value['icon']['placement'] ?? $default_attr_value['icon']['placement'] ?? null;
		$on_hover  = $attr_value['icon']['onHover'] ?? $default_attr_value['icon']['onHover'] ?? null;
		$enable    = $attr_value['icon']['enable'] ?? $default_attr_value['icon']['enable'] ?? null;
		$padding   = $attr_value['padding'] ?? $default_attr_value['padding'] ?? [];

		$is_button_icon_left   = 'left' === $placement;
		$current_right_padding = $padding['right'] ?? null;
		$current_left_padding  = $padding['left'] ?? null;

		if ( 'off' === $on_hover && 'on' === $enable ) {
			$style_declarations->add( 'padding-right', ! $is_button_icon_left ? '2em' : '0.7em' );
		}

		if ( 'off' === $on_hover && 'on' === $enable ) {
			$style_declarations->add( 'padding-left', ! $is_button_icon_left ? '0.7em' : '2em' );
		}

		$desktop_padding     = $params['attr']['desktop']['value']['padding'] ?? $default_attr_value['padding'] ?? [];
		$has_desktop_padding = ! empty( $desktop_padding['top'] )
			|| ! empty( $desktop_padding['right'] )
			|| ! empty( $desktop_padding['bottom'] )
			|| ! empty( $desktop_padding['left'] );

		if ( 'off' === $enable && ! $has_desktop_padding ) {
			if ( empty( $padding ) ) {
				$style_declarations->add( 'padding', '0.3em 1em' );
			} else {
				// Add default padding for right and left if not set.
				if ( ! $current_right_padding ) {
					$style_declarations->add( 'padding-right', '1em' );
				}

				if ( ! $current_left_padding ) {
					$style_declarations->add( 'padding-left', '1em' );
				}
			}
		}

		return $style_declarations->value();
	}

	/**
	 * Button hover spacing icon style declaration for WooCommerce Product Add To Cart module.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array $attrValue The value (breakpoint > state > value) of module attribute.
	 *     @type array $defaultAttrValue The default value of module attribute.
	 * }
	 *
	 * @return string The button hover spacing icon style declaration.
	 */
	public static function button_spacing_icon_hover_style_declaration( array $params ): string {
		$attr_value         = $params['attrValue'] ?? [];
		$default_attr_value = $params['defaultAttrValue'] ?? [];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => [
					'padding-left'  => true,
					'padding-right' => true,
				],
			]
		);

		$enable    = $attr_value['icon']['enable'] ?? $default_attr_value['icon']['enable'] ?? null;
		$on_hover  = $attr_value['icon']['onHover'] ?? $default_attr_value['icon']['onHover'] ?? null;
		$placement = $attr_value['icon']['placement'] ?? $default_attr_value['icon']['placement'] ?? null;

		if ( 'on' === $enable && 'left' === $placement && ( 'on' === $on_hover || null === $on_hover ) ) {
			$style_declarations->add( 'padding-left', '2em' );
			$style_declarations->add( 'padding-right', '0.7em' );
		}

		return $style_declarations->value();
	}

	/**
	 * Button icon hover style declaration for WooCommerce Product Add To Cart module.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array $attrValue The value (breakpoint > state > value) of module attribute.
	 *     @type array $defaultAttrValue The default value of module attribute.
	 * }
	 *
	 * @return string The button icon hover style declaration.
	 */
	public static function button_icon_hover_style_declaration( array $params ): string {
		$attr_value         = $params['attrValue'] ?? [];
		$default_attr_value = $params['defaultAttrValue'] ?? [];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => [
					'margin-left' => true,
					'opacity'     => true,
				],
			]
		);

		$icon_settings = $attr_value['icon']['settings'] ?? $default_attr_value['icon']['settings'] ?? [];
		$enable        = $attr_value['icon']['enable'] ?? $default_attr_value['icon']['enable'] ?? null;
		$on_hover      = $attr_value['icon']['onHover'] ?? $default_attr_value['icon']['onHover'] ?? null;
		$raw_placement = $attr_value['icon']['placement'] ?? $default_attr_value['icon']['placement'] ?? 'right';
		$placement     = 'left' === $raw_placement ? 'left' : 'right';
		$has_icon      = ! empty( $icon_settings );

		// Hover styles only apply when the icon is enabled and onHover is not off.
		if ( 'off' !== $enable && 'off' !== $on_hover && $has_icon ) {
			if ( 'left' === $placement ) {
				$style_declarations->add( 'margin-left', '-1.3em' );
				$style_declarations->add( 'right', 'auto' );
				$style_declarations->add( 'opacity', '1' );
			} elseif ( 'right' === $placement ) {
				$style_declarations->add( 'margin-left', '.3em' );
				$style_declarations->add( 'left', 'auto' );
				$style_declarations->add( 'opacity', '1' );
			}
		}

		return $style_declarations->value();
	}

	/**
	 * WooCommerceProductAddToCart Module's style components.
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
		$attrs                       = $args['attrs'] ?? [];
		$elements                    = $args['elements'];
		$settings                    = $args['settings'] ?? [];
		$style_group                 = $args['styleGroup'] ?? 'module';
		$default_printed_style_attrs = $args['defaultPrintedStyleAttrs'] ?? [];

		// Extract the order class.
		$order_class = $args['orderClass'] ?? '';

		$is_inside_sticky_module   = $elements->get_is_inside_sticky_module();
		$sticky_parent_order_class = $elements->get_sticky_parent_order_class();

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
											'selector' => "{$order_class} td.label",
											'attr'     => $attrs['module']['advanced']['text'] ?? [],
											'propertySelectors' => [
												'textShadow' => [
													'desktop' => [
														'value' => [
															'text-shadow' => "{$order_class} td.label",
														],
													],
												],
											],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} form.cart .variations td.value span:after",
											'attr'     => $attrs['dropdownMenus']['decoration']['spacing'] ?? [],
											'declarationFunction' => [ self::class, 'dropdown_arrow_positioning_style_declaration' ],
										],
									],
								],
							],
						]
					),
					// Button.
					$elements->style(
						[
							'attrName'   => 'button',
							'styleProps' => [
								'button'         => [
									'affectingAttrs' => $button_affecting_attrs,
									'important'      => [
										'desktop' => [
											'value' => [
												'color'       => true,
												'content'     => true,
												'display'     => true,
												'font-family' => true,
												'font-size'   => true,
												'line-height' => true,
												'margin-left' => true,
												'opacity'     => true,
											],
										],
									],
								],
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} .button",
											// Keep icon declarations scoped to button attrs; font attrs can carry default icon data.
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
											'selector' => "{$order_class} .button",
											'attr'     => $attrs['button']['decoration']['button'] ?? [],
											'declarationFunction' => [ self::class, 'button_icon_hover_style_declaration' ],
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

												return $params['selector'] . ':hover' . $pseudo_element;
											},
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} .button",
											'attr'     => $attrs['button']['decoration']['button'] ?? [],
											'declarationFunction' => [ self::class, 'button_icon_opposite_style_declaration' ],
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
												$pseudo_element = 'left' === $placement ? ':after' : ':before';

												return $params['selector'] . $pseudo_element;
											},
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} .button",
											'attr'     => $attrs['button']['decoration']['button'] ?? [],
											'declarationFunction' => [ self::class, 'button_spacing_icon_style_declaration' ],
											'selectorFunction' => function ( $params ) {
												return $params['selector'] . ', ' . $params['selector'] . ':hover';
											},
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} .button",
											'attr'     => $attrs['button']['decoration']['button'] ?? [],
											'declarationFunction' => [ self::class, 'button_spacing_icon_hover_style_declaration' ],
											'selectorFunction' => function ( $params ) {
												return $params['selector'] . ':hover';
											},
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector'            => "{$order_class} .button:before, {$order_class} .button:after, {$order_class} .button:hover:before, {$order_class} .button:hover:after",
											'attr'                => $attrs['button']['decoration']['button'] ?? [],
											'declarationFunction' => [ self::class, 'button_icon_disabled_style_declaration' ],
										],
									],
								],
								'attrsFilter'    => function ( $decoration_attrs ) use ( $style_group ): ?array {
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
						]
					),
					// Dropdown Menus.
					FormFieldStyle::style(
						[
							'selector'               => "{$order_class}.et_pb_module .et_pb_module_inner form.cart .variations td select",
							'attr'                   => $attrs['dropdownMenus'] ?? [],
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
							'propertySelectors'      => [
								'spacing' => [
									'desktop' => [
										'value' => [
											'margin'  => "{$order_class} select",
											'padding' => "{$order_class} select",
										],
									],
								],
								'focus'   => [
									'font' => [
										'font' => [
											'desktop' => [
												'value' => [
													'color' => "{$order_class}.et_pb_module .et_pb_module_inner form.cart .variations td select, {$order_class}.et_pb_module .et_pb_module_inner form.cart .variations td select option, {$order_class}.et_pb_module .et_pb_module_inner form.cart .variations td select + label",
												],
											],
										],
									],
								],
							],
							'important'              => [
								'border'  => true,
								'font'    => true,
								'spacing' => true,
								'focus'   => [
									'border' => true,
									'font'   => true,
								],
							],
						]
					),
					// Form Fields.
					FormFieldStyle::style(
						[
							'selector'               => implode(
								', ',
								[
									"{$order_class} input",
									"{$order_class} .quantity input.qty",
								]
							),
							'selectors'              => [
								'desktop' => [
									'value' => "{$order_class} input, {$order_class} .quantity input.qty",
									'hover' => "{$order_class} input:hover, {$order_class} .quantity input.qty:hover",
								],
							],
							'attr'                   => $attrs['field'] ?? [],
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
							'propertySelectors'      => [
								'label' => [
									'font' => [
										'font'       => [
											'desktop' => [
												'value' => array_merge(
													array_fill_keys(
														[
															'color',
															'font-family',
															'font-size',
															'font-style',
															'font-weight',
															'letter-spacing',
															'line-height',
															'text-align',
															'text-decoration',
															'text-transform',
														],
														implode(
															', ',
															[
																"{$order_class}.et_pb_module .et_pb_module_inner form.cart .variations label",
																"{$order_class} td.woocommerce-grouped-product-list-item__label a",
															]
														)
													),
													[
														'text-align' => "{$order_class} th.label",
													]
												),
												'hover' => array_merge(
													array_fill_keys(
														[
															'color',
															'font-family',
															'font-size',
															'font-style',
															'font-weight',
															'letter-spacing',
															'line-height',
															'text-align',
															'text-decoration',
															'text-transform',
														],
														implode(
															', ',
															[
																"{$order_class}.et_pb_module .et_pb_module_inner form.cart .variations label:hover",
																"{$order_class} td.woocommerce-grouped-product-list-item__label a:hover",
															]
														)
													),
													[
														'text-align' => "{$order_class} th.label:hover",
													]
												),
											],
										],
										'textShadow' => [
											'desktop' => [
												'value' => [
													'text-shadow' => implode(
														', ',
														[
															"{$order_class}.et_pb_module .et_pb_module_inner form.cart .variations label",
															"{$order_class} td.woocommerce-grouped-product-list-item__label a",
														]
													),
												],
												'hover' => [
													'text-shadow' => implode(
														', ',
														[
															"{$order_class}.et_pb_module .et_pb_module_inner form.cart .variations label:hover",
															"{$order_class} td.woocommerce-grouped-product-list-item__label a:hover",
														]
													),
												],
											],
										],
									],
								],
							],
							'important'              => [
								'border'  => true,
								'font'    => true,
								'spacing' => true,
								'focus'   => [
									'border' => true,
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
	 * Get the custom CSS fields for the Divi WooCommerceProductAddToCart module.
	 *
	 * This function retrieves the custom CSS fields defined for the Divi WooCommerceProductAddToCart module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /api/js/divi-module-library/functions/generateDefaultAttrs cssFields}
	 * located in `@divi/module-library`. Note that this function does not have
	 * a `label` property on each array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi WooCommerceProductAddToCart module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = CustomCssTrait::custom_css();
	 * // Returns an array of custom CSS fields for the WooCommerceProductAddToCart module.
	 * ```
	 */
	public static function custom_css(): array {
		$registered_block = WP_Block_Type_Registry::get_instance()->get_registered( 'divi/woocommerce-product-add-to-cart' );

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
	 * Loads `WooCommerceProductAddToCartModule` and registers Front-End render callback and REST API Endpoints.
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
			'divi_module_library_module_default_attributes_divi/woocommerce-product-add-to-cart',
			[ WooCommerceUtils::class, 'process_dynamic_attr_defaults' ],
			10,
			2
		);

		$module_json_folder_path = dirname( __DIR__, 5 ) . '/visual-builder/packages/module-library/src/components/woocommerce/product-add-to-cart/';

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
	 * Retrieves the add to cart markup for a given set of arguments.
	 *
	 * This function uses the `WooCommerceUtils::render_module_template()` to render the module template
	 * for the add to cart markup based on the provided arguments.
	 *
	 * WooCommerce already provides the correct product permalink via $product->get_permalink(),
	 * so no filter is needed to modify the form action URL. This ensures Loop Builder products
	 * redirect to their own product pages correctly.
	 * Compatibility with WooCommerce Product Add-ons is added.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Optional. An array of arguments for rendering the add to cart markup.
	 *
	 *     @type string $product Optional. The product identifier.
	 * }
	 *
	 * @param array $conditional_tags {
	 *     Optional. An array of conditional tags.
	 *
	 *     @type string $is_tb Optional.  Whether the theme builder is enabled. Default 'false'.
	 *     @type string $is_bfb Optional. Whether the builder is in the frontend builder. Default 'false'.
	 *     @type string $is_bfb_activated Optional. Whether the builder is activated. Default 'false'.
	 * }
	 *
	 * @return string The rendered add to cart markup or a placeholder if in theme builder mode.
	 *
	 * @example:
	 * ```php
	 * $add_to_cart = WooCommerceProductAddToCartModule::get_add_to_cart();
	 * // Returns the add to cart markup for the current product.
	 *
	 * $add_to_cart = WooCommerceProductAddToCartModule::get_add_to_cart( [ 'product' => 123 ] );
	 * // Returns the add to cart markup for the product with ID 123.
	 * ```
	 */
	public static function get_add_to_cart( array $args = [], array $conditional_tags = [] ): string {
		// Needed for product post-type.
		if ( ! isset( $args['product'] ) ) {
			$args['product'] = WooCommerceUtils::get_product_id( 'current' );
		}

		$output = WooCommerceUtils::render_module_template(
			'woocommerce_template_single_add_to_cart',
			$args,
			[ 'product', 'post' ]
		);

		return $output;
	}
}
