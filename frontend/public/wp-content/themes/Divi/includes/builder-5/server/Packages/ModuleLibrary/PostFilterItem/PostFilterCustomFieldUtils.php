<?php
/**
 * Post Filter Item custom field utilities.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PostFilterItem;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentACFUtils;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentOptions;
use WP_Taxonomy;

/**
 * Shared helpers for post-filter-item custom field discovery, rendering, and query refinement.
 *
 * @since ??
 */
class PostFilterCustomFieldUtils {
	/**
	 * Option key prefix for custom field selections.
	 *
	 * @var string
	 */
	public const OPTION_PREFIX = 'custom_field:';

	/**
	 * Option key for manual meta key entry.
	 *
	 * @var string
	 */
	public const MANUAL_OPTION = 'custom_field:manual';

	/**
	 * Field value option key for term count filters on terms loops.
	 *
	 * @var string
	 */
	public const TERM_COUNT_FIELD_VALUE_OPTION = 'loop_term_count';

	/**
	 * Field value option key for menu order on menus loops.
	 *
	 * @var string
	 */
	public const MENU_ORDER_FIELD_VALUE_OPTION = 'loop_menu_order';

	/**
	 * Field value option key for published date on post-type loops.
	 *
	 * @var string
	 */
	public const PUBLISHED_DATE_FIELD_VALUE_OPTION = 'loop_post_date';

	/**
	 * Field value option key for last modified date on post-type loops.
	 *
	 * @var string
	 */
	public const LAST_MODIFIED_DATE_FIELD_VALUE_OPTION = 'loop_post_modified_date';

	/**
	 * Default PHP date format for list-control option labels on date-related field value types.
	 *
	 * @var string
	 */
	public const DEFAULT_LABEL_DATE_FORMAT = 'M j, Y';

	/**
	 * Menu-loop search target: menu item link text.
	 *
	 * @var string
	 */
	public const SEARCH_TARGET_MENU_TEXT = 'menu_text';

	/**
	 * Menu-loop search target: title attribute.
	 *
	 * @var string
	 */
	public const SEARCH_TARGET_MENU_ATTR_TITLE = 'attr_title';

	/**
	 * Menu-loop search target: CSS classes.
	 *
	 * @var string
	 */
	public const SEARCH_TARGET_MENU_CLASSES = 'classes';

	/**
	 * Menu-loop search target: link relationship (XFN).
	 *
	 * @var string
	 */
	public const SEARCH_TARGET_MENU_XFN = 'xfn';

	/**
	 * Menu-loop search target: menu description.
	 *
	 * @var string
	 */
	public const SEARCH_TARGET_MENU_DESCRIPTION = 'description';

	/**
	 * Query param prefix for number field controls.
	 *
	 * @var string
	 */
	public const NUMBER_FIELD_PARAM_PREFIX = 'number_';

	/**
	 * Query param prefix for date field controls.
	 *
	 * @var string
	 */
	public const DATE_FIELD_PARAM_PREFIX = 'date_';

	/**
	 * Query param prefix for date-time field controls.
	 *
	 * @var string
	 */
	public const DATE_TIME_FIELD_PARAM_PREFIX = 'date-time_';

	/**
	 * Query param prefix for time field controls.
	 *
	 * @var string
	 */
	public const TIME_FIELD_PARAM_PREFIX = 'time_';

	/**
	 * Maps post-filter date field types to ACF field types.
	 *
	 * @var array<string, string>
	 */
	private const DATE_FIELD_TYPE_ACF_MAP = [
		'date'      => 'date_picker',
		'date-time' => 'date_time_picker',
		'time'      => 'time_picker',
	];

	/**
	 * ACF field types excluded from text-based field value type filtering.
	 *
	 * @var array<int, string>
	 */
	private const TEXT_FILTER_EXCLUDED_ACF_FIELD_TYPES = [
		'number',
		'range',
		'date_picker',
		'date_time_picker',
		'time_picker',
	];

	/**
	 * ACF field types included in number-based field value type filtering.
	 *
	 * @var array<int, string>
	 */
	private const NUMBER_FILTER_ACF_FIELD_TYPES = [
		'number',
		'range',
	];

	/**
	 * Maps post-filter date field types to meta_query type values.
	 *
	 * @var array<string, string>
	 */
	private const DATE_FIELD_TYPE_META_QUERY_MAP = [
		'date'      => 'DATE',
		'date-time' => 'DATETIME',
		'time'      => 'TIME',
	];

	/**
	 * Query param prefix for select custom-field controls.
	 *
	 * @var string
	 */
	public const SELECT_FIELD_PARAM_PREFIX = 'select_';

	/**
	 * Query param prefix for radio custom-field controls.
	 *
	 * @var string
	 */
	public const RADIO_FIELD_PARAM_PREFIX = 'radio_';

	/**
	 * Query param prefix for search field controls.
	 *
	 * @var string
	 */
	public const TEXT_FIELD_PARAM_PREFIX = 'text_';

	/**
	 * Search target value for WordPress default title and content search.
	 *
	 * @var string
	 */
	public const SEARCH_TARGET_POST_CONTENT = 'post_content';

	/**
	 * User-loop search target: display name and bio/description.
	 *
	 * @var string
	 */
	public const SEARCH_TARGET_USER_DISPLAY_NAME_AND_BIO = 'display_name_and_bio';

	/**
	 * User-loop search target: username (`user_login`).
	 *
	 * @var string
	 */
	public const SEARCH_TARGET_USER_USERNAME = 'username';

	/**
	 * User-loop search target: email (`user_email`).
	 *
	 * @var string
	 */
	public const SEARCH_TARGET_USER_EMAIL = 'email';

	/**
	 * Check whether a field option key refers to a custom field.
	 *
	 * @since ??
	 *
	 * @param string $option_key Field option key.
	 *
	 * @return bool
	 */
	public static function is_custom_field_option( string $option_key ): bool {
		return str_starts_with( $option_key, self::OPTION_PREFIX );
	}

	/**
	 * Check whether a field value option key targets term count on terms loops.
	 *
	 * @since ??
	 *
	 * @param string $option_key Field value option key.
	 *
	 * @return bool
	 */
	public static function is_term_count_field_value_option( string $option_key ): bool {
		return self::TERM_COUNT_FIELD_VALUE_OPTION === $option_key;
	}

	/**
	 * Check whether a field value option key targets menu order on menus loops.
	 *
	 * @since ??
	 *
	 * @param string $option_key Field value option key.
	 *
	 * @return bool
	 */
	public static function is_menu_order_field_value_option( string $option_key ): bool {
		return self::MENU_ORDER_FIELD_VALUE_OPTION === $option_key;
	}

	/**
	 * Check whether a field value option key targets published date on post-type loops.
	 *
	 * @since ??
	 *
	 * @param string $option_key Field value option key.
	 *
	 * @return bool
	 */
	public static function is_published_date_field_value_option( string $option_key ): bool {
		return self::PUBLISHED_DATE_FIELD_VALUE_OPTION === $option_key;
	}

	/**
	 * Check whether a field value option key targets last modified date on post-type loops.
	 *
	 * @since ??
	 *
	 * @param string $option_key Field value option key.
	 *
	 * @return bool
	 */
	public static function is_last_modified_date_field_value_option( string $option_key ): bool {
		return self::LAST_MODIFIED_DATE_FIELD_VALUE_OPTION === $option_key;
	}

