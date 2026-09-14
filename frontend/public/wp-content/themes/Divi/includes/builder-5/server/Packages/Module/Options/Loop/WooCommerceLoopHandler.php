<?php
/**
 * WooCommerce Loop Handler.
 *
 * @package Builder\Packages\Module\Options\Loop
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Loop;

use ET\Builder\Packages\ModuleUtils\ModuleUtils;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * WooCommerce Loop Handler class.
 *
 * @since ??
 */
class WooCommerceLoopHandler {

	/**
	 * Field configuration mapping.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	private static $_field_config = [
		'loop_product_price_regular'       => [
			'custom' => 'get_formatted_regular_price',
			'escape' => 'wp_kses_post',
		],
		'loop_product_price_sale'          => [
			'custom' => 'get_formatted_sale_price',
			'escape' => 'wp_kses_post',
		],
		'loop_product_price_current'       => [
			'custom' => 'get_formatted_price',
			'escape' => 'wp_kses_post',
		],
		'loop_product_description'         => [
			'method' => 'get_description',
			'escape' => 'wp_kses_post',
		],
		'loop_product_short_description'   => [
			'method' => 'get_short_description',
			'escape' => 'wp_kses_post',
		],
		'loop_product_stock_quantity'      => [
			'custom' => 'get_stock_quantity_safe',
			'escape' => 'esc_html',
		],
		'loop_product_stock_status'        => [
			'method' => 'get_stock_status',
			'escape' => 'esc_html',
		],
		'loop_product_reviews_count'       => [
			'method' => 'get_review_count',
			'escape' => 'absint',
		],
		'loop_product_sku'                 => [
			'method' => 'get_sku',
			'escape' => 'esc_html',
		],
		'loop_product_id'                  => [
			'method' => 'get_id',
			'escape' => 'absint',
		],
		'loop_product_title'               => [
			'method' => 'get_name',
			'escape' => 'esc_html',
		],
		'loop_product_post_date'           => [
			'custom' => 'get_formatted_date',
			'escape' => 'esc_html',
		],
		'loop_product_post_modified_date'  => [
			'custom' => 'get_formatted_modified_date',
			'escape' => 'esc_html',
		],
		'loop_product_post_featured_image' => [
			'custom' => 'get_featured_image_url',
			'escape' => 'esc_url',
		],
		'loop_product_post_link_url'       => [
			'custom' => 'get_product_permalink',
			'escape' => 'esc_url',
		],
		'loop_product_post_comment_count'  => [
			'custom' => 'get_comment_count',
			'escape' => 'absint',
		],
		'loop_product_terms'               => [
			'custom' => 'get_product_terms',
			'escape' => 'wp_kses_post',
		],
	];

	/**
	 * Get filtered variation prices for a specific price type.
	 *
	 * @since ??
	 *
	 * @param \WC_Product $product     Variable product instance.
	 * @param string      $price_type Price type ('price', 'regular_price', 'sale_price').
	 *
	 * @return array Filtered array of prices with null/empty values removed.
	 */
	private static function _get_filtered_variation_prices( \WC_Product $product, string $price_type ): array {
		if ( ! $product->is_type( 'variable' ) ) {
			return [];
		}

		$prices = $product->get_variation_prices( true );

		if ( ! isset( $prices[ $price_type ] ) || ! is_array( $prices[ $price_type ] ) ) {
			return [];
		}

		// Filter out null/empty values but keep legitimate zero prices.
		return array_filter(
			$prices[ $price_type ],
			function ( $price ) {
				return null !== $price && '' !== $price;
			}
		);
	}

	/**
	 * Format product price range.
	 *
	 * @since ??
	 *
	 * @param array $prices Array of prices from get_variation_prices() or grouped product children.
	 *
	 * @return string Formatted price or price range.
	 */
	private static function _format_price_range( array $prices ): string {
		$min_price = min( $prices );
		$max_price = max( $prices );

		if ( $min_price !== $max_price ) {
			return wc_format_price_range( $min_price, $max_price );
		}

		return wc_price( $min_price );
	}

