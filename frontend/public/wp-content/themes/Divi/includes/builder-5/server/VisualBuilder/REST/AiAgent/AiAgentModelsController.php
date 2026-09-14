<?php
/**
 * REST: AiAgentModelsController class.
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
use ET\Builder\VisualBuilder\REST\DiviAIAuth\DiviAIAuthService;
use WP_Error;

/**
 * REST controller for AI agent text models.
 *
 * @since ??
 */
class AiAgentModelsController extends RESTController {
	/**
	 * Site transient key for cached text-model payload.
	 *
	 * @since ??
	 */
	private const CACHE_KEY = 'et_builder_ai_agent_text_models_v5';

	/**
	 * Cache TTL in seconds.
	 *
	 * @since ??
	 */
	private const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Build a cache key scoped to the site, WordPress user, and token family.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	private static function _cache_key(): string {
		$user_id          = get_current_user_id();
		$auth_credentials = 0 < $user_id ? DiviAIAuthService::read( $user_id ) : [ 'connected' => false ];
		$token_family_id  = isset( $auth_credentials['token_family_id'] ) && is_string( $auth_credentials['token_family_id'] )
			? $auth_credentials['token_family_id']
			: 'disconnected';

		return sprintf(
			'%1$s:%2$d:%3$d:%4$s',
			self::CACHE_KEY,
			get_current_blog_id(),
			$user_id,
			hash( 'sha256', $token_family_id )
		);
	}

	/**
	 * Returns the normalized model list, preferring cache first.
	 *
	 * @since ??
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function read() {
		$cached_payload = self::_get_cached_models_payload();
		if ( ! empty( $cached_payload['models'] ) ) {
			return self::response_success(
				[
					'models'              => $cached_payload['models'],
					'toolClassifierModel' => $cached_payload['toolClassifierModel'],
				]
			);
		}

		$fetched_payload = self::_fetch_models_from_ai_server();
		if ( is_wp_error( $fetched_payload ) ) {
			return self::response_success(
				[
					'models'              => [],
					'toolClassifierModel' => '',
				]
			);
		}

		self::_cache_models_payload( $fetched_payload['models'], $fetched_payload['toolClassifierModel'] );

		return self::response_success(
			[
				'models'              => $fetched_payload['models'],
				'toolClassifierModel' => $fetched_payload['toolClassifierModel'],
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
	 * Returns cached model payload from site transient.
	 *
	 * @since ??
	 *
	 * @return array{models:array<int,array<string,mixed>>,toolClassifierModel:string}
	 */
	private static function _get_cached_models_payload(): array {
		$cached = get_site_transient( self::_cache_key() );

		if ( ! is_array( $cached ) ) {
			return [
				'models'              => [],
				'toolClassifierModel' => '',
			];
		}
		$models                = isset( $cached['models'] ) && is_array( $cached['models'] ) ? $cached['models'] : [];
		$tool_classifier_model = isset( $cached['toolClassifierModel'] ) && is_string( $cached['toolClassifierModel'] )
			? sanitize_text_field( $cached['toolClassifierModel'] )
			: '';

		return [
			'models'              => $models,
			'toolClassifierModel' => $tool_classifier_model,
		];
	}

	/**
	 * Cache normalized model payload in site transient.
	 *
	 * @since ??
	 *
	 * @param array<int,array<string,mixed>> $models                Models to cache.
	 * @param string                         $tool_classifier_model Tool-classifier model id.
	 *
	 * @return void
	 */
	private static function _cache_models_payload( array $models, string $tool_classifier_model ): void {
		set_site_transient(
			self::_cache_key(),
			[
				'models'              => $models,
				'toolClassifierModel' => sanitize_text_field( $tool_classifier_model ),
			],
			self::CACHE_TTL
		);
	}

