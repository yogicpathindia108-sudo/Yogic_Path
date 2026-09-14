<?php
/**
 * ET_Builder_Plugin_Compat_DiviFlash class file.
 *
 * @package Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	// Exit if accessed directly.
	exit;
}

/**
 * Keeps DiviFlash popup module-order indexes unique across isolated popup renders.
 * Applies restart continuation only in popup render context,
 * so normal page module ordering stays stable.
 *
 * @since 5.0.0
 */
class ET_Builder_Plugin_Compat_DiviFlash extends ET_Builder_Plugin_Compat_Base {
	/**
	 * Whether current execution is inside DiviFlash popup render window.
	 *
	 * @var bool
	 */
	private static $_is_popup_render_context = false;

	/**
	 * Highest seen order index per order type and module slug in current request.
	 *
	 * @since 5.0.0
	 *
	 * @var array
	 */
	private $_max_seen_order_index = [];

	/**
	 * Whether current execution stack is inside DiviFlash popup isolated render.
	 *
	 * @return bool
	 * @since 5.0.0
	 */
	private function _is_popup_render_context() {
		return self::$_is_popup_render_context;
	}

	/**
	 * Mark the start of DiviFlash popup render context.
	 *
	 * @return void
	 * @since 5.0.0
	 */
	public function mark_popup_render_context_start() {
		self::$_is_popup_render_context = true;
	}

	/**
	 * Mark the end of DiviFlash popup render context.
	 *
	 * @return void
	 * @since 5.0.0
	 */
	public function mark_popup_render_context_end() {
		self::$_is_popup_render_context = false;
	}

	/**
	 * Constructor.
	 *
	 * @since 5.0.0
	 */
	public function __construct() {
		$this->plugin_id = 'diviflash/diviflash.php';
		$this->init_hooks();
	}

	/**
	 * Hook methods to WordPress.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public function init_hooks() {
		if ( ! $this->get_plugin_version() ) {
			return;
		}

		add_filter(
			'et_builder_module_order_adjusted_index',
			[ $this, 'maybe_adjust_module_order_index' ],
			10,
			4
		);

		if ( function_exists( 'showPopup' ) && function_exists( 'df_render_library_layout_for_popup' ) ) {
			// DiviFlash popup output is generated from wp_footer via DF_Popup_Process::init().
			// Create a runtime context window around that render pass.
			add_action( 'wp_footer', [ $this, 'mark_popup_render_context_start' ], 9 );
			add_action( 'wp_footer', [ $this, 'mark_popup_render_context_end' ], 11 );
		}
	}

	/**
	 * Adjust module order index for DiviFlash popup isolated render restarts.
	 *
	 * DiviFlash popup rendering can execute an isolated shortcode pass in the same request,
	 * which may restart module-order indexes from `-1 -> 0` and collide with already-rendered
	 * classes in main content.
	 *
	 * @since 5.0.0
	 *
	 * @param int    $index           Current index value.
	 * @param string $index_type      Index type.
	 * @param string $module_slug     Module slug.
	 * @param string $layout_type     Layout type.
	 * @return int
	 */
	public function maybe_adjust_module_order_index( $index, $index_type, $module_slug, $layout_type ) {
		$previous_index = ET_Builder_Module_Order::get_index( $index_type, $module_slug, $layout_type );
		$adjusted_index = (int) $index;

		if ( ! isset( $this->_max_seen_order_index[ $index_type ] ) || ! is_array( $this->_max_seen_order_index[ $index_type ] ) ) {
			$this->_max_seen_order_index[ $index_type ] = [];
		}

		$max_seen_by_slug = &$this->_max_seen_order_index[ $index_type ];
		$max_seen         = (int) ( $max_seen_by_slug[ $module_slug ] ?? -1 );

		if (
			0 === $adjusted_index
			&& -1 === (int) $previous_index
			&& 0 <= $max_seen
			&& 'default' === (string) $layout_type
			&& $this->_is_popup_render_context()
		) {
			$adjusted_index = $max_seen + 1;
		}

		if ( $adjusted_index > $max_seen ) {
			$max_seen_by_slug[ $module_slug ] = $adjusted_index;
		}

		return $adjusted_index;
	}
}

new ET_Builder_Plugin_Compat_DiviFlash();
