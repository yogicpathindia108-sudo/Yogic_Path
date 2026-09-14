<?php
/**
 * Module Library: Countdown Timer Module
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\CountdownTimer;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

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
use ET\Builder\Packages\Module\Options\Text\TextClassnames;
use ET\Builder\Packages\ModuleLibrary\CountdownTimer\CountdownTimerPresetAttrsMap;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use ET\Builder\Packages\StyleLibrary\Declarations\Background\Utils\BackgroundStyleUtils;
use ET\Builder\Packages\StyleLibrary\Declarations\Declarations;
use WP_Block_Type_Registry;
use WP_Block;
use ET\Builder\Packages\GlobalData\GlobalPresetItemGroup;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;

/**
 * CountdownTimerModule class.
 *
 * This class implements the functionality of a countdown timer component in a
 * frontend application. It provides functions for rendering the countdown timer,
 * managing REST API endpoints, and other related tasks.
 *
 * This is a dependency class and can be used as a dependency for `DependencyTree`.
 *
 * @since ??
 *
 * @see DependencyInterface
 */
class CountdownTimerModule implements DependencyInterface {

	/**
	 * Generate classnames for the module.
	 *
	 * This function generates classnames for the module based on the provided
	 * arguments. It is used in the `render_callback` function of the Countdown Timer module.
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
	 * CountdownTimerModule::module_classnames($args);
	 * ```
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		$background_value = $attrs['module']['decoration']['background']['desktop']['value'] ?? [];

		$text_options_classnames = TextClassnames::text_options_classnames(
			$attrs['module']['advanced']['text'] ?? [],
			[
				'orientation' => false,
			]
		);

		if ( $text_options_classnames ) {
			$classnames_instance->add( $text_options_classnames, true );
		}

		// Add et_pb_no_bg class when no background type is actively configured.
		$classnames_instance->add( 'et_pb_no_bg', ! BackgroundStyleUtils::has_active_background( $background_value ) );

		// Module.
		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					// phpcs:ignore ET.Comments.Todo.TodoFound -- Legacy TODO: May not be tracked in GitHub issues yet. Preserve for future tracking/removal.
					// TODO feat(D5, Module Attribute Refactor) Once link is merged as part of options property, remove this.
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
	 * Countdown Timer module script data.
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
	 * CountdownTimerModule::module_script_data( $args );
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
	 * Get the custom CSS fields for the Divi Countdown Timer module.
	 *
	 * This function retrieves the custom CSS fields defined for the Divi countdown timer module.
	 *
	 * This function is equivalent to the JavaScript constant
	 * {@link /api/js/divi-module-library/functions/generateDefaultAttrs cssFields}
	 * located in `@divi/module-library`. Note that this function does not have
	 * a `label` property on each array item, unlike the JS const cssFields.
	 *
	 * @since ??
	 *
	 * @return array An array of custom CSS fields for the Divi countdown timer module.
	 *
	 * @example
	 * ```php
	 * $customCssFields = CustomCssTrait::custom_css();
	 * // Returns an array of custom CSS fields for the countdown timer module.
	 * ```
	 */
	public static function custom_css(): array {
		return WP_Block_Type_Registry::get_instance()->get_registered( 'divi/countdown-timer' )->customCssFields;
	}


