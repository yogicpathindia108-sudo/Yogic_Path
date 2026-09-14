<?php
/**
 * Imunify Security plugin for WordPress.
 *
 * @copyright Copyright 2010-2026 CloudLinux Inc.
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 or later
 *
 * @wordpress-plugin
 * Plugin Name: Imunify Security
 * Plugin URI: https://imunify360.com/imunify-security-wp-plugin/
 * Description: Imunify Security WordPress plugin is a comprehensive tool offering malware scanning, firewall protection, and intrusion detection for WordPress websites.
 * Version: 4.1.0
 * Requires at least: 5.0.0
 * Requires PHP: 5.6
 * Author: CloudLinux
 * Author URI: https://www.cloudlinux.com
 * Text Domain: imunify-security
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Imunify Security plugin for WordPress is free software: you can
 * redistribute it and/or modify it under the terms of the GNU General
 * Public License as published by the Free Software Foundation, either
 * version 2 of the License, or any later version.
 *
 * Imunify Security plugin for WordPress is distributed in the hope
 * that it will be useful, but WITHOUT ANY WARRANTY; without even the
 * implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
 * PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this plugin. If not, see https://www.gnu.org/licenses/
 */

use CloudLinux\Imunify\App\Plugin;

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'IMUNIFY_SECURITY_SLUG', 'imunify-security' );
define( 'IMUNIFY_SECURITY_PATH', __DIR__ );
define( 'IMUNIFY_SECURITY_VERSION', '4.1.0' );
define( 'IMUNIFY_SECURITY_FILE_PATH', __FILE__ );

require_once __DIR__ . '/inc/autoload.php';

register_activation_hook(
	__FILE__,
	array( \CloudLinux\Imunify\App\Lifecycle\Lifecycle::class, 'activate' )
);

register_deactivation_hook(
	__FILE__,
	array( \CloudLinux\Imunify\App\Lifecycle\Lifecycle::class, 'deactivate' )
);

// Uninstall cleanup runs from uninstall.php (the WordPress magic file), not a hook.

add_action( 'plugins_loaded', array( \CloudLinux\Imunify\App\Bot\MuPluginSelfHealer::class, 'check' ) );

try {
	if ( ! class_exists( Plugin::class ) ) {
		return;
	}

	Plugin::instance()->init();
} catch ( \Exception $e ) {
	do_action(
		'imunify_security_set_error',
		E_WARNING,
		'Init plugin failed: ' . $e->getMessage(),
		__FILE__,
		__LINE__,
		array(
			'fingerprint' => array( 'init-plugin-failed', get_class( $e ) ),
		)
	);
}
