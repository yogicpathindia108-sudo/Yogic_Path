<?php
/**
 * Module Library: WooCommerceCartTotals Module
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\CartTotals;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\ScriptData;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewUtils;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Element\ElementStyle;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\Module\Options\FormField\FormFieldStyle;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use ET\Builder\Packages\IconLibrary\IconFont\Utils;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use WP_Block_Type_Registry;
use WP_Block;

/**
 * WooCommerceCartTotalsModule class.
 *
 * This class implements the functionality of a call-to-action component
 * in a frontend application. It provides functions for rendering the
 * WooCommerceCartTotals module,
 * managing REST API endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class WooCommerceCartTotalsModule implements DependencyInterface {

	/**
	 * Render callback for the WooCommerceCartTotals module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ WooCommerceCartTotalsEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $content                     The block's content.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the WooCommerceCartTotals module.
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
	 * WooCommerceCartTotalsModule::render_callback( $attrs, $content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements ): string {
		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		// Get cart totals markup.
		$cart_totals_output = self::get_cart_totals();

		// Process custom button icons.
		$button_icon_data = self::process_custom_button_icons( $attrs );
		if ( $button_icon_data['has_custom_icons'] ) {
			$cart_totals_output = self::_inject_custom_button_icon_attributes(
				$cart_totals_output,
				$button_icon_data['html_attrs']
			);
		}

		$output = Module::render(
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
							'children'          => $cart_totals_output,
						]
					),
				],
			]
		);

		return empty( $cart_totals_output ) ? '' : $output;
	}

	/**
	 * Generate classnames for the module.
	 *
	 * This function generates classnames for the module based on the provided
	 * arguments. It is used in the `render_callback` function of the WooCommerceCartTotals module.
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
	 * WooCommerceCartTotalsModule::module_classnames($args);
	 * ```
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		// Add WooCommerce specific classname.
		$classnames_instance->add( 'woocommerce-cart' );

		// Add text orientation classnames.
		$classnames_instance->add(
			TextClassnames::text_options_classnames(
				$attrs['module']['advanced']['text'] ?? [],
				[
					'color' => false,
				]
			),
			true
		);

		// Add element classnames for decoration.
		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					'attrs' => $attrs['module']['decoration'] ?? [],
				]
			)
		);

		// Add cart empty classname if cart is empty (for FE only).
		if (
			! Conditions::is_rest_api_request()
			&& ! is_et_pb_preview()
			&& WooCommerceUtils::is_woocommerce_cart_available()
			&& WC()->cart->is_empty()
		) {
			$classnames_instance->add( 'et_pb_wc_cart_empty' );
		}

		// Add button icon support classnames.
		$button_icon_data = self::process_custom_button_icons( $attrs );
		if ( $button_icon_data['has_custom_icons'] ) {
			foreach ( $button_icon_data['css_classes'] as $css_class ) {
				$classnames_instance->add( $css_class );
			}
		}
	}

	/**
	 * Set front-end script data for the Cart Totals module.
	 *
	 * This function is responsible for setting the frontend script data for the
	 * Cart Totals module. It registers the necessary data items for the
	 * module based on the provided arguments.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments for setting the front-end script data.
	 *
	 *     @type string $selector The module selector.
	 *     @type array  $attrs    The module attributes.
	 * }
	 *
	 * @return void
	 *
	 * @example
	 * ```php
	 * WooCommerceCartTotalsModule::set_front_end_data( [
	 *   'selector' => '#cart-totals-1',
	 *   'attrs'    => [
	 *     // Module attributes...
	 *   ],
	 * ] );
	 * ```
	 */
	public static function set_front_end_data( array $args ): void {
		// Script data is not needed in VB.
		if ( Conditions::is_vb_enabled() ) {
			return;
		}

		$selector = $args['selector'] ?? '';
		$attrs    = $args['attrs'] ?? [];

		if ( ! $selector || ! $attrs ) {
			return;
		}

		// Register front-end data item.
		ScriptData::add_data_item(
			[
				'data_name'    => 'woocommerce_cart_totals',
				'data_item_id' => null,
				'data_item'    => [
					'selector' => $selector,
					'data'     => [
						'shippingCalculatorEnabled' => true, // Enable shipping calculator by default.
					],
				],
			]
		);
	}

	/**
	 * WooCommerceCartTotals module script data.
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
	 * WooCommerceCartTotalsModule::module_script_data( $args );
	 * ```
	 */
	public static function module_script_data( array $args ): void {
		// Assign variables.
		$selector = $args['selector'] ?? '';
		$attrs    = $args['attrs'] ?? [];
		$elements = $args['elements'];

		// Element Script Data Options.
		$elements->script_data(
			[
				'attrName' => 'module',
			]
		);

		// Set module specific front-end data.
		self::set_front_end_data(
			[
				'selector' => $selector,
				'attrs'    => $attrs,
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
	 * Gets collapse table gutters borders style declarations.
	 *
	 * This function generates CSS styles for table border collapse and spacing.
	 * It matches the VB implementation in collapse-table-gutters-borders/index.ts.
	 *
	 * @since ??
	 *
	 * @param array $params Style declaration parameters.
	 * @param array $attrs The full module attributes array.
	 *
	 * @return string Generated CSS declarations.
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
		$attr_value = $attrs['table']['advanced']['collapseTableGuttersBorders'][ $breakpoint ][ $state ] ?? 'off';

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		if ( 'on' === $attr_value ) {
			$style_declarations->add( 'border-collapse', 'collapse' );

			// Border spacing property has no effect when `border-collapse: separate`.
			// Hence we set the border-spacing as `0`.
			//
			// Note that `border-spacing` can exist irrespective of the value set
			// in the `border-collapse` property.
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
	 * Gets border style declarations for table cells.
	 *
	 * This function generates CSS border styles to override WooCommerce border styles.
	 * WooCommerce uses side-specific properties (border-top, border-bottom, border-left, border-right)
	 * which have higher CSS specificity than Divi's individual border properties.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     Style declaration parameters.
	 *
	 *     @type array|null $attrValue The border decoration attribute value.
	 *     @type array|null $defaultAttrValue The default border decoration attribute value.
	 * }
	 *
	 * @return string Generated CSS declarations.
	 */
	public static function border_style_declaration( array $params ): string {
		$attr_value         = $params['attrValue'] ?? [];
		$default_attr_value = $params['defaultAttrValue'] ?? [];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => true,
			]
		);

		// Array of border sides that need to override.
		$sides = [
			'top',
			'right',
			'bottom',
			'left',
		];

		// Default border side values used as fallback.
		$default_sides = [
			'top' => [
				'width' => '1px',
			],
			'all' => [
				'width' => '0px',
				'style' => 'solid',
				'color' => 'rgba(0, 0, 0, 0.1)',
			],
		];

		// Generate border styles for each side.
		foreach ( $sides as $side ) {
			$side_style = self::_generate_border_side_style( $side, $attr_value, $default_attr_value, $default_sides );

			// Only add declaration if style is not empty (avoids default values).
			if ( ! empty( $side_style ) ) {
				$style_declarations->add( "border-{$side}", $side_style );
			}
		}

		return $style_declarations->value();
	}

	/**
	 * Generates CSS border style string for a specific side.
	 *
	 * @since ??
	 *
	 * @param string $side The border side to generate style for.
	 * @param array  $attr_value Current attribute value.
	 * @param array  $default_attr_value Default attribute value.
	 * @param array  $default_sides Default side values.
	 *
	 * @return string CSS border style string or empty string if using default values.
	 */
	private static function _generate_border_side_style( string $side, array $attr_value, array $default_attr_value, array $default_sides ): string {
		// Get width with cascading fallback: side-specific → 'all' → default.
		$width = $attr_value['styles'][ $side ]['width'] ?? $default_attr_value['styles'][ $side ]['width'] ?? null;

		if ( ! $width ) {
			$width = $attr_value['styles']['all']['width'] ?? $default_attr_value['styles']['all']['width'] ?? null;
		}

		$width_default = $default_sides[ $side ]['width'] ?? $default_sides['all']['width'] ?? null;

		if ( ! $width ) {
			$width = $width_default;
		}

		// Get style with cascading fallback: side-specific → 'all' → default.
		$style = $attr_value['styles'][ $side ]['style'] ?? $default_attr_value['styles'][ $side ]['style'] ?? null;

		if ( ! $style ) {
			$style = $attr_value['styles']['all']['style'] ?? $default_attr_value['styles']['all']['style'] ?? null;
		}

		$style_default = $default_sides[ $side ]['style'] ?? $default_sides['all']['style'] ?? null;

		if ( ! $style ) {
			$style = $style_default;
		}

		// Get color with cascading fallback: side-specific → 'all' → default.
		$color = $attr_value['styles'][ $side ]['color'] ?? $default_attr_value['styles'][ $side ]['color'] ?? null;

		if ( ! $color ) {
			$color = $attr_value['styles']['all']['color'] ?? $default_attr_value['styles']['all']['color'] ?? null;
		}

		$color_default = $default_sides[ $side ]['color'] ?? $default_sides['all']['color'] ?? null;

		if ( ! $color ) {
			$color = $color_default;
		}

		// Return empty string if all values are defaults to avoid unnecessary CSS.
		if ( $width === $width_default && $style === $style_default && $color === $color_default ) {
			return '';
		}

		return "{$width} {$style} {$color}";
	}


	/**
	 * Gets dropdown arrow style declarations.
	 *
	 * This function generates CSS styles for dropdown arrow positioning based on margin values.
	 * It matches the VB implementation in dropdown-arrow/index.ts.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     Style declaration parameters.
	 *
	 *     @type array|null $attrValue The spacing attribute value.
	 * }
	 *
	 * @return string Generated CSS declarations.
	 */
	public static function dropdown_arrow_style_declaration( array $params ): string {
		$attr_value = $params['attrValue'] ?? [];
		$margin     = $attr_value['margin'] ?? [];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => true,
			]
		);

		if ( ! empty( $margin['right'] ) ) {
			$style_declarations->add( 'margin-left', "calc({$margin['right']} - 4px)" );
		}

		return $style_declarations->value();
	}

	/**
	 * Keep Select2 open-state bottom corners connected with dropdown panel.
	 *
	 * @since ??
	 *
	 * @param array $params Style declaration parameters.
	 *
	 * @return string Generated CSS declarations.
	 */
	public static function open_select_border_radius_style_declaration( array $params ): string {
		if ( empty( $params['attrValue'] ) || ! is_array( $params['attrValue'] ) ) {
			return '';
		}

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => true,
			]
		);

		$style_declarations->add( 'border-bottom-left-radius', '0' );
		$style_declarations->add( 'border-bottom-right-radius', '0' );

		return $style_declarations->value();
	}

	/**
	 * Button icon style declaration for WooCommerce Cart Totals module.
	 *
	 * This declaration also sets `right: inherit` ( or `left: inherit` ) instead of `right: 0.6em`
	 * to prevent icon position shifting when Button Custom Style is enabled.
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
					'left'        => true,
					'right'       => true,
				],
			]
		);

		// Only add vertical centering and horizontal positioning for default icons to fix alignment issue.
		// All other properties are handled by the centralized ButtonIcon.
		$has_custom_icon = ! empty( $icon_settings['unicode'] );
		$enable          = $attr_value['icon']['enable'] ?? $default_attr_value['icon']['enable'] ?? 'off';
		$on_hover        = $attr_value['icon']['onHover'] ?? $default_attr_value['icon']['onHover'] ?? null;
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

			$style_declarations->add( $placement, 'inherit' );
		}

		return $style_declarations->value();
	}

	/**
	 * WooCommerceCartTotals Module's style components.
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
		$attrs                                      = $args['attrs'] ?? [];
		$elements                                   = $args['elements'];
		$settings                                   = $args['settings'] ?? [];
		$order_class                                = $args['orderClass'] ?? '';
		$style_group                                = $args['styleGroup'] ?? 'module';
		$default_printed_style_attrs                = $args['defaultPrintedStyleAttrs'] ?? [];
		$breakpoints_states_info                    = MultiViewUtils::get_breakpoints_states_info();
		$default_breakpoint                         = $breakpoints_states_info->default_breakpoint();
		$default_state                              = $breakpoints_states_info->default_state();
		$focus_use_border_value                     = ModuleUtils::use_attr_value(
			[
				'attr'         => $attrs['field']['advanced']['focusUseBorder'] ?? [],
				'breakpoint'   => $default_breakpoint,
				'state'        => $default_state,
				'mode'         => 'getAndInheritAll',
				'defaultValue' => 'off',
			]
		);
		$is_field_focus_border_enabled              = 'on' === $focus_use_border_value;
		$has_field_border_config                    = ! empty( $attrs['field']['decoration']['border'] );
		$has_focus_background_config                = ! empty( $attrs['field']['advanced']['focus']['background'] );
		$has_focus_border_config                    = ! empty( $attrs['field']['advanced']['focus']['border'] );
		$should_apply_open_select_border_radius_fix = ( $has_field_border_config && $has_focus_background_config ) || ( $is_field_focus_border_enabled && $has_focus_border_config );
		$open_select_border_radius_attr             = [];
		$form_field_attr                            = $attrs['field'] ?? [];

		if ( $should_apply_open_select_border_radius_fix ) {
			$open_select_border_radius_attr = $has_field_border_config && $has_focus_background_config
				? ( $attrs['field']['decoration']['border'] ?? [] )
				: ( $attrs['field']['advanced']['focus']['border'] ?? [] );
		}

		if ( empty( $form_field_attr['advanced']['focusUseBorder'] ) ) {
			// Align with Cart Totals default toggle behavior when attr is absent,
			// while preserving explicit values coming from module attrs.
			$form_field_attr['advanced']['focusUseBorder']['desktop']['value'] = 'off';
		}

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
											'selector' => "{$order_class}.et_pb_wc_cart_totals",
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
							'attrName' => 'content',
						]
					),

					// Button.
					$elements->style(
						[
							'attrName'   => 'button',
							'styleProps' => [
								'button'         => [
									'affectingAttrs' => $button_affecting_attrs,
								],
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} a.checkout-button, {$order_class} button.button",
											'attr'     => $attrs['button']['decoration']['button'] ?? [],
											'declarationFunction' => [ self::class, 'button_icon_style_declaration' ],
											'selectorFunction' => function ( $params ) {
												$params = wp_parse_args(
													$params,
													[
														'selector' => null,
														'breakpoint' => null,
														'state'    => null,
														'attr'     => [],
														'defaultPrintedStyleAttr' => [],
													]
												);

												$icon_attr = ModuleUtils::use_attr_value(
													[
														'attr'  => $params['attr'],
														'breakpoint' => $params['breakpoint'],
														'state' => $params['state'],
													]
												);

												$placement      = $icon_attr['icon']['placement'] ?? 'right';
												$pseudo_element = 'left' === $placement ? ':before' : ':after';

												// Use explode/implode to handle comma-separated selectors properly.
												$selectors = explode( ',', $params['selector'] );
												$selectors = array_map(
													function ( $item ) use ( $pseudo_element ) {
														return trim( $item ) . $pseudo_element;
													},
													$selectors
												);

												return implode( ', ', $selectors );
											},
										],
									],
								],
								'attrsFilter'    => function ( $decoration_attrs ) use ( $style_group ): ?array {
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

					// Column Label.
					$elements->style(
						[
							'attrName' => 'columnLabel',
						]
					),

					// Field.
					$elements->style(
						[
							'attrName'   => 'field',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} .select2-container--default .select2-selection--single .select2-selection__arrow b",
											'attr'     => $attrs['field']['decoration']['border'] ?? [],
											'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} .select2-container--default .select2-selection--single .select2-selection__arrow b",
											'attr'     => $attrs['field']['decoration']['spacing'] ?? [],
											'declarationFunction' => [ self::class, 'dropdown_arrow_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/text',
										'props'         => [
											'selector' => implode(
												', ',
												[
													".woocommerce {$order_class} .select2-container--open .select2-selection__rendered",
													"{$order_class} .select2-container--open .select2-selection__rendered",
												]
											),
											'attr'     => $attrs['field']['advanced']['focus']['font'] ?? [],
										],
									],
									[
										'componentName' => 'divi/background',
										'props'         => [
											'selector'  => implode(
												', ',
												[
													".woocommerce {$order_class} .select2-container--open .select2-selection",
													"{$order_class} .select2-container--open .select2-selection",
												]
											),
											'attr'      => $attrs['field']['advanced']['focus']['background'] ?? [],
											'important' => true,
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => implode(
												', ',
												[
													".woocommerce {$order_class} .select2-container--default.select2-container--open.select2-container--below .select2-selection--single",
													"{$order_class} .select2-container--default.select2-container--open.select2-container--below .select2-selection--single",
													".woocommerce {$order_class} .select2-container--default.select2-container--open.select2-container--above .select2-selection--single",
													"{$order_class} .select2-container--default.select2-container--open.select2-container--above .select2-selection--single",
												]
											),
											'attr'     => $open_select_border_radius_attr,
											'declarationFunction' => [ self::class, 'open_select_border_radius_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => implode(
												', ',
												[
													".woocommerce {$order_class} .select2-container--default.select2-container--open .select2-selection--single",
													"{$order_class} .select2-container--default.select2-container--open .select2-selection--single",
												]
											),
											'attr'     => $is_field_focus_border_enabled
												? ( $attrs['field']['advanced']['focus']['border'] ?? [] )
												: [],
											'declarationFunction' => [ self::class, 'border_style_declaration' ],
										],
									],
								],
							],
						]
					),

					// Form Field Style.
					FormFieldStyle::style(
						[
							'selector'               => implode(
								', ',
								[
									".woocommerce {$order_class} .select2-container--default .select2-selection--single",
									"{$order_class} .select2-container--default .select2-selection--single",
									".woocommerce {$order_class} form .form-row .input-text",
									"{$order_class} form .form-row .input-text",
								]
							),
							'attr'                   => $form_field_attr,
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
							'propertySelectors'      => [
								'background'  => [
									'desktop' => [
										'hover' => [
											'background-color' => implode(
												', ',
												[
													"{$order_class} .select2-container--default .select2-selection--single:hover",
													".woocommerce {$order_class} form .form-row .input-text:hover",
													"{$order_class} form .form-row .input-text:hover",
												]
											),
										],
									],
								],
								'focus'       => [
									'background' => [
										'desktop' => [
											'value' => [
												'background-color' => implode(
													', ',
													[
														".woocommerce {$order_class} .select2-container--open .select2-selection",
														"{$order_class} .select2-container--open .select2-selection",
														".woocommerce {$order_class} form .input-text",
														"{$order_class} form .input-text",
													]
												),
											],
											'hover' => [
												'background-color' => implode(
													', ',
													[
														".woocommerce {$order_class} .select2-container--open:hover .select2-selection",
														"{$order_class} .select2-container--open:hover .select2-selection",
														".woocommerce {$order_class} form .input-text:hover",
														"{$order_class} form .input-text:hover",
													]
												),
											],
										],
									],
									'font'       => [
										'font' => [
											'desktop' => [
												'value' => [
													'color' => implode(
														', ',
														[
															".woocommerce {$order_class} .select2-container--open .select2-selection__rendered",
															"{$order_class} .select2-container--open .select2-selection__rendered",
															".woocommerce {$order_class} form .form-row input.input-text",
															"{$order_class} form .form-row input.input-text",
														]
													),
												],
												'hover' => [
													'color' => implode(
														', ',
														[
															".woocommerce {$order_class} .select2-container--open:hover .select2-selection__rendered",
															"{$order_class} .select2-container--open:hover .select2-selection__rendered",
															".woocommerce {$order_class} form .form-row input.input-text:hover",
															"{$order_class} form .form-row input.input-text:hover",
														]
													),
												],
											],
										],
									],
									'border'     => [
										'desktop' => [
											'value' => [
												'border-radius' => implode(
													', ',
													[
														".woocommerce {$order_class} form .form-row input.input-text",
														"{$order_class} form .form-row input.input-text",
													]
												),
												'border-style'  => implode(
													', ',
													[
														".woocommerce {$order_class} .select2-container--default.select2-container--open .select2-selection--single",
														"{$order_class} .select2-container--default.select2-container--open .select2-selection--single",
														".woocommerce {$order_class} form .form-row .input-text",
														"{$order_class} form .form-row .input-text",
													]
												),
											],
											'hover' => [
												'border-radius' => implode(
													', ',
													[
														".woocommerce {$order_class} form .form-row input.input-text:hover",
														"{$order_class} form .form-row input.input-text:hover",
													]
												),
												'border-style'  => implode(
													', ',
													[
														".woocommerce {$order_class} .select2-container--default.select2-container--open:hover .select2-selection--single",
														"{$order_class} .select2-container--default.select2-container--open:hover .select2-selection--single",
														".woocommerce {$order_class} form .form-row .input-text:hover",
														"{$order_class} form .form-row .input-text:hover",
													]
												),
											],
										],
									],
								],
								'label'       => [
									'font' => [
										'font'       => [
											'desktop' => [
												'value' => array_fill_keys(
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
													"{$order_class} form .form-row label"
												),
												'hover' => array_fill_keys(
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
													"{$order_class} form .form-row label:hover"
												),
											],
										],
										'textShadow' => [
											'desktop' => [
												'value' => [
													'text-shadow' => "{$order_class} form .form-row label",
												],
												'hover' => [
													'text-shadow' => "{$order_class} form .form-row label:hover",
												],
											],
										],
									],
								],
								'placeholder' => [
									'font' => [
										'font' => [
											'desktop' => [
												'value' => [
													'color' => implode(
														', ',
														[
															"{$order_class} form .form-row input.input-text",
															"{$order_class} form .form-row textarea",
														]
													),
												],
											],
										],
									],
								],
							],
						]
					),

					// PHP FormFieldStyle does not fall back to field.advanced.placeholder.font.
					// Keep this separate ElementStyle for parity with the Visual Builder fallback.
					ElementStyle::style(
						[
							'selector'               => implode(
								', ',
								[
									"{$order_class} form .form-row input.input-text",
									"{$order_class} form .form-row textarea",
								]
							),
							'attrs'                  => [
								'font' => $attrs['field']['advanced']['placeholder']['font'] ?? [],
							],
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
							'font'                   => [
								'selectorFunction' => function ( $params ) {
									$maybe_multiple_selectors = $params['selector'] ?? '';
									$base_selectors           = array_map( 'trim', explode( ',', $maybe_multiple_selectors ) );
									$placeholder_selectors    = [];

									foreach ( $base_selectors as $selector ) {
										$placeholder_selectors[] = "{$selector}::placeholder";
									}

									return implode( ', ', $placeholder_selectors );
								},
							],
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
								'attrsFilter'    => function ( $attrs_to_filter ) {
									// Filter out the border attribute to avoid duplication.
									// Border attribute is already handled by the border_style_declaration.
									if ( isset( $attrs_to_filter['border'] ) ) {
										unset( $attrs_to_filter['border'] );
									}

									return $attrs_to_filter;
								},
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} table.shop_table tr th, {$order_class} table.shop_table tr td",
											'attr'     => $attrs['tableCell']['decoration']['border'] ?? [],
											'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} table.shop_table tr th, {$order_class} table.shop_table tr td",
											'attr'     => $attrs['tableCell']['decoration']['border'] ?? [],
											'declarationFunction' => [ self::class, 'border_style_declaration' ],
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
							'selector'  => $order_class,
							'attr'      => $attrs['css'] ?? [],
							'cssFields' => self::custom_css(),
						]
					),
				],
			]
		);
	}

	/**
	 * Get the custom CSS fields for the Divi WooCommerceCartTotals module.
	 *
	 * This function retrieves the custom CSS fields defined for the Divi WooCommerceCartTotals module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /api/js/divi-module-library/functions/generateDefaultAttrs cssFields}
	 * located in `@divi/module-library`. Note that this function does not have
	 * a `label` property on each array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi WooCommerceCartTotals module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = CustomCssTrait::custom_css();
	 * // Returns an array of custom CSS fields for the WooCommerceCartTotals module.
	 * ```
	 */
	public static function custom_css(): array {
		$registered_block = WP_Block_Type_Registry::get_instance()->get_registered( 'divi/woocommerce-cart-totals' );

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
	 * Loads `WooCommerceCartTotalsModule` and registers Front-End render callback and REST API Endpoints.
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

		$module_json_folder_path = dirname( __DIR__, 5 ) . '/visual-builder/packages/module-library/src/components/woocommerce/cart-totals/';

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
	 * Swaps Cart Totals template.
	 *
	 * By default WooCommerce displays Shipping calculator only for eligible cart items.
	 * However, Shipping Calculator must be shown in VB (and TB) therefore we swap the template.
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
		$is_template_override = 'cart/cart-totals.php' === $template_name;

		if ( $is_template_override ) {
			return trailingslashit( ET_BUILDER_5_DIR ) . 'server/Packages/WooCommerce/Templates/' . $template_name;
		}

		return $template;
	}

	/**
	 * Show dummy subtotal.
	 *
	 * The dummy subtotal is used to display the subtotal of the cart in VB and TB.
	 *
	 * @param string $value Value.
	 *
	 * @return string
	 */
	public static function show_dummy_subtotal( string $value ): string {
		if ( ! function_exists( 'wc_price' ) ) {
			return $value;
		}

		return wc_price( '187.00' );
	}

	/**
	 * Show dummy total.
	 *
	 * The dummy total is used to display the total of the cart in VB and TB.
	 *
	 * @param string $value Value.
	 *
	 * @return string
	 */
	public static function show_dummy_total( string $value ): string {
		if ( ! function_exists( 'wc_price' ) ) {
			return $value;
		}

		return sprintf( '<strong>%s</strong>', wc_price( '187.00' ) );
	}

	/**
	 * Displays message before shipping calculator in VB and TB.
	 *
	 * @return void
	 */
	public static function display_message_before_shipping_calculator(): void {
		$message = apply_filters(
			'woocommerce_shipping_may_be_available_html',
			__( 'Enter your address to view shipping options.', 'woocommerce' )
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the previous line.
		echo $message;
	}

	/**
	 * Handle hooks.
	 *
	 * @param array $conditional_tags {
	 *     Conditional tags passed from the REST API request.
	 *
	 *     @type bool $is_tb Whether the current request is a TB request.
	 * }
	 *
	 * @return void
	 */
	public static function maybe_handle_hooks( array $conditional_tags = [] ): void {
		$is_tb              = $conditional_tags['is_tb'] ?? false;
		$is_use_placeholder = $is_tb || is_et_pb_preview();

		if ( $is_use_placeholder || Conditions::is_rest_api_request() ) {
			// Ensure WooCommerce objects are properly initialized for VB/TB and preview contexts.
			WooCommerceUtils::ensure_woocommerce_objects_initialized( $conditional_tags );

			add_filter(
				'woocommerce_cart_subtotal',
				[
					self::class,
					'show_dummy_subtotal',
				]
			);

			add_filter(
				'woocommerce_cart_totals_order_total_html',
				[
					self::class,
					'show_dummy_total',
				]
			);

			add_action(
				'woocommerce_before_shipping_calculator',
				[
					self::class,
					'display_message_before_shipping_calculator',
				]
			);

			if ( is_et_pb_preview() || WC()->cart->is_empty() ) {
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
		}
	}

	/**
	 * Resets hooks.
	 *
	 * @since ??
	 *
	 * @param array $conditional_tags {
	 *     Conditional tags passed from the REST API request.
	 *
	 *     @type bool $is_tb Whether the current request is a TB request.
	 * }
	 */
	public static function maybe_reset_hooks( array $conditional_tags = [] ): void {
		$is_tb              = $conditional_tags['is_tb'] ?? false;
		$is_use_placeholder = $is_tb || is_et_pb_preview();

		if ( $is_use_placeholder || Conditions::is_rest_api_request() ) {
			remove_filter(
				'woocommerce_cart_subtotal',
				[
					self::class,
					'show_dummy_subtotal',
				]
			);

			remove_filter(
				'woocommerce_cart_totals_order_total_html',
				[
					self::class,
					'show_dummy_total',
				]
			);

			remove_action(
				'woocommerce_before_shipping_calculator',
				[
					self::class,
					'display_message_before_shipping_calculator',
				]
			);

			if ( is_et_pb_preview() || WC()->cart->is_empty() ) {
				remove_filter(
					'wc_get_template',
					[
						self::class,
						'swap_template',
					]
				);
			}
		}
	}

	/**
	 * Gets Cart totals markup.
	 *
	 * This function is used to get the cart totals markup in VB and TB and FE.
	 *
	 * @param array $conditional_tags {
	 *     Conditional tags passed from the REST API request.
	 *
	 *     @type bool $is_tb Whether the current request is a TB request.
	 * }
	 *
	 * @return string The cart totals markup.
	 */
	public static function get_cart_totals( array $conditional_tags = [] ): string {
		if ( ! function_exists( 'woocommerce_cart_totals' ) ) {
			return '';
		}

		// Show nothing when the Cart is empty in FE.
		if (
			! Conditions::is_rest_api_request()
			&& ! is_et_pb_preview()
			&& ( is_null( WC()->cart ) || WC()->cart->is_empty() )
		) {
			return '';
		}

		self::maybe_handle_hooks( $conditional_tags );

		ob_start();
		if (
			! Conditions::is_vb_enabled()
			&& ! is_et_pb_preview()
			&& ! is_null( WC()->cart )
		) {
			wc_maybe_define_constant( 'WOOCOMMERCE_CART', true );
			WC()->cart->calculate_totals();
		}
		woocommerce_cart_totals();
		$markup = ob_get_clean();

		self::maybe_reset_hooks( $conditional_tags );

		// In case $markup is not a string, fallback to an empty string.
		if ( ! is_string( $markup ) ) {
			$markup = '';
		}

		return $markup;
	}

	/**
	 * Processes custom button icons for WooCommerce Cart Totals module.
	 *
	 * This function checks if custom button icons are enabled and returns the necessary
	 * data attributes and CSS class to apply custom icons to WooCommerce buttons.
	 *
	 * This function follows the same pattern as other WooCommerce modules like
	 * ProductAddToCart and CartNotice modules.
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
				'data-icon'        => $processed_icon_desktop,
				'data-icon-tablet' => $processed_icon_tablet,
				'data-icon-phone'  => $processed_icon_phone,
			],
			'css_classes'      => [
				'et_pb_woo_custom_button_icon',
			],
		];

		// Cache and return result.
		$cache[ $cache_key ] = $result;
		return $result;
	}

	/**
	 * Injects icon data attributes onto cart totals checkout button element.
	 *
	 * WooCommerce renders the checkout button markup, so module-level htmlAttrs do not
	 * automatically end up on the anchor/button element that receives icon pseudo-elements.
	 *
	 * @since ??
	 *
	 * @param string $markup     Cart totals HTML markup.
	 * @param array  $html_attrs Icon-related HTML attributes.
	 *
	 * @return string
	 */
	private static function _inject_custom_button_icon_attributes( string $markup, array $html_attrs ): string {
		if ( empty( $markup ) || empty( $html_attrs ) ) {
			return $markup;
		}

		$attributes_to_inject = '';
		foreach ( $html_attrs as $attr_name => $attr_value ) {
			if ( ! empty( $attr_value ) ) {
				$attributes_to_inject .= sprintf( ' %s="%s"', esc_attr( $attr_name ), esc_attr( $attr_value ) );
			}
		}

		if ( '' === $attributes_to_inject ) {
			return $markup;
		}

		// Regex test: https://regex101.com/r/1qI2lz/1.
		$updated_markup = preg_replace(
			'/(<a\b(?![^>]*\bdata-icon=)[^>]*\bclass=(["\'])[^"\']*\bcheckout-button\b[^"\']*\2[^>]*)(>)/i',
			"$1{$attributes_to_inject}$3",
			$markup,
			1
		);

		if ( is_string( $updated_markup ) && $updated_markup !== $markup ) {
			return $updated_markup;
		}

		return $markup;
	}
}
