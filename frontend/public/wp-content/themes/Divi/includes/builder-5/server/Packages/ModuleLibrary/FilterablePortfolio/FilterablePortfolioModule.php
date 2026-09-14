<?php
/**
 * ModuleLibrary: Filterable Portfolio Module class.
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\FilterablePortfolio;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
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
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\ModuleUtils\ImageUtils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use WP_Block_Type_Registry;
use WP_Block;
use ET\Builder\Framework\Breakpoint\Breakpoint;

/**
 * `FilterablePortfolioModule` is consisted of functions used for Filterable Portfolio Module such as Front-End rendering, REST API Endpoints etc.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 */
class FilterablePortfolioModule implements DependencyInterface {

	/**
	 * Check if layout display is fullwidth (flex or block).
	 *
	 * @since ??
	 *
	 * @param string $portfolio_grid_layout_display Layout display value ('flex', 'block', or 'grid').
	 *
	 * @return bool True if layout is fullwidth (flex or block), false otherwise (grid).
	 */
	private static function _is_layout_fullwidth( string $portfolio_grid_layout_display ): bool {
		return 'flex' === $portfolio_grid_layout_display || 'block' === $portfolio_grid_layout_display;
	}

	/**
	 * Get Fullwidth Portfolio Items.
	 *
	 * @since ??
	 *
	 * @param array  $args                         Fullwidth Portfolio Item request params.
	 * @param array  $attrs                        Module attributes for responsive image sizing.
	 * @param string $portfolio_grid_layout_display Layout display value ('flex', 'block', or 'grid').
	 *
	 * @return array The processed content.
	 */
	public static function get_portfolio_items( array $args, array $attrs = [], string $portfolio_grid_layout_display = 'grid' ) {
		$posts = [];

		$default_query_args = [
			'post_type'      => 'project',
			'post_status'    => [ 'publish', 'private' ],
			'perm'           => 'readable',
			'paged'          => 1,
			'posts_per_page' => - 1,
		];

		$term_ids = $args['categories'];

		$valid_term_ids = [];

		foreach ( $term_ids as $term_id ) {
			$term_id = (int) $term_id;
			$term    = term_exists( $term_id, 'project_category' );
			if ( ! empty( $term ) ) {
				$valid_term_ids[] = $term_id;
			}
		}

		if ( ! empty( $valid_term_ids ) ) {
			$args['tax_query'] = [
				[
					'taxonomy' => 'project_category',
					'field'    => 'id',
					'terms'    => $valid_term_ids,
					'operator' => 'IN',
				],
			];
		}

		$query_args = wp_parse_args( $args, $default_query_args );

		// Get portfolio query.
		$query = new \WP_Query( $query_args );

		// Portfolio image width.
		$width = self::_is_layout_fullwidth( $portfolio_grid_layout_display ) ? 1080 : 400;

		/**
		 * Filter the portfolio image width.
		 *
		 * @param int $width The portfolio image width.
		 *
		 * @deprecated 5.0.0 Use {@see 'divi_module_library_portfolio_image_width'} instead.
		 *
		 * @since ??
		 */
		$width = apply_filters(
			'et_pb_portfolio_image_width',
			$width
		);

		// Type cast here for proper doc generation.
		$width = (int) $width;

		/**
		 * Filter the portfolio image width.
		 *
		 * @param int $width The portfolio image width.
		 *
		 * @since ??
		 */
		$width = apply_filters( 'divi_module_library_portfolio_image_width', $width );

		// Type cast here for proper doc generation.
		$width = (int) $width;

		// Portfolio image height.
		$height = self::_is_layout_fullwidth( $portfolio_grid_layout_display ) ? 9999 : 284;

		/**
		 * Filter the portfolio image height.
		 *
		 * @param int $height The portfolio image height.
		 *
		 * @deprecated 5.0.0 Use {@see 'divi_module_library_portfolio_image_height'} instead.
		 *
		 * @since ??
		 */
		$height = apply_filters(
			'et_pb_portfolio_image_height',
			$height
		);

		// Type cast here for proper doc generation.
		$height = (int) $height;

		/**
		 * Filter the portfolio image height.
		 *
		 * @param int $height The portfolio image height.
		 *
		 * @since ??
		 */
		$height = apply_filters( 'divi_module_library_portfolio_image_height', $height );

		$class_text = self::_is_layout_fullwidth( $portfolio_grid_layout_display ) ? 'et_pb_post_main_image' : '';

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();

				$post_id           = get_the_ID();
				$categories        = [];
				$categories_object = get_the_terms( $post_id, 'project_category' );

				$category_classes = [ 'et_pb_portfolio_item' ];

				if ( ! empty( $categories_object ) ) {
					foreach ( $categories_object as $category ) {
						// Update category classes which will be used for post_class.
						$category_classes[] = 'project_category_' . urldecode( $category->slug );

						$categories[] = [
							'id'        => $category->term_id,
							'slug'      => $category->slug,
							'label'     => $category->name,
							'permalink' => get_term_link( $category ),
						];
					}
				}

				$title_text = get_the_title();
				// Capture the ALT text defined in WP Media Library.
				$alt_text = get_post_meta( get_post_thumbnail_id(), '_wp_attachment_image_alt', true );

				// Get thumbnail using standard dimensions for thumbnail generation.
				$thumbnail = get_thumbnail( $width, $height, $class_text, $alt_text, $title_text, false, 'Blogimage' );

				// Determine responsive image dimensions.
				$image_dimensions = self::_determine_image_dimensions( $attrs, $width, $height, $portfolio_grid_layout_display );

				// Generate thumbnail HTML with responsive dimensions.
				$thumbnail_output = print_thumbnail( $thumbnail['thumb'], $thumbnail['use_timthumb'], $title_text, $image_dimensions['display_width'], $image_dimensions['display_height'], '', false, true );

				// Replace URL with higher resolution if needed.
				if ( $image_dimensions['use_large_grid_image'] ) {
					$thumbnail_output = str_replace( '-400x284.', '-1080x675.', $thumbnail_output );
				}

				$new_post               = [];
				$new_post['id']         = $post_id;
				$new_post['title']      = get_the_title( $post_id );
				$new_post['permalink']  = get_permalink( $post_id );
				$new_post['thumbnail']  = $thumbnail_output;
				$new_post['categories'] = $categories;
				$new_post['classNames'] = array_merge( get_post_class( '', get_the_ID() ), $category_classes );

				$posts[] = $new_post;
			}
		}

		wp_reset_postdata();

		return $posts;
	}

	/**
	 * Determine responsive image dimensions for portfolio items.
	 *
	 * Analyzes layout type and responsive column configuration to determine
	 * optimal image dimensions. Uses ImageUtils to check if larger images
	 * are needed based on column size thresholds.
	 *
	 * @since ??
	 *
	 * @param array  $attrs                        Module attributes for responsive analysis.
	 * @param int    $base_width                   Base width (400 for grid, 1080 for fullwidth).
	 * @param int    $base_height                  Base height (284 for grid, 9999 for fullwidth).
	 * @param string $portfolio_grid_layout_display Layout display value ('flex', 'block', or 'grid').
	 *
	 * @return array {
	 *     Image sizing information.
	 *
	 *     @type int  $display_width        Width to use in HTML attributes.
	 *     @type int  $display_height       Height to use in HTML attributes.
	 *     @type bool $use_large_grid_image Whether to use large grid image URL.
	 * }
	 */
	private static function _determine_image_dimensions( array $attrs, int $base_width, int $base_height, string $portfolio_grid_layout_display ): array {
		$display_width        = $base_width;
		$display_height       = $base_height;
		$use_large_grid_image = false;

		// Check if responsive column configuration requires larger images.
		if ( ! empty( $attrs ) ) {
			$layout_for_image_size = 'grid' === $portfolio_grid_layout_display ? 'grid' : 'fullwidth';

			$selected_image_size = ImageUtils::select_optimal_image_size( $attrs, $layout_for_image_size );

			if ( 'et-pb-post-main-image-fullwidth' === $selected_image_size ) {
				$use_large_grid_image = true;
				$display_width        = 1080;
				$display_height       = 675;
			}
		}

		return [
			'display_width'        => $display_width,
			'display_height'       => $display_height,
			'use_large_grid_image' => $use_large_grid_image,
		];
	}

	/**
	 * Module custom CSS fields.
	 *
	 * This function is equivalent of JS function cssFields located in
	 * visual-builder/packages/module-library/src/components/filterable-portfolio/custom-css.ts.
	 *
	 * @since ??
	 *
	 * @return array The array of custom CSS fields.
	 */
	public static function custom_css(): array {
		return WP_Block_Type_Registry::get_instance()->get_registered( 'divi/filterable-portfolio' )->customCssFields;
	}

	/**
	 * Set CSS class names to the module.
	 *
	 * This function is equivalent of JS function moduleClassnames located in
	 * visual-builder/packages/module-library/src/components/filterable-portfolio/module-classnames.ts.
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
	public static function module_classnames( $args ) {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		// Text options.
		$classnames_instance->add( TextClassnames::text_options_classnames( $attrs['module']['advanced']['text'] ?? [] ), true );
		$classnames_instance->add( 'et_pb_portfolio', true );

		// Module.
		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					'attrs' => array_merge(
						$attrs['portfolioItem']['decoration'] ?? [],
						[
							'link' => $args['attrs']['module']['advanced']['link'] ?? [],
						]
					),
				]
			)
		);
	}

	/**
	 * Set script data to the module.
	 *
	 * This function is equivalent of JS function ModuleScriptData located in
	 * visual-builder/packages/module-library/src/components/filterable-portfolio/module-script-data.tsx.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string         $id            Module unique ID.
	 *     @type string         $name          Module name with namespace.
	 *     @type string         $selector      Module CSS selector.
	 *     @type array          $attrs         Module attributes.
	 *     @type array          $parentAttrs   Parent module attributes.
	 *     @type ModuleElements $elements      Instance of ModuleElements class.
	 *
	 *     // FE only.
	 *     @type int|null $storeInstance The ID of instance where this block stored in BlockParserStore.
	 *     @type int      $orderIndex    The order index of the element.
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

		$elements->script_data(
			[
				'attrName' => 'portfolioGrid',
			]
		);

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
						'selector'      => $selector . ' .et_pb_portofolio_pagination',
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
	 * Filterable Portfolio Grid Item's CSS declaration for horizontal gap.
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
	public static function filterable_portfolio_grid_item_style_declaration( array $params ): string {
		$declarations = new StyleDeclarations( $params );
		$attr         = $params['attr'] ?? [];

		return $declarations->value();
	}

	/**
	 * Set CSS styles to the module.
	 *
	 * This function is equivalent of JS function ModuleStyles located in
	 * visual-builder/packages/module-library/src/components/filterable-portfolio/module-styles.tsx.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string $id                       Module unique ID.
	 *     @type string $name                     Module name with namespace.
	 *     @type array  $attrs                    Module attributes.
	 *     @type array  $parentAttrs              Parent module attributes.
	 *     @type array  $siblingAttrs             Sibling module attributes.
	 *     @type array  $defaultPrintedStyleAttrs Default printed style attributes.
	 *     @type string $orderClass               Module CSS selector.
	 *     @type string $parentOrderClass         Parent module CSS selector.
	 *     @type string $wrapperOrderClass        Wrapper module CSS selector.
	 *     @type array  $settings                 Custom settings.
	 *     @type ModuleElements $elements         The ModuleElements instance.
	 *
	 *     // VB only.
	 *     @type string $state Attributes state.
	 *     @type string $mode  Style mode.
	 *
	 *     // FE only.
	 *     @type int|null $storeInstance The ID of instance where this block stored in BlockParserStore.
	 *     @type int      $orderIndex    The order index of the element.
	 * }
	 */
	public static function module_styles( array $args ): void {
		$attrs                       = $args['attrs'] ?? [];
		$elements                    = $args['elements'];
		$settings                    = $args['settings'] ?? [];
		$order_class                 = $args['orderClass'] ?? '';
		$is_inside_sticky_module     = $elements->get_is_inside_sticky_module();
		$sticky_parent_order_class   = $elements->get_sticky_parent_order_class();
		$default_printed_style_attrs = $args['defaultPrintedStyleAttrs'] ?? [];

		$main_selector = "{$args['orderClass']}.et_pb_filterable_portfolio";

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

					// Portfolio Grid.
					$elements->style(
						[
							'attrName'   => 'portfolioGrid',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'attr' => $attrs['portfolioGrid']['decoration']['layout'] ?? [],
											'declarationFunction' => [ self::class, 'filterable_portfolio_grid_item_style_declaration' ],
											'selectorFunction' => function ( $params ) {
												return $params['selector'] . '> .et_flex_column';
											},
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
								'fit'    => [
									'selector' => "{$order_class} .et_portfolio_image img",
								],
								'sizing' => [
									'propertySelectors' => [
										'desktop' => [
											'value' => [
												'aspect-ratio' => "{$order_class} .et_portfolio_image img",
											],
										],
									],
								],
							],
						]
					),
					CommonStyle::style(
						[
							'selector'            => $main_selector . ' .et_portfolio_image',
							'attr'                => $attrs['image']['decoration']['border'] ?? [],
							'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
							'orderClass'          => $order_class,
						]
					),

					TextStyle::style(
						[
							'selector'               => implode(
								', ',
								[
									$args['orderClass'] . ' .et_pb_module_header',
									$args['orderClass'] . ' .post-meta',
								]
							),
							'attr'                   => $attrs['module']['advanced']['text'] ?? [],
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

					// Filter Criteria.
					$elements->style(
						[
							'attrName' => 'filter',
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

					// Portfolio Item.
					$elements->style(
						[
							'attrName' => 'portfolioItem',
						]
					),

					// Overlay.
					$elements->style(
						[
							'attrName' => 'overlay',
						]
					),

					CommonStyle::style(
						[
							'selector'            => "{$args['orderClass']} .et_pb_portfolio_item",
							'attr'                => $attrs['portfolioItem']['decoration']['border'] ?? [],
							'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
							'orderClass'          => $order_class,
						]
					),

					// Custom CSS.
					CssStyle::style(
						[
							'selector'               => $order_class,
							'attr'                   => $attrs['css'] ?? [],
							'cssFields'              => self::custom_css(),
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
	 * Module render callback which outputs server side rendered HTML on the Front-End.
	 *
	 * This function is equivalent of JS function FilterablePortfolioEdit located in
	 * visual-builder/packages/module-library/src/components/filterable-portfolio/edit.tsx.
	 *
	 * @param array          $attrs                        Block attributes that were saved by VB.
	 * @param string         $child_modules_content        Block content from child modules.
	 * @param WP_Block       $block                        Parsed block object that being rendered.
	 * @param ModuleElements $elements                     Instance of ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements class.
	 * @param array          $default_printed_style_attrs  Default printed style attributes.
	 *
	 * @return string The module HTML output.
	 * @since ??
	 */
	public static function render_callback( array $attrs, string $child_modules_content, WP_Block $block, ModuleElements $elements, array $default_printed_style_attrs ): string {
		// Extract child modules IDs using helper utility.
		$children_ids = ChildrenUtils::extract_children_ids( $block );

		// Determine layout based on Layout Style setting (used throughout render).
		$portfolio_grid_layout_display = $attrs['portfolioGrid']['decoration']['layout']['desktop']['value']['display'] ?? 'grid';

		$show_pagination   = $attrs['portfolio']['advanced']['showPagination']['desktop']['value'] ?? 'on';
		$posts_per_page    = $attrs['portfolio']['advanced']['postsNumber']['desktop']['value'] ?? '';
		$selected_term     = $attrs['portfolio']['advanced']['includedCategories']['desktop']['value'] ?? [];
		$selected_term_ids = is_string( $selected_term ) ? explode( ',', $selected_term ) : $selected_term;
		$heading_level     = $attrs['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] ?? '';

		$hover_icon        = $attrs['overlay']['decoration']['icon']['desktop']['value'] ?? '';
		$hover_icon_tablet = $attrs['overlay']['decoration']['icon']['tablet']['value'] ?? '';
		$hover_icon_phone  = $attrs['overlay']['decoration']['icon']['phone']['value'] ?? '';
		$hover_icon_sticky = $attrs['overlay']['decoration']['icon']['desktop']['sticky'] ?? '';

		$icon        = $hover_icon ? Utils::process_font_icon( $hover_icon ) : '';
		$icon_tablet = $hover_icon_tablet ? Utils::process_font_icon( $hover_icon_tablet ) : '';
		$icon_phone  = $hover_icon_phone ? Utils::process_font_icon( $hover_icon_phone ) : '';
		$icon_sticky = $hover_icon_sticky ? Utils::process_font_icon( $hover_icon_sticky ) : '';

		$is_title_visible      = ModuleUtils::has_value(
			$attrs['portfolio']['advanced']['showTitle'] ?? [],
			[
				'valueResolver' => function ( $value ) {
					return 'off' !== $value;
				},
			]
		);
		$is_categories_visible = ModuleUtils::has_value(
			$attrs['portfolio']['advanced']['showCategories'] ?? [],
			[
				'valueResolver' => function ( $value ) {
					return 'off' !== $value;
				},
			]
		);
		$is_pagination_visible = ModuleUtils::has_value(
			$attrs['portfolio']['advanced']['showPagination'] ?? [],
			[
				'valueResolver' => function ( $value ) {
					return 'off' !== $value;
				},
			]
		);

		// Renders portfolio overlay HTML based on selected hover icons.
		// Only rendered when Layout Style is "grid".
		$overlay_html = '';
		if ( 'grid' === $portfolio_grid_layout_display ) {
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
		}

		// Render Portfolio Pagination Html.
		if ( $is_pagination_visible ) {
			$pagination_html = $elements->render(
				[
					'attrName'   => 'pagination',
					'tagName'    => 'div',
					'attributes' => [
						'class' => 'et_pb_portofolio_pagination clearfix',
					],
					'children'   => '',
				]
			);

			// Fallback if elements->render returns empty for pagination.
			if ( empty( $pagination_html ) ) {
				$pagination_html = HTMLUtility::render(
					[
						'tag'        => 'div',
						'attributes' => [
							'class' => 'et_pb_portofolio_pagination clearfix',
						],
					]
				);
			}
		} else {
			$pagination_html = '';
		}

		// Get Portfolio Items based on params passed.
		$query_args = [
			'categories'      => $selected_term_ids,
			'fullwidth'       => self::_is_layout_fullwidth( $portfolio_grid_layout_display ) ? 'on' : 'off',
			'show_pagination' => $show_pagination,
		];

		$filterable_portfolio_posts = self::get_portfolio_items( $query_args, $attrs, $portfolio_grid_layout_display );

		$items_count = 0;

		$portfolio_items_html = '';
		foreach ( $filterable_portfolio_posts as $portfolio ) {
			$item_class = sprintf( 'et_pb_filterable_portfolio_item_%1$s_%2$s', $block->parsed_block['orderIndex'], $items_count );
			++$items_count;

			$main_post_class = sprintf(
				'%1$s',
				$item_class
			);

			// Add flex column classes for grid items when layout is flex.
			$portfolio_item_classes = array_merge(
				explode( ' ', $main_post_class ),
				$portfolio['classNames'] ?? []
			);

			$post_meta = get_the_term_list( $portfolio['id'], 'project_category', '', ', ' );

			// Fetch Portfolio Thumbnail Image.
			$thumb_image_html = '';

			// Image Box shadow overlay.
			$box_shadow_components_overlay     = '';
			$box_shadow_classnames_has_overlay = '';

			if ( BoxShadowUtils::is_overlay_enabled( $attrs['image']['decoration']['boxShadow'] ?? [] ) ) {
				$box_shadow_components_overlay     = $elements->style_components(
					[
						'attrName' => 'image',
					]
				);
				$box_shadow_classnames_has_overlay = BoxShadowClassnames::has_overlay( $attrs['image']['decoration']['boxShadow'] ?? [] );
			}
			if ( ! empty( $portfolio['thumbnail'] ) ) {
				$thumb_src = $portfolio['thumbnail'];

				$width = self::_is_layout_fullwidth( $portfolio_grid_layout_display ) ? 1080 : 400;
				$width = (int) apply_filters( 'et_pb_portfolio_image_width', $width );

				$height = self::_is_layout_fullwidth( $portfolio_grid_layout_display ) ? 9999 : 284;
				$height = (int) apply_filters( 'et_pb_portfolio_image_height', $height );

				// Determine image dimensions.
				$image_dimensions = self::_determine_image_dimensions( $attrs, $width, $height, $portfolio_grid_layout_display );

				$thumb_image = self::_is_layout_fullwidth( $portfolio_grid_layout_display ) ? $elements->render(
					[
						'attrName'          => 'image',
						'tagName'           => 'span',
						'attributes'        => [
							'class' => 'et_portfolio_image ' . $box_shadow_classnames_has_overlay,
						],
						'skipAttrChildren'  => true,
						'children'          => [
							$box_shadow_components_overlay,
							HTMLUtility::render(
								[
									'tag'        => 'img',
									'attributes' => [
										'src'    => esc_url( $thumb_src ),
										'alt'    => esc_html( $portfolio['title'] ),
										'width'  => $image_dimensions['display_width'],
										'height' => $image_dimensions['display_height'],
									],
								]
							),
						],
						'childrenSanitizer' => 'et_core_esc_previously',
					]
				) : $elements->render(
					[
						'attrName'          => 'image',
						'tagName'           => 'span',
						'attributes'        => [
							'class' => 'et_portfolio_image ' . $box_shadow_classnames_has_overlay,
						],
						'skipAttrChildren'  => true,
						'children'          => [
							$box_shadow_components_overlay,
							HTMLUtility::render(
								[
									'tag'        => 'img',
									'attributes' => [
										'src'    => esc_url( $thumb_src ),
										'alt'    => esc_html( $portfolio['title'] ),
										'width'  => $image_dimensions['display_width'],
										'height' => $image_dimensions['display_height'],
									],
								]
							),
							$overlay_html,
						],
						'childrenSanitizer' => 'et_core_esc_previously',
					]
				);
				if ( self::_is_layout_fullwidth( $portfolio_grid_layout_display ) ) {
					// Generate Image srcset and sizes only for fullwidth layouts.
					$thumb_image = et_image_add_srcset_and_sizes( $thumb_image, false );
					// phpcs:ignore Universal.ControlStructures.DisallowLonelyIf.Found -- Intentional else-if pattern for readability.
				} else {
					// For grid layouts, replace URL with higher resolution if needed.
					if ( $image_dimensions['use_large_grid_image'] ) {
						$thumb_image = str_replace( '-400x284.', '-1080x675.', $thumb_image );
					}
				}

				$thumb_image_html = ! empty( $thumb_image ) ? HTMLUtility::render(
					[
						'tag'               => 'a',
						'attributes'        => [
							'href'  => esc_url( $portfolio['permalink'] ),
							'title' => esc_html( $portfolio['title'] ),
						],
						'children'          => $thumb_image,
						'childrenSanitizer' => 'et_core_esc_previously',
					]
				) : '';
			}

			// Render Portfolio Item Title Html.
			$title_html = $is_title_visible
				? $elements->render(
					[
						'attrName'          => 'title',
						'tagName'           => $heading_level,
						'attributes'        => [
							'class' => 'et_pb_module_header',
						],
						'skipAttrChildren'  => true,
						'childrenSanitizer' => 'et_core_esc_previously',
						'children'          => HTMLUtility::render(
							[
								'tag'        => 'a',
								'attributes' => [
									'href'  => esc_url( $portfolio['permalink'] ),
									'title' => esc_html( $portfolio['title'] ),
								],
								'children'   => esc_html( $portfolio['title'] ),
							]
						),
					]
				)
				: null;

			// Render Portfolio Categories Html.
			$categories_html = $is_categories_visible
				? $elements->render(
					[
						'attrName'          => 'meta',
						'tagName'           => 'p',
						'attributes'        => [
							'class' => 'post-meta',
						],
						'skipAttrChildren'  => true,
						'childrenSanitizer' => 'et_core_esc_previously',
						'children'          => et_core_esc_wp( $post_meta ),
					]
				)
				: null;

			// Portfolio item wrapper.
			$portfolio_items_html .= $elements->render(
				[
					'attrName'          => 'portfolioItem',
					'tagName'           => 'div',
					'attributes'        => [
						'class' => HTMLUtility::classnames( $portfolio_item_classes ),
						'id'    => $portfolio['id'] ?? '',
					],
					'skipAttrChildren'  => true,
					'childrenSanitizer' => 'et_core_esc_previously',
					'children'          => [
						$thumb_image_html,
						$title_html,
						$categories_html,
					],
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

		$terms_args = [
			'include' => $selected_term,
			'orderby' => 'name',
			'order'   => 'ASC',
		];
		// phpcs:ignore WordPress.WP.DeprecatedParameters.Get_termsParam2Found -- Using legacy format for compatibility.
		$terms = get_terms( 'project_category', $terms_args );

		$category_filters  = '<ul class="clearfix">';
		$category_filters .= sprintf(
			'<li class="et_pb_portfolio_filter et_pb_portfolio_filter_all"><a href="#" class="active" data-category-slug="all">%1$s</a></li>',
			esc_html__( 'All', 'et_builder_5' )
		);
		foreach ( $terms as $term ) {
			$category_filters .= sprintf(
				'<li class="et_pb_portfolio_filter"><a href="#" data-category-slug="%1$s">%2$s</a></li>',
				esc_attr( urldecode( $term->slug ) ),
				esc_html( $term->name )
			);
		}
		$category_filters .= '</ul>';

		$category_filters_html = $elements->render(
			[
				'attrName'          => 'filter',
				'tagName'           => 'div',
				'attributes'        => [
					'class' => HTMLUtility::classnames(
						[
							'et_pb_portfolio_filters' => true,
							'clearfix'                => true,
						]
					),
				],
				'skipAttrChildren'  => true,
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $category_filters,
			]
		);

		// Portfolio Items Content Wrapper.
		$portfolio_grid_layout   = $attrs['portfolioGrid']['decoration']['layout']['desktop']['value'] ?? null;
		$layout_display          = ( $portfolio_grid_layout ?? [] )['display'] ?? 'grid';
		$is_flex_layout          = 'flex' === $layout_display;
		$is_grid_layout          = 'grid' === $layout_display;
		$portfolio_posts_classes = [ 'et_pb_portfolio_items', 'et_pb_posts' ];

		if ( $is_flex_layout ) {
			$portfolio_posts_classes[] = 'et_flex_module';
		} elseif ( $is_grid_layout ) {
			$portfolio_posts_classes[] = 'et_grid_module';
		} else {
			// Block layout or any other layout type.
			$portfolio_posts_classes[] = 'et_block_module';
		}

		$portfolio_items_wrapper_inner = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => HTMLUtility::classnames( $portfolio_posts_classes ),
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => [
					$portfolio_items_html,
					$child_modules_content,
				],
			]
		);
		$portfolio_items_wrapper       = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => HTMLUtility::classnames(
						[
							'et_pb_portfolio_items_wrapper' => true,
							'no_pagination' => ! $is_pagination_visible,
							'clearfix'      => true,
						]
					),
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $portfolio_items_wrapper_inner,
			]
		);

		// Renders Portfolio final HTML output if portfolio found.
		$children = ! empty( $portfolio_items_html ) ? $category_filters_html . $portfolio_items_wrapper . $pagination_html : $no_posts_output;

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
				'htmlAttrs'                => [
					'data-posts-number' => $posts_per_page,
				],
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
				) . $children . $child_modules_content,
			]
		);
	}

	/**
	 * Loads `FilterablePortfolioModule` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load() {
		// phpcs:ignore PHPCompatibility.FunctionUse.NewFunctionParameters.dirname_levelsFound -- We have PHP 7 support now, This can be deleted once PHPCS config is updated.
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/filterable-portfolio/';

		add_filter( 'divi_conversion_presets_attrs_map', [ FilterablePortfolioPresetAttrsMap::class, 'get_map' ], 10, 2 );

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
