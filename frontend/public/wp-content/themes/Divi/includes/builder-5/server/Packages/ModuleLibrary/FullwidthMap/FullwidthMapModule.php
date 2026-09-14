<?php
/**
 * Module: Fullwidth Map class.
 *
 * @package ET\Builder\Packages\ModuleLibrary\FullwidthMap
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\FullwidthMap;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\StyleCommon\CommonStyle;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\ModuleLibrary\Map\MapUtils;
use ET\Builder\FrontEnd\Module\ScriptData;
use ET\Builder\FrontEnd\Assets\CriticalCSS;

/**
 * `Map` is consisted of functions used for Map such as Front-End rendering, REST API Endpoints etc.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 */
class FullwidthMapModule implements DependencyInterface {

	/**
	 * Module classnames function for Map module.
	 *
	 * This function is equivalent of JS function moduleClassnames located in
	 * visual-builder/packages/module-library/src/components/fullwidth-map/module-classnames.ts.
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

		// Module.
		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					// phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
					// TODO feat(D5, Module Attribute Refactor) Once link is merged as part of decoration property, remove this.
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
	 * Set script data of used module options.
	 *
	 * This function is equivalent of JS function ModuleScriptData located in
	 * visual-builder/packages/module-library/src/components/fullwidth-map/module-script-data.tsx.
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
		$elements = $args['elements'];
		$id       = $args['id'] ?? '';
		$selector = $args['selector'] ?? '';
		$attrs    = $args['attrs'] ?? [];

		// Element Script Data Options.
		$elements->script_data(
			[
				'attrName' => 'module',
			]
		);

		$map_lat_long_data = $attrs['map']['innerContent']['desktop']['value'] ?? [];

		if ( isset( $map_lat_long_data['lat'] ) && isset( $map_lat_long_data['lng'] ) ) {
			// Register script data for lazy loading. The `is_above_the_fold` flag
			// uses the lazy-load fold signal so maps below the fold are deferred.
			$is_below_the_fold = CriticalCSS::should_generate_critical_css() && CriticalCSS::is_current_module_below_fold_for_lazy_load();

			ScriptData::add_data_item(
				[
					'data_name'    => 'map_lazy_load',
					'data_item_id' => $id,
					'data_item'    => [
						'selector'          => $selector,
						'is_above_the_fold' => ! $is_below_the_fold,
					],
				]
			);
		}
	}

	/**
	 * Map Module's style components.
	 *
	 * This function is equivalent of JS function ModuleStyles located in
	 * visual-builder/packages/module-library/src/components/fullwidth-map/styles.tsx.
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
	public static function module_styles( array $args ): void {
		$attrs                     = $args['attrs'] ?? [];
		$elements                  = $args['elements'];
		$settings                  = $args['settings'] ?? [];
		$order_class               = $args['orderClass'] ?? '';
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

					// Map.
					$elements->style(
						[
							'attrName' => 'map',
						]
					),
					CommonStyle::style(
						[
							'selector'               => "{$args['orderClass']}.et_pb_module",
							'attr'                   => $attrs['module']['decoration']['sizing'] ?? [],
							'declarationFunction'    => [ self::class, 'alignment_style_declaration' ],
							'orderClass'             => $order_class,
							'isInsideStickyModule'   => $is_inside_sticky_module,
							'stickyParentOrderClass' => $sticky_parent_order_class,
						]
					),
					CssStyle::style(
						[
							'selector'   => $args['orderClass'],
							'attr'       => $attrs['css'] ?? [],
							'orderClass' => $order_class,
						]
					),
				],
			]
		);
	}

	/**
	 * Fullwidth Map render callback which outputs server side rendered HTML on the Front-End.
	 *
	 * This function is equivalent of JS function FullwidthMapEdit located in
	 * visual-builder/packages/module-library/src/components/fullwidth-map/edit.tsx.
	 *
	 * @param array          $attrs Block attributes that were saved by VB.
	 * @param string         $content                     Block content.
	 * @param WP_Block       $block                       Parsed block object that being rendered.
	 * @param ModuleElements $elements                    ModuleElements instance.
	 *
	 * @return string HTML rendered of Map module.
	 * @since ??
	 */
	public static function render_callback( $attrs, $content, $block, $elements ) {
		$children_ids = $block->parsed_block['innerBlocks'] ? array_map(
			function ( $inner_block ) {
				return $inner_block['id'];
			},
			$block->parsed_block['innerBlocks']
		) : [];

		$children = '';

		$coordinates = $attrs['map']['innerContent']['desktop']['value'] ?? [];
		$zoom        = $coordinates['zoom'] ?? '';
		$lat         = $coordinates['lat'] ?? '';
		$lng         = $coordinates['lng'] ?? '';

		$mouse_wheel     = $attrs['map']['advanced']['mouseWheel']['desktop']['value'] ?? '';
		$mobile_dragging = $attrs['map']['advanced']['mobileDragging']['desktop']['value'] ?? '';

		// Check if map should be deferred (below the fold).
		$should_defer_map = MapUtils::should_defer_map_loading();

		// Google Maps API Script Handling for GDPR Plugin Compatibility.
		// Always register Google Maps script so GDPR plugins can detect/replace it, matching Divi 4 behavior.
		// Ensures script handle exists even if enqueueing is blocked by GDPR controls.
		$is_maps_enqueue_managed_by_divi = et_pb_enqueue_google_maps_script();

		// Get Google Maps API URL for potential deferred loading.
		$google_api_key           = et_pb_get_google_api_key();
		$google_maps_api_url_args = [
			'v'         => 3,
			'key'       => $google_api_key,
			'libraries' => 'marker',
			'loading'   => 'async',
		];
		$google_maps_api_url      = add_query_arg( $google_maps_api_url_args, is_ssl() ? 'https://maps.googleapis.com/maps/api/js' : 'http://maps.googleapis.com/maps/api/js' );

		// Defer script loading if map is below the fold.
		if ( ! $should_defer_map ) {
			if ( $is_maps_enqueue_managed_by_divi ) {
				// Standard path: GDPR plugin allows maps or no GDPR plugin is active.
				wp_enqueue_script( 'google-maps-api' );
			} else {
				// GDPR blocked path: Register script directly so GDPR plugins can detect and replace it.
				// This maintains backward compatibility with Divi 4 GDPR plugins.
				// Register and enqueue script bypassing all filters.
				wp_register_script(
					'google-maps-api',
					esc_url_raw( $google_maps_api_url ),
					[],
					ET_BUILDER_VERSION,
					true
				);
				wp_enqueue_script( 'google-maps-api' );
			}
		} elseif ( ! $is_maps_enqueue_managed_by_divi ) {
			// Deferred path: register handle only so GDPR/consent plugins can detect/replace it.
			wp_register_script(
				'google-maps-api',
				esc_url_raw( $google_maps_api_url ),
				[],
				ET_BUILDER_VERSION,
				true
			);
		}

		$map_container = HTMLUtility::render(
			[
				'tag'        => 'div',
				'attributes' => [
					'class'                => 'et_pb_map',
					'data-center-lat'      => $lat,
					'data-center-lng'      => $lng,
					'data-zoom'            => $zoom,
					'data-mouse-wheel'     => $mouse_wheel,
					'data-mobile-dragging' => $mobile_dragging,
				],
			]
		);

		$children .= $map_container;
		$children .= $content;

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		// Build htmlAttrs for module wrapper container.
		$html_attrs = [];

		// Add deferred script URL to wrapper container if map is below the fold.
		if ( $should_defer_map ) {
			$html_attrs['data-deferred-maps-script'] = esc_url_raw( $google_maps_api_url );
		}

		return Module::render(
			[
				// FE only.
				'orderIndex'          => $block->parsed_block['orderIndex'],
				'storeInstance'       => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'id'                  => $block->parsed_block['id'],
				'name'                => $block->block_type->name,
				'htmlAttrs'           => $html_attrs,
				'moduleCategory'      => $block->block_type->category,
				'attrs'               => $attrs,
				'elements'            => $elements,
				'classnamesFunction'  => [ self::class, 'module_classnames' ],
				'scriptDataComponent' => [ self::class, 'module_script_data' ],
				'stylesComponent'     => [ self::class, 'module_styles' ],
				'parentId'            => $parent->id ?? '',
				'parentName'          => $parent->blockName ?? '', // phpcs:ignore -- No need to change the class property.
				'parentAttrs'         => $parent->attrs ?? [],
				'children'            => $elements->style_components(
					[
						'attrName' => 'module',
					]
				) . $children,
				'childrenIds'         => $children_ids,
			]
		);
	}

