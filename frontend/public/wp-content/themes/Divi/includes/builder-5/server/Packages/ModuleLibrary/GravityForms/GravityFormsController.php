<?php
/**
 * Gravity Forms module REST controller.
 *
 * @package Builder\Packages\ModuleLibrary
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\GravityForms;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GravityFormsController class.
 *
 * @since ??
 */
class GravityFormsController extends RESTController {

	/**
	 * Rendered HTML for the selected form (Visual Builder preview).
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public static function index( WP_REST_Request $request ): WP_REST_Response {
		$form_id            = sanitize_text_field( (string) $request->get_param( 'formId' ) );
		$use_ajax           = (string) $request->get_param( 'useAjax' );
		$validation_preview = (string) $request->get_param( 'validationPreview' );
		$post_id            = absint( $request->get_param( 'postId' ) );
		$use_ajax           = 'on' === $use_ajax ? 'on' : 'off';
		$validation_preview = 'on' === $validation_preview ? 'on' : 'off';

		return self::response_success(
			GravityFormsService::get_form_preview_response(
				[
					'formId'            => $form_id,
					'useAjax'           => $use_ajax,
					'context'           => 'visual_builder_preview',
					'validationPreview' => $validation_preview,
					'postId'            => $post_id,
				]
			)
		);
	}

	/**
	 * Renders Gravity Forms preview HTML (Visual Builder REST and module render entry point).
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $args Arguments for {@see GravityFormsService::render_form_preview()}.
	 *
	 * @return string
	 */
	public static function render_form_preview( array $args ): string {
		return GravityFormsService::render_form_preview( $args );
	}

	/**
	 * REST args for preview endpoint.
	 *
	 * @since ??
	 *
	 * @return array<string, mixed>
	 */
	public static function index_args(): array {
		return [
			'formId'            => [
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'useAjax'           => [
				'type'              => 'string',
				'enum'              => [ 'on', 'off' ],
				'default'           => 'off',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'validationPreview' => [
				'type'              => 'string',
				'enum'              => [ 'on', 'off' ],
				'default'           => 'off',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'postId'            => [
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			],
		];
	}

	/**
	 * Permission callback.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function index_permission(): bool {
		return UserRole::can_current_user_use_visual_builder();
	}
}
