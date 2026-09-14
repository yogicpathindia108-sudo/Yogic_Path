<?php
/**
 * Module: Conditions Option.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Options\Conditions;

use DateTimeImmutable;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Conditions option.
 * This class is responsible for checking module's conditions option and decides if a module should be displayed or not.
 *
 * @since ??
 */
class Conditions {

	/**
	 * Custom current date to use for testing purposes.
	 *
	 * @var DateTimeImmutable
	 */
	protected $_custom_current_date;

	/**
	 * Whether the main query is a singular content request (not an archive, search, or blog index).
	 *
	 * Theme Builder layout rendering can spoof the global post, so callers must not infer
	 * singular context from `ET_Post_Stack::get_main_post_id()` alone on listing queries.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private function _is_main_query_singular_content(): bool {
		global $wp_query;

		if ( ! $wp_query instanceof \WP_Query ) {
			return false;
		}

		if ( $wp_query->is_archive || $wp_query->is_search ) {
			return false;
		}

		if ( $wp_query->is_home && ! $wp_query->is_front_page ) {
			return false;
		}

		return (bool) $wp_query->is_singular;
	}

	/**
	 * Whether conditions should treat the current request as singular content.
	 *
	 * During Theme Builder layout rendering `is_singular()` can be false even when
	 * the main query is a singular page/post.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private function _is_singular_content_context(): bool {
		$is_on_shop_page = class_exists( 'WooCommerce' ) && is_shop();

		if ( $is_on_shop_page ) {
			return true;
		}

		if ( is_singular() ) {
			return true;
		}

		if ( class_exists( '\ET_Theme_Builder_Layout' )
			&& \ET_Theme_Builder_Layout::is_theme_builder_layout()
			&& class_exists( '\ET_Post_Stack' )
			&& $this->_is_main_query_singular_content() ) {
			$main_post_id = (int) \ET_Post_Stack::get_main_post_id();

			if ( $main_post_id > 0 ) {
				$post_type = get_post_type( $main_post_id );

				return is_string( $post_type )
					&& function_exists( 'et_theme_builder_is_layout_post_type' )
					&& ! et_theme_builder_is_layout_post_type( $post_type );
			}
		}

		return false;
	}

	/**
	 * Resolve the post ID conditions should evaluate against.
	 *
	 * In Theme Builder layout rendering the post stack is spoofed to the layout post.
	 * Conditions must evaluate against the viewed page/post from the main query.
	 *
	 * @since ??
	 *
	 * @return int Post ID for singular-content condition evaluation, or 0 if unavailable.
	 */
	private function _get_condition_evaluation_post_id(): int {
		$is_on_shop_page = class_exists( 'WooCommerce' ) && is_shop();

		if ( $is_on_shop_page ) {
			return (int) wc_get_page_id( 'shop' );
		}

		if ( class_exists( '\ET_Theme_Builder_Layout' )
			&& \ET_Theme_Builder_Layout::is_theme_builder_layout()
			&& class_exists( '\ET_Post_Stack' )
			&& $this->_is_main_query_singular_content() ) {
			$main_post_id = (int) \ET_Post_Stack::get_main_post_id();

			if ( $main_post_id > 0 ) {
				$post_type = get_post_type( $main_post_id );

				if ( is_string( $post_type )
					&& function_exists( 'et_theme_builder_is_layout_post_type' )
					&& ! et_theme_builder_is_layout_post_type( $post_type ) ) {
					return $main_post_id;
				}
			}
		}

		return (int) get_queried_object_id();
	}

	/**
	 * Resolve tag selections from D5 `tags` or legacy D4 `categories` key.
	 *
	 * Legacy D4 / TB-imported payloads store tag selections under `categories`
	 * with the same item shape as `tags`.
	 *
	 * @since ??
	 *
	 * @param array $condition_settings Condition settings array.
	 *
	 * @return array Tag items array.
	 */
	private function _resolve_tags_condition_items( array $condition_settings ): array {
		if ( isset( $condition_settings['tags'] ) && is_array( $condition_settings['tags'] ) ) {
			return $condition_settings['tags'];
		}

		if ( isset( $condition_settings['categories'] ) && is_array( $condition_settings['categories'] ) ) {
			return $condition_settings['categories'];
		}

		return [];
	}

	/**
	 * Process the author condition for displaying content.
	 *
	 * This function checks if the current page is a singular post and if
	 * the author(s) specified in the condition settings match the author of the post.
	 *
	 * Note: This check is only applied on singular posts, if `is_singular()` is false, `false` is returned.
	 *
	 * @since ??
	 *
	 * @param array $condition_settings {
	 *     An array of condition settings.
	 *
	 *     @type string    $displayRule   Optional. The display rule. One of `'is'`, or `'is_not'`. Default `is`.
	 *     @type int[]     $authors       Optional. An array of author IDs. Default `[]`.
	 * }
	 *
	 * @return bool Whether to display the content based on the author condition.
	 *
	 * @example:
	 * ```php
	 *     // Default case where the current page is not a singular post.
	 *     $condition_settings = [
	 *         'displayRule' => 'is',
	 *         'authors' => [
	 *             [
	 *                 'label' => 'Author 1',
	 *                 'value' => '1',
	 *             ],
	 *             [
	 *                 'label' => 'Author 2',
	 *                 'value' => '2',
	 *             ],
	 *         ],
	 *     ];
	 *     $result = $this->_process_author_condition($condition_settings);
	 *     // $result will be false
	 *
	 *     // Case where the current page is a singular post and the post author matches the specified author IDs.
	 *     $condition_settings = [
	 *         'displayRule' => 'is_not',
	 *         'authors' => [
	 *             [
	 *                 'label' => 'Author 4',
	 *                 'value' => '4',
	 *             ],
	 *             [
	 *                 'label' => 'Author 5',
	 *                 'value' => '5',
	 *             ],
	 *         ],
	 *     ];
	 *     $result = $this->_process_author_condition($condition_settings);
	 *     // $result will be false
	 *
	 *     // Case where the current page is a singular post and the post author does not match the specified author IDs.
	 *     $condition_settings = [
	 *         'displayRule' => 'is',
	 *         'authors' => [
	 *             [
	 *                 'label' => 'Author 4',
	 *                 'value' => '4',
	 *             ],
	 *             [
	 *                 'label' => 'Author 5',
	 *                 'value' => '5',
	 *             ],
	 *         ],
	 *     ];
	 *     $result = $this->_process_author_condition($condition_settings);
	 *     // $result will be true
	 *
	 *     // Case where the current page is a singular post and the post author matches one of the specified author IDs.
	 *     $condition_settings = [
	 *         'displayRule' => 'is_not',
	 *         'authors' => [
	 *             [
	 *                 'label' => 'Author 1',
	 *                 'value' => '1',
	 *             ],
	 *             [
	 *                 'label' => 'Author 2',
	 *                 'value' => '2',
	 *             ],
	 *         ],
	 *     ];
	 *     $result = $this->_process_author_condition($condition_settings);
	 *     // $result will be true
	 * ```
	 */
	protected function _process_author_condition( array $condition_settings ): bool {
		// Only check for Posts.
		if ( ! $this->_is_singular_content_context() ) {
			return false;
		}

		$display_rule           = $condition_settings['displayRule'] ?? 'is';
		$authors_raw            = $condition_settings['authors'] ?? [];
		$authors_ids            = array_column( $authors_raw, 'value' );
		$queried_object_id      = $this->_get_condition_evaluation_post_id();
		$current_post_author_id = get_post_field( 'post_author', (int) $queried_object_id );

		$should_display = array_intersect( $authors_ids, (array) $current_post_author_id ) ? true : false;

		return ( 'is' === $display_rule ) ? $should_display : ! $should_display;
	}

	/**
	 * Processes "Browser" condition.
	 *
	 * @since ??
	 *
	 * @param  array $condition_settings Containing all settings of the condition.
	 *
	 * @return boolean Condition output.
	 */
	protected function _process_browser_condition( $condition_settings ) {

		$display_rule = $condition_settings['displayRule'] ?? 'is';
		$browsers     = $condition_settings['browsers'] ?? [];

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- User Agent is not stored or displayed therefore XSS safe.
		$useragent              = ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
		$is_old_edge            = preg_match( '/edge\//i', $useragent );
		$is_checking_for_chrome = array_search( 'chrome', $browsers, true ) !== false;
		$current_browser        = $this->_get_browser( $useragent );

		// Exception: When checking "Chrome" condition we should treat New Edge as Chrome.
		if ( 'edge' === $current_browser && ! $is_old_edge && $is_checking_for_chrome ) {
			$current_browser = 'chrome';
		}

		// Alter the value `maxthon` into `chrome` to make it compatible with the browser detection.
		array_walk(
			$browsers,
			function ( &$value ) {
				if ( 'maxthon' === $value ) {
					$value = 'chrome';
				}
			}
		);

		$should_display = in_array( $current_browser, $browsers, true );
		return ( 'is' === $display_rule ) ? $should_display : ! $should_display;
	}

	/**
	 * Returns the Browser name based on user agent.
	 *
	 * @since ??
	 *
	 * @param  string $useragent The useragent of the berowser.
	 *
	 * @return string Detected browser.
	 */
	protected function _get_browser( $useragent ) {
		$browser       = 'unknown';
		$browser_array = [
			'/safari/i'           => 'safari',
			'/chrome|CriOS/i'     => 'chrome',
			'/firefox|FxiOS/i'    => 'firefox',
			'/msie|Trident/i'     => 'ie',
			'/edg/i'              => 'edge',
			'/opr|Opera|Presto/i' => 'opera',
			'/ucbrowser/i'        => 'ucbrowser',
		];

		foreach ( $browser_array as $regex => $value ) {
			if ( preg_match( $regex, $useragent ) ) {
				$browser = $value;
			}
		}

		return $browser;
	}

