<?php
/**
 * Classic Editor: ClassicEditor class.
 *
 * @package Divi
 * @since ??
 */

namespace ET\Builder\VisualBuilder\ClassicEditor;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use WP_Post;
use ET\Builder\Packages\WooCommerce\WooCommerceHooks;

/**
 * ClassicEditor class.
 *
 * This class has functionality to enable certain features and provide a placeholder for Classic Editor.
 *
 * @since ??
 */
class ClassicEditor {

	/**
	 * Initialize ClassicEditor
	 *
	 * @since ??
	 */
	public function initialize() {
		// Ajax function used to enable the Divi Builder and save the post.
		add_action( 'wp_ajax_enable_divi_in_classic_editor', [ __CLASS__, 'set_divi_builder_redirect' ] );
		add_action( 'wp_ajax_disable_divi_in_classic_editor', [ __CLASS__, 'set_deactivate_divi_builder' ] );
		add_action( 'save_post', [ __CLASS__, 'redirect_to_builder' ], 10, 3 );
	}

	/**
	 * Check if the Classic Editor is enabled.
	 *
	 * This function checks the value of the 'et_enable_classic_editor' option and returns a boolean indicating
	 * whether the Classic Editor is enabled or not. The option value is compared to `'on'` to determine the status.
	 *
	 * @since ??
	 *
	 * @return bool Whether the Classic Editor is enabled or not.
	 *
	 * @example:
	 * ```php
	 * if (EditorUtils::is_enabled()) {
	 *     // Classic Editor is enabled
	 * } else {
	 *     // Classic Editor is not enabled
	 * }
	 * ```
	 */
	public static function is_enabled(): bool {
		return 'on' === et_get_option( 'et_enable_classic_editor', 'off' );
	}

	/**
	 * Add/load CSS for the Classic Editor placeholder overlay.
	 *
	 * This function adds a style block containing CSS code to customize the appearance of the Classic Editor overlay in the Divi builder.
	 *
	 * @since ??
	 *
	 * @return void
	 */
	public static function add_scripts(): void {
		global $typenow;

		wp_enqueue_style(
			'et-classic-editor',
			ET_BUILDER_5_URI . '/visual-builder/build/classic-editor.css',
			[],
			ET_BUILDER_PRODUCT_VERSION,
			'all'
		);

		wp_enqueue_script(
			'et-classic-editor',
			ET_BUILDER_5_URI . '/visual-builder/build/script-library-classic-editor.js',
			[ 'jquery' ],
			ET_BUILDER_PRODUCT_VERSION,
			true
		);

		$settings = [
			'ajax_url'                             => admin_url( 'admin-ajax.php' ),
			'enable_divi_in_classic_editor_nonce'  => wp_create_nonce( 'enable_divi_in_classic_editor_nonce' ),
			'disable_divi_in_classic_editor_nonce' => wp_create_nonce( 'disable_divi_in_classic_editor_nonce' ),
			'post_type'                            => $typenow,
			'is_third_party_post_type'             => et_builder_is_post_type_custom( $typenow ) ? 'yes' : 'no',
		];

		wp_localize_script( 'et-classic-editor', 'classic_editor_settings', apply_filters( 'classic_editor_settings', $settings ) );
	}

