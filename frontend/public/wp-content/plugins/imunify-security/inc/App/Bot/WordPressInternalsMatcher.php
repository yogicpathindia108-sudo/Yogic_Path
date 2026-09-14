<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

/**
 * Allowlist WordPress's own loopback / discovery traffic.
 *
 * Matches three things:
 *   - PHP's DOING_CRON constant (wp-cron invoked as an HTTP loopback).
 *   - wp-cron.php URI (catches installs that run cron over HTTP without
 *     the constant being set before mu-plugins load).
 *   - /wp-json/* — REST API discovery + versioned endpoints that WP's
 *     own code polls at boot.
 *   - /wp-admin/admin-ajax.php?action=health-check-* — the Site Health
 *     tool's own probes.
 *
 * UA is intentionally not inspected: a WP install may run cron through
 * any client. IP is not inspected either — loopback-vs-external is not
 * reliable on managed hosts that proxy wp-cron through the frontend.
 *
 * @since 4.0.0
 */
class WordPressInternalsMatcher implements AllowlistMatcher {

	/**
	 * Whether PHP's DOING_CRON constant is defined and truthy.
	 *
	 * @var bool
	 */
	private $doing_cron;

	/**
	 * Capture the DOING_CRON state for the lifetime of this request.
	 *
	 * @param bool $doing_cron Pass the return of defined('DOING_CRON')&&DOING_CRON.
	 */
	public function __construct( $doing_cron ) {
		$this->doing_cron = (bool) $doing_cron;
	}

	/**
	 * {@inheritdoc}
	 */
	public function matches( $context ) {
		if ( $this->doing_cron ) {
			return true;
		}
		if ( ! is_array( $context ) || ! isset( $context['uri'] ) || ! is_string( $context['uri'] ) ) {
			return false;
		}
		$uri = $context['uri'];
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pre-WP hot path; wp_parse_url may not be available.
		$path = (string) parse_url( $uri, PHP_URL_PATH );
		$path = strtolower( $path );

		// wp-cron.php is canonically at the site root. Anchor the match
		// so an attacker can't bypass classification with a request to
		// /anything/wp-cron.php or /wp-cron.php.evil.
		if ( '/wp-cron.php' === $path ) {
			return true;
		}
		// Only allowlist the REST API discovery root. The full
		// `/wp-json/...` tree is the primary scraping surface for AI
		// crawlers — letting `/wp-json/wp/v2/posts` etc. bypass
		// classification would defeat the feature. Per-namespace
		// exemptions (e.g. WooCommerce's /wp-json/wc/) are the job of
		// their dedicated matchers later in the chain.
		if ( '/wp-json' === $path || '/wp-json/' === $path ) {
			return true;
		}
		if ( '/wp-admin/admin-ajax.php' === $path ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pre-WP hot path; wp_parse_url may not be available.
			$query = (string) parse_url( $uri, PHP_URL_QUERY );
			if ( '' !== $query ) {
				parse_str( $query, $args );
				if ( isset( $args['action'] )
					&& is_string( $args['action'] )
					&& in_array( $args['action'], self::healthCheckActions(), true ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Enumerated set of WordPress Site Health AJAX action names that we
	 * allowlist on `/wp-admin/admin-ajax.php`.
	 *
	 * Strict enumeration — not a `health-check*` prefix match — because a
	 * loose prefix lets a bot reach `?action=health-check-anything` and
	 * bypass classification on any plugin that registers an action with
	 * that prefix. Trade-off: a future WP version that adds a new
	 * `health-check-*` action will surface as benign rate-limit hits on
	 * Site Health probes until this list is refreshed; an obvious,
	 * fixable failure mode beats an invisible bypass.
	 *
	 * Sources covered (WP core + the older Site Health plugin
	 * actions WP merged or kept aliases for):
	 *   - wp-admin/includes/class-wp-site-health.php (5.2+)
	 *   - the legacy `health-check-test-*` synonyms
	 *
	 * @return array
	 */
	private static function healthCheckActions() {
		return array(
			// WP core (5.2+).
			'health-check-site-status',
			'health-check-site-status-result',
			'health-check-loopback-requests',
			'health-check-dotorg-communication',
			'health-check-background-updates',
			// Legacy / Site Health plugin synonyms still wired for back-compat.
			'health-check-test-loopback-requests',
			'health-check-test-rest-availability',
			'health-check-test-php-default-timezone',
			'health-check-test-https-status',
			'health-check-test-debug-enabled',
		);
	}
}
