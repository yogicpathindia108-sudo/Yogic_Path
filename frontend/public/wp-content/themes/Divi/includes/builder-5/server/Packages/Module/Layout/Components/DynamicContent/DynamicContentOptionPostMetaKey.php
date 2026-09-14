<?php
/**
 * Module: DynamicContentOptionPostMetaKey class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Layout\Components\DynamicContent;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentUtils;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;
use ET\Builder\Framework\Utility\ArrayUtility;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\Framework\Utility\StringUtility;

/**
 * Module: DynamicContentOptionPostMetaKey class.
 *
 * @since ??
 */
class DynamicContentOptionPostMetaKey extends DynamicContentOptionBase implements DynamicContentOptionInterface {

	/**
	 * Cache for field types keyed by post_id and context.
	 * This prevents recursive calls to et_builder_get_dynamic_content_fields() during rendering.
	 *
	 * @since ??
	 *
	 * @var array<string, array<string, string>> Cache structure: [post_id][field_name] => field_type
	 */
	private static $_field_type_cache = [];

	/**
	 * Get the name of the post meta key option.
	 *
	 * @since ??
	 *
	 * @return string The name of the post meta key option.
	 */
	public function get_name(): string {
		return 'post_meta_key';
	}

	/**
	 * Get the label for the post meta key option.
	 *
	 * This function retrieves the localized label for the post meta key option,
	 * which is used to describe the post meta key in user interfaces.
	 *
	 * @since ??
	 *
	 * @return string The label for the post meta key option.
	 */
	public function get_label(): string {
		// The 'Custom Field' is the official label name for custom meta option.
		// key. So, we keep the same key name and not rename it into 'Option'.
		return esc_html__( 'Custom Field', 'et_builder_5' );
	}

