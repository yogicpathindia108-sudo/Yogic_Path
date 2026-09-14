<?php
/**
 * Post Filter Item control surface renderer.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostFilterItem;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Packages\Module\Options\Loop\LoopUtils;
use ET\Builder\Packages\ModuleLibrary\PostFilter\PostFilterUtils;

/**
 * Builds the control-surface HTML for every post-filter-item field type.
 *
 * Delegated entry points:
 * - `render()` — called by the render callback with a pre-resolved field config.
 * - `render_from_value()` — called by the MultiView value resolver for script-data updates.
 *
 * Range and multiple-order controls are delegated further to their own classes
 * (`PostFilterItemRangeControl` and `PostFilterItemMultipleOrderControl`).
 *
 * @since ??
 */
class PostFilterItemControlSurface {
	/**
	 * Product review rating star color.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private const PRODUCT_REVIEW_STAR_COLOR = '#404040';

	/**
	 * Product review rating star size in pixels.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	private const PRODUCT_REVIEW_STAR_SIZE = 17;

	/**
	 * Product review rating star SVG viewBox.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private const PRODUCT_REVIEW_STAR_VIEW_BOX = '7 7.4 14 13.3';

	/**
	 * SVG path used for product-review rating stars.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private const PRODUCT_REVIEW_STAR_PATH = 'M20.4629 12.0664L16.6419 11.5134C16.4619 11.4874 16.3049 11.3744 16.2229 11.2114L14.5089 7.80741C14.3029 7.39841 13.7199 7.39741 13.5119 7.80541L11.7729 11.2134C11.6899 11.3754 11.5339 11.4874 11.3549 11.5134L7.53692 12.0654C7.07392 12.1324 6.89292 12.7044 7.23392 13.0254L9.98392 15.6214C10.1199 15.7504 10.1829 15.9394 10.1499 16.1254L9.49692 19.8424C9.41692 20.2964 9.88992 20.6474 10.3009 20.4374L14.0099 18.5474H14.0129L17.6979 20.4374C18.1079 20.6474 18.5819 20.2994 18.5049 19.8454L17.8709 16.1224C17.8399 15.9384 17.9019 15.7514 18.0369 15.6234L20.7679 13.0244C21.1069 12.7034 20.9249 12.1334 20.4629 12.0664Z';

	/**
	 * Resolve list items and render the control surface from a multiview value config.
	 *
	 * Used as the `valueResolver` callback inside `module_script_data()` so that
	 * MultiView can re-render the control when the field config changes in the builder.
	 *
	 * @since ??
	 *
	 * @param mixed  $value            Raw MultiView control config value.
	 * @param string $post_type        Effective post type for list item resolution.
	 * @param array  $attrs            Saved module attrs.
	 * @param array  $order_by_context Loop order-by context from the parent target loop.
	 * @param string $target_loop      Parent target loop id.
	 * @param int|null $store_instance Block parser store instance.
	 *
	 * @return string
	 */
	public static function render_from_value(
		$value,
		string $post_type = 'post',
		array $attrs = [],
		array $order_by_context = [],
		string $target_loop = '',
		$store_instance = null
	): string {
		$stored_field_type              = 'text';
		$render_field_type              = 'text';
		$field_value_type               = 'text';
		$field_option_key               = '';
		$custom_field_key               = '';
		$field_comparison               = '=';
		$field_placeholder              = '';
		$search_target                  = PostFilterCustomFieldUtils::resolve_default_search_target( $order_by_context['query_type'] ?? 'post_types' );
		$search_target_custom_field_key = '';

		if ( is_array( $value ) ) {
			$stored_field_type     = self::resolve_field_type( $value['type'] ?? null );
			$query_field_type      = PostFilterCustomFieldUtils::resolve_query_param_field_type( $value );
			$render_field_type     = PostFilterCustomFieldUtils::is_text_field_type( $stored_field_type )
				? $query_field_type
				: $stored_field_type;
			$field_value_type      = PostFilterCustomFieldUtils::resolve_effective_field_value_type( $value );
			$field_option_key      = self::_resolve_field_option_key( $value['option'] ?? null );
			$custom_field_key      = is_string( $value['customFieldKey'] ?? null ) ? $value['customFieldKey'] : '';
			$field_comparison      = PostFilterCustomFieldUtils::sanitize_compare_operator( is_string( $value['comparison'] ?? null ) ? $value['comparison'] : '' ) ?? '=';
			$field_placeholder     = is_string( $value['placeholder'] ?? null ) ? $value['placeholder'] : '';
			$loop_query_type       = $order_by_context['query_type'] ?? 'post_types';
			$default_search_target = PostFilterCustomFieldUtils::resolve_default_search_target( $loop_query_type );

			if ( PostFilterCustomFieldUtils::is_text_field_type( $stored_field_type ) ) {
				$search_target                  = '' !== $field_option_key ? $field_option_key : $default_search_target;
				$search_target_custom_field_key = $custom_field_key;
			} else {
				$search_target                  = $default_search_target;
				$search_target_custom_field_key = '';
			}
		}

		if (
			PostFilterCustomFieldUtils::is_custom_field_option( $field_option_key )
			|| PostFilterCustomFieldUtils::is_post_date_field_value_option( $field_option_key )
		) {
			$list_items = PostFilterCustomFieldValueOptions::get_list_items( $stored_field_type ?? $render_field_type, $attrs, $post_type );
		} else {
			$loop_term_restrictions = LoopUtils::resolve_loop_term_restrictions_for_taxonomy_from_loop_id(
				$target_loop,
				$field_option_key,
				$store_instance
			);

			$list_items = PostFilterFieldListItems::get_list_items(
				$stored_field_type ?? $render_field_type,
				$field_option_key,
				$post_type,
				$attrs,
				$order_by_context['query_type'] ?? 'post_types',
				$loop_term_restrictions
			);
		}

		$field_label = is_string( $attrs['label']['innerContent']['desktop']['value'] ?? null )
			? $attrs['label']['innerContent']['desktop']['value']
			: '';

		return self::render(
			$render_field_type,
			'script-data',
			$field_label,
			$field_option_key,
			false,
			$list_items,
			'',
			$custom_field_key,
			$field_comparison,
			$field_placeholder,
			$search_target,
			$search_target_custom_field_key,
			$order_by_context['query_type'] ?? 'post_types',
			$post_type,
			$attrs['button']['decoration']['button'] ?? [],
			$stored_field_type,
			$field_value_type
		);
	}

