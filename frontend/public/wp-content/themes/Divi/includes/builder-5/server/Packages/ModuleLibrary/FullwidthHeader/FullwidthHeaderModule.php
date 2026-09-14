<?php
/**
 * Module Library: Fullwidth Header Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\FullwidthHeader;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\IconLibrary\IconFont\Utils;
use ET\Builder\Packages\Module\Layout\Components\Classnames;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\ModuleLibrary\FullwidthHeader\FullwidthHeaderPresetAttrsMap;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\GlobalData\GlobalPresetItemGroupAttrNameResolver;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\GlobalData\GlobalPresetItemGroupAttrNameResolved;
use ET\Builder\Framework\Utility\ArrayUtility;

use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use Exception;
use WP_Block_Type_Registry;
use WP_Block;

/**
 * FullwidthHeaderModule class.
 *
 * This class implements the functionality of a fullwidth header component in a
 * frontend application. It provides functions for rendering the fullwidth header,
 * managing REST API endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class FullwidthHeaderModule implements DependencyInterface {

	/**
	 * Generate classnames for the module.
	 *
	 * This function generates classnames for the module based on the provided
	 * arguments. It is used in the `render_callback` function of the Fullwidth Header module.
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
	 * FullwidthHeaderModule::module_classnames($args);
	 * ```
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		$header_fullscreen = $attrs['module']['advanced']['headerFullscreen']['desktop']['value'] ?? 'off';
		$header_image      = $attrs['image']['innerContent']['desktop']['value']['src'] ?? '';

		$classnames_instance->add( 'et_pb_fullscreen', 'on' === $header_fullscreen );
		$classnames_instance->add( 'et_pb_header_with_image', ! empty( $header_image ) );

		// Text Options.
		$classnames_instance->add( TextClassnames::text_options_classnames( $attrs['module']['advanced']['text'] ?? [] ), true );

		// Module.
		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					// phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
					// TODO feat(D5, Module Attribute Refactor) Once link is merged as part of options property, remove this.
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
	 * Fullwidth Header module script data.
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
	 * FullwidthHeaderModule::module_script_data( $args );
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

		MultiViewScriptData::set(
			[
				'id'            => $id,
				'name'          => $name,
				'storeInstance' => $store_instance,
				'hoverSelector' => $selector,
				'setAttrs'      => [
					[
						'selector'      => "$selector .header-logo",
						'data'          => [
							'src'   => $attrs['logo']['innerContent'] ?? [],
							'alt'   => $attrs['logo']['innerContent'] ?? [],
							'title' => $attrs['logo']['innerContent'] ?? [],
						],
						'valueResolver' => function ( $value, $resolver_args ) {
							return $value[ $resolver_args['attrName'] ] ?? '';
						},
						'tag'           => 'img',
					],
					[
						'selector'      => "$selector .header-image img",
						'data'          => [
							'src'   => $attrs['image']['innerContent'] ?? [],
							'alt'   => $attrs['image']['innerContent'] ?? [],
							'title' => $attrs['image']['innerContent'] ?? [],
						],
						'valueResolver' => function ( $value, $resolver_args ) {
							return $value[ $resolver_args['attrName'] ] ?? '';
						},
						'tag'           => 'img',
					],
				],
				'setClassName'  => [
					[
						'selector'      => "$selector .header-image",
						'data'          => [
							'et_pb_header_with_image' => $attrs['image']['innerContent'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return ! empty( $value['src'] ) ? 'add' : 'remove';
						},
					],
				],
				'setContent'    => [
					[
						'selector'      => "$selector .scroll-down-container .scroll-down.et-pb-icon",
						'data'          => $attrs['scrollDown']['decoration']['icon'] ?? [],
						'valueResolver' => function ( $value ) {
							return Utils::process_font_icon( $value, true ) ?? '';
						},
						'sanitizer'     => 'esc_html',
					],
				],
			]
		);
	}

	/**
	 * Render callback for the Fullwidth Header module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ FullwidthHeaderEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs    Block attributes that were saved by Divi Builder.
	 * @param string         $child_modules_content  The block's child modules content.
	 * @param WP_Block       $block    Parsed block object that is being rendered.
	 * @param ModuleElements $elements An instance of the ModuleElements class.
	 * @param array          $default_printed_style_attrs Default printed style attributes.
	 *
	 * @throws Exception If the block is not registered.
	 *
	 * @return string The HTML rendered output of the Fullwidth Header module.
	 *
	 * @example
	 * ```php
	 * $attrs = [
	 *   'attrName' => 'value',
	 *   //...
	 * ];
	 * $child_modules_content = 'The child modules content.';
	 * $block = new WP_Block();
	 * $elements = new ModuleElements();
	 *
	 * FullwidthHeaderModule::render_callback( $attrs, $child_modules_content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $child_modules_content, WP_Block $block, ModuleElements $elements, array $default_printed_style_attrs ): string {
		$children_ids = ChildrenUtils::extract_children_ids( $block );

		// Logo.
		$logo_image = $elements->render(
			[
				'attrName' => 'logo',
			]
		);

		// Title.
		$title = $elements->render(
			[
				'attrName' => 'title',
			]
		);

		// Subhead.
		$subhead = $elements->render(
			[
				'attrName' => 'subhead',
			]
		);

		// Content.
		$content = $elements->render(
			[
				'attrName' => 'content',
			]
		);

		// Button one.
		$button_one = $elements->render(
			[
				'attrName'    => 'buttonOne',
				'elementType' => 'button',
				'tagName'     => 'a',
			]
		);

		// Button two.
		$button_two = $elements->render(
			[
				'attrName'    => 'buttonTwo',
				'elementType' => 'button',
				'tagName'     => 'a',
			]
		);

		// Button Wrapper.
		$button_wrapper = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => 'et_pb_header_button_wrapper',
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $button_one . $button_two,
			]
		);

		// Layout classes for header-content container.
		// These classes are merged with the existing 'header-content' class.
		$layout_display_value   = $attrs['module']['decoration']['layout']['desktop']['value']['display'] ?? 'flex';
		$header_content_classes = HTMLUtility::classnames(
			'header-content',
			[
				'et_flex_module' => 'flex' === $layout_display_value,
				'et_grid_module' => 'grid' === $layout_display_value,
			]
		);

		// Header Content.
		$header_content = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => $header_content_classes,
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $logo_image . $title . $subhead . $content . $button_wrapper . $child_modules_content,
			]
		);

		// Header Content Container.
		$content_orientation      = $attrs['content']['advanced']['orientation']['desktop']['value'] ?? 'center';
		$header_content_container = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => 'header-content-container ' . $content_orientation,
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $header_content,
			]
		);

		// Header Image.
		$has_header_image_src = ModuleUtils::has_value(
			$attrs['image']['innerContent'] ?? [],
			[
				'valueResolver' => function ( $value ) {
					return ! empty( $value['src'] );
				},
			]
		);

		// Header Image Wrapper.
		$header_image_wrapper = $has_header_image_src ? HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => 'header-image',
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $elements->render(
					[
						'attrName' => 'image',
					]
				),
			]
		) : '';

		// Header Image Container.
		$image_orientation      = $attrs['image']['advanced']['orientation']['desktop']['value'] ?? 'center';
		$header_image_container = $has_header_image_src ? HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => 'header-image-container ' . $image_orientation,
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $header_image_wrapper,
			]
		) : '';

		// Container.
		$text_orientation = $attrs['module']['advanced']['text']['text']['desktop']['value']['orientation'] ?? 'left';
		$header_container = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => 'et_pb_fullwidth_header_container ' . $text_orientation,
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $header_content_container . $header_image_container,
			]
		);

		// Header overlay.
		$header_overlay = HTMLUtility::render(
			[
				'tag'        => 'div',
				'attributes' => [
					'class' => 'et_pb_fullwidth_header_overlay',
				],
			]
		);

		// Scroll Down Icon.
		$scroll_down_show       = $attrs['scrollDown']['decoration']['icon']['desktop']['value']['show'] ?? 'off';
		$scroll_down_icon_value = $attrs['scrollDown']['decoration']['icon']['desktop']['value'] ?? [];
		$scroll_down_icon       = 'on' === $scroll_down_show ? HTMLUtility::render(
			[
				'tag'               => 'span',
				'attributes'        => [
					'class' => 'scroll-down et-pb-icon',
				],
				'childrenSanitizer' => 'esc_html',
				'children'          => Utils::process_font_icon( $scroll_down_icon_value, true ) ?? '',
			]
		) : '';

		// Scroll Down Tablet Icon.
		$scroll_down_tablet_icon_value = $attrs['scrollDown']['decoration']['icon']['tablet']['value'] ?? [];
		$scroll_down_tablet_icon       = 'on' === $scroll_down_show && ! empty( $scroll_down_tablet_icon_value ) ? HTMLUtility::render(
			[
				'tag'               => 'span',
				'attributes'        => [
					'class' => 'scroll-down-tablet et-pb-icon',
				],
				'childrenSanitizer' => 'esc_html',
				'children'          => Utils::process_font_icon( $scroll_down_tablet_icon_value, true ) ?? '',
			]
		) : '';

		// Scroll Down Phone Wide Icon.
		$scroll_down_phone_wide_icon_value = $attrs['scrollDown']['decoration']['icon']['phoneWide']['value'] ?? [];
		$scroll_down_phone_wide_icon       = 'on' === $scroll_down_show && ! empty( $scroll_down_phone_wide_icon_value ) ? HTMLUtility::render(
			[
				'tag'               => 'span',
				'attributes'        => [
					'class' => 'scroll-down-phone-wide et-pb-icon',
				],
				'childrenSanitizer' => 'esc_html',
				'children'          => Utils::process_font_icon( $scroll_down_phone_wide_icon_value, true ) ?? '',
			]
		) : '';

		// Scroll Down Phone Icon.
		$scroll_down_phone_icon_value = $attrs['scrollDown']['decoration']['icon']['phone']['value'] ?? [];
		$scroll_down_phone_icon       = 'on' === $scroll_down_show && ! empty( $scroll_down_phone_icon_value ) ? HTMLUtility::render(
			[
				'tag'               => 'span',
				'attributes'        => [
					'class' => 'scroll-down-phone et-pb-icon',
				],
				'childrenSanitizer' => 'esc_html',
				'children'          => Utils::process_font_icon( $scroll_down_phone_icon_value, true ) ?? '',
			]
		) : '';

		// Scroll Down Container.
		$icon_classnames_instance = new Classnames();
		$icon_classnames_instance->add( 'scroll-down-container', true );
		$icon_classnames_instance->add( 'scroll-down-container-tablet', ! empty( $scroll_down_tablet_icon_value ) );
		$icon_classnames_instance->add( 'scroll-down-container-phone-wide', ! empty( $scroll_down_phone_wide_icon_value ) );
		$icon_classnames_instance->add( 'scroll-down-container-phone', ! empty( $scroll_down_phone_icon_value ) );
		$scroll_down_container = 'on' === $scroll_down_show ? HTMLUtility::render(
			[
				'tag'               => 'a',
				'attributes'        => [
					'class' => $icon_classnames_instance->value(),
					'href'  => '#',
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $scroll_down_icon . $scroll_down_tablet_icon . $scroll_down_phone_wide_icon . $scroll_down_phone_icon,
			]
		) : '';

		// Header Scroll Down.
		$header_scroll_down = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => 'et_pb_fullwidth_header_scroll',
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $scroll_down_container,
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
				'elements'                 => $elements,
				'id'                       => $block->parsed_block['id'],
				'name'                     => $block->block_type->name,
				'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
				'classnamesFunction'       => [ self::class, 'module_classnames' ],
				'moduleCategory'           => $block->block_type->category,
				'stylesComponent'          => [ self::class, 'module_styles' ],
				'scriptDataComponent'      => [ self::class, 'module_script_data' ],
				'parentAttrs'              => $parent->attrs ?? [],
				'parentId'                 => $parent->id ?? '',
				'parentName'               => $parent->blockName ?? '',
				'childrenIds'              => $children_ids,
				'tag'                      => 'section',
				'children'                 => $elements->style_components(
					[
						'attrName' => 'module',
					]
				) . $header_container . $header_overlay . $header_scroll_down,
			]
		);
	}

	/**
	 * Get the custom CSS fields for the Divi Fullwidth Header module.
	 *
	 * This function retrieves the custom CSS fields defined for the Divi fullwidth header module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /api/js/divi-module-library/functions/generateDefaultAttrs cssFields}
	 * located in `@divi/module-library`. Note that this function does not have
	 * a `label` property on each array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi fullwidth header module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = CustomCssTrait::custom_css();
	 * // Returns an array of custom CSS fields for the fullwidth header module.
	 * ```
	 */
	public static function custom_css(): array {
		return WP_Block_Type_Registry::get_instance()->get_registered( 'divi/fullwidth-header' )->customCssFields;
	}

	/**
	 * Scroll Down Style Declaration.
	 *
	 * This function is used to declare the initial style for the "scroll down" icon.
	 *
	 * @param array $params An array of arguments.
	 *
	 * @return string The string containing the CSS style declarations for the icon.
	 *
	 * @since ??
	 *
	 * @example: Get the scroll down style declarations.
	 * ```php
	 * $params = [
	 *     'attrValue' => [
	 *         'show'  => 'on',
	 *         'color' => '#ff0000',
	 *         'size'  => '16px',
	 *     ],
	 *     'attr'      => [
	 *         'desktop' => [
	 *             'value' => [
	 *                 'show'  => 'on',
	 *                 'color' => '#ff0000',
	 *                 'size'  => '16px',
	 *             ],
	 *         ],
	 *         'tablet'  => [
	 *             'value' => [
	 *                 'color' => '#00ff00',
	 *                 'size'  => '20px',
	 *             ],
	 *         ],
	 *     ],
	 *     'breakpoint' => 'tablet',
	 *     'state'      => 'value',
	 * ];
	 * $style = FullwidthHeaderModule::scroll_down_style_declaration( $params );
	 * // Result: 'color: #00ff00; font-size: 20px;'
	 * ```
	 */
	public static function scroll_down_style_declaration( array $params ): string {
		$attr_value = $params['attrValue'] ?? [];
		$attr       = $params['attr'] ?? [];
		$breakpoint = $params['breakpoint'] ?? 'desktop';
		$state      = $params['state'] ?? 'value';

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => true,
			]
		);

		// Get merged attribute value that includes non-responsive fields (like 'show') from desktop.
		// This ensures that 'show' is available for all breakpoints, not just desktop.
		$merged_attr_value = $attr_value;

		if ( ! empty( $attr ) && ! empty( $breakpoint ) ) {
			// For desktop (base breakpoint), use 'get' mode to prevent inheriting from ultrawide/widescreen.
			// For other breakpoints, use 'getAndInheritAll' to merge non-responsive fields from desktop.
			$mode = 'desktop' === $breakpoint ? 'get' : 'getAndInheritAll';

			$merged_attr_value = ModuleUtils::get_attr_value(
				[
					'attr'            => $attr,
					'breakpoint'      => $breakpoint,
					'state'           => $state,
					'mode'            => $mode,
					'defaultValue'    => [],
					'breakpointNames' => Breakpoint::get_all_breakpoint_names(),
					'baseBreakpoint'  => 'desktop',
				]
			);
		}

		$show = $merged_attr_value['show'] ?? null;

		// Hover/state data does not include `show`, so pull it from value state.
		if ( 'value' !== $state && ( null === $show || '' === $show ) && ! empty( $attr ) && ! empty( $breakpoint ) ) {
			$value_attr_value = ModuleUtils::get_attr_value(
				[
					'attr'            => $attr,
					'breakpoint'      => $breakpoint,
					'state'           => 'value',
					'mode'            => $mode,
					'defaultValue'    => [],
					'breakpointNames' => Breakpoint::get_all_breakpoint_names(),
					'baseBreakpoint'  => 'desktop',
				]
			);

			$show = $value_attr_value['show'] ?? null;
		}

		$color = $merged_attr_value['color'] ?? '';
		$size  = $merged_attr_value['size'] ?? '';

		if ( null === $show || '' === $show ) {
			// When building transition properties, the attr may be missing; infer visibility from values.
			$show = ( ! empty( $color ) || ! empty( $size ) ) ? 'on' : 'off';
		}

		if ( 'on' === $show && ! empty( $color ) ) {
			$style_declarations->add( 'color', $color );
		}

		if ( 'on' === $show && ! empty( $size ) ) {
			$style_declarations->add( 'font-size', $size );
		}

		return $style_declarations->value();
	}

	/**
	 * Scroll Down icon visibility declaration.
	 *
	 * Controls icon visibility for phone wide and phone breakpoints to prevent
	 * multiple icons showing at the same breakpoint.
	 *
	 * @since ??
	 *
	 * @param array  $params    Style declaration params.
	 * @param array  $attrs     Module attrs.
	 * @param string $icon_type Icon type: base|tablet|phoneWide|phone.
	 *
	 * @return string
	 */
	public static function scroll_down_visibility_declaration( array $params, array $attrs, string $icon_type ): string {
		$breakpoint = $params['breakpoint'] ?? 'desktop';

		$scroll_down_icon_attrs = $attrs['scrollDown']['decoration']['icon'] ?? [];

		$has_tablet_icon     = ! empty( $scroll_down_icon_attrs['tablet']['value'] ?? [] );
		$has_phone_wide_icon = ! empty( $scroll_down_icon_attrs['phoneWide']['value'] ?? [] );
		$has_phone_icon      = ! empty( $scroll_down_icon_attrs['phone']['value'] ?? [] );

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		if ( 'base' === $icon_type ) {
			if ( 'tablet' === $breakpoint && $has_tablet_icon ) {
				$style_declarations->add( 'display', 'none' );
			}

			if ( 'phoneWide' === $breakpoint && $has_phone_wide_icon ) {
				$style_declarations->add( 'display', 'none' );
			}

			if ( 'phone' === $breakpoint && $has_phone_icon ) {
				$style_declarations->add( 'display', 'none' );
			}
		} else {
			$should_show = false;

			if ( 'tablet' === $icon_type ) {
				$should_show = 'tablet' === $breakpoint && $has_tablet_icon;
			} elseif ( 'phoneWide' === $icon_type ) {
				$should_show = 'phoneWide' === $breakpoint && $has_phone_wide_icon;
			} elseif ( 'phone' === $icon_type ) {
				$should_show = 'phone' === $breakpoint && $has_phone_icon;
			}

			$style_declarations->add( 'display', $should_show ? 'inline-block' : 'none' );
		}

		return $style_declarations->value();
	}


	/**
	 * Fullwidth Header Module's style components.
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

		$heading_level = $attrs['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] ?? 'h1';

		$default_printed_style_attrs = $args['defaultPrintedStyleAttrs']['module']['decoration'] ?? [];

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
								'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
								'disabledOn'               => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
								'advancedStyles'           => [
									[
										'componentName' => 'divi/text',
										'props'         => [
											'selector'    => implode(
												', ',
												[
													"{$args['orderClass']} .et_pb_module_header",
													"{$args['orderClass']} .et_pb_fullwidth_header_subhead",
													"{$args['orderClass']} p",
													"{$args['orderClass']} .et_pb_button",
												]
											),
											'attr'        => $attrs['module']['advanced']['text'] ?? [],
											'propertySelectors' => [
												'textShadow' => [
													'desktop' => [
														'value' => [
															'text-shadow' => "{$args['orderClass']} .header-content",
														],
													],
												],
											],
											'orientation' => false,
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
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$args['orderClass']}.et_pb_fullwidth_header .et_pb_fullwidth_header_scroll a .et-pb-icon",
											'attr'     => $attrs['scrollDown']['decoration']['icon'] ?? [],
											'declarationFunction' => [ self::class, 'scroll_down_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$args['orderClass']}.et_pb_fullwidth_header .scroll-down-container .scroll-down",
											'attr'     => $attrs['scrollDown']['decoration']['icon'] ?? [],
											'declarationFunction' => function ( $params ) use ( $attrs ) {
												return self::scroll_down_visibility_declaration( $params, $attrs, 'base' );
											},
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$args['orderClass']}.et_pb_fullwidth_header .scroll-down-container .scroll-down-tablet",
											'attr'     => $attrs['scrollDown']['decoration']['icon'] ?? [],
											'declarationFunction' => function ( $params ) use ( $attrs ) {
												return self::scroll_down_visibility_declaration( $params, $attrs, 'tablet' );
											},
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$args['orderClass']}.et_pb_fullwidth_header .scroll-down-container .scroll-down-phone-wide",
											'attr'     => $attrs['scrollDown']['decoration']['icon'] ?? [],
											'declarationFunction' => function ( $params ) use ( $attrs ) {
												return self::scroll_down_visibility_declaration( $params, $attrs, 'phoneWide' );
											},
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$args['orderClass']}.et_pb_fullwidth_header .scroll-down-container .scroll-down-phone",
											'attr'     => $attrs['scrollDown']['decoration']['icon'] ?? [],
											'declarationFunction' => function ( $params ) use ( $attrs ) {
												return self::scroll_down_visibility_declaration( $params, $attrs, 'phone' );
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
							'attrName' => 'image',
						]
					),

					// Title.
					$elements->style(
						[
							'attrName'   => 'title',
							'styleProps' => [
								'selector' => 'h1' === $heading_level ? "{$args['orderClass']}.et_pb_fullwidth_header .header-content h1" : "{$args['orderClass']}.et_pb_fullwidth_header .header-content {$heading_level}.et_pb_module_header",
							],
						]
					),
					// Content.
					$elements->style(
						[
							'attrName'   => 'content',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$args['orderClass']}.et_pb_fullwidth_header .et_pb_fullwidth_header_container .header-content",
											'attr'     => $attrs['content']['advanced']['maxWidth'] ?? [],
											'property' => 'max-width',
										],
									],
								],
							],
						]
					),
					// Sub Heading.
					$elements->style(
						[
							'attrName' => 'subhead',
						]
					),
					$elements->style(
						[
							'attrName' => 'buttonOne',
						]
					),
					$elements->style(
						[
							'attrName' => 'buttonTwo',
						]
					),
					$elements->style(
						[
							'attrName' => 'overlay',
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
	 * Loads `FullwidthHeaderModule` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/fullwidth-header/';

		add_filter( 'divi_conversion_presets_attrs_map', [ FullwidthHeaderPresetAttrsMap::class, 'get_map' ], 10, 2 );

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
	 * Resolve the group preset attribute name for the Fullwidth Header module.
	 *
	 * @param GlobalPresetItemGroupAttrNameResolved $attr_name_to_resolve The attribute name to be resolved.
	 * @param array                                 $params               The filter parameters.
	 *
	 * @return GlobalPresetItemGroupAttrNameResolved The resolved attribute name.
	 */
	public static function option_group_preset_resolver_attr_name( $attr_name_to_resolve, array $params ): ?GlobalPresetItemGroupAttrNameResolved {
		// Bydefault, $attr_name_to_resolve is a null value.
		// If it is not null, it means that the attribute name is already resolved.
		// In this case, we return the resolved attribute name.
		if ( null !== $attr_name_to_resolve ) {
			return $attr_name_to_resolve;
		}

		if ( $params['moduleName'] !== $params['dataModuleName'] ) {
			if ( 'divi/fullwidth-header' === $params['moduleName'] ) {
				if ( 'content.advanced.maxWidth' === $params['attrName'] ) {
					$attr_names_to_pairs = GlobalPresetItemGroupAttrNameResolver::get_attr_names_by_group( $params['dataModuleName'], $params['dataGroupId'] );

					$find_attr_name_matched = function () use ( $attr_names_to_pairs ) {
						if ( count( $attr_names_to_pairs ) > 1 ) {
							return ArrayUtility::find(
								$attr_names_to_pairs,
								function ( $attr_name ) {
									return 'module.decoration.sizing' !== $attr_name && GlobalPresetItemGroupAttrNameResolver::is_attr_name_suffix_matched(
										$attr_name,
										'content.decoration.sizing'
									);
								}
							);
						}

						return $attr_names_to_pairs[0];
					};

					$attr_name_match = $find_attr_name_matched();

					return new GlobalPresetItemGroupAttrNameResolved(
						[
							'attrName'     => $attr_name_match,
							'attrSubName'  => 'maxWidth',
							'attrCallback' => function ( array $attrs ) use ( $attr_name_match ): array {
								$sizing_attrs = ArrayUtility::get_value( $attrs, $attr_name_match );

								if ( empty( $sizing_attrs ) ) {
									return [];
								}

								$attr = [];

								foreach ( $sizing_attrs as $breakpoint => $states_value ) {
									foreach ( $states_value as $state => $value ) {
										if ( isset( $value['maxWidth'] ) ) {
											if ( ! isset( $attr[ $breakpoint ] ) ) {
												$attr[ $breakpoint ] = [];
											}

											$attr[ $breakpoint ][ $state ] = $value['maxWidth'];
										}
									}
								}

								return $attr;
							},
						]
					);
				}
			}

			if ( 'divi/fullwidth-header' === $params['dataModuleName'] ) {
				if ( 'module.decoration.sizing' !== $params['attrName'] && strpos( $params['attrName'], '.decoration.sizing' ) ) {
					$attr_name_match = 'content.advanced.maxWidth';

					return new GlobalPresetItemGroupAttrNameResolved(
						[
							'attrName'     => $attr_name_match,
							'attrSubName'  => '',
							'attrCallback' => function ( array $attrs ) use ( $attr_name_match ): array {
								$sizing_attrs = ArrayUtility::get_value( $attrs, $attr_name_match );

								if ( empty( $sizing_attrs ) ) {
									return [];
								}

								$attr = [];

								foreach ( $sizing_attrs as $breakpoint => $states_value ) {
									foreach ( $states_value as $state => $value ) {
										if ( ! isset( $attr[ $breakpoint ] ) ) {
											$attr[ $breakpoint ] = [];
										}

										if ( ! isset( $attr[ $breakpoint ][ $state ] ) ) {
											$attr[ $breakpoint ][ $state ] = [];
										}

										$attr[ $breakpoint ][ $state ]['maxWidth'] = $value;
									}
								}

								return $attr;
							},
						]
					);
				}
			}
		}

		return $attr_name_to_resolve;
	}
}