	/**
	 * Callback for registering post meta key option with dropdown system.
	 *
	 * This function is a callback for the `divi_module_dynamic_content_options` filter.
	 * This function is used to register options for post meta key by adding them to the options array passed to the function.
	 * It provides a sophisticated dropdown with ACF grouping similar to the loop version.
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
		// Always register the Custom Field option - it should always be available.
		// Permission check only affects whether dropdown options (actual custom field names) are built.
		if ( ! isset( $options[ $this->get_name() ] ) ) {
			// Build meta key options dropdown like the loop version.
			$prefix = 'custom_meta_';

			// Start with manual input option - this is always available.
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

			// Only build custom field dropdown options (ACF fields, standard meta keys) if user has permission or context is 'display'.
			// When permission is off, users can still use the "Manual Input" option to enter custom field names manually.
			// This matches D4 behavior where custom fields dropdown is hidden but manual input is still available.
			if ( 'display' === $context || et_pb_is_allowed( 'read_dynamic_content_custom_fields' ) ) {
				// Get most used meta keys.
				$most_used_meta_keys = DynamicContentOptions::get_most_used_meta_keys();

				// Get ALL ACF fields (not cached) to ensure immediate visibility.
				$acf_field_names = array_keys( DynamicContentACFUtils::get_acf_field_info( 'post' ) );

				// Merge most used meta keys with ACF fields for complete coverage.
				$used_meta_keys = array_unique( array_merge( $most_used_meta_keys, $acf_field_names ) );

				// Add discovered meta keys with ACF grouping.
				if ( ! empty( $used_meta_keys ) ) {
					$final_options    = DynamicContentACFUtils::build_meta_key_options( 'post', $prefix, $used_meta_keys );
					$meta_key_options = array_merge( $meta_key_options, $final_options );
				}

				// Cache field types during registration to avoid recursive calls during rendering.
				// This is safe because we're already inside get_options() with the flag set,
				// so any recursion would be prevented by the static flag.
				self::_cache_field_types( $post_id, $context );
			}

			$fields = array_merge(
				[
					'before'          => [
						'label'   => esc_html__( 'Before', 'et_builder_5' ),
						'type'    => 'text',
						'default' => '',
					],
					'after'           => [
						'label'   => esc_html__( 'After', 'et_builder_5' ),
						'type'    => 'text',
						'default' => '',
					],
					'select_meta_key' => [
						'label'   => esc_html__( 'Select Custom Field', 'et_builder_5' ),
						'type'    => 'select',
						'options' => $meta_key_options,
						'default' => $prefix . 'manual_custom_field_value',
					],
					'meta_key'        => [
						'label'   => esc_html__( 'Field Name', 'et_builder_5' ),
						'type'    => 'text',
						'show_if' => [
							'select_meta_key' => $prefix . 'manual_custom_field_value',
						],
					],
				],
				DynamicContentUtils::get_date_format_fields()
			);

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

			$options[ $this->get_name() ] = [
				'id'     => $this->get_name(),
				'label'  => $this->get_label(),
				'type'   => 'any',
				'custom' => false,
				// Use 'Default' group so it appears in Dynamic Content section.
				'group'  => esc_html__( 'Default', 'et_builder_5' ),
				'fields' => $fields,
			];
		}

		return $options;
	}



	/**
	 * Render callback for post meta key option with dropdown and ACF support.
	 *
	 * Retrieves the value of post meta key option based on the provided arguments and settings.
	 * This is a callback for `divi_module_dynamic_content_resolved_value` filter.
	 *
	 * @since ??
	 *
	 * @param mixed $value     The current value of the post meta key option.
	 * @param array $data_args {
	 *     Optional. An array of arguments for retrieving the post meta key.
	 *     Default `[]`.
	 *
	 *     @type string  $name            Optional. Option name. Default empty string.
	 *     @type array   $settings        Optional. Option settings. Default `[]`.
	 *     @type integer $post_id         Optional. Post Id. Default `null`.
	 *     @type string  $context         Optional. Context in which the option is used (`'edit'` or `'display'`). Default empty string.
	 *     @type string  $loop_query_type Optional. The loop query type. Default `null`.
	 *     @type mixed   $loop_object     Optional. The loop object (WP_Post, WP_User, WP_Term, etc.). Default `null`.
	 *     @type bool    $is_manual_entry  Optional. Whether this is a manual entry (user manually entered custom field name).
	 *                                     When `true`, permission check is bypassed and value always resolves.
	 *                                     When `false` or not set, permission check applies for discovered custom fields.
	 *                                     Default `false`.
	 * }
	 *
	 * @return string The formatted value of the post meta key option.
	 */
	public function render_callback( $value, array $data_args = [] ): string {
		$name            = $data_args['name'] ?? '';
		$settings        = $data_args['settings'] ?? [];
		$post_id         = $data_args['post_id'] ?? null;
		$context         = $data_args['context'] ?? '';
		$loop_query_type = $data_args['loop_query_type'] ?? null;
		$loop_object     = $data_args['loop_object'] ?? null;

		// Get is_manual_entry flag from data_args if provided (from DynamicContentUtils).
		// Otherwise, detect it ourselves for backward compatibility.
		$is_manual_entry = $data_args['is_manual_entry'] ?? false;
		$meta_key        = '';

		// If flag not provided, detect manually (for backward compatibility).
		if ( ! isset( $data_args['is_manual_entry'] ) ) {
			// Handle custom field names - both old simple format and legacy complex format.
			if ( StringUtility::starts_with( $name, 'custom_meta_' ) ) {
				// Simple format: custom_meta_field_name (preferred).
				if ( 'custom_meta_manual_custom_field_value' === $name ) {
					$meta_key        = $settings['meta_key'] ?? '';
					$is_manual_entry = true;
				} else {
					// Strip prefix to get actual meta key.
					$meta_key        = str_replace( 'custom_meta_', '', $name );
					$is_manual_entry = false;
				}
			} elseif ( $name === $this->get_name() ) {
				// Legacy complex format: name is "post_meta_key", actual field is in settings.
				// This is for backward compatibility with old saved content.
				$selected_meta_key = $settings['select_meta_key'] ?? '';

				// Handle both legacy formats.
				// 1. New legacy: select_meta_key with custom_meta_ prefix.
				// 2. Old legacy: direct meta_key (from main branch).
				if ( ! empty( $selected_meta_key ) ) {
					if ( 'custom_meta_manual_custom_field_value' === $selected_meta_key ) {
						$meta_key        = $settings['meta_key'] ?? '';
						$is_manual_entry = true;
					} elseif ( StringUtility::starts_with( $selected_meta_key, 'custom_meta_' ) ) {
						// Strip prefix to get actual meta key.
						$meta_key        = str_replace( 'custom_meta_', '', $selected_meta_key );
						$is_manual_entry = false;
					} else {
						// Unknown format.
						return $value;
					}
				} elseif ( ! empty( $settings['meta_key'] ) ) {
					// Handle original main branch format: direct meta_key in settings.
					// This is a manual entry (post_meta_key with direct meta_key).
					$meta_key        = $settings['meta_key'];
					$is_manual_entry = true;
				} else {
					// Unknown format.
					return $value;
				}
			} else {
				// Not our responsibility.
				return $value;
			}
		} elseif ( StringUtility::starts_with( $name, 'custom_meta_' ) ) {
			// Flag provided, extract meta_key based on name and settings.
			// Simple format: custom_meta_field_name (preferred).
			if ( 'custom_meta_manual_custom_field_value' === $name ) {
				$meta_key = $settings['meta_key'] ?? '';
			} else {
				// Strip prefix to get actual meta key.
				$meta_key = str_replace( 'custom_meta_', '', $name );
			}
		} elseif ( $name === $this->get_name() ) {
			// Legacy complex format: name is "post_meta_key", actual field is in settings.
			$selected_meta_key = $settings['select_meta_key'] ?? '';

			if ( ! empty( $selected_meta_key ) ) {
				if ( 'custom_meta_manual_custom_field_value' === $selected_meta_key ) {
					$meta_key = $settings['meta_key'] ?? '';
				} elseif ( StringUtility::starts_with( $selected_meta_key, 'custom_meta_' ) ) {
					// Strip prefix to get actual meta key.
					$meta_key = str_replace( 'custom_meta_', '', $selected_meta_key );
				} else {
					// Unknown format.
					return $value;
				}
			} elseif ( ! empty( $settings['meta_key'] ) ) {
				// Handle original main branch format: direct meta_key in settings.
				$meta_key = $settings['meta_key'];
			} else {
				// Unknown format.
				return $value;
			}
		} else {
			// Not our responsibility.
			return $value;
		}

		// Check permission in edit context - but ONLY for discovered custom fields, not manual entries.
		// This matches D4 behavior where manual entries (post_meta_key) bypass permission check
		// and always resolve, while discovered custom fields (custom_meta_field_name) check permission
		// and show error if off.
		if ( ! $is_manual_entry && 'edit' === $context && ! et_pb_is_allowed( 'read_dynamic_content_custom_fields' ) ) {
			// Get field type from cache (populated during registration) to avoid recursive calls.
			// If cache is empty, default to 'text' for safety (matches D4 behavior).
			$field_type = self::_get_cached_field_type( $post_id, $context, $name );

			if ( 'text' === $field_type ) {
				return esc_html__( 'You don\'t have sufficient permissions to access this content.', 'et_builder' );
			}

			return '';
		}

		// TODO feat(D5, Theme Builder): Replace it once the Theme Builder is implemented in D5 [https://github.com/elegantthemes/Divi/issues/25149].
		$is_fe = 'fe' === et_builder_get_current_builder_type() && ! is_et_theme_builder_template_preview() && ! Conditions::is_rest_api_request() ? true : false;

		if ( empty( $meta_key ) ) {
			$before = $settings['before'] ?? '';
			$after  = $settings['after'] ?? '';
			return DynamicContentElements::get_wrapper_element(
				[
					'post_id'  => $post_id,
					'name'     => $name,
					'value'    => $before . '' . $after,
					'settings' => $settings,
				]
			);
		}

		// Ensure we have a valid post ID for meta retrieval.
		if ( null === $post_id ) {
			$post_id = get_the_ID();
			if ( false === $post_id || 0 === $post_id ) {
				// No valid post context, return empty value with wrapper.
				$before = $settings['before'] ?? '';
				$after  = $settings['after'] ?? '';
				return DynamicContentElements::get_wrapper_element(
					[
						'post_id'  => null,
						'name'     => $name,
						'value'    => $before . '' . $after,
						'settings' => $settings,
					]
				);
			}
		}

		$archive_term    = $is_fe ? DynamicContentUtils::get_archive_term_context( $data_args ) : null;
		$meta_type       = 'post';
		$meta_context_id = $post_id;
		$acf_context     = $post_id;

		if ( $archive_term instanceof \WP_Term ) {
			$meta_type       = 'term';
			$meta_context_id = $archive_term->term_id;
			$acf_context     = 'term_' . $meta_context_id;
		}

		if (
			StringUtility::starts_with( $loop_query_type ?? '', 'repeater' ) &&
			null !== $loop_object &&
			is_array( $loop_object )
		) {
			$value = $loop_object[ $meta_key ] ?? '';
		} else {
			$value = DynamicContentACFUtils::get_meta_value_by_type( $meta_type, $meta_context_id, $meta_key );
		}

		// Normalize ACF image return values that were not converted inside get_meta_value_by_type.
		// This covers cases where get_field_object() returned false for the term context,
		// leaving the raw ACF value (array for Image Array, integer ID for Image ID) unconverted.
		if ( 'term' === $meta_type ) {
			if ( is_array( $value ) && isset( $value['url'] ) ) {
				// ACF Image Array: extract the URL directly.
				$value = $value['url'];
			} elseif ( is_numeric( $value ) && (int) $value > 0 ) {
				// ACF Image ID: convert attachment ID to URL when the ID resolves to a valid attachment.
				// We use wp_get_attachment_url() as the guard — a non-false result confirms the value
				// is an attachment ID, not an unrelated numeric term meta value.
				$image_url = wp_get_attachment_url( absint( $value ) );
				if ( false !== $image_url ) {
					$value = $image_url;
				}
			}
		}

		// Handle array values (like ACF checkboxes).
		$value = ArrayUtility::is_array_of_strings( $value ) ? implode( ', ', $value ) : $value;

		/**
		 * Filters custom meta value allowing third party to format the values.
		 *
		 * @since ??
		 *
		 * @param string  $value     Custom meta option value.
		 * @param string  $meta_key  Custom meta option key.
		 * @param integer $post_id   Post ID.
		 */
		$value = apply_filters( 'divi_module_dynamic_content_resolved_custom_meta_value', $value, $meta_key, $post_id );

		// Handle custom field date formatting.
		if ( ! is_array( $value )
			&& '' !== $value
			&& ! empty( $meta_key )
			&& 'default' !== ( $settings['date_format'] ?? 'default' ) ) {
			$acf_field_type = '';
			if ( DynamicContentACFUtils::is_acf_active() && function_exists( 'get_field_object' ) ) {
				$field_name   = StringUtility::starts_with( $meta_key, '_' ) ? ltrim( $meta_key, '_' ) : $meta_key;
				$field_object = get_field_object( $field_name, $acf_context );

				if ( is_array( $field_object ) ) {
					$acf_field_type = $field_object['type'] ?? '';
				}
			}

			$is_date_field = in_array( $acf_field_type, [ 'date_picker', 'date_time_picker', 'time_picker' ], true );

			if ( $is_date_field && is_string( $value ) ) {
				$processed       = DynamicContentACFUtils::process_acf_date_field( $value, $acf_field_type, $settings );
				$formatted_value = ModuleUtils::format_date( $processed['value'], $processed['settings'] );
			} else {
				$formatted_value = ModuleUtils::format_date( $value, $settings );
			}

			$value = ! empty( $formatted_value ) ? $formatted_value : $value;
		}

		if ( ( $is_fe && empty( $value ) ) || empty( $meta_key ) ) {
			$value = '';
		} elseif ( empty( $meta_key ) && empty( $value ) ) {
				$value = '';
		} elseif ( empty( $value ) && ! empty( $meta_key ) ) {
			$is_image_field = false;

			if ( DynamicContentACFUtils::is_acf_active() && function_exists( 'get_field_object' ) ) {
				$field_name   = StringUtility::starts_with( $meta_key, '_' ) ? ltrim( $meta_key, '_' ) : $meta_key;
				$field_object = get_field_object( $field_name, $acf_context );
				if ( is_array( $field_object ) && 'image' === ( $field_object['type'] ?? '' ) ) {
					$is_image_field = true;
				}
			}

			if ( $is_image_field ) {
				$value = '';
			} else {
				$value = DynamicContentUtils::get_custom_meta_label( $meta_key );
			}
		} else {
			// Sanitize HTML contents with oembed-aware sanitization.
			$value = $this->_sanitize_dynamic_content_value( $value );

			if ( 'on' !== ( $settings['enable_html'] ?? 'off' ) ) {
				// Escape HTML if not explicitly enabled.
				$value = esc_html( $value );
			}
		}

		return DynamicContentElements::get_wrapper_element(
			[
				'post_id'  => $post_id,
				'name'     => $name,
				'value'    => $value,
				'settings' => $settings,
			]
		);
	}

