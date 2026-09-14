<?php
/**
 * Compatibility for the Secure Custom Fields plugin.
 *
 * @package Divi
 * @since 5.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	// Exit if accessed directly.
	exit;
}

/**
 * Compatibility for the Secure Custom Fields plugin.
 *
 * @since 5.0.0
 *
 * @link https://wordpress.org/plugins/secure-custom-fields/
 */

// Bail if ACF/ACF Pro is active - ACF compat will handle everything.
if ( is_plugin_active( 'advanced-custom-fields/acf.php' )
	|| is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) ) {
	return;
}

// Load ACF compat class which will detect SCF as fallback.
require_once 'advanced-custom-fields.php';