	/**
	 * Render the control surface for one field type.
	 *
	 * Dispatches to the appropriate control renderer based on `$field_type`.
	 * Range and multiple-order controls are further delegated to dedicated classes.
	 *
	 * @since ??
	 *
	 * @param string $field_type                                Field control type.
	 * @param string $module_id                                 Module id.
	 * @param string $field_label                               Optional field label value.
	 * @param string $field_option_key                          Optional field option key value.
	 * @param bool   $has_label                                 Whether a field label is rendered above the control.
	 * @param array  $list_items                                Selectable list items (`value`, `label` pairs).
	 * @param string $target_loop                               Parent target loop id for GET query param names.
	 * @param string $custom_field_key                          Manual meta key when manual custom field option is selected.
	 * @param string $field_comparison                          Comparison operator for meta queries.
	 * @param string $field_placeholder                         Placeholder text for text inputs.
	 * @param string $search_target                             Primary search target slug.
	 * @param string $search_target_custom_field_key            Meta key for custom field primary search target.
	 * @param string $target_loop_query_type                    Parent target loop query type.
	 * @param string $post_type                                 Effective post type slug.
	 * @param array  $button_decoration                         Button decoration attrs.
	 * @param string $stored_field_type                         Stored field type for DOM ids (defaults to query field type).
	 * @param string $field_value_type                          Selected Field Value Type.
	 *
	 * @return string
	 */
	public static function render(
		string $field_type,
		string $module_id,
		string $field_label = '',
		string $field_option_key = '',
		bool $has_label = false,
		array $list_items = [],
		string $target_loop = '',
		string $custom_field_key = '',
		string $field_comparison = '=',
		string $field_placeholder = '',
		string $search_target = '',
		string $search_target_custom_field_key = '',
		string $target_loop_query_type = 'post_types',
		string $post_type = 'post',
		array $button_decoration = [],
		string $stored_field_type = '',
		string $field_value_type = ''
	): string {
		$stored_type_for_param_names = '' !== $stored_field_type ? $stored_field_type : $field_type;
		$field_param_name            = PostFilterUtils::get_field_query_param_name( $target_loop, $stored_type_for_param_names, $field_option_key, '', false, $custom_field_key, $module_id, $field_value_type );
		$checkbox_param_name         = PostFilterUtils::get_field_query_param_name( $target_loop, $stored_type_for_param_names, $field_option_key, '', true, $custom_field_key, $module_id, $field_value_type );
		$query_value                 = PostFilterUtils::get_field_query_param_value( $target_loop, $stored_type_for_param_names, $field_option_key, '', $custom_field_key, $module_id, $field_value_type );
		$locked_archive_term_slug    = PostFilterUtils::resolve_locked_archive_term_slug_for_taxonomy_filter(
			$field_option_key,
			$target_loop_query_type
		);

		if ( in_array( $field_type, [ 'checkbox', 'radio', 'select' ], true ) && '' !== $locked_archive_term_slug ) {
			if ( 'checkbox' === $field_type ) {
				$query_value = PostFilterUtils::apply_locked_archive_term_to_query_value(
					$query_value,
					$locked_archive_term_slug,
					$field_type
				);
			} else {
				$list_items  = PostFilterUtils::filter_list_items_for_locked_archive_term(
					$list_items,
					$locked_archive_term_slug,
					$field_option_key
				);
				$query_value = $locked_archive_term_slug;
			}
		}

		$aria_label                  = '' !== $field_label ? $field_label : esc_html__( 'Filter field', 'et_builder_5' );
		$options_title               = '' !== $field_label ? $field_label : esc_html__( 'Options', 'et_builder_5' );
		$option_preview_label        = PostFilterCustomFieldUtils::resolve_field_option_preview_label(
			$field_option_key,
			$post_type
		);
		$input_id_field_type         = '' !== $stored_field_type ? $stored_field_type : $field_type;
		$input_id                    = "post-filter-item-{$input_id_field_type}-{$module_id}";
		$show_options_title          = ! $has_label && '' !== $field_label;

		if ( 'submit' === $field_type ) {
			return self::_render_submit( $field_label, $button_decoration );
		}

		if ( 'reset' === $field_type ) {
			return self::_render_reset( $field_label, $button_decoration );
		}

		if ( 'checkbox' === $field_type ) {
			return self::_render_checkbox(
				$list_items,
				$checkbox_param_name,
				$query_value,
				$input_id,
				$options_title,
				$show_options_title,
				$target_loop,
				$stored_type_for_param_names,
				$field_option_key,
				$custom_field_key,
				$module_id,
				$field_comparison,
				$field_value_type,
				$locked_archive_term_slug
			);
		}

		if ( 'radio' === $field_type || 'product-status' === $field_type || 'product-review' === $field_type ) {
			return self::_render_radio(
				$field_type,
				$list_items,
				$field_param_name,
				$module_id,
				$query_value,
				$input_id,
				$target_loop,
				$field_option_key,
				$custom_field_key,
				$field_comparison,
				$options_title,
				$show_options_title,
				$field_value_type,
				$locked_archive_term_slug
			);
		}

		if ( 'product-range' === $field_type ) {
			return PostFilterItemRangeControl::render( $module_id, $target_loop );
		}

		if ( 'number' === $field_type ) {
			return self::_render_number(
				$field_type,
				$field_option_key,
				$target_loop_query_type,
				$field_label,
				$field_placeholder,
				$aria_label,
				$input_id,
				$field_param_name,
				$query_value,
				$target_loop,
				$custom_field_key,
				$module_id,
				$field_comparison,
				$field_value_type
			);
		}

		if ( in_array( $field_type, [ 'date', 'date-time', 'time' ], true ) ) {
			return self::_render_date_picker(
				$field_type,
				$field_option_key,
				$field_label,
				$field_placeholder,
				$aria_label,
				$input_id,
				$field_param_name,
				$query_value,
				$target_loop,
				$custom_field_key,
				$module_id,
				$field_comparison,
				$field_value_type
			);
		}

		if ( 'multiple-order' === $field_type ) {
			return PostFilterItemMultipleOrderControl::render( $target_loop, $module_id, $list_items );
		}

		if ( 'order' === $field_type || 'orderby' === $field_type || 'select' === $field_type ) {
			return self::_render_select(
				$list_items,
				$option_preview_label,
				$aria_label,
				$input_id,
				$field_param_name,
				$query_value,
				$target_loop,
				$field_type,
				$field_option_key,
				$custom_field_key,
				$module_id,
				$field_comparison,
				$field_value_type,
				'select' === $field_type ? $locked_archive_term_slug : ''
			);
		}

		return self::_render_search(
			$field_label,
			$field_placeholder,
			$aria_label,
			$input_id,
			$field_param_name,
			$query_value,
			$target_loop,
			$field_type,
			$field_option_key,
			$custom_field_key,
			$module_id,
			$search_target,
			$search_target_custom_field_key,
			$target_loop_query_type
		);
	}