	/**
	 * CountdownTimer Module's style components.
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
	 *     @type string         $id                Module ID. In VB, the ID of module is UUIDV4. In FE, the ID is order index.
	 *     @type string         $name              Module name.
	 *     @type string         $attrs             Module attributes.
	 *     @type string         $parentAttrs       Parent attrs.
	 *     @type string         $orderClass        Selector class name.
	 *     @type string         $parentOrderClass  Parent selector class name.
	 *     @type string         $wrapperOrderClass Wrapper selector class name.
	 *     @type string         $settings          Custom settings.
	 *     @type string         $state             Attributes state.
	 *     @type string         $mode              Style mode.
	 *     @type ModuleElements $elements          ModuleElements instance.
	 * }
	 *
	 * @return void
	 */
	public static function module_styles( array $args ): void {
		$attrs    = $args['attrs'] ?? [];
		$elements = $args['elements'];
		$settings = $args['settings'] ?? [];

		$main_class = "{$args['orderClass']}.et_pb_countdown_timer";

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
										'componentName' => 'divi/text',
										'props'         => [
											'selector' => implode(
												', ',
												[
													"{$args['orderClass']} .et_pb_countdown_timer_container",
													"{$args['orderClass']} .title",
												]
											),
											'attr'     => $attrs['module']['advanced']['text'] ?? [],
										],
									],
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
					// Title.
					$elements->style(
						[
							'attrName' => 'title',
						]
					),
					// Number.
					$elements->style(
						[
							'attrName' => 'number',
						]
					),
					// Separator.
					$elements->style(
						[
							'attrName' => 'separator',
						]
					),
					// Label.
					$elements->style(
						[
							'attrName' => 'label',
						]
					),
					// Module - Only for Custom CSS.
					CssStyle::style(
						[
							'selector'  => $args['orderClass'] . '.et_pb_countdown_timer',
							'attr'      => $attrs['css'] ?? [],
							'cssFields' => self::custom_css(),
						]
					),
				],
			]
		);
	}

	/**
	 * Render callback for the Countdown Timer module.
	 *
	 * This function is responsible for rendering the server-side HTML of the module on the frontend.
	 *
	 * This function is equivalent to the JavaScript function
	 * {@link /docs/builder-api/js/module-library/ CountdownTimerEdit}
	 * located in `@divi/module-library`.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                       Block attributes that were saved by Divi Builder.
	 * @param string         $child_modules_content       The block's content from child modules.
	 * @param WP_Block       $block                       Parsed block object that is being rendered.
	 * @param ModuleElements $elements                    An instance of the ModuleElements class.
	 *
	 * @return string The HTML rendered output of the Countdown Timer module.
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
	 * CountdownTimerModule::render_callback( $attrs, $content, $block, $elements );
	 * ```
	 */
	public static function render_callback( array $attrs, string $child_modules_content, WP_Block $block, ModuleElements $elements ): string {
		// Title.
		$header = $elements->render(
			[
				'attrName' => 'title',
			]
		);

		$date_time         = $attrs['content']['advanced']['dateTime']['desktop']['value'] ?? '';
		$end_date          = gmdate( 'M d, Y H:i:s', strtotime( $date_time ) );
		$gmt_offset        = (string) get_option( 'gmt_offset' );
		$gmt_divider       = '-' === $gmt_offset[0] ? '-' : '+';
		$gmt_offset_hour   = str_pad( abs( (int) $gmt_offset ), 2, '0', STR_PAD_LEFT );
		$gmt_offset_minute = str_pad( ( ( abs( $gmt_offset ) * 100 ) % 100 ) * ( 60 / 100 ), 2, '0', STR_PAD_LEFT );
		$gmt               = "GMT{$gmt_divider}{$gmt_offset_hour}{$gmt_offset_minute}";

		$parent = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );

		$value_html = HTMLUtility::render(
			[
				'tag'        => 'p',
				'attributes' => [
					'class' => 'value',
				],
			]
		);

		$separator_html = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => 'sep section',
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => '<p>:</p>',
			]
		);

		$days_html = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class'      => 'days section values',
					'data-short' => esc_html__( 'D', 'et_builder_5' ),
					'data-full'  => esc_html__( 'Day(s)', 'et_builder_5' ),
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $value_html . sprintf( '<p class="label">%s</p>', esc_html__( 'Day(s)', 'et_builder_5' ) ),
			]
		);

		$hours_html = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class'      => 'hours section values',
					'data-short' => esc_html__( 'Hr', 'et_builder_5' ),
					'data-full'  => esc_html__( 'Hour(s)', 'et_builder_5' ),
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $value_html . sprintf( '<p class="label">%s</p>', esc_html__( 'Hour(s)', 'et_builder_5' ) ),
			]
		);

		$minutes_html = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class'      => 'minutes section values',
					'data-short' => esc_html__( 'Min', 'et_builder_5' ),
					'data-full'  => esc_html__( 'Minute(s)', 'et_builder_5' ),
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $value_html . sprintf( '<p class="label">%s</p>', esc_html__( 'Minute(s)', 'et_builder_5' ) ),
			]
		);

		$seconds_html = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class'      => 'seconds section values',
					'data-short' => esc_html__( 'Sec', 'et_builder_5' ),
					'data-full'  => esc_html__( 'Second(s)', 'et_builder_5' ),
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $value_html . sprintf( '<p class="label">%s</p>', esc_html__( 'Second(s)', 'et_builder_5' ) ),
			]
		);

		$countdown_container = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => 'et_pb_countdown_timer_container clearfix',
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $header . $days_html . $separator_html . $hours_html . $separator_html . $minutes_html . $separator_html . $seconds_html,
			]
		) . $child_modules_content;

		// Extract child modules IDs using helper utility.
		$children_ids = ChildrenUtils::extract_children_ids( $block );

		return Module::render(
			[
				// FE only.
				'orderIndex'          => $block->parsed_block['orderIndex'],
				'storeInstance'       => $block->parsed_block['storeInstance'],

				// VB equivalent.
				'attrs'               => $attrs,
				'elements'            => $elements,
				'id'                  => $block->parsed_block['id'],
				'htmlAttrs'           => [ 'data-end-timestamp' => esc_attr( strtotime( "{$end_date} {$gmt}" ) ) ],
				'name'                => $block->block_type->name,
				'classnamesFunction'  => [ self::class, 'module_classnames' ],
				'moduleCategory'      => $block->block_type->category,
				'stylesComponent'     => [ self::class, 'module_styles' ],
				'scriptDataComponent' => [ self::class, 'module_script_data' ],
				'parentAttrs'         => $parent->attrs ?? [],
				'parentId'            => $parent->id ?? '',
				'parentName'          => $parent->blockName ?? '', // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase,WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP use snakeCase in \WP_Block_Parser_Block
				'childrenIds'         => $children_ids,
				'children'            => $elements->style_components(
					[
						'attrName' => 'module',
					]
				) . $countdown_container,
			]
		);
	}

	/**
	 * Loads `CountdownTimerModule` and registers Front-End render callback and REST API Endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/countdown-timer/';

		add_filter( 'divi_conversion_presets_attrs_map', [ CountdownTimerPresetAttrsMap::class, 'get_map' ], 10, 2 );

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
