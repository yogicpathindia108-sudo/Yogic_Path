<?php
/**
 * PaymentButton: PaymentButtonController.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Packages\ModuleLibrary\PaymentButton;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Controllers\RESTController;
use ET\Builder\Framework\UserRole\UserRole;
use ET\Builder\Services\PaymentAccountService\PaymentAccountService;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Payment Button REST Controller class.
 *
 * @since ??
 */
class PaymentButtonController extends RESTController {
	/**
	 * Write Payment Button debug log.
	 *
	 * @since ??
	 *
	 * @param string $message Log message.
	 * @param array  $context Context payload.
	 *
	 * @return void
	 */
	private static function _debug_log( string $message, array $context = [] ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log(
			sprintf(
				'[Divi Builder 5][PaymentButtonController] %1$s | context=%2$s',
				$message,
				(string) wp_json_encode( $context )
			)
		);
	}

	/**
	 * Read provider and resource payload for Visual Builder fields.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function read( WP_REST_Request $request ): WP_REST_Response {
		$provider = $request->get_param( 'provider' );
		$services = self::_append_mutation_capability( self::_get_services_payload() );

		if ( $provider && isset( $services[ $provider ] ) ) {
			return self::response_success(
				[
					'services' => [
						$provider => $services[ $provider ],
					],
				]
			);
		}

		return self::response_success(
			[
				'services' => $services,
			]
		);
	}

	/**
	 * Read action arguments.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function read_args(): array {
		return [
			'provider' => [
				'description'       => esc_html__( 'Optional provider key.', 'et_builder_5' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Read action permission callback.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public static function read_permission(): bool {
		return UserRole::can_current_user_use_visual_builder();
	}

	/**
	 * Whether current user can mutate payment resources.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function _can_mutate_payment_resources(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Append payment mutation capability flag to each provider payload.
	 *
	 * @since ??
	 *
	 * @param array $services Services payload.
	 *
	 * @return array
	 */
	private static function _append_mutation_capability( array $services ): array {
		$can_mutate = self::_can_mutate_payment_resources();

		foreach ( $services as $provider => $provider_data ) {
			if ( ! is_array( $provider_data ) ) {
				continue;
			}

			$provider_data['canMutate'] = $can_mutate;
			$services[ $provider ]      = $provider_data;
		}

		return $services;
	}

	/**
	 * Get normalized services payload.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	private static function _get_services_payload(): array {
		try {
			$defaults = PaymentAccountService::definition();
		} catch ( \Throwable $error ) {
			self::_debug_log(
				'PaymentAccountService::definition threw exception.',
				[
					'message' => $error->getMessage(),
					'file'    => $error->getFile(),
					'line'    => $error->getLine(),
				]
			);

			return [];
		}

		/**
		 * Filters available Payment Button providers/resources for Visual Builder.
		 *
		 * @since ??
		 *
		 * @param array $defaults Default service payload.
		 */
		try {
			$services = apply_filters( 'divi_module_payment_button_services', $defaults );
		} catch ( \Throwable $error ) {
			self::_debug_log(
				'divi_module_payment_button_services filter threw exception.',
				[
					'message' => $error->getMessage(),
					'file'    => $error->getFile(),
					'line'    => $error->getLine(),
				]
			);

			return $defaults;
		}

		return is_array( $services ) ? $services : $defaults;
	}
}
