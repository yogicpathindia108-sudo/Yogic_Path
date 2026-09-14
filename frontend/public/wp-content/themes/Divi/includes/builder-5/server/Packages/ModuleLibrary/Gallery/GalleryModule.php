<?php
/**
 * ModuleLibrary: Gallery Module class.
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Gallery;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\IconLibrary\IconFont\Utils as IconFontUtils;
use ET\Builder\Packages\IconLibrary\IconFont\Utils;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewUtils;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\BoxShadow\BoxShadowClassnames;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Animation\AnimationUtils;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\ModuleUtils\ImageUtils;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use ET\Builder\Packages\StyleLibrary\Declarations\Border\Border;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\GlobalData\GlobalData;
use Exception;
use WP_Block_Type_Registry;
use WP_Block;
use ET\Builder\Framework\Breakpoint\Breakpoint;

/**
 * `GalleryModule` is consisted of functions used for Gallery Module such as Front-End rendering, REST API Endpoints etc.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 */
class GalleryModule implements DependencyInterface {

	/**
	 * Filters the module.decoration attributes.
	 *
	 * This function is equivalent of JS function filterModuleDecorationAttrs located in
	 * visual-builder/packages/module-library/src/components/gallery/attrs-filter/filter-module-decoration-attrs/index.ts.
	 *
	 * @since ??
	 *
	 * @param array $decoration_attrs The original decoration attributes.
	 * @param array $attrs The attributes of the Gallery module.
	 *
	 * @return array The filtered decoration attributes.
	 */
	public static function filter_module_decoration_attrs( array $decoration_attrs, array $attrs ): array {
		// Attribute `module.advanced.fullwidth` is desktop only.
		$is_slider = $attrs['module']['advanced']['fullwidth']['desktop']['value'] ?? null;

		// If the module layout is Grid, it returns the decoration attributes with empty `boxShadow`.
		if ( 'on' !== $is_slider ) {
			$decoration_attrs = array_merge(
				$decoration_attrs,
				[
					'boxShadow' => [],
				]
			);
		}

		return $decoration_attrs;
	}

	/**
	 * Filters the image.decoration attributes.
	 *
	 * This function is equivalent of JS function filterImageDecorationAttrs located in
	 * visual-builder/packages/module-library/src/components/gallery/attrs-filter/filter-image-decoration-attrs/index.ts.
	 *
	 * @since ??
	 *
	 * @param array $decoration_attrs The decoration attributes to be filtered.
	 * @param array $attrs           The whole module attributes.
	 *
	 * @return array The filtered decoration attributes.
	 */
	public static function filter_image_decoration_attrs( array $decoration_attrs, array $attrs ): array {
		// Attribute `module.advanced.fullwidth` is desktop only.
		$is_slider = $attrs['module']['advanced']['fullwidth']['desktop']['value'] ?? null;

		// If the module layout is Slider, it returns the image decoration attributes with empty `border` and `boxShadow`.
		if ( 'on' === $is_slider ) {
			$decoration_attrs = array_merge(
				$decoration_attrs,
				[
					'border'    => [],
					'boxShadow' => [],
				]
			);
		}

		return $decoration_attrs;
	}

	/**
	 * Module custom CSS fields.
	 *
	 * This function is equivalent of JS function cssFields located in
	 * visual-builder/packages/module-library/src/components/gallery/custom-css.ts.
	 *
	 * @since ??
	 *
	 * @return array The array of custom CSS fields.
	 */
	public static function custom_css(): array {
		return WP_Block_Type_Registry::get_instance()->get_registered( 'divi/gallery' )->customCssFields;
	}

