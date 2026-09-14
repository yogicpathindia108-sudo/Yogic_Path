<?php
/**
 * Dynamic Data: DynamicDataController.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Layout\Components\DynamicData;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\Packages\Module\Layout\Components\DynamicData\DynamicData;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Dynamic Data REST Controller class.
 *
 * @since ??
 */
class DynamicDataController extends RESTController {

	/**
	 * Process and retrieve the resolved values for dynamic data.
	 *
	 * Iterates through the provided data array and calls the `DynamicData::get_processed_dynamic_data`
	 * to retrieve the resolved values for each element in the array.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response The REST response object containing the resolved values.
	 *
	 * @example
	 * ```php
	 *  $request = new WP_REST_Request();
	 *  $request->set_param( 'data', $data );
	 *  $response = DynamicDataController::index( $request );
	 * ```
	 */
	public static function index( WP_REST_Request $request ): WP_REST_Response {
		$result = [];
		$data   = $request->get_param( 'data' );

		foreach ( $data as $datum ) {
			$context = isset( $datum['context'] ) && is_array( $datum['context'] ) ? $datum['context'] : [];

			$result[] = [
				'resolvedValue' => DynamicData::get_processed_dynamic_data( $datum['value'], $datum['postId'], false, null, null, null, $context ),
			];
		}

		return self::response_success( $result );
	}

	/**
	 * Get the arguments for the index action.
	 *
	 * This function returns an array that defines the arguments for the index action, which is used in the `register_rest_route()` function.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for the index action.
	 */
	public static function index_args(): array {
		return [
			'data' => [
				'type'  => 'array',
				'items' => [
					'type'       => 'object',
					'properties' => [
						'postId'  => [
							'type'     => 'integer',
							'required' => true,
						],
						'value'   => [
							'type'     => 'string',
							'required' => true,
						],
						'context' => [
							'type'       => 'object',
							'required'   => false,
							'properties' => [
								'requestType'    => [
									'type'     => 'string',
									'required' => false,
								],
								'currentPageId'  => [
									'type'     => 'integer',
									'required' => false,
								],
								'currentPageUrl' => [
									'type'     => 'string',
									'required' => false,
								],
							],
						],
					],
				],
			],
		];
	}

	/**
	 * Check whether current user can edit all requested posts.
	 *
	 * @since ??
	 *
	 * @param array $data Dynamic data payload.
	 *
	 * @return bool
	 */
	private static function _can_edit_requested_posts( array $data ): bool {
		foreach ( $data as $datum ) {
			if ( ! is_array( $datum ) ) {
				return false;
			}

			foreach ( self::_get_requested_post_ids( $datum ) as $post_id ) {
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Get all post IDs referenced by a dynamic-data request item.
	 *
	 * This includes top-level `postId` and inner `value.post_id` references.
	 *
	 * @since ??
	 *
	 * @param array $datum Request item data.
	 *
	 * @return int[]
	 */
	private static function _get_requested_post_ids( array $datum ): array {
		$post_ids = [];
		$post_id  = isset( $datum['postId'] ) ? (int) $datum['postId'] : 0;

		if ( 0 < $post_id ) {
			$post_ids[] = $post_id;
		}

		$datum_value = $datum['value'] ?? '';
		if ( ! is_string( $datum_value ) || '' === $datum_value ) {
			return array_values( array_unique( $post_ids ) );
		}

		$variable_values = DynamicData::get_variable_values( $datum_value );
		foreach ( $variable_values as $variable_value ) {
			$data_value = DynamicData::get_data_value( $variable_value );
			$value      = $data_value['value'] ?? [];

			if ( is_array( $value ) ) {
				$inner_post_id = isset( $value['post_id'] ) ? (int) $value['post_id'] : 0;
				if ( 0 < $inner_post_id ) {
					$post_ids[] = $inner_post_id;
				}
			}
		}

		return array_values( array_unique( $post_ids ) );
	}

	/**
	 * Provides the permission status for the index action.
	 *
	 * This function checks if the current user has the permission to use the Visual Builder.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return bool Returns `true` if the current user has the permission to use the Visual Builder, `false` otherwise.
	 */
	public static function index_permission( WP_REST_Request $request ): bool {
		if ( ! UserRole::can_current_user_use_visual_builder() ) {
			return false;
		}

		$data = $request->get_param( 'data' );
		$data = is_array( $data ) ? $data : [];

		return self::_can_edit_requested_posts( $data );
	}
}