	/**
	 * Check whether a field value option key targets a post date column.
	 *
	 * @since ??
	 *
	 * @param string $option_key Field value option key.
	 *
	 * @return bool
	 */
	public static function is_post_date_field_value_option( string $option_key ): bool {
		return self::is_published_date_field_value_option( $option_key )
			|| self::is_last_modified_date_field_value_option( $option_key );
	}

	/**
	 * Resolve the effective field value option key for number controls on dedicated loop query types.
	 *
	 * Menus loops only support menu order and terms loops only support term count. When the option
	 * attribute is unset, default to the contextual field value so companion hidden inputs still submit.
	 *
	 * @since ??
	 *
	 * @param string $option_key            Selected field option key.
	 * @param string $target_loop_query_type  Parent target loop query type.
	 *
	 * @return string
	 */
	public static function resolve_number_field_value_option_key( string $option_key, string $target_loop_query_type ): string {
		if ( '' !== $option_key ) {
			return $option_key;
		}

		if ( self::is_menus_loop_query_type( $target_loop_query_type ) ) {
			return self::MENU_ORDER_FIELD_VALUE_OPTION;
		}

		if ( in_array( $target_loop_query_type, [ 'post_taxonomies', 'terms' ], true ) ) {
			return self::TERM_COUNT_FIELD_VALUE_OPTION;
		}

		return '';
	}

	/**
	 * Resolve the effective field value type from stored field inner content.
	 *
	 * @since ??
	 *
	 * @param array $field_config Field inner content attrs.
	 *
	 * @return string
	 */
	public static function resolve_effective_field_value_type( array $field_config ): string {
		$type             = is_string( $field_config['type'] ?? null ) ? $field_config['type'] : 'text';
		$field_value_type = is_string( $field_config['fieldValueType'] ?? null ) ? $field_config['fieldValueType'] : '';
		$allowed_values   = [ 'text', 'number', 'date', 'date-time', 'time' ];

		if ( ! self::is_field_value_type_capable_field_type( $type ) ) {
			return 'text';
		}

		return in_array( $field_value_type, $allowed_values, true ) ? $field_value_type : 'text';
	}

	/**
	 * Whether the stored field type supports the Field Value Type setting.
	 *
	 * @since ??
	 *
	 * @param string $type Stored field type.
	 *
	 * @return bool
	 */
	public static function is_field_value_type_capable_field_type( string $type ): bool {
		return in_array( $type, [ 'text', 'select', 'checkbox', 'radio' ], true );
	}

	/**
	 * Map stored field attrs to the query-param field type slug used for param key names.
	 *
	 * List controls keep their control type when `fieldValueType = text`, but use value-type
	 * slugs (`number`, `date`, etc.) when a typed comparison is configured.
	 *
	 * @since ??
	 *
	 * @param array $field_config Field inner content attrs.
	 *
	 * @return string
	 */
	public static function resolve_query_param_field_type( array $field_config ): string {
		$type = is_string( $field_config['type'] ?? null ) ? $field_config['type'] : 'text';

		if ( ! self::is_field_value_type_capable_field_type( $type ) ) {
			return $type;
		}

		$effective_field_value_type = self::resolve_effective_field_value_type( $field_config );

		if ( self::is_text_field_type( $type ) ) {
			return $effective_field_value_type;
		}

		return 'text' === $effective_field_value_type ? $type : $effective_field_value_type;
	}

	/**
	 * Whether a list control submits companion `_meta` and `_compare` hidden inputs.
	 *
	 * @since ??
	 *
	 * @param string $stored_field_type Stored field type.
	 * @param string $field_value_type  Selected Field Value Type.
	 *
	 * @return bool
	 */
	public static function list_control_uses_companion_hidden_inputs( string $stored_field_type, string $field_value_type = '' ): bool {
		if ( ! in_array( $stored_field_type, [ 'select', 'radio', 'checkbox' ], true ) ) {
			return false;
		}

		$query_field_type = self::resolve_query_param_field_type(
			[
				'type'           => $stored_field_type,
				'fieldValueType' => $field_value_type,
			]
		);

		return $query_field_type !== $stored_field_type;
	}

	/**
	 * Whether the stored field type represents a text-group control.
	 *
	 * @since ??
	 *
	 * @param string $type Stored field type.
	 *
	 * @return bool
	 */
	public static function is_text_field_type( string $type ): bool {
		return 'text' === $type;
	}

	/**
	 * Whether the loop query type resolves menu items.
	 *
	 * @since ??
	 *
	 * @param string $query_type Loop query type.
	 *
	 * @return bool
	 */
	public static function is_menus_loop_query_type( string $query_type ): bool {
		return 'menus' === $query_type;
	}

	/**
	 * Whether the loop query type resolves users.
	 *
	 * @since ??
	 *
	 * @param string $query_type Loop query type.
	 *
	 * @return bool
	 */
	public static function is_user_loop_query_type( string $query_type ): bool {
		return in_array( $query_type, [ 'user_roles', 'users' ], true );
	}

	/**
	 * Resolve the default search target for one loop query type.
	 *
	 * @since ??
	 *
	 * @param string $query_type Loop query type.
	 *
	 * @return string
	 */
	public static function resolve_default_search_target( string $query_type ): string {
		if ( self::is_user_loop_query_type( $query_type ) ) {
			return self::SEARCH_TARGET_USER_DISPLAY_NAME_AND_BIO;
		}

		if ( self::is_menus_loop_query_type( $query_type ) ) {
			return self::SEARCH_TARGET_MENU_TEXT;
		}

		return self::SEARCH_TARGET_POST_CONTENT;
	}

	/**
	 * Resolve the meta key submitted through companion hidden inputs for filter controls.
	 *
	 * @since ??
	 *
	 * @param string $option_key       Selected field option key.
	 * @param string $custom_field_key Manual meta key when manual option is selected.
	 *
	 * @return string
	 */
	public static function resolve_companion_filter_meta_key( string $option_key, string $custom_field_key = '' ): string {
		if ( self::is_term_count_field_value_option( $option_key ) ) {
			return self::TERM_COUNT_FIELD_VALUE_OPTION;
		}

		if ( self::is_menu_order_field_value_option( $option_key ) ) {
			return self::MENU_ORDER_FIELD_VALUE_OPTION;
		}

		if ( self::is_post_date_field_value_option( $option_key ) ) {
			return $option_key;
		}

		return self::resolve_meta_key( $option_key, $custom_field_key );
	}

	/**
	 * Resolve the underlying post meta key from field option configuration.
	 *
	 * @since ??
	 *
	 * @param string $option_key       Selected field option key.
	 * @param string $custom_field_key Manual meta key when manual option is selected.
	 *
	 * @return string
	 */
	public static function resolve_meta_key( string $option_key, string $custom_field_key = '' ): string {
		if ( self::MANUAL_OPTION === $option_key ) {
			return sanitize_text_field( $custom_field_key );
		}

		if ( ! self::is_custom_field_option( $option_key ) ) {
			return '';
		}

		return sanitize_text_field( substr( $option_key, strlen( self::OPTION_PREFIX ) ) );
	}

