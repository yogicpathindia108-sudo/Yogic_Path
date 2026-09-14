<?php
/**
 * Module: DynamicContentOptionLoopMenuOrder class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Layout\Components\DynamicContent;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Module: DynamicContentOptionLoopMenuOrder class.
 *
 * @since ??
 */
class DynamicContentOptionLoopMenuOrder extends DynamicContentOptionBase implements DynamicContentOptionInterface {

	/**
	 * Get the name of the loop menu order option.
	 *
	 * @since ??
	 *
	 * @return string The name of the loop menu order option.
	 */
	public function get_name(): string {
		return 'loop_menu_menu_order';
	}

	/**
	 * Get the label for the loop menu order option.
	 *
	 * @since ??
	 *
	 * @return string The label for the loop menu order option.
	 */
	public function get_label(): string {
		return __( 'Loop Menu Order', 'et_builder_5' );
	}

	/**
	 * Callback for registering loop menu order option.
	 *
	 * This function is a callback for the `divi_module_dynamic_content_options` filter.
	 * This function is used to register options for loop menu order by adding them to the options array passed to the function.
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
		if ( ! isset( $options[ $this->get_name() ] ) ) {
			$options[ $this->get_name() ] = [
				'id'     => $this->get_name(),
				'label'  => esc_html( $this->get_label() ),
				'type'   => 'text',
				'custom' => false,
				'group'  => 'Loop Menus',
				'fields' => DynamicContentUtils::get_common_loop_fields(),
			];
		}

		return $options;
	}

	/**
	 * Render callback for loop menu order option.
	 *
	 * Retrieves the value of loop menu order option based on the provided arguments and settings.
	 * This is a callback for `divi_module_dynamic_content_resolved_value` filter.
	 *
	 * @since ??
	 *
	 * @param mixed $value     The current value of the loop menu order option.
	 * @param array $data_args {
	 *     Optional. An array of arguments for retrieving the loop menu order.
	 *     Default `[]`.
	 *
	 *     @type string  $name            Optional. Option name. Default empty string.
	 *     @type array   $settings        Optional. Option settings. Default `[]`.
	 *     @type integer $post_id        Optional. Post Id. Default `null`.
	 *     @type string  $loop_query_type Optional. Loop query type. Default empty string.
	 *     @type mixed   $loop_object     Optional. Loop object (menu item). Default `null`.
	 * }
	 *
	 * @return string The formatted value of the loop menu order option.
	 */
	public function render_callback( $value, array $data_args = [] ): string {
		$name        = $data_args['name'] ?? '';
		$settings    = $data_args['settings'] ?? [];
		$loop_object = $data_args['loop_object'] ?? null;

		if ( $name !== $this->get_name() ) {
			return $value;
		}

		// Extract menu order from loop object (menu item).
		if ( is_object( $loop_object ) && isset( $loop_object->menu_order ) ) {
			$value = esc_html( (string) $loop_object->menu_order );
		} elseif ( is_array( $loop_object ) && isset( $loop_object['menu_order'] ) ) {
			$value = esc_html( (string) $loop_object['menu_order'] );
		} else {
			$value = '';
		}

		return DynamicContentElements::get_wrapper_element(
			[
				'post_id'  => null, // Menu items are not post-specific.
				'name'     => $name,
				'value'    => $value,
				'settings' => $settings,
			]
		);
	}
}
