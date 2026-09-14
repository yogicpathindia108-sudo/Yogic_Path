<?php
/**
 * Perf cache helpers for Visual Builder settings-data payloads.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\Performance;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Utility\Conditions;

/**
 * Utilities for perf-mode-only settings-data caches.
 *
 * @since ??
 */
class SettingsDataPerfCache {
	/**
	 * Perf mode enable env key.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private const PERF_ENABLE_ENV = 'DIVI_PERF_E2E_ENABLE';

	/**
	 * Perf mode enable const key.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private const PERF_ENABLE_CONST = 'DIVI_PERF_E2E_ENABLE';

	/**
	 * Module definitions cache env key.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private const CACHE_MODULE_DEFS_ENV = 'DIVI_PERF_E2E_CACHE_MODULE_DEFS';

	/**
	 * Module definitions cache const key.
	 *
	 * @since ??
	 *
	 * @var string
	 */
	private const CACHE_MODULE_DEFS_CONST = 'DIVI_PERF_E2E_CACHE_MODULE_DEFS';

	/**
	 * Read cached shortcode module definitions for perf runs.
	 *
	 * @since ??
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_cached_shortcode_module_definitions(): ?array {
		if ( true !== self::_should_cache_shortcode_module_definitions() ) {
			return null;
		}

		$cache_file = self::_get_shortcode_module_definitions_cache_file();

		if ( ! file_exists( $cache_file ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local perf cache read.
		$content = file_get_contents( $cache_file );

		if ( false === $content || '' === $content ) {
			return null;
		}

		$decoded = json_decode( $content, true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Cache shortcode module definitions for perf runs.
	 *
	 * @since ??
	 *
	 * @param array<string, mixed> $definitions Shortcode module definitions.
	 *
	 * @return void
	 */
	public static function cache_shortcode_module_definitions( array $definitions ): void {
		if ( true !== self::_should_cache_shortcode_module_definitions() ) {
			return;
		}

		$cache_file = self::_get_shortcode_module_definitions_cache_file();
		$cache_dir  = dirname( $cache_file );

		if ( ! is_dir( $cache_dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Local perf cache directory.
			@mkdir( $cache_dir, 0755, true );
		}

		$encoded = wp_json_encode( $definitions );
		if ( false === $encoded ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Local perf cache write.
		file_put_contents( $cache_file, $encoded );
	}

	/**
	 * Determine whether Visual Builder perf test mode is enabled.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function _is_performance_test_mode(): bool {
		$env_value = getenv( self::PERF_ENABLE_ENV );

		if ( false !== $env_value ) {
			return in_array( strtolower( trim( (string) $env_value ) ), [ '1', 'true', 'yes', 'on' ], true );
		}

		if ( defined( self::PERF_ENABLE_CONST ) ) {
			return true === (bool) constant( self::PERF_ENABLE_CONST );
		}

		return false;
	}

	/**
	 * Determine whether shortcode definitions cache is enabled.
	 *
	 * @since ??
	 *
	 * @return bool
	 */
	private static function _should_cache_shortcode_module_definitions(): bool {
		if ( true !== self::_is_performance_test_mode() ) {
			return false;
		}

		$env_value = getenv( self::CACHE_MODULE_DEFS_ENV );
		if ( false !== $env_value ) {
			return in_array( strtolower( trim( (string) $env_value ) ), [ '1', 'true', 'yes', 'on' ], true );
		}

		if ( defined( self::CACHE_MODULE_DEFS_CONST ) ) {
			return true === (bool) constant( self::CACHE_MODULE_DEFS_CONST );
		}

		return false;
	}

	/**
	 * Get shortcode module definitions cache file path.
	 *
	 * Cache key is tied to perf-affecting site context to avoid stale payload reuse.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	private static function _get_shortcode_module_definitions_cache_file(): string {
		$builder_version = defined( 'ET_BUILDER_VERSION' ) ? ET_BUILDER_VERSION : '0';
		$locale          = function_exists( 'get_locale' ) ? (string) get_locale() : 'unknown';
		$blog_id         = function_exists( 'get_current_blog_id' ) ? (string) get_current_blog_id() : '0';
		$stylesheet      = function_exists( 'get_stylesheet' ) ? (string) get_stylesheet() : 'unknown';
		$active_plugins  = (array) get_option( 'active_plugins', [] );
		sort( $active_plugins );

		$hash_source = sprintf(
			'%s|%s|%s|%s|%s',
			$builder_version,
			$locale,
			$blog_id,
			$stylesheet,
			wp_json_encode( $active_plugins )
		);

		return sprintf(
			'%s/%sshortcode_module_definitions_%s.json',
			self::_get_cache_dir(),
			self::_get_cache_file_prefix(),
			md5( $hash_source )
		);
	}

	/**
	 * Get the performance cache directory path.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	private static function _get_cache_dir(): string {
		if ( function_exists( 'et_core_cache_dir' ) ) {
			return et_core_cache_dir()->path . '/perf';
		}

		return WP_CONTENT_DIR . '/et-cache/perf';
	}

	/**
	 * Get the cache file prefix for the current context.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	private static function _get_cache_file_prefix(): string {
		if (
			Conditions::is_vb_enabled()
			|| Conditions::is_vb_app_window()
			|| Conditions::is_vb_top_window()
		) {
			return 'e2e-vb-';
		}

		return 'e2e-fe-';
	}
}
