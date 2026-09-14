<?php
/**
 * Module: DynamicContentOptionTermsGroups class.
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
 * Module: DynamicContentOptionTermsGroups class.
 *
 * @since ??
 */
class DynamicContentOptionTermsGroups extends DynamicContentOptionBase implements DynamicContentOptionInterface {
	/**
	 * Get the name of the terms groups option.
	 *
	 * @since ??
	 *
	 * @return string The name of the terms groups option.
	 */
	public function get_name(): string {
		return 'terms_groups';
	}

	/**
	 * Get the label for the terms groups option.
	 *
	 * @since ??
	 *
	 * @return string The label for the terms groups option.
	 */
	public function get_label(): string {
		return esc_html__( 'Terms Groups', 'et_builder_5' );
	}

	/**
	 * Callback for registering terms groups option.
	 *
	 * This function is a callback for the `divi_module_dynamic_content_options` filter.
	 * This function is used to register options for terms groups by adding them to the options array passed to the function.
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

		$term_fields = [
			'name'           => [
				'label' => esc_html__( 'Term Name', 'et_builder_5' ),
				'type'  => 'text',
			],
			'description'    => [
				'label' => esc_html__( 'Term Description', 'et_builder_5' ),
				'type'  => 'text',
			],
			'count'          => [
				'label' => esc_html__( 'Term Count', 'et_builder_5' ),
				'type'  => 'text',
			],
			'permalink'      => [
				'label' => esc_html__( 'Term Link', 'et_builder_5' ),
				'type'  => 'url',
			],
			'taxonomy'       => [
				'label' => esc_html__( 'Taxonomy', 'et_builder_5' ),
				'type'  => 'text',
			],
			'featured_image' => [
				'label' => esc_html__( 'Category Image', 'et_builder_5' ),
				'type'  => 'image',
			],
		];

		foreach ( $term_fields as $field_key => $field_data ) {
			$options[ 'loop_term_' . $field_key ] = [
				'id'     => 'loop_' . $field_key,
				'label'  => $field_data['label'],
				'type'   => $field_data['type'],
				'custom' => false,
				'group'  => 'Loop Terms',
				'fields' => $fields,
			];
		}

		return $options;
	}

	/**
	 * Render callback for terms groups option.
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
