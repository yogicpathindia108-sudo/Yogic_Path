<?php
/**
 * PSR-0-ish autoloader for CloudLinux\Imunify\* classes. Shared by the main
 * plugin bootstrap and uninstall.php, which runs without the plugin loaded.
 */

if ( ! defined( 'IMUNIFY_SECURITY_PATH' ) ) {
	define( 'IMUNIFY_SECURITY_PATH', dirname( __DIR__ ) );
}

if ( ! defined( 'IMUNIFY_SECURITY_AUTOLOADER_REGISTERED' ) ) {
	define( 'IMUNIFY_SECURITY_AUTOLOADER_REGISTERED', true );

	spl_autoload_register(
		function ( $class ) {
			$prefixes = array(
				'CloudLinux\\Imunify\\Composer\\Semver\\' => IMUNIFY_SECURITY_PATH . '/lib/CloudLinux/Imunify/Composer/Semver/',
				'CloudLinux\\Imunify\\'                   => IMUNIFY_SECURITY_PATH . '/inc/',
			);

			foreach ( $prefixes as $prefix => $base_dir ) {
				if ( 0 === strpos( $class, $prefix ) ) {
					$relative_class = substr( $class, strlen( $prefix ) );
					$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
					// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					if ( @file_exists( $file ) ) {
						include_once $file;
					}
					break;
				}
			}
		}
	);
}
