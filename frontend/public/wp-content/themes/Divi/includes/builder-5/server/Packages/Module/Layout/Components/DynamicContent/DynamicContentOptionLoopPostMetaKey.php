<?php
/**
 * Module: DynamicContentOptionLoopMetaKey class.
 *
 * Handles dynamic content for loop meta keys (custom fields) within loop contexts.
 * This class provides functionality to retrieve and display custom field values
 * for posts, users, and terms within loops, with support for HTML output and before/after text.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Layout\Components\DynamicContent;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Utility\ArrayUtility;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;

/**
 * Module: DynamicContentOptionLoopMetaKey class.
 *
 * @since ??
 */
class DynamicContentOptionLoopPostMetaKey extends DynamicContentOptionBase implements DynamicContentOptionInterface {

	/**
	 * Get the name of the loop meta key option.
	 *
	 * @since ??
	 *
	 * @return string The name of the loop meta key option.
	 */
	public function get_name(): string {
		return 'loop_manual_custom_field';
	}

	/**
	 * Get the label for the loop meta key option.
	 *
	 * This function retrieves the localized label for the loop meta key option,
	 * which is used to describe the loop meta key in user interfaces.
	 *
	 * @since ??
	 *
	 * @return string The label for the loop meta key option.
	 */
	public function get_label(): string {
		return esc_html__( 'Loop Meta Keys', 'et_builder_5' );
	}

	/**
	 * Check if the current user has the capability to manage options.
	 *
	 * @since ??
	 *
	 * @param string $type The type of meta: 'post', 'user', or 'term'.
	 *
	 * @return bool True if the current user has the capability to manage options, false otherwise.
	 */
	public static function has_user_cap( string $type ): bool {
		if ( 'user' === $type ) {
			return current_user_can( 'manage_options' );
		}
		return true;
	}

	/**
	 * Callback for registering loop meta key options.
	 *
	 * This function is a callback for the `divi_module_dynamic_content_options` filter.
	 * This function is used to register options for loop meta keys by adding them to the options array passed to the function.
	 * It supports three types: post meta, user meta, and term meta keys.
	 *
	 * @since ??
	 *
	 * @param array  $options The options array to be registered.
	 * @param int    $post_id The post ID.
	 * @param string $context The context in which the options are retrieved e.g `edit`, `display`.
	 *
	 * @return array The options array.
	 */
	public function register_option_callback( array $options, int $post_id, string $context ): array {
		// Register Post Meta Key Options.
		self::_register_meta_key_options( $options, 'post' );

		// Register User Meta Key Options.
		self::_register_meta_key_options( $options, 'user' );

		// Register Term Meta Key Options.
		self::_register_meta_key_options( $options, 'term' );

		return $options;
	}

