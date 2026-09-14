<?php
/**
 * Migration Interface
 *
 * Defines the contract for all migration classes.
 *
 * @package Divi
 */

namespace ET\Builder\Migration;

interface MigrationInterface {

	/**
	 * Get the migration name.
	 *
	 * @since ??
	 *
	 * @return string The migration name.
	 */
	public static function get_name();

	/**
	 * Get the release version for this migration.
	 *
	 * @since ??
	 *
	 * @return string The release version.
	 */
	public static function get_release_version(): string;

	/**
	 * Run the migration.
	 *
	 * This method is called when the migration is loaded.
	 * You can use this method to register hooks and other actions.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function load(): void;
}