	/**
	 * Resolve the query-string param key for a custom field filter control.
	 *
	 * @since ??
	 *
	 * @param string $option_key       Selected field option key.
	 * @param string $custom_field_key Manual meta key when manual option is selected.
	 *
	 * @return string
	 */
	public static function resolve_query_param_key( string $option_key, string $custom_field_key = '' ): string {
		$meta_key = self::resolve_meta_key( $option_key, $custom_field_key );

		if ( '' === $meta_key ) {
			return $option_key;
		}

		return self::OPTION_PREFIX . $meta_key;
	}

	/**
	 * Parse a meta key from a custom field query param clause key.
	 *
	 * @since ??
	 *
	 * @param string $clause_key Query param clause key.
	 *
	 * @return string
	 */
	public static function resolve_meta_key_from_clause_key( string $clause_key ): string {
		if ( ! self::is_custom_field_option( $clause_key ) ) {
			return '';
		}

		return sanitize_text_field( substr( $clause_key, strlen( self::OPTION_PREFIX ) ) );
	}

	/**
	 * Sanitize a filter clause key while preserving custom field namespace separators.
	 *
	 * @since ??
	 *
	 * @param string $clause_key Raw filter clause key from request params.
	 *
	 * @return string
	 */
	public static function sanitize_clause_key( string $clause_key ): string {
		if ( self::is_custom_field_option( $clause_key ) ) {
			$meta_key = sanitize_key( self::resolve_meta_key_from_clause_key( $clause_key ) );

			if ( '' !== $meta_key ) {
				return self::OPTION_PREFIX . $meta_key;
			}
		}

		return sanitize_key( $clause_key );
	}

	/**
	 * Whether a meta key should use numeric ordering in WP_Query.
	 *
	 * @since ??
	 *
	 * @param string $meta_key Resolved post meta key.
	 *
	 * @return bool
	 */
	public static function is_numeric_meta_key( string $meta_key ): bool {
		if ( '' === $meta_key ) {
			return false;
		}

		$acf_fields     = DynamicContentACFUtils::get_acf_field_info( 'post' );
		$acf_field_type = $acf_fields[ $meta_key ]['field_type'] ?? '';

		return in_array( $acf_field_type, [ 'number', 'range' ], true );
	}

	/**
	 * Build grouped custom field option choices for module settings.
	 *
	 * @since ??
	 *
	 * @param string $post_type Effective post type slug.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_field_option_choices( string $post_type, string $filter_field_type = '' ): array {
		if ( self::is_date_filter_field_type( $filter_field_type ) ) {
			return self::enrich_acf_option_groups_with_field_type(
				self::get_date_field_option_choices( $filter_field_type )
			);
		}

		if ( 'number' === $filter_field_type ) {
			return self::enrich_acf_option_groups_with_field_type(
				self::get_number_field_option_choices()
			);
		}

		$discovered_options = self::get_discovered_meta_key_options( $post_type );

		if ( 'text' === $filter_field_type ) {
			return self::enrich_acf_option_groups_with_field_type(
				self::filter_meta_key_options_by_acf_field_types(
					$discovered_options,
					self::TEXT_FILTER_EXCLUDED_ACF_FIELD_TYPES,
					true
				)
			);
		}

		return self::enrich_acf_option_groups_with_field_type( $discovered_options );
	}

	/**
	 * Build grouped custom field option choices without field value type filtering.
	 *
	 * @since ??
	 *
	 * @param string $post_type Effective post type slug.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_discovered_meta_key_options( string $post_type ): array {
		unset( $post_type );

		$meta_key_options = self::get_manual_custom_field_option_group();

		if ( ! et_pb_is_allowed( 'read_dynamic_content_custom_fields' ) ) {
			return $meta_key_options;
		}

		$most_used_meta_keys = DynamicContentOptions::get_most_used_meta_keys_by_type( 'post' );
		$acf_field_names     = array_keys( DynamicContentACFUtils::get_acf_field_info( 'post' ) );
		$used_meta_keys      = array_unique( array_merge( $most_used_meta_keys, $acf_field_names ) );

		if ( empty( $used_meta_keys ) ) {
			return $meta_key_options;
		}

		$discovered_options = DynamicContentACFUtils::build_meta_key_options( 'post', self::OPTION_PREFIX, $used_meta_keys );

		return array_merge( $meta_key_options, $discovered_options );
	}

	/**
	 * Build ACF-only field option choices for date-based filter field types.
	 *
	 * @since ??
	 *
	 * @param string $filter_field_type Post-filter field type (`date`, `date-time`, or `time`).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_date_field_option_choices( string $filter_field_type ): array {
		if ( ! self::is_date_filter_field_type( $filter_field_type ) ) {
			return [];
		}

		$choices = [];

		if ( 'date' === $filter_field_type || 'date-time' === $filter_field_type ) {
			$choices['post_group'] = [
				'label'   => esc_html__( 'Post', 'et_builder_5' ),
				'options' => [
					self::PUBLISHED_DATE_FIELD_VALUE_OPTION => [
						'label' => esc_html__( 'Published Date', 'et_builder_5' ),
					],
					self::LAST_MODIFIED_DATE_FIELD_VALUE_OPTION => [
						'label' => esc_html__( 'Last Modified Date', 'et_builder_5' ),
					],
				],
			];
		}

		$choices = array_merge( $choices, self::get_manual_custom_field_option_group() );

		if ( ! et_pb_is_allowed( 'read_dynamic_content_custom_fields' ) ) {
			return $choices;
		}

		$acf_choices = self::get_acf_date_field_option_choices( $filter_field_type );

		if ( empty( $acf_choices ) ) {
			return $choices;
		}

		return array_merge( $choices, $acf_choices );
	}

	/**
	 * Build ACF-only field option choices for one date-based filter field type.
	 *
	 * @since ??
	 *
	 * @param string $filter_field_type Post-filter field type (`date`, `date-time`, or `time`).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_acf_date_field_option_choices( string $filter_field_type ): array {
		$acf_field_type = self::resolve_acf_field_type_for_filter_field_type( $filter_field_type );

		if ( '' === $acf_field_type ) {
			return [];
		}

		$acf_fields = DynamicContentACFUtils::get_acf_field_info( 'post' );

		if ( empty( $acf_fields ) ) {
			return [];
		}

		$acf_options = [];

		foreach ( $acf_fields as $meta_key => $acf_field ) {
			$field_type = is_array( $acf_field ) ? ( $acf_field['field_type'] ?? '' ) : '';

			if ( $acf_field_type !== $field_type ) {
				continue;
			}

			$label = DynamicContentACFUtils::get_acf_field_label( $meta_key, $acf_fields );
			$key   = self::OPTION_PREFIX . ltrim( $meta_key, '_' );

			$acf_options[ $key ] = [
				'label' => esc_html( $label ),
			];
		}

		if ( empty( $acf_options ) ) {
			return [];
		}

		return [
			self::OPTION_PREFIX . 'group_acf' => [
				'label'   => esc_html__( 'Advanced Custom Fields', 'et_builder_5' ),
				'options' => $acf_options,
			],
		];
	}

	/**
	 * Whether a field type is one of the date-based filter field types.
	 *
	 * @since ??
	 *
	 * @param string $field_type Post-filter field type.
	 *
	 * @return bool
	 */
	public static function is_date_filter_field_type( string $field_type ): bool {
		return isset( self::DATE_FIELD_TYPE_ACF_MAP[ $field_type ] );
	}

