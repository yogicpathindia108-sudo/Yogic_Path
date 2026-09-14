<?php
/**
 * Plugin uninstall handler (WordPress magic file).
 *
 * Runs in an isolated request with the plugin not bootstrapped, so it loads
 * just the autoloader to resolve the per-domain uninstall handlers.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/inc/autoload.php';

\CloudLinux\Imunify\App\Lifecycle\Lifecycle::uninstall();