	/**
	 * Alignment Style Declaration.
	 *
	 * This function is used to declare the margin styles used to align a Fullwidth Map.
	 *
	 * This function is equivalent of JS function alignmentStyleDeclaration located in
	 * visual-builder/packages/module-library/src/components/fullwidth-map/style-declarations/alignment/index.ts
	 *
	 * @since ??
	 *
	 * @param array $params {
	 *     An array of arguments.
	 *
	 *     @type array $attrValue The value (breakpoint > state > value) of the module attribute.
	 * }
	 *
	 * @return string The style declarations as a string.
	 */
	public static function alignment_style_declaration( array $params ): string {
		$alignment = $params['attrValue']['alignment'] ?? '';

		$style_declarations = new StyleDeclarations(
			[
				'returnType' => 'string',
				'important'  => true,
			]
		);

		if ( ! empty( $alignment ) ) {
			switch ( $alignment ) {
				case 'left':
					$style_declarations->add( 'margin-left', '0' );
					$style_declarations->add( 'margin-right', 'auto' );
					break;
				case 'center':
					$style_declarations->add( 'margin-left', 'auto' );
					$style_declarations->add( 'margin-right', 'auto' );
					break;
				case 'right':
					$style_declarations->add( 'margin-left', 'auto' );
					$style_declarations->add( 'margin-right', '0' );
					break;
				default:
					break;
			}
		}

		return $style_declarations->value();
	}

	/**
	 * Loads `Map` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load() {
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/fullwidth-map/';

		add_filter( 'divi_conversion_presets_attrs_map', [ FullwidthMapPresetAttrsMap::class, 'get_map' ], 10, 2 );

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
