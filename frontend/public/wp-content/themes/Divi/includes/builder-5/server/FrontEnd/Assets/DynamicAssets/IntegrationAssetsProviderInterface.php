<?php
/**
 * Dynamic Assets integration asset provider contract.
 *
 * @since   ??
 * @package Divi
 */

namespace ET\Builder\FrontEnd\Assets\DynamicAssets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Contract for integrations that consume Dynamic Assets-owned request content.
 *
 * Providers interpret their own modules and invoke their plugin's supported enqueue API. Dynamic
 * Assets retains ownership of request gating, content gathering, and early/late timing.
 *
 * @since ??
 */
interface IntegrationAssetsProviderInterface {

	/**
	 * Whether the integration's runtime enqueue boundary is available.
	 *
	 * Implementations must keep this check cheap and side-effect free.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	public function is_available(): bool;

	/**
	 * Interpret DA-owned content and enqueue integration assets through the plugin's public API.
	 *
	 * @since ??
	 *
	 * @param IntegrationAssetsContext $context Dynamic Assets integration context.
	 *
	 * @return void
	 */
	public function enqueue( IntegrationAssetsContext $context ): void;
}
