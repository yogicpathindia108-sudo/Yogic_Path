<?php
/**
 * Lottie Module
 *
 * @package ET\Builder\Packages\ModuleLibrary\Lottie
 */

namespace ET\Builder\Packages\ModuleLibrary\Lottie;

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\FrontEnd\Module\ScriptData;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\GlobalData\GlobalPreset;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\Lottie\LottiePresetAttrsMap;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use WP_Block;

/**
 * Lottie Module class.
 */
class LottieModule implements DependencyInterface {

	/**
	 * Load the module.
	 *
	 * @since ??
	 */
	public function load() {
		$module_json_folder_path = dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/visual-builder/packages/module-library/src/components/lottie/';

		// Register preset attributes filter.
		add_filter( 'divi_conversion_presets_attrs_map', [ LottiePresetAttrsMap::class, 'get_map' ], 10, 2 );
		add_filter(
			'divi_module_options_interactions_processed_preset_interaction',
			[ self::class, 'filter_preset_interaction_data' ],
			10,
			3
		);

		// Register module.
		ModuleRegistration::register_module(
			$module_json_folder_path,
			[ 'render_callback' => [ self::class, 'render_callback' ] ]
		);
	}

	/**
	 * Render callback for the Lottie module.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                 Block attributes.
	 * @param string         $child_modules_content The rendered child modules content.
	 * @param WP_Block       $block                 Block instance.
	 * @param ModuleElements $elements              ModuleElements instance.
	 *
	 * @return string
	 */
	public static function render_callback( array $attrs, $child_modules_content, WP_Block $block, ModuleElements $elements ) {
		// Extract child modules IDs using helper utility.
		$children_ids = ChildrenUtils::extract_children_ids( $block );

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		// Generate the Lottie content first.
		$lottie_content = self::_render_lottie_content( $attrs, $elements );

		return Module::render(
			[
				// Frontend-only properties.
				'orderIndex'          => $block->parsed_block['orderIndex'],
				'storeInstance'       => $block->parsed_block['storeInstance'],

				// Visual Builder equivalent properties.
				'attrs'               => $attrs,
				'elements'            => $elements,
				'id'                  => $block->parsed_block['id'],
				'name'                => $block->block_type->name,
				'classnamesFunction'  => [ self::class, 'module_classnames' ],
				'moduleCategory'      => $block->block_type->category,
				'stylesComponent'     => [ self::class, 'module_styles' ],
				'scriptDataComponent' => [ self::class, 'module_script_data' ],
				'parentAttrs'         => isset( $parent->attrs ) ? $parent->attrs : [],
				'parentId'            => isset( $parent->id ) ? $parent->id : '',
				'parentName'          => isset( $parent->blockName ) ? $parent->blockName : '', // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block
				'childrenIds'         => $children_ids,
				'children'            => $elements->style_components(
					[
						'attrName' => 'module',
					]
				) . $lottie_content . $child_modules_content,
				'childrenSanitizer'   => 'et_core_esc_previously',
			]
		);
	}

