<?php
/**
 * Module: DynamicContentACFUtils class.
 *
 * Shared utility functions for handling Advanced Custom Fields (ACF) integration
 * across different dynamic content option classes.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Layout\Components\DynamicContent;

use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\StringUtility;
use ET\Builder\Packages\Module\Options\Loop\QueryResults\QueryResultsController;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Module: DynamicContentACFUtils class.
 *
 * @since ??
 */
class DynamicContentACFUtils {
	/**
	 * Cached ACF plugin status for the current request.
	 *
	 * @since ??
	 *
	 * @var bool|null
	 */
	private static ?bool $_acf_active_cache = null;

	/**
	 * Cached ACF field info by meta type for the current request.
	 *
	 * @since ??
	 *
	 * @var array<string, array>
	 */
	private static array $_acf_field_info_cache = [];

	/**
	 * Cached ACF field groups for the current request.
	 *
	 * @since ??
	 *
	 * @var array|null
	 */
	private static ?array $_acf_field_groups_cache = null;

	/**
	 * Cached ACF fields by group ID for the current request.
	 *
	 * @since ??
	 *
	 * @var array<int, array>
	 */
	private static array $_acf_fields_by_group_cache = [];

	/**
	 * Default items per page for repeater queries.
	 *
	 * @var int
	 */
	const DEFAULT_REPEATER_PER_PAGE = 10;

	/**
	 * Check if ACF or SCF plugin is active.
	 *
	 * SCF (Secure Custom Fields) is a fork of ACF that uses the same function names.
	 * We check for both plugins here so all existing ACF code automatically works with SCF.
	 *
	 * @since ??
	 *
	 * @return bool True if ACF or SCF is active, false otherwise.
	 */
	public static function is_acf_active(): bool {
		if ( null !== self::$_acf_active_cache ) {
			return self::$_acf_active_cache;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		self::$_acf_active_cache = is_plugin_active( 'advanced-custom-fields/acf.php' )
			|| is_plugin_active( 'advanced-custom-fields-pro/acf.php' )
			|| is_plugin_active( 'secure-custom-fields/secure-custom-fields.php' );

		return self::$_acf_active_cache;
	}

	/**
	 * Initialize hooks for ACF taxonomy field processing.
	 *
	 * @since ??
	 */
	public static function init_hooks(): void {
		add_filter( 'divi_module_dynamic_content_resolved_custom_meta_value', [ self::class, 'filter_custom_meta_value' ], 15, 3 );
	}

	/**
	 * Get ACF field information for a given meta type.
	 *
	 * @since ??
	 *
	 * @param string $meta_type The meta type: 'post', 'user', or 'term'.
	 *
	 * @return array Array of ACF field info with field names as keys and labels as values.
	 */
	public static function get_acf_field_info( string $meta_type ): array {
		if ( isset( self::$_acf_field_info_cache[ $meta_type ] ) ) {
			return self::$_acf_field_info_cache[ $meta_type ];
		}

		if ( ! self::is_acf_active() || ! function_exists( 'acf_get_field_groups' ) ) {
			self::$_acf_field_info_cache[ $meta_type ] = [];
			return self::$_acf_field_info_cache[ $meta_type ];
		}

		// Check permissions for user meta access.
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- manage_users is a valid WordPress capability.
		if ( 'user' === $meta_type && ! current_user_can( 'manage_users' ) ) {
			return [];
		}

		$acf_fields = [];

		// Get all ACF field groups.
		// Include all field groups for all meta types (post, user, term) to ensure
		// ACF fields are available regardless of location rules. This matches the
		// behavior where user ACF fields appear when post type is selected for loops.
		$field_groups = self::_get_acf_field_groups();

		if ( empty( $field_groups ) ) {
			self::$_acf_field_info_cache[ $meta_type ] = [];
			return self::$_acf_field_info_cache[ $meta_type ];
		}

		foreach ( $field_groups as $group ) {
			// Get fields from this group.
			$fields = self::_get_acf_fields_for_group( $group['ID'] );

			if ( empty( $fields ) ) {
				continue;
			}

			foreach ( $fields as $field ) {
				$field_type  = $field['type'] ?? 'unknown';
				$field_name  = $field['name'] ?? '';
				$field_label = $field['label'] ?? $field_name;

				// Skip repeater and flexible content fields (they have their own handling).
				if ( in_array( $field_type, [ 'repeater', 'flexible_content' ], true ) ) {
					continue;
				}

				if ( ! empty( $field_name ) ) {
					$acf_fields[ $field_name ] = [
						'field_label' => $field_label,
						'field_type'  => $field_type,
					];
				}
			}
		}

		self::$_acf_field_info_cache[ $meta_type ] = $acf_fields;

		return self::$_acf_field_info_cache[ $meta_type ];
	}

	/**
	 * ACF field types that do not map to a single flat meta key for post-filter search.
	 *
	 * @since ??
	 *
	 * @return array<int, string>
	 */
	public static function get_non_searchable_acf_field_types(): array {
		return [
			'repeater',
			'flexible_content',
			'clone',
			'tab',
			'accordion',
			'message',
		];
	}

	/**
	 * Collect flat post meta keys from an ACF field definition tree.
	 *
	 * Group fields expand to prefixed sub-field keys (for example `address` + `city` => `address_city`).
	 * Layout and composite field types are skipped. See `specs/dynamic-content/acf-field-types-and-post-filter-search.md`.
	 *
	 * @since ??
	 *
	 * @param array<int, array<string, mixed>> $fields      ACF field definitions.
	 * @param string                           $name_prefix Meta key prefix for nested fields.
	 *
	 * @return array<int, string>
	 */
	public static function collect_flat_leaf_meta_keys_from_acf_fields( array $fields, string $name_prefix = '' ): array {
		$meta_keys = [];

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$field_type = $field['type'] ?? '';
			$field_name = $field['name'] ?? '';

			if ( '' === $field_name ) {
				continue;
			}

			$full_name = $name_prefix . $field_name;

			if ( 'group' === $field_type ) {
				$sub_fields = $field['sub_fields'] ?? [];

				if ( is_array( $sub_fields ) && ! empty( $sub_fields ) ) {
					$meta_keys = array_merge(
						$meta_keys,
						self::collect_flat_leaf_meta_keys_from_acf_fields( $sub_fields, $full_name . '_' )
					);
				}

				continue;
			}

			if ( in_array( $field_type, self::get_non_searchable_acf_field_types(), true ) ) {
				continue;
			}

			$meta_keys[] = $full_name;
		}

		return array_values( array_unique( $meta_keys ) );
	}

