<?php
/**
 * Module: DynamicContentOptions class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Layout\Components\DynamicContent;

use ET\Builder\Packages\Module\Layout\Components\DynamicData\DynamicData;


if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Module: DynamicContentOptions class.
 *
 * To use the dynamic content feature, we need to generate the options first. The options
 * will be used in the Visual Builder and the Frontend. This class is responsible to
 * generate the dynamic content options. This includes:
 * - All options that contains:
 *   - Built-in options.
 *   - Product options.
 *   - Custom meta options that includes:
 *     - Most used meta keys in the site.
 *     - Used meta keys on the post content.
 *
 * In addition, all options are sorted by the `group` and the `id` as fallback.
 *
 * @since ??
 */
class DynamicContentOptions {
	/**
	 * Default limit for most-used meta key queries.
	 *
	 * @since ??
	 *
	 * @var int
	 */
	private const _DEFAULT_META_KEYS_LIMIT = 50;

	/**
	 * Flag to prevent recursive calls to get_options().
	 *
	 * @since ??
	 *
	 * @var bool
	 */
	private static $_is_getting_options = false;

	/**
	 * Get an array of options for dynamic content elements.
	 *
	 * This function retrieves an array of options for dynamic content elements based on the provided post ID and context.
	 * This function runs the options through the `divi_module_dynamic_content_options` filter hook.
	 *
	 * @since ??
	 *
	 * @param int|string $post_id The ID of the post.
	 * @param string     $context The context in which the options are retrieved. Valid values:
	 *                            - `'edit'`: Visual Builder edit mode. Used when user lacks permission to view custom fields.
	 *                              Custom field dropdown options are hidden, but "Manual Input" option remains available.
	 *                            - `'display'`: Frontend display mode or Visual Builder when user has permission.
	 *                              All custom field options (including dropdown) are available.
	 *
	 * @return array An array of options for dynamic content elements.
	 *
	 * @example:
	 * ```php
	 *  // Get the options for dynamic content elements in edit context for a post with ID 123.
	 *  // Custom field dropdown options will be hidden if user lacks permission.
	 *  $options = DynamicContentOptions::get_options( 123, 'edit' );
	 * ```
	 *
	 * @example:
	 * ```php
	 *  // Get the options for dynamic content elements in display context for a post with ID 456.
	 *  // All custom field options will be available.
	 *  $options = DynamicContentOptions::get_options( 456, 'display' );
	 * ```
	 */
	public static function get_options( $post_id, string $context ): array {
		// Prevent recursive calls to avoid infinite loops.
		if ( self::$_is_getting_options ) {
			return [];
		}

		// All dynamic content options.
		$dynamic_content_options = [];

		// Type cast variable for the filter hooks.
		$post_id = (int) $post_id;
		$context = (string) $context;

		// Set flag to prevent recursion.
		self::$_is_getting_options = true;

		try {
			/**
			 * Filter the dynamic content options.
			 *
			 * @since ??
			 *
			 * @param array  $dynamic_content_options Dynamic content options.
			 * @param int    $post_id                 Post Id.
			 * @param string $context                 Context e.g `edit`, `display`.
			 */
			$dynamic_content_options = apply_filters( 'divi_module_dynamic_content_options', $dynamic_content_options, $post_id, $context );

			$all_options = (array) $dynamic_content_options;
			foreach ( $all_options as $id => $option ) {
				$all_options[ $id ]['id'] = $id;
			}

			$all_option_keys = array_flip( array_keys( $all_options ) );

			// Sort options by group based on the existence `group` and the order of `id`.
			uasort(
				$all_options,
				function ( $first_option, $second_option ) use ( $all_option_keys ) {
					return self::get_sorted_options_comparison_result( $first_option, $second_option, $all_option_keys );
				}
			);

			return $all_options;
		} finally {
			// Always reset flag, even if an exception occurs.
			self::$_is_getting_options = false;
		}
	}

