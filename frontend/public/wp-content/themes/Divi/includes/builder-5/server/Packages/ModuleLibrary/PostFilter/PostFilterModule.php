<?php
/**
 * Module Library: Post Filter Module.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostFilter;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Element\ElementStyle;
use ET\Builder\Packages\Module\Options\FormField\FormFieldStyle;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use WP_Block;

class PostFilterModule implements DependencyInterface {
	/**
	 * Set script data of used module options.
	 *
	 * @since ??
	 *
	 * @param array $args Module script data args.
	 *
	 * @return void
	 */
	public static function module_script_data( array $args ): void {
		$id             = $args['id'] ?? '';
		$name           = $args['name'] ?? '';
		$selector       = $args['selector'] ?? '';
		$elements       = $args['elements'];
		$store_instance = $args['storeInstance'] ?? null;

		$elements->script_data(
			[
				'attrName' => 'module',
			]
		);
		$elements->script_data(
			[
				'attrName' => 'field',
			]
		);
		$elements->script_data(
			[
				'attrName' => 'checkbox',
			]
		);
		$elements->script_data(
			[
				'attrName' => 'radio',
			]
		);
		$elements->script_data(
			[
				'attrName' => 'multipleOrderButton',
			]
		);
		$elements->script_data(
			[
				'attrName' => 'button',
			]
		);
		$elements->script_data(
			[
				'attrName' => 'option',
			]
		);

		MultiViewScriptData::set(
			[
				'id'            => $id,
				'name'          => $name,
				'storeInstance' => $store_instance,
				'selector'      => $selector,
			]
		);
	}

	/**
	 * Set module classnames.
	 *
	 * @since ??
	 *
	 * @param array $args Module render args.
	 *
	 * @return void
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					'attrs' => $attrs['module']['decoration'] ?? [],
				]
			)
		);
	}

	/**
	 * Set module styles.
	 *
	 * @since ??
	 *
	 * @param array $args Module style args.
	 *
	 * @return void
	 */
	public static function module_styles( array $args ): void {
		$attrs     = $args['attrs'] ?? [];
		$elements  = $args['elements'];
		$settings  = $args['settings'] ?? [];
		$order_css = $args['orderClass'] ?? '';

		Style::add(
			[
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => [
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
					FormFieldStyle::style(
						PostFilterFieldFormFieldStyle::get_style_args(
							[
								'orderClass'             => $order_css,
								'scope'                  => 'parent',
								'attr'                   => $attrs['field'] ?? [],
								'isInsideStickyModule'   => $args['isInsideStickyModule'] ?? false,
								'stickyParentOrderClass' => $args['stickyParentOrderClass'] ?? null,
							]
						)
					),
					FormFieldStyle::style(
						PostFilterFieldFormFieldStyle::get_checkbox_style_args(
							[
								'orderClass'               => $order_css,
								'scope'                    => 'parent',
								'attr'                     => $attrs['checkbox'] ?? [],
								'isInsideStickyModule'     => $args['isInsideStickyModule'] ?? false,
								'stickyParentOrderClass'   => $args['stickyParentOrderClass'] ?? null,
								'defaultPrintedStyleAttrs' => $args['defaultPrintedStyleAttrs'] ?? [],
							]
						)
					),
					ElementStyle::style(
						PostFilterFieldFormFieldStyle::get_checkbox_icon_style_args(
							[
								'orderClass'             => $order_css,
								'scope'                  => 'parent',
								'attr'                   => $attrs['checkbox'] ?? [],
								'isInsideStickyModule'   => $args['isInsideStickyModule'] ?? false,
								'stickyParentOrderClass' => $args['stickyParentOrderClass'] ?? null,
							]
						)
					),
					FormFieldStyle::style(
						PostFilterFieldFormFieldStyle::get_radio_style_args(
							[
								'orderClass'               => $order_css,
								'scope'                    => 'parent',
								'attr'                     => $attrs['radio'] ?? [],
								'isInsideStickyModule'     => $args['isInsideStickyModule'] ?? false,
								'stickyParentOrderClass'   => $args['stickyParentOrderClass'] ?? null,
								'defaultPrintedStyleAttrs' => $args['defaultPrintedStyleAttrs'] ?? [],
							]
						)
					),
					ElementStyle::style(
						PostFilterFieldFormFieldStyle::get_radio_icon_style_args(
							[
								'orderClass'             => $order_css,
								'scope'                  => 'parent',
								'attr'                   => $attrs['radio'] ?? [],
								'isInsideStickyModule'   => $args['isInsideStickyModule'] ?? false,
								'stickyParentOrderClass' => $args['stickyParentOrderClass'] ?? null,
							]
						)
					),
					$elements->style(
						[
							'attrName' => 'multipleOrderButton',
						]
					),
					$elements->style(
						[
							'attrName' => 'button',
						]
					),
					$elements->style(
						[
							'attrName'   => 'option',
							'styleProps' => PostFilterFieldFormFieldStyle::get_option_style_args(
								[
									'orderClass' => $order_css,
									'scope'      => 'parent',
								]
							),
						]
					),
					CssStyle::style(
						[
							'selector' => $order_css,
							'attr'     => $attrs['css'] ?? [],
						]
					),
				],
			]
		);
	}

	/**
	 * Render callback for Post Filter.
	 *
	 * @since ??
	 *
	 * @param array          $attrs                 Saved block attributes.
	 * @param string         $child_modules_content Rendered child modules content.
	 * @param WP_Block       $block                 Parsed block.
	 * @param ModuleElements $elements              Module elements helper.
	 * @param array          $default_printed_style_attrs Default printed style attributes.
	 *
	 * @return string
	 */
	public static function render_callback( array $attrs, string $child_modules_content, WP_Block $block, ModuleElements $elements, array $default_printed_style_attrs ): string {
		$children_ids    = ChildrenUtils::extract_children_ids( $block );
		$target_loop     = $attrs['module']['advanced']['targetLoop']['desktop']['value'] ?? '';
		$filters_config  = $attrs['module']['advanced']['filters']['desktop']['value'] ?? [];
		$apply_mode      = is_array( $filters_config ) ? ( $filters_config['applyMode'] ?? 'submit' ) : 'submit';
		$relation        = is_array( $filters_config ) ? ( $filters_config['relation'] ?? 'and' ) : 'and';
		$relation        = 'or' === $relation ? 'or' : 'and';
		$normalized_loop = is_string( $target_loop ) ? trim( $target_loop ) : '';

		$has_target_loop = '' !== $normalized_loop && 'main_query' !== $normalized_loop;

		$controls_content = $has_target_loop
			? $child_modules_content
			: HTMLUtility::render(
				[
					'tag'        => 'span',
					'attributes' => [
						'class' => 'et_pb_post_filter__guidance',
					],
					'children'   => esc_html__( 'Select a Loop', 'et_builder_5' ),
				]
			);

		$controls = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'aria-label' => esc_attr__( 'Post filter controls', 'et_builder_5' ),
					'class'      => 'et_pb_post_filter__controls',
					'role'       => 'group',
				],
				'children'          => $controls_content,
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);

		$relation_param_name = PostFilterUtils::get_filter_relation_query_param_name( $normalized_loop );

		$relation_hidden_input = '' !== $relation_param_name
			? HTMLUtility::render(
				[
					'tag'        => 'input',
					'attributes' => [
						'name'  => esc_attr( $relation_param_name ),
						'type'  => 'hidden',
						'value' => esc_attr( $relation ),
					],
				]
			)
			: '';

		$form_action_url = PostFilterUtils::get_form_action_url( $normalized_loop );

		$filter_markup = $has_target_loop
			? HTMLUtility::render(
				[
					'tag'               => 'form',
					'attributes'        => [
						'action'           => esc_url( $form_action_url ),
						'class'            => 'et_pb_post_filter__form',
						'data-apply-mode'  => esc_attr( $apply_mode ),
						'data-target-loop' => esc_attr( $normalized_loop ),
						'method'           => 'get',
					],
					'children'          => $relation_hidden_input . $controls,
					'childrenSanitizer' => 'et_core_esc_previously',
				]
			)
			: $controls;

		return Module::render(
			[
				'orderIndex'    => $block->parsed_block['orderIndex'],
				'storeInstance' => $block->parsed_block['storeInstance'],
				'attrs'         => $attrs,
				'elements'      => $elements,
				'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
				'id'            => $block->parsed_block['id'],
				'name'          => $block->block_type->name,
				'classnamesFunction'  => [ self::class, 'module_classnames' ],
				'stylesComponent'     => [ self::class, 'module_styles' ],
				'scriptDataComponent' => [ self::class, 'module_script_data' ],
				'childrenIds'         => $children_ids,
				'children'            => $elements->style_components(
					[
						'attrName' => 'module',
					]
				) . $filter_markup,
			]
		);
	}

	/**
	 * Load module registration.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/post-filter/';

		PostFilterHooks::register();

		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}
}
