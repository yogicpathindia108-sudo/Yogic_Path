<?php
/**
 * Module Library: Blurb Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Blurb;

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
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewUtils;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Animation\AnimationUtils;
use ET\Builder\Packages\Module\Options\BoxShadow\BoxShadowClassnames;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\Common\ImageWrapperAnimation;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\ModuleLibrary\Image\ImageModule;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ImageUtils;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use Exception;
use WP_Block_Type_Registry;
use WP_Block;
use ET\Builder\Packages\GlobalData\GlobalPresetItemGroup;
use ET\Builder\Packages\GlobalData\GlobalData;

/**
 * BlurbModule class.
 *
 * This class implements the functionality of a blurb component in a frontend
 * application. It provides functions for rendering the blurb, managing REST API
 * endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class BlurbModule implements DependencyInterface {

	/**
	 * Generate classnames for the module.
	 *
	 * This function generates classnames for the module based on the provided
	 * arguments. It is used in the `render_callback` function of the Blurb module.
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
	 *   'classnamesInstance' => $classnamesInstance,
	 *   'attrs' => $attrs,
	 * ];
	 *
	 * BlurbModule::module_classnames($args);
	 * ```
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		$classnames_instance->add( TextClassnames::text_options_classnames( $attrs['module']['advanced']['text'] ?? [] ), true );

		$image_icon_placement = $attrs['imageIcon']['advanced']['placement']['desktop']['value'] ?? 'top';
		$classnames_instance->add( 'et_pb_blurb_position_' . $image_icon_placement, true );

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
	 * Blurb module script data.
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
	 *   'id'             => 'my-module',
	 *   'name'           => 'My Module',
	 *   'selector'       => '.my-module',
	 *   'attrs'          => array(
	 *     'portfolio' => array(
	 *       'advanced' => array(
	 *         'showTitle'       => false,
	 *         'showCategories'  => true,
	 *         'showPagination' => true,
	 *       )
	 *     )
	 *   ),
	 *   'elements'       => $elements,
	 *   'store_instance' => 123,
	 * );
	 *
	 * BlurbModule::module_script_data( $args );
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
		$is_use_icon    = 'on' === ( $attrs['imageIcon']['innerContent']['desktop']['value']['useIcon'] ?? 'off' );

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
				'setContent'    => [
					$is_use_icon ? [
						'selector'      => $selector . ' .et-pb-icon',
						'data'          => $attrs['imageIcon']['innerContent'] ?? [],
						'valueResolver' => function ( $value ) {
							return Utils::process_font_icon( $value['icon'] ?? '' ) ?? '';
						},
					] : [],
				],
				'setClassName'  => [
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_blurb_position_top' => $attrs['imageIcon']['advanced']['placement'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'top' === $value ? 'add' : 'remove';
						},
					],
					[
						'selector'      => $selector,
						'data'          => [
							'et_pb_blurb_position_left' => $attrs['imageIcon']['advanced']['placement'] ?? [],
						],
						'valueResolver' => function ( $value ) {
							return 'left' === $value ? 'add' : 'remove';
						},
					],
				],
			]
		);
	}

	/**
	 * Get the custom CSS fields for the Divi Blurb module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /api/js/divi-module-library/functions/generateDefaultAttrs cssFields}
	 * located in `@divi/module-library`. Note that this function does not have
	 * a `label` property on each array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi blurb module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = CustomCssTrait::custom_css();
	 * // Returns an array of custom CSS fields for the blurb module.
	 * ```
	 */
	public static function custom_css(): array {
		return WP_Block_Type_Registry::get_instance()->get_registered( 'divi/blurb' )->customCssFields;
	}

	/**
	 * Returns the icon style declaration for Blurb module.
	 *
	 * This function declares CSS styles for the Blurb module icon based on the provided parameters.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array      $attrValue  The value (breakpoint > state > value) of the module attribute.
	 *     @type bool|array $important  If set to true, the CSS will be added with !important.
	 *     @type string     $returnType This is the type of value that the function will return. Can be either string or key_value_pair.
	 * }
	 *
	 * @throws Exception Throws an exception if the provided attribute value is not an array.
	 *
	 * @return string The generated icon style declaration.
	 *
	 * @example
	 * ```php
	 * BlurbModule::icon_style_declaration( [
	 *   'attrValue'  => [
	 *     'icon' => [
	 *       'type'    => 'fa',
	 *       'weight'  => 'bold',
	 *       'unicode' => '&#xf104;',
	 *     ],
	 *   ],
	 * ] );
	 *
	 * // Result: 'font-family: FontAwesom !important; font-weight: bold; content: "\f104";'
	 * ```
	 */
	public static function icon_style_declaration( array $params ): string {
		$icon_attr = $params['attrValue']['icon'] ?? [];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => [
					'font-family' => true,
				],
			]
		);

		if ( isset( $icon_attr['type'] ) ) {
			$font_family = 'fa' === $icon_attr['type'] ? 'FontAwesome' : 'ETmodules';
			$style_declarations->add( 'font-family', $font_family );
		}

		if ( ! empty( $icon_attr['weight'] ) ) {
			$style_declarations->add( 'font-weight', $icon_attr['weight'] );
		}

		if ( ! empty( $icon_attr['unicode'] ) ) {
			$font_icon = Utils::escape_font_icon( Utils::process_font_icon( $icon_attr ) );
			$style_declarations->add( 'content', "'" . $font_icon . "'" );
		}

		return $style_declarations->value();
	}

	/**
	 * Declare content alignment style for Blurb module.
	 *
	 * This function takes an array of arguments and declares the content alignment style for the Blurb module.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array $attrValue The alignment value (`left`, `center`, `right`).
	 * }
	 *
	 * @return string The content alignment style declaration.
	 *
	 * @example
	 * ```php
	 * BlurbModule::content_alignment_style_declaration( [ 'attrValue' => 'left' ] );
	 * // Result: 'text-align: left;'
	 * ```
	 *
	 * @example: Passing 'center' as the attribute value.
	 * ```php
	 * BlurbModule::content_alignment_style_declaration( [ 'attrValue' => 'center' ] );
	 * // Result: ''
	 * ```
	 */
	public static function content_alignment_style_declaration( array $params ): string {
		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
			]
		);

		if ( $params['attrValue'] ) {
			$style_declarations->add( 'text-align', $params['attrValue'] );
		}

		return $style_declarations->value();
	}

	/**
	 * Declare content alignment style for Blurb module.
	 *
	 * This function takes an array of arguments and declares the content alignment
	 * style for the Blurb module. The function expects an array of parameters with
	 * the following keys:
	 *
	 * - attrValue (array): The value (breakpoint > state > value) of the module attribute.
	 * - important (bool|array): If set to true, the CSS will be added with !important.
	 * - returnType (string): The type of value that the function will return. Can be either string or key_value_pair.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array $attrValue Image alignment value (`left`, `right`).
	 * }
	 *
	 * @return string The content alignment style declaration.
	 *
	 * @throws Exception Throws an exception if the provided attribute value is not an array.
	 *
	 * @example: Passing 'left' as the attribute value.
	 * ```php
	 * $params = [
	 *   'attrValue' => 'left',
	 * ];
	 * $style = BlurbModule::content_alignment_style_declaration( $params );
	 * // Result: 'margin: auto auto auto 0;'
	 * ```
	 *
	 * @example: Passing 'right' as the attribute value.
	 * ```php
	 *  $params = [
	 *    'attrValue' => 'right',
	 *  ];
	 *  $style = BlurbModule::content_alignment_style_declaration( $params );
	 *  // Result: 'margin: auto 0 auto auto;'
	 *  ```
	 */
	public static function image_alignment_style_declaration( array $params ): string {
		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
			]
		);

		switch ( $params['attrValue'] ) {
			case 'left':
				$style_declarations->add( 'margin', 'auto auto auto 0' );
				break;

			case 'right':
				$style_declarations->add( 'margin', 'auto 0 auto auto' );
				break;

			default:
				$style_declarations->add( 'margin', 'auto' );
		}

		return $style_declarations->value();
	}

	/**
	 * Map D5 sizing alignSelf values to legacy alignment values used by Blurb declarations.
	 *
	 * @since ??
	 *
	 * @param string $align_self The alignSelf value.
	 *
	 * @return string The alignment value, or empty string when unsupported.
	 */
	private static function align_self_to_alignment( string $align_self ): string {
		switch ( $align_self ) {
			case 'flex-start':
				return 'left';

			case 'end':
				return 'right';

			case 'center':
			case 'stretch':
				return 'center';

			default:
				return '';
		}
	}

	/**
	 * Declare icon-mode sizing alignment styles for Blurb module.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array $attrValue The sizing value that may include `alignSelf`.
	 * }
	 *
	 * @return string The icon-mode sizing alignment declaration.
	 */
	public static function icon_sizing_alignment_style_declaration( array $params ): string {
		$align_self = $params['attrValue']['alignSelf'] ?? '';

		if ( ! is_string( $align_self ) || '' === $align_self ) {
			return '';
		}

		$alignment = self::align_self_to_alignment( $align_self );

		if ( '' === $alignment ) {
			return '';
		}

		return self::content_alignment_style_declaration(
			[
				'attrValue' => $alignment,
			]
		);
	}

	/**
	 * Declare image-mode sizing alignment styles for Blurb module.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array $attrValue The sizing value that may include `alignSelf`.
	 * }
	 *
	 * @return string The image-mode sizing alignment declaration.
	 */
	public static function image_sizing_alignment_style_declaration( array $params ): string {
		$align_self = $params['attrValue']['alignSelf'] ?? '';

		if ( ! is_string( $align_self ) || '' === $align_self ) {
			return '';
		}

		$alignment = self::align_self_to_alignment( $align_self );

		if ( '' === $alignment ) {
			return '';
		}

		return self::image_alignment_style_declaration(
			[
				'attrValue' => $alignment,
			]
		);
	}

	/**
	 * Retrieve the CSS style declaration for the icon font size.
	 *
	 * This function adds a `font-size` style declaration to the Blurb module's icon.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array $attrValue The value (breakpoint > state > value) of the module attribute.
	 * }
	 *
	 * @return string The CSS style declaration for the icon font size.
	 *
	 * @example
	 * ```php
	 * $params = [
	 *   'attrValue' => [
	 *     'iconFontSize' => '24px',
	 *   ]
	 * ];
	 * $result = BlurbModule::icon_width_style_declaration( $params );
	 * // Result: "font-size: 24px;"
	 * ```
	 */
	public static function icon_width_style_declaration( array $params ): string {
		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
			]
		);

		$icon_font_size = $params['attrValue']['iconFontSize'] ?? null;
		if ( is_string( $icon_font_size ) && '' !== $icon_font_size ) {
			$style_declarations->add( 'font-size', $icon_font_size );
		}

		return $style_declarations->value();
	}

	/**
	 * Sets the image width style declaration for the Blurb module.
	 *
	 * This function adds a `width` style declaration to the Blurb module's
	 * image based on the provided parameters. It uses the value (breakpoint >
	 * state > value) of the module attribute to determine the width. The CSS
	 * style declaration is returned as a string.
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array $attrValue The value (breakpoint > state > value) of the module attribute.
	 * }
	 *
	 * @return string The CSS style declaration for the image width.
	 *
	 * @example: Set the image width style declaration for the Blurb module.
	 * ```php
	 * $params = [
	 *   'attrValue' => [
	 *     'width' => '500px',
	 *   ],
	 * ];
	 * $imageWidthStyle = BlurbModule::image_width_style_declaration( $params );
	 * // Result: "width: 500px;"
	 * ```
	 */
	public static function image_width_style_declaration( array $params ): string {
		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
			]
		);

		$width_value = $params['attrValue']['width'] ?? null;
		if ( is_string( $width_value ) && '' !== $width_value ) {
			$style_declarations->add( 'width', $width_value );
		}

		return $style_declarations->value();
	}

	/**
	 * Image max-width style declaration for Blurb module.
	 * Adds max-width: 100% only when the image width exceeds 100%
	 * to prevent overflow in left/right positioned blurbs.
	 *
	 * @since ??
	 *
	 * @param array $params Parameters for the style declaration.
	 *
	 * @return string Generated CSS styles for image max-width.
	 */
	public static function image_max_width_style_declaration( array $params ): string {
		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
			]
		);

		// Get the image width value.
		$image_width = $params['attrValue']['width'] ?? '';

		if ( $image_width ) {
			// Parse the numeric value from the width string.
			$numeric_value = floatval( $image_width );

			// Check if the value is a percentage and exceeds 100.
			if ( str_contains( $image_width, '%' ) && $numeric_value > 100 ) {
				$style_declarations->add( 'max-width', '100%' );
			}
		}

		return $style_declarations->value();
	}

	/**
	 * SVG style declaration for Blurb module.
	 * Handles the unique Blurb structure where width can be either for icon or image.
	 *
	 * @since ??
	 *
	 * @param array $params Parameters for the style declaration.
	 *
	 * @return string Generated CSS styles for SVG images.
	 */
	public static function svg_style_declaration( array $params ): string {
		$attr_value = $params['attrValue'] ?? [];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		// Only handle SVG images, not icons.
		$is_using_icon = 'on' === ( $attr_value['useIcon'] ?? '' );

		if ( ! $is_using_icon ) {
			// Get src from innerContent.
			$src = $attr_value['src'] ?? '';

			if ( ! is_string( $src ) ) {
				$src = '';
			}

			// Check if image is SVG using utility that handles query params and fragments.
			// Skip utility call if src is empty for performance.
			$is_src_svg = ! empty( $src ) && ImageUtils::is_file_extension( $src, 'svg' );

			if ( $is_src_svg ) {
				// Width comes from Image/Icon sizing group.
				$width = $attr_value['width'] ?? '';

				// Use user's width if set, otherwise fallback to 100%.
				$style_declarations->add( 'width', ! empty( $width ) ? $width : '100%' );

				// Use auto height for SVGs.
				$style_declarations->add( 'height', 'auto' );
			}
		}

		return $style_declarations->value();
	}


	/**
	 * Get the style components for the Blurb Module.
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
	 *     @type string $id                The ID of the module. In VB, the ID of module is UUIDV4. In FE, the ID is order index.
	 *     @type string $name              The name of the module.
	 *     @type string $attrs             The attributes of the module.
	 *     @type string $parentAttrs       The parent attributes.
	 *     @type string $orderClass        The selector class name.
	 *     @type string $parentOrderClass  The parent selector class name.
	 *     @type string $wrapperOrderClass The wrapper selector class name.
	 *     @type string $settings          The custom settings.
	 *     @type string $state             The attributes state.
	 *     @type string $mode              The style mode.
	 *     @type ModuleElements $elements  ModuleElements instance.
	 * }
	 *
	 * @return void
	 *
	 * @example
	 * ```php
	 * $module_styles = MyClass::getModuleStyles([
	 *   'id' => '1234',
	 *   'name' => 'My Module',
	 *   'attrs' => '',
	 *   'parentAttrs' => '',
	 *   'orderClass' => 'module-class',
	 *   'parentOrderClass' => 'parent-class',
	 *   'wrapperOrderClass' => 'wrapper-class',
	 *   'settings' => '',
	 *   'state' => '',
	 *   'mode' => 'default',
	 * ]);
	 * ```
	 */
	public static function module_styles( array $args ): void {
		$attrs       = $args['attrs'] ?? [];
		$elements    = $args['elements'];
		$settings    = $args['settings'] ?? [];
		$placement   = $attrs['imageIcon']['advanced']['placement']['desktop']['value'] ?? '';
		$order_class = $args['orderClass'];
		$style_group = $args['styleGroup'] ?? 'module';
		$use_icon    = 'on' === ( $attrs['imageIcon']['innerContent']['desktop']['value']['useIcon'] ?? 'off' );

		$blurb_image_container_selector = "{$args['orderClass']} > .et_pb_blurb_content > .et_pb_main_blurb_image";
		$blurb_image_wrap_selector      = "{$blurb_image_container_selector} .et_pb_image_wrap";
		$blurb_image_only_mode_selector = "{$blurb_image_wrap_selector}.et_pb_only_image_mode_wrap";
		$blurb_icon_selector            = "{$blurb_image_container_selector} .et-pb-icon";

		// Default SizingStyle uses the imageIcon selector that includes `.et-pb-icon` and `img`. Strip width from that
		// pass when rendering preset/group styles (useIcon is not in preset attrs) or when the module uses icon mode.
		// Keep width in module + image mode, while always stripping maxWidth/minWidth so icon font-size data never
		// leaks into default sizing output.
		$should_strip_sizing_width_for_default_sizing_style = ( 'module' !== $style_group ) || $use_icon;

		// Image conditionals.
		$image_src         = $attrs['imageIcon']['innerContent']['desktop']['value']['src'] ?? '';
		$is_placement_top  = empty( $placement ) || 'top' === $placement;
		$is_image_svg      = ! empty( $image_src ) && ImageUtils::is_file_extension( $image_src, 'svg' );
		$image_sizing_attr = $attrs['imageIcon']['decoration']['sizing'] ?? [];

		$has_relative_image_width = false;
		$image_sizing_height_attr = [];

		foreach ( $image_sizing_attr as $breakpoint => $breakpoint_values ) {
			if ( ! is_array( $breakpoint_values ) ) {
				continue;
			}

			foreach ( $breakpoint_values as $state => $state_value ) {
				$width_value = is_array( $state_value ) ? ( $state_value['width'] ?? '' ) : '';

				if ( is_string( $width_value ) && ! empty( $width_value ) && ! str_contains( $width_value, 'px' ) ) {
					$has_relative_image_width = true;
				}

				if ( ! is_array( $state_value ) ) {
					continue;
				}

				$filtered_height_value = array_intersect_key(
					$state_value,
					array_flip(
						[
							'minHeight',
							'height',
							'maxHeight',
							'aspectRatio',
							'forceFullwidth',
						]
					)
				);

				if ( ! empty( $filtered_height_value ) ) {
					$image_sizing_height_attr[ $breakpoint ][ $state ] = $filtered_height_value;
				}
			}
		}

		// Preset attrs omit use_icon, so icon font-size must still run on preset style passes.
		$render_icon_width_style_declaration = ( 'module' !== $style_group ) || $use_icon;

		// Same for image width on preset passes; module path still gates on image mode.
		$render_image_width_style_declaration = ( 'module' !== $style_group ) || ! $use_icon;

		// Create icon width style props if icon width styles should be rendered.
		$render_icon_width_props = $render_icon_width_style_declaration ? [
			'selector'            => $blurb_icon_selector,
			'attr'                => $attrs['imageIcon']['decoration']['sizing'] ?? [],
			'declarationFunction' => [ self::class, 'icon_width_style_declaration' ],
		] : [];

		// Determine image width selector based on D4's conditional logic.
		// Apply selector to both parent and wrapper only for fixed px widths.
		// Relative units (%, em, rem, etc.) must target wrapper only to avoid compounded max-width.
		$image_width_selector = ( $is_placement_top && $is_image_svg && ! $has_relative_image_width )
			? "{$blurb_image_container_selector}, {$blurb_image_only_mode_selector}"
			: $blurb_image_only_mode_selector;
		$image_sizing_alignment_selector = $is_image_svg
			? ( $has_relative_image_width ? $blurb_image_only_mode_selector : $blurb_image_container_selector )
			: $blurb_image_wrap_selector;

		// Create image width style props if image width styles should be rendered.
		$render_image_width_props = $render_image_width_style_declaration ? [
			'selector'            => $image_width_selector,
			'attr'                => $attrs['imageIcon']['decoration']['sizing'] ?? [],
			'declarationFunction' => [ self::class, 'image_width_style_declaration' ],
		] : [];

		// Route image height and aspect-ratio sizing styles to the img element.
		$render_image_height_props = ( $render_image_width_style_declaration && ! empty( $image_sizing_height_attr ) ) ? [
			'selector'      => $blurb_image_only_mode_selector,
			'imageSelector' => "{$blurb_image_wrap_selector} img",
			'attr'          => $image_sizing_height_attr,
		] : [];

		// Determine if icon sizing alignment styles should be rendered:
		// - always render when rendering preset styles (useIcon attribute is not available in preset attributes).
		// - only render when icon is enabled when rendering module styles.
		$render_icon_sizing_alignment_style_declaration = 'module' !== $style_group || $use_icon;

		// Determine if image sizing alignment styles should be rendered:
		// - always render when rendering preset styles (useIcon attribute is not available in preset attributes).
		// - only render when icon is disabled when rendering module styles.
		$render_image_sizing_alignment_style_declaration = 'module' !== $style_group || ! $use_icon;

		// Create icon sizing alignment style props.
		$render_icon_sizing_alignment_props = $render_icon_sizing_alignment_style_declaration ? [
			'selector'            => $blurb_image_container_selector,
			'attr'                => $attrs['imageIcon']['decoration']['sizing'] ?? [],
			'declarationFunction' => [ self::class, 'icon_sizing_alignment_style_declaration' ],
		] : [];

		// Create image sizing alignment style props. Match D4 behavior and only use image alignment when placement is top.
		$render_image_sizing_alignment_props = ( $is_placement_top && $render_image_sizing_alignment_style_declaration ) ? [
			'selector'            => $image_sizing_alignment_selector,
			'attr'                => $attrs['imageIcon']['decoration']['sizing'] ?? [],
			'declarationFunction' => [ self::class, 'image_sizing_alignment_style_declaration' ],
		] : [];

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
								'advancedStyles' => array_merge(
									[
										[
											'componentName' => 'divi/background',
											'props' => [
												'attr' => $attrs['module']['decoration']['background'] ?? [],
											],
										],
										[
											'componentName' => 'divi/text',
											'props' => [
												'selector' => "{$order_class} .et_pb_blurb_container",
												'attr'     => $attrs['module']['advanced']['text'] ?? [],
											],
										],
										[
											'componentName' => 'divi/common',
											'props' => [
												'attr' => $attrs['module']['decoration']['border'] ?? [],
												'declarationFunction' => function ( $params ) use ( $attrs ) {
													$overflow_attr = $attrs['module']['decoration']['overflow'] ?? [];
													return Declarations::overflow_for_border_radius_style_declaration( $params, $overflow_attr );
												},
											],
										],
										[
											'componentName' => 'divi/common',
											'props' => [
												'selector' => "{$blurb_image_only_mode_selector}, {$blurb_icon_selector}",
												'attr'     => $attrs['imageIcon']['decoration']['border'] ?? [],
												'declarationFunction' => [ Declarations::class, 'overflow_for_border_radius_style_declaration' ],
											],
										],
									],
									AnimationUtils::is_enabled( $attrs['imageIcon']['decoration']['animation'] ?? [] )
										? [
											[
												'componentName' => 'divi/common',
												'props' => [
													'selector' => "{$order_class} .et_pb_blurb_content .et_pb_blurb_container",
													'attr' => $attrs['imageIcon']['decoration']['animation'] ?? [],
													'declarationFunction' => static function (): string {
														return 'position: relative; z-index: 2;';
													},
												],
											],
										]
										: []
								),
							],
						]
					),

					// Image Icon.
					$elements->style(
						[
							'attrName'   => 'imageIcon',
							'styleProps' => [
								// Use custom alignment handling so sizing alignment can match Blurb's icon/image behavior.
								'sizing'         => [
									'disableAlignmentStyles' => true,
								],
								// Prevent default SizingStyle from outputting icon-mode sizing values.
								'attrsFilter'    => function ( $element_attrs ) use ( $should_strip_sizing_width_for_default_sizing_style ) {
									if ( empty( $element_attrs['sizing'] ) || ! is_array( $element_attrs['sizing'] ) ) {
										return $element_attrs;
									}

									$breakpoint_states = MultiViewUtils::get_breakpoints_states();

									foreach ( $breakpoint_states as $breakpoint => $states ) {
										if ( ! isset( $element_attrs['sizing'][ $breakpoint ] ) || ! is_array( $element_attrs['sizing'][ $breakpoint ] ) ) {
											continue;
										}

										foreach ( $states as $state ) {
											if ( ! isset( $element_attrs['sizing'][ $breakpoint ][ $state ] ) || ! is_array( $element_attrs['sizing'][ $breakpoint ][ $state ] ) ) {
												continue;
											}

											unset( $element_attrs['sizing'][ $breakpoint ][ $state ]['iconFontSize'] );
											unset( $element_attrs['sizing'][ $breakpoint ][ $state ]['minHeight'] );
											unset( $element_attrs['sizing'][ $breakpoint ][ $state ]['height'] );
											unset( $element_attrs['sizing'][ $breakpoint ][ $state ]['maxHeight'] );
											unset( $element_attrs['sizing'][ $breakpoint ][ $state ]['aspectRatio'] );

											if ( $should_strip_sizing_width_for_default_sizing_style ) {
												unset( $element_attrs['sizing'][ $breakpoint ][ $state ]['width'] );
												unset( $element_attrs['sizing'][ $breakpoint ][ $state ]['maxWidth'] );
												unset( $element_attrs['sizing'][ $breakpoint ][ $state ]['minWidth'] );
											}
										}
									}

									return $element_attrs;
								},
								// Custom Image and Image Icon Styles.
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector'   => $blurb_icon_selector,
											'selectors'  => [
												'desktop' => [
													'value' => $blurb_icon_selector,
													'hover' => "{$args['orderClass']}{{:hover}} > .et_pb_blurb_content > .et_pb_main_blurb_image .et-pb-icon",
												],
											],
											'orderClass' => $args['orderClass'],
											'attr'       => $attrs['imageIcon']['advanced']['color'] ?? [],
											'property'   => 'color',
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $blurb_icon_selector,
											'attr'     => $attrs['imageIcon']['innerContent'] ?? [],
											'declarationFunction' => [ self::class, 'icon_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => $render_icon_width_props,
									],
									[
										'componentName' => 'divi/common',
										'props'         => $render_image_width_props,
									],
									[
										'componentName' => 'divi/image-sizing',
										'props'         => $render_image_height_props,
									],
									[
										'componentName' => 'divi/common',
										'props'         => $render_icon_sizing_alignment_props,
									],
									[
										'componentName' => 'divi/common',
										'props'         => $render_image_sizing_alignment_props,
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$args['orderClass']}.et_pb_blurb_position_left > .et_pb_blurb_content > .et_pb_main_blurb_image .et_pb_image_wrap.et_pb_only_image_mode_wrap, {$args['orderClass']}.et_pb_blurb_position_right > .et_pb_blurb_content > .et_pb_main_blurb_image .et_pb_image_wrap.et_pb_only_image_mode_wrap",
											'attr'     => $attrs['imageIcon']['decoration']['sizing'] ?? [],
											'declarationFunction' => [ self::class, 'image_max_width_style_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$blurb_image_wrap_selector} img",
											'attr'     => array_replace_recursive( [], $attrs['imageIcon']['innerContent'] ?? [], $attrs['imageIcon']['decoration']['sizing'] ?? [] ),
											'declarationFunction' => [ self::class, 'svg_style_declaration' ],
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
								'attrs' => [
									'border'     => $attrs['image']['border'] ?? [],
									'transition' => $attrs['transition'] ?? [],
								],
							],
						]
					),

					// Title.
					$elements->style(
						[
							'attrName' => 'title',
						]
					),

					// Content.
					$elements->style(
						[
							'attrName' => 'content',
						]
					),

					// Content Container.
					$elements->style(
						[
							'attrName' => 'contentContainer',
						]
					),

					// Module - Only for Custom CSS.
					CssStyle::style(
						[
							'selector'  => $args['orderClass'] . '.et_pb_blurb',
							'attr'      => $attrs['css'] ?? [],
							'cssFields' => self::custom_css(),
						]
					),
				],
			]
		);
	}

	/**
	 * Render callback for the Blurb module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ BlurbEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $child_modules_content       The block's content (child modules content).
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 * @param array          $default_printed_style_attrs Default printed style attributes.
	 *
	 * @throws Exception If the `imageIcon` attribute is not set.
	 *
	 * return string The HTML rendered output of the Blurb module.
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
	 * BlurbModule::render_callback( $attrs, $content, $block, $elements, $default_printed_style_attrs );
	 * ```
	 */
	public static function render_callback( array $attrs, string $child_modules_content, WP_Block $block, ModuleElements $elements, array $default_printed_style_attrs ): string {
		$has_image_src = ModuleUtils::has_value(
			$attrs['imageIcon']['innerContent'] ?? [],
			[
				'valueResolver' => function ( $value ) {
					return ! empty( $value['src'] );
				},
			]
		);

		$use_icon                        = $attrs['imageIcon']['innerContent']['desktop']['value']['useIcon'] ?? 'off';
		$image_icon_layout_display_value = $attrs['imageIcon']['decoration']['layout']['desktop']['value']['display'] ?? 'block';
		$image_icon_attr                 = $attrs['imageIcon'] ?? [];
		$image_icon_render_attr          = ImageWrapperAnimation::render_attr_without_animation( $image_icon_attr );
		$image_icon_layout_classnames    = [
			'et_flex_module' => 'flex' === $image_icon_layout_display_value,
			'et_grid_module' => 'grid' === $image_icon_layout_display_value,
		];

		// Icon.
		$is_icon_enabled = 'on' === $use_icon;
		$icon_value      = Utils::process_font_icon( $attrs['imageIcon']['innerContent']['desktop']['value']['icon'] ?? [] );
		$icon            = isset( $icon_value ) ? HTMLUtility::render(
			[
				'tag'               => 'span',
				'attributes'        => [
					'class' => HTMLUtility::classnames(
						[
							'et_pb_image_wrap' => true,
						],
						$image_icon_layout_classnames
					),
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $elements->render(
					[
						'attrName'      => 'imageIcon',
						'elementAttr'   => $image_icon_render_attr,
						'tagName'       => 'span',
						'attributes'    => [
							'class' => 'et-pb-icon',
						],
						'applyWpautop'  => false,
						'valueResolver' => function ( $value ) {
							// process_font_icon can return non-string values, so normalize to empty string.
							$process_font_icon = Utils::process_font_icon( $value['icon'] ?? [] );

							return is_string( $process_font_icon ) ? $process_font_icon : '';
						},
					]
				),
			]
		) : '';

		// Image.
		$image = ! $is_icon_enabled && $has_image_src ? HTMLUtility::render(
			[
				'tag'               => 'span',
				'attributes'        => [
					'class' => HTMLUtility::classnames(
						[
							'et_pb_image_wrap'           => true,
							'et_pb_only_image_mode_wrap' => true,
						],
						$image_icon_layout_classnames,
						BoxShadowClassnames::has_overlay( $attrs['imageIcon']['decoration']['boxShadow'] ?? [] )
					),
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => [
					$elements->style_components(
						[
							'attrName' => 'imageIcon',
						]
					),
					$elements->render(
						[
							'attrName'    => 'imageIcon',
							'elementAttr' => $image_icon_render_attr,
							'elementType' => 'image',
						]
					),
				],
			]
		) : '';

		$image_or_icon = $is_icon_enabled ? $icon : $image;

		// Image/Icon Link.
		$title_link        = $attrs['title']['innerContent']['desktop']['value']['url'] ?? '';
		$title_link_target = 'on' === ( $attrs['title']['innerContent']['desktop']['value']['target'] ?? '' ) ? '_blank' : null;
		$image_icon_link   = ! empty( $title_link ) && ! empty( $image_or_icon ) ? HTMLUtility::render(
			[
				'tag'               => 'a',
				'attributes'        => [
					'href'   => $title_link,
					'target' => $title_link_target,
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $image_or_icon,
			]
		) : $image_or_icon;

		// Image/Icon Container.
		$image_container = ! empty( $image_or_icon ) ? HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => HTMLUtility::classnames(
						'et_pb_main_blurb_image',
						ImageWrapperAnimation::wrapper_animation_classname( $image_icon_attr )
					),
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $image_icon_link,
			]
		) : '';

		// Check if the header has a value accross all breakpoints.
		$has_header_text = ModuleUtils::has_value(
			$attrs['title']['innerContent'] ?? [],
			[
				'valueResolver' => function ( $value ) {
					return ! empty( $value['text'] );
				},
			]
		);

		// Title.
		$header = $has_header_text ? $elements->render(
			[
				'attrName' => 'title',
			]
		) : '';

		// Content.
		$content = $elements->render(
			[
				'attrName' => 'content',
			]
		);

		// Header + Content.
		$header_n_content = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => 'et_pb_blurb_container',
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $header . $content,
			]
		);

		// Extract child modules IDs using helper utility.
		$children_ids = ChildrenUtils::extract_children_ids( $block );

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		// Layout classes for content container.
		// These classes are merged with the existing 'et_pb_blurb_content' class from metadata.
		$layout_display_value      = $attrs['module']['decoration']['layout']['desktop']['value']['display'] ?? 'flex';
		$content_container_classes = HTMLUtility::classnames(
			'et_pb_blurb_content',
			[
				'et_flex_module' => 'flex' === $layout_display_value,
				'et_grid_module' => 'grid' === $layout_display_value,
			]
		);

		return Module::render(
			[
				// FE only.
				'orderIndex'          => $block->parsed_block['orderIndex'],
				'storeInstance'       => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'attrs'               => $attrs,
				'id'                  => $block->parsed_block['id'],
				'elements'            => $elements,
				'name'                => $block->block_type->name,
				'classnamesFunction'  => [ self::class, 'module_classnames' ],
				'moduleCategory'      => $block->block_type->category,
				'stylesComponent'     => [ self::class, 'module_styles' ],
				'scriptDataComponent' => [ self::class, 'module_script_data' ],
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
						'attrName'   => 'contentContainer',
						'attributes' => [
							'class' => $content_container_classes,
						],
						'children'   => [
							$image_container,
							$header_n_content,
							$child_modules_content,
						],
					]
				),
			]
		);
	}

	/**
	 * Load the Blurb Module.
	 *
	 * This function is responsible for loading the BlurbModule and registering the necessary
	 * callbacks and endpoints for front-end rendering and REST API integration. It retrieves
	 * the path of the BlurbModule JSON folder and uses it to register the module with the
	 * ModuleRegistration class. The module is registered with the specified render callback
	 * function, which is a method within the BlurbModule class.
	 *
	 * @since ??
	 *
	 * @return void
	 *
	 * @example
	 * ```php
	 * $module_loader = new BlurbModule();
	 * $module_loader->load();
	 * ```
	 */
	public function load(): void {
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/blurb/';

		add_filter( 'divi_conversion_presets_attrs_map', [ BlurbPresetAttrsMap::class, 'get_map' ], 10, 2 );

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
