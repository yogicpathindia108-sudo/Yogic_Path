<?php
/**
 * ModuleLibrary: Canvas Portal Module class.
 *
 * @package Builder\ModuleLibrary\CanvasPortalModule
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\CanvasPortal;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\Module\Layout\Components\StyleCommon\CommonStyle;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\ModuleUtils\CanvasUtils;
use ET\Builder\Packages\ModuleLibrary\CanvasPortal\CanvasPortalPresetAttrsMap;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;

/**
 * `CanvasPortalModule` is consisted of functions used for Canvas Portal Module such as Front-End rendering, REST API Endpoints etc.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 */
class CanvasPortalModule implements DependencyInterface {
	/**
	 * Pre-populate canvas content cache with batch-fetched canvas content.
	 * This allows render_callback() to reuse cached content instead of fetching again.
	 *
	 * @since ??
	 *
	 * @param array $canvas_content_map Map of canvas_id => post_content.
	 * @param int   $post_id Post ID for cache key.
	 *
	 * @return void
	 */
	public static function pre_populate_canvas_content_cache( array $canvas_content_map, int $post_id ): void {
		CanvasUtils::pre_populate_cache( $canvas_content_map, $post_id );
	}

	/**
	 * Custom CSS fields
	 *
	 * This function is equivalent of JS const cssFields located in
	 * visual-builder/packages/module-library/src/components/canvas-portal/custom-css.ts.
	 *
	 * A minor difference with the JS const cssFields, this function did not have `label` property on each array item.
	 *
	 * @since ??
	 */
	public static function custom_css() {
		return \WP_Block_Type_Registry::get_instance()->get_registered( 'divi/canvas-portal' )->customCssFields;
	}


	/**
	 * Canvas Portal Module's style components.
	 *
	 * This function is equivalent of JS function ModuleStyles located in
	 * visual-builder/packages/module-library/src/components/canvas-portal/module-styles.tsx.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *      @type string         $id                Module ID. In VB, the ID of module is UUIDV4. In FE, the ID is order index.
	 *      @type string         $name              Module name.
	 *      @type string         $attrs             Module attributes.
	 *      @type string         $parentAttrs       Parent attrs.
	 *      @type string         $orderClass        Selector class name.
	 *      @type string         $parentOrderClass  Parent selector class name.
	 *      @type string         $wrapperOrderClass Wrapper selector class name.
	 *      @type string         $settings          Custom settings.
	 *      @type string         $state             Attributes state.
	 *      @type string         $mode              Style mode.
	 *      @type ModuleElements $elements          ModuleElements instance.
	 * }
	 */
	public static function module_styles( array $args ): void {
		$attrs       = $args['attrs'] ?? [];
		$elements    = $args['elements'];
		$order_class = $args['orderClass'] ?? '';

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
									'disabledModuleVisibility' => $args['settings']['disabledModuleVisibility'] ?? null,
								],
								'advancedStyles' => [
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

					// Placed the very end only for custom css.
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
	 * Module classnames function for Canvas Portal module.
	 *
	 * This function is the equivalent of the `moduleClassnames` JS function located in
	 * visual-builder/packages/module-library/src/components/canvas-portal/module-classnames.ts.
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
		$attrs               = $args['attrs'];

		$classnames_instance->add( 'et_pb_canvas_portal', true );

		// Module.
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
	 * Canvas Portal module script data.
	 *
	 * This function generates and sets the script data for the module,
	 * which includes animations, interactions, and other frontend script data.
	 *
	 * This function is equivalent of JS function ModuleScriptData located in
	 * visual-builder/packages/module-library/src/components/canvas-portal/module-script-data.tsx.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type string         $id            Module ID.
	 *     @type string         $name          Module name.
	 *     @type array          $attrs         Module attributes.
	 *     @type string         $selector      CSS selector.
	 *     @type ModuleElements $elements      ModuleElements instance.
	 *     @type string|null    $storeInstance Store instance ID.
	 *     @type int|null       $orderIndex    Order index.
	 * }
	 *
	 * @return void
	 */
	public static function module_script_data( array $args ): void {
		// Assign variables.
		$elements = $args['elements'];

		// Element Script Data Options.
		// This handles animations, interactions, scroll effects, sticky, link, and background script data.
		$elements->script_data(
			[
				'attrName' => 'module',
			]
		);
	}