	/**
	 * Retrieve the limit for meta key usage queries.
	 *
	 * This helper fetches the filtered limit value used when retrieving the most used meta keys.
	 * It ensures the limit is always at least 1 to keep SQL queries valid.
	 *
	 * @since ??
	 *
	 * @param string $meta_type The meta type the query targets: 'post_loop', 'user_loop', 'term_loop', or 'post'.
	 *
	 * @return int Filtered meta key query limit with a minimum value of 1.
	 */
	private static function _get_meta_keys_limit( string $meta_type ): int {
		/**
		 * Filters the limit used when querying the most used meta keys for dynamic content.
		 *
		 * Allows you to raise or lower the number of meta keys retrieved for specific meta types.
		 * Returning a value lower than 1 will be clamped to 1 before use.
		 *
		 * @since ??
		 *
		 * @param int    $default_limit Default query limit (50).
		 * @param string $meta_type     Meta type for the query ('post_loop', 'user_loop', 'term_loop', or 'post').
		 *
		 * @return int The filtered meta key query limit with a minimum value of 1.
		 *
		 * @example:
		 * ```php
		 * add_filter( 'divi_module_dynamic_content_meta_query_limit', function( $limit, $meta_type ) {
		 *     // For post loop and user loop, return 150.
		 *     if ( in_array( $meta_type, [ 'post_loop', 'user_loop' ], true ) ) {
		 *         return 150;
		 *     }
		 *
		 *     // For post and term, return 100.
		 *     if ( 'post' === $meta_type ) {
		 *         return 100;
		 *     }
		 *
		 *     return 80;
		 * }, 10, 2 );
		 * ```
		 */
		$filtered_limit = apply_filters( 'divi_module_dynamic_content_meta_query_limit', self::_DEFAULT_META_KEYS_LIMIT, $meta_type );
		$limit          = (int) $filtered_limit;

		if ( 1 > $limit ) {
			return 1;
		}

		return $limit;
	}

