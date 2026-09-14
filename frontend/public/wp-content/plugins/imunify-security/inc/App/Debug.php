<?php
/**
 * Copyright (с) Cloud Linux GmbH & Cloud Linux Software, Inc 2010-2025 All Rights Reserved
 *
 * Licensed under CLOUD LINUX LICENSE AGREEMENT
 * https://www.cloudlinux.com/legal/
 */

namespace CloudLinux\Imunify\App;

/**
 * Debug
 */
class Debug {
	/**
	 * Transient key prefix for error throttling.
	 */
	const ERROR_THROTTLE_PREFIX = 'imunify_security_error_';

	/**
	 * Website url.
	 *
	 * @var string
	 */
	private $website = '';

	/**
	 * Home path.
	 *
	 * @var string
	 */
	private $home_path = '';

	/**
	 * User.
	 *
	 * @var string
	 */
	private $user = '';

	/**
	 * Request uri.
	 *
	 * @var string
	 */
	private $request_uri = '';

	/**
	 * Http code.
	 *
	 * @var int
	 */
	private $http_code = 0;

	/**
	 * Server ip.
	 *
	 * @var string
	 */
	private $server_ip = '';

	/**
	 * Error codes.
	 *
	 * @var array<string>
	 *
	 * PHP Core Exceptions
	 */
	public $codes = array(
		E_ERROR             => 'E_ERROR',
		E_WARNING           => 'E_WARNING',
		E_PARSE             => 'E_PARSE',
		E_NOTICE            => 'E_NOTICE',
		E_CORE_ERROR        => 'E_CORE_ERROR',
		E_CORE_WARNING      => 'E_CORE_WARNING',
		E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
		E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
		E_USER_ERROR        => 'E_USER_ERROR',
		E_USER_WARNING      => 'E_USER_WARNING',
		E_USER_NOTICE       => 'E_USER_NOTICE',
		// E_STRICT is deprecated as of PHP 8.4.0. It is added in the constructor conditionally.
		E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
		E_DEPRECATED        => 'E_DEPRECATED',
		E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
		E_ALL               => 'E_ALL',
	);

	/**
	 * Environment.
	 *
	 * @var Environment
	 */
	private $environment;

	/**
	 * Constructor.
	 *
	 * @param Environment $environment Environment instance.
	 */
	public function __construct( $environment ) {
		$this->environment = $environment;
		add_action( 'imunify_security_set_error', array( $this, 'error' ), 10, 5 );
		add_action( 'imunify_security_set_error_handler', array( $this, 'setErrorHandler' ), 10, 0 );
		add_action( 'imunify_security_restore_error_handler', array( $this, 'restoreErrorHandler' ), 10, 0 );

		// E_STRICT is deprecated as of PHP 8.4.0.
		if ( PHP_VERSION_ID < 80400 ) {
			$this->codes[ E_STRICT ] = 'E_STRICT';
		}
	}

	/**
	 * Get the site home URL via the WordPress API.
	 *
	 * The home_url() function reads the `home` option — which reflects the
	 * WP_HOME constant through the `option_home` filter — and applies the
	 * `home_url` filter. Guarded so the accessor degrades to '' before
	 * WordPress is loaded.
	 *
	 * @return string
	 */
	protected function wpHome() {
		if ( function_exists( 'home_url' ) ) {
			return (string) home_url();
		}

		return '';
	}

	/**
	 * Get option home.
	 *
	 * @return string
	 */
	protected function wpHomeOption() {
		if ( function_exists( 'get_option' ) ) {
			$home = get_option( 'home' );
			if ( is_string( $home ) ) {
				return $home;
			}
		}

		return '';
	}

	/**
	 * Get home path.
	 *
	 * @return string
	 */
	public function homePath() {
		if ( ! empty( $this->home_path ) ) {
			return $this->home_path;
		}

		if ( defined( 'ABSPATH' ) ) {
			return ABSPATH;
		}

		return $this->home_path;
	}

	/**
	 * Get website.
	 *
	 * @return string
	 */
	public function website() {
		if ( ! empty( $this->website ) ) {
			return $this->website;
		}

		$wp_home        = $this->wpHome();
		$wp_home_option = $this->wpHomeOption();

		if ( ! empty( $wp_home ) ) {
			$this->website = $wp_home;
		} elseif ( ! empty( $wp_home_option ) ) {
			$this->website = $wp_home_option;
		} elseif ( array_key_exists( 'SERVER_NAME', $_SERVER ) ) {
			$this->website = esc_url_raw( wp_unslash( $_SERVER['SERVER_NAME'] ) );
		}

		return $this->website;
	}

	/**
	 * Get user.
	 *
	 * @return string
	 */
	public function user() {
		if ( ! empty( $this->user ) ) {
			return $this->user;
		}

		$parse = wp_parse_url( $this->website() );
		if ( is_array( $parse ) && array_key_exists( 'host', $parse ) ) {
			$this->user = $parse['host'];
		}

		return $this->user;
	}

