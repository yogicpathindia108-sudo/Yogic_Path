<?php
/**
 * Post Filter Item range control renderer.
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
 * Renders the dual-handle price-range slider control for the product-range filter type.
 *
 * @since ??
 */
class PostFilterItemRangeControl {
	/**
	 * Render dual-handle range control markup.
	 *
	 * @since ??
	 *
	 * @param string $module_id   Module id.
	 * @param string $target_loop Parent target loop id.
	 *
	 * @return string
	 */
	public static function render( string $module_id, string $target_loop = '' ): string {
		$price_range        = PostFilterProductPriceRange::get_range();
		$min_param_name     = PostFilterUtils::get_field_query_param_name( $target_loop, 'product-range', '', 'min' );
		$max_param_name     = PostFilterUtils::get_field_query_param_name( $target_loop, 'product-range', '', 'max' );
		$range_min_bound    = (int) ( $price_range['min'] ?? 0 );
		$range_max_bound    = (int) ( $price_range['max'] ?? 100 );
		$range_default_min  = $range_min_bound;
		$range_default_max  = $range_max_bound;
		$min_query_value    = PostFilterUtils::get_field_query_param_value( $target_loop, 'product-range', '', 'min' );
		$max_query_value    = PostFilterUtils::get_field_query_param_value( $target_loop, 'product-range', '', 'max' );
		$range_default_min  = self::_parse_query_value( $min_query_value, $range_default_min, $range_min_bound, $range_max_bound );
		$range_default_max  = self::_parse_query_value( $max_query_value, $range_default_max, $range_min_bound, $range_max_bound );
		$range_step         = (int) ( $price_range['step'] ?? 1 );
		$currency_symbol    = (string) ( $price_range['currency_symbol'] ?? '$' );
		$currency_position  = (string) ( $price_range['currency_position'] ?? 'left' );
		$range_id           = "post-filter-item-range-{$module_id}";
		$min_slider_id      = "{$range_id}-min";
		$max_slider_id      = "{$range_id}-max";
		$min_value_input_id = "{$range_id}-min-value";
		$max_value_input_id = "{$range_id}-max-value";
		$min_display_value  = PostFilterProductPriceRange::format_price( $range_default_min );
		$max_display_value  = PostFilterProductPriceRange::format_price( $range_default_max );
		$range_span         = max( $range_max_bound - $range_min_bound, 1 );
		$progress_left      = ( ( $range_default_min - $range_min_bound ) / $range_span ) * 100;
		$progress_width     = max( ( ( $range_default_max - $range_default_min ) / $range_span ) * 100, 0 );
		$progress_style     = sprintf( 'left:%s%%;width:%s%%', $progress_left, $progress_width );

		$min_slider = HTMLUtility::render(
			[
				'tag'        => 'input',
				'attributes' => [
					'aria-label' => esc_attr__( 'Minimum value', 'et_builder_5' ),
					'class'      => 'et_pb_post_filter__item-range-input et_pb_post_filter__item-range-input--min',
					'id'         => $min_slider_id,
					'max'        => (string) $range_max_bound,
					'min'        => (string) $range_min_bound,
					'step'       => (string) $range_step,
					'type'       => 'range',
					'value'      => (string) $range_default_min,
				],
			]
		);

		$max_slider = HTMLUtility::render(
			[
				'tag'        => 'input',
				'attributes' => [
					'aria-label' => esc_attr__( 'Maximum value', 'et_builder_5' ),
					'class'      => 'et_pb_post_filter__item-range-input et_pb_post_filter__item-range-input--max',
					'id'         => $max_slider_id,
					'max'        => (string) $range_max_bound,
					'min'        => (string) $range_min_bound,
					'step'       => (string) $range_step,
					'type'       => 'range',
					'value'      => (string) $range_default_max,
				],
			]
		);

		$min_value_input = HTMLUtility::render(
			[
				'tag'        => 'input',
				'attributes' => array_filter(
					[
						'aria-label' => esc_attr__( 'Minimum value', 'et_builder_5' ),
						'class'      => 'et_pb_post_filter__item-control et_pb_post_filter__item-range-value et_pb_post_filter__item-range-value--min',
						'id'         => $min_value_input_id,
						'inputmode'  => 'decimal',
						'name'       => '' !== $min_param_name ? $min_param_name : null,
						'type'       => 'text',
						'value'      => esc_attr( $min_display_value ),
					]
				),
			]
		);

		$max_value_input = HTMLUtility::render(
			[
				'tag'        => 'input',
				'attributes' => array_filter(
					[
						'aria-label' => esc_attr__( 'Maximum value', 'et_builder_5' ),
						'class'      => 'et_pb_post_filter__item-control et_pb_post_filter__item-range-value et_pb_post_filter__item-range-value--max',
						'id'         => $max_value_input_id,
						'inputmode'  => 'decimal',
						'name'       => '' !== $max_param_name ? $max_param_name : null,
						'type'       => 'text',
						'value'      => esc_attr( $max_display_value ),
					]
				),
			]
		);

		return HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'aria-labelledby'                  => "{$range_id}-label",
					'class'                            => 'et_pb_post_filter__item-range',
					'data-currency-decimal-separator'  => esc_attr( (string) ( $price_range['currency_decimal_separator'] ?? '.' ) ),
					'data-currency-format'             => esc_attr( (string) ( $price_range['currency_format'] ?? '%s%v' ) ),
					'data-currency-position'           => esc_attr( $currency_position ),
					'data-currency-symbol'             => esc_attr( $currency_symbol ),
					'data-currency-thousand-separator' => esc_attr( (string) ( $price_range['currency_thousand_separator'] ?? ',' ) ),
					'data-max'                         => (string) $range_max_bound,
					'data-min'                         => (string) $range_min_bound,
					'data-step'                        => (string) $range_step,
					'role'                             => 'group',
				],
				'children'          => [
					HTMLUtility::render(
						[
							'tag'               => 'div',
							'attributes'        => [
								'class' => 'et_pb_post_filter__item-range-slider',
							],
							'children'          => [
								HTMLUtility::render(
									[
										'tag'               => 'div',
										'attributes'        => [
											'class' => 'et_pb_post_filter__item-range-track',
										],
										'children'          => [
											HTMLUtility::render(
												[
													'tag'        => 'div',
													'attributes' => [
														'class' => 'et_pb_post_filter__item-range-progress',
														'style' => esc_attr( $progress_style ),
													],
												]
											),
										],
										'childrenSanitizer' => 'et_core_esc_previously',
									]
								),
								$min_slider,
								$max_slider,
							],
							'childrenSanitizer' => 'et_core_esc_previously',
						]
					),
					HTMLUtility::render(
						[
							'tag'               => 'div',
							'attributes'        => [
								'class' => 'et_pb_post_filter__item-range-values',
							],
							'children'          => [
								$min_value_input,
								$max_value_input,
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
	 * Parse a range query-string value into an integer within bounds.
	 *
	 * @since ??
	 *
	 * @param mixed $raw_value  Query-string value.
	 * @param int   $fallback   Fallback when parsing fails.
	 * @param int   $bound_min  Minimum allowed value.
	 * @param int   $bound_max  Maximum allowed value.
	 *
	 * @return int
	 */
	private static function _parse_query_value( $raw_value, int $fallback, int $bound_min, int $bound_max ): int {
		if ( ! is_string( $raw_value ) || '' === $raw_value ) {
			return $fallback;
		}

		$digits = preg_replace( '/[^\d]/', '', $raw_value );

		if ( ! is_string( $digits ) || '' === $digits ) {
			return $fallback;
		}

		return max( $bound_min, min( $bound_max, (int) $digits ) );
	}
}
