<?php
/**
 * ModuleLibrary: Portfolio Module class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Portfolio;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\IconLibrary\IconFont\Utils as IconFontUtils;
use ET\Builder\Packages\IconLibrary\IconFont\Utils;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\BoxShadow\BoxShadowClassnames;
use ET\Builder\Packages\Module\Options\BoxShadow\BoxShadowUtils;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\ModuleLibrary\Common\ImageWrapperAnimation;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleLibrary\Portfolio\PortfolioPresetAttrsMap;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\ModuleUtils\ImageUtils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\Module\Layout\Components\StyleCommon\CommonStyle;
use WP_Block;
use WP_REST_Request;
use WP_REST_Response;
use ET\Builder\Framework\Breakpoint\Breakpoint;

/**
 * PortfolioModule class.
 *
 * This class contains functionality used for Portfolio Module such as Front-End rendering, REST API Endpoints etc.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 */
class PortfolioModule implements DependencyInterface {



	/**
	 * Get the module classnames for the Portfolio module.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/module-library/portfolio-module-classnames moduleClassnames} located in `@divi/module-library` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type object $classnamesInstance An instance of `ET\Builder\Packages\Module\Layout\Components\Classnames` class.
	 *     @type array  $attrs              Block attributes data that is being rendered.
	 * }
	 *
	 * @return void
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		// Portfolio Grid Layout (Flex Settings).
		$portfolio_grid_layout = $attrs['portfolioGrid']['decoration']['layout']['desktop']['value']['display'] ?? 'grid';
		$is_flex_layout        = 'flex' === $portfolio_grid_layout;

		// Text Options.
		$classnames_instance->add( TextClassnames::text_options_classnames( $attrs['module']['advanced']['text'] ?? [] ), true );

		// Module components - always use grid layout now.
		$classnames_instance->add( 'clearfix' );

		// Note: et_flex_grid class is now added to the .et_pb_portfolio_grid_items element
		// in the render_callback function rather than to the main module element.

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
	 * Set Portfolio module script data.
	 *
	 * This function generates and sets the script data for the module,
	 * which includes assigning variables, setting element script data options,
	 * and setting visibility for certain elements based on the provided attributes.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Array of arguments for generating the script data.
	 *
	 *     @type string $id             Optional. The ID of the module. Default empty string.
	 *     @type string $name           Optional. The name of the module. Default empty string.
	 *     @type string $selector       Optional. The selector of the module. Default empty string.
	 *     @type array  $attrs          Optional. The attributes of the module. Default `[]`.
	 *     @type object $elements       The elements object.
	 *     @type int    $store_instance Optional. The ID of instance where this block stored in BlockParserStore. Default `null`.
	 * }
	 *
	 * @return void
	 *
	 * @example:
	 * ```php
	 *     // Generate the script data for a module with specific arguments.
	 *     $args = array(
	 *         'id'             => 'my-module',
	 *         'name'           => 'My Module',
	 *         'selector'       => '.my-module',
	 *         'attrs'          => array(
	 *             'portfolio' => array(
	 *                 'advanced' => array(
	 *                     'showTitle'       => false,
	 *                     'showCategories'  => true,
	 *                     'showPagination' => true,
	 *                 )
	 *             )
	 *         ),
	 *         'elements'       => $elements,
	 *         'storeInstance' => 123,
	 *     );
	 *
	 *     PortfolioModule::module_script_data( $args );
	 * ```
	 */
	public static function module_script_data( array $args ): void {
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

		$elements->script_data(
			[
				'attrName' => 'portfolioGrid',
			]
		);

		// Get layout details for responsive column classes.
		$portfolio_grid_layout = $attrs['portfolioGrid']['decoration']['layout']['desktop']['value']['display'] ?? 'grid';
		$is_flex_layout        = 'flex' === $portfolio_grid_layout;

		MultiViewScriptData::set(
			[
				'id'            => $id,
				'name'          => $name,
				'storeInstance' => $store_instance,
				'hoverSelector' => $selector,
				'setVisibility' => [
					[
						'selector'      => $selector . ' .et_pb_module_header',
						'data'          => $attrs['portfolio']['advanced']['showTitle'] ?? [],
						'valueResolver' => function ( $value ) {
							return 'off' !== $value ? 'visible' : 'hidden';
						},
					],
					[
						'selector'      => $selector . ' .post-meta',
						'data'          => $attrs['portfolio']['advanced']['showCategories'] ?? [],
						'valueResolver' => function ( $value ) {
							return 'off' !== $value ? 'visible' : 'hidden';
						},
					],
					[
						'selector'      => implode(
							',',
							[
								$selector . ' .wp-pagenavi',
								$selector . ' .pagination',
							]
						),
						'data'          => $attrs['portfolio']['advanced']['showPagination'] ?? [],
						'valueResolver' => function ( $value ) {
							return 'off' !== $value && ! is_search() ? 'visible' : 'hidden';
						},
					],
				],
			]
		);
	}

