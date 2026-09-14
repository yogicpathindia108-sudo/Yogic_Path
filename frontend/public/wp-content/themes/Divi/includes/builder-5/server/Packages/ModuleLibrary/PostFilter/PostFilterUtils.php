<?php
/**
 * Post Filter shared utilities.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostFilter;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Utility\URLUtility;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\Packages\ModuleLibrary\PostFilter\Query\PostFilterQueryHelpers;
use ET\Builder\Packages\ModuleLibrary\PostFilterItem\PostFilterCustomFieldUtils;

class PostFilterUtils {
	/**
	 * Query-string key for the filter relation hidden field.
	 *
	 * @var string
	 */
	public const FILTER_RELATION_PARAM_KEY = 'relation';

	/**
	 * Build a namespaced GET query parameter name for one filter control.
	 *
	 * Filter params use the `loop_filter-{loopId}[{fieldKey}]` namespace so they are
	 * identifiable in query strings and do not collide with loop pagination (`?{loopId}=N`).
	 *
	 * @since ??
	 *
	 * @param string $target_loop      Parent target loop id.
	 * @param string $field_type         Child field type.
	 * @param string $field_option_key   Child field option key.
	 * @param string $suffix             Optional suffix (for example `min` / `max`).
	 * @param bool   $is_checkbox_array  Whether the control submits multiple values.
	 * @param string $custom_field_key   Manual custom field meta key.
	 * @param string $module_id          Child module id.
	 * @param string $field_value_type   Selected Field Value Type.
	 *
	 * @return string Empty string when target loop is missing.
	 */
	public static function get_field_query_param_name(
		string $target_loop,
		string $field_type,
		string $field_option_key = '',
		string $suffix = '',
		bool $is_checkbox_array = false,
		string $custom_field_key = '',
		string $module_id = '',
		string $field_value_type = ''
	): string {
		$normalized_target_loop = trim( $target_loop );

		if ( '' === $normalized_target_loop || 'main_query' === $normalized_target_loop ) {
			return '';
		}

		$param_key = self::resolve_param_key( $field_type, $field_option_key, $custom_field_key, $module_id, $field_value_type );

		if ( '' === $param_key ) {
			return '';
		}

		if ( '' !== $suffix ) {
			$param_key .= "_{$suffix}";
		}

		$param_name = "loop_filter-{$normalized_target_loop}[{$param_key}]";

		if ( $is_checkbox_array ) {
			$param_name .= '[]';
		}

		return $param_name;
	}

	/**
	 * Resolve the query-string key segment for a filter control.
	 *
	 * @since ??
	 *
	 * @param string $field_type       Child field type.
	 * @param string $field_option_key Child field option key.
	 * @param string $custom_field_key Manual custom field meta key.
	 * @param string $module_id        Child module id.
	 * @param string $field_value_type Selected Field Value Type.
	 *
	 * @return string
	 */
	public static function resolve_param_key( string $field_type, string $field_option_key, string $custom_field_key = '', string $module_id = '', string $field_value_type = '' ): string {
		$param_field_type = PostFilterCustomFieldUtils::resolve_query_param_field_type(
			[
				'type'           => $field_type,
				'fieldValueType' => $field_value_type,
			]
		);

		if ( in_array( $param_field_type, [ 'order', 'orderby', 'multiple-order', 'product-range', 'product-status', 'product-review', self::FILTER_RELATION_PARAM_KEY ], true ) ) {
			if ( 'orderby' === $param_field_type ) {
				return 'order_by';
			}

			return $param_field_type;
		}

		if ( 'text' === $param_field_type ) {
			return PostFilterCustomFieldUtils::resolve_text_field_param_key( $module_id );
		}

		if ( 'number' === $param_field_type ) {
			return PostFilterCustomFieldUtils::resolve_number_field_param_key( $module_id );
		}

		if ( 'date' === $param_field_type ) {
			return PostFilterCustomFieldUtils::resolve_date_field_param_key( $module_id );
		}

		if ( 'date-time' === $param_field_type ) {
			return PostFilterCustomFieldUtils::resolve_date_time_field_param_key( $module_id );
		}

		if ( 'time' === $param_field_type ) {
			return PostFilterCustomFieldUtils::resolve_time_field_param_key( $module_id );
		}

		if ( 'select' === $param_field_type && PostFilterCustomFieldUtils::is_custom_field_option( $field_option_key ) ) {
			return PostFilterCustomFieldUtils::resolve_select_field_param_key( $module_id );
		}

		if ( 'radio' === $param_field_type && PostFilterCustomFieldUtils::is_custom_field_option( $field_option_key ) ) {
			return PostFilterCustomFieldUtils::resolve_radio_field_param_key( $module_id );
		}

		if ( PostFilterCustomFieldUtils::is_custom_field_option( $field_option_key ) ) {
			return PostFilterCustomFieldUtils::resolve_query_param_key( $field_option_key, $custom_field_key );
		}

		return '' !== $field_option_key ? $field_option_key : $param_field_type;
	}

	/**
	 * Build the GET query parameter name for the relation hidden field.
	 *
	 * @since ??
	 *
	 * @param string $target_loop Parent target loop id.
	 *
	 * @return string Empty string when target loop is missing.
	 */
	public static function get_filter_relation_query_param_name( string $target_loop ): string {
		return self::get_field_query_param_name( $target_loop, self::FILTER_RELATION_PARAM_KEY );
	}

	/**
	 * Read one filter value from the current request query string.
	 *
	 * @since ??
	 *
	 * @param string $target_loop      Parent target loop id.
	 * @param string $field_type       Child field type.
	 * @param string $field_option_key Child field option key.
	 * @param string $suffix           Optional suffix (for example `min` / `max`).
	 * @param string $custom_field_key Manual custom field meta key.
	 * @param string $module_id        Child module id.
	 * @param string $field_value_type Selected Field Value Type.
	 *
	 * @return string|array|null Sanitized value, array of values for multi-select, or null when unset.
	 */
	public static function get_field_query_param_value(
		string $target_loop,
		string $field_type,
		string $field_option_key = '',
		string $suffix = '',
		string $custom_field_key = '',
		string $module_id = '',
		string $field_value_type = ''
	) {
		$normalized_target_loop = trim( $target_loop );

		if ( '' === $normalized_target_loop || 'main_query' === $normalized_target_loop ) {
			return null;
		}

		$param_key = self::resolve_param_key( $field_type, $field_option_key, $custom_field_key, $module_id, $field_value_type );

		if ( '' === $param_key ) {
			return null;
		}

		if ( '' !== $suffix ) {
			$param_key .= "_{$suffix}";
		}

		$namespace = "loop_filter-{$normalized_target_loop}";

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state from GET form submit.
		if ( ! isset( $_GET[ $namespace ] ) || ! is_array( $_GET[ $namespace ] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$filter_params = wp_unslash( $_GET[ $namespace ] );

		if ( ! isset( $filter_params[ $param_key ] ) ) {
			return null;
		}

		$value = $filter_params[ $param_key ];

		if ( is_array( $value ) ) {
			$sanitized = array_map( 'sanitize_text_field', $value );

			return array_values(
				array_filter(
					$sanitized,
					static function ( $item ) {
						return '' !== $item;
					}
				)
			);
		}

		if ( is_string( $value ) && '' !== $value ) {
			return sanitize_text_field( $value );
		}

		return null;
	}

	/**
	 * Whether one option value matches the current query-string filter value.
	 *
	 * @since ??
	 *
	 * @param string|array|null $query_value   Query-string value for the control.
	 * @param string            $option_value  Option value to compare.
	 *
	 * @return bool
	 */
	public static function is_query_option_selected( $query_value, string $option_value ): bool {
		if ( is_array( $query_value ) ) {
			return in_array( $option_value, $query_value, true );
		}

		if ( is_string( $query_value ) ) {
			return $query_value === $option_value;
		}

		return false;
	}

	/**
	 * Read all sanitized filter params for one loop from the current request.
	 *
	 * @since ??
	 *
	 * @param string $target_loop Target loop id.
	 *
	 * @return array<string, string|array<int, string>>
	 */
	public static function get_loop_filter_params_from_request( string $target_loop ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state from GET form submit.
		return self::get_loop_filter_params_from_params( $_GET, $target_loop );
	}

	/**
	 * Whether the current request includes post-filter params for any loop.
	 *
	 * Any non-empty params in a `loop_filter-{loopId}` namespace disable unified static CSS,
	 * including sort params, because loop item order can affect which styles apply.
	 *
	 * @since ??
	 *
	 * @return bool True when post-filter params are present in the request.
	 */
	public static function has_active_loop_filter_clauses_in_request(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state from GET form submit.
		if ( ! isset( $_GET ) || ! is_array( $_GET ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state from GET form submit.
		foreach ( array_keys( $_GET ) as $param_key ) {
			if ( ! is_string( $param_key ) || ! str_starts_with( $param_key, 'loop_filter-' ) ) {
				continue;
			}

			$target_loop = substr( $param_key, strlen( 'loop_filter-' ) );

			if ( '' === $target_loop ) {
				continue;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state from GET form submit.
			$filter_params = self::get_loop_filter_params_from_params( $_GET, $target_loop );

			if ( ! empty( $filter_params ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Read all sanitized filter params for one loop from a params array.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $params      Request params (for example `$_GET` or REST params).
	 * @param string              $target_loop Target loop id.
	 *
	 * @return array<string, string|array<int, string>>
	 */
	public static function get_loop_filter_params_from_params( array $params, string $target_loop ): array {
		$normalized_target_loop = trim( $target_loop );

		if ( '' === $normalized_target_loop || 'main_query' === $normalized_target_loop ) {
			return [];
		}

		$namespace = "loop_filter-{$normalized_target_loop}";

		if ( ! isset( $params[ $namespace ] ) || ! is_array( $params[ $namespace ] ) ) {
			return [];
		}

		$filter_params = $params[ $namespace ];
		$sanitized     = [];

		foreach ( $filter_params as $param_key => $param_value ) {
			$param_key = PostFilterCustomFieldUtils::sanitize_clause_key( (string) $param_key );

			if ( '' === $param_key || self::FILTER_RELATION_PARAM_KEY === $param_key ) {
				continue;
			}

			if ( 'multiple-order' === $param_key && is_array( $param_value ) ) {
				$multiple_order_rows = self::_sanitize_multiple_order_rows( $param_value );

				if ( ! empty( $multiple_order_rows ) ) {
					$sanitized[ $param_key ] = $multiple_order_rows;
				}

				continue;
			}

			if ( str_ends_with( $param_key, '_compare' ) ) {
				$compare = PostFilterCustomFieldUtils::sanitize_compare_param_value( $param_value );

				if ( null !== $compare ) {
					$sanitized[ $param_key ] = $compare;
				}

				continue;
			}

			if ( is_array( $param_value ) ) {
				$values = array_values(
					array_filter(
						array_map( 'sanitize_text_field', $param_value ),
						static function ( $item ) {
							return '' !== $item;
						}
					)
				);

				if ( ! empty( $values ) ) {
					$sanitized[ $param_key ] = $values;
				}

				continue;
			}

			if ( is_string( $param_value ) && '' !== $param_value ) {
				$sanitized[ $param_key ] = sanitize_text_field( $param_value );
			}
		}

		return $sanitized;
	}

	/**
	 * Read filter relation from the current request query string.
	 *
	 * @since ??
	 *
	 * @param string $target_loop Target loop id.
	 *
	 * @return string|null `and`, `or`, or null when unset.
	 */
	public static function get_loop_filter_relation_from_request( string $target_loop ): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state from GET form submit.
		return self::get_loop_filter_relation_from_params( $_GET, $target_loop );
	}

	/**
	 * Read filter relation from a params array.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $params      Request params (for example `$_GET` or REST params).
	 * @param string              $target_loop Target loop id.
	 *
	 * @return string|null `and`, `or`, or null when unset.
	 */
	public static function get_loop_filter_relation_from_params( array $params, string $target_loop ): ?string {
		$normalized_target_loop = trim( $target_loop );

		if ( '' === $normalized_target_loop || 'main_query' === $normalized_target_loop ) {
			return null;
		}

		$namespace = "loop_filter-{$normalized_target_loop}";

		if ( ! isset( $params[ $namespace ] ) || ! is_array( $params[ $namespace ] ) ) {
			return null;
		}

		$relation_value = $params[ $namespace ][ self::FILTER_RELATION_PARAM_KEY ] ?? null;
		$relation_value = is_string( $relation_value ) ? sanitize_text_field( $relation_value ) : null;

		if ( ! is_string( $relation_value ) ) {
			return null;
		}

		if ( 'or' === $relation_value ) {
			return 'or';
		}

		if ( 'and' === $relation_value ) {
			return 'and';
		}

		return null;
	}

	/**
	 * Resolve filter relation for one loop from request state or post-filter module attrs.
	 *
	 * @since ??
	 *
	 * @param string   $target_loop      Target loop id.
	 * @param int|null $store_instance   Optional block parser store instance.
	 *
	 * @return string `and` or `or`.
	 */
	public static function resolve_filter_relation_for_loop( string $target_loop, $store_instance = null ): string {
		$normalized_target_loop = trim( $target_loop );

		if ( '' === $normalized_target_loop ) {
			return 'and';
		}

		$relation_from_request = self::get_loop_filter_relation_from_request( $normalized_target_loop );

		if ( null !== $relation_from_request ) {
			return $relation_from_request;
		}

		$all_blocks = BlockParserStore::get_all( $store_instance );

		foreach ( $all_blocks as $block ) {
			$block_name = $block->blockName ?? '';

			if ( 'divi/post-filter' !== $block_name ) {
				continue;
			}

			$target_loop_value = $block->attrs['module']['advanced']['targetLoop']['desktop']['value'] ?? '';
			$target_loop_value = is_string( $target_loop_value ) ? trim( $target_loop_value ) : '';

			if ( $normalized_target_loop !== $target_loop_value ) {
				continue;
			}

			$filters_config = $block->attrs['module']['advanced']['filters']['desktop']['value'] ?? [];
			$relation       = is_array( $filters_config ) ? ( $filters_config['relation'] ?? 'and' ) : 'and';

			return 'or' === $relation ? 'or' : 'and';
		}

		return 'and';
	}

	/**
	 * Whether one query-string key belongs to a loop filter namespace.
	 *
	 * @since ??
	 *
	 * @param string $param_key        Query-string key.
	 * @param string $namespace_prefix Loop filter namespace prefix.
	 *
	 * @return bool
	 */
	public static function is_loop_filter_namespace_param_key( string $param_key, string $namespace_prefix ): bool {
		return $param_key === $namespace_prefix || str_starts_with( $param_key, "{$namespace_prefix}[" );
	}

	/**
	 * Build the GET form action URL for one post-filter form.
	 *
	 * Plain permalink pages identify content via query args such as `page_id`, `p`, or CPT
	 * rewrite tags. An empty form action drops those args on GET submit, so the action must
	 * preserve unrelated query args while omitting this loop's filter namespace and pagination.
	 *
	 * @since ??
	 *
	 * @param string $target_loop Parent target loop id.
	 *
	 * @return string Empty string when target loop is missing.
	 */
	public static function get_form_action_url( string $target_loop ): string {
		$normalized_target_loop = trim( $target_loop );

		if ( '' === $normalized_target_loop || 'main_query' === $normalized_target_loop ) {
			return '';
		}

		$url    = URLUtility::get_canonical_current_request_url();
		$parsed = wp_parse_url( $url );

		if ( empty( $parsed['query'] ) ) {
			return $url;
		}

		parse_str( $parsed['query'], $query_params );

		if ( empty( $query_params ) ) {
			return $url;
		}

		$namespace_prefix = "loop_filter-{$normalized_target_loop}";
		$keys_to_remove   = [];

		foreach ( array_keys( $query_params ) as $param_key ) {
			if ( self::is_loop_filter_namespace_param_key( (string) $param_key, $namespace_prefix ) ) {
				$keys_to_remove[] = $param_key;
			}
		}

		$keys_to_remove[] = $normalized_target_loop;

		return remove_query_arg( $keys_to_remove, $url );
	}

	/**
	 * Sanitize multiple-order rows from request params.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $rows Raw multiple-order rows.
	 *
	 * @return array<int, array{order_by: string, order: string}>
	 */
	private static function _sanitize_multiple_order_rows( array $rows ): array {
		$indexed_rows = [];

		foreach ( $rows as $row_index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$indexed_rows[ is_int( $row_index ) ? $row_index : (int) $row_index ] = $row;
		}

		ksort( $indexed_rows, SORT_NUMERIC );

		$sanitized_rows = [];

		foreach ( $indexed_rows as $row ) {
			$order_by = isset( $row['order_by'] ) ? PostFilterCustomFieldUtils::sanitize_clause_key( (string) $row['order_by'] ) : 'default';
			$order    = isset( $row['order'] ) ? sanitize_key( (string) $row['order'] ) : 'default';

			if ( 'default' === $order_by && 'default' === $order ) {
				continue;
			}

			$sanitized_rows[] = [
				'order_by' => $order_by,
				'order'    => $order,
			];

			if ( count( $sanitized_rows ) >= 5 ) {
				break;
			}
		}

		return $sanitized_rows;
	}

	/**
	 * Resolve the archive term slug that must stay selected for one taxonomy filter.
	 *
	 * When a post-filter-item targets a `current_page` loop on a matching taxonomy
	 * archive, the loop query already includes the queried term. This helper exposes
	 * that term slug so list controls can render it as checked and non-editable.
	 *
	 * @since ??
	 *
	 * @param string $field_option_key       Selected taxonomy slug from Field Option.
	 * @param string $target_loop_query_type Parent target loop query type.
	 *
	 * @return string Locked term slug, or empty string when not applicable.
	 */
	public static function resolve_locked_archive_term_slug_for_taxonomy_filter(
		string $field_option_key,
		string $target_loop_query_type
	): string {
		if ( 'current_page' !== $target_loop_query_type ) {
			return '';
		}

		if ( '' === $field_option_key || ! taxonomy_exists( $field_option_key ) ) {
			return '';
		}

		$queried_object = get_queried_object();

		if ( ! ( $queried_object instanceof \WP_Term ) ) {
			return '';
		}

		if ( $field_option_key !== $queried_object->taxonomy ) {
			return '';
		}

		$term_slug = is_string( $queried_object->slug ?? null ) ? $queried_object->slug : '';

		return '' !== $term_slug ? $term_slug : '';
	}

	/**
	 * Merge a locked archive term slug into hydrated query control state.
	 *
	 * @since ??
	 *
	 * @param string|array|null $query_value   Current query-string value for the control.
	 * @param string            $locked_term_slug Locked archive term slug.
	 * @param string            $field_type      Field control type.
	 *
	 * @return string|array|null
	 */
	public static function apply_locked_archive_term_to_query_value( $query_value, string $locked_term_slug, string $field_type ) {
		if ( '' === $locked_term_slug ) {
			return $query_value;
		}

		if ( 'checkbox' === $field_type ) {
			$selected_values = is_array( $query_value ) ? $query_value : [];

			if ( ! in_array( $locked_term_slug, $selected_values, true ) ) {
				$selected_values[] = $locked_term_slug;
			}

			return array_values( $selected_values );
		}

		if ( in_array( $field_type, [ 'radio', 'select' ], true ) ) {
			if ( null === $query_value || ( is_string( $query_value ) && '' === $query_value ) ) {
				return $locked_term_slug;
			}
		}

		return $query_value;
	}

	/**
	 * Restrict taxonomy list items to the locked archive term for single-choice controls.
	 *
	 * Radio and select filters on `current_page` taxonomy archives can only reflect
	 * the queried archive term, so other terms are omitted from the option list.
	 *
	 * @since ??
	 *
	 * @param array<int, array{value: string, label: string}> $list_items       Selectable list items.
	 * @param string                                          $locked_term_slug Locked archive term slug.
	 * @param string                                          $taxonomy_slug    Taxonomy slug from Field Option.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function filter_list_items_for_locked_archive_term(
		array $list_items,
		string $locked_term_slug,
		string $taxonomy_slug
	): array {
		if ( '' === $locked_term_slug ) {
			return $list_items;
		}

		foreach ( $list_items as $list_item ) {
			$option_value = $list_item['value'] ?? '';

			if ( $locked_term_slug === $option_value ) {
				return [
					[
						'value' => $option_value,
						'label' => $list_item['label'] ?? $option_value,
					],
				];
			}
		}

		$term = get_term_by( 'slug', $locked_term_slug, $taxonomy_slug );

		if ( $term instanceof \WP_Term && ! is_wp_error( $term ) ) {
			return [
				[
					'value' => $term->slug,
					'label' => $term->name,
				],
			];
		}

		return [
			[
				'value' => $locked_term_slug,
				'label' => $locked_term_slug,
			],
		];
	}
}
