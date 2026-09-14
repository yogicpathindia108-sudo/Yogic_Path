<?php
/**
 * WooCommerce Product Variation Placeholder for Theme Builder in Divi 5.
 *
 * @package ET\Builder\ThemeBuilder\WooCommerce
 * @since ??
 */

namespace ET\Builder\ThemeBuilder\WooCommerce;

use WC_Product_Variation;

/**
 * WooCommerce Product Variation Placeholder class.
 *
 * Display variation (child of variable) placeholder product on Theme Builder. This needs to be
 * explicitly defined in case WC add-ons relies on any of variation's method.
 *
 * @since ??
 */
class WooCommerceProductVariationPlaceholder extends WC_Product_Variation {

	/**
	 * Get internal type.
	 *
	 * Define custom internal type so custom data store can be used to bypass database value retrieval.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	public function get_type() {
		return 'tb-placeholder-variation';
	}
}
