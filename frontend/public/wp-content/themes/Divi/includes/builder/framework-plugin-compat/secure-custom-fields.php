<?php
/**
 * Plugin compatibility for Secure Custom Fields.
 *
 * @package Divi
 * @since 5.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Bail if ACF/ACF Pro is active - ACF compat will handle everything.
if ( is_plugin_active( 'advanced-custom-fields/acf.php' )
	|| is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) ) {
	return;
}

// Load ACF compat class which will detect SCF as fallback.
require_once 'advanced-custom-fields.php';
