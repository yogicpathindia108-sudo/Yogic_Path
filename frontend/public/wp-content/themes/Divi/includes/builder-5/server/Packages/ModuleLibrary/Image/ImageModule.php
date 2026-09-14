<?php
/**
 * Module Library: Image Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Image;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\Breakpoint\Breakpoint;
use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\IconLibrary\IconFont\Utils as IconFontUtils;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\Image\Styles\Sizing\SizingStyle;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ImageUtils;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use Exception;
use WP_Block;
use ET\Builder\Packages\GlobalData\GlobalPresetItemGroupAttrNameResolved;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use ET\Builder\Packages\StyleLibrary\Utils\Utils;
use WP_Block_Type_Registry;

/**
 * ImageModule class.
 *
 * This class implements the functionality of an icon component in a frontend
 * application. It provides functions for rendering the icon, managing REST API
 * endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class ImageModule implements DependencyInterface {

	/**
	 * Generate classnames for the module.
	 *
	 * This function generates classnames for the module based on the provided
	 * arguments. It is used in the `render_callback` function of the Image module.
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
	 * ImageModule::module_classnames($args);
	 * ```
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];
		$parent_attrs        = $args['parentAttrs'] ?? null;

		$show_bottom_space        = $attrs['module']['advanced']['spacing']['desktop']['value']['showBottomSpace'] ?? 'on';
		$show_bottom_space_tablet = $attrs['module']['advanced']['spacing']['tablet']['value']['showBottomSpace'] ?? null;
		$show_bottom_space_phone  = $attrs['module']['advanced']['spacing']['phone']['value']['showBottomSpace'] ?? null;

		$url              = HTMLUtility::resolve_url_shortcodes( $attrs['image']['innerContent']['desktop']['value']['linkUrl'] ?? '' );
		$show_in_lightbox = $attrs['image']['advanced']['lightbox']['desktop']['value'] ?? 'off';
		$use_overlay      = $attrs['image']['advanced']['overlay']['desktop']['value']['use'] ?? 'off';
		$is_lightbox      = 'on' === $show_in_lightbox;
		$is_overlay       = 'on' === $use_overlay && ( $is_lightbox || ( ! $is_lightbox && '' !== $url ) );

		$classnames_instance->add( 'et_pb_image_bottom_space_tablet', 'on' === $show_bottom_space_tablet );
		$classnames_instance->add( 'et_pb_image_bottom_space_phone', 'on' === $show_bottom_space_phone );
		$classnames_instance->add( 'et_pb_image_sticky', 'off' === $show_bottom_space );
		$classnames_instance->add( 'et_pb_image_sticky_tablet', 'off' === $show_bottom_space_tablet );
		$classnames_instance->add( 'et_pb_image_sticky_phone', 'off' === $show_bottom_space_phone );
		$classnames_instance->add( 'et_pb_has_overlay', $is_overlay );

		// Add flex column classes if parent is in flex layout.
		// Image module stores sizing data at module.advanced.sizing.
		if ( $parent_attrs ) {
			$parent_layout_display = $parent_attrs['module']['decoration']['layout']['desktop']['value']['display'] ?? 'flex';
			$is_parent_flex_layout = 'flex' === $parent_layout_display;

			if ( $is_parent_flex_layout ) {
				$breakpoints_mapping = Breakpoint::get_css_class_suffixes();

				foreach ( $breakpoints_mapping as $breakpoint => $suffix ) {
					if ( ! Breakpoint::is_enabled_for_style( $breakpoint ) ) {
						continue;
					}

					$flex_type = $attrs['module']['advanced']['sizing'][ $breakpoint ]['value']['flexType'] ?? null;

					if ( $flex_type && 'none' !== $flex_type ) {
						$classnames_instance->add( "et_flex_column_{$flex_type}{$suffix}" );
					}
				}
			}
		}

		// Module.
		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					'attrs' => array_merge(
						$attrs['module']['decoration'] ?? [],
						[
							'border' => $attrs['image']['decoration']['border'] ?? [],
						]
					),
				]
			)
		);
	}

	/**
	 * Image module script data.
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
	 * ImageModule::module_script_data( $args );
	 * ```
	 */
	public static function module_script_data( array $args ): void {
		// Assign variables.
		$elements = $args['elements'];

		// Element Script Data Options.
		$elements->script_data(
			[
				'attrName'      => 'module',
				// Image module doesn't have link script data.
				'attrsResolver' => function ( $attrs_to_resolve ) {
					if ( isset( $attrs_to_resolve['link'] ) ) {
						unset( $attrs_to_resolve['link'] );
					}

					return $attrs_to_resolve;
				},
			]
		);
	}

	/**
	 * Alignment style declaration.
	 *
	 * This function will declare alignment style for Image module.
	 * Always handles content alignment (text-align), module positioning margins, and flex cross-axis alignment (`align-items`).
	 * Module Alignment and Flex Alignment can override margins with !important.
	 *
	 * @since ??
	 *
	 * @param array $params An array of arguments.
	 *
	 * @return string The alignment style declaration.
	 *
	 * @example: Declare alignment style for Image module.
	 * ```php
	 * $params = [
	 *   'attrValue' => 'left',
	 * ];
	 * $style = ImageModule::alignment_style_declaration( $params );
	 *
	 * echo $style;
	 *
	 * // Output: 'text-align: left; margin-left: 0; margin-right: auto; align-items: flex-start;'
	 * ```
	 */
	public static function alignment_style_declaration( array $params ): string {
		$alignment_attr = $params['attrValue'];

		// Keep margins non-important so custom spacing margins can override alignment.
		// Base flex unset rules are handled via selector specificity in module styles.
		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		// Handle both content alignment (text-align) and module positioning margins.
		// Selector specificity handles base `.et_flex_column > .et_pb_image` unset margins.
		if ( ! empty( $alignment_attr ) ) {
			switch ( $alignment_attr ) {
				case 'left':
					$style_declarations->add( 'text-align', 'left' );
					$style_declarations->add( 'margin-left', '0' );
					$style_declarations->add( 'margin-right', 'auto' );
					$style_declarations->add( 'align-items', 'flex-start' );
					break;
				case 'center':
					$style_declarations->add( 'text-align', 'center' );
					$style_declarations->add( 'margin-left', 'auto' );
					$style_declarations->add( 'margin-right', 'auto' );
					$style_declarations->add( 'align-items', 'center' );
					break;
				case 'right':
					$style_declarations->add( 'text-align', 'right' );
					$style_declarations->add( 'margin-left', 'auto' );
					$style_declarations->add( 'margin-right', '0' );
					$style_declarations->add( 'align-items', 'flex-end' );
					break;
				default:
					$style_declarations->add( 'text-align', 'left' );
					$style_declarations->add( 'margin-left', '0' );
					$style_declarations->add( 'margin-right', 'auto' );
					$style_declarations->add( 'align-items', 'flex-start' );
					break;
			}
		}

		return $style_declarations->value();
	}

	/**
	 * Fullwidth module style declaration.
	 *
	 * This function will declare fullwidth module style for Image module.
	 *
	 * @since ??
	 *
	 * @param array $params An array of arguments.
	 *
	 * @return string
	 *
	 * @example: Declare fullwidth module style for Image module.
	 * ```php
	 * $params = [
	 *   'attrValue' => [
	 *     'fullwidth' => 'on',
	 *     // ... other attributes
	 *   ],
	 * ];
	 * $style = ImageModule::fullwidth_module_style_declaration( $params );
	 * // Result: 'width: 100%; max-width: 100% !important'
	 * ```
	 */
	public static function fullwidth_module_style_declaration( array $params ): string {
		$style_declarations = new StyleDeclarations(
			[
				'returnType' => $params['returnType'] ?? 'array',
				'important'  => false,
			]
		);

		$force_fullwidth = $params['attrValue']['forceFullwidth'] ?? 'off';

		if ( 'on' === $force_fullwidth ) {
			$style_declarations->add( 'width', '100%' );
		}

		return $style_declarations->value();
	}

	/**
	 * Fullwidth image style declaration.
	 *
	 * This function will declare fullwidth image style for Image module.
	 *
	 * @since ??
	 *
	 * @param array $params An array of arguments.
	 *
	 * @return string
	 *
	 * @example: Declare fullwidth image style for Image module.
	 * ```php
	 * $params = [
	 *   'attrValue' => [
	 *     'fullwidth' => 'on',
	 *     // ... other attributes
	 *   ],
	 * ];
	 * $style = ImageModule::fullwidth_image_style_declaration( $params );
	 * // Result: 'width: 100%'
	 * ```
	 */
	public static function fullwidth_image_style_declaration( array $params ): string {
		$style_declarations = new StyleDeclarations(
			[
				'returnType' => $params['returnType'] ?? 'array',
				'important'  => [
					'max-width' => true,
				],
			]
		);

		$force_fullwidth = $params['attrValue']['forceFullwidth'] ?? 'off';
		$is_grow_to_fill  = isset( $params['attrValue']['size'] ) && is_array( $params['attrValue']['size'] ) && in_array( 'flexGrow', $params['attrValue']['size'], true );

		// Grow to Fill needs a definite image box so object-fit can stretch in Safari.
		if ( 'on' === $force_fullwidth || $is_grow_to_fill ) {
			$style_declarations->add( 'width', '100%' );
		}

		if ( 'on' === $force_fullwidth ) {
			$style_declarations->add( 'max-width', '100%' );
		}

		if ( $is_grow_to_fill ) {
			$style_declarations->add( 'height', '100%' );
		}

		return $style_declarations->value();
	}

	/**
	 * Declare the overlay background style for the Image module.
	 *
	 * This function takes an array of arguments and declares the overlay background style for the Image module.
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
	 * ImageModule::overlay_background_style_declaration( $params );
	 * // Result: 'background-color: #000000;'
	 * ```
	 */
	public static function overlay_background_style_declaration( array $params ): string {
		$overlay_attr = $params['attrValue'];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => $params['important'],
			]
		);

		if ( ! empty( $overlay_attr['backgroundColor'] ) ) {
			$style_declarations->add( 'background-color', $overlay_attr['backgroundColor'] );
		}

		return $style_declarations->value();
	}

	/**
	 * Get overlay icon style declaration for Fullwidth Image module.
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
	 * $style = ImageModule::overlay_icon_style_declaration( $params );
	 * // Result: 'color: #ff0000; font-weight: 400;'
	 * ```
	 */
	public static function overlay_icon_style_declaration( array $params ): string {
		$overlay_icon_attr = $params['attrValue'];
		$hover_icon        = $overlay_icon_attr['hoverIcon'] ?? [];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => true,
			]
		);

		if ( ! empty( $overlay_icon_attr['iconColor'] ) ) {
			$style_declarations->add( 'color', $overlay_icon_attr['iconColor'] );
		}

		$font_icon = IconFontUtils::escape_font_icon( IconFontUtils::process_font_icon( $hover_icon ) );

		if ( ! empty( $hover_icon['type'] ) ) {
			$font_family = IconFontUtils::is_fa_icon( $hover_icon ) ? 'FontAwesome' : 'ETmodules';
			$style_declarations->add( 'font-family', "'{$font_family}'" );
			$style_declarations->add( 'content', "'{$font_icon}'" );
		}

		if ( ! empty( $hover_icon['weight'] ) ) {
			$style_declarations->add( 'font-weight', $hover_icon['weight'] );
		}

		return $style_declarations->value();
	}


	/**
	 * Style declaration for SVG images.
	 *
	 * This function is responsible for declaring the display style for SVG images.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array  $attrValue Optional. The value (breakpoint > state > value) of the module attribute. Default `[]`.
	 * }
	 *
	 * @return string The value of the display style declaration.
	 *
	 * @example:
	 * ```php
	 * $params = [
	 *     'attrValue' => [
	 *         'src' => 'https://example.com/image.svg?version=1.2.3',
	 *     ],
	 *     'important' => false,
	 *     'returnType' => 'string',
	 * ];
	 *
	 * ImageModule::svg_style_declaration($params);
	 * ```
	 */
	public static function svg_style_declaration( $params ) {
		$attr_value = $params['attrValue'] ?? [];
		$attr       = $params['attr'] ?? null;
		$breakpoint = $params['breakpoint'] ?? null;
		$state      = $params['state'] ?? 'value';

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		// Extract sizing properties for current breakpoint.
		$src        = $attr_value['src'] ?? '';
		$width      = $attr_value['width'] ?? '';
		$height     = $attr_value['height'] ?? '';
		$min_height = $attr_value['minHeight'] ?? '';
		$max_height = $attr_value['maxHeight'] ?? '';

		// Inherit from parent breakpoints if current breakpoint values are missing.
		if ( $attr && $breakpoint ) {
			$all_breakpoint_names = Breakpoint::get_all_breakpoint_names();

			// Use ModuleUtils::get_attr_value with mode 'getAndInheritAll' to recursively merge all parent breakpoint values.
			$inherited_value = ModuleUtils::get_attr_value(
				[
					'attr'            => $attr,
					'breakpoint'      => $breakpoint,
					'state'           => $state,
					'mode'            => 'getAndInheritAll',
					'defaultValue'    => [],
					'breakpointNames' => $all_breakpoint_names,
					'baseBreakpoint'  => 'desktop',
				]
			);

			$src        = $src ? $src : ( $inherited_value['src'] ?? '' );
			$width      = $width ? $width : ( $inherited_value['width'] ?? '' );
			$height     = $height ? $height : ( $inherited_value['height'] ?? '' );
			$min_height = $min_height ? $min_height : ( $inherited_value['minHeight'] ?? '' );
			$max_height = $max_height ? $max_height : ( $inherited_value['maxHeight'] ?? '' );
		}

		if ( ! is_string( $src ) ) {
			$src = '';
		}

		// Check if image is SVG using utility that handles query params and fragments.
		// Skip utility call if src is empty for performance.
		$is_src_svg = ! empty( $src ) && ImageUtils::is_file_extension( $src, 'svg' );

		if ( $is_src_svg ) {
			// Match D4 behavior: SVG modules fill parent width to handle dimensionless SVGs.
			// In D4, block elements with width: auto fill their parent (100%).
			// In D5, flex items with width: auto shrink to content (0px for dimensionless SVGs).
			// Reference: PR #2441 - VB/FE :: Fixed disappearing svg image on image and blurb modules.
			// Only apply 100% if user hasn't set a custom width.
			if ( ! $width || 'auto' === $width || '' === $width ) {
				$style_declarations->add( 'width', '100%' );
			} else {
				// User set custom width, use it.
				$style_declarations->add( 'width', $width );
			}

			// Use user's height if set, otherwise fallback to auto.
			$style_declarations->add( 'height', '' !== $height ? $height : 'auto' );

			// Apply min-height if set.
			if ( is_string( $min_height ) && '' !== $min_height ) {
				$style_declarations->add( 'min-height', $min_height );
			}

			// Apply max-height if set.
			if ( is_string( $max_height ) && '' !== $max_height ) {
				$style_declarations->add( 'max-height', $max_height );
			}
		}

		return $style_declarations->value();
	}


	/**
	 * Style declaration for SVG image child elements (a, .et_pb_image_wrap).
	 *
	 * Always emits width: 100% for SVG images to prevent nested percentage scaling.
	 * Preserves the flex visibility fix from #48460 while resolving the regression
	 * where the user's custom width was propagated into child elements.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array  $attrValue Optional. The value (breakpoint > state > value) of the module attribute. Default `[]`.
	 * }
	 *
	 * @return string The value of the style declaration.
	 */
	public static function svg_child_style_declaration( $params ) {
		$attr_value = $params['attrValue'] ?? [];
		$attr       = $params['attr'] ?? null;
		$breakpoint = $params['breakpoint'] ?? null;
		$state      = $params['state'] ?? 'value';

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		// Extract src for the current breakpoint.
		$src = $attr_value['src'] ?? '';

		// Inherit from parent breakpoints if current breakpoint value is missing.
		if ( $attr && $breakpoint ) {
			$all_breakpoint_names = Breakpoint::get_all_breakpoint_names();

			$inherited_value = ModuleUtils::get_attr_value(
				[
					'attr'            => $attr,
					'breakpoint'      => $breakpoint,
					'state'           => $state,
					'mode'            => 'getAndInheritAll',
					'defaultValue'    => [],
					'breakpointNames' => $all_breakpoint_names,
					'baseBreakpoint'  => 'desktop',
				]
			);

			$src = $src ? $src : ( $inherited_value['src'] ?? '' );
		}

		if ( ! is_string( $src ) ) {
			$src = '';
		}

		// Check if image is SVG using utility that handles query params and fragments.
		$is_src_svg = ! empty( $src ) && ImageUtils::is_file_extension( $src, 'svg' );

		if ( $is_src_svg ) {
			// Child elements always fill the module container at 100%.
			// This prevents the user's custom percentage width from nesting
			// into child elements and causing visual scaling regression.
			$style_declarations->add( 'width', '100%' );
		}

		return $style_declarations->value();
	}


	/**
	 * Comma-separated selectors for Grow to Fill dimension styles when lightbox wraps the image.
	 *
	 * Open in Lightbox inserts an anchor between the module root and `.et_pb_image_wrap`.
	 *
	 * @since ??
	 *
	 * @param string $order_class Module order class.
	 *
	 * @return string
	 */
	public static function grow_to_fill_dimension_selectors( string $order_class ): string {
		return implode(
			', ',
			[
				"{$order_class} img",
				"{$order_class} .et_pb_image_wrap",
			]
		);
	}


	/**
	 * Comma-separated selectors for Grow to Fill flex styles when lightbox wraps the image.
	 *
	 * @since ??
	 *
	 * @param string $order_class Module order class.
	 *
	 * @return string
	 */
	public static function grow_to_fill_flex_selectors( string $order_class ): string {
		return implode(
			', ',
			[
				$order_class,
				"{$order_class} .et_pb_image_wrap",
			]
		);
	}


	/**
	 * Grow to Fill styles for the lightbox anchor between the module root and `.et_pb_image_wrap`.
	 *
	 * D4 sets `display: block; position: relative` on overlay lightbox links. Grow to Fill needs a flex
	 * column chain through the anchor so sizing and magnificPopup click handling stay on the same element.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array $attrValue Optional. The value (breakpoint > state > value) of the module attribute. Default `[]`.
	 *     @type bool  $includeFlexGrow Optional. Whether to emit flex-grow. Default `true`.
	 * }
	 *
	 * @return string
	 */
	public static function grow_to_fill_lightbox_link_style_declaration( array $params ): string {
		$attr_value        = $params['attrValue'] ?? [];
		$include_flex_grow = $params['includeFlexGrow'] ?? true;
		$is_grow_to_fill   = isset( $attr_value['size'] ) && is_array( $attr_value['size'] ) && in_array( 'flexGrow', $attr_value['size'], true );

		if ( ! $is_grow_to_fill ) {
			return '';
		}

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		$style_declarations->add( 'display', 'flex' );
		$style_declarations->add( 'flex-direction', 'column' );
		$style_declarations->add( 'position', 'relative' );
		$style_declarations->add( 'min-height', '0' );
		$style_declarations->add( 'min-width', '0' );
		$style_declarations->add( 'width', '100%' );
		$style_declarations->add( 'height', '100%' );

		if ( $include_flex_grow ) {
			$style_declarations->add( 'flex-grow', '1' );
			$style_declarations->add( 'flex-shrink', '0' );
		}

		return $style_declarations->value();
	}


	/**
	 * Sizing flex style declaration.
	 *
	 * This function is responsible for declaring the flex style for the Image module.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array  $attrValue Optional. The value (breakpoint > state > value) of the module attribute. Default `[]`.
	 * }
	 *
	 * @return string The value of the flex style declaration.
	 *
	 * @example
	 * ```php
	 * $params = [
	 *     'attrValue' => [
	 *         'size' => ['custom', 'flexGrow', 'flexShrink'],
	 *     ],
	 * ];
	 *
	 * ImageModule::sizing_flex_style_declaration($params);
	 * ```
	 */
	public static function sizing_flex_style_declaration( array $params ): string {
		$attr_value      = $params['attrValue'] ?? [];
		$include_display = $params['includeDisplay'] ?? true;

		// Only apply if 'size' is an array.
		if ( ! isset( $attr_value['size'] ) || ! is_array( $attr_value['size'] ) ) {
			return '';
		}

		$size               = $attr_value['size'];
		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		if ( $include_display ) {
			// Default behavior: keep display:flex for existing consumers.
			$style_declarations->add( 'display', 'flex' );
		}

		if ( in_array( 'custom', $size, true ) ) {
			if ( isset( $attr_value['flexGrow'] ) && '' !== $attr_value['flexGrow'] && '0' !== $attr_value['flexGrow'] ) {
				$style_declarations->add( 'flex-grow', $attr_value['flexGrow'] );
			}
			if ( isset( $attr_value['flexShrink'] ) && '' !== $attr_value['flexShrink'] && '1' !== $attr_value['flexShrink'] ) {
				$style_declarations->add( 'flex-shrink', $attr_value['flexShrink'] );
			}
		} else {
			if ( in_array( 'flexGrow', $size, true ) ) {
				$style_declarations->add( 'flex-grow', '1' );
			}
			if ( ! in_array( 'flexShrink', $size, true ) ) {
				$style_declarations->add( 'flex-shrink', '0' );
			}
		}

		return $style_declarations->value();
	}

	/**
	 * Whether the resolved string is an explicit zero length (mirrors TS `isExplicitZeroLength` in border-decoration-flags).
	 *
	 * {@link https://regex101.com/r/MAwFp8/1 Regex101}
	 *
	 * @param string $value Resolved border width or radius.
	 *
	 * @return bool
	 */
	public static function border_decoration_length_is_explicit_zero( string $value ): bool {
		return (bool) preg_match( '/^0(?:[a-z%]+)?$/i', trim( $value ) );
	}

	/**
	 * Resolves a border width fragment like TS `resolveBorderLength` in border-decoration-flags (parseFloat vs non-numeric string).
	 *
	 * {@link https://regex101.com/r/hDd8Gs/1 Regex101}
	 *
	 * @param mixed $raw Raw width from border attribute.
	 *
	 * @return float|string|null Parsed leading number as float, or unresolved string, or null when empty.
	 */
	public static function resolve_border_length_for_decoration_flag( $raw ) {
		if ( null === $raw || false === $raw || '' === $raw ) {
			return null;
		}

		$resolved = Utils::resolve_dynamic_variables_recursive( $raw );

		if ( null === $resolved || false === $resolved ) {
			return null;
		}

		if ( is_array( $resolved ) ) {
			return null;
		}

		$resolved_string = trim( (string) $resolved );

		if ( '' === $resolved_string ) {
			return null;
		}

		// {@link https://regex101.com/r/hDd8Gs/1 Regex101}.
		if ( preg_match( '/^\s*([-+]?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?)/', $resolved_string, $matches ) ) {
			return floatval( $matches[1] );
		}

		return $resolved_string;
	}

	/**
	 * Whether the image border decoration defines any border width greater than zero.
	 *
	 * When width is present, omit `border-radius: inherit` on the inner `img` to avoid corner gaps.
	 *
	 * Parity with TS `borderDecorationHasNonZeroWidth` in `@divi/style-library` border-decoration-flags.
	 *
	 * @since ??
	 *
	 * @param array $attr_value Border attribute value for the current breakpoint/state.
	 *
	 * @return bool
	 */
	public static function image_border_decoration_has_nonzero_width( array $attr_value ): bool {
		$styles = $attr_value['styles'] ?? null;

		if ( ! is_array( $styles ) ) {
			return false;
		}

		$sides = [ 'all', 'top', 'right', 'bottom', 'left' ];

		foreach ( $sides as $side ) {
			if ( ! isset( $styles[ $side ]['width'] ) ) {
				continue;
			}

			$resolved = self::resolve_border_length_for_decoration_flag( $styles[ $side ]['width'] );

			if ( null === $resolved ) {
				continue;
			}

			if ( is_float( $resolved ) ) {
				if ( 0.0 < $resolved ) {
					return true;
				}
				continue;
			}

			if ( is_string( $resolved ) && ! self::border_decoration_length_is_explicit_zero( $resolved ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Image-level `border-radius: inherit` for `.et_pb_image_wrap img` when radius is set and no border width.
	 *
	 * This function is equivalent to the JavaScript `imageBorderRadiusInheritStyleDeclaration` closure in
	 * {@link /docs/builder-api/js/module-library/module-styles moduleStyles} (`@divi/module-library`), which uses
	 * `borderDecorationHasNonZeroWidth` / `borderDecorationHasNonZeroRadius` from `@divi/style-library`.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     Declaration parameters.
	 *
	 *     @type array $attrValue Border attribute value.
	 * }
	 *
	 * @return string
	 */
	public static function image_border_radius_inherit_style_declaration( array $params ): string {
		$attr_value = $params['attrValue'] ?? [];

		if ( ! is_array( $attr_value ) ) {
			return '';
		}

		if ( self::image_border_decoration_has_nonzero_width( $attr_value ) ) {
			return '';
		}

		$radius = $attr_value['radius'] ?? null;

		if ( ! is_array( $radius ) || empty( $radius ) ) {
			return '';
		}

		$all_corners_zero = true;

		foreach ( $radius as $corner => $value ) {
			if ( 'sync' === $corner ) {
				continue;
			}

			$resolved = Utils::resolve_dynamic_variables_recursive( $value );

			if ( is_array( $resolved ) ) {
				continue;
			}

			$resolved_string = (string) $resolved;

			if ( '' === $resolved_string ) {
				continue;
			}

			if ( 0.0 !== floatval( $resolved_string ) ) {
				$all_corners_zero = false;
				break;
			}
		}

		if ( $all_corners_zero ) {
			return '';
		}

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		$style_declarations->add( 'border-radius', 'inherit' );

		return $style_declarations->value();
	}

	/**
	 * Image Module's style components.
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
		$attrs                 = $args['attrs'] ?? [];
		$elements              = $args['elements'];
		$settings              = $args['settings'] ?? [];
		$is_parent_flex_layout = ! empty( $args['isParentFlexLayout'] );
		$is_lightbox_enabled   = 'on' === ( $attrs['image']['advanced']['lightbox']['desktop']['value'] ?? 'off' );

		// Get parent layout type information.
		$is_parent_grid_layout = $elements->get_is_parent_grid_layout();

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
								'advancedStyles' => array_filter(
									[
										[
											// Custom Styles.
											// Important: This style must be added before `divi/image-sizing` to make sure the module alignment is correct.
											'componentName' => 'divi/image-spacing',
											'props' => [
												'attr' => $attrs['module']['advanced']['spacing'] ?? [],
												'important' => [
													'desktop' => [
														'value' => [
															'margin' => true,
														],
													],
												],
											],
										],
										[
											// Image Alignment must come before Module Alignment (divi/image-sizing) to ensure
											// proper CSS cascade. Module Alignment will override Image Alignment when width is set.
											'componentName' => 'divi/common',
											'props' => [
												// Scope alignment to image modules for stronger specificity than base flex unset rules.
												'selector' => "{$args['orderClass']}.et_pb_image",
												'attr' => $attrs['module']['advanced']['align'] ?? null,
												'declarationFunction' => [ self::class, 'alignment_style_declaration' ],
											],
										],
										[
											'componentName' => 'divi/image-sizing',
											'props' => [
												'imageSelector' => "{$args['orderClass']} .et_pb_image_wrap img",
												'attr' => $attrs['module']['advanced']['sizing'] ?? [],
												'isParentFlexLayout' => $is_parent_flex_layout,
												'isParentGridLayout' => $is_parent_grid_layout,
												'spacingAttr' => $attrs['module']['advanced']['spacing'] ?? [],
											],
										],
										[
											'componentName' => 'divi/common',
											'props' => [
												'attr' => $attrs['module']['advanced']['sizing'] ?? [],
												'declarationFunction' => [ self::class, 'fullwidth_module_style_declaration' ],
											],
										],
										[
											'componentName' => 'divi/common',
											'props' => [
												'selector'            => self::grow_to_fill_dimension_selectors( $args['orderClass'] ),
												'attr'                => $attrs['module']['advanced']['sizing'] ?? [],
												'declarationFunction' => [ self::class, 'fullwidth_image_style_declaration' ],
											],
										],
										$is_parent_flex_layout ? [
											'componentName' => 'divi/common',
											'props' => [
												'selector'            => self::grow_to_fill_flex_selectors( $args['orderClass'] ),
												'attr'                => $attrs['module']['advanced']['sizing'] ?? [],
												'declarationFunction' => static function ( $params ) {
													$params['includeDisplay'] = false;

													return self::sizing_flex_style_declaration( $params );
												},
											],
										] : null,
										$is_lightbox_enabled ? [
											'componentName' => 'divi/common',
											'props' => [
												'selector' => "{$args['orderClass']} a.et_pb_lightbox_image",
												'attr'     => $attrs['module']['advanced']['sizing'] ?? [],
												'declarationFunction' => static function ( $params ) use ( $is_parent_flex_layout ) {
													$params['includeFlexGrow'] = $is_parent_flex_layout;

													return self::grow_to_fill_lightbox_link_style_declaration( $params );
												},
											],
										] : null,
									]
								),
							],
						]
					),
					// Image.
					$elements->style(
						[
							'attrName'   => 'image',
							'styleProps' => [
								'fit'            => [
									'selector' => "{$args['orderClass']} .et_pb_image_wrap img",
								],
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$args['orderClass']} .et_overlay",
											'attr'     => $attrs['image']['advanced']['overlay'] ?? [],
											'declarationFunction' => [ self::class, 'overlay_background_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$args['orderClass']} .et_overlay:before",
											'attr'     => $attrs['image']['advanced']['overlayIcon'] ?? [],
											'declarationFunction' => [ self::class, 'overlay_icon_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $args['orderClass'] . ' .et_pb_image_wrap',
											'attr'     => $attrs['image']['decoration']['border'] ?? [],
											'declarationFunction' => function ( $params ) use ( $attrs ) {
												$overflow_attr = $attrs['module']['decoration']['overflow'] ?? [];
												return Declarations::overflow_for_border_radius_style_declaration( $params, $overflow_attr );
											},
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$args['orderClass']} .et_pb_image_wrap img",
											'attr'     => $attrs['image']['decoration']['border'] ?? [],
											'declarationFunction' => [ self::class, 'image_border_radius_inherit_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$args['orderClass']}",
											'attr'     => array_replace_recursive( [], $attrs['module']['advanced']['sizing'] ?? [], $attrs['image']['innerContent'] ?? [] ),
											'declarationFunction' => [ self::class, 'svg_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$args['orderClass']} a, {$args['orderClass']} .et_pb_image_wrap",
											'attr'     => array_replace_recursive( [], $attrs['module']['advanced']['sizing'] ?? [], $attrs['image']['innerContent'] ?? [] ),
											'declarationFunction' => [ self::class, 'svg_child_style_declaration' ],
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
	 * Get the custom CSS fields for the Divi Image module.
	 *
	 * This function retrieves the custom CSS fields defined for the Divi image module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /api/js/divi-module-library/functions/generateDefaultAttrs cssFields} located in
	 * `@divi/module-library`. Note that this function does not have a `label` property on each
	 * array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi image module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = ImageModule::custom_css();
	 * // Returns an array of custom CSS fields for the image module.
	 * ```
	 */
	public static function custom_css(): array {
		return WP_Block_Type_Registry::get_instance()->get_registered( 'divi/image' )->customCssFields;
	}

	/**
	 * Render callback for the Image module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ ImageEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                 Block attributes that were saved by Divi Builder.
	 * @param string         $child_modules_content The block's content.
	 * @param WP_Block       $block                 Parsed block object that is being rendered.
	 * @param ModuleElements $elements              An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the Image module.
	 *
	 * @example
	 * ```php
	 * $attrs = [
	 *   'attrName' => 'value',
	 *   //...
	 * ];
	 * $child_modules_content = 'The block content.';
	 * $block = new WP_Block();
	 * $elements = new ModuleElements();
	 *
	 * ImageModule::render_callback( $attrs, $child_modules_content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $child_modules_content, WP_Block $block, ModuleElements $elements ): string {
		// Extract child modules IDs using helper utility.
		$children_ids = ChildrenUtils::extract_children_ids( $block );

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
				'scriptDataComponent' => [ self::class, 'module_script_data' ],
				'stylesComponent'     => [ self::class, 'module_styles' ],
				'parentAttrs'         => $parent->attrs ?? [],
				'parentId'            => $parent->id ?? '',
				'parentName'          => $parent->blockName ?? '',
				'childrenIds'         => $children_ids,
				'children'            => $elements->style_components(
					[
						'attrName' => 'module',
					]
				) . $elements->render(
					[
						'attrName'              => 'image',
						'imageWrapperClassName' => 'et_pb_image_wrap',
					]
				) . $child_modules_content,
			]
		);
	}

	/**
	 * Loads `ImageModule` and registers Front-End render callback.
	 *
	 * @return void
	 */
	public function load(): void {
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/image/';

		add_filter( 'divi_conversion_presets_attrs_map', [ ImagePresetAttrsMap::class, 'get_map' ], 10, 2 );

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
	 * Resolve the group preset attribute name for the Image module.
	 *
	 * @param GlobalPresetItemGroupAttrNameResolved|null $attr_name_to_resolve The attribute name to be resolved.
	 * @param array                                      $params               The filter parameters.
	 *
	 * @return GlobalPresetItemGroupAttrNameResolved|null The resolved attribute name.
	 */
	public static function option_group_preset_resolver_attr_name( $attr_name_to_resolve, array $params ): ?GlobalPresetItemGroupAttrNameResolved {
		// Bydefault, $attr_name_to_resolve is a null value.
		// If it is not null, it means that the attribute name is already resolved.
		// In this case, we return the resolved attribute name.
		if ( null !== $attr_name_to_resolve ) {
			return $attr_name_to_resolve;
		}

		if ( $params['moduleName'] !== $params['dataModuleName'] ) {
			if ( 'divi/image' === $params['moduleName'] ) {
				if ( 'module.advanced.sizing' === $params['attrName'] ) {
					return new GlobalPresetItemGroupAttrNameResolved(
						[
							'attrName'    => 'module.decoration.sizing',
							'attrSubName' => $params['attrSubName'] ?? null,
						]
					);
				}

				if ( 'module.advanced.spacing' === $params['attrName'] ) {
					return new GlobalPresetItemGroupAttrNameResolved(
						[
							'attrName'    => 'module.decoration.spacing',
							'attrSubName' => $params['attrSubName'] ?? null,
						]
					);
				}
			}

			if ( 'divi/image' === $params['dataModuleName'] ) {
				if ( 'module.decoration.sizing' === $params['attrName'] ) {
					return new GlobalPresetItemGroupAttrNameResolved(
						[
							'attrName'    => 'module.advanced.sizing',
							'attrSubName' => $params['attrSubName'] ?? null,
						]
					);
				}

				if ( 'module.decoration.spacing' === $params['attrName'] ) {
					return new GlobalPresetItemGroupAttrNameResolved(
						[
							'attrName'    => 'module.advanced.spacing',
							'attrSubName' => $params['attrSubName'] ?? null,
						]
					);
				}
			}
		}

		return $attr_name_to_resolve;
	}
}