	/**
	 * Register meta key options for a specific type (post, user, or term).
	 *
	 * @since ??
	 *
	 * @param array  $options The options array to be modified.
	 * @param string $type    The meta type: 'post', 'user', or 'term'.
	 *
	 * @return void
	 */
	private static function _register_meta_key_options( array &$options, string $type ): void {

		$prefix = 'loop_' . $type . '_meta_key_';

		if ( ! self::has_user_cap( $type ) ) {
			$used_meta_keys = [];
		} else {
			// Get most used meta keys.
			$most_used_meta_keys = DynamicContentOptions::get_most_used_meta_keys_by_type( $type );

			// Get ALL ACF fields (not cached) to ensure immediate visibility.
			$acf_field_names = array_keys( DynamicContentACFUtils::get_acf_field_info( $type ) );

			// Merge most used meta keys with ACF fields for complete coverage.
			$used_meta_keys = array_unique( array_merge( $most_used_meta_keys, $acf_field_names ) );
		}

		$meta_key_options = [
			$prefix . 'group_manual' => [
				'label'   => esc_html__( 'Manual Input', 'et_builder_5' ),
				'options' => [
					$prefix . 'manual_custom_field_value' => [
						'label' => esc_html__( 'Enter Custom Meta Key', 'et_builder_5' ),
					],
				],
			],
		];

		if ( ! empty( $used_meta_keys ) ) {
			// Use shared utility to build meta key options with ACF grouping.
			$final_options = DynamicContentACFUtils::build_meta_key_options( $type, $prefix, $used_meta_keys );

			// Merge with base options.
			$meta_key_options = array_merge( $meta_key_options, $final_options );
		}

		// Create base fields for all meta types.
		$fields = [
			'before'               => [
				'label'   => esc_html__( 'Before', 'et_builder_5' ),
				'type'    => 'text',
				'default' => '',
			],
			'after'                => [
				'label'   => esc_html__( 'After', 'et_builder_5' ),
				'type'    => 'text',
				'default' => '',
			],
			'select_loop_meta_key' => [
				'label'   => esc_html__( 'Select Custom Field', 'et_builder_5' ),
				'type'    => 'select',
				'options' => $meta_key_options,
				'default' => $prefix . 'manual_custom_field_value',
			],
			'loop_meta_key'        => [
				'label'   => esc_html__( 'Meta Key', 'et_builder_5' ),
				'type'    => 'text',
				'show_if' => [
					'select_loop_meta_key' => $prefix . 'manual_custom_field_value',
				],
			],
			'date_format'          => [
				'label'   => esc_html__( 'Date Format', 'et_builder_5' ),
				'type'    => 'select',
				'options' => [
					'default' => et_builder_i18n( 'Default' ),
					'M j, Y'  => esc_html__( 'Aug 6, 1999 (M j, Y)', 'et_builder_5' ),
					'F d, Y'  => esc_html__( 'August 06, 1999 (F d, Y)', 'et_builder_5' ),
					'm/d/Y'   => esc_html__( '08/06/1999 (m/d/Y)', 'et_builder_5' ),
					'm.d.Y'   => esc_html__( '08.06.1999 (m.d.Y)', 'et_builder_5' ),
					'j M, Y'  => esc_html__( '6 Aug, 1999 (j M, Y)', 'et_builder_5' ),
					'l, M d'  => esc_html__( 'Tuesday, Aug 06 (l, M d)', 'et_builder_5' ),
					'custom'  => esc_html__( 'Custom', 'et_builder_5' ),
				],
				'default' => 'default',
			],
			'custom_date_format'   => [
				'label'   => esc_html__( 'Custom Date Format', 'et_builder_5' ),
				'type'    => 'text',
				'default' => '',
				'show_if' => [
					'date_format' => 'custom',
				],
			],
			'loop_position'        => [
				'label'       => 'Loop Position',
				'type'        => 'text',
				'default'     => '',
				'renderAfter' => 'n',
			],
		];

		if ( current_user_can( 'unfiltered_html' ) ) {
			$fields['enable_html'] = [
				'label'   => esc_html__( 'Enable Raw HTML', 'et_builder_5' ),
				'type'    => 'yes_no_button',
				'options' => [
					'on'  => et_builder_i18n( 'Yes' ),
					'off' => et_builder_i18n( 'No' ),
				],
				'default' => 'off',
				'show_on' => 'text',
			];
		}

		if ( ! self::has_user_cap( $type ) ) {
			unset( $fields['select_loop_meta_key'] );
			unset( $fields['loop_meta_key']['show_if'] );
		}

		// Register Custom Field option.
		if ( ! isset( $options[ $prefix . 'manual_custom_field' ] ) ) {
			$options[ $prefix . 'manual_custom_field' ] = [
				'id'     => $prefix . 'manual_custom_field',
				'label'  => sprintf( esc_html__( 'Loop %s Custom Field', 'et_builder_5' ), ucfirst( $type ) ),
				'type'   => 'any',
				'custom' => false,
				'group'  => sprintf( esc_html__( 'Loop %s Custom Fields', 'et_builder_5' ), ucfirst( $type ) ),
				'fields' => $fields,
			];
		}
	}

	/**
	 * Render callback for loop meta key option.
	 *
	 * Retrieves the value of loop meta key option based on the provided arguments and settings.
	 * This is a callback for `divi_module_dynamic_content_resolved_value` filter.
	 *
	 * @since ??
	 *
	 * @param mixed $value     The current value of the loop meta key option.
	 * @param array $data_args {
	 *     Optional. An array of arguments for retrieving the loop meta key.
	 *     Default `[]`.
	 *
	 *     @type string  $name            Optional. Option name. Default empty string.
	 *     @type array   $settings        Optional. Option settings. Default `[]`.
	 *     @type integer $post_id         Optional. Post Id. Default `null`.
	 *     @type integer $loop_id         Optional. Loop item post ID. Default `null`.
	 *     @type string  $loop_query_type Optional. Loop query type. Default empty string.
	 * }
	 *
	 * @return string The formatted value of the loop meta key option.
	 */
	public function render_callback( $value, array $data_args = [] ): string {
		$name     = $data_args['name'] ?? '';
		$settings = $data_args['settings'] ?? [];
		$post_id  = $data_args['post_id'] ?? null;

		$is_doing_content_filter = doing_filter( 'the_content' ) || doing_filter( 'et_builder_render_layout' );

		// Early return if this isn't one of our loop meta key options.
		// Frontend processing is handled directly in DynamicContentLoopOptions.
		// This callback only handles Visual Builder placeholder generation.
		if ( ! self::is_custom_loop_meta_key_option( $name ) || $is_doing_content_filter ) {
			return $value;
		}

		// Determine meta type and key.
		$meta_type = self::get_meta_type_from_name( $name );

		// Get meta key from settings.
		// Note: If the user doesn't have permission, the select_loop_meta_key field would not exist.
		// So we need to check if the settings are empty and use the Loop Meta Key if they are.
		$meta_key = self::get_meta_key_from_name( $settings, $meta_type );

		if ( empty( $meta_key ) ) {
			return $settings['before'] . '' . $settings['after'];
		}

		// Get meta value based on type.
		$meta_value = self::get_meta_value_by_type( $meta_type, $post_id, $meta_key );

		// If the value is empty, return empty string with before/after text.
		if ( empty( $meta_value ) ) {
			return $settings['before'] . '' . $settings['after'];
		}

		// Handle array values (like ACF checkboxes).
		$meta_value = ArrayUtility::is_array_of_strings( $meta_value ) ? implode( ', ', $meta_value ) : $meta_value;

		// Apply date formatting if enabled by user, returns actual value if disabled or value is not a valid date.
		if ( ! is_array( $meta_value ) && '' !== $meta_value && ! empty( $meta_key ) && 'default' !== ( $settings['date_format'] ?? 'default' ) ) {
			$acf_field_type = $settings['acf_type'] ?? '';
			$is_date_field  = in_array( $acf_field_type, [ 'date_picker', 'date_time_picker', 'time_picker' ], true );

			if ( $is_date_field && is_string( $meta_value ) ) {
				$processed       = DynamicContentACFUtils::process_acf_date_field( $meta_value, $acf_field_type, $settings );
				$formatted_value = ModuleUtils::format_date( $processed['value'], $processed['settings'] );
			} else {
				$formatted_value = ModuleUtils::format_date( $meta_value, $settings );
			}

			$meta_value = ! empty( $formatted_value ) ? $formatted_value : $meta_value;
		}

		if ( 'on' !== ( $settings['enable_html'] ?? 'off' ) && 'default' === ( $settings['date_format'] ?? 'default' ) ) {
			// Only escape HTML if date formatting wasn't applied.
			$meta_value = esc_html( $meta_value );
		}

		return DynamicContentElements::get_wrapper_element(
			[
				'post_id'  => $post_id,
				'name'     => $name,
				'value'    => $meta_value,
				'settings' => $settings,
			]
		);
	}

