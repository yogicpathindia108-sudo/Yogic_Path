<?php
/**
 * Post Filter Item custom field value options discovery and caching.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostFilterItem;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentACFUtils;

/**
 * Discovers distinct post meta values for post-filter-item list controls and caches results.
 *
 * @since ??
 */
class PostFilterCustomFieldValueOptions {
	/**
	 * Transient key prefix for cached value option payloads.
	 *
	 * @var string
	 */
	public const TRANSIENT_PREFIX = 'divi_post_filter_item_custom_field_values_';

	/**
	 * Transient key prefix for cached post date column value payloads.
	 *
	 * @var string
	 */
	public const POST_DATE_TRANSIENT_PREFIX = 'divi_post_filter_item_post_date_values_';

	/**
	 * Transient TTL in seconds (1 day).
	 *
	 * @var int
	 */
	public const TRANSIENT_TTL = DAY_IN_SECONDS;

	/**
	 * Maximum unique values returned for automatic/manual generation.
	 *
	 * @var int
	 */
	public const MAX_UNIQUE_VALUES = 500;

	/**
	 * Unique value count threshold for performance warning.
	 *
	 * @var int
	 */
	public const UNIQUE_COUNT_WARNING_THRESHOLD = 10;

	/**
	 * Record count threshold for performance warning.
	 *
	 * @var int
	 */
	public const RECORD_COUNT_WARNING_THRESHOLD = 50;

	/**
	 * Register cache invalidation hooks.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		if ( has_action( 'updated_post_meta', [ self::class, 'flush_cache_on_meta_change' ] ) ) {
			return;
		}

		add_action( 'added_post_meta', [ self::class, 'flush_cache_on_meta_change' ], 10, 3 );
		add_action( 'updated_post_meta', [ self::class, 'flush_cache_on_meta_change' ], 10, 3 );
		add_action( 'deleted_post_meta', [ self::class, 'flush_cache_on_meta_change' ], 10, 3 );
		add_action( 'transition_post_status', [ self::class, 'flush_cache_on_post_status_change' ], 10, 3 );
		add_action( 'save_post', [ self::class, 'flush_cache_on_post_save' ], 99, 2 );
	}

	/**
	 * Flush cached value options when post meta is added, updated, or deleted.
	 *
	 * @since ??
	 *
	 * @param int|array<int> $meta_id   Meta row ID or list of deleted meta row IDs.
	 * @param int            $object_id Post ID.
	 * @param string         $meta_key  Meta key.
	 *
	 * @return void
	 */
	public static function flush_cache_on_meta_change( $meta_id, int $object_id, string $meta_key ): void {
		unset( $meta_id );

		$meta_key = self::normalize_cache_meta_key( $meta_key );

		if ( '' === $meta_key || 0 >= $object_id ) {
			return;
		}

		$post_type = get_post_type( $object_id );

		if ( is_string( $post_type ) && '' !== $post_type ) {
			self::clear_cache( $post_type, $meta_key );
		}

		self::flush_cache_for_meta_key( $meta_key );
	}

	/**
	 * Flush cached value options when a post enters or leaves the publish status.
	 *
	 * Published posts are the only rows included in automatic discovery queries, so
	 * status transitions can change the distinct value set without a meta hook firing.
	 *
	 * @since ??
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 *
	 * @return void
	 */
	public static function flush_cache_on_post_status_change( string $new_status, string $old_status, $post ): void {
		if ( $new_status === $old_status ) {
			return;
		}

		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return;
		}

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		self::flush_cache_for_post_meta_keys( (int) $post->ID );

		$post_type = get_post_type( $post );