	/**
	 * Get request uri.
	 *
	 * @return string
	 */
	public function requestUri() {
		if ( ! empty( $this->request_uri ) ) {
			return $this->request_uri;
		}

		if ( array_key_exists( 'REQUEST_URI', $_SERVER ) ) {
			$this->request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}

		return $this->request_uri;
	}

	/**
	 * Get http code.
	 *
	 * @return int
	 */
	public function httpCode() {
		if ( ! empty( $this->http_code ) ) {
			return $this->http_code;
		}

		if ( function_exists( 'http_response_code' ) ) {
			$this->http_code = (int) http_response_code();
		}

		return $this->http_code;
	}

	/**
	 * Get current server IP Address.
	 *
	 * @return string
	 */
	public function serverIp() {
		if ( ! empty( $this->server_ip ) ) {
			return $this->server_ip;
		}

		if ( isset( $_SERVER['SERVER_ADDR'] ) ) {
			$ip = filter_var( sanitize_text_field( wp_unslash( $_SERVER['SERVER_ADDR'] ) ), FILTER_VALIDATE_IP );
			if ( is_string( $ip ) ) {
				$this->server_ip = $ip;

				return $this->server_ip;
			}
		}

		if ( function_exists( 'gethostbyname' ) && function_exists( 'gethostname' ) ) {
			$hostname = gethostname();
			if ( is_string( $hostname ) ) {
				$ip = gethostbyname( $hostname );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					$this->server_ip = $ip;
				}
			}
		}