	/**
	 * Render a submit button control.
	 *
	 * @since ??
	 *
	 * @param string $field_label       Configured label value.
	 * @param array  $button_decoration Button decoration attrs.
	 *
	 * @return string
	 */
	private static function _render_submit( string $field_label, array $button_decoration = [] ): string {
		$submit_button_text = '' !== $field_label ? $field_label : esc_html__( 'Apply', 'et_builder_5' );
		$submit_aria_label  = '' !== $field_label ? $field_label : esc_attr__( 'Apply', 'et_builder_5' );

		return HTMLUtility::render(
			[
				'tag'        => 'button',
				'attributes' => array_merge(
					[
						'aria-label' => esc_attr( $submit_aria_label ),
						'class'      => 'et_pb_button et_pb_post_filter__item-control-button et_pb_post_filter__item-control-submit',
						'type'       => 'submit',
					],
					PostFilterItemButtonIconDataAttributes::get( $button_decoration )
				),
				'children'   => esc_html( $submit_button_text ),
			]
		);
	}

	/**
	 * Render a reset button control.
	 *
	 * @since ??
	 *
	 * @param string $field_label       Configured label value.
	 * @param array  $button_decoration Button decoration attrs.
	 *
	 * @return string
	 */
	private static function _render_reset( string $field_label, array $button_decoration = [] ): string {
		$reset_button_text = '' !== $field_label ? $field_label : esc_html__( 'Reset', 'et_builder_5' );
		$reset_aria_label  = '' !== $field_label ? $field_label : esc_attr__( 'Reset filters', 'et_builder_5' );

		return HTMLUtility::render(
			[
				'tag'        => 'button',
				'attributes' => array_merge(
					[
						'aria-label' => esc_attr( $reset_aria_label ),
						'class'      => 'et_pb_button et_pb_post_filter__item-control-button et_pb_post_filter__item-control-reset',
						'type'       => 'button',
					],
					PostFilterItemButtonIconDataAttributes::get( $button_decoration )
				),
				'children'   => esc_html( $reset_button_text ),
			]
		);
	}

