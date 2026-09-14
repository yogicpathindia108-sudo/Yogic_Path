<?php
/**
 * Plugin compatibility for Duplicate Page
 *
 * Handles compatibility issue with the Duplicate Page plugin where D5 content
 * becomes corrupted during post duplication due to missing slashing.
 *
 * @package Divi
 * @since 5.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use ET\Builder\VisualBuilder\Saving\SavingUtility;

/**
 * Plugin compatibility for Duplicate Page
 *
 * @since 5.0.0
 * @link https://wordpress.org/plugins/duplicate-page/
 */
class ET_Builder_Plugin_Compat_Duplicate_Page extends ET_Builder_Plugin_Compat_Base {
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->plugin_id = 'duplicate-page/duplicatepage.php';
		$this->init_hooks();
	}

	/**
	 * Hook methods to WordPress
	 * Latest plugin version: 4.5.5.
	 *
	 * @return void
	 */
	public function init_hooks() {
		// Bail if there's no version found.
		if ( ! $this->get_plugin_version() ) {
			return;
		}

		// Hook into wp_insert_post_data with priority 1 to fix missing slashing before other filters run.
		// The Duplicate Page plugin does not apply wp_slash() to the content when the editor setting is 'classic' or 'all',
		// so we need to ensure the content is properly slashed before WordPress core or other filters process it.
		add_filter( 'wp_insert_post_data', [ $this, 'fix_duplicate_page_slashing' ], 1, 2 );
	}

	/**
	 * Fix slashing issue when Duplicate Page plugin duplicates D5 content.
	 *
	 * The Duplicate Page plugin only applies wp_slash() to content when the editor setting
	 * is 'gutenberg'. When the setting is 'classic' or 'all', it does NOT apply wp_slash().
	 *
	 * D5 content with JSON-encoded Unicode sequences needs to be slashed before wp_insert_post()
	 * because WordPress core will unslash it during processing. Without slashing, the Unicode
	 * sequences get corrupted.
	 *
	 * This method ensures D5 content is properly slashed when the plugin is duplicating a post.
	 *
	 * @since 5.0.0
	 *
	 * @param array $data    An array of slashed, sanitized, and processed post data.
	 * @param array $postarr An array of sanitized (and slashed) but otherwise unmodified post data.
	 *
	 * @return array Modified post data with corrected slashing for D5 content.
	 */
	public function fix_duplicate_page_slashing( array $data, array $postarr ): array {
		// Only apply fix when Duplicate Page plugin is duplicating a post.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification is handled by the plugin.
		if ( ! isset( $_REQUEST['action'] ) || 'dt_duplicate_post_as_draft' !== $_REQUEST['action'] ) {
			return $data;
		}

		if ( isset( $data['post_content'] ) ) {
			$data['post_content'] = SavingUtility::maybe_add_slash( $data['post_content'] );
		}

		return $data;
	}
}

new ET_Builder_Plugin_Compat_Duplicate_Page();