	/**
	 * Custom CSS fields
	 *
	 * This function is equivalent of JS const cssFields located in
	 * visual-builder/packages/module-library/src/components/portfolio/custom-css.ts.
	 *
	 * A minor difference with the JS const cssFields, this function did not have `label` property on each array item.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function custom_css() {
		return \WP_Block_Type_Registry::get_instance()->get_registered( 'divi/portfolio' )->customCssFields;
	}


	/**
	 * Overlay Icon style declaration.
	 *
	 * Retrieves the style declaration for the overlay icon based on the provided parameters.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     Optional. Parameters for generating the style declaration.
	 *
	 *     @type array $attrValue Optional. The value (breakpoint > state > value) of the module attribute. Default `[]`.
	 * }
	 *
	 * @return string The style declaration for the overlay icon.
	 *
	 * @example:
	 * ```php
	 *     // Get the style declaration for the overlay icon.
	 *     $params = array(
	 *         'attrValue' => array(
	 *             'type'    => 'fa',
	 *             'weight'  => 'bold',
	 *             'unicode' => 'f123',
	 *         ),
	 *     );
	 *     $style_declaration = PortfolioModule::overlay_icon_style_declaration( $params );
	 *
	 *     // Output: 'font-family: FontAwesome; font-weight: bold; content: "\f123";'
	 * ```
	 */
	public static function overlay_icon_style_declaration( array $params ): string {
		$overlay_icon_attr = $params['attrValue'] ?? [];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => [
					'font-family' => true,
				],
			]
		);

		if ( isset( $overlay_icon_attr['type'] ) && ! empty( $overlay_icon_attr['type'] ) ) {
			$font_family = IconFontUtils::is_fa_icon( $overlay_icon_attr ) ? 'FontAwesome' : 'ETmodules';
			$style_declarations->add( 'font-family', "'{$font_family}'" );
		}

		if ( isset( $overlay_icon_attr['weight'] ) && ! empty( $overlay_icon_attr['weight'] ) ) {
			$style_declarations->add( 'font-weight', $overlay_icon_attr['weight'] );
		}

		if ( isset( $overlay_icon_attr['unicode'] ) && ! empty( $overlay_icon_attr['unicode'] ) ) {
			$style_declarations->add( 'content', '"' . IconFontUtils::escape_font_icon( IconFontUtils::process_font_icon( $overlay_icon_attr ) ) . '"' );
		}

		return $style_declarations->value();
	}

	/**
	 * Portfolio Grid Item's CSS declaration for horizontal gap.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of parameters.
	 *
	 *     @type string $selector    Selector.
	 *     @type array  $attr        Attribute.
	 *     @type bool   $important   Important.
	 *     @type string $returnType  Return type.
	 * }
	 *
	 * @return string
	 */
	public static function portfolio_grid_item_style_declaration( array $params ): string {
		$declarations = new StyleDeclarations( $params );
		$attr         = $params['attr'] ?? [];

		return $declarations->value();
	}

	/**
	 * Load Portfolio module styles.
	 *
	 * This function is responsible for loading styles for the module. It takes an array of arguments
	 * which includes the module ID, name, attributes, settings, and other details. The function then
	 * uses these arguments to dynamically generate and add the required styles.
	 *
	 * This function is equivalent of JS function:
	 * {@link /docs/builder-api/js/module-library/portfolio-module-styles ModuleStyles}
	 * located in `@divi/module-library` package.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string         $id                       The module ID. In Visual Builder (VB), the ID of the module is a UUIDV4 string.
	 *                                                    In FrontEnd (FE), the ID is the order index.
	 *     @type string         $name                     The module name.
	 *     @type array          $attrs                    Optional. The module attributes. Default `[]`.
	 *     @type array          $settings                 Optional. The module settings. Default `[]`.
	 *     @type array          $defaultPrintedStyleAttrs Optional. The default printed style attributes. Default `[]`.
	 *     @type string         $orderClass               The selector class name.
	 *     @type int            $orderIndex               The order index of the module.
	 *     @type int            $storeInstance            The ID of instance where this block stored in BlockParserStore.
	 *     @type ModuleElements $elements                 The ModuleElements instance.
	 * }
	 *
	 * @return void
	 *
	 * @example:
	 * ```php
	 *     PortfolioModule::module_styles([
	 *         'id'        => 'module-1',
	 *         'name'      => 'Accordion Module',
	 *         'attrs'     => [],
	 *         'elements'  => $elementsInstance,
	 *         'settings'  => $moduleSettings,
	 *         'orderClass'=> '.accordion-module'
	 *     ]);
	 * ```
	 */
	public static function module_styles( array $args ): void {
		$attrs            = $args['attrs'] ?? [];
		$heading_level    = $attrs['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] ?? 'h2';
		$main_selector    = "{$args['orderClass']} .et_pb_portfolio_item";
		$heading_selector = "{$main_selector} {$heading_level}";
		$elements         = $args['elements'] ?? [];
		$settings         = $args['settings'] ?? [];
		$order_class      = $args['orderClass'] ?? '';

		$default_printed_style_attrs = $args['defaultPrintedStyleAttrs'] ?? [];
		$is_inside_sticky_module     = $elements->get_is_inside_sticky_module();
		$sticky_parent_order_class   = $elements->get_sticky_parent_order_class();

		$pagination_font_attr = $attrs['pagination']['decoration']['font']['font'] ?? [];
		$pagination_color     = $pagination_font_attr['desktop']['value']['color'] ?? null;

		$primary_color = empty( $pagination_color ) ? GlobalData::get_accent_color( 'primary' ) : null;
		if ( empty( $primary_color ) && empty( $pagination_color ) ) {
			$primary_color = 'var(--gcid-primary-color, #2ea3f2)';
		}

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
								'advancedStyles'           => [
									[
										'componentName' => 'divi/text',
										'props'         => [
											'selector' => implode(
												', ',
												[
													$args['orderClass'] . ' .et_pb_module_header',
													$args['orderClass'] . ' .post-meta',
												]
											),
											'attr'     => $attrs['module']['advanced']['text'] ?? [],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $main_selector,
											'attr'     => $attrs['module']['decoration']['border'] ?? [],
											'declarationFunction' => function ( $params ) use ( $attrs ) {
												$overflow_attr = $attrs['module']['decoration']['overflow'] ?? [];
												return Declarations::overflow_for_border_radius_style_declaration( $params, $overflow_attr );
											},
										],
									],
									[
										'componentName' => 'divi/background',
										'props'         => [
											'selector' => $main_selector,
											'attr'     => $attrs['module']['decoration']['background'] ?? [],
										],
									],
								],
							],
						]
					),

					// Image.
					$elements->style(
						[
							'attrName'   => 'image',
							'styleProps' => [
								'fit'            => [
									'selector' => "{$order_class} .et_portfolio_image img",
								],
								'sizing'         => [
									'propertySelectors' => [
										'desktop' => [
											'value' => [
												'aspect-ratio' => "{$order_class} .et_portfolio_image img",
											],
										],
									],
								],
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $main_selector . ' .et_portfolio_image',
											'attr'     => $attrs['image']['decoration']['border'] ?? [],
											'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
										],
									],
								],
							],
						]
					),

					// Title.
					$elements->style(
						[
							'attrName'   => 'title',
							'styleProps' => [
								'selector' => implode(
									', ',
									[
										$heading_selector,
										$heading_selector . ' a',
										$heading_selector . '.et_pb_module_header',
										$heading_selector . '.et_pb_module_header a',
									]
								),
							],
						]
					),

					// Meta.
					$elements->style(
						[
							'attrName' => 'meta',
						]
					),

					// Pagination.
					$elements->style(
						[
							'attrName' => 'pagination',
						]
					),

					( empty( $pagination_color ) && ! empty( $primary_color ) )
						? CommonStyle::style(
							[
								'selector'               => "{$order_class} .wp-pagenavi a, {$order_class} .wp-pagenavi span.current, {$order_class} .wp-pagenavi span.pages, {$order_class} .wp-pagenavi span.extend, {$order_class} .pagination a",
								'attr'                   => [
									'desktop' => [
										'value' => $primary_color,
									],
								],
								'property'               => 'color',
								'important'              => true,
								'orderClass'             => $order_class,
								'isInsideStickyModule'   => $is_inside_sticky_module,
								'stickyParentOrderClass' => $sticky_parent_order_class,
							]
						)
						: null,

					// Portfolio Grid Container.
					$elements->style(
						[
							'attrName'   => 'portfolioGrid',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'attr' => $attrs['portfolioGrid']['decoration']['layout'] ?? [],
											'declarationFunction' => [ self::class, 'portfolio_grid_item_style_declaration' ],
											'selectorFunction' => function ( $params ) {
												return $params['selector'] . '> .et_flex_column';
											},
										],
									],
								],
							],
						]
					),

					// Overlay.
					$elements->style(
						[
							'attrName'   => 'overlay',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector'  => "{$args['orderClass']} .et_overlay:before",
											'attr'      => $attrs['overlay']['advanced']['iconColor'] ?? [],
											'property'  => 'color',
											'important' => true,
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector'  => "{$args['orderClass']} .et_overlay:before",
											'attr'      => $attrs['overlay']['advanced']['hoverIcon'] ?? [],
											'declarationFunction' => [ self::class, 'overlay_icon_style_declaration' ],
											'important' => true,
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
	 * Render callback for the Portfolio module.
	 *
	 * Generates the HTML output for the NumberCounter module, including the percent value, percent symbol,
	 * percent wrapper, title, and other necessary components.
	 * This HTML is then rendered on the FrontEnd (FE).
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       The block attributes.
	 * @param string         $content                     The block content.
	 * @param WP_Block       $block                       The block object.
	 * @param ModuleElements $elements                    The elements object.
	 * @param array          $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string The rendered HTML output.
	 *
	 * @example:
	 * ```php
	 * $attrs = [
	 *     'number' => [
	 *         'advanced' => [
	 *             'enablePercentSign' => [
	 *                 'desktop' => [
	 *                     'value' => 'on',
	 *                 ],
	 *             ],
	 *         ],
	 *     ],
	 *     // Other attributes...
	 * ];
	 * $content = 'Block content';
	 * $result = NumberCounter::render_callback( $attrs, $content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements, array $default_printed_style_attrs ): string {
		// Extract child modules IDs using helper utility.
		$children_ids = ChildrenUtils::extract_children_ids( $block );

		$posts_per_page    = $attrs['portfolio']['innerContent']['desktop']['value']['postsNumber'] ?? '';
		$selected_term     = $attrs['portfolio']['innerContent']['desktop']['value']['includedCategories'] ?? [];
		$selected_term_ids = is_string( $selected_term ) ? explode( ',', $selected_term ) : $selected_term;
		$heading_level     = $attrs['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] ?? '';

		$hover_icon        = $attrs['overlay']['advanced']['hoverIcon']['desktop']['value'] ?? '';
		$hover_icon_tablet = $attrs['overlay']['advanced']['hoverIcon']['tablet']['value'] ?? '';
		$hover_icon_phone  = $attrs['overlay']['advanced']['hoverIcon']['phone']['value'] ?? '';
		$hover_icon_sticky = $attrs['overlay']['advanced']['hoverIcon']['desktop']['sticky'] ?? '';

		$icon                         = $hover_icon ? Utils::process_font_icon( $hover_icon ) : '';
		$icon_tablet                  = $hover_icon_tablet ? Utils::process_font_icon( $hover_icon_tablet ) : '';
		$icon_phone                   = $hover_icon_phone ? Utils::process_font_icon( $hover_icon_phone ) : '';
		$icon_sticky                  = $hover_icon_sticky ? Utils::process_font_icon( $hover_icon_sticky ) : '';
		$is_title_visible             = ModuleUtils::has_value(
			$attrs['portfolio']['advanced']['showTitle'] ?? [],
			[
				'valueResolver' => function ( $value ) {
					return 'off' !== $value;
				},
			]
		);
		$is_categories_visible        = ModuleUtils::has_value(
			$attrs['portfolio']['advanced']['showCategories'] ?? [],
			[
				'valueResolver' => function ( $value ) {
					return 'off' !== $value;
				},
			]
		);
		$is_pagination_visible        = ModuleUtils::has_value(
			$attrs['portfolio']['advanced']['showPagination'] ?? [],
			[
				'valueResolver' => function ( $value ) {
					return 'off' !== $value;
				},
			]
		);
		$is_wp_pagenavi_plugin_active = function_exists( 'wp_pagenavi' );

		// Check layout display to determine if overlay should be rendered.
		// Overlay is only rendered when Layout Style is set to "grid".
		$portfolio_grid_layout_display = $attrs['portfolioGrid']['decoration']['layout']['desktop']['value']['display'] ?? 'grid';
		$should_render_overlay         = 'grid' === $portfolio_grid_layout_display;

		/**
		 * Renders Portfolio Hover Overlay HTML based on selected hover icons.
		 * Only rendered when Layout Style is "grid".
		 */
		$overlay = '';
		if ( $should_render_overlay ) {
			$overlay = HTMLUtility::render(
				[
					'tag'               => 'span',
					'attributes'        => [
						'class'            => HTMLUtility::classnames(
							[
								'et_overlay'               => true,
								'et_pb_inline_icon'        => ! empty( $icon ),
								'et_pb_inline_icon_tablet' => ! empty( $icon_tablet ),
								'et_pb_inline_icon_phone'  => ! empty( $icon_phone ),
								'et_pb_inline_icon_sticky' => ! empty( $icon_sticky ),
							]
						),
						'data-icon'        => ! empty( $icon ) ? $icon : null,
						'data-icon-tablet' => ! empty( $icon_tablet ) ? $icon_tablet : null,
						'data-icon-phone'  => ! empty( $icon_phone ) ? $icon_phone : null,
						'data-icon-sticky' => ! empty( $icon_sticky ) ? $icon_sticky : null,
					],
					'childrenSanitizer' => 'et_core_esc_previously',
				]
			);
		}

		/**
		 * Composes and runs the post query.
		 */
		// Static front pages use the `page` query var for pagination; other views use `paged`.
		$portfolio_paged = self::get_pagination_page_for_query();
		$query_args      = [
			'posts_per_page' => (int) $posts_per_page,
			'paged'          => $portfolio_paged,
			'post_type'      => 'project',
			'post_status'    => [ 'publish', 'private' ],
			'perm'           => 'readable',
		];
		$posts           = [];

		// Apply category filtering using the consolidated utility method.
		$query_args = ModuleUtils::add_category_query_args( $query_args, $selected_term_ids, 'project' );

		// Exclude current post when using "Current Category" on project pages or Theme Builder context.
		if ( is_array( $selected_term_ids ) && in_array( 'current', $selected_term_ids, true ) ) {
			$current_post_id = self::_get_current_post_id_for_exclusion();
			if ( $current_post_id > 0 ) {
				if ( isset( $query_args['post__not_in'] ) ) {
					$query_args['post__not_in'] = array_unique( array_merge( $query_args['post__not_in'], [ $current_post_id ] ) );
				} else {
					$query_args['post__not_in'] = [ $current_post_id ];
				}
			}
		}

		$query       = new \WP_Query( $query_args );
		$items_count = 0;

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();

				/**
				 * Stores reusable variable data.
				 */
				$post_id           = get_the_ID();
				$title             = get_the_title();
				$permalink         = get_permalink( $post_id );
				$post_thumbnail_id = get_post_thumbnail_id();

				/**
				 * Composes portfolio post class names, heading.
				 */
				$post_default_classes = get_post_class( 'et_pb_portfolio_item', $post_id );
				// Always add grid item class since we're always in grid mode now.
				$post_classes = array_merge( [ 'et_pb_grid_item' ], $post_default_classes );

				// add order item class.
				$item_class = sprintf( 'et_pb_portfolio_item_%1$s_%2$s', (int) $block->parsed_block['orderIndex'], (int) $items_count );
				array_push( $post_classes, $item_class );

				++$items_count;

				$heading = $elements->render(
					[
						'attrName'          => 'title',
						'tagName'           => $heading_level,
						'skipAttrChildren'  => true,
						'childrenSanitizer' => 'et_core_esc_previously',
						'children'          => sprintf(
							'<a href="%1$s" title="%2$s">%3$s</a>',
							esc_url( $permalink ),
							esc_attr( $title ),
							esc_html( $title )
						),
					]
				);

				// Box shadow overlay.
				$box_shadow_components_overlay     = '';
				$box_shadow_classnames_has_overlay = '';
				$thumbnail_wrapper_classnames      = 'et_portfolio_image';

				if ( BoxShadowUtils::is_overlay_enabled( $attrs['image']['decoration']['boxShadow'] ?? [] ) ) {
					$box_shadow_components_overlay     = $elements->style_components(
						[
							'attrName' => 'image',
						]
					);
					$box_shadow_classnames_has_overlay = BoxShadowClassnames::has_overlay( $attrs['image']['decoration']['boxShadow'] ?? [] );
				}

				$thumbnail_wrapper_classnames = HTMLUtility::classnames(
					$thumbnail_wrapper_classnames,
					$box_shadow_classnames_has_overlay,
					ImageWrapperAnimation::wrapper_animation_classname( $attrs['image'] ?? [] )
				);

				/**
				 * Composes portfolio post thumbnails.
				 */
				// Determine thumbnail layout: 'grid' for grid layout, 'fullwidth' for others.
				$thumbnail_layout = 'grid' === $portfolio_grid_layout_display ? 'grid' : 'fullwidth';

				$thumbnail = has_post_thumbnail( $post_id ) ? sprintf(
					'
				<a href="%1$s" title="%2$s">
					<span class="%5$s">
						%3$s
						%4$s
					</span>
				</a>',
					esc_url( $permalink ),
					esc_attr( $title ),
					$box_shadow_components_overlay . self::get_portfolio_thumbnail( $post_thumbnail_id, $thumbnail_layout, $elements, $attrs ),
					$overlay,
					$thumbnail_wrapper_classnames
				) : '';

				/**
				 * Composes portfolio post categories.
				 */
				$categories        = '';
				$categories_object = get_the_terms( $post_id, 'project_category' );
				if ( $categories_object && ! is_wp_error( $categories_object ) ) {
					$max_length       = count( $categories_object ) - 1;
					$categories_links = array_reduce(
						$categories_object,
						function ( $previous_value, $category ) use ( $categories_object, $max_length ) {
							return $previous_value . sprintf(
								'<a href="%1$s" title="%2$s">%2$s</a>%3$s',
								esc_url( get_term_link( $category ) ),
								esc_attr( $category->name ),
								$categories_object[ $max_length ] === $category ? '' : ', '
							);
						},
						''
					);
					$categories       = $elements->render(
						[
							'attrName'          => 'meta',
							'tagName'           => 'p',
							'skipAttrChildren'  => true,
							'childrenSanitizer' => 'et_core_esc_previously',
							'children'          => $categories_links,
						]
					);
				}

				/**
				 * Renders portfolio post HTML.
				 */
				$posts[] = sprintf(
					'<div id="post-%1$s" class="%2$s">
						%3$s
						%4$s
						%5$s
					</div>
					',
					esc_attr( $post_id ),
					esc_attr( implode( ' ', $post_classes ) ),
					$thumbnail,
					$is_title_visible ? $heading : null,
					$is_categories_visible ? $categories : null
				);
			}
		}

		$rendered_post = implode( '', $posts );

		/**
		 * Composes portfolio pagination with support for both WP Default pagination and WP PageNavi Plugin.
		 *
		 * Uses get_pagenum_link() for URLs (same as Divi 4 Portfolio) so "Older" / "Next" links show
		 * correctly on every page; get_next_posts_link / get_previous_posts_link can omit the previous
		 * link when is_single() is true or when global $paged does not match the portfolio page.
		 */
		$next_page_url     = self::get_portfolio_next_page_url( $portfolio_paged, (int) $query->max_num_pages );
		$previous_page_url = self::get_portfolio_previous_page_url( $portfolio_paged );

		$older_entries_label = esc_html__( '&laquo; Older Entries', 'et_builder_5' );
		$next_entries_label  = esc_html__( 'Next Entries &raquo;', 'et_builder_5' );

		$next_posts_link     = null;
		$previous_posts_link = null;

		if ( $next_page_url ) {
			$next_posts_link = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $next_page_url ),
				$older_entries_label
			);
		}

		if ( $previous_page_url ) {
			$previous_posts_link = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $previous_page_url ),
				$next_entries_label
			);
		}

		$next_posts_link_html = $next_posts_link ? sprintf(
			'<div class="alignleft">
				%1$s
			</div>',
			$next_posts_link
		) : null;

		$prev_posts_link_html = $previous_posts_link ? sprintf(
			'<div class="alignright">
				%1$s
			</div>',
			$previous_posts_link
		) : null;

		$default_pagination = $elements->render(
			[
				'attrName'          => 'pagination',
				'tagName'           => 'div',
				'skipAttrChildren'  => true,
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $next_posts_link_html . $prev_posts_link_html,
			]
		);

		$pagination = $is_wp_pagenavi_plugin_active ? $elements->render(
			[
				'attrName'          => 'pagination',
				'tagName'           => 'div',
				'skipAttrChildren'  => true,
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => wp_pagenavi(
					[
						'query' => $query,
						'echo'  => false,
					]
				),
			]
		) : $default_pagination;

		wp_reset_postdata();

		$no_posts_output = '';

		ob_start();

		get_template_part( 'includes/no-results', 'index' );

		if ( ob_get_length() > 0 ) {
			$no_posts_output = ob_get_clean();
		}

		/**
		 * Renders Portfolio final HTML output.
		 */
		// Portfolio Grid Layout for wrapper classes.
		$portfolio_grid_layout   = $attrs['portfolioGrid']['decoration']['layout']['desktop']['value']['display'] ?? 'grid';
		$is_flex_layout          = 'flex' === $portfolio_grid_layout;
		$is_grid_layout          = 'grid' === $portfolio_grid_layout;
		$portfolio_posts_classes = [ 'et_pb_posts' ];

		if ( $is_flex_layout ) {
			$portfolio_posts_classes[] = 'et_flex_module';
		} elseif ( $is_grid_layout ) {
			$portfolio_posts_classes[] = 'et_grid_module';
		} else {
			// Block layout or any other layout type.
			$portfolio_posts_classes[] = 'et_block_module';
		}

		// Render et_pb_posts wrapper with posts and child modules.
		$posts_wrapper = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => implode( ' ', $portfolio_posts_classes ),
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => [
					$rendered_post,
					$content,
				],
			]
		);

		// Render portfolioGrid element with et_pb_ajax_pagination_container.
		$portfolio_grid_content = $elements->render(
			[
				'attrName'          => 'portfolioGrid',
				'tagName'           => 'div',
				'attributes'        => [
					'class' => 'et_pb_ajax_pagination_container',
				],
				'skipAttrChildren'  => true,
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => [
					$posts_wrapper,
					$is_pagination_visible && ! is_search() ? $pagination : '',
				],
			]
		);

		$children = $rendered_post ? $portfolio_grid_content : $no_posts_output;

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		return Module::render(
			[
				// FE only.
				'orderIndex'               => $block->parsed_block['orderIndex'],
				'storeInstance'            => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'attrs'                    => $attrs,
				'elements'                 => $elements,
				'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
				'id'                       => $block->parsed_block['id'],
				'name'                     => $block->block_type->name,
				'parentAttrs'              => $parent->attrs ?? [],
				'parentId'                 => $parent->id ?? '',
				'classnamesFunction'       => [ self::class, 'module_classnames' ],
				'scriptDataComponent'      => [ self::class, 'module_script_data' ],
				'stylesComponent'          => [ self::class, 'module_styles' ],
				'moduleCategory'           => $block->block_type->category,
				'parentName'               => $parent->blockName ?? '', // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block
				'childrenIds'              => $children_ids,
				'children'                 => $elements->style_components(
					[
						'attrName' => 'module',
					]
				) . $children,
			]
		);
	}

	/**
	 * Retrieve the portfolio thumbnail image ackages/ModuleLibrary/Comments/CommentsModule.phpas HTML img.
	 *
	 * Returns the HTML image tag for the portfolio thumbnail based on the specified post thumbnail ID and layout.
	 *
	 * @since Unknown
	 *
	 * @param int            $post_thumbnail_id The ID of the post thumbnail.
	 * @param string         $layout            Optional. The layout to display the thumbnail in. One of `grid`, or `fullwidth`.
	 *                                          Default `fullwidth`.
	 * @param ModuleElements $elements          Module elements object.
	 * @param array          $attrs             Optional. The attributes of the module. Default `[]`.
	 *
	 * @return string The HTML image (img tag) tag for the portfolio thumbnail.
	 *
	 * @example:
	 * ```php
	 * $thumbnail = PortfolioModule::get_portfolio_thumbnail( $post_thumbnail_id, 'grid', $elements, $attrs );
	 * echo $thumbnail;
	 * ```
	 */
	public static function get_portfolio_thumbnail( int $post_thumbnail_id, string $layout, ModuleElements $elements, array $attrs = [] ): string {
		$alt_text   = get_post_meta( $post_thumbnail_id, '_wp_attachment_image_alt', true );
		$image_attr = ImageWrapperAnimation::render_attr_without_animation( $attrs['image'] ?? [] );

		// Select optimal image size based on layout.
		$selected_image_size = ImageUtils::select_optimal_image_size( $attrs, $layout );

		$thumbnail_data = wp_get_attachment_image_src( $post_thumbnail_id, $selected_image_size );

		$selected_thumbnail = [
			'src'     => is_array( $thumbnail_data ) ? $thumbnail_data[0] : '',
			'width'   => is_array( $thumbnail_data ) ? (int) $thumbnail_data[1] : 0,
			'height'  => is_array( $thumbnail_data ) ? (int) $thumbnail_data[2] : 0,
			'altText' => (string) $alt_text,
		];

		$image = $elements->render(
			[
				'attrName'    => 'image',
				'elementAttr' => $image_attr,
				'tagName'     => 'img',
				'attributes'  => [
					'src'    => esc_url( $selected_thumbnail['src'] ),
					'width'  => esc_attr( $selected_thumbnail['width'] ),
					'height' => esc_attr( $selected_thumbnail['height'] ),
					'alt'    => esc_attr( $alt_text ),
					'class'  => 'et_pb_post_main_image',
				],
			]
		);

		$image = et_image_add_srcset_and_sizes( $image, false );

		return $image;
	}

	/**
	 * REST API callback function that handles retrieving posts and metadata.
	 *
	 * Retrieves posts based on the provided parameters, including pagination, categories, and image sizes.
	 * This function makes use of `et_pb_portfolio_image_width`, `et_pb_portfolio_image_height`,
	 * `divi_module_library_portfolio_image_width`, and `divi_module_library_portfolio_image_height`
	 *  filters to retrieve the portfolio image width and height.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *                                 {@link https://developer.wordpress.org/reference/classes/wp_rest_request/ wp_rest_request}.
	 *
	 * @return WP_REST_Response The REST response object containing the posts and metadata in JSON format.
	 *
	 * @example:
	 * ```php
	 * // Retrieve all posts with a limit of 10 per page
	 * $request = new \WP_REST_Request();
	 * $request->set_param( 'postsPerPage', 10 );
	 * $request->set_param( 'paged', 1 );
	 * $response = PortfolioModule::rest_api_callback( $request );
	 * ```
	 *
	 * @example:
	 * ```php
	 * // Retrieve posts from a specific category with custom image sizes
	 * $request = new \WP_REST_Request();
	 * $request->set_param( 'categories', [1, 2] );
	 * $request->set_param( 'fullwidth', 'on' );
	 * $response = PortfolioModule::rest_api_callback( $request );
	 * ```
	 */
	public static function rest_api_callback( WP_REST_Request $request ): WP_REST_Response {

		$posts = [];

		$args = [
			'posts_per_page' => $request->get_param( 'postsPerPage' ),
			'paged'          => $request->get_param( 'paged' ),
			'categories'     => $request->get_param( 'categories' ),
			'fullwidth'      => $request->get_param( 'fullwidth' ),
		];

		$query_args = [
			'posts_per_page' => $args['posts_per_page'],
			'paged'          => $args['paged'],
			'post_type'      => 'project',
			'post_status'    => [ 'publish', 'private' ],
			'perm'           => 'readable',
		];

		$selected_term_ids = $args['categories'];

		// Apply category filtering using the consolidated utility method.
		$query_args = ModuleUtils::add_category_query_args( $query_args, $selected_term_ids, 'project' );

		$query = new \WP_Query( $query_args );

		// Portfolio image width.
		$width = 'on' === $args['fullwidth'] ? 1080 : 400;

		/**
		 * This filter is documented in /builder-5/server/Packages/ModuleLibrary/Portfolio/PortfolioController.php
		 *
		 * @ignore
		 */
		$width = apply_filters(
			'et_pb_portfolio_image_width',
			$width
		);

		// Type cast here for proper doc generation.
		$width = (int) $width;

		/**
		 * This filter is documented in /builder-5/server/Packages/ModuleLibrary/Portfolio/PortfolioController.php
		 *
		 * @ignore
		 */
		$width = apply_filters( 'divi_module_library_portfolio_image_width', $width );

		// Type cast here for proper doc generation.
		$width = (int) $width;

		// Portfolio image height.
		$height = 'on' === $args['fullwidth'] ? 9999 : 284;

		/**
		 * This filter is documented in /builder-5/server/Packages/ModuleLibrary/Portfolio/PortfolioController.php
		 *
		 * @ignore
		 */
		$height = apply_filters(
			'et_pb_portfolio_image_height',
			$height
		);

		// Type cast here for proper doc generation.
		$height = (int) $height;

		/**
		 * This filter is documented in /builder-5/server/Packages/ModuleLibrary/Portfolio/PortfolioController.php
		 *
		 * @ignore
		 */
		$height = apply_filters( 'divi_module_library_portfolio_image_height', $height );

		// Type cast here for proper doc generation.
		$height = (int) $height;

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();

				$post_id            = get_the_ID();
				$categories         = [];
				$categories_object  = get_the_terms( $post_id, 'project_category' );
				$has_post_thumbnail = has_post_thumbnail( $post_id );

				if ( ! empty( $categories_object ) ) {
					foreach ( $categories_object as $category ) {
						$categories[] = [
							'id'        => (int) $category->term_id,
							'label'     => $category->name,
							'permalink' => get_term_link( $category ),
						];
					}
				}

				if ( $has_post_thumbnail ) {
					$alt_text            = get_post_meta( get_post_thumbnail_id(), '_wp_attachment_image_alt', true );
					$thumbnail_grid      = wp_get_attachment_image_src( get_post_thumbnail_id(), 'et-pb-portfolio-image' );
					$thumbnail_fullwidth = wp_get_attachment_image_src( get_post_thumbnail_id(), 'et-pb-portfolio-image-single' );
					$thumbnails          = [
						'grid'      => [
							'src'     => $thumbnail_grid[0],
							'width'   => (int) $thumbnail_grid[1],
							'height'  => (int) $thumbnail_grid[2],
							'altText' => $alt_text,
						],
						'fullwidth' => [
							'src'     => $thumbnail_fullwidth[0],
							'width'   => (int) $thumbnail_fullwidth[1],
							'height'  => (int) $thumbnail_fullwidth[2],
							'altText' => $alt_text,
						],
					];
				}

				$new_post                 = [];
				$new_post['id']           = $post_id;
				$new_post['title']        = get_the_title( $post_id );
				$new_post['permalink']    = get_permalink( $post_id );
				$new_post['thumbnails']   = $has_post_thumbnail ? $thumbnails : null;
				$new_post['categories']   = $categories;
				$new_post['classNames']   = get_post_class( '', $post_id );
				$new_post['classNames'][] = 'et_pb_portfolio_item';
				$posts[]                  = $new_post;
			}
		}

		$metadata = [];

		$metadata['maxNumPages'] = $query->max_num_pages;

		$metadata['nextPageButtonLabel'] = esc_html__( '&laquo; Older Entries', 'et_builder_5' );

		$metadata['prevPageButtonLabel'] = esc_html__( 'Next Entries &raquo;', 'et_builder_5' );

		// Adds WP-PageNavi plugin support.
		$metadata['wpPagenavi'] = function_exists( 'wp_pagenavi' ) ? \wp_pagenavi(
			[
				'query' => $query,
				'echo'  => false,
			]
		) : null;

		wp_reset_postdata();

		$response = [
			'posts'    => $posts,
			'metadata' => $metadata,
		];

		return new WP_REST_Response( $response, 200, [ 'content-type' => 'application/json' ] );
	}

	/**
	 * Load the portfolio module.
	 *
	 * This function loads the portfolio module by registering the module
	 * via WordPress `init` action hook, specifying the render callback.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		// phpcs:ignore PHPCompatibility.FunctionUse.NewFunctionParameters.dirname_levelsFound -- We have PHP 7 support now, This can be deleted once PHPCS config is updated.
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/portfolio/';

		add_filter( 'divi_conversion_presets_attrs_map', [ PortfolioPresetAttrsMap::class, 'get_map' ], 10, 2 );

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
	 * Resolves the current pagination index for a secondary portfolio query on the frontend.
	 *
	 * WordPress stores pagination for a static front page in the `page` query var; other views use `paged`.
	 *
	 * @since ??
	 *
	 * @return int Current page number, at least 1.
	 */
	public static function get_pagination_page_for_query(): int {
		$raw = is_front_page()
			? get_query_var( 'page' )
			: get_query_var( 'paged' );

		return max( 1, absint( $raw ) );
	}

	/**
	 * Get the URL for the next portfolio page (older entries).
	 *
	 * Mirrors Divi 4 {@see \ET_Builder_Module_Portfolio::get_next_link()} using get_pagenum_link().
	 *
	 * @since ??
	 *
	 * @param int $current_page Current page number (1-based).
	 * @param int $max_pages    Total pages from the portfolio WP_Query.
	 *
	 * @return string|null URL string or null when there is no next page.
	 */
	public static function get_portfolio_next_page_url( int $current_page, int $max_pages ): ?string {
		if ( $current_page < 1 ) {
			$current_page = 1;
		}

		$next_page = $current_page + 1;

		if ( $next_page <= $max_pages ) {
			return get_pagenum_link( $next_page );
		}

		return null;
	}

	/**
	 * Get the URL for the previous portfolio page (newer entries).
	 *
	 * Mirrors Divi 4 {@see \ET_Builder_Module_Portfolio::get_previous_link()} using get_pagenum_link().
	 *
	 * @since ??
	 *
	 * @param int $current_page Current page number (1-based).
	 *
	 * @return string|null URL string or null when already on page 1.
	 */
	public static function get_portfolio_previous_page_url( int $current_page ): ?string {
		$previous_page = $current_page - 1;

		if ( $previous_page >= 1 ) {
			return get_pagenum_link( $previous_page );
		}

		return null;
	}

	/**
	 * Get current post ID for exclusion from portfolio query.
	 *
	 * Returns the current post ID that should be excluded from portfolio results
	 * when using "Current Category" on project pages or Theme Builder context.
	 *
	 * @since ??
	 *
	 * @return int The post ID to exclude, or 0 if no exclusion needed.
	 */
	private static function _get_current_post_id_for_exclusion(): int {
		// Check if we're in Theme Builder context.
		$is_theme_builder = class_exists( '\ET_Theme_Builder_Layout' ) && \ET_Theme_Builder_Layout::is_theme_builder_layout();

		if ( $is_theme_builder ) {
			// In Theme Builder, get the main post ID (the actual post being displayed).
			// Only exclude on singular project pages, not on archive pages.
			$main_post_id = class_exists( '\ET_Post_Stack' ) ? \ET_Post_Stack::get_main_post_id() : 0;
			if ( $main_post_id > 0 && is_singular( 'project' ) ) {
				return $main_post_id;
			}
		}

		// Standard context - use global $post on singular project pages.
		if ( is_singular( 'project' ) ) {
			global $post;
			if ( $post && $post->ID > 0 ) {
				return $post->ID;
			}
		}

		// Fallback to get_the_ID() but only return it if we're on a singular project page.
		// On archive/category pages, get_the_ID() returns random loop post IDs which would cause unintended exclusions.
		$post_id = get_the_ID();
		return $post_id > 0 && is_singular( 'project' ) ? $post_id : 0;
	}
}
