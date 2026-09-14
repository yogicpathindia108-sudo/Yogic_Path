<?php
/**
 * Post Filter Item field list item resolution.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostFilterItem;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Options\Loop\LoopUtils;
use ET\Builder\Packages\Module\Options\Loop\QueryOrderBy\QueryOrderByController;

/**
 * Builds selectable list items for post-filter-item list controls.
 *
 * @since ??
 */
class PostFilterFieldListItems {
	/**
	 * Order field option labels keyed by value slug.
	 *
	 * @since ??
	 *
	 * @return array<string, string>
	 */
	private static function _get_order_option_labels(): array {
		return [
			'default'    => __( 'Default', 'et_builder_5' ),
			'ascending'  => __( 'Ascending', 'et_builder_5' ),
			'descending' => __( 'Descending', 'et_builder_5' ),
		];
	}

	/**
	 * Resolve list items for a field type and option key.
	 *
	 * @since ??
	 *
	 * @param string $field_type       Field control type.
	 * @param string $field_option_key Selected field option key.
	 * @param string $post_type        Effective post type for taxonomy/author resolution.
	 * @param array  $attrs            Saved module attrs for order-by option filtering.
	 * @param string $query_type       Parent target loop query type for order-by resolution.
	 * @param array  $loop_term_restrictions Optional include/exclude term ID restrictions.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function get_list_items(
		string $field_type,
		string $field_option_key,
		string $post_type = 'post',
		array $attrs = [],
		string $query_type = 'post_types',
		array $loop_term_restrictions = []
	): array {
		if ( 'order' === $field_type ) {
			return self::_get_order_list_items();
		}

		if ( 'orderby' === $field_type || 'multiple-order' === $field_type ) {
			return self::_get_orderby_list_items( $post_type, $attrs, $query_type );
		}

		if ( 'product-status' === $field_type ) {
			return self::_get_product_status_list_items();
		}

		if ( 'product-review' === $field_type ) {
			return self::_get_product_review_list_items();
		}

		if ( ! in_array( $field_type, [ 'select', 'checkbox', 'radio' ], true ) || '' === $field_option_key ) {
			return [];
		}

		if ( 'author' === $field_option_key ) {
			return self::_get_author_list_items( $post_type );
		}

		if ( 'role' === $field_option_key ) {
			return self::_get_role_list_items();
		}

		return self::_get_taxonomy_term_list_items( $field_option_key, $post_type, $loop_term_restrictions );
	}

	/**
	 * Product stock status option labels keyed by WooCommerce status slug.
	 *
	 * @since ??
	 *
	 * @return array<string, string>
	 */
	private static function _get_product_status_option_labels(): array {
		if ( ! function_exists( 'wc_get_product_stock_status_options' ) ) {
			return [];
		}

		$status_options = wc_get_product_stock_status_options();

		return is_array( $status_options ) ? $status_options : [];
	}