	/**
	 * Whether a field value type can filter custom field option choices.
	 *
	 * @since ??
	 *
	 * @param string $field_type Post-filter field value type.
	 *
	 * @return bool
	 */
	public static function is_filter_field_type( string $field_type ): bool {
		return in_array( $field_type, [ 'text', 'number', 'date', 'date-time', 'time' ], true );
	}

	/**
	 * Build ACF-only field option choices for number field value types.
	 *
	 * @since ??
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_number_field_option_choices(): array {
		$acf_choices = self::get_acf_field_option_choices_by_types( self::NUMBER_FILTER_ACF_FIELD_TYPES );

		return array_merge( self::get_manual_custom_field_option_group(), $acf_choices );
	}

	/**
	 * Build the manual custom field option group.
	 *
	 * @since ??
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_manual_custom_field_option_group(): array {
		return [
			'custom_field_group_manual' => [
				'label'   => esc_html__( 'Manual Custom Field', 'et_builder_5' ),
				'options' => [
					self::MANUAL_OPTION => [
						'label' => esc_html__( 'Enter Custom Meta Key', 'et_builder_5' ),
					],
				],
			],
		];
	}

	/**
	 * Attach ACF field type metadata to ACF option groups for client-side filtering.
	 *
	 * @since ??
	 *
	 * @param array<string, array<string, mixed>> $options Grouped field option choices.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function enrich_acf_option_groups_with_field_type( array $options ): array {
		foreach ( $options as $group_key => $group ) {
			if ( ! is_array( $group ) || empty( $group['options'] ) || ! str_contains( (string) $group_key, 'group_acf' ) ) {
				continue;
			}

			foreach ( $group['options'] as $option_key => $option ) {
				if ( ! is_array( $option ) ) {
					continue;
				}

				$meta_key = self::resolve_meta_key_from_clause_key( (string) $option_key );

				$options[ $group_key ]['options'][ $option_key ]['fieldType'] = self::get_acf_field_type_for_meta_key( $meta_key );
			}
		}

		return $options;
	}

	/**
	 * Build grouped ACF field option choices for the provided ACF field types.
	 *
	 * @since ??
	 *
	 * @param array<int, string> $acf_field_types Allowed ACF field types.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_acf_field_option_choices_by_types( array $acf_field_types ): array {
		if ( ! et_pb_is_allowed( 'read_dynamic_content_custom_fields' ) ) {
			return [];
		}

		$acf_fields = DynamicContentACFUtils::get_acf_field_info( 'post' );

		if ( empty( $acf_fields ) ) {
			return [];
		}

		$acf_options = [];

		foreach ( $acf_fields as $meta_key => $acf_field ) {
			$field_type = is_array( $acf_field ) ? ( $acf_field['field_type'] ?? '' ) : '';

			if ( ! in_array( $field_type, $acf_field_types, true ) ) {
				continue;
			}

			$label = DynamicContentACFUtils::get_acf_field_label( $meta_key, $acf_fields );
			$key   = self::OPTION_PREFIX . ltrim( $meta_key, '_' );

			$acf_options[ $key ] = [
				'label' => esc_html( $label ),
			];
		}

		if ( empty( $acf_options ) ) {
			return [];
		}

		return [
			self::OPTION_PREFIX . 'group_acf' => [
				'label'   => esc_html__( 'Advanced Custom Fields', 'et_builder_5' ),
				'options' => $acf_options,
			],
		];
	}

	/**
	 * Filter grouped meta key options by ACF field type.
	 *
	 * @since ??
	 *
	 * @param array<string, array<string, mixed>> $options Grouped field option choices.
	 * @param array<int, string>                  $acf_field_types ACF field types used for filtering.
	 * @param bool                                $exclude_when_matched When true, remove matching ACF field types.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function filter_meta_key_options_by_acf_field_types(
		array $options,
		array $acf_field_types,
		bool $exclude_when_matched = false
	): array {
		foreach ( $options as $group_key => $group ) {
			if ( ! is_array( $group ) || empty( $group['options'] ) || ! str_contains( (string) $group_key, 'group_acf' ) ) {
				continue;
			}

			foreach ( $group['options'] as $option_key => $option ) {
				$meta_key        = self::resolve_meta_key_from_clause_key( (string) $option_key );
				$acf_field_type  = self::get_acf_field_type_for_meta_key( $meta_key );
				$matches_filter  = in_array( $acf_field_type, $acf_field_types, true );
				$should_remove   = $exclude_when_matched ? $matches_filter : ! $matches_filter;

				if ( $should_remove ) {
					unset( $options[ $group_key ]['options'][ $option_key ] );
				}
			}

			if ( empty( $options[ $group_key ]['options'] ) ) {
				unset( $options[ $group_key ] );
			}
		}

		return $options;
	}

	/**
	 * Resolve the ACF field type for one post-filter date field type.
	 *
	 * @since ??
	 *
	 * @param string $filter_field_type Post-filter field type.
	 *
	 * @return string
	 */
	public static function resolve_acf_field_type_for_filter_field_type( string $filter_field_type ): string {
		return self::DATE_FIELD_TYPE_ACF_MAP[ $filter_field_type ] ?? '';
	}

	/**
	 * Resolve the meta_query type for one post-filter date field type.
	 *
	 * @since ??
	 *
	 * @param string $filter_field_type Post-filter field type.
	 *
	 * @return string
	 */
	public static function resolve_meta_query_type_for_filter_field_type( string $filter_field_type ): string {
		return self::DATE_FIELD_TYPE_META_QUERY_MAP[ $filter_field_type ] ?? 'CHAR';
	}

	/**
	 * Resolve the post-filter date field type from a module-scoped param key.
	 *
	 * @since ??
	 *
	 * @param string $param_key Filter param key.
	 *
	 * @return string|null
	 */
	public static function resolve_date_filter_field_type_from_param_key( string $param_key ): ?string {
		if ( self::is_date_field_param_key( $param_key ) ) {
			return 'date';
		}

		if ( self::is_date_time_field_param_key( $param_key ) ) {
			return 'date-time';
		}

		if ( self::is_time_field_param_key( $param_key ) ) {
			return 'time';
		}

		return null;
	}

	/**
	 * Resolve the ACF field type for one post meta key.
	 *
	 * @since ??
	 *
	 * @param string $meta_key Resolved post meta key.
	 *
	 * @return string
	 */
	public static function get_acf_field_type_for_meta_key( string $meta_key ): string {
		if ( '' === $meta_key ) {
			return '';
		}

		$acf_fields = DynamicContentACFUtils::get_acf_field_info( 'post' );

		return is_array( $acf_fields[ $meta_key ] ?? null )
			? (string) ( $acf_fields[ $meta_key ]['field_type'] ?? '' )
			: '';
	}

