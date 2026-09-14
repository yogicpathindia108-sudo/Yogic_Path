<?php
/**
 * Module Library: WooCommerce Breadcrumb Module
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\Breadcrumb;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewUtils;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentPosts;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use ET_Post_Stack;
use Exception;
use WP_Block_Type_Registry;
use WP_Block;

/**
 * WooCommerceBreadcrumbModule class.
 *
 * This class implements the functionality of a breadcrumb component
 * in a frontend application. It provides functions for rendering the
 * WooCommerceBreadcrumb module,
 * managing REST API endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class WooCommerceBreadcrumbModule implements DependencyInterface {
	/**
	 * Home URL.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	public static $home_url;

	/**
	 * Render callback for the WooCommerceBreadcrumb module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ WooCommerceBreadcrumbEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $content                     The block's content.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the WooCommerceBreadcrumb module.
	 *
	 * @throws Exception If rendering fails or required dependencies are missing.
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
	 * WooCommerceBreadcrumbModule::render_callback( $attrs, $content, $block, $elements );
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
		$breadcrumb_home_text = $attrs['content']['advanced']['homeText'][ $default_breakpoint ][ $default_state ] ?? '';
		$breadcrumb_home_url  = $attrs['content']['advanced']['homeUrl'][ $default_breakpoint ][ $default_state ] ?? '';
		$breadcrumb_separator = $attrs['content']['advanced']['separator'][ $default_breakpoint ][ $default_state ] ?? '';

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		// Get the breadcrumb HTML.
		$breadcrumb_html = self::get_breadcrumb(
			[
				'product'              => $product_id,
				'breadcrumb_home_text' => $breadcrumb_home_text,
				'breadcrumb_home_url'  => $breadcrumb_home_url,
				'breadcrumb_separator' => $breadcrumb_separator,
			]
		);

		// Render an empty string if no $breadcrumb_html is generated to avoid unwanted vertical space.
		if ( '' === $breadcrumb_html ) {
			return '';
		}

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
							// Use the generated breadcrumb HTML.
							'children'          => $breadcrumb_html,
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
	 * arguments. It is used in the `render_callback` function of the WooCommerceBreadcrumb module.
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
	 * WooCommerceBreadcrumbModule::module_classnames($args);
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
	 * WooCommerceBreadcrumb module script data.
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
		$id       = $args['id'] ?? '';
		$selector = $args['selector'] ?? '';
		$elements = $args['elements'];

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
	}

	/**
	 * WooCommerceBreadcrumb Module's style components.
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
	 *      @type array $attrs              Module attributes.
	 *      @type array $parentAttrs        Parent attrs.
	 *      @type string $orderClass        Selector class name.
	 *      @type string $parentOrderClass  Parent selector class name.
	 *      @type string $wrapperOrderClass Wrapper selector class name.
	 *      @type array $settings           Custom settings.
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
											'selector' => "{$order_class} .woocommerce-breadcrumb",
											'attr'     => $attrs['module']['advanced']['text'] ?? [],
										],
									],
								],
							],
						]
					),
					// Content.
					$elements->style(
						[
							'attrName' => 'content',
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
	 * Get the custom CSS fields for the Divi WooCommerceBreadcrumb module.
	 *
	 * This function retrieves the custom CSS fields defined for the Divi WooCommerceBreadcrumb module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /api/js/divi-module-library/functions/generateDefaultAttrs cssFields}
	 * located in `@divi/module-library`. Note that this function does not have
	 * a `label` property on each array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi WooCommerceBreadcrumb module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = WooCommerceBreadcrumbModule::custom_css();
	 * // Returns an array of custom CSS fields for the WooCommerceBreadcrumb module.
	 * ```
	 */
	public static function custom_css(): array {
		$registered_block = WP_Block_Type_Registry::get_instance()->get_registered( 'divi/woocommerce-breadcrumb' );

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
	 * Modify home URL for the breadcrumb.
	 *
	 * This method is used as a callback for the 'woocommerce_breadcrumb_home_url' filter
	 * to modify the home URL used in the breadcrumb.
	 *
	 * @since ??
	 *
	 * @return string The modified home URL.
	 */
	public static function modify_home_url(): string {
		return self::$home_url;
	}

	/**
	 * Load WooCommerce Breadcrumb Module.
	 *
	 * This function loads the WooCommerce Breadcrumb module by registering it
	 * with the `ModuleRegistration` class. It ensures that the module is only
	 * loaded if the WooCommerce plugin is active and the `wooProductPageModules`
	 * feature flag is enabled.
	 *
	 * @since ??
	 *
	 * @return void
	 *
	 * @throws Exception If module registration fails.
	 */
	public function load(): void {
		/*
		 * Bail if WooCommerce plugin is not active or the feature-flag `wooProductPageModules` is disabled.
		 */
		if ( ! et_is_woocommerce_plugin_active() ) {
			return;
		}

		// Add a filter for processing dynamic attribute defaults.
		add_filter(
			'divi_module_library_module_default_attributes_divi/woocommerce-breadcrumb',
			[ WooCommerceUtils::class, 'process_dynamic_attr_defaults' ],
			10,
			2
		);

		$module_json_folder_path = dirname( __DIR__, 5 ) . '/visual-builder/packages/module-library/src/components/woocommerce/breadcrumb/';

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
	 * Generate the WooCommerce breadcrumb trail.
	 *
	 * This method generates an HTML representation of the WooCommerce breadcrumb trail, allowing customization
	 * through parameters and filters. It returns formatted breadcrumb content based on provided or default
	 * arguments. If in the theme builder or preview mode, a placeholder breadcrumb is returned instead.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Optional. Array of arguments to customize the breadcrumb.
	 *
	 *     @type string $product Specifies the context of the product ('current' or a specific ID).
	 *     @type string $breadcrumb_home_text Text label for the home page link in the breadcrumb. Default 'Home'.
	 *     @type string $breadcrumb_home_url URL for the home page link in the breadcrumb. Default site home URL.
	 *     @type string $breadcrumb_separator Separator character or string between breadcrumb items. Default '/'.
	 *     @type bool   $use_placeholders Whether to return placeholders for Visual Builder. Default false.
	 * }
	 *
	 * @return string The generated HTML content for the breadcrumb.
	 *
	 * @throws Exception If breadcrumb generation fails or required dependencies are missing.
	 */
	public static function get_breadcrumb( array $args = [] ): string {
		// Set default values for missing parameters.
		$use_placeholders = $args['use_placeholders'] ?? false;
		$args             = [
			'product'              => empty( $args['product'] ) ? 'current' : $args['product'],
			'breadcrumb_home_text' => empty( $args['breadcrumb_home_text'] ) ? __( 'Home', 'et_builder_5' ) : $args['breadcrumb_home_text'],
			'breadcrumb_home_url'  => empty( $args['breadcrumb_home_url'] ) ? get_home_url() : $args['breadcrumb_home_url'],
			'breadcrumb_separator' => empty( $args['breadcrumb_separator'] ) ? '/' : esc_html( $args['breadcrumb_separator'] ),
		];

		$args['breadcrumb_separator'] = esc_html( $args['breadcrumb_separator'] );

		/*
		 * Use placeholders in Visual Builder for better performance.
		 *
		 * Placeholders like %HOME_TEXT% allow the client to replace values instantly
		 * without making new REST API calls on each field change, providing a more
		 * responsive user experience.
		 *
		 * Placeholders are used when:
		 * - Explicitly requested
		 * - In Visual Builder (outside of REST API requests)
		 * - During preview mode
		 */
		$main_query_post_id = ET_Post_Stack::get_main_post_id();
		$layout_post_id     = Style::get_layout_id();
		$is_visual_builder  = Conditions::is_vb_app_window() && $main_query_post_id === $layout_post_id;

		// Enable placeholders when explicitly requested or in Visual Builder contexts.
		$use_placeholders = $use_placeholders || (
				! Conditions::is_rest_api_request() &&
				( $is_visual_builder || is_et_pb_preview() )
			);

		if ( $use_placeholders ) {
			$args = wp_parse_args(
				[
					'breadcrumb_home_text' => '%HOME_TEXT%',
					'breadcrumb_home_url'  => '%HOME_URL%',
					'breadcrumb_separator' => '%SEPARATOR%',
				],
				$args
			);
		}

		// Generate breadcrumb with user settings, handling Theme Builder context automatically.
		return self::_generate_breadcrumb_with_settings( $args );
	}

	/**
	 * Generate breadcrumb with user-defined settings applied.
	 *
	 * This helper method applies user customizations (home text, URL, separator) to the breadcrumb
	 * generation process using filters and WooCommerce's template rendering system. It automatically
	 * handles Theme Builder context by setting proper globals when needed.
	 *
	 * @since ??
	 *
	 * @param array $args Processed arguments with user settings.
	 *
	 * @return string The generated HTML content for the breadcrumb.
	 */
	private static function _generate_breadcrumb_with_settings( array $args ): string {
		// Handle Theme Builder context for 'current' product.
		if ( 'current' === $args['product'] ) {
			return self::_handle_theme_builder_context( $args );
		}

		// Handle specific product ID with standard context.
		return self::_render_breadcrumb_with_filters( $args );
	}

	/**
	 * Handle Theme Builder context for current product breadcrumbs.
	 *
	 * @since ??
	 *
	 * @param array $args Processed arguments with user settings.
	 *
	 * @return string The generated HTML content for the breadcrumb.
	 */
	private static function _handle_theme_builder_context( array $args ): string {
		// Check if we're in Theme Builder context.
		$is_theme_builder = \ET_Theme_Builder_Layout::is_theme_builder_layout();

		if ( ! $is_theme_builder ) {
			return self::_render_breadcrumb_with_filters( $args );
		}

		// Theme Builder context - check if we need global context switching.
		$main_post_id = \ET_Post_Stack::get_main_post_id();

		$current_id = get_the_ID();

		// If post IDs match, no context switching needed.
		if ( $current_id === $main_post_id ) {
			return self::_render_breadcrumb_with_filters( $args );
		}

		// Need to switch global context for proper WooCommerce breadcrumb.
		if ( ! $main_post_id || ! is_product() ) {
			return self::_render_breadcrumb_with_filters( $args );
		}

		// Switch globals, render, then restore.
		global $post, $product;

		$original_post    = $post;
		$original_product = $product;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required for WooCommerce breadcrumb context.
		$post    = get_post( $main_post_id );
		$product = wc_get_product( $main_post_id );

		$breadcrumb_output = self::_render_breadcrumb_with_filters( $args );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring original context.
		$post    = $original_post;
		$product = $original_product;

		return $breadcrumb_output;
	}

	/**
	 * Render breadcrumb with user filters applied.
	 *
	 * @since ??
	 *
	 * @param array $args Processed arguments with user settings.
	 *
	 * @return string The generated HTML content for the breadcrumb.
	 */
	private static function _render_breadcrumb_with_filters( array $args ): string {
		// Handle 'current' value for product.
		if ( 'current' === $args['product'] && ! Conditions::is_rest_api_request() ) {
			global $product;

			$product = WooCommerceUtils::get_product( $args['product'] );
		}

		// Update home URL which is rendered inside the breadcrumb function and pluggable via filter.
		self::$home_url = $args['breadcrumb_home_url'];
		add_filter(
			'woocommerce_breadcrumb_home_url',
			[ self::class, 'modify_home_url' ]
		);

		// Generate breadcrumb HTML using WooCommerceUtils::render_module_template.
		$breadcrumb = WooCommerceUtils::render_module_template(
			'woocommerce_breadcrumb',
			$args,
			[
				'product',
				'post',
				'wp_query',
			]
		);

		// Reset home URL.
		self::$home_url = get_home_url();
		remove_filter(
			'woocommerce_breadcrumb_home_url',
			[ self::class, 'modify_home_url' ]
		);

		return $breadcrumb;
	}
}
