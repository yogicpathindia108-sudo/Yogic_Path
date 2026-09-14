<?php
/**
 * Module Library: WooCommerceProductRating Module
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductRating;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
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
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_Block_Type_Registry;
use WP_Block;

/**
 * WooCommerceProductRatingModule class.
 *
 * This class implements the functionality of a call-to-action component
 * in a frontend application. It provides functions for rendering the
 * WooCommerceProductRating module,
 * managing REST API endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class WooCommerceProductRatingModule implements DependencyInterface {

	/**
	 * Render callback for the WooCommerceProductRating module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ WooCommerceProductRatingEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $content                     The block's content.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the WooCommerceProductRating module.
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
	 * WooCommerceProductRatingModule::render_callback( $attrs, $content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements ): string {
		// Get parameters from attributes.
		$product_id      = $attrs['content']['advanced']['product']['desktop']['value'] ?? WooCommerceUtils::get_default_product();
		$loop_product_id = WooCommerceUtils::get_loop_context_product_id( $attrs, $block );
		if ( $loop_product_id > 0 && 'current' === $product_id ) {
			$product_id = $loop_product_id;
		}

		// Get the rating HTML markup.
		$rating_html = self::get_rating(
			[
				'product' => $product_id,
			]
		);

		// Render empty string if no output or no price value are generated to avoid unwanted vertical space.
		if ( '' === $rating_html ) {
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
							'children'          => $rating_html,
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
	 * arguments. It is used in the `render_callback` function of the WooCommerceProductRating module.
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
	 * WooCommerceProductRatingModule::module_classnames($args);
	 * ```
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		$show_rating       = $attrs['elements']['advanced']['showRating']['desktop']['value'] ?? 'on';
		$show_reviews_link = $attrs['elements']['advanced']['showReviewsLink']['desktop']['value'] ?? 'on';
		$layout            = $attrs['layout']['advanced']['layout']['desktop']['value'] ?? 'inline';

		// Text Options.
		$classnames_instance->add( TextClassnames::text_options_classnames( $attrs['module']['advanced']['text'] ?? [] ), true );

		// Add classnames for no rating.
		if ( 'off' === $show_rating ) {
			$classnames_instance->add( 'et_pb_wc_rating_no_rating' );
		}

		// Add classnames for no reviews link.
		if ( 'off' === $show_reviews_link ) {
			$classnames_instance->add( 'et_pb_wc_rating_no_reviews' );
		}

		// Add classnames for layout.
		$classnames_instance->add( "et_pb_wc_rating_layout_{$layout}" );

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
	 * Star rating style declaration.
	 *
	 * This function is responsible for declaring the star rating styles for the WooCommerce Product Rating module.
	 * It handles letter spacing width calculations and text alignment margins.
	 *
	 * This function is the equivalent of the `starRatingStyleDeclaration` JS function located in
	 * visual-builder/packages/module-library/src/components/woocommerce/product-rating/style-declarations/star-rating/index.ts.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array  $attrValue Optional. The value (breakpoint > state > value) of the module attribute. Default `[]`.
	 * }
	 *
	 * @return string The value of the star rating style declaration.
	 *
	 * @example:
	 * ```php
	 * $params = [
	 *     'attrValue' => [
	 *         'letterSpacing' => '2px',
	 *         'textAlign' => 'center',
	 *     ],
	 *     'important' => [
	 *         'margin-left' => true,
	 *         'margin-right' => true,
	 *     ],
	 *     'returnType' => 'string',
	 * ];
	 *
	 * WooCommerceProductRatingModule::star_rating_style_declaration($params);
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
	 * Star rating font override style declaration.
	 *
	 * This function ensures the WooCommerce star rating element always uses
	 * `font-family: WooCommerce` with `!important` to override possible Text OGP preset
	 * font-family settings that may cascade to the star rating element.
	 *
	 * This function is the equivalent of the TypeScript function
	 * `starRatingFontOverrideStyleDeclaration` located in
	 * `visual-builder/packages/module-library/src/components/woocommerce/product-rating/style-declarations/star-rating-font-override/index.ts`.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array $attrValue Optional. The value (breakpoint > state > value) of the module attribute. Default `[]`.
	 *                            Note: This parameter is unused but required for type compatibility with declaration functions.
	 * }
	 *
	 * @return string The value of the star rating font override style declaration with `font-family: WooCommerce !important`.
	 *
	 * @example:
	 * ```php
	 * $params = [
	 *     'attrValue' => [],
	 *     'returnType' => 'string',
	 * ];
	 *
	 * WooCommerceProductRatingModule::star_rating_font_override_declaration($params);
	 * ```
	 */
	public static function star_rating_font_override_declaration( array $params ): string {
		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => [
					'font-family' => true,
				],
			]
		);

		$style_declarations->add( 'font-family', 'WooCommerce' );

		return $style_declarations->value();
	}

	/**
	 * WooCommerceProductRating module script data.
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
	 * WooCommerceProductRatingModule::module_script_data( $args );
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
	 * WooCommerceProductRating Module's style components.
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
								'disabledOn' => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
							],
						]
					),

					// Body.
					$elements->style(
						[
							'attrName' => 'body',
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
											'selector' => "{$order_class} .star-rating",
											'attr'     => $attrs['rating']['decoration']['font']['font'] ?? [],
											'declarationFunction' => [ self::class, 'star_rating_style_declaration' ],
										],
									],
									[
										// Ensures WooCommerce star rating always uses WooCommerce font with !important,
										// overriding Text OGP preset font-family that cascades to star rating element.
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} .woocommerce-product-rating .star-rating",
											'attr'     => $attrs['body']['decoration']['font']['font'] ?? [],
											'declarationFunction' => [ self::class, 'star_rating_font_override_declaration' ],
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
	 * Get the custom CSS fields for the Divi WooCommerceProductRating module.
	 *
	 * This function retrieves the custom CSS fields defined for the Divi WooCommerceProductRating module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /api/js/divi-module-library/functions/generateDefaultAttrs cssFields}
	 * located in `@divi/module-library`. Note that this function does not have
	 * a `label` property on each array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi WooCommerceProductRating module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = CustomCssTrait::custom_css();
	 * // Returns an array of custom CSS fields for the WooCommerceProductRating module.
	 * ```
	 */
	public static function custom_css(): array {
		return WP_Block_Type_Registry::get_instance()->get_registered( 'divi/woocommerce-product-rating' )->customCssFields;
	}

	/**
	 * Loads `WooCommerceProductRatingModule` and registers Front-End render callback and REST API Endpoints.
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
			'divi_module_library_module_default_attributes_divi/woocommerce-product-rating',
			[ WooCommerceUtils::class, 'process_dynamic_attr_defaults' ],
			10,
			2
		);

		$module_json_folder_path = dirname( __DIR__, 5 ) . '/visual-builder/packages/module-library/src/components/woocommerce/product-rating/';

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
	 * Retrieves the product rating for a given set of arguments.
	 *
	 * This function checks if the theme builder is enabled and returns a placeholder
	 * title if so. Otherwise, it uses the WooCommerceUtils to render the module template
	 * for the product title based on the provided arguments.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Optional. An array of arguments for rendering the product rating.
	 *
	 *     @type string $product Optional. The product identifier. Default 'current'.
	 * }
	 *
	 * @return string The rendered product rating.
	 *
	 * @example:
	 * ```php
	 * $rating = WooCommerceProductRatingModule::get_rating();
	 * // Returns the product rating for the current product.
	 *
	 * $rating = WooCommerceProductRatingModule::get_rating( [ 'product' => 123 ] );
	 * // Returns the product rating for the product with ID 123.
	 * ```
	 */
	public static function get_rating( array $args = [] ): string {
		// Needed for product post-type.
		if ( ! isset( $args['product'] ) ) {
			$args['product'] = WooCommerceUtils::get_product_id( 'current' );
		}

		if ( 'current' !== $args['product'] ) {
			// Enable comments via filter to render the review link.
			add_filter( 'comments_open', '__return_true' );
		}

		$rating = WooCommerceUtils::render_module_template(
			'woocommerce_template_single_rating',
			$args,
			[ 'product', 'wp_query' ]
		);

		if ( 'current' !== $args['product'] ) {
			// Remove filter after module is rendered.
			remove_filter( 'comments_open', '__return_true' );
		}

		return $rating;
	}
}