	/**
	 * Get HTML element(s) that open Divi Builder placeholder overlay wrapper for Classic Editor.
	 *
	 * This method is called before the classic editor, and renders the opening
	 * HTML wrapper for the classic editor' Divi Builder placeholder overlay.
	 *
	 * @since ??
	 *
	 * @param \WP_Post $_post The post object.
	 *
	 * @return void
	 */
	public static function html_enable_divi_button( \WP_Post $_post ): void {
		// Check if user has permission to toggle Divi Builder.
		if ( ! et_pb_is_allowed( 'divi_builder_control' ) ) {
			return;
		}

		$post_id           = $_post->ID;
		$post_status       = get_post_status( $post_id );
		$post_status_class = 'auto-draft' === $post_status ? 'is-auto-draft' : '';
		?>
		<div class="et-fb-app__return-to-divi-editor">
			<a id="et_pb_use_the_builder" href="<?php echo esc_url( self::vb_activation_link() ); ?>" class="button button-primary button-large et_pb_ready <?php echo esc_attr( $post_status_class ); ?>">
				<?php esc_html_e( 'Use the Divi Builder', 'et_builder_5' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Get HTML element(s) that open Divi Builder placeholder overlay wrapper for Classic Editor.
	 *
	 * This method is called before the classic editor, and renders the opening
	 * HTML wrapper for the classic editor' Divi Builder placeholder overlay.
	 *
	 * @since ??
	 *
	 * @param \WP_Post $post The post object.
	 *
	 * @return void
	 */
	public static function html_prefix( \WP_Post $post ): void {
		if ( ! et_builder_enabled_for_post( $post->ID ) ) {
			return;
		}
		// TODO feat(D5, Settings) Add "Return To Default Editor" button. @see https://github.com/elegantthemes/Divi/issues/31405.

		?>
		<div class="et-fb-app--classic-editor-overlay block-editor__container">
		<div class="et-fb-app__return-to-divi-editor">
			<a id="et_pb_use_the_builder" href="<?php echo esc_url( self::vb_activation_link() ); ?>" class="button button-primary button-large et_pb_ready" style="display: none">
				<?php esc_html_e( 'Use the Divi Builder', 'et_builder_5' ); ?>
			</a>
		</div>
		<div class="et-fb-app__overlay wp-block-divi-placeholder">
			<div class="et-fb-app__overlay-content">
				<?php if ( et_pb_is_allowed( 'divi_builder_control' ) ) : ?>
					<a href="#" id="et_pb_toggle_builder" data-builder="divi" data-editor="visual-builder" class="components-button et-fb-app__overlay-button et_pb_builder_is_used">
						<?php esc_html_e( 'Switch Back To Classic Editor', 'et_builder_5' ); ?>
					</a>
				<?php endif; ?>
				<span class="et-fb-app__overlay-title et-icon"></span>
				<h3 class="et-fb-app__overlay-description">
					<?php esc_html_e( 'This Layout Is Built With Divi', 'et_builder_5' ); ?>
				</h3>
				<div class="et-fb-app__overlay-buttons et-controls">
					<a href="<?php echo esc_url( self::vb_activation_link() ); ?>" id="overlay_edit_with_divi" class="components-button is-button is-default is-large et-fb-app__overlay-button et-fb-app__overlay-button--use-divi-builder">
						<?php esc_html_e( 'Edit with the Divi Builder', 'et_builder_5' ); ?>
					</a>
				</div>
				<div id="et-vb-switch-modal-wrapper" style="display: none;">
					<div class="et-vb-switch-modal">
						<div class="et-vb-modal-header">
							<h3 class="et-vb-settings-heading"><?php esc_html_e( 'Disable Builder', 'et_builder_5' ); ?></h3>
							<a href="#" id="et-vb-cancel-switch" type="button"></a>
						</div>
						<div class="et-vb-modal-body">
							<p><?php esc_html_e( 'All content created in the Divi Builder will be lost. Previous content will be restored. Do you wish to proceed?', 'et_builder_5' ); ?></p>
						</div>
						<button id="et-vb-confirm-switch" class="et-vb-modal-button"><?php esc_html_e( 'Yes', 'et_builder_5' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get HTML element Open Divi Builder placeholder overlay wrapper for Classic Editor.
	 *
	 * This method is called after the classic editor, and renders the closing
	 * HTML wrapper for the classic editor' Divi Builder placeholder overlay.
	 *
	 * If `et_builder_enabled_for_post( $post->ID )` is `true`, no action is taken,
	 * otherwise `</div>` is printed.
	 *
	 * @since ??
	 *
	 * @param \WP_Post $post The post object.
	 *
	 * @return void
	 */
	public static function html_suffix( \WP_Post $post ): void {
		if ( ! et_builder_enabled_for_post( $post->ID ) ) {
			return;
		}

		print '</div>';
	}

	/**
	 * Get HTML element(s) that open Divi Builder placeholder overlay wrapper for Classic Editor.
	 *
	 * This method is called before the classic editor, and renders the opening
	 * HTML wrapper for the classic editor' Divi Builder placeholder overlay.
	 *
	 * @since ??
	 *
	 * @return string
	 */
	public static function vb_activation_link(): string {
		$post_id     = get_the_ID();
		$page_url    = get_the_permalink();
		$post_status = get_post_status( $post_id );

		// If Divi isn't enabled on the post, we need to supply the VB activation nonce to handle VB activation.
		$use_visual_builder_url = et_pb_is_pagebuilder_used( $post_id ) ?
		et_fb_get_vb_url() :
		add_query_arg(
			[
				'et_fb_activation_nonce' => wp_create_nonce( 'et_fb_activation_nonce_' . $post_id ),
			],
			$page_url
		);

		// If the post is a newly-created auto-draft, we need to use ajax to save the post and redirect.
		// The button shouldn't go anywhere when clicked as we click the save button instead.
		if ( 'auto-draft' === $post_status ) {
			$use_visual_builder_url = '#';
		}

		return $use_visual_builder_url;
	}

	/**
	 * Prepare Redirect URL When Use Builder Button Is Clicked
	 *
	 * For auto-draft posts, we perform a targeted save first so the post can
	 * transition to draft, then return a Visual Builder URL in the AJAX response.
	 * The client-side script handles the redirect.
	 */
	public static function set_divi_builder_redirect() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'enable_divi_in_classic_editor_nonce' ) ) {
			wp_die();
		}

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		// Ensure user has permission to edit this post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die();
		}

		// Check if this is an auto-draft that needs to be saved first.
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			wp_send_json_error( [ 'message' => 'Invalid post.' ] );
			return;
		}

