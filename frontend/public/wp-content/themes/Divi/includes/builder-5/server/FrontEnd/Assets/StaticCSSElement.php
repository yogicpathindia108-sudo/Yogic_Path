<?php
/**
 * Static CSS Element
 *
 * Represents a static CSS element with layout information, custom CSS,
 * and style managers for rendering CSS assets.
 *
 * @package Divi
 *
 * @since ??
 */

namespace ET\Builder\FrontEnd\Assets;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}


use ET_Core_PageResource;

/**
 * Static CSS Element class.
 *
 * Handles static CSS element data including layout type, layout ID,
 * custom CSS, and style managers for both regular and deferred styles.
 *
 * @since ??
 */
class StaticCSSElement {

	/**
	 * Layout type.
	 *
	 * @var string
	 */
	private $_layout_type;

	/**
	 * Layout ID.
	 *
	 * @var int
	 */
	private $_layout_id;

	/**
	 * Custom CSS.
	 *
	 * @var string
	 */
	private $_custom_css;

	/**
	 * Styles manager.
	 *
	 * @var ?ET_Core_PageResource
	 */
	private $_styles_manager;

	/**
	 * Deferred styles manager.
	 *
	 * @var ?ET_Core_PageResource
	 */
	private $_deferred_styles_manager;

	/**
	 * Priority for CSS loading order.
	 *
	 * @var int
	 */
	private $_priority;

	/**
	 * Styles data organized by group.
	 *
	 * @var array
	 */
	private $_styles_data = [];

	/**
	 * Constructor.
	 *
	 * @param string                $layout_type Layout type.
	 * @param int                   $layout_id Layout ID.
	 * @param string                $custom_css Custom CSS.
	 * @param ?ET_Core_PageResource $styles_manager Styles manager.
	 * @param ?ET_Core_PageResource $deferred_styles_manager Deferred styles manager.
	 */
	public function __construct(
		string $layout_type,
		int $layout_id,
		string $custom_css,
		?ET_Core_PageResource $styles_manager = null,
		?ET_Core_PageResource $deferred_styles_manager = null
	) {
		$this->_layout_type             = $layout_type;
		$this->_layout_id               = $layout_id;
		$this->_custom_css              = $custom_css;
		$this->_styles_manager          = $styles_manager;
		$this->_deferred_styles_manager = $deferred_styles_manager;

		switch ( $layout_type ) {
			case 'et_header_layout':
				$this->_priority = 10;
				break;
			case 'et_body_layout':
				$this->_priority = 20;
				break;
			case 'et_footer_layout':
				$this->_priority = 40;
				break;
			default:
				$this->_priority = 30;
				break;
		}
	}

	/**
	 * Get layout type.
	 *
	 * @since ??
	 *
	 * @return string Layout type.
	 */
	public function get_layout_type(): string {
		return $this->_layout_type;
	}

	/**
	 * Get layout ID.
	 *
	 * @since ??
	 *
	 * @return int Layout ID.
	 */
	public function get_layout_id(): int {
		return $this->_layout_id;
	}

	/**
	 * Get custom CSS.
	 *
	 * @since ??
	 *
	 * @return string Custom CSS.
	 */
	public function get_custom_css(): string {
		return $this->_custom_css;
	}

	/**
	 * Get styles manager.
	 *
	 * @since ??
	 *
	 * @return ?ET_Core_PageResource Styles manager, or null if not set.
	 */
	public function get_styles_manager(): ?ET_Core_PageResource {
		return $this->_styles_manager;
	}

	/**
	 * Get deferred styles manager.
	 *
	 * @since ??
	 *
	 * @return ?ET_Core_PageResource Deferred styles manager, or null if not set.
	 */
	public function get_deferred_styles_manager(): ?ET_Core_PageResource {
		return $this->_deferred_styles_manager;
	}

	/**
	 * Get priority.
	 *
	 * @since ??
	 *
	 * @return int Priority value for CSS loading order.
	 */
	public function get_priority(): int {
		return $this->_priority;
	}

	/**
	 * Get styles data.
	 *
	 * @since ??
	 *
	 * @return array Styles data organized by group.
	 */
	public function get_styles_data(): array {
		return $this->_styles_data;
	}

	/**
	 * Set styles data for a specific group.
	 *
	 * @since ??
	 *
	 * @param string $styles_group The group identifier for the styles data.
	 * @param array  $styles_data  The styles data to store for the group.
	 *
	 * @return void
	 */
	public function set_styles_data( string $styles_group, array $styles_data ): void {
		$this->_styles_data[ $styles_group ] = $styles_data;
	}
}
