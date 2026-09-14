<?php
/**
 * Module Library: Group Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Group;

// phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames -- Reserved keywords used intentionally for clarity in utility functions.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP Core use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserBlock;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Element\ElementComponents;
use ET\Builder\Packages\ModuleLibrary\Group\GroupPresetAttrsMap;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\GlobalData\GlobalData;
use WP_Block;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;

/**
 * GroupModule class.
 *
 * This class implements the functionality of a group component in a frontend
 * application. It provides functions for rendering the group, managing REST
 * API endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class GroupModule implements DependencyInterface {

	/**
	 * Render styles component
	 *
	 * @param array $args {
	 *      An array of arguments.
	 *
	 *      @type ModuleElements   $elements The instance of ModuleElements class.
	 *      @type WP_Block         $block The block object.
	 *      @type BlockParserBlock $parent The parent block object.
	 *      @type int|null         $storeInstance The store instance.
	 *      @type array            $defaultPrintedStyleAttrs Optional. Default printed style attributes. Default empty array.
	 * }
	 * @return string
	 */
	public static function render_style_components( array $args ): string {
		$elements                    = $args['elements'];
		$block                       = $args['block'];
		$parent                      = $args['parent'];
		$store_instance              = $args['storeInstance'] ?? null;
		$default_printed_style_attrs = $args['defaultPrintedStyleAttrs'] ?? [];

		if ( $parent && 'divi/group' === $block->name && 'divi/section' === $parent->blockName ) {
			$group_id    = self::_get_group_attr_name( $block->parsed_block['id'], $parent->id );
			$children    = $parent->attrs['children'] ?? [];
			$children    = is_array( $children ) ? $children : [];
			$group_attrs = $children[ $group_id ] ?? [];

			// Only use section-stored attrs when present (e.g. from VB). For imported/converted
			// content (e.g. D4 fullwidth with Group), the Group has its own attrs and we fall
			// through to the default path.
			if ( ! empty( $group_attrs ) ) {
				$decoration_attr = $group_attrs['decoration'] ?? [];

				// Extract decoration boxShadow defaultPrintedStyleAttr using same pattern as module_styles().
				$default_printed_style_attr = $default_printed_style_attrs['module']['decoration']['boxShadow'] ?? [];

				return ElementComponents::component(
					[
						'id'                      => $block->parsed_block['id'],
						'attrs'                   => $decoration_attr,
						'defaultPrintedStyleAttr' => $default_printed_style_attr,

						// FE Only.
						'orderIndex'              => $block->orderIndex,
						'storeInstance'           => $store_instance,
					]
				);
			}
		}

		return $elements->style_components(
			[
				'attrName' => 'module',
			]
		);
	}

	/**
	 * Get the attribute key for a Group's attributes when stored in a Section's children.
	 *
	 * When a Group is a direct child of a Section (e.g. fullwidth or specialty section),
	 * the Section stores per-child attrs in attrs.children[childId]. This method returns
	 * the child ID used as the lookup key.
	 *
	 * @since ??
	 *
	 * @param string $group_id  The Group block's ID.
	 * @param string $parent_id The parent Section block's ID.
	 *
	 * @return string The key used to look up the group's attrs in parent.attrs.children.
	 */
	private static function _get_group_attr_name( string $group_id, string $parent_id ): string {
		return $group_id;
	}

	/**
	 * Render callback for the Group module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/GroupEdit}
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
	 * @return string The HTML rendered output of the Group module.
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
	 * GroupModule::render_callback( $attrs, $content, $block, $elements, $default_printed_style_attrs );
	 * ```
	 */
	public static function render_callback( array $attrs, string $content, WP_Block $block, ModuleElements $elements, array $default_printed_style_attrs ): string {
		$children_ids = $block->parsed_block['innerBlocks'] ? array_map(
			function ( $inner_block ) {
				return $inner_block['id'];
			},
			$block->parsed_block['innerBlocks']
		) : [];

		$is_last = BlockParserStore::is_last( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		// Check if this Group is inside a Group Carousel.
		$is_inside_group_carousel = $parent && 'divi/group-carousel' === $parent->blockName;

		// Get parent attrs if parent exists.
		$parent_attrs = $parent && isset( $parent->attrs ) ? $parent->attrs : [];

		$group_content = Module::render(
			[
				// FE only.
				'orderIndex'               => $block->parsed_block['orderIndex'],
				'storeInstance'            => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'attrs'                    => $attrs,
				'elements'                 => $elements,
				'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
				'classnamesFunction'       => [ self::class, 'module_classnames' ],
				'stylesComponent'          => function ( array $args ) use ( $block, $parent, $is_inside_group_carousel ): void {
					GroupModule::module_styles( $args, $block, $parent, $is_inside_group_carousel );
				},
				'scriptDataComponent'      => [ self::class, 'module_script_data' ],
				'htmlAttributesFunction'   => function ( array $params ) use ( $block, $parent ): array {
					return GroupModule::module_html_attributes( $params, $block, $parent, $block->parsed_block['storeInstance'] );
				},
				'id'                       => $block->parsed_block['id'],
				'isLast'                   => $is_last,
				'childrenIds'              => $children_ids,
				'name'                     => $block->block_type->name,
				'moduleCategory'           => $block->block_type->category,
				'parentAttrs'              => $parent_attrs,
				'parentId'                 => $parent ? $parent->id : '',
				'parentName'               => $parent ? $parent->blockName : '',
				'children'                 => [
					self::render_style_components(
						[
							'elements'                 => $elements,
							'block'                    => $block,
							'parent'                   => $parent,
							'storeInstance'            => $block->parsed_block['storeInstance'],
							'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
						]
					),
					$content,
				],
			]
		);

		// If this Group is inside a Group Carousel, wrap it in a carousel slide wrapper.
		if ( $is_inside_group_carousel ) {
			return sprintf(
				'<div class="et_pb_group_carousel_slide">%s</div>',
				$group_content
			);
		}

		// Otherwise, return the Group content directly.
		return $group_content;
	}

	/**
	 * Group module front-end render_block_data filter.
	 *
	 * @since ??
	 *
	 * @param array         $parsed_block The block being rendered.
	 * @param array         $source_block An un-modified copy of $parsed_block, as it appeared in the source content.
	 * @param null|WP_Block $parent_block If this is a nested block, a reference to the parent block.
	 *
	 * @return array Filtered block that being rendered.
	 */
	public static function render_block_data( array $parsed_block, array $source_block, ?WP_Block $parent_block ): array {
		if ( 'divi/group' !== $parsed_block['blockName'] ) {
			return $parsed_block;
		}

		/**
		 * Pass custom attribute into attrs if current module is section-group (direct child
		 * of specialty section) so group knows that it is section-group, not regular group.
		 *
		 * @since ??
		 *
		 * @see https://github.com/elegantthemes/submodule-builder-5/blob/9d27e56991790d438a3bc89faa6abd22a3615a2a/visual-builder/packages/module/src/layout/components/child-modules/component.tsx#L129-L139
		 */
		if ( ! isset( $parsed_block['attrs']['sectionType'] ) ) {
			if ( 'divi/section' === BlockParserStore::get_parent( $parsed_block['id'], $parsed_block['storeInstance'] )->blockName ) {
				$parsed_block['attrs']['sectionType'] = [
					'desktop' => [
						'value' => 'specialty',
					],
				];
			}
		}

		return $parsed_block;
	}

	/**
	 * Generate classnames for the module.
	 *
	 * This function generates classnames for the module based on the provided
	 * arguments. It is used in the `render_callback` function of the Group module.
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
	 *     @type string $name               Nodule name.
	 *     @type bool   $isLast             Whether this item is the last child.
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
	 * GroupModule::module_classnames($args);
	 * ```
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];
		$is_last             = $args['isLast'];

		$classnames_instance->add( 'et-last-child', $is_last );

		$classnames_instance->add( 'et_pb_group', true );
		$classnames_instance->add( 'et_pb_module', true );

		// Add flex and grid group classes based on layout display.
		$layout_value         = $attrs['module']['decoration']['layout']['desktop']['value']['display'] ?? 'flex';
		$is_group_flex_layout = 'flex' === $layout_value;
		$is_group_grid_layout = 'grid' === $layout_value;
		$classnames_instance->add( 'et_flex_group', $is_group_flex_layout );
		$classnames_instance->add( 'et_grid_group', $is_group_grid_layout );

		$has_mix_blend_mode   = ! empty( $attrs['module']['decoration']['filters']['desktop']['value']['blendMode'] );
		$mix_blend_class_name = $has_mix_blend_mode ? 'et_pb_css_mix_blend_mode' : 'et_pb_css_mix_blend_mode_passthrough';

		// Groups need to pass through if no mix-blend mode is selected.
		$classnames_instance->add( $mix_blend_class_name );

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
	 * Group Module's style components.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /docs/builder-api/js/module-library/module-styles moduleStyles}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array            $args {
	 *                An array of arguments.
	 *
	 *     @type string         $id                       Module ID. In VB, the ID of module is UUIDV4. In FE, the ID is order index.
	 *     @type string         $name                     Module name.
	 *     @type string         $attrs                    Module attributes.
	 *     @type string         $defaultPrintedStyleAttrs Default printed style attributes.
	 *     @type string         $parentAttrs              Parent attrs.
	 *     @type string         $orderClass               Selector class name.
	 *     @type string         $parentOrderClass         Parent selector class name.
	 *     @type string         $wrapperOrderClass        Wrapper selector class name.
	 *     @type string         $settings                 Custom settings.
	 *     @type string         $state                    Attributes state.
	 *     @type string         $mode                     Style mode.
	 *     @type int            $orderIndex               Module order index.
	 *     @type int            $storeInstance            The ID of instance where this block stored in BlockParserStore class.
	 *     @type ModuleElements $elements                 ModuleElements instance.
	 * }
	 * @param WP_Block         $block                    Optional. The block object.
	 * @param BlockParserBlock $parent                   Optional. The parent block object.
	 * @param bool             $is_inside_group_carousel Optional. Whether this group is inside a group carousel.
	 *
	 * @return void
	 */
	public static function module_styles( array $args, ?WP_Block $block = null, ?BlockParserBlock $parent = null, bool $is_inside_group_carousel = false ): void {
		$attrs    = $args['attrs'] ?? [];
		$elements = $args['elements'];
		$settings = $args['settings'] ?? [];

		$default_printed_style_attrs = $args['defaultPrintedStyleAttrs'] ?? [];

		// If this Group is inside a Group Carousel, we need to generate stronger CSS selectors.
		// to ensure they can override the parent carousel's CSS.
		$style_props = [
			'disabledOn'               => [
				'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
			],
			'zIndex'                   => [
				'important' => true,
			],
			'defaultPrintedStyleAttrs' => $default_printed_style_attrs['module']['decoration'] ?? [],
			'advancedStyles'           => [
				[
					'componentName' => 'divi/css',
					'props'         => [
						'attr' => $attrs['css'] ?? [],
					],
				],
				[
					'componentName' => 'divi/common',
					'props'         => [
						'attr'                => $attrs['module']['decoration']['border'] ?? [],
						'declarationFunction' => function ( $params ) use ( $attrs ) {
											$overflow_attr = $attrs['module']['decoration']['overflow'] ?? [];
											return Declarations::overflow_for_border_radius_style_declaration( $params, $overflow_attr );
						},
					],
				],
			],
		];

		// Add stronger selector when inside Group Carousel.
		if ( $is_inside_group_carousel && $parent ) {
			$parent_order_class = ModuleUtils::get_module_order_class_name( $parent->id, $args['storeInstance'] );
			if ( $parent_order_class ) {
				// Use same logic as Module.php for consistency.
				$selector_prefix = Conditions::is_custom_post_type() ? '.et-db #et-boc .et-l ' : '';

				// Use baseOrderClass which contains the clean group class.
				$group_class             = $args['baseOrderClass'] ?? '';
				$style_props['selector'] = "{$selector_prefix}.et_pb_module.{$parent_order_class} {$group_class}";
			}
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
							'styleProps' => $style_props,
						]
					),
				],
			]
		);
	}

	/**
	 * Group module script data.
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
	 * GroupModule::module_script_data( $args );
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
	}

	/**
	 * Retrieves HTML attributes for a module based on the block and parent block names.
	 *
	 * @since ??
	 *
	 * @param array            $params The original params array passed by the the `htmlAttributesFunction` function.
	 * @param WP_Block         $block The object of the current block.
	 * @param BlockParserBlock $parent The object of the parent block.
	 * @param int              $store_instance The block parser store instance.
	 *
	 * @return array An array contains the id and classNames of the module.
	 */
	public static function module_html_attributes( array $params, WP_Block $block, BlockParserBlock $parent, ?int $store_instance = null ): array {
		return [
			'id'         => $params['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] ?? '',
			'classNames' => $params['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['class'] ?? '',
		];
	}

	/**
	 * Loads `GroupModule` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		// phpcs:ignore PHPCompatibility.FunctionUse.NewFunctionParameters.dirname_levelsFound -- We have PHP 7 support now, This can be deleted once PHPCS config is updated.
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/group/';

		add_filter( 'divi_conversion_presets_attrs_map', [ GroupPresetAttrsMap::class, 'get_map' ], 10, 2 );

		add_filter(
			'render_block_data',
			[ self::class, 'render_block_data' ],
			10,
			3
		);

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