	/**
	 * Sanitize dynamic content value with oembed-aware sanitization.
	 *
	 * This function provides smart sanitization that detects oembed content (iframe tags)
	 * and applies appropriate sanitization. For oembed content, it allows safe iframe
	 * attributes while still removing dangerous elements. For regular content, it uses
	 * standard wp_kses_post sanitization.
	 *
	 * @since ??
	 *
	 * @param string $value The value to sanitize.
	 *
	 * @return string The sanitized value.
	 */
	private function _sanitize_dynamic_content_value( string $value ): string {
		// Check if the value contains iframe tags (likely oembed content).
		if ( str_contains( $value, '<iframe' ) ) {
			// Define allowed iframe attributes for oembed content.
			$allowed_iframe_tags = [
				'iframe' => [
					'title'           => true,
					'width'           => true,
					'height'          => true,
					'src'             => true,
					'allow'           => true,
					'frameborder'     => true,
					'allowfullscreen' => true,
					'referrerpolicy'  => true,
					'loading'         => true,
				],
			];

			// Merge with default wp_kses_post allowed tags.
			$allowed_tags = array_merge( wp_kses_allowed_html( 'post' ), $allowed_iframe_tags );

			// Apply oembed-aware sanitization.
			return wp_kses( $value, $allowed_tags );
		}

		// For non-oembed content, use standard wp_kses_post sanitization.
		return wp_kses_post( $value );
	}

