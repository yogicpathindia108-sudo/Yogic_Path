<?php
/**
 * REST: SyncToServerController class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\REST\SyncToServer;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Security\Security;
use ET\Builder\VisualBuilder\Saving\SavingUtility;
use ET\Builder\Framework\Revision\Revision;
use ET\Builder\Packages\Module\Layout\Components\DynamicData\DynamicData;
use ET\Builder\VisualBuilder\REST\Portability\PortabilityController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * SyncToServerController class.
 *
 * This class extends the RESTController class and provides functionality for syncing data to the server.
 *
 * @since ??
 */
class SyncToServerController extends RESTController {

	/**
	 * Update post content and status.
	 *
	 * This function updates the post content and status based on the given parameters.
	 * It retrieves the post ID, post status, content, and preferences from the provided `$request` object.
	 * It then calls the `wp_update_post()` function to update the post with the new content and status.
	 * If there is an autosave that is newer, it deletes the existing autosave.
	 * It also calls `et_save_post` and `divi_visual_builder_rest_save_post` action hooks to perform actions before the update
	 * as well as `et_update_post`, and `divi_visual_builder_rest_update_post` action hooks and also `et_fb_ajax_save_verification_result`,
	 * and `divi_visual_builder_rest_save_post_save_verification` filters all applied after the update.
	 * Finally, it sanitizes and updates the app preferences using the `SavingUtility::sanitize_app_preferences()`, filters the save
	 * verification result, and returns the updated post status, save verification status, and rendered content as a response.
	 *
	 * @since 3.2.0
	 *
	 * @deprecated 5.0.0 Use `divi_visual_builder_rest_save_post` hook instead.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response|WP_Error `WP_REST_Response` object on success, `WP_Error` object on failure.
	 */
	public static function update( WP_REST_Request $request ) {
		$post_id                 = (int) $request->get_param( 'post_id' );
		$post_status             = $request->get_param( 'post_status' );
		$content                 = $request->get_param( 'content' );
		$page_settings_by_layout = $request->get_param( 'pageSettingsByLayout' );
		$sync_type               = $request->get_param( 'syncType' );
		$options                 = $request->get_param( 'options' );
		$prime_cache_url         = $request->get_param( 'prime_cache_url' );
		$main_loop_type          = $request->get_param( 'mainLoopType' );
		$main_loop_type          = is_string( $main_loop_type ) ? $main_loop_type : 'singular';
		$loop_settings_data      = $request->get_param( 'mainLoopSettingsData' );
		$layout_post_ids         = $request->get_param( 'layout_post_ids' );
		$layout_post_ids         = is_array( $layout_post_ids ) ? $layout_post_ids : [];
		$layout_post_ids         = self::sanitize_layout_post_ids( $layout_post_ids );
		$page_settings_by_layout = is_array( $page_settings_by_layout ) ? $page_settings_by_layout : [];
		$layout_post_ids         = self::_validate_and_normalize_layout_post_ids( $layout_post_ids, $post_id, $main_loop_type );

		if ( is_wp_error( $layout_post_ids ) ) {
			return self::response_error(
				$layout_post_ids->get_error_code(),
				$layout_post_ids->get_error_message(),
				$layout_post_ids->get_error_data()
			);
		}

		foreach ( $page_settings_by_layout as $layout => $layout_page_settings ) {
			if ( ! is_array( $layout_page_settings ) || ! isset( $layout_page_settings['customCss'] ) ) {
				continue;
			}

			$page_settings_by_layout[ $layout ]['customCss'] = str_replace( '\\', '\\\\', $layout_page_settings['customCss'] );
		}

		$post_content             = isset( $content['post_content'] ) ? $content['post_content'] : '';
		$header_content           = isset( $content['header'] ) ? $content['header'] : '';
		$body_content             = isset( $content['body'] ) ? $content['body'] : '';
		$footer_content           = isset( $content['footer'] ) ? $content['footer'] : '';
		$has_post_content_payload = '' !== $post_content;

		$post_content_post_id       = isset( $layout_post_ids['postContent'] ) ? absint( $layout_post_ids['postContent'] ) : $post_id;
		$header_post_id             = isset( $layout_post_ids['header'] ) ? absint( $layout_post_ids['header'] ) : 0;
		$body_post_id               = isset( $layout_post_ids['body'] ) ? absint( $layout_post_ids['body'] ) : 0;
		$footer_post_id             = isset( $layout_post_ids['footer'] ) ? absint( $layout_post_ids['footer'] ) : 0;
		$post_content_page_settings = isset( $page_settings_by_layout['postContent'] ) && is_array( $page_settings_by_layout['postContent'] )
			? $page_settings_by_layout['postContent']
			: [];

		// Guardrail: never allow empty placeholder-only payloads to overwrite Theme Builder layouts.
		$header_has_content = self::_has_meaningful_layout_content( $header_content );
		$body_has_content   = self::_has_meaningful_layout_content( $body_content );
		$footer_has_content = self::_has_meaningful_layout_content( $footer_content );

		if ( ! $header_has_content ) {
			$header_post_id = 0;
		}

		if ( ! $body_has_content ) {
			$body_post_id = 0;
		}

		if ( ! $footer_has_content ) {
			$footer_post_id = 0;
		}

		// Extract off-canvas data and make it available for hooks.
		$off_canvas_data = $request->get_param( 'off_canvas_data' );
		if ( $off_canvas_data ) {
			// Normalize to array format - decode JSON string if needed.
			$normalized_canvas_data = self::_normalize_off_canvas_data( $off_canvas_data );
			if ( $normalized_canvas_data ) {
				// Store off-canvas data globally so hooks can access it.
				$GLOBALS['divi_off_canvas_data'] = $normalized_canvas_data;
			}
		}

		// Check if there is an autosave that is newer.
		$post_author = get_current_user_id();

		// Store one autosave per author. If there is already an autosave, overwrite it.
		$autosave = wp_get_post_autosave( $post_id, $post_author );

		if ( ! empty( $autosave ) ) {
			wp_delete_post_revision( $autosave->ID );
		}

		// Check if what is being synced to server is preview post.
		$is_saving_preview = 'preview' === $sync_type;

		// Preview post aims to have similar post previewing experience as WordPress block editor.
		// How preview post works in WordPress block editor:
		// 1. Preview button is clicked.
		// 2. REST request is sent to `/wp-json/wp/v2/pages/POST_ID/autosaves?_locale=user` endpoint.
		// 3. An autosave post for current post is created. Autosave post is another record on `wp_post` table that:
		// - has post_status: `inherit`
		// - has post_type: `revision`
		// - has post_parent pointing to the published / draft post ID
		// 4. Basically the autosave post is the most recent revision of the post. A user commonly can have only have
		// one autosave per post.
		// 5. Once autosave post is created, the preview post URL is generated and returned to the client.
		// 6. User is being redirected to the preview post URL
		// For WordPress block editor's preview post mechanism, see: `WP_REST_Autosaves_Controller->create_item()`.

		if ( $is_saving_preview ) {
			// Create new autosave as post revision that will be used for preview.
			$revision_id = Revision::put_post_revision(
				[
					'ID'           => $post_id,
					'post_title'   => $post_content_page_settings['postTitle'] ?? '',

					// `_wp_put_post_revision()` already does the `wp_slash()` for the content. No need apply `wp_slash()` here.
					'post_content' => $post_content,
				],
				true
			);

			// Create preview post link.
			// Equivalent of this on WordPress block editor can be seen at WP_REST_Autosaves_Controller->prepare_item_for_response().
			$preview_url = get_preview_post_link(
				$post_id,
				[
					'preview_id'    => $post_id,
					'preview_nonce' => wp_create_nonce( 'post_preview_' . $post_id ),
				]
			);

			return self::response_success(
				[
					'previewId'         => $revision_id,
					'previewUrl'        => $preview_url,
					'syncType'          => 'preview',
					'post_status'       => $post_status,
					'save_verification' => $revision_id ? true : false,
				]
			);
		}

		/**
		 * Filters whether to proceed with the post save operation.
		 *
		 * This filter allows modules to intercept the save process and prevent
		 * the default wp_update_post() call. If the filter returns an array,
		 * that array will be returned as the success response and no save will occur.
		 * If the filter returns null, the save will proceed normally.
		 *
		 * @since ??
		 *
		 * @param array|null $abort_save_response Response array to return if save should be aborted, or null to proceed.
		 * @param int        $post_id             The post ID being saved.
		 * @param string     $post_content        The post content being saved.
		 * @param string     $post_status         The post status being saved.
		 * @param array      $options             The options array containing conditional_tags and other metadata.
		 * @param array      $post_content_page_settings The post content page settings array (if any).
		 */
		$abort_save_response = apply_filters(
			'divi_visual_builder_rest_before_save_post',
			null,
			$post_id,
			$post_content,
			$post_status,
			$options,
			$post_content_page_settings
		);

		if ( is_array( $abort_save_response ) ) {
			// A filter has intercepted the save and provided a custom response.
			return self::response_success( $abort_save_response );
		}

		// Build the post update array.
		$post_update_data = [
			'ID'          => $post_id,
			'post_status' => $post_status,
		];

		// Ignore invalid empty-string post content payloads, while still allowing status/title/excerpt updates.
		if ( $has_post_content_payload ) {
			$post_update_data['post_content'] = wp_slash( $post_content );
		}

		// Include post_title and post_excerpt in this update to avoid a second wp_update_post() call.
		// This prevents duplicate revisions and duplicate filter processing.
		// Note: wp_update_post() internally calls sanitize_post() which sanitizes both fields,
		// but we sanitize here for consistency with the existing approach in et_builder_update_settings().
		if ( $post_content_page_settings ) {
			if ( isset( $post_content_page_settings['postTitle'] ) ) {
				$post_update_data['post_title'] = sanitize_text_field( $post_content_page_settings['postTitle'] );
			}

			if ( isset( $post_content_page_settings['postExcerpt'] ) ) {
				// Use wp_kses_post for excerpt to allow certain HTML tags (same as et_builder_update_settings).
				$post_update_data['post_excerpt'] = wp_kses_post( $post_content_page_settings['postExcerpt'] );
			}
		}

		// Persist main post content/meta only for singular VB saves.
		// On non-singular pages (home/archive), request post_id may be the first
		// main-query loop post and must not be converted to Divi (#49679).
		$main_post                = get_post( $post_id );
		$should_persist_main_post = $main_post instanceof \WP_Post && 'singular' === $main_loop_type;
		$update                   = $should_persist_main_post ? wp_update_post( $post_update_data ) : 0;

		$layout_updates                        = [
			[
				'layout'  => 'postContent',
				'id'      => $post_content_post_id,
				'content' => $post_content,
			],
			[
				'layout'  => 'header',
				'id'      => $header_post_id,
				'content' => $header_content,
			],
			[
				'layout'  => 'body',
				'id'      => $body_post_id,
				'content' => $body_content,
			],
			[
				'layout'  => 'footer',
				'id'      => $footer_post_id,
				'content' => $footer_content,
			],
		];
		$updated_theme_builder_layout_post_ids = [];

		foreach ( $layout_updates as $layout_update ) {
			$target_layout  = $layout_update['layout'] ?? 'postContent';
			$target_post_id = absint( $layout_update['id'] ?? 0 );
			$target_content = $layout_update['content'] ?? '';

			// Main post content is already updated above.
			if ( $post_id === $target_post_id ) {
				continue;
			}

			$layout_update_result = self::_update_layout_post_content( $target_post_id, $target_content );

			if ( is_wp_error( $layout_update_result ) ) {
				return self::response_error(
					$layout_update_result->get_error_code(),
					$layout_update_result->get_error_message(),
					$layout_update_result->get_error_data()
				);
			}

			if ( 0 !== (int) $layout_update_result && in_array( $target_layout, [ 'header', 'body', 'footer' ], true ) ) {
				$updated_theme_builder_layout_post_ids[] = $target_post_id;
			}
		}

		// Ensure Theme Builder layout saves clear template-targeted frontend caches.
		// In non-singular Visual Builder contexts, the main request post ID is not a layout post,
		// so cache clearing must also run for explicitly saved layout post IDs.
		if ( ! empty( $updated_theme_builder_layout_post_ids ) ) {
			foreach ( array_unique( $updated_theme_builder_layout_post_ids ) as $updated_layout_post_id ) {
				et_theme_builder_clear_wp_post_cache( $updated_layout_post_id );
			}
		}

		// Save page settings by layout post ID.
		if ( ! empty( $page_settings_by_layout ) ) {
			self::_save_page_settings_by_layout(
				$page_settings_by_layout,
				[
					'postContent' => $post_content_post_id,
					'header'      => $header_post_id,
					'body'        => $body_post_id,
					'footer'      => $footer_post_id,
				],
				$post_id
			);
		}

		// Save non-singular page settings (term properties, author properties, etc.).
		// The frontend syncs field edits back into `mainLoopSettingsData`, so the
		// updated values arrive here directly — no separate property needed.
		if ( 'singular' !== $main_loop_type && is_array( $loop_settings_data ) ) {
			$expected_main_loop_owner_context = self::_get_expected_main_loop_owner_context( $main_loop_type );

			SavingUtility::save_non_singular_page_settings(
				$loop_settings_data,
				$main_loop_type,
				$expected_main_loop_owner_context
			);
		}

		/**
		 * Action hook to fire when the Post is being saved.
		 *
		 * This is for backward compatibility with hooks written for Divi version <5.0.0.
		 *
		 * @since 3.20
		 * @deprecated 5.0.0 Use `divi_visual_builder_rest_save_post` hook instead.
		 *
		 * @param int $post_id Post ID.
		 */
		do_action(
			'et_save_post',
			$post_id
		);

		/**
		 * Action hook to fire when the Post is being saved.
		 *
		 * @since ??
		 *
		 * @param int $post_id Post ID.
		 */
		// Pass request context into save hooks.
		// This avoids relying on globals and is extendable for future non-singular owners.
		do_action(
			'divi_visual_builder_rest_save_post',
			$post_id,
			[
				'mainLoopType'         => $main_loop_type,
				'mainLoopSettingsData' => $loop_settings_data,
			]
		);

		// Prime page cache AFTER off-canvas data and other post meta is saved.
		// This ensures that when the cache priming request triggers early detection,
		// all post meta (including appended canvas content) is available.
		SavingUtility::prime_page_cache_on_save( $post_id, $prime_cache_url );

		if ( ( $update && ! is_wp_error( $update ) ) || ( ! $should_persist_main_post && ! is_wp_error( $update ) ) ) {
			// Update post meta so we know D5 is used and Readiness migrator will skip it.
			if ( $should_persist_main_post ) {
				update_post_meta( $post_id, '_et_pb_use_divi_5', 'on' );
				// Also set the legacy meta for backward compatibility with D4 components.
				update_post_meta( $post_id, '_et_pb_use_builder', 'on' );

				// Clear the page creation flow flag so it doesn't show again on subsequent loads.
				update_post_meta( $post_id, '_et_pb_show_page_creation', 'off' );
			}

			$layout_ids_to_mark = [
				$post_content_post_id,
				$header_post_id,
				$body_post_id,
				$footer_post_id,
			];

			foreach ( $layout_ids_to_mark as $layout_id ) {
				$layout_id = absint( $layout_id );

				if ( 0 === $layout_id || $post_id === $layout_id ) {
					continue;
				}

				update_post_meta( $layout_id, '_et_pb_use_divi_5', 'on' );
				update_post_meta( $layout_id, '_et_pb_use_builder', 'on' );
			}

			/**
			 * Action hook to fire when the Post is updated.
			 *
			 * This is for backward compatibility with hooks written for Divi version <5.0.0.
			 *
			 * @param int $post_id Post ID.
			 *
			 * @since 3.29
			 * @deprecated 5.0.0 Use `divi_visual_builder_rest_update_post` hook instead.
			 */
			do_action(
				'et_update_post',
				$post_id
			);

			/**
			 * Action hook to fire when the Post is updated.
			 *
			 * @param int $post_id Post ID.
			 *
			 * @since ??
			 */
			do_action( 'divi_visual_builder_rest_update_post', $post_id );

			$saved_post_content = $should_persist_main_post ? get_post_field( 'post_content', $update ) : $post_content;
			$verification       = $should_persist_main_post
				? self::_verify_post_content_matches_after_sanitization( $post_content, $saved_post_content )
				: true;

			/**
			 * Filter to modify the save verification result.
			 *
			 * @since ??
			 * @deprecated 5.0.0 Use the {@see 'divi_visual_builder_rest_save_verification'} filter instead.
			 *
			 * @param bool $verification Whether to save the verification result.
			 */
			$verification = apply_filters(
				'et_fb_ajax_save_verification_result',
				$verification
			);

			/**
			 * Filter to modify the save verification result.
			 *
			 * @since ??
			 *
			 * @param bool $verification Whether to save the verification result.
			 */
			$save_verification_filtered = apply_filters(
				'divi_visual_builder_rest_save_post_save_verification',
				$verification
			);

			$return_rendered_content = $options['return_rendered_content'] ?? false;

			if ( $return_rendered_content && $should_persist_main_post ) {
				// Replace dynamic data in the content with the actual value.
				$normalized_content = DynamicData::get_processed_dynamic_data( $saved_post_content, $post_id, true );

				// Apply the_content filter to the content.
				$rendered_content = apply_filters( 'the_content', $normalized_content );
			} else {
				$rendered_content = $saved_post_content;
			}

			return self::response_success(
				[
					'post_status'       => $should_persist_main_post ? get_post_status( $update ) : $post_status,
					'save_verification' => $save_verification_filtered,
					'rendered_content'  => $rendered_content,
				]
			);
		}

		if ( is_wp_error( $update ) ) {
			return self::response_error( $update->get_error_code(), $update->get_error_message(), $update->get_error_data() );
		}

		return self::response_error( 'unknown_error', esc_html__( 'Unknown error.', 'et_builder_5' ) );
	}

