<?php
/**
 * Module: DynamicContentOptionACFGroups class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Layout\Components\DynamicContent;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentUtils;

/**
 * Module: DynamicContentOptionACFGroups class.
 *
 * @since ??
 */
class DynamicContentOptionACFGroups extends DynamicContentOptionBase implements DynamicContentOptionInterface {
	/**
	 * Get the name of the ACF groups option.
	 *
	 * @since ??
	 *
	 * @return string The name of the ACF groups option.
	 */
	public function get_name(): string {
		return 'acf_groups';
	}

	/**
	 * Get the label for the ACF groups option.
	 *
	 * @since ??
	 *
	 * @return string The label for the ACF groups option.
	 */
	public function get_label(): string {
		return esc_html__( 'ACF Groups', 'et_builder_5' );
	}

	/**
	 * Get the field type for the ACF groups option.
	 *
	 * @since ??
	 *
	 * @param string $type The type of the field.
	 *
	 * @return string The field type.
	 */
	public function get_field_type( string $type ): string {
		if ( in_array( $type, [ 'page_link', 'link', 'url' ], true ) ) {
			return 'url';
		}

		if ( 'image' === $type ) {
			return 'image';
		}

		if ( 'color_picker' === $type ) {
			return 'color';
		}

		return 'text';
	}

	/**
	 * Callback for registering ACF groups option.
	 *
	 * This function is a callback for the `divi_module_dynamic_content_options` filter.
	 * This function is used to register options for ACF groups by adding them to the options array passed to the function.
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
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Check if ACF, ACF Pro, or SCF (Secure Custom Fields) is active.
		// SCF is a fork of ACF that uses the same function names and API.
		if ( ! is_plugin_active( 'advanced-custom-fields/acf.php' )
			&& ! is_plugin_active( 'advanced-custom-fields-pro/acf.php' )
			&& ! is_plugin_active( 'secure-custom-fields/secure-custom-fields.php' ) ) {
			return $options;
		}

		$compat_file = ET_BUILDER_DIR . 'plugin-compat/advanced-custom-fields.php';
		if ( ! file_exists( $compat_file ) ) {
			return $options;
		}

		// ACF class might not be loaded, so we need to load it.
		require_once $compat_file;

		$acf_options = [];
		$acf_compat  = new \ET_Builder_Plugin_Compat_Advanced_Custom_Fields();
		$repeaters   = $acf_compat->get_repeater_fields();

		if ( ! empty( $repeaters ) ) {
			foreach ( $repeaters as $group => $repeater ) {
				$acf_options[ $repeater['group'] ] = [
					'name'       => $repeater['name'],
					'sub_fields' => $repeater['sub_fields'] ?? [],
				];
			}
		}

		foreach ( $acf_options as $group_key => $groups ) {
			if ( ! isset( $groups['sub_fields'] ) || ! is_array( $groups['sub_fields'] ) ) {
				continue;
			}

			foreach ( $groups['sub_fields'] as $field ) {
				// Build fields array with common loop fields and Raw HTML option.
				$fields = DynamicContentUtils::get_common_loop_fields();

				$acf_date_field_types = [ 'date_picker', 'date_time_picker', 'time_picker' ];
				if ( in_array( $field['type'], $acf_date_field_types, true ) ) {
					$date_format_fields = DynamicContentUtils::get_date_format_fields();
					$fields             = array_merge( $fields, $date_format_fields );
				}

				// Add Raw HTML option for users with unfiltered_html capability.
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

				$option_key             = 'loop_acf_' . $groups['name'] . '|||' . $field['name'];
				$options[ $option_key ] = [
					'id'       => 'loop_' . $field['name'],
					'label'    => $group_key . ': ' . $field['label'],
					'type'     => $this->get_field_type( $field['type'] ),
					'custom'   => false,
					'group'    => 'Loop ACF ' . $groups['name'],
					'fields'   => $fields,
					'acf_type' => $field['type'],
				];
			}
		}

		return $options;
	}

	/**
	 * Render callback for ACF groups option.
	 *
	 * The main rendering happens from the visual builder.
	 *
	 * @since ??
	 *
	 * @param string $value     The value to render.
	 * @param array  $data_args Additional data arguments for rendering.
	 *
	 * @return string The rendered value.
	 */
	public function render_callback( $value, array $data_args = [] ): string {
		return $value;
	}
}