	/**
	 * Build WooCommerce product stock status list items.
	 *
	 * @since ??
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function _get_product_status_list_items(): array {
		$list_items = [];

		foreach ( self::_get_product_status_option_labels() as $value => $label ) {
			$list_items[] = [
				'value' => $value,
				'label' => $label,
			];
		}

		return $list_items;
	}

	/**
	 * Build WooCommerce product review rating list items.
	 *
	 * @since ??
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function _get_product_review_list_items(): array {
		$list_items = [];

		for ( $rating = 5; $rating >= 1; $rating-- ) {
			$list_items[] = [
				'value' => (string) $rating,
				'label' => sprintf(
					/* translators: %d: rating from 1 to 5. */
					__( 'Rated %d out of 5', 'et_builder_5' ),
					$rating
				),
			];
		}

		return $list_items;
	}

	/**
	 * Build order list items.
	 *
	 * @since ??
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function _get_order_list_items(): array {
		$list_items = [];

		foreach ( self::_get_order_option_labels() as $value => $label ) {
			$list_items[] = [
				'value' => $value,
				'label' => $label,
			];
		}

		return $list_items;
	}

	/**
	 * Build order-by list items for one post type.
	 *
	 * @since ??
	 *
	 * @param string $post_type   Effective post type.
	 * @param array  $attrs       Saved module attrs for enabled order-by option filtering.
	 * @param string $query_type  Parent target loop query type.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function _get_orderby_list_items(
		string $post_type = 'post',
		array $attrs = [],
		string $query_type = 'post_types'
	): array {
		$list_items  = [
			[
				'value' => 'default',
				'label' => __( 'Default', 'et_builder_5' ),
			],
		];
		$seen_values = [ 'default' => true ];

		if ( in_array( $query_type, [ 'post_taxonomies', 'terms' ], true ) ) {
			$order_by_options = self::_get_terms_loop_orderby_options();
		} elseif ( PostFilterCustomFieldUtils::is_user_loop_query_type( $query_type ) ) {
			$order_by_options = self::_get_users_loop_orderby_options();
		} elseif ( 'menus' === $query_type ) {
			$order_by_options = self::_get_menus_loop_orderby_options();
		} else {
			$order_by_options = QueryOrderByController::get_options( 'post_types', '' !== $post_type ? $post_type : 'post' );
		}

		foreach ( $order_by_options as $order_by_option ) {
			$value = $order_by_option['value'] ?? '';
			$label = $order_by_option['label'] ?? $value;

			if ( '' === $value || isset( $seen_values[ $value ] ) ) {
				continue;
			}

			$seen_values[ $value ] = true;
			$list_items[]          = [
				'value' => $value,
				'label' => $label,
			];
		}

		if ( 'post_types' === $query_type || 'current_page' === $query_type ) {
			foreach ( self::_get_orderby_custom_field_list_items( $post_type ) as $custom_field_item ) {
				$value = $custom_field_item['value'] ?? '';

				if ( '' === $value || isset( $seen_values[ $value ] ) ) {
					continue;
				}

				$seen_values[ $value ] = true;
				$list_items[]          = $custom_field_item;
			}
		}

		return self::_filter_orderby_list_items_by_enabled_options( $list_items, $attrs );
	}

	/**
	 * Build terms-loop order-by options aligned with loop term dynamic content fields.
	 *
	 * @since ??
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function _get_terms_loop_orderby_options(): array {
		return [
			[
				'value' => 'name',
				'label' => esc_html__( 'Term Name', 'et_builder_5' ),
			],
			[
				'value' => 'description',
				'label' => esc_html__( 'Term Description', 'et_builder_5' ),
			],
			[
				'value' => 'count',
				'label' => esc_html__( 'Term Count', 'et_builder_5' ),
			],
			[
				'value' => 'taxonomy',
				'label' => esc_html__( 'Taxonomy', 'et_builder_5' ),
			],
		];
	}

	/**
	 * Build users-loop order-by options aligned with loop user dynamic content fields.
	 *
	 * @since ??
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function _get_users_loop_orderby_options(): array {
		return [
			[
				'value' => 'display_name',
				'label' => esc_html__( 'Display Name', 'et_builder_5' ),
			],
			[
				'value' => 'login',
				'label' => esc_html__( 'Username', 'et_builder_5' ),
			],
			[
				'value' => 'email',
				'label' => esc_html__( 'Email', 'et_builder_5' ),
			],
			[
				'value' => 'description',
				'label' => esc_html__( 'Bio/Description', 'et_builder_5' ),
			],
		];
	}

	/**
	 * Build menus-loop order-by options aligned with loop menu dynamic content fields.
	 *
	 * @since ??
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function _get_menus_loop_orderby_options(): array {
		return [
			[
				'value' => 'menu_text',
				'label' => esc_html__( 'Menu Text', 'et_builder_5' ),
			],
			[
				'value' => 'attr_title',
				'label' => esc_html__( 'Title Attribute', 'et_builder_5' ),
			],
			[
				'value' => 'classes',
				'label' => esc_html__( 'CSS Classes', 'et_builder_5' ),
			],
			[
				'value' => 'xfn',
				'label' => esc_html__( 'Link Relationship', 'et_builder_5' ),
			],
			[
				'value' => 'description',
				'label' => esc_html__( 'Menu Description', 'et_builder_5' ),
			],
		];
	}

	/**
	 * Build custom-field order-by list items for one post type.
	 *
	 * @since ??
	 *
	 * @param string $post_type Effective post type.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function _get_orderby_custom_field_list_items( string $post_type ): array {
		$choices    = PostFilterCustomFieldUtils::get_field_option_choices( $post_type );
		$list_items = [];

		foreach ( $choices as $group ) {
			if ( ! is_array( $group ) || ! is_array( $group['options'] ?? null ) ) {
				continue;
			}

			foreach ( $group['options'] as $value => $option ) {
				if ( PostFilterCustomFieldUtils::MANUAL_OPTION === $value ) {
					continue;
				}

				if ( ! PostFilterCustomFieldUtils::is_custom_field_option( (string) $value ) ) {
					continue;
				}

				$label = is_array( $option ) ? ( $option['label'] ?? $value ) : $value;

				$list_items[] = [
					'value' => (string) $value,
					'label' => is_string( $label ) ? $label : (string) $value,
				];
			}
		}

		return $list_items;
	}

	/**
	 * Filter order-by list items to author-enabled option values.
	 *
	 * @since ??
	 *
	 * @param array<int, array{value: string, label: string}> $list_items All order-by list items.
	 * @param array                                           $attrs      Saved module attrs.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function _filter_orderby_list_items_by_enabled_options( array $list_items, array $attrs ): array {
		$enabled_options = $attrs['field']['advanced']['orderbyEnabledOptions']['desktop']['value'] ?? null;

		if ( ! is_array( $enabled_options ) || empty( $enabled_options ) ) {
			return [];
		}

		$enabled_values = array_values(
			array_filter(
				array_map(
					static function ( $option_value ) {
						return is_string( $option_value ) && '' !== $option_value ? $option_value : '';
					},
					$enabled_options
				)
			)
		);

		if ( empty( $enabled_values ) ) {
			return [];
		}

		$enabled_lookup = array_fill_keys( $enabled_values, true );

		return array_values(
			array_filter(
				$list_items,
				static function ( $list_item ) use ( $enabled_lookup ) {
					$value = $list_item['value'] ?? '';

					return is_string( $value ) && isset( $enabled_lookup[ $value ] );
				}
			)
		);
	}

	/**
	 * Build taxonomy term list items.
	 *
	 * @since ??
	 *
	 * @param string $taxonomy_slug Taxonomy slug (field option key).
	 * @param string $post_type     Post type slug.
	 * @param array  $loop_term_restrictions Optional include/exclude term ID restrictions.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function _get_taxonomy_term_list_items(
		string $taxonomy_slug,
		string $post_type,
		array $loop_term_restrictions = []
	): array {
		if ( ! taxonomy_exists( $taxonomy_slug ) ) {
			return [];
		}

		if ( '' !== $post_type && ! is_object_in_taxonomy( $post_type, $taxonomy_slug ) ) {
			return [];
		}

		$normalized_restrictions = self::_normalize_loop_term_restrictions( $loop_term_restrictions, $taxonomy_slug );
		$include_ids             = $normalized_restrictions['include'];
		$exclude_ids             = $normalized_restrictions['exclude'];

		if ( $normalized_restrictions['has_include_restriction'] && empty( $include_ids ) ) {
			return [];
		}

		if ( ! empty( $include_ids ) ) {
			$get_terms_args = [
				'taxonomy'   => $taxonomy_slug,
				'hide_empty' => false,
				'include'    => $include_ids,
			];
		} else {
			$get_terms_args = [
				'taxonomy'   => $taxonomy_slug,
				'hide_empty' => false,
				'number'     => 0,
			];
		}

		$terms = get_terms( $get_terms_args );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		if ( empty( $include_ids ) && ! empty( $exclude_ids ) ) {
			$exclude_lookup = array_fill_keys( $exclude_ids, true );
			$terms          = array_values(
				array_filter(
					$terms,
					static function ( $term ) use ( $exclude_lookup ) {
						return ! isset( $exclude_lookup[ (int) $term->term_id ] );
					}
				)
			);
		}

		$list_items = [];

		foreach ( $terms as $term ) {
			$list_items[] = [
				'value' => $term->slug,
				'label' => $term->name,
			];
		}

		return $list_items;
	}

	/**
	 * Normalize loop term restrictions for taxonomy list item filtering.
	 *
	 * @since ??
	 *
	 * @param array  $loop_term_restrictions Raw include/exclude term ID restrictions.
	 * @param string $taxonomy_slug          Taxonomy slug.
	 *
	 * @return array{include: int[], exclude: int[], has_include_restriction: bool} Normalized restrictions.
	 */
	private static function _normalize_loop_term_restrictions( array $loop_term_restrictions, string $taxonomy_slug ): array {
		$raw_include_ids         = is_array( $loop_term_restrictions['include'] ?? null ) ? $loop_term_restrictions['include'] : [];
		$raw_exclude_ids         = is_array( $loop_term_restrictions['exclude'] ?? null ) ? $loop_term_restrictions['exclude'] : [];
		$has_include_restriction = ! empty( $loop_term_restrictions['has_include_restriction'] );
		$include_ids             = array_map( 'intval', $raw_include_ids );
		$exclude_ids             = array_map( 'intval', $raw_exclude_ids );
		$include_ids             = LoopUtils::filter_valid_term_ids( $include_ids, $taxonomy_slug );
		$exclude_ids             = LoopUtils::filter_valid_term_ids( $exclude_ids, $taxonomy_slug );

		if ( ! $has_include_restriction && ! empty( $include_ids ) ) {
			$has_include_restriction = true;
		}

		if ( ! empty( $include_ids ) && ! empty( $exclude_ids ) ) {
			$include_ids = array_values( array_diff( $include_ids, $exclude_ids ) );
		}

		return [
			'include'                 => $include_ids,
			'exclude'                 => $exclude_ids,
			'has_include_restriction' => $has_include_restriction,
		];
	}

	/**
	 * Build author list items for a post type.
	 *
	 * @since ??
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function _get_author_list_items( string $post_type ): array {
		$user_query_args = [
			'orderby' => 'display_name',
			'order'   => 'ASC',
			'fields'  => [ 'ID', 'display_name', 'user_login' ],
		];

		if ( '' !== $post_type && post_type_exists( $post_type ) ) {
			$user_query_args['has_published_posts'] = [ $post_type ];
		} else {
			$user_query_args['who'] = 'authors';
		}

		$users = get_users( $user_query_args );

		if ( empty( $users ) ) {
			return [];
		}

		$list_items = [];

		foreach ( $users as $user ) {
			$display_name = '' !== $user->display_name ? $user->display_name : $user->user_login;

			$list_items[] = [
				'value' => (string) $user->ID,
				'label' => $display_name,
			];
		}

		return $list_items;
	}

	/**
	 * Build role list items.
	 *
	 * @since ??
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function _get_role_list_items(): array {
		if ( ! function_exists( 'wp_roles' ) ) {
			return [];
		}

		$wp_roles   = wp_roles();
		$list_items = [];

		foreach ( $wp_roles->roles as $role_slug => $role_data ) {
			$list_items[] = [
				'value' => $role_slug,
				'label' => translate_user_role( $role_data['name'] ),
			];
		}

		return $list_items;
	}
}
