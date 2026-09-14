<?php
/**
 * Module Library: Post Filter Item Module.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostFilterItem;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements;
use ET\Builder\Packages\Module\Layout\Components\MultiView\MultiViewScriptData;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Css\CssStyle;
use ET\Builder\Packages\Module\Options\Loop\LoopUtils;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\Module\Options\Element\ElementStyle;
use ET\Builder\Packages\Module\Options\FormField\FormFieldStyle;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use ET\Builder\Packages\ModuleLibrary\PostFilter\PostFilterFieldFormFieldStyle;
use ET\Builder\Packages\ModuleUtils\ChildrenUtils;
use WP_Block;

class PostFilterItemModule implements DependencyInterface {
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
		$id               = $args['id'] ?? '';
		$name             = $args['name'] ?? '';
		$selector         = $args['selector'] ?? '';
		$attrs            = $args['attrs'] ?? [];
		$elements         = $args['elements'];
		$store_instance   = $args['storeInstance'] ?? null;
		$parent           = BlockParserStore::get_parent( $id, $store_instance );
		$parent_attrs     = null !== $parent ? ( $parent->attrs ?? [] ) : [];
		$target_loop      = $parent_attrs['module']['advanced']['targetLoop']['desktop']['value'] ?? '';
		$post_type        = self::_resolve_post_type_for_target_loop( $target_loop, $store_instance );
		$normalized_loop  = is_string( $target_loop ) ? trim( $target_loop ) : '';
		$order_by_context = LoopUtils::resolve_loop_order_by_context_from_loop_id( $normalized_loop, $store_instance );

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
		$elements->script_data(
			[
				'attrName' => 'label',
			]
		);

		MultiViewScriptData::set(
			[
				'id'            => $id,
				'name'          => $name,
				'storeInstance' => $store_instance,
				'selector'      => $selector,
				'setContent'    => [
					[
						'selector' => $selector . ' .et_pb_post_filter__item-label',
						'data'     => $attrs['label']['innerContent'] ?? [],
					],
					[
						'selector'      => $selector . ' .et_pb_post_filter__item-control-surface',
						'data'          => $attrs['field']['innerContent'] ?? [],
						'valueResolver' => function ( $value ) use ( $post_type, $attrs, $order_by_context, $target_loop, $store_instance ) {
							return PostFilterItemControlSurface::render_from_value(
								$value,
								$post_type,
								$attrs,
								$order_by_context,
								is_string( $target_loop ) ? trim( $target_loop ) : '',
								$store_instance
							);
						},
					],
				],
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
								'scope'                  => 'child',
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
								'scope'                    => 'child',
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
								'scope'                  => 'child',
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
								'scope'                    => 'child',
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
								'scope'                  => 'child',
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
									'scope'      => 'child',
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
	 * Render callback for Post Filter Item.
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
		$children_ids      = ChildrenUtils::extract_children_ids( $block );
		$parent            = BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );
		$parent_attrs      = null !== $parent ? ( $parent->attrs ?? [] ) : [];
		$parent_id         = null !== $parent ? ( $parent->id ?? '' ) : '';
		$parent_name       = null !== $parent ? ( $parent->blockName ?? '' ) : '';
		$field_config      = $attrs['field']['innerContent']['desktop']['value'] ?? [];
		$field_type        = PostFilterItemControlSurface::resolve_field_type( $field_config['type'] ?? null );
		$query_field_type  = PostFilterCustomFieldUtils::resolve_query_param_field_type( is_array( $field_config ) ? $field_config : [] );
		$field_option_key  = is_string( $field_config['option'] ?? null ) ? $field_config['option'] : '';
		$custom_field_key  = is_string( $field_config['customFieldKey'] ?? null ) ? $field_config['customFieldKey'] : '';
		$field_comparison  = PostFilterCustomFieldUtils::sanitize_compare_operator(
			is_string( $field_config['comparison'] ?? null ) ? $field_config['comparison'] : ''
		) ?? '=';
		$field_placeholder = is_string( $field_config['placeholder'] ?? null ) ? $field_config['placeholder'] : '';
		$field_value_type  = PostFilterCustomFieldUtils::resolve_effective_field_value_type( is_array( $field_config ) ? $field_config : [] );

		$target_loop           = $parent_attrs['module']['advanced']['targetLoop']['desktop']['value'] ?? '';
		$post_type             = self::_resolve_post_type_for_target_loop( $target_loop, $block->parsed_block['storeInstance'] );
		$normalized_loop       = is_string( $target_loop ) ? trim( $target_loop ) : '';
		$order_by_context      = LoopUtils::resolve_loop_order_by_context_from_loop_id(
			$normalized_loop,
			$block->parsed_block['storeInstance']
		);
		$loop_query_type       = $order_by_context['query_type'] ?? 'post_types';
		$default_search_target = PostFilterCustomFieldUtils::resolve_default_search_target( $loop_query_type );

		if ( 'text' === $query_field_type ) {
			$search_target                  = '' !== $field_option_key ? $field_option_key : $default_search_target;
			$search_target_custom_field_key = $custom_field_key;
		} else {
			$search_target                  = $default_search_target;
			$search_target_custom_field_key = '';
		}

		$field_label         = is_string( $attrs['label']['innerContent']['desktop']['value'] ?? null )
			? $attrs['label']['innerContent']['desktop']['value']
			: '';
		$has_label           = '' !== $field_label && ! in_array( $field_type, [ 'submit', 'reset' ], true );
		$input_id            = "post-filter-item-{$field_type}-{$block->parsed_block['id']}";
		$range_label_id      = "post-filter-item-range-{$block->parsed_block['id']}-label";
		$label_uses_html_for = $has_label && ( PostFilterCustomFieldUtils::is_text_field_type( $field_type ) || in_array( $field_type, [ 'select', 'order', 'orderby' ], true ) );
		$field_label_markup  = $has_label ? HTMLUtility::render(
			[
				'tag'        => 'label',
				'attributes' => array_filter(
					[
						'class' => 'et_pb_post_filter__item-label',
						'for'   => $label_uses_html_for ? $input_id : null,
						'id'    => 'product-range' === $field_type ? $range_label_id : null,
					]
				),
				'children'   => esc_html( $field_label ),
			]
		) : '';

		if (
			PostFilterCustomFieldUtils::is_custom_field_option( $field_option_key )
			|| PostFilterCustomFieldUtils::is_post_date_field_value_option( $field_option_key )
		) {
			$list_items = PostFilterCustomFieldValueOptions::get_list_items( $field_type, $attrs, $post_type );
		} else {
			$loop_term_restrictions = LoopUtils::resolve_loop_term_restrictions_for_taxonomy_from_loop_id(
				$normalized_loop,
				$field_option_key,
				$block->parsed_block['storeInstance']
			);

			$list_items = PostFilterFieldListItems::get_list_items(
				$field_type,
				$field_option_key,
				$post_type,
				$attrs,
				$order_by_context['query_type'] ?? 'post_types',
				$loop_term_restrictions
			);
		}

		$render_field_type = PostFilterCustomFieldUtils::is_text_field_type( $field_type )
			? $query_field_type
			: $field_type;

		$control_surface = PostFilterItemControlSurface::render(
			$render_field_type,
			$block->parsed_block['id'],
			$field_label,
			$field_option_key,
			$has_label,
			$list_items,
			is_string( $target_loop ) ? trim( $target_loop ) : '',
			$custom_field_key,
			$field_comparison,
			$field_placeholder,
			$search_target,
			$search_target_custom_field_key,
			$order_by_context['query_type'] ?? 'post_types',
			$post_type,
			$attrs['button']['decoration']['button'] ?? [],
			$field_type,
			$field_value_type
		);

		$control_surface_markup = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => 'et_pb_post_filter__item-control-surface',
					'role'  => 'group',
				],
				'children'          => $control_surface,
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);

		return Module::render(
			[
				'orderIndex'               => $block->parsed_block['orderIndex'],
				'storeInstance'            => $block->parsed_block['storeInstance'],
				'attrs'                    => $attrs,
				'elements'                 => $elements,
				'defaultPrintedStyleAttrs' => $default_printed_style_attrs,
				'id'                       => $block->parsed_block['id'],
				'name'                     => $block->block_type->name,
				'classnamesFunction'       => [ self::class, 'module_classnames' ],
				'htmlAttrs'                => [
					'data-type' => $field_type,
				],
				'stylesComponent'          => [ self::class, 'module_styles' ],
				'scriptDataComponent'      => [ self::class, 'module_script_data' ],
				'parentAttrs'              => $parent_attrs,
				'parentId'                 => $parent_id,
				'parentName'               => $parent_name,
				'childrenIds'              => $children_ids,
				'children'                 => $elements->style_components(
					[
						'attrName' => 'module',
					]
				) . $field_label_markup . $control_surface_markup . $child_modules_content,
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
		LoopUtils::register_hooks();

		$module_json_folder_path = dirname( __DIR__, 4 ) . '/visual-builder/packages/module-library/src/components/post-filter-item/';

		ModuleRegistration::register_module(
			$module_json_folder_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}

	/**
	 * Resolve effective post type for list controls from a parent target loop id.
	 *
	 * Defaults to `post` only when no target loop is configured. When a target loop
	 * is set but post type cannot be resolved yet, returns an empty string so taxonomy
	 * terms can still load without an incorrect `post` fallback.
	 *
	 * @since ??
	 *
	 * @param mixed    $target_loop    Parent post-filter target loop id.
	 * @param int|null $store_instance Block parser store instance.
	 *
	 * @return string
	 */
	private static function _resolve_post_type_for_target_loop( $target_loop, $store_instance = null ): string {
		$normalized_target_loop = is_string( $target_loop ) ? trim( $target_loop ) : '';
		$post_type              = LoopUtils::resolve_target_post_type_from_loop_id( $normalized_target_loop, $store_instance );

		if ( '' === $post_type && '' === $normalized_target_loop ) {
			return 'post';
		}

		return $post_type;
	}
}
