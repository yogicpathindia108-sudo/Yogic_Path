<?php
/**
 * Visual Builder performance logger.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\Performance;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ActionScheduler;
use stdClass;
use ET\Builder\Framework\Utility\Conditions;
use ET\Builder\VisualBuilder\SettingsData\SettingsData;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Provides helpers for Visual Builder performance testing, logging, and debugging.
 *
 * This class handles performance tracking, including request logging, OPcache monitoring,
 * and remote request blocking. It is intended for internal development use and should
 * be activated only when performance testing is required to avoid overhead on core Divi requests.
 *
 * @since ??
 */
class PerformanceLogger {
	/**
	 * Environment variable for enabling performance logging.
	 *
	 * @since ??
	 */
	private const PERF_ENABLE_ENV = 'DIVI_PERF_E2E_ENABLE';

	/**
	 * Constant for enabling performance logging.
	 *
	 * @since ??
	 */
	private const PERF_ENABLE_CONST = 'DIVI_PERF_E2E_ENABLE';

	/**
	 * Environment variable for enabling query param activation.
	 *
	 * @since ??
	 */
	private const PERF_ENABLE_QUERY_PARAM_ENV = 'DIVI_PERF_E2E_ENABLE_QUERY_PARAM';

	/**
	 * Constant for enabling query param activation.
	 *
	 * @since ??
	 */
	private const PERF_ENABLE_QUERY_PARAM_CONST = 'DIVI_PERF_E2E_ENABLE_QUERY_PARAM';

	/**
	 * Query parameter to enable performance tracking for a single request.
	 *
	 * @since ??
	 */
	private const PERF_MODE_QUERY_PARAM = 'et_fb_performance_tracking';

	/**
	 * Query parameter to block remote requests for a single request.
	 *
	 * @since ??
	 */
	private const BLOCK_REMOTE_QUERY_PARAM = 'et_fb_block_remote_requests';

	/**
	 * Query parameter to cache Google Fonts requests for a single request.
	 *
	 * @since ??
	 */
	private const CACHE_FONTS_QUERY_PARAM = 'et_fb_cache_fonts';

	/**
	 * Environment variable for blocking remote requests globally.
	 *
	 * @since ??
	 */
	private const BLOCK_REMOTE_ENV = 'DIVI_PERF_E2E_BLOCK_REMOTE_REQUESTS';

	/**
	 * Constant for blocking remote requests globally.
	 *
	 * @since ??
	 */
	private const BLOCK_REMOTE_CONST = 'DIVI_PERF_E2E_BLOCK_REMOTE_REQUESTS';

	/**
	 * Environment variable for logging blocked URLs.
	 *
	 * @since ??
	 */
	private const LOG_BLOCKED_ENV = 'DIVI_PERF_E2E_LOG_BLOCKED_URLS';

	/**
	 * Constant for logging blocked URLs.
	 *
	 * @since ??
	 */
	private const LOG_BLOCKED_CONST = 'DIVI_PERF_E2E_LOG_BLOCKED_URLS';

	/**
	 * Environment variable for logging all HTTP requests.
	 *
	 * @since ??
	 */
	private const LOG_ALL_REQUESTS_ENV = 'DIVI_PERF_E2E_LOG_ALL_REQUESTS';

	/**
	 * Constant for logging all HTTP requests.
	 *
	 * @since ??
	 */
	private const LOG_ALL_REQUESTS_CONST = 'DIVI_PERF_E2E_LOG_ALL_REQUESTS';

	/**
	 * Environment variable for logging REST API requests.
	 *
	 * @since ??
	 */
	private const LOG_REST_API_ENV = 'DIVI_PERF_E2E_LOG_REST_API';

	/**
	 * Constant for logging REST API requests.
	 *
	 * @since ??
	 */
	private const LOG_REST_API_CONST = 'DIVI_PERF_E2E_LOG_REST_API';

	/**
	 * Environment variable for enabling memory debugging.
	 *
	 * @since ??
	 */
	private const DEBUG_MEMORY_ENV = 'DIVI_PERF_E2E_DEBUG_MEMORY_USAGE';

	/**
	 * Constant for enabling memory debugging.
	 *
	 * @since ??
	 */
	private const DEBUG_MEMORY_CONST = 'DIVI_PERF_E2E_DEBUG_MEMORY_USAGE';

	/**
	 * Environment variable for logging included files.
	 *
	 * @since ??
	 */
	private const DEBUG_INCLUDED_FILES_ENV = 'DIVI_PERF_E2E_DEBUG_INCLUDED_FILES';

	/**
	 * Constant for logging included files.
	 *
	 * @since ??
	 */
	private const DEBUG_INCLUDED_FILES_CONST = 'DIVI_PERF_E2E_DEBUG_INCLUDED_FILES';

	/**
	 * Environment variable for disabling WooCommerce REST API initialization.
	 *
	 * @since ??
	 */
	private const DISABLE_WC_REST_ENV = 'DIVI_PERF_E2E_DISABLE_WC_REST_API';

	/**
	 * Constant for disabling WooCommerce REST API initialization.
	 *
	 * @since ??
	 */
	private const DISABLE_WC_REST_CONST = 'DIVI_PERF_E2E_DISABLE_WC_REST_API';

	/**
	 * Environment variable for caching YouTube requests.
	 *
	 * @since ??
	 */
	private const CACHE_YOUTUBE_ENV = 'DIVI_PERF_E2E_CACHE_YOUTUBE';

	/**
	 * Constant for caching YouTube requests.
	 *
	 * @since ??
	 */
	private const CACHE_YOUTUBE_CONST = 'DIVI_PERF_E2E_CACHE_YOUTUBE';

	/**
	 * Environment variable for caching Google Fonts requests.
	 *
	 * @since ??
	 */
	private const CACHE_FONTS_ENV = 'DIVI_PERF_E2E_CACHE_FONTS';

	/**
	 * Constant for caching Google Fonts requests.
	 *
	 * @since ??
	 */
	private const CACHE_FONTS_CONST = 'DIVI_PERF_E2E_CACHE_FONTS';

	/**
	 * Environment variable for blocking Google Fonts requests.
	 *
	 * @since ??
	 */
	private const BLOCK_FONTS_ENV = 'DIVI_PERF_E2E_BLOCK_FONTS_GOOGLEAPIS';

	/**
	 * Constant for blocking Google Fonts requests.
	 *
	 * @since ??
	 */
	private const BLOCK_FONTS_CONST = 'DIVI_PERF_E2E_BLOCK_FONTS_GOOGLEAPIS';

	/**
	 * Environment variable for custom log path.
	 *
	 * @since ??
	 */
	private const LOG_PATH_ENV = 'DIVI_PERF_E2E_LOG_PATH';

	/**
	 * Constant for custom log path.
	 *
	 * @since ??
	 */
	private const LOG_PATH_CONST = 'DIVI_PERF_E2E_LOG_PATH';

	/**
	 * Environment variable for logging settings data during app load.
	 *
	 * @since ??
	 */
	private const PERF_LOG_APP_LOAD_ENV = 'DIVI_PERF_E2E_LOG_APP_LOAD';

	/**
	 * Constant for logging settings data during app load.
	 *
	 * @since ??
	 */
	private const PERF_LOG_APP_LOAD_CONST = 'DIVI_PERF_E2E_LOG_APP_LOAD';

	/**
	 * Environment variable for logging settings data after app load.
	 *
	 * @since ??
	 */
	private const PERF_LOG_AFTER_APP_LOAD_ENV = 'DIVI_PERF_E2E_LOG_AFTER_APP_LOAD';

	/**
	 * Constant for logging settings data after app load.
	 *
	 * @since ??
	 */
	private const PERF_LOG_AFTER_APP_LOAD_CONST = 'DIVI_PERF_E2E_LOG_AFTER_APP_LOAD';

	/**
	 * REST namespace for performance routes.
	 *
	 * @since ??
	 */
	private const REST_NAMESPACE = 'divi/v1';

	/**
	 * REST route for performance health check.
	 *
	 * @since ??
	 */
	private const REST_HEALTH_ROUTE = '/performance/health';

	/**
	 * REST route for retrieving blocked requests.
	 *
	 * @since ??
	 */
	private const REST_BLOCKED_REQUESTS_ROUTE = '/performance/blocked-requests';

	/**
	 * Maximum number of blocked requests to keep in memory.
	 *
	 * @since ??
	 */
	private const BLOCKED_REQUESTS_CACHE_LIMIT = 100;

	/**
	 * Collects blocked requests during the current PHP lifecycle.
	 *
	 * @since ??
	 *
	 * @var array<string>
	 */
	private static array $_blocked_requests = [];

	/**
	 * Recorded requests during the current PHP lifecycle.
	 *
	 * @since ??
	 *
	 * @var array<string, array{count: int, paths: array<string, array{count: int, total_duration: float}>}>
	 */
	private static array $_recorded_requests = [];

	/**
	 * Stack of HTTP request start times.
	 *
	 * @since ??
	 *
	 * @var array<array{url: string, start: float}>
	 */
	private static array $_http_start_stack = [];

	/**
	 * REST API request start times.
	 *
	 * @since ??
	 *
	 * @var array<string, float>
	 */
	private static array $_rest_start_times = [];

	/**
	 * Track whether the logger has been initialized.
	 *
	 * @since ??
	 *
	 * @var bool
	 */
	private static bool $_initialized = false;

	/**
	 * Track which settings data hooks have been registered.
	 *
	 * @since ??
	 *
	 * @var array<string, bool>
	 */
	private static array $_settings_data_hooks_registered = [
		'app_load'       => false,
		'after_app_load' => false,
	];

	/**
	 * Modules initialization start time.
	 *
	 * @since ??
	 *
	 * @var float
	 */
	private float $modules_start;

	/**
	 * Register all shortcodes start time.
	 *
	 * @since ??
	 *
	 * @var float
	 */
	private float $register_all_start;