	/**
	 * Processes "Categories" condition.
	 *
	 * @since ??
	 *
	 * @param  array $condition_settings Containing all condition settings.
	 *
	 * @return boolean Condition output.
	 */
	protected function _process_categories_condition( $condition_settings ) {

		if ( ! $this->_is_singular_content_context() ) {
			return false;
		}

		$display_rule   = isset( $condition_settings['displayRule'] ) ? $condition_settings['displayRule'] : 'is';
		$categories_raw = isset( $condition_settings['categories'] ) ? $condition_settings['categories'] : [];

		$categories = array_map(
			function ( $item ) {
				return (object) [
					'id'            => $item['value'],
					'taxonomy_slug' => $item['groupSlug'],
				];
			},
			$categories_raw
		);

		$current_queried_id           = $this->_get_condition_evaluation_post_id();
		$has_post_specified_term      = false;
		$tax_slugs_of_catch_all_items = [];
		$is_any_catch_all_selected    = false;
		$has_post_specified_taxonomy  = false;

		// Logic evaluation.
		foreach ( $categories_raw as $item ) {
			if ( isset( $item['isCatchAll'] ) && true === $item['isCatchAll'] ) {
				$tax_slugs_of_catch_all_items[] = $item['groupSlug'];
				$is_any_catch_all_selected      = true;
			}
		}

		foreach ( $categories as $cat ) {
			if ( is_object( $cat ) && has_term( $cat->id, $cat->taxonomy_slug, $current_queried_id ) ) {
				$has_post_specified_term = true;
				break;
			}
		}

		$is_displayable = $has_post_specified_term ? true : false;

		if ( ! $is_displayable && $is_any_catch_all_selected ) {
			foreach ( $tax_slugs_of_catch_all_items as $tax_slug ) {
				$has_post_specified_taxonomy = has_term( '', $tax_slug, $current_queried_id );
				if ( $has_post_specified_taxonomy ) {
					break;
				}
			}

			$is_displayable = $has_post_specified_taxonomy ? true : false;
		}

		// Evaluation output.
		return ( 'is' === $display_rule ) ? $is_displayable : ! $is_displayable;
	}

	/**
	 * Processes "CategoryPage" condition.
	 *
	 * @since ??
	 *
	 * @param  array $condition_settings Containing all condition settings.
	 *
	 * @return boolean Condition output.
	 */
	protected function _process_category_page_condition( $condition_settings ) {

		// Only check for Archive pages.
		if ( ! is_archive() ) {
			return false;
		}

		$display_rule            = isset( $condition_settings['displayRule'] ) ? $condition_settings['displayRule'] : 'is';
		$categories_raw          = isset( $condition_settings['categories'] ) ? $condition_settings['categories'] : [];
		$queried_object          = get_queried_object();
		$is_queried_object_valid = $queried_object instanceof \WP_Term && property_exists( $queried_object, 'taxonomy' );

		if ( ! $is_queried_object_valid ) {
			return false;
		}

		$queried_taxonomy = $queried_object->taxonomy;
		$categories_ids   = array_map(
			function ( $item ) {
				return $item['value'];
			},
			$categories_raw
		);

		$tax_slugs_of_catch_all_items = [];
		$is_any_catch_all_selected    = false;
		foreach ( $categories_raw as $item ) {
			if ( isset( $item['isCatchAll'] ) && true === $item['isCatchAll'] ) {
				$tax_slugs_of_catch_all_items[] = $item['groupSlug'];
				$is_any_catch_all_selected      = true;
			}
		}

		// Logic evaluation.
		$current_category_id = get_queried_object_id();
		$is_displayable      = array_intersect( $categories_ids, (array) $current_category_id ) ? true : false;

		if ( ! $is_displayable && $is_any_catch_all_selected ) {
			$is_displayable = array_intersect( $tax_slugs_of_catch_all_items, (array) $queried_taxonomy ) ? true : false;
		}

		// Evaluation output.
		return ( 'is' === $display_rule ) ? $is_displayable : ! $is_displayable;
	}

	/**
	 * Process the cookie condition.
	 *
	 * This function takes the condition settings for cookies and determines
	 * if module should be displayed based on those settings.
	 *
	 * @since ??
	 *
	 * @param array $condition_settings {
	 *     Array of condition settings for cookies.
	 *
	 *     @type string $displayRule Optional. The display rule for cookies.
	 *     @type string  $cookieName   The cookies to check against. Defaults empty string `''`.
	 *     @type string $cookieValue     Optional. The cookie value to compare with. Defaults empty string `''`.
	 * }
	 *
	 * @return bool Whether or not the module should be displayed based on the condition settings.
	 *
	 * @example
	 * ```php
	 *     $condition_settings = [
	 *         'displayRule' => 'cookieExists',
	 *         'cookieName' => 'my_cookie',
	 *         'cookieValue' => 'my_cookie_value'
	 *     ];
	 *
	 *     $should_display = $this->_process_cookie_condition( $condition_settings );
	 *
	 *     if ( $should_display ) {
	 *         // Display cookie-related content.
	 *     } else {
	 *         // Do not display cookie-related content.
	 *     }
	 * ```
	 */
	protected function _process_cookie_condition( array $condition_settings ): bool {
		$display_rule           = $condition_settings['displayRule'] ?? 'cookieExists';
		$cookie_name            = $condition_settings['cookieName'] ?? '';
		$cookie_value           = $condition_settings['cookieValue'] ?? '';
		$is_cookie_set          = ( isset( $_COOKIE[ $cookie_name ] ) ) ? true : false;
		$is_cookie_value_equals = ( isset( $_COOKIE[ $cookie_name ] ) ) ? $cookie_value === $_COOKIE[ $cookie_name ] : false;

		switch ( $display_rule ) {
			case 'cookieExists':
				return $is_cookie_set;

			case 'cookieDoesNotExist':
				return ! $is_cookie_set;

			case 'cookieValueEquals':
				return $is_cookie_value_equals;

			case 'cookieValueDoesNotEqual':
				return ! $is_cookie_value_equals;

			default:
				return false;
		}
	}

	/**
	 * Processes "Product Purchase" display condition (WooCommerce).
	 *
	 * @since ??
	 *
	 * @param array $condition_settings Condition settings payload.
	 *
	 * @return bool
	 */
	protected function _process_product_purchase_condition( array $condition_settings ): bool {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}

		$order_received_order = $this->_get_valid_order_received_order_for_product_purchase();
		$logged_in            = is_user_logged_in();
		$current_user         = wp_get_current_user();

		// Guests have no customer_id for wc_get_orders / wc_customer_bought_product unless we use the
		// order-received page (thank you) where the order key proves access to that purchase.
		if ( ! $logged_in && ! $order_received_order ) {
			return false;
		}

		$legacy_display_rule = isset( $condition_settings['productPurchaseDisplay'] ) ? $condition_settings['productPurchaseDisplay'] : 'hasBoughtProduct';
		$display_rule        = isset( $condition_settings['displayRule'] ) ? $condition_settings['displayRule'] : $legacy_display_rule;
		$products_raw        = isset( $condition_settings['products'] ) ? $condition_settings['products'] : [];
		$products_ids        = array_values(
			array_filter(
				array_map(
					function ( $item ) {
						return isset( $item['value'] ) ? $item['value'] : '';
					},
					$products_raw
				)
			)
		);