	/**
	 * Render a checkbox list control.
	 *
	 * @since ??
	 *
	 * @param array  $list_items          Selectable list items.
	 * @param string $checkbox_param_name GET param name for checkbox inputs.
	 * @param mixed  $query_value         Current query value.
	 * @param string $input_id            Base input id.
	 * @param string $options_title       Label used as the options group title.
	 * @param bool   $show_options_title  Whether to render the options title element.
	 * @param string $target_loop         Parent target loop id.
	 * @param string $stored_field_type   Stored field type for param names.
	 * @param string $field_option_key    Child field option key.
	 * @param string $custom_field_key    Manual meta key when manual option is selected.
	 * @param string $module_id           Post-filter-item module id.
	 * @param string $field_comparison    Configured comparison operator.
	 * @param string $field_value_type    Selected Field Value Type.
	 * @param string $locked_archive_term_slug Locked archive term slug when applicable.
	 *
	 * @return string
	 */
	private static function _render_checkbox(
		array $list_items,
		string $checkbox_param_name,
		$query_value,
		string $input_id,
		string $options_title,
		bool $show_options_title,
		string $target_loop = '',
		string $stored_field_type = '',
		string $field_option_key = '',
		string $custom_field_key = '',
		string $module_id = '',
		string $field_comparison = '',
		string $field_value_type = '',
		string $locked_archive_term_slug = ''
	): string {
		$checkbox_options = [];

		if ( empty( $list_items ) ) {
			$checkbox_options[] = self::_render_options_empty_state();
		} else {
			foreach ( $list_items as $index => $list_item ) {
				$option_value      = $list_item['value'] ?? '';
				$option_label      = $list_item['label'] ?? $option_value;
				$option_id         = "{$input_id}-{$index}";
				$is_locked_option  = '' !== $locked_archive_term_slug && $locked_archive_term_slug === $option_value;
				$option_classnames = [ 'et_pb_post_filter__item-option', 'et_pb_post_filter__item-option--checkbox' ];

				if ( $is_locked_option ) {
					$option_classnames[] = 'et_pb_post_filter__item-option--locked';
				}

				$checkbox_attributes = [
					'aria-label' => esc_attr( $option_label ),
					'class'      => 'et_pb_post_filter__item-control et_pb_post_filter__item-control-checkbox',
					'id'         => $option_id,
					'name'       => '' !== $checkbox_param_name ? $checkbox_param_name : null,
					'type'       => 'checkbox',
					'value'      => esc_attr( $option_value ),
				];

				if ( PostFilterUtils::is_query_option_selected( $query_value, $option_value ) ) {
					$checkbox_attributes['checked'] = true;
				}

				if ( $is_locked_option ) {
					$checkbox_attributes['disabled'] = true;
				}

				$checkbox_input = HTMLUtility::render(
					[
						'tag'        => 'input',
						'attributes' => array_filter( $checkbox_attributes ),
					]
				);

				$checkbox_label = HTMLUtility::render(
					[
						'tag'               => 'label',
						'attributes'        => [
							'for' => $option_id,
						],
						'children'          => [
							HTMLUtility::render(
								[
									'tag' => 'i',
								]
							),
							esc_html( $option_label ),
						],
						'childrenSanitizer' => 'et_core_esc_previously',
					]
				);

				$checkbox_options[] = HTMLUtility::render(
					[
						'tag'               => 'span',
						'attributes'        => [
							'class' => HTMLUtility::classnames( $option_classnames ),
						],
						'children'          => [
							$checkbox_input,
							$checkbox_label,
						],
						'childrenSanitizer' => 'et_core_esc_previously',
					]
				);
			}
		}

		$options_title_markup = $show_options_title
			? HTMLUtility::render(
				[
					'tag'        => 'span',
					'attributes' => [
						'class' => 'et_pb_post_filter__item-options-title',
					],
					'children'   => esc_html( $options_title ),
				]
			)
			: '';

		$companion_inputs = PostFilterCustomFieldUtils::list_control_uses_companion_hidden_inputs( $stored_field_type, $field_value_type )
			? self::_render_companion_hidden_inputs(
				$target_loop,
				$stored_field_type,
				$field_option_key,
				$custom_field_key,
				$module_id,
				$field_comparison,
				$field_value_type
			)
			: '';

		$locked_archive_term_hidden_input = self::_render_locked_archive_term_hidden_input(
			$checkbox_param_name,
			$locked_archive_term_slug
		);

		return HTMLUtility::render(
			[
				'tag'               => 'div',
				'children'          => [
					$companion_inputs,
					$locked_archive_term_hidden_input,
					HTMLUtility::render(
						[
							'tag'        => 'input',
							'attributes' => [
								'class' => 'et_pb_post_filter__item-checkbox-handle',
								'type'  => 'hidden',
							],
						]
					),
					HTMLUtility::render(
						[
							'tag'               => 'span',
							'attributes'        => [
								'class' => 'et_pb_post_filter__item-options-wrapper et_pb_post_filter__item-options-wrapper--checkbox',
							],
							'children'          => [
								$options_title_markup,
								HTMLUtility::render(
									[
										'tag'               => 'span',
										'attributes'        => [
											'class' => 'et_pb_post_filter__item-options-list',
										],
										'children'          => $checkbox_options,
										'childrenSanitizer' => 'et_core_esc_previously',
									]
								),
							],
							'childrenSanitizer' => 'et_core_esc_previously',
						]
					),
				],
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);
	}

	/**
	 * Render a radio list control (covers radio, product-status, and product-review types).
	 *
	 * @since ??
	 *
	 * @param string $field_type        Field control type.
	 * @param array  $list_items        Selectable list items.
	 * @param string $field_param_name  GET param name.
	 * @param string $module_id         Module id.
	 * @param mixed  $query_value       Current query value.
	 * @param string $input_id          Base input id.
	 * @param string $target_loop       Parent target loop id.
	 * @param string $field_option_key  Field option key.
	 * @param string $custom_field_key  Manual meta key.
	 * @param string $field_comparison  Comparison operator.
	 * @param string $options_title     Label used as the options group title.
	 * @param bool   $show_options_title Whether to render the options title element.
	 * @param string $field_value_type  Selected Field Value Type.
	 * @param string $locked_archive_term_slug Locked archive term slug when applicable.
	 *
	 * @return string
	 */
	private static function _render_radio(
		string $field_type,
		array $list_items,
		string $field_param_name,
		string $module_id,
		$query_value,
		string $input_id,
		string $target_loop,
		string $field_option_key,
		string $custom_field_key,
		string $field_comparison,
		string $options_title,
		bool $show_options_title,
		string $field_value_type = '',
		string $locked_archive_term_slug = ''
	): string {
		$is_archive_locked_single_choice = 'radio' === $field_type && '' !== $locked_archive_term_slug;
		$radio_name                      = '' !== $field_param_name ? $field_param_name : "post-filter-item-radio-{$module_id}";
		$radio_options                   = [];

		if ( empty( $list_items ) ) {
			$radio_options[] = self::_render_options_empty_state();
		} else {
			foreach ( $list_items as $index => $list_item ) {
				$option_value      = $list_item['value'] ?? '';
				$option_label      = $list_item['label'] ?? $option_value;
				$option_id         = "{$input_id}-{$index}";
				$is_locked_option  = $is_archive_locked_single_choice || ( '' !== $locked_archive_term_slug && $locked_archive_term_slug === $option_value );
				$option_classnames = [ 'et_pb_post_filter__item-option', 'et_pb_post_filter__item-option--radio' ];

				if ( $is_locked_option ) {
					$option_classnames[] = 'et_pb_post_filter__item-option--locked';
				}

				$radio_attributes = [
					'aria-label' => esc_attr( $option_label ),
					'class'      => 'et_pb_post_filter__item-control et_pb_post_filter__item-control-radio',
					'id'         => $option_id,
					'name'       => $radio_name,
					'type'       => 'radio',
					'value'      => esc_attr( $option_value ),
				];

				if ( $is_archive_locked_single_choice || PostFilterUtils::is_query_option_selected( $query_value, $option_value ) ) {
					$radio_attributes['checked'] = true;
				}

				if ( $is_locked_option ) {
					$radio_attributes['disabled'] = true;
				}

				$radio_input = HTMLUtility::render(
					[
						'tag'        => 'input',
						'attributes' => $radio_attributes,
					]
				);

				$radio_label = self::_render_radio_option_label(
					$field_type,
					$option_value,
					$option_label,
					$option_id
				);

				$radio_options[] = HTMLUtility::render(
					[
						'tag'               => 'span',
						'attributes'        => [
							'class' => HTMLUtility::classnames( $option_classnames ),
						],
						'children'          => [
							$radio_input,
							$radio_label,
						],
						'childrenSanitizer' => 'et_core_esc_previously',
					]
				);
			}
		}

		$options_title_markup = $show_options_title
			? HTMLUtility::render(
				[
					'tag'        => 'span',
					'attributes' => [
						'class' => 'et_pb_post_filter__item-options-title',
					],
					'children'   => esc_html( $options_title ),
				]
			)
			: '';

		$companion_inputs = self::_render_companion_hidden_inputs(
			$target_loop,
			$field_type,
			$field_option_key,
			$custom_field_key,
			$module_id,
			$field_comparison,
			$field_value_type
		);

		$locked_archive_term_hidden_input = $is_archive_locked_single_choice
			? self::_render_locked_archive_term_hidden_input( $radio_name, $locked_archive_term_slug )
			: '';

		return HTMLUtility::render(
			[
				'tag'               => 'div',
				'children'          => [
					HTMLUtility::render(
						[
							'tag'        => 'input',
							'attributes' => [
								'class' => 'et_pb_post_filter__item-checkbox-handle',
								'type'  => 'hidden',
							],
						]
					),
					$companion_inputs,
					$locked_archive_term_hidden_input,
					HTMLUtility::render(
						[
							'tag'               => 'span',
							'attributes'        => [
								'class' => 'et_pb_post_filter__item-options-wrapper et_pb_post_filter__item-options-wrapper--radio',
							],
							'children'          => [
								$options_title_markup,
								HTMLUtility::render(
									[
										'tag'               => 'span',
										'attributes'        => [
											'class' => 'et_pb_post_filter__item-options-list',
										],
										'children'          => $radio_options,
										'childrenSanitizer' => 'et_core_esc_previously',
									]
								),
							],
							'childrenSanitizer' => 'et_core_esc_previously',
						]
					),
				],
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);
	}

	/**
	 * Render a number input control.
	 *
	 * @since ??
	 *
	 * @param string $field_type              Field control type.
	 * @param string $field_option_key        Field option key.
	 * @param string $target_loop_query_type  Parent target loop query type.
	 * @param string $field_label             Configured label value.
	 * @param string $field_placeholder       Configured placeholder text.
	 * @param string $aria_label              Accessible label.
	 * @param string $input_id               Input element id.
	 * @param string $field_param_name        GET param name.
	 * @param mixed  $query_value             Current query value.
	 * @param string $target_loop             Parent target loop id.
	 * @param string $custom_field_key        Manual meta key.
	 * @param string $module_id              Module id.
	 * @param string $field_comparison        Comparison operator.
	 * @param string $field_value_type        Selected Field Value Type.
	 *
	 * @return string
	 */
	private static function _render_number(
		string $field_type,
		string $field_option_key,
		string $target_loop_query_type,
		string $field_label,
		string $field_placeholder,
		string $aria_label,
		string $input_id,
		string $field_param_name,
		$query_value,
		string $target_loop,
		string $custom_field_key,
		string $module_id,
		string $field_comparison,
		string $field_value_type = ''
	): string {
		$number_field_option_key = PostFilterCustomFieldUtils::resolve_number_field_value_option_key(
			$field_option_key,
			$target_loop_query_type
		);
		$number_placeholder      = '' !== $field_placeholder
			? esc_attr( $field_placeholder )
			: ( '' !== $field_label ? esc_attr( $field_label ) : esc_attr__( '0', 'et_builder_5' ) );
		$number_attributes       = [
			'aria-label'      => esc_attr( $aria_label ),
			'class'           => 'et_pb_post_filter__item-control',
			'data-field_type' => 'number',
			'id'              => $input_id,
			'name'            => '' !== $field_param_name ? $field_param_name : null,
			'type'            => 'number',
			'inputmode'       => 'decimal',
			'placeholder'     => $number_placeholder,
		];

		if ( is_string( $query_value ) && '' !== $query_value ) {
			$number_attributes['value'] = esc_attr( $query_value );
		}

		$companion_inputs = self::_render_companion_hidden_inputs(
			$target_loop,
			$field_type,
			$number_field_option_key,
			$custom_field_key,
			$module_id,
			$field_comparison,
			$field_value_type
		);

		return HTMLUtility::render(
			[
				'tag'               => 'div',
				'children'          => [
					$companion_inputs,
					HTMLUtility::render(
						[
							'tag'        => 'input',
							'attributes' => array_filter(
								$number_attributes,
								static function ( $attribute_value ) {
									return null !== $attribute_value;
								}
							),
						]
					),
				],
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);
	}

	/**
	 * Render a native HTML date/time picker control.
	 *
	 * @since ??
	 *
	 * @param string $field_type        Field control type.
	 * @param string $field_option_key  Field option key.
	 * @param string $field_label       Configured label value.
	 * @param string $field_placeholder Configured placeholder text.
	 * @param string $aria_label        Accessible label.
	 * @param string $input_id          Input element id.
	 * @param string $field_param_name  GET param name.
	 * @param mixed  $query_value       Current query value.
	 * @param string $target_loop       Parent target loop id.
	 * @param string $custom_field_key  Manual meta key.
	 * @param string $module_id         Module id.
	 * @param string $field_comparison  Comparison operator.
	 * @param string $field_value_type  Selected Field Value Type.
	 *
	 * @return string
	 */
	private static function _render_date_picker(
		string $field_type,
		string $field_option_key,
		string $field_label,
		string $field_placeholder,
		string $aria_label,
		string $input_id,
		string $field_param_name,
		$query_value,
		string $target_loop,
		string $custom_field_key,
		string $module_id,
		string $field_comparison,
		string $field_value_type = ''
	): string {
		$input_type_map = [
			'date'      => 'date',
			'date-time' => 'datetime-local',
			'time'      => 'time',
		];
		$input_type     = $input_type_map[ $field_type ] ?? 'date';
		$placeholder    = '' !== $field_placeholder
			? esc_attr( $field_placeholder )
			: ( '' !== $field_label ? esc_attr( $field_label ) : esc_attr__( 'Select', 'et_builder_5' ) );
		$attributes     = [
			'aria-label'      => esc_attr( $aria_label ),
			'class'           => 'et_pb_post_filter__item-control',
			'data-field_type' => $field_type,
			'id'              => $input_id,
			'name'            => '' !== $field_param_name ? $field_param_name : null,
			'type'            => $input_type,
			'placeholder'     => $placeholder,
		];

		if ( is_string( $query_value ) && '' !== $query_value ) {
			$attributes['value'] = esc_attr( $query_value );
		}

		$companion_inputs = self::_render_companion_hidden_inputs(
			$target_loop,
			$field_type,
			$field_option_key,
			$custom_field_key,
			$module_id,
			$field_comparison,
			$field_value_type
		);

		return HTMLUtility::render(
			[
				'tag'               => 'div',
				'children'          => [
					$companion_inputs,
					HTMLUtility::render(
						[
							'tag'        => 'input',
							'attributes' => array_filter(
								$attributes,
								static function ( $attribute_value ) {
									return null !== $attribute_value;
								}
							),
						]
					),
				],
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);
	}

	/**
	 * Render a select control (covers order, orderby, and select types).
	 *
	 * @since ??
	 *
	 * @param array  $list_items           Selectable list items.
	 * @param string $option_preview_label  Option preview label for the placeholder.
	 * @param string $aria_label            Accessible label.
	 * @param string $input_id             Input element id.
	 * @param string $field_param_name     GET param name.
	 * @param mixed  $query_value          Current query value.
	 * @param string $target_loop          Parent target loop id.
	 * @param string $field_type           Field control type.
	 * @param string $field_option_key     Field option key.
	 * @param string $custom_field_key     Manual meta key.
	 * @param string $module_id           Module id.
	 * @param string $field_comparison     Comparison operator.
	 * @param string $field_value_type     Selected Field Value Type.
	 * @param string $locked_archive_term_slug Locked archive term slug when applicable.
	 *
	 * @return string
	 */
	private static function _render_select(
		array $list_items,
		string $option_preview_label,
		string $aria_label,
		string $input_id,
		string $field_param_name,
		$query_value,
		string $target_loop,
		string $field_type,
		string $field_option_key,
		string $custom_field_key,
		string $module_id,
		string $field_comparison,
		string $field_value_type = '',
		string $locked_archive_term_slug = ''
	): string {
		$is_archive_locked_single_choice = 'select' === $field_type && '' !== $locked_archive_term_slug;
		$select_placeholder              = sprintf(
			/* translators: %s: option preview label. */
			__( 'Select %s', 'et_builder_5' ),
			$option_preview_label
		);
		$select_options                  = [];

		$has_query_selection = is_string( $query_value ) && '' !== $query_value;

		if ( empty( $list_items ) ) {
			$select_options[] = HTMLUtility::render(
				[
					'tag'        => 'option',
					'attributes' => [
						'value'    => '',
						'selected' => ! $has_query_selection,
						'disabled' => true,
					],
					'children'   => esc_html__( 'No options available', 'et_builder_5' ),
				]
			);
		} elseif ( $is_archive_locked_single_choice ) {
			foreach ( $list_items as $list_item ) {
				$option_value = $list_item['value'] ?? '';
				$option_label = $list_item['label'] ?? $option_value;

				$select_options[] = HTMLUtility::render(
					[
						'tag'        => 'option',
						'attributes' => [
							'value'    => esc_attr( $option_value ),
							'selected' => true,
						],
						'children'   => esc_html( $option_label ),
					]
				);
			}
		} else {
			$select_options[] = HTMLUtility::render(
				[
					'tag'        => 'option',
					'attributes' => [
						'value'    => '',
						'selected' => ! $has_query_selection,
						'disabled' => true,
					],
					'children'   => esc_html( $select_placeholder ),
				]
			);

			foreach ( $list_items as $list_item ) {
				$option_value = $list_item['value'] ?? '';
				$option_label = $list_item['label'] ?? $option_value;
				$option_attrs = [
					'value' => esc_attr( $option_value ),
				];

				if ( PostFilterUtils::is_query_option_selected( $query_value, $option_value ) ) {
					$option_attrs['selected'] = true;
				}

				$select_options[] = HTMLUtility::render(
					[
						'tag'        => 'option',
						'attributes' => $option_attrs,
						'children'   => esc_html( $option_label ),
					]
				);
			}
		}

		$select_attributes = [
			'aria-label' => esc_attr( $aria_label ),
			'class'      => 'et_pb_post_filter__item-control et_pb_post_filter__item-control-select',
			'id'         => $input_id,
			'name'       => '' !== $field_param_name ? $field_param_name : null,
		];

		if ( $is_archive_locked_single_choice ) {
			$select_attributes['disabled'] = true;
		}

		$select_markup = HTMLUtility::render(
			[
				'tag'               => 'select',
				'attributes'        => array_filter( $select_attributes ),
				'children'          => $select_options,
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);

		$companion_inputs = self::_render_companion_hidden_inputs(
			$target_loop,
			$field_type,
			$field_option_key,
			$custom_field_key,
			$module_id,
			$field_comparison,
			$field_value_type
		);

		$locked_archive_term_hidden_input = $is_archive_locked_single_choice
			? self::_render_locked_archive_term_hidden_input( $field_param_name, $locked_archive_term_slug )
			: '';

		if ( '' === $companion_inputs && '' === $locked_archive_term_hidden_input ) {
			return $select_markup;
		}

		return HTMLUtility::render(
			[
				'tag'               => 'div',
				'children'          => [
					$companion_inputs,
					$locked_archive_term_hidden_input,
					$select_markup,
				],
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);
	}

	/**
	 * Render a text search input control.
	 *
	 * @since ??
	 *
	 * @param string $field_label                               Configured label value.
	 * @param string $field_placeholder                         Configured placeholder text.
	 * @param string $aria_label                                Accessible label.
	 * @param string $input_id                                  Input element id.
	 * @param string $field_param_name                          GET param name.
	 * @param mixed  $query_value                               Current query value.
	 * @param string $target_loop                               Parent target loop id.
	 * @param string $field_type                                Field control type.
	 * @param string $field_option_key                          Field option key.
	 * @param string $custom_field_key                          Manual meta key.
	 * @param string $module_id                                 Module id.
	 * @param string $search_target                             Primary search target slug.
	 * @param string $search_target_custom_field_key            Meta key for custom field primary search target.
	 * @param string $target_loop_query_type                    Parent target loop query type.
	 *
	 * @return string
	 */
	private static function _render_search(
		string $field_label,
		string $field_placeholder,
		string $aria_label,
		string $input_id,
		string $field_param_name,
		$query_value,
		string $target_loop,
		string $field_type,
		string $field_option_key,
		string $custom_field_key,
		string $module_id,
		string $search_target,
		string $search_target_custom_field_key,
		string $target_loop_query_type
	): string {
		$resolved_search_target = '' !== $search_target
			? $search_target
			: PostFilterCustomFieldUtils::resolve_default_search_target( $target_loop_query_type );

		$is_user_loop                    = PostFilterCustomFieldUtils::is_user_loop_query_type( $target_loop_query_type );
		$is_dedicated_search_target_loop = $is_user_loop || PostFilterCustomFieldUtils::is_menus_loop_query_type( $target_loop_query_type );
		$search_target_meta_key          = PostFilterCustomFieldUtils::resolve_meta_key( $resolved_search_target, $search_target_custom_field_key );

		$target_param_name      = PostFilterUtils::get_field_query_param_name( $target_loop, $field_type, $field_option_key, 'target', false, $custom_field_key, $module_id );
		$target_meta_param_name = PostFilterUtils::get_field_query_param_name( $target_loop, $field_type, $field_option_key, 'target_meta', false, $custom_field_key, $module_id );

		$search_placeholder = '' !== $field_placeholder
			? esc_attr( $field_placeholder )
			: ( '' !== $field_label ? esc_attr( $field_label ) : esc_attr__( 'Search', 'et_builder_5' ) );

		$search_attributes = [
			'aria-label'      => esc_attr( $aria_label ),
			'class'           => 'et_pb_post_filter__item-control',
			'data-field_type' => 'text',
			'id'              => $input_id,
			'name'            => '' !== $field_param_name ? $field_param_name : null,
			'placeholder'     => $search_placeholder,
			'type'            => 'text',
		];

		if ( is_string( $query_value ) && '' !== $query_value ) {
			$search_attributes['value'] = esc_attr( $query_value );
		}

		$target_input = '' !== $target_param_name
			? HTMLUtility::render(
				[
					'tag'        => 'input',
					'attributes' => [
						'name'  => $target_param_name,
						'type'  => 'hidden',
						'value' => esc_attr( $resolved_search_target ),
					],
				]
			)
			: '';

		$target_meta_input = ! $is_dedicated_search_target_loop
			&& '' !== $target_meta_param_name
			&& PostFilterCustomFieldUtils::is_custom_field_option( $resolved_search_target )
			&& '' !== $search_target_meta_key
			? HTMLUtility::render(
				[
					'tag'        => 'input',
					'attributes' => [
						'name'  => $target_meta_param_name,
						'type'  => 'hidden',
						'value' => esc_attr( $search_target_meta_key ),
					],
				]
			)
			: '';

		return HTMLUtility::render(
			[
				'tag'               => 'div',
				'children'          => [
					$target_input,
					$target_meta_input,
					HTMLUtility::render(
						[
							'tag'        => 'input',
							'attributes' => array_filter(
								$search_attributes,
								static function ( $attribute_value ) {
									return null !== $attribute_value;
								}
							),
						]
					),
				],
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);
	}

	/**
	 * Render hidden inputs that submit resolved meta key and comparison for module-scoped
	 * custom-field list controls.
	 *
	 * @since ??
	 *
	 * @param string $target_loop      Parent target loop id.
	 * @param string $field_type       Child field type.
	 * @param string $field_option_key Child field option key.
	 * @param string $custom_field_key Manual meta key when manual custom field option is selected.
	 * @param string $module_id        Post-filter-item module id.
	 * @param string $field_comparison Configured comparison operator.
	 * @param string $field_value_type Selected Field Value Type.
	 *
	 * @return string
	 */
	private static function _render_companion_hidden_inputs(
		string $target_loop,
		string $field_type,
		string $field_option_key,
		string $custom_field_key,
		string $module_id,
		string $field_comparison,
		string $field_value_type = ''
	): string {
		$resolved_meta_key = PostFilterCustomFieldUtils::resolve_companion_filter_meta_key( $field_option_key, $custom_field_key );

		if ( '' === $resolved_meta_key && ! PostFilterCustomFieldUtils::is_custom_field_option( $field_option_key ) ) {
			return '';
		}

		$param_field_type = PostFilterCustomFieldUtils::resolve_query_param_field_type(
			[
				'type'           => $field_type,
				'fieldValueType' => $field_value_type,
			]
		);

		$compare_param_name = PostFilterUtils::get_field_query_param_name(
			$target_loop,
			$param_field_type,
			$field_option_key,
			'compare',
			false,
			$custom_field_key,
			$module_id,
			$field_value_type
		);
		$meta_param_name    = PostFilterUtils::get_field_query_param_name(
			$target_loop,
			$param_field_type,
			$field_option_key,
			'meta',
			false,
			$custom_field_key,
			$module_id,
			$field_value_type
		);

		$meta_input = '' !== $meta_param_name && '' !== $resolved_meta_key
			? HTMLUtility::render(
				[
					'tag'        => 'input',
					'attributes' => [
						'name'  => $meta_param_name,
						'type'  => 'hidden',
						'value' => esc_attr( $resolved_meta_key ),
					],
				]
			)
			: '';

		$compare_input = '' !== $compare_param_name
			? HTMLUtility::render(
				[
					'tag'        => 'input',
					'attributes' => [
						'name'  => $compare_param_name,
						'type'  => 'hidden',
						'value' => esc_attr( $field_comparison ),
					],
				]
			)
			: '';

		return $meta_input . $compare_input;
	}

	/**
	 * Render empty list-control state text.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	private static function _render_options_empty_state(): string {
		return HTMLUtility::render(
			[
				'tag'        => 'span',
				'attributes' => [
					'class' => 'et_pb_post_filter__item-options-empty',
				],
				'children'   => esc_html__( 'No options available', 'et_builder_5' ),
			]
		);
	}

	/**
	 * Render inline SVG star icons for product-review filter options.
	 *
	 * @since ??
	 *
	 * @param int $rating Star count from 1 to 5.
	 *
	 * @return string
	 */
	private static function _render_product_review_rating_stars( int $rating ): string {
		$rating     = max( 1, min( 5, $rating ) );
		$stars_html = '';
		$star_size  = self::PRODUCT_REVIEW_STAR_SIZE;
		$star_color = esc_attr( self::PRODUCT_REVIEW_STAR_COLOR );

		for ( $index = 0; $index < $rating; $index++ ) {
			$stars_html .= sprintf(
				'<svg xmlns="http://www.w3.org/2000/svg" class="et_pb_post_filter__item-rating-star" viewBox="%1$s" width="%2$d" height="%2$d" aria-hidden="true" focusable="false"><path fill="%3$s" fill-rule="evenodd" clip-rule="evenodd" d="%4$s"></path></svg>',
				esc_attr( self::PRODUCT_REVIEW_STAR_VIEW_BOX ),
				$star_size,
				$star_color,
				esc_attr( self::PRODUCT_REVIEW_STAR_PATH )
			);
		}

		return sprintf(
			'<span class="et_pb_post_filter__item-rating-stars" aria-hidden="true">%s</span>',
			$stars_html
		);
	}

	/**
	 * Render radio option label markup, including star ratings for product-review.
	 *
	 * @since ??
	 *
	 * @param string $field_type   Field control type.
	 * @param string $option_value Option value slug.
	 * @param string $option_label Accessible option label.
	 * @param string $option_id    Input id for label association.
	 *
	 * @return string
	 */
	private static function _render_radio_option_label( string $field_type, string $option_value, string $option_label, string $option_id ): string {
		if ( 'product-review' === $field_type ) {
			$rating = absint( $option_value );

			if ( 1 <= $rating && 5 >= $rating ) {
				return sprintf(
					'<label for="%1$s">%2$s%3$s</label>',
					esc_attr( $option_id ),
					HTMLUtility::render(
						[
							'tag' => 'i',
						]
					),
					self::_render_product_review_rating_stars( $rating )
				);
			}
		}

		$label_content = esc_html( $option_label );

		return HTMLUtility::render(
			[
				'tag'               => 'label',
				'attributes'        => [
					'for' => $option_id,
				],
				'children'          => [
					HTMLUtility::render(
						[
							'tag' => 'i',
						]
					),
					$label_content,
				],
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);
	}

	/**
	 * Render a hidden input that preserves a locked archive term in GET submissions.
	 *
	 * Disabled checkbox inputs do not submit, so the locked archive term must be
	 * carried by a companion hidden field.
	 *
	 * @since ??
	 *
	 * @param string $param_name         GET param name for the list control.
	 * @param string $locked_term_slug   Locked archive term slug.
	 *
	 * @return string
	 */
	private static function _render_locked_archive_term_hidden_input( string $param_name, string $locked_term_slug ): string {
		if ( '' === $param_name || '' === $locked_term_slug ) {
			return '';
		}

		return HTMLUtility::render(
			[
				'tag'        => 'input',
				'attributes' => [
					'name'  => $param_name,
					'type'  => 'hidden',
					'value' => esc_attr( $locked_term_slug ),
				],
			]
		);
	}

	/**
	 * Resolve a field type string, falling back to `text` for unknown or WooCommerce-only types
	 * when WooCommerce is not active.
	 *
	 * @since ??
	 *
	 * @param mixed $value Candidate field type.
	 *
	 * @return string
	 */
	public static function resolve_field_type( $value ): string {
		$allowed_types = [
			'text',
			'select',
			'checkbox',
			'radio',
			'product-range',
			'product-status',
			'product-review',
			'order',
			'orderby',
			'multiple-order',
			'submit',
			'reset',
		];

		if ( in_array( $value, $allowed_types, true ) ) {
			if ( in_array( $value, [ 'product-range', 'product-status', 'product-review' ], true ) && ! et_is_woocommerce_plugin_active() ) {
				return 'text';
			}

			return $value;
		}

		return 'text';
	}

	/**
	 * Resolve a field option key, coercing non-strings to empty string.
	 *
	 * @since ??
	 *
	 * @param mixed $value Candidate option key.
	 *
	 * @return string
	 */
	private static function _resolve_field_option_key( $value ): string {
		return is_string( $value ) ? $value : '';
	}
}
