<?php
/**
 * PaymentAccountService class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Services\PaymentAccountService;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use WP_Error;

/**
 * Payment account service.
 *
 * @since ??
 */
class PaymentAccountService {
	/**
	 * Option name used to persist payment accounts.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	const OPTION_NAME = 'et_builder_5_payment_accounts';

	/**
	 * PayPal provider key.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	const PAYPAL_PROVIDER = 'paypal';

	/**
	 * Stripe provider key.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	const STRIPE_PROVIDER = 'stripe';

	/**
	 * Supported Stripe checkout hosts.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	const STRIPE_ALLOWED_HOSTS = [
		'checkout.stripe.com',
		'buy.stripe.com',
	];

	/**
	 * PayPal currencies that must be sent without decimal values.
	 *
	 * @since ??
	 *
	 * @var array
	 */
	const PAYPAL_ZERO_DECIMAL_CURRENCIES = [
		'HUF',
		'JPY',
		'TWD',
	];

	/**
	 * Get provider adapter definitions.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	private static function _provider_definitions(): array {
		return [
			self::PAYPAL_PROVIDER => [
				'label'                      => esc_html__( 'PayPal', 'et_builder_5' ),
				'resource_label'             => esc_html__( 'Account', 'et_builder_5' ),
				'resource_value_label'       => esc_html__( 'PayPal Email', 'et_builder_5' ),
				'resource_value_description' => esc_html__( 'The PayPal business email used to receive payments.', 'et_builder_5' ),
				'resource_id_prefix'         => 'pp_',
				'capabilities'               => [
					'supportsEnvironment' => true,
					'supportsAmountMode'  => true,
					'supportsAmount'      => true,
					'supportsCurrency'    => true,
					'supportsReturnUrl'   => true,
					'supportsCancelUrl'   => true,
					'supportsDescription' => true,
				],
			],
			self::STRIPE_PROVIDER => [
				'label'                      => esc_html__( 'Stripe', 'et_builder_5' ),
				'resource_label'             => esc_html__( 'Product', 'et_builder_5' ),
				'resource_value_label'       => esc_html__( 'Checkout URL', 'et_builder_5' ),
				'resource_value_description' => esc_html__( 'A Stripe hosted checkout link (for example, checkout.stripe.com).', 'et_builder_5' ),
				'resource_id_prefix'         => 'st_',
				'capabilities'               => [
					'supportsEnvironment' => false,
					'supportsAmountMode'  => false,
					'supportsAmount'      => false,
					'supportsCurrency'    => false,
					'supportsReturnUrl'   => false,
					'supportsCancelUrl'   => false,
				],
			],
		];
	}

	/**
	 * Get provider definition.
	 *
	 * @since ??
	 *
	 * @param string $provider Provider key.
	 *
	 * @return array|null
	 */
	private static function _provider_definition( string $provider ): ?array {
		$providers = self::_provider_definitions();

		return $providers[ $provider ] ?? null;
	}

