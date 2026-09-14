<?php
/**
 * D5 Readiness Helper functions.
 *
 * @since ??
 *
 * @package D5_Readiness
 */

namespace Divi\D5_Readiness\Helpers;

use ET\Builder\FrontEnd\Assets\DetectFeature;
use ET\Builder\FrontEnd\BlockParser\BlockParser;
use Divi\D5_Readiness\Server\AJAXEndpoints\CompatibilityChecks;
use Divi\D5_Readiness\Server\PostTypes;

/**
 * Get the list of modules that are ready and not ready for D5 conversation.
 *
 * @return array
 */
function get_modules_conversation_status() : array {
	$used_modules_names = maybe_unserialize( get_transient( 'et_d5_readiness_used_modules' ) );

	$ready_modules     = isset( $used_modules_names['will_convert'] ) ? array_values( $used_modules_names['will_convert'] ) : [];
	$not_ready_modules = isset( $used_modules_names['will_not_convert'] ) ? array_values( $used_modules_names['will_not_convert'] ) : [];

	return [
		'ready'     => $ready_modules,
		'not_ready' => $not_ready_modules,
	];
}

/**
 * Get the list of road map items that are in-progress and completed items.
 *
 * @return array
 */
function get_roadmap_items() {
	// Fetch the Roadmap Items JSON file from the remote Divi Docs URL.
	$response = wp_remote_get( 'https://devalpha.elegantthemes.com/json/roadmapItems.json' );

	// Check for errors.
	if ( is_wp_error( $response ) ) {
		$roadmap_items = []; // Empty array in case of error.
	} else {
		$roadmap_items = json_decode( wp_remote_retrieve_body( $response ), true );
	}

	return $roadmap_items;
}

/**
 * Get the list of cached road map items.
 *
 * @return array
 */
function get_cached_roadmap_items() {
	$transient_name = 'et-d5-roadmap-items';

	// Try to get the data from the cache (transient).
	$cached_data = get_transient( $transient_name );

	// If cached data exists, return it.
	if ( false !== $cached_data ) {
		return $cached_data;
	}

	$data = get_roadmap_items();

	// Store the data in a transient for future use.
	set_transient( $transient_name, $data, HOUR_IN_SECONDS );

	return $data;
}

/**
 * Check if the rollback is needed.
 *
 * @return bool
 */
function is_rollback_needed() {
	$post_types = PostTypes::get_post_type_slugs();

	$post_ids = [];

	// Collect post IDs from all relevant post types.
	foreach ( $post_types as $post_type ) {
		$args = [
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'meta_query'     => [
				[
					'key'     => '_et_pb_divi_4_content',
					'compare' => 'EXISTS',
				],
			],
		];

		$post_ids = array_merge( $post_ids, get_posts( $args ) );
	}

	return ! empty( $post_ids );
}

/**
 * Check if the conversion is finished.
 *
 * @return bool
 */
function is_conversion_finished() {
	return et_get_option( 'et_d5_readiness_conversion_finished', false );
}

/**
 * Get cached third-party module lookup map.
 *
 * The function creates a cached lookup map of third-party module D4 shortcode names to their corresponding block names.
 * It only processes third-party modules to optimize performance and uses static caching to avoid rebuilding the map on
 * subsequent calls.
 *
 * @since ??
 *
 * @return array Associative array mapping `d4Shortcode` to block name for third-party modules.
 */
function get_third_party_module_lookup_map() {
	static $conversion_map = null;

	if ( null !== $conversion_map ) {
		return $conversion_map;
	}

	$conversion_map = [];
	$modules        = CompatibilityChecks::third_party_module_slugs();

	// Get module collections (cached and lightweight).
	$module_collections = \ET\Builder\Packages\Conversion\Conversion::getModuleCollections();

	// Build lookup map only for third-party modules.
	foreach ( $module_collections as $module ) {
		if ( isset( $module['d4Shortcode'] ) && array_key_exists( $module['d4Shortcode'], $modules ) ) {
			$conversion_map[ $module['d4Shortcode'] ] = $module['name'];
		}
	}

	return $conversion_map;
}

/**
 * Check if a third-party module has conversion support.
 *
 * This function checks if a third-party module has proper conversion outlines implemented by looking for the module in
 * the conversion map. This allows the D5 readiness system to properly classify third-party modules as compatible when
 * they have conversion support, instead of automatically marking them as incompatible.
 *
 * @since ??
 *
 * @param string $shortcode_name The module shortcode name to check.
 *
 * @return bool True if the module has conversion support, false otherwise.
 */