	/**
	 * Fetch model list from AI server `/api/v2/agent/models/text`.
	 *
	 * @since ??
	 *
	 * @return array{models:array<int,array<string,mixed>>,toolClassifierModel:string}|WP_Error
	 */
	private static function _fetch_models_from_ai_server() {
		$base_url = defined( 'ET_AI_SERVER_URL_V2' ) ? ET_AI_SERVER_URL_V2 : '';
		if ( '' === $base_url ) {
			return self::_models_error(
				'ai_models_missing_base_url',
				esc_html__( 'AI model endpoint is not configured.', 'et_builder_5' ),
				500
			);
		}

		$authorization_header = '';
		$user_id              = get_current_user_id();
		$auth_credentials     = 0 < $user_id ? DiviAIAuthService::read( $user_id ) : [ 'connected' => false ];
		$access_token         = isset( $auth_credentials['access_token'] ) && is_string( $auth_credentials['access_token'] )
			? $auth_credentials['access_token']
			: '';
		$access_token_expires_at = isset( $auth_credentials['access_token_expires_at'] ) && is_int( $auth_credentials['access_token_expires_at'] )
			? $auth_credentials['access_token_expires_at']
			: 0;

		if ( ! empty( $auth_credentials['connected'] ) && '' !== $access_token ) {
			$is_access_token_usable = time() < $access_token_expires_at;

			// Renew when token is expired or close to expiry. If proactive renewal
			// cannot complete for a split session, keep using the still-valid token.
			if ( ( time() + MINUTE_IN_SECONDS ) >= $access_token_expires_at ) {
				$renewed_credentials = DiviAIAuthService::renew( $user_id, null );

				if (
					! is_wp_error( $renewed_credentials ) &&
					isset( $renewed_credentials['access_token'] ) &&
					is_string( $renewed_credentials['access_token'] ) &&
					'' !== $renewed_credentials['access_token'] &&
					isset( $renewed_credentials['access_token_expires_at'] ) &&
					is_int( $renewed_credentials['access_token_expires_at'] ) &&
					time() < $renewed_credentials['access_token_expires_at']
				) {
					$access_token = $renewed_credentials['access_token'];
					$is_access_token_usable = true;
				}
			}

			if ( $is_access_token_usable ) {
				$authorization_header = 'Bearer ' . $access_token;
			}
		}

		if ( '' === $authorization_header ) {
			return self::_models_error(
				'ai_models_missing_credentials',
				esc_html__( 'AI account credentials are missing.', 'et_builder_5' ),
				401
			);
		}

		$response = wp_remote_get(
			untrailingslashit( $base_url ) . '/agent/models/text',
			[
				'timeout' => 20,
				'headers' => [
					'Authorization'     => $authorization_header,
					'X-Product-Version' => defined( 'ET_BUILDER_PRODUCT_VERSION' ) ? ET_BUILDER_PRODUCT_VERSION : '',
					'Content-Type'      => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return self::_models_error(
				'ai_models_request_failed',
				esc_html__( 'Failed to fetch AI models.', 'et_builder_5' ),
				502
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );

		if ( 200 > $status_code || 300 <= $status_code ) {
			return self::_models_error(
				'ai_models_request_failed',
				esc_html__( 'Failed to fetch AI models.', 'et_builder_5' ),
				$status_code
			);
		}

		$body = json_decode( $raw_body, true );
		if ( ! is_array( $body ) ) {
			return self::_models_error(
				'ai_models_invalid_response',
				esc_html__( 'Invalid AI models response.', 'et_builder_5' ),
				502
			);
		}

		$models = self::_normalize_models_response( $body );
		$tool_classifier_model = isset( $body['toolClassifierModel'] ) && is_string( $body['toolClassifierModel'] )
			? sanitize_text_field( $body['toolClassifierModel'] )
			: '';

		return [
			'models'              => $models,
			'toolClassifierModel' => $tool_classifier_model,
		];
	}

	/**
	 * Normalize the upstream `/agent/models/text` payload into
	 * `{ id, label, contextLength?, isAllowed?, promptTokenPrice?, completionTokenPrice?, recommendedBalancedFor?, recommendedValueFor?, recommendedDeepValueFor?, recommendedPremiumFor? }[]`.
	 *
	 * @since ??
	 *
	 * @param array<string,mixed>|array<int,mixed> $response Parsed API payload.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function _normalize_models_response( array $response ): array {
		$raw_models       = [];
		$default_model_id = '';

		if ( isset( $response['defaultModel'] ) && is_string( $response['defaultModel'] ) ) {
			$default_model_id = sanitize_text_field( $response['defaultModel'] );
		}

		if ( isset( $response['models'] ) && is_array( $response['models'] ) ) {
			$raw_models = $response['models'];
		} elseif ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
			$raw_models = $response['data'];
		} elseif ( array_is_list( $response ) ) {
			$raw_models = $response;
		}

		$models = [];

		foreach ( $raw_models as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$model_id = '';

			if ( isset( $item['id'] ) && is_string( $item['id'] ) ) {
				$model_id = sanitize_text_field( $item['id'] );
			} elseif ( isset( $item['model'] ) && is_string( $item['model'] ) ) {
				$model_id = sanitize_text_field( $item['model'] );
			} elseif ( isset( $item['value'] ) && is_string( $item['value'] ) ) {
				$model_id = sanitize_text_field( $item['value'] );
			}

			if ( '' === $model_id ) {
				continue;
			}

			$label = $model_id;
			if ( isset( $item['label'] ) && is_string( $item['label'] ) ) {
				$label = sanitize_text_field( $item['label'] );
			} elseif ( isset( $item['name'] ) && is_string( $item['name'] ) ) {
				$label = sanitize_text_field( $item['name'] );
			}
			$model                      = [
				'id'    => $model_id,
				'label' => '' !== $label ? $label : $model_id,
			];
			$recommended_balanced_for   = [];
			$recommended_value_for      = [];
			$recommended_deep_value_for = [];
			$recommended_premium_for    = [];
			$supported_efforts          = [];
			$default_effort             = '';
			$context_length             = 0;
			$is_allowed                 = null;
			$prompt_token_price         = null;
			$completion_token_price     = null;

			if ( isset( $item['contextLength'] ) ) {
				$context_length = absint( $item['contextLength'] );
			}

			if ( 0 >= $context_length && $model_id !== $default_model_id ) {
				continue;
			}
			$model['contextLength'] = $context_length;

			if ( isset( $item['isAllowed'] ) && is_bool( $item['isAllowed'] ) ) {
				$is_allowed = $item['isAllowed'];
			}

			if ( null !== $is_allowed ) {
				$model['isAllowed'] = $is_allowed;
			}

			if ( isset( $item['supportedEfforts'] ) && is_array( $item['supportedEfforts'] ) ) {
				$supported_efforts = $item['supportedEfforts'];
			} elseif ( isset( $item['supported_efforts'] ) && is_array( $item['supported_efforts'] ) ) {
				$supported_efforts = $item['supported_efforts'];
			}

			if ( ! empty( $supported_efforts ) ) {
				$allowed_efforts = [ 'low', 'medium', 'high', 'xhigh' ];

				$normalized_supported_efforts = array_values(
					array_filter(
						array_map(
							static function ( $effort ) use ( $allowed_efforts ) {
								if ( ! is_string( $effort ) ) {
									return '';
								}

								$normalized_effort = sanitize_text_field( strtolower( $effort ) );

								return in_array( $normalized_effort, $allowed_efforts, true ) ? $normalized_effort : '';
							},
							$supported_efforts
						),
						static function ( $effort ) {
							return '' !== $effort;
						}
					)
				);

				if ( ! empty( $normalized_supported_efforts ) ) {
					$model['supportedEfforts'] = $normalized_supported_efforts;
				}
			}

			if ( isset( $item['defaultEffort'] ) && is_string( $item['defaultEffort'] ) ) {
				$default_effort = sanitize_text_field( strtolower( $item['defaultEffort'] ) );
			} elseif ( isset( $item['default_effort'] ) && is_string( $item['default_effort'] ) ) {
				$default_effort = sanitize_text_field( strtolower( $item['default_effort'] ) );
			}

			if ( '' !== $default_effort ) {
				$allowed_efforts = [ 'low', 'medium', 'high', 'xhigh' ];

				if ( in_array( $default_effort, $allowed_efforts, true ) ) {
					$model['defaultEffort'] = $default_effort;
				}
			}

			if ( isset( $item['promptTokenPrice'] ) && is_numeric( $item['promptTokenPrice'] ) ) {
				$parsed_prompt_token_price = (float) $item['promptTokenPrice'];

				if ( 0 <= $parsed_prompt_token_price ) {
					$prompt_token_price = $parsed_prompt_token_price;
				}
			}

			if ( null !== $prompt_token_price ) {
				$model['promptTokenPrice'] = $prompt_token_price;
			}

			if ( isset( $item['completionTokenPrice'] ) && is_numeric( $item['completionTokenPrice'] ) ) {
				$parsed_completion_token_price = (float) $item['completionTokenPrice'];

				if ( 0 <= $parsed_completion_token_price ) {
					$completion_token_price = $parsed_completion_token_price;
				}
			}

			if ( null !== $completion_token_price ) {
				$model['completionTokenPrice'] = $completion_token_price;
			}

			if ( isset( $item['recommendedBalancedFor'] ) && is_array( $item['recommendedBalancedFor'] ) ) {
				$recommended_balanced_for = $item['recommendedBalancedFor'];
			}

			if ( isset( $item['recommendedValueFor'] ) && is_array( $item['recommendedValueFor'] ) ) {
				$recommended_value_for = $item['recommendedValueFor'];
			}

			if ( isset( $item['recommendedDeepValueFor'] ) && is_array( $item['recommendedDeepValueFor'] ) ) {
				$recommended_deep_value_for = $item['recommendedDeepValueFor'];
			}

			if ( isset( $item['recommendedPremiumFor'] ) && is_array( $item['recommendedPremiumFor'] ) ) {
				$recommended_premium_for = $item['recommendedPremiumFor'];
			}

			if ( ! empty( $recommended_balanced_for ) ) {
				$normalized_recommended_balanced_for = array_values(
					array_filter(
						array_map(
							static function ( $target ) {
								return is_string( $target ) ? sanitize_text_field( $target ) : '';
							},
							$recommended_balanced_for
						),
						static function ( $target ) {
							return '' !== $target;
						}
					)
				);

				if ( ! empty( $normalized_recommended_balanced_for ) ) {
					$model['recommendedBalancedFor'] = $normalized_recommended_balanced_for;
				}
			}

			if ( ! empty( $recommended_value_for ) ) {
				$normalized_recommended_value_for = array_values(
					array_filter(
						array_map(
							static function ( $target ) {
								return is_string( $target ) ? sanitize_text_field( $target ) : '';
							},
							$recommended_value_for
						),
						static function ( $target ) {
							return '' !== $target;
						}
					)
				);

				if ( ! empty( $normalized_recommended_value_for ) ) {
					$model['recommendedValueFor'] = $normalized_recommended_value_for;
				}
			}

			if ( ! empty( $recommended_deep_value_for ) ) {
				$normalized_recommended_deep_value_for = array_values(
					array_filter(
						array_map(
							static function ( $target ) {
								return is_string( $target ) ? sanitize_text_field( $target ) : '';
							},
							$recommended_deep_value_for
						),
						static function ( $target ) {
							return '' !== $target;
						}
					)
				);

				if ( ! empty( $normalized_recommended_deep_value_for ) ) {
					$model['recommendedDeepValueFor'] = $normalized_recommended_deep_value_for;
				}
			}

			if ( ! empty( $recommended_premium_for ) ) {
				$normalized_recommended_premium_for = array_values(
					array_filter(
						array_map(
							static function ( $target ) {
								return is_string( $target ) ? sanitize_text_field( $target ) : '';
							},
							$recommended_premium_for
						),
						static function ( $target ) {
							return '' !== $target;
						}
					)
				);

				if ( ! empty( $normalized_recommended_premium_for ) ) {
					$model['recommendedPremiumFor'] = $normalized_recommended_premium_for;
				}
			}

			$models[] = $model;
		}

		return $models;
	}

	/**
	 * Build a WP_Error response for model endpoint failures.
	 *
	 * @since ??
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param int    $status  HTTP status code.
	 *
	 * @return WP_Error
	 */
	private static function _models_error( string $code, string $message, int $status ): WP_Error {
		return new WP_Error(
			$code,
			$message,
			[
				'status' => $status,
			]
		);
	}
}
