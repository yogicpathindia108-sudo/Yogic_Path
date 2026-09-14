<?php
/**
 * Gutenberg: Preview Class (PLACEHOLDER)
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\Gutenberg;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Preview class.
 *
 * Handles Layout Block preview functionality (placeholder implementation).
 *
 * RESPONSIBILITIES (to be implemented):
 * - Preview template registration
 * - Preview asset enqueuing
 * - Content modification for preview
 * - Meta modification
 * - Theme Builder integration
 * - Box shadow attributes
 *
 * STATUS: Implementation details to be determined. This file will handle the preview system
 * for Layout Blocks in the Gutenberg editor, ensuring proper rendering in the preview iframe.
 *
 * @since ??
 */
class Preview {

	/**
	 * Placeholder method - to be implemented.
	 *
	 * @since ??
	 */
	public function __construct() {
		// Preview system to be implemented separately.
	}

	/**
	 * Get Theme Builder's template settings of current (layout block preview) page.
	 *
	 * Delegates to ET_GB_Block_Layout utility class.
	 *
	 * @since ??
	 *
	 * @return array
	 */
	public static function get_preview_tb_template() {
		return \ET_GB_Block_Layout::get_preview_tb_template();
	}
}