		switch ( $display_rule ) {
			case 'hasBoughtProduct':
				if ( $order_received_order && $this->_wc_order_is_paid_for_product_purchase_condition( $order_received_order ) ) {
					return true;
				}
				if ( $logged_in ) {
					return $this->_has_user_bought_any_product( (int) $current_user->ID );
				}
				return false;

			case 'hasNotBoughtProduct':
				if ( $order_received_order && $this->_wc_order_is_paid_for_product_purchase_condition( $order_received_order ) ) {
					return false;
				}
				if ( $logged_in ) {
					return ! $this->_has_user_bought_any_product( (int) $current_user->ID );
				}
				return false;

			case 'hasBoughtSpecificProduct':
				if ( count( $products_ids ) === 0 ) {
					return false;
				}
				if ( $order_received_order && $this->_wc_order_is_paid_for_product_purchase_condition( $order_received_order ) ) {
					return $this->_order_contains_any_product_ids_for_product_purchase( $order_received_order, $products_ids );
				}
				if ( $logged_in ) {
					return $this->_has_user_bought_specific_product( $current_user, $products_ids );
				}
				return false;

			case 'hasNotBoughtSpecificProduct':
				if ( count( $products_ids ) === 0 ) {
					return false;
				}
				if ( $order_received_order && $this->_wc_order_is_paid_for_product_purchase_condition( $order_received_order ) ) {
					return ! $this->_order_contains_any_product_ids_for_product_purchase( $order_received_order, $products_ids );
				}
				if ( $logged_in ) {
					return ! $this->_has_user_bought_specific_product( $current_user, $products_ids );
				}
				return false;

			default:
				return false;
		}
	}

	/**
	 * Whether the user has bought any of the given product IDs.
	 *
	 * @since ??
	 *
	 * @param \WP_User $current_user Current user.
	 * @param array    $products_ids Product or variation IDs.
	 *
	 * @return bool
	 */
	protected function _has_user_bought_specific_product( $current_user, array $products_ids ): bool {
		$has_bought_specific_product = false;

		foreach ( $products_ids as $product_id ) {
			$has_bought_specific_product = wc_customer_bought_product( $current_user->user_email, $current_user->ID, $product_id );
			if ( $has_bought_specific_product ) {
				break;
			}
		}

		return $has_bought_specific_product;
	}

	/**
	 * Whether the user has any paid WooCommerce order.
	 *
	 * @since ??
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool
	 */
	protected function _has_user_bought_any_product( int $user_id ): bool {
		if ( ! class_exists( 'WooCommerce' ) || ! $user_id || ! is_numeric( $user_id ) ) {
			return false;
		}

		$paid_statuses = wc_get_is_paid_statuses();

		$orders = wc_get_orders(
			[
				'limit'       => 1,
				'status'      => $paid_statuses,
				'customer_id' => (int) $user_id,
				'return'      => 'ids',
			]
		);

		return count( $orders ) > 0 ? true : false;
	}

	/**
	 * Loads the current order on the order-received (thank you) page when the URL order key is valid.
	 *
	 * Used so guest checkouts can satisfy Product Purchase conditions immediately after purchase.
	 *
	 * @since ??
	 *
	 * @return \WC_Order|null
	 */
	protected function _get_valid_order_received_order_for_product_purchase() {
		if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
			return null;
		}

		$order_id = 0;
		if ( function_exists( 'wc_get_order_id_from_url' ) ) {
			$order_id = absint( wc_get_order_id_from_url() );
		} else {
			$order_id = absint( get_query_var( 'order-received' ) );
		}

		if ( ! $order_id ) {
			return null;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return null;
		}

		$order_key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WooCommerce thank-you URL order key, not a WP nonce; validated by WC_Order::key_is_valid().
		if ( empty( $order_key ) || ! $order->key_is_valid( $order_key ) ) {
			return null;
		}

		return $order;
	}

	/**
	 * Whether the order is in a paid state for display-condition purposes.
	 *
	 * @since ??
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return bool
	 */
	protected function _wc_order_is_paid_for_product_purchase_condition( \WC_Order $order ): bool {
		if ( method_exists( $order, 'is_paid' ) ) {
			return $order->is_paid();
		}

		return in_array( $order->get_status(), wc_get_is_paid_statuses(), true );
	}

	/**
	 * Whether the order contains any of the given product or variation IDs.
	 *
	 * @since ??
	 *
	 * @param \WC_Order $order        Order.
	 * @param array     $products_ids Product or variation IDs from condition settings.
	 *
	 * @return bool
	 */
	protected function _order_contains_any_product_ids_for_product_purchase( \WC_Order $order, array $products_ids ): bool {
		$ids_lookup = array_flip( array_map( 'strval', $products_ids ) );

		foreach ( $order->get_items() as $item ) {
			if ( ! is_object( $item ) || ! is_callable( [ $item, 'get_product_id' ] ) ) {
				continue;
			}

			$product_id   = (string) $item->get_product_id();
			$variation_id = (string) $item->get_variation_id();

			if ( isset( $ids_lookup[ $product_id ] ) ) {
				return true;
			}

			if ( '' !== $variation_id && isset( $ids_lookup[ $variation_id ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Processes "Cart Contents" display condition (WooCommerce).
	 *
	 * @since ??
	 *
	 * @param array $condition_settings Condition settings payload.
	 *
	 * @return bool
	 */
	protected function _process_cart_contents_condition( array $condition_settings ): bool {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}

		$cart = WC()->cart;
		if ( ! ( $cart instanceof \WC_Cart ) ) {
			return false;
		}

		$legacy_display_rule = isset( $condition_settings['cartContentsDisplay'] ) ? $condition_settings['cartContentsDisplay'] : 'hasProducts';
		$display_rule        = isset( $condition_settings['displayRule'] ) ? $condition_settings['displayRule'] : $legacy_display_rule;
		$products_raw        = isset( $condition_settings['products'] ) ? $condition_settings['products'] : [];
		$products_ids        = array_values(
			array_filter(
				array_map(
					function ( $item ) {
						return isset( $item['value'] ) ? $item['value'] : '';
					},
					$products_raw
				)
			)
		);
		$is_cart_empty       = $cart->is_empty();

		switch ( $display_rule ) {
			case 'hasProducts':
				return ! $is_cart_empty;

			case 'isEmpty':
				return $is_cart_empty;

			case 'hasSpecificProduct':
				if ( count( $products_ids ) === 0 ) {
					return false;
				}
				return $this->_has_specific_product_in_cart( $cart, $products_ids );

			case 'doesNotHaveSpecificProduct':
				if ( count( $products_ids ) === 0 ) {
					return false;
				}
				return ! $this->_has_specific_product_in_cart( $cart, $products_ids );

			default:
				return false;
		}
	}

	/**
	 * Whether the cart contains any of the given product or variation IDs.
	 *
	 * @since ??
	 *
	 * @param \WC_Cart $cart          WooCommerce cart instance.
	 * @param array    $products_ids Product IDs.
	 *
	 * @return bool
	 */
	protected function _has_specific_product_in_cart( \WC_Cart $cart, array $products_ids ): bool {
		$has_specific_product = false;

		if ( ! $cart->is_empty() ) {
			foreach ( $cart->get_cart() as $cart_item ) {
				$cart_item_ids = [ $cart_item['product_id'], $cart_item['variation_id'] ];
				if ( array_intersect( $products_ids, $cart_item_ids ) ) {
					$has_specific_product = true;
					break;
				}
			}
		}

		return $has_specific_product;
	}

	/**
	 * Processes "Product Stock" display condition (WooCommerce).
	 *
	 * @since ??
	 *
	 * @param array $condition_settings Condition settings payload.
	 *
	 * @return bool
	 */
	protected function _process_product_stock_condition( array $condition_settings ): bool {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}

		$display_rule = isset( $condition_settings['displayRule'] ) ? $condition_settings['displayRule'] : 'isInStock';
		$products_raw = isset( $condition_settings['products'] ) ? $condition_settings['products'] : [];
		$products_ids = array_map(
			function ( $item ) {
				return isset( $item['value'] ) ? (int) $item['value'] : 0;
			},
			$products_raw
		);
		$products_ids = array_values( array_filter( $products_ids ) );

		// Empty `include` would make wc_get_products match all products (unbounded query).
		if ( count( $products_ids ) === 0 ) {
			return false;
		}

		// One matching product is enough: non-empty means at least one selected ID is in stock; empty means none are.
		$products = wc_get_products(
			[
				'limit'        => 1,
				'include'      => $products_ids,
				'stock_status' => 'instock',
				'return'       => 'ids',
			]
		);

		$output = [
			'isInStock'    => count( $products ) > 0,
			'isOutOfStock' => count( $products ) === 0,
		];

		return isset( $output[ $display_rule ] ) ? $output[ $display_rule ] : false;
	}

	/**
	 * Evaluates "Custom Field" condition.
	 *
	 * This function takes the condition settings for custom field condition and determines
	 * if the module should be displayed based on those settings.
	 *
	 * On singular pages the custom field is read from post meta. On taxonomy archive pages
	 * (category, tag, custom taxonomy) the custom field is read from term meta instead,
	 * allowing ACF and other plugins that attach fields to terms to work correctly.
	 *
	 * @since ??
	 *
	 * @param array $condition_settings {
	 *     Array of condition settings for custom field.
	 *
	 *     @type string $displayRule The display rule for custom fields.
	 *     @type string $selectedFieldName selected custom field name.
	 *     @type string $customFieldName custom field name.
	 *     @type string $selectedFieldValue selected custom field value.
	 *     @type string $customFieldValue custom field value.
	 * }
	 *
	 * @return boolean Returns `true` if the condition evaluation is true, `false` otherwise.
	 *
	 * @example
	 * ```php
	 *     $condition_settings = [
	 *        'displayRule' => 'is',
	 *        'selectedFieldName' => 'manualCustomFieldName',
	 *        'customFieldName' => 'custom_field_name',
	 *        'selectedFieldValue' => 'manualCustomFieldValue',
	 *        'customFieldValue' => 'custom_field_value',
	 *     ];
	 *
	 *     $should_display = $this->_process_custom_field_condition( $condition_settings );
	 *
	 *     if ( $should_display ) {
	 *        // Display user-related content.
	 *     } else {
	 *       // Do not display user-related content.
	 *     }
	 * ```
	 */
	protected function _process_custom_field_condition( array $condition_settings ): bool {
		$queried_object    = get_queried_object();
		$queried_object_id = get_queried_object_id();

		// Determine whether we are on a singular post/page or a taxonomy archive page,
		// and set the meta type accordingly so the correct meta API is used.
		if ( $this->_is_singular_content_context() ) {
			$queried_object_id = $this->_get_condition_evaluation_post_id();
			$queried_object    = get_post( $queried_object_id );
			$meta_type         = 'post';
		} elseif ( is_tax() || is_category() || is_tag() ) {
			// On taxonomy archive pages the queried object is a WP_Term.
			if ( ! $queried_object instanceof \WP_Term ) {
				return false;
			}
			$meta_type = 'term';
		} else {
			return false;
		}

		if ( 'post' === $meta_type && ! $queried_object instanceof \WP_Post ) {
			return false;
		}

		// Gets custom fields settings.
		$display_rule         = $condition_settings['displayRule'] ?? 'is';
		$selected_field_name  = $condition_settings['selectedFieldName'] ?? 'manualCustomFieldName';
		$custom_field_name    = $condition_settings['customFieldName'] ?? '';
		$selected_field_value = $condition_settings['selectedFieldValue'] ?? 'manualCustomFieldValue';
		$custom_field_value   = $condition_settings['customFieldValue'] ?? '';

		$field_name             = 'manualCustomFieldName' === $selected_field_name ? $custom_field_name : $selected_field_name;
		$has_custom_field_value = 'manualCustomFieldValue' === $selected_field_value ? true : false;

		// Checks whether the specified custom fields actually exist.
		$has_field_name_metadata           = metadata_exists( $meta_type, $queried_object_id, $field_name );
		$has_selected_field_value_metadata = $has_custom_field_value ? true : metadata_exists( $meta_type, $queried_object_id, $selected_field_value );

		// Bailout if specified custom fields don't exist.
		if ( ! $has_field_name_metadata || ! $has_selected_field_value_metadata ) {
			return false;
		}

		if ( 'post' === $meta_type ) {
			$field_name_meta  = get_post_meta( $queried_object_id, $field_name, true );
			$field_value_meta = $has_custom_field_value ? (string) $custom_field_value : (string) get_post_meta( $queried_object_id, $selected_field_value, true );
		} else {
			$field_name_meta  = get_term_meta( $queried_object_id, $field_name, true );
			$field_value_meta = $has_custom_field_value ? (string) $custom_field_value : (string) get_term_meta( $queried_object_id, $selected_field_value, true );
		}

		// We want to ensure that custom field conditions work correctly with ACF checkboxes.
		// Since ACF checkboxes return arrays, we need to handle this specific case.
		if ( is_array( $field_name_meta ) ) {
			$output = [
				'is'             => in_array( $field_value_meta, $field_name_meta, true ),
				'isNot'          => ! in_array( $field_value_meta, $field_name_meta, true ),
				'contains'       => ! empty( array_filter( $field_name_meta, fn( $item ) => str_contains( $item, $field_value_meta ) ) ),
				'doesNotContain' => empty( array_filter( $field_name_meta, fn( $item ) => str_contains( $item, $field_value_meta ) ) ),
				'isAnyValue'     => count( $field_name_meta ) > 0,
				'hasNoValue'     => count( $field_name_meta ) === 0,
				'isGreaterThan'  => ! empty( array_filter( $field_name_meta, fn( $value ) => (float) $value > (float) $field_value_meta ) ),
				'isLessThan'     => ! empty( array_filter( $field_name_meta, fn( $value ) => (float) $value < (float) $field_value_meta ) ),
			];
		} else {
			$contains = ! empty( $field_value_meta ) && str_contains( (string) $field_name_meta, $field_value_meta );

			$output = [
				'is'             => $field_name_meta === $field_value_meta,
				'isNot'          => $field_name_meta !== $field_value_meta,
				'contains'       => false !== $contains,
				'doesNotContain' => false === $contains,
				'isAnyValue'     => strlen( $field_name_meta ) > 0,
				'hasNoValue'     => strlen( $field_name_meta ) === 0,
				'isGreaterThan'  => (float) $field_name_meta > (float) $field_value_meta,
				'isLessThan'     => (float) $field_name_meta < (float) $field_value_meta,
			];
		}

		return isset( $output[ $display_rule ] ) ? $output[ $display_rule ] : false;
	}

	/**
	 * Process the "Date Archive" condition for displaying content.
	 *
	 * This method checks the date archive with a target date range,
	 * and determines whether to display content based on the provided display rule.
	 *
	 * @since ??
	 *
	 * @param array $condition_settings {
	 *     An array of condition settings.
	 *
	 *     @type string    $displayRule   Optional. The display rule. Default 'is'.
	 *     @type string    $dateArchive   Optional. The date. Default empty string.
	 * }
	 *
	 * @return bool Whether to display the content based on the "Date" condition.
	 *
	 * @example:
	 * $condition_settings = [
	 *     'displayRule' => 'isAfter',
	 *     'dateArchive' => '2022-12-31',
	 *
	 * ];
	 * $should_display = $this->_process_date_archive_condition($condition_settings);
	 */
	protected function _process_date_archive_condition( array $condition_settings ): bool {
		if ( ! is_date() ) {
			return false;
		}

		$display_rule = $condition_settings['displayRule'] ?? 'isAfter';
		$date         = $condition_settings['dateArchive'] ?? '';

		$year         = get_query_var( 'year' );
		$monthnum_raw = get_query_var( 'monthnum' ) === 0 ? 1 : get_query_var( 'monthnum' );
		$day_raw      = get_query_var( 'day' ) === 0 ? 1 : get_query_var( 'day' );

		$monthnum = str_pad( $monthnum_raw, 2, '0', STR_PAD_LEFT ); // To add a leading zero if monthnum is less than 10.
		$day      = str_pad( $day_raw, 2, '0', STR_PAD_LEFT ); // To add a leading zero if day is less than 10.

		$archive_date = sprintf( '%s-%s-%s', $year, $monthnum, $day );

		$target_date  = new DateTimeImmutable( $date, wp_timezone() );
		$current_date = new DateTimeImmutable( $archive_date, wp_timezone() );

		switch ( $display_rule ) {
			case 'isAfter':
				return $current_date > $target_date;
			case 'isBefore':
				return $current_date < $target_date;
			default:
				return $current_date > $target_date;
		}
	}

	/**
	 * Process the "Date & Time" condition for displaying content.
	 *
	 * This method checks the current date and time against a target date and time,
	 * and determines whether to display content based on the provided display rule.
	 *
	 * @since ??
	 *
	 * @param array $condition_settings {
	 *     An array of condition settings.
	 *
	 *     @type string    $displayRule   Optional. The display rule. Default 'isAfter'.
	 *     @type string    $date          Optional. The date. Default empty string.
	 *     @type string    $time          Optional. The time. Default empty string.
	 *     @type string    $allDay        Optional. The allDay switch. Default empty string.
	 *     @type string    $fromTime      Optional. The fromTime. Default empty string.
	 *     @type string    $untilTime     Optional. The untilTime. Default empty string.
	 *     @type array     $weekdays      Optional. The weekdays. Default empty array.
	 *     @type string    $repeatFrequency Optional. The repeatFrequency. Default empty string.
	 *     @type string    $repeatFrequencySpecificDays Optional. The repeatFrequencySpecificDays. Default empty string.
	 *     @type string    $repeatEnd     Optional. The repeatEnd. Default empty string.
	 *     @type string    $repeatUntilDate Optional. The repeatUntilDate. Default empty string.
	 *     @type string    $repeatTimes   Optional. The repeatTimes. Default empty string.
	 * }
	 *
	 * @return bool Whether to display the content based on the "Date & Time" condition.
	 *
	 * @example:
	 * $condition_settings = [
	 * 'displayRule' => 'isAfter',
	 *     'date' => '2022-12-31',
	 *     'time' => '23:59:59',
	 *     'allDay' => 'off',
	 *     'fromTime' => '00:00:00',
	 *     'untilTime' => '23:59:59',
	 *     'weekdays' => ['monday', 'tuesday'],
	 *     'repeatFrequency' => 'monthly',
	 *     'repeatFrequencySpecificDays' => 'weekly',
	 *     'repeatEnd' => 'untilDate',
	 *     'repeatUntilDate' => '2023-12-31',
	 *     'repeatTimes' => '5',
	 * ];
	 *
	 *
	 * $should_display = $this->_process_date_time_condition($condition_settings);
	 */
	protected function _process_date_time_condition( array $condition_settings ): bool {
		$display_rule                   = $condition_settings['displayRule'] ?? 'isAfter';
		$date                           = $condition_settings['date'] ?? '';
		$time                           = $condition_settings['time'] ?? '';
		$all_day                        = $condition_settings['allDay'] ?? '';
		$from_time                      = $condition_settings['fromTime'] ?? '';
		$until_time                     = $condition_settings['untilTime'] ?? '';
		$weekdays                       = $condition_settings['weekdays'] ?? [];
		$repeat_frequency               = $condition_settings['repeatFrequency'] ?? '';
		$repeat_frequency_specific_days = $condition_settings['repeatFrequencySpecificDays'] ?? '';

		$date_from_time  = $date . ' ' . $from_time;
		$date_until_time = $date . ' ' . $until_time;
		$date_time       = $date . ' ' . $time;

		$target_date           = new DateTimeImmutable( $date, wp_timezone() );
		$target_datetime       = new DateTimeImmutable( $date_time, wp_timezone() );
		$target_from_datetime  = new DateTimeImmutable( $date_from_time, wp_timezone() );
		$target_until_datetime = new DateTimeImmutable( $date_until_time, wp_timezone() );

		$current_datetime       = ! empty( $this->_custom_current_date ) ? $this->_custom_current_date : current_datetime();
		$current_datetime_from  = ! empty( $from_time ) ? $current_datetime->modify( $from_time ) : $current_datetime;
		$current_datetime_until = ! empty( $until_time ) ? $current_datetime->modify( $until_time ) : $current_datetime;

		switch ( $display_rule ) {
			case 'isAfter':
				return $current_datetime > $target_datetime;

			case 'isBefore':
				return $current_datetime < $target_datetime;

			case 'isOnSpecificDate':
				$has_reached_target_datetime = $current_datetime >= $target_date;

				$has_time_until_tomorrow = $current_datetime < $target_date->modify( 'tomorrow' );
				if ( 'off' === $all_day ) {
					$has_reached_target_datetime = $current_datetime >= $target_from_datetime;
					$has_time_until_tomorrow     = $current_datetime < $target_until_datetime;
				}
				$is_on_specific_date = ( $has_reached_target_datetime && $has_time_until_tomorrow );
				$is_repeated         = $this->_is_datetime_condition_repeated( $condition_settings, $is_on_specific_date, $current_datetime, $target_datetime );
				return ( $is_on_specific_date || $is_repeated );

			case 'isNotOnSpecificDate':
				$has_reached_target_datetime = $current_datetime >= $target_date;
				$has_time_until_tomorrow     = $current_datetime < $target_date->modify( 'tomorrow' );
				if ( 'off' === $all_day ) {
					$has_reached_target_datetime = $current_datetime >= $target_from_datetime;
					$has_time_until_tomorrow     = $current_datetime < $target_until_datetime;
				}
				return ! ( $has_reached_target_datetime && $has_time_until_tomorrow );

			case 'isOnSpecificDays':
				$current_day                 = strtolower( $current_datetime->format( 'l' ) );
				$is_on_selected_day          = array_intersect( (array) $current_day, $weekdays ) ? true : false;
				$has_reached_target_datetime = true;
				$has_time_until_tomorrow     = true;
				if ( 'off' === $all_day ) {
					$has_reached_target_datetime = $current_datetime >= $current_datetime_from;
					$has_time_until_tomorrow     = $current_datetime < $current_datetime_until;
				}
				$is_repeated         = $this->_is_datetime_condition_repeated( $condition_settings, $is_on_selected_day, $current_datetime, $target_datetime );
				$is_on_specific_days = $is_on_selected_day && $has_reached_target_datetime && $has_time_until_tomorrow;
				return ( 'weekly' === $repeat_frequency_specific_days ) ? $is_on_specific_days : $is_repeated;

			case 'isFirstDayOfMonth':
				$is_first_day_of_month       = $current_datetime->format( 'd' ) === '01';
				$has_reached_target_datetime = true;
				$has_time_until_tomorrow     = true;
				if ( 'off' === $all_day ) {
					$has_reached_target_datetime = $current_datetime >= $current_datetime_from;
					$has_time_until_tomorrow     = $current_datetime < $current_datetime_until;
				}
				return ( $is_first_day_of_month && $has_reached_target_datetime && $has_time_until_tomorrow );

			case 'isLastDayOfMonth':
				$last_day_of_month           = new DateTimeImmutable( 'last day of this month', wp_timezone() );
				$is_last_day_of_month        = $current_datetime->format( 'd' ) === $last_day_of_month->format( 'd' );
				$has_reached_target_datetime = true;
				$has_time_until_tomorrow     = true;
				if ( 'off' === $all_day ) {
					$has_reached_target_datetime = $current_datetime >= $current_datetime_from;
					$has_time_until_tomorrow     = $current_datetime < $current_datetime_until;
				}
				return ( $is_last_day_of_month && $has_reached_target_datetime && $has_time_until_tomorrow );

			default:
				return $current_datetime >= $target_datetime;
		}
	}

	/**
	 * Checks whether a condition should be repeated or not.
	 *
	 * @since ??
	 *
	 * @param array             $condition_settings  Contains all settings of the condition.
	 * @param boolean           $is_on_specific_date Specifies if "Is On Specific Date" condition has already
	 *                                               reached that specific date or not.
	 *                                               Useful to avoid repetition checking if condition is already
	 *                                               true and also for "Every Other" repeat frequency.
	 * @param DateTimeImmutable $current_datetime    The current date and time to use.
	 * @param DateTimeImmutable $target_datetime     To detect Monthly/Annually repetition and "After Number of times".
	 *
	 * @return boolean          Condition repetition result.
	 */
	protected function _is_datetime_condition_repeated( $condition_settings, $is_on_specific_date, $current_datetime, $target_datetime ) {
		$display_rule                   = $condition_settings['displayRule'] ?? 'isAfter';
		$repeat                         = $condition_settings['repeat'] ?? '';
		$repeat_frequency               = $condition_settings['repeatFrequency'] ?? '';
		$repeat_frequency_specific_days = $condition_settings['repeatFrequencySpecificDays'] ?? '';
		$repeat_end                     = $condition_settings['repeatEnd'] ?? '';
		$repeat_until                   = $condition_settings['repeatUntilDate'] ?? '';
		$repeat_times                   = $condition_settings['repeatTimes'] ?? '';
		$all_day                        = $condition_settings['allDay'] ?? '';
		$from_time                      = $condition_settings['fromTime'] ?? '';
		$until_time                     = $condition_settings['untilTime'] ?? '';
		$is_repeated                    = false;
		$is_on_specific_days            = 'isOnSpecificDays' === $display_rule;

		if ( $is_on_specific_days || ( 'on' === $repeat && ! $is_on_specific_date ) ) {
			if ( $is_on_specific_days ) {
				$is_day_repeated = $this->_is_day_repeated( $repeat_frequency_specific_days, $is_on_specific_date, $current_datetime, $target_datetime );
			} else {
				$is_day_repeated = $this->_is_day_repeated( $repeat_frequency, $is_on_specific_date, $current_datetime, $target_datetime );
			}
			$is_repeat_valid = false;

			switch ( $repeat_end ) {
				case 'untilDate':
					$is_repeat_valid = $current_datetime <= new DateTimeImmutable( $repeat_until, wp_timezone() );
					break;

				case 'afterNumberOfTimes':
					$target_date_after_number_of_times = $target_datetime->modify( '+' . $repeat_times . ' month' );
					if ( 'annually' === $repeat_frequency ) {
						$target_date_after_number_of_times = $target_datetime->modify( '+' . $repeat_times . ' year' );
					}
					if ( 'off' === $all_day ) {
						$target_date_after_number_of_times = $target_date_after_number_of_times->modify( $until_time );
					}
					$is_repeat_valid = $current_datetime <= $target_date_after_number_of_times;
					break;

				case 'never':
					$is_repeat_valid = true;
					break;

				default:
					$is_repeat_valid = true;
					break;
			}

			// We assume "All Day" switch is "On".
			$has_reached_from_time  = $is_day_repeated;
			$has_reached_until_time = $current_datetime < $current_datetime->modify( 'tomorrow' );

			// Calculate from time/until time if "All Day" switch is "Off".
			if ( 'off' === $all_day ) {
				$has_reached_from_time  = $current_datetime >= $current_datetime->modify( $from_time );
				$has_reached_until_time = $current_datetime < $current_datetime->modify( $until_time );
			}

			$is_repeated = $is_day_repeated && $has_reached_from_time && $has_reached_until_time && $is_repeat_valid;
		}

		return $is_repeated;
	}

	/**
	 * Checks whether a day is repeated or not.
	 *
	 * @since ??
	 *
	 * @param string            $repeat_frequency    Frequency of repeat Ex. monthly, annually, everyOther...
	 * @param boolean           $is_on_specific_date Useful for "Every Other" repeat frequency.
	 * @param DateTimeImmutable $current_datetime    The current date and time to use.
	 * @param DateTimeImmutable $target_datetime     Checks monthly/annually repetition against this Date and Time.
	 *
	 * @return boolean          Day repetition result.
	 */
	protected function _is_day_repeated( $repeat_frequency, $is_on_specific_date, $current_datetime, $target_datetime ) {
		switch ( $repeat_frequency ) {
			case 'monthly':
				return ( $current_datetime->format( 'd' ) === $target_datetime->format( 'd' ) );

			case 'annually':
				return ( $current_datetime->format( 'm d' ) === $target_datetime->format( 'm d' ) );

			case 'everyOther':
				return ! $is_on_specific_date;

			case 'firstInstanceOfMonth':
				return ( $current_datetime->format( 'Y-m-d' ) === $current_datetime->modify( 'first ' . $current_datetime->format( 'l' ) . ' of this month' )->format( 'Y-m-d' ) );

			case 'lastInstanceOfMonth':
				return ( $current_datetime->format( 'Y-m-d' ) === $current_datetime->modify( 'last ' . $current_datetime->format( 'l' ) . ' of this month' )->format( 'Y-m-d' ) );

			default:
				return false;
		}
	}

	/**
	 * Overrides current date with specified date.
	 * Useful for testing purposes where we don't want to depend on server's timestamp.
	 *
	 * @since ??
	 *
	 * @param DateTimeImmutable $date The datetime which will overrides current datetime.
	 *
	 * @return void
	 */
	public function override_current_date( $date ) {
		$this->_custom_current_date = $date;
	}

	/**
	 * Processes "Dynamic Posts" condition.
	 *
	 * @since ??
	 *
	 * @param  array $condition_settings Containing all settings of the condition.
	 *
	 * @return boolean Whether or not the current module should be displayed based on the condition settings.
	 */
	protected function _process_dynamic_posts_condition( array $condition_settings ): bool {
		$display_rule    = $condition_settings['displayRule'] ?? 'is';
		$is_on_shop_page = class_exists( 'WooCommerce' ) && is_shop();

		// Non-singular URLs cannot match selected pages for "is"; "is not" must still pass on archives.
		// The WooCommerce shop page is non-singular but must resolve against the shop page ID.
		if ( ! $this->_is_singular_content_context() && ! $is_on_shop_page ) {
			return ( 'isNot' === $display_rule );
		}

		$dynamic_posts_raw = $condition_settings['dynamicPosts'] ?? [];
		$dynamic_posts_ids = array_column( $dynamic_posts_raw, 'value' );

		$current_page_id = $this->_get_condition_evaluation_post_id();

		$should_display = array_intersect( $dynamic_posts_ids, (array) $current_page_id ) ? true : false;

		return ( 'is' === $display_rule ) ? $should_display : ! $should_display;
	}

	/**
	 * Process the logged in status condition.
	 *
	 * Determines whether to display content based on the user's logged in status.
	 *
	 * @since ??
	 *
	 * @param array $condition_settings {
	 *     Array of condition settings.
	 *
	 *     @type string $displayRule The display rule for the condition. Default `'loggedIn'`.
	 * }
	 *
	 * @return bool Whether to display the content or not.
	 *
	 * @example:
	 * ```php
	 * // Example usage in a class method.
	 * $condition_settings = ['displayRule' => 'loggedIn'];
	 *
	 * $should_display = $this->_process_logged_in_status_condition( $condition_settings );
	 *
	 * if ( $should_display ) {
	 *     // Display the content for logged in users.
	 *     echo 'Welcome, logged in user!';
	 * } else {
	 *     // Display the content for logged out users.
	 *     echo 'Please login to see the content.';
	 * }
	 * ```
	 */
	protected function _process_logged_in_status_condition( array $condition_settings ): bool {
		$display_rule   = $condition_settings['displayRule'] ?? 'loggedIn';
		$should_display = ( is_user_logged_in() ) ? true : false;

		return ( 'loggedIn' === $display_rule ) ? $should_display : ! $should_display;
	}

	/**
	 * Process the Number Of Views condition.
	 *
	 * This function takes the condition settings for Number Of Views and determines
	 * if the content should be displayed based on those settings.
	 *
	 * @since ??
	 *
	 * @param string $condition_id ID of the condition.
	 * @param array  $condition_settings {
	 *     Array of condition settings for number of views roles.
	 *
	 *     @type string $numberOfViews                 The display rule for Number Of Views condition.
	 *     @type string $resetAfterDuration    Optional. Wether to reset the condition or not.
	 *     @type string $displayAgainAfter     Optional. If Reset the condition then after how much time.
	 *     @type string $displayAgainAfterUnit Optional. The Unit to reset. Options are 'days', 'hours', 'minutes',
	 * }
	 *
	 * @return bool Whether or not the content should be displayed based on the condition settings.
	 */
	protected function _process_number_of_views_condition( string $condition_id, array $condition_settings ): bool {
		if ( ! isset( $_COOKIE['divi_module_views'] ) ) {
			return true;
		}

		// Get condition's settings.
		$number_of_views  = isset( $condition_settings['numberOfViews'] ) ? $condition_settings['numberOfViews'] : '0';
		$cookie_array     = [];
		$visit_count      = 0;
		$current_datetime = current_datetime();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Cookie is not stored or displayed therefore XSS safe, The returned data is an array and necessary validation checks are performed.
		$cookie_array = json_decode( base64_decode( wp_unslash( $_COOKIE['divi_module_views'] ) ), true );

		if ( ! is_array( $cookie_array ) ) {
			return true;
		}

		// Logic evaluation.
		$col                        = array_column( $cookie_array, 'id' );
		$is_condition_set_in_cookie = array_search( $condition_id, $col, true ) !== false;

		if ( ! $is_condition_set_in_cookie ) {
			// Display module if condition is not set in Cookie yet.
			return true;
		}

		$is_reset_after_duration_on = 'on' === ( $condition_settings['resetAfterDuration'] ?? '' ) ? true : false;

		if ( $is_reset_after_duration_on ) {
			$first_visit_timestamp  = $cookie_array[ $condition_id ]['first_visit_timestamp'];
			$display_again_after    = $condition_settings['displayAgainAfter'] . ' ' . $condition_settings['displayAgainAfterUnit'];
			$first_visit_datetime   = $current_datetime->setTimestamp( $first_visit_timestamp );
			$display_again_datetime = $first_visit_datetime->modify( $display_again_after );
			if ( $current_datetime > $display_again_datetime ) {
				return true;
			}
		}

		$visit_count = $cookie_array[ $condition_id ]['visit_count'];

		if ( (int) $visit_count >= (int) $number_of_views ) {
			$is_displayable = false;
		} else {
			$is_displayable = true;
		}

		// Evaluation output.
		return $is_displayable;
	}

	/**
	 * Processes "Operating System" condition.
	 *
	 * @since ??
	 *
	 * @param  array $condition_settings Containing all settings of the condition.
	 *
	 * @return boolean Condition output.
	 */
	protected function _process_operating_system_condition( $condition_settings ) {

		$display_rule      = $condition_settings['displayRule'] ?? 'is';
		$operating_systems = $condition_settings['operatingSystems'] ?? [];
		$current_os        = $this->_get_operating_system();

		$should_display = in_array( $current_os, $operating_systems, true );

		return ( 'is' === $display_rule ) ? $should_display : ! $should_display;
	}

	/**
	 * Returns the Operating System name based on user agent.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	protected function _get_operating_system() {
		$os_platform = 'unknown';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- User Agent is not stored or displayed therefore XSS safe.
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
		$os_array   = [
			'/windows nt/i'         => 'windows',
			'/macintosh|mac os x/i' => 'macos',
			'/linux/i'              => 'linux',
			'/android/i'            => 'android',
			'/iphone/i'             => 'iphone',
			'/ipad/i'               => 'ipad',
			'/ipod/i'               => 'ipod',
			'/appletv/i'            => 'appletv',
			'/playstation/i'        => 'playstation',
			'/xbox/i'               => 'xbox',
			'/nintendo/i'           => 'nintendo',
			'/webos|hpwOS/i'        => 'webos',
		];

		foreach ( $os_array as $regex => $value ) {
			if ( preg_match( $regex, $user_agent ) ) {
				$os_platform = $value;
			}
		}
		return $os_platform;
	}

	/**
	 * Process the page visit or post visit condition.
	 *
	 * This function takes the condition settings for page visits and determines
	 * if the module should be displayed based on those settings.
	 *
	 * Page Visit and Post Visit conditions have the same condition settings structure.
	 * So we can use the same processing function for both.
	 *
	 * @since ??
	 *
	 * @param array $condition_settings {
	 *     Array of condition settings for page visit or post visit.
	 *
	 *     @type string $displayRule Optional. The display rule for page visit or post visit. One of `'hasVisitedSpecificPage'`, or `'hasNotVisitedSpecificPage'`. Default `hasVisitedSpecificPage`.
	 *     @type array  $pages   The Page IDs check against. Defaults `[]`.
	 * }
	 *
	 * @return bool Whether or not the current user should be displayed based on the condition settings.
	 *
	 * @example
	 * ```php
	 *     $condition_settings = [
	 *         'displayRule' => 'hasVisitedSpecificPage',
	 *         'pages' => [
	 *             [
	 *                 'label' => 'Sample Page',
	 *                 'value' => '2',
	 *             ],
	 *         ],
	 *     ];
	 *
	 *     $should_display = $this->_process_page_visit_condition( $condition_settings );
	 *
	 *     if ( $should_display ) {
	 *         // Display user-related content.
	 *     } else {
	 *         // Do not display user-related content.
	 *     }
	 * ```
	 */
	protected function _process_page_visit_condition( array $condition_settings ): bool {
		$display_rule              = $condition_settings['displayRule'] ?? 'hasVisitedSpecificPage';
		$pages_raw                 = $condition_settings['pages'] ?? [];
		$pages_ids                 = array_map(
			function ( $item ) {
				return isset( $item['value'] ) ? (int) $item['value'] : '';
			},
			$pages_raw
		);
		$has_visited_specific_page = false;
		$cookie                    = [];

		if ( isset( $_COOKIE['divi_post_visit'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Cookie is not stored or displayed therefore XSS safe, base64_decode returned data is an array and necessary validation checks are performed.
			$cookie = json_decode( base64_decode( wp_unslash( $_COOKIE['divi_post_visit'] ) ), true );
		}

		if ( $cookie && is_array( $cookie ) ) {
			$col                       = array_column( $cookie, 'id' );
			$has_visited_specific_page = array_intersect( $pages_ids, $col ) ? true : false;
		}

		$should_display = $has_visited_specific_page;

		return ( 'hasVisitedSpecificPage' === $display_rule ) ? $should_display : ! $should_display;
	}

	/**
	 * Process the post type condition.
	 *
	 * This function takes the condition settings for post types and determines
	 * if the current Post type should be displayed based on those settings.
	 *
	 * @since ??
	 *
	 * @param array $condition_settings {
	 *     Array of condition settings for post types.
	 *
	 *     @type string $displayRule Optional. The display rule for post types. One of `'is'`, or `'not'`. Default `is`.
	 *     @type array  $posTypes   Optional. The post types to check against. Defaults `[]`.
	 * }
	 *
	 * @return bool Whether or not the current post type should be displayed based on the condition settings.
	 *
	 * @example
	 * ```php
	 *     $condition_settings = [
	 *         'displayRule' => 'is',
	 *         'PostTypes' => [
	 *             [
	 *                 'label' => 'Posts',
	 *                 'value' => 'post',
	 *             ],
	 *             [
	 *                 'label' => 'Pages',
	 *                 'value' => 'page',
	 *             ],
	 *         ],
	 *     ];
	 *
	 *     $should_display = $this->_process_post_type_condition( $condition_settings );
	 *
	 *     if ( $should_display ) {
	 *         // Display post-related content.
	 *     } else {
	 *         // Do not display post-related content.
	 *     }
	 * ```
	 */
	protected function _process_post_type_condition( array $condition_settings ): bool {
		// Only check for single post.
		if ( ! $this->_is_singular_content_context() ) {
			return false;
		}

		$display_rule       = $condition_settings['displayRule'] ?? 'is';
		$post_types_raw     = $condition_settings['postTypes'] ?? [];
		$post_types_values  = array_column( $post_types_raw, 'value' );
		$current_queried_id = $this->_get_condition_evaluation_post_id();
		$post_type          = get_post_type( $current_queried_id );

		$should_display = array_intersect( $post_types_values, (array) $post_type ) ? true : false;

		return ( 'is' === $display_rule ) ? $should_display : ! $should_display;
	}

	/**
	 * Determine if a module is displayable or not based on the given conditions.
	 *
	 * @since ??
	 *
	 * @param  array   $conditions         Associative array containing conditions.
	 * @param  boolean $only_return_status Optional. Whether to return all conditions full status (useful in VB tooltips). Default `false`.
	 *
	 * @return boolean|array Condition output or array of all conditions statuses.
	 */
	public function is_displayable( array $conditions, bool $only_return_status = false ) {
		// Bails out and just displays the module if $conditions is not an array.
		if ( ! is_array( $conditions ) ) {
			return true;
		}

		// Holds current condition evaluation.
		$should_display = true;
		$status         = [];

		// Reverses condition list, We start from the bottom of the list.
		$conditions = array_reverse( $conditions );

		// Holds all the conditions that have been processed, except the ones detected as conflicted.
		$processed_conditions = [];

		foreach ( $conditions as $arr_key => $condition ) {
			$condition_id            = $condition['id'] ?? '';
			$condition_name          = $condition['conditionName'] ?? '';
			$condition_settings      = $condition['conditionSettings'] ?? [];
			$operator                = $condition['operator'] ?? 'OR';
			$is_enable_condition_set = isset( $condition_settings['enableCondition'] ) ? true : false;
			$is_disabled             = $is_enable_condition_set && 'off' === $condition_settings['enableCondition'] ? true : false;

			// Skip if condition is disabled.
			if ( $is_disabled ) {
				$status[] = [
					'id'           => $condition_id,
					'isConflicted' => false,
				];
				continue;
			}

			$is_conflict_detected = $this->_is_condition_conflicted( $condition, $processed_conditions, $operator );

			$status[] = [
				'id'           => $condition_id,
				'isConflicted' => $is_conflict_detected,
			];

			if ( $is_conflict_detected ) {
				continue;
			} else {
				$should_display         = $this->is_condition_true( $condition_id, $condition_name, $condition_settings );
				$processed_conditions[] = $condition;
			}

			// If operator is set to "OR/ANY" break as soon as one condition is true - returning a final true.
			// If operator is set to "AND/ALL" break as soon as one condition is false - returning a final false.
			if ( 'OR' === $operator && $should_display && ! $only_return_status ) {
				break;
			} elseif ( 'AND' === $operator && ! $should_display && ! $only_return_status ) {
				break;
			}
		}

		return ( $only_return_status ) ? $status : $should_display;
	}

	/**
	 * Determines if a condition is true based on the condition ID, name, and settings.
	 *
	 * This function checks the value of the condition name and executes the corresponding private function
	 * to process the condition and determine if it is true or not. If the condition name does not match any
	 * of the predefined cases, the function returns true by default.
	 *
	 * @since ??
	 *
	 * @param int    $condition_id        The ID of the condition.
	 * @param string $condition_name      The name of the condition.
	 * @param array  $condition_settings  The settings for the condition.
	 *
	 * @return bool  Whether the condition is true or not.
	 *
	 * @example:
	 * ```php
	 * // Example 1: Check if logged-in status condition is true
	 * $condition_id = 1;
	 * $condition_name = 'loggedInStatus';
	 * $condition_settings = array(
	 *     'is_logged_in' => true
	 * );
	 * $is_true = is_condition_true( $condition_id, $condition_name, $condition_settings );
	 *
	 * // $is_true will be set to true after processing the 'loggedInStatus' condition
	 * ```
	 *
	 * @example:
	 * ```php
	 * // Example 2: Check if user role condition is true
	 * $condition_id = 2;
	 * $condition_name = 'userRole';
	 * $condition_settings = array(
	 *     'roles' => array( 'admin', 'editor' ),
	 *     'is_exclusive' => false
	 * );
	 * $is_true = is_condition_true( $condition_id, $condition_name, $condition_settings );
	 *
	 * // $is_true will be set to true after processing the 'userRole' condition
	 * ```
	 *
	 * @example:
	 * ```php
	 * // Example 3: Check if author condition is true
	 * $condition_id = 3;
	 * $condition_name = 'author';
	 * $condition_settings = array(
	 *     'author_id' => 123
	 * );
	 * $is_true = is_condition_true( $condition_id, $condition_name, $condition_settings );
	 *
	 * // $is_true will be set to true after processing the 'author' condition
	 * ```
	 */
	public function is_condition_true( $condition_id, string $condition_name, array $condition_settings ): bool {

		switch ( $condition_name ) {
			case 'postType':
				return $this->_process_post_type_condition( $condition_settings );

			case 'categories':
				return $this->_process_categories_condition( $condition_settings );

			case 'tags':
				return $this->_process_tags_condition( $condition_settings );

			case 'author':
				return $this->_process_author_condition( $condition_settings );

			case 'customField':
				return $this->_process_custom_field_condition( $condition_settings );

			case 'tagPage':
				return $this->_process_tag_page_condition( $condition_settings );

			case 'categoryPage':
				return $this->_process_category_page_condition( $condition_settings );

			case 'dateArchive':
				return $this->_process_date_archive_condition( $condition_settings );

			case 'searchResults':
				return $this->_process_search_results_condition( $condition_settings );

			case 'loggedInStatus':
				return $this->_process_logged_in_status_condition( $condition_settings );

			case 'userRole':
				return $this->_process_user_role_condition( $condition_settings );

			case 'dateTime':
				return $this->_process_date_time_condition( $condition_settings );

			case 'pageVisit':
				return $this->_process_page_visit_condition( $condition_settings );

			case 'postVisit':
				return $this->_process_page_visit_condition( $condition_settings );

			case 'numberOfViews':
				return $this->_process_number_of_views_condition( $condition_id, $condition_settings );

			case 'urlParameter':
				return $this->_process_url_parameter_condition( $condition_settings );

			case 'browser':
				return $this->_process_browser_condition( $condition_settings );

			case 'operatingSystem':
				return $this->_process_operating_system_condition( $condition_settings );

			case 'cookie':
				return $this->_process_cookie_condition( $condition_settings );

			case 'productPurchase':
				return $this->_process_product_purchase_condition( $condition_settings );

			case 'cartContents':
				return $this->_process_cart_contents_condition( $condition_settings );

			case 'productStock':
				return $this->_process_product_stock_condition( $condition_settings );

			default:
				/**
				 * Filters the evaluation result of a custom condition.
				 *
				 * This filter allows third-party developers to provide evaluation logic for custom conditions
				 * registered via the `divi.module.options.conditions.condition` filter. When a custom condition
				 * is evaluated on the PHP side, this hook is called to determine if the condition is true or false.
				 *
				 * @since ??
				 *
				 * @param bool|null $is_condition_true The evaluation result, or null if not handled. Default null.
				 * @param string    $condition_name    The name of the condition being evaluated.
				 * @param array     $condition_settings The condition settings array.
				 * @param string    $condition_id      The unique ID of the condition instance.
				 *
				 * @example
				 * ```php
				 * add_filter(
				 *     'divi_module_options_conditions_is_custom_condition_true',
				 *     function( $is_condition_true, $condition_name, $condition_settings, $condition_id ) {
				 *         if ( 'myCustomCondition' === $condition_name ) {
				 *             $display_rule = $condition_settings['displayRule'] ?? 'is';
				 *             $custom_value  = $condition_settings['customValue'] ?? '';
				 *
				 *             // Your custom evaluation logic here
				 *             $is_match = ( 'test' === $custom_value );
				 *
				 *             return ( 'is' === $display_rule ) ? $is_match : ! $is_match;
				 *         }
				 *         return $is_condition_true;
				 *     },
				 *     10,
				 *     4
				 * );
				 * ```
				 */
				$is_custom_condition_true = apply_filters(
					'divi_module_options_conditions_is_custom_condition_true',
					null,
					$condition_name,
					$condition_settings,
					$condition_id
				);

				if ( null !== $is_custom_condition_true ) {
					return (bool) $is_custom_condition_true;
				}

				// Custom conditions have higher priority than dynamic posts because 'dynamicPosts'
				// is just a property in the condition settings, not an actual condition name.
				// This allows third-party developers to override dynamic post type conditions
				// by registering a custom condition with the same name as the post type.
				if ( isset( $condition_settings['dynamicPosts'] ) ) {
					return $this->_process_dynamic_posts_condition( $condition_settings );
				}

				return true;
		}
	}

	/**
	 * Checks the given condition against the processed conditions to determine if it is considered a conflict or not.
	 *
	 * When the operator `'OR/Any'` is selected and we have more than one condition of the same type, the priority is with
	 * the latest condition (located lower in the list).
	 *
	 * When the operator `'AND/All'` is selected, no condition is considered a conflict.
	 *
	 * @since ??
	 *
	 * @param array  $condition            The condition to be checked, containing all its settings.
	 * @param array  $processed_conditions The array of previously processed conditions.
	 * @param string $operator             The selected operator for the Display Conditions, with options: `'OR'`, or `'AND'`.
	 *
	 * @return bool Whether the condition is conflicted or not.
	 *
	 * @example:
	 * ```php
	 *    $condition = [
	 *        'conditionName' => 'customField',
	 *        'conditionSettings' => [
	 *            // Condition settings here
	 *        ],
	 *    ];
	 *    $processed_conditions = [
	 *        // Previously processed conditions here
	 *    ];
	 *    $operator = 'AND';
	 *    $is_conflicted = $this->_is_condition_conflicted($condition, $processed_conditions, $operator);
	 * ```
	 */
	protected function _is_condition_conflicted( array $condition, array $processed_conditions, string $operator ): bool {

		if ( 'AND' === $operator ) {
			return false;
		}

		$is_conflicted = false;

		// Check condition against all previously processed conditions.
		foreach ( $processed_conditions as $processed_condition ) {
			// Only check same condition types against each other, Ex. UserRole against UserRole.
			if ( $condition['conditionName'] !== $processed_condition['conditionName'] ) {
				continue;
			}

			// Exception! "Date Time" Condition can have multiple positive conditions.
			$is_datetime                           = 'dateTime' === $condition['conditionName'];
			$is_prev_cond_datetime_and_negative    = $is_datetime && 'isNotOnSpecificDate' === ( $processed_condition['conditionSettings']['displayRule'] ?? null );
			$is_current_cond_datetime_and_negative = $is_datetime && 'isNotOnSpecificDate' === ( $condition['conditionSettings']['displayRule'] ?? null );
			if ( $is_prev_cond_datetime_and_negative || $is_current_cond_datetime_and_negative ) {
				$is_conflicted = true;
				break;
			} elseif ( $is_datetime ) {
				$is_conflicted = false;
				break;
			}

			// Exception! "Custom Field" Condition can have multiple conditions.
			$is_custom_field = 'customField' === $condition['conditionName'];
			if ( $is_custom_field ) {
				$is_conflicted = false;
				break;
			}

			// Exception! "URL Parameter" Condition can have multiple conditions.
			$is_url_parameter = 'urlParameter' === $condition['conditionName'];
			if ( $is_url_parameter ) {
				$is_conflicted = false;
				break;
			}

			// Exception! "Cookie" Condition can have multiple conditions.
			$is_cookie = 'cookie' === $condition['conditionName'];
			if ( $is_cookie ) {
				$is_conflicted = false;
				break;
			}

			/**
			 * When operator is set to "OR/ANY" and we have more than one condition, all other conditions
			 * will be set as conflicted, giving the priority to the latest condition in the list.
			 */
			if ( count( $processed_conditions ) > 0 ) {
				$is_conflicted = true;
				break;
			}
		}

		return $is_conflicted;
	}

	/**
	 * Process the search Results condition.
	 *
	 * This function takes the condition settings for search results and determines
	 * if module should be displayed based on those settings.
	 *
	 * @since ??
	 *
	 * @param array $condition_settings {
	 *     Array of condition settings for cookies.
	 *
	 *     @type string $displayRule Optional. The display rule for cookies.
	 *     @type string  $specific_search_queries_raw  The search queries to check against. Defaults empty string `''`.
	 *     @type string  $excluded_search_queries_raw The search queries to exclude. Defaults empty string `''`.
	 * }
	 *
	 * @return bool Whether or not the module should be displayed based on the condition settings.
	 *
	 * @example
	 * ```php
	 *     $condition_settings = [
	 *         'displayRule' => 'specificSearchQueries',
	 *         'specificSearchQueries' => 'hello',
	 *     ];
	 *
	 *     $should_display = $this->_process_search_results_condition( $condition_settings );
	 *
	 *     if ( $should_display ) {
	 *         // Display search-results-related content.
	 *     } else {
	 *         // Do not display search-results-related content.
	 *     }
	 * ```
	 */
	protected function _process_search_results_condition( array $condition_settings ): bool {

		// Only check for Search.
		if ( ! is_search() ) {
			return false;
		}
		$display_rule                = $condition_settings['displayRule'] ?? 'specificSearchQueries';
		$specific_search_queries_raw = $condition_settings['specificSearchQueries'] ?? '';
		$excluded_search_queries_raw = $condition_settings['excludedSearchQueries'] ?? '';
		$specific_search_queries     = explode( ',', $specific_search_queries_raw );
		$excluded_search_queries     = explode( ',', $excluded_search_queries_raw );

		switch ( $display_rule ) {
			case 'specificSearchQueries':
				return $this->_is_specific_search_query( $specific_search_queries );

			case 'excludedSearchQueries':
				return ! $this->_is_specific_search_query( $excluded_search_queries );

			default:
				return false;
		}
	}

	/**
	 * "is specific search query" Condition logic.
	 *
	 * @since ??
	 *
	 * @param array $specific_search_queries Array of search queries.
	 * @return boolean Indicating whether "is specific search query" Condition is true or false.
	 */
	protected function _is_specific_search_query( $specific_search_queries ) {
		$is_specific_search_query = false;
		foreach ( $specific_search_queries as $search_query ) {
			$is_specific_search_query = get_search_query() === $search_query;
			if ( $is_specific_search_query ) {
				break;
			}
		}
		return $is_specific_search_query;
	}

	/**
	 * Processes "Tag Page" condition.
	 *
	 * @since ??
	 *
	 * @param  array $condition_settings Containing all settings of the condition.
	 *
	 * @return boolean Condition output.
	 */
	protected function _process_tag_page_condition( $condition_settings ) {

		// Only check for Archive pages.
		if ( ! is_archive() ) {
			return false;
		}

		$display_rule            = $condition_settings['displayRule'] ?? 'is';
		$tags_raw                = $this->_resolve_tags_condition_items( $condition_settings );
		$queried_object          = get_queried_object();
		$is_queried_object_valid = $queried_object instanceof \WP_Term && property_exists( $queried_object, 'taxonomy' );

		if ( ! $is_queried_object_valid ) {
			return false;
		}

		$queried_taxonomy = $queried_object->taxonomy;
		$tags_raw_ids     = array_map(
			function ( $item ) {
				return $item['value'];
			},
			$tags_raw
		);

		$tax_slugs_of_catch_all_items = [];
		$is_any_catch_all_selected    = false;
		foreach ( $tags_raw as $item ) {
			if ( isset( $item['isCatchAll'] ) && true === $item['isCatchAll'] ) {
				$tax_slugs_of_catch_all_items[] = $item['groupSlug'];
				$is_any_catch_all_selected      = true;
			}
		}

		// Logic evaluation.
		$current_tag_id = get_queried_object_id();
		$is_displayable = array_intersect( $tags_raw_ids, (array) $current_tag_id ) ? true : false;

		if ( ! $is_displayable && $is_any_catch_all_selected ) {
			$is_displayable = array_intersect( $tax_slugs_of_catch_all_items, (array) $queried_taxonomy ) ? true : false;
		}

		// Evaluation output.
		return ( 'is' === $display_rule ) ? $is_displayable : ! $is_displayable;
	}

	/**
	 * Processes "Tags" condition.
	 *
	 * @since ??
	 *
	 * @param  array $condition_settings Containing all condition settings.
	 *
	 * @return boolean Condition output.
	 */
	protected function _process_tags_condition( $condition_settings ) {

		if ( ! $this->_is_singular_content_context() ) {
			return false;
		}

		$display_rule = isset( $condition_settings['displayRule'] ) ? $condition_settings['displayRule'] : 'is';
		$tags_raw     = $this->_resolve_tags_condition_items( $condition_settings );

		$tags = array_map(
			function ( $item ) {
				return (object) [
					'id'            => $item['value'],
					'taxonomy_slug' => $item['groupSlug'],
				];
			},
			$tags_raw
		);

		$current_queried_id           = $this->_get_condition_evaluation_post_id();
		$has_post_specified_term      = false;
		$tax_slugs_of_catch_all_items = [];
		$is_any_catch_all_selected    = false;
		$has_post_specified_taxonomy  = false;

		// Logic evaluation.
		foreach ( $tags_raw as $item ) {
			if ( isset( $item['isCatchAll'] ) && true === $item['isCatchAll'] ) {
				$tax_slugs_of_catch_all_items[] = $item['groupSlug'];
				$is_any_catch_all_selected      = true;
			}
		}

		foreach ( $tags as $tag ) {
			if ( is_object( $tag ) && has_term( $tag->id, $tag->taxonomy_slug, $current_queried_id ) ) {
				$has_post_specified_term = true;
				break;
			}
		}

		$is_displayable = $has_post_specified_term ? true : false;

		if ( ! $is_displayable && $is_any_catch_all_selected ) {
			foreach ( $tax_slugs_of_catch_all_items as $tax_slug ) {
				$has_post_specified_taxonomy = has_term( '', $tax_slug, $current_queried_id );
				if ( $has_post_specified_taxonomy ) {
					break;
				}
			}

			$is_displayable = $has_post_specified_taxonomy ? true : false;
		}

		// Evaluation output.
		return ( 'is' === $display_rule ) ? $is_displayable : ! $is_displayable;
	}

	/**
	 * Processes "URL Parameter" condition.
	 *
	 * @since ??
	 *
	 * @param  array $condition_settings The Condition settings containing:
	 *                                   'selectUrlParameter' => string,
	 *                                   'displayRule' => string,
	 *                                   'urlParameterName' => string,
	 *                                   'urlParameterValue' => string.
	 *
	 * @return boolean Returns `true` if the condition evaluation is true, `false` otherwise.
	 */
	protected function _process_url_parameter_condition( array $condition_settings ): bool {
		$select_url_parameter = $condition_settings['selectUrlParameter'] ?? 'specificUrlParameter';
		$display_rule         = $condition_settings['displayRule'] ?? 'equals';
		$url_parameter_name   = $condition_settings['urlParameterName'] ?? '';
		$url_parameter_value  = $condition_settings['urlParameterValue'] ?? '';

		$get_url_parameter    = isset( $_GET[ $url_parameter_name ] ) ? sanitize_text_field( wp_unslash( $_GET[ $url_parameter_name ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No need to use nonce as there is no form processing.
		$is_url_parameter_set = isset( $_GET[ $url_parameter_name ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No need to use nonce as there is no form processing.

		if ( 'anyUrlParameter' === $select_url_parameter ) {
			$parameter_values = $this->_get_all_parameter_values();
			$output           = [
				'equals'         => count( $parameter_values ) > 0 && array_intersect( $parameter_values, (array) $url_parameter_value ),
				'exist'          => count( $parameter_values ) > 0,
				'doesNotExist'   => count( $parameter_values ) === 0,
				'doesNotEqual'   => count( $parameter_values ) > 0 && ! array_intersect( $parameter_values, (array) $url_parameter_value ),
				'contains'       => count( $parameter_values ) > 0 && $this->_array_contains_string( $parameter_values, $url_parameter_value ),
				'doesNotContain' => count( $parameter_values ) > 0 && ! $this->_array_contains_string( $parameter_values, $url_parameter_value ),
			];
		} else {
			$output = [
				'equals'         => $is_url_parameter_set && $get_url_parameter === $url_parameter_value,
				'exist'          => $is_url_parameter_set,
				'doesNotExist'   => ! $is_url_parameter_set,
				'doesNotEqual'   => $is_url_parameter_set && $get_url_parameter !== $url_parameter_value,
				'contains'       => $is_url_parameter_set && str_contains( $get_url_parameter, $url_parameter_value ),
				'doesNotContain' => $is_url_parameter_set && ! str_contains( $get_url_parameter, $url_parameter_value ),
			];
		}

		return isset( $output[ $display_rule ] ) ? $output[ $display_rule ] : false;
	}

	/**
	 * Returns all parameter values.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	protected function _get_all_parameter_values() {
		return array_map(
			function ( $value ) {
				return $value;
			},
			$_GET // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No need to use nonce.
		);
	}

	/**
	 * Checks if `$haystack` items contain `$needle` in their values.
	 *
	 * @since ??
	 *
	 * @param array  $haystack The array to search in.
	 * @param string $needle   The string needle to search for.
	 *
	 * @return boolean
	 */
	protected function _array_contains_string( $haystack, $needle ) {
		$filtered_array = array_filter(
			$haystack,
			function ( $value ) use ( $needle ) {
				return str_contains( $value, $needle );
			}
		);
		return count( $filtered_array ) > 0 ? true : false;
	}

	/**
	 * Process the user role condition.
	 *
	 * This function takes the condition settings for user roles and determines
	 * if the current user should be displayed based on those settings.
	 *
	 * @since ??
	 *
	 * @param array $condition_settings {
	 *     Array of condition settings for user roles.
	 *
	 *     @type string $displayRule Optional. The display rule for user roles. One of `'is'`, or `'not'`. Default `is`.
	 *     @type array  $userRoles   Optional. The user roles to check against. Defaults `[]`.
	 *     @type string $userIds     Optional. The user IDs as a comma-separated string. Defaults to an empty string.
	 * }
	 *
	 * @return bool Whether or not the current user should be displayed based on the condition settings.
	 *
	 * @example
	 * ```php
	 *     $condition_settings = [
	 *         'displayRule' => 'is',
	 *         'userRoles' => [
	 *             [
	 *                 'label' => 'Administrator',
	 *                 'value' => 'administrator',
	 *             ],
	 *             [
	 *                 'label' => 'Editor',
	 *                 'value' => 'editor',
	 *             ]
	 *         ],
	 *         'userIds' => '1, 2, 3'
	 *     ];
	 *
	 *     $should_display = $this->_process_user_role_condition( $condition_settings );
	 *
	 *     if ( $should_display ) {
	 *         // Display user-related content.
	 *     } else {
	 *         // Do not display user-related content.
	 *     }
	 * ```
	 */
	protected function _process_user_role_condition( array $condition_settings ): bool {
		$display_rule = $condition_settings['displayRule'] ?? 'is';
		$roles_raw    = $condition_settings['userRoles'] ?? [];
		$ids_raw      = $condition_settings['userIds'] ?? '';
		$ids          = isset( $ids_raw ) ? array_map( 'trim', array_filter( explode( ',', $ids_raw ) ) ) : [];
		$roles        = array_column( $roles_raw, 'value' );
		$user         = wp_get_current_user();

		$should_display_based_on_roles = array_intersect( $roles, (array) $user->roles ) ? true : false;
		$should_display_based_on_ids   = array_intersect( $ids, (array) $user->ID ) ? true : false;
		$should_display                = ( $should_display_based_on_roles || $should_display_based_on_ids );

		return ( 'is' === $display_rule ) ? $should_display : ! $should_display;
	}
}
