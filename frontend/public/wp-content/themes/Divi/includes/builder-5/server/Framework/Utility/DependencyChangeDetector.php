<?php
/**
 * Dependency Change Detector utility class.
 *
 * @package ET\Builder\Framework\Utility
 * @since ??
 */

namespace ET\Builder\Framework\Utility;

/**
 * Dependency Change Detector
 *
 * Tracks when plugins or themes are installed, activated, deactivated, updated,
 * or switched to determine if attrs maps cache should be invalidated.
 *
 * @since ??
 */
class DependencyChangeDetector {

	/**
	 * Option key for storing the last dependency change timestamp.
	 */
	const OPTION_KEY = 'divi_last_dependency_change';

	/**
	 * Option key for storing the tracked Divi version.
	 */
	const VERSION_OPTION_KEY = 'divi_tracked_version';

	/**
	 * Initialize dependency change detection hooks.
	 */
	public static function init(): void {
		// When plugins are activated or deactivated.
		add_action( 'activated_plugin', [ self::class, 'on_dependency_change' ] );
		add_action( 'deactivated_plugin', [ self::class, 'on_dependency_change' ] );

		// When themes or plugins get updated.
		add_action( 'upgrader_process_complete', [ self::class, 'on_dependency_update' ], 10, 2 );

		// When themes are switched.
		add_action( 'switch_theme', [ self::class, 'on_dependency_change' ] );
		add_action( 'after_switch_theme', [ self::class, 'on_dependency_change' ] );

		// Check for Divi version changes only in admin context.
		// This avoids unnecessary checks on every frontend page load.
		// The transient cache ensures this check only runs once per hour even in admin.
		add_action( 'admin_init', [ self::class, 'check_version_change' ] );
	}

	/**
	 * Handle dependency change events.
	 *
	 * @param mixed $dependency Plugin file, theme name, or other data.
	 */

	/**
	 * Handle dependency change.
	 *
	 * @since ??
	 *
	 * @param mixed $dependency Dependency.
	 *
	 * @return void
	 */
	public static function on_dependency_change( $dependency = null ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Parameter reserved for future use.
		self::_update_change_timestamp();
	}

	/**
	 * Handle dependency update events.
	 *
	 * @param \WP_Upgrader $upgrader WP_Upgrader instance.
	 * @param array        $hook_extra Array of bulk item update data.
	 */
	public static function on_dependency_update( $upgrader, $hook_extra ): void {
		// Check if this is a plugin or theme update.
		if ( isset( $hook_extra['type'] ) && in_array( $hook_extra['type'], [ 'plugin', 'theme' ], true ) ) {
			self::_update_change_timestamp();

		}
	}


	/**
	 * Update the dependency change timestamp.
	 */
	private static function _update_change_timestamp(): void {
		$timestamp = time();
		update_option( self::OPTION_KEY, $timestamp, false );

		// Also update a transient for faster access.
		set_transient( 'divi_dependency_change_flag', $timestamp, HOUR_IN_SECONDS );
	}

	/**
	 * Get the last dependency change timestamp.
	 *
	 * @return int Unix timestamp of last dependency change.
	 */
	public static function get_last_change_timestamp(): int {
		// Try transient first for performance.
		$timestamp = get_transient( 'divi_dependency_change_flag' );

		if ( false === $timestamp ) {
			// Fall back to option.
			$timestamp = get_option( self::OPTION_KEY, 0 );

			// If timestamp has never been set, initialize it now.
			// This provides a baseline for cache validation on first run.
			if ( 0 === $timestamp ) {
				$timestamp = time();
				update_option( self::OPTION_KEY, $timestamp, false );
			}

			// Cache in transient.
			set_transient( 'divi_dependency_change_flag', $timestamp, HOUR_IN_SECONDS );
		}

		return (int) $timestamp;
	}

	/**
	 * Check if dependencies have changed since a given timestamp.
	 *
	 * @param int $since_timestamp Timestamp to compare against.
	 * @return bool True if dependencies changed since the given timestamp.
	 */
	public static function has_changed_since( int $since_timestamp ): bool {
		$last_change = self::get_last_change_timestamp();
		return $last_change > $since_timestamp;
	}

	/**
	 * Force update the change timestamp (useful for manual cache clearing).
	 */
	public static function force_update(): void {
		self::_update_change_timestamp();
	}

	/**
	 * Check if the Divi core builder version has changed and update timestamp if it has.
	 *
	 * This method runs on the 'init' hook to detect version changes from:
	 * - Version rollback feature
	 * - Manual updates/FTP uploads
	 * - Switching between different Divi builds
	 * - Child theme switches affecting parent Divi version
	 */
	public static function check_version_change(): void {
		// Use transient to avoid checking on every request.
		$transient_key = 'divi_version_check_done';
		if ( get_transient( $transient_key ) ) {
			return;
		}

		// Get current Divi core builder version.
		$current_version = defined( 'ET_CORE_VERSION' ) ? ET_CORE_VERSION : '';

		// Get stored version.
		$stored_version = get_option( self::VERSION_OPTION_KEY, '' );

		// If this is the first time or version has changed.
		if ( '' === $stored_version ) {
			// Initialize with current version.
			update_option( self::VERSION_OPTION_KEY, $current_version, false );
		} elseif ( $stored_version !== $current_version ) {
			// Version changed - update timestamp.
			self::_update_change_timestamp();

			// Update stored version.
			update_option( self::VERSION_OPTION_KEY, $current_version, false );
		}

		// Set transient to avoid checking again for an hour.
		set_transient( $transient_key, true, HOUR_IN_SECONDS );
	}

	/**
	 * Get dependency change data for the Visual Builder.
	 *
	 * @return array Dependency change information.
	 */
	public static function get_change_data(): array {
		return [
			'lastDependencyChange' => self::get_last_change_timestamp(),
			'currentTime'          => time(),
		];
	}
}
