<?php
/**
 * REST: AiAgentCommandsController class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\REST\AiAgent;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\VisualBuilder\AiAgent\AiAgentCommands;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for AI agent commands.
 *
 * @since ??
 */
class AiAgentCommandsController extends RESTController {
	/**
	 * Fallback title for untitled commands.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	private static function _get_untitled_command_title(): string {
		return esc_html__( 'Untitled Command', 'et_builder_5' );
	}

	/**
	 * Get AI command by id and ensure its post type matches.
	 *
	 * @since ??
	 *
	 * @param int $command_id Command post id.
	 *
	 * @return WP_Post|null
	 */
	private static function _get_command_post( int $command_id ): ?WP_Post {
		$post = get_post( $command_id );

		if ( ! ( $post instanceof WP_Post ) || AiAgentCommands::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	/**
	 * Build payload for command creates/updates.
	 *
	 * @since ??
	 *
	 * @param string $title   Command title.
	 * @param string $command Command content.
	 *
	 * @return array<string,string>
	 */
	private static function _get_command_post_payload( string $title, string $command ): array {
		return [
			'post_title'   => '' !== $title ? $title : self::_get_untitled_command_title(),
			'post_content' => $command,
		];
	}

	/**
	 * Build not-found error response.
	 *
	 * @since ??
	 *
	 * @return WP_Error
	 */
	private static function _command_not_found_error(): WP_Error {
		return new WP_Error(
			'ai_command_not_found',
			esc_html__( 'AI command not found.', 'et_builder_5' ),
			[ 'status' => 404 ]
		);
	}

	/**
	 * Build a generic operation-failed error response.
	 *
	 * @since ??
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 *
	 * @return WP_Error
	 */
	private static function _command_operation_failed_error( string $code, string $message ): WP_Error {
		return new WP_Error(
			$code,
			$message,
			[ 'status' => 500 ]
		);
	}

	/**
	 * Shared route args for a command id argument.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	private static function _command_id_args(): array {
		return [
			'commandId' => [
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
		];
	}

	/**
	 * Build serialized command payload from post.
	 *
	 * @since ??
	 *
	 * @param WP_Post $post Command post.
	 *
	 * @return array<string,mixed>
	 */
	private static function _serialize_command( WP_Post $post ): array {
		return [
			'id'      => (string) $post->ID,
			'title'   => (string) $post->post_title,
			'command' => (string) $post->post_content,
		];
	}

	/**
	 * Get AI commands authored by the current user.
	 *
	 * @since ??
	 *
	 * @return WP_REST_Response
	 */
	public static function read(): WP_REST_Response {
		$commands = get_posts(
			[
				'post_type'              => AiAgentCommands::POST_TYPE,
				'post_status'            => [ 'publish' ],
				'author'                 => get_current_user_id(),
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'numberposts'            => -1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'suppress_filters'       => true,
			]
		);

		return self::response_success(
			[
				'commands' => array_map( [ __CLASS__, '_serialize_command' ], $commands ),
			]
		);
	}

	/**
	 * Create AI command.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create( WP_REST_Request $request ) {
		$title   = self::sanitize_text( $request->get_param( 'title' ) );
		$command = self::sanitize_textarea( $request->get_param( 'command' ) );

		$post_id = wp_insert_post(
			array_merge(
				[
					'post_type'   => AiAgentCommands::POST_TYPE,
					'post_status' => 'publish',
				],
				self::_get_command_post_payload( $title, $command )
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post = self::_get_command_post( $post_id );
		if ( null === $post ) {
			return self::_command_operation_failed_error(
				'ai_command_create_failed',
				esc_html__( 'Failed to create AI command.', 'et_builder_5' )
			);
		}

		return self::response_success(
			[
				'command' => self::_serialize_command( $post ),
			]
		);
	}

	/**
	 * Update AI command.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update( WP_REST_Request $request ) {
		$command_id = absint( $request->get_param( 'commandId' ) );
		$post       = self::_get_command_post( $command_id );

		if ( null === $post ) {
			return self::_command_not_found_error();
		}

		if ( ! current_user_can( 'edit_post', $command_id ) ) {
			return self::response_error_permission();
		}

		$title   = self::sanitize_text( $request->get_param( 'title' ) );
		$command = self::sanitize_textarea( $request->get_param( 'command' ) );

		$updated = wp_update_post(
			array_merge(
				[
					'ID'        => $command_id,
					'post_type' => AiAgentCommands::POST_TYPE,
				],
				self::_get_command_post_payload( $title, $command )
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		$updated_post = self::_get_command_post( $command_id );
		if ( null === $updated_post ) {
			return self::_command_operation_failed_error(
				'ai_command_update_failed',
				esc_html__( 'Failed to update AI command.', 'et_builder_5' )
			);
		}

		return self::response_success(
			[
				'command' => self::_serialize_command( $updated_post ),
			]
		);
	}

	/**
	 * Delete AI command.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete( WP_REST_Request $request ) {
		$command_id = absint( $request->get_param( 'commandId' ) );
		$post       = self::_get_command_post( $command_id );

		if ( null === $post ) {
			return self::_command_not_found_error();
		}

		if ( ! current_user_can( 'delete_post', $command_id ) ) {
			return self::response_error_permission();
		}

		$deleted = wp_delete_post( $command_id, true );
		if ( ! $deleted ) {
			return self::_command_operation_failed_error(
				'ai_command_delete_failed',
				esc_html__( 'Failed to delete AI command.', 'et_builder_5' )
			);
		}

		return self::response_success(
			[
				'commandId' => (string) $command_id,
			]
		);
	}

	/**
	 * Duplicate AI command.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function duplicate( WP_REST_Request $request ) {
		$command_id = absint( $request->get_param( 'commandId' ) );
		$post       = self::_get_command_post( $command_id );

		if ( null === $post ) {
			return self::_command_not_found_error();
		}

		if ( ! current_user_can( 'edit_post', $command_id ) ) {
			return self::response_error_permission();
		}

		$duplicated_post_id = wp_insert_post(
			[
				'post_type'    => AiAgentCommands::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => sprintf(
					/* translators: %s: Command title. */
					__( '%s (Copy)', 'et_builder_5' ),
					(string) $post->post_title
				),
				'post_content' => (string) $post->post_content,
			],
			true
		);

		if ( is_wp_error( $duplicated_post_id ) ) {
			return $duplicated_post_id;
		}

		$duplicated_post = self::_get_command_post( $duplicated_post_id );
		if ( null === $duplicated_post ) {
			return self::_command_operation_failed_error(
				'ai_command_duplicate_failed',
				esc_html__( 'Failed to duplicate AI command.', 'et_builder_5' )
			);
		}

		return self::response_success(
			[
				'command' => self::_serialize_command( $duplicated_post ),
			]
		);
	}

	/**
	 * Read route args.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function read_args(): array {
		return [];
	}

	/**
	 * Create route args.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function create_args(): array {
		return self::_write_args();
	}

	/**
	 * Update route args.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function update_args(): array {
		return array_merge(
			self::_command_id_args(),
			self::_write_args()
		);
	}

	/**
	 * Delete route args.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function delete_args(): array {
		return self::_command_id_args();
	}

	/**
	 * Duplicate route args.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function duplicate_args(): array {
		return self::_command_id_args();
	}

	/**
	 * Read permission callback.
	 *
	 * @since ??
	 *
	 * @return bool|WP_Error
	 */
	public static function read_permission() {
		return self::_commands_permission();
	}

	/**
	 * Create permission callback.
	 *
	 * @since ??
	 *
	 * @return bool|WP_Error
	 */
	public static function create_permission() {
		return self::_commands_permission();
	}

	/**
	 * Update permission callback.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return bool|WP_Error
	 */
	public static function update_permission( WP_REST_Request $request ) {
		return self::_mutate_command_permission( $request, 'edit_post' );
	}

	/**
	 * Delete permission callback.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return bool|WP_Error
	 */
	public static function delete_permission( WP_REST_Request $request ) {
		return self::_mutate_command_permission( $request, 'delete_post' );
	}

	/**
	 * Duplicate permission callback.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return bool|WP_Error
	 */
	public static function duplicate_permission( WP_REST_Request $request ) {
		return self::_mutate_command_permission( $request, 'edit_post' );
	}

	/**
	 * Shared args for create/update writes.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	private static function _write_args(): array {
		return [
			'title'   => [
				'default'           => '',
				'type'              => 'string',
				'sanitize_callback' => [ __CLASS__, 'sanitize_text' ],
			],
			'command' => [
				'default'           => '',
				'type'              => 'string',
				'sanitize_callback' => [ __CLASS__, 'sanitize_textarea' ],
			],
		];
	}

	/**
	 * Shared permission callback.
	 *
	 * @since ??
	 *
	 * @return bool|WP_Error
	 */
	private static function _commands_permission() {
		if ( ! UserRole::can_current_user_use_visual_builder() || ! current_user_can( 'edit_posts' ) ) {
			return self::response_error_permission();
		}

		return true;
	}

	/**
	 * Permission callback for mutating routes that target a specific command.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request Request object.
	 * @param string          $cap     Object capability to check (`edit_post` or `delete_post`).
	 *
	 * @return bool|WP_Error
	 */
	private static function _mutate_command_permission( WP_REST_Request $request, string $cap ) {
		$global_permission = self::_commands_permission();
		if ( true !== $global_permission ) {
			return $global_permission;
		}

		$command_id = absint( $request->get_param( 'commandId' ) );
		if ( 0 >= $command_id ) {
			// Let the endpoint callback return `ai_command_not_found` when the command ID is missing or invalid.
			return true;
		}

		$post = self::_get_command_post( $command_id );
		if ( null === $post ) {
			// Let the endpoint callback return `ai_command_not_found` when the command doesn't exist.
			return true;
		}

		if ( ! current_user_can( $cap, $command_id ) ) {
			return self::response_error_permission();
		}

		return true;
	}

	/**
	 * Sanitize regular text.
	 *
	 * @since ??
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	public static function sanitize_text( $value ): string {
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Sanitize textarea text.
	 *
	 * @since ??
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	public static function sanitize_textarea( $value ): string {
		return sanitize_textarea_field( (string) $value );
	}
}