	/**
	 * Whether a meta key is compatible with one post-filter date field type.
	 *
	 * @since ??
	 *
	 * @param string $meta_key          Resolved post meta key.
	 * @param string $filter_field_type Post-filter field type.
	 *
	 * @return bool
	 */
	public static function is_meta_key_compatible_with_date_filter_field_type( string $meta_key, string $filter_field_type ): bool {
		if ( ! self::is_date_filter_field_type( $filter_field_type ) ) {
			return false;
		}

		if ( self::is_post_date_field_value_option( $meta_key ) ) {
			return 'date' === $filter_field_type || 'date-time' === $filter_field_type;
		}

		$expected_acf_type = self::resolve_acf_field_type_for_filter_field_type( $filter_field_type );
		$actual_acf_type   = self::get_acf_field_type_for_meta_key( $meta_key );

		if ( '' === $actual_acf_type ) {
			return true;
		}

		return $expected_acf_type === $actual_acf_type;
	}

	/**
	 * Normalize an HTML picker value to ACF storage format for meta_query comparison.
	 *
	 * @since ??
	 *
	 * @param string $value             Submitted picker value.
	 * @param string $filter_field_type Post-filter field type.
	 *
	 * @return string
	 */
	public static function normalize_html_picker_value_to_acf_storage( string $value, string $filter_field_type ): string {
		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		if ( 'date' === $filter_field_type ) {
			$date_time = \DateTime::createFromFormat( 'Y-m-d', $value );

			return false !== $date_time ? $date_time->format( 'Ymd' ) : '';
		}

		if ( 'date-time' === $filter_field_type ) {
			$date_time = \DateTime::createFromFormat( 'Y-m-d\TH:i', $value );

			if ( false === $date_time ) {
				$date_time = \DateTime::createFromFormat( 'Y-m-d H:i:s', $value );
			}

			if ( false === $date_time ) {
				$date_time = \DateTime::createFromFormat( 'Y-m-d H:i', $value );
			}

			return false !== $date_time ? $date_time->format( 'YmdHis' ) : '';
		}

		if ( 'time' === $filter_field_type ) {
			$time_value = $value;

			if ( 5 === strlen( $time_value ) ) {
				$time_value .= ':00';
			}

			$date_time = \DateTime::createFromFormat( 'H:i:s', $time_value );

			return false !== $date_time ? $date_time->format( 'H:i:s' ) : '';
		}

		return $value;
	}

	/**
	 * Parse an HTML picker value into WP_Date_Query component keys.
	 *
	 * @since ??
	 *
	 * @param string $value             Submitted picker value.
	 * @param string $filter_field_type Post-filter field type.
	 *
	 * @return array<string, int>
	 */
	public static function parse_html_picker_value_to_date_query_components( string $value, string $filter_field_type ): array {
		$value = trim( $value );

		if ( '' === $value ) {
			return [];
		}

		if ( 'date' === $filter_field_type ) {
			$date_time = \DateTime::createFromFormat( 'Y-m-d', $value );

			if ( false === $date_time ) {
				return [];
			}

			return [
				'year'  => (int) $date_time->format( 'Y' ),
				'month' => (int) $date_time->format( 'm' ),
				'day'   => (int) $date_time->format( 'd' ),
			];
		}

		if ( 'date-time' === $filter_field_type ) {
			$date_time = \DateTime::createFromFormat( 'Y-m-d\TH:i', $value );

			if ( false === $date_time ) {
				$date_time = \DateTime::createFromFormat( 'Y-m-d H:i:s', $value );
			}

			if ( false === $date_time ) {
				$date_time = \DateTime::createFromFormat( 'Y-m-d H:i', $value );
			}

			if ( false === $date_time ) {
				return [];
			}

			return [
				'year'   => (int) $date_time->format( 'Y' ),
				'month'  => (int) $date_time->format( 'm' ),
				'day'    => (int) $date_time->format( 'd' ),
				'hour'   => (int) $date_time->format( 'H' ),
				'minute' => (int) $date_time->format( 'i' ),
			];
		}

		return [];
	}

	/**
	 * Resolve the posts table column targeted by one post date field value option.
	 *
	 * @since ??
	 *
	 * @param string $option_key Selected field value option key.
	 *
	 * @return string
	 */
	public static function resolve_post_date_column_from_option_key( string $option_key ): string {
		if ( self::is_last_modified_date_field_value_option( $option_key ) ) {
			return 'post_modified';
		}

		if ( self::is_published_date_field_value_option( $option_key ) ) {
			return 'post_date';
		}

		return '';
	}

	/**
	 * Build a MySQL datetime boundary string from parsed picker components.
	 *
	 * @since ??
	 *
	 * @param array<string, int> $date_components   Parsed year/month/day (and optional hour/minute).
	 * @param string             $filter_field_type Post-filter field type.
	 *
	 * @return string
	 */
	public static function build_mysql_datetime_boundary_from_components( array $date_components, string $filter_field_type ): string {
		$year  = isset( $date_components['year'] ) ? absint( $date_components['year'] ) : 0;
		$month = isset( $date_components['month'] ) ? absint( $date_components['month'] ) : 0;
		$day   = isset( $date_components['day'] ) ? absint( $date_components['day'] ) : 0;

		if ( 0 === $year || 0 === $month || 0 === $day ) {
			return '';
		}

		if ( 'date-time' === $filter_field_type ) {
			$hour   = isset( $date_components['hour'] ) ? absint( $date_components['hour'] ) : 0;
			$minute = isset( $date_components['minute'] ) ? absint( $date_components['minute'] ) : 0;

			return sprintf( '%04d-%02d-%02d %02d:%02d', $year, $month, $day, $hour, $minute );
		}

		return sprintf( '%04d-%02d-%02d', $year, $month, $day );
	}

	/**
	 * Build a WP_Date_Query clause for post_date / post_modified column comparisons.
	 *
	 * Relational operators map to `before` / `after` boundaries because WP_Date_Query
	 * applies `compare` independently to each year/month/day component.
	 *
	 * @since ??
	 *
	 * @param string             $column            Posts table column (`post_date` or `post_modified`).
	 * @param string             $compare           Sanitized comparison operator.
	 * @param array<string, int> $date_components   Parsed picker components.
	 * @param string             $filter_field_type Post-filter field type.
	 *
	 * @return array<string, mixed>
	 */
	public static function build_post_column_date_query_clause(
		string $column,
		string $compare,
		array $date_components,
		string $filter_field_type
	): array {
		if ( '=' === $compare ) {
			return array_merge(
				[
					'column' => $column,
				],
				$date_components
			);
		}

		$boundary = self::build_mysql_datetime_boundary_from_components( $date_components, $filter_field_type );

		if ( '' === $boundary ) {
			return [];
		}

		if ( '!=' === $compare ) {
			return [
				'relation' => 'OR',
				[
					'column'    => $column,
					'before'    => $boundary,
					'inclusive' => false,
				],
				[
					'column'    => $column,
					'after'     => $boundary,
					'inclusive' => false,
				],
			];
		}

		$clause = [
			'column' => $column,
		];

		switch ( $compare ) {
			case '<':
				$clause['before']    = $boundary;
				$clause['inclusive'] = false;
				break;
			case '<=':
				$clause['before']    = $boundary;
				$clause['inclusive'] = true;
				break;
			case '>':
				$clause['after']     = $boundary;
				$clause['inclusive'] = false;
				break;
			case '>=':
				$clause['after']     = $boundary;
				$clause['inclusive'] = true;
				break;
			default:
				return [];
		}

		return $clause;
	}