	/**
	 * Cache field types for a given post_id and context.
	 *
	 * This is called during option registration when the recursion flag is already set,
	 * making it safe to call et_builder_get_dynamic_content_fields() without causing recursion.
	 *
	 * @since ??
	 *
	 * @param int    $post_id Post ID.
	 * @param string $context Context ('edit' or 'display').
	 *
	 * @return void
	 */
	private static function _cache_field_types( int $post_id, string $context ): void {
		$cache_key = $post_id . ':' . $context;

		// Skip if already cached.
		if ( isset( self::$_field_type_cache[ $cache_key ] ) ) {
			return;
		}

		// Initialize cache entry.
		self::$_field_type_cache[ $cache_key ] = [];

		// Get field types safely (we're already inside get_options() with flag set).
		if ( function_exists( 'et_builder_get_dynamic_content_fields' ) ) {
			$fields = et_builder_get_dynamic_content_fields( $post_id, $context );

			if ( is_array( $fields ) ) {
				foreach ( $fields as $field_name => $field_data ) {
					if ( isset( $field_data['type'] ) ) {
						self::$_field_type_cache[ $cache_key ][ $field_name ] = $field_data['type'];
					}
				}
			}
		}
	}

	/**
	 * Get cached field type for a given field name.
	 *
	 * @since ??
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $context    Context ('edit' or 'display').
	 * @param string $field_name Field name to look up.
	 *
	 * @return string Field type, defaults to 'text' if not found in cache.
	 */
	private static function _get_cached_field_type( int $post_id, string $context, string $field_name ): string {
		$cache_key = $post_id . ':' . $context;

		if ( isset( self::$_field_type_cache[ $cache_key ][ $field_name ] ) ) {
			return self::$_field_type_cache[ $cache_key ][ $field_name ];
		}

		// Default to 'text' for safety (matches D4 behavior where text fields show error message).
		return 'text';
	}
}