	/**
	 * Whether a URL is a valid HTTPS checkout URL.
	 *
	 * @since ??
	 *
	 * @param string $url URL to validate.
	 *
	 * @return bool
	 */
	private static function _is_valid_stripe_checkout_url( string $url ): bool {
		$normalized_url = self::_normalize_stripe_checkout_url( $url );
		$parsed_url     = wp_parse_url( $normalized_url );

		if ( ! is_array( $parsed_url ) ) {
			return false;
		}

		$scheme = sanitize_text_field( (string) ( $parsed_url['scheme'] ?? '' ) );
		$host   = strtolower( sanitize_text_field( (string) ( $parsed_url['host'] ?? '' ) ) );

		if ( 'https' !== $scheme ) {
			return false;
		}

		if ( '' === $host || ! in_array( $host, self::STRIPE_ALLOWED_HOSTS, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Normalize Stripe checkout URL.
	 *
	 * Accepts values without protocol and normalizes to https.
	 *
	 * @since ??
	 *
	 * @param string $url Raw URL value.
	 *
	 * @return string
	 */
	private static function _normalize_stripe_checkout_url( string $url ): string {
		$normalized_url = trim( $url );

		if ( '' === $normalized_url ) {
			return '';
		}

		if ( str_starts_with( $normalized_url, 'http://' ) ) {
			$normalized_url = 'https://' . substr( $normalized_url, 7 );
		}

		if ( ! str_starts_with( $normalized_url, 'https://' ) ) {
			$normalized_url = "https://{$normalized_url}";
		}

		return esc_url_raw( $normalized_url );
	}

	/**
	 * Get persisted payment services payload.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	private static function _get_services(): array {
		$services = get_option( self::OPTION_NAME, [] );

		return is_array( $services ) ? $services : [];
	}

	/**
	 * Persist payment services payload.
	 *
	 * @since ??
	 *
	 * @param array $services Services payload.
	 *
	 * @return void
	 */
	private static function _set_services( array $services ): void {
		update_option( self::OPTION_NAME, $services, false );
	}

	/**
	 * Get fallback service payload keyed by provider.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	private static function _default_services(): array {
		$providers = self::_provider_definitions();

		return [
			self::PAYPAL_PROVIDER => [
				'label'     => $providers[ self::PAYPAL_PROVIDER ]['label'],
				'resources' => [],
			],
			self::STRIPE_PROVIDER => [
				'label'     => $providers[ self::STRIPE_PROVIDER ]['label'],
				'resources' => [],
			],
		];
	}

	/**
	 * Normalize provider resource value by provider rules.
	 *
	 * @since ??
	 *
	 * @param string $provider Provider key.
	 * @param string $value    Raw resource value.
	 *
	 * @return string
	 */
	private static function _normalize_provider_resource_value( string $provider, string $value ): string {
		if ( self::PAYPAL_PROVIDER === $provider ) {
			return sanitize_email( $value );
		}

		if ( self::STRIPE_PROVIDER === $provider ) {
			return self::_normalize_stripe_checkout_url( $value );
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Get decimal precision for PayPal amount formatting by currency code.
	 *
	 * @since ??
	 *
	 * @param string $currency Currency code.
	 *
	 * @return int
	 */
	private static function _paypal_currency_decimal_places( string $currency ): int {
		$normalized_currency = strtoupper( sanitize_text_field( $currency ) );

		return in_array( $normalized_currency, self::PAYPAL_ZERO_DECIMAL_CURRENCIES, true ) ? 0 : 2;
	}

	/**
	 * Normalize stored payload for Visual Builder use.
	 *
	 * Credentials are intentionally omitted from this definition payload.
	 *
	 * @since ??
	 *
	 * @param array $services Raw stored services.
	 *
	 * @return array
	 */
	private static function _normalize_definition( array $services ): array {
		$defaults   = self::_default_services();
		$normalized = [];

		foreach ( $defaults as $provider => $provider_data ) {
			$stored_provider = $services[ $provider ] ?? [];
			$resources       = $stored_provider['resources'] ?? [];
			$provider_label  = (string) ( $stored_provider['label'] ?? $provider_data['label'] );
			$provider_def    = self::_provider_definition( $provider );

			if ( ! is_array( $provider_def ) ) {
				continue;
			}

			$normalized_resources = [];

			if ( is_array( $resources ) ) {
				foreach ( $resources as $resource_id => $resource_data ) {
					if ( ! is_array( $resource_data ) ) {
						continue;
					}

					$resource_value = (string) ( $resource_data['resource_value'] ?? '' );

					$normalized_value = self::_normalize_provider_resource_value( $provider, $resource_value );
					$normalized_name  = (string) (
						$resource_data['resource_name']
						?? $resource_data['label']
						?? ''
					);
					$normalized_id    = (string) ( $resource_data['id'] ?? $resource_id );

					if ( '' === $normalized_name ) {
						$normalized_name = $normalized_value;
					}

					$normalized_resources[ $normalized_id ] = [
						'id'             => $normalized_id,
						'resource_name'  => $normalized_name,
						'label'          => $normalized_name,
						'resource_value' => $normalized_value,
						'status'         => (string) ( $resource_data['status'] ?? 'active' ),
						'created_at'     => (string) ( $resource_data['created_at'] ?? '' ),
						'updated_at'     => (string) ( $resource_data['updated_at'] ?? '' ),
					];
				}
			}

			$normalized[ $provider ] = [
				'label'                    => $provider_label,
				'resourceLabel'            => $provider_def['resource_label'],
				'resourceValueLabel'       => $provider_def['resource_value_label'],
				'resourceValueDescription' => $provider_def['resource_value_description'],
				'capabilities'             => $provider_def['capabilities'],
				'resources'                => $normalized_resources,
			];
		}

		return $normalized;
	}

	/**
	 * Get service definition for settings/field consumption.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function definition(): array {
		return self::_normalize_definition( self::_get_services() );
	}

	/**
	 * Create a payment resource.
	 *
	 * @since ??
	 *
	 * @param string $provider      Provider key.
	 * @param string $resource_value Provider resource value.
	 * @param string $resource_name  Provider resource display name.
	 *
	 * @return array|WP_Error
	 */
	public static function create_resource( string $provider, string $resource_value, string $resource_name = '' ) {
		$normalized_provider = sanitize_key( $provider );
		$normalized_name     = sanitize_text_field( $resource_name );
		$provider_def        = self::_provider_definition( $normalized_provider );

		if ( ! is_array( $provider_def ) ) {
			return new WP_Error( 'payment_provider_unsupported', esc_html__( 'Payment provider is not supported.', 'et_builder_5' ) );
		}

		$normalized_value = self::_normalize_provider_resource_value( $normalized_provider, $resource_value );

		if ( self::PAYPAL_PROVIDER === $normalized_provider ) {
			if ( '' === $normalized_value || ! is_email( $normalized_value ) ) {
				return new WP_Error( 'payment_resource_email_invalid', esc_html__( 'PayPal account email is invalid.', 'et_builder_5' ) );
			}
		}

		if ( self::STRIPE_PROVIDER === $normalized_provider ) {
			if ( '' === $normalized_value || ! self::_is_valid_stripe_checkout_url( $normalized_value ) ) {
				return new WP_Error( 'payment_resource_url_invalid', esc_html__( 'Stripe URL is invalid.', 'et_builder_5' ) );
			}
		}

		$services = self::_get_services();

		if ( ! isset( $services[ $normalized_provider ] ) || ! is_array( $services[ $normalized_provider ] ) ) {
			$services[ $normalized_provider ] = [
				'label'     => $provider_def['label'],
				'resources' => [],
			];
		}

		if ( ! isset( $services[ $normalized_provider ]['resources'] ) || ! is_array( $services[ $normalized_provider ]['resources'] ) ) {
			$services[ $normalized_provider ]['resources'] = [];
		}

		$id_prefix = sanitize_key( (string) $provider_def['resource_id_prefix'] );

		do {
			$resource_id = sanitize_key( uniqid( $id_prefix, true ) );
		} while ( isset( $services[ $normalized_provider ]['resources'][ $resource_id ] ) );

		$now    = gmdate( 'c' );
		$status = 'active';
		$label  = '' !== $normalized_name ? $normalized_name : $normalized_value;

		$services[ $normalized_provider ]['resources'][ $resource_id ] = [
			'id'             => $resource_id,
			'resource_name'  => $label,
			'resource_value' => $normalized_value,
			'status'         => $status,
			'created_at'     => $now,
			'updated_at'     => $now,
		];

		self::_set_services( $services );

		return self::definition();
	}

	/**
	 * Update a payment resource.
	 *
	 * @since ??
	 *
	 * @param string $provider      Provider key.
	 * @param string $resource_id    Resource ID.
	 * @param string $resource_name  Resource display name.
	 * @param string $resource_value Provider resource value.
	 *
	 * @return array|WP_Error
	 */
	public static function update_resource( string $provider, string $resource_id, string $resource_name, string $resource_value ) {
		$normalized_provider    = sanitize_key( $provider );
		$normalized_resource_id = sanitize_text_field( $resource_id );
		$normalized_name        = sanitize_text_field( $resource_name );
		$provider_def           = self::_provider_definition( $normalized_provider );

		if ( ! is_array( $provider_def ) ) {
			return new WP_Error( 'payment_provider_unsupported', esc_html__( 'Payment provider is not supported.', 'et_builder_5' ) );
		}

		$services = self::_get_services();

		if ( ! isset( $services[ $normalized_provider ]['resources'] ) || ! is_array( $services[ $normalized_provider ]['resources'] ) ) {
			return new WP_Error( 'payment_resource_missing', esc_html__( 'Payment resource not found.', 'et_builder_5' ) );
		}

		$resource = $services[ $normalized_provider ]['resources'][ $normalized_resource_id ] ?? null;

		if ( ! is_array( $resource ) ) {
			return new WP_Error( 'payment_resource_missing', esc_html__( 'Payment resource not found.', 'et_builder_5' ) );
		}

		$normalized_value = self::_normalize_provider_resource_value( $normalized_provider, $resource_value );

		if ( self::PAYPAL_PROVIDER === $normalized_provider && ( '' === $normalized_value || ! is_email( $normalized_value ) ) ) {
			return new WP_Error( 'payment_resource_email_invalid', esc_html__( 'PayPal account email is invalid.', 'et_builder_5' ) );
		}

		if ( self::STRIPE_PROVIDER === $normalized_provider && ( '' === $normalized_value || ! self::_is_valid_stripe_checkout_url( $normalized_value ) ) ) {
			return new WP_Error( 'payment_resource_url_invalid', esc_html__( 'Stripe URL is invalid.', 'et_builder_5' ) );
		}

		$label = '' !== $normalized_name ? $normalized_name : $normalized_value;

		$services[ $normalized_provider ]['resources'][ $normalized_resource_id ]['resource_name']  = $label;
		$services[ $normalized_provider ]['resources'][ $normalized_resource_id ]['resource_value'] = $normalized_value;
		$services[ $normalized_provider ]['resources'][ $normalized_resource_id ]['updated_at']     = gmdate( 'c' );

		self::_set_services( $services );

		return self::definition();
	}

	/**
	 * Delete a payment resource.
	 *
	 * @since ??
	 *
	 * @param string $provider   Provider key.
	 * @param string $resource_id Resource ID.
	 *
	 * @return array
	 */
	public static function delete_resource( string $provider, string $resource_id ): array {
		$normalized_provider    = sanitize_key( $provider );
		$normalized_resource_id = sanitize_text_field( $resource_id );
		$services               = self::_get_services();

		if ( ! isset( $services[ $normalized_provider ]['resources'] ) || ! is_array( $services[ $normalized_provider ]['resources'] ) ) {
			return self::definition();
		}

		if (
			isset( $services[ $normalized_provider ]['resources'][ $normalized_resource_id ] ) &&
			is_array( $services[ $normalized_provider ]['resources'] )
		) {
			unset( $services[ $normalized_provider ]['resources'][ $normalized_resource_id ] );
			self::_set_services( $services );
		}

		return self::definition();
	}

	/**
	 * Build a checkout URL for the selected provider resource.
	 *
	 * @since ??
	 *
	 * @param string $provider         Provider key.
	 * @param string $resource_id      Resource ID.
	 * @param string $environment      Environment key.
	 * @param string $amount           Checkout amount.
	 * @param string $currency         Currency code.
	 * @param string $amount_mode      Amount mode.
	 * @param string $return_url       Return URL.
	 * @param string $cancel_url       Cancel URL.
	 * @param string $description      Payment description (PayPal item_name).
	 *
	 * @return string|WP_Error
	 */
	public static function create_checkout_url(
		string $provider,
		string $resource_id,
		string $environment,
		string $amount,
		string $currency,
		string $amount_mode = 'fixed',
		string $return_url = '',
		string $cancel_url = '',
		string $description = ''
	) {
		$normalized_provider    = sanitize_key( $provider );
		$normalized_resource_id = sanitize_text_field( $resource_id );
		$normalized_environment = 'live' === sanitize_key( $environment ) ? 'live' : 'sandbox';
		$normalized_currency    = strtoupper( sanitize_text_field( $currency ) );
		$normalized_currency    = '' !== $normalized_currency ? $normalized_currency : 'USD';
		$normalized_amount      = sanitize_text_field( $amount );
		$normalized_amount_mode = sanitize_key( $amount_mode );

		if ( ! is_array( self::_provider_definition( $normalized_provider ) ) ) {
			return new WP_Error( 'payment_provider_unsupported', esc_html__( 'Payment provider is not supported.', 'et_builder_5' ) );
		}

		$services = self::definition();
		$resource = $services[ $normalized_provider ]['resources'][ $normalized_resource_id ] ?? null;

		if ( ! is_array( $resource ) ) {
			return new WP_Error( 'payment_resource_missing', esc_html__( 'Payment resource not found.', 'et_builder_5' ) );
		}

		if ( self::STRIPE_PROVIDER === $normalized_provider ) {
			$stripe_url = self::_normalize_stripe_checkout_url( (string) ( $resource['resource_value'] ?? '' ) );

			if ( '' === $stripe_url || ! self::_is_valid_stripe_checkout_url( $stripe_url ) ) {
				return new WP_Error( 'payment_resource_url_missing', esc_html__( 'Stripe checkout URL is missing.', 'et_builder_5' ) );
			}

			return $stripe_url;
		}

		$paypal_email = sanitize_email( (string) ( $resource['resource_value'] ?? '' ) );

		if ( '' === $paypal_email || ! is_email( $paypal_email ) ) {
			return new WP_Error( 'payment_resource_email_missing', esc_html__( 'PayPal account email is missing.', 'et_builder_5' ) );
		}

		$amount_decimal_places = self::_paypal_currency_decimal_places( $normalized_currency );
		$amount_value          = is_numeric( $normalized_amount )
			? number_format( (float) $normalized_amount, $amount_decimal_places, '.', '' )
			: '';

		if ( 'user-defined' === $normalized_amount_mode ) {
			$amount_value = '';
		}

		$checkout_host = 'sandbox' === $normalized_environment
			? 'https://www.sandbox.paypal.com/cgi-bin/webscr'
			: 'https://www.paypal.com/cgi-bin/webscr';

		$query_args = [
			'cmd'           => '_xclick',
			'business'      => $paypal_email,
			'currency_code' => $normalized_currency,
		];

		if ( '' !== $amount_value ) {
			$query_args['amount'] = $amount_value;
		}

		$normalized_return_url = esc_url_raw( $return_url );
		$normalized_cancel_url = esc_url_raw( $cancel_url );

		if ( '' !== $normalized_return_url ) {
			$query_args['return'] = $normalized_return_url;
		}

		if ( '' !== $normalized_cancel_url ) {
			$query_args['cancel_return'] = $normalized_cancel_url;
		}

		$normalized_description = sanitize_text_field( $description );

		if ( '' !== $normalized_description ) {
			$query_args['item_name'] = function_exists( 'mb_substr' )
				? mb_substr( $normalized_description, 0, 127 )
				: substr( $normalized_description, 0, 127 );
		}

		return esc_url_raw( add_query_arg( $query_args, $checkout_host ) );
	}
}
