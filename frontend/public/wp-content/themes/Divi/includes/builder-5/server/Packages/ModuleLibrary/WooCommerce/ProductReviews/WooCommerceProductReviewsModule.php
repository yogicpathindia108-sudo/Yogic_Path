<?php
/**
 * Module Library: WooCommerceProductReviews Module
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\WooCommerce\ProductReviews;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\FrontEnd\Assets\DynamicAssetsUtils;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\IconLibrary\IconFont\Utils;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewUtils;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\FormField\FormFieldStyle;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;
use WP_Block_Type_Registry;
use WP_Block;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;

/**
 * WooCommerceProductReviewsModule class.
 *
 * This class implements the functionality of a call-to-action component
 * in a frontend application. It provides functions for rendering the
 * WooCommerceProductReviews module,
 * managing REST API endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class WooCommerceProductReviewsModule implements DependencyInterface {

	/**
	 * Render callback for the WooCommerceProductReviews module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ WooCommerceProductReviewsEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $content                     The block's content.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 * @param array          $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string The HTML rendered output of the WooCommerceProductReviews module.
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
	 * WooCommerceProductReviewsModule::render_callback( $attrs, $content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements, array $default_printed_style_attrs ): string {
		$product_id      = $attrs['content']['advanced']['product']['desktop']['value'] ?? WooCommerceUtils::get_default_product();
		$loop_product_id = WooCommerceUtils::get_loop_context_product_id( $attrs, $block );
		if ( $loop_product_id > 0 && 'current' === $product_id ) {
			$product_id = $loop_product_id;
		}
		$header_level = $attrs['reviewCount']['decoration']['font']['font']['desktop']['value']['headingLevel'] ?? 'h1';

		// enhancement(D5, Button Icons) The button icons needs a comprehensive update that is in line with D5 including support for customizable breakpoints.
		// https://github.com/elegantthemes/Divi/issues/44873.
		$button_icon     = $attrs['button']['decoration']['button']['desktop']['value']['icon']['settings'] ?? [];
		$has_button_icon = ! empty( $button_icon );

		$button_icon        = $has_button_icon
		? Utils::process_font_icon( $attrs['button']['decoration']['button']['desktop']['value']['icon']['settings'] ?? [] )
		: '';
		$button_icon_tablet = $has_button_icon
		? Utils::process_font_icon( $attrs['button']['decoration']['button']['tablet']['value']['icon']['settings'] ?? [] )
		: '';
		$button_icon_phone  = $has_button_icon
		? Utils::process_font_icon( $attrs['button']['decoration']['button']['phone']['value']['icon']['settings'] ?? [] )
		: '';

		$reviews_html = self::get_comments_content( $header_level, $product_id );

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		return Module::render(
			[
				// FE only.
				'orderIndex'               => $block->parsed_block['orderIndex'],
				'storeInstance'            => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'attrs'                    => $attrs,
				'elements'                 => $elements,
				'htmlAttrs'                => [
					'data-icon'        => esc_attr( $button_icon ),
					'data-icon-tablet' => esc_attr( $button_icon_tablet ),
					'data-icon-phone'  => esc_attr( $button_icon_phone ),
				],
				'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
				'id'                       => $block->parsed_block['id'],
				'name'                     => $block->block_type->name,
				'classnamesFunction'       => [ self::class, 'module_classnames' ],
				'moduleCategory'           => $block->block_type->category,
				'stylesComponent'          => [ self::class, 'module_styles' ],
				'scriptDataComponent'      => [ self::class, 'module_script_data' ],
				'parentAttrs'              => $parent->attrs ?? [],
				'parentId'                 => $parent->id ?? '',
				'parentName'               => $parent->blockName ?? '',
				'children'                 => [
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
							'children'          => $reviews_html,
						]
					),
				],
			]
		);
	}

	/**
	 * Renders review (comments) content.
	 *
	 * This function is responsible for rendering the review (comments) content.
	 *
	 * @since ??
	 *
	 * @param string $header_level The heading level.
	 * @param string $product_id The product ID.
	 *
	 * @return string The rendered review (comments) content.
	 */
	public static function get_comments_content( string $header_level, string $product_id ): string {
		$verified_product = WooCommerceUtils::get_product( $product_id );

		return self::get_reviews_markup(
			$verified_product,
			HTMLUtility::validate_heading_level( $header_level, 'h2' )
		);
	}

	/**
	 * Generate classnames for the module.
	 *
	 * This function generates classnames for the module based on the provided
	 * arguments. It is used in the `render_callback` function of the WooCommerceProductReviews module.
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
	 * WooCommerceProductReviewsModule::module_classnames($args);
	 * ```
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		// Get breakpoints states info for dynamic access to attributes.
		$breakpoints_states_info = MultiViewUtils::get_breakpoints_states_info();
		$default_breakpoint      = $breakpoints_states_info->default_breakpoint();
		$default_state           = $breakpoints_states_info->default_state();

		// Extract show/hide settings from attributes.
		$show_avatar = $attrs['elements']['advanced']['showAvatar'][ $default_breakpoint ][ $default_state ] ?? 'on';
		$show_reply  = $attrs['elements']['advanced']['showReply'][ $default_breakpoint ][ $default_state ] ?? 'on';
		$show_count  = $attrs['elements']['advanced']['showCount'][ $default_breakpoint ][ $default_state ] ?? 'on';
		$show_meta   = $attrs['elements']['advanced']['showMeta'][ $default_breakpoint ][ $default_state ] ?? 'on';
		$show_rating = $attrs['elements']['advanced']['showRating'][ $default_breakpoint ][ $default_state ] ?? 'on';

		// Always add comments module class.
		$classnames_instance->add( 'et_pb_comments_module' );

		// Remove default comments class if it exists.
		$classnames_instance->remove( 'et_pb_comments' );

		// Conditional class names based on show/hide settings.
		if ( 'off' === $show_avatar ) {
			$classnames_instance->add( 'et_pb_no_avatar' );
		}

		if ( 'off' === $show_reply ) {
			$classnames_instance->add( 'et_pb_no_reply_button' );
		}

		if ( 'off' === $show_count ) {
			$classnames_instance->add( 'et_pb_no_comments_count' );
		}

		if ( 'off' === $show_meta ) {
			$classnames_instance->add( 'et_pb_no_comments_meta' );
		}

		if ( 'off' === $show_rating ) {
			$classnames_instance->add( 'et_pb_no_comments_rating' );
		}

		// Text Options.
		$classnames_instance->add(
			TextClassnames::text_options_classnames(
				$attrs['module']['advanced']['text'] ?? [],
				[
					'color'       => true,
					'orientation' => true,
				]
			),
			true
		);

		// Add element classnames.
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
	 * WooCommerceProductReviews module script data.
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
	 * WooCommerceProductReviewsModule::module_script_data( $args );
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
				'attrName' => 'module',
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
							'et_pb_no_avatar' => $attrs['elements']['advanced']['showAvatar'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_no_reply_button' => $attrs['elements']['advanced']['showReply'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_no_comments_count' => $attrs['elements']['advanced']['showCount'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_no_comments_meta' => $attrs['elements']['advanced']['showMeta'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_no_comments_rating' => $attrs['elements']['advanced']['showRating'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'off' === $value ? 'add' : 'remove';
						},
					],
				],
			]
		);
	}


	/**
	 * Style declaration for WooCommerce Product Reviews rating alignment.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration function parameters.
	 *
	 * @return string CSS declarations.
	 */
	public static function rating_alignment_style_declaration( array $params ): string {
		$text_align = $params['attrValue']['textAlign'] ?? '';

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		if ( 'right' === $text_align ) {
			// Fixes right text alignment of review form star rating.
			// By default, WC adds text-indent -999em to hide the original rating number.
			// However, it causes an issue if the alignment is set to right position.
			// We should push it to the right side and hide the overflow.
			$style_declarations->add( 'overflow', 'hidden' );
			$style_declarations->add( 'text-indent', '999em' );
		}

		return $style_declarations->value();
	}

	/**
	 * Get calculated width based on letter spacing value.
	 * WooCommerce's .star-rating uses `em` based width on float layout;
	 * any additional width caused by letter-spacing makes the calculation incorrect;
	 * thus the `width: calc()` overwrite.
	 *
	 * @since ??
	 *
	 * @param string $value Letter spacing value.
	 *
	 * @return string Calculated width value.
	 */
	private static function _get_rating_width_style( string $value ): string {
		return "calc(5.4em + ({$value} * 4))";
	}

	/**
	 * Get margin properties & values based on current alignment status.
	 * Default star alignment is not controlled by standard text align system. It uses float to control
	 * how stars symbol will be displayed based on the percentage. It's not possible to convert it to
	 * simple text align. We have to use margin left & right to set the alignment.
	 *
	 * @since ??
	 *
	 * @param string $align     Alignment value.
	 * @param string $breakpoint Breakpoint name.
	 *
	 * @return array Margin properties.
	 */
	private static function _get_rating_alignment_style( string $align, string $breakpoint ): array {
		// Bail early if mode is desktop and alignment is left or justify.
		if ( 'desktop' === $breakpoint && in_array( $align, [ 'left', 'justify' ], true ) ) {
			return [];
		}

		$margin_properties = [
			'center' => [
				'left'  => 'auto',
				'right' => 'auto',
			],
			'right'  => [
				'left'  => 'auto',
				'right' => '0',
			],
		];

		// By default (left or justify), the margin will be left: inherit and right: auto.
		$margin_left  = $margin_properties[ $align ]['left'] ?? '0';
		$margin_right = $margin_properties[ $align ]['right'] ?? 'auto';

		return [
			'margin-left'  => $margin_left,
			'margin-right' => $margin_right,
		];
	}

	/**
	 * Style declaration for WooCommerce Product Reviews star rating.
	 *
	 * @since ??
	 *
	 * @param array $params Declaration function parameters.
	 *
	 * @return string CSS declarations.
	 */
	public static function star_rating_style_declaration( array $params ): string {
		$breakpoint     = $params['breakpoint'] ?? 'desktop';
		$letter_spacing = $params['attrValue']['letterSpacing'] ?? '';
		$text_align     = $params['attrValue']['textAlign'] ?? '';

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => [
					'margin-left'  => true,
					'margin-right' => true,
				],
			]
		);

		// Handle letter spacing width calculation.
		if ( ! empty( $letter_spacing ) ) {
			$width_style = self::_get_rating_width_style( $letter_spacing );
			$style_declarations->add( 'width', $width_style );
		}

		// Handle text alignment margins.
		if ( ! empty( $text_align ) ) {
			$alignment_styles = self::_get_rating_alignment_style( $text_align, $breakpoint );
			foreach ( $alignment_styles as $property => $value ) {
				$style_declarations->add( $property, $value );
			}
		}

		return $style_declarations->value();
	}

	/**
	 * WooCommerceProductReviews Module's style components.
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

		// Extract the order class.
		$order_class = $args['orderClass'] ?? '';

		$is_inside_sticky_module   = $elements->get_is_inside_sticky_module();
		$sticky_parent_order_class = $elements->get_sticky_parent_order_class();

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
											'selector' => "{$order_class}",
											'attr'     => $attrs['module']['advanced']['text'] ?? [],
											'propertySelectors' => [
												'textShadow' => [
													'desktop' => [
														'value' => [
															'text-shadow' => "{$order_class} p, {$order_class} .comment_postinfo, {$order_class} .page_title, {$order_class} .comment-reply-title",
														],
													],
												],
											],
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
					// Image.
					$elements->style(
						[
							'attrName'   => 'image',
							'styleProps' => [
								'fit'    => [
									'selector' => "{$order_class}.et_pb_wc_reviews #reviews #comments ol.commentlist li img.avatar",
								],
								'sizing' => [
									'propertySelectors' => [
										'desktop' => [
											'value' => [
												'aspect-ratio' => "{$order_class}.et_pb_wc_reviews #reviews #comments ol.commentlist li img.avatar",
											],
										],
									],
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
									"{$order_class} #commentform textarea",
									"{$order_class} #commentform input[type='text']",
									"{$order_class} #commentform input[type='email']",
									"{$order_class} #commentform input[type='url']",
								]
							),
							'attr'                   => $attrs['field'] ?? [],
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
							'propertySelectors'      => [
								'spacing' => [
									'desktop' => [
										'value' => [
											'margin' => implode(
												', ',
												[
													"{$order_class} #review_form #respond .comment-form-comment",
													"{$order_class} #review_form #respond .comment-form-author",
													"{$order_class} #review_form #respond .comment-form-email",
													"{$order_class} #review_form #respond .comment-form-url",
												]
											),
										],
									],
								],
							],
						]
					),
					// Button.
					$elements->style(
						[
							'attrName' => 'button',
						]
					),
					// Comment.
					$elements->style(
						[
							'attrName' => 'comment',
						]
					),
					// Form Title.
					$elements->style(
						[
							'attrName' => 'formTitle',
						]
					),
					// Meta.
					$elements->style(
						[
							'attrName' => 'meta',
						]
					),
					// Review Count.
					$elements->style(
						[
							'attrName' => 'reviewCount',
						]
					),
					// Star Rating.
					$elements->style(
						[
							'attrName'   => 'starRating',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} p.stars a",
											'attr'     => $attrs['starRating']['decoration']['font']['font'] ?? [],
											'declarationFunction' => [ self::class, 'rating_alignment_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$order_class} .star-rating",
											'attr'     => $attrs['starRating']['decoration']['font']['font'] ?? [],
											'declarationFunction' => [ self::class, 'star_rating_style_declaration' ],
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
	 * Get the custom CSS fields for the Divi WooCommerceProductReviews module.
	 *
	 * This function retrieves the custom CSS fields defined for the Divi WooCommerceProductReviews module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /api/js/divi-module-library/functions/generateDefaultAttrs cssFields}
	 * located in `@divi/module-library`. Note that this function does not have
	 * a `label` property on each array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi WooCommerceProductReviews module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = CustomCssTrait::custom_css();
	 * // Returns an array of custom CSS fields for the WooCommerceProductReviews module.
	 * ```
	 */
	public static function custom_css(): array {
		$registered_block = WP_Block_Type_Registry::get_instance()->get_registered( 'divi/woocommerce-product-reviews' );

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
	 * Loads `WooCommerceProductReviewsModule` and registers Front-End render callback and REST API Endpoints.
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

		// Add a filter for processing dynamic attribute defaults.
		add_filter(
			'divi_module_library_module_default_attributes_divi/woocommerce-product-reviews',
			[ WooCommerceUtils::class, 'process_dynamic_attr_defaults' ],
			10,
			2
		);

		$module_json_folder_path = dirname( __DIR__, 5 ) . '/visual-builder/packages/module-library/src/components/woocommerce/product-reviews/';

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
	 * Gets the Reviews markup.
	 *
	 * This function returns the HTML for the product reviews markup based on the provided arguments.
	 * This includes the Reviews and the Review comment form.
	 *
	 * @since ??
	 *
	 * @param \WC_Product|false $product        WooCommerce Product.
	 * @param string            $header_level   Heading level.
	 * @param bool              $is_api_request Whether this is for a REST API request.
	 *                                          Should be set to TRUE when used in REST API call for proper results.
	 *
	 * @return string The rendered product reviews markup HTML.
	 */
	public static function get_reviews_markup( $product, string $header_level, bool $is_api_request = false ): string {
		if ( ! ( $product instanceof \WC_Product ) ) {
			return '';
		}

		if ( ! comments_open( $product->get_id() ) ) {
			return '';
		}

		$reviews_title = WooCommerceUtils::get_reviews_title( $product );
		// The product can be changed using the Product filter in the Settings modal.
		// Hence we provide the product ID to fetch data based on the selected product.
		$reviews         = get_comments(
			[
				'post_id' => $product->get_id(),
				'status'  => 'approve',
			]
		);
		$total_pages     = get_comment_pages_count( $reviews );
		$reviews_content = wp_list_comments(
			[
				'callback' => 'woocommerce_comments',
				'echo'     => false,
			],
			$reviews
		);

		// Provide the `$total_pages` var, otherwise `$pagination` will always be empty.
		if ( $is_api_request ) {
			$page = get_query_var( 'cpage' );
			if ( ! $page ) {
				$page = 1;
			}

			$args = [
				'base'         => add_query_arg( 'cpage', '%#%' ),
				'format'       => '',
				'total'        => $total_pages,
				'current'      => $page,
				'echo'         => false,
				'add_fragment' => '#comments',
				'type'         => 'list',
			];

			global $wp_rewrite;

			if ( $wp_rewrite->using_permalinks() ) {
				$args['base'] = user_trailingslashit( trailingslashit( get_permalink() ) . $wp_rewrite->comments_pagination_base . '-%#%', 'commentpaged' );
			}

			$pagination = paginate_links( $args );
		} else {
			$pagination = paginate_comments_links(
				[
					'echo'  => false,
					'type'  => 'list',
					'total' => $total_pages,
				]
			);
		}

		// Pass $product to unify the flow of data.
		// Note in D4 this call also passes the $reviews variable to the function, but the function definition does not accept it.
		$reviews_comment_form = WooCommerceUtils::get_reviews_comment_form( $product );

		return sprintf(
			'
			<div id="reviews" class="woocommerce-Reviews">
				<div id="comments">
					<%3$s class="woocommerce-Reviews-title">
						%1$s
					</%3$s>
					<ol class="commentlist">
						%2$s
					</ol>
					<nav class="woocommerce-pagination">
						%4$s
					</nav>
				</div>
				%5$s
				<div class="clear"></div>
			</div>
			',
			/* 1$s */
			$reviews_title,
			/* 2$s */
			$reviews_content,
			/* 3$s */
			$header_level,
			/* 4$s */
			$pagination,
			/* 5$s */
			$reviews_comment_form
		);
	}

	/**
	 * Retrieves the product reviews markup HTML for a given set of arguments.
	 *
	 * This function returns the HTML for the product reviews markup based on the provided arguments.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     Optional. An array of arguments for rendering the product reviews.
	 *
	 *     @type string $product      Optional. The product ID. Default 'current'.
	 *     @type string $header_level Optional. The heading level. Default 'h2'.
	 * }
	 *
	 * @param array $conditional_tags {
	 *     Optional. An array of conditional tags.
	 *
	 *     @type string $is_api_request Whether the request is an AJAX request.
	 * }
	 *
	 * @param array $current_page {
	 *     Optional. An array of current page args.
	 *
	 *     @type string $id Optional. The current page ID.
	 * }
	 *
	 * @return string The rendered product reviews markup HTML.
	 *
	 * @example:
	 * ```php
	 * $reviews = WooCommerceProductReviewsModule::get_reviews_html();
	 * // Returns the product reviews for the current product.
	 *
	 * $reviews = WooCommerceProductReviewsModule::get_reviews_html( [ 'product' => 123 ] );
	 * // Returns the product reviews for the product with ID 123.
	 * ```
	 */
	public static function get_reviews_html( array $args = [], array $conditional_tags = [], array $current_page = [] ): string {
		// Needed for product post-type.
		if ( ! isset( $args['product'] ) ) {
			$args['product'] = WooCommerceUtils::get_product_id( 'current' );
		}

		// Needed for product post-type.
		if ( ! isset( $args['header_level'] ) ) {
			$args['header_level'] = 'h2';
		}

		$is_tb = Conditions::is_tb_enabled();

		if ( $is_tb || is_et_pb_preview() ) {
			global $product;

			WooCommerceUtils::set_global_objects_for_theme_builder();
		} else {
			$product = WooCommerceUtils::get_product( $args['product'] );
		}

		if ( ! ( $product instanceof \WC_Product ) ) {
			return '';
		}

		$reviews_markup = self::get_reviews_markup( $product, $args['header_level'], true );

		if ( $is_tb || is_et_pb_preview() ) {
			WooCommerceUtils::reset_global_objects_for_theme_builder();
		}

		return $reviews_markup;
	}
}