	/**
	 * Generate CSS classnames for the module.
	 *
	 * @since ??
	 *
	 * @param array $args Arguments.
	 */
	public static function module_classnames( array $args ) {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		$classnames_instance->add( 'et_pb_module' );
		$classnames_instance->add( 'et_pb_lottie' );

		// Add conditional classes based on settings.
		// Removed conditional classes - all Lottie behavior is now handled through responsive script data.
		// This ensures proper responsive functionality across all breakpoints.

		// Add element classnames for standard options.
		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					'attrs' => array_merge(
						isset( $attrs['module']['decoration'] ) ? $attrs['module']['decoration'] : [],
						[ 'link' => isset( $attrs['module']['advanced']['link'] ) ? $attrs['module']['advanced']['link'] : [] ]
					),
				]
			)
		);
	}

	/**
	 * Handle script data for frontend JavaScript.
	 *
	 * @since ??
	 *
	 * @param array $args Arguments.
	 */
	public static function module_script_data( array $args ) {
		$elements       = $args['elements'];
		$selector       = isset( $args['selector'] ) ? $args['selector'] : '';
		$id             = isset( $args['id'] ) ? $args['id'] : '';
		$attrs          = isset( $args['attrs'] ) ? $args['attrs'] : [];
		$store_instance = isset( $args['storeInstance'] ) ? $args['storeInstance'] : null;

		$elements->script_data( [ 'attrName' => 'module' ] );

		// Set module specific front-end data.
		self::set_front_end_data(
			[
				'selector'      => $selector,
				'id'            => $id,
				'attrs'         => $attrs,
				'storeInstance' => $store_instance,
			]
		);
	}

	/**
	 * Generate CSS styles for the module.
	 *
	 * @since ??
	 *
	 * @param array $args Arguments.
	 */
	public static function module_styles( array $args ) {
		$attrs    = isset( $args['attrs'] ) ? $args['attrs'] : [];
		$elements = $args['elements'];
		$settings = isset( $args['settings'] ) ? $args['settings'] : [];

		Style::add(
			[
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => [
					// Module base styles.
					$elements->style(
						[
							'attrName'   => 'module',
							'styleProps' => [
								'disabledOn' => [
									'disabledModuleVisibility' => isset( $settings['disabledModuleVisibility'] ) ? $settings['disabledModuleVisibility'] : null,
								],
							],
						]
					),

					// Lottie animation styles.
					$elements->style(
						[
							'attrName'   => 'lottie',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $args['orderClass'] . ' .et_pb_lottie_animation',
											'attr'     => isset( $attrs['lottie']['advanced']['width'] ) ? $attrs['lottie']['advanced']['width'] : [],
											'property' => 'width',
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $args['orderClass'] . ' .et_pb_lottie_animation',
											'attr'     => isset( $attrs['lottie']['advanced']['height'] ) ? $attrs['lottie']['advanced']['height'] : [],
											'property' => 'height',
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $args['orderClass'] . ' .et_pb_lottie_animation',
											'attr'     => isset( $attrs['lottie']['advanced']['maxWidth'] ) ? $attrs['lottie']['advanced']['maxWidth'] : [],
											'property' => 'max-width',
										],
									],
								],
							],
						]
					),

					// Custom CSS support.
					CssStyle::style(
						[
							'selector'  => $args['orderClass'],
							'attr'      => isset( $attrs['css'] ) ? $attrs['css'] : [],
							'cssFields' => self::custom_css(),
						]
					),
				],
			]
		);
	}

	/**
	 * Custom CSS fields
	 *
	 * This function is equivalent of JS customCssFields located in
	 * visual-builder/packages/module-library/src/components/lottie/module.json-source.ts.
	 *
	 * A minor difference with the JS customCssFields, this function did not have `label` property on each array item.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function custom_css() {
		return \WP_Block_Type_Registry::get_instance()->get_registered( 'divi/lottie' )->customCssFields;
	}

	/**
	 * Set the module specific front-end data.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments for setting the front-end script data.
	 *
	 *     @type string $selector The module selector.
	 *     @type string $id The module ID.
	 *     @type array  $attrs The module attributes.
	 *     @type int|null $storeInstance The store instance ID.
	 * }
	 * @return void
	 */
	public static function set_front_end_data( array $args ) {
		// Script data is not needed in VB.
		if ( Conditions::is_vb_enabled() ) {
			return;
		}

		$selector       = isset( $args['selector'] ) ? $args['selector'] : '';
		$id             = isset( $args['id'] ) ? $args['id'] : '';
		$attrs          = isset( $args['attrs'] ) ? $args['attrs'] : [];
		$store_instance = isset( $args['storeInstance'] ) ? $args['storeInstance'] : null;

		// Get Lottie animation attributes for all breakpoints.
		$lottie_attrs = isset( $attrs['lottie']['innerContent'] ) ? $attrs['lottie']['innerContent'] : [];

		if ( empty( $lottie_attrs ) ) {
			return;
		}

		// Prepare responsive animation data using MultiView system.
		self::_set_responsive_animation_data(
			[
				'id'            => $id,
				'selector'      => $selector,
				'attrs'         => $lottie_attrs,
				'storeInstance' => $store_instance,
			]
		);

		// Fallback: Register basic selector data for legacy compatibility.
		ScriptData::add_data_item(
			[
				'data_name'    => 'lottie',
				'data_item_id' => null,
				'data_item'    => [
					'selector' => $selector,
				],
			]
		);
	}

	/**
	 * Filter preset interaction script data for Lottie modules.
	 *
	 * @since ??
	 *
	 * @param array  $processed_interaction The processed interaction data.
	 * @param array  $interaction           The original interaction data.
	 * @param string $preset_id             The preset ID.
	 *
	 * @return array
	 */
	public static function filter_preset_interaction_data(
		array $processed_interaction,
		$interaction = [],
		$preset_id = ''
	): array {
		if ( ! is_string( $preset_id ) || '' === $preset_id ) {
			return $processed_interaction;
		}

		$lottie_runtime_attributes = self::_extract_preset_runtime_attributes( $preset_id );
		if ( ! empty( $lottie_runtime_attributes ) ) {
			$processed_interaction['lottieRuntimeAttributes'] = $lottie_runtime_attributes;
		}

		return $processed_interaction;
	}

	/**
	 * Extract runtime-relevant Lottie data attributes from preset data.
	 *
	 * @since ??
	 *
	 * @param string $preset_id The preset ID.
	 *
	 * @return array
	 */
	private static function _extract_preset_runtime_attributes( string $preset_id ): array {
		$preset_data = GlobalPreset::find_preset_data_by_id( $preset_id );

		if ( ! $preset_data || empty( $preset_data['renderAttrs'] ) ) {
			return [];
		}

		$module_name = $preset_data['moduleName'] ?? '';
		if ( 'divi/lottie' !== $module_name && 'lottie' !== $module_name ) {
			return [];
		}

		$render_attrs = $preset_data['renderAttrs'];
		$lottie_attrs = $render_attrs['lottie']['innerContent']['desktop']['value'] ?? [];

		$runtime_attributes = [];
		$attr_map           = [
			'trigger'   => 'data-lottie-trigger',
			'loop'      => 'data-lottie-loop',
			'speed'     => 'data-lottie-speed',
			'direction' => 'data-lottie-direction',
			'mode'      => 'data-lottie-mode',
		];

		foreach ( $attr_map as $setting => $data_attr_name ) {
			if ( ! array_key_exists( $setting, $lottie_attrs ) && 'speed' !== $setting ) {
				continue;
			}

			$value = array_key_exists( $setting, $lottie_attrs ) ? $lottie_attrs[ $setting ] : '1';

			if ( 'loop' === $setting ) {
				$runtime_attributes[ $data_attr_name ] = 'on' === $value ? 'true' : 'false';
				continue;
			}

			$runtime_attributes[ $data_attr_name ] = (string) $value;
		}

		return $runtime_attributes;
	}

	/**
	 * Set responsive animation data using MultiView system.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments for setting the responsive animation data.
	 *
	 *     @type string   $id The module ID.
	 *     @type string   $selector The module selector.
	 *     @type array    $attrs The lottie animation attributes for all breakpoints.
	 *     @type int|null $storeInstance The store instance ID.
	 * }
	 * @return void
	 */
	private static function _set_responsive_animation_data( array $args ) {
		$id             = isset( $args['id'] ) ? $args['id'] : '';
		$selector       = isset( $args['selector'] ) ? $args['selector'] : '';
		$attrs          = isset( $args['attrs'] ) ? $args['attrs'] : [];
		$store_instance = isset( $args['storeInstance'] ) ? $args['storeInstance'] : null;

		if ( empty( $attrs ) || empty( $selector ) || empty( $id ) ) {
			return;
		}

		// Prepare data for MultiView script data.
		$animation_data = [];

		// Process each animation setting that should be responsive.
		$responsive_settings = [ 'src', 'trigger', 'loop', 'speed', 'direction', 'mode' ];

		foreach ( $responsive_settings as $setting ) {
			$setting_data = [];

			// Extract values for each breakpoint and state.
			foreach ( [ 'desktop', 'tablet', 'phone' ] as $breakpoint ) {
				// Process each state: value, hover, sticky.
				foreach ( [ 'value', 'hover', 'sticky' ] as $state ) {
					if ( isset( $attrs[ $breakpoint ][ $state ][ $setting ] ) ) {
						$value = $attrs[ $breakpoint ][ $state ][ $setting ];

						// Convert all values to strings for DOM attributes.
						if ( 'loop' === $setting ) {
							$value = 'on' === $value ? 'true' : 'false';
						} else {
							// Ensure all other values are strings (speed, etc.).
							$value = (string) $value;
						}

						$setting_data[ $breakpoint ][ $state ] = $value;
					}
				}
			}

			// Only add to animation data if we have at least desktop data.
			if ( ! empty( $setting_data ) ) {
				$animation_data[ 'data-lottie-' . $setting ] = $setting_data;
			}
		}

		// Set MultiView attributes if we have responsive data.
		if ( ! empty( $animation_data ) ) {
			MultiViewScriptData::set_attrs(
				[
					'id'            => $id,
					'name'          => 'divi/lottie',
					'selector'      => $selector . ' .et_pb_lottie_animation',
					'hoverSelector' => $selector,
					'data'          => $animation_data,
					'storeInstance' => $store_instance,
				]
			);
		}
	}

	/**
	 * Render the Lottie animation content.
	 *
	 * @since ??
	 *
	 * @param array          $attrs    Block attributes.
	 * @param ModuleElements $elements ModuleElements instance.
	 *
	 * @return string
	 */
	private static function _render_lottie_content( array $attrs, ModuleElements $elements ) {
		$lottie_attrs  = isset( $attrs['lottie']['innerContent']['desktop']['value'] ) ? $attrs['lottie']['innerContent']['desktop']['value'] : [];
		$animation_src = isset( $lottie_attrs['src'] ) ? $lottie_attrs['src'] : '';

		if ( empty( $animation_src ) ) {
			return HTMLUtility::render(
				[
					'tag'               => 'div',
					'attributes'        => [ 'class' => 'et_pb_lottie_animation' ],
					'children'          => '',
					'childrenSanitizer' => 'et_core_esc_previously',
				]
			);
		}

		$trigger   = isset( $lottie_attrs['trigger'] ) ? $lottie_attrs['trigger'] : 'onLoad';
		$loop      = ( isset( $lottie_attrs['loop'] ) ? $lottie_attrs['loop'] : '' ) === 'on';
		$speed     = isset( $lottie_attrs['speed'] ) ? $lottie_attrs['speed'] : 1;
		$direction = isset( $lottie_attrs['direction'] ) ? $lottie_attrs['direction'] : 'forward';
		$mode      = isset( $lottie_attrs['mode'] ) ? $lottie_attrs['mode'] : 'normal';

		return HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class'                 => 'et_pb_lottie_animation',
					'data-lottie-src'       => SanitizerUtility::sanitize_data_url( $animation_src ),
					'data-lottie-trigger'   => esc_attr( $trigger ),
					'data-lottie-loop'      => $loop ? 'true' : 'false',
					'data-lottie-speed'     => esc_attr( $speed ),
					'data-lottie-direction' => esc_attr( $direction ),
					'data-lottie-mode'      => esc_attr( $mode ),
				],
				'children'          => '',
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);
	}
}