	/**
	 * Build render arguments array for Module::render().
	 *
	 * @since ??
	 *
	 * @param \WP_Block      $block                        Parsed block object that being rendered.
	 * @param array          $attrs                        Block attributes that were saved by VB.
	 * @param array          $default_printed_style_attrs  Default printed style attributes.
	 * @param ModuleElements $elements                     ModuleElements instance.
	 * @param object         $parent_block                 Parent block object.
	 * @param array          $children_ids                 Array of child module IDs. Default empty array.
	 * @param string         $rendered_content             Rendered content to append. Default empty string.
	 *
	 * @return array Render arguments array.
	 */
	private static function _build_render_args( $block, $attrs, $default_printed_style_attrs, $elements, $parent_block, $children_ids = [], $rendered_content = '' ) {
		return [
			'orderIndex'               => $block->parsed_block['orderIndex'],
			'storeInstance'            => $block->parsed_block['storeInstance'],
			'attrs'                    => $attrs,
			'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
			'id'                       => $block->parsed_block['id'],
			'elements'                 => $elements,
			'name'                     => $block->block_type->name,
			'classnamesFunction'       => [ self::class, 'module_classnames' ],
			'moduleCategory'           => $block->block_type->category,
			'stylesComponent'          => [ self::class, 'module_styles' ],
			'scriptDataComponent'      => [ self::class, 'module_script_data' ],
			'parentAttrs'              => $parent_block->attrs ?? [],
			'parentId'                 => $parent_block->id ?? '',
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP uses camelCase in \WP_Block_Parser_Block
			'parentName'               => $parent_block->blockName ?? '',
			'childrenIds'              => $children_ids,
			'children'                 => $elements->style_components(
				[
					'attrName' => 'module',
				]
			) . $rendered_content,
		];
	}

	/**
	 * Canvas Portal module render callback which outputs server side rendered HTML on the Front-End.
	 *
	 * This function is the equivalent of JS function CanvasPortalEdit located in
	 * visual-builder/packages/module-library/src/components/canvas-portal/edit.tsx.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                        Block attributes that were saved by VB.
	 * @param string         $content                      Block content.
	 * @param \WP_Block      $block                        Parsed block object that being rendered.
	 * @param ModuleElements $elements                     ModuleElements instance.
	 * @param array          $default_printed_style_attrs  Default printed style attributes.
	 *
	 * @return string HTML rendered of Canvas Portal module.
	 */
	public static function render_callback( $attrs, $content, $block, $elements, $default_printed_style_attrs ) {
		// Get selected canvas ID from attributes.
		$canvas_id = $attrs['canvas']['advanced']['canvasId']['desktop']['value'] ?? '';

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		if ( empty( $canvas_id ) ) {
			// No canvas selected, render empty portal.
			return Module::render(
				self::_build_render_args( $block, $attrs, $default_printed_style_attrs, $elements, $parent )
			);
		}

		// Get current post ID.
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			$post_id = get_queried_object_id();
		}

		// Get canvas content using utility method.
		$canvas_content = CanvasUtils::get_canvas_content( $canvas_id, $post_id );

		if ( ! $canvas_content ) {
			// No content found, render empty portal.
			return Module::render(
				self::_build_render_args( $block, $attrs, $default_printed_style_attrs, $elements, $parent )
			);
		}

		// Skip expensive rendering when in admin/builder context.
		// The builder will handle rendering on the client side, so we don't need
		// to fully render the canvas content on the server when loading the builder.
		$should_skip_rendering = Conditions::is_admin_request()
			|| Conditions::is_vb_enabled()
			|| Conditions::is_rest_api_request();

		if ( $should_skip_rendering ) {
			// In builder/admin context, render empty portal placeholder.
			// The builder will handle rendering the canvas content on the client side.
			return Module::render(
				self::_build_render_args( $block, $attrs, $default_printed_style_attrs, $elements, $parent )
			);
		}

		// Unwrap placeholder block if needed.
		$unwrapped_content = ModuleUtils::maybe_unwrap_placeholder_block( $canvas_content );

		if ( ! $unwrapped_content ) {
			// No valid content, render empty portal.
			return Module::render(
				self::_build_render_args( $block, $attrs, $default_printed_style_attrs, $elements, $parent )
			);
		}

		// Render canvas content.
		// Use et_core_intentionally_unescaped because render_inner_content returns
		// already-sanitized HTML from WordPress block rendering pipeline.
		$rendered_content = et_core_intentionally_unescaped(
			BlockParserStore::render_inner_content( $unwrapped_content ),
			'html'
		);

		// Extract child modules IDs from rendered content.
		$children_ids = ChildrenUtils::extract_children_ids( $block );

		return Module::render(
			self::_build_render_args( $block, $attrs, $default_printed_style_attrs, $elements, $parent, $children_ids, $rendered_content )
		);
	}

	/**
	 * Loads `CanvasPortalModule` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @return void
	 */
	public function load() {
		// phpcs:ignore PHPCompatibility.FunctionUse.NewFunctionParameters.dirname_levelsFound -- We have PHP 7 support now, This can be deleted once PHPCS config is updated.
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/canvas-portal/';

		add_filter( 'divi_conversion_presets_attrs_map', [ CanvasPortalPresetAttrsMap::class, 'get_map' ], 10, 2 );

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