	/**
	 * Previously included files during the PHP lifecycle.
	 *
	 * @since ??
	 *
	 * @var string[]|null
	 */
	private static ?array $_included_before = null;

	/**
	 * OPcache snapshot before a certain stage.
	 *
	 * @since ??
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $_opcache_before = null;

	/**
	 * Settings data items start times.
	 *
	 * @since ??
	 *
	 * @var array<string, float>
	 */
	private array $items_start = [];

	/**
	 * Cache for configuration flags to avoid repeated checks.
	 *
	 * @since ??
	 *
	 * @var array<string, bool|null>
	 */
	private static array $_config_cache = [];

	/**
	 * Initialize the performance logger.
	 *
	 * This should be called early (e.g., in bootstrap.php) to capture startup metrics.
	 * It checks if performance tracking is enabled via constants, env vars, or query parameters.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function initialize(): void {
		if ( self::$_initialized ) {
			return;
		}

		if ( ! self::_should_initialize() ) {
			return;
		}

		self::$_initialized = true;

		if ( true === self::_should_write_log_header() ) {
			self::_log( '---| Perf Logs |---' );
		}

		$logger = new self();
		$logger->load();

		if ( true === self::_should_disable_wc_rest() ) {
			self::_register_wc_rest_cleanup();

			if ( true === self::_is_vb_context() ) {
				self::_register_wc_cart_fragments_cleanup();
			}
		}

		self::_register_action_scheduler_async_runner_block();
	}

	/**
	 * Determine whether performance logging is active for the current request.
	 *
	 * @since ??
	 *
	 * @return bool True if performance logging is enabled.
	 */
	public static function is_enabled(): bool {
		return self::_should_initialize();
	}

	/**
	 * Register filters and endpoints for performance testing.
	 *
	 * This method sets up all hooks required to track performance metrics,
	 * block remote requests, and provide diagnostic REST endpoints.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function load(): void {
		if ( ! self::_should_initialize() ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( ! empty( $request_uri ) && true === self::_should_log_request_processing() ) {
			self::_log( sprintf( '[processing] %s', esc_url_raw( home_url( $request_uri ) ) ) );
		}

		// Track all outgoing HTTP requests and optionally block them.
		add_filter( 'pre_http_request', [ $this, 'maybe_block_remote_request' ], 10, 3 );
		add_filter( 'http_response', [ $this, 'maybe_log_http_response' ], 10, 3 );

		self::_prime_update_transients();
		self::_register_update_transient_overrides();

		// Register app-load settings data timing hooks when enabled.
		if ( true === self::_should_log_app_load() ) {
			$this->_register_settings_data_item_hooks( 'app_load' );
		}

		add_action( 'divi_visual_builder_settings_data_register_item', [ $this, 'maybe_register_settings_data_item_hooks' ], 10, 2 );

		// Optionally track REST API requests and after-app-load logging.
		if ( true === self::_should_log_rest_api() || true === self::_should_log_after_app_load() || true === self::_should_log_all_requests() ) {
			add_filter( 'rest_pre_dispatch', [ $this, 'maybe_log_rest_api' ], 10, 3 );
			add_filter( 'rest_post_dispatch', [ $this, 'maybe_log_rest_api_post' ], 10, 3 );
		}

		// Register diagnostic debug hooks.
		if ( true === self::_should_debug_memory() ) {
			self::_register_memory_debug_hooks();
		}

		if ( true === self::_should_debug_included_files() ) {
			self::_register_included_files_debug_hooks();
		}

		// Log summary of all requests at the end of the lifecycle.
		if ( true === self::_should_log_all_requests() ) {
			add_action( 'shutdown', [ $this, 'log_requests_summary' ] );
		}

		// Ensure REST routes are registered even if initialization happens after 'rest_api_init'.
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );

		if ( did_action( 'rest_api_init' ) ) {
			$this->register_routes();
		}
	}

	/**
	 * Register REST endpoints to surface performance test state and diagnostics.
	 *
	 * Provides endpoints for health checks and blocked request lists.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_HEALTH_ROUTE,
			[
				'methods'             => 'GET',
				'permission_callback' => [ $this, 'can_access_performance_routes' ],
				'callback'            => [ $this, 'handle_health_check' ],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_BLOCKED_REQUESTS_ROUTE,
			[
				'methods'             => 'GET',
				'permission_callback' => [ $this, 'can_access_performance_routes' ],
				'callback'            => [ $this, 'handle_blocked_requests' ],
			]
		);
	}

	/**
	 * Determine whether the request can access performance routes.
	 *
	 * Only allows access if:
	 * 1. Performance test mode is active (e.g., via constant/env).
	 * 2. OR the user is logged in as an administrator.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return bool True if access is allowed.
	 */
	public function can_access_performance_routes( WP_REST_Request $request ): bool {
		// If explicitly in performance test mode via constant/env, allow access.
		if ( true === self::_is_performance_test_mode() ) {
			return true;
		}

		// Otherwise, require administrator privileges.
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return self::_has_valid_rest_nonce( $request );
	}

	/**
	 * Provide a basic health response for performance tests.
	 *
	 * Returns current performance configuration, database health, and optionally
	 * blocked requests list. Can also clear local caches if requested.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return array<string, mixed> Health data.
	 */
	public function handle_health_check( WP_REST_Request $request ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required for REST callback signature.
		$should_include   = self::_should_include_blocked_requests( $request );
		$blocked_requests = $should_include ? self::_get_blocked_requests() : [];
		$blocked_count    = count( self::_get_blocked_requests() );
		$db_health        = self::_get_db_health();
		$clear_cache      = self::_get_bool_from_value( $request->get_param( 'clear_cache' ) );
		$is_perf_run      = self::_get_bool_from_value( $request->get_param( 'is_performance_run' ) );

		if ( true === $clear_cache && true === $is_perf_run ) {
			$cache_dir = self::_get_cache_dir();

			if ( is_dir( $cache_dir ) ) {
				$patterns = [
					$cache_dir . '/*.json',
					$cache_dir . '/fonts/*.json',
					$cache_dir . '/youtube/*.json',
				];

				foreach ( $patterns as $pattern ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_glob -- Local cache only.
					$files = glob( $pattern );

					if ( is_array( $files ) ) {
						foreach ( $files as $file ) {
							if ( is_file( $file ) ) {
								// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink -- Local cache only.
								unlink( $file );
							}
						}
					}
				}
			}
		}

		return [
			'performance_test_mode' => self::_is_performance_test_mode(),
			'block_remote_requests' => self::_should_block_remote_requests(),
			'blocked_requests'      => $blocked_requests,
			'blocked_count'         => $blocked_count,
			'db_connected'          => $db_health['connected'],
			'db_error'              => $db_health['error'],
			'cache_cleared'         => true === $clear_cache && true === $is_perf_run,
		];
	}

	/**
	 * Provide blocked request information for diagnostics.
	 *
	 * Returns the list of all unique remote URLs blocked during the current session.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return array<string, mixed> Blocked requests data.
	 */
	public function handle_blocked_requests( WP_REST_Request $request ): array {
		$should_clear     = self::_should_clear_blocked_requests( $request );
		$blocked_requests = self::_get_blocked_requests();

		if ( true === $should_clear ) {
			self::_clear_blocked_requests();
		}

		return [
			'blocked_requests' => $blocked_requests,
			'blocked_count'    => count( $blocked_requests ),
		];
	}

