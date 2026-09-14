<?php
/**
 * REST: AiAgentRulesController class.
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
use ET\Builder\VisualBuilder\AiAgent\AiAgentApprovalBinding;
use ET\Builder\VisualBuilder\AiAgent\AiAgentApprovalTokens;
use ET\Builder\VisualBuilder\AiAgent\AiAgentRules;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for AI agent rules.
 *
 * @since ??
 */
class AiAgentRulesController extends RESTController {
	/**
	 * Fallback title for untitled rules.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	private static function _get_untitled_rule_title(): string {
		return esc_html__( 'Untitled Rule', 'et_builder_5' );
	}

	/**
	 * Get AI rule by id and ensure its post type matches.
	 *
	 * @since ??
	 *
	 * @param int $rule_id Rule post id.
	 *
	 * @return WP_Post|null
	 */
	private static function _get_rule_post( int $rule_id ): ?WP_Post {
		$post = get_post( $rule_id );

		if ( ! ( $post instanceof WP_Post ) || AiAgentRules::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	/**
	 * Build payload for rule creates/updates.
	 *
	 * @since ??
	 *
	 * @param string $title Rule title.
	 * @param string $rule  Rule body.
	 *
	 * @return array<string,string>
	 */
	private static function _get_rule_post_payload( string $title, string $rule ): array {
		return [
			'post_title'   => '' !== $title ? $title : self::_get_untitled_rule_title(),
			'post_content' => $rule,
		];
	}

	/**
	 * Persist rule meta fields.
	 *
	 * @since ??
	 *
	 * @param int    $rule_id      Rule post id.
	 * @param string $when_to_use  Guidance text.
	 * @param bool   $always_use   Always-on toggle.
	 *
	 * @return void
	 */
	private static function _update_rule_meta( int $rule_id, string $when_to_use, bool $always_use ): void {
		update_post_meta( $rule_id, AiAgentRules::META_WHEN_TO_USE, $when_to_use );
		update_post_meta( $rule_id, AiAgentRules::META_ALWAYS_USE, $always_use ? '1' : '0' );
	}

	/**
	 * Build not-found error response.
	 *
	 * @since ??
	 *
	 * @return WP_Error
	 */
	private static function _rule_not_found_error(): WP_Error {
		return new WP_Error(
			'ai_rule_not_found',
			esc_html__( 'AI rule not found.', 'et_builder_5' ),
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
	private static function _rule_operation_failed_error( string $code, string $message ): WP_Error {
		return new WP_Error(
			$code,
			$message,
			[ 'status' => 500 ]
		);
	}

	/**
	 * Shared route args for a rule id argument.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	private static function _rule_id_args(): array {
		return [
			'ruleId' => [
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
		];
	}

	/**
	 * Build serialized rule payload from post.
	 *
	 * @since ??
	 *
	 * @param WP_Post $post Rule post.
	 *
	 * @return array<string,mixed>
	 */
	private static function _serialize_rule( WP_Post $post ): array {
		$when_to_use = get_post_meta( $post->ID, AiAgentRules::META_WHEN_TO_USE, true );
		$always_use  = get_post_meta( $post->ID, AiAgentRules::META_ALWAYS_USE, true );

		return [
			'id'        => (string) $post->ID,
			'title'     => (string) $post->post_title,
			'rule'      => (string) $post->post_content,
			'whenToUse' => is_string( $when_to_use ) ? $when_to_use : '',
			'alwaysUse' => '1' === (string) $always_use,
		];
	}

	/**
	 * Get AI rules authored by the current user.
	 *
	 * @since ??
	 *
	 * @return WP_REST_Response
	 */
	public static function read(): WP_REST_Response {
		$rules = get_posts(
			[
				'post_type'              => AiAgentRules::POST_TYPE,
				'post_status'            => [ 'publish' ],
				'author'                 => get_current_user_id(),
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'numberposts'            => -1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
				'suppress_filters'       => true,
			]
		);

		return self::response_success(
			[
				'rules' => array_map( [ __CLASS__, '_serialize_rule' ], $rules ),
			]
		);
	}

	/**
	 * Create AI rule.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create( WP_REST_Request $request ) {
		$title       = self::sanitize_text( $request->get_param( 'title' ) );
		$rule        = self::sanitize_textarea( $request->get_param( 'rule' ) );
		$when_to_use = self::sanitize_textarea( $request->get_param( 'whenToUse' ) );
		$always_use  = rest_sanitize_boolean( $request->get_param( 'alwaysUse' ) );

		if ( $always_use ) {
			$always_on_permission = self::_always_on_write_permission();

			if ( true !== $always_on_permission ) {
				return $always_on_permission;
			}
		}

		$post_id = wp_insert_post(
			array_merge(
				[
					'post_type'   => AiAgentRules::POST_TYPE,
					'post_status' => 'publish',
				],
				self::_get_rule_post_payload( $title, $rule )
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		self::_update_rule_meta( $post_id, $when_to_use, $always_use );

		$post = self::_get_rule_post( $post_id );
		if ( null === $post ) {
			return self::_rule_operation_failed_error(
				'ai_rule_create_failed',
				esc_html__( 'Failed to create AI rule.', 'et_builder_5' )
			);
		}

		return self::response_success(
			[
				'rule' => self::_serialize_rule( $post ),
			]
		);
	}

	/**
	 * Update AI rule.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update( WP_REST_Request $request ) {
		$rule_id = absint( $request->get_param( 'ruleId' ) );
		$post    = self::_get_rule_post( $rule_id );

		if ( null === $post ) {
			return self::_rule_not_found_error();
		}

		if ( ! current_user_can( 'edit_post', $rule_id ) || ! self::_current_user_owns_rule( $post ) ) {
			return self::response_error_permission();
		}

		$title               = self::sanitize_text( $request->get_param( 'title' ) );
		$rule                = self::sanitize_textarea( $request->get_param( 'rule' ) );
		$when_to_use         = self::sanitize_textarea( $request->get_param( 'whenToUse' ) );
		$always_use          = rest_sanitize_boolean( $request->get_param( 'alwaysUse' ) );
		$existing_always_use = '1' === (string) get_post_meta( $rule_id, AiAgentRules::META_ALWAYS_USE, true );

		if ( $always_use || $existing_always_use ) {
			$always_on_permission = self::_always_on_write_permission();

			if ( true !== $always_on_permission ) {
				return $always_on_permission;
			}
		}

		$updated = wp_update_post(
			array_merge(
				[
					'ID'        => $rule_id,
					'post_type' => AiAgentRules::POST_TYPE,
				],
				self::_get_rule_post_payload( $title, $rule )
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		self::_update_rule_meta( $rule_id, $when_to_use, $always_use );

		$updated_post = self::_get_rule_post( $rule_id );
		if ( null === $updated_post ) {
			return self::_rule_operation_failed_error(
				'ai_rule_update_failed',
				esc_html__( 'Failed to update AI rule.', 'et_builder_5' )
			);
		}

		return self::response_success(
			[
				'rule' => self::_serialize_rule( $updated_post ),
			]
		);
	}

	/**
	 * Delete AI rule.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete( WP_REST_Request $request ) {
		$rule_id = absint( AiAgentApprovalBinding::resolve_delete_rule_id( $request->get_param( 'ruleId' ) ) );
		$post    = self::_get_rule_post( $rule_id );

		if ( null === $post ) {
			return self::_rule_not_found_error();
		}

		if ( ! current_user_can( 'delete_post', $rule_id ) || ! self::_current_user_owns_rule( $post ) ) {
			return self::response_error_permission();
		}

		if ( '1' === (string) get_post_meta( $rule_id, AiAgentRules::META_ALWAYS_USE, true ) ) {
			$always_on_permission = self::_always_on_write_permission();

			if ( true !== $always_on_permission ) {
				return $always_on_permission;
			}
		}

		$deleted = wp_delete_post( $rule_id, true );
		if ( ! $deleted ) {
			return self::_rule_operation_failed_error(
				'ai_rule_delete_failed',
				esc_html__( 'Failed to delete AI rule.', 'et_builder_5' )
			);
		}

		return self::response_success(
			[
				'ruleId' => (string) $rule_id,
			]
		);
	}

	/**
	 * Duplicate AI rule.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function duplicate( WP_REST_Request $request ) {
		$rule_id = absint( $request->get_param( 'ruleId' ) );
		$post    = self::_get_rule_post( $rule_id );

		if ( null === $post ) {
			return self::_rule_not_found_error();
		}

		if ( ! current_user_can( 'edit_post', $rule_id ) || ! self::_current_user_owns_rule( $post ) ) {
			return self::response_error_permission();
		}

		$when_to_use = get_post_meta( $rule_id, AiAgentRules::META_WHEN_TO_USE, true );
		$always_use  = get_post_meta( $rule_id, AiAgentRules::META_ALWAYS_USE, true );

		if ( '1' === (string) $always_use ) {
			$always_on_permission = self::_always_on_write_permission();

			if ( true !== $always_on_permission ) {
				return $always_on_permission;
			}
		}

		$duplicated_post_id = wp_insert_post(
			[
				'post_type'    => AiAgentRules::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => sprintf(
					/* translators: %s: Rule title. */
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

		self::_update_rule_meta(
			$duplicated_post_id,
			is_string( $when_to_use ) ? $when_to_use : '',
			'1' === (string) $always_use
		);

		$duplicated_post = self::_get_rule_post( $duplicated_post_id );
		if ( null === $duplicated_post ) {
			return self::_rule_operation_failed_error(
				'ai_rule_duplicate_failed',
				esc_html__( 'Failed to duplicate AI rule.', 'et_builder_5' )
			);
		}

		return self::response_success(
			[
				'rule' => self::_serialize_rule( $duplicated_post ),
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
			self::_rule_id_args(),
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
		return [
			'ruleId' => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Duplicate route args.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function duplicate_args(): array {
		return self::_rule_id_args();
	}

	/**
	 * Read permission callback.
	 *
	 * @since ??
	 *
	 * @return bool|WP_Error
	 */
	public static function read_permission() {
		return self::_rules_permission();
	}

	/**
	 * Create permission callback.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return bool|WP_Error
	 */
	public static function create_permission( WP_REST_Request $request ) {
		return AiAgentApprovalTokens::authorize_after_caps(
			$request,
			'add_rule',
			$request->get_params(),
			self::_rules_permission()
		);
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
		return self::_mutate_rule_permission( $request, 'edit_post' );
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
		return AiAgentApprovalTokens::authorize_after_caps(
			$request,
			'delete_rule',
			$request->get_params(),
			self::_mutate_rule_permission( $request, 'delete_post' )
		);
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
		return self::_mutate_rule_permission( $request, 'edit_post' );
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
			'title'     => [
				'default'           => '',
				'type'              => 'string',
				'sanitize_callback' => [ __CLASS__, 'sanitize_text' ],
			],
			'rule'      => [
				'default'           => '',
				'type'              => 'string',
				'sanitize_callback' => [ __CLASS__, 'sanitize_textarea' ],
			],
			'whenToUse' => [
				'default'           => '',
				'type'              => 'string',
				'sanitize_callback' => [ __CLASS__, 'sanitize_textarea' ],
			],
			'alwaysUse' => [
				'default'           => false,
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
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
	private static function _rules_permission() {
		if ( ! UserRole::can_current_user_use_visual_builder() || ! current_user_can( 'edit_posts' ) ) {
			return self::response_error_permission();
		}

		return true;
	}

	/**
	 * Permission callback for mutating routes that target a specific rule.
	 *
	 * Requires Visual Builder + `edit_posts`, the object cap (`edit_post` /
	 * `delete_post`), and that the current user authored the rule. Object caps
	 * alone are not enough: this CPT uses `capability_type => 'post'`, so
	 * `edit_others_posts` would otherwise allow cross-user writes.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request Request object.
	 * @param string          $cap     Object capability to check (`edit_post` or `delete_post`).
	 *
	 * @return bool|WP_Error
	 */
	private static function _mutate_rule_permission( WP_REST_Request $request, string $cap ) {
		$global_permission = self::_rules_permission();
		if ( true !== $global_permission ) {
			return $global_permission;
		}

		$rule_id = absint( AiAgentApprovalBinding::resolve_delete_rule_id( $request->get_param( 'ruleId' ) ) );
		if ( 0 >= $rule_id ) {
			// Let the endpoint callback return `ai_rule_not_found` when the rule ID is missing or invalid.
			return true;
		}

		$post = self::_get_rule_post( $rule_id );
		if ( null === $post ) {
			// Let the endpoint callback return `ai_rule_not_found` when the rule doesn't exist.
			return true;
		}

		if ( ! current_user_can( $cap, $rule_id ) || ! self::_current_user_owns_rule( $post ) ) {
			return self::response_error_permission();
		}

		return true;
	}

	/**
	 * Whether the current user authored the rule.
	 *
	 * Mutate routes require ownership in addition to `edit_post` / `delete_post`.
	 * Object caps on this CPT inherit `edit_others_posts`, which must not authorize
	 * cross-user writes of personal agent policy.
	 *
	 * @since ??
	 *
	 * @param WP_Post $post Rule post.
	 *
	 * @return bool
	 */
	private static function _current_user_owns_rule( WP_Post $post ): bool {
		return (int) $post->post_author === (int) get_current_user_id();
	}

	/**
	 * Permission check for writes that create, copy, mutate, or delete an always-on rule.
	 *
	 * Always-on rules are site-wide agent policy and require `manage_options`.
	 *
	 * @since ??
	 *
	 * @return bool|WP_Error
	 */
	private static function _always_on_write_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
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
