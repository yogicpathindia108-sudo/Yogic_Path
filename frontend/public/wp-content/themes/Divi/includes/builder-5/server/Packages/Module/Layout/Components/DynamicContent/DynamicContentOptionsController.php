<?php
/**
 * Module: DynamicContentOptionsController class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\Module\Layout\Components\DynamicContent;

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- WordPress REST API callbacks require specific signatures.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\Framework\Utility\LocaleUtility;

/**
 * Dynamic Content Options REST Controller class.
 *
 * @since ??
 */
class DynamicContentOptionsController extends RESTController {

	/**
	 * Get dynamic content options.
	 *
	 * Retrieves the options for Dynamic Content and returns a WP_REST_Response object with the options.
	 *
	 * @since ??
	 *
	 * @param \WP_REST_Request $request {
	 *   The REST request object.
	 *
	 *   @type string $postId The ID of the post.
	 * }
	 *
	 * @return \WP_REST_Response|\WP_Error The REST response object with the options,
	 *                                     or a WP_Error object if the request fails.
	 *
	 * @example:
	 * ```php
	 *  $request = new \WP_REST_Request();
	 *  $request->set_param( 'postId', '123' );
	 *  $response = DynamicContentOptionsController::index( $request );
	 * ```
	 *
	 * @example:
	 * ```php
	 * $request = new \WP_REST_Request();
	 * $request->set_param( 'postId', '456' );
	 * $response = DynamicContentOptionsController::index( $request );
	 * ```
	 */
	public static function index( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = $request->get_param( 'postId' );

		// TODO feat(D5, Translation): Handle locale switching for user profile language preference [https://github.com/elegantthemes/Divi/issues/45526].
		// Handle locale switching for user profile language preference.
		$locale_param    = $request->get_param( '_locale' );
		$locale_switched = false;

		// Switch locale if parameter is provided and valid.
		if ( in_array( $locale_param, [ 'user', 'site' ], true ) ) {
			$locale_switched = LocaleUtility::maybe_switch_locale( $locale_param );
		}

		// Check permission for custom fields.
		// If user doesn't have permission, use 'edit' context to hide custom fields.
		// If user has permission, use 'display' context to show custom fields.
		$can_read_custom_fields = et_pb_is_allowed( 'read_dynamic_content_custom_fields' );
		$context                = $can_read_custom_fields ? 'display' : 'edit';

		// Get dynamic content options.
		// When context is 'edit' and permission is off, register_option_callback will skip custom fields.
		$options = DynamicContentOptions::get_options( $post_id, $context );

		// Restore original locale if it was switched by us.
		if ( $locale_switched ) {
			LocaleUtility::maybe_restore_locale( $locale_switched );
		}

		// Build response with options and permission flag.
		// The options array contains all registered dynamic content options (custom fields filtered by register_option_callback).
		// The canReadCustomFields flag is used for client-side filtering.
		$response_data = array_merge(
			$options,
			[
				'canReadCustomFields' => $can_read_custom_fields,
			]
		);

		return self::response_success( $response_data );
	}

	/**
	 * Get the arguments for the index action.
	 *
	 * This function returns an array that defines the arguments for the index action,
	 * which is used in the `register_rest_route()` function.
	 *
	 * @since ??
	 *
	 * @return array An array of arguments for the index action.
	 */
	public static function index_args(): array {
		return [
			'postId'  => [
				'type'              => 'string',
				'required'          => true,
				'validate_callback' => function ( $param, $request, $key ) {
					return is_numeric( $param );
				},
				'sanitize_callback' => function ( $value, $request, $param ) {
					return (int) $value;
				},
			],
			'_locale' => [
				'type'              => 'string',
				'required'          => false,
				'validate_callback' => function ( $param, $request, $key ) {
					return in_array( $param, [ 'user', 'site' ], true );
				},
				'sanitize_callback' => function ( $value, $request, $param ) {
					return sanitize_text_field( $value );
				},
			],
		];
	}

	/**
	 * Get the permission status for the index action.
	 *
	 * This function checks if the current user has the permission to use the Visual Builder.
	 *
	 * @since ??
	 *
	 * @return bool Returns `true` if the current user has the permission to use the Visual Builder, `false` otherwise.
	 */
	public static function index_permission( \WP_REST_Request $request ): bool {
		$post_id = absint( $request->get_param( 'postId' ) );

		return UserRole::can_current_user_use_visual_builder()
			&& 0 < $post_id
			&& current_user_can( 'edit_post', $post_id );
	}
}
