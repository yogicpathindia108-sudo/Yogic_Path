<?php
/**
 * Post Filter Item multiple-order control renderer.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostFilterItem;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\Packages\ModuleLibrary\PostFilter\PostFilterUtils;

/**
 * Renders the compound multiple-order sorting control for the multiple-order filter type.
 *
 * @since ??
 */
class PostFilterItemMultipleOrderControl {
	/**
	 * Render the multiple-order compound control for the front end.
	 *
	 * Returns an empty string when the target loop is unset or targets the main query,
	 * since multiple-order sorting requires a named custom loop.
	 *
	 * @since ??
	 *
	 * @param string $target_loop   Parent target loop id.
	 * @param string $module_id     Module id.
	 * @param array  $orderby_items Order-by list items.
	 *
	 * @return string
	 */
	public static function render( string $target_loop, string $module_id, array $orderby_items ): string {
		$normalized_target_loop = trim( $target_loop );

		if ( '' === $normalized_target_loop || 'main_query' === $normalized_target_loop ) {
			return '';
		}

		$filter_params       = PostFilterUtils::get_loop_filter_params_from_request( $normalized_target_loop );
		$multiple_order_rows = isset( $filter_params['multiple-order'] ) && is_array( $filter_params['multiple-order'] )
			? $filter_params['multiple-order']
			: [];
		$order_items         = PostFilterFieldListItems::get_list_items( 'order', 'order' );

		if ( empty( $multiple_order_rows ) ) {
			$multiple_order_rows = [
				[
					'order_by' => 'default',
					'order'    => 'default',
				],
			];
		}

		$row_markup = [];

		foreach ( $multiple_order_rows as $row_index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$order_by_value = isset( $row['order_by'] ) ? PostFilterCustomFieldUtils::sanitize_clause_key( (string) $row['order_by'] ) : 'default';
			$order_value    = isset( $row['order'] ) ? sanitize_key( (string) $row['order'] ) : 'default';
			$order_by_name  = "loop_filter-{$normalized_target_loop}[multiple-order][{$row_index}][order_by]";
			$order_name     = "loop_filter-{$normalized_target_loop}[multiple-order][{$row_index}][order]";
			$row_children   = [
				self::_wrap_select(
					self::_render_select(
						$orderby_items,
						$order_by_name,
						$order_by_value,
						esc_attr__( 'Order By', 'et_builder_5' ),
						esc_html__( 'Select order by', 'et_builder_5' ),
						'et_pb_post_filter__item-multiple-order-orderby'
					)
				),
				self::_wrap_select(
					self::_render_select(
						$order_items,
						$order_name,
						$order_value,
						esc_attr__( 'Order', 'et_builder_5' ),
						esc_html__( 'Select order', 'et_builder_5' ),
						'et_pb_post_filter__item-multiple-order-order'
					)
				),
			];

			if ( ( count( $multiple_order_rows ) - 1 ) === $row_index && count( $multiple_order_rows ) < 5 ) {
				$row_children[] = HTMLUtility::render(
					[
						'tag'        => 'button',
						'attributes' => [
							'aria-label' => esc_attr__( 'Add sort rule', 'et_builder_5' ),
							'class'      => 'et_pb_post_filter__item-multiple-order-action et_pb_post_filter__item-multiple-order-add',
							'type'       => 'button',
						],
						'children'   => esc_html__( 'Add', 'et_builder_5' ),
					]
				);
			}

			if ( 1 < count( $multiple_order_rows ) ) {
				$row_children[] = HTMLUtility::render(
					[
						'tag'        => 'button',
						'attributes' => [
							'aria-label' => esc_attr__( 'Remove sort rule', 'et_builder_5' ),
							'class'      => 'et_pb_post_filter__item-multiple-order-action et_pb_post_filter__item-multiple-order-remove',
							'type'       => 'button',
						],
						'children'   => esc_html__( 'Remove', 'et_builder_5' ),
					]
				);
			}

			$row_markup[] = HTMLUtility::render(
				[
					'tag'               => 'span',
					'attributes'        => [
						'class' => 'et_pb_post_filter__item-multiple-order-row',
					],
					'children'          => $row_children,
					'childrenSanitizer' => 'et_core_esc_previously',
				]
			);
		}

		return HTMLUtility::render(
			[
				'tag'               => 'span',
				'attributes'        => [
					'class'                 => 'et_pb_post_filter__item-multiple-order',
					'data-module-id'        => esc_attr( $module_id ),
					'data-namespace-prefix' => esc_attr( "loop_filter-{$normalized_target_loop}" ),
				],
				'children'          => $row_markup,
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);
	}

	/**
	 * Render one select control for use inside a multiple-order row.
	 *
	 * @since ??
	 *
	 * @param array  $list_items   Select options.
	 * @param string $name         Input name.
	 * @param string $value        Selected value.
	 * @param string $aria_label   Accessible label.
	 * @param string $placeholder  Placeholder option label.
	 * @param string $extra_class  Additional class names.
	 *
	 * @return string
	 */
	private static function _render_select( array $list_items, string $name, string $value, string $aria_label, string $placeholder, string $extra_class = '' ): string {
		$select_options = [
			HTMLUtility::render(
				[
					'tag'        => 'option',
					'attributes' => [
						'disabled' => true,
						'value'    => '',
					],
					'children'   => esc_html( $placeholder ),
				]
			),
		];

		foreach ( $list_items as $list_item ) {
			$option_value = $list_item['value'] ?? '';
			$option_label = $list_item['label'] ?? $option_value;
			$option_attrs = [
				'value' => esc_attr( $option_value ),
			];

			if ( PostFilterUtils::is_query_option_selected( $value, $option_value ) ) {
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

		return HTMLUtility::render(
			[
				'tag'               => 'select',
				'attributes'        => [
					'aria-label' => $aria_label,
					'class'      => trim( "et_pb_post_filter__item-control et_pb_post_filter__item-control-select {$extra_class}" ),
					'name'       => $name,
				],
				'children'          => $select_options,
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);
	}

	/**
	 * Wrap one multiple-order select with the dropdown chevron host element.
	 *
	 * @since ??
	 *
	 * @param string $select_markup Rendered select markup.
	 *
	 * @return string
	 */
	private static function _wrap_select( string $select_markup ): string {
		return HTMLUtility::render(
			[
				'tag'               => 'span',
				'attributes'        => [
					'class' => 'et_pb_post_filter__item-multiple-order-select',
				],
				'children'          => $select_markup,
				'childrenSanitizer' => 'et_core_esc_previously',
			]
		);
	}
}