	/**
	 * Resolve the select placeholder preview label for one field option.
	 *
	 * Priority: discovered option label, then legacy key fallback.
	 *
	 * @since ??
	 *
	 * @param string $field_option_key Selected field option key.
	 * @param string $post_type        Effective post type slug.
	 *
	 * @return string
	 */
	public static function resolve_field_option_preview_label( string $field_option_key, string $post_type ): string {
		if ( '' === $field_option_key ) {
			return esc_html__( 'option', 'et_builder_5' );
		}

		$resolved_label = self::_resolve_field_option_label_from_choices( $field_option_key, $post_type );

		if ( '' !== $resolved_label ) {
			return $resolved_label;
		}

		$resolved_label = self::_resolve_builtin_field_option_label( $field_option_key );

		if ( '' !== $resolved_label ) {
			return $resolved_label;
		}

		return str_replace( '_', ' ', $field_option_key );
	}

	/**
	 * Resolve labels for built-in field option keys such as taxonomies and author.
	 *
	 * @since ??
	 *
	 * @param string $field_option_key Selected field option key.
	 *
	 * @return string
	 */
	private static function _resolve_builtin_field_option_label( string $field_option_key ): string {
		if ( 'author' === $field_option_key ) {
			return esc_html__( 'Author', 'et_builder_5' );
		}

		if ( 'role' === $field_option_key ) {
			return esc_html__( 'Role', 'et_builder_5' );
		}

		$taxonomy = get_taxonomy( $field_option_key );

		if ( ! $taxonomy instanceof WP_Taxonomy ) {
			return '';
		}

		$label = $taxonomy->labels->singular_name ?? $taxonomy->label ?? '';

		return is_string( $label ) && '' !== $label ? $label : '';
	}

	/**
	 * Resolve a field option label from grouped custom field choices.
	 *
	 * @since ??
	 *
	 * @param string $field_option_key Selected field option key.
	 * @param string $post_type        Effective post type slug.
	 *
	 * @return string
	 */
	private static function _resolve_field_option_label_from_choices( string $field_option_key, string $post_type ): string {
		$choices = self::get_field_option_choices( $post_type );

		foreach ( $choices as $group_key => $group ) {
			if ( ! is_array( $group ) || ! is_array( $group['options'] ?? null ) ) {
				continue;
			}

			if (
				self::OPTION_PREFIX . 'group_standard' === $group_key
				|| self::OPTION_PREFIX . 'group_underscore' === $group_key
			) {
				if ( isset( $group['options'][ $field_option_key ] ) ) {
					return esc_html__( 'Label', 'et_builder_5' );
				}

				continue;
			}

			if ( ! isset( $group['options'][ $field_option_key ] ) ) {
				continue;
			}

			$option = $group['options'][ $field_option_key ];
			$label  = is_array( $option ) ? ( $option['label'] ?? '' ) : $option;

			return is_string( $label ) && '' !== $label ? $label : '';
		}

		return '';
	}

	/**
	 * Whether the Label Date Format setting applies to the current field configuration.
	 *
	 * @since ??
	 *
	 * @param string $field_type       Stored field type.
	 * @param string $field_value_type Effective field value type.
	 *
	 * @return bool
	 */
	public static function should_show_label_date_format( string $field_type, string $field_value_type ): bool {
		if ( ! in_array( $field_type, [ 'select', 'checkbox', 'radio' ], true ) ) {
			return false;
		}

		return in_array( $field_value_type, [ 'date', 'date-time', 'time' ], true );
	}

	/**
	 * Resolve the configured PHP date format string for option label formatting.
	 *
	 * @since ??
	 *
	 * @param array $attrs Saved module attrs.
	 *
	 * @return string
	 */
	public static function resolve_label_date_format( array $attrs ): string {
		$field_inner_content = $attrs['field']['innerContent']['desktop']['value'] ?? [];

		if ( ! is_array( $field_inner_content ) ) {
			return '';
		}

		$field_type       = is_string( $field_inner_content['type'] ?? null ) ? $field_inner_content['type'] : 'text';
		$field_value_type = self::resolve_effective_field_value_type( $field_inner_content );

		if ( ! self::should_show_label_date_format( $field_type, $field_value_type ) ) {
			return '';
		}

		if ( array_key_exists( 'labelDateFormat', $field_inner_content ) && is_string( $field_inner_content['labelDateFormat'] ) ) {
			return trim( $field_inner_content['labelDateFormat'] );
		}

		return self::DEFAULT_LABEL_DATE_FORMAT;
	}

	/**
	 * Format one date-related option label using a PHP date format string.
	 *
	 * @since ??
	 *
	 * @param string $value             Raw stored option value.
	 * @param string $format            PHP date format string.
	 * @param string $field_value_type  Selected field value type used for parsing.
	 *
	 * @return string
	 */
	public static function format_date_option_label( string $value, string $format, string $field_value_type = 'date' ): string {
		$value  = trim( $value );
		$format = trim( $format );

		if ( '' === $value || '' === $format ) {
			return $value;
		}

		$timestamp = self::parse_date_option_value_to_timestamp( $value, $field_value_type );

		if ( null === $timestamp ) {
			return $value;
		}

		return wp_date( $format, $timestamp );
	}

	/**
	 * Apply label formatting to a value-keyed labels map.
	 *
	 * @since ??
	 *
	 * @param array<int, string>    $values           Distinct option values.
	 * @param array<string, string> $labels           Labels keyed by option value.
	 * @param string                $label_format     PHP date format string.
	 * @param string                $field_value_type Selected field value type used for parsing.
	 *
	 * @return array<string, string>
	 */
	public static function apply_label_date_format_to_value_option_labels(
		array $values,
		array $labels,
		string $label_format,
		string $field_value_type = 'date'
	): array {
		$label_format = trim( $label_format );

		if ( '' === $label_format ) {
			return $labels;
		}

		$formatted_labels = [];

		foreach ( $values as $value ) {
			if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
				continue;
			}

			$value_key = (string) $value;

			if ( '' === $value_key ) {
				continue;
			}

			$formatted_labels[ $value_key ] = self::format_date_option_label( $value_key, $label_format, $field_value_type );
		}