	/**
	 * Resolve one custom-field search target to the flat meta keys that should be queried.
	 *
	 * When ACF is active and the target is a group field, this expands to all searchable leaf
	 * sub-field meta keys. Non-group fields resolve to the submitted meta key unchanged.
	 *
	 * @since ??
	 *
	 * @param string $meta_key  Submitted meta key or ACF field name.
	 * @param string $meta_type Meta object type (`post`, `user`, or `term`). Post-filter search uses `post`.
	 *
	 * @return array<int, string>
	 */
	public static function resolve_search_meta_keys( string $meta_key, string $meta_type = 'post' ): array {
		$meta_key = ltrim( sanitize_text_field( $meta_key ), '_' );

		if ( '' === $meta_key ) {
			return [];
		}

		$resolved_keys = [ $meta_key ];

		if ( self::is_acf_active() && function_exists( 'acf_get_field' ) ) {
			$field = acf_get_field( $meta_key );

			if ( is_array( $field ) && ! empty( $field['name'] ) ) {
				$field_type = $field['type'] ?? '';

				if ( 'group' === $field_type ) {
					$sub_fields = $field['sub_fields'] ?? [];

					if ( is_array( $sub_fields ) && ! empty( $sub_fields ) ) {
						$expanded_keys = self::collect_flat_leaf_meta_keys_from_acf_fields(
							$sub_fields,
							$field['name'] . '_'
						);

						if ( ! empty( $expanded_keys ) ) {
							$resolved_keys = $expanded_keys;
						}
					}
				}
			}
		}

		/**
		 * Filters the flat meta keys used when searching a custom-field target.
		 *
		 * @since ??
		 *
		 * @param array<int, string> $resolved_keys Flat meta keys to include in meta_query search clauses.
		 * @param string             $meta_key      Original submitted meta key.
		 * @param string             $meta_type     Meta object type (`post`, `user`, or `term`).
		 */
		return apply_filters( 'divi_resolve_search_meta_keys', $resolved_keys, $meta_key, $meta_type );
	}

	/**
	 * Get cached ACF field groups for the current request.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	private static function _get_acf_field_groups(): array {
		if ( null !== self::$_acf_field_groups_cache ) {
			return self::$_acf_field_groups_cache;
		}

		$field_groups                  = acf_get_field_groups();
		self::$_acf_field_groups_cache = is_array( $field_groups ) ? $field_groups : [];

		return self::$_acf_field_groups_cache;
	}

	/**
	 * Get cached ACF fields for a group ID for the current request.
	 *
	 * @since ??
	 *
	 * @param int $group_id ACF field group ID.
	 *
	 * @return array
	 */
	private static function _get_acf_fields_for_group( int $group_id ): array {
		if ( isset( self::$_acf_fields_by_group_cache[ $group_id ] ) ) {
			return self::$_acf_fields_by_group_cache[ $group_id ];
		}

		$fields                                        = acf_get_fields( $group_id );
		self::$_acf_fields_by_group_cache[ $group_id ] = is_array( $fields ) ? $fields : [];

		return self::$_acf_fields_by_group_cache[ $group_id ];
	}

