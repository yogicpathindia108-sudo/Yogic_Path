<?php
/**
 * Module Library: Icon List Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\IconList;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WordPress uses snakeCase in \WP_Block_Parser_Block

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\SanitizerUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\IconList\IconListPresetAttrsMap;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\StyleLibrary\Utils\StyleDeclarations;
use ET\Builder\Packages\GlobalData\GlobalData;
use WP_Block_Type_Registry;
use WP_Block;
use ET\Builder\Packages\GlobalData\GlobalPresetItemGroup;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Packages\ModuleLibrary\IconList\Styles\FontStyle;
use ET\Builder\Packages\ModuleLibrary\IconList\Styles\IconFontSizeStyle;
use ET\Builder\Packages\ModuleLibrary\IconList\Styles\TextStyle;

/**
 * IconListModule class.
 *
 * This class implements the functionality of an icon list component in a
 * frontend application. It provides functions for rendering the icon list,
 * managing REST API endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class IconListModule implements DependencyInterface {

	/**
	 * Render callback for the Icon List module.
	 *
	 * This function is responsible for the module's server-side HTML rendering on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ IconListEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $content                     The block's content.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the Icon List module.
	 */
	public static function render_callback( array $attrs, $content, WP_Block $block, ModuleElements $elements ) {
		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		// Create the <ul> content first.
		$ul_content = $elements->style_components(
			[
				'attrName' => 'module',
			]
		) . $content;

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
				'parentAttrs'         => isset( $parent->attrs ) ? $parent->attrs : [],
				'parentId'            => isset( $parent->id ) ? $parent->id : '',
				'parentName'          => isset( $parent->blockName ) ? $parent->blockName : '',
				// Generate consistent styles from attribute values when missing.
				'tag'                 => 'ul', // Render as <ul> instead of <div>.
				'children'            => $ul_content,
			]
		);
	}

	/**
	 * Generate classnames for the module.
	 *
	 * This function generates classnames for the module based on the provided
	 * arguments. It is used in the `render_callback` function of the Icon List
	 * module.
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
	public static function module_classnames( array $args ) {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		// Add default Divi module classes.
		$classnames_instance->add( 'et_pb_module' );
		$classnames_instance->add( 'et_pb_icon_list' );

		// Module.
		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					'attrs' => array_merge(
						isset( $attrs['module']['decoration'] ) ? $attrs['module']['decoration'] : [],
						[
							'link' => isset( $attrs['module']['advanced']['link'] ) ? $attrs['module']['advanced']['link'] : [],
						]
					),
				]
			)
		);
	}

	/**
	 * Icon List module script data.
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
	 *     @type array $attrs Module attributes.
	 * }
	 *
	 * @return void
	 */
	public static function module_script_data( array $args ) {
		// Assign variables.
		$elements = $args['elements'];

		// Element Script Data Options.
		$elements->script_data(
			[
				'attrName' => 'module',
			]
		);
	}

	/**
	 * Icon List module styles.
	 *
	 * This function generates the styles for the Icon List module.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /api/js/divi-module-library/functions/ModuleStyles ModuleStyles}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array $args {
	 *     An array of arguments for generating the module styles.
	 *
	 *     @type array          $attrs                       Module attributes.
	 *     @type array          $defaultPrintedStyleAttrs    Default printed style attributes.
	 *     @type ModuleElements $elements                    Module elements instance.
	 *     @type string         $mode                        Rendering mode.
	 *     @type string         $state                       Module state.
	 *     @type string         $orderClass                  Order class.
	 *     @type bool           $noStyleTag                  Whether to exclude style tag.
	 * }
	 *
	 * @return void
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
					// Module.
					$elements->style(
						[
							'attrName'   => 'module',
							'styleProps' => [
								'disabledOn'     => [
									'disabledModuleVisibility' => isset( $settings['disabledModuleVisibility'] ) ? $settings['disabledModuleVisibility'] : null,
								],
								'advancedStyles' => [
									[
										'componentName' => 'divi/text',
										'props'         => [
											'selector' => $args['orderClass'],
											'attr'     => isset( $attrs['module']['advanced']['text'] ) ? $attrs['module']['advanced']['text'] : null,
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $args['orderClass'] . ' .et_pb_icon_list_text',
											'attr'     => isset( $attrs['module']['advanced']['text']['text'] ) ? $attrs['module']['advanced']['text']['text'] : null,
											'declarationFunction' => [ TextStyle::class, 'text_orientation_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $args['orderClass'] . ' .et-pb-icon',
											'attr'     => isset( $attrs['icon']['advanced']['color'] ) ? $attrs['icon']['advanced']['color'] : null,
											'property' => 'color',
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $args['orderClass'] . ' .et-pb-icon',
											'attr'     => isset( $attrs['icon']['advanced']['size'] ) ? $attrs['icon']['advanced']['size'] : null,
											'declarationFunction' => [ IconFontSizeStyle::class, 'icon_font_size_declaration' ],
										],
									],
								],
							],
						]
					),
					// List Item styles that apply to all child list items.
					$elements->style(
						[
							'attrName'   => 'listItem',
							'styleProps' => [
								'advancedStyles' => [
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $args['orderClass'] . ' .et_pb_icon_list_text',
											'attr'     => isset( $attrs['listItem']['decoration']['font']['font'] ) ? $attrs['listItem']['decoration']['font']['font'] : null,
											'declarationFunction' => [ FontStyle::class, 'text_alignment_declaration' ],
										],
									],
									[
										'componentName' => 'divi/common',
										'props'         => [
											'selector' => $args['orderClass'] . ' .et_pb_icon_list_text *',
											'attr'     => isset( $attrs['module'] ) ? $attrs['module'] : null,
											'declarationFunction' => [ FontStyle::class, 'text_color_inherit_declaration' ],
										],
									],
								],
							],
						]
					),
					// Icon styles that apply to all child icons.
					$elements->style(
						[
							'attrName' => 'icon',
						]
					),
					// Module - Only for Custom CSS.
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
	 * Get the custom CSS fields for the Divi Icon List module.
	 *
	 * This function retrieves the custom CSS fields defined for the Divi icon list module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /api/js/divi-module-library/functions/generateDefaultAttrs cssFields} located in
	 * `@divi/module-library`. Note that this function does not have a `label` property on each
	 * array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi icon list module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = CustomCssTrait::custom_css();
	 * // Returns an array of custom CSS fields for the icon list module.
	 * ```
	 */
	public static function custom_css(): array {
		return \WP_Block_Type_Registry::get_instance()->get_registered( 'divi/icon-list' )->customCssFields;
	}

	/**
	 * Load the Icon List module.
	 *
	 * This function is responsible for loading the Icon List module by registering it.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load() {
		$module_json_folder_path = dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/visual-builder/packages/module-library/src/components/icon-list/';

		add_filter( 'divi_conversion_presets_attrs_map', [ IconListPresetAttrsMap::class, 'get_map' ], 10, 2 );

		// Register module directly like Accordion, Text, and Icon modules do.
		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}
}