	/**
	 * Get filtered grouped product prices for a specific price type.
	 *
	 * @since ??
	 *
	 * @param \WC_Product $product   Grouped product instance.
	 * @param string      $price_type Price type ('price', 'regular_price', 'sale_price').
	 *
	 * @return array Filtered array of prices with null/empty values removed.
	 */
	private static function _get_grouped_product_prices( \WC_Product $product, string $price_type ): array {
		if ( ! $product->is_type( 'grouped' ) ) {
			return [];
		}

		$children = $product->get_children();

		if ( empty( $children ) ) {
			return [];
		}

		if ( function_exists( 'wc_products_array_filter_visible_grouped' ) ) {
			$children = array_filter( array_map( 'wc_get_product', $children ), 'wc_products_array_filter_visible_grouped' );
		} else {
			$children = array_filter( array_map( 'wc_get_product', $children ) );
		}

		if ( empty( $children ) ) {
			return [];
		}

		$tax_display_mode = get_option( 'woocommerce_tax_display_shop', 'excl' );
		$prices           = [];

		foreach ( $children as $child ) {
			if ( ! $child ) {
				continue;
			}

			$price = '';

			switch ( $price_type ) {
				case 'regular_price':
					$price = $child->get_regular_price();
					break;

				case 'sale_price':
					$price = $child->get_sale_price();
					break;

				case 'price':
				default:
					$price = $child->get_price();
					break;
			}

			if ( '' === $price ) {
				continue;
			}

			if ( 'incl' === $tax_display_mode ) {
				$price = wc_get_price_including_tax( $child, [ 'price' => $price ] );
			} else {
				$price = wc_get_price_excluding_tax( $child, [ 'price' => $price ] );
			}

			$prices[] = $price;
		}

		return array_filter(
			$prices,
			function ( $price ) {
				return null !== $price && '' !== $price;
			}
		);
	}

	/**
	 * Get loop content for WooCommerce product.
	 *
	 * SECURITY NOTE: All user inputs in $settings are expected to be sanitized
	 * at the entry point in LoopUtils::get_query_args_from_attrs() before reaching
	 * this method. This method focuses on output escaping only.
	 *
	 * Enhanced to support flexible date formatting similar to HooksRegistration.
	 *
	 * @since ??
	 *
	 * @param string $name     Loop variable name.
	 * @param mixed  $post     WP_Post object.
	 * @param array  $settings Optional. Field settings for customization. Default [].
	 *
	 * @return string The field value or empty string.
	 */
	public static function get_loop_content( string $name, $post, $settings = [] ): string {
		$product = self::_validate_and_get_product( $post );
		if ( ! $product ) {
			return '';
		}

		return self::_get_field_value( $name, $product, $post, $settings );
	}

	/**
	 * Check if a field is supported.
	 *
	 * @since ??
	 *
	 * @param string $name Field name.
	 *
	 * @return bool True if supported.
	 */
	public static function is_supported_field( string $name ): bool {
		return isset( self::$_field_config[ $name ] );
	}

