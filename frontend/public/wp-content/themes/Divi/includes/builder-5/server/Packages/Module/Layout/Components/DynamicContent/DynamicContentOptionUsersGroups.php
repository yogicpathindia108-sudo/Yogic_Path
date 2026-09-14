<?php
/**
 * Module: DynamicContentOptionUsersGroups class.
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
 * Module: DynamicContentOptionUsersGroups class.
 *
 * @since ??
 */
class DynamicContentOptionUsersGroups extends DynamicContentOptionBase implements DynamicContentOptionInterface {
	/**
	 * Get the name of the users groups option.
	 *
	 * @since ??
	 *
	 * @return string The name of the users groups option.
	 */
	public function get_name(): string {
		return 'users_groups';
	}

	/**
	 * Get the label for the users groups option.
	 *
	 * @since ??
	 *
	 * @return string The label for the users groups option.
	 */
	public function get_label(): string {
		return esc_html__( 'Users Groups', 'et_builder_5' );
	}

	/**
	 * Callback for registering users groups option.
	 *
	 * This function is a callback for the `divi_module_dynamic_content_options` filter.
	 * This function is used to register options for users groups by adding them to the options array passed to the function.
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
		$fields = DynamicContentUtils::get_common_loop_fields();

		$user_fields = [
			'name'        => [
				'label' => esc_html__( 'Display Name', 'et_builder_5' ),
				'type'  => 'text',
			],
			'username'    => [
				'label' => esc_html__( 'Username', 'et_builder_5' ),
				'type'  => 'text',
			],
			'email'       => [
				'label' => esc_html__( 'Email', 'et_builder_5' ),
				'type'  => 'text',
			],
			'avatar'      => [
				'label' => esc_html__( 'Avatar', 'et_builder_5' ),
				'type'  => 'image',
			],
			'description' => [
				'label' => esc_html__( 'Bio/Description', 'et_builder_5' ),
				'type'  => 'text',
			],
			'url'         => [
				'label' => esc_html__( 'User Link', 'et_builder_5' ),
				'type'  => 'url',
			],
		];

		foreach ( $user_fields as $field_key => $field_data ) {
			$options[ 'loop_user_' . $field_key ] = [
				'id'     => 'loop_user_' . $field_key,
				'label'  => $field_data['label'],
				'type'   => $field_data['type'],
				'custom' => false,
				'group'  => 'Loop Users',
				'fields' => $fields,
			];
		}

		return $options;
	}

	/**
	 * Render callback for users groups option.
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
		$name     = $data_args['name'] ?? '';
		$settings = $data_args['settings'] ?? [];
		$post_id  = $data_args['post_id'] ?? null;

		if ( $name !== $this->get_name() ) {
			return $value;
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
}
