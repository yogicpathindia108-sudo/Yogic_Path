<?php
/**
 * Migration Content Interface
 *
 * Defines the contract for migration classes that handle content data migration.
 *
 * @package Divi
 */

namespace ET\Builder\Migration;

/**
 * Migration Content Interface
 *
 * Defines the contract for migration classes that handle content data migration.
 *
 * @package Divi
 */
interface MigrationContentInterface {

	/**
	 * Migrate shortcode content.
	 *
	 * This method processes shortcode content and applies necessary transformations
	 * based on the migration requirements. It should handle the conversion of
	 * shortcode attributes, content, and structure as needed for the target version.
	 *
	 * @since ??
	 *
	 * @param string $content The shortcode content to migrate.
	 *
	 * @return string The migrated shortcode content.
	 */
	public static function migrate_content_shortcode( string $content ): string;

	/**
	 * Migrate block content.
	 *
	 * This method processes block content and applies necessary transformations
	 * based on the migration requirements. It should handle the conversion of
	 * block attributes, content, and structure as needed for the target version.
	 *
	 * @since ??
	 *
	 * @param string $content The block content to migrate.
	 *
	 * @return string The migrated block content.
	 */
	public static function migrate_content_block( string $content ): string;

	/**
	 * Migrate content both.
	 *
	 * This method migrates both shortcode and block content.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string The migrated content.
	 */
	public static function migrate_content_both( string $content ): string;
}
