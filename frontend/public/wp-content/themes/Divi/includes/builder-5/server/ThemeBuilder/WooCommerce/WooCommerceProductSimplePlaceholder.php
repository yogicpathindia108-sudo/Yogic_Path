<?php
/**
 * WooCommerce Simple Product Placeholder for Theme Builder in Divi 5.
 *
 * @since ??
 * @package Divi
 */

namespace ET\Builder\ThemeBuilder\WooCommerce;

use WC_Product;
use WC_Product_Simple;
use ET\Builder\Packages\WooCommerce\WooCommerceUtils;

/**
 * Class WooCommerceProductSimplePlaceholder
 *
 * @since ??
 */
class WooCommerceProductSimplePlaceholder extends WC_Product_Simple {
	/**
	 * Create a pre-filled WC Product (simple) object which acts as a placeholder generator in TB
	 *
	 * Instead of an empty product object that is set later, pre-filled default data properties.
	 *
	 * @since ??
	 *
	 * @param int|WC_Product|object $product Product to init.
	 */
	public function __construct( $product = 0 ) {
		/*
		 * Pre-filled default data with placeholder value, so everytime this product class is
		 * initialized, it already has enough data to be displayed on Theme Builder.
		 */
		$this->data = [
			'name'               => esc_html__( 'Product 1', 'et_builder_5' ),
			'slug'               => 'product-1',
			'date_created'       => current_time( 'timestamp' ), // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Timestamp is used only for reference.
			'date_modified'      => null,
			'status'             => 'publish',
			'featured'           => false,
			'catalog_visibility' => 'visible',
			'description'        => esc_html__( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris bibendum eget dui sed vehicula. Suspendisse potenti. Nam dignissim at elit non lobortis. Cras sagittis dui diam, a finibus nibh euismod vestibulum. Integer sed blandit felis. Maecenas commodo ante in mi ultricies euismod. Morbi condimentum interdum luctus. Mauris iaculis interdum risus in volutpat. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Praesent cursus odio eget cursus pharetra. Aliquam lacinia lectus a nibh ullamcorper maximus. Quisque at sapien pulvinar, dictum elit a, bibendum massa. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Mauris non pellentesque urna.', 'et_builder_5' ),
			'short_description'  => esc_html__( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris bibendum eget dui sed vehicula. Suspendisse potenti. Nam dignissim at elit non lobortis.', 'et_builder_5' ),
			'sku'                => 'product-name',
			'price'              => '12',
			'date_on_sale_from'  => null,
			'date_on_sale_to'    => null,
			'total_sales'        => '0',
			'tax_status'         => 'taxable',
			'tax_class'          => '',
			'manage_stock'       => true,
			'stock_quantity'     => 50,
			'stock_status'       => 'instock',
			'backorders'         => 'no',
			'low_stock_amount'   => 2,
			'sold_individually'  => false,
			'weight'             => 2,
			'length'             => '',
			'width'              => 2,
			'height'             => 2,
			'upsell_ids'         => [],
			'cross_sell_ids'     => [],
			'parent_id'          => 0,
			'reviews_allowed'    => true,
			'purchase_note'      => '',
			'attributes'         => [],
			'default_attributes' => [],
			'menu_order'         => 0,
			'post_password'      => '',
			'virtual'            => false,
			'downloadable'       => false,
			'category_ids'       => [],
			'tag_ids'            => [],
			'shipping_class_id'  => 0,
			'downloads'          => [],
			'image_id'           => '',
			'gallery_image_ids'  => [],
			'download_limit'     => -1,
			'download_expiry'    => -1,
			'rating_counts'      => [
				4 => 2,
			],
			'average_rating'     => '4.00',
			'review_count'       => 2,
			'recent_product_ids' => null,
		];

		parent::__construct( $product );
	}

	/**
	 * Display Divi's placeholder image in WC image in TB.
	 *
	 * @since ??
	 *
	 * @param string $size        Image size (not used but need to be declared to prevent incompatible declaration error).
	 * @param array  $attr        Image attributes (not used but need to be declared to prevent incompatible declaration error).
	 * @param bool   $placeholder Whether to use placeholder (not used but need to be declared to prevent incompatible declaration error).
	 *
	 * @return string
	 */
	public function get_image( $size = 'woocommerce_thumbnail', $attr = [], $placeholder = true ): string {
		return WooCommerceUtils::get_placeholder_img();
	}
}