	/**
	 * Block remote requests during performance tests.
	 *
	 * Intercepts `pre_http_request` to block outgoing requests to 3rd party domains
	 * during performance testing, helping to ensure consistent test results.
	 * Also handles caching for YouTube and Google Fonts requests.
	 *
	 * @since ??
	 *
	 * @param false|array|WP_Error $preempt     Preempted response.
	 * @param array                $parsed_args Parsed arguments.
	 * @param string               $url          Request URL.
	 *
	 * @return false|array|WP_Error
	 */
	public function maybe_block_remote_request( $preempt, array $parsed_args, string $url ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required for filter signature.
		// Always block WordPress.org translation checks as they are frequent and unnecessary for testing.
		if ( str_starts_with( $url, 'https://api.wordpress.org/translations/core/' ) ) {
			if ( true === self::_should_log_request_processing() ) {
				self::_log( sprintf( '[**blocked**] %s', $url ) );
			}
			return [
				'headers'  => [
					'content-type' => 'application/json; charset=utf-8',
				],
				'body'     => wp_json_encode( [ 'translations' => [] ] ),
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
				'filename' => null,
			];
		}

		if ( true === self::_is_wporg_core_version_request( $url ) ) {
			if ( true === self::_should_log_request_processing() ) {
				self::_log( sprintf( '[**blocked**] %s', $url ) );
			}
			return self::_build_wporg_core_version_response();
		}

		if ( true === self::_is_wporg_theme_update_request( $url ) ) {
			if ( true === self::_should_log_request_processing() ) {
				self::_log( sprintf( '[**blocked**] %s', $url ) );
			}
			return self::_build_wporg_theme_update_response();
		}

		if ( true === self::_is_wporg_plugin_update_request( $url ) ) {
			if ( true === self::_should_log_request_processing() ) {
				self::_log( sprintf( '[**blocked**] %s', $url ) );
			}
			return self::_build_wporg_plugin_update_response();
		}

		// Handle YouTube request caching.
		if ( true === self::_should_cache_youtube_requests() && true === self::_is_youtube_request( $url ) ) {
			$cached = self::_get_youtube_cache( $url );

			if ( false !== $cached ) {
				if ( true === self::_should_log_request_processing() ) {
					self::_log( sprintf( '[youtube] cache hit: %s', md5( $url ) ) );
				}

				return $cached;
			}

			// For cache miss, we allow the request to proceed even if blocking is enabled.
			// This is to allow initial population of the cache.
			return $preempt;
		}

		if ( true === self::_should_cache_fonts_requests() && true === self::_is_fonts_css_request( $url ) ) {
			$cached = self::_get_fonts_css_cache( $url );

			if ( false !== $cached ) {
				if ( true === self::_should_log_request_processing() ) {
					self::_log( sprintf( '[fonts] css cache hit: %s', esc_url_raw( $url ) ) );
				}
				return $cached;
			}

			if ( true === self::_should_block_google_fonts() ) {
				if ( true === self::_should_log_blocked_requests() ) {
					self::_record_blocked_request( $url );
					self::_log( sprintf( '[*blocked*] request: %s', esc_url_raw( $url ) ) );
				}

				return [
					'headers'  => [],
					'body'     => '',
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}

			return $preempt;
		}

		if ( true === self::_should_block_google_fonts() && true === self::_is_google_fonts_host( $url ) ) {
			if ( true === self::_should_log_blocked_requests() ) {
				self::_record_blocked_request( $url );
				self::_log( sprintf( '[*blocked*] request: %s', esc_url_raw( $url ) ) );
			}

			return [
				'headers'  => [],
				'body'     => '',
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
				'filename' => null,
			];
		}

		// Track start time for non-blocked requests.
		self::$_http_start_stack[] = [
			'url'   => $url,
			'start' => microtime( true ),
		];

		if ( true !== self::_should_block_remote_requests() ) {
			return $preempt;
		}

		if ( true === self::_should_skip_admin_blocking() ) {
			return $preempt;
		}

		if ( true === self::_is_youtube_request( $url ) && true !== self::_should_cache_youtube_requests() ) {
			if ( true === self::_should_log_blocked_requests() ) {
				self::_record_blocked_request( $url );
				self::_log( sprintf( '[*blocked*] request: %s', esc_url_raw( $url ) ) );
			}

			return [
				'headers'  => [],
				'body'     => '',
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
				'filename' => null,
			];
		}

		if ( true === self::_is_google_fonts_host( $url ) && true !== self::_should_cache_fonts_requests() ) {
			if ( true === self::_should_log_blocked_requests() ) {
				self::_record_blocked_request( $url );
				self::_log( sprintf( '[*blocked*] request: %s', esc_url_raw( $url ) ) );
			}

			return [
				'headers'  => [],
				'body'     => '',
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
				'filename' => null,
			];
		}

		// Handle Google Fonts request caching.
		if ( true === self::_should_cache_fonts_requests() && true === self::_is_fonts_request( $url ) ) {
			$cached = self::_get_fonts_cache( $url );

			if ( false !== $cached ) {
				if ( true === self::_should_log_request_processing() ) {
					self::_log( sprintf( '[fonts] cache hit: %s', esc_url_raw( $url ) ) );
				}

				return $cached;
			}

			// For cache miss, we allow the request to proceed even if blocking is enabled.
			return $preempt;
		}

		// Allow local requests.
		if ( true === self::_is_local_request( $url ) ) {
			return $preempt;
		}

		// Record and block all other remote requests.
		if ( true === self::_should_log_blocked_requests() ) {
			self::_record_blocked_request( $url );
			self::_log( sprintf( '[*blocked*] request: %s', esc_url_raw( $url ) ) );
		}

		return new WP_Error(
			'divi_vb_performance_blocked_request',
			sprintf( 'Blocked remote request during performance testing: %s', esc_url_raw( $url ) )
		);
	}

	/**
	 * Determine whether remote blocking should be skipped for admin requests.
	 *
	 * @since ??
	 *
	 * @return bool True if the current request is an admin-related screen.
	 */
	private static function _should_skip_admin_blocking(): bool {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( true === is_admin() ) {
			return true;
		}

		if ( '' !== $request_uri && ( str_contains( $request_uri, '/wp-login.php' ) || str_contains( $request_uri, '/wp-admin/' ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Intercept and log internal REST API calls to the divi/v1 namespace.
	 *
	 * Tracks duration and registers settings data timing hooks for
	 * after-app-load requests.
	 *
	 * @since ??
	 *
	 * @param mixed           $result  Response to send to the client.
	 * @param WP_REST_Server  $server  REST server instance.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return mixed The (modified) result.
	 */
	public function maybe_log_rest_api( $result, WP_REST_Server $server, WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required for REST filter signature.
		$route = $request->get_route();

		if ( ! str_contains( $route, '/' . self::REST_NAMESPACE ) ) {
			return $result;
		}

		self::$_rest_start_times[ spl_object_hash( $request ) ] = microtime( true );

		if ( str_contains( $route, '/divi/v1/settings-data/after-app-load' ) ) {

			if ( true === self::_should_log_after_app_load() ) {
				$this->_register_settings_data_item_hooks( 'after_app_load' );
			}

			add_action( 'divi_visual_builder_settings_data_before_register_all_shortcodes', [ $this, 'log_before_register_all_shortcodes' ] );
			add_action( 'divi_visual_builder_settings_data_after_register_all_shortcodes', [ $this, 'log_after_register_all_shortcodes' ] );

			add_action( 'divi_visual_builder_settings_data_before_get_shortcodeModuleDefinitions', [ $this, 'log_before_item' ] );
			add_action( 'divi_visual_builder_settings_data_after_get_shortcodeModuleDefinitions', [ $this, 'log_after_item' ] );

			add_action( 'divi_visual_builder_before_load_module_types', [ $this, 'log_before_item' ] );
			add_action( 'divi_visual_builder_after_load_module_types', [ $this, 'log_after_item' ] );

			add_action( 'divi_visual_builder_before_load_module_files', [ $this, 'log_before_item' ] );
			add_action( 'divi_visual_builder_after_load_module_files', [ $this, 'log_after_item' ] );

			add_action( 'divi_visual_builder_before_load_woo_module_files', [ $this, 'log_before_item' ] );
			add_action( 'divi_visual_builder_after_load_woo_module_files', [ $this, 'log_after_item' ] );

			add_action( 'divi_visual_builder_settings_data_before_get_structureModuleDefinitions', [ $this, 'log_before_item' ] );
			add_action( 'divi_visual_builder_settings_data_after_get_structureModuleDefinitions', [ $this, 'log_after_item' ] );

			add_action( 'divi_visual_builder_settings_data_before_get_offCanvas', [ $this, 'log_before_item' ] );
			add_action( 'divi_visual_builder_settings_data_after_get_offCanvas', [ $this, 'log_after_item' ] );

			add_action( 'divi_visual_builder_before_get_shortcode_module_definitions', [ $this, 'log_before_modules' ] );
			add_filter( 'divi_visual_builder_settings_data_after_app_load', [ $this, 'log_after_modules' ] );
		}

		return $result;
	}

	/**
	 * Log the start of module loading.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function log_before_modules(): void {
		if ( true !== self::_should_log_after_app_load() ) {
			return;
		}

		$this->modules_start = microtime( true );
	}

	/**
	 * Log the end of module loading and its duration.
	 *
	 * @since ??
	 *
	 * @param array $data Module data.
	 *
	 * @return array The original module data.
	 */
	public function log_after_modules( $data ): array {
		if ( true !== self::_should_log_after_app_load() ) {
			return $data;
		}

		if ( isset( $this->modules_start ) ) {
			$duration = ( microtime( true ) - $this->modules_start ) * 1000;
			self::_log( sprintf( '[callback] shortcode_module_definitions - [%dms]', (int) $duration ) );
		}

		return $data;
	}

	/**
	 * Log the start of shortcode registration.
	 *
	 * Also captures initial OPcache and included file state for delta reporting.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function log_before_register_all_shortcodes(): void {
		if ( true !== self::_should_log_after_app_load() ) {
			return;
		}

		self::_log( '[*start*] register_all_shortcodes' );
		$this->register_all_start = microtime( true );
		self::$_opcache_before    = self::_get_opcache_snapshot();
		self::$_included_before   = get_included_files();
	}

	/**
	 * Log the end of shortcode registration and delta metrics.
	 *
	 * Reports duration, newly included files, and OPcache changes.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function log_after_register_all_shortcodes(): void {
		if ( true !== self::_should_log_after_app_load() ) {
			return;
		}

		if ( isset( $this->register_all_start ) ) {
			$duration      = ( microtime( true ) - $this->register_all_start ) * 1000;
			$opcache_after = self::_get_opcache_snapshot();

			$opcache_msg = '';
			if ( self::$_opcache_before && $opcache_after ) {
				$hits_diff   = $opcache_after['hits'] - self::$_opcache_before['hits'];
				$misses_diff = $opcache_after['misses'] - self::$_opcache_before['misses'];
				$opcache_msg = sprintf( '[OPcache] +%d hits, +%d misses', $hits_diff, $misses_diff );

				// Log full status only if there were misses to help identify why.
				if ( 0 < $misses_diff ) {
					self::_log( sprintf( '[OPcache] Status: %d/%d scripts, restarts: %d OOM, %d hash, %d manual', $opcache_after['num_scripts'], $opcache_after['max_scripts'], $opcache_after['oom'], $opcache_after['hash_restarts'], $opcache_after['manual_restart'] ) );
				}

				if ( 0 < $misses_diff && is_array( self::$_included_before ) && self::_should_debug_included_files() ) {
					$included_after = get_included_files();
					$newly_included = array_diff( $included_after, self::$_included_before );
					self::_log( sprintf( '[New] included during register_all_shortcodes (%d files):', count( $newly_included ) ) );

					foreach ( $newly_included as $file ) {
						self::_log( sprintf( 'INC: %s', $file ) );
					}
				}
			}

			self::_log( sprintf( '[*done*] register_all_shortcodes - [%dms]%s', (int) $duration, $opcache_msg ) );
		}
	}

	/**
	 * Log the start of a settings data item processing.
	 *
	 * @since ??
	 *
	 * @param string $usage Usage of the settings data. 'app_load' or 'after_app_load'.
	 *
	 * @return void
	 */
	public function log_before_item( string $usage = '' ): void {
		if ( true !== self::_should_log_settings_data_usage( $usage ) ) {
			return;
		}

		$name                      = str_replace( [ 'divi_visual_builder_settings_data_before_get_', 'divi_visual_builder_settings_data_after_get_', 'divi_visual_builder_before_load_', 'divi_visual_builder_after_load_' ], '', current_action() );
		$key                       = $usage . ':' . $name;
		$this->items_start[ $key ] = microtime( true );
	}

	/**
	 * Log the end of a settings data item processing and its duration.
	 *
	 * @since ??
	 *
	 * @param string $usage Usage of the settings data. 'app_load' or 'after_app_load'.
	 *
	 * @return void
	 */
	public function log_after_item( string $usage = '' ): void {
		if ( true !== self::_should_log_settings_data_usage( $usage ) ) {
			return;
		}

		$name = str_replace( [ 'divi_visual_builder_settings_data_before_get_', 'divi_visual_builder_settings_data_after_get_', 'divi_visual_builder_before_load_', 'divi_visual_builder_after_load_' ], '', current_action() );
		$key  = $usage . ':' . $name;

		if ( isset( $this->items_start[ $key ] ) ) {
			$duration    = ( microtime( true ) - $this->items_start[ $key ] ) * 1000;
			$usage_label = str_replace( '_', '-', $usage );
			self::_log( sprintf( '[item][%s] %s - [%dms]', $usage_label, $name, (int) $duration ) );
		}
	}

	/**
	 * Log HTTP response and track duration of the request.
	 *
	 * @since ??
	 *
	 * @param array|WP_Error $response    HTTP response or error.
	 * @param array          $parsed_args Request arguments.
	 * @param string         $url          Request URL.
	 *
	 * @return array|WP_Error The original response.
	 */
	public function maybe_log_http_response( $response, array $parsed_args, string $url ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required for filter signature.
		$start_data = array_pop( self::$_http_start_stack );
		$duration   = 0;

		if ( $start_data && $url === $start_data['url'] ) {
			$duration = ( microtime( true ) - $start_data['start'] ) * 1000;
		}

		if ( true === self::_should_log_all_requests() ) {
			// Don't log mocked translation requests if they were skipped in pre_http_request.
			if ( ! str_starts_with( $url, 'https://api.wordpress.org/translations/core/' ) ) {
				$this->_record_request( $url, (float) $duration );
			}
		}

		if ( true === self::_should_cache_youtube_requests() && true === self::_is_youtube_request( $url ) ) {
			if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
				self::_set_youtube_cache( $url, $response );
			}
		}

		if ( true === self::_should_cache_fonts_requests() && true === self::_is_fonts_request( $url ) ) {
			if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
				self::_set_fonts_cache( $url, $response );
			}
		}

		if ( true === self::_should_cache_fonts_requests() && true === self::_is_fonts_css_request( $url ) ) {
			if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
				self::_set_fonts_css_cache( $url, $response );
			}
		}

		return $response;
	}

	/**
	 * Log REST API response and track its duration.
	 *
	 * @since ??
	 *
	 * @param mixed           $result  Response to send to the client.
	 * @param WP_REST_Server  $server  REST server instance.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return mixed The (modified) result.
	 */
	public function maybe_log_rest_api_post( $result, WP_REST_Server $server, WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required for REST filter signature.
		$hash = spl_object_hash( $request );

		if ( isset( self::$_rest_start_times[ $hash ] ) ) {
			$duration = ( microtime( true ) - self::$_rest_start_times[ $hash ] ) * 1000;
			unset( self::$_rest_start_times[ $hash ] );

			if ( true === self::_should_log_all_requests() ) {
				$this->_record_request( home_url( $request->get_route() ), (float) $duration );
			} elseif ( true === self::_should_log_rest_api() ) {
				self::_log( sprintf( '[ROUTE] %s - [%dms]', home_url( $request->get_route() ), (int) round( $duration ) ) );
			}
		}

		return $result;
	}

	/**
	 * Log a summary of all requests recorded during the PHP lifecycle.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public function log_requests_summary(): void {
		if ( empty( self::$_recorded_requests ) ) {
			return;
		}

		$log = self::$_recorded_requests;
		uasort(
			$log,
			function ( $a, $b ) {
				return $b['count'] <=> $a['count'];
			}
		);

		foreach ( $log as $host => $data ) {
			$paths = $data['paths'];
			uasort(
				$paths,
				function ( $a, $b ) {
					return $b['count'] <=> $a['count'];
				}
			);

			foreach ( $paths as $path => $path_data ) {
				$count    = $path_data['count'];
				$duration = $path_data['total_duration'];

				if ( 1 === $count ) {
					self::_log( sprintf( '[ROUTE] %s - [%dms]', $path, (int) round( $duration ) ) );
				} else {
					$avg = $duration / $count;
					self::_log( sprintf( '[ROUTE] %s - [avg %dms] (%d)', $path, (int) round( $avg ), $count ) );
				}
			}
		}
	}

	/**
	 * Get the performance cache directory path.
	 *
	 * Returns the path to the directory used for local performance caches
	 * (e.g., YouTube, Google Fonts).
	 *
	 * @since ??
	 *
	 * @return string The cache directory path.
	 */
	private static function _get_cache_dir(): string {
		static $cache_dir = null;

		if ( null !== $cache_dir ) {
			return $cache_dir;
		}

		if ( function_exists( 'et_core_cache_dir' ) ) {
			$cache_dir = et_core_cache_dir()->path . '/perf';
		} else {
			$cache_dir = WP_CONTENT_DIR . '/et-cache/perf';
		}

		return $cache_dir;
	}

	/**
	 * Get the update check timestamp used for transients.
	 *
	 * @since ??
	 *
	 * @return int
	 */
	private static function _get_update_last_checked(): int {
		return time() - ( 30 * MINUTE_IN_SECONDS );
	}

	/**
	 * Build an update_core transient payload for performance runs.
	 *
	 * @since ??
	 *
	 * @return stdClass
	 */
	public static function build_update_core_transient(): stdClass {
		$version = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '';

		return (object) [
			'updates'         => [],
			'version_checked' => $version,
			'last_checked'    => self::_get_update_last_checked(),
		];
	}

	/**
	 * Build an update_plugins transient payload for performance runs.
	 *
	 * @since ??
	 *
	 * @return stdClass
	 */
	public static function build_update_plugins_transient(): stdClass {
		return (object) [
			'response'     => [],
			'no_update'    => [],
			'translations' => [],
			'checked'      => [],
			'last_checked' => self::_get_update_last_checked(),
		];
	}

	/**
	 * Build an update_themes transient payload for performance runs.
	 *
	 * @since ??
	 *
	 * @return stdClass
	 */
	public static function build_update_themes_transient(): stdClass {
		return (object) [
			'response'     => [],
			'no_update'    => [],
			'translations' => [],
			'checked'      => [],
			'last_checked' => self::_get_update_last_checked(),
		];
	}

	/**
	 * Build an update_translations transient payload for performance runs.
	 *
	 * @since ??
	 *
	 * @return stdClass
	 */
	public static function build_update_translations_transient(): stdClass {
		return (object) [
			'translations' => [],
			'last_checked' => self::_get_update_last_checked(),
		];
	}

	/**
	 * Prime update transients to avoid remote requests during perf runs.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	private static function _prime_update_transients(): void {
		$expiration = 5 * YEAR_IN_SECONDS;

		set_site_transient( 'update_core', self::build_update_core_transient(), $expiration );
		set_site_transient( 'update_plugins', self::build_update_plugins_transient(), $expiration );
		set_site_transient( 'update_themes', self::build_update_themes_transient(), $expiration );
		set_site_transient( 'update_translations', self::build_update_translations_transient(), $expiration );
	}

	/**
	 * Register update transient overrides during perf runs.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	private static function _register_update_transient_overrides(): void {
		add_filter( 'pre_site_transient_update_core', [ self::class, 'build_update_core_transient' ] );
		add_filter( 'pre_site_transient_update_plugins', [ self::class, 'build_update_plugins_transient' ] );
		add_filter( 'pre_site_transient_update_themes', [ self::class, 'build_update_themes_transient' ] );
		add_filter( 'pre_site_transient_update_translations', [ self::class, 'build_update_translations_transient' ] );
	}

	/**
	 * Determine whether the URL is a WordPress.org core version check request.
	 *
	 * @since ??
	 *
	 * @param string $url Request URL.
	 *
	 * @return bool
	 */
	private static function _is_wporg_core_version_request( string $url ): bool {
		if ( false === str_starts_with( $url, 'https://api.wordpress.org/' ) ) {
			return false;
		}

		return str_contains( $url, '/core/version-check/' );
	}

	/**
	 * Determine whether the URL is a WordPress.org theme update request.
	 *
	 * @since ??
	 *
	 * @param string $url Request URL.
	 *
	 * @return bool
	 */
	private static function _is_wporg_theme_update_request( string $url ): bool {
		if ( false === str_starts_with( $url, 'https://api.wordpress.org/' ) ) {
			return false;
		}

		return str_contains( $url, '/themes/update-check/' );
	}

	/**
	 * Determine whether the URL is a WordPress.org plugin update request.
	 *
	 * @since ??
	 *
	 * @param string $url Request URL.
	 *
	 * @return bool
	 */
	private static function _is_wporg_plugin_update_request( string $url ): bool {
		if ( false === str_starts_with( $url, 'https://api.wordpress.org/' ) ) {
			return false;
		}

		return str_contains( $url, '/plugins/update-check/' );
	}

	/**
	 * Build a stubbed response for WP.org theme update requests.
	 *
	 * @since ??
	 *
	 * @return array<string, mixed>
	 */
	private static function _build_wporg_theme_update_response(): array {
		return [
			'headers'  => [
				'content-type' => 'application/x-www-form-urlencoded; charset=utf-8',
			],
			'body'     => maybe_serialize(
				[
					'themes'       => [],
					'translations' => [],
					'response'     => [],
				]
			),
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	/**
	 * Build a stubbed response for WP.org plugin update requests.
	 *
	 * @since ??
	 *
	 * @return array<string, mixed>
	 */
	private static function _build_wporg_plugin_update_response(): array {
		return [
			'headers'  => [
				'content-type' => 'application/x-www-form-urlencoded; charset=utf-8',
			],
			'body'     => maybe_serialize(
				[
					'plugins'      => [],
					'translations' => [],
					'no_update'    => [],
					'response'     => [],
				]
			),
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	/**
	 * Build a stubbed response for WP.org core version check requests.
	 *
	 * @since ??
	 *
	 * @return array<string, mixed>
	 */
	private static function _build_wporg_core_version_response(): array {
		return [
			'headers'  => [
				'content-type' => 'application/json; charset=utf-8',
			],
			'body'     => wp_json_encode(
				[
					'offers'       => [],
					'translations' => [],
				]
			),
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	/**
	 * Log a message to the performance log file.
	 *
	 * Prepend timestamp and memory usage to the message. Logs are only written
	 * if performance test mode is active.
	 *
	 * @since ??
	 *
	 * @param string $message Log message.
	 *
	 * @return void
	 */
	private static function _log( string $message ): void {
		if ( true !== self::_is_performance_test_mode() ) {
			return;
		}

		// Remove any existing prefixes.
		$message = str_ireplace( [ 'Divi VB perf: ', 'Divi VB perf:', '[VB] ', '[VB]' ], '', $message );

		$log_file = self::_get_log_file();
		$log_dir  = dirname( $log_file );

		if ( ! is_dir( $log_dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Local cache only.
			@mkdir( $log_dir, 0755, true );
		}

		$mb_val = (int) round( memory_get_usage() / 1024 / 1024 );
		$mb_str = $mb_val < 10 ? '.' . $mb_val : (string) $mb_val;
		$memory = sprintf( '[%sMB]', $mb_str );
		$time   = '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC]';

		$prefix = ( str_starts_with( $message, '---' ) ) ? '' : '- ';
		$line   = $time . ' ' . $memory . ' ' . $prefix . $message;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Local cache only.
		file_put_contents( $log_file, $line . PHP_EOL, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Get the path to the performance log file.
	 *
	 * Checks for a custom path via constants/env, otherwise defaults to the
	 * Divi cache directory.
	 *
	 * @since ??
	 *
	 * @return string The absolute path to the log file.
	 */
	private static function _get_log_file(): string {
		$forced_path = self::_get_env_or_constant_string( self::LOG_PATH_ENV, self::LOG_PATH_CONST );

		if ( ! empty( $forced_path ) ) {
			$forced_path = (string) $forced_path;

			if ( true === str_ends_with( $forced_path, '/' ) ) {
				return $forced_path . self::_get_default_log_filename();
			}

			if ( true === is_dir( $forced_path ) ) {
				return trailingslashit( $forced_path ) . self::_get_default_log_filename();
			}

			return $forced_path;
		}

		$log_filename = self::_get_default_log_filename();

		if ( function_exists( 'et_core_cache_dir' ) ) {
			return et_core_cache_dir()->path . '/' . $log_filename;
		}

		return sprintf( '%s/et-cache/%s', WP_CONTENT_DIR, $log_filename );
	}

	/**
	 * Get the default performance log filename.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	private static function _get_default_log_filename(): string {
		if ( true === self::_is_vb_context() ) {
			return 'e2e-vb-performance.log';
		}

		return 'e2e-fe-performance.log';
	}

	/**
	 * Get the cache file prefix for the current context.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	private static function _get_cache_file_prefix(): string {
		if ( true === self::_is_vb_context() ) {
			return 'e2e-vb-';
		}

		return 'e2e-fe-';
	}

	/**
	 * Check if the current request is a Visual Builder context.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function _is_vb_context(): bool {
		return Conditions::is_vb_enabled()
			|| Conditions::is_vb_app_window()
			|| Conditions::is_vb_top_window();
	}

	/**
	 * Read a string value from an environment variable or a constant.
	 *
	 * @since ??
	 *
	 * @param string $env_key    Environment variable name.
	 * @param string $const_name Constant name.
	 *
	 * @return string|null The value or null if not found.
	 */
	private static function _get_env_or_constant_string( string $env_key, string $const_name ): ?string {
		if ( defined( $const_name ) ) {
			$value = constant( $const_name );

			if ( is_bool( $value ) ) {
				return true === $value ? '1' : '0';
			}

			return (string) $value;
		}

		$env_value = getenv( $env_key );

		if ( false === $env_value ) {
			return null;
		}

		return (string) $env_value;
	}

	/**
	 * Record a request for summary reporting.
	 *
	 * @since ??
	 *
	 * @param string $url      Request URL.
	 * @param float  $duration Request duration in milliseconds.
	 *
	 * @return void
	 */
	private function _record_request( string $url, float $duration = 0 ): void {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$host = $host ? $host : 'no-host';

		$path  = wp_parse_url( $url, PHP_URL_PATH );
		$query = wp_parse_url( $url, PHP_URL_QUERY );

		$path = $path ? $path : '/';

		if ( '' !== $query && null !== $query && false !== $query ) {
			$path .= '?' . $query;
		}

		if ( ! isset( self::$_recorded_requests[ $host ] ) ) {
			self::$_recorded_requests[ $host ] = [
				'count' => 0,
				'paths' => [],
			];
		}

		++self::$_recorded_requests[ $host ]['count'];

		if ( ! isset( self::$_recorded_requests[ $host ]['paths'][ $path ] ) ) {
			self::$_recorded_requests[ $host ]['paths'][ $path ] = [
				'count'          => 0,
				'total_duration' => 0,
			];
		}

		++self::$_recorded_requests[ $host ]['paths'][ $path ]['count'];
		self::$_recorded_requests[ $host ]['paths'][ $path ]['total_duration'] += $duration;
	}

	/**
	 * Get a cached YouTube response if available.
	 *
	 * @since ??
	 *
	 * @param string $url YouTube URL.
	 *
	 * @return array|false Cached response or false if not found.
	 */
	private static function _get_youtube_cache( string $url ) {
		$cache_file = self::_get_youtube_cache_file( $url );

		if ( ! file_exists( $cache_file ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local cache only.
		$content = file_get_contents( $cache_file );

		if ( false === $content ) {
			return false;
		}

		return json_decode( $content, true );
	}

	/**
	 * Save a YouTube response to the local cache.
	 *
	 * @since ??
	 *
	 * @param string $url      YouTube URL.
	 * @param array  $response HTTP response to cache.
	 *
	 * @return void
	 */
	private static function _set_youtube_cache( string $url, array $response ): void {
		$cache_file = self::_get_youtube_cache_file( $url );
		$cache_dir  = dirname( $cache_file );
		$headers    = wp_remote_retrieve_headers( $response );

		if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
			$headers = $headers->getAll();
		}

		if ( ! is_array( $headers ) ) {
			$headers = [];
		}

		$cacheable_response = [
			'headers'  => $headers,
			'body'     => wp_remote_retrieve_body( $response ),
			'response' => [
				'code'    => (int) wp_remote_retrieve_response_code( $response ),
				'message' => wp_remote_retrieve_response_message( $response ),
			],
			'cookies'  => [],
			'filename' => null,
		];

		if ( ! is_dir( $cache_dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Local cache only.
			@mkdir( $cache_dir, 0755, true );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Local cache only.
		file_put_contents( $cache_file, (string) wp_json_encode( $cacheable_response ) );
	}

	/**
	 * Get the cache file path for a given YouTube URL.
	 *
	 * @since ??
	 *
	 * @param string $url YouTube URL.
	 *
	 * @return string The absolute path to the cache file.
	 */
	private static function _get_youtube_cache_file( string $url ): string {
		return sprintf(
			'%s/youtube/%s%s.json',
			self::_get_cache_dir(),
			self::_get_cache_file_prefix(),
			md5( $url )
		);
	}

	/**
	 * Check if the given URL belongs to a YouTube-related host.
	 *
	 * @since ??
	 *
	 * @param string $url Request URL.
	 *
	 * @return bool True if it's a YouTube request.
	 */
	private static function _is_youtube_request( string $url ): bool {
		static $youtube_hosts_hash = null;

		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( empty( $host ) ) {
			return false;
		}

		if ( null === $youtube_hosts_hash ) {
			$youtube_hosts_hash = array_flip(
				[
					'youtube.com',
					'www.youtube.com',
					'youtu.be',
					'i.ytimg.com',
					'img.youtube.com',
					'i1.ytimg.com',
					'i2.ytimg.com',
					'i3.ytimg.com',
					'i4.ytimg.com',
					'i5.ytimg.com',
				]
			);
		}

		if ( isset( $youtube_hosts_hash[ $host ] ) ) {
			return true;
		}

		foreach ( $youtube_hosts_hash as $y_host => $index ) {
			$suffix = '.' . $y_host;
			if ( str_ends_with( $host, $suffix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether YouTube requests should be cached.
	 *
	 * @since ??
	 *
	 * @return bool True if YouTube caching is enabled.
	 */
	private static function _should_cache_youtube_requests(): bool {
		if ( true !== self::_is_performance_test_mode() ) {
			return false;
		}

		$enabled = self::_get_env_or_constant_flag( self::CACHE_YOUTUBE_ENV, self::CACHE_YOUTUBE_CONST );

		// Defaults to true.
		return null !== $enabled ? $enabled : true;
	}

	/**
	 * Determine whether the current request is a local request to the same site.
	 *
	 * @since ??
	 *
	 * @param string $url Request URL.
	 *
	 * @return bool True if the request is local.
	 */
	private static function _is_local_request( string $url ): bool {
		static $site_host = null;

		if ( null === $site_host ) {
			$site_host = (string) wp_parse_url( site_url(), PHP_URL_HOST );
		}

		$request_host = wp_parse_url( $url, PHP_URL_HOST );

		if ( empty( $request_host ) ) {
			return true;
		}

		if ( '' === $site_host ) {
			return false;
		}

		return $request_host === $site_host;
	}

	/**
	 * Determine whether performance test mode is active.
	 *
	 * Performance test mode is active if explicitly enabled via constants/env,
	 * or if enabled via query parameter (when query-param activation is allowed).
	 *
	 * @since ??
	 *
	 * @return bool True if performance test mode is active.
	 */
	private static function _is_performance_test_mode(): bool {
		if ( isset( self::$_config_cache['perf_test_mode'] ) ) {
			return (bool) self::$_config_cache['perf_test_mode'];
		}

		$query_only = self::_is_query_only_mode();
		$explicit   = self::_get_env_or_constant_flag( self::PERF_ENABLE_ENV, self::PERF_ENABLE_CONST );

		// First, check if initialization was successful.
		if ( ! self::_should_initialize() ) {
			return false;
		}

		// If query-param activation is enabled, we check for the specific query parameter.
		if ( true === self::_is_query_only_mode() ) {
			$enabled = self::_get_query_flag( self::PERF_MODE_QUERY_PARAM );
		} else {
			// If not using query-param activation, and initialize() already passed, then we are in performance mode.
			$enabled = true;
		}

		$enabled = (bool) $enabled;

		$enabled = (bool) apply_filters( 'divi_vb_performance_test_mode', $enabled );

		if ( true !== $query_only || true === $explicit ) {
			self::$_config_cache['perf_test_mode'] = $enabled;
		}

		return $enabled;
	}

	/**
	 * Determine whether the performance logger should be initialized.
	 *
	 * Checks for explicit activation via constants or environment variables first.
	 * If not explicitly enabled or disabled, it may check the query parameter
	 * if query-param activation is allowed.
	 *
	 * @since ??
	 *
	 * @return bool True if the logger should be initialized.
	 */
	private static function _should_initialize(): bool {
		if ( isset( self::$_config_cache['should_initialize'] ) ) {
			return (bool) self::$_config_cache['should_initialize'];
		}

		// Prioritize constants and environment variables.
		$explicit   = self::_get_env_or_constant_flag( self::PERF_ENABLE_ENV, self::PERF_ENABLE_CONST );
		$enabled    = $explicit;
		$query_only = self::_is_query_only_mode();

		// Fallback to query parameter if allowed.
		if ( true !== $enabled && true === $query_only ) {
			$enabled = self::_get_query_flag( self::PERF_MODE_QUERY_PARAM ) ?? $enabled;
		}

		/**
		 * Filters whether the performance logger should initialize.
		 *
		 * @since ??
		 *
		 * @param bool $enabled Whether the logger should initialize.
		 */
		$enabled = (bool) apply_filters( 'divi_vb_performance_should_initialize', $enabled );

		if ( true !== $query_only || true === $explicit ) {
			self::$_config_cache['should_initialize'] = $enabled;
		}

		return $enabled;
	}

	/**
	 * Determine whether the performance logger allows query-param activation.
	 *
	 * Query-only mode allows enabling performance tracking via the `et_fb_performance_tracking`
	 * query parameter even if the global performance constant/env is not set to true.
	 *
	 * For security, this is restricted to administrators or when specifically enabled.
	 *
	 * @since ??
	 *
	 * @return bool True if query-param activation is active.
	 */
	private static function _is_query_only_mode(): bool {
		if ( isset( self::$_config_cache['query_only_mode'] ) ) {
			return (bool) self::$_config_cache['query_only_mode'];
		}

		$enabled = self::_get_env_or_constant_flag( self::PERF_ENABLE_QUERY_PARAM_ENV, self::PERF_ENABLE_QUERY_PARAM_CONST );

		// Defaults to true.
		$enabled = null !== $enabled ? $enabled : true;

		self::$_config_cache['query_only_mode'] = $enabled;

		return $enabled;
	}

	/**
	 * Determine whether remote requests should be blocked.
	 *
	 * @since ??
	 *
	 * @return bool True if remote requests should be blocked.
	 */
	private static function _should_block_remote_requests(): bool {
		if ( true !== self::_is_performance_test_mode() ) {
			return false;
		}

		if ( isset( self::$_config_cache['block_remote_requests'] ) ) {
			return (bool) self::$_config_cache['block_remote_requests'];
		}

		$query_override = self::_get_query_flag( self::BLOCK_REMOTE_QUERY_PARAM );

		if ( null !== $query_override ) {
			$enabled = $query_override;
		} else {
			$enabled = self::_get_env_or_constant_flag( self::BLOCK_REMOTE_ENV, self::BLOCK_REMOTE_CONST );
		}

		$enabled = null !== $enabled ? $enabled : false;

		$enabled = (bool) apply_filters( 'divi_vb_performance_block_remote_requests', $enabled );

		self::$_config_cache['block_remote_requests'] = $enabled;

		return $enabled;
	}

	/**
	 * Determine whether blocked URLs should be logged.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function _should_log_blocked_requests(): bool {
		if ( true !== self::_is_performance_test_mode() ) {
			return false;
		}

		if ( isset( self::$_config_cache['log_blocked_requests'] ) ) {
			return (bool) self::$_config_cache['log_blocked_requests'];
		}

		$enabled = self::_get_env_or_constant_flag( self::LOG_BLOCKED_ENV, self::LOG_BLOCKED_CONST );
		$enabled = null !== $enabled ? $enabled : false;

		self::$_config_cache['log_blocked_requests'] = $enabled;

		return $enabled;
	}

	/**
	 * Determine whether request-level processing traces should be logged.
	 *
	 * This keeps high-frequency request logs disabled unless one of the
	 * explicit request logging modes is enabled.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function _should_log_request_processing(): bool {
		return
			self::_should_log_all_requests()
			|| self::_should_log_rest_api()
			|| self::_should_log_app_load()
			|| self::_should_log_after_app_load()
			|| self::_should_log_blocked_requests();
	}


	/**
	 * Record a blocked request URL.
	 *
	 * @since ??
	 *
	 * @param string $url Request URL.
	 *
	 * @return void
	 */
	private static function _record_blocked_request( string $url ): void {
		if ( in_array( $url, self::$_blocked_requests, true ) ) {
			return;
		}

		self::$_blocked_requests[] = $url;

		if ( count( self::$_blocked_requests ) > self::BLOCKED_REQUESTS_CACHE_LIMIT ) {
			self::$_blocked_requests = array_slice( self::$_blocked_requests, -1 * self::BLOCKED_REQUESTS_CACHE_LIMIT );
		}
	}

	/**
	 * Retrieve blocked requests.
	 *
	 * @since ??
	 *
	 * @return array<string>
	 */
	private static function _get_blocked_requests(): array {
		return array_values( self::$_blocked_requests );
	}

	/**
	 * Clear the blocked request cache.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	private static function _clear_blocked_requests(): void {
		self::$_blocked_requests = [];
	}

	/**
	 * Check whether the blocked requests list should be cleared.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return bool
	 */
	private static function _should_clear_blocked_requests( WP_REST_Request $request ): bool {
		$flag = self::_get_bool_from_value( $request->get_param( 'clear' ) );

		return null !== $flag ? $flag : false;
	}

	/**
	 * Check whether blocked requests should be included in the response.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return bool
	 */
	private static function _should_include_blocked_requests( WP_REST_Request $request ): bool {
		$flag = self::_get_bool_from_value( $request->get_param( 'include_blocked_requests' ) );

		return null !== $flag ? $flag : false;
	}

	/**
	 * Validate the REST nonce from the request.
	 *
	 * @since ??
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return bool
	 */
	private static function _has_valid_rest_nonce( WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'x-wp-nonce' );

		if ( empty( $nonce ) ) {
			return false;
		}

		$nonce = sanitize_text_field( $nonce );

		return false !== wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Perform a lightweight database connection check.
	 *
	 * @since ??
	 *
	 * @return array{connected: bool, error: string|null}
	 */
	private static function _get_db_health(): array {
		global $wpdb;

		$connected = true;
		$error     = null;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return [
				'connected' => false,
				'error'     => 'Database object not initialized.',
			];
		}

		if ( method_exists( $wpdb, 'check_connection' ) ) {
			$connected = (bool) $wpdb->check_connection( false );
		} elseif ( ! empty( $wpdb->last_error ) ) {
			$connected = false;
		}

		if ( true !== $connected ) {
			$error = ! empty( $wpdb->last_error ) ? $wpdb->last_error : 'Database connection check failed.';
		}

		return [
			'connected' => $connected,
			'error'     => $error,
		];
	}

	/**
	 * Determine whether all requests should be logged.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function _should_log_all_requests(): bool {
		if ( true !== self::_is_performance_test_mode() ) {
			return false;
		}

		if ( isset( self::$_config_cache['log_all_requests'] ) ) {
			return (bool) self::$_config_cache['log_all_requests'];
		}

		$enabled = self::_get_env_or_constant_flag( self::LOG_ALL_REQUESTS_ENV, self::LOG_ALL_REQUESTS_CONST );

		// Defaults to false.
		$enabled = null !== $enabled ? $enabled : false;

		self::$_config_cache['log_all_requests'] = $enabled;

		return $enabled;
	}

	/**
	 * Determine whether REST API calls should be logged.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function _should_log_rest_api(): bool {
		if ( true !== self::_is_performance_test_mode() ) {
			return false;
		}

		if ( isset( self::$_config_cache['log_rest_api'] ) ) {
			return (bool) self::$_config_cache['log_rest_api'];
		}

		$enabled = self::_get_env_or_constant_flag( self::LOG_REST_API_ENV, self::LOG_REST_API_CONST );

		// Defaults to true.
		$enabled = null !== $enabled ? $enabled : true;

		self::$_config_cache['log_rest_api'] = $enabled;

		return $enabled;
	}

	/**
	 * Determine whether app-load settings data timings should be logged.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function _should_log_app_load(): bool {
		if ( true !== self::_is_performance_test_mode() ) {
			return false;
		}

		if ( isset( self::$_config_cache['log_app_load'] ) ) {
			return (bool) self::$_config_cache['log_app_load'];
		}

		$enabled = self::_get_env_or_constant_flag( self::PERF_LOG_APP_LOAD_ENV, self::PERF_LOG_APP_LOAD_CONST );

		// Defaults to false.
		$enabled = null !== $enabled ? $enabled : false;

		self::$_config_cache['log_app_load'] = $enabled;

		return $enabled;
	}

	/**
	 * Determine whether after-app-load settings data timings should be logged.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function _should_log_after_app_load(): bool {
		if ( true !== self::_is_performance_test_mode() ) {
			return false;
		}

		if ( isset( self::$_config_cache['log_after_app_load'] ) ) {
			return (bool) self::$_config_cache['log_after_app_load'];
		}

		$enabled = self::_get_env_or_constant_flag( self::PERF_LOG_AFTER_APP_LOAD_ENV, self::PERF_LOG_AFTER_APP_LOAD_CONST );

		// Defaults to false.
		$enabled = null !== $enabled ? $enabled : false;

		self::$_config_cache['log_after_app_load'] = $enabled;

		return $enabled;
	}

	/**
	 * Read a boolean flag from the current query string.
	 *
	 * @since ??
	 *
	 * @param string $key Query parameter key.
	 *
	 * @return bool|null
	 */
	private static function _get_query_flag( string $key ): ?bool {
		// phpcs:disable WordPress.Security.NonceVerification -- Query flags are read-only for test configuration.
		$value = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : null;
		$value = is_scalar( $value ) ? (string) $value : null;

		return self::_get_bool_from_value( $value );
	}

	/**
	 * Read boolean flag from an env var or a constant.
	 *
	 * @since ??
	 *
	 * @param string $env_key    Env var name.
	 * @param string $const_name Constant name.
	 *
	 * @return bool|null
	 */
	private static function _get_env_or_constant_flag( string $env_key, string $const_name ): ?bool {
		if ( defined( $const_name ) ) {
			return self::_get_bool_from_value( constant( $const_name ) );
		}

		$env_value = getenv( $env_key );

		if ( false === $env_value ) {
			return null;
		}

		return self::_get_bool_from_value( $env_value );
	}

	/**
	 * Normalize boolean-like values.
	 *
	 * @since ??
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return bool|null
	 */
	private static function _get_bool_from_value( $value ): ?bool {
		if ( null === $value ) {
			return null;
		}

		if ( is_bool( $value ) ) {
			return $value;
		}

		static $bool_cache = [];

		$value_str = (string) $value;

		if ( isset( $bool_cache[ $value_str ] ) ) {
			return $bool_cache[ $value_str ];
		}

		$normalized = strtolower( trim( $value_str ) );

		if ( in_array( $normalized, [ '1', 'true', 'yes', 'on' ], true ) ) {
			$result = true;
		} elseif ( in_array( $normalized, [ '0', 'false', 'no', 'off' ], true ) ) {
			$result = false;
		} else {
			$result = null;
		}

		$bool_cache[ $value_str ] = $result;
		return $result;
	}

	/**
	 * Register settings data item timing hooks for a usage.
	 *
	 * @since ??
	 *
	 * @param string $usage Usage of the settings data. 'app_load' or 'after_app_load'.
	 *
	 * @return void
	 */
	private function _register_settings_data_item_hooks( string $usage ): void {
		if ( empty( $usage ) ) {
			return;
		}

		if ( true === ( self::$_settings_data_hooks_registered[ $usage ] ?? false ) ) {
			return;
		}

		$item_names = SettingsData::get_registered_item_names( $usage );

		foreach ( $item_names as $item_name ) {
			add_action( "divi_visual_builder_settings_data_before_get_{$item_name}", [ $this, 'log_before_item' ], 10, 1 );
			add_action( "divi_visual_builder_settings_data_after_get_{$item_name}", [ $this, 'log_after_item' ], 10, 1 );
		}

		self::$_settings_data_hooks_registered[ $usage ] = true;
	}

	/**
	 * Register settings data item timing hooks for a newly registered item.
	 *
	 * @since ??
	 *
	 * @param string $item_name Settings data item name.
	 * @param string $usage     Usage of the item. 'app_load', 'after_app_load', or 'both'.
	 *
	 * @return void
	 */
	public function maybe_register_settings_data_item_hooks( string $item_name, string $usage ): void {
		if ( 'both' === $usage ) {
			$usages = [ 'app_load', 'after_app_load' ];
		} else {
			$usages = [ $usage ];
		}

		foreach ( $usages as $usage_name ) {
			if ( true !== self::_should_log_settings_data_usage( $usage_name ) ) {
				continue;
			}

			add_action( "divi_visual_builder_settings_data_before_get_{$item_name}", [ $this, 'log_before_item' ], 10, 1 );
			add_action( "divi_visual_builder_settings_data_after_get_{$item_name}", [ $this, 'log_after_item' ], 10, 1 );
		}
	}

	/**
	 * Determine whether memory debugging is enabled.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function _should_debug_memory(): bool {
		if ( true !== self::_is_performance_test_mode() ) {
			return false;
		}

		$enabled = self::_get_env_or_constant_flag( self::DEBUG_MEMORY_ENV, self::DEBUG_MEMORY_CONST );

		return true === $enabled;
	}

	/**
	 * Register memory debug hooks.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	private static function _register_memory_debug_hooks(): void {
		self::_log_memory_usage( 'bootstrap' );

		add_action( 'plugins_loaded', fn() => self::_log_memory_usage( 'plugins_loaded' ), 1 );
		add_action( 'init', fn() => self::_log_memory_usage( 'init' ), 1 );
		add_action( 'rest_api_init', fn() => self::_log_memory_usage( 'rest_api_init' ), 1 );

		add_filter(
			'rest_pre_dispatch',
			static function ( $result, WP_REST_Server $server, WP_REST_Request $request ) {
				self::_log_memory_usage( 'rest_pre_dispatch:' . $request->get_route() );
				return $result;
			},
			-100,
			3
		);

		add_filter(
			'rest_post_dispatch',
			static function ( $result, WP_REST_Server $server, WP_REST_Request $request ) {
				self::_log_memory_usage( 'rest_post_dispatch:' . $request->get_route() );
				return $result;
			},
			100,
			3
		);

		register_shutdown_function(
			static function () {
				self::_log_memory_usage( 'shutdown' );
			}
		);
	}

	/**
	 * Log current memory usage.
	 *
	 * @since ??
	 *
	 * @param string $stage Stage name.
	 *
	 * @return void
	 */
	private static function _log_memory_usage( string $stage ): void {
		$usage = memory_get_usage( true );
		$peak  = memory_get_peak_usage( true );

		self::_log( sprintf( '[memory] (%s): %sMB, peak: %sMB', $stage, round( $usage / 1024 / 1024, 2 ), round( $peak / 1024 / 1024, 2 ) ) );
	}

	/**
	 * Disable Action Scheduler async runner for performance tests.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	private static function _register_action_scheduler_async_runner_block(): void {
		if ( true !== self::_is_performance_test_mode() ) {
			return;
		}

		add_filter( 'action_scheduler_use_async_request_runner', '__return_false', 0 );
		add_filter( 'action_scheduler_allow_async_request_runner', '__return_false', 0 );
		add_action( 'init', [ __CLASS__, '_disable_action_scheduler_default_runner' ], 10 );
	}

	/**
	 * Disable Action Scheduler default runner.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function _disable_action_scheduler_default_runner(): void {
		if ( ! class_exists( 'ActionScheduler' ) ) {
			return;
		}

		remove_action( 'action_scheduler_run_queue', [ ActionScheduler::runner(), 'run' ] );
	}

	/**
	 * Determine whether WooCommerce REST API should be disabled.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function _should_disable_wc_rest(): bool {
		if ( true !== self::_is_performance_test_mode() ) {
			return false;
		}

		$enabled = self::_get_env_or_constant_flag( self::DISABLE_WC_REST_ENV, self::DISABLE_WC_REST_CONST );

		// Defaults to false.
		return null !== $enabled ? $enabled : false;
	}

	/**
	 * Register WooCommerce REST cleanup hooks.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	private static function _register_wc_rest_cleanup(): void {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$rest_prefix = rest_get_url_prefix();

		if ( ! str_contains( $request_uri, '/' . $rest_prefix . '/' ) ) {
			return;
		}

		// Ported from phpunit-wc-rest-test-cleanup.php for VB performance testing.
		add_filter( 'woocommerce_admin_disabled', '__return_true', 0 );

		add_filter(
			'woocommerce_rest_api_get_rest_namespaces',
			static function (): array {
				return [];
			},
			0
		);

		add_filter(
			'woocommerce_is_rest_api_request',
			'__return_false',
			0
		);

		add_action(
			'plugins_loaded',
			static function () {
				remove_action( 'init', [ 'WC_Site_Tracking', 'init' ] );
				remove_action( 'init', [ 'WC_Shortcodes', 'init' ] );
				remove_action( 'init', [ 'WC_Emails', 'init_transactional_emails' ] );

				if ( ! function_exists( 'WC' ) ) {
					return;
				}

				remove_action( 'init', [ WC(), 'load_rest_api' ], 0 );
			},
			-1
		);

		add_action(
			'init',
			static function () {
				if ( ! function_exists( 'WC' ) ) {
					return;
				}

				remove_action( 'rest_api_init', [ WC(), 'register_wp_admin_settings' ] );
			},
			1
		);
	}

	/**
	 * Disable WooCommerce cart fragments in Visual Builder performance runs.
	 *
	 * The cart fragments script triggers `wc-ajax=get_refreshed_fragments`,
	 * which adds frontend noise during VB performance measurements without
	 * helping the scenarios we are timing.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	private static function _register_wc_cart_fragments_cleanup(): void {
		add_action( 'wp_enqueue_scripts', [ self::class, '_disable_wc_cart_fragments_script' ], 999 );
		add_filter( 'woocommerce_get_script_data', [ self::class, '_filter_wc_script_data' ], 10, 2 );
	}

	/**
	 * Dequeue WooCommerce cart fragments script for VB performance runs.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function _disable_wc_cart_fragments_script(): void {
		wp_dequeue_script( 'wc-cart-fragments' );
		wp_deregister_script( 'wc-cart-fragments' );
	}

	/**
	 * Remove WooCommerce cart fragments runtime data in VB performance runs.
	 *
	 * @since ??
	 *
	 * @param mixed  $script_data Script data payload.
	 * @param string $handle      Script handle.
	 *
	 * @return mixed
	 */
	public static function _filter_wc_script_data( $script_data, string $handle ) {
		if ( 'wc-cart-fragments' === $handle ) {
			return null;
		}

		return $script_data;
	}

	/**
	 * Determine whether Google Fonts requests should be cached.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function _should_cache_fonts_requests(): bool {
		if ( true !== self::_is_performance_test_mode() ) {
			return false;
		}

		$query_override = self::_get_query_flag( self::CACHE_FONTS_QUERY_PARAM );

		if ( null !== $query_override ) {
			return $query_override;
		}

		$enabled = self::_get_env_or_constant_flag( self::CACHE_FONTS_ENV, self::CACHE_FONTS_CONST );

		// Defaults to true.
		return null !== $enabled ? $enabled : true;
	}

	/**
	 * Determine whether to block Google Fonts requests.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function _should_block_google_fonts(): bool {
		if ( true !== self::_is_performance_test_mode() ) {
			return false;
		}

		$enabled = self::_get_env_or_constant_flag( self::BLOCK_FONTS_ENV, self::BLOCK_FONTS_CONST );

		return null !== $enabled ? $enabled : false;
	}

	/**
	 * Check if the request is to a Google Fonts-related host.
	 *
	 * @since ??
	 *
	 * @param string $url Request URL.
	 *
	 * @return bool
	 */
	private static function _is_fonts_request( string $url ): bool {
		$host = wp_parse_url( $url, PHP_URL_HOST );

		return 'www.googleapis.com' === $host && str_contains( $url, '/webfonts/' );
	}

	/**
	 * Check if the request is to a Google Fonts CSS endpoint.
	 *
	 * @since ??
	 *
	 * @param string $url Request URL.
	 *
	 * @return bool
	 */
	private static function _is_fonts_css_request( string $url ): bool {
		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( 'fonts.googleapis.com' !== $host ) {
			return false;
		}

		return str_contains( $url, '/css' );
	}

	/**
	 * Check if the request targets a Google Fonts host.
	 *
	 * @since ??
	 *
	 * @param string $url Request URL.
	 *
	 * @return bool
	 */
	private static function _is_google_fonts_host( string $url ): bool {
		$host = wp_parse_url( $url, PHP_URL_HOST );

		return in_array( $host, [ 'fonts.googleapis.com', 'fonts.gstatic.com' ], true );
	}

	/**
	 * Get cached Google Fonts response if available.
	 *
	 * @since ??
	 *
	 * @param string $url Google Fonts URL.
	 *
	 * @return array|false Cached response or false if not found.
	 */
	private static function _get_fonts_cache( string $url ) {
		$cache_file = self::_get_fonts_cache_file( $url );

		if ( ! file_exists( $cache_file ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local cache only.
		$content = file_get_contents( $cache_file );

		if ( false === $content ) {
			return false;
		}

		return json_decode( $content, true );
	}

	/**
	 * Get cached Google Fonts CSS response if available.
	 *
	 * @since ??
	 *
	 * @param string $url Google Fonts CSS URL.
	 *
	 * @return array|false Cached response or false if not found.
	 */
	private static function _get_fonts_css_cache( string $url ) {
		$cache_file = self::_get_fonts_css_cache_file( $url );

		if ( ! file_exists( $cache_file ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local cache only.
		$content = file_get_contents( $cache_file );

		if ( false === $content ) {
			return false;
		}

		return json_decode( $content, true );
	}

	/**
	 * Save Google Fonts response to cache.
	 *
	 * @since ??
	 *
	 * @param string $url      Google Fonts URL.
	 * @param array  $response HTTP response.
	 *
	 * @return void
	 */
	private static function _set_fonts_cache( string $url, array $response ): void {
		$cache_file = self::_get_fonts_cache_file( $url );
		$cache_dir  = dirname( $cache_file );

		if ( ! is_dir( $cache_dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Local cache only.
			@mkdir( $cache_dir, 0755, true );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Local cache only.
		file_put_contents( $cache_file, wp_json_encode( $response ) );
	}

	/**
	 * Save Google Fonts CSS response to cache.
	 *
	 * @since ??
	 *
	 * @param string $url      Google Fonts CSS URL.
	 * @param array  $response HTTP response.
	 *
	 * @return void
	 */
	private static function _set_fonts_css_cache( string $url, array $response ): void {
		$cache_file = self::_get_fonts_css_cache_file( $url );
		$cache_dir  = dirname( $cache_file );

		if ( ! is_dir( $cache_dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Local cache only.
			@mkdir( $cache_dir, 0755, true );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Local cache only.
		file_put_contents( $cache_file, wp_json_encode( $response ) );
	}

	/**
	 * Get a cache file path for a Google Fonts URL.
	 *
	 * @since ??
	 *
	 * @param string $url Google Fonts URL.
	 *
	 * @return string Cache file path.
	 */
	private static function _get_fonts_cache_file( string $url ): string {
		$hash = md5( $url );

		return sprintf(
			'%s/fonts/%s%s.json',
			self::_get_cache_dir(),
			self::_get_cache_file_prefix(),
			$hash
		);
	}

	/**
	 * Get a cache file path for a Google Fonts CSS URL.
	 *
	 * @since ??
	 *
	 * @param string $url Google Fonts CSS URL.
	 *
	 * @return string Cache file path.
	 */
	private static function _get_fonts_css_cache_file( string $url ): string {
		$hash = md5( $url );

		return sprintf(
			'%s/fonts-css/%s%s.json',
			self::_get_cache_dir(),
			self::_get_cache_file_prefix(),
			$hash
		);
	}

	/**
	 * Determine whether included files debugging is enabled.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function _should_debug_included_files(): bool {
		if ( true !== self::_is_performance_test_mode() ) {
			return false;
		}

		$enabled = self::_get_env_or_constant_flag( self::DEBUG_INCLUDED_FILES_ENV, self::DEBUG_INCLUDED_FILES_CONST );

		return true === $enabled;
	}

	/**
	 * Register included files debug hooks.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	private static function _register_included_files_debug_hooks(): void {
		add_filter(
			'rest_post_dispatch',
			static function ( $result, WP_REST_Server $server, WP_REST_Request $request ) {
				$route = $request->get_route();

				$included_files = get_included_files();
				self::_log( sprintf( 'included files for %s: %d files', $route, count( $included_files ) ) );

				foreach ( $included_files as $file ) {
					self::_log( sprintf( 'INC: %s', $file ) );
				}

				return $result;
			},
			101,
			3
		);
	}

	/**
	 * Get OPcache snapshot.
	 *
	 * @since ??
	 *
	 * @return array<string, int>|null
	 */
	private static function _get_opcache_snapshot(): ?array {
		if ( ! function_exists( 'opcache_get_status' ) ) {
			return null;
		}

		$status = opcache_get_status( false );
		if ( ! is_array( $status ) ) {
			return null;
		}

		return [
			'hits'           => $status['opcache_statistics']['hits'] ?? 0,
			'misses'         => $status['opcache_statistics']['misses'] ?? 0,
			'num_scripts'    => $status['opcache_statistics']['num_cached_scripts'] ?? 0,
			'max_scripts'    => $status['opcache_statistics']['max_cached_keys'] ?? 0,
			'oom'            => $status['opcache_statistics']['oom_restarts'] ?? 0,
			'hash_restarts'  => $status['opcache_statistics']['hash_restarts'] ?? 0,
			'manual_restart' => $status['opcache_statistics']['manual_restarts'] ?? 0,
		];
	}

	/**
	 * Determine whether a log message should be allowed based on active debug parameters,
	 * even if after-app-load filtering is enabled.
	 *
	 * @since ??
	 *
	 * @param string $usage The log message.
	 *
	 * @return bool
	 */
	private static function _should_log_settings_data_usage( string $usage ): bool {
		if ( 'app_load' === $usage ) {
			return self::_should_log_app_load();
		}

		if ( 'after_app_load' === $usage ) {
			return self::_should_log_after_app_load();
		}

		return false;
	}

	/**
	 * Determine whether to write the per-request log header.
	 *
	 * Avoid creating header-only FE logs when no detailed logging mode is active.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function _should_write_log_header(): bool {
		if ( true === self::_is_vb_context() ) {
			return true;
		}

		if ( true === self::_should_log_all_requests() ) {
			return true;
		}

		if ( true === self::_should_log_rest_api() ) {
			return true;
		}

		if ( true === self::_should_log_blocked_requests() ) {
			return true;
		}

		return self::_should_debug_memory() || self::_should_debug_included_files();
	}
}
