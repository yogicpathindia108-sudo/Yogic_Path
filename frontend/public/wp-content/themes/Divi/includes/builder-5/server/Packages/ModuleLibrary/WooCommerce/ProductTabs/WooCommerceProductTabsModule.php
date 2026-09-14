<?php
/**
 * Module Library: WooCommerceProductTabs Module
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductTabs;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\Conditions;
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
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use ET\Builder\Packages\WooCommerce\WooCommerceHooks;
use WP_Block_Type_Registry;
use WP_Block;

/**
 * WooCommerceProductTabsModule class.
 *
 * This class implements the functionality of a call-to-action component
 * in a frontend application. It provides functions for rendering the
 * WooCommerceProductTabs module,
 * managing REST API endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class WooCommerceProductTabsModule implements DependencyInterface {

	/**
	 * Render callback for the WooCommerceProductTabs module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ WooCommerceProductTabsEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $content                     The block's content.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the WooCommerceProductTabs module.
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
	 * WooCommerceProductTabsModule::render_callback( $attrs, $content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements ): string {
		// Get product and includeTabs attributes.
		$product_id      = $attrs['content']['advanced']['product']['desktop']['value'] ?? WooCommerceUtils::get_default_product();
		$loop_product_id = WooCommerceUtils::get_loop_context_product_id( $attrs, $block );
		if ( $loop_product_id > 0 && 'current' === $product_id ) {
			$product_id = $loop_product_id;
		}

		// Fix for issue #45557: Get includeTabs from the actual parsed block attributes.
		// to avoid array_replace_recursive merging issues with dynamic attribute processing.
		$include_tabs = $block->parsed_block['attrs']['content']['advanced']['includeTabs']['desktop']['value'] ??
			$attrs['content']['advanced']['includeTabs']['desktop']['value'] ??
			[];

		// Get product tabs data.
		$tabs_data = self::get_product_tabs(
			[
				'product'      => $product_id,
				'include_tabs' => $include_tabs,
			]
		);

		// Generate tab navigation HTML.
		$tab_nav_html = self::render_tab_nav( $tabs_data );

		// Generate tab content HTML.
		$tab_content_html = self::render_tab_content( $tabs_data );

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
				'parentName'          => $parent->blockName ?? '', // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP_Block_Parser_Block uses camelCase.
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
								HTMLUtility::render(
									[
										'tag'        => 'ul',
										'tagEscaped' => true,
										'attributes' => [
											'class' => 'et_pb_tabs_controls clearfix',
										],
										'childrenSanitizer' => 'et_core_esc_previously',
										'children'   => $tab_nav_html,
									]
								),
								HTMLUtility::render(
									[
										'tag'        => 'div',
										'tagEscaped' => true,
										'attributes' => [
											'class' => 'et_pb_all_tabs',
										],
										'childrenSanitizer' => 'et_core_esc_previously',
										'children'   => $tab_content_html,
									]
								),
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
	 * arguments. It is used in the `render_callback` function of the WooCommerceProductTabs module.
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
	 * WooCommerceProductTabsModule::module_classnames($args);
	 * ```
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		$classnames_instance->add( 'et_pb_tabs' );

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
	 * WooCommerceProductTabs module script data.
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
	 * WooCommerceProductTabsModule::module_script_data( $args );
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
	 * WooCommerceProductTabs Module's style components.
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
		$order_class = $args['orderClass'];

		// Check if box shadow is inner type.
		$is_inner_box_shadow = 'inner' === ( $attrs['module']['decoration']['boxShadow']['desktop']['value']['position'] ?? '' );

		// Main tab wrapper class.
		$main_selector = "{$order_class}.et_pb_tabs";

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
								'background'     => [
									'selector'         => "{$main_selector} .et_pb_all_tabs",
									'featureSelectors' => [
										'mask'    => [
											'desktop' => [
												'value' => "{$main_selector} > .et_pb_background_mask",
											],
										],
										'pattern' => [
											'desktop' => [
												'value' => "{$main_selector} > .et_pb_background_pattern",
											],
										],
									],
								],
								'disabledOn'     => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
								'boxShadow'      => [
									'selector' => $is_inner_box_shadow
										? "{$order_class} .et-pb-active-slide,{$order_class} .et_pb_active_content"
										: $order_class,
								],
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class}.et_pb_tabs",
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
					// All Tabs Content.
					$elements->style(
						[
							'attrName' => 'content',
						]
					),
					// Active Tab.
					$elements->style(
						[
							'attrName' => 'activeTab',
						]
					),
					// Inactive Tab.
					$elements->style(
						[
							'attrName' => 'inactiveTab',
						]
					),
					// Tab.
					$elements->style(
						[
							'attrName' => 'tab',
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
	 * Get the custom CSS fields for the Divi WooCommerceProductTabs module.
	 *
	 * This function retrieves the custom CSS fields defined for the Divi WooCommerceProductTabs module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /api/js/divi-module-library/functions/generateDefaultAttrs cssFields}
	 * located in `@divi/module-library`. Note that this function does not have
	 * a `label` property on each array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi WooCommerceProductTabs module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = CustomCssTrait::custom_css();
	 * // Returns an array of custom CSS fields for the WooCommerceProductTabs module.
	 * ```
	 */
	public static function custom_css(): array {
		return WP_Block_Type_Registry::get_instance()->get_registered( 'divi/woocommerce-product-tabs' )->customCssFields;
	}

	/**
	 * Loads `WooCommerceProductTabsModule` and registers Front-End render callback and REST API Endpoints.
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
			'divi_module_library_module_default_attributes_divi/woocommerce-product-tabs',
			[ WooCommerceUtils::class, 'process_dynamic_attr_defaults' ],
			10,
			2
		);

		$module_json_folder_path = dirname( __DIR__, 5 ) . '/visual-builder/packages/module-library/src/components/woocommerce/product-tabs/';

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
	 * Load comments template.
	 *
	 * This method ensures the correct comment template is loaded for WooCommerceProductTabs' review tab
	 * in the Theme Builder environment.
	 * It searches for the appropriate template file in the theme and WooCommerce template directories.
	 *
	 * Based on the legacy `ET_Builder_Module_Woocommerce_Tabs::comments_template_loader` method.
	 *
	 * @since ??
	 *
	 * @param string $template Template to load.
	 *
	 * @return string The template path.
	 */
	public static function comments_template_loader( string $template ): string {
		if ( ! et_builder_tb_enabled() ) {
			return $template;
		}

		$check_dirs = [
			trailingslashit( get_stylesheet_directory() ) . WC()->template_path(),
			trailingslashit( get_template_directory() ) . WC()->template_path(),
			trailingslashit( get_stylesheet_directory() ),
			trailingslashit( get_template_directory() ),
			trailingslashit( WC()->plugin_path() ) . 'templates/',
		];

		if ( WC_TEMPLATE_DEBUG_MODE ) {
			$check_dirs = [ array_pop( $check_dirs ) ];
		}

		foreach ( $check_dirs as $dir ) {
			if ( file_exists( trailingslashit( $dir ) . 'single-product-reviews.php' ) ) {
				return trailingslashit( $dir ) . 'single-product-reviews.php';
			}
		}

		return $template;
	}

	/**
	 * Render tab navigation HTML.
	 *
	 * This function generates the HTML for tab navigation based on the provided tabs data.
	 * It creates list items with proper CSS classes and links for each tab.
	 *
	 * @since ??
	 *
	 * @param array $tabs_data Array of tab data containing name, title, and content.
	 *
	 * @return array Array of HTMLUtility::render() calls for tab navigation items.
	 */
	public static function render_tab_nav( array $tabs_data ): array {
		$nav_items = [];
		$index     = 0;

		foreach ( $tabs_data as $name => $tab ) {
			++$index;

			$nav_items[] = HTMLUtility::render(
				[
					'tag'               => 'li',
					'tagEscaped'        => true,
					'attributes'        => [
						'class'   => HTMLUtility::classnames(
							[
								'et_pb_tab_nav_item' => true,
								'et_pb_tab_nav_item_' . esc_attr( $name ) => true,
								esc_attr( $name ) . '_tab' => true,
								'et_pb_tab_active'   => 1 === $index,
							]
						),
						'data-id' => esc_attr( $name ),
					],
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => HTMLUtility::render(
						[
							'tag'        => 'a',
							'tagEscaped' => true,
							'attributes' => [
								'href' => '#tab-' . esc_attr( $name ),
							],
							'children'   => esc_html( $tab['title'] ),
						]
					),
				]
			);
		}

		return $nav_items;
	}

	/**
	 * Render tab content HTML.
	 *
	 * This function generates the HTML for tab content panels based on the provided tabs data.
	 * It creates div elements with proper CSS classes for each tab content.
	 *
	 * @since ??
	 *
	 * @param array $tabs_data Array of tab data containing name, title, and content.
	 *
	 * @return array Array of HTMLUtility::render() calls for tab content panels.
	 */
	public static function render_tab_content( array $tabs_data ): array {
		$content_items = [];
		$index         = 0;

		foreach ( $tabs_data as $name => $tab ) {
			++$index;

			$content_items[] = HTMLUtility::render(
				[
					'tag'               => 'div',
					'tagEscaped'        => true,
					'attributes'        => [
						'class' => HTMLUtility::classnames(
							[
								'et_pb_tab'            => true,
								'clearfix'             => true,
								'et_pb_active_content' => 1 === $index,
							]
						),
					],
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => HTMLUtility::render(
						[
							'tag'               => 'div',
							'tagEscaped'        => true,
							'attributes'        => [
								'class' => 'et_pb_tab_content',
							],
							'childrenSanitizer' => 'et_core_esc_previously',
							'children'          => $tab['content'],
						]
					),
				]
			);
		}

		return $content_items;
	}

	/**
	 * Gets product tabs data.
	 *
	 * This function retrieves an array of product tabs data that includes:
	 * - additional_information
	 * - description
	 * - reviews
	 * The returned data also includes the tab Name, Title, and Content,
	 * which is the HTML template output of the respective tab.
	 * This function also avoids fetching Tabs content using `the_content` when editing TB layout.
	 *
	 * This function is based on the legacy `ET_Builder_Module_Woocommerce_Tabs::get_tabs()` function.
	 *
	 * @since ??
	 *
	 * @param array $args Additional args.
	 *
	 * @return array Product tabs data.
	 */
	public static function get_product_tabs( array $args = [] ): array {
		global $product, $post, $wp_query;

		/*
		 * Visual builder fetches all tabs data and filters the included tab on the app to reduce
		 * requests between app and server for faster user experience. The frontend passes `includes_tab` to
		 * this method, so it only processes required tabs.
		 */
		$defaults     = [
			'product' => 'current',
		];
		$args         = wp_parse_args( $args, $defaults );
		$product_tabs = [];
		$product_id   = $args['product'];

		// Handle 'current' and 'latest' values for product.
		if ( in_array( $args['product'], [ 'current', 'latest' ], true ) ) {
			// Convert both 'current' and 'latest' to actual product ID using existing utility.
			$product_id = WooCommerceUtils::get_product_id_by_prop( $args['product'] );

			if ( ! $product_id ) {
				return $product_tabs;
			}
		}

		// Determine whether current tabs data needs global variable overwrite or not.
		$overwrite_global = WooCommerceUtils::need_overwrite_global( $product_id );

		// Check if TB is used.
		$is_tb              = Conditions::is_tb_enabled();
		$is_use_placeholder = $is_tb || is_et_pb_preview();

		if ( $is_use_placeholder ) {
			WooCommerceUtils::set_global_objects_for_theme_builder();
		} elseif ( $overwrite_global ) {
			// Save current global variable for later reset.
			$original_product  = $product;
			$original_post     = $post;
			$original_wp_query = $wp_query;

			// Overwrite global variable.
			$post     = get_post( $product_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentionally override global $post, will be restored/reset later.
			$product  = wc_get_product( $product_id );
			$wp_query = new \WP_Query( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentionally override global $wp_query, will be restored/reset later.
				[
					'p'             => $product_id,
					'no_found_rows' => true, // Performance: Skip SQL_CALC_FOUND_ROWS for single post query.
				]
			);

			// Ensure query variables that comments_template() might access are initialized to prevent rtrim() null errors.
			// comments_template() uses get_query_template() which calls untrailingslashit() (which uses rtrim())
			// on query variables. If these are null, PHP 8.1+ throws deprecation warnings.
			$query_vars_to_ensure = [ 'cpage', 'paged', 'page' ];
			foreach ( $query_vars_to_ensure as $var ) {
				if ( ! isset( $wp_query->query_vars[ $var ] ) || null === $wp_query->query_vars[ $var ] ) {
					$wp_query->query_vars[ $var ] = '';
				}
			}
		}

		if ( ! is_a( $post, 'WP_Post' ) || ! is_a( $product, 'WC_Product' ) ) {
			return $product_tabs;
		}

		// Store a reference to the product to ensure it's available when calling comments_template().
		// The global $product might be reset during the loop, so we keep a local reference.
		$product_ref = $product;

		/**
		 * Gets and filters the WooCommerce product tabs.
		 *
		 * @param array $tabs The product tabs.
		 *
		 * @return array The filtered product tabs.
		 */
		$all_tabs    = apply_filters( 'woocommerce_product_tabs', [] );
		$active_tabs = $args['include_tabs'] ?? [];

		// Get product tabs data.
		foreach ( $all_tabs as $name => $tab ) {
			// Skip if current tab is not included, based on `include_tabs` attribute value.
			if ( ! empty( $active_tabs ) && ! in_array( $name, $active_tabs, true ) ) {
				continue;
			}

			if ( 'description' === $name ) {
				if ( ! $is_use_placeholder && ! et_pb_is_pagebuilder_used( $product_id ) ) {
					$layouts = et_theme_builder_get_template_layouts();

					// If selected product doesn't use builder, retrieve post content.
					if ( ! empty( $layouts ) && $layouts[ ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE ]['override'] ) {
						/**
						 * This filter parses the tab content and processes any shortcodes in the content.
						 * This filter is used in place of `the_content` filter because it adds content wrapper.
						 *
						 * This filter is based on the legacy `et_builder_wc_description` filter.
						 *
						 * @param string $content The post content.
						 */
						$tab_content = apply_filters( 'et_builder_wc_description', $post->post_content );
					} else {
						// When Theme Builder layouts are empty or don't override, use post content directly.
						$tab_content = $post->post_content;
					}
				} elseif ( $is_use_placeholder ) {
					/*
					 * Description can't use built in callback data because it gets `the_content`
					 * which might cause infinite loop; get Divi's long description from
					 * post meta instead.
					 */
					$placeholders = WooCommerceUtils::woocommerce_placeholders();

					$tab_content = $placeholders['description'];
				} else {
					/*
					 * Description can't use built in callback data because it gets `the_content`
					 * which might cause infinite loop; get Divi's long description from
					 * post meta instead.
					 */
					$tab_content = get_post_meta( $product_id, WooCommerceHooks::get_long_desc_meta_key(), true );

					/**
					 * This filter parses the tab content and processes any shortcodes in the content.
					 * This filter is used in place of `the_content` filter because it adds content wrapper.
					 *
					 * This filter is based on the legacy `et_builder_wc_description` filter.
					 *
					 * @param string $content The tab content.
					 */
					$tab_content = apply_filters( 'et_builder_wc_description', $tab_content );
				}
			} else {
				// Skip if the 'callback' key does not exist.
				if ( ! isset( $tab['callback'] ) ) {
					continue;
				}

				// Ensure $name is a string to prevent null being passed to callbacks.
				$name = is_string( $name ) ? $name : '';

				// For reviews tab callback, ensure comments template is called with safe globals + params.
				// WooCommerce may provide either `comments_template` or a wrapped callable for reviews.
				$is_reviews_tab_callback = 'reviews' === $name && is_callable( $tab['callback'] );
				if ( 'comments_template' === $tab['callback'] || $is_reviews_tab_callback ) {
					// Ensure product global is set and valid before calling comments_template().
					// WooCommerce's review template expects $product to be a valid WC_Product instance.
					global $product;

					// Ensure the product global is set using our stored reference.
					// This prevents the WooCommerce template from accessing a null product.
					if ( ! is_a( $product, 'WC_Product' ) && is_a( $product_ref, 'WC_Product' ) ) {
						$product = $product_ref; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentionally override global $product.
					}

					if ( ! is_a( $product, 'WC_Product' ) ) {
						// If product is still not valid, skip this tab or use placeholder content.
						$tab_content = '';
					} else {
						// Ensure theme path globals are always strings before comments_template() runs.
						// WordPress core calls trailingslashit()/untrailingslashit() on these values.
						// Null values trigger PHP 8.1+ deprecation warnings via rtrim().
						global $wp_stylesheet_path, $wp_template_path;
						if ( ! isset( $wp_stylesheet_path ) || null === $wp_stylesheet_path ) {
							$stylesheet_path    = get_stylesheet_directory();
							$wp_stylesheet_path = is_string( $stylesheet_path ) ? $stylesheet_path : '';
						}
						if ( ! isset( $wp_template_path ) || null === $wp_template_path ) {
							$template_path    = get_template_directory();
							$wp_template_path = is_string( $template_path ) ? $template_path : '';
						}

						// Ensure query vars are initialized for comments template resolution.
						// WordPress template lookup can flow into `rtrim()` with null query var values.
						if ( is_a( $wp_query, 'WP_Query' ) ) {
							$query_vars_to_ensure = [ 'cpage', 'paged', 'page' ];

							foreach ( $query_vars_to_ensure as $query_var ) {
								if ( ! isset( $wp_query->query_vars[ $query_var ] ) || null === $wp_query->query_vars[ $query_var ] ) {
									$wp_query->query_vars[ $query_var ] = '';
								}
							}
						}

						// Set up post data for template tags and WordPress functions that expect setup_postdata() to be called.
						setup_postdata( $post );

						// Ensure product is still set right before calling comments_template().
						// setup_postdata() might have affected globals, so we re-check and restore if needed.
						// We must ensure $product is valid before comments_template() executes, as WooCommerce's
						// single-product-reviews.php template calls $product->get_review_count() immediately.
						global $product;
						if ( ! is_a( $product, 'WC_Product' ) && is_a( $product_ref, 'WC_Product' ) ) {
							$product = $product_ref; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentionally override global $product.
						}

						// Final check: ensure product is valid right before calling comments_template().
						// If it's still not valid, skip the template call to prevent fatal errors.
						// Use the stored reference as the source of truth.
						$valid_product = is_a( $product, 'WC_Product' ) ? $product : ( is_a( $product_ref, 'WC_Product' ) ? $product_ref : null );

						if ( ! is_a( $valid_product, 'WC_Product' ) ) {
							$tab_content = '';
							// Reset post data even if we skip the template to prevent side effects.
							wp_reset_postdata();
						} else {
							// Ensure product is available in both global scope and $GLOBALS array.
							// WooCommerce templates may access $product via different methods.
							// Set it right before template execution to prevent it from being reset.
							$product            = $valid_product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentionally override global $product.
							$GLOBALS['product'] = $valid_product;

							// Call comments_template() directly with correct parameters instead of via call_user_func.
							ob_start();
							// TODO fix(D5, WooCommerce): Revert to comments_template after WordPress core resolves Trac #61468. [https://github.com/elegantthemes/Divi/issues/28338].
							et_comments_template_safe( '', false );
							$tab_content = ob_get_clean();
							// Ensure $tab_content is a string (ob_get_clean() can return false).
							$tab_content = is_string( $tab_content ) ? $tab_content : '';

							// Reset post data after calling comments_template() to prevent side effects.
							wp_reset_postdata();
						}
					}
				} else {
					// Get tab value based on defined product tab's callback attribute.
					ob_start();
					// @phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- The callable function is hard-coded.

					call_user_func( $tab['callback'], $name, $tab );
					$tab_content = ob_get_clean();
					// Ensure $tab_content is a string (ob_get_clean() can return false).
					$tab_content = is_string( $tab_content ) ? $tab_content : '';
				}
			}

			// Populate product tab data.
			$product_tabs[ $name ] = [
				'name'    => $name,
				'title'   => $tab['title'],
				'content' => $tab_content,
			];
		}

		// Reset overwritten global variable.
		if ( $is_use_placeholder ) {
			WooCommerceUtils::reset_global_objects_for_theme_builder();
		} elseif ( $overwrite_global ) {
			$product  = $original_product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentionally override global $product, restoring previously overridden value.
			$post     = $original_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentionally override global $post, restoring previously overridden value.
			$wp_query = $original_wp_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentionally override global $wp_query, restoring previously overridden value.
		}

		return $product_tabs;
	}
}
