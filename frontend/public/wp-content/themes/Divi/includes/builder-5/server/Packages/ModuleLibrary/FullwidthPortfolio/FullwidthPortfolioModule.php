<?php
/**
 * ModuleLibrary: Fullwidth Portfolio Module class.
 *
 * @package Builder\Packages\ModuleLibrary\FullwidthPortfolioModule
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\FullwidthPortfolio;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\IconLibrary\IconFont\Utils;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Layout\Components\StyleCommon\CommonStyle;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\BoxShadow\BoxShadowClassnames;
use ET\Builder\Packages\Module\Options\BoxShadow\BoxShadowUtils;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\Module\Options\Text\TextStyle;
use ET\Builder\Packages\Module\Options\Layout\LayoutStyle;
use ET\Builder\Packages\ModuleLibrary\Common\ImageWrapperAnimation;
use ET\Builder\Packages\ModuleLibrary\FullwidthPortfolio\FullwidthPortfolioPresetAttrsMap;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use WP_Block;
use ET\Builder\Packages\GlobalData\GlobalPresetItemGroup;
use ET\Builder\Packages\GlobalData\GlobalData;

/**
 * FullwidthPortfolioModule class.
 *
 * This class contains functionality used for Fullwidth Portfolio Module such as Front-End rendering, REST API Endpoints etc.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 */
class FullwidthPortfolioModule implements DependencyInterface {

	/**
	 * Set CSS class names to the module.
	 *
	 * This function is equivalent of JS function moduleClassnames located in
	 * visual-builder/packages/module-library/src/components/fullwidth-portfolio/module-classnames.ts.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $id                  Module unique ID.
	 *     @type string $name                Module name with namespace.
	 *     @type array  $attrs               Module attributes.
	 *     @type array  $childrenIds         Module children IDs.
	 *     @type bool   $hasModule           Flag that indicates if module has child modules.
	 *     @type bool   $isFirst             Flag that indicates if module is first in the row.
	 *     @type bool   $isLast              Flag that indicates if module is last in the row.
	 *     @type object $classnamesInstance  Instance of Instance of ET\Builder\Packages\Module\Layout\Components\Classnames class.
	 *
	 *     // FE only.
	 *     @type int|null $storeInstance The ID of instance where this block stored in BlockParserStore.
	 *     @type int      $orderIndex    The order index of the element.
	 * }
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		$layout             = $attrs['portfolio']['advanced']['layout']['desktop']['value'] ?? 'on';
		$is_layout_carousel = 'on' === $layout;

		// Text Options.
		$classnames_instance->add( TextClassnames::text_options_classnames( $attrs['module']['advanced']['text'] ?? [] ), true );

		// Module components.
		$classnames_instance->add( 'et_pb_fullwidth_portfolio_carousel', $is_layout_carousel );
		$classnames_instance->add( 'et_pb_fullwidth_portfolio_grid', ! $is_layout_carousel );
		$classnames_instance->add( 'clearfix', ! $is_layout_carousel );

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
	 * Set Fullwidth Portfolio module script data.
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
	 *         'id'            => 'my-module',
	 *         'name'          => 'My Module',
	 *         'selector'      => '.my-module',
	 *         'attrs'         => array(
	 *             'portfolio' => array(
	 *                 'advanced' => array(
	 *                     'showTitle' => false,
	 *                     'showDate'  => true,
	 *                 )
	 *             )
	 *         ),
	 *         'elements'      => $elements,
	 *         'storeInstance' => 123,
	 *     );
	 *
	 *     FullwidthPortfolioModule::module_script_data( $args );
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

		// Responsive Content for portfolio title and its post meta.
		MultiViewScriptData::set(
			[
				'id'            => $id,
				'name'          => $name,
				'storeInstance' => $store_instance,
				'hoverSelector' => $selector,
				'setVisibility' => [
					[
						'selector'      => $selector . ' .post-meta',
						'data'          => $attrs['portfolio']['advanced']['showDate'] ?? [],
						'valueResolver' => function ( $value ) {
							return 'off' !== $value ? 'visible' : 'hidden';
						},
					],
					[
						'selector'      => $selector . ' .et_pb_module_header',
						'data'          => $attrs['portfolio']['advanced']['showTitle'] ?? [],
						'valueResolver' => function ( $value ) {
							return 'off' !== $value ? 'visible' : 'hidden';
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
	 * visual-builder/packages/module-library/src/components/fullwidth-portfolio/custom-css.ts.
	 *
	 * A minor difference with the JS const cssFields, this function did not have `label` property on each array item.
	 *
	 * @since ??
	 *
	 * @return array The array of custom CSS fields.
	 */
	public static function custom_css() {
		return \WP_Block_Type_Registry::get_instance()->get_registered( 'divi/fullwidth-portfolio' )->customCssFields;
	}


