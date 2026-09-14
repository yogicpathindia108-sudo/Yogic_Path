<?php
/**
 * Module Library: Breadcrumbs REST controller.
 *
 * @package Divi
 * @since   ??
 */

namespace ET\Builder\Packages\ModuleLibrary\Breadcrumbs;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use WP_REST_Request;

/**
 * Breadcrumbs REST controller.
 *
 * @since ??
 */
class BreadcrumbsController extends RESTController {
	/**
	 * Retrieve the rendered breadcrumb HTML.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return mixed
	 */
	public static function index( WP_REST_Request $request ) {
		$params = $request->get_params();

		return self::response_success(
			[
				'html' => BreadcrumbsModule::get_breadcrumb(
					[
						'home_text'        => $params['homeText'] ?? '',
						'home_url'         => $params['homeUrl'] ?? '',
						'separator'        => $params['separator'] ?? '',
						'html_tag'         => $params['htmlTag'] ?? 'nav',
						'request_type'     => $params['requestType'] ?? '',
						'current_page'     => $params['currentPage'] ?? [],
						'use_placeholders' => true,
					]
				),
			]
		);
	}

	/**
	 * Returns index arguments.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function index_args(): array {
		return [
			'homeText'    => [
				'required'          => false,
				'description'       => __( 'Text for the home breadcrumb item.', 'et_builder_5' ),
				'sanitize_callback' => 'sanitize_text_field',
			],
			'homeUrl'     => [
				'required'          => false,
				'description'       => __( 'URL for the home breadcrumb item.', 'et_builder_5' ),
				'sanitize_callback' => 'esc_url_raw',
			],
			'separator'   => [
				'required'          => false,
				'description'       => __( 'Separator string between breadcrumb items.', 'et_builder_5' ),
				'sanitize_callback' => 'sanitize_text_field',
			],
			'htmlTag'     => [
				'required'          => false,
				'description'       => __( 'Wrapper HTML tag.', 'et_builder_5' ),
				'sanitize_callback' => 'sanitize_text_field',
			],
			'requestType' => [
				'required'          => false,
				'description'       => __( 'Request type for current preview context.', 'et_builder_5' ),
				'sanitize_callback' => 'sanitize_text_field',
			],
			'currentPage' => [
				'required'          => false,
				'description'       => __( 'Current page object for preview context.', 'et_builder_5' ),
				'sanitize_callback' => function ( $param ) {
					if ( ! is_array( $param ) ) {
						return [];
					}

					$current_page = [];

					if ( isset( $param['id'] ) ) {
						$current_page['id'] = absint( $param['id'] );
					}

					if ( isset( $param['title'] ) ) {
						$current_page['title'] = sanitize_text_field( $param['title'] );
					}

					if ( isset( $param['mainLoopSettingsData'] ) && is_array( $param['mainLoopSettingsData'] ) ) {
						$current_page['mainLoopSettingsData'] = [];

						if ( isset( $param['mainLoopSettingsData']['termId'] ) ) {
							$current_page['mainLoopSettingsData']['termId'] = absint( $param['mainLoopSettingsData']['termId'] );
						}

						if ( isset( $param['mainLoopSettingsData']['taxonomy'] ) ) {
							$current_page['mainLoopSettingsData']['taxonomy'] = sanitize_key( $param['mainLoopSettingsData']['taxonomy'] );
						}
					}

					return $current_page;
				},
			],
		];
	}

	/**
	 * Permission callback for index endpoint.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function index_permission(): bool {
		return UserRole::can_current_user_use_visual_builder();
	}
}
