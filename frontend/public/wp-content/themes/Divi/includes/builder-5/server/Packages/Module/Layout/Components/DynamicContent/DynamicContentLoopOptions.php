<?php
/**
 * Module: DynamicContentLoopOptions class.
 *
 * @package Builder\Packages\Module
 * @since ??
 */

namespace ET\Builder\Packages\Module\Layout\Components\DynamicContent;

use ET\Builder\Framework\Utility\ArrayUtility;
use ET\Builder\Packages\Module\Options\Loop\LoopUtils;
use ET\Builder\Packages\Module\Options\Loop\LoopContext;
use ET\Builder\Packages\ModuleUtils\ModuleUtils;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Module: DynamicContentLoopOptions class.
 *
 * @since ??
 */
class DynamicContentLoopOptions extends DynamicContentOptionBase implements DynamicContentOptionInterface {

	/**
	 * Get the name of the loop option.
	 *
	 * @since ??
	 *
	 * @return string The name of the loop option.
	 */
	public function get_name(): string {
		return 'loop_';
	}

	/**
	 * Get the label for the loop option.
	 *
	 * @since ??
	 *
	 * @return string The label for the loop option.
	 */
	public function get_label(): string {
		return __( 'Loop', 'et_builder_5' );
	}

	/**
	 * Callback for registering loop option .
	 *
	 * This function is a callback for the `divi_module_dynamic_content_options` filter .
	 * This function is used to register options for loop by adding them to the options array passed to the function .
	 * It checks if the current module's name exists as a key in the options array.
	 * If not, it adds the module's name as a key and the specific options for that module as the value.
	 *
	 * @since ??
	 *
	 * @param array  $options The options array to be registered.
	 * @param int    $post_id The post ID.
	 * @param string $context The context in which the options are retrieved e.g `edit`, `display`.
	 *
	 * @return array The options array.
	 */
	public function register_option_callback( array $options, $post_id, $context ): array {
		return $options;
	}

	/**
	 * Render the loop option.
	 *
	 * @since ??
	 *
	 * @param string $value The value of the loop option.
	 * @param array  $data_args {
	 *     The data arguments.
	 *
	 *     @type string  $name            Option name.
	 *     @type array   $settings        Option settings.
	 *     @type integer $loop_id         The loop post ID for loop context.
	 *     @type string  $loop_query_type The loop query type.
	 *     @type mixed   $loop_object     The loop object (WP_Post, WP_User, WP_Term, etc.).
	 * }
	 *
	 * @return string The rendered loop option.
	 */
	public function render_callback( $value, array $data_args = [] ): string {
		$name            = $data_args['name'] ?? '';
		$settings        = $data_args['settings'] ?? [];
		$loop_object     = $data_args['loop_object'] ?? null;
		$loop_id         = $data_args['loop_id'] ?? null;
		$loop_query_type = $data_args['loop_query_type'] ?? '';

		// Bail early if the option name doesn't start with `loop_`.
		if ( 0 !== strpos( $name, $this->get_name() ) ) {
			return $value;
		}

		if ( ! $loop_id && ! $loop_query_type ) {
			$is_doing_content_filter = doing_filter( 'the_content' ) || doing_filter( 'et_builder_render_layout' );

			// On frontend, return empty string with before/after text when no loop context exists.
			// This prevents raw JSON placeholders from appearing on the frontend.
			if ( $is_doing_content_filter ) {
				$before_text = $settings['before'] ?? '';
				$after_text  = $settings['after'] ?? '';

				return $before_text . '' . $after_text;
			}

			// Encode the value as a JSON string to maintain data integrity during loop processing.
			// This ensures that dynamic variables can be properly resolved to their actual content
			// when the module is rendered within a loop context.
			// JSON_UNESCAPED_UNICODE preserves international characters (e.g., Cyrillic, Arabic)
			// as UTF-8 instead of Unicode escape sequences to prevent content corruption.
			$variable_string = wp_json_encode(
				[
					'type'  => 'content',
					'value' => [
						'name'     => $name,
						'settings' => $settings,
					],
				],
				JSON_UNESCAPED_UNICODE
			);

			return '$variable(' . $variable_string . ')$';
		}

		if ( isset( $settings['loop_position'] ) && '' !== $settings['loop_position'] ) {
			$loop_context = LoopContext::get();

			if ( $loop_context ) {
				$loop_pos_0_based = max( 0, intval( $settings['loop_position'] ) - 1 );
				$loop_object      = $loop_context->get_result_for_position( $loop_pos_0_based );
			}
		}

		$before_text = $settings['before'] ?? '';
		$after_text  = $settings['after'] ?? '';

		// Handle meta key-based loop content retrieval.
		// This branch processes dynamic content when a specific meta key is configured,
		// handling various meta types (post meta, user meta, term meta) and ensuring.
		if ( '' !== DynamicContentOptionLoopPostMetaKey::get_meta_type_from_name( $name ) ) {
			$meta_type = DynamicContentOptionLoopPostMetaKey::get_meta_type_from_name( $name );
			$meta_key  = DynamicContentOptionLoopPostMetaKey::get_meta_key_from_name( $settings, $meta_type );

			// Determine the correct object ID based on meta type.
			// For user meta, use the user ID from loop_object; for term meta, use term ID; otherwise use loop_id (post ID).
			$object_id = $loop_id;
			if ( 'user' === $meta_type && $loop_object instanceof \WP_User ) {
				$object_id = $loop_object->ID;
			} elseif ( 'term' === $meta_type && $loop_object instanceof \WP_Term ) {
				$object_id = $loop_object->term_id;
			}

			$get_loop_post_meta_value = DynamicContentOptionLoopPostMetaKey::get_meta_value_by_type( $meta_type, $object_id, $meta_key );

			// If the value is empty or the meta key is empty, return empty string.
			if ( empty( $get_loop_post_meta_value ) || empty( $meta_key ) ) {
				return $before_text . '' . $after_text;
			}

			// We want to ensure that custom field conditions work correctly with ACF checkboxes.
			// Since ACF checkboxes return arrays, we need to handle this specific case.
			$get_loop_post_meta_value = ArrayUtility::is_array_of_strings( $get_loop_post_meta_value ) ? implode( ', ', $get_loop_post_meta_value ) : $get_loop_post_meta_value;

			// Apply date formatting if enabled by user, returns actual value if disabled or value is not a valid date.
			if ( ! is_array( $get_loop_post_meta_value ) && '' !== $get_loop_post_meta_value && ! empty( $meta_key ) && 'default' !== ( $settings['date_format'] ?? 'default' ) ) {
				$formatted_value          = ModuleUtils::format_date( $get_loop_post_meta_value, $settings );
				$get_loop_post_meta_value = ! empty( $formatted_value ) ? $formatted_value : $get_loop_post_meta_value;
			}

			if ( 'on' !== ( $settings['enable_html'] ?? 'off' ) && 'default' === ( $settings['date_format'] ?? 'default' ) ) {
				// Only escape HTML if date formatting wasn't applied.
				$get_loop_post_meta_value = esc_html( $get_loop_post_meta_value );
			}

			$get_loop_content_by_variable_name = $get_loop_post_meta_value;
		} else {
			$get_loop_content_by_variable_name = LoopUtils::get_loop_content_by_variable_name(
				$name,
				$loop_query_type,
				$loop_object,
				$settings
			);
		}

		return $before_text . $get_loop_content_by_variable_name . $after_text;
	}
}