	/**
	 * Fullwidth Portfolio Grid Item's CSS declaration for horizontal gap.
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
	public static function fullwidth_portfolio_grid_item_style_declaration( array $params ): string {
		$declarations = new StyleDeclarations( $params );
		$attr         = $params['attr'] ?? [];

		return $declarations->value();
	}

	/**
	 * Fullwidth Portfolio image aspect-ratio companion CSS declarations.
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
	public static function fullwidth_portfolio_image_aspect_ratio_style_declaration( array $params ): string {
		$declarations = new StyleDeclarations( $params );

		$declarations->add( 'height', 'auto' );
		$declarations->add( 'min-height', 'auto' );

		return $declarations->value();
	}

	/**
	 * Set CSS styles to the module.
	 *
	 * This function is equivalent of JS function ModuleStyles located in
	 * visual-builder/packages/module-library/src/components/fullwidth-portfolio/module-styles.tsx.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string         $id                       Module unique ID.
	 *     @type string         $name                     Module name with namespace.
	 *     @type array          $attrs                    Module attributes.
	 *     @type array          $parentAttrs              Parent module attributes.
	 *     @type array          $siblingAttrs             Sibling module attributes.
	 *     @type array          $defaultPrintedStyleAttrs Default printed style attributes.
	 *     @type string         $orderClass               Module CSS selector.
	 *     @type string         $parentOrderClass         Parent module CSS selector.
	 *     @type string         $wrapperOrderClass        Wrapper module CSS selector.
	 *     @type array          $settings                 Custom settings.
	 *     @type ModuleElements $elements                 ModuleElements instance.
	 *
	 *     // VB only.
	 *     @type string $state Attributes state.
	 *     @type string $mode  Style mode.
	 *
	 *     // FE only.
	 *     @type int|null $storeInstance The ID of instance where this block stored in BlockParserStore.
	 *     @type int      $orderIndex    The order index of the element.
	 * }
	 *
	 * @return void
	 *
	 * @example:
	 * ```php
	 *     FullwidthPortfolioModule::module_styles([
	 *         'id'        => 'module-1',
	 *         'name'      => 'Fullwidth Portfolio Module',
	 *         'attrs'     => [],
	 *         'elements'  => $elementsInstance,
	 *         'settings'  => $moduleSettings,
	 *         'orderClass'=> '.fullwidth-portfolio-module'
	 *     ]);
	 * ```
	 */
	public static function module_styles( array $args ): void {
		$attrs                       = $args['attrs'] ?? [];
		$elements                    = $args['elements'] ?? [];
		$settings                    = $args['settings'] ?? [];
		$default_printed_style_attrs = $args['defaultPrintedStyleAttrs'] ?? [];
		$is_inside_sticky_module     = $elements->get_is_inside_sticky_module();
		$sticky_parent_order_class   = $elements->get_sticky_parent_order_class();
		$order_class                 = $args['orderClass'] ?? '';

		// Check if flex layout is enabled for portfolioGrid.
		$portfolio_grid_layout   = $attrs['portfolioGrid']['decoration']['layout']['desktop']['value']['display'] ?? 'flex';
		$is_flex_layout          = 'flex' === $portfolio_grid_layout;
		$image_sizing_attr       = $attrs['image']['decoration']['sizing'] ?? [];
		$image_aspect_ratio_attr = [];
		$has_image_height        = ModuleUtils::has_value(
			$image_sizing_attr,
			[
				'subName' => 'height',
			]
		);
		$has_image_min_height    = ModuleUtils::has_value(
			$image_sizing_attr,
			[
				'subName' => 'minHeight',
			]
		);

		foreach ( $image_sizing_attr as $breakpoint => $breakpoint_values ) {
			if ( ! is_array( $breakpoint_values ) ) {
				continue;
			}

			foreach ( $breakpoint_values as $state => $state_value ) {
				if ( ! is_array( $state_value ) ) {
					continue;
				}

				if ( ! array_key_exists( 'aspectRatio', $state_value ) ) {
					continue;
				}

				$aspect_ratio_value = $state_value['aspectRatio'];

				if ( is_scalar( $aspect_ratio_value ) && '' === strval( $aspect_ratio_value ) ) {
					continue;
				}

				if ( ! is_scalar( $aspect_ratio_value ) && empty( $aspect_ratio_value ) ) {
					continue;
				}

				$image_aspect_ratio_attr[ $breakpoint ][ $state ] = [
					'aspectRatio' => $aspect_ratio_value,
				];
			}
		}

		$should_render_image_aspect_ratio_auto_height = ! empty( $image_aspect_ratio_attr ) && ! $has_image_height && ! $has_image_min_height;

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
					// Portfolio Grid.
					LayoutStyle::style(
						[
							'selector'               => $args['orderClass'] . ' .et_pb_portfolio_items',
							'attr'                   => $attrs['portfolioGrid']['decoration']['layout'] ?? [],
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					),
					// Portfolio Grid Items - Flex Column Gap Support.
					$elements->style(
						[
							'attrName'   => 'portfolioGrid',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $args['orderClass'] . ' .et_pb_portfolio_items > .et_flex_column',
											'attr'     => $attrs['portfolioGrid']['decoration']['layout'] ?? [],
											'declarationFunction' => [ self::class, 'fullwidth_portfolio_grid_item_style_declaration' ],
										],
									],
								],
							],
						]
					),
					// Text Style.
					TextStyle::style(
						[
							'selector'               => $args['orderClass'],
							'attr'                   => $attrs['module']['advanced']['text'] ?? [],
							'propertySelectors'      => [
								'text' => [
									'desktop' => [
										'value' => [
											'text-align' => implode(
												', ',
												[
													$args['orderClass'] . ' .et_pb_module_header',
													$args['orderClass'] . ' h2',
													$args['orderClass'] . ' .et_pb_portfolio_image h3',
													$args['orderClass'] . ' .et_pb_portfolio_image p',
													$args['orderClass'] . ' .et_pb_portfolio_title',
													$args['orderClass'] . ' .et_pb_portfolio_image .et_pb_module_header',
												]
											),
										],
									],
								],
							],
							'important'              => [
								'text' => [
									'desktop' => [
										'value' => [
											'text-align' => true,
										],
									],
								],
							],
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					),
					// Title.
					$elements->style(
						[
							'attrName' => 'title',
						]
					),
					// Meta.
					$elements->style(
						[
							'attrName' => 'meta',
						]
					),
					// Portfolio.
					$elements->style(
						[
							'attrName' => 'portfolio',
						]
					),
					// Overlay.
					$elements->style(
						[
							'attrName' => 'overlay',
						]
					),
					// Image.
					$elements->style(
						[
							'attrName'   => 'image',
							'styleProps' => [
								'fit'    => [
									'selector' => "{$order_class} .et_pb_portfolio_image img",
								],
								'sizing' => [
									'propertySelectors' => [
										'desktop' => [
											'value' => [
												'aspect-ratio' => "{$order_class} .et_pb_portfolio_image img",
											],
										],
									],
								],
							],
						]
					),
					// Custom Styles.
					CommonStyle::style(
						[
							'selector'               => $args['orderClass'] . ' .et_pb_portfolio_image',
							'attr'                   => $attrs['image']['decoration']['border'] ?? [],
							'declarationFunction'    => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					),
					$should_render_image_aspect_ratio_auto_height ? CommonStyle::style(
						[
							'selector'               => "{$order_class} .et_pb_portfolio_image img",
							'attr'                   => $image_aspect_ratio_attr,
							'important'              => true,
							'declarationFunction'    => [ self::class, 'fullwidth_portfolio_image_aspect_ratio_style_declaration' ],
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					) : null,
					// Custom CSS.
					CssStyle::style(
						[
							'selector'   => $args['orderClass'],
							'attr'       => $attrs['css'] ?? [],
							'cssFields'  => self::custom_css(),
							'orderClass' => $order_class,
						]
					),
				],
			]
		);
	}

	/**
	 * Render callback for the Fullwidth Portfolio module.
	 *
	 * Generates the HTML output for the Fullwidth portfolio module.
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
	 *     'module' => [
	 *         'advanced' => [
	 *             'showDate' => [
	 *                 'desktop' => [
	 *                     'value' => 'on',
	 *                 ],
	 *             ],
	 *         ],
	 *     ],
	 *     // Other attributes...
	 * ];
	 * $content = 'Block content';
	 * $result = FullwidthPortfolio::render_callback( $attrs, $content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements, array $default_printed_style_attrs ): string {
		$children_ids            = ChildrenUtils::extract_children_ids( $block );
		$posts_per_page          = $attrs['portfolio']['innerContent']['desktop']['value']['postsNumber'] ?? '-1';
		$selected_term           = $attrs['portfolio']['innerContent']['desktop']['value']['includedCategories'] ?? [];
		$selected_term_ids       = is_string( $selected_term ) ? explode( ',', $selected_term ) : $selected_term;
		$post_type               = $attrs['portfolio']['innerContent']['desktop']['value']['type'] ?? 'post';
		$heading_level           = $attrs['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] ?? 'h2';
		$portfolio_heading_level = $attrs['portfolio']['decoration']['font']['font']['desktop']['value']['headingLevel'] ?? 'h3';
		$auto_rotate             = $attrs['module']['advanced']['autoRotate']['desktop']['value'] ?? 'off';
		$auto_rotate_speed       = $attrs['module']['advanced']['autoRotateSpeed']['desktop']['value'] ?? '';
		$module_order_index      = $block->parsed_block['orderIndex'];

		// Portfolio Grid attributes for flexbox support.
		$portfolio_layout         = $attrs['portfolio']['advanced']['layout']['desktop']['value'] ?? 'on';
		$portfolio_grid_flex_type = $attrs['portfolioGrid']['advanced']['flexType']['desktop']['value'] ?? '6_24';
		$module_layout_display    = $attrs['module']['decoration']['layout']['desktop']['value']['display'] ?? 'flex';

		// Determine layout modes.
		$is_carousel_mode   = 'on' === $portfolio_layout;
		$is_grid_mode       = 'off' === $portfolio_layout;
		$is_flex_layout     = $is_grid_mode && 'flex' === $module_layout_display;
		$is_css_grid_layout = $is_grid_mode && 'grid' === $module_layout_display;
		$is_block_layout    = $is_grid_mode && 'block' === $module_layout_display;

		$hover_icon        = $attrs['overlay']['decoration']['icon']['desktop']['value'] ?? '';
		$hover_icon_tablet = $attrs['overlay']['decoration']['icon']['tablet']['value'] ?? '';
		$hover_icon_phone  = $attrs['overlay']['decoration']['icon']['phone']['value'] ?? '';
		$hover_icon_sticky = $attrs['overlay']['decoration']['icon']['desktop']['sticky'] ?? '';

		$icon        = $hover_icon ? Utils::process_font_icon( $hover_icon ) : '';
		$icon_tablet = $hover_icon_tablet ? Utils::process_font_icon( $hover_icon_tablet ) : '';
		$icon_phone  = $hover_icon_phone ? Utils::process_font_icon( $hover_icon_phone ) : '';
		$icon_sticky = $hover_icon_sticky ? Utils::process_font_icon( $hover_icon_sticky ) : '';

		// Check if Fullwidth Portfolio Item Title Visible.
		$is_title_visible = ModuleUtils::has_value(
			$attrs['portfolio']['advanced']['showTitle'] ?? [],
			[
				'valueResolver' => function ( $value ) {
					return 'off' !== $value;
				},
			]
		);
		// Check if Fullwidth Portfolio Item Date visible.
		$is_date_visible = ModuleUtils::has_value(
			$attrs['portfolio']['advanced']['showDate'] ?? [],
			[
				'valueResolver' => function ( $value ) {
					return 'off' !== $value;
				},
			]
		);

		// Renders portfolio overlay HTML based on selected hover icons.
		$overlay_html = HTMLUtility::render(
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
					'data-icon'        => $icon,
					'data-icon-tablet' => $icon_tablet,
					'data-icon-phone'  => $icon_phone,
					'data-icon-sticky' => $icon_sticky,
				],
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);

		// Get Portfolio Items based on params passed.
		$query_args      = [
			'posts_number'       => (int) $posts_per_page,
			'categories'         => $selected_term_ids,
			'post_type'          => $post_type,
			'is_carousel_mode'   => $is_carousel_mode,
			'is_flex_layout'     => $is_flex_layout,
			'is_css_grid_layout' => $is_css_grid_layout,
			'attrs'              => $attrs,
		];
		$portfolio_posts = self::get_portfolio_items( $query_args );

		$portfolio_items_html = '';
		$items_count          = 0;
		foreach ( $portfolio_posts as $key => $portfolio ) {

			// Fetch Portfolio Thumbnail Image.
			$thumb_image = '';
			if ( ! empty( $portfolio['thumbnail'] ) ) {
				$full_src     = get_the_post_thumbnail_url( $portfolio['id'], 'full' );
				$thumb_id     = get_post_thumbnail_id( $portfolio['id'] );
				$thumb_src    = $portfolio['thumbnail']['src'] ?? '';
				$thumb_width  = $portfolio['thumbnail']['width'] ?? '';
				$thumb_height = $portfolio['thumbnail']['height'] ?? '';
				$thumb_image  = HTMLUtility::render(
					[
						'tag'        => 'img',
						'tagEscaped' => true,
						'attributes' => [
							'src'    => $thumb_src,
							'class'  => $thumb_id > 0 ? "wp-image-{$thumb_id}" : '',
							'alt'    => $portfolio['title'],
							'title'  => $portfolio['title'],
							'width'  => $thumb_width,
							'height' => $thumb_height,
							'srcset' => $full_src . ' 479w, ' . $thumb_src . ' 480w',
							'sizes'  => '(max-width:479px) 479px, 100vw',
						],
					]
				);
				// Generate Image srcset and sizes.
				$thumb_image = et_image_add_srcset_and_sizes( $thumb_image, false );
			}

			// Render Portfolio Item Title Html.
			$post_title_html = $is_title_visible
			? $elements->render(
				[
					'attrName'          => 'portfolioItemTitle',
					'tagName'           => $portfolio_heading_level,
					'skipAttrChildren'  => true,
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => $portfolio['title'],
				]
			)
			: null;

			// Render Portfolio Item Meta Html.
			$post_meta_html = $is_date_visible
			? $elements->render(
				[
					'attrName'          => 'meta',
					'tagName'           => 'p',
					'skipAttrChildren'  => true,
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => $portfolio['date'],
				]
			)
			: null;

			// Portfolio meta wrapper.
			$portfolio_meta_wrapper = HTMLUtility::render(
				[
					'tag'               => 'div',
					'attributes'        => [
						'class' => 'meta',
					],
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => HTMLUtility::render(
						[
							'tag'               => 'a',
							'attributes'        => [
								'href' => $portfolio['permalink'],
							],
							'childrenSanitizer' => 'et_core_esc_previously',
							'children'          => [
								$overlay_html,
								$post_title_html,
								$post_meta_html,
							],
						]
					),
				]
			);

			// Image has-box-shadow-overlay.
			// Note : In D4 If image box shadow has inner position then has-box-shadow-overlay class is getting added
			// into the image wrapper.
			$image_has_box_shadow_overlay_classname = BoxShadowClassnames::has_overlay( $attrs['image']['decoration']['boxShadow'] ?? [] );
			$image_wrapper_animation_classname      = ImageWrapperAnimation::wrapper_animation_classname( $attrs['image'] ?? [] );

			// Portfolio item wrapper.
			$portfolio_item_wrapper = HTMLUtility::render(
				[
					'tag'               => 'div',
					'attributes'        => [
						'class' => HTMLUtility::classnames(
							[
								'et_pb_portfolio_image' => true,
								"{$portfolio['orientation']}" => ! empty( $portfolio['orientation'] ),
								"{$image_has_box_shadow_overlay_classname}" => ! empty( $image_has_box_shadow_overlay_classname ),
								"{$image_wrapper_animation_classname}" => ! empty( $image_wrapper_animation_classname ),
							]
						),
					],
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => [
						$elements->style_components(
							[
								'attrName' => 'image',
							]
						),
						$thumb_image,
						$portfolio_meta_wrapper,
					],
				]
			);

			// Portfolio Item Index Class.
			$portfolio_item_index_class = sprintf( 'et_pb_fullwidth_portfolio_item_%1$s_%2$s', $module_order_index, $items_count );
			++$items_count;

			// Individual Portfolio Item Html.
			$portfolio_items_html .= HTMLUtility::render(
				[
					'tag'               => 'div',
					'attributes'        => [
						'class' => HTMLUtility::classnames(
							array_merge(
								[
									$portfolio_item_index_class => true,
								],
								$portfolio['classNames'] ?? []
							)
						),
						'id'    => $portfolio['id'] ?? '',
					],
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => $portfolio_item_wrapper,
				]
			);
		}

		// Set No Posts Output.
		$no_posts_output = '';
		ob_start();
		get_template_part( 'includes/no-results', 'index' );
		if ( ob_get_length() > 0 ) {
			$no_posts_output = ob_get_clean();
		}

		// Renders Portfolio final HTML output if portfolio found.
		$children = $portfolio_items_html ? $portfolio_items_html : $no_posts_output;

		// Portfolio Items Content Wrapper.
		$portfolio_items_classes = [
			'et_pb_portfolio_items' => true,
		];

		// Add layout-specific module classes when in grid mode.
		if ( $is_flex_layout ) {
			$portfolio_items_classes['et_flex_module'] = true;
		} elseif ( $is_css_grid_layout ) {
			$portfolio_items_classes['et_grid_module'] = true;
		}

		$portfolio_items_wrapper = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class'                  => HTMLUtility::classnames( $portfolio_items_classes ),
					'data-portfolio-columns' => '',
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $children,
			]
		);

		// Fullwidth Portfolio Heading.
		$fullwidth_portfolio_heading = $elements->render(
			[
				'attrName' => 'title',
				'tagName'  => $heading_level,
			]
		);

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		return Module::render(
			[
				// FE only.
				'orderIndex'               => $block->parsed_block['orderIndex'],
				'storeInstance'            => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'attrs'                    => $attrs,
				'htmlAttrs'                => [
					'data-auto-rotate'       => $auto_rotate,
					'data-auto-rotate-speed' => $auto_rotate_speed,
				],
				'elements'                 => $elements,
				'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
				'id'                       => $block->parsed_block['id'],
				'name'                     => $block->block_type->name,
				'parentAttrs'              => $parent->attrs ?? [],
				'parentId'                 => $parent->id ?? '',
				'parentName'               => $parent->blockName ?? '',
				'childrenIds'              => $children_ids,
				'classnamesFunction'       => [ self::class, 'module_classnames' ],
				'scriptDataComponent'      => [ self::class, 'module_script_data' ],
				'stylesComponent'          => [ self::class, 'module_styles' ],
				'moduleCategory'           => $block->block_type->category,
				'children'                 => $elements->style_components(
					[
						'attrName' => 'module',
					]
				) . $fullwidth_portfolio_heading . $portfolio_items_wrapper . $content,
			]
		);
	}

	/**
	 * Get Fullwidth Portfolio Items.
	 *
	 * @since ??
	 *
	 * @param array $args Fullwidth Portfolio Item request params.
	 *
	 * @return array The processed content.
	 */
	public static function get_portfolio_items( array $args ) {
		// Request Params.
		$posts_number       = $args['posts_number'] ?? '';
		$selected_term_ids  = $args['categories'] ?? [];
		$post_type          = $args['post_type'] ?? 'post';
		$is_carousel_mode   = $args['is_carousel_mode'] ?? false;
		$is_flex_layout     = $args['is_flex_layout'] ?? false;
		$is_css_grid_layout = $args['is_css_grid_layout'] ?? false;
		$attrs              = $args['attrs'] ?? [];

		$query_args = [
			'post_type'   => $post_type,
			'post_status' => [ 'publish', 'private' ],
			'perm'        => 'readable',
		];

		// If Posts number found in argument then set posts_per_page/nopaging arguments.
		if ( is_numeric( $posts_number ) && $posts_number > 0 ) {
			$query_args['posts_per_page'] = $posts_number;
		} else {
			$query_args['nopaging'] = true;
		}

		// Apply category filtering using the consolidated utility method.
		$query_args = ModuleUtils::add_category_query_args( $query_args, $selected_term_ids, $post_type );

		$query = new \WP_Query( $query_args );

		/**
		 * Filter the fullwidth portfolio image width.
		 *
		 * @since ??
		 * @deprecated 5.0.0 Use {@see 'divi_module_library_portfolio_image_width'} instead.
		 *
		 * @param int $width The portfolio image width.
		 */
		$width = apply_filters(
			'et_pb_portfolio_image_width',
			510
		);

		// Type cast here for proper doc generation.
		$width = (int) $width;

		/**
		 * Filter the fullwidth portfolio image width.
		 *
		 * @since ??
		 *
		 * @param int $width The portfolio image width.
		 */
		$width = apply_filters( 'divi_module_library_portfolio_image_width', $width );

		// Type cast here for proper doc generation.
		$width = (int) $width;

		/**
		 * Filter the fullwidth portfolio image height.
		 *
		 * @since ??
		 * @deprecated 5.0.0 Use {@see 'divi_module_library_portfolio_image_height'} instead.
		 *
		 * @param int $height The portfolio image height.
		 */
		$height = apply_filters(
			'et_pb_portfolio_image_height',
			382
		);

		// Type cast here for proper doc generation.
		$height = (int) $height;

		/**
		 * Filter the fullwidth portfolio image height.
		 *
		 * @since ??
		 *
		 * @param int $height The portfolio image height.
		 */
		$height = apply_filters( 'divi_module_library_portfolio_image_height', $height );

		// Type cast here for proper doc generation.
		$height = (int) $height;

		$posts = [];

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();

				$post_id   = get_the_ID();
				$thumbnail = wp_get_attachment_image_src( get_post_thumbnail_id(), [ $width, $height ] );

				// Initialize thumbnail data.
				$thumbnails = null;
				if ( has_post_thumbnail( $post_id ) && ! empty( $thumbnail ) ) {
					$alt_text   = get_post_meta( get_post_thumbnail_id(), '_wp_attachment_image_alt', true );
					$thumbnails = [
						'src'     => $thumbnail[0],
						'width'   => (int) $thumbnail[1],
						'height'  => (int) $thumbnail[2],
						'altText' => $alt_text,
					];
				}

				// Find orientation based on thumbnail dimensions.
				$orientation = false;
				if ( null !== $thumbnails && isset( $thumbnails['height'] ) && isset( $thumbnails['width'] ) ) {
					$orientation = ( $thumbnails['height'] > $thumbnails['width'] ) ? 'portrait' : 'landscape';
				}

				$new_post                = [];
				$new_post['id']          = $post_id;
				$new_post['title']       = get_the_title( $post_id );
				$new_post['permalink']   = get_permalink( $post_id );
				$new_post['date']        = get_the_date( '', $post_id );
				$new_post['thumbnail']   = $thumbnails;
				$new_post['orientation'] = $orientation;

				// Build post class names based on layout mode.
				$base_classes = [ 'et_pb_portfolio_item' ];

				// Add et_pb_grid_item only for grid modes (not carousel).
				if ( ! $is_carousel_mode ) {
					$base_classes[] = 'et_pb_grid_item';
				}

				// Add flexbox classes for flex layout only (not CSS Grid).
				if ( $is_flex_layout && ! $is_css_grid_layout ) {
					$base_classes[] = 'et_flex_column';

					$breakpoints_mapping = Breakpoint::get_css_class_suffixes();

					foreach ( $breakpoints_mapping as $breakpoint => $suffix ) {
						if ( ! Breakpoint::is_enabled_for_style( $breakpoint ) ) {
							continue;
						}

						$flex_type = $args['attrs']['portfolioGrid']['advanced']['flexType'][ $breakpoint ]['value'] ?? null;

						if ( $flex_type && 'none' !== $flex_type ) {
							$base_classes[] = "et_flex_column_{$flex_type}{$suffix}";
						}
					}
				}

				// Fix for WooCommerce ArrayAccess objects: ensure classNames is always a proper array.
				$post_classes           = get_post_class( implode( ' ', $base_classes ), $post_id );
				$new_post['classNames'] = is_array( $post_classes ) ? $post_classes : array_values( (array) $post_classes );
				// Ensure sequential numeric keys to prevent JSON object conversion.
				$new_post['classNames'] = array_values( array_filter( $new_post['classNames'] ) );
				$posts[]                = $new_post;
			}
		}

		wp_reset_postdata();

		return $posts;
	}

	/**
	 * Load the fullwidth portfolio module.
	 *
	 * This function loads the fullwidth portfolio module by registering the module
	 * via WordPress `init` action hook, specifying the render callback.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/fullwidth-portfolio/';

		add_filter( 'divi_conversion_presets_attrs_map', [ FullwidthPortfolioPresetAttrsMap::class, 'get_map' ], 10, 2 );

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
