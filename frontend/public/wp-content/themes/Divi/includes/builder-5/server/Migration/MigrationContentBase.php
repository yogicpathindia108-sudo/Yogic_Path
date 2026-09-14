<?php
/**
 * Migration Content Base Class
 *
 * Abstract base class that provides common functionality for content migration
 * classes. This class implements both MigrationInterface and MigrationContentInterface
 * to provide a foundation for migrating content between different Divi versions.
 *
 * The class provides utility methods for detecting Divi shortcodes and blocks,
 * as well as a unified migration method that handles both content types.
 *
 * @since ??
 *
 * @package Divi
 */

namespace ET\Builder\Migration;

use ET\Builder\Migration\MigrationInterface;
use ET\Builder\Migration\MigrationContentInterface;

/**
 * Abstract base class for content migration implementations.
 *
 * This class provides common functionality for migrating content between
 * different Divi versions, including detection of shortcodes and blocks,
 * and unified migration processing.
 *
 * @since ??
 */
abstract class MigrationContentBase implements MigrationInterface, MigrationContentInterface {

	/**
	 * Checks if the given content contains Divi shortcodes.
	 *
	 * This method performs a simple string check to determine if the content
	 * contains Divi shortcodes by looking for the characteristic '[et_pb_' prefix
	 * that all Divi shortcodes use.
	 *
	 * @since ??
	 *
	 * @param string $content The content to check for Divi shortcodes.
	 *
	 * @return bool True if the content contains Divi shortcodes, false otherwise.
	 */
	public static function has_divi_shortcode( string $content ): bool {
		return 0 === strpos( $content, '[et_pb_' );
	}

	/**
	 * Checks if the given content contains Divi blocks.
	 *
	 * This method performs a string check to determine if the content contains
	 * Divi blocks by looking for the characteristic '<!-- wp:divi/' HTML comment
	 * that marks the beginning of Divi block content.
	 *
	 * @since ??
	 *
	 * @param string $content The content to check for Divi blocks.
	 *
	 * @return bool True if the content contains Divi blocks, false otherwise.
	 */
	public static function has_divi_block( string $content ): bool {
		return false !== strpos( $content, '<!-- wp:divi/' );
	}

	/**
	 * Migrates content from Divi 4 format to Divi 5 format.
	 *
	 * This method handles the migration of content that may contain both shortcodes
	 * and blocks. It processes shortcodes first, then blocks, ensuring proper
	 * migration order. The method is designed to handle mixed content types
	 * efficiently by detecting the content type and applying the appropriate
	 * migration strategy.
	 *
	 * @since ??
	 *
	 * @param string $content The content to migrate.
	 *
	 * @return string The migrated content.
	 */
	public static function migrate_content_both( string $content ): string {
		if ( '' === $content ) {
			return $content;
		}

		if ( static::has_divi_shortcode( $content ) ) {
			$content = static::migrate_content_shortcode( $content );
		}

		if ( static::has_divi_block( $content ) ) {
			$content = static::migrate_content_block( $content );
		}

		return $content;
	}
}