		return $this->server_ip;
	}

	/**
	 * Set error handler.
	 *
	 * @return void
	 */
	public function setErrorHandler() {
		// @phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler( array( $this, 'error' ), E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED ); // @phpstan-ignore-line
	}

	/**
	 * Restore error handler.
	 *
	 * @return void
	 */
	public function restoreErrorHandler() {
		restore_error_handler();
	}

	/**
	 * Send error.
	 *
	 * @param int     $errno number.
	 * @param string  $errstr message.
	 * @param ?string $errfile file.
	 * @param ?int    $errline line.
	 * @param array   $extra extra data.
	 *
	 * @return void
	 */
	public function error( $errno, $errstr, $errfile = null, $errline = null, $extra = array() ) {
		if ( in_array( $errno, array( E_DEPRECATED, E_USER_DEPRECATED ) ) ) {
			return;
		}

		$data = $this->data( $errno, $errstr, $errfile, $errline, $extra );

		// Filter out events that don't contain our plugin's code in the stack trace.
		if ( isset( $data['sentry.interfaces.Exception']['values'][0]['stacktrace']['frames'] ) ) {
			$frames = $data['sentry.interfaces.Exception']['values'][0]['stacktrace']['frames'];
			if ( ! $this->hasPluginCodeInFrames( $frames ) ) {
				return;
			}
		}

		$this->send( $data );
	}

	/**
	 * Data.
	 *
	 * @param int     $errno number.
	 * @param string  $errstr message.
	 * @param ?string $errfile file.
	 * @param ?int    $errline line.
	 * @param array   $extra extra data.
	 *
	 * @return array
	 */
	public function data( $errno, $errstr, $errfile = null, $errline = null, $extra = array() ) {
		$fingerprint = null;
		if ( is_array( $extra ) && array_key_exists( 'fingerprint', $extra ) ) {
			$fingerprint = $extra['fingerprint'];
			unset( $extra['fingerprint'] );
		}

		$result = array(
			'environment'                 => $this->environment->get(),
			'release'                     => $this->release(),
			'tags'                        => $this->tags(),
			'extra'                       => array_merge(
				$this->extra(),
				$extra
			),
			'user'                        => $this->userdata(),
			'sentry.interfaces.Exception' => array(
				'exc_omitted' => null,
				'values'      => array(
					array(
						'stacktrace' => array(
							'frames'         => $this->prepare_stack_frames( $errfile, $errno ),
							'frames_omitted' => null,
						),
						'type'       => isset( $this->codes[ $errno ] ) ? $this->codes[ $errno ] : 'Undefined: ' . $errno,
						'value'      => $errstr,
					),
				),
			),
		);

		if ( $fingerprint ) {
			$result['fingerprint'] = $fingerprint;
		}

		return $result;
	}

	/**
	 * Send to sentry.
	 *
	 * @param array $body send.
	 *
	 * @return string|false
	 */
	public function send( $body ) {
		if ( ! function_exists( 'curl_init' ) || empty( $body ) || defined( 'IS_TESTING' ) ) {
			return false;
		}

		$url     = 'https://im360.sentry.cloudlinux.com/api/' . $this->project_id() . '/store/';
		$headers = array(
			'Content-Type: application/json',
			'X-Sentry-Auth: Sentry sentry_version=7,sentry_timestamp=' . time() . ',sentry_client=php-curl/1.0,sentry_key=' . $this->key(),
		);

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $body ) );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 1 );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 1 );
		curl_setopt( $ch, CURLOPT_HEADER, false );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		$response = curl_exec( $ch );
		curl_close( $ch );

		return $response;
	}

	/**
	 * Prepares stack trace data for Sentry.
	 *
	 * @param string $errfile File name.
	 * @param int    $errline Line number.
	 *
	 * @return array
	 *
	 * @phpcs:disable PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
	 */
	private function prepare_stack_frames( $errfile = null, $errline = null ) {

		// Default result will contain only the error file and line.
		$default = array(
			array(
				'filename' => $errfile,
				'lineno'   => $errline,
			),
		);

		if ( ! function_exists( 'debug_backtrace' ) ) {
			return $default;
		}

		$backtrace = debug_backtrace(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace

		// Ignore last couple of frames that are not relevant to the error.
		$frames_to_omit = array(
			'App/Debug.php', // Call to CloudLinux\Imunify\App::prepare_stack_frames().
			'App/Debug.php', // Call to CloudLinux\Imunify\App::data().
			'wp-includes/class-wp-hook.php', // WordPress executing a filter.
			'wp-includes/class-wp-hook.php', // WordPress executing a filter.
			'wp-includes/plugin.php', // Call to apply_filters().
		);

		foreach ( $frames_to_omit as $frame_to_omit ) {
			if ( isset( $backtrace[0]['file'] ) && strpos( $backtrace[0]['file'], $frame_to_omit ) !== false ) {
				array_shift( $backtrace );
			}
		}

		$frames = array();
		while ( ! empty( $backtrace ) ) {
			$frame = array_pop( $backtrace );
			if ( isset( $frame['file'] ) && isset( $frame['line'] ) ) {
				$frames[] = array(
					'filename' => $frame['file'],
					'lineno'   => $frame['line'],
				);
			}
		}

		if ( empty( $frames ) ) {
			return $default;
		}

		return $frames;
	}

	/**
	 * Determines the plugin version.
	 *
	 * @return string Plugin version.
	 */
	public function pluginVersion() {
		return defined( 'IMUNIFY_SECURITY_VERSION' ) ? IMUNIFY_SECURITY_VERSION : 'dev';
	}

	/**
	 * Get environment instance.
	 *
	 * @return Environment
	 */
	public function environment() {
		return $this->environment;
	}

	/**
	 * Get release name.
	 *
	 * @return string Release name.
	 */
	public function release() {
		return 'imunify-security-wp-plugin@' . $this->pluginVersion();
	}

	/**
	 * Get Sentry key.
	 *
	 * @return string Sentry Key.
	 */
	public function key() {
		return '0f3f9fb22ff84c0c80144b570dea433a';
	}

	/**
	 * Get Sentry project ID.
	 *
	 * @return int Sentry project ID.
	 */
	public function project_id() {
		return 34;
	}

	/**
	 * Get tags.
	 *
	 * @return array<string> Tags.
	 */
	public function tags() {
		$tags = array(
			'php_version'    => phpversion(),
			'plugin_version' => $this->pluginVersion(),
		);

		// Merge Imunify package versions as Sentry tags. Wrapped in try/catch
		// so that version tagging can never disrupt error reporting.
		try {
			$versions = function_exists( 'get_option' )
				? get_option( DataStore::PACKAGE_VERSIONS_OPTION, array() )
				: array();
			if ( is_array( $versions ) ) {
				foreach ( $versions as $package => $version ) {
					if ( is_string( $version ) && ! isset( $tags[ $package ] ) ) {
						$tags[ $package ] = $version;
					}
				}
			}
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- version tags are non-critical.
		}

		return $tags;
	}

	/**
	 * Get user data.
	 *
	 * @return array<string> User data.
	 */
	public function userdata() {
		return array(
			'username' => $this->user(),
		);
	}

	/**
	 * Get extra data.
	 *
	 * @return array<string, int|string> Extra data.
	 */
	public function extra() {
		return array(
			'website'     => $this->website(),
			'home_path'   => $this->homePath(),
			'request_uri' => $this->requestUri(),
			'http_code'   => $this->httpCode(),
			'server_ip'   => $this->serverIp(),
		);
	}

	/**
	 * Check if any frame in the stack trace contains plugin code.
	 *
	 * @param array $frames Array of stack trace frames.
	 * @return bool True if any frame contains plugin code, false otherwise.
	 */
	public function hasPluginCodeInFrames( $frames ) {
		foreach ( $frames as $frame ) {
			if ( isset( $frame['filename'] ) && strpos( $frame['filename'], IMUNIFY_SECURITY_PATH ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Handles errors while ensuring that the same error is not reported more than once per hour.
	 *
	 * @param string $message The error message.
	 * @param string $errorCode The unique error identifier.
	 * @param array  $context Additional context data.
	 *
	 * @return void
	 *
	 * @since 2.1.0
	 */
	public function sendThrottledError( $message, $errorCode, $context = array() ) {
		$transientKey = self::ERROR_THROTTLE_PREFIX . $errorCode;

		// Check if this error was already reported in the last hour.
		if ( ! get_transient( $transientKey ) ) {

			// Set transient to prevent this error from being reported again for an hour.
			set_transient( $transientKey, true, HOUR_IN_SECONDS );

			$this->error(
				E_WARNING,
				$message,
				__FILE__,
				__LINE__,
				array(
					'fingerprint' => array( $errorCode ),
					'context'     => $context,
				)
			);
		}
	}
}
