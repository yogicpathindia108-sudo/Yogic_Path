<?php
/**
 * Module: Map class.
 *
 * @package ET\Builder\Packages\ModuleLibrary\Map
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Map;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\GlobalData\GlobalPresetItemGroup;
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
class MapModule implements DependencyInterface {

	/**
	 * Module classnames function for Map module.
	 *
	 * This function is equivalent of JS function moduleClassnames located in
	 * visual-builder/packages/module-library/src/components/map/module-classnames.ts.
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
	 * visual-builder/packages/module-library/src/components/map/module-script-data.tsx.
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
			// uses the lazy-load fold signal (row-granular) so maps below the fold
			// inside a single tall section are detected and deferred correctly.
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
	 * Map render callback which outputs server side rendered HTML on the Front-End.
	 *
	 * This function is equivalent of JS function MapEdit located in
	 * visual-builder/packages/module-library/src/components/map/edit.tsx.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by VB.
	 * @param string         $content                     Block content.
	 * @param WP_Block       $block                       Parsed block object that being rendered.
	 * @param ModuleElements $elements                    ModuleElements instance.
	 * @param array          $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string HTML rendered of Map module.
	 */
	public static function render_callback( $attrs, $content, $block, $elements, $default_printed_style_attrs ) {
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

		$mouse_wheel             = $attrs['map']['advanced']['mouseWheel']['desktop']['value'] ?? '';
		$mobile_dragging         = $attrs['map']['advanced']['mobileDragging']['desktop']['value'] ?? '';
		$grayscale_filter        = $attrs['map']['advanced']['grayscaleFilter']['desktop']['value'] ?? '';
		$use_grayscale_filter    = isset( $grayscale_filter['enabled'] ) && 'on' === $grayscale_filter['enabled'];
		$grayscale_filter_amount = $grayscale_filter['amount'] ?? '';

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

		// Build map container attributes.
		$map_container_attributes = [
			'class'                => 'et_pb_map',
			'data-center-lat'      => $lat,
			'data-center-lng'      => $lng,
			'data-zoom'            => $zoom,
			'data-mouse-wheel'     => $mouse_wheel,
			'data-mobile-dragging' => $mobile_dragging,
		];

		$map_container = HTMLUtility::render(
			[
				'tag'        => 'div',
				'attributes' => $map_container_attributes,
			]
		);

		$children .= $map_container;
		$children .= $content;

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		// Build htmlAttrs for module wrapper container.
		$html_attrs = [
			'data-grayscale' => $use_grayscale_filter ? esc_attr( $grayscale_filter_amount ) : '',
		];

		// Add deferred script URL to wrapper container if map is below the fold.
		if ( $should_defer_map ) {
			$html_attrs['data-deferred-maps-script'] = esc_url_raw( $google_maps_api_url );
		}

		return Module::render(
			[
				// FE only.
				'orderIndex'               => $block->parsed_block['orderIndex'],
				'storeInstance'            => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'id'                       => $block->parsed_block['id'],
				'name'                     => $block->block_type->name,
				'htmlAttrs'                => $html_attrs,
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
				'children'                 => $elements->style_components(
					[
						'attrName' => 'module',
					]
				) . $children,
				'childrenIds'              => $children_ids,
			]
		);
	}


	/**
	 * Map Module's style components.
	 *
	 * This function is equivalent of JS function ModuleStyles located in
	 * visual-builder/packages/module-library/src/components/map/styles.tsx.
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

		// Manually construct default sizing attrs for both module and map elements.
		//
		// The $default_printed_style_attrs parameter doesn't include sizing defaults because
		// the system only tracks "printed style attributes" (position, filters, etc.) and
		// doesn't automatically include sizing as a printed style attribute.
		//
		// However, the Map module needs sizing defaults for proper height comparison:
		// - Default height is 440px (defined in module-default-render-attributes.json-source.ts)
		// - The Sizing::style_declaration() uses this default to implement D4's comparison logic
		// - When height equals default (440px), no inline CSS is printed
		// - This allows D4's dynamic assets CSS column-specific heights to apply correctly
		// - When height is user-customized, inline CSS is printed and overrides column-specific heights
		//
		// Without this default, D5 would print inline styles for default heights, causing
		// maps in narrow columns (1/2, 3/5, 3/8) to show 440px instead of 280px.
		//
		// See: Issue #44716 - D5 Map module size changes after migration from D4 to D5.
		$default_decoration_with_sizing           = $default_printed_style_attrs['module']['decoration'] ?? [];
		$default_decoration_with_sizing['sizing'] = [
			'desktop' => [
				'value' => [
					'height' => '440px',
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
							'attrName'              => 'module',
							'styleProps'            => [
								'defaultPrintedStyleAttrs' => $default_decoration_with_sizing,
								'disabledOn'               => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
								'sizing'                   => [
									// Enable D4-style default comparison to skip printing default height values.
									'skipDefaults' => true,
								],
								'advancedStyles'           => [
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
							'isMergeRecursiveProps' => true,
						]
					),

					// Map.
					$elements->style(
						[
							'attrName'              => 'map',
							'styleProps'            => [
								'defaultPrintedStyleAttrs' => $default_decoration_with_sizing,
								'sizing'                   => [
									// Enable D4-style default comparison to skip printing default height values.
									'skipDefaults' => true,
								],
							],
							'isMergeRecursiveProps' => true,
						]
					),

					// Module - Only for Custom CSS.
					CssStyle::style(
						[
							'selector' => $args['orderClass'],
							'attr'     => $attrs['css'] ?? [],
						]
					),
				],
			]
		);
	}

	/**
	 * Loads `Map` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load() {
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/map/';

		add_filter( 'divi_conversion_presets_attrs_map', [ MapPresetAttrsMap::class, 'get_map' ], 10, 2 );

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