	/**
	 * Validate post and get WooCommerce product.
	 *
	 * @since ??
	 *
	 * @param mixed $post WP_Post object.
	 *
	 * @return \WC_Product|false Product object or false.
	 */
	private static function _validate_and_get_product( $post ) {
		if ( ! isset( $post->ID ) || ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		$product = wc_get_product( $post->ID );

		return $product ? $product : false;
	}

	/**
	 * Get field value using configuration.
	 *
	 * @since ??
	 *
	 * @param string      $name     Field name.
	 * @param \WC_Product $product  Product object.
	 * @param mixed       $post     WP_Post object.
	 * @param array       $settings Optional. Field settings for customization.
	 *
	 * @return string Field value or empty string.
	 */
	private static function _get_field_value( $name, $product, $post, $settings = [] ): string {
		if ( ! isset( self::$_field_config[ $name ] ) ) {
			return '';
		}

		$config = self::$_field_config[ $name ];

		if ( isset( $config['custom'] ) ) {
			$value = self::_get_custom_value( $config['custom'], $product, $post, $settings );
		} else {
			$method = $config['method'];
			$value  = $product->$method();
		}

		return self::_escape_value( $value, $config['escape'] );
	}

	/**
	 * Get custom field value.
	 *
	 * @since ??
	 *
	 * @param string      $method   Custom method name.
	 * @param \WC_Product $product  Product object.
	 * @param mixed       $post     WP_Post object.
	 * @param array       $settings Optional. Field settings for customization. Default [].
	 *
	 * @return mixed Field value.
	 */
	private static function _get_custom_value( $method, $product, $post, $settings = [] ) {
		switch ( $method ) {
			case 'get_formatted_date':
				$date = $product->get_date_created();
				return ModuleUtils::format_date( $date, $settings );

			case 'get_formatted_modified_date':
				$date = $product->get_date_modified();
				return ModuleUtils::format_date( $date, $settings );

			case 'get_featured_image_url':
				$thumbnail_url = get_the_post_thumbnail_url( $post->ID, 'full' );

				// Fallback to WooCommerce product image.
				if ( ! $thumbnail_url ) {
					$image_id = $product->get_image_id();
					if ( $image_id ) {
						$thumbnail_url = wp_get_attachment_url( $image_id );
					}
				}

				return $thumbnail_url ? $thumbnail_url : '';

			case 'get_product_permalink':
				$permalink = get_permalink( $post->ID );

				return $permalink ? $permalink : '';

			case 'get_comment_count':
				return isset( $post->comment_count ) ? absint( $post->comment_count ) : '0';

			case 'get_stock_quantity_safe':
				$quantity = $product->get_stock_quantity();

				return null === $quantity ? '' : absint( $quantity );

			case 'get_formatted_price':
				// For current price, handle variable and grouped products specially to show price ranges.
				if ( $product->is_type( 'variable' ) ) {
					$current_prices = self::_get_filtered_variation_prices( $product, 'price' );
					if ( ! empty( $current_prices ) ) {
						return self::_format_price_range( $current_prices );
					}
				} elseif ( $product->is_type( 'grouped' ) ) {
					$current_prices = self::_get_grouped_product_prices( $product, 'price' );
					if ( ! empty( $current_prices ) ) {
						return self::_format_price_range( $current_prices );
					}
				}
				return wc_price( $product->get_price() );

			case 'get_formatted_regular_price':
				// For regular price, handle variable and grouped products specially to show price ranges.
				if ( $product->is_type( 'variable' ) ) {
					$regular_prices = self::_get_filtered_variation_prices( $product, 'regular_price' );
					if ( ! empty( $regular_prices ) ) {
						return self::_format_price_range( $regular_prices );
					}
				} elseif ( $product->is_type( 'grouped' ) ) {
					$regular_prices = self::_get_grouped_product_prices( $product, 'regular_price' );
					if ( ! empty( $regular_prices ) ) {
						return self::_format_price_range( $regular_prices );
					}
				}
				return wc_price( $product->get_regular_price() );

			case 'get_formatted_sale_price':
				// For sale price, handle variable and grouped products specially to show price ranges.
				if ( $product->is_type( 'variable' ) ) {
					$sale_prices = self::_get_filtered_variation_prices( $product, 'sale_price' );
					if ( ! empty( $sale_prices ) ) {
						return self::_format_price_range( $sale_prices );
					}
				} elseif ( $product->is_type( 'grouped' ) ) {
					$sale_prices = self::_get_grouped_product_prices( $product, 'sale_price' );
					if ( ! empty( $sale_prices ) ) {
						return self::_format_price_range( $sale_prices );
					}
				}
				return wc_price( $product->get_sale_price() );

			case 'get_product_terms':
				return self::_get_product_terms( $product, $post, $settings );

			default:
				return '';
		}
	}

	/**
	 * Get product terms (categories, tags, or custom taxonomies).
	 *
	 * @since ??
	 *
	 * @param \WC_Product $product  Product object.
	 * @param mixed       $post     WP_Post object.
	 * @param array       $settings Field settings for customization.
	 *
	 * @return string Formatted terms list.
	 */
	private static function _get_product_terms( $product, $post, $settings ): string {
		$taxonomy_type = $settings['taxonomy_type'] ?? 'product_cat';
		$separator     = $settings['separator'] ?? ', ';
		$links_enabled = ( $settings['links'] ?? 'off' ) === 'on';

		// Get terms for the specified taxonomy.
		$terms = get_the_terms( $post->ID, $taxonomy_type );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}

		$terms_list = [];

		foreach ( $terms as $term ) {
			if ( $links_enabled ) {
				$term_link = get_term_link( $term, $taxonomy_type );
				if ( ! is_wp_error( $term_link ) ) {
					$terms_list[] = '<a href="' . esc_url( $term_link ) . '">' . esc_html( $term->name ) . '</a>';
				} else {
					$terms_list[] = esc_html( $term->name );
				}
			} else {
				$terms_list[] = esc_html( $term->name );
			}
		}

		return implode( $separator, $terms_list );
	}

	/**
	 * Apply escaping to value.
	 *
	 * @since ??
	 *
	 * @param mixed  $value       Value to escape.
	 * @param string $escape_type Escape function name.
	 *
	 * @return string Escaped value.
	 */
	private static function _escape_value( $value, string $escape_type ): string {
		switch ( $escape_type ) {
			case 'esc_html':
				return esc_html( $value );

			case 'esc_url':
				return esc_url( $value );

			case 'wp_kses_post':
				return wp_kses_post( $value );

			case 'absint':
				return (string) absint( $value );

			default:
				// Log unexpected escape type for debugging.
				// error_log( "Unexpected escape type in WooCommerceLoopHandler: {$escape_type}" );.
				return esc_html( $value ); // Safe default instead of no escaping.
		}
	}
}