	/**
	 * Get meta type from option name.
	 *
	 * @since ??
	 *
	 * @param string $name The option name.
	 *
	 * @return string The meta type: 'post', 'user', 'term', or empty string.
	 */
	public static function get_meta_type_from_name( string $name ): string {
		if ( str_starts_with( $name, 'loop_post_meta_key_' ) ) {
			return 'post';
		}
		if ( str_starts_with( $name, 'loop_user_meta_key_' ) ) {
			return 'user';
		}
		if ( str_starts_with( $name, 'loop_term_meta_key_' ) ) {
			return 'term';
		}
		return '';
	}

	/**
	 * Get meta key from settings.
	 *
	 * @since ??
	 *
	 * @param array  $settings The settings.
	 * @param string $meta_type The meta type: 'post', 'user', or 'term'.
	 *
	 * @return string The meta key.
	 */
	public static function get_meta_key_from_name( array $settings, string $meta_type ): string {

		$selected_loop_meta_key = $settings['select_loop_meta_key'] ?? '';

		// Check if it's a manual custom field option, or if user meta type with no saved selection.
		// For user meta: empty select_loop_meta_key indicates post author lacked 'manage_options' permission during save,
		// so field wasn't available and we should use manual field.
		$is_manual_field       = str_ends_with( $selected_loop_meta_key, '_manual_custom_field_value' );
		$is_user_meta_fallback = 'user' === $meta_type && empty( $selected_loop_meta_key );
		if ( $is_manual_field || $is_user_meta_fallback ) {
			return $settings['loop_meta_key'] ?? '';
		}

		// Extract meta key from option name.
		if ( str_starts_with( $selected_loop_meta_key, 'loop_post_meta_key_' ) ) {
			return str_replace( 'loop_post_meta_key_', '', $selected_loop_meta_key );
		}
		if ( str_starts_with( $selected_loop_meta_key, 'loop_user_meta_key_' ) ) {
			return str_replace( 'loop_user_meta_key_', '', $selected_loop_meta_key );
		}
		if ( str_starts_with( $selected_loop_meta_key, 'loop_term_meta_key_' ) ) {
			return str_replace( 'loop_term_meta_key_', '', $selected_loop_meta_key );
		}

		return '';
	}

	/**
	 * Get meta value by type with ACF field processing.
	 *
	 * @since ??
	 *
	 * @param string $type     The meta type: 'post', 'user', or 'term'.
	 * @param int    $id       The object ID.
	 * @param string $meta_key The meta key.
	 *
	 * @return mixed The meta value.
	 */
	public static function get_meta_value_by_type( string $type, int $id, string $meta_key ) {
		// Use shared ACF utility for meta value retrieval.
		return DynamicContentACFUtils::get_meta_value_by_type( $type, $id, $meta_key );
	}

	/**
	 * Check if the option is a custom loop meta key option.
	 * This function checks if the option name starts with 'loop_post_meta_key_manual_custom_field', 'loop_user_meta_key_manual_custom_field', or 'loop_term_meta_key_manual_custom_field'.
	 *
	 * @since ??
	 *
	 * @param string $name The option name.
	 *
	 * @return bool True if the option is a custom loop meta key option, false otherwise.
	 */
	public static function is_custom_loop_meta_key_option( string $name ): bool {
		return str_starts_with( $name, 'loop_post_meta_key_manual_custom_field' ) ||
		str_starts_with( $name, 'loop_user_meta_key_manual_custom_field' ) ||
		str_starts_with( $name, 'loop_term_meta_key_manual_custom_field' );
	}
}
