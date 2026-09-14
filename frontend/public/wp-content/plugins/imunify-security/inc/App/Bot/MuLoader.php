<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App\Bot;

use CloudLinux\Imunify\App\Model\PluginConfig;

/**
 * Side-effecting bootstrap for the mu-plugin shim.
 *
 * Exposes two entry points:
 *   - run(): production entry. Reads WP_CONTENT_DIR + $_SERVER, builds
 *            a production Responder, defers to runWith().
 *   - runWith(): deterministic entry for tests. Takes the content dir,
 *                $_SERVER snapshot, and Responder as arguments so the
 *                whole wiring can be exercised under PHPUnit.
 *
 * Short-circuit order (cheapest / strongest first):
 *   1. Server gate — plugin_config.php::ai_bot_protection.
 *      Off or missing / malformed → pass through.
 *   2. Site-owner opt-out — IMUNIFY_AI_BOT_PROTECTION wp-config
 *      constant, default true per the parent epic. Defined and false
 *      → pass through.
 *   3. Site-owner opt-out — bot-settings.php::enabled (widget-written).
 *      False → pass through.
 *
 * Preset resolution (first match wins):
 *   a. IMUNIFY_AI_BOT_PROTECTION_PRESET constant (power user).
 *   b. bot-settings.php::preset if the site owner has explicitly
 *      picked one (widget).
 *   c. plugin_config.php::preset hoster default written by the agent.
 *   d. Preset::BALANCED.
 * Unknown values at any layer fall through to the next so an admin typo
 * never disables rate limiting or crashes the bootstrap.
 *
 * @since 4.0.0
 */
class MuLoader {

	/**
	 * Names of bot-ip-range providers treated as verified search engines.
	 *
	 * Kept here (not on IpRangeLookup) so the partition is trivially
	 * reviewable next to the pipeline wiring that uses it. Returned from
	 * a static method rather than an array-valued class constant because
	 * the latter is PHP 7.0+ syntax and the plugin must parse on PHP 5.6
	 * — an array `const` triggers a fatal parse error there, which the
	 * mu-plugin shim's runtime catch can never intercept.
	 *
	 * @return array
	 */
	private static function searchEngineProviders() {
		return array( 'google', 'bing', 'apple', 'duckduckgo', 'duckassistbot', 'meta' );
	}

	/**
	 * Names of bot-ip-range providers treated as verified AI crawlers.
	 *
	 * See searchEngineProviders() for the reason this is a method
	 * rather than a class constant.
	 *
	 * @return array
	 */
	private static function aiCrawlerProviders() {
		return array( 'anthropic', 'openai', 'meta', 'perplexity', 'google' );
	}

	/**
	 * Names of bot-ip-range providers treated as verified SEO crawlers.
	 *
	 * See searchEngineProviders() for the reason this is a method
	 * rather than a class constant.
	 *
	 * @return array
	 */
	private static function seoCrawlerProviders() {
		return array( 'ahrefs', 'barkrowler' );
	}

	/**
	 * Split the bundled bot-ip-range providers into the search-engine and
	 * AI-crawler lookups the Classifier consumes.
	 *
	 * The two membership tests are independent rather than mutually
	 * exclusive: a provider that serves both roles must land in both lookups.
	 * Google ships Googlebot from the same ranges it uses for GoogleOther /
	 * Gemini, so 'google' belongs to both sets; the disjoint UA token lists
	 * (ua-search-engines vs ua-ai-crawlers) keep any single request on
	 * exactly one Classifier branch.
	 *
	 * @param array $providers Map of provider name => data-file path.
	 * @return array Three entries: 'search_engines', 'ai_crawlers' and
	 *               'seo_crawlers', each a provider-name => file map.
	 */
	private static function partitionBotIpRanges( $providers ) {
		$search_engines = array();
		$ai_crawlers    = array();
		$seo_crawlers   = array();
		foreach ( $providers as $name => $file ) {
			if ( in_array( $name, self::searchEngineProviders(), true ) ) {
				$search_engines[ $name ] = $file;
			}
			if ( in_array( $name, self::aiCrawlerProviders(), true ) ) {
				$ai_crawlers[ $name ] = $file;
			}
			if ( in_array( $name, self::seoCrawlerProviders(), true ) ) {
				$seo_crawlers[ $name ] = $file;
			}
		}
		return array(
			'search_engines' => $search_engines,
			'ai_crawlers'    => $ai_crawlers,
			'seo_crawlers'   => $seo_crawlers,
		);
	}