	/**
	 * Check if a meta key is an ACF field.
	 *
	 * @since ??
	 *
	 * @param string $meta_key  The meta key to check.
	 * @param array  $acf_fields Array of ACF field names.
	 *
	 * @return bool True if the meta key is an ACF field, false otherwise.
	 */
	public static function is_acf_field( string $meta_key, array $acf_fields ): bool {
		// First check our collected ACF fields array.
		if ( isset( $acf_fields[ $meta_key ] ) ) {
			return true;
		}

		// Check if it's an ACF reference field (starts with underscore and has corresponding field).
		if ( StringUtility::starts_with( $meta_key, '_' ) ) {
			$field_name = ltrim( $meta_key, '_' );
			if ( isset( $acf_fields[ $field_name ] ) ) {
				return true;
			}
		}

		// Use ACF's built-in function as a fallback to catch any fields we might have missed.
		if ( self::is_acf_active() && function_exists( 'acf_get_field' ) ) {
			// Try with $meta_key and, if needed, without a leading underscore.
			foreach ( [ $meta_key, ltrim( $meta_key, '_' ) ] as $key ) {
				if ( acf_get_field( $key ) !== false ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get the proper label for an ACF field.
	 *
	 * @since ??
	 *
	 * @param string $meta_key   The meta key to get label for.
	 * @param array  $acf_fields Array of pre-collected ACF field names and labels.
	 *
	 * @return string The ACF field label or meta key as fallback.
	 */
	public static function get_acf_field_label( string $meta_key, array $acf_fields ): string {
		// First check our pre-collected ACF fields array.
		$field_name = '_' === substr( $meta_key, 0, 1 ) ? ltrim( $meta_key, '_' ) : $meta_key;
		if ( isset( $acf_fields[ $field_name ] ) ) {
			return $acf_fields[ $field_name ]['field_label'] ?? $meta_key;
		}

		// Use ACF's built-in function to get field information.
		if ( self::is_acf_active() && function_exists( 'acf_get_field' ) ) {
			// Try with $meta_key and, if needed, without a leading underscore.
			foreach ( [ $meta_key, ltrim( $meta_key, '_' ) ] as $key ) {
				$field = acf_get_field( $key );
				if ( false !== $field && isset( $field['label'] ) ) {
					return esc_html( $field['label'] );
				}
			}
		}

		// Fallback to the meta key itself.
		return esc_html( $meta_key );
	}

	/**
	 * Get meta value by type with ACF field processing.
	 *
	 * @since ??
	 *
	 * @param string      $type     The meta type: 'post', 'user', or 'term'.
	 * @param int         $id       The object ID.
	 * @param string|null $meta_key The meta key, can be null.
	 *
	 * @return mixed The meta value.
	 */
	public static function get_meta_value_by_type( string $type, int $id, ?string $meta_key ) {
		// Return early if meta_key is null or empty.
		if ( empty( $meta_key ) ) {
			return '';
		}

		// Check if this is an ACF field and use ACF's functions for proper processing.
		if ( self::is_acf_active() && function_exists( 'get_field' ) ) {
			// Get ACF field info to determine if this is an ACF field.
			$acf_fields = self::get_acf_field_info( $type );

			// Remove underscore prefix for ACF field lookup.
			$field_name = StringUtility::starts_with( $meta_key, '_' ) ? ltrim( $meta_key, '_' ) : $meta_key;

			// Check if this is an ACF field using our collected fields or ACF's built-in check.
			$is_acf = self::is_acf_field( $meta_key, $acf_fields );
			// If not found in our list but ACF function exists, try direct ACF check as fallback.
			if ( ! $is_acf && function_exists( 'acf_get_field' ) ) {
				$is_acf = ( acf_get_field( $field_name ) !== false );
			}

			if ( $is_acf ) {
				// Use ACF's get_field() function which handles field type processing.
				$acf_context = $id;
				switch ( $type ) {
					case 'post':
						$value = get_field( $field_name, $id );
						break;
					case 'user':
						// Check if current user has permission to access user meta.
						// Use list_users capability which is appropriate for reading/viewing user data.
						// This is less restrictive than manage_users but still provides security.
						// phpcs:ignore WordPress.WP.Capabilities.Unknown -- list_users is a valid WordPress capability.
						if ( ! current_user_can( 'list_users' ) ) {
							return '';
						}
						$acf_context = 'user_' . $id;
						$value       = get_field( $field_name, $acf_context );
						break;
					case 'term':
						$acf_context = 'term_' . $id;
						$value       = get_field( $field_name, $acf_context );
						break;
					default:
						$value = '';
				}

				$field_object = null;

				if ( function_exists( 'get_field_object' ) ) {
					// Use the correct context for get_field_object based on meta type.
					$field_object = get_field_object( $field_name, $acf_context );
				}

				$acf_field_type = $acf_fields[ $field_name ]['field_type'] ?? '';

				// Handle special cases for ACF field types.
				switch ( $acf_field_type ) {
					case 'user':
						if ( is_array( $value ) && ! empty( $value ) ) {
							$value = implode( ', ', $value );
						} elseif ( is_object( $value ) ) {
							$value = $value->user_login;
						}
						break;
					case 'true_false':
						$value = et_builder_i18n( $value ? 'Yes' : 'No' );
						break;
					default:
						break;
				}

				$could_be_taxonomy = ( is_object( $value ) && isset( $value->term_id ) ) ||
								( is_numeric( $value ) && $value > 0 ) ||
								( is_array( $value ) && ! empty( $value ) );

				if ( $could_be_taxonomy && is_array( $field_object ) && 'taxonomy' === ( $field_object['type'] ?? '' ) && 'post' === $type ) {
					return self::format_taxonomy_field_value( $value, $field_object );
				}

				// ACF get_field() handles the processing, but we may need to convert some types for display.
				// For single image fields, ACF returns array with URL and other image data.
				if ( is_array( $value ) && isset( $value['url'] ) ) {
					return $value['url'];
				}

				// ACF image fields that return attachment ID (return is "Image ID").
				if ( is_numeric( $value ) && $value > 0 && is_array( $field_object ) && 'image' === ( $field_object['type'] ?? '' ) ) {
					$image_url = wp_get_attachment_url( absint( $value ) );
					if ( $image_url ) {
						return $image_url;
					}
				}

				// For gallery/multiple image fields, return first image URL or all URLs.
				if ( is_array( $value ) ) {
					// Check if it's a gallery field (array of image objects).
					if ( isset( $value[0] ) && is_array( $value[0] ) && isset( $value[0]['url'] ) ) {
						// Gallery field - return first image URL for now.
						return $value[0]['url'];
					} elseif ( isset( $value[0] ) && is_array( $value[0] ) && isset( $value[0]['value'] ) ) {
						// ACF field with value/label structure - extract values from array of objects.
						$values = array_map(
							function ( $item ) {
								return $item['value'] ?? '';
							},
							$value
						);
						return implode( ', ', array_filter( $values ) );
					} elseif ( isset( $value[0] ) && is_string( $value[0] ) ) {
						// Simple array of strings - join with commas (e.g., checkbox values).
						return implode( ', ', $value );
					}
					// For other complex arrays, fall through to return as-is.
				}

				return $value;
			}
		}

		// Fallback to standard WordPress meta functions for non-ACF fields.
		switch ( $type ) {
			case 'post':
				return get_post_meta( $id, $meta_key, true );
			case 'user':
				// Check if current user has permission to access user meta.
				// phpcs:ignore WordPress.WP.Capabilities.Unknown -- manage_users is a valid WordPress capability.
				if ( ! current_user_can( 'manage_users' ) ) {
					return '';
				}
				return get_user_meta( $id, $meta_key, true );
			case 'term':
				return get_term_meta( $id, $meta_key, true );
			default:
				return '';
		}
	}

	/**
	 * Build meta key options with ACF grouping for dropdown menus.
	 *
	 * @since ??
	 *
	 * @param string $type         The meta type: 'post', 'user', or 'term'.
	 * @param string $prefix       The option key prefix.
	 * @param array  $used_meta_keys Array of meta keys to process.
	 *
	 * @return array The organized meta key options with subgroups.
	 */
	public static function build_meta_key_options( string $type, string $prefix, array $used_meta_keys ): array {
		if ( empty( $used_meta_keys ) ) {
			return [];
		}

		// Check permissions for user meta access.
		// Use list_users capability which is appropriate for reading/viewing user data.
		// This is less restrictive than manage_users but still provides security.
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- list_users is a valid WordPress capability.
		if ( 'user' === $type && ! current_user_can( 'list_users' ) ) {
			return [];
		}

		// Get ACF field information for enhanced display.
		$acf_fields = self::get_acf_field_info( $type );

		// Separate ACF fields, standard keys, and underscore keys.
		$acf_keys        = [];
		$standard_keys   = [];
		$underscore_keys = [];

		// Prioritize non-underscore meta keys when duplicates exist.
		$added_keys = [];
		foreach ( $used_meta_keys as $meta_key ) {
			// Skip null or empty meta keys.
			if ( empty( $meta_key ) ) {
				continue;
			}

			$key_without_underscore = ltrim( $meta_key, '_' );

			// If we haven't seen this key (without underscore) before, or if this is the non-underscore version, add it.
			if ( ! isset( $added_keys[ $key_without_underscore ] ) || ! str_starts_with( $meta_key, '_' ) ) {
				// Check if this is an ACF field.
				if ( self::is_acf_field( $meta_key, $acf_fields ) ) {
					// Get ACF field label using multiple methods.
					$acf_label = self::get_acf_field_label( $meta_key, $acf_fields );

					// Normalize ACF field keys to always use non-underscore version to prevent duplicates.
					// This ensures that both 'field_name' and '_field_name' map to the same option key.
					$normalized_acf_key = $prefix . $key_without_underscore;
					if ( ! isset( $acf_keys[ $normalized_acf_key ] ) ) {
						$acf_keys[ $normalized_acf_key ] = $acf_label;
					}
					// phpcs:ignore Universal.ControlStructures.DisallowLonelyIf.Found -- Intentional else-if pattern for readability.
				} else {
					// Separate standard keys from underscore keys.
					if ( StringUtility::starts_with( $meta_key, '_' ) ) {
						$underscore_keys[ $prefix . $meta_key ] = esc_html( $meta_key );
					} else {
						$standard_keys[ $prefix . $meta_key ] = esc_html( $meta_key );
					}
				}
				$added_keys[ $key_without_underscore ] = esc_html( $meta_key );
			}
		}

		// Build the final options with proper subgroup structure for et-vb-subgroup-title.
		$final_options = [];

		// Add ACF fields section with subgroup structure.
		if ( ! empty( $acf_keys ) ) {
			// Convert string values to proper option format.
			$acf_options = [];
			foreach ( $acf_keys as $key => $value ) {
				if ( is_string( $value ) ) {
					$acf_options[ $key ] = [ 'label' => esc_html( $value ) ];
				} else {
					$acf_options[ $key ] = $value;
				}
			}

			$final_options[ $prefix . 'group_acf' ] = [
				'label'   => esc_html__( 'Advanced Custom Fields', 'et_builder_5' ),
				'options' => $acf_options,
			];
		}

		// Add standard meta keys section with subgroup structure.
		if ( ! empty( $standard_keys ) ) {
			// Convert string values to proper option format.
			$standard_options = [];
			foreach ( $standard_keys as $key => $value ) {
				if ( is_string( $value ) ) {
					$standard_options[ $key ] = [ 'label' => esc_html( $value ) ];
				} else {
					$standard_options[ $key ] = $value;
				}
			}

			$final_options[ $prefix . 'group_standard' ] = [
				'label'   => esc_html__( 'Standard Meta Keys', 'et_builder_5' ),
				'options' => $standard_options,
			];
		}

		// Add underscore keys section with subgroup structure.
		if ( ! empty( $underscore_keys ) ) {
			// Convert string values to proper option format.
			$underscore_options = [];
			foreach ( $underscore_keys as $key => $value ) {
				if ( is_string( $value ) ) {
					$underscore_options[ $key ] = [ 'label' => esc_html( $value ) ];
				} else {
					$underscore_options[ $key ] = $value;
				}
			}

			$final_options[ $prefix . 'group_underscore' ] = [
				'label'   => esc_html__( 'Non-Standard Meta Keys', 'et_builder_5' ),
				'options' => $underscore_options,
			];
		}

		return $final_options;
	}

	/**
	 * Check if the query type is a repeater query.
	 *
	 * @since ??
	 *
	 * @param string $query_type The query type.
	 *
	 * @return bool True if it's a repeater query, false otherwise.
	 */
	public static function is_repeater_query( $query_type ) {
		return StringUtility::starts_with( $query_type, 'repeater_' );
	}

	/**
	 * Build repeater query arguments for repeater queries.
	 *
	 * @since ??
	 *
	 * @param array $params Extracted parameters from loop settings.
	 *
	 * @return array The query result array.
	 */
	public static function build_repeater_query_args( $params ) {
		$query_type    = $params['query_type'];
		$repeater_name = '';

		// Remove 'repeater_' prefix.
		$repeater_name = substr( $query_type, 9 );

		$query_args = [
			'repeater_name'     => $repeater_name,
			'repeater_per_page' => $params['post_per_page'],
			'repeater_offset'   => $params['post_offset'],
		];

		$result = [
			'loop_enabled' => $params['loop_enabled'],
			'query_args'   => $query_args,
			'query_type'   => $query_type,
			'post_type'    => $repeater_name,
		];

		return $result;
	}

	/**
	 * Execute a repeater query.
	 *
	 * @since ??
	 *
	 * @param array $query_args Query arguments.
	 *
	 * @return array The executed query result array.
	 */
	public static function execute_repeater_query( $query_args ) {
		$repeater_response = self::get_repeater_results( $query_args );

		return [
			'results'     => $repeater_response['items'] ?? [],
			'total_pages' => $repeater_response['total_pages'],
		];
	}

	/**
	 * Get repeater query results.
	 *
	 * @since ??
	 *
	 * @param array $params Query parameters.
	 *
	 * @return array Repeater query results.
	 */
	public static function get_repeater_results( array $params ): array {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return self::get_empty_repeater_pagination_response( $params );
		}

		$repeater_field = $params['repeater_name'] ?? '';
		if ( empty( $repeater_field ) ) {
			return self::get_empty_repeater_pagination_response( $params );
		}

		$pagination = self::get_repeater_pagination_params( $params );

		$repeater_field_object = self::find_repeater_field_object( $repeater_field );
		if ( ! $repeater_field_object ) {
			return self::get_empty_repeater_pagination_response( $params );
		}

		$repeater_full_name = $repeater_field_object['_full_name'] ?? $repeater_field_object['name'] ?? '';

		$current_post_id   = QueryResultsController::get_current_post_id( $params );
		$query_all_posts   = true === ( $params['query_all_posts'] ?? false );
		$current_post_type = 0 < $current_post_id ? get_post_type( $current_post_id ) : '';
		$targets_post_type = false;
		if ( $current_post_type && ! empty( $repeater_field_object['_field_group'] ) ) {
			$targets_post_type = self::_field_group_targets_post_type( $repeater_field_object['_field_group'], $current_post_type );
		}

		if ( $query_all_posts && $targets_post_type ) {
			$query_all_posts = false;
		}

		$all_repeater_values = [];
		if ( $query_all_posts && 0 < $current_post_id ) {
			$single_post_values = self::get_all_repeater_values_from_database( $repeater_field_object, $current_post_id, $repeater_full_name, false );
			if ( ! empty( $single_post_values ) ) {
				$all_repeater_values = $single_post_values;
			}
		}

		if ( empty( $all_repeater_values ) ) {
			$all_repeater_values = self::get_all_repeater_values_from_database( $repeater_field_object, $current_post_id, $repeater_full_name, $query_all_posts );
		}
		if ( empty( $all_repeater_values ) ) {
			return self::get_empty_repeater_pagination_response( $params );
		}

		$processed_values = self::process_repeater_values( $all_repeater_values, $repeater_field_object );
		$total_items      = count( $processed_values );
		$items            = array_slice( $processed_values, $pagination['offset'], $pagination['per_page'] );

		return self::format_repeater_pagination_response(
			$items,
			$total_items,
			$pagination['per_page'],
			$pagination['page'],
			$pagination['offset']
		);
	}

	/**
	 * Get pagination parameters for repeater queries.
	 *
	 * @since ??
	 *
	 * @param array $params Query parameters.
	 *
	 * @return array Array containing per_page, page, and offset.
	 */
	public static function get_repeater_pagination_params( array $params ): array {
		$per_page = isset( $params['per_page'] ) && '' !== $params['per_page'] ?
			absint( $params['per_page'] ) : self::DEFAULT_REPEATER_PER_PAGE;

		if ( isset( $params['repeater_per_page'] ) && '' !== $params['repeater_per_page'] ) {
			$per_page = absint( $params['repeater_per_page'] );
		}

		// Ensure per_page is at least 1 to prevent errors.
		$per_page = max( 1, $per_page );

		$page = isset( $params['page'] ) ? absint( $params['page'] ) : 1;
		$page = max( 1, $page );

		if ( isset( $params['repeater_offset'] ) && '' !== $params['repeater_offset'] ) {
			$offset = absint( $params['repeater_offset'] );
			$page   = floor( $offset / $per_page ) + 1;
			$page   = max( 1, $page );
		} else {
			$offset = ( $page - 1 ) * $per_page;
		}

		return [
			'per_page' => $per_page,
			'page'     => $page,
			'offset'   => $offset,
		];
	}

	/**
	 * Get empty pagination response for repeater queries.
	 *
	 * @since ??
	 *
	 * @param array $params Query parameters.
	 *
	 * @return array Empty pagination response.
	 */
	public static function get_empty_repeater_pagination_response( array $params ): array {
		$pagination = self::get_repeater_pagination_params( $params );
		return self::format_repeater_pagination_response( [], 0, $pagination['per_page'], $pagination['page'], $pagination['offset'] );
	}

	/**
	 * Format pagination response for repeater queries.
	 *
	 * @since ??
	 *
	 * @param array $items      Result items.
	 * @param int   $total      Total number of items.
	 * @param int   $per_page   Items per page.
	 * @param int   $page       Current page.
	 * @param int   $offset     Applied offset.
	 *
	 * @return array Formatted response with pagination info.
	 */
	public static function format_repeater_pagination_response( array $items, int $total, int $per_page, int $page, int $offset = 0 ): array {
		// Adjust total items and pages when offset is applied.
		$adjusted_total = max( 0, $total - $offset );
		$adjusted_pages = $adjusted_total > 0 ? ceil( $adjusted_total / $per_page ) : 0;

		return [
			'items'       => $items,
			'total_items' => $adjusted_total,
			'total_pages' => $adjusted_pages,
			'page'        => $page,
			'per_page'    => $per_page,
		];
	}

	/**
	 * Find repeater field object by name, key, or label.
	 *
	 * @since ??
	 *
	 * @param string $repeater_field The repeater field identifier.
	 *
	 * @return array|null The repeater field object or null if not found.
	 */
	public static function find_repeater_field_object( string $repeater_field ): ?array {
		$field_groups = acf_get_field_groups();

		if ( empty( $field_groups ) ) {
			return null;
		}

		foreach ( $field_groups as $group ) {
			$fields = acf_get_fields( $group['ID'] );
			if ( empty( $fields ) ) {
				continue;
			}

			$found_field = self::_find_repeater_field_in_fields( $fields, $repeater_field );
			if ( $found_field ) {
				$found_field['_field_group'] = $group;
				return $found_field;
			}
		}

		return null;
	}

	/**
	 * Check if a field group targets a specific post type.
	 *
	 * @since ??
	 *
	 * @param array  $field_group Field group data.
	 * @param string $post_type   Post type to check.
	 *
	 * @return bool True when the group targets the post type.
	 */
	private static function _field_group_targets_post_type( array $field_group, string $post_type ): bool {
		if ( empty( $field_group['location'] ) || ! is_array( $field_group['location'] ) ) {
			return false;
		}

		foreach ( $field_group['location'] as $location_group ) {
			if ( ! is_array( $location_group ) ) {
				continue;
			}

			$has_post_type_rule = false;
			$matches_group      = true;

			foreach ( $location_group as $rule ) {
				if ( empty( $rule['param'] ) || 'post_type' !== $rule['param'] ) {
					continue;
				}

				$has_post_type_rule = true;
				$operator           = $rule['operator'] ?? '==';
				$rule_value         = $rule['value'] ?? '';

				if ( '==' === $operator && $post_type !== $rule_value ) {
					$matches_group = false;
				}

				if ( '!=' === $operator && $post_type === $rule_value ) {
					$matches_group = false;
				}
			}

			if ( $has_post_type_rule && $matches_group ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Recursively search for a repeater field within a given fields array.
	 *
	 * @param array  $fields        Array of ACF fields to search through.
	 * @param string $repeater_name The name of the repeater field to find (may include group prefixes).
	 * @param string $name_prefix   Current name prefix for nested fields.
	 * @return array|null The repeater field object if found, null otherwise.
	 */
	private static function _find_repeater_field_in_fields( array $fields, string $repeater_name, string $name_prefix = '' ): ?array {
		foreach ( $fields as $field ) {
			$full_field_name = $name_prefix . $field['name'];

			if ( 'repeater' === $field['type'] ) {
				// Check if this repeater matches by full name, base name, key, or label.
				if ( $full_field_name === $repeater_name ||
					$field['name'] === $repeater_name ||
					$field['key'] === $repeater_name ||
					$field['label'] === $repeater_name
				) {
					$field['_full_name'] = $full_field_name;
					return $field;
				}

				$repeater_name_lower = strtolower( $repeater_name );
				if (
					strtolower( $full_field_name ) === $repeater_name_lower ||
					strtolower( $field['name'] ) === $repeater_name_lower ||
					strtolower( $field['label'] ) === $repeater_name_lower
				) {
					$field['_full_name'] = $full_field_name;
					return $field;
				}
			} elseif ( 'group' === $field['type'] && isset( $field['sub_fields'] ) ) {
				// Recursively search inside group fields for nested repeaters.
				// Build the prefix for nested fields: current_prefix + group_name + underscore.
				$nested_prefix = $name_prefix . $field['name'] . '_';
				$nested_result = self::_find_repeater_field_in_fields( $field['sub_fields'], $repeater_name, $nested_prefix );
				if ( $nested_result ) {
					return $nested_result;
				}
			}
		}

		return null;
	}

	/**
	 * Get all repeater field values from database efficiently.
	 *
	 * Uses direct wpdb queries for performance - more faster than WP_Query approach.
	 * Only retrieves needed postmeta data, not full post objects.
	 *
	 * @since ??
	 *
	 * @param array  $repeater_field_object The validated repeater field object from ACF.
	 * @param int    $current_post_id       Current post ID for context (default: 0).
	 * @param string $full_repeater_name    The full repeater name (may include group prefixes).
	 * @param bool   $query_all_posts       Whether to query all posts or just current post.
	 *
	 * @return array Array of all repeater values from all posts or options.
	 */
	public static function get_all_repeater_values_from_database( array $repeater_field_object, int $current_post_id = 0, string $full_repeater_name = '', bool $query_all_posts = false ): array {
		global $wpdb;

		// Use the full repeater name if provided (for nested repeaters), otherwise use field object name.
		$repeater_name = ! empty( $full_repeater_name ) ? $full_repeater_name : $repeater_field_object['name'];
		$sub_fields    = $repeater_field_object['sub_fields'] ?? [];

		if ( empty( $repeater_name ) || ! is_string( $repeater_name ) ) {
			return [];
		}

		$is_theme_options = self::is_repeater_field_assigned_to_theme_options( $repeater_field_object );

		if ( $is_theme_options ) {
			return self::get_theme_options_repeater_values( $repeater_name, $sub_fields );
		}

		if ( ! $query_all_posts && $current_post_id <= 0 ) {
			return [];
		}

		if ( $query_all_posts ) {
			// Loop Builder context: query all posts with the repeater field, excluding revisions.
			// Use JOIN to filter out revision posts efficiently at database level.
			// Pagination is handled after fetching all posts to ensure all data is accessible.
			$posts_with_repeater = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT pm.post_id, CAST(pm.meta_value AS UNSIGNED) as row_count
					FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
					WHERE pm.meta_key = %s
					AND CAST(pm.meta_value AS UNSIGNED) > 0
					AND p.post_type != 'revision'
					ORDER BY pm.post_id",
					$repeater_name
				)
			);
		} else {
			// Dynamic Content context: query only current post (no JOIN needed for single post lookup).
			$posts_with_repeater = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_id, CAST(meta_value AS UNSIGNED) as row_count
					FROM {$wpdb->postmeta}
					WHERE meta_key = %s
					AND post_id = %d
					AND CAST(meta_value AS UNSIGNED) > 0",
					$repeater_name,
					$current_post_id
				)
			);
		}

		if ( empty( $posts_with_repeater ) ) {
			return [];
		}

		$all_repeater_values = [];

		foreach ( $posts_with_repeater as $post_data ) {
			$post_id   = absint( $post_data->post_id );
			$row_count = absint( $post_data->row_count );

			$post          = get_post( $post_id );
			$can_read_post = $post ? current_user_can( 'read_post', $post_id ) : false;
			$is_viewable   = $post ? is_post_publicly_viewable( $post ) : false;

			if ( ! $post || ( ! $is_viewable && ! $can_read_post ) ) {
				continue;
			}

			// Performance limit: Prevent extremely large repeaters from causing memory issues.
			$row_count = min( $row_count, 1000 );

			for ( $i = 0; $i < $row_count; $i++ ) {
				$row_data = [
					'_post_id'   => $post_id,
					'_row_index' => $i,
				];

				foreach ( $sub_fields as $sub_field ) {
					$sub_field_name = $sub_field['name'];
					$meta_key_raw   = $repeater_name . '_' . $i . '_' . $sub_field_name;
					$meta_key       = $meta_key_raw;
					$fallback_key   = sanitize_key( $meta_key_raw );

					if ( in_array( $sub_field['type'], [ 'image', 'true_false' ], true ) && function_exists( 'get_field' ) ) {
						$value = get_field( $meta_key_raw, $post_id );

						if ( false === $value || null === $value ) {
							$value = self::_get_post_meta_with_fallback( $post_id, $meta_key, $meta_key_raw, $fallback_key );
						}
					} else {
						$value = self::_get_post_meta_with_fallback( $post_id, $meta_key, $meta_key_raw, $fallback_key );

						if ( 'link' === $sub_field['type'] ) {
							$value = $value['url'] ?? '';
						}
					}

					$row_data[ $sub_field_name ] = $value;
				}

				$all_repeater_values[] = $row_data;
			}
		}

		return $all_repeater_values;
	}

	/**
	 * Process repeater values to handle special field types.
	 *
	 * @since ??
	 *
	 * @param array $repeater_values The raw repeater values.
	 * @param array $repeater_field_object The repeater field object.
	 *
	 * @return array Processed repeater values.
	 */
	public static function process_repeater_values( array $repeater_values, array $repeater_field_object ): array {
		if ( empty( $repeater_values ) || empty( $repeater_field_object['sub_fields'] ) ) {
			return [];
		}

		return array_map(
			function ( $row ) use ( $repeater_field_object ) {
				$processed_row = [
					'_post_id'   => $row['_post_id'] ?? 0,
					'_row_index' => $row['_row_index'] ?? 0,
				];

				foreach ( $repeater_field_object['sub_fields'] as $sub_field ) {
					$field_name  = $sub_field['name'];
					$field_value = $row[ $field_name ] ?? null;

					$processed_row[ $field_name ] = self::process_repeater_field_value( $field_value, $sub_field );
				}

				return $processed_row;
			},
			$repeater_values
		);
	}

	/**
	 * Process a single repeater field value based on its type.
	 *
	 * @since ??
	 *
	 * @param object $value The field value to process.
	 * @param array  $field The field configuration.
	 *
	 * @return object The processed field value.
	 */
	public static function process_repeater_field_value( $value, array $field ) {
		if ( 'image' === $field['type'] ) {
			if ( is_array( $value ) ) {
				return ! empty( $value['url'] ) ? esc_url( $value['url'] ) : '';
			} elseif ( is_numeric( $value ) && $value > 0 ) {
				$url = wp_get_attachment_url( absint( $value ) );
				return $url ? esc_url( $url ) : '';
			} elseif ( is_string( $value ) && ! empty( $value ) ) {
				return esc_url( $value );
			}

			return '';
		}

		if ( 'true_false' === $field['type'] ) {
			// Convert raw database values (0/1) to boolean strings.
			return $value ? 'true' : 'false';
		}

		$acf_field_type = $field['type'] ?? '';
		if (
			in_array( $acf_field_type, [ 'date_picker', 'date_time_picker', 'time_picker' ], true ) &&
			is_string( $value ) &&
			'' !== $value
		) {
			$processed = self::process_acf_date_field( $value, $acf_field_type, [] );
			return $processed['value'];
		}

		return $value;
	}

	/**
	 * Process ACF date field value and format settings.
	 *
	 * Converts ACF date field values to proper format and automatically appends time format
	 * for date_time_picker fields when format string doesn't include time characters.
	 *
	 * @since ??
	 *
	 * @param mixed  $value         The date value to process.
	 * @param string $acf_field_type The ACF field type.
	 * @param array  $settings      The date format settings.
	 *
	 * @return array{value: string, settings: array} Processed value and modified settings.
	 */
	public static function process_acf_date_field( $value, string $acf_field_type, array $settings ): array {
		// - date_picker: Ymd format (8 digits, e.g., "20250115").
		// - date_time_picker: YmdHis format (14 digits, e.g., "20250115143000") or Y-m-d H:i:s format.
		if ( 'date_picker' === $acf_field_type && is_string( $value ) ) {
			// Use DateTime::createFromFormat() to parse Ymd format (e.g., "20250115").
			$date_time = \DateTime::createFromFormat( 'Ymd', $value );
			if ( false !== $date_time ) {
				$value = $date_time->format( 'Y-m-d' );
			}
		} elseif ( 'date_time_picker' === $acf_field_type && is_string( $value ) ) {
			// Try 14-digit YmdHis format first (e.g., "20250115143000").
			$date_time = \DateTime::createFromFormat( 'YmdHis', $value );
			if ( false !== $date_time ) {
				$value = $date_time->format( 'Y-m-d H:i:s' );
			} elseif ( str_contains( $value, 'T' ) ) {
				// Try ISO format (e.g., "2025-11-20T17:13:18").
				// Convert T to space for strtotime() compatibility.
				$value = str_replace( 'T', ' ', $value );
			}
		} elseif ( 'time_picker' === $acf_field_type && is_string( $value ) ) {
			// Time picker doesn't have date, so we'll use today's date with the time.
			$today      = current_time( 'Y-m-d' );
			$time_value = $value;
			// If time doesn't include seconds, add them.
			if ( strlen( $time_value ) === 5 ) {
				$time_value .= ':00';
			}
			$value = $today . ' ' . $time_value;
		}

		$format_settings = $settings;
		if ( 'date_time_picker' === $acf_field_type ) {
			$format = $settings['date_format'] ?? 'default';
			if ( 'custom' === $format && ! empty( $settings['custom_date_format'] ) ) {
				$format_string = $settings['custom_date_format'];
			} elseif ( 'default' !== $format ) {
				$format_string = $format;
			} else {
				$format_string = '';
			}

			// PHP date format time characters: H, h, g, G, i, s, a, A, B, v.
			if ( ! empty( $format_string ) && ! preg_match( '/[HhGgisAaBv]/', $format_string ) ) {
				// Append default time format: " g:i A" (e.g., " 5:13 PM").
				if ( 'custom' === $format ) {
					$format_settings['custom_date_format'] = $format_string . ' g:i A';
				} else {
					$format_settings['date_format'] = $format_string . ' g:i A';
				}
			}
		}

		return [
			'value'    => $value,
			'settings' => $format_settings,
		];
	}

	/**
	 * Gets the content for a repeater field.
	 *
	 * @since ??
	 *
	 * @param string $name        The repeater field name.
	 * @param object $loop_object The loop object.
	 * @param array  $settings    Optional. Field settings for customization. Default [].
	 *
	 * @return string The repeater field content.
	 */
	public static function get_repeater_field_content( string $name, $loop_object, array $settings = [] ): string {
		// e.g., loop_acf_dynamic_repeater_name|||artist_name -> artist_name.
		$field = explode( '|||', $name )[1] ?? '';
		$value = '';

		if ( is_array( $loop_object ) ) {
			$value = $loop_object[ $field ] ?? '';
		} elseif ( is_object( $loop_object ) && property_exists( $loop_object, $field ) ) {
			$value = $loop_object->$field ?? '';
		}

		$acf_field_type = $settings['acf_type'] ?? '';
		$is_date_field  = in_array( $acf_field_type, [ 'date_picker', 'date_time_picker', 'time_picker' ], true );

		if ( ! is_array( $value ) && is_string( $value ) && '' !== $value && ! empty( $field ) && $is_date_field && 'default' !== ( $settings['date_format'] ?? 'default' ) ) {
			$processed       = self::process_acf_date_field( $value, $acf_field_type, $settings );
			$formatted_value = ModuleUtils::format_date( $processed['value'], $processed['settings'] );
			$value           = ! empty( $formatted_value ) ? $formatted_value : $value;
		}

		// Handle Raw HTML setting - only escape if Raw HTML is disabled.
		if ( 'on' !== ( $settings['enable_html'] ?? 'off' ) && ( ! $is_date_field || 'default' === ( $settings['date_format'] ?? 'default' ) ) ) {
			// Only escape HTML if date formatting wasn't applied.
			$value = esc_html( $value );
		}

		return $value;
	}

	/**
	 * Check if a repeater field is assigned to Theme Options (options page).
	 *
	 * @since ??
	 *
	 * @param array $repeater_field_object The ACF repeater field object.
	 *
	 * @return bool True if assigned to Theme Options, false otherwise.
	 */
	public static function is_repeater_field_assigned_to_theme_options( array $repeater_field_object ) {
		if ( ! function_exists( 'acf_get_field_group' ) ) {
			return false;
		}

		// Get the parent field group ID from the field object.
		$parent_group_id = $repeater_field_object['parent'] ?? null;
		if ( empty( $parent_group_id ) ) {
			return false;
		}

		$field_group = acf_get_field_group( $parent_group_id );
		if ( ! $field_group || empty( $field_group['location'] ) ) {
			return false;
		}

		// Check location rules for options_page assignment.
		foreach ( $field_group['location'] as $rule_group ) {
			foreach ( $rule_group as $rule ) {
				if ( isset( $rule['param'] ) && 'options_page' === $rule['param'] ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get repeater field values from Theme Options (options table).
	 *
	 * @since ??
	 *
	 * @param string $repeater_name The repeater field name.
	 * @param array  $sub_fields    The repeater sub-fields configuration.
	 *
	 * @return array Array of repeater values from Theme Options.
	 */
	public static function get_theme_options_repeater_values( string $repeater_name, array $sub_fields ) {
		$option_key = 'options_' . $repeater_name;
		$row_count  = absint( get_option( $option_key, 0 ) );

		if ( $row_count <= 0 ) {
			return [];
		}

		// Performance limit: Prevent extremely large repeaters from causing memory issues.
		$row_count = min( $row_count, 1000 );

		$all_repeater_values = [];

		for ( $i = 0; $i < $row_count; $i++ ) {
			$row_data = [
				'_options_page' => true, // Identifier for Theme Options data.
				'_row_index'    => $i,
				'_post_id'      => 0, // Theme Options don't have a post ID, but set to 0 for compatibility.
			];

			foreach ( $sub_fields as $sub_field ) {
				$sub_field_name = $sub_field['name'];
				$option_key     = 'options_' . $repeater_name . '_' . $i . '_' . $sub_field_name;

				if (
					in_array( $sub_field['type'], [ 'image', 'true_false' ], true ) &&
					function_exists( 'get_field' )
				) {
					$field_key = $repeater_name . '_' . $i . '_' . $sub_field_name;
					$value     = get_field( $field_key, 'option' );

					if ( false === $value || null === $value ) {
						$value = get_option( $option_key, '' );
					}
				} else {
					$value = get_option( $option_key, '' );

					if ( 'link' === $sub_field['type'] && is_array( $value ) ) {
						$value = $value['url'] ?? '';
					}
				}

				$row_data[ $sub_field_name ] = $value;
			}

			$all_repeater_values[] = $row_data;
		}

		return $all_repeater_values;
	}

	/**
	 * Get post meta with a sanitized fallback key when needed.
	 *
	 * @since ??
	 *
	 * @param int    $post_id       Post ID.
	 * @param string $meta_key      Primary meta key.
	 * @param string $meta_key_raw  Raw meta key (preserves casing).
	 * @param string $fallback_key  Sanitized fallback meta key.
	 *
	 * @return mixed Meta value.
	 */
	private static function _get_post_meta_with_fallback( int $post_id, string $meta_key, string $meta_key_raw, string $fallback_key ) {
		$value = get_post_meta( $post_id, $meta_key, true );

		if (
			'' === $value &&
			$meta_key_raw !== $fallback_key &&
			! metadata_exists( 'post', $post_id, $meta_key )
		) {
			$value = get_post_meta( $post_id, $fallback_key, true );
		}

		return $value;
	}

	/**
	 * Format a taxonomy field value from ID to term names/links.
	 *
	 * @since ??
	 *
	 * @param mixed $value        The taxonomy field value (term ID or array of IDs).
	 * @param array $field_object The ACF field object containing field configuration.
	 *
	 * @return string The formatted taxonomy value as comma-separated term names or links.
	 */
	private static function format_taxonomy_field_value( $value, array $field_object ): string { // phpcs:ignore ET.NamingConventions.VisibilityUnderscore.Private_Method -- Method name follows existing codebase conventions.
		if ( empty( $value ) || ! isset( $field_object['taxonomy'] ) ) {
			return '';
		}

		$values   = is_array( $value ) ? $value : [ $value ];
		$terms    = [];
		$term_ids = [];

		foreach ( $values as $val ) {
			if ( is_object( $val ) && isset( $val->term_id ) ) {
				$terms[] = $val;
			} elseif ( is_numeric( $val ) && $val > 0 ) {
				$term_ids[] = absint( $val );
			}
		}

		if ( ! empty( $term_ids ) ) {
			$fetched_terms = get_terms(
				[
					'taxonomy' => $field_object['taxonomy'],
					'include'  => $term_ids,
					'orderby'  => 'include',
				]
			);

			if ( ! empty( $fetched_terms ) && ! is_wp_error( $fetched_terms ) ) {
				$terms = array_merge( $terms, $fetched_terms );
			}
		}

		if ( empty( $terms ) ) {
			return '';
		}

		$term_names = array_map(
			function ( $term ) {
				return esc_html( $term->name );
			},
			$terms
		);

		return implode( ', ', $term_names );
	}

	/**
	 * Format taxonomy field value with HTML links.
	 * This version always generates HTML links for ACF taxonomy fields.
	 *
	 * @since ??
	 *
	 * @param mixed $value        The ACF field value (WP_Term objects or term IDs).
	 * @param array $field_object The ACF field object configuration.
	 *
	 * @return string Formatted taxonomy terms with HTML links.
	 */

	/**
	 * Format taxonomy field value with links.
	 *
	 * @since ??
	 *
	 * @param mixed $value       Field value.
	 * @param array $field_object Field object.
	 *
	 * @return string Formatted value with links.
	 */
	private static function format_taxonomy_field_value_with_links( $value, array $field_object ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter,ET.NamingConventions.VisibilityUnderscore.Private_Method -- Parameter reserved for future use. Method name follows existing codebase conventions.
		if ( empty( $value ) ) {
			return '';
		}

		$terms  = [];
		$values = is_array( $value ) ? $value : [ $value ];

		foreach ( $values as $val ) {
			if ( is_object( $val ) && isset( $val->term_id ) ) {
				$terms[] = $val;
			} elseif ( is_numeric( $val ) && $val > 0 ) {
				$term = get_term( absint( $val ) );
				if ( ! is_wp_error( $term ) && $term ) {
					$terms[] = $term;
				}
			}
		}

		if ( empty( $terms ) ) {
			return '';
		}

		$term_links = [];
		foreach ( $terms as $term ) {
			$term_link = get_term_link( $term );
			if ( ! is_wp_error( $term_link ) ) {
				$term_links[] = sprintf( '<a href="%s">%s</a>', esc_url( $term_link ), esc_html( $term->name ) );
			} else {
				$term_links[] = esc_html( $term->name );
			}
		}

		return implode( ', ', $term_links );
	}

	/**
	 * Filter custom meta values to generate HTML links for ACF taxonomy fields.
	 *
	 * @since ??
	 *
	 * @param string      $value    The custom meta value.
	 * @param string|null $meta_key The meta key, can be null.
	 * @param int         $post_id  The post ID.
	 *
	 * @return string The processed meta value with taxonomy fields converted to HTML links.
	 */
	public static function filter_custom_meta_value( string $value, ?string $meta_key, int $post_id ): string {
		if ( ! self::is_acf_active() || ! function_exists( 'get_field_object' ) || empty( $meta_key ) ) {
			return $value;
		}

		// When rendering a Theme Builder archive template, the legacy ACF compatibility code
		// (in `includes/builder/framework-plugin-compat/advanced-custom-fields.php`) runs before this filter
		// and attempts to format the value using the Layout ID ($post_id) instead of the Term ID.
		// Because `get_field_object` fails with the wrong ID, it treats the array value (from Image Array format)
		// as a generic array and flattens it into a comma-separated string (e.g. "ID, filename, title, URL...").
		//
		// We detect this scenario here and re-resolve the value correctly using the term context.
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$term_context = 'term_' . $term->term_id;
				$field_name   = StringUtility::starts_with( $meta_key, '_' ) ? ltrim( $meta_key, '_' ) : $meta_key;
				$field_object = get_field_object( $field_name, $term_context );

				if ( is_array( $field_object ) && 'image' === ( $field_object['type'] ?? '' ) ) {
					// Re-fetch the value using the correct term context.
					$term_value = get_field( $field_name, $term_context );

					// Normalize ACF Image Array format to URL.
					if ( is_array( $term_value ) && isset( $term_value['url'] ) ) {
						return esc_url( $term_value['url'] );
					}

					// Normalize ACF Image ID format to URL.
					if ( is_numeric( $term_value ) && $term_value > 0 ) {
						$image_url = wp_get_attachment_url( absint( $term_value ) );
						if ( $image_url ) {
							return esc_url( $image_url );
						}
					}

					// Normalize URL string.
					if ( is_string( $term_value ) && filter_var( $term_value, FILTER_VALIDATE_URL ) ) {
						return esc_url( $term_value );
					}
				}
			}
		}

		$field_name   = StringUtility::starts_with( $meta_key, '_' ) ? ltrim( $meta_key, '_' ) : $meta_key;
		$field_object = get_field_object( $field_name, $post_id );

		if ( is_array( $field_object ) && 'image' === ( $field_object['type'] ?? '' ) ) {
			// If the value contains attachment info like "(590, 2560×1246)", extract just the URL.
			// https://regex101.com/r/PVlY4T/1 - regex.
			if ( is_string( $value ) && preg_match( '/^(https?:\/\/[^\s]+)\s*\([^)]+\)/', $value, $matches ) ) {
				$extracted_url = $matches[1];
				if ( filter_var( $extracted_url, FILTER_VALIDATE_URL ) ) {
					return esc_url( $extracted_url );
				}
			}

			// Handle case where raw ACF Image ID is passed to filter (Image ID return format).
			if ( is_numeric( $value ) && $value > 0 ) {
				$image_url = wp_get_attachment_url( absint( $value ) );
				if ( $image_url && filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
					return esc_url( $image_url );
				}
				return '';
			}

			if ( is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ) {
				return esc_url( $value );
			}

			return '';
		}

		if ( is_array( $field_object ) && 'taxonomy' === ( $field_object['type'] ?? '' ) ) {
			$field_value = get_field( $field_name, $post_id );

			if ( ! empty( $field_value ) ) {
				$formatted_value = self::format_taxonomy_field_value_with_links( $field_value, $field_object );
				return ! empty( $formatted_value ) ? $formatted_value : $value;
			}
		}

		return $value;
	}
}