		if ( is_string( $post_type ) && '' !== $post_type ) {
			self::flush_post_date_column_caches( $post_type );
		}
	}

	/**
	 * Flush cached value options after a post is saved.
	 *
	 * This is a fallback for admin CPT edit screens where meta can be saved without
	 * firing the post-meta hooks expected during REST/AJAX requests.
	 *
	 * @since ??
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post object.
	 *
	 * @return void
	 */
	public static function flush_cache_on_post_save( int $post_id, $post = null ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! $post instanceof \WP_Post ) {
			$post = get_post( $post_id );
		}

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		self::flush_cache_for_post_meta_keys( $post_id );

		$post_type = $post->post_type;

		if ( is_string( $post_type ) && '' !== $post_type ) {
			self::flush_post_date_column_caches( $post_type );
		}
	}

	/**
	 * Build the REST payload for custom field value options.
	 *
	 * @since ??
	 *
	 * @param string $post_type        Effective post type slug.
	 * @param string $option_key       Selected field option key.
	 * @param string $custom_field_key Manual meta key when manual option is selected.
	 * @param bool   $bypass_cache     Whether to bypass and refresh the transient cache.
	 * @param string $field_value_type Selected field value type used to format discovered post date values.
	 * @param string $label_date_format PHP date format string for option labels.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_value_options_response(
		string $post_type,
		string $option_key,
		string $custom_field_key = '',
		bool $bypass_cache = false,
		string $field_value_type = 'date',
		string $label_date_format = ''
	): array {
		$post_type = self::normalize_discovery_post_type( $post_type );

		if ( PostFilterCustomFieldUtils::is_post_date_field_value_option( $option_key ) ) {
			$payload = self::get_cached_post_date_payload( $post_type, $option_key, $field_value_type, $bypass_cache );
			$labels  = PostFilterCustomFieldUtils::apply_label_date_format_to_value_option_labels(
				$payload['values'],
				$payload['labels'],
				$label_date_format,
				$field_value_type
			);

			return array_merge(
				[
					'values'       => $payload['values'],
					'labels'       => $labels,
					'record_count' => $payload['record_count'],
					'unique_count' => $payload['unique_count'],
				],
				[
					'is_acf_group' => false,
					'warnings'     => self::build_warnings( $payload['record_count'], $payload['unique_count'] ),
				]
			);
		}

		$meta_key = PostFilterCustomFieldUtils::resolve_meta_key( $option_key, $custom_field_key );

		if ( '' === $meta_key ) {
			return self::_empty_response();
		}

		if ( self::is_acf_group_field( $meta_key ) ) {
			return [
				'values'        => [],
				'record_count'  => 0,
				'unique_count'  => 0,
				'is_acf_group'  => true,
				'warnings'      => [
					esc_html__(
						'ACF group fields cannot be used for automatic option generation. Select a leaf sub-field meta key instead.',
						'et_builder_5'
					),
				],
			];
		}

		$payload = self::get_cached_payload( $post_type, $meta_key, $bypass_cache );
		$labels  = [];

		foreach ( self::values_to_list_items( $payload['values'] ) as $list_item ) {
			$labels[ $list_item['value'] ] = $list_item['label'];
		}

		$labels = PostFilterCustomFieldUtils::apply_label_date_format_to_value_option_labels(
			$payload['values'],
			$labels,
			$label_date_format,
			$field_value_type
		);

		return array_merge(
			$payload,
			[
				'labels'       => $labels,
				'is_acf_group' => false,
				'warnings'     => self::build_warnings( $payload['record_count'], $payload['unique_count'] ),
			]
		);
	}

	/**
	 * Resolve list items from attrs, including automatic option discovery.
	 *
	 * @since ??
	 *
	 * @param string $field_type Field control type.
	 * @param array  $attrs      Saved module attrs.
	 * @param string $post_type  Effective post type slug.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function get_list_items( string $field_type, array $attrs, string $post_type ): array {
		if ( 'manual' === self::resolve_options_method( $field_type, $attrs ) ) {
			return PostFilterCustomFieldUtils::get_list_items_from_attrs( $field_type, $attrs );
		}

		$field_inner_content = $attrs['field']['innerContent']['desktop']['value'] ?? [];
		$option_key          = is_array( $field_inner_content ) ? (string) ( $field_inner_content['option'] ?? '' ) : '';
		$custom_field_key    = is_array( $field_inner_content ) ? (string) ( $field_inner_content['customFieldKey'] ?? '' ) : '';
		$field_value_type    = is_array( $field_inner_content )
			? PostFilterCustomFieldUtils::resolve_effective_field_value_type( $field_inner_content )
			: 'date';
		$post_type           = self::normalize_discovery_post_type( $post_type );

		if ( PostFilterCustomFieldUtils::is_post_date_field_value_option( $option_key ) ) {
			$payload    = self::get_cached_post_date_payload( $post_type, $option_key, $field_value_type, false );
			$list_items = self::post_date_values_to_list_items( $payload['values'], $payload['labels'] );

			return PostFilterCustomFieldUtils::apply_date_option_label_formatting_to_list_items( $list_items, $attrs );
		}

		$meta_key = PostFilterCustomFieldUtils::resolve_meta_key( $option_key, $custom_field_key );

		if ( '' === $meta_key ) {
			return [];
		}

		if ( self::is_acf_group_field( $meta_key ) ) {
			return [];
		}

		$payload = self::get_cached_payload( $post_type, $meta_key, false );

		return PostFilterCustomFieldUtils::apply_date_option_label_formatting_to_list_items(
			self::values_to_list_items( $payload['values'] ),
			$attrs
		);
	}

	/**
	 * Clear cached value options for one post type and meta key.
	 *
	 * @since ??
	 *
	 * @param string $post_type Post type slug.
	 * @param string $meta_key  Meta key.
	 *
	 * @return bool
	 */
	public static function clear_cache( string $post_type, string $meta_key ): bool {
		$post_type = sanitize_key( $post_type );
		$meta_key  = self::normalize_cache_meta_key( $meta_key );

		if ( '' === $post_type || '' === $meta_key ) {
			return false;
		}

		return delete_transient( self::get_transient_key( $post_type, $meta_key ) );
	}

	/**
	 * Flush all cached payloads that match a meta key across post types.
	 *
	 * @since ??
	 *
	 * @param string $meta_key Meta key.
	 *
	 * @return void
	 */
	public static function flush_cache_for_meta_key( string $meta_key ): void {
		global $wpdb;

		$meta_key = self::normalize_cache_meta_key( $meta_key );

		if ( '' === $meta_key ) {
			return;
		}

		$like = $wpdb->esc_like( self::TRANSIENT_PREFIX ) . '%' . $wpdb->esc_like( '_' . $meta_key );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transient discovery requires direct query.
		$option_names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_' . $like
			)
		);

		if ( ! is_array( $option_names ) || empty( $option_names ) ) {
			return;
		}

		foreach ( $option_names as $option_name ) {
			if ( ! is_string( $option_name ) || 0 !== strpos( $option_name, '_transient_' ) ) {
				continue;
			}

			$transient = substr( $option_name, strlen( '_transient_' ) );

			if ( '' !== $transient ) {
				delete_transient( $transient );
			}
		}
	}

	/**
	 * Flush cached payloads for every meta key stored on one post.
	 *
	 * @since ??
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public static function flush_cache_for_post_meta_keys( int $post_id ): void {
		if ( 0 >= $post_id ) {
			return;
		}

		$post_type = get_post_type( $post_id );

		if ( ! is_string( $post_type ) || '' === $post_type ) {
			return;
		}

		$meta = get_post_meta( $post_id );

		if ( ! is_array( $meta ) || empty( $meta ) ) {
			return;
		}

		foreach ( array_keys( $meta ) as $meta_key ) {
			if ( ! is_string( $meta_key ) || '' === $meta_key ) {
				continue;
			}

			$normalized_meta_key = self::normalize_cache_meta_key( $meta_key );

			if ( '' === $normalized_meta_key ) {
				continue;
			}

			self::clear_cache( $post_type, $normalized_meta_key );
			self::flush_cache_for_meta_key( $normalized_meta_key );
		}
	}

	/**
	 * Normalize a meta key for cache storage and lookup.
	 *
	 * @since ??
	 *
	 * @param string $meta_key Candidate meta key.
	 *
	 * @return string Normalized meta key slug.
	 */
	public static function normalize_cache_meta_key( string $meta_key ): string {
		return sanitize_key( sanitize_text_field( $meta_key ) );
	}

	/**
	 * Whether the resolved meta key refers to an ACF group field.
	 *
	 * @since ??
	 *
	 * @param string $meta_key Resolved meta key.
	 *
	 * @return bool
	 */
	public static function is_acf_group_field( string $meta_key ): bool {
		$meta_key = sanitize_key( $meta_key );

		if ( '' === $meta_key || ! DynamicContentACFUtils::is_acf_active() || ! function_exists( 'acf_get_field' ) ) {
			return false;
		}

		$field = acf_get_field( $meta_key );

		return is_array( $field ) && 'group' === ( $field['type'] ?? '' );
	}

	/**
	 * Resolve the options method for custom field list controls.
	 *
	 * @since ??
	 *
	 * @param string $field_type Field control type.
	 * @param array  $attrs      Saved module attrs.
	 *
	 * @return string `automatic` or `manual`.
	 */
	public static function resolve_options_method( string $field_type, array $attrs ): string {
		$field_inner_content = $attrs['field']['innerContent']['desktop']['value'] ?? [];
		$options_method      = is_array( $field_inner_content ) ? (string) ( $field_inner_content['optionsMethod'] ?? '' ) : '';

		if ( 'manual' === $options_method || 'automatic' === $options_method ) {
			return $options_method;
		}

		if ( self::has_manual_options_configured( $field_type, $attrs ) ) {
			return 'manual';
		}

		return 'automatic';
	}

	/**
	 * Normalize post type slug used for custom field value discovery.
	 *
	 * @since ??
	 *
	 * @param string $post_type Candidate post type slug.
	 *
	 * @return string Effective post type slug.
	 */
	public static function normalize_discovery_post_type( string $post_type ): string {
		$post_type = sanitize_key( $post_type );

		if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
			return 'post';
		}

		return $post_type;
	}

	/**
	 * Build performance warnings for large option sets.
	 *
	 * @since ??
	 *
	 * @param int $record_count Total meta rows scanned.
	 * @param int $unique_count Distinct values count.
	 *
	 * @return array<int, string>
	 */
	public static function build_warnings( int $record_count, int $unique_count ): array {
		$warnings = [];

		if ( self::UNIQUE_COUNT_WARNING_THRESHOLD < $unique_count ) {
			$warnings[] = esc_html__(
				'More than 10 unique values were found. For performance reasons, the Manual options method is advised.',
				'et_builder_5'
			);
		}

		if ( self::RECORD_COUNT_WARNING_THRESHOLD < $record_count ) {
			$warnings[] = esc_html__(
				'More than 50 records were found for this custom field. This may cause performance issues; the Manual options method is advised.',
				'et_builder_5'
			);
		}

		return $warnings;
	}

	/**
	 * Convert discovered values into list item rows.
	 *
	 * @since ??
	 *
	 * @param array<int, string> $values Distinct meta values.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function values_to_list_items( array $values ): array {
		$list_items = [];

		foreach ( $values as $value ) {
			if ( ! is_string( $value ) ) {
				continue;
			}

			$label = trim( wp_strip_all_tags( $value ) );

			if ( '' === $label ) {
				continue;
			}

			$list_items[] = [
				'value' => $label,
				'label' => $label,
			];
		}

		return $list_items;
	}

	/**
	 * Get transient key for one post type and meta key pair.
	 *
	 * @since ??
	 *
	 * @param string $post_type Post type slug.
	 * @param string $meta_key  Meta key.
	 *
	 * @return string
	 */
	public static function get_transient_key( string $post_type, string $meta_key ): string {
		return self::TRANSIENT_PREFIX . sanitize_key( $post_type ) . '_' . self::normalize_cache_meta_key( $meta_key );
	}

	/**
	 * Whether manual sortable-list options are configured for the field type.
	 *
	 * @since ??
	 *
	 * @param string $field_type Field control type.
	 * @param array  $attrs      Saved module attrs.
	 *
	 * @return bool
	 */
	private static function has_manual_options_configured( string $field_type, array $attrs ): bool {
		return ! empty( PostFilterCustomFieldUtils::get_list_items_from_attrs( $field_type, $attrs ) );
	}

	/**
	 * Get cached payload or fetch and store it.
	 *
	 * @since ??
	 *
	 * @param string $post_type    Post type slug.
	 * @param string $meta_key     Meta key.
	 * @param bool   $bypass_cache Whether to bypass cached data.
	 *
	 * @return array{values: array<int, string>, record_count: int, unique_count: int}
	 */
	private static function get_cached_payload( string $post_type, string $meta_key, bool $bypass_cache ): array {
		$transient_key = self::get_transient_key( $post_type, $meta_key );

		if ( ! $bypass_cache ) {
			$cached_payload = get_transient( $transient_key );

			if ( is_array( $cached_payload ) ) {
				return [
					'values'       => array_values( array_map( 'strval', $cached_payload['values'] ?? [] ) ),
					'record_count' => (int) ( $cached_payload['record_count'] ?? 0 ),
					'unique_count' => (int) ( $cached_payload['unique_count'] ?? 0 ),
				];
			}
		}

		$payload = self::fetch_distinct_values( $post_type, $meta_key );

		set_transient( $transient_key, $payload, self::TRANSIENT_TTL );

		return $payload;
	}

	/**
	 * Fetch distinct meta values for one post type and meta key.
	 *
	 * @since ??
	 *
	 * @param string $post_type Post type slug.
	 * @param string $meta_key  Meta key.
	 *
	 * @return array{values: array<int, string>, record_count: int, unique_count: int}
	 */
	private static function fetch_distinct_values( string $post_type, string $meta_key ): array {
		global $wpdb;

		$post_type = self::normalize_discovery_post_type( $post_type );
		$meta_key  = sanitize_text_field( $meta_key );

		if ( '' === $meta_key ) {
			return self::_empty_payload();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Distinct meta discovery requires direct query.
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pm.meta_value
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s
				AND p.post_type = %s
				AND p.post_status = 'publish'
				AND pm.meta_value != ''",
				$meta_key,
				$post_type
			)
		);

		if ( ! is_array( $rows ) ) {
			return self::_empty_payload();
		}

		$record_count   = count( $rows );
		$unique_values  = [];
		$seen_values    = [];

		foreach ( $rows as $raw_value ) {
			if ( ! is_string( $raw_value ) && ! is_numeric( $raw_value ) ) {
				continue;
			}

			$value = trim( wp_strip_all_tags( (string) $raw_value ) );

			if ( '' === $value ) {
				continue;
			}

			$normalized_key = strtolower( $value );

			if ( isset( $seen_values[ $normalized_key ] ) ) {
				continue;
			}

			$seen_values[ $normalized_key ] = true;
			$unique_values[]                = $value;

			if ( self::MAX_UNIQUE_VALUES <= count( $unique_values ) ) {
				break;
			}
		}

		sort( $unique_values, SORT_NATURAL | SORT_FLAG_CASE );

		return [
			'values'       => $unique_values,
			'record_count' => $record_count,
			'unique_count' => count( $unique_values ),
		];
	}

	/**
	 * Convert discovered post date values into list item rows.
	 *
	 * @since ??
	 *
	 * @param array<int, string>        $values Distinct filter values.
	 * @param array<string, string>     $labels Labels keyed by filter value.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function post_date_values_to_list_items( array $values, array $labels = [] ): array {
		$list_items = [];

		foreach ( $values as $value ) {
			if ( ! is_string( $value ) ) {
				continue;
			}

			$label = isset( $labels[ $value ] ) ? (string) $labels[ $value ] : $value;

			if ( '' === trim( $label ) ) {
				continue;
			}

			$list_items[] = [
				'value' => $value,
				'label' => $label,
			];
		}

		return $list_items;
	}

	/**
	 * Flush cached post date column payloads for one post type.
	 *
	 * @since ??
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return void
	 */
	public static function flush_post_date_column_caches( string $post_type ): void {
		global $wpdb;

		$post_type = sanitize_key( $post_type );

		if ( '' === $post_type ) {
			return;
		}

		$like = $wpdb->esc_like( self::POST_DATE_TRANSIENT_PREFIX ) . $wpdb->esc_like( $post_type ) . '_%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transient discovery requires direct query.
		$option_names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_' . $like
			)
		);

		if ( ! is_array( $option_names ) || empty( $option_names ) ) {
			return;
		}

		foreach ( $option_names as $option_name ) {
			if ( ! is_string( $option_name ) || 0 !== strpos( $option_name, '_transient_' ) ) {
				continue;
			}

			$transient = substr( $option_name, strlen( '_transient_' ) );

			if ( '' !== $transient ) {
				delete_transient( $transient );
			}
		}
	}

	/**
	 * Get transient key for one post date column payload.
	 *
	 * @since ??
	 *
	 * @param string $post_type        Post type slug.
	 * @param string $option_key       Post date field option key.
	 * @param string $field_value_type Selected field value type.
	 *
	 * @return string
	 */
	private static function get_post_date_transient_key( string $post_type, string $option_key, string $field_value_type ): string {
		return self::POST_DATE_TRANSIENT_PREFIX . sanitize_key( $post_type ) . '_' . sanitize_key( $option_key ) . '_' . sanitize_key( $field_value_type );
	}

	/**
	 * Get cached post date payload or fetch and store it.
	 *
	 * @since ??
	 *
	 * @param string $post_type        Post type slug.
	 * @param string $option_key       Post date field option key.
	 * @param string $field_value_type Selected field value type.
	 * @param bool   $bypass_cache     Whether to bypass cached data.
	 *
	 * @return array{values: array<int, string>, labels: array<string, string>, record_count: int, unique_count: int}
	 */
	private static function get_cached_post_date_payload(
		string $post_type,
		string $option_key,
		string $field_value_type,
		bool $bypass_cache
	): array {
		$transient_key = self::get_post_date_transient_key( $post_type, $option_key, $field_value_type );

		if ( ! $bypass_cache ) {
			$cached_payload = get_transient( $transient_key );

			if ( is_array( $cached_payload ) ) {
				return [
					'values'       => array_values( array_map( 'strval', $cached_payload['values'] ?? [] ) ),
					'labels'       => is_array( $cached_payload['labels'] ?? null ) ? $cached_payload['labels'] : [],
					'record_count' => (int) ( $cached_payload['record_count'] ?? 0 ),
					'unique_count' => (int) ( $cached_payload['unique_count'] ?? 0 ),
				];
			}
		}

		$payload = self::fetch_distinct_post_column_values( $post_type, $option_key, $field_value_type );

		set_transient( $transient_key, $payload, self::TRANSIENT_TTL );

		return $payload;
	}

	/**
	 * Fetch distinct published or modified dates for one post type.
	 *
	 * @since ??
	 *
	 * @param string $post_type        Post type slug.
	 * @param string $option_key       Post date field option key.
	 * @param string $field_value_type Selected field value type.
	 *
	 * @return array{values: array<int, string>, labels: array<string, string>, record_count: int, unique_count: int}
	 */
	private static function fetch_distinct_post_column_values( string $post_type, string $option_key, string $field_value_type ): array {
		global $wpdb;

		$post_type = self::normalize_discovery_post_type( $post_type );
		$column    = PostFilterCustomFieldUtils::is_published_date_field_value_option( $option_key ) ? 'post_date' : 'post_modified';

		if ( ! in_array( $column, [ 'post_date', 'post_modified' ], true ) ) {
			return self::_empty_post_date_payload();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Distinct post column discovery requires direct query.
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT {$column}
				FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status = 'publish'",
				$post_type
			)
		);

		if ( ! is_array( $rows ) ) {
			return self::_empty_post_date_payload();
		}

		$record_count = count( $rows );
		$values       = [];
		$labels       = [];
		$seen_values  = [];

		foreach ( $rows as $raw_value ) {
			if ( ! is_string( $raw_value ) && ! is_numeric( $raw_value ) ) {
				continue;
			}

			$formatted_row = self::format_post_column_option_row( (string) $raw_value, $field_value_type );

			if ( null === $formatted_row ) {
				continue;
			}

			$value = $formatted_row['value'];
			$label = $formatted_row['label'];

			if ( '' === $value || '' === $label ) {
				continue;
			}

			$normalized_key = strtolower( $value );

			if ( isset( $seen_values[ $normalized_key ] ) ) {
				continue;
			}

			$seen_values[ $normalized_key ] = true;
			$values[]                       = $value;
			$labels[ $value ]               = $label;

			if ( self::MAX_UNIQUE_VALUES <= count( $values ) ) {
				break;
			}
		}

		sort( $values, SORT_NATURAL | SORT_FLAG_CASE );

		return [
			'values'       => $values,
			'labels'       => $labels,
			'record_count' => $record_count,
			'unique_count' => count( $values ),
		];
	}

	/**
	 * Format one post column datetime into filter value and display label.
	 *
	 * @since ??
	 *
	 * @param string $mysql_datetime   Raw MySQL datetime from the posts table.
	 * @param string $field_value_type Selected field value type.
	 *
	 * @return array{value: string, label: string}|null
	 */
	private static function format_post_column_option_row( string $mysql_datetime, string $field_value_type ): ?array {
		$mysql_datetime = trim( $mysql_datetime );

		if ( '' === $mysql_datetime ) {
			return null;
		}

		$timestamp = mysql2date( 'U', $mysql_datetime, false );

		if ( ! is_numeric( $timestamp ) ) {
			return null;
		}

		$timestamp = (int) $timestamp;

		if ( 'date-time' === $field_value_type ) {
			$value = wp_date( 'Y-m-d\TH:i', $timestamp );
			$label = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
		} else {
			$value = wp_date( 'Y-m-d', $timestamp );
			$label = wp_date( get_option( 'date_format' ), $timestamp );
		}

		if ( '' === $value || '' === $label ) {
			return null;
		}

		return [
			'value' => $value,
			'label' => $label,
		];
	}

	/**
	 * Empty post date cached payload shape.
	 *
	 * @since ??
	 *
	 * @return array{values: array<int, string>, labels: array<string, string>, record_count: int, unique_count: int}
	 */
	private static function _empty_post_date_payload(): array {
		return [
			'values'       => [],
			'labels'       => [],
			'record_count' => 0,
			'unique_count' => 0,
		];
	}

	/**
	 * Empty REST response payload.
	 *
	 * @since ??
	 *
	 * @return array<string, mixed>
	 */
	private static function _empty_response(): array {
		return array_merge(
			self::_empty_payload(),
			[
				'is_acf_group' => false,
				'warnings'     => [],
			]
		);
	}

	/**
	 * Empty cached payload shape.
	 *
	 * @since ??
	 *
	 * @return array{values: array<int, string>, record_count: int, unique_count: int}
	 */
	private static function _empty_payload(): array {
		return [
			'values'       => [],
			'record_count' => 0,
			'unique_count' => 0,
		];
	}
}
