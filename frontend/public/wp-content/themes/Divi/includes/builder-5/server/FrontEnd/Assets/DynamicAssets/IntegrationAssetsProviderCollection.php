<?php
/**
 * Dynamic Assets integration asset provider collection.
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\FrontEnd\Assets\DynamicAssets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Throwable;

/**
 * DA-internal request state for registered integration asset providers.
 *
 * @since ??
 * @internal Dynamic Assets owns this lifecycle; extensions receive only the registry facade.
 */
final class IntegrationAssetsProviderCollection {

	/** @var array<string, IntegrationAssetsProviderInterface> */
	private array $_providers = [];

	/** @var array<string, bool> */
	private array $_disabled_providers = [];

	/** @var bool */
	private bool $_sealed = false;

	/**
	 * Add a validated provider.
	 *
	 * @since ??
	 *
	 * @param string                             $id       Integration ID.
	 * @param IntegrationAssetsProviderInterface $provider Provider object.
	 *
	 * @return void
	 */
	public function add( string $id, IntegrationAssetsProviderInterface $provider ): void {
		$this->_providers[ $id ] = $provider;
	}

	/**
	 * Whether an integration ID is already registered.
	 *
	 * @since ??
	 *
	 * @param string $id Integration ID.
	 *
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->_providers[ $id ] );
	}

	/**
	 * Close the registration window.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function seal(): void {
		$this->_sealed = true;
	}

	/**
	 * Whether the registration window is closed.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public function is_sealed(): bool {
		return $this->_sealed;
	}

	/**
	 * Disable one provider for the remainder of the request.
	 *
	 * @since ??
	 *
	 * @param string $id Integration ID.
	 *
	 * @return void
	 */
	public function disable( string $id ): void {
		$this->_disabled_providers[ $id ] = true;
	}

	/**
	 * Resolve available providers in deterministic registration order.
	 *
	 * Availability is resolved once per early/late pass. A provider that throws is disabled for
	 * the request without preventing other providers from running.
	 *
	 * @since ??
	 *
	 * @return array<string, IntegrationAssetsProviderInterface>
	 */
	public function get_available_providers(): array {
		$available_providers = [];

		foreach ( $this->_providers as $id => $provider ) {
			if ( isset( $this->_disabled_providers[ $id ] ) ) {
				continue;
			}

			try {
				$is_available = $provider->is_available();
			} catch ( Throwable $error ) {
				$this->disable( $id );

				_doing_it_wrong(
					__METHOD__,
					sprintf(
						// Translators: 1: integration provider ID; 2: exception class.
						esc_html__( 'Integration asset provider "%1$s" failed its availability check and was disabled for this request (%2$s).', 'et_builder_5' ),
						esc_html( $id ),
						esc_html( get_class( $error ) )
					),
					'5.11.0'
				);

				continue;
			}

			if ( $is_available ) {
				$available_providers[ $id ] = $provider;
			}
		}

		return $available_providers;
	}
}