function is_third_party_module_convertible( $shortcode_name ) {
	// Get the cached lookup map for third-party modules.
	$conversion_map = get_third_party_module_lookup_map();
	$module_name    = $conversion_map[ $shortcode_name ] ?? null;

	if ( ! $module_name ) {
		// Module not found in the conversion map, so it doesn't have conversion support.
		return false;
	}

	if ( 'et_pb_shop' === $shortcode_name || et_()->starts_with( $shortcode_name, 'et_pb_wc_' ) ) {
		// No need to check the conversion outlines for WooCommerce modules, as we know they have them
		return true;
	}

	// Get the conversion map that contains all registered conversion outlines.
	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores,WordPress.NamingConventions.ValidHookName.NotLowercase -- This is a valid hook name.
	$conversion_outlines = apply_filters( 'divi.conversion.moduleLibrary.conversionMap', [] );

	// Check if the module exists in the conversion map.
	// If it does, it means the module has conversion support.
	return isset( $conversion_outlines[ $module_name ] );
}

/**
 * Get Module Name from Registered Modules.
 *
 * @since ??
 *
 * @param string $slug The module slug.
 * @param array  $modules The list of registered modules.
 *
 * @return string $results Comma separated list of shortcode names found in widget areas.
 */
function readiness_get_module_name_from_slug( $slug, $modules ) : string {
	if ( ! isset( $modules[ $slug ] ) ) {
		return $slug;
	}

	return is_array( $modules[ $slug ] ) ? $modules[ $slug ]['name'] : $modules[ $slug ]->name;
}

/**
 * Get the module names used in the post content.
 *
 * @since ??
 *
 * @param string $content The post content.
 * @param array  $modules The list of registered modules.
 * @param array  $third_party_module_slugs The list of third party module slugs.
 *
 * @return array The module names used in the post content.
 */
function readiness_get_modules_names_from_content( $content, $modules, $third_party_module_slugs ) : array {
	// force the content to be a string.
	$content = empty( $content ) ? '' : $content;
	$shortcode_slugs = DetectFeature::get_shortcode_names( $content );

	$modules_names = [
		'will_convert'     => [],
		'will_not_convert' => [],
	];

	$ignored_slugs = [
		'et_pb_section',
		'et_pb_row',
		'et_pb_column',
		'et_pb_row_inner',
		'et_pb_column_inner',
	];

	foreach ( $shortcode_slugs as $slug ) {
		if ( ! $slug ) {
			continue;
		}

		if ( array_key_exists( $slug, $third_party_module_slugs ) ) { // Third party modules.
			// Check if the third-party module has conversion support.
			$module_name = readiness_get_module_name_from_slug( $slug, $third_party_module_slugs );
			$key = is_third_party_module_convertible( $slug ) ? 'will_convert' : 'will_not_convert';
			$modules_names[ $key ][] = $module_name;
			continue;
		}

		if ( ! in_array( $slug, $ignored_slugs, true ) ) { // Divi Builder modules.
			$modules_names['will_convert'][] = readiness_get_module_name_from_slug( $slug, $modules );
		}
	}

	return $modules_names;
}

/**
 * Update the used modules names.
 *
 * @param array $used_modules_names The used modules names.
 *
 * @since ??
 *
 * @return void
 */
function readiness_update_used_modules_names( $used_modules_names ) {
	$used_modules_names = [
		'will_convert'     => array_unique( $used_modules_names['will_convert'] ),
		'will_not_convert' => array_unique( $used_modules_names['will_not_convert'] ),
	];

	$fifthteen_minutes = 900;

	set_transient( 'et_d5_readiness_used_modules', maybe_serialize( $used_modules_names ), $fifthteen_minutes );
}

/**
 * Clear the modules conversation cache.
 *
 * This function clears the transient cache that stores the list of modules
 * ready for conversion. This should be called after successful migration
 * to ensure the counter reflects the current state.
 *
 * @since ??
 *
 * @return void
 */
function clear_modules_conversation_cache() {
	delete_transient( 'et_d5_readiness_used_modules' );
}

/**
 * Reset module cache once per readiness scan session.
 *
 * @since ??
 *
 * @return void
 */
function maybe_reset_modules_conversation_cache() {
	$scan_key     = 'et_d5_readiness_modules_scan_in_progress';
	$scan_window  = 15 * MINUTE_IN_SECONDS;
	$scan_started = get_transient( $scan_key );

	// Refresh the scan marker to avoid cache resets during long scans.
	set_transient( $scan_key, time(), $scan_window );

	// If scan is running, keep accumulating modules and avoid clearing on each batch.
	if ( false !== $scan_started ) {
		return;
	}

	// Clear once per scan session to avoid stale results from earlier runs.
	clear_modules_conversation_cache();
}