		// If this is an auto-draft, we need to save it first before redirecting.
		if ( 'auto-draft' === $post->post_status ) {
			// Save the post to transition it from auto-draft to draft.
			$post_data = [
				'ID'          => $post_id,
				'post_status' => 'draft',
			];

			// If a title was provided, update it.
			if ( isset( $_POST['post_title'] ) ) {
				$post_data['post_title'] = sanitize_text_field( wp_unslash( $_POST['post_title'] ) );
			}

			// If content was provided, update it.
			if ( isset( $_POST['post_content'] ) ) {
				$post_data['post_content'] = wp_kses_post( wp_unslash( $_POST['post_content'] ) );
			}

			$result = wp_update_post( $post_data, true );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( [ 'message' => $result->get_error_message() ] );
				return;
			}
		}

		// Generate the redirect URL to the Visual Builder.
		// We need to activate the builder using the et_fb_activation_nonce.
		$redirect_url = esc_url_raw(
			add_query_arg(
				'et_fb_activation_nonce',
				wp_create_nonce( 'et_fb_activation_nonce_' . $post_id ),
				et_fb_prepare_ssl_link( get_permalink( $post_id ) )
			)
		);

		// Set flag to explicitly trigger page creation flow when redirecting to VB.
		// This is needed because Classic Editor saves the post before redirecting, making content "not empty enough".
		update_post_meta( $post_id, '_et_pb_show_page_creation', 'on' );

		// Return the redirect URL so JavaScript can navigate to the VB.
		wp_send_json_success( [ 'redirect_url' => $redirect_url ] );
	}

	/**
	 * AJAX handler to deactivate Divi Builder.
	 *
	 * This function is called when the user clicks the "Switch Back To Classic Editor" button in the
	 * Divi Builder placeholder overlay. It performs complete deactivation including:
	 * 1. Restores original content from _et_pb_old_content back to post body
	 * 2. Cleans up all Divi-related meta fields
	 * 3. Sets _et_pb_use_builder to 'off'
	 *
	 * @since ??
	 *
	 * @return void Always exits via wp_send_json_success() or wp_die().
	 */
	public static function set_deactivate_divi_builder() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'disable_divi_in_classic_editor_nonce' ) ) {
			wp_die();
		}

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die();
		}

		// Get the post object.
		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_die();
		}

		// Step 1: Restore original content from _et_pb_old_content.
		$old_content = get_post_meta( $post_id, '_et_pb_old_content', true );
		if ( ! empty( $old_content ) ) {
			// Update the post content to the restored old content.
			wp_update_post(
				[
					'ID'           => $post_id,
					'post_content' => $old_content,
				]
			);
		}

		// Step 2: Set builder state to 'off'.
		update_post_meta( $post_id, '_et_pb_use_builder', 'off' );

		// Step 3: Clean up all Divi-related meta fields.
		self::_cleanup_divi_meta_fields( $post_id );

		// Step 4: Fire hooks for extensions (like WooCommerce).
		do_action( 'et_save_post', $post_id );

		wp_send_json_success();
	}

	/**
	 * Clean up Divi-related meta fields when deactivating the builder.
	 *
	 * This function removes all meta fields that were added when Divi Builder was activated,
	 * restoring the post to its pre-Divi state while preserving global theme settings.
	 *
	 * @since ??
	 *
	 * @param int $post_id The post ID to clean up.
	 *
	 * @return void
	 */
	private static function _cleanup_divi_meta_fields( $post_id ) {
		// List of meta fields to remove when deactivating Divi Builder.
		$meta_fields_to_remove = [
			'_divi_dynamic_assets_cached_modules',
			'_divi_dynamic_assets_canvases_used',
			'_et_pb_ab_current_shortcode',
			'_et_pb_content_area_background_color',
			'_et_pb_custom_css',
			'_et_pb_enable_shortcode_tracking',
			'_et_pb_first_image',
			'_et_pb_gutter_width',
			'_et_pb_old_content', // Remove after content restoration.
			'_et_pb_page_layout',
			'_et_pb_page_z_index',
			'_et_pb_post_hide_nav',
			'_et_pb_section_background_color',
			'_et_pb_show_page_creation',
			'_et_pb_side_nav',
			'_et_pb_truncate_post',
			'_et_pb_truncate_post_date',
			'_et_pb_use_divi_5',
			// Note: _et_pb_use_builder is set to 'off', not deleted.
			// Note: _global_colors_info is preserved (site-wide theme data).
		];

		// WooCommerce-specific cleanup.
		$meta_fields_to_remove[] = WooCommerceHooks::get_product_page_content_status_meta_key();

		// Remove all the meta fields.
		foreach ( $meta_fields_to_remove as $meta_key ) {
			delete_post_meta( $post_id, $meta_key );
		}
	}

	/**
	 * Redirect To Builder After Saving Post
	 *
	 * This is the non-AJAX fallback path for legacy save flows.
	 * Auto-draft activation in modern flow is handled by
	 * `set_divi_builder_redirect()` via AJAX response redirect URL.
	 *
	 * If `_et_enable_divi_redirect` is set in post meta during a normal save
	 * request, redirect to the Visual Builder and delete the flag.
	 *
	 * @param string $post_id Post ID of the post being saved.
	 * @param object $post    Post object contains post data of post being saved.
	 * @param bool   $update  Whether or not the post is being updated or created.
	 */
	public static function redirect_to_builder( $post_id, $post, $update ) {
		// Return if the post is being saved via Ajax or a cron job.
		// We only need to redirect when a user is present.
		if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}

		// Intentionally do not bail on `$update === false`.
		// A brand new post transitions from auto-draft to draft on first save,
		// and that first save must be able to redirect into the Visual Builder.

		// Check if the current save action is an auto-save or a revision.
		// We don't want to redirect people unless they initiate a purposeful save.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$redirect_flag = get_post_meta( $post_id, '_et_enable_divi_redirect', true );

		// If the redirect flag is not set, we don't need to continue.
		if ( 'yes' !== $redirect_flag ) {
			return;
		}

		// Retrieve the current post data.
		$post_data = get_post( $post_id );

		if ( ! $post_data instanceof WP_Post || ! post_type_exists( $post_data->post_type ) ) {
			delete_post_meta( $post_id, '_et_enable_divi_redirect' );
			return;
		}

		// Ensure user has permission to edit this post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// If this post is older than 24 hours, it's not relevant.
		// Users should only get reirected when they are making a new post.
		if ( strtotime( $post_data->post_date ) < time() - DAY_IN_SECONDS ) {
			// Delete the redirect flag so it doesn't cause issues in the future.
			delete_post_meta( $post_id, '_et_enable_divi_redirect' );
			return;
		}

		// Remove the flag so it doesn't persist for future saves.
		delete_post_meta( $post_id, '_et_enable_divi_redirect' );

		// Generate the redirect URL to the Visual Builder.
		// We need to activate the builder using the et_fb_activation_nonce.
		$redirect_url = esc_url_raw(
			add_query_arg(
				'et_fb_activation_nonce',
				wp_create_nonce( 'et_fb_activation_nonce_' . $post_id ),
				et_fb_prepare_ssl_link( get_permalink( $post_id ) )
			)
		);

		// Redirect to the Visual Builder and activate it.
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Remove D4 actions.
	 *
	 * This function removes various actions related to the Divi Builder D4 version.
	 * It disables the D4 preboot script, dequeues D4 scripts, dequeues the code snippet library in VB,
	 * and dequeues D4 assets (styles & scripts).
	 * It ideally should be called during the initialization of the Divi Builder to remove these default D4 actions.
	 *
	 * @since ??
	 *
	 * @return void
	 *
	 * @example:
	 * ```php
	 *   // This function is typically called during the initialization of the Divi Builder,
	 *   // to remove the default D4 actions.
	 *
	 *   ClassicEditor::remove_d4_actions();
	 * ```
	 */
	public static function remove_d4_actions(): void {
		// Disable D4 preboot script.
		remove_action( 'wp_head', 'et_builder_inject_preboot_script', 0 );

		// Dequeue D4 scripts.
		remove_action( 'wp_footer', 'et_fb_wp_footer' );

		// Dequeue code snippet library in VB because it causes devtool failed to be loaded.
		remove_action( 'wp_enqueue_scripts', 'et_code_snippets_vb_enqueue_scripts' );

		// Dequeue D4 assets (styles & scripts).
		remove_action( 'wp_enqueue_scripts', 'et_builder_enqueue_assets_main', 99999999 );
		remove_action( 'init', 'et_builder_settings_init', 100 );
		remove_action( 'current_screen', 'et_builder_settings_init' );
		remove_action( 'init', 'et_builder_register_assets', 11 );
		remove_action( 'add_meta_boxes', 'et_pb_add_custom_box' );
		remove_action( 'add_meta_boxes', 'et_builder_prioritize_meta_box', 999999 );
		remove_action( 'init', 'et_pb_setup_theme', 11 );
	}
}

$classic_editor = new ClassicEditor();
$classic_editor->initialize();