	/**
	 * Update action arguments.
	 *
	 * Retrieves the arguments for the update action endpoint.
	 * These arguments are used in `register_rest_route()` to define the endpoint parameters.
	 *
	 * @since ??
	 *
	 * @return array  An associative array of arguments for the update action endpoint.
	 *
	 * @example:
	 * ```php
	 * $args = SyncToServer::update_args();
	 *
	 * // Returns an associative array of arguments for the update action endpoint.
	 * ```
	 */
	public static function update_args(): array {
		return [
			'post_id'              => [
				'required'          => true,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'get_post',
			],
			'post_status'          => [
				'default'           => 'draft',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'syncType'             => [
				'default'           => 'draft',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'content'              => [
				'default'           => [],
				'sanitize_callback' => [ __CLASS__, 'sanitize_content' ],
			],
			'pageSettingsByLayout' => [
				'default'           => [],
				'sanitize_callback' => [ __CLASS__, 'sanitize_page_settings_by_layout' ],
			],
			'off_canvas_data'      => [
				'default'           => '',
				'sanitize_callback' => [ PortabilityController::class, 'sanitize_json_param' ],
			],
			'layout_post_ids'      => [
				'default'           => [],
				'sanitize_callback' => [ __CLASS__, 'sanitize_layout_post_ids' ],
			],
			'mainLoopType'         => [
				'default'           => 'singular',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'mainLoopSettingsData' => [
				'default'           => [],
				'sanitize_callback' => [ __CLASS__, 'sanitize_main_loop_settings_data' ],
			],
		];
	}

	/**
	 * Update action permission for a post.
	 *
	 * Checks if the current user has permission to update the post with the given ID and status.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request {
	 *     The REST request object.
	 *
	 *     @type string $post_id     The ID of the post to check permission for.
	 *     @type string $post_status The status of the post to check permission for.
	 * }
	 *
	 * @return bool|WP_Error Returns `true` if the current user has permission, `WP_Error` object otherwise.
	 *
	 * @example:
	 * ```php
	 * $request = new WP_REST_Request();
	 * $request->set_param( 'post_id', $post_id );
	 * $request->set_param( 'post_status', $post_status );
	 * $result = SyncToServer::update_permission( $request );
	 * ```
	 */
	public static function update_permission( WP_REST_Request $request ) {
		$post_id         = $request->get_param( 'post_id' );
		$post_status     = $request->get_param( 'post_status' );
		$main_loop_type  = $request->get_param( 'mainLoopType' );
		$main_loop_type  = is_string( $main_loop_type ) ? $main_loop_type : 'singular';
		$layout_post_ids = $request->get_param( 'layout_post_ids' );
		$layout_post_ids = is_array( $layout_post_ids ) ? $layout_post_ids : [];
		$layout_post_ids = self::sanitize_layout_post_ids( $layout_post_ids );
		$layout_post_ids = self::_validate_and_normalize_layout_post_ids( $layout_post_ids, (int) $post_id, $main_loop_type );

		if ( is_wp_error( $layout_post_ids ) ) {
			return self::response_error_permission();
		}

		if ( ! et_fb_current_user_can_save( $post_id, $post_status ) ) {
			return self::response_error_permission();
		}

		foreach ( $layout_post_ids as $layout_post_id ) {
			$layout_post_id = absint( $layout_post_id );

			if ( 0 === $layout_post_id || (int) $post_id === $layout_post_id ) {
				continue;
			}

			$layout_post_status = get_post_status( $layout_post_id );
			if ( ! $layout_post_status ) {
				continue;
			}

			if ( ! et_fb_current_user_can_save( $layout_post_id, $layout_post_status ) ) {
				return self::response_error_permission();
			}
		}

		return true;
	}

	/**
	 * Validate and normalize requested layout IDs against current editing scope.
	 *
	 * @since ??
	 *
	 * @param array  $requested_layout_post_ids Requested layout IDs map.
	 * @param int    $main_post_id Main post ID.
	 * @param string $main_loop_type Main loop type.
	 *
	 * @return array|WP_Error
	 */
	private static function _validate_and_normalize_layout_post_ids( array $requested_layout_post_ids, int $main_post_id, string $main_loop_type = 'singular' ) {
		$allowed_layout_post_ids = self::_get_allowed_layout_post_ids( $main_post_id );
		$normalized              = [
			'postContent' => $main_post_id,
			'header'      => 0,
			'body'        => 0,
			'footer'      => 0,
		];

		foreach ( [ 'postContent', 'header', 'body', 'footer' ] as $layout ) {
			$requested_post_id = absint( $requested_layout_post_ids[ $layout ] ?? 0 );

			if ( 0 === $requested_post_id ) {
				continue;
			}

			if ( 'postContent' === $layout ) {
				if ( $requested_post_id !== $main_post_id ) {
					return new WP_Error(
						'rest_forbidden',
						esc_html__( 'Invalid layout save target for current context.', 'et_builder_5' ),
						[ 'status' => 403 ]
					);
				}
				$normalized[ $layout ] = $requested_post_id;
				continue;
			}

			$allowed_post_id = absint( $allowed_layout_post_ids[ $layout ] ?? 0 );
			if ( $requested_post_id !== $allowed_post_id ) {
				/*
				 * Non-singular saves can lack a reliable `post_id` context for Theme Builder
				 * assignment resolution (e.g. taxonomy/author archives). Keep strict matching
				 * whenever an allowed layout is resolved, but fall back to validating the
				 * requested layout post itself when no assigned ID is available.
				 */
				if (
					'singular' !== $main_loop_type
					&& self::_can_edit_theme_builder_layout_post_for_slot( $requested_post_id, $layout )
				) {
					$normalized[ $layout ] = $requested_post_id;
					continue;
				}

				return new WP_Error(
					'rest_forbidden',
					esc_html__( 'Invalid layout save target for current context.', 'et_builder_5' ),
					[ 'status' => 403 ]
				);
			}

			$normalized[ $layout ] = $requested_post_id;
		}

		return $normalized;
	}

	/**
	 * Validate that a layout post can be saved for a given Theme Builder slot.
	 *
	 * @since ??
	 *
	 * @param int    $layout_post_id Layout post ID.
	 * @param string $layout Theme Builder slot.
	 *
	 * @return bool
	 */
	private static function _can_edit_theme_builder_layout_post_for_slot( int $layout_post_id, string $layout ): bool {
		if ( 0 === $layout_post_id ) {
			return false;
		}

		$expected_post_type = self::_get_theme_builder_post_type_for_layout( $layout );
		if ( '' === $expected_post_type ) {
			return false;
		}

		$actual_post_type = get_post_type( $layout_post_id );
		if ( $actual_post_type !== $expected_post_type ) {
			return false;
		}

		return current_user_can( 'edit_post', $layout_post_id );
	}

	/**
	 * Resolve allowed Theme Builder layout post IDs for current editing context.
	 *
	 * @since ??
	 *
	 * @param int $main_post_id Main post ID.
	 *
	 * @return array{postContent:int,header:int,body:int,footer:int}
	 */
	private static function _get_allowed_layout_post_ids( int $main_post_id ): array {
		$allowed = [
			'postContent' => $main_post_id,
			'header'      => 0,
			'body'        => 0,
			'footer'      => 0,
		];

		$main_post_type = get_post_type( $main_post_id );
		if ( is_string( $main_post_type ) && et_theme_builder_is_layout_post_type( $main_post_type ) ) {
			$layout = '';

			if ( 'et_header_layout' === $main_post_type ) {
				$layout = 'header';
			} elseif ( 'et_body_layout' === $main_post_type ) {
				$layout = 'body';
			} elseif ( 'et_footer_layout' === $main_post_type ) {
				$layout = 'footer';
			}

			if ( '' !== $layout ) {
				$allowed[ $layout ] = $main_post_id;
			}

			return $allowed;
		}

		$theme_builder_layouts = et_theme_builder_get_template_layouts();
		if ( empty( $theme_builder_layouts ) && 0 < $main_post_id ) {
			$tb_request = \ET_Theme_Builder_Request::from_post( $main_post_id );
			if ( $tb_request ) {
				$theme_builder_layouts = et_theme_builder_get_template_layouts( $tb_request );
			}
		}

		$layout_keys = [
			'header' => ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE,
			'body'   => ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE,
			'footer' => ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE,
		];

		foreach ( $layout_keys as $layout => $layout_key ) {
			$layout_data = $theme_builder_layouts[ $layout_key ] ?? [];

			if ( empty( $layout_data['enabled'] ) || empty( $layout_data['override'] ) ) {
				continue;
			}

			$layout_post_id = absint( $layout_data['id'] ?? 0 );
			if ( 0 === $layout_post_id ) {
				continue;
			}

			$expected_post_type = self::_get_theme_builder_post_type_for_layout( $layout );
			if ( get_post_type( $layout_post_id ) !== $expected_post_type ) {
				continue;
			}

			$allowed[ $layout ] = $layout_post_id;
		}

		return $allowed;
	}

	/**
	 * Get expected Theme Builder post type for layout area.
	 *
	 * @since ??
	 *
	 * @param string $layout Layout area.
	 *
	 * @return string
	 */
	private static function _get_theme_builder_post_type_for_layout( string $layout ): string {
		if ( 'header' === $layout ) {
			return 'et_header_layout';
		}

		if ( 'body' === $layout ) {
			return 'et_body_layout';
		}

		if ( 'footer' === $layout ) {
			return 'et_footer_layout';
		}

		return '';
	}

	/**
	 * Resolve expected non-singular owner context from current request.
	 *
	 * @since ??
	 *
	 * @param string $main_loop_type Main loop type.
	 *
	 * @return array
	 */
	private static function _get_expected_main_loop_owner_context( string $main_loop_type ): array {
		$expected_context = [];

		if ( in_array( $main_loop_type, [ 'category', 'tag', 'taxonomy' ], true ) ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$expected_context['termId']   = (int) $term->term_id;
				$expected_context['taxonomy'] = sanitize_key( $term->taxonomy );
			}
		}

		if ( 'author' === $main_loop_type ) {
			$author = get_queried_object();
			if ( $author instanceof \WP_User ) {
				$expected_context['authorId'] = (int) $author->ID;
			}
		}

		return $expected_context;
	}

	/**
	 * Sanitize the content array by preparing each content item for database storage.
	 *
	 * This function takes an array of contents and loops through the array and calls the
	 * `SavingUtility::prepare_content_for_db()` to sanitize each content item.
	 * The sanitized contents are then stored in a new array and returned.
	 *
	 * @since ??
	 *
	 * @param array $contents The array of contents to be sanitized.
	 *
	 * @return array The sanitized contents array
	 *
	 * @example:
	 * ```php
	 * $contents = [
	 *    'location1' => '<p>Content 1</p>',
	 *    'location2' => '<script>alert("Content 2")</script>'
	 * ];
	 *
	 * $sanitizedContents = SyncToServer::sanitize_content($contents);
	 * // Returns the sanitized contents array
	 * ```
	 */
	public static function sanitize_content( array $contents ): array {
		$sanitized = [];

		foreach ( $contents as $location => $content ) {
			$sanitized[ $location ] = SavingUtility::prepare_content_for_db( $content );
		}

		return $sanitized;
	}

	/**
	 * Sanitize page settings grouped by layout.
	 *
	 * @since ??
	 *
	 * @param array $page_settings_by_layout The page settings grouped by layout.
	 *
	 * @return array
	 */
	public static function sanitize_page_settings_by_layout( array $page_settings_by_layout ): array {
		$sanitized = [];

		foreach ( [ 'postContent', 'header', 'body', 'footer' ] as $layout ) {
			$layout_settings = $page_settings_by_layout[ $layout ] ?? null;

			if ( ! is_array( $layout_settings ) ) {
				continue;
			}

			$sanitized[ $layout ] = SavingUtility::sanitize_page_settings( $layout_settings );
		}

		return $sanitized;
	}

	/**
	 * Sanitize the layout post IDs map.
	 *
	 * @since ??
	 *
	 * @param array $layout_post_ids Layout post IDs map.
	 *
	 * @return array
	 */
	public static function sanitize_layout_post_ids( array $layout_post_ids ): array {
		return [
			'postContent' => absint( $layout_post_ids['postContent'] ?? 0 ),
			'header'      => absint( $layout_post_ids['header'] ?? 0 ),
			'body'        => absint( $layout_post_ids['body'] ?? 0 ),
			'footer'      => absint( $layout_post_ids['footer'] ?? 0 ),
		];
	}

	/**
	 * Sanitize mainLoopSettingsData parameter.
	 *
	 * Contains identifiers and metadata for the current non-singular context
	 * (e.g. term ID, taxonomy slug, author ID).
	 *
	 * @since ??
	 *
	 * @param mixed $data Raw mainLoopSettingsData from the request.
	 *
	 * @return array Sanitized data.
	 */
	public static function sanitize_main_loop_settings_data( $data ): array {
		/*
		 * Accept both array and string payloads.
		 *
		 * WordPress REST argument sanitization runs before the controller callback executes and will
		 * call this sanitizer with whatever was provided by the client. In Visual Builder saves,
		 * `mainLoopSettingsData` may arrive as:
		 *
		 * - an array (expected for non-singular pages where the UI edits term/author fields), or
		 * - a JSON string (when serialized in a querystring or transport layer), or
		 * - an empty string (common on singular pages where there is no non-singular context).
		 *
		 * We normalize these cases to a consistent array shape to prevent fatal type errors and to
		 * keep save requests resilient to client-side serialization differences.
		 */
		if ( is_string( $data ) ) {
			if ( '' === $data ) {
				return [];
			}

			$decoded = json_decode( $data, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				$decoded = json_decode( stripslashes( $data ), true );
			}
			$data = is_array( $decoded ) ? $decoded : [];
		}

		if ( ! is_array( $data ) ) {
			return [];
		}

		$sanitized = [];

		$allowed = [
			'termId'          => 'absint',
			'taxonomy'        => 'sanitize_key',
			'termName'        => 'sanitize_text_field',
			'termSlug'        => 'sanitize_title',
			'termDescription' => 'wp_kses_post',
			'pageInfo'        => 'sanitize_text_field',
			'postType'        => 'sanitize_key',
			'canEdit'         => 'rest_sanitize_boolean',
		];

		foreach ( $allowed as $key => $sanitizer ) {
			if ( array_key_exists( $key, $data ) ) {
				$sanitized[ $key ] = call_user_func( $sanitizer, $data[ $key ] );
			}
		}

		return $sanitized;
	}

	/**
	 * Save page settings for each layout.
	 *
	 * @since ??
	 *
	 * @param array $page_settings_by_layout Page settings grouped by layout.
	 * @param array $layout_post_ids Layout post IDs grouped by layout.
	 * @param int   $main_post_id Main post ID.
	 *
	 * @return void
	 */
	private static function _save_page_settings_by_layout( array $page_settings_by_layout, array $layout_post_ids, int $main_post_id ): void {
		foreach ( $page_settings_by_layout as $layout => $layout_page_settings ) {
			if ( ! is_array( $layout_page_settings ) ) {
				continue;
			}

			$target_post_id = absint( $layout_post_ids[ $layout ] ?? 0 );
			if ( 0 === $target_post_id && 'postContent' === $layout ) {
				$target_post_id = $main_post_id;
			}

			if ( 0 === $target_post_id ) {
				continue;
			}

			if ( 'postContent' !== $layout ) {
				$existing_post      = get_post( $target_post_id );
				$layout_post_update = [
					'ID' => $target_post_id,
				];

				if ( isset( $layout_page_settings['postTitle'] ) ) {
					$incoming_title = sanitize_text_field( $layout_page_settings['postTitle'] );
					$existing_title = $existing_post instanceof \WP_Post ? (string) $existing_post->post_title : null;

					if ( null === $existing_title || $incoming_title !== $existing_title ) {
						$layout_post_update['post_title'] = $incoming_title;
					}
				}

				if ( isset( $layout_page_settings['postExcerpt'] ) ) {
					$incoming_excerpt = wp_kses_post( $layout_page_settings['postExcerpt'] );
					$existing_excerpt = $existing_post instanceof \WP_Post ? (string) $existing_post->post_excerpt : null;

					if ( null === $existing_excerpt || $incoming_excerpt !== $existing_excerpt ) {
						$layout_post_update['post_excerpt'] = $incoming_excerpt;
					}
				}

				if ( 1 < count( $layout_post_update ) ) {
					wp_update_post( $layout_post_update );
				}
			}

			SavingUtility::save_page_settings( $layout_page_settings, $target_post_id );
		}
	}

	/**
	 * Update a layout post's content.
	 *
	 * @since ??
	 *
	 * @param int    $post_id      Layout post ID.
	 * @param string $post_content Layout post content.
	 *
	 * @return int|WP_Error
	 */
	private static function _update_layout_post_content( int $post_id, string $post_content ) {
		if ( 0 === $post_id || '' === $post_content ) {
			return 0;
		}

		$existing_post = get_post( $post_id );

		if ( $existing_post instanceof \WP_Post ) {
			$existing_content       = wp_unslash( $existing_post->post_content ?? '' );
			$normalized_new_content = wp_unslash( $post_content );

			if ( $normalized_new_content === $existing_content ) {
				return 0;
			}
		}

		return wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => wp_slash( $post_content ),
			],
			true
		);
	}

