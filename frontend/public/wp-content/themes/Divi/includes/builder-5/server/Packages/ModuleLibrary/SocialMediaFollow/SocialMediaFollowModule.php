<?php
/**
 * Module: Social Media Follow class.
 *
 * @package ET\Builder\Packages\ModuleLibrary\SocialMediaFollow
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\SocialMediaFollow;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\ArrayUtility;
use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\GlobalData\GlobalPresetItemGroup;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;

/**
 * `SocialMediaFollow` is consisted of functions used for Social Media Follow such as Front-End rendering, REST API Endpoints etc.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 */
class SocialMediaFollowModule implements DependencyInterface {

	/**
	 * Module classnames function for Social Media Follow module.
	 *
	 * This function is equivalent of JS function moduleClassnames located in
	 * visual-builder/packages/module-library/src/components/social-media-follow/module-classnames.ts.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type object $classnamesInstance Instance of ET\Builder\Packages\Module\Layout\Components\Classnames.
	 *     @type array  $attrs              Block attributes data that being rendered.
	 * }
	 */
	public static function module_classnames( $args ) {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'] ?? [];

		// Only add clearfix class when using Block layout (float-based).
		// For Flex and Grid layouts, clearfix is unnecessary and can interfere with custom CSS.
		$layout_display  = $attrs['module']['decoration']['layout']['desktop']['value']['display'] ?? 'flex';
		$is_block_layout = 'block' === $layout_display;

		$classnames_instance->add( 'clearfix', $is_block_layout );

		// Text Options classnames.
		$text_options_classnames = TextClassnames::text_options_classnames( $attrs['module']['advanced']['text'] ?? [] );

		if ( $text_options_classnames ) {
			$classnames_instance->add( $text_options_classnames, true );
		}

		$has_follow_button = 'on' === ( $attrs['socialNetwork']['advanced']['followButton']['desktop']['value'] ?? 'off' );
		$classnames_instance->add( 'has_follow_button', $has_follow_button );

		// Module.
		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					// phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
					// TODO feat(D5, Module Attribute Refactor) Once link is merged as part of decoration property, remove this.
					'attrs'  => array_merge(
						$attrs['module']['decoration'] ?? [],
						[
							'link' => $attrs['module']['advanced']['link'] ?? [],
						]
					),
					'border' => false,
				]
			)
		);
	}

	/**
	 * Set script data of used module options.
	 *
	 * This function is equivalent of JS function ModuleScriptData located in
	 * visual-builder/packages/module-library/src/components/social-media-follow/module-script-data.tsx.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *   Array of arguments.
	 *
	 *   @type string         $id            Module id.
	 *   @type string         $name          Module name.
	 *   @type string         $selector      Module selector.
	 *   @type array          $attrs         Module attributes.
	 *   @type int            $storeInstance The ID of instance where this block stored in BlockParserStore class.
	 *   @type ModuleElements $elements      ModuleElements instance.
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
				'attrName'        => 'module',
				'scriptDataProps' => [
					'animation' => [
						'selector' => $selector,
					],
				],
			]
		);

		MultiViewScriptData::set(
			[
				'id'            => $id,
				'name'          => $name,
				'storeInstance' => $store_instance,
				'selector'      => $selector,
				'setClassName'  => [
					[
						'data'          => [
							'has_follow_button' => $attrs['socialNetwork']['advanced']['followButton'] ?? [],
						],
						'valueResolver' => function ( $value, $resolver_args ) {
							return 'has_follow_button' === $resolver_args['className'] && 'on' === ( $value ?? '' ) ? 'add' : 'remove';
						},
					],
				],
			]
		);
	}

	/**
	 * Social Media Follow render callback which outputs server side rendered HTML on the Front-End.
	 *
	 * This function is equivalent of JS function SocialMediaFollowEdit located in
	 * visual-builder/packages/module-library/src/components/social-media-follow/edit.tsx.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by VB.
	 * @param string         $content                     Block content.
	 * @param WP_Block       $block                       Parsed block object that being rendered.
	 * @param ModuleElements $elements                    ModuleElements instance.
	 * @param array          $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string HTML rendered of Social Media Follow module.
	 */
	public static function render_callback( $attrs, $content, $block, $elements, $default_printed_style_attrs ) {
		$children_ids = $block->parsed_block['innerBlocks'] ? array_map(
			function ( $inner_block ) {
				return $inner_block['id'];
			},
			$block->parsed_block['innerBlocks']
		) : [];

		$children = '';

		$module_components = $elements->style_components(
			[
				'attrName' => 'module',
			]
		);

		if ( $module_components ) {
			$children .= $module_components;
		}

		$children .= $content;

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		return Module::render(
			[
				// FE only.
				'orderIndex'               => $block->parsed_block['orderIndex'],
				'storeInstance'            => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'id'                       => $block->parsed_block['id'],
				'name'                     => $block->block_type->name,
				'tag'                      => 'ul',
				'moduleCategory'           => $block->block_type->category,
				'attrs'                    => $attrs,
				'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
				'elements'                 => $elements,
				'classnamesFunction'       => [ self::class, 'module_classnames' ],
				'scriptDataComponent'      => [ self::class, 'module_script_data' ],
				'stylesComponent'          => [ self::class, 'module_styles' ],
				'parentId'                 => $parent->id ?? '',
				'parentName'               => $parent->blockName ?? '',
				'parentAttrs'              => $parent->attrs ?? [],
				'children'                 => $children,
				'childrenIds'              => $children_ids,
			]
		);
	}

	/**
	 * Custom CSS fields
	 *
	 * This function is equivalent of JS const cssFields located in
	 * visual-builder/packages/module-library/src/components/social-media-follow/custom-css.ts.
	 *
	 * A minor difference with the JS const cssFields, this function did not have `label` property on each array item.
	 *
	 * @since ??
	 */
	public static function custom_css() {
		return \WP_Block_Type_Registry::get_instance()->get_registered( 'divi/social-media-follow' )->customCssFields;
	}

	/**
	 * Icon style declaration for social media follow module.
	 *
	 * This function will declare Icon style for Social Media Follow module.
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array      $attrValue  The value (breakpoint > state > value) of module attribute.
	 *     @type bool|array $important  If set to true, the CSS will be added with !important.
	 *     @type string     $returnType This is the type of value that the function will return. Can be either string or key_value_pair.
	 *     @type array      $attr       Optional. The full attribute object for inheritance processing. Default `[]`.
	 *     @type string     $breakpoint Optional. Current breakpoint for inheritance processing. Default `desktop`.
	 *     @type string     $state      Optional. Current state for inheritance processing. Default `value`.
	 * }
	 *
	 * @since ??
	 */
	public static function icon_size_style_declaration( $params ) {
		$args = wp_parse_args(
			$params,
			[
				'important'  => false,
				'returnType' => 'string',
				'breakpoint' => 'desktop',
				'state'      => 'value',
			]
		);

		$attr_value  = $args['attrValue'];
		$important   = $args['important'];
		$return_type = $args['returnType'];
		$attr        = $args['attr'] ?? [];
		$breakpoint  = $args['breakpoint'];
		$state       = $args['state'];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => $return_type,
				'important'  => [
					'font-size'   => true,
					'position'    => true,
					'top'         => true,
					'left'        => true,
					'transform'   => true,
					'display'     => true,
					'width'       => true,
					'height'      => true,
					'line-height' => true,
					'text-align'  => true,
				],
			]
		);

		// Use ModuleUtils::use_attr_value() for proper state inheritance.
		// This follows the established codebase pattern and handles all edge cases internally.
		// The utility will return defaultValue when inheritance is not applicable or available.
		$final_attr_value = ModuleUtils::use_attr_value(
			[
				'attr'         => $attr,
				'breakpoint'   => $breakpoint,
				'state'        => $state,
				'mode'         => 'getAndInheritAll',
				'defaultValue' => $attr_value,
			]
		);

		$use_size = $final_attr_value['useSize'] ?? '';
		$size     = $final_attr_value['size'] ?? '';

		if ( 'on' === $use_size && ! empty( $size ) && '' !== $size ) {
			$resolved_size = GlobalData::resolve_global_variable_value( $size );
			$parsed_size   = SanitizerUtility::numeric_parse_value( $resolved_size );
			// numeric_parse_value returns null for CSS math functions, variables, and some keywords; still emit layout + font-size using the resolved string (see ToggleModule::icon_style_declaration).
			$is_pass_through = ModuleUtils::is_css_math_function( $resolved_size )
				|| ModuleUtils::is_css_variable( $resolved_size )
				|| ModuleUtils::is_css_keyword( $resolved_size );

			if ( $parsed_size || $is_pass_through ) {
				$style_declarations->add( 'font-size', $resolved_size );
				$style_declarations->add( 'position', 'absolute' );
				$style_declarations->add( 'top', '50%' );
				$style_declarations->add( 'left', '50%' );
				$style_declarations->add( 'transform', 'translate(-50%, -50%)' );
				$style_declarations->add( 'display', 'block' );
				$style_declarations->add( 'width', $resolved_size );
				$style_declarations->add( 'height', $resolved_size );
				$style_declarations->add( 'line-height', $resolved_size );
				$style_declarations->add( 'text-align', 'center' );
			}
		}

		return $style_declarations->value();
	}

	/**
	 * Icon dimension style declaration for social media follow icon.
	 *
	 * This function will declare Icon dimension style style for Social Media Follow module.
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array      $attrValue  The value (breakpoint > state > value) of module attribute.
	 *     @type bool|array $important  If set to true, the CSS will be added with !important.
	 *     @type string     $returnType This is the type of value that the function will return. Can be either string or key_value_pair.
	 *     @type array      $attr       Optional. The full attribute object for inheritance processing. Default `[]`.
	 *     @type string     $breakpoint Optional. Current breakpoint for inheritance processing. Default `desktop`.
	 *     @type string     $state      Optional. Current state for inheritance processing. Default `value`.
	 * }
	 *
	 * @since ??
	 */
	public static function icon_dimension_style_declaration( $params ) {
		$args = wp_parse_args(
			$params,
			[
				'important'  => false,
				'returnType' => 'string',
				'breakpoint' => 'desktop',
				'state'      => 'value',
			]
		);

		$attr_value  = $args['attrValue'];
		$important   = $args['important'];
		$return_type = $args['returnType'];
		$attr        = $args['attr'] ?? [];
		$breakpoint  = $args['breakpoint'];
		$state       = $args['state'];

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => $return_type,
				'important'  => [
					'width'    => true,
					'height'   => true,
					'position' => true,
				],
			]
		);

		// Use ModuleUtils::use_attr_value() for proper state inheritance.
		// This follows the established codebase pattern and handles all edge cases internally.
		// The utility will return defaultValue when inheritance is not applicable or available.
		$final_attr_value = ModuleUtils::use_attr_value(
			[
				'attr'         => $attr,
				'breakpoint'   => $breakpoint,
				'state'        => $state,
				'mode'         => 'getAndInheritAll',
				'defaultValue' => $attr_value,
			]
		);

		$use_size = $final_attr_value['useSize'] ?? '';
		$size     = $final_attr_value['size'] ?? '';

		if ( 'on' === $use_size && ! empty( $size ) && '' !== $size ) {
			$resolved_size = GlobalData::resolve_global_variable_value( $size );
			$parsed_size   = SanitizerUtility::numeric_parse_value( $resolved_size );
			$trimmed_size  = trim( $resolved_size );

			if ( $parsed_size ) {
				$container_width  = $parsed_size['valueNumber'] * 2 . $parsed_size['valueUnit'];
				$container_height = $parsed_size['valueNumber'] * 2 . $parsed_size['valueUnit'];

				$style_declarations->add( 'width', $container_width );
				$style_declarations->add( 'height', $container_height );
				$style_declarations->add( 'position', 'relative' );
			} elseif ( ModuleUtils::is_css_math_function( $resolved_size ) || ModuleUtils::is_css_variable( $resolved_size ) ) {
				$doubled = 'calc(2 * (' . $trimmed_size . '))';
				$style_declarations->add( 'width', $doubled );
				$style_declarations->add( 'height', $doubled );
				$style_declarations->add( 'position', 'relative' );
			} elseif ( ModuleUtils::is_css_keyword( $resolved_size ) ) {
				$style_declarations->add( 'width', $trimmed_size );
				$style_declarations->add( 'height', $trimmed_size );
				$style_declarations->add( 'position', 'relative' );
			}
		}

		return $style_declarations->value();
	}

	/**
	 * Content alignment style declaration
	 *
	 * This function will declare content alignment style for Social Media Follow module.
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array      $attrValue  The value (breakpoint > state > value) of module attribute.
	 *     @type bool|array $important  If set to true, the CSS will be added with !important.
	 *     @type string     $returnType This is the type of value that the function will return. Can be either string or key_value_pair.
	 * }
	 *
	 * @since ??
	 */
	public static function alignment_style_declaration( $params ) {
		$alignment = $params['attrValue']['orientation'] ?? '';

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		if ( ! empty( $alignment ) ) {
			$style_declarations->add( 'text-align', $alignment );
		}

		return $style_declarations->value();
	}

	/**
	 * Hide ::after pseudo-element when module is in flex layout.
	 *
	 * This function returns CSS declaration to hide the ::after pseudo-element
	 * when the module is in a flex container (either parent is flex or module itself is flex),
	 * preventing flex gap issues.
	 *
	 * @param array $params Style declaration params.
	 *
	 * @since ??
	 *
	 * @return string CSS declaration string.
	 */
	public static function hide_after_pseudo_element_declaration( $params ) {
		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => false,
			]
		);

		$style_declarations->add( 'content', 'none' );
		$style_declarations->add( 'display', 'none' );

		return $style_declarations->value();
	}

	/**
	 * SocialMediaFollow Module's style components.
	 *
	 * This function is equivalent of JS function ModuleStyles located in
	 * visual-builder/packages/module-library/src/components/social-media-follow/styles.tsx.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *       @type string         $id                Module ID. In VB, the ID of module is UUIDV4. In FE, the ID is order index.
	 *       @type string         $name              Module name.
	 *       @type string         $attrs             Module attributes.
	 *       @type string         $parentAttrs       Parent attrs.
	 *       @type string         $orderClass        Selector class name.
	 *       @type string         $parentOrderClass  Parent selector class name.
	 *       @type string         $wrapperOrderClass Wrapper selector class name.
	 *       @type string         $settings          Custom settings.
	 *       @type string         $state             Attributes state.
	 *       @type string         $mode              Style mode.
	 *       @type ModuleElements $elements          ModuleElements instance.
	 * }
	 */
	public static function module_styles( $args ) {
		$attrs    = $args['attrs'] ?? [];
		$elements = $args['elements'];
		$settings = $args['settings'] ?? [];

		$default_printed_style_attrs = $args['defaultPrintedStyleAttrs'] ?? [];

		$is_parent_flex_layout = method_exists( $elements, 'get_is_parent_flex_layout' ) ? $elements->get_is_parent_flex_layout() : false;

		$module_settings         = ModuleRegistration::get_module_settings( $args['name'] );
		$has_layout_option_group = $module_settings && isset( $module_settings->attributes['module']['settings']['decoration']['layout'] );
		$layout_value            = $attrs['module']['decoration']['layout']['desktop']['value']['display'] ?? 'flex';
		$is_module_flex_layout   = $has_layout_option_group && 'flex' === $layout_value;

		$should_hide_after = $is_parent_flex_layout || $is_module_flex_layout;

		// Build icon advanced styles array.
		// Parent module renders icon size styles without exclusion, just like other modules.
		// The declaration function handles conditional logic (checks useSize and size values).
		$icon_advanced_styles = [
			[
				'componentName' => 'divi/common',
				'props'         => [
					'selector' => "{$args['orderClass']} li.et_pb_social_icon a.icon:before",
					'attr'     => $attrs['icon']['advanced']['color'] ?? [],
					'property' => 'color',
				],
			],
			[
				'componentName' => 'divi/common',
				'props'         => [
					'selectors'           => [
						'desktop' => [
							'value' => "{$args['orderClass']} li.et_pb_social_icon a.icon:before",
							'hover' => implode(
								', ',
								[
									"{$args['orderClass']} li.et_pb_social_icon:hover a.icon:before",
									"{$args['orderClass']} li.et_pb_social_icon a.icon:hover:before",
									"{$args['orderClass']}.et_vb_hover li.et_pb_social_icon a.icon:before",
								]
							),
						],
						'tablet'  => [
							'value' => "{$args['orderClass']} li.et_pb_social_icon a.icon:before",
							'hover' => implode(
								', ',
								[
									"{$args['orderClass']} li.et_pb_social_icon:hover a.icon:before",
									"{$args['orderClass']} li.et_pb_social_icon a.icon:hover:before",
									"{$args['orderClass']}.et_vb_hover li.et_pb_social_icon a.icon:before",
								]
							),
						],
						'phone'   => [
							'value' => "{$args['orderClass']} li.et_pb_social_icon a.icon:before",
							'hover' => implode(
								', ',
								[
									"{$args['orderClass']} li.et_pb_social_icon:hover a.icon:before",
									"{$args['orderClass']} li.et_pb_social_icon a.icon:hover:before",
									"{$args['orderClass']}.et_vb_hover li.et_pb_social_icon a.icon:before",
								]
							),
						],
					],
					'attr'                => $attrs['icon']['advanced']['size'] ?? [],
					'declarationFunction' => [
						self::class,
						'icon_size_style_declaration',
					],
				],
			],
			[
				'componentName' => 'divi/common',
				'props'         => [
					'selectors'           => [
						'desktop' => [
							'value' => "{$args['orderClass']} li a.icon",
							'hover' => implode(
								', ',
								[
									"{$args['orderClass']} li.et_pb_social_icon:hover a.icon",
									"{$args['orderClass']} li.et_pb_social_icon a.icon:hover",
									"{$args['orderClass']}.et_vb_hover li.et_pb_social_icon a.icon",
								]
							),
						],
						'tablet'  => [
							'value' => "{$args['orderClass']} li a.icon",
							'hover' => implode(
								', ',
								[
									"{$args['orderClass']} li.et_pb_social_icon:hover a.icon",
									"{$args['orderClass']} li.et_pb_social_icon a.icon:hover",
									"{$args['orderClass']}.et_vb_hover li.et_pb_social_icon a.icon",
								]
							),
						],
						'phone'   => [
							'value' => "{$args['orderClass']} li a.icon",
							'hover' => implode(
								', ',
								[
									"{$args['orderClass']} li.et_pb_social_icon:hover a.icon",
									"{$args['orderClass']} li.et_pb_social_icon a.icon:hover",
									"{$args['orderClass']}.et_vb_hover li.et_pb_social_icon a.icon",
								]
							),
						],
					],
					'attr'                => $attrs['icon']['advanced']['size'] ?? [],
					'declarationFunction' => [
						self::class,
						'icon_dimension_style_declaration',
					],
				],
			],
		];

		$styles = [
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
									'attr'                => $attrs['module']['advanced']['text'] ?? [],
									'declarationFunction' => [ self::class, 'alignment_style_declaration' ],
								],
							],
							[
								'componentName' => 'divi/text',
								'props'         => [
									'attr' => $attrs['module']['advanced']['text'] ?? [],
								],
							],
						],
					],
				]
			),

			// Icon.
			$elements->style(
				[
					'attrName'   => 'icon',
					'styleProps' => [
						'advancedStyles' => $icon_advanced_styles,
					],
				]
			),

			// Button.
			$elements->style(
				[
					'attrName' => 'button',
				]
			),
		];

		// Module - Only for Custom CSS.
		// Custom CSS must come AFTER design styles so it can override them.
		$styles[] = CssStyle::style(
			[
				'selector'  => $args['orderClass'],
				'attr'      => $attrs['css'] ?? [],
				'cssFields' => self::custom_css(),
			]
		);

		// Hide ::after pseudo-element when module is in flex layout to prevent flex gap issues.
		// This must come AFTER Custom CSS to ensure it overrides any Custom CSS that targets ::after.
		if ( $should_hide_after ) {
			$styles[] = $elements->style(
				[
					'attrName'   => 'module',
					'styleProps' => [
						'advancedStyles' => [
							[
								'componentName' => 'divi/common',
								'props'         => [
									'selector'            => "{$args['orderClass']}:after",
									'important'           => false,
									'attr'                => [
										'desktop' => [
											'value' => 'enabled',
										],
									],
									'declarationFunction' => [ self::class, 'hide_after_pseudo_element_declaration' ],
								],
							],
						],
					],
				]
			);
		}

		Style::add(
			[
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => $styles,
			]
		);
	}

	/**
	 * Loads `SocialMediaFollow` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load() {
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/social-media-follow/';

		add_filter( 'divi_conversion_presets_attrs_map', [ SocialMediaFollowPresetAttrsMap::class, 'get_map' ], 10, 2 );

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
