<?php
/**
 * REST: AiAgentPreferencesController class.
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
use WP_REST_Request;

/**
 * REST controller for AI agent model preferences.
 *
 * @since ??
 */
class AiAgentPreferencesController extends RESTController {
	/**
	 * User meta key for AI model preferences.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private const MODEL_PREFERENCES_META_KEY = 'et_divi_ai_agent_model_preferences';

	/**
	 * Read model preferences for the current user.
	 *
	 * @since ??
	 *
	 * @return WP_REST_Response
	 */
	public static function read() {
		$user_id                     = get_current_user_id();
		$has_stored_preferences      = false;
		$stored_preferences          = [];
		$normalized_model_preference = self::_get_default_preferences();

		if ( 0 < $user_id ) {
			$has_stored_preferences = metadata_exists( 'user', $user_id, self::MODEL_PREFERENCES_META_KEY );
			$stored_preferences     = get_user_meta( $user_id, self::MODEL_PREFERENCES_META_KEY, true );

			if ( is_array( $stored_preferences ) ) {
				$normalized_model_preference = self::_sanitize_preferences( $stored_preferences );
			}
		}

		return self::response_success(
			[
				'preferences'          => self::_normalize_preferences_for_response( $normalized_model_preference ),
				'hasStoredPreferences' => $has_stored_preferences,
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
	 * Read permission callback.
	 *
	 * @since ??
	 *
	 * @return bool|WP_Error
	 */
	public static function read_permission() {
		if ( ! UserRole::can_current_user_use_visual_builder() || ! current_user_can( 'edit_posts' ) ) {
			return self::response_error_permission();
		}

		return true;
	}

	/**
	 * Persist model preferences for the current user.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function update( WP_REST_Request $request ) {
		$user_id = get_current_user_id();

		if ( 1 > $user_id ) {
			return self::response_error_permission();
		}

		$preferences           = $request->get_param( 'preferences' );
		$sanitized_preferences = self::_sanitize_preferences( is_array( $preferences ) ? $preferences : [] );

		update_user_meta( $user_id, self::MODEL_PREFERENCES_META_KEY, $sanitized_preferences );

		return self::response_success(
			[
				'preferences' => self::_normalize_preferences_for_response( $sanitized_preferences ),
			]
		);
	}

	/**
	 * Update route args.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function update_args(): array {
		return [
			'preferences' => [
				'default'           => self::_get_default_preferences(),
				'type'              => 'object',
				'sanitize_callback' => static function ( $value ): array {
					return self::_sanitize_preferences( is_array( $value ) ? $value : [] );
				},
			],
		];
	}

	/**
	 * Update permission callback.
	 *
	 * @since ??
	 *
	 * @return bool|WP_Error
	 */
	public static function update_permission() {
		if ( ! UserRole::can_current_user_use_visual_builder() || ! current_user_can( 'edit_posts' ) ) {
			return self::response_error_permission();
		}

		return true;
	}

	/**
	 * Return the default model preferences shape.
	 *
	 * @since ??
	 *
	 * @return array<string,mixed>
	 */
	private static function _get_default_preferences(): array {
		return [
			'modelId'               => 'recommended_value',
			'reasoningEffort'       => 'medium',
			'customModelByAgentKey' => [],
		];
	}

	/**
	 * Normalize and sanitize model preferences from user input/meta.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $preferences Raw preferences.
	 *
	 * @return array<string,mixed>
	 */
	private static function _sanitize_preferences( array $preferences ): array {
		$defaults                = self::_get_default_preferences();
		$allowed_reasoning       = [ 'low', 'medium', 'high', 'xhigh' ];
		$allowed_agent_keys      = [ 'planner', 'agent', 'layout', 'ask' ];
		$model_id                = isset( $preferences['modelId'] ) && is_string( $preferences['modelId'] )
			? sanitize_text_field( $preferences['modelId'] )
			: $defaults['modelId'];
		$reasoning_effort        = isset( $preferences['reasoningEffort'] ) && is_string( $preferences['reasoningEffort'] )
			? sanitize_text_field( strtolower( $preferences['reasoningEffort'] ) )
			: $defaults['reasoningEffort'];
		$custom_model_by_agent   = [];
		$raw_custom_model_by_key = isset( $preferences['customModelByAgentKey'] ) && is_array( $preferences['customModelByAgentKey'] )
			? $preferences['customModelByAgentKey']
			: [];

		if ( '' === $model_id ) {
			$model_id = $defaults['modelId'];
		}

		if ( ! in_array( $reasoning_effort, $allowed_reasoning, true ) ) {
			$reasoning_effort = $defaults['reasoningEffort'];
		}

		foreach ( $allowed_agent_keys as $agent_key ) {
			$value = $raw_custom_model_by_key[ $agent_key ] ?? null;

			if ( ! is_string( $value ) ) {
				continue;
			}

			$sanitized_value = sanitize_text_field( $value );

			if ( '' === $sanitized_value ) {
				continue;
			}

			$custom_model_by_agent[ $agent_key ] = $sanitized_value;
		}

		return [
			'modelId'               => $model_id,
			'reasoningEffort'       => $reasoning_effort,
			'customModelByAgentKey' => $custom_model_by_agent,
		];
	}

	/**
	 * Ensure response payload keeps custom model map as a JSON object when empty.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed> $preferences Sanitized preferences.
	 *
	 * @return array<string,mixed>
	 */
	private static function _normalize_preferences_for_response( array $preferences ): array {
		$custom_model_by_agent = isset( $preferences['customModelByAgentKey'] ) && is_array( $preferences['customModelByAgentKey'] )
			? $preferences['customModelByAgentKey']
			: [];

		if ( [] === $custom_model_by_agent ) {
			$preferences['customModelByAgentKey'] = (object) [];
		}

		return $preferences;
	}
}
