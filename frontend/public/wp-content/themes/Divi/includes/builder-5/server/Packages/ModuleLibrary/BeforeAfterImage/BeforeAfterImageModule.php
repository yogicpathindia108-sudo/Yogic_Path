<?php
/**
 * Module Library: Before/After Image Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\BeforeAfterImage;

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
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Element\ElementStyle;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\VisualBuilder\Saving\SavingUtility;
use WP_Block;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;

/**
 * BeforeAfterImageModule class.
 *
 * This class implements the functionality of a before-after-image component in a frontend
 * application. It provides functions for rendering the before-after-image, managing REST API
 * endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class BeforeAfterImageModule implements DependencyInterface {

	/**
	 * Before/After Image module script data.
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

		// MultiView: Responsive content support for labels and visibility.
		MultiViewScriptData::set(
			[
				'id'            => $id,
				'name'          => $name,
				'storeInstance' => $store_instance,
				'selector'      => "{$selector} .et_pb_before_label",
				'setContent'    => [
					[
						'data' => $attrs['beforeLabel']['innerContent'] ?? [],
					],
				],
				'setVisibility' => [
					[
						'data'          => $attrs['beforeLabel']['innerContent'] ?? [],
						'valueResolver' => function ( $value ) {
							return empty( $value ) ? 'hidden' : 'visible';
						},
					],
				],
			]
		);

		MultiViewScriptData::set(
			[
				'id'            => $id,
				'name'          => $name,
				'storeInstance' => $store_instance,
				'selector'      => "{$selector} .et_pb_after_label",
				'setContent'    => [
					[
						'data' => $attrs['afterLabel']['innerContent'] ?? [],
					],
				],
				'setVisibility' => [
					[
						'data'          => $attrs['afterLabel']['innerContent'] ?? [],
						'valueResolver' => function ( $value ) {
							return empty( $value ) ? 'hidden' : 'visible';
						},
					],
				],
			]
		);
	}

	/**
	 * Generate classnames for the module.
	 *
	 * This function generates classnames for the module based on the provided
	 * arguments. It is used in the `render_callback` function of the Before/After Image module.
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
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		// Add base module class for SCSS scoping.
		$classnames_instance->add( 'et_pb_before_after_image', true );

		// Add orientation class for SCSS scoping.
		$orientation       = $attrs['slider']['advanced']['orientation']['desktop']['value'] ?? 'horizontal';
		$orientation_class = 'horizontal' === $orientation ? 'et_pb_before_after_image_horizontal' : 'et_pb_before_after_image_vertical';
		$classnames_instance->add( $orientation_class, true );

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
	 * Before/After Image Module's style components.
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
								'disabledOn' => [
									'disabledModuleVisibility' => $settings['disabledModuleVisibility'] ?? null,
								],
							],
						]
					),
					// Labels.
					$elements->style(
						[
							'attrName' => 'labels',
						]
					),
					// Before image.
					$elements->style(
						[
							'attrName' => 'beforeImage',
						]
					),
					// After image.
					$elements->style(
						[
							'attrName' => 'afterImage',
						]
					),
					// Slider.
					$elements->style(
						[
							'attrName'   => 'slider',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$args['orderClass']} .et_pb_before_after_image_slider",
											'attr'     => $attrs['slider']['advanced']['color'] ?? [],
											'property' => 'background-color',
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$args['orderClass']} .et_pb_before_after_image_slider_line",
											'attr'     => $attrs['slider']['advanced']['color'] ?? [],
											'property' => 'background-color',
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$args['orderClass']} .et_pb_before_after_image_slider_handle",
											'attr'     => $attrs['slider']['advanced']['color'] ?? [],
											'property' => 'background-color',
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$args['orderClass']} .et_pb_before_after_image_slider_handle::before",
											'attr'     => $attrs['slider']['advanced']['arrowColor'] ?? [],
											'property' => 'border-color',
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$args['orderClass']} .et_pb_before_after_image_slider_handle::after",
											'attr'     => $attrs['slider']['advanced']['arrowColor'] ?? [],
											'property' => 'border-color',
										],
									],
									// Horizontal orientation: left position, top: 0.
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$args['orderClass']}[data-orientation=\"horizontal\"] .et_pb_before_after_image_slider",
											'attr'     => $attrs['slider']['advanced']['position'] ?? [],
											'property' => 'left',
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$args['orderClass']}[data-orientation=\"horizontal\"] .et_pb_before_after_image_slider",
											'attr'     => [
												'desktop' => [
													'value' => '0',
												],
											],
											'property' => 'top',
										],
									],
									// Vertical orientation: top position, left: 0.
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$args['orderClass']}[data-orientation=\"vertical\"] .et_pb_before_after_image_slider",
											'attr'     => $attrs['slider']['advanced']['position'] ?? [],
											'property' => 'top',
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => "{$args['orderClass']}[data-orientation=\"vertical\"] .et_pb_before_after_image_slider",
											'attr'     => [
												'desktop' => [
													'value' => '0',
												],
											],
											'property' => 'left',
										],
									],
								],
							],
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
	 * Render callback for the Before/After Image module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ BeforeAfterImageEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                Block attributes that were saved by Divi Builder.
	 * @param string         $child_modules_content The block's content (child modules content).
	 * @param WP_Block       $block                Parsed block object that is being rendered.
	 * @param ModuleElements $elements             An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the Before/After Image module.
	 */
	public static function render_callback( array $attrs, string $child_modules_content, WP_Block $block, ModuleElements $elements ): string {
		// Get slider settings.
		$orientation  = $attrs['slider']['advanced']['orientation']['desktop']['value'] ?? 'horizontal';
		$position_raw = $attrs['slider']['advanced']['position']['desktop']['value'] ?? '50%';

		// Extract numeric value from percentage string (e.g., "50%" -> 50).
		$position = is_numeric( $position_raw )
			? (float) $position_raw
			: (float) str_replace( '%', '', $position_raw );

		// Ensure value is between 0 and 100.
		$position = max( 0, min( 100, $position ) );

		// Render before image.
		$before_image = $elements->render(
			[
				'attrName' => 'beforeImage',
			]
		);

		// Render after image.
		$after_image = $elements->render(
			[
				'attrName' => 'afterImage',
			]
		);

		// Render labels using elements->render() for proper responsive/dynamic content support.
		$before_label = $elements->render(
			[
				'attrName' => 'beforeLabel',
			]
		);

		$after_label = $elements->render(
			[
				'attrName' => 'afterLabel',
			]
		);

		// Build the container HTML.
		$orientation_class = 'horizontal' === $orientation ? 'et_pb_before_after_image_horizontal' : 'et_pb_before_after_image_vertical';

		$container_content = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class'            => "et_pb_before_after_image_container {$orientation_class}",
					'data-orientation' => $orientation,
					'data-position'    => (string) $position,
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => [
					HTMLUtility::render(
						[
							'tag'               => 'div',
							'attributes'        => [
								'class' => 'et_pb_before_image',
							],
							'childrenSanitizer' => 'et_core_esc_previously',
							'children'          => $before_image,
						]
					),
					HTMLUtility::render(
						[
							'tag'               => 'div',
							'attributes'        => [
								'class' => 'et_pb_after_image',
							],
							'childrenSanitizer' => 'et_core_esc_previously',
							'children'          => $after_image,
						]
					),
					HTMLUtility::render(
						[
							'tag'               => 'div',
							'attributes'        => [
								'class' => 'et_pb_before_after_image_slider',
							],
							'childrenSanitizer' => 'et_core_esc_previously',
							'children'          => HTMLUtility::render(
								[
									'tag'        => 'div',
									'attributes' => [
										'class' => 'et_pb_before_after_image_slider_line',
									],
								]
							) . HTMLUtility::render(
								[
									'tag'        => 'div',
									'attributes' => [
										'class' => 'et_pb_before_after_image_slider_handle',
									],
								]
							),
						]
					),
					! empty( $before_label ) ? $before_label : '',
					! empty( $after_label ) ? $after_label : '',
				],
			]
		);

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
				) . $container_content . $child_modules_content,
			]
		);
	}

	/**
	 * Loads `BeforeAfterImageModule` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		// phpcs:ignore PHPCompatibility.FunctionUse.NewFunctionParameters.dirname_levelsFound -- We have PHP 7 support now, This can be deleted once PHPCS config is updated.
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/before-after-image/';

		// Preset attribute maps are auto-generated from module.json metadata.
		// Only register this filter if you need to customize the auto-generated map
		// (e.g., exclude attributes, modify preset values, or add D4 conversion mappings).
		// For brand new modules, you can remove this line entirely.

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
