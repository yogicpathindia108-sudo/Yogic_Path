<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Allowlist WooCommerce storefront and gateway traffic.
 *
 * The matcher is intentionally permissive: false positives here mean
 * one more allowed request (acceptable), false negatives mean a broken
 * checkout or a dropped payment-gateway callback (never acceptable).
 *
 * Detection is URI-based:
 *   - /cart/ and /checkout/ catch the default WC page slugs. Stores
 *     that rename these pages only lose allowlisting on the renamed
 *     slug, which still funnels to the same wc-ajax endpoints and
 *     stays allowlisted via those.
 *   - /wp-json/wc/* covers every versioned WC REST namespace.
 *   - ?wc-ajax= and ?wc-api= are accepted ONLY at the site root
 *     (`/`, empty path, or `/index.php`). WooCommerce dispatches
 *     both of these query-arg endpoints from the front controller
 *     at the root, so any other path carrying the same query is a
 *     bypass attempt — typical patterns include
 *     `/imunify-bot-check?wc-ajax=x` to dodge the honeypot or
 *     `/wp-json/wp/v2/posts?wc-ajax=x` to scrape under the WC alibi.
 *     Restricting these to the root preserves Stripe/PayPal/Square
 *     callback delivery (which always POST to the root) and the WC
 *     front-end's own AJAX bus, while closing the bypass.
 *   - ?add-to-cart= is accepted only at the site root for the same
 *     reason; non-root product-permalink variants
 *     (`/product/widget/?add-to-cart=42`) still classify normally
 *     and rely on browser-UA → HUMAN to skip the rate limiter.
 *     This favours catching scrapers that append ?add-to-cart=N to
 *     arbitrary URLs over a marginal allowlisting convenience for
 *     the rare bot-UA add-to-cart callback.
 *
 * UA is not inspected — a gateway callback comes from the gateway's
 * servers with arbitrary (often undocumented) UAs.
 *
 * @since 4.0.0
 */
class WooCommerceMatcher implements AllowlistMatcher {

	/**
	 * {@inheritdoc}
	 */
	public function matches( $context ) {
		if ( ! is_array( $context ) || ! isset( $context['uri'] ) || ! is_string( $context['uri'] ) ) {
			return false;
		}
		$uri = $context['uri'];
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pre-WP hot path; wp_parse_url may not be available.
		$path = strtolower( (string) parse_url( $uri, PHP_URL_PATH ) );

		if ( 0 === strpos( $path, '/cart/' ) || '/cart' === $path ) {
			return true;
		}
		if ( 0 === strpos( $path, '/checkout/' ) || '/checkout' === $path ) {
			return true;
		}
		if ( 0 === strpos( $path, '/wp-json/wc/' ) ) {
			return true;
		}

		// Query-param allowlist is only valid at the site root — see
		// class docblock for the bypass rationale.
		if ( '' !== $path && '/' !== $path && '/index.php' !== $path ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pre-WP hot path; wp_parse_url may not be available.
		$query = (string) parse_url( $uri, PHP_URL_QUERY );
		if ( '' === $query ) {
			return false;
		}
		parse_str( $query, $args );
		if ( isset( $args['add-to-cart'] ) || isset( $args['wc-ajax'] ) || isset( $args['wc-api'] ) ) {
			return true;
		}
		return false;
	}
}