	/**
	 * Determine whether layout content has meaningful data.
	 *
	 * Treat empty strings and placeholder-only wrappers as empty so accidental
	 * empty payloads cannot overwrite assigned Theme Builder layouts.
	 *
	 * @since ??
	 *
	 * @param string $content Layout content.
	 *
	 * @return bool
	 */
	private static function _has_meaningful_layout_content( string $content ): bool {
		$normalized_content = trim( $content );

		if ( '' === $normalized_content ) {
			return false;
		}

		$normalized_content = preg_replace( '/^\s*<!-- wp:divi\/placeholder -->\s*/', '', $normalized_content );
		$normalized_content = preg_replace( '/\s*<!-- \/wp:divi\/placeholder -->\s*$/', '', (string) $normalized_content );

		return '' !== trim( $normalized_content );
	}


	/**
	 * Verify that post content matches after Divi save-time `wp_insert_post_data` transforms.
	 *
	 * Replays the same ordered sanitization as {@see Security::apply_post_content_save_transforms()}
	 * on the submitted content and compares the result to what was persisted after `wp_update_post()`.
	 *
	 * @since ??
	 *
	 * @param string $original_content The original post content sent from frontend.
	 * @param string $saved_content    The content that was actually saved to database.
	 *
	 * @return bool True if contents match after accounting for sanitization, false otherwise.
	 */
	private static function _verify_post_content_matches_after_sanitization( $original_content, $saved_content ): bool {
		if ( $original_content === $saved_content ) {
			return true;
		}

		if ( empty( $original_content ) ) {
			return $original_content === $saved_content;
		}

		$post_data = [
			'post_content' => wp_slash( $original_content ),
		];

		$replay_data = Security::apply_post_content_save_transforms( $post_data );

		return wp_unslash( $replay_data['post_content'] ) === $saved_content;
	}

	/**
	 * Normalize off-canvas data to array format.
	 *
	 * Decodes JSON string if needed, ensuring the caller always passes
	 * data in a single expected format (array) to downstream handlers.
	 *
	 * @since ??
	 *
	 * @param mixed $off_canvas_data Off-canvas data (array or JSON string).
	 *
	 * @return array|null Normalized array data, or null if invalid.
	 */
	private static function _normalize_off_canvas_data( $off_canvas_data ) {
		// If already an array, return as-is.
		if ( is_array( $off_canvas_data ) ) {
			return $off_canvas_data;
		}

		// If not a string, return null.
		if ( ! is_string( $off_canvas_data ) ) {
			return null;
		}

		// Try to decode JSON string.
		$decoded = json_decode( $off_canvas_data, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			// Try with stripslashes in case of escaped JSON.
			$decoded = json_decode( stripslashes( $off_canvas_data ), true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return null;
			}
		}

		// Ensure decoded result is an array.
		if ( ! is_array( $decoded ) ) {
			return null;
		}

		return $decoded;
	}
}
