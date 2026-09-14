<?php
/**
 * Dynamic Assets integration asset provider registry.
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\FrontEnd\Assets\DynamicAssets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Public registration facade for Dynamic Assets integration asset providers.
 *
 * @since ??
 */
final class IntegrationAssetsProviderRegistry {

	/**
	 * DA-internal provider collection.
	 *
	 * @var IntegrationAssetsProviderCollection
	 */
	private IntegrationAssetsProviderCollection $_collection;

	/**
	 * Constructor.
	 *
	 * @since ??
	 *
	 * @param IntegrationAssetsProviderCollection $collection DA-internal provider collection.
	 *
	 * @internal Dynamic Assets constructs the registry; extensions use the action-provided instance.
	 */
	public function __construct( IntegrationAssetsProviderCollection $collection ) {
		$this->_collection = $collection;
	}

	/**
	 * Register an integration asset provider.
	 *
	 * Both arguments are intentionally validated at runtime so malformed third-party
	 * registrations can fail softly instead of raising a frontend `TypeError`.
	 *
	 * @since ??
	 *
	 * @param mixed $id       Unique lowercase namespaced integration ID.
	 * @param mixed $provider Integration asset provider object.
	 *
	 * @return bool Whether the provider was registered.
	 */
	public function register( $id, $provider ): bool {
		$id_label = is_string( $id ) ? $id : gettype( $id );

		if ( $this->_collection->is_sealed() ) {
			$this->_warn(
				__METHOD__,
				sprintf(
					// Translators: %s is the integration provider ID.
					esc_html__( 'Integration asset provider "%s" was registered after the registry was sealed.', 'et_builder_5' ),
					esc_html( $id_label )
				)
			);

			return false;
		}

		if ( ! is_string( $id ) || ! $this->_is_valid_id( $id ) ) {
			$this->_warn(
				__METHOD__,
				sprintf(
					// Translators: %s is the invalid integration provider ID.
					esc_html__( 'Integration asset provider ID "%s" must be a lowercase namespaced slug such as "vendor/integration".', 'et_builder_5' ),
					esc_html( $id_label )
				)
			);

			return false;
		}

		if ( ! $provider instanceof IntegrationAssetsProviderInterface ) {
			$this->_warn(
				__METHOD__,
				sprintf(
					// Translators: %s is the integration provider ID.
					esc_html__( 'Integration asset provider "%s" must implement IntegrationAssetsProviderInterface.', 'et_builder_5' ),
					esc_html( $id )
				)
			);

			return false;
		}

		if ( $this->_collection->has( $id ) ) {
			$this->_warn(
				__METHOD__,
				sprintf(
					// Translators: %s is the duplicate integration provider ID.
					esc_html__( 'Integration asset provider "%s" is already registered. The first registration was preserved.', 'et_builder_5' ),
					esc_html( $id )
				)
			);

			return false;
		}

		$this->_collection->add( $id, $provider );

		return true;
	}

	/**
	 * Whether an integration ID is a lowercase namespaced slug.
	 *
	 * @param string $id Integration provider ID.
	 *
	 * @return bool
	 */
	private function _is_valid_id( string $id ): bool {
		return 1 === preg_match( '/^[a-z0-9][a-z0-9._-]*(?:\/[a-z0-9][a-z0-9._-]*)+$/D', $id );
	}

	/**
	 * Emit a developer warning without interrupting the frontend request.
	 *
	 * @param string $method  Method reporting the warning.
	 * @param string $message Warning message.
	 *
	 * @return void
	 */
	private function _warn( string $method, string $message ): void {
		_doing_it_wrong( $method, $message, '5.11.0' );
	}
}