		return $formatted_labels;
	}

	/**
	 * Parse one stored option value into a Unix timestamp.
	 *
	 * @since ??
	 *
	 * @param string $value            Raw stored option value.
	 * @param string $field_value_type Selected field value type.
	 *
	 * @return int|null
	 */
	public static function parse_date_option_value_to_timestamp( string $value, string $field_value_type ): ?int {
		$value = trim( $value );

		if ( '' === $value ) {
			return null;
		}

		if ( 'time' === $field_value_type ) {
			$time_value = $value;

			if ( 5 === strlen( $time_value ) ) {
				$time_value .= ':00';
			}

			$date_time = \DateTime::createFromFormat( 'H:i:s', $time_value );

			if ( false === $date_time ) {
				return null;
			}

			return (int) $date_time->format( 'U' );
		}

		if ( preg_match( '/^\d{8}$/', $value ) ) {
			$date_time = \DateTime::createFromFormat( 'Ymd', $value );

			return false !== $date_time ? (int) $date_time->format( 'U' ) : null;
		}

		if ( preg_match( '/^\d{14}$/', $value ) ) {
			$date_time = \DateTime::createFromFormat( 'YmdHis', $value );

			return false !== $date_time ? (int) $date_time->format( 'U' ) : null;
		}

		if ( is_numeric( $value ) ) {
			$numeric_value = (int) $value;

			// Check if the numeric value falls within the allowed range for Unix timestamps from the year 2000 to 2100.
			if ( 946684800 <= $numeric_value && 4102444800 >= $numeric_value ) {
				return $numeric_value;
			}
		}

		$timestamp = mysql2date( 'U', $value, false );

		if ( is_numeric( $timestamp ) ) {
			return (int) $timestamp;
		}

		$date_time = \DateTime::createFromFormat( 'Y-m-d\TH:i', $value );

		if ( false === $date_time ) {
			$date_time = \DateTime::createFromFormat( 'Y-m-d H:i:s', $value );
		}

		if ( false === $date_time ) {
			$date_time = \DateTime::createFromFormat( 'Y-m-d H:i', $value );
		}

		if ( false === $date_time ) {
			$date_time = \DateTime::createFromFormat( 'Y-m-d', $value );
		}

		return false !== $date_time ? (int) $date_time->format( 'U' ) : null;
	}

	/**
	 * Apply date option label formatting to list items when enabled in attrs.
	 *
	 * @since ??
	 *
	 * @param array<int, array{value: string, label: string}> $list_items List items.
	 * @param array                                           $attrs      Saved module attrs.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function apply_date_option_label_formatting_to_list_items( array $list_items, array $attrs ): array {
		$label_format = self::resolve_label_date_format( $attrs );

		if ( '' === $label_format ) {
			return $list_items;
		}

		$field_inner_content = $attrs['field']['innerContent']['desktop']['value'] ?? [];
		$field_value_type    = is_array( $field_inner_content )
			? self::resolve_effective_field_value_type( $field_inner_content )
			: 'date';

		$formatted_list_items = [];

		foreach ( $list_items as $list_item ) {
			if ( ! is_array( $list_item ) ) {
				continue;
			}

			$option_value = (string) ( $list_item['value'] ?? '' );

			if ( '' === $option_value ) {
				continue;
			}

			$formatted_list_items[] = [
				'value' => $option_value,
				'label' => self::format_date_option_label( $option_value, $label_format, $field_value_type ),
			];
		}

		return $formatted_list_items;
	}

	/**
	 * Resolve list items from manually configured field options attrs.
	 *
	 * @since ??
	 *
	 * @param string $field_type Field control type.
	 * @param array  $attrs      Saved module attrs.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function get_list_items_from_attrs( string $field_type, array $attrs ): array {
		$options_attr_key = self::_get_options_attr_key( $field_type );

		if ( '' === $options_attr_key ) {
			return [];
		}

		$options = $attrs['field']['advanced'][ $options_attr_key ]['desktop']['value'] ?? [];

		if ( ! is_array( $options ) ) {
			return [];
		}

		$list_items = [];

		foreach ( $options as $option ) {
			if ( ! is_array( $option ) ) {
				continue;
			}

			$raw_value = $option['value'] ?? '';

			if ( ! is_string( $raw_value ) || '' === trim( wp_strip_all_tags( $raw_value ) ) ) {
				continue;
			}

			$raw_label = $option['label'] ?? '';
			$label     = is_string( $raw_label ) && '' !== trim( wp_strip_all_tags( $raw_label ) )
				? trim( wp_strip_all_tags( $raw_label ) )
				: trim( wp_strip_all_tags( $raw_value ) );

			$list_items[] = [
				'value' => trim( wp_strip_all_tags( $raw_value ) ),
				'label' => $label,
			];
		}

		return self::apply_date_option_label_formatting_to_list_items( $list_items, $attrs );
	}

	/**
	 * Resolve the query param key for a number field control.
	 *
	 * @since ??
	 *
	 * @param string $module_id Post-filter-item module id.
	 *
	 * @return string
	 */
	public static function resolve_number_field_param_key( string $module_id ): string {
		$module_id = sanitize_key( $module_id );

		if ( '' === $module_id ) {
			return '';
		}

		return self::NUMBER_FIELD_PARAM_PREFIX . $module_id;
	}

	/**
	 * Whether a filter param key belongs to a number field control.
	 *
	 * @since ??
	 *
	 * @param string $param_key Filter param key.
	 *
	 * @return bool
	 */
	public static function is_number_field_param_key( string $param_key ): bool {
		return str_starts_with( $param_key, self::NUMBER_FIELD_PARAM_PREFIX );
	}

	/**
	 * Resolve the query param key for a select custom-field control.
	 *
	 * @since ??
	 *
	 * @param string $module_id Post-filter-item module id.
	 *
	 * @return string
	 */
	public static function resolve_select_field_param_key( string $module_id ): string {
		$module_id = sanitize_key( $module_id );

		if ( '' === $module_id ) {
			return '';
		}

		return self::SELECT_FIELD_PARAM_PREFIX . $module_id;
	}

	/**
	 * Whether a filter param key belongs to a select custom-field control.
	 *
	 * @since ??
	 *
	 * @param string $param_key Filter param key.
	 *
	 * @return bool
	 */
	public static function is_select_field_param_key( string $param_key ): bool {
		return str_starts_with( $param_key, self::SELECT_FIELD_PARAM_PREFIX );
	}

	/**
	 * Resolve the query param key for a radio custom-field control.
	 *
	 * @since ??
	 *
	 * @param string $module_id Post-filter-item module id.
	 *
	 * @return string
	 */
	public static function resolve_radio_field_param_key( string $module_id ): string {
		$module_id = sanitize_key( $module_id );

		if ( '' === $module_id ) {
			return '';
		}

		return self::RADIO_FIELD_PARAM_PREFIX . $module_id;
	}

	/**
	 * Whether a filter param key belongs to a radio custom-field control.
	 *
	 * @since ??
	 *
	 * @param string $param_key Filter param key.
	 *
	 * @return bool
	 */
	public static function is_radio_field_param_key( string $param_key ): bool {
		return str_starts_with( $param_key, self::RADIO_FIELD_PARAM_PREFIX );
	}

	/**
	 * Whether a filter param key uses companion `_meta` and `_compare` params.
	 *
	 * @since ??
	 *
	 * @param string $param_key Filter param key.
	 *
	 * @return bool
	 */
	public static function is_companion_meta_field_param_key( string $param_key ): bool {
		return self::is_number_field_param_key( $param_key )
			|| self::is_date_field_param_key( $param_key )
			|| self::is_date_time_field_param_key( $param_key )
			|| self::is_time_field_param_key( $param_key )
			|| self::is_select_field_param_key( $param_key )
			|| self::is_radio_field_param_key( $param_key );
	}

	/**
	 * Resolve the query param key for a date field control.
	 *
	 * @since ??
	 *
	 * @param string $module_id Post-filter-item module id.
	 *
	 * @return string
	 */
	public static function resolve_date_field_param_key( string $module_id ): string {
		$module_id = sanitize_key( $module_id );

		if ( '' === $module_id ) {
			return '';
		}

		return self::DATE_FIELD_PARAM_PREFIX . $module_id;
	}

	/**
	 * Whether a filter param key belongs to a date field control.
	 *
	 * @since ??
	 *
	 * @param string $param_key Filter param key.
	 *
	 * @return bool
	 */
	public static function is_date_field_param_key( string $param_key ): bool {
		return str_starts_with( $param_key, self::DATE_FIELD_PARAM_PREFIX )
			&& ! str_starts_with( $param_key, self::DATE_TIME_FIELD_PARAM_PREFIX );
	}

	/**
	 * Resolve the query param key for a date-time field control.
	 *
	 * @since ??
	 *
	 * @param string $module_id Post-filter-item module id.
	 *
	 * @return string
	 */
	public static function resolve_date_time_field_param_key( string $module_id ): string {
		$module_id = sanitize_key( $module_id );

		if ( '' === $module_id ) {
			return '';
		}

		return self::DATE_TIME_FIELD_PARAM_PREFIX . $module_id;
	}

	/**
	 * Whether a filter param key belongs to a date-time field control.
	 *
	 * @since ??
	 *
	 * @param string $param_key Filter param key.
	 *
	 * @return bool
	 */
	public static function is_date_time_field_param_key( string $param_key ): bool {
		return str_starts_with( $param_key, self::DATE_TIME_FIELD_PARAM_PREFIX );
	}

	/**
	 * Resolve the query param key for a time field control.
	 *
	 * @since ??
	 *
	 * @param string $module_id Post-filter-item module id.
	 *
	 * @return string
	 */
	public static function resolve_time_field_param_key( string $module_id ): string {
		$module_id = sanitize_key( $module_id );

		if ( '' === $module_id ) {
			return '';
		}

		return self::TIME_FIELD_PARAM_PREFIX . $module_id;
	}

	/**
	 * Whether a filter param key belongs to a time field control.
	 *
	 * @since ??
	 *
	 * @param string $param_key Filter param key.
	 *
	 * @return bool
	 */
	public static function is_time_field_param_key( string $param_key ): bool {
		return str_starts_with( $param_key, self::TIME_FIELD_PARAM_PREFIX );
	}

	/**
	 * Resolve the query param key for a search field control.
	 *
	 * @since ??
	 *
	 * @param string $module_id Post-filter-item module id.
	 *
	 * @return string
	 */
	public static function resolve_text_field_param_key( string $module_id ): string {
		$module_id = sanitize_key( $module_id );

		if ( '' === $module_id ) {
			return '';
		}

		return self::TEXT_FIELD_PARAM_PREFIX . $module_id;
	}

	/**
	 * Whether a filter param key belongs to a text field control.
	 *
	 * @since ??
	 *
	 * @param string $param_key Filter param key.
	 *
	 * @return bool
	 */
	public static function is_text_field_param_key( string $param_key ): bool {
		return str_starts_with( $param_key, self::TEXT_FIELD_PARAM_PREFIX );
	}

	/**
	 * Sanitize a numeric comparison operator for meta_query clauses.
	 *
	 * @since ??
	 *
	 * @param string $compare_value Raw comparison operator.
	 *
	 * @return string|null
	 */
	public static function sanitize_compare_operator( string $compare_value ): ?string {
		$allowed_operators = [
			'=',
			'!=',
			'>',
			'>=',
			'<',
			'<=',
			'LIKE',
			'NOT LIKE',
			'IN',
			'NOT IN',
			'EXISTS',
			'NOT EXISTS',
		];

		$normalized_compare = strtoupper( trim( $compare_value ) );

		if ( in_array( $normalized_compare, $allowed_operators, true ) ) {
			return $normalized_compare;
		}

		return in_array( $compare_value, $allowed_operators, true ) ? $compare_value : null;
	}

	/**
	 * Whether a comparison operator checks only for meta key existence.
	 *
	 * @since ??
	 *
	 * @param string $compare_value Sanitized comparison operator.
	 *
	 * @return bool
	 */
	public static function is_exists_compare_operator( string $compare_value ): bool {
		return in_array( $compare_value, [ 'EXISTS', 'NOT EXISTS' ], true );
	}

	/**
	 * Whether a comparison operator expects an array of values.
	 *
	 * @since ??
	 *
	 * @param string $compare_value Sanitized comparison operator.
	 *
	 * @return bool
	 */
	public static function is_list_compare_operator( string $compare_value ): bool {
		return in_array( $compare_value, [ 'IN', 'NOT IN' ], true );
	}

	/**
	 * Append one meta_query clause to query args.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query  Base query args.
	 * @param array<string,mixed> $meta_clause Meta query clause.
	 *
	 * @return array<string,mixed>
	 */
	public static function append_meta_query_clause( array $base_query, array $meta_clause ): array {
		if ( ! isset( $base_query['meta_query'] ) || ! is_array( $base_query['meta_query'] ) ) {
			$base_query['meta_query'] = [];
		}

		$base_query['meta_query'][] = $meta_clause;

		return $base_query;
	}

	/**
	 * Append an EXISTS / NOT EXISTS meta_query clause to query args.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $base_query Base query args.
	 * @param string              $meta_key   Resolved meta key.
	 * @param string              $compare    Sanitized comparison operator.
	 * @param string|null         $meta_type  Optional meta query type.
	 *
	 * @return array<string,mixed>
	 */
	public static function append_exists_meta_query_clause(
		array $base_query,
		string $meta_key,
		string $compare,
		?string $meta_type = null
	): array {
		$meta_clause = [
			'key'     => $meta_key,
			'compare' => $compare,
		];

		if ( null !== $meta_type && '' !== $meta_type ) {
			$meta_clause['type'] = $meta_type;
		}

		return self::append_meta_query_clause( $base_query, $meta_clause );
	}

	/**
	 * Sanitize a submitted comparison operator from filter request params.
	 *
	 * Comparison operators must not pass through `sanitize_text_field()` because WordPress
	 * encodes `<` as `&lt;`, which breaks `Less Than` / `Smaller Than or Equal To` filters.
	 *
	 * @since ??
	 *
	 * @param mixed $compare_value Raw comparison operator from request params.
	 *
	 * @return string|null
	 */
	public static function sanitize_compare_param_value( $compare_value ): ?string {
		if ( ! is_string( $compare_value ) || '' === $compare_value ) {
			return null;
		}

		$normalized_value = wp_unslash( $compare_value );
		$compare          = self::sanitize_compare_operator( $normalized_value );

		if ( null !== $compare ) {
			return $compare;
		}

		return self::sanitize_compare_operator( html_entity_decode( $normalized_value, ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Parse numeric digits from a submitted filter value.
	 *
	 * @since ??
	 *
	 * @param mixed $value Submitted filter value.
	 *
	 * @return float|null
	 */
	public static function parse_numeric_filter_value( $value ): ?float {
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			return null;
		}

		$digits_only = preg_replace( '/[^0-9.\-]/', '', (string) $value );

		if ( null === $digits_only || '' === $digits_only || '-' === $digits_only ) {
			return null;
		}

		return (float) $digits_only;
	}

	/**
	 * Resolve the advanced attr key that stores list options for a field type.
	 *
	 * @since ??
	 *
	 * @param string $field_type Field control type.
	 *
	 * @return string
	 */
	private static function _get_options_attr_key( string $field_type ): string {
		switch ( $field_type ) {
			case 'checkbox':
				return 'checkboxOptions';
			case 'radio':
				return 'radioOptions';
			case 'select':
				return 'selectOptions';
			default:
				return '';
		}
	}
}