	/**
	 * Production entry point — the shim calls this.
	 *
	 * @return void
	 */
	public static function run() {
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			return;
		}
		// The main plugin's autoloader isn't registered yet — we run at
		// muplugins_loaded, which is before the regular plugin file is
		// included. Register a minimal spl_autoload for the sibling
		// CloudLinux\Imunify\* classes we need so class resolution inside
		// the pipeline doesn't silently fail-open through the shim's
		// Throwable catch.
		self::registerAutoloader();
		BotLifecycle::registerCleanupHooks();
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		self::runWith( (string) WP_CONTENT_DIR, $_SERVER, new Responder( false ) );
	}

	/**
	 * Register a narrow PSR-0-ish autoloader rooted at this plugin's
	 * inc/ directory. Idempotent — safe to call on every request.
	 *
	 * @return void
	 */
	private static function registerAutoloader() {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		$inc_dir = dirname( dirname( __DIR__ ) );
		spl_autoload_register(
			function ( $class ) use ( $inc_dir ) {
				$prefix = 'CloudLinux\\Imunify\\';
				if ( 0 !== strpos( $class, $prefix ) ) {
					return;
				}
				$relative = substr( $class, strlen( $prefix ) );
				$file     = $inc_dir . '/' . str_replace( '\\', '/', $relative ) . '.php';
				// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( @is_readable( $file ) ) {
					include_once $file;
				}
			}
		);
		$registered = true;
	}

	/**
	 * Deterministic entry point exposed for tests.
	 *
	 * @param string    $wp_content_dir Absolute path to wp-content.
	 * @param array     $server         $_SERVER snapshot.
	 * @param Responder $responder      Response emitter.
	 * @return void
	 */
	public static function runWith( $wp_content_dir, $server, $responder ) {
		$cfg = self::loadPluginConfig( $wp_content_dir );
		if ( ! $cfg->isAiBotProtectionEnabled() ) {
			return;
		}
		if ( self::siteDisabledByConstant() ) {
			return;
		}

		$opt_out = OptOutFlag::load( $wp_content_dir );
		if ( ! $opt_out->isEnabled() ) {
			return;
		}

		$pipeline = self::buildPipeline(
			$wp_content_dir,
			self::resolvePreset( $opt_out, $cfg ),
			$responder,
			$opt_out->isStatsEnabled()
		);
		$pipeline->run( $server );
	}

	/**
	 * Site-owner opt-out via IMUNIFY_AI_BOT_PROTECTION wp-config constant.
	 *
	 * @return bool True iff the constant is defined and evaluates false.
	 */
	private static function siteDisabledByConstant() {
		return defined( 'IMUNIFY_AI_BOT_PROTECTION' )
			&& false === (bool) constant( 'IMUNIFY_AI_BOT_PROTECTION' );
	}

	/**
	 * Whether the current request is a WP-Cron run.
	 *
	 * WordPress's wp_doing_cron() layers the `wp_doing_cron` filter over the
	 * DOING_CRON constant; guarded with function_exists so the shim degrades to
	 * "not a cron run" when WP core's helper isn't loaded yet.
	 *
	 * @return bool
	 */
	private static function isDoingCron() {
		return function_exists( 'wp_doing_cron' ) && wp_doing_cron();
	}

	/**
	 * Optional override for the rate-limiter rolling window via a wp-config
	 * constant.  Returns null when the constant is not defined (so the
	 * RateLimiter falls back to its built-in WINDOW_SECONDS).
	 *
	 * @return int|null
	 */
	private static function resolveWindowSeconds() {
		if ( defined( 'IMUNIFY_RATE_LIMIT_WINDOW' ) ) {
			return (int) constant( 'IMUNIFY_RATE_LIMIT_WINDOW' );
		}
		return null;
	}

	/**
	 * Resolve the active preset via the shared chain in Preset::resolve().
	 *
	 * @param OptOutFlag   $opt_out Site-owner preference loaded by runWith().
	 * @param PluginConfig $cfg     Decoded plugin_config.php.
	 * @return string One of Preset::BALANCED/STRICT/MONITOR.
	 */
	private static function resolvePreset( $opt_out, PluginConfig $cfg ) {
		return Preset::resolve( $opt_out, $cfg );
	}

	/**
	 * Read plugin_config.php exactly once per request and return the
	 * resulting PluginConfig. Threaded through the gate check and
	 * preset resolution so the file isn't re-included on the hot path.
	 *
	 * Returns an indeterminate PluginConfig — rather than one that reads
	 * as cleanly disabled — when the file is missing, unreadable, or
	 * fails to parse, so MuPluginSelfHealer can tell "couldn't confirm
	 * the gate" apart from "hoster turned it off" instead of tearing
	 * down an already-installed shim on a transient read failure.
	 *
	 * @param string $wp_content_dir Absolute path to wp-content.
	 * @return PluginConfig
	 */
	public static function loadPluginConfig( $wp_content_dir ) {
		$path = rtrim( $wp_content_dir, '/' ) . '/imunify-security/plugin_config.php';
		clearstatcache( true );
		if ( ! is_readable( $path ) ) {
			return PluginConfig::indeterminate();
		}
		$raw = SafeInclude::load( $path );
		if ( ! is_array( $raw ) ) {
			return PluginConfig::indeterminate();
		}
		return PluginConfig::fromArray( $raw );
	}

	/**
	 * Optional override for the FCrDNS verifier's PTR / forward resolvers,
	 * supplied through the `imunify_security_bot_rdns_resolvers` filter.
	 *
	 * With no hook the filter returns the empty default, so production keeps
	 * RdnsVerifier's real dns_get_record resolvers. Hooking the filter
	 * requires code already running inside WordPress, which is the trust
	 * boundary the rest of the plugin assumes; it does not widen the
	 * attacker's reach.
	 *
	 * Malformed returns degrade safely: a non-array result yields no
	 * override, and a non-callable 'reverse'/'forward' is ignored by
	 * RdnsVerifier, which falls back to its dns_get_record default.
	 *
	 * @return array Map with optional 'reverse' / 'forward' callables.
	 */
	private static function rdnsResolverOverrides() {
		if ( ! function_exists( 'apply_filters' ) ) {
			return array();
		}
		$overrides = apply_filters( 'imunify_security_bot_rdns_resolvers', array() );
		return is_array( $overrides ) ? $overrides : array();
	}

	/**
	 * Build the production Pipeline over the bundled classifier data.
	 *
	 * @param string    $wp_content_dir Absolute path to wp-content.
	 * @param string    $preset         Preset identifier.
	 * @param Responder $responder      Response emitter.
	 * @param bool      $stats_enabled  Whether to attach the hourly stats recorder.
	 * @return Pipeline
	 */
	private static function buildPipeline( $wp_content_dir, $preset, $responder, $stats_enabled ) {
		$bundled_dir = self::pluginDir() . '/inc/App/Bot/data';
		$overlay_dir = self::overlayDir( $wp_content_dir );
		$bundled     = new BundledData( $bundled_dir, $overlay_dir );

		$partition      = self::partitionBotIpRanges( $bundled->providersIn( 'bot-ip-ranges' ) );
		$search_engines = $partition['search_engines'];
		$ai_crawlers    = $partition['ai_crawlers'];
		$seo_crawlers   = $partition['seo_crawlers'];

		$cdn = new CdnDetector(
			new IpRangeLookup( $bundled->providersIn( 'cdn-ip-ranges' ) )
		);

		$signatures = new UserAgentSignatures(
			array(
				UserAgentSignatures::CATEGORY_MALICIOUS   => $bundled->pathFor( 'signatures/ua-malicious.php' ),
				UserAgentSignatures::CATEGORY_SEARCH_ENGINE => $bundled->pathFor( 'signatures/ua-search-engines.php' ),
				UserAgentSignatures::CATEGORY_SEO_CRAWLER => $bundled->pathFor( 'signatures/ua-seo-crawlers.php' ),
				UserAgentSignatures::CATEGORY_AI_CRAWLER  => $bundled->pathFor( 'signatures/ua-ai-crawlers.php' ),
			)
		);

		$datacenter = new DatacenterDetector(
			new IpRangeLookup( $bundled->providersIn( 'datacenter-ip-ranges' ) )
		);

		// Share one RealIpResolver between Classifier and Pipeline so
		// the resolved client IP is consistent across classification
		// and rate-limit counter keying.
		$real_ip_resolver = new RealIpResolver( $cdn );

		global $wpdb;
		$db_storage   = isset( $wpdb ) ? DbStorageFactory::detect( $wpdb ) : DbStorageFactory::nullPair();
		$rate_limiter = new RateLimiter(
			$db_storage['counter'],
			$db_storage['block'],
			$preset,
			null,
			self::resolveWindowSeconds()
		);

		$rdns_overrides = self::rdnsResolverOverrides();
		$rdns_reverse   = isset( $rdns_overrides['reverse'] ) ? $rdns_overrides['reverse'] : null;
		$rdns_forward   = isset( $rdns_overrides['forward'] ) ? $rdns_overrides['forward'] : null;

		$make_rdns_verifier = function ( $suffix_file, $key_prefix ) use ( $db_storage, $bundled, $rdns_reverse, $rdns_forward ) {
			return new RdnsVerifier(
				$db_storage['counter'],
				RdnsVerifier::loadProvidersFromFile( $bundled->pathFor( $suffix_file ) ),
				$rdns_reverse,
				$rdns_forward,
				$key_prefix
			);
		};

		$rdns_verifier     = $make_rdns_verifier( 'signatures/ua-rdns-suffixes.php', 'rdns:se:' );
		$ai_rdns_verifier  = $make_rdns_verifier( 'signatures/ua-ai-rdns-suffixes.php', 'rdns:ai:' );
		$seo_rdns_verifier = $make_rdns_verifier( 'signatures/ua-seo-rdns-suffixes.php', 'rdns:seo:' );

		$classifier = new Classifier(
			$signatures,
			$real_ip_resolver,
			new IpRangeLookup( $search_engines ),
			new IpRangeLookup( $ai_crawlers ),
			new IpRangeLookup( $seo_crawlers ),
			$datacenter,
			$rdns_verifier,
			$ai_rdns_verifier,
			$seo_rdns_verifier
		);

		// Share the storage between rate limiter and daily counter so
		// the widget reads the same keyspace the pipeline writes.
		$daily_counter = new DailyCounter( $db_storage['counter'] );

		// Durable hourly rollup + per-IP detail for the dashboard. Attached
		// only when the site owner has stats capture on and a DB handle is
		// available; otherwise the pipeline records no detailed stats (fail-open).
		$stats_storage    = $stats_enabled ? HourlyStatsStorage::forGlobalWpdb() : null;
		$ip_stats_storage = $stats_enabled ? HourlyIpStatsStorage::forGlobalWpdb() : null;

		$allowlist = new RequestAllowlist(
			array(
				new WordPressInternalsMatcher( self::isDoingCron() ),
				new WooCommerceMatcher(),
				new MonitoringUaMatcher(),
			)
		);

		return new Pipeline(
			$classifier,
			$rate_limiter,
			$allowlist,
			$responder,
			$real_ip_resolver,
			$daily_counter,
			$stats_storage,
			$ip_stats_storage
		);
	}

	/**
	 * Resolve the main plugin directory whether WP_PLUGIN_DIR is defined yet or not.
	 *
	 * @return string
	 */
	private static function pluginDir() {
		if ( defined( 'IMUNIFY_SECURITY_PATH' ) ) {
			return (string) IMUNIFY_SECURITY_PATH;
		}
		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			return (string) WP_PLUGIN_DIR . '/imunify-security';
		}
		// Dev / test fallback — derive from this file's location.
		// __DIR__ = .../inc/App/Bot → go up three levels to plugin root.
		// Using nested dirname() to stay PHP 5.6 compatible (the 2-arg
		// form was added in PHP 7.0).
		return dirname( dirname( dirname( __DIR__ ) ) );
	}

	/**
	 * Absolute path to the overlay directory that SignatureRefresher writes to.
	 *
	 * @param string $wp_content_dir Absolute path to wp-content.
	 * @return string
	 */
	private static function overlayDir( $wp_content_dir ) {
		return rtrim( $wp_content_dir, '/' ) . '/imunify-security/bot-data';
	}
}
