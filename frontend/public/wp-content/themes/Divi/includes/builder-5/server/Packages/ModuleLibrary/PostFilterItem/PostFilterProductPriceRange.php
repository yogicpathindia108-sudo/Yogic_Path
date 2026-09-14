<?php
/**
 * WooCommerce product price range helper for post-filter-item.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostFilterItem;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Resolves catalog price bounds and display formatting for product price range controls.
 *
 * Mirrors WooCommerce price filter behavior: bounds come from `wc_product_meta_lookup`
 * min/max columns for published products, and display values use the store currency.
 *
 * @since ??
 */
class PostFilterProductPriceRange {
	/**
	 * Fallback bounds when WooCommerce is unavailable.
	 *
	 * @var array<string,int|string>
	 */
	private const FALLBACK_RANGE = [
		'min'                         => 0,
		'max'                         => 100,
		'step'                        => 1,
		'currency_symbol'             => '$',
		'currency_position'           => 'left',
		'currency_decimals'           => 0,
		'currency_decimal_separator'  => '.',
		'currency_thousand_separator' => ',',
		'currency_format'             => '%s%v',
	];

	/**
	 * Return product price range metadata for slider controls.
	 *
	 * @since ??
	 *
	 * @return array{
	 *     min:int,
	 *     max:int,
	 *     step:int,
	 *     currency_symbol:string,
	 *     currency_position:string,
	 *     formatted_min:string,
	 *     formatted_max:string
	 * }
	 */
	public static function get_range(): array {
		if ( ! et_is_woocommerce_plugin_active() || ! function_exists( 'wc_get_price_decimal_separator' ) ) {
			return self::_with_formatted_values( self::FALLBACK_RANGE );
		}

		global $wpdb;

		$post_types = apply_filters( 'woocommerce_price_filter_post_type', [ 'product' ] );
		$post_types = array_map( 'esc_sql', array_map( 'strval', $post_types ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Post types are escaped above.
		$sql = "
			SELECT min( min_price ) as min_price, MAX( max_price ) as max_price
			FROM {$wpdb->wc_product_meta_lookup}
			WHERE product_id IN (
				SELECT ID FROM {$wpdb->posts}
				WHERE {$wpdb->posts}.post_type IN ('" . implode( "','", $post_types ) . "')
				AND {$wpdb->posts}.post_status = 'publish'
			)
		";

		$sql     = apply_filters( 'woocommerce_price_filter_sql', $sql, [ 'join' => '', 'where' => '' ], [ 'join' => '', 'where' => '' ] );
		$prices = $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Matches WooCommerce widget pattern.

		if ( ! $prices || ( null === $prices->min_price && null === $prices->max_price ) ) {
			return self::_with_formatted_values( self::FALLBACK_RANGE );
		}

		$min_raw = floatval( $prices->min_price ?? 0 );
		$max_raw = floatval( $prices->max_price ?? 0 );

		$step = max( (int) apply_filters( 'woocommerce_price_filter_widget_step', 1 ), 1 );

		$tax_display_mode = get_option( 'woocommerce_tax_display_shop' );

		if ( wc_tax_enabled() && ! wc_prices_include_tax() && 'incl' === $tax_display_mode ) {
			$tax_class = apply_filters( 'woocommerce_price_filter_widget_tax_class', '' );
			$tax_rates = \WC_Tax::get_rates( $tax_class );

			if ( $tax_rates ) {
				$min_raw += \WC_Tax::get_tax_total( \WC_Tax::calc_exclusive_tax( $min_raw, $tax_rates ) );
				$max_raw += \WC_Tax::get_tax_total( \WC_Tax::calc_exclusive_tax( $max_raw, $tax_rates ) );
			}
		}

		$min_price = (int) apply_filters( 'woocommerce_price_filter_widget_min_amount', (int) floor( $min_raw / $step ) * $step );
		$max_price = (int) apply_filters( 'woocommerce_price_filter_widget_max_amount', (int) ceil( $max_raw / $step ) * $step );

		if ( $max_price < $min_price ) {
			$max_price = $min_price;
		}

		return self::_with_formatted_values(
			array_merge(
				self::_get_currency_format_metadata(),
				[
					'min'  => $min_price,
					'max'  => $max_price,
					'step' => $step,
				]
			)
		);
	}

	/**
	 * Format one integer price for display using WooCommerce currency settings.
	 *
	 * @since ??
	 *
	 * @param int $amount Integer price amount.
	 *
	 * @return string
	 */
	public static function format_price( int $amount ): string {
		if ( ! function_exists( 'wc_price' ) ) {
			return self::FALLBACK_RANGE['currency_symbol'] . $amount;
		}

		return html_entity_decode(
			wp_strip_all_tags(
				wc_price(
					$amount,
					[
						'decimals' => 0,
					]
				)
			)
		);
	}

	/**
	 * Append formatted min/max strings to a range payload.
	 *
	 * @since ??
	 *
	 * @param array<string,int|string> $range Range payload.
	 *
	 * @return array<string,int|string>
	 */
	private static function _with_formatted_values( array $range ): array {
		$min = (int) ( $range['min'] ?? 0 );
		$max = (int) ( $range['max'] ?? 0 );

		$range['formatted_min'] = self::format_price( $min );
		$range['formatted_max'] = self::format_price( $max );

		return $range;
	}

	/**
	 * Resolve whether the currency symbol appears before or after the amount.
	 *
	 * @since ??
	 *
	 * @return string `left` or `right`.
	 */
	private static function _get_currency_position(): string {
		if ( ! function_exists( 'get_woocommerce_price_format' ) ) {
			return 'left';
		}

		$format = get_woocommerce_price_format();

		return 0 === strpos( $format, '%1$s' ) ? 'left' : 'right';
	}

	/**
	 * Return WooCommerce currency formatting metadata for client-side display.
	 *
	 * Mirrors `woocommerce_price_slider_params` from the classic price filter widget.
	 *
	 * @since ??
	 *
	 * @return array<string,int|string>
	 */
	private static function _get_currency_format_metadata(): array {
		if ( ! function_exists( 'get_woocommerce_currency_symbol' ) ) {
			return [
				'currency_symbol'             => self::FALLBACK_RANGE['currency_symbol'],
				'currency_position'           => self::FALLBACK_RANGE['currency_position'],
				'currency_decimals'           => self::FALLBACK_RANGE['currency_decimals'],
				'currency_decimal_separator'  => self::FALLBACK_RANGE['currency_decimal_separator'],
				'currency_thousand_separator' => self::FALLBACK_RANGE['currency_thousand_separator'],
				'currency_format'             => self::FALLBACK_RANGE['currency_format'],
			];
		}

		$price_format = get_woocommerce_price_format();

		return [
			'currency_symbol'             => html_entity_decode(
				get_woocommerce_currency_symbol(),
				ENT_QUOTES | ENT_HTML5,
				'UTF-8'
			),
			'currency_position'           => self::_get_currency_position(),
			'currency_decimals'           => 0,
			'currency_decimal_separator'  => wc_get_price_decimal_separator(),
			'currency_thousand_separator' => wc_get_price_thousand_separator(),
			'currency_format'             => str_replace( [ '%1$s', '%2$s' ], [ '%s', '%v' ], $price_format ),
		];
	}
}
