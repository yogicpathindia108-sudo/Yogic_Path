<?php
/**
 * Migration Context Manager
 *
 * Manages the migration context state to prevent interference between
 * migration processes and normal rendering operations.
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Migration;

/**
 * Migration Context Manager Class.
 *
 * @since ??
 */
class MigrationContext {

	/**
	 * Whether migration is currently in progress.
	 *
	 * @since ??
	 *
	 * @var bool
	 */
	private static $_is_in_migration = false;

	/**
	 * Set migration state to active.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function start(): void {
		self::$_is_in_migration = true;
	}

	/**
	 * Set migration state to inactive.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function end(): void {
		self::$_is_in_migration = false;
	}

	/**
	 * Check if migration is currently in progress.
	 *
	 * @since ??
	 *
	 * @return bool True if migration is active, false otherwise.
	 */
	public static function is_active(): bool {
		return self::$_is_in_migration;
	}

	/**
	 * Reset migration state to inactive.
	 *
	 * This method ensures migration state is clean, useful for error recovery.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$_is_in_migration = false;
	}
}
