<?php
/**
 * Module: DynamicContentOptionPostID class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Layout\Components\DynamicContent;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Module: DynamicContentOptionPostID class.
 *
 * @since ??
 */
class DynamicContentOptionPostID extends DynamicContentOptionBase implements DynamicContentOptionInterface {

	/**
	 * Get the name of the post id option.
	 *
	 * @since ??
	 *
	 * @return string The name of the post id option.
	 */
	public function get_name(): string {
		return 'post_id';
	}

	/**
	 * Get the label for the post id option.
	 *
	 * This function retrieves the localized label for the post id option,
	 * which is used to describe the post id in user interfaces.
	 *
	 * @since ??
	 *
	 * @return string The label for the post id option.
	 */
	public function get_label(): string {
		// Translators: %1$s: Post type name.
		return __( 'Loop %1$s ID', 'et_builder_5' );
	}

	/**
	 * Callback for registering post id option.
	 *
	 * This function is a callback for the `divi_module_dynamic_content_options` filter.
	 * This function is used to register options for post id by adding them to the options array passed to the function.
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
	public function register_option_callback( array $options, int $post_id, string $context ): array {
		if ( ! isset( $options[ 'loop_' . $this->get_name() ] ) ) {
			$options[ 'loop_' . $this->get_name() ] = [
				'id'     => 'loop_' . $this->get_name(),
				'label'  => esc_html( sprintf( $this->get_label(), DynamicContentUtils::get_post_type_label( $post_id ) ) ),
				'type'   => 'text',
				'custom' => false,
				'group'  => 'Loop',
				'fields' => DynamicContentUtils::get_common_loop_fields(),
			];
		}

		return $options;
	}

	/**
	 * Render callback for post id option.
	 *
	 * Retrieves the value of post id option based on the provided arguments and settings.
	 * This is a callback for `divi_module_dynamic_content_resolved_value` filter.
	 *
	 * @since ??
	 *
	 * @param mixed $value     The current value of the post id option.
	 * @param array $data_args {
	 *     Optional. An array of arguments for retrieving the post id.
	 *     Default `[]`.
	 *
	 *     @type string  $name       Optional. Option name. Default empty string.
	 *     @type array   $settings   Optional. Option settings. Default `[]`.
	 *     @type integer $post_id    Optional. Post Id. Default `null`.
	 * }
	 *
	 * @return string The formatted value of the post id option.
	 */
	public function render_callback( $value, array $data_args = [] ): string {
		return $value;
	}
}