	/**
	 * Set CSS class names to the module.
	 *
	 * This function is equivalent of JS function moduleClassnames located in
	 * visual-builder/packages/module-library/src/components/gallery/module-classnames.ts.
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

		$auto            = $attrs['module']['advanced']['auto']['desktop']['value'] ?? 'off';
		$auto_speed      = $attrs['module']['advanced']['autoSpeed']['desktop']['value'] ?? '7000';
		$fullwidth       = $attrs['module']['advanced']['fullwidth']['desktop']['value'] ?? 'off';
		$show_pagination = $attrs['pagination']['advanced']['showPagination']['desktop']['value'] ?? 'on';

		if ( 'on' === $fullwidth ) {
			$classnames_instance->add( 'et_pb_slider' );
			$classnames_instance->add( 'et_pb_gallery_fullwidth' );
			$classnames_instance->add( 'clearfix' );

			if ( 'off' === $show_pagination ) {
				$classnames_instance->add( 'et_pb_slider_no_pagination' );
			}

			if ( 'on' === $auto ) {
				$classnames_instance->add( 'et_slider_auto' );
				$classnames_instance->add( 'et_slider_speed_' . $auto_speed );
			}
		} else {
			$classnames_instance->add( 'et_pb_gallery_grid' );
		}

		// Module.
		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					'attrs' => array_merge(
						$attrs['item']['decoration'] ?? [],
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
	 * visual-builder/packages/module-library/src/components/gallery/module-script-data.tsx.
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

		// Fullwidth is desktop only attribute.
		$is_fullwidth = 'on' === ( $attrs['module']['advanced']['fullwidth']['desktop']['value'] ?? '' );

		MultiViewScriptData::set(
			[
				'id'            => $id,
				'name'          => $name,
				'storeInstance' => $store_instance,
				'hoverSelector' => $selector,
				'setVisibility' => [
					[
						'selector'      => $selector . ' .et_pb_gallery_title, ' . $selector . ' .et_pb_gallery_caption',
						'data'          => $attrs['module']['advanced']['showTitleAndCaption'] ?? [],
						'valueResolver' => function ( $value ) {
							return 'on' === $value ? 'visible' : 'hidden';
						},
					],
					$is_fullwidth ? [] : [
						'selector'      => $selector . ' .et_pb_gallery_pagination',
						'data'          => $attrs['pagination']['advanced']['showPagination'] ?? [],
						'valueResolver' => function ( $value ) {
							return 'on' === ( $value ?? 'on' ) ? 'visible' : 'hidden';
						},
					],
				],
				'setClassName'  => [
					$is_fullwidth ? [
						'selector'      => $selector,
						'data'          => [
							'et_pb_slider_no_pagination' => $attrs['pagination']['advanced']['showPagination'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'on' === ( $value ?? 'on' ) ? 'remove' : 'add';
						},
					] : [],
				],
			]
		);
	}

	/**
	 * Get overlay icon style declaration for Gallery module.
	 *
	 * This function takes an array of parameters and returns a CSS style
	 * declaration for the overlay icon. The style declaration includes
	 * properties such as color, font-family, and font-weight. It uses the
	 * values provided in the parameters to generate the style declaration.
	 *
	 * @since ??
	 *
	 * @param array $params An array of parameters.
	 *
	 * @throws Exception Throws an exception if the hover icon type is not supported.
	 *
	 * @return string The CSS style declaration for the overlay icon.
	 *
	 * @example
	 * ```php
	 * $params = [
	 *   'attrValue' => [
	 *     'hoverIcon' => [
	 *       'type' => 'font',
	 *       'weight' => 400
	 *     ],
	 *     'iconColor' => '#ff0000'
	 *   ],
	 * ];
	 * $style = GalleryModule::icon_font_style_declaration( $params );
	 * // Result: 'color: #ff0000; font-weight: 400;'
	 * ```
	 */
	public static function icon_font_style_declaration( array $params ): string {
		$overlay_icon_attr = $params['attrValue'];
		$hover_icon        = $overlay_icon_attr['icon'] ?? [];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => true,
			]
		);

		$font_icon = IconFontUtils::escape_font_icon( IconFontUtils::process_font_icon( $hover_icon ) );

		if ( ! empty( $hover_icon['type'] ) ) {
			$font_family = IconFontUtils::is_fa_icon( $hover_icon ) ? 'FontAwesome' : 'ETmodules';
			$style_declarations->add( 'font-family', "'{$font_family}'" );
		}

		if ( ! empty( $hover_icon['weight'] ) ) {
			$style_declarations->add( 'font-weight', $hover_icon['weight'] );
		}

		if ( ! empty( $hover_icon['unicode'] ) ) {
			$style_declarations->add( 'content', "'{$font_icon}'" );
		}

		return $style_declarations->value();
	}

	/**
	 * Declare the overlay background style for the Gallery module.
	 *
	 * This function takes an array of arguments and declares the overlay background style for the Gallery module.
	 *
	 * @since ??
	 *
	 * @param array $params An array of arguments.
	 *
	 * @return string The overlay background style declaration.
	 *
	 * @example
	 * ```php
	 * $params = array(
	 *     'attrValue' => array(
	 *         'backgroundColor' => '#000000'
	 *     ),
	 *     'important' => true,
	 * );
	 * GalleryModule::hover_overlay_color_style_declaration( $params );
	 * // Result: 'background-color: #000000;'
	 * ```
	 */
	public static function hover_overlay_color_style_declaration( array $params ): string {
		$overlay_color = $params['attrValue'];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => $params['important'],
			]
		);

		if ( ! empty( $overlay_color ) ) {
			$style_declarations->add( 'background-color', $overlay_color );
		}

		return $style_declarations->value();
	}


	/**
	 * Gallery Grid Item's CSS declaration for horizontal gap.
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
	public static function gallery_grid_item_style_declaration( array $params ): string {
		$declarations = new StyleDeclarations( $params );
		$attr         = $params['attr'] ?? [];

		return $declarations->value();
	}

	/**
	 * Border style declaration for Gallery module.
	 *
	 * Applies border styles only when slider mode is active and border attribute exists.
	 * This ensures box shadow follows border-radius correctly in slider mode.
	 * Border-radius is applied to both module level and item level.
	 *
	 * This function is equivalent of JS function borderStyleDeclaration located in
	 * visual-builder/packages/module-library/src/components/gallery/style-declarations/border/index.ts.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of parameters.
	 *
	 *     @type array      $attrValue  The value (breakpoint > state > value) of module attribute.
	 *     @type bool|array $important  Optional. Whether to add `!important` tag. Default `false`.
	 *     @type string     $returnType Optional. The type of value that the function will return.
	 *                                  Can be either `string` or `key_value_pair`. Default `string`.
	 *     @type array      $defaultAttrValue Optional. Default attribute of border attribute.
	 *     @type array      $attrs      Optional. Gallery module attributes for slider check.
	 * }
	 *
	 * @return string|array Border CSS declaration or empty string/array if conditions not met.
	 */
	public static function border_style_declaration( array $params ) {
		$attr_value         = $params['attrValue'] ?? [];
		$important          = $params['important'] ?? false;
		$return_type        = $params['returnType'] ?? 'string';
		$default_attr_value = $params['defaultAttrValue'] ?? [];
		$attrs              = $params['attrs'] ?? [];

		// Check if slider mode is active.
		$is_slider = 'on' === ( $attrs['module']['advanced']['fullwidth']['desktop']['value'] ?? null );

		// Check if border attribute exists and has values.
		$has_border_attr = ! empty( $attr_value );

		// Apply border-radius to module level only when slider mode is active and border attr exists.
		// This ensures box shadow follows border-radius correctly.
		// Border-radius is applied to both module level and item level.
		if ( ! $is_slider || ! $has_border_attr ) {
			return 'string' === $return_type ? '' : [];
		}

		// Call base border style declaration if conditions are met.
		return Border::style_declaration(
			[
				'attrValue'        => $attr_value,
				'important'        => $important,
				'returnType'       => $return_type,
				'defaultAttrValue' => $default_attr_value,
			]
		);
	}

	/**
	 * Set CSS styles to the module.
	 *
	 * This function is equivalent of JS function ModuleStyles located in
	 * visual-builder/packages/module-library/src/components/gallery/module-styles.tsx.
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
	 *     @type object $elements                 Instance of ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements class.
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
	public static function module_styles( $args ) {
		$attrs    = $args['attrs'] ?? [];
		$elements = $args['elements'];
		$settings = $args['settings'] ?? [];

		$default_printed_style_attrs = $args['defaultPrintedStyleAttrs'] ?? [];

		// Build advanced styles for module.
		$module_advanced_styles = [
			[
				'componentName' => 'divi/text',
				'props'         => [
					'selector'          => "{$args['orderClass']}.et_pb_gallery .et_pb_gallery_title, {$args['orderClass']}.et_pb_gallery .mfp-title, {$args['orderClass']}.et_pb_gallery .et_pb_gallery_caption, {$args['orderClass']}.et_pb_gallery .et_pb_gallery_pagination a",
					'attr'              => $attrs['module']['advanced']['text'] ?? [],
					'propertySelectors' => [
						'textShadow' => [
							'desktop' => [
								'value' => [
									'text-shadow' => "{$args['orderClass']}.et_pb_gallery.et_pb_gallery_grid",
								],
							],
						],
					],
				],
			],
			[
				'componentName' => 'divi/common',
				'props'         => [
					'selector'            => $args['orderClass'] . ' .et_overlay',
					'attr'                => $attrs['module']['advanced']['hoverOverlayColor'] ?? [],
					'declarationFunction' => [ self::class, 'hover_overlay_color_style_declaration' ],
				],
			],
			[
				'componentName' => 'divi/common',
				'props'         => [
					'selector'            => $args['orderClass'],
					'attr'                => $attrs['item']['decoration']['border'] ?? [],
					'declarationFunction' => function ( $params ) use ( $attrs ) {
						return self::border_style_declaration(
							array_merge(
								$params,
								[
									'attrs' => $attrs,
								]
							)
						);
					},
				],
			],
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
								'defaultPrintedStyleAttrs' => $default_printed_style_attrs['module']['decoration'] ?? [],
								'disabledOn'               => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
								'attrsFilter'              => function ( $decoration_attrs ) use ( $attrs ) {
									return GalleryModule::filter_module_decoration_attrs( $decoration_attrs, $attrs );
								},
								'advancedStyles'           => $module_advanced_styles,
							],
						]
					),

					// title.
					$elements->style(
						[
							'attrName' => 'title',
						]
					),
					// caption.
					$elements->style(
						[
							'attrName' => 'caption',
						]
					),
					// pagination.
					$elements->style(
						[
							'attrName' => 'pagination',
						]
					),
					// item.
					$elements->style(
						[
							'attrName'   => 'item',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $args['orderClass'] . '.et_pb_gallery .et_pb_gallery_item',
											'attr'     => $attrs['item']['decoration']['border'] ?? [],
											'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
										],
									],
								],
							],
						]
					),
					// image.
					$elements->style(
						[
							'attrName'   => 'image',
							'styleProps' => [
								'fit'            => [
									'selector' => $args['orderClass'] . '.et_pb_gallery .et_pb_gallery_image img',
								],
								'sizing'         => [
									'propertySelectors' => [
										'desktop' => [
											'value' => [
												'aspect-ratio' => $args['orderClass'] . '.et_pb_gallery .et_pb_gallery_image img',
											],
										],
									],
								],
								'attrsFilter'    => function ( $decoration_attrs ) use ( $attrs ) {
									return GalleryModule::filter_image_decoration_attrs( $decoration_attrs, $attrs );
								},
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $args['orderClass'] . '.et_pb_gallery .et_pb_gallery_image',
											'attr'     => $attrs['image']['decoration']['border'] ?? [],
											'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
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
											'selector' => $args['orderClass'] . ' .et_overlay:before',
											'attr'     => $attrs['overlay']['innerContent'] ?? [],
											'declarationFunction' => [ self::class, 'icon_font_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector'  => $args['orderClass'] . ' .et_overlay:before',
											'attr'      => $attrs['overlay']['advanced']['zoomIconColor'] ?? [],
											'property'  => 'color',
											'important' => true,
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $args['orderClass'] . ' .et_overlay',
											'attr'     => $attrs['overlay']['advanced']['hoverOverlayColor'] ?? [],
											'declarationFunction' => [ self::class, 'hover_overlay_color_style_declaration' ],
										],
									],
								],
							],
						]
					),

					// Gallery Grid - Layout settings for gallery wrapper.
					$elements->style(
						[
							'attrName'   => 'galleryGrid',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'attr' => $attrs['galleryGrid']['decoration']['layout'] ?? [],
											'declarationFunction' => [ self::class, 'gallery_grid_item_style_declaration' ],
											'selectorFunction' => function ( $params ) {
												return $params['selector'] . '> .et_flex_column';
											},
										],
									],
								],
							],
						]
					),

					// Module - Only for Custom CSS.
					CssStyle::style(
						[
							'selector'  => $args['orderClass'] . '.et_pb_gallery',
							'attr'      => $attrs['css'] ?? [],
							'cssFields' => self::custom_css(),
						]
					),
				],
			]
		);
	}

	/**
	 * Module render callback which outputs server side rendered HTML on the Front-End.
	 *
	 * This function is equivalent of JS function GalleryEdit located in
	 * visual-builder/packages/module-library/src/components/gallery/edit.tsx.
	 *
	 * @param array          $attrs block attributes that were saved by VB.
	 * @param string         $content block content.
	 * @param WP_Block       $block parsed block object that being rendered.
	 * @param ModuleElements $elements instance of ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements class.
	 * @param array          $default_printed_style_attrs default printed style attributes.
	 *
	 * @return string the module HTML output
	 * @since ??
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements, array $default_printed_style_attrs ): string {
		// Extract child modules IDs using helper utility.
		$children_ids = ChildrenUtils::extract_children_ids( $block );

		$hover_icon        = $attrs['overlay']['decoration']['icon']['desktop']['value'] ?? '';
		$hover_icon_tablet = $attrs['overlay']['decoration']['icon']['tablet']['value'] ?? '';
		$hover_icon_phone  = $attrs['overlay']['decoration']['icon']['phone']['value'] ?? '';

		$icon        = ! empty( $hover_icon ) ? Utils::process_font_icon( $hover_icon ) : '';
		$icon_tablet = ! empty( $hover_icon_tablet ) ? Utils::process_font_icon( $hover_icon_tablet ) : '';
		$icon_phone  = ! empty( $hover_icon_phone ) ? Utils::process_font_icon( $hover_icon_phone ) : '';

		// Fullwidth is desktop only attribute.
		$is_fullwidth = 'on' === ( $attrs['module']['advanced']['fullwidth']['desktop']['value'] ?? '' );
		$is_animation_enabled = AnimationUtils::is_enabled( $attrs['module']['decoration']['animation'] ?? [] );

		$show_pagination = ModuleUtils::has_value(
			$attrs['pagination']['advanced']['showPagination'] ?? [],
			[
				'valueResolver' => function ( $value ) {
					return 'on' === $value;
				},
			]
		);

		$has_title_and_caption = ModuleUtils::has_value(
			$attrs['module']['advanced']['showTitleAndCaption'] ?? [],
			[
				'valueResolver' => function ( $value ) {
					return 'on' === $value;
				},
			]
		);

		$fullwidth             = $attrs['module']['advanced']['fullwidth']['desktop']['value'] ?? 'off';
		$posts_number          = $attrs['module']['advanced']['postsNumber']['desktop']['value'] ?? '4';
		$gallery_ids           = $attrs['image']['advanced']['galleryIds']['desktop']['value'] ?? [];
		$gallery_orderby       = $attrs['image']['advanced']['galleryOrderby']['desktop']['value'] ?? 'default';
		$gallery_captions      = $attrs['image']['advanced']['galleryCaptions']['desktop']['value'] ?? '';
		$heading_level         = $attrs['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] ?? 'h2';
		$orientation           = $attrs['image']['advanced']['orientation']['desktop']['value'] ?? '';
		$pagination_text_align = $attrs['pagination']['decoration']['font']['textAlign']['desktop']['value'] ?? '';
		$auto_rotate           = $attrs['module']['advanced']['autoRotate']['desktop']['value'] ?? 'off';
		$auto_rotate_speed     = $attrs['module']['advanced']['autoRotateSpeed']['desktop']['value'] ?? '';
		$module_order_index    = $block->parsed_block['orderIndex'];

		// Gallery Grid Layout for wrapper classes.
		$gallery_grid_layout = $attrs['galleryGrid']['decoration']['layout']['desktop']['value']['display'] ?? 'grid';
		$is_flex_layout      = 'flex' === $gallery_grid_layout;
		$is_grid_layout      = 'grid' === $gallery_grid_layout;

		// Get gallery item data.
		$attachments = self::get_gallery_items(
			[
				'gallery_ids'     => $gallery_ids,
				'gallery_orderby' => $gallery_orderby,
				'fullwidth'       => $fullwidth,
				'orientation'     => $orientation,
			],
			$attrs
		);

		if ( empty( $attachments ) ) {
			return '';
		}
		$posts_number = 0 === (int) $posts_number ? 4 : (int) $posts_number;

		$overlay_output = HTMLUtility::render(
			[
				'tag'               => 'span',
				'attributes'        => [
					'class'            => HTMLUtility::classnames(
						[
							'et_overlay'               => true,
							'et_pb_inline_icon'        => ! empty( $icon ),
							'et_pb_inline_icon_tablet' => ! empty( $icon_tablet ),
							'et_pb_inline_icon_phone'  => ! empty( $icon_phone ),
						]
					),
					'data-icon'        => $icon,
					'data-icon-tablet' => $icon_tablet,
					'data-icon-phone'  => $icon_phone,
				],
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);

		$images_count = 0;

		$output = '';
		foreach ( $attachments as $id => $attachment ) {
			// Use full-size image in slider mode, thumbnail in grid mode.
			$image_src = $is_fullwidth ? $attachment->image_src_full[0] : $attachment->image_src_thumb[0];

			$image_render_attributes = [
				'src'   => $image_src,
				'alt'   => $attachment->image_alt_text,
				'class' => 'wp-image-' . $attachment->ID,
			];

			if ( ! $is_fullwidth && et_is_responsive_images_enabled() ) {
				$image_render_attributes['srcset'] = $attachment->image_src_full[0] . ' 479w, ' . $attachment->image_src_thumb[0] . ' 480w';
				$image_render_attributes['sizes']  = '(max-width:479px) 479px, 100vw';
			}

			$image_html = $elements->render(
				[
					'attrName'   => 'image',
					'tagName'    => 'img',
					'attributes' => $image_render_attributes,
				]
			);

			$image_anchor = HTMLUtility::render(
				[
					'tag'               => 'a',
					'attributes'        => [
						'href'  => esc_url( $attachment->image_src_full[0] ),
						'title' => esc_attr( $attachment->post_title ),
					],
					'children'          => [
						$image_html,
						et_core_esc_previously( $overlay_output ),
					],
					'childrenSanitizer' => 'et_core_esc_previously',
				]
			);

			$image_title   = '';
			$image_caption = '';

			if ( ! $is_fullwidth && $has_title_and_caption ) {
				if ( trim( $attachment->post_title ) ) {
					$image_title = $elements->render(
						[
							'attrName'         => 'title',
							'tagName'          => esc_attr( $heading_level ),
							'skipAttrChildren' => true,
							'children'         => wptexturize( $attachment->post_title ),
						]
					);
				}
				if ( trim( $attachment->post_excerpt ) ) {
					$image_caption .= $elements->render(
						[
							'attrName'          => 'caption',
							'tagName'           => 'p',
							'skipAttrChildren'  => true,
							'childrenSanitizer' => 'wp_kses_post',
							'children'          => wptexturize( $attachment->post_excerpt ),
						]
					);
				}
			}

			$image_container = HTMLUtility::render(
				[
					'tag'               => 'div',
					'attributes'        => [
						'class'         => HTMLUtility::classnames(
							[
								'et_pb_gallery_image' => true,
								'landscape'           => 'portrait' !== $orientation,
								'portrait'            => 'portrait' === $orientation,
							],
							BoxShadowClassnames::has_overlay( $attrs['image']['decoration']['boxShadow'] ?? [] )
						),
						'data-per_page' => $posts_number,
					],
					'children'          => [
						$elements->style_components(
							[
								'attrName' => 'image',
							]
						),
						$image_anchor,
					],
					'childrenSanitizer' => 'et_core_esc_previously',
				]
			);

			// Build gallery item classes.
			$gallery_item_classes = [ 'et_pb_gallery_item' ];

			if ( ! $is_fullwidth ) {
				$gallery_item_classes[] = 'et_pb_grid_item';
			}

			// Add unique item class.
			$gallery_item_classes[] = sprintf( 'et_pb_gallery_item_%1$s_%2$s', $module_order_index, $images_count );

			$gallery_item = HTMLUtility::render(
				[
					'tag'               => 'div',
					'attributes'        => [
						'class' => implode( ' ', $gallery_item_classes ),
					],
					'children'          => [
						$image_container,
						$image_title,
						$image_caption,
					],
					'childrenSanitizer' => 'et_core_esc_previously',
				]
			);

			++$images_count;

			$output .= $gallery_item;
		}

		$pagination_html = '';
		if ( ! $is_fullwidth && $show_pagination ) {
			$pagination_classes = HTMLUtility::classnames(
				[
					'et_pb_gallery_pagination'         => true,
					'et_pb_gallery_pagination_justify' => 'justify' === $pagination_text_align,
				],
				MultiViewUtils::hidden_on_load_class_name(
					$attrs['pagination']['advanced']['showPagination'] ?? [],
					[
						'valueResolver' => function ( $value ) {
							return 'on' === ( $value ?? 'on' ) ? 'visible' : 'hidden';
						},
					]
				)
			);

			$pagination_html = $elements->render(
				[
					'attrName'         => 'pagination',
					'tagName'          => 'div',
					'attributes'       => [
						'class' => $pagination_classes,
					],
					'skipAttrChildren' => true,
				]
			);
		}

		$output_wrapper = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class'         => HTMLUtility::classnames(
						[
							'et_pb_gallery_items' => true,
							'et_post_gallery'     => true,
							'clearfix'            => true,
							// Only add layout-specific classes when NOT in slider mode.
							// In slider mode (fullwidth), the slider handles its own layout.
							'et_flex_module'      => ! $is_fullwidth && $is_flex_layout,
							'et_grid_module'      => ! $is_fullwidth && $is_grid_layout,
							'et_block_module'     => ! $is_fullwidth && ! $is_flex_layout && ! $is_grid_layout,
						]
					),
					'data-per_page' => $posts_number,
				],
				'children'          => [
					$output,
					$content,
				],
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );
		$html_attrs = [
			'data-auto-rotate'       => $auto_rotate,
			'data-auto-rotate-speed' => $auto_rotate_speed,
		];

		if ( $is_animation_enabled ) {
			$html_attrs['data-divi-gallery-animation-bootstrap'] = 'on';
			$html_attrs['style']                                 = 'opacity: 0;';
		}

		return Module::render(
			[
				// FE only.
				'orderIndex'               => $block->parsed_block['orderIndex'],
				'storeInstance'            => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'attrs'                    => $attrs,
				'htmlAttrs'                => $html_attrs,
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
				) . $output_wrapper . $pagination_html,
			]
		);
	}

	/**
	 * Sanitize attachment caption/excerpt HTML for gallery output.
	 *
	 * Mirrors Blog REST title sanitization: decode entities so allowed tags can render,
	 * then apply `wp_kses_post()` to strip disallowed markup such as `<script>`.
	 *
	 * @since ??
	 *
	 * @param string $excerpt Attachment `post_excerpt` (caption) value.
	 *
	 * @return string KSES-sanitized caption HTML.
	 */
	public static function sanitize_attachment_excerpt( string $excerpt ): string {
		return wp_kses_post( html_entity_decode( $excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * Get Gallery Items.
	 *
	 * @since ??
	 *
	 * @param array $args  Gallery Item request params.
	 * @param array $attrs Module attributes for responsive image sizing.
	 *
	 * @return array The processed content.
	 */
	public static function get_gallery_items( array $args, array $attrs = [] ) {
		$defaults = [
			'gallery_ids'      => [],
			'gallery_orderby'  => '',
			'gallery_captions' => [],
			'fullwidth'        => 'off',
			'orientation'      => 'landscape',
		];

		$args = wp_parse_args( $args, $defaults );

		$attachments_args = [
			'include'        => $args['gallery_ids'],
			'post_status'    => 'inherit',
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'order'          => 'ASC',
			'orderby'        => 'post__in',
		];

		// Woo Gallery module shouldn't display placeholder image when no Gallery image is available.
		// @see https://github.com/elegantthemes/submodule-builder/pull/6706#issuecomment-542275647.
		if ( isset( $args['attachment_id'] ) ) {
			$attachments_args['attachment_id'] = $args['attachment_id'];
		}

		if ( 'rand' === $args['gallery_orderby'] ) {
			$attachments_args['orderby'] = 'rand';
		}

		// Select optimal image size based on layout and flexbox column configuration.
		$layout              = 'on' === $args['fullwidth'] ? 'fullwidth' : 'grid';
		$selected_image_size = ImageUtils::select_optimal_image_size( $attrs, $layout, 'galleryGrid' );

		// Use the same image sizing logic as Portfolio modules.
		if ( 'et-pb-post-main-image-fullwidth' === $selected_image_size ) {
			// Large grid images for big columns (1/1, 2/3, 1/2 on desktop/tablet).
			$width  = 1080;
			$height = 675;
		} elseif ( 'et-pb-portfolio-image-single' === $selected_image_size ) {
			// Fullwidth layout uses original aspect ratio.
			$width  = 1080;
			$height = 9999;
		} else {
			// Small grid images for small columns (1/4, 1/3) - default et-pb-portfolio-image.
			$width  = 400;
			$height = ( 'landscape' === $args['orientation'] ) ? 284 : 516;
		}

		// Apply legacy filters for backward compatibility.
		$width  = (int) apply_filters( 'et_pb_gallery_image_width', $width );
		$height = (int) apply_filters( 'et_pb_gallery_image_height', $height );

		$_attachments = get_posts( $attachments_args );
		$attachments  = [];

		foreach ( $_attachments as $key => $val ) {
			$attachments[ $key ]                  = $_attachments[ $key ];
			$attachments[ $key ]->image_alt_text  = get_post_meta( $val->ID, '_wp_attachment_image_alt', true );
			$attachments[ $key ]->image_src_full  = wp_get_attachment_image_src( $val->ID, 'full' );
			$attachments[ $key ]->image_src_thumb = wp_get_attachment_image_src( $val->ID, [ $width, $height ] );
		}

		return $attachments;
	}

	/**
	 * Get image attachment class.
	 *
	 * - wp-image-{$id}
	 *   Add `wp-image-{$id}` class to let `wp_filter_content_tags()` fill in missing
	 *   height and width attributes on the image. Those attributes are required to add
	 *   loading "lazy" attribute on the image. WP doesn't have specific method to only
	 *   generate this class. It's included in get_image_tag() to generate image tags.
	 *
	 * @since 4.6.4
	 *
	 * @param array   $attrs         All module attributes.
	 * @param string  $source_key    Key of image source.
	 * @param integer $attachment_id Attachment ID. Optional.
	 *
	 * @return string
	 */
	public static function get_image_attachment_class( $attrs, $source_key, $attachment_id = 0 ) {
		$attachment_class = '';

		// 1.a. Find attachment ID by URL. Skip if the source key is empty.
		if ( ! empty( $source_key ) ) {
			$attachment_src = et_()->array_get( $attrs, $source_key, '' );
			$attachment_id  = et_get_attachment_id_by_url( $attachment_src );
		}

		// 1.b. Generate attachment ID class.
		if ( $attachment_id > 0 ) {
			$attachment_class = "wp-image-{$attachment_id}";
		}

		return $attachment_class;
	}

	/**
	 * Loads `GalleryModule` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/gallery/';

		add_filter( 'divi_conversion_presets_attrs_map', [ GalleryPresetAttrsMap::class, 'get_map' ], 10, 2 );

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