	/**
	 * Get an array of the most used meta keys for a specific meta type.
	 *
	 * The number of meta keys returned can be modified via the
	 * `divi_module_dynamic_content_meta_query_limit` filter.
	 *
	 * @since ??
	 *
	 * @param string $meta_type The type of meta to retrieve: 'post', 'user', or 'term'.
	 *
	 * @return array An array of the most used meta keys for the specified type.
	 */
	public static function get_most_used_meta_keys_by_type( string $meta_type ): array {
		global $wpdb;

		$transient_key = "divi_module_dynamic_content_most_used_{$meta_type}_meta_keys";
		$cached_data   = get_transient( $transient_key );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		$sql   = '';
		$limit = self::_get_meta_keys_limit( $meta_type . '_loop' );

		switch ( $meta_type ) {
			case 'post':
				$public_post_types = array_keys( et_builder_get_public_post_types() );
				$post_types        = "'" . implode( "','", esc_sql( $public_post_types ) ) . "'";

				$sql = "SELECT pm.meta_key, COUNT(pm.meta_key) AS usage_count
						FROM {$wpdb->postmeta} pm
						INNER JOIN {$wpdb->posts} p
							ON p.ID = pm.post_id
							AND p.post_type IN ({$post_types})
						GROUP BY pm.meta_key
						HAVING SUM(CASE WHEN LEFT(pm.meta_value, 2) IN ('a:', 'O:', 'C:') THEN 1 ELSE 0 END) = 0
						ORDER BY usage_count DESC
						LIMIT {$limit}";
				break;

			case 'user':
				$sql = "SELECT um.meta_key, COUNT(um.meta_key) AS usage_count
						FROM {$wpdb->usermeta} um
						INNER JOIN {$wpdb->users} u
							ON u.ID = um.user_id
						AND um.meta_key NOT LIKE '%capabilities'
						AND um.meta_key NOT LIKE '%user_level'
						AND um.meta_key NOT LIKE '%session_tokens'
						GROUP BY um.meta_key
						HAVING SUM(CASE WHEN LEFT(um.meta_value, 2) IN ('a:', 'O:', 'C:') THEN 1 ELSE 0 END) = 0
						ORDER BY usage_count DESC
						LIMIT {$limit}";
				break;

			case 'term':
				// Get all public taxonomies.
				$public_taxonomies = get_taxonomies( [ 'public' => true ], 'names' );
				$taxonomies        = "'" . implode( "','", esc_sql( $public_taxonomies ) ) . "'";

				$sql = "SELECT tm.meta_key, COUNT(tm.meta_key) AS usage_count
						FROM {$wpdb->termmeta} tm
						INNER JOIN {$wpdb->terms} t
							ON t.term_id = tm.term_id
						INNER JOIN {$wpdb->term_taxonomy} tt
							ON tt.term_id = t.term_id
							AND tt.taxonomy IN ({$taxonomies})
						GROUP BY tm.meta_key
						HAVING SUM(CASE WHEN LEFT(tm.meta_value, 2) IN ('a:', 'O:', 'C:') THEN 1 ELSE 0 END) = 0
						ORDER BY usage_count DESC
						LIMIT {$limit}";
				break;

			default:
				return [];
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql query does not use users/visitor input.
		$meta_keys = $wpdb->get_col( $sql );

		set_transient( $transient_key, $meta_keys, 5 * MINUTE_IN_SECONDS );

		return $meta_keys;
	}

	/**
	 * Get the most used post meta keys in dynamic content.
	 *
	 * This is a convenience wrapper that delegates to get_most_used_meta_keys_by_type('post').
	 * It retrieves the most used post meta keys from the cache or database.
	 *
	 * The number of meta keys returned can be modified via the
	 * `divi_module_dynamic_content_meta_query_limit` filter.
	 *
	 * @since ??
	 *
	 * @return array An array of the most used post meta keys.
	 */
	public static function get_most_used_meta_keys(): array {
		global $wpdb;

		$most_used_meta_keys = get_transient( 'divi_module_dynamic_content_most_used_meta_keys' );

		if ( false !== $most_used_meta_keys ) {
			return $most_used_meta_keys;
		}

		// TODO feat(D5, Theme Builder): Replace `et_builder_get_public_post_types` once the Theme Builder is implemented in D5 [https://github.com/elegantthemes/Divi/issues/25149].
		$public_post_types = array_keys( et_builder_get_public_post_types() );
		$post_types        = "'" . implode( "','", esc_sql( $public_post_types ) ) . "'";
		$limit             = self::_get_meta_keys_limit( 'post' );

		$sql = "SELECT pm.meta_key, COUNT(pm.meta_key) AS usage_count
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p
					ON p.ID = pm.post_id
					AND p.post_type IN ({$post_types})
				WHERE pm.meta_key NOT LIKE '\_%'
				GROUP BY pm.meta_key
				HAVING SUM(CASE WHEN LEFT(pm.meta_value, 2) IN ('a:', 'O:', 'C:') THEN 1
				ELSE 0 END) = 0
				ORDER BY usage_count DESC
				LIMIT {$limit}";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql query does not use users/visitor input
		$most_used_meta_keys = $wpdb->get_col( $sql );

		set_transient( 'divi_module_dynamic_content_most_used_meta_keys', $most_used_meta_keys, 5 * MINUTE_IN_SECONDS );

		return $most_used_meta_keys;
	}

	/**
	 * Sorts the options and returns the comparison result.
	 *
	 * This function compares two options and determines their order based on the following rules:
	 *  - If only the first option has a top group and the second option does not, it returns -1.
	 *  - If only the second option has a top group and the first option does not, it returns 1.
	 *  - If both options have a top group and their groups are different, it returns the difference of their top group values.
	 *  - If none of the above conditions are met, it compares the order of the options based on their index in the $all_option_keys array.
	 *
	 * @since ??
	 *
	 * @param array $first_option      The first option to compare.
	 * @param array $second_option     The second option to compare.
	 * @param array $all_option_keys   The array that maps option keys to their indices.
	 *
	 * @return int   The comparison result as an integer.
	 *
	 * @example:
	 * ```php
	 * $first_option = [
	 *     'group'  => 'Default',
	 *     'id'     => 'option_one'
	 * ];
	 *
	 * $second_option = [
	 *     'group'  => 'Custom Fields',
	 *     'id'     => 'option_two'
	 * ];
	 *
	 * $all_option_keys = [
	 *     'option_one' => 0,
	 *     'option_two' => 1
	 * ];
	 *
	 * $result = get_sorted_options_comparison_result( $first_option, $second_option, $all_option_keys );
	 * echo $result;
	 * ```
	 *
	 * @output:
	 * ```php
	 *  -1
	 * ```
	 */
	public static function get_sorted_options_comparison_result( array $first_option, array $second_option, array $all_option_keys ): int {
		$top = array_flip(
			[
				'Default',
				// The 'Custom Fields' is the official group name for custom meta options
				// group. So, we keep the same group name and not rename it into 'Options'.
				__( 'Custom Fields', 'et_builder_5' ),
			]
		);

		$first_option_group   = $first_option['group'] ?? 'Default';
		$first_option_is_top  = isset( $top[ $first_option_group ] );
		$second_option_group  = $second_option['group'] ?? 'Default';
		$second_option_is_top = isset( $top[ $second_option_group ] );

		// If the `group` of first option is on top and second option is not simply return -1
		// to keep first option on current order.
		if ( $first_option_is_top && ! $second_option_is_top ) {
			return -1;
		}

		// Otherwise, if the `group` of second option is on top and first option is not simply
		// return 1 to move first option after the second option.
		if ( ! $first_option_is_top && $second_option_is_top ) {
			return 1;
		}

		// If both options are on top and the `group` are not the same, sort it based on the
		// top `group` order. `Default` should be above `Custom Fields`.
		if ( $first_option_is_top && $second_option_is_top && $first_option_group !== $second_option_group ) {
			return $top[ $first_option_group ] - $top[ $second_option_group ];
		}

		// Otherwise, sort it based on the order of the option `id`. The option `id` won't
		// be the same, so it may only return less or more than 0.
		$first_option_index  = $all_option_keys[ ( $first_option['id'] ?? '' ) ] ?? 0;
		$second_option_index = $all_option_keys[ ( $second_option['id'] ?? '' ) ] ?? 0;

		return $first_option_index - $second_option_index;
	}

	/**
	 * Get an array of the most used meta keys for the given post ID.
	 *
	 * The function first checks if the most used meta keys are cached in a transient before retrieving them from the database.
	 *
	 * The returned array is in the format of `[meta_key => meta_key_label]`.
	 * The returned array is sorted by the most used meta keys first.
	 * The returned array is limited to 10 meta keys.
	 * The returned array is cached for 5 minutes in as a transient (`divi_module_dynamic_content_most_used_meta_keys_{$post_id}`).
	 *
	 * @since ??
	 *
	 * @param int|null $post_id The ID of the post.
	 *
	 * @return array An array of the most used meta keys for the given post ID.
	 */
	public static function get_used_meta_keys( ?int $post_id ): array {
		$transient      = 'divi_module_dynamic_content_most_used_meta_keys_' . $post_id;
		$used_meta_keys = get_transient( $transient );

		if ( false !== $used_meta_keys ) {
			return $used_meta_keys;
		}

		// The most used meta keys will change from time to time so we will also retrieve
		// the used meta keys in the layout content to make sure that the previously selected
		// meta keys always stay in the list even if they are not in the most used meta keys
		// list anymore.
		$layout_post    = get_post( $post_id );
		$layout_content = $layout_post->post_content ?? '';
		$used_meta_keys = [];
		$string_values  = DynamicData::get_variable_values( $layout_content );

		foreach ( $string_values as $string_value ) {
			$data_value = DynamicData::get_data_value( $string_value );
			$type       = $data_value['type'] ?? '';
			$value      = $data_value['value'] ?? [];

			if ( 'content' !== $type || empty( $value ) ) {
				continue;
			}

			$name               = $value['name'] ?? '';
			$custom_meta_length = strlen( 'custom_meta_' );

			if ( 'custom_meta_' === substr( $name, 0, $custom_meta_length ) ) {
				$meta_key         = substr( $name, $custom_meta_length );
				$used_meta_keys[] = $meta_key;
			}
		}

		set_transient( $transient, $used_meta_keys, 5 * MINUTE_IN_SECONDS );

		return $used_meta_keys;
	}

	/**
	 * Check if get_options() is currently being executed.
	 *
	 * This method is used to prevent recursive calls that could cause infinite loops.
	 *
	 * @since ??
	 *
	 * @return bool True if get_options() is currently being executed, false otherwise.
	 */
	public static function is_getting_options(): bool {
		return self::$_is_getting_options;
	}
}
