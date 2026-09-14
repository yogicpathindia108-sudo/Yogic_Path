<?php
/**
 * ModuleLibrary: Menu Module class.
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Menu;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewUtils;
use ET\Builder\Packages\Module\Layout\Components\StyleCommon\CommonStyle;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\Module\Options\Text\TextStyle;
use ET\Builder\Packages\ModuleLibrary\Menu\MenuUtils;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\ModuleLibrary\Menu\MenuPresetAttrsMap;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\ModuleLibrary\Image\ImageModule;
use ET\Builder\Packages\ModuleUtils\ImageUtils;

/**
 * `MenuModule` is consisted of functions used for Menu Module such as Front-End rendering, REST API Endpoints etc.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 */
class MenuModule implements DependencyInterface {

	/**
	 * Custom CSS fields
	 *
	 * This function is equivalent of JS const cssFields located in
	 * visual-builder/packages/module-library/src/components/menu/custom-css.ts.
	 *
	 * @since ??
	 */
	public static function custom_css() {
		return \WP_Block_Type_Registry::get_instance()->get_registered( 'divi/menu' )->customCssFields;
	}

	/**
	 * Module classnames function for post type module.
	 *
	 * This function is equivalent of JS function moduleClassnames located in
	 * visual-builder/packages/module-library/src/components/menu/module-classnames.ts.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type object $classnamesInstance Instance of ET\Builder\Packages\Module\Layout\Components\Classnames.
	 *     @type array  $attrs              Block attributes data that being rendered.
	 * }
	 */
	public static function module_classnames( $args ) {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		$logo                    = $attrs['logo']['innerContent']['desktop']['value']['src'] ?? '';
		$menu_style              = $attrs['menu']['advanced']['style']['desktop']['value'] ?? '';
		$menu_dropdown_animation = $attrs['menuDropdown']['advanced']['animation']['value'] ?? 'fade';

		$classnames_instance->add( 'et_pb_menu--with-logo', (bool) $logo );
		$classnames_instance->add( 'et_pb_menu--without-logo', ! $logo );
		$classnames_instance->add( "et_pb_menu--style-{$menu_style}", true );

		$classnames_instance->add( "et_dropdown_animation_{$menu_dropdown_animation}", true );

		// Text options.
		$classnames_instance->add( TextClassnames::text_options_classnames( $attrs['module']['advanced']['text'] ?? [] ), true );

		// Module.
		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					'attrs' => array_merge(
						$attrs['module']['decoration'] ?? [],
						[
							'link' => $args['attrs']['module']['advanced']['link'] ?? [],
						]
					),
				]
			)
		);
	}

	/**
	 * Set script data of used module options.
	 *
	 * This function is equivalent of JS function ModuleScriptData located in
	 * visual-builder/packages/module-library/src/components/menu/module-script-data.tsx.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *   Array of arguments.
	 *
	 *   @type string         $id            Module id.
	 *   @type string         $name          Module name.
	 *   @type string         $selector      Module selector.
	 *   @type array          $attrs         Module attributes.
	 *   @type int            $storeInstance The ID of instance where this block stored in BlockParserStore class.
	 *   @type ModuleElements $elements      ModuleElements instance.
	 * }
	 */
	public static function module_script_data( $args ) {
		// Assign variables.
		$id             = $args['id'] ?? '';
		$name           = $args['name'] ?? '';
		$selector       = $args['selector'] ?? '';
		$attrs          = $args['attrs'] ?? [];
		$elements       = $args['elements'];
		$store_instance = $args['storeInstance'] ?? null;

		// Element Script Data Options.
		$elements->script_data(
			[
				'attrName' => 'module',
			]
		);

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
							'et_pb_menu--with-logo'    => $attrs['logo']['innerContent'] ?? [],
							'et_pb_menu--without-logo' => $attrs['logo']['innerContent'] ?? [],
						],
						'subName'       => 'src',
						'valueResolver' => function ( $value, $resolver_args ) {
							$class_name = $resolver_args['className'] ?? '';

							if ( 'et_pb_menu--with-logo' === $class_name ) {
								return (bool) $value ? 'add' : 'remove';
							}

							return ! $value ? 'add' : 'remove';
						},
					],
				],
				'setVisibility' => [
					[
						'selector'      => $selector . ' .et_pb_menu__logo-wrap',
						'data'          => $attrs['logo']['innerContent'] ?? [],
						'valueResolver' => function ( $value ) {
							return (bool) $value ? 'visible' : 'hidden';
						},
					],
					[
						'selector'      => $selector . ' .et_pb_menu__cart-button',
						'data'          => $attrs['cartIcon']['advanced']['show'] ?? [],
						'valueResolver' => function ( $value ) {
							return 'on' === ( $value ?? 'off' ) ? 'visible' : 'hidden';
						},
					],
					[
						'selector'      => $selector . ' .et_pb_menu__cart-button .et_pb_menu__cart-count',
						'data'          => $attrs['cartQuantity']['advanced']['show'] ?? [],
						'valueResolver' => function ( $value ) {
							return 'on' === ( $value ?? 'off' ) ? 'visible' : 'hidden';
						},
					],
					[
						'selector'      => implode( ', ', [ "$selector .et_pb_menu__icon.et_pb_menu__search-button", "$selector .et_pb_menu__search-container" ] ),
						'data'          => $attrs['searchIcon']['advanced']['show'] ?? [],
						'valueResolver' => function ( $value ) {
							return 'on' === ( $value ?? 'off' ) ? 'visible' : 'hidden';
						},
					],
				],
			]
		);
	}

	/**
	 * Menu Module's style components.
	 *
	 * This function is equivalent of JS function ModuleStyles located in
	 * visual-builder/packages/module-library/src/components/menu/module-styles.tsx.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *      @type string         $id                Module ID. In VB, the ID of module is UUIDV4. In FE, the ID is order index.
	 *      @type string         $name              Module name.
	 *      @type string         $attrs             Module attributes.
	 *      @type string         $parentAttrs       Parent attrs.
	 *      @type string         $orderClass        Selector class name.
	 *      @type string         $parentOrderClass  Parent selector class name.
	 *      @type string         $wrapperOrderClass Wrapper selector class name.
	 *      @type string         $settings          Custom settings.
	 *      @type string         $state             Attributes state.
	 *      @type string         $mode              Style mode.
	 *      @type ModuleElements $elements          ModuleElements instance.
	 * }
	 */
	public static function module_styles( $args ) {
		$attrs                       = $args['attrs'] ?? [];
		$elements                    = $args['elements'];
		$settings                    = $args['settings'] ?? [];
		$order_class                 = $args['orderClass'] ?? '';
		$default_printed_style_attrs = $args['defaultPrintedStyleAttrs'] ?? [];
		$is_inside_sticky_module     = $elements->get_is_inside_sticky_module();
		$sticky_parent_order_class   = $elements->get_sticky_parent_order_class();

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
								'defaultPrintedStyleAttrs' => $default_printed_style_attrs['module']['decoration'] ?? [],
								'disabledOn'               => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
							],
						]
					),
					$elements->style(
						[
							'attrName' => 'menu',
						]
					),
					$elements->style(
						[
							'attrName' => 'logo',
						]
					),
					$elements->style(
						[
							'attrName' => 'menuDropdown',
						]
					),
					$elements->style(
						[
							'attrName'   => 'menuMobile',
							'styleProps' => [
								'attrsFilter'    => function ( $attrs_to_filter ) {
									$desktop_background_color = $attrs_to_filter['background']['desktop']['value']['color'] ?? null;
									$tablet_background_color  = $attrs_to_filter['background']['tablet']['value']['color'] ?? null;
									$phone_background_color   = $attrs_to_filter['background']['phone']['value']['color'] ?? null;

									$tablet_background_color_fallback = $tablet_background_color;
									$phone_background_color_fallback  = $phone_background_color;

									// Fallback to desktop background color if tablet value is explicitly empty string.
									if ( '' === $tablet_background_color_fallback && $desktop_background_color ) {
										$tablet_background_color_fallback = $desktop_background_color;
									}

									// Fallback to tablet background color if phone value is explicitly empty string.
									if ( '' === $phone_background_color_fallback && $tablet_background_color_fallback ) {
										$phone_background_color_fallback = $tablet_background_color_fallback;
									}

									// Update tablet background color with fallback value if set.
									if ( null !== $tablet_background_color_fallback && $tablet_background_color_fallback !== $tablet_background_color ) {
										$attrs_to_filter['background']['tablet']['value']['color'] = $tablet_background_color_fallback;
									}

									// Update phone background color with fallback value if set.
									if ( null !== $phone_background_color_fallback && $phone_background_color_fallback !== $phone_background_color ) {
										$attrs_to_filter['background']['phone']['value']['color'] = $phone_background_color_fallback;
									}

									return $attrs_to_filter;
								},
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} .et_pb_menu__wrap",
											'attr'     => $attrs['module']['advanced']['text']['text'] ?? [],
											'declarationFunction' => function ( $props ) use ( $attrs ) {
												return self::hamburger_menu_alignment_declaration( $props, $attrs );
											},
										],
									],
								],
							],
						]
					),
					$elements->style(
						[
							'attrName' => 'cartQuantity',
						]
					),
					$elements->style(
						[
							'attrName' => 'cartIcon',
						]
					),
					$elements->style(
						[
							'attrName' => 'searchIcon',
						]
					),
					$elements->style(
						[
							'attrName' => 'hamburgerMenuIcon',
						]
					),
					TextStyle::style(
						[
							'selector'               => $order_class,
							'attr'                   => $attrs['module']['advanced']['text'] ?? [],
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					),
					CommonStyle::style(
						[
							'selector'               => "{$order_class}.et_pb_menu ul li.current-menu-item > a, {$order_class}.et_pb_menu ul li.current-menu-ancestor > a, {$order_class}.et_pb_menu ul:not(.sub-menu) > li.current-menu-ancestor > a",
							'attr'                   => $attrs['menu']['advanced']['activeLinkColor'] ?? [],
							'declarationFunction'    => function ( $params ) {
								$attr_value = $params['attrValue'] ?? '';

								return "color: {$attr_value} !important;";
							},
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					),
					CommonStyle::style(
						[
							'selector'               => "{$order_class}.et_pb_menu ul li.current-menu-ancestor > a, {$order_class}.et_pb_menu .nav li ul.sub-menu li.current-menu-item > a",
							'attr'                   => $attrs['menuDropdown']['advanced']['activeLinkColor'] ?? [],
							'declarationFunction'    => function ( $params ) {
								$attr_value = $params['attrValue'] ?? '';

								return "color: {$attr_value} !important;";
							},
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					),
					CommonStyle::style(
						[
							'selector'               => 'upwards' === ( $attrs['menuDropdown']['advanced']['direction']['desktop']['value'] ?? 'downwards' )
								? "{$order_class}.et_pb_menu .et-menu-nav > ul.upwards li ul, {$order_class}.et_pb_menu .et_mobile_menu"
								: "{$order_class}.et_pb_menu .nav li ul, {$order_class}.et_pb_menu .et_mobile_menu",
							'attr'                   => $attrs['menuDropdown']['advanced']['lineColor'] ?? [],
							'declarationFunction'    => function ( $params ) {
								$attr_value = $params['attrValue'] ?? '';

								return "border-color: {$attr_value} !important;";
							},
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					),
					CommonStyle::style(
						[
							'selector'               => "{$order_class} .nav li.et-reverse-direction-nav[data-reverse-reason=\"nested\"] li ul, {$order_class} .nav li.et-reverse-direction-nav[data-reverse-reason=\"both\"] li ul",
							'attr'                   => [
								'desktop' => [
									'value' => true,
								],
							],
							'declarationFunction'    => function () {
								return 'left: auto; right: 240px;';
							},
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					),
					CommonStyle::style(
						[
							'selector'               => "{$order_class} .nav li.et-reverse-direction-nav[data-reverse-reason=\"first-level\"] > ul, {$order_class} .nav li.et-reverse-direction-nav[data-reverse-reason=\"both\"] > ul, {$order_class} .nav li.et-reverse-direction-nav[data-reverse-reason=\"first-level\"] > .sub-menu, {$order_class} .nav li.et-reverse-direction-nav[data-reverse-reason=\"both\"] > .sub-menu",
							'attr'                   => [
								'desktop' => [
									'value' => true,
								],
							],
							'declarationFunction'    => function () {
								return 'left: auto; right: 2px;';
							},
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					),
					CommonStyle::style(
						[
							'selector'               => implode(
								', ',
								[
									"{$order_class} .et_pb_menu_inner_container > .et_pb_menu__logo-wrap .et_pb_menu__logo img",
									"{$order_class} .et_pb_menu__logo-slot .et_pb_menu__logo-wrap img",
								]
							),
							'attr'                   => $attrs['logo']['decoration']['sizing'] ?? [],
							'declarationFunction'    => function ( $params ) {
								$attr_value = $params['attrValue'] ?? '';

								// In D5, if a certain value is not set, then it has the default value.
								$width          = $attr_value['width'] ?? 'auto';
								$has_auto_width = '' === $width || 'auto' === $width;

								$height              = $attr_value['height'] ?? 'auto';
								$has_non_auto_height = 'auto' !== $height;

								$max_height         = $attr_value['maxHeight'] ?? 'none';
								$has_max_height_set = 'none' !== $max_height;

								if ( ( $has_auto_width && $has_non_auto_height ) || $has_max_height_set ) {
									return 'width: auto';
								}
							},
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					),
					// Only apply flex styles if parent layout is flex.
					! empty( $args['isParentFlexLayout'] ) ? CommonStyle::style(
						[
							'selector'               => implode(
								', ',
								[
									"{$order_class} .et_pb_menu__logo-wrap",
									"{$order_class} .et_pb_menu__logo",
								]
							),
							'attr'                   => $attrs['logo']['decoration']['sizing'] ?? [],
							'declarationFunction'    => [ ImageModule::class, 'sizing_flex_style_declaration' ],
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					) : null,
					// SVG style declaration for logo images.
					CommonStyle::style(
						[
							'selector'               => implode(
								', ',
								[
									"{$order_class} .et_pb_menu__logo-wrap .et_pb_menu__logo",
									"{$order_class} .et_pb_menu__logo-slot .et_pb_menu__logo-wrap .et_pb_menu__logo",
								]
							),
							'attr'                   => $attrs['logo']['innerContent'] ?? [],
							'declarationFunction'    => [ self::class, 'svg_logo_style_declaration' ],
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					),
					CssStyle::style(
						[
							'selector'   => "{$order_class}.et_pb_menu",
							'attr'       => $attrs['css'] ?? [],
							'cssFields'  => self::custom_css(),
							'orderClass' => $order_class,
						]
					),
					CommonStyle::style(
						[
							'selector'               => "{$order_class}.et_pb_menu--style-left_aligned.et_pb_text_align_right .et_pb_menu__menu > nav > ul",
							'attr'                   => [
								'desktop' => [
									'value' => true,
								],
							],
							'declarationFunction'    => function () {
								return 'margin-right: 0;';
							},
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					),
					CommonStyle::style(
						[
							'selector'               => "{$order_class} nav > ul > li > a:hover",
							'attr'                   => $attrs['menu']['decoration']['font']['font'] ?? [],
							'declarationFunction'    => function ( $params ) {
								return 'hover' === $params['state'] && $params['attr']['desktop']['hover'] ? 'opacity: 1;' : '';
							},
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					),
					CommonStyle::style(
						[
							'selector'               => "{$order_class} nav > ul > li li a:hover",
							'attr'                   => $attrs['menuDropdown']['decoration']['font']['font'] ?? [],
							'declarationFunction'    => function ( $params ) {
								return 'hover' === $params['state'] && $params['attr']['desktop']['hover'] ? 'opacity: 1;' : '';
							},
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					),
					CommonStyle::style(
						[
							'selector'               => "{$order_class} nav > ul > li li.current-menu-item a:hover",
							'attr'                   => $attrs['menuDropdown']['advanced']['activeLinkColor'] ?? [],
							'declarationFunction'    => function ( $params ) {
								return 'hover' === $params['state'] && $params['attr']['desktop']['hover'] ? 'opacity: 1;' : '';
							},
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					),
				],
			]
		);
	}

	/**
	 * Menu module render callback which outputs server side rendered HTML on the Front-End.
	 *
	 * This function is equivalent of JS function MenuEdit located in
	 * visual-builder/packages/module-library/src/components/menu/edit.tsx.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                Block attributes that were saved by VB.
	 * @param string         $child_modules_content Block content (child modules content).
	 * @param \WP_Block      $block                Parsed block object that being rendered.
	 * @param ModuleElements $elements             ModuleElements instance.
	 *
	 * @return string HTML rendered of Menu module.
	 */
	public static function render_callback( $attrs, $child_modules_content, $block, $elements ) {
		// Extract child modules IDs using helper utility.
		$children_ids = ChildrenUtils::extract_children_ids( $block );

		// Get parent module for passing parent context to children.
		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		return Module::render(
			[
				// FE only.
				'orderIndex'          => $block->parsed_block['orderIndex'],
				'storeInstance'       => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'attrs'               => $attrs,
				'id'                  => $block->parsed_block['id'],
				'elements'            => $elements,
				'name'                => $block->block_type->name,
				'moduleCategory'      => $block->block_type->category,
				'classnamesFunction'  => [ self::class, 'module_classnames' ],
				'stylesComponent'     => [ self::class, 'module_styles' ],
				'scriptDataComponent' => [ self::class, 'module_script_data' ],
				'parentId'            => $parent->id ?? '',
				'parentName'          => $parent->blockName ?? '',
				'parentAttrs'         => $parent->attrs ?? [],
				'childrenIds'         => $children_ids,
				'children'            => [
					$elements->style_components(
						[
							'attrName' => 'module',
						]
					),
					self::render_elements( $attrs, $child_modules_content, $block, $elements ),
				],
			]
		);
	}

	/**
	 * Render cart icon element for Menu module.
	 *
	 * This function is equivalent of JS function renderCartIconElement located in
	 * visual-builder/packages/module-library/src/components/menu/edit.tsx.
	 *
	 * @since ??
	 *
	 * @param array          $attrs    Block attributes that were saved by VB.
	 * @param string         $content  Block content.
	 * @param \WP_Block      $block    Parsed block object that being rendered.
	 * @param ModuleElements $elements ModuleElements instance.
	 *
	 * @return string HTML rendered of Menu module.
	 */
	public static function render_cart_icon_element( $attrs, $content, $block, $elements ) {
		if ( ! class_exists( 'woocommerce' ) || ! WC()->cart ) {
			return '';
		}

		$show_cart_icon = ModuleUtils::has_value(
			$attrs['cartIcon']['advanced']['show'] ?? [],
			[
				'valueResolver' => function ( $value ) {
					return 'on' === $value;
				},
			]
		);

		if ( ! $show_cart_icon ) {
			return '';
		}

		$show_cart_quantity = ModuleUtils::has_value(
			$attrs['cartQuantity']['advanced']['show'] ?? [],
			[
				'valueResolver' => function ( $value ) {
					return 'on' === $value;
				},
			]
		);

		$icon_classes = HTMLUtility::classnames(
			[
				'et_pb_menu__icon'             => true,
				'et_pb_menu__cart-button'      => true,
				'et_pb_menu__icon__with_count' => $show_cart_quantity,
			],
			MultiViewUtils::hidden_on_load_class_name(
				$attrs['cartIcon']['advanced']['show'] ?? [],
				[
					'valueResolver' => function ( $value ) {
							return 'off' === ( $value ?? 'off' ) ? 'hidden' : 'visible';
					},
				]
			)
		);

		$cart_url            = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'cart' ) : WC()->cart->get_cart_url();
		$items_number        = $show_cart_quantity ? WC()->cart->get_cart_contents_count() : 0;
		$cart_quantity_badge = $show_cart_quantity ? $elements->render(
			[
				'attrName'          => 'cartQuantity',
				'tagName'           => 'span',
				'attributes'        => [
					'class' => 'et_pb_menu__cart-count',
				],
				'skipAttrChildren'  => true,
				'childrenSanitizer' => 'esc_html',
				'children'          => sprintf(
					_nx( '%1$s Item', '%1$s Items', $items_number, 'WooCommerce items number', 'et_builder_5' ),
					number_format_i18n( $items_number )
				),
			]
		) : '';

		return $elements->render(
			[
				'attrName'          => 'cartIcon',
				'tagName'           => 'a',
				'attributes'        => [
					'href'  => $cart_url,
					'class' => $icon_classes,
				],
				'skipAttrChildren'  => true,
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $cart_quantity_badge,
			]
		);
	}

	/**
	 * Render elements for Menu module.
	 *
	 * This function is equivalent of JS function renderElements located in
	 * visual-builder/packages/module-library/src/components/menu/edit.tsx.
	 *
	 * @since ??
	 *
	 * @param array          $attrs    Block attributes that were saved by VB.
	 * @param string         $content  Block content.
	 * @param \WP_Block      $block    Parsed block object that being rendered.
	 * @param ModuleElements $elements ModuleElements instance.
	 *
	 * @return string HTML rendered of Menu module.
	 */
	public static function render_elements( $attrs, $content, $block, $elements ) {
		// Layout classes for inner container.
		// These classes are merged with the existing 'et_pb_menu_inner_container' class.
		$layout_display_value = $attrs['module']['decoration']['layout']['desktop']['value']['display'] ?? 'flex';
		$inner_class          = HTMLUtility::classnames(
			'et_pb_menu_inner_container',
			[
				'et_flex_module' => 'flex' === $layout_display_value,
				'et_grid_module' => 'grid' === $layout_display_value,
			]
		);
		$menu_style           = $attrs['menu']['advanced']['style']['desktop']['value'] ?? '';

		switch ( $menu_style ) {
			case 'inline_centered_logo':
				return HTMLUtility::render(
					[
						'tag'               => 'div',
						'attributes'        => [
							'class' => $inner_class,
						],
						'childrenSanitizer' => 'et_core_esc_previously',
						'children'          => [
							self::render_logo_element( $attrs, $content, $block, $elements ),
							HTMLUtility::render(
								[
									'tag'               => 'div',
									'attributes'        => [
										'class' => 'et_pb_menu__wrap',
									],
									'childrenSanitizer' => 'et_core_esc_previously',
									'children'          => [
										self::render_cart_icon_element( $attrs, $content, $block, $elements ),
										self::render_menu_element( $attrs, $content, $block, $elements ),
										self::render_search_icon_element( $attrs, $content, $block, $elements ),
										self::render_hamburger_menu_icon_element( $attrs, $content, $block, $elements ),
									],
								]
							),
							self::render_search_from_element( $attrs, $content, $block, $elements ),
							$content,
						],
					]
				);

			case 'centered':
			case 'left_aligned':
			default:
				return HTMLUtility::render(
					[
						'tag'               => 'div',
						'attributes'        => [
							'class' => $inner_class,
						],
						'childrenSanitizer' => 'et_core_esc_previously',
						'children'          => [
							self::render_logo_element( $attrs, $content, $block, $elements ),
							HTMLUtility::render(
								[
									'tag'               => 'div',
									'attributes'        => [
										'class' => 'et_pb_menu__wrap',
									],
									'childrenSanitizer' => 'et_core_esc_previously',
									'children'          => [
										self::render_menu_element( $attrs, $content, $block, $elements ),
										self::render_cart_icon_element( $attrs, $content, $block, $elements ),
										self::render_search_icon_element( $attrs, $content, $block, $elements ),
										self::render_hamburger_menu_icon_element( $attrs, $content, $block, $elements ),
									],
								]
							),
							self::render_search_from_element( $attrs, $content, $block, $elements ),
							$content,
						],
					]
				);
		}
	}

	/**
	 * Render search icon element for Menu module.
	 *
	 * This function is equivalent of JS function renderHamburgerMenuIconElement located in
	 * visual-builder/packages/module-library/src/components/menu/edit.tsx.
	 *
	 * @since ??
	 *
	 * @param array          $attrs    Block attributes that were saved by VB.
	 * @param string         $content  Block content.
	 * @param \WP_Block      $block    Parsed block object that being rendered.
	 * @param ModuleElements $elements ModuleElements instance.
	 *
	 * @return string HTML rendered of Menu module.
	 */
	public static function render_hamburger_menu_icon_element( $attrs, $content, $block, $elements ) {
		return $elements->render(
			[
				'attrName'          => 'menuMobile',
				'tagName'           => 'div',
				'skipAttrChildren'  => true,
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => HTMLUtility::render(
					[
						'tag'               => 'div',
						'attributes'        => [
							'class' => HTMLUtility::classnames(
								[
									'mobile_nav' => true,
									'et_pb_mobile_menu_upwards' => 'upwards' === $attrs['menuDropdown']['advanced']['direction']['desktop']['value'] ?? 'downwards',
									'closed'     => true,
								]
							),
						],
						'childrenSanitizer' => 'et_core_esc_previously',
						'children'          => HTMLUtility::render(
							[
								'tag'        => 'span',
								'attributes' => [
									'class' => 'mobile_menu_bar',
								],
							]
						),
					]
				),
			]
		);
	}

	/**
	 * Render logo element for Menu module.
	 *
	 * This function is equivalent of JS function renderLogoElement located in
	 * visual-builder/packages/module-library/src/components/menu/edit.tsx.
	 *
	 * @since ??
	 *
	 * @param array          $attrs    Block attributes that were saved by VB.
	 * @param string         $content  Block content.
	 * @param \WP_Block      $block    Parsed block object that being rendered.
	 * @param ModuleElements $elements ModuleElements instance.
	 *
	 * @return string HTML rendered of Menu module.
	 */
	public static function render_logo_element( $attrs, $content, $block, $elements ) {
		if ( ! ModuleUtils::has_value( $attrs['logo']['innerContent'] ?? [], [ 'subName' => 'src' ] ) ) {
			return '';
		}

		return HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => 'et_pb_menu__logo-wrap',
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => HTMLUtility::render(
					[
						'tag'               => 'div',
						'attributes'        => [
							'class' => 'et_pb_menu__logo',
						],
						'childrenSanitizer' => 'et_core_esc_previously',
						'children'          => $elements->render(
							[
								'attrName' => 'logo',
							]
						),
					]
				),
			]
		);
	}

	/**
	 * Render menu element for Menu module.
	 *
	 * This function is equivalent of JS function renderMenuElement located in
	 * visual-builder/packages/module-library/src/components/menu/edit.tsx.
	 *
	 * @since ??
	 *
	 * @param array          $attrs    Block attributes that were saved by VB.
	 * @param string         $content  Block content.
	 * @param \WP_Block      $block    Parsed block object that being rendered.
	 * @param ModuleElements $elements ModuleElements instance.
	 *
	 * @return string HTML rendered of Menu module.
	 */
	public static function render_menu_element( $attrs, $content, $block, $elements ) {
		$menu_dropdown_direction = $attrs['menuDropdown']['advanced']['direction']['desktop']['value'] ?? '';
		$menu_id                 = $attrs['menu']['advanced']['menuId']['desktop']['value'] ?? '';

		return $elements->render(
			[
				'attrName'          => 'menuContent',
				'tagName'           => 'div',
				'skipAttrChildren'  => true,
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => MenuUtils::render_menu(
					[
						'menuDropdownDirection' => $menu_dropdown_direction,
						'menuId'                => $menu_id,
					]
				),
			]
		);
	}

	/**
	 * Render search icon element for Menu module.
	 *
	 * This function is equivalent of JS function renderSearchIconElement located in
	 * visual-builder/packages/module-library/src/components/menu/edit.tsx.
	 *
	 * @since ??
	 *
	 * @param array          $attrs    Block attributes that were saved by VB.
	 * @param string         $content  Block content.
	 * @param \WP_Block      $block    Parsed block object that being rendered.
	 * @param ModuleElements $elements ModuleElements instance.
	 *
	 * @return string HTML rendered of Menu module.
	 */
	public static function render_search_icon_element( $attrs, $content, $block, $elements ) {
		$show_search_icon = ModuleUtils::has_value(
			$attrs['searchIcon']['advanced']['show'] ?? [],
			[
				'valueResolver' => function ( $value ) {
					return 'on' === $value;
				},
			]
		);

		if ( ! $show_search_icon ) {
			return '';
		}

		return $elements->render(
			[
				'attrName'         => 'searchIcon',
				'tagName'          => 'button',
				'skipAttrChildren' => true,
				'attributes'       => [
					'type' => 'button',
				],
			]
		);
	}

	/**
	 * Render search form element for Menu module.
	 *
	 * This function is equivalent of JS function renderSearchForm located in
	 * visual-builder/packages/module-library/src/components/menu/edit.tsx.
	 *
	 * @since ??
	 *
	 * @param array          $attrs    Block attributes that were saved by VB.
	 * @param string         $content  Block content.
	 * @param \WP_Block      $block    Parsed block object that being rendered.
	 * @param ModuleElements $elements ModuleElements instance.
	 *
	 * @return string HTML rendered of Menu module.
	 */
	public static function render_search_from_element( $attrs, $content, $block, $elements ) {
		$show_search_icon = ModuleUtils::has_value(
			$attrs['searchIcon']['advanced']['show'] ?? [],
			[
				'valueResolver' => function ( $value ) {
					return 'on' === $value;
				},
			]
		);

		if ( ! $show_search_icon ) {
			return '';
		}

		return HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => 'et_pb_menu__search-container et_pb_menu__search-container--disabled',
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => HTMLUtility::render(
					[
						'tag'               => 'div',
						'attributes'        => [
							'class' => 'et_pb_menu__search',
						],
						'childrenSanitizer' => 'et_core_esc_previously',
						'children'          => [
							HTMLUtility::render(
								[
									'tag'               => 'form',
									'attributes'        => [
										'role'   => 'search',
										'method' => 'get',
										'class'  => 'et_pb_menu__search-form',
										'action' => home_url( '/' ),
									],
									'childrenSanitizer' => 'et_core_esc_previously',
									'children'          => HTMLUtility::render(
										[
											'tag'        => 'input',
											'attributes' => [
												'type'  => 'search',
												'name'  => 's',
												'class' => 'et_pb_menu__search-input',
												'placeholder' => __( 'Search &hellip;', 'et_builder_5' ),
												'title' => __( 'Search for:', 'et_builder_5' ),
											],
										]
									),
								]
							),
							HTMLUtility::render(
								[
									'tag'        => 'button',
									'attributes' => [
										'type'  => 'button',
										'class' => 'et_pb_menu__icon et_pb_menu__close-search-button',
									],
								]
							),
						],
					]
				),
			]
		);
	}

	/**
	 * SVG logo style declaration for Menu module.
	 *
	 * Unlike the Image module's SVG declaration, this does NOT apply width: 100%
	 * by default when only height/max-height is set, to match D4 behavior where
	 * the logo wrapper naturally sizes to its content.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     Parameters for generating SVG logo CSS declarations.
	 *
	 *     @type array $attrValue Logo attributes for style generation.
	 * }
	 *
	 * @return string CSS declaration.
	 */
	public static function svg_logo_style_declaration( array $params ): string {
		$attr_value = $params['attrValue'] ?? [];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		$src = $attr_value['src'] ?? '';

		if ( ! is_string( $src ) ) {
			$src = '';
		}

		$has_svg_extension = ! empty( $src ) && ImageUtils::is_file_extension( $src, 'svg' );

		if ( $has_svg_extension ) {
			// Match D4 behavior: only apply height: auto to maintain aspect ratio.
			// Do NOT apply width: 100% by default, allowing the wrapper to size naturally.
			// This prevents the logo from stretching when only max-height is set.
			$style_declarations->add( 'height', 'auto' );
		}

		return $style_declarations->value();
	}

	/**
	 * Hamburger menu alignment declaration.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration function parameters.
	 * @param array $attrs  Module attributes (optional, used for menu style logic).
	 *
	 * @return string CSS declaration.
	 */
	public static function hamburger_menu_alignment_declaration( array $params, array $attrs = [] ): string {
		$breakpoint = $params['breakpoint'] ?? 'desktop';

		$original_attr_value = $params['attrValue'] ?? [];
		if ( ! is_array( $original_attr_value ) ) {
			$original_attr_value = [];
		}

		// Check if we need to apply centered menu style logic.
		if ( ! empty( $attrs ) && Breakpoint::get_base_breakpoint_name() !== $breakpoint ) {
			$menu_data        = $attrs['menu'] ?? [];
			$menu_advanced    = $menu_data['advanced'] ?? [];
			$menu_styles      = $menu_advanced['style'] ?? [];
			$breakpoint_style = ( $menu_styles[ $breakpoint ] ?? [] )['value'] ?? null;
			$desktop_style    = ( $menu_styles['desktop'] ?? [] )['value'] ?? null;
			// phpcs:ignore Universal.Operators.DisallowShortTernary.Found -- Short ternary is appropriate here for fallback to desktop style.
			$menu_style = $breakpoint_style ?: $desktop_style;

			// For centered menu style, force center orientation on non-desktop breakpoints.
			if ( 'centered' === $menu_style ) {
				$original_attr_value = array_merge(
					$original_attr_value,
					[
						'orientation' => 'center',
					]
				);
			}
		}

		$attr_value = $original_attr_value['orientation'] ?? '';

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => true,
			]
		);

		// Only apply styles for non-desktop breakpoints.
		if ( Breakpoint::get_base_breakpoint_name() !== $breakpoint ) {
			switch ( $attr_value ) {
				case 'left':
					$style_declarations->add( 'justify-content', 'flex-start' );
					break;
				case 'center':
					$style_declarations->add( 'justify-content', 'center' );
					break;
				case 'right':
					$style_declarations->add( 'justify-content', 'flex-end' );
					break;
				case 'justify':
					$style_declarations->add( 'justify-content', 'justify' );
					break;
				default:
					// No alignment set, return an empty declaration.
					return $style_declarations->value();
			}
		}

		return $style_declarations->value();
	}

	/**
	 * Loads `MenuModule` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load() {
		// phpcs:ignore PHPCompatibility.FunctionUse.NewFunctionParameters.dirname_levelsFound -- We have PHP 7 support now, This can be deleted once PHPCS config is updated.
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/menu/';

		add_filter( 'divi_conversion_presets_attrs_map', [ MenuPresetAttrsMap::class, 'get_map' ], 10, 2 );

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
