<?php
/**
 * LazyAssetLoader class.
 *
 * @package Divi
 *
 * @since ??
 */

namespace ET\Builder\VisualBuilder\Assets;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\Utility\TextTransform;

/**
 * Utility for registering inline lazy loaders for scripts/styles.
 *
 * @since ??
 */
class LazyAssetLoader {
	/**
	 * Ensure a package script (and its package-script dependencies) is registered.
	 *
	 * @since ??
	 *
	 * @param string $handle    Script handle.
	 * @param array  $resolving Stack of currently resolving handles.
	 *
	 * @return void
	 */
	private static function _maybe_register_package_script( string $handle, array &$resolving = [] ): void {
		if ( '' === $handle || wp_script_is( $handle, 'registered' ) ) {
			return;
		}

		if ( in_array( $handle, $resolving, true ) ) {
			return;
		}

		$package = PackageBuildManager::get_package_build( $handle );
		$script  = $package['script'] ?? [];
		$src     = $script['src'] ?? '';

		if ( '' === $src ) {
			return;
		}

		$resolving[] = $handle;

		$deps = $script['deps'] ?? [];

		if ( is_array( $deps ) ) {
			foreach ( $deps as $dep ) {
				if ( is_string( $dep ) && '' !== $dep ) {
					self::_maybe_register_package_script( $dep, $resolving );
				}
			}
		}

		wp_register_script(
			$handle,
			$src,
			$deps,
			$package['version'] ?? '',
			$script['args'] ?? [ 'in_footer' => true ]
		);

		array_pop( $resolving );
	}

	/**
	 * Runtime script handle.
	 *
	 * @var string
	 */
	private const RUNTIME_SCRIPT_HANDLE = 'divi-script-library-lazy-asset-loader';

	/**
	 * Ensure runtime script is registered.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	private static function _maybe_register_runtime_script(): void {
		$package_dependencies = [];
		self::_maybe_register_package_script( self::RUNTIME_SCRIPT_HANDLE, $package_dependencies );
	}

	/**
	 * Enqueue a reusable lazy asset loader.
	 *
	 * @since ??
	 *
	 * @param string $loader_handle Loader script handle.
	 * @param array  $assets        Assets configuration.
	 * @param array  $options       Loader options.
	 *
	 * @return void
	 */
	public static function enqueue_loader( $loader_handle, $assets = [], $options = [] ): void {
		$default_assets  = [
			'scripts' => [],
			'styles'  => [],
			'globals' => [],
		];
		$default_options = [
			'trigger_event'        => '',
			'trigger_message_type' => '',
			'trigger_window_flag'  => '',
			'wait_for_preloader'   => false,
			'auto_attempt'         => true,
		];

		$assets      = wp_parse_args( $assets, $default_assets );
		$options     = wp_parse_args( $options, $default_options );
		$data_object = TextTransform::pascal_case( "{$loader_handle}_config" );
		$config      = [
			'id'      => $loader_handle,
			'assets'  => $assets,
			'options' => $options,
		];

		self::_maybe_register_runtime_script();

		// Pass loader config to the shared runtime, then register the loader instance by object name.
		wp_enqueue_script( self::RUNTIME_SCRIPT_HANDLE );
		wp_localize_script( self::RUNTIME_SCRIPT_HANDLE, $data_object, $config );
		wp_add_inline_script(
			self::RUNTIME_SCRIPT_HANDLE,
			"window.DiviLazyAssetLoader&&window.DiviLazyAssetLoader.register(window.{$data_object});",
			'after'
		);
	}
}
